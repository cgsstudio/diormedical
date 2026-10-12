<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Variants are Mosaic's built-in per-element-type style bases. They come from
 * Mosaic's catalog (Mosaic/VariantCatalog/VariantCatalog.json), so they are
 * read-only here; each element type declares which ones it accepts in its
 * factory's init() (e.g. ButtonElementTypeFactory: Button, Inherited text —
 * catalog name "Link" — and Badge, default Button). See class-model.php.
 */

wp_register_ability('novamira/mosaic-list-variants', [
    'label' => __('List Mosaic Variants', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Variants a Mosaic element type accepts — Mosaic\'s built-in style bases for that element (a `button` can be a Button, a Link or a Badge) — with the default first and each Variant\'s variant sub classes in this theme. Per Variant: id, name, class_name (the class Mosaic renders, e.g. `m-button`), is_default, can_have_sub_classes and `sub_classes` (a nested tree of id, name, emitted_name such as `m-button--primary`). Pass any of these ids or names to the `variant` input of mosaic-add-element / mosaic-edit-element. Variants themselves are built into Mosaic and cannot be created or deleted; their sub classes are managed with the mosaic-*-variant-sub-class abilities.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string', 'description' => 'Theme UUID (variant sub classes are theme-scoped).'],
            'element_type' => [
                'type' => 'string',
                'description' => 'Mosaic element type, e.g. `button`, `text`, `div` — the `type` of a node in mosaic-get-element-tree.',
            ],
        ],
        'required' => ['theme_id', 'element_type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'element_type' => ['type' => 'string'],
            'default_variant_id' => ['type' => 'string'],
            'variants' => ['type' => 'array'],
        ],
        'required' => ['element_type', 'default_variant_id', 'variants'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_list_variants',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use before setting `variant` on an element: only the Variants listed for its element type are accepted.',
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
function mosaic_list_variants(array $input): array|WP_Error
{
    $err = mo_require_runtime() ?? mo_require_class_model();
    if ($err !== null) {
        return $err;
    }
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    $element_type = is_string($input['element_type'] ?? null) ? trim($input['element_type']) : '';
    $err = $element_type === ''
        ? mo_error('mo_invalid_input', __('theme_id and element_type are required.', domain: 'novamira-pro'))
        : mo_require_publish_theme($theme_id);
    if ($err !== null) {
        return $err;
    }
    $supported = mo_element_type_variants($element_type);
    if ($supported instanceof WP_Error) {
        return $supported;
    }
    $index = mo_class_index($theme_id);
    $variants = [];
    foreach ($supported['variants'] as $id) {
        $variants[] = mo_present_variant($id, $supported['default'], $index['variant_sub_classes']);
    }
    return [
        'element_type' => $element_type,
        'default_variant_id' => $supported['default'],
        'variants' => $variants,
    ];
}

/**
 * @param array<string, array<string, mixed>> $variant_sub_classes
 * @return array<string, mixed>
 */
function mo_present_variant(string $id, string $default_id, array $variant_sub_classes): array
{
    $meta = mo_variant_catalog()[$id] ?? null;
    return [
        'id' => $id,
        'name' => $meta['name'] ?? '',
        'class_name' => $meta['class_name'] ?? '',
        'is_default' => $id === $default_id,
        'can_have_sub_classes' => $meta !== null && mo_variant_accepts_sub_classes($meta),
        'sub_classes' => mo_class_subtree($variant_sub_classes, $id),
    ];
}
