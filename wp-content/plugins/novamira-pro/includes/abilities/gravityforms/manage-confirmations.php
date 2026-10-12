<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ─── list-confirmations ───────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-list-confirmations', [
    'label' => __('List Gravity Forms Confirmations', domain: 'novamira-pro'),
    'description' => __(
        'List every confirmation configured on a Gravity Forms form, keyed by id. Confirmations decide what the visitor sees after submitting: a message, a redirect to a page, or a redirect to an arbitrary URL.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'count' => ['type' => 'integer'],
            'confirmations' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'count', 'confirmations'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_list_confirmations',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the confirmations map verbatim — keys are GF-generated 13-char ids, values are confirmation configs (name, type, message, url, pageId, conditionalLogic, …). A brand-new form has zero confirmations until create-confirmation is called.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int} $input
 * @return array{form_id: int, count: int, confirmations: array<array-key, mixed>}|WP_Error
 */
function gravityforms_list_confirmations(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $confirmations = isset($form['confirmations']) && is_array($form['confirmations']) ? $form['confirmations'] : [];
    return [
        'form_id' => (int) $form['id'],
        'count' => count($confirmations),
        'confirmations' => $confirmations,
    ];
}

// ─── create-confirmation ──────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-create-confirmation', [
    'label' => __('Create Gravity Forms Confirmation', domain: 'novamira-pro'),
    'description' => __(
        'Append a confirmation to a Gravity Forms form. Required: form_id, name, type (message | page | redirect). For type=message, pass `message` (defaults to "Thanks for contacting us!"); for type=page, pass `pageId` (a published page must exist); for type=redirect, pass `url` (must be a valid absolute URL). New confirmation gets a fresh 13-char id.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'name' => ['type' => 'string', 'minLength' => 1, 'description' => 'Internal name shown in the GF admin.'],
            'type' => ['type' => 'string', 'enum' => ['message', 'page', 'redirect']],
            'message' => [
                'type' => 'string',
                'description' => 'For type=message. {all_fields} expands at submission time.',
            ],
            'pageId' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'For type=page. Must be a published page.',
            ],
            'url' => ['type' => 'string', 'description' => 'For type=redirect. Must be a valid absolute URL.'],
            'queryString' => [
                'type' => 'string',
                'description' => 'Optional query string appended on redirect (e.g. "name={Name:1}").',
            ],
        ],
        'required' => ['form_id', 'name', 'type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'confirmation_id' => ['type' => 'string'],
            'confirmation' => ['type' => 'object'],
        ],
        'required' => ['form_id', 'confirmation_id', 'confirmation'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_create_confirmation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'type=page requires a valid published page id (validated at create time); type=redirect requires a valid absolute URL. type=message uses {all_fields} as default body when not specified.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     name: string,
 *     type: string,
 *     message?: string,
 *     pageId?: int,
 *     url?: string,
 *     queryString?: string,
 * } $input
 * @return array{form_id: int, confirmation_id: string, confirmation: array<array-key, mixed>}|WP_Error
 */
function gravityforms_create_confirmation(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_edit_forms']);
    if ($cap !== null) {
        return $cap;
    }

    $type_error = gravityforms_validate_confirmation_payload($input);
    if ($type_error !== null) {
        return $type_error;
    }

    $confirmations = isset($form['confirmations']) && is_array($form['confirmations']) ? $form['confirmations'] : [];
    $id = uniqid();
    $confirmation = gravityforms_build_confirmation($id, $input);
    $confirmations[$id] = $confirmation;
    $form['confirmations'] = $confirmations;

    $result = \GFAPI::update_form($form, (int) $form['id']);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'form_id' => (int) $form['id'],
        'confirmation_id' => $id,
        'confirmation' => $confirmation,
    ];
}

