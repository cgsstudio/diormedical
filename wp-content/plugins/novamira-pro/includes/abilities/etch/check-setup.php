<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

/**
 * Ability: Check Etch setup and report environment status.
 *
 * Registered only when ETCH_PLUGIN_FILE is defined (the loader gate), like the
 * Bricks integration — a fully inactive Etch makes this ability absent rather
 * than returning active:false. The callback still reports active/issues
 * defensively for the narrow case where the constant is defined but the
 * Etch\Plugin class did not autoload.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/etch-check-setup', [
    'label' => __('Check Etch Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Etch environment status: active/inactive, version, PHP compatibility (Etch requires PHP 8.1+), license activation status, the Etch-managed custom post types, and counts of stored Etch libraries (components, global stylesheets, styles, loops, queries, taxonomies). Etch is an HTML-first builder that stores page content as Gutenberg block markup (etch/* blocks) in post_content and its global libraries in wp_options. Run this first to diagnose builder availability.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'active' => [
                'type' => 'boolean',
                'description' => 'Whether Etch is active (ETCH_PLUGIN_FILE defined and Etch\\Plugin present).',
            ],
            'version' => [
                'type' => 'string',
                'description' => 'Etch plugin version, or "unknown" when inactive.',
            ],
            'php_compatible' => [
                'type' => 'boolean',
                'description' => 'Whether the runtime PHP version meets the 8.1 minimum Etch requires.',
            ],
            'custom_field_values_supported' => [
                'type' => 'boolean',
                'description' => 'Whether Etch supports the per-post custom field values API (Etch 1.5.0+); when true the etch-get/set/delete-field-value abilities are available.',
            ],
            'license_status' => [
                'type' => 'string',
                'description' => 'Raw value of the etch_license_status option ("" when no license is stored).',
            ],
            'custom_post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Slugs of the custom post types Etch registers from the etch_cpts option.',
            ],
            'library_counts' => [
                'type' => 'object',
                'description' => 'Number of top-level entries stored in each Etch library option.',
                'properties' => [
                    'components' => ['type' => 'integer'],
                    'global_stylesheets' => ['type' => 'integer'],
                    'styles' => ['type' => 'integer'],
                    'loops' => ['type' => 'integer'],
                    'queries' => ['type' => 'integer'],
                    'taxonomies' => ['type' => 'integer'],
                ],
            ],
            'issues' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Actionable configuration warnings.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run this before building pages to verify Etch is ready. Key fields: active (Etch must be installed and activated), php_compatible (Etch needs PHP 8.1+), version (compare against the minimum the skill documents), and issues (actionable warnings). custom_post_types lists the Etch-managed CPTs you can target; library_counts hints at what global state already exists.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     active: bool,
 *     version: string,
 *     php_compatible: bool,
 *     custom_field_values_supported: bool,
 *     license_status: string,
 *     custom_post_types: list<string>,
 *     library_counts: array{
 *         components: int,
 *         global_stylesheets: int,
 *         styles: int,
 *         loops: int,
 *         queries: int,
 *         taxonomies: int,
 *     },
 *     issues: list<string>,
 * }
 */
function etch_check_setup(): array
{
    $php_compatible = PHP_VERSION_ID >= 80_100;
    $active = etch_active();

    $issues = [];

    if (!$active) {
        $issues[] = 'Etch is not active. Install and activate the Etch plugin (https://etchwp.com) to use these abilities.';
    }

    if (!$php_compatible) {
        $issues[] = sprintf('PHP %s is below the 8.1 minimum Etch requires.', PHP_VERSION);
    }

    $license_status = '';
    $custom_post_types = [];
    $library_counts = [
        'components' => 0,
        'global_stylesheets' => 0,
        'styles' => 0,
        'loops' => 0,
        'queries' => 0,
        'taxonomies' => 0,
    ];

    if ($active) {
        // get_option may return a non-scalar (a corrupt/object license value);
        // a bare (string) cast would throw on an object, so guard with is_scalar.
        /** @var mixed $license_raw */
        $license_raw = get_option('etch_license_status', default_value: '');
        $license_status = is_scalar($license_raw) ? (string) $license_raw : '';
        if ($license_status === '') {
            $issues[] = 'Etch license is not activated (etch_license_status is empty). The visual editor may be limited; programmatic content still renders.';
        }

        // Only count entries Etch will actually register: a string slug whose
        // value is an args array (ContentTypeService skips non-array entries).
        $cpts = etch_option_array('etch_cpts');
        foreach (array_keys($cpts) as $cpt_slug) {
            if (!is_string($cpt_slug)) {
                continue;
            }

            /** @var mixed $cpt_args */
            $cpt_args = $cpts[$cpt_slug];
            if (is_array($cpt_args)) {
                $custom_post_types[] = $cpt_slug;
            }
        }

        $library_counts = [
            'components' => etch_count_components(),
            'global_stylesheets' => etch_count_option_entries('etch_global_stylesheets'),
            'styles' => etch_count_option_entries('etch_styles'),
            'loops' => etch_count_option_entries('etch_loops'),
            'queries' => etch_count_option_entries('etch_queries'),
            'taxonomies' => etch_count_option_entries('etch_taxonomies'),
        ];
    }

    return [
        'active' => $active,
        'version' => etch_version(),
        'php_compatible' => $php_compatible,
        'custom_field_values_supported' => etch_supports_field_values(),
        'license_status' => $license_status,
        'custom_post_types' => $custom_post_types,
        'library_counts' => $library_counts,
        'issues' => $issues,
    ];
}
