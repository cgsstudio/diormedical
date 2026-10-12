<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Detect ACF presence, edition, and version. Cached per request.
 *
 * @return array{active: bool, edition: 'free'|'pro'|null, version: string|null}
 */
function acf_detect_setup(): array
{
    /** @var array{active: bool, edition: 'free'|'pro'|null, version: string|null}|null $cache */
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $active = class_exists('ACF');
    $edition = null;
    $version = null;

    if ($active) {
        $edition = class_exists('acf_pro') || defined('ACF_PRO') ? 'pro' : 'free';
        $version = defined('ACF_VERSION') ? (string) constant('ACF_VERSION') : null;
    }

    return $cache = [
        'active' => $active,
        'edition' => $edition,
        'version' => $version,
    ];
}

/**
 * Check whether the supplied id is acceptable as a numeric WordPress object id.
 */
function acf_is_numeric_id(mixed $id): bool
{
    if (is_int($id)) {
        return true;
    }
    if (is_string($id) && ctype_digit($id)) {
        return true;
    }
    return false;
}

/**
 * Convert a structured target descriptor into ACF's polymorphic $post_id string.
 *
 * @param array{type?: string, id?: int|string} $target
 * @return string|WP_Error
 */
function acf_normalize_target(array $target): string|WP_Error
{
    /** @var mixed $type */
    $type = $target['type'] ?? null;
    /** @var mixed $id */
    $id = $target['id'] ?? null;

    if (!is_string($type)) {
        return new WP_Error('target_invalid', 'target.type is required and must be a string', ['target' => $target]);
    }

    if ($type === 'post' || $type === 'user' || $type === 'term' || $type === 'comment') {
        if (!acf_is_numeric_id($id)) {
            return new WP_Error('target_invalid', "target.id must be a numeric {$type} id", ['target' => $target]);
        }
        $numeric = (int) $id;
        return match ($type) {
            'post' => (string) $numeric,
            'user' => 'user_' . $numeric,
            'term' => 'term_' . $numeric,
            'comment' => 'comment_' . $numeric,
        };
    }

    if ($type === 'options') {
        if ($id === null || $id === '') {
            return 'options';
        }
        if (!is_string($id)) {
            return new WP_Error('target_invalid', 'target.id for options must be a string slug or omitted', [
                'target' => $target,
            ]);
        }
        if (!preg_match('/^[a-z0-9_-]+$/i', $id)) {
            return new WP_Error(
                'target_invalid',
                'target.id for options must match [a-z0-9_-]+ (the options page slug, without the "options_" prefix)',
                ['target' => $target],
            );
        }
        return $id;
    }

    return new WP_Error(
        'target_invalid',
        "target.type must be one of: post, user, term, comment, options. Got: {$type}",
        ['target' => $target],
    );
}

/**
 * Resolve where a field group is defined.
 *
 * @return 'db'|'json'|'php'|null
 */
function acf_resolve_fieldgroup_source(string $key): ?string
{
    if (!function_exists('acf_get_field_group')) {
        return null;
    }

    /** @var mixed $group */
    $group = acf_get_field_group($key);
    if (!is_array($group)) {
        return null;
    }

    /** @var mixed $local */
    $local = $group['local'] ?? null;
    if ($local === 'php') {
        return 'php';
    }
    if ($local === 'json') {
        return 'json';
    }
    return 'db';
}

/**
 * Field types whose values are arrays/structures — writing replaces the entire
 * value.
 *
 * The list is the five native ACF types. ACF Extended (ACFE) does NOT
 * register replacement field types for these; it extends the native ones via
 * filters and settings (collapse, advanced settings, async loading, etc.) so
 * an ACFE-flavored repeater is still `type: "repeater"`. This means our
 * native list automatically covers ACFE-extended fields without changes.
 */
function acf_field_is_complex(string $type): bool
{
    return in_array($type, ['repeater', 'flexible_content', 'gallery', 'clone', 'group'], strict: true);
}

/**
 * Constants that distinguish the three ACF internal-post-type kinds.
 *
 * @return array{post_type: string, key_prefix: string, slug_field: string, kind_label: string, slug_max: int}
 */
