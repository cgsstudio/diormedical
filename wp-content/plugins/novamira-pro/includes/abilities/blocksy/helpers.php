<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/** Maximum JSON-encoded byte size for a single option value emitted by read abilities. */
const BLY_MAX_VALUE_BYTES = 16_384;

/** Maximum number of choice values emitted for a single option. */
const BLY_MAX_CHOICES = 60;

/** Maximum palette slots emitted by blocksy-get-color-palette (theme ships 8). */
const BLY_MAX_PALETTE_SLOTS = 24;

/** Maximum byte length for a single palette color value; longer strings are malformed, not colors. */
const BLY_MAX_COLOR_VALUE_BYTES = 256;

/** Maximum extension slugs reported by blocksy-check-setup. */
const BLY_MAX_EXTENSIONS = 50;

/** Maximum extra (non-colorN) stored palette keys echoed by get-color-palette. */
const BLY_MAX_EXTRA_STORED_KEYS = 20;

/** Maximum byte length echoed for a single extra stored palette key name. */
const BLY_MAX_EXTRA_KEY_BYTES = 64;

/** Maximum elements accepted in a list-shaped written value (see write-helpers). */
const BLY_MAX_LIST_ITEMS = 100;

// Dynamic choice-vocabulary kinds (see bly_choice_source()).
/** Vocabulary resolved from live nav_menu terms — blocksy_get_menus_items(). */
const BLY_CHOICE_SOURCE_NAV_MENU = 'nav_menu';

/** Vocabulary resolved from live ct_content_block posts — blocksy_companion_get_content_blocks(). */
const BLY_CHOICE_SOURCE_CONTENT_BLOCK = 'content_block';

/** Vocabulary resolved from live taxonomy terms — the Companion trending extension's category pickers. */
const BLY_CHOICE_SOURCE_TERM = 'term';

/** Vocabulary resolved from live pages — the WooCommerce checkout page pickers (get_pages()). */
const BLY_CHOICE_SOURCE_PAGE = 'page';

// Option storage kinds (see bly_option_storage()).
/** The option lives in the theme's `theme_mods_<stylesheet>` row (the default for every Blocksy option). */
const BLY_STORAGE_THEME_MOD = 'theme_mod';

/** The option lives in its OWN wp_options row (`setting.type = option` in the declaration). */
const BLY_STORAGE_OPTION = 'option';

/**
 * Shared helpers for the Blocksy theme integration (Sprint 1: read-only).
 *
 * Blocksy stores every customizer value in the standard
 * `theme_mods_<stylesheet>` row, and — critically — NEVER persists
 * defaults: a fresh install has no `colorPalette` key at all, and
 * `blocksy_get_theme_mod()` does not consult the option definitions.
 * Every read ability here therefore resolves unset values from the
 * option schema in the theme's `inc/options/` files (via
 * `blocksy_get_options('customizer')`), exactly like the customizer does
 * when it registers settings (`default => $option['value']`).
 */

// ---------------------------------------------------------------------------
// Error factories
// ---------------------------------------------------------------------------

/**
 * Uniform WP_Error factory for this surface. The HTTP status is embedded in
 * `$data['status']` so the Abilities API returns the right 4xx code over REST.
 *
 * Code-to-status map:
 *   bly_invalid_input=400, bly_not_found=404, bly_stylesheet_changed=409,
 *   bly_choice_stale=409 (a dynamic choice vocabulary changed between
 *   validation and the in-lock persist; nothing written),
 *   bly_theme_inactive=412, bly_companion_inactive=412,
 *   bly_pro_required=412, bly_extension_disabled=412,
 *   bly_schema_unavailable=500,
 *   bly_write_failed=500 (nothing persisted; any lifecycle side effects
 *   were compensated or never ran), bly_partial_side_effects=500 (nothing
 *   persisted but third-party lifecycle side effects may remain — the
 *   compensation hook failed or is not declared), bly_rollback_failed=500,
 *   bly_refresh_failed=500 (the value WAS persisted; only the cache
 *   regeneration failed), bly_sanitizer_unavailable=500,
 *   bly_lock_unavailable=503, bly_lock_lost=503
 */
