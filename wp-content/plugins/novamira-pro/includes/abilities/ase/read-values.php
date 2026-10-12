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
 * Ability: Read CFT field values for a post / term / options-page target.
 */
wp_register_ability('novamira/ase-read-values', [
    'label' => __('Read ASE Field Values', domain: 'novamira-pro'),
    'description' => __(
        'Reads ASE Custom Field Types (CFT) values for a target: post (by post ID), term (by term ID), or options-page (by slug). Pass fields: [name, ...] to restrict to specific fields; omit to return all declared fields for the target. format controls the return format: api (default, ASE-processed), input (form-ready), raw (database string). only_with_values defaults to true — drops empty/null/[] entries to avoid flooding context with unfilled fields.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => [
                'type' => 'object',
                'description' => 'The object to read field values from.',
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
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional. Restrict to these field names. When omitted, returns all fields declared for the target.',
            ],
            'format' => [
                'type' => 'string',
                'enum' => ['api', 'input', 'raw'],
                'description' => 'Value format: api (default, ASE-processed), input (form-ready), raw (database string).',
                'default' => 'api',
            ],
            'only_with_values' => [
                'type' => 'boolean',
                'description' => 'When true (default), omit fields with null, empty string, or empty array values.',
                'default' => true,
            ],
        ],
        'required' => ['target'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => ['type' => 'object'],
            'values' => [
                'type' => 'object',
                'description' => 'Field name to value map.',
            ],
            'format' => ['type' => 'string'],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Non-fatal issues (unknown or unreadable fields). Only present when non-empty.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_read_values',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Defaults to only_with_values: true — only filled fields are returned. Pass only_with_values: false to see every declared field including unset ones. Layout fields (tab/heading/line_break) are silently skipped — they have no stored value.',
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
function ase_read_values(array $input): array
{
    $guard =
        ase_runtime_guard() ?? ase_pro_guard() ?? ase_module_guard('custom_content_types', __(
            'Enable the "Custom Field Types" module in ASE settings.',
            domain: 'novamira-pro',
        ));
    if ($guard !== null) {
        return ['error' => $guard->get_error_message(), 'code' => $guard->get_error_code()];
    }

    // ASE's cfgroup_functions.php (find_cf / get_cf) is only loaded in admin web
    // context. Ensure the full CFT stack is initialised for CLI / REST / cron.
    ase_ensure_cfgroup_functions_loaded();

    $parsed = rv_parse_input($input);
    if (array_key_exists('error', $parsed)) {
        return $parsed;
    }

    /** @var string $target_type */
    $target_type = $parsed['target_type'];
    /** @var int|string $object_id */
    $object_id = $parsed['object_id'];
    /** @var list<string>|null $requested_fields */
    $requested_fields = $parsed['requested_fields'];
    /** @var string $format */
    $format = $parsed['format'];
    /** @var bool $only_with_values */
    $only_with_values = $parsed['only_with_values'];
    /** @var mixed $raw_id */
    $raw_id = $parsed['raw_id'];

    $field_map = rv_build_field_map($target_type, $object_id);
    $names_to_fetch = $requested_fields ?? array_keys($field_map);

    [$values, $warnings] = rv_collect_values($names_to_fetch, $field_map, $requested_fields, $format, $object_id);

    if ($only_with_values) {
        $values = rv_filter_empty_values($values);
    }

    $result = [
        'target' => ['type' => $target_type, 'id' => $raw_id],
        'values' => $values,
        'format' => $format,
    ];

    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }

    return $result;
}

/**
 * Parse and validate all inputs, resolve the target object_id.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed> On success: target_type, object_id, requested_fields, format, only_with_values, raw_id.
 *                              On failure: error + code keys.
 */
function rv_parse_input(array $input): array
{
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

    /** @var mixed $raw_id */
    $raw_id = $target['id'] ?? null;
    $resolved = rv_resolve_target($target_type, $raw_id);
    if (array_key_exists('error', $resolved)) {
        return $resolved;
    }

    /** @var mixed $raw_fields */
    $raw_fields = $input['fields'] ?? null;
    $requested_fields = is_array($raw_fields) ? array_values(array_filter(array_map('strval', $raw_fields))) : null;

    /** @var mixed $raw_format */
    $raw_format = $input['format'] ?? 'api';
    $format = is_string($raw_format) && in_array($raw_format, ['api', 'input', 'raw'], strict: true)
        ? $raw_format
        : 'api';

    /** @var mixed $raw_only */
    $raw_only = $input['only_with_values'] ?? true;

    return [
        'target_type' => $target_type,
        'object_id' => $resolved['object_id'],
        'requested_fields' => $requested_fields,
        'format' => $format,
        'only_with_values' => $raw_only !== false,
        'raw_id' => $raw_id,
    ];
}

/**
 * Iterate over field names, read values via get_cf(), and accumulate warnings.
 *
 * @param list<string>         $names_to_fetch  Field names to read.
 * @param array<string, mixed> $field_map       Name → field definition.
 * @param list<string>|null    $requested_fields Non-null when the caller provided an explicit list.
 * @param string               $format          'api' | 'input' | 'raw'.
 * @param int|string           $object_id       Post ID, term string, or options-page slug.
 * @return array{0: array<string, mixed>, 1: list<string>}  [values, warnings]
 */
function rv_collect_values(
    array $names_to_fetch,
    array $field_map,
    ?array $requested_fields,
    string $format,
    int|string $object_id,
): array {
    $values = [];
    $warnings = [];

    foreach ($names_to_fetch as $name) {
        /** @var mixed $field_def */
        $field_def = $field_map[$name] ?? null;

        if ($field_def === null) {
            if ($requested_fields !== null) {
                $warnings[] = sprintf('Field "%s" not found in any field group for this target.', $name);
            }
            continue;
        }

        /** @var mixed $raw_type_def */
        $raw_type_def = $field_def['type'] ?? '';
        $field_type = is_string($raw_type_def) ? $raw_type_def : '';

        if (in_array($field_type, ['tab', 'heading', 'line_break'], strict: true)) {
            continue;
        }

        $values[$name] = rv_get_cf_value($name, $format, $object_id);
    }

    return [$values, $warnings];
}

// ---------------------------------------------------------------------------
// Private helpers (rv_ prefix = read-values)
// ---------------------------------------------------------------------------

/**
 * Validate and resolve the target to a concrete object_id.
 *
 * @param string $target_type 'post' | 'term' | 'options-page'
 * @param mixed  $raw_id      Raw id from input.
 * @return array<string, mixed> On success: ['object_id' => int|string].
 *                              On failure: ['error' => string, 'code' => string].
 */
function rv_resolve_target(string $target_type, mixed $raw_id): array
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

    if (!rv_options_page_has_field_group($slug)) {
        return [
            'error' => sprintf(
                __('Options page "%s" not found or not referenced by any field group.', domain: 'novamira-pro'),
                $slug,
            ),
            'code' => 'ase_options_page_not_found',
        ];
    }

    // Resolve the slug to the numeric asenha_options_page post ID so that
    // rv_get_cf_value() can call get_cf($name, $format, $numeric_id). Passing
    // the slug string to get_cf() relies on get_options_pages_cf() → find_fields()
    // → get_matching_groups() correctly wiring the slug to the post ID — that
    // chain works when ASE's options-pages module has initialised its internal
    // $screens[] array, but in CLI/REST contexts that init may not have run,
    // causing get_cf() to return null for every field. Using the numeric post ID
    // directly bypasses the fragile field-name-based lookup entirely.
    $op_post_id = rv_resolve_options_page_post_id($slug);
    if ($op_post_id > 0) {
        return ['object_id' => $op_post_id];
    }

    // No asenha_options_page CPT post exists for this slug — fall back to the
    // slug string so get_cf() can attempt the field-name lookup path.
    return ['object_id' => $slug];
}