function acf_internal_post_type_constants(string $kind): array
{
    return match ($kind) {
        'taxonomy' => [
            'post_type' => 'acf-taxonomy',
            'key_prefix' => 'taxonomy_',
            'slug_field' => 'taxonomy',
            'kind_label' => 'taxonomy',
            'slug_max' => 32,
        ],
        'options-page' => [
            'post_type' => 'acf-ui-options-page',
            'key_prefix' => 'ui_options_page_',
            'slug_field' => 'menu_slug',
            'kind_label' => 'options page',
            'slug_max' => 50,
        ],
        default => [
            'post_type' => 'acf-post-type',
            'key_prefix' => 'post_type_',
            'slug_field' => 'post_type',
            'kind_label' => 'post type',
            'slug_max' => 20,
        ],
    };
}

/**
 * Common pre-flight gate for internal-post-type abilities. Returns null when
 * the runtime supports the API, a WP_Error otherwise. Requires ACF Pro 6.5+
 * across all kinds (post types, taxonomies, options pages) to keep the
 * supported runtime aligned with the plugin's documented minimum.
 *
 * The function_exists() check covers ACF Free, where the
 * acf_get_internal_post_type_posts() API is not present even on 6.5+.
 */
function acf_internal_post_type_guard(string $kind = 'post-type'): ?WP_Error
{
    if (!class_exists('ACF')) {
        return new WP_Error('acf_not_active', 'ACF is not active');
    }
    $target = match ($kind) {
        'options-page' => 'options pages',
        'taxonomy' => 'taxonomies',
        default => 'post types',
    };
    if (!function_exists('acf_get_internal_post_type_posts')) {
        return new WP_Error(
            'acf_version_unsupported',
            "ACF Pro is required to manage {$target} via abilities (the free edition does not expose the internal-post-type API)",
        );
    }
    $version = defined('ACF_VERSION') ? (string) constant('ACF_VERSION') : '';
    if ($version === '' || version_compare(version1: $version, version2: '6.5.0', operator: '<')) {
        return new WP_Error('acf_version_unsupported', "ACF Pro 6.5+ is required to manage {$target} via abilities", [
            'installed_version' => $version,
        ]);
    }
    return null;
}

/**
 * Validate a slug against WordPress format rules.
 */
function acf_internal_post_type_validate_slug(string $slug, string $kind): ?WP_Error
{
    $constants = acf_internal_post_type_constants($kind);
    $max = $constants['slug_max'];
    $label = $constants['kind_label'];

    if ($slug === '') {
        return new WP_Error('invalid_slug', "{$label} slug is required and must not be empty");
    }
    if (strlen($slug) > $max) {
        return new WP_Error('invalid_slug', "{$label} slug must be {$max} characters or fewer", [
            'slug' => $slug,
            'length' => strlen($slug),
            'max' => $max,
        ]);
    }
    if (!preg_match('/^[a-z0-9_-]+$/', $slug)) {
        return new WP_Error(
            'invalid_slug',
            "{$label} slug must match [a-z0-9_-]+ (lowercase letters, digits, underscore, hyphen)",
            ['slug' => $slug],
        );
    }
    return null;
}

/**
 * Block slugs that would clash with WP core post types or taxonomies.
 *
 * For options pages, no static reserved list applies — admin menu slugs are
 * free-form and a static block-list would be both incomplete and over-restrictive.
 */
function acf_internal_post_type_check_reserved_slug(string $slug, string $kind): ?WP_Error
{
    $reserved = match ($kind) {
        'taxonomy' => [
            'category',
            'post_tag',
            'nav_menu',
            'link_category',
            'post_format',
            'wp_theme',
            'wp_template_part_area',
        ],
        'options-page' => [],
        default => [
            'post',
            'page',
            'attachment',
            'revision',
            'nav_menu_item',
            'custom_css',
            'customize_changeset',
            'oembed_cache',
            'user_request',
            'wp_block',
            'wp_template',
            'wp_template_part',
            'wp_global_styles',
            'wp_navigation',
        ],
    };

    if (in_array($slug, $reserved, strict: true)) {
        $label = acf_internal_post_type_constants($kind)['kind_label'];
        return new WP_Error('reserved_slug', "{$label} slug '{$slug}' is reserved by WordPress core", [
            'slug' => $slug,
            'reserved' => $reserved,
        ]);
    }
    return null;
}

/**
 * Detect slug collisions with both ACF-managed records and externally
 * registered post types/taxonomies.
 *
 * @return array{acf: bool, external: bool, external_source: string|null}
 */
