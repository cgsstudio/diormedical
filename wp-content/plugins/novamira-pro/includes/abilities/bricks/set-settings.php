<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

/**
 * Ability: Set Bricks settings (global or per-page).
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Page-setting control types that hold ONE value. A list written to any of
 * them reaches a string function during rendering.
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_PAGE_SETTING_SCALAR_TYPES = ['text', 'textarea', 'number', 'select', 'checkbox'];

wp_register_ability('novamira/bricks-set-settings', [
    'label' => __('Set Bricks Settings', domain: 'novamira-pro'),
    'description' => __(
        'Updates Bricks settings. Pass a post_id to set page-level settings, or omit it to set global settings. Settings are merged with existing values by default. Global keys are validated against the settings Bricks itself defines (discover them with novamira/bricks-list-settings scope="global"), which also says how each toggle is turned off: most are off when the key is absent, so false REMOVES the key (listed in removed_keys) — for keys like disableGoogleFonts Bricks reads presence, not value — while a toggle the screen stores an explicit false for keeps that false, because removing it would switch the setting back on. A toggle whose off state this Bricks declares nowhere is refused rather than guessed; pass null to remove a key outright. Credentials and the code-execution toggle cannot be written here. In external CSS file mode, page-level settings regenerate the post\'s CSS file (css_file).',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'Post ID for page-level settings. Omit for global settings.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Settings key-value pairs to set.',
            ],
            'merge' => [
                'type' => 'boolean',
                'description' => 'Whether to merge with existing settings (true) or replace entirely (false).',
                'default' => true,
            ],
        ],
        'required' => ['settings'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'scope' => ['type' => 'string'],
            'saved' => ['type' => 'boolean'],
            'settings' => ['type' => 'object', 'description' => 'The resulting settings after save.'],
            'post_id' => [
                'type' => 'integer',
                'description' => 'The post whose page settings these are; absent for global settings.',
            ],
            'removed_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Global scope: keys removed from the option because their value was false / empty / null. A toggle the settings screen stores an explicit false for is NOT listed here — it keeps the stored false, because removing it would switch the setting back on.',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => 'External CSS file mode: "regenerated" when the affected CSS file (the post\'s file for page settings, the global custom CSS file for customCss) was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise.',
            ],
            'unresolved_variables' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Page scope: var(--name) references in the written settings (customCss included) that resolve to nothing on this site.',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Validation warnings about the settings (data is still saved unless there is an error).',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_set_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Settings are shallow-merged with existing values by default (set merge=false to replace entirely). Use novamira/bricks-get-settings to see current values, and novamira/bricks-list-settings to discover every valid key (page, template, and global) with its expected value shape. For building a new page end-to-end or configuring common page-level layout intents (full-bleed / blank canvas, disable header/footer, body classes, custom scripts), load the "bricks-build-page" skill via novamira/skill-get.

Page/template settings: unknown keys and wrong value shapes on known keys produce a warning in the response but the data is still saved (Bricks itself silently ignores garbage keys). YOU MUST inspect the warnings[] field after every call — saved:true does NOT mean the settings will have any effect if warnings were returned.

Global settings: unknown keys and wrong value shapes are rejected before save because malformed global values can crash Bricks during WordPress boot. Prefer the narrow novamira/bricks-set-post-types ability when enabling Bricks for post types.

Do NOT set "templateType" on a regular page or post. That key is reserved for Bricks template posts (bricks_template post type) and identifies the template kind ("header", "footer", "section", "content", "popup", etc.). Setting it on a normal page makes Bricks treat the post as a template and the "Edit with Bricks" link fails with "Invalid post type". If you need to undo this, delete the "templateType" key from _bricks_page_settings.

Global settings (omit post_id): the keys read from Bricks\' own settings screen are accepted with their type — not necessarily every key the screen defines, since a key the scan cannot name or type is refused rather than guessed at; novamira/bricks-check-setup reports the coverage as capabilities.settings_scan ("complete", "partial" or "unavailable") and novamira/bricks-list-settings scope="global" lists exactly what IS writable here — postTypes (flat array of registered post type slugs), cssLoading ("file" or "" for inline; "inline" is accepted as an alias of ""), toggles (boolean), numbers and texts. How a toggle is turned OFF is read from that screen, per key, and novamira/bricks-list-settings with scope="global" states it in each key\'s value_format: for most toggles the key\'s absence is the off state, so false removes the key (disableGoogleFonts and the other disable* keys work that way — Bricks reads whether the key is there, not what it holds); for a toggle the screen stores an explicit falsy value for, false is stored, because removing the key would restore a default that may be on. Passing null always removes the key. A toggle whose off state this Bricks declares nowhere is refused with ambiguous_toggle instead of guessed. Call novamira/bricks-list-settings with scope="global" for the full list, and novamira/bricks-check-setup (capabilities.settings_scan) for how much of the screen could be read.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Warnings for a page-setting select whose value is not one of its choices.
 *
 * A select that declares several values stores a LIST, so each entry is
 * checked on its own rather than casting the whole array to a string.
 *
 * @param array<string, mixed> $control
 * @return list<string>
 */
