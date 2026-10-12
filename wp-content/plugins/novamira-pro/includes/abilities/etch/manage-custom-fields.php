<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;

/**
 * Abilities: manage Etch's native custom fields — the ACF-like field groups
 * Etch stores in the etch_cfs option ({ group_id => { label, assigned_to,
 * fields[] } }). A group is assigned to post types and/or specific post ids;
 * each field has a key (the post meta key), a label, and a type
 * (text, textarea, number or boolean). Field values resolve as {etch.<key>}
 * in dynamic content. CRUD is guarded by the etch_option_lock.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * The base field types Etch's field factory has always implemented. Etch's
 * CustomFieldType phpstan alias also lists 'image', but BaseField::create() has
 * no image case and throws "Unknown field type: image" — which would fatal the
 * post-edit meta box for any post type the group is assigned to — so 'image' is
 * never offered. Etch 1.5.0 added 'date', 'time' and 'select'; those are gated
 * separately (see etch_cf_field_types()) so older Etch never receives a type its
 * factory would throw on.
 */
const ETCH_CF_FIELD_TYPES = ['text', 'textarea', 'number', 'boolean'];

/**
 * The field types accepted for a field definition on THIS install: the base
 * four always, plus 'date', 'time' and 'select' on Etch 1.5.0+ (gated by the
 * same feature floor as the value abilities). Both the input-schema enums and
 * the validation read this, so they widen automatically with the installed Etch
 * and stay safe down to the floor.
 *
 * @return list<string>
 */
function etch_cf_field_types(): array
{
    $types = ETCH_CF_FIELD_TYPES;
    if (etch_supports_field_values()) {
        $types[] = 'date';
        $types[] = 'time';
        $types[] = 'select';
    }

    return $types;
}

// ---------------------------------------------------------------------------
// Shared helpers
// ---------------------------------------------------------------------------

/**
 * The etch_cfs option as a normalized map { group_id => definition }.
 *
 * @return array<array-key, mixed>
 */
function etch_cfs_option(): array
{
    return etch_option_array('etch_cfs');
}

/**
 * Resolve a field group within the map, or a WP_Error.
 *
 * @param array<array-key, mixed> $groups
 * @return array<array-key, mixed>|WP_Error
 */
function etch_cf_group_or_error(array $groups, string $id): array|WP_Error
{
    if (!isset($groups[$id])) {
        return etch_error('etch_not_found', sprintf(
            /* translators: %s: field group id. */
            __('Field group "%s" was not found.', domain: 'novamira-pro'),
            $id,
        ));
    }

    /** @var mixed $group */
    $group = $groups[$id];

    return is_array($group) ? $group : [];
}

/**
 * Generate a group id not already present in the map.
 *
 * @param array<array-key, mixed> $existing
 */
function etch_generate_cf_group_id(array $existing): string
{
    do {
        $id = substr(uniqid(), offset: -7);
    } while ($id === '' || isset($existing[$id]));

    return $id;
}

/**
 * Validate a custom field key (used verbatim as a post meta key).
 *
 * @return string|WP_Error
 */
function etch_cf_field_key_or_error(mixed $key): string|WP_Error
{
    if (!is_string($key) || $key === '') {
        return etch_error('etch_invalid_input', __('A non-empty field "key" is required.', domain: 'novamira-pro'));
    }

    if (strlen($key) > 64 || preg_match('#^[A-Za-z_][A-Za-z0-9_-]*$#D', $key) !== 1) {
        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: the offending key. */
            __(
                'Field key "%s" is invalid; use up to 64 letters, digits, "_" or "-", starting with a letter or "_".',
                domain: 'novamira-pro',
            ),
            $key,
        ));
    }

    return $key;
}

/**
 * Validate the options string of a "select" field: a newline-separated list,
 * stored verbatim (newlines are significant) with NUL/control bytes stripped
 * and a size cap. Whitespace-only normalizes to ''.
 *
 * @return string|WP_Error
 */
