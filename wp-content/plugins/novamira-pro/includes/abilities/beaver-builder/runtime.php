<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\BeaverBuilder;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Whether Beaver Builder's Global Styles (the reusable Global Colors token
 * system) is usable. Global Styles ships inside the builder as an auto-loaded
 * extension, so this is the builder runtime plus the real FLBuilderGlobalStyles
 * class — the `method_exists('save_settings')` guard also excludes the
 * read-only polyfill stub an out-of-date Beaver Themer can install in its place.
 *
 * bb_runtime_available(), bb_plugin_active(), bb_version() are defined in
 * includes/gates.php (namespace Novamira\Pro\Abilities\BeaverBuilder).
 */
function bb_global_styles_available(): bool
{
    return (
        bb_runtime_available()
        && class_exists('FLBuilderGlobalStyles')
        && method_exists('FLBuilderGlobalStyles', 'get_settings')
        && method_exists('FLBuilderGlobalStyles', 'save_settings')
    );
}

/**
 * Whether reusable templates can be used at all. Whole-page LAYOUT templates
 * need only the builder runtime.
 */
function bb_templates_available(): bool
{
    return bb_runtime_available();
}

/**
 * Whether saved row/column/module (node) templates and global templates are
 * available — Beaver's node-templates feature, off on Lite and when templates
 * are set to core-only/disabled.
 */
function bb_node_templates_available(): bool
{
    return (
        bb_runtime_available()
        && !(defined('FL_BUILDER_LITE') && constant('FL_BUILDER_LITE') === true)
        && class_exists('FLBuilderModel')
        && method_exists('FLBuilderModel', 'node_templates_enabled')
        && \FLBuilderModel::node_templates_enabled()
    );
}

/**
 * Whether Beaver Themer is active alongside the main builder.
 */
function bb_theme_builder_active(): bool
{
    return defined('FL_THEME_BUILDER_VERSION');
}

/**
 * Beaver Themer version, or empty string when inactive.
 */
function bb_theme_builder_version(): string
{
    return defined('FL_THEME_BUILDER_VERSION') ? (string) constant('FL_THEME_BUILDER_VERSION') : '';
}
