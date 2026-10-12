<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the per-post Blocksy options (Sprint 6):
 * `novamira/blocksy-get-page-options` / `novamira/blocksy-edit-page-options`.
 *
 * Storage (Blocksy 2.1.52, verified from the installed theme):
 *  - ONE post meta, `blocksy_post_meta_options`, holding a single assoc
 *    array of option id => value for the post (inc/meta-boxes.php:10
 *    blocksy_get_post_options() reads `get_post_meta($id, key)[0]`; the
 *    editor's REST field `blocksy_meta` and the classic metabox save
 *    write it with update_post_meta, admin/helpers/meta-boxes.php:98-131
 *    and :216-234). Like the customizer, defaults are NEVER stored — an
 *    absent key means "inherit" / the option-file default.
 *  - The per-post schema is what the block editor loads
 *    (admin/blocks-editor.php:20-53): `inc/options/meta/<post_type>.php`
 *    for `post` and `page`, `meta/blog.php` for the posts page and the
 *    WooCommerce shop page (only disable_header/disable_footer — and
 *    blocksy_get_post_options() itself drops every other stored key on
 *    those two pages, inc/meta-boxes.php:73-92), `meta/default.php` (with
 *    the post-type object) for every public custom post type Blocksy
 *    supports (inc/integrations/custom-post-types.php:32 — `product` and
 *    a deny list of builder/plugin CPTs are excluded and get NO per-post
 *    options), `meta/<post_type>` (null — no such file) for the post
 *    types integrations add through
 *    `blocksy:editor:post_types_for_rest_field`, then the
 *    `blocksy:editor:post_meta_options` filter (Companion/integration
 *    hooks, evaluated with the target post as the global $post exactly
 *    like the editor — it is what gives e.g. `tribe_events` its schema).
 *    Keys, types, choices and defaults all come from those files at
 *    call time — nothing is hardcoded here.
 *  - `styles_descriptor` is a theme-managed CACHE key inside the same
 *    array: the per-post dynamic CSS (hero/content-style/background
 *    output) plus the Google-font list, stamped with the CSS version
 *    (inc/dynamic-css.php:220-300, version 7 on 2.1.52). The front end
 *    regenerates it only when the stamped version differs, so a write
 *    that changed option values but left a current-version descriptor in
 *    place would render STALE per-post CSS. The editor's REST save
 *    recomputes it on every write (admin/helpers/meta-boxes.php:109-123)
 *    — the edit ability does the same.
 */

/** The theme's per-post options meta key (one row per post, one array). */
const BLY_PO_META_KEY = 'blocksy_post_meta_options';

/** The theme-managed per-post CSS cache key inside the options array. */
const BLY_PO_STYLES_KEY = 'styles_descriptor';

/** Schema kinds — which option file the editor loads for the post. */
const BLY_PO_SCHEMA_POST = 'post';

const BLY_PO_SCHEMA_PAGE = 'page';

const BLY_PO_SCHEMA_BLOG = 'blog';

const BLY_PO_SCHEMA_DEFAULT = 'default';

/**
 * A post type the theme ships no option FILE for, but which a Blocksy
 * integration registers for the editor panel through the
 * `blocksy:editor:post_types_for_rest_field` filter and supplies the
 * schema for through `blocksy:editor:post_meta_options` (e.g. The Events
 * Calendar's `tribe_events`, inc/integrations/tribe-events.php:32-52 —
 * deny-listed from the supported CPTs, meta/default re-used through the
 * filter).
 */
const BLY_PO_SCHEMA_INTEGRATION = 'integration';

/**
 * Companion builder post types whose "per-post options" are display
 * conditions / hook positions, not page options (framework/premium/
 * features/content-blocks.php:10, extensions/woocommerce-extra/features/
 * custom-thank-you-page/feature.php:10, custom-tabs/feature.php:10,
 * size-guide/feature.php:12 — each removes itself from the supported
 * CPT list and re-registers through the editor filters with a
 * `blocksy-display-condition` schema). Content Blocks have their own
 * abilities; the WooCommerce-extra builders are out of this surface.
 */
const BLY_PO_BUILDER_TYPES = [BLY_CB_POST_TYPE, 'ct_thank_you_page', 'ct_product_tab', 'ct_size_guide'];

/** Maximum option ids accepted in one edit / one `ids` filter. */
const BLY_PO_MAX_IDS = 60;

/** Maximum unmodelled key names echoed by get-page-options. */
const BLY_PO_MAX_EXTRA_KEYS = 30;

