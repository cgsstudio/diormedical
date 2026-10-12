<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

if (!ff_pro_runtime_available()) {
    return;
}

wp_register_ability('novamira/fluentforms-get-subscription', [
    'label' => __('Get a Fluent Forms subscription', domain: 'novamira-pro'),
    'description' => __(
        "Fetch one Fluent Forms Pro subscription by id. Returns the compact row PLUS note (admin notes), original_plan (raw vendor plan), vendor_response (the vendor's last API payload — opaque JSON). Returns fluentforms_subscription_not_found (404) for missing ids. Capability: fluentform_view_payments.",
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => ['subscription_id' => ['type' => 'integer', 'minimum' => 1]],
        'required' => ['subscription_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['subscription' => ['type' => 'object']],
        'required' => ['subscription'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_get_subscription',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. Capability: fluentform_view_payments. To cancel use novamira/fluentforms-cancel-subscription.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{subscription: array<string, mixed>}|WP_Error
 */
function fluentforms_get_subscription(array $input): array|WP_Error
{
    $pro = ff_pro_required('subscriptions');
    if (!ff_pro_runtime_available()) {
        return $pro;
    }
    $sub = ff_subscription_or_error($input['subscription_id'] ?? null);
    if ($sub instanceof WP_Error) {
        return $sub;
    }
    $cap = ff_capability_or_error('fluentform_view_payments', form_id: ff_row_int($sub, column: 'form_id'));
    if ($cap instanceof WP_Error) {
        return $cap;
    }
    $payload = ff_subscription_row($sub);
    $payload['note'] = ff_row_str($sub, column: 'note');
    $payload['original_plan'] = ff_row_str($sub, column: 'original_plan');
    $payload['vendor_response'] = ff_row_str($sub, column: 'vendor_response');
    return ['subscription' => $payload];
}
