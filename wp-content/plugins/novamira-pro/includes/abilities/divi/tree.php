<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared module-tree machinery for the Divi write abilities (set-content,
 * add/edit/delete/move-module).
 *
 * The internal model is a list of "nodes", each
 * `array{name: string, attrs: array<array-key, mixed>, children: node[]}`.
 * Nodes are addressed by a slash path of zero-based indices matching
 * `divi-get-content`: top-level nodes are `0`, `1`, … in document order and
 * children append `/<index>`. `divi/*` blocks become editable module nodes;
 * every other top-level block (a core block, freeform text, a legacy
 * shortcode) is kept as an opaque passthrough node that occupies an address
 * slot of its own, so sections are contiguous from `0` only on a document
 * that holds nothing but Divi modules. The model round-trips a page exactly.
 *
 * Divi stores a page's builder content inside a single root
 * `<!-- wp:divi/placeholder --> … <!-- /wp:divi/placeholder -->` envelope
 * (written on Visual Builder save, D5 conversion, and portability import).
 * That envelope is TRANSPARENT to the model: parsing flattens every root-level
 * wrapper into the root list — the wrapper consumes no address, and the
 * top-level nodes keep their document-order indices whether or not the page
 * is wrapped — and saving re-emits exactly one wrapper whenever the original
 * content carried one. Divi's own consumers unwrap the FIRST envelope
 * non-greedily, so a root insert that landed as a sibling after the closing
 * marker would be silently dropped — the round trip keeps every top-level
 * node inside it.
 */

/**
 * Write-path safety caps for untrusted input trees. Recursion over an
 * unbounded `children` chain crashes the PHP worker (uncatchable SIGSEGV)
 * before any validity check runs, and an unbounded node count inflates memory
 * and the `post_content` row. Normalization enforces both before recursing.
 */
const NOVAMIRA_DIVI_MAX_INPUT_DEPTH = 64;

const NOVAMIRA_DIVI_MAX_INPUT_NODES = 2000;

/** Block name of the root envelope Divi wraps builder content in. */
const NOVAMIRA_DIVI_PLACEHOLDER_BLOCK = 'divi/placeholder';

/**
 * The name-keyed structural skeleton this integration enforced before the
 * validator became attribute-aware. It is no longer the grammar — Divi decides
 * from the parent NODE (see {@see divi_allowed_children()}) — it is the
 * TOLERANCE set: a shape this table accepted but Divi's own editor rejects is
 * reported as a warning instead of an error, so a page already written that
 * way keeps round-tripping through the write abilities.
 *
 * Keyed by module name, so it cannot tell a regular section from a fullwidth or
 * specialty one, nor an ordinary column from a specialty-section column — which
 * is exactly why it over-accepts and why it now only softens the verdict.
 *
 * @var array<string, list<string>>
 */
const NOVAMIRA_DIVI_STRUCTURE_CHILDREN = [
    'divi/section' => ['divi/row'],
    'divi/row' => ['divi/column'],
    'divi/row-inner' => ['divi/column-inner'],
    'divi/column' => ['divi/row', 'divi/row-inner', 'divi/group'],
    'divi/column-inner' => ['divi/group'],
    'divi/group' => [],
];

/**
 * Whether a placement Divi rejects but {@see NOVAMIRA_DIVI_STRUCTURE_CHILDREN}
 * used to accept is a hard error.
 *
 * Warning mode (`false`) is deliberate: pages already written by these
 * abilities can carry those shapes, and turning them into a 422 would make
 * content we can read impossible to write back. Every write path already
 * surfaces the `warnings` list, so the agent is still told. Flip this single
 * constant to `true` to promote every such placement to an error once the
 * field has had a release to converge.
 */
const NOVAMIRA_DIVI_STRICT_STRUCTURE = false;

/**
 * {@see NOVAMIRA_DIVI_STRICT_STRUCTURE}, read through a call so the mode is a
 * runtime question rather than one the analyzer folds away at every call site.
 */
function divi_strict_structure(): bool
{
    return NOVAMIRA_DIVI_STRICT_STRUCTURE;
}

/**
 * The module categories Divi treats as placeable content inside a container.
 * `unsupported` is the category Divi gives a module it could not convert from a
 * legacy shortcode, which it places wherever a content module goes.
 */
const NOVAMIRA_DIVI_CONTENT_CATEGORIES = ['module', 'fullwidth-module', 'unsupported'];

/**
 * The structural names Divi refuses inside a module whose metadata declares an
 * empty `childrenName` list (its "this module may host modules" marker).
 */
const NOVAMIRA_DIVI_NON_NESTABLE_STRUCTURE = [
    'divi/section',
    'divi/row-inner',
    'divi/column',
    'divi/column-inner',
];

/** The filter-controls container, and the child module that is one filter control. */
const NOVAMIRA_DIVI_POST_FILTER = 'divi/post-filter';

const NOVAMIRA_DIVI_POST_FILTER_ITEM = 'divi/post-filter-item';

/**
 * First Divi release whose builder lets a post-filter item sit deeper than a
 * direct child of its post filter (the Visual Builder's
 * `isValidChildWithPostFilterNesting`), and whose renderer binds such an item
 * to the loop of its NEAREST post-filter ancestor
 * (`PostFilterItemModule::_get_post_filter_ancestor_attrs()`). Older releases
 * read the target loop from the item's direct parent only, so a nested item
 * renders as an inert control with no loop-bound input name.
 */
const NOVAMIRA_DIVI_POST_FILTER_NESTING_MIN_VERSION = '5.13';

/** Attribute path Divi reads a section's layout type from (regular/fullwidth/specialty). */
const NOVAMIRA_DIVI_SECTION_TYPE_PATH = ['module', 'advanced', 'type', 'desktop', 'value'];

/** Attribute path that marks a column as the inner-row host of a specialty section. */
const NOVAMIRA_DIVI_SPECIALTY_COLUMNS_PATH = ['module', 'advanced', 'specialtyColumns', 'desktop', 'value'];

/** Attribute path holding a column's width fraction ("1_3"). */
const NOVAMIRA_DIVI_COLUMN_TYPE_PATH = ['module', 'advanced', 'type', 'desktop', 'value'];

/**
 * Parse a post's stored content into the normalized node tree.
 *
 * @return list<array<string, mixed>>
 */
function divi_parse_tree(string $content): array
{
    return divi_blocks_to_tree(divi_unwrap_root_placeholders(parse_blocks($content)));
}

/**
 * Whether stored content carries Divi's root `divi/placeholder` envelope.
 * Only root-level wrappers count; a placeholder nested deeper is an ordinary
 * (preserved) node. Symmetric with {@see divi_unwrap_root_placeholders()}.
 */
function divi_content_has_placeholder(string $content): bool
{
    foreach (parse_blocks($content) as $block) {
        if (($block['blockName'] ?? null) === NOVAMIRA_DIVI_PLACEHOLDER_BLOCK) {
            return true;
        }
    }
    return false;
}

/**
 * Splice every root-level `divi/placeholder` block's contents into the root
 * list, in document order, dropping the wrapper (and its attrs). The parser
 * keeps freeform content that sits inside a wrapper (text, HTML, legacy
 * shortcodes) in the wrapper's `innerContent` — string fragments interleaved
 * with one `null` slot per inner block — so both are walked together: a
 * non-whitespace fragment becomes the same opaque freeform block the parser
 * emits for root-level text (no name, the fragment as innerHTML/innerContent),
 * and each slot yields the corresponding inner block. Recurses so nested or
 * repeated wrappers at the root collapse into one flat list; a self-closing
 * wrapper contributes nothing. Deeper placeholders are untouched.
 *
 * @param array<array-key, mixed> $blocks
 * @return list<array<string, mixed>>
 */
function divi_unwrap_root_placeholders(array $blocks): array
{
    $flat = [];
    /** @var mixed $block */
    foreach ($blocks as $block) {
        if (!is_array($block)) {
            continue;
        }
        if (($block['blockName'] ?? null) !== NOVAMIRA_DIVI_PLACEHOLDER_BLOCK) {
            /** @var array<string, mixed> $block */
            $flat[] = $block;
            continue;
        }

        $inner = is_array($block['innerBlocks'] ?? null) ? array_values($block['innerBlocks']) : [];
        $fragments = is_array($block['innerContent'] ?? null) ? array_values($block['innerContent']) : [];
        $next = 0;
        /** @var mixed $fragment */
        foreach ($fragments as $fragment) {
            if (is_string($fragment)) {
                if (trim($fragment) !== '') {
                    $flat[] = divi_freeform_block($fragment);
                }
                continue;
            }
            // A null slot stands for the next inner block, in order.
            if (array_key_exists($next, $inner)) {
                foreach (divi_unwrap_root_placeholders([$inner[$next]]) as $child) {
                    $flat[] = $child;
                }
                $next++;
            }
        }
        // Inner blocks without a matching slot (a malformed parse) still count.
        for (; $next < count($inner); $next++) {
            foreach (divi_unwrap_root_placeholders([$inner[$next]]) as $child) {
                $flat[] = $child;
            }
        }
    }
    return $flat;
}

/**
 * The opaque freeform block the block parser emits for non-block content at
 * the root: no name, no attrs, the raw fragment as both innerHTML and the sole
 * innerContent entry. serialize_blocks re-emits it verbatim.
 *
 * @return array{blockName: null, attrs: array<array-key, mixed>, innerBlocks: array<array-key, mixed>, innerHTML: string, innerContent: list<string>}
 */
function divi_freeform_block(string $fragment): array
{
    return [
        'blockName' => null,
        'attrs' => [],
        'innerBlocks' => [],
        'innerHTML' => $fragment,
        'innerContent' => [$fragment],
    ];
}

/**
 * @param array<array-key, array<string, mixed>> $blocks
 * @return list<array<string, mixed>>
 */
function divi_blocks_to_tree(array $blocks): array
{
    $tree = [];
    foreach ($blocks as $block) {
        $name = is_string($block['blockName'] ?? null) ? $block['blockName'] : '';

        if (str_starts_with($name, 'divi/')) {
            /** @var list<array<string, mixed>> $inner */
            $inner = is_array($block['innerBlocks'] ?? null) ? $block['innerBlocks'] : [];
            $tree[] = [
                'name' => $name,
                'attrs' => is_array($block['attrs'] ?? null) ? $block['attrs'] : [],
                'children' => divi_blocks_to_tree($inner),
            ];
            continue;
        }

        // Drop insignificant inter-block whitespace; preserve every other
        // non-Divi block verbatim as an opaque passthrough node so writes never
        // silently delete it (it occupies an address slot but isn't editable).
        $html = is_string($block['innerHTML'] ?? null) ? $block['innerHTML'] : '';
        if ($name === '' && trim($html) === '') {
            continue;
        }
        $tree[] = ['name' => $name, '_raw' => $block, 'children' => []];
    }
    return $tree;
}

/**
 * Whether a normalized tree node is an editable Divi module (vs an opaque
 * non-Divi passthrough preserved for round-trip).
 *
 * @param array<string, mixed> $node
 */
function divi_is_divi_node(array $node): bool
{
    return !array_key_exists('_raw', $node);
}

