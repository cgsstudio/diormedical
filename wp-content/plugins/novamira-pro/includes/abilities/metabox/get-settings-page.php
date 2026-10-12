<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-get-settings-page', [
    'label' => __('Get Meta Box Settings Page', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full settings for a single MB Settings Page entry identified by id. The payload includes id, menu_title, page_title, option_name, parent, capability, icon_url, position, style, columns, source (php|db). Use id with metabox-read-values (target.type=settings, target.id=<id>) to read the stored option values.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'The settings page id.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'menu_title' => ['type' => 'string'],
            'page_title' => ['type' => 'string'],
            'option_name' => ['type' => 'string'],
            'source' => ['type' => 'string', 'enum' => ['php', 'db']],
            'db_post_id' => ['type' => 'integer'],
            'settings' => ['type' => 'object'],
        ],
        'required' => ['id', 'menu_title', 'source', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_get_settings_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Fetch the full settings for one MB Settings Page entry. The id is used as the option_name in wp_options (unless option_name is explicitly set). Use id with metabox-read-values (target.type=settings, target.id=<id>) to read stored field values.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id: string} $args
 * @return array<string, mixed>|WP_Error
 */
function metabox_get_settings_page(array $args): array|WP_Error
{
    $guard = mb_settings_page_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = $args['id'];
    if ($id === '') {
        return new WP_Error('metabox_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    // Check DB first.
    $post = mb_find_settings_page_post($id);
    if ($post !== null) {
        /** @var array<string, mixed> $settings */
        $settings = (static function () use ($post): array {
            /** @var mixed $raw */
            $raw = get_post_meta($post->ID, key: 'settings_page', single: true);
            return is_array($raw) ? $raw : [];
        })();
        /** @var mixed $menu_title_raw */
        $menu_title_raw = $settings['menu_title'] ?? $post->post_title;
        $menu_title = is_string($menu_title_raw) ? $menu_title_raw : $id;

        /** @var mixed $page_title_raw */
        $page_title_raw = $settings['page_title'] ?? null;
        $page_title = is_string($page_title_raw) ? $page_title_raw : $menu_title;

        /** @var mixed $option_name_raw */
        $option_name_raw = $settings['option_name'] ?? null;
        $option_name = is_string($option_name_raw) ? $option_name_raw : $id;

        return [
            'id' => $id,
            'menu_title' => $menu_title,
            'page_title' => $page_title,
            'option_name' => $option_name,
            'source' => 'db',
            'db_post_id' => $post->ID,
            'settings' => $settings,
        ];
    }

    // PHP-registered fallback.
    $php_result = mb_find_php_settings_page($id);
    if ($php_result !== null) {
        return $php_result;
    }

    return new WP_Error('metabox_not_found', sprintf(
        /* translators: %s: settings page id. */
        __('Settings page "%s" not found.', domain: 'novamira-pro'),
        $id,
    ));
}

/**
 * Search PHP-registered settings pages for the given id and return a formatted response.
 *
 * @return array<string, mixed>|null
 */
function mb_find_php_settings_page(string $id): ?array
{
    /** @var mixed $php_pages_raw */
    $php_pages_raw = apply_filters('mb_settings_pages', []);
    if (!is_array($php_pages_raw)) {
        return null;
    }
    /** @var array<int, mixed> $php_pages */
    $php_pages = $php_pages_raw;
    /** @var mixed $page */
    foreach ($php_pages as $page) {
        if (!is_array($page)) {
            continue;
        }
        /** @var mixed $page_id_raw */
        $page_id_raw = $page['id'] ?? null;
        if (!is_string($page_id_raw) || $page_id_raw !== $id) {
            continue;
        }
        /** @var mixed $menu_title_raw */
        $menu_title_raw = $page['menu_title'] ?? $id;
        /** @var mixed $page_title_raw */
        $page_title_raw = $page['page_title'] ?? $menu_title_raw;
        /** @var mixed $option_name_raw */
        $option_name_raw = $page['option_name'] ?? $id;

        return [
            'id' => $id,
            'menu_title' => is_string($menu_title_raw) ? $menu_title_raw : $id,
            'page_title' => is_string($page_title_raw) ? $page_title_raw : $id,
            'option_name' => is_string($option_name_raw) ? $option_name_raw : $id,
            'source' => 'php',
            'settings' => $page,
        ];
    }
    return null;
}

/**
 * Find the mb-settings-page CPT post for a given settings page id.
 */
function mb_find_settings_page_post(string $id): ?\WP_Post
{
    // Try post_name match first.
    $post = mb_find_settings_page_post_by_name($id);
    if ($post !== null) {
        return $post;
    }

    // Fallback: scan settings_page postmeta for matching id.
    return mb_find_settings_page_post_by_meta($id);
}

/**
 * Find a mb-settings-page post by post_name.
 */
function mb_find_settings_page_post_by_name(string $id): ?\WP_Post
{
    /** @var mixed $posts_raw */
    $posts_raw = get_posts([
        'post_type' => 'mb-settings-page',
        'post_status' => 'publish',
        'name' => $id,
        'numberposts' => 1,
    ]);
    if (!is_array($posts_raw) || count($posts_raw) === 0) {
        return null;
    }
    /** @var mixed $first */
    $first = $posts_raw[0];
    return $first instanceof \WP_Post ? $first : null;
}

/**
 * Find a mb-settings-page post by scanning settings_page postmeta for matching id.
 */
function mb_find_settings_page_post_by_meta(string $id): ?\WP_Post
{
    /** @var mixed $all_posts_raw */
    $all_posts_raw = get_posts([
        'post_type' => 'mb-settings-page',
        'post_status' => 'publish',
        'numberposts' => -1,
    ]);
    if (!is_array($all_posts_raw)) {
        return null;
    }
    /** @var array<int, mixed> $all_posts */
    $all_posts = $all_posts_raw;
    /** @var mixed $post */
    foreach ($all_posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }
        /** @var array<string, mixed> $raw */
        $raw = (static function () use ($post): array {
            /** @var mixed $r */
            $r = get_post_meta($post->ID, key: 'settings_page', single: true);
            return is_array($r) ? $r : [];
        })();
        /** @var mixed $id_raw */
        $id_raw = $raw['id'] ?? null;
        if (is_string($id_raw) && $id_raw === $id) {
            return $post;
        }
    }

    return null;
}

/**
 * Determine the source for a settings page (db or php).
 *
 * @return 'db'|'php'|null
 */
function mb_resolve_settings_page_source(string $id): ?string
{
    if (mb_find_settings_page_post($id) !== null) {
        return 'db';
    }

    /** @var mixed $php_pages_raw */
    $php_pages_raw = apply_filters('mb_settings_pages', []);
    if (!is_array($php_pages_raw)) {
        return null;
    }
    /** @var array<int, mixed> $php_pages */
    $php_pages = $php_pages_raw;
    /** @var mixed $page */
    foreach ($php_pages as $page) {
        if (!is_array($page)) {
            continue;
        }
        /** @var mixed $page_id_raw */
        $page_id_raw = $page['id'] ?? null;
        if (is_string($page_id_raw) && $page_id_raw === $id) {
            return 'php';
        }
    }

    return null;
}
