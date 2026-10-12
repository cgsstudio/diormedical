<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Voxel theme presence + capability snapshot. Registers whenever Voxel
 * is the active theme (presence gate), even below the minimum version, so the
 * agent can always learn the setup state. Never returns a WP_Error.
 */

wp_register_ability('novamira/voxel-check-setup', [
    'label' => __('Check Voxel Setup', domain: 'novamira-pro'),
    'description' => __(
        'Diagnostic snapshot of the Voxel theme install: active flag, version, minimum supported version and whether it is satisfied, PHP version check, the count and keys of Voxel-managed post types, the count of Voxel taxonomies, which Voxel database tables are present, and whether Elementor and WooCommerce are active. Call once at the start of any Voxel session and branch on theme.min_satisfied before using any other voxel-* ability.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    // NOTE: keep `properties` an empty ARRAY (not stdClass). With
    // additionalProperties:false, core's validator fatals on an object here.
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
                    'min_required' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                    'php_version' => ['type' => 'string'],
                    'php_required' => ['type' => 'string'],
                    'php_satisfied' => ['type' => 'boolean'],
                ],
                'required' => [
                    'active',
                    'version',
                    'min_required',
                    'min_satisfied',
                    'php_version',
                    'php_required',
                    'php_satisfied',
                ],
            ],
            'content' => [
                'type' => 'object',
                'properties' => [
                    'post_types_count' => ['type' => 'integer'],
                    'post_type_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'taxonomies_count' => ['type' => 'integer'],
                ],
                'required' => ['post_types_count', 'post_type_keys', 'taxonomies_count'],
            ],
            'tables' => [
                'type' => 'object',
                'description' => 'Presence map for the Voxel custom tables (name => bool). Missing tables usually mean the matching module has never run its installer.',
            ],
            'index_tables' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Per-post-type search index tables present (voxel_index_<key>).',
            ],
            'index_tables_missing' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Post type keys that are configured but have no search index table. Their posts cannot be indexed, so Voxel front-end search will never return them.',
            ],
            'integrations' => [
                'type' => 'object',
                'properties' => [
                    'elementor' => ['type' => 'boolean'],
                    'elementor_atomic' => [
                        'type' => 'boolean',
                        'description' => 'Whether the Elementor v4 atomic-widgets runtime is available. Caveat for building on Voxel: a plain @tags() string inside an atomic v4 element is NOT resolved and renders literally — use legacy v3 widgets for Voxel dynamic tags.',
                    ],
                    'woocommerce' => ['type' => 'boolean'],
                ],
                'required' => ['elementor', 'elementor_atomic', 'woocommerce'],
            ],
            'storage' => [
                'type' => 'object',
                'properties' => [
                    'post_types_option_key' => ['type' => 'string'],
                    'post_types_option_bytes' => ['type' => 'integer'],
                ],
                'required' => ['post_types_option_key', 'post_types_option_bytes'],
            ],
            'current_user_can_manage' => ['type' => 'boolean'],
        ],
        'required' => [
            'theme',
            'content',
            'tables',
            'index_tables',
            'index_tables_missing',
            'integrations',
            'storage',
            'current_user_can_manage',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call once at the start of any Voxel-touching session. If this ability is missing from your tool list, Voxel is not the active theme. If theme.min_satisfied=false, stop and surface the gap: every other voxel-* ability will refuse with voxel_version_unsupported. Use content.post_type_keys to pick a post type for list-post-types / get-post-type / list-posts. This snapshot is compact by design; read actual structure with the get-* abilities.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     theme: array{active: bool, version: string, min_required: string, min_satisfied: bool, php_version: string, php_required: string, php_satisfied: bool},
 *     content: array{post_types_count: int, post_type_keys: list<string>, taxonomies_count: int},
 *     tables: array<string, bool>,
 *     index_tables: list<string>,
 *     index_tables_missing: list<string>,
 *     integrations: array{elementor: bool, elementor_atomic: bool, woocommerce: bool},
 *     storage: array{post_types_option_key: string, post_types_option_bytes: int},
 *     current_user_can_manage: bool,
 * }
 */
