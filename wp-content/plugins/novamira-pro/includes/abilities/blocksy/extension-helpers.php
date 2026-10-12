<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Companion extensions surface (Sprint 4).
 *
 * Model verified from Blocksy Companion Pro 2.1.52 source:
 *
 *  - Registry: `Blocksy\ExtensionsManager` (framework/includes/
 *    extensions-manager.php) scans `framework/extensions/*` plus every
 *    path added through the `blocksy_extensions_paths` filter —
 *    `Blocksy\Premium` adds `framework/premium/extensions/` only when
 *    the install is pro-entitled. On non-pro installs the premium
 *    catalog appears as placeholder rows with `path === null`.
 *  - State: ONE option, `blocksy_active_extensions` — a flat list of
 *    lowercase extension ids. No per-extension state rows.
 *  - Enable/disable: `activate_extension()` / `deactivate_extension()`
 *    run the extension's static onActivation/onDeactivation hooks,
 *    rewrite the option and fire `blocksy:dynamic-css:refresh-caches`.
 *    Neither performs plan gating (the boot gate in
 *    `boot_activated_extension_for()` does) — the enable ability
 *    replicates that gate BEFORE mutating state.
 *  - Per-extension dashboard settings live in individual
 *    `blocksy_ext_*_settings` options; defaults are resolved by each
 *    extension's own pre-boot `ext_data()` / `get_settings()` accessor,
 *    never duplicated here (see bly_ext_settings_registry()).
 */

/** Maximum bytes emitted for one extension description in list output. */
const BLY_EXT_MAX_DESCRIPTION_BYTES = 200;

/** Maximum bytes emitted for one extension display name. */
const BLY_EXT_MAX_NAME_BYTES = 64;

/** Maximum top-level settings keys echoed by get-extension-settings. */
const BLY_EXT_MAX_SETTINGS_KEYS = 40;

/**
 * Maximum bytes for one emitted settings KEY name (a corrupted stored row
 * can carry arbitrary keys; 40 unbounded names would be an unbounded
 * response). Truncation markers count inside this budget.
 */
const BLY_EXT_MAX_SETTINGS_KEY_BYTES = 64;

/**
 * The wp_options row holding the flat list of active extension ids.
 */
const BLY_EXT_STATE_OPTION = 'blocksy_active_extensions';

// ---------------------------------------------------------------------------
// Gates + manager access
// ---------------------------------------------------------------------------

function bly_companion_inactive_error(): WP_Error
{
    return bly_error(
        'bly_companion_inactive',
        message: 'Blocksy Companion (free or Pro) is not active or is below the minimum supported version (2.1.47). Extensions are a Companion feature — check companion.active and companion.version from blocksy-check-setup.',
    );
}

/**
 * The Companion's own extensions manager, or a WP_Error when the theme or
 * the Companion is unavailable. All extension abilities go through this
 * object so activation side effects (onActivation/onDeactivation hooks,
 * the dynamic-CSS refresh) run exactly the Companion's code path.
 */
function bly_extensions_manager(): \Blocksy\ExtensionsManager|WP_Error
{
    if (!blocksy_theme_available()) {
        return bly_theme_inactive_error();
    }
    if (!blocksy_companion_available() || !class_exists('Blocksy\\Plugin')) {
        return bly_companion_inactive_error();
    }
    $manager = \Blocksy\Plugin::instance()->extensions;
    if (!$manager instanceof \Blocksy\ExtensionsManager) {
        return bly_companion_inactive_error();
    }
    return $manager;
}

// ---------------------------------------------------------------------------
// Registry rows (id, name, description, pro, availability, enabled)
// ---------------------------------------------------------------------------

/**
 * Normalized registry rows keyed by extension id, in the manager's
 * registration order (free directory first, then premium). `installed`
 * is false for the placeholder rows the Companion registers for the
 * premium catalog on non-pro installs (path === null) — those can never
 * be activated on the current install.
 *
 * Memoized per manager instance (a re-instantiated manager — e.g. a test
 * registering a fixture extension via `blocksy_extensions_paths` — gets a
 * fresh read), and the `pro` flag comes from the billing map, not the
 * entry's config: a REPEATED `get_extensions(require_config)` call in one
 * request clobbers the placeholder rows' config (2.1.52 quirk —
 * `read_config_for(null)` re-runs on them and resets name/description/pro
 * to bare defaults), so the first read per manager is cached and the pro
 * flag is sourced from data that survives the clobbering.
 *
 * @return array<string, array{
 *     id: string,
 *     name: string,
 *     description: string,
 *     pro: bool,
 *     installed: bool,
 *     path: null|string,
 * }>
 */
function bly_ext_rows(\Blocksy\ExtensionsManager $manager): array
{
    static $memo = [];
    /** @var array<int, array<string, array{id: string, name: string, description: string, pro: bool, installed: bool, path: null|string}>> $memo */
    $manager_key = spl_object_id($manager);
    if (isset($memo[$manager_key])) {
        return $memo[$manager_key];
    }

    $billing = bly_ext_billing_map();
    $rows = [];
    foreach ($manager->get_extensions(['require_config' => true]) as $id => $entry) {
        if ($id === '') {
            continue;
        }
        $config = isset($entry['config']) && is_array($entry['config']) ? $entry['config'] : [];
        $name = isset($config['name']) && is_string($config['name']) ? $config['name'] : $id;
        $description = isset($config['description']) && is_string($config['description']) ? $config['description'] : '';
        $path = isset($entry['path']) && is_string($entry['path']) && $entry['path'] !== '' ? $entry['path'] : null;
        $rows[$id] = [
            'id' => $id,
            'name' => bly_ext_clean_text($name, BLY_EXT_MAX_NAME_BYTES),
            'description' => bly_ext_clean_text($description, BLY_EXT_MAX_DESCRIPTION_BYTES),
            'pro' => isset($billing[$id]) ? $billing[$id]['pro'] : bly_ext_flag_truthy($config['pro'] ?? false),
            'installed' => $path !== null,
            'path' => $path,
        ];
    }

    $memo[$manager_key] = $rows;
    return $rows;
}

/**
 * Truthiness of a mixed config flag without an `empty()`/bool-cast on
 * mixed — the same value list the builder helpers use for JS-parity
 * truthiness checks.
 */
function bly_ext_flag_truthy(mixed $flag): bool
{
    return !in_array($flag, [false, 0, 0.0, '', null], strict: true);
}

/**
 * Tag-stripped, whitespace-collapsed, byte-capped display text. Truncation
 * is marked with an ellipsis so capped text is never mistaken for the full
 * string, and the ellipsis counts INSIDE the budget — the result never
 * exceeds `$budget` bytes total.
 */
