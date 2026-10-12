<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use ET\Builder\Packages\Conversion\Conversion;
use ET\Builder\Packages\GlobalData\GlobalPreset;
use ET\Builder\Packages\Module\Layout\Components\DynamicContent\DynamicContentOptions;
use ET\Builder\Packages\ModuleLibrary\ModuleRegistration;
use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Minimum Divi version that ships the D5 block-based module API the abilities
 * depend on. Divi 5 stores layouts as WordPress blocks (`divi/*`) in
 * `post_content`; Divi 4 used `[et_pb_*]` shortcodes and is out of scope.
 */
const NOVAMIRA_DIVI_MIN_VERSION = '5.0';

/** Upper bound for a loop's results-per-page, to keep the render-time query finite. */
const NOVAMIRA_DIVI_MAX_PER_PAGE = 100;

/**
 * Modules that hold raw HTML/PHP rather than structured content. The
 * integration builds native modules and treats these as a last resort — see
 * the `divi-build-page` skill. `divi-list-modules` flags them `raw_html: true`.
 *
 * @var list<string>
 */
const NOVAMIRA_DIVI_RAW_HTML_MODULES = ['divi/code', 'divi/fullwidth-code'];

/**
 * True when a Divi 5 runtime with the D5 module API is loaded. Used both by the
 * loader in novamira-pro.php (inlined there to avoid an always-on require) and
 * by abilities/tests that need a defensive guard.
 */
function divi_min_runtime_available(): bool
{
    $version = \Novamira\Pro\novamira_pro_divi_builder_version();
    if ($version === '') {
        return false;
    }
    if (version_compare($version, NOVAMIRA_DIVI_MIN_VERSION, operator: '<')) {
        return false;
    }
    if (!function_exists('et_builder_d5_enabled') || !et_builder_d5_enabled()) {
        return false;
    }
    return class_exists(ModuleRegistration::class);
}

/**
 * Build a namespaced WP_Error with an HTTP status, matching the other
 * integrations' error shape.
 *
 * @param array<string, mixed> $data
 */
function divi_error(string $code, string $message, int $status = 400, array $data = []): WP_Error
{
    return new WP_Error('novamira_divi_' . $code, $message, ['status' => $status] + $data);
}

/**
 * Mark a post as a Divi 5 builder page, exactly as Divi's own D5 save does
 * (SyncToServerController writes these three metas). Without _et_pb_use_builder,
 * et_pb_is_pagebuilder_used() returns false and the first Visual Builder open
 * runs Divi's classic-content activation, which wraps the tree in a
 * divi/placeholder and stashes the original in _et_pb_old_content — silently
 * destroying the build. _et_pb_use_divi_5 additionally suppresses the D5
 * readiness placeholder-wrap; _et_pb_show_page_creation hides the
 * page-creation flow once real content exists.
 */
function divi_mark_builder_page(int $post_id): void
{
    update_post_meta($post_id, meta_key: '_et_pb_use_divi_5', meta_value: 'on');
    update_post_meta($post_id, meta_key: '_et_pb_use_builder', meta_value: 'on');
    update_post_meta($post_id, meta_key: '_et_pb_show_page_creation', meta_value: 'off');
}

/** Seconds after which a held global-data lock is considered orphaned and stealable. */
const NOVAMIRA_DIVI_LOCK_TTL = 10;

/** How long (seconds) to wait for a contended global-data lock before proceeding anyway. */
const NOVAMIRA_DIVI_LOCK_WAIT = 5.0;

/**
 * Run `$fn` while holding a cross-process lock, so the read-modify-write of a
 * shared Divi option blob (the global colors / presets / variables palettes,
 * each a single `wp_options` row that `set_*` REPLACES wholesale) is
 * serialized. Without this, two concurrent writers each read the same base
 * blob and the second write clobbers the first — a silent site-wide lost write.
 *
 * The lock is a dedicated `wp_options` row acquired by INSERT: `option_name`
 * carries a UNIQUE index on both MySQL and the SQLite drop-in, so exactly one
 * inserter wins. A row older than the TTL is stolen (its holder likely died
 * mid-request). If the lock stays contended past the wait budget we run anyway
 * rather than fail the write — availability over the small residual race.
 *
 * After the lock is taken, the `wp_options` rows the write reads back through
 * are dropped from the per-request cache so the read INSIDE the lock sees a
 * prior holder's committed write instead of this request's start-of-request
 * copy — otherwise the lock would faithfully serialize writes over stale data
 * (still a lost write).
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function divi_with_global_lock(string $key, callable $fn)
{
    // The autoloaded/product options each write's read-modify-write reads back
    // through, keyed by lock name.
    $refresh = [
        'colors' => ['et_global_data'],
        'variables' => ['et_divi_global_variables'],
        'presets' => ['builder_global_presets_d5'],
    ];

    $token = divi_acquire_global_lock($key);
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete(key: 'alloptions', group: 'options');
        foreach ($refresh[$key] ?? [] as $option) {
            wp_cache_delete(key: $option, group: 'options');
        }
    }
    // Divi stores its theme options (including the color palette's
    // `et_global_data`) one-row in `et_divi`, which et_get_option() memoizes in
    // the `$et_theme_options` global and never re-reads within a request. Clear
    // it so the read inside the lock reloads from the (now-flushed) DB.
    unset($GLOBALS['et_theme_options']);
    try {
        return $fn();
    } finally {
        if ($token !== null) {
            divi_release_global_lock($key, $token);
        }
    }
}

/** The `wp_options` name backing a named global-data lock. */
function divi_global_lock_option(string $key): string
{
    return '_novamira_divi_lock_' . $key;
}