function acf_internal_post_type_check_collision(string $slug, string $kind, ?string $own_key = null): array
{
    $constants = acf_internal_post_type_constants($kind);
    $result = ['acf' => false, 'external' => false, 'external_source' => null];

    $records = acf_get_internal_post_type_posts($constants['post_type']);
    $own_record_slug = null;
    foreach ($records as $record) {
        /** @var mixed $record_slug */
        $record_slug = $record[$constants['slug_field']] ?? null;
        /** @var mixed $record_key */
        $record_key = $record['key'] ?? null;
        $is_own = $own_key !== null && is_string($record_key) && $record_key === $own_key;
        if ($is_own && is_string($record_slug)) {
            $own_record_slug = $record_slug;
        }
        if (!is_string($record_slug) || $record_slug !== $slug) {
            continue;
        }
        if ($is_own) {
            continue;
        }
        $result['acf'] = true;
        return $result;
    }

    // If the queried slug equals our own record's current slug, the "external"
    // registration is us (we registered our own CPT/taxonomy at runtime). Don't
    // flag a collision against ourselves.
    if ($own_record_slug === $slug) {
        return $result;
    }

    if ($kind === 'taxonomy' && taxonomy_exists($slug)) {
        $result['external'] = true;
        $result['external_source'] = 'wp_or_plugin';
        return $result;
    }
    if ($kind === 'post-type' && post_type_exists($slug)) {
        $result['external'] = true;
        $result['external_source'] = 'wp_or_plugin';
    }

    // 'options-page' has no reliable external collision check at registration
    // time (admin menus are not all known until admin_menu fires).
    return $result;
}

/**
 * Count content stored under a given CPT/taxonomy slug. Used to warn on
 * slug-change and to gate destructive deletes.
 *
 * For options pages, returns 0: option values are stored in `wp_options` under
 * a shared post_id namespace (default 'options'), so deleting one page does
 * not orphan content in the same way as deleting a CPT or taxonomy.
 */
function acf_internal_post_type_count_content(string $slug, string $kind): int
{
    if ($kind === 'options-page') {
        return 0;
    }

    global $wpdb;
    /** @var \wpdb $wpdb */

    if ($kind === 'taxonomy') {
        if (!taxonomy_exists($slug)) {
            // Fallback to a direct term count via wp_term_taxonomy when the
            // taxonomy is not currently registered (e.g. ACF inactive at boot).
            // @mago-expect analysis:possibly-invalid-argument
            $prepared = $wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->term_taxonomy} WHERE taxonomy = %s", $slug);
            // @mago-expect analysis:possibly-invalid-argument
            $count = $wpdb->get_var($prepared);
            return is_numeric($count) ? (int) $count : 0;
        }
        /** @var int|string|WP_Error $terms */
        $terms = get_terms(['taxonomy' => $slug, 'hide_empty' => false, 'fields' => 'count']);
        return is_numeric($terms) ? (int) $terms : 0;
    }

    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status NOT IN ('auto-draft', 'trash')",
        $slug,
    );
    // @mago-expect analysis:possibly-invalid-argument
    $count = $wpdb->get_var($prepared);
    return is_numeric($count) ? (int) $count : 0;
}

/**
 * Resolve a user-supplied identifier (ACF key or bare slug) to the canonical
 * ACF key. Returns null when no record matches, or a WP_Error when the input
 * is structurally invalid (multiple matches by slug — defensive only).
 */
function acf_internal_post_type_resolve_key(string $key_or_slug, string $kind): string|WP_Error|null
{
    $constants = acf_internal_post_type_constants($kind);

    if (str_starts_with($key_or_slug, $constants['key_prefix'])) {
        $record = acf_get_internal_post_type($key_or_slug, $constants['post_type']);
        if (!is_array($record)) {
            return null;
        }
        /** @var mixed $key */
        $key = $record['key'] ?? null;
        return is_string($key) ? $key : null;
    }

    $records = acf_get_internal_post_type_posts($constants['post_type']);
    /** @var list<string> $matches */
    $matches = [];
    foreach ($records as $record) {
        /** @var mixed $record_slug */
        $record_slug = $record[$constants['slug_field']] ?? null;
        /** @var mixed $record_key */
        $record_key = $record['key'] ?? null;
        if (is_string($record_slug) && $record_slug === $key_or_slug && is_string($record_key)) {
            $matches[] = $record_key;
        }
    }

    if ($matches === []) {
        return null;
    }
    if (count($matches) > 1) {
        return new WP_Error(
            'ambiguous_slug',
            "multiple {$constants['kind_label']} records match slug '{$key_or_slug}'",
            [
                'slug' => $key_or_slug,
                'matches' => $matches,
            ],
        );
    }
    return $matches[0];
}

