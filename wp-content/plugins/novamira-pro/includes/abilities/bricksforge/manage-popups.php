<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricksforge;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const BRF_POPUP_TRIGGERS = ['pageLoad', 'click', 'scrollPosition', 'customTrigger'];

const BRF_POPUP_DISPLAY_ON = ['entire_website', 'specific_page'];

const BRF_POPUP_ANIMATIONS = ['fade-in-down', 'fade-in-up', 'fade-in-left', 'fade-in-right', 'fade-in'];

/**
 * The shared popup-config input schema properties (everything except id).
 *
 * @return array<string, array<string, mixed>>
 */
function brf_popup_schema_properties(): array
{
    return [
        'template' => [
            'type' => 'integer',
            'description' => 'The Bricks template post id rendered inside the popup (novamira/bricks-list-templates). A popup without a template never fires.',
        ],
        'active' => [
            'type' => 'boolean',
            'description' => 'Whether the popup fires. Inactive popups are skipped entirely.',
        ],
        'trigger' => [
            'type' => 'string',
            'enum' => BRF_POPUP_TRIGGERS,
            'description' => 'What opens the popup: pageLoad (optionally delayed by seconds), click (on selector), scrollPosition (px), or customTrigger.',
        ],
        'seconds' => ['type' => 'number', 'description' => 'pageLoad trigger: delay in seconds before firing.'],
        'selector' => ['type' => 'string', 'description' => 'click trigger: CSS selector that opens the popup.'],
        'scrollPosition' => ['type' => 'integer', 'description' => 'scrollPosition trigger: scrollTop offset in px.'],
        'displayOn' => [
            'type' => 'string',
            'enum' => BRF_POPUP_DISPLAY_ON,
            'description' => 'Where the popup may fire: entire_website or specific_page (set page too).',
        ],
        'page' => [
            'type' => ['integer', 'array'],
            'items' => ['type' => 'integer'],
            'description' => 'displayOn=specific_page: one post/page id or a list of ids.',
        ],
        'executions' => [
            'type' => 'integer',
            'description' => 'Maximum times the popup fires per visitor (tracked in localStorage). Ignored for the click trigger.',
        ],
        'animation' => [
            'type' => 'string',
            'enum' => BRF_POPUP_ANIMATIONS,
            'description' => 'Open animation. Default fade-in-down.',
        ],
        'overlayColor' => [
            'type' => 'string',
            'description' => 'CSS color of the fullscreen overlay (default rgba(0,0,0,.5)).',
        ],
        'innerBackgroundColor' => [
            'type' => 'string',
            'description' => 'CSS color of the popup body (default transparent).',
        ],
        'addCloseButton' => ['type' => 'boolean', 'description' => 'Render an X close button.'],
        'closeOnOverlayClick' => ['type' => 'boolean', 'description' => 'Clicking the overlay closes the popup.'],
        'isScrollable' => ['type' => 'boolean', 'description' => 'Align to top and allow long content to scroll.'],
    ];
}

/**
 * Validate popup-config inputs shared by create and edit. Returns only the
 * keys that were provided, coerced and checked.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function brf_validate_popup_input(array $input): array|WP_Error
{
    $template = brf_validate_popup_template($input);
    if ($template instanceof WP_Error) {
        return $template;
    }

    $enums = brf_validate_popup_enums($input);
    if ($enums instanceof WP_Error) {
        return $enums;
    }

    $out = array_merge($enums, brf_popup_scalar_inputs($input));
    if ($template !== null) {
        $out['template'] = $template;
    }

    $page = brf_popup_page_input($input);
    if ($page !== null) {
        $out['page'] = $page;
    }

    return $out;
}

/**
 * @param array<string, mixed> $input
 * @return int|null|WP_Error
 */
function brf_validate_popup_template(array $input): int|null|WP_Error
{
    if (!isset($input['template'])) {
        return null;
    }

    $template = brf_int_or_null($input['template']);
    /** @var mixed $post */
    $post = $template === null ? null : get_post($template);
    if (!$post instanceof \WP_Post || $post->post_type !== 'bricks_template') {
        return brf_error(
            code: 'bricksforge_invalid_input',
            message: 'template must be the post id of a Bricks template (post type bricks_template). Use novamira/bricks-list-templates to find one.',
            data: ['status' => 400],
        );
    }

    // Publish-only, matching the Bricksforge admin picker. The popup render
    // endpoint has no status check and would serve trashed/draft/private
    // template content to anonymous visitors.
    if ($post->post_status !== 'publish') {
        return brf_error(
            code: 'bricksforge_invalid_input',
            message: sprintf(
                'template %1$d is not published (status "%2$s"). Popups render their template on the public frontend regardless of status, so only published Bricks templates are accepted.',
                $template,
                $post->post_status,
            ),
            data: ['status' => 400],
        );
    }

    return $template;
}

