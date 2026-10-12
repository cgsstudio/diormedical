<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-get-field-types-schema', [
    'label' => __('List Gravity Forms Field Types', domain: 'novamira-pro'),
    'description' => __(
        'Return the catalog of every Gravity Forms field type the integration can build via add-field / edit-field on this install. Output is compact: per entry {type, label, category, pro_only}. Use this FIRST when constructing a form, then call novamira/gravityforms-get-field-type-schema for the per-type settings whitelist + supports[]. Gravity Forms has no Lite/Pro license split — `pro_only` is always false; categories reflect the Gravity field-editor groupings (standard, advanced, post_fields, pricing_fields). Mirrors the catalog-vs-detail pattern used by `wpforms-get-field-types-schema`, `fluentforms-get-field-types-schema`, `formidable-get-field-types-schema`.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_get_field_types_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Cache for the session — the catalog is stable. For per-type detail (supports[], label, class), call get-field-type-schema (singular) with the chosen type. Gravity Forms has no Pro/Lite gating, so pro_only is always false and pro_active is always true.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{types: list<array{type:string,label:string,category:string,pro_only:bool}>, count: int, pro_active: bool}|WP_Error
 */
function gravityforms_get_field_types_schema(): array|WP_Error
{
    if (!gravityforms_detect_setup()['active']) {
        return gravityforms_not_active();
    }
    if (!class_exists('GF_Fields')) {
        return gravityforms_operation_failed('GF_Fields registry is unavailable.');
    }
    $registry = \GF_Fields::get_all();
    /** @var list<array{type:string,label:string,category:string,pro_only:bool}> $types */
    $types = [];
    foreach ($registry as $type_slug => $field) {
        if ($type_slug === '') {
            continue;
        }
        $types[] = [
            'type' => $type_slug,
            'label' => gravityforms_field_type_label($field, $type_slug),
            'category' => gravityforms_field_type_category($type_slug),
            'pro_only' => false,
        ];
    }
    usort($types, static fn(array $a, array $b): int => strcmp($a['type'], $b['type']));
    return [
        'types' => $types,
        'count' => count($types),
        // Gravity Forms is a single-edition plugin — license_active gates
        // form rendering at runtime but every field type is available
        // surface-wise. We report pro_active=true so callers using the
        // same code path across the form family can simplify their branch
        // logic.
        'pro_active' => true,
    ];
}

/**
 * Reads the human-readable label for a GF field type from the underlying
 * GF_Field subclass. Falls back to the slug capitalised when the field
 * has no editor title (rare, but possible on third-party add-ons).
 */
function gravityforms_field_type_label(object $field, string $type_slug): string
{
    if (method_exists($field, 'get_form_editor_field_title')) {
        /** @var mixed $label */
        $label = $field->get_form_editor_field_title();
        if (is_string($label) && $label !== '') {
            return $label;
        }
    }
    return ucwords(str_replace(['_', '-'], replace: ' ', subject: $type_slug));
}

/**
 * Classifies a GF field type into the four editor-pane groups (standard,
 * advanced, post_fields, pricing_fields). Hard-coded because GF's
 * GF_Fields registry exposes the category as a property only on a subset
 * of types — checking `get_form_editor_field_settings()` would be
 * expensive (it instantiates editor markup).
 */
function gravityforms_field_type_category(string $type_slug): string
{
    $advanced = [
        'name',
        'email',
        'phone',
        'address',
        'website',
        'date',
        'time',
        'fileupload',
        'captcha',
        'list',
        'multiselect',
        'consent',
        'password',
        'repeater',
        'image-choice',
        'multi_choice',
    ];
    $post_fields = [
        'post_title',
        'post_content',
        'post_excerpt',
        'post_tags',
        'post_category',
        'post_image',
        'post_custom_field',
    ];
    $pricing = [
        'product',
        'singleproduct',
        'hiddenproduct',
        'option',
        'shipping',
        'singleshipping',
        'quantity',
        'price',
        'total',
        'donation',
        'creditcard',
        'calculation',
    ];
    if (in_array($type_slug, $advanced, strict: true)) {
        return 'advanced';
    }
    if (in_array($type_slug, $post_fields, strict: true)) {
        return 'post_fields';
    }
    if (in_array($type_slug, $pricing, strict: true)) {
        return 'pricing_fields';
    }
    return 'standard';
}
