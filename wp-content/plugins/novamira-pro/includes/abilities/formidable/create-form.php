<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use FrmForm;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/formidable-create-form', [
    'label' => __('Create Formidable Form', domain: 'novamira-pro'),
    'description' => __(
        'Creates a Formidable form. Accepts both short and WordPress-native keys for the common fields: `title` ↔ `name` (255-char limit, CRLF-stripped); `slug` ↔ `form_key` (auto-suffixed when it collides with an existing form, so always trust the returned value, never the input). Optional `description` and `status` (`published` | `draft`). Set `is_template=true` to register the row as a form-template. Pass `source_form_id` to DUPLICATE an existing form (including its fields and Repeater child sub-forms) and then apply the caller-supplied name / slug / description / status as overrides — the duplication path uses FrmForm::duplicate, which copies fields verbatim. Returns the compact form row of the newly created (or duplicated) form.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'title' => [
                'type' => 'string',
                'description' => 'Human-readable form name (255-char limit, CRLF-stripped). Equivalent to `name` — pass one OR the other.',
            ],
            'name' => [
                'type' => 'string',
                'description' => 'WordPress-native alias for `title`.',
            ],
            'slug' => [
                'type' => 'string',
                'description' => 'Slug-style key for the form. Equivalent to `form_key` — pass one OR the other. Auto-derived from `title` when omitted. If the value collides with an existing form, Formidable appends a numeric suffix; ALWAYS read back the actual key from the response.',
            ],
            'form_key' => [
                'type' => 'string',
                'description' => 'Formidable-native alias for `slug`.',
            ],
            'description' => ['type' => 'string'],
            'status' => [
                'type' => 'string',
                'enum' => ['published', 'draft'],
                'default' => 'published',
            ],
            'is_template' => ['type' => 'boolean', 'default' => false],
            'source_form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'When set, the form is created by DUPLICATING this form id (fields, Repeater child sub-forms, action templates per FrmForm::duplicate). The other inputs (title / slug / description / status / is_template) then OVERRIDE the duplicated row.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form' => [
                'type' => 'object',
                'properties' => [
                    'id' => ['type' => 'integer'],
                    'form_key' => ['type' => 'string'],
                    'name' => ['type' => 'string'],
                    'description' => ['type' => 'string'],
                    'status' => ['type' => 'string'],
                    'parent_form_id' => ['type' => 'integer'],
                    'is_template' => ['type' => 'boolean'],
                    'created_at' => ['type' => 'string'],
                ],
                'required' => [
                    'id',
                    'form_key',
                    'name',
                    'description',
                    'status',
                    'parent_form_id',
                    'is_template',
                    'created_at',
                ],
                'additionalProperties' => false,
            ],
            'duplicated_from' => [
                'type' => ['integer', 'null'],
                'description' => 'Source form id when the row came from the duplicate path, null otherwise.',
            ],
        ],
        'required' => ['form', 'duplicated_from'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_create_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates a new form. The `title` / `name` field is required; `slug` / `form_key` is optional (auto-derived from title — Formidable auto-suffixes on collision, so ALWAYS read the actual key from the response, never assume the input). Pass `source_form_id` to duplicate an existing form including its fields and Repeater child sub-forms; the other inputs then override the duplicated row. The response includes `duplicated_from` so you know which path ran. Add fields with novamira/formidable-add-field (S3) — this ability registers the form shell only.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     title?: string,
 *     name?: string,
 *     slug?: string,
 *     form_key?: string,
 *     description?: string,
 *     status?: string,
 *     is_template?: bool,
 *     source_form_id?: int,
 * } $input
 * @return array{
 *     form: array{
 *         id: int,
 *         form_key: string,
 *         name: string,
 *         description: string,
 *         status: string,
 *         parent_form_id: int,
 *         is_template: bool,
 *         created_at: string,
 *     },
 *     duplicated_from: int|null,
 * }|WP_Error
 */
