<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-get-page-overrides', [
    'label' => __('Get OceanWP Per-Page Overrides', domain: 'novamira-pro'),
    'description' => __(
        'Reads the agent-curated allowlist of `ocean_*` postmeta override keys on a single post. Each entry reports whether the override is actually set on the post (`is_set: true` means the post overrides the global default) or whether the post inherits the global. Returns the meta value when set, null otherwise. Filter to a specific subset with `fields: [...]`. For meta keys outside the allowlist use `novamira/execute-php` with `get_post_meta($post_id, "ocean_<key>", true)`.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'Post id (any public CPT). Trash / auto-draft / inherit are rejected.',
                'minimum' => 1,
            ],
            'fields' => [
                'type' => 'array',
                'description' => 'Optional subset of allowlist meta keys to fetch. Unknown keys are reported in `unknown[]`.',
                'items' => ['type' => 'string'],
                'maxItems' => 50,
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'total_allowlisted' => ['type' => 'integer'],
            'returned' => ['type' => 'integer'],
            'unknown' => ['type' => 'array', 'items' => ['type' => 'string']],
            'overrides' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'type' => ['type' => 'string', 'enum' => ['boolean', 'integer', 'string', 'enum', 'color']],
                        'is_set' => [
                            'type' => 'boolean',
                            'description' => 'True when the post has an explicit override; false means the post inherits the global default.',
                        ],
                        'value' => [
                            'type' => ['boolean', 'integer', 'string', 'null'],
                            'description' => 'Current meta value when `is_set` is true; null when inherited.',
                        ],
                    ],
                    'required' => ['name', 'type', 'is_set', 'value'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['post_id', 'total_allowlisted', 'returned', 'unknown', 'overrides'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_get_page_overrides',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Reports both set + unset entries by default so the agent can distinguish "post overrides the global" from "post inherits the global default". Pass `fields: [...]` to narrow.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id?: int, fields?: list<string>} $input
 * @return array{
 *   post_id: int,
 *   total_allowlisted: int,
 *   returned: int,
 *   unknown: list<string>,
 *   overrides: list<array{name: string, type: string, is_set: bool, value: mixed}>
 * }|WP_Error
 */
function oceanwp_get_page_overrides(array $input): array|WP_Error
{
    if (!\Novamira\Pro\Abilities\OceanWP\oceanwp_theme_available()) {
        return ow_not_active();
    }
    $post_id = ow_coerce_int_or_null($input['post_id'] ?? null);
    if ($post_id === null) {
        return ow_invalid_input(__('Input `post_id` is required (positive integer).', domain: 'novamira-pro'));
    }
    $post = ow_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $allowlist = ow_page_overrides_allowlist();
    $requested = $input['fields'] ?? [];

    [$names, $unknown] = ow_resolve_page_override_names($requested, $allowlist);

    $overrides = [];
    foreach ($names as $name) {
        $overrides[] = ow_read_page_override($post->ID, $name, $allowlist[$name]);
    }

    return [
        'post_id' => $post->ID,
        'total_allowlisted' => count($allowlist),
        'returned' => count($overrides),
        'unknown' => $unknown,
        'overrides' => $overrides,
    ];
}

/**
 * Read one page-override key into the response shape, distinguishing
 * "explicit override" from "inherited global default" via metadata_exists.
 * Extracted out of the main callback to keep its cyclomatic complexity
 * under the mago threshold.
 *
 * @param array{type: string, panel?: string, description: string, enum?: list<string>} $meta
 * @return array{name: string, type: string, is_set: bool, value: bool|int|string|null}
 */
function ow_read_page_override(int $post_id, string $name, array $meta): array
{
    $is_set = metadata_exists('post', $post_id, $name);
    $value = $is_set ? ow_normalize_post_meta_scalar(get_post_meta($post_id, $name, single: true)) : null;
    return [
        'name' => $name,
        'type' => $meta['type'],
        'is_set' => $is_set,
        'value' => $value,
    ];
}

/**
 * Coerce a raw post-meta value (`get_post_meta` returns mixed) into the
 * scalar shape declared by the page-overrides output schema, applying the
 * per-field byte cap to long strings. Floats are coerced to int when they
 * are whole, otherwise to string — the output schema declares
 * `value: boolean|integer|string|null` without `number`, so passing a
 * float through unchanged would violate the contract. Audit round 2
 * M#1 / M#3.
 */
// @mago-expect lint:halstead
function ow_normalize_post_meta_scalar(mixed $raw): bool|int|string|null
{
    if (is_bool($raw) || is_int($raw)) {
        return $raw;
    }
    if (is_float($raw)) {
        return (float) (int) $raw === $raw ? (int) $raw : (string) $raw;
    }
    if (is_string($raw)) {
        return ow_truncate_field($raw, OW_FIELD_BYTES_DEFAULT);
    }
    return null;
}
