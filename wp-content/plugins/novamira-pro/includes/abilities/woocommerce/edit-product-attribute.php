<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-edit-product-attribute', [
    'label' => __('Edit Product Attribute', domain: 'novamira-pro'),
    'description' => __(
        'Partially updates an existing global product attribute (PATCH semantics). Required: `id`. '
        . 'Optional: `name`, `slug` (pa_ prefix auto-stripped), `type` (select|text), `order_by` (menu_order|name|name_num|id), `has_archives` (bool), `force` (bool). '
        . 'Only fields you pass change; omitted fields stay as-is. '
        . 'Slug edits trigger taxonomy re-registration; use sparingly. '
        . 'When changing `slug`: if any variation postmeta key still references the old attribute slug with a stale term slug (e.g. after a prior term-slug rename), the call refuses with wc_invalid_input listing offending variation IDs unless `force: true` is passed. With `force: true` the response includes `variations_orphaned` (stale rows found) and `variations_migrated` (rows WC fixed). '
        . 'Returns the full updated attribute object. '
        . 'Possible errors: wc_inactive, wc_attribute_not_found, wc_invalid_input, wc_invalid_attribute_type, wc_invalid_attribute_order_by, wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Attribute id (required).'],
            'name' => ['type' => 'string', 'description' => 'New display label.'],
            'slug' => [
                'type' => ['string', 'null'],
                'description' => 'New URL slug. Accepts "size" or "pa_size"; pa_ prefix stripped automatically.',
            ],
            'type' => ['type' => 'string', 'description' => 'Attribute type. Allowed: select, text.'],
            'order_by' => [
                'type' => 'string',
                'description' => 'Term sort order. Allowed: menu_order, name, name_num, id.',
            ],
            'has_archives' => ['type' => 'boolean', 'description' => 'Whether the attribute has archive pages.'],
            'force' => [
                'type' => 'boolean',
                'description' => 'When changing slug, proceed even if stale variation postmeta rows exist. Response will include variations_orphaned count. Default false.',
                'default' => false,
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean', 'description' => 'True when the attribute was updated successfully.'],
            'attribute' => [
                'type' => 'object',
                'description' => 'The updated attribute object.',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Attribute id.'],
                    'name' => ['type' => 'string', 'description' => 'Display label, e.g. "Size".'],
                    'slug' => ['type' => 'string', 'description' => 'Short slug without pa_ prefix, e.g. "size".'],
                    'taxonomy_slug' => [
                        'type' => 'string',
                        'description' => 'Full taxonomy name with pa_ prefix, e.g. "pa_size".',
                    ],
                    'type' => ['type' => 'string', 'description' => 'Attribute type: select or text.'],
                    'order_by' => [
                        'type' => 'string',
                        'description' => 'Term sort order: menu_order, name, name_num, or id.',
                    ],
                    'has_archives' => [
                        'type' => 'boolean',
                        'description' => 'Whether the attribute has archive pages.',
                    ],
                    'term_count' => [
                        'type' => 'integer',
                        'description' => 'Number of terms registered under this attribute.',
                    ],
                    'has_products' => [
                        'type' => 'boolean',
                        'description' => 'True if at least one product uses this attribute as a variation axis.',
                    ],
                ],
                'required' => [
                    'id',
                    'name',
                    'slug',
                    'taxonomy_slug',
                    'type',
                    'order_by',
                    'has_archives',
                    'term_count',
                    'has_products',
                ],
            ],
            'variations_orphaned' => [
                'type' => 'integer',
                'description' => 'Number of variation postmeta rows with stale term slugs found before the rename (present only on slug change with force:true).',
            ],
            'variations_migrated' => [
                'type' => 'integer',
                'description' => 'Number of variation postmeta rows migrated by WC core during the rename (present only on slug change with force:true).',
            ],
        ],
        'required' => ['success', 'attribute'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_edit_product_attribute',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_edit_product_attribute(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $attr_id = (int) $input['id'];
    $attr = \wc_get_attribute($attr_id);
    if (!is_object($attr)) {
        return wc_error('wc_attribute_not_found', sprintf(
            /* translators: attribute id */
            __('Attribute with id %d not found.', domain: 'novamira-pro'),
            $attr_id,
        ));
    }

    $field_args = wc_validate_attribute_fields($input);
    if ($field_args instanceof WP_Error) {
        return $field_args;
    }

    $update_args = wc_build_attribute_update_args($attr, $input, $field_args);
    if ($update_args instanceof WP_Error) {
        return $update_args;
    }

    return wc_run_edit_attribute_with_slug_guard($attr, $attr_id, $input, $update_args);
}

