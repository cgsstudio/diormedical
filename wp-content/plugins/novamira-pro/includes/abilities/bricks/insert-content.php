<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

/**
 * Ability: Insert Bricks elements into an existing area without replacing it.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-insert-content', [
    'label' => __('Insert Bricks Content', domain: 'novamira-pro'),
    'description' => __(
        'Inserts new Bricks elements into an existing area without replacing the full element tree — the way to build a page section by section. Position accepts exactly one of: after, before, append_to, prepend_to; omitted = append at the end of the area as roots. Before the first write on a site, call novamira/bricks-list-elements (element="<name>" for controls, include_shared_controls=true for the shared style keys) and load the "bricks-build-page" skill via novamira/skill-get. One native element per semantic node; NEVER paste raw HTML into a text, text-basic or code element (layout markup in a text is reported as raw_layout_html_ids[], an empty section/container/block as empty_structural_ids[]). Example — a hero section appended to the content area: {"post_id": 12, "area": "content", "elements": [{"id": "hero01", "name": "section", "settings": {"_padding": {"top": "var(--space-2xl)", "bottom": "var(--space-2xl)"}}}, {"id": "herocn", "name": "container", "parent": "hero01", "settings": {"_direction": "column", "_rowGap": "var(--space-m)", "_widthMax": "60rem"}}, {"id": "herohd", "name": "heading", "parent": "herocn", "settings": {"text": "Build faster", "tag": "h1", "_typography": {"font-size": "var(--text-3xl)"}}}, {"id": "herotx", "name": "text-basic", "parent": "herocn", "settings": {"text": "One paragraph of prose per element.", "tag": "p"}}]}. Ids may be omitted (generated server-side and returned in generated_ids). The raw "code" element is gated and requires allow_code_elements:true after explicit user confirmation.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or template ID.',
            ],
            'area' => [
                'type' => 'string',
                'description' => 'Which area to modify: "header", "content", or "footer".',
                'enum' => ['header', 'content', 'footer'],
            ],
            'elements' => [
                'type' => 'array',
                'description' => 'New element objects to insert. Use the same element shape as bricks-set-content. Root elements should omit parent or use 0; descendants can reference other inserted element IDs.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Unique element ID of exactly 6 characters (e.g. "abc123"). Optional: omit it and a 6-character id is generated server-side (never colliding with the area) and returned in generated_ids keyed by the element\'s index. Descendants reference their parent by id, so an element that omits its id can only be a root or a leaf. New ids of any other length and ids already present in the area are rejected.',
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Element type (e.g. "heading", "text", "container").',
                        ],
                        'parent' => [
                            'type' => ['string', 'integer'],
                            'description' => 'Parent element ID for descendants inside the inserted payload, or 0 (integer or string) for inserted roots. The server sets inserted roots parent based on position.',
                        ],
                        'settings' => ['type' => 'object', 'description' => 'Element settings/control values.'],
                    ],
                    'required' => ['name', 'settings'],
                ],
            ],
            'position' => [
                'type' => 'object',
                'description' => 'Optional insertion descriptor. Provide exactly one of: after, before, append_to, prepend_to. Omit to append the new roots at the end of the area.',
                'properties' => [
                    'after' => [
                        'type' => 'string',
                        'description' => 'Insert after this existing element as a sibling under the same parent.',
                    ],
                    'before' => [
                        'type' => 'string',
                        'description' => 'Insert before this existing element as a sibling under the same parent.',
                    ],
                    'append_to' => [
                        'type' => 'string',
                        'description' => 'Append as the last children of this existing element.',
                    ],
                    'prepend_to' => [
                        'type' => 'string',
                        'description' => 'Prepend as the first children of this existing element.',
                    ],
                ],
                'additionalProperties' => false,
                'minProperties' => 1,
                'maxProperties' => 1,
                'oneOf' => [
                    ['required' => ['after']],
                    ['required' => ['before']],
                    ['required' => ['append_to']],
                    ['required' => ['prepend_to']],
                ],
            ],
            'return_css' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'If true, return the CSS for this write: in external CSS file mode the regenerated per-post CSS file, otherwise the inline CSS Bricks generates for the area (css_source says which). Useful for verifying that settings produced the expected styles.',
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Confirmation gate for the Bricks "code" element (raw HTML/CSS/JS), which is a last resort. By default inserting a "code" element is rejected with guidance to use native elements. Set true ONLY after the user has explicitly confirmed they want a raw code/HTML element.',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Write even when another user currently holds the WordPress edit lock on this post (their builder or editor session is open). Without it such a write is refused with locked_by_other_user naming the user.',
            ],
        ],
        'required' => ['post_id', 'area', 'elements'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'area' => ['type' => 'string'],
            'inserted' => ['type' => 'integer'],
            'element_count' => ['type' => 'integer'],
            'revision_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Id of the WordPress revision created before this write, carrying the Bricks element meta of every area as it was — restorable from the Bricks builder\'s revision panel or WordPress. Null when revisions are disabled for the post type (a warning says so).',
            ],
            'generated_ids' => [
                'type' => 'object',
                'description' => 'Present when elements were sent without an id: maps the 0-based index of each such element in the request to the id generated for it (6 lowercase hex characters, the format Bricks itself uses).',
            ],
            'saved' => [
                'type' => 'boolean',
                'description' => 'Deprecated alias of verified (kept for existing callers; it is no longer a literal true). Read verified instead.',
            ],
            'verified' => [
                'type' => 'boolean',
                'description' => 'True only when the element tree read back from the database after the write equals the tree that was written. False means the stored content differs — the warnings say how.',
            ],
            'stored_element_count' => [
                'type' => 'integer',
                'description' => 'Number of elements the area holds in the database after the write (read back, not computed from the request).',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => '"regenerated" when Bricks serves CSS from external files and the per-post CSS file was rewritten for this write; "inline" when Bricks serves CSS inline (nothing to regenerate); "skipped(reason)" when file mode is on but the file could not be written — the reason names the fix.',
            ],
            'unresolved_variables' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when a written value references var(--name) for a name that is neither a Bricks global variable, a palette colour nor a custom property declared in the site\'s custom CSS: those declarations are dropped by the browser until the token exists.',
            ],
            'inserted_root_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'IDs of the inserted root elements after parent remapping.',
            ],
            'position_applied' => [
                'type' => 'string',
                'description' => 'Insertion mode that was applied: append, before, after, append_to, or prepend_to.',
            ],
            'reference_id' => [
                'type' => 'string',
                'description' => 'Existing element ID used by before/after/append_to/prepend_to. Omitted for default root append.',
            ],
            'parent' => [
                'type' => ['string', 'integer'],
                'description' => 'Parent assigned to the inserted root elements. 0 means root-level insertion.',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Validation warnings about the resulting element tree (data is still saved).',
            ],
            'raw_layout_html_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when an inserted text / text-basic / text-link element carries layout markup, <style>, <script> or inline style attributes in its text: rebuild those as native elements.',
            ],
            'empty_structural_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when an inserted section / container / block has no children.',
            ],
            'css' => [
                'type' => 'string',
                'description' => 'Present only when return_css=true. In external CSS file mode the content of the post\'s regenerated CSS file (what the front end loads, every area of the post); otherwise the inline CSS Bricks generates for this area. css_source says which.',
            ],
            'css_source' => [
                'type' => 'string',
                'enum' => ['file', 'inline'],
                'description' => 'Present only when return_css=true: "file" when css is the regenerated per-post CSS file, "inline" when it is the inline generation result.',
            ],
            'css_truncated' => [
                'type' => 'boolean',
                'description' => 'Present (true) only when css_source is "file" and the file exceeded the return_css limit (256 KiB): css holds the first part and a warning names the file size.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_insert_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Structural Bricks insert without re-sending the full tree. Send only the new subtree in "elements". Inserted roots should omit parent or use 0; descendants may reference inserted IDs. Modes: {"after":"target"} sibling after target, {"before":"target"} sibling before target, {"append_to":"target"} last children, {"prepend_to":"target"} first children, omit position = append roots to area end. Examples: after a section -> position={"after":"hero01"}; prepend to a container -> position={"prepend_to":"cards1"}. Root parents are assigned automatically. Payload IDs must be unique, use exactly 6 characters, and must not already exist in the area; omit an id to have a six-character one generated.

Style reuse hierarchy. Before writing raw values:
  Tier 1: respect active theme styles (do not override h1-h6/p/a defaults in element settings).
  Tier 2: apply existing global class via _cssGlobalClasses (look up via novamira/bricks-list-global-classes; attach via novamira/bricks-apply-global-class).
  Tier 3: reference existing variable: "color": {"raw": "var(--brand-primary)"} (look up via novamira/bricks-list-variables).
  Tier 4: create a token first via novamira/bricks-create-variable / bricks-create-global-class / bricks-add-color-palette-entry, then reference it.
  Tier 5: raw values in controls — only for non-systemic one-offs (animation timing, single transforms, clip-paths).
  Tier 6: _cssCustom — last resort.
Hex colors, px values on _padding/_margin/_gap, font sizes/weights/family, radii → NEVER raw. Always tokenize.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

function bricks_insert_content(array $input): array|WP_Error
{
    $post_id = (int) $input['post_id'];
    /** @var WP_Post|null $post */
    $post = get_post($post_id);

    if (!$post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
    }

    $area = (string) $input['area'];
    $elements = bricks_get_area_elements($post_id, $area);

    // A page corrupted behind the abilities' back (a stored circular parent
    // chain) cannot take an insert: say so up front, before any position
    // lookup walks the cycle.
    $stored_cycles = bricks_detect_circular_parents($elements);
    if ($stored_cycles !== []) {
        return bricks_error('corrupt_tree', sprintf(
            'The stored "%s" area of post %d already contains a circular parent chain, so nothing can be inserted into it. %s Read the area with novamira/bricks-get-content (summary: true) to see the parent pointers, then rewrite it with novamira/bricks-set-content (full replace with unique ids) or remove one chain member with novamira/bricks-remove-content.',
            $area,
            $post_id,
            $stored_cycles[0],
        ));
    }

    $prepared_payload = bricks_prepare_insert_payload($input['elements'], $elements);
    if (is_wp_error($prepared_payload)) {
        return $prepared_payload;
    }

    ['elements' => $inserted_elements, 'roots' => $roots, 'generated_ids' => $generated_ids] = $prepared_payload;

    $code_ids = bricks_collect_code_element_ids($inserted_elements);
    if ($code_ids !== [] && ($input['allow_code_elements'] ?? false) !== true) {
        return bricks_code_element_block($code_ids);
    }

    /** @var array<string, mixed>|null $position */
    $position = is_array($input['position'] ?? null) ? $input['position'] : null;

    [$insertion_index, $root_parent, $position_applied, $reference_id] = bricks_resolve_insert_position(
        $elements,
        $position,
    );
    if (is_wp_error($insertion_index)) {
        return $insertion_index;
    }

    $inserted_elements = bricks_assign_insert_root_parent($inserted_elements, $roots, $root_parent);
    $elements = array_merge(
        array_slice($elements, offset: 0, length: $insertion_index),
        $inserted_elements,
        array_slice($elements, offset: $insertion_index),
    );

    return bricks_finalize_insert_content([
        'input' => $input,
        'post' => $post,
        'post_id' => $post_id,
        'area' => $area,
        'inserted_count' => count($inserted_elements),
        'inserted_ids' => array_values(array_map(
            static fn(array $element): string => (string) ($element['id'] ?? ''),
            $inserted_elements,
        )),
        'inserted_root_ids' => $roots,
        'generated_ids' => $generated_ids,
        'position_applied' => $position_applied,
        'reference_id' => $reference_id,
        'parent' => $root_parent,
    ], $elements);
}

