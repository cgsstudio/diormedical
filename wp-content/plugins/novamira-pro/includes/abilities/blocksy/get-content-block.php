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
 * Ability: read one Blocksy Content Block's render-relevant state.
 */

wp_register_ability('novamira/blocksy-get-content-block', [
    'label' => __('Get Blocksy Content Block', domain: 'novamira-pro'),
    'description' => __(
        'Reads one Blocksy Content Block (Companion Pro): the full display-condition descriptor ({relation, conditions} — rows are {type, rule, payload} rules or nested {relation, conditions} groups, exactly as the customizer stores them; excludes act within their OWN group\'s relation, and a nested group counts as an include in its parent — e.g. root OR(include:everywhere, AND(exclude:everywhere)) still matches via the direct include), the hook position settings (location, priority, custom_location, count fields, additional_locations), the is_hook_enabled kill switch, template type/subtype, and which other option keys the block stores (names only — design keys, visibility, etc.). The block CONTENT is deliberately not returned: it is a regular post, use novamira/gutenberg-get-content (or the generic post abilities) with this same id; `content.bytes` tells whether that is worth a call.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'Content-block post id (from blocksy-list-content-blocks).',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'template_type' => ['type' => 'string'],
            'template_subtype' => [
                'type' => ['string', 'null'],
                'description' => 'archive/single blocks only (stored value or the type default: card for archive, canvas for single). Null otherwise.',
            ],
            'enabled' => ['type' => 'boolean'],
            'conditions' => [
                'type' => 'object',
                'description' => 'The stored display conditions in descriptor form: {relation: OR|AND, conditions: [...]} with rule rows {type, rule, payload} and nested {relation, conditions} groups verbatim (a legacy flat stored list is presented under relation OR, exactly like the matcher normalizes it; storage is not rewritten). Empty conditions means the block matches nowhere (maintenance/nothing_found: defaults to everywhere).',
            ],
            'position' => [
                'type' => ['object', 'null'],
                'description' => 'Hook blocks only: {location, priority, custom_location, paragraphs_count, headings_count, cards_count, repeat_for_every_card, additional_locations}. Null for other template types.',
            ],
            'other_option_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Names of further keys stored in the block\'s options meta (design, visibility, structure…), capped at 40 — read/write them is out of this surface\'s scope.',
            ],
            'content' => [
                'type' => 'object',
                'properties' => [
                    'bytes' => ['type' => 'integer'],
                    'hint' => ['type' => 'string'],
                ],
                'required' => ['bytes', 'hint'],
                'additionalProperties' => false,
            ],
        ],
        'required' => [
            'id',
            'title',
            'status',
            'template_type',
            'template_subtype',
            'enabled',
            'conditions',
            'position',
            'other_option_keys',
            'content',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_get_content_block',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Rendering requires ALL of: status publish, enabled=true, and the condition tree matching (per group: excludes veto within that group, includes admit; a nested group is an include row of its parent). Blocks display on the NEXT front-end request (hooks attach on the `wp` action).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
// @mago-expect lint:cyclomatic-complexity
// @mago-expect lint:halstead
function blocksy_get_content_block(array $input): array|WP_Error
{
    $gate = bly_cb_gate();
    if ($gate !== null) {
        return $gate;
    }

    $post = bly_cb_get_post($input['id'] ?? null);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $atts = bly_cb_read_atts($post->ID);
    $template_type = bly_cb_template_type($post->ID);
    $descriptor = bly_cb_stored_condition_descriptor($atts);

    $template_subtype = null;
    if ($template_type === 'archive' || $template_type === 'single') {
        $default_subtype = $template_type === 'single' ? 'canvas' : 'card';
        $stored_subtype =
            isset($atts['template_subtype']) && is_string($atts['template_subtype']) && $atts['template_subtype'] !== ''
                ? $atts['template_subtype']
                : $default_subtype;
        $template_subtype = $stored_subtype;
    }

    $position = null;
    if ($template_type === 'hook') {
        $stored = bly_cb_stored_position($atts);
        /** @var mixed $capped_additional */
        $capped_additional = bly_cap_value($stored['additional_locations']);
        $position = [
            'location' => $stored['location'],
            'priority' => $stored['priority'],
            'custom_location' => $stored['custom_location'],
            'paragraphs_count' => $stored['paragraphs_count'],
            'headings_count' => $stored['headings_count'],
            'cards_count' => $stored['cards_count'],
            'repeat_for_every_card' => $stored['repeat_for_every_card'],
            'additional_locations' => $capped_additional,
        ];
    }

    $handled = [
        'conditions',
        'is_hook_enabled',
        'template_subtype',
        'location',
        'priority',
        'custom_location',
        'paragraphs_count',
        'headings_count',
        'cards_count',
        'repeat_for_every_card',
        'additional_locations',
    ];
    $unhandled = [];
    foreach (array_keys($atts) as $key) {
        if (in_array((string) $key, $handled, strict: true)) {
            continue;
        }
        $unhandled[] = (string) $key;
    }
    $other_keys = [];
    foreach (array_slice($unhandled, offset: 0, length: BLY_CB_MAX_OTHER_KEYS) as $key) {
        $other_keys[] = bly_ext_clean_text($key, BLY_EXT_MAX_SETTINGS_KEY_BYTES);
    }
    if (count($unhandled) > BLY_CB_MAX_OTHER_KEYS) {
        $other_keys[] = sprintf('[+%d more keys omitted]', count($unhandled) - BLY_CB_MAX_OTHER_KEYS);
    }

    /** @var mixed $capped_rows */
    $capped_rows = bly_cap_value($descriptor['conditions']);

    return [
        'id' => $post->ID,
        'title' => bly_ext_clean_text($post->post_title, BLY_CB_MAX_TITLE_BYTES),
        'status' => $post->post_status,
        'template_type' => bly_ext_clean_text($template_type, BLY_EXT_MAX_NAME_BYTES),
        'template_subtype' => $template_subtype === null
            ? null
            : bly_ext_clean_text($template_subtype, BLY_EXT_MAX_NAME_BYTES),
        'enabled' => bly_cb_is_enabled($atts),
        'conditions' => [
            'relation' => $descriptor['relation'],
            'conditions' => is_array($capped_rows) ? $capped_rows : [$capped_rows],
        ],
        'position' => $position,
        'other_option_keys' => $other_keys,
        'content' => [
            'bytes' => strlen($post->post_content),
            'hint' => sprintf(
                'Content blocks are regular posts — read the content with novamira/gutenberg-get-content and write it with novamira/update-post (content/post_content, full block markup) using post id %d. novamira/gutenberg-write-content only accepts registered novamira/* dynamic-only blocks, not native Gutenberg markup.',
                $post->ID,
            ),
        ],
    ];
}