/**
 * Spin until the lock row is ours (or the wait budget elapses). Returns the
 * ownership token we wrote (the exact row value) when we hold the lock, or null
 * to mean "proceed unlocked" — in which case the caller must NOT release.
 *
 * The row is created with $wpdb->insert (not update_option) so the `option_name`
 * UNIQUE index does the mutual exclusion at the DB layer: exactly one concurrent
 * inserter wins, the rest get a duplicate-key failure. The value is a unique
 * `<uuid>|<timestamp>` token: the uuid identifies THIS acquisition (so release
 * and steal only remove the exact row they own/observed, never a lock a
 * different process has since taken over), and the timestamp drives TTL steal.
 */
function divi_acquire_global_lock(string $key): ?string
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return null;
    }
    $option = divi_global_lock_option($key);
    $token = divi_generate_uuid() . '|' . time();
    $deadline = microtime(as_float: true) + NOVAMIRA_DIVI_LOCK_WAIT;

    do {
        // A duplicate-key insert is the expected contended path — don't log it.
        $prior = $wpdb->suppress_errors(suppress: true);
        $inserted = $wpdb->insert($wpdb->options, [
            'option_name' => $option,
            'option_value' => $token,
            'autoload' => 'no',
        ]);
        $wpdb->suppress_errors(suppress: $prior);

        if ($inserted) {
            // $wpdb->insert bypasses the object cache; drop any stale/notoptions
            // entry so a same-process get_option (steal) sees the real row.
            divi_flush_lock_cache($option);
            return $token;
        }
        divi_steal_stale_global_lock($key);
        usleep(microseconds: 50_000);
    } while (microtime(as_float: true) < $deadline);

    return null;
}

/** Drop the object-cache entry for a lock option after a raw $wpdb write. */
function divi_flush_lock_cache(string $option): void
{
    if (function_exists('wp_cache_delete')) {
        wp_cache_delete(key: $option, group: 'options');
    }
}

/**
 * Clear a lock whose row is older than the TTL (the previous holder died
 * without releasing) so the next acquire attempt can take it. Compare-and-delete
 * on the EXACT value observed: if another waiter has meanwhile stolen it and
 * inserted a fresh token, our delete no-ops instead of removing their live lock.
 */
function divi_steal_stale_global_lock(string $key): void
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return;
    }
    $option = divi_global_lock_option($key);
    // Read fresh — a just-created lock from another process is a cache miss.
    /** @var mixed $held */
    $held = get_option($option);
    if (!is_string($held)) {
        return;
    }
    if ((time() - divi_lock_token_timestamp($held)) <= NOVAMIRA_DIVI_LOCK_TTL) {
        return;
    }
    $wpdb->delete($wpdb->options, ['option_name' => $option, 'option_value' => $held]);
    divi_flush_lock_cache($option);
}

/** Release a held lock, deleting only the exact row this acquisition wrote. */
function divi_release_global_lock(string $key, string $token): void
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return;
    }
    $option = divi_global_lock_option($key);
    $wpdb->delete($wpdb->options, ['option_name' => $option, 'option_value' => $token]);
    divi_flush_lock_cache($option);
}

/** The `<uuid>|<timestamp>` lock token's timestamp component (0 when malformed). */
function divi_lock_token_timestamp(string $token): int
{
    $pos = strrpos($token, needle: '|');
    return $pos === false ? 0 : (int) substr($token, $pos + 1);
}

/**
 * True when a module-attrs (or preset-attrs) object writes custom CSS through
 * the `css.<breakpoint>.<state>.freeForm` escape hatch.
 *
 * @param array<array-key, mixed> $attrs
 */
function divi_attrs_have_free_form_css(array $attrs): bool
{
    /** @var mixed $css */
    $css = $attrs['css'] ?? null;
    if (!is_array($css)) {
        return false;
    }
    /** @var mixed $states */
    foreach ($css as $states) {
        if (!is_array($states)) {
            continue;
        }
        /** @var mixed $fields */
        foreach ($states as $fields) {
            if (is_array($fields) && array_key_exists('freeForm', $fields)) {
                return true;
            }
        }
    }
    return false;
}

/**
 * True when any node in a forest of normalized module nodes carries a
 * `css.freeForm` write. Used by the whole-tree write paths.
 *
 * @param list<array<string, mixed>> $nodes
 */
function divi_tree_has_free_form_css(array $nodes): bool
{
    foreach ($nodes as $node) {
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        if (divi_attrs_have_free_form_css($attrs)) {
            return true;
        }
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if (divi_tree_has_free_form_css($children)) {
            return true;
        }
    }
    return false;
}

/**
 * The in-band nudge attached to every write that sets custom CSS via
 * `css.freeForm` — never a rejection, freeForm has legitimate uses
 * (pseudo-elements, keyframes), but attr-expressible properties belong in
 * `module.decoration.*` or the page stops being editable in the builder.
 */
function divi_free_form_css_warning(): string
{
    return (
        'This write sets custom CSS via `css.freeForm`. That is the escape hatch for what '
        . 'module attrs cannot express (pseudo-elements, keyframes). If any of these rules set '
        . 'background, spacing, typography, borders, or sizing, move them to the module\'s native '
        . '`module.decoration.*` attrs (see `divi-get-style-schema`) so the page stays editable '
        . 'in the builder.'
    );
}

/**
 * Count inline `style="…"` attributes inside markup carried by the string
 * leaves of a module-attrs object, at any depth. Only tag-scoped matches
 * count (`<span style=…`), never the bare word "style=" in prose.
 *
 * @param array<array-key, mixed> $attrs
 */
