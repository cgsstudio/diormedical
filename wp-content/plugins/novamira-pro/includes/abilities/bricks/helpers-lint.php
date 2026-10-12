<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Advisory structural lint shared by set-content, insert-content and
 * patch-elements. It never blocks a write; it names the anti-pattern that
 * makes Bricks pages unmaintainable — a whole section's HTML pasted into one
 * text element — and points the agent at the native-element model and the
 * bricks-build-page skill.
 */

/**
 * Elements whose `text` setting is a free string an agent could stuff with
 * markup: the rich-text editor element, the plain text element and the
 * text link.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_TEXT_ELEMENTS = ['text', 'text-basic', 'text-link'];

/**
 * Markup that never belongs inside a text control on any of the text
 * elements: layout containers, embedded stylesheets and scripts, inline
 * style attributes. Each of these is a native element or a Bricks control.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_LAYOUT_MARKUP_PATTERNS = [
    '/<(section|div|main|article|aside|header|footer|nav|form|table|figure)\b/i',
    '/<style\b/i',
    '/<script\b/i',
    '/\sstyle\s*=\s*["\']/i',
];

/**
 * Markup that is legitimate prose inside the rich-text `text` element but
 * signals structural markup pasted inside the plain `text-basic` / `text-link`
 * elements: headings, lists, images, buttons.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_PLAIN_TEXT_MARKUP_PATTERNS = ['/<(h[1-6]|ul|ol|img|button|picture|video|iframe)\b/i'];

/**
 * Runs the structural lint over a flat element tree.
 *
 * `$focus_ids` restricts the per-element checks (raw layout HTML, empty
 * structural wrapper) to the elements a caller wrote; the single-text-root
 * check always looks at the whole area because it describes the area.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<string>|null          $focus_ids
 * @return array{warnings: list<string>, raw_layout_html_ids: list<string>, empty_structural_ids: list<string>}
 */
function bricks_lint_structure(array $elements, ?array $focus_ids = null): array
{
    $focus = $focus_ids === null ? null : array_fill_keys($focus_ids, value: true);
    $child_counts = bricks_child_counts($elements);

    $warnings = [];
    $raw_layout_ids = [];
    $empty_structural_ids = [];

    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        $name = (string) ($element['name'] ?? '');
        if ($id === '' || $focus !== null && !array_key_exists($id, $focus)) {
            continue;
        }

        $markup = bricks_text_element_layout_markup($element);
        if ($markup !== null) {
            $raw_layout_ids[] = $id;
            $warnings[] = sprintf(
                'Element "%s" (%s) carries %s inside its text — that is a block of raw HTML, not native Bricks content: it cannot be edited in the builder, ignores theme styles and global classes, and breaks responsive controls. Model one native element per semantic node instead (section > container > heading / text-basic / button / image / block) and load the bricks-build-page skill (novamira/skill-get) for the HTML-to-native mapping.',
                $id,
                $name,
                $markup,
            );
            continue;
        }

        // A component instance is judged by the body it expands to, not by its
        // own children: it is childless on purpose and the builder fills it at
        // render time. Checked BEFORE the structural-name gate, because an
        // instance can sit on any tag and one that expands to nothing is just
        // as invisible on a div as on a block. The message itself comes from
        // the nesting advisory, which every write path runs; this records the
        // id so a caller reading empty_structural_ids sees it too — but only
        // for the verdicts that really do leave an empty band. A malformed
        // body (two rows carrying the component id) can render, so listing it
        // here would tell a caller to delete an element that is on the page.
        $component_id = (string) ($element['cid'] ?? '');
        if ($component_id !== '') {
            if (bricks_component_verdict_is_empty_band(bricks_component_instance_verdict($component_id))) {
                $empty_structural_ids[] = $id;
            }
            continue;
        }

        if (!in_array($name, ['section', 'container', 'block'], strict: true) || ($child_counts[$id] ?? 0) !== 0) {
            continue;
        }

        $empty_structural_ids[] = $id;
    }

    $root_text = bricks_single_text_root($elements);
    if ($root_text !== null) {
        $warnings[] = sprintf(
            'This area has a single root element and it is a text element ("%s", %s). A Bricks page is a tree of native elements — start with a section, put a container inside it, then one element per heading, paragraph, button, image or card. Load the bricks-build-page skill (novamira/skill-get) before rebuilding.',
            $root_text['id'],
            $root_text['name'],
        );
    }

    return [
        'warnings' => $warnings,
        'raw_layout_html_ids' => $raw_layout_ids,
        'empty_structural_ids' => $empty_structural_ids,
    ];
}