/**
 * Execute the attribute update with the slug-change guard and response assembly.
 *
 * Separated to keep woocommerce_edit_product_attribute within cyclomatic-complexity budget.
 *
 * @param array<string, mixed> $input
 * @param array<string, mixed> $update_args
 * @return array<string, mixed>|WP_Error
 */
function wc_run_edit_attribute_with_slug_guard(
    object $attr,
    int $attr_id,
    array $input,
    array $update_args,
): array|WP_Error {
    $is_slug_change = array_key_exists('slug', $input) && $input['slug'] !== null && $input['slug'] !== '';
    $force = isset($input['force']) && ($input['force'] === true || $input['force'] === 1);

    // Slug-change guard: scan for stale variation postmeta rows before rename.
    $old_tax = $is_slug_change ? wc_attr_object_to_taxonomy_slug($attr) : '';
    $stale_ids = $is_slug_change ? wc_find_stale_variation_rows($old_tax) : [];
    if (!$force) {
        $refuse = wc_stale_slug_rename_error($stale_ids);
        if ($refuse !== null) {
            return $refuse;
        }
    }

    $result = wc_run_attribute_update($attr_id, $update_args);
    if ($result instanceof WP_Error) {
        return $result;
    }

    $attr_shape = wc_attribute_to_array($attr_id);
    if ($attr_shape instanceof WP_Error) {
        return $attr_shape;
    }

    // Register the new taxonomy immediately so taxonomy_exists() returns true in the same request.
    if ($is_slug_change) {
        wc_ensure_attribute_taxonomy_registered((string) ($attr_shape['taxonomy_slug'] ?? ''));
    }

    $response = ['success' => true, 'attribute' => $attr_shape];
    if ($is_slug_change && $force) {
        $response = wc_append_slug_change_migration_counts($response, $attr_shape, $stale_ids);
    }

    return $response;
}

/**
 * Run wc_update_attribute and map errors.
 *
 * @param array<string, mixed> $update_args
 */
function wc_run_attribute_update(int $attr_id, array $update_args): bool|WP_Error
{
    try {
        $result = \wc_update_attribute($attr_id, $update_args);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Attribute update failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        $code = (string) $result->get_error_code();
        if (str_contains($code, 'invalid') || str_contains($code, 'empty') || str_contains($code, 'missing')) {
            return wc_error('wc_invalid_input', $result->get_error_message());
        }
        return wc_error('wc_internal_error', $result->get_error_message());
    }

    return true;
}

/**
 * Build a wc_invalid_input WP_Error for stale variation postmeta rows blocking a slug rename.
 * Returns null when $stale_var_ids is empty (no stale rows found).
 *
 * @param list<int> $stale_var_ids
 */
function wc_stale_slug_rename_error(array $stale_var_ids): ?WP_Error
{
    if ($stale_var_ids === []) {
        return null;
    }
    return new WP_Error(
        'wc_invalid_input',
        sprintf(
            /* translators: 1: variation count, 2: comma-separated variation IDs */
            __(
                'Cannot rename attribute slug: %1$d variation(s) have stale postmeta keys from a prior term-slug rename. Pass `force: true` to proceed. Offending variation IDs: %2$s.',
                domain: 'novamira-pro',
            ),
            count($stale_var_ids),
            implode(', ', $stale_var_ids),
        ),
        ['status' => 400, 'variation_ids' => $stale_var_ids],
    );
}

/**
 * Append variations_orphaned and variations_migrated to the response when a force slug-change occurred.
 *
 * @param array<string, mixed> $response
 * @param array<string, mixed> $attr_shape
 * @param list<int>            $stale_var_ids Stale variation IDs found before rename.
 * @return array<string, mixed>
 */
function wc_append_slug_change_migration_counts(array $response, array $attr_shape, array $stale_var_ids): array
{
    $new_tax = (string) ($attr_shape['taxonomy_slug'] ?? '');
    $stale_after = $new_tax !== '' ? wc_find_stale_variation_rows($new_tax) : [];
    $response['variations_orphaned'] = count($stale_var_ids);
    $response['variations_migrated'] = max(0, count($stale_var_ids) - count($stale_after));
    return $response;
}

/**
 * Build the attribute update args array.
 *
 * @param object                $attr       Current attribute object.
 * @param array<string, mixed>  $input      Ability input.
 * @param array<string, mixed>  $field_args Validated field args from wc_validate_attribute_fields.
 * @return array<string, mixed>|WP_Error
 */