/**
 * Build the base (summary) output shape for an internal-post-type record.
 * For the full shape including raw payload use acf_internal_post_type_to_output_full().
 *
 * @param array<string, mixed> $record
 * @return array<string, mixed>
 */
function acf_internal_post_type_to_output(array $record, string $kind): array
{
    $constants = acf_internal_post_type_constants($kind);

    /** @var mixed $key */
    $key = $record['key'] ?? null;
    /** @var mixed $slug */
    $slug = $record[$constants['slug_field']] ?? null;
    /** @var mixed $active */
    $active = $record['active'] ?? true;
    /** @var mixed $local */
    $local = $record['local'] ?? null;

    $source = match (true) {
        $local === 'php' => 'php',
        $local === 'json' => 'json',
        default => 'db',
    };

    $base = [
        'key' => is_string($key) ? $key : '',
        'slug' => is_string($slug) ? $slug : '',
        'title' => acf_internal_post_type_extract_title($record, $kind),
        'active' => filter_var($active, FILTER_VALIDATE_BOOLEAN),
        'source' => $source,
    ];

    return match ($kind) {
        'taxonomy' => acf_internal_post_type_extend_output_taxonomy($base, $record),
        'options-page' => acf_internal_post_type_extend_output_options_page($base, $record),
        default => acf_internal_post_type_extend_output_post_type($base, $record),
    };
}

/**
 * @param array<string, mixed> $base
 * @param array<string, mixed> $record
 * @return array<string, mixed>
 */
function acf_internal_post_type_extend_output_taxonomy(array $base, array $record): array
{
    /** @var mixed $object_type */
    $object_type = $record['object_type'] ?? [];
    $base['object_type'] = is_array($object_type)
        ? array_values(array_filter($object_type, callback: 'is_string'))
        : [];
    return $base;
}

/**
 * @param array<string, mixed> $base
 * @param array<string, mixed> $record
 * @return array<string, mixed>
 */
function acf_internal_post_type_extend_output_options_page(array $base, array $record): array
{
    /** @var mixed $menu_title */
    $menu_title = $record['menu_title'] ?? null;
    /** @var mixed $parent_slug */
    $parent_slug = $record['parent_slug'] ?? null;
    /** @var mixed $capability */
    $capability = $record['capability'] ?? null;
    $base['menu_title'] = is_string($menu_title) ? $menu_title : '';
    $base['parent_slug'] = is_string($parent_slug) ? $parent_slug : '';
    $base['capability'] = is_string($capability) ? $capability : '';
    return $base;
}

/**
 * @param array<string, mixed> $base
 * @param array<string, mixed> $record
 * @return array<string, mixed>
 */
function acf_internal_post_type_extend_output_post_type(array $base, array $record): array
{
    /** @var mixed $public */
    $public = $record['public'] ?? false;
    /** @var mixed $hierarchical */
    $hierarchical = $record['hierarchical'] ?? false;
    $base['public'] = filter_var($public, FILTER_VALIDATE_BOOLEAN);
    $base['hierarchical'] = filter_var($hierarchical, FILTER_VALIDATE_BOOLEAN);
    return $base;
}

/**
 * Extract a human-readable title from an internal-post-type record. For
 * post types and taxonomies the canonical field is `title`; for options
 * pages it's `page_title` with `menu_title` as a fallback.
 *
 * @param array<string, mixed> $record
 */
function acf_internal_post_type_extract_title(array $record, string $kind): string
{
    if ($kind === 'options-page') {
        /** @var mixed $page_title */
        $page_title = $record['page_title'] ?? null;
        if (is_string($page_title) && $page_title !== '') {
            return $page_title;
        }
        /** @var mixed $menu_title */
        $menu_title = $record['menu_title'] ?? null;
        return is_string($menu_title) ? $menu_title : '';
    }
    /** @var mixed $title */
    $title = $record['title'] ?? null;
    return is_string($title) ? $title : '';
}