/**
 * @return array<string, string>
 */
function bricks_area_meta_keys(): array
{
    return [
        'header' => BRICKS_DB_PAGE_HEADER,
        'content' => BRICKS_DB_PAGE_CONTENT,
        'footer' => BRICKS_DB_PAGE_FOOTER,
    ];
}

/**
 * @return list<array<string, mixed>>
 */
function bricks_get_area_elements(int $post_id, string $area): array
{
    /** @var array<array-key, mixed>|string|false $stored */
    $stored = get_post_meta($post_id, bricks_area_meta_keys()[$area], single: true);

    if (!is_array($stored)) {
        return [];
    }

    /** @var list<array<string, mixed>> */
    return array_values(array_filter($stored, static fn(mixed $item): bool => is_array($item)));
}

/**
 * @param mixed $raw_elements
 * @param list<array<string, mixed>> $existing_elements
 * @return array{elements: list<array<string, mixed>>, roots: list<string>, generated_ids: array<string, string>}|WP_Error
 */
function bricks_prepare_insert_payload(mixed $raw_elements, array $existing_elements): array|WP_Error
{
    /** @var list<array<string, mixed>> $inserted_elements */
    $inserted_elements = $raw_elements;

    if ($inserted_elements === []) {
        return bricks_error('missing_elements', message: 'At least one element is required for insertion.');
    }

    $inserted_elements = array_map(static function (array $element): array {
        $element['parent'] ??= 0;
        return $element;
    }, $inserted_elements);

    $existing_index = bricks_index_elements_by_id($existing_elements);
    ['elements' => $inserted_elements, 'generated_ids' => $generated_ids] =
        bricks_assign_missing_ids($inserted_elements, array_fill_keys(array_keys($existing_index), value: true));

    $unsafe_id_error = bricks_unsafe_new_element_ids_error($inserted_elements, $existing_elements);
    if ($unsafe_id_error instanceof WP_Error) {
        return $unsafe_id_error;
    }

    $roots = bricks_insert_payload_root_ids($inserted_elements);
    $payload_errors = bricks_validate_insert_payload($inserted_elements, $existing_index, $roots);
    if ($payload_errors !== []) {
        return bricks_error('invalid_elements', implode(' ', $payload_errors));
    }

    if ($roots === []) {
        return bricks_error(
            'invalid_elements',
            message: 'The inserted payload must contain at least one root element with parent 0, parent "0", an empty parent, or an omitted parent.',
        );
    }

    return [
        'elements' => $inserted_elements,
        'roots' => $roots,
        'generated_ids' => $generated_ids,
    ];
}

