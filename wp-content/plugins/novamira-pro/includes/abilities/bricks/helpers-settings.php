<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use const BRICKS_PATH;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Registry of Bricks' global settings (the `bricks_global_settings` option),
 * derived from Bricks itself instead of a hand-maintained allow-list:
 *
 *  1. The keys and control types are read from Bricks' own settings screen
 *     — the form Bricks uses to write the option — so a checkbox, select,
 *     number, text or list the scan can both NAME and TYPE is accepted with
 *     that type. The scan is not guaranteed to reach the whole screen: a
 *     setting whose key or type it cannot resolve, and one the screen names
 *     only while it renders, is refused rather than guessed at, and
 *     check-setup reports how much was read in capabilities.settings_scan
 *     ("complete", "partial" or "unavailable").
 *  2. A small static list of the keys the abilities have always supported
 *     underlies it (the screen wins on a shared key), so a missing or
 *     unreadable screen still leaves the known keys editable — and the
 *     static list is never a reason to reject a key the screen knows.
 *  3. Keys already stored in the option that neither source lists are
 *     accepted too, typed from their stored value, so a setting the site
 *     already uses can always be edited or removed.
 *
 * Credential-bearing keys (license, API keys, tokens, passwords, secrets) and
 * the code-execution toggle are never part of the writable registry, and the
 * capability lists Bricks stores on user roles rather than in the option are
 * excluded as well.
 *
 * A checkbox has two opposite ways of being off, and the screen declares
 * which one applies. A plain checkbox submits nothing when it is unticked,
 * so the key's ABSENCE is the off state and a false value must remove it —
 * storing `false` would read as "on" for keys like disableGoogleFonts.
 * A checkbox paired with a hidden input of the same name and a falsy value
 * submits that value when unticked, so the key is STORED as an explicit
 * false; removing it would fall back to whatever Bricks defaults the
 * setting to, which for some of these keys is on — turning a feature back
 * on for a user who asked for it off. Each scanned checkbox therefore
 * carries the off_state the screen declares, and a checkbox whose off_state
 * cannot be established is refused rather than guessed (see
 * {@see bricks_normalize_checkbox_setting()}).
 */

/**
 * Keys that never enter the writable registry, whatever the source says.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_GLOBAL_SETTINGS_EXCLUDED = [
    'licenseKey',
    'executeCodeEnabled',
    'executeCodeCapabilities',
    'codeSignaturesLocked',
    'codeExecutionMode',
    'htmlExecutionMode',
    'customCapabilities',
    'builderCapabilities',
    'uploadSvgCapabilities',
    'bypassMaintenanceCapabilities',
    'formSubmissionAccessCapabilities',
    'instagramAccessToken',
    'myTemplatesPassword',
    'remoteTemplatesPassword',
    // Rows of remote template sources, each carrying its own password.
    'remoteTemplates',
    'action',
    'save',
    'reset',
    'files',
];

/**
 * True for keys that hold credentials or secrets, or gate code execution,
 * whatever their spelling: the key is normalised (lower-cased, separators
 * `_` / `-` / spaces removed) before matching, so `apiKey`, `ApiKey`,
 * `api_key`, `access-key` and `Client Secret` are all caught, and new
 * integrations are covered without a list update.
 */
function bricks_setting_key_is_credential(string $key): bool
{
    $normalized = strtolower((string) preg_replace(pattern: '/[\s_-]+/', replacement: '', subject: $key));
    if ($normalized === '') {
        return false;
    }
    if (in_array($normalized, ['executecodeenabled', 'executecodecapabilities'], strict: true)) {
        return true;
    }
    foreach ([
        'licensekey',
        'apikey',
        'token',
        'password',
        'secret',
        'passphrase',
        'privatekey',
        'accesskey',
    ] as $needle) {
        if (str_contains($normalized, $needle)) {
            return true;
        }
    }

    return false;
}

