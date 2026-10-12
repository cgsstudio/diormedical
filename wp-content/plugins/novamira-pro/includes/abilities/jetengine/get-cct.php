<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/jetengine-get-cct', [
    'label' => __('Get JetEngine CCT', domain: 'novamira-pro'),
    'description' => __(
        'Return the full native JetEngine Custom Content Type definition by slug, enriched with editable, source, and records_count. The returned shape (minus the read-only enrichments) round-trips with create-cct and edit-cct.',
        domain: 'novamira-pro',
    ),
    'category' => 'jetengine',
    'input_schema' => [
        'type' => 'object',
        'properties' => ['slug' => ['type' => 'string']],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'labels' => ['type' => 'object'],
            'is_admin' => ['type' => 'boolean'],
            'admin_capability' => ['type' => ['string', 'null']],
            'cap_type' => ['type' => ['string', 'null']],
            'db_field_data' => ['type' => 'array'],
            'editable' => ['type' => 'boolean'],
            'source' => ['type' => 'string', 'enum' => ['db', 'php']],
            'records_count' => ['type' => 'integer'],
        ],
        'required' => ['slug', 'name', 'db_field_data', 'editable', 'source', 'records_count'],
    ],
    'execute_callback' => __NAMESPACE__ . '\\jetengine_get_cct',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

/**
 * @param array{slug: string} $input
 * @return array<string, mixed>|WP_Error
 */
function jetengine_get_cct(array $input): array|WP_Error
{
    $guard = jetengine_cct_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $slug = $input['slug'];
    if ($slug === '') {
        return new WP_Error('invalid_input', __('slug is required.', domain: 'novamira-pro'));
    }

    $factory = jetengine_cct_get_factory($slug);
    if ($factory === null) {
        return new WP_Error('cct_not_found', __('CCT not found.', domain: 'novamira-pro'));
    }

    $source = jetengine_cct_source($slug) ?? 'db';

    /** @var mixed $raw_labels */
    $raw_labels = $factory->get_arg('labels', []);
    /** @var array<string, mixed> $labels */
    $labels = is_array($raw_labels) ? $raw_labels : [];

    return [
        'slug' => $slug,
        'name' => (string) $factory->get_arg('name', $slug),
        'labels' => $labels,
        'is_admin' => filter_var($factory->get_arg('is_admin', true), FILTER_VALIDATE_BOOLEAN),
        'admin_capability' => (string) $factory->get_arg('admin_capability', 'manage_options'),
        'cap_type' => (string) $factory->get_arg('cap_type', 'post'),
        // Only user-defined fields. The Factory constructor merges service
        // fields (cct_status, cct_created, cct_author_id, cct_modified, _ID,
        // cct_slug) into `$factory->fields`; surface them and edit-cct would
        // treat them as user fields being removed. Filter against
        // Manager::get_service_fields() to keep the schema-facing list clean.
        'db_field_data' => array_values(jetengine_filter_user_fields($factory->fields, $factory->args)),
        'editable' => $source === 'db',
        'source' => $source,
        'records_count' => jetengine_cct_records_count($slug),
    ];
}
