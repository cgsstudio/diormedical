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
 * Ability: read one Companion extension's dashboard settings with the
 * extension's own default resolution.
 */

wp_register_ability('novamira/blocksy-get-extension-settings', [
    'label' => __('Get Blocksy Extension Settings', domain: 'novamira-pro'),
    'description' => __(
        'Reads one Companion extension\'s dashboard-level settings (the settings panel on the extension card in the Blocksy dashboard) with defaults resolved by the extension\'s OWN accessor — the same code path the dashboard UI reads — never from a copied snapshot. Covers the extensions that persist such settings to a dedicated wp_options row: product-reviews (CPT slugs), newsletter-subscribe (provider credentials — api_key is redacted), local-google-fonts, custom-fonts, adobe-typekit (readable only while enabled), white-label, post-types-extra and woocommerce-extra (feature toggles). Extensions not listed with has_settings=true by blocksy-list-extensions keep their options in the customizer instead — read those via blocksy-list-options / blocksy-get-option. This ability is read-only; extension settings have no write counterpart because each extension persists through its own side-effect chain (rewrite flushes, feature boots, font downloads) that a generic write path cannot protect.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => [
                'type' => 'string',
                'description' => 'Extension id with has_settings=true in blocksy-list-extensions.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'enabled' => ['type' => 'boolean'],
            'option_name' => [
                'type' => 'string',
                'description' => 'The wp_options row the extension persists these settings to.',
            ],
            'stored' => [
                'type' => 'boolean',
                'description' => 'False when the option row does not exist yet — every returned value is then the extension\'s default.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Defaults-merged settings as the extension\'s own accessor returns them. Oversized values (>16 KB JSON) are replaced with a marker string; key names longer than 64 bytes are truncated with an ellipsis marker (UTF-8-safe, marker inside the budget); redacted keys carry a marker instead of the credential.',
            ],
            'settings_keys_omitted' => [
                'type' => 'integer',
                'description' => 'How many top-level settings keys were omitted beyond the 40-key cap (0 for every known extension).',
            ],
            'redacted' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Settings keys whose non-empty credential values were replaced with a redaction marker.',
            ],
        ],
        'required' => [
            'id',
            'name',
            'enabled',
            'option_name',
            'stored',
            'settings',
            'settings_keys_omitted',
            'redacted',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_get_extension_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'stored=false means the site runs on the extension\'s defaults (the row is created on first dashboard save). adobe-typekit settings resolve only while the extension is enabled (its accessor lives on the booted extension object). A redacted api_key marker means a credential IS configured — never overwrite it blindly.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<array-key, mixed> $input
 * @return array{
 *     id: string,
 *     name: string,
 *     enabled: bool,
 *     option_name: string,
 *     stored: bool,
 *     settings: array<array-key, mixed>|\stdClass,
 *     settings_keys_omitted: int,
 *     redacted: list<string>,
 * }|WP_Error
 */
function blocksy_get_extension_settings(array $input): array|WP_Error
{
    $manager = bly_extensions_manager();
    if ($manager instanceof WP_Error) {
        return $manager;
    }

    $rows = bly_ext_rows($manager);
    $id = bly_ext_validate_id($input['id'] ?? null, $rows);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $registry = bly_ext_settings_registry()[$id] ?? null;
    if ($registry === null) {
        return bly_error('bly_not_found', sprintf(
            'Extension "%s" has no dashboard-level settings storage. Its options live in the customizer — discover them with blocksy-list-options and read/write them with blocksy-get-option / blocksy-edit-option.',
            $id,
        ));
    }

    $installed_gate = bly_ext_installed_gate($rows[$id]);
    if ($installed_gate !== null) {
        return $installed_gate;
    }
    $plan_gate = bly_ext_plan_gate($id);
    if ($plan_gate !== null) {
        return $plan_gate;
    }

    $settings = bly_ext_resolve_settings($manager, $id, $registry['source'], $registry['extract']);
    if ($settings instanceof WP_Error) {
        return $settings;
    }

    [$settings, $redacted] = bly_ext_redact_secrets($settings, $registry['secret_keys']);
    [$settings, $omitted] = bly_ext_cap_settings($settings);

    return [
        'id' => $id,
        'name' => $rows[$id]['name'],
        'enabled' => in_array($id, bly_ext_active_raw(), strict: true),
        'option_name' => $registry['option'],
        'stored' => bly_ext_option_row_exists($registry['option']),
        'settings' => bly_object_or_map($settings),
        'settings_keys_omitted' => $omitted,
        'redacted' => $redacted,
    ];
}

/**
 * Resolve the defaults-merged settings through the extension's own
 * accessor. `preboot` runs the manager's do_extensions_preboot() (the
 * dashboard's exact path — idempotent, plan-gated internally) and calls
 * the pre-boot object's ext_data(); `extension` calls get_settings() on
 * the booted extension object, which only exists while the extension is
 * enabled. An unresolvable accessor is an explicit failure — defaults
 * are never fabricated from a copied snapshot.
 *
 * @param 'preboot'|'extension' $source
 * @param 'settings'|'all'|'newsletter' $extract
 * @return array<array-key, mixed>|WP_Error
 */
function bly_ext_resolve_settings(
    \Blocksy\ExtensionsManager $manager,
    string $id,
    string $source,
    string $extract,
): array|WP_Error {
    if ($source === 'extension') {
        /** @var mixed $object */
        $object = $manager->get($id);
        if (!is_object($object)) {
            return bly_error('bly_extension_disabled', sprintf(
                'Extension "%s" resolves its settings on the booted extension object, which only exists while the extension is enabled. Enable it first (blocksy-enable-extension), then re-read.',
                $id,
            ));
        }
        if (!method_exists($object, 'get_settings')) {
            return bly_schema_error(sprintf('the %s extension object exposes no get_settings() accessor', $id));
        }
        try {
            /** @var mixed $payload */
            $payload = $object->get_settings();
        } catch (\Throwable $e) {
            error_log(sprintf('novamira-pro blocksy: %s get_settings() threw: %s', $id, $e->getMessage()));
            return bly_schema_error(sprintf('the %s settings accessor threw — details in the server error log', $id));
        }
        return is_array($payload)
            ? $payload
            : bly_schema_error(sprintf('the %s settings accessor returned a non-array value', $id));
    }

    try {
        $manager->do_extensions_preboot();
        /** @var mixed $preboot */
        $preboot = $manager->get($id, ['type' => 'preboot']);
        if (!is_object($preboot) || !method_exists($preboot, 'ext_data')) {
            return bly_schema_error(sprintf('the %s pre-boot data accessor is unavailable', $id));
        }
        /** @var mixed $payload */
        $payload = $preboot->ext_data();
    } catch (\Throwable $e) {
        error_log(sprintf('novamira-pro blocksy: %s pre-boot settings read threw: %s', $id, $e->getMessage()));
        return bly_schema_error(sprintf('the %s settings accessor threw — details in the server error log', $id));
    }
    if (!is_array($payload)) {
        return bly_schema_error(sprintf('the %s settings accessor returned a non-array value', $id));
    }

    if ($extract === 'settings') {
        return isset($payload['settings']) && is_array($payload['settings'])
            ? $payload['settings']
            : bly_schema_error(sprintf('the %s pre-boot payload carries no settings map', $id));
    }
    if ($extract === 'newsletter') {
        // ext_data() merges the provider credentials with the provider
        // CATALOG; only the credentials are settings.
        unset($payload['providers']);
        return $payload;
    }
    return $payload;
}

/**
 * Replace non-empty credential values with a redaction marker. Empty /
 * unset credentials pass through so "not configured" stays visible.
 *
 * @param array<array-key, mixed> $settings
 * @param list<string> $secret_keys
 * @return array{0: array<array-key, mixed>, 1: list<string>}
 */
function bly_ext_redact_secrets(array $settings, array $secret_keys): array
{
    $redacted = [];
    foreach ($secret_keys as $key) {
        if (!isset($settings[$key]) || $settings[$key] === '' || $settings[$key] === []) {
            continue;
        }
        $settings[$key] = '[redacted — a value is configured]';
        $redacted[] = $key;
    }
    return [$settings, $redacted];
}

/**
 * Output discipline for the settings map: at most
 * BLY_EXT_MAX_SETTINGS_KEYS top-level keys (omissions counted, never
 * silent), every KEY NAME byte-capped (a corrupted stored row can carry
 * arbitrary keys — 40 unbounded names would be an unbounded response),
 * and every value individually capped at the shared 16 KB JSON budget
 * (oversized values become marker strings, see bly_cap_value()).
 *
 * @param array<array-key, mixed> $settings
 * @return array{0: array<array-key, mixed>, 1: int}
 */
function bly_ext_cap_settings(array $settings): array
{
    $capped = [];
    $omitted = 0;
    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        if (count($capped) >= BLY_EXT_MAX_SETTINGS_KEYS) {
            $omitted++;
            continue;
        }
        /** @var mixed */
        $capped[bly_ext_cap_settings_key((string) $key, $capped)] = bly_cap_value($value);
    }
    return [$capped, $omitted];
}

/**
 * Byte-bound one emitted settings key name: at most
 * BLY_EXT_MAX_SETTINGS_KEY_BYTES bytes INCLUDING the truncation marker,
 * character-safe for UTF-8 (bly_truncate_utf8_bytes never splits a
 * multibyte sequence, so the response stays valid UTF-8 and
 * JSON-encodable).
 *
 * EVERY emitted key — the in-budget fast path included — goes through
 * the same uniqueness check against the keys already emitted: distinct
 * originals can collide after truncation, and a stored key can be
 * exactly equal to a previously-emitted TRUNCATED key — either way the
 * collision gets a numeric disambiguation tag, still inside the budget,
 * so no stored key silently overwrites another's value.
 *
 * @param array<array-key, mixed> $already Keys emitted so far.
 */
function bly_ext_cap_settings_key(string $key, array $already): string
{
    $ellipsis = '…';
    $emit = $key;
    if (strlen($emit) > BLY_EXT_MAX_SETTINGS_KEY_BYTES) {
        $emit = bly_truncate_utf8_bytes($key, BLY_EXT_MAX_SETTINGS_KEY_BYTES - strlen($ellipsis)) . $ellipsis;
    }
    $n = 1;
    while (array_key_exists($emit, $already)) {
        $n++;
        $tag = $ellipsis . '#' . $n;
        $emit = bly_truncate_utf8_bytes($key, BLY_EXT_MAX_SETTINGS_KEY_BYTES - strlen($tag)) . $tag;
    }
    return $emit;
}

/**
 * Whether the settings option row physically exists (raw $wpdb read —
 * get_option() cannot distinguish "absent" from "stored false").
 */
function bly_ext_option_row_exists(string $option_name): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    // @mago-expect analysis:possibly-invalid-argument
    $sql = $wpdb->prepare("SELECT option_id FROM {$wpdb->options} WHERE option_name = %s", $option_name);
    /** @var mixed $found */
    $found = is_string($sql) ? $wpdb->get_var($sql) : null;
    return $found !== null;
}
