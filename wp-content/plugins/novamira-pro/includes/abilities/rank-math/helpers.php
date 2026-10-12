<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\RankMath;

use RankMath\Helper;
use RankMath\Schema\DB;
use WP_Error;
use WP_Post;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Detect Rank Math presence + version. Cached per request.
 *
 * @return array{active: bool, version: string|null}
 */
function rm_detect_setup(): array
{
    /** @var array{active: bool, version: string|null}|null $cache */
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $active = defined('RANK_MATH_VERSION');

    return $cache = [
        'active' => $active,
        'version' => $active ? (string) constant('RANK_MATH_VERSION') : null,
    ];
}

/**
 * Standard "Rank Math is not active" error.
 */
function rm_not_active(): WP_Error
{
    return new WP_Error('rank_math_not_active', 'Rank Math is not active', ['status' => 400]);
}

/**
 * Enabled Rank Math module slugs, read from the persisted option (the runtime
 * module manager is not always bootstrapped outside a full page load).
 *
 * @return list<string>
 */
function rm_active_modules(): array
{
    /** @var mixed $mods */
    $mods = get_option('rank_math_modules', []);
    return is_array($mods) ? array_values(array_filter($mods, callback: 'is_string')) : [];
}

/**
 * Whether a Rank Math module is enabled (option-based gate). Lenient on purpose:
 * this gates whether module-dependent abilities register at all, so it must not
 * depend on Rank Math's runtime module manager being bootstrapped.
 */
function rm_module_active(string $module): bool
{
    return in_array($module, rm_active_modules(), strict: true);
}

/**
 * Whether the host plugin for a known host-gated Rank Math 3rd-party module is
 * present. Mirrors Rank Math's setup_3rd_party() gating (module/class-manager.php)
 * so we can drop host-gated modules even when Rank Math's module manager is not
 * bootstrapped to ask directly. Modules that are not host-gated return true.
 */
function rm_module_host_present(string $module): bool
{
    return match ($module) {
        'woocommerce' => class_exists('WooCommerce'),
        'acf' => function_exists('acf'),
        'buddypress' => class_exists('BuddyPress'),
        'bbpress' => function_exists('is_bbpress'),
        'web-stories' => defined('WEBSTORIES_VERSION'),
        default => true,
    };
}

/**
 * Enabled modules as Rank Math's runtime actually treats them: the stored option
 * minus any module whose host plugin is absent (e.g. the woocommerce/acf modules
 * when those plugins are inactive). This mirrors the output of Rank Math's own
 * Helper::get_active_modules() without depending on its module manager being
 * bootstrapped (it is not, on installs that are not connected to the Rank Math
 * account, nor under wp-cli) — that helper dereferences a null manager and fatals
 * in those contexts, so we replicate its host gating directly. Never fatals.
 *
 * @return list<string>
 */
function rm_runtime_active_modules(): array
{
    return array_values(array_filter(rm_active_modules(), static fn(string $module): bool => rm_module_host_present(
        $module,
    )));
}

/**
 * Coerce a mixed meta value to a string.
 */
function rm_str(mixed $raw): string
{
    return is_string($raw) ? $raw : '';
}

/**
 * Coerce a mixed meta value to a non-negative integer (0 when not numeric or
 * out of range).
 */
function rm_int(mixed $raw): int
{
    if (!is_numeric($raw)) {
        return 0;
    }
    $float = (float) $raw;
    // >= because (float) PHP_INT_MAX rounds UP to 9223372036854775808.0 (the int
    // max is not exactly representable as a double); a value equal to it would
    // pass a `>` guard and then cast out of range, emitting a PHP 8.1+ warning
    // and yielding PHP_INT_MIN. Real ids never approach this boundary.
    if (!is_finite($float) || $float >= (float) PHP_INT_MAX || $float < 0.0) {
        return 0;
    }
    return (int) $raw;
}

/**
 * Coerce a stored Rank Math flag to a boolean. Rank Math uses 'on'/'off' and
 * '1'/'' for toggles like pillar_content, and stores schema isPrimary as the
 * integer 1, bool true, or string '1' depending on which importer wrote it.
 * Rank Math itself reads all of those with !empty(), so any non-zero number is
 * truthy here too (while 'off'/'' stay false via the string allow-list).
 */
function rm_bool(mixed $raw): bool
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
 * Coerce a stored robots value to a clean list of recognised tokens.
 *
 * @return list<string>
 */