/**
 * Serialize the normalized node tree back to stored block content (bare root
 * list, no envelope).
 *
 * @param list<array<string, mixed>> $tree
 */
function divi_serialize_tree(array $tree): string
{
    return serialize_blocks(divi_tree_to_blocks($tree));
}

/**
 * Serialize the normalized node tree enclosed in exactly one
 * `divi/placeholder` envelope, byte-identical to the one Divi writes (bare
 * opening marker, content, closing marker, no attrs).
 *
 * @param list<array<string, mixed>> $tree
 */
function divi_serialize_wrapped_tree(array $tree): string
{
    return (
        '<!-- wp:'
        . NOVAMIRA_DIVI_PLACEHOLDER_BLOCK
        . ' -->'
        . divi_serialize_tree($tree)
        . '<!-- /wp:'
        . NOVAMIRA_DIVI_PLACEHOLDER_BLOCK
        . ' -->'
    );
}

/**
 * @param list<array<string, mixed>> $tree
 * @return list<array{blockName: string|null, attrs: array<array-key, mixed>, innerBlocks: array<array-key, array<array-key, mixed>>, innerHTML: string, innerContent: array<array-key, mixed>}>
 */
function divi_tree_to_blocks(array $tree): array
{
    $blocks = [];
    foreach ($tree as $node) {
        /** @var mixed $raw */
        $raw = $node['_raw'] ?? null;
        if (is_array($raw)) {
            // Opaque non-Divi passthrough: re-emit the original block verbatim.
            $blocks[] = divi_passthrough_block($raw);
            continue;
        }
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $inner = divi_tree_to_blocks($children);
        $blocks[] = [
            'blockName' => is_string($node['name'] ?? null) ? $node['name'] : '',
            'attrs' => is_array($node['attrs'] ?? null) ? $node['attrs'] : [],
            'innerBlocks' => $inner,
            'innerHTML' => '',
            'innerContent' => array_fill(0, count($inner), value: null),
        ];
    }
    return $blocks;
}

/**
 * Re-emit an opaque non-Divi block from its preserved raw form, coercing fields
 * to the block shape serialize_blocks expects.
 *
 * @param array<array-key, mixed> $raw
 * @return array{blockName: string|null, attrs: array<array-key, mixed>, innerBlocks: array<array-key, array<array-key, mixed>>, innerHTML: string, innerContent: array<array-key, mixed>}
 */
function divi_passthrough_block(array $raw): array
{
    $inner = [];
    /** @var mixed $child */
    foreach (is_array($raw['innerBlocks'] ?? null) ? $raw['innerBlocks'] : [] as $child) {
        if (!is_array($child)) {
            continue;
        }
        $inner[] = $child;
    }
    return [
        'blockName' => is_string($raw['blockName'] ?? null) ? $raw['blockName'] : null,
        'attrs' => is_array($raw['attrs'] ?? null) ? $raw['attrs'] : [],
        'innerBlocks' => $inner,
        'innerHTML' => is_string($raw['innerHTML'] ?? null) ? $raw['innerHTML'] : '',
        'innerContent' => is_array($raw['innerContent'] ?? null) ? $raw['innerContent'] : [],
    ];
}

/**
 * Total node count in the tree.
 *
 * @param list<array<string, mixed>> $tree
 */
function divi_tree_count(array $tree): int
{
    $count = 0;
    foreach ($tree as $node) {
        $count++;
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $count += divi_tree_count($children);
    }
    return $count;
}

/**
 * Split an address into [parent_address, index]. Root nodes return ['', idx].
 *
 * @return array{0: string, 1: int}
 */
function divi_split_address(string $address): array
{
    $segments = explode('/', $address);
    $index = (int) array_pop($segments);
    return [implode('/', $segments), $index];
}

/**
 * The node at an address, or null when the path does not resolve.
 *
 * @param list<array<string, mixed>> $tree
 * @return array<string, mixed>|null
 */
function divi_tree_get(array $tree, string $address): ?array
{
    $nodes = $tree;
    $found = null;
    foreach (explode('/', $address) as $segment) {
        // Reject non-numeric/empty/non-canonical segments rather than coercing
        // them — a typo like "//" or "foo/bar" must miss, not silently hit
        // module 0, and a leading-zero index ("02", "00") must not alias to a
        // different real module (a silent wrong write on the destructive paths).
        if ($segment === '' || !ctype_digit($segment) || $segment !== '0' && $segment[0] === '0') {
            return null;
        }
        $index = (int) $segment;
        if (!array_key_exists($index, $nodes)) {
            return null;
        }
        $found = $nodes[$index];
        /** @var list<array<string, mixed>> $nodes */
        $nodes = is_array($found['children'] ?? null) ? $found['children'] : [];
    }
    return $found;
}

/**
 * The children list of the node at an address ([] when the node is missing or
 * has no children). Used for counting, so the element type is left loose.
 *
 * @param list<array<string, mixed>> $tree
 * @return array<array-key, mixed>
 */
function divi_node_children(array $tree, string $address): array
{
    $node = divi_tree_get($tree, $address);
    if ($node === null) {
        return [];
    }
    return is_array($node['children'] ?? null) ? $node['children'] : [];
}

/**
 * Apply a children-transform to the children list of the node at
 * `$parent_address` (empty string = the root list). The callback receives the
 * current children list and returns the new one, or null to signal failure
 * (which aborts and returns null). Returns the new tree, or null on failure.
 *
 * @param list<array<string, mixed>> $tree
 * @param callable(list<array<string, mixed>>): (list<array<string, mixed>>|null) $op
 * @return list<array<string, mixed>>|null
 */
function divi_tree_update_children(array $tree, string $parent_address, callable $op): ?array
{
    if ($parent_address === '') {
        return $op($tree);
    }
    return divi_tree_descend($tree, explode('/', $parent_address), $op);
}

/**
 * @param list<array<string, mixed>> $nodes
 * @param list<string> $path
 * @param callable(list<array<string, mixed>>): (list<array<string, mixed>>|null) $op
 * @return list<array<string, mixed>>|null
 */
function divi_tree_descend(array $nodes, array $path, callable $op): ?array
{
    $index = (int) array_shift($path);
    if (!array_key_exists($index, $nodes)) {
        return null;
    }

    /** @var list<array<string, mixed>> $children */
    $children = is_array($nodes[$index]['children'] ?? null) ? $nodes[$index]['children'] : [];

    $updated = $path === [] ? $op($children) : divi_tree_descend($children, $path, $op);
    if ($updated === null) {
        return null;
    }

    $nodes[$index]['children'] = $updated;
    return $nodes;
}

/**
 * Replace the node at an address. Returns null when the address does not exist.
 *
 * @param list<array<string, mixed>> $tree
 * @param array<string, mixed> $node
 * @return list<array<string, mixed>>|null
 */
function divi_tree_replace(array $tree, string $address, array $node): ?array
{
    [$parent, $index] = divi_split_address($address);
    return divi_tree_update_children($tree, $parent, static function (array $children) use ($index, $node): ?array {
        if (!array_key_exists($index, $children)) {
            return null;
        }
        $children[$index] = $node;
        return $children;
    });
}

/**
 * Remove the node (and its subtree) at an address.
 *
 * @param list<array<string, mixed>> $tree
 * @return list<array<string, mixed>>|null
 */
function divi_tree_remove(array $tree, string $address): ?array
{
    [$parent, $index] = divi_split_address($address);
    return divi_tree_update_children($tree, $parent, static function (array $children) use ($index): ?array {
        if (!array_key_exists($index, $children)) {
            return null;
        }
        array_splice($children, $index, length: 1);
        return $children;
    });
}

/**
 * Insert a node into the children of `$parent_address` (empty = root) at
 * `$position` (clamped to the valid range).
 *
 * @param list<array<string, mixed>> $tree
 * @param array<string, mixed> $node
 * @return list<array<string, mixed>>|null
 */
function divi_tree_insert(array $tree, string $parent_address, int $position, array $node): ?array
{
    return divi_tree_update_children($tree, $parent_address, static function (array $children) use (
        $position,
        $node,
    ): array {
        $pos = max(0, min($position, count($children)));
        array_splice($children, $pos, length: 0, replacement: [$node]);
        return $children;
    });
}

/**
 * Read a nested attribute leaf off a node, or null when the path does not
 * resolve. Divi's own structural predicate reads these leaves directly, so the
 * lookup deliberately does not fall back to another breakpoint.
 *
 * @param array<string, mixed> $node
 * @param list<string> $path
 * @return mixed
 */
function divi_node_attr(array $node, array $path)
{
    /** @var mixed $cursor */
    $cursor = $node['attrs'] ?? null;
    foreach ($path as $segment) {
        if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            return null;
        }
        /** @var mixed $cursor */
        $cursor = $cursor[$segment];
    }
    return $cursor;
}

/**
 * A section's layout type — `fullwidth`, `specialty`, or anything else (an
 * unset or `regular` type is the ordinary row-holding section).
 *
 * @param array<string, mixed> $node
 */
function divi_section_type(array $node): string
{
    /** @var mixed $type */
    $type = divi_node_attr($node, NOVAMIRA_DIVI_SECTION_TYPE_PATH);
    return is_string($type) ? $type : '';
}

/**
 * JavaScript truthiness, which is what Divi's selector applies to the raw
 * metadata and attribute values it branches on. Only `undefined` / `null` /
 * `false` / `''` / `0` are falsy there — the string `"0"` and an empty array
 * are both truthy in JavaScript, so neither may be folded into false here.
 *
 * @param mixed $value
 */
function divi_js_truthy($value): bool
{
    return !($value === null || $value === false || $value === '' || $value === 0 || $value === 0.0);
}

/**
 * Whether a column is the inner-row host of a specialty section, i.e. carries a
 * truthy `specialtyColumns` value.
 *
 * @param array<string, mixed> $node
 */
function divi_column_hosts_inner_rows(array $node): bool
{
    return divi_js_truthy(divi_node_attr($node, NOVAMIRA_DIVI_SPECIALTY_COLUMNS_PATH));
}

/**
 * The parent module name a module's metadata declares, or null when it declares
 * none. Divi falls back to this comparison for a parent whose `childrenName` is
 * absent or is not a list.
 */
function divi_module_parent_name(string $name): ?string
{
    $meta = divi_module_metadata($name);
    if ($meta === null) {
        return null;
    }
    /** @var mixed $parent */
    $parent = $meta['parentName'] ?? null;
    return is_string($parent) && $parent !== '' ? $parent : null;
}

/**
 * A module's registry category, with Divi's own fallback for a name the module
 * registry does not know (`unsupported`, the category it gives an unconverted
 * legacy shortcode). Never returns an empty string.
 */
function divi_module_category(string $name): string
{
    $meta = divi_module_metadata($name);
    $category = $meta !== null && is_string($meta['category'] ?? null) ? $meta['category'] : '';
    return $category === '' ? 'unsupported' : $category;
}

