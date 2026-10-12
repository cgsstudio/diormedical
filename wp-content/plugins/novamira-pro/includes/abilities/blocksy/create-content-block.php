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
 * Ability: create a Blocksy Content Block.
 */

wp_register_ability('novamira/blocksy-create-content-block', [
    'label' => __('Create Blocksy Content Block', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Blocksy Content Block (Companion Pro): a ct_content_block post with the given title and template type (hook / popup / 404 / header / footer / archive / single / nothing_found / maintenance), optional initial display conditions (a flat rule list or a full {relation, conditions} descriptor with nested groups — the same grammar the customizer saves) and — for hook blocks — an optional hook position. Status defaults to draft; a draft block never renders. Conditions and position are strictly validated against the install\'s live rule/hook catalogs. The block is created EMPTY: set its content afterwards with the generic post/Gutenberg abilities (the returned hint says how). Without conditions the block matches nowhere (maintenance/nothing_found template types default to everywhere).',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => [
                'type' => 'string',
                'description' => 'Block title (admin-facing), 1–200 bytes.',
            ],
            'template_type' => [
                'type' => 'string',
                'enum' => [
                    'hook',
                    'popup',
                    '404',
                    'header',
                    'footer',
                    'archive',
                    'single',
                    'nothing_found',
                    'maintenance',
                ],
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['draft', 'publish'],
                'description' => 'Post status; defaults to draft (never renders until published).',
            ],
            'conditions' => [
                'type' => ['array', 'object'],
                'description' => 'Initial display conditions: either a JSON array of {type, rule, payload?} rows (persisted under relation OR, like the customizer) or a full {relation, conditions} descriptor with nested relation groups. Excludes act within their own group\'s relation; nested groups count as includes in their parent. Omit for none.',
            ],
            'position' => [
                'type' => 'object',
                'description' => 'Hook blocks only: {location, priority?, custom_location?, paragraphs_count?, headings_count?, cards_count?, repeat_for_every_card?, additional_locations?}. location is a HooksManager id or "custom_hook".',
            ],
        ],
        'required' => ['title', 'template_type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'template_type' => ['type' => 'string'],
            'conditions_count' => [
                'type' => 'integer',
                'description' => 'Leaf display-condition rules persisted (rules inside nested groups counted individually).',
            ],
            'position' => [
                'type' => ['string', 'null'],
                'description' => 'Position summary for hook blocks ("wp_body_open@10"), null otherwise.',
            ],
            'content_hint' => ['type' => 'string'],
        ],
        'required' => ['id', 'title', 'status', 'template_type', 'conditions_count', 'position', 'content_hint'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_create_content_block',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'To make a hook block actually render: create it with a position and conditions, set its content, then publish. All three writes are verified; after any failed meta write the created post is verifiably deleted, or the distinct bly_rollback_failed error names the orphan post id with removal instructions — never an unverified success claim.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{id: int, title: string, status: string, template_type: string, conditions_count: int, position: null|string, content_hint: string}|WP_Error
 */
// @mago-expect lint:cyclomatic-complexity
// @mago-expect lint:halstead
function blocksy_create_content_block(array $input): array|WP_Error
{
    $gate = bly_cb_gate();
    if ($gate !== null) {
        return $gate;
    }

    /** @var mixed $title_raw */
    $title_raw = $input['title'] ?? null;
    if (!is_string($title_raw) || trim($title_raw) === '' || strlen($title_raw) > BLY_CB_MAX_TITLE_BYTES) {
        return bly_error('bly_invalid_input', sprintf(
            '`title` is required and must be a non-empty string of at most %d bytes.',
            BLY_CB_MAX_TITLE_BYTES,
        ));
    }
    $title = trim($title_raw);

    /** @var mixed $template_type */
    $template_type = $input['template_type'] ?? null;
    if (!is_string($template_type) || !in_array($template_type, bly_cb_template_types(), strict: true)) {
        return bly_error('bly_invalid_input', sprintf('`template_type` must be one of: %s.', implode(
            ', ',
            bly_cb_template_types(),
        )));
    }

    /** @var mixed $status */
    $status = $input['status'] ?? 'draft';
    if ($status !== 'draft' && $status !== 'publish') {
        return bly_error('bly_invalid_input', message: '`status` must be "draft" or "publish".');
    }

    $conditions = null;
    if (array_key_exists('conditions', $input)) {
        $conditions = bly_cb_validate_conditions($input['conditions'], $template_type);
        if ($conditions instanceof WP_Error) {
            return $conditions;
        }
    }

    $position = null;
    if (array_key_exists('position', $input)) {
        if ($template_type !== 'hook') {
            return bly_error('bly_invalid_input', sprintf(
                '`position` applies only to template_type "hook" — a "%s" block renders through its own template surface, not a theme hook.',
                $template_type,
            ));
        }
        $position = bly_cb_validate_position($input['position'], effective_base: bly_cb_position_defaults());
        if ($position instanceof WP_Error) {
            return $position;
        }
    }

    $post_id = wp_insert_post([
        'post_title' => wp_slash($title),
        'post_status' => $status,
        'post_type' => BLY_CB_POST_TYPE,
    ], wp_error: true);
    if ($post_id instanceof WP_Error) {
        return bly_error('bly_write_failed', sprintf(
            'wp_insert_post() refused to create the content block (%s). Nothing was created.',
            $post_id->get_error_code(),
        ));
    }

    // template_type meta — verified; a block whose type meta is missing is
    // invisible to every Companion code path, so a failed write here goes
    // through the verified-rollback contract (deleted and confirmed, or
    // the orphan named honestly).
    update_post_meta($post_id, meta_key: 'template_type', meta_value: $template_type);
    wp_cache_delete($post_id, group: 'post_meta');
    if (get_post_meta($post_id, key: 'template_type', single: true) !== $template_type) {
        return bly_cb_create_rollback(
            $post_id,
            reason: 'the template_type meta of the new content block did not persist (a database failure or a meta filter blocked it)',
        );
    }

    if ($conditions !== null || $position !== null) {
        $atts = [];
        if ($conditions !== null) {
            $atts['conditions'] = $conditions;
        }
        if ($position !== null) {
            $atts = array_merge($atts, $position['set']);
            if ($position['additional_locations'] !== null) {
                $atts['additional_locations'] = $position['additional_locations'];
            }
        }
        $written = bly_cb_locked_atts_edit($post_id, static fn(array $prior): array => array_merge($prior, $atts));
        if ($written instanceof WP_Error) {
            return bly_cb_create_rollback($post_id, reason: sprintf(
                'the options meta (conditions/position) of the new content block could not be persisted: %s',
                $written->get_error_message(),
            ));
        }
    }

    $final_atts = bly_cb_read_atts($post_id);
    return [
        'id' => $post_id,
        'title' => bly_ext_clean_text($title, BLY_CB_MAX_TITLE_BYTES),
        'status' => $status,
        'template_type' => $template_type,
        'conditions_count' => count(bly_cb_leaf_rules(bly_cb_stored_condition_descriptor($final_atts)['conditions'])),
        'position' => $template_type === 'hook' ? bly_cb_position_summary($final_atts) : null,
        'content_hint' => sprintf(
            'The block is empty. Set its content with novamira/update-post (content/post_content, full block markup) using post id %d, then publish it to render. novamira/gutenberg-write-content is not a general write path — it only accepts registered novamira/* dynamic-only blocks; native Gutenberg blocks go through update-post or the gutenberg pending-change pipeline.',
            $post_id,
        ),
    ];
}

/**
 * The create-failure rollback CONTRACT: after any failed meta write,
 * the created post is either VERIFIABLY deleted (wp_delete_post
 * returned the post AND a fresh get_post finds nothing) — only then
 * may the error claim "nothing remains" — or the distinct
 * bly_rollback_failed is returned naming the orphan post id and how to
 * remove it (a pre_delete_post filter refusing, a database failure).
 * Never an unverified success claim.
 */
function bly_cb_create_rollback(int $post_id, string $reason): WP_Error
{
    $deleted = wp_delete_post($post_id, force_delete: true);
    clean_post_cache($post_id);
    $gone = $deleted instanceof \WP_Post && !get_post($post_id) instanceof \WP_Post;

    if ($gone) {
        return bly_error('bly_write_failed', sprintf(
            'Could not create the content block: %s. The partially-created post %d was deleted (verified) — nothing remains.',
            $reason,
            $post_id,
        ));
    }

    return bly_error('bly_rollback_failed', sprintf(
        'Could not create the content block: %s — AND the rollback delete of the partially-created post failed (a filter blocked wp_delete_post or the database write failed). An incomplete content block remains as post %d; remove it with blocksy-delete-content-block (force=true) or from the Content Blocks admin list.',
        $reason,
        $post_id,
    ));
}
