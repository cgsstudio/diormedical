<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-get-field-types-schema', [
    'label' => __('List WPForms Field Types', domain: 'novamira-pro'),
    'description' => __(
        'Return the catalog of every WPForms field type the integration can build via add-field / edit-field on this install. Output is compact: per entry {type, label, category, pro_only}. Use this FIRST when constructing a form, then call novamira/wpforms-get-field-type-schema for the per-type settings whitelist + supports[]. The catalog ALWAYS includes Pro entries — even on a Lite install — so the agent can learn what would unlock with Pro; add-field still rejects Pro-only types on a Lite install via the wpforms_pro_required error. Categories reflect the WPForms field-editor groupings: standard (text / textarea / select / checkbox / radio / number / hidden / …), structure (layout / pagebreak / divider / html / repeater), and payment (payment-* / credit-card). Mirrors the catalog-vs-detail pattern used by `gravityforms-get-field-types-schema`, `fluentforms-get-field-types-schema`, `formidable-get-field-types-schema`.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'types' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'type' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                        'pro_only' => ['type' => 'boolean'],
                    ],
                    'required' => ['type', 'label', 'category', 'pro_only'],
                ],
            ],
            'count' => ['type' => 'integer'],
            'pro_active' => ['type' => 'boolean'],
        ],
        'required' => ['types', 'count', 'pro_active'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_get_field_types_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Cache for the session — the catalog is stable. Branch on each entry\'s pro_only flag to know what would unlock with Pro. For per-type detail (supports[], settings whitelist, example_input), call get-field-type-schema (singular) with the chosen type.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{types: list<array{type:string,label:string,category:string,pro_only:bool}>, count: int, pro_active: bool}|WP_Error
 */
function wpforms_get_field_types_schema(): array|WP_Error
{
    $setup = wpforms_detect_setup();
    if (!$setup['active']) {
        return wpforms_not_active();
    }
    $pro_active = $setup['pro'];
    $lite = wpforms_lite_field_types();
    $pro = wpforms_pro_field_types();

    /** @var list<array{type:string,label:string,category:string,pro_only:bool}> $types */
    $types = [];
    foreach ($lite as $type_slug) {
        $types[] = [
            'type' => $type_slug,
            'label' => wpforms_field_type_label($type_slug),
            'category' => wpforms_field_type_category($type_slug),
            'pro_only' => false,
        ];
    }
    foreach ($pro as $type_slug) {
        $types[] = [
            'type' => $type_slug,
            'label' => wpforms_field_type_label($type_slug),
            'category' => wpforms_field_type_category($type_slug),
            'pro_only' => true,
        ];
    }
    usort($types, static fn(array $a, array $b): int => strcmp($a['type'], $b['type']));
    return [
        'types' => $types,
        'count' => count($types),
        'pro_active' => $pro_active,
    ];
}

/**
 * Human-readable label for a WPForms field type slug. WPForms hides its
 * editor-facing labels behind a Vue admin bundle (no PHP getter
 * comparable to GF_Field::get_form_editor_field_title()), so we
 * hard-code the canonical labels used in the WPForms field-picker
 * sidebar. Falls back to a slug-derived title-case label for types
 * registered by third-party add-ons.
 */
// @mago-expect lint:no-literal-password
function wpforms_field_type_label(string $type_slug): string
{
    $labels = [
        'text' => 'Single Line Text',
        'textarea' => 'Paragraph Text',
        'select' => 'Dropdown',
        'checkbox' => 'Checkboxes',
        'radio' => 'Multiple Choice',
        'name' => 'Name',
        'email' => 'Email',
        'number' => 'Number',
        'number-slider' => 'Number Slider',
        'gdpr-checkbox' => 'GDPR Agreement',
        'internal-information' => 'Internal Information',
        'divider' => 'Section Divider',
        'html' => 'HTML',
        'content' => 'Content',
        'pagebreak' => 'Page Break',
        'hidden' => 'Hidden Field',
        'address' => 'Address',
        'date-time' => 'Date / Time',
        'phone' => 'Phone',
        'rating' => 'Rating',
        'richtext' => 'Rich Text',
        'url' => 'Website / URL',
        'file-upload' => 'File Upload',
        'camera' => 'Camera',
        'password' => 'Password',
        'signature' => 'Signature',
        'credit-card' => 'Credit Card',
        'payment-single' => 'Single Item',
        'payment-multiple' => 'Multiple Items',
        'payment-checkbox' => 'Checkbox Items',
        'payment-select' => 'Dropdown Items',
        'payment-total' => 'Total',
        'entry-preview' => 'Entry Preview',
        'repeater' => 'Repeater',
        'layout' => 'Layout',
        'custom-captcha' => 'Custom Captcha',
    ];
    if (isset($labels[$type_slug])) {
        return $labels[$type_slug];
    }
    return ucwords(str_replace(['_', '-'], replace: ' ', subject: $type_slug));
}

/**
 * Classifies a WPForms field type into one of three editor groups:
 * standard (single value inputs and choice fields), structure (layout
 * controls, dividers, pagebreaks, repeater container, html), and
 * payment (payment-* + credit-card). Hard-coded because WPForms exposes
 * the category only inside the Vue admin and not via PHP.
 */
function wpforms_field_type_category(string $type_slug): string
{
    $structure = [
        'divider',
        'html',
        'content',
        'pagebreak',
        'internal-information',
        'layout',
        'repeater',
        'entry-preview',
    ];
    $payment = [
        'credit-card',
        'payment-single',
        'payment-multiple',
        'payment-checkbox',
        'payment-select',
        'payment-total',
    ];
    if (in_array($type_slug, $structure, strict: true)) {
        return 'structure';
    }
    if (in_array($type_slug, $payment, strict: true)) {
        return 'payment';
    }
    return 'standard';
}
