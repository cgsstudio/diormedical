<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-create-product-attribute', [
    'label' => __('Create Product Attribute', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new global WooCommerce product attribute (e.g. Size, Color). '
        . 'Required: `name` (display label). Optional: `slug` (auto-generated from name; accepts "size" or "pa_size"; the pa_ prefix is stripped automatically and stored as the short form), '
        . '`type` (select or text; default select), `order_by` (menu_order|name|name_num|id; default menu_order), `has_archives` (bool; default false). '
        . 'Returns the full attribute object including id, slug, taxonomy_slug (e.g. "pa_size"), term_count, has_products. '
        . 'Use taxonomy_slug when passing the attribute to create-product or create-product-variation. '
        . 'Non-ASCII attribute names produce raw-UTF-8 attribute slugs; terms within those attributes are stored with percent-encoded slugs. Both forms work as input. '
        . 'Possible errors: wc_inactive, wc_invalid_input (name empty/too long), wc_invalid_attribute_type, wc_invalid_attribute_order_by, wc_term_exists (slug already registered as attribute), wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Display label (required), e.g. "Size".'],
            'slug' => [
                'type' => ['string', 'null'],
                'description' => 'URL slug. Auto-generated from name if omitted. Accepts "size" or "pa_size"; pa_ prefix is stripped automatically.',
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Attribute type. Allowed: select, text. Default: select.',
            ],
            'order_by' => [
                'type' => 'string',
                'description' => 'Term sort order. Allowed: menu_order, name, name_num, id. Default: menu_order.',
            ],
            'has_archives' => [
                'type' => 'boolean',
                'description' => 'Whether the attribute has archive pages. Default false.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean', 'description' => 'True when the attribute was created successfully.'],
            'attribute' => [
                'type' => 'object',
                'description' => 'The newly created attribute object.',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Attribute id.'],
                    'name' => ['type' => 'string', 'description' => 'Display label, e.g. "Size".'],
                    'slug' => ['type' => 'string', 'description' => 'Short slug without pa_ prefix, e.g. "size".'],
                    'taxonomy_slug' => [
                        'type' => 'string',
                        'description' => 'Full taxonomy name with pa_ prefix, e.g. "pa_size". Use this when assigning the attribute to products.',
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
        ],
        'required' => ['success', 'attribute'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_create_product_attribute',
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
function woocommerce_create_product_attribute(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $name_err = wc_require_term_name($input);
    if ($name_err !== null) {
        return $name_err;
    }

    $field_args = wc_validate_attribute_fields($input);
    if ($field_args instanceof WP_Error) {
        return $field_args;
    }

    $name = wc_strip_unsafe_chars(trim((string) $input['name']));

    /** @var mixed $ha_raw */
    $ha_raw = $field_args['has_archives'] ?? false;
    $has_archives_val = is_bool($ha_raw) ? $ha_raw : $ha_raw === 1;

    $args = [
        'name' => $name,
        'type' => (string) ($field_args['type'] ?? 'select'),
        'order_by' => (string) ($field_args['order_by'] ?? 'menu_order'),
        'has_archives' => $has_archives_val,
    ];

    if (isset($input['slug']) && $input['slug'] !== null && $input['slug'] !== '') {
        $args['slug'] = wc_strip_pa_prefix(wc_strip_unsafe_chars(trim((string) $input['slug'])));
    }

    try {
        $result = \wc_create_attribute($args);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Attribute creation failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        return wc_map_attribute_create_error($result);
    }

    $attr_id = (int) $result;
    $attr_shape = wc_attribute_to_array($attr_id);
    if ($attr_shape instanceof WP_Error) {
        return $attr_shape;
    }

    // Register the taxonomy in this PHP process immediately so that taxonomy_exists()
    // returns true for subsequent calls in the same wp-cli / MCP request (e.g. creating terms).
    wc_ensure_attribute_taxonomy_registered((string) ($attr_shape['taxonomy_slug'] ?? ''));

    return ['success' => true, 'attribute' => $attr_shape];
}

/**
 * Map a WP_Error from wc_create_attribute to a clean ability error.
 */
function wc_map_attribute_create_error(\WP_Error $result): \WP_Error
{
    $code = (string) $result->get_error_code();
    if ($code === 'invalid_product_attribute_slug_too_long') {
        return wc_error('wc_invalid_input', $result->get_error_message());
    }
    if ($code === 'invalid_product_attribute_slug_already_exists') {
        return wc_error('wc_term_exists', $result->get_error_message());
    }
    if (str_contains($code, 'invalid') || str_contains($code, 'empty') || str_contains($code, 'missing')) {
        return wc_error('wc_invalid_input', $result->get_error_message());
    }
    return wc_error('wc_internal_error', $result->get_error_message());
}