function bricks_select_setting_warnings(string $key, array $control, mixed $value): array
{
    if (!is_array($control['options'] ?? null)) {
        return [];
    }
    /** @var array<string, mixed> $options */
    $options = $control['options'];
    $chosen = is_array($value) ? array_values($value) : [$value];

    $warnings = [];
    /** @var mixed $one */
    foreach ($chosen as $one) {
        if (!is_scalar($one) || array_key_exists((string) $one, $options)) {
            continue;
        }
        $warnings[] = sprintf(
            'Setting "%s" must be one of: %s. Got %s. Read the choices this Bricks offers with novamira/bricks-list-settings scope="page".',
            $key,
            implode(', ', array_keys($options)),
            (string) $one,
        );
    }

    return $warnings;
}

/**
 * The page-settings controls Bricks itself declares for this post — the same
 * introspection novamira/bricks-list-settings publishes under scope "page"
 * (or "template" for a Bricks template).
 *
 * @return array<string, mixed>
 */
function bricks_page_settings_controls(int $post_id): array
{
    $type = get_post_type($post_id) === 'bricks_template' ? 'template' : 'page';
    return bricks_get_settings_schema($type)['controls'];
}

/**
 * Refuses a page setting whose SHAPE the builder cannot survive.
 *
 * A control Bricks declares as a single value is handed to string functions
 * when the page renders: bodyClasses, for example, is exploded on spaces on
 * every request. Passing a list there stores cleanly, verifies cleanly and
 * then fatals the front end on the next page view, which is the worst
 * possible outcome — so it is refused before anything is written.
 *
 * @param array<array-key, mixed> $settings
 * @param array<string, mixed>    $controls
 */
function bricks_check_page_setting_shapes(array $settings, array $controls): ?WP_Error
{
    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        $key = (string) $key;
        [$base] = bricks_split_setting_key($key);
        if (!is_array($controls[$base] ?? null) || !is_array($value)) {
            continue;
        }
        /** @var array<string, mixed> $control */
        $control = $controls[$base];
        $type = (string) ($control['type'] ?? '');
        if (!in_array($type, NOVAMIRA_BRICKS_PAGE_SETTING_SCALAR_TYPES, strict: true)) {
            continue;
        }
        // A control that declares it holds several values legitimately stores a
        // list — the robots directives and the popup triggers are both selects
        // of that kind — so only single-value controls are refused here.
        if (($control['multiple'] ?? false) !== false || ($control['multiple_values'] ?? false) !== false) {
            continue;
        }

        return bricks_error('invalid_setting_shape', sprintf(
            'Page setting "%s" must be a single %s value, not a list or object: Bricks passes it straight to a string function when the page renders, so a list is stored without complaint and then fatals the front end on the next request. %s Read the shape this Bricks expects with novamira/bricks-list-settings scope="page" (controls.%s), then write it again.',
            $key,
            $type === 'number' ? 'number' : 'text',
            $base === 'bodyClasses'
                ? 'Join the classes into one space-separated string, for example "hero dark full-bleed".'
                : 'Send the value as a single string.',
            $base,
        ));
    }

    return null;
}

