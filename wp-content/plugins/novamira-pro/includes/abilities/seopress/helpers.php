<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;
use WP_Post;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Detect SeoPress presence, version, and edition. Cached per request.
 *
 * SEOPRESS_VERSION is defined by the free plugin; SEOPRESS_PRO_VERSION by the
 * Pro add-on (which hard-depends on free). Pro presence does NOT imply a valid
 * license — Pro features run unlicensed, so the Pro gate keys on the constant,
 * not the license option.
 *
 * @return array{active: bool, version: string|null, pro: bool, pro_version: string|null, licensed: bool}
 */
function sp_detect_setup(): array
{
    /** @var array{active: bool, version: string|null, pro: bool, pro_version: string|null, licensed: bool}|null $cache */
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $active = defined('SEOPRESS_VERSION');
    $pro = $active && defined('SEOPRESS_PRO_VERSION');

    return $cache = [
        'active' => $active,
        'version' => $active ? (string) constant('SEOPRESS_VERSION') : null,
        'pro' => $pro,
        'pro_version' => $pro ? (string) constant('SEOPRESS_PRO_VERSION') : null,
        // The only place the license is read. Used solely for the optional
        // license_active flag — never to gate a feature.
        'licensed' => $pro && get_option('seopress_pro_license_status') === 'valid',
    ];
}

/**
 * Standard "SeoPress is not active" error.
 */
function sp_not_active(): WP_Error
{
    return new WP_Error('seopress_not_active', 'SeoPress is not active', ['status' => 400]);
}

/**
 * Standard "this needs SeoPress Pro" error (redirects, schema).
 */
function sp_pro_required(string $feature): WP_Error
{
    return new WP_Error('seopress_pro_required', "{$feature} requires SeoPress Pro", ['status' => 400]);
}

/**
 * Coerce a mixed meta/option value to a string ('' when not a string/scalar).
 */
function sp_str(mixed $raw): string
{
    if (is_string($raw)) {
        return $raw;
    }
    if (is_int($raw) || is_float($raw)) {
        return (string) $raw;
    }
    return '';
}

/**
 * Coerce a mixed value to a non-negative integer (0 when not numeric or out of
 * range). >= PHP_INT_MAX guards the float boundary so an out-of-range value
 * never casts lossy.
 */
function sp_int(mixed $raw): int
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
 * Whether a SeoPress checkbox meta value is "on". SeoPress stores the literal
 * string 'yes' when a robots/redirect toggle is checked and DELETES the row when
 * unchecked, so 'yes' is the only on-value; absent/'' = off (= inherit global).
 */
function sp_yesbool(mixed $raw): bool
{
    return $raw === 'yes' || $raw === true;
}

/**
 * Whether a SeoPress option-level toggle is "on". The global checkbox options
 * (seopress_titles_noindex, …) store '1' when on and are absent/'' when off;
 * a literal '0' also counts as off.
 */
function sp_opt_on(mixed $raw): bool
{
    return !($raw === null || $raw === '' || $raw === '0' || $raw === false || $raw === 0);
}

/**
 * Sanitise a free-text SEO value. SeoPress dynamic variables (%%sitetitle%%) use
 * '%', which sanitize_text_field preserves, so this is safe for templates.
 */
function sp_clean_text(string $value): string
{
    return sanitize_text_field($value);
}

/**
 * Sanitise a URL value with WordPress's protocol allowlist (drops javascript:).
 */
function sp_clean_url(string $value): string
{
    return esc_url_raw($value);
}

/**
 * Read a single SeoPress postmeta value. The key comes first so call sites pass
 * the meta key as a leading literal (the codebase's named/leading-arg convention).
 */
function sp_post_meta(string $key, int $id): mixed
{
    return get_post_meta($id, key: $key, single: true);
}

/**
 * Read a single SeoPress termmeta value (key first, mirroring sp_post_meta).
 */
function sp_term_meta(string $key, int $id): mixed
{
    return get_term_meta($id, key: $key, single: true);
}

/**
 * The decoded global titles/metas option (seopress_titles_option_name). Cached.
 *
 * @return array<array-key, mixed>
 */
function sp_titles_option(): array
{
    /** @var array<array-key, mixed>|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    /** @var mixed $raw */
    $raw = get_option('seopress_titles_option_name');
    return $cache = is_array($raw) ? $raw : [];
}

/**
 * Whether a SeoPress module is enabled. Modules are on by default; the
 * seopress_toggle option only records an explicit '0' to disable one, so the
 * module is enabled unless its toggle value is exactly '0'.
 */
function sp_module_enabled(string $feature): bool
{
    if (!function_exists('seopress_get_toggle_option')) {
        return false;
    }
    /** @var mixed $value */
    $value = seopress_get_toggle_option($feature);
    return $value !== '0';
}

/**
 * Public post-type names SeoPress manages SEO for (the intended get/edit-post-seo
 * targets). Excludes attachments and internal types.
 *
 * @return list<string>
 */
function sp_public_post_type_names(): array
{
    // get_post_types() returns names by default — no positional output arg needed.
    $names = array_filter(get_post_types(['public' => true]), callback: 'is_string');
    return array_values(array_filter($names, static fn(string $name): bool => $name !== 'attachment'));
}

/**
 * Public taxonomy names SeoPress manages SEO for (the get/edit-term-seo targets).
 *
 * @return list<string>
 */
function sp_public_taxonomy_names(): array
{
    $names = array_filter(get_taxonomies(['public' => true]), callback: 'is_string');
    return array_values($names);
}

/**
 * Resolve a post id to a usable WP_Post, rejecting revisions, auto-drafts, and
 * non-existent / non-positive ids. Accepts an int, an integer-valued float, or
 * an all-digit string.
 */
function sp_resolve_post(mixed $id): WP_Post|WP_Error
{
    if (!is_int($id) && !is_float($id) && !(is_string($id) && ctype_digit($id))) {
        return new WP_Error('seopress_invalid_post', 'post_id must be a numeric post id', ['status' => 400]);
    }
    $post_id = sp_int($id);
    if ($post_id <= 0) {
        return new WP_Error('seopress_invalid_post', 'post_id must be a positive integer', ['status' => 400]);
    }
    /** @var WP_Post|null $post */
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return new WP_Error('seopress_post_not_found', "post not found: {$post_id}", ['status' => 404]);
    }
    if ($post->post_type === 'revision' || $post->post_status === 'auto-draft') {
        return new WP_Error('seopress_invalid_post', "post {$post_id} is a revision or auto-draft", ['status' => 400]);
    }
    return $post;
}

/**
 * Resolve a (term id, taxonomy) pair to a WP_Term, validating the taxonomy.
 * Accepts an int, integer-valued float, or all-digit string for the id.
 */
function sp_resolve_term(mixed $term_id, mixed $taxonomy): WP_Term|WP_Error
{
    if (!is_string($taxonomy) || $taxonomy === '') {
        return new WP_Error('seopress_invalid_term', 'taxonomy is required and must be a string', ['status' => 400]);
    }
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('seopress_invalid_term', 'taxonomy does not exist: ' . sanitize_text_field($taxonomy), [
            'status' => 400,
        ]);
    }
    if (!is_int($term_id) && !is_float($term_id) && !(is_string($term_id) && ctype_digit($term_id))) {
        return new WP_Error('seopress_invalid_term', 'term_id must be a numeric term id', ['status' => 400]);
    }
    $id = sp_int($term_id);
    if ($id <= 0) {
        return new WP_Error('seopress_invalid_term', 'term_id must be a positive integer', ['status' => 400]);
    }
    /** @var WP_Term|WP_Error|null $term */
    $term = get_term($id, $taxonomy);
    if (!$term instanceof WP_Term) {
        return new WP_Error('seopress_term_not_found', "term not found in {$taxonomy}: {$id}", ['status' => 404]);
    }
    return $term;
}

/**
 * The per-entity robots block from raw meta (the explicit per-post/term
 * overrides). Each flag is the friendly POSITIVE-sense directive: noindex true
 * means "this entity carries a noindex override". An absent row reads as false
 * (= inherit the global / per-type default — see sp_effective_robots to resolve
 * that). breadcrumbs_hide is Pro-written and read-only on free.
 *
 * @param callable(string, int): mixed $get sp_post_meta or sp_term_meta
 * @return array{noindex: bool, nofollow: bool, nosnippet: bool, noimageindex: bool, breadcrumbs_hide: bool, canonical: string}
 */
function sp_robots_block(callable $get, int $id): array
{
    return [
        'noindex' => sp_yesbool($get('_seopress_robots_index', $id)),
        'nofollow' => sp_yesbool($get('_seopress_robots_follow', $id)),
        'nosnippet' => sp_yesbool($get('_seopress_robots_snippet', $id)),
        'noimageindex' => sp_yesbool($get('_seopress_robots_imageindex', $id)),
        'breadcrumbs_hide' => sp_yesbool($get('_seopress_robots_breadcrumbs', $id)),
        'canonical' => sp_clean_url(sp_str($get('_seopress_robots_canonical', $id))),
    ];
}

/**
 * Whether a global titles-option robots toggle (seopress_titles_no*) is on.
 */