/**
 * The enum-constrained keys (trigger, displayOn, animation), validated.
 *
 * @param array<string, mixed> $input
 * @return array<string, string>|WP_Error
 */
function brf_validate_popup_enums(array $input): array|WP_Error
{
    $allowed = [
        'trigger' => BRF_POPUP_TRIGGERS,
        'displayOn' => BRF_POPUP_DISPLAY_ON,
        'animation' => BRF_POPUP_ANIMATIONS,
    ];

    $out = [];
    foreach ($allowed as $key => $values) {
        if (!isset($input[$key])) {
            continue;
        }
        if (!is_string($input[$key]) || !in_array($input[$key], $values, strict: true)) {
            return brf_error(
                code: 'bricksforge_invalid_input',
                message: sprintf('%1$s must be one of: %2$s.', $key, implode(', ', $values)),
                data: ['status' => 400],
            );
        }
        $out[$key] = $input[$key];
    }

    return $out;
}

/**
 * The free-form scalar keys (booleans, numbers, strings), coerced.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function brf_popup_scalar_inputs(array $input): array
{
    $out = [];
    foreach (['active', 'addCloseButton', 'closeOnOverlayClick', 'isScrollable'] as $bool_key) {
        if (!isset($input[$bool_key])) {
            continue;
        }
        // REST-truthy coercion: the schema layer lets 1/0/"true"/"false"
        // through for booleans.
        $out[$bool_key] = brf_bool($input[$bool_key]);
    }
    foreach (['selector', 'overlayColor', 'innerBackgroundColor'] as $string_key) {
        if (!isset($input[$string_key]) || !is_string($input[$string_key])) {
            continue;
        }
        $out[$string_key] = $input[$string_key];
    }
    foreach (['scrollPosition', 'executions'] as $int_key) {
        $int_value = brf_int_or_null($input[$int_key] ?? null);
        if ($int_value === null) {
            continue;
        }
        $out[$int_key] = $int_value;
    }
    if (isset($input['seconds']) && (is_int($input['seconds']) || is_float($input['seconds']))) {
        $out['seconds'] = $input['seconds'];
    }

    return $out;
}

/**
 * The page targeting input: one post id or a list of ids, or null when absent.
 *
 * @param array<string, mixed> $input
 * @return int|list<int>|null
 */
function brf_popup_page_input(array $input): int|array|null
{
    if (!isset($input['page'])) {
        return null;
    }
    if (!is_array($input['page'])) {
        return brf_int_or_null($input['page']);
    }

    $pages = [];
    /** @var mixed $value */
    foreach ($input['page'] as $value) {
        $page_id = brf_int_or_null($value);
        if ($page_id === null) {
            continue;
        }
        $pages[] = $page_id;
    }

    return $pages;
}

/**
 * Warnings about a popup config that will silently never fire, plus the
 * popups-tool state. The frontend script skips any popup missing template,
 * displayOn, trigger, or active.
 *
 * @param array<string, mixed> $response
 * @return array<string, mixed>
 */
function brf_popup_fire_warnings(array $response, object $popup): array
{
    if (!brf_tool_active(5)) {
        $response = brf_append_warning(
            $response,
            warning: 'The Popups tool (tool 5) is not active — no popup fires until it is enabled (bricksforge-enable-tool slug "popups").',
        );
    }

    $vars = get_object_vars($popup);
    $missing = [];
    foreach (['template', 'displayOn', 'trigger'] as $required_key) {
        if (isset($vars[$required_key]) && $vars[$required_key] !== '' && $vars[$required_key] !== null) {
            continue;
        }
        $missing[] = $required_key;
    }
    if (($vars['active'] ?? null) !== true) {
        $missing[] = 'active (must be true)';
    }
    if ($missing !== []) {
        $response = brf_append_warning(
            $response,
            warning: 'This popup will never fire until set: ' . implode(', ', $missing) . '.',
        );
    }

    // TOCTOU: a template published when the popup was created can later be
    // trashed or unpublished. The popup render endpoint serves it to anonymous
    // visitors regardless of status.
    $template = brf_int_or_null($vars['template'] ?? null);
    if ($template !== null && !brf_template_is_publishable($template)) {
        $response = brf_append_warning($response, warning: sprintf(
            'The popup template (id %d) is no longer a published Bricks template. It renders to visitors regardless of status, or renders nothing if trashed — republish it or point the popup at a published template.',
            $template,
        ));
    }

    return $response;
}

/**
 * Whether a template id still resolves to a published bricks_template — the
 * precondition create/edit-popup enforced at write time but which can drift.
 */
function brf_template_is_publishable(int $template): bool
{
    /** @var mixed $post */
    $post = get_post($template);

    return $post instanceof \WP_Post && $post->post_type === 'bricks_template' && $post->post_status === 'publish';
}

