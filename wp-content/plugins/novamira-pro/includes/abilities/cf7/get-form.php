<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Cf7;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/cf7-get-form', [
    'label' => __('Get Contact Form 7 Form', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Contact Form 7 form by id, including the full template (`form`), both mail configurations (`mail` and `mail_2`, with `mail_2.active=false` when the secondary mail is disabled), all message strings (`messages`), the additional-settings blob, locale and hash. Large text fields (`form`, `mail.body`, `mail_2.body`, `additional_settings`) are truncated at `max_field_bytes` (default 8192) per field with a `*_truncated` flag and `*_bytes` (original byte length) so the agent can decide whether to re-read at a higher cap. Set `max_field_bytes` to 0 to disable truncation. Returns WP_Error `cf7_form_not_found` when the id does not exist or refers to a trashed / auto-draft / non-CF7 post.',
        domain: 'novamira-pro',
    ),
    'category' => 'cf7',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Form id from cf7-list-forms.'],
            'max_field_bytes' => [
                'type' => 'integer',
                'description' => 'Per-field byte cap for the large text properties (`form`, `mail.body`, `mail_2.body`, `additional_settings`). Default 8192. Set to 0 to disable truncation entirely.',
                'minimum' => 0,
                'default' => 8192,
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'locale' => ['type' => 'string'],
            'hash' => ['type' => 'string'],
            'tag_count' => ['type' => 'integer'],
            'modified' => ['type' => 'string'],
            'form' => ['type' => 'string'],
            'form_truncated' => ['type' => 'boolean'],
            'form_bytes' => ['type' => 'integer'],
            'mail' => ['type' => 'object'],
            'mail_2' => ['type' => 'object'],
            'messages' => ['type' => 'object'],
            'additional_settings' => ['type' => 'string'],
            'additional_settings_truncated' => ['type' => 'boolean'],
            'additional_settings_bytes' => ['type' => 'integer'],
        ],
        'required' => [
            'id',
            'title',
            'slug',
            'status',
            'locale',
            'hash',
            'tag_count',
            'form',
            'mail',
            'mail_2',
            'messages',
            'additional_settings',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Cf7\\cf7_get_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass the integer id from cf7-list-forms. The response includes the full template + mail config — use this only when you need to read or are about to edit. To browse, prefer cf7-list-forms. Re-call with a larger max_field_bytes (or 0) when *_truncated=true and you need the full body.',
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
function cf7_get_form(array $input): array|WP_Error
{
    // `rest_is_integer()` accepts numeric strings, so a stringly-typed
    // id passes the JSON-schema layer and then trips a TypeError inside
    // strict_types code paths. Guard explicitly (round 1 audit P4 GAP-A).
    $id = isset($input['id']) && is_int($input['id']) ? $input['id'] : 0;
    if ($id <= 0) {
        return cf7_error('cf7_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    $max_field_bytes = isset($input['max_field_bytes']) && is_int($input['max_field_bytes'])
        ? max(0, $input['max_field_bytes'])
        : cf7_default_max_field_bytes();

    $form = cf7_form_or_error($id);
    if ($form instanceof WP_Error) {
        return $form;
    }

    return cf7_form_to_full($form, $max_field_bytes);
}
