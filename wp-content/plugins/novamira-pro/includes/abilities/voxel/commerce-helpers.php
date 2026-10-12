<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;
use WP_User;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Sprint 5 commerce READ abilities (orders, product
 * types, plans, roles, membership). Read-only: none of these touch Stripe or
 * mutate any row/option. Amounts are returned exactly as Voxel stores them in
 * the order `details` JSON (their currency's own unit) — never recomputed.
 */

/**
 * Normalize a stored monetary/quantity value to a number, or null when it is
 * missing or non-numeric. Never coerces a non-numeric string to 0.
 *
 * @param mixed $value
 */
function vx_money(mixed $value): int|float|null
{
    if (is_int($value) || is_float($value)) {
        return $value;
    }
    if (is_string($value) && is_numeric($value)) {
        return str_contains($value, '.') ? (float) $value : (int) $value;
    }
    return null;
}

/** A stored string value, or null when absent/non-string. */
function vx_str_or_null(mixed $value): ?string
{
    return is_string($value) ? $value : null;
}

/**
 * Resolve an order by id for the CURRENT test-mode. Returns a WP_Error when the
 * id is not a positive integer or no order matches (orders are mode-scoped, so
 * an order created in the other mode reads as not-found).
 *
 * @return \Voxel\Product_Types\Orders\Order|WP_Error a WP_Error (itself an object) on failure
 */
function vx_order_or_error(mixed $id): object
{
    $order_id = vx_coerce_int($id);
    if ($order_id === null || $order_id <= 0) {
        return vx_invalid(__('A valid order id is required.', domain: 'novamira-pro'));
    }
    $order = \Voxel\Product_Types\Orders\Order::get($order_id);
    if ($order === null) {
        // Order::get is mode-scoped. Distinguish "exists in the other payments
        // mode" from "never existed" so an agent handed a known id is not misled.
        if (vx_order_exists_any_mode($order_id)) {
            $mode = \Voxel\is_test_mode() ? __('test', domain: 'novamira-pro') : __('live', domain: 'novamira-pro');
            return vx_not_found('order', sprintf(
                /* translators: 1: order id, 2: current payments mode (test/live). */
                __('%1$s (it exists in the other payments mode; this store is in %2$s mode)', domain: 'novamira-pro'),
                (string) $order_id,
                $mode,
            ));
        }
        return vx_not_found('order', (string) $order_id);
    }
    return $order;
}

/**
 * Whether an order row with this id exists at all, ignoring the test-mode scope
 * (used only to make the not-found message mode-aware). Direct, prepared read.
 */
function vx_order_exists_any_mode(int $order_id): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    // $order_id is a typed int — safe to interpolate; avoids the prepare()
    // literal-string rule for the interpolated table prefix (matches S3 pattern).
    $found = $wpdb->get_var("SELECT id FROM {$wpdb->prefix}vx_orders WHERE id = {$order_id} LIMIT 1");
    return $found !== null;
}

/**
 * Compact row for one order (list output).
 *
 * @param \Voxel\Product_Types\Orders\Order $order
 * @return array<string, mixed>
 */
function vx_order_row(object $order): array
{
    $created = $order->get_created_at();
    return [
        'id' => $order->get_id(),
        'customer_id' => $order->get_customer_id(),
        // get_vendor_id() falls back to the site admin id when there is no real
        // vendor; gate on has_vendor() so a platform order reports null, not admin.
        'vendor_id' => $order->has_vendor() ? $order->get_vendor_id() : null,
        'status' => $order->get_status(),
        'status_label' => $order->get_status_label(),
        'shipping_status' => $order->get_shipping_status(),
        'payment_method' => $order->get_payment_method_key(),
        'total' => vx_money($order->get_total()),
        'currency' => vx_str_or_null($order->get_currency()),
        'item_count' => $order->get_item_count(),
        'test_mode' => $order->is_test_mode(),
        'created_at' => $created instanceof \DateTime ? $created->format('c') : null,
    ];
}

/**
 * Full order projection: the row plus opt-in sections. `include` ⊆ {items,
 * pricing, shipping, customer}. Customer/vendor are limited to id + display name
 * (no email); shipping/transaction id are only surfaced under their include.
 *
 * @param \Voxel\Product_Types\Orders\Order $order
 * @param list<string> $include
 * @return array<string, mixed>
 */
