<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;

/**
 * Ability: Create a Bricks template.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-create-template', [
    'label' => __('Create Bricks Template', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Bricks template with a given type and optional element data. Returns the new template ID.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => [
                'type' => 'string',
                'description' => 'Template title.',
                'minLength' => 1,
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Template type: "header", "footer", "content", "section", "popup", "archive", "search", "error".',
                'default' => 'section',
                'enum' => ['header', 'footer', 'content', 'section', 'popup', 'archive', 'search', 'error'],
            ],
            'status' => [
                'type' => 'string',
                'description' => 'Post status.',
                'default' => 'publish',
                'enum' => ['publish', 'draft', 'private'],
            ],
            'elements' => [
                'type' => 'array',
                'description' => 'Optional array of Bricks elements (same shape as bricks-set-content; supplied ids must use exactly 6 characters, and omitted ids are generated and returned in generated_ids). Routed automatically to the area matching `type`: header templates write to the header area, footer templates to the footer area, every other type to the content area.',
                'items' => ['type' => 'object'],
                'default' => [],
            ],
            'template_settings' => [
                'type' => 'object',
                'description' => 'Optional template settings (conditions, etc.).',
                'default' => [],
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Confirmation gate for the Bricks "code" element (raw HTML/CSS/JS), which is a last resort. By default a tree containing a "code" element is rejected with guidance to use native elements. Set true ONLY after the user has explicitly confirmed they want a raw code/HTML element.',
            ],
        ],
        'required' => ['title'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'edit_link' => ['type' => 'string'],
            'generated_ids' => [
                'type' => 'object',
                'description' => 'Present when elements were sent without an id: maps the 0-based index of each such element in the request to the id generated for it (6 lowercase hex characters, the format Bricks itself uses).',
            ],
            'saved' => [
                'type' => 'boolean',
                'description' => 'Deprecated alias of verified (kept for existing callers; it is no longer a literal true). Read verified instead.',
            ],
            'verified' => [
                'type' => 'boolean',
                'description' => 'True only when the element tree read back from the database after the write equals the tree that was written. False means the stored content differs — the warnings say how.',
            ],
            'stored_element_count' => [
                'type' => 'integer',
                'description' => 'Number of elements the template area holds after the write (read back). Both fields are present only when elements were passed.',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => '"regenerated" when Bricks serves CSS from external files and the per-post CSS file was rewritten for this write; "inline" when Bricks serves CSS inline (nothing to regenerate); "skipped(reason)" when file mode is on but the file could not be written — the reason names the fix.',
            ],
            'unresolved_variables' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when a written value references var(--name) for a name that is neither a Bricks global variable, a palette colour nor a custom property declared in the site\'s custom CSS: those declarations are dropped by the browser until the token exists.',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Validation warnings about the element data.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_create_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'After creating a template, use novamira/bricks-set-content to populate it with elements if you did not pass elements in the creation call.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

function bricks_create_template(array $input): array|WP_Error
{
    $title = (string) $input['title'];
    $type = (string) ($input['type'] ?? 'section');
    $status = (string) ($input['status'] ?? 'publish');

    /** @var array<string, mixed> $template_settings */
    $template_settings = is_array($input['template_settings'] ?? null) ? $input['template_settings'] : [];
    $settings_result = ct_normalize_template_settings($template_settings);
    if (is_wp_error($settings_result)) {
        return $settings_result;
    }
    $template_settings = $settings_result['settings'];
    $condition_warnings = $settings_result['warnings'];

    /** @var list<array<string, mixed>> $elements */
    $elements = $input['elements'] ?? [];
    $code_ids = bricks_collect_code_element_ids($elements);
    if ($code_ids !== [] && ($input['allow_code_elements'] ?? false) !== true) {
        return bricks_code_element_block($code_ids);
    }

    $unsafe_id_error = bricks_unsafe_new_element_ids_error($elements);
    if ($unsafe_id_error instanceof WP_Error) {
        return $unsafe_id_error;
    }

    $post_id = wp_insert_post([
        'post_type' => BRICKS_DB_TEMPLATE_SLUG,
        'post_title' => $title,
        'post_status' => $status,
    ], wp_error: true);

    if (is_wp_error($post_id)) {
        return $post_id;
    }

    update_post_meta($post_id, BRICKS_DB_TEMPLATE_TYPE, meta_value: $type);
    update_post_meta($post_id, BRICKS_DB_EDITOR_MODE, meta_value: 'bricks');

    $stored = ct_store_template_elements($post_id, $elements, $type);
    if ($stored instanceof WP_Error) {
        return $stored;
    }

    if (count($template_settings) > 0) {
        update_post_meta($post_id, BRICKS_DB_TEMPLATE_SETTINGS, $template_settings);
    }

    clean_post_cache($post_id);

    return ct_build_result(
        [
            'template_id' => $post_id,
            'title' => $title,
            'type' => $type,
            'status' => $status,
        ],
        $stored,
        $condition_warnings,
    );
}