function sp_global_flag(string $key): bool
{
    return sp_opt_on(sp_titles_option()[$key] ?? null);
}

/**
 * Whether a scoped per-type / per-taxonomy default flag ('noindex'|'nofollow') is
 * on. $key first so it is a leading literal at the call site.
 *
 * @param array<array-key, mixed> $scoped
 */
function sp_scoped_flag(string $key, array $scoped): bool
{
    return sp_opt_on($scoped[$key] ?? null);
}

/**
 * The scoped robots defaults sub-array for a post type or taxonomy: $group is the
 * titles-option key (seopress_titles_single_titles for posts,
 * seopress_titles_tax_titles for terms), $name the post type / taxonomy slug.
 *
 * @return array<array-key, mixed>
 */
function sp_scoped_titles(string $group, string $name): array
{
    /** @var mixed $all */
    $all = sp_titles_option()[$group] ?? null;
    /** @var mixed $scoped */
    $scoped = is_array($all) ? $all[$name] ?? null : null;
    return is_array($scoped) ? $scoped : [];
}

/**
 * Resolve the EFFECTIVE robots directives from the per-entity overrides ($per),
 * the global seopress_titles_no* toggles, and the scoped per-type/per-taxonomy
 * defaults ($scoped): any of the three forces a directive. The caller folds any
 * other per-entity force (e.g. a password-protected post's noindex) into $per
 * before calling. Mirrors SeoPress's RobotSettings resolution via direct option
 * reads, with no coupling to internal service classes.
 *
 * @param array{noindex: bool, nofollow: bool, nosnippet: bool, noimageindex: bool, breadcrumbs_hide: bool, canonical: string} $per
 * @param array<array-key, mixed> $scoped
 * @return array{noindex: bool, nofollow: bool, nosnippet: bool, noimageindex: bool}
 */
function sp_resolve_effective(array $per, array $scoped): array
{
    return [
        'noindex' => $per['noindex'] || sp_global_flag('seopress_titles_noindex') || sp_scoped_flag('noindex', $scoped),
        'nofollow' =>
            $per['nofollow'] || sp_global_flag('seopress_titles_nofollow') || sp_scoped_flag('nofollow', $scoped),
        'nosnippet' => $per['nosnippet'] || sp_global_flag('seopress_titles_nosnippet'),
        'noimageindex' => $per['noimageindex'] || sp_global_flag('seopress_titles_noimageindex'),
    ];
}

/**
 * The EFFECTIVE robots directives for a post: the per-post override ($per), a
 * global toggle, a per-post-type default, or — for noindex — a password-protected
 * post forces the directive.
 *
 * @param array{noindex: bool, nofollow: bool, nosnippet: bool, noimageindex: bool, breadcrumbs_hide: bool, canonical: string} $per
 * @return array{noindex: bool, nofollow: bool, nosnippet: bool, noimageindex: bool}
 */
function sp_effective_robots(WP_Post $post, array $per): array
{
    if (post_password_required($post)) {
        $per['noindex'] = true;
    }
    $scoped = sp_scoped_titles('seopress_titles_single_titles', $post->post_type);
    return sp_resolve_effective($per, $scoped);
}

/**
 * The Facebook (Open Graph) or Twitter/X social block for an entity. $prefix is
 * 'fb' or 'twitter'. image_id is null when no attachment backs the image.
 *
 * @param callable(string, int): mixed $get sp_post_meta or sp_term_meta
 * @return array{title: string, description: string, image: string, image_id: int|null}
 */
function sp_social_block(string $prefix, callable $get, int $id): array
{
    $image_id = sp_int($get("_seopress_social_{$prefix}_img_attachment_id", $id));
    return [
        'title' => sp_clean_text(sp_str($get("_seopress_social_{$prefix}_title", $id))),
        'description' => sp_clean_text(sp_str($get("_seopress_social_{$prefix}_desc", $id))),
        'image' => sp_clean_url(sp_str($get("_seopress_social_{$prefix}_img", $id))),
        'image_id' => $image_id > 0 ? $image_id : null,
    ];
}

/**
 * The single-entity redirection block (the per-post/term redirect, distinct from
 * the Pro seopress_404 CPT). enabled gates whether SeoPress fires it; type is
 * null when no status code is stored.
 *
 * @param callable(string, int): mixed $get sp_post_meta or sp_term_meta
 * @return array{enabled: bool, type: int|null, value: string, logged_status: string}
 */
function sp_redirect_block(callable $get, int $id): array
{
    $type = sp_int($get('_seopress_redirections_type', $id));
    $logged = sp_str($get('_seopress_redirections_logged_status', $id));
    return [
        'enabled' => sp_yesbool($get('_seopress_redirections_enabled', $id)),
        'type' => $type > 0 ? $type : null,
        'value' => sp_clean_url(sp_str($get('_seopress_redirections_value', $id))),
        'logged_status' => $logged !== '' ? $logged : 'both',
    ];
}

// ---------------------------------------------------------------------------
// Pro redirect-manager helpers (the seopress_404 CUSTOM POST TYPE, distinct
// from the per-post/term sp_redirect_block above). Redirects AND monitored
// 404s share ONE CPT — the PRESENCE of the _seopress_redirections_type meta is
// what marks a configured redirect; a bare 404 log entry has no type. Shared by
// list-redirects / get-redirect (read) and the S? write half (create / edit).
// ---------------------------------------------------------------------------

/**
 * Whether the SeoPress Pro redirect manager (the seopress_404 CPT) is usable:
 * Pro must be active AND the CPT registered (belt-and-braces — the CPT is
 * registered by the Pro Redirections feature, so a Pro install that has the
 * module disabled would lack it).
 */
function sp_redirect_supported(): bool
{
    return sp_detect_setup()['pro'] && post_type_exists('seopress_404');
}

/**
 * Resolve an id to a seopress_404 WP_Post, rejecting non-positive / missing ids
 * and any post of another type. sp_int coerces an int, integer-valued float, or
 * all-digit string; anything else lands as 0 and is rejected.
 */
function sp_resolve_redirect(mixed $id): WP_Post|WP_Error
{
    $post_id = sp_int($id);
    if ($post_id <= 0) {
        return new WP_Error('seopress_invalid_redirect', 'id must be a positive integer', ['status' => 400]);
    }
    /** @var WP_Post|null $post */
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return new WP_Error('seopress_invalid_redirect', "redirect not found: {$post_id}", ['status' => 404]);
    }
    if ($post->post_type !== 'seopress_404') {
        return new WP_Error('seopress_invalid_redirect', "post {$post_id} is not a redirect", ['status' => 400]);
    }
    return $post;
}

/**
 * Whether a seopress_404 post is a bare monitored 404 (no redirect configured):
 * the _seopress_redirections_type meta is absent or empty. The inverse marks a
 * configured redirect — this is the canonical distinction SeoPress itself uses.
 */
function sp_redirect_is_404(WP_Post $post): bool
{
    $type = sp_str(sp_post_meta('_seopress_redirections_type', $post->ID));
    return $type === '' || $type === '0';
}

/**
 * The COMPACT redirect projection every list/get/create/edit returns. Mirrors
 * the SeoPress Pro REST shape (build_redirection_data) but with snake_case keys
 * and stricter typing: origin is the post_title (a relative path or regex);
 * type is the HTTP status as an int, or null for a bare 404 (no type meta);
 * last_hit is the unix timestamp of the last hit, or null when never hit. The
 * user-agent / referer telemetry is deliberately NOT in the compact row —
 * get-redirect surfaces those.
 *
 * @return array{
 *   id: int,
 *   origin: string,
 *   destination: string,
 *   type: int|null,
 *   enabled: bool,
 *   enabled_regex: bool,
 *   logged_status: string,
 *   param: string,
 *   count: int,
 *   last_hit: int|null
 * }
 */
function sp_redirect_row(WP_Post $post): array
{
    $id = $post->ID;
    $type = sp_int(sp_post_meta('_seopress_redirections_type', $id));
    $logged = sp_str(sp_post_meta('_seopress_redirections_logged_status', $id));
    $param = sp_str(sp_post_meta('_seopress_redirections_param', $id));
    $last_hit = sp_int(sp_post_meta('_seopress_404_redirect_date_request', $id));

    return [
        'id' => $id,
        'origin' => sp_str($post->post_title),
        'destination' => sp_clean_url(sp_str(sp_post_meta('_seopress_redirections_value', $id))),
        'type' => $type > 0 ? $type : null,
        'enabled' => sp_yesbool(sp_post_meta('_seopress_redirections_enabled', $id)),
        'enabled_regex' => sp_yesbool(sp_post_meta('_seopress_redirections_enabled_regex', $id)),
        'logged_status' => $logged !== '' ? $logged : 'both',
        'param' => $param !== '' ? $param : 'exact_match',
        'count' => sp_int(sp_post_meta('seopress_404_count', $id)),
        'last_hit' => $last_hit > 0 ? $last_hit : null,
    ];
}

