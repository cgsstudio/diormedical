<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

/**
 * Ability: Get Bricks settings (global or per-page).
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-get-settings', [
    'label' => __('Get Bricks Settings', domain: 'novamira-pro'),
    'description' => __(
        'Retrieves Bricks settings. Pass a post_id to get page-level settings, or omit it to get global settings. Global credentials (license key, API keys, tokens, passwords, secrets, passphrases, private/access keys — at any depth, including remote template connections, whatever the key\'s spelling: apiKey, api_key, access-key …) and the code-execution toggle are returned as "[redacted]" and listed in redacted_keys as dotted paths — they are never exposed through this ability.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'Post ID for page-level settings. Omit for global settings.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'scope' => ['type' => 'string', 'description' => '"global" or "page".'],
            'settings' => ['type' => 'object'],
            'post_id' => [
                'type' => 'integer',
                'description' => 'The post whose page settings these are; absent for global settings.',
            ],
            'redacted_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Global scope: dotted paths (list indexes included, e.g. remoteTemplates.0.password) whose value was replaced with "[redacted]" — credentials at any depth and the code-execution toggle.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Global settings include general Bricks configuration (post types, builder mode, performance options, etc.). Page settings include per-page overrides like custom header/footer, body classes, etc.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_get_settings(array $input): array|WP_Error
{
    /** @var int|null $post_id */
    $post_id = $input['post_id'] ?? null;

    if ($post_id !== null) {
        /** @var WP_Post|null $post */
        $post = get_post($post_id);

        if (!$post) {
            return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
        }

        /** @var array<string, mixed>|false $settings */
        $settings = get_post_meta($post_id, BRICKS_DB_PAGE_SETTINGS, single: true);

        return [
            'scope' => 'page',
            'post_id' => $post_id,
            'settings' => (object) (is_array($settings) ? $settings : []),
        ];
    }

    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_GLOBAL_SETTINGS, []);
    $settings = is_array($raw) ? $raw : [];
    $redacted = [];
    $settings = bricks_redact_settings($settings, prefix: '', redacted: $redacted);

    return [
        'scope' => 'global',
        'settings' => (object) $settings,
        'redacted_keys' => $redacted,
    ];
}

/**
 * Replaces every credential-bearing value at ANY depth with "[redacted]":
 * Bricks nests some of them (a remote template connection's password /
 * token inside a remoteTemplates row, API keys inside integration maps).
 * Redacted paths are reported dotted, list indexes included
 * (`remoteTemplates.0.password`).
 *
 * @param array<array-key, mixed> $settings
 * @param list<string>            $redacted
 * @return array<array-key, mixed>
 */
function bricks_redact_settings(array $settings, string $prefix, array &$redacted): array
{
    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        $path = $prefix === '' ? (string) $key : $prefix . '.' . $key;
        if (bricks_setting_is_credential((string) $key)) {
            $settings[$key] = '[redacted]';
            $redacted[] = $path;
            continue;
        }
        if (is_array($value)) {
            $settings[$key] = bricks_redact_settings($value, $path, $redacted);
        }
    }

    return $settings;
}

/**
 * Keys of the global settings option that hold credentials or gate code
 * execution: never returned to an agent. Shares the separator/case-blind
 * predicate of the write registry (helpers-settings.php).
 */
function bricks_setting_is_credential(string $key): bool
{
    return bricks_setting_key_is_credential($key);
}