/**
 * True for keys the write registry must never expose: credentials (see
 * {@see bricks_setting_key_is_credential()}), license-related keys, the
 * capability lists Bricks stores on roles and the settings-form controls.
 */
function bricks_setting_key_is_sensitive(string $key): bool
{
    if (in_array($key, NOVAMIRA_BRICKS_GLOBAL_SETTINGS_EXCLUDED, strict: true)) {
        return true;
    }

    return bricks_setting_key_is_credential($key) || str_starts_with(strtolower($key), 'license');
}

/**
 * The derived registry: key => {type, options?, off_state?, source}.
 * Types: checkbox | select | number | text | array | post_type_list.
 *
 * @return array<string, array<string, mixed>>
 */
function bricks_global_settings_registry(): array
{
    /** @var array<string, array<string, mixed>>|null $cache */
    static $cache = null;
    // The screen scan is file work and is cached per request; the stored
    // keys are re-read on every call so a value written earlier in the same
    // request is already editable.
    $cache ??= array_merge(bricks_registry_static_fallback(), bricks_registry_from_settings_screen());
    $registry = $cache;
    foreach (bricks_registry_from_stored_option() as $key => $entry) {
        if (array_key_exists($key, $registry)) {
            continue;
        }
        $registry[$key] = $entry;
    }

    return $registry;
}

/**
 * Source 1: Bricks' own settings screen. The template is the form that
 * writes the option, so its input names and types are the authoritative
 * key list on builds without a registry.
 *
 * @return array<string, array<string, mixed>>
 */
function bricks_registry_from_settings_screen(): array
{
    return bricks_settings_screen_scan()['registry'];
}

/**
 * Reads the settings screen once and reports both what it yielded and what
 * it could not resolve, so the coverage can be stated instead of implied.
 *
 * @return array{available: bool, registry: array<string, array<string, mixed>>, unresolved_keys: list<string>, names_settings_at_runtime: bool}
 */