/**
 * Build the full output shape for an internal-post-type record, including the
 * raw ACF payload. Use acf_internal_post_type_to_output() for list/summary views.
 *
 * @param array<string, mixed> $record
 * @return array<string, mixed>
 */
function acf_internal_post_type_to_output_full(array $record, string $kind): array
{
    $base = acf_internal_post_type_to_output($record, $kind);
    $base['payload'] = $record;
    return $base;
}

/**
 * Build a warning entry when an edit changes the slug while content exists.
 * Returns null when no warning is warranted.
 *
 * @return array{code: string, old_slug: string, new_slug: string, affected_count: int}|null
 */
function acf_internal_post_type_slug_change_warning(string $old_slug, string $new_slug, string $kind): ?array
{
    if ($old_slug === $new_slug) {
        return null;
    }
    $count = acf_internal_post_type_count_content($old_slug, $kind);
    if ($count === 0) {
        return null;
    }
    return [
        'code' => 'slug_change_orphans_content',
        'old_slug' => $old_slug,
        'new_slug' => $new_slug,
        'affected_count' => $count,
    ];
}

/**
 * Validate the minimal input shape required by a create operation. Returns null
 * on success, WP_Error on failure.
 *
 * @param array<string, mixed> $args
 */
function acf_pt_validate_create_input(array $args, string $kind): ?WP_Error
{
    $constants = acf_internal_post_type_constants($kind);

    $slug = acf_pt_extract_slug($args, $kind);
    if ($slug === null || $slug === '') {
        return new WP_Error('invalid_input', "{$constants['slug_field']} (alias: slug) is required on create");
    }

    if ($kind === 'options-page') {
        /** @var mixed $page_title */
        $page_title = $args['page_title'] ?? null;
        /** @var mixed $menu_title */
        $menu_title = $args['menu_title'] ?? null;
        /** @var mixed $title_alias */
        $title_alias = $args['title'] ?? null;
        if (!is_string($page_title) && !is_string($menu_title) && !is_string($title_alias)) {
            return new WP_Error('invalid_input', 'page_title (alias: title or menu_title) is required on create');
        }
        return null;
    }

    /** @var mixed $title */
    $title = $args['title'] ?? null;
    /** @var mixed $labels */
    $labels = $args['labels'] ?? null;
    /** @var mixed $singular */
    $singular = is_array($labels) ? $labels['singular_name'] ?? null : null;
    if (!is_string($title) && !is_string($singular)) {
        return new WP_Error('invalid_input', 'title or labels.singular_name is required on create');
    }

    return null;
}

/**
 * Validate the minimal input shape required by an edit operation. Returns null
 * on success, WP_Error on failure.
 *
 * @param array<string, mixed> $args
 */
function acf_pt_validate_edit_input(array $args): ?WP_Error
{
    /** @var mixed $key */
    $key = $args['key'] ?? null;
    if (!is_string($key) || $key === '') {
        return new WP_Error('invalid_input', 'key is required and must be a non-empty string');
    }
    return null;
}

/**
 * Pull the slug out of the input regardless of which alias the agent used.
 *
 * @param array<string, mixed> $args
 */
function acf_pt_extract_slug(array $args, string $kind): ?string
{
    $constants = acf_internal_post_type_constants($kind);
    /** @var mixed $native */
    $native = $args[$constants['slug_field']] ?? null;
    if (is_string($native) && $native !== '') {
        return $native;
    }
    /** @var mixed $alias */
    $alias = $args['slug'] ?? null;
    return is_string($alias) && $alias !== '' ? $alias : null;
}

/**
 * Run the full validation pipeline for create/edit (slug format, reserved,
 * collision). External collisions are returned via $warnings (out parameter),
 * not as errors.
 *
 * @param list<array<string, mixed>> $warnings
 */
function acf_pt_validate_slug_pipeline(string $slug, string $kind, ?string $own_key, array &$warnings): ?WP_Error
{
    $format = acf_internal_post_type_validate_slug($slug, $kind);
    if ($format instanceof WP_Error) {
        return $format;
    }
    $reserved = acf_internal_post_type_check_reserved_slug($slug, $kind);
    if ($reserved instanceof WP_Error) {
        return $reserved;
    }
    $collision = acf_internal_post_type_check_collision($slug, $kind, $own_key);
    if ($collision['acf']) {
        return new WP_Error('slug_collision_acf', "another ACF-managed record uses slug '{$slug}'", ['slug' => $slug]);
    }
    if ($collision['external']) {
        $warnings[] = [
            'code' => 'external_slug_collision',
            'message' => "slug '{$slug}' is already registered outside ACF",
            'source' => $collision['external_source'],
        ];
    }
    return null;
}

