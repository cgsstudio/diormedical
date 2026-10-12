<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Ability: Disable the query loop on a module.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-disable-loop', [
    'label' => __('Disable Divi Loop', domain: 'novamira-pro'),
    'description' => __(
        'Turns off the query loop on the module at "address" (sets module.advanced.loop.enable to "off"), leaving the rest of the query configuration in place so it can be re-enabled. Accepts the post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'address' => ['type' => 'string', 'description' => 'Module address, e.g. "0/0/0".'],
        ],
        'required' => ['address'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'address' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_disable_loop',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Switches a loop off without discarding its query settings. Re-enable with novamira/divi-enable-loop.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_disable_loop(array $input)
{
    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $address = divi_string_arg($input, key: 'address');
    if (trim($address) === '') {
        return divi_error('invalid_input', message: 'An "address" is required.');
    }

    $node = divi_loop_node_or_error($post, $address);
    if ($node instanceof WP_Error) {
        return $node;
    }

    // A loop can be enabled on any breakpoint; turn off every one that is on,
    // not just desktop, or divi_attrs_have_loop would still report it active.
    /** @var mixed $loop */
    $loop = $node['attrs']['module']['advanced']['loop'] ?? [];
    $breakpoints = is_array($loop) ? array_keys($loop) : [];
    foreach ($breakpoints as $breakpoint) {
        if (!is_string($breakpoint)) {
            continue;
        }
        /** @var mixed $enable */
        $enable = is_array($loop) && is_array($loop[$breakpoint] ?? null)
            ? $loop[$breakpoint]['value']['enable'] ?? null
            : null;
        if ($enable !== '1' && $enable !== true && $enable !== 'on') {
            continue;
        }
        $result = divi_set_field_value(
            $post,
            $address,
            field: 'module.advanced.loop',
            breakpoint: $breakpoint,
            write: ['value' => ['enable' => 'off']],
        );
        if ($result instanceof WP_Error) {
            return $result;
        }
    }

    return ['post_id' => $post->ID, 'address' => $address];
}