// ─── edit-confirmation ────────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-edit-confirmation', [
    'label' => __('Edit Gravity Forms Confirmation', domain: 'novamira-pro'),
    'description' => __(
        'Edit a confirmation on a Gravity Forms form. Pass any of: name, type, message, pageId, url, queryString. Switching type validates the new payload (e.g. switching to page requires a valid pageId).',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'confirmation_id' => ['type' => 'string', 'minLength' => 1],
            'name' => ['type' => 'string'],
            'type' => ['type' => 'string', 'enum' => ['message', 'page', 'redirect']],
            'message' => ['type' => 'string'],
            'pageId' => ['type' => 'integer', 'minimum' => 1],
            'url' => ['type' => 'string'],
            'queryString' => ['type' => 'string'],
        ],
        'required' => ['form_id', 'confirmation_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'confirmation_id' => ['type' => 'string'],
            'confirmation' => ['type' => 'object'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['form_id', 'confirmation_id', 'confirmation', 'changed'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_edit_confirmation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Idempotent: a patch that produces no real change returns changed=[] without a DB write. When type changes, the patch validates the new combination (e.g. setting type=page requires pageId to be present, either in this patch or already on the confirmation).',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id: int,
 *     confirmation_id: string,
 *     name?: string,
 *     type?: string,
 *     message?: string,
 *     pageId?: int,
 *     url?: string,
 *     queryString?: string,
 * } $input
 * @return array{form_id: int, confirmation_id: string, confirmation: array<array-key, mixed>, changed: list<string>}|WP_Error
 */
function gravityforms_edit_confirmation(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_edit_forms']);
    if ($cap !== null) {
        return $cap;
    }

    $cid = $input['confirmation_id'];
    $confirmations = isset($form['confirmations']) && is_array($form['confirmations']) ? $form['confirmations'] : [];
    if (!isset($confirmations[$cid]) || !is_array($confirmations[$cid])) {
        return new WP_Error(
            'gravityforms_confirmation_not_found',
            "Confirmation id '{$cid}' not found on form " . (int) $form['id'],
            ['status' => 404],
        );
    }

    $merged = gravityforms_merge_confirmation_payload($confirmations[$cid], $input);
    $type_error = gravityforms_validate_confirmation_payload($merged);
    if ($type_error !== null) {
        return $type_error;
    }

    [$updated, $changed] = gravityforms_apply_confirmation_patch($confirmations[$cid], $input);
    if ($changed === []) {
        return [
            'form_id' => (int) $form['id'],
            'confirmation_id' => $cid,
            'confirmation' => $updated,
            'changed' => [],
        ];
    }

    $confirmations[$cid] = $updated;
    $form['confirmations'] = $confirmations;
    $result = \GFAPI::update_form($form, (int) $form['id']);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'form_id' => (int) $form['id'],
        'confirmation_id' => $cid,
        'confirmation' => $updated,
        'changed' => $changed,
    ];
}

// ─── delete-confirmation ──────────────────────────────────────────────────

wp_register_ability('novamira/gravityforms-delete-confirmation', [
    'label' => __('Delete Gravity Forms Confirmation', domain: 'novamira-pro'),
    'description' => __(
        'Remove a confirmation from a Gravity Forms form by id. Returns the removed confirmation for confirmation. A missing confirmation is treated as idempotent success.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'confirmation_id' => ['type' => 'string', 'minLength' => 1],
        ],
        'required' => ['form_id', 'confirmation_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'confirmation_id' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
            'removed_confirmation' => ['type' => ['object', 'null']],
            'remaining_count' => ['type' => 'integer'],
        ],
        'required' => ['form_id', 'confirmation_id', 'deleted', 'removed_confirmation', 'remaining_count'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_delete_confirmation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Idempotent: missing confirmation_id returns deleted=true with removed_confirmation=null and no DB write.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, confirmation_id: string} $input
 * @return array{form_id: int, confirmation_id: string, deleted: bool, removed_confirmation: array<array-key, mixed>|null, remaining_count: int}|WP_Error
 */
function gravityforms_delete_confirmation(array $input): array|WP_Error
{
    $form = gravityforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = gravityforms_capability_or_error(['gravityforms_edit_forms']);
    if ($cap !== null) {
        return $cap;
    }

    $cid = $input['confirmation_id'];
    $confirmations = isset($form['confirmations']) && is_array($form['confirmations']) ? $form['confirmations'] : [];
    if (!isset($confirmations[$cid])) {
        return [
            'form_id' => (int) $form['id'],
            'confirmation_id' => $cid,
            'deleted' => true,
            'removed_confirmation' => null,
            'remaining_count' => count($confirmations),
        ];
    }

    $removed = is_array($confirmations[$cid]) ? $confirmations[$cid] : [];
    unset($confirmations[$cid]);
    $form['confirmations'] = $confirmations;
    $result = \GFAPI::update_form($form, (int) $form['id']);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'form_id' => (int) $form['id'],
        'confirmation_id' => $cid,
        'deleted' => true,
        'removed_confirmation' => $removed,
        'remaining_count' => count($confirmations),
    ];
}

// ─── shared helpers ───────────────────────────────────────────────────────

/**
 * Validate a confirmation payload according to its type. For type=page the
 * page id must reference a published page; for type=redirect the URL must
 * be a valid absolute URL.
 *
 * @param array<array-key, mixed> $payload
 */
function gravityforms_validate_confirmation_payload(array $payload): ?WP_Error
{
    $type = (string) ($payload['type'] ?? 'message');
    if ($type === 'page') {
        $page_id = isset($payload['pageId']) ? (int) $payload['pageId'] : 0;
        if ($page_id <= 0) {
            return gravityforms_invalid_input('type=page requires a positive pageId.');
        }
        /** @var \WP_Post|array<string, mixed>|null $post */
        $post = get_post($page_id);
        if (!$post instanceof \WP_Post || $post->post_type !== 'page' || $post->post_status !== 'publish') {
            return gravityforms_invalid_input(
                "pageId {$page_id} is not a published page — agents must reference an existing public page.",
            );
        }
    }
    if ($type === 'redirect') {
        $url = (string) ($payload['url'] ?? '');
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return gravityforms_invalid_input('type=redirect requires a valid absolute URL.');
        }
    }
    return null;
}

/**
 * Merge an edit-confirmation patch on top of the existing confirmation so we
 * can validate the COMBINED shape before persisting (e.g. switching type=page
 * needs to know whether the patch includes a pageId).
 *
 * @param array<array-key, mixed> $existing
 * @param array<array-key, mixed> $patch
 * @return array<array-key, mixed>
 */
function gravityforms_merge_confirmation_payload(array $existing, array $patch): array
{
    $merged = $existing;
    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        if ($key === 'form_id' || $key === 'confirmation_id') {
            continue;
        }
        $merged[$key] = $value;
    }
    return $merged;
}

