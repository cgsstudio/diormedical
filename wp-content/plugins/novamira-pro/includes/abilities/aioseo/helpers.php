<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\AIOSEO;

use AIOSEO\Plugin\Common\Meta\Helpers as MetaHelpers;
use AIOSEO\Plugin\Common\Models\Model;
use AIOSEO\Plugin\Common\Models\Post;
use AIOSEO\Plugin\Pro\Models\Term;
use AIOSEO\Plugin\Pro\Redirects\Models\Redirect;
use AIOSEO\Plugin\Pro\Redirects\Utils\Request;
use WP_Error;
use WP_Post;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Detect AIOSEO presence, version, and edition. Cached per request.
 *
 * @return array{active: bool, version: string|null, pro: bool}
 */
function aio_detect_setup(): array
{
    /** @var array{active: bool, version: string|null, pro: bool}|null $cache */
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $active = defined('AIOSEO_VERSION');
    $pro = $active && function_exists('aioseo') && aioseo()->pro === true;

    return $cache = [
        'active' => $active,
        'version' => $active ? (string) constant('AIOSEO_VERSION') : null,
        'pro' => $pro,
    ];
}

/**
 * Standard "AIOSEO is not active" error.
 */
function aio_not_active(): WP_Error
{
    return new WP_Error('aioseo_not_active', 'All in One SEO is not active', ['status' => 400]);
}

/**
 * Standard "this needs AIOSEO Pro" error (term SEO, redirects).
 */
function aio_pro_required(string $feature): WP_Error
{
    return new WP_Error('aioseo_pro_required', "{$feature} requires All in One SEO Pro", ['status' => 400]);
}

/**
 * Whether the AIOSEO Pro redirects feature is available (Pro + the redirects
 * model loaded).
 */
function aio_supports_redirects(): bool
{
    return aio_detect_setup()['pro'] && class_exists('AIOSEO\Plugin\Pro\Redirects\Models\Redirect');
}

/**
 * Coerce a mixed model value to a string.
 */
function aio_str(mixed $raw): string
{
    return is_string($raw) ? $raw : '';
}

/**
 * Coerce a mixed model value to a non-negative integer (0 when not numeric or
 * out of range). >= PHP_INT_MAX guards the float boundary (the constant is not
 * exactly representable as a double) so an out-of-range value never casts lossy.
 */
function aio_int(mixed $raw): int
{
    if (!is_numeric($raw)) {
        return 0;
    }
    $float = (float) $raw;
    if (!is_finite($float) || $float >= (float) PHP_INT_MAX || $float < 0.0) {
        return 0;
    }
    return (int) $raw;
}

/**
 * Coerce a mixed model value to a boolean. AIOSEO stores its robots / toggle
 * columns as tinyint (0/1), so a numeric is read as a bool; '1'/'on'/'true' are
 * also accepted for safety.
 */
function aio_bool(mixed $raw): bool
{
    if (is_bool($raw)) {
        return $raw;
    }
    if (is_int($raw) || is_float($raw)) {
        return (float) $raw !== 0.0;
    }
    return is_string($raw) && in_array(strtolower($raw), ['1', 'on', 'true', 'yes'], strict: true);
}

/**
 * Coerce AIOSEO's getPublic* helper result to a clean list of string names.
 *
 * @param array<int|string, mixed> $names
 * @return list<string>
 */
function aio_string_names(array $names): array
{
    return array_values(array_filter($names, callback: 'is_string'));
}

/**
 * Public post-type names AIOSEO manages SEO for.
 *
 * @return list<string>
 */
function aio_public_post_type_names(): array
{
    if (!function_exists('aioseo')) {
        return [];
    }
    return aio_string_names(aioseo()->helpers->getPublicPostTypes(true));
}

/**
 * Public taxonomy names AIOSEO manages SEO for.
 *
 * @return list<string>
 */
function aio_public_taxonomy_names(): array
{
    if (!function_exists('aioseo')) {
        return [];
    }
    return aio_string_names(aioseo()->helpers->getPublicTaxonomies(true));
}

/**
 * Decode one of AIOSEO's JSON-string options (aioseo_options /
 * aioseo_options_dynamic) to an array.
 *
 * @return array<array-key, mixed>
 */
function aio_options_array(string $option): array
{
    /** @var mixed $raw */
    $raw = get_option($option);
    // The base options are stored as JSON strings; the _localized layers are
    // stored as plain (serialized) arrays. Handle both.
    if (is_array($raw)) {
        return $raw;
    }
    /** @var mixed $decoded */
    $decoded = is_string($raw) ? json_decode($raw, associative: true) : null;
    return is_array($decoded) ? $decoded : [];
}

/**
 * Walk a nested decoded-option array by a path of string keys and return the
 * leaf as a string ('' when any key is missing or the leaf is not a string).
 *
 * @param array<array-key, mixed> $data
 * @param list<string> $path
 */
function aio_nested_str(array $data, array $path): string
{
    /** @var mixed $cursor */
    $cursor = $data;
    foreach ($path as $key) {
        if (!is_array($cursor) || !array_key_exists($key, $cursor)) {
            return '';
        }
        /** @var mixed $cursor */
        $cursor = $cursor[$key];
    }
    return is_string($cursor) ? $cursor : '';
}