// ---------------------------------------------------------------------------
// Post resolution
// ---------------------------------------------------------------------------

/**
 * Resolve a user-supplied id to an editable content post, or WP_Error.
 * Revisions/autosaves are not content; Content Blocks have their own
 * surface (their options array holds conditions/positions, not page
 * options) and are redirected there.
 */
function bly_po_get_post(mixed $input_id): WP_Post|WP_Error
{
    if (!is_int($input_id) || $input_id < 1) {
        return bly_error('bly_invalid_input', message: '`post_id` is required and must be a positive integer post id.');
    }
    /** @var WP_Post|null $post */
    $post = get_post($input_id);
    if (!$post instanceof WP_Post || $post->post_status === 'auto-draft' || $post->post_type === 'revision') {
        return bly_error('bly_not_found', sprintf('No post with id %d exists.', $input_id));
    }
    if ($post->post_type === BLY_CB_POST_TYPE) {
        return bly_error('bly_invalid_input', sprintf(
            'Post %d is a Blocksy Content Block, not a content post — its options are display conditions and hook positions, managed by blocksy-get-content-block / blocksy-edit-content-block-conditions / blocksy-edit-content-block-position.',
            $input_id,
        ));
    }
    if (in_array($post->post_type, BLY_PO_BUILDER_TYPES, strict: true)) {
        return bly_error('bly_invalid_input', sprintf(
            'Post %d is of type "%s", a Blocksy Companion builder post type whose options are display conditions, not page options — it is outside this surface.',
            $input_id,
            $post->post_type,
        ));
    }
    return $post;
}

// ---------------------------------------------------------------------------
// Per-post schema resolution (mirrors admin/blocks-editor.php)
// ---------------------------------------------------------------------------

/**
 * Which schema kind the editor would load for this post — the exact
 * applicability of admin/blocks-editor.php:20-53 + the REST field
 * registration of admin/helpers/meta-boxes.php:85-100:
 *
 *  - the posts page and the WooCommerce shop page take the blog schema
 *    regardless of post type (blocksy_get_post_options() enforces that at
 *    read time too);
 *  - `post` / `page` take their own option file;
 *  - the theme's supported custom post types take meta/default;
 *  - any other type a Blocksy integration lists through
 *    `blocksy:editor:post_types_for_rest_field` (the REST field the
 *    editor saves through is registered for exactly ['post', 'page'] +
 *    that filter + the supported CPTs) takes whatever file exists for
 *    it (none, usually) and the `blocksy:editor:post_meta_options` filter
 *    — `integration`; whether that yields a schema is decided AFTER the
 *    filter ran (bly_po_option_tree), never before;
 *  - null for everything else (attachments, `product`, the deny-listed
 *    builder/plugin CPTs, non-public types): the editor registers no
 *    REST field for them, so no per-post save path exists.
 *
 * @return array{kind: string, special: string|null}|null
 */
function bly_po_schema_kind(WP_Post $post): ?array
{
    if ((int) get_option('page_for_posts') === $post->ID) {
        return ['kind' => BLY_PO_SCHEMA_BLOG, 'special' => 'posts_page'];
    }
    if ((int) get_option('woocommerce_shop_page_id') === $post->ID) {
        return ['kind' => BLY_PO_SCHEMA_BLOG, 'special' => 'shop_page'];
    }
    if ($post->post_type === 'post' || $post->post_type === 'page') {
        return ['kind' => $post->post_type, 'special' => null];
    }
    if (in_array($post->post_type, bly_po_supported_cpts(), strict: true)) {
        return ['kind' => BLY_PO_SCHEMA_DEFAULT, 'special' => null];
    }
    if (in_array($post->post_type, bly_po_rest_field_post_types(), strict: true)) {
        return ['kind' => BLY_PO_SCHEMA_INTEGRATION, 'special' => null];
    }
    return null;
}

/**
 * The post types a Blocksy integration adds to the editor's REST field
 * through `blocksy:editor:post_types_for_rest_field` (Blocksy_Meta_Boxes::
 * init_taxonomies seeds ['post', 'page'] and appends the supported CPTs
 * afterwards — those are resolved separately here). Non-string entries
 * are dropped; the builder deny list is never consulted here (its types
 * are refused earlier, bly_po_get_post).
 *
 * @return list<string>
 */