/**
 * Build a confirmation entry from create-confirmation input.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function gravityforms_build_confirmation(string $id, array $input): array
{
    $type = (string) ($input['type'] ?? 'message');
    $confirmation = [
        'id' => $id,
        'name' => trim((string) ($input['name'] ?? '')),
        'isDefault' => false,
        'type' => $type,
        'conditionalLogic' => [],
    ];
    if ($type === 'message') {
        $confirmation['message'] = (string) (
            $input['message'] ?? 'Thanks for contacting us! We will get in touch with you shortly.'
        );
        $confirmation['disableAutoformat'] = false;
    }
    if ($type === 'page') {
        $confirmation['pageId'] = (int) ($input['pageId'] ?? 0);
        $confirmation['queryString'] = (string) ($input['queryString'] ?? '');
    }
    if ($type === 'redirect') {
        $confirmation['url'] = (string) ($input['url'] ?? '');
        $confirmation['queryString'] = (string) ($input['queryString'] ?? '');
    }
    return $confirmation;
}

/**
 * Apply a partial confirmation-edit patch. Returns [updated, changed-keys].
 *
 * @param array<array-key, mixed> $confirmation
 * @param array<array-key, mixed> $input
 * @return array{0: array<array-key, mixed>, 1: list<string>}
 */
function gravityforms_apply_confirmation_patch(array $confirmation, array $input): array
{
    /** @var list<string> $changed */
    $changed = [];
    $string_keys = ['name', 'type', 'message', 'url', 'queryString'];
    foreach ($string_keys as $key) {
        if (!isset($input[$key])) {
            continue;
        }
        $val = (string) $input[$key];
        if (($confirmation[$key] ?? null) !== $val) {
            $confirmation[$key] = $val;
            $changed[] = $key;
        }
    }
    if (isset($input['pageId'])) {
        $val = (int) $input['pageId'];
        if (($confirmation['pageId'] ?? null) !== $val) {
            $confirmation['pageId'] = $val;
            $changed[] = 'pageId';
        }
    }
    return [$confirmation, $changed];
}
