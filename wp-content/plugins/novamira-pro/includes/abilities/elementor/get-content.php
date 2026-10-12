<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

use WP_Post;

/**
 * Ability: Read the Elementor document tree for a post.
 *
 * Output is COMPACT by default — a skeleton tree with only element ids,
 * types, and children — so a caller can understand a page's structure
 * without paying for the full settings blob. Progressive disclosure is
 * layered on top:
 *
 *     element_id: "abc1234"   return only that element (and its children)
 *                             with full settings — the RIGHT way to drill
 *                             after seeing the skeleton
 *     full_dump: true         attach every element's settings — use ONLY
 *                             for full page clones / audits, NEVER as a
 *                             "give me everything" first call
 */

if (!defined('ABSPATH')) {
    exit();
}

/** How many unregistered type names the get-content warning names before eliding. */
const NOVAMIRA_ELEMENTOR_WARNING_TYPE_CAP = 6;

wp_register_ability('novamira/elementor-get-content', [
    'label' => __('Get Elementor Content', domain: 'novamira-pro'),
    'description' => __(
        'Reads the Elementor document tree for a post. Output is COMPACT by default — a structural skeleton with only element ids, types, widget types, and children (no settings). The skeleton is small (typically a few hundred tokens) and is the right first call for tasks like "remake this page", "edit a widget", "understand the layout". After the skeleton, drill into the widget you care about with element_id:"<id>" — you get back that subtree (plus children) with full settings, cheaply. Do NOT pass full_dump:true unless you genuinely need every widget\'s settings at once (e.g. cloning the entire page verbatim or running a full audit); for every other use case it wastes thousands of tokens on data you will not use.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post ID.',
            ],
            'element_id' => [
                'type' => 'string',
                'description' => 'Return only the subtree rooted at this element id, with full settings. This is the cheap, targeted way to zoom into one widget after reading the skeleton. Prefer this over full_dump.',
            ],
            'full_dump' => [
                'type' => 'boolean',
                'description' => 'HEAVY. When true, every element in the tree includes its full settings — expect thousands of tokens for a real page. Use ONLY for whole-page clones or audits where you really need every widget at once. For remakes, edits, or structure understanding prefer the default skeleton + element_id drill. Defaults to false.',
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'post_id' => ['type' => 'integer'],
            'post_title' => ['type' => 'string'],
            'template_type' => ['type' => 'string'],
            'content' => [
                'type' => 'array',
                'description' => 'Parsed Elementor element tree. Compact skeleton by default, single subtree when element_id is used, full dump when full_dump=true — note the skeleton omits settings and styles, so the full dump is the form to clone from. A NAMED SET of ten mistyped node keys (`element_id`, `elementId`, `element_settings`, `elementSettings`, `children`, `style`, `elementType`, `is_inner`, `is_locked`, `editorSettings`) is left out of this tree and reported in `removed_node_keys` with the value each held, because the write abilities refuse those spellings. That set is a named list, NOT a check of the whole node shape: any other key a document happens to hold is passed through here untouched and is not reported.',
                'items' => ['type' => 'object'],
            ],
            'element_count' => ['type' => 'integer'],
            'has_element_cache' => [
                'type' => 'boolean',
                'description' => 'True when Elementor Pro would serve a cached render for this document, so a programmatic edit will not show on the front end until the cache is cleared with novamira/elementor-clear-document-cache.',
            ],
            'cache_expires_at' => [
                'type' => 'integer',
                'description' => 'When has_element_cache is true: the cached render\'s expiry as Elementor stores it (a current_time()-based timestamp).',
            ],
            'cache_expires_in_seconds' => [
                'type' => 'integer',
                'description' => 'When has_element_cache is true: seconds until the cached render expires.',
            ],
            'hint' => [
                'type' => 'string',
                'description' => 'Progressive-disclosure tip describing how to drill deeper when the default compact view is not enough.',
            ],
            'warning' => [
                'type' => 'string',
                'description' => 'Present only when the returned tree uses v4 atomic (`e-*`) types Elementor cannot resolve from their stored node shapes — not registered on this site, or in an elType/widgetType envelope that does not match how the type IS registered; it names them.',
            ],
            'removed_node_keys' => [
                'type' => 'array',
                'description' => 'The ten mistyped node keys this ability knows by name, when the document holds any: `element_id`, `elementId`, `element_settings`, `elementSettings`, `children`, `style`, `elementType`, `is_inner`, `is_locked`, `editorSettings`. They are NOT in `content`, because the write abilities refuse those spellings and `content` has to stay writable. Each record carries the value the key held, so nothing is lost by their absence — read them before writing `content` back, because that write is what removes them from storage. On the compact skeleton the `value` field is omitted: call again with full_dump:true to get the values. This is a NAMED LIST, not a full check of the node shape: a document can hold other keys Elementor does not read, and those are neither removed nor reported.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'path' => [
                            'type' => 'string',
                            'description' => 'Where it sat, e.g. content[0].elements[1].children.',
                        ],
                        'key' => ['type' => 'string', 'description' => 'The key as stored.'],
                        'expected' => [
                            'type' => 'string',
                            'description' => 'The node key it corresponds to (`children` → `elements`, `style` → `styles`, …).',
                        ],
                        'value' => [
                            'type' => ['array', 'object', 'string', 'number', 'integer', 'boolean', 'null'],
                            'description' => 'What the key held, verbatim — a whole subtree in the case of `children`. Omitted on the compact skeleton.',
                        ],
                        'canonical_present' => [
                            'type' => 'boolean',
                            'description' => 'True when the node also carries `expected`, which is the value Elementor uses. It does not mean this one is empty.',
                        ],
                    ],
                    'required' => ['path', 'key', 'expected', 'canonical_present'],
                ],
            ],
            'notice' => [
                'type' => 'string',
                'description' => 'Accompanies `removed_node_keys`: what was found, that writing `content` back removes it from storage, and where the values are.',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\elementor_get_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function elementor_get_content(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);
    /** @var WP_Post|null $post */
    $post = $post_id > 0 ? get_post($post_id) : null;
    if (!$post) {
        return ['success' => false, 'error' => "Post {$post_id} not found."];
    }

    [$elements, $error] = el_read_page($post_id);
    if ($elements === null) {
        return ['success' => false, 'error' => $error ?? 'Unknown error.'];
    }

    /** @var mixed $template_type */
    $template_type = get_post_meta($post_id, key: '_elementor_template_type', single: true);

    $focus_id = (string) ($input['element_id'] ?? '');
    if ($focus_id !== '') {
        $found = el_find($elements, $focus_id);
        if ($found === null) {
            return ['success' => false, 'error' => "Element '{$focus_id}' not found on post {$post_id}."];
        }
        $elements = [$found];
    }

    $compact = $focus_id === '' && ($input['full_dump'] ?? false) !== true;

    // The compact skeleton is built by whitelisting structural fields, so it
    // cannot carry residue — but silence would let an agent clone a dirty
    // document without ever being told, so the same scan runs there too and
    // reports the paths (the values come with full_dump, which is the form to
    // clone from anyway).
    /** @var list<array{path: string, key: string, expected: string, value: mixed, canonical_present: bool}> $removed */
    $removed = [];
    $tree = el_normalize_output_tree($elements, $removed);
    $payload = [
        'success' => true,
        'post_id' => $post_id,
        'post_title' => $post->post_title,
        'template_type' => is_string($template_type) ? $template_type : '',
        'content' => $compact ? el_strip_settings_tree($elements) : $tree,
        'element_count' => el_count_elements($elements),
    ];
    $payload += $compact ? el_residue_signal($removed) : el_residue_report($removed);
    $unresolvable = el_tree_unresolvable_atomic_types($elements);
    if ($unresolvable !== []) {
        $payload['warning'] = sprintf(
            'Elementor cannot resolve these v4 atomic types from their stored node shapes (%s): those elements render as nothing and the next editor save drops them. Check that each type is registered here (novamira/elementor-check-setup, atomic.runtime_available) and that its elType/widgetType pair matches how it is registered.',
            implode(', ', $unresolvable),
        );
    }

    if ($compact) {
        $payload['hint'] = 'Compact skeleton. To inspect one widget pass element_id:"<id>" (cheap, targeted). Only pass full_dump:true when you really need every widget\'s settings at once (e.g. full page clone or audit).';
    }

    return array_merge($payload, el_document_cache_status($post_id));
}

