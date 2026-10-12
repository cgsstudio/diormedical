<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

use Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/code-snippets-validate-snippet', [
    'label' => __('Validate Code Snippet', domain: 'novamira-pro'),
    'description' => __(
        'Lints a PHP snippet body for syntax errors WITHOUT saving or executing it. The check runs `token_get_all($source, TOKEN_PARSE)` over the prefixed source so PHP\'s parser walks every token (catching both ParseError and CompileError, which is what some PHP 8.4 constructs throw) but never invokes the runtime. Use this as a pre-flight step before code-snippets-enable-snippet. For non-PHP types (html / css / js / cond) the call returns `valid: true, skipped: true` immediately — `cond` bodies are JSON, not PHP, so running the parser on them would produce false negatives. To lint a snippet you have NOT saved yet, pass `code` directly; to lint an existing snippet, pass `id`.',
        domain: 'novamira-pro',
    ),
    'category' => 'code-snippets',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'id' => [
                'type' => 'integer',
                'description' => 'Snippet id whose stored code should be validated. Either `id` or `code` must be supplied; `code` wins when both are present.',
            ],
            'code' => [
                'type' => 'string',
                'description' => 'Snippet body to validate. Only PHP bodies are actually linted — html / css / js / cond return `skipped: true`. For PHP, do NOT include the leading <?php tag — the snippet engine appends it on its own.',
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Optional snippet type (php | html | css | js | cond). Defaults to php when `code` is supplied; defaults to the stored type when `id` is supplied.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'valid' => ['type' => 'boolean'],
            'type' => ['type' => 'string'],
            'message' => ['type' => 'string'],
            'line' => ['type' => 'integer'],
            'excerpt' => [
                'type' => 'string',
                'description' => 'Multi-line source excerpt centered on the offending line (previous + offending + next, "> " marker on the bad line). Present only when `valid: false`.',
            ],
            'skipped' => ['type' => 'boolean'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['valid'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\CodeSnippets\\code_snippets_validate_snippet',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run BEFORE code-snippets-enable-snippet on every PHP snippet you author. Recommended workflow: code-snippets-create-snippet (default inactive) → code-snippets-validate-snippet (catches syntax errors before they go live) → code-snippets-enable-snippet (only after the user confirms the code is what they want). For non-PHP types validate is a no-op that returns valid=true; you can still call it as a uniform "ready?" step in a programmatic loop.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function code_snippets_validate_snippet(array $input): array
{
    $resolved = cs_resolve_validate_input($input);
    if (isset($resolved['error'])) {
        return ['valid' => false, 'error' => $resolved['error']];
    }
    $type = $resolved['type'];
    $code = $resolved['code'];

    // `cond` bodies are JSON consumed by the Code Snippets Pro condition
    // evaluator, NOT PHP — running the parser on them produces false
    // negatives ("unexpected token \"{\"" on every well-formed condition).
    // Skip with the same shape as the other non-PHP types (Round 1 S5).
    if ($type !== 'php') {
        return ['valid' => true, 'type' => $type, 'skipped' => true];
    }

    return cs_php_parse_check($code, $type);
}

/**
 * Parses a PHP snippet body for syntax errors WITHOUT running its
 * statements. Uses `token_get_all($source, TOKEN_PARSE)` which since
 * PHP 7.0 throws `\ParseError` when the source is not syntactically
 * valid PHP, with no execution side effects (the tokeniser walks the
 * source and stops on the first invalid construct). This is strictly
 * a parser check — semantic errors like calling an undefined function
 * still slip through and only surface at activation time.
 *
 * @return array<string, mixed>
 */
function cs_php_parse_check(string $code, string $type): array
{
    try {
        $tokens = token_get_all('<?php ' . $code, TOKEN_PARSE);
        unset($tokens);
        return ['valid' => true, 'type' => $type];
    } catch (
        // token_get_all($s, TOKEN_PARSE) throws BOTH ParseError (most
        // syntactically invalid code) AND CompileError (some PHP 8.0+
        // constructs — e.g. asymmetric visibility, duplicate enum cases,
        // reserved keywords as class names). CompileError is a sibling
        // of ParseError under \Error, NOT a subclass — Round 1 audit S6
        // hit an uncaught CompileError and bubbled a 500. Catch the
        // common base \Error to cover both without enumerating future
        // engine additions, then re-raise anything that isn't a parse-
        // type error.
        // @mago-expect analysis:avoid-catching-error
        Error $e
    ) {
        // Be defensive — only treat true syntax/compile errors as
        // `valid: false`; any other Error (TypeError, AssertionError,
        // etc. — shouldn't be reachable here since we never execute,
        // but cheap to guard) re-throws up to the abilities runner.
        if (!$e instanceof \ParseError && !$e instanceof \CompileError) {
            throw $e;
        }
        $line = max(1, $e->getLine());
        return [
            'valid' => false,
            'type' => $type,
            'message' => rtrim($e->getMessage(), characters: '.') . '.',
            'line' => $line,
            'excerpt' => cs_source_excerpt($code, $line),
        ];
    }
}

/**
 * Builds a small source-excerpt for the validate-snippet error response.
 * Returns the offending line as the user sees it, padded with the
 * previous + next line (when present) so the agent has surrounding
 * context. Lines are 1-indexed. Each entry is "  $n: $text" for the
 * neighbours and "> $n: $text" for the offending line — same shape PHP's
 * own CLI uses for parse errors. Sub-agent CS2 asked for this to handle
 * multi-line snippets without mentally re-rendering the source.
 */
function cs_source_excerpt(string $code, int $error_line): string
{
    $lines = preg_split("/\r\n|\n|\r/", $code);
    if ($lines === false) {
        return '';
    }
    $total = count($lines);
    if ($total === 0) {
        return '';
    }
    $from = max(1, $error_line - 1);
    $to = min($total, $error_line + 1);
    $out = [];
    for ($n = $from; $n <= $to; $n++) {
        $marker = $n === $error_line ? '>' : ' ';
        $out[] = sprintf('%s %d: %s', $marker, $n, $lines[$n - 1]);
    }
    return implode("\n", $out);
}

/**
 * Resolves the validate-snippet input into a {type, code} pair, falling
 * back from `code` (preferred) to `id` lookup. Returns an `error` key
 * when neither input is usable.
 *
 * @param array<string, mixed> $input
 * @return array{type: string, code: string, error?: string}
 */
function cs_resolve_validate_input(array $input): array
{
    if (isset($input['code']) && is_string($input['code']) && $input['code'] !== '') {
        $type = isset($input['type']) && is_string($input['type']) ? $input['type'] : 'php';
        return ['type' => $type, 'code' => $input['code']];
    }

    if (isset($input['id']) && is_int($input['id']) && $input['id'] > 0) {
        $snippet = cs_load_snippet($input['id']);
        if ($snippet === null) {
            return ['type' => '', 'code' => '', 'error' => sprintf('Snippet with id %d not found.', $input['id'])];
        }
        $type = isset($input['type']) && is_string($input['type']) ? $input['type'] : $snippet->type;
        return ['type' => $type, 'code' => $snippet->code];
    }

    return ['type' => '', 'code' => '', 'error' => 'Either `id` or `code` must be supplied.'];
}
