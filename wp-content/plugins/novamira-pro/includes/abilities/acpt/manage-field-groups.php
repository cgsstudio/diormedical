<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// acpt-create-field-group
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-create-field-group', [
    'label' => __('Create ACPT Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new ACPT meta group (field group). Required: name (unique slug-like string). Optional: label, display (standard|accordion|verticalTabs|horizontalTabs), context (normal|advanced|side), priority (default|high|low|core), belongs[] (location rules), boxes[] (initial meta boxes with fields). belongs[] items: {belongs_to: customPostType|taxonomy|optionPage|user|comment, operator: "=", find: "cpt-slug-or-taxonomy-slug"} (user and comment rules need no find). boxes[] items: {name, label?, fields[{name, type, label?, required?, default_value?}]}. Box names must be unique across the whole site: a box name another group already uses is refused. ACPT may store a new field, repeater child or block name with a numeric suffix when that name exists in another group; the response then lists every changed name in renamed[] (requested and stored box.field paths). A response is a success only when ACPT stored everything sent, under the returned (stored) group name. Otherwise it is an error: acpt_create_field_group_failed / acpt_edit_field_group_failed when the save failed (including a database error ACPT only logged), acpt_field_group_incomplete when ACPT reported success but the group, a box, field, block or advanced option value is not stored as sent. Its message ends with compact recovery data (group id and stored name, missing items with id and actual place or null, option values sent vs stored; at most 10 entries per list with long values shortened, the rest counted under omitted; "more" gives the novamira/acpt-get-field-group-schema call, with full_definition and a path, that returns everything shortened or left out); some changes may already be stored, so re-read the group before retrying.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Unique meta group name (slug-like, alphanumeric + underscore).',
            ],
            'label' => ['type' => 'string'],
            'display' => [
                'type' => 'string',
                'enum' => [
                    'standard',
                    'accordion',
                    'verticalTabs',
                    'horizontalTabs',
                    'vertical_tabs',
                    'horizontal_tabs',
                ],
                'description' => 'Display style. Default: standard. vertical_tabs / horizontal_tabs are accepted as aliases of verticalTabs / horizontalTabs.',
            ],
            'context' => [
                'type' => 'string',
                'enum' => ['normal', 'advanced', 'side'],
                'description' => 'Meta box context. Default: advanced.',
            ],
            'priority' => [
                'type' => 'string',
                'enum' => ['default', 'high', 'low', 'core'],
                'description' => 'Meta box priority. Default: default.',
            ],
            'belongs' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'belongs_to' => [
                            'type' => 'string',
                            'description' => 'ACPT MetaTypes value: customPostType|taxonomy|optionPage|user|comment.',
                        ],
                        'operator' => ['type' => 'string', 'description' => 'Default "="'],
                        'find' => [
                            'type' => 'string',
                            'description' => 'CPT slug, taxonomy slug, or option page menu_slug.',
                        ],
                    ],
                    'required' => ['belongs_to'],
                    'additionalProperties' => true,
                ],
                'description' => 'Location rules (where this group appears).',
            ],
            'boxes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => [
                            'type' => 'string',
                            'description' => 'Box name. Must not be used by a box of any other field group. Stored values are keyed by {box}_{field}.',
                        ],
                        'label' => ['type' => 'string'],
                        'fields' => ['type' => 'array'],
                    ],
                    'required' => ['name'],
                    'additionalProperties' => true,
                ],
                'description' => 'Initial meta boxes. Each box can include fields[].',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'name' => ['type' => 'string', 'description' => 'The group name as ACPT stored it.'],
            'requested_name' => [
                'type' => 'string',
                'description' => 'Only when ACPT stored the group under another name than requested.',
            ],
            'renamed' => [
                'type' => 'array',
                'description' => 'Names ACPT stored differently from the request, as box.field paths (box.field.child, box.field.block.field for nested items).',
                'items' => [
                    'type' => 'object',
                    'properties' => ['requested' => ['type' => 'string'], 'stored' => ['type' => 'string']],
                ],
            ],
            'note' => ['type' => 'string'],
        ],
        'required' => ['success', 'name'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_create_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates an ACPT meta group. Pass belongs[] to attach to a CPT/taxonomy/option page immediately. Each box in boxes[] can include fields[] with name and type (see novamira/acpt-get-field-type-schema for valid types and option keys). Pick box names no other group uses (prefix them with the group, e.g. product_general): a taken box name is refused with acpt_box_name_taken. When the response has renamed[], use the stored names from then on. If you omit boxes[], the group is created empty and you can add boxes/fields later via novamira/acpt-edit-field-group.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// acpt-edit-field-group
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-edit-field-group', [
    'label' => __('Edit ACPT Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing ACPT meta group (PATCH for top-level fields; REPLACE for belongs[] and boxes[]). Identify by name. Passing boxes[] replaces the entire box/field structure — include all boxes and fields you want to keep; boxes and fields are matched to the existing ones by id, or by name within this group. Each sent field, at any depth (repeater children and block fields included), is merged over the stored field: a key you omit (options, children, blocks, advanced_options, …) keeps its stored value, a key you send replaces it, an empty list included (advanced_options: [] clears every advanced option, options: [] removes all choices). Advanced options sent as top-level field keys (e.g. maximum_blocks) only set those keys. Siblings need distinct names. Ids of fields, sub-fields and blocks must belong to this group. To move a field between the top level, a repeater and a block, send it with its id at its new place only; stored values do not follow a move (each place stores them differently). Moving a field into a repeater or block is allowed; taking one out of a repeater or block (to the top level, or between a repeater and a block) is always refused before anything is written (acpt_field_move_unsupported): send it at its new place without its id (a new field) and leave the old one out, which deletes it. A response is a success only when ACPT stored everything sent, under the returned (stored) group name. Otherwise it is an error: acpt_create_field_group_failed / acpt_edit_field_group_failed when the save failed (including a database error ACPT only logged), acpt_field_group_incomplete when ACPT reported success but the group, a box, field, block or advanced option value is not stored as sent. Its message ends with compact recovery data (group id and stored name, missing items with id and actual place or null, option values sent vs stored; at most 10 entries per list with long values shortened, the rest counted under omitted; "more" gives the novamira/acpt-get-field-group-schema call, with full_definition and a path, that returns everything shortened or left out); some changes may already be stored, so re-read the group before retrying. A NEW box name must not be used by any other group. The response lists every name ACPT stored differently in renamed[]. Uses ACPT\'s SaveMetaGroupCommand internally so all hooks fire and orphans are cleaned up.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'The meta group name to edit.',
            ],
            'new_name' => ['type' => 'string', 'description' => 'Rename the group to this name.'],
            'label' => ['type' => 'string'],
            'display' => [
                'type' => 'string',
                'enum' => [
                    'standard',
                    'accordion',
                    'verticalTabs',
                    'horizontalTabs',
                    'vertical_tabs',
                    'horizontal_tabs',
                ],
                'description' => 'vertical_tabs / horizontal_tabs are accepted as aliases of verticalTabs / horizontalTabs.',
            ],
            'context' => [
                'type' => 'string',
                'enum' => ['normal', 'advanced', 'side'],
            ],
            'priority' => [
                'type' => 'string',
                'enum' => ['default', 'high', 'low', 'core'],
            ],
            'belongs' => [
                'type' => 'array',
                'description' => 'Replaces the entire belongs/location-rules array when provided.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'belongs_to' => ['type' => 'string'],
                        'operator' => ['type' => 'string'],
                        'find' => ['type' => 'string'],
                    ],
                    'required' => ['belongs_to'],
                    'additionalProperties' => true,
                ],
            ],
            'boxes' => [
                'type' => 'array',
                'description' => 'Replaces the entire box/field structure when provided. Include all boxes and fields you want to keep.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'fields' => ['type' => 'array'],
                    ],
                    'required' => ['name'],
                    'additionalProperties' => true,
                ],
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'name' => ['type' => 'string', 'description' => 'The group name as ACPT stored it.'],
            'requested_name' => [
                'type' => 'string',
                'description' => 'Only when ACPT stored the group under another name than requested.',
            ],
            'renamed' => [
                'type' => 'array',
                'description' => 'Names ACPT stored differently from the request, as box.field paths (box.field.child, box.field.block.field for nested items).',
                'items' => [
                    'type' => 'object',
                    'properties' => ['requested' => ['type' => 'string'], 'stored' => ['type' => 'string']],
                ],
            ],
            'note' => ['type' => 'string'],
        ],
        'required' => ['success', 'name'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_edit_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'PATCH-style edit. Pass only the top-level fields you want to change. boxes[] is REPLACE for the list of boxes and fields — if you pass it, include all boxes and fields you want to keep (fetch them with novamira/acpt-get-field-group-schema full_definition=true and send them back under boxes[]: that output keeps choices, defaults, advanced options, sub-fields and blocks); omit to leave the structure unchanged. Inside boxes[], each field at any depth is merged over its stored definition: omitted keys keep their stored value, sent keys replace it (send an empty list to remove choices, sub-fields or advanced options; send options with isDefault false to remove a default choice). Two siblings with the same name are refused (acpt_duplicate_sibling_name), as is one id sent twice (acpt_duplicate_item). A new box name already used by another group is refused with acpt_box_name_taken. belongs[] is REPLACE — omit to leave location rules unchanged. When the response has renamed[], use the stored names from then on.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// acpt-delete-field-group
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-delete-field-group', [
    'label' => __('Delete ACPT Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes an ACPT meta group and all its boxes and fields. Field value data stored in wp_postmeta / wp_termmeta / etc. is NOT automatically deleted. Idempotent: deleting a non-existent name returns deleted=false.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'The meta group name to delete.',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'name' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
        ],
        'required' => ['deleted', 'name'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_delete_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removes the ACPT meta group and all its boxes/fields. Stored field values are NOT deleted from the meta tables — use the corresponding WP meta delete functions if you want to clean up stored data.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// Callbacks
// ---------------------------------------------------------------------------

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_create_field_group(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $name = trim((string) ($args['name'] ?? ''));
    if ($name === '') {
        return new WP_Error('acpt_invalid_input', __('name is required.', domain: 'novamira-pro'));
    }

    // Check for existing group with same name.
    $existing = \ACPT\Core\Repository\MetaRepository::get(['groupName' => $name]);
    if ($existing !== []) {
        return new WP_Error('acpt_already_exists', sprintf(
            /* translators: %s: field group name. */
            __('ACPT field group "%s" already exists.', domain: 'novamira-pro'),
            $name,
        ));
    }

    $payload = acpt_build_group_payload($args, $name);
    $boxes = acpt_prepare_group_boxes($payload['boxes'], group: null, group_name: $name);
    if ($boxes instanceof WP_Error) {
        return $boxes;
    }
    $payload['boxes'] = $boxes;

    return acpt_finish_save(
        acpt_save_meta_group_safely($payload),
        $payload['id'],
        $name,
        $boxes,
        failed_code: 'acpt_create_field_group_failed',
    );
}

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_edit_field_group(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $name = trim((string) ($args['name'] ?? ''));
    if ($name === '') {
        return new WP_Error('acpt_invalid_input', __('name is required.', domain: 'novamira-pro'));
    }

    $rows = \ACPT\Core\Repository\MetaRepository::get(['groupName' => $name]);
    if ($rows === []) {
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: %s: field group name. */
            __('ACPT field group "%s" not found.', domain: 'novamira-pro'),
            $name,
        ));
    }

    $group = $rows[0];

    // Build merged payload — use existing values for anything not supplied.
    $existing_data = acpt_existing_group_data($group);

    $payload = acpt_merge_group_payload($args, $existing_data);
    if (array_key_exists('boxes', $args)) {
        $payload['boxes'] = acpt_merge_stored_fields(
            fgn_items($payload['boxes']),
            fgn_items($args['boxes']),
            acpt_stored_tree_index(fgn_items($existing_data['boxes'] ?? null)),
        );
    }
    $boxes = acpt_prepare_group_boxes(fgn_items($payload['boxes'] ?? null), $group, $group->getName());
    if ($boxes instanceof WP_Error) {
        return $boxes;
    }
    $payload['boxes'] = $boxes;

    // ACPT cannot be relied on to clear a field's repeater / block reference
    // (some versions keep the field at its old place too), so moves out of a
    // repeater or block are refused before anything is written.
    $leaving = acpt_fields_leaving_parent($boxes, $group);
    if ($leaving !== []) {
        return new WP_Error('acpt_field_move_unsupported', sprintf(
            /* translators: %s: comma-separated box.field paths. */
            __(
                'A field cannot be taken out of a repeater or flexible block (to the top level, or into another repeater or block kind): ACPT does not reliably clear its old place. Nothing was saved. Fields affected: %s. Instead, send the field at its new place without its id (a new field) and leave the old one out, which deletes it. Stored values do not follow.',
                domain: 'novamira-pro',
            ),
            implode(', ', $leaving),
        ));
    }

    $final_name = isset($args['new_name']) ? trim((string) $args['new_name']) : $name;
    $final_name = $final_name !== '' ? $final_name : $name;

    return acpt_finish_save(
        acpt_save_meta_group_safely($payload),
        $group->getId(),
        $final_name,
        $boxes,
        failed_code: 'acpt_edit_field_group_failed',
    );
}

