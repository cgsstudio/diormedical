<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Ability: Clear a module field's dynamic binding, reverting it to a static value.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-clear-dynamic-content', [
    'label' => __('Clear Divi Dynamic Content', domain: 'novamira-pro'),
    'description' => __(
        'Removes a dynamic-content binding from a module field at "address"/"field" (the same ones used with novamira/divi-apply-dynamic-content). For a structured field, pass the same "value_field" from its value_fields list; when omitted, image values select "src" and text-bearing values select "text". If you omit "value", only the dynamic $variable(...) token is stripped — surrounding literal text is preserved (a whole-leaf binding clears to empty); pass "value" to set an explicit static replacement instead. Operates on one breakpoint (default "desktop"). Accepts the post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'address' => ['type' => 'string', 'description' => 'Module address, e.g. "0/0/0/0".'],
            'field' => ['type' => 'string', 'description' => 'Field attrName to clear, e.g. "content.innerContent".'],
            'value_field' => [
                'type' => 'string',
                'description' => 'Structured value leaf used when applying the binding, e.g. "linkUrl".',
            ],
            'value' => ['type' => 'string', 'description' => 'Static value to set instead (default "").'],
            'breakpoint' => ['type' => 'string', 'description' => 'Responsive breakpoint (default "desktop").'],
        ],
        'required' => ['address', 'field'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'address' => ['type' => 'string'],
            'field' => ['type' => 'string'],
            'value_field' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_clear_dynamic_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reverts a dynamically-bound field back to a static value. Pass the same value_field used to bind a structured leaf. Pass "value" for the replacement text, or omit it to clear the binding.',
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
function divi_clear_dynamic_content(array $input)
{
    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $address = divi_string_arg($input, key: 'address');
    $field = divi_string_arg($input, key: 'field');
    if ($address === '' || $field === '') {
        return divi_error('invalid_input', message: '"address" and "field" are required.');
    }

    $breakpoint = divi_string_arg($input, key: 'breakpoint', default: 'desktop');
    $value_field = divi_string_arg($input, key: 'value_field');
    $value = divi_clear_replacement($post, $address, $field, $input);

    $result = divi_set_field_value($post, $address, $field, $breakpoint, [
        'value' => $value,
        'value_field' => $value_field,
    ]);
    if ($result instanceof WP_Error) {
        return $result;
    }

    $out = ['post_id' => $post->ID, 'address' => $address, 'field' => $field];
    if (isset($result['value_field'])) {
        $out['value_field'] = $result['value_field'];
    }
    return $out;
}

/**
 * Decide the static value to write when clearing a dynamic binding. An explicit
 * "value" wins; otherwise strip only the `$variable(...)` token(s) from the
 * current value so surrounding literal text (e.g. "Price: …") is preserved, and
 * a whole-field JSON binding clears to empty.
 *
 * @param array<string, mixed> $input
 */
function divi_clear_replacement(\WP_Post $post, string $address, string $field, array $input): string
{
    if (array_key_exists('value', $input)) {
        return divi_string_arg($input, key: 'value');
    }

    $current = divi_read_field_value(
        $post,
        $address,
        $field,
        divi_string_arg($input, key: 'breakpoint', default: 'desktop'),
        divi_string_arg($input, key: 'value_field'),
    );
    if (!is_string($current)) {
        return '';
    }
    if (str_contains($current, '$variable(')) {
        return trim(divi_strip_dynamic_tokens($current));
    }
    if (str_starts_with(ltrim($current), '{"type":"content"')) {
        return '';
    }
    return $current;
}