function etch_cf_options_or_error(mixed $options): string|WP_Error
{
    if (!is_string($options)) {
        return etch_error('etch_invalid_input', __(
            'Field "options" must be a newline-separated string.',
            domain: 'novamira-pro',
        ));
    }
    if (strlen($options) > 8192) {
        return etch_error('etch_invalid_input', __('Field "options" is too large (max 8 KB).', domain: 'novamira-pro'));
    }
    // Strip NUL / C0 / DEL control bytes but keep the newline (\x0A) separator.
    $clean = preg_replace('/[\x00-\x09\x0B-\x1F\x7F]/', replacement: '', subject: $options);
    if (!is_string($clean) || trim($clean) === '') {
        return '';
    }

    return $clean;
}

/**
 * Validate one field definition into a clean { key, label, type, required?, default?, description?, options? }.
 *
 * @return array{key: string, label: string, type: string, required?: bool, default?: string, description?: string, options?: string}|WP_Error
 */
function etch_cf_field_or_error(mixed $field): array|WP_Error
{
    if (!is_array($field)) {
        return etch_error('etch_invalid_input', __('Each field must be an object.', domain: 'novamira-pro'));
    }

    $key = etch_cf_field_key_or_error($field['key'] ?? null);
    if ($key instanceof WP_Error) {
        return $key;
    }

    /** @var mixed $label */
    $label = $field['label'] ?? null;
    if (!is_string($label) || trim($label) === '') {
        return etch_error('etch_invalid_input', __('Each field needs a non-empty "label".', domain: 'novamira-pro'));
    }

    /** @var mixed $type */
    $type = $field['type'] ?? null;
    if (!is_string($type) || !in_array($type, etch_cf_field_types(), strict: true)) {
        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: comma-separated list of field types. */
            __('Field "type" must be one of: %s.', domain: 'novamira-pro'),
            implode(', ', etch_cf_field_types()),
        ));
    }

    $clean = ['key' => $key, 'label' => etch_clean_text($label), 'type' => $type];

    return etch_cf_field_optionals($clean, $field, $type);
}

/**
 * Apply the optional field properties (required, default, description, options)
 * onto a validated base field, enforcing a select field's options requirement.
 * Split out so etch_cf_field_or_error stays under the complexity threshold.
 *
 * @param array{key: string, label: string, type: string} $clean
 * @param array<array-key, mixed> $field
 * @return array{key: string, label: string, type: string, required?: bool, default?: string, description?: string, options?: string}|WP_Error
 */
function etch_cf_field_optionals(array $clean, array $field, string $type): array|WP_Error
{
    if (isset($field['required'])) {
        $clean['required'] = $field['required'] === true;
    }
    if (isset($field['default'])) {
        /** @var mixed $default */
        $default = $field['default'];
        if (!is_scalar($default)) {
            return etch_error('etch_invalid_input', __(
                'Field "default" must be a scalar value.',
                domain: 'novamira-pro',
            ));
        }
        $clean['default'] = (string) $default;
    }
    if (isset($field['description'])) {
        /** @var mixed $description */
        $description = $field['description'];
        if (!is_string($description)) {
            return etch_error('etch_invalid_input', __(
                'Field "description" must be a string.',
                domain: 'novamira-pro',
            ));
        }
        $clean['description'] = etch_clean_text($description);
    }
    if (isset($field['options'])) {
        $options = etch_cf_options_or_error($field['options']);
        if ($options instanceof WP_Error) {
            return $options;
        }
        if ($options !== '') {
            $clean['options'] = $options;
        }
    }
    if ($type === 'select' && ($clean['options'] ?? '') === '') {
        return etch_error('etch_invalid_input', __(
            'A "select" field needs non-empty "options" (newline-separated choices, each line "Label : value" or plain "value").',
            domain: 'novamira-pro',
        ));
    }

    return $clean;
}

/**
 * Validate the fields list of a group.
 *
 * @return list<array{key: string, label: string, type: string, required?: bool, default?: string, description?: string, options?: string}>|WP_Error
 */
