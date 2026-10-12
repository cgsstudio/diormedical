<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\JetEngine;

use Jet_Engine\Modules\Custom_Content_Types\Factory;
use Jet_Engine\Modules\Custom_Content_Types\Module;
use Jet_Engine_Modules;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Detect JetEngine presence, version, capability, and CCT module state.
 * Cached per request.
 *
 * @return array{
 *     active: bool,
 *     version: string|null,
 *     min_satisfied: bool,
 *     cct_module_active: bool,
 *     can_manage: bool,
 *     mcp_enabled: bool,
 * }
 */
function jetengine_detect_setup(): array
{
    /** @var null|array{active: bool, version: string|null, min_satisfied: bool, cct_module_active: bool, can_manage: bool, mcp_enabled: bool} $cache */
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $active = class_exists('Jet_Engine');
    $version = $active && function_exists('jet_engine') ? jet_engine()->get_version() : null;
    $min_satisfied =
        $active && $version !== null && version_compare($version, NOVAMIRA_PRO_JETENGINE_MIN_VERSION, operator: '>=');

    $cct_module_active = false;
    if ($min_satisfied && function_exists('jet_engine')) {
        $modules = jet_engine()->modules ?? null;
        if ($modules instanceof Jet_Engine_Modules) {
            $cct_module_active = $modules->is_module_active('custom-content-types');
        }
    }

    /** @var mixed $raw_misc */
    $raw_misc = get_option('jet-engine-misc-settings', []);
    $misc = is_array($raw_misc) ? $raw_misc : [];
    $has_explicit_mcp = array_key_exists('enable_mcp_server', $misc);
    $mcp_enabled = $has_explicit_mcp ? filter_var($misc['enable_mcp_server'], FILTER_VALIDATE_BOOLEAN) : $min_satisfied; // JetEngine ensures default true when settings are unset.

    return $cache = [
        'active' => $active,
        'version' => $version,
        'min_satisfied' => $min_satisfied,
        'cct_module_active' => $cct_module_active,
        'can_manage' => current_user_can('manage_options'),
        'mcp_enabled' => $mcp_enabled,
    ];
}

/**
 * Locate a JetEngine module by slug, returning null when JetEngine is missing
 * or the module is not active. Avoids fatal errors at call sites.
 */
function jetengine_get_module(string $name): ?object
{
    if (!class_exists('Jet_Engine') || !function_exists('jet_engine')) {
        return null;
    }

    $modules = jet_engine()->modules ?? null;
    if (!$modules instanceof Jet_Engine_Modules) {
        return null;
    }

    if (!$modules->is_module_active($name)) {
        return null;
    }

    /** @var mixed $module */
    $module = $modules->get_module($name);
    return is_object($module) ? $module : null;
}

/**
 * Drop JetEngine's in-request data caches so a write followed by a read in
 * the same request returns fresh data.
 *
 * Two caches are involved depending on storage:
 * - `$data->raw` — meta-boxes / options-pages on options storage; populated
 *   by `get_raw()` and never refreshed within the request.
 * - `$data->db->query_cache` — anything using the Jet_Engine_DB layer
 *   (CCT types, options-pages); query() seeds it on first call.
 *
 * Best-effort; silently no-ops on unexpected shapes.
 */
function jetengine_invalidate_raw_cache(?object $data): void
{
    if ($data === null) {
        return;
    }
    if (property_exists($data, 'raw')) {
        /** @phpstan-ignore-next-line dynamic JetEngine property */
        $data->raw = null;
    }
    /** @var mixed $db */
    $db = $data->db ?? null;
    if (is_object($db) && property_exists($db, 'query_cache')) {
        /** @phpstan-ignore-next-line dynamic JetEngine property */
        $db->query_cache = [];
    }
}

/**
 * Convenience accessor for the namespaced CCT Module instance.
 *
 * JetEngine wraps each module in a non-namespaced container
 * (`Jet_Engine_Module_Custom_Content_Types`) whose `$instance` property
 * holds the actual `\Jet_Engine\Modules\Custom_Content_Types\Module`. We
 * unwrap here so callers can use the typed namespaced API directly.
 */
function jetengine_get_cct_module(): ?Module
{
    $wrapper = jetengine_get_module('custom-content-types');
    if ($wrapper === null) {
        return null;
    }

    /** @var mixed $instance */
    $instance = $wrapper->instance ?? null;
    return $instance instanceof Module ? $instance : null;
}

/**
 * Determine whether a meta box id is registered in DB, via PHP filter, or unknown.
 *
 * @return 'db'|'php'|null
 */
