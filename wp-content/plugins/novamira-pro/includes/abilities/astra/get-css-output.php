<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Astra;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: return the Astra dynamic CSS string for debug / preview purposes.
 */

wp_register_ability('novamira/astra-get-css-output', [
    'label' => __('Get Astra Dynamic CSS', domain: 'novamira-pro'),
    'description' => __(
        'Returns the CSS string Astra generates at runtime from `astra-settings` via `Astra_Dynamic_CSS::return_output()`. Useful for debugging why a style change is not landing on the front-end (e.g. a color or font rule that should have changed). `source` is `astra_dynamic_css` when the class is available, or `unavailable` on installs where the class is not loaded. `bytes` is the raw length before any truncation — the full CSS is returned without byte-capping since it is already a single atomic string.',
        domain: 'novamira-pro',
    ),
    'category' => 'astra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'css' => ['type' => 'string'],
            'source' => ['type' => 'string', 'enum' => ['astra_dynamic_css', 'unavailable']],
            'bytes' => ['type' => 'integer'],
        ],
        'required' => ['css', 'source', 'bytes'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Astra\\astra_get_css_output',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Debug tool — only call when investigating a styling issue (a setting that does not seem to render, a color that does not change). For day-to-day work, astra-get-settings is enough. When source=unavailable the Astra_Dynamic_CSS class was not loaded on this request; try calling from a front-end context.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{css: string, source: string, bytes: int}
 */
function astra_get_css_output(): array
{
    if (!class_exists('Astra_Dynamic_CSS') || !method_exists('Astra_Dynamic_CSS', 'return_output')) {
        return ['css' => '', 'source' => 'unavailable', 'bytes' => 0];
    }

    // Pass an empty string so Astra generates CSS from scratch (first arg is a
    // dynamic_css accumulator that callers may pre-populate; '' is the neutral value).
    $css = \Astra_Dynamic_CSS::return_output('');

    return ['css' => $css, 'source' => 'astra_dynamic_css', 'bytes' => strlen($css)];
}
