<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Yoast;

use WP_Error;
use WP_Post;
use WP_Term;
use WPSEO_Meta;
use WPSEO_Post_Type;
use WPSEO_Redirect;
use WPSEO_Redirect_Manager;
use WPSEO_Taxonomy_Meta;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Detect Yoast presence, edition, and version. Cached per request.
 *
 * @return array{active: bool, edition: 'free'|'premium'|null, version: string|null, premium: bool}
 */
function yoast_detect_setup(): array
{
    /** @var array{active: bool, edition: 'free'|'premium'|null, version: string|null, premium: bool}|null $cache */
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $active = defined('WPSEO_VERSION');
    $premium = defined('WPSEO_PREMIUM_VERSION');

    $edition = null;
    if ($active) {
        $edition = $premium ? 'premium' : 'free';
    }

    return $cache = [
        'active' => $active,
        'edition' => $edition,
        'version' => $active ? (string) constant('WPSEO_VERSION') : null,
        'premium' => $premium,
    ];
}

/**
 * Standard "Yoast is not active" error, returned by every ability's pre-flight.
 */
function yoast_not_active(): WP_Error
{
    return new WP_Error('yoast_not_active', 'Yoast SEO is not active', ['status' => 400]);
}

/**
 * Map Yoast's post `meta-robots-noindex` code to a friendly label. The raw
 * codes are counter-intuitive: '0' = use the post-type default, '2' = index,
 * '1' = noindex.
 *
 * @return 'default'|'index'|'noindex'
 */
function yoast_post_noindex_label(string $raw): string
{
    return match ($raw) {
        '1' => 'noindex',
        '2' => 'index',
        default => 'default',
    };
}

/**
 * Normalise a term `wpseo_noindex` value (already a string enum upstream) to
 * the same friendly labels used for posts.
 *
 * @return 'default'|'index'|'noindex'
 */
function yoast_term_noindex_label(string $raw): string
{
    return in_array($raw, ['index', 'noindex'], strict: true) ? $raw : 'default';
}

/**
 * Parse the space/comma-separated `meta-robots-adv` value into the subset of
 * recognised advanced-robots tokens, dropping anything else (including 'none').
 *
 * @return list<string>
 */
function yoast_advanced_robots(string $raw): array
{
    if ($raw === '' || $raw === 'none') {
        return [];
    }
    $parts = preg_split('/[\s,]+/', strtolower($raw));
    if (!is_array($parts)) {
        return [];
    }
    return array_values(array_intersect($parts, ['noimageindex', 'noarchive', 'nosnippet']));
}

/**
 * Map a friendly index label to Yoast's post `meta-robots-noindex` code
 * (0 = post-type default, 2 = index, 1 = noindex). Returns null for an
 * unrecognised label.
 */
function yoast_noindex_to_code(string $label): ?string
{
    return match ($label) {
        'default' => '0',
        'index' => '2',
        'noindex' => '1',
        default => null,
    };
}

/**
 * Map a friendly follow label to Yoast's post `meta-robots-nofollow` code
 * (0 = follow, 1 = nofollow). Returns null for an unrecognised label.
 */
function yoast_follow_to_code(string $label): ?string
{
    return match ($label) {
        'follow' => '0',
        'nofollow' => '1',
        default => null,
    };
}

/**
 * Validate and serialise an advanced-robots token list into Yoast's stored
 * comma-separated string. Returns a WP_Error if any token is unrecognised.
 *
 * @param array<array-key, mixed> $tokens
 */
function yoast_advanced_to_raw(array $tokens): string|WP_Error
{
    $allowed = ['noimageindex', 'noarchive', 'nosnippet'];
    $strings = array_values(array_filter($tokens, callback: 'is_string'));
    $invalid = count($strings) !== count($tokens) || array_diff($strings, $allowed) !== [];
    if ($invalid) {
        $msg = 'advanced robots tokens must each be one of: ' . implode(', ', $allowed);
        return new WP_Error('yoast_invalid_robots', $msg, ['status' => 400]);
    }
    return implode(',', array_values(array_unique($strings)));
}

/**
 * Coerce a stored Yoast meta value (always a string at rest) to a boolean.
 */