function bly_po_rest_field_post_types(): array
{
    /** @var mixed $types */
    $types = apply_filters('blocksy:editor:post_types_for_rest_field', ['post', 'page']);
    if (!is_array($types)) {
        return [];
    }
    $out = [];
    /** @var mixed $type */
    foreach ($types as $type) {
        if (!is_string($type) || $type === '' || in_array($type, $out, strict: true)) {
            continue;
        }
        $out[] = $type;
    }
    return $out;
}

/**
 * The custom post types Blocksy gives per-post options to — the theme's
 * own supported list (public, non-builtin, minus its deny list, filtered
 * through `blocksy:custom_post_types:supported_list`). Empty when the
 * manager is unavailable.
 *
 * @return list<string>
 */
function bly_po_supported_cpts(): array
{
    if (!function_exists('blocksy_manager')) {
        return [];
    }
    $manager = blocksy_manager();
    $post_types = $manager->post_types ?? null;
    if (!$post_types instanceof \Blocksy\CustomPostTypes) {
        return [];
    }
    /** @var mixed $supported */
    $supported = $post_types->get_supported_post_types();
    if (!is_array($supported)) {
        return [];
    }
    $out = [];
    /** @var mixed $type */
    foreach ($supported as $type) {
        if (!is_string($type) || $type === '') {
            continue;
        }
        $out[] = $type;
    }
    return $out;
}

/**
 * The resolved per-post option tree for this post — the editor's
 * `post_options` (admin/blocks-editor.php:20-53): the kind's option file
 * (meta/default gets the post-type object; an `integration` kind loads
 * `meta/<post_type>`, usually absent → null, exactly like the editor),
 * then the `blocksy:editor:post_meta_options` filter evaluated with the
 * target post pinned as the global $post (the filter callbacks read it,
 * exactly as in the editor request). "No per-post options" is decided
 * on the FILTERED result — a filter-supplied schema counts. WP_Error
 * when the post type has no per-post options, or when the tree cannot
 * be resolved (preload failure, a throwing option file / filter).
 *
 * `$kind` is the schema kind already resolved by the caller
 * (bly_po_schema_kind) — resolved ONCE per schema resolution and passed
 * down, never re-derived here: resolving it fires the integration
 * filters (`blocksy:editor:post_types_for_rest_field`, the supported-CPT
 * list filter), and a stateful filter that answers differently on a
 * second call would otherwise change the applicability between the two
 * decisions of one read/write. Null = resolve here (direct callers).
 *
 * @param array{kind: string, special: string|null}|null $kind
 * @return array<array-key, mixed>|WP_Error
 */
function bly_po_option_tree(WP_Post $post, ?array $kind = null): array|WP_Error
{
    $kind ??= bly_po_schema_kind($post);
    if ($kind === null) {
        return bly_po_no_options_error($post);
    }

    $preload = bly_preload_customizer_sync();
    if ($preload instanceof WP_Error) {
        return $preload;
    }

    /** @var mixed $previous_global */
    $previous_global = $GLOBALS['post'] ?? null;
    $GLOBALS['post'] = $post;
    try {
        $pass_inside = [];
        // An integration kind loads `meta/<post_type>` like the editor does
        // (blocks-editor.php:26) — null when no such file ships — and hands
        // THAT to the filter.
        $file = $kind['kind'] === BLY_PO_SCHEMA_INTEGRATION ? $post->post_type : $kind['kind'];
        if ($kind['kind'] === BLY_PO_SCHEMA_DEFAULT) {
            $object = get_post_type_object($post->post_type);
            if ($object === null) {
                return bly_po_no_options_error($post);
            }
            $pass_inside = ['post_type' => $object];
        }
        /** @var mixed $tree */
        $tree = blocksy_get_options('meta/' . $file, $pass_inside);
        /** @var mixed $tree */
        $tree = apply_filters('blocksy:editor:post_meta_options', $tree, $post->post_type);
    } catch (\Throwable $e) {
        // Third-party exception text stays server-side (REST payloads
        // reach agents); the honest outcome is "schema undetermined".
        error_log(sprintf(
            'novamira-pro blocksy: per-post option tree failed for post %d (%s): %s',
            $post->ID,
            $post->post_type,
            $e->getMessage(),
        ));
        return bly_schema_error(
            'the per-post option files or a blocksy:editor:post_meta_options filter threw — details in the server error log',
        );
    } finally {
        $GLOBALS['post'] = $previous_global;
    }

    if (!is_array($tree) || $tree === []) {
        return bly_po_no_options_error($post);
    }
    return $tree;
}

