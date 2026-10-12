<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Formidable's on-submit action slug. Identifies an action of type
 * `on_submit` in the wp_posts.post_excerpt column. Verified against
 * Formidable 6.32 (FrmOnSubmitAction::$slug at FrmOnSubmitAction.php:19).
 */
const FRM_ON_SUBMIT_ACTION_TYPE = 'on_submit';

/**
 * Whitelist of keys allowed in the on-submit action's settings blob.
 * Mirrors FrmOnSubmitAction::get_defaults() with the `event` key added
 * so the caller can target create / update separately. Anything outside
 * this whitelist is silently dropped at the boundary.
 */
const FRM_ON_SUBMIT_KEYS = [
    'success_action',
    'success_msg',
    'show_form',
    'success_url',
    'success_page_id',
    'open_in_new_tab',
    'redirect_delay',
    'redirect_delay_time',
    'redirect_delay_msg',
    'event',
];

wp_register_ability('novamira/formidable-set-on-submit-action', [
    'label' => __('Set Formidable On-Submit Action', domain: 'novamira-pro'),
    'description' => __(
        "PUT semantics for the ONE on-submit action a Formidable form may have per event (`create` or `update`). Three types — `message` (show a success message inline), `redirect` (send the browser to a URL), `page` (render a WordPress page id). Existing on-submit actions for the same event are updated in place; missing ones are created. The other keys are type-aware: `message` reads `message` + `show_form_after_message`; `redirect` reads `redirect_url` (CRLF + scheme-whitelist validated via frm_validate_url_safe — javascript: / data: / vbscript: rejected) + `redirect_open_in_new_tab` + `redirect_delay_seconds` + `redirect_delay_message`; `page` reads `page_id` (must exist as a published page). The `event` input defaults to `['create']`; pass `['create','update']` to bind the same configuration to both submission events (front-end edits require Pro).",
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
            'type' => [
                'type' => 'string',
                'enum' => ['message', 'redirect', 'page'],
                'description' => 'Outcome type. `message` shows inline copy, `redirect` navigates the browser, `page` renders a published page.',
            ],
            'message' => ['type' => 'string', 'description' => 'Success message HTML (when type=message).'],
            'show_form_after_message' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'When type=message, also re-render the empty form below the success message.',
            ],
            'redirect_url' => [
                'type' => 'string',
                'description' => 'Target URL when type=redirect. Allowed schemes: http, https, mailto, tel.',
            ],
            'redirect_open_in_new_tab' => ['type' => 'boolean', 'default' => false],
            'redirect_delay_seconds' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 60, 'default' => 8],
            'redirect_delay_message' => [
                'type' => 'string',
                'description' => 'Holding-pattern copy shown while the redirect_delay_seconds counter ticks.',
            ],
            'page_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Published page id when type=page. Rejected if the post does not exist OR is not type=`page` OR is not `publish` status.',
            ],
            'event' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['create', 'update']],
                'description' => 'Submission events that trigger this on-submit. `update` only fires on Pro front-end edits.',
                'default' => ['create'],
            ],
            'enabled' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Flip the underlying post to draft (skips the action on submit) when false.',
            ],
        ],
        'required' => ['form_id', 'type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'action_id' => ['type' => 'integer'],
            'created' => [
                'type' => 'boolean',
                'description' => 'True when no on-submit action existed for the requested event and one was created; false when an existing one was updated.',
            ],
            'enabled' => ['type' => 'boolean'],
            'type' => ['type' => 'string', 'enum' => ['message', 'redirect', 'page']],
            'settings' => ['type' => 'object'],
        ],
        'required' => ['action_id', 'created', 'enabled', 'type', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_set_on_submit_action',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => "Sets the single on-submit action for a Formidable form. PUT semantics: existing on-submit for the same event is updated in place; missing one is created. Pick the type then supply only that type's fields — others are ignored. `redirect_url` rejects javascript: / data: / vbscript: schemes. `page_id` MUST point to an existing `publish`-status `page` post. The response carries `created` so the agent can distinguish first-write from update.",
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *     form_id?: int|string,
 *     type?: string,
 *     message?: string,
 *     show_form_after_message?: bool,
 *     redirect_url?: string,
 *     redirect_open_in_new_tab?: bool,
 *     redirect_delay_seconds?: int,
 *     redirect_delay_message?: string,
 *     page_id?: int,
 *     event?: list<string>,
 *     enabled?: bool,
 * } $input
 * @return array{action_id: int, created: bool, enabled: bool, type: string, settings: array<string, mixed>}|WP_Error
 */
function formidable_set_on_submit_action(array $input): array|WP_Error
{
    if (!frm_runtime_available()) {
        return frm_not_active();
    }
    $cap = frm_capability_or_error('frm_edit_forms');
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $form = frm_form_or_error($input['form_id'] ?? 0);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $type = $input['type'] ?? '';
    if (!in_array($type, ['message', 'redirect', 'page'], strict: true)) {
        return frm_invalid_input('`type` must be one of: message, redirect, page.');
    }

    $settings = frm_build_on_submit_settings($type, $input);
    if ($settings instanceof WP_Error) {
        return $settings;
    }

    $event = frm_on_submit_event($input);
    $settings['event'] = $event;

    return frm_persist_on_submit_action((int) $form->id, $type, $settings, $input, $event);
}

