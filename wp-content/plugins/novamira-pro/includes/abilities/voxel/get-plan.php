<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read one Voxel membership plan (read-only, opt-in detail).
 */

wp_register_ability('novamira/voxel-get-plan', [
    'label' => __('Get Voxel Plan', domain: 'novamira-pro'),
    'description' => __(
        'Read one Voxel membership plan by key. Returns the summary (key, label, description, archived, supported roles); pass include with "pricing" for the price tiers and/or "submissions" for the per-post-type submission limits. Read-only. Pricing is linked to Stripe and is shown for reference only.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'plan' => ['type' => 'string', 'description' => 'The plan key (e.g. "default", "premium").'],
            'include' => [
                'type' => 'array',
                'items' => ['type' => 'string', 'enum' => ['pricing', 'submissions']],
                'description' => 'Optional sections to embed.',
            ],
        ],
        'required' => ['plan'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => ['string', 'null']],
            'label' => ['type' => ['string', 'null']],
            'description' => ['type' => ['string', 'null']],
            'archived' => ['type' => 'boolean'],
            'supported_roles' => ['type' => 'array', 'items' => ['type' => 'string']],
            'pricing' => ['type' => 'object', 'additionalProperties' => true],
            'submissions' => ['type' => 'object', 'additionalProperties' => true],
        ],
        'required' => ['key', 'label', 'description', 'archived', 'supported_roles'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_get_plan',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read-only. include: pricing (Stripe-linked, reference only), submissions.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function voxel_get_plan(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['plan'] ?? null) ? $input['plan'] : '';

    /** @var mixed $plans */
    $plans = \Voxel\get('plans');
    if (!is_array($plans) || !isset($plans[$key]) || !is_array($plans[$key])) {
        return vx_not_found('plan', $key);
    }
    $plan = $plans[$key];

    $row = vx_plan_row($plan);
    $row['description'] = vx_str_or_null($plan['description'] ?? null);
    $include = vx_coerce_string_list($input['include'] ?? []);

    if (in_array('pricing', $include, strict: true)) {
        $row['pricing'] = isset($plan['pricing']) && is_array($plan['pricing']) ? $plan['pricing'] : [];
    }
    if (in_array('submissions', $include, strict: true)) {
        $row['submissions'] = isset($plan['submissions']) && is_array($plan['submissions']) ? $plan['submissions'] : [];
    }

    return $row;
}