function bly_error(string $code, string $message): WP_Error
{
    $status_map = [
        'bly_invalid_input' => 400,
        'bly_not_found' => 404,
        'bly_stylesheet_changed' => 409,
        'bly_choice_stale' => 409,
        'bly_theme_inactive' => 412,
        'bly_companion_inactive' => 412,
        'bly_pro_required' => 412,
        'bly_extension_disabled' => 412,
        'bly_schema_unavailable' => 500,
        'bly_write_failed' => 500,
        'bly_partial_side_effects' => 500,
        'bly_rollback_failed' => 500,
        'bly_refresh_failed' => 500,
        'bly_sanitizer_unavailable' => 500,
        'bly_lock_unavailable' => 503,
        'bly_lock_lost' => 503,
    ];
    $status = $status_map[$code] ?? 400;
    return new WP_Error($code, $message, ['status' => $status]);
}

/**
 * The option schema could not be loaded or is provably incomplete. Defaults
 * MUST come from the installed theme's own `inc/options/` definitions —
 * fabricating values from a copied snapshot would silently return wrong
 * data on newer theme versions or filtered schemas, so an undetermined
 * schema is an explicit failure, never a canned success.
 */
function bly_schema_error(string $detail): WP_Error
{
    return bly_error('bly_schema_unavailable', sprintf(
        'The Blocksy option schema could not be resolved (%s). Default values cannot be determined, so this read refuses instead of guessing. Verify the theme installation (inc/options/, inc/customizer/sync.php) and retry.',
        $detail,
    ));
}

function bly_theme_inactive_error(): WP_Error
{
    return bly_error(
        'bly_theme_inactive',
        message: 'The Blocksy theme is not active or does not meet the minimum supported version. Check theme.active and theme.min_satisfied from blocksy-check-setup.',
    );
}

// ---------------------------------------------------------------------------
// Output shape helpers
// ---------------------------------------------------------------------------

/**
 * Emit an associative map as-is, or `new \stdClass()` when empty — a raw
 * empty PHP array serialises to JSON `[]`, which strict validators reject
 * against `type: object`.
 *
 * @template TValue
 * @param array<array-key, TValue> $map
 * @return array<array-key, TValue>|\stdClass
 */
function bly_object_or_map(array $map): array|\stdClass
{
    return $map === [] ? new \stdClass() : $map;
}

/**
 * Cap an arbitrary option value for agent-facing output. Values whose JSON
 * encoding exceeds BLY_MAX_VALUE_BYTES are replaced with a marker string so
 * a runaway theme_mod (a full header_placements tree, an inlined SVG) cannot
 * blow the agent context on a discovery read.
 *
 * @param mixed $value
 * @return mixed
 */
function bly_cap_value(mixed $value): mixed
{
    $encoded = wp_json_encode($value);
    if (!is_string($encoded) || strlen($encoded) <= BLY_MAX_VALUE_BYTES) {
        return $value;
    }
    return sprintf(
        '[value omitted: %d bytes JSON-encoded, exceeds the %d-byte cap]',
        strlen($encoded),
        BLY_MAX_VALUE_BYTES,
    );
}

// ---------------------------------------------------------------------------
// Stored theme_mods access
// ---------------------------------------------------------------------------

/**
 * The wp_options row Blocksy values live in for the CURRENT theme. With a
 * child theme active this is `theme_mods_<child-slug>` — the parent row
 * exists too but is dormant, and writing it would be a visual no-op.
 */
function bly_mods_key(): string
{
    return 'theme_mods_' . get_stylesheet();
}

