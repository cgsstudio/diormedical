<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Kadence;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: enumerate Kadence theme settings as a grouped catalogue.
 */

wp_register_ability('novamira/kadence-list-customizer-settings', [
    'label' => __('List Kadence Customizer Settings', domain: 'novamira-pro'),
    'description' => __(
        'Enumerates the Kadence theme settings as a grouped catalogue. Kadence exposes ~1000 individual setting keys (typography, colors, layout, header, footer, page-title, archive, product, mobile menu, etc.); returning every key with its default would overflow most contexts. This ability returns a compact `areas[]` overview by default — `{name, count}` per area — and lets the agent drill down by passing `area=<name>` to get the keys for that area only, optionally with their inferred type and default value via `with_defaults=true`. The list-only response is safe to call any time; pass `area` only when you actually need that area\'s keys.',
        domain: 'novamira-pro',
    ),
    'category' => 'kadence',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'area' => [
                'type' => 'string',
                'description' => 'Restrict the response to one area. Areas are inferred from the key prefix in a deliberately conservative way: plugin / runtime prefixes are peeled off first (`product` (Woo / EDD / SureCart), `lms` (LearnDash / LifterLMS / Tutor / Groups), `community` (bbPress, BuddyPress), `events` (The Events Calendar), `blocks` (Kadence Blocks)), then theme anchors (`header`, `footer`, `mobile`, `transparent`, `scroll`, `sidebar`, `search`, `archive`, `post`, `page`, `content`, `global`, `site`, `buttons`, `layout`, `404`, `page-title`), then `typography` / `colors` substring fallbacks, then `other`. Side effect: most color keys nested under a feature prefix (e.g. `header_link_color`) land in the feature area, not in `colors` — call the overview (no input) first to see the actual area distribution on this install. The catalogue covers every key in Kadence\'s defaults map; some Customizer settings registered conditionally at runtime are not in the defaults map and therefore not enumerable here. Omit `area` to get the overview only.',
            ],
            'with_defaults' => [
                'type' => 'boolean',
                'description' => 'When true and `area` is set, each setting record carries its inferred type + default value. Skip on first browse; opt in when composing a `kadence-edit-settings` payload.',
                'default' => false,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'areas' => [
                'type' => 'array',
                'description' => 'Compact catalogue overview. One row per area Kadence registers settings for, with the key count. Present when `area` is NOT set.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string'],
                        'count' => ['type' => 'integer'],
                    ],
                    'required' => ['name', 'count'],
                ],
            ],
            'area' => [
                'type' => 'string',
                'description' => 'Echoed area name when the request specified one. Present when `area` IS set.',
            ],
            'settings' => [
                'type' => 'array',
                'description' => 'Per-key records for the requested area. Each record has `{key, type, default?}` — `default` only when `with_defaults=true`. Present when `area` IS set.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'type' => [
                            'type' => 'string',
                            'enum' => ['string', 'integer', 'number', 'boolean', 'array', 'null'],
                        ],
                        // `default` declares an explicit type union to silence
                        // a flood of `Undefined array key "type"` warnings from
                        // WP core's REST validator (Round 2 audit B5 measured
                        // ~1014 warnings on a single area=header call).
                        'default' => [
                            'type' => ['string', 'integer', 'number', 'boolean', 'array', 'object', 'null'],
                            'description' => 'Default value. Only present when with_defaults=true.',
                        ],
                    ],
                    'required' => ['key', 'type'],
                ],
            ],
            'total' => [
                'type' => 'integer',
                'description' => 'Total number of settings across all areas (when overview) or within the requested area.',
            ],
        ],
        'required' => ['total'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Kadence\\kadence_list_customizer_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Start with no input to browse the area overview — pick the area you actually need (e.g. `header`, `typography`, `colors`), then call again with `area=<name>` to get its keys. Add `with_defaults=true` only when you are about to compose a write payload and need to know the canonical default to diff against. Kadence registers integrations even for plugins that are not active (the `product` and `lms` areas in particular); a high count there does not mean WooCommerce or LearnDash are installed — call kadence-check-setup for activation.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Maps a Kadence setting key to one of the curated area names. The
 * mapping is deliberately conservative — when nothing matches the key
 * falls into `other` so the agent always sees the count for surfacing.
 */
function kd_infer_area(string $key): string
{
    foreach (kd_area_prefix_map() as $prefix => $area) {
        if (str_starts_with($key, $prefix)) {
            return $area;
        }
    }

    // Anchor-style infers: keys that contain the area substring after the
    // first segment (e.g. `enable_palette_swatches` -> colors).
    if (str_contains($key, 'font') || str_contains($key, 'typography')) {
        return 'typography';
    }
    if (str_contains($key, 'palette') || str_contains($key, '_color')) {
        return 'colors';
    }
    return 'other';
}

/**
 * Compact prefix -> area map, in match order (more specific first).
 *
 * @return array<string, string>
 */
function kd_area_prefix_map(): array
{
    return [
        // Plugin / runtime keys come first to peel them off
        'wpb_gutenberg_param_' => 'page-title',
        'woocommerce_' => 'product',
        'product_' => 'product',
        'lifterlms_' => 'lms',
        'llms_' => 'lms',
        'sfwd-' => 'lms',
        'course_' => 'lms',
        'lesson_' => 'lms',
        'groups_' => 'lms',
        'tutor_' => 'lms',
        'edd_' => 'product',
        'bbpress_' => 'community',
        'buddypress_' => 'community',
        'tribe_events_' => 'events',
        'surecart_' => 'product',
        'kadence_blocks_' => 'blocks',
        // Theme-side anchors
        'header_' => 'header',
        'footer_' => 'footer',
        'mobile_' => 'mobile',
        'transparent_' => 'transparent',
        'scroll_' => 'scroll',
        'sidebar_' => 'sidebar',
        'search_' => 'search',
        'archive_' => 'archive',
        'post_' => 'post',
        'page_' => 'page',
        'content_' => 'content',
        'global_' => 'global',
        'site_' => 'site',
        'brand_' => 'site',
        'logo_' => 'site',
        'buttons_' => 'buttons',
        '404_' => '404',
        'comment_' => 'post',
        'breadcrumb_' => 'global',
        'dropdown_' => 'mobile',
        'ht_' => 'header',
        'primary_' => 'header',
        'secondary_' => 'header',
        'enable_' => 'global',
        'boxed_' => 'layout',
    ];
}

/**
 * Returns the PHP type name as one of the strings declared in the
 * ability's output_schema enum. `object` and `resource` are surfaced
 * distinctly from `null` so kadence-edit-settings can refuse them with
 * an accurate `non_persistable_type` warning (Round 2 audit B18) —
 * otherwise the catch-all `null` produced a misleading `actual=null`
 * report while update_option silently persisted a serialized object.
 */
function kd_value_type(mixed $value): string
{
    if (is_string($value)) {
        return 'string';
    }
    if (is_bool($value)) {
        return 'boolean';
    }
    if (is_int($value)) {
        return 'integer';
    }
    if (is_float($value)) {
        return 'number';
    }
    if (is_array($value)) {
        return 'array';
    }
    if (is_object($value)) {
        return 'object';
    }
    if (is_resource($value)) {
        return 'resource';
    }
    return 'null';
}

/**
 * @param array{area?: string, with_defaults?: bool} $input
 * @return array<string, mixed>|WP_Error
 */
function kadence_list_customizer_settings(array $input): array|WP_Error
{
    if (!kadence_theme_available()) {
        return kd_error('kadence_theme_inactive', __(
            'Kadence theme is not active or does not meet the minimum supported version.',
            domain: 'novamira-pro',
        ));
    }

    $tags = kadence_theme_tags();
    if ($tags === null) {
        return kd_error('kadence_theme_inactive', __('Kadence theme runtime not available.', domain: 'novamira-pro'));
    }

    $defaults = kd_load_defaults();
    $area_filter = $input['area'] ?? null;
    $with_defaults = ($input['with_defaults'] ?? false) === true;

    if ($area_filter === null) {
        return kd_areas_overview($defaults);
    }

    return $with_defaults
        ? kd_area_listing_with_defaults($defaults, $area_filter)
        : kd_area_listing_compact($defaults, $area_filter);
}

/**
 * Reads the full defaults map from Kadence's Options component. Wrapped so
 * the callback can swap to a cache layer later without touching the
 * ability shape. Returns an empty map if Kadence isn't booted (caller
 * should have already gated on kadence_theme_available()).
 *
 * @return array<string, mixed>
 */
function kd_load_defaults(): array
{
    $component = \Kadence\Theme::instance()->component('options');
    if (!$component instanceof \Kadence\Options\Component) {
        return [];
    }
    return $component->defaults();
}

/**
 * Builds the compact areas overview the ability returns when no area
 * filter is provided.
 *
 * @param array<string, mixed> $defaults
 * @return array{areas: list<array{name: string, count: int}>, total: int}
 */
function kd_areas_overview(array $defaults): array
{
    // List of {name, count} pairs (not an associative map keyed by area
    // name) to sidestep PHP's auto-cast of numeric-looking string keys
    // (e.g. '404') to int — the auto-cast would corrupt the area name
    // when the array is iterated back.
    $areas = [];
    foreach (array_keys($defaults) as $key) {
        $area = kd_infer_area($key);
        $found = false;
        foreach ($areas as $i => $entry) {
            if ($entry['name'] !== $area) {
                continue;
            }
            $areas[$i]['count'] = $entry['count'] + 1;
            $found = true;
            break;
        }
        if (!$found) {
            $areas[] = ['name' => $area, 'count' => 1];
        }
    }
    usort($areas, static fn(array $a, array $b): int => $b['count'] - $a['count']);

    return ['areas' => $areas, 'total' => count($defaults)];
}

/**
 * Listing variant that returns each key with type only — `{key, type}`.
 * Cheap response for the catalogue-browse case where the agent does not
 * yet need the default values.
 *
 * @param array<string, mixed> $defaults
 * @return array{area: string, settings: list<array{key: string, type: string}>, total: int}
 */
function kd_area_listing_compact(array $defaults, string $area): array
{
    $settings = [];
    /** @var mixed $value */
    foreach ($defaults as $key => $value) {
        if (kd_infer_area($key) !== $area) {
            continue;
        }
        $settings[] = ['key' => $key, 'type' => kd_value_type($value)];
    }
    usort($settings, static fn(array $a, array $b): int => strcmp($a['key'], $b['key']));
    return ['area' => $area, 'settings' => $settings, 'total' => count($settings)];
}

/**
 * Listing variant that includes the default value alongside the key and
 * type — `{key, type, default}`. Use when composing a write payload and
 * the agent needs the canonical default to diff against.
 *
 * @param array<string, mixed> $defaults
 * @return array{area: string, settings: list<array{key: string, type: string, default: mixed}>, total: int}
 */
function kd_area_listing_with_defaults(array $defaults, string $area): array
{
    $settings = [];
    /** @var mixed $value */
    foreach ($defaults as $key => $value) {
        if (kd_infer_area($key) !== $area) {
            continue;
        }
        $settings[] = ['key' => $key, 'type' => kd_value_type($value), 'default' => $value];
    }
    usort($settings, static fn(array $a, array $b): int => strcmp($a['key'], $b['key']));
    return ['area' => $area, 'settings' => $settings, 'total' => count($settings)];
}