/**
 * Resolve a raw key/slug to a canonical ACF key and fetch the existing record.
 * Extracted to keep acf_edit_post_type / acf_edit_taxonomy / acf_edit_options_page
 * below the cyclomatic-complexity gate.
 *
 * @param string $raw
 * @param string $kind  'post-type', 'taxonomy', or 'options-page'
 * @return array{resolved: string, existing: array<string, mixed>}|WP_Error
 */
function acf_pt_resolve_existing_record(string $raw, string $kind): array|WP_Error
{
    $constants = acf_internal_post_type_constants($kind);
    $resolved = acf_internal_post_type_resolve_key($raw, kind: $kind);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    if ($resolved === null) {
        return new WP_Error('not_found', "ACF {$constants['kind_label']} not found: {$raw}", ['key' => $raw]);
    }

    $existing = acf_get_internal_post_type($resolved, post_type: $constants['post_type']);
    if (!is_array($existing)) {
        return new WP_Error('not_found', "ACF {$constants['kind_label']} not found: {$resolved}", ['key' => $resolved]);
    }

    return ['resolved' => $resolved, 'existing' => $existing];
}

/**
 * Normalize: autofill key, default active=true, strip the 'slug' alias.
 * For post types and taxonomies, when `labels` was passed in input, cross-fill
 * `title` ↔ `labels.singular_name` if exactly one was provided; when `labels`
 * was NOT passed, leave it absent from the payload — do not synthesize a
 * labels container. For options pages, cross-fill page_title ↔ menu_title ↔
 * the `title` alias and strip `title` from the saved payload.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function acf_pt_normalize_payload(array $args, string $slug, string $kind, ?string $existing_key): array
{
    $constants = acf_internal_post_type_constants($kind);
    $payload = $args;
    $payload[$constants['slug_field']] = $slug;
    unset($payload['slug']);

    // On edit, the canonical ACF key (resolved from the lookup) wins over any
    // value the agent supplied as the `key` alias — agents commonly pass a
    // bare slug there (e.g. "novamira_test") and persisting that as the ACF
    // key would corrupt the record's identity.
    /** @var mixed $supplied_key */
    $supplied_key = $payload['key'] ?? null;
    $payload['key'] = match (true) {
        $existing_key !== null => $existing_key,
        is_string($supplied_key) && $supplied_key !== '' => $supplied_key,
        default => $constants['key_prefix'] . $slug,
    };

    if ($kind === 'options-page') {
        acf_pt_normalize_options_page_titles($payload);
    }
    if ($kind !== 'options-page') {
        acf_pt_normalize_post_type_or_taxonomy_title($payload, $args, $kind, $existing_key);
    }

    if ($existing_key === null && !array_key_exists('active', $payload)) {
        $payload['active'] = true;
    }

    return $payload;
}

/**
 * Apply PATCH semantics on edit: agent-supplied keys win, fields the agent
 * didn't pass fall back to the existing record so acf_update_internal_post_type
 * doesn't blank them out (ACF replaces missing fields with empty/default,
 * it does not merge with the stored record).
 *
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $existing
 * @return array<string, mixed>
 */
function acf_pt_merge_with_existing(array $payload, array $existing): array
{
    return $payload + $existing;
}

/**
 * Cross-fill the title aliases for an options page payload, then drop the
 * generic `title` alias before save.
 *
 * Rules (asymmetric on purpose, to preserve user intent on edits):
 *   - `title` alias seeds both page_title and menu_title when they're missing.
 *   - page_title falls through to menu_title when menu_title is missing.
 *   - menu_title NEVER falls up to page_title — passing only menu_title on
 *     an edit means "rename the menu item, leave the page heading alone".
 *
 * Fields the agent didn't pass stay absent from the payload so ACF preserves
 * the existing record's values on edit.
 *
 * @param array<string, mixed> $payload
 */
