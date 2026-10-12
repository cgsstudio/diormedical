<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * WPBakery user-templates storage — wp_options-backed by the
 * `wpb_js_templates` option that WPBakery uses for its native
 * "My Templates" feature.
 */

/**
 * Returns all WPBakery user templates from the wp option.
 *
 * PHP auto-casts numeric-looking string keys to int when an array is
 * serialized through update_option/get_option. The returned shape is
 * therefore array-key (int|string), not necessarily string. Callers
 * that surface the key to the agent must cast it to string.
 *
 * @return array<array-key, array<string, mixed>>
 */
function wpb_get_templates(): array
{
    /** @var array<array-key, array<string, mixed>>|false $raw */
    $raw = get_option('wpb_js_templates', []);
    return is_array($raw) ? $raw : [];
}

/**
 * Persists the full templates array to the wp option. Returns true on success.
 *
 * Key type matches `wpb_get_templates()` — `array-key` because PHP
 * auto-casts numeric-looking string keys to int when round-tripped
 * through update_option/get_option.
 *
 * @param array<array-key, array<string, mixed>> $templates
 */
function wpb_save_templates(array $templates): bool
{
    return update_option('wpb_js_templates', $templates, autoload: false);
}