/**
 * Assemble a child allowance.
 *
 * @param list<string> $names names accepted whatever their category
 * @param list<string>|null $categories accepted categories (null = every category)
 * @param list<string> $deny_names names refused even when their category matches
 * @param list<string> $deny_categories categories refused even inside `null` categories
 * @param string|null $parent_of accept any child whose metadata names this module as its parent
 * @return array{names: list<string>, categories: list<string>|null, deny_names: list<string>, deny_categories: list<string>, parent_of: string|null}
 */
function divi_allowance(
    array $names,
    ?array $categories = [],
    array $deny_names = [],
    array $deny_categories = [],
    ?string $parent_of = null,
): array {
    return [
        'names' => $names,
        'categories' => $categories,
        'deny_names' => $deny_names,
        'deny_categories' => $deny_categories,
        'parent_of' => $parent_of,
    ];
}

/**
 * What THIS INTEGRATION accepts inside a parent NODE (null = the document root).
 *
 * A port of the module-library store's `isValidChild(parentName, parentAttrs,
 * childName)` selector, with one deliberate divergence. The grammar is
 * conditioned on the parent's ATTRIBUTES, not just its name — a section's
 * `module.advanced.type` decides between rows, fullwidth modules and specialty
 * columns, and a column's `module.advanced.specialtyColumns` decides between
 * inner rows and ordinary content.
 *
 * THE DIVERGENCE: Divi accepts `divi/column` directly inside a specialty
 * section; this returns an empty allowance there instead, because the rendered
 * width of such a column cannot be resolved here (see
 * {@see divi_specialty_section_note()}). It is a feature refusal, NOT a
 * correction of Divi — do not "restore" it to `['divi/column']` without
 * building the resolution it stands in for. The empty allowance is also load
 * bearing in the other direction: it leaves the `divi/row` edge, which Divi
 * really does reject, to the legacy tolerance so a retype is still reported.
 *
 * @param array<string, mixed>|null $parent
 * @return array{names: list<string>, categories: list<string>|null, deny_names: list<string>, deny_categories: list<string>, parent_of: string|null}
 */
function divi_allowed_children(?array $parent): array
{
    if ($parent === null) {
        // Divi's `divi/root` accepts sections only.
        return divi_allowance(['divi/section']);
    }

    $name = is_string($parent['name'] ?? null) ? $parent['name'] : '';

    if ($name === 'divi/section') {
        $type = divi_section_type($parent);
        if ($type === 'fullwidth') {
            // A fullwidth section holds modules directly and no row at all.
            return divi_allowance([], NOVAMIRA_DIVI_CONTENT_CATEGORIES);
        }
        if ($type === 'specialty') {
            // Nothing is VALID here. Divi gives a specialty section
            // `divi/column` children, which this integration cannot grade (see
            // divi_specialty_section_note()), and it refuses `divi/row`, which
            // the pre-attribute-aware table accepted. An empty allowance keeps
            // both answers right: the column is refused, and the row falls
            // through to the legacy tolerance and is reported as a warning
            // rather than passing as valid — which is what keeps retyping a
            // row-holding section to `specialty` visible.
            return divi_allowance([]);
        }
        return divi_allowance(['divi/row']);
    }

    if ($name === 'divi/row') {
        return divi_allowance(['divi/column']);
    }

    if ($name === 'divi/row-inner') {
        return divi_allowance(['divi/column-inner']);
    }

    if ($name === 'divi/column') {
        if (divi_column_hosts_inner_rows($parent)) {
            return divi_allowance(['divi/row-inner']);
        }
        return divi_allowance(['divi/row', 'divi/group'], NOVAMIRA_DIVI_CONTENT_CATEGORIES);
    }

    if ($name === 'divi/column-inner') {
        return divi_allowance(['divi/group'], NOVAMIRA_DIVI_CONTENT_CATEGORIES);
    }

    if ($name === 'divi/group') {
        return divi_allowance(['divi/group', 'divi/row'], NOVAMIRA_DIVI_CONTENT_CATEGORIES);
    }

    if ($name === 'divi/group-carousel') {
        return divi_allowance(['divi/group']);
    }

    return divi_metadata_allowed_children($name);
}

/**
 * The allowance a non-structural parent declares through its own metadata.
 *
 * Divi reads the parent's `childrenName`: when it is absent — or present but not
 * a list — the answer is child-dependent, `parentName === <the child's declared
 * parent>`; an EMPTY list is the container marker that opens the module to
 * everything except a child-module and the four structural names; a named list
 * is exactly that list, widened to content modules when the parent also sets a
 * truthy `allowAllElements`.
 *
 * @return array{names: list<string>, categories: list<string>|null, deny_names: list<string>, deny_categories: list<string>, parent_of: string|null}
 */
function divi_metadata_allowed_children(string $parent_name): array
{
    $meta = divi_module_metadata($parent_name);
    /** @var mixed $children_name */
    $children_name = $meta === null ? null : $meta['childrenName'] ?? null;
    // Divi tests `Array.isArray`, which a JSON object fails — and a JSON object
    // decodes to an associative PHP array, which `is_array` alone would accept.
    if (!is_array($children_name) || !divi_is_list($children_name)) {
        // No shipped module declares a `parentName`, so this accepts nothing
        // today; it is ported as the child-dependent comparison Divi makes so a
        // module that does declare one is placed correctly.
        return divi_allowance([], parent_of: $parent_name);
    }

    /** @var array<string, mixed> $meta */
    $children = divi_children_names($meta);
    if ($children === []) {
        return divi_allowance(
            [],
            categories: null,
            deny_names: NOVAMIRA_DIVI_NON_NESTABLE_STRUCTURE,
            deny_categories: ['child-module'],
        );
    }

    $allow_all = divi_js_truthy($meta['allowAllElements'] ?? null);
    return divi_allowance($children, $allow_all ? ['module', 'fullwidth-module'] : []);
}

/**
 * Whether a module is a placeable leaf (non-structural, non-child) module.
 * Supports the legacy tolerance table only.
 */
function divi_is_leaf_module(string $name): bool
{
    $meta = divi_module_metadata($name);
    if ($meta === null) {
        return false;
    }
    $category = is_string($meta['category'] ?? null) ? $meta['category'] : '';
    return in_array($category, ['module', 'fullwidth-module'], strict: true);
}

/**
 * Whether this integration accepts `$child_name` inside the parent NODE (null =
 * the root). That is Divi's answer everywhere except a specialty section, where
 * it is narrower on purpose — see {@see divi_allowed_children()}.
 *
 * @param array<string, mixed>|null $parent
 */
function divi_child_allowed(string $child_name, ?array $parent): bool
{
    return divi_child_matches($child_name, divi_allowed_children($parent));
}

/**
 * Whether `$child_name` satisfies a resolved allowance.
 *
 * @param array{names: list<string>, categories: list<string>|null, deny_names: list<string>, deny_categories: list<string>, parent_of: string|null} $allowed
 */
function divi_child_matches(string $child_name, array $allowed): bool
{
    if (in_array($child_name, $allowed['deny_names'], strict: true)) {
        return false;
    }
    if (in_array($child_name, $allowed['names'], strict: true)) {
        return true;
    }
    if ($allowed['parent_of'] !== null && divi_module_parent_name($child_name) === $allowed['parent_of']) {
        return true;
    }
    $category = divi_module_category($child_name);
    if (in_array($category, $allowed['deny_categories'], strict: true)) {
        return false;
    }
    return $allowed['categories'] === null || in_array($category, $allowed['categories'], strict: true);
}

/**
 * Whether the pre-attribute-aware skeleton accepted this placement. Drives the
 * tolerance verdict only — see {@see NOVAMIRA_DIVI_STRUCTURE_CHILDREN}.
 */
function divi_child_allowed_legacy(string $child_name, ?string $parent_name): bool
{
    if ($parent_name === null) {
        return $child_name === 'divi/section';
    }
    if (array_key_exists($parent_name, NOVAMIRA_DIVI_STRUCTURE_CHILDREN)) {
        if (in_array($child_name, NOVAMIRA_DIVI_STRUCTURE_CHILDREN[$parent_name], strict: true)) {
            return true;
        }
        return (
            in_array($parent_name, ['divi/column', 'divi/column-inner', 'divi/group'], strict: true)
            && divi_is_leaf_module($child_name)
        );
    }
    $meta = divi_module_metadata($parent_name);
    return $meta !== null && in_array($child_name, divi_children_names($meta), strict: true);
}

/**
 * Whether the installed Divi accepts a post-filter item nested deeper than a
 * direct child of its post filter — see
 * {@see NOVAMIRA_DIVI_POST_FILTER_NESTING_MIN_VERSION}. Below it the widened
 * placement stays refused, because that Divi's builder rejects it and its
 * renderer leaves the nested control unbound from the loop.
 */
function divi_post_filter_nesting_supported(): bool
{
    $version = \Novamira\Pro\novamira_pro_divi_builder_version();
    return $version !== '' && version_compare($version, NOVAMIRA_DIVI_POST_FILTER_NESTING_MIN_VERSION, operator: '>=');
}

/**
 * Divi's post-filter nesting exception to `isValidChild`, a port of the Visual
 * Builder's `isValidChildWithPostFilterNesting`: a `divi/post-filter-item`
 * that the ordinary grammar refuses is still accepted under any parent that
 * accepts a regular content module (Divi probes the parent with `divi/text`)
 * as long as that parent IS, or descends from, a `divi/post-filter`. There is
 * no depth limit and any post-filter ancestor qualifies; the item binds to the
 * nearest one.
 *
 * Divi's companion move rule — a subtree holding post-filter items cannot
 * leave its post filter unless its destination is inside one — needs no
 * separate check here: every insert re-grades the whole moved subtree against
 * its destination, and an item whose post filter stayed behind fails this
 * predicate there exactly when Divi's move rule refuses the move.
 *
 * @param array<string, mixed>|null $parent
 * @param array<string, mixed>|null $post_filter the divi/post-filter `$parent` is or descends from, if any
 */
function divi_post_filter_item_nests(string $child_name, ?array $parent, ?array $post_filter): bool
{
    return $child_name === NOVAMIRA_DIVI_POST_FILTER_ITEM
    && $parent !== null
    && $post_filter !== null
    && divi_post_filter_nesting_supported()
    && divi_child_allowed('divi/text', $parent);
}

/**
 * The nearest `divi/post-filter` that the node at `$address` ('' = the root)
 * is or descends from, or null — the ancestry
 * {@see divi_post_filter_item_nests()} reads.
 *
 * @param list<array<string, mixed>> $tree
 * @return array<string, mixed>|null
 */
function divi_address_post_filter(array $tree, string $address): ?array
{
    if ($address === '') {
        return null;
    }
    $post_filter = null;
    $prefix = '';
    foreach (explode('/', $address) as $segment) {
        $prefix = $prefix === '' ? $segment : $prefix . '/' . $segment;
        $node = divi_tree_get($tree, $prefix);
        if ($node === null) {
            return null;
        }
        $post_filter = divi_children_post_filter($node, $post_filter);
    }
    return $post_filter;
}

/**
 * The nearest post filter a node's CHILDREN sit in: the node itself when it is
 * one, otherwise the one the node sits in.
 *
 * @param array<string, mixed> $node
 * @param array<string, mixed>|null $post_filter the post filter `$node` sits in, if any
 * @return array<string, mixed>|null
 */
