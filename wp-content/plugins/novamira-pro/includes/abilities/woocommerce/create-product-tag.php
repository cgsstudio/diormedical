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

wp_register_ability('novamira/woocommerce-create-product-tag', [
    'label' => __('Create Product Tag', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new WooCommerce product tag (taxonomy: product_tag). Returns the full tag object on success. '
        . 'Required: `name`. Optional: `slug` (auto-generated from name if omitted), `description`. '
        . 'Tags are flat; no parent, no thumbnail, no display_type, no menu_order. '
        . 'Slug collision: WP appends a uniqueness suffix automatically; the response reflects the actual stored slug. '
        . 'WordPress silently strips benign HTML tags (`<b>`, `<i>`, etc.) from `name`; the response reflects the stripped value. '
        . 'Possible errors: wc_inactive, wc_invalid_input (name empty/too long, description too long), wc_term_exists (duplicate slug), wc_internal_error. '
        . 'Response reflects the actual stored term. WP filters (`wp_update_term_data`, `create_term`, etc.) added by third-party plugins may rewrite fields before they land in the database.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Tag name (required).'],
            'slug' => ['type' => 'string', 'description' => 'URL slug. Auto-generated from name if omitted.'],
            'description' => ['type' => 'string', 'description' => 'Tag description. Max 64 KB.'],
        ],
        'required' => ['name'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_create_product_tag',
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
function woocommerce_create_product_tag(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $name_err = wc_require_term_name($input);
    if ($name_err !== null) {
        return $name_err;
    }

    $taxonomy = 'product_tag';
    $args = wc_apply_term_input(term_id: 0, input: $input, taxonomy: $taxonomy);
    if ($args instanceof WP_Error) {
        return $args;
    }

    try {
        /** @var array{term_id: int, term_taxonomy_id: int}|WP_Error $result */
        $result = \wp_insert_term((string) $args['name'], $taxonomy, [
            'description' => (string) ($args['description'] ?? ''),
            'slug' => (string) ($args['slug'] ?? ''),
        ]);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Tag creation failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        $code = $result->get_error_code();
        if ($code === 'term_exists') {
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

    $term_id = (int) $result['term_id'];

    $term = get_term($term_id, $taxonomy);
    if (!$term instanceof WP_Term) {
        return wc_error('wc_internal_error', __('Failed to reload tag after creation.', domain: 'novamira-pro'));
    }

    return ['success' => true, 'tag' => wc_term_to_array($term, $taxonomy)];
}
