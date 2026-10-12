<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

if (!ff_pro_runtime_available()) {
    return;
}

wp_register_ability('novamira/fluentforms-cancel-subscription', [
    'label' => __('Cancel a Fluent Forms subscription', domain: 'novamira-pro'),
    'description' => __(
        "Cancel an active Fluent Forms Pro subscription. Required: subscription_id. The ability resolves the subscription, dispatches to the vendor handler (Stripe / PayPal / Square / RazorPay / …) via the payment-method registry, and marks the row status='cancelled' on success. Returns fluentforms_subscription_not_found (404) for missing ids; fluentforms_pro_integration_missing when the vendor handler class isn't loaded; fluentforms_operation_failed when the vendor API rejects the cancel. Capability: fluentform_manage_payments. There is NO undo — once vendor-cancelled the subscription stops billing on the vendor side.",
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'subscription_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['subscription_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'subscription_id' => ['type' => 'integer'],
            'previous_status' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'vendor' => ['type' => 'string'],
        ],
        'required' => ['success', 'subscription_id', 'previous_status', 'status', 'vendor'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_cancel_subscription',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only, destructive (vendor-side billing stops). Capability: fluentform_manage_payments. Always confirm with the user before invoking — there is no undo path.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 *
 * @return array{
 *     success: bool,
 *     subscription_id: int,
 *     previous_status: string,
 *     status: string,
 *     vendor: string,
 * }|WP_Error
 */
function fluentforms_cancel_subscription(array $input): array|WP_Error
{
    $pro = ff_pro_required('subscriptions');
    if (!ff_pro_runtime_available()) {
        return $pro;
    }
    $sub = ff_subscription_or_error($input['subscription_id'] ?? null);
    if ($sub instanceof WP_Error) {
        return $sub;
    }
    $cap = ff_capability_or_error('fluentform_manage_payments', form_id: ff_row_int($sub, column: 'form_id'));
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $sub_id = ff_row_id($sub);
    $previous_status = ff_row_str($sub, column: 'status');
    $vendor = ff_cancel_subscription_vendor($sub);

    // If the row is already in a terminal state, treat the call as a no-op
    // success (idempotent: re-cancelling an already-cancelled subscription
    // should not 500). Vendor-side billing has already stopped.
    if (in_array($previous_status, ['cancelled', 'canceled', 'expired'], strict: true)) {
        return [
            'success' => true,
            'subscription_id' => $sub_id,
            'previous_status' => $previous_status,
            'status' => $previous_status,
            'vendor' => $vendor,
        ];
    }

    $vendor_result = ff_cancel_subscription_dispatch_vendor($sub, $vendor);
    if ($vendor_result instanceof WP_Error) {
        return $vendor_result;
    }

    // Mark the row cancelled locally via wpFluent (FF Pro has no Eloquent
    // Model for subscriptions). The local mirror has to converge even when
    // the vendor result was a no-op (offline / unknown vendor).
    $new_status = 'cancelled';
    try {
        wpFluent()->table('fluentform_subscriptions')->where('id', $sub_id)->update(['status' => $new_status]);
    } catch (Throwable $e) {
        return ff_operation_failed('Vendor cancelled but local update failed: ' . $e->getMessage());
    }
    do_action('fluentform/subscription_status_updated', $sub_id, $new_status, $previous_status);

    return [
        'success' => true,
        'subscription_id' => $sub_id,
        'previous_status' => $previous_status,
        'status' => 'cancelled',
        'vendor' => $vendor,
    ];
}

/**
 * Identify the vendor for a subscription. FF stores it implicitly via the
 * parent transaction's payment_method — we look it up via the
 * parent_transaction_id pointer when present.
 */
function ff_cancel_subscription_vendor(\stdClass $sub): string
{
    $parent_id = ff_row_int($sub, column: 'parent_transaction_id');
    if ($parent_id <= 0 || !function_exists('wpFluent')) {
        return '';
    }
    $row = wpFluent()->table('fluentform_transactions')->where('id', $parent_id)->first();
    if (!$row instanceof \stdClass) {
        return '';
    }
    return ff_row_str($row, column: 'payment_method');
}

/**
 * Dispatch the cancellation to the vendor handler. Each handler lives at
 * \FluentFormPro\Payments\PaymentMethods\<Stripe|PayPal|Square|RazorPay|
 * Mollie|Paystack>\<Vendor>Handler. We probe class_exists + a known
 * cancel method to stay compatible with FF release variations.
 */
function ff_cancel_subscription_dispatch_vendor(\stdClass $sub, string $vendor): bool|WP_Error
{
    if ($vendor === '') {
        // No vendor link — likely an offline / manual subscription. The
        // local-side cancel is enough.
        return true;
    }
    $class = ff_cancel_subscription_handler_class($vendor);
    if ($class === '' || !class_exists($class)) {
        return new WP_Error(
            'fluentforms_pro_integration_missing',
            sprintf("Cancel handler for '%s' is not loaded.", $vendor),
            ['status' => 400],
        );
    }
    if (!method_exists($class, 'cancelSubscription')) {
        return new WP_Error(
            'fluentforms_pro_integration_missing',
            sprintf("Vendor '%s' has no cancelSubscription() method in this FF release.", $vendor),
            ['status' => 400],
        );
    }
    try {
        $invoked = ff_invoke_vendor_cancel($class, $sub);
        if ($invoked instanceof WP_Error) {
            return $invoked;
        }
        return true;
    } catch (Throwable $e) {
        return ff_operation_failed(sprintf("Vendor '%s' rejected the cancel: %s", $vendor, $e->getMessage()));
    }
}

/**
 * Invoke the vendor cancelSubscription via Reflection — keeps the
 * dynamic-class machinery in one audited helper and gives mago a
 * statically narrow surface (no untyped callable arrays in the abilities
 * file).
 *
 * @param class-string $class
 */
function ff_invoke_vendor_cancel(string $class, \stdClass $sub): ?WP_Error
{
    try {
        $rc = new \ReflectionClass($class);
        $method = $rc->getMethod('cancelSubscription');
        if ($method->isStatic()) {
            $method->invoke(null, $sub);
            return null;
        }
        if ($rc->isAbstract() || !$rc->isInstantiable()) {
            return new WP_Error(
                'fluentforms_pro_integration_missing',
                sprintf("Vendor handler '%s' is not instantiable.", $class),
                ['status' => 400],
            );
        }
        $instance = $rc->newInstance();
        $method->invoke($instance, $sub);
        return null;
    } catch (\ReflectionException $e) {
        return new WP_Error(
            'fluentforms_pro_integration_missing',
            sprintf('Vendor handler reflection failed: %s', $e->getMessage()),
            ['status' => 400],
        );
    }
}

/**
 * Map a vendor slug (the value stored in transactions.payment_method) to
 * the FF Pro PaymentManager class that ships `cancelSubscription()`. The
 * mapping was wrong in the first draft (round-4 audit CRITICAL) — FF Pro
 * does NOT use `*Handler::cancelSubscription`; the method lives on the
 * per-vendor `*PaymentManager` (Stripe / PayPal) or the generic
 * `\FluentFormPro\Payments\Classes\PaymentManagement` fallback that
 * vendors without a dedicated PaymentManager extend.
 *
 * Returns '' when the vendor is unknown.
 *
 * @return class-string|string
 */
function ff_cancel_subscription_handler_class(string $vendor): string
{
    return match (strtolower($vendor)) {
        'stripe' => '\\FluentFormPro\\Payments\\PaymentMethods\\Stripe\\PaymentManager',
        'paypal' => '\\FluentFormPro\\Payments\\PaymentMethods\\PayPal\\PayPalPaymentManager',
        // Square, RazorPay, Mollie, Paystack and any other vendor without
        // a dedicated PaymentManager class fall back to the generic base
        // — it can cancel locally even when the vendor-side REST API is
        // missing.
        'square',
        'razorpay',
        'mollie',
        'paystack',
        'offline',
            => '\\FluentFormPro\\Payments\\Classes\\PaymentManagement',
        default => '',
    };
}