/**
 * Read a setting that AIOSEO may override in its _localized option layer (the
 * authoritative value for the admin-UI-edited title/description templates):
 * prefer the flat localized key (the path joined by '_', e.g.
 * searchAppearance_global_metaDescription) when non-empty, else the base nested
 * value.
 *
 * @param array<array-key, mixed> $base
 * @param array<array-key, mixed> $localized
 * @param list<string> $path
 */
function aio_setting_str(array $base, array $localized, array $path): string
{
    /** @var mixed $loc */
    $loc = $localized[implode('_', $path)] ?? null;
    if (is_string($loc) && $loc !== '') {
        return $loc;
    }
    return aio_nested_str($base, $path);
}

/**
 * AIOSEO's effective (default-aware) dynamic-options values tree. Reading all
 * options refreshes the defaults first, so this includes public types registered
 * at runtime even when their Search Appearance rows have not been persisted.
 * Returns an empty array when the service or API is unavailable.
 *
 * @return array<array-key, mixed>
 */
function aio_default_template_options(): array
{
    if (!function_exists('aioseo')) {
        return [];
    }

    /** @var mixed $dynamic_options */
    $dynamic_options = aioseo()->dynamicOptions ?? null;
    if (!is_object($dynamic_options) || !method_exists($dynamic_options, 'noConflict')) {
        return [];
    }

    /** @var mixed $dynamic_options */
    $dynamic_options = $dynamic_options->noConflict();
    if (!is_object($dynamic_options) || !method_exists($dynamic_options, 'all')) {
        return [];
    }

    /** @var mixed $options */
    $options = $dynamic_options->all();
    // The clone inherits traversal state, so all() can return a non-array subtree when an upstream group is selected.
    if (!is_array($options)) {
        return [];
    }

    return $options;
}

/**
 * Resolve a post id to a usable WP_Post, rejecting revisions, auto-drafts, and
 * non-existent / non-positive ids. Accepts an int, an integer-valued float, or
 * an all-digit string (aio_int normalises any of them).
 */
function aio_resolve_post(mixed $id): WP_Post|WP_Error
{
    if (!is_int($id) && !is_float($id) && !(is_string($id) && ctype_digit($id))) {
        return new WP_Error('aioseo_invalid_post', 'post_id must be a numeric post id', ['status' => 400]);
    }
    $post_id = aio_int($id);
    if ($post_id <= 0) {
        return new WP_Error('aioseo_invalid_post', 'post_id must be a positive integer', ['status' => 400]);
    }
    /** @var WP_Post|null $post */
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return new WP_Error('aioseo_post_not_found', "post not found: {$post_id}", ['status' => 404]);
    }
    if ($post->post_type === 'revision' || $post->post_status === 'auto-draft') {
        return new WP_Error('aioseo_invalid_post', "post {$post_id} is a revision or auto-draft", ['status' => 400]);
    }
    // AIOSEO only manages SEO for public post types (check-setup advertises them).
    // Reject a non-public type so we never report/seed SEO or schema AIOSEO never
    // renders. Guarded on a non-empty list so an unexpectedly-empty result (e.g.
    // AIOSEO part-loaded) does not reject everything.
    $public = aio_public_post_type_names();
    if ($public !== [] && !in_array($post->post_type, $public, strict: true)) {
        return new WP_Error(
            'aioseo_invalid_post',
            "AIOSEO does not manage SEO for the '{$post->post_type}' post type; see check-setup public_post_types",
            ['status' => 400],
        );
    }
    return $post;
}

/**
 * The friendly robots block from an AIOSEO post/term model, honoring the
 * robots_default footgun: when robots_default is true the per-row robots_* flags
 * are ignored (the row inherits the global / post-type default), so we report
 * index "default" (= inherit). When false, the per-row flags drive the value.
 *
 * @return array{index: string, follow: string, advanced: list<string>, max_snippet?: int, max_image_preview?: string, max_video_preview?: int}
 */
function aio_robots_block(Model $model): array
{
    if (aio_bool($model->robots_default)) {
        return ['index' => 'default', 'follow' => 'follow', 'advanced' => []];
    }

    $advanced = [];
    if (aio_bool($model->robots_noarchive)) {
        $advanced[] = 'noarchive';
    }
    if (aio_bool($model->robots_noimageindex)) {
        $advanced[] = 'noimageindex';
    }
    if (aio_bool($model->robots_nosnippet)) {
        $advanced[] = 'nosnippet';
    }
    if (aio_bool($model->robots_noodp)) {
        $advanced[] = 'noodp';
    }
    if (aio_bool($model->robots_notranslate)) {
        $advanced[] = 'notranslate';
    }

    // The SERP-preview limits AIOSEO also emits on an explicit-robots post, so an
    // agent can SEE a max-snippet/preview restriction. -1 = unlimited (AIOSEO
    // stores null for "unset", which renders as no limit); image preview defaults
    // to "large". Reported read-only — a robots edit preserves these columns
    // (set/change them with execute-php); index/follow/advanced are the writable
    // levers.
    $image_preview = aio_str($model->robots_max_imagepreview);

    return [
        'index' => aio_bool($model->robots_noindex) ? 'noindex' : 'index',
        'follow' => aio_bool($model->robots_nofollow) ? 'nofollow' : 'follow',
        'advanced' => $advanced,
        'max_snippet' => aio_robots_limit($model->robots_max_snippet),
        'max_image_preview' => $image_preview !== '' ? $image_preview : 'large',
        'max_video_preview' => aio_robots_limit($model->robots_max_videopreview),
    ];
}

