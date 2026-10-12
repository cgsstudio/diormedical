<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/code-snippets-create-snippet', [
    'label' => __('Create Code Snippet', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new snippet. Required: `name`, `code`, `type` (php|html|css|js|cond), `scope`. Optional: `desc`, `tags[]`, `priority`. SAFETY: the new snippet is always created INACTIVE — it is saved to the database but does NOT run on the site until you call code-snippets-enable-snippet (or pass `activate: true` to opt into immediate activation, in which case the agent takes responsibility for the safety review). The css / js / cond types require Code Snippets Pro on this install; the response surfaces a clear error when an unavailable type is requested. Note: Code Snippets does NOT enforce unique `name` — multiple snippets may share the same name; identify rows by the id returned in `snippet.id`. Note: operates on the per-site `wp_snippets` table only; network-shared snippets in `wp_ms_snippets` are out of scope.',
        domain: 'novamira-pro',
    ),
    'category' => 'code-snippets',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string', 'description' => 'Short label visible in the admin list. Required.'],
            'code' => [
                'type' => 'string',
                'description' => 'The snippet body. For type=php, do NOT include the opening <?php tag — the plugin adds it on save.',
            ],
            'type' => [
                'type' => 'string',
                'description' => 'One of: php | html | css | js | cond. Call code-snippets-get-types-schema for the type/scope matrix.',
            ],
            'scope' => [
                'type' => 'string',
                'description' => 'Where/when the snippet runs. Valid values depend on type (e.g. type=php accepts global / admin / front-end / single-use).',
            ],
            'desc' => [
                'type' => 'string',
                'description' => 'Optional long description; surfaces in the admin list-table.',
            ],
            'tags' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional list of tags for organisation. Useful for agent bookkeeping (e.g. "nvp").',
            ],
            'priority' => [
                'type' => 'integer',
                'description' => 'Execution priority (lower runs earlier). Defaults to 10. Range 0..32767 — the plugin\'s priority column is SMALLINT, MySQL clamps anything larger to 32767 and Code Snippets coerces negative values via absint() (Round 2 R10).',
                'minimum' => 0,
                'maximum' => 32_767,
            ],
            'condition_id' => [
                'type' => 'integer',
                'description' => 'Pro-only. Id of a snippet of type=cond / scope=condition. When set (and non-zero), the snippet only runs when that condition evaluates true. Defaults to 0 (no condition gate). Use code-snippets-get-conditions-schema for the condition JSON shape.',
            ],
            'activate' => [
                'type' => 'boolean',
                'description' => 'Optional. When true, activate the snippet immediately after save. DEFAULT FALSE — new snippets stay inactive until explicitly enabled, so the agent has a chance to validate before code runs on the site.',
            ],
        ],
        'required' => ['name', 'code', 'type', 'scope'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'snippet' => ['type' => 'object'],
            'error' => ['type' => 'string'],
            'allowed_types' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\CodeSnippets\\code_snippets_create_snippet',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Workflow: pick type + scope (call code-snippets-get-types-schema if unsure) → write the code body (PHP without <?php) → call this with activate=false (the safe default) → optionally call code-snippets-validate-snippet to lint → finally code-snippets-enable-snippet when the user has confirmed. For PHP snippets that wire hooks (add_action / add_filter), prefer scope=global. For one-off data migrations, use scope=single-use (the snippet auto-deactivates after one run).',
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
function code_snippets_create_snippet(array $input): array
{
    $type = isset($input['type']) && is_string($input['type']) ? $input['type'] : '';
    $scope = isset($input['scope']) && is_string($input['scope']) ? $input['scope'] : '';
    $err = cs_validate_type_scope($type, $scope);
    if ($err !== null) {
        // Pro-only type on a free install carries the machine-stable
        // cs_pro_required code so agents can branch deterministically
        // (Round 1 S4); shared with edit-snippet.
        return cs_type_scope_failure($type, $err);
    }

    $snippet = cs_new_snippet();
    $snippet->scope = $scope;
    // The fresh Snippet leaves `network` as null; the output schema
    // declares it boolean, so initialise to false before save_snippet
    // sees the row (Round 2 R9).
    $snippet->network = false;
    try {
        cs_apply_snippet_fields($snippet, $input);
    } catch (\InvalidArgumentException $e) {
        return ['success' => false, 'error' => $e->getMessage(), 'error_code' => 'cs_invalid_tag'];
    }
    $snippet->active = isset($input['activate']) && $input['activate'] === true;

    if ($snippet->name === '' || $snippet->code === '') {
        return ['success' => false, 'error' => 'name and code are required and must be non-empty strings.'];
    }
    // wp_snippets.code is utf8mb4 — non-UTF-8 byte sequences (e.g. an
    // accidental binary blob) trigger a silent DB-level rejection that
    // surfaces as a generic save error. Surface a machine-stable code
    // (Round 2 R13).
    if (!mb_check_encoding($snippet->code, encoding: 'UTF-8')) {
        return [
            'success' => false,
            'error' => 'code contains bytes that are not valid UTF-8. The wp_snippets.code column is utf8mb4 and will reject invalid sequences.',
            'error_code' => 'cs_invalid_utf8',
        ];
    }
    // The wp_snippets.name column is tinytext (255 byte cap). Plugin's
    // save returns id=0 silently when the column overflows. Pre-check
    // so the agent gets an actionable error instead of a generic
    // "database error" (Round 1 audit S13).
    if (strlen($snippet->name) > 255) {
        return [
            'success' => false,
            'error' => sprintf(
                'name is too long (%d bytes). The wp_snippets.name column accepts at most 255 bytes.',
                strlen($snippet->name),
            ),
            'error_code' => 'cs_name_too_long',
        ];
    }

    $saved = \Code_Snippets\save_snippet($snippet);
    if ($saved === null || $saved->id === 0) {
        return ['success' => false, 'error' => 'Snippet save failed (database error).'];
    }

    return ['success' => true, 'snippet' => cs_snippet_to_array($saved)];
}