/**
 * Raw stored mods array for the active (child-aware) theme. No defaults
 * applied — a key absent here means "the customizer never saved it" and its
 * effective value comes from the option schema default.
 *
 * Despite its docblock, `get_theme_mods()` returns `false` on a truly
 * fresh install where neither the current `theme_mods_<stylesheet>` row
 * nor the legacy `mods_<name>` row exists — precisely the state where
 * default resolution matters most, so the false MUST normalize to [].
 *
 * @return array<array-key, mixed>
 */
function bly_stored_mods(): array
{
    /** @var array<array-key, mixed>|false $mods */
    $mods = get_theme_mods();
    return is_array($mods) ? $mods : [];
}

// ---------------------------------------------------------------------------
// Option schema (defaults) — inc/options tree
// ---------------------------------------------------------------------------

/**
 * Load the full customizer option tree from the theme's `inc/options/`
 * definitions, or a WP_Error when it cannot be resolved COMPLETELY.
 *
 * Outside an actual customizer request the theme skips loading
 * `inc/customizer/init.php`, so the sync helpers some option files call
 * (`blocksy_sync_single_post_container()` and friends) are undefined and
 * those files would be silently dropped ("contained fatal") — preloading
 * core's customize classes plus the theme's `inc/customizer/sync.php`
 * restores the complete tree in MCP/REST/CLI context. When that preload
 * cannot happen (sync.php unreadable or throwing), the tree WOULD be
 * silently partial, so this fails explicitly instead of proceeding.
 * Memoized per request (failures too).
 *
 * @return array<array-key, mixed>|WP_Error
 */
function bly_options_tree_or_error(): array|WP_Error
{
    static $result = null;
    /** @var array<array-key, mixed>|WP_Error|null $result */
    if ($result === null) {
        $result = bly_load_options_tree();
    }
    return $result;
}

/**
 * Make the theme's option files loadable OUTSIDE a customizer request:
 * core's customize classes plus the theme's `inc/customizer/sync.php`
 * (the `blocksy_sync_*` helpers many option files call at load time —
 * the customizer tree AND the per-post meta option files, whose
 * page-title block calls blocksy_sync_whole_page()). Without this the
 * theme silently drops those files ("contained fatal"); when the preload
 * itself cannot happen, callers fail explicitly instead of serving a
 * partial schema. Idempotent (require_once throughout).
 */
function bly_preload_customizer_sync(): ?WP_Error
{
    if (!function_exists('blocksy_get_options')) {
        return bly_schema_error('the theme option reader blocksy_get_options() is not loaded');
    }

    $sync = get_template_directory() . '/inc/customizer/sync.php';
    if (!is_readable($sync)) {
        return bly_schema_error(
            'inc/customizer/sync.php is missing or unreadable — option files depending on sync helpers would be silently dropped',
        );
    }

    try {
        require_once ABSPATH . 'wp-includes/customize/class-wp-customize-partial.php';
        require_once ABSPATH . 'wp-includes/class-wp-customize-manager.php';
        require_once $sync;
    } catch (\Throwable $e) {
        // Third-party exception text stays server-side: REST error payloads
        // reach agents/clients, and raw messages can leak paths or internals.
        error_log('novamira-pro blocksy: customizer sync preload failed: ' . $e->getMessage());
        return bly_schema_error('customizer sync preload failed — details in the server error log');
    }
    return null;
}

/**
 * Uncached tree load behind `bly_options_tree_or_error()`. Callers use the
 * memoized accessor.
 *
 * @return array<array-key, mixed>|WP_Error
 */
function bly_load_options_tree(): array|WP_Error
{
    $preload = bly_preload_customizer_sync();
    if ($preload instanceof WP_Error) {
        return $preload;
    }

    /** @var mixed $raw */
    $raw = blocksy_get_options('customizer');
    if (!is_array($raw) || $raw === []) {
        return bly_schema_error('blocksy_get_options(\'customizer\') returned no option tree');
    }
    return $raw;
}

