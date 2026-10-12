<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: Apply a saved WPBakery template to a page.
 */

wp_register_ability('novamira/wpbakery-apply-template', [
    'label' => __('Apply WPBakery Template', domain: 'novamira-pro'),
    'description' => __(
        'Applies a saved WPBakery template to a post. mode=append adds the template after the existing content (default, non-destructive); mode=prepend adds it before (non-destructive); mode=replace DISCARDS every existing WPBakery node on the target and uses only the template (destructive, warn the user first). Non-WPBakery shortcodes elsewhere in post_content (e.g. [contact-form-7]) survive any mode and are listed in `preserved_foreign_shortcodes` when present. _idx values are reassigned after the apply; call wpbakery-get-content again before targeting an _idx on the target.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'ID of the post to apply the template to.',
            ],
            'template_id' => [
                'type' => 'string',
                'description' => 'The id of the template to apply (from wpbakery-list-templates).',
            ],
            'mode' => [
                'type' => 'string',
                'enum' => ['append', 'prepend', 'replace'],
                'description' => 'How to apply the template: append (default), prepend, or replace.',
                'default' => 'append',
            ],
        ],
        'required' => ['post_id', 'template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'post_id' => ['type' => 'integer'],
            'edit_url' => ['type' => 'string'],
            'tree' => [
                'type' => 'array',
                'description' => 'The post-write tree with fresh _idx values — same shape wpbakery-get-content returns. After mode=replace the tree is the template alone; after mode=append/prepend it includes both. Use this to act on the new indices without an extra get-content round-trip.',
            ],
            'preserved_foreign_shortcodes' => ['type' => 'array'],
            'error' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\WPBakery\wpbakery_apply_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call wpbakery-list-templates to find the template_id before applying. mode=append (default) and mode=prepend are non-destructive: they add content next to the existing tree. mode=replace DISCARDS every existing WPBakery node on the target post and uses only the template; confirm with the user before issuing it. Foreign (non-WPBakery) shortcodes survive any mode and are re-appended after the saved tree; the response lists them in `preserved_foreign_shortcodes`.',
            'readonly' => false,
            // Marked destructive because mode=replace is destructive; append/
            // prepend are safe. Tools that filter by this annotation will
            // correctly treat the ability as worst-case destructive.
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

function wpbakery_apply_template(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);
    $template_id = (string) ($input['template_id'] ?? '');
    $mode = (string) ($input['mode'] ?? 'append');

    $writable_error = wpb_post_writable_or_error($post_id);
    if ($writable_error !== null) {
        return $writable_error;
    }

    $templates = wpb_get_templates();
    if (!array_key_exists($template_id, $templates)) {
        return ['success' => false, 'error' => sprintf('Template "%s" not found.', $template_id)];
    }

    $tpl_shortcode = (string) ($templates[$template_id]['template'] ?? '');
    if ($tpl_shortcode === '') {
        return ['success' => false, 'error' => sprintf('Template "%s" has no content.', $template_id)];
    }

    $raw = wpb_get_post_content($post_id) ?? '';
    $merged_tree = wpb_merge_template_tree($raw, $tpl_shortcode, $mode);
    return wpb_finalize_write($post_id, $merged_tree, $raw);
}

/**
 * Merges the template tree into the existing page tree according to $mode.
 * Returns the merged tree ready for serialization. Foreign shortcodes are
 * preserved separately by the save path (wpb_save_tree_preserving_foreign).
 *
 * @return list<array<string, mixed>>
 */
function wpb_merge_template_tree(string $raw, string $tpl_shortcode, string $mode): array
{
    $tpl_tree = wpb_parse_content($tpl_shortcode);

    if ($mode === 'replace') {
        return $tpl_tree;
    }

    $page_tree = wpb_parse_content($raw);

    if ($mode === 'prepend') {
        return array_merge($tpl_tree, $page_tree);
    }

    return array_merge($page_tree, $tpl_tree);
}
