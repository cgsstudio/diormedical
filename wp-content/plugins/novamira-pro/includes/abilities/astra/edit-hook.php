<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/astra-edit-hook', [
    'label' => __('Edit Astra Advanced Hook', domain: 'novamira-pro'),
    'description' => __(
        'Merge-patches an existing astra-advanced-hook post. Only keys you pass are updated; omitted keys keep their current value. Accepts WP-native aliases (`post_title` for `title`, `post_content` for `content`, `post_status` for `status`) — passing both a short name and its WP-native alias with different values returns astra_alias_conflict. Constraint: when the resulting layout is hooks (either already stored or newly set), action must not be empty. Content is capped at 32 KB. The php-execution surface is permanently blocked. Returns the full hook payload after the patch.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Post id of the hook to edit.'],
            'title' => ['type' => 'string', 'description' => 'Hook name. Accepts WP-native alias `post_title`.'],
            'post_title' => ['type' => 'string', 'description' => 'WP-native alias for `title`.'],
            'content' => [
                'type' => 'string',
                'description' => 'Gutenberg markup. Capped at 32 KB. Accepts WP-native alias `post_content`.',
            ],
            'post_content' => ['type' => 'string', 'description' => 'WP-native alias for `content`.'],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'description' => 'Post status. Accepts WP-native alias `post_status`.',
            ],
            'post_status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft'],
                'description' => 'WP-native alias for `status`.',
            ],
            'layout' => [
                'type' => 'string',
                'description' => 'Layout type. One of: header, footer, 404-page, hooks, content, template.',
            ],
            'action' => [
                'type' => 'string',
                'description' => 'WordPress action hook name. Required when the effective layout is hooks.',
            ],
            'priority' => ['type' => 'integer'],
            'enabled' => ['type' => 'boolean'],
            'location' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Targeting rules — string-keyed object. Allowed keys: rule, specific, specificPosts, posts, taxonomy, terms, taxonomies.',
            ],
            'exclusion' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Exclusion rules — string-keyed object with the same allowlist as location.',
            ],
            'users' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'User role rules — string-keyed object. Allowed keys: rule, specific, users.',
            ],
            'header' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Header configuration — string-keyed object. Allowed keys: disable_header, disable_primary_header, disable_main_menu, sticky, transparent.',
            ],
            'footer' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Footer configuration — string-keyed object. Allowed keys: disable_footer, disable_primary_footer.',
            ],
            'padding' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Padding/spacing configuration — string-keyed object. Allowed keys: above, below, left, right, top, bottom.',
            ],
            'template_type' => ['type' => 'string'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'content' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'layout' => ['type' => 'string'],
            'action' => ['type' => 'string'],
            'priority' => ['type' => 'integer'],
            'enabled' => ['type' => 'boolean'],
            'template_type' => ['type' => 'string'],
            'header' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Header configuration object. Empty object when not configured.',
            ],
            'footer' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Footer configuration object. Empty object when not configured.',
            ],
            'padding' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Padding/spacing configuration object. Empty object when not configured.',
            ],
            'location' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Targeting rules — where the hook renders. Sub-lists capped at 500 entries.',
            ],
            'exclusion' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Exclusion rules — where the hook is suppressed. Sub-lists capped at 500 entries.',
            ],
            'users' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'User role rules — which visitors see the hook. Sub-lists capped at 500 entries.',
            ],
        ],
        'required' => ['id', 'title', 'status', 'layout'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_edit_hook',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge-patch an existing hook — only pass the fields you want to change. If the resulting layout is hooks, action must be non-empty. The php-execution surface is permanently blocked.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Validate the layout enum value (if provided) and check the effective
 * layout=hooks + action constraint after merge. Returns WP_Error on failure.
 *
 * @param array<string, mixed> $input
 */
function eh_validate_layout_constraint(int $id, array $input): ?WP_Error
{
    if (isset($input['layout'])) {
        $layout_val = is_string($input['layout']) ? $input['layout'] : '';
        if (!in_array($layout_val, astra_hook_layout_allowlist(), strict: true)) {
            return astra_invalid_input_error(sprintf('`layout` must be one of: %s.', implode(
                ', ',
                astra_hook_layout_allowlist(),
            )));
        }
    }

    $effective_layout = isset($input['layout']) && is_string($input['layout'])
        ? $input['layout']
        : (string) get_post_meta(post_id: $id, key: 'ast-advanced-hook-layout', single: true);

    $effective_action = isset($input['action']) && is_string($input['action'])
        ? astra_strip_nul_bytes(trim($input['action']))
        : astra_strip_nul_bytes(trim((string) get_post_meta(
            post_id: $id,
            key: 'ast-advanced-hook-action',
            single: true,
        )));

    if ($effective_layout === 'hooks' && $effective_action === '') {
        return astra_invalid_input_error(
            'layout=hooks requires a non-empty action (WordPress action hook name). Either provide action or change layout to a value that does not require one.',
        );
    }

    return null;
}

/**
 * Build the wp_update_post array from the input fields. Returns only ['ID'=>$id]
 * when no post-level fields are present (caller checks count > 1).
 *
 * @param array<string, mixed> $input
 * @return array{'ID': int, 'post_title'?: string, 'post_status'?: string, 'post_content'?: string}
 */
function eh_build_post_update(int $id, array $input): array
{
    $update = ['ID' => $id];
    if (isset($input['title']) && is_string($input['title'])) {
        $update['post_title'] = astra_strip_nul_bytes(trim($input['title']));
    }
    if (isset($input['status']) && in_array($input['status'], ['publish', 'draft'], strict: true)) {
        $update['post_status'] = $input['status'];
    }
    if (isset($input['content']) && is_string($input['content'])) {
        $update['post_content'] = (string) astra_cap_bytes(astra_strip_nul_bytes($input['content']), limit: 32_768);
    }
    return $update;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function astra_edit_hook(array $input): array|WP_Error
{
    if (!astra_theme_available()) {
        return astra_theme_inactive_error();
    }
    if (!astra_addon_available()) {
        return astra_addon_required_error('advanced-hooks');
    }
    if (!astra_module_active('advanced-hooks')) {
        return astra_module_disabled_error('advanced-hooks');
    }
    if (!current_user_can('manage_options')) {
        return new WP_Error('forbidden', 'manage_options capability required.', ['status' => 403]);
    }

    // Normalise WP-native aliases before touching post fields.
    $normalised = astra_normalize_wp_aliases($input);
    if ($normalised instanceof WP_Error) {
        return $normalised;
    }
    $input = $normalised;

    // Deep-validate the shape of the rule bags (location / exclusion /
    // users / header / footer / padding) before any DB touch.
    $shape_error = astra_validate_meta_rules_shape($input);
    if ($shape_error instanceof WP_Error) {
        return $shape_error;
    }

    $id = astra_coerce_int($input['id'] ?? 0);
    $post = astra_hook_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $layout_error = eh_validate_layout_constraint($id, $input);
    if ($layout_error instanceof WP_Error) {
        return $layout_error;
    }

    $post_update = eh_build_post_update($id, $input);
    if (count($post_update) > 1) {
        $result = wp_update_post($post_update, wp_error: true);
        if ($result instanceof WP_Error) {
            return astra_error(
                'astra_invalid_input',
                message: 'wp_update_post failed: ' . $result->get_error_message(),
            );
        }
    }

    astra_write_hook_meta($id, $input);

    clean_post_cache($id);

    /** @var WP_Post|null $fresh */
    $fresh = get_post($id);
    if (!$fresh instanceof WP_Post) {
        return astra_error('astra_not_found', message: sprintf('Hook %d could not be reloaded after update.', $id));
    }
    return astra_hook_to_array($fresh);
}
