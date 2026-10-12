<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Abilities: discover and set Divi 5 module interactions (motion — a trigger on
 * the module fires an effect on the module).
 *
 * A module's interactions live at
 * `module.decoration.interactions.desktop.value.interactions` as a list of
 * interaction objects. Interactions are NOT responsive: Divi reads them only
 * from the `desktop` value (see InteractionsScriptData::generate_data). Each
 * item is shaped `{id, enableInteraction, trigger, triggerClass, effect, target,
 * timeDelay, …}`;
 * `triggerClass`/`target.targetClass` are derived from a shared per-module id
 * and are what ties the interaction to its DOM element at render.
 *
 * v1 is SELF-TARGET only: the module that carries the interaction is both the
 * trigger and the target (click me → toggle me). Cross-module targeting is a
 * deferred follow-up. Catalog verified against Divi's generated interaction
 * schema, interaction editor, and frontend runtime.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * The triggers a module interaction can fire on. Keyed by trigger id; each has
 * a label and the extra `settings` it reads on top of the common `timeDelay`.
 *
 * @return array<string, array{label: string, settings: list<string>}>
 */
function divi_interaction_triggers(): array
{
    return [
        'click' => ['label' => 'Click', 'settings' => []],
        'mouseEnter' => ['label' => 'Mouse Enter', 'settings' => []],
        'mouseExit' => ['label' => 'Mouse Exit', 'settings' => []],
        'viewportEnter' => ['label' => 'Viewport Enter', 'settings' => []],
        'viewportExit' => ['label' => 'Viewport Exit', 'settings' => []],
        'load' => ['label' => 'Load', 'settings' => []],
        'breakpointEnter' => ['label' => 'Breakpoint Enter', 'settings' => ['breakpointName']],
        'breakpointExit' => ['label' => 'Breakpoint Exit', 'settings' => ['breakpointName']],
    ];
}

/**
 * The effects a module interaction can run (v1: the effects with a confirmed
 * Divi 5 runtime handler). Keyed by effect id; each has a label and its extra
 * `settings` keys (on top of the common `timeDelay`).
 *
 * @return array<string, array{label: string, settings: list<string>}>
 */
function divi_interaction_effects(): array
{
    return [
        'toggleVisibility' => ['label' => 'Toggle visibility (show/hide)', 'settings' => []],
        'scrollToElement' => ['label' => 'Scroll to the module', 'settings' => []],
        'mirrorMouseMovement' => [
            'label' => 'Mirror mouse movement (parallax)',
            'settings' => ['sensitivity', 'mouseMovementType'],
        ],
    ];
}

/** Valid `mouseMovementType` values for the mirrorMouseMovement effect. */
const NOVAMIRA_DIVI_MOUSE_MOVEMENT_TYPES = ['translate', 'scale', 'opacity', 'tilt', 'rotate'];