function vx_order_full(object $order, array $include): array
{
    $row = vx_order_row($order);
    $row['parent_order_id'] = $order->get_parent_order_id();
    // The gateway reference is a low-sensitivity id (not a secret); keep it in the
    // base response, decoupled from the shipping/PII bundle.
    $row['transaction_id'] = $order->get_transaction_id();

    if (in_array('items', $include, strict: true)) {
        $items = [];
        foreach ($order->get_items() as $item) {
            $items[] = vx_order_item_row($item);
        }
        $row['items'] = $items;
    }
    if (in_array('pricing', $include, strict: true)) {
        $row['pricing'] = vx_order_pricing($order);
    }
    if (in_array('customer', $include, strict: true)) {
        $row['customer'] = vx_user_brief($order->get_customer_id());
        $row['vendor'] = $order->has_vendor() ? vx_user_brief((int) $order->get_vendor_id()) : null;
    }
    if (in_array('shipping', $include, strict: true)) {
        $row['shipping'] = vx_order_shipping($order);
    }
    return $row;
}

/**
 * Curated shipping section: fulfillment status plus a COARSE destination
 * (city/state/zip/country). The full delivery name, street lines and phone are
 * deliberately NOT surfaced here — this is a read/inspection surface, and an
 * admin uses wp-admin for the full address. Never a raw pass-through of the
 * stored shipping node (which would leak name + street + phone).
 *
 * @param \Voxel\Product_Types\Orders\Order $order
 * @return array<string, mixed>
 */
function vx_order_shipping(object $order): array
{
    /** @var mixed $address */
    $address = $order->get_details('shipping.address');
    $address = is_array($address) ? $address : [];
    return [
        'shipping_status' => $order->get_shipping_status(),
        'destination' => [
            'city' => vx_str_or_null($address['city'] ?? null),
            'state' => vx_str_or_null($address['state'] ?? null),
            'zip' => vx_str_or_null($address['zip'] ?? null),
            'country' => vx_str_or_null($address['country'] ?? null),
        ],
    ];
}

/**
 * The order's pricing breakdown from its stored details, each value normalized.
 *
 * @param \Voxel\Product_Types\Orders\Order $order
 * @return array<string, mixed>
 */
function vx_order_pricing(object $order): array
{
    return [
        'currency' => vx_str_or_null($order->get_details('pricing.currency')),
        'subtotal' => vx_money($order->get_details('pricing.subtotal')),
        'total' => vx_money($order->get_details('pricing.total')),
        'tax' => vx_money($order->get_details('pricing.tax')),
        'discount' => vx_money($order->get_details('pricing.discount')),
        'shipping' => vx_money($order->get_details('pricing.shipping')),
    ];
}

/**
 * Compact row for one order item.
 *
 * @param \Voxel\Product_Types\Order_Items\Order_Item $item
 * @return array<string, mixed>
 */
function vx_order_item_row(object $item): array
{
    return [
        'id' => $item->get_id(),
        'post_id' => $item->get_post_id(),
        'product_type' => $item->get_product_type_key(),
        'field_key' => $item->get_product_field_key(),
        'label' => vx_str_or_null($item->get_product_label()),
        'quantity' => $item->get_quantity(),
        'subtotal' => vx_money($item->get_subtotal()),
        'currency' => vx_str_or_null($item->get_currency()),
    ];
}

/**
 * A minimal, non-PII user reference: id + display name (never email).
 *
 * @return array{id: int, display_name: string|null}
 */
function vx_user_brief(int $user_id): array
{
    $user = get_userdata($user_id);
    return [
        'id' => $user_id,
        'display_name' => $user instanceof WP_User ? $user->display_name : null,
    ];
}

/**
 * Compact row for one product type.
 *
 * @param \Voxel\Product_Type $product_type
 * @return array<string, mixed>
 */
function vx_product_type_row(object $product_type): array
{
    $config = $product_type->get_config();
    $fields = isset($config['fields']) && is_array($config['fields']) ? $config['fields'] : [];
    return [
        'key' => $product_type->get_key(),
        'label' => $product_type->get_label(),
        'product_mode' => $product_type->get_product_mode(),
        'fields_count' => count($fields),
        'supports_marketplace' => $product_type->supports_marketplace(),
    ];
}

