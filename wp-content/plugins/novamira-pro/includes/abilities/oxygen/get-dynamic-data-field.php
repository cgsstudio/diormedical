<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-get-dynamic-data-field', [
    'label' => __('Get Oxygen Dynamic Data Field', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full configuration of a single dynamic-data field by `slug`: label, category, subcategory, returnTypes, defaultAttributes, the full `controls` tree (form-control schema agents need when binding a control to dynamic data), and `proOnly`. Use list-dynamic-data-fields first to discover the slug.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'description' => 'Dynamic-data field slug (the id returned by list-dynamic-data-fields).',
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'field' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_get_dynamic_data_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The `controls` tree is heavy — only fetch when you actually need the binding schema. To BIND a field, place the shortcode `[breakdance_dynamic field="<slug>"]` directly in the target control VALUE (e.g. `content.content.text`) via edit-element / set-content — never in a sibling `*_dynamic_meta` key, which the renderer ignores. Field controls go in `params` as a JSON object: `[breakdance_dynamic field="<slug>" params=\'{"key":"value"}\']`. AVOID `phpreturn`: it evaluates arbitrary PHP on every render (slow, security-sensitive, and it traps logic in the page instead of the CMS). Nearly every value has a registered field slug — post fields, taxonomies, author, current user, URL/query, ACF/Pods/JetEngine/Meta Box/ACPT. Reach for `phpreturn` (`params=\'{"code":"return ...;"}\'`) ONLY when confirmed no registered field covers the value.',
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
function oxygen_get_dynamic_data_field(array $input): array|WP_Error
{
    if (!isset($input['slug']) || !is_string($input['slug']) || trim($input['slug']) === '') {
        return ox_error('oxygen_invalid_input', __('`slug` is required.', domain: 'novamira-pro'));
    }
    $slug = trim($input['slug']);
    if (!class_exists('Breakdance\\DynamicData\\DynamicDataController')) {
        return ox_error('oxygen_invalid_input', __(
            'The Oxygen dynamic-data subsystem is not available.',
            domain: 'novamira-pro',
        ));
    }
    $fields = \Breakdance\DynamicData\DynamicDataController::getInstance()->getAllFields();
    foreach ($fields as $field) {
        if (isset($field['slug']) && (string) $field['slug'] === $slug) {
            return ['success' => true, 'field' => $field];
        }
    }
    return ox_error('oxygen_not_found', sprintf(
        __('Dynamic-data field `%s` not found.', domain: 'novamira-pro'),
        $slug,
    ));
}
