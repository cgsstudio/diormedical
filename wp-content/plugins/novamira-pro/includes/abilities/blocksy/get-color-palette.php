<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: read the Blocksy global color palette with default resolution.
 */

wp_register_ability('novamira/blocksy-get-color-palette', [
    'label' => __('Get Blocksy Color Palette', domain: 'novamira-pro'),
    'description' => __(
        'Reads the Blocksy global color palette (the `colorPalette` theme_mod). Blocksy never persists defaults — on a fresh install the theme_mod is absent and the effective palette comes from the option schema — so each color reports whether it is stored or a schema default. Colors are emitted as {id, color, css_variable, source} rows; the css_variable (--theme-palette-color-N) is how derived globals (fontColor, linkColor, headings) and Gutenberg reference palette slots. Also reports the number of user-saved custom palettes.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'source' => [
                'type' => 'string',
                'enum' => ['default', 'stored', 'mixed'],
                'description' => '`default` = nothing stored, schema defaults throughout; `stored` = every color comes from the theme_mod; `mixed` = stored value covers only part of the slots.',
            ],
            'colors' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'color' => ['type' => 'string'],
                        'css_variable' => ['type' => 'string'],
                        'source' => ['type' => 'string', 'enum' => ['default', 'stored']],
                    ],
                    'required' => ['id', 'color', 'css_variable', 'source'],
                    'additionalProperties' => false,
                ],
            ],
            'custom_palettes_count' => [
                'type' => 'integer',
                'description' => 'User-saved palettes in the `blocksy_custom_palettes` option (not counting the 12 predefined ones).',
            ],
            'extra_stored_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Non-color keys found in the stored theme_mod (legacy pre-2.0 shapes kept `palettes` / `current_palette` here). Informational; capped at 20 keys with an explicit trailing truncation marker when more exist.',
            ],
            'omitted_slots' => [
                'type' => 'integer',
                'description' => 'Slot ids beyond the 24-slot response cap that were omitted from `colors` (0 normally; non-zero only when a corrupted stored value invented extra slots).',
            ],
        ],
        'required' => ['source', 'colors', 'custom_palettes_count', 'extra_stored_keys', 'omitted_slots'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_get_color_palette',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to inspect the site palette before proposing color changes. Reference palette slots by css_variable (var(--theme-palette-color-N)) when styling — derived Blocksy globals point at those variables, so changing a slot recolors everything bound to it. source=default means the user never customized the palette.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     source: string,
 *     colors: list<array{id: string, color: string, css_variable: string, source: string}>,
 *     custom_palettes_count: int,
 *     extra_stored_keys: list<string>,
 *     omitted_slots: int,
 * }|WP_Error
 */
function blocksy_get_color_palette(): array|WP_Error
{
    if (!blocksy_theme_available()) {
        return bly_theme_inactive_error();
    }

    $index = bly_option_index_or_error();
    if ($index instanceof WP_Error) {
        return $index;
    }

    $default_palette = bly_default_palette_or_error($index);
    if ($default_palette instanceof WP_Error) {
        return $default_palette;
    }

    /** @var mixed $stored_raw */
    $stored_raw = bly_stored_mods()['colorPalette'] ?? null;
    $stored_palette = is_array($stored_raw) ? bly_palette_color_map($stored_raw) : [];
    $extra_stored_keys = is_array($stored_raw) ? bly_extra_stored_keys($stored_raw) : [];

    // Merge per slot: a stored palette normally covers every slot, but a
    // partial write (or a legacy migration leftover) must not blank the rest.
    // Slot count is bounded so a corrupted stored value (color999, thousands
    // of invented slots) cannot balloon the response or emit CSS variables
    // the theme never defines — omitted_slots signals how many were cut.
    $slot_ids = array_keys($default_palette + $stored_palette);
    usort($slot_ids, static fn(string $a, string $b): int => strnatcmp($a, $b));
    $omitted_slots = max(0, count($slot_ids) - BLY_MAX_PALETTE_SLOTS);
    $slot_ids = array_slice($slot_ids, offset: 0, length: BLY_MAX_PALETTE_SLOTS);

    $colors = [];
    $stored_count = 0;
    foreach ($slot_ids as $slot_id) {
        $from_stored = array_key_exists($slot_id, $stored_palette);
        if ($from_stored) {
            $stored_count++;
        }
        $colors[] = [
            'id' => $slot_id,
            'color' => $from_stored ? $stored_palette[$slot_id] : $default_palette[$slot_id],
            'css_variable' =>
                '--theme-palette-color-' . (string) preg_replace('/\D/', replacement: '', subject: $slot_id),
            'source' => $from_stored ? 'stored' : 'default',
        ];
    }

    $source = 'default';
    if ($stored_count > 0) {
        $source = $stored_count === count($colors) ? 'stored' : 'mixed';
    }

    /** @var mixed $custom_raw */
    $custom_raw = get_option('blocksy_custom_palettes', []);

    return [
        'source' => $source,
        'colors' => $colors,
        'custom_palettes_count' => is_array($custom_raw) ? count($custom_raw) : 0,
        'extra_stored_keys' => $extra_stored_keys,
        'omitted_slots' => $omitted_slots,
    ];
}