function formidable_create_form(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }

    $cap = frm_capability_or_error('frm_edit_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $input = frm_normalize_form_input($input);

    $source_id = isset($input['source_form_id']) ? frm_to_int($input['source_form_id']) : 0;
    if ($source_id > 0) {
        return frm_create_form_via_duplicate($source_id, $input);
    }

    $name = isset($input['name']) ? trim((string) $input['name']) : '';
    if ($name === '') {
        return frm_invalid_input('Either `title` or `name` is required and must be a non-empty string.');
    }

    return frm_create_form_fresh($name, $input);
}

/**
 * Fresh-create path: build values, call FrmForm::create, re-fetch row.
 *
 * @param array<string, mixed> $input
 * @return array{form: array{id: int, form_key: string, name: string, description: string, status: string, parent_form_id: int, is_template: bool, created_at: string}, duplicated_from: int|null}|WP_Error
 */
function frm_create_form_fresh(string $name, array $input): array|WP_Error
{
    $clean_name = frm_strip_crlf(sanitize_text_field($name));
    $description = isset($input['description']) ? sanitize_textarea_field((string) $input['description']) : '';
    $status = isset($input['status']) ? (string) $input['status'] : 'published';
    $is_template = isset($input['is_template']) && (bool) (int) $input['is_template'] ? 1 : 0;

    $form_key = isset($input['form_key']) ? sanitize_key((string) $input['form_key']) : '';
    if ($form_key === '') {
        $form_key = sanitize_title($clean_name);
    }

    $new_id = FrmForm::create([
        'name' => $clean_name,
        'form_key' => $form_key,
        'description' => $description,
        'status' => $status,
        'is_template' => $is_template,
        'parent_form_id' => 0,
        'options' => [],
        'field_options' => [],
    ]);
    if (!is_int($new_id) || $new_id <= 0) {
        return frm_error(code: 'formidable_create_failed', message: 'FrmForm::create returned a non-positive id.');
    }

    return frm_create_form_response($new_id, source_id: null);
}

/**
 * Duplicate path: clone source, apply caller overrides (when supplied).
 *
 * @param array<string, mixed> $input
 * @return array{form: array{id: int, form_key: string, name: string, description: string, status: string, parent_form_id: int, is_template: bool, created_at: string}, duplicated_from: int|null}|WP_Error
 */
function frm_create_form_via_duplicate(int $source_id, array $input): array|WP_Error
{
    $source = frm_form_or_error($source_id);
    if ($source instanceof WP_Error) {
        return $source;
    }

    $new_id = FrmForm::duplicate(id: $source_id, template: false, copy_keys: false);
    if (!is_int($new_id) || $new_id <= 0) {
        return frm_error(
            code: 'formidable_duplicate_failed',
            message: "FrmForm::duplicate returned a non-positive id for source form {$source_id}.",
        );
    }

    /** @var array<string, mixed> $overrides */
    $overrides = [];
    if (isset($input['name'])) {
        $overrides['name'] = frm_strip_crlf(sanitize_text_field((string) $input['name']));
    }
    if (isset($input['form_key'])) {
        $overrides['form_key'] = sanitize_key((string) $input['form_key']);
    }
    if (isset($input['description'])) {
        $overrides['description'] = sanitize_textarea_field((string) $input['description']);
    }
    if (isset($input['status'])) {
        $overrides['status'] = (string) $input['status'];
    }
    if (isset($input['is_template'])) {
        $overrides['is_template'] = (bool) (int) $input['is_template'] ? 1 : 0;
    }

    if ($overrides !== []) {
        FrmForm::update($new_id, $overrides);
    }

    return frm_create_form_response($new_id, source_id: $source_id);
}

/**
 * Build the standard create-form response envelope by re-fetching the row.
 *
 * @return array{form: array{id: int, form_key: string, name: string, description: string, status: string, parent_form_id: int, is_template: bool, created_at: string}, duplicated_from: int|null}|WP_Error
 */
function frm_create_form_response(int $form_id, ?int $source_id): array|WP_Error
{
    $row = FrmForm::getOne($form_id);
    if ($row === false) {
        return frm_error(
            code: 'formidable_create_failed',
            message: "Could not re-fetch newly created form {$form_id}.",
            status: 500,
        );
    }
    return [
        'form' => frm_form_to_compact_row($row),
        'duplicated_from' => $source_id,
    ];
}