/**
 * Compact row for one plan config entry.
 *
 * @param array<array-key, mixed> $plan
 * @return array<string, mixed>
 */
function vx_plan_row(array $plan): array
{
    $settings = isset($plan['settings']) && is_array($plan['settings']) ? $plan['settings'] : [];
    $roles = isset($settings['supported_roles']) && is_array($settings['supported_roles'])
        ? $settings['supported_roles']
        : [];
    return [
        'key' => vx_str_or_null($plan['key'] ?? null),
        'label' => vx_str_or_null($plan['label'] ?? null),
        'archived' => ($plan['archived'] ?? false) === true,
        'supported_roles' => array_values(array_filter($roles, callback: 'is_string')),
    ];
}

/**
 * Assemble the read-only membership view for a user. Curated: it returns the
 * membership type, active state, active plan (key + label), and the linking
 * order id — NOT the raw membership blob (which carries Stripe subscription/price
 * ids for paid members).
 *
 * Voxel's membership resolver lazily creates a persisted "default" plan (a
 * write) when the site has none. To keep this ability strictly read-only, the
 * resolver is used only when a default plan already exists; otherwise the raw
 * membership meta is read directly, with no write.
 *
 * @param \Voxel\User $voxel_user
 * @return array<string, mixed>
 */
function vx_membership_view(object $voxel_user, int $user_id): array
{
    /** @var mixed $plans */
    $plans = \Voxel\get('plans', []);
    if (is_array($plans) && isset($plans['default'])) {
        $membership = $voxel_user->get_membership();
        $plan = $membership->get_active_plan();
        return [
            'user_id' => $user_id,
            'type' => $membership->get_type(),
            'is_active' => $membership->is_active(),
            'plan_key' => $plan->get_key(),
            'plan_label' => $plan->get_label(),
            'test_mode' => \Voxel\is_test_mode(),
            'order_id' => vx_membership_order_id($membership->to_array()),
        ];
    }

    $details = vx_read_membership_meta($user_id);
    $type = is_string($details['type'] ?? null) ? $details['type'] : 'default';
    return [
        'user_id' => $user_id,
        'type' => $type,
        'is_active' => $type === 'default',
        'plan_key' => is_string($details['plan'] ?? null) ? $details['plan'] : null,
        'plan_label' => null,
        'test_mode' => \Voxel\is_test_mode(),
        'order_id' => vx_membership_order_id($details),
    ];
}

/**
 * Read the raw membership meta for the current payments mode (read-only), decoded
 * to an array. Used only by the no-default-plan fallback in vx_membership_view.
 *
 * @return array<array-key, mixed>
 */
function vx_read_membership_meta(int $user_id): array
{
    $meta_key = \Voxel\is_test_mode() ? 'voxel:test_plan' : 'voxel:plan';
    /** @var mixed $raw */
    $raw = \Voxel\get_site_specific_user_meta($user_id, $meta_key, single: true);
    /** @var mixed $decoded */
    $decoded = is_string($raw) ? json_decode($raw, associative: true) : null;
    return is_array($decoded) ? $decoded : [];
}

/**
 * The linking order id inside a membership detail blob, or null. Never surfaces
 * the raw Stripe subscription/price ids that may sit alongside it.
 *
 * @param array<array-key, mixed> $details
 */
function vx_membership_order_id(array $details): ?int
{
    $meta = isset($details['meta']) && is_array($details['meta']) ? $details['meta'] : [];
    /** @var mixed $order_id */
    $order_id = $meta['order_id'] ?? $details['order_id'] ?? null;
    return vx_coerce_int($order_id);
}

/**
 * Compact row for one role config entry.
 *
 * @param array<array-key, mixed> $role
 * @return array<string, mixed>
 */
function vx_role_row(array $role): array
{
    $settings = isset($role['settings']) && is_array($role['settings']) ? $role['settings'] : [];
    $registration = isset($role['registration']) && is_array($role['registration']) ? $role['registration'] : [];
    return [
        'key' => vx_str_or_null($settings['key'] ?? null),
        'label' => vx_str_or_null($settings['label'] ?? null),
        'registration_enabled' => ($registration['enabled'] ?? false) === true,
    ];
}