function etch_cf_fields_or_error(mixed $fields): array|WP_Error
{
    if (!is_array($fields)) {
        return etch_error('etch_invalid_input', __(
            '"fields" must be an array of field definitions.',
            domain: 'novamira-pro',
        ));
    }

    $out = [];
    $seen = [];
    foreach (array_keys($fields) as $index) {
        $clean = etch_cf_field_or_error($fields[$index]);
        if ($clean instanceof WP_Error) {
            return $clean;
        }
        if (isset($seen[$clean['key']])) {
            return etch_error('etch_invalid_input', sprintf(
                /* translators: %s: duplicate field key. */
                __('Duplicate field key "%s" in the group.', domain: 'novamira-pro'),
                $clean['key'],
            ));
        }
        $seen[$clean['key']] = true;
        $out[] = $clean;
    }

    return $out;
}

/**
 * Validate the assigned_to target of a group.
 *
 * @return array<string, mixed>|WP_Error
 */
function etch_cf_assigned_to_or_error(mixed $assigned): array|WP_Error
{
    if (!is_array($assigned)) {
        return etch_error('etch_invalid_input', __(
            '"assigned_to" must be an object with "post_types" and/or "post_ids".',
            domain: 'novamira-pro',
        ));
    }

    // op (isIn|isNotIn) — Etch's CustomFieldAssignment type carries it; the
    // runtime matcher currently ignores it (plain in_array), but we store it
    // for shape fidelity so the editor UI sees the expected shape.
    $op = 'isIn';
    if (isset($assigned['op'])) {
        /** @var mixed $raw_op */
        $raw_op = $assigned['op'];
        if (!is_string($raw_op) || !in_array($raw_op, ['isIn', 'isNotIn'], strict: true)) {
            return etch_error('etch_invalid_input', __(
                '"assigned_to.op" must be "isIn" or "isNotIn".',
                domain: 'novamira-pro',
            ));
        }
        $op = $raw_op;
    }

    $out = [];
    if (isset($assigned['post_types'])) {
        $types = etch_cf_string_list_or_error($assigned['post_types'], field: 'post_types');
        if ($types instanceof WP_Error) {
            return $types;
        }
        $out['post_types'] = $types;
    }
    if (isset($assigned['post_ids'])) {
        $ids = etch_cf_int_list_or_error($assigned['post_ids'], field: 'post_ids');
        if ($ids instanceof WP_Error) {
            return $ids;
        }
        $out['post_ids'] = $ids;
    }

    if ($out === []) {
        return etch_error('etch_invalid_input', __(
            '"assigned_to" needs at least one of "post_types" or "post_ids".',
            domain: 'novamira-pro',
        ));
    }

    $out['op'] = $op;

    return $out;
}

/**
 * @return list<string>|WP_Error
 */
function etch_cf_string_list_or_error(mixed $value, string $field): array|WP_Error
{
    if (!is_array($value)) {
        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: field name. */
            __('"%s" must be an array of strings.', domain: 'novamira-pro'),
            $field,
        ));
    }

    $out = [];
    foreach (array_keys($value) as $key) {
        /** @var mixed $item */
        $item = $value[$key];
        if (!is_string($item) || $item === '') {
            return etch_error('etch_invalid_input', sprintf(
                /* translators: %s: field name. */
                __('Each "%s" entry must be a non-empty string.', domain: 'novamira-pro'),
                $field,
            ));
        }
        $out[] = $item;
    }

    return $out;
}

/**
 * @return list<int>|WP_Error
 */
function etch_cf_int_list_or_error(mixed $value, string $field): array|WP_Error
{
    if (!is_array($value)) {
        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: field name. */
            __('"%s" must be an array of integers.', domain: 'novamira-pro'),
            $field,
        ));
    }

    $out = [];
    foreach (array_keys($value) as $key) {
        /** @var mixed $item */
        $item = $value[$key];
        if (is_int($item)) {
            $out[] = $item;

            continue;
        }
        if (is_string($item) && ctype_digit($item)) {
            $out[] = (int) $item;

            continue;
        }

        return etch_error('etch_invalid_input', sprintf(
            /* translators: %s: field name. */
            __('Each "%s" entry must be an integer.', domain: 'novamira-pro'),
            $field,
        ));
    }

    return $out;
}

