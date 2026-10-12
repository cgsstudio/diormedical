<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicShortcodes;

use DynamicShortcodes\Core\Demo\ResultTypeDetector;
use DynamicShortcodes\Core\Shortcodes\PermissionsError;
use DynamicShortcodes\Plugin;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/dynamic-shortcodes-evaluate-shortcode', [
    'label' => __('Evaluate Shortcode', domain: 'novamira-pro'),
    'description' => __(
        'Evaluates a Dynamic Shortcode against the live WordPress environment and returns the real result. Optional post_id sets postdata before evaluation. If the shortcode demands the Power Shortcode privileged context, returns {requires_power: true, result: null} — the agent must then call create-power-shortcode to wrap it.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-shortcodes',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'shortcode' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'The Dynamic Shortcode to evaluate.',
            ],
            'post_id' => [
                'type' => 'integer',
                'description' => 'Post ID to set as the post context before evaluation.',
            ],
        ],
        'required' => ['shortcode'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'shortcode' => ['type' => 'string'],
            'result' => [
                'type' => ['string', 'integer', 'number', 'boolean', 'array', 'object', 'null'],
                'description' => 'The evaluation result (type varies). Null when requires_power is true.',
            ],
            'result_type' => ['type' => 'string'],
            'requires_power' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicShortcodes\\dynamic_shortcodes_evaluate_shortcode',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => dsh_make_instructions(
                'Executes the shortcode against this site. Note: some shortcodes have side effects (`{set:}` writes to local env; `{call:fn …}` invokes arbitrary PHP; custom Power Shortcodes can do anything). Do not auto-retry on transient errors.',
            ),
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{shortcode: string, post_id?: int} $input
 * @return array{shortcode: string, result: mixed, result_type: string, requires_power: bool}|WP_Error
 */
function dynamic_shortcodes_evaluate_shortcode(array $input): array|WP_Error
{
    $shortcode = $input['shortcode'];
    $manager = Plugin::instance()->shortcodes_manager;

    $post_id = $input['post_id'] ?? null;
    /** @var mixed $prev_post */
    $prev_post = null;
    $set_post = false;
    if ($post_id !== null) {
        /** @var \WP_Post|null $post */
        $post = get_post((int) $post_id);
        if ($post instanceof \WP_Post) {
            /** @var mixed $prev_post */
            $prev_post = $GLOBALS['post'] ?? null;
            // phpcs:ignore WordPress.WP.GlobalVariablesOverride
            $GLOBALS['post'] = $post;
            setup_postdata($post);
            $set_post = true;
        }
    }

    $requires_power = false;
    /** @var mixed $result */
    $result = null;

    try {
        /** @var mixed $result */
        $result = $manager->evaluate_and_return_value($shortcode);
    } catch (PermissionsError) {
        $requires_power = true;
    } catch (\Throwable $e) {
        if ($set_post) {
            dsh_restore_post($prev_post);
        }

        return new WP_Error('evaluation_error', $e->getMessage());
    }

    $result_type = 'null';
    if (!$requires_power && $result !== null) {
        $detected = ResultTypeDetector::detect('', $result);
        $result_type = ResultTypeDetector::get_default_type($detected);
    }

    /** @var mixed $serialized */
    $serialized = match (true) {
        $result instanceof \WP_Post, $result instanceof \WP_Term, $result instanceof \WP_User => get_object_vars(
            $result,
        ),
        is_object($result) => get_class($result),
        default => $result,
    };

    if ($set_post) {
        dsh_restore_post($prev_post);
    }

    return [
        'shortcode' => $shortcode,
        'result' => $serialized,
        'result_type' => $result_type,
        'requires_power' => $requires_power,
    ];
}