function bly_ext_clean_text(string $text, int $budget): string
{
    $clean = trim((string) preg_replace(pattern: '/\s+/u', replacement: ' ', subject: wp_strip_all_tags($text)));
    if (strlen($clean) <= $budget) {
        return $clean;
    }
    $ellipsis = '…';
    return bly_truncate_utf8_bytes($clean, max(0, $budget - strlen($ellipsis))) . $ellipsis;
}

// ---------------------------------------------------------------------------
// Plan gating (mirrors boot_activated_extension_for())
// ---------------------------------------------------------------------------

/**
 * Billing rules for the premium catalog, keyed by extension id:
 * {pro: bool, plans: list<string>|null}. Sourced from the Companion's own
 * `blocksy_companion_exts_get_preliminary_config(null, only_billing_data)`
 * — the exact map the manager's boot gate consults. Extensions absent
 * from the map (the free four) are not pro. Memoized per request.
 *
 * @return array<string, array{pro: bool, plans: list<string>|null}>
 */
function bly_ext_billing_map(): array
{
    static $map = null;
    /** @var array<string, array{pro: bool, plans: list<string>|null}>|null $map */
    if (is_array($map)) {
        return $map;
    }

    $map = [];
    if (!function_exists('blocksy_companion_exts_get_preliminary_config')) {
        return $map;
    }
    /** @var mixed $row */
    foreach (blocksy_companion_exts_get_preliminary_config(null, ['only_billing_data' => true]) as $id => $row) {
        if (!is_string($id) || !is_array($row)) {
            continue;
        }
        $plans = null;
        if (isset($row['plans']) && is_array($row['plans'])) {
            $plans = [];
            /** @var mixed $plan */
            foreach ($row['plans'] as $plan) {
                if (!is_string($plan)) {
                    continue;
                }
                $plans[] = $plan;
            }
        }
        $map[$id] = [
            'pro' => bly_ext_flag_truthy($row['pro'] ?? false),
            'plans' => $plans,
        ];
    }
    return $map;
}

/**
 * Replicates the Companion's extension boot gate for one id
 * (`boot_activated_extension_for()`: pro extensions require a non-free
 * plan; a `plans` allow-list additionally restricts by plan slug). Null
 * when the current plan may run the extension. `activate_extension()`
 * itself performs NO such check, so the enable ability MUST call this
 * first — otherwise the option would carry an id that never boots.
 *
 * `$plan` defaults to the live plan; passing one explicitly evaluates the
 * gate for that plan (the Capabilities singleton memoizes the live plan
 * per request, so tests exercise the free/allow-list branches this way).
 */
function bly_ext_plan_gate(string $id, ?string $plan = null): ?WP_Error
{
    $billing = bly_ext_billing_map()[$id] ?? null;
    if ($billing === null || !$billing['pro']) {
        return null;
    }

    $plan ??= bly_companion_plan();
    if ($plan === '' || $plan === 'free') {
        return bly_error('bly_pro_required', sprintf(
            'Extension "%s" is a Companion Pro extension and this install runs the free plan (companion.plan from blocksy-check-setup). It would be stored but never boot, so the call is refused.',
            $id,
        ));
    }
    if ($billing['plans'] !== null && !in_array($plan, $billing['plans'], strict: true)) {
        return bly_error('bly_pro_required', sprintf(
            'Extension "%s" requires one of the plans [%s]; this site has plan "%s". It would be stored but never boot, so the call is refused.',
            $id,
            implode(', ', $billing['plans']),
            $plan,
        ));
    }
    return null;
}

// ---------------------------------------------------------------------------
// Verified-persist state reads
// ---------------------------------------------------------------------------

/**
 * The active-extension id list read RAW from the options table ($wpdb,
 * bypassing every option cache layer) — the verified-persist read used
 * after enable/disable writes. Ids are lowercased for comparison; the
 * Companion lowercases on store.
 *
 * @return list<string>
 */
