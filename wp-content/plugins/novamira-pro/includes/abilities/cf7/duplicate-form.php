<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Cf7;

use WP_Error;
use WPCF7_ContactForm;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/cf7-duplicate-form', [
    'label' => __('Duplicate Contact Form 7 Form', domain: 'novamira-pro'),
    'description' => __(
        'Clones an existing Contact Form 7 form into a new row. Every canonical property (`form`, `mail`, `mail_2`, `messages`, `additional_settings`, `locale`) is copied verbatim; the new form receives a fresh hash and a new id. Optional `title` overrides the duplicate\'s post_title (defaults to the source title plus " (copy)"). Useful when an agent wants to A/B a contact form, localise an existing form for a new region, or stage edits without touching the live original.',
        domain: 'novamira-pro',
    ),
    'category' => 'cf7',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'source_id' => ['type' => 'integer', 'description' => 'Id of the form to duplicate.'],
            'title' => [
                'type' => 'string',
                'description' => 'Override the duplicate\'s post_title. When omitted, defaults to "<source title> (copy)".',
            ],
            'status' => [
                'type' => 'string',
                'description' => 'CPT post_status for the duplicate. One of publish | draft. Defaults to draft so the agent can review before going live.',
                'enum' => ['publish', 'draft'],
            ],
        ],
        'required' => ['source_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'form' => ['type' => 'object'],
            'source_id' => ['type' => 'integer'],
            'error' => ['type' => 'string'],
            'error_code' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Cf7\\cf7_duplicate_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to clone a tested form and then edit the copy (cf7-edit-form) instead of risking changes on the original. Defaults the duplicate to status=draft so it does not surface live until the agent (or user) explicitly publishes it.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{source_id?: mixed, title?: string, status?: string} $input
 * @return array<string, mixed>
 */
function cf7_duplicate_form(array $input): array
{
    if (!isset($input['source_id']) || !is_int($input['source_id']) || $input['source_id'] <= 0) {
        return [
            'success' => false,
            'error' => 'source_id must be a positive integer.',
            'error_code' => 'cf7_invalid_input',
        ];
    }
    $source_id = $input['source_id'];
    $status = $input['status'] ?? 'draft';
    // Defense-in-depth: the schema already restricts `status` to
    // [publish, draft], but a direct PHP caller bypassing the schema
    // could pass `'trash'` / `'junk'` / arbitrary strings (round 2 audit
    // L2-A8 / L3-A8). Refuse anything outside the allow-list rather
    // than letting wp_update_post persist it.
    if (!in_array($status, ['publish', 'draft'], strict: true)) {
        return [
            'success' => false,
            'error' => "status must be 'publish' or 'draft'.",
            'error_code' => 'cf7_invalid_input',
        ];
    }
    // Strip NUL bytes from the title override (round 2 audit L2-A8).
    if (isset($input['title'])) {
        $input['title'] = str_replace(search: "\x00", replace: '', subject: $input['title']);
    }

    $source = cf7_form_or_error($source_id);
    if ($source instanceof WP_Error) {
        return cf7_failure_from_wp_error($source);
    }

    $title_override = isset($input['title']) && $input['title'] !== '' ? $input['title'] : $source->title() . ' (copy)';

    // CF7 5.x+ ships a `copy()` helper that returns an unsaved clone of
    // the form with a fresh title and a blank id. The minimum supported
    // version (NOVAMIRA_PRO_CF7_MIN_VERSION) covers that.
    $clone = $source->copy();
    // Override the clone title BEFORE save() so wp_insert_post sees the
    // final title — without this every duplicate of the same source
    // collides on the same `<source> (copy)` slug-base, forcing
    // wp_unique_post_slug() into O(N²) probe queries on burst (round 2
    // audit L4-A8). CF7 stores `title` as a public property on the
    // contact-form object, so direct assignment is sufficient.
    cf7_assign_clone_title($clone, $title_override);
    $clone_id = $clone->save();
    if ($clone_id === 0) {
        return ['success' => false, 'error' => 'CF7 form duplicate save failed.', 'error_code' => 'cf7_save_failed'];
    }

    wp_update_post([
        'ID' => $clone_id,
        'post_status' => $status,
    ]);

    $reloaded = WPCF7_ContactForm::get_instance($clone_id);
    if ($reloaded === null) {
        return [
            'success' => false,
            'error' => sprintf('Duplicate form id %d could not be reloaded.', $clone_id),
            'error_code' => 'cf7_form_not_found',
        ];
    }

    return [
        'success' => true,
        'source_id' => $source_id,
        'form' => cf7_form_to_full($reloaded, cf7_default_max_field_bytes()),
    ];
}
