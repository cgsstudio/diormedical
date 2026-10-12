<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use FluentForm\App\Models\Form;
use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/fluentforms-duplicate-form', [
    'label' => __('Duplicate a Fluent Forms Form', domain: 'novamira-pro'),
    'description' => __(
        'Clone an existing Fluent Forms form. Required: form_id. Optional: title — when omitted, the duplicate is titled "{original title} (copy)". Optional: include_meta (default true) — when true, copies every form_meta row so notifications, confirmations, settings, and conditional logic transfer to the clone. Submissions are NEVER copied. Returns the freshly-created form (compact shape). Capability: fluentform_forms_manager.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'title' => [
                'type' => 'string',
                'description' => 'Optional title for the duplicate. Defaults to "{source title} (copy)".',
            ],
            'include_meta' => [
                'type' => 'boolean',
                'description' => 'When true (default), copy every form_meta row (notifications, confirmation, settings, conditions, integrations) into the duplicate.',
                'default' => true,
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form' => ['type' => 'object'],
            'source_form_id' => ['type' => 'integer'],
            'meta_copied' => ['type' => 'integer'],
        ],
        'required' => ['form', 'source_form_id', 'meta_copied'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_duplicate_form',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Submissions are never duplicated (the clone starts with zero entries). When include_meta=true (default), the clone inherits notifications, confirmation, integrations feeds, and conditional logic. Capability: fluentform_forms_manager. The form_id in the response.form is the NEW id (the duplicate); source_form_id is the original.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{form: array<string, mixed>, source_form_id: int, meta_copied: int}|WP_Error
 */
function fluentforms_duplicate_form(array $input): array|WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $source = ff_form_or_error($input['form_id'] ?? null);
    if ($source instanceof WP_Error) {
        return $source;
    }
    $cap = ff_capability_or_error('fluentform_forms_manager', (int) $source->id);
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $title = ff_resolve_duplicate_title($input, fallback: ff_str($source->title));
    $include_meta = !isset($input['include_meta']) || $input['include_meta'] === true;

    try {
        $clone = Form::create([
            'title' => $title,
            'status' => ff_str($source->status),
            'type' => ff_str($source->type),
            'form_fields' => ff_str($source->form_fields),
            'has_payment' => (int) $source->has_payment,
            'has_conversion' => (int) $source->has_conversion,
            'appearance_settings' => ff_str($source->appearance_settings),
            'conditions' => ff_str($source->conditions),
            'created_by' => (string) get_current_user_id(),
        ]);
    } catch (Throwable $e) {
        return ff_operation_failed('Failed to duplicate form: ' . $e->getMessage());
    }
    if ((int) $clone->id <= 0) {
        return ff_operation_failed('Fluent Forms did not return a cloned form.');
    }

    $meta_copied = $include_meta ? ff_copy_form_meta((int) $source->id, (int) $clone->id) : 0;
    ff_bootstrap_default_form_meta((int) $clone->id);

    ff_after_form_save((int) $clone->id, event: 'duplicated');
    ff_clear_form_cache((int) $clone->id);

    return [
        'form' => ff_form_row($clone),
        'source_form_id' => (int) $source->id,
        'meta_copied' => $meta_copied,
    ];
}

/**
 * Pick the title for the duplicate: caller-supplied (trimmed) if non-empty,
 * else "{source title} (copy)". A trailing "(copy)" on the source becomes
 * "(copy 2)", "(copy 3)", … so repeated duplicates stay distinguishable.
 *
 * @param array<string, mixed> $input
 */
function ff_resolve_duplicate_title(array $input, string $fallback): string
{
    if (isset($input['title']) && is_string($input['title']) && trim($input['title']) !== '') {
        return trim($input['title']);
    }
    if ($fallback === '') {
        return '(copy)';
    }
    return $fallback . ' (copy)';
}

/**
 * Copy every form_meta row from the source form to the clone. Returns the
 * count of rows inserted. Errors on individual rows are skipped (counted as
 * zero) so a single bad blob doesn't abort the whole duplication.
 */
function ff_copy_form_meta(int $source_id, int $target_id): int
{
    if ($source_id <= 0 || $target_id <= 0 || !function_exists('wpFluent')) {
        return 0;
    }
    $rows = ff_rows_to_array(wpFluent()->table('fluentform_form_meta')->where('form_id', $source_id)->get());
    $count = 0;
    foreach ($rows as $row) {
        $meta_key = ff_row_str($row, column: 'meta_key');
        if ($meta_key === '') {
            continue;
        }
        // `_total_views` is a per-form analytics counter — FF's own Duplicator
        // excludes it (whereNot _total_views), so the clone must not inherit
        // the source's view count and skew its conversion-rate stats.
        if ($meta_key === '_total_views') {
            continue;
        }
        // Conversational-form CSS/meta scope their selectors to the source
        // form id (`ff_conv_app_<id>`); FF's Duplicator rewrites these to the
        // new id. Without it the clone's generated CSS targets the SOURCE
        // form's wrapper. Only the conversational rows carry the token.
        $value = ff_row_str($row, column: 'value');
        if ($meta_key === 'ffc_form_settings_generated_css' || $meta_key === 'ffc_form_settings_meta') {
            $value = str_replace('ff_conv_app_' . $source_id, 'ff_conv_app_' . $target_id, $value);
        }
        $inserted = wpFluent()
            ->table('fluentform_form_meta')
            ->insert([
                'form_id' => $target_id,
                'meta_key' => $meta_key,
                'value' => $value,
            ]);
        if ($inserted) {
            ++$count;
        }
    }
    return $count;
}