/**
 * Presentational option types that never carry a stored value. Mirrors the
 * theme's own `blocksy-options-without-controls` deny list, plus
 * `ct-group-title` (display-only headings in the root tree).
 *
 * @return list<string>
 */
function bly_presentational_types(): array
{
    return [
        'ct-divider',
        'ct-spacer',
        'ct-title',
        'ct-group-title',
        'ct-notification',
        'ct-customize-section-title-actions',
        'blocksy-customizer-options-manager',
    ];
}

/**
 * Whether an option id looks like one of Blocksy's anonymous
 * `blocksy_rand_md5()` wrapper keys (32 hex chars). Those ids are
 * regenerated per release and never hold user data.
 */
function bly_is_hash_id(string $id): bool
{
    return preg_match('/^[a-f0-9]{32}$/', $id) === 1;
}

/**
 * Flat id-indexed view of every real (value-bearing) option in the schema
 * tree, or a WP_Error when the schema is unavailable or provably
 * incomplete. Each row: {id, type, label, section, option} where `section`
 * is the breadcrumb of container titles from the tree root and `option` is
 * the raw definition array (its `value` key is the customizer default).
 *
 * `colorPalette` is used as a completeness anchor: it has shipped in
 * `inc/options/general/colors.php` since 2.0 and does not depend on sync
 * helpers, so an index without it means the tree walk saw a broken or
 * foreign schema — refuse rather than serve partial defaults. Memoized per
 * request.
 *
 * @return array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}>|WP_Error
 */
function bly_option_index_or_error(): array|WP_Error
{
    static $index = null;
    /** @var array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}>|null $index */
    if (is_array($index)) {
        return $index;
    }

    $tree = bly_options_tree_or_error();
    if ($tree instanceof WP_Error) {
        return $tree;
    }

    $built = bly_build_option_index($tree);
    if ($built instanceof WP_Error) {
        return $built;
    }
    $index = $built;
    return $built;
}

/**
 * The SAME index, rebuilt from a FRESH, uncached tree load — the option
 * files (and every dynamic vocabulary they resolve: nav menus, pages,
 * terms) are re-evaluated against the site state of this exact moment.
 * Used by the in-lock re-check of dynamic choice vocabularies
 * (bly_recheck_dynamic_choice); every other caller uses the memoized
 * bly_option_index_or_error(). Deliberately NOT memoized.
 *
 * @return array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}>|WP_Error
 */
function bly_fresh_option_index(): array|WP_Error
{
    $tree = bly_load_options_tree();
    if ($tree instanceof WP_Error) {
        return $tree;
    }
    return bly_build_option_index($tree);
}

/**
 * Flatten one resolved option tree into the id-indexed schema, refusing a
 * tree without the `colorPalette` completeness anchor.
 *
 * @param array<array-key, mixed> $tree
 * @return array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}>|WP_Error
 */
function bly_build_option_index(array $tree): array|WP_Error
{
    $built = [];
    bly_walk_options($built, $tree, []);
    if (!isset($built['colorPalette'])) {
        return bly_schema_error('the resolved option tree lacks the colorPalette anchor — schema incomplete');
    }
    return $built;
}

/**
 * Recursive walker over a Blocksy option tree, mirroring the shapes
 * `blocksy_collect_options()` understands:
 *  - a node with an `options` array is a container (breadcrumb push when titled)
 *  - an int-keyed node without `type` is an anonymous list wrapper
 *  - a `ct-panel` node holds its children in `inner-options`; the panel
 *    itself is a real setting only when it declares a `switch`
 *  - any other node with `inner-options` is a composite wrapper — children
 *    are real settings, the wrapper (a `blocksy_rand_md5()` id) is not
 *  - remaining typed nodes are settings unless presentational or hash-keyed
 *
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $out
 * @param array<array-key, mixed> $options
 * @param list<string> $breadcrumb
 * @param-out array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $out
 */