/**
 * The `removed_node_keys` / `notice` pair for a read that found one or more of
 * the ten mistyped spellings in EL_CONFUSABLE_NODE_KEYS, or an empty array when
 * it found none of them. It says nothing about any other key the document holds.
 *
 * Each record carries the VALUE, not just the path. The point of removing them
 * from the tree is that it can be written back; the point of reporting the
 * values is that writing it back is destructive to whatever those keys held, and
 * the caller has to be able to see that before choosing. A `children` key can
 * hold a whole subtree Elementor never rendered.
 *
 * The scope is EL_CONFUSABLE_NODE_KEYS and nothing else. A document can hold
 * other keys Elementor does not read — the raw write path stores whatever it is
 * given — and this ability neither removes nor reports those. See the note on
 * that constant for why the list cannot be closed here.
 *
 * The compact skeleton omits the values, because it omits settings and styles
 * too and is not the form to clone from: it reports the paths so a dirty
 * document is never silent, and says where the values are.
 *
 * @param list<array{path: string, key: string, expected: string, value: mixed, canonical_present: bool}> $removed
 * @return array<string, mixed>
 */
function el_residue_report(array $removed): array
{
    if ($removed === []) {
        return [];
    }

    return [
        'removed_node_keys' => $removed,
        'notice' => el_residue_notice($removed, where: ' with the value each one held'),
    ];
}