/** WP_Error for a post whose type carries no Blocksy per-post options. */
function bly_po_no_options_error(WP_Post $post): WP_Error
{
    return bly_error('bly_invalid_input', sprintf(
        'Post %d is of type "%s", which has no Blocksy per-post options on this install (the theme ships them for posts, pages, the posts/shop page, its supported public custom post types and the post types its integrations register for the editor panel — not for products, attachments or builder/plugin post types).',
        $post->ID,
        bly_ext_clean_text($post->post_type, BLY_EXT_MAX_NAME_BYTES),
    ));
}

/**
 * Flat id-indexed schema of the post's per-post options (same row shape
 * as the customizer index — bly_walk_options), plus the schema kind. The
 * tree is walked with the SAME walker the customizer index uses, so
 * panels/tabs/conditions/layers flatten identically; a tree that yields
 * no settings is a schema failure, not an empty success.
 *
 * @return array{kind: string, special: string|null, index: array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}>}|WP_Error
 */
function bly_po_schema(WP_Post $post): array|WP_Error
{
    // The kind is resolved exactly once and handed to the tree loader —
    // each integration filter fires once per resolution.
    $kind = bly_po_schema_kind($post);
    if ($kind === null) {
        return bly_po_no_options_error($post);
    }
    $tree = bly_po_option_tree($post, $kind);
    if ($tree instanceof WP_Error) {
        return $tree;
    }
    $index = [];
    bly_walk_options($index, $tree, []);
    if ($index === []) {
        return bly_schema_error(sprintf(
            'the resolved per-post option tree for post type "%s" contains no settings',
            $post->post_type,
        ));
    }
    return ['kind' => $kind['kind'], 'special' => $kind['special'], 'index' => $index];
}

// ---------------------------------------------------------------------------
// Stored values + resolution
// ---------------------------------------------------------------------------

/**
 * The post's stored options array, read fresh from the FIRST meta row
 * (the one the theme consumes) — bly_cb_read_atts, the same reader the
 * content-block surface uses on this meta key.
 *
 * @return array<array-key, mixed>
 */
function bly_po_read_stored(int $post_id): array
{
    return bly_cb_read_atts($post_id);
}

/**
 * Whether ANY `blocksy_post_meta_options` row exists for the post — a
 * raw row check, independent of what it holds. Distinct from "has
 * stored options": the theme's own save paths skip the update when the
 * sanitized array is empty (admin/helpers/meta-boxes.php:125-131 and
 * :227-233), but an import, a migration, a direct update_post_meta() or
 * a third-party write can leave a row holding `a:0:{}` — such a post
 * HAS a meta row and stores NO options, and the two must not be
 * conflated ("no meta at all" vs "meta present, nothing overridden").
 * get_row, not get_var: wpdb::get_var folds a stored '' into null.
 */
function bly_po_meta_row_exists(int $post_id): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    // @mago-expect analysis:possibly-invalid-argument
    $sql = $wpdb->prepare(
        "SELECT meta_id FROM {$wpdb->postmeta} WHERE post_id = %d AND meta_key = %s ORDER BY meta_id ASC LIMIT 1",
        $post_id,
        BLY_PO_META_KEY,
    );
    if (!is_string($sql)) {
        return false;
    }
    /** @var mixed $row */
    $row = $wpdb->get_row($sql, 'ARRAY_A');
    return is_array($row);
}

/**
 * One resolved option row for the read ability: effective value (stored
 * when present, else the option-file default), `source`, the finite
 * choice list when declared plus the informative labels behind opaque
 * choice values (type-3 → "Narrow Width"). `default` is only spelled out
 * for stored rows (for default rows it IS the value).
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @param array<array-key, mixed> $stored
 * @return array<string, mixed>
 */
function bly_po_resolve_row(array $row, array $stored): array
{
    $id = $row['id'];
    /** @var mixed $default */
    $default = $row['option']['value'] ?? null;
    $is_stored = array_key_exists($id, $stored);
    $out = [
        'type' => $row['type'],
        'label' => $row['label'],
        'section' => $row['section'],
        'value' => bly_cap_value($is_stored ? $stored[$id] : $default),
        'source' => $is_stored ? 'stored' : 'default',
    ];
    if ($is_stored) {
        $out['default'] = bly_cap_value($default);
    }
    $typed = bly_choice_vocabulary($row);
    /** @var mixed $choices */
    $choices = $row['option']['choices'] ?? null;
    if ($typed !== null && is_array($choices)) {
        $values = array_map('strval', array_slice($typed, offset: 0, length: BLY_MAX_CHOICES));
        $out['choices'] = $values;
        $labels = array_intersect_key(bly_choice_labels($choices), array_flip($values));
        if ($labels !== []) {
            $out['choice_labels'] = (object) $labels;
        }
    }
    return $out;
}