// ---------------------------------------------------------------------------
// Pro redirect-manager WRITE helpers (create / edit / delete of the seopress_404
// CPT). Shared by create-redirect / edit-redirect. The CPT supports a WIDER
// status set than a per-post/term redirect — see sp_redirect_cpt_types — and a
// redirect only FIRES when enabled='yes' AND a destination is set AND a valid
// type is stored AND the logged_status row is present (SeoPress's runtime SQL
// inner-joins on logged_status), so create-redirect must default it.
// ---------------------------------------------------------------------------

/**
 * The redirect status codes valid for the Pro seopress_404 CPT. WIDER than the
 * per-entity set (sp_redirect_types) — 410 (Gone) and 451 (Unavailable For Legal
 * Reasons) need no destination. Mirrors the Pro REST handler's get_valid_types().
 *
 * @return list<int>
 */
function sp_redirect_cpt_types(): array
{
    return [301, 302, 307, 410, 451];
}

/**
 * The Query-Parameters mode whitelist for a seopress_404 redirect. SeoPress only
 * stores one of these three; an unknown value is rejected in validation.
 *
 * @return list<string>
 */
function sp_redirect_params(): array
{
    return ['exact_match', 'without_param', 'with_ignored_param'];
}

/**
 * Validate the SUBSET of redirect write fields present in $args BEFORE any post
 * is inserted / updated (the apply step is all-or-nothing). $require_origin is
 * NOT a bool flag — it is the create-vs-edit mode passed as a non-empty string
 * literal: 'require' makes a missing/empty origin an error (create needs one),
 * 'optional' lets origin be absent (an edit need not touch it) but still rejects
 * an explicitly-empty origin (a redirect always needs a source). Returns a
 * WP_Error on the first problem, or null when the payload is safe to apply.
 *
 * @param array<string, mixed> $args
 */
function sp_validate_redirect_input(array $args, string $require_origin): ?WP_Error
{
    $origin_error = sp_validate_redirect_origin($args, $require_origin);
    if ($origin_error instanceof WP_Error) {
        return $origin_error;
    }
    if (array_key_exists('type', $args) && !sp_redirect_type_clears($args['type'])) {
        if (!in_array(sp_int($args['type']), sp_redirect_cpt_types(), strict: true)) {
            return sp_invalid('type must be one of 301, 302, 307, 410, 451 (or 0 to clear)');
        }
    }
    if (array_key_exists('logged_status', $args) && $args['logged_status'] !== '') {
        if (!in_array($args['logged_status'], sp_logged_statuses(), strict: true)) {
            return sp_invalid('logged_status must be both, only_logged_in or only_not_logged_in');
        }
    }
    if (array_key_exists('param', $args) && $args['param'] !== '') {
        if (!in_array($args['param'], sp_redirect_params(), strict: true)) {
            return sp_invalid('param must be exact_match, without_param or with_ignored_param');
        }
    }
    foreach (['enabled', 'enabled_regex'] as $flag) {
        if (array_key_exists($flag, $args) && !is_bool($args[$flag])) {
            return sp_invalid("{$flag} must be a boolean");
        }
    }
    return sp_validate_category_ids($args);
}

/**
 * Normalise a redirect origin EXACTLY as SeoPress's REST handler does before it
 * stores the value as the seopress_404 post_title: strip an anchor, reduce a full
 * URL to its path (+query), then strip the LEADING SLASH. This last step is
 * load-bearing — the front-end matcher (seopress_301_do_redirect) ltrims the
 * request URI before looking the title up, so an origin stored WITH a leading
 * slash never matches and the redirect silently never fires. Hardening mirrors
 * sanitize_text_field WITHOUT its %XX-stripping loop (which would destroy
 * percent-encoded paths and regex metacharacters). Mirrors
 * SEOPressPro\Actions\Api\Redirections::normalize_origin.
 */
function sp_redirect_normalize_origin(string $origin): string
{
    $origin = trim($origin);
    $hash = strpos($origin, needle: '#');
    if ($hash !== false) {
        $origin = substr($origin, offset: 0, length: $hash);
    }
    /** @var array<string, mixed>|false $parts */
    $parts = wp_parse_url($origin);
    if (is_array($parts) && is_string($parts['host'] ?? null) && $parts['host'] !== '') {
        $path = is_string($parts['path'] ?? null) ? $parts['path'] : '/';
        $query = is_string($parts['query'] ?? null) ? $parts['query'] : '';
        $origin = $query !== '' ? $path . '?' . $query : $path;
    }
    $origin = ltrim($origin, characters: '/');
    $origin = wp_check_invalid_utf8($origin);
    $origin = wp_strip_all_tags($origin);
    $collapsed = preg_replace('/[\r\n\t ]+/', replacement: ' ', subject: $origin);
    return trim(is_string($collapsed) ? $collapsed : $origin);
}

/**
 * Validate the origin field for a redirect write. 'require' (create): origin must
 * be present and non-blank. 'optional' (edit): origin may be absent, but if sent
 * it must not be blank — a redirect always needs a source. A non-string origin,
 * or one that NORMALISES to empty (e.g. "/" or a bare domain — which would
 * otherwise create a catch-all redirect), is rejected.
 *
 * @param array<string, mixed> $args
 */
function sp_validate_redirect_origin(array $args, string $require_origin): ?WP_Error
{
    $present = array_key_exists('origin', $args);
    if (!$present) {
        return $require_origin === 'require' ? sp_invalid('origin is required') : null;
    }
    if (!is_string($args['origin']) || sp_redirect_normalize_origin($args['origin']) === '') {
        return sp_invalid('origin must be a non-empty source path or regex (it normalises to empty)');
    }
    return null;
}

/**
 * Validate category_ids (when present): it must be an array, and every entry must
 * coerce to a positive integer term id. Returns null when absent or all valid.
 *
 * @param array<string, mixed> $args
 */
function sp_validate_category_ids(array $args): ?WP_Error
{
    if (!array_key_exists('category_ids', $args)) {
        return null;
    }
    if (!is_array($args['category_ids'])) {
        return sp_invalid('category_ids must be an array of term ids');
    }
    /** @var mixed $raw */
    foreach ($args['category_ids'] as $raw) {
        if (sp_int($raw) <= 0) {
            return sp_invalid('category_ids must contain positive integer term ids');
        }
    }
    return null;
}

/**
 * Coerce a validated category_ids payload to a clean list of positive int term
 * ids (duplicates kept as-is — wp_set_post_terms de-dupes). Validation must have
 * passed first; this only normalises.
 *
 * @param array<string, mixed> $args
 * @return list<int>
 */
function sp_redirect_category_id_list(array $args): array
{
    $ids = [];
    if (!array_key_exists('category_ids', $args) || !is_array($args['category_ids'])) {
        return $ids;
    }
    /** @var mixed $raw */
    foreach ($args['category_ids'] as $raw) {
        $id = sp_int($raw);
        if ($id > 0) {
            $ids[] = $id;
        }
    }
    return $ids;
}

/**
 * Apply the SUBSET of redirect write fields present in $args to a seopress_404
 * post via the writer closures. Validation (sp_validate_redirect_input) MUST have
 * passed first. enabled / enabled_regex true => 'yes', false => delete the row
 * (SeoPress treats an absent checkbox as off); destination empty => delete else
 * esc_url_raw; type cleared => delete else store the int; logged_status / param
 * whitelisted => store, '' => delete. origin and category_ids are handled by the
 * caller (they touch the post row / taxonomy, not postmeta).
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 * @param array<string, mixed>           $args
 */
function sp_apply_redirect_fields(callable $set, callable $del, array $args): void
{
    if (array_key_exists('destination', $args)) {
        sp_apply_url('_seopress_redirections_value', $set, $del, sp_str($args['destination']));
    }
    if (array_key_exists('type', $args)) {
        sp_apply_redirect_cpt_type($set, $del, $args['type']);
    }
    if (array_key_exists('enabled', $args)) {
        sp_apply_checkbox('_seopress_redirections_enabled', $set, $del, $args['enabled']);
    }
    if (array_key_exists('enabled_regex', $args)) {
        sp_apply_checkbox('_seopress_redirections_enabled_regex', $set, $del, $args['enabled_regex']);
    }
    if (array_key_exists('logged_status', $args)) {
        sp_apply_text('_seopress_redirections_logged_status', $set, $del, sp_str($args['logged_status']));
    }
    if (array_key_exists('param', $args)) {
        sp_apply_text('_seopress_redirections_param', $set, $del, sp_str($args['param']));
    }
}

/**
 * Apply the seopress_404 redirect status code: a value in the CPT type set stores
 * as an int, any other / 0 deletes the row (demoting the redirect back to a bare
 * 404). Validation has already rejected truly invalid codes; this stays defensive.
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 */
function sp_apply_redirect_cpt_type(callable $set, callable $del, mixed $raw): void
{
    $type = sp_int($raw);
    $key = '_seopress_redirections_type';
    if (!in_array($type, sp_redirect_cpt_types(), strict: true)) {
        $del($key);
        return;
    }
    $set($key, $type);
}

/**
 * The post's content-analysis target keywords, split from the single
 * comma-separated string SeoPress stores in _seopress_analysis_target_kw.
 *
 * @return list<string>
 */
