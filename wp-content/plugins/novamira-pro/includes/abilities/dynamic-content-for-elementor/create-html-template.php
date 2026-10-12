<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicContentForElementor;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/dynamic-content-for-elementor-create-html-template', [
    'label' => __('Create Dynamic Content for Elementor HTML Template', domain: 'novamira-pro'),
    'description' => __(
        'Creates an HTML Template for the Dynamic Content for Elementor PDF generator. A template renders one of two ways and you choose which here: pass code to hold its own markup, or pass elementor_template_id to render an existing Elementor template instead. Passing non-empty code together with elementor_template_id is refused, because only one of them would ever render; an empty code alongside a reference is accepted and simply means the template keeps no markup of its own. The markup is plain HTML and CSS rendered by mPDF, not an Elementor document, so Elementor abilities do not apply to it. WordPress shortcodes in the template\'s own code are not expanded. Legacy compatibility only: do not use Twig for new templates or mix the syntaxes; if Dynamic Shortcodes changes mixed template code, all Twig syntax in that code stays literal in the PDF with no error. Use Dynamic Shortcodes for dynamic values, and read that setup ability before writing them: they expand only when dynamic_shortcodes_expansion is true.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-content-for-elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Name shown in the plugin HTML Templates screen. The only post field this post type supports.',
            ],
            'code' => [
                'type' => 'string',
                'description' => 'HTML and CSS for mPDF to render. WordPress shortcodes in this stored code are not expanded, and unexpanded syntax is printed literally into the PDF without warning. Legacy compatibility only: do not use Twig for new templates or mix the syntaxes; if Dynamic Shortcodes changes mixed template code, all Twig syntax in that code stays literal in the PDF with no error. Use Dynamic Shortcodes for dynamic values; they expand at render time only when dynamic_shortcodes_expansion is true. Leave empty when rendering an Elementor template.',
            ],
            'elementor_template_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Render this Elementor template instead of stored markup. The plugin own screen offers only saved Elementor templates here, and it is the only kind that renders; any other existing post is accepted but comes out empty, so pass an elementor_library id.',
            ],
            'format' => [
                'type' => 'string',
                'description' => 'Page format, required because nothing renders a template that has none. The plugin\'s own template screen offers A4, A5, A6, Letter, Legal, Executive and Folio; any other name mPDF knows is stored as given and answered for at render time.',
            ],
            'orientation' => [
                'type' => 'string',
                'enum' => ['P', 'L', 'portrait', 'landscape'],
                'description' => 'Page orientation, stored as P or L. Required for the same reason as format.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft', 'pending', 'private'],
                'description' => 'Post status. Defaults to publish.',
            ],
        ],
        'required' => ['title', 'format', 'orientation'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'renders' => ['type' => 'string'],
            'edit_url' => [
                'type' => 'string',
                'description' => 'Where a person edits this template in the plugin own screen.',
            ],
        ],
        'required' => ['id', 'title', 'status', 'renders'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicContentForElementor\\dynamic_content_for_elementor_create_html_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pick one rendering mode. Non-empty code together with elementor_template_id is refused rather than silently resolved; an empty code alongside a reference is accepted.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function dynamic_content_for_elementor_create_html_template(array $input): array|WP_Error
{
    if (!post_type_exists(DCE_TEMPLATE_POST_TYPE)) {
        return dce_error(
            'post_type_missing',
            message: 'The HTML Templates post type is not registered in this request, so no template can be created.',
        );
    }

    $title = trim((string) ($input['title'] ?? ''));
    if ($title === '') {
        return dce_error('invalid_input', message: 'title is required and cannot be empty.');
    }

    $fields = dce_create_template_fields($input);
    if ($fields instanceof WP_Error) {
        return $fields;
    }

    $post_id = wp_insert_post([
        'post_type' => DCE_TEMPLATE_POST_TYPE,
        'post_title' => wp_slash($title),
        'post_status' => (string) ($input['status'] ?? 'publish'),
    ], wp_error: true);

    if (is_wp_error($post_id)) {
        return dce_wp_post_error($post_id, operation: 'create');
    }
    // The same lock edit takes. The id is brand new so nothing can be racing yet,
    // but a read-modify-write on this meta is protected in one place or in none.
    $written = dce_with_template_lock($post_id, static fn(): array|WP_Error => dce_write_template_meta(
        $post_id,
        $fields,
    ));
    if ($written instanceof WP_Error) {
        // The page setup was validated before the post existed, so this is only
        // reachable if the write itself refused. Leave nothing half-made behind.
        wp_delete_post($post_id, force_delete: true);

        return $written;
    }

    /** @var WP_Post|array<mixed>|null $post */
    $post = get_post($post_id);

    return [
        'id' => $post_id,
        'title' => $post instanceof WP_Post ? $post->post_title : $title,
        'status' => $post instanceof WP_Post ? $post->post_status : (string) ($input['status'] ?? 'publish'),
        'renders' => $fields[DCE_TEMPLATE_FIELDS['is_elementor_template']] === '' ? 'html' : 'elementor_template',
        'edit_url' => (string) get_edit_post_link($post_id, context: 'raw'),
    ];
}

/**
 * Turn the create input into the stored fields, or the first reason it cannot be.
 *
 * @param array<string, mixed> $input
 * @return array<string, string>|WP_Error
 */
function dce_create_template_fields(array $input): array|WP_Error
{
    $code = (string) ($input['code'] ?? '');

    if (array_key_exists('elementor_template_id', $input) && !dce_is_plain_id($input['elementor_template_id'])) {
        return dce_error('invalid_input', message: 'elementor_template_id must be a whole number.');
    }
    $elementor_id = (int) ($input['elementor_template_id'] ?? 0);

    if ($code !== '' && $elementor_id > 0) {
        return dce_ambiguous_mode_error();
    }

    $target_error = dce_elementor_target_error($elementor_id);
    if ($target_error instanceof WP_Error) {
        return $target_error;
    }

    $page_setup = dce_page_setup_fields((string) ($input['format'] ?? ''), (string) ($input['orientation'] ?? ''));
    if ($page_setup instanceof WP_Error) {
        return $page_setup;
    }

    return [DCE_TEMPLATE_FIELDS['code'] => $code] + dce_render_mode_fields($elementor_id) + $page_setup;
}
