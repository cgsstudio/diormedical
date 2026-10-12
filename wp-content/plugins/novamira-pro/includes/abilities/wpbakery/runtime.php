<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards for WPBakery abilities.
 *
 * Gates ability registration on both the presence of the WPBMap registry
 * class and the WPB_VC_VERSION constant being at or above the minimum
 * supported release.
 *
 * wpb_runtime_available() is defined in includes/gates.php
 * (namespace Novamira\Pro\Abilities\WPBakery).
 */

/**
 * WPBakery version string, or empty string when the plugin is inactive.
 */
function wpb_version(): string
{
    return defined('WPB_VC_VERSION') ? (string) constant('WPB_VC_VERSION') : '';
}

/**
 * Whether the WPBakery Grid Builder runtime is loaded. Grid Builder ships
 * the vc_basic_grid / vc_media_grid elements and the vc_grid_item custom
 * post type that templates use. The class is registered when WPBakery's
 * grid component is active.
 */
function wpb_grid_builder_active(): bool
{
    return class_exists('WPBakeryShortCode_VC_Basic_Grid') || class_exists('Vc_Grid_Item_Editor');
}

/**
 * Registers the `vc_grid_item` custom post type when WPBakery's Grid
 * Builder is loaded but the CPT itself has not been registered yet.
 *
 * Why this exists: WPBakery only calls Vc_Grid_Item_Editor::addHooksSettings()
 * — which registers the CPT — inside its admin bootstrap. Non-admin
 * surfaces (REST, MCP, wp-cli ability execution) therefore see
 * `invalid_post_type` when an agent tries to `create-post` a grid-item
 * template. Sub-agent A9 hit this on the documented build-page workflow
 * and had to inline `register_post_type()` to unblock — we register it
 * ourselves so the canonical flow succeeds on every request surface.
 *
 * Mirrors the minimal capability shape WPBakery uses internally (private
 * CPT, supports title/editor/custom-fields/page-attributes); the visible
 * admin UI keeps coming from WPBakery itself in admin context.
 */
function wpb_register_grid_item_cpt(): void
{
    if (!wpb_grid_builder_active()) {
        return;
    }
    if (post_type_exists('vc_grid_item')) {
        return;
    }

    // Mirrors WPBakery's own Vc_Grid_Item_Editor::createPostType() args
    // (show_ui=true so WP sets `_edit_link` to 'post.php?post=%d', which
    // makes get_edit_post_link() return a real URL — sub-agent B1 caught
    // edit_url coming back empty for grid item templates). show_in_menu
    // stays false: WPBakery hides the CPT behind its own "Grid Builder"
    // admin screen, so even with show_ui=true the menu doesn't get a
    // standalone entry.
    register_post_type('vc_grid_item', [
        'labels' => ['name' => 'Grid Item Templates'],
        'public' => false,
        'publicly_queryable' => false,
        'show_ui' => true,
        'show_in_menu' => false,
        'show_in_nav_menus' => false,
        'show_in_rest' => false,
        'exclude_from_search' => true,
        'has_archive' => false,
        'rewrite' => false,
        'query_var' => true,
        'capability_type' => 'post',
        'hierarchical' => false,
        'supports' => ['title', 'editor', 'custom-fields'],
    ]);
}
