<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicContentForElementor;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/dynamic-content-for-elementor-get-html-template', [
    'label' => __('Get Dynamic Content for Elementor HTML Template', domain: 'novamira-pro'),
    'description' => __(
        'Reads one HTML Template in full: its title, status, page format and orientation, how it renders, and its stored markup. Read this before editing, because the two rendering modes look identical from outside and only one of them holds markup here. When renders is "elementor_template" the visible content lives in the Elementor template named by elementor_template_id and is edited with the novamira/elementor-* abilities, not with this surface. The markup comes back whole unless you ask for a cap with max_code_bytes, so a read-modify-write cycle can never quietly shorten a template; when you do cap it, code_truncated says so and code_bytes still reports the real size.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-content-for-elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Post id of the HTML Template.',
            ],
            'max_code_bytes' => [
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 2_000_000,
                'description' => 'Optional byte cap on the returned markup. Omit it to get the whole template, which is what you want before editing. Zero returns no markup at all, only its size.',
            ],
        ],
        'required' => ['template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'renders' => ['type' => 'string'],
            'is_elementor_template' => ['type' => 'boolean'],
            'elementor_template_id' => [
                'type' => 'integer',
                'description' => 'The Elementor template this one points at, whether or not it is the mode in use, and zero when there is none. Switching a template back to markup through novamira/dynamic-content-for-elementor-edit-html-template clears this reference; the plugin own screen hides the picker rather than clearing it, so a template switched back there usually keeps the id it had. Read renders to know which mode is live.',
            ],
            'elementor_template_exists' => [
                'type' => ['boolean', 'null'],
                'description' => 'Whether the post this template points at exists and is neither trashed nor an auto-draft. That is all it checks: the post is not tested for being an Elementor template, nor for holding anything to render, so true is not a promise that the PDF will have content in it. Null when this template does not render an Elementor template. False is a common cause of a PDF that comes out empty.',
            ],
            'format' => ['type' => 'string'],
            'orientation' => ['type' => 'string'],
            'code' => ['type' => 'string'],
            'code_bytes' => ['type' => 'integer'],
            'code_truncated' => ['type' => 'boolean'],
            'preview_post' => ['type' => 'string'],
            'preview_form_data' => ['type' => 'string'],
            'configured' => [
                'type' => 'boolean',
                'description' => 'False when there is no usable stored configuration, so every field below is at its empty default rather than a stored choice. That is normally because the plugin has never saved this template, and occasionally because what is stored is not the array it writes.',
            ],
        ],
        'required' => ['id', 'title', 'status', 'renders', 'code_bytes', 'configured'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicContentForElementor\\dynamic_content_for_elementor_get_html_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Check renders before offering to edit: an "elementor_template" holds no markup here. If you passed max_code_bytes and got code_truncated, do not write that markup back.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function dynamic_content_for_elementor_get_html_template(array $input): array|WP_Error
{
    $post = dce_template_or_error((int) ($input['template_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    // No default cap: a caller that reads in order to edit must get the whole
    // markup back, or the next write silently shortens the template. Null carries
    // "uncapped" so that an explicit zero can still mean "the size only".
    $max_bytes = array_key_exists('max_code_bytes', $input) ? (int) $input['max_code_bytes'] : null;

    /** @var mixed $stored */
    $stored = get_post_meta($post->ID, DCE_TEMPLATE_META_KEY, single: true);
    $meta = dce_read_template_meta($post->ID);
    $presented = dce_present_template_meta($meta);

    $code = dce_truncate_field($meta[DCE_TEMPLATE_FIELDS['code']], $max_bytes);

    $elementor_id = $presented['elementor_template_id'];
    $elementor_exists = $presented['is_elementor_template'] ? dce_elementor_target_is_renderable($elementor_id) : null;

    return [
        'id' => $post->ID,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'renders' => $presented['renders'],
        'is_elementor_template' => $presented['is_elementor_template'],
        'elementor_template_id' => $elementor_id,
        'elementor_template_exists' => $elementor_exists,
        'format' => $presented['format'],
        'orientation' => $presented['orientation'],
        'code' => $code['value'],
        'code_bytes' => $code['total_bytes'],
        'code_truncated' => $code['truncated'],
        'preview_post' => $meta[DCE_TEMPLATE_FIELDS['preview_post']],
        'preview_form_data' => $meta[DCE_TEMPLATE_FIELDS['preview_form_data']],
        'configured' => is_array($stored),
    ];
}
