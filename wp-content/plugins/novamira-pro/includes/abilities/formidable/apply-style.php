<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/formidable-apply-style', [
    'label' => __('Apply Formidable Style', domain: 'novamira-pro'),
    'description' => __(
        "Binds a Formidable form to a style (the `frm_styles` post id whose CSS variable set governs the form's appearance). Writes `options.custom_style = <style_id>` on the form via FrmForm::update. The style_id MUST resolve to an existing `frm_styles` post — otherwise rejected with formidable_invalid_input. Idempotent: re-applying the same style returns applied=false (no-op). This is the ONLY supported path for style binding; edit-form-settings explicitly rejects custom_style in its input.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'style_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['form_id', 'style_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'style_id' => ['type' => 'integer'],
            'applied' => [
                'type' => 'boolean',
                'description' => 'True when the binding changed; false on an idempotent re-call (style_id was already bound).',
            ],
            'previous_style_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Style id that was bound BEFORE this call. Null when no style was bound previously.',
            ],
        ],
        'required' => ['form_id', 'style_id', 'applied', 'previous_style_id'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_apply_style',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Binds a form to a style. style_id MUST come from list-styles. Idempotent re-call returns applied=false. The previous binding (if any) is reported as previous_style_id so the agent can revert. Reversible binding — not destructive.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id?: int|string, style_id?: int|string} $input
 * @return array{form_id: int, style_id: int, applied: bool, previous_style_id: int|null}|WP_Error
 */
function formidable_apply_style(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_change_settings');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $style_id = frm_to_int($input['style_id'] ?? 0);
    if ($style_id <= 0) {
        return frm_invalid_input('`style_id` must be a positive integer.');
    }

    // @mago-expect analyzer:mixed-assignment
    $style_post = get_post($style_id);
    if (!$style_post instanceof WP_Post || $style_post->post_type !== FRM_STYLE_POST_TYPE) {
        return frm_invalid_input("style_id {$style_id} does not exist or is not a `frm_styles` post.");
    }

    $form_id = (int) $form->id;
    $current_options = frm_decode_form_options($form);
    $previous = isset($current_options['custom_style']) ? (int) $current_options['custom_style'] : 0;

    if ($previous === $style_id) {
        return [
            'form_id' => $form_id,
            'style_id' => $style_id,
            'applied' => false,
            'previous_style_id' => $previous > 0 ? $previous : null,
        ];
    }

    $current_options['custom_style'] = (string) $style_id;

    // We bypass FrmForm::update() here because its update_form_options()
    // helper filters the options array against a known-keys schema and
    // silently drops `custom_style` — the exact behaviour we need to write.
    // FrmStylesController applies the binding via a direct $wpdb->update()
    // for the same reason (see formidable/classes/controllers/FrmStylesController.php
    // around line 552). The cache invalidation below covers the
    // hand-rolled write.
    global $wpdb;
    /** @var \wpdb $wpdb */
    $updated = $wpdb->update(
        $wpdb->prefix . 'frm_forms',
        ['options' => maybe_serialize($current_options)],
        ['id' => $form_id],
        ['%s'],
        ['%d'],
    );
    if ($updated === false) {
        return frm_error(
            code: 'formidable_apply_style_failed',
            message: "wpdb->update returned false for form {$form_id}.",
        );
    }

    frm_invalidate_form_caches($form_id);

    return [
        'form_id' => $form_id,
        'style_id' => $style_id,
        'applied' => true,
        'previous_style_id' => $previous > 0 ? $previous : null,
    ];
}
