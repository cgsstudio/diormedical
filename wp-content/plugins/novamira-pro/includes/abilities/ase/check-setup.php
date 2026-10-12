<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\ASE;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Check ASE setup and report environment status.
 *
 * Does NOT call ase_runtime_guard() — this ability must return useful state
 * information even when ASE is inactive, so agents can diagnose the setup
 * rather than receiving an opaque error.
 */
wp_register_ability('novamira/ase-check-setup', [
    'label' => __('Check ASE Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Admin and Site Enhancements (ASE) Pro environment status: active/inactive, version, min-version satisfied, Pro detection, per-module toggles (Custom Field Types, Custom Content Types), and counts of registered field groups, custom post types, and custom taxonomies. Run this first to diagnose availability of the other ASE abilities.',
        domain: 'novamira-pro',
    ),
    'category' => 'ase',
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
                'description' => 'Whether ASE is currently active (ASENHA_VERSION is defined).',
            ],
            'version' => [
                'type' => 'string',
                'description' => 'The active ASE version, or "inactive" when not loaded.',
            ],
            'min_satisfied' => [
                'type' => 'boolean',
                'description' => 'Whether the active ASE version meets NOVAMIRA_PRO_ASE_MIN_VERSION (8.7.0).',
            ],
            'pro_active' => [
                'type' => 'boolean',
                'description' => 'Whether the Pro distribution of ASE is loaded (bwasenha_fs() is defined). This reflects build presence, not license validity — see pro_licensed.',
            ],
            'pro_licensed' => [
                'type' => 'boolean',
                'description' => 'Whether ASE Pro has a valid active license (Freemius can_use_premium_code__premium_only). When false, ASE refuses to register the Custom Content Types module: the abilities still write rows to wp_posts but ASE will not register the CPTs/taxonomies until the license is activated. Field group / values still work for read+write at the DB layer.',
            ],
            'cft_module_active' => [
                'type' => 'boolean',
                'description' => 'Whether the Custom Field Types module toggle is enabled.',
            ],
            'cct_module_active' => [
                'type' => 'boolean',
                'description' => 'Whether the Custom Content Types module toggle is enabled (governs both CPTs and taxonomies).',
            ],
            'current_user_can_manage' => [
                'type' => 'boolean',
                'description' => 'Whether the current user has manage_options capability.',
            ],
            'field_groups_count' => [
                'type' => 'integer',
                'description' => 'Number of published field group definitions (asenha_cfgroup CPT).',
            ],
            'custom_post_types_count' => [
                'type' => 'integer',
                'description' => 'Number of published custom post type definitions (asenha_cpt CPT).',
            ],
            'custom_taxonomies_count' => [
                'type' => 'integer',
                'description' => 'Number of published custom taxonomy definitions (asenha_ctax CPT).',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\ASE\ase_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run this before using any other ASE ability to verify the environment. Check: active (plugin loaded), min_satisfied (version OK), pro_active (Pro modules available), cft_module_active (needed for field group and value abilities), cct_module_active (needed for CPT and taxonomy abilities).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function ase_check_setup(): array
{
    $active = defined('ASENHA_VERSION');
    $version = $active ? (string) constant('ASENHA_VERSION') : 'inactive';
    $min_satisfied =
        $active
        && version_compare(
            version1: $version,
            version2: (string) constant('NOVAMIRA_PRO_ASE_MIN_VERSION'),
            operator: '>=',
        );

    return [
        'active' => $active,
        'version' => $version,
        'min_satisfied' => $min_satisfied,
        'pro_active' => ase_pro_active(),
        'pro_licensed' => ase_pro_licensed(),
        'cft_module_active' => ase_cft_module_active(),
        'cct_module_active' => ase_cct_module_active(),
        'current_user_can_manage' => current_user_can('manage_options'),
        'field_groups_count' => $active ? cs_count_definitions('asenha_cfgroup') : 0,
        'custom_post_types_count' => $active ? cs_count_definitions('asenha_cpt') : 0,
        'custom_taxonomies_count' => $active ? cs_count_definitions('asenha_ctax') : 0,
    ];
}

/**
 * Count published rows in wp_posts for a given post_type. Bypasses
 * wp_count_posts(), which returns an empty stdClass when the post type
 * isn't registered in the current request context (a real problem in
 * wp-cli where ASE's CCT bootstrap doesn't run before our handler).
 *
 * Cap check is intentionally omitted: this function only runs inside
 * check-setup, whose permission_callback already enforces manage_options.
 */
function cs_count_definitions(string $post_type): int
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return 0;
    }
    /** @var string $sql */
    $sql = $wpdb->prepare(
        'SELECT COUNT(*) FROM %i WHERE post_type = %s AND post_status = %s',
        $wpdb->posts,
        $post_type,
        'publish',
    );
    return (int) $wpdb->get_var($sql);
}