/**
 * @param list<array<string, mixed>> $inserted_elements
 * @param list<string> $roots
 * @param string|int $root_parent
 * @return list<array<string, mixed>>
 */
function bricks_assign_insert_root_parent(array $inserted_elements, array $roots, string|int $root_parent): array
{
    foreach ($inserted_elements as $i => $inserted_element) {
        $id = (string) ($inserted_element['id'] ?? '');
        if (!in_array($id, $roots, strict: true)) {
            continue;
        }

        $inserted_element['parent'] = $root_parent;
        $inserted_elements[$i] = $inserted_element;
    }

    return $inserted_elements;
}

/**
 * @param array{input: array<array-key, mixed>, post: WP_Post, post_id: int, area: string, inserted_count: int, inserted_ids: list<string>, inserted_root_ids: list<string>, generated_ids: array<string, string>, position_applied: string, reference_id: string, parent: string|int} $context
 * @param list<array<string, mixed>> $elements
 */
function bricks_finalize_insert_content(array $context, array $elements): array|WP_Error
{
    $input = $context['input'];
    $post = $context['post'];
    $post_id = $context['post_id'];
    $area = $context['area'];
    $elements = bricks_normalize_write_elements($elements);
    $validation = bricks_validate_elements($elements, $context['inserted_ids']);
    $warnings = $validation['warnings'];
    $errors = $validation['errors'];

    if (count($errors) > 0) {
        return bricks_write_validation_error($post_id, $area, $errors);
    }

    if (in_array($area, ['header', 'footer'], strict: true) && $post->post_type !== BRICKS_DB_TEMPLATE_SLUG) {
        $warnings[] = sprintf(
            'Writing the "%s" area to a non-template post (type: "%s") has no visible effect — Bricks renders headers and footers from template CPTs with conditions. Create a template of type "%s" instead.',
            $area,
            $post->post_type,
            $area,
        );
    }

    $elements = bricks_ensure_children($elements);
    $commit = bricks_commit_area_write($post_id, $area, $elements, $warnings, [
        'force' => ($input['force'] ?? false) === true,
    ]);
    if ($commit instanceof WP_Error) {
        return $commit;
    }

    return bricks_build_insert_result($context, $commit);
}