function sp_target_keywords(int $post_id): array
{
    $raw = sp_str(sp_post_meta('_seopress_analysis_target_kw', $post_id));
    if (trim($raw) === '') {
        return [];
    }
    $parts = array_map(static fn(string $kw): string => sp_clean_text(trim($kw)), explode(',', $raw));
    return array_values(array_filter($parts, static fn(string $kw): bool => $kw !== ''));
}

/**
 * Assemble the friendly SEO shape for a post from its SeoPress postmeta. All
 * string fields are the RAW per-post overrides; an empty string means "no
 * override — inherit the global template/default", never a stored blank (SeoPress
 * deletes the row on clear). robots.effective resolves the global / per-post-type
 * defaults so a globally-forced directive is never under-reported.
 *
 * @return array<string, mixed>
 */
function sp_post_seo(WP_Post $post): array
{
    $id = $post->ID;
    $get = static fn(string $key, int $pid): mixed => sp_post_meta($key, $pid);
    $robots = sp_robots_block($get, $id);
    $robots['effective'] = sp_effective_robots($post, $robots);

    return [
        'title' => sp_clean_text(sp_str($get('_seopress_titles_title', $id))),
        'description' => sp_clean_text(sp_str($get('_seopress_titles_desc', $id))),
        'robots' => $robots,
        'target_keywords' => sp_target_keywords($id),
        'facebook' => sp_social_block('fb', $get, $id),
        'twitter' => sp_social_block('twitter', $get, $id),
        'redirect' => sp_redirect_block($get, $id),
    ];
}

/**
 * Assemble the friendly SEO shape for a term from its SeoPress termmeta. Term SEO
 * reuses the SAME _seopress_* keys as posts (only the storage table differs) and
 * is a FREE feature. robots.effective resolves against the per-taxonomy global
 * defaults (seopress_titles_tax_titles[<taxonomy>]).
 *
 * @return array<string, mixed>
 */
function sp_term_seo(WP_Term $term): array
{
    $id = $term->term_id;
    $get = static fn(string $key, int $tid): mixed => sp_term_meta($key, $tid);
    $robots = sp_robots_block($get, $id);
    $robots['effective'] = sp_effective_term_robots($term, $robots);

    return [
        'title' => sp_clean_text(sp_str($get('_seopress_titles_title', $id))),
        'description' => sp_clean_text(sp_str($get('_seopress_titles_desc', $id))),
        'robots' => $robots,
        'facebook' => sp_social_block('fb', $get, $id),
        'twitter' => sp_social_block('twitter', $get, $id),
        'redirect' => sp_redirect_block($get, $id),
    ];
}

/**
 * The EFFECTIVE robots directives for a term: the global seopress_titles_no*
 * toggles or the per-taxonomy defaults (seopress_titles_tax_titles[<taxonomy>])
 * force the directive; otherwise the per-term override stands.
 *
 * @param array{noindex: bool, nofollow: bool, nosnippet: bool, noimageindex: bool, breadcrumbs_hide: bool, canonical: string} $per
 * @return array{noindex: bool, nofollow: bool, nosnippet: bool, noimageindex: bool}
 */
function sp_effective_term_robots(WP_Term $term, array $per): array
{
    $scoped = sp_scoped_titles('seopress_titles_tax_titles', $term->taxonomy);
    return sp_resolve_effective($per, $scoped);
}

// ---------------------------------------------------------------------------
// Write / apply helpers (scope-agnostic: used by edit-post-seo and, in S3,
// edit-term-seo). Every apply helper takes two writer closures bound to the
// entity id so the same code drives postmeta or termmeta:
//   $set = fn(string $key, mixed $value): mixed => update_post_meta($id, ...)
//   $del = fn(string $key): bool              => delete_post_meta($id, ...)
// SeoPress's storage rule shapes them all: an EMPTY value DELETES the row
// ("inherit the global/per-type default"), never stores '' or 'no'.
// ---------------------------------------------------------------------------

/**
 * The redirect status codes valid for a single-post / single-term redirect.
 * Broader redirect types (410/451) belong to the Pro seopress_404 CPT, not the
 * per-entity redirect this ability writes.
 *
 * @return list<int>
 */
function sp_redirect_types(): array
{
    return [301, 302, 307];
}

/**
 * The audience whitelist for a per-entity redirect's logged_status. SeoPress's
 * runtime inner-joins on this row, so an unknown value would silently break the
 * redirect — reject it in validation rather than store it.
 *
 * @return list<string>
 */
function sp_logged_statuses(): array
{
    return ['both', 'only_logged_in', 'only_not_logged_in'];
}

/**
 * The robots boolean keys this ability writes. breadcrumbs_hide is deliberately
 * absent: it is Pro-written/read-only, so a provided value is ignored.
 *
 * @return array<string, string> friendly flag => meta key
 */
function sp_robots_write_keys(): array
{
    return [
        'noindex' => '_seopress_robots_index',
        'nofollow' => '_seopress_robots_follow',
        'nosnippet' => '_seopress_robots_snippet',
        'noimageindex' => '_seopress_robots_imageindex',
    ];
}

/**
 * Validate every provided field of an edit-post/term-seo input BEFORE any write
 * happens (the apply step is all-or-nothing). Returns a WP_Error on the first
 * problem, or null when the whole payload is safe to apply.
 *
 * @param array<string, mixed> $args
 */
function sp_validate_post_seo_input(array $args): ?WP_Error
{
    foreach (['robots', 'facebook', 'twitter', 'redirect'] as $key) {
        if (array_key_exists($key, $args) && !is_array($args[$key])) {
            return sp_invalid("{$key} must be an object");
        }
    }
    if (array_key_exists('target_keywords', $args)) {
        if (!is_array($args['target_keywords'])) {
            return sp_invalid('target_keywords must be an array of strings');
        }
        // SeoPress stores keywords as ONE comma-delimited row with no escaping, so a
        // keyword containing a comma would be silently split into several. Reject it
        // loudly instead of corrupting the list.
        $comma = ',';
        foreach (array_filter($args['target_keywords'], callback: 'is_string') as $kw) {
            if (str_contains($kw, $comma)) {
                return sp_invalid(
                    'a target keyword cannot contain a comma (SeoPress uses commas to delimit keywords, so it would split into separate keywords)',
                );
            }
        }
    }

    if (array_key_exists('robots', $args) && is_array($args['robots'])) {
        $robots_error = sp_validate_robots($args['robots']);
        if ($robots_error instanceof WP_Error) {
            return $robots_error;
        }
    }
    if (array_key_exists('redirect', $args) && is_array($args['redirect'])) {
        return sp_validate_redirect($args['redirect']);
    }
    return null;
}

/**
 * Scope-agnostic alias for sp_validate_post_seo_input. The validator is NOT
 * post-specific (it only checks robots / redirect / social block typing), so
 * edit-term-seo reuses it under this neutral name — same logic, one source.
 *
 * @param array<string, mixed> $args
 */
function sp_validate_seo_input(array $args): ?WP_Error
{
    return sp_validate_post_seo_input($args);
}

/**
 * Standard partial-merge input rejection (HTTP 400).
 */
function sp_invalid(string $message): WP_Error
{
    return new WP_Error('seopress_invalid_input', $message, ['status' => 400]);
}

/**
 * Every recognised robots flag must be a boolean. breadcrumbs_hide is allowed in
 * the payload (so a round-tripped read shape can be passed straight back) but is
 * ignored on write — its type is still checked for a clean contract.
 *
 * @param array<array-key, mixed> $robots
 */
function sp_validate_robots(array $robots): ?WP_Error
{
    foreach (array_keys(sp_robots_write_keys()) as $flag) {
        if (array_key_exists($flag, $robots) && !is_bool($robots[$flag])) {
            return sp_invalid("robots.{$flag} must be a boolean");
        }
    }
    return null;
}

/**
 * Validate the per-entity redirect block: type (when set) must be one of the
 * single-entity codes, and logged_status (when set) must be whitelisted.
 *
 * @param array<array-key, mixed> $redirect
 */
function sp_validate_redirect(array $redirect): ?WP_Error
{
    if (array_key_exists('type', $redirect) && !sp_redirect_type_clears($redirect['type'])) {
        $type = sp_int($redirect['type']);
        if (!in_array($type, sp_redirect_types(), strict: true)) {
            return sp_invalid('redirect.type must be one of 301, 302, 307 (or 0 to clear)');
        }
    }
    if (array_key_exists('logged_status', $redirect) && $redirect['logged_status'] !== '') {
        if (!in_array($redirect['logged_status'], sp_logged_statuses(), strict: true)) {
            return sp_invalid('redirect.logged_status must be both, only_logged_in or only_not_logged_in');
        }
    }
    return null;
}

/**
 * Whether a provided redirect.type means "clear the redirect status code"
 * (0, '', or a non-numeric blank). Keeps sp_validate_redirect's branching flat.
 */
function sp_redirect_type_clears(mixed $type): bool
{
    return $type === '' || $type === null || sp_int($type) === 0;
}