/**
 * A robots preview-limit column as an int: AIOSEO stores null for "unset"
 * (unlimited), which it renders as no limit — report that as -1 (its UI's
 * unlimited sentinel). A real 0 means "none" and is preserved.
 */
function aio_robots_limit(mixed $raw): int
{
    return is_numeric($raw) ? (int) $raw : -1;
}

/**
 * The focus keyphrase from a model's decoded keyphrases object.
 */
function aio_focus_keyphrase(Post $model): string
{
    /** @var mixed $keyphrases */
    $keyphrases = $model->keyphrases;
    if (!is_object($keyphrases)) {
        return '';
    }
    /** @var mixed $focus */
    $focus = $keyphrases->focus ?? null;
    // Tag-strip on read like every other text field: AIOSEO stores the keyphrase
    // verbatim (its sanitizeKeyphrases never strips the phrase) and never renders
    // it, so a UI-stored <script>…</script> would otherwise reach the agent raw.
    return is_object($focus) ? aio_clean_text(aio_str($focus->keyphrase ?? null)) : '';
}

/**
 * The additional keyphrases (Pro) from a model's decoded keyphrases object.
 *
 * @return list<string>
 */
function aio_additional_keyphrases(Post $model): array
{
    /** @var mixed $keyphrases */
    $keyphrases = $model->keyphrases;
    if (!is_object($keyphrases)) {
        return [];
    }
    /** @var mixed $additional */
    $additional = $keyphrases->additional ?? null;
    if (!is_array($additional)) {
        return [];
    }
    $out = [];
    /** @var mixed $entry */
    foreach ($additional as $entry) {
        if (!is_object($entry)) {
            continue;
        }
        /** @var mixed $keyphrase */
        $keyphrase = $entry->keyphrase ?? null;
        if (!is_string($keyphrase) || $keyphrase === '') {
            continue;
        }
        // Tag-strip on read (see aio_focus_keyphrase) so raw HTML never leaks.
        $clean = aio_clean_text($keyphrase);
        if ($clean !== '') {
            $out[] = $clean;
        }
    }
    return $out;
}

/**
 * The Facebook / Open Graph block from a model.
 *
 * @return array{title: string, description: string, image: string, object_type: string}
 */
function aio_social_facebook(Model $model): array
{
    // Only a 'custom_image' source actually renders the per-entity image; for
    // any other source there is no agent-set custom image, so report ''.
    $image = aio_str($model->og_image_type) === 'custom_image' ? aio_str($model->og_image_custom_url) : '';
    return [
        // AIOSEO entity-encodes the title/description on save and decodes at
        // render; report the rendered (decoded) value.
        'title' => aio_display_text(aio_str($model->og_title)),
        'description' => aio_display_text(aio_str($model->og_description)),
        'image' => $image,
        'object_type' => aio_str($model->og_object_type),
    ];
}

/**
 * The X (Twitter) block from a model. use_og true means it mirrors the Facebook
 * values.
 *
 * @return array{use_og: bool, title: string, description: string, image: string, card: string}
 */
function aio_social_twitter(Model $model): array
{
    $image = aio_str($model->twitter_image_type) === 'custom_image' ? aio_str($model->twitter_image_custom_url) : '';
    return [
        'use_og' => aio_bool($model->twitter_use_og),
        'title' => aio_display_text(aio_str($model->twitter_title)),
        'description' => aio_display_text(aio_str($model->twitter_description)),
        'image' => $image,
        'card' => aio_str($model->twitter_card),
    ];
}

/**
 * Assemble the friendly SEO shape for a post from its AIOSEO model.
 *
 * @return array<string, mixed>
 */
function aio_post_seo(int $post_id): array
{
    $model = Post::getPost($post_id);
    return [
        'focus_keyphrase' => aio_focus_keyphrase($model),
        'additional_keyphrases' => aio_additional_keyphrases($model),
        // AIOSEO entity-encodes title/description on save and decodes at render.
        'seo_title' => aio_display_text(aio_str($model->title)),
        'meta_description' => aio_display_text(aio_str($model->description)),
        'canonical' => aio_clean_url(aio_str($model->canonical_url)),
        'pillar_content' => aio_bool($model->pillar_content),
        'robots' => aio_robots_block($model),
        'facebook' => aio_social_facebook($model),
        'twitter' => aio_social_twitter($model),
        // Whether the post emits its default schema graph ("enabled"/"off"), read
        // from the schema JSON column (the legacy schema_type varchar is a dead
        // 'default' flag in AIOSEO 4.x). Use aioseo-get-post-schema for the full
        // schema picture (default type + custom graphs).
        'schema' => aio_post_default_graph($model),
    ];
}

/**
 * Sanitise a free-text SEO value. AIOSEO smart tags (#post_title, #separator_sa)
 * use '#', which sanitize_text_field preserves, so this is safe for templates.
 */
