<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/code-snippets-get-snippet', [
    'label' => __('Get Code Snippet', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single snippet by id, including the executable `code` body and all metadata (name, desc, type, scope, tags, priority, active flag, modified timestamp, revision, condition_id, plus a `shortcode` field when type=html scope=content). Returns WP_Error `cs_not_found` when the id does not exist.',
        domain: 'novamira-pro',
    ),
    'category' => 'code-snippets',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Snippet id from code-snippets-list-snippets.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'name' => ['type' => 'string'],
            'desc' => ['type' => 'string'],
            'code' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'scope' => ['type' => 'string'],
            'tags' => ['type' => 'array', 'items' => ['type' => 'string']],
            'priority' => ['type' => 'integer'],
            'active' => ['type' => 'boolean'],
            'network' => ['type' => 'boolean'],
            'modified' => ['type' => 'string'],
            'revision' => ['type' => 'integer'],
            'locked' => [
                'type' => 'boolean',
                'description' => 'True when the installed Code Snippets supports snippet locking and this snippet is locked; a locked snippet refuses name / code edits and delete until unlocked in the Code Snippets admin.',
            ],
            'condition_id' => [
                'type' => 'integer',
                'description' => 'Pro condition snippet id this snippet is gated by, or 0 when ungated.',
            ],
            'shortcode' => [
                'type' => 'string',
                'description' => 'Auto-generated `[code_snippet id="<id>"]` shortcode. Present only when type=html and scope=content.',
            ],
        ],
        'required' => ['id', 'name', 'code', 'type', 'scope', 'active'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\CodeSnippets\\code_snippets_get_snippet',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pass the integer id from code-snippets-list-snippets. The response includes the full code body — use this only when you need to read or about to edit the body. To browse the catalogue, prefer code-snippets-list-snippets.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{id: int} $input
 * @return array<string, mixed>|WP_Error
 */
function code_snippets_get_snippet(array $input): array|WP_Error
{
    $id = $input['id'];
    if ($id <= 0) {
        return new WP_Error('cs_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    $snippet = cs_load_snippet($id);
    if ($snippet === null) {
        return new WP_Error('cs_not_found', sprintf(__('Snippet with id %d not found.', domain: 'novamira-pro'), $id));
    }
    return cs_snippet_to_array($snippet);
}