/**
 * Set a sanitised meta value, or DELETE the row when the value is empty (the
 * SeoPress "no override" rule). $key is the leading literal at the call site;
 * the sanitiser is a callable (NOT a bool flag, so it never trips the
 * no-boolean-flag-parameter gate) — the three wrappers below bind it.
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 * @param callable(string): string       $sanitize
 */
function sp_apply_clean(string $key, callable $set, callable $del, callable $sanitize, string $value): void
{
    if ($value === '') {
        $del($key);
        return;
    }
    $set($key, $sanitize($value));
}

/**
 * Apply a single-line text field (sanitize_text_field; empty clears the row).
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 */
function sp_apply_text(string $key, callable $set, callable $del, string $value): void
{
    sp_apply_clean($key, $set, $del, static fn(string $v): string => sanitize_text_field($v), $value);
}

/**
 * Apply a multi-line text field (sanitize_textarea_field PRESERVES newlines;
 * empty clears the row).
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 */
function sp_apply_textarea(string $key, callable $set, callable $del, string $value): void
{
    sp_apply_clean($key, $set, $del, static fn(string $v): string => sanitize_textarea_field($v), $value);
}

/**
 * Apply a URL field (esc_url_raw drops disallowed protocols; empty clears it).
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 */
function sp_apply_url(string $key, callable $set, callable $del, string $value): void
{
    sp_apply_clean($key, $set, $del, static fn(string $v): string => esc_url_raw($v), $value);
}

/**
 * Apply a SeoPress checkbox meta from its incoming value: a strict boolean true
 * stores the literal 'yes', anything else (false / absent-sense) DELETEs the row
 * (inherit). $key first keeps it the leading literal; $value is mixed (not a
 * bool flag) so this never trips the no-boolean-flag-parameter gate.
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 */
function sp_apply_checkbox(string $key, callable $set, callable $del, mixed $value): void
{
    if ($value !== true) {
        $del($key);
        return;
    }
    $yes = 'yes';
    $set($key, $yes);
}

/**
 * Apply the robots boolean block: each provided flag true => 'yes', false =>
 * delete. breadcrumbs_hide and any unknown key are ignored.
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 * @param array<array-key, mixed>        $robots
 */
function sp_apply_robots(callable $set, callable $del, array $robots): void
{
    foreach (sp_robots_write_keys() as $flag => $key) {
        if (!array_key_exists($flag, $robots)) {
            continue;
        }
        sp_apply_checkbox($key, $set, $del, $robots[$flag]);
    }
}

/**
 * Apply the content-analysis target keywords: SeoPress stores them as ONE
 * comma-joined string in a single row. An empty list DELETES the row.
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 * @param array<array-key, mixed>        $keywords
 */
function sp_apply_keywords(callable $set, callable $del, array $keywords): void
{
    $clean = array_values(array_filter(
        array_map(static fn(mixed $kw): string => trim(sp_str($kw)), $keywords),
        static fn(string $kw): bool => $kw !== '',
    ));
    if ($clean === []) {
        $del('_seopress_analysis_target_kw');
        return;
    }
    $set('_seopress_analysis_target_kw', sanitize_text_field(implode(', ', $clean)));
}

/**
 * Apply one social block (Facebook or Twitter/X). $prefix is 'fb' or 'twitter'.
 * Field-by-field merge: title/description empty => delete; image empty => delete
 * the URL AND its attachment-id/width/height companions; a positive image_id
 * updates the companion, 0/empty deletes it.
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 * @param array<array-key, mixed>        $block
 */
function sp_apply_social(callable $set, callable $del, string $prefix, array $block): void
{
    $base = "_seopress_social_{$prefix}";
    if (array_key_exists('title', $block)) {
        sp_apply_text("{$base}_title", $set, $del, sp_str($block['title']));
    }
    if (array_key_exists('description', $block)) {
        sp_apply_textarea("{$base}_desc", $set, $del, sp_str($block['description']));
    }
    if (array_key_exists('image', $block)) {
        sp_apply_social_image($set, $del, $base, sp_str($block['image']));
    }
    if (array_key_exists('image_id', $block)) {
        sp_apply_image_id("{$base}_img_attachment_id", $set, $del, $block['image_id']);
    }
}

/**
 * Apply a social image URL: empty clears the URL and every dependent companion
 * (attachment id / width / height — meaningless without the image).
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 */
function sp_apply_social_image(callable $set, callable $del, string $base, string $image): void
{
    if ($image === '') {
        $del("{$base}_img");
        $del("{$base}_img_attachment_id");
        $del("{$base}_img_width");
        $del("{$base}_img_height");
        return;
    }
    $set("{$base}_img", esc_url_raw($image));
}

/**
 * Apply an attachment-id companion: a positive id updates it, 0/empty deletes it.
 * $key first so it is the leading literal at the call site.
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 */
function sp_apply_image_id(string $key, callable $set, callable $del, mixed $raw): void
{
    $id = sp_int($raw);
    if ($id <= 0) {
        $del($key);
        return;
    }
    $set($key, $id);
}

/**
 * Apply the per-entity redirect block. enabled true => 'yes', false => delete;
 * value empty => delete else esc_url_raw; a valid type updates as an int, 0 /
 * blank deletes; logged_status whitelisted updates, empty deletes.
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 * @param array<array-key, mixed>        $redirect
 */
function sp_apply_redirect(callable $set, callable $del, array $redirect): void
{
    if (array_key_exists('enabled', $redirect)) {
        sp_apply_checkbox('_seopress_redirections_enabled', $set, $del, $redirect['enabled']);
    }
    if (array_key_exists('value', $redirect)) {
        sp_apply_url('_seopress_redirections_value', $set, $del, sp_str($redirect['value']));
    }
    if (array_key_exists('type', $redirect)) {
        sp_apply_redirect_type($set, $del, $redirect['type']);
    }
    if (array_key_exists('logged_status', $redirect)) {
        sp_apply_text('_seopress_redirections_logged_status', $set, $del, sp_str($redirect['logged_status']));
    }
}

/**
 * Apply the redirect status code: a value in {301,302,307} updates as an int,
 * any other / 0 deletes the row (validation has already rejected truly invalid
 * codes; this stays defensive).
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 */
function sp_apply_redirect_type(callable $set, callable $del, mixed $raw): void
{
    $type = sp_int($raw);
    $key = '_seopress_redirections_type';
    if (!in_array($type, sp_redirect_types(), strict: true)) {
        $del($key);
        return;
    }
    $set($key, $type);
}

/**
 * Apply a whole validated SEO payload to one entity via the writer closures.
 * Validation (sp_validate_post_seo_input) MUST have passed first — this only
 * writes. Each block is applied only when the field is present in $args.
 *
 * @param callable(string, mixed): mixed $set
 * @param callable(string): bool         $del
 * @param array<string, mixed>           $args
 */
function sp_apply_seo(callable $set, callable $del, array $args): void
{
    if (array_key_exists('title', $args)) {
        sp_apply_text('_seopress_titles_title', $set, $del, sp_str($args['title']));
    }
    if (array_key_exists('description', $args)) {
        sp_apply_textarea('_seopress_titles_desc', $set, $del, sp_str($args['description']));
    }
    if (array_key_exists('canonical', $args)) {
        sp_apply_url('_seopress_robots_canonical', $set, $del, sp_str($args['canonical']));
    }
    if (array_key_exists('robots', $args) && is_array($args['robots'])) {
        sp_apply_robots($set, $del, $args['robots']);
    }
    if (array_key_exists('target_keywords', $args) && is_array($args['target_keywords'])) {
        sp_apply_keywords($set, $del, $args['target_keywords']);
    }
    if (array_key_exists('facebook', $args) && is_array($args['facebook'])) {
        sp_apply_social($set, $del, prefix: 'fb', block: $args['facebook']);
    }
    if (array_key_exists('twitter', $args) && is_array($args['twitter'])) {
        sp_apply_social($set, $del, prefix: 'twitter', block: $args['twitter']);
    }
    if (array_key_exists('redirect', $args) && is_array($args['redirect'])) {
        sp_apply_redirect($set, $del, $args['redirect']);
    }
}

// ---------------------------------------------------------------------------
// Settings-snapshot helpers (used by get-settings). These read EXPLICIT named
// subkeys out of a SeoPress option array — they never echo a whole option, so
// no secret subkey can leak by construction.
// ---------------------------------------------------------------------------

/**
 * Read a SeoPress option as an array ([] when the option is absent or not an
 * array). $name first keeps it the leading literal at the call site.
 *
 * @return array<array-key, mixed>
 */
function sp_option_array(string $name): array
{
    /** @var mixed $raw */
    $raw = get_option($name);
    return is_array($raw) ? $raw : [];
}

/**
 * The string value of one named subkey of an option array ('' when absent / not
 * a scalar). $key first so it is a leading literal at the call site.
 *
 * @param array<array-key, mixed> $option
 */
function sp_sub_str(string $key, array $option): string
{
    return sp_str($option[$key] ?? null);
}

/**
 * Whether one named subkey of an option array is "on" (sp_opt_on semantics).
 * $key first so it is a leading literal at the call site.
 *
 * @param array<array-key, mixed> $option
 */