/**
 * Describes the layout markup found in a text element's `text` setting, or
 * null when the element is not a text element / its text is clean.
 *
 * @param array<string, mixed> $element
 */
function bricks_text_element_layout_markup(array $element): ?string
{
    $name = (string) ($element['name'] ?? '');
    if (!in_array($name, NOVAMIRA_BRICKS_TEXT_ELEMENTS, strict: true)) {
        return null;
    }

    /** @var array<string, mixed> $settings */
    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
    /** @var mixed $text */
    $text = $settings['text'] ?? null;
    if (!is_string($text) || $text === '') {
        return null;
    }

    $found = bricks_matched_markup($text, NOVAMIRA_BRICKS_LAYOUT_MARKUP_PATTERNS);
    if ($name !== 'text') {
        $found = array_merge($found, bricks_matched_markup($text, NOVAMIRA_BRICKS_PLAIN_TEXT_MARKUP_PATTERNS));
    }

    if ($found === []) {
        return null;
    }

    return implode(', ', array_values(array_unique($found)));
}

/**
 * Human-readable list of the markup tokens that matched, e.g. `<section>`,
 * `<style>`, `style="…"`.
 *
 * @param list<string> $patterns
 * @return list<string>
 */
function bricks_matched_markup(string $text, array $patterns): array
{
    $found = [];
    foreach ($patterns as $pattern) {
        $m = [];
        if (preg_match($pattern, $text, $m) !== 1) {
            continue;
        }
        $tag = $m[1] ?? null;
        $whole = strtolower(trim($m[0]));
        $found[] = match (true) {
            is_string($tag) => '<' . strtolower($tag) . '>',
            str_starts_with($whole, '<') => '<' . trim($whole, characters: '<> ') . '>',
            default => 'style="…"',
        };
    }
    return $found;
}

/**
 * The area's only root element when that root is a text element, else null.
 *
 * @param list<array<string, mixed>> $elements
 * @return array{id: string, name: string}|null
 */
function bricks_single_text_root(array $elements): ?array
{
    $roots = [];
    foreach ($elements as $element) {
        /** @var string|int $parent */
        $parent = $element['parent'] ?? 0;
        if ($parent === 0 || $parent === '' || $parent === '0') {
            $roots[] = $element;
        }
    }

    if (count($roots) !== 1) {
        return null;
    }

    $name = (string) ($roots[0]['name'] ?? '');
    if (!in_array($name, NOVAMIRA_BRICKS_TEXT_ELEMENTS, strict: true)) {
        return null;
    }

    return ['id' => (string) ($roots[0]['id'] ?? ''), 'name' => $name];
}

/**
 * Merges a lint result into an ability response: warnings are appended,
 * the id lists are added only when non-empty so compact responses stay
 * compact.
 *
 * @param array<string, mixed> $result
 * @param list<string>         $warnings
 * @param array{warnings: list<string>, raw_layout_html_ids: list<string>, empty_structural_ids: list<string>} $lint
 * @return array{0: array<string, mixed>, 1: list<string>}
 */
function bricks_apply_lint_result(array $result, array $warnings, array $lint): array
{
    foreach ($lint['warnings'] as $warning) {
        $warnings[] = $warning;
    }
    if ($lint['raw_layout_html_ids'] !== []) {
        $result['raw_layout_html_ids'] = $lint['raw_layout_html_ids'];
    }
    if ($lint['empty_structural_ids'] !== []) {
        $result['empty_structural_ids'] = $lint['empty_structural_ids'];
    }

    return [$result, $warnings];
}

/**
 * Warns about `font-family` values Bricks cannot render: Bricks writes the
 * family literally inside quotes, so `var(--font)` becomes a family named
 * "var(--font)" and a comma-separated stack becomes one family named
 * "Inter, sans-serif" — both fall back to the system font, and Google Fonts
 * are loaded only for a bare family name. Walks nested settings (element
 * `_typography`, theme-style typography groups, global-class settings).
 *
 * @param array<array-key, mixed> $settings
 * @return list<string>
 */
