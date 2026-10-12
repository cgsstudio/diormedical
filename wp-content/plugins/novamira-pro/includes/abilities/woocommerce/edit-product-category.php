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

wp_register_ability('novamira/woocommerce-edit-product-category', [
    'label' => __('Edit Product Category', domain: 'novamira-pro'),
    'description' => __(
        'Partially updates (PATCH semantics) a WooCommerce product category. Only fields present in the input are changed. Requires `id`. '
        . 'Optional: `name`, `slug`, `description`, `parent_id` (int) or `parent_slug` (string); mutually exclusive, pass `parent_id: 0` to make root. '
        . 'Category-specific: `thumbnail_id` (0/null to clear), `display_type` (default|products|subcategories|both), `menu_order` (int >= 0). '
        . 'The default Uncategorized category (stored in WC option default_product_cat) cannot be edited; returns wc_invalid_input. '
        . 'WordPress silently strips benign HTML tags (`<b>`, `<i>`, etc.) from `name`; the response reflects the stripped value. '
        . 'Possible errors: wc_inactive, wc_term_not_found, wc_invalid_input (default category, name empty/too long, description too long, bad display_type, bad menu_order), '
        . 'wc_invalid_parent (parent not found, circular ref), wc_term_exists (duplicate name+parent), wc_attachment_not_found, wc_internal_error. '
        . 'Response reflects the actual stored term. WP filters (`wp_update_term_data`, `edit_term`, etc.) added by third-party plugins may rewrite fields before they land in the database.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Category id (required).'],
            'name' => ['type' => 'string', 'description' => 'New category name.'],
            'slug' => ['type' => 'string', 'description' => 'New URL slug.'],
            'description' => ['type' => 'string', 'description' => 'Category description. Max 64 KB.'],
            'parent_id' => [
                'type' => ['integer', 'null'],
                'description' => 'New parent category id. Pass 0 to make this a root category. Mutual exclusion with parent_slug.',
            ],
            'parent_slug' => [
                'type' => ['string', 'null'],
                'description' => 'New parent category slug. Pass empty string to make root. Mutual exclusion with parent_id.',
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
                'description' => 'Ordering hint. Default 0.',
            ],
        ],
        'required' => ['id'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_edit_product_category',
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
function woocommerce_edit_product_category(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $taxonomy = 'product_cat';
    $term_id = (int) $input['id'];

    // Verify term exists in product_cat taxonomy
    $term = wc_post_to_term($term_id, $taxonomy);
    if ($term instanceof WP_Error) {
        return $term;
    }

    // D3: block default category
    $default_guard = wc_validate_default_category($term_id);
    if ($default_guard !== null) {
        return $default_guard;
    }

    $args = wc_apply_term_input(term_id: $term_id, input: $input, taxonomy: $taxonomy);
    if ($args instanceof WP_Error) {
        return $args;
    }

    // Re-validate right before mutation (closes TOCTOU window where default_product_cat
    // might have changed between the first check and the actual update).
    $default_guard = wc_validate_default_category($term_id);
    if ($default_guard !== null) {
        return $default_guard;
    }

    if ($args !== []) {
        /** @var array{'alias_of'?: string, 'description'?: string, 'parent'?: int, 'slug'?: string} $args */
        try {
            /** @var array{term_id: int, term_taxonomy_id: int}|WP_Error $result */
            $result = \wp_update_term($term_id, $taxonomy, $args);
        } catch (\Throwable $e) {
            return wc_error('wc_internal_error', sprintf(
                __('Term update failed unexpectedly: %s', domain: 'novamira-pro'),
                $e->getMessage(),
            ));
        }

        if ($result instanceof WP_Error) {
            $code = $result->get_error_code();
            if ($code === 'term_exists' || $code === 'duplicate_term_slug') {
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
    }

    // Apply category extras (thumbnail, display_type, menu_order)
    $extras_err = wc_set_category_extras($term_id, $input);
    if ($extras_err !== null) {
        return $extras_err;
    }

    $updated_term = get_term($term_id, $taxonomy);
    if (!$updated_term instanceof WP_Term) {
        return wc_error('wc_internal_error', __('Failed to reload category after update.', domain: 'novamira-pro'));
    }

    return ['success' => true, 'category' => wc_term_to_array($updated_term, $taxonomy)];
}