function sp_sub_on(string $key, array $option): bool
{
    return sp_opt_on($option[$key] ?? null);
}

/**
 * The LIST of slugs whose entry in a nested per-slug map is "present" — used for
 * the single_titles / tax_titles override lists and the sitemap include lists,
 * where the FULL nested map must NEVER be returned (noise + leak surface). A row
 * counts when $predicate(entry) is true; the order is the map's own key order.
 *
 * @param array<array-key, mixed>      $map
 * @param callable(mixed): bool        $predicate
 * @return list<string>
 */
function sp_slug_list(array $map, callable $predicate): array
{
    $slugs = [];
    /** @var mixed $entry */
    foreach ($map as $slug => $entry) {
        if (!is_string($slug) || $slug === '' || !$predicate($entry)) {
            continue;
        }
        $slugs[] = $slug;
    }
    return $slugs;
}

/**
 * Predicate: a single_titles / tax_titles entry counts as an "override" when it
 * is a non-empty array (SeoPress writes a per-type sub-array only once a field
 * is set; an all-empty sub-array still means "no real override").
 */
function sp_titles_entry_set(mixed $entry): bool
{
    if (!is_array($entry)) {
        return false;
    }
    /** @var mixed $value */
    foreach ($entry as $value) {
        if ($value !== '' && $value !== null && $value !== false) {
            return true;
        }
    }
    return false;
}

/**
 * Predicate: a sitemap post_types_list / taxonomies_list entry is "included"
 * when its 'include' subkey is truthy (SeoPress unsets the subkey to exclude).
 */
function sp_sitemap_included(mixed $entry): bool
{
    return is_array($entry) && sp_opt_on($entry['include'] ?? null);
}

// ---------------------------------------------------------------------------
// MANUAL structured-data (rich snippets) helpers. SeoPress Pro stores the
// per-post MANUAL schemas as ONE postmeta on the content post:
// `_seopress_pro_schemas_manual` — a numerically-indexed array of "schema
// rows". Each row is an assoc array { '_seopress_pro_rich_snippets_type' =>
// '<type>', '<field key>' => '<value>', ... }. This is DISTINCT from the
// per-post AUTOMATIC-template override (`_seopress_pro_schemas`) — these
// helpers NEVER touch that one. Read with get_post_meta(single=true) ('' when
// none → []); write with update_post_meta. Mirrors the official Pro REST
// handler (SchemaManual::processGet/processPut).
// ---------------------------------------------------------------------------

/**
 * The meta key holding a row's schema type, and the postmeta key holding the
 * whole manual-schemas array. Named constants so call sites read clearly and
 * the literals live in one place.
 */
const SP_SCHEMA_TYPE_KEY = '_seopress_pro_rich_snippets_type';

const SP_SCHEMA_MANUAL_META = '_seopress_pro_schemas_manual';

const SP_SCHEMA_CUSTOM_KEY = '_seopress_pro_rich_snippets_custom';

/**
 * The 14 manual schema TYPE values (the `_seopress_pro_rich_snippets_type` row
 * value). The type value and the field-key STEM differ for several types (the
 * plural/singular quirk) — see sp_schema_field_catalog. This is the validation
 * whitelist for set-post-schema-type and the list-schema-fields enum.
 *
 * @return list<string>
 */
function sp_schema_types(): array
{
    return [
        'articles',
        'localbusiness',
        'recipes',
        'jobs',
        'videos',
        'events',
        'products',
        'softwareapp',
        'services',
        'review',
        'faq',
        'howto',
        'courses',
        'custom',
    ];
}

/**
 * The manual-schema field keys whose stored value is an ARRAY (a repeater),
 * not a scalar. Extracted from the FormSchema*::getTypeByField() classes (every
 * `return 'repeater_*'` case) — these are the keys whose value SeoPress's
 * processPut sanitises with map_deep(..., 'sanitize_text_field') instead of a
 * flat sanitize_text_field. Used to tag the catalog format hint and to pick the
 * right sanitiser on write. `custom` is handled separately (json-ld-script).
 *
 * @return list<string>
 */
function sp_schema_repeater_keys(): array
{
    return [
        '_seopress_pro_rich_snippets_faq',
        '_seopress_pro_rich_snippets_how_to',
        '_seopress_pro_rich_snippets_courses_offers',
        '_seopress_pro_rich_snippets_courses_instances',
        '_seopress_pro_rich_snippets_product_positive_notes',
        '_seopress_pro_rich_snippets_product_negative_notes',
    ];
}

/**
 * The static catalog mapping each manual schema TYPE to its EXACT field keys.
 * Every key is verbatim from the matching FormSchema class in the SeoPress Pro
 * source (extracted from src/Services/Forms/Schemas/FormSchema*.php — NOT
 * invented). The row's `_seopress_pro_rich_snippets_type` key is implicit (it
 * carries the type itself, not a content field) and is deliberately absent from
 * the per-type lists. `custom` is the single raw JSON-LD field; `faq` is a
 * single repeater field.
 *
 * @return array<string, list<string>>
 */
