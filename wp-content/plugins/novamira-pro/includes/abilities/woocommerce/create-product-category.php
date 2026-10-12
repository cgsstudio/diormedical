<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WP_Error;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-create-product-category', [
    'label' => __('Create Product Category', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new WooCommerce product category (taxonomy: product_cat). Returns the full category object on success. '
        . 'Required: `name`. Optional: `slug` (auto-generated from name if omitted), `description`, `parent_id` (int), `parent_slug` (string); provide one or the other, not both; omit for a root category. '
        . 'Category-specific: `thumbnail_id` (attachment id, 0/null to clear), `display_type` (default|products|subcategories|both), `menu_order` (int >= 0). '
        . 'Slug collision: WP appends a uniqueness suffix; for hierarchical categories it may use the parent slug as suffix. The response reflects the actual stored slug. '
        . 'WordPress silently strips benign HTML tags (`<b>`, `<i>`, etc.) from `name`; the response reflects the stripped value. '
        . 'Possible errors: wc_inactive, wc_invalid_input (name empty/too long, description too long, bad display_type, bad thumbnail, bad menu_order), '
        . 'wc_invalid_parent (parent not found, circular ref), wc_term_exists (duplicate name+parent), wc_attachment_not_found, wc_internal_error. '
        . 'Response reflects the actual stored term. WP filters (`wp_update_term_data`, `create_term`, etc.) added by third-party plugins may rewrite fields before they land in the database.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Category name (required).'],
            'slug' => ['type' => 'string', 'description' => 'URL slug. Auto-generated from name if omitted.'],
            'description' => ['type' => 'string', 'description' => 'Category description. Max 64 KB.'],
            'parent_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Parent category id. 0 or omit for root. Mutual exclusion with parent_slug.',
            ],
            'parent_slug' => [
                'type' => ['string', 'null'],
                'description' => 'Parent category slug. Omit for root. Mutual exclusion with parent_id.',
            ],
            'thumbnail_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Attachment id for the category featured image. 0 or null to clear.',
            ],
            'display_type' => [
                'type' => 'string',
                'description' => 'Archive page display type. Allowed: default, products, subcategories, both.',
            ],
            'menu_order' => [
                'type' => 'integer',
                'description' => 'Ordering hint. Default 0. Higher values appear later in default ordering.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'category' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Category term ID.'],
                    'name' => ['type' => 'string', 'description' => 'Display name.'],
                    'slug' => [
                        'type' => 'string',
                        'description' => 'URL slug (stored, may differ from input if collision).',
                    ],
                    'description' => ['type' => 'string', 'description' => 'Category description.'],
                    'parent_id' => ['type' => 'integer', 'description' => 'Parent category ID; 0 if root.'],
                    'parent_slug' => [
                        'type' => ['string', 'null'],
                        'description' => 'Parent category slug; null if root.',
                    ],
                    'count' => ['type' => 'integer', 'description' => 'Number of products directly attached.'],
                    'thumbnail_id' => [
                        'type' => ['integer', 'null'],
                        'description' => 'Featured image attachment ID or null.',
                    ],
                    'thumbnail_src' => ['type' => ['string', 'null'], 'description' => 'Featured image URL or null.'],
                    'display_type' => [
                        'type' => 'string',
                        'description' => 'Archive display: default, products, subcategories, or both.',
                    ],
                    'menu_order' => ['type' => 'integer', 'description' => 'Sort order.'],
                ],
                'required' => ['id', 'name', 'slug', 'parent_id', 'count'],
            ],
        ],
        'required' => ['success', 'category'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_create_product_category',
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
function woocommerce_create_product_category(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $name_err = wc_require_term_name($input);
    if ($name_err !== null) {
        return $name_err;
    }

    $taxonomy = 'product_cat';
    $args = wc_apply_term_input(term_id: 0, input: $input, taxonomy: $taxonomy);
    if ($args instanceof WP_Error) {
        return $args;
    }

    try {
        /** @var array{term_id: int, term_taxonomy_id: int}|WP_Error $result */
        $result = \wp_insert_term((string) $args['name'], $taxonomy, [
            'description' => (string) ($args['description'] ?? ''),
            'slug' => (string) ($args['slug'] ?? ''),
            'parent' => (int) ($args['parent'] ?? 0),
        ]);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Term creation failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        $code = $result->get_error_code();
        if ($code === 'term_exists') {
            return wc_error('wc_term_exists', __(
                'A category with this name already exists under the same parent.',
                domain: 'novamira-pro',
            ));
        }
        if ($code === 'invalid_term_name' || $code === 'empty_term_name') {
            return wc_error('wc_invalid_input', $result->get_error_message());
        }
        return wc_error('wc_internal_error', $result->get_error_message());
    }

    $term_id = (int) $result['term_id'];

    // Apply category extras (thumbnail, display_type, menu_order)
    $extras_err = wc_set_category_extras($term_id, $input);
    if ($extras_err !== null) {
        // Clean up the term we just created before returning the error
        \wp_delete_term($term_id, $taxonomy);
        return $extras_err;
    }

    $term = get_term($term_id, $taxonomy);
    if (!$term instanceof WP_Term) {
        return wc_error('wc_internal_error', __('Failed to reload category after creation.', domain: 'novamira-pro'));
    }

    return ['success' => true, 'category' => wc_term_to_array($term, $taxonomy)];
}
