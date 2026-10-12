<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Helpers for the Breakdance design tokens — variables, global classes
 * and global settings. Each token blob lives in a `wp_options` entry
 * Breakdance writes through `Breakdance\Data\set_global_option`; the
 * stored value is JSON-encoded once by the Data API. Some option values are
 * arrays, while fields named `*_json_string` may themselves intentionally be
 * inner JSON strings. We always expose decoded arrays to ability callbacks and
 * mirror each native writer's value shape when persisting.
 *
 *   variables_json_string             → OxygenVariable[]
 *   variables_collections_json_string → string[]
 *   breakdance_classes_json_string    → CSSSelector[]  ({name, type, properties})
 *   global_settings_json_string       → nested settings tree
 */

/**
 * Normalize a value returned by `Breakdance\Data\get_global_option`.
 *
 * `get_global_option` already runs Breakdance's own
 * `decode_after_reading_from_wp` (one `json_decode`). Variable options normally
 * arrive here as arrays. Global classes and global settings intentionally
 * arrive as inner JSON strings because Breakdance's consumers decode them a
 * second time. Accept either shape so values written with the wrong historical
 * encoding remain readable and self-heal on their next write.
 *
 * @param mixed $value
 * @return array<int|string, mixed>
 */
function bd_decode_json_option(mixed $value): array
{
    if (is_array($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode(json: $value, associative: true);
    if (!is_array($decoded)) {
        return [];
    }
    return $decoded;
}

/**
 * @return list<array<string, mixed>>
 */
function bd_read_variables(): array
{
    /** @var mixed $raw */
    $raw = \Breakdance\Data\get_global_option(field_name: 'variables_json_string');
    $decoded = bd_decode_json_option($raw);
    $out = [];
    /** @var mixed $item */
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        /** @var array<string, mixed> $item */
        $out[] = $item;
    }
    return $out;
}

/**
 * @param list<array<string, mixed>> $variables
 *
 * Breakdance's native variables writer passes an array directly. Unlike global
 * classes and global settings, the variables consumer tolerates/uses that
 * decoded array shape.
 */
function bd_write_variables(array $variables): void
{
    \Breakdance\Data\set_global_option(field_name: 'variables_json_string', value: $variables);
    bd_regenerate_global_css();
}

/**
 * @return list<string>
 */
function bd_read_variable_collections(): array
{
    /** @var mixed $raw */
    $raw = \Breakdance\Data\get_global_option(field_name: 'variables_collections_json_string');
    $decoded = bd_decode_json_option($raw);
    $out = [];
    /** @var mixed $item */
    foreach ($decoded as $item) {
        if (!is_string($item)) {
            continue;
        }
        $out[] = $item;
    }
    return $out;
}

/**
 * @return list<array<string, mixed>>
 */
function bd_read_global_classes(): array
{
    /** @var mixed $raw */
    $raw = \Breakdance\Data\get_global_option(field_name: 'breakdance_classes_json_string');
    $decoded = bd_decode_json_option($raw);
    $out = [];
    /** @var mixed $item */
    foreach ($decoded as $item) {
        if (!is_array($item)) {
            continue;
        }
        /** @var array<string, mixed> $item */
        $out[] = $item;
    }
    return $out;
}

/**
 * @param list<array<string, mixed>> $classes
 * @return true|\WP_Error
 */
function bd_write_global_classes(array $classes): bool|\WP_Error
{
    $encoded = wp_json_encode($classes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return bd_error('bd_invalid_input', __(
            'Failed to encode Breakdance global classes; no changes were written.',
            domain: 'novamira-pro',
        ));
    }
    \Breakdance\Data\set_global_option(field_name: 'breakdance_classes_json_string', value: $encoded);
    bd_regenerate_global_css();
    return true;
}

/**
 * @return array<string, mixed>
 */
function bd_read_global_settings(): array
{
    /** @var mixed $raw */
    $raw = \Breakdance\Data\get_global_option(field_name: 'global_settings_json_string');
    $decoded = bd_decode_json_option($raw);
    if ($decoded === []) {
        return [];
    }
    $out = [];
    /** @var mixed $value */
    foreach ($decoded as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * @param array<string, mixed> $settings
 * @return true|\WP_Error
 */
function bd_write_global_settings(array $settings): bool|\WP_Error
{
    $encoded = wp_json_encode($settings, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!is_string($encoded)) {
        return bd_error('bd_invalid_input', __(
            'Failed to encode Breakdance global settings; no changes were written.',
            domain: 'novamira-pro',
        ));
    }
    \Breakdance\Data\set_global_option(field_name: 'global_settings_json_string', value: $encoded);
    bd_regenerate_global_css();
    return true;
}

/**
 * Force Breakdance to regenerate the cached global CSS file after a
 * design-token write. Without this, front-end pages keep serving the
 * pre-edit CSS until the user opens the builder, hits a Breakdance
 * native save, or the per-post cache rebuilds lazily on next render.
 *
 * The Breakdance cache regen entrypoint is internal but stable across
 * 2.7+ — guarded with `function_exists` so a future rename does not
 * fatal the integration.
 */
function bd_regenerate_global_css(): void
{
    if (function_exists('\\Breakdance\\Render\\generateCacheForGlobalSettings')) {
        \Breakdance\Render\generateCacheForGlobalSettings();
    }
}

/**
 * Locate a variable by its id. Variables use a string id that the
 * builder assigns at creation time; if the caller passes an id that
 * doesn't exist, returns null.
 *
 * @param list<array<string, mixed>> $variables
 * @return array{0: int, 1: array<string, mixed>}|null
 */
function bd_find_variable(array $variables, string $id): ?array
{
    foreach ($variables as $i => $variable) {
        if (isset($variable['id']) && (string) $variable['id'] === $id) {
            return [$i, $variable];
        }
    }
    return null;
}

/**
 * Locate a variable by its `cssVariableName`. CSS variables are
 * referenced at render time by name (var(--<cssVariableName>)), so a
 * collision means two records map to the same CSS variable and the
 * later-defined one silently shadows the earlier one in the browser.
 *
 * @param list<array<string, mixed>> $variables
 * @return array{0: int, 1: array<string, mixed>}|null
 */
function bd_find_variable_by_css_name(array $variables, string $css_name): ?array
{
    foreach ($variables as $i => $variable) {
        if (isset($variable['cssVariableName']) && (string) $variable['cssVariableName'] === $css_name) {
            return [$i, $variable];
        }
    }
    return null;
}

/**
 * Locate a global class by its name (class names act as ids).
 *
 * @param list<array<string, mixed>> $classes
 * @return array{0: int, 1: array<string, mixed>}|null
 */
function bd_find_global_class(array $classes, string $name): ?array
{
    foreach ($classes as $i => $class) {
        if (isset($class['name']) && (string) $class['name'] === $name) {
            return [$i, $class];
        }
    }
    return null;
}

/**
 * Slugify a string into a stable id (used when callers do not supply
 * an explicit id on create). Matches the conservative shape Breakdance
 * uses: lowercase, dashes, no whitespace or special chars.
 */
function bd_make_id(string $hint): string
{
    $lower = strtolower($hint);
    $slug = preg_replace(pattern: '~[^a-z0-9]+~', replacement: '-', subject: $lower);
    if (!is_string($slug)) {
        $slug = '';
    }
    $slug = trim($slug, characters: '-');
    // Non-slugifiable hint (`!!!`, `🎨`, etc.): fall back to a cryptographic
    // random suffix. The previous `md5(microtime)` derivative collided 95%
    // under k=10000 (microtime resolution on busy hosts collapses sub-ms
    // calls). 8 random bytes give us 2^64 entropy with no clock dependency.
    return $slug !== '' ? $slug : 'var-' . bin2hex(random_bytes(length: 4));
}