/**
 * Check whether at least one field group's location rules list the given
 * options-page slug.
 *
 * @param string $slug Options-page slug to look for.
 * @return bool
 */
function rv_options_page_has_field_group(string $slug): bool
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
 * Resolve an options-page slug to the numeric ID of its asenha_options_page CPT post.
 *
 * Mirrors wv_resolve_options_page_post_id() in write-values.php. Kept separate
 * to avoid cross-file function dependencies between the two ability files.
 *
 * @param string $slug The options-page slug as declared in the field group's location rules.
 * @return int         The numeric post ID, or 0 when not found.
 */
function rv_resolve_options_page_post_id(string $slug): int
{
    if ($slug === '') {
        return 0;
    }

    // Primary lookup: by post_name.
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

/**
 * Build a name→field-definition map for the target.
 *
 * For post targets, delegate to find_cf() with post_id — it filters field
 * groups by location rules matching the post. For term and options-page
 * targets, fall back to manual WP_Query + location rules because find_cf()
 * doesn't accept term_id or options-page slug.
 *
 * @param string     $target_type 'post' | 'term' | 'options-page'
 * @param int|string $object_id   Post ID (int), term string "tax_id", or options-page slug.
 * @return array<string, mixed>   Field name → field definition array.
 */
function rv_build_field_map(string $target_type, int|string $object_id): array
{
    if ($target_type === 'post' && is_int($object_id)) {
        // find_cf() with post_id uses get_matching_groups internally.
        return \find_cf(['post_id' => $object_id]);
    }

    if ($target_type === 'term') {
        // Flush the CFG field-group cache so find_cf() sees the current state of
        // field groups rather than a memoized snapshot from earlier in the same
        // PHP process (e.g. after a write that may have created or modified groups).
        fg_flush_cfgroup_cache();
        return rv_build_field_map_for_placement('taxonomy-terms');
    }

    // options-page
    return rv_build_field_map_for_placement('options-pages');
}

/**
 * Build a field map from all field groups with a given placement.
 *
 * @param string $placement 'taxonomy-terms' | 'options-pages'
 * @return array<string, mixed>
 */
function rv_build_field_map_for_placement(string $placement): array
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
        if ($raw_placement === $placement) {
            $group_ids[] = $id;
        }
    }

    if ($group_ids === []) {
        return [];
    }

    return \find_cf(['group_id' => $group_ids]);
}