function aio_clean_text(string $value): string
{
    return sanitize_text_field($value);
}

/**
 * Sanitise a URL value with WordPress's protocol allowlist (drops javascript:).
 */
function aio_clean_url(string $value): string
{
    return esc_url_raw($value);
}

/**
 * The human-readable value AIOSEO renders for a stored title / description /
 * separator, produced by mirroring AIOSEO's own render pipeline (Common\Meta\
 * Helpers::sanitize): recursively decode HTML entities exactly as AIOSEO does
 * (decodeHtmlEntitiesRecursive — recursive, ENT_HTML401), strip tags (AIOSEO
 * strips tags from rendered titles/descriptions), and collapse whitespace. Smart
 * tags (#post_title) survive untouched.
 *
 * Mirroring the render keeps the read faithful (it equals what AIOSEO serves
 * into <title>/<meta>/og:/twitter:) AND keeps a read-modify-write round-trip
 * stable: the stored value is just an encoded form of this, and writing this
 * back (sanitize_text_field) re-stores a render-equivalent value. A bare
 * html_entity_decode instead would surface live HTML tags the page never serves
 * (an injection vector for the consuming agent) and lose tag text on write-back.
 */
function aio_display_text(string $value): string
{
    // Delegate to AIOSEO's own title/description sanitiser so we match its render
    // EXACTLY (including encodeExceptions, which protects a literal "<3" etc. that
    // a bare decode-then-strip would eat). $replaceTags=true keeps smart tags
    // (#post_title) intact; we never resolve them here.
    if (class_exists('AIOSEO\Plugin\Common\Meta\Helpers')) {
        return (new MetaHelpers('title'))->sanitize($value, false, true);
    }
    // Fallback when AIOSEO is unavailable: recursive-equivalent decode + strip.
    $stripped = wp_strip_all_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML401));
    $collapsed = preg_replace('/\s+/u', replacement: ' ', subject: $stripped);
    return trim(is_string($collapsed) ? $collapsed : $stripped);
}

/**
 * The rendered title separator. AIOSEO decodes the separator's entities but does
 * NOT run it through the full tag-stripping title sanitiser, so a lone "<" / "< "
 * survives in the rendered title (aio_display_text would eat it). We therefore
 * decode entities and strip only COMPLETE HTML tags: a real "<script>…" can't
 * reach the agent as a live tag (AIOSEO also strips it from the rendered title),
 * while a bare "<" separator is preserved. Realistic separators (the entity
 * picker: &mdash; &raquo; … ) just decode.
 */
function aio_separator_display(string $value): string
{
    $decoded = function_exists('aioseo')
        ? aioseo()->helpers->decodeHtmlEntitiesRecursive($value)
        : html_entity_decode($value, ENT_QUOTES | ENT_HTML401);
    $stripped = preg_replace('#</?[a-zA-Z][^>]*>#', replacement: '', subject: $decoded);
    return is_string($stripped) ? $stripped : $decoded;
}

/**
 * Apply a friendly robots block to a model, honoring AIOSEO's all-or-nothing
 * robots_default gate: index "default" makes the row inherit ALL robots
 * (robots_default=true, per-row flags reset); any explicit index/follow/advanced
 * forces robots_default=false so the flags actually apply.
 *
 * @param list<string> $advanced
 */
function aio_apply_robots(Model $model, string $index, string $follow, array $advanced): void
{
    if ($index === 'default') {
        $model->robots_default = true;
        $model->robots_noindex = false;
        $model->robots_nofollow = false;
        $model->robots_noarchive = false;
        $model->robots_noimageindex = false;
        $model->robots_nosnippet = false;
        $model->robots_noodp = false;
        $model->robots_notranslate = false;
        // Inherit ALL robots, including the SERP limits — reset them to AIOSEO's
        // unset defaults so a stale limit can't resurface (and render) on a later
        // return to explicit robots that does not mention them.
        $model->robots_max_snippet = null;
        $model->robots_max_videopreview = null;
        $model->robots_max_imagepreview = 'large';
        return;
    }

    $model->robots_default = false;
    $model->robots_noindex = $index === 'noindex';
    $model->robots_nofollow = $follow === 'nofollow';
    $model->robots_noarchive = in_array('noarchive', $advanced, strict: true);
    $model->robots_noimageindex = in_array('noimageindex', $advanced, strict: true);
    $model->robots_nosnippet = in_array('nosnippet', $advanced, strict: true);
    $model->robots_noodp = in_array('noodp', $advanced, strict: true);
    $model->robots_notranslate = in_array('notranslate', $advanced, strict: true);
}

/**
 * Whether the advanced-robots input is an array of recognised tokens only.
 */
function aio_advanced_valid(mixed $advanced): bool
{
    if (!is_array($advanced)) {
        return false;
    }
    $strings = array_filter($advanced, callback: 'is_string');
    return (
        count($strings) === count($advanced)
        && array_diff($strings, ['noarchive', 'noimageindex', 'nosnippet', 'noodp', 'notranslate']) === []
    );
}

/**
 * Validate a friendly robots sub-block without writing. Returns a WP_Error, or
 * null if clean.
 */