function divi_count_inline_style_attrs(array $attrs): int
{
    $count = 0;
    /** @var mixed $value */
    foreach ($attrs as $value) {
        if (is_array($value)) {
            $count += divi_count_inline_style_attrs($value);
            continue;
        }
        if (is_string($value) && $value !== '' && str_contains($value, 'style')) {
            $matches = [];
            $count += (int) preg_match_all('/<[^>]*\sstyle\s*=/i', $value, $matches);
        }
    }
    return $count;
}

/**
 * Same count across a nested {attrs, children} module tree — the payload
 * shape of set-content and add-module.
 *
 * @param array<array-key, mixed> $nodes
 */
function divi_tree_count_inline_style_attrs(array $nodes): int
{
    $count = 0;
    /** @var mixed $node */
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];
        $count += divi_count_inline_style_attrs($attrs);
        $children = is_array($node['children'] ?? null) ? $node['children'] : [];
        if ($children !== []) {
            $count += divi_tree_count_inline_style_attrs($children);
        }
    }
    return $count;
}

/**
 * Inline-style occurrences across an add-module style input payload: the root
 * `attrs` object plus the nested `children` modules.
 *
 * @param array<array-key, mixed> $input
 */
function divi_input_inline_style_count(array $input): int
{
    /** @var mixed $raw_attrs */
    $raw_attrs = $input['attrs'] ?? null;
    $count = divi_count_inline_style_attrs(is_array($raw_attrs) ? $raw_attrs : []);
    /** @var mixed $raw_children */
    $raw_children = $input['children'] ?? null;
    if (is_array($raw_children)) {
        $count += divi_tree_count_inline_style_attrs($raw_children);
    }
    return $count;
}

/**
 * Gate for content HTML carrying inline `style="…"` attributes — sibling of
 * divi_code_module_gate(). Inline styles bypass Divi's responsive system and
 * its builder controls, and a field build showed that a warning alone does
 * not stop an agent from spraying them across a page, so the write is
 * REJECTED unless the caller passes the explicit `allow_inline_styles: true`
 * opt-in (never to be set without the user's explicit confirmation).
 *
 * @param array<array-key, mixed> $input
 */
function divi_inline_style_gate(int $count, array $input): ?WP_Error
{
    if ($count === 0 || ($input['allow_inline_styles'] ?? false) === true) {
        return null;
    }

    return divi_error('inline_styles_not_allowed', message: sprintf(
        'This write carries %d inline style="…" attribute(s) in the content HTML. Inline styles '
        . 'bypass Divi 5\'s responsive system: they apply unchanged at every breakpoint, override '
        . 'the module\'s native typography and spacing attrs, and are invisible to the Visual '
        . 'Builder controls, so the user cannot edit or override them in Divi. Move these rules '
        . 'to the module\'s native attrs (typography via the font attrs\' <breakpoint>.value, '
        . 'spacing via module.decoration.spacing — see divi-get-module-schema) and keep the '
        . 'content HTML style-free. Only if the user has EXPLICITLY confirmed they want inline '
        . 'styles, re-call with "allow_inline_styles": true — do not set that flag on your own.',
        $count,
    ));
}

/**
 * The in-band trace attached when an inline-style write goes through WITH the
 * `allow_inline_styles` opt-in — the gate above rejected it otherwise. Keeps
 * the occurrence count in the conversation so the choice stays auditable.
 */
function divi_inline_style_warning(int $count): string
{
    return sprintf(
        'The content HTML carries inline style="…" attributes (%d found in this write). Inline '
        . 'styles bypass Divi 5\'s responsive system: they apply unchanged at every breakpoint, '
        . 'override the module\'s native typography and spacing attrs, and are invisible to the '
        . 'Visual Builder controls, so the user cannot edit or override them in Divi. Move these '
        . 'rules to the module\'s native attrs (typography via the font attrs\' <breakpoint>.value, '
        . 'spacing via module.decoration.spacing — see divi-get-module-schema) and keep the '
        . 'content HTML style-free.',
        $count,
    );
}

/**
 * True when a module-attrs object carries a dotted key (`a.b.c`) at any depth —
 * the flat responsive-leaf shape Divi 5 does not render. The write paths nest
 * these automatically (see divi_expand_dotted_keys); this detects the input so
 * the ability can tell the agent its keys were normalized to the native shape.
 *
 * @param array<array-key, mixed> $attrs
 */
function divi_attrs_have_dotted_keys(array $attrs): bool
{
    /** @var mixed $value */
    foreach ($attrs as $key => $value) {
        if (is_string($key) && str_contains($key, '.')) {
            return true;
        }
        if (is_array($value) && divi_attrs_have_dotted_keys($value)) {
            return true;
        }
    }
    return false;
}

/**
 * True when any node in a raw (pre-normalization) input forest carries dotted
 * attr keys. Used by the whole-tree write paths (set-content) to surface the
 * same nudge single-module writes get.
 *
 * @param mixed $nodes
 */
function divi_raw_nodes_have_dotted_attrs($nodes): bool
{
    if (!is_array($nodes)) {
        return false;
    }
    /** @var mixed $node */
    foreach ($nodes as $node) {
        if (!is_array($node)) {
            continue;
        }
        /** @var mixed $attrs */
        $attrs = $node['attrs'] ?? null;
        if (is_array($attrs) && divi_attrs_have_dotted_keys($attrs)) {
            return true;
        }
        if (divi_raw_nodes_have_dotted_attrs($node['children'] ?? null)) {
            return true;
        }
    }
    return false;
}

/**
 * The in-band note attached to a write whose input used dotted attr keys. Never
 * a rejection — the keys are auto-nested and render correctly — but the agent is
 * told the native shape so its next write matches what Divi 5 stores.
 */