function yoast_meta_bool(mixed $raw): bool
{
    if (is_bool($raw)) {
        return $raw;
    }
    if (is_int($raw)) {
        return $raw === 1;
    }
    return is_string($raw) && in_array(strtolower($raw), ['1', 'true', 'on', 'yes'], strict: true);
}

/**
 * Coerce a mixed option/meta value to a string, treating non-strings as empty.
 */
function yoast_str(mixed $raw): string
{
    return is_string($raw) ? $raw : '';
}

/**
 * Coerce a mixed option/meta value to an integer, treating non-numerics as 0.
 */
function yoast_int(mixed $raw): int
{
    if (!is_numeric($raw)) {
        return 0;
    }
    // A JSON number larger than PHP_INT_MAX decodes to a float and passes an
    // integer schema; casting it to int directly emits a "not representable"
    // warning and stores a garbage value, so range-guard before the cast.
    $float = (float) $raw;
    if (!is_finite($float) || $float > (float) PHP_INT_MAX || $float < (float) PHP_INT_MIN) {
        return 0;
    }
    return (int) $raw;
}

/**
 * Sanitise a free-text SEO value (strips tags/control chars), mirroring how
 * Yoast sanitises its registered text meta. Template variables like
 * %%title%% / %%sep%% are plain text and survive.
 */
function yoast_clean_text(string $value): string
{
    return sanitize_text_field($value);
}

/**
 * Sanitise a URL value with WordPress's protocol allowlist (drops javascript:
 * etc.), mirroring how Yoast sanitises canonical and social image fields.
 */
function yoast_clean_url(string $value): string
{
    return esc_url_raw($value);
}

/**
 * Resolve a post id to a usable WP_Post, rejecting revisions, auto-drafts, and
 * non-existent ids. SEO metadata is valid on any real post status.
 */
function yoast_resolve_post(mixed $id): WP_Post|WP_Error
{
    if (!is_int($id) && !(is_string($id) && ctype_digit($id))) {
        return new WP_Error('yoast_invalid_post', 'post_id must be a numeric post id', ['status' => 400]);
    }
    if ((int) $id <= 0) {
        // get_post(0) falls back to the global $post; reject so the wrong post is never returned.
        return new WP_Error('yoast_post_not_found', "post not found: {$id}", ['status' => 404]);
    }
    /** @var WP_Post|null $post */
    $post = get_post((int) $id);
    if (!$post instanceof WP_Post) {
        return new WP_Error('yoast_post_not_found', "post not found: {$id}", ['status' => 404]);
    }
    if ($post->post_type === 'revision' || $post->post_status === 'auto-draft') {
        return new WP_Error('yoast_invalid_post', "post {$id} is a revision or auto-draft", ['status' => 400]);
    }
    return $post;
}

/**
 * Resolve a (term id, taxonomy) pair to a WP_Term, validating the taxonomy.
 */
function yoast_resolve_term(mixed $term_id, mixed $taxonomy): WP_Term|WP_Error
{
    if (!is_string($taxonomy) || $taxonomy === '') {
        return new WP_Error('yoast_invalid_term', 'taxonomy is required and must be a string', ['status' => 400]);
    }
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('yoast_invalid_term', "taxonomy does not exist: {$taxonomy}", ['status' => 400]);
    }
    if (!is_int($term_id) && !(is_string($term_id) && ctype_digit($term_id))) {
        return new WP_Error('yoast_invalid_term', 'term_id must be a numeric term id', ['status' => 400]);
    }
    $term = get_term((int) $term_id, $taxonomy);
    if (!$term instanceof WP_Term) {
        return new WP_Error('yoast_term_not_found', "term not found in {$taxonomy}: {$term_id}", ['status' => 404]);
    }
    return $term;
}

/**
 * Read the primary-category id, stored as a standalone postmeta outside the
 * WPSEO_Meta registry. Returns null when unset.
 */
function yoast_primary_category(int $post_id): ?int
{
    /** @var mixed $raw */
    $raw = get_post_meta($post_id, key: '_yoast_wpseo_primary_category', single: true);
    if (!is_numeric($raw)) {
        return null;
    }
    $id = (int) $raw;
    return $id > 0 ? $id : null;
}

