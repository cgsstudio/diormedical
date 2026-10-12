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

wp_register_ability('novamira/woocommerce-edit-product-tag', [
    'label' => __('Edit Product Tag', domain: 'novamira-pro'),
    'description' => __(
        'Partially updates (PATCH semantics) a WooCommerce product tag. Only fields present in the input are changed. Requires `id`. '
        . 'Optional: `name`, `slug`, `description`. '
        . 'Tags are flat; they do not accept parent_id, parent_slug, thumbnail_id, display_type, or menu_order. '
        . 'WordPress silently strips benign HTML tags (`<b>`, `<i>`, etc.) from `name`; the response reflects the stripped value. '
        . 'Possible errors: wc_inactive, wc_term_not_found, wc_invalid_input (name empty/too long, description too long), wc_term_exists (duplicate name/slug), wc_internal_error. '
        . 'Response reflects the actual stored term. WP filters (`wp_update_term_data`, `edit_term`, etc.) added by third-party plugins may rewrite fields before they land in the database.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Tag id (required).'],
            'name' => ['type' => 'string', 'description' => 'New tag name.'],
            'slug' => ['type' => 'string', 'description' => 'New URL slug.'],
            'description' => ['type' => 'string', 'description' => 'Tag description. Max 64 KB.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'tag' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Tag term ID.'],
                    'name' => ['type' => 'string', 'description' => 'Display name.'],
                    'slug' => ['type' => 'string', 'description' => 'URL slug.'],
                    'description' => ['type' => 'string', 'description' => 'Tag description.'],
                    'count' => ['type' => 'integer', 'description' => 'Number of products tagged.'],
                ],
                'required' => ['id', 'name', 'slug', 'count'],
            ],
        ],
        'required' => ['success', 'tag'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_edit_product_tag',
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
function woocommerce_edit_product_tag(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $taxonomy = 'product_tag';
    $term_id = (int) $input['id'];

    $term = wc_post_to_term($term_id, $taxonomy);
    if ($term instanceof WP_Error) {
        return $term;
    }

    $args = wc_apply_term_input(term_id: $term_id, input: $input, taxonomy: $taxonomy);
    if ($args instanceof WP_Error) {
        return $args;
    }

    if ($args !== []) {
        /** @var array{'alias_of'?: string, 'description'?: string, 'parent'?: int, 'slug'?: string} $args */
        try {
            /** @var array{term_id: int, term_taxonomy_id: int}|WP_Error $result */
            $result = \wp_update_term($term_id, $taxonomy, $args);
        } catch (\Throwable $e) {
            return wc_error('wc_internal_error', sprintf(
                __('Tag update failed unexpectedly: %s', domain: 'novamira-pro'),
                $e->getMessage(),
            ));
        }

        if ($result instanceof WP_Error) {
            $code = $result->get_error_code();
            if ($code === 'term_exists' || $code === 'duplicate_term_slug') {
                return wc_error('wc_term_exists', __(
                    'A tag with this name or slug already exists.',
                    domain: 'novamira-pro',
                ));
            }
            if ($code === 'invalid_term_name' || $code === 'empty_term_name') {
                return wc_error('wc_invalid_input', $result->get_error_message());
            }
            return wc_error('wc_internal_error', $result->get_error_message());
        }
    }

    $updated = get_term($term_id, $taxonomy);
    if (!$updated instanceof WP_Term) {
        return wc_error('wc_internal_error', __('Failed to reload tag after update.', domain: 'novamira-pro'));
    }

    return ['success' => true, 'tag' => wc_term_to_array($updated, $taxonomy)];
}
