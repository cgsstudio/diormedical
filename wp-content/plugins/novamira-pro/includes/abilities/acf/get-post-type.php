<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-get-post-type', [
    'label' => __('Get ACF Post Type', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full payload of a single ACF-managed post type, including all register_post_type() arguments and ACF-specific keys. Accepts either the ACF key (post_type_<slug>) or the bare slug.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => [
                'type' => 'string',
                'description' => 'ACF key (post_type_<slug>) or bare slug.',
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
            'public' => ['type' => 'boolean'],
            'hierarchical' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array'],
            'payload' => ['type' => 'object'],
        ],
        'required' => ['key', 'slug', 'title', 'active', 'source', 'public', 'hierarchical', 'warnings', 'payload'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_get_post_type',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to inspect a single ACF-managed post type before editing or deleting it. The payload mirrors acf_update_internal_post_type() input shape — pass it (modified) back to acf-edit-post-type to apply changes.',
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
function acf_get_post_type(array $args): array|WP_Error
{
    $guard = acf_internal_post_type_guard();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $raw = $args['key'];
    if ($raw === '') {
        return new WP_Error('invalid_input', 'key is required and must be a non-empty string');
    }

    $resolved = acf_internal_post_type_resolve_key($raw, kind: 'post-type');
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    if ($resolved === null) {
        return new WP_Error('not_found', "ACF post type not found: {$raw}", ['key' => $raw]);
    }

    $record = acf_get_internal_post_type($resolved, post_type: 'acf-post-type');
    if (!is_array($record)) {
        return new WP_Error('not_found', "ACF post type not found: {$resolved}", ['key' => $resolved]);
    }

    $output = acf_internal_post_type_to_output_full($record, kind: 'post-type');
    $output['warnings'] = [];
    return $output;
}
