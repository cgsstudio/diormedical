<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-get-design-system', [
    'label' => __('Get Bricks Design System', domain: 'novamira-pro'),
    'description' => __(
        'One compact read of the site\'s Bricks design system before building anything: global classes (count, naming prefixes in use, top categories), variables (count, categories, whether fluid scales exist), colour palette (palettes, colour counts, dark-mode readiness), theme styles (which apply to the front page, their conditions and the setting groups they define), components (label, property count, how many instances use each), breakpoints, templates that render (by type), and the design authority level when the base plugin reports one. Every section names the ability that returns the detail, so read this first and drill down only where needed.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'global_classes' => [
                'type' => 'object',
                'description' => 'Global classes in use: how many, the naming prefixes they follow and their busiest categories.',
                'properties' => [
                    'count' => ['type' => 'integer'],
                    'prefixes' => [
                        'type' => 'array',
                        'description' => 'Naming conventions in use: the segment before the first "-" or "_", for prefixes used by at least two classes.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'prefix' => ['type' => 'string'],
                                'count' => ['type' => 'integer'],
                            ],
                            'required' => ['prefix', 'count'],
                        ],
                    ],
                    'top_categories' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'count' => ['type' => 'integer'],
                            ],
                            'required' => ['name', 'count'],
                        ],
                    ],
                    'detail' => [
                        'type' => 'string',
                        'description' => 'The ability that returns the classes themselves.',
                    ],
                ],
                'required' => ['count', 'prefixes', 'top_categories', 'detail'],
            ],
            'variables' => [
                'type' => 'object',
                'description' => 'Global variables: how many, their busiest categories and whether any fluid (clamp) scale exists.',
                'properties' => [
                    'count' => ['type' => 'integer'],
                    'categories' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'name' => ['type' => 'string'],
                                'count' => ['type' => 'integer'],
                            ],
                            'required' => ['name', 'count'],
                        ],
                    ],
                    'fluid_scales' => ['type' => 'boolean'],
                    'detail' => ['type' => 'string'],
                ],
                'required' => ['count', 'categories', 'fluid_scales', 'detail'],
            ],
            'palette' => [
                'type' => 'object',
                'description' => 'Colour palettes with their colour counts and whether the site is ready for dark-mode variants.',
                'properties' => [
                    'palettes' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'name' => ['type' => 'string'],
                                'color_count' => ['type' => 'integer'],
                            ],
                            'required' => ['id', 'name', 'color_count'],
                        ],
                    ],
                    'color_count' => ['type' => 'integer'],
                    'dark_mode_ready' => [
                        'type' => 'boolean',
                        'description' => 'True when a stored dark value was OBSERVED to reach the CSS this Bricks compiles for the dark context — the value is swapped and the output checked, not predicted. Read dark_mode_support beside it: false can mean no dark values are stored, or that they are stored and do not reach it, or that the compile could not be run here.',
                    ],
                    'dark_mode_support' => [
                        'type' => 'string',
                        'enum' => ['materialised', 'absent', 'not_determinable', 'no_dark_values'],
                        'description' => 'What was observed by recompiling the stored palette for the dark context, swapping one stored dark value at a time: \'materialised\' (at least one follows the swap, so it is what the dark context renders), \'absent\' (dark values are stored, EVERY one of them was observed, and none follows — the light value renders in both modes), \'not_determinable\' (nothing rendered among the colours that were observed, and at least one could not be observed or was not reached — so no claim either way), or \'no_dark_values\' (nothing carries a dark value). Bounded work: a run of colours is probed rather than the whole palette, so a very large design system answers \'not_determinable\' instead of \'absent\' when nothing among the probed colours renders.',
                    ],
                    'detail' => ['type' => 'string'],
                ],
                'required' => ['palettes', 'color_count', 'dark_mode_ready', 'dark_mode_support', 'detail'],
            ],
            'theme_styles' => [
                'type' => 'object',
                'description' => 'Theme styles, which of them apply to the front page, and the setting groups each one defines.',
                'properties' => [
                    'count' => ['type' => 'integer'],
                    'active_for_front_page' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Ids of the styles whose conditions match the front page.',
                    ],
                    'styles' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'label' => ['type' => 'string'],
                                'active' => ['type' => 'boolean'],
                                'conditions' => [
                                    'type' => ['array', 'null'],
                                    'description' => 'Bricks\' own condition rows, passed through unchanged; null when the style sets none.',
                                ],
                                'groups' => ['type' => 'array', 'items' => ['type' => 'string']],
                            ],
                            'required' => ['id', 'label', 'active', 'groups'],
                        ],
                    ],
                    'detail' => ['type' => 'string'],
                ],
                'required' => ['count', 'active_for_front_page', 'styles', 'detail'],
            ],
            'components' => [
                'type' => 'object',
                'description' => 'Components with their property counts and how many instances use each.',
                'properties' => [
                    'count' => ['type' => 'integer'],
                    'items' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'label' => ['type' => 'string'],
                                'property_count' => ['type' => 'integer'],
                                'instance_count' => ['type' => 'integer'],
                            ],
                            'required' => ['id', 'label', 'property_count', 'instance_count'],
                        ],
                    ],
                    'detail' => ['type' => 'string'],
                ],
                'required' => ['count', 'items', 'detail'],
            ],
            'breakpoints' => ['type' => 'object', 'description' => 'The bricks-list-breakpoints response.'],
            'templates' => [
                'type' => 'object',
                'description' => 'Templates with at least one display condition: {rendering_by_type: {header: n, footer: n, …}, total, detail}. rendering_by_type stays generic because its keys are the template types this Bricks defines.',
            ],
            'authority' => [
                'type' => ['object', 'null'],
                'description' => '{level, builder?} reported through the base plugin\'s design-authority filter; null when the base plugin does not expose the design feature.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_get_design_system',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read this once at the start of a build (bricks-build-page phase 0). On a mature site the prefixes tell you the naming convention to follow; on a fresh site every count is small and reading the detail lists is cheap. Do not create tokens, classes or theme styles that a section here already reports.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_get_design_system(array $input): array
{
    return [
        'global_classes' => bricks_design_system_classes(),
        'variables' => bricks_design_system_variables(),
        'palette' => bricks_design_system_palette(),
        'theme_styles' => bricks_design_system_theme_styles(),
        'components' => bricks_design_system_components(),
        'breakpoints' => bricks_list_breakpoints([]),
        'templates' => bricks_design_system_templates(),
        'authority' => bricks_design_system_authority(),
    ];
}

/**
 * @return array<string, mixed>
 */
function bricks_design_system_classes(): array
{
    $classes = bricks_option_rows(BRICKS_DB_GLOBAL_CLASSES);
    $names = [];
    $category_ids = [];
    foreach ($classes as $class) {
        if (is_string($class['name'] ?? null)) {
            $names[] = $class['name'];
        }
        if (is_string($class['category'] ?? null) && $class['category'] !== '') {
            $category_ids[] = $class['category'];
        }
    }

    return [
        'count' => count($classes),
        'prefixes' => bricks_name_prefixes($names),
        'top_categories' => bricks_top_categories($category_ids, BRICKS_DB_GLOBAL_CLASSES_CATEGORIES),
        'detail' => 'novamira/bricks-list-global-classes (filter by category; bricks-get-global-class for one class\'s settings)',
    ];
}

/**
 * @return array<string, mixed>
 */
function bricks_design_system_variables(): array
{
    $variables = bricks_option_rows(BRICKS_DB_GLOBAL_VARIABLES);
    $category_ids = [];
    $fluid = false;
    foreach ($variables as $variable) {
        if (is_string($variable['category'] ?? null) && $variable['category'] !== '') {
            $category_ids[] = $variable['category'];
        }
        $value = is_string($variable['value'] ?? null) ? $variable['value'] : '';
        if (str_contains($value, 'clamp(')) {
            $fluid = true;
        }
    }

    return [
        'count' => count($variables),
        'categories' => bricks_top_categories($category_ids, BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES, limit: 20),
        'fluid_scales' => $fluid,
        'detail' => 'novamira/bricks-list-variables (filter by category)',
    ];
}

/**
 * @return array<string, mixed>
 */
function bricks_design_system_palette(): array
{
    $palettes = bricks_option_rows(BRICKS_DB_COLOR_PALETTE);
    $rows = [];
    $total = 0;
    foreach ($palettes as $palette) {
        $colors = is_array($palette['colors'] ?? null) ? $palette['colors'] : [];
        $total += count($colors);
        $rows[] = [
            'id' => (string) ($palette['id'] ?? ''),
            'name' => (string) ($palette['name'] ?? ''),
            'color_count' => count($colors),
        ];
    }

    return [
        'palettes' => $rows,
        'color_count' => $total,
        // One definition, shared with novamira/bricks-list-color-palette: the
        // two used to compute this separately and disagree.
        'dark_mode_ready' => cp_dark_mode_ready($palettes),
        'dark_mode_support' => cp_observe_dark_rendering($palettes),
        'detail' => 'novamira/bricks-list-color-palette',
    ];
}

/**
 * @return array<string, mixed>
 */
function bricks_design_system_theme_styles(): array
{
    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_THEME_STYLES, []);
    $store = is_array($raw) ? $raw : [];
    $styles = [];
    /** @var mixed $entry */
    foreach ($store as $id => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $settings = is_array($entry['settings'] ?? null) ? $entry['settings'] : [];
        $conditions = is_array($settings['conditions']['conditions'] ?? null)
            ? $settings['conditions']['conditions']
            : null;
        $groups = [];
        foreach (array_keys($settings) as $group) {
            if ((string) $group === 'conditions') {
                continue;
            }
            $groups[] = (string) $group;
        }
        $styles[] = [
            'id' => (string) $id,
            'label' => (string) ($entry['label'] ?? ''),
            'active' => ts_is_active($settings),
            'conditions' => $conditions,
            'groups' => $groups,
        ];
    }

    return [
        'count' => count($styles),
        'active_for_front_page' => bricks_theme_styles_for_front_page(),
        'styles' => $styles,
        'detail' => 'novamira/bricks-get-theme-style (one style with its settings)',
    ];
}

