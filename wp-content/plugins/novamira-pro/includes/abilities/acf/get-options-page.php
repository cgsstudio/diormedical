<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-get-options-page', [
    'label' => __('Get ACF Options Page', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full payload of a single ACF-managed options page, including page_title, menu_title, parent_slug, capability, icon_url, position, redirect, post_id, autoload, advanced_configuration, and all other ACF UI options-page fields. Accepts either the ACF key (ui_options_page_<menu_slug>) or the bare menu_slug.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'ACF key (ui_options_page_<menu_slug>) or bare menu_slug.',
            ],
        ],
        'required' => ['key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'active' => ['type' => 'boolean'],
            'source' => ['type' => 'string', 'enum' => ['db', 'json', 'php']],
            'menu_title' => ['type' => 'string'],
            'parent_slug' => ['type' => 'string'],
            'capability' => ['type' => 'string'],
            'warnings' => ['type' => 'array'],
            'payload' => ['type' => 'object'],
        ],
        'required' => [
            'key',
            'slug',
            'title',
            'active',
            'source',
            'menu_title',
            'parent_slug',
            'capability',
            'warnings',
            'payload',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_get_options_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to inspect a single ACF-managed options page before editing or deleting it. The payload mirrors acf_update_internal_post_type() input shape — pass it (modified) back to acf-edit-options-page to apply changes. After reading, use acf-read-values / acf-write-values with target.type=options and target.id set to the page\'s post_id (default "options" when post_id is not explicitly set on the page). Pass the menu_slug as target.id only if you also set post_id=menu_slug on the page itself — otherwise values go to the wrong store.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{key: string} $args
 * @return array<string, mixed>|WP_Error
 */
function acf_get_options_page(array $args): array|WP_Error
{
    $guard = acf_internal_post_type_guard(kind: 'options-page');
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $raw = $args['key'];
    if ($raw === '') {
        return new WP_Error('invalid_input', 'key is required and must be a non-empty string');
    }

    $resolved = acf_internal_post_type_resolve_key($raw, kind: 'options-page');
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    if ($resolved === null) {
        return new WP_Error('not_found', "ACF options page not found: {$raw}", ['key' => $raw]);
    }

    $record = acf_get_internal_post_type($resolved, post_type: 'acf-ui-options-page');
    if (!is_array($record)) {
        return new WP_Error('not_found', "ACF options page not found: {$resolved}", ['key' => $resolved]);
    }

    $output = acf_internal_post_type_to_output_full($record, kind: 'options-page');
    $output['warnings'] = [];
    return $output;
}