/**
 * The robots sub-block of a post's SEO, with the 0/2/1 noindex codes mapped to
 * friendly labels and the advanced tokens parsed to a list.
 *
 * @return array{index: 'default'|'index'|'noindex', follow: 'follow'|'nofollow', advanced: list<string>}
 */
function yoast_post_robots(int $post_id): array
{
    $nofollow = (string) WPSEO_Meta::get_value('meta-robots-nofollow', $post_id);
    return [
        'index' => yoast_post_noindex_label((string) WPSEO_Meta::get_value('meta-robots-noindex', $post_id)),
        'follow' => $nofollow === '1' ? 'nofollow' : 'follow',
        'advanced' => yoast_advanced_robots((string) WPSEO_Meta::get_value('meta-robots-adv', $post_id)),
    ];
}

/**
 * One social-preview block (OpenGraph or X/Twitter) for a post.
 *
 * @param 'opengraph'|'twitter' $network
 * @return array{title: string, description: string, image: string, image_id: int}
 */
function yoast_post_social(string $network, int $post_id): array
{
    return [
        'title' => (string) WPSEO_Meta::get_value("{$network}-title", $post_id),
        'description' => (string) WPSEO_Meta::get_value("{$network}-description", $post_id),
        'image' => (string) WPSEO_Meta::get_value("{$network}-image", $post_id),
        'image_id' => (int) WPSEO_Meta::get_value("{$network}-image-id", $post_id),
    ];
}

/**
 * Assemble the friendly, fully-mapped SEO shape for a post. Scores
 * (computed read-only analysis values) are included only when
 * `$options['include_scores']` is true.
 *
 * @param array{include_scores?: bool} $options
 * @return array<string, mixed>
 */
function yoast_post_seo(int $post_id, array $options = []): array
{
    $include_scores = ($options['include_scores'] ?? false) === true;

    $seo = [
        'focus_keyphrase' => (string) WPSEO_Meta::get_value('focuskw', $post_id),
        'seo_title' => (string) WPSEO_Meta::get_value('title', $post_id),
        'meta_description' => (string) WPSEO_Meta::get_value('metadesc', $post_id),
        'canonical' => (string) WPSEO_Meta::get_value('canonical', $post_id),
        'breadcrumb_title' => (string) WPSEO_Meta::get_value('bctitle', $post_id),
        'cornerstone' => yoast_meta_bool(WPSEO_Meta::get_value('is_cornerstone', $post_id)),
        'robots' => yoast_post_robots($post_id),
        'opengraph' => yoast_post_social('opengraph', $post_id),
        'twitter' => yoast_post_social('twitter', $post_id),
        'schema' => [
            'page_type' => (string) WPSEO_Meta::get_value('schema_page_type', $post_id),
            'article_type' => (string) WPSEO_Meta::get_value('schema_article_type', $post_id),
        ],
        'primary_category' => yoast_primary_category($post_id),
        'redirect' => (string) WPSEO_Meta::get_value('redirect', $post_id),
    ];

    if ($include_scores) {
        $seo['scores'] = [
            'seo' => (int) WPSEO_Meta::get_value('linkdex', $post_id),
            'readability' => (int) WPSEO_Meta::get_value('content_score', $post_id),
            'inclusive_language' => (int) WPSEO_Meta::get_value('inclusive_language_score', $post_id),
        ];
    }

    return $seo;
}

/**
 * Read one term-meta value as a string, treating absence as empty. The key is
 * the first parameter (bare, no `wpseo_` prefix) so callers pass it as a
 * leading literal.
 */
function yoast_term_meta_string(string $key, int $term_id, string $taxonomy): string
{
    return yoast_str(WPSEO_Taxonomy_Meta::get_term_meta($term_id, $taxonomy, $key));
}

/**
 * Read a term's full Yoast meta array (prefixed `wpseo_*` keys, merged with
 * defaults). Used by edit-term-seo to merge changes over the existing values
 * before saving — Yoast resets keys absent from a set_values() payload.
 *
 * @return array<array-key, mixed>
 */