function divi_dotted_keys_warning(): string
{
    return (
        'Some attrs were written with dotted keys (e.g. `module.decoration.spacing.tablet.value`). '
        . 'Divi 5 only reads the nested shape, so these were auto-nested for you '
        . '(`{"module":{"decoration":{"spacing":{"tablet":{"value": …}}}}}`). Pass the nested object '
        . 'directly next time — that is exactly what gets stored and what the builder reads.'
    );
}

/**
 * Normalize a module name to its fully-qualified `divi/<slug>` form. Accepts
 * both `text` and `divi/text` so callers don't have to remember the prefix.
 */
function divi_normalize_module_name(string $name): string
{
    $name = trim($name);
    if ($name === '') {
        return $name;
    }
    return str_contains($name, '/') ? $name : 'divi/' . $name;
}

/**
 * All core module metadata, keyed by `divi/<slug>` module name. Returns an
 * empty array when the registry is unavailable.
 *
 * @return array<string, array<string, mixed>>
 */
function divi_all_modules_metadata(): array
{
    if (!class_exists(ModuleRegistration::class)) {
        return [];
    }

    $by_name = [];
    foreach (ModuleRegistration::get_all_core_modules_metadata() as $meta) {
        $name = is_string($meta['name'] ?? null) ? $meta['name'] : null;
        if ($name === null || $name === '') {
            continue;
        }
        $by_name[$name] = $meta;
    }

    ksort($by_name);
    return $by_name;
}

/**
 * Metadata for a single module, or null when the module is unknown.
 *
 * @return array<string, mixed>|null
 */
function divi_module_metadata(string $name): ?array
{
    $name = divi_normalize_module_name($name);
    if ($name === '' || !class_exists(ModuleRegistration::class)) {
        return null;
    }

    // Deliberately NOT widened to the block registry. The registry does answer
    // for a module the generated table misses (the legacy-shortcode holder is
    // registered from its own package), but this node model keeps only
    // name/attrs/children: a block whose payload lives in `innerHTML` is
    // re-serialized empty, so recognizing such a module turns an honest
    // unknown-module refusal into a silent write that discards the payload.
    // Recognition has to wait until the model can carry that content.
    $meta = ModuleRegistration::get_core_module_metadata($name);
    if ($meta === [] || ($meta['name'] ?? null) !== $name) {
        return null;
    }
    return $meta;
}

/**
 * The names of the child modules a module accepts, from its `childrenName`
 * metadata. Non-string entries are dropped.
 *
 * @param array<string, mixed> $meta
 * @return list<string>
 */
function divi_children_names(array $meta): array
{
    $children = [];
    $raw = is_array($meta['childrenName'] ?? null) ? $meta['childrenName'] : [];
    /** @var mixed $child */
    foreach ($raw as $child) {
        if (!is_string($child)) {
            continue;
        }
        $children[] = $child;
    }
    return $children;
}

/**
 * Compact one module's metadata into the row shape returned by
 * `divi-list-modules`. Kept deliberately small (progressive disclosure):
 * `category` already conveys structural/child/fullwidth, so no derived flags;
 * `raw_html` and `children` appear only when they carry information.
 *
 * @param array<string, mixed> $meta
 * @return array<string, mixed>
 */
function divi_module_summary(array $meta): array
{
    $name = is_string($meta['name'] ?? null) ? $meta['name'] : '';

    $summary = [
        'name' => $name,
        'title' => is_string($meta['title'] ?? null) ? $meta['title'] : $name,
        'category' => is_string($meta['category'] ?? null) ? $meta['category'] : 'module',
    ];

    if (in_array($name, NOVAMIRA_DIVI_RAW_HTML_MODULES, strict: true)) {
        $summary['raw_html'] = true;
    }
    if (divi_meta_supports_loop($meta)) {
        $summary['loop_capable'] = true;
    }
    $children = divi_children_names($meta);
    if ($children !== []) {
        $summary['children'] = $children;
    }

    return $summary;
}

/**
 * Whether a module's metadata exposes the generic query-loop option
 * (`module.settings.advanced.loop`). Note this is Divi 5's "repeat any module"
 * loop — distinct from modules like divi/blog that run their own internal query.
 *
 * @param array<string, mixed> $meta
 */
function divi_meta_supports_loop(array $meta): bool
{
    /** @var mixed $advanced */
    $advanced = $meta['attributes']['module']['settings']['advanced'] ?? null;
    return is_array($advanced) && array_key_exists('loop', $advanced);
}

/**
 * Whether the named module supports the generic query loop.
 */
function divi_module_supports_loop(string $name): bool
{
    $meta = divi_module_metadata($name);
    return $meta !== null && divi_meta_supports_loop($meta);
}

/**
 * Wrap a list of subtype slugs/ids into Divi's loop `subTypes` shape
 * (`[{"value":"post"}, …]`).
 *
 * @param list<string> $slugs
 * @return list<array{value: string}>
 */
function divi_loop_subtypes(array $slugs): array
{
    $subtypes = [];
    foreach ($slugs as $slug) {
        $subtypes[] = ['value' => $slug];
    }
    return $subtypes;
}