function bly_walk_options(array &$out, array $options, array $breadcrumb): void
{
    /** @var mixed $node */
    foreach ($options as $key => $node) {
        if (!is_array($node)) {
            continue;
        }

        if (isset($node['options']) && is_array($node['options'])) {
            bly_walk_options($out, $node['options'], bly_push_crumb($breadcrumb, $key, $node));
            continue;
        }

        if (is_int($key) && !isset($node['type'])) {
            bly_walk_options($out, $node, $breadcrumb);
            continue;
        }

        if (!isset($node['type']) || !is_string($node['type'])) {
            continue;
        }

        bly_walk_typed_node($out, $key, $node, $breadcrumb);
    }
}

/**
 * Handle one typed (non-container) node during the walk: panels recurse
 * into `inner-options` with a breadcrumb push (emitting the panel itself
 * only when it is switchable), other composite wrappers recurse
 * transparently, presentational and hash-keyed nodes are dropped, and
 * everything else is a real setting.
 *
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $out
 * @param array<array-key, mixed> $node
 * @param list<string> $breadcrumb
 * @param-out array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $out
 */
function bly_walk_typed_node(array &$out, int|string $key, array $node, array $breadcrumb): void
{
    $id = is_string($key) ? $key : '';

    if ($node['type'] === 'ct-panel') {
        if (isset($node['switch']) && $id !== '' && !bly_is_hash_id($id)) {
            bly_emit_option($out, $id, $node, $breadcrumb);
        }
        if (isset($node['inner-options']) && is_array($node['inner-options'])) {
            bly_walk_options($out, $node['inner-options'], bly_push_crumb($breadcrumb, $key, $node));
        }
        return;
    }

    if (isset($node['inner-options']) && is_array($node['inner-options'])) {
        bly_walk_options($out, $node['inner-options'], $breadcrumb);
        return;
    }

    if (in_array($node['type'], bly_presentational_types(), strict: true)) {
        return;
    }
    if ($id === '' || bly_is_hash_id($id)) {
        return;
    }

    bly_emit_option($out, $id, $node, $breadcrumb);
}

/**
 * Append a container's display name to the breadcrumb. Containers carry
 * `title` (root panels) or `label` (nested groups); anonymous hash-keyed
 * wrappers (conditions, styling groups) add no crumb.
 *
 * @param list<string> $breadcrumb
 * @param array<array-key, mixed> $node
 * @return list<string>
 */
function bly_push_crumb(array $breadcrumb, int|string $key, array $node): array
{
    $name = trim(wp_strip_all_tags(bly_crumb_name($key, $node)));
    if ($name === '') {
        return $breadcrumb;
    }
    $breadcrumb[] = $name;
    return $breadcrumb;
}

/**
 * Raw display name for a container: `title` (root panels) first, then
 * `label` (nested groups), then a non-hash string key; '' when anonymous.
 *
 * @param array<array-key, mixed> $node
 */
function bly_crumb_name(int|string $key, array $node): string
{
    if (isset($node['title']) && is_string($node['title'])) {
        return $node['title'];
    }
    if (isset($node['label']) && is_string($node['label'])) {
        return $node['label'];
    }
    if (is_string($key) && !bly_is_hash_id($key)) {
        return $key;
    }
    return '';
}

/**
 * Store one flattened option row. First definition wins — the tree can
 * mention an id twice through conditional wrappers, and the first
 * occurrence is the one the customizer registers.
 *
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $out
 * @param array<array-key, mixed> $node
 * @param list<string> $breadcrumb
 * @param-out array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $out
 */
function bly_emit_option(array &$out, string $id, array $node, array $breadcrumb): void
{
    if (isset($out[$id])) {
        return;
    }
    $label = isset($node['label']) && is_string($node['label']) ? $node['label'] : $id;
    $type = isset($node['type']) && is_string($node['type']) ? $node['type'] : '';
    $out[$id] = [
        'id' => $id,
        'type' => $type,
        'label' => $label,
        'section' => implode(' / ', $breadcrumb),
        'option' => $node,
    ];
}

