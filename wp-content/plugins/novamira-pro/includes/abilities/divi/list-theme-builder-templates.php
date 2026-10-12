<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

/**
 * Ability: List Divi 5 Theme Builder templates and their layouts.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-list-theme-builder-templates', [
    'label' => __('List Divi Theme Builder Templates', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Divi 5 Theme Builder templates: each template\'s id, title, whether it is the default/enabled, its header/body/footer layout post ids (with enabled flags), and its assignment conditions (use_on / exclude_from). The header/body/footer layout ids are ordinary Divi posts — edit their module trees with the standard novamira/divi-get-content, divi-set-content, and divi-*-module abilities by passing the layout id as the post. An empty list means no Theme Builder templates are defined.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'templates' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_list_theme_builder_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover Theme Builder templates and their header/body/footer layout post ids. To change a header/footer/body, read and edit that layout id with the normal Divi content/module abilities. Create templates with novamira/divi-create-theme-builder-template, re-assign them with divi-set-theme-builder-conditions, and remove them with divi-delete-theme-builder-template.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function divi_list_theme_builder_templates(): array
{
    if (!function_exists('et_theme_builder_get_theme_builder_templates')) {
        return ['templates' => [], 'total' => 0];
    }

    $templates = [];
    foreach (et_theme_builder_get_theme_builder_templates(true) as $template) {
        $templates[] = divi_theme_builder_template_row($template);
    }

    return ['templates' => $templates, 'total' => count($templates)];
}

/**
 * Reduce a Theme Builder template to the fields an agent needs to find and edit
 * its layouts.
 *
 * @param array<string, mixed> $template
 * @return array<string, mixed>
 */
function divi_theme_builder_template_row(array $template): array
{
    return [
        'id' => (int) ($template['id'] ?? 0),
        'title' => is_string($template['title'] ?? null) ? $template['title'] : '',
        'default' => ($template['default'] ?? false) === true,
        'enabled' => ($template['enabled'] ?? false) === true,
        'layouts' => divi_theme_builder_layouts($template['layouts'] ?? null),
        'use_on' => is_array($template['use_on'] ?? null) ? array_values($template['use_on']) : [],
        'exclude_from' => is_array($template['exclude_from'] ?? null) ? array_values($template['exclude_from']) : [],
    ];
}

/**
 * Compact the header/body/footer layout references of a template to
 * `{id, enabled}` each.
 *
 * @param mixed $raw_layouts
 * @return array<string, array{id: int, enabled: bool}>
 */
function divi_theme_builder_layouts($raw_layouts): array
{
    $source = is_array($raw_layouts) ? $raw_layouts : [];
    $layouts = [];
    foreach (['header', 'body', 'footer'] as $area) {
        $layout = is_array($source[$area] ?? null) ? $source[$area] : [];
        $layouts[$area] = [
            'id' => (int) ($layout['id'] ?? 0),
            'enabled' => ($layout['enabled'] ?? false) === true,
        ];
    }
    return $layouts;
}
