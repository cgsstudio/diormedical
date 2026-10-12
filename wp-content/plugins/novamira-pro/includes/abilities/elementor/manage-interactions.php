<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

use WP_Error;

/**
 * Abilities: Elementor v4 Interactions on atomic widgets — list / add /
 * delete. Each operation is its own ability so input schemas stay sharp.
 * The ix_* helpers below host the actual logic.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/elementor-list-interactions', [
    'label' => __('List Elementor Interactions', domain: 'novamira-pro'),
    'description' => __(
        'Lists every interaction (animation, hover effect, scroll effect) attached to a specific atomic widget on a page. Returns each interaction with its trigger, effect, type, direction, duration, delay, and easing.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The page/post id containing the element.',
            ],
            'element_id' => [
                'type' => 'string',
                'description' => 'The Elementor element id of the atomic widget.',
            ],
        ],
        'required' => ['post_id', 'element_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'interactions' => ['type' => 'array'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\list_interactions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

wp_register_ability('novamira/elementor-add-interaction', [
    'label' => __('Add Elementor Interaction', domain: 'novamira-pro'),
    'description' => __(
        'Adds an interaction (animation, hover effect, scroll effect) to an atomic widget. Required: trigger (load/scrollIn/scrollOut/scrollOn/hover/click) and effect (fade/slide/scale/custom). Optional: type (in/out), direction, duration, delay, easing.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The page/post id containing the element.'],
            'element_id' => [
                'type' => 'string',
                'description' => 'The Elementor element id of the target atomic widget.',
            ],
            'trigger' => [
                'type' => 'string',
                'description' => 'When the interaction fires.',
                'enum' => ['load', 'scrollIn', 'scrollOut', 'scrollOn', 'hover', 'click'],
            ],
            'effect' => [
                'type' => 'string',
                'description' => 'Animation effect family.',
                'enum' => ['fade', 'slide', 'scale', 'custom'],
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Animation direction type. Defaults to "in".',
                'enum' => ['in', 'out'],
            ],
            'direction' => [
                'type' => 'string',
                'description' => 'Slide direction (for slide effect). Defaults to empty.',
                'enum' => ['', 'left', 'right', 'top', 'bottom'],
            ],
            'duration' => [
                'type' => 'integer',
                'description' => 'Animation duration in milliseconds. Defaults to 600.',
            ],
            'delay' => ['type' => 'integer', 'description' => 'Animation delay in milliseconds. Defaults to 0.'],
            'easing' => [
                'type' => 'string',
                'description' => 'Easing function. Defaults to "easeIn".',
                'enum' => ['easeIn', 'easeOut', 'easeInOut', 'backIn', 'backInOut', 'backOut', 'linear'],
            ],
        ],
        'required' => ['post_id', 'element_id', 'trigger', 'effect'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'interaction' => ['type' => 'object'],
            'error' => ['type' => 'string'],
            'widget_type' => [
                'type' => 'string',
                'description' => 'On v4-only rejection: the target element\'s widget type.',
            ],
            'is_atomic' => [
                'type' => 'boolean',
                'description' => 'On v4-only rejection: false when the target is a legacy v3 widget.',
            ],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\add_interaction',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => false],
    ],
]);

wp_register_ability('novamira/elementor-delete-interaction', [
    'label' => __('Delete Elementor Interaction', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an interaction from an atomic widget by its zero-based index in the widget\'s interactions array. Use list-interactions first to discover the indices.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The page/post id containing the element.'],
            'element_id' => [
                'type' => 'string',
                'description' => 'The Elementor element id of the target atomic widget.',
            ],
            'interaction_index' => [
                'type' => 'integer',
                'description' => 'Zero-based index of the interaction to delete.',
            ],
        ],
        'required' => ['post_id', 'element_id', 'interaction_index'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'error' => ['type' => 'string'],
            'widget_type' => [
                'type' => 'string',
                'description' => 'On v4-only rejection: the target element\'s widget type.',
            ],
            'is_atomic' => [
                'type' => 'boolean',
                'description' => 'On v4-only rejection: false when the target is a legacy v3 widget.',
            ],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\delete_interaction',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => false, 'destructive' => true, 'idempotent' => true],
    ],
]);

/**
 * Guard that ensures Elementor is loaded and the page/element pair are present.
 *
 * @return array{success: false, error: string}|null
 */