function bly_ext_active_raw(): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    // @mago-expect analysis:possibly-invalid-argument
    $sql = $wpdb->prepare("SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", BLY_EXT_STATE_OPTION);
    /** @var mixed $raw */
    $raw = is_string($sql) ? $wpdb->get_var($sql) : null;
    if (!is_string($raw)) {
        return [];
    }
    /** @var mixed $list */
    $list = maybe_unserialize($raw);
    if (!is_array($list)) {
        return [];
    }
    $out = [];
    /** @var mixed $id */
    foreach ($list as $id) {
        if (!is_string($id) || $id === '') {
            continue;
        }
        $out[] = strtolower($id);
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Rewrite-rule side effects
// ---------------------------------------------------------------------------

/**
 * Extensions whose enable/disable changes the rewrite surface, verified
 * from 2.1.52 source: product-reviews registers the `blc-product-review`
 * CPT + taxonomy with rewrites (extension.php:566); woocommerce-extra
 * features register `ct_product_tab` / `ct_thank_you_page` / size-guide
 * CPTs and the brand taxonomy; post-types-extra's filtering/taxonomy
 * features add query/rewrite state. The Companion's own settings-persist
 * paths for exactly these three call `$wp_rewrite->flush_rules()`
 * (pre-boot.php in each), and the dashboard exposes a companion
 * `blocksy_flush_permalinks` AJAX action.
 *
 * @return list<string>
 */
function bly_ext_rewrite_sensitive(): array
{
    return ['product-reviews', 'woocommerce-extra', 'post-types-extra'];
}

/**
 * DEFERRED rewrite flush: drop the cached `rewrite_rules` option so the
 * rules regenerate on the NEXT request. An immediate
 * flush_rewrite_rules() here would regenerate rules WITHOUT the
 * extension's post types — in this request `init` already fired before
 * the activation, so the newly booted extension has not registered its
 * CPTs yet (and after a disable the stale CPT rules are still loaded).
 * On the next request the extension boots (or no longer boots) on init
 * before WP lazily rebuilds the rules, producing the correct set.
 */
function bly_ext_defer_rewrite_flush(): void
{
    delete_option('rewrite_rules');
}

// ---------------------------------------------------------------------------
// Locked toggle engine (enable/disable share it)
// ---------------------------------------------------------------------------

/**
 * Success payload shared by enable/disable, applying the deferred rewrite
 * flush (only when state actually changed) and the dependency warnings.
 *
 * @param array{enabled: bool, changed: bool} $state
 * @return array{id: string, name: string, enabled: bool, changed: bool, rewrite_rules_flushed: bool, warnings: list<string>}
 */
function bly_ext_toggle_result(string $id, string $name, array $state): array
{
    $flushed = false;
    if ($state['changed'] && in_array($id, bly_ext_rewrite_sensitive(), strict: true)) {
        bly_ext_defer_rewrite_flush();
        $flushed = true;
    }

    $warnings = [];
    if ($state['enabled'] && $id === 'woocommerce-extra' && !class_exists('WooCommerce')) {
        $warnings[] = 'WooCommerce is not active: woocommerce-extra stores its enabled state but self-guards and stays inert until WooCommerce is activated.';
    }

    return [
        'id' => $id,
        'name' => $name,
        'enabled' => $state['enabled'],
        'changed' => $state['changed'],
        'rewrite_rules_flushed' => $flushed,
        'warnings' => $warnings,
    ];
}

/**
 * The Companion's lifecycle-hook class name for an extension id
 * (`ExtensionsManager::get_class_name_for()`: `BlocksyExtension` + the
 * id's dash parts ucfirst'd, e.g. product-reviews →
 * BlocksyExtensionProductReviews).
 */
function bly_ext_class_name(string $id): string
{
    $parts = array_map(static fn(string $part): string => ucfirst($part), explode('-', $id));
    return 'BlocksyExtension' . implode('', $parts);
}

/**
 * Enable or disable one extension under the shared advisory row lock,
 * REPLICATING the Companion's toggle chain under this engine's control
 * instead of delegating to it.
 *
 * Why replicate: `ExtensionsManager::activate_extension()` /
 * `deactivate_extension()` (2.1.55, framework/includes/
 * extensions-manager.php:185-227 / :229-264) read their base state
 * unlocked, run arbitrary lifecycle code, call update_option() with the
 * return value ignored, and then fire an UNCONDITIONAL
 * `blocksy:dynamic-css:refresh-caches` — even when the write failed.
 * Delegating surrenders that ordering. This engine mirrors the chain
 * faithfully — same hooks, same construct timing, same refresh action —
 * minus those bugs, using the S2 precomputed-payload pattern:
 *
 *   in-lock cache-busted fresh read → no-op / placeholder gates →
 *   lifecycle phase (Throwable-contained; the arbitrary-code window) →
 *   payload + autoload precomputed (the LAST user code before the SQL)
 *   → ownership re-check → raw hook-free persist (row-absent INSERT
 *   gets core's default autoload; an existing row keeps its stored
 *   autoload) → post-write ownership re-check (the un-suppressible wpdb
 *   'query'-filter / auto-reconnect window; when tripped, the READ-ONLY
 *   lock-loss recovery reports honestly — no rollback is provably safe
 *   there, see bly_ext_recover_after_lock_loss) → byte-exact raw verify
 *   (its only rollback runs under re-verified continuous ownership) →
 *   ONLY THEN the dynamic-CSS refresh (contained) → release.
 *
 * Replication map — verified against 2.1.52 and 2.1.55; diff these lines on every
 * Companion upgrade (plugin-upgrade-audit):
 *  - enable  = activate_extension() body, extensions-manager.php:196-213:
 *    require_once <path>/extension.php → static onActivation when
 *    declared → boot `new BlocksyExtension<CamelId>` and attach it as
 *    the registry entry's `__object` (bly_ext_attach_object — the
 *    Companion assigns its private $extensions map directly) → persist
 *    `array_unique($stored ∪ [strtolower(id)])`.
 *  - disable = deactivate_extension() body, :229-243: static
 *    onDeactivation when declared and the id is stored → persist
 *    `array_diff($stored, [$id])` (keys preserved — exactly the shape
 *    the Companion serializes); the booted object stays loaded for the
 *    rest of the request, as upstream.
 *  - both: `do_action('blocksy:dynamic-css:refresh-caches')`
 *    (:226 / :263) — here gated on the verified persist.
 *  - INTENTIONAL drift: the persist is raw and hook-free, so the option
 *    filters/actions the Companion's update_option() call would fire
 *    (pre_update_option_blocksy_active_extensions, update_option,
 *    updated_option, the option-cache actions) do NOT fire here. No
 *    Companion or theme listener exists on those hooks in 2.1.52; a
 *    third-party consumer listening on them would not observe these
 *    writes — re-audit this drift on Companion upgrades.
 *
 * Failure containment (third-party lifecycle hooks cannot be rolled
 * back; the $phase record — owned by this engine, not inferred — feeds
 * bly_ext_toggle_failure()'s honest messaging):
 *  - persisted+verified, refresh threw → bly_refresh_failed (new state
 *    kept and said so)
 *  - not persisted (lifecycle threw, lock lost, write blocked) → the
 *    opposite hook is invoked as contained compensation;
 *    bly_write_failed / bly_lock_lost when clean, bly_partial_side_effects
 *    when hook effects may remain; a rollback that cannot re-establish
 *    the prior bytes is the distinct bly_rollback_failed.
 *
 * @param array{id: string, name: string, description: string, pro: bool, installed: bool, path: null|string} $row
 * @param 'enable'|'disable' $direction
 * @return array{id: string, name: string, enabled: bool, changed: bool, rewrite_rules_flushed: bool, warnings: list<string>}|WP_Error
 */
function bly_ext_locked_toggle(\Blocksy\ExtensionsManager $manager, array $row, string $direction): array|WP_Error
{
    return bly_with_option_row_lock(BLY_EXT_STATE_OPTION, static fn(callable $lock_held): array|WP_Error => bly_ext_toggle_in_lock(
        $manager,
        $row,
        $direction,
        $lock_held,
    ));
}

/**
 * The critical section of bly_ext_locked_toggle(): everything between
 * lock acquisition and release. Split out to keep each stage small; the
 * stage order is the contract documented on bly_ext_locked_toggle().
 *
 * @param array{id: string, name: string, description: string, pro: bool, installed: bool, path: null|string} $row
 * @param 'enable'|'disable' $direction
 * @return array{id: string, name: string, enabled: bool, changed: bool, rewrite_rules_flushed: bool, warnings: list<string>}|WP_Error
 */
function bly_ext_toggle_in_lock(
    \Blocksy\ExtensionsManager $manager,
    array $row,
    string $direction,
    callable $lock_held,
): array|WP_Error {
    $id = $row['id'];
    $want_enabled = $direction === 'enable';

    // Fresh state: a request that waited on the lock must not act on its
    // bootstrap-primed option caches. The raw bytes double as the
    // byte-exact rollback target.
    bly_bust_mods_cache(BLY_EXT_STATE_OPTION);
    $prior_bytes = bly_raw_mods_row(BLY_EXT_STATE_OPTION);
    // Ownership re-verified IMMEDIATELY after the read, before ANY
    // decision is made on it (the read itself can reconnect). Everything
    // between here and the lifecycle phase is pure in-memory code, so
    // this single check covers the whole span up to the lifecycle entry.
    if (!$lock_held()) {
        return bly_error('bly_lock_lost', sprintf(
            'The write lock for %s was lost while reading the row, before the %s of "%s" started (database reconnect). Nothing ran and nothing was changed — retry.',
            BLY_EXT_STATE_OPTION,
            $direction,
            $id,
        ));
    }
    /** @var mixed $prior_list */
    $prior_list = is_string($prior_bytes) ? maybe_unserialize($prior_bytes) : false;
    // The Companion's get_activated_extensions() normalization: a missing
    // or malformed row reads as "none active".
    $stored = is_array($prior_list) ? $prior_list : [];
    $active = bly_ext_lowercase_ids($stored);

    if (in_array($id, $active, strict: true) === $want_enabled) {
        return bly_ext_toggle_result($id, $row['name'], ['enabled' => $want_enabled, 'changed' => false]);
    }

    // Placeholder rows (path=null) have no files to run — refuse here
    // instead of mis-reporting a persist failure. Matters for disable: a
    // premium id can be left in the state row after a plan downgrade or a
    // switch to the free Companion build.
    $installed_gate = bly_ext_installed_gate($row);
    if ($installed_gate !== null) {
        return $installed_gate;
    }
    if ($row['path'] === null) {
        return bly_ext_installed_gate_missing($row);
    }

    // Lifecycle phase: the arbitrary-code window, contained. $phase
    // records exactly what ran — the honest-messaging input.
    $phase = ['file_executed' => false, 'hook_ran' => false, 'construction_attempted' => false, 'constructed' => false];
    $threw = bly_ext_run_lifecycle($manager, $row, $direction, $active, $phase);

    return bly_ext_commit_state(
        [
            'id' => $id,
            'name' => $row['name'],
            'direction' => $direction,
            'phase' => $phase,
            'threw' => $threw,
        ],
        $stored,
        $prior_bytes,
        $lock_held,
    );
}

/**
 * Lowercased string entries of a stored id list — the membership view
 * (the Companion lowercases ids on store; corrupted non-string entries
 * are ignored for membership but preserved by the payload compute).
 *
 * @param array<array-key, mixed> $stored
 * @return list<string>
 */
function bly_ext_lowercase_ids(array $stored): array
{
    $active = [];
    /** @var mixed $entry */
    foreach ($stored as $entry) {
        if (!is_string($entry) || $entry === '') {
            continue;
        }
        $active[] = strtolower($entry);
    }
    return $active;
}

/**
 * Contained lifecycle dispatch: runs the direction's replicated hooks
 * and returns the Throwable they raised, or null. Exception text stays
 * server-side (REST error payloads reach agents/clients and can leak
 * paths/internals).
 *
 * @param array{id: string, name: string, description: string, pro: bool, installed: bool, path: null|string} $row
 * @param 'enable'|'disable' $direction
 * @param list<string> $active
 * @param array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool} $phase
 * @param-out array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool} $phase
 */
function bly_ext_run_lifecycle(
    \Blocksy\ExtensionsManager $manager,
    array $row,
    string $direction,
    array $active,
    array &$phase,
): ?\Throwable {
    try {
        if ($direction === 'enable' && is_string($row['path'])) {
            bly_ext_run_enable_lifecycle($manager, $row['id'], $row['path'], $phase);
        }
        if ($direction === 'disable') {
            bly_ext_run_disable_lifecycle($row['id'], $active, $phase);
        }
        return null;
    } catch (\Throwable $e) {
        error_log(sprintf(
            'novamira-pro blocksy: %s lifecycle of extension %s threw: %s',
            $direction,
            $row['id'],
            $e->getMessage(),
        ));
        return $e;
    }
}

/**
 * Payload precompute — the mutation the Companion would persist,
 * byte-faithfully (activate: append strtolower(id) + array_unique,
 * extensions-manager.php:213-215; deactivate: array_diff with keys
 * preserved, :240-243). The serialize() and the core autoload filter
 * (row-absent INSERT only) are the LAST user code before the SQL.
 *
 * @param array<array-key, mixed> $stored
 * @param 'enable'|'disable' $direction
 * @return array{0: string, 1: string} [payload bytes, autoload for a row-absent INSERT]
 */
function bly_ext_state_payload(array $stored, string $id, string $direction): array
{
    $next = $stored;
    if ($direction === 'enable') {
        $next[] = strtolower($id);
        $next = array_unique($next);
    }
    if ($direction !== 'enable') {
        $next = array_diff($stored, [$id]);
    }
    $payload = serialize($next);
    return [$payload, bly_mods_row_autoload(BLY_EXT_STATE_OPTION, $next, $payload)];
}

/**
 * Persist + verify + refresh — the post-lifecycle tail of the chain.
 * Nothing but checks and raw SQL runs between the ownership re-check and
 * the write; the refresh fires ONLY after the byte-verified persist
 * (extensions-manager.php:226/:263 fire it unconditionally — gating it
 * is the point of the replication).
 *
 * @param array{id: string, name: string, direction: 'enable'|'disable', phase: array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool}, threw: \Throwable|null} $ctx
 * @param array<array-key, mixed> $stored
 * @param string|false $prior_bytes
 * @return array{id: string, name: string, enabled: bool, changed: bool, rewrite_rules_flushed: bool, warnings: list<string>}|WP_Error
 */
function bly_ext_commit_state(array $ctx, array $stored, string|false $prior_bytes, callable $lock_held): array|WP_Error
{
    [$payload, $autoload] = bly_ext_state_payload($stored, $ctx['id'], $ctx['direction']);

    if ($ctx['threw'] !== null) {
        return bly_ext_toggle_failure($ctx, reason: sprintf(
            'its %s lifecycle phase threw (details in the server error log), so nothing was persisted',
            $ctx['direction'],
        ));
    }
    if (!$lock_held()) {
        // Reconnect DURING the lifecycle phase: the advisory lock died
        // with the old connection — persisting now would run unlocked.
        return bly_ext_toggle_failure(
            $ctx,
            reason: sprintf(
                'the write lock for %s was lost during the lifecycle phase (database reconnect) and persisting without it is refused, so nothing was persisted',
                BLY_EXT_STATE_OPTION,
            ),
            base_code: 'bly_lock_lost',
        );
    }

    bly_persist_mods_row_raw(BLY_EXT_STATE_OPTION, $payload, $autoload);

    if (!$lock_held()) {
        // The one window that cannot be closed: wpdb's global 'query'
        // filter (and its auto-reconnect path) runs INSIDE the write.
        // The lock died mid-write — READ-ONLY recovery, never a rollback
        // (see bly_ext_recover_after_lock_loss for the ABA rationale).
        return bly_ext_recover_after_lock_loss($ctx, reason: sprintf(
            'the database connection changed WHILE persisting the %s row (a query filter or auto-reconnect), so the write may have run without the advisory lock',
            BLY_EXT_STATE_OPTION,
        ));
    }

    $verified = bly_raw_mods_row(BLY_EXT_STATE_OPTION) === $payload;
    if (!$lock_held()) {
        // The verification read itself can reconnect — and its result is
        // then untrustworthy EITHER WAY: matching bytes prove nothing
        // once ownership is gone (a competitor may already be acting on
        // the row), so neither success + refresh nor a rollback may
        // proceed. Read-only recovery, unconditionally.
        return bly_ext_recover_after_lock_loss($ctx, reason: sprintf(
            'the database connection changed during the post-write verification of the %s row, so the write may have run without the advisory lock',
            BLY_EXT_STATE_OPTION,
        ));
    }
    if (!$verified) {
        // $lock_held was just re-verified true: the write provably ran
        // under continuous ownership and simply did not stick — the ONLY
        // window where a rollback is provably safe (and the rollback
        // itself re-verifies ownership immediately before AND after its
        // restore write).
        return bly_ext_persist_rollback(
            $ctx,
            $prior_bytes,
            reason: sprintf(
                'the raw re-read inside the lock does not hold the intended bytes (a database failure or a query filter blocked the write), so the %s row was rolled back',
                BLY_EXT_STATE_OPTION,
            ),
            lock_held: $lock_held,
        );
    }

    return bly_ext_fire_refresh($ctx);
}

/**
 * The Companion's refresh action after a byte-verified persist,
 * Throwable-contained: a throwing listener yields bly_refresh_failed
 * with the new state KEPT (and said so).
 *
 * @param array{id: string, name: string, direction: 'enable'|'disable', phase: array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool}, threw: \Throwable|null} $ctx
 * @return array{id: string, name: string, enabled: bool, changed: bool, rewrite_rules_flushed: bool, warnings: list<string>}|WP_Error
 */
function bly_ext_fire_refresh(array $ctx): array|WP_Error
{
    $want_enabled = $ctx['direction'] === 'enable';
    try {
        do_action('blocksy:dynamic-css:refresh-caches');
    } catch (\Throwable $refresh_e) {
        error_log(sprintf(
            'novamira-pro blocksy: dynamic-CSS refresh after %s of %s threw: %s',
            $ctx['direction'],
            $ctx['id'],
            $refresh_e->getMessage(),
        ));
        return bly_error('bly_refresh_failed', sprintf(
            'Extension "%s" WAS %s and the state row persisted (byte-verified inside the lock), but the dynamic-CSS refresh (or one of its listeners) threw — details in the server error log. The new state is kept; front-end CSS may be stale until the next customizer save or cache refresh.',
            $ctx['id'],
            $want_enabled ? 'enabled' : 'disabled',
        ));
    }

    return bly_ext_toggle_result($ctx['id'], $ctx['name'], ['enabled' => $want_enabled, 'changed' => true]);
}

/**
 * Defensive twin of bly_ext_installed_gate() for the engine's typed path
 * check — reachable only if a registry row ever reported installed=true
 * with a null path.
 *
 * @param array{id: string, name: string, description: string, pro: bool, installed: bool, path: null|string} $row
 */
function bly_ext_installed_gate_missing(array $row): WP_Error
{
    return bly_error('bly_not_found', sprintf(
        'Extension "%s" is registered without a file path — its files are missing from this install.',
        $row['id'],
    ));
}

/**
 * The enable lifecycle, mirroring activate_extension()'s changed path
 * (extensions-manager.php:196-211): require the extension file, run the
 * static onActivation hook when declared, boot the extension class and
 * attach it to the manager's registry. `hook_ran` is flipped BEFORE the
 * hook is invoked — a throwing hook still (partially) ran, and the
 * failure messaging must say so.
 *
 * @param array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool} $phase
 * @param-out array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool} $phase
 * @throws \Throwable Third-party extension code runs here.
 */
function bly_ext_run_enable_lifecycle(
    \Blocksy\ExtensionsManager $manager,
    string $id,
    string $path,
    array &$phase,
): void {
    // Every flag is recorded BEFORE the code it describes runs: a throw
    // mid-step must still be classified as "this step was attempted and
    // may have made changes", never as "nothing ran".
    $phase['file_executed'] = true;
    require_once $path . '/extension.php';
    $class = bly_ext_class_name($id);
    if (method_exists($class, 'onActivation')) {
        $phase['hook_ran'] = true;
        call_user_func($class . '::onActivation');
    }
    if (!class_exists($class)) {
        throw new \RuntimeException(sprintf('%s/extension.php did not define %s', $path, $class));
    }
    $phase['construction_attempted'] = true;
    // The concrete class is only known at runtime — by contract it is
    // BlocksyExtension<CamelId>, just verified to exist.
    // @mago-expect analysis:unknown-class-instantiation
    $object = new $class();
    $phase['constructed'] = true;
    bly_ext_attach_object($manager, $id, $object);
}

/**
 * The disable lifecycle, mirroring deactivate_extension()'s hook gate
 * (extensions-manager.php:238-247): the static onDeactivation hook runs
 * only when the id is currently stored and the hook is declared. The
 * method_exists probe on the class string matches the Companion's own
 * probe. The booted object is NOT torn down — upstream keeps it loaded
 * for the rest of the request too.
 *
 * @param list<string> $active
 * @param array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool} $phase
 * @param-out array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool} $phase
 * @throws \Throwable Third-party extension code runs here.
 */
function bly_ext_run_disable_lifecycle(string $id, array $active, array &$phase): void
{
    $class = bly_ext_class_name($id);
    if (in_array($id, $active, strict: true) && method_exists($class, 'onDeactivation')) {
        $phase['hook_ran'] = true;
        call_user_func($class . '::onDeactivation');
    }
}

/**
 * In-request parity with activate_extension():210 — the Companion stores
 * the booted object into its private $extensions registry so
 * `$manager->get($id)` serves it for the rest of the request. No public
 * setter exists, so the attach goes through reflection; a failure is
 * logged and non-fatal (the extension object itself is booted and its
 * constructor-registered hooks are live either way).
 */
function bly_ext_attach_object(\Blocksy\ExtensionsManager $manager, string $id, object $object): void
{
    try {
        $property = new \ReflectionProperty(\Blocksy\ExtensionsManager::class, 'extensions');
        // A no-op on PHP >= 8.1 but REQUIRED on 8.0, this codebase's floor.
        // @mago-expect analysis:deprecated-method
        $property->setAccessible(true);
        /** @var mixed $registry */
        $registry = $property->getValue($manager);
        if (is_array($registry) && isset($registry[$id]) && is_array($registry[$id])) {
            $registry[$id]['__object'] = $object;
            $property->setValue($manager, $registry);
        }
    } catch (\Throwable $e) {
        error_log(sprintf(
            'novamira-pro blocksy: could not attach the booted %s object to the extensions registry: %s',
            $id,
            $e->getMessage(),
        ));
    }
}

/**
 * READ-ONLY recovery for a POST-WRITE lock loss. In this window NO
 * rollback can be proven correct, so this function NEVER writes:
 *
 *  - Byte-equality cannot prove exclusive authorship (ABA): a competing
 *    request B can acquire the freed lock, observe our just-written
 *    payload as ITS OWN idempotent no-op success (the toggles are
 *    idempotent by contract) and release — the bytes still "look ours",
 *    but rolling them back would silently undo the success B reported.
 *  - Any rollback write issued here can itself run unlocked after yet
 *    another reconnect — the same hole one level down.
 *  - The captured prior bytes cannot contain a competitor's ids either,
 *    so a rollback could only ever trade one clobber for another.
 *
 * What it does instead: re-acquire the lock (or not) PURELY to take an
 * accurate cache-busted raw read for reporting — the read is trusted
 * only when $lock_held confirms ownership right after it, and the
 * message degrades honestly when it cannot be verified. The caller gets
 * bly_lock_lost stating the write may have persisted and may have raced
 * a concurrent request, what the row holds now (bounded), and that
 * re-running blocksy-list-extensions + re-issuing the toggle reconciles
 * safely (both toggles are idempotent).
 *
 * @param array{id: string, name: string, direction: 'enable'|'disable', phase: array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool}, threw: \Throwable|null} $ctx
 */
function bly_ext_recover_after_lock_loss(array $ctx, string $reason): WP_Error
{
    // Read-only by construction: the closure takes a fresh raw read and
    // returns a summary string — no code path in here writes anything.
    /** @var mixed $summary */
    $summary = bly_with_option_row_lock(BLY_EXT_STATE_OPTION, static function (callable $lock_held): string {
        bly_bust_mods_cache(BLY_EXT_STATE_OPTION);
        $current = bly_raw_mods_row(BLY_EXT_STATE_OPTION);
        if (!$lock_held()) {
            // The read itself is unverifiable (lost the lock again) —
            // degrade rather than report possibly-stale contents.
            return '';
        }
        return bly_ext_state_summary($current);
    });
    $verified = is_string($summary) && $summary !== '';

    return bly_error('bly_lock_lost', sprintf(
        'Could not reliably %s extension "%s": %s. The write may have persisted and may have raced a concurrent request — nothing was rolled back (no rollback can be proven safe in this window). %s Re-run blocksy-list-extensions to see the effective state and re-issue this %s if still needed; both toggles are idempotent, so reconciling is safe.',
        $ctx['direction'],
        $ctx['id'],
        $reason,
        $verified
            ? sprintf('The %s row currently holds %s.', BLY_EXT_STATE_OPTION, $summary)
            : sprintf(
                'The current contents of the %s row could not be verifiably read (the lock could not be re-acquired, or was lost again during the read).',
                BLY_EXT_STATE_OPTION,
            ),
        $ctx['direction'],
    ));
}

/**
 * Bounded, human-readable summary of the state row's raw bytes for the
 * lock-loss report: at most ten ids named, each id byte-capped (a
 * corrupted giant id must not make an ERROR message unbounded), with
 * explicit omission markers.
 *
 * @param string|false $bytes
 */
function bly_ext_state_summary(string|false $bytes): string
{
    if ($bytes === false) {
        return 'no row (no extensions active)';
    }
    /** @var mixed $list */
    $list = maybe_unserialize($bytes);
    if (!is_array($list)) {
        return 'a malformed value';
    }
    $ids = bly_ext_lowercase_ids($list);
    if ($ids === []) {
        return 'an empty list';
    }
    $shown = [];
    foreach (array_slice($ids, offset: 0, length: 10) as $one) {
        $shown[] = bly_ext_clean_text($one, BLY_EXT_MAX_NAME_BYTES);
    }
    $suffix = count($ids) > 10 ? sprintf(' (+%d more)', count($ids) - 10) : '';
    return '[' . implode(', ', $shown) . ']' . $suffix;
}

/**
 * Roll the state row back to its captured prior bytes — raw and
 * hook-free, existence-aware (captured absence deletes the row; the
 * value-only ON DUPLICATE update preserves an existing row's stored
 * autoload) — verify the restore byte-exactly, then hand off to the
 * honest failure messaging. Reachable ONLY from the verify-fail path
 * where ownership was held continuously, and ownership is re-verified
 * HERE, both immediately BEFORE the restore write and immediately AFTER
 * it (the restore query can itself reconnect and re-run unlocked — the
 * same window as the main persist) — on either loss this degrades to
 * the read-only lock-loss recovery instead of writing (anything)
 * further. An unverifiable restore under held ownership is the one
 * state that must not be papered over: distinct bly_rollback_failed,
 * and NO compensation (the row may actually hold the new state —
 * compensating against an indeterminate row could desync it further).
 *
 * @param array{id: string, name: string, direction: 'enable'|'disable', phase: array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool}, threw: \Throwable|null} $ctx
 * @param string|false $prior_bytes
 */
function bly_ext_persist_rollback(array $ctx, string|false $prior_bytes, string $reason, callable $lock_held): WP_Error
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    if (!$lock_held()) {
        return bly_ext_recover_after_lock_loss(
            $ctx,
            $reason . '; the lock was then lost before the in-lock rollback could run',
        );
    }

    if ($prior_bytes === false) {
        $wpdb->delete($wpdb->options, ['option_name' => BLY_EXT_STATE_OPTION]);
        bly_bust_mods_cache(BLY_EXT_STATE_OPTION);
    }
    if ($prior_bytes !== false) {
        // The autoload argument only applies to a row-absent INSERT; a
        // string $prior_bytes means the row pre-existed, so it is unused.
        bly_persist_mods_row_raw(BLY_EXT_STATE_OPTION, $prior_bytes, autoload: 'yes');
    }

    if (!$lock_held()) {
        // Post-write mirror of the main persist's check: the restore
        // query can itself reconnect and re-run on a fresh,
        // non-lock-owning connection — no further writes.
        return bly_ext_recover_after_lock_loss(
            $ctx,
            $reason
            . '; the connection then changed WHILE restoring the prior bytes, so the restore may have run without the advisory lock',
        );
    }

    $restored = bly_raw_mods_row(BLY_EXT_STATE_OPTION) === $prior_bytes;
    if (!$lock_held()) {
        // The restore-verification read can itself reconnect — once
        // ownership is gone its result is untrustworthy either way, so
        // neither bly_write_failed nor bly_rollback_failed may be
        // claimed. Read-only recovery, unconditionally.
        return bly_ext_recover_after_lock_loss(
            $ctx,
            $reason
            . '; the connection then changed while verifying the restored bytes, so the rollback outcome could not be confirmed under the lock',
        );
    }
    if ($restored) {
        return bly_ext_toggle_failure($ctx, $reason, base_code: 'bly_write_failed');
    }

    return bly_error('bly_rollback_failed', sprintf(
        'Could not %s extension "%s": %s — AND the rollback could not re-establish the prior bytes, so the %s row may now differ from both the prior and the intended state. Inspect it (blocksy-list-extensions, blocksy-check-setup) before retrying.%s',
        $ctx['direction'],
        $ctx['id'],
        $reason,
        BLY_EXT_STATE_OPTION,
        $ctx['phase']['hook_ran']
            ? ' Its lifecycle hook ran and was NOT compensated (the stored state is indeterminate).'
            : '',
    ));
}

