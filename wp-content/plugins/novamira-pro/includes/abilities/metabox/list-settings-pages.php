<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-list-settings-pages', [
    'label' => __('List Meta Box Settings Pages', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight overview of all settings pages registered via MB Settings Page: id, menu_title, page_title, source (php|db), option_name, parent (parent page id if any). Use id with metabox-get-settings-page for the full payload.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'settings_pages' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'menu_title' => ['type' => 'string'],
                        'page_title' => ['type' => 'string'],
                        'source' => ['type' => 'string', 'enum' => ['php', 'db']],
                        'option_name' => ['type' => 'string'],
                        'parent' => ['type' => 'string'],
                        'db_post_id' => ['type' => 'integer'],
                    ],
                    'required' => ['id', 'menu_title', 'source'],
                ],
            ],
        ],
        'required' => ['settings_pages'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_list_settings_pages',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Lists all MB Settings Page entries. source=php means PHP-registered (read-only); source=db means created via MB Builder (editable). Use id with metabox-read-values (target.type=settings, target.id=<id>) to read option values.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{settings_pages: list<array<string, mixed>>}|WP_Error
 */
function metabox_list_settings_pages(): array|WP_Error
{
    $guard = mb_settings_page_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var array<string, bool> $db_ids */
    $db_ids = [];
    /** @var list<array<string, mixed>> $out */
    $out = [];

    mb_collect_db_settings_pages($db_ids, $out);
    mb_collect_php_settings_pages($db_ids, $out);

    return ['settings_pages' => $out];
}

/**
 * Append DB-stored settings pages to $out and track their ids in $db_ids.
 *
 * @param array<string, bool>        $db_ids
 * @param list<array<string, mixed>> $out
 */
function mb_collect_db_settings_pages(array &$db_ids, array &$out): void
{
    /** @var mixed $posts_raw */
    $posts_raw = get_posts([
        'post_type' => 'mb-settings-page',
        'post_status' => 'publish',
        'numberposts' => -1,
    ]);
    if (!is_array($posts_raw)) {
        return;
    }
    /** @var array<int, mixed> $posts */
    $posts = $posts_raw;
    /** @var mixed $post */
    foreach ($posts as $post) {
        if (!$post instanceof \WP_Post) {
            continue;
        }
        /** @var array<string, mixed> $settings */
        $settings = (static function () use ($post): array {
            /** @var mixed $raw */
            $raw = get_post_meta($post->ID, key: 'settings_page', single: true);
            return is_array($raw) ? $raw : [];
        })();

        /** @var mixed $id_raw */
        $id_raw = $settings['id'] ?? $post->post_name;
        $id = is_string($id_raw) && $id_raw !== '' ? $id_raw : $post->post_name;
        $db_ids[$id] = true;

        /** @var mixed $menu_title_raw */
        $menu_title_raw = $settings['menu_title'] ?? $post->post_title;
        /** @var mixed $page_title_raw */
        $page_title_raw = $settings['page_title'] ?? $menu_title_raw;
        /** @var mixed $option_name_raw */
        $option_name_raw = $settings['option_name'] ?? $id;
        /** @var mixed $parent_raw */
        $parent_raw = $settings['parent'] ?? '';

        $out[] = [
            'id' => $id,
            'menu_title' => is_string($menu_title_raw) ? $menu_title_raw : $id,
            'page_title' => is_string($page_title_raw) ? $page_title_raw : $id,
            'source' => 'db',
            'option_name' => is_string($option_name_raw) ? $option_name_raw : $id,
            'parent' => is_string($parent_raw) ? $parent_raw : '',
            'db_post_id' => $post->ID,
        ];
    }
}

/**
 * Append PHP-registered settings pages (skipping db_ids) to $out.
 *
 * @param array<string, bool>        $db_ids
 * @param list<array<string, mixed>> $out
 */
function mb_collect_php_settings_pages(array $db_ids, array &$out): void
{
    /** @var mixed $php_pages_raw */
    $php_pages_raw = apply_filters('mb_settings_pages', []);
    if (!is_array($php_pages_raw)) {
        return;
    }
    /** @var array<int, mixed> $php_pages */
    $php_pages = $php_pages_raw;
    /** @var mixed $page */
    foreach ($php_pages as $page) {
        if (!is_array($page)) {
            continue;
        }
        /** @var mixed $id_raw */
        $id_raw = $page['id'] ?? null;
        if (!is_string($id_raw) || $id_raw === '' || isset($db_ids[$id_raw])) {
            continue;
        }
        /** @var mixed $menu_title_raw */
        $menu_title_raw = $page['menu_title'] ?? $id_raw;
        /** @var mixed $page_title_raw */
        $page_title_raw = $page['page_title'] ?? $menu_title_raw;
        /** @var mixed $option_name_raw */
        $option_name_raw = $page['option_name'] ?? $id_raw;
        /** @var mixed $parent_raw */
        $parent_raw = $page['parent'] ?? '';

        $out[] = [
            'id' => $id_raw,
            'menu_title' => is_string($menu_title_raw) ? $menu_title_raw : $id_raw,
            'page_title' => is_string($page_title_raw) ? $page_title_raw : $id_raw,
            'source' => 'php',
            'option_name' => is_string($option_name_raw) ? $option_name_raw : $id_raw,
            'parent' => is_string($parent_raw) ? $parent_raw : '',
        ];
    }
}