function acf_pt_normalize_options_page_titles(array &$payload): void
{
    /** @var mixed $page_title */
    $page_title = $payload['page_title'] ?? null;
    /** @var mixed $menu_title */
    $menu_title = $payload['menu_title'] ?? null;
    /** @var mixed $title_alias */
    $title_alias = $payload['title'] ?? null;
    unset($payload['title']);

    if (is_string($title_alias) && $title_alias !== '') {
        if (!is_string($page_title) || $page_title === '') {
            $payload['page_title'] = $title_alias;
            $page_title = $title_alias;
        }
        if (!is_string($menu_title) || $menu_title === '') {
            $payload['menu_title'] = $title_alias;
            $menu_title = $title_alias;
        }
    }

    if (is_string($page_title) && $page_title !== '' && (!is_string($menu_title) || $menu_title === '')) {
        $payload['menu_title'] = $page_title;
    }
}

/**
 * Cross-fill title ↔ labels.singular_name for post types and taxonomies, and
 * — only on create — synthesize the full WP label set so the registered
 * post type / taxonomy shows the right name in the admin menu and produces
 * sensible "Add New X", "Edit X", "View X" prompts. Without this, ACF saves
 * the partial labels payload and WordPress falls back to "Posts" / "Categories"
 * default plurals.
 *
 * On edit, labels are touched only when the agent supplied them — the merge
 * with the existing record (acf_pt_merge_with_existing) preserves whatever
 * was previously stored.
 *
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $args
 */
function acf_pt_normalize_post_type_or_taxonomy_title(
    array &$payload,
    array $args,
    string $kind,
    ?string $existing_key,
): void {
    [$title, $singular, $labels, $labels_in_input] = acf_pt_resolve_title_aliases($payload, $args);

    if ($existing_key === null && $title !== '' && $singular !== '') {
        $payload['labels'] = $labels + acf_pt_synthesize_full_labels($title, $singular, $kind, $payload);
        return;
    }

    if ($labels_in_input) {
        $payload['labels'] = $labels;
    }
}

/**
 * Resolve the title ↔ labels.singular_name aliases for a post-type / taxonomy
 * payload, mutating $payload['title'] and the labels array as needed and
 * reporting whether the caller supplied any labels container at all.
 *
 * @param array<string, mixed> $payload
 * @param array<string, mixed> $args
 * @return array{0: string, 1: string, 2: array<string, mixed>, 3: bool}
 */
function acf_pt_resolve_title_aliases(array &$payload, array $args): array
{
    /** @var mixed $title_raw */
    $title_raw = $payload['title'] ?? null;
    $labels_in_input = array_key_exists('labels', $args) && is_array($args['labels']);
    /** @var array<string, mixed> $labels */
    $labels = $labels_in_input ? $args['labels'] : [];
    /** @var mixed $singular_raw */
    $singular_raw = $labels['singular_name'] ?? null;

    $title = is_string($title_raw) ? $title_raw : '';
    $singular = is_string($singular_raw) ? $singular_raw : '';

    if ($title === '' && $singular !== '') {
        $payload['title'] = $singular;
        $title = $singular;
    }

    if ($title !== '' && $singular === '') {
        $singular = $title;
        $labels['singular_name'] = $title;
    }

    return [$title, $singular, $labels, $labels_in_input];
}

/**
 * Synthesize the full WP-default label set for a post type or taxonomy by
 * delegating to core's get_post_type_labels() / get_taxonomy_labels(). The
 * hierarchical flag is read from $payload (it controls whether WP picks
 * "Posts" or "Pages" style defaults).
 *
 * @param array<string, mixed> $payload
 * @return array<array-key, mixed>
 */
function acf_pt_synthesize_full_labels(string $title, string $singular, string $kind, array $payload): array
{
    /** @var mixed $hierarchical_raw */
    $hierarchical_raw = $payload['hierarchical'] ?? false;
    $obj = (object) [
        'name' => $kind === 'taxonomy' ? 'novamira_tax_label_synth' : 'novamira_pt_label_synth',
        'labels' => (object) ['name' => $title, 'singular_name' => $singular],
        'hierarchical' => filter_var($hierarchical_raw, FILTER_VALIDATE_BOOLEAN),
    ];
    if ($kind === 'taxonomy') {
        // @mago-expect analysis:invalid-argument
        return get_object_vars(get_taxonomy_labels($obj));
    }
    return get_object_vars(get_post_type_labels($obj));
}