/**
 * The compact skeleton's version of {@see el_residue_report}: the same records
 * without their values, so a dirty document is never silent on the cheap read
 * while the payload stays a skeleton.
 *
 * @param list<array{path: string, key: string, expected: string, value: mixed, canonical_present: bool}> $removed
 * @return array<string, mixed>
 */
function el_residue_signal(array $removed): array
{
    if ($removed === []) {
        return [];
    }

    $records = array_map(static function (array $record): array {
        unset($record['value']);
        return $record;
    }, $removed);

    return [
        'removed_node_keys' => $records,
        'notice' => el_residue_notice(
            $removed,
            where: ' by path — call this ability again with full_dump:true to get their values, which is also the form to clone from',
        ),
    ];
}

/**
 * Shared wording for the two residue reports.
 *
 * @param list<array{path: string, key: string, expected: string, value: mixed, canonical_present: bool}> $removed
 */
function el_residue_notice(array $removed, string $where): string
{
    $paths = array_column($removed, 'path');
    $named = implode(', ', array_slice($paths, offset: 0, length: 5));
    if (count($paths) > 5) {
        $named .= ', …';
    }

    return sprintf(
        'This document holds %d mistyped node key(s) from the named set this ability knows (%s). Elementor never read them, so they change nothing on the page — but they can still hold what somebody wrote, and `children` can hold an entire subtree. They are listed in `removed_node_keys`%s, and they are NOT in `content`, so writing `content` back with novamira/elementor-set-content is what removes them from storage: read what they held first, and move anything worth keeping into the real key (%s). Other keys Elementor does not read are not detected — this is a named list, not a check of the whole node shape.',
        count($removed),
        $named,
        $where,
        implode(', ', array_unique(array_column($removed, 'expected'))),
    );
}

/**
 * The distinct v4 atomic (`e-*`) type names a tree uses that Elementor cannot
 * resolve from the node they are stored in, sorted and capped so the warning
 * stays one line. Two situations produce that, and the warning covers both:
 * the type is not registered on this site at all, or it is registered with
 * the manager the node's envelope does NOT select (see
 * el_node_type_registered()).
 *
 * The `e-` prefix alone is not the answer: some atomic types are gated by
 * their own module and stay registered when the atomic-elements experiment
 * is off, so a prefix test would claim a page is broken when it renders
 * perfectly. The registries are asked instead, live, because that is what
 * actually decides whether an element renders and survives an editor save.
 *
 * @param list<array<string, mixed>> $elements
 * @return list<string>
 */
function el_tree_unresolvable_atomic_types(array $elements): array
{
    /** @var array<string, true> $found */
    $found = [];
    el_collect_unresolvable_atomic_types($elements, $found);

    $names = array_keys($found);
    sort($names);
    if (count($names) > NOVAMIRA_ELEMENTOR_WARNING_TYPE_CAP) {
        $names = array_slice($names, offset: 0, length: NOVAMIRA_ELEMENTOR_WARNING_TYPE_CAP);
        $names[] = '…';
    }

    return $names;
}

/**
 * Walk the tree collecting unresolvable `e-*` type names as a set. Resolves
 * each node through el_element_widget_type() so both shapes are covered:
 * atomic containers, which are their own elType, and atomic widgets, which
 * are stored as `elType: widget` + an `e-*` widgetType.
 *
 * KNOWN LIMITATION — a node whose `elType` is empty or absent can still slip
 * through. el_element_widget_type() yields '' for `{elType: "", widgetType:
 * "e-heading"}`, so the `e-` prefix test never sees the widget type; and when
 * `elType` is missing entirely both helpers here default it to "widget",
 * which on a capable site resolves the widgetType and suppresses the warning
 * even though Elementor reads the required `elType` key directly and cannot
 * instantiate the node. Neither shape is canonical — our own writes normalize
 * or reject them, and Elementor never stores them — so this only affects
 * imported or hand-edited documents, and the cost of tightening it is a
 * second type-resolution path that would have to stay in step with the write
 * side. A future hardening would have this collector examine an `e-*`
 * `widgetType` explicitly when `elType` is missing or unusable, leaving
 * el_element_widget_type() and the write/refusal path untouched.
 *
 * @param list<array<string, mixed>> $elements
 * @param array<string, true> $found
 */