function divi_children_post_filter(array $node, ?array $post_filter): ?array
{
    return ($node['name'] ?? null) === NOVAMIRA_DIVI_POST_FILTER ? $node : $post_filter;
}

/**
 * Classify a placement: `allowed` when this integration accepts it, `tolerated`
 * when only the legacy skeleton did (a warning, so the page still round-trips),
 * `refused` otherwise.
 *
 * `allowed` is Divi's own answer everywhere except inside a specialty section,
 * which is refused here by choice rather than by Divi's grammar — see
 * {@see divi_allowed_children()}. It includes Divi's post-filter nesting
 * exception, which reads the parent's ancestry — see
 * {@see divi_post_filter_item_nests()}.
 *
 * @param array<string, mixed>|null $parent
 * @param array<string, mixed>|null $post_filter the divi/post-filter `$parent` is or descends from, if any
 * @return 'allowed'|'tolerated'|'refused'
 */
function divi_placement_verdict(string $child_name, ?array $parent, ?array $post_filter = null): string
{
    if (divi_child_allowed($child_name, $parent)) {
        return 'allowed';
    }
    if (divi_post_filter_item_nests($child_name, $parent, $post_filter)) {
        return 'allowed';
    }
    if (!divi_strict_structure() && divi_child_allowed_legacy($child_name, divi_parent_name($parent))) {
        return 'tolerated';
    }
    return 'refused';
}

/**
 * A parent node's module name, or null for the document root.
 *
 * @param array<string, mixed>|null $parent
 */
function divi_parent_name(?array $parent): ?string
{
    if ($parent === null) {
        return null;
    }
    return is_string($parent['name'] ?? null) ? $parent['name'] : '';
}

/**
 * How a parent reads in a message, naming the attribute-driven flavour so the
 * agent can tell the three sections and the two columns apart.
 *
 * @param array<string, mixed>|null $parent
 */
function divi_parent_label(?array $parent): string
{
    if ($parent === null) {
        return 'the top level';
    }
    $name = is_string($parent['name'] ?? null) ? $parent['name'] : '';
    if ($name === 'divi/section') {
        $type = divi_section_type($parent);
        if ($type === 'fullwidth') {
            return 'a fullwidth "divi/section"';
        }
        if ($type === 'specialty') {
            return 'a specialty "divi/section"';
        }
        return 'a regular "divi/section"';
    }
    if ($name === 'divi/column' && divi_column_hosts_inner_rows($parent)) {
        return 'a specialty-section "divi/column"';
    }
    return sprintf('"%s"', $name);
}

/**
 * A plain-language list of what a parent accepts, rendered ENTIRELY from the
 * resolved allowance — including its exclusions — so the sentence cannot drift
 * from the predicate and recommend a construct the same call just refused.
 *
 * @param array<string, mixed>|null $parent
 */
function divi_allowed_children_hint(?array $parent): string
{
    $allowed = divi_allowed_children($parent);
    $parts = $allowed['names'];
    if ($allowed['categories'] === null) {
        $parts[] = divi_open_allowance_phrase($allowed);
    }
    foreach (divi_advertisable_categories($allowed['categories'] ?? []) as $category) {
        $parts[] = divi_category_label($category);
    }
    // Only worth naming when a registered module really declares that parent;
    // none does today, so this reads as "no children at all" rather than as an
    // instruction an agent cannot act on.
    if ($allowed['parent_of'] !== null && divi_modules_declaring_parent($allowed['parent_of']) !== []) {
        $parts[] = sprintf('modules whose metadata names "%s" as their parent', $allowed['parent_of']);
    }
    return $parts === [] ? 'no children at all' : implode(', ', $parts);
}

/**
 * The phrase for an allowance that opens every category.
 *
 * "Any module" would be a promise this integration does not keep: an open
 * container accepts any category Divi knows, including names its registry does
 * not know at all, and every one of those is refused by the unknown-module
 * check before a nesting rule runs. So the phrase is built from the categories
 * a registered module can actually be written with, minus the ones this
 * allowance denies, and then names the structural exclusions.
 *
 * @param array{names: list<string>, categories: list<string>|null, deny_names: list<string>, deny_categories: list<string>, parent_of: string|null} $allowed
 */
function divi_open_allowance_phrase(array $allowed): string
{
    $writable = [];
    foreach (divi_advertisable_categories(divi_registered_categories()) as $category) {
        if (in_array($category, $allowed['deny_categories'], strict: true)) {
            continue;
        }
        $writable[] = divi_category_label($category);
    }
    $phrase = $writable === [] ? 'no module category' : implode(', ', $writable);
    if ($allowed['deny_names'] !== []) {
        $phrase .= sprintf(' (and never %s)', implode(', ', $allowed['deny_names']));
    }
    return $phrase;
}

/**
 * The registered modules that declare `$parent_name` as their `parentName`.
 *
 * @return list<string>
 */
function divi_modules_declaring_parent(string $parent_name): array
{
    $names = [];
    foreach (divi_all_modules_metadata() as $meta) {
        /** @var mixed $declared */
        $declared = $meta['parentName'] ?? null;
        $name = is_string($meta['name'] ?? null) ? $meta['name'] : '';
        if ($declared !== $parent_name || $name === '') {
            continue;
        }
        $names[] = $name;
    }
    return $names;
}

/**
 * Every category some registered module carries, in a stable order.
 *
 * @return list<string>
 */
function divi_registered_categories(): array
{
    $categories = [];
    foreach (divi_all_modules_metadata() as $meta) {
        /** @var mixed $category */
        $category = $meta['category'] ?? null;
        if (!is_string($category) || $category === '' || in_array($category, $categories, strict: true)) {
            continue;
        }
        $categories[] = $category;
    }
    sort($categories);
    return $categories;
}

/**
 * The subset of an allowance's categories worth naming to an agent: those some
 * registered module actually has.
 *
 * Divi accepts an `unsupported` child — its own fallback category for a name
 * its registry does not know — but every such name fails this integration's
 * unknown-module check first, so naming that category in an error would offer a
 * construct the very same call refuses. Filtering on the live registry keeps
 * the hint honest by construction: a category becomes advertisable the moment a
 * real module has it, and stops being advertisable when none does.
 *
 * @param list<string> $categories
 * @return list<string>
 */
function divi_advertisable_categories(array $categories): array
{
    $present = [];
    foreach (divi_all_modules_metadata() as $meta) {
        /** @var mixed $category */
        $category = $meta['category'] ?? null;
        if (!is_string($category)) {
            continue;
        }
        $present[$category] = true;
    }
    $advertisable = [];
    foreach ($categories as $category) {
        if (!isset($present[$category])) {
            continue;
        }
        $advertisable[] = $category;
    }
    return $advertisable;
}

/** How a module category reads in a message. */
function divi_category_label(string $category): string
{
    return match ($category) {
        'module' => 'content modules',
        'fullwidth-module' => 'fullwidth modules',
        'child-module' => 'child modules',
        'unsupported' => 'unconverted shortcode modules',
        'structure' => 'structural modules',
        default => sprintf('"%s" modules', $category),
    };
}

/**
 * The message for a non-`allowed` placement: an error sentence for `refused`, a
 * round-trip advisory for `tolerated`.
 *
 * @param array<string, mixed>|null $parent
 */
function divi_placement_message(string $child_name, ?array $parent, string $verdict): string
{
    if ($verdict === 'tolerated') {
        $specialty = divi_specialty_section_note($parent ?? []);
        if ($specialty !== null) {
            return sprintf(
                '"%s" sits inside %s, which Divi\'s own editor does not accept there. %s It was kept so the page '
                . 'still round-trips, but the builder may not render or edit it as intended.',
                $child_name,
                divi_parent_label($parent),
                $specialty,
            );
        }
        return sprintf(
            '"%s" sits inside %s, which Divi\'s own editor does not accept — it accepts %s there. '
            . 'It was kept so the page still round-trips, but the builder may not render or edit it as intended.',
            $child_name,
            divi_parent_label($parent),
            divi_allowed_children_hint($parent),
        );
    }
    if ($parent === null) {
        return sprintf('"%s" is not allowed at the top level — only divi/section may sit at the root.', $child_name);
    }
    $note = divi_specialty_section_note($parent);
    if ($note !== null) {
        return sprintf('"%s" is not allowed inside %s. %s', $child_name, divi_parent_label($parent), $note);
    }
    return sprintf(
        '"%s" is not allowed inside %s — it accepts %s.%s',
        $child_name,
        divi_parent_label($parent),
        divi_allowed_children_hint($parent),
        divi_post_filter_item_note($child_name),
    );
}

/**
 * The extra sentence a refused `divi/post-filter-item` gets: where this site's
 * Divi does accept one, since no parent's own hint names it. Empty for every
 * other module.
 */
function divi_post_filter_item_note(string $child_name): string
{
    if ($child_name !== NOVAMIRA_DIVI_POST_FILTER_ITEM) {
        return '';
    }
    if (divi_post_filter_nesting_supported()) {
        return (
            ' A divi/post-filter-item must stay inside a divi/post-filter: either as its direct child, or at any '
            . 'depth below a module nested in it that accepts content modules (for example a divi/text placed in '
            . 'the filter).'
        );
    }
    return ' On this site\'s Divi, a divi/post-filter-item must be a direct child of divi/post-filter.';
}

/**
 * The note that replaces the usual "it accepts …" hint for a specialty section.
 *
 * Divi builds a specialty section out of direct `divi/column` children, and how
 * wide each one renders is decided by EFFECTIVE sizing — the column's sizing
 * attrs after preset resolution, across every breakpoint — which this
 * integration cannot resolve, so it cannot tell a layout that renders as asked
 * from one that does not. Creating or replacing those columns is refused on the
 * paths that validate a tree, and this note replaces the generic hint, which
 * would otherwise name `divi/row` — the one child Divi itself refuses there.
 * Returns null for every other parent.
 *
 * @param array<string, mixed> $parent
 */
function divi_specialty_section_note(array $parent): ?string
{
    $name = is_string($parent['name'] ?? null) ? $parent['name'] : '';
    if ($name !== 'divi/section' || divi_section_type($parent) !== 'specialty') {
        return null;
    }
    return (
        'A specialty section lays out direct divi/column children whose rendered widths depend on effective '
        . 'sizing these abilities cannot resolve, so writing those columns is refused. Edit this section in the '
        . 'Visual Builder, or build the layout as a regular section with divi/row > divi/column.'
    );
}

/**
 * Collect the placement messages for a forest, recursing with each node as the
 * parent of its own children. Non-Divi passthrough blocks are skipped: Divi
 * stores arbitrary blocks and the round trip preserves them verbatim.
 *
 * @param list<array<string, mixed>> $nodes
 * @param array<string, mixed>|null $parent
 * @param list<string> $messages
 * @param array<string, mixed>|null $post_filter the divi/post-filter `$parent` is or descends from, if any
 */