/**
 * Build a partial loop `value` patch from the optional edit-loop inputs,
 * including only the keys the caller provided.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function divi_loop_partial_from_input(array $input): array
{
    $value = [];
    foreach (['query_type' => 'queryType', 'order_by' => 'orderBy', 'order' => 'order'] as $in => $out) {
        if (!array_key_exists($in, $input)) {
            continue;
        }
        $value[$out] = divi_string_arg($input, $in);
    }
    if (array_key_exists('sub_types', $input)) {
        $value['subTypes'] = divi_loop_subtypes(divi_string_list_arg($input, key: 'sub_types'));
    }
    if (array_key_exists('per_page', $input)) {
        $value['postPerPage'] = divi_clamp_per_page(divi_int_arg_or_null($input, key: 'per_page') ?? 10);
    }
    if (array_key_exists('offset', $input)) {
        $value['postOffset'] = max(0, divi_int_arg_or_null($input, key: 'offset') ?? 0);
    }
    return $value;
}

/**
 * Clamp a loop's results-per-page to a finite, sane range. The input schema
 * already bounds it, but execute() callers bypass schema validation and the
 * oversized query fires later in Divi's loop renderer — so bound it here too.
 */
function divi_clamp_per_page(int $per_page): int
{
    return max(1, min(NOVAMIRA_DIVI_MAX_PER_PAGE, $per_page));
}

/**
 * The `module` section of the global-preset store, keyed by module name. Each
 * entry is `['default' => <id>, 'items' => [<id> => <preset>]]`. Empty when no
 * presets exist or the API is unavailable.
 *
 * @return array<array-key, mixed>
 */
function divi_global_module_presets(): array
{
    if (!class_exists(GlobalPreset::class)) {
        return [];
    }
    $data = GlobalPreset::get_data();
    return is_array($data['module'] ?? null) ? $data['module'] : [];
}

/**
 * Generate a short unique loop id (used by Divi as the per-loop pagination
 * query-var name).
 */
function divi_generate_loop_id(): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyz0123456789';
    $id = '';
    for ($i = 0; $i < 8; $i++) {
        $id .= $chars[random_int(0, strlen($chars) - 1)];
    }
    return 'loop-' . $id;
}

/**
 * Generate a fresh RFC-4122 v4 UUID (lowercase, hyphenated). Used to mint Divi
 * global-color (`gcid-<uuid>`) and global-variable (`gvid-<uuid>`) ids.
 */
function divi_generate_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, offset: 0, length: 8),
        substr($hex, offset: 8, length: 4),
        substr($hex, offset: 12, length: 4),
        substr($hex, offset: 16, length: 4),
        substr($hex, offset: 20, length: 12),
    );
}

/**
 * Resolve the module at `$address` and confirm it supports the query loop.
 * Returns the node, or a WP_Error (404 missing / 422 not loop-capable).
 *
 * @return array<string, mixed>|WP_Error
 */
function divi_loop_node_or_error(WP_Post $post, string $address)
{
    $node = divi_tree_get(divi_parse_tree($post->post_content), $address);
    if ($node === null) {
        return divi_error('not_found', message: sprintf('No module at address "%s".', $address), status: 404);
    }
    $name = is_string($node['name'] ?? null) ? $node['name'] : '';
    if (!divi_module_supports_loop($name)) {
        return divi_error(
            'not_loop_capable',
            message: sprintf(
                'Module "%s" does not support a query loop. Use novamira/divi-list-modules (loop_capable) to find ones that do.',
                $name,
            ),
            status: 422,
        );
    }
    return $node;
}

/**
 * Flatten a module's `attributes` schema into the list of settable
 * content/configuration fields, keyed by their canonical `attrName` path
 * (e.g. `content.innerContent`). Universal style groups (background, spacing,
 * border, fonts, …) live under `module.decoration.*` for every module and are
 * intentionally not enumerated here — see `divi_style_groups()`.
 *
 * Each field carries whether it accepts dynamic content (`$variables(...)`),
 * which the dynamic-content abilities (S3) rely on.
 *
 * @param array<string, mixed> $attributes
 * @return list<array<string, mixed>>
 */
function divi_flatten_fields(array $attributes): array
{
    /** @var array<string, array<string, mixed>> $by_attr */
    $by_attr = [];
    divi_collect_fields($attributes, $by_attr);
    return array_values($by_attr);
}

/**
 * Recursive worker for {@see divi_flatten_fields()}. Records any node that
 * declares a string `attrName`, deduping by that path.
 *
 * @param mixed $node
 * @param array<string, array<string, mixed>> $by_attr
 */
function divi_collect_fields($node, array &$by_attr): void
{
    if (!is_array($node)) {
        return;
    }

    /** @var mixed $attr_name */
    /** @var mixed $attr_name */
    $attr_name = $node['attrName'] ?? null;
    // Decoration paths are universal styling, surfaced by get-style-schema — keep
    // them out of the content/config "fields" list.
    if (
        is_string($attr_name)
        && $attr_name !== ''
        && !str_contains($attr_name, '.decoration.')
        && !array_key_exists($attr_name, $by_attr)
    ) {
        $by_attr[$attr_name] = divi_field_from_node($attr_name, $node);
    }

    /** @var mixed $child */
    foreach ($node as $child) {
        if (!is_array($child)) {
            continue;
        }
        divi_collect_fields($child, $by_attr);
    }
}

/**
 * Build the compact field descriptor for one schema node carrying an attrName.
 * Only the keys useful to an agent setting the field are retained.
 *
 * @param array<array-key, mixed> $node
 * @return array<string, mixed>
 */
function divi_field_from_node(string $attr_name, array $node): array
{
    $field = ['attrName' => $attr_name];
    foreach (['label', 'description', 'category'] as $key) {
        /** @var mixed $value */
        $value = $node[$key] ?? null;
        if (!is_string($value) || $value === '') {
            continue;
        }
        $field[$key] = $value;
    }
    $field['dynamic'] = isset($node['features']['dynamicContent']) && $node['features']['dynamicContent'] !== false;
    /** @var mixed $component */
    $component = $node['component']['name'] ?? null;
    if (is_string($component)) {
        $field['component'] = $component;
    }
    return $field;
}