/**
 * Warn when persisting the popups list dropped rows brf_raw_popups() could
 * not read (non-object garbage) — the rewrite is upstream-parity but must
 * not be silent.
 *
 * @param array<string, mixed> $response
 * @return array<string, mixed>
 */
function brf_popup_dropped_rows_warning(array $response, int $raw_count, int $read_count): array
{
    if ($raw_count <= $read_count) {
        return $response;
    }

    return brf_append_warning($response, warning: sprintf(
        '%d malformed (non-object) row(s) in brf_popups were dropped while saving. They were unreadable to Bricksforge as popups; nothing functional was lost.',
        $raw_count - $read_count,
    ));
}

wp_register_ability('novamira/bricksforge-list-popups', [
    'label' => __('List Bricksforge Popups', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Bricksforge popups (the brf_popups configs) in compact form: id, active, trigger, displayOn, template id, animation. Bricksforge popups are a distinct system from Bricks-core popup templates: content comes from a Bricks template, behaviour from this config. Use bricksforge-get-popup for one full config.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'popups' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'active' => ['type' => 'boolean'],
                        'trigger' => ['type' => 'string'],
                        'displayOn' => ['type' => 'string'],
                        'template' => ['type' => ['integer', 'null']],
                        'animation' => ['type' => ['string', 'null']],
                    ],
                ],
            ],
            'popups_tool_active' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_list_popups',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'popups_tool_active=false means none of these fire regardless of their config.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function bricksforge_list_popups(): array
{
    $popups = [];
    foreach (brf_raw_popups() as $popup) {
        $vars = get_object_vars($popup);
        $popups[] = [
            'id' => brf_popup_id($popup),
            'active' => ($vars['active'] ?? null) === true,
            'trigger' => is_string($vars['trigger'] ?? null) ? $vars['trigger'] : '',
            'displayOn' => is_string($vars['displayOn'] ?? null) ? $vars['displayOn'] : '',
            'template' => brf_int_or_null($vars['template'] ?? null),
            'animation' => is_string($vars['animation'] ?? null) ? $vars['animation'] : null,
        ];
    }

    $capped = brf_cap_list($popups);

    return brf_list_omitted_warning(
        [
            'popups' => $capped['rows'],
            'popups_tool_active' => brf_tool_active(5),
        ],
        $capped['omitted'],
        noun: 'popup(s)',
    );
}

