<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-apply-global-class', [
    'label' => __('Apply Oxygen Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Attaches an existing Oxygen 6 native selector of type `class` to an element by adding its stable id to `properties.meta.classes`, the binding path the Oxygen renderer consumes. Custom CSS selectors are stylesheet rules and cannot be attached as class tokens. Pass `class_id` (preferred) or `class_name`; names are resolved to ids before write. Idempotent: an existing id is a no-op. The attach is GATED when the element already stores executable per-selector conditions for that selector at `properties.meta.classes_conditions`: those only run for selectors the element actually wears, so attaching one is what ARMS them, and the call is rejected unless you pass `allow_code_elements: true` after the user explicitly confirms — even though the request itself carries no code.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The post whose canvas holds the target element.'],
            'element_id' => ['type' => 'integer', 'description' => 'The id of the element to attach the class to.'],
            'class_id' => [
                'type' => 'string',
                'description' => 'Stable selector id from oxygen-list-global-classes. Preferred.',
            ],
            'class_name' => [
                'type' => 'string',
                'description' => 'Compatibility locator by emitted class name; resolved to the stable id.',
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'description' => 'Explicit user opt-in, required only when the element already stores executable per-selector conditions for THIS selector, which attaching it would start running. Never set it on your own initiative.',
                'default' => false,
            ],
        ],
        'required' => ['post_id', 'element_id'],
        'anyOf' => [['required' => ['class_id']], ['required' => ['class_name']]],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'element_id' => ['type' => 'integer'],
            'classes' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Resolved emitted class names after the apply.',
            ],
            'class_ids' => ['type' => 'array', 'items' => ['type' => 'string']],
            'verification' => ['type' => 'object'],
            'already_present' => [
                'type' => 'boolean',
                'description' => 'True when the class was already attached — the call made no change.',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_apply_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Prefer class_id from list-global-classes. The ability writes properties.meta.classes, reads it back through the canvas writer, and confirms the selector id resolves. If the element already stores executable conditions for that selector, the attach is rejected with oxygen_code_element_needs_confirmation because it would arm them. Ask the user; only after explicit confirmation may you re-call with allow_code_elements:true, and never set that flag on your own initiative.',
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
function oxygen_apply_global_class(array $input): array|WP_Error
{
    $post_id = ox_coerce_int($input['post_id'] ?? 0);
    $element_id = ox_coerce_int($input['element_id'] ?? 0);
    if ($post_id <= 0 || $element_id <= 0) {
        return ox_error('oxygen_invalid_input', __(
            'post_id and element_id must both be positive integers.',
            domain: 'novamira-pro',
        ));
    }
    $post = ox_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $classes = ox_read_global_classes();
    $hit = ox_global_class_from_input($classes, [
        'id' => $input['class_id'] ?? null,
        'name' => $input['class_name'] ?? null,
    ]);
    if ($hit instanceof WP_Error) {
        return $hit;
    }
    [, $class] = $hit;
    if (($class['type'] ?? null) !== 'class') {
        return ox_error('oxygen_invalid_input', __(
            'Only a native selector of type `class` can be attached to an element. A custom selector is already matched by its CSS selector text.',
            domain: 'novamira-pro',
        ));
    }
    $class_id = isset($class['id']) && is_string($class['id']) ? $class['id'] : '';
    /** @var array<string, mixed>|WP_Error $result */
    return ox_with_tree_lock($post_id, static fn(): array|WP_Error => ox_apply_global_class_locked(
        $input,
        $post_id,
        $element_id,
        $class_id,
    ));
}

/**
 * Attach the class under the post's tree lock. Idempotent: an already-present
 * class short-circuits without a write so a re-apply never bumps the modified
 * date or regenerates caches.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function ox_apply_global_class_locked(array $input, int $post_id, int $element_id, string $class_id): array|WP_Error
{
    $tree = ox_read_tree($post_id);
    if ($tree instanceof WP_Error) {
        return $tree;
    }
    if ($element_id === 1) {
        return ox_error('oxygen_invalid_input', __(
            'The root element cannot carry a global class.',
            domain: 'novamira-pro',
        ));
    }
    $hit = ox_find_node($tree, $element_id);
    if ($hit === null) {
        return ox_error('oxygen_not_found', sprintf(
            __('Element %1$d not found in the canvas of post %2$d.', domain: 'novamira-pro'),
            $element_id,
            $post_id,
        ));
    }

    /** @var array<string, mixed> $data */
    $data = isset($hit['node']['data']) && is_array($hit['node']['data']) ? $hit['node']['data'] : [];
    /** @var array<string, mixed> $properties */
    $properties = isset($data['properties']) && is_array($data['properties']) ? $data['properties'] : [];
    $class_ids = ox_element_class_list($properties);

    if (in_array($class_id, $class_ids, strict: true)) {
        return ox_verified_existing_class_apply_result($post_id, $element_id, $class_id, $class_ids);
    }
    // The attachment carries no code, but it can ARM code the element already
    // stores: per-selector conditions at properties.meta.classes_conditions
    // only reach doesRuleGroupApply() for selectors the element actually wears
    // (selectors.php:314-332 → renderer.php:1479 → selectors.php:504-517).
    // Checked AFTER the idempotent short-circuit above: re-applying a selector
    // that is already attached arms nothing.
    $armed = ox_class_attachment_arming_payloads($properties, $class_id);
    if ($armed !== [] && !ox_coerce_bool($input['allow_code_elements'] ?? false)) {
        return ox_code_payload_block($armed);
    }
    /** @var mixed $before */
    $before = \Breakdance\Data\get_meta($post_id, ox_data_meta_key());
    $class_ids[] = $class_id;
    $class_path = ['meta', 'classes'];
    $conflict = ox_property_path_conflict($properties, $class_path);
    if ($conflict !== null) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: %s: the conflicting property prefix */
            __(
                'Cannot attach the class: this element stores a non-object value at `%s`, so its class list is unreadable. Inspect the element with oxygen-get-element before retrying.',
                domain: 'novamira-pro',
            ),
            $conflict,
        ));
    }
    $properties = ox_set_property_at_path($properties, $class_path, $class_ids);

    $write = ox_persist_element_properties($tree, $hit, $post_id, $element_id, $properties);
    if ($write instanceof WP_Error) {
        return $write;
    }
    return ox_verified_new_class_apply_result($post_id, $element_id, $class_id, $class_ids, $before);
}