/**
 * Map of a module's non-decoration (content/config) attribute paths to their
 * sub-value field names, from the preset-attrs mapping. This is the complete
 * source (the metadata-only flatten misses fields like a blurb's title or an
 * image's src), and the sub-field list reveals structured value shapes (e.g.
 * `button.innerContent` → {text, linkUrl}). Editor-only `module.meta.*` is
 * skipped; decoration paths belong to get-style-schema.
 *
 * @return array<string, list<string>>
 */
function divi_preset_content_subfields(string $module): array
{
    if (!class_exists(Conversion::class)) {
        return [];
    }
    $by_attr = [];
    foreach (Conversion::get_preset_attrs_mapping($module) as $key => $def) {
        $attr = is_string($def['attrName'] ?? null) ? $def['attrName'] : explode('__', $key, limit: 2)[0];
        if (str_contains($key, '.decoration.')) {
            // Decoration paths are styling (→ get-style-schema) and are skipped —
            // EXCEPT content that Divi mis-namespaces under decoration, e.g. a
            // button's text/linkUrl live at `<x>.decoration.<x>.innerContent` yet
            // the real write path is `<x>.innerContent`. Collapse those so the
            // schema surfaces them instead of hiding the defining content fields.
            if (!str_contains($attr, 'innerContent')) {
                continue;
            }
            $attr = (string) preg_replace('/\.decoration\.[^.]+/', replacement: '', subject: $attr);
        }
        if (str_starts_with($attr, 'module.meta.')) {
            continue;
        }
        $by_attr[$attr] ??= [];
        $sub = is_string($def['subName'] ?? null) ? $def['subName'] : '';
        if ($sub !== '' && !in_array($sub, $by_attr[$attr], strict: true)) {
            $by_attr[$attr][] = $sub;
        }
    }
    return $by_attr;
}

/**
 * The complete settable content/config field list for a module: every
 * non-decoration attrName (from the preset map) with its value sub-fields,
 * enriched with label/description/dynamic from the metadata where available.
 *
 * @param array<string, mixed> $attributes  The module's metadata `attributes`.
 * @return list<array<string, mixed>>
 */
function divi_module_fields(string $module, array $attributes): array
{
    $meta = [];
    foreach (divi_flatten_fields($attributes) as $field) {
        $meta[(string) $field['attrName']] = $field;
    }

    $subfields = divi_preset_content_subfields($module);

    $names = array_keys($subfields);
    foreach (array_keys($meta) as $attr) {
        if (in_array($attr, $names, strict: true)) {
            continue;
        }
        $names[] = $attr;
    }
    sort($names);

    $fields = [];
    foreach ($names as $attr) {
        $fields[] = divi_merge_field($attr, $meta[$attr] ?? null, $subfields[$attr] ?? []);
    }
    return $fields;
}

/**
 * Build one get-module-schema field row from its attrName, optional metadata
 * enrichment, and its value sub-fields.
 *
 * @param array<string, mixed>|null $meta
 * @param list<string> $subfields
 * @return array<string, mixed>
 */
function divi_merge_field(string $attr, ?array $meta, array $subfields): array
{
    $field = ['attrName' => $attr];
    if ($meta !== null) {
        foreach (['label', 'description', 'category', 'component'] as $key) {
            if (!isset($meta[$key])) {
                continue;
            }
            $field[$key] = $meta[$key];
        }
        if (isset($meta['dynamic'])) {
            $field['dynamic'] = $meta['dynamic'];
        }
    }
    if ($subfields !== []) {
        $field['value_fields'] = $subfields;
    }
    return $field;
}

/**
 * The universal decoration (style) groups a module exposes, derived from the
 * `settings.decoration` map on each top-level attribute group. These map to
 * `<group>.decoration.<name>` attribute paths (background, spacing, border,
 * font, sizing, animation, position, …) shared across modules.
 *
 * @param array<string, mixed> $attributes
 * @return list<string>
 */
function divi_style_groups(array $attributes): array
{
    /** @var array<string, true> $groups */
    $groups = [];
    /** @var mixed $group */
    foreach ($attributes as $group) {
        /** @var mixed $decoration */
        $decoration = is_array($group) ? $group['settings']['decoration'] ?? null : null;
        if (!is_array($decoration)) {
            continue;
        }
        foreach (array_keys($decoration) as $key) {
            if (!is_string($key)) {
                continue;
            }
            $groups[$key] = true;
        }
    }
    $names = array_keys($groups);
    sort($names);
    return $names;
}

/**
 * Resolve a post id (or its `post_id` alias) to a WP_Post, rejecting the
 * statuses `get_post()` silently accepts but that must never be treated as a
 * live builder document (trash, autosave/auto-draft, revisions).
 *
 * @param mixed $id
 * @return WP_Post|WP_Error
 */
function divi_post_or_error($id)
{
    if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
        return divi_error('invalid_input', message: 'A numeric post id is required.', status: 400);
    }

    /** @var mixed $post */
    $post = get_post((int) $id);
    if (!$post instanceof WP_Post) {
        return divi_error('not_found', sprintf('No post found with id %d.', (int) $id), status: 404);
    }

    if ($post->post_type === 'revision') {
        return divi_error('invalid_post', sprintf('Post %d is a revision, not an editable document.', $post->ID));
    }
    if (in_array($post->post_status, ['trash', 'auto-draft'], strict: true)) {
        return divi_error('invalid_post', sprintf(
            'Post %d is in "%s" status and cannot be read as a layout.',
            $post->ID,
            $post->post_status,
        ));
    }
    if (!in_array($post->post_type, divi_writable_post_types(), strict: true)) {
        return divi_error('invalid_post', sprintf(
            'Post %d is a "%s", which does not hold a Divi layout. Divi content can only be written to '
            . 'builder post types (page, post, project, …), Divi Library items, or Theme Builder layouts.',
            $post->ID,
            $post->post_type,
        ));
    }

    return $post;
}