/**
 * The post types of a group's assignment, for the compact list view.
 *
 * @param array<array-key, mixed> $assigned
 * @return list<string>
 */
function etch_cf_post_types(array $assigned): array
{
    /** @var mixed $types */
    $types = $assigned['post_types'] ?? [];
    $out = [];
    if (is_array($types)) {
        foreach (array_keys($types) as $key) {
            /** @var mixed $item */
            $item = $types[$key];
            if (is_string($item)) {
                $out[] = $item;
            }
        }
    }

    return $out;
}

/**
 * The fields list of a group, normalized to a list of arrays.
 *
 * @param array<array-key, mixed> $group
 * @return list<array<array-key, mixed>>
 */
function etch_cf_group_fields(array $group): array
{
    /** @var mixed $fields */
    $fields = $group['fields'] ?? [];
    $out = [];
    if (is_array($fields)) {
        foreach (array_keys($fields) as $key) {
            /** @var mixed $field */
            $field = $fields[$key];
            if (is_array($field)) {
                $out[] = $field;
            }
        }
    }

    return $out;
}

/**
 * Shape one group for the full (get) view.
 *
 * @param array<array-key, mixed> $group
 * @return array{id: string, label: string, assigned_to: array<array-key, mixed>, fields: list<array<array-key, mixed>>}
 */
function etch_cf_group_view(string $id, array $group): array
{
    /** @var mixed $label */
    $label = $group['label'] ?? '';
    /** @var mixed $assigned */
    $assigned = $group['assigned_to'] ?? [];

    return [
        'id' => $id,
        'label' => is_string($label) ? $label : '',
        'assigned_to' => is_array($assigned) ? $assigned : [],
        'fields' => etch_cf_group_fields($group),
    ];
}

/**
 * Locate a field's list index within a group's fields by its key.
 *
 * @param list<array<array-key, mixed>> $fields
 */
function etch_cf_field_index(array $fields, string $key): ?int
{
    foreach (array_keys($fields) as $index) {
        $field = $fields[$index];
        if (($field['key'] ?? null) === $key) {
            return $index;
        }
    }

    return null;
}