function aio_validate_robots(mixed $robots): ?WP_Error
{
    if (!is_array($robots)) {
        return new WP_Error('aioseo_invalid_robots', 'robots must be an object', ['status' => 400]);
    }
    if (
        array_key_exists('index', $robots)
        && (!is_string($robots['index']) || !in_array($robots['index'], ['default', 'index', 'noindex'], strict: true))
    ) {
        return new WP_Error('aioseo_invalid_robots', 'robots.index must be default, index, or noindex', [
            'status' => 400,
        ]);
    }
    if (
        array_key_exists('follow', $robots)
        && (!is_string($robots['follow']) || !in_array($robots['follow'], ['follow', 'nofollow'], strict: true))
    ) {
        return new WP_Error('aioseo_invalid_robots', 'robots.follow must be follow or nofollow', ['status' => 400]);
    }
    if (array_key_exists('advanced', $robots) && !aio_advanced_valid($robots['advanced'])) {
        return new WP_Error(
            'aioseo_invalid_robots',
            'robots.advanced must be an array of: noarchive, noimageindex, nosnippet, noodp, notranslate',
            ['status' => 400],
        );
    }
    return null;
}

/**
 * Merge a partial friendly robots block onto a model's current robots and apply
 * it. Unprovided sub-fields keep their current effective value (field-by-field
 * merge); the all-or-nothing robots_default gate is handled by aio_apply_robots.
 *
 * @param array<array-key, mixed> $robots
 */
function aio_apply_robots_merge(Model $model, array $robots): void
{
    $current = aio_robots_block($model);

    $index_provided = array_key_exists('index', $robots) && is_string($robots['index']);
    $index = $index_provided ? $robots['index'] : $current['index'];
    $follow = array_key_exists('follow', $robots) && is_string($robots['follow'])
        ? $robots['follow']
        : $current['follow'];
    $advanced = array_key_exists('advanced', $robots) && is_array($robots['advanced'])
        ? array_values(array_intersect(array_filter($robots['advanced'], callback: 'is_string'), [
            'noarchive',
            'noimageindex',
            'nosnippet',
            'noodp',
            'notranslate',
        ]))
        : $current['advanced'];

    // The caller set a CONCRETE follow/advanced override but left index
    // inheriting ('default'). Because robots_default is all-or-nothing, keeping
    // 'default' would reset those flags; promote to a concrete 'index' so the
    // override actually applies. An explicit nofollow or a NON-EMPTY advanced
    // list counts; an empty advanced [] (or follow 'follow') carries no directive
    // and must not flip an inheriting row to an explicit override.
    $wants_nofollow = array_key_exists('follow', $robots) && ($robots['follow'] ?? null) === 'nofollow';
    $wants_advanced =
        array_key_exists('advanced', $robots) && is_array($robots['advanced']) && $robots['advanced'] !== [];
    if (!$index_provided && $index === 'default' && ($wants_nofollow || $wants_advanced)) {
        $index = 'index';
    }

    aio_apply_robots($model, $index, $follow, $advanced);

    // SERP-preview limits apply only to an explicit-robots row (when inheriting,
    // AIOSEO ignores them). Set them from the provided block so a read-modify-
    // write of get-*-seo's robots shape round-trips and the agent can change them.
    if ($index !== 'default') {
        aio_apply_robots_limits($model, $robots);
    }
}

/**
 * Apply the SERP-preview limits from a friendly robots block to a model (only
 * provided keys). -1 (the read's "unlimited" sentinel) maps back to AIOSEO's
 * stored null so a verbatim round-trip neither adds nor drops a directive.
 *
 * @param array<array-key, mixed> $robots
 */
function aio_apply_robots_limits(Model $model, array $robots): void
{
    if (array_key_exists('max_snippet', $robots)) {
        $snippet = aio_robots_limit_in($robots['max_snippet']);
        $model->robots_max_snippet = $snippet < 0 ? null : $snippet;
    }
    if (array_key_exists('max_video_preview', $robots)) {
        $video = aio_robots_limit_in($robots['max_video_preview']);
        $model->robots_max_videopreview = $video < 0 ? null : $video;
    }
    if (array_key_exists('max_image_preview', $robots) && is_string($robots['max_image_preview'])) {
        $model->robots_max_imagepreview = $robots['max_image_preview'];
    }
}

/**
 * Coerce a provided SERP limit to a safe int. A bare (int) cast warns ("float
 * not representable as int") for a JSON number above PHP_INT_MAX, so guard the
 * range first; a non-numeric / out-of-range value falls back to -1 (unlimited).
 */
function aio_robots_limit_in(mixed $raw): int
{
    if (!is_numeric($raw)) {
        return -1;
    }
    $float = (float) $raw;
    if (!is_finite($float) || $float >= (float) PHP_INT_MAX || $float <= -(float) PHP_INT_MAX) {
        return -1;
    }
    return (int) $float;
}

/**
 * Resolve the (image_type, image_custom_url) pair for a social-image edit.
 *
 * AIOSEO only renders a per-entity image when the source is 'custom_image'
 * (og_image_url is just a derived cache). A concrete URL sets that source. An
 * empty value clears it back to 'default' ONLY when a custom image was actually
 * set; for any other source ('content', 'auto', 'featured', 'attach',
 * 'default') the read reports image '' too, so a verbatim read->write-back of
 * that '' must NOT flip the source — return null to leave both columns intact.
 *
 * @return array{0: string, 1: string}|null
 */
