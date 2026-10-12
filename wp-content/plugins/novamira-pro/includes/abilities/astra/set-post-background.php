<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: write the responsive background meta for a post (page or content background).
 */

wp_register_ability('novamira/astra-set-post-background', [
    'label' => __('Set Astra Post Background', domain: 'novamira-pro'),
    'description' => __(
        'Writes the responsive background meta (`ast-page-background-meta` or `ast-content-background-meta`) for a given post. Each viewport (`desktop`, `tablet`, `mobile`) accepts a partial object with background field overrides. When `merge=true` (default) the payload is deep-merged over existing meta under a per-post advisory lock; when `merge=false` the entire meta is replaced. Supplying `merge=false` with no viewport data, or with viewports whose fields are all invalid, returns an error rather than silently destroying existing state. Unknown fields within a viewport are reported in `invalid_fields` and skipped. The `background-media` field must reference an existing attachment id. Valid fields: background-color, background-image, background-repeat, background-position, background-size, background-attachment, background-type, background-media, overlay-type, overlay-color, overlay-opacity, overlay-gradient.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to write background meta for.',
                'minimum' => 1,
            ],
            'target' => [
                'type' => 'string',
                'description' => '"page" writes ast-page-background-meta, "content" writes ast-content-background-meta.',
                'enum' => ['page', 'content'],
            ],
            'desktop' => [
                'type' => 'object',
                'description' => 'Background fields for the desktop viewport. Partial update when merge=true.',
                'additionalProperties' => true,
            ],
            'tablet' => [
                'type' => 'object',
                'description' => 'Background fields for the tablet viewport.',
                'additionalProperties' => true,
            ],
            'mobile' => [
                'type' => 'object',
                'description' => 'Background fields for the mobile viewport.',
                'additionalProperties' => true,
            ],
            'merge' => [
                'type' => 'boolean',
                'description' => 'When true (default), viewport payloads are merged over existing meta under a per-post advisory lock. When false, the full meta is replaced with only the viewports you provide. merge=false with no valid viewport data returns an error.',
                'default' => true,
            ],
        ],
        'required' => ['post_id', 'target'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'target' => ['type' => 'string'],
            'meta_key' => ['type' => 'string'],
            'written' => [
                'type' => 'object',
                'description' => 'The full background meta array as persisted.',
                'additionalProperties' => true,
            ],
            'invalid_fields' => [
                'type' => 'array',
                'description' => 'Viewport fields not in the background field allowlist or failing validation — skipped.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'viewport' => ['type' => 'string'],
                        'field' => ['type' => 'string'],
                        'reason' => ['type' => 'string'],
                    ],
                    'required' => ['viewport', 'field', 'reason'],
                ],
            ],
            'no_op' => [
                'type' => 'boolean',
                'description' => 'True when no database write occurred because the result was identical to the existing meta.',
            ],
        ],
        'required' => ['post_id', 'target', 'meta_key', 'written', 'invalid_fields', 'no_op'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_set_post_background',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Writes responsive background meta (page or content) for a post under a per-post advisory lock. Set merge=true (default) for partial viewport updates, merge=false to overwrite the full meta. merge=false with no valid viewport data returns an error rather than silently clearing existing state. background-media must be an existing attachment id. Call astra-get-post-settings to read the current background values.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Validate a single viewport object and return clean fields + invalid entries.
 * Unknown fields are skipped with a reason; NUL bytes and oversized strings
 * are sanitized on known fields. The `background-media` field is verified to
 * reference an existing attachment to prevent media id enumeration.
 *
 * @param mixed  $raw_viewport Raw input value for this viewport.
 * @param string $viewport     Viewport name (desktop, tablet, mobile).
 * @return array{clean: array<string, mixed>, invalid: list<array{viewport: string, field: string, reason: string}>}
 */
function ast_pbg_validate_viewport(mixed $raw_viewport, string $viewport): array
{
    $clean = [];
    $invalid = [];
    $field_index = array_flip(astra_post_bg_field_allowlist());

    $arr = astra_coerce_array($raw_viewport);
    if ($arr === null) {
        $invalid[] = [
            'viewport' => $viewport,
            'field' => '*',
            'reason' => 'expected object, got ' . gettype($raw_viewport),
        ];
        return ['clean' => $clean, 'invalid' => $invalid];
    }

    /** @var mixed $val */
    foreach ($arr as $field => $val) {
        if (!is_string($field)) {
            continue;
        }
        if (!array_key_exists($field, $field_index)) {
            $invalid[] = ['viewport' => $viewport, 'field' => $field, 'reason' => 'unknown background field'];
            continue;
        }
        if (is_string($val)) {
            $val = astra_strip_nul_bytes($val);
            // @mago-expect analysis:mixed-assignment
            $val = astra_cap_bytes($val, limit: 2048);
        }
        $clean[$field] = $val;
    }

    // Validate background-media: must reference an accessible attachment post.
    if (isset($clean['background-media']) && is_int($clean['background-media']) && $clean['background-media'] > 0) {
        // @mago-expect analysis:mixed-assignment
        $media = get_post($clean['background-media']);
        if (!$media instanceof WP_Post || $media->post_type !== 'attachment' || $media->post_status !== 'inherit') {
            $invalid[] = [
                'viewport' => $viewport,
                'field' => 'background-media',
                'reason' => 'attachment not found or not accessible',
            ];
            unset($clean['background-media']);
        }
    }

    return ['clean' => $clean, 'invalid' => $invalid];
}

/**
 * Apply viewport patches onto `$base` and return the merged array.
 * Private to this file; used by both apply helpers below.
 *
 * @param array<array-key, mixed>             $base          Starting state (existing meta or empty).
 * @param array<string, array<string, mixed>> $viewport_data Pre-validated viewport patches.
 * @return array<array-key, mixed>
 */
function ast_pbg_build_result(array $base, array $viewport_data): array
{
    $result = $base;
    foreach ($viewport_data as $viewport => $clean) {
        if ($clean === []) {
            continue;
        }
        $existing_vp = is_array($result[$viewport] ?? null) ? $result[$viewport] : [];
        $result[$viewport] = array_merge($existing_vp, $clean);
    }
    return $result;
}

/**
 * merge=true path: read existing meta, deep-merge viewport patches, write if changed.
 * Executed inside `astra_with_post_lock`.
 *
 * @param array<string, array<string, mixed>> $viewport_data Pre-validated viewport patches.
 * @return array{written: array<array-key, mixed>, no_op: bool}
 */
function ast_pbg_do_merge(int $post_id, string $meta_key, array $viewport_data): array
{
    /** @var mixed $existing_raw */
    $existing_raw = get_post_meta($post_id, $meta_key, single: true);
    $existing = is_array($existing_raw) ? $existing_raw : [];
    $result = ast_pbg_build_result($existing, $viewport_data);
    if ($result === $existing) {
        return ['written' => $existing, 'no_op' => true];
    }
    update_post_meta($post_id, $meta_key, $result);
    return ['written' => $result, 'no_op' => false];
}

/**
 * merge=false path: build a fresh meta from viewport patches only, then write.
 * Returns WP_Error when no valid viewport data was provided (would silently destroy existing state).
 * Executed inside `astra_with_post_lock`.
 *
 * @param array<string, array<string, mixed>> $viewport_data Pre-validated viewport patches.
 * @return array{written: array<array-key, mixed>, no_op: bool}|WP_Error
 */
function ast_pbg_do_replace(int $post_id, string $meta_key, array $viewport_data): array|WP_Error
{
    $result = ast_pbg_build_result([], $viewport_data);
    if ($result === []) {
        return astra_invalid_input_error('merge=false requires at least one viewport with valid fields.');
    }
    /** @var mixed $existing_raw */
    $existing_raw = get_post_meta($post_id, $meta_key, single: true);
    $existing = is_array($existing_raw) ? $existing_raw : [];
    if ($result === $existing) {
        return ['written' => $existing, 'no_op' => true];
    }
    update_post_meta($post_id, $meta_key, $result);
    return ['written' => $result, 'no_op' => false];
}

/**
 * @param array<array-key, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_set_post_background(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }

    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', __('manage_options capability required.', domain: 'novamira-pro'), [
            'status' => 403,
        ]);
    }

    $post_id = astra_coerce_int($input['post_id'] ?? 0);
    $post_or_err = astra_post_or_error($post_id);
    if ($post_or_err instanceof WP_Error) {
        return $post_or_err;
    }

    $target_raw = is_string($input['target'] ?? null) ? $input['target'] : '';
    $meta_key = match ($target_raw) {
        'page' => 'ast-page-background-meta',
        'content' => 'ast-content-background-meta',
        default => '',
    };
    if ($meta_key === '') {
        return astra_invalid_input_error('target must be "page" or "content".');
    }

    $merge = astra_coerce_bool($input['merge'] ?? true);

    // Pre-validate viewport data outside the lock to fail fast on format errors.
    $all_invalid = [];
    /** @var array<string, array<string, mixed>> $viewport_data */
    $viewport_data = [];

    foreach (astra_post_bg_viewports() as $viewport) {
        if (!array_key_exists($viewport, $input)) {
            continue;
        }
        $v = ast_pbg_validate_viewport($input[$viewport], $viewport);
        $all_invalid = array_merge($all_invalid, $v['invalid']);
        $viewport_data[$viewport] = $v['clean'];
    }

    // Read + merge + write atomically under a per-post advisory lock.
    $lock_result = astra_with_post_lock(
        $post_id,
        $merge
            ? static fn() => ast_pbg_do_merge($post_id, $meta_key, $viewport_data)
            : static fn() => ast_pbg_do_replace($post_id, $meta_key, $viewport_data),
    );

    if ($lock_result instanceof WP_Error) {
        return $lock_result;
    }

    clean_post_cache($post_id);

    return [
        'post_id' => $post_id,
        'target' => $target_raw,
        'meta_key' => $meta_key,
        'written' => $lock_result['written'],
        'invalid_fields' => $all_invalid,
        'no_op' => $lock_result['no_op'],
    ];
}