/**
 * Resolve the caller's `event` list to the documented default-on-empty
 * shape. Always returns a non-empty list with values from {create, update}.
 *
 * @param array<string, mixed> $input
 * @return list<string>
 */
function frm_on_submit_event(array $input): array
{
    if (!isset($input['event']) || !is_array($input['event'])) {
        return ['create'];
    }
    /** @var list<string> $clean */
    $clean = array_values(array_filter(
        $input['event'],
        static fn(mixed $v): bool => is_string($v) && in_array($v, ['create', 'update'], strict: true),
    ));
    if ($clean === []) {
        return ['create'];
    }
    return array_values(array_unique($clean));
}

/**
 * Build the post_content settings array for the chosen type. The whitelist
 * approach mirrors the FluentForms audit-round-3 #F arbitrary-key-injection
 * guard: only the type-relevant keys are written, regardless of what the
 * caller supplies.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function frm_build_on_submit_settings(string $type, array $input): array|WP_Error
{
    $settings = frm_on_submit_default_settings($type);

    if ($type === 'message') {
        return frm_build_on_submit_message_settings($settings, $input);
    }
    if ($type === 'redirect') {
        return frm_build_on_submit_redirect_settings($settings, $input);
    }
    return frm_build_on_submit_page_settings($settings, $input);
}

/**
 * Empty-defaults map for the on-submit settings blob. Mirrors
 * FrmOnSubmitAction::get_defaults() at FrmOnSubmitAction.php:56.
 *
 * @return array<string, mixed>
 */
function frm_on_submit_default_settings(string $type): array
{
    return [
        'success_action' => $type,
        'success_msg' => '',
        'show_form' => '',
        'success_url' => '',
        'success_page_id' => '',
        'open_in_new_tab' => '',
        'redirect_delay' => '',
        'redirect_delay_time' => 8,
        'redirect_delay_msg' => '',
    ];
}

/**
 * Apply caller input on top of the message-type defaults.
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function frm_build_on_submit_message_settings(array $settings, array $input): array
{
    if (isset($input['message'])) {
        $settings['success_msg'] = (string) $input['message'];
    }
    if (isset($input['show_form_after_message']) && (bool) (int) $input['show_form_after_message']) {
        $settings['show_form'] = 1;
    }
    return $settings;
}

/**
 * Apply caller input on top of the redirect-type defaults. URL scheme
 * validated via frm_validate_url_safe (javascript: / data: / vbscript:
 * rejected).
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function frm_build_on_submit_redirect_settings(array $settings, array $input): array|WP_Error
{
    $url = isset($input['redirect_url']) ? (string) $input['redirect_url'] : '';
    if ($url === '') {
        return frm_invalid_input('`redirect_url` is required when type=redirect.');
    }
    $safe_url = frm_validate_url_safe($url);
    if ($safe_url instanceof WP_Error) {
        return $safe_url;
    }
    $settings['success_url'] = $safe_url;
    if (isset($input['redirect_open_in_new_tab']) && (bool) (int) $input['redirect_open_in_new_tab']) {
        $settings['open_in_new_tab'] = 1;
    }
    if (isset($input['redirect_delay_seconds'])) {
        $settings['redirect_delay'] = 1;
        $settings['redirect_delay_time'] = max(0, min(60, (int) $input['redirect_delay_seconds']));
    }
    if (isset($input['redirect_delay_message'])) {
        $settings['redirect_delay_msg'] = (string) $input['redirect_delay_message'];
    }
    return $settings;
}

/**
 * Apply caller input on top of the page-type defaults. The page_id is
 * required AND must resolve to an existing, published `page` post.
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function frm_build_on_submit_page_settings(array $settings, array $input): array|WP_Error
{
    $page_id = isset($input['page_id']) ? frm_to_int($input['page_id']) : 0;
    if ($page_id <= 0) {
        return frm_invalid_input('`page_id` is required and must be a positive integer when type=page.');
    }
    $check = frm_validate_on_submit_page_id($page_id);
    if ($check instanceof WP_Error) {
        return $check;
    }
    $settings['success_page_id'] = (string) $page_id;
    return $settings;
}

/**
 * Verify a page_id resolves to an existing, published `page` post. Returns
 * null on success, a typed WP_Error otherwise.
 */
function frm_validate_on_submit_page_id(int $page_id): ?WP_Error
{
    // @mago-expect analyzer:mixed-assignment
    $post = get_post($page_id);
    if (!$post instanceof WP_Post) {
        return new WP_Error('formidable_invalid_input', "page_id {$page_id} does not exist.", ['status' => 400]);
    }
    if ($post->post_type !== 'page') {
        return new WP_Error(
            'formidable_invalid_input',
            "page_id {$page_id} is of post_type='{$post->post_type}', not 'page'.",
            ['status' => 400],
        );
    }
    if ($post->post_status !== 'publish') {
        return new WP_Error(
            'formidable_invalid_input',
            "page_id {$page_id} is in '{$post->post_status}' status, not 'publish'.",
            ['status' => 400],
        );
    }
    return null;
}

