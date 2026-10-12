<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WC_Product_Variable;
use WC_Product_Variation;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/woocommerce-delete-product-variation', [
    'label' => __('Delete Product Variation', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a single product variation. Requires `id`. Default behaviour is soft delete (variation moves to trash; `status` returns `trash` in the response). Pass `force: true` for permanent deletion (returns `deleted_id`). Soft-deleting an already-trashed variation is an idempotent no-op. If this variation was the storefront default, the default is cleared on the parent before deletion. The parent product is not affected. If the parent product is currently in trash, this ability still deletes the variation. To restore the parent and its variations later, untrash the parent before deleting individual variations. Possible errors: wc_invalid_input, wc_not_found (variation does not exist), wc_internal_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'woocommerce',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Variation post id (required).'],
            'force' => [
                'type' => 'boolean',
                'description' => 'If true, permanently deletes the variation. Defaults to false (soft delete / trash).',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'variation' => [
                'type' => 'object',
                'description' => 'Variation object after soft delete (status=trash). Present only on soft delete.',
            ],
            'deleted_id' => [
                'type' => 'integer',
                'description' => 'Id of the permanently deleted variation. Present only when force=true.',
            ],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Woocommerce\\woocommerce_delete_product_variation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Resolve a variation post allowing trashed status (for delete operations).
 * Returns WP_Error when not found or not a product_variation post.
 */
function wc_resolve_variation_for_delete(int $id): WC_Product_Variation|WP_Error
{
    /** @var \WP_Post|\WP_Post[]|null $raw */
    $raw = get_post($id);
    $post = $raw instanceof \WP_Post ? $raw : null;
    if (!$post instanceof \WP_Post) {
        return wc_error('wc_not_found', sprintf(__('Variation %d not found.', domain: 'novamira-pro'), $id));
    }
    if ($post->post_type !== 'product_variation') {
        return wc_error('wc_not_found', sprintf(
            __('Post %d is not a product variation (post_type: %s).', domain: 'novamira-pro'),
            $id,
            $post->post_type,
        ));
    }
    return new WC_Product_Variation($id);
}

/**
 * Soft-delete a variation (move to trash) and return the API response.
 *
 * @return array<string, mixed>|WP_Error
 */
function wc_soft_delete_variation(WC_Product_Variation $v): array|WP_Error
{
    $variation_id = $v->get_id();
    $parent_id = $v->get_parent_id();

    try {
        $result = \wp_trash_post($variation_id);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', $e->getMessage());
    }
    if ($result === false || $result === null) {
        return wc_error('wc_internal_error', __('Variation soft delete failed unexpectedly.', domain: 'novamira-pro'));
    }

    clean_post_cache($variation_id);
    clean_post_cache($parent_id);
    \WC_Product_Variable::sync($parent_id);

    // Reload the trashed variation to return current state.
    $trashed = new WC_Product_Variation($variation_id);
    $row = wc_variation_full_to_array($trashed);
    $row['status'] = 'trash';

    return ['success' => true, 'variation' => $row];
}

/**
 * Force-delete a variation permanently and return the API response.
 *
 * @return array<string, mixed>|WP_Error
 */
function wc_force_delete_variation(WC_Product_Variation $v): array|WP_Error
{
    $variation_id = $v->get_id();
    $parent_id = $v->get_parent_id();

    try {
        $result = \wp_delete_post(post_id: $variation_id, force_delete: true);
    } catch (\Throwable $e) {
        return wc_error('wc_internal_error', $e->getMessage());
    }
    if ($result === false || $result === null) {
        return wc_error('wc_internal_error', __('Variation deletion failed unexpectedly.', domain: 'novamira-pro'));
    }

    clean_post_cache($variation_id);
    clean_post_cache($parent_id);
    \WC_Product_Variable::sync($parent_id);

    return ['success' => true, 'deleted_id' => $variation_id];
}

/**
 * Per-process re-entrancy guard for woocommerce_delete_product_variation.
 * Keyed by variation id; set before delete, cleared in finally.
 *
 * @var array<int, true>
 */
$NOVAMIRA_DELETE_VARIATION_IN_PROGRESS = [];

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function woocommerce_delete_product_variation(array $input): array|WP_Error
{
    global $NOVAMIRA_DELETE_VARIATION_IN_PROGRESS;

    $gate = wc_check_minimum();
    if ($gate !== null) {
        return $gate;
    }

    if (!isset($input['id']) || !is_numeric($input['id'])) {
        return wc_error('wc_invalid_input', __('`id` is required.', domain: 'novamira-pro'));
    }

    $del_id = (int) $input['id'];

    if (isset($NOVAMIRA_DELETE_VARIATION_IN_PROGRESS[$del_id])) {
        return wc_error('wc_internal_error', __(
            'Recursive variation delete detected; aborting.',
            domain: 'novamira-pro',
        ));
    }

    $v = wc_resolve_variation_for_delete($del_id);
    if ($v instanceof WP_Error) {
        return $v;
    }

    /** @var array<int, true> $NOVAMIRA_DELETE_VARIATION_IN_PROGRESS */
    $NOVAMIRA_DELETE_VARIATION_IN_PROGRESS[$del_id] = true;
    try {
        $force = isset($input['force']) && ($input['force'] === true || $input['force'] === 1);

        // Idempotent: soft delete on already-trashed variation returns current state.
        /** @var \WP_Post|\WP_Post[]|null $rawPost */
        $rawPost = get_post($v->get_id());
        $post = $rawPost instanceof \WP_Post ? $rawPost : null;
        if (!$force && $post instanceof \WP_Post && $post->post_status === 'trash') {
            $row = wc_variation_full_to_array($v);
            $row['status'] = 'trash';
            return ['success' => true, 'variation' => $row];
        }

        // Clear default if this is the storefront default variation.
        if (wc_is_default_variation($v->get_parent_id(), $v)) {
            wc_clear_default_variation($v->get_parent_id());
        }

        if ($force) {
            return wc_force_delete_variation($v);
        }
        return wc_soft_delete_variation($v);
    } finally {
        unset($NOVAMIRA_DELETE_VARIATION_IN_PROGRESS[$del_id]);
    }
}