function sp_schema_field_catalog(): array
{
    /** @var array<string, list<string>>|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    return $cache = array_merge(
        sp_schema_catalog_content(),
        sp_schema_catalog_local(),
        sp_schema_catalog_commerce(),
        sp_schema_catalog_misc(),
    );
}

/**
 * Catalog slice: content types whose keys carry editorial fields (articles,
 * recipes, jobs, videos). Keys are verbatim from the FormSchema source.
 *
 * @return array<string, list<string>>
 */
function sp_schema_catalog_content(): array
{
    return [
        'articles' => [
            '_seopress_pro_rich_snippets_article_type',
            '_seopress_pro_rich_snippets_article_title',
            '_seopress_pro_rich_snippets_article_desc',
            '_seopress_pro_rich_snippets_article_author',
            '_seopress_pro_rich_snippets_article_img',
            '_seopress_pro_rich_snippets_article_img_width',
            '_seopress_pro_rich_snippets_article_img_height',
            '_seopress_pro_rich_snippets_article_speakable_css_selector',
            '_seopress_pro_rich_snippets_article_coverage_start_date',
            '_seopress_pro_rich_snippets_article_coverage_start_time',
            '_seopress_pro_rich_snippets_article_coverage_end_date',
            '_seopress_pro_rich_snippets_article_coverage_end_time',
        ],
        'recipes' => [
            '_seopress_pro_rich_snippets_recipes_name',
            '_seopress_pro_rich_snippets_recipes_desc',
            '_seopress_pro_rich_snippets_recipes_img',
            '_seopress_pro_rich_snippets_recipes_cat',
            '_seopress_pro_rich_snippets_recipes_cuisine',
            '_seopress_pro_rich_snippets_recipes_keywords',
            '_seopress_pro_rich_snippets_recipes_yield',
            '_seopress_pro_rich_snippets_recipes_calories',
            '_seopress_pro_rich_snippets_recipes_prep_time',
            '_seopress_pro_rich_snippets_recipes_cook_time',
            '_seopress_pro_rich_snippets_recipes_ingredient',
            '_seopress_pro_rich_snippets_recipes_instructions',
            '_seopress_pro_rich_snippets_recipes_video',
        ],
        'jobs' => [
            '_seopress_pro_rich_snippets_jobs_name',
            '_seopress_pro_rich_snippets_jobs_desc',
            '_seopress_pro_rich_snippets_jobs_employment_type',
            '_seopress_pro_rich_snippets_jobs_date_posted',
            '_seopress_pro_rich_snippets_jobs_valid_through',
            '_seopress_pro_rich_snippets_jobs_hiring_organization',
            '_seopress_pro_rich_snippets_jobs_hiring_same_as',
            '_seopress_pro_rich_snippets_jobs_hiring_logo',
            '_seopress_pro_rich_snippets_jobs_hiring_logo_width',
            '_seopress_pro_rich_snippets_jobs_hiring_logo_height',
            '_seopress_pro_rich_snippets_jobs_address_street',
            '_seopress_pro_rich_snippets_jobs_address_locality',
            '_seopress_pro_rich_snippets_jobs_address_region',
            '_seopress_pro_rich_snippets_jobs_postal_code',
            '_seopress_pro_rich_snippets_jobs_country',
            '_seopress_pro_rich_snippets_jobs_salary',
            '_seopress_pro_rich_snippets_jobs_salary_currency',
            '_seopress_pro_rich_snippets_jobs_salary_unit',
            '_seopress_pro_rich_snippets_jobs_remote',
            '_seopress_pro_rich_snippets_jobs_location_requirement',
            '_seopress_pro_rich_snippets_jobs_direct_apply',
            '_seopress_pro_rich_snippets_jobs_identifier_name',
            '_seopress_pro_rich_snippets_jobs_identifier_value',
        ],
        'videos' => [
            '_seopress_pro_rich_snippets_videos_name',
            '_seopress_pro_rich_snippets_videos_description',
            '_seopress_pro_rich_snippets_videos_img',
            '_seopress_pro_rich_snippets_videos_url',
            '_seopress_pro_rich_snippets_videos_date_posted',
            '_seopress_pro_rich_snippets_videos_duration',
        ],
    ];
}

/**
 * Catalog slice: place / event types (localbusiness, events, services). Keys are
 * verbatim from the FormSchema source.
 *
 * @return array<string, list<string>>
 */
function sp_schema_catalog_local(): array
{
    return [
        'localbusiness' => [
            '_seopress_pro_rich_snippets_lb_type',
            '_seopress_pro_rich_snippets_lb_name',
            '_seopress_pro_rich_snippets_lb_img',
            '_seopress_pro_rich_snippets_lb_street_addr',
            '_seopress_pro_rich_snippets_lb_city',
            '_seopress_pro_rich_snippets_lb_state',
            '_seopress_pro_rich_snippets_lb_pc',
            '_seopress_pro_rich_snippets_lb_country',
            '_seopress_pro_rich_snippets_lb_tel',
            '_seopress_pro_rich_snippets_lb_website',
            '_seopress_pro_rich_snippets_lb_price',
            '_seopress_pro_rich_snippets_lb_lat',
            '_seopress_pro_rich_snippets_lb_lon',
            '_seopress_pro_rich_snippets_lb_opening_hours',
            '_seopress_pro_rich_snippets_lb_cuisine',
            '_seopress_pro_rich_snippets_lb_menu',
            '_seopress_pro_rich_snippets_lb_accepts_reservations',
        ],
        'events' => [
            '_seopress_pro_rich_snippets_events_type',
            '_seopress_pro_rich_snippets_events_name',
            '_seopress_pro_rich_snippets_events_desc',
            '_seopress_pro_rich_snippets_events_img',
            '_seopress_pro_rich_snippets_events_status',
            '_seopress_pro_rich_snippets_events_attendance_mode',
            '_seopress_pro_rich_snippets_events_start_date',
            '_seopress_pro_rich_snippets_events_start_time',
            '_seopress_pro_rich_snippets_events_start_date_timezone',
            '_seopress_pro_rich_snippets_events_end_date',
            '_seopress_pro_rich_snippets_events_end_time',
            '_seopress_pro_rich_snippets_events_previous_start_date',
            '_seopress_pro_rich_snippets_events_previous_start_time',
            '_seopress_pro_rich_snippets_events_location_name',
            '_seopress_pro_rich_snippets_events_location_address',
            '_seopress_pro_rich_snippets_events_location_url',
            '_seopress_pro_rich_snippets_events_performer',
            '_seopress_pro_rich_snippets_events_organizer_name',
            '_seopress_pro_rich_snippets_events_organizer_url',
            '_seopress_pro_rich_snippets_events_offers_name',
            '_seopress_pro_rich_snippets_events_offers_cat',
            '_seopress_pro_rich_snippets_events_offers_price',
            '_seopress_pro_rich_snippets_events_offers_price_currency',
            '_seopress_pro_rich_snippets_events_offers_availability',
            '_seopress_pro_rich_snippets_events_offers_valid_from_date',
            '_seopress_pro_rich_snippets_events_offers_valid_from_time',
            '_seopress_pro_rich_snippets_events_offers_url',
        ],
        'services' => [
            '_seopress_pro_rich_snippets_service_type',
            '_seopress_pro_rich_snippets_service_name',
            '_seopress_pro_rich_snippets_service_description',
            '_seopress_pro_rich_snippets_service_slogan',
            '_seopress_pro_rich_snippets_service_img',
            '_seopress_pro_rich_snippets_service_lb_img',
            '_seopress_pro_rich_snippets_service_price',
            '_seopress_pro_rich_snippets_service_area',
            '_seopress_pro_rich_snippets_service_provider_name',
            '_seopress_pro_rich_snippets_service_provider_mobility',
            '_seopress_pro_rich_snippets_service_street_addr',
            '_seopress_pro_rich_snippets_service_city',
            '_seopress_pro_rich_snippets_service_state',
            '_seopress_pro_rich_snippets_service_pc',
            '_seopress_pro_rich_snippets_service_country',
            '_seopress_pro_rich_snippets_service_tel',
            '_seopress_pro_rich_snippets_service_lat',
            '_seopress_pro_rich_snippets_service_lon',
        ],
    ];
}

/**
 * Catalog slice: commerce / app types (products, softwareapp, review). Keys are
 * verbatim from the FormSchema source.
 *
 * @return array<string, list<string>>
 */
function sp_schema_catalog_commerce(): array
{
    return [
        'products' => [
            '_seopress_pro_rich_snippets_product_name',
            '_seopress_pro_rich_snippets_product_description',
            '_seopress_pro_rich_snippets_product_img',
            '_seopress_pro_rich_snippets_product_brand',
            '_seopress_pro_rich_snippets_product_sku',
            '_seopress_pro_rich_snippets_product_global_ids',
            '_seopress_pro_rich_snippets_product_global_ids_value',
            '_seopress_pro_rich_snippets_product_condition',
            '_seopress_pro_rich_snippets_product_energy_consumption',
            '_seopress_pro_rich_snippets_product_price',
            '_seopress_pro_rich_snippets_product_price_currency',
            '_seopress_pro_rich_snippets_product_price_valid_date',
            '_seopress_pro_rich_snippets_product_availability',
            '_seopress_pro_rich_snippets_product_positive_notes',
            '_seopress_pro_rich_snippets_product_negative_notes',
        ],
        'softwareapp' => [
            '_seopress_pro_rich_snippets_softwareapp_name',
            '_seopress_pro_rich_snippets_softwareapp_os',
            '_seopress_pro_rich_snippets_softwareapp_cat',
            '_seopress_pro_rich_snippets_softwareapp_price',
            '_seopress_pro_rich_snippets_softwareapp_currency',
            '_seopress_pro_rich_snippets_softwareapp_rating',
            '_seopress_pro_rich_snippets_softwareapp_max_rating',
        ],
        'review' => [
            '_seopress_pro_rich_snippets_review_item',
            '_seopress_pro_rich_snippets_review_item_type',
            '_seopress_pro_rich_snippets_review_img',
            '_seopress_pro_rich_snippets_review_body',
            '_seopress_pro_rich_snippets_review_rating',
            '_seopress_pro_rich_snippets_review_max_rating',
        ],
    ];
}

/**
 * Catalog slice: repeater-heavy / freeform types (faq, howto, courses, custom).
 * Keys are verbatim from the FormSchema source.
 *
 * @return array<string, list<string>>
 */
function sp_schema_catalog_misc(): array
{
    return [
        'faq' => [
            '_seopress_pro_rich_snippets_faq',
        ],
        'howto' => [
            '_seopress_pro_rich_snippets_how_to_name',
            '_seopress_pro_rich_snippets_how_to_desc',
            '_seopress_pro_rich_snippets_how_to_img',
            '_seopress_pro_rich_snippets_how_to_total_time',
            '_seopress_pro_rich_snippets_how_to_cost',
            '_seopress_pro_rich_snippets_how_to_currency',
            '_seopress_pro_rich_snippets_how_to',
        ],
        'courses' => [
            '_seopress_pro_rich_snippets_courses_title',
            '_seopress_pro_rich_snippets_courses_desc',
            '_seopress_pro_rich_snippets_courses_school',
            '_seopress_pro_rich_snippets_courses_website',
            '_seopress_pro_rich_snippets_courses_offers',
            '_seopress_pro_rich_snippets_courses_instances',
        ],
        'custom' => [
            '_seopress_pro_rich_snippets_custom',
        ],
    ];
}

/**
 * The coarse value-format hint for one manual-schema field key: a small fixed
 * vocabulary the agent reads from list-schema-fields. `custom` is json-ld-script
 * (raw <script> JSON-LD); the repeater keys are repeater(array); otherwise the
 * key SUFFIX refines a scalar to url/date/time/number, defaulting to string.
 */
function sp_schema_field_format(string $key): string
{
    if ($key === SP_SCHEMA_CUSTOM_KEY) {
        return 'json-ld-script';
    }
    if (in_array($key, sp_schema_repeater_keys(), strict: true)) {
        return 'repeater(array)';
    }
    return sp_schema_scalar_format($key);
}

/**
 * The scalar format hint inferred from a field key's SUFFIX. Kept separate from
 * sp_schema_field_format so neither branches past the complexity threshold.
 */
function sp_schema_scalar_format(string $key): string
{
    foreach (sp_schema_format_suffixes() as $suffix => $format) {
        if (str_ends_with($key, $suffix)) {
            return $format;
        }
    }
    return 'string';
}

/**
 * The key-suffix → format map for scalar manual-schema fields, longest-first so
 * a more specific suffix wins (e.g. *_valid_from_date before *_date is moot, but
 * *_img_width must NOT match *_img). Order matters: the first match wins.
 *
 * @return array<string, string>
 */
function sp_schema_format_suffixes(): array
{
    return [
        '_img_width' => 'number',
        '_img_height' => 'number',
        '_logo_width' => 'number',
        '_logo_height' => 'number',
        '_date' => 'date',
        '_time' => 'time',
        '_img' => 'url',
        '_logo' => 'url',
        '_url' => 'url',
        '_website' => 'url',
        '_menu' => 'url',
        '_same_as' => 'url',
    ];
}

/**
 * The field-key → format catalog for ONE schema type: the list-schema-fields
 * payload. Returns null when the type is unknown (the caller turns that into a
 * validation error). The type's own `_seopress_pro_rich_snippets_type` key is
 * not a content field, so it is not listed.
 *
 * @return list<array{key: string, format: string}>|null
 */
function sp_schema_fields_for(string $type): ?array
{
    $catalog = sp_schema_field_catalog();
    if (!array_key_exists($type, $catalog)) {
        return null;
    }
    $fields = [];
    foreach ($catalog[$type] as $key) {
        $fields[] = ['key' => $key, 'format' => sp_schema_field_format($key)];
    }
    return $fields;
}

/**
 * The raw manual-schemas array stored on a post. get_post_meta(single=true)
 * returns '' when no manual schemas exist; anything non-array reads as []. Only
 * numerically-indexed array rows that are themselves arrays are kept (defensive
 * against a malformed stored value).
 *
 * @return list<array<array-key, mixed>>
 */
function sp_schema_rows(int $post_id): array
{
    /** @var mixed $raw */
    $raw = sp_post_meta(SP_SCHEMA_MANUAL_META, $post_id);
    if (!is_array($raw)) {
        return [];
    }
    $rows = [];
    /** @var mixed $row */
    foreach ($raw as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rows[] = $row;
    }
    return $rows;
}

/**
 * Persist the manual-schemas array back onto a post. Re-indexes to a clean
 * 0-based list (SeoPress and the schema renderer iterate numerically). An empty
 * list still writes [] rather than deleting the row — set-post-schema-type only
 * ever adds, and edit only ever merges, so a present-but-empty array is the
 * correct "no manual schemas" state and round-trips cleanly.
 *
 * @param list<array<array-key, mixed>> $rows
 */
function sp_schema_write_rows(int $post_id, array $rows): void
{
    update_post_meta($post_id, meta_key: SP_SCHEMA_MANUAL_META, meta_value: array_values($rows));
}

/**
 * One row's TYPE value ('' when the row has no / a non-string type).
 *
 * @param array<array-key, mixed> $row
 */
function sp_schema_row_type(array $row): string
{
    return sp_str($row[SP_SCHEMA_TYPE_KEY] ?? null);
}

/**
 * Run $fn while holding a per-post advisory lock, so a read-modify-write of a
 * shared postmeta blob (the _seopress_pro_schemas_manual rows) cannot interleave
 * with a concurrent call on the SAME post and silently lose a row. Uses a MySQL
 * named lock (GET_LOCK) scoped by blog id + post id. If the lock can't be acquired
 * (e.g. a non-MySQL backend without GET_LOCK) it degrades to running $fn unlocked —
 * no worse than not guarding. The lock is always released.
 *
 * @param callable(): (array<string, mixed>|WP_Error) $fn
 * @return array<string, mixed>|WP_Error
 */
function sp_with_post_lock(int $post_id, callable $fn): array|WP_Error
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $key = 'nvp_sp_lock_' . get_current_blog_id() . '_' . $post_id;
    $timeout = 5;
    /** @var mixed $got */
    $got = $wpdb->get_var((string) $wpdb->prepare('SELECT GET_LOCK(%s, %d)', $key, $timeout));
    try {
        return $fn();
    } finally {
        if ($got === '1') {
            $wpdb->query((string) $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $key));
        }
    }
}