/**
 * Validates and normalises a global-settings payload against the registry
 * derived from Bricks itself ({@see bricks_global_settings_registry()}).
 * Returns the keys to store, the keys to REMOVE (a checkbox set to false, a
 * select / text set to the empty string — Bricks stores neither) and the
 * warnings; a WP_Error for an unknown key or a wrong type, because malformed
 * global values can break Bricks during WordPress boot.
 *
 * The payload arrives from decoded JSON, so its keys are array-key, not
 * string: a setting named only of digits decodes to an INTEGER key.
 *
 * @param array<array-key, mixed> $settings
 * @return array{set: array<string, mixed>, unset: list<string>, warnings: list<string>}|WP_Error
 */
function bricks_normalize_global_settings(array $settings): array|WP_Error
{
    $registry = bricks_global_settings_registry();
    $set = [];
    $unset = [];
    $warnings = [];

    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        // An all-digit setting key arrives as an int; removed_keys is declared
        // a list of strings and the messages below interpolate this key.
        $key = (string) $key;
        if (!array_key_exists($key, $registry)) {
            return bricks_error('unknown_global_setting', sprintf(
                'Unrecognised global setting key "%s"%s. Call novamira/bricks-list-settings with scope="global" for the keys this Bricks install accepts.',
                $key,
                bricks_setting_key_is_sensitive($key)
                    ? ' (credentials and the code-execution toggle are never written through this ability)'
                    : '',
            ));
        }

        $normalized = bricks_normalize_global_setting_value($key, $registry[$key], $value);
        if ($normalized instanceof WP_Error) {
            return $normalized;
        }
        if (is_string($normalized['warning'] ?? null)) {
            $warnings[] = $normalized['warning'];
        }
        if ($normalized['remove']) {
            $unset[] = $key;
            continue;
        }
        $set[$key] = $normalized['value'];
    }

    if (array_key_exists('postTypes', $set) && $set['postTypes'] === []) {
        $warnings[] = 'Setting postTypes to an empty array disables Bricks editing for all post types.';
    }

    return ['set' => $set, 'unset' => $unset, 'warnings' => $warnings];
}

/**
 * Normalises one global setting value against its registry entry.
 *
 * @param array<string, mixed> $entry
 * @return array{value: mixed, remove: bool, warning?: string}|WP_Error
 */
function bricks_normalize_global_setting_value(string $key, array $entry, mixed $value): array|WP_Error
{
    $type = (string) ($entry['type'] ?? 'text');

    return match ($type) {
        'post_type_list' => bricks_normalize_post_type_list_setting($value),
        'checkbox' => bricks_normalize_checkbox_setting($key, $entry, $value),
        'select' => bricks_normalize_select_setting($key, $entry, $value),
        'number' => bricks_normalize_number_setting($key, $value),
        'array' => bricks_normalize_list_setting($key, $value),
        default => bricks_normalize_text_setting($key, $value),
    };
}

/**
 * @return array{value: mixed, remove: bool, warning?: string}|WP_Error
 */
function bricks_normalize_post_type_list_setting(mixed $value): array|WP_Error
{
    $validation = bricks_validate_post_types_setting($value);
    if ($validation instanceof WP_Error) {
        return $validation;
    }

    return ['value' => $value, 'remove' => false];
}