function rm_robots_array(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $allowed = ['index', 'noindex', 'nofollow', 'noarchive', 'noimageindex', 'nosnippet'];
    return array_values(array_intersect(array_filter($raw, callback: 'is_string'), $allowed));
}

/**
 * Split Rank Math's comma-separated focus_keyword string into a trimmed list.
 *
 * @return list<string>
 */
function rm_focus_keywords(mixed $raw): array
{
    if (!is_string($raw) || trim($raw) === '') {
        return [];
    }
    $parts = array_map(callback: 'trim', array: explode(',', $raw));
    return array_values(array_filter($parts, static fn(string $kw): bool => $kw !== ''));
}

/**
 * Map a robots token list to a friendly index label. index/noindex are mutually
 * exclusive; neither present means "inherit the post-type default".
 *
 * @param list<string> $robots
 * @return 'default'|'index'|'noindex'
 */
function rm_index_label(array $robots): string
{
    if (in_array('noindex', $robots, strict: true)) {
        return 'noindex';
    }
    return in_array('index', $robots, strict: true) ? 'index' : 'default';
}

/**
 * @param list<string> $robots
 * @return 'follow'|'nofollow'
 */
function rm_follow_label(array $robots): string
{
    return in_array('nofollow', $robots, strict: true) ? 'nofollow' : 'follow';
}

/**
 * @param list<string> $robots
 * @return list<string>
 */
function rm_advanced_robots(array $robots): array
{
    return array_values(array_intersect($robots, ['noarchive', 'noimageindex', 'nosnippet']));
}

/**
 * Build Rank Math's robots token array from friendly values. index/noindex are
 * mutually exclusive; 'default' (or any unrecognised index) emits neither
 * (inherit). Advanced tokens are appended.
 *
 * @param string $index  default|index|noindex
 * @param string $follow follow|nofollow
 * @param list<string> $advanced
 * @return list<string>
 */
function rm_robots_tokens(string $index, string $follow, array $advanced): array
{
    $tokens = [];
    if ($index === 'index') {
        $tokens[] = 'index';
    }
    if ($index === 'noindex') {
        $tokens[] = 'noindex';
    }
    if ($follow === 'nofollow') {
        $tokens[] = 'nofollow';
    }
    foreach (['noarchive', 'noimageindex', 'nosnippet'] as $token) {
        if (!in_array($token, $advanced, strict: true)) {
            continue;
        }
        $tokens[] = $token;
    }
    return $tokens;
}

/**
 * Sanitise a free-text SEO value: strip HTML/script markup but PRESERVE Rank
 * Math replace-variables like %category% / %currentyear%. WordPress's
 * sanitize_text_field() removes %XY percent-hex octets, so '%category%' would be
 * corrupted to 'tegory%'; wp_kses() with no allowed tags is exactly what Rank
 * Math's own field sanitizer (wp_filter_nohtml_kses) uses and leaves the
 * variables intact. (URL fields go through rm_clean_url instead.)
 */
function rm_clean_text(string $value): string
{
    return trim(wp_kses($value, allowed_html: []));
}

/**
 * Sanitise a focus keyword. Rank Math stores focus keywords via
 * sanitize_text_field (CMB2::sanitize_focus_keywords) — unlike the title /
 * description fields it keeps '&' and '<' literal, so the keyword still matches
 * the term in the post content. Keywords are search terms, not templates, so
 * they carry no Rank Math %variables% for sanitize_text_field to mangle.
 */
function rm_clean_keyword(string $value): string
{
    return sanitize_text_field($value);
}

/**
 * Sanitise a URL value with WordPress's protocol allowlist (drops javascript:).
 */
function rm_clean_url(string $value): string
{
    return esc_url_raw($value);
}

/**
 * Read a single postmeta value (key passed first so callers use a leading
 * literal).
 */
function rm_post_meta(string $key, int $post_id): mixed
{
    return get_post_meta($post_id, key: $key, single: true);
}

/**
 * Read a single termmeta value (key first).
 */
function rm_term_meta(string $key, int $term_id): mixed
{
    return get_term_meta($term_id, key: $key, single: true);
}

/**
 * Resolve a post id to a usable WP_Post, rejecting revisions, auto-drafts, and
 * non-existent / non-positive ids. Accepts an int, an integer-valued float (the
 * form an MCP client may send for an integer field — rest_is_integer passes
 * whole-number floats through un-coerced) or an all-digit string; rm_int()
 * normalises any of them.
 */
