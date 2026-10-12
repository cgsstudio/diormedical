<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * MB Builder (UI-defined field groups stored as `meta-box` CPT posts).
 *
 * Detected by the presence of the `meta-box` CPT itself — the most reliable
 * signal across MB Builder release lines (the `MBB\Loader` class is only set
 * up after the post type is registered, so post_type_exists is the right
 * canonical check).
 */
function mb_builder_available(): bool
{
    /** @var bool|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return post_type_exists('meta-box');
}

/**
 * MB Custom Post Type (covers both `mb-post-type` and `mb-taxonomy` CPTs —
 * the extension handles CPTs and custom taxonomies in the same plugin).
 */
function mb_cpt_available(): bool
{
    /** @var bool|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return post_type_exists('mb-post-type') && post_type_exists('mb-taxonomy');
}

/**
 * MB Settings Page (settings-page definitions registered via the MBSP loader).
 */
function mb_settings_page_available(): bool
{
    /** @var bool|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return class_exists('MBSP\\Loader') || function_exists('mb_settings_page');
}

/**
 * MB Relationships (many-to-many associations between objects).
 */
function mb_relationships_available(): bool
{
    /** @var bool|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return class_exists('MB_Relationships_API');
}

/**
 * Whether the MB Relationships *builder* (the DB-backed UI) is active — i.e.
 * the `mb-relationship` CPT is registered AND the builder's settings parser
 * class is loadable. When true, relationship definitions persist as published
 * `mb-relationship` posts (re-registered every request via the
 * `mb_relationships_init` hook), exactly like the CPT / taxonomy / field-group
 * builders. When false (e.g. the free, API-only MB Relationships), the only
 * persistence path is a PHP `MB_Relationships_API::register()` call.
 */
function mb_relationships_builder_active(): bool
{
    return (
        post_type_exists('mb-relationship') && class_exists('\\MBB\\Extensions\\Relationships\\Parsers\\Relationship')
    );
}

/**
 * MB Term Meta — lets a field group attach to taxonomies. Without this
 * extension, `create-field-group taxonomies=[...]` would save the binding
 * but the runtime would not render the field group on term edit screens.
 */
function mb_term_meta_available(): bool
{
    /** @var bool|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return class_exists('\\MBTM\\Loader') || class_exists('RWMB_Term_Storage');
}

/**
 * MB Comment Meta — lets a field group attach to comments. Loaded on the
 * `init` action with priority 5; by the time abilities run, the class
 * should be defined.
 */
function mb_comment_meta_available(): bool
{
    /** @var bool|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return class_exists('MB_Comment_Meta_Box') || class_exists('MB_Comment_Meta_Loader');
}

/**
 * MB User Meta — lets a field group attach to users. Not bundled in any
 * free distribution; standalone paid extension on metabox.io. Detection
 * is multi-signal best-effort because the extension is not present in any
 * MB stack we ship test fixtures against.
 */
function mb_user_meta_available(): bool
{
    /** @var bool|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return class_exists('\\MBUM\\Loader') || class_exists('MB_User_Meta_Box') || function_exists('mb_user_meta_load');
}

/**
 * MB Group field type. Without this extension, a field with `type: group`
 * registers but its nested fields are dropped at runtime.
 */
function mb_group_available(): bool
{
    /** @var bool|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return class_exists('RWMB_Group_Field');
}

/**
 * MB Custom Table — stores field values in a custom DB table instead of
 * `postmeta`. When a field group is flagged for custom-table storage, the
 * standard `read-values` / `write-values` abilities (which read/write
 * postmeta) will silently miss the data. This integration explicitly does
 * not cover custom-table CRUD; the flag exists so the agent can detect
 * the situation and warn the user.
 */
function mb_custom_table_available(): bool
{
    /** @var bool|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return (
        class_exists('\\MetaBox\\CustomTable\\Loader')
        || class_exists('\\MBCT\\Loader')
        || function_exists('mb_custom_table_load')
    );
}
