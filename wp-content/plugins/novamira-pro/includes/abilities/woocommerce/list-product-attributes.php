<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-list-product-attributes', [
    'label' => __('List Product Attributes', domain: 'novamira-pro'),
    'description' => __(
        'Returns all global product attributes (the ones defined under Products → Attributes in the admin). Each item carries the attribute id, name, taxonomy slug (with `pa_` prefix as stored), display type, sort order, and the available terms with id/slug/name. Per-product attributes that are NOT registered as global attributes are not enumerated here; they appear inside `get-product` instead.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [],
        'additionalProperties' => false,
        'default' => [],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'attributes' => [
                'type' => 'array',
                'description' => 'List of all registered global product attributes.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer', 'description' => 'Attribute id.'],
                        'name' => ['type' => 'string', 'description' => 'Display label, e.g. "Size".'],
                        'slug' => ['type' => 'string', 'description' => 'Short slug without pa_ prefix, e.g. "size".'],
                        'taxonomy' => [
                            'type' => 'string',
                            'description' => 'Full taxonomy name with pa_ prefix, e.g. "pa_size".',
                        ],
                        'type' => ['type' => 'string', 'description' => 'Attribute type: select or text.'],
                        'order_by' => [
                            'type' => 'string',
                            'description' => 'Term sort order: menu_order, name, name_num, or id.',
                        ],
                        'terms' => [
                            'type' => 'array',
                            'description' => 'Terms (options) registered under this attribute.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'integer', 'description' => 'Term id.'],
                                    'slug' => ['type' => 'string', 'description' => 'URL slug.'],
                                    'name' => ['type' => 'string', 'description' => 'Term display label.'],
                                ],
                                'required' => ['id', 'slug', 'name'],
                            ],
                        ],
                    ],
                    'required' => ['id', 'name', 'slug', 'taxonomy', 'type', 'order_by', 'terms'],
                ],
            ],
            'total' => ['type' => 'integer', 'description' => 'Total number of global product attributes.'],
        ],
        'required' => ['attributes', 'total'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_list_product_attributes',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to discover global attributes before creating variations in later sprints. The `taxonomy` field already includes the `pa_` prefix.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_list_product_attributes(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }
    /** @var array<array-key, object> $raw */
    $raw = \wc_get_attribute_taxonomies();
    $attributes = [];
    foreach ($raw as $row) {
        $name = (string) ($row->attribute_label ?? '');
        $slug = (string) ($row->attribute_name ?? '');
        $taxonomy = 'pa_' . $slug;
        /** @var list<\WP_Term>|WP_Error $terms_raw */
        $terms_raw = get_terms(['taxonomy' => $taxonomy, 'hide_empty' => false]);
        $terms = [];
        if (is_array($terms_raw)) {
            foreach ($terms_raw as $t) {
                $terms[] = [
                    'id' => (int) $t->term_id,
                    'slug' => $t->slug,
                    'name' => $t->name,
                ];
            }
        }
        $attributes[] = [
            'id' => (int) ($row->attribute_id ?? 0),
            'name' => $name,
            'slug' => $slug,
            'taxonomy' => $taxonomy,
            'type' => (string) ($row->attribute_type ?? 'select'),
            'order_by' => (string) ($row->attribute_orderby ?? 'menu_order'),
            'terms' => $terms,
        ];
    }
    return ['attributes' => $attributes, 'total' => count($attributes)];
}
