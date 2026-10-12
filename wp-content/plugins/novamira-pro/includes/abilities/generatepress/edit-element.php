<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-edit-element', [
    'label' => __('Edit GeneratePress Element', domain: 'novamira-pro'),
    'description' => __(
        'Patches an existing `gp_elements` post. Merge semantics: only the keys you pass are updated, omitted keys keep their current value. When changing `type` (or moving `type=block` between `block_type` values), pass both fields together — the integration validates the resulting combination. Conditions (display / exclude / user) are NOT touched by this ability; use generatepress-set-element-conditions for those. The `_generate_hook_execute_php` flag is permanently blocked. Returns WP_Error when the id does not point at a `gp_elements` post.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Id of the element to edit.'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string', 'enum' => ['publish', 'draft']],
            'type' => ['type' => 'string', 'enum' => ['hook', 'header', 'layout', 'block']],
            'block_type' => [
                'type' => 'string',
                'enum' => [
                    'site-header',
                    'site-footer',
                    'right-sidebar',
                    'left-sidebar',
                    'content-template',
                    'loop-template',
                    'search-modal',
                    'page-hero',
                    'post-meta-template',
                    'post-navigation-template',
                    'archive-navigation-template',
                    'hook',
                ],
            ],
            'hook' => ['type' => 'string'],
            'custom_hook' => ['type' => 'string'],
            'content' => ['type' => 'string'],
            'priority' => ['type' => 'integer'],
            'execute_shortcodes' => ['type' => 'boolean'],
            'disable_site_header' => ['type' => 'boolean'],
            'disable_site_footer' => ['type' => 'boolean'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'element' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_edit_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge semantics — only fields you pass are updated. Editing an active element replaces what the front-end renders on the next request. Use generatepress-set-element-conditions for visibility rules (this ability never touches them).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function generatepress_edit_element(array $input): array|WP_Error
{
    $id = gp_coerce_int($input['id'] ?? 0);
    if ($id <= 0) {
        return gp_error('gp_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    if (isset($input['priority']) && is_int($input['priority']) && $input['priority'] < 0) {
        return [
            'success' => false,
            'error' => '`priority` must be 0 or a positive integer (GP sanitizes with absint).',
        ];
    }
    if (isset($input['title']) && is_string($input['title'])) {
        $input['title'] = gp_strip_nul_bytes($input['title']);
    }
    $post = gp_load_element($id);
    if ($post === null) {
        return gp_error('gp_not_found', sprintf(
            __('gp_elements post with id %d not found.', domain: 'novamira-pro'),
            $id,
        ));
    }

    $shape_err = gp_validate_element_edit_shape($id, $input);
    if ($shape_err !== null) {
        return ['success' => false, 'error' => $shape_err];
    }

    $post_err = gp_apply_element_post_fields($id, $input);
    if ($post_err !== null) {
        return ['success' => false, 'error' => $post_err];
    }

    gp_apply_element_meta($id, $input);

    $fresh = gp_load_element($id);
    if ($fresh === null) {
        return ['success' => false, 'error' => 'Element saved but could not be re-loaded.'];
    }
    return ['success' => true, 'element' => gp_element_to_array($fresh)];
}

/**
 * Validates the type / block_type combination edit-element will end up
 * with after the patch is applied. Resolves missing halves against the
 * stored values (so passing `block_type` alone still validates against
 * the existing `type`). Returns null when valid, message string on bad
 * combination.
 *
 * @param array<string, mixed> $input
 */
function gp_validate_element_edit_shape(int $id, array $input): ?string
{
    $merged = $input;
    if (!array_key_exists('type', $merged) && array_key_exists('block_type', $input)) {
        $merged['type'] = (string) get_post_meta(post_id: $id, key: '_generate_element_type', single: true);
    }
    if (array_key_exists('type', $merged) && $merged['type'] === 'block' && !array_key_exists('block_type', $merged)) {
        $merged['block_type'] = (string) get_post_meta(post_id: $id, key: '_generate_block_type', single: true);
    }
    // Mirror the block_type half-resolve for hook elements: if the patch
    // keeps `type=hook` (explicit or implied via the stored type) without
    // re-sending `hook`, fall back to the persisted `_generate_hook` so
    // the validator's hook-required check doesn't fire on legitimate
    // partial edits (e.g. changing only the priority on an existing hook).
    if (!array_key_exists('type', $merged) && array_key_exists('hook', $input)) {
        $merged['type'] = (string) get_post_meta(post_id: $id, key: '_generate_element_type', single: true);
    }
    if (array_key_exists('type', $merged) && $merged['type'] === 'hook' && !array_key_exists('hook', $merged)) {
        $merged['hook'] = (string) get_post_meta(post_id: $id, key: '_generate_hook', single: true);
    }
    return gp_validate_element_shape($merged);
}

/**
 * Writes the post-level fields (title / status) when the patch includes
 * them. Returns null on success, an error message string on wp_update_post
 * failure.
 *
 * @param array<string, mixed> $input
 */
function gp_apply_element_post_fields(int $id, array $input): ?string
{
    $post_update = ['ID' => $id];
    if (isset($input['title']) && is_string($input['title'])) {
        $post_update['post_title'] = $input['title'];
    }
    if (isset($input['status']) && in_array($input['status'], ['publish', 'draft'], strict: true)) {
        $post_update['post_status'] = $input['status'];
    }
    if (count($post_update) <= 1) {
        return null;
    }
    $r = wp_update_post($post_update, wp_error: true);
    if ($r instanceof \WP_Error) {
        return 'wp_update_post failed: ' . $r->get_error_message();
    }
    return null;
}
