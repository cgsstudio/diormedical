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
 * Ability: read the Voxel site settings (read-only, secrets redacted).
 */

wp_register_ability('novamira/voxel-list-settings', [
    'label' => __('List Voxel Settings', domain: 'novamira-pro'),
    'description' => __(
        'Read the Voxel site settings (the voxel:settings configuration: addons, timeline, notifications, messages, membership, maps and other options). Read-only. Secret values (reCAPTCHA secret, Google client secret, geolocation API keys, and similar) are always redacted and returned as "__redacted__". Payment/Stripe keys are not part of this data.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => ['type' => 'object', 'additionalProperties' => true],
        ],
        'required' => ['settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_list_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. Secrets are redacted.',
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
function voxel_list_settings(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    /** @var mixed $settings */
    $settings = \Voxel\get('settings', []);
    /** @var mixed $redacted */
    $redacted = vx_redact_settings(is_array($settings) ? $settings : []);

    return ['settings' => is_array($redacted) ? $redacted : []];
}