function ix_runtime_guard(int $post_id, string $element_id): ?array
{
    if (!class_exists('Elementor\\Plugin')) {
        return ['success' => false, 'error' => 'Elementor is not active.'];
    }
    if ($post_id <= 0) {
        return ['success' => false, 'error' => 'Parameter "post_id" is required.'];
    }
    if ($element_id === '') {
        return ['success' => false, 'error' => 'Parameter "element_id" is required.'];
    }
    return null;
}

/**
 * Reject writes targeting a v3 legacy widget. Interactions are a v4-only
 * concept — attaching one to a v3 element persists an `interaction-item`
 * that no v4 runtime will ever animate, yielding a silent visual no-op
 * agents otherwise can't tell apart from a working call. Mirrors the
 * same pattern `apply-global-class` uses (commit 05fb3c2) so the error
 * shape stays consistent across v4-only writes.
 *
 * @param array<string, mixed> $element
 * @return array{success: false, error: string, widget_type: string, is_atomic: false}|null
 */
function ix_atomic_guard(array $element): ?array
{
    $widget_type = el_element_widget_type($element);
    if ($widget_type === '') {
        return [
            'success' => false,
            'error' => 'Target element has no resolvable widget type — cannot inspect or modify interactions on a broken element.',
            'widget_type' => '',
            'is_atomic' => false,
        ];
    }

    $schema = el_extract_schema($widget_type);
    if (!is_array($schema) || ($schema['is_atomic'] ?? false) !== true) {
        return [
            'success' => false,
            'error' => sprintf(
                "Interactions are a v4-only feature. Widget '%s' is a legacy v3 widget and cannot accept interactions. Convert it to its atomic equivalent first (see novamira/skill-get slug=elementor-convert-to-v4).",
                $widget_type,
            ),
            'widget_type' => $widget_type,
            'is_atomic' => false,
        ];
    }

    return null;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function list_interactions(array $input): array
{
    $post_id = (int) ($input['post_id'] ?? 0);
    $element_id = (string) ($input['element_id'] ?? '');
    $guard = ix_runtime_guard($post_id, $element_id);
    return $guard ?? ix_list($post_id, $element_id);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function add_interaction(array $input): array|WP_Error
{
    $post_id = (int) ($input['post_id'] ?? 0);
    $element_id = (string) ($input['element_id'] ?? '');
    $guard = ix_runtime_guard($post_id, $element_id);
    return $guard ?? ix_add($post_id, $element_id, $input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function delete_interaction(array $input): array|WP_Error
{
    $post_id = (int) ($input['post_id'] ?? 0);
    $element_id = (string) ($input['element_id'] ?? '');
    $guard = ix_runtime_guard($post_id, $element_id);
    return $guard ?? ix_remove($post_id, $element_id, $input);
}

/**
 * Find an element by ID recursively.
 *
 * @param list<array<string, mixed>> $elements
 * @return array<string, mixed>|null
 */
function ix_find_element(array $elements, string $element_id): ?array
{
    foreach ($elements as $element) {
        if (($element['id'] ?? '') === $element_id) {
            return $element;
        }
        if (($element['elements'] ?? null) !== null && is_array($element['elements'])) {
            /** @var list<array<string, mixed>> $children */
            $children = $element['elements'];
            $found = ix_find_element($children, $element_id);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}

/**
 * Decode the interactions field from an element (can be JSON string or array).
 *
 * @param mixed $interactions
 * @return array{items: list<array<string, mixed>>, version: int}
 */
function ix_decode(mixed $interactions): array
{
    $default = ['items' => [], 'version' => 1];

    if (is_string($interactions) && $interactions !== '') {
        /** @var array<string, mixed>|null $decoded */
        $decoded = json_decode($interactions, associative: true);
        if (!is_array($decoded)) {
            return $default;
        }
        $interactions = $decoded;
    }

    if (!is_array($interactions)) {
        return $default;
    }

    if (($interactions['items'] ?? null) === null) {
        return $default;
    }

    /** @var list<array<string, mixed>> $items */
    $items = is_array($interactions['items']) ? $interactions['items'] : [];

    return ['items' => $items, 'version' => (int) ($interactions['version'] ?? 1)];
}

/**
 * List interactions on an element.
 *
 * @return array{success: bool, interactions?: list<array<string, mixed>>, error?: string}
 */
function ix_list(int $post_id, string $element_id): array
{
    [$elements, $error] = el_read_page_required($post_id);
    if ($elements === null) {
        return ['success' => false, 'error' => $error ?? 'Unknown error.'];
    }

    $element = ix_find_element($elements, $element_id);
    if ($element === null) {
        return ['success' => false, 'error' => "Element '{$element_id}' not found on page {$post_id}."];
    }

    $data = ix_decode($element['interactions'] ?? []);
    $result = [];

    foreach ($data['items'] as $index => $item) {
        if (($item['$$type'] ?? '') !== 'interaction-item') {
            continue;
        }
        /** @var array<string, mixed> $value */
        $value = $item['value'] ?? [];
        $result[] = [
            'index' => $index,
            'interaction_id' => $value['interaction_id']['value'] ?? null,
            'trigger' => $value['trigger']['value'] ?? null,
            'effect' => $value['animation']['value']['effect']['value'] ?? null,
            'type' => $value['animation']['value']['type']['value'] ?? null,
            'direction' => $value['animation']['value']['direction']['value'] ?? null,
            'duration' => $value['animation']['value']['timing_config']['value']['duration']['value'] ?? null,
            'delay' => $value['animation']['value']['timing_config']['value']['delay']['value'] ?? null,
        ];
    }

    return ['success' => true, 'interactions' => $result];
}

/**
 * Validate input for the add action.
 *
 * @param array<string, mixed> $input
 * @return list<string> List of error messages; empty when valid.
 */
function ix_validate_add_input(array $input): array
{
    $errors = [];

    $trigger = (string) ($input['trigger'] ?? '');
    if ($trigger === '') {
        $errors[] = 'Parameter "trigger" is required for add.';
    }
    if (
        $trigger !== ''
        && !in_array($trigger, ['load', 'scrollIn', 'scrollOut', 'scrollOn', 'hover', 'click'], strict: true)
    ) {
        $errors[] = "Invalid trigger: {$trigger}";
    }

    $effect = (string) ($input['effect'] ?? '');
    if ($effect === '') {
        $errors[] = 'Parameter "effect" is required for add.';
    }
    if ($effect !== '' && !in_array($effect, ['fade', 'slide', 'scale', 'custom'], strict: true)) {
        $errors[] = "Invalid effect: {$effect}";
    }

    return $errors;
}

/**
 * Build an interaction item in Elementor's PropType format.
 *
 * @param array{trigger: string, effect: string, type: string, direction: string, duration: int, delay: int, easing: string} $params
 * @return array<string, mixed>
 */
function ix_build_interaction_item(array $params): array
{
    $interaction_id = 'temp-' . bin2hex(random_bytes(4));

    return [
        '$$type' => 'interaction-item',
        'value' => [
            'interaction_id' => ['$$type' => 'string', 'value' => $interaction_id],
            'trigger' => ['$$type' => 'string', 'value' => $params['trigger']],
            'animation' => [
                '$$type' => 'animation-preset-props',
                'value' => [
                    'effect' => ['$$type' => 'string', 'value' => $params['effect']],
                    'type' => ['$$type' => 'string', 'value' => $params['type']],
                    'direction' => ['$$type' => 'string', 'value' => $params['direction']],
                    'timing_config' => [
                        '$$type' => 'timing-config',
                        'value' => [
                            'duration' => ['$$type' => 'number', 'value' => $params['duration']],
                            'delay' => ['$$type' => 'number', 'value' => $params['delay']],
                        ],
                    ],
                    'config' => [
                        '$$type' => 'config',
                        'value' => [
                            'easing' => ['$$type' => 'string', 'value' => $params['easing']],
                        ],
                    ],
                ],
            ],
        ],
    ];
}

/**
 * Add an interaction to an element.
 *
 * @param array<string, mixed> $input
 * @return array{success: bool, interaction?: array<string, mixed>, error?: string, widget_type?: string, is_atomic?: bool}|WP_Error
 */
function ix_add(int $post_id, string $element_id, array $input): array|WP_Error
{
    $errors = ix_validate_add_input($input);
    if ($errors !== []) {
        return ['success' => false, 'error' => $errors[0]];
    }

    [$elements, $error] = el_read_page_required($post_id);
    if ($elements === null) {
        return ['success' => false, 'error' => $error ?? 'Unknown error.'];
    }

    $element = ix_find_element($elements, $element_id);
    if ($element === null) {
        return ['success' => false, 'error' => "Element '{$element_id}' not found on page {$post_id}."];
    }

    $guard = ix_atomic_guard($element);
    if ($guard !== null) {
        return $guard;
    }

    $interaction_item = ix_build_interaction_item([
        'trigger' => (string) ($input['trigger'] ?? ''),
        'effect' => (string) ($input['effect'] ?? ''),
        'type' => (string) ($input['type'] ?? 'in'),
        'direction' => (string) ($input['direction'] ?? ''),
        'duration' => (int) ($input['duration'] ?? 600),
        'delay' => (int) ($input['delay'] ?? 0),
        'easing' => (string) ($input['easing'] ?? 'easeIn'),
    ]);

    $result = ix_update_elements($elements, $element_id, $interaction_item, remove_index: null);
    if (!$result['found']) {
        return ['success' => false, 'error' => "Element '{$element_id}' not found on page {$post_id}."];
    }

    $save = el_write_page($post_id, $result['elements']);
    if ($save instanceof WP_Error) {
        return $save;
    }

    // Re-read the persisted tree so the response carries the canonical
    // interaction id assigned by Elementor's Parser, not the pre-save
    // `temp-...` placeholder built above.
    $persisted_item = ix_read_last_interaction($post_id, $element_id) ?? $interaction_item;

    return ['success' => true, 'interaction' => $persisted_item];
}

/**
 * Read the last interaction item persisted on a given element. Used to
 * surface the canonical interaction id (assigned during save) back to the
 * caller of add-interaction.
 *
 * @return array<string, mixed>|null
 */
function ix_read_last_interaction(int $post_id, string $element_id): ?array
{
    [$elements, $error] = el_read_page_required($post_id);
    if ($elements === null) {
        unset($error);
        return null;
    }
    $element = ix_find_element($elements, $element_id);
    if ($element === null) {
        return null;
    }
    $items = ix_decode($element['interactions'] ?? [])['items'];
    if ($items === []) {
        return null;
    }
    return end($items);
}

/**
 * Remove an interaction from an element.
 *
 * @param array<string, mixed> $input
 * @return array{success: bool, error?: string, widget_type?: string, is_atomic?: bool}|WP_Error
 */
function ix_remove(int $post_id, string $element_id, array $input): array|WP_Error
{
    /** @var int|string|null $index */
    $index = $input['interaction_index'] ?? null;
    if ($index === null) {
        return ['success' => false, 'error' => 'Parameter "interaction_index" is required for remove.'];
    }
    $index = (int) $index;

    [$elements, $error] = el_read_page_required($post_id);
    if ($elements === null) {
        return ['success' => false, 'error' => $error ?? 'Unknown error.'];
    }

    $element = ix_find_element($elements, $element_id);
    if ($element === null) {
        return ['success' => false, 'error' => "Element '{$element_id}' not found on page {$post_id}."];
    }

    $guard = ix_atomic_guard($element);
    if ($guard !== null) {
        return $guard;
    }

    $result = ix_update_elements($elements, $element_id, add_item: null, remove_index: $index);
    if (!$result['found']) {
        return ['success' => false, 'error' => "Element '{$element_id}' not found on page {$post_id}."];
    }

    $save = el_write_page($post_id, $result['elements']);
    if ($save instanceof WP_Error) {
        return $save;
    }

    return ['success' => true];
}

/**
 * Recursively walk elements to update interactions on a specific element.
 *
 * When $add_item is provided, it is appended. When $remove_index is provided, that item is removed.
 *
 * @param list<array<string, mixed>>  $elements
 * @param array<string, mixed>|null   $add_item
 * @return array{elements: list<array<string, mixed>>, found: bool}
 */
function ix_update_elements(array $elements, string $element_id, ?array $add_item, ?int $remove_index): array
{
    foreach ($elements as &$element) {
        if (($element['id'] ?? '') === $element_id) {
            $data = ix_decode($element['interactions'] ?? []);

            if ($add_item !== null) {
                $data['items'][] = $add_item;
            }

            if ($remove_index !== null) {
                if (($data['items'][$remove_index] ?? null) === null) {
                    // Mark found so we don't report "element not found" — the element exists but index is wrong.
                    return ['elements' => $elements, 'found' => true];
                }
                array_splice($data['items'], $remove_index, length: 1);
            }

            // Store interactions as JSON-encoded string (matches Elementor's save format).
            $element['interactions'] = wp_json_encode($data);
            if ($data['items'] === []) {
                $element['interactions'] = [];
            }

            return ['elements' => $elements, 'found' => true];
        }

        if (($element['elements'] ?? null) !== null && is_array($element['elements'])) {
            /** @var list<array<string, mixed>> $children */
            $children = $element['elements'];
            $child_result = ix_update_elements($children, $element_id, $add_item, $remove_index);
            $element['elements'] = $child_result['elements'];
            if ($child_result['found']) {
                return ['elements' => $elements, 'found' => true];
            }
        }
    }

    return ['elements' => $elements, 'found' => false];
}
