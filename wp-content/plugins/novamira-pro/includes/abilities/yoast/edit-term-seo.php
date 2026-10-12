<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Yoast;

use WP_Error;
use WPSEO_Taxonomy_Meta;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/yoast-edit-term-seo', [
    'label' => __('Edit Term SEO (Yoast)', domain: 'novamira-pro'),
    'description' => __(
        'Updates the Yoast SEO metadata for a single taxonomy term (category, tag, or custom-taxonomy term). Send only the fields you want to change — it is a partial merge; the ability reads the term\'s current SEO and writes the full set back so untouched fields are preserved (Yoast would otherwise reset them). Fields: focus_keyphrase, seo_title, meta_description, canonical, breadcrumb_title, cornerstone (bool), robots {index: default|index|noindex}, opengraph/twitter {title, description, image, image_id}. Terms carry only the indexing toggle (no nofollow / advanced robots / schema). Identify the term by term_id + taxonomy. Returns the full updated SEO.',
        domain: 'novamira-pro',
    ),
    'category' => 'yoast',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => ['type' => 'integer', 'description' => 'The taxonomy term id.'],
            'taxonomy' => ['type' => 'string', 'description' => 'The taxonomy slug, e.g. category or post_tag.'],
            'focus_keyphrase' => ['type' => 'string'],
            'seo_title' => ['type' => 'string'],
            'meta_description' => ['type' => 'string'],
            'canonical' => ['type' => 'string'],
            'breadcrumb_title' => ['type' => 'string'],
            'cornerstone' => ['type' => 'boolean'],
            'robots' => [
                'type' => 'object',
                'properties' => [
                    'index' => ['type' => 'string', 'enum' => ['default', 'index', 'noindex']],
                ],
                'additionalProperties' => false,
            ],
            'opengraph' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'image' => ['type' => 'string'],
                    'image_id' => ['type' => 'integer'],
                ],
                'additionalProperties' => false,
            ],
            'twitter' => [
                'type' => 'object',
                'properties' => [
                    'title' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'image' => ['type' => 'string'],
                    'image_id' => ['type' => 'integer'],
                ],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['term_id', 'taxonomy'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'term_id' => ['type' => 'integer'],
            'taxonomy' => ['type' => 'string'],
            'seo' => ['type' => 'object'],
        ],
        'required' => ['term_id', 'taxonomy', 'seo'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Yoast\yoast_edit_term_seo',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update of a term\'s Yoast SEO. The ability merges your changes over the term\'s existing values, so sending just one field never wipes the others. robots.index uses friendly labels (default|index|noindex). Terms have no nofollow/advanced/schema fields. Read first with yoast-get-term-seo when unsure.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array{term_id: int, taxonomy: string, seo: array<string, mixed>}|WP_Error
 */
function yoast_edit_term_seo(array $args): array|WP_Error
{
    if (!yoast_detect_setup()['active']) {
        return yoast_not_active();
    }

    $term = yoast_resolve_term($args['term_id'] ?? null, $args['taxonomy'] ?? null);
    if ($term instanceof WP_Error) {
        return $term;
    }

    $current = yoast_term_meta_all($term->term_id, $term->taxonomy);
    $payload = ets_build_payload($current, $args);
    if ($payload instanceof WP_Error) {
        return $payload;
    }

    WPSEO_Taxonomy_Meta::set_values($term->term_id, $term->taxonomy, $payload);

    return [
        'term_id' => $term->term_id,
        'taxonomy' => $term->taxonomy,
        'seo' => yoast_term_seo($term),
    ];
}

/**
 * Overlay the requested changes onto the term's current meta and return the
 * full prefixed-key payload for set_values(). Returns a WP_Error on a bad value.
 *
 * @param array<array-key, mixed> $current
 * @param array<string, mixed> $args
 * @return array<array-key, mixed>|WP_Error
 */
function ets_build_payload(array $current, array $args): array|WP_Error
{
    $payload = $current;
    ets_overlay_scalars($payload, $args);

    if (array_key_exists('cornerstone', $args)) {
        $payload['wpseo_is_cornerstone'] = yoast_meta_bool($args['cornerstone']) ? '1' : '0';
    }

    if (array_key_exists('robots', $args) && is_array($args['robots']) && array_key_exists('index', $args['robots'])) {
        /** @var mixed $index */
        $index = $args['robots']['index'];
        if (!is_string($index) || !in_array($index, ['default', 'index', 'noindex'], strict: true)) {
            return new WP_Error('yoast_invalid_robots', 'robots.index must be default, index, or noindex', [
                'status' => 400,
            ]);
        }
        $payload['wpseo_noindex'] = $index;
    }

    foreach (['opengraph', 'twitter'] as $network) {
        ets_overlay_social($payload, $network, $args);
    }

    return $payload;
}

/**
 * Overlay the plain string term fields onto the payload.
 *
 * @param array<array-key, mixed> $payload
 * @param array<string, mixed> $args
 */
function ets_overlay_scalars(array &$payload, array $args): void
{
    $map = [
        'focus_keyphrase' => 'wpseo_focuskw',
        'seo_title' => 'wpseo_title',
        'meta_description' => 'wpseo_desc',
        'canonical' => 'wpseo_canonical',
        'breadcrumb_title' => 'wpseo_bctitle',
    ];
    foreach ($map as $friendly => $stored_key) {
        if (!array_key_exists($friendly, $args) || !is_string($args[$friendly])) {
            continue;
        }
        $payload[$stored_key] = $friendly === 'canonical'
            ? yoast_clean_url($args[$friendly])
            : yoast_clean_text($args[$friendly]);
    }
}

/**
 * Overlay a social-preview block (network is 'opengraph' or 'twitter') onto
 * the payload, mapping to the term's `wpseo_<network>-<field>` keys.
 *
 * @param array<array-key, mixed> $payload
 * @param array<string, mixed> $args
 */
function ets_overlay_social(array &$payload, string $network, array $args): void
{
    if (!array_key_exists($network, $args) || !is_array($args[$network])) {
        return;
    }
    $block = $args[$network];
    foreach (['title', 'description', 'image'] as $field) {
        if (!array_key_exists($field, $block) || !is_string($block[$field])) {
            continue;
        }
        $payload["wpseo_{$network}-{$field}"] = $field === 'image'
            ? yoast_clean_url($block[$field])
            : yoast_clean_text($block[$field]);
    }
    if (array_key_exists('image_id', $block)) {
        $payload["wpseo_{$network}-image-id"] = (string) max(0, yoast_int($block['image_id']));
    }
}