/**
 * Post types that can legitimately hold a Divi 5 layout: the builder post types
 * Divi itself enables (page/post/project, plus any a site adds via filter),
 * Divi Library items, and Theme Builder templates/layout areas. Everything else
 * — attachments, nav menu items, changesets, and other non-content types —
 * must not accept builder writes: doing so corrupts that record and marks it
 * builder-built with no way to render.
 *
 * @return list<string>
 */
function divi_writable_post_types(): array
{
    $types = ['page', 'post', 'project'];
    if (function_exists('et_builder_get_builder_post_types')) {
        /** @var mixed $builder_types */
        $builder_types = et_builder_get_builder_post_types();
        if (is_array($builder_types)) {
            /** @var mixed $type */
            foreach ($builder_types as $type) {
                if (!is_string($type)) {
                    continue;
                }
                $types[] = $type;
            }
        }
    }
    // Divi Library items and Theme Builder templates/layout areas are ordinary
    // Divi posts edited through the same content abilities.
    $types[] = 'et_pb_layout';
    $types[] = 'et_template';
    $types[] = 'et_header_layout';
    $types[] = 'et_body_layout';
    $types[] = 'et_footer_layout';

    return array_values(array_unique($types));
}

/**
 * Resolve the post id (or its `post_id` alias) from an ability input into a
 * WP_Post, or a WP_Error. Shared by the write abilities.
 *
 * @param array<string, mixed> $input
 * @return WP_Post|WP_Error
 */
function divi_resolve_post(array $input)
{
    return divi_post_or_error($input['post'] ?? $input['post_id'] ?? null);
}

/**
 * Read a string argument from an ability input, defaulting when absent or not a
 * string.
 *
 * @param array<string, mixed> $input
 */
function divi_string_arg(array $input, string $key, string $default = ''): string
{
    return is_string($input[$key] ?? null) ? $input[$key] : $default;
}

/**
 * Read an integer argument from an ability input, or null when absent / not an
 * integer.
 *
 * @param array<string, mixed> $input
 */
function divi_int_arg_or_null(array $input, string $key): ?int
{
    /** @var mixed $value */
    $value = $input[$key] ?? null;
    if (is_int($value)) {
        return $value;
    }
    // MCP/LLM clients routinely send JSON-string numbers ("20"); accept them
    // rather than silently dropping to the default (0).
    if (is_string($value) && is_numeric($value)) {
        return (int) $value;
    }
    return null;
}

/**
 * Read a list-of-strings argument from an ability input (non-string entries
 * dropped); empty list when absent.
 *
 * @param array<string, mixed> $input
 * @return list<string>
 */
function divi_string_list_arg(array $input, string $key): array
{
    /** @var mixed $raw */
    $raw = $input[$key] ?? null;
    if (!is_array($raw)) {
        return [];
    }
    $list = [];
    /** @var mixed $item */
    foreach ($raw as $item) {
        if (!is_string($item)) {
            continue;
        }
        $list[] = $item;
    }
    return $list;
}

/**
 * Truncate a string to a byte budget, returning the (possibly shortened) value
 * and whether truncation happened.
 *
 * @return array{0: string, 1: bool}
 */
function divi_truncate_bytes(string $value, int $max_bytes): array
{
    if ($max_bytes <= 0 || strlen($value) <= $max_bytes) {
        return [$value, false];
    }
    // mb_strcut (not substr) so a multibyte character is never split — a half
    // character is invalid UTF-8 and makes json_encode of the whole response
    // fail, silently returning nothing to the caller.
    return [mb_strcut($value, start: 0, length: $max_bytes, encoding: 'UTF-8'), true];
}

/**
 * The valid dynamic-content option ids for a post context (keys of the Divi
 * options registry), e.g. `post_title`, `post_meta_key`, `site_title`. Empty
 * when the registry is unavailable.
 *
 * @return list<string>
 */
function divi_dynamic_option_ids(?int $post_id): array
{
    if (!class_exists(DynamicContentOptions::class)) {
        return [];
    }
    $options = DynamicContentOptions::get_options((int) $post_id, 'edit');
    $ids = [];
    foreach (array_keys($options) as $id) {
        $ids[] = $id;
    }
    return $ids;
}

/**
 * Extract the `settings` argument of a dynamic-content binding as a flat map of
 * string values (Divi sanitizes settings as strings; non-scalars are dropped).
 *
 * @param array<string, mixed> $input
 * @return array<string, string>
 */
function divi_dynamic_settings_arg(array $input): array
{
    /** @var mixed $raw */
    $raw = $input['settings'] ?? null;
    if (!is_array($raw)) {
        return [];
    }
    $settings = [];
    /** @var mixed $value */
    foreach ($raw as $key => $value) {
        if (!is_string($key) || !is_scalar($value)) {
            continue;
        }
        if (is_bool($value)) {
            $settings[$key] = $value ? '1' : '';
            continue;
        }
        $settings[$key] = (string) $value;
    }
    return $settings;
}

/**
 * Build the Divi 5 dynamic-content value a module field stores. Divi resolves
 * dynamic content at render time from an inline `$variable(<json>)$` token in
 * the field value (a bare JSON object renders literally and is NOT resolved),
 * where json is `{"type":"content","value":{"name":<source>,"settings":<…>}}`.
 * Empty settings serialize as an object (`{}`), matching Divi.
 *
 * @param array<string, string> $settings
 */