/**
 * A toggle is off in one of two opposite ways, and which one applies is the
 * difference between switching a setting off and switching it on:
 *
 *  - `remove`      — the key's absence is the off state (Bricks tests the
 *                    key's presence), so false must REMOVE the key;
 *  - `store_false` — the screen submits an explicit falsy value for the key,
 *                    so false must be STORED rather than removed. Removing
 *                    the key falls back to Bricks' default for it, which for
 *                    some of these keys is on.
 *
 * When the installed Bricks declares neither — a key this site stores that
 * its settings screen does not offer — false is refused instead of guessed,
 * because guessing wrong turns the setting on for someone who asked for it
 * off. `null` always means "remove the key", so an agent that does mean
 * that can still say it.
 *
 * @param array<string, mixed> $entry
 * @return array{value: mixed, remove: bool, warning?: string}|WP_Error
 */
function bricks_normalize_checkbox_setting(string $key, array $entry, mixed $value): array|WP_Error
{
    if ($value === null) {
        return ['value' => null, 'remove' => true];
    }
    if (!is_bool($value)) {
        return bricks_error('invalid_type', sprintf(
            'Setting "%s" must be a boolean (or null to remove the key), got %s.',
            $key,
            gettype($value),
        ));
    }
    if ($value) {
        return ['value' => true, 'remove' => false];
    }

    return match ((string) ($entry['off_state'] ?? 'unknown')) {
        'remove' => ['value' => null, 'remove' => true],
        'store_false' => ['value' => false, 'remove' => false],
        default => bricks_error('ambiguous_toggle', sprintf(
            'Setting "%s" cannot be turned off safely: this Bricks does not declare whether the setting is off when it is stored false or when the key is absent, and the two are opposite — removing a key whose default is on switches the setting ON. The settings screen this site exposes does not offer this key, so nothing declares it. Read novamira/bricks-check-setup (capabilities.settings_scan) to see how much of the screen this site exposes could be read, and turn the setting off in the Bricks settings screen; or, if removing the key IS what you mean, call this ability again with %s set to null.',
            $key,
            $key,
        )),
    };
}

/**
 * @param array<string, mixed> $entry
 * @return array{value: mixed, remove: bool, warning?: string}|WP_Error
 */
function bricks_normalize_select_setting(string $key, array $entry, mixed $value): array|WP_Error
{
    if (!is_string($value)) {
        return bricks_error('invalid_type', sprintf('Setting "%s" must be a string, got %s.', $key, gettype($value)));
    }
    if ($key === 'cssLoading' && $value === 'inline') {
        $value = '';
    }
    if ($value === '') {
        return ['value' => null, 'remove' => true];
    }
    $options = is_array($entry['options'] ?? null) ? array_values(array_map('strval', $entry['options'])) : [];
    if (in_array($value, $options, strict: true)) {
        return ['value' => $value, 'remove' => false];
    }
    // A select whose choices this Bricks builds at runtime has no complete
    // fixed list to check against, so a value outside the fixed part is
    // written with an explanation rather than refused.
    if (($entry['options_partial'] ?? false) === true) {
        return [
            'value' => $value,
            'remove' => false,
            'warning' => sprintf(
                'Setting "%s" was written unchecked: this Bricks builds its choices at runtime, so only the fixed ones (%s) can be validated. Call novamira/bricks-get-settings (scope "global") to read "%s" back, or open the Bricks settings screen, and confirm it is one of the choices offered there.',
                $key,
                $options === []
                    ? 'none'
                    : implode(', ', array_map(static fn(string $o): string => $o === ''
                        ? '"" (default)'
                        : $o, $options)),
                $value,
            ),
        ];
    }

    if ($options === []) {
        return ['value' => $value, 'remove' => false];
    }

    return bricks_error('invalid_value', sprintf(
        'Setting "%s" must be one of: %s.',
        $key,
        implode(', ', array_map(static fn(string $o): string => $o === '' ? '"" (default)' : $o, $options)),
    ));
}

