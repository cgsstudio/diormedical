<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use FrmProApplication;
use WP_Error;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Formidable Pro's application CPT-like taxonomy. Applications are
 * `wp_terms` rows in the `frm_application` taxonomy with companion
 * term_meta (`_frm_form_id` for member forms, `_frm_form_count`,
 * `_frm_created_at`, `_frm_updated_at`).
 */
const FRM_APPLICATION_TAXONOMY = 'frm_application';

// ---------------------------------------------------------------------------
// list-applications
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-list-applications', [
    'label' => __('List Formidable Applications', domain: 'novamira-pro'),
    'description' => __(
        'Lists every Formidable Pro Application (terms in the `frm_application` taxonomy). Compact row per application: id, name, slug, form_count (cached on `_frm_form_count` term meta), created_at, updated_at (Unix timestamps from `_frm_created_at` / `_frm_updated_at` term meta). Pro-only. Use this BEFORE get-application / apply-form-to-application to pick an application id.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'applications' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'name' => ['type' => 'string'],
                        'slug' => ['type' => 'string'],
                        'form_count' => ['type' => 'integer'],
                        'created_at' => ['type' => 'integer'],
                        'updated_at' => ['type' => 'integer'],
                    ],
                    'required' => ['id', 'name', 'slug', 'form_count', 'created_at', 'updated_at'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['applications'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_list_applications',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists Formidable Pro Applications. Pro-only — if this ability is missing, Pro is not active at the required version.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{applications: list<array{id: int, name: string, slug: string, form_count: int, created_at: int, updated_at: int}>}|WP_Error
 */
function formidable_list_applications(array $input): array|WP_Error
{
    if (!frm_pro_active()) {
        return frm_pro_required('list-applications');
    }
    $cap = frm_capability_or_error('frm_view_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    // @mago-expect analysis:mixed-assignment
    $terms = get_terms([
        'taxonomy' => FRM_APPLICATION_TAXONOMY,
        'hide_empty' => false,
    ]);

    $rows = [];
    if (is_array($terms)) {
        // @mago-expect analysis:mixed-assignment
        foreach ($terms as $term) {
            if (!$term instanceof WP_Term) {
                continue;
            }
            $rows[] = frm_application_to_compact_row($term);
        }
    }
    return ['applications' => $rows];
}

// ---------------------------------------------------------------------------
// get-application
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-get-application', [
    'label' => __('Get Formidable Application', domain: 'novamira-pro'),
    'description' => __(
        'Reads one Formidable Pro Application by id. Returns id, name, slug, form_count, created_at, updated_at, AND the `form_ids` list (member form ids retrieved via FrmProApplication::get_forms_for_application). Pro-only.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'application_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['application_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'application' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'name' => ['type' => 'string'],
                    'slug' => ['type' => 'string'],
                    'form_count' => ['type' => 'integer'],
                    'created_at' => ['type' => 'integer'],
                    'updated_at' => ['type' => 'integer'],
                    'form_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
                ],
                'required' => ['id', 'name', 'slug', 'form_count', 'created_at', 'updated_at', 'form_ids'],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['application'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_get_application',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns one application + the form_ids it owns. Pro-only.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{application_id?: int|string} $input
 * @return array{application: array<string, mixed>}|WP_Error
 */
function formidable_get_application(array $input): array|WP_Error
{
    if (!frm_pro_active()) {
        return frm_pro_required('get-application');
    }
    $cap = frm_capability_or_error('frm_view_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $term = frm_application_or_error($input['application_id'] ?? 0);
    if ($term instanceof WP_Error) {
        return $term;
    }

    $row = frm_application_to_compact_row($term);
    $form_ids_raw = FrmProApplication::get_forms_for_application($term->term_id, get_ids_only: true);
    $row['form_ids'] = array_values(array_map(static fn(int|string $id): int => (int) $id, $form_ids_raw));

    return ['application' => $row];
}

// ---------------------------------------------------------------------------
// create-application
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-create-application', [
    'label' => __('Create Formidable Application', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Formidable Pro Application (a `frm_application` taxonomy term). Wraps FrmProApplication::create which calls wp_insert_term + adds `_frm_created_at` / `_frm_updated_at` term meta. Pass `name` (required). Pro-only.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'application' => ['type' => 'object'],
        ],
        'required' => ['application'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_create_application',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates a new application with the given name. Pro-only.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{name?: string} $input
 * @return array{application: array<string, mixed>}|WP_Error
 */
function formidable_create_application(array $input): array|WP_Error
{
    if (!frm_pro_active()) {
        return frm_pro_required('create-application');
    }
    $cap = frm_capability_or_error('frm_change_settings');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $name = isset($input['name']) ? trim($input['name']) : '';
    if ($name === '') {
        return frm_invalid_input('`name` is required and must be a non-empty string.');
    }
    $result = FrmProApplication::create($name);
    if ($result instanceof WP_Error) {
        return $result;
    }
    $term = get_term((int) $result['term_id'], FRM_APPLICATION_TAXONOMY);
    if (!$term instanceof WP_Term) {
        return frm_error(
            code: 'formidable_create_application_failed',
            message: "Could not re-fetch newly created application {$result['term_id']}.",
            status: 500,
        );
    }
    return ['application' => frm_application_to_compact_row($term)];
}

// ---------------------------------------------------------------------------
// apply-form-to-application (bind)
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-apply-form-to-application', [
    'label' => __('Apply Form to Application', domain: 'novamira-pro'),
    'description' => __(
        'Binds a form to a Formidable Pro Application — wraps FrmProApplication::add_form_to_application which adds a `_frm_form_id` term meta and bumps the cached form_count. Idempotent re-call returns applied=false. Pro-only.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'application_id' => ['type' => 'integer', 'minimum' => 1],
            'form_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['application_id', 'form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'application_id' => ['type' => 'integer'],
            'form_id' => ['type' => 'integer'],
            'applied' => ['type' => 'boolean'],
        ],
        'required' => ['application_id', 'form_id', 'applied'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_apply_form_to_application',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Binds a form to an application. Idempotent. Pro-only. Reversible binding (call clear-form-from-application to undo) — not destructive.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{application_id?: int|string, form_id?: int|string} $input
 * @return array{application_id: int, form_id: int, applied: bool}|WP_Error
 */
function formidable_apply_form_to_application(array $input): array|WP_Error
{
    if (!frm_pro_active()) {
        return frm_pro_required('apply-form-to-application');
    }
    $cap = frm_capability_or_error('frm_change_settings');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $term = frm_application_or_error($input['application_id'] ?? 0);
    if ($term instanceof WP_Error) {
        return $term;
    }
    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $form_id = (int) $form->id;

    $existing = FrmProApplication::get_forms_for_application($term->term_id, get_ids_only: true);
    if (in_array($form_id, array_map(static fn(int|string $id): int => (int) $id, $existing), strict: true)) {
        return ['application_id' => $term->term_id, 'form_id' => $form_id, 'applied' => false];
    }

    FrmProApplication::add_form_to_application($term->term_id, $form_id);
    return ['application_id' => $term->term_id, 'form_id' => $form_id, 'applied' => true];
}

// ---------------------------------------------------------------------------
// clear-form-from-application (unbind)
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-clear-form-from-application', [
    'label' => __('Clear Form From Application', domain: 'novamira-pro'),
    'description' => __(
        "Unbinds a form from a Formidable Pro Application — wraps FrmProApplication::remove_form_from_application. Idempotent: re-call when the form isn't bound returns cleared=false. Pro-only.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'application_id' => ['type' => 'integer', 'minimum' => 1],
            'form_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['application_id', 'form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'application_id' => ['type' => 'integer'],
            'form_id' => ['type' => 'integer'],
            'cleared' => ['type' => 'boolean'],
        ],
        'required' => ['application_id', 'form_id', 'cleared'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_clear_form_from_application',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Unbinds a form from an application. Idempotent. Pro-only. Reversible binding (call apply-form-to-application to re-bind) — not destructive.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{application_id?: int|string, form_id?: int|string} $input
 * @return array{application_id: int, form_id: int, cleared: bool}|WP_Error
 */
function formidable_clear_form_from_application(array $input): array|WP_Error
{
    if (!frm_pro_active()) {
        return frm_pro_required('clear-form-from-application');
    }
    $cap = frm_capability_or_error('frm_change_settings');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $term = frm_application_or_error($input['application_id'] ?? 0);
    if ($term instanceof WP_Error) {
        return $term;
    }
    $form_id = frm_to_int($input['form_id'] ?? 0);
    if ($form_id <= 0) {
        return frm_invalid_input('`form_id` must be a positive integer.');
    }

    $existing = FrmProApplication::get_forms_for_application($term->term_id, get_ids_only: true);
    if (!in_array($form_id, array_map(static fn(int|string $id): int => (int) $id, $existing), strict: true)) {
        return ['application_id' => $term->term_id, 'form_id' => $form_id, 'cleared' => false];
    }

    FrmProApplication::remove_form_from_application($term->term_id, $form_id);
    return ['application_id' => $term->term_id, 'form_id' => $form_id, 'cleared' => true];
}

// ---------------------------------------------------------------------------
// delete-application
// ---------------------------------------------------------------------------

wp_register_ability('novamira/formidable-delete-application', [
    'label' => __('Delete Formidable Application', domain: 'novamira-pro'),
    'description' => __(
        "Deletes a Formidable Pro Application term. Underlying member forms are NOT cascaded — they're unbound but remain in the database. Pro-only. Idempotent: deleting a missing application returns deleted=false.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'application_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['application_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'application_id' => ['type' => 'integer'],
            'deleted' => ['type' => 'boolean'],
        ],
        'required' => ['application_id', 'deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_delete_application',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Deletes one application term. Member forms remain (not cascaded). Pro-only. Idempotent.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{application_id?: int|string} $input
 * @return array{application_id: int, deleted: bool}|WP_Error
 */
function formidable_delete_application(array $input): array|WP_Error
{
    if (!frm_pro_active()) {
        return frm_pro_required('delete-application');
    }
    $cap = frm_capability_or_error('frm_change_settings');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $application_id = frm_to_int($input['application_id'] ?? 0);
    if ($application_id <= 0) {
        return frm_invalid_input('`application_id` must be a positive integer.');
    }

    $term = get_term($application_id, FRM_APPLICATION_TAXONOMY);
    if (!$term instanceof WP_Term) {
        return ['application_id' => $application_id, 'deleted' => false];
    }

    $result = wp_delete_term($application_id, FRM_APPLICATION_TAXONOMY);
    if ($result === false || $result === 0 || $result instanceof WP_Error) {
        return frm_error(
            code: 'formidable_delete_application_failed',
            message: 'wp_delete_term returned a non-success value.',
        );
    }
    return ['application_id' => $application_id, 'deleted' => true];
}

// ===========================================================================
// Internal helpers (private to this file)
// ===========================================================================

/**
 * Resolve an application_id to its `WP_Term` row in the frm_application
 * taxonomy. Returns a typed WP_Error on every failure.
 */
function frm_application_or_error(mixed $application_id): WP_Term|WP_Error
{
    $id = frm_to_int($application_id);
    if ($id <= 0) {
        return frm_invalid_input('`application_id` must be a positive integer.');
    }
    $term = get_term($id, FRM_APPLICATION_TAXONOMY);
    if (!$term instanceof WP_Term) {
        return new WP_Error('formidable_application_not_found', "Formidable application not found: {$id}", [
            'status' => 404,
        ]);
    }
    return $term;
}

/**
 * Reduce a Formidable application term to the compact row shape.
 *
 * @return array{id: int, name: string, slug: string, form_count: int, created_at: int, updated_at: int}
 */
function frm_application_to_compact_row(WP_Term $term): array
{
    return [
        'id' => (int) $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'form_count' => (int) get_term_meta($term->term_id, key: '_frm_form_count', single: true),
        'created_at' => (int) get_term_meta($term->term_id, key: '_frm_created_at', single: true),
        'updated_at' => (int) get_term_meta($term->term_id, key: '_frm_updated_at', single: true),
    ];
}