function divi_collect_placement_messages(
    array $nodes,
    ?array $parent,
    array &$messages,
    ?array $post_filter = null,
): void {
    foreach ($nodes as $node) {
        if (!divi_is_divi_node($node)) {
            continue;
        }
        $name = is_string($node['name'] ?? null) ? $node['name'] : '';
        if (divi_module_metadata($name) !== null) {
            $verdict = divi_placement_verdict($name, $parent, $post_filter);
            if ($verdict !== 'allowed') {
                $messages[] = divi_placement_message($name, $parent, $verdict);
            }
        }
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        divi_collect_placement_messages($children, $node, $messages, divi_children_post_filter($node, $post_filter));
    }
}

/**
 * Validate a node tree: every node is a known module, every parent/child
 * relationship respects the structural skeleton, and the tree is not made up
 * solely of raw-HTML code modules.
 *
 * @param list<array<string, mixed>> $tree
 * @return array{errors: list<string>, warnings: list<string>}
 */
function divi_validate_tree(array $tree): array
{
    $errors = [];
    $warnings = [];
    divi_validate_level($tree, parent: null, errors: $errors, warnings: $warnings);

    if (divi_tree_is_raw_html_only($tree)) {
        $errors[] =
            'The layout consists only of raw-HTML code modules (divi/code / divi/fullwidth-code). '
            . 'Build the design from native Divi modules (heading, text, image, button, blurb, …) instead of '
            . 'dumping raw HTML.';
    }

    // Dedupe and cap so a malformed forest cannot emit a multi-KB wall of
    // near-identical sentences that floods the agent's context.
    return ['errors' => divi_cap_messages($errors), 'warnings' => divi_cap_messages($warnings)];
}

/**
 * Dedupe a message list and cap it, appending an "and N more" summary when the
 * list is longer than the cap. Keeps validation output context-friendly.
 *
 * @param list<string> $messages
 * @return list<string>
 */
function divi_cap_messages(array $messages): array
{
    $unique = array_values(array_unique($messages));
    $cap = 10;
    if (count($unique) <= $cap) {
        return $unique;
    }
    $kept = array_slice($unique, offset: 0, length: $cap);
    $kept[] = sprintf('… and %d more.', count($unique) - $cap);
    return $kept;
}

/**
 * Grade one level of a tree against Divi's grammar, recursing with each node as
 * the parent of its own children. `$parent` is the parent NODE (name + attrs),
 * or null for the document root, because the grammar is attribute-conditioned.
 *
 * @param list<array<string, mixed>> $nodes
 * @param array<string, mixed>|null $parent
 * @param list<string> $errors
 * @param list<string> $warnings
 * @param array<string, mixed>|null $post_filter the divi/post-filter `$parent` is or descends from, if any
 */
function divi_validate_level(
    array $nodes,
    ?array $parent,
    array &$errors,
    array &$warnings,
    ?array $post_filter = null,
): void {
    foreach ($nodes as $node) {
        if (!divi_is_divi_node($node)) {
            // An opaque non-Divi block: preserved verbatim, never graded.
            continue;
        }
        $name = is_string($node['name'] ?? null) ? $node['name'] : '';
        if (divi_module_metadata($name) === null) {
            $errors[] = sprintf('Unknown module "%s". Use novamira/divi-list-modules to discover valid names.', $name);
            continue;
        }
        $verdict = divi_placement_verdict($name, $parent, $post_filter);
        if ($verdict === 'refused') {
            $errors[] = divi_placement_message($name, $parent, $verdict);
        }
        if ($verdict === 'tolerated') {
            $warnings[] = divi_placement_message($name, $parent, $verdict);
        }
        if (in_array($name, NOVAMIRA_DIVI_RAW_HTML_MODULES, strict: true)) {
            $warnings[] = sprintf(
                '"%s" embeds raw HTML; prefer native Divi modules so the layout stays editable.',
                $name,
            );
        }
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        divi_validate_level($children, $node, $errors, $warnings, divi_children_post_filter($node, $post_filter));
    }
}

/**
 * Collect the positional addresses of raw-HTML code modules anywhere in a
 * forest of normalized nodes.
 *
 * @param list<array<string, mixed>> $nodes
 * @return list<string>
 */
function divi_collect_code_module_addresses(array $nodes, string $prefix = ''): array
{
    $found = [];
    foreach (array_values($nodes) as $i => $node) {
        $address = $prefix === '' ? (string) $i : $prefix . '/' . $i;
        $name = is_string($node['name'] ?? null) ? $node['name'] : '';
        if (in_array($name, NOVAMIRA_DIVI_RAW_HTML_MODULES, strict: true)) {
            $found[] = $address;
        }
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $found = [...$found, ...divi_collect_code_module_addresses($children, $address)];
    }
    return $found;
}

/**
 * Ability-level guardrail against styling pages with raw code modules: any
 * write whose payload contains a divi/code or divi/fullwidth-code module is
 * refused unless the caller passed the explicit opt-in `allow_code_modules`.
 * The error is in-band and actionable on purpose — it is the only guardrail
 * that reaches agents that never loaded the divi-build-page skill.
 *
 * Returns the gate error, or null when the write may proceed.
 *
 * @param list<array<string, mixed>> $nodes
 * @param array<string, mixed> $input
 */
function divi_code_module_gate(array $nodes, array $input): ?WP_Error
{
    if (($input['allow_code_modules'] ?? false) === true) {
        return null;
    }

    $addresses = divi_collect_code_module_addresses($nodes);
    if ($addresses === []) {
        return null;
    }

    $shown = implode(', ', array_slice($addresses, offset: 0, length: 10));
    $extra = count($addresses) - 10;
    if ($extra > 0) {
        $shown = sprintf('%s, … and %d more', $shown, $extra);
    }

    return divi_error(
        'code_modules_not_allowed',
        message: sprintf(
            'This write would create %d Divi code module(s) (addresses: %s). The code module injects '
            . 'raw HTML/CSS/JS and is a LAST RESORT: it bypasses Divi\'s styling, responsiveness and '
            . 'editability, and a single malformed rule can break the whole page. Build content with '
            . 'native modules (heading, text, image, button, blurb, …) and style via '
            . '`module.decoration.*` attrs, global presets, and global colors/variables — discover them '
            . 'with `divi-get-style-schema`. Only if the user has EXPLICITLY confirmed they want a raw '
            . 'code module, re-call with `"allow_code_modules": true` — do not set that flag on your own.',
            count($addresses),
            $shown,
        ),
        status: 422,
    );
}

/**
 * True when the tree has content modules and every one of them is a raw-HTML
 * code module — the "faked layout" anti-pattern the integration forbids.
 *
 * @param list<array<string, mixed>> $tree
 */
function divi_tree_is_raw_html_only(array $tree): bool
{
    [$leaves, $raw] = divi_count_content_leaves($tree);
    return $leaves > 0 && $leaves === $raw;
}

/**
 * Count non-structural content modules and, of those, how many are raw-HTML
 * code modules.
 *
 * @param list<array<string, mixed>> $tree
 * @return array{0: int, 1: int}
 */
function divi_count_content_leaves(array $tree): array
{
    $leaves = 0;
    $raw = 0;
    foreach ($tree as $node) {
        $name = is_string($node['name'] ?? null) ? $node['name'] : '';
        $meta = divi_module_metadata($name);
        $category = $meta !== null && is_string($meta['category'] ?? null) ? $meta['category'] : '';
        if ($category !== '' && $category !== 'structure') {
            $leaves++;
            if (in_array($name, NOVAMIRA_DIVI_RAW_HTML_MODULES, strict: true)) {
                $raw++;
            }
        }
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        [$childLeaves, $childRaw] = divi_count_content_leaves($children);
        $leaves += $childLeaves;
        $raw += $childRaw;
    }
    return [$leaves, $raw];
}

/**
 * Build a nested associative array from a path and a leaf value, e.g.
 * (['content', 'innerContent', 'desktop', 'value'], 'x') →
 * ['content' => ['innerContent' => ['desktop' => ['value' => 'x']]]]. Used to
 * make a deep-merge patch that touches a single attribute leaf.
 *
 * @param list<string> $path
 * @param mixed $value
 * @return array<string, mixed>
 */
function divi_path_to_patch(array $path, $value): array
{
    /** @var mixed $patch */
    $patch = $value;
    foreach (array_reverse($path) as $key) {
        $patch = [$key => $patch];
    }
    /** @var array<string, mixed> $patch */
    return $patch;
}

/**
 * Expand any dotted attribute key into the nested object Divi 5 actually reads.
 *
 * An agent (or a design tool) often writes a responsive leaf as a single flat
 * key — `"module.decoration.spacing.tablet.value" => {...}` — but Divi 5 only
 * reads properly nested attrs (`module` → `decoration` → `spacing` → `tablet` →
 * `value`). Stored flat the override serializes fine and re-reads identically,
 * so a round-trip verify passes, yet Divi never renders it. A Divi attr name
 * never contains a dot, so a dot in a key is always a path separator: split it,
 * rebuild the nested shape, and deep-merge sibling paths together. Recurses so a
 * dotted key nested inside a value object is expanded too. Empty path segments
 * (a leading/trailing/doubled dot) are dropped rather than creating a `""` key.
 *
 * @param array<array-key, mixed> $attrs
 * @return array<array-key, mixed>
 */
function divi_expand_dotted_keys(array $attrs): array
{
    $out = [];
    /** @var mixed $value */
    foreach ($attrs as $key => $value) {
        if (is_array($value) && !divi_is_list($value)) {
            $value = divi_expand_dotted_keys($value);
        }
        if (is_string($key) && str_contains($key, '.')) {
            $path = array_values(array_filter(explode('.', $key), static fn(string $s): bool => $s !== ''));
            if ($path !== []) {
                $out = divi_deep_merge($out, divi_path_to_patch($path, $value));
                continue;
            }
        }
        $out = divi_deep_merge($out, [$key => $value]);
    }
    return $out;
}

/**
 * Whether an array is a sequential (list) array. Local helper so the merge
 * logic works on PHP 8.0 (array_is_list is 8.1+).
 *
 * @param array<array-key, mixed> $value
 */
function divi_is_list(array $value): bool
{
    $i = 0;
    foreach ($value as $key => $_unused) {
        if ($key !== $i) {
            return false;
        }
        $i++;
    }
    return true;
}

/**
 * Deep-merge a patch into a base attributes object. Associative sub-arrays are
 * merged recursively; lists and scalars in the patch replace the base value.
 * This gives partial-update (PATCH) semantics for module attributes.
 *
 * @param array<array-key, mixed> $base
 * @param array<array-key, mixed> $patch
 * @return array<array-key, mixed>
 */
function divi_deep_merge(array $base, array $patch): array
{
    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        // An empty array decodes from both `{}` and `[]`; as a PATCH value treat
        // it as "no change" rather than letting it overwrite (wipe) the existing
        // subtree — deep-merge abilities advertise partial-update semantics.
        if ($value === [] && array_key_exists($key, $base)) {
            continue;
        }
        /** @var mixed $existing */
        $existing = $base[$key] ?? null;
        if (is_array($existing) && is_array($value) && !divi_is_list($existing) && !divi_is_list($value)) {
            $base[$key] = divi_deep_merge($existing, $value);
            continue;
        }
        $base[$key] = $value;
    }
    return $base;
}