function bricks_font_family_warnings(array $settings, string $subject): array
{
    $warnings = [];
    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        if (is_array($value)) {
            foreach (bricks_font_family_warnings($value, $subject) as $w) {
                $warnings[] = $w;
            }
            continue;
        }
        if ($key !== 'font-family' || !is_string($value)) {
            continue;
        }
        if (str_contains($value, 'var(')) {
            $warnings[] = sprintf(
                '%s: font-family "%s" uses var(). Bricks writes the family literally in quotes, so the browser looks for a family named "%s" and falls back to the system font, and no Google Font is loaded. Write the bare family name (e.g. "Inter") — fonts are the one token Bricks cannot take through a variable.',
                $subject,
                $value,
                $value,
            );
            continue;
        }
        if (str_contains($value, ',')) {
            $warnings[] = sprintf(
                '%s: font-family "%s" is a comma-separated stack. Bricks quotes the whole value as ONE family name, so nothing matches and the system font is used. Put the first family in font-family and the rest in the sibling "fallback" key.',
                $subject,
                $value,
            );
        }
    }

    return $warnings;
}

/**
 * Names referenced as `var(--name)` anywhere inside a value (strings nested
 * at any depth), without the leading dashes, de-duplicated in order.
 *
 * @return list<string>
 */
function bricks_collect_var_references(mixed $value): array
{
    $names = [];
    bricks_collect_var_references_into($value, $names);

    // Values, not keys: a token written as var(--900017) would come back from
    // array_keys() as an integer, and unresolved_variables is declared a list
    // of strings by 15 abilities — the 14 writers that report it plus the
    // readonly bricks-get-global-class — so one miscollected token would fail
    // every one of their output schemas at once.
    return array_values($names);
}

/**
 * @param array<string, string> $names
 */
function bricks_collect_var_references_into(mixed $value, array &$names): void
{
    if (is_array($value)) {
        /** @var mixed $item */
        foreach ($value as $item) {
            bricks_collect_var_references_into($item, $names);
        }
        return;
    }
    if (!is_string($value) || !str_contains($value, 'var(')) {
        return;
    }
    foreach (bricks_regex_group_matches('/var\(\s*--([A-Za-z0-9_-]+)/', $value) as $name) {
        // The name is the value as well as the key: the key alone loses its
        // type when the token is all digits.
        $names[$name] = $name;
    }
}

/**
 * All first-group matches of a pattern in a string (empty when none).
 *
 * @return list<string>
 */
function bricks_regex_group_matches(string $pattern, string $subject): array
{
    $m = [];
    $count = preg_match_all($pattern, $subject, $m);
    if ($count === false || $count === 0) {
        return [];
    }

    return array_values(array_map('strval', $m[1]));
}

/**
 * CSS custom-property names that resolve on the frontend of this site:
 * Bricks global variables, palette colours (`--bricks-color-<id>`), and
 * any `--name:` declared in the global custom CSS, a theme style's
 * stylesheet or a global class's custom CSS. Cached per request; a variable
 * or palette write bumps the generation through
 * {@see bricks_forget_known_css_variables()}.
 *
 * @return array<string, true>
 */
function bricks_known_css_variable_names(): array
{
    /** @var array<string, true>|null $cache */
    static $cache = null;
    static $cached_generation = -1;

    $generation = bricks_known_css_variables_generation();
    if ($cache !== null && $cached_generation === $generation) {
        return $cache;
    }
    $cached_generation = $generation;

    $names = [];
    foreach (bricks_global_variable_names() as $name) {
        $names[$name] = true;
    }
    foreach (bricks_palette_variable_names() as $name) {
        $names[$name] = true;
    }
    foreach (bricks_declared_custom_properties() as $name) {
        $names[$name] = true;
    }

    return $cache = $names;
}

/**
 * Generation counter of the known-variables cache; `$bump` advances it.
 */
function bricks_known_css_variables_generation(int $bump = 0): int
{
    static $generation = 0;
    $generation += $bump;

    return $generation;
}

/**
 * Marks the known-variables cache stale after a variable / palette write.
 */
function bricks_forget_known_css_variables(): void
{
    bricks_known_css_variables_generation(bump: 1);
}

/**
 * Names of the Bricks global variables (without the leading dashes).
 *
 * @return list<string>
 */
function bricks_global_variable_names(): array
{
    $names = [];
    // Same store the variable abilities read: a variable the builder shows
    // from the network's main site must not be linted as unresolved here.
    /** @var mixed $variable */
    foreach (bricks_routed_option_rows(BRICKS_DB_GLOBAL_VARIABLES) as $variable) {
        if (!is_array($variable) || !is_string($variable['name'] ?? null) || $variable['name'] === '') {
            continue;
        }
        $names[] = ltrim($variable['name'], characters: '-');
    }

    return $names;
}

