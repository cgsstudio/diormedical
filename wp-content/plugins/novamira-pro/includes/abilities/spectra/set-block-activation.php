<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-set-block-activation', [
    'label' => __('Set Spectra Block Activation', domain: 'novamira-pro'),
    'description' => __(
        'Enable or disable a single Spectra block (`uagb/xxx` or `spectra-pro/xxx`) in the `_uagb_blocks` registry. Atomic read-modify-write so concurrent admin writes on unrelated blocks in the same option row do not clobber each other. The block must be present in the plugin catalogue (verified against novamira/spectra-list-blocks). Spectra front-end + editor caches are flushed after the write.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'block' => [
                'type' => 'string',
                'description' => 'Full block name, e.g. `uagb/container`.',
            ],
            'enabled' => [
                'type' => 'boolean',
                'description' => 'true → block available in the editor; false → block hidden from the editor.',
            ],
        ],
        'required' => ['block', 'enabled'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'block' => ['type' => 'string'],
            'enabled' => ['type' => 'boolean'],
            'changed' => ['type' => 'boolean'],
        ],
        'required' => ['block', 'enabled', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_set_block_activation',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Toggle a single block in the _uagb_blocks activation registry. Atomic and idempotent.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/** Option key for UAGB's block activation registry. Kept as a const so the ability layer never repeats the literal. */
const SPECTRA_BLOCK_ACTIVATION_OPTION = '_uagb_blocks';

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_set_block_activation(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    $block = is_string($input['block'] ?? null) ? $input['block'] : '';
    if ($block === '') {
        return sp_invalid_input('block is required');
    }
    if (!isset($input['enabled']) || !is_bool($input['enabled'])) {
        return sp_invalid_input('enabled must be a boolean');
    }
    $enabled = $input['enabled'];

    // Reject unknown blocks up-front so a mistyped slug never adds a
    // permanent junk row to the option blob.
    if (spectra_block_family($block) === 'unknown') {
        return sp_block_not_found($block);
    }
    $known = spectra_uagb_block_list();
    $registry = spectra_php_registry_index();
    if (!isset($known[$block]) && !isset($registry[$block])) {
        return sp_block_not_found($block);
    }

    $changed = $enabled ? spectra_enable_block_in_registry($block) : spectra_disable_block_in_registry($block);
    if ($changed) {
        sp_invalidate_spectra_cache();
    }

    return [
        'block' => $block,
        'enabled' => $enabled,
        'changed' => $changed,
    ];
}

/**
 * Remove the `_uagb_blocks` disabled marker for `$block`, if present. UAGB
 * treats missing rows as enabled, so the enable path only ever unsets.
 * Returns whether the option was actually written.
 */
function spectra_enable_block_in_registry(string $block): bool
{
    $applied = false;
    sp_option_write_json_atomic(SPECTRA_BLOCK_ACTIVATION_OPTION, function (array $registry) use (
        $block,
        &$applied,
    ): array {
        $normalised = spectra_normalise_block_activation_registry($registry);
        if (spectra_block_currently_enabled($normalised, $block)) {
            // Always return the normalised registry — a no-op on this block
            // is still a chance to migrate any legacy string row into the
            // modern array shape. `$applied` stays false so the caller
            // reports `changed=false`.
            return $normalised;
        }
        $applied = true;
        unset($normalised[$block]);
        return $normalised;
    });
    return $applied;
}

/**
 * Set the `_uagb_blocks` disabled marker for `$block`. UAGB's own storage
 * convention is `['status' => 'disabled']` — this helper matches that shape
 * so the editor JS unregistration path (which reads the same option) sees
 * the change consistently. Returns whether the option was actually written.
 */
function spectra_disable_block_in_registry(string $block): bool
{
    $applied = false;
    sp_option_write_json_atomic(SPECTRA_BLOCK_ACTIVATION_OPTION, function (array $registry) use (
        $block,
        &$applied,
    ): array {
        $normalised = spectra_normalise_block_activation_registry($registry);
        if (!spectra_block_currently_enabled($normalised, $block)) {
            // Same rationale as the enable path — return the normalised
            // registry even on a no-op so legacy shapes get migrated.
            return $normalised;
        }
        $applied = true;
        $normalised[$block] = ['status' => 'disabled'];
        return $normalised;
    });
    return $applied;
}

/**
 * Normalise the `_uagb_blocks` option payload so lookups do not depend on
 * whether the row is a string / array / stdClass — UAGB has flip-flopped
 * across releases. Only `array<string, array<string, mixed>>` entries are
 * kept; unknown shapes are dropped so the writer never emits garbage.
 *
 * @param array<array-key, mixed> $registry
 * @return array<string, array<string, mixed>>
 */
function spectra_normalise_block_activation_registry(array $registry): array
{
    $out = [];
    /** @var mixed $row */
    foreach ($registry as $name => $row) {
        if (!is_string($name) || $name === '') {
            continue;
        }
        if (is_array($row)) {
            /** @var array<string, mixed> $row */
            $out[$name] = $row;
            continue;
        }
        // Legacy UAGB installs may store the row as a bare string ('disabled')
        // instead of the current `['status' => 'disabled']` shape. Preserve
        // user intent by normalising the string form to the array form — a
        // silent drop would re-enable every legacy-disabled block on the
        // first write path this ability takes.
        if (is_string($row) && $row === 'disabled') {
            $out[$name] = ['status' => 'disabled'];
        }
    }
    return $out;
}

/**
 * Whether a block is currently enabled per the `_uagb_blocks` registry.
 * A row with `status = disabled` means disabled; anything else (missing row
 * or unexpected shape) is considered enabled — same interpretation UAGB's
 * own `blocks-deactivate.js` uses when it filters the editor block registry.
 *
 * @param array<string, array<string, mixed>> $registry
 */
function spectra_block_currently_enabled(array $registry, string $block): bool
{
    if (!isset($registry[$block])) {
        return true;
    }
    $row = $registry[$block];
    return ($row['status'] ?? '') !== 'disabled';
}
