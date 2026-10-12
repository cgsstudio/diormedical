<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-create-template', [
    'label' => __('Create Oxygen Template', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Oxygen template post under one of the editable template CPTs. `post_type` is required and must be one of `oxygen_template`, `oxygen_header`, `oxygen_footer`, `oxygen_block` (the mode-aware slugs Oxygen 6 registers). `title` (or the WordPress-native alias `post_title`) is required. Optional `settings` is the template-settings JSON object. The template is seeded with `ruleGroups: []` (like Oxygen\'s own template manager): the engine only CONSIDERS a template for request matching when the `ruleGroups` key exists, and an empty list means "no extra constraints" — the template applies wherever its `settings.type` matches. To narrow WHERE it applies, call set-template-conditions afterwards; to keep it from rendering anywhere until configured, pass `settings: {"disabled": true}`. Element-tree content (the builder canvas) is NOT created here — pair with add-element / set-content or open the template in the Oxygen editor. Popups are not part of the editable template surface in Oxygen mode and are not created here. If `settings.parentId` points at a template that ALREADY stores executable code, the create is gated behind `allow_code_elements: true`: the parent\'s stored tree renders through the new template.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => [
                'type' => 'string',
                'description' => 'Visible title in the admin list. Required (or use `post_title`).',
            ],
            'post_title' => ['type' => 'string', 'description' => 'WordPress-native alias for `title`.'],
            'post_type' => [
                'type' => 'string',
                'enum' => [
                    'oxygen_template',
                    'oxygen_header',
                    'oxygen_footer',
                    'oxygen_block',
                ],
                'description' => 'Which CPT to create under. Required.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Optional initial template-settings JSON, a CLOSED schema: `{type?: string, priority?: int, fallback?: bool, disabled?: bool, parentId?: int}`. `type` must be a registered template-type slug — discover valid values via list-template-conditions (`template_types`); for a single template targeting a custom post type it is that post type\'s slug. `priority` is an integer, `fallback`/`disabled` booleans, `parentId` an existing template id. Unknown keys are rejected. Visibility rules (`ruleGroups`) are NOT set here — create the template, then call set-template-conditions (the single source of truth, which validates every rule against the live catalogue).',
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'description' => 'Explicit user approval to create a template whose `settings.parentId` attaches a parent template that ALREADY stores executable code (an eval()\'d payload in its tree or ruleGroups). The create call carries no code itself, but the engine starts running the parent\'s stored code through the new template. Never set this on your own initiative: ask the user first.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => ['publish'],
                'description' => 'Post status (or the WordPress-native alias `post_status`). The underlying Soflyy engine forces template-like CPTs to `publish`, so `draft` is not accepted here.',
                'default' => 'publish',
            ],
            'post_status' => [
                'type' => 'string',
                'enum' => ['publish'],
                'description' => 'WordPress-native alias for `status`.',
            ],
            'slug' => [
                'type' => 'string',
                'description' => 'URL slug for the template (WordPress-native alias for `post_name`). Omit to let WP derive it from the title.',
            ],
            'post_name' => ['type' => 'string', 'description' => 'WordPress-native alias for `slug`.'],
        ],
        'required' => ['post_type'],
        'anyOf' => [
            ['required' => ['title']],
            ['required' => ['post_title']],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'template' => ['type' => 'object'],
            'template_id' => [
                'type' => 'integer',
                'description' => 'Present on a failed create only when rollback was blocked and the partial template still exists.',
            ],
            'verification' => ['type' => 'object'],
            'rollback' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_create_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates an empty template post, seeded with `ruleGroups: []` so the engine considers it for matching immediately (it applies wherever its `settings.type` matches until you narrow it). Pair with set-template-conditions to scope WHERE it renders — or pass settings.disabled=true at creation if it must stay dark until configured — and with add/set-content abilities to populate the builder canvas. The engine\'s UI seeds `priority` per template type (1 for catch-all/everywhere, 10 for all-singles/all-archives, 20 for a specific single/archive/front-page); this ability seeds 1, so pass an explicit `priority` matching your type\'s specificity when several templates can match the same request (higher wins).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Normalise the WordPress-native aliases and validate the create input,
 * returning the `wp_insert_post` array, or a WP_Error the caller surfaces.
 * Kept separate so the callback stays under the complexity budget.
 *
 * @param array<string, mixed> $input
 * @return array{post_title: string, post_type: string, post_status: string, post_content: string, post_name?: string}|\WP_Error
 */
function ox_prepare_template_insert(array $input): array|\WP_Error
{
    /** @var mixed $title_raw */
    $title_raw = $input['post_title'] ?? $input['title'] ?? null;
    if (!is_string($title_raw) || trim($title_raw) === '') {
        return ox_error('oxygen_invalid_input', __(
            '`title` (or `post_title`) is required and must be a non-empty string.',
            domain: 'novamira-pro',
        ));
    }
    if (!isset($input['post_type']) || !is_string($input['post_type'])) {
        return ox_error('oxygen_invalid_input', __('`post_type` is required.', domain: 'novamira-pro'));
    }
    if (!in_array($input['post_type'], ox_template_post_types(), strict: true)) {
        return ox_error('oxygen_invalid_input', __(
            '`post_type` must be one of the editable Oxygen template CPTs (oxygen_template, oxygen_header, oxygen_footer, oxygen_block).',
            domain: 'novamira-pro',
        ));
    }
    // wp_insert_post EXPECTS slashed data (it wp_unslash-es internally), so a
    // backslash in the title/slug must be wp_slash-ed first or it is stripped.
    $insert = [
        'post_title' => wp_slash(trim($title_raw)),
        'post_type' => $input['post_type'],
        'post_status' => 'publish',
        'post_content' => '',
    ];
    /** @var mixed $slug_raw */
    $slug_raw = $input['post_name'] ?? $input['slug'] ?? null;
    if (is_string($slug_raw) && $slug_raw !== '') {
        if (sanitize_title($slug_raw) === '') {
            return ox_error('oxygen_invalid_input', __(
                '`slug` (or `post_name`) must contain at least one character WordPress can use in a URL slug.',
                domain: 'novamira-pro',
            ));
        }
        $insert['post_name'] = wp_slash($slug_raw);
    }
    return $insert;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function oxygen_create_template(array $input): array
{
    $insert = ox_prepare_template_insert($input);
    if ($insert instanceof \WP_Error) {
        return ['success' => false, 'error' => $insert->get_error_message()];
    }

    // Validate the settings bag against the live registry BEFORE creating the
    // post, so a bad request fails without leaving an orphan template. Rejects
    // an unknown `type` and condition keys (ruleGroups/conditions/priority) that
    // belong to oxygen-set-template-conditions — otherwise they would persist
    // verbatim, unvalidated, and silently never match.
    if (isset($input['settings']) && is_array($input['settings'])) {
        $settings = $input['settings'];
        $settings_error = ox_validate_template_settings_input($settings);
        if ($settings_error instanceof \WP_Error) {
            return ['success' => false, 'error' => $settings_error->get_error_message()];
        }
        // A brand-new template stores no code of its own, but `settings.parentId`
        // splices an EXISTING template's stored tree into the render hierarchy
        // (engine themeless/god-controller.php:131-158), and the seeded
        // `ruleGroups: []` means this template matches as soon as its `type`
        // does. Creating it therefore arms the parent's stored payloads.
        $armed = ox_template_arming_payloads(0, $settings);
        if ($armed !== [] && !ox_coerce_bool($input['allow_code_elements'] ?? false)) {
            return ['success' => false, 'error' => ox_code_payload_block($armed)->get_error_message()];
        }
    }

    $post_id = wp_insert_post($insert, wp_error: true);
    if ($post_id instanceof \WP_Error) {
        return ['success' => false, 'error' => 'wp_insert_post failed: ' . $post_id->get_error_message()];
    }

    $field_mismatch = ox_created_template_field_mismatch($post_id, $input);
    if ($field_mismatch !== null) {
        return ox_create_template_failure_with_rollback($post_id, sprintf(
            'The created template did not retain the requested `%s`; a storage hook altered the post.',
            $field_mismatch,
        ));
    }

    /** @var array<string, mixed> $settings_patch */
    $settings_patch = isset($input['settings']) && is_array($input['settings']) ? $input['settings'] : [];
    // Match Oxygen's own template-creation flow: the manage-templates SPA
    // seeds `ruleGroups: []` on every new template, and an omitted priority
    // is 1 (the engine's request-time fallback, `themeless/request.php:138-147`).
    // The `ruleGroups` seed is LOAD-BEARING: `doesTemplateApply` returns false
    // when the key is ABSENT (`themeless/request.php:173-179`), so a template
    // without it never matches any request; an empty list means "no additional
    // constraints" and matches wherever `settings.type` applies. The seed
    // cannot be overridden here — `ruleGroups` in the input bag is rejected by
    // the closed schema and owned by oxygen-set-template-conditions.
    // Null uses the documented unset semantics and therefore removes the
    // priority seed.
    $initial_settings = ox_merge_template_settings_patch(['priority' => 1, 'ruleGroups' => []], $settings_patch);
    $written = ox_write_template_settings($post_id, $initial_settings);
    if ($written instanceof \WP_Error) {
        return ox_create_template_failure_with_rollback($post_id, $written->get_error_message());
    }

    $created = ox_load_template($post_id);
    if ($created === null) {
        return ox_create_template_failure_with_rollback(
            $post_id,
            error: 'Template created but could not be re-loaded.',
        );
    }
    return [
        'success' => true,
        'template' => ox_template_to_array($created),
        'verification' => [
            'persisted' => true,
            'fields' => ['title', 'slug', 'post_type', 'status', 'settings'],
            'settings_fingerprint' => ox_state_fingerprint(ox_read_template_settings($post_id)),
        ],
    ];
}

/**
 * Return the first create field whose observed value differs from the request.
 * WordPress's id/uniqueness-aware canonical slug is the expected value.
 *
 * @param array<string, mixed> $input
 */
function ox_created_template_field_mismatch(int $post_id, array $input): ?string
{
    /** @var mixed $post */
    $post = get_post($post_id);
    if (!$post instanceof \WP_Post) {
        return 'post';
    }
    /** @var mixed $title_raw */
    $title_raw = $input['post_title'] ?? $input['title'] ?? '';
    $title = is_string($title_raw) ? trim($title_raw) : '';
    /** @var mixed $expected_title */
    $expected_title = sanitize_post_field('post_title', $title, $post_id, context: 'db');
    if (
        $post->post_title !== $expected_title
        || $post->post_type !== (string) ($input['post_type'] ?? '')
        || $post->post_status !== 'publish'
    ) {
        return 'title/post_type/status';
    }
    /** @var mixed $slug_raw */
    $slug_raw = $input['post_name'] ?? $input['slug'] ?? null;
    if (!is_string($slug_raw) || $slug_raw === '') {
        return $post->post_name === '' ? 'slug' : null;
    }
    $expected_slug = wp_unique_post_slug(
        sanitize_title($slug_raw),
        $post_id,
        $post->post_status,
        $post->post_type,
        $post->post_parent,
    );
    return $post->post_name === $expected_slug ? null : 'slug';
}

/**
 * Delete a partially-created template and report the OBSERVED rollback result.
 *
 * @return array<string, mixed>
 */
function ox_create_template_failure_with_rollback(int $post_id, string $error): array
{
    wp_delete_post($post_id, force_delete: true);
    if (get_post($post_id) !== null) {
        return [
            'success' => false,
            'template_id' => $post_id,
            'rollback' => ['restored' => false, 'retained' => ['post' => true]],
            'error' => sprintf(
                '%s Rollback was blocked: partial template %d still exists and requires cleanup.',
                $error,
                $post_id,
            ),
        ];
    }
    return [
        'success' => false,
        'rollback' => ['restored' => true, 'retained' => []],
        'error' => $error,
    ];
}