function aio_resolve_custom_image(string $image, string $current_type): ?array
{
    $url = aio_clean_url($image);
    if ($url !== '') {
        return ['custom_image', $url];
    }
    if ($current_type === 'custom_image') {
        return ['default', ''];
    }
    return null;
}

/**
 * Apply a partial Facebook / Open Graph block to a model (only provided keys).
 *
 * @param array<array-key, mixed> $block
 */
function aio_apply_facebook(Model $model, array $block): void
{
    if (array_key_exists('title', $block) && is_string($block['title'])) {
        $model->og_title = aio_clean_text($block['title']);
    }
    if (array_key_exists('description', $block) && is_string($block['description'])) {
        $model->og_description = aio_clean_text($block['description']);
    }
    if (array_key_exists('image', $block) && is_string($block['image'])) {
        $resolved = aio_resolve_custom_image($block['image'], aio_str($model->og_image_type));
        if ($resolved !== null) {
            $model->og_image_type = $resolved[0];
            $model->og_image_custom_url = $resolved[1];
        }
    }
    if (array_key_exists('object_type', $block) && is_string($block['object_type'])) {
        $model->og_object_type = aio_clean_text($block['object_type']);
    }
}

/**
 * Apply a partial X (Twitter) block to a model (only provided keys).
 *
 * @param array<array-key, mixed> $block
 */
function aio_apply_twitter(Model $model, array $block): void
{
    if (array_key_exists('title', $block) && is_string($block['title'])) {
        $model->twitter_title = aio_clean_text($block['title']);
    }
    if (array_key_exists('description', $block) && is_string($block['description'])) {
        $model->twitter_description = aio_clean_text($block['description']);
    }
    if (array_key_exists('image', $block) && is_string($block['image'])) {
        $resolved = aio_resolve_custom_image($block['image'], aio_str($model->twitter_image_type));
        if ($resolved !== null) {
            $model->twitter_image_type = $resolved[0];
            $model->twitter_image_custom_url = $resolved[1];
        }
    }
    if (array_key_exists('use_og', $block)) {
        $model->twitter_use_og = aio_bool($block['use_og']);
    }
    if (array_key_exists('card', $block) && is_string($block['card'])) {
        $model->twitter_card = aio_clean_text($block['card']);
    }
}

/**
 * Set a post's focus keyphrase, preserving any existing additional keyphrases.
 */
function aio_set_focus_keyphrase(Post $model, string $keyphrase): void
{
    // Preserve the existing keyphrases structure (AIOSEO keeps per-keyphrase
    // TruSEO score/analysis there for the focus AND the additional keyphrases);
    // only swap the focus keyphrase string.
    /** @var mixed $decoded */
    $decoded = json_decode((string) json_encode($model->keyphrases), associative: true);
    $data = is_array($decoded) ? $decoded : [];

    $focus = is_array($data['focus'] ?? null) ? $data['focus'] : [];
    $focus['keyphrase'] = $keyphrase;
    $data['focus'] = $focus;
    if (!is_array($data['additional'] ?? null)) {
        $data['additional'] = [];
    }

    $model->keyphrases = json_encode($data);
}

/**
 * Resolve a (term id, taxonomy) pair to a WP_Term, validating the taxonomy.
 * Accepts an int, integer-valued float, or all-digit string for the id.
 */
function aio_resolve_term(mixed $term_id, mixed $taxonomy): WP_Term|WP_Error
{
    if (!is_string($taxonomy) || $taxonomy === '') {
        return new WP_Error('aioseo_invalid_term', 'taxonomy is required and must be a string', ['status' => 400]);
    }
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('aioseo_invalid_term', "taxonomy does not exist: {$taxonomy}", ['status' => 400]);
    }
    // AIOSEO only manages SEO for public taxonomies (check-setup advertises these
    // as the targets). Reject internal taxonomies (nav_menu, link_category,
    // wp_theme, post_format, …) so we never write a row AIOSEO will never render.
    if (!in_array($taxonomy, aio_public_taxonomy_names(), strict: true)) {
        return new WP_Error(
            'aioseo_invalid_term',
            "AIOSEO does not manage SEO for the '{$taxonomy}' taxonomy; see check-setup public_taxonomies",
            ['status' => 400],
        );
    }
    if (!is_int($term_id) && !is_float($term_id) && !(is_string($term_id) && ctype_digit($term_id))) {
        return new WP_Error('aioseo_invalid_term', 'term_id must be a numeric term id', ['status' => 400]);
    }
    $id = aio_int($term_id);
    if ($id <= 0) {
        return new WP_Error('aioseo_invalid_term', 'term_id must be a positive integer', ['status' => 400]);
    }
    $term = get_term($id, $taxonomy);
    if (!$term instanceof WP_Term) {
        return new WP_Error('aioseo_term_not_found', "term not found in {$taxonomy}: {$id}", ['status' => 404]);
    }
    return $term;
}

/**
 * Assemble the friendly SEO shape for a term from its AIOSEO model (Pro). Term
 * SEO has the same shape as a post minus the keyphrase and schema fields.
 *
 * @return array<string, mixed>
 */