/**
 * Ids of the theme styles Bricks applies to the front page, resolved the
 * way Bricks does for a request (condition match, most specific last).
 *
 * @return list<string>
 */
function bricks_theme_styles_for_front_page(): array
{
    if (!method_exists(\Bricks\Theme_Styles::class, 'set_active_style')) {
        return [];
    }
    $front = (int) get_option('page_on_front', default_value: 0);
    try {
        /** @var mixed $ids */
        $ids = \Bricks\Theme_Styles::set_active_style($front, true);
    } catch (\Throwable) {
        return [];
    }

    if (!is_array($ids)) {
        return [];
    }
    $resolved = [];
    /** @var mixed $id */
    foreach ($ids as $id) {
        if (!is_scalar($id)) {
            continue;
        }
        $resolved[] = (string) $id;
    }

    return $resolved;
}

/**
 * @return array<string, mixed>
 */
function bricks_design_system_components(): array
{
    $items = [];
    foreach (comp_load_all() as $component) {
        $id = (string) ($component['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $items[] = [
            'id' => $id,
            'label' => comp_component_label($component),
            'property_count' => count(comp_component_properties($component)),
            'instance_count' => comp_total_instance_count(comp_find_instances($id)),
        ];
    }

    return [
        'count' => count($items),
        'items' => $items,
        'detail' => 'novamira/bricks-list-components (verbose: true for trees and property definitions)',
    ];
}

/**
 * @return array<string, mixed>
 */
function bricks_design_system_templates(): array
{
    /** @var list<int> $ids */
    $ids = get_posts([
        'post_type' => BRICKS_DB_TEMPLATE_SLUG,
        'post_status' => 'publish',
        'posts_per_page' => 200,
        'fields' => 'ids',
        'no_found_rows' => true,
        'suppress_filters' => true,
    ]);
    $by_type = [];
    foreach ($ids as $template_id) {
        /** @var mixed $settings */
        $settings = get_post_meta($template_id, BRICKS_DB_TEMPLATE_SETTINGS, single: true);
        $conditions = is_array($settings) && is_array($settings['templateConditions'] ?? null)
            ? $settings['templateConditions']
            : [];
        if ($conditions === []) {
            continue;
        }
        $type = bricks_template_type($template_id);
        $by_type[$type] = ($by_type[$type] ?? 0) + 1;
    }
    ksort($by_type);

    return [
        'rendering_by_type' => $by_type,
        'total' => array_sum($by_type),
        'detail' => 'novamira/bricks-list-templates (conditions per template; bricks-list-template-conditions for one)',
    ];
}

/**
 * The design authority level the base plugin reports for this site through
 * its `novamira_design_authority` filter, built from the base design store
 * when it is available; null when the installed base plugin has no design
 * feature to report on.
 *
 * @return array{level: string, builder?: string}|null
 */
function bricks_design_system_authority(): ?array
{
    if (!function_exists('Novamira\Design\Store\get_active_slug')) {
        return null;
    }
    /** @var mixed $slug */
    $slug = \Novamira\Design\Store\get_active_slug();
    $active = is_string($slug) ? $slug : '';
    $context = ['feature_enabled' => true, 'design_active' => $active !== '', 'active_design' => $active];
    $base_default = 'design';
    /** @var mixed $reported */
    $reported = apply_filters('novamira_design_authority', $base_default, $context);
    if (is_array($reported) && is_string($reported['level'] ?? null)) {
        $result = ['level' => $reported['level']];
        if (is_string($reported['builder'] ?? null)) {
            $result['builder'] = $reported['builder'];
        }

        return $result;
    }

    return ['level' => is_string($reported) ? $reported : 'design'];
}

/**
 * Rows of a list-shaped Bricks option, read from the site that holds it.
 *
 * @return list<array<string, mixed>>
 */
function bricks_option_rows(string $option): array
{
    /** @var mixed $raw */
    $raw = bricks_routed_option_read($option, []);
    $rows = [];
    /** @var mixed $entry */
    foreach (is_array($raw) ? $raw : [] as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $rows[] = bricks_string_keyed($entry);
    }

    return $rows;
}

/**
 * Naming prefixes in use: the segment before the first "-" or "_", counted
 * across names, kept when it repeats (a convention, not a one-off).
 *
 * @param list<string> $names
 * @return list<array{prefix: string, count: int}>
 */
function bricks_name_prefixes(array $names): array
{
    // Tally with the prefix kept as a VALUE: a class named "900-hero" has an
    // all-digit prefix, and such a key comes back from the map as an integer
    // while this list is declared to hold strings.
    /** @var array<array-key, array{prefix: string, count: int}> $counts */
    $counts = [];
    foreach ($names as $name) {
        $segments = preg_split('/[-_]/', $name, limit: 2);
        $prefix = is_array($segments) && count($segments) === 2 ? $segments[0] : '';
        if ($prefix === '') {
            continue;
        }
        $counts[$prefix] = ['prefix' => $prefix, 'count' => ($counts[$prefix]['count'] ?? 0) + 1];
    }
    uasort($counts, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

    $rows = [];
    foreach ($counts as $entry) {
        if ($entry['count'] < 2 || count($rows) >= 8) {
            break;
        }
        $rows[] = $entry;
    }

    return $rows;
}

/**
 * Category usage, ids resolved to names through the categories option.
 *
 * @param list<string> $category_ids
 * @return list<array{name: string, count: int}>
 */
function bricks_top_categories(array $category_ids, string $categories_option, int $limit = 8): array
{
    $names = [];
    foreach (bricks_option_rows($categories_option) as $category) {
        if (!is_string($category['id'] ?? null) || !is_string($category['name'] ?? null)) {
            continue;
        }
        $names[$category['id']] = $category['name'];
    }
    // Tally by id while keeping the id as a VALUE: an all-digit id used as an
    // array key comes back as an int, and it is the display name's fallback.
    /** @var array<array-key, array{id: string, count: int}> $tally */
    $tally = [];
    foreach ($category_ids as $category_id) {
        $tally[$category_id] = [
            'id' => $category_id,
            'count' => ($tally[$category_id]['count'] ?? 0) + 1,
        ];
    }
    uasort($tally, static fn(array $a, array $b): int => $b['count'] <=> $a['count']);

    $rows = [];
    foreach ($tally as $entry) {
        if (count($rows) >= $limit) {
            break;
        }
        $rows[] = ['name' => $names[$entry['id']] ?? $entry['id'], 'count' => $entry['count']];
    }

    return $rows;
}