function yoast_term_meta_all(int $term_id, string $taxonomy): array
{
    /** @var mixed $all */
    $all = WPSEO_Taxonomy_Meta::get_term_meta($term_id, $taxonomy);
    return is_array($all) ? $all : [];
}

/**
 * One social-preview block for a term.
 *
 * @param 'opengraph'|'twitter' $network
 * @return array{title: string, description: string, image: string, image_id: int}
 */
function yoast_term_social(string $network, WP_Term $term): array
{
    $tax = $term->taxonomy;
    $id = $term->term_id;
    $image_id = yoast_term_meta_string("{$network}-image-id", $id, $tax);
    return [
        'title' => yoast_term_meta_string("{$network}-title", $id, $tax),
        'description' => yoast_term_meta_string("{$network}-description", $id, $tax),
        'image' => yoast_term_meta_string("{$network}-image", $id, $tax),
        'image_id' => ctype_digit($image_id) ? (int) $image_id : 0,
    ];
}

/**
 * Assemble the friendly SEO shape for a term. Terms carry only a noindex toggle
 * (no nofollow / advanced robots / schema in Yoast's term model).
 *
 * @return array<string, mixed>
 */
function yoast_term_seo(WP_Term $term): array
{
    $tax = $term->taxonomy;
    $id = $term->term_id;
    return [
        'focus_keyphrase' => yoast_term_meta_string('focuskw', $id, $tax),
        'seo_title' => yoast_term_meta_string('title', $id, $tax),
        'meta_description' => yoast_term_meta_string('desc', $id, $tax),
        'canonical' => yoast_term_meta_string('canonical', $id, $tax),
        'breadcrumb_title' => yoast_term_meta_string('bctitle', $id, $tax),
        'cornerstone' => yoast_meta_bool(yoast_term_meta_string('is_cornerstone', $id, $tax)),
        'robots' => ['index' => yoast_term_noindex_label(yoast_term_meta_string('noindex', $id, $tax))],
        'opengraph' => yoast_term_social('opengraph', $term),
        'twitter' => yoast_term_social('twitter', $term),
    ];
}

/**
 * Public post-type names Yoast manages SEO settings for (excludes attachments).
 *
 * @return list<string>
 */
function yoast_public_post_type_names(): array
{
    // Use Yoast's own accessible-post-type set so the list matches exactly what
    // Yoast manages SEO for: it applies is_post_type_viewable AND the
    // wpseo_accessible_post_types filter (e.g. Elementor removes its Library),
    // and includes attachment exactly when Yoast does.
    if (class_exists('WPSEO_Post_Type')) {
        return array_values(WPSEO_Post_Type::get_accessible_post_types());
    }
    $types = get_post_types(['public' => true], output: 'names');
    return array_values(array_filter($types, static fn(string $name): bool => is_post_type_viewable($name)));
}

/**
 * Public taxonomy names that are valid term-SEO targets. All public taxonomies
 * qualify, including post_format (which Yoast's settings always surface).
 *
 * @return list<string>
 */
function yoast_public_taxonomy_names(): array
{
    return array_values(get_taxonomies(['public' => true], output: 'names'));
}

/**
 * True when Yoast SEO Premium's redirect module is available.
 */
function yoast_redirect_premium(): bool
{
    return defined('WPSEO_PREMIUM_VERSION') && class_exists('WPSEO_Redirect_Manager') && class_exists('WPSEO_Redirect');
}

/**
 * Error returned when a redirect ability is reached without Premium.
 */
function yoast_redirect_not_available(): WP_Error
{
    return new WP_Error(
        'yoast_redirects_unavailable',
        'Yoast SEO Premium (with the redirect module) is required for redirects',
        ['status' => 400],
    );
}

/**
 * The allowed redirect HTTP type codes.
 *
 * @return list<int>
 */
function yoast_redirect_types(): array
{
    return [301, 302, 307, 410, 451];
}

/**
 * Whether a redirect type requires a target URL. 410 (gone) and 451 (legal)
 * have no target; 301/302/307 do.
 */
function yoast_redirect_needs_target(int $type): bool
{
    return in_array($type, [301, 302, 307], strict: true);
}

