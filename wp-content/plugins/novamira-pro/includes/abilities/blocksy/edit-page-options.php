<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: merge-edit one post's per-post Blocksy options.
 */

wp_register_ability('novamira/blocksy-edit-page-options', [
    'label' => __('Edit Blocksy Page Options', domain: 'novamira-pro'),
    'description' => __(
        'Sets per-post Blocksy options on ONE post/page/CPT entry — the per-page overrides of the editor\'s "Blocksy Settings" panel — with merge semantics: only the keys you pass change, every other stored key (including keys this schema does not model) is preserved byte-for-byte. Typical landing-page recipe: {"has_hero_section": "disabled"} hides the page title band on THIS page only; {"page_structure_type": "type-4"} forces normal width without sidebar; {"vertical_spacing_source": "custom", "content_area_spacing": "none"} removes the content-area top/bottom spacing; {"disable_header": "yes", "disable_footer": "yes"} drops the header/footer — none of these touch the global customizer options. Keys are validated against the post\'s OWN per-post schema (posts/pages/blog page/supported CPTs differ — read it with blocksy-get-page-options): unknown keys are refused with nothing written, finite choices are enforced (has_hero_section: default|enabled|disabled; page_structure_type: default|type-3 narrow|type-4 normal|type-2 left sidebar|type-1 right sidebar; content_area_spacing: both|top|bottom|none; content_style: wide|boxed, responsive map allowed), ct-switch keys take yes/no or booleans, sliders/numbers must sit inside their declared ranges/units, structured values must match the option-file default\'s shape, and every value then passes Blocksy\'s own post-meta sanitizer (strings with < or > are refused, never blanked). After a verified persist the theme\'s per-post CSS cache (styles_descriptor) is regenerated exactly like an editor save, so the change renders on the next front-end request. The posts page and the WooCommerce shop page only honor disable_header/disable_footer.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'Post id (not a revision, not a Content Block).',
            ],
            'options' => [
                'type' => 'object',
                'description' => 'Option id → new value (partial map; at least one key). Ids and value shapes per blocksy-get-page-options for this post.',
            ],
        ],
        'required' => ['post_id', 'options'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'post_type' => ['type' => 'string'],
            'schema' => ['type' => 'string', 'enum' => ['post', 'page', 'blog', 'default', 'integration']],
            'changed' => [
                'type' => 'boolean',
                'description' => 'False when every passed value already equalled the stored value (no-op, nothing rewritten).',
            ],
            'written' => [
                'type' => 'object',
                'description' => 'Option id → the value persisted (after coercion, e.g. boolean → yes/no on ct-switch).',
            ],
            'previous' => [
                'type' => 'object',
                'description' => 'Option id → {value, source: stored|default} before the write (source=default: the key was not stored, the value shown is the option-file default that applied).',
            ],
            'preserved_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Stored keys left untouched by this write (names only). The theme-managed CSS cache `styles_descriptor` appears here only on a no-op write (styles_descriptor: unchanged), where it is genuinely preserved; on a real write it is regenerated or dropped and is NOT listed — see styles_descriptor.',
            ],
            'styles_descriptor' => [
                'type' => 'string',
                'enum' => ['regenerated', 'dropped', 'unchanged'],
                'description' => 'What happened to the theme\'s per-post CSS cache: regenerated (editor-save parity), dropped (the theme could not produce one — the front end regenerates it on the next view), unchanged (no-op write).',
            ],
        ],
        'required' => [
            'post_id',
            'post_type',
            'schema',
            'changed',
            'written',
            'previous',
            'preserved_keys',
            'styles_descriptor',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_edit_page_options',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read blocksy-get-page-options first (pass `ids`) to learn the valid keys, current values and choices for THIS post — page and post schemas differ, and the posts/shop page accepts only disable_header/disable_footer. The write is validated and atomic: any invalid key or value means nothing is persisted. Prefer these per-page keys over global customizer flips when the user wants one page to look different.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: mixed, options?: mixed} $input
 * @return array{
 *     post_id: int,
 *     post_type: string,
 *     schema: string,
 *     changed: bool,
 *     written: array<array-key, mixed>|\stdClass,
 *     previous: array<array-key, array{value: mixed, source: string}>|\stdClass,
 *     preserved_keys: list<string>,
 *     styles_descriptor: string,
 * }|WP_Error
 */
function blocksy_edit_page_options(array $input): array|WP_Error
{
    if (!blocksy_theme_available()) {
        return bly_theme_inactive_error();
    }

    $post = bly_po_get_post($input['post_id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    /** @var mixed $raw_options */
    $raw_options = $input['options'] ?? null;
    if ($raw_options instanceof \stdClass) {
        $raw_options = get_object_vars($raw_options);
    }
    if (!is_array($raw_options) || $raw_options === [] || bly_array_is_list($raw_options)) {
        return bly_error('bly_invalid_input', message: '`options` must be a non-empty object of option id → value.');
    }
    if (count($raw_options) > BLY_PO_MAX_IDS) {
        return bly_error('bly_invalid_input', sprintf(
            '`options` holds %d keys, above the %d-key cap for one edit.',
            count($raw_options),
            BLY_PO_MAX_IDS,
        ));
    }

    $schema = bly_po_schema($post);
    if ($schema instanceof WP_Error) {
        return $schema;
    }

    // Pre-lock: every current-INDEPENDENT check (unknown keys, caps, the
    // typed gates on non-typography values, shape of typography
    // candidates) — invalid requests never take the row lock.
    $prechecked = epo_validate_batch($raw_options, $schema['index'], $post);
    if ($prechecked instanceof WP_Error) {
        return $prechecked;
    }

    $outcome = ['previous' => [], 'preserved' => [], 'descriptor' => 'unchanged', 'validated' => []];
    $result = bly_cb_locked_atts_edit(
        $post->ID,
        // A closure, not an arrow fn: arrow fns capture by value and the
        // outcome must flow back out of the locked section.
        static function (array $prior) use ($prechecked, $schema, $post, &$outcome): array|WP_Error {
            // In-lock: complete typography candidates from the FRESH
            // stored value, run their typed gates, then the theme's own
            // sanitizer over the final batch — only then merge + persist.
            $validated = epo_finalize_batch($prechecked, $schema['index'], $prior);
            if ($validated instanceof WP_Error) {
                return $validated;
            }
            $outcome['validated'] = $validated;
            return epo_mutate($prior, $validated, $schema['index'], $post, $outcome);
        },
        subject: 'post',
    );
    if ($result instanceof WP_Error) {
        return $result;
    }
    $validated = $outcome['validated'];

    $written = [];
    /** @var mixed $value */
    foreach ($validated as $id => $value) {
        /** @var mixed */
        $written[$id] = bly_cap_value($value);
    }

    return [
        'post_id' => $post->ID,
        'post_type' => $post->post_type,
        'schema' => $schema['kind'],
        'changed' => $result['changed'],
        'written' => bly_object_or_map($written),
        'previous' => bly_object_or_map($outcome['previous']),
        'preserved_keys' => $result['changed']
            ? $outcome['preserved']
            : epo_all_keys_preserved($result['atts'], $validated),
        'styles_descriptor' => $outcome['descriptor'],
    ];
}

/**
 * The in-lock compute step: record the previous state of every edited
 * key, merge the validated values over the fresh stored array (every
 * other key untouched), detect the no-op, then — editor-save parity —
 * recompute the theme's per-post CSS cache from the new values (a
 * descriptor the theme cannot produce is dropped so the front end
 * regenerates it, never left stale). `$outcome` carries previous /
 * preserved / descriptor back to the caller.
 *
 * @param array<array-key, mixed> $prior
 * @param array<string, mixed> $validated
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $index
 * @param array{previous: array<string, array{value: mixed, source: string}>, preserved: list<string>, descriptor: string, validated: array<string, mixed>} $outcome
 * @param-out array{previous: array<string, array{value: mixed, source: string}>, preserved: list<string>, descriptor: string, validated: array<string, mixed>} $outcome
 * @return array<array-key, mixed>
 */
function epo_mutate(array $prior, array $validated, array $index, WP_Post $post, array &$outcome): array
{
    $next = $prior;
    /** @var mixed $value */
    foreach ($validated as $id => $value) {
        $was_stored = array_key_exists($id, $prior);
        /** @var mixed $default */
        $default = $index[$id]['option']['value'] ?? null;
        $outcome['previous'][$id] = [
            'value' => bly_cap_value($was_stored ? $prior[$id] : $default),
            'source' => $was_stored ? 'stored' : 'default',
        ];
        /** @var mixed */
        $next[$id] = $value;
    }
    if ($next === $prior) {
        // Same values: no-op — the descriptor stays as it is.
        return $prior;
    }

    foreach (array_keys($prior) as $key) {
        $name = (string) $key;
        if (!array_key_exists($name, $validated) && $name !== BLY_PO_STYLES_KEY) {
            $outcome['preserved'][] = bly_ext_clean_text($name, BLY_MAX_EXTRA_KEY_BYTES);
        }
    }

    unset($next[BLY_PO_STYLES_KEY]);
    $descriptor = bly_po_compute_styles_descriptor($post, $next);
    if ($descriptor === null) {
        $outcome['descriptor'] = 'dropped';
        return $next;
    }
    $next[BLY_PO_STYLES_KEY] = $descriptor;
    $outcome['descriptor'] = 'regenerated';
    return $next;
}

/**
 * Pre-lock validation of the whole batch against the post's schema —
 * everything that does not depend on the CURRENT stored value: every key
 * must be in the schema (unknown keys — including the theme-managed
 * styles_descriptor — are refused together, nothing written); every
 * non-typography value clears the per-option gates
 * (bly_po_validate_value); ct-typography candidates are only checked for
 * size caps and shape (a keyed object) here — their completion from the
 * stored value, their typed gates and the theme sanitizer over the final
 * batch run INSIDE the row lock (epo_finalize_batch), so the value that
 * is merged is completed from the fresh in-lock read, never from a
 * pre-lock snapshot a concurrent writer may have outdated.
 *
 * @param array<array-key, mixed> $raw
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $index
 * @return array{fixed: array<string, mixed>, typography: array<string, array<array-key, mixed>>}|WP_Error
 */
function epo_validate_batch(array $raw, array $index, WP_Post $post): array|WP_Error
{
    $unknown = [];
    foreach (array_keys($raw) as $key) {
        $id = (string) $key;
        if (!isset($index[$id])) {
            $unknown[] = bly_ext_clean_text($id, BLY_MAX_EXTRA_KEY_BYTES);
        }
    }
    if ($unknown !== []) {
        $hint = in_array(BLY_PO_STYLES_KEY, $unknown, strict: true)
            ? ' `styles_descriptor` is the theme\'s per-post CSS cache and is managed automatically — never write it.'
            : '';
        return bly_error('bly_invalid_input', sprintf(
            'Unknown per-post option key(s) for post %d (%s schema): %s. Nothing was written — read blocksy-get-page-options for the keys this post accepts.%s',
            $post->ID,
            bly_ext_clean_text($post->post_type, BLY_EXT_MAX_NAME_BYTES),
            implode(', ', $unknown),
            $hint,
        ));
    }

    $fixed = [];
    $typography = [];
    /** @var mixed $value */
    foreach ($raw as $key => $value) {
        $id = (string) $key;
        if ($index[$id]['type'] === 'ct-typography') {
            $shape = bly_typography_candidate_shape($index[$id], $value);
            if ($shape instanceof WP_Error) {
                return $shape;
            }
            $typography[$id] = $shape;
            continue;
        }
        /** @var mixed $clean */
        $clean = bly_po_validate_value($index[$id], $value);
        if ($clean instanceof WP_Error) {
            return $clean;
        }
        /** @var mixed */
        $fixed[$id] = $clean;
    }
    return ['fixed' => $fixed, 'typography' => $typography];
}

/**
 * In-lock finalization: complete every ct-typography candidate from the
 * FRESH stored value (`$prior`, read under the row lock — else the
 * option-file default) and run its per-option gates, then the theme's
 * own post-meta sanitizer over the whole final batch
 * (bly_po_theme_sanitize). The returned map is what epo_mutate merges
 * and what `written` reports. Key order: the non-typography keys first,
 * in input order (the pre-lock `fixed` batch), then the typography keys
 * in input order — `written` follows the same order.
 *
 * @param array{fixed: array<string, mixed>, typography: array<string, array<array-key, mixed>>} $prechecked
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $index
 * @param array<array-key, mixed> $prior the fresh in-lock stored options
 * @return array<string, mixed>|WP_Error
 */
function epo_finalize_batch(array $prechecked, array $index, array $prior): array|WP_Error
{
    $validated = $prechecked['fixed'];
    foreach ($prechecked['typography'] as $id => $candidate) {
        /** @var mixed $current */
        $current = array_key_exists($id, $prior) ? $prior[$id] : $index[$id]['option']['value'] ?? null;
        /** @var mixed $clean */
        $clean = bly_po_validate_value($index[$id], $candidate, $current);
        if ($clean instanceof WP_Error) {
            return $clean;
        }
        /** @var mixed */
        $validated[$id] = $clean;
    }
    return bly_po_theme_sanitize($validated);
}

/**
 * On a no-op write every stored key outside the batch is, trivially,
 * preserved — reported the same way a real write reports them.
 *
 * @param array<array-key, mixed> $atts
 * @param array<string, mixed> $validated
 * @return list<string>
 */
function epo_all_keys_preserved(array $atts, array $validated): array
{
    $out = [];
    foreach (array_keys($atts) as $key) {
        $name = (string) $key;
        if (!array_key_exists($name, $validated)) {
            $out[] = bly_ext_clean_text($name, BLY_MAX_EXTRA_KEY_BYTES);
        }
    }
    return $out;
}