function aio_term_seo(int $term_id): array
{
    $model = Term::getTerm($term_id);
    return [
        // AIOSEO entity-encodes title/description on save and decodes at render.
        'seo_title' => aio_display_text(aio_str($model->title)),
        'meta_description' => aio_display_text(aio_str($model->description)),
        'canonical' => aio_clean_url(aio_str($model->canonical_url)),
        'pillar_content' => aio_bool($model->pillar_content),
        'robots' => aio_robots_block($model),
        'facebook' => aio_social_facebook($model),
        'twitter' => aio_social_twitter($model),
    ];
}

/**
 * Decode a post model's schema column (a JSON object: blockGraphs, customGraphs,
 * default {graphName, isEnabled, data}, graphs) to an associative array.
 *
 * @return array<array-key, mixed>
 */
function aio_schema_array(Post $model): array
{
    /** @var mixed $schema */
    $schema = $model->schema;
    /** @var mixed $decoded */
    $decoded = json_decode((string) json_encode($schema), associative: true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * The schema.org graph a post type renders by default. AIOSEO's coarse
 * searchAppearance.schemaType ("Article"/"WebPage") resolves to a sub-type:
 * "Article" -> articleType (e.g. "BlogPosting"), "WebPage" -> webPageType.
 * This is the graph a per-post "default" schema actually inherits.
 */
function aio_default_schema_type(string $post_type): string
{
    $dynamic = aio_options_array('aioseo_options_dynamic');
    $key = $post_type === 'type' ? '_aioseo_type' : $post_type;
    $base = aio_nested_str($dynamic, ['searchAppearance', 'postTypes', $key, 'schemaType']);
    if ($base === '') {
        // Not persisted to the option row yet (e.g. a runtime-registered public
        // CPT). AIOSEO seeds the schema default at boot: WebPage for every post
        // type except 'post' (Article). Mirror that so we don't under-report.
        $base = $post_type === 'post' ? 'Article' : 'WebPage';
    }
    if ($base === 'Article') {
        $sub = aio_nested_str($dynamic, ['searchAppearance', 'postTypes', $key, 'articleType']);
        return $sub !== '' ? $sub : 'Article';
    }
    if ($base === 'WebPage') {
        $sub = aio_nested_str($dynamic, ['searchAppearance', 'postTypes', $key, 'webPageType']);
        return $sub !== '' ? $sub : 'WebPage';
    }
    return $base;
}

/**
 * The state of the post's PRIMARY (default) schema graph: "enabled" (the post
 * emits its default schema graph, whose type is always the post-type default —
 * AIOSEO resets default.graphName to that at render, so the type is not
 * per-post-changeable) or "off" (the default graph is disabled). Custom typed
 * graphs (schema.graphs[]) render independently and are reported separately.
 */
function aio_post_default_graph(Post $model): string
{
    $schema = aio_schema_array($model);
    $default = is_array($schema['default'] ?? null) ? $schema['default'] : [];
    return aio_bool($default['isEnabled'] ?? true) ? 'enabled' : 'off';
}

/**
 * The schema.org types of the post's custom Schema-Generator graphs
 * (schema.graphs[]). Unlike the default graph, these explicit graphs DO render
 * with the chosen type, so they are the authoritative answer to "what schema
 * types does this post emit beyond its default".
 *
 * @return list<string>
 */
function aio_post_custom_graph_types(Post $model): array
{
    $schema = aio_schema_array($model);
    $graphs = is_array($schema['graphs'] ?? null) ? $schema['graphs'] : [];
    $types = [];
    /** @var mixed $graph */
    foreach ($graphs as $graph) {
        if (!is_array($graph)) {
            continue;
        }
        $name = aio_str($graph['graphName'] ?? '');
        $props = is_array($graph['properties'] ?? null) ? $graph['properties'] : [];
        // AIOSEO renders a custom graph only when id, graphName AND properties are
        // all non-empty (Pro\Schema\Schema::getUserDefinedGraphs skips otherwise),
        // so an incomplete entry must NOT be reported as a rendered type.
        if (aio_str($graph['id'] ?? '') === '' || $name === '' || $props === []) {
            continue;
        }
        // AIOSEO swaps in the subtype at render: when properties.type is set
        // (e.g. NewsArticle for an Article graph, AboutPage for a WebPage graph)
        // THAT is the schema.org type actually emitted. Report it, mirroring
        // Pro\Schema\Schema::getUserDefinedGraphs.
        $subtype = aio_str($props['type'] ?? '');
        $types[] = $subtype !== '' ? $subtype : $name;
    }
    return $types;
}

/**
 * Whether the post has any additional user-authored schema graphs alongside the
 * default graph: the editor's added graphs (schema.graphs), raw "Custom Schema"
 * JSON-LD (schema.customGraphs), and block-provided graphs (schema.blockGraphs).
 * AIOSEO renders all of these; they are NOT managed by edit-post-schema (which
 * only sets the default graph).
 */
function aio_post_has_custom_graphs(Post $model): bool
{
    $schema = aio_schema_array($model);
    foreach (['graphs', 'customGraphs', 'blockGraphs'] as $bucket) {
        if (is_array($schema[$bucket] ?? null) && $schema[$bucket] !== []) {
            return true;
        }
    }
    return false;
}

/**
 * The friendly per-post schema shape. default_graph is "enabled"/"off" for the
 * post's default (primary) graph; default_type is the schema.org type that
 * default graph renders (the post-type default); custom_graphs lists the types
 * of any Schema-Generator graphs the post adds (these render too);
 * has_custom_graphs flags any custom graphs (graphs/customGraphs/blockGraphs)
 * edit-post-schema leaves intact.
 *
 * @return array{default_graph: string, default_type: string, custom_graphs: list<string>, has_custom_graphs: bool}
 */
function aio_post_schema(int $post_id, string $post_type): array
{
    $model = Post::getPost($post_id);
    return [
        'default_graph' => aio_post_default_graph($model),
        'default_type' => aio_default_schema_type($post_type),
        'custom_graphs' => aio_post_custom_graph_types($model),
        'has_custom_graphs' => aio_post_has_custom_graphs($model),
    ];
}

/**
 * The HTTP status types AIOSEO redirects support (301/302/307 move; 410 gone;
 * 451 unavailable for legal reasons).
 *
 * @return list<int>
 */
function aio_redirect_types(): array
{
    return [301, 302, 307, 410, 451];
}

/**
 * The id of an existing redirect whose source_url_hash matches $hash (the UNIQUE
 * index), excluding $exclude_id (0 excludes nothing), or 0 when none. The index
 * is shared by plain AND regex rows, so a hash match catches a cross-kind
 * collision (a regex source whose hash equals a plain row's, etc.).
 */
function aio_redirect_id_by_hash(string $hash, int $exclude_id): int
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = $wpdb->prefix . 'aioseo_redirects';
    // Manual SQL with esc_sql (not prepare): the table name interpolates the
    // trusted prefix, $hash is SQL-escaped, and $exclude_id is an int.
    $escaped = esc_sql($hash);
    $hash_sql = is_string($escaped) ? $escaped : '';
    $exclude_sql = $exclude_id > 0 ? ' AND id != ' . $exclude_id : '';
    /** @var mixed $id */
    $id = $wpdb->get_var("SELECT id FROM {$table} WHERE source_url_hash = '{$hash_sql}'{$exclude_sql} LIMIT 1");
    return aio_int($id);
}

/**
 * Append a row's custom_rules to a source before hashing, exactly as
 * Redirect::save() does (it json-encodes a non-string rules value). $custom_rules
 * is null for a new row / a row without query-param rules.
 */
function aio_redirect_with_rules(string $source, mixed $custom_rules): string
{
    if (is_array($custom_rules) && $custom_rules !== []) {
        return $source . (string) wp_json_encode($custom_rules);
    }
    if (is_string($custom_rules) && $custom_rules !== '') {
        return $source . $custom_rules;
    }
    return $source;
}

/**
 * The source_url_hash AIOSEO's Redirect::save() stores for a PLAIN redirect:
 * normalise + decodeUrl the source, append any custom_rules, then getUrlHash.
 * Matching the save hash exactly makes the dedup pre-check authoritative.
 */
function aio_redirect_hash_plain(string $source, mixed $custom_rules): string
{
    $transformed = aioseo()->helpers->decodeUrl(Request::normalizeUrl($source));
    return Request::getUrlHash(aio_redirect_with_rules($transformed, $custom_rules));
}

/**
 * The source_url_hash AIOSEO's Redirect::save() stores for a REGEX redirect: the
 * RAW source (AIOSEO skips the URL normalisation it applies to plain rows), with
 * any custom_rules appended, then getUrlHash.
 */
function aio_redirect_hash_regex(string $source, mixed $custom_rules): string
{
    return Request::getUrlHash(aio_redirect_with_rules($source, $custom_rules));
}

/**
 * Compact row for a redirect model (the shape list/create/edit return).
 *
 * @return array{id: int, source_url: string, target_url: string, type: int, regex: bool, enabled: bool}
 */
function aio_redirect_row(Redirect $redirect): array
{
    return [
        'id' => aio_int($redirect->id),
        'source_url' => aio_str($redirect->source_url),
        'target_url' => aio_str($redirect->target_url),
        'type' => aio_int($redirect->type),
        'regex' => aio_bool($redirect->regex),
        'enabled' => aio_bool($redirect->enabled),
    ];
}

/**
 * Map raw redirect DB rows (from a $wpdb ARRAY_A read) to compact, coerced rows.
 *
 * @param array<array-key, mixed> $rows
 * @return list<array{id: int, source_url: string, target_url: string, type: int, regex: bool, enabled: bool}>
 */
function aio_redirect_db_rows(array $rows): array
{
    $out = [];
    /** @var mixed $row */
    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $out[] = [
            'id' => aio_int($row['id'] ?? 0),
            'source_url' => aio_str($row['source_url'] ?? ''),
            'target_url' => aio_str($row['target_url'] ?? ''),
            'type' => aio_int($row['type'] ?? 0),
            'regex' => aio_bool($row['regex'] ?? false),
            'enabled' => aio_bool($row['enabled'] ?? false),
        ];
    }
    return $out;
}