/**
 * Deep-merge a patch into the attributes of the module at `$address` and
 * persist. Lower-level than divi_set_field_value (which targets a responsive
 * leaf) — use for root-level attrs like `modulePreset`.
 *
 * The Divi grammar is attribute-conditioned, so a patch can silently re-shape
 * what the module is allowed to contain (retyping a section to `specialty`
 * while it is full of rows, marking a column as an inner-row host while it
 * holds content modules). Divi performs no structural check of its own on save,
 * so the write is re-graded afterwards and any violation comes back in
 * `warnings` — the patch itself is never refused for it.
 *
 * @param array<array-key, mixed> $patch
 * @return array{post_id: int, address: string, name: string, warnings?: list<string>}|WP_Error
 */
function divi_merge_module_attrs(WP_Post $post, string $address, array $patch)
{
    $tree = divi_parse_tree($post->post_content);
    $node = divi_tree_get($tree, $address);
    if ($node === null) {
        return divi_error('not_found', message: sprintf('No module at address "%s".', $address), status: 404);
    }
    if (!divi_is_divi_node($node)) {
        return divi_error(
            'not_a_module',
            message: sprintf('Address "%s" is a non-Divi block, not an editable module.', $address),
            status: 422,
        );
    }

    $before = $node;
    $current = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
    // Nest any dotted responsive keys (`module.decoration.spacing.tablet.value`)
    // before merging — stored flat, Divi 5 silently never renders them.
    $node['attrs'] = divi_deep_merge($current, divi_expand_dotted_keys($patch));

    $updated = divi_tree_replace($tree, $address, $node);
    if ($updated === null) {
        return divi_error('not_found', message: sprintf('No module at address "%s".', $address), status: 404);
    }

    $warnings = divi_structural_change_warnings($before, $node, $updated, $address);

    $count = divi_save_tree($post, $updated);
    if ($count instanceof WP_Error) {
        return $count;
    }

    $result = [
        'post_id' => $post->ID,
        'address' => $address,
        'name' => is_string($node['name'] ?? null) ? $node['name'] : '',
    ];
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }
    return $result;
}

/**
 * The structural identity an attrs write can change: everything the grammar
 * reads, so an edit to it is re-graded. A section's layout type decides whether
 * it takes rows or modules; a column's inner-row-host flag decides whether it
 * takes inner rows or content. Anything else returns the same shape and skips
 * the re-grade.
 *
 * @param array<string, mixed> $node
 */
function divi_structural_shape(array $node): string
{
    $name = is_string($node['name'] ?? null) ? $node['name'] : '';
    if ($name === 'divi/section') {
        return 'section:' . divi_section_type($node);
    }
    if ($name === 'divi/column') {
        return 'column:' . (divi_column_hosts_inner_rows($node) ? 'inner-rows' : 'ordinary');
    }
    return '';
}

/**
 * Re-grade the subtree an attrs write re-shaped. Returns nothing when the write
 * left the module's structural shape alone; otherwise every placement the new
 * shape breaks — the node under its own parent and its whole subtree — as
 * warnings, so a retype can never corrupt a tree silently. An attrs write is
 * never refused for a placement.
 *
 * @param array<string, mixed> $before
 * @param array<string, mixed> $after
 * @param list<array<string, mixed>> $tree the tree with the write already applied
 * @return list<string>
 */
function divi_structural_change_warnings(array $before, array $after, array $tree, string $address): array
{
    if (divi_structural_shape($before) === divi_structural_shape($after)) {
        return [];
    }

    [$parent_address] = divi_split_address($address);
    $parent = $parent_address === '' ? null : divi_tree_get($tree, $parent_address);

    $messages = [];
    divi_collect_placement_messages([$after], $parent, $messages, divi_address_post_filter($tree, $parent_address));
    if ($messages === []) {
        return [];
    }

    $warnings = [];
    foreach ($messages as $message) {
        $warnings[] = 'After this attribute change: ' . $message;
    }
    return divi_cap_messages($warnings);
}

/**
 * Read the current string value at `<field>.<breakpoint>.value` (or its
 * selected structured `$value_field`) of the module at `$address`, or null
 * when absent / not a string.
 */
function divi_read_field_value(
    WP_Post $post,
    string $address,
    string $field,
    string $breakpoint,
    string $value_field = '',
): ?string {
    $node = divi_tree_get(divi_parse_tree($post->post_content), $address);
    if ($node === null) {
        return null;
    }
    $selected_value_field = divi_resolve_value_field($node, $field, $value_field);
    if ($selected_value_field instanceof WP_Error) {
        return null;
    }
    $path = array_merge(explode('.', $field), [$breakpoint, 'value']);
    /** @var mixed $cursor */
    $cursor = $node['attrs'] ?? null;
    foreach ($path as $segment) {
        if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
            return null;
        }
        /** @var mixed $cursor */
        $cursor = $cursor[$segment];
    }
    // Legacy bindings were stored directly at `.value`, including structured
    // fields. Read that string as-is; the next write migrates it to its leaf.
    if (is_string($cursor)) {
        return $cursor;
    }
    if ($selected_value_field !== '' && is_array($cursor) && array_key_exists($selected_value_field, $cursor)) {
        /** @var mixed $cursor */
        $cursor = $cursor[$selected_value_field];
    }
    return is_string($cursor) ? $cursor : null;
}

/**
 * Set a single responsive attribute leaf (`<field>.<breakpoint>.value`) on the
 * module at `$address`, deep-merging so sibling attrs/breakpoints survive, then
 * persist. A structured value routes to its requested `$value_field`, or by
 * default to `src` for an image value and `text` for a text-bearing value.
 * Shared by the dynamic-content abilities. `$field` is a dotted attrName path
 * (e.g. `content.innerContent`).
 *
 * @param array{value: mixed, value_field?: string} $write
 * @return array{post_id: int, address: string, field: string, name: string, value_field?: string}|WP_Error
 */
function divi_set_field_value(WP_Post $post, string $address, string $field, string $breakpoint, array $write)
{
    $target = divi_resolve_field_target($post, $address, $field, $breakpoint);
    if ($target instanceof WP_Error) {
        return $target;
    }
    $node = $target['node'];
    $value_field = is_string($write['value_field'] ?? null) ? $write['value_field'] : '';
    $selected_value_field = is_scalar($write['value']) ? divi_resolve_value_field($node, $field, $value_field) : '';
    if ($selected_value_field instanceof WP_Error) {
        return $selected_value_field;
    }
    $path = $target['path'];
    if ($selected_value_field !== '') {
        $path[] = $selected_value_field;
    }
    $current = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
    $node['attrs'] = divi_deep_merge($current, divi_path_to_patch($path, $write['value']));
    $result = divi_persist_field_write($post, $target['tree'], $address, $field, $node);
    if (is_array($result) && $selected_value_field !== '') {
        $result['value_field'] = $selected_value_field;
    }
    return $result;
}

/**
 * Validate and select the scalar leaf inside a field's structured value.
 * Explicit values must match the module schema's `value_fields`. Without one,
 * image values prefer `src`, text-bearing values prefer `text`, and simple
 * values remain at the whole `.value` leaf.
 *
 * @param array<string, mixed> $node
 * @return string|WP_Error
 */
function divi_resolve_value_field(array $node, string $field, string $value_field)
{
    $module = is_string($node['name'] ?? null) ? $node['name'] : '';
    $by_field = $module === '' ? [] : divi_preset_content_subfields($module);
    $value_fields = $by_field[$field] ?? [];

    if ($value_field !== '') {
        if (!in_array($value_field, $value_fields, strict: true)) {
            $valid = $value_fields === [] ? 'none' : implode(', ', $value_fields);
            return divi_error(
                'unknown_value_field',
                message: sprintf(
                    'Unknown value_field "%s" for field "%s" on module "%s". Valid value_fields: %s.',
                    $value_field,
                    $field,
                    $module,
                    $valid,
                ),
                status: 422,
            );
        }
        return $value_field;
    }
    if (in_array('src', $value_fields, strict: true)) {
        return 'src';
    }
    return in_array('text', $value_fields, strict: true) ? 'text' : '';
}

/**
 * Like divi_set_field_value, but REPLACES the `<field>.<breakpoint>.value` leaf
 * wholesale instead of deep-merging into it (sibling breakpoints/attrs still
 * survive). Use for full-leaf writes — e.g. enabling a loop — where a deep-merge
 * would leave stale sub-keys behind (an empty `subTypes:[]` would be skipped).
 *
 * @param mixed $leaf_value
 * @return array{post_id: int, address: string, field: string, name: string}|WP_Error
 */
function divi_replace_field_value(WP_Post $post, string $address, string $field, string $breakpoint, $leaf_value)
{
    $target = divi_resolve_field_target($post, $address, $field, $breakpoint);
    if ($target instanceof WP_Error) {
        return $target;
    }
    $node = $target['node'];
    $current = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
    divi_array_set_path($current, $target['path'], $leaf_value);
    $node['attrs'] = $current;
    return divi_persist_field_write($post, $target['tree'], $address, $field, $node);
}

/**
 * Resolve the write target for a responsive leaf: validate the breakpoint and
 * field path, fetch the (editable Divi) node, and return the parsed tree, the
 * node, and the full `<field>.<breakpoint>.value` path. Shared prelude for the
 * merge/replace field writers.
 *
 * @return array{tree: list<array<string, mixed>>, node: array<string, mixed>, path: list<string>}|WP_Error
 */
function divi_resolve_field_target(WP_Post $post, string $address, string $field, string $breakpoint)
{
    $breakpoint = $breakpoint === '' ? 'desktop' : $breakpoint;
    if (!in_array($breakpoint, ['desktop', 'tablet', 'phone'], strict: true)) {
        return divi_error('invalid_input', message: sprintf(
            'Unknown breakpoint "%s" (expected desktop/tablet/phone).',
            $breakpoint,
        ));
    }
    // Reject a malformed field path (empty or empty segments) so we never write
    // a phantom attr branch like `content..value`.
    $segments = explode('.', $field);
    if ($field === '' || in_array('', $segments, strict: true)) {
        return divi_error('invalid_input', message: sprintf('Invalid field path "%s".', $field));
    }

    $tree = divi_parse_tree($post->post_content);
    $node = divi_tree_get($tree, $address);
    if ($node === null) {
        return divi_error('not_found', message: sprintf('No module at address "%s".', $address), status: 404);
    }
    if (!divi_is_divi_node($node)) {
        return divi_error(
            'not_a_module',
            message: sprintf('Address "%s" is a non-Divi block, not an editable module.', $address),
            status: 422,
        );
    }

    return ['tree' => $tree, 'node' => $node, 'path' => array_merge($segments, [$breakpoint, 'value'])];
}

/**
 * Replace the node at `$address` with `$node` and persist. Shared tail for the
 * field writers.
 *
 * @param list<array<string, mixed>> $tree
 * @param array<string, mixed> $node
 * @return array{post_id: int, address: string, field: string, name: string}|WP_Error
 */