/**
 * Stored keys the post's schema does not model: the theme's own
 * `styles_descriptor` cache, keys written by Companion features or by an
 * earlier post-type/schema, legacy keys. Names only — values are never
 * echoed (the descriptor alone is kilobytes of CSS).
 *
 * @param array<array-key, mixed> $stored
 * @param array<string, mixed> $index
 * @return list<string>
 */
function bly_po_extra_keys(array $stored, array $index): array
{
    $extra = [];
    foreach (array_keys($stored) as $key) {
        $name = (string) $key;
        if (!isset($index[$name])) {
            $extra[] = bly_ext_clean_text($name, BLY_MAX_EXTRA_KEY_BYTES);
        }
        if (count($extra) >= BLY_PO_MAX_EXTRA_KEYS) {
            break;
        }
    }
    return $extra;
}

// ---------------------------------------------------------------------------
// Write validation
// ---------------------------------------------------------------------------

/**
 * Validate ONE candidate value for a per-post option — the write gates,
 * in order:
 *
 *  1. size caps (bly_check_value_caps), then the schema-derived typed
 *     check the customizer writes use (ct-switch vocabulary, finite
 *     choices, declared ranges/units, shape vs the option-file default,
 *     the CSS-injection backstop on every string leaf — bly_validate_*);
 *     two control types the customizer path never meets on top-level
 *     values get their own shape rules here: `ct-visibility` stores a
 *     boolean OR a {desktop, tablet, mobile} => bool map (the option-file
 *     default is a bare `false`, so the generic device-map rule would
 *     refuse the map the UI itself stores — bly_validate_visibility_value),
 *     `ct-layers` stores a list of {id, enabled, …} layer rows validated
 *     RECURSIVELY against the per-layer declarations (ids among the
 *     declared layers, every nested setting typed against its own
 *     control declaration, unknown settings refused —
 *     bly_validate_layers_value, shared with the customizer path).
 *  2. Blocksy's own per-control sanitizer (blocksy_validate_for, fail
 *     closed), ONLY for the control types whose validator branch is
 *     shape-safe and meaningful for per-post values
 *     (bly_control_sanitizer_applies: color pickers, image uploaders,
 *     ct-number / ct-slider, scalar choice controls — text/textarea are
 *     deliberately excluded: the theme's own per-post path never
 *     wp_kses'es them, the Code Snippets keys rely on that for
 *     unfiltered_html users).
 *
 * Partial ct-typography objects are first completed
 * (bly_complete_typography_value) from `$current` — on the write path
 * that is the FRESH in-lock stored value (epo_validate_batch runs inside
 * the row lock), never a pre-lock read. Responsive top-level values are
 * then completed to a full {desktop, tablet, mobile} map
 * (bly_complete_device_map). Gate 3 — the theme's real per-post
 * sanitizer — runs on the whole batch, see bly_po_theme_sanitize().
 *
 * @param array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>} $row
 * @return mixed
 */
function bly_po_validate_value(array $row, mixed $value, mixed $current = null): mixed
{
    /** @var mixed $value */
    $value = bly_complete_typography_value($row, $value, $current);
    if ($value instanceof WP_Error) {
        return $value;
    }
    $caps = bly_check_value_caps($row, $value);
    if ($caps instanceof WP_Error) {
        return $caps;
    }

    /** @var mixed $validated */
    $validated = match ($row['type']) {
        'ct-switch' => bly_validate_switch_value($row, $value),
        'ct-visibility' => bly_validate_visibility_value($row, $value),
        'ct-layers' => bly_validate_layers_value($row, $value),
        default => bly_validate_against_schema($row, $value),
    };
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    if (bly_control_sanitizer_applies($row['type'], $validated)) {
        /** @var mixed $validated */
        $validated = bly_apply_blocksy_sanitizer($row, $validated);
        if ($validated instanceof WP_Error) {
            return $validated;
        }
    }
    if (is_array($validated) && bly_option_is_responsive($row['option']) && bly_is_device_map($validated)) {
        // The theme's own CSS output reads every device key of a stored
        // per-post responsive value unguarded (inc/css/fundamentals.php:
        // 143-180 on content_style), so a partial map would render with
        // PHP warnings on every page view; the expanded map is what
        // `written` reports.
        return bly_complete_device_map($validated);
    }
    return $validated;
}

