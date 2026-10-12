<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Memory;

use WP_Error;
use WP_Post;

/**
 * Memory system bootstrap.
 *
 * Registers the `novamira_memory` custom post type and the post meta used
 * to classify memories. Memories are backed by posts so we get revisions,
 * capabilities, and REST integration for free — this is the WordPress-native
 * way to store structured, queryable content.
 */

if (!defined('ABSPATH')) {
    exit();
}

const NOVAMIRA_MEMORY_POST_TYPE = 'novamira_memory';

const NOVAMIRA_MEMORY_META_TYPE = '_novamira_memory_type';

/** Private post-array key carrying the per-call marker of memory_write_post(); never persisted. */
const NOVAMIRA_MEMORY_WRITE_MARKER = '_novamira_memory_write';

/** @var list<string> */
const NOVAMIRA_MEMORY_TYPES = ['user', 'feedback', 'project', 'reference'];

add_action('init', static function (): void {
    register_post_type(NOVAMIRA_MEMORY_POST_TYPE, [
        'label' => __('Memories', domain: 'novamira-pro'),
        'public' => false,
        'show_ui' => false,
        'show_in_rest' => false,
        'supports' => ['title', 'editor', 'excerpt', 'revisions'],
        'capability_type' => 'post',
        'map_meta_cap' => true,
        'has_archive' => false,
        'rewrite' => false,
        'query_var' => false,
    ]);

    register_post_meta(NOVAMIRA_MEMORY_POST_TYPE, NOVAMIRA_MEMORY_META_TYPE, [
        'type' => 'string',
        'single' => true,
        'show_in_rest' => false,
        'sanitize_callback' => static function (mixed $value): string {
            $value = is_string($value) ? $value : '';

            return in_array($value, NOVAMIRA_MEMORY_TYPES, strict: true) ? $value : 'project';
        },
    ]);
});

/**
 * Shape a memory post into the wire format used by the abilities.
 *
 * @return array{id: int, name: string, description: string, type: string, content: string, updated_at: string}
 */
function memory_shape(WP_Post $post): array
{
    /** @var mixed $type */
    $type = get_post_meta($post->ID, NOVAMIRA_MEMORY_META_TYPE, single: true);

    return [
        'id' => $post->ID,
        'name' => $post->post_title,
        'description' => $post->post_excerpt,
        'type' => is_string($type) && $type !== '' ? $type : 'project',
        'content' => $post->post_content,
        'updated_at' => $post->post_modified_gmt,
    ];
}

/**
 * Insert or update a memory post with its body stored verbatim.
 *
 * The body is plain text / Markdown, not HTML: it is stored exactly as given
 * and escaped at every output point instead (esc_textarea in the admin form;
 * the abilities return it as JSON only).
 *
 * wp_insert_post() / wp_update_post() unslash their input, so the fields are
 * wp_slash()ed first. They also pass the body through content_save_pre (kses
 * for users without unfiltered_html, convert_invalid_entities, balanceTags,
 * third-party callbacks), and so does a revision saved alongside. The global
 * hooks are left untouched; instead, for this call only:
 * - a wp_insert_post_data guard puts the original body back on the row that
 *   carries this call's random marker (a private post-array key WordPress
 *   does not store) and, on update, on revisions whose parent is $id;
 * - on create, revisions of the new row written during the call (reported by
 *   _wp_put_post_revision) are repaired after the insert returns, because the
 *   new id is unknown while they are written. If a repair fails, the new row
 *   is deleted and a memory_revision_repair_failed error is returned.
 * Any other post or revision written by hooks during this call, including
 * other memories, is processed normally.
 *
 * @param array{post_title: string, post_excerpt: string, post_content: string} $fields Unslashed values.
 * @return int|WP_Error Post id, or the insert/update error.
 */