wp_register_ability('novamira/bricksforge-get-popup', [
    'label' => __('Get Bricksforge Popup', domain: 'novamira-pro'),
    'description' => __(
        'Reads one Bricksforge popup config by id, returning every stored key (template, trigger + trigger params, displayOn/page, executions, animation, colors, close behaviour). Warnings flag configs that will never fire.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The popup id (from bricksforge-list-popups).'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'popup' => ['type' => 'object', 'description' => 'The full stored config, upstream camelCase keys.'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_get_popup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Config keys are the upstream camelCase names — pass them back unchanged to bricksforge-edit-popup.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricksforge_get_popup(array $input): array|WP_Error
{
    $id = isset($input['id']) && is_string($input['id']) ? $input['id'] : '';
    foreach (brf_raw_popups() as $popup) {
        if (brf_popup_id($popup) !== $id) {
            continue;
        }

        return brf_warn_if_large(
            brf_popup_fire_warnings(['popup' => brf_popup_to_output($popup)], $popup),
            $popup,
            what: 'popup config',
        );
    }

    return brf_error(
        code: 'bricksforge_popup_not_found',
        message: sprintf('No Bricksforge popup with id "%s".', $id),
        data: ['status' => 404],
    );
}

wp_register_ability('novamira/bricksforge-create-popup', [
    'label' => __('Create Bricksforge Popup', domain: 'novamira-pro'),
    'description' => __(
        'Creates a Bricksforge popup with a generated id. Requires template (a Bricks template post id) and trigger; displayOn defaults to entire_website, active to true, overlay/body colors to the Bricksforge defaults. Returns the full stored config. The popup fires only while the Popups tool is enabled (warned otherwise).',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'properties' => array_merge(brf_popup_schema_properties(), []),
        'required' => ['template', 'trigger'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'popup' => ['type' => 'object'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_create_popup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pair the click trigger with selector, pageLoad with optional seconds, scrollPosition with scrollPosition px. To open from a Bricks page element use the brf-popup-trigger element (or class brf-popup-open with data-popup=<id>).',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricksforge_create_popup(array $input): array|WP_Error
{
    $validated = brf_validate_popup_input($input);
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    $config = array_merge([
        'active' => true,
        'displayOn' => 'entire_website',
        'overlayColor' => 'rgba(0,0,0,.5)',
        'innerBackgroundColor' => 'transparent',
    ], $validated);
    $config['id'] = wp_generate_uuid4();

    /** @var mixed $popup */
    $popup = json_decode((string) wp_json_encode($config));
    if (!is_object($popup)) {
        return brf_error(code: 'bricksforge_popup_encode_failed', message: 'Could not encode the popup config.', data: [
            'status' => 500,
        ]);
    }

    $result = brf_with_lock('popups', static function () use ($popup): array {
        /** @var mixed $raw */
        $raw = get_option('brf_popups');
        $raw_count = is_array($raw) ? count($raw) : 0;
        $popups = brf_raw_popups();
        $read_count = count($popups);
        $popups[] = $popup;
        brf_write_popups($popups);

        return ['raw' => $raw_count, 'read' => $read_count];
    });
    if ($result instanceof WP_Error) {
        return $result;
    }

    return brf_popup_dropped_rows_warning(
        brf_popup_fire_warnings([
            'id' => $config['id'],
            'popup' => brf_popup_to_output($popup),
        ], $popup),
        raw_count: $result['raw'],
        read_count: $result['read'],
    );
}

wp_register_ability('novamira/bricksforge-edit-popup', [
    'label' => __('Edit Bricksforge Popup', domain: 'novamira-pro'),
    'description' => __(
        'Edits a Bricksforge popup in place (partial merge update): only the provided keys change, every other stored key is preserved. The id cannot change. Returns the full updated config.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'properties' => array_merge(['id' => [
            'type' => 'string',
            'description' => 'The popup id (from bricksforge-list-popups).',
        ]], brf_popup_schema_properties()),
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'popup' => ['type' => 'object'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_edit_popup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Merge semantics — omit keys you do not want to change. Set active=false to pause a popup without deleting it.',
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
function bricksforge_edit_popup(array $input): array|WP_Error
{
    $id = isset($input['id']) && is_string($input['id']) ? $input['id'] : '';
    $validated = brf_validate_popup_input($input);
    if ($validated instanceof WP_Error) {
        return $validated;
    }

    $result = brf_with_lock('popups', static function () use ($id, $validated): mixed {
        $popups = brf_raw_popups();
        foreach ($popups as $index => $popup) {
            if (brf_popup_id($popup) !== $id) {
                continue;
            }

            if (!brf_popup_is_representable($popup)) {
                return brf_error(
                    code: 'bricksforge_popup_unreadable',
                    message: sprintf(
                        'Popup "%s" cannot be edited safely: its stored config is too deeply nested to round-trip without loss. Delete and recreate it instead.',
                        $id,
                    ),
                    data: ['status' => 422],
                );
            }

            $merged = array_merge(brf_popup_to_output($popup), $validated);
            /** @var mixed $updated */
            $updated = json_decode((string) wp_json_encode($merged));
            if (!is_object($updated)) {
                return brf_error(
                    code: 'bricksforge_popup_encode_failed',
                    message: 'Could not encode the popup config.',
                    data: ['status' => 500],
                );
            }
            $popups[$index] = $updated;
            brf_write_popups($popups);

            return $updated;
        }

        return brf_error(
            code: 'bricksforge_popup_not_found',
            message: sprintf('No Bricksforge popup with id "%s".', $id),
            data: ['status' => 404],
        );
    });
    if ($result instanceof WP_Error) {
        return $result;
    }

    return brf_popup_fire_warnings([
        'id' => $id,
        'popup' => brf_popup_to_output($result),
    ], $result);
}

wp_register_ability('novamira/bricksforge-delete-popup', [
    'label' => __('Delete Bricksforge Popup', domain: 'novamira-pro'),
    'description' => __(
        'Permanently removes a Bricksforge popup config by id (every stored row carrying that id, if duplicates exist). The Bricks template used as its content is NOT deleted. To pause a popup instead, edit it with active=false.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The popup id (from bricksforge-list-popups).'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_delete_popup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Irreversible for the config (the content template survives). Prefer active=false when the user might want it back.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricksforge_delete_popup(array $input): array|WP_Error
{
    $id = isset($input['id']) && is_string($input['id']) ? $input['id'] : '';
    $found = brf_with_lock('popups', static function () use ($id): bool {
        $popups = brf_raw_popups();
        $kept = [];
        $removed = false;
        foreach ($popups as $popup) {
            if (brf_popup_id($popup) === $id) {
                $removed = true;
                continue;
            }
            $kept[] = $popup;
        }
        if ($removed) {
            brf_write_popups($kept);
        }

        return $removed;
    });
    if ($found instanceof WP_Error) {
        return $found;
    }

    if (!$found) {
        return brf_error(
            code: 'bricksforge_popup_not_found',
            message: sprintf('No Bricksforge popup with id "%s".', $id),
            data: ['status' => 404],
        );
    }

    return [
        'id' => $id,
        'deleted' => true,
    ];
}
