<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/code-snippets-disable-snippet', [
    'label' => __('Disable Code Snippet', domain: 'novamira-pro'),
    'description' => __(
        'Deactivates a snippet by id. The snippet body stays in the database (unchanged) but stops executing on the next request. Reversible by code-snippets-enable-snippet. Calling disable on an already-inactive snippet is a no-op.',
        domain: 'novamira-pro',
    ),
    'category' => 'code-snippets',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'snippet' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\CodeSnippets\\code_snippets_disable_snippet',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Cheap and reversible — use freely whenever you want a snippet to stop running without losing its body. The opposite of code-snippets-enable-snippet.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id: int} $input
 * @return array<string, mixed>|WP_Error
 */
function code_snippets_disable_snippet(array $input): array|WP_Error
{
    $id = $input['id'];
    if ($id <= 0) {
        return new WP_Error('cs_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    $snippet = cs_load_snippet($id);
    if ($snippet === null) {
        return new WP_Error('cs_not_found', sprintf(__('Snippet with id %d not found.', domain: 'novamira-pro'), $id));
    }
    $gate = cs_require_type_available($snippet);
    if ($gate !== null) {
        return ['success' => false, 'error_code' => 'cs_pro_required', 'error' => $gate];
    }
    // Idempotency: deactivating an already-inactive snippet is a no-op.
    // The plugin's `deactivate_snippet()` returns null when wpdb->update
    // affects zero rows, which surfaces as `Deactivation failed.` — that
    // contradicts the `idempotent: true` annotation and confuses agents.
    if ($snippet->active === false) {
        return ['success' => true, 'snippet' => cs_snippet_to_array($snippet)];
    }
    $result = \Code_Snippets\deactivate_snippet($id);
    if ($result === null) {
        return ['success' => false, 'error' => 'Deactivation failed.'];
    }
    // Re-load so `active` reflects the post-deactivation state.
    $fresh = cs_load_snippet($id) ?? $result;
    return ['success' => true, 'snippet' => cs_snippet_to_array($fresh)];
}