wp_register_ability('novamira/divi-list-interaction-types', [
    'label' => __('List Divi Interaction Types', domain: 'novamira-pro'),
    'description' => __(
        'Lists the interaction triggers and effects a module can use, so you can build motion (a trigger on the module runs an effect on it). Returns "triggers" (click, mouseEnter, mouseExit, viewportEnter, viewportExit, load, breakpointEnter, breakpointExit) and "effects" (toggleVisibility, scrollToElement, mirrorMouseMovement), each with a label and its extra "settings" keys. Pass these to novamira/divi-set-interactions. Interactions are not responsive (desktop only). v1 targets the module itself (self-target).',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'triggers' => ['type' => 'array', 'items' => ['type' => 'object']],
            'effects' => ['type' => 'array', 'items' => ['type' => 'object']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_list_interaction_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover the runtime-supported interaction triggers and effects (and their settings) before calling divi-set-interactions.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-set-interactions', [
    'label' => __('Set Divi Interactions', domain: 'novamira-pro'),
    'description' => __(
        'Sets the interactions (motion) on the module at "address": a trigger on the module runs an effect on the module itself (self-target). "interactions" is a list; each item is {trigger, effect, settings?}. "trigger" and "effect" are ids from novamira/divi-list-interaction-types; "settings" holds the effect/trigger-specific keys (e.g. sensitivity + mouseMovementType for mirrorMouseMovement, breakpointName for breakpoint triggers, timeDelay for any). Replaces the module\'s current interactions. Interactions are desktop-only (not responsive). Example: [{"trigger":"click","effect":"toggleVisibility"}] hides the module when clicked; on a self-target, visibility changes are one-way except with breakpoint triggers. viewportExit may fire immediately when the module starts outside the viewport. Pair mirrorMouseMovement with mouseEnter; the effect starts when the page loads. Accepts the post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'address' => ['type' => 'string', 'description' => 'Module address, e.g. "0/0/0/0".'],
            'interactions' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'List of {trigger, effect, settings?}. Replaces the module\'s current interactions.',
            ],
        ],
        'required' => ['address', 'interactions'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'address' => ['type' => 'string'],
            'interactions' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_set_interactions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Add motion to a module with a runtime-supported trigger id. Build each item from divi-list-interaction-types (trigger + effect + the effect\'s settings). The list replaces the module\'s current interactions; interactions apply on desktop only and target the module itself. Self-target toggleVisibility is a one-way hide except with breakpoint triggers; viewportExit may fire immediately outside the viewport. Use module.decoration.animation or module.decoration.scroll for scroll reveals. Pair mirrorMouseMovement with mouseEnter; it starts when the page loads.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function divi_list_interaction_types(array $input): array
{
    $triggers = [];
    foreach (divi_interaction_triggers() as $id => $def) {
        $triggers[] = ['trigger' => $id, 'label' => $def['label'], 'settings' => $def['settings']];
    }
    $effects = [];
    foreach (divi_interaction_effects() as $id => $def) {
        $effects[] = ['effect' => $id, 'label' => $def['label'], 'settings' => $def['settings']];
    }
    return ['triggers' => $triggers, 'effects' => $effects];
}

/**
 * A hyphen-free interaction id derived deterministically from its inputs. It
 * MUST be hyphen-free: Divi extracts the id from the trigger class with
 * `et-interaction-trigger-([a-zA-Z0-9]+)` (Module.php), which stops at the first
 * hyphen — a UUID would be truncated and the DOM `data-interaction-trigger`
 * would never match. Deterministic (md5 hex) so re-running the same
 * set-interactions on the same module yields identical ids: idempotent, no
 * churn of the DOM classes an external stylesheet might key on.
 */
function divi_interaction_id(string ...$parts): string
{
    return substr(md5(implode(':', $parts)), offset: 0, length: 16);
}

/**
 * Build one interaction object (the stored envelope), or a WP_Error when
 * invalid. v1 self-target: the module is both trigger and target, so the
 * trigger/target classes both derive from `$module_id` (the shared per-module
 * id the caller also stamps into `module.decoration.interactionTarget`). The
 * item's own `id` is a distinct deterministic value (the script-data key).
 *
 * @param mixed $raw
 * @param array<string, mixed> $context  {post_id, address, index, module_id}
 * @return array<string, mixed>|WP_Error
 */
function divi_build_interaction($raw, array $context)
{
    if (!is_array($raw)) {
        return divi_error('invalid_input', message: 'Each interaction must be an object {trigger, effect, settings?}.');
    }
    $trigger = is_string($raw['trigger'] ?? null) ? $raw['trigger'] : '';
    $effect = is_string($raw['effect'] ?? null) ? $raw['effect'] : '';
    /** @var array<string, mixed> $settings */
    $settings = is_array($raw['settings'] ?? null) ? $raw['settings'] : [];

    $invalid = divi_interaction_input_error($trigger, $effect, $settings);
    if ($invalid instanceof WP_Error) {
        return $invalid;
    }

    return divi_interaction_envelope($trigger, $effect, $settings, $context);
}

/**
 * Assemble the stored interaction envelope for a validated {trigger, effect,
 * settings}. Self-target: the trigger/target classes come from the shared
 * per-module id in `$context['module_id']`; the item's own id is deterministic.
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $context
 * @return array<string, mixed>
 */
function divi_interaction_envelope(string $trigger, string $effect, array $settings, array $context): array
{
    $module_id = is_string($context['module_id'] ?? null) ? $context['module_id'] : '';
    $item = [
        'id' => divi_interaction_id(
            (string) ($context['post_id'] ?? ''),
            (string) ($context['address'] ?? ''),
            (string) ($context['index'] ?? ''),
            $trigger,
            $effect,
        ),
        'trigger' => $trigger,
        'triggerClass' => 'et-interaction-trigger-' . $module_id,
        'effect' => $effect,
        'target' => [
            'targetClass' => 'et-interaction-target-' . $module_id,
            'label' => 'This Module',
            'moduleId' => '',
            'targetType' => 'module',
        ],
        'timeDelay' => divi_coerce_interaction_setting('timeDelay', $settings['timeDelay'] ?? null),
        'enableInteraction' => 'on',
    ];

    // Effect/trigger-specific settings, taken only from the keys each declares.
    $allowed = array_merge(
        divi_interaction_triggers()[$trigger]['settings'],
        divi_interaction_effects()[$effect]['settings'],
    );
    foreach ($allowed as $key) {
        if (!array_key_exists($key, $settings)) {
            continue;
        }
        $item[$key] = divi_coerce_interaction_setting($key, $settings[$key]);
    }

    return $item;
}

/**
 * Validate a caller's trigger/effect/settings against the catalog and the
 * per-trigger requirements. Returns a WP_Error, or null when valid.
 *
 * @param array<string, mixed> $settings
 */
function divi_interaction_input_error(string $trigger, string $effect, array $settings): ?WP_Error
{
    if (!array_key_exists($trigger, divi_interaction_triggers())) {
        $replacement = match ($trigger) {
            'onClick' => ' Use "click" instead.',
            'onHover' => ' Use "mouseEnter" or "mouseExit" instead.',
            'onMouseMove'
                => ' Use "mouseEnter" instead. With "mirrorMouseMovement", the effect starts when the page loads.',
            'onScroll' => ' Use "viewportEnter" or "viewportExit" for viewport boundary events instead.',
            'onSubmit' => ' Form submission is not a Divi interaction trigger.',
            'onLoad' => ' Use "load" instead.',
            'onView' => ' Use "viewportEnter" or "viewportExit" instead.',
            default => '',
        };
        return divi_error(
            'unknown_trigger',
            message: sprintf(
                'Unknown trigger "%1$s".%2$s Valid trigger ids: %3$s.',
                $trigger,
                $replacement,
                implode(', ', array_keys(divi_interaction_triggers())),
            ),
            status: 422,
        );
    }
    if (!array_key_exists($effect, divi_interaction_effects())) {
        return divi_error(
            'unknown_effect',
            message: sprintf('Unknown effect "%s". Use novamira/divi-list-interaction-types.', $effect),
            status: 422,
        );
    }
    // A breakpoint trigger with no breakpointName is stored fine but never
    // fires — refuse it rather than silently accept a dead interaction.
    if (in_array($trigger, ['breakpointEnter', 'breakpointExit'], strict: true)) {
        $bp = is_string($settings['breakpointName'] ?? null) ? trim($settings['breakpointName']) : '';
        if ($bp === '') {
            return divi_error(
                'invalid_input',
                message: sprintf('Trigger "%s" requires a "breakpointName" setting (e.g. "tablet").', $trigger),
                status: 422,
            );
        }
    }
    return null;
}

/**
 * Coerce one interaction setting value to the shape Divi expects: clamp
 * sensitivity 0-100, validate the mouseMovementType enum, and normalize a
 * duration (timeDelay) — a bare number becomes "<n>ms" instead of being
 * silently dropped. Other string settings (breakpointName) pass through.
 *
 * @param mixed $value
 * @return mixed
 */
function divi_coerce_interaction_setting(string $key, $value)
{
    if ($key === 'sensitivity') {
        return max(0, min(100, (int) $value));
    }
    if ($key === 'mouseMovementType') {
        return in_array($value, NOVAMIRA_DIVI_MOUSE_MOVEMENT_TYPES, strict: true) ? $value : 'translate';
    }
    if ($key === 'timeDelay') {
        if (is_int($value) || is_float($value)) {
            return (int) $value . 'ms';
        }
        return is_string($value) && $value !== '' ? $value : '0ms';
    }
    return is_string($value) ? $value : '';
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_set_interactions(array $input)
{
    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $address = divi_string_arg($input, key: 'address');
    if (trim($address) === '') {
        return divi_error('invalid_input', message: 'An "address" is required (e.g. "0/0/0/0").');
    }

    /** @var mixed $raw */
    $raw = $input['interactions'] ?? null;
    if (!is_array($raw) || $raw === [] || divi_is_list($raw) === false) {
        return divi_error(
            'invalid_input',
            message: '"interactions" must be a non-empty list of {trigger, effect, settings?}.',
        );
    }

    // Self-target: one hyphen-free id for the module, shared by every
    // interaction's trigger/target class and stamped into interactionTarget so
    // Divi marks this module as both the trigger and the effect's target.
    $module_id = divi_interaction_id((string) $post->ID, $address);

    $list = [];
    $index = 0;
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        $built = divi_build_interaction($entry, [
            'post_id' => $post->ID,
            'address' => $address,
            'index' => $index,
            'module_id' => $module_id,
        ]);
        if ($built instanceof WP_Error) {
            return $built;
        }
        $list[] = $built;
        $index++;
    }

    // Desktop-only: Divi reads interactions solely from the desktop value.
    // `interactionTarget` (a plain attr, not breakpoint-scoped) marks the module
    // as the effect's target — without it the target class/attr never render.
    $patch = [
        'module' => [
            'decoration' => [
                'interactions' => ['desktop' => ['value' => ['interactions' => $list]]],
                'interactionTarget' => $module_id,
            ],
        ],
    ];
    $result = divi_merge_module_attrs($post, $address, $patch);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'post_id' => $post->ID,
        'address' => $address,
        'interactions' => count($list),
    ];
}