function el_collect_unresolvable_atomic_types(array $elements, array &$found): void
{
    foreach ($elements as $element) {
        $type = el_element_widget_type($element);
        if (str_starts_with($type, 'e-') && !isset($found[$type]) && !el_node_type_registered($element, $type)) {
            $found[$type] = true;
        }
        /** @var list<array<string, mixed>> $children */
        $children = is_array($element['elements'] ?? null) ? $element['elements'] : [];
        if ($children !== []) {
            el_collect_unresolvable_atomic_types($children, $found);
        }
    }
}

/**
 * Whether the manager the node's own envelope selects registers its type.
 *
 * Elementor instantiates `elType: "widget"` through the widgets manager and
 * everything else through the elements manager — the same split
 * el_element_identity_error() enforces on the write side — so asking either
 * manager would clear a cross-wired node that cannot be built: a
 * `{elType: "widget", widgetType: "e-flexbox"}` is not a widget however
 * registered `e-flexbox` is as an element, and vice versa. Our own writes
 * normalize or reject those shapes, but an imported or hand-edited document
 * can carry them, and a read is where that should surface.
 *
 * @param array<string, mixed> $element
 */
function el_node_type_registered(array $element, string $resolved_type): bool
{
    $el_type = (string) ($element['elType'] ?? 'widget');

    return $el_type === 'widget' ? el_is_widget_type($resolved_type) : el_is_element_type($el_type);
}

/**
 * Whether Elementor Pro would serve a cached render for this document (which
 * would mask a programmatic edit until the cache is cleared), mirroring
 * Document::print_elements + get_document_cache: the element-cache feature must
 * be active, the _elementor_element_cache meta present with a future timeout,
 * and its value an array. When served, also returns the expiry (a
 * current_time()-based timestamp, as Elementor stores it) and seconds remaining.
 *
 * @return array{has_element_cache: bool, cache_expires_at?: int, cache_expires_in_seconds?: int}
 */
function el_document_cache_status(int $post_id): array
{
    if (get_option('elementor_element_cache_ttl') === 'disable') {
        return ['has_element_cache' => false];
    }
    $cache = el_decode_cache_meta(get_post_meta($post_id, key: el_element_cache_meta_key(), single: true));
    if ($cache === null) {
        return ['has_element_cache' => false];
    }
    /** @var mixed $raw_timeout */
    $raw_timeout = $cache['timeout'] ?? null;
    $timeout = 0;
    if (is_int($raw_timeout)) {
        $timeout = $raw_timeout;
    }
    if (is_string($raw_timeout) && ctype_digit($raw_timeout)) {
        $timeout = (int) $raw_timeout;
    }
    $now = (int) current_time('timestamp');
    if ($timeout <= $now || !isset($cache['value']) || !is_array($cache['value'])) {
        return ['has_element_cache' => false];
    }
    return [
        'has_element_cache' => true,
        'cache_expires_at' => $timeout,
        'cache_expires_in_seconds' => $timeout - $now,
    ];
}

/**
 * Decode the element-cache meta into an array. Elementor stores it JSON-encoded
 * (Document::update_json_meta); a raw array is accepted too.
 *
 * @param mixed $raw
 * @return array<array-key, mixed>|null
 */