/**
 * Whether SeoPress will actually RENDER manual rich snippets on the front end.
 * The per-post manual rows only emit JSON-LD when the site-wide rich-snippets
 * feature is on (OptionPro::getRichSnippetEnable === '1', stored in
 * seopress_pro_option_name['seopress_rich_snippets_enable']) — distinct from the
 * 'rich-snippets' module toggle. Strict '1' mirrors SeoPress's own render gate,
 * so a stored schema row whose feature is off is reported as won't-render.
 */
function sp_schema_rendering_enabled(): bool
{
    return (sp_option_array('seopress_pro_option_name')['seopress_rich_snippets_enable'] ?? null) === '1';
}

/**
 * The LEAN view of a post's manual schemas: only the POPULATED (non-empty) keys
 * of each row are surfaced, so the output stays small even when a type has dozens
 * of possible fields. Each entry is { index, type, fields: { key: value } }. An
 * empty-string / empty-array value is treated as unpopulated and omitted. The
 * row's type key is lifted to `type` and never duplicated inside `fields`.
 * rendering_enabled reflects the site-wide feature gate (see above).
 *
 * @return array{
 *   post_id: int,
 *   schemas: list<array{index: int, type: string, fields: array<string, mixed>}>,
 *   disabled_all: bool,
 *   rendering_enabled: bool
 * }
 */
function sp_schema_view(int $post_id): array
{
    $schemas = [];
    foreach (sp_schema_rows($post_id) as $index => $row) {
        $schemas[] = [
            'index' => $index,
            'type' => sp_schema_row_type($row),
            'fields' => sp_schema_populated_fields($row),
        ];
    }

    return [
        'post_id' => $post_id,
        'schemas' => $schemas,
        'disabled_all' => sp_post_meta('_seopress_pro_rich_snippets_disable_all', $post_id) === '1',
        // Stored rows only emit JSON-LD when the site-wide feature is enabled.
        'rendering_enabled' => sp_schema_rendering_enabled(),
    ];
}

/**
 * The populated content fields of one row: every key except the type key whose
 * value is non-empty. Keeps get-post-schema lean — a row with a dozen blank
 * fields surfaces nothing but its type.
 *
 * @param array<array-key, mixed> $row
 * @return array<string, mixed>
 */
function sp_schema_populated_fields(array $row): array
{
    $fields = [];
    /** @var mixed $value */
    foreach ($row as $key => $value) {
        if (!is_string($key) || $key === SP_SCHEMA_TYPE_KEY) {
            continue;
        }
        if ($value === '' || $value === [] || $value === null) {
            continue;
        }
        $fields[$key] = $value;
    }
    return $fields;
}

/**
 * Validate that every key in a provided `fields` payload belongs to the row's
 * CURRENT type per the catalog. The raw JSON-LD `custom` key is allowed ONLY
 * when the row type is `custom`. Returns a WP_Error (pointing at
 * list-schema-fields) on the first unknown key, or null when all keys are valid.
 *
 * @param array<array-key, mixed> $fields
 */
function sp_schema_validate_fields(string $type, array $fields): ?WP_Error
{
    $valid = sp_schema_field_catalog()[$type] ?? [];
    $repeaters = sp_schema_repeater_keys();
    foreach (array_keys($fields) as $key) {
        if (!is_string($key) || !in_array($key, $valid, strict: true)) {
            $hint = $valid === [] ? 'an unknown type' : implode(', ', $valid);
            // Sanitise the reflected (attacker-controlled) bad key before echoing it.
            $shown = sanitize_text_field((string) $key);
            return sp_invalid(
                "field key '{$shown}' is not valid for schema type '{$type}'. "
                . "Call list-schema-fields(type: '{$type}') for the valid keys. Valid keys: {$hint}",
            );
        }
        // A scalar field must NOT receive an array value: SeoPress would store the
        // nested array and its front-end renderer then fatals (str_replace on an
        // array → a 500 on the page). Only the repeater keys (and the custom
        // JSON-LD string) take a non-scalar — reject an array on any other key.
        if (is_array($fields[$key]) && !in_array($key, $repeaters, strict: true)) {
            return sp_invalid(
                "field '{$key}' expects a scalar value, not an array — only the repeater fields take arrays (see list-schema-fields)",
            );
        }
    }
    return null;
}

/**
 * Sanitise ONE manual-schema field value EXACTLY like SeoPress's processPut:
 * the raw JSON-LD `custom` key via wp_kses (preserving the <script type> tag);
 * an array value (a repeater) via map_deep + sanitize_text_field; any other
 * value via sanitize_text_field. $key first keeps it the leading literal.
 */
function sp_schema_sanitize_field(string $key, mixed $value): mixed
{
    if ($key === SP_SCHEMA_CUSTOM_KEY) {
        return wp_kses(sp_str($value), ['script' => ['type' => []]]);
    }
    if (is_array($value)) {
        $clean = $value;
        return map_deep($clean, callback: 'sanitize_text_field');
    }
    return sanitize_text_field(sp_str($value));
}

/**
 * Merge a validated `fields` payload into a row IN PLACE. A field whose value is
 * '' DELETES that key from the row (the SeoPress "no value" rule, mirroring the
 * per-post-SEO clearing semantics); any other value is sanitised and stored. The
 * type key is never touched here (set-post-schema-type owns it).
 *
 * @param array<array-key, mixed> $row
 * @param array<array-key, mixed> $fields
 * @return array<array-key, mixed>
 */
function sp_schema_merge_fields(array $row, array $fields): array
{
    /** @var mixed $value */
    foreach ($fields as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        if ($value === '') {
            unset($row[$key]);
            continue;
        }
        $row[$key] = sp_schema_sanitize_field($key, $value);
    }
    return $row;
}