function jetengine_meta_box_source(string $id): ?string
{
    if (!class_exists('Jet_Engine') || !function_exists('jet_engine')) {
        return null;
    }

    $data = jet_engine()->meta_boxes->data ?? null;
    if ($data === null) {
        return null;
    }

    /** @var mixed $raw_option */
    $raw_option = get_option('jet_engine_meta_boxes', []);
    /** @var array<array<string, mixed>> $option */
    $option = is_array($raw_option) ? $raw_option : [];
    foreach ($option as $row) {
        if ((string) ($row['id'] ?? '') === $id) {
            return 'db';
        }
    }

    foreach ($data->get_items() as $row) {
        if ((string) ($row['id'] ?? '') === $id) {
            return 'php';
        }
    }

    return null;
}

/**
 * Determine whether an options page is registered in DB, via PHP filter, or unknown.
 *
 * Options pages are stored in `wp_jet_post_types` (status='page') keyed by
 * `slug`, NOT in a wp_options option. The agent-facing id is the slug.
 *
 * @return 'db'|'php'|null
 */
function jetengine_options_page_source(string $id): ?string
{
    if (!class_exists('Jet_Engine') || !function_exists('jet_engine')) {
        return null;
    }

    global $wpdb;
    if ($wpdb instanceof \wpdb) {
        /** @var string $op_source_sql */
        $op_source_sql = $wpdb->prepare(
            'SELECT id FROM %i WHERE slug = %s AND status = %s LIMIT 1',
            $wpdb->prefix . 'jet_post_types',
            $id,
            'page',
        );
        $row = $wpdb->get_var($op_source_sql);
        if ($row !== null) {
            return 'db';
        }
    }

    $data = jet_engine()->options_pages->data ?? null;
    if ($data === null) {
        return null;
    }
    foreach ($data->get_items() as $row) {
        if ((string) ($row['slug'] ?? '') === $id) {
            return 'php';
        }
    }

    return null;
}

/**
 * Determine whether a CCT slug is registered in DB, via PHP, or unknown.
 *
 * JetEngine stores CCT type definitions in the `wp_jet_post_types` custom
 * table (status = 'content-type'), not in a WordPress option. PHP-registered
 * CCTs are loaded via filter/PHP but not written to that table, so anything
 * NOT in the table but present in the factory map is PHP-registered.
 *
 * @return 'db'|'php'|null
 */
function jetengine_cct_source(string $slug): ?string
{
    $module = jetengine_get_cct_module();
    if ($module === null) {
        return null;
    }

    // Authoritative check against `wp_jet_post_types` via $wpdb, bypassing
    // JetEngine's stale in-request query_cache.
    global $wpdb;
    if ($wpdb instanceof \wpdb) {
        /** @var string $source_sql */
        $source_sql = $wpdb->prepare(
            'SELECT id FROM %i WHERE slug = %s AND status = %s LIMIT 1',
            $wpdb->prefix . 'jet_post_types',
            $slug,
            'content-type',
        );
        $row = $wpdb->get_var($source_sql);
        if ($row !== null) {
            return 'db';
        }
    }

    // Not in DB. We intentionally do NOT fall back to the Manager's in-memory
    // `_registered_instances` cache here: that map is populated once at boot
    // from the DB and is never invalidated when a CCT row is deleted in the
    // same request, so a cache hit can't be distinguished from a genuine
    // PHP-filter-registered CCT. Trusting the cache would mis-flag a freshly-
    // deleted slug as 'php' and reject a legitimate re-create with
    // cct_not_editable. Until JetEngine exposes a public re-init we treat
    // "not in DB" as "not registered".
    return null;
}

/**
 * Build the full DB table name for a CCT slug.
 *
 * JetEngine uses the prefix `jet_cct_` for CCT record tables
 * (see Jet_Engine\Modules\Custom_Content_Types\DB::$prefix).
 */
function jetengine_cct_table_name(string $slug): string
{
    global $wpdb;
    $prefix = $wpdb instanceof \wpdb ? $wpdb->prefix : 'wp_';
    return $prefix . 'jet_cct_' . $slug;
}

/**
 * Count records in a CCT table. Returns 0 if the table is missing.
 */
function jetengine_cct_records_count(string $slug): int
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return 0;
    }

    $table = jetengine_cct_table_name($slug);
    /** @var string $show_tables_sql */
    $show_tables_sql = $wpdb->prepare('SHOW TABLES LIKE %s', $table);
    $exists = $wpdb->get_var($show_tables_sql);
    if ($exists !== $table) {
        return 0;
    }

    /** @var string $count_sql */
    $count_sql = $wpdb->prepare('SELECT COUNT(*) FROM %i', $table);
    return (int) $wpdb->get_var($count_sql);
}

