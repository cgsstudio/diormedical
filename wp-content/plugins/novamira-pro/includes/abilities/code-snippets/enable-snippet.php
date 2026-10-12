<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/code-snippets-enable-snippet', [
    'label' => __('Enable Code Snippet', domain: 'novamira-pro'),
    'description' => __(
        'Activates a snippet by id. From this point on, the snippet code runs on every matching request (based on its scope) — for PHP that means it can hook into actions / filters, for HTML it becomes a shortcode or head/footer markup, for CSS / JS it gets enqueued. ALWAYS call code-snippets-validate-snippet first on PHP bodies — the plugin only catches a narrow set of activation-time problems (duplicate function names, missing class identifiers) and surfaces those under `error_line`; a plain PHP syntax error slips past activation and fatals on the next render. Calling enable on an already-active snippet is a safe no-op (returns `success: true` without re-firing activation hooks).',
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
            'error_line' => ['type' => 'integer'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\CodeSnippets\\code_snippets_enable_snippet',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Enabling a PHP snippet executes its code on every matching request — confirm with the user before activating anything that mutates data (file writes, db updates, third-party API calls). Run code-snippets-validate-snippet immediately before this call to catch PHP syntax errors (the plugin\'s activation-time check ONLY catches duplicate identifiers and a few semantic mistakes — it does NOT catch syntax errors, which fatal at execution time).',
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
function code_snippets_enable_snippet(array $input): array|WP_Error
{
    $id = $input['id'];
    if ($id <= 0) {
        return new WP_Error('cs_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    $snippet = cs_load_snippet($id);
    if ($snippet === null) {
        return new WP_Error('cs_not_found', sprintf(__('Snippet with id %d not found.', domain: 'novamira-pro'), $id));
    }
    // Pro types remain in the DB after Code Snippets Pro is deactivated.
    // Flipping `active` would succeed at the DB layer but the plugin can't
    // actually register the hooks for those types, so it would be an
    // inert toggle — refuse loudly instead.
    $gate = cs_require_type_available($snippet);
    if ($gate !== null) {
        return ['success' => false, 'error_code' => 'cs_pro_required', 'error' => $gate];
    }
    // Real idempotency: enabling an already-active snippet is a no-op.
    // Otherwise the plugin's wpdb->update returns 0 rows changed and we
    // surface it as `Could not activate snippet.` — which contradicts the
    // `idempotent: true` annotation. Returning the current snippet shape
    // keeps the response uniform with the post-activation path.
    if ($snippet->active === true) {
        return ['success' => true, 'snippet' => cs_snippet_to_array($snippet)];
    }
    $result = \Code_Snippets\activate_snippet($id);

    // activate_snippet returns a Snippet on success or a string error
    // message when the plugin's pre-flight check rejects the activation
    // (e.g. duplicate function/class identifier — note: a pure PHP syntax
    // error is NOT caught here, it surfaces only at execution time, so
    // callers should run code-snippets-validate-snippet first).
    if (is_string($result)) {
        $err = ['success' => false, 'error' => $result];
        $reloaded = cs_load_snippet($id);
        if ($reloaded !== null && is_array($reloaded->code_error)) {
            $err['error_line'] = (int) $reloaded->code_error[1];
        }
        return $err;
    }
    // Re-load so the returned snippet reflects the post-activation state
    // (the Snippet that activate_snippet returns is the pre-update copy).
    $fresh = cs_load_snippet($id) ?? $result;
    return ['success' => true, 'snippet' => cs_snippet_to_array($fresh)];
}
