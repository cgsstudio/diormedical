<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-duplicate-form', [
    'label' => __('Duplicate WPForms Form', domain: 'novamira-pro'),
    'description' => __(
        'Duplicate an existing WPForms form. Required: form_id. Optional: title (defaults to "<original title> (Copy)") and status (defaults to "draft" — duplicates start unpublished). The new form copies fields, settings, notifications, confirmations, payments and meta from the source.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Source WPForms form id.',
            ],
            'title' => [
                'type' => 'string',
                'description' => 'Title for the duplicate. Defaults to the original title with " (Copy)" appended.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'default' => 'draft',
                'description' => 'Initial post_status of the duplicate (defaults to draft so the copy does not publish silently).',
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'source_id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'date_created' => ['type' => 'string'],
            'date_modified' => ['type' => 'string'],
            'entry_count' => ['type' => ['integer', 'null']],
        ],
        'required' => [
            'id',
            'source_id',
            'title',
            'slug',
            'status',
            'date_created',
            'date_modified',
            'entry_count',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_duplicate_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Copies the full form definition (fields + settings + notifications + confirmations + payments + meta). Entries (Pro) are NOT copied. New form starts as a draft unless status is overridden so the duplicate does not publish silently.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{form_id: int, title?: string, status?: string} $input
 * @return array{id: int, source_id: int, title: string, slug: string, status: string, date_created: string, date_modified: string, entry_count: int|null}|WP_Error
 */
function wpforms_duplicate_form(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }
    $cap_error = wpforms_capability_or_error('wpforms_create_forms');
    if ($cap_error !== null) {
        return $cap_error;
    }

    $source = wpforms_form_or_error($input['form_id']);
    if ($source instanceof WP_Error) {
        return $source;
    }

    $title = isset($input['title']) ? trim($input['title']) : '';
    if ($title === '') {
        $title = $source->post_title . ' (Copy)';
    }
    $status = ($input['status'] ?? 'draft') === 'publish' ? 'publish' : 'draft';

    $blob = wpforms_decode_blob($source);
    if ($blob instanceof WP_Error) {
        return $blob;
    }
    $settings = wpforms_section($blob, key: 'settings');
    $settings['form_title'] = $title;
    $blob['settings'] = $settings;

    $encoded = wpforms_encode_form($blob);
    if ($encoded instanceof WP_Error) {
        return $encoded;
    }

    $inserted = wp_insert_post([
        'post_type' => 'wpforms',
        'post_title' => $title,
        'post_status' => $status,
        'post_excerpt' => $source->post_excerpt,
        'post_content' => $encoded,
    ], wp_error: true);
    if ($inserted instanceof WP_Error) {
        return $inserted;
    }

    $persisted = wpforms_persist_form($inserted, $blob);
    if ($persisted instanceof WP_Error) {
        wp_delete_post($inserted, force_delete: true);
        return $persisted;
    }

    /** @var \WP_Post|null $copy */
    $copy = get_post($inserted);
    if (!$copy instanceof \WP_Post) {
        return new WP_Error('wpforms_duplicate_failed', 'Duplicated form could not be read back.', ['status' => 500]);
    }

    $row = wpforms_form_row($copy);
    return [
        'id' => $row['id'],
        'source_id' => (int) $source->ID,
        'title' => $row['title'],
        'slug' => $row['slug'],
        'status' => $row['status'],
        'date_created' => $row['date_created'],
        'date_modified' => $row['date_modified'],
        'entry_count' => $row['entry_count'],
    ];
}
