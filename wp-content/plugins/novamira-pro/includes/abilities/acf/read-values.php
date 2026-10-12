<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-read-values', [
    'label' => __('Read ACF Values', domain: 'novamira-pro'),
    'description' => __(
        'Reads ACF values from a target (post, user, term, comment, options page). If fields[] is omitted, returns all fields whose location rules apply to the target. format_values defaults to true (formatted: images as arrays with sizes, post objects expanded). Set to false for raw stored data (typically IDs). only_with_values: true skips fields whose stored value is empty. Note: get_field returns ACF default values for never-written fields, so a fresh post may appear pre-populated.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['post', 'user', 'term', 'comment', 'options']],
                    'id' => ['type' => ['integer', 'string']],
                ],
                'required' => ['type'],
                'additionalProperties' => false,
            ],
            'fields' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Optional list of field names or keys to read. Mixed allowed.',
            ],
            'only_with_values' => ['type' => 'boolean', 'default' => false],
            'format_values' => ['type' => 'boolean', 'default' => true],
        ],
        'required' => ['target'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => ['type' => 'object'],
            'values' => ['type' => 'object'],
        ],
        'required' => ['target', 'values'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_read_values',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to inspect what ACF data is stored on a target. fields[] can mix names and keys; keys are unambiguous, names may resolve via location rules to a different group when multiple groups share a name. For complex types (repeater/flexible/gallery/clone/group) the returned value reflects the entire array; modify a copy then write back via acf-write-values with mode:"replace" to avoid surprises.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{target: array{type: string, id?: int|string}, fields?: list<string>, only_with_values?: bool, format_values?: bool} $args
 * @return array{target: array<string, mixed>, values: array<string, mixed>|\stdClass}|WP_Error
 */
function acf_read_values(array $args): array|WP_Error
{
    if (!class_exists('ACF')) {
        return new WP_Error('acf_not_active', 'ACF is not active');
    }

    $target = $args['target'];
    $normalized = acf_normalize_target($target);
    if ($normalized instanceof WP_Error) {
        return $normalized;
    }

    $existence = acf_target_exists($target, $normalized);
    if ($existence instanceof WP_Error) {
        return $existence;
    }

    $format = $args['format_values'] ?? true;
    $only_with_values = $args['only_with_values'] ?? false;
    $requested = $args['fields'] ?? null;

    $raw = acf_collect_values($normalized, $requested, ['format' => $format]);
    $values = $only_with_values ? acf_filter_non_empty($raw) : $raw;

    // Cast to object so JSON serialization produces `{}` rather than `[]` for
    // an empty values map; the output schema declares `values` as an object.
    return [
        'target' => $target,
        'values' => $values === [] ? new \stdClass() : $values,
    ];
}

/**
 * Pull ACF values from the target. When $requested is null, returns every field
 * whose location rules apply via get_field_objects(); when it is a list of
 * selectors (field names or keys), fetches each one with get_field(). Options
 * is an associative array to avoid boolean flag parameters; currently
 * recognised: `format` (bool, default true) — corresponds to ACF's $format_value.
 *
 * @param list<string>|null $requested
 * @param array{format?: bool} $options
 * @return array<string, mixed>
 */
function acf_collect_values(string $normalized, ?array $requested, array $options): array
{
    $format = $options['format'] ?? true;
    $raw = [];

    if ($requested === null) {
        $objects = get_field_objects($normalized, $format);
        if (!is_array($objects)) {
            return $raw;
        }
        foreach ($objects as $name => $obj) {
            $raw[$name] = $obj['value'] ?? null;
        }
        return $raw;
    }

    foreach ($requested as $selector) {
        if ($selector === '') {
            continue;
        }
        $raw[$selector] = get_field($selector, $normalized, $format);
    }
    return $raw;
}

/**
 * @param array<string, mixed> $values
 * @return array<string, mixed>
 */
function acf_filter_non_empty(array $values): array
{
    return array_filter($values, static fn(mixed $value): bool => !acf_value_is_empty($value));
}

/**
 * @param array{type: string, id?: int|string} $target
 */
function acf_target_exists(array $target, string $normalized): ?WP_Error
{
    $type = $target['type'];
    $id = $target['id'] ?? null;

    if ($type === 'options') {
        return null;
    }

    $exists = match ($type) {
        'post' => get_post((int) $id) !== null,
        'user' => get_userdata((int) $id) !== false,
        'term' => acf_term_exists((int) $id),
        'comment' => get_comment((int) $id) !== null,
        default => null,
    };

    if ($exists === null) {
        return new WP_Error('target_invalid', "unknown target type: {$type}", ['target' => $target]);
    }

    if (!$exists) {
        return new WP_Error('target_not_found', "{$type} not found: {$id}", ['target' => $target]);
    }

    return null;
}

function acf_term_exists(int $id): bool
{
    $term = get_term($id);
    return $term !== null && !$term instanceof WP_Error;
}

function acf_value_is_empty(mixed $value): bool
{
    if ($value === null || $value === '' || $value === false) {
        return true;
    }
    if (is_array($value) && $value === []) {
        return true;
    }
    return false;
}