/**
 * @param array{name: string} $args
 * @return array{deleted: bool, name: string, reason?: string}|WP_Error
 */
function acpt_delete_field_group(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $name = trim($args['name']);
    if ($name === '') {
        return new WP_Error('acpt_invalid_input', __('name is required.', domain: 'novamira-pro'));
    }

    $rows = \ACPT\Core\Repository\MetaRepository::get(['groupName' => $name]);
    if ($rows === []) {
        return ['deleted' => false, 'name' => $name, 'reason' => 'not_found'];
    }

    $group_id = $rows[0]->getId();
    $result = acpt_handle_api_result(static fn() => \ACPT\Core\Repository\MetaRepository::deleteMetaGroup($group_id));

    if (!$result['success']) {
        return new WP_Error(
            'acpt_delete_field_group_failed',
            $result['error'] ?? __('Failed to delete field group.', domain: 'novamira-pro'),
        );
    }

    return ['deleted' => true, 'name' => $name];
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/**
 * ACPT stores the tab layouts in camelCase and rejects anything else, so the
 * long-documented snake_case spellings are mapped to the stored values.
 */
function acpt_normalize_display(mixed $display): mixed
{
    return match ($display) {
        'vertical_tabs' => 'verticalTabs',
        'horizontal_tabs' => 'horizontalTabs',
        default => $display,
    };
}

/**
 * Build a SaveMetaGroupCommand-compatible payload from create $args.
 *
 * @param array<string, mixed> $args
 * The group gets a pinned id so it can be read back by id: ACPT normalizes
 * (and may suffix) the name it stores.
 *
 * @return array{id: string, name: string, label: mixed, display: mixed, context: mixed, priority: mixed, belongs: list<array<string, mixed>>, boxes: list<array<string, mixed>>}
 */
function acpt_build_group_payload(array $args, string $name): array
{
    /** @var list<array<string, mixed>> $belongs_raw */
    $belongs_raw = is_array($args['belongs'] ?? null) ? array_values($args['belongs']) : [];
    $boxes_raw = is_array($args['boxes'] ?? null) ? $args['boxes'] : [];

    $belongs = acpt_normalize_belongs($belongs_raw);

    return [
        'id' => acpt_uuid_v4(),
        'name' => $name,
        'label' => $args['label'] ?? null,
        'display' => acpt_normalize_display($args['display'] ?? 'standard'),
        'context' => $args['context'] ?? 'advanced',
        'priority' => $args['priority'] ?? 'default',
        'belongs' => $belongs,
        'boxes' => acpt_normalize_boxes($boxes_raw),
    ];
}

/**
 * Merge $args over $existing_data, building the final SaveMetaGroupCommand payload.
 *
 * @param array<string, mixed> $args
 * @param array<string, mixed> $existing_data
 * @return array<string, mixed>
 */
function acpt_merge_group_payload(array $args, array $existing_data): array
{
    $payload = $existing_data;

    if (isset($args['new_name'])) {
        $payload['new_name'] = trim((string) $args['new_name']);
    }
    if (isset($args['label'])) {
        $payload['label'] = $args['label'];
    }
    if (isset($args['display'])) {
        $payload['display'] = acpt_normalize_display($args['display']);
    }
    if (isset($args['context'])) {
        $payload['context'] = $args['context'];
    }
    if (isset($args['priority'])) {
        $payload['priority'] = $args['priority'];
    }
    if (array_key_exists('belongs', $args)) {
        /** @var list<array<string, mixed>> $belongs_raw */
        $belongs_raw = is_array($args['belongs']) ? $args['belongs'] : [];
        $payload['belongs'] = acpt_normalize_belongs($belongs_raw);
    }
    if (array_key_exists('boxes', $args)) {
        $payload['boxes'] = is_array($args['boxes']) ? acpt_normalize_boxes($args['boxes']) : [];
    }

    return $payload;
}

/**
 * isDefault of every choice option of a group, by option id. ACPT's own
 * array representation leaves it out, so re-saving that representation
 * would reset every default (or be refused outright by some versions).
 *
 * @return array<string, bool>
 */
function acpt_option_defaults(\ACPT\Core\Models\Meta\MetaGroupModel $group): array
{
    $defaults = [];
    $fields = [];
    foreach ($group->getBoxes() as $box) {
        array_push($fields, ...$box->getFields());
    }
    while ($fields !== []) {
        $field = array_pop($fields);
        foreach ($field->getOptions() as $option) {
            $defaults[$option->getId()] = $option->isDefault();
        }
        array_push($fields, ...$field->getChildren());
        foreach ($field->getBlocks() as $block) {
            array_push($fields, ...$block->getFields());
        }
    }
    return $defaults;
}

/**
 * ACPT's full array representation of a group, with option defaults restored.
 *
 * @return array<string, mixed>
 */
function acpt_existing_group_data(\ACPT\Core\Models\Meta\MetaGroupModel $group): array
{
    $defaults = acpt_option_defaults($group);
    $data = $group->arrayRepresentation('full');
    /** @var mixed $value */
    foreach ($data as $key => $value) {
        if (!is_array($value)) {
            continue;
        }
        $data[$key] = acpt_restore_option_defaults($value, $defaults);
    }
    return $data;
}

/**
 * Put isDefault back on every option of an array representation.
 *
 * @param array<array-key, mixed> $data
 * @param array<string, bool> $defaults
 * @return array<array-key, mixed>
 */
function acpt_restore_option_defaults(array $data, array $defaults): array
{
    /** @var mixed $value */
    foreach ($data as $key => $value) {
        if (!is_array($value)) {
            continue;
        }
        if ($key === 'options') {
            $data[$key] = acpt_restore_option_list($value, $defaults);
            continue;
        }
        $data[$key] = acpt_restore_option_defaults($value, $defaults);
    }
    return $data;
}

/**
 * @param array<array-key, mixed> $options
 * @param array<string, bool> $defaults
 * @return array<array-key, mixed>
 */
function acpt_restore_option_list(array $options, array $defaults): array
{
    /** @var mixed $option */
    foreach ($options as $i => $option) {
        if (!is_array($option) || isset($option['isDefault'])) {
            continue;
        }
        $id = is_string($option['id'] ?? null) ? $option['id'] : '';
        $option['isDefault'] = $defaults[$id] ?? false;
        $options[$i] = $option;
    }
    return $options;
}

/**
 * Index of a group's stored array representation at every depth: fields
 * (top-level, repeater children, block fields) and blocks by id; top-level
 * fields by box name + name; children and block fields by parent id + name;
 * blocks by field id + name.
 *
 * @param list<array<string, mixed>> $stored_boxes ACPT's full array representation
 * @return array{fields: array<string, array<string, mixed>>, blocks: array<string, array<string, mixed>>, boxes: array<string, array<string, array<string, mixed>>>, members: array<string, array<string, array<string, mixed>>>, block_names: array<string, array<string, array<string, mixed>>>}
 */
function acpt_stored_tree_index(array $stored_boxes): array
{
    $index = ['fields' => [], 'blocks' => [], 'boxes' => [], 'members' => [], 'block_names' => []];
    $queue = [];
    foreach ($stored_boxes as $stored_box) {
        $box_name = (string) ($stored_box['name'] ?? '');
        foreach (fgn_items($stored_box['fields'] ?? null) as $field) {
            $index['boxes'][$box_name][(string) ($field['name'] ?? '')] = $field;
            $queue[] = $field;
        }
    }
    while ($queue !== []) {
        array_push($queue, ...acpt_index_stored_field(array_pop($queue), $index));
    }
    return $index;
}

/**
 * Add one stored field to the tree index; returns its nested fields.
 *
 * @param array<string, mixed> $field
 * @param array{fields: array<string, array<string, mixed>>, blocks: array<string, array<string, mixed>>, boxes: array<string, array<string, array<string, mixed>>>, members: array<string, array<string, array<string, mixed>>>, block_names: array<string, array<string, array<string, mixed>>>} $index
 * @return list<array<string, mixed>>
 */
function acpt_index_stored_field(array $field, array &$index): array
{
    $nested = [];
    $id = (string) ($field['id'] ?? '');
    $index['fields'][$id] = $field;
    foreach (fgn_items($field['children'] ?? null) as $child) {
        $index['members'][$id][(string) ($child['name'] ?? '')] = $child;
        $nested[] = $child;
    }
    foreach (fgn_items($field['blocks'] ?? null) as $block) {
        $block_id = (string) ($block['id'] ?? '');
        $index['blocks'][$block_id] = $block;
        $index['block_names'][$id][(string) ($block['name'] ?? '')] = $block;
        foreach (fgn_items($block['fields'] ?? null) as $block_field) {
            $index['members'][$block_id][(string) ($block_field['name'] ?? '')] = $block_field;
            $nested[] = $block_field;
        }
    }
    return $nested;
}

/**
 * On edit, merge each sent field, at any depth (top-level, repeater child,
 * block field), over the stored definition of the same field, matched by id
 * anywhere in the group or by name under the same parent. Keys the agent
 * leaves out (options, children, blocks, advanced options, validation…)
 * keep their stored value instead of being wiped by the REPLACE of boxes[];
 * a key the agent sends replaces the stored value, an empty list included.
 * The raw request (aligned with the normalized one: both keep array entries
 * only) tells which advanced options lists were sent explicitly.
 *
 * @param list<array<string, mixed>> $boxes normalized request boxes
 * @param list<array<string, mixed>> $raw_boxes request boxes as sent
 * @param array{fields: array<string, array<string, mixed>>, blocks: array<string, array<string, mixed>>, boxes: array<string, array<string, array<string, mixed>>>, members: array<string, array<string, array<string, mixed>>>, block_names: array<string, array<string, array<string, mixed>>>} $stored
 * @return list<array<string, mixed>>
 */
function acpt_merge_stored_fields(array $boxes, array $raw_boxes, array $stored): array
{
    foreach ($boxes as $b => $box) {
        $boxes[$b]['fields'] = acpt_merge_field_list(
            fgn_items($box['fields'] ?? null),
            fgn_items($raw_boxes[$b]['fields'] ?? null),
            $stored['boxes'][fgn_slug((string) ($box['name'] ?? ''))] ?? [],
            $stored,
        );
    }
    return $boxes;
}

/**
 * @param list<array<string, mixed>> $fields normalized
 * @param list<array<string, mixed>> $raw_fields as sent, same positions
 * @param array<string, array<string, mixed>> $siblings stored fields under the same parent, by name
 * @param array{fields: array<string, array<string, mixed>>, blocks: array<string, array<string, mixed>>, boxes: array<string, array<string, array<string, mixed>>>, members: array<string, array<string, array<string, mixed>>>, block_names: array<string, array<string, array<string, mixed>>>} $stored
 * @return list<array<string, mixed>>
 */
function acpt_merge_field_list(array $fields, array $raw_fields, array $siblings, array $stored): array
{
    foreach ($fields as $i => $field) {
        $raw = $raw_fields[$i] ?? [];
        $field = acpt_camel_field_keys($field);
        $match =
            $stored['fields'][(string) ($field['id'] ?? '')] ?? $siblings[fgn_slug((string) ($field['name'] ?? ''))]
                ?? null;
        if ($match !== null) {
            $explicit = array_key_exists('advancedOptions', $raw) || array_key_exists('advanced_options', $raw);
            $field = $explicit ? acpt_replace_stored_field($match, $field) : acpt_merge_stored_field($match, $field);
        }
        $fields[$i] = acpt_merge_nested_fields($field, $raw, $stored);
    }
    return $fields;
}

/**
 * Recurse into the children and blocks the request sent for a field. Lists
 * the request left out already hold the stored ones from the merge.
 *
 * @param array<string, mixed> $field merged field
 * @param array<string, mixed> $raw the field as sent
 * @param array{fields: array<string, array<string, mixed>>, blocks: array<string, array<string, mixed>>, boxes: array<string, array<string, array<string, mixed>>>, members: array<string, array<string, array<string, mixed>>>, block_names: array<string, array<string, array<string, mixed>>>} $stored
 * @return array<string, mixed>
 */
function acpt_merge_nested_fields(array $field, array $raw, array $stored): array
{
    $id = (string) ($field['id'] ?? '');
    if (array_key_exists('children', $raw)) {
        $field['children'] = acpt_merge_field_list(
            fgn_items($field['children'] ?? null),
            fgn_items($raw['children']),
            $stored['members'][$id] ?? [],
            $stored,
        );
    }
    if (array_key_exists('blocks', $raw)) {
        $field['blocks'] = acpt_merge_block_list(
            fgn_items($field['blocks'] ?? null),
            fgn_items($raw['blocks']),
            $id,
            $stored,
        );
    }
    return $field;
}

/**
 * Merge sent flexible blocks over the stored blocks (by id, or by name under
 * the same field), then their fields.
 *
 * @param list<array<string, mixed>> $blocks normalized
 * @param list<array<string, mixed>> $raw_blocks as sent, same positions
 * @param array{fields: array<string, array<string, mixed>>, blocks: array<string, array<string, mixed>>, boxes: array<string, array<string, array<string, mixed>>>, members: array<string, array<string, array<string, mixed>>>, block_names: array<string, array<string, array<string, mixed>>>} $stored
 * @return list<array<string, mixed>>
 */
function acpt_merge_block_list(array $blocks, array $raw_blocks, string $field_id, array $stored): array
{
    foreach ($blocks as $k => $block) {
        $raw_block = $raw_blocks[$k] ?? [];
        $name = fgn_slug((string) ($block['name'] ?? ''));
        $match = $stored['blocks'][(string) ($block['id'] ?? '')] ?? $stored['block_names'][$field_id][$name] ?? null;
        if ($match !== null) {
            // The normalizer defaults a missing label to the name; keep the stored one instead.
            $sent = array_key_exists('label', $raw_block) ? $block : array_diff_key($block, ['label' => true]);
            $block = array_merge($match, $sent);
        }
        if (array_key_exists('fields', $raw_block)) {
            $block['fields'] = acpt_merge_field_list(
                fgn_items($block['fields'] ?? null),
                fgn_items($raw_block['fields']),
                $stored['members'][(string) ($block['id'] ?? '')] ?? [],
                $stored,
            );
        }
        $blocks[$k] = $block;
    }
    return $blocks;
}

/**
 * Merge a sent field that carries no explicit advanced options list over its
 * stored definition: option keys sent as top-level field keys are set one by
 * one over the stored options, the others are kept.
 *
 * @param array<string, mixed> $stored
 * @param array<string, mixed> $field
 * @return array<string, mixed>
 */
function acpt_merge_stored_field(array $stored, array $field): array
{
    $stored_options = acpt_existing_advanced_blob($stored);
    $merged = array_merge($stored, $field);
    $merged['advancedOptions'] = acpt_advanced_by_key($stored_options, acpt_keep_advanced_ids(
        acpt_existing_advanced_blob($field),
        $stored_options,
    ));
    return $merged;
}

/**
 * Merge a sent field that carries an explicit advanced options list (even an
 * empty one) over its stored definition: that list replaces the stored
 * options. ACPT never deletes an advanced option row on save, so a stored key
 * missing from the list is sent with an empty value, the value ACPT itself
 * stores for an unset option.
 *
 * @param array<string, mixed> $stored
 * @param array<string, mixed> $field
 * @return array<string, mixed>
 */
function acpt_replace_stored_field(array $stored, array $field): array
{
    $stored_options = acpt_existing_advanced_blob($stored);
    $sent = acpt_keep_advanced_ids(acpt_existing_advanced_blob($field), $stored_options);
    $sent_keys = array_flip(array_map(static fn(array $o): string => (string) ($o['key'] ?? ''), $sent));
    $cleared = [];
    foreach ($stored_options as $option) {
        if (isset($sent_keys[(string) ($option['key'] ?? '')])) {
            continue;
        }
        $option['value'] = '';
        $cleared[] = $option;
    }
    $merged = array_merge($stored, $field);
    $merged['advancedOptions'] = acpt_advanced_by_key($cleared, $sent);
    return $merged;
}

/**
 * Give each sent advanced option the id of the stored option with the same
 * key. ACPT upserts advanced options by id, so an option re-sent without it
 * would be stored as a second row for the same key.
 *
 * @param list<array<string, mixed>> $sent
 * @param list<array<string, mixed>> $stored
 * @return list<array<string, mixed>>
 */
function acpt_keep_advanced_ids(array $sent, array $stored): array
{
    $ids = [];
    foreach ($stored as $option) {
        if (!is_string($option['id'] ?? null)) {
            continue;
        }
        $ids[(string) ($option['key'] ?? '')] = $option['id'];
    }
    foreach ($sent as $i => $option) {
        $id = $ids[(string) ($option['key'] ?? '')] ?? null;
        if ($id === null || isset($option['id'])) {
            continue;
        }
        $sent[$i]['id'] = $id;
    }
    return $sent;
}

/**
 * Concatenate advanced option lists, keeping one entry per key (the last).
 *
 * @param list<array<string, mixed>> ...$lists
 * @return list<array<string, mixed>>
 */
function acpt_advanced_by_key(array ...$lists): array
{
    $by_key = [];
    foreach ($lists as $options) {
        foreach ($options as $option) {
            $by_key[(string) ($option['key'] ?? '')] = $option;
        }
    }
    return array_values($by_key);
}

/**
 * Rename the snake_case aliases ACPT accepts to the camelCase keys of its
 * own representation, so a sent value overrides the stored one on merge.
 *
 * @param array<string, mixed> $field
 * @return array<string, mixed>
 */
function acpt_camel_field_keys(array $field): array
{
    static $aliases = [
        'default_value' => 'defaultValue',
        'is_required' => 'isRequired',
        'show_in_archive' => 'showInArchive',
        'advanced_options' => 'advancedOptions',
        'validation_rules' => 'validationRules',
        'visibility_conditions' => 'visibilityConditions',
        'quick_edit' => 'quickEdit',
        'filterable_in_admin' => 'filterableInAdmin',
        'cloned_fields' => 'clonedFields',
    ];
    foreach ($aliases as $snake => $camel) {
        if (!array_key_exists($snake, $field)) {
            continue;
        }
        /** @var mixed $value */
        $value = $field[$snake];
        $field[$camel] = $value;
        unset($field[$snake]);
    }
    return $field;
}

/**
 * Normalize the belongs array to the shape SaveMetaGroupCommand expects.
 *
 * Accepts both snake_case (belongs_to) and camelCase (belongsTo) from agents.
 *
 * @param list<array<string, mixed>> $belongs_raw
 * @return list<array<string, mixed>>
 */
function acpt_normalize_belongs(array $belongs_raw): array
{
    $result = [];
    foreach ($belongs_raw as $b) {
        /** @var mixed $belongs_to_raw */
        $belongs_to_raw = $b['belongs_to'] ?? $b['belongsTo'] ?? null;
        if (!is_string($belongs_to_raw) || $belongs_to_raw === '') {
            continue;
        }
        $result[] = [
            'belongsTo' => $belongs_to_raw,
            'operator' => (string) ($b['operator'] ?? '='),
            'find' => $b['find'] ?? null,
            'logic' => $b['logic'] ?? null,
        ];
    }
    return $result;
}

/**
 * Normalize the boxes[] array: for each field, move unknown top-level keys
 * into the advancedOptions blob so agents can pass schema-declared option
 * keys at the top level without ACPT silently dropping the whole field.
 *
 * @param list<array<string, mixed>>|array<int|string, mixed> $boxes_raw
 * @return list<array<string, mixed>>
 */
function acpt_normalize_boxes(array $boxes_raw): array
{
    $result = [];
    /** @var mixed $box */
    foreach ($boxes_raw as $box) {
        if (!is_array($box)) {
            continue;
        }
        /** @var array<string, mixed> $box_out */
        $box_out = $box;
        /** @var mixed $box_fields */
        $box_fields = $box_out['fields'] ?? null;
        if (is_array($box_fields)) {
            $box_out['fields'] = acpt_normalize_fields($box_fields);
        }
        $result[] = $box_out;
    }
    return $result;
}

/**
 * Normalize a list of field definitions. Recurses into children and blocks.
 *
 * @param list<array<string, mixed>>|array<int|string, mixed> $fields_raw
 * @return list<array<string, mixed>>
 */
function acpt_normalize_fields(array $fields_raw): array
{
    $result = [];
    /** @var mixed $field */
    foreach ($fields_raw as $field) {
        if (!is_array($field)) {
            continue;
        }
        /** @var array<string, mixed> $field */
        $result[] = acpt_normalize_field($field);
    }
    return $result;
}

/**
 * Read an existing advancedOptions blob from a partially-normalized field,
 * accepting both camelCase and snake_case keys.
 *
 * @param array<string, mixed> $out
 * @return list<array<string, mixed>>
 */
function acpt_existing_advanced_blob(array $out): array
{
    /** @var mixed $raw */
    $raw = $out['advancedOptions'] ?? $out['advanced_options'] ?? null;
    if (!is_array($raw)) {
        return [];
    }
    $result = [];
    /** @var mixed $entry */
    foreach (array_values($raw) as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        /** @var array<string, mixed> $entry */
        $result[] = $entry;
    }
    return $result;
}

/**
 * Move any top-level field keys that ACPT does not recognize into the
 * advancedOptions blob — keeping known structural keys (name, type,
 * options, children, blocks, etc.) in place.
 *
 * @param array<string, mixed> $field
 * @return array<string, mixed>
 */
function acpt_normalize_field(array $field): array
{
    // Keys ACPT MetaFieldModel::fullHydrateFromArray reads at top level.
    // Anything else gets wrapped into advancedOptions to survive hydration.
    static $known = [
        'id',
        'name',
        'type',
        'label',
        'sort',
        'defaultValue',
        'default_value',
        'showInArchive',
        'show_in_archive',
        'isRequired',
        'is_required',
        'required',
        'description',
        'blockId',
        'block_id',
        'parentId',
        'parent_id',
        'quickEdit',
        'quick_edit',
        'filterableInAdmin',
        'filterable_in_admin',
        'options',
        'advancedOptions',
        'advanced_options',
        'validationRules',
        'validation_rules',
        'visibilityConditions',
        'visibility_conditions',
        'permissions',
        'children',
        'blocks',
        'relations',
        'clonedFields',
        'cloned_fields',
    ];

    $known_map = array_flip($known);

    $extra_advanced = [];
    $out = [];
    /** @var mixed $value */
    foreach ($field as $key => $value) {
        if (isset($known_map[$key])) {
            $out[$key] = $value;
            continue;
        }
        $extra_advanced[] = ['key' => $key, 'value' => $value];
    }

    if ($extra_advanced !== []) {
        $out['advancedOptions'] = acpt_advanced_by_key(acpt_existing_advanced_blob($out), $extra_advanced);
        unset($out['advanced_options']);
    }

    $out = acpt_normalize_field_flags($out);

    /** @var mixed $children_raw */
    $children_raw = $out['children'] ?? null;
    if (is_array($children_raw)) {
        $out['children'] = acpt_normalize_fields($children_raw);
    }

    /** @var mixed $blocks_raw */
    $blocks_raw = $out['blocks'] ?? null;
    if (is_array($blocks_raw)) {
        $blocks_out = [];
        /** @var mixed $block */
        foreach ($blocks_raw as $block) {
            if (!is_array($block)) {
                continue;
            }
            /** @var mixed $block_fields */
            $block_fields = $block['fields'] ?? null;
            if (is_array($block_fields)) {
                $block['fields'] = acpt_normalize_fields($block_fields);
            }
            // Some ACPT versions fatal on a block without a label.
            if (!isset($block['label']) || !is_string($block['label']) || $block['label'] === '') {
                $block['label'] = (string) ($block['name'] ?? '');
            }
            $blocks_out[] = $block;
        }
        $out['blocks'] = $blocks_out;
    }

    return $out;
}

/**
 * A boolean flag as agents send it: true, 1, "1" or "true".
 */
function acpt_is_truthy_flag(mixed $value): bool
{
    return in_array($value, [true, 1, '1', 'true'], strict: true);
}

/**
 * Map `required` to the key ACPT reads (isRequired; a plain `required` is
 * ignored) and give every choice option the boolean isDefault ACPT expects.
 *
 * @param array<string, mixed> $out
 * @return array<string, mixed>
 */
function acpt_normalize_field_flags(array $out): array
{
    if (array_key_exists('required', $out)) {
        if (!isset($out['isRequired']) && !isset($out['is_required'])) {
            $out['isRequired'] = acpt_is_truthy_flag($out['required']);
        }
        unset($out['required']);
    }

    /** @var mixed $options_raw */
    $options_raw = $out['options'] ?? null;
    if (is_array($options_raw)) {
        $out['options'] = acpt_normalize_options($options_raw);
    }

    return $out;
}

/**
 * Choice options: some ACPT versions reject an option without a boolean
 * isDefault and drop the whole group save.
 *
 * @param array<int|string, mixed> $options_raw
 * @return list<mixed>
 */
function acpt_normalize_options(array $options_raw): array
{
    $result = [];
    /** @var mixed $option */
    foreach ($options_raw as $option) {
        if (is_array($option)) {
            $option['isDefault'] = acpt_is_truthy_flag($option['isDefault'] ?? $option['is_default'] ?? false);
            unset($option['is_default']);
        }
        $result[] = $option;
    }
    return $result;
}

/**
 * Where each field of a group is stored: [repeater parent id, block id].
 *
 * @return array<string, array{0: string, 1: string}>
 */
function acpt_stored_field_parents(\ACPT\Core\Models\Meta\MetaGroupModel $group): array
{
    $parents = [];
    $queue = [];
    foreach ($group->getBoxes() as $box) {
        foreach ($box->getFields() as $field) {
            $queue[] = [$field, '', ''];
        }
    }
    while ($queue !== []) {
        [$field, $parent, $block] = array_pop($queue);
        $parents[$field->getId()] = [$parent, $block];
        foreach ($field->getChildren() as $child) {
            $queue[] = [$child, $field->getId(), ''];
        }
        foreach ($field->getBlocks() as $block_model) {
            foreach ($block_model->getFields() as $block_field) {
                $queue[] = [$block_field, '', $block_model->getId()];
            }
        }
    }
    return $parents;
}

/**
 * The fields this edit takes out of a repeater or a flexible block (to the
 * top level, or from a repeater into a block and back), as requested
 * box.field paths.
 *
 * @param list<array<string, mixed>> $boxes pinned boxes
 * @return list<string>
 */
function acpt_fields_leaving_parent(array $boxes, \ACPT\Core\Models\Meta\MetaGroupModel $group): array
{
    $stored = acpt_stored_field_parents($group);
    $leaving = [];
    $queue = [];
    foreach ($boxes as $box) {
        foreach (fgn_items($box['fields'] ?? null) as $field) {
            $queue[] = [$field, '', '', fgn_requested_name($box)];
        }
    }
    while ($queue !== []) {
        [$field, $parent, $block, $path] = array_pop($queue);
        $id = (string) ($field['id'] ?? '');
        $path .= '.' . fgn_requested_name($field);
        [$was_parent, $was_block] = $stored[$id] ?? ['', ''];
        if ($was_parent !== '' && $parent === '' || $was_block !== '' && $block === '') {
            $leaving[] = $path;
        }
        array_push($queue, ...acpt_requested_nested_places($field, $id, $path));
    }
    return $leaving;
}

/**
 * The children and block fields of a requested field, with the place each
 * one asks for: [field, repeater parent id, block id, parent path].
 *
 * @param array<string, mixed> $field
 * @return list<array{0: array<string, mixed>, 1: string, 2: string, 3: string}>
 */
function acpt_requested_nested_places(array $field, string $id, string $path): array
{
    $places = [];
    foreach (fgn_items($field['children'] ?? null) as $child) {
        $places[] = [$child, $id, '', $path];
    }
    foreach (fgn_items($field['blocks'] ?? null) as $block) {
        $block_path = $path . '.' . fgn_requested_name($block);
        foreach (fgn_items($block['fields'] ?? null) as $block_field) {
            $places[] = [$block_field, '', (string) ($block['id'] ?? ''), $block_path];
        }
    }
    return $places;
}

/**
 * Wrap save_acpt_meta_group() in a try/catch and return standard result shape.
 * save_acpt_meta_group() swallows its exceptions into the acpt/error action and
 * returns false, so that action is listened to for the reason.
 *
 * @param array<string, mixed> $payload
 * @return array{success: bool, error?: string}
 */
function acpt_save_meta_group_safely(array $payload): array
{
    if (!function_exists('save_acpt_meta_group')) {
        return ['success' => false, 'error' => 'save_acpt_meta_group() is not available'];
    }
    $errors = [];
    $listener = static function (mixed $error) use (&$errors): void {
        if ($error instanceof Throwable) {
            $errors[] = $error->getMessage();
        }
    };
    add_action('acpt/error', $listener);
    try {
        $saved = save_acpt_meta_group($payload);
    } catch (Throwable $e) {
        $saved = false;
        $errors[] = $e->getMessage();
    } finally {
        remove_action('acpt/error', $listener);
    }
    // ACPT logs persistence failures to acpt/error and may still return true:
    // any captured error makes the save a failure.
    if ($errors !== []) {
        return ['success' => false, 'error' => implode(' | ', $errors)];
    }
    return $saved ? ['success' => true] : ['success' => false];
}