function bricks_settings_screen_scan(): array
{
    /** @var array{available: bool, registry: array<string, array<string, mixed>>, unresolved_keys: list<string>, names_settings_at_runtime: bool}|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $empty = ['available' => false, 'registry' => [], 'unresolved_keys' => [], 'names_settings_at_runtime' => false];
    if (!defined('BRICKS_PATH')) {
        return $cache = $empty;
    }
    $file = (string) BRICKS_PATH . 'includes/admin/admin-screen-settings.php';
    if (!is_readable($file)) {
        return $cache = $empty;
    }
    $html = file_get_contents($file);
    if (!is_string($html) || $html === '') {
        return $cache = $empty;
    }

    $registry = [];
    foreach (bricks_settings_screen_inputs($html) as $key => $entry) {
        if (bricks_setting_key_is_sensitive($key)) {
            continue;
        }
        $registry[$key] = $entry + ['label' => $key, 'source' => 'bricks-settings-screen'];
    }

    $gaps = bricks_settings_screen_gaps($html, $registry);

    return $cache = [
        'available' => true,
        'registry' => $registry,
        'unresolved_keys' => $gaps['keys'],
        'names_settings_at_runtime' => $gaps['runtime'],
    ];
}

/**
 * What the scan leaves out: the settings this screen defines that did not
 * end up in the registry.
 *
 * Counted per SETTING, never per source tag — one setting is often two tags
 * (a control plus the hidden field carrying its off value), and one tag
 * inside a loop can stand for any number of settings.
 *
 *  - `keys`    — settings the screen names literally that the scan could not
 *                type, usually because the name is an array path. They can be
 *                listed by name, which is what makes the gap actionable.
 *  - `runtime` — true when at least one control's whole name is produced
 *                while the screen renders. How many settings such a control
 *                stands for is decided by the loop that emits it, so it
 *                cannot be counted from the file and no number is invented.
 *
 * Hidden fields are transport, not settings surface, and are skipped: a
 * legacy key a screen submits invisibly is not a control to send an agent at.
 *
 * @param array<string, array<string, mixed>> $registry the keys already scanned
 * @return array{keys: list<string>, runtime: bool}
 */
function bricks_settings_screen_gaps(string $html, array $registry): array
{
    $m = [];
    $count = preg_match_all('/<(input|select|textarea)\b([^>]*)\bname="([^"]*)"/', $html, $m, PREG_SET_ORDER);
    /** @var list<array<int, string>> $matches */
    $matches = $count === false ? [] : $m;

    $keys = [];
    $runtime = false;
    foreach ($matches as $match) {
        if ($match[1] === 'input' && preg_match('/\btype="hidden"/', $match[2]) === 1) {
            continue;
        }
        $name = $match[3];
        if (str_starts_with($name, '<?')) {
            $runtime = true;
            continue;
        }
        $key = explode('[', $name)[0];
        if ($key === '' || isset($registry[$key]) || bricks_setting_key_is_sensitive($key)) {
            continue;
        }
        $keys[$key] = true;
    }
    $names = array_keys($keys);
    sort($names);

    return ['keys' => $names, 'runtime' => $runtime];
}

/**
 * How complete the settings-screen scan is on this site:
 *
 *  - `complete`    — every setting the screen defines is in the allow-list;
 *  - `partial`     — the screen was read, but it defines settings the scan
 *                    could not type or could not name, so those are refused
 *                    unless the site already stores them;
 *  - `unavailable` — the screen could not be read at all; only the built-in
 *                    list and the keys already stored are writable.
 */
function bricks_settings_scan_status(): string
{
    $scan = bricks_settings_screen_scan();
    if ($scan['available'] !== true || $scan['registry'] === []) {
        return 'unavailable';
    }

    return $scan['unresolved_keys'] === [] && $scan['names_settings_at_runtime'] !== true ? 'complete' : 'partial';
}

/**
 * The sentence check-setup reports for a scan that is not complete: which
 * settings cannot be written here, named wherever they can be named, and the
 * calls that get the agent moving again.
 */
function bricks_settings_scan_gap_message(): string
{
    $scan = bricks_settings_screen_scan();
    $parts = [];
    if ($scan['unresolved_keys'] !== []) {
        $parts[] = sprintf('these settings its screen defines are not writable here: %s', implode(
            ', ',
            $scan['unresolved_keys'],
        ));
    }
    if ($scan['names_settings_at_runtime'] === true) {
        $parts[] = 'it also names settings only while its screen renders, so those keys cannot be listed at all';
    }

    return sprintf(
        'The writable global settings are a subset of what this Bricks offers: %s. Call novamira/bricks-list-settings with scope="global" for the keys that ARE writable; a setting outside that list has to be set once in the Bricks settings screen, after which novamira/bricks-set-settings can edit it and novamira/bricks-get-settings can read it back.',
        implode(', and ', $parts),
    );
}

/**
 * Extracts `name="…"` inputs, selects (with their option values) and
 * textareas from the settings-screen markup.
 *
 * @return array<string, array<string, mixed>>
 */
function bricks_settings_screen_inputs(string $html): array
{
    $inputs = [];
    $stores_false = bricks_settings_screen_store_false_keys($html);

    $m = [];
    $count = preg_match_all(
        '/<input[^>]*\btype="(checkbox|text|number|password)"[^>]*\bname="([A-Za-z0-9_]+)(\[\])?"/',
        $html,
        $m,
        PREG_SET_ORDER,
    );
    /** @var list<array<int, string>> $input_matches */
    $input_matches = $count === false ? [] : $m;
    foreach ($input_matches as $match) {
        $key = $match[2];
        $is_list = ($match[3] ?? '') === '[]';
        $inputs[$key] = match (true) {
            $is_list && $key === 'postTypes' => ['type' => 'post_type_list'],
            $is_list => ['type' => 'array'],
            $match[1] === 'checkbox' => [
                'type' => 'checkbox',
                'off_state' => in_array($key, $stores_false, strict: true) ? 'store_false' : 'remove',
            ],
            $match[1] === 'number' => ['type' => 'number'],
            default => ['type' => 'text'],
        };
    }

    $m = [];
    $count = preg_match_all('/<select[^>]*\bname="([A-Za-z0-9_]+)"[^>]*>(.*?)<\/select>/s', $html, $m, PREG_SET_ORDER);
    /** @var list<array<int, string>> $select_matches */
    $select_matches = $count === false ? [] : $m;
    foreach ($select_matches as $match) {
        $inputs[$match[1]] = ['type' => 'select'] + bricks_settings_screen_option_values($match[2]);
    }

    $m = [];
    $count = preg_match_all('/<textarea[^>]*\bname="([A-Za-z0-9_]+)"/', $html, $m);
    /** @var list<string> $textareas */
    $textareas = $count === false ? [] : $m[1];
    foreach ($textareas as $key) {
        $inputs[$key] = ['type' => 'text'];
    }

    return $inputs;
}

/**
 * The setting names the screen submits an explicit falsy value for: a
 * checkbox that carries a hidden input of the SAME name holding 0 or false.
 * That hidden field is what makes an unticked box post something at all, so
 * its presence is the screen stating that this setting is stored as an
 * explicit false rather than switched off by removing the key.
 *
 * @return list<string>
 */
function bricks_settings_screen_store_false_keys(string $html): array
{
    $m = [];
    $count = preg_match_all(
        '/<input[^>]*\btype="hidden"[^>]*\bname="([A-Za-z0-9_]+)"[^>]*\bvalue="(?:0|false)"/',
        $html,
        $m,
    );
    /** @var list<string> $keys */
    $keys = $count === false ? [] : $m[1];

    return array_values(array_unique($keys));
}

/**
 * The `value="…"` list of a select's markup.
 *
 * The screen is read as a template, not as a rendered page, so a select
 * whose choices are built at runtime (pages, templates, languages, …)
 * carries the PHP that would produce them where the value belongs. Those
 * entries are not values and are dropped instead of being offered as
 * choices or enforced as the only accepted ones; `options_partial` then
 * marks the select as loosely validated, so a real choice is accepted with
 * an explanation rather than rejected against a list it can never match.
 *
 * @return array{options: list<string>, options_partial: bool}
 */
function bricks_settings_screen_option_values(string $select_markup): array
{
    $o = [];
    $count = preg_match_all('/<option[^>]*\bvalue="([^"]*)"/', $select_markup, $o);
    if ($count === false || $count === 0) {
        return ['options' => [], 'options_partial' => true];
    }
    /** @var list<string> $values */
    $values = $o[1];
    $literal = array_values(array_unique(array_filter($values, static fn(string $value): bool => bricks_setting_value_is_literal(
        $value,
    ))));

    return ['options' => $literal, 'options_partial' => count($literal) !== count(array_unique($values))];
}

/**
 * Whether an option value read from the settings-screen template is a value
 * and not the code that would render one. A stored setting value is a plain
 * token (a slug, an id, a mode); anything carrying PHP tags, variables,
 * quotes, spaces or calls is markup that was never rendered.
 */
function bricks_setting_value_is_literal(string $value): bool
{
    return preg_match('#^[A-Za-z0-9_./:-]*$#', $value) === 1;
}

/**
 * Source 3: keys already present in the stored option, typed from their
 * value. Credential-bearing and excluded keys never enter.
 *
 * @return array<string, array<string, mixed>>
 */
function bricks_registry_from_stored_option(): array
{
    /** @var mixed $raw */
    $raw = get_option(BRICKS_DB_GLOBAL_SETTINGS, []);
    $registry = [];
    /** @var mixed $value */
    foreach (is_array($raw) ? $raw : [] as $key => $value) {
        $key = (string) $key;
        if (
            bricks_setting_key_is_sensitive($key)
            || in_array($key, NOVAMIRA_BRICKS_GLOBAL_SETTINGS_EXCLUDED, strict: true)
        ) {
            continue;
        }
        $registry[$key] = bricks_registry_entry_from_stored_value($key, $value)
        + ['label' => $key, 'source' => 'stored'];
    }

    return $registry;
}

/**
 * Registry entry inferred from a stored value: Bricks writes a ticked
 * checkbox as a truthy scalar, lists as arrays, counts as numbers.
 *
 * @return array<string, mixed>
 */
function bricks_registry_entry_from_stored_value(string $key, mixed $value): array
{
    if (is_array($value)) {
        return ['type' => $key === 'postTypes' ? 'post_type_list' : 'array'];
    }
    if ($value === false || $value === 0 || $value === '0') {
        // The site stores an explicit false for this key, so that is its off state.
        return ['type' => 'checkbox', 'off_state' => 'store_false'];
    }
    if ($value === true || $value === '1' || $value === 1 || $value === 'on') {
        // On is all this tells us. Whether off means an explicit false or no
        // key at all is the difference between switching the setting off and
        // switching it on, and no source here declares it.
        return ['type' => 'checkbox', 'off_state' => 'unknown'];
    }
    if (is_int($value) || is_float($value) || is_string($value) && is_numeric($value)) {
        return ['type' => 'number'];
    }

    return ['type' => 'text'];
}

/**
 * Source 2: the keys the abilities always supported (underlying list).
 *
 * @return array<string, array<string, mixed>>
 */
function bricks_registry_static_fallback(): array
{
    $checkbox = ['type' => 'checkbox', 'off_state' => 'remove', 'source' => 'static'];

    return [
        'postTypes' => ['type' => 'post_type_list', 'source' => 'static'],
        'cssLoading' => ['type' => 'select', 'options' => ['', 'file'], 'source' => 'static'],
        'disableLazyLoad' => $checkbox,
        'disableEmojis' => $checkbox,
        'disableEmbed' => $checkbox,
        'disableJqueryMigrate' => $checkbox,
        'disableSeo' => $checkbox,
        'disableOpenGraph' => $checkbox,
        'disableGoogleFonts' => $checkbox,
        'builderGlobalClassesSync' => $checkbox,
        'publicTemplates' => $checkbox,
    ];
}

/**
 * Human-readable value hint for a registry entry (used by list-settings).
 *
 * @param array<string, mixed> $entry
 */
function bricks_registry_value_format(string $key, array $entry): string
{
    $type = (string) ($entry['type'] ?? 'text');

    return match ($type) {
        'checkbox' => match ((string) ($entry['off_state'] ?? 'unknown')) {
            'remove'
                => 'boolean — true stores the key (on); false REMOVES the key, because this setting is off when the key is absent',
            'store_false'
                => 'boolean — true stores the key (on); false stores an explicit false, because Bricks\' own settings screen stores a falsy value for this key rather than dropping it. null removes the key instead, which falls back to whatever Bricks defaults this setting to — on for some of these keys — so use false to turn it off and null only when you mean to clear it',
            default => 'boolean — true stores the key (on). false is refused: this Bricks does not declare whether the setting is off when stored false or when absent, and the two are opposite. Pass null to remove the key when that is what you mean',
        },
        'select' => match (true) {
            $key === 'cssLoading' => 'string: "file" for external CSS files, "" (or "inline") for inline CSS',
            ($entry['options_partial'] ?? false) === true
                => 'string; this Bricks builds the choices at runtime (pages, templates, …), so options lists only the fixed ones and any other value is accepted with a warning instead of being checked (an empty string removes the key)',
            default => 'string matching one of options (an empty string removes the key)',
        },
        'number' => 'integer',
        'post_type_list'
            => 'flat array of registered post type slugs, e.g. ["page","post"]; prefer novamira/bricks-set-post-types for this key',
        'array' => 'list of strings',
        default => 'string',
    };
}