/**
 * Response of a completed create-template call: identity, edit link, the
 * write-verification fields and generated ids when elements were written,
 * and the merged element + condition warnings.
 *
 * @param array{template_id: int, title: string, type: string, status: string} $identity
 * @param array{warnings: list<string>, verified: bool|null, stored_element_count: int|null, css_file: string|null, unresolved_variables: list<string>, generated_ids: array<string, string>} $stored
 * @param list<string> $condition_warnings
 * @return array<string, mixed>
 */
function ct_build_result(array $identity, array $stored, array $condition_warnings): array
{
    $result = $identity;
    $result['edit_link'] = get_edit_post_link($identity['template_id'], context: 'raw') ?? '';
    if ($stored['verified'] !== null) {
        $result['verified'] = $stored['verified'];
        $result['saved'] = $stored['verified'];
        $result['stored_element_count'] = $stored['stored_element_count'];
    }
    if ($stored['css_file'] !== null) {
        $result['css_file'] = $stored['css_file'];
    }
    if ($stored['unresolved_variables'] !== []) {
        $result['unresolved_variables'] = $stored['unresolved_variables'];
    }
    if ($stored['generated_ids'] !== []) {
        $result['generated_ids'] = bricks_generated_ids_object($stored['generated_ids']);
    }

    $warnings = array_merge($stored['warnings'], $condition_warnings);
    if (count($warnings) > 0) {
        $result['warnings'] = $warnings;
    }

    return $result;
}

/**
 * Validates and persists the optional element payload of a new template into
 * the area matching its type, through the same write pipeline as
 * bricks-set-content (normalisation, signing, read-back); the code-element
 * gate runs in the caller before the post is created. On a hard error the
 * just-created template post is deleted again and the WP_Error is returned
 * instead. `verified` is null when no elements were passed.
 *
 * @param  list<array<string, mixed>> $elements
 * @return array{warnings: list<string>, verified: bool|null, stored_element_count: int|null, css_file: string|null, unresolved_variables: list<string>, generated_ids: array<string, string>}|WP_Error
 */
function ct_store_template_elements(int $post_id, array $elements, string $type): array|WP_Error
{
    if (count($elements) === 0) {
        return [
            'warnings' => [],
            'verified' => null,
            'stored_element_count' => null,
            'css_file' => null,
            'unresolved_variables' => [],
            'generated_ids' => [],
        ];
    }

    // Conform root parents before generating missing ids.
    $elements = bricks_normalize_root_parents($elements);
    ['elements' => $elements, 'generated_ids' => $generated_ids] = bricks_assign_missing_ids($elements);
    $elements = bricks_normalize_write_elements($elements);

    $validation = bricks_validate_elements($elements);
    if (count($validation['errors']) > 0) {
        wp_delete_post($post_id, force_delete: true);
        return bricks_error('invalid_elements', implode(' ', $validation['errors']));
    }

    $elements = bricks_ensure_children($elements);
    // A just-created template has no lock and nothing to snapshot.
    $commit = bricks_commit_area_write(
        $post_id,
        bricks_template_expected_area($type),
        $elements,
        $validation['warnings'],
        [
            'revision' => false,
        ],
    );
    if ($commit instanceof WP_Error) {
        wp_delete_post($post_id, force_delete: true);
        return $commit;
    }

    return [
        'warnings' => $commit['warnings'],
        'verified' => $commit['verified'],
        'stored_element_count' => $commit['stored_element_count'],
        'css_file' => $commit['css_file'],
        'unresolved_variables' => $commit['unresolved_variables'],
        'generated_ids' => $generated_ids,
    ];
}

/**
 * Runs the template_settings payload through the shared condition normalizer
 * so `templateConditions` written at create time carries the same guarantees
 * as bricks-set-template-conditions: multi-value keys (postType, archiveType,
 * terms, ids, …) are persisted as arrays, never scalars — Bricks 2.4 discards
 * malformed scalar values instead of matching them.
 *
 * @param  array<string, mixed> $template_settings
 * @return array{settings: array<string, mixed>, warnings: list<string>}|WP_Error
 */
function ct_normalize_template_settings(array $template_settings): array|WP_Error
{
    if (!array_key_exists('templateConditions', $template_settings)) {
        return ['settings' => $template_settings, 'warnings' => []];
    }

    /** @var mixed $raw_conditions */
    $raw_conditions = $template_settings['templateConditions'];
    if (!is_array($raw_conditions)) {
        return bricks_error(
            'invalid_condition',
            message: 'template_settings.templateConditions must be an array of condition objects.',
        );
    }

    $normalized = bricks_normalize_template_conditions(array_values($raw_conditions));
    if (is_wp_error($normalized)) {
        return $normalized;
    }

    if ($normalized['conditions'] === []) {
        unset($template_settings['templateConditions']);
    }
    if ($normalized['conditions'] !== []) {
        $template_settings['templateConditions'] = $normalized['conditions'];
    }

    return ['settings' => $template_settings, 'warnings' => $normalized['warnings']];
}
