<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read one Voxel setting by dot path (read-only, secrets refused).
 */

wp_register_ability('novamira/voxel-get-setting', [
    'label' => __('Get Voxel Setting', domain: 'novamira-pro'),
    'description' => __(
        'Read one Voxel setting by its dot path relative to the settings root, e.g. "timeline.posts.enabled" or "messages.maxlength". Read-only. Secret settings (paths whose key is a secret, client_secret, api_key, ...) are refused, and any secret nested inside the returned value is redacted. A value larger than max_field_bytes comes back as a truncation marker { value, truncated, full_size } (value = the JSON cut to the cap) instead of the raw typed value.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'path' => ['type' => 'string', 'description' => 'Dot path under settings, e.g. "messages.maxlength".'],
            'max_field_bytes' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 4096,
                'description' => 'Cap for a large value (default 4096, max 65536); larger values return a truncation marker.',
            ],
        ],
        'required' => ['path'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'path' => ['type' => 'string'],
            'value' => ['type' => ['string', 'number', 'integer', 'boolean', 'object', 'array', 'null']],
        ],
        'required' => ['path', 'value'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_setting',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Secret paths are refused; nested secrets redacted. When truncated is true the value is JSON cut to max_field_bytes (not parseable) — raise max_field_bytes to get the full value.',
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
function voxel_get_setting(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $path = is_string($input['path'] ?? null) ? trim($input['path']) : '';
    if ($path === '') {
        return vx_invalid(__('A settings path is required.', domain: 'novamira-pro'));
    }

    // Refuse a path that targets a secret leaf directly (key-level redaction only
    // catches secrets nested inside an array value, not a scalar secret leaf).
    foreach (explode('.', $path) as $segment) {
        if (vx_is_secret_key($segment)) {
            return vx_invalid(__('That setting is a secret and cannot be read.', domain: 'novamira-pro'));
        }
    }

    /** @var mixed $value */
    $value = vx_redact_settings(\Voxel\get('settings.' . $path));

    // Progressive disclosure: cap a large value so it cannot bloat context. Small
    // scalars/arrays pass through as-is; an oversized value becomes a truncation
    // marker on its JSON form.
    $max_bytes = vx_resolve_max_field_bytes(vx_coerce_int($input['max_field_bytes'] ?? null));
    $encoded = is_string($value) ? $value : (string) wp_json_encode($value);
    if (strlen($encoded) > $max_bytes) {
        return ['path' => $path, 'value' => vx_truncate_field($encoded, $max_bytes)];
    }

    return ['path' => $path, 'value' => $value];
}