/**
 * @return array{value: mixed, remove: bool, warning?: string}|WP_Error
 */
function bricks_normalize_number_setting(string $key, mixed $value): array|WP_Error
{
    if (!is_int($value) && !(is_string($value) && is_numeric($value))) {
        return bricks_error('invalid_type', sprintf('Setting "%s" must be a number, got %s.', $key, gettype($value)));
    }

    return ['value' => (int) $value, 'remove' => false];
}

/**
 * @return array{value: mixed, remove: bool, warning?: string}|WP_Error
 */
function bricks_normalize_list_setting(string $key, mixed $value): array|WP_Error
{
    if (!is_array($value) || !bricks_is_list_array($value)) {
        return bricks_error('invalid_type', sprintf(
            'Setting "%s" must be a flat list, got %s.',
            $key,
            gettype($value),
        ));
    }

    return ['value' => $value === [] ? null : $value, 'remove' => $value === []];
}

/**
 * @return array{value: mixed, remove: bool, warning?: string}|WP_Error
 */
function bricks_normalize_text_setting(string $key, mixed $value): array|WP_Error
{
    if (!is_string($value) && !is_int($value) && !is_float($value)) {
        return bricks_error('invalid_type', sprintf('Setting "%s" must be a string, got %s.', $key, gettype($value)));
    }
    $string = (string) $value;

    return ['value' => $string, 'remove' => $string === ''];
}

/**
 * Validates Bricks' global `postTypes` value.
 *
 * Bricks expects a plain list of post type slugs and later passes each entry
 * into string functions during `init`. Associative/nested arrays can therefore
 * fatal-error WordPress before MCP has a chance to recover.
 */
function bricks_validate_post_types_setting(mixed $value): bool|WP_Error
{
    if (!is_array($value)) {
        return bricks_error('invalid_type', sprintf(
            'Setting "postTypes" must be a flat array of registered post type slugs, got %s.',
            gettype($value),
        ));
    }

    if (!bricks_is_list_array($value)) {
        return bricks_error(
            'invalid_post_types_shape',
            message: 'Setting "postTypes" must be a flat list like ["page","post"], not an associative object such as {"item":["page","post"]}.',
        );
    }

    /** @var mixed $post_type */
    foreach ($value as $post_type) {
        if (!is_string($post_type) || trim($post_type) === '') {
            return bricks_error(
                'invalid_post_type_slug',
                message: 'Setting "postTypes" must contain only non-empty post type slug strings.',
            );
        }

        if (!post_type_exists($post_type)) {
            return bricks_error('unknown_post_type', sprintf(
                'Post type "%s" is not registered in this WordPress environment.',
                $post_type,
            ));
        }
    }

    return true;
}

/**
 * PHP 8.0-compatible `array_is_list()`.
 *
 * @param array<mixed> $value
 */
function bricks_is_list_array(array $value): bool
{
    $expected_key = 0;
    foreach (array_keys($value) as $key) {
        if ($key !== $expected_key) {
            return false;
        }
        $expected_key++;
    }

    return true;
}

/**
 * Validates page-level or template-level settings against the live Bricks
 * controls schema. Returns a list of warnings (unknown keys, wrong shapes on
 * known keys). Never returns a fatal error: Bricks silently accepts any key
 * at the storage layer, so surfacing warnings without blocking the save
 * matches the plugin's "don't drop, do tell" posture.
 *
 * @param array<string, mixed> $settings
 * @return list<string>
 */