// ---------------------------------------------------------------------------
// list-field-groups
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-list-field-groups', [
    'label' => __('List Etch Field Groups', domain: 'novamira-pro'),
    'description' => __(
        'Lists the custom field groups Etch registers (the etch_cfs option). Returns a compact entry per group { id, label, post_types, field_count }. Fetch the full definition (incl. each field) with novamira/etch-get-field-group.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'count' => ['type' => 'integer'],
            'field_groups' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'field_count' => ['type' => 'integer'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_list_field_groups',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Etch native custom fields only. Field values resolve as {etch.<field key>} in dynamic content. Use novamira/etch-get-field-group for the full field list.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{count: int, field_groups: list<array{id: string, label: string, post_types: list<string>, field_count: int}>}
 */
function etch_list_field_groups(): array
{
    $groups = etch_cfs_option();

    $out = [];
    foreach (array_keys($groups) as $id) {
        if (!is_string($id)) {
            continue;
        }
        /** @var mixed $group */
        $group = $groups[$id];
        if (!is_array($group)) {
            continue;
        }

        $view = etch_cf_group_view($id, $group);
        $out[] = [
            'id' => $view['id'],
            'label' => $view['label'],
            'post_types' => etch_cf_post_types($view['assigned_to']),
            'field_count' => count($view['fields']),
        ];
    }

    return ['count' => count($out), 'field_groups' => $out];
}

// ---------------------------------------------------------------------------
// get-field-group
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-get-field-group', [
    'label' => __('Get Etch Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Returns one Etch custom field group in full: { id, label, assigned_to (post_types / post_ids), fields }. Each field is { key, label, type, required?, default? }.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'group_id' => ['type' => 'string', 'description' => 'The field group id.'],
        ],
        'required' => ['group_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'assigned_to' => ['type' => 'object'],
            'fields' => ['type' => 'array', 'items' => ['type' => 'object']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a group before editing it or its fields.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: string, label: string, assigned_to: array<array-key, mixed>, fields: list<array<array-key, mixed>>}|WP_Error
 */
function etch_get_field_group(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $group_id */
    $group_id = $input['group_id'] ?? null;
    if (!is_string($group_id) || $group_id === '') {
        return etch_error('etch_invalid_input', __('A non-empty "group_id" is required.', domain: 'novamira-pro'));
    }

    $groups = etch_cfs_option();
    $group = etch_cf_group_or_error($groups, $group_id);
    if ($group instanceof WP_Error) {
        return $group;
    }

    return etch_cf_group_view($group_id, $group);
}

// ---------------------------------------------------------------------------
// create-field-group
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-create-field-group', [
    'label' => __('Create Etch Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Etch custom field group. Requires label and assigned_to ({ post_types: [slugs] } and/or { post_ids: [ids] }); fields is an optional initial list of { key, label, type } (type is text, textarea, number, boolean — plus date, time, select on Etch 1.5.0+; a select field also needs options: newline-separated choices). Returns the new group id. Add fields later with novamira/etch-add-field.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'label' => ['type' => 'string'],
            'assigned_to' => [
                'type' => 'object',
                'properties' => [
                    'post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'post_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'op' => [
                        'type' => 'string',
                        'enum' => ['isIn', 'isNotIn'],
                        'description' => 'Match mode; defaults to isIn.',
                    ],
                ],
                'additionalProperties' => false,
                'description' => 'Where the group applies: post type slugs and/or specific post ids.',
            ],
            'fields' => [
                'type' => 'array',
                'items' => etch_cf_field_input_schema(),
                'description' => 'Optional initial fields.',
            ],
        ],
        'required' => ['label', 'assigned_to'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'label' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_create_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'assigned_to should reference existing post types (built-in or Etch CPTs). Field keys become post meta keys and resolve as {etch.<key>} in dynamic content.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * The shared JSON schema for a single field input.
 *
 * @return array<string, mixed>
 */
function etch_cf_field_input_schema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string', 'description' => 'The post meta key (and {etch.<key>} binding).'],
            'label' => ['type' => 'string'],
            'type' => ['type' => 'string', 'enum' => etch_cf_field_types()],
            'required' => ['type' => 'boolean'],
            'default' => ['type' => 'string'],
            'options' => [
                'type' => 'string',
                'description' => 'For a "select" field: newline-separated choices, each line "Label : value" or plain "value".',
            ],
            'description' => ['type' => 'string'],
        ],
        'required' => ['key', 'label', 'type'],
        'additionalProperties' => false,
    ];
}

/**
 * @param array<array-key, mixed> $input
 * @return array{id: string, label: string}|WP_Error
 */
function etch_create_field_group(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $label */
    $label = $input['label'] ?? '';
    if (!is_string($label) || trim($label) === '') {
        return etch_error('etch_invalid_input', __('A non-empty "label" is required.', domain: 'novamira-pro'));
    }

    $assigned = etch_cf_assigned_to_or_error($input['assigned_to'] ?? null);
    if ($assigned instanceof WP_Error) {
        return $assigned;
    }

    $fields = etch_cf_fields_or_error($input['fields'] ?? []);
    if ($fields instanceof WP_Error) {
        return $fields;
    }

    $clean_label = etch_clean_text($label);
    $definition = ['label' => $clean_label, 'assigned_to' => $assigned, 'fields' => $fields];

    $lock = etch_option_lock_acquire('etch_cfs');
    try {
        $groups = etch_cfs_option();
        $id = etch_generate_cf_group_id($groups);
        $groups[$id] = $definition;
        update_option('etch_cfs', $groups);
    } finally {
        etch_option_lock_release($lock);
    }

    return ['id' => $id, 'label' => $clean_label];
}

// ---------------------------------------------------------------------------
// edit-field-group
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-edit-field-group', [
    'label' => __('Edit Etch Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Edits a field group\'s label and/or assigned_to (partial). Provide group_id and any of label or assigned_to (assigned_to replaces the whole assignment). To change the fields use novamira/etch-add-field / etch-edit-field / etch-delete-field. Returns id and label.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'group_id' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'assigned_to' => [
                'type' => 'object',
                'properties' => [
                    'post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'post_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                    'op' => [
                        'type' => 'string',
                        'enum' => ['isIn', 'isNotIn'],
                        'description' => 'Match mode; defaults to isIn.',
                    ],
                ],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['group_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'label' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_edit_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial: label and assigned_to update; assigned_to (if passed) replaces the whole assignment. Fields are not touched here. The id cannot change.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: string, label: string}|WP_Error
 */
function etch_edit_field_group(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $group_id */
    $group_id = $input['group_id'] ?? null;
    if (!is_string($group_id) || $group_id === '') {
        return etch_error('etch_invalid_input', __('A non-empty "group_id" is required.', domain: 'novamira-pro'));
    }

    $new_label = null;
    if (isset($input['label'])) {
        /** @var mixed $label */
        $label = $input['label'];
        if (!is_string($label) || trim($label) === '') {
            return etch_error('etch_invalid_input', __('"label" must be a non-empty string.', domain: 'novamira-pro'));
        }
        $new_label = etch_clean_text($label);
    }

    $new_assigned = null;
    if (isset($input['assigned_to'])) {
        $new_assigned = etch_cf_assigned_to_or_error($input['assigned_to']);
        if ($new_assigned instanceof WP_Error) {
            return $new_assigned;
        }
    }

    $result = ['id' => $group_id, 'label' => ''];
    $lock = etch_option_lock_acquire('etch_cfs');
    try {
        $groups = etch_cfs_option();
        $group = etch_cf_group_or_error($groups, $group_id);
        if ($group instanceof WP_Error) {
            return $group;
        }

        if ($new_label !== null) {
            $group['label'] = $new_label;
        }
        if ($new_assigned !== null) {
            $group['assigned_to'] = $new_assigned;
        }
        $groups[$group_id] = $group;
        update_option('etch_cfs', $groups);

        $view = etch_cf_group_view($group_id, $group);
        $result = ['id' => $group_id, 'label' => $view['label']];
    } finally {
        etch_option_lock_release($lock);
    }

    return $result;
}

// ---------------------------------------------------------------------------
// delete-field-group
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-delete-field-group', [
    'label' => __('Delete Etch Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an Etch custom field group by id. The group definition is removed; existing post meta values written for its fields remain in the database. Returns id and deleted:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'group_id' => ['type' => 'string'],
        ],
        'required' => ['group_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_delete_field_group',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'DESTRUCTIVE for the group definition only — post meta values already stored are left in place (orphaned).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: string, deleted: bool}|WP_Error
 */
function etch_delete_field_group(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $group_id */
    $group_id = $input['group_id'] ?? null;
    if (!is_string($group_id) || $group_id === '') {
        return etch_error('etch_invalid_input', __('A non-empty "group_id" is required.', domain: 'novamira-pro'));
    }

    $lock = etch_option_lock_acquire('etch_cfs');
    try {
        $groups = etch_cfs_option();
        $group = etch_cf_group_or_error($groups, $group_id);
        if ($group instanceof WP_Error) {
            return $group;
        }

        unset($groups[$group_id]);
        update_option('etch_cfs', $groups);
    } finally {
        etch_option_lock_release($lock);
    }

    return ['id' => $group_id, 'deleted' => true];
}

// ---------------------------------------------------------------------------
// add-field
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-add-field', [
    'label' => __('Add Etch Field', domain: 'novamira-pro'),
    'description' => __(
        'Adds a field to an existing Etch field group. Requires group_id, key (the post meta key), label, and type (text, textarea, number, boolean — plus date, time, select on Etch 1.5.0+; a select field also needs options: newline-separated choices); optional required and default. Errors if the key already exists in the group. Returns group_id and key.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'group_id' => ['type' => 'string'],
            'key' => ['type' => 'string', 'description' => 'The post meta key (and {etch.<key>} binding).'],
            'label' => ['type' => 'string'],
            'type' => ['type' => 'string', 'enum' => etch_cf_field_types()],
            'required' => ['type' => 'boolean'],
            'default' => ['type' => 'string'],
            'options' => [
                'type' => 'string',
                'description' => 'For a "select" field: newline-separated choices, each line "Label : value" or plain "value".',
            ],
            'description' => ['type' => 'string'],
        ],
        'required' => ['group_id', 'key', 'label', 'type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'group_id' => ['type' => 'string'],
            'key' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_add_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Inserts one field into the group. The key must be unique within the group and becomes the post meta key bound as {etch.<key>}.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{group_id: string, key: string}|WP_Error
 */
function etch_add_field(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $group_id */
    $group_id = $input['group_id'] ?? null;
    if (!is_string($group_id) || $group_id === '') {
        return etch_error('etch_invalid_input', __('A non-empty "group_id" is required.', domain: 'novamira-pro'));
    }

    $field = etch_cf_field_or_error($input);
    if ($field instanceof WP_Error) {
        return $field;
    }

    $lock = etch_option_lock_acquire('etch_cfs');
    try {
        $groups = etch_cfs_option();
        $group = etch_cf_group_or_error($groups, $group_id);
        if ($group instanceof WP_Error) {
            return $group;
        }

        $fields = etch_cf_group_fields($group);
        if (etch_cf_field_index($fields, $field['key']) !== null) {
            return etch_error('etch_exists', sprintf(
                /* translators: %s: field key. */
                __('Field "%s" already exists in this group.', domain: 'novamira-pro'),
                $field['key'],
            ));
        }

        $fields[] = $field;
        $group['fields'] = $fields;
        $groups[$group_id] = $group;
        update_option('etch_cfs', $groups);
    } finally {
        etch_option_lock_release($lock);
    }

    return ['group_id' => $group_id, 'key' => $field['key']];
}

// ---------------------------------------------------------------------------
// edit-field
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-edit-field', [
    'label' => __('Edit Etch Field', domain: 'novamira-pro'),
    'description' => __(
        'Edits a field within a group (partial). Provide group_id, field_key (the current key), and any of label, type, required, default, or key (to rename — changes the post meta key). Returns group_id and key (the resulting key).',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'group_id' => ['type' => 'string'],
            'field_key' => ['type' => 'string', 'description' => 'The current key of the field to edit.'],
            'key' => ['type' => 'string', 'description' => 'A new key (renames the meta key).'],
            'label' => ['type' => 'string'],
            'type' => ['type' => 'string', 'enum' => etch_cf_field_types()],
            'required' => ['type' => 'boolean'],
            'default' => ['type' => 'string'],
            'options' => [
                'type' => 'string',
                'description' => 'For a "select" field: newline-separated choices, each line "Label : value" or plain "value".',
            ],
            'description' => ['type' => 'string'],
        ],
        'required' => ['group_id', 'field_key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'group_id' => ['type' => 'string'],
            'key' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_edit_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial merge over the existing field. Renaming the key changes the post meta key bound as {etch.<key>} — existing values under the old key are not migrated.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{group_id: string, key: string}|WP_Error
 */
function etch_edit_field(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $group_id */
    $group_id = $input['group_id'] ?? null;
    if (!is_string($group_id) || $group_id === '') {
        return etch_error('etch_invalid_input', __('A non-empty "group_id" is required.', domain: 'novamira-pro'));
    }

    /** @var mixed $field_key */
    $field_key = $input['field_key'] ?? null;
    if (!is_string($field_key) || $field_key === '') {
        return etch_error('etch_invalid_input', __('A non-empty "field_key" is required.', domain: 'novamira-pro'));
    }

    $result = ['group_id' => $group_id, 'key' => $field_key];
    $lock = etch_option_lock_acquire('etch_cfs');
    try {
        $groups = etch_cfs_option();
        $group = etch_cf_group_or_error($groups, $group_id);
        if ($group instanceof WP_Error) {
            return $group;
        }

        $fields = etch_cf_group_fields($group);
        $index = etch_cf_field_index($fields, $field_key);
        if ($index === null) {
            return etch_error('etch_not_found', sprintf(
                /* translators: %s: field key. */
                __('Field "%s" was not found in this group.', domain: 'novamira-pro'),
                $field_key,
            ));
        }

        $merged = etch_cf_merge_field($fields[$index], $input);
        if ($merged instanceof WP_Error) {
            return $merged;
        }

        // Guard against renaming onto another field's key.
        if ($merged['key'] !== $field_key && etch_cf_field_index($fields, $merged['key']) !== null) {
            return etch_error('etch_exists', sprintf(
                /* translators: %s: field key. */
                __('Field "%s" already exists in this group.', domain: 'novamira-pro'),
                $merged['key'],
            ));
        }

        $fields[$index] = $merged;
        $group['fields'] = array_values($fields);
        $groups[$group_id] = $group;
        update_option('etch_cfs', $groups);

        $result = ['group_id' => $group_id, 'key' => $merged['key']];
    } finally {
        etch_option_lock_release($lock);
    }

    return $result;
}

/**
 * Merge an edit payload over an existing field, re-validating the result.
 *
 * @param array<array-key, mixed> $existing
 * @param array<array-key, mixed> $input
 * @return array{key: string, label: string, type: string, required?: bool, default?: string, description?: string, options?: string}|WP_Error
 */
function etch_cf_merge_field(array $existing, array $input): array|WP_Error
{
    /** @var mixed $current_key */
    $current_key = $existing['key'] ?? '';
    $candidate = [
        'key' => $input['key'] ?? $current_key,
        'label' => $input['label'] ?? $existing['label'] ?? '',
        'type' => $input['type'] ?? $existing['type'] ?? '',
    ];
    foreach (['required', 'default', 'description', 'options'] as $opt) {
        if (!isset($input[$opt]) && !isset($existing[$opt])) {
            continue;
        }
        /** @var mixed $value */
        $value = $input[$opt] ?? $existing[$opt] ?? '';
        $candidate[$opt] = $value;
    }

    return etch_cf_field_or_error($candidate);
}

// ---------------------------------------------------------------------------
// delete-field
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-delete-field', [
    'label' => __('Delete Etch Field', domain: 'novamira-pro'),
    'description' => __(
        'Removes a field from a group by its key. The field definition is removed; existing post meta values under that key remain in the database. Returns group_id, key, and deleted:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'group_id' => ['type' => 'string'],
            'field_key' => ['type' => 'string'],
        ],
        'required' => ['group_id', 'field_key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'group_id' => ['type' => 'string'],
            'key' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_delete_field',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'DESTRUCTIVE for the field definition only — post meta values already stored under the key are left in place.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{group_id: string, key: string, deleted: bool}|WP_Error
 */
function etch_delete_field(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $group_id */
    $group_id = $input['group_id'] ?? null;
    if (!is_string($group_id) || $group_id === '') {
        return etch_error('etch_invalid_input', __('A non-empty "group_id" is required.', domain: 'novamira-pro'));
    }

    /** @var mixed $field_key */
    $field_key = $input['field_key'] ?? null;
    if (!is_string($field_key) || $field_key === '') {
        return etch_error('etch_invalid_input', __('A non-empty "field_key" is required.', domain: 'novamira-pro'));
    }

    $lock = etch_option_lock_acquire('etch_cfs');
    try {
        $groups = etch_cfs_option();
        $group = etch_cf_group_or_error($groups, $group_id);
        if ($group instanceof WP_Error) {
            return $group;
        }

        $fields = etch_cf_group_fields($group);
        $index = etch_cf_field_index($fields, $field_key);
        if ($index === null) {
            return etch_error('etch_not_found', sprintf(
                /* translators: %s: field key. */
                __('Field "%s" was not found in this group.', domain: 'novamira-pro'),
                $field_key,
            ));
        }

        unset($fields[$index]);
        $group['fields'] = array_values($fields);
        $groups[$group_id] = $group;
        update_option('etch_cfs', $groups);
    } finally {
        etch_option_lock_release($lock);
    }

    return ['group_id' => $group_id, 'key' => $field_key, 'deleted' => true];
}
