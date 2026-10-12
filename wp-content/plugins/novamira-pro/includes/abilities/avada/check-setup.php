<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-check-setup', [
    'label' => __('Check Avada Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Avada theme environment status: theme active, version, minimum-satisfied flag, Fusion Builder companion-plugin state, Fusion Core companion-plugin state, current-user capability, `fusion_options` presence, and counts of portfolio + FAQ posts. Run this before any Avada-touching workflow to confirm the environment is ready and to detect the "theme installed but companion plugin missing" configuration.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'theme' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'fusion_builder' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'fusion_core' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'capability' => [
                'type' => 'object',
                'properties' => [
                    'can_manage_options' => ['type' => 'boolean'],
                ],
                'required' => ['can_manage_options'],
                'additionalProperties' => false,
            ],
            'options' => [
                'type' => 'object',
                'properties' => [
                    'fusion_options_present' => ['type' => 'boolean'],
                ],
                'required' => ['fusion_options_present'],
                'additionalProperties' => false,
            ],
            'counts' => [
                'type' => 'object',
                'properties' => [
                    'portfolio_items' => ['type' => 'integer'],
                    'faqs' => ['type' => 'integer'],
                ],
                'required' => ['portfolio_items', 'faqs'],
                'additionalProperties' => false,
            ],
            'issues' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Actionable configuration warnings (theme under floor, companion plugin missing, options row missing on a fresh install).',
            ],
        ],
        'required' => ['theme', 'fusion_builder', 'fusion_core', 'capability', 'options', 'counts', 'issues'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run first on any Avada task. Gate every subsequent write on `theme.min_satisfied`. `fusion_builder.active` false means element-tree CRUD / Template Builder / Fusion Forms are unavailable — theme-side abilities still work.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function avada_check_setup(): array
{
    $theme_active = defined('AVADA_VERSION');
    $theme_version = avada_version();
    $theme_min_satisfied = avada_theme_available();
    $fb_active = defined('FUSION_BUILDER_VERSION');
    $fc_active = defined('FUSION_CORE_VERSION');

    // Route through the same counter the list-* abilities use so the two
    // response fields never diverge (happy-path audit A1 finding F1) and so
    // the count picks up freshly-inserted posts without waiting for
    // `wp_count_posts`'s per-request cache to invalidate.
    $portfolio_count = $theme_active ? av_query_total(AVADA_CPT_PORTFOLIO, status: 'any') : 0;
    $faq_count = $theme_active ? av_query_total(AVADA_CPT_FAQ, status: 'any') : 0;

    return [
        'theme' => [
            'active' => $theme_active,
            'version' => $theme_version,
            'min_supported' => NOVAMIRA_PRO_AVADA_MIN_VERSION,
            'min_satisfied' => $theme_min_satisfied,
        ],
        'fusion_builder' => [
            'active' => $fb_active,
            'version' => $fb_active ? (string) constant('FUSION_BUILDER_VERSION') : '',
            'min_supported' => NOVAMIRA_PRO_FUSION_BUILDER_MIN_VERSION,
            'min_satisfied' => fusion_builder_available(),
        ],
        'fusion_core' => [
            'active' => $fc_active,
            'version' => $fc_active ? (string) constant('FUSION_CORE_VERSION') : '',
            'min_supported' => NOVAMIRA_PRO_FUSION_CORE_MIN_VERSION,
            'min_satisfied' => fusion_core_available(),
        ],
        'capability' => [
            'can_manage_options' => current_user_can('manage_options'),
        ],
        'options' => [
            'fusion_options_present' => avada_options_row_exists(),
        ],
        'counts' => [
            'portfolio_items' => $portfolio_count,
            'faqs' => $faq_count,
        ],
        'issues' => avada_check_setup_issues(
            $theme_active,
            $theme_min_satisfied,
            $theme_version,
            $fb_active,
            $fc_active,
        ),
    ];
}

/**
 * Build the actionable `issues[]` list from the collected environment
 * signals. Kept out of the callback body so the top-level function stays
 * under the complexity budget while every warning maps to a discrete gate.
 * The 4 boolean parameters mirror the 4 discrete environment gates the
 * caller has just probed — they are outcome signals, not behaviour flags,
 * so the no-boolean-flag-parameter rule doesn't apply.
 *
 * @return list<string>
 */
// @mago-expect lint:no-boolean-flag-parameter
function avada_check_setup_issues(
    bool $theme_active,
    bool $theme_min_satisfied,
    string $theme_version,
    bool $fb_active,
    bool $fc_active,
): array {
    $issues = [];
    if (!$theme_active) {
        $issues[] = 'Avada theme is not the active theme on this site.';
        return $issues;
    }
    if (!$theme_min_satisfied) {
        $issues[] = sprintf(
            /* translators: 1: installed version, 2: minimum version */
            'Avada %1$s is installed but below the minimum supported version %2$s. Update Avada.',
            $theme_version,
            NOVAMIRA_PRO_AVADA_MIN_VERSION,
        );
    }
    if (!$fb_active) {
        $issues[] = 'Fusion Builder companion plugin is not installed. Element-tree CRUD, Template Builder, and Fusion Forms abilities are unavailable in this environment; theme-side abilities still work.';
    }
    if (!$fc_active) {
        $issues[] = 'Fusion Core companion plugin is not installed. Fusion Sliders (slide CPT), Fusion Elements (fusion_element), and some Studio import paths are unavailable in this environment.';
    }
    if ($fc_active && !fusion_core_available()) {
        $issues[] = sprintf(
            /* translators: 1: installed version, 2: minimum version */
            'Fusion Core %1$s is installed but below the minimum supported version %2$s. Update Fusion Core to enable the Fusion Slider abilities.',
            (string) constant('FUSION_CORE_VERSION'),
            NOVAMIRA_PRO_FUSION_CORE_MIN_VERSION,
        );
    }
    if (!avada_options_row_exists()) {
        $issues[] = 'The `fusion_options` DB row does not exist yet — either the theme has never rendered admin (fresh install), or the row was purged. Load /wp-admin once to populate defaults before writing theme options.';
    }
    return $issues;
}