/**
 * Find any existing on-submit action for the form whose event list overlaps
 * with the requested one. Returns the WP_Post or null when no match.
 *
 * @param list<string> $requested_events
 */
function frm_find_existing_on_submit(int $form_id, array $requested_events): ?WP_Post
{
    /** @var list<WP_Post> $posts */
    $posts = get_posts([
        'post_type' => FRM_ACTION_POST_TYPE,
        'post_status' => ['publish', 'draft'],
        'numberposts' => -1,
        'orderby' => 'ID',
        'order' => 'ASC',
        'meta_query' => [],
        'suppress_filters' => false,
    ]);
    foreach ($posts as $post) {
        if ($post->post_excerpt !== FRM_ON_SUBMIT_ACTION_TYPE) {
            continue;
        }
        if ((int) $post->menu_order !== $form_id) {
            continue;
        }
        $existing_events = frm_on_submit_decode_events($post);
        if (array_intersect($existing_events, $requested_events) !== []) {
            return $post;
        }
    }
    return null;
}

/**
 * Extract the `event` array from an on-submit action's settings blob.
 * Always returns a list (defaults to ['create'] when missing).
 *
 * @return list<string>
 */
function frm_on_submit_decode_events(WP_Post $post): array
{
    $settings = frm_on_submit_decode_settings($post);
    if (!isset($settings['event']) || !is_array($settings['event'])) {
        return ['create'];
    }
    /** @var list<string> $clean */
    $clean = array_values(array_filter(
        $settings['event'],
        static fn(mixed $v): bool => is_string($v) && in_array($v, ['create', 'update'], strict: true),
    ));
    return $clean === [] ? ['create'] : $clean;
}

/**
 * Decode the on-submit action's settings blob, whitelist-filtered.
 *
 * @return array<string, mixed>
 */
function frm_on_submit_decode_settings(WP_Post $post): array
{
    /** @var mixed $raw */
    $raw = $post->post_content;
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = maybe_unserialize($raw);
    if (!is_array($decoded)) {
        return [];
    }
    /** @var array<string, mixed> $kept */
    $kept = [];
    foreach (FRM_ON_SUBMIT_KEYS as $key) {
        if (!array_key_exists($key, $decoded)) {
            continue;
        }
        $kept[$key] = $decoded[$key];
    }
    return $kept;
}

/**
 * Apply the wp_insert_post / wp_update_post for the on-submit action.
 * Centralised so the main callback stays under the cyclomatic-complexity
 * threshold.
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $input
 * @param list<string> $event
 * @return array{action_id: int, created: bool, enabled: bool, type: string, settings: array<string, mixed>}|WP_Error
 */
function frm_persist_on_submit_action(
    int $form_id,
    string $type,
    array $settings,
    array $input,
    array $event,
): array|WP_Error {
    $enabled = !isset($input['enabled']) || (bool) (int) $input['enabled'];
    $post_status = $enabled ? 'publish' : 'draft';

    $existing = frm_find_existing_on_submit($form_id, $event);
    if ($existing instanceof WP_Post) {
        $updated = wp_update_post([
            'ID' => (int) $existing->ID,
            'post_status' => $post_status,
            'post_content' => maybe_serialize($settings),
        ], wp_error: true);
        if ($updated instanceof WP_Error) {
            return $updated;
        }
        return frm_on_submit_response(
            (int) $existing->ID,
            created: false,
            enabled: $enabled,
            type: $type,
            settings: $settings,
        );
    }

    $action_id = wp_insert_post([
        'post_type' => FRM_ACTION_POST_TYPE,
        'post_status' => $post_status,
        'post_title' => 'Confirmation',
        'post_excerpt' => FRM_ON_SUBMIT_ACTION_TYPE,
        'menu_order' => $form_id,
        'post_content' => maybe_serialize($settings),
    ], wp_error: true);
    if ($action_id instanceof WP_Error) {
        return $action_id;
    }
    if ($action_id <= 0) {
        return frm_error(
            code: 'formidable_set_on_submit_failed',
            message: 'wp_insert_post returned a non-positive id.',
        );
    }

    return frm_on_submit_response($action_id, created: true, enabled: $enabled, type: $type, settings: $settings);
}

/**
 * Shape the standard response envelope. The `$created` and `$enabled` booleans
 * map 1:1 to the JSON response keys — splitting this into two helpers would
 * just push the same flags up one frame.
 *
 * @param array<string, mixed> $settings
 * @return array{action_id: int, created: bool, enabled: bool, type: string, settings: array<string, mixed>}
 */
// @mago-expect lint:no-boolean-flag-parameter
function frm_on_submit_response(int $action_id, bool $created, bool $enabled, string $type, array $settings): array
{
    return [
        'action_id' => $action_id,
        'created' => $created,
        'enabled' => $enabled,
        'type' => $type,
        'settings' => $settings,
    ];
}