function rm_resolve_post(mixed $id): WP_Post|WP_Error
{
    if (!is_int($id) && !is_float($id) && !(is_string($id) && ctype_digit($id))) {
        return new WP_Error('rank_math_invalid_post', 'post_id must be a numeric post id', ['status' => 400]);
    }
    $post_id = rm_int($id);
    if ($post_id <= 0) {
        return new WP_Error('rank_math_post_not_found', "post not found: {$post_id}", ['status' => 404]);
    }
    /** @var WP_Post|null $post */
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return new WP_Error('rank_math_post_not_found', "post not found: {$post_id}", ['status' => 404]);
    }
    if ($post->post_type === 'revision' || $post->post_status === 'auto-draft') {
        return new WP_Error('rank_math_invalid_post', "post {$post_id} is a revision or auto-draft", ['status' => 400]);
    }
    return $post;
}

/**
 * Resolve a (term id, taxonomy) pair to a WP_Term, validating the taxonomy.
 */
function rm_resolve_term(mixed $term_id, mixed $taxonomy): WP_Term|WP_Error
{
    if (!is_string($taxonomy) || $taxonomy === '') {
        return new WP_Error('rank_math_invalid_term', 'taxonomy is required and must be a string', ['status' => 400]);
    }
    if (!taxonomy_exists($taxonomy)) {
        return new WP_Error('rank_math_invalid_term', "taxonomy does not exist: {$taxonomy}", ['status' => 400]);
    }
    if (!is_int($term_id) && !is_float($term_id) && !(is_string($term_id) && ctype_digit($term_id))) {
        return new WP_Error('rank_math_invalid_term', 'term_id must be a numeric term id', ['status' => 400]);
    }
    $id = rm_int($term_id);
    $term = get_term($id, $taxonomy);
    if (!$term instanceof WP_Term) {
        return new WP_Error('rank_math_term_not_found', "term not found in {$taxonomy}: {$id}", ['status' => 404]);
    }
    return $term;
}

/**
 * The robots sub-block from a stored token list.
 *
 * @param list<string> $robots
 * @return array{index: string, follow: string, advanced: list<string>}
 */
function rm_robots_block(array $robots): array
{
    return [
        'index' => rm_index_label($robots),
        'follow' => rm_follow_label($robots),
        'advanced' => rm_advanced_robots($robots),
    ];
}

/**
 * One social-preview block from a key prefix ('rank_math_facebook' or
 * 'rank_math_twitter') for a post.
 *
 * @return array{title: string, description: string, image: string, image_id: int}
 */
function rm_post_social(string $prefix, int $post_id): array
{
    return [
        'title' => rm_str(rm_post_meta("{$prefix}_title", $post_id)),
        'description' => rm_str(rm_post_meta("{$prefix}_description", $post_id)),
        'image' => rm_str(rm_post_meta("{$prefix}_image", $post_id)),
        'image_id' => rm_int(rm_post_meta("{$prefix}_image_id", $post_id)),
    ];
}

/**
 * Read the primary-category term id (0/empty -> null).
 */
function rm_primary_category(int $post_id): ?int
{
    $id = rm_int(rm_post_meta('rank_math_primary_category', $post_id));
    return $id > 0 ? $id : null;
}

/**
 * Assemble the friendly SEO shape for a post. The read-only SEO score is
 * included only when `$options['include_score']` is true.
 *
 * @param array{include_score?: bool} $options
 * @return array<string, mixed>
 */
function rm_post_seo(int $post_id, array $options = []): array
{
    $include_score = ($options['include_score'] ?? false) === true;

    $seo = [
        'focus_keywords' => rm_focus_keywords(rm_post_meta('rank_math_focus_keyword', $post_id)),
        'seo_title' => rm_str(rm_post_meta('rank_math_title', $post_id)),
        'meta_description' => rm_str(rm_post_meta('rank_math_description', $post_id)),
        'canonical' => rm_str(rm_post_meta('rank_math_canonical_url', $post_id)),
        'pillar_content' => rm_bool(rm_post_meta('rank_math_pillar_content', $post_id)),
        'robots' => rm_robots_block(rm_robots_array(rm_post_meta('rank_math_robots', $post_id))),
        'facebook' => rm_post_social('rank_math_facebook', $post_id),
        'twitter' => rm_post_social('rank_math_twitter', $post_id),
        'primary_category' => rm_primary_category($post_id),
        'schema_type' => rm_post_schema_type($post_id),
    ];

    if ($include_score) {
        $seo['scores'] = ['seo' => rm_int(rm_post_meta('rank_math_seo_score', $post_id))];
    }

    return $seo;
}

