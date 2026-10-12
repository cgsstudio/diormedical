<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/seopress-delete-redirect', [
    'label' => __('Delete Redirect (SeoPress)', domain: 'novamira-pro'),
    'description' => __(
        'PERMANENTLY deletes a SeoPress Pro redirect / monitored 404 (a seopress_404 custom post type) by id (requires SeoPress Pro). This is a HARD delete — the post is removed outright, NOT trashed, and cannot be undone (mirrors SeoPress\'s own behaviour). Use it to drop a redirect OR to clear a monitored 404 log entry (they share the seopress_404 CPT). Identify the entry by id (from list-redirects). SAFETY: if the id does not exist this is an idempotent no-op (returns {deleted: false, id} — already gone); if the id is a post of ANOTHER type it is REFUSED (seopress_invalid_input, 400) and nothing is deleted — this ability NEVER hard-deletes an arbitrary post. Returns {deleted: bool, id}.',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'The seopress_404 post id to delete (from list-redirects).'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'id' => ['type' => 'integer'],
        ],
        'required' => ['deleted', 'id'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_delete_redirect',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'PERMANENT HARD delete of one SeoPress Pro redirect / monitored 404 (the seopress_404 CPT) by id — not trashed, not reversible. Also clears a monitored 404 log entry. A missing id is an idempotent no-op ({deleted:false}); a post of another type is REFUSED (this NEVER hard-deletes an arbitrary post). Confirm the id with list-redirects / get-redirect before calling.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id?: mixed} $args
 * @return array{deleted: bool, id: int}|WP_Error
 */
function seopress_delete_redirect(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }
    if (!sp_redirect_supported()) {
        return sp_pro_required('Redirects');
    }

    $id = sp_int($args['id'] ?? null);
    if ($id <= 0) {
        return sp_invalid('id must be a positive integer');
    }

    /** @var WP_Post|null $post */
    $post = get_post($id);
    if (!$post instanceof WP_Post) {
        // Already gone — idempotent no-op, never an error.
        return ['deleted' => false, 'id' => $id];
    }
    if ($post->post_type !== 'seopress_404') {
        // NEVER hard-delete an arbitrary post: refuse anything that is not a redirect.
        return sp_invalid("post {$id} is not a redirect");
    }

    // HARD delete (force = true): bypass the trash, mirroring SeoPress's REST DELETE.
    wp_delete_post($id, force_delete: true);

    return ['deleted' => true, 'id' => $id];
}
