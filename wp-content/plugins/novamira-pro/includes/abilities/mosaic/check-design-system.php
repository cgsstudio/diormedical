<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// check-design-system
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-check-design-system', [
    'label' => __('Check Mosaic Design-System Compliance', domain: 'novamira-pro'),
    'description' => __(
        'Audits every element of a Mosaic document for design-system compliance — whether the page is styled through Mosaic\'s OWN design system (Classes and Variants attached to elements, theme variables) rather than custom CSS baked in from outside it. It flags an element whose inline `style` hardcodes a colour (`#hex`, `rgb()`, `hsl()`) or a `font-family` literal instead of referencing a theme variable (`var(--token)`), an inline style that uses neither a variable nor a Class or Variant, and a Mosaic Class or Variant name written into the element\'s foreign class list (`css_classes`), where Mosaic renders it without its CSS. Returns a COMPACT report: summary counts (total / compliant / flagged nodes; how many elements attach Classes, select a Variant or reference variables; a count per violation type) plus the FLAGGED nodes only — each with its id, element type, the offending inline style (truncated), the misplaced class names and the violations — so an agent can fix them: swap a literal for a `var(--token)` (mosaic-list-variables), move a rule into a Class (mosaic-create-class, then attach it with `classes`), or move a misplaced Mosaic class name from `css_classes` to `classes` / `variant`. Compliant nodes are summarised, not listed. Read-only; run it after building or editing a page.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'document_type' => [
                'type' => 'string',
                'enum' => ['template', 'master', 'styleGuide', 'component'],
                'description' => 'Document kind — same set as mosaic-get-element-tree.',
            ],
            'document_id' => [
                'type' => 'string',
                'description' => 'Document UUID (e.g. a template id from mosaic-list-templates).',
            ],
            'max_nodes' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 5000,
                'default' => 2000,
                'description' => 'Cap on nodes scanned (BFS from the root). When it fires, `truncated` is true and the deepest layer is skipped.',
            ],
        ],
        'required' => ['document_type', 'document_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'document_type' => ['type' => 'string'],
            'document_id' => ['type' => 'string'],
            'total_nodes' => ['type' => 'integer'],
            'compliant_nodes' => ['type' => 'integer'],
            'flagged_nodes' => ['type' => 'integer'],
            'using_classes' => ['type' => 'integer'],
            'using_variants' => ['type' => 'integer'],
            'using_variable_refs' => ['type' => 'integer'],
            'violations_by_type' => [
                'type' => 'object',
                'properties' => [
                    'hardcoded-color' => ['type' => 'integer'],
                    'hardcoded-font' => ['type' => 'integer'],
                    'inline-style-no-tokens' => ['type' => 'integer'],
                    'mosaic-class-in-css-classes' => ['type' => 'integer'],
                ],
                'additionalProperties' => false,
            ],
            'flagged' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'inline_style' => ['type' => 'string'],
                        'uses_classes' => ['type' => 'boolean'],
                        'uses_variant' => ['type' => 'boolean'],
                        'uses_variable_ref' => ['type' => 'boolean'],
                        'misplaced_css_classes' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'violations' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ],
                    'required' => [
                        'id',
                        'type',
                        'inline_style',
                        'uses_classes',
                        'uses_variant',
                        'uses_variable_ref',
                        'misplaced_css_classes',
                        'violations',
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'truncated' => ['type' => 'boolean'],
        ],
        'required' => [
            'document_type',
            'document_id',
            'total_nodes',
            'compliant_nodes',
            'flagged_nodes',
            'using_classes',
            'using_variants',
            'using_variable_refs',
            'violations_by_type',
            'flagged',
            'truncated',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_check_design_system',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only design-system audit. Run after building/editing a page. Fix hardcoded literals with var(--token) (mosaic-list-variables) or a Class attached through `classes`; move any misplaced_css_classes entry from `css_classes` to `classes` (Classes) or `variant` (Variants).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// Callback
// ---------------------------------------------------------------------------

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_check_design_system(array $input): array|WP_Error
{
    // The audit reasons about how Classes and Variants are attached, so it
    // needs the current class model (see class-model.php).
    $err = mo_require_runtime() ?? mo_require_class_model();
    if ($err !== null) {
        return $err;
    }
    $document_type = is_string($input['document_type'] ?? null) ? $input['document_type'] : '';
    $document_id = mo_norm_uuid($input['document_id'] ?? null);
    if ($document_id === '' || mo_root_parent_type($document_type) === null) {
        return mo_error('mo_invalid_input', __('document_type + document_id are required.', domain: 'novamira-pro'));
    }
    $cap = max(1, min(5000, is_int($input['max_nodes'] ?? null) ? $input['max_nodes'] : 2000));
    $rows = mo_fetch_document_nodes_bfs($document_type, $document_id, $cap + 1);
    $truncated = count($rows) > $cap;
    if ($truncated) {
        $rows = array_slice($rows, offset: 0, length: $cap);
    }
    $theme_id = mo_document_theme_id($document_type, $document_id) ?? '';
    $owned = $theme_id !== '' ? mo_mosaic_owned_class_names(mo_class_index($theme_id)) : [];

    $total = count($rows);
    $summary = mo_ds_summarize($rows, $owned);
    return [
        'document_type' => $document_type,
        'document_id' => $document_id,
        'total_nodes' => $total,
        'compliant_nodes' => $summary['compliant'],
        'flagged_nodes' => $total - $summary['compliant'],
        'using_classes' => $summary['using_classes'],
        'using_variants' => $summary['using_variants'],
        'using_variable_refs' => $summary['using_vars'],
        'violations_by_type' => $summary['by_type'],
        'flagged' => $summary['flagged'],
        'truncated' => $truncated,
    ];
}

/**
 * Tally per-node audits into the report's summary fields. The violation map is
 * pre-seeded so it always encodes as a JSON object (an empty PHP array would
 * encode as a list and fail the output schema), and the detail list is capped
 * — the counts still cover every node.
 *
 * @param  list<array<string, mixed>>        $rows
 * @param  array<string, 'classes'|'variant'> $owned
 * @return array{compliant:int, using_classes:int, using_variants:int, using_vars:int, by_type:array<string, int>, flagged:list<array<string, mixed>>}
 */
function mo_ds_summarize(array $rows, array $owned): array
{
    $by_type = [
        'hardcoded-color' => 0,
        'hardcoded-font' => 0,
        'inline-style-no-tokens' => 0,
        'mosaic-class-in-css-classes' => 0,
    ];
    $compliant = 0;
    $using_classes = 0;
    $using_variants = 0;
    $using_vars = 0;
    $flagged = [];
    foreach ($rows as $row) {
        $report = mo_ds_audit_node($row, $owned);
        $using_classes += $report['uses_classes'] ? 1 : 0;
        $using_variants += $report['uses_variant'] ? 1 : 0;
        $using_vars += $report['uses_variable_ref'] ? 1 : 0;
        if ($report['violations'] === []) {
            $compliant++;
            continue;
        }
        foreach ($report['violations'] as $v) {
            $by_type[$v] = ($by_type[$v] ?? 0) + 1;
        }
        if (count($flagged) < 200) {
            $flagged[] = $report;
        }
    }
    return [
        'compliant' => $compliant,
        'using_classes' => $using_classes,
        'using_variants' => $using_variants,
        'using_vars' => $using_vars,
        'by_type' => $by_type,
        'flagged' => $flagged,
    ];
}

// ---------------------------------------------------------------------------
// Audit helpers
// ---------------------------------------------------------------------------

/**
 * Audit one node row. Design-system usage is read where Mosaic keeps it:
 * attached Classes in `data.style.universalClasses`, the selected Variant in
 * `data.style.variant` (see class-model.php). `data.cssClasses` holds foreign
 * class strings and does NOT count as design-system usage; a Mosaic-owned
 * name found there (including the legacy alias a migrated Class renders) is
 * reported, because Mosaic renders it without the Class's CSS.
 *
 * @param  array<string, mixed>              $row
 * @param  array<string, 'classes'|'variant'> $owned
 * @return array{id:string, type:string, inline_style:string, uses_classes:bool, uses_variant:bool, uses_variable_ref:bool, misplaced_css_classes:list<string>, violations:list<string>}
 */
function mo_ds_audit_node(array $row, array $owned): array
{
    $data = mo_decode_data($row['data'] ?? null);
    $inline = mo_ds_style_value($data);
    /** @var mixed $style */
    $style = $data['style'] ?? null;
    $style = is_array($style) ? $style : [];
    $uses_classes = is_array($style['universalClasses'] ?? null) && $style['universalClasses'] !== [];
    $uses_variant = is_string($style['variant'] ?? null) && $style['variant'] !== '';
    $uses_var = mo_ds_has_var_ref($inline);
    $misplaced = mo_ds_misplaced_css_classes($data['cssClasses'] ?? null, $owned);
    $violations = mo_ds_violations($inline, [
        'uses_tokens' => $uses_classes || $uses_variant || $uses_var,
        'misplaced' => $misplaced,
    ]);

    return [
        'id' => mo_str($row, key: 'ID'),
        'type' => mo_str($row, key: 'type'),
        'inline_style' => mb_substr($inline, start: 0, length: 200),
        'uses_classes' => $uses_classes,
        'uses_variant' => $uses_variant,
        'uses_variable_ref' => $uses_var,
        'misplaced_css_classes' => $misplaced,
        'violations' => $violations,
    ];
}

/**
 * @param  array{uses_tokens:bool, misplaced:list<string>} $usage
 * @return list<string>
 */
function mo_ds_violations(string $inline, array $usage): array
{
    // Strip variable references before hunting for literals so a var(--brand)
    // never reads as a hardcoded colour.
    $literal = mo_ds_strip_var_refs($inline);
    $checks = [
        'hardcoded-color' => mo_ds_has_hardcoded_color($literal),
        'hardcoded-font' => mo_ds_has_font_literal($literal),
        'inline-style-no-tokens' => trim($inline) !== '' && !$usage['uses_tokens'],
        'mosaic-class-in-css-classes' => $usage['misplaced'] !== [],
    ];
    return array_keys(array_filter($checks));
}

/**
 * @param  array<string, 'classes'|'variant'> $owned
 * @return list<string>
 */
function mo_ds_misplaced_css_classes(mixed $records, array $owned): array
{
    if (!is_array($records) || $owned === []) {
        return [];
    }
    $misplaced = [];
    /** @var mixed $record */
    foreach ($records as $record) {
        $name = is_array($record) && is_string($record['className'] ?? null) ? trim($record['className']) : '';
        if ($name !== '' && isset($owned[$name])) {
            $misplaced[] = $name;
        }
    }
    return $misplaced;
}

/**
 * Pull the inline-style value out of a node's Mosaic-native `attributes`
 * array (the record whose `name` is `style`).
 *
 * @param array<array-key, mixed> $data
 */
function mo_ds_style_value(array $data): string
{
    /** @var mixed $attributes */
    $attributes = $data['attributes'] ?? null;
    if (!is_array($attributes)) {
        return '';
    }
    /** @var mixed $record */
    foreach ($attributes as $record) {
        if (is_array($record) && ($record['name'] ?? null) === 'style' && is_string($record['value'] ?? null)) {
            return $record['value'];
        }
    }
    return '';
}

function mo_ds_strip_var_refs(string $style): string
{
    $stripped = preg_replace('/var\(\s*--[^)]*\)/i', replacement: ' ', subject: $style);
    $stripped = is_string($stripped) ? $stripped : $style;
    $stripped = preg_replace('/@VAR\([^)]*\)/i', replacement: ' ', subject: $stripped);
    return is_string($stripped) ? $stripped : $style;
}

function mo_ds_has_var_ref(string $style): bool
{
    return preg_match('/var\(\s*--|@VAR\(/i', $style) === 1;
}

function mo_ds_has_hardcoded_color(string $style): bool
{
    return (
        preg_match('/#[0-9a-fA-F]{3,8}\b/', $style) === 1
        || preg_match('/\b(?:rgb|rgba|hsl|hsla)\s*\(/i', $style) === 1
    );
}

function mo_ds_has_font_literal(string $style): bool
{
    return preg_match('/font-family\s*:/i', $style) === 1;
}