/**
 * The `bricks-color-<id>` custom properties Bricks emits for palette colours.
 *
 * @return list<string>
 */
function bricks_palette_variable_names(): array
{
    $names = [];
    /** @var mixed $palettes */
    $palettes = get_option(BRICKS_DB_COLOR_PALETTE, []);
    /** @var mixed $palette */
    foreach (is_array($palettes) ? $palettes : [] as $palette) {
        if (!is_array($palette) || !is_array($palette['colors'] ?? null)) {
            continue;
        }
        /** @var mixed $color */
        foreach ($palette['colors'] as $color) {
            if (!is_array($color) || !is_string($color['id'] ?? null) || $color['id'] === '') {
                continue;
            }
            $names[] = 'bricks-color-' . $color['id'];
        }
    }

    return $names;
}

/**
 * `--name` declarations found in the site's raw CSS stores (global custom
 * CSS, theme-style stylesheets, global-class custom CSS).
 *
 * @return list<string>
 */
function bricks_declared_custom_properties(): array
{
    $css = implode("\n", array_merge(
        bricks_global_custom_css_sources(),
        bricks_theme_style_stylesheets(),
        bricks_global_class_custom_css(),
    ));
    if ($css === '') {
        return [];
    }

    return array_values(array_unique(bricks_regex_group_matches('/--([A-Za-z0-9_-]+)\s*:/', $css)));
}

/**
 * @return list<string>
 */
function bricks_global_custom_css_sources(): array
{
    /** @var mixed $global */
    $global = get_option(BRICKS_DB_GLOBAL_SETTINGS, []);
    if (is_array($global) && is_string($global['customCss'] ?? null)) {
        return [$global['customCss']];
    }

    return [];
}

/**
 * @return list<string>
 */
function bricks_theme_style_stylesheets(): array
{
    $sheets = [];
    /** @var mixed $styles */
    $styles = get_option(BRICKS_DB_THEME_STYLES, []);
    /** @var mixed $style */
    foreach (is_array($styles) ? $styles : [] as $style) {
        if (
            !is_array($style)
            || !is_array($style['settings'] ?? null)
            || !is_array($style['settings']['css'] ?? null)
        ) {
            continue;
        }
        /** @var mixed $sheet */
        $sheet = $style['settings']['css']['stylesheet'] ?? null;
        if (is_string($sheet)) {
            $sheets[] = $sheet;
        }
    }

    return $sheets;
}

/**
 * @return list<string>
 */
function bricks_global_class_custom_css(): array
{
    $css = [];
    /** @var mixed $classes */
    $classes = get_option(BRICKS_DB_GLOBAL_CLASSES, []);
    /** @var mixed $class */
    foreach (is_array($classes) ? $classes : [] as $class) {
        if (!is_array($class) || !is_array($class['settings'] ?? null)) {
            continue;
        }
        /** @var mixed $custom */
        $custom = $class['settings']['_cssCustom'] ?? null;
        if (is_string($custom)) {
            $css[] = $custom;
        }
    }

    return $css;
}

/**
 * The `var(--name)` references inside `$value` that resolve to nothing on
 * this site (no Bricks global variable, no palette colour, no declaration in
 * the site's custom CSS). The browser drops a declaration whose var() has
 * no value, so these are the tokens an agent still has to materialise.
 *
 * @return list<string>
 */
function bricks_unresolved_variables(mixed $value): array
{
    $known = bricks_known_css_variable_names();
    $unresolved = [];
    foreach (bricks_collect_var_references($value) as $name) {
        if (array_key_exists($name, $known)) {
            continue;
        }
        $unresolved[] = $name;
    }

    return $unresolved;
}

/**
 * The warning that accompanies a non-empty unresolved_variables list.
 *
 * @param list<string> $names
 */
function bricks_unresolved_variables_warning(array $names): string
{
    return sprintf(
        'Unresolved CSS variable(s): %s. None of them is a Bricks global variable, a palette colour or a custom property declared in the site\'s custom CSS, so every declaration using them is dropped by the browser. Create each one first with novamira/bricks-create-variable (name without the leading --) or novamira/bricks-add-color-palette-entry, then reference it.',
        implode(', ', array_map(static fn(string $n): string => '--' . $n, $names)),
    );
}
