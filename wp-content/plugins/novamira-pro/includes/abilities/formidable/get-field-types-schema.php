<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use FrmField;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/formidable-get-field-types-schema', [
    'label' => __('List Formidable Field Types', domain: 'novamira-pro'),
    'description' => __(
        "Return the catalog of every Formidable field type this install can actually render — Lite types always, plus Pro types when Formidable Pro is active and the type is NOT an upsell for a separate add-on (entries with an `addon` key are filtered out). Internal-only types marked `hide=true` in the catalog (e.g. `submit`) are excluded so an agent never accidentally registers one via add-field. Output is compact: per entry {type, label, category, pro_only}. Use this FIRST when constructing a form, then call novamira/formidable-get-field-type-schema for the per-type detail. Pass `type` to filter to a single slug; omit to enumerate everything. A non-listed type returns formidable_invalid_input. Categories reflect the editor groupings: standard (single-value inputs and choice fields), structure (divider / end_divider / break / html / summary / captcha), payment (credit_card / product / quantity / total / coupon). Note: `dropdown` is an alias of `select` in Formidable's update path (FrmField::update at FrmField.php:742-746) — the catalog reports `select` only. Mirrors the catalog-vs-detail pattern used by `gravityforms-get-field-types-schema`, `wpforms-get-field-types-schema`, `fluentforms-get-field-types-schema`.",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Filter the catalog to a single type slug (e.g. `text`, `email`, `signature`). Omit to return every available type.',
            ],
        ],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_get_field_types_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => "Cache for the session — the catalog is stable. Branch on each entry's `pro_only` flag to know what would unlock with Pro. For per-type detail (settings whitelist, supports[], example_input), call get-field-type-schema (singular) with the chosen type. Pass `type` to filter to a single slug; a non-listed type returns formidable_invalid_input. Heads-up: Formidable internally accepts `dropdown` as an alias of `select` in its update path, but the catalog reports only the canonical `select`.",
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{type?: string} $input
 *
 * @return array{types: list<array{type:string,label:string,category:string,pro_only:bool}>, count: int, pro_active: bool}|WP_Error
 */
function formidable_get_field_types_schema(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }

    $cap = frm_capability_or_error('frm_view_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $filter = isset($input['type']) ? trim($input['type']) : null;

    $pro_active = frm_pro_active();
    /** @var list<array{type:string,label:string,category:string,pro_only:bool}> $types */
    $types = [];
    frm_collect_lite_field_types($types, $filter);
    if ($pro_active) {
        frm_collect_pro_field_types($types, $filter);
    }
    usort($types, static fn(array $a, array $b): int => strcmp($a['type'], $b['type']));

    if ($filter !== null && $filter !== '' && $types === []) {
        return frm_invalid_input(
            "Unknown field type: '{$filter}'. Call this ability without the `type` filter to list every available slug.",
        );
    }

    return [
        'types' => $types,
        'count' => count($types),
        'pro_active' => $pro_active,
    ];
}

/**
 * Walk the Lite catalog and append every accepted, non-hidden type into
 * the accumulator. Split from the main callback so the cyclomatic-
 * complexity threshold stays low.
 *
 * @param list<array{type:string,label:string,category:string,pro_only:bool}> $types
 */
function frm_collect_lite_field_types(array &$types, ?string $filter): void
{
    if (!method_exists(FrmField::class, 'field_selection')) {
        return;
    }
    foreach (FrmField::field_selection() as $slug => $def) {
        if (!frm_field_type_accepted($slug, $filter)) {
            continue;
        }
        if (frm_field_type_is_hidden($def)) {
            continue;
        }
        $types[] = frm_field_def_to_row($slug, $def, pro_only: false);
    }
}

/**
 * Walk the Pro catalog and append every accepted, non-addon-upsell,
 * non-hidden type. Caller MUST have already gated on `frm_pro_active()`.
 *
 * @param list<array{type:string,label:string,category:string,pro_only:bool}> $types
 */