function wc_build_attribute_update_args(object $attr, array $input, array $field_args): array|WP_Error
{
    $current_archives = wc_attribute_has_archives($attr);

    // wc_get_attribute() returns the REST-shaped object {name, slug (full "pa_xxx"), type, order_by, has_archives}.
    // Extract current values first so the if-chain below only touches changed fields.
    $current = wc_attr_read_current_fields($attr);
    $update_args = [
        'name' => $current['name'],
        'slug' => wc_strip_pa_prefix($current['taxonomy_slug']),
        'type' => $current['type'],
        'order_by' => $current['order_by'],
        'has_archives' => $current_archives,
    ];

    if (array_key_exists('name', $input) && $input['name'] !== null) {
        $new_name = wc_strip_unsafe_chars(trim((string) $input['name']));
        if ($new_name === '') {
            return wc_error('wc_invalid_input', __('`name` cannot be empty.', domain: 'novamira-pro'));
        }
        $update_args['name'] = $new_name;
    }

    if (array_key_exists('slug', $input) && $input['slug'] !== null && $input['slug'] !== '') {
        $update_args['slug'] = wc_strip_pa_prefix(wc_strip_unsafe_chars(trim((string) $input['slug'])));
    }

    if (isset($field_args['type'])) {
        $update_args['type'] = $field_args['type'];
    }
    if (isset($field_args['order_by'])) {
        $update_args['order_by'] = $field_args['order_by'];
    }
    if (isset($field_args['has_archives'])) {
        /** @var mixed $ha */
        $ha = $field_args['has_archives'];
        $update_args['has_archives'] = is_bool($ha) ? $ha : $ha === 1;
    }

    return $update_args;
}

/**
 * Read the current fields from a wc_get_attribute() REST-shaped object.
 * Accepts both REST shape (name, slug, type, order_by) and DB shape (attribute_name, etc.)
 * so this function works correctly even if the caller source changes.
 *
 * @return array{name: string, taxonomy_slug: string, type: string, order_by: string}
 */
function wc_attr_read_current_fields(object $attr): array
{
    return [
        'name' => (string) ($attr->name ?? $attr->attribute_label ?? ''),
        'taxonomy_slug' => (string) ($attr->slug ?? $attr->attribute_name ?? ''),
        'type' => (string) ($attr->type ?? $attr->attribute_type ?? 'select'),
        'order_by' => (string) ($attr->order_by ?? $attr->attribute_orderby ?? 'menu_order'),
    ];
}

/**
 * Find variation post IDs that have a postmeta key matching the given attribute taxonomy slug
 * but whose stored term-slug value does NOT correspond to any live term in that taxonomy.
 *
 * This detects the cascade-orphan scenario: an agent renamed a term slug first (WC migrates
 * the postmeta value for that term), then tries to rename the attribute slug. WC's key-migration
 * scan during the attribute rename will miss any already-stale rows from the prior rename.
 *
 * @return list<int> List of orphaned variation post IDs.
 */
function wc_find_stale_variation_rows(string $taxonomy_slug): array
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return [];
    }
    $meta_key = 'attribute_' . $taxonomy_slug;
    $pm_table = $wpdb->postmeta;
    $p_table = $wpdb->posts;
    /** @var literal-string $sql */
    $sql = "SELECT pm.post_id, pm.meta_value
            FROM {$pm_table} pm
            INNER JOIN {$p_table} p ON pm.post_id = p.ID
            WHERE pm.meta_key = %s
              AND p.post_type = 'product_variation'
              AND p.post_status != 'trash'";
    /** @var list<object{post_id: string, meta_value: string}>|null $rows */
    $rows = $wpdb->get_results((string) $wpdb->prepare($sql, $meta_key));
    if (!is_array($rows) || $rows === []) {
        return [];
    }

    // Collect all live term slugs for this taxonomy once.
    /** @var list<string>|WP_Error $live_terms */
    $live_terms = get_terms(['taxonomy' => $taxonomy_slug, 'hide_empty' => false, 'fields' => 'slugs']);
    $slug_list = is_array($live_terms) ? $live_terms : [];
    /** @var array<string, int> $live_slugs */
    $live_slugs = array_flip($slug_list);

    $stale = [];
    foreach ($rows as $row) {
        $stored_val = $row->meta_value;
        // Empty string means "any value"; not stale.
        if ($stored_val === '') {
            continue;
        }
        if (!array_key_exists($stored_val, $live_slugs)) {
            $stale[] = (int) $row->post_id;
        }
    }
    return array_values(array_unique($stale));
}