/**
 * Where an option's value PHYSICALLY lives — the fact the customizer
 * honors and every read/write here must honor too. Blocksy passes each
 * declaration's `setting` array straight into
 * `$wp_customize->add_setting()` (inc/customizer/init.php:747), so a
 * declaration carrying `setting.type = 'option'` is registered as a
 * WP_Customize_Setting of type `option`: the customizer saves it with
 * update_option() into its OWN wp_options row, never into theme_mods,
 * and the consumers read it with get_option() — on 2.1.52 these are the
 * WooCommerce settings Blocksy re-exposes in its customizer panels
 * (checkout field toggles, the privacy-policy / terms page pickers,
 * demo-store notice, catalog columns/rows, thumbnail cropping, catalog
 * ordering, shop/category display, single image width) plus core's
 * `wp_page_for_privacy_policy`, all read by WooCommerce/core via
 * get_option(), and one (woocommerce_catalog_columns) by Blocksy's own
 * dynamic CSS via get_option(). Everything else is a theme_mod. Writing
 * an option-typed id into theme_mods would report success while the real
 * row stays unchanged — a silent misdirection this classifier exists to
 * prevent.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return string BLY_STORAGE_THEME_MOD | BLY_STORAGE_OPTION
 */
function bly_option_storage(array $row): string
{
    /** @var mixed $setting */
    $setting = $row['option']['setting'] ?? null;
    if (is_array($setting) && ($setting['type'] ?? null) === 'option') {
        return BLY_STORAGE_OPTION;
    }
    return BLY_STORAGE_THEME_MOD;
}

/**
 * The stored state of an option-storage (`setting.type = option`) id:
 * whether its wp_options row exists (a RAW existence probe — get_option()
 * cannot tell an absent row from a stored `false`, and `option_<name>`
 * filters could forge either) and the effective value exactly as its
 * consumers read it — get_option() with the schema default as fallback,
 * so an absent row resolves to the same default the theme_mod path
 * reports. Scalars come back in stored form (strings), which IS what
 * WooCommerce/core see.
 *
 * @return array{stored: bool, value: mixed}
 */
function bly_stored_option_state(string $id, mixed $default): array
{
    $stored = bly_raw_option_row($id) !== false;
    /** @var mixed $value */
    $value = get_option($id, $default);
    return ['stored' => $stored, 'value' => $value];
}

/**
 * Resolve one option id against a pre-fetched schema index: stored
 * theme_mod when present, otherwise the schema default (`option['value']`).
 * Returns null when the id is neither in the schema nor stored. Callers
 * obtain `$index` from `bly_option_index_or_error()` and propagate its
 * WP_Error before calling this.
 *
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $index
 * Storage-aware (bly_option_storage): an option-typed id is read from its
 * own wp_options row, exactly where the write path puts it and where its
 * consumers read it — read and write always agree.
 *
 * @return array{id: string, in_schema: bool, type: string|null, label: string|null, section: string|null, default: mixed, has_stored_value: bool, value: mixed, storage: string}|null
 */
function bly_resolve_option(string $id, array $index): ?array
{
    $mods = bly_stored_mods();
    $in_schema = isset($index[$id]);
    $has_stored = array_key_exists($id, $mods);
    $storage = $in_schema ? bly_option_storage($index[$id]) : BLY_STORAGE_THEME_MOD;

    if (!$in_schema && !$has_stored) {
        return null;
    }

    $default = null;
    if ($in_schema && array_key_exists('value', $index[$id]['option'])) {
        /** @var mixed $default */
        $default = $index[$id]['option']['value'];
    }

    $state = $storage === BLY_STORAGE_OPTION
        ? bly_stored_option_state($id, $default)
        : ['stored' => $has_stored, 'value' => $has_stored ? $mods[$id] : $default];

    return [
        'id' => $id,
        'in_schema' => $in_schema,
        'type' => $in_schema ? $index[$id]['type'] : null,
        'label' => $in_schema ? $index[$id]['label'] : null,
        'section' => $in_schema ? $index[$id]['section'] : null,
        'default' => $default,
        'has_stored_value' => $state['stored'],
        'value' => $state['value'],
        'storage' => $storage,
    ];
}

