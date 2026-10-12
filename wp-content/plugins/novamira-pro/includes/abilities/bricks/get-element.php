<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-get-element', [
    'label' => __('Get Bricks Element', domain: 'novamira-pro'),
    'description' => __(
        'Reads one element by id from a Bricks post or template: its settings, parent, direct children ids, the ancestor path and its global classes resolved to names. Cheaper than bricks-get-content when you only need one element — use it before bricks-patch-elements to see the current settings, or after a write to verify a single element. Searches the content area by default; pass area "header" or "footer" for those, or "any" to search header, content and footer.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The WordPress post or Bricks template id.'],
            'element_id' => [
                'type' => 'string',
                'description' => 'The element id (as listed by bricks-get-content summary: true).',
            ],
            'area' => [
                'type' => 'string',
                'enum' => ['header', 'content', 'footer', 'any'],
                'default' => 'content',
                'description' => 'Area to search; "any" searches header, content and footer.',
            ],
        ],
        'required' => ['post_id', 'element_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'area' => ['type' => 'string', 'description' => 'The area the element was found in.'],
            'element' => [
                'type' => 'object',
                'description' => 'The stored element: id, name, parent, settings, plus label / cid / children when present.',
            ],
            'parent' => [
                'type' => ['string', 'integer'],
                'description' => 'Parent element id, or 0 for a root element.',
            ],
            'path' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Ancestor ids from the root down to the parent.',
            ],
            'children_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Direct children, in stored order.',
            ],
            'descendant_count' => ['type' => 'integer'],
            'global_classes' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => ['id' => ['type' => 'string'], 'name' => ['type' => ['string', 'null']]],
                ],
                'description' => 'The element\'s _cssGlobalClasses resolved to class names; name is null for an id no global class has.',
            ],
            'component' => [
                'type' => 'object',
                'description' => 'Present when the element is a component instance: {cid, label, property_count}.',
            ],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_get_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Responsive and state values live under suffixed keys inside settings (e.g. `_padding:tablet_portrait`, `_background:hover`) — read them from the same settings object. A component instance carries `cid` and per-instance `properties`; its rendered body comes from the component (bricks-list-components verbose: true).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_get_element(array $input): array|WP_Error
{
    $post_id = (int) $input['post_id'];
    if (!get_post($post_id) instanceof WP_Post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
    }
    $element_id = (string) $input['element_id'];
    $area = (string) ($input['area'] ?? 'content');
    $areas = $area === 'any' ? array_keys(bricks_area_meta_keys()) : [$area];

    foreach ($areas as $candidate) {
        $elements = bricks_get_area_elements($post_id, $candidate);
        $found = bricks_element_context($elements, $element_id);
        if ($found !== null) {
            return ['post_id' => $post_id, 'area' => $candidate] + $found;
        }
    }

    return bricks_error('not_found', sprintf(
        'Element "%s" not found in the %s area%s of post %d. Call novamira/bricks-get-content with summary: true to list the ids that exist%s.',
        $element_id,
        implode(' / ', $areas),
        count($areas) > 1 ? 's' : '',
        $post_id,
        $area === 'any' ? '' : ', or pass area: "any" to search every area',
    ));
}

/**
 * The element plus its position in the tree and its resolved classes, or
 * null when the id is not in `$elements`.
 *
 * @param list<array<string, mixed>> $elements
 * @return array<string, mixed>|null
 */
function bricks_element_context(array $elements, string $element_id): ?array
{
    [$by_id, $children_of] = bricks_index_elements($elements);
    if (!isset($by_id[$element_id])) {
        return null;
    }
    $element = $by_id[$element_id];
    /** @var array<string, mixed> $settings */
    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
    [$classes, $warnings] = bricks_resolve_global_class_names($settings);
    $cycle = bricks_element_parent_cycle($by_id, $element_id);
    if ($cycle !== null) {
        $warnings[] = sprintf(
            'The stored tree has a circular parent chain (%s): the ancestor path and descendant count stop at the loop. Rewrite the area with bricks-set-content (every element with a valid parent) to repair it.',
            implode(' -> ', $cycle),
        );
    }

    $result = [
        'element' => $element,
        'parent' => $element['parent'] ?? 0,
        'path' => bricks_ancestor_path($by_id, $element_id),
        'children_ids' => $children_of[$element_id] ?? [],
        'descendant_count' => bricks_descendant_count($children_of, $element_id),
        'global_classes' => $classes,
    ];
    if (is_string($element['cid'] ?? null) && $element['cid'] !== '') {
        $result['component'] = bricks_instance_component_summary($element['cid']);
    }
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }

    return $result;
}