/**
 * Validate a redirect type + target pair. Returns a WP_Error on a bad type or a
 * missing target for a type that needs one, null otherwise.
 */
function yoast_redirect_validate(int $type, string $target): ?WP_Error
{
    if (!in_array($type, yoast_redirect_types(), strict: true)) {
        return new WP_Error('yoast_invalid_redirect', 'type must be one of: 301, 302, 307, 410, 451', [
            'status' => 400,
        ]);
    }
    if (yoast_redirect_needs_target($type) && $target === '') {
        return new WP_Error('yoast_invalid_redirect', 'a 301, 302, or 307 redirect requires a target', [
            'status' => 400,
        ]);
    }
    return null;
}

/**
 * Compact output row for a single redirect.
 *
 * @return array{origin: string, target: string, type: int, format: string}
 */
function yoast_redirect_to_row(WPSEO_Redirect $redirect): array
{
    return [
        'origin' => $redirect->get_origin(),
        'target' => $redirect->get_target(),
        'type' => $redirect->get_type(),
        'format' => $redirect->get_format(),
    ];
}

/**
 * Find a redirect by origin (across both formats). Delegates to Yoast's
 * get_redirect(), which normalises the origin (leading slash, home-url
 * stripping) and searches the stored redirects. Returns null when none matches.
 */
function yoast_find_redirect(string $origin): ?WPSEO_Redirect
{
    $found = (new WPSEO_Redirect_Manager())->get_redirect($origin);
    return $found instanceof WPSEO_Redirect ? $found : null;
}

/**
 * True when the string contains a C0 control character or DEL. Yoast's save
 * layer silently drops such redirects, so we reject them up front instead of
 * reporting a write that never happened.
 */
function yoast_redirect_has_control_chars(string $value): bool
{
    return preg_match('/[\x00-\x1F\x7F]/', $value) === 1;
}

/**
 * Whether a redirect already exists for the given origin. Delegates to Yoast's
 * get_redirect() so it uses the SAME matching as WPSEO_Redirect_Option::add()
 * (origin_is(): slash-insensitive and format-agnostic). This catches a
 * cross-format duplicate and a leading-slash / home-URL variant that an exact
 * string compare would miss, so the ability returns a precise 409 rather than a
 * generic failure.
 */
function yoast_redirect_origin_taken(string $origin): bool
{
    return yoast_find_redirect($origin) instanceof WPSEO_Redirect;
}

/**
 * Replicate the error-level checks Yoast's WPSEO_Redirect_Validator runs before
 * a redirect is persisted (the AJAX/admin path), but without its HTTP
 * accessibility/endpoint warnings: reject control characters, an origin that
 * normalises to empty, a self-redirect loop, and a per-format origin collision.
 *
 * $current_origin is the existing (already-normalised) origin on an edit, or
 * null on create; the uniqueness check is skipped when the origin is unchanged.
 * Checks run against the constructed redirect's normalised origin/target so they
 * match what Yoast actually stores.
 */
function yoast_redirect_guard(
    WPSEO_Redirect $redirect,
    string $raw_origin,
    string $raw_target,
    ?string $current_origin,
): ?WP_Error {
    if (yoast_redirect_has_control_chars($raw_origin) || yoast_redirect_has_control_chars($raw_target)) {
        return new WP_Error(
            'yoast_invalid_redirect',
            'origin and target must not contain line breaks or control characters',
            ['status' => 400],
        );
    }

    $origin = $redirect->get_origin();
    if ($origin === '') {
        return new WP_Error('yoast_invalid_redirect', 'origin is required (it normalises to an empty path)', [
            'status' => 400,
        ]);
    }

    if ($redirect->get_format() !== 'regex' && $redirect->get_target() !== '' && $redirect->get_target() === $origin) {
        return new WP_Error('yoast_invalid_redirect', 'origin and target must differ (a self-redirect loops)', [
            'status' => 400,
        ]);
    }

    if ($origin !== $current_origin && yoast_redirect_origin_taken($origin)) {
        return new WP_Error('yoast_redirect_exists', "a redirect for that origin already exists: {$origin}", [
            'status' => 409,
        ]);
    }

    return null;
}