function bricks_validate_post_settings(array $settings, string $post_type): array
{
    $valid = bricks_get_valid_post_settings($post_type);
    $warnings = [];

    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        if (!array_key_exists($key, $valid)) {
            $warnings[] = sprintf(
                'Unrecognised %s setting key "%s" — call novamira/bricks-list-settings to see valid keys. The value was stored but Bricks will likely ignore it.',
                $post_type === 'bricks_template' ? 'template' : 'page',
                $key,
            );
            continue;
        }

        $control = $valid[$key];
        $type = is_string($control['type'] ?? null) ? $control['type'] : '';

        if ($type === 'checkbox' && $value !== null && !is_bool($value)) {
            $warnings[] = sprintf('Setting "%s" is a checkbox and should be a boolean, got %s.', $key, gettype($value));
            continue;
        }

        if ($type === 'select' && $value !== null) {
            $warnings = array_merge($warnings, bricks_select_setting_warnings($key, $control, $value));
        }
    }

    return $warnings;
}

function bricks_set_settings(array $input): array|WP_Error
{
    /** @var int|null $post_id */
    $post_id = $input['post_id'] ?? null;
    /** @var array<string, mixed> $new_settings */
    $new_settings = $input['settings'];
    $merge = ($input['merge'] ?? true) !== false;

    if ($post_id !== null) {
        return bricks_write_page_settings_store($post_id, $new_settings, $merge ? 'merge' : 'replace');
    }

    return bricks_write_global_settings_store($new_settings, $merge ? 'merge' : 'replace');
}

/**
 * Page / template scope: validate against the live controls (warnings only),
 * store, and regenerate the post's CSS file (page settings feed it).
 *
 * @param array<string, mixed> $new_settings
 * @param 'merge'|'replace'    $mode
 * @return array<string, mixed>|WP_Error
 */
function bricks_write_page_settings_store(int $post_id, array $new_settings, string $mode): array|WP_Error
{
    $merge = $mode === 'merge';
    /** @var WP_Post|null $post */
    $post = get_post($post_id);

    if (!$post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
    }

    $controls = bricks_page_settings_controls($post_id);
    $shape = bricks_check_page_setting_shapes($new_settings, $controls);
    if ($shape instanceof WP_Error) {
        return $shape;
    }

    $warnings = bricks_validate_post_settings($new_settings, $post->post_type);
    $requested = array_keys($new_settings);
    $new_settings = bricks_apply_presence_toggle_offs(
        $new_settings,
        $controls,
        sprintf('the page settings of post %d', $post_id),
        $warnings,
    );
    // Keys dropped here must also leave the STORED settings: merging a payload
    // that no longer carries the key would otherwise keep the previous true,
    // and the toggle would stay on.
    $turned_off = array_values(array_diff($requested, array_keys($new_settings)));

    /** @var array<string, mixed> $existing */
    $existing = [];
    if ($merge) {
        /** @var array<string, mixed>|false $raw */
        $raw = get_post_meta($post_id, BRICKS_DB_PAGE_SETTINGS, single: true);
        $existing = is_array($raw) ? $raw : [];
    }
    $final = array_merge($existing, $new_settings);
    foreach ($turned_off as $key) {
        unset($final[$key]);
    }
    update_post_meta($post_id, BRICKS_DB_PAGE_SETTINGS, $final);
    clean_post_cache($post_id);

    $result = [
        'scope' => 'page',
        'post_id' => $post_id,
        'saved' => true,
        'settings' => (object) $final,
        'css_file' => bricks_regenerate_post_css($post_id, bricks_get_area_elements(
            $post_id,
            bricks_template_expected_area(bricks_css_content_type($post_id)),
        )),
    ];

    $unresolved = bricks_unresolved_variables($new_settings);
    if ($unresolved !== []) {
        $result['unresolved_variables'] = $unresolved;
        $warnings[] = bricks_unresolved_variables_warning($unresolved);
    }

    if (count($warnings) > 0) {
        $result['warnings'] = $warnings;
    }

    return $result;
}

/**
 * Global scope: normalise against the derived registry (false / empty
 * removes the key), store, verify Bricks still boots on the new option
 * (rolling back otherwise), report removed keys and the global custom CSS
 * file status.
 *
 * @param array<string, mixed> $new_settings
 * @param 'merge'|'replace'    $mode
 * @return array<string, mixed>|WP_Error
 */