/**
 * One social-preview block for a term.
 *
 * @return array{title: string, description: string, image: string, image_id: int}
 */
function rm_term_social(string $prefix, int $term_id): array
{
    return [
        'title' => rm_str(rm_term_meta("{$prefix}_title", $term_id)),
        'description' => rm_str(rm_term_meta("{$prefix}_description", $term_id)),
        'image' => rm_str(rm_term_meta("{$prefix}_image", $term_id)),
        'image_id' => rm_int(rm_term_meta("{$prefix}_image_id", $term_id)),
    ];
}

/**
 * Assemble the friendly SEO shape for a term (real Rank Math termmeta).
 *
 * @return array<string, mixed>
 */
function rm_term_seo(WP_Term $term): array
{
    $id = $term->term_id;
    return [
        'focus_keywords' => rm_focus_keywords(rm_term_meta('rank_math_focus_keyword', $id)),
        'seo_title' => rm_str(rm_term_meta('rank_math_title', $id)),
        'meta_description' => rm_str(rm_term_meta('rank_math_description', $id)),
        'canonical' => rm_str(rm_term_meta('rank_math_canonical_url', $id)),
        'robots' => rm_robots_block(rm_robots_array(rm_term_meta('rank_math_robots', $id))),
        'facebook' => rm_term_social('rank_math_facebook', $id),
        'twitter' => rm_term_social('rank_math_twitter', $id),
    ];
}

/**
 * Bust Rank Math's per-request Settings cache so a read reflects a same-request
 * update_option (e.g. an inspect -> execute-php edit -> verify sequence inside a
 * single MCP request). Rank Math memoizes the option arrays in its Settings
 * singleton and never invalidates them on update_option; reset() reloads from
 * the DB. Mirrors the from_db:true treatment the schema reads use.
 */
function rm_refresh_settings(): void
{
    if (function_exists('rank_math')) {
        rank_math()->settings->reset();
    }
}

/**
 * Read a Rank Math setting (dotted path), coerced to a string.
 */
function rm_setting_str(string $path): string
{
    return rm_str(Helper::get_settings($path));
}

/**
 * Public post-type names Rank Math manages SEO for (public + viewable, minus
 * attachments which Rank Math gates separately).
 *
 * @return list<string>
 */
function rm_public_post_type_names(): array
{
    $types = get_post_types(['public' => true], output: 'names');
    return array_values(array_filter($types, static fn(string $name): bool => is_post_type_viewable($name)));
}

/**
 * Public taxonomy names that are valid term-SEO targets.
 *
 * @return list<string>
 */
function rm_public_taxonomy_names(): array
{
    // Rank Math's get_accessible_taxonomies() formula (Helpers\Taxonomy::
    // is_taxonomy_viewable): publicly_queryable || (_builtin && public). WordPress
    // core's is_taxonomy_viewable() checks ONLY publicly_queryable (unlike core's
    // is_post_type_viewable), so it would wrongly drop a builtin public-but-not-
    // queryable taxonomy (e.g. post_format with archives disabled) that Rank Math
    // still manages term SEO for.
    $out = [];
    foreach (get_taxonomies(['public' => true], output: 'objects') as $taxonomy) {
        if (!$taxonomy->publicly_queryable && !($taxonomy->_builtin && $taxonomy->public)) {
            continue;
        }
        $out[] = $taxonomy->name;
    }
    return $out;
}

/**
 * Extract a schema's type as a string. Rank Math stores @type as either a string
 * or an array of types (e.g. ['Article', 'NewsArticle']); it derives the meta key
 * and primary type from the first element (@type[0]), so we report that.
 */
function rm_schema_type(mixed $type): string
{
    if (is_string($type)) {
        return $type;
    }
    if (is_array($type)) {
        foreach (array_filter($type, callback: 'is_string') as $candidate) {
            if ($candidate !== '') {
                return $candidate;
            }
        }
    }
    return '';
}

/**
 * The per-post schema entries as compact rows.
 *
 * @return list<array{id: string, type: string, is_primary: bool}>
 */
