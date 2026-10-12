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

wp_register_ability('novamira/woocommerce-create-product-attribute-term', [
    'label' => __('Create Product Attribute Term', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new term (option) inside a global product attribute. '
        . 'Required: `name` plus exactly one of `attribute_id` (int) or `taxonomy_slug` (string, e.g. "pa_size" or "size") to identify the parent attribute. '
        . 'Optional: `slug` (auto-generated from name), `description`. '
        . 'Mutual exclusion: provide `attribute_id` OR `taxonomy_slug`, not both. '
        . 'Returns the full term object including attribute_id and taxonomy_slug. '
        . 'Possible errors: wc_inactive, wc_invalid_input (mutual exclusion, name empty), wc_attribute_not_found, wc_term_exists (duplicate name), wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'attribute_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Parent attribute id. Mutual exclusion with taxonomy_slug.',
            ],
            'taxonomy_slug' => [
                'type' => ['string', 'null'],
                'description' => 'Parent attribute taxonomy slug, e.g. "pa_size" or "size". Mutual exclusion with attribute_id.',
            ],
            'name' => ['type' => 'string', 'description' => 'Term display label (required), e.g. "Small".'],
            'slug' => ['type' => ['string', 'null'], 'description' => 'URL slug. Auto-generated from name if omitted.'],
            'description' => ['type' => ['string', 'null'], 'description' => 'Term description.'],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean', 'description' => 'True when the term was created successfully.'],
            'term' => [
                'type' => 'object',
                'description' => 'The newly created term object.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_create_product_attribute_term',
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
function woocommerce_create_product_attribute_term(array $input): array|WP_Error
{
    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    $name_err = wc_require_term_name($input);
    if ($name_err !== null) {
        return $name_err;
    }

    $attr_id = wc_resolve_attribute_id($input);
    if ($attr_id instanceof WP_Error) {
        return $attr_id;
    }

    $attr = \wc_get_attribute($attr_id);
    if (!is_object($attr)) {
        return wc_error('wc_attribute_not_found', sprintf(
            /* translators: attribute id */
            __('Attribute with id %d not found.', domain: 'novamira-pro'),
            $attr_id,
        ));
    }

    $taxonomy_slug = wc_attr_object_to_taxonomy_slug($attr);
    $tax_check = wc_ensure_taxonomy_or_error($taxonomy_slug);
    if ($tax_check instanceof WP_Error) {
        return $tax_check;
    }

    $term_args = wc_apply_term_input(term_id: 0, input: $input, taxonomy: $taxonomy_slug);
    if ($term_args instanceof WP_Error) {
        return $term_args;
    }

    try {
        /** @var array{term_id: int, term_taxonomy_id: int}|WP_Error $result */
        $result = \wp_insert_term((string) ($term_args['name'] ?? $input['name']), $taxonomy_slug, [
            'description' => (string) ($term_args['description'] ?? ''),
            'slug' => (string) ($term_args['slug'] ?? ''),
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
                'A term with this name already exists in this attribute.',
                domain: 'novamira-pro',
            ));
        }
        return wc_error('wc_internal_error', $result->get_error_message());
    }

    $term_id = (int) $result['term_id'];
    $term = get_term($term_id, $taxonomy_slug);
    if (!$term instanceof WP_Term) {
        return wc_error('wc_internal_error', __('Failed to reload term after creation.', domain: 'novamira-pro'));
    }

    return ['success' => true, 'term' => wc_attribute_term_to_array($term, $attr_id, $taxonomy_slug)];
}
