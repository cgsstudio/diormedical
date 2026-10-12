<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/code-snippets-edit-snippet', [
    'label' => __('Edit Code Snippet', domain: 'novamira-pro'),
    'description' => __(
        'Merges new field values into an existing snippet identified by id. Only the keys you pass are changed — omitted keys keep their current value. To change `type` and `scope` together, pass both (changing one without the other often produces an invalid pair). The active flag is NOT touched here — use code-snippets-enable-snippet / code-snippets-disable-snippet for toggling execution. Code Snippets re-validates the PHP code on save and deactivates the snippet if the code errors; the result then includes deactivated_on_save and code_error.',
        domain: 'novamira-pro',
    ),
    'category' => 'code-snippets',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Id of the snippet to edit.'],
            'name' => ['type' => 'string'],
            'code' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'scope' => ['type' => 'string'],
            'desc' => ['type' => 'string'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'priority' => [
                'type' => 'integer',
                'minimum' => 0,
                'maximum' => 32_767,
            ],
            'condition_id' => [
                'type' => 'integer',
                'description' => 'Pro-only. Link or unlink the snippet to a condition snippet (type=cond). Set to the condition\'s id to gate execution; set to 0 to detach. The condition itself is a separate snippet — create it first via code-snippets-create-snippet type=cond.',
            ],
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
            'error_code' => [
                'type' => 'string',
                'description' => 'Machine-stable failure code: cs_pro_required, cs_snippet_locked, cs_save_failed, cs_invalid_tag, cs_invalid_utf8, cs_name_too_long.',
            ],
            'allowed_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'deactivated_on_save' => [
                'type' => 'boolean',
                'description' => 'True when the snippet was active but Code Snippets deactivated it on save because the edited code raised an error.',
            ],
            'code_error' => [
                'type' => 'string',
                'description' => 'The validation error Code Snippets reported when it deactivated the snippet on save.',
            ],
            'note' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\CodeSnippets\\code_snippets_edit_snippet',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge semantics: only fields present in the input are updated. Editing an ACTIVE snippet replaces the executing code on the next request — be extra careful with PHP snippets (a syntax error breaks the site). Prefer disable → edit → validate → enable for risky changes.',
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
function code_snippets_edit_snippet(array $input): array|WP_Error
{
    $id = isset($input['id']) && is_int($input['id']) ? $input['id'] : 0;
    if ($id <= 0) {
        return new WP_Error('cs_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }

    // The whole read-modify-write cycle has to run under the snippet-id
    // lock — without it, parallel edit calls produce ~40% silent data
    // loss because each call snapshots the full Snippet (all columns)
    // before mutating, and save_snippet writes back all columns. The
    // lock serialises cross-request edits on the same id (Round 1 S7).
    return cs_with_snippet_lock($id, static function () use ($id, $input): array|WP_Error {
        $snippet = cs_load_snippet($id);
        if ($snippet === null) {
            return new WP_Error('cs_not_found', sprintf(
                __('Snippet with id %d not found.', domain: 'novamira-pro'),
                $id,
            ));
        }

        $gate = cs_require_type_available($snippet);
        if ($gate !== null) {
            return ['success' => false, 'error_code' => 'cs_pro_required', 'error' => $gate];
        }
        // Code Snippets 3.10+ locking: save_snippet() would silently keep
        // the stored name / code and report success. Refuse up front.
        if (cs_is_locked($snippet)) {
            return [
                'success' => false,
                'error_code' => 'cs_snippet_locked',
                'error' => sprintf(
                    'Snippet %d is locked in Code Snippets. Code Snippets discards name / code changes to a locked snippet on save. Ask the user to unlock it from the Code Snippets admin (Snippets table, row lock toggle) first.',
                    $id,
                ),
            ];
        }

        $failure = cs_validate_edit_type_scope_change($snippet, $input);
        if ($failure !== null) {
            return $failure;
        }
        if (isset($input['scope']) && is_string($input['scope'])) {
            $snippet->scope = $input['scope'];
        }

        try {
            cs_apply_snippet_fields($snippet, $input);
        } catch (\InvalidArgumentException $e) {
            return ['success' => false, 'error' => $e->getMessage(), 'error_code' => 'cs_invalid_tag'];
        }
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
        if (!mb_check_encoding($snippet->code, encoding: 'UTF-8')) {
            return [
                'success' => false,
                'error' => 'code contains bytes that are not valid UTF-8. The wp_snippets.code column is utf8mb4 and will reject invalid sequences.',
                'error_code' => 'cs_invalid_utf8',
            ];
        }

        return cs_persist_edit($snippet);
    });
}

/**
 * Save an edited snippet and build the response. Guards both failure
 * shapes the plugin can produce: a null return, and a "successful" return
 * whose stored row does not carry the edited values (see
 * cs_saved_fields_landed()).
 *
 * @return array<string, mixed>
 */
function cs_persist_edit(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $snippet): array
{
    $saved = \Code_Snippets\save_snippet($snippet);
    if ($saved === null || $saved->id === 0) {
        return ['success' => false, 'error' => 'Snippet save failed (database error).'];
    }
    if (!cs_saved_fields_landed($snippet, $saved)) {
        return [
            'success' => false,
            'error_code' => 'cs_save_failed',
            'error' => 'Snippet save did not persist: the stored row still carries the previous values (database update failed).',
        ];
    }
    return cs_edit_result($saved);
}

/**
 * When edit-snippet changes the type, the scope, or both, run them
 * through cs_validate_type_scope() with the "missing" half resolved
 * against the snippet's current state. Returns the failure payload (same
 * shape and `cs_pro_required` code as create-snippet) when the resulting
 * pair is invalid, null when there is no change or the change is valid.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|null
 */
function cs_validate_edit_type_scope_change(
    \Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $snippet,
    array $input,
): ?array {
    $changing_type = isset($input['type']) && is_string($input['type']);
    $changing_scope = isset($input['scope']) && is_string($input['scope']);
    if (!$changing_type && !$changing_scope) {
        return null;
    }
    $next_type = $changing_type ? $input['type'] : $snippet->type;
    $next_scope = $changing_scope ? $input['scope'] : $snippet->scope;
    $err = cs_validate_type_scope($next_type, $next_scope);
    return $err === null ? null : cs_type_scope_failure($next_type, $err);
}