function voxel_check_setup(): array
{
    $setup = vx_detect_setup();

    // Content model — guarded so a hypothetical below-min Voxel with a different
    // API never fatals the diagnostic.
    $post_type_keys = [];
    if ($setup['present'] && class_exists('Voxel\\Post_Type') && method_exists('Voxel\\Post_Type', 'get_voxel_types')) {
        foreach (array_keys(\Voxel\Post_Type::get_voxel_types()) as $k) {
            $post_type_keys[] = $k;
        }
    }
    $taxonomies_count = 0;
    if (
        $setup['present']
        && class_exists('Voxel\\Taxonomy')
        && method_exists('Voxel\\Taxonomy', 'get_voxel_taxonomies')
    ) {
        $taxonomies_count = count(\Voxel\Taxonomy::get_voxel_taxonomies());
    }

    // Elementor: prefer Voxel's own detector, fall back to the core class.
    $elementor = function_exists('Voxel\\is_elementor_active')
        ? \Voxel\is_elementor_active()
        : class_exists('Elementor\\Plugin');

    /** @var mixed $raw_option */
    $raw_option = get_option('voxel:post_types');
    $option_bytes = is_string($raw_option) ? strlen($raw_option) : 0;

    // A configured post type with no index table can never be indexed: every
    // index()/unindex() call fails on a missing table and the error is swallowed,
    // so the type stays invisible to Voxel's front-end search. Surfacing the gap
    // here is the cheapest way to catch that drift.
    $index_tables = vx_index_tables();
    $index_tables_missing = [];
    foreach ($post_type_keys as $key) {
        if (in_array('voxel_index_' . $key, $index_tables, strict: true)) {
            continue;
        }
        $index_tables_missing[] = $key;
    }

    return [
        'theme' => [
            'active' => $setup['active'],
            'version' => $setup['version'],
            'min_required' => $setup['min_required'],
            'min_satisfied' => $setup['min_satisfied'],
            'php_version' => $setup['php_version'],
            'php_required' => $setup['php_required'],
            'php_satisfied' => $setup['php_satisfied'],
        ],
        'content' => [
            'post_types_count' => count($post_type_keys),
            'post_type_keys' => $post_type_keys,
            'taxonomies_count' => $taxonomies_count,
        ],
        'tables' => vx_table_presence_map(),
        'index_tables' => $index_tables,
        'index_tables_missing' => $index_tables_missing,
        'integrations' => [
            'elementor' => $elementor,
            // The same runtime probe Voxel gates its atomic integration on
            // (elementor-controller.php, register_atomic_vx). Surfaced because
            // atomic availability is a trap on Voxel sites: a plain @tags()
            // string in an atomic element renders literally (only Voxel's own
            // controls and the $$type:"vx" envelope resolve tags).
            'elementor_atomic' =>
                $elementor && class_exists('Elementor\\Modules\\AtomicWidgets\\PropTypes\\Base\\Plain_Prop_Type'),
            'woocommerce' => class_exists('WooCommerce'),
        ],
        'storage' => [
            'post_types_option_key' => 'voxel:post_types',
            'post_types_option_bytes' => $option_bytes,
        ],
        'current_user_can_manage' => current_user_can('manage_options'),
    ];
}

/**
 * Presence map for the known Voxel custom tables (name without prefix => bool).
 *
 * @return array<string, bool>
 */
function vx_table_presence_map(): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $names = [
        'vx_orders',
        'vx_order_items',
        'voxel_timeline',
        'voxel_timeline_replies',
        'voxel_followers',
        'voxel_relations',
        'voxel_notifications',
        'voxel_chats',
        'voxel_messages',
        'voxel_visits',
        'voxel_work_hours',
        'voxel_recurring_dates',
    ];
    $map = [];
    foreach ($names as $name) {
        $full = $wpdb->prefix . $name;
        $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $full);
        $found = is_string($sql) ? $wpdb->get_var($sql) : null;
        $map[$name] = $found === $full;
    }
    return $map;
}

/**
 * Per-post-type search index tables present, with the site prefix stripped.
 *
 * @return list<string>
 */
function vx_index_tables(): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $prefix = $wpdb->prefix;
    $like = $wpdb->esc_like($prefix . 'voxel_index_') . '%';
    $sql = $wpdb->prepare('SHOW TABLES LIKE %s', $like);
    /** @var list<string> $rows */
    $rows = is_string($sql) ? $wpdb->get_col($sql) : [];
    $out = [];
    foreach ($rows as $name) {
        if (str_starts_with($name, $prefix)) {
            $name = substr($name, strlen($prefix));
        }
        $out[] = $name;
    }
    return $out;
}
