<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-get-field-types-schema', [
    'label' => __('List Ninja Forms Field Types', domain: 'novamira-pro'),
    'description' => __(
        'Return the catalog of every Ninja Forms field type this install can build via add-field / edit-field. Output is compact: per entry {type, label, category, pro_only}. Use this FIRST when constructing a form, then call novamira/ninja-forms-get-field-type-schema for the per-type supported-settings whitelist. The catalog is read from the live Ninja Forms field registry (Ninja_Forms()->fields), so it already reflects whichever add-ons are installed — a type only appears when the install can actually render it, so `pro_only` is always false and `pro_active` is always true (Ninja Forms has no single Lite/Pro edition split; payment / extra types come from separate add-ons and simply are or are not in the registry). `category` is the Ninja editor section (common / userinfo / misc / layout / other). Mirrors the catalog-vs-detail pattern used by `gravityforms-get-field-types-schema`, `wpforms-get-field-types-schema`, `fluentforms-get-field-types-schema`, `formidable-get-field-types-schema`.',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
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
                    'additionalProperties' => false,
                ],
            ],
            'count' => ['type' => 'integer'],
            'pro_active' => ['type' => 'boolean'],
        ],
        'required' => ['types', 'count', 'pro_active'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_get_field_types_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Cache for the session — the catalog is stable per install. For per-type detail (the supported-settings whitelist add-field accepts), call get-field-type-schema (singular) with the chosen type. Ninja Forms has no Pro/Lite gating model, so pro_only is always false and pro_active is always true; a type missing from the catalog means the add-on that provides it is not installed.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{types: list<array{type:string,label:string,category:string,pro_only:bool}>, count: int, pro_active: bool}|WP_Error
 */
function ninja_forms_get_field_types_schema(): array|WP_Error
{
    if (!ninja_forms_runtime_available()) {
        return ninja_forms_not_active();
    }
    $cap = ninja_forms_capability_or_error();
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    /** @var list<array{type:string,label:string,category:string,pro_only:bool}> $types */
    $types = [];
    foreach (ninja_forms_field_registry() as $slug => $field) {
        $types[] = [
            'type' => $slug,
            'label' => ninja_forms_field_type_label($field, $slug),
            'category' => ninja_forms_field_type_category($field),
            'pro_only' => false,
        ];
    }
    usort($types, static fn(array $a, array $b): int => strcmp($a['type'], $b['type']));

    return [
        'types' => $types,
        'count' => count($types),
        // Ninja Forms is effectively single-edition from the registry's
        // perspective — every type present is renderable. Report pro_active
        // true so cross-plugin callers using the shared catalog code path can
        // simplify their branch logic (matches the gravityforms reader).
        'pro_active' => true,
    ];
}

/**
 * Human-readable label for a Ninja Forms field type. Prefers the field's
 * `get_nicename()` (localized editor label); falls back to a slug-derived
 * title-case label for types whose nicename is empty.
 */
function ninja_forms_field_type_label(\NF_Abstracts_Field $field, string $slug): string
{
    $nicename = $field->get_nicename();
    if ($nicename !== '') {
        return $nicename;
    }
    return ucwords(str_replace(['_', '-'], replace: ' ', subject: $slug));
}

/**
 * The Ninja Forms editor section a field type belongs to (common / userinfo
 * / misc / layout). Empty sections (some structural / payment / add-on types
 * carry none) collapse to `other` so the catalog never emits a blank
 * category.
 */
function ninja_forms_field_type_category(\NF_Abstracts_Field $field): string
{
    $section = $field->get_section();
    return $section !== '' ? $section : 'other';
}
