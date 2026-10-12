<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-list-field-groups', [
    'label' => __('List Meta Box Field Groups', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight overview of all Meta Box field groups from both PHP-registered and DB-stored (MB Builder) sources: key, title, source (php|db), post_types the group applies to, and field_count. Use to discover available groups before calling metabox-get-field-group-schema for the full schema. Accepts an optional post_type filter.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'filter' => [
                'type' => 'object',
                'properties' => [
                    'post_type' => [
                        'type' => 'string',
                        'description' => 'When set, only returns field groups whose post_types include this slug.',
                    ],
                ],
                'additionalProperties' => false,
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'field_groups' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => ['type' => 'string'],
                        'title' => ['type' => 'string'],
                        'source' => ['type' => 'string', 'enum' => ['php', 'db']],
                        'post_types' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'field_count' => ['type' => 'integer'],
                    ],
                    'required' => ['key', 'title', 'source', 'post_types', 'field_count'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['field_groups'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_list_field_groups',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call before metabox-get-field-group-schema to discover available keys. source=php means the group is PHP-registered and read-only; source=db means it was created via MB Builder and can be edited/deleted. post_types lists the object types the group is attached to. Use the returned key with metabox-get-field-group-schema to read the full field list.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{filter?: array{post_type?: string}} $args
 * @return array{field_groups: list<array<string, mixed>>}|WP_Error
 */
function metabox_list_field_groups(array $args): array|WP_Error
{
    $guard = mb_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $filter_pt = $args['filter']['post_type'] ?? null;
    $groups = mb_collect_all_field_groups();
    $out = [];

    foreach ($groups as $group) {
        $entry = mb_field_group_to_list_entry($group, $filter_pt);
        if ($entry !== null) {
            $out[] = $entry;
        }
    }

    return ['field_groups' => $out];
}

/**
 * Convert a raw field group array to a list-entry array, or null to skip.
 *
 * @param array<string, mixed> $group
 * @return array<string, mixed>|null
 */
function mb_field_group_to_list_entry(array $group, ?string $filter_pt): ?array
{
    /** @var mixed $key_raw */
    $key_raw = $group['key'] ?? $group['id'] ?? null;
    if (!is_string($key_raw) || $key_raw === '') {
        return null;
    }

    /** @var mixed $post_types_raw */
    $post_types_raw = $group['post_types'] ?? [];
    $post_types = is_array($post_types_raw)
        ? array_values(array_filter(
            array_map(static fn(mixed $pt): string => is_string($pt) ? $pt : '', $post_types_raw),
            static fn(string $pt): bool => $pt !== '',
        ))
        : [];

    if ($filter_pt !== null && !in_array($filter_pt, $post_types, strict: true)) {
        return null;
    }

    /** @var mixed $fields_raw */
    $fields_raw = $group['fields'] ?? [];
    $field_count = is_array($fields_raw) ? count($fields_raw) : 0;

    /** @var mixed $title_raw */
    $title_raw = $group['title'] ?? '';
    /** @var mixed $source_raw */
    $source_raw = $group['source'] ?? 'php';

    return [
        'key' => $key_raw,
        'title' => is_string($title_raw) ? $title_raw : '',
        'source' => $source_raw === 'db' ? 'db' : 'php',
        'post_types' => $post_types,
        'field_count' => $field_count,
    ];
}

/**
 * Collect all field groups from both PHP and DB sources.
 *
 * DB groups (MB Builder) are enumerated first, then PHP-only groups are
 * appended so there are no duplicates (a DB group already appears in the
 * rwmb_meta_boxes filter output too, keyed identically).
 *
 * @return list<array<string, mixed>>
 */
function mb_collect_all_field_groups(): array
{
    /** @var array<string, bool> $db_keys */
    $db_keys = [];
    /** @var list<array<string, mixed>> $out */
    $out = [];

    // DB groups via the meta-box CPT.
    if (mb_builder_available()) {
        mb_collect_db_field_groups($db_keys, $out);
    }

    // PHP-registered groups (not already in DB).
    mb_collect_php_field_groups($db_keys, $out);

    return $out;
}

/**
 * Append DB-stored field groups from the meta-box CPT to $out and track their keys in $db_keys.
 *
 * @param array<string, bool>        $db_keys
 * @param list<array<string, mixed>> $out
 */
function mb_collect_db_field_groups(array &$db_keys, array &$out): void
{
    /** @var mixed $posts_raw */
    $posts_raw = get_posts([
        'post_type' => 'meta-box',
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
            $raw = get_post_meta($post->ID, key: 'settings', single: true);
            return is_array($raw) ? $raw : [];
        })();
        // Read runtime-shape fields from `meta_box` postmeta. The legacy
        // `fields` postmeta now stores the React-UI shape (choice options as
        // newline-delimited string, `_id` per row) and would mislead the
        // agent if reused here.
        $fields = mb_read_runtime_fields_from_post($post);

        $key = (string) ($settings['id'] ?? $post->post_name);
        $db_keys[$key] = true;

        /** @var array<string, mixed> $merged */
        $merged = array_merge($settings, [
            'key' => $key,
            'title' => $post->post_title,
            'fields' => $fields,
            'source' => 'db',
        ]);
        $out[] = $merged;
    }
}

/**
 * Append PHP-registered field groups (skipping those already tracked in $db_keys) to $out.
 *
 * @param array<string, bool>        $db_keys
 * @param list<array<string, mixed>> $out
 */
function mb_collect_php_field_groups(array $db_keys, array &$out): void
{
    /** @var mixed $registered_raw */
    $registered_raw = apply_filters('rwmb_meta_boxes', []);
    if (!is_array($registered_raw)) {
        return;
    }
    /** @var array<int, mixed> $registered */
    $registered = $registered_raw;
    /** @var mixed $row */
    foreach ($registered as $row) {
        if (!is_array($row)) {
            continue;
        }
        $id = (string) ($row['id'] ?? $row['key'] ?? '');
        if ($id === '' || isset($db_keys[$id])) {
            continue;
        }
        /** @var array<string, mixed> $merged */
        $merged = array_merge($row, [
            'key' => $id,
            'source' => 'php',
        ]);
        $out[] = $merged;
    }
}