/**
 * The default palette slots from the resolved option schema. Defaults come
 * from the INSTALLED theme's own schema, never from a copied snapshot — a
 * missing/malformed palette definition is an explicit failure, not a cue
 * to fabricate plausible colors.
 *
 * @param array<string, array{id: string, type: string, label: string, section: string, option: array<array-key, mixed>}> $index
 * @return array<string, string>|WP_Error
 */
function bly_default_palette_or_error(array $index): array|WP_Error
{
    /** @var mixed $default_raw */
    $default_raw = $index['colorPalette']['option']['value'] ?? null;
    $default_palette = is_array($default_raw) ? bly_palette_color_map($default_raw) : [];
    if ($default_palette === []) {
        return bly_schema_error('the colorPalette definition carries no default color slots');
    }
    return $default_palette;
}

/**
 * Non-color keys present in the stored colorPalette theme_mod (legacy
 * pre-2.0 bookkeeping like `palettes` / `current_palette`). Reported to
 * the agent as informational context, never merged into the slots. A
 * corrupted stored value could carry any number of junk keys of any
 * length, so the list is bounded: at most BLY_MAX_EXTRA_STORED_KEYS
 * payload keys, each emitted string capped at BLY_MAX_EXTRA_KEY_BYTES
 * bytes TOTAL (truncated keys keep a trailing `[key truncated]` marker
 * within that budget), and dropping keys beyond the cap is SIGNALED with
 * one extra trailing marker element — the marker is not counted against
 * the key cap, so a fully-truncated response holds 21 strings.
 *
 * @param array<array-key, mixed> $stored_raw
 * @return list<string>
 */
function bly_extra_stored_keys(array $stored_raw): array
{
    $marker = '… [key truncated]';
    $out = [];
    $omitted = 0;
    foreach (array_keys($stored_raw) as $key) {
        if (!is_string($key) || preg_match('/^color\d+$/', $key) === 1) {
            continue;
        }
        if (count($out) >= BLY_MAX_EXTRA_STORED_KEYS) {
            $omitted++;
            continue;
        }
        if (strlen($key) > BLY_MAX_EXTRA_KEY_BYTES) {
            $budget = BLY_MAX_EXTRA_KEY_BYTES - strlen($marker);
            $key = bly_truncate_utf8_bytes($key, $budget) . $marker;
        }
        $out[] = $key;
    }
    if ($omitted > 0) {
        $out[] = sprintf('[+%d more keys omitted beyond the %d-key cap]', $omitted, BLY_MAX_EXTRA_STORED_KEYS);
    }
    return $out;
}

/**
 * Byte-bounded, character-safe UTF-8 truncation: at most `$budget` bytes,
 * never splitting a multibyte sequence — a plain substr() could emit
 * invalid UTF-8 that wp_json_encode() then chokes on. mb_strcut() does
 * this natively; without mbstring (WP's compat layer does not polyfill
 * mb_strcut) the pure-PHP fallback below applies. The fallback is its own
 * function so tests exercise it directly on mbstring-equipped
 * environments too.
 */
function bly_truncate_utf8_bytes(string $value, int $budget): string
{
    if ($budget <= 0) {
        return '';
    }
    if (function_exists('mb_strcut')) {
        return mb_strcut($value, start: 0, length: $budget, encoding: 'UTF-8');
    }
    return bly_truncate_utf8_bytes_fallback($value, $budget);
}

/**
 * Pure-PHP equivalent of the mb_strcut() call above: byte-cut, then back
 * off one byte at a time until the result is valid UTF-8 again
 * (`preg //u` fails on any split sequence — at most 3 backoff steps for a
 * cut 4-byte character; a key that was invalid UTF-8 to begin with
 * degrades to '' rather than being echoed).
 */
function bly_truncate_utf8_bytes_fallback(string $value, int $budget): string
{
    if ($budget <= 0) {
        return '';
    }
    $cut = substr($value, offset: 0, length: $budget);
    while ($cut !== '' && preg_match('//u', $cut) !== 1) {
        $cut = substr($cut, offset: 0, length: strlen($cut) - 1);
    }
    return $cut;
}

/**
 * Reduce a raw palette array (stored theme_mod or schema default) to a
 * clean {colorN => color-string} map, dropping legacy bookkeeping keys and
 * malformed entries. A "color" longer than BLY_MAX_COLOR_VALUE_BYTES is not
 * a color (palette values are hex / rgb() / var() references) and is
 * treated as malformed rather than echoed into the response.
 *
 * @param array<array-key, mixed> $raw
 * @return array<string, string>
 */
function bly_palette_color_map(array $raw): array
{
    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $key => $entry) {
        if (!is_string($key) || preg_match('/^color\d+$/', $key) !== 1) {
            continue;
        }
        if (!is_array($entry) || !isset($entry['color']) || !is_string($entry['color'])) {
            continue;
        }
        if (strlen($entry['color']) > BLY_MAX_COLOR_VALUE_BYTES) {
            continue;
        }
        $out[$key] = $entry['color'];
    }
    return $out;
}
