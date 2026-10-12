<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\OceanWP;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oceanwp-set-woo-features', [
    'label' => __('Set OceanWP WooCommerce Feature Toggles', domain: 'novamira-pro'),
    'description' => __(
        'Narrow writer for the high-leverage WooCommerce theme_mods OceanWP exposes: off-canvas filter sidebar, distraction-free cart / checkout, quick view modal, floating add-to-cart bar, product hover image-swap, AJAX add-to-cart, menu cart icon style, mobile mini-cart. Requires WooCommerce active (returns `oceanwp_requires_woocommerce` when WC is inactive). Same lock + pre-validation + alias-aware error messages as `oceanwp-set-header-config`.',
        domain: 'novamira-pro',
    ),
    'category' => 'oceanwp',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'off_canvas_filter' => [
                'type' => 'boolean',
                'description' => 'Off-canvas product filter sidebar on shop archives.',
            ],
            'distraction_free_cart' => ['type' => 'boolean', 'description' => 'Hide header / footer on the cart page.'],
            'distraction_free_checkout' => [
                'type' => 'boolean',
                'description' => 'Hide header / footer on the checkout page.',
            ],
            'quick_view' => ['type' => 'boolean', 'description' => 'Product quick view modal on shop archives.'],
            'floating_bar' => [
                'type' => 'string',
                'enum' => ['enable', 'mobile', 'disable'],
                'description' => 'Sticky add-to-cart bar visibility on single product pages.',
            ],
            'products_style' => [
                'type' => 'string',
                'enum' => ['default', 'hover'],
                'description' => 'Product image hover behavior.',
            ],
            'ajax_add_to_cart' => ['type' => 'boolean', 'description' => 'AJAX add-to-cart on single product pages.'],
            'menu_cart_style' => [
                'type' => 'string',
                'enum' => ['icon', 'icon_count', 'icon_total', 'bag'],
                'description' => 'Menu cart icon style.',
            ],
            'mobile_mini_cart' => ['type' => 'boolean', 'description' => 'Mobile mini-cart bar.'],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'persisted' => ['type' => 'boolean'],
            'changed' => ['type' => 'array', 'items' => ['type' => 'string']],
            'unchanged' => ['type' => 'array', 'items' => ['type' => 'string']],
            'values' => [
                'type' => 'object',
                'description' => 'Post-write value of every accepted feature flag.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['persisted', 'changed', 'unchanged', 'values'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\OceanWP\\oceanwp_set_woo_features',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'WooCommerce feature toggles. Internally maps the 9 short aliases to their `ocean_woo_*` / `ocean_menu_cart_style` theme_mods. Always call `oceanwp-check-setup` first to confirm `integrations.woocommerce === true` — when WC is inactive this ability rejects.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{
 *   off_canvas_filter?: bool,
 *   distraction_free_cart?: bool,
 *   distraction_free_checkout?: bool,
 *   quick_view?: bool,
 *   floating_bar?: string,
 *   products_style?: string,
 *   ajax_add_to_cart?: bool,
 *   menu_cart_style?: string,
 *   mobile_mini_cart?: bool
 * } $input
 * @return array{
 *   persisted: bool,
 *   changed: list<string>,
 *   unchanged: list<string>,
 *   values: array<string, mixed>|\stdClass
 * }|WP_Error
 */
function oceanwp_set_woo_features(array $input): array|WP_Error
{
    $detected = ow_detect_setup();
    if (!$detected['active'] || !$detected['min_satisfied']) {
        return ow_not_active();
    }
    $integrations = ow_detect_integrations();
    if (!$integrations['woocommerce']) {
        // Dedicated env-gate code so the agent can distinguish "input
        // shape is wrong" from "this site has no WooCommerce". Audit
        // round 2 M#6.
        return ow_requires_woocommerce();
    }
    $aliases = [
        'off_canvas_filter' => 'ocean_woo_off_canvas_filter',
        'distraction_free_cart' => 'ocean_woo_distraction_free_cart',
        'distraction_free_checkout' => 'ocean_woo_distraction_free_checkout',
        'quick_view' => 'ocean_woo_quick_view',
        'floating_bar' => 'ocean_woo_display_floating_bar',
        'products_style' => 'ocean_woo_products_style',
        'ajax_add_to_cart' => 'ocean_woo_product_ajax_add_to_cart',
        'menu_cart_style' => 'ocean_menu_cart_style',
        'mobile_mini_cart' => 'ocean_woo_add_mobile_mini_cart',
    ];
    $alias_for = array_flip($aliases);
    /** @var array<string, mixed> $forwarded */
    $forwarded = [];
    foreach ($aliases as $short => $theme_mod_name) {
        if (!array_key_exists($short, $input)) {
            continue;
        }
        $forwarded[$theme_mod_name] = $input[$short];
    }
    if ($forwarded === []) {
        return ow_invalid_input(__(
            'No Woo feature flags supplied. Pass at least one of: off_canvas_filter, distraction_free_cart, distraction_free_checkout, quick_view, floating_bar, products_style, ajax_add_to_cart, menu_cart_style, mobile_mini_cart.',
            domain: 'novamira-pro',
        ));
    }

    $rejections = ow_prevalidate_customizer_writes($forwarded);
    if ($rejections !== []) {
        return ow_format_alias_rejections(
            $rejections,
            $alias_for,
            __('Woo feature flags rejected —', domain: 'novamira-pro'),
        );
    }

    $result = oceanwp_set_customizer_settings(['settings' => $forwarded]);
    if ($result instanceof WP_Error) {
        return $result;
    }
    // Translate every output back through the alias map so the success
    // path's vocabulary matches the input schema's. Without this, callers
    // get back `changed: ['ocean_woo_off_canvas_filter']` for an input of
    // `{off_canvas_filter: true}` — internal-name leak. Audit round 2 M#7.
    return ow_translate_result_to_aliases($result, $alias_for);
}
