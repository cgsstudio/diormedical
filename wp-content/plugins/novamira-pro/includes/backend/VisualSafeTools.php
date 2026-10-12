<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\VisualSafeTools;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Pro-owned builder filter for Novamira Visual backend tools.
 *
 * The discovery/execution engine lives in the free plugin
 * (Novamira\Visual\BackendTools). Pro hooks the engine's filters to keep its
 * builder abilities (Elementor/Bricks) out of backend discovery, so live
 * builder work stays on the page-tools path.
 */

/** @var list<string> */
const EXCLUDED_BUILDER_SCOPES = ['bricks', 'elementor'];

/** @var list<string> */
const INCLUDED_BUILDER_SETUP_ABILITY_NAMES = [
    'novamira/bricks-check-setup',
    'novamira/bricks-set-post-types',
];

/** @var list<string> */
const EXCLUDED_BUILDER_SKILL_SLUGS = [
    'bricks-build-page',
    'bricks-query-loops',
    'bricks-nestables',
    'bricks-components',
    'bricks-page-settings',
    'bricks-element-gotchas',
    'bricks-verify',
    'dynamic-data-binding',
    'elementor-build-page',
    'elementor-convert-to-v4',
];

\add_filter('novamira_visual_excluded_backend_abilities', __NAMESPACE__ . '\exclude_builder_abilities');
\add_filter('novamira_visual_backend_discovery_instructions', __NAMESPACE__ . '\adjust_discovery_instructions');

/**
 * @param mixed $excluded
 * @return array<string, bool>
 */
function exclude_builder_abilities(mixed $excluded): array
{
    /** @var array<string, bool> $excluded */
    $excluded = is_array($excluded) ? $excluded : [];
    if (!function_exists('wp_get_abilities')) {
        return $excluded;
    }

    foreach (\wp_get_abilities() as $ability) {
        $name = $ability->get_name();
        if (in_array($name, INCLUDED_BUILDER_SETUP_ABILITY_NAMES, strict: true)) {
            continue;
        }
        if (is_builder_ability($ability)) {
            $excluded[$name] = true;
        }
    }

    return $excluded;
}

/**
 * @param mixed $base
 */
function adjust_discovery_instructions(mixed $base): string
{
    $base = is_string($base) ? $base : '';
    $filter = backend_skill_filter();
    $source_filter = backend_skill_source_filter();
    \add_filter('novamira_discover_abilities_skills', $filter);
    \add_filter('novamira_skill_lookup_sources', $source_filter);
    try {
        /** @var mixed $instructions */
        $instructions = \apply_filters('novamira_discover_abilities_instructions', $base);
    } finally {
        \remove_filter('novamira_discover_abilities_skills', $filter);
        \remove_filter('novamira_skill_lookup_sources', $source_filter);
    }

    return is_string($instructions) ? $instructions : $base;
}

function is_builder_ability(\WP_Ability $ability): bool
{
    $name = strtolower($ability->get_name());
    $category = strtolower($ability->get_category());

    foreach (EXCLUDED_BUILDER_SCOPES as $scope) {
        if (str_starts_with($name, 'novamira/' . $scope . '-')) {
            return true;
        }
        if ($category === $scope || str_contains($category, $scope)) {
            return true;
        }
    }

    return false;
}

/**
 * @return callable(mixed): mixed
 */
function backend_skill_filter(): callable
{
    return static function (mixed $skills): mixed {
        if (!is_array($skills)) {
            return $skills;
        }

        $filtered = array_filter($skills, static fn(mixed $skill): bool => !is_excluded_builder_skill($skill));

        return array_values($filtered);
    };
}

/**
 * @return callable(mixed): mixed
 */
function backend_skill_source_filter(): callable
{
    return static function (mixed $sources): mixed {
        if (!is_array($sources)) {
            return $sources;
        }

        // @mago-expect analysis:mixed-assignment
        foreach ($sources as $key => $source) {
            if (!is_array($source) || !is_callable($source['loader'] ?? null)) {
                continue;
            }

            /** @var callable(): mixed $loader */
            $loader = $source['loader'];
            $source['loader'] = static function () use ($loader): array {
                /** @var mixed $loaded */
                $loaded = $loader();
                if (!is_array($loaded)) {
                    return [];
                }

                return array_values(array_filter(
                    $loaded,
                    static fn(mixed $skill): bool => !is_excluded_builder_skill($skill),
                ));
            };
            $sources[$key] = $source;
        }

        return $sources;
    };
}

function is_excluded_builder_skill(mixed $skill): bool
{
    if (!is_array($skill)) {
        return false;
    }

    $slug = is_string($skill['slug'] ?? null) ? $skill['slug'] : '';

    // Other plugins (e.g. the free Gutenberg integration) add their own
    // headless skills that should stay out of Visual's live editing flow.
    /** @var mixed $slugs */
    $slugs = \apply_filters('novamira_visual_excluded_backend_skills', EXCLUDED_BUILDER_SKILL_SLUGS);

    return in_array($slug, is_array($slugs) ? $slugs : EXCLUDED_BUILDER_SKILL_SLUGS, strict: true);
}
