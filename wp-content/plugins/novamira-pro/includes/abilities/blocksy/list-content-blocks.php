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
 * Ability: enumerate Blocksy Content Blocks (Companion Pro).
 */

wp_register_ability('novamira/blocksy-list-content-blocks', [
    'label' => __('List Blocksy Content Blocks', domain: 'novamira-pro'),
    'description' => __(
        'Enumerates every Blocksy Content Block (the ct_content_block posts, a Companion Pro feature): id, title, post status (trashed blocks included and flagged), template type (hook / popup / 404 / header / footer / archive / single / nothing_found / maintenance), the is_hook_enabled kill switch, a compact display-condition summary, and — for hook blocks — the hook position ("wp_body_open@10"). Use blocksy-get-content-block for the full conditions and position of one block. Content blocks are regular posts: their CONTENT is read/edited through the generic post and Gutenberg abilities, not here.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => new \stdClass(),
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'blocks' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'template_type' => [
                            'type' => 'string',
                            'description' => 'One of hook/popup/404/header/footer/archive/single/nothing_found/maintenance; "" when the meta is missing (corrupted block).',
                        ],
                        'enabled' => [
                            'type' => 'boolean',
                            'description' => 'The is_hook_enabled kill switch (default true). A disabled block never renders regardless of conditions.',
                        ],
                        'conditions_count' => [
                            'type' => 'integer',
                            'description' => 'Leaf display-condition rules (rules inside nested relation groups counted individually).',
                        ],
                        'condition_summary' => ['type' => 'string'],
                        'position' => [
                            'type' => ['string', 'null'],
                            'description' => 'Hook blocks only: "location@priority (+N more)" or "(none)". Null for other template types.',
                        ],
                    ],
                    'required' => [
                        'id',
                        'title',
                        'status',
                        'template_type',
                        'enabled',
                        'conditions_count',
                        'condition_summary',
                        'position',
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'blocks_omitted' => [
                'type' => 'integer',
                'description' => 'How many content blocks exist beyond the 100-row cap (exact count).',
            ],
        ],
        'required' => ['blocks', 'blocks_omitted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_list_content_blocks',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'An empty condition list means the block matches NOWHERE (except maintenance/nothing_found blocks, which then default to everywhere). Only published, enabled blocks with a matching include condition render.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{
 *     blocks: list<array{id: int, title: string, status: string, template_type: string, enabled: bool, conditions_count: int, condition_summary: string, position: null|string}>,
 *     blocks_omitted: int,
 * }|WP_Error
 */
function blocksy_list_content_blocks(array $input): array|WP_Error
{
    $gate = bly_cb_gate();
    if ($gate !== null) {
        return $gate;
    }

    $statuses = ['publish', 'future', 'draft', 'pending', 'private', 'trash'];

    /** @var list<\WP_Post> $posts */
    $posts = get_posts([
        'post_type' => BLY_CB_POST_TYPE,
        'post_status' => $statuses,
        'numberposts' => BLY_CB_MAX_LIST,
        'orderby' => 'ID',
        'order' => 'ASC',
        'suppress_filters' => false,
    ]);

    $blocks = [];
    foreach ($posts as $post) {
        $atts = bly_cb_read_atts($post->ID);
        $template_type = bly_cb_template_type($post->ID);
        $leaves = bly_cb_leaf_rules(bly_cb_stored_condition_descriptor($atts)['conditions']);
        $blocks[] = [
            'id' => $post->ID,
            'title' => bly_ext_clean_text($post->post_title, BLY_CB_MAX_TITLE_BYTES),
            'status' => $post->post_status,
            'template_type' => bly_ext_clean_text($template_type, BLY_EXT_MAX_NAME_BYTES),
            'enabled' => bly_cb_is_enabled($atts),
            'conditions_count' => count($leaves),
            'condition_summary' => bly_cb_condition_summary($leaves, $template_type),
            'position' => $template_type === 'hook' ? bly_cb_position_summary($atts) : null,
        ];
    }

    // True total beyond the cap — a direct count, not an over-fetch.
    global $wpdb;
    /** @var \wpdb $wpdb */
    $placeholders = implode(', ', array_fill(start_index: 0, count: count($statuses), value: '%s'));
    // @mago-expect analysis:possibly-invalid-argument
    $sql = $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = %s AND post_status IN ({$placeholders})",
        array_merge([BLY_CB_POST_TYPE], $statuses),
    );
    $total = is_string($sql) ? (int) $wpdb->get_var($sql) : count($blocks);

    return [
        'blocks' => $blocks,
        'blocks_omitted' => max(0, $total - count($blocks)),
    ];
}