function rm_schema_entries(int $post_id): array
{
    $out = [];
    // from_db: true bypasses Rank Math's per-request static cache so a read
    // immediately after a write (same MCP request) reflects the new state.
    // class_exists guards the un-gated callers (get-post-seo) on installs where
    // the Schema class is not loaded.
    if (class_exists('RankMath\Schema\DB')) {
        foreach (DB::get_schemas($post_id, table: 'postmeta', from_db: true) as $id => $entry) {
            $metadata = is_array($entry['metadata'] ?? null) ? $entry['metadata'] : [];
            $out[] = [
                'id' => (string) $id,
                'type' => rm_schema_type($entry['@type'] ?? ''),
                'is_primary' => rm_bool($metadata['isPrimary'] ?? null),
            ];
        }
    }

    // Fall back to the legacy single-snippet field (older Rank Math, and the
    // AIOSEO / SEOPress / Divi importers) when there is no modern entry — Rank
    // Math's frontend still renders it, so it is a real per-post schema and
    // get-post-schema must report it (matching get-post-seo's schema_type).
    // 'off' and '' are removal sentinels, not a schema type.
    if ($out === []) {
        $legacy = rm_str(rm_post_meta('rank_math_rich_snippet', $post_id));
        if ($legacy !== '' && $legacy !== 'off') {
            $out[] = ['id' => 'rich_snippet', 'type' => $legacy, 'is_primary' => true];
        }
    }

    return $out;
}

/**
 * The post's effective Rank Math schema type as a single label: the primary
 * per-post schema entry, else the first typed one. rm_schema_entries() already
 * unifies the modern rank_math_schema_* entries with the legacy single-snippet
 * field and filters the 'off' sentinel, so get-post-seo's schema_type stays in
 * lock-step with get-post-schema.
 */
function rm_post_schema_type(int $post_id): string
{
    $fallback = '';
    foreach (rm_schema_entries($post_id) as $entry) {
        if ($entry['type'] === '') {
            continue;
        }
        if ($entry['is_primary']) {
            return $entry['type'];
        }
        if ($fallback === '') {
            $fallback = $entry['type'];
        }
    }
    return $fallback;
}

/**
 * The schema type a post inherits when it has no per-post schema.
 */
function rm_default_schema_type(int $post_id): string
{
    return rm_str(Helper::get_default_schema_type($post_id));
}

/**
 * Delete every per-post schema entry, leaving the post in the same state Rank
 * Math's own "Reset Schema" action does:
 *  - the modern rank_math_schema_* postmeta,
 *  - the rank_math_shortcode_schema_<shortcode> companion shortcuts Rank Math's
 *    editor writes alongside each schema (otherwise they dangle, pointing at a
 *    deleted meta_id),
 *  - the legacy single-snippet rank_math_rich_snippet field that older Rank Math
 *    / importers leave behind (leaving it suppresses schema instead of letting
 *    the post inherit the post-type default).
 */
function rm_clear_post_schemas(int $post_id): void
{
    delete_post_meta($post_id, meta_key: 'rank_math_rich_snippet');

    /** @var mixed $all */
    $all = get_post_meta($post_id);
    if (!is_array($all)) {
        return;
    }
    foreach (array_keys($all) as $key) {
        if (!is_string($key)) {
            continue;
        }
        if (!str_starts_with($key, 'rank_math_schema_') && !str_starts_with($key, 'rank_math_shortcode_schema_')) {
            continue;
        }
        delete_post_meta($post_id, meta_key: $key);
    }
}

/**
 * Replace the post's per-post schema with a single minimal entry of the given
 * type (Rank Math fills the rest of the properties from its template).
 *
 * Idempotent: if the post already has exactly one per-post schema and it is of
 * the requested type AND already primary, leave it untouched. The reported
 * schema id derives from the row's meta_id, so a blind delete+insert would
 * churn the id on every identical call; skipping keeps repeated calls returning
 * a stable result. The is_primary check is required because the contract is a
 * single PRIMARY entry — a same-type but non-primary entry (e.g. what Rank
 * Math's Yoast importer writes for VideoObject) must still be replaced.
 */
function rm_set_post_schema(int $post_id, string $type): void
{
    $existing = rm_schema_entries($post_id);
    if (count($existing) === 1 && $existing[0]['type'] === $type && $existing[0]['is_primary']) {
        return;
    }
    rm_clear_post_schemas($post_id);
    $entry = ['@type' => $type, 'metadata' => ['type' => 'template', 'title' => $type, 'isPrimary' => '1']];
    update_post_meta($post_id, meta_key: "rank_math_schema_{$type}", meta_value: $entry);
}