/**
 * Read a single CFT field value.
 *
 * For post and options-page targets delegates to get_cf() — ASE's public
 * helper. For term targets, ASE's get_cf() → get_fields() internally calls
 * get_matching_groups_for_taxonomy() which silently skips any field group
 * that has a catch-all placement rule (no explicit taxonomy restriction).
 * We bypass that broken path for term targets by using rv_get_term_cf_value()
 * which resolves group IDs correctly and seeds the CFG cache directly.
 *
 * The ability accepts format values `api` (default, processed) / `input`
 * (form-ready) / `raw` (database string). ASE's get_cf() accepts a different
 * set: `default` (maps to our `api`), `display` (rendered HTML), `raw`.
 * Map accordingly; `input` has no exact ASE equivalent — use `raw`.
 *
 * @param string     $name      Field name.
 * @param string     $format    'api' | 'input' | 'raw'.
 * @param int|string $object_id Post ID, "taxonomy_termid" string, or options-page slug.
 * @return mixed
 */
function rv_get_cf_value(string $name, string $format, int|string $object_id): mixed
{
    $ase_format = match ($format) {
        'api' => 'default',
        'input' => 'raw',
        'raw' => 'raw',
        default => 'default',
    };

    // Term targets need a dedicated path — ASE's get_cf() / get_fields() calls
    // get_matching_groups_for_taxonomy() which skips catch-all groups (no explicit
    // taxonomies rule), causing every term read for such groups to return null.
    if (is_string($object_id) && str_contains($object_id, '_')) {
        return rv_get_term_cf_value($name, $ase_format, $object_id);
    }

    return \get_cf($name, $ase_format, $object_id);
}