/**
 * @param array{input: array<array-key, mixed>, post: WP_Post, post_id: int, area: string, inserted_count: int, inserted_ids: list<string>, inserted_root_ids: list<string>, generated_ids: array<string, string>, position_applied: string, reference_id: string, parent: string|int} $context
 * @param array{elements: list<array<string, mixed>>, warnings: list<string>, verified: bool, stored_element_count: int, css_file: string, unresolved_variables: list<string>, revision_id: int|null} $commit
 * @return array<string, mixed>
 */
function bricks_build_insert_result(array $context, array $commit): array
{
    $input = $context['input'];
    $post_id = $context['post_id'];
    $area = $context['area'];
    $elements = $commit['elements'];
    $warnings = $commit['warnings'];
    $result = bricks_with_write_verification([
        'post_id' => $post_id,
        'area' => $area,
        'inserted' => $context['inserted_count'],
        'element_count' => count($elements),
        'inserted_root_ids' => $context['inserted_root_ids'],
        'position_applied' => $context['position_applied'],
        'parent' => $context['parent'],
    ], $commit);

    if ($context['reference_id'] !== '') {
        $result['reference_id'] = $context['reference_id'];
    }
    if ($context['generated_ids'] !== []) {
        $result['generated_ids'] = bricks_generated_ids_object($context['generated_ids']);
    }

    [$result, $warnings] = bricks_apply_lint_result(
        $result,
        $warnings,
        bricks_lint_structure($elements, $context['inserted_ids']),
    );

    if (($input['return_css'] ?? false) === true) {
        [$result, $warnings] = bricks_append_return_css($result, $elements, $area, $warnings);
    }

    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }

    return $result;
}