// ---------------------------------------------------------------------------
// Choice vocabulary extraction
// ---------------------------------------------------------------------------

/**
 * The value vocabulary a `choices` declaration encodes, each value in its
 * DECLARED type — or null when the declaration carries none.
 *
 * Blocksy declares choices in two vocabulary-bearing shapes: an assoc map
 * (value => label, or value => {src, title} for ct-image-picker), where
 * the KEYS are the vocabulary (int keys stay ints), and the
 * blocksy_ordered_keys() shape every ct-select uses
 * (admin/helpers/options-logic.php) — a LIST of {key, value} rows where
 * each row's `key` is the valid value (its scalar type kept) and `value`
 * its label. For the list shape array_keys() would yield the bare list
 * indexes (0, 1, 2, …), which are never valid values. List rows without a
 * scalar `key` (e.g. ct-labeled-group's {id, label} UI tabs) carry no
 * value vocabulary at all → null, so callers fall back to Blocksy's own
 * sanitizer instead of rejecting against a phantom vocabulary.
 *
 * Writers must persist the typed form: Blocksy's own per-control
 * validator (inc/customizer/validator.php, ct-select/ct-image-picker/
 * ct-radio) strict-compares the incoming value against these — a menu
 * term id stored as the string "16" would be silently swapped for the
 * default by a later customizer save even if our own gate let it through.
 * Callers holding an option ROW use bly_choice_vocabulary().
 *
 * @param array<array-key, mixed> $choices
 * @return list<int|string>|null
 */
function bly_choice_typed_values(array $choices): ?array
{
    if ($choices === []) {
        return null;
    }
    if (!bly_array_is_list($choices)) {
        return array_keys($choices);
    }
    $values = [];
    /** @var mixed $row */
    foreach ($choices as $row) {
        if (is_array($row) && isset($row['key']) && (is_int($row['key']) || is_string($row['key']))) {
            $values[] = $row['key'];
            continue;
        }
        if (is_int($row) || is_string($row)) {
            $values[] = $row;
            continue;
        }
        return null;
    }
    return $values;
}

/**
 * Human labels keyed by choice value, for the values whose label
 * meaningfully differs from the value itself (case-insensitively) — the
 * informative cases: menu names behind term ids, image-picker titles
 * behind type-N slugs. Redundant pairs like small => Small are dropped to
 * keep discovery output compact. Both declaration shapes are covered:
 * assoc maps carry the label in the entry value (`title` for the
 * image-picker row shape), blocksy_ordered_keys() rows in `value`.
 *
 * @param array<array-key, mixed> $choices
 * @return array<string, string>
 */
function bly_choice_labels(array $choices): array
{
    $labels = [];
    /** @var mixed $entry */
    foreach ($choices as $key => $entry) {
        if (is_int($key) && is_array($entry) && is_scalar($entry['key'] ?? null)) {
            bly_collect_choice_label($labels, (string) $entry['key'], $entry['value'] ?? null);
            continue;
        }
        bly_collect_choice_label($labels, (string) $key, is_array($entry) ? $entry['title'] ?? null : $entry);
    }
    return $labels;
}

/**
 * Keep one candidate choice label when it is an informative string —
 * non-empty and case-insensitively different from the value it names.
 *
 * @param array<string, string> $labels
 * @param-out array<string, string> $labels
 */
function bly_collect_choice_label(array &$labels, string $value, mixed $label): void
{
    if (is_string($label) && $label !== '' && strcasecmp($label, $value) !== 0) {
        $labels[$value] = $label;
    }
}