function bricks_write_global_settings_store(array $new_settings, string $mode): array|WP_Error
{
    $merge = $mode === 'merge';
    $normalized = bricks_normalize_global_settings($new_settings);
    if ($normalized instanceof WP_Error) {
        return $normalized;
    }

    /** @var array<string, mixed>|false $raw_existing */
    $raw_existing = get_option(BRICKS_DB_GLOBAL_SETTINGS, []);
    $existing = $merge && is_array($raw_existing) ? $raw_existing : [];
    $final = array_merge($existing, $normalized['set']);
    foreach ($normalized['unset'] as $key) {
        unset($final[$key]);
    }
    $custom_css_changed = ($existing['customCss'] ?? null) !== ($final['customCss'] ?? null);
    if ($custom_css_changed) {
        bricks_ensure_css_dir();
    }
    $started = time();
    $health_check = bricks_write_global_settings_guarded($final, $raw_existing);
    if ($health_check instanceof WP_Error) {
        return $health_check;
    }

    $result = [
        'scope' => 'global',
        'saved' => true,
        'settings' => (object) $final,
    ];
    if ($normalized['unset'] !== []) {
        $result['removed_keys'] = $normalized['unset'];
    }
    if ($custom_css_changed) {
        $result['css_file'] = bricks_global_css_file_status('global-custom-css.min.css', $started);
    }
    if (count($normalized['warnings']) > 0) {
        $result['warnings'] = $normalized['warnings'];
    }

    return $result;
}

/**
 * Writes the global settings option and verifies Bricks still boots on it:
 * Bricks reads its settings from a static snapshot taken at boot, so the
 * snapshot is pointed at the just-written option BEFORE the check, and the
 * option AND the snapshot are rolled back to `$previous` when Bricks rejects
 * the new shape. Returns true, or the rejection.
 *
 * @param array<string, mixed> $final
 */
function bricks_write_global_settings_guarded(array $final, mixed $previous): bool|WP_Error
{
    $snapshot = bricks_global_settings_snapshot();
    update_option(BRICKS_DB_GLOBAL_SETTINGS, $final);
    bricks_set_global_settings_snapshot($final);

    $health_check = bricks_check_global_settings_boot_safe();
    if ($health_check instanceof WP_Error) {
        update_option(BRICKS_DB_GLOBAL_SETTINGS, $previous);
        bricks_set_global_settings_snapshot($snapshot);
        return $health_check;
    }

    return true;
}

/**
 * Bricks' in-memory global settings (the snapshot its helpers read).
 *
 * @return array<array-key, mixed>
 */
function bricks_global_settings_snapshot(): array
{
    if (!class_exists(\Bricks\Database::class)) {
        return [];
    }
    /** @var mixed $settings */
    $settings = \Bricks\Database::$global_settings;

    return is_array($settings) ? $settings : [];
}

/**
 * Points Bricks' in-memory global settings at the given array so its helpers
 * evaluate the state that was just written (or restored).
 *
 * @param array<array-key, mixed> $settings
 */
function bricks_set_global_settings_snapshot(array $settings): void
{
    if (!class_exists(\Bricks\Database::class)) {
        return;
    }
    \Bricks\Database::$global_settings = $settings;
}

/**
 * Smoke-checks Bricks' own global-settings read path (against the current
 * in-memory snapshot — see bricks_write_global_settings_guarded()).
 */
function bricks_check_global_settings_boot_safe(): bool|WP_Error
{
    $callback = '\Bricks\Helpers::get_supported_post_types';
    if (!is_callable($callback)) {
        return true;
    }

    try {
        call_user_func($callback);
    } catch (\Throwable $e) {
        return bricks_error('bricks_global_settings_rejected', sprintf(
            'Bricks rejected the saved global settings while reading supported post types: %s',
            $e->getMessage(),
        ));
    }

    return true;
}
