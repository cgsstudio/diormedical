<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

if (!defined('ABSPATH')) {
    exit();
}

// Sync variable product aggregates when a variation is untrashed.
// Without this the parent's _price / _stock_status postmeta stay stale until
// something else triggers WC_Product_Variable::sync() — agents reading the
// parent immediately after wp_untrash_post() would see outdated data.
add_action(
    'untrashed_post',
    static function (int $post_id): void {
        if (!wc_min_runtime_available()) {
            return;
        }
        /** @var \WP_Post|array<string, mixed>|null $post */
        $post = get_post($post_id);
        if (!$post instanceof \WP_Post || $post->post_type !== 'product_variation') {
            return;
        }
        $parent_id = (int) $post->post_parent;
        if ($parent_id > 0 && class_exists('WC_Product_Variable')) {
            \WC_Product_Variable::sync($parent_id);
            clean_post_cache($parent_id);
        }
    },
    priority: 10,
    accepted_args: 1,
);