function divi_persist_field_write(WP_Post $post, array $tree, string $address, string $field, array $node)
{
    $updated = divi_tree_replace($tree, $address, $node);
    if ($updated === null) {
        return divi_error('not_found', message: sprintf('No module at address "%s".', $address), status: 404);
    }

    $count = divi_save_tree($post, $updated);
    if ($count instanceof WP_Error) {
        return $count;
    }

    return [
        'post_id' => $post->ID,
        'address' => $address,
        'field' => $field,
        'name' => is_string($node['name'] ?? null) ? $node['name'] : '',
    ];
}

/**
 * Assign `$value` at `$path` inside `$target`, creating intermediate object
 * levels and overwriting whatever is at the final key (siblings untouched).
 *
 * @param array<array-key, mixed> $target
 * @param list<string> $path
 * @param mixed $value
 */
function divi_array_set_path(array &$target, array $path, $value): void
{
    $cursor = &$target;
    $last = array_key_last($path);
    foreach ($path as $i => $segment) {
        if ($i === $last) {
            $cursor[$segment] = $value;
            return;
        }
        if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
            $cursor[$segment] = [];
        }
        $cursor = &$cursor[$segment];
    }
}

/**
 * Whether every node's attributes survive JSON encoding (the shape
 * serialize_blocks relies on). Returns false if any node's attrs exceed JSON's
 * nesting depth or are otherwise unencodable.
 *
 * @param list<array<string, mixed>> $tree
 */
function divi_tree_attrs_encodable(array $tree): bool
{
    foreach ($tree as $node) {
        /** @var mixed $attrs */
        $attrs = $node['attrs'] ?? [];
        if (is_array($attrs) && $attrs !== [] && wp_json_encode($attrs) === false) {
            return false;
        }
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if (!divi_tree_attrs_encodable($children)) {
            return false;
        }
    }
    return true;
}

/**
 * Divi renders a row's columns side-by-side only when the row carries a
 * `module.advanced.columnStructure` and each column a `module.advanced.type`
 * fraction (e.g. "1_3"); columns built without them collapse to full width and
 * stack. An agent that builds a row with N columns expects an N-up layout, so
 * fill these in automatically before every save: a row whose columns are all
 * bare (or all share one fraction) is distributed into equal fractions, which
 * also self-heals after a column is added or removed; a genuine custom layout
 * (columns set to two or more different fractions via `module.advanced.type`)
 * is respected, with any bare sibling taking an equal share. Single-column rows
 * and rows with more than six columns are left exactly as written: that is the
 * window this normalization has always applied, NOT a statement about Divi,
 * which builds equal-column rows well past six.
 *
 * `divi/row-inner` › `divi/column-inner` reads the identical two attributes and
 * is distributed the same way.
 *
 * @param list<array<string, mixed>> $tree
 * @return list<array<string, mixed>>
 */
function divi_normalize_column_widths(array $tree): array
{
    foreach ($tree as $i => $node) {
        $name = is_string($node['name'] ?? null) ? $node['name'] : '';
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if (in_array($name, ['divi/row', 'divi/row-inner'], strict: true)) {
            [$node, $children] = divi_distribute_columns($node, $children);
        }
        $node['children'] = divi_normalize_column_widths($children);
        $tree[$i] = $node;
    }
    return $tree;
}

/**
 * Assign the row's `columnStructure` and each column's `type` for one row. See
 * {@see divi_normalize_column_widths} for the equal-vs-custom rule.
 *
 * @param array<string, mixed> $row
 * @param list<array<string, mixed>> $children
 * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>}
 */
function divi_distribute_columns(array $row, array $children): array
{
    $indices = divi_row_column_indices($children);
    $count = count($indices);
    // The window this normalization has always applied. It is an inherited
    // limit of this code, not a Divi one — Divi's own grid library defines
    // equal-column layouts from one to eight. Widening it would change what
    // every existing row saves as, so a row outside the window is left exactly
    // as it was written.
    if ($count < 2 || $count > 6) {
        return [$row, $children];
    }

    $types = divi_resolve_column_fractions($children, $indices, $count);
    $structure = [];
    foreach ($indices as $i) {
        $attrs = is_array($children[$i]['attrs'] ?? null) ? $children[$i]['attrs'] : [];
        divi_array_set_path($attrs, NOVAMIRA_DIVI_COLUMN_TYPE_PATH, $types[$i]);
        $children[$i]['attrs'] = $attrs;
        $structure[] = $types[$i];
    }

    $row_attrs = is_array($row['attrs'] ?? null) ? $row['attrs'] : [];
    divi_array_set_path(
        $row_attrs,
        ['module', 'advanced', 'columnStructure', 'desktop', 'value'],
        implode(',', $structure),
    );
    $row['attrs'] = $row_attrs;

    return [$row, $children];
}

/**
 * Indices of the direct column children of a row.
 *
 * @param list<array<string, mixed>> $children
 * @return list<int>
 */
function divi_row_column_indices(array $children): array
{
    $indices = [];
    foreach ($children as $i => $child) {
        $name = is_string($child['name'] ?? null) ? $child['name'] : '';
        if ($name === 'divi/column' || $name === 'divi/column-inner') {
            $indices[] = $i;
        }
    }
    return $indices;
}

/**
 * The final width fraction per column index: equal shares when the columns
 * carry at most one distinct explicit width (all bare or all equal, so
 * add/remove self-heals), otherwise each column's own explicit width with a
 * bare sibling taking an equal share. See {@see divi_normalize_column_widths}.
 *
 * @param list<array<string, mixed>> $children
 * @param list<int> $indices
 * @return array<int, string>
 */
function divi_resolve_column_fractions(array $children, array $indices, int $count): array
{
    $equal = '1_' . $count;
    $fractions = [];
    $explicit = [];
    foreach ($indices as $i) {
        $fractions[$i] = divi_column_type_fraction($children[$i]);
        if ($fractions[$i] !== null) {
            $explicit[] = $fractions[$i];
        }
    }
    $equalize = count(array_unique($explicit)) <= 1;

    $resolved = [];
    foreach ($indices as $i) {
        $resolved[$i] = $equalize ? $equal : $fractions[$i] ?? $equal;
    }
    return $resolved;
}

/**
 * A column's explicit width fraction ("A_B") read from
 * `module.advanced.type.desktop.value`, or null when unset / malformed.
 *
 * @param array<string, mixed> $column
 */
function divi_column_type_fraction(array $column): ?string
{
    /** @var mixed $value */
    $value = divi_node_attr($column, NOVAMIRA_DIVI_COLUMN_TYPE_PATH);
    return is_string($value) && preg_match('/^[1-9]\d*_[1-9]\d*$/', $value) === 1 ? $value : null;
}

/**
 * Persist a node tree to a post's content and invalidate its cache. Returns the
 * total node count written, or a WP_Error if the update failed.
 *
 * @param list<array<string, mixed>> $tree
 * @return int|WP_Error
 */
function divi_save_tree(WP_Post $post, array $tree)
{
    $tree = divi_normalize_column_widths($tree);

    // serialize_blocks silently drops a block's attrs when wp_json_encode fails
    // (e.g. a patch nested past JSON's ~512-level depth), which would wipe a
    // module's settings on what looks like a successful write. Refuse instead.
    if (!divi_tree_attrs_encodable($tree)) {
        return divi_error(
            'invalid_input',
            message: 'Module attributes are nested too deeply or are otherwise not serializable; the write was refused to avoid wiping existing settings.',
            status: 422,
        );
    }

    // Mark the post builder-built BEFORE wp_update_post:
    // et_pb_is_pagebuilder_used() memoizes its answer per request, and
    // third-party save_post listeners (e.g. AIOSEO's Divi integration) call it
    // during the update — if the meta lands after the save, the false answer
    // poisons Divi's cache for the rest of the request and those listeners
    // treat the page as not builder-built.
    $was_builder = get_post_meta($post->ID, key: '_et_pb_use_builder', single: true) === 'on';
    divi_mark_builder_page($post->ID);

    // Keep the page's storage shape: content that carried Divi's root
    // placeholder envelope is written back inside exactly one envelope (which
    // also heals a multi-root document into a single wrapper); content that
    // never had one stays bare.
    $content = divi_content_has_placeholder($post->post_content)
        ? divi_serialize_wrapped_tree($tree)
        : divi_serialize_tree($tree);

    // Confirm the builder meta actually landed. A third-party update_post_metadata
    // short-circuit filter can silently drop it — leaving the page unmarked would
    // make the first Visual Builder open destroy the build — so refuse before
    // writing any builder content rather than persist an editable-only-until-VB page.
    if (get_post_meta($post->ID, key: '_et_pb_use_builder', single: true) !== 'on') {
        if (!$was_builder) {
            divi_unmark_builder_page($post->ID);
        }
        return divi_error(
            'builder_meta_blocked',
            message: 'The page could not be marked as a Divi builder page (a plugin or filter is blocking '
            . '_et_pb_use_builder). Writing the layout was refused because the first Visual Builder open '
            . 'would otherwise destroy it.',
            status: 409,
        );
    }

    $result = wp_update_post(
        [
            'ID' => $post->ID,
            // wp_update_post wp_unslash()es the data, so content must be slashed —
            // otherwise backslash-escaped quotes inside attr JSON (e.g. dynamic
            // values, rich text with quotes) get mangled on save.
            'post_content' => wp_slash($content),
        ],
        wp_error: true,
    );

    if ($result instanceof WP_Error) {
        if (!$was_builder) {
            // The content never landed; don't leave a non-builder page marked
            // builder-built.
            divi_unmark_builder_page($post->ID);
        }
        return divi_error('save_failed', message: $result->get_error_message(), status: 500);
    }

    // update_post_meta-style structural writes don't fire save_post cleanup for
    // builder caches; clear the post cache so the next render reparses content.
    clean_post_cache($post->ID);

    // Verify-after-write: re-read what actually landed and confirm it carries
    // the same content we wrote. wp_update_post returning OK is not proof the
    // layout is intact — a hostile content filter (e.g. a plugin that
    // stripslashes post_content on save) mangles the block-attr JSON *after* our
    // write, corrupting values without necessarily changing the block count.
    // We compare a content SIGNATURE (module names + attrs + passthrough
    // innerHTML) rather than the raw parse: Divi's BlockParser stamps volatile
    // per-request counters (orderIndex, id, …) on every parsed block, so two
    // reparses of identical content are not byte-equal — the signature strips
    // those while still catching any value mangling.
    /** @var mixed $stored */
    $stored = get_post($post->ID);
    if ($stored instanceof WP_Post) {
        $actual = wp_json_encode(divi_tree_content_signature(divi_parse_tree($stored->post_content)));
        $intended = wp_json_encode(divi_tree_content_signature(divi_parse_tree($content)));
        if ($actual !== $intended) {
            return divi_error(
                'content_corrupted',
                message: 'The layout was written but re-reading it does not match what was sent — a plugin or '
                . 'filter altered the content on save (commonly a stripslashes or sanitize hook that mangles '
                . 'block markup). The page may be broken; check for a plugin that rewrites post_content.',
                status: 409,
            );
        }
    }

    return divi_tree_count($tree);
}

