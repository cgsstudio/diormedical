<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acpt-get-field-group-schema', [
    'label' => __('Get ACPT Field Group Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the schema for one ACPT meta group (field group) identified by name. Default response: identity (id, name, label, context, priority), belongs[] rules, and box_count. Pass include_fields=true to also receive the 3-level hierarchy: meta_boxes[{id, name, label, sort, fields[{id, name, type, label, sort, required, default_value}]}]. Pass full_definition=true for each field\'s stored definition instead: also its choices (options with isDefault), advanced_options (stored values), repeater children and flexible blocks, recursively; this output can be sent back as boxes[] to novamira/acpt-edit-field-group unchanged. Add path (a box name, or box.field, box.field.child, box.field.block.field) to return only that part. For the advanced options a field type supports call novamira/acpt-get-field-type-schema.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'The meta group name (unique string identifier).',
            ],
            'include_fields' => [
                'type' => 'boolean',
                'description' => 'When true, inflates meta_boxes with their fields. Default false.',
            ],
            'full_definition' => [
                'type' => 'boolean',
                'description' => 'When true, returns every field with its stored definition at any depth (options, advanced_options, children, blocks) in the shape novamira/acpt-edit-field-group accepts. Default false.',
            ],
            'path' => [
                'type' => 'string',
                'description' => 'With full_definition: return only this box, field, repeater child, block or block field (dot path of stored names, e.g. general.rows.title), as definition.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'label' => ['type' => ['string', 'null']],
            'context' => ['type' => ['string', 'null']],
            'priority' => ['type' => ['string', 'null']],
            'box_count' => ['type' => 'integer'],
            'belongs' => ['type' => 'array'],
            'meta_boxes' => ['type' => 'array'],
            'path' => ['type' => 'string'],
            'definition' => [
                'type' => 'object',
                'description' => 'With path: {kind: box|field|block, ...stored definition}.',
            ],
        ],
        'required' => ['id', 'name', 'box_count'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_get_field_group_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Fetches one ACPT meta group by name. By default returns only identity + box_count to keep the payload lean. Pass include_fields=true to get the full 3-level hierarchy (MetaGroup → MetaBox → MetaField). Pass full_definition=true (optionally with path) when you need a field\'s stored choices, advanced options, sub-fields or blocks, e.g. to reconcile after a failed edit; that output round-trips into novamira/acpt-edit-field-group. Before creating or editing a field, call novamira/acpt-get-field-type-schema with the target type to see available advanced options.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{name: string, include_fields?: bool, full_definition?: bool, path?: string} $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_get_field_group_schema(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $name = trim($args['name']);
    if ($name === '') {
        return new WP_Error('acpt_invalid_input', __('name is required.', domain: 'novamira-pro'));
    }

    $include_fields = $args['include_fields'] ?? false;

    $rows = \ACPT\Core\Repository\MetaRepository::get(['groupName' => $name]);
    if ($rows === []) {
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: %s: field group name. */
            __('ACPT field group "%s" not found.', domain: 'novamira-pro'),
            $name,
        ));
    }

    $group = $rows[0];
    $boxes = $group->getBoxes();

    $belongs_out = [];
    foreach ($group->getBelongs() as $belong) {
        $belongs_out[] = [
            'belongs_to' => $belong->getBelongsTo(),
            'operator' => $belong->getOperator(),
            'find' => $belong->getFind(),
        ];
    }

    $out = [
        'id' => $group->getId(),
        'name' => $group->getName(),
        'label' => $group->getLabel(),
        'context' => $group->getContext(),
        'priority' => $group->getPriority(),
        'box_count' => count($boxes),
        'belongs' => $belongs_out,
    ];

    $path = trim($args['path'] ?? '');
    if (($args['full_definition'] ?? false) || $path !== '') {
        return acpt_add_full_definitions($out, $group, $path);
    }

    if ($include_fields) {
        $meta_boxes_out = [];
        foreach ($boxes as $box) {
            $fields_out = [];
            foreach ($box->getFields() as $field) {
                $fields_out[] = [
                    'id' => $field->getId(),
                    'name' => $field->getName(),
                    'type' => $field->getType(),
                    'label' => $field->getLabel(),
                    'sort' => $field->getSort(),
                    'required' => $field->isRequired(),
                    'default_value' => $field->getDefaultValue(),
                ];
            }
            $meta_boxes_out[] = [
                'id' => $box->getId(),
                'name' => $box->getName(),
                'label' => $box->getLabel(),
                'sort' => $box->getSort(),
                'fields' => $fields_out,
            ];
        }
        $out['meta_boxes'] = $meta_boxes_out;
    }

    return $out;
}

/**
 * Add the stored definitions of a group's boxes (or of the part at $path).
 *
 * @param array<string, mixed> $out
 * @return array<string, mixed>|WP_Error
 */