/**
 * Honest failure for a toggle whose state row is verifiably unchanged.
 * The engine OWNS the replicated chain, so `$ctx['phase']` records
 * exactly what was ATTEMPTED and what completed — nothing is inferred:
 *
 *  - no hook ran and the boot is clean (nothing attempted, or the class
 *    fully constructed) → $base_code; a successful construction is named
 *    for what it is (request-scoped; no persistent changes), and only a
 *    call where NO file execution was attempted gets the provably-clean
 *    "no lifecycle code ran" wording.
 *  - a throw interrupted the boot (extension file executed but died
 *    before the class constructed, or the constructor started and did
 *    not complete) → bly_partial_side_effects: the interrupted code may
 *    have made persistent changes before failing.
 *  - the forward hook ran (or was invoked and threw) → the opposite hook
 *    is invoked as contained compensation: clean → $base_code (escalated
 *    to bly_partial_side_effects when the boot was ALSO interrupted);
 *    compensation threw or undeclared → bly_partial_side_effects.
 *
 * @param array{id: string, name: string, direction: 'enable'|'disable', phase: array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool}, threw: \Throwable|null} $ctx
 * @param 'bly_write_failed'|'bly_lock_lost' $base_code
 */
function bly_ext_toggle_failure(array $ctx, string $reason, string $base_code = 'bly_write_failed'): WP_Error
{
    $forward_hook = $ctx['direction'] === 'enable' ? 'onActivation' : 'onDeactivation';
    $opposite_hook = $ctx['direction'] === 'enable' ? 'onDeactivation' : 'onActivation';
    $boot = bly_ext_boot_note($ctx);
    $inspect = ' Inspect the extension\'s state manually before retrying.';

    if (!$ctx['phase']['hook_ran']) {
        if ($boot['partial']) {
            return bly_error('bly_partial_side_effects', bly_ext_failure_message(
                $ctx,
                $reason,
                sprintf(' and its %s lifecycle hook did not run, but%s%s', $forward_hook, $boot['text'], $inspect),
            ));
        }
        return bly_error($base_code, bly_ext_failure_message(
            $ctx,
            $reason,
            sprintf(
                ' and its %s lifecycle hook did not run.%s',
                $forward_hook,
                $boot['text'] !== '' ? $boot['text'] : ' No lifecycle code ran; no persistent changes were made.',
            ),
        ));
    }

    $hook_status = $ctx['threw'] !== null ? 'was invoked and may have only partially completed' : 'ran';
    $class = bly_ext_class_name($ctx['id']);

    if (!method_exists($class, $opposite_hook)) {
        return bly_error('bly_partial_side_effects', bly_ext_failure_message(
            $ctx,
            $reason,
            sprintf(
                ', but its %s lifecycle hook %s and the extension declares no %s hook to compensate with — its lifecycle side effects may remain.%s%s',
                $forward_hook,
                $hook_status,
                $opposite_hook,
                $boot['text'],
                $inspect,
            ),
        ));
    }

    try {
        call_user_func($class . '::' . $opposite_hook);
    } catch (\Throwable $comp) {
        error_log(sprintf(
            'novamira-pro blocksy: compensation %s::%s for failed %s of %s threw: %s',
            $class,
            $opposite_hook,
            $ctx['direction'],
            $ctx['id'],
            $comp->getMessage(),
        ));
        return bly_error('bly_partial_side_effects', bly_ext_failure_message(
            $ctx,
            $reason,
            sprintf(
                '; its %s lifecycle hook %s, and the compensating %s hook ALSO threw (details in the server error log) — its lifecycle side effects may remain.%s%s',
                $forward_hook,
                $hook_status,
                $opposite_hook,
                $boot['text'],
                $inspect,
            ),
        ));
    }

    // Clean compensation — but an interrupted boot still leaves
    // potentially-persistent forward progress the compensation does not
    // cover, so it escalates the code.
    return bly_error(
        $boot['partial'] ? 'bly_partial_side_effects' : $base_code,
        bly_ext_failure_message(
            $ctx,
            $reason,
            sprintf(
                '; its %s lifecycle hook %s and was compensated by invoking %s, which completed.%s%s',
                $forward_hook,
                $hook_status,
                $opposite_hook,
                $boot['text'],
                $boot['partial'] ? $inspect : '',
            ),
        ),
    );
}

