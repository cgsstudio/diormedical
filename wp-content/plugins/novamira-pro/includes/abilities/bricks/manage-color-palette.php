<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;

/**
 * Abilities: Bricks Color Palette — list / get / add / edit / delete.
 *
 * Bricks stores BRICKS_DB_COLOR_PALETTE as a list of palettes, each
 * containing a colors array. Two color-entry formats coexist:
 *   legacy:            {id, name, raw|hex|rgb}
 *   Color Manager 2.2+: {id, name, light, dark?, raw?: 'var(--x)'}
 *
 * In the 2.2+ format `light`/`dark` hold the values and `raw` (when
 * present) is only a var() reference naming the CSS variable. CRITICAL
 * renderer precedence (Bricks assets.php, generate_inline_css_color_vars):
 * `rgb`, `hex`, and a non-var() `raw` override `light`/`dark` in BOTH
 * modes — so an entry carrying them can never render its dark variant.
 * The dark-mode CSS is emitted under `:root[data-brx-theme="dark"]`, and
 * the builder offers the toggle-mode element once at least one color has
 * a non-empty `dark`.
 *
 * The typical install has one palette named "Default" with N colors,
 * but users may create multiple palettes. All abilities work at the
 * two-level palette → color depth.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-list-color-palette', [
    'label' => __('List Bricks Color Palette', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Bricks color palette store. Returns all palettes with their colors nested: [{id, name, colors: [{id, name, raw|light, dark?}, ...]}]. Supply palette_id to return only that palette\'s colors as a flat list [{palette_id, color_id, name, raw, light?, dark?}]. Also returns `dark_mode_ready` (a stored dark value was observed in the CSS this Bricks compiles for the dark context) and `dark_mode_support`, which says what that observation showed: \"materialised\", \"absent\", \"not_determinable\" or \"no_dark_values\". novamira/bricks-get-design-system reports the same two fields from the same source.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'palette_id' => [
                'type' => 'string',
                'description' => 'Optional. When supplied, return only that palette\'s colors as a flat list.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'palettes' => ['type' => 'array'],
            'colors' => ['type' => 'array'],
            'dark_mode_ready' => [
                'type' => 'boolean',
                'description' => 'True when a stored dark value was OBSERVED to reach the CSS this Bricks compiles for the dark context — the value is swapped and the output checked, not predicted. Read dark_mode_support beside it: false can mean no dark values are stored, or that they are stored and do not reach it, or that the compile could not be run here.',
            ],
            'dark_mode_support' => [
                'type' => 'string',
                'enum' => ['materialised', 'absent', 'not_determinable', 'no_dark_values'],
                'description' => 'What was observed by recompiling the stored palette for the dark context, swapping one stored dark value at a time: \'materialised\' (at least one follows the swap, so it is what the dark context renders), \'absent\' (dark values are stored, EVERY one of them was observed, and none follows — the light value renders in both modes), \'not_determinable\' (nothing rendered among the colours that were observed, and at least one could not be observed or was not reached — so no claim either way), or \'no_dark_values\' (nothing carries a dark value). Bounded work: a run of colours is probed rather than the whole palette, so a very large design system answers \'not_determinable\' instead of \'absent\' when nothing among the probed colours renders.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_color_palette',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_list_color_palette(array $input): array|WP_Error
{
    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_COLOR_PALETTE, []);
    $palettes = is_array($raw) ? array_values($raw) : [];

    /** @var mixed $palette_id_filter */
    $palette_id_filter = $input['palette_id'] ?? null;

    if (!is_string($palette_id_filter) || $palette_id_filter === '') {
        return [
            'palettes' => $palettes,
            'dark_mode_ready' => cp_dark_mode_ready($palettes),
            'dark_mode_support' => cp_observe_dark_rendering($palettes),
        ];
    }

    $flat = cp_list_flat_colors($palettes, $palette_id_filter);
    if ($flat instanceof WP_Error) {
        return $flat;
    }
    $flat['dark_mode_ready'] = cp_dark_mode_ready($palettes);
    $flat['dark_mode_support'] = cp_observe_dark_rendering($palettes);
    return $flat;
}

/**
 * Whether any color across ALL palettes carries a dark variant. Mirrors the
 * condition Bricks uses to offer the toggle-mode element in the builder and
 * to emit the `:root[data-brx-theme="dark"]` CSS block.
 *
 * @param array<mixed> $palettes
 */
function cp_any_dark_variant(array $palettes): bool
{
    /** @var mixed $palette */
    foreach ($palettes as $palette) {
        if (!is_array($palette)) {
            continue;
        }
        $colors = is_array($palette['colors'] ?? null) ? $palette['colors'] : [];
        /** @var mixed $color */
        foreach ($colors as $color) {
            if (is_array($color) && is_string($color['dark'] ?? null) && $color['dark'] !== '') {
                return true;
            }
        }
    }
    return false;
}

/**
 * Returns a flat list of {palette_id, color_id, name, raw} for the given palette.
 *
 * @param  array<mixed> $palettes
 * @return array<string, mixed>|WP_Error
 */
function cp_list_flat_colors(array $palettes, string $palette_id): array|WP_Error
{
    /** @var mixed $palette */
    foreach ($palettes as $palette) {
        if (!is_array($palette) || ($palette['id'] ?? null) !== $palette_id) {
            continue;
        }
        $colors = is_array($palette['colors'] ?? null) ? $palette['colors'] : [];
        $flat = [];
        /** @var mixed $color */
        foreach ($colors as $color) {
            if (!is_array($color)) {
                continue;
            }
            $flat[] = cp_color_to_entry($palette_id, $color);
        }
        return ['colors' => $flat];
    }
    return bricks_error('not_found', sprintf('Palette "%s" not found.', $palette_id));
}