function frm_collect_pro_field_types(array &$types, ?string $filter): void
{
    if (!method_exists(FrmField::class, 'pro_field_selection')) {
        return;
    }
    foreach (FrmField::pro_field_selection() as $slug => $def) {
        if (!frm_field_type_accepted($slug, $filter)) {
            continue;
        }
        // Drop add-on upsells (e.g. types that require a separate Pro
        // add-on like Surveys) — we only advertise types the install can
        // actually render. Mirrors the filter in
        // `frm_available_field_types()` used by check-setup.
        if (array_key_exists('addon', $def)) {
            continue;
        }
        if (frm_field_type_is_hidden($def)) {
            continue;
        }
        $types[] = frm_field_def_to_row($slug, $def, pro_only: true);
    }
}

/**
 * Whether a catalog slug should be included given the optional caller
 * filter. Empty slugs are always rejected (defensive — the upstream
 * catalog should never emit them, but a misbehaving filter on
 * `frm_field_types` could).
 */
function frm_field_type_accepted(int|string $slug, ?string $filter): bool
{
    if (!is_string($slug) || $slug === '') {
        return false;
    }
    return $filter === null || $filter === '' || $slug === $filter;
}

/**
 * Whether the catalog entry should be hidden from the response.
 * Formidable marks internal-only field types (e.g. `submit`, the hidden
 * GDPR variant) with `hide => true` in `FrmField::field_selection()`.
 * Surfacing them as valid `type` values for `add-field` would let an
 * agent register an invalid field; filtering at the source prevents
 * that.
 *
 * @param array<string, mixed> $def
 */
function frm_field_type_is_hidden(array $def): bool
{
    // Formidable serialises the `hide` flag as either a bare `true` or
    // an integer `1` depending on the catalog entry — both must read as
    // hidden. Any other value (missing, null, false, 0, '') reads as
    // visible.
    // @mago-expect analyzer:mixed-assignment
    $flag = $def['hide'] ?? null;
    if ($flag === null || $flag === false || $flag === 0 || $flag === '' || $flag === '0') {
        return false;
    }
    return true;
}

/**
 * Reduce one Formidable field-type definition (a row from
 * `field_selection()` or `pro_field_selection()`) to the compact list
 * shape this ability returns. Mirrors the
 * `{type, label, category, pro_only}` row produced by Gravity / WPForms
 * / Fluent so an agent can write cross-plugin code that walks the
 * catalog with the same accessors regardless of source plugin.
 *
 * Formidable's catalog uses `section` for UI grouping (typically empty,
 * with `'pricing'` for the product / quantity / total trio); we re-map
 * the catalog into a small fixed vocabulary (standard / structure /
 * payment) to match the categorisation the other three readers use.
 *
 * @param array<string, mixed> $def
 *
 * @return array{type:string,label:string,category:string,pro_only:bool}
 */
// @mago-expect lint:no-boolean-flag-parameter
function frm_field_def_to_row(string $slug, array $def, bool $pro_only): array
{
    $label = is_string($def['name'] ?? null) ? $def['name'] : '';
    return [
        'type' => $slug,
        'label' => $label !== '' ? $label : ucwords(str_replace(['_', '-'], replace: ' ', subject: $slug)),
        'category' => frm_field_type_category($slug),
        'pro_only' => $pro_only,
    ];
}

/**
 * Classifies a Formidable field type into one of three editor groups:
 * standard (single value inputs and choice fields), structure (layout
 * controls, dividers, pagebreaks, html, summary, captcha), and payment
 * (product / quantity / total / credit-card / coupon). Hard-coded
 * because Formidable's catalog only sets `section='pricing'` for a
 * subset of payment fields and leaves the rest empty — a hand-rolled
 * categorisation here matches what the agent expects across the form
 * family.
 */
function frm_field_type_category(string $slug): string
{
    $structure = [
        'divider',
        'end_divider',
        'break',
        'html',
        'summary',
        'captcha',
        'form',
    ];
    $payment = [
        'credit_card',
        'product',
        'quantity',
        'total',
        'coupon',
    ];
    if (in_array($slug, $structure, strict: true)) {
        return 'structure';
    }
    if (in_array($slug, $payment, strict: true)) {
        return 'payment';
    }
    return 'standard';
}
