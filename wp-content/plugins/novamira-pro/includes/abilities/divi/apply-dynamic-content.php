<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Ability: Bind a module field to a Divi 5 dynamic-content source.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/divi-apply-dynamic-content', [
    'label' => __('Apply Divi Dynamic Content', domain: 'novamira-pro'),
    'description' => __(
        'Binds a module field to a dynamic-content source so it resolves at render time (e.g. a heading to the post title, an image to the featured image). Identify the module by "address" (from novamira/divi-get-content) and the field by its attrName "field" (a field with dynamic:true from novamira/divi-get-module-schema; e.g. "content.innerContent", "title.innerContent"). For a structured field, pass "value_field" from that field\'s value_fields list to bind one scalar leaf (e.g. "linkUrl" for a button URL); unknown value fields are refused. When omitted, image values select "src", text-bearing values select "text", and simple fields use the whole value. "source" is an id from novamira/divi-list-dynamic-sources. Optional "settings" carries the source options shown by that ability\'s per-source detail. The token is written at <field>.<breakpoint>.value[.<value_field>]. Accepts the post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'address' => ['type' => 'string', 'description' => 'Module address, e.g. "0/0/0/0".'],
            'field' => ['type' => 'string', 'description' => 'Field attrName to bind, e.g. "content.innerContent".'],
            'value_field' => [
                'type' => 'string',
                'description' => 'Structured value leaf from the field\'s value_fields list, e.g. "linkUrl".',
            ],
            'source' => ['type' => 'string', 'description' => 'Dynamic source id, e.g. "post_title".'],
            'settings' => [
                'type' => 'object',
                'description' => 'Optional source settings (string values), e.g. {"before":"","after":"","meta_key":"…"}.',
            ],
            'breakpoint' => ['type' => 'string', 'description' => 'Responsive breakpoint (default "desktop").'],
        ],
        'required' => ['address', 'field', 'source'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'address' => ['type' => 'string'],
            'field' => ['type' => 'string'],
            'value_field' => ['type' => 'string'],
            'source' => ['type' => 'string'],
            'warning' => [
                'type' => 'string',
                'description' => 'Present when the binding may expose protected/internal data.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_apply_dynamic_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Bind a field to dynamic data instead of hardcoding it. Find a dynamic:true field attrName and its value_fields via novamira/divi-get-module-schema, and find the source id/settings via novamira/divi-list-dynamic-sources. Pass value_field to target a structured leaf such as linkUrl; omit it to select src for images or text for text-bearing values. Re-running with the same args is idempotent.',
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
function divi_apply_dynamic_content(array $input)
{
    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $address = divi_string_arg($input, key: 'address');
    $field = divi_string_arg($input, key: 'field');
    $source = divi_string_arg($input, key: 'source');
    if ($address === '' || $field === '' || $source === '') {
        return divi_error('invalid_input', message: '"address", "field", and "source" are all required.');
    }

    $valid_ids = divi_dynamic_option_ids($post->ID);
    if ($valid_ids !== [] && !in_array($source, $valid_ids, strict: true)) {
        return divi_error(
            'unknown_source',
            message: sprintf(
                'Unknown dynamic source "%s". Use novamira/divi-list-dynamic-sources to discover valid ids.',
                $source,
            ),
            status: 404,
        );
    }

    $settings = divi_dynamic_settings_arg($input);
    $value = divi_build_dynamic_value($source, $settings);
    $breakpoint = divi_string_arg($input, key: 'breakpoint', default: 'desktop');
    $value_field = divi_string_arg($input, key: 'value_field');

    $result = divi_set_field_value($post, $address, $field, $breakpoint, [
        'value' => $value,
        'value_field' => $value_field,
    ]);
    if ($result instanceof WP_Error) {
        return $result;
    }

    $out = ['post_id' => $post->ID, 'address' => $address, 'field' => $field, 'source' => $source];
    if (isset($result['value_field'])) {
        $out['value_field'] = $result['value_field'];
    }

    // Binding a custom meta_key bypasses Divi's own field-permission check and
    // get_post_meta has no protected-meta guard, so a protected ("_"-prefixed)
    // key would publish hidden internal data on the public frontend. Still allow
    // it (admin-only ability) but surface a warning so the operator is told.
    $meta_key = is_string($settings['meta_key'] ?? null) ? $settings['meta_key'] : '';
    if ($meta_key !== '' && is_protected_meta($meta_key)) {
        $out['warning'] = sprintf(
            'Meta key "%s" is protected (internal). This binding will publish its value on the public frontend.',
            $meta_key,
        );
    }

    return $out;
}