/**
 * Shared failure-message frame: what failed, why, and that the state row
 * is verifiably unchanged; `$tail` carries the branch-specific lifecycle
 * account.
 *
 * @param array{id: string, name: string, direction: 'enable'|'disable', phase: array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool}, threw: \Throwable|null} $ctx
 */
function bly_ext_failure_message(array $ctx, string $reason, string $tail): string
{
    return sprintf(
        'Could not %s extension "%s": %s. The state row is unchanged%s',
        $ctx['direction'],
        $ctx['id'],
        $reason,
        $tail,
    );
}

/**
 * What the boot phase (extension file + constructor — the code BETWEEN
 * the hooks and the persist) provably did, for the failure messages:
 * `partial` means interrupted forward progress that may have made
 * persistent changes and cannot be compensated.
 *
 * @param array{id: string, name: string, direction: 'enable'|'disable', phase: array{file_executed: bool, hook_ran: bool, construction_attempted: bool, constructed: bool}, threw: \Throwable|null} $ctx
 * @return array{partial: bool, text: string}
 */
function bly_ext_boot_note(array $ctx): array
{
    $phase = $ctx['phase'];
    if ($phase['construction_attempted'] && !$phase['constructed']) {
        return [
            'partial' => true,
            'text' => ' Its constructor was started and did not complete — it may have made persistent changes before failing.',
        ];
    }
    if ($phase['constructed']) {
        return [
            'partial' => false,
            'text' => ' The extension class was constructed for this request only (its constructor-registered hooks vanish when the request ends); no persistent changes were made.',
        ];
    }
    if ($ctx['threw'] !== null && $phase['file_executed'] && !$phase['hook_ran']) {
        return [
            'partial' => true,
            'text' => ' Its extension file was executed and threw before any hook or constructor ran — top-level file code may have made persistent changes before failing.',
        ];
    }
    return ['partial' => false, 'text' => ''];
}