/**
 * Gate 3 — the theme's OWN per-post sanitizer, blocksy_sanitize_post_meta_options()
 * (admin/helpers/validator.php:85): every string leaf containing `<`,
 * `>`, a null byte or a serialized-PHP payload is blanked to '' — except
 * the keys the `blocksy:post-meta:unfiltered-keys` filter exempts for
 * users with `unfiltered_html` (Companion's Code Snippets scripts). Both
 * theme save paths (classic metabox, editor REST field) run it on the
 * incoming values, so the ability runs it on the SAME input — and fails
 * closed: a value the sanitizer would alter is refused with the key
 * named, never silently persisted blanked. Only the keys being written
 * are passed (preserved keys stay byte-identical, as promised).
 *
 * @param array<string, mixed> $values validated id => value
 * @return array<string, mixed>|WP_Error
 */
function bly_po_theme_sanitize(array $values): array|WP_Error
{
    if (!function_exists('blocksy_sanitize_post_meta_options')) {
        return bly_error(
            'bly_sanitizer_unavailable',
            message: 'Refusing to write per-post options: the theme\'s post-meta sanitizer blocksy_sanitize_post_meta_options() is not loaded. Nothing was written — verify the theme installation and retry.',
        );
    }
    try {
        /** @var mixed $sanitized */
        $sanitized = blocksy_sanitize_post_meta_options($values);
    } catch (\Throwable $e) {
        error_log('novamira-pro blocksy: blocksy_sanitize_post_meta_options() threw: ' . $e->getMessage());
        return bly_error(
            'bly_sanitizer_unavailable',
            message: 'Refusing to write per-post options: blocksy_sanitize_post_meta_options() threw — details in the server error log. Nothing was written.',
        );
    }
    if (!is_array($sanitized)) {
        return bly_error(
            'bly_sanitizer_unavailable',
            message: 'Refusing to write per-post options: blocksy_sanitize_post_meta_options() returned a non-array. Nothing was written.',
        );
    }
    /** @var mixed $value */
    foreach ($values as $id => $value) {
        if (!array_key_exists($id, $sanitized) || $sanitized[$id] !== $value) {
            return bly_error('bly_invalid_input', sprintf(
                'Option "%s": a string inside the value contains `<`, `>`, a null byte or serialized PHP data, which Blocksy\'s own post-meta sanitizer blanks on save. Nothing was written — remove those characters (HTML is not accepted in per-post option values).',
                $id,
            ));
        }
    }
    return $values;
}

// ---------------------------------------------------------------------------
// styles_descriptor — the theme-managed per-post CSS cache
// ---------------------------------------------------------------------------

/**
 * Recompute the post's `styles_descriptor` from `$atts` exactly like the
 * editor's REST save does (admin/helpers/meta-boxes.php:109-118 →
 * ThemeDynamicCss::maybe_set_single_post_styles_descriptor with the
 * target post pinned as the global $post, as in that request). Returns
 * the descriptor array, or null when the theme could not produce one —
 * the caller then DROPS the key, which makes the front end regenerate
 * and persist it on the next view (version mismatch path,
 * inc/dynamic-css.php:231-252), so either outcome renders fresh CSS.
 *
 * @param array<array-key, mixed> $atts the options array WITHOUT the descriptor key
 * @return array<array-key, mixed>|null
 */
function bly_po_compute_styles_descriptor(WP_Post $post, array $atts): ?array
{
    if (!function_exists('blocksy_manager')) {
        return null;
    }
    $dynamic_css = blocksy_manager()->dynamic_css ?? null;
    if (!$dynamic_css instanceof \Blocksy\ThemeDynamicCss) {
        return null;
    }
    /** @var mixed $previous_global */
    $previous_global = $GLOBALS['post'] ?? null;
    $GLOBALS['post'] = $post;
    try {
        /** @var mixed $descriptor */
        $descriptor = $dynamic_css->maybe_set_single_post_styles_descriptor([
            'post_id' => $post->ID,
            'atts' => $atts,
        ]);
    } catch (\Throwable $e) {
        error_log(sprintf(
            'novamira-pro blocksy: styles_descriptor regeneration threw for post %d: %s',
            $post->ID,
            $e->getMessage(),
        ));
        return null;
    } finally {
        $GLOBALS['post'] = $previous_global;
    }
    return is_array($descriptor) && $descriptor !== [] ? $descriptor : null;
}
