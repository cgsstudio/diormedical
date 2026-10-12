<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/breakdance-get-dynamic-data-field', [
    'label' => __('Get Breakdance Dynamic Data Field', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full configuration of a single dynamic-data field by `slug`: label, category, subcategory, returnTypes, defaultAttributes, the full `controls` tree (form-control schema agents need when binding a control to dynamic data), and `proOnly`. Repeater/group fields with separately registered children also include `subfields` entries with each child\'s slug, label, and provider type. Use list-dynamic-data-fields first to discover the slug.',
        domain: 'novamira-pro',
    ),
    'category' => 'breakdance',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Breakdance\\breakdance_get_dynamic_data_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The `controls` tree is heavy, only fetch when you actually need the binding schema. For just deciding which dynamic data to use, list-dynamic-data-fields is enough. To BIND a field, place the shortcode `[breakdance_dynamic field="<slug>"]` directly in the target control VALUE (e.g. `content.content.text`) via edit-element / set-content — never in a sibling `*_dynamic_meta` key, which the renderer ignores (it only feeds the builder UI), so an element set that way renders empty. Field controls go in `params` as a JSON object: `[breakdance_dynamic field="<slug>" params=\'{"key":"value"}\']`. AVOID the `phpreturn` field: it is a true last resort that almost never should be used. It evaluates arbitrary PHP on every render (slow, a maintenance and security liability, and it traps logic in the page instead of the CMS), and nearly every value already has a registered field slug — post fields, taxonomies, author, current user, URL/query, and ACF/Pods/JetEngine/Meta Box/ACPT custom fields. Reach for `phpreturn` (`params=\'{"code":"return …;"}\'`) ONLY when you have confirmed no registered field covers the value at all.',
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
function breakdance_get_dynamic_data_field(array $input): array|WP_Error
{
    if (!isset($input['slug']) || !is_string($input['slug']) || trim($input['slug']) === '') {
        return bd_error('bd_invalid_input', __('`slug` is required.', domain: 'novamira-pro'));
    }
    $slug = trim($input['slug']);
    if (!class_exists('Breakdance\\DynamicData\\DynamicDataController')) {
        return bd_error('bd_unavailable', __(
            'The Breakdance dynamic-data subsystem is not available.',
            domain: 'novamira-pro',
        ));
    }
    $controller = \Breakdance\DynamicData\DynamicDataController::getInstance();
    $fields = $controller->getAllFields();
    foreach ($fields as $field) {
        if (!isset($field['slug']) || (string) $field['slug'] !== $slug) {
            continue;
        }
        $subfields = bd_dd_group_subfields($controller, $fields, $slug);
        if ($subfields !== []) {
            $field['subfields'] = $subfields;
        }
        return ['success' => true, 'field' => $field];
    }
    return bd_error('bd_not_found', sprintf(__('Dynamic-data field `%s` not found.', domain: 'novamira-pro'), $slug));
}

/**
 * Project registered children for a repeater/group field. Breakdance
 * serializes dynamic fields without the provider metadata that links
 * Meta Box sub-fields to their group, so inspect the same registry's
 * public field objects and pair them with the serialized rows by index.
 *
 * @param list<array<string, mixed>> $fields
 * @return list<array{slug: string, label: string, type: string}>
 */
function bd_dd_group_subfields(object $controller, array $fields, string $slug): array
{
    $registered = bd_dd_registered_fields($controller);
    $group_id = bd_dd_registered_group_id($registered, $fields, $slug);
    if ($group_id === '') {
        return [];
    }

    $subfields = [];
    foreach ($fields as $index => $field) {
        $metadata = bd_dd_registered_field_metadata($registered[$index] ?? null);
        $subfield = bd_dd_subfield_row($field, $metadata, $group_id);
        if ($subfield === null) {
            continue;
        }
        $subfields[$subfield['slug']] = $subfield;
    }
    return array_values($subfields);
}

/**
 * Read the dynamic-data field objects from Breakdance's controller.
 *
 * @return list<mixed>
 */
function bd_dd_registered_fields(object $controller): array
{
    $controller_vars = get_object_vars($controller);
    /** @var mixed $registered */
    $registered = $controller_vars['fields'] ?? null;
    return is_array($registered) ? array_values($registered) : [];
}

/**
 * Find the provider id of the requested registered field.
 *
 * @param list<mixed> $registered
 * @param list<array<string, mixed>> $fields
 */
function bd_dd_registered_group_id(array $registered, array $fields, string $slug): string
{
    foreach ($fields as $index => $field) {
        if (!isset($field['slug']) || !is_string($field['slug']) || $field['slug'] !== $slug) {
            continue;
        }
        $metadata = bd_dd_registered_field_metadata($registered[$index] ?? null);
        if (($metadata['type'] ?? null) !== 'group') {
            return '';
        }
        /** @var mixed $id */
        $id = $metadata['id'] ?? null;
        return is_string($id) || is_int($id) ? (string) $id : '';
    }
    return '';
}

/**
 * Build one child summary when the registered field belongs to the
 * requested group.
 *
 * @param array<string, mixed> $field
 * @param array<string, mixed> $metadata
 * @return array{slug: string, label: string, type: string}|null
 */
function bd_dd_subfield_row(array $field, array $metadata, string $group_id): ?array
{
    if (($metadata['is_sub_field'] ?? false) !== true) {
        return null;
    }
    if (!isset($metadata['group_id']) || (string) $metadata['group_id'] !== $group_id) {
        return null;
    }
    $child_slug = isset($field['slug']) && is_string($field['slug']) ? $field['slug'] : '';
    if ($child_slug === '') {
        return null;
    }
    return [
        'slug' => $child_slug,
        'label' => isset($field['label']) && is_string($field['label']) ? $field['label'] : '',
        'type' => isset($metadata['type']) && is_string($metadata['type']) ? $metadata['type'] : '',
    ];
}

/**
 * Read provider metadata from a registered dynamic-data field object
 * without modifying or re-registering it.
 *
 * @return array<string, mixed>
 */
function bd_dd_registered_field_metadata(mixed $registered): array
{
    if (!is_object($registered)) {
        return [];
    }
    $vars = get_object_vars($registered);
    /** @var mixed $metadata */
    $metadata = $vars['field'] ?? null;
    if (!is_array($metadata)) {
        return [];
    }
    /** @var array<string, mixed> $metadata */
    return $metadata;
}