// ---------------------------------------------------------------------------
// Per-extension settings registry
// ---------------------------------------------------------------------------

/**
 * The dashboard-level settings surface per extension, verified from
 * 2.1.52 source. `option` is the wp_options row the extension persists
 * to; `source` says which Companion accessor resolves the
 * defaults-merged value:
 *
 *  - `preboot`: the extension's pre-boot object's `ext_data()` — the
 *    exact accessor the ct-dashboard settings UI reads. `extract`
 *    selects the settings portion of that payload:
 *      `settings`   → the ['settings'] key
 *      `all`        → the whole payload (white-label: ext_data() IS the
 *                     settings map)
 *      `newsletter` → the payload minus the 'providers' catalog
 *                     (newsletter-subscribe merges provider credentials
 *                     with the provider list)
 *  - `extension`: the booted extension object's `get_settings()`
 *    (adobe-typekit has no pre-boot data accessor, so its settings are
 *    only resolvable while the extension is enabled).
 *
 * `secret_keys` lists settings keys whose values are credentials and are
 * redacted before leaving the server.
 *
 * Extensions absent from this registry have NO dashboard settings
 * storage — their options are customizer options (theme_mods) served by
 * blocksy-list-options / blocksy-get-option / blocksy-edit-option.
 *
 * @return array<string, array{
 *     option: string,
 *     source: 'preboot'|'extension',
 *     extract: 'settings'|'all'|'newsletter',
 *     secret_keys: list<string>,
 * }>
 */