function memory_write_post(int $id, array $fields): int|WP_Error
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    // wp_insert_post() encodes emoji right before wp_insert_post_data when the
    // column cannot hold 4-byte characters; the stored body keeps that step.
    $charset = $wpdb->get_col_charset($wpdb->posts, 'post_content');
    $raw = $fields['post_content'];
    $stored_raw = $charset === 'utf8' || $charset === 'utf8mb3' ? wp_encode_emoji($raw) : $raw;
    $marker = bin2hex(random_bytes(16));

    $postarr = [
        'post_type' => NOVAMIRA_MEMORY_POST_TYPE,
        'post_status' => 'publish',
        'post_title' => wp_slash($fields['post_title']),
        'post_excerpt' => wp_slash($fields['post_excerpt']),
        'post_content' => wp_slash($raw),
        NOVAMIRA_MEMORY_WRITE_MARKER => $marker,
    ];
    if ($id > 0) {
        $postarr['ID'] = $id;
    }

    $guard = memory_content_guard($id, $marker, [wp_slash($raw), wp_slash($stored_raw)]);
    /** @var array<int, int> $revisions */
    $revisions = [];
    $record = static function (mixed $revision_id, mixed $parent_id) use (&$revisions): void {
        if (is_numeric($revision_id) && is_numeric($parent_id)) {
            $revisions[(int) $revision_id] = (int) $parent_id;
        }
    };

    add_filter('wp_insert_post_data', $guard, PHP_INT_MAX, accepted_args: 3);
    add_action('_wp_put_post_revision', $record, accepted_args: 2);
    try {
        // The stubs' post-array shape is closed; the marker key is private and ignored by WordPress.
        // @mago-expect analysis:possibly-invalid-argument(2)
        $result = $id > 0 ? wp_update_post($postarr, wp_error: true) : wp_insert_post($postarr, wp_error: true);
    } finally {
        remove_filter('wp_insert_post_data', $guard, PHP_INT_MAX);
        remove_action('_wp_put_post_revision', $record);
    }

    if ($id > 0 || !is_int($result) || $result <= 0) {
        return $result;
    }

    $failed = memory_repair_created_revisions($result, array_keys($revisions, $result, true), $stored_raw);
    if ($failed === []) {
        return $result;
    }

    // A create that reports an error leaves nothing behind, so retrying it
    // cannot duplicate the memory: the new row and its revisions are deleted.
    $deleted = wp_delete_post($result, force_delete: true);

    return new WP_Error(
        'memory_revision_repair_failed',
        __(
            'The memory was not saved: a revision written during the save could not be stored verbatim.',
            domain: 'novamira-pro',
        ),
        ['id' => $result, 'revision_ids' => $failed, 'rolled_back' => $deleted instanceof WP_Post],
    );
}

/**
 * The wp_insert_post_data callback of one memory_write_post() call. It puts
 * the stored body (second entry of $bodies) back when the unsanitized body is
 * one of $bodies and the row is either the one carrying $marker or, on update,
 * a revision whose parent is $id.
 *
 * @param array{0: string, 1: string} $bodies Slashed body as given and as stored.
 * @return \Closure(array<array-key, mixed>, array<array-key, mixed>, array<array-key, mixed>): array<array-key, mixed>
 */
function memory_content_guard(int $id, string $marker, array $bodies): \Closure
{
    return static function (array $data, array $sanitized, array $unsanitized) use ($id, $marker, $bodies): array {
        if (!in_array($unsanitized['post_content'] ?? null, $bodies, strict: true)) {
            return $data;
        }
        $marked = ($unsanitized[NOVAMIRA_MEMORY_WRITE_MARKER] ?? null) === $marker;
        if ($marked && memory_is_row($data, $sanitized, $id) || $id > 0 && memory_revision_parent($data) === $id) {
            $data['post_content'] = $bodies[1];
        }

        return $data;
    };
}

/**
 * Whether a wp_insert_post_data call writes the memory row of this call: a
 * memory post with the same id on update, or a new row when $id is 0.
 *
 * @param array<array-key, mixed> $data
 * @param array<array-key, mixed> $sanitized
 */
function memory_is_row(array $data, array $sanitized, int $id): bool
{
    if (($data['post_type'] ?? null) !== NOVAMIRA_MEMORY_POST_TYPE) {
        return false;
    }

    return (is_numeric($sanitized['ID'] ?? null) ? (int) $sanitized['ID'] : 0) === $id;
}

/**
 * Parent id of a revision being written, or 0 for any other post.
 *
 * @param array<array-key, mixed> $data
 */
function memory_revision_parent(array $data): int
{
    if (($data['post_type'] ?? null) !== 'revision') {
        return 0;
    }

    return is_numeric($data['post_parent'] ?? null) ? (int) $data['post_parent'] : 0;
}

/**
 * Store the verbatim body on revisions of a just-created memory that were
 * written during its insert. Revisions copy the row, so they are repaired only
 * while the row still holds exactly this body (otherwise nothing is touched
 * and nothing is reported). Each repaired revision is read back after its
 * post cache is cleaned.
 *
 * @param list<int> $revision_ids Revisions whose parent is $post_id.
 * @param string $body Unslashed body as stored on the row.
 * @return list<int> Revisions whose update failed or whose stored body differs.
 */
function memory_repair_created_revisions(int $post_id, array $revision_ids, string $body): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    if ($revision_ids === []) {
        return [];
    }

    clean_post_cache($post_id);
    /** @var WP_Post|null $row */
    $row = get_post($post_id);
    if (!$row instanceof WP_Post || $row->post_content !== $body) {
        return [];
    }

    $failed = [];
    foreach ($revision_ids as $revision_id) {
        $updated = $wpdb->update(
            $wpdb->posts,
            ['post_content' => $body],
            ['ID' => $revision_id, 'post_type' => 'revision', 'post_parent' => $post_id],
        );
        clean_post_cache($revision_id);
        /** @var WP_Post|null $revision */
        $revision = get_post($revision_id);
        if ($updated === false || !$revision instanceof WP_Post || $revision->post_content !== $body) {
            $failed[] = $revision_id;
        }
    }

    return $failed;
}