// ---------------------------------------------------------------------------
// Choice vocabulary resolution + the explicit dynamic/static classification
// ---------------------------------------------------------------------------

/**
 * THE choice-vocabulary resolution function: the typed values an option
 * row's declaration currently carries (bly_choice_typed_values over its
 * `choices`), or null when the row declares no usable vocabulary. Every
 * surface goes through this one function — discovery (get-option,
 * get-header/get-footer item summaries), gate 1 (bly_validate_leaf), and
 * the in-lock re-check right before persisting (bly_recheck_dynamic_choice)
 * — so what an agent is shown, what a write is validated against, and
 * what is re-verified under the lock can never drift apart. Freshness is
 * a property of the ROW passed in, not of this function: the memoized
 * request-start index yields the request-start vocabulary, a row from
 * bly_fresh_option_index() / a re-run bly_builder_item_option_index()
 * yields the vocabulary of that exact moment.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return list<int|string>|null
 */
function bly_choice_vocabulary(array $row): ?array
{
    /** @var mixed $choices */
    $choices = $row['option']['choices'] ?? null;
    return is_array($choices) ? bly_choice_typed_values($choices) : null;
}

/**
 * Explicit classification of a choice vocabulary as DYNAMIC (one of the
 * BLY_CHOICE_SOURCE_* kinds) or STATIC (null).
 *
 * A vocabulary is dynamic when its declaration resolves database-backed
 * content entities at evaluation time — things that can be created,
 * emptied or deleted between gate-1 validation and the persist. Those get
 * re-resolved and re-checked INSIDE the write lock (TOCTOU discipline,
 * see bly_recheck_dynamic_choice). Everything else is static: fixed enums
 * literal in the option files, and the code-registered vocabularies
 * (image sizes via blocksy_get_all_image_sizes(), taxonomies via
 * get_object_taxonomies() / blocksy_get_taxonomies_for_cpt(), post types)
 * — constant for the lifetime of the process, so re-resolving them under
 * the lock would be pure overhead. The dynamic kinds, each pinned to the
 * generator that declares it (Blocksy 2.1.52 / Companion Pro 2.1.52):
 *
 *  - nav_menu — blocksy_get_menus_items() (inc/components/menus.php:506):
 *    the `blocksy_location` sentinel row followed by the term ids of every
 *    non-empty nav_menu (get_terms hide_empty). Used by the header
 *    menu/mobile-menu items, the footer menu item, and the Companion
 *    account item. Recognized by the sentinel as first value.
 *  - content_block — blocksy_companion_get_content_blocks()
 *    (framework/premium/helpers/content-blocks.php:98): ct_content_block
 *    post ids, the `hook_id` setting of the premium header/footer
 *    content-block item. Recognized by the setting id.
 *  - term — the trending extension's category pickers
 *    (framework/extensions/trending/customizer.php): the `all_categories`
 *    sentinel followed by term ids. Recognized by the sentinel.
 *  - page — the WooCommerce checkout page pickers (inc/options/woocommerce/
 *    general/checkout-page.php): '' plus page ids from get_pages().
 *    Recognized by the option ids.
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 */
function bly_choice_source(array $row): ?string
{
    if ($row['id'] === 'hook_id') {
        return BLY_CHOICE_SOURCE_CONTENT_BLOCK;
    }
    if (in_array($row['id'], ['wp_page_for_privacy_policy', 'woocommerce_terms_page_id'], strict: true)) {
        return BLY_CHOICE_SOURCE_PAGE;
    }
    $values = bly_choice_vocabulary($row);
    if ($values === null) {
        return null;
    }
    $first = $values[0] ?? null;
    if ($first === 'blocksy_location') {
        return BLY_CHOICE_SOURCE_NAV_MENU;
    }
    if ($first === 'all_categories') {
        return BLY_CHOICE_SOURCE_TERM;
    }
    return null;
}
