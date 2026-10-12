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

wp_register_ability('novamira/woocommerce-edit-product-attribute-term', [
    'label' => __('Edit Product Attribute Term', domain: 'novamira-pro'),
    'description' => __(
        'Partially updates an existing attribute term (option) inside a global product attribute (PATCH semantics). '
        . 'Required: `id` (term id). Optional: `name`, `slug`, `description`. '
        . 'Only fields you pass change; omitted fields stay as-is. '
        . 'Returns the full updated term object including attribute_id and taxonomy_slug. '
        . 'Possible errors: wc_inactive, wc_attribute_term_not_found, wc_invalid_input, wc_term_exists (slug rename collision), wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Term id (required).'],
            'name' => ['type' => 'string', 'description' => 'New display label.'],
            'slug' => ['type' => ['string', 'null'], 'description' => 'New URL slug.'],
            'description' => ['type' => ['string', 'null'], 'description' => 'Term description.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean', 'description' => 'True when the term was updated successfully.'],
            'term' => [
                'type' => 'object',
                'description' => 'The updated term object.',
                'properties' => [
                    'id' => ['type' => 'integer', 'description' => 'Term id.'],
                    'name' => ['type' => 'string', 'description' => 'Term display label, e.g. "Small".'],
                    'slug' => ['type' => 'string', 'description' => 'URL slug (lowercased), e.g. "small".'],
                    'description' => ['type' => 'string', 'description' => 'Term description.'],
                    'count' => ['type' => 'integer', 'description' => 'Number of products assigned this term.'],
                    'attribute_id' => ['type' => 'integer', 'description' => 'Parent attribute id.'],
                    'taxonomy_slug' => [
                        'type' => 'string',
                        'description' => 'Parent attribute taxonomy name, e.g. "pa_size".',
                    ],
                ],
                'required' => ['id', 'name', 'slug', 'description', 'count', 'attribute_id', 'taxonomy_slug'],
            ],
        ],
        'required' => ['success', 'term'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_edit_product_attribute_term',
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
function woocommerce_edit_product_attribute_term(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $term_id = (int) $input['id'];
    $term = wc_post_to_attribute_term($term_id);
    if ($term instanceof WP_Error) {
        return $term;
    }

    $taxonomy_slug = $term->taxonomy;
    $attr_id = \wc_attribute_taxonomy_id_by_name($taxonomy_slug);

    $term_args = wc_apply_term_input(term_id: $term_id, input: $input, taxonomy: $taxonomy_slug);
    if ($term_args instanceof WP_Error) {
        return $term_args;
    }

    if ($term_args === []) {
        // Nothing to update — return current state
        $fresh = get_term($term_id, $taxonomy_slug);
        if (!$fresh instanceof WP_Term) {
            return wc_error('wc_attribute_term_not_found', sprintf(
                __('Attribute term %d not found.', domain: 'novamira-pro'),
                $term_id,
            ));
        }
        return ['success' => true, 'term' => wc_attribute_term_to_array($fresh, $attr_id, $taxonomy_slug)];
    }

    return wc_apply_attribute_term_update($term_id, $taxonomy_slug, $attr_id, $term_args);
}

/**
 * Execute wp_update_term and return the updated term shape.
 *
 * @param array<string, mixed> $term_args
 * @return array<string, mixed>|WP_Error
 */
function wc_apply_attribute_term_update(
    int $term_id,
    string $taxonomy_slug,
    int $attr_id,
    array $term_args,
): array|WP_Error {
    $update_args = [];
    if (isset($term_args['name'])) {
        $update_args['name'] = (string) $term_args['name'];
    }
    if (isset($term_args['slug'])) {
        $update_args['slug'] = (string) $term_args['slug'];
    }
    if (isset($term_args['description'])) {
        $update_args['description'] = (string) $term_args['description'];
    }

    try {
        /** @var array{term_id: int, term_taxonomy_id: int}|WP_Error $result */
        // @mago-expect analysis:possibly-invalid-argument
        $result = \wp_update_term($term_id, $taxonomy_slug, $update_args);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', sprintf(
            __('Term update failed unexpectedly: %s', domain: 'novamira-pro'),
            $e->getMessage(),
        ));
    }

    if ($result instanceof WP_Error) {
        $code = $result->get_error_code();
        if ($code === 'term_exists') {
            return wc_error('wc_term_exists', __(
                'A term with this name already exists in this attribute.',
                domain: 'novamira-pro',
            ));
        }
        return wc_error('wc_internal_error', $result->get_error_message());
    }

    $updated_id = (int) $result['term_id'];
    $fresh = get_term($updated_id, $taxonomy_slug);
    if (!$fresh instanceof WP_Term) {
        return wc_error('wc_internal_error', __('Failed to reload term after update.', domain: 'novamira-pro'));
    }

    return ['success' => true, 'term' => wc_attribute_term_to_array($fresh, $attr_id, $taxonomy_slug)];
}