function bly_ext_settings_registry(): array
{
    return [
        'product-reviews' => [
            'option' => 'blocksy_ext_product_reviews_settings',
            'source' => 'preboot',
            'extract' => 'settings',
            'secret_keys' => [],
        ],
        'newsletter-subscribe' => [
            'option' => 'blocksy_ext_mailchimp_credentials',
            'source' => 'preboot',
            'extract' => 'newsletter',
            'secret_keys' => ['api_key'],
        ],
        'local-google-fonts' => [
            'option' => 'blocksy_ext_local_google_fonts_settings',
            'source' => 'preboot',
            'extract' => 'settings',
            'secret_keys' => [],
        ],
        'custom-fonts' => [
            'option' => 'blocksy_ext_custom_fonts_settings',
            'source' => 'preboot',
            'extract' => 'settings',
            'secret_keys' => [],
        ],
        'adobe-typekit' => [
            'option' => 'blocksy_ext_adobe_typekit_settings',
            'source' => 'extension',
            'extract' => 'all',
            'secret_keys' => [],
        ],
        'white-label' => [
            'option' => 'blocksy_ext_white_label_settings',
            'source' => 'preboot',
            'extract' => 'all',
            'secret_keys' => [],
        ],
        'post-types-extra' => [
            'option' => 'blocksy_ext_post_types_extra_settings',
            'source' => 'preboot',
            'extract' => 'settings',
            'secret_keys' => [],
        ],
        'woocommerce-extra' => [
            'option' => 'blocksy_ext_woocommerce_extra_settings',
            'source' => 'preboot',
            'extract' => 'settings',
            'secret_keys' => [],
        ],
    ];
}