/**
 * Elements keyed by id, and children ids keyed by parent id (stored order).
 *
 * @param list<array<string, mixed>> $elements
 * @return array{0: array<string, array<string, mixed>>, 1: array<string, list<string>>}
 */
function bricks_index_elements(array $elements): array
{
    $by_id = [];
    $children_of = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $by_id[$id] = $element;
        $children_of[(string) ($element['parent'] ?? '0')][] = $id;
    }

    return [$by_id, $children_of];
}

/**
 * Ancestor ids from the root down to the element's parent.
 *
 * @param array<string, array<string, mixed>> $by_id
 * @return list<string>
 */
function bricks_ancestor_path(array $by_id, string $element_id): array
{
    /** @var list<string> $path */
    $path = [];
    $visited = [$element_id => true];
    $cursor = (string) ($by_id[$element_id]['parent'] ?? '0');
    while ($cursor !== '0' && $cursor !== '' && isset($by_id[$cursor]) && !isset($visited[$cursor])) {
        $visited[$cursor] = true;
        array_unshift($path, $cursor);
        $cursor = (string) ($by_id[$cursor]['parent'] ?? '0');
    }

    return $path;
}

/** Upper bound of elements visited by a tree walk. */
const NOVAMIRA_BRICKS_MAX_TREE_WALK = 20_000;

/**
 * The circular parent chain the element sits on or under, or null when
 * its ancestry reaches a root.
 *
 * @param array<string, array<string, mixed>> $by_id
 * @return list<string>|null
 */
function bricks_element_parent_cycle(array $by_id, string $element_id): ?array
{
    $parent_of = [];
    foreach ($by_id as $id => $element) {
        $parent_of[$id] = (string) ($element['parent'] ?? '0');
    }

    return bricks_parent_cycle_from($element_id, $parent_of);
}

/**
 * @param array<string, list<string>> $children_of
 */
function bricks_descendant_count(array $children_of, string $element_id): int
{
    // A visited set keeps a corrupted tree (parents referencing each other)
    // from looping; the size cap bounds the walk on any input.
    $visited = [$element_id => true];
    $queue = $children_of[$element_id] ?? [];
    while ($queue !== [] && count($visited) <= NOVAMIRA_BRICKS_MAX_TREE_WALK) {
        $next = array_shift($queue);
        if (isset($visited[$next])) {
            continue;
        }
        $visited[$next] = true;
        foreach ($children_of[$next] ?? [] as $child) {
            $queue[] = $child;
        }
    }

    return count($visited) - 1;
}

/**
 * `_cssGlobalClasses` ids resolved against the global classes store.
 *
 * @param array<string, mixed> $settings
 * @return array{0: list<array{id: string, name: string|null}>, 1: list<string>}
 */
function bricks_resolve_global_class_names(array $settings): array
{
    /** @var mixed $ids */
    $ids = $settings['_cssGlobalClasses'] ?? [];
    if (!is_array($ids) || $ids === []) {
        return [[], []];
    }
    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_GLOBAL_CLASSES, []);
    $names = [];
    /** @var mixed $class */
    foreach (is_array($raw) ? $raw : [] as $class) {
        if (!is_array($class) || !is_string($class['id'] ?? null) || !is_string($class['name'] ?? null)) {
            continue;
        }
        $names[$class['id']] = $class['name'];
    }
    $resolved = [];
    $missing = [];
    /** @var mixed $id */
    foreach ($ids as $id) {
        if (!is_string($id)) {
            continue;
        }
        $resolved[] = ['id' => $id, 'name' => $names[$id] ?? null];
        if (!isset($names[$id])) {
            $missing[] = $id;
        }
    }
    $warnings = $missing === []
        ? []
        : [sprintf(
            'Global class id(s) %s are attached to this element but no global class has them, so they contribute no styles to it. Create the class (bricks-create-global-class) or remove the id with bricks-patch-elements.',
            implode(', ', $missing),
        )];

    return [$resolved, $warnings];
}

/**
 * @return array{cid: string, label: string|null, property_count: int|null}
 */
function bricks_instance_component_summary(string $cid): array
{
    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_COMPONENTS, []);
    /** @var mixed $component */
    foreach (is_array($raw) ? $raw : [] as $component) {
        if (!is_array($component) || ($component['id'] ?? null) !== $cid) {
            continue;
        }
        $elements = is_array($component['elements'] ?? null) ? $component['elements'] : [];
        $root = is_array($elements[0] ?? null) ? $elements[0] : [];
        $properties = is_array($component['properties'] ?? null) ? $component['properties'] : [];

        return [
            'cid' => $cid,
            'label' => is_string($root['label'] ?? null) ? $root['label'] : null,
            'property_count' => count($properties),
        ];
    }

    return ['cid' => $cid, 'label' => null, 'property_count' => null];
}