/**
 * @param list<array<string, mixed>> $elements
 * @return array<string, int>
 */
function bricks_index_elements_by_id(array $elements): array
{
    $index = [];

    foreach ($elements as $i => $element) {
        $id = (string) ($element['id'] ?? '');
        if ($id !== '') {
            $index[$id] = $i;
        }
    }

    return $index;
}

/**
 * @param list<array<string, mixed>> $inserted_elements
 * @param array<string, int> $existing_id_index
 * @param list<string> $root_ids
 * @return list<string>
 */
function bricks_validate_insert_payload(array $inserted_elements, array $existing_id_index, array $root_ids): array
{
    $errors = [];
    /** @var array<string, int> $payload_id_counts */
    $payload_id_counts = [];
    /** @var array<string, true> $payload_ids */
    $payload_ids = [];

    foreach ($inserted_elements as $i => $element) {
        $id = (string) ($element['id'] ?? '');

        if ($id === '') {
            $errors[] = sprintf('Inserted element at index %d has an empty id.', $i);
            continue;
        }

        $payload_id_counts[$id] = ($payload_id_counts[$id] ?? 0) + 1;
        $payload_ids[$id] = true;

        if (array_key_exists($id, $existing_id_index)) {
            $errors[] = sprintf(
                'Inserted element id "%s" already exists in the target area. If this is a retry, the earlier call may already have succeeded: re-read the area with novamira/bricks-get-content (summary: true) before sending it again, or omit the ids to have fresh ones generated.',
                $id,
            );
        }
    }

    foreach ($payload_id_counts as $id => $count) {
        if ($count <= 1) {
            continue;
        }

        $errors[] = sprintf('Inserted element id "%s" appears %d times in the payload.', $id, $count);
    }

    foreach ($inserted_elements as $element) {
        $id = (string) ($element['id'] ?? '');
        /** @var string|int $parent */
        $parent = $element['parent'] ?? 0;

        if (in_array($id, $root_ids, strict: true)) {
            continue;
        }

        $parent_id = (string) $parent;
        if (!array_key_exists($parent_id, $payload_ids)) {
            $errors[] = sprintf(
                'Inserted element "%s" references parent "%s" outside the payload. Root elements must use parent 0 or omit parent; placement is controlled by position.',
                $id,
                $parent_id,
            );
        }
    }

    return array_values(array_unique($errors));
}