/**
 * Strip JetEngine service fields from a CCT field list. The Factory
 * constructor merges service fields (`cct_status`, `cct_created`,
 * `cct_modified`, `cct_author_id`, `_ID`, `cct_slug`) into the per-instance
 * `$fields` array. Agent-facing reads (and the diff used by edit-cct) must
 * only see user-defined fields.
 *
 * @param array<int, array<string, mixed>> $fields
 * @param array<string, mixed>             $args   The Factory args; some
 *                                                 service fields are
 *                                                 conditional on toggles
 *                                                 like `is_admin`.
 * @return array<int, array<string, mixed>>
 */
function jetengine_filter_user_fields(array $fields, array $args): array
{
    $module = jetengine_get_cct_module();
    if ($module === null) {
        return $fields;
    }

    $service = $module->manager->data->get_service_fields($args);
    $service_names = [];
    foreach ($service as $sf) {
        if (!array_key_exists('name', $sf)) {
            continue;
        }
        $service_names[(string) $sf['name']] = true;
    }

    $out = [];
    foreach ($fields as $f) {
        if (!array_key_exists('name', $f)) {
            continue;
        }
        if (array_key_exists((string) $f['name'], $service_names)) {
            continue;
        }
        $out[] = $f;
    }
    return $out;
}

/**
 * Lazy-load JetEngine's CCT Factory class on the rare path where no CCT
 * exists at boot (Manager::register_instances() short-circuits on an empty
 * registry and never `require`s the class files). Without this, our wpdb-
 * direct factory lookup can find a row but be unable to instantiate Factory.
 */
function jetengine_ensure_cct_factory_loaded(Module $module): void
{
    if (class_exists(Factory::class)) {
        return;
    }
    // Module exposes a public `module_path()` helper to resolve files relative
    // to the CCT module directory. Guard against typos / refactors with
    // method_exists.
    if (!method_exists($module, 'module_path')) {
        return;
    }
    foreach (['single-item-factory.php', 'factory.php'] as $file) {
        /** @var mixed $path */
        $path = $module->module_path($file);
        if (is_string($path) && is_readable($path)) {
            require_once $path;
        }
    }
}

/**
 * Coerce a JetEngine serialized-or-array column into a plain array.
 *
 * @param mixed $value
 * @return array<array-key, mixed>
 */
function jetengine_cct_coerce_array(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (is_string($value)) {
        /** @var mixed $deserialized */
        $deserialized = maybe_unserialize($value);
        return is_array($deserialized) ? $deserialized : [];
    }
    return [];
}

/**
 * Resolve the CCT factory for a slug. Returns null when the module is off
 * or the slug is unknown.
 *
 * Reads directly from `wp_jet_post_types` via $wpdb to bypass JetEngine's
 * in-request `query_cache`. JetEngine populates that cache once at boot from
 * the initial DB state and re-populates it lazily, but writes inside the
 * same request can leave it stale until we hit a fresh query. Going through
 * $wpdb avoids the indirection and keeps create→get/edit/delete consistent
 * within one request.
 */
function jetengine_cct_get_factory(string $slug): ?Factory
{
    $module = jetengine_get_cct_module();
    if ($module === null) {
        return null;
    }

    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return null;
    }

    /** @var string $select_sql */
    $select_sql = $wpdb->prepare(
        'SELECT id, args, meta_fields FROM %i WHERE slug = %s AND status = %s LIMIT 1',
        $wpdb->prefix . 'jet_post_types',
        $slug,
        'content-type',
    );
    /** @var array<string, string>|null $row */
    $row = $wpdb->get_row($select_sql, 'ARRAY_A');

    if ($row === null) {
        // See jetengine_cct_source(): we deliberately don't fall back to the
        // Manager's in-memory cache because we can't distinguish a stale
        // post-delete cache hit from a real PHP registration.
        return null;
    }

    jetengine_ensure_cct_factory_loaded($module);
    if (!class_exists(Factory::class)) {
        return null;
    }

    $args = jetengine_cct_coerce_array($row['args'] ?? null);
    $fields = jetengine_cct_coerce_array($row['meta_fields'] ?? null);
    $type_id = (int) ($row['id'] ?? 0);
    return new Factory($args, $fields, $type_id);
}

/**
 * Determine whether a JetEngine CCT field definition has is_key_field set to a truthy value.
 *
 * JetEngine stores boolean-like flags inconsistently across versions: values may be a literal
 * true, the integer 1, the strings "1", "yes", or "true". This helper normalises all of them.
 *
 * @param array<string, mixed> $field
 */
function jetengine_is_key_field(array $field): bool
{
    /** @var mixed $raw */
    $raw = $field['is_key_field'] ?? false;
    return $raw === true || $raw === 1 || $raw === '1' || $raw === 'yes' || $raw === 'true';
}