/**
 * Shared output schema for enable/disable — identical shape, only the
 * meaning of `enabled` flips.
 *
 * @return array<string, mixed>
 */
function bly_ext_toggle_output_schema(string $enabled_meaning): array
{
    return [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'enabled' => [
                'type' => 'boolean',
                'description' => $enabled_meaning,
            ],
            'changed' => [
                'type' => 'boolean',
                'description' => 'False when the extension was already in the requested state (idempotent no-op — nothing was written and no hooks ran).',
            ],
            'rewrite_rules_flushed' => [
                'type' => 'boolean',
                'description' => 'True when the cached rewrite rules were dropped for regeneration on the next request (extensions that register post types / rewrites).',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Non-fatal conditions worth knowing (e.g. a dependency plugin the extension self-guards on is inactive).',
            ],
        ],
        'required' => ['id', 'name', 'enabled', 'changed', 'rewrite_rules_flushed', 'warnings'],
        'additionalProperties' => false,
    ];
}

/**
 * Validate one user-supplied extension id against the live registry.
 * Returns the normalized (lowercased, trimmed) id, or WP_Error.
 *
 * @param array<string, array{id: string, name: string, description: string, pro: bool, installed: bool, path: null|string}> $rows
 */
function bly_ext_validate_id(mixed $input_id, array $rows): string|WP_Error
{
    if (!is_string($input_id) || trim($input_id) === '') {
        return bly_error('bly_invalid_input', message: '`id` is required and must be a non-empty string.');
    }
    $id = strtolower(trim($input_id));
    if (!isset($rows[$id])) {
        return bly_error('bly_not_found', sprintf('Unknown extension id "%s". Use blocksy-list-extensions to discover valid ids.', bly_ext_clean_text(
            $id,
            BLY_EXT_MAX_NAME_BYTES,
        )));
    }
    return $id;
}

/**
 * Guard shared by enable/disable: the extension's files must be present
 * on this install (placeholder premium rows on non-pro installs have no
 * files and the manager silently no-ops on them).
 *
 * @param array{id: string, name: string, description: string, pro: bool, installed: bool, path: null|string} $row
 */
function bly_ext_installed_gate(array $row): ?WP_Error
{
    if ($row['installed']) {
        return null;
    }
    if ($row['pro']) {
        return bly_error('bly_pro_required', sprintf(
            'Extension "%s" is part of the Companion Pro catalog but its files are not available on this install (free Companion or non-pro plan) — the Companion registers it as a placeholder only.',
            $row['id'],
        ));
    }
    return bly_error('bly_not_found', sprintf(
        'Extension "%s" is registered without a file path — its files are missing from this install.',
        $row['id'],
    ));
}
