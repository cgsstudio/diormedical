<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards for the Code Snippets integration.
 *
 * The plugin ships in two variants from the same vendor: a free "Code
 * Snippets" (php + html types only) and "Code Snippets Pro" (adds css,
 * js, cond types plus cloud sync). Only one of the two can be active at
 * a time. The detection here distinguishes free vs Pro via the active
 * plugin slug — `code-snippets-pro/code-snippets.php` for Pro, anything
 * else for free.
 *
 * Pro license activation (Freemius) gates UI features and update
 * delivery but does NOT gate the underlying CRUD; saving / loading a
 * Pro-only snippet type works on an unlicensed Pro install, which keeps
 * our ability surface stable.
 */

/**
 * Code Snippets version string, or empty when the plugin is inactive.
 */
function cs_version(): string
{
    return defined('CODE_SNIPPETS_VERSION') ? (string) constant('CODE_SNIPPETS_VERSION') : '';
}

/**
 * Whether the active install is the Pro variant. Detected via the plugin
 * folder slug — `code-snippets-pro/code-snippets.php` when Pro is active,
 * `code-snippets/code-snippets.php` when free is active. Only one of the
 * two can run at a time so this is reliable.
 */
function cs_is_pro(): bool
{
    if (!defined('CODE_SNIPPETS_FILE')) {
        return false;
    }
    $basename = plugin_basename((string) constant('CODE_SNIPPETS_FILE'));
    return str_starts_with($basename, 'code-snippets-pro/');
}

/**
 * Whether css / js snippets actually execute on this install. Needs the
 * Pro build AND the plugin's own licence check: both Pro asset evaluators
 * return from init() before registering any front-end hook unless
 * `licensing->was_licensed()` is true, so on a never-licensed Pro install
 * css / js rows can be stored and edited but never run. Read through the
 * plugin's own accessor; any unknown shape counts as unavailable.
 */
function cs_assets_runtime_available(): bool
{
    if (!cs_is_pro()) {
        return false;
    }
    $plugin = \Code_Snippets\code_snippets();
    if (!property_exists($plugin, 'licensing')) {
        return false;
    }
    $licensing = $plugin->licensing;
    if (!is_object($licensing) || !method_exists($licensing, 'was_licensed')) {
        return false;
    }
    return $licensing->was_licensed() === true;
}

/**
 * The plugin's flat-file storage class for this build, or null when the
 * feature is not shipped. The class moved namespaces along with the
 * model; both names are probed and the method surface we rely on is
 * checked, so an unknown shape counts as "no flat files".
 *
 * @return class-string<\Code_Snippets\Flat_Files\Snippet_Files|\Code_Snippets\Snippet_Files>|null
 */
function cs_snippet_files_class(): ?string
{
    if (cs_has_snippet_files_api(\Code_Snippets\Flat_Files\Snippet_Files::class)) {
        return \Code_Snippets\Flat_Files\Snippet_Files::class;
    }
    if (cs_has_snippet_files_api(\Code_Snippets\Snippet_Files::class)) {
        return \Code_Snippets\Snippet_Files::class;
    }
    return null;
}

/**
 * Whether a candidate flat-file storage class is loaded with the static
 * members the classifier reads.
 */
function cs_has_snippet_files_api(string $class): bool
{
    return (
        class_exists($class)
        && method_exists($class, 'is_active')
        && method_exists($class, 'get_hashed_table_name')
        && method_exists($class, 'get_base_dir')
    );
}

/**
 * Whether the plugin's flat-file snippet storage is enabled (its own
 * predicate: a flag file the settings screen writes). Unknown → false.
 */
function cs_flat_files_active(): bool
{
    $class = cs_snippet_files_class();
    return $class !== null && $class::is_active() === true;
}

/**
 * Path of the flat-file config index the runtime reads for snippets of
 * `$table` (the plugin's site or network snippets table — site and
 * network snippets live under differently hashed directories, so pass
 * the table the consumer belongs to, see cs_snippets_table()) and the
 * given type. Null when the build ships no flat-file API.
 */
function cs_flat_file_index_path(string $table, string $type): ?string
{
    $class = cs_snippet_files_class();
    if ($class === null) {
        return null;
    }
    return $class::get_base_dir($class::get_hashed_table_name($table), $type) . '/index.php';
}

/**
 * The snippets table a snippet belongs to, resolved by the plugin from
 * the snippet's own `network` flag.
 */
function cs_snippets_table(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $snippet): string
{
    return \Code_Snippets\code_snippets()->db->get_table_name($snippet->network);
}

/**
 * Parsed flat-file config index (entries keyed by snippet id), loaded at
 * most once per request per index file. Null when the file is missing,
 * unreadable, fails to load or has an unexpected shape — every failure
 * is swallowed on purpose: this runs after a destructive delete has
 * already succeeded, so building the response must never die.
 *
 * @return array<int, array<array-key, mixed>>|null
 */
function cs_flat_file_index(string $table, string $type): ?array
{
    /** @var array<string, array<int, array<array-key, mixed>>|null> $cache */
    static $cache = [];
    $path = cs_flat_file_index_path($table, $type);
    if ($path === null) {
        return null;
    }
    if (!array_key_exists($path, $cache)) {
        $cache[$path] = cs_read_flat_file_index($path);
    }
    return $cache[$path];
}