wp_register_ability('novamira/bricks-get-color-palette-entry', [
    'label' => __('Get Bricks Color Palette Entry', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Bricks color palette entry by palette_id and color_id. Returns {palette_id, color_id, name, raw, light?, dark?} — `light`/`dark` appear on colors stored in the Color Manager light/dark format (dark-mode support).',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'palette_id' => ['type' => 'string', 'description' => 'The id of the palette that contains the color.'],
            'color_id' => ['type' => 'string', 'description' => 'The id of the color entry to read.'],
        ],
        'required' => ['palette_id', 'color_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'entry' => ['type' => 'object'],
            'css_file' => [
                'type' => 'string',
                'description' => 'External CSS file mode: "regenerated" when the palette CSS file was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise.',
            ],
            'warning' => [
                'type' => 'string',
                'description' => 'Advisories about this entry: a raw var(...) reference that emits no --bricks-color-{id} CSS, and what was observed about a dark value that does not reach the dark-context CSS or could not be checked. Absent when there is nothing to say.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_get_color_palette_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_get_color_palette_entry(array $input): array|WP_Error
{
    $ids = cp_require_ids($input);
    if ($ids instanceof WP_Error) {
        return $ids;
    }
    $palette_id = $ids['palette_id'];
    $color_id = $ids['color_id'];

    $result = cp_find_color(cp_load_palettes(), $palette_id, $color_id);
    if ($result === null) {
        return cp_color_not_found($palette_id, $color_id);
    }

    return ['entry' => cp_color_to_entry($palette_id, $result['color'])];
}

wp_register_ability('novamira/bricks-add-color-palette-entry', [
    'label' => __('Add Bricks Color Palette Entry', domain: 'novamira-pro'),
    'description' => __(
        'Adds a new color to the Bricks color palette store. Provide a name and a raw color value (hex, rgba, hsl, oklch). Supply palette_id to target a specific palette; if omitted the first palette is used, creating a "Default" palette when none exist. Optionally pass `dark` to give the color a dark-mode variant: the color is then stored in the Color Manager light/dark format and participates in Bricks native dark mode (toggle-mode element, `:root[data-brx-theme="dark"]` CSS). The color id is auto-generated. Returns {entry: {palette_id, color_id, name, raw, light?, dark?}}.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'palette_id' => [
                'type' => 'string',
                'description' => 'Optional. The palette to add the color to. Defaults to the first palette (creating "Default" if none exists).',
            ],
            'name' => [
                'type' => 'string',
                'description' => 'Display name for the color (e.g. "Brand Primary").',
            ],
            'raw' => [
                'type' => 'string',
                'description' => 'Color value (hex, rgba, hsl, oklch — e.g. "#1A2845", "rgba(26,40,69,1)", "oklch(30% 0.1 250)").',
            ],
            'dark' => [
                'type' => 'string',
                'description' => 'Optional dark-mode value for the color (hex, rgba, hsl, oklch). Takes effect only where this Bricks has Color Manager dark mode — `dark_mode_support` in bricks-list-color-palette reports what was observed. When set, `raw` becomes the light value (stored as `light`) and Bricks renders `dark` under `:root[data-brx-theme="dark"]`. Not compatible with a var(...) raw.',
            ],
        ],
        'required' => ['name', 'raw'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('palette colours'),
            'entry' => ['type' => 'object'],
            'dark_render' => [
                'type' => 'string',
                'enum' => ['materialised', 'absent', 'not_determinable'],
                'description' => 'Present when this write set a dark value: what the installed build then did with it. The stored value is swapped for a test value and the palette recompiled for the dark context — never a search for a property name, which Bricks changes when raw names a variable. \'materialised\' — the output followed the swap, so this colour\'s dark value is what the dark context renders; \'absent\' — it did not, so the light value renders in both modes; \'not_determinable\' — the generator could not be run or failed, so nothing was observed. The write is accepted and stored in every case, including when the observation itself cannot be made.',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => 'External CSS file mode: "regenerated" when the palette CSS file was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise.',
            ],
            'warning' => [
                'type' => 'string',
                'description' => 'Advisories about this entry: a raw var(...) reference that emits no --bricks-color-{id} CSS, and what was observed about a dark value that does not reach the dark-context CSS or could not be checked. Absent when there is nothing to say.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_add_color_palette_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

function bricks_add_color_palette_entry(array $input): array|WP_Error
{
    /** @var mixed $name */
    $name = $input['name'] ?? '';
    if (!is_string($name) || trim($name) === '') {
        return bricks_error('invalid_input', message: 'name is required.');
    }

    /** @var mixed $color_raw */
    $color_raw = $input['raw'] ?? '';
    if (!is_string($color_raw) || trim($color_raw) === '') {
        return bricks_error('invalid_input', message: 'raw is required.');
    }

    $dark = cp_read_dark_input($input, trim($color_raw));
    if ($dark instanceof WP_Error) {
        return $dark;
    }

    /** @var mixed $palette_id_input */
    $palette_id_input = $input['palette_id'] ?? null;
    $palette_id_filter = is_string($palette_id_input) && $palette_id_input !== '' ? $palette_id_input : null;

    $palettes = cp_load_palettes();

    $resolved = cp_resolve_target_palette($palettes, $palette_id_filter);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }

    $palettes = $resolved['palettes'];
    $target_palette_id = $resolved['palette_id'];

    $new_color = cp_new_color(bricks_random_id(prefix: 'col-'), trim($name), trim($color_raw), $dark);

    $palettes = cp_append_color_to_palette($palettes, $target_palette_id, $new_color);

    $css_file = cp_save_palettes($palettes);

    // Everything reported about the write is derived from the palette AS
    // STORED, re-read after the commit — never from the array we meant to
    // store, which says what we intended rather than what is there.
    $stored = cp_load_palettes();
    $response = cp_build_entry_response($target_palette_id, $new_color, $stored);
    $response['css_file'] = $css_file;

    return cp_verified($response, $palettes);
}

/**
 * Reads and validates the optional `dark` input for add. Returns the trimmed
 * dark value ('' when absent), or a WP_Error when the raw is a var()
 * reference — the only reason a dark value is refused. Whether this build
 * renders it is not predicted here: the write is stored and what happened to
 * it is reported afterwards.
 *
 * @param array<array-key, mixed> $input
 */
function cp_read_dark_input(array $input, string $raw): string|WP_Error
{
    /** @var mixed $dark_input */
    $dark_input = $input['dark'] ?? null;
    $dark = is_string($dark_input) ? trim($dark_input) : '';
    if ($dark === '') {
        return '';
    }
    if (preg_match('/^var\(/i', $raw) === 1) {
        return bricks_error(
            'invalid_input',
            message: 'dark cannot be combined with a var(...) raw — pass a literal light color in raw.',
        );
    }
    return $dark;
}

/**
 * Builds a new color entry. Without a dark value: the legacy {id, name, raw}
 * shape. With one: the Color Manager 2.2+ {id, name, light, dark} shape —
 * values live in light/dark because a plain raw would override both at
 * render time (see the header note).
 *
 * @return array<string, string>
 */
function cp_new_color(string $color_id, string $name, string $raw, string $dark): array
{
    if ($dark === '') {
        return ['id' => $color_id, 'name' => $name, 'raw' => $raw];
    }
    return ['id' => $color_id, 'name' => $name, 'light' => $raw, 'dark' => $dark];
}

/**
 * Builds the standard {entry, warning?} response shape for add/edit. Warns
 * when the color raw is a CSS var() reference AND the entry has no
 * light/dark values — Bricks's Assets::generate_inline_css_color_vars()
 * skips such entries (no hex/rgb/hsl/oklch), so the entry never emits
 * --bricks-color-{id} and id-based references silently fall back. (A var()
 * raw WITH light/dark values is the Color Manager 2.2+ shape: the var name
 * comes from raw and the values from light/dark, so CSS is emitted.)
 *
 * @param  array<array-key, mixed> $color
 * @return array{entry: array<array-key, mixed>, warning?: string}
 */
/**
 * Adds the read-back verification of the palette option to a response;
 * stored_count counts colours across palettes.
 *
 * @param array<string, mixed>     $result
 * @param array<array-key, mixed>  $palettes the list as handed to cp_save_palettes
 * @return array<string, mixed>
 */
function cp_verified(array $result, array $palettes): array
{
    $count = static function (mixed $stored): int {
        $total = 0;
        /** @var mixed $palette */
        foreach (is_array($stored) ? $stored : [] as $palette) {
            $total += is_array($palette) && is_array($palette['colors'] ?? null) ? count($palette['colors']) : 0;
        }

        return $total;
    };

    return bricks_with_option_write_verification($result, BRICKS_DB_COLOR_PALETTE, array_values($palettes), $count);
}

/**
 * Persists the palette option (external file mode: the CSS directory is
 * made sure to exist so Bricks' listener can rewrite color-palettes.min.css)
 * and returns the css_file status.
 *
 * @param array<array-key, mixed> $palettes
 */
function cp_save_palettes(array $palettes): string
{
    bricks_ensure_css_dir();
    $started = time();
    update_option(BRICKS_DB_COLOR_PALETTE, array_values($palettes));
    bricks_forget_known_css_variables();

    return bricks_global_css_file_status('color-palettes.min.css', $started);
}

/**
 * @param array<array-key, mixed> $color
 * @return array<string, mixed>
 */
function cp_build_entry_response(string $palette_id, array $color, ?array $stored = null): array
{
    $response = ['entry' => cp_color_to_entry($palette_id, $color)];
    if (is_array($stored) && is_string($color['dark'] ?? null) && $color['dark'] !== '') {
        $response = cp_with_dark_observation($response, $color, $stored);
    }
    $raw = is_string($color['raw'] ?? null) ? $color['raw'] : '';
    $has_values =
        is_string($color['light'] ?? null) && $color['light'] !== ''
        || is_string($color['dark'] ?? null) && $color['dark'] !== '';
    if (preg_match('/^var\(/i', $raw) === 1 && !$has_values) {
        $response['warning'] = 'raw with var(...) does not emit --bricks-color-{id} CSS, so id-based references will silently fall back. Pass a literal color value (hex/rgb/hsl/oklch) for entries that need to be referenced by id; reference the variable directly in element settings if you only want to use it inline.';
    }
    return $response;
}

/**
 * Finds this colour in the stored palette and observes its dark value.
 *
 * Located by POSITION, so a colour id that repeats in another palette cannot
 * make us observe the wrong slot — and matched on CONTENT, so a stored colour
 * that merely shares the id (an older one left behind by a write that was
 * blocked or altered) is not observed in place of the colour this response
 * describes. Presence of the id is not proof that the entry is there.
 *
 * @param  array<array-key, mixed> $color
 * @param  array<array-key, mixed> $stored
 * @return 'materialised'|'absent'|'not_determinable'
 */
function cp_locate_and_observe(array $color, array $stored): string
{
    $colour_id = (string) ($color['id'] ?? '');
    foreach (array_keys($stored) as $palette_key) {
        /** @var mixed $palette */
        $palette = $stored[$palette_key];
        if (!is_array($palette) || !is_array($palette['colors'] ?? null)) {
            continue;
        }
        foreach (array_keys($palette['colors']) as $colour_key) {
            /** @var mixed $candidate */
            $candidate = $palette['colors'][$colour_key];
            if (!is_array($candidate) || (string) ($candidate['id'] ?? '') !== $colour_id) {
                continue;
            }
            // Matched on CONTENT, not on the id alone: the stored colour has
            // to be the colour this response describes. If the write was
            // blocked or altered, the store still holds a colour with this
            // id — an older one — and observing that would answer a question
            // about a different colour than the entry being reported.
            if ($candidate !== $color) {
                return 'not_determinable';
            }

            return cp_observe_dark_value($stored, $palette_key, $colour_key);
        }
    }

    return 'not_determinable';
}

/**
 * Adds what the installed generator did with THIS colour. The palette handed
 * in is the one read back with get_option() AFTER the commit, so the answer
 * describes what the store answers with rather than what the write meant to
 * put there — those are not the same thing when something rejects the write.
 * Not a read of the database row: WordPress's options cache and the filters
 * in front of get_option() both sit in between.
 *
 * @param  array{entry: array<array-key, mixed>, dark_render?: string, warning?: string} $response
 * @param  array<array-key, mixed> $color
 * @param  array<array-key, mixed> $stored
 * @return array{entry: array<array-key, mixed>, dark_render?: string, warning?: string}
 */
function cp_with_dark_observation(array $response, array $color, array $stored): array
{
    // Contained: this runs AFTER the write is committed, so nothing it does
    // may turn a successful write into an exception. A vendor explosion
    // downgrades the observation to "could not tell" and the response still
    // describes the write that happened.
    try {
        $state = cp_locate_and_observe($color, $stored);
    } catch (\Throwable) {
        $state = 'not_determinable';
    }
    $response['dark_render'] = $state;

    $note = cp_dark_render_note($state);
    if ($note === null) {
        return $response;
    }
    $existing = $response['warning'] ?? '';
    $response['warning'] = $existing !== '' ? $existing . ' Also: ' . $note : ucfirst($note);

    return $response;
}

/**
 * What to tell the caller about the dark value they just wrote, or null when
 * it materialised and nothing needs saying.
 *
 * Each string is produced by one build state and was read back from that
 * state before shipping.
 */
function cp_dark_render_note(string $observed): ?string
{
    if ($observed === 'absent') {
        return 'the dark value was stored, but changing it makes no difference to the CSS this Bricks compiles for the dark context: the value was swapped for a test value, the palette recompiled, and the output did not follow — so the light value renders in both modes. The write stands and is reversible. To REMOVE the dark value, call novamira/bricks-edit-color-palette-entry with dark set to an empty string — omitting `dark` leaves the stored one in place — or send a different value; read novamira/bricks-list-color-palette to see what is stored.';
    }
    if ($observed === 'not_determinable') {
        return 'the dark value was stored, but whether it renders could NOT be observed: this Bricks does not expose the colour-variable generator, or it failed when run, so no CSS was produced to look at. Nothing here says the value does or does not work. This response\'s css_file field says whether the palette stylesheet was rewritten, and novamira/bricks-list-color-palette shows the stored light/dark pair; seeing the colour change needs the page opened in a dark-mode context.';
    }

    return null;
}

/**
 * Whether ONE stored colour's dark value reaches the CSS this build compiles
 * for the dark context — asked by changing that value and seeing whether the
 * output follows.
 *
 * WHY IT IS A DIFFERENTIAL, and not a search for the colour's property. The
 * previous version looked for `--<something><colour id><something>` and was
 * wrong in both directions: Bricks replaces the id-based property name with
 * the variable named in `raw` when a colour uses the supported
 * `{raw: "var(--x)", light, dark}` shape, so a rendered colour read as absent;
 * and an id that is a substring of another id matched that other colour's
 * declaration, so an unrendered colour read as present. Both came from trying
 * to reproduce a vendor naming rule. This does not know or care what the
 * property is called: it swaps this colour's dark value for a sentinel that
 * does not occur in the baseline output, recompiles, and asks whether the
 * sentinel came through as a declared value. If it did, this colour's dark
 * value is what the dark context renders for it.
 *
 * Nothing is written, no option is read and no CSS file is touched — the
 * palette is compiled in memory, so a stale file from an earlier write cannot
 * influence the answer.
 *
 * @param  array<array-key, mixed> $palettes the palette list AS STORED
 * @return 'materialised'|'absent'|'not_determinable'
 */
function cp_observe_dark_value(array $palettes, int|string $palette_key, int|string $colour_key): string
{
    $baseline = cp_compiled_dark_css($palettes);
    if ($baseline === null) {
        return 'not_determinable';
    }

    return cp_observe_against_baseline($palettes, $palette_key, $colour_key, $baseline);
}

/**
 * The swap itself, against a baseline compiled once. Compiling the baseline is
 * the same work as compiling a probe, so an aggregate that asked this question
 * of many colours would otherwise recompile the whole palette twice per
 * colour.
 *
 * @param  array<array-key, mixed> $palettes the palette list AS STORED
 * @return 'materialised'|'absent'|'not_determinable'
 */
function cp_observe_against_baseline(
    array $palettes,
    int|string $palette_key,
    int|string $colour_key,
    string $baseline,
): string {
    $sentinel = cp_sentinel_absent_from($baseline);
    if ($sentinel === null) {
        return 'not_determinable';
    }

    /** @var mixed $palette */
    $palette = $palettes[$palette_key] ?? null;
    if (!is_array($palette) || !is_array($palette['colors'] ?? null)) {
        return 'not_determinable';
    }
    $colors = $palette['colors'];
    /** @var mixed $colour */
    $colour = $colors[$colour_key] ?? null;
    if (!is_array($colour)) {
        return 'not_determinable';
    }
    $colour['dark'] = $sentinel;
    $colors[$colour_key] = $colour;
    $palette['colors'] = $colors;
    $probe = $palettes;
    $probe[$palette_key] = $palette;

    $css = cp_compiled_dark_css($probe);
    if ($css === null) {
        return 'not_determinable';
    }

    return cp_value_declared($css, $sentinel) ? 'materialised' : 'absent';
}

/**
 * A colour value that does not already occur in this CSS, so its appearance
 * after the swap can only have come from the swap.
 */
function cp_sentinel_absent_from(string $baseline): ?string
{
    for ($attempt = 0; $attempt < 8; $attempt++) {
        $candidate = sprintf('#%06x', random_int(min: 0x10_0000, max: 0xff_fffe));
        if (!str_contains($baseline, $candidate)) {
            return $candidate;
        }
    }

    return null;
}

/**
 * Whether this CSS declares the given value — in value position, after a
 * colon and before the end of the declaration. Never a bare substring search:
 * comments are stripped first, and a name is not a value.
 */
function cp_value_declared(string $css, string $value): bool
{
    $without_comments = (string) preg_replace('#/\*.*?\*/#s', replacement: '', subject: $css);

    return preg_match('/:\s*[^;{}]*' . preg_quote($value, delimiter: '/') . '[^;{}]*[;}]/i', $without_comments) === 1;
}

/**
 * The dark-context CSS the installed build compiles for this palette, or null
 * when its generator cannot be run here.
 *
 * The one static the generator fills is snapshotted and put back.
 *
 * @param array<array-key, mixed> $palettes
 */
function cp_compiled_dark_css(array $palettes): ?string
{
    if (
        !class_exists(\Bricks\Assets::class) || !method_exists(\Bricks\Assets::class, 'generate_inline_css_color_vars')
    ) {
        return null;
    }

    /** @var list<array<string, mixed>> $rows */
    $rows = [];
    /** @var mixed $palette */
    foreach ($palettes as $palette) {
        if (!is_array($palette)) {
            continue;
        }
        /** @var array<string, mixed> $palette */
        $rows[] = $palette;
    }

    $restore = \Bricks\Assets::$global_colors;
    try {
        /** @var mixed $css */
        $css = \Bricks\Assets::generate_inline_css_color_vars($rows, 'dark');
    } catch (\Throwable) {
        return null;
    } finally {
        \Bricks\Assets::$global_colors = $restore;
    }

    return is_string($css) ? $css : null;
}

/**
 * The state to report for a whole palette store, for the readers:
 *  - "materialised"     — at least one stored dark value reaches the dark CSS;
 *  - "absent"           — dark values are stored, every one of them was
 *                         observed, and none of them reaches it;
 *  - "not_determinable" — nothing rendered among the colours observed, and at
 *                         least one could not be observed or was past the
 *                         probe bound and never looked at;
 *  - "no_dark_values"   — nothing carries a dark value, so nothing to observe.
 *
 * @param  array<array-key, mixed> $palettes
 * @return 'materialised'|'absent'|'not_determinable'|'no_dark_values'
 */
function cp_observe_dark_rendering(array $palettes): string
{
    $positions = cp_dark_colour_positions($palettes);
    if ($positions === []) {
        return 'no_dark_values';
    }
    $baseline = cp_compiled_dark_css($palettes);
    if ($baseline === null) {
        return 'not_determinable';
    }

    // 'absent' is a statement about the WHOLE palette — none of its dark
    // values renders — so it may only be reached when every colour was
    // actually looked at and every one of them came back absent. A colour
    // whose swap could not be observed, and a colour past the probe bound
    // that was never observed at all, both leave the answer undetermined:
    // one of them may well be rendering, and saying "absent" over it is the
    // false negative this observation exists to avoid. A positively
    // materialised colour still settles it, because one rendering colour is
    // all "the dark context renders palette colours" claims.
    $unresolved = false;
    $probed = 0;
    foreach ($positions as [$palette_key, $colour_key]) {
        if ($probed >= NOVAMIRA_BRICKS_DARK_PROBE_LIMIT) {
            $unresolved = true;

            break;
        }
        $probed++;
        $observed = cp_observe_against_baseline($palettes, $palette_key, $colour_key, $baseline);
        if ($observed === 'materialised') {
            return 'materialised';
        }
        if ($observed !== 'absent') {
            $unresolved = true;
        }
    }

    return $unresolved ? 'not_determinable' : 'absent';
}

/**
 * How many colours the palette-wide observation probes before it stops. Each
 * probe recompiles the palette, so the work on a design system with thousands
 * of colours is bounded rather than proportional to it: one baseline compile
 * plus at most this many. Reaching the bound without finding a rendering
 * colour leaves the answer undetermined — never 'absent' — because the
 * colours past it were not looked at.
 */
const NOVAMIRA_BRICKS_DARK_PROBE_LIMIT = 24;

/**
 * The [palette key, colour key] of every stored colour carrying a dark value.
 *
 * @param  array<array-key, mixed> $palettes
 * @return list<array{0: int|string, 1: int|string}>
 */
function cp_dark_colour_positions(array $palettes): array
{
    $positions = [];
    foreach (array_keys($palettes) as $palette_key) {
        /** @var mixed $palette */
        $palette = $palettes[$palette_key];
        if (!is_array($palette) || !is_array($palette['colors'] ?? null)) {
            continue;
        }
        foreach (array_keys($palette['colors']) as $colour_key) {
            /** @var mixed $color */
            $color = $palette['colors'][$colour_key];
            if (is_array($color) && is_string($color['dark'] ?? null) && $color['dark'] !== '') {
                $positions[] = [$palette_key, $colour_key];
            }
        }
    }

    return $positions;
}

/**
 * Whether this site is set up for Bricks' native dark mode: a stored dark
 * value was OBSERVED to reach the CSS this build compiles for the dark
 * context. One definition, called by every ability that reports it.
 *
 * @param array<array-key, mixed> $palettes
 */
function cp_dark_mode_ready(array $palettes): bool
{
    return cp_observe_dark_rendering($palettes) === 'materialised';
}

/**
 * Migrates a color's effective light value into the `light` field and drops
 * the keys that would override light/dark at render time. The effective
 * value follows the renderer's own precedence: a non-var() raw wins, then
 * rgb, then hex; an already-set `light` is kept when no overriding key is
 * present. A var() raw is preserved — in the 2.2+ format it names the CSS
 * variable while light/dark carry the values.
 *
 * @param  array<array-key, mixed> $color
 * @return array<array-key, mixed>
 */
function cp_consolidate_light_value(array $color): array
{
    $effective = cp_effective_light_value($color);
    if ($effective !== '') {
        $color['light'] = $effective;
    }

    return cp_strip_overriding_value_keys($color);
}

/**
 * The value the renderer currently shows for a color in light mode, following
 * its own precedence: a non-var() raw wins, then rgb, then hex. '' when none
 * of the overriding keys carries a value (the entry either already uses
 * `light` or only holds a var() reference).
 *
 * @param array<array-key, mixed> $color
 */
function cp_effective_light_value(array $color): string
{
    $raw = is_string($color['raw'] ?? null) ? trim($color['raw']) : '';
    if ($raw !== '' && preg_match('/^var\(/i', $raw) !== 1) {
        return $raw;
    }
    if (is_string($color['rgb'] ?? null) && $color['rgb'] !== '') {
        return $color['rgb'];
    }
    if (is_string($color['hex'] ?? null) && $color['hex'] !== '') {
        return $color['hex'];
    }
    return '';
}

/**
 * Removes the legacy value keys (`rgb`, `hex`, non-var() `raw`) that override
 * `light`/`dark` in Bricks's renderer, so the light/dark pair actually takes
 * effect. Keeps a var() raw (CSS variable name in the 2.2+ format).
 *
 * @param  array<array-key, mixed> $color
 * @return array<array-key, mixed>
 */
function cp_strip_overriding_value_keys(array $color): array
{
    unset($color['rgb'], $color['hex']);
    $raw = is_string($color['raw'] ?? null) ? trim($color['raw']) : '';
    if ($raw !== '' && preg_match('/^var\(/i', $raw) !== 1) {
        unset($color['raw']);
    }
    return $color;
}

/**
 * Resolves the target palette id and returns the (possibly modified) palettes array.
 * When palette_id_filter is null, defaults to the first palette or creates "Default".
 * Returns a WP_Error on failure.
 *
 * @param  array<mixed>  $palettes
 * @param  string|null   $palette_id_filter
 * @return array{palettes: array<mixed>, palette_id: string}|WP_Error
 */
function cp_resolve_target_palette(array $palettes, ?string $palette_id_filter): array|WP_Error
{
    if ($palette_id_filter !== null) {
        return cp_resolve_existing_palette($palettes, $palette_id_filter);
    }

    if (is_array($palettes[0] ?? null)) {
        $first_palette = $palettes[0];
        return ['palettes' => $palettes, 'palette_id' => (string) ($first_palette['id'] ?? '')];
    }

    // No palettes exist — create a default one
    $new_id = bricks_random_id(prefix: 'pal-');
    $palettes[] = ['id' => $new_id, 'name' => 'Default', 'colors' => []];
    return ['palettes' => $palettes, 'palette_id' => $new_id];
}

/**
 * Resolves a specific palette by id. Returns a WP_Error when not found.
 *
 * @param  array<mixed> $palettes
 * @return array{palettes: array<mixed>, palette_id: string}|WP_Error
 */
function cp_resolve_existing_palette(array $palettes, string $palette_id): array|WP_Error
{
    /** @var mixed $p */
    foreach ($palettes as $p) {
        if (is_array($p) && ($p['id'] ?? null) === $palette_id) {
            return ['palettes' => $palettes, 'palette_id' => $palette_id];
        }
    }
    return bricks_error('not_found', sprintf('Palette "%s" not found.', $palette_id));
}

/**
 * Appends a new color to the target palette's colors array.
 *
 * @param  array<mixed>            $palettes
 * @param  array<array-key, mixed> $new_color
 * @return array<mixed>
 */
function cp_append_color_to_palette(array $palettes, string $target_palette_id, array $new_color): array
{
    $palette_keys = array_keys($palettes);
    foreach ($palette_keys as $pk) {
        /** @var mixed $p */
        $p = $palettes[$pk];
        if (!is_array($p) || ($p['id'] ?? null) !== $target_palette_id) {
            continue;
        }
        $colors = is_array($p['colors'] ?? null) ? $p['colors'] : [];
        $colors[] = $new_color;
        $p['colors'] = $colors;
        $palettes[$pk] = $p;
        break;
    }
    return $palettes;
}

wp_register_ability('novamira/bricks-edit-color-palette-entry', [
    'label' => __('Edit Bricks Color Palette Entry', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Bricks color palette entry. Provide palette_id, color_id, plus at least one of: name (display name), raw (new color value) or dark (dark-mode variant). Partial update — omitted fields are left unchanged. Setting `dark` migrates a legacy color to the Color Manager light/dark format (its current value moves to `light`, exactly as the Bricks UI does) so the dark variant actually renders; on such colors a later `raw` edit updates the light value. Pass dark: "" to remove the dark variant. Once at least one color has a dark variant, Bricks offers the toggle-mode element.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'palette_id' => ['type' => 'string', 'description' => 'The id of the palette that contains the color.'],
            'color_id' => ['type' => 'string', 'description' => 'The id of the color entry to edit.'],
            'name' => ['type' => 'string', 'description' => 'New display name for the color.'],
            'raw' => [
                'type' => 'string',
                'description' => 'New color value (hex, rgba, hsl, oklch). On a color with a dark variant this updates the LIGHT value.',
            ],
            'dark' => [
                'type' => 'string',
                'description' => 'Dark-mode value (hex, rgba, hsl, oklch). Takes effect only where this Bricks has Color Manager dark mode — `dark_mode_support` in bricks-list-color-palette reports what was observed. Empty string removes the dark variant.',
            ],
        ],
        'required' => ['palette_id', 'color_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('palette colours'),
            'entry' => ['type' => 'object'],
            'dark_render' => [
                'type' => 'string',
                'enum' => ['materialised', 'absent', 'not_determinable'],
                'description' => 'Present when this write set a dark value: what the installed build then did with it. The stored value is swapped for a test value and the palette recompiled for the dark context — never a search for a property name, which Bricks changes when raw names a variable. \'materialised\' — the output followed the swap, so this colour\'s dark value is what the dark context renders; \'absent\' — it did not, so the light value renders in both modes; \'not_determinable\' — the generator could not be run or failed, so nothing was observed. The write is accepted and stored in every case, including when the observation itself cannot be made.',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => 'External CSS file mode: "regenerated" when the palette CSS file was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise.',
            ],
            'warning' => [
                'type' => 'string',
                'description' => 'Advisories about this entry: a raw var(...) reference that emits no --bricks-color-{id} CSS, and what was observed about a dark value that does not reach the dark-context CSS or could not be checked. Absent when there is nothing to say.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_edit_color_palette_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_edit_color_palette_entry(array $input): array|WP_Error
{
    $ids = cp_require_ids($input);
    if ($ids instanceof WP_Error) {
        return $ids;
    }
    $palette_id = $ids['palette_id'];
    $color_id = $ids['color_id'];

    /** @var mixed $new_name */
    $new_name = $input['name'] ?? null;
    /** @var mixed $new_raw */
    $new_raw = $input['raw'] ?? null;
    /** @var mixed $new_dark */
    $new_dark = $input['dark'] ?? null;

    if ($new_name === null && $new_raw === null && $new_dark === null) {
        return bricks_error('invalid_input', message: 'Pass at least one of name, raw or dark.');
    }

    $palettes = cp_load_palettes();
    $result = cp_find_color($palettes, $palette_id, $color_id);
    if ($result === null) {
        return cp_color_not_found($palette_id, $color_id);
    }

    $patched = cp_apply_color_patch($result['color'], $new_name, $new_raw, $new_dark);
    if ($patched instanceof WP_Error) {
        return $patched;
    }
    $color = $patched['color'];

    $palettes = cp_write_color($palettes, $result['palette_key'], $result['color_key'], $color);
    $css_file = cp_save_palettes($palettes);

    // Observed on the palette as stored, not as intended — see the add path.
    $stored = cp_load_palettes();
    $response = cp_build_entry_response($palette_id, $color, $stored);
    $response['css_file'] = $css_file;

    return cp_verified($response, $palettes);
}

/**
 * Applies the name / raw / dark patch to a color entry, handling the
 * legacy ↔ Color Manager 2.2+ format transitions.
 *
 * @param  array<array-key, mixed> $color
 * @return array{color: array<array-key, mixed>}|WP_Error
 */
function cp_apply_color_patch(array $color, mixed $new_name, mixed $new_raw, mixed $new_dark): array|WP_Error
{
    if (is_string($new_name) && $new_name !== '') {
        $color['name'] = $new_name;
    }

    $color = cp_apply_raw_patch($color, $new_raw, $new_dark);

    return cp_apply_dark_patch($color, $new_dark);
}

/**
 * Applies a raw-value patch. On dark-format colors (or when a dark value is
 * being set in the same call) the value goes to `light` — writing a plain
 * raw there would override BOTH modes at render time.
 *
 * @param  array<array-key, mixed> $color
 * @return array<array-key, mixed>
 */
function cp_apply_raw_patch(array $color, mixed $new_raw, mixed $new_dark): array
{
    if (!is_string($new_raw) || $new_raw === '') {
        return $color;
    }
    if (!cp_wants_dark_set($new_dark) && !cp_is_dark_format($color)) {
        $color['raw'] = $new_raw;
        return $color;
    }
    $color['light'] = trim($new_raw);
    return cp_strip_overriding_value_keys($color);
}

/**
 * Applies the dark patch: a non-empty value migrates the entry to the
 * light/dark format (erroring when no literal light value exists), an empty
 * string removes the variant, null leaves the entry untouched.
 *
 * @param  array<array-key, mixed> $color
 * @return array{color: array<array-key, mixed>}|WP_Error
 */
function cp_apply_dark_patch(array $color, mixed $new_dark): array|WP_Error
{
    if (!is_string($new_dark)) {
        return ['color' => $color];
    }
    if (!cp_wants_dark_set($new_dark)) {
        // dark: "" — remove the variant.
        unset($color['dark']);
        return ['color' => $color];
    }
    $color = cp_consolidate_light_value($color);
    if (!is_string($color['light'] ?? null) || $color['light'] === '') {
        return bricks_error(
            'invalid_input',
            message: 'This color has no literal light value (its raw is a var(...) reference) — pass raw with a literal color in the same call.',
        );
    }
    $color['dark'] = trim($new_dark);
    return ['color' => $color];
}

/**
 * Whether the `dark` input asks to SET a variant (non-empty string) rather
 * than remove it ('') or leave it untouched (absent).
 */
function cp_wants_dark_set(mixed $new_dark): bool
{
    return is_string($new_dark) && trim($new_dark) !== '';
}

/**
 * Whether a color entry already uses the Color Manager 2.2+ light/dark format.
 *
 * @param array<array-key, mixed> $color
 */
function cp_is_dark_format(array $color): bool
{
    return (
        is_string($color['light'] ?? null)
        && $color['light'] !== ''
        || is_string($color['dark'] ?? null)
        && $color['dark'] !== ''
    );
}

wp_register_ability('novamira/bricks-delete-color-palette-entry', [
    'label' => __('Delete Bricks Color Palette Entry', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Bricks color palette entry by palette_id and color_id. Hard delete — any reference to this color will lose the palette value. A second call for the same color fails with a not_found error.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'palette_id' => ['type' => 'string', 'description' => 'The id of the palette that contains the color.'],
            'color_id' => ['type' => 'string', 'description' => 'The id of the color entry to delete.'],
        ],
        'required' => ['palette_id', 'color_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('palette colours'),
            'deleted' => ['type' => 'boolean'],
            'css_file' => [
                'type' => 'string',
                'description' => 'External CSS file mode: "regenerated" when the palette CSS file was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_delete_color_palette_entry',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

function bricks_delete_color_palette_entry(array $input): array|WP_Error
{
    $ids = cp_require_ids($input);
    if ($ids instanceof WP_Error) {
        return $ids;
    }
    $palette_id = $ids['palette_id'];
    $color_id = $ids['color_id'];

    $palettes = cp_load_palettes();
    $result = cp_find_color($palettes, $palette_id, $color_id);
    if ($result === null) {
        return cp_color_not_found($palette_id, $color_id);
    }

    $pk = $result['palette_key'];
    $ck = $result['color_key'];

    /** @var array<array-key, mixed> $palette_arr */
    $palette_arr = $palettes[$pk];
    $colors_arr = is_array($palette_arr['colors'] ?? null) ? $palette_arr['colors'] : [];
    array_splice($colors_arr, offset: (int) $ck, length: 1);
    $palette_arr['colors'] = array_values($colors_arr);
    $palettes[$pk] = $palette_arr;

    $css_file = cp_save_palettes($palettes);

    return cp_verified(['deleted' => true, 'css_file' => $css_file], $palettes);
}

// ---------------------------------------------------------------------------
// Internal helpers
// ---------------------------------------------------------------------------

/**
 * Validates the palette_id + color_id inputs shared by get / edit / delete.
 *
 * @param  array<array-key, mixed> $input
 * @return array{palette_id: string, color_id: string}|WP_Error
 */
function cp_require_ids(array $input): array|WP_Error
{
    /** @var mixed $palette_id */
    $palette_id = $input['palette_id'] ?? '';
    if (!is_string($palette_id) || $palette_id === '') {
        return bricks_error('invalid_input', message: 'palette_id is required.');
    }
    /** @var mixed $color_id */
    $color_id = $input['color_id'] ?? '';
    if (!is_string($color_id) || $color_id === '') {
        return bricks_error('invalid_input', message: 'color_id is required.');
    }
    return ['palette_id' => $palette_id, 'color_id' => $color_id];
}

/**
 * Standard not-found error for a palette color addressed by palette_id + color_id.
 */
function cp_color_not_found(string $palette_id, string $color_id): WP_Error
{
    return bricks_error('not_found', sprintf('Color "%s" not found in palette "%s".', $color_id, $palette_id));
}

/**
 * Load palettes from the DB and return a list array.
 *
 * @return array<mixed>
 */
function cp_load_palettes(): array
{
    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_COLOR_PALETTE, []);
    return is_array($raw) ? array_values($raw) : [];
}

/**
 * Find a color inside a palettes list by palette_id + color_id.
 * Returns null when not found.
 *
 * @param  array<mixed> $palettes
 * @return array{palette_key: array-key, color_key: array-key, color: array<array-key, mixed>}|null
 */
function cp_find_color(array $palettes, string $palette_id, string $color_id): ?array
{
    $palette_keys = array_keys($palettes);
    foreach ($palette_keys as $pk) {
        /** @var mixed $palette */
        $palette = $palettes[$pk];
        if (!is_array($palette) || ($palette['id'] ?? null) !== $palette_id) {
            continue;
        }
        $colors = is_array($palette['colors'] ?? null) ? $palette['colors'] : [];
        $color_keys = array_keys($colors);
        foreach ($color_keys as $ck) {
            /** @var mixed $color */
            $color = $colors[$ck];
            if (!is_array($color) || ($color['id'] ?? null) !== $color_id) {
                continue;
            }
            return ['palette_key' => $pk, 'color_key' => $ck, 'color' => $color];
        }
    }
    return null;
}

/**
 * Writes a mutated color back to its position in the palettes list.
 *
 * @param  array<mixed>            $palettes
 * @param  array-key               $pk
 * @param  array-key               $ck
 * @param  array<array-key, mixed> $color
 * @return array<mixed>
 */
function cp_write_color(array $palettes, mixed $pk, mixed $ck, array $color): array
{
    /** @var array<array-key, mixed> $palette_arr */
    $palette_arr = $palettes[$pk];
    $colors_arr = is_array($palette_arr['colors'] ?? null) ? $palette_arr['colors'] : [];
    $colors_arr[$ck] = $color;
    $palette_arr['colors'] = $colors_arr;
    $palettes[$pk] = $palette_arr;
    return $palettes;
}

/**
 * Normalise a raw color array + palette_id into the public entry shape.
 * `light`/`dark` are included only when present (Color Manager 2.2+ format)
 * so legacy entries keep their exact historical shape.
 *
 * @param  array<array-key, mixed> $color
 * @return array<string, mixed>
 */
function cp_color_to_entry(string $palette_id, array $color): array
{
    $entry = [
        'palette_id' => $palette_id,
        'color_id' => $color['id'] ?? '',
        'name' => $color['name'] ?? '',
        'raw' => $color['raw'] ?? '',
    ];
    if (is_string($color['light'] ?? null) && $color['light'] !== '') {
        $entry['light'] = $color['light'];
    }
    if (is_string($color['dark'] ?? null) && $color['dark'] !== '') {
        $entry['dark'] = $color['dark'];
    }
    return $entry;
}