function divi_build_dynamic_value(string $source, array $settings): string
{
    $value = [
        'type' => 'content',
        'value' => [
            'name' => $source,
            'settings' => $settings === [] ? new \stdClass() : $settings,
        ],
    ];
    return '$variable(' . (string) wp_json_encode($value) . ')$';
}

/**
 * Strip every inline `$variable(<json>)$` dynamic token from a string, keeping
 * the surrounding literal text. The token's JSON is matched by brace balance
 * (respecting quoted strings and escapes), so a literal `)$` inside the settings
 * (e.g. an `after` separator) does not truncate the match — a lazy regex would.
 */
function divi_strip_dynamic_tokens(string $value): string
{
    $needle = '$variable(';
    $out = '';
    $i = 0;
    while (true) {
        $pos = strpos($value, $needle, $i);
        if ($pos === false) {
            $out .= substr($value, $i);
            break;
        }
        $out .= substr($value, $i, $pos - $i);
        $json_start = $pos + strlen($needle);
        $json_end = divi_match_json_end($value, $json_start);
        // A real token is `$variable(` + a JSON object + `)$`. Anything else is
        // left verbatim and scanning resumes just past the `$variable(`.
        if ($json_end !== null && substr($value, $json_end, length: 2) === ')$') {
            $i = $json_end + 2;
            continue;
        }
        $out .= $needle;
        $i = $json_start;
    }
    return $out;
}

/**
 * Count well-formed inline `$variable(<json>)$` dynamic tokens in a string,
 * matching the JSON by brace balance and requiring the `)$` terminator — so a
 * bare literal mention of `$variable(` (docs, code) is not miscounted.
 */
function divi_count_inline_tokens(string $value): int
{
    $needle = '$variable(';
    $count = 0;
    $i = 0;
    while (true) {
        $pos = strpos($value, $needle, $i);
        if ($pos === false) {
            return $count;
        }
        $json_start = $pos + strlen($needle);
        $json_end = divi_match_json_end($value, $json_start);
        if ($json_end !== null && substr($value, $json_end, length: 2) === ')$') {
            $count++;
            $i = $json_end + 2;
            continue;
        }
        $i = $json_start;
    }
}

/**
 * Index just past the JSON object that starts at `$start` (which must be a `{`),
 * or null if it is not a balanced object. String contents and escapes are
 * honored so braces inside quoted values do not affect the balance.
 */
function divi_match_json_end(string $s, int $start): ?int
{
    $len = strlen($s);
    if ($start >= $len || $s[$start] !== '{') {
        return null;
    }
    $depth = 0;
    $in_string = false;
    $escaped = false;
    for ($i = $start; $i < $len; $i++) {
        $c = $s[$i];
        if ($in_string) {
            if ($escaped) {
                $escaped = false;
                continue;
            }
            if ($c === '\\') {
                $escaped = true;
                continue;
            }
            if ($c === '"') {
                $in_string = false;
            }
            continue;
        }
        if ($c === '"') {
            $in_string = true;
            continue;
        }
        if ($c === '{') {
            $depth++;
            continue;
        }
        if ($c === '}') {
            $depth--;
            if ($depth === 0) {
                return $i + 1;
            }
        }
    }
    return null;
}

/**
 * Count dynamic-content bindings in an attributes subtree. Recognizes both
 * forms Divi 5 uses: whole-field bindings (a value string that is a
 * `{"type":"content","value":{"name":…}}` payload) and inline `$variable(...)`
 * tokens embedded in free text.
 *
 * @param mixed $value
 */
function divi_count_dynamic($value): int
{
    if (is_string($value)) {
        // Count only well-formed `$variable(<json>)$` tokens — a literal mention
        // like "use $variable( to bind" in prose/code is not a binding.
        $inline = divi_count_inline_tokens($value);
        if ($inline > 0) {
            return $inline;
        }
        // A whole-field binding is a value that *is* the dynamic JSON, not merely
        // a string that mentions those substrings (e.g. doc text in a code module).
        return str_starts_with(ltrim($value), '{"type":"content"') ? 1 : 0;
    }
    if (!is_array($value)) {
        return 0;
    }
    $count = 0;
    /** @var mixed $child */
    foreach ($value as $child) {
        $count += divi_count_dynamic($child);
    }
    return $count;
}

/**
 * The number of interactions on a module, from the Divi 5 shape
 * `module.decoration.interactions.desktop.value.interactions` (interactions are
 * desktop-only). Surfaced by get-content so an agent sees a module carries
 * motion without paying for include_attrs — parallel to loop_enabled /
 * dynamic_bindings.
 *
 * @param array<array-key, mixed> $attrs
 */
function divi_attrs_interaction_count(array $attrs): int
{
    /** @var mixed $list */
    $list = $attrs['module']['decoration']['interactions']['desktop']['value']['interactions'] ?? null;
    return is_array($list) ? count($list) : 0;
}

/**
 * Whether a module's stored attributes enable a query loop. Mirrors the Divi 5
 * shape `module.advanced.loop.<breakpoint>.value.enable`.
 *
 * @param array<array-key, mixed> $attrs
 */
function divi_attrs_have_loop(array $attrs): bool
{
    /** @var mixed $loop */
    $loop = $attrs['module']['advanced']['loop'] ?? null;
    if (!is_array($loop)) {
        return false;
    }
    /** @var mixed $breakpoint */
    foreach ($loop as $breakpoint) {
        /** @var mixed $enable */
        $enable = is_array($breakpoint) ? $breakpoint['value']['enable'] ?? null : null;
        if ($enable === '1' || $enable === true || $enable === 'on') {
            return true;
        }
    }
    return false;
}
