<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

/**
 * Ability: List the Etch block vocabulary (element types) and their attributes.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/etch-list-elements', [
    'label' => __('List Etch Elements', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Etch block vocabulary used to build pages: the block name (e.g. "etch/element"), a short description, whether it can contain children (container vs leaf), and its top-level attributes. Also returns `base_attributes` — attributes (hidden, script, options) available on EVERY node. Use this to learn what nodes novamira/etch-get-content returns and what novamira/etch-set-content accepts.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'count' => ['type' => 'integer'],
            'elements' => [
                'type' => 'array',
                'description' => 'The available Etch block types.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'block' => ['type' => 'string'],
                        'description' => ['type' => 'string'],
                        'container' => [
                            'type' => 'boolean',
                            'description' => 'Whether the block can hold child nodes.',
                        ],
                        'attributes' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Top-level attribute keys carried in the node\'s attrs object.',
                        ],
                    ],
                ],
            ],
            'base_attributes' => [
                'type' => 'array',
                'description' => 'Attributes available on EVERY node (in addition to each block\'s own attributes). They are preserved on get-content/set-content round-trip.',
                'items' => ['type' => 'object'],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_list_elements',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The block vocabulary is fixed by the Etch version installed. Container blocks (container=true) hold a children array; leaf blocks do not. etch/element is the workhorse — set its attrs.tag and attrs.attributes (an HTML attribute map). etch/text carries attrs.content (which may embed dynamic expressions like {props.title}). etch/component references a component by attrs.ref (a post id) and passes attrs.attributes as props.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{count: int, elements: list<array{block: string, description: string, container: bool, attributes: list<string>}>, base_attributes: list<array{attr: string, type: string, note: string}>}
 */
function etch_list_elements(): array
{
    $elements = [
        [
            'block' => 'etch/element',
            'description' => 'Generic HTML element — the workhorse. Renders attrs.tag with attrs.attributes and the styles in attrs.styles.',
            'container' => true,
            'attributes' => ['tag', 'attributes', 'styles'],
        ],
        [
            'block' => 'etch/text',
            'description' => 'A text node. attrs.content is a string that may embed dynamic expressions like {props.title}.',
            'container' => false,
            'attributes' => ['content'],
        ],
        [
            'block' => 'etch/component',
            'description' => 'An instance of a reusable component. attrs.ref is the component post id; attrs.attributes carries the prop values.',
            'container' => true,
            'attributes' => ['ref', 'attributes'],
        ],
        [
            'block' => 'etch/condition',
            'description' => 'Conditionally renders its children. attrs.condition is a rule object (leftHand/operator/rightHand); attrs.conditionString is its string form.',
            'container' => true,
            'attributes' => ['condition', 'conditionString'],
        ],
        [
            'block' => 'etch/loop',
            'description' => 'Repeats its children over a data source. attrs.loopId/target select the query; attrs.itemId and attrs.indexId name the per-iteration variables.',
            'container' => true,
            'attributes' => ['target', 'itemId', 'indexId', 'loopId', 'loopParams'],
        ],
        [
            'block' => 'etch/dynamic-element',
            'description' => 'An element whose tag and attributes resolve from dynamic data.',
            'container' => true,
            'attributes' => ['tag', 'attributes', 'styles'],
        ],
        [
            'block' => 'etch/dynamic-image',
            'description' => 'A dynamically-resolved image element.',
            'container' => true,
            'attributes' => ['tag', 'attributes', 'styles'],
        ],
        [
            'block' => 'etch/svg',
            'description' => 'An inline SVG element.',
            'container' => true,
            'attributes' => ['tag', 'attributes', 'styles'],
        ],
        [
            'block' => 'etch/raw-html',
            'description' => 'Verbatim HTML passthrough. attrs.content is the raw markup; attrs.unsafe opts out of sanitization.',
            'container' => false,
            'attributes' => ['content', 'unsafe'],
        ],
        [
            'block' => 'etch/slot-placeholder',
            'description' => 'Marks where a named component slot is inserted. attrs.name is the slot name.',
            'container' => false,
            'attributes' => ['name'],
        ],
        [
            'block' => 'etch/slot-content',
            'description' => 'Provides children into a named component slot. attrs.name is the slot name.',
            'container' => true,
            'attributes' => ['name'],
        ],
    ];

    return [
        'count' => count($elements),
        'elements' => $elements,
        'base_attributes' => [
            ['attr' => 'hidden', 'type' => 'boolean', 'note' => 'Excludes the node from render when true.'],
            ['attr' => 'script', 'type' => 'object', 'note' => 'Per-node script {id, code}.'],
            ['attr' => 'options', 'type' => 'object', 'note' => 'Builder/editor options bag.'],
        ],
    ];
}
