<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use ET\Builder\Packages\Conversion\Conversion;

/**
 * Ability: Get a Divi 5 module's universal styling (decoration) schema.
 */

if (!defined('ABSPATH')) {
    exit();
}

const NOVAMIRA_DIVI_DECORATION_SEGMENT = '.decoration.';

const NOVAMIRA_DIVI_ADVANCED_SEGMENT = '.advanced.';

wp_register_ability('novamira/divi-get-style-schema', [
    'label' => __('Get Divi Style Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the universal styling and advanced attributes available on a Divi 5 module — the "decoration" styles (background, spacing, border, sizing, fonts, animation, position, …) plus "advanced" module options that are otherwise hard to find: the CSS ID and class (group "htmlAttributes"), link, column gutter, and text-shadow. Each row is tagged segment: "decoration" or "advanced". These are the groups novamira/divi-get-module-schema only names as style_groups. By default returns a compact summary (group, segment, attrName, field_count); pass "group" (e.g. "background" or "htmlAttributes") to get that group\'s individual fields. Set a value with novamira/divi-edit-module at <attrName>.<breakpoint>.value.<field> — e.g. module.decoration.spacing.desktop.value.padding.top, or module.advanced.htmlAttributes.desktop.value.id for a CSS ID. Accepts the module name with or without the "divi/" prefix.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string', 'description' => 'Module name, e.g. "divi/text" or "text".'],
            'group' => [
                'type' => 'string',
                'description' => 'A style group (e.g. "background", "spacing") to return its fields.',
            ],
        ],
        'required' => ['module'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'module' => ['type' => 'string'],
            'groups' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'Summary rows (present when no "group" is requested).',
            ],
            'group' => [
                'type' => 'string',
                'description' => 'The requested group name (present when "group" is requested).',
            ],
            'matches' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'Each attrName in the group with its fields (present when "group" is requested).',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_get_style_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover how to style a module. Call without "group" for the list of style groups, then with group=<name> for its fields. Apply a value via novamira/divi-edit-module at <attrName>.<breakpoint>.value.<field>. Pair with novamira/divi-get-module-schema (the module\'s content/config fields).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|\WP_Error
 */
function divi_get_style_schema(array $input)
{
    if (!class_exists(Conversion::class)) {
        return divi_error('unavailable', message: 'The Divi preset-attrs mapping API is not available.', status: 500);
    }

    $module = divi_normalize_module_name(divi_string_arg($input, key: 'module'));
    if ($module === '' || divi_module_metadata($module) === null) {
        return divi_error(
            'unknown_module',
            message: sprintf('Unknown Divi module "%s". Use novamira/divi-list-modules.', $module),
            status: 404,
        );
    }

    $by_attr = divi_collect_style_groups(Conversion::get_preset_attrs_mapping($module));

    $group = divi_string_arg($input, key: 'group');
    if ($group !== '') {
        $matches = [];
        foreach ($by_attr as $entry) {
            if ($entry['group'] !== $group && $entry['attrName'] !== $group) {
                continue;
            }
            $matches[] = $entry;
        }
        if ($matches === []) {
            return divi_error(
                'unknown_group',
                message: sprintf(
                    'No style group "%s" on "%s". Call without "group" to list the groups.',
                    $group,
                    $module,
                ),
                status: 404,
            );
        }
        // A short group label can map to several attrName paths (e.g. fonts on
        // child modules) — return them all, not just the first.
        return ['module' => $module, 'group' => $group, 'matches' => $matches];
    }

    $groups = [];
    foreach ($by_attr as $entry) {
        $groups[] = [
            'group' => $entry['group'],
            'segment' => $entry['segment'],
            'attrName' => $entry['attrName'],
            'field_count' => count($entry['fields']),
        ];
    }

    return ['module' => $module, 'groups' => $groups];
}

/**
 * The style segment an attr path belongs to — `.decoration.` (universal styles)
 * or `.advanced.` (advanced module options) — or null when it is neither.
 */
function divi_attr_segment(string $attr_name): ?string
{
    if (str_contains($attr_name, NOVAMIRA_DIVI_DECORATION_SEGMENT)) {
        return NOVAMIRA_DIVI_DECORATION_SEGMENT;
    }
    if (str_contains($attr_name, NOVAMIRA_DIVI_ADVANCED_SEGMENT)) {
        return NOVAMIRA_DIVI_ADVANCED_SEGMENT;
    }
    return null;
}

/**
 * Group a module's preset-attrs mapping into its style groups, keyed by
 * attrName, each with its sub-field names and the segment it belongs to
 * (decoration = universal styles; advanced = module options like the CSS
 * id/class, link, gutter, text-shadow). The query `loop` group is omitted — it
 * has dedicated novamira/divi-*-loop abilities.
 *
 * @param array<string, mixed> $mapping
 * @return list<array{group: string, segment: string, attrName: string, fields: list<string>}>
 */
function divi_collect_style_groups(array $mapping): array
{
    $by_attr = [];
    /** @var mixed $def */
    foreach ($mapping as $key => $def) {
        $attr_name = is_array($def) && is_string($def['attrName'] ?? null)
            ? $def['attrName']
            : explode('__', $key, limit: 2)[0];

        $segment = divi_attr_segment($attr_name);
        if ($segment === null) {
            continue;
        }
        $pos = strpos($attr_name, $segment);
        if ($pos === false) {
            continue;
        }
        $group = explode('.', substr($attr_name, $pos + strlen($segment)))[0];
        $is_advanced = $segment === NOVAMIRA_DIVI_ADVANCED_SEGMENT;
        if ($is_advanced && $group === 'loop') {
            continue;
        }

        $sub_name = is_array($def) && is_string($def['subName'] ?? null)
            ? $def['subName']
            : explode('__', $key, limit: 2)[1] ?? '';

        if (!isset($by_attr[$attr_name])) {
            $by_attr[$attr_name] = [
                'group' => $group,
                'segment' => $is_advanced ? 'advanced' : 'decoration',
                'attrName' => $attr_name,
                'fields' => [],
            ];
        }
        if ($sub_name !== '') {
            $by_attr[$attr_name]['fields'][] = $sub_name;
        }
    }

    return array_values($by_attr);
}