function acpt_add_full_definitions(
    array $out,
    \ACPT\Core\Models\Meta\MetaGroupModel $group,
    string $path,
): array|WP_Error {
    $boxes = [];
    foreach (fgn_items(acpt_existing_group_data($group)['boxes'] ?? null) as $box) {
        $boxes[] = [
            'id' => (string) ($box['id'] ?? ''),
            'name' => (string) ($box['name'] ?? ''),
            'label' => $box['label'] ?? null,
            'fields' => array_map(static fn(array $f): array => acpt_full_field_definition(
                $f,
            ), fgn_items($box['fields'] ?? null)),
        ];
    }
    if ($path === '') {
        $out['meta_boxes'] = $boxes;
        return $out;
    }
    $node = acpt_definition_at_path($boxes, $path);
    if ($node === null) {
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: 1: path, 2: field group name. */
            __('Nothing is stored at path "%1$s" in ACPT field group "%2$s".', domain: 'novamira-pro'),
            $path,
            $group->getName(),
        ));
    }
    $out['path'] = $path;
    $out['definition'] = $node;
    return $out;
}

/**
 * A field's stored definition, recursively, using only keys
 * novamira/acpt-edit-field-group accepts (so it can be sent back unchanged).
 * Empty advanced options are left out: ACPT treats them as unset.
 *
 * @param array<string, mixed> $field ACPT's full array representation of the field
 * @return array<string, mixed>
 */
function acpt_full_field_definition(array $field): array
{
    $out = [
        'id' => (string) ($field['id'] ?? ''),
        'name' => (string) ($field['name'] ?? ''),
        'type' => (string) ($field['type'] ?? ''),
        'label' => $field['label'] ?? null,
        'required' => acpt_is_truthy_flag($field['isRequired'] ?? false),
        'default_value' => $field['defaultValue'] ?? null,
    ];
    if (is_string($field['description'] ?? null) && $field['description'] !== '') {
        $out['description'] = $field['description'];
    }
    $extra = [
        'options' => acpt_full_choice_options($field),
        'advanced_options' => acpt_full_advanced_options($field),
        'children' => array_map(static fn(array $c): array => acpt_full_field_definition(
            $c,
        ), fgn_items($field['children'] ?? null)),
        'blocks' => array_map(static fn(array $b): array => acpt_full_block_definition(
            $b,
        ), fgn_items($field['blocks'] ?? null)),
    ];
    return array_merge($out, array_filter($extra, static fn(array $list): bool => $list !== []));
}

/**
 * @param array<string, mixed> $field
 * @return list<array<string, mixed>>
 */
function acpt_full_choice_options(array $field): array
{
    return array_map(static fn(array $o): array => [
        'id' => $o['id'] ?? null,
        'label' => $o['label'] ?? null,
        'value' => $o['value'] ?? null,
        'isDefault' => acpt_is_truthy_flag($o['isDefault'] ?? false),
    ], fgn_items($field['options'] ?? null));
}

/**
 * Stored advanced options with a value (an empty one is unset for ACPT).
 *
 * @param array<string, mixed> $field
 * @return list<array{key: string, value: mixed}>
 */
function acpt_full_advanced_options(array $field): array
{
    $advanced = [];
    foreach (fgn_items($field['advancedOptions'] ?? null) as $option) {
        /** @var mixed $value */
        $value = $option['value'] ?? null;
        if ($value === null || $value === '') {
            continue;
        }
        $advanced[] = ['key' => (string) ($option['key'] ?? ''), 'value' => $value];
    }
    return $advanced;
}

/**
 * @param array<string, mixed> $block
 * @return array<string, mixed>
 */
function acpt_full_block_definition(array $block): array
{
    return [
        'id' => (string) ($block['id'] ?? ''),
        'name' => (string) ($block['name'] ?? ''),
        'label' => $block['label'] ?? null,
        'fields' => array_map(static fn(array $f): array => acpt_full_field_definition(
            $f,
        ), fgn_items($block['fields'] ?? null)),
    ];
}

/**
 * The box, field, child, block or block field at a dot path of stored names.
 *
 * @param list<array<string, mixed>> $boxes full definitions
 * @return array<string, mixed>|null
 */
function acpt_definition_at_path(array $boxes, string $path): ?array
{
    $segments = explode('.', $path);
    $box_name = array_shift($segments);
    $node = null;
    foreach ($boxes as $box) {
        if ($box['name'] !== $box_name) {
            continue;
        }
        $node = ['kind' => 'box'] + $box;
    }
    foreach ($segments as $segment) {
        if ($node === null) {
            return null;
        }
        $node = acpt_definition_child($node, $segment);
    }
    return $node;
}

/**
 * The named field (or, under a flexible field, block) directly below a node.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>|null
 */
function acpt_definition_child(array $node, string $name): ?array
{
    $lists = $node['kind'] === 'field'
        ? ['field' => fgn_items($node['children'] ?? null), 'block' => fgn_items($node['blocks'] ?? null)]
        : ['field' => fgn_items($node['fields'] ?? null)];
    foreach ($lists as $kind => $items) {
        foreach ($items as $item) {
            if (($item['name'] ?? null) === $name) {
                return ['kind' => $kind] + $item;
            }
        }
    }
    return null;
}
