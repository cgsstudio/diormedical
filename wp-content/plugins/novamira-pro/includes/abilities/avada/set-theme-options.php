<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/avada-set-theme-options', [
    'label' => __('Set Avada Theme Options', domain: 'novamira-pro'),
    'description' => __(
        'Whitelist-only write to `fusion_options`. Accepts a `settings` map of key → value; every key must be on the curated allowlist (~20 safe scalars — colors, layout, header/footer toggles, extras) AND must NOT be on the reserved list (raw `custom_css` / `custom_js` / license fields). Response reports `written[]` (keys whose stored value actually changed) and `unchanged[]` (submitted keys already at target — idempotent no-op). Cache flush after every write. The whole batch runs inside a `fusion_options` row lock so it never interleaves with a concurrent edit-global-color / edit-global-typography / set-theme-options call on the same row.',
        domain: 'novamira-pro',
    ),
    'category' => 'avada',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'settings' => [
                'type' => 'object',
                'minProperties' => 1,
                'additionalProperties' => [
                    'type' => ['string', 'number', 'boolean'],
                ],
            ],
        ],
        'required' => ['settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'written' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys whose stored value actually changed on this call. A key already at target is NOT listed (idempotent no-op).',
            ],
            'unchanged' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Keys submitted whose value already matched the stored value — nothing was written for them.',
            ],
            'settings' => [
                'type' => 'object',
                'additionalProperties' => true,
                'description' => 'Snapshot of every submitted key with its post-write value.',
            ],
        ],
        'required' => ['written', 'unchanged', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Avada\\avada_set_theme_options',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Whitelist-only writer for fusion_options. Unknown key → avada_unknown_setting. custom_css/custom_js reserved (use execute-php recipe). Cache flush after write.',
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
function avada_set_theme_options(array $input): array|WP_Error
{
    if (!avada_theme_available()) {
        return av_theme_not_active();
    }

    if (!isset($input['settings']) || !is_array($input['settings']) || $input['settings'] === []) {
        return av_invalid_input('settings map is required and must be non-empty');
    }
    /** @var array<string, mixed> $settings */
    $settings = $input['settings'];

    // Pre-validate every key AND every value BEFORE the first write —
    // same all-or-nothing pattern the Spectra Pro layer uses (adversarial
    // lens fix in the upstream branch). A single unknown key, reserved
    // key, non-scalar value, or unsafe string rejects the whole batch so
    // no partial write can leak (audit A2 F4 + F8).
    $allowlist = avada_writable_setting_keys();
    /** @var array<string, mixed> $sanitised */
    $sanitised = [];
    /** @var mixed $raw_value */
    foreach ($settings as $key => $raw_value) {
        if (avada_is_reserved_setting_key($key)) {
            return av_unknown_setting($key);
        }
        if (!in_array($key, $allowlist, strict: true)) {
            return av_unknown_setting($key);
        }
        /** @var mixed $clean_value */
        $clean_value = avada_sanitize_theme_option_value($raw_value);
        if ($clean_value === null) {
            return av_invalid_input(sprintf(
                'value for `%s` is not a safe scalar (strings must contain no angle brackets)',
                $key,
            ));
        }
        $sanitised[$key] = $clean_value;
    }

    // Every write path against `fusion_options` shares one row lock so
    // this batch never interleaves with a concurrent set-global-color /
    // set-global-typography / another set-theme-options call — first-
    // round review MAJOR-2 (formerly documented in the ability
    // description as "last-write-wins per key").
    $outcome = av_with_fusion_row_lock(
        /** @return array{written: list<string>, unchanged: list<string>, settings: array<string, mixed>}|WP_Error */
        static function () use ($sanitised): array|WP_Error {
            // Snapshot the current option row so we can compare per-key.
            /** @var mixed $current_raw */
            $current_raw = get_option(AVADA_OPTIONS_KEY, default_value: []);
            /** @var array<string, mixed> $current */
            $current = is_array($current_raw) ? $current_raw : [];

            /** @var list<string> $written */
            $written = [];
            /** @var list<string> $unchanged */
            $unchanged = [];
            /** @var array<string, mixed> $snapshot */
            $snapshot = [];

            /** @var mixed $value */
            foreach ($sanitised as $key => $value) {
                /** @var mixed $prev */
                $prev = $current[$key] ?? null;
                // Compare via WP's own equality basis on option storage
                // (`maybe_serialize`). String-cast comparison collapses
                // `true` vs `1` vs `'1'` (all `'1'`), which would let
                // genuine boolean → int writes silently skip — audit A2 F5.
                $is_same = maybe_serialize($prev) === maybe_serialize($value);
                if ($is_same) {
                    $unchanged[] = $key;
                    $snapshot[$key] = $prev;
                    continue;
                }
                $write_error = avada_write_fusion_setting($key, $value);
                if ($write_error instanceof WP_Error) {
                    return $write_error;
                }
                $written[] = $key;
                $snapshot[$key] = $value;
                // Update the local view so subsequent no-op checks in
                // the same batch don't reference stale state.
                $current[$key] = $value;
            }

            return [
                'written' => $written,
                'unchanged' => $unchanged,
                'settings' => $snapshot,
            ];
        },
    );

    if ($outcome instanceof WP_Error) {
        return $outcome;
    }

    if ($outcome['written'] !== []) {
        avada_flush_fusion_cache();
    }

    return $outcome;
}
