<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/mosaic-check-setup', [
    'label' => __('Check Mosaic Setup', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Mosaic Pro runtime state in one call. Use this BEFORE any other Mosaic ability. Surfaces whether the plugin is active, its version, the supported version band (min_supported inclusive, max_supported exclusive — Mosaic at or above max is rejected), whether the active version sits inside the band (min_satisfied + max_satisfied), the edition (free / pro), the distribution channel (stable / beta / dev), whether the custom tables have been provisioned, which class storage the install uses, and counts of the four top-level entities (themes, templates, components, collections). Every other Mosaic call is gated on `plugin.runtime_ready` (the AND of active + version-band + class + tables). `plugin.class_storage` is `current` when Mosaic\'s Classes, sub classes, Variants and variant sub classes are available to the tools; `legacy` means this Mosaic install still stores classes in its legacy format — the class and variant tools (and the element `css_classes` / `classes` / `detach_classes` / `variant` inputs) return `mo_legacy_class_storage` until Mosaic is updated, while everything else keeps working. A handful of advanced operations additionally require `plugin.edition=pro`.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'plugin' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'max_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                    'max_satisfied' => ['type' => 'boolean'],
                    'edition' => ['type' => 'string', 'enum' => ['free', 'pro']],
                    'channel' => ['type' => 'string'],
                    'tables_present' => ['type' => 'boolean'],
                    'runtime_ready' => ['type' => 'boolean'],
                    'class_storage' => ['type' => 'string', 'enum' => ['current', 'legacy', 'unavailable']],
                ],
                'required' => [
                    'active',
                    'version',
                    'min_supported',
                    'max_supported',
                    'min_satisfied',
                    'max_satisfied',
                    'edition',
                    'channel',
                    'tables_present',
                    'runtime_ready',
                    'class_storage',
                ],
                'additionalProperties' => false,
            ],
            'entities' => [
                'type' => 'object',
                'description' => 'Counts of the four top-level Mosaic entities. Useful as a smoke signal that the install has been used: a fresh install has 0 of everything; the Mosaic onboarding writes at least 1 theme on first admin pageview (templates / components / collections are added as the user works in the editor).',
                'properties' => [
                    'themes' => ['type' => 'integer'],
                    'templates' => ['type' => 'integer'],
                    'components' => ['type' => 'integer'],
                    'collections' => ['type' => 'integer'],
                ],
                'required' => ['themes', 'templates', 'components', 'collections'],
                'additionalProperties' => false,
            ],
            'current_user_can_manage' => ['type' => 'boolean'],
        ],
        'required' => ['plugin', 'entities', 'current_user_can_manage'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always the first call on a Mosaic site. Surface plugin.runtime_ready=false or plugin.edition=free to the user before attempting Pro-only abilities. If plugin.class_storage=legacy, tell the user to update Mosaic before any Class or Variant work. If entities.themes=0 the install has never been opened in the Mosaic admin — the onboarding writes the first theme on first pageview.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function mosaic_check_setup(): array
{
    $version = mo_version();
    $active = $version !== '' && class_exists('Mosaic\\Database\\MosaicDB');
    $tables_present = $active && mo_tables_present();
    // `min_satisfied` / `max_satisfied` answer ONLY the version-band check
    // independent of whether the tables are present or the runtime is
    // fully ready. Use `runtime_ready` for the full AND-gate.
    $min_satisfied = $active && version_compare($version, NOVAMIRA_PRO_MOSAIC_MIN_VERSION, operator: '>=');
    $max_satisfied = $active && version_compare($version, NOVAMIRA_PRO_MOSAIC_MAX_VERSION, operator: '<');

    return [
        'plugin' => [
            'active' => $active,
            'version' => $version,
            'min_supported' => NOVAMIRA_PRO_MOSAIC_MIN_VERSION,
            'max_supported' => NOVAMIRA_PRO_MOSAIC_MAX_VERSION,
            'min_satisfied' => $min_satisfied,
            'max_satisfied' => $max_satisfied,
            'edition' => mo_edition(),
            'channel' => mo_channel(),
            'tables_present' => $tables_present,
            'runtime_ready' => mo_runtime_available(),
            'class_storage' => $tables_present ? mo_class_storage() : 'unavailable',
        ],
        'entities' => $tables_present ? mo_entity_counts() : mo_zero_entity_counts(),
        'current_user_can_manage' => current_user_can('manage_options'),
    ];
}

/**
 * Cheap COUNT(*) on the four top-level entity tables. Caller guarantees
 * the tables exist (use `mo_zero_entity_counts()` when they don't, so
 * the check-setup response shape stays stable even on broken installs).
 *
 * @return array{themes: int, templates: int, components: int, collections: int}
 */
function mo_entity_counts(): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    /** @var mixed $themes */
    $themes = $wpdb->get_var('SELECT COUNT(*) FROM ' . mo_table('themes'));
    /** @var mixed $templates */
    $templates = $wpdb->get_var('SELECT COUNT(*) FROM ' . mo_table('templates'));
    /** @var mixed $components */
    $components = $wpdb->get_var('SELECT COUNT(*) FROM ' . mo_table('components'));
    /** @var mixed $collections */
    $collections = $wpdb->get_var('SELECT COUNT(*) FROM ' . mo_table('collections'));
    return [
        'themes' => (int) $themes,
        'templates' => (int) $templates,
        'components' => (int) $components,
        'collections' => (int) $collections,
    ];
}

/**
 * Zero-counts placeholder returned by `mosaic_check_setup` when the
 * Mosaic schema isn't provisioned yet — keeps the response shape stable.
 *
 * @return array{themes: int, templates: int, components: int, collections: int}
 */
function mo_zero_entity_counts(): array
{
    return ['themes' => 0, 'templates' => 0, 'components' => 0, 'collections' => 0];
}