/**
 * @param list<array<string, mixed>> $inserted_elements
 * @return list<string>
 */
function bricks_insert_payload_root_ids(array $inserted_elements): array
{
    // This id-"0" branch may run, but it cannot affect a successful bricks-insert-content call: a new "0" id
    // fails the six-character rule, while re-sending a stored "0" id is rejected as a duplicate. It remains
    // solely for this helper's standalone contract.
    $has_zero_id = bricks_tree_has_zero_id($inserted_elements);

    $roots = [];

    foreach ($inserted_elements as $element) {
        $id = (string) ($element['id'] ?? '');
        /** @var string|int $parent */
        $parent = $element['parent'] ?? 0;
        if ($parent === 0 || $parent === '' || $parent === '0' && (!$has_zero_id || $id === '0')) {
            $roots[] = $id;
        }
    }

    return $roots;
}

/**
 * @param list<array<string, mixed>> $elements
 * @param array<string, mixed>|null $position
 * @return array{0: int|WP_Error, 1: string|int, 2: string, 3: string}
 */
function bricks_resolve_insert_position(array $elements, ?array $position): array
{
    if ($position === null) {
        return [count($elements), 0, 'append', ''];
    }

    $id_index = bricks_index_elements_by_id($elements);
    // Deliberately the row map, not the id map: a stored child with no id has
    // to reach the subtree walk so the insert is refused instead of writing
    // over a corrupt tree.
    $children_by_parent = bricks_child_rows_by_parent($elements);
    // Normalize only the resolver's local copy; the stored tree is not
    // rewritten as a side effect of sibling placement.
    $resolved_elements = bricks_normalize_root_parents($elements);

    if (array_key_exists('before', $position)) {
        return bricks_resolve_insert_before($resolved_elements, $id_index, (string) $position['before']);
    }

    if (array_key_exists('after', $position)) {
        return bricks_resolve_insert_after(
            $resolved_elements,
            $id_index,
            $children_by_parent,
            (string) $position['after'],
        );
    }

    if (array_key_exists('prepend_to', $position)) {
        return bricks_resolve_insert_prepend($id_index, $children_by_parent, (string) $position['prepend_to']);
    }

    if (array_key_exists('append_to', $position)) {
        return bricks_resolve_insert_append($id_index, $children_by_parent, (string) $position['append_to']);
    }

    return [
        bricks_error(
            'invalid_position',
            message: 'Position must contain exactly one of: after, before, append_to, prepend_to.',
        ),
        0,
        '',
        '',
    ];
}

/**
 * @param array<string, int> $id_index
 * @return array{0: int|WP_Error, 1: string|int, 2: string, 3: string}
 */
function bricks_resolve_insert_before(array $elements, array $id_index, string $target_id): array
{
    $target_index = bricks_require_insert_target_index($id_index, $target_id);
    if (is_wp_error($target_index)) {
        return [$target_index, 0, '', ''];
    }

    /** @var array<string, mixed> $target */
    $target = $elements[$target_index];
    return [$target_index, bricks_element_parent($target), 'before', $target_id];
}

/**
 * @param array<string, int> $id_index
 * @param array<string, list<string>> $children_by_parent
 * @return array{0: int|WP_Error, 1: string|int, 2: string, 3: string}
 */
function bricks_resolve_insert_after(
    array $elements,
    array $id_index,
    array $children_by_parent,
    string $target_id,
): array {
    $target_index = bricks_require_insert_target_index($id_index, $target_id);
    if (is_wp_error($target_index)) {
        return [$target_index, 0, '', ''];
    }

    /** @var array<string, mixed> $target */
    $target = $elements[$target_index];
    $end = bricks_subtree_end_index($target_id, $id_index, $children_by_parent);
    if ($end === null) {
        return [bricks_corrupt_tree_error($target_id), 0, '', ''];
    }
    return [$end + 1, bricks_element_parent($target), 'after', $target_id];
}