function el_decode_cache_meta($raw): ?array
{
    if (is_array($raw)) {
        return $raw;
    }
    if (is_string($raw) && $raw !== '') {
        /** @var mixed $decoded */
        $decoded = json_decode($raw, associative: true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }
    return null;
}

/**
 * Count every element in a tree, including nested children.
 *
 * @param list<array<string, mixed>> $elements
 */
function el_count_elements(array $elements): int
{
    $count = 0;
    foreach ($elements as $element) {
        ++$count;
        if (!is_array($element['elements'] ?? null)) {
            continue;
        }

        /** @var list<array<string, mixed>> $children */
        $children = $element['elements'];
        $count += el_count_elements($children);
    }
    return $count;
}

/**
 * Walk an element tree and drop the `settings` field from every node,
 * keeping only the structural fields (id, elType, widgetType, children).
 *
 * @param list<array<string, mixed>> $elements
 * @return list<array<string, mixed>>
 */
function el_strip_settings_tree(array $elements): array
{
    $out = [];
    foreach ($elements as $element) {
        $out[] = el_strip_settings_node($element);
    }
    return $out;
}

/**
 * Walk an element tree and normalize each node's `styles` field for output.
 * When `styles` is present and empty (either `[]` or `{}`), replace it with
 * a `stdClass` so the JSON encoder emits `{}` instead of `[]` — the shape
 * agents should see when they inspect existing content, since the write
 * path expects a keyed map of style IDs. PHP decodes the stored `{}` as an
 * empty list, which mis-signals "array of anonymous styles" to the caller.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<array{path: string, key: string, expected: string, value: mixed, canonical_present: bool}> $removed
 * @return list<array<string, mixed>>
 */
function el_normalize_output_tree(array $elements, array &$removed): array
{
    $out = [];
    foreach (array_keys($elements) as $index) {
        $out[] = el_normalize_output_node($elements[$index], sprintf('content[%s].', $index), $removed);
    }
    return $out;
}

/**
 * Normalize a single element for output and recurse into its children.
 *
 * @param array<string, mixed> $element
 * @param list<array{path: string, key: string, expected: string, value: mixed, canonical_present: bool}> $removed
 * @return array<string, mixed>
 */
function el_normalize_output_node(array $element, string $prefix, array &$removed): array
{
    // Keys no Elementor format defines, left in the document by the write paths
    // that used to persist them verbatim. Handing them back would produce a
    // tree our own writers refuse, so they come out here and are reported
    // instead — see el_strip_confusable_node_keys().
    $element = el_strip_confusable_node_keys($element, $prefix, $removed);

    if (array_key_exists('styles', $element)) {
        $element['styles'] = el_normalize_output_styles($element['styles']);
    }

    if (array_key_exists('interactions', $element)) {
        $element['interactions'] = el_normalize_output_interactions($element['interactions']);
    }

    if (is_array($element['elements'] ?? null)) {
        /** @var list<array<string, mixed>> $children */
        $children = $element['elements'];
        $new_children = [];
        foreach (array_keys($children) as $index) {
            $new_children[] = el_normalize_output_node(
                $children[$index],
                sprintf('%selements[%s].', $prefix, $index),
                $removed,
            );
        }
        $element['elements'] = $new_children;
    }

    return $element;
}

/**
 * Map empty `styles` shapes (both `[]` from raw meta and the decoded `{}`
 * which PHP hands back as `[]`) to a `stdClass` instance so JSON encoding
 * produces `{}` — the shape the write path expects. Non-empty arrays are
 * left alone: PHP encodes a keyed array as a JSON object already.
 */
function el_normalize_output_styles(mixed $styles): mixed
{
    if (is_array($styles) && $styles === []) {
        return new \stdClass();
    }
    return $styles;
}

/**
 * Elementor serializes interactions as a JSON string on disk. Leaving that
 * through to the caller forces every downstream tool to JSON.parse one
 * field but not others (e.g. `styles` which is already an array). The
 * asymmetry is a paper cut that compounds: diff tools, inspectors, and
 * edit roundtrips all grow a special case. Decode once here so `get-content`
 * hands back a normal object for atomic interactions, and leave the empty
 * shapes (`""`, `[]`, `null`, already-decoded array) as-is.
 */
function el_normalize_output_interactions(mixed $interactions): mixed
{
    if (!is_string($interactions) || $interactions === '') {
        return $interactions;
    }

    /** @var mixed $decoded */
    $decoded = json_decode($interactions, associative: true);
    if (!is_array($decoded)) {
        return $interactions;
    }
    return $decoded;
}

/**
 * Strip settings from a single element and recurse into its children.
 *
 * @param array<string, mixed> $element
 * @return array<string, mixed>
 */
function el_strip_settings_node(array $element): array
{
    $compact = [];

    foreach (['id', 'elType', 'widgetType'] as $field) {
        if (!array_key_exists($field, $element)) {
            continue;
        }
        $compact[$field] = $element[$field];
    }

    if (is_array($element['elements'] ?? null)) {
        /** @var list<array<string, mixed>> $children */
        $children = $element['elements'];
        $compact['elements'] = el_strip_settings_tree($children);
    }

    return $compact;
}
