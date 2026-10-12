<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-create-element', [
    'label' => __('Create GeneratePress Element', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new `gp_elements` post. Required inputs depend on `type`: every element needs `title` + `type`; when `type=block`, `block_type` is required; when `type=hook` (or `type=block` with `block_type=hook`), `hook` is required (or `hook="custom"` plus `custom_hook` for a non-registered action). `content` is treated as a raw string (HTML / shortcode / Gutenberg block-serialised markup); the integration does NOT parse it. The `_generate_hook_execute_php` flag is permanently blocked — agents do not write arbitrary PHP via this ability. New elements default to `status=publish`. Use generatepress-list-hooks before this call so the hook value is valid.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => ['type' => 'string', 'description' => 'Visible title in the admin list. Required.'],
            'type' => [
                'type' => 'string',
                'enum' => ['hook', 'header', 'layout', 'block'],
                'description' => 'Element kind. Required. `hook` = inject content at a theme action; `block` = templated site part (header/footer/sidebar/hero…); `header` and `layout` are legacy specialised types.',
            ],
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
                'description' => 'Required when `type=block`. NOTE: `page-hero` is a MODIFIER (it disables featured image / title on the target page) — its `content` is NOT printed by GP. To render a hero banner, use a Hook Element on `generate_after_header` instead.',
            ],
            'hook' => [
                'type' => 'string',
                'description' => 'Action hook name to attach to (e.g. generate_after_header). Required when `type=hook` or when `type=block` with `block_type=hook`. Use the literal "custom" + the `custom_hook` field for actions not in generatepress-list-hooks.',
            ],
            'custom_hook' => [
                'type' => 'string',
                'description' => 'Custom action name used when `hook="custom"`. Ignored otherwise.',
            ],
            'content' => [
                'type' => 'string',
                'description' => 'Raw element body. HTML, shortcodes, and Gutenberg block-serialised markup are all accepted as strings — the integration writes the meta verbatim.',
            ],
            'priority' => [
                'type' => 'integer',
                'description' => 'Hook execution priority. Defaults to 10. Lower runs earlier.',
                'default' => 10,
            ],
            'execute_shortcodes' => [
                'type' => 'boolean',
                'description' => 'When true, do_shortcode runs over `content`. Default false.',
                'default' => false,
            ],
            'disable_site_header' => [
                'type' => 'boolean',
                'description' => 'When true on a `type=hook` element bound to generate_header, replaces the theme\'s site header markup with this element\'s content. Only meaningful for that hook.',
                'default' => false,
            ],
            'disable_site_footer' => [
                'type' => 'boolean',
                'description' => 'Same as disable_site_header but for the generate_footer hook.',
                'default' => false,
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'description' => 'Post status of the new element. Defaults to publish.',
                'default' => 'publish',
            ],
        ],
        'required' => ['title', 'type'],
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_create_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Recommended workflow: generatepress-list-hooks to pick a valid `hook` value → generatepress-create-element with the right type/block_type/hook trio → optionally generatepress-set-element-conditions to scope visibility. The integration does NOT accept the `_generate_hook_execute_php` flag — there is no way to ask this ability to enable PHP execution on the element.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function generatepress_create_element(array $input): array
{
    $pre_err = gp_validate_create_inputs($input);
    if ($pre_err !== null) {
        return ['success' => false, 'error' => $pre_err];
    }
    $raw_title = is_string($input['title'] ?? null) ? $input['title'] : '';
    $title = gp_strip_nul_bytes(trim($raw_title));

    $status = isset($input['status']) && in_array($input['status'], ['publish', 'draft'], strict: true)
        ? $input['status']
        : 'publish';

    // Block Elements render from `post_content` (class-block.php:229 reads
    // $block_element->post_content). Hook Elements render from the
    // `_generate_element_content` postmeta. Route the `content` field to
    // the right storage based on the element type — gp_apply_element_meta
    // also honours this and skips the postmeta write for block types.
    $is_block = isset($input['type']) && $input['type'] === 'block';
    $initial_content = $is_block && isset($input['content']) && is_string($input['content']) ? $input['content'] : '';

    $post_id = wp_insert_post([
        'post_type' => 'gp_elements',
        'post_title' => $title,
        'post_status' => $status,
        'post_content' => $initial_content,
    ], wp_error: true);

    if ($post_id instanceof \WP_Error) {
        return ['success' => false, 'error' => 'wp_insert_post failed: ' . $post_id->get_error_message()];
    }

    // The schema documents `priority` default=10 (matching WP's add_action
    // default). Apply the default on create so the stored postmeta reflects
    // the documented behavior — otherwise an omitted priority lands as 0
    // because the integer meta is never written, and `(int) ''` reads back
    // as 0, which contradicts the schema.
    $input_with_defaults = $input;
    if (!array_key_exists('priority', $input_with_defaults)) {
        $input_with_defaults['priority'] = 10;
    }
    gp_apply_element_meta($post_id, $input_with_defaults);

    $created = gp_load_element($post_id);
    if ($created === null) {
        return ['success' => false, 'error' => 'Post created but could not be re-loaded.'];
    }
    return ['success' => true, 'element' => gp_element_to_array($created)];
}

/**
 * Up-front validation for create-element inputs: required title, priority
 * sign, type/block_type/hook shape. Returns an error message string on
 * rejection, null when the input is clean. Lives outside the main
 * callback to keep cyclomatic complexity bounded.
 *
 * @param array<string, mixed> $input
 */
function gp_validate_create_inputs(array $input): ?string
{
    $title = isset($input['title']) && is_string($input['title']) ? gp_strip_nul_bytes(trim($input['title'])) : '';
    if ($title === '') {
        return '`title` is required and must be a non-empty string.';
    }
    if (isset($input['priority']) && is_int($input['priority']) && $input['priority'] < 0) {
        return '`priority` must be 0 or a positive integer (GP sanitizes with absint).';
    }
    return gp_validate_element_shape($input);
}
