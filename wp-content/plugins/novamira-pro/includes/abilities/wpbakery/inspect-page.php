<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read-only aggregate snapshot of one WPBakery-edited post.
 *
 * Combines the per-post views (`wpbakery-get-content`,
 * `wpbakery-get-settings`) with the environment view
 * (`wpbakery-check-setup` — relevant fields only) AND a
 * `foreign_shortcodes` list — the non-WPBakery shortcodes present in
 * the raw `post_content` that `get-content` does not surface. Lets a
 * planning-only agent inspect everything that will be touched by a
 * subsequent write, in a single call, without needing to issue any
 * mutating call to learn about foreign shortcodes (which today are
 * only visible via the `preserved_foreign_shortcodes` field on a
 * write response).
 */

wp_register_ability('novamira/wpbakery-inspect-page', [
    'label' => __('Inspect WPBakery Page', domain: 'novamira-pro'),
    'description' => __(
        'Read-only aggregate snapshot of a WPBakery-edited post: setup snapshot (version, min_satisfied, grid_builder_active, supported_post_types), per-post settings (wpbakery_edited flag, custom_css, shortcodes_custom_css), the WPBakery element tree, and a list of foreign shortcodes ([gallery], [wpforms], [contact-form-7], …) that are in the raw post_content but outside the WPBakery tree. Use this BEFORE planning a mutation so you know what is on the page (including content that get-content alone does not surface).',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to inspect.',
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'is_blank' => [
                'type' => 'boolean',
                'description' => 'True when the page has no WPBakery tree, no foreign shortcodes, no custom CSS, and the wpbakery_edited flag is false. Single signal an agent can branch on when deciding whether to scaffold from scratch.',
            ],
            'page_state' => [
                'type' => 'string',
                'enum' => [
                    'blank',
                    'has_wpbakery_tree_only',
                    'has_foreign_shortcodes_only',
                    'mixed_wpbakery_and_foreign',
                    'styled_but_empty',
                ],
                'description' => 'Coarse classification of what is on the page, useful when is_blank is false and the agent needs to explain WHY in one sentence. "styled_but_empty" means the page has custom_css or the wpbakery_edited flag but no actual tree or shortcodes.',
            ],
            'setup' => [
                'type' => 'object',
                'properties' => [
                    'version' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                    'grid_builder_active' => ['type' => 'boolean'],
                    'supported_post_types' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                    ],
                ],
                'required' => ['version', 'min_satisfied', 'grid_builder_active', 'supported_post_types'],
            ],
            'settings' => [
                'type' => 'object',
                'properties' => [
                    'wpbakery_edited' => ['type' => 'boolean'],
                    'custom_css' => ['type' => 'string'],
                    'shortcodes_custom_css' => ['type' => 'string'],
                ],
                'required' => ['wpbakery_edited', 'custom_css', 'shortcodes_custom_css'],
            ],
            'tree' => [
                'type' => 'array',
                'description' => 'The WPBakery element tree (same shape returned by wpbakery-get-content `tree`, accepted by wpbakery-set-content `tree`).',
            ],
            'foreign_shortcodes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'tag' => ['type' => 'string'],
                        'raw' => ['type' => 'string'],
                    ],
                    'required' => ['tag', 'raw'],
                ],
                'description' => 'Non-WPBakery shortcodes detected in post_content. These are NOT in the WPBakery tree; they survive writes via the preserved_foreign_shortcodes mechanism but their position is not retained.',
            ],
            'diagnostics' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'code' => [
                            'type' => 'string',
                            'description' => 'Short machine-readable code (e.g. "tree_without_edited_flag").',
                        ],
                        'severity' => [
                            'type' => 'string',
                            'enum' => ['action_required', 'warning', 'info'],
                            'description' => 'Lets an agent loop deterministically: `action_required` = safe to auto-apply (always paired with a `fix_call`); `warning` = surface to the user before mutating (may or may not have a fix_call — when present, requires confirmation); `info` = informational only.',
                        ],
                        'message' => [
                            'type' => 'string',
                            'description' => 'Engineer-voiced explanation of the symptom (uses internal terminology like "WPBakery tree", "post meta", "structural write"). Use for agent reasoning.',
                        ],
                        'user_message' => [
                            'type' => 'string',
                            'description' => 'Customer-voiced restatement of the symptom — safe to quote verbatim back to the user. Plain language, no internal jargon. Pair with `suggested_fix` to compose a complete reply.',
                        ],
                        'suggested_fix' => [
                            'type' => 'string',
                            'description' => 'One-line natural-language hint pointing at the ability call that would resolve it.',
                        ],
                        'fix_call' => [
                            'type' => 'object',
                            'properties' => [
                                'ability' => [
                                    'type' => 'string',
                                    'description' => 'Slug of the Novamira ability to invoke.',
                                ],
                                'args' => [
                                    'type' => 'object',
                                    'description' => 'Full input payload to pass to that ability.',
                                ],
                            ],
                            'required' => ['ability', 'args'],
                            'description' => 'Structured payload an agent can execute mechanically (wp_get_ability(ability)->execute(args)). Present ONLY when the fix is safe to auto-apply (unambiguous, idempotent, no data loss). Diagnostics that require user judgement omit this field.',
                        ],
                    ],
                    'required' => ['code', 'severity', 'message', 'user_message', 'suggested_fix'],
                ],
                'description' => 'Auto-detected anomalies the agent should surface to the user before mutating. Empty array when nothing flagged. Iterate by severity to drive deterministic auto-fix loops (`action_required` entries always carry a fix_call).',
            ],
        ],
        'required' => [
            'post_id',
            'is_blank',
            'page_state',
            'setup',
            'settings',
            'tree',
            'foreign_shortcodes',
            'diagnostics',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPBakery\\wpbakery_inspect_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this as the single read call when planning a change to an existing WPBakery page. It replaces calling check-setup + get-settings + get-content separately and additionally lists foreign shortcodes that get-content alone would hide. Returns wpbakery_not_found when the post does not exist.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{post_id: int} $input
 * @return array{
 *     post_id: int,
 *     is_blank: bool,
 *     page_state: string,
 *     setup: array{version: string, min_satisfied: bool, grid_builder_active: bool, supported_post_types: list<string>},
 *     settings: array{wpbakery_edited: bool, custom_css: string, shortcodes_custom_css: string},
 *     tree: list<array<string, mixed>>,
 *     foreign_shortcodes: list<array{tag: string, raw: string}>,
 *     diagnostics: list<array{
 *         code: string,
 *         severity: string,
 *         message: string,
 *         user_message: string,
 *         suggested_fix: string,
 *         fix_call?: array{ability: string, args: array<string, mixed>},
 *     }>,
 * }|WP_Error
 */
function wpbakery_inspect_page(array $input): array|WP_Error
{
    $post_id = $input['post_id'];
    if ($post_id <= 0 || get_post($post_id) === null) {
        return new WP_Error('wpbakery_not_found', sprintf(
            /* translators: %d: post ID. */
            __('Post %d not found.', domain: 'novamira-pro'),
            $post_id,
        ));
    }

    $raw = wpb_get_post_content($post_id) ?? '';
    $tree = wpb_parse_content($raw);
    $counter = 0;
    $tree = wpb_index_tree($tree, $counter);

    $foreign_raw = wpb_extract_foreign_shortcodes($raw);
    $foreign = array_map(static fn(array $f): array => ['tag' => $f['tag'], 'raw' => $f['match']], $foreign_raw);

    /** @var mixed $custom_css_raw */
    $custom_css_raw = get_post_meta($post_id, key: WPB_META_CUSTOM_CSS, single: true);
    /** @var mixed $shortcodes_css_raw */
    $shortcodes_css_raw = get_post_meta($post_id, key: WPB_META_SHORTCODES_CSS, single: true);
    /** @var mixed $edited_raw */
    $edited_raw = get_post_meta($post_id, key: WPB_META_EDITED, single: true);

    $wpbakery_edited = is_string($edited_raw) && $edited_raw === 'true';
    $custom_css = is_string($custom_css_raw) ? $custom_css_raw : '';
    $shortcodes_custom_css = is_string($shortcodes_css_raw) ? $shortcodes_css_raw : '';

    $is_blank =
        $tree === [] && $foreign === [] && $custom_css === '' && $shortcodes_custom_css === '' && !$wpbakery_edited;

    $page_state = wpb_classify_page_state([
        'tree_present' => $tree !== [],
        'foreign_present' => $foreign !== [],
        'css_or_flag_present' => $custom_css !== '' || $shortcodes_custom_css !== '' || $wpbakery_edited,
    ]);

    return [
        'post_id' => $post_id,
        'is_blank' => $is_blank,
        'page_state' => $page_state,
        'setup' => [
            'version' => wpb_version(),
            'min_satisfied' =>
                wpb_version() !== ''
                    && version_compare(wpb_version(), NOVAMIRA_PRO_WPBAKERY_MIN_VERSION, operator: '>='),
            'grid_builder_active' => array_key_exists('vc_basic_grid', \WPBMap::getAllShortCodes()),
            'supported_post_types' => wpb_supported_post_types(),
        ],
        'settings' => [
            'wpbakery_edited' => $wpbakery_edited,
            'custom_css' => $custom_css,
            'shortcodes_custom_css' => $shortcodes_custom_css,
        ],
        'tree' => $tree,
        'foreign_shortcodes' => $foreign,
        'diagnostics' => wpb_inspect_diagnostics([
            'post_id' => $post_id,
            'tree' => $tree,
            'foreign' => $foreign,
            'wpbakery_edited' => $wpbakery_edited,
        ]),
    ];
}

/**
 * Classifies the high-level state of a WPBakery page based on three
 * boolean signals. Used to populate the `page_state` enum on
 * wpbakery-inspect-page so the agent can branch on a single field
 * instead of cross-reading content/foreign/settings.
 *
 * @param array{tree_present: bool, foreign_present: bool, css_or_flag_present: bool} $signals
 */
function wpb_classify_page_state(array $signals): string
{
    if ($signals['tree_present'] && $signals['foreign_present']) {
        return 'mixed_wpbakery_and_foreign';
    }
    if ($signals['tree_present']) {
        return 'has_wpbakery_tree_only';
    }
    if ($signals['foreign_present']) {
        return 'has_foreign_shortcodes_only';
    }
    if ($signals['css_or_flag_present']) {
        return 'styled_but_empty';
    }
    return 'blank';
}

/**
 * Builds the diagnostics list for wpbakery-inspect-page. Encapsulated so
 * future symptoms can be added here without inflating the main callback
 * past the mago Halstead thresholds.
 *
 * Diagnostics that are safe to auto-apply (unambiguous + idempotent +
 * no data loss) include a `fix_call` field with the ability slug and
 * the full args payload the agent can execute mechanically. Diagnostics
 * that require user judgement (e.g. anything that re-positions content)
 * omit `fix_call`.
 *
 * @param array{
 *     post_id: int,
 *     tree: list<array<string, mixed>>,
 *     foreign: list<array{tag: string, raw: string}>,
 *     wpbakery_edited: bool,
 * } $state
 * @return list<array{
 *     code: string,
 *     severity: string,
 *     message: string,
 *     user_message: string,
 *     suggested_fix: string,
 *     fix_call?: array{ability: string, args: array<string, mixed>},
 * }>
 */
function wpb_inspect_diagnostics(array $state): array
{
    $diagnostics = [];

    if ($state['tree'] !== [] && !$state['wpbakery_edited']) {
        $diagnostics[] = [
            'code' => 'tree_without_edited_flag',
            'severity' => 'action_required',
            'message' => 'The page has a valid WPBakery shortcode tree, but the wpbakery_edited flag is false — the "Edit with WPBakery" admin link will not appear and the classic editor may strip the shortcodes on next save. Common after backup imports.',
            'user_message' => 'This page is built with WPBakery, but it is not marked as such — so the "Edit with WPBakery" link is missing and other editors might break the layout. I can mark it correctly without changing the content.',
            'suggested_fix' => 'Call wpbakery-set-settings with wpbakery_edited=true on this post.',
            'fix_call' => [
                'ability' => 'novamira/wpbakery-set-settings',
                'args' => [
                    'post_id' => $state['post_id'],
                    'wpbakery_edited' => true,
                ],
            ],
        ];
    }

    $self_nested = wpb_self_nested_nodes($state['tree']);
    if ($self_nested !== []) {
        $paths = array_values(array_unique(array_map(static fn(array $f): string => implode(
            ' > ',
            $f['path'],
        ), $self_nested)));
        // No fix_call: untangling the nesting means deciding where the
        // inner container should live instead, and only the author knows.
        $diagnostics[] = [
            'code' => 'self_nested_tag',
            'severity' => 'action_required',
            'message' => sprintf(
                'The tree nests a tag inside itself (%s). WordPress cannot parse a shortcode nested inside itself: it matches the first closing tag, so the outer one is printed as literal text and the elements below it never render. The write paths refuse this shape, so it reached the post another way.',
                implode('; ', $paths),
            ),
            'user_message' => 'Part of this page cannot display: a container is nested inside another of the same kind, which WordPress cannot read, so visitors see raw shortcode text instead of the content. The inner one needs to move somewhere else.',
            'suggested_fix' => 'Move the inner container out — into its own row for a section, or into the row/column _inner variants where one exists — then write the tree back with wpbakery-set-content.',
        ];
    }

    if ($state['foreign'] !== []) {
        $tags = implode(
            ', ',
            array_unique(array_map(static fn(array $f): string => '[' . $f['tag'] . ']', $state['foreign'])),
        );
        // Intentionally no `fix_call` — the right fix here requires user
        // judgement (warn the user, or re-anchor the foreign shortcodes
        // manually after the write). Auto-applying would silently move
        // their content. severity=warning signals to the agent that this
        // must surface to the user before mutating, not auto-applied.
        $diagnostics[] = [
            'code' => 'mixed_with_foreign_shortcodes',
            'severity' => 'warning',
            'message' => sprintf(
                'The page mixes WPBakery shortcodes with %d non-WPBakery shortcode(s) (%s). On the next mutating write these will be preserved but re-appended after the WPBakery tree, so their original position will not be retained.',
                count($state['foreign']),
                $tags,
            ),
            'user_message' => sprintf(
                'This page also includes %d shortcode(s) from other plugins (%s). They will not be lost when I make changes, but they will end up at the bottom of the page instead of staying in their current spot. Let me know if you want me to put them back in a specific place.',
                count($state['foreign']),
                $tags,
            ),
            'suggested_fix' => 'Warn the user before the mutation, or re-anchor the foreign shortcodes manually after the write.',
        ];
    }

    return $diagnostics;
}