/**
 * Diff two CCT field arrays by name and return a structured changeset.
 * Used by edit-cct to enforce schema-migration constraints.
 *
 * @param list<array<string, mixed>> $old_fields
 * @param list<array<string, mixed>> $new_fields
 * @return array{
 *     added: list<string>,
 *     removed: list<string>,
 *     kept: list<string>,
 *     type_changed: list<string>,
 *     key_field_changed: list<string>,
 * }
 */
function jetengine_diff_cct_fields(array $old_fields, array $new_fields): array
{
    /**
     * @param list<array<string, mixed>> $fields
     * @return array<string, array<string, mixed>>
     */
    $by_name = static function (array $fields): array {
        $out = [];
        /** @var array<string, mixed> $field */
        foreach ($fields as $field) {
            $name = (string) ($field['name'] ?? '');
            if ($name !== '') {
                $out[$name] = $field;
            }
        }
        return $out;
    };

    /** @var array<string, array<string, mixed>> $old */
    $old = $by_name($old_fields);
    /** @var array<string, array<string, mixed>> $new */
    $new = $by_name($new_fields);

    $added = [];
    $removed = [];
    $kept = [];
    $type_changed = [];
    $key_field_changed = [];

    foreach ($new as $name => $field) {
        if (!array_key_exists($name, $old)) {
            $added[] = $name;
            continue;
        }

        $kept[] = $name;
        $old_type = (string) ($old[$name]['type'] ?? '');
        $new_type = (string) ($field['type'] ?? '');
        if ($old_type !== '' && $new_type !== '' && $old_type !== $new_type) {
            $type_changed[] = $name;
        }

        $old_key = jetengine_is_key_field($old[$name]);
        $new_key = jetengine_is_key_field($field);
        if ($old_key !== $new_key) {
            $key_field_changed[] = $name;
        }
    }

    foreach ($old as $name => $_field) {
        if (array_key_exists($name, $new)) {
            continue;
        }

        $removed[] = $name;
    }

    return [
        'added' => $added,
        'removed' => $removed,
        'kept' => $kept,
        'type_changed' => $type_changed,
        'key_field_changed' => $key_field_changed,
    ];
}

/**
 * Standard runtime guard returned from every JetEngine ability callback's first line.
 */
function jetengine_runtime_guard(): ?WP_Error
{
    if (!class_exists('Jet_Engine') || !function_exists('jet_engine')) {
        return new WP_Error('jetengine_not_active', __('JetEngine is not active.', domain: 'novamira-pro'));
    }

    $version = jet_engine()->get_version();
    if (version_compare($version, NOVAMIRA_PRO_JETENGINE_MIN_VERSION, operator: '<')) {
        return new WP_Error('jetengine_version_unsupported', sprintf(
            /* translators: 1: installed JetEngine version, 2: minimum required version. */
            __('JetEngine %1$s is below the minimum supported version %2$s.', domain: 'novamira-pro'),
            $version,
            NOVAMIRA_PRO_JETENGINE_MIN_VERSION,
        ));
    }

    return null;
}

/**
 * Runtime guard for CCT abilities — extends the base guard with module activeness.
 */
function jetengine_cct_runtime_guard(): ?WP_Error
{
    $base = jetengine_runtime_guard();
    if ($base !== null) {
        return $base;
    }

    if (jetengine_get_cct_module() === null) {
        return new WP_Error('cct_module_not_active', __(
            'JetEngine Custom Content Types module is disabled. Enable it from JetEngine > Modules.',
            domain: 'novamira-pro',
        ));
    }

    return null;
}

/**
 * Accessor for JetEngine's Custom Post Types data layer.
 *
 * JetEngine exposes it at jet_engine()->cpt->data — a Jet_Engine_CPT_Data
 * instance extending the same Jet_Engine_Base_Data that backs the meta-box and
 * CCT layers (so get_items()/get_item_for_edit()/set_request()/create_item()/
 * delete_item() behave the same). Returns null when JetEngine or the post-types
 * component is unavailable, so call sites can guard without fatals.
 */
function jetengine_cpt_data(): ?\Jet_Engine_CPT_Data
{
    if (!class_exists('Jet_Engine') || !function_exists('jet_engine')) {
        return null;
    }

    return jet_engine()->cpt->data ?? null;
}

/**
 * Resolve a post-type slug to its raw JetEngine DB row.
 *
 * The agent-facing identifier is the post-type slug (stable and meaningful),
 * while JetEngine keys rows by an integer `id`; this maps slug -> row so callers
 * can then use the row's `id` with get_item_for_edit() / delete_item().
 *
 * @return array<string, mixed>|null
 */
function jetengine_cpt_find_row_by_slug(string $slug): ?array
{
    $data = jetengine_cpt_data();
    if ($data === null) {
        return null;
    }

    foreach ($data->get_items() as $row) {
        if ((string) ($row['slug'] ?? '') === $slug) {
            return $row;
        }
    }

    return null;
}