/**
 * Read a single CFT field value for a term target, bypassing the broken
 * get_matching_groups_for_taxonomy() path in ASE's internal cache layer.
 *
 * ASE's cfgroup_api::get_fields() for term objects calls
 * get_matching_groups_for_taxonomy($taxonomy) to discover which field groups
 * apply. That function skips groups whose 'taxonomies' rule is absent or has
 * an empty exclude-list — even though both mean "applies to all taxonomies".
 * The result: every read through get_cf() for such groups returns null.
 *
 * This helper works around the bug by:
 * 1. Using ase_resolve_term_group_ids() to find the correct group IDs.
 * 2. Reading stored field values directly from wp_termmeta (where ASE writes
 *    them via save_fields_for_term).
 * 3. Pre-seeding CFG()->api->cache_for_term[$term_id][$format] with those
 *    values so that a subsequent CFG()->get() call returns from cache without
 *    going through get_matching_groups_for_taxonomy() again.
 *
 * @param string $name      Field name.
 * @param string $ase_format 'default' | 'raw' (ASE-native format names).
 * @param string $object_id  Term object ID string, e.g. "category_42".
 * @return mixed
 */
function rv_get_term_cf_value(string $name, string $ase_format, string $object_id): mixed
{
    // Extract taxonomy + term_id from the "taxonomy_id" compound key.
    $last_underscore = strrpos(haystack: $object_id, needle: '_');
    if ($last_underscore === false || $last_underscore === 0) {
        return null;
    }
    $term_id = (int) substr(string: $object_id, offset: $last_underscore + 1);
    $taxonomy = substr(string: $object_id, offset: 0, length: $last_underscore);
    if ($term_id <= 0 || $taxonomy === '') {
        return null;
    }

    // Resolve which field groups apply to this taxonomy using our own helper
    // that handles catch-all groups correctly.
    $group_ids = ase_resolve_term_group_ids($taxonomy);
    if ($group_ids === []) {
        return null;
    }

    // Get the field definitions for all matching groups.
    $field_map = \find_cf(['group_id' => $group_ids]);
    if (!array_key_exists($name, $field_map)) {
        return null;
    }

    // Pre-seed CFG()'s per-term cache with the actual stored values so that
    // CFG()->get() returns from cache without calling get_matching_groups_for_taxonomy.
    // ASE writes field values to wp_termmeta keyed by field name; reading back via
    // get_term_meta() gives us the stored value in the same form that get_fields()
    // would return for 'raw' format. For 'default' (api) format, CFG()->fields[]
    // processes the raw value further via format_value_for_api() — for simple field
    // types (text, textarea, number, etc.) this is a no-op or simple cast.
    //
    // We seed only the keys we know from $field_map so the cache entry is valid
    // for any field in this group, not just $name.
    $cache_entry = [];
    foreach (array_keys($field_map) as $field_name) {
        /** @var mixed $raw */
        $raw = get_term_meta($term_id, $field_name, single: true);
        $cache_entry[$field_name] = $raw !== '' ? $raw : null;
    }

    // Seed both 'raw' and 'default' slots since a caller may request either.
    /** @var array<int, array<string, array<string, mixed>>> $term_cache */
    $term_cache = \CFG()->api->cache_for_term;
    $term_cache[$term_id]['raw'] = $cache_entry;
    $term_cache[$term_id]['default'] = $cache_entry;
    \CFG()->api->cache_for_term = $term_cache;

    // Now delegate to CFG()->get() which will serve directly from the cache.
    return \CFG()->get($name, $object_id, ['format' => $ase_format]);
}

/**
 * Drop values that are considered empty for the purposes of only_with_values.
 *
 * Drops null, '', and []. Keeps false (boolean false can be a valid stored
 * value for true_false fields) and 0.
 *
 * @param array<string, mixed> $values
 * @return array<string, mixed>
 */
function rv_filter_empty_values(array $values): array
{
    $filtered = [];

    /** @var mixed $value */
    foreach ($values as $name => $value) {
        if ($value === null || $value === '' || $value === []) {
            continue;
        }

        $filtered[$name] = $value;
    }

    return $filtered;
}