/**
 * Parse one flat-file config index. Every filesystem call — the
 * existence / readability checks as much as the load itself — runs under
 * a scoped error handler: an unavailable stream wrapper or a file that
 * vanishes between the checks and the `require` emits a warning before
 * anything is thrown, and that warning is not covered by the catch.
 *
 * @return array<int, array<array-key, mixed>>|null
 */
function cs_read_flat_file_index(string $path): ?array
{
    set_error_handler(static fn(int $errno, string $errstr): bool => true);
    try {
        if (!is_file($path) || !is_readable($path)) {
            return null;
        }
        /** @var mixed $config */
        $config = require $path;
    } catch (\Throwable) {
        return null;
    } finally {
        restore_error_handler();
    }
    if (!is_array($config)) {
        return null;
    }
    $index = [];
    /** @var mixed $entry */
    foreach ($config as $id => $entry) {
        if (!is_int($id) || !is_array($entry)) {
            continue;
        }
        $index[$id] = $entry;
    }
    return $index;
}

/**
 * The condition_id recorded for a snippet in its flat-file config index
 * (the copy the runtime reads when flat files are enabled), or null when
 * flat files are off / the index cannot be read / the snippet has no
 * entry. The plugin only rewrites that entry when the snippet itself is
 * saved, so it can lag behind the database row — e.g. after a condition
 * delete that detached consumers in SQL only. The index file is read
 * directly (the plugin's list reader serves a per-request cache primed at
 * boot, which would hide rows written later in the same request).
 */
function cs_flat_file_condition_id(int $snippet_id, string $table, string $type): ?int
{
    if (!cs_flat_files_active()) {
        return null;
    }
    $index = cs_flat_file_index($table, $type);
    if ($index === null || !isset($index[$snippet_id])) {
        return null;
    }
    /** @var mixed $condition_id */
    $condition_id = $index[$snippet_id]['condition_id'] ?? 0;
    if (is_int($condition_id)) {
        return $condition_id;
    }
    return is_numeric($condition_id) ? (int) $condition_id : 0;
}

/**
 * The full list of snippet types available on this install. Free is
 * limited to `php` and `html`; Pro adds `css`, `js`, and `cond`
 * (conditions). Cloud sync and the bundles UI pages are not real types
 * an agent authors so they are not listed here.
 *
 * @return list<string>
 */
function cs_available_types(): array
{
    $base = ['php', 'html'];
    if (cs_is_pro()) {
        return [...$base, 'css', 'js', 'cond'];
    }
    return $base;
}

/**
 * Per-type list of valid `scope` values, grouped by type so an agent can
 * pick a scope without reading the plugin source.
 *
 * The base map is the 3.6–3.9.x reference and is never reduced: the
 * plugin's own Snippet::get_all_scopes() is NOT a reliable floor for it
 * (3.6.0 lists no `condition` at all, and free builds still list the
 * css / js / condition scopes they cannot run — Pro gating is a separate
 * concern handled by cs_available_types()). Scopes introduced after the
 * floor are opted in additively via cs_optional_scopes(): they join the
 * map only when the live class actually lists them, so an older install
 * never sees a scope its prepare_scope() would silently coerce to the
 * model default.
 *
 * @return array<string, list<string>>
 */
function cs_scopes_by_type(): array
{
    $map = [
        'php' => ['global', 'admin', 'front-end', 'single-use'],
        'html' => ['content', 'head-content', 'footer-content'],
        'css' => ['admin-css', 'site-css'],
        'js' => ['site-head-js', 'site-footer-js'],
        'cond' => ['condition'],
    ];
    foreach (cs_enabled_optional_scopes() as $scope => $spec) {
        $map[$spec['type']] = cs_insert_scope($map[$spec['type']] ?? [], $scope, $spec['before']);
    }
    return $map;
}

/**
 * Scopes added by Code Snippets after the 3.6 floor, keyed to the type
 * they belong to and to the static scope they sit before in the plugin's
 * canonical order (Snippet::get_all_scopes()). Each is offered / accepted
 * only when the live class lists it (see cs_enabled_optional_scopes());
 * when the anchor is missing the scope is appended.
 *
 * @return array<string, array{type: string, before: string}> scope => placement
 */
function cs_optional_scopes(): array
{
    return [
        // 3.10: HTML output right after the opening <body> tag (wp_body_open);
        // canonical order is content, head-content, body-content, footer-content.
        'body-content' => ['type' => 'html', 'before' => 'footer-content'],
    ];
}

/**
 * The subset of cs_optional_scopes() the active plugin version accepts.
 *
 * @return array<string, array{type: string, before: string}> scope => placement
 */
function cs_enabled_optional_scopes(): array
{
    $live = cs_plugin_scopes();
    return array_filter(
        cs_optional_scopes(),
        static fn(string $scope): bool => in_array($scope, $live, strict: true),
        ARRAY_FILTER_USE_KEY,
    );
}
