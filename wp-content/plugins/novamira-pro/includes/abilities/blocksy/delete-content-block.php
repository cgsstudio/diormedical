<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: delete (trash or permanently remove) a Blocksy Content Block.
 */

wp_register_ability('novamira/blocksy-delete-content-block', [
    'label' => __('Delete Blocksy Content Block', domain: 'novamira-pro'),
    'description' => __(
        'Deletes one Blocksy Content Block (Companion Pro). Default: move to trash (recoverable from the Content Blocks admin list; a trashed block never renders). With force=true the post and ALL its meta (conditions, position, design settings) are permanently removed — unrecoverable. Trashing an already-trashed block is a no-op; force-deleting works from any status including trash. Both outcomes are verified by re-reading the post.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'Content-block post id.',
            ],
            'force' => [
                'type' => 'boolean',
                'description' => 'true = permanent delete (post + meta, unrecoverable). Default false = trash.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'result' => [
                'type' => 'string',
                'enum' => ['trashed', 'deleted'],
            ],
            'changed' => [
                'type' => 'boolean',
                'description' => 'False when the block was already in the requested state (already trashed, trash requested).',
            ],
        ],
        'required' => ['id', 'result', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_delete_content_block',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Prefer the default trash unless the user explicitly wants a permanent delete. force=true also removes the block\'s conditions and position meta — nothing can be restored afterwards.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: int, result: string, changed: bool}|WP_Error
 */
function blocksy_delete_content_block(array $input): array|WP_Error
{
    $gate = bly_cb_gate();
    if ($gate !== null) {
        return $gate;
    }

    $post = bly_cb_get_post($input['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    /** @var mixed $force */
    $force = $input['force'] ?? false;
    if (!is_bool($force)) {
        return bly_error('bly_invalid_input', message: '`force` must be a boolean.');
    }

    if ($force) {
        $deleted = wp_delete_post($post->ID, force_delete: true);
        clean_post_cache($post->ID);
        if (!$deleted instanceof \WP_Post || get_post($post->ID) instanceof \WP_Post) {
            return bly_error('bly_write_failed', sprintf(
                'wp_delete_post() could not permanently delete content block %d (a filter blocked it or the database write failed). Re-check with blocksy-list-content-blocks.',
                $post->ID,
            ));
        }
        return ['id' => $post->ID, 'result' => 'deleted', 'changed' => true];
    }

    if ($post->post_status === 'trash') {
        return ['id' => $post->ID, 'result' => 'trashed', 'changed' => false];
    }

    $trashed = wp_trash_post($post->ID);
    clean_post_cache($post->ID);
    // With EMPTY_TRASH_DAYS=0 core silently converts a trash into a
    // permanent delete — report what actually happened.
    if ($trashed instanceof \WP_Post && !get_post($post->ID) instanceof \WP_Post) {
        return ['id' => $post->ID, 'result' => 'deleted', 'changed' => true];
    }
    if (!$trashed instanceof \WP_Post || get_post_status($post->ID) !== 'trash') {
        return bly_error('bly_write_failed', sprintf(
            'wp_trash_post() could not trash content block %d (a filter blocked it or the status write failed). Its status is currently "%s".',
            $post->ID,
            (string) get_post_status($post->ID),
        ));
    }

    return ['id' => $post->ID, 'result' => 'trashed', 'changed' => true];
}