/**
 * A stable content signature of a node tree for the round-trip verify. Keeps
 * only the fields that carry meaning — a Divi node's name + attrs + children,
 * and a passthrough block's blockName + attrs + innerHTML + innerContent +
 * recursively-signed inner blocks — and DROPS the volatile per-request counters
 * (orderIndex, index, id, storeInstance, parentId, _merged_attrs) Divi's
 * BlockParser stamps onto every parsed block, which otherwise differ between
 * two reparses of identical content and produce false corruption reports.
 *
 * @param list<array<string, mixed>> $tree
 * @return list<array<string, mixed>>
 */
function divi_tree_content_signature(array $tree): array
{
    $out = [];
    foreach ($tree as $node) {
        /** @var mixed $raw */
        $raw = $node['_raw'] ?? null;
        if (is_array($raw)) {
            $out[] = ['passthrough' => divi_block_content_signature($raw)];
            continue;
        }
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        $out[] = [
            'name' => is_string($node['name'] ?? null) ? $node['name'] : '',
            'attrs' => is_array($node['attrs'] ?? null) ? $node['attrs'] : [],
            'children' => divi_tree_content_signature($children),
        ];
    }
    return $out;
}

/**
 * Content signature of a raw (non-Divi passthrough) block: its stable content
 * fields only, recursing into inner blocks, dropping BlockParser counters.
 *
 * @param array<array-key, mixed> $raw
 * @return array<string, mixed>
 */
function divi_block_content_signature(array $raw): array
{
    $inner = [];
    /** @var mixed $child */
    foreach (is_array($raw['innerBlocks'] ?? null) ? $raw['innerBlocks'] : [] as $child) {
        if (!is_array($child)) {
            continue;
        }
        $inner[] = divi_block_content_signature($child);
    }
    return [
        'blockName' => is_string($raw['blockName'] ?? null) ? $raw['blockName'] : null,
        'attrs' => is_array($raw['attrs'] ?? null) ? $raw['attrs'] : [],
        'innerHTML' => is_string($raw['innerHTML'] ?? null) ? $raw['innerHTML'] : '',
        'innerContent' => is_array($raw['innerContent'] ?? null) ? $raw['innerContent'] : [],
        'inner' => $inner,
    ];
}

/** Remove the three Divi builder-page metas. */
function divi_unmark_builder_page(int $post_id): void
{
    delete_post_meta($post_id, meta_key: '_et_pb_use_divi_5');
    delete_post_meta($post_id, meta_key: '_et_pb_use_builder');
    delete_post_meta($post_id, meta_key: '_et_pb_show_page_creation');
}

/**
 * Validate that `$child_name` may be inserted under `$parent` ('' = root) and
 * report the parent's current child count. Returns a WP_Error on a bad parent
 * or a nesting violation. Shared by add-module and move-module.
 *
 * @param list<array<string, mixed>> $tree
 * @return array{child_count: int}|WP_Error
 */
function divi_check_placement(array $tree, string $parent, string $child_name)
{
    if ($parent === '') {
        if (divi_placement_verdict($child_name, parent: null) === 'refused') {
            return divi_error(
                'invalid_tree',
                message: sprintf(
                    '"%s" cannot sit at the top level — only divi/section may. Add a section first.',
                    $child_name,
                ),
                status: 422,
            );
        }
        return ['child_count' => count($tree)];
    }

    $parent_node = divi_tree_get($tree, $parent);
    if ($parent_node === null) {
        return divi_error('not_found', message: sprintf('Parent address "%s" does not exist.', $parent), status: 404);
    }

    // Only a refused placement blocks the write; a placement Divi rejects that
    // this integration used to accept comes back as a warning from the
    // validation divi_finalize_insert runs on the inserted subtree.
    $post_filter = divi_address_post_filter($tree, $parent);
    if (divi_placement_verdict($child_name, $parent_node, $post_filter) === 'refused') {
        return divi_error(
            'invalid_tree',
            message: divi_placement_message($child_name, $parent_node, verdict: 'refused'),
            status: 422,
        );
    }

    /** @var list<array<string, mixed>> $children */
    $children = is_array($parent_node['children'] ?? null) ? $parent_node['children'] : [];
    return ['child_count' => count($children)];
}

/**
 * Insert a node into `$parent` ('' = root) at `$position` (null = append),
 * guard the raw-HTML rule on the result, persist, and report the node's
 * resulting address. Shared by add-module, move-module, and apply-library-item.
 *
 * The `warnings` it returns carry the non-fatal advisories the validation
 * raised on the inserted subtree (e.g. a raw-HTML code module) — callers must
 * surface them so the "warnings not logs" rule holds on every insert path, not
 * only add-module.
 *
 * @param list<array<string, mixed>> $tree
 * @param array<string, mixed> $node
 * @return array{address: string, node_count: int, warnings: list<string>}|WP_Error
 */
function divi_finalize_insert(WP_Post $post, array $tree, string $parent, ?int $position, array $node)
{
    // Validate the WHOLE inserted subtree (the node and any children), not just
    // the top node — otherwise add-module would persist unknown modules / illegal
    // nesting inside `children` that set-content would reject.
    $parent_node = $parent === '' ? null : divi_tree_get($tree, $parent);
    $errors = [];
    $warnings = [];
    divi_validate_level([$node], $parent_node, $errors, $warnings, divi_address_post_filter($tree, $parent));
    if ($errors !== []) {
        return divi_error('invalid_tree', message: implode(' ', divi_cap_messages($errors)), status: 422);
    }

    $child_count = $parent === '' ? count($tree) : count(divi_node_children($tree, $parent));
    $pos = $position === null ? $child_count : max(0, min($position, $child_count));

    $updated = divi_tree_insert($tree, $parent, $pos, $node);
    if ($updated === null) {
        return divi_error('not_found', message: sprintf('Parent address "%s" does not exist.', $parent), status: 404);
    }

    if (divi_tree_is_raw_html_only($updated)) {
        return divi_error(
            'invalid_tree',
            message: 'This would leave the page with only raw-HTML code modules. Build native Divi modules instead.',
            status: 422,
        );
    }

    if (divi_tree_has_free_form_css([$node])) {
        $warnings[] = divi_free_form_css_warning();
    }

    $count = divi_save_tree($post, $updated);
    if ($count instanceof WP_Error) {
        return $count;
    }

    return [
        'address' => $parent === '' ? (string) $pos : $parent . '/' . $pos,
        'node_count' => $count,
        'warnings' => divi_cap_messages($warnings),
    ];
}

/**
 * Whether `$candidate` is `$ancestor` itself or one of its descendants — used
 * to reject moving a node into its own subtree.
 */
function divi_address_within(string $candidate, string $ancestor): bool
{
    return $candidate === $ancestor || str_starts_with($candidate . '/', $ancestor . '/');
}

/**
 * Adjust an address to remain valid after the node at [removed_parent,
 * removed_index] is removed. Only sibling indices at the removed node's level
 * (and after it) shift down by one; addresses on other branches are unchanged.
 */
function divi_adjust_address_after_removal(string $address, string $removed_parent, int $removed_index): string
{
    $segments = explode('/', $address);
    $parent_segments = $removed_parent === '' ? [] : explode('/', $removed_parent);
    $depth = count($parent_segments);

    for ($i = 0; $i < $depth; $i++) {
        if (($segments[$i] ?? null) !== $parent_segments[$i]) {
            return $address; // different branch — unaffected
        }
    }

    if (isset($segments[$depth]) && (int) $segments[$depth] > $removed_index) {
        $segments[$depth] = (string) ((int) $segments[$depth] - 1);
    }
    return implode('/', $segments);
}

/**
 * Normalize an untrusted input node ({name, attrs?, children?}) into a clean
 * tree node, recursively. Returns a WP_Error on a malformed shape.
 *
 * @param mixed $raw
 * @return array<string, mixed>|WP_Error
 */
function divi_normalize_input_node($raw)
{
    $count = 0;
    return divi_normalize_input_node_at($raw, depth: 1, count: $count);
}

/**
 * Normalize a forest of untrusted input nodes, sharing one node counter across
 * all roots so the total node cap bounds the whole tree (not each root).
 *
 * @param array<array-key, mixed> $raw_nodes
 * @return list<array<string, mixed>>|WP_Error
 */
function divi_normalize_input_forest(array $raw_nodes)
{
    $count = 0;
    /** @var list<array<string, mixed>> $tree */
    $tree = [];
    /** @var mixed $raw_node */
    foreach ($raw_nodes as $raw_node) {
        $node = divi_normalize_input_node_at($raw_node, depth: 1, count: $count);
        if ($node instanceof WP_Error) {
            return $node;
        }
        $tree[] = $node;
    }
    return $tree;
}

/**
 * Recursive worker for {@see divi_normalize_input_node}. Threads a depth level
 * and a shared node counter so an untrusted tree cannot crash the worker by
 * deep nesting or exhaust memory by sheer node count.
 *
 * @param mixed $raw
 * @param int $count running total of normalized nodes, by reference
 * @return array<string, mixed>|WP_Error
 */
function divi_normalize_input_node_at($raw, int $depth, int &$count)
{
    if ($depth > NOVAMIRA_DIVI_MAX_INPUT_DEPTH) {
        return divi_error('invalid_input', message: sprintf(
            'Module tree is nested too deeply (max %d levels).',
            NOVAMIRA_DIVI_MAX_INPUT_DEPTH,
        ));
    }
    if (!is_array($raw)) {
        return divi_error('invalid_input', message: 'Each module must be an object with a "name".');
    }
    if (++$count > NOVAMIRA_DIVI_MAX_INPUT_NODES) {
        return divi_error('invalid_input', message: sprintf(
            'Module tree is too large (max %d nodes).',
            NOVAMIRA_DIVI_MAX_INPUT_NODES,
        ));
    }
    /** @var mixed $name */
    $name = $raw['name'] ?? null;
    if (!is_string($name) || $name === '') {
        return divi_error('invalid_input', message: 'Each module requires a non-empty "name" (e.g. "divi/text").');
    }
    $name = divi_normalize_module_name($name);

    /** @var mixed $attrs */
    $attrs = $raw['attrs'] ?? [];
    if (!is_array($attrs) || $attrs !== [] && divi_is_list($attrs)) {
        return divi_error('invalid_input', message: sprintf('"attrs" for "%s" must be an object, not a list.', $name));
    }
    // Nest dotted responsive keys so a module built via add/set-content renders
    // them — Divi 5 ignores a flat `module.decoration.spacing.tablet.value`.
    $attrs = divi_expand_dotted_keys($attrs);

    /** @var list<array<string, mixed>> $children */
    $children = [];
    /** @var mixed $raw_children */
    $raw_children = $raw['children'] ?? [];
    if (!is_array($raw_children)) {
        return divi_error('invalid_input', message: sprintf('"children" for "%s" must be an array.', $name));
    }
    /** @var mixed $raw_child */
    foreach ($raw_children as $raw_child) {
        $child = divi_normalize_input_node_at($raw_child, depth: $depth + 1, count: $count);
        if ($child instanceof WP_Error) {
            return $child;
        }
        $children[] = $child;
    }

    return ['name' => $name, 'attrs' => $attrs, 'children' => $children];
}
