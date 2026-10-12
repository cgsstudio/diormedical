<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: field-type discovery + per-type prop reference (read-only).
 *
 * Voxel registers its field types in the static app config
 * (`\Voxel\config('post_types.field_types')`, a type => class map) and stores a
 * field's settings as a freeform props array inside the voxel:post_types blob.
 * The theme exposes no schema API that says which props a type supports, so this
 * ability pairs the LIVE type registry (never a hardcoded list) with a curated
 * prop reference. Every prop row carries a `verified` flag: true means the prop
 * name was confirmed against the `$props` definitions in the Voxel theme source
 * (1.7.8.3, the supported floor) — Voxel would silently ignore a wrong key, so
 * only names read straight from those definitions qualify, and several are
 * additionally read back from live fields and asserted by the smoke suites.
 * false means the name is documented secondhand and was NOT confirmed against
 * the source — check it against a real field of the same type with
 * novamira/voxel-get-field before relying on it. No invented names: a type this
 * reference does not cover returns an empty prop list, not a guess.
 */

wp_register_ability('novamira/voxel-get-field-type-schema', [
    'label' => __('Get Voxel Field Type Schema', domain: 'novamira-pro'),
    'description' => __(
        'Field-type reference for Voxel post types. Without input, lists every field type registered on this site (from Voxel\'s live config). With field_type, returns the props known for that type: common_props (base props every field shares: type, key, label, description, required) and type_props (type-specific settings, e.g. for file/image: allowed-types, max-size in kilobytes, max-count). Each prop row has key, type, description, and verified — verified:true marks a prop name confirmed against the Voxel theme source (1.7.8.3); verified:false marks a prop documented secondhand — cross-check it with novamira/voxel-get-field on an existing field. Read-only.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'field_type' => [
                'type' => 'string',
                'description' => 'A registered Voxel field type key (e.g. text, select, image). Omit to list all types.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'field_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Every field type registered on this site (no-input mode).',
            ],
            'count' => ['type' => 'integer'],
            'field_type' => ['type' => 'string'],
            'common_props' => [
                'type' => 'array',
                'items' => ['type' => 'object', 'additionalProperties' => true],
                'description' => 'Base props shared by every field type (single-type mode).',
            ],
            'type_props' => [
                'type' => 'array',
                'items' => ['type' => 'object', 'additionalProperties' => true],
                'description' => 'Type-specific props; empty when this reference does not cover the type.',
            ],
            'notes' => ['type' => 'string'],
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_field_type_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call before voxel-add-field / voxel-edit-field to learn which keys belong in props. Trust verified:true rows (names confirmed against the Voxel theme source); treat verified:false rows as documented hints and confirm them by reading a live field of the same type with novamira/voxel-get-field. An empty type_props means no curated reference exists for that type, not that the type has no settings. Types starting with "ui-" are layout-only and hold no value.',
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
function voxel_get_field_type_schema(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $registered = vx_registered_field_types();
    $type = is_string($input['field_type'] ?? null) ? trim($input['field_type']) : '';

    if ($type === '') {
        return [
            'field_types' => $registered,
            'count' => count($registered),
            'notes' => __(
                'Pass field_type to get the prop reference for one type. Types starting with "ui-" are layout-only.',
                domain: 'novamira-pro',
            ),
        ];
    }

    if (!in_array($type, $registered, strict: true)) {
        return vx_not_found('Voxel field type', $type);
    }

    $map = vx_field_type_props_map();

    return [
        'field_type' => $type,
        'common_props' => vx_common_field_props(),
        'type_props' => $map[$type] ?? [],
        'notes' => __(
            'verified:true props are confirmed against the Voxel theme source (1.7.8.3); verified:false props are documented secondhand — confirm them with novamira/voxel-get-field on an existing field of this type.',
            domain: 'novamira-pro',
        ),
    ];
}

/**
 * The field types registered on this site, straight from Voxel's live config
 * (type => class map). Never a hardcoded list, so custom/child-theme types show.
 *
 * @return list<string>
 */
function vx_registered_field_types(): array
{
    /** @var mixed $types */
    $types = \Voxel\config('post_types.field_types');
    if (!is_array($types)) {
        return [];
    }
    $out = [];
    foreach (array_keys($types) as $key) {
        $out[] = (string) $key;
    }
    return $out;
}

/**
 * Base props every Voxel field shares. All five names are verified against the
 * theme source 1.7.8.3: they sit in Base_Field::base_props() (base-post-field.php
 * inherits it via app/utils/object-fields/base-field.php, which array_merges the
 * defaults into every live field). type/key/label/required are additionally read
 * back from live fields through dedicated accessors (get_type, get_key,
 * get_label, is_required) and asserted by the smoke suites.
 *
 * @return list<array{key: string, type: string, description: string, verified: bool}>
 */
function vx_common_field_props(): array
{
    return [
        [
            'key' => 'type',
            'type' => 'string',
            'description' => 'The field type key. Immutable after creation.',
            'verified' => true,
        ],
        [
            'key' => 'key',
            'type' => 'string',
            'description' => 'Unique field key on the post type; doubles as the post-meta key. Immutable.',
            'verified' => true,
        ],
        [
            'key' => 'label',
            'type' => 'string',
            'description' => 'The field label shown in forms.',
            'verified' => true,
        ],
        [
            'key' => 'description',
            'type' => 'string',
            'description' => 'Help text shown under the field.',
            'verified' => true,
        ],
        [
            'key' => 'required',
            'type' => 'boolean',
            'description' => 'Whether a value is required to publish. Enforced for publish/pending, skipped for draft.',
            'verified' => true,
        ],
    ];
}

/**
 * Curated type-specific prop reference. Every prop name below is verified
 * against the `$props` definitions in the Voxel theme source 1.7.8.3
 * (app/post-types/fields/<type>-field.php) — nothing here is guessed. A type
 * absent from this map simply returns an empty list.
 *
 * @return array<string, list<array{key: string, type: string, description: string, verified: bool}>>
 */
function vx_field_type_props_map(): array
{
    $placeholder = [
        'key' => 'placeholder',
        'type' => 'string',
        'description' => 'Placeholder text shown in the empty input.',
        'verified' => true,
    ];

    return [
        'text' => [
            $placeholder,
            [
                'key' => 'minlength',
                'type' => 'integer',
                'description' => 'Minimum accepted length.',
                'verified' => true,
            ],
            [
                'key' => 'maxlength',
                'type' => 'integer',
                'description' => 'Maximum accepted length.',
                'verified' => true,
            ],
        ],
        'texteditor' => [
            [
                'key' => 'maxlength',
                'type' => 'integer',
                'description' => 'Maximum accepted length.',
                'verified' => true,
            ],
        ],
        'number' => [
            $placeholder,
            ['key' => 'min', 'type' => 'number', 'description' => 'Minimum accepted value.', 'verified' => true],
            ['key' => 'max', 'type' => 'number', 'description' => 'Maximum accepted value.', 'verified' => true],
            ['key' => 'step', 'type' => 'number', 'description' => 'Increment step.', 'verified' => true],
        ],
        'email' => [$placeholder],
        'url' => [$placeholder],
        'phone' => [$placeholder],
        'select' => [
            [
                'key' => 'choices',
                'type' => 'array',
                'description' => 'The selectable choices, each an object with value and label (optionally icon).',
                'verified' => true,
            ],
            $placeholder,
        ],
        'taxonomy' => [
            [
                // verified: smoke 06-fixes-round-2 asserts props['taxonomy'] is a
                // string on a real field read via voxel-get-field, and suite 16
                // re-checks it against a live field.
                'key' => 'taxonomy',
                'type' => 'string',
                'description' => 'The Voxel taxonomy key the field selects terms from.',
                'verified' => true,
            ],
            [
                'key' => 'multiple',
                'type' => 'boolean',
                'description' => 'Whether more than one term can be selected.',
                'verified' => true,
            ],
        ],
        'date' => [
            [
                'key' => 'enable_timepicker',
                'type' => 'boolean',
                'description' => 'Whether the field also collects a time of day.',
                'verified' => true,
            ],
        ],
        'file' => vx_upload_field_props(),
        'image' => vx_upload_field_props(),
        'repeater' => [
            [
                'key' => 'fields',
                'type' => 'array',
                'description' => 'The nested field definitions repeated per row.',
                'verified' => true,
            ],
        ],
        'post-relation' => vx_post_relation_field_props(),
    ];
}

/**
 * The file/image upload props. These three really are kebab-case in the Voxel
 * source (file-field.php / image-field.php $props), unlike e.g. the
 * post-relation props which are snake_case.
 *
 * @return list<array{key: string, type: string, description: string, verified: bool}>
 */
function vx_upload_field_props(): array
{
    return [
        [
            'key' => 'allowed-types',
            'type' => 'array',
            'description' => 'Allowed MIME types for uploads (e.g. ["image/jpeg", "image/png", "image/webp"]).',
            'verified' => true,
        ],
        [
            'key' => 'max-size',
            'type' => 'integer',
            'description' => 'Maximum size per file, in kilobytes.',
            'verified' => true,
        ],
        [
            'key' => 'max-count',
            'type' => 'integer',
            'description' => 'Maximum number of files the field accepts.',
            'verified' => true,
        ],
    ];
}

/**
 * The post-relation field props, snake_case as in the Voxel source
 * (post-relation-field.php $props).
 *
 * @return list<array{key: string, type: string, description: string, verified: bool}>
 */
function vx_post_relation_field_props(): array
{
    return [
        [
            'key' => 'post_types',
            'type' => 'array',
            'description' => 'The post type keys this field can relate to.',
            'verified' => true,
        ],
        [
            'key' => 'relation_type',
            'type' => 'string',
            'description' => 'The relation cardinality: has_one, has_many, belongs_to_one, or belongs_to_many.',
            'verified' => true,
        ],
        [
            'key' => 'max_count',
            'type' => 'integer',
            'description' => 'Maximum number of related posts for has_many/belongs_to_many relations; null means no limit.',
            'verified' => true,
        ],
        [
            'key' => 'use_custom_key',
            'type' => 'boolean',
            'description' => 'Store relation rows under custom_key instead of the field key.',
            'verified' => true,
        ],
        [
            'key' => 'custom_key',
            'type' => 'string',
            'description' => 'The relation_key written to the voxel_relations table when use_custom_key is on.',
            'verified' => true,
        ],
        [
            'key' => 'allowed_authors',
            'type' => 'string',
            'description' => 'Whose posts can be selected: current_author or any.',
            'verified' => true,
        ],
        [
            'key' => 'require_author_approval',
            'type' => 'string',
            'description' => 'Whether the related post\'s author must approve the relation: never or always.',
            'verified' => true,
        ],
        [
            'key' => 'allowed_statuses',
            'type' => 'array',
            'description' => 'Extra post statuses whose posts stay selectable besides publish (publish is always allowed).',
            'verified' => true,
        ],
    ];
}