/**
 * @param array<string, int> $id_index
 * @param array<string, list<string>> $children_by_parent
 * @return array{0: int|WP_Error, 1: string|int, 2: string, 3: string}
 */
function bricks_resolve_insert_prepend(array $id_index, array $children_by_parent, string $target_id): array
{
    $target_index = bricks_require_insert_target_index($id_index, $target_id);
    if (is_wp_error($target_index)) {
        return [$target_index, 0, '', ''];
    }

    $child_ids = $children_by_parent[$target_id] ?? [];
    if ($child_ids === []) {
        return [$target_index + 1, $target_id, 'prepend_to', $target_id];
    }

    return [$id_index[$child_ids[0]], $target_id, 'prepend_to', $target_id];
}

/**
 * @param array<string, int> $id_index
 * @param array<string, list<string>> $children_by_parent
 * @return array{0: int|WP_Error, 1: string|int, 2: string, 3: string}
 */
function bricks_resolve_insert_append(array $id_index, array $children_by_parent, string $target_id): array
{
    $target_index = bricks_require_insert_target_index($id_index, $target_id);
    if (is_wp_error($target_index)) {
        return [$target_index, 0, '', ''];
    }

    $child_ids = $children_by_parent[$target_id] ?? [];
    if ($child_ids === []) {
        return [$target_index + 1, $target_id, 'append_to', $target_id];
    }

    $last_child_id = $child_ids[count($child_ids) - 1];
    $end = bricks_subtree_end_index($last_child_id, $id_index, $children_by_parent);
    if ($end === null) {
        return [bricks_corrupt_tree_error($target_id), 0, '', ''];
    }
    return [$end + 1, $target_id, 'append_to', $target_id];
}

/**
 * @param array<string, int> $id_index
 */
function bricks_require_insert_target_index(array $id_index, string $target_id): int|WP_Error
{
    if (array_key_exists($target_id, $id_index)) {
        return $id_index[$target_id];
    }

    return bricks_error('invalid_position', sprintf('Position reference "%s" was not found.', $target_id));
}

/**
 * Index of the last element of the subtree rooted at `$root_id` in the flat
 * array. Returns null when the stored tree is corrupted with a circular
 * parent chain (a child that is also an ancestor), which would otherwise
 * recurse until PHP dies; callers turn that into a WP_Error.
 *
 * @param array<string, int> $id_index
 * @param array<string, list<string>> $children_by_parent
 * @param array<string, true> $visited
 */
function bricks_subtree_end_index(
    string $root_id,
    array $id_index,
    array $children_by_parent,
    array &$visited = [],
): ?int {
    if (array_key_exists($root_id, $visited) || !array_key_exists($root_id, $id_index)) {
        return null;
    }
    $visited[$root_id] = true;
    $end_index = $id_index[$root_id];

    foreach ($children_by_parent[$root_id] ?? [] as $child_id) {
        $child_end = bricks_subtree_end_index($child_id, $id_index, $children_by_parent, $visited);
        if ($child_end === null) {
            return null;
        }
        $end_index = max($end_index, $child_end);
    }

    return $end_index;
}

/**
 * The error returned when a position lookup runs into a corrupted stored
 * tree (circular parent chain) instead of crashing.
 */
function bricks_corrupt_tree_error(string $reference_id): WP_Error
{
    return bricks_error('corrupt_tree', sprintf(
        'The stored element tree around "%s" contains a circular parent chain, so the insert position cannot be resolved. Read the area with novamira/bricks-get-content (summary: true) to see the parent pointers, then rewrite the area with novamira/bricks-set-content (full replace with unique ids) or remove one chain member with novamira/bricks-remove-content.',
        $reference_id,
    ));
}

/**
 * @param array<string, mixed> $element
 */
function bricks_element_parent(array $element): string|int
{
    /** @var mixed $parent */
    $parent = $element['parent'] ?? 0;

    return is_string($parent) || is_int($parent) ? $parent : 0;
}