/**
 * Verify an idempotent pre-existing binding without making another mutation.
 *
 * @param list<string> $class_ids
 * @return array<string, mixed>|WP_Error
 */
function ox_verified_existing_class_apply_result(
    int $post_id,
    int $element_id,
    string $class_id,
    array $class_ids,
): array|WP_Error {
    $resolved = ox_native_element_resolves_class($post_id, $element_id, $class_id);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    if (!$resolved) {
        return ox_error('oxygen_write_not_applied', __(
            'The selector binding is persisted, but Oxygen\'s native renderer does not currently resolve it. Retry in a fresh request; a stale native selector cache may be active.',
            domain: 'novamira-pro',
        ));
    }
    return [
        'success' => true,
        'element_id' => $element_id,
        'classes' => ox_global_class_names_for_ids($class_ids),
        'class_ids' => $class_ids,
        'already_present' => true,
        'verification' => ['persisted' => true, 'selector_resolved' => true],
    ];
}

/**
 * Verify a newly-written binding and compensate it when native resolution is
 * stale or otherwise absent.
 *
 * @param list<string> $class_ids
 * @return array<string, mixed>|WP_Error
 */
function ox_verified_new_class_apply_result(
    int $post_id,
    int $element_id,
    string $class_id,
    array $class_ids,
    mixed $before,
): array|WP_Error {
    $resolved = ox_native_element_resolves_class($post_id, $element_id, $class_id);
    if ($resolved instanceof WP_Error) {
        return ox_apply_class_resolution_failure($post_id, $before, $class_id, $resolved->get_error_message());
    }
    if (!$resolved) {
        return ox_apply_class_resolution_failure(
            $post_id,
            $before,
            $class_id,
            reason: 'Oxygen\'s native renderer did not resolve the newly attached selector (its request-local selector cache may be stale).',
        );
    }
    return [
        'success' => true,
        'element_id' => $element_id,
        'classes' => ox_global_class_names_for_ids($class_ids),
        'class_ids' => $class_ids,
        'already_present' => false,
        'verification' => ['persisted' => true, 'selector_resolved' => true],
    ];
}

/**
 * Ask the same native selector resolver used by Oxygen's renderer whether the
 * observed element resolves the requested selector id in this request. This
 * catches Oxygen's request-local `getOxySelectors()` static cache: persistence
 * can be correct while an immediate render still omits a just-created class.
 */
function ox_native_element_resolves_class(int $post_id, int $element_id, string $class_id): bool|WP_Error
{
    if (!function_exists('Breakdance\\BreakdanceOxygen\\Selectors\\getElementOxySelectors')) {
        return ox_error('oxygen_invalid_input', __(
            'Oxygen\'s native element-selector resolver is unavailable.',
            domain: 'novamira-pro',
        ));
    }
    $observed = ox_read_tree($post_id);
    if ($observed instanceof WP_Error) {
        return $observed;
    }
    $hit = ox_find_node($observed, $element_id);
    if ($hit === null) {
        return ox_error('oxygen_not_found', __('Element vanished after the class write.', domain: 'novamira-pro'));
    }
    /** @var mixed $resolved */
    $resolved = \Breakdance\BreakdanceOxygen\Selectors\getElementOxySelectors($hit['node']);
    if (!is_array($resolved)) {
        return false;
    }
    /** @var mixed $selector */
    foreach ($resolved as $selector) {
        if (is_array($selector) && isset($selector['id']) && (string) $selector['id'] === $class_id) {
            return true;
        }
    }
    return false;
}

/**
 * Compensate a persisted binding that the native renderer cannot observe yet.
 */
function ox_apply_class_resolution_failure(int $post_id, mixed $before, string $class_id, string $reason): WP_Error
{
    $rollback = ox_restore_tree_snapshot($post_id, $before);
    return ox_error(
        'oxygen_write_not_applied',
        sprintf(
            __('The selector `%1$s` was not observably applied. %2$s %3$s', domain: 'novamira-pro'),
            $class_id,
            $reason,
            $rollback['restored']
                ? 'The previous canvas was restored and re-verified.'
                : 'Rollback was partial; inspect the retained canvas before retrying.',
        ),
        ['rollback' => $rollback],
    );
}

/**
 * Resolve selector ids to their emitted names for a compact human-readable
 * receipt. Dangling ids are omitted from the name list but remain in class_ids.
 *
 * @param list<string> $ids
 * @return list<string>
 */
function ox_global_class_names_for_ids(array $ids): array
{
    $classes = ox_read_global_classes();
    $names = [];
    foreach ($ids as $id) {
        $hit = ox_find_global_class_by_id($classes, $id);
        if ($hit !== null && isset($hit[1]['name']) && is_string($hit[1]['name'])) {
            $names[] = $hit[1]['name'];
        }
    }
    return $names;
}
