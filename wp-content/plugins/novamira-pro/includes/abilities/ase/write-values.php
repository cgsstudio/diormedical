<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\ASE;

use WP_Query;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Write CFT field values for a post / term / options-page target.
 */
wp_register_ability('novamira/ase-write-values', [
    'label' => __('Write ASE Field Values', domain: 'novamira-pro'),
    'description' => __(
        'Writes ASE Custom Field Types (CFT) values to a target: post (by post ID), term (by term ID), or options-page (by slug). Each field\'s outcome is reported independently in results[] — the call never aborts on partial failure. Unknown fields return ase_unknown_field, layout fields (tab/heading/line_break) return ase_layout_field_write, and successful writes return status: ok.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => [
                'type' => 'object',
                'description' => 'The object to write field values to.',
                'properties' => [
                    'type' => [
                        'type' => 'string',
                        'enum' => ['post', 'term', 'options-page'],
                        'description' => 'Target type: post, term, or options-page.',
                    ],
                    'id' => [
                        'description' => 'Post ID (integer), term ID (integer), or options-page slug (string).',
                    ],
                ],
                'required' => ['type', 'id'],
                'additionalProperties' => false,
            ],
            'values' => [
                'type' => 'object',
                'description' => 'Map of field name to new value. Every entry is attempted independently.',
            ],
        ],
        'required' => ['target', 'values'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => ['type' => 'object'],
            'results' => [
                'type' => 'array',
                'description' => 'Per-field write outcome.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'field' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['ok', 'error']],
                        'code' => ['type' => 'string'],
                        'message' => ['type' => 'string'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_write_values',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Per-field write — each outcome is reported independently. Layout fields (tab/heading/line_break) always return ase_layout_field_write. Unknown field names return ase_unknown_field. The call never aborts on partial failure: all fields are attempted and all results are returned.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function ase_write_values(array $input): array
{
    $guard = ase_runtime_guard();
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    $guard = ase_pro_guard();
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    $guard = ase_module_guard('custom_content_types', __(
        'Enable the "Custom Field Types" module in ASE settings.',
        domain: 'novamira-pro',
    ));
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    // ASE's cfgroup_functions.php is only require_once'd from the admin web
    // context (bootstrap.php → init hook). In CLI / REST / cron contexts the
    // global helpers (find_cf, update_cf) are missing unless we load them here.
    ase_ensure_cfgroup_functions_loaded();

    /** @var mixed $raw_target */
    $raw_target = $input['target'] ?? [];
    $target = is_array($raw_target) ? $raw_target : [];

    /** @var mixed $raw_type */
    $raw_type = $target['type'] ?? '';
    $target_type = is_string($raw_type) ? $raw_type : '';

    if (!in_array($target_type, ['post', 'term', 'options-page'], strict: true)) {
        return [
            'error' => __('target.type must be one of: post, term, options-page.', domain: 'novamira-pro'),
            'code' => 'invalid_target_type',
        ];
    }

    $resolved = wv_resolve_target($target_type, $target['id'] ?? null);
    if (array_key_exists('error', $resolved)) {
        return $resolved;
    }

    /** @var int|string $object_id */
    $object_id = $resolved['object_id'];

    /** @var mixed $raw_values */
    $raw_values = $input['values'] ?? [];
    $values = is_array($raw_values) ? $raw_values : [];

    // Build name→field-def map for the target.
    $field_map = wv_build_field_map($target_type, $object_id);

    $results = [];

    /** @var mixed $value */
    foreach ($values as $name => $value) {
        $name = (string) $name;
        $results[] = wv_apply_one_value($name, $value, $object_id, $field_map);
    }

    return [
        'target' => ['type' => $target_type, 'id' => $target['id'] ?? null],
        'results' => $results,
    ];
}

// ---------------------------------------------------------------------------
// Private helpers (wv_ prefix = write-values)
// ---------------------------------------------------------------------------

/**
 * Validate and resolve the target to a concrete object_id.
 *
 * @param string $target_type 'post' | 'term' | 'options-page'
 * @param mixed  $raw_id      Raw id from input.
 * @return array<string, mixed> On success: ['object_id' => int|string].
 *                              On failure: ['error' => string, 'code' => string].
 */
function wv_resolve_target(string $target_type, mixed $raw_id): array
{
    if ($target_type === 'post') {
        $post_id = (int) $raw_id;
        /** @var \WP_Post|null $post */
        $post = get_post($post_id);
        if ($post === null) {
            return [
                'error' => sprintf(__('Post %d not found.', domain: 'novamira-pro'), $post_id),
                'code' => 'ase_post_not_found',
            ];
        }

        return ['object_id' => $post_id];
    }

    if ($target_type === 'term') {
        $term_id = (int) $raw_id;
        $term = get_term($term_id);
        if ($term === null || is_wp_error($term)) {
            return [
                'error' => sprintf(__('Term %d not found.', domain: 'novamira-pro'), $term_id),
                'code' => 'ase_term_not_found',
            ];
        }

        // ASE term object_id is "taxonomy_termid", e.g. "genre_42".
        $taxonomy = is_object($term) && property_exists($term, 'taxonomy') ? $term->taxonomy : '';
        return ['object_id' => $taxonomy . '_' . $term_id];
    }

    // options-page
    $slug = is_string($raw_id) ? trim($raw_id) : '';
    if ($slug === '') {
        return [
            'error' => __('options-page id must be a non-empty slug string.', domain: 'novamira-pro'),
            'code' => 'invalid_target_type',
        ];
    }

    if (!wv_options_page_has_field_group($slug)) {
        return [
            'error' => sprintf(
                __('Options page "%s" not found or not referenced by any field group.', domain: 'novamira-pro'),
                $slug,
            ),
            'code' => 'ase_options_page_not_found',
        ];
    }

    return ['object_id' => $slug];
}

/**
 * Check whether at least one field group's location rules list the given
 * options-page slug.
 *
 * @param string $slug Options-page slug to look for.
 * @return bool
 */
function wv_options_page_has_field_group(string $slug): bool
{
    $query = new WP_Query([
        'post_type' => 'asenha_cfgroup',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
    ]);

    /** @var array<mixed> $ids */
    $ids = $query->posts;

    /** @var mixed $id */
    foreach ($ids as $id) {
        if (!is_int($id)) {
            continue;
        }

        $rules = ase_fg_read_rules($id);
        $options_pages = ase_fg_extract_rule_values('options_pages', $rules);

        if (in_array($slug, $options_pages, strict: true)) {
            return true;
        }
    }

    return false;
}

/**
 * Build a name→field-definition map for the target.
 *
 * For term and options-page targets the map is filtered to the SPECIFIC
 * taxonomy / options-page slug — not all groups with that placement type.
 * Otherwise a field belonging to "categories" would be accepted (and
 * reported `status: ok`) when writing to a "tags" term, even though the
 * value would never persist there.
 *
 * @param string     $target_type 'post' | 'term' | 'options-page'
 * @param int|string $object_id   Post ID (int), term string "tax_termid", or options-page slug.
 * @return array<string, mixed>   Field name → field definition array.
 */
function wv_build_field_map(string $target_type, int|string $object_id): array
{
    if ($target_type === 'post' && is_int($object_id)) {
        return \find_cf(['post_id' => $object_id]);
    }

    if ($target_type === 'term' && is_string($object_id)) {
        // object_id format: "<taxonomy>_<term_id>", e.g. "genre_42".
        $sep = strrpos($object_id, needle: '_');
        $taxonomy = $sep !== false ? substr($object_id, offset: 0, length: $sep) : '';
        if ($taxonomy === '') {
            return [];
        }
        return wv_build_field_map_for_term_taxonomy($taxonomy);
    }

    // options-page: object_id is the slug
    if ($target_type === 'options-page' && is_string($object_id)) {
        return wv_build_field_map_for_options_page($object_id);
    }

    return [];
}

/**
 * Field map for groups bound to a specific taxonomy.
 *
 * @return array<string, mixed>
 */
function wv_build_field_map_for_term_taxonomy(string $taxonomy_slug): array
{
    $group_ids = ase_resolve_term_group_ids($taxonomy_slug);
    if ($group_ids === []) {
        return [];
    }
    return \find_cf(['group_id' => $group_ids]);
}

/**
 * Field map for groups bound to a specific options page slug.
 *
 * @return array<string, mixed>
 */
function wv_build_field_map_for_options_page(string $options_page_slug): array
{
    $group_ids = wv_resolve_options_page_group_ids($options_page_slug);
    if ($group_ids === []) {
        return [];
    }
    return \find_cf(['group_id' => $group_ids]);
}

/**
 * Enumerate field group ids whose `options_pages` rule includes the given
 * page slug (or that have no options_pages rule — applies-to-all groups).
 *
 * @return list<int>
 */
function wv_resolve_options_page_group_ids(string $page_slug): array
{
    $query = new WP_Query([
        'post_type' => 'asenha_cfgroup',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
    ]);

    /** @var array<mixed> $ids */
    $ids = $query->posts;
    $group_ids = [];

    /** @var mixed $id */
    foreach ($ids as $id) {
        if (!is_int($id)) {
            continue;
        }

        $rules = ase_fg_read_rules($id);
        /** @var mixed $raw_placement */
        $raw_placement = $rules['placement']['values'] ?? '';
        if ($raw_placement !== 'options-pages') {
            continue;
        }

        // Match the rule against the specific slug. Mirrors the IN/NOT IN
        // / == / != logic used in ase_resolve_term_group_ids.
        /** @var mixed $rule */
        $rule = $rules['options_pages'] ?? null;
        if (!is_array($rule)) {
            // No options_pages restriction → applies to all option pages.
            $group_ids[] = $id;
            continue;
        }
        /** @var mixed $operator */
        $operator = $rule['operator'] ?? '==';
        /** @var mixed $values */
        $values = $rule['values'] ?? [];
        $values_list = is_array($values) ? array_map('strval', $values) : [(string) $values];
        $matches = in_array($page_slug, $values_list, strict: true);
        $negate = in_array($operator, ['!=', 'NOT IN'], strict: true);
        if ($matches && !$negate || !$matches && $negate) {
            $group_ids[] = $id;
        }
    }

    return $group_ids;
}

/**
 * Attempt to write a single field value and return the per-field result entry.
 *
 * @param string         $name      Field name.
 * @param mixed          $value     New value.
 * @param int|string     $object_id Post ID, term string, or options-page slug.
 * @param array<string, mixed> $field_map Name → field definition map.
 * @return array<string, mixed>
 */
function wv_apply_one_value(string $name, mixed $value, int|string $object_id, array $field_map): array
{
    /** @var mixed $field_def */
    $field_def = $field_map[$name] ?? null;

    if ($field_def === null) {
        return [
            'field' => $name,
            'status' => 'error',
            'code' => 'ase_unknown_field',
        ];
    }

    /** @var mixed $raw_field_type */
    $raw_field_type = $field_def['type'] ?? '';
    $field_type = is_string($raw_field_type) ? $raw_field_type : '';

    if (in_array($field_type, ['tab', 'heading', 'line_break'], strict: true)) {
        return [
            'field' => $name,
            'status' => 'error',
            'code' => 'ase_layout_field_write',
            'message' => __('Layout fields cannot accept values.', domain: 'novamira-pro'),
        ];
    }

    // Dispatch to the correct write path based on the object_id type.
    // ASE's update_cf($post_id, $fields_array) only accepts a numeric post ID.
    // For term targets (object_id = "taxonomy_42") and options-page targets
    // (object_id = slug string), the values are stored via update_post_meta
    // on the field group's own storage row (custom table for terms). We use
    // the low-level CFG API for these non-post targets.
    $ok = wv_do_write($name, $value, $object_id);

    if ($ok) {
        return ['field' => $name, 'status' => 'ok'];
    }

    return [
        'field' => $name,
        'status' => 'error',
        'code' => 'ase_write_failed',
        'message' => sprintf(__('Failed to write value for field "%s".', domain: 'novamira-pro'), $name),
    ];
}

/**
 * Perform the actual write for a single field value.
 *
 * - Post targets: use update_cf($post_id, [$field_name => $value]) — the correct
 *   ASE Public API for programmatic field writes on posts.
 * - Term targets: use CFG()->save_for_term() with the correct arguments shape.
 *   save_fields_for_term() requires $term_data['ID'] (not 'term_id'), and
 *   $options['field_groups'] must be a non-empty indexed array of cfgroup post IDs —
 *   without it the method early-returns before the save loop, silently no-oping.
 * - Options-page targets: use CFG()->save() with an options-page context.
 *
 * @param string     $name      Field name.
 * @param mixed      $value     New value.
 * @param int|string $object_id Numeric post ID, term string ("taxonomy_termid"), or options-page slug.
 * @return bool
 */
function wv_do_write(string $name, mixed $value, int|string $object_id): bool
{
    if (is_int($object_id)) {
        // Post target: use ASE's public update_cf($post_id, $fields_assoc_array).
        // The correct signature is update_cf($post_id, $fields_data, $single_new_value).
        // Passing an associative array as $fields_data writes multiple fields at once.
        $result = \update_cf($object_id, [$name => $value]);
        return is_numeric($result) && (int) $result === $object_id;
    }

    // Term or options-page target: update_cf only accepts numeric post IDs, so we
    // use CFG()->save_for_term() / CFG()->save() via the low-level path.
    // The object_id for terms is "taxonomy_termid" (e.g. "category_42"); for options
    // pages it is the slug string.
    if (str_contains($object_id, '_') && preg_match('/^[a-z_]+_\d+$/', $object_id)) {
        // Term target: extract taxonomy slug and term_id from the object_id string.
        $last_underscore = strrpos(haystack: $object_id, needle: '_');
        $term_id = $last_underscore !== false ? (int) substr(string: $object_id, offset: $last_underscore + 1) : 0;
        $taxonomy_slug = $last_underscore !== false
            ? substr(string: $object_id, offset: 0, length: $last_underscore)
            : '';
        if ($term_id <= 0) {
            return false;
        }

        // Resolve field groups for this taxonomy. save_fields_for_term() requires a
        // non-empty $options['field_groups'] — it early-returns before the save loop
        // when this list is empty, causing a silent no-op even when status: ok is
        // returned. We use our own helper instead of
        // CFG()->api->get_matching_groups_for_taxonomy() because that function skips
        // groups with no 'taxonomies' rule (i.e. "apply to all taxonomy terms") —
        // those catch-all groups would never be written to via the ASE API.
        $group_ids = ase_resolve_term_group_ids($taxonomy_slug);
        if ($group_ids === []) {
            return false;
        }

        // Flush the CFG field-group cache so save_fields_for_term() → find_input_fields()
        // sees the current field-group state. The cache is populated lazily on the first
        // call and never invalidated automatically, so stale data from earlier in the same
        // request (e.g. after a field-group create/delete) would cause the field_id_lookup
        // to omit the new group's fields — resulting in a silent save no-op.
        fg_flush_cfgroup_cache();

        // field_data is keyed by field NAME with format='api' (the default).
        // save_fields_recursive() resolves name → numeric ID via field_id_lookup
        // when format != 'input'. $term_data must use key 'ID' (not 'term_id').
        $field_data = [$name => $value];
        $term_data = ['ID' => $term_id];
        \CFG()->save_for_term($field_data, $term_data, ['field_groups' => $group_ids]);
        return true;
    }

    // Options-page target: use CFG()->save() with the numeric asenha_options_page post ID.
    //
    // CFG()->save_fields() casts $post_data['ID'] to int at line
    //   `$post_id = (int) $post_data['ID'];`
    // Passing the slug string here would produce 0, causing CFG to call
    // wp_insert_post() and create a stray "My CFG post" entry instead of writing
    // the value against the actual options-page post. We must resolve the slug to
    // the numeric ID of the matching asenha_options_page CPT post first.
    //
    // The 'type' => 'options_page' option is only used by CFG()->save_fields() for
    // matching field groups via get_matching_groups(). Passing the real numeric
    // asenha_options_page post ID ensures: (a) the DELETE of stale values targets the
    // right post, (b) the new value is stored in wp_postmeta against the correct ID.
    $page_post_id = wv_resolve_options_page_post_id($object_id);
    if ($page_post_id <= 0) {
        // The asenha_options_page CPT post does not exist yet — fall back to the
        // field-name-based lookup that get_cf uses (get_options_pages_cf). Without
        // a concrete post_id CFG cannot write; report failure.
        return false;
    }

    $field_data = [$name => $value];
    $post_data = ['ID' => $page_post_id];

    // Flush the CFG field-group cache so save_fields() → get_matching_groups()
    // returns the current field group list including groups created in this request.
    fg_flush_cfgroup_cache();

    \CFG()->save($field_data, $post_data, []);
    return true;
}

/**
 * Resolve an options-page slug to the numeric ID of its asenha_options_page CPT post.
 *
 * ASE's options-pages module creates one `asenha_options_page` post per screen;
 * the `_cfgroup_options_screen_name` postmeta stores the slug that field groups
 * reference in their `options_pages` location rule. CFG()->save_fields() needs
 * a numeric post ID — the slug string would be cast to 0.
 *
 * @param string $slug The options-page slug as declared in the field group's location rules.
 * @return int         The numeric post ID, or 0 when not found.
 */
function wv_resolve_options_page_post_id(string $slug): int
{
    if ($slug === '') {
        return 0;
    }

    // Primary lookup: by post_name (set when the asenha_options_page post was created).
    $query = new WP_Query([
        'post_type' => 'asenha_options_page',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'no_found_rows' => true,
        'name' => $slug,
    ]);

    $posts = $query->posts;
    $first = $posts[0] ?? null;
    if ($first instanceof \WP_Post && $first->ID > 0) {
        return (int) $first->ID;
    }

    // Fallback: scan postmeta for _cfgroup_options_screen_name = $slug.
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return 0;
    }
    /** @var string $sql */
    $sql = $wpdb->prepare(
        'SELECT post_id FROM %i WHERE meta_key = %s AND meta_value = %s LIMIT 1',
        $wpdb->postmeta,
        '_cfgroup_options_screen_name',
        $slug,
    );
    $post_id_str = $wpdb->get_var($sql);

    return $post_id_str !== null ? (int) $post_id_str : 0;
}
