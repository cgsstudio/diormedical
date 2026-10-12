<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricksforge;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricksforge-list-email-themes', [
    'label' => __('List Bricksforge Email Themes', domain: 'novamira-pro'),
    'description' => __(
        'Lists the saved Email Designer themes (id + name). A template binds to a theme by id (see bricksforge-edit-email-template use_theme/theme). Read-only: theme creation/design is a visual editor task.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'themes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                    ],
                ],
            ],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_list_email_themes',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use the returned id as the `theme` value when editing a template to use a theme.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function bricksforge_list_email_themes(): array
{
    $themes = [];
    foreach (brf_raw_email_themes() as $theme) {
        $themes[] = [
            'id' => brf_email_theme_id($theme),
            'name' => brf_obj_str($theme, key: 'name'),
        ];
    }

    $capped = brf_cap_list($themes);

    return brf_list_omitted_warning(['themes' => $capped['rows']], $capped['omitted'], noun: 'theme(s)');
}

wp_register_ability('novamira/bricksforge-get-email-theme', [
    'label' => __('Get Bricksforge Email Theme', domain: 'novamira-pro'),
    'description' => __(
        'Reads one saved Email Designer theme by id, returning its full stored settings (colors, typography, spacing — the exact keys depend on the Bricksforge version). Read-only.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The theme id (from bricksforge-list-email-themes).'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'theme' => ['type' => 'object', 'description' => 'The full stored theme settings.'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_get_email_theme',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Keys are the upstream theme shape; treat as opaque unless copying into a template binding.',
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
function bricksforge_get_email_theme(array $input): array|WP_Error
{
    $id = isset($input['id']) && is_string($input['id']) ? $input['id'] : '';
    foreach (brf_raw_email_themes() as $theme) {
        if (brf_email_theme_id($theme) !== $id) {
            continue;
        }
        /** @var mixed $decoded */
        $decoded = json_decode((string) wp_json_encode($theme), associative: true);

        $theme_out = is_array($decoded) ? $decoded : [];

        return brf_warn_if_large(['theme' => $theme_out], $theme_out, what: 'theme');
    }

    return brf_error(
        code: 'bricksforge_email_theme_not_found',
        message: sprintf('No Bricksforge email theme with id "%s".', $id),
        data: ['status' => 404],
    );
}
