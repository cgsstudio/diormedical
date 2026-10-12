<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-refresh-from-cloud', [
    'label' => __('Refresh Weglot Options from Cloud', domain: 'novamira-pro'),
    'description' => __(
        'Forces a fresh fetch of the Weglot project settings from the cloud: clears the local `weglot_cache_cdn` and `weglot_slugs_cache` transients, calls `/projects/settings`, and rehydrates the local v3 options blob with the cloud response (merged on top of locally-persisted defaults the cloud does not return). Use this when the admin has edited slugs / languages / exclusions on dashboard.weglot.com and you want the local cache to reflect cloud truth before the next page-load auto-refresh.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'refreshed' => ['type' => 'boolean'],
            'destination_languages' => ['type' => 'array', 'items' => ['type' => 'string']],
            'exclude_rules' => ['type' => 'integer'],
            'translated_url_languages' => ['type' => 'integer'],
            'preserved_local_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Local-only keys the cloud returned as empty and that were kept at their local value instead of being blanked (custom_urls, menu_switcher, flag_css, active_wc_reload).',
            ],
        ],
        'required' => [
            'refreshed',
            'destination_languages',
            'exclude_rules',
            'translated_url_languages',
            'preserved_local_keys',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_refresh_from_cloud',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Round-trip the cloud and overwrite the local options cache — cloud wins, EXCEPT for local-only keys the cloud returned empty, which are preserved and reported in preserved_local_keys. DISCARDS pending local edits made by the write abilities: run weglot-push-options-to-cloud first if any are outstanding. Use AFTER the admin has manually edited on dashboard.weglot.com, or to recover from a desynced state. Error codes: weglot_no_api_key (private key missing), weglot_cloud_unreachable / weglot_cloud_http_<code> / weglot_cloud_transport (network or upstream failure), weglot_lock_busy (a concurrent push-options-to-cloud is in flight — retry shortly).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{refreshed: bool, destination_languages: list<string>, exclude_rules: int, translated_url_languages: int, preserved_local_keys: list<string>}|WP_Error
 */
function weglot_refresh_from_cloud(): array|WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }
    $private = wg_api_key_private();
    if ($private === '') {
        return new WP_Error(
            'weglot_no_api_key',
            'Weglot refresh requires the private (long) API key. Set it via weglot-set-api-key first.',
            ['status' => 400],
        );
    }

    return wg_with_options_lock(static function () use ($private): array|WP_Error {
        // Fetch FIRST, then save, then bust transients. Inverting the order
        // (delete-then-fetch) would expose a window where frontend visitors
        // trigger their own cloud refetch in parallel with ours.
        $result = wg_remote_get(path: '/projects/settings', query: ['api_key' => $private]);
        if ($result instanceof WP_Error) {
            return $result;
        }

        // SECURITY: drop any credential-shaped keys the cloud echoes back so a
        // hostile or misconfigured cloud cannot rotate the local credential
        // option silently. The api_key_private kept in the v3 blob is
        // re-applied AFTER the upstream filter chain below so a third-party
        // hook on `weglot_get_options` cannot rotate it either.
        $local = wg_options_raw();
        $cloud_safe = wg_refresh_filter_cloud_payload($result);

        // Reject degenerate cloud responses (empty body, missing the expected
        // top-level keys) — overwriting the local cache with `{}` would wipe
        // valid config AND trigger a cache stampede on the frontend.
        if (!wg_refresh_cloud_payload_is_usable($cloud_safe)) {
            // Separate the two reasons a body can be unusable, because the
            // remedy is opposite. Weglot answers 200 with an error body for a
            // rejected or unprovisioned key; reporting that as 502 told the
            // agent "transient, retry" for a condition that will never clear on
            // its own, and it would retry forever. An explicit error marker is
            // deterministic: rotate the credential, do not retry.
            $upstream = wg_refresh_upstream_error($result);
            if ($upstream !== null) {
                return new \WP_Error(
                    'weglot_cloud_key_invalid',
                    sprintf(
                        'Weglot Cloud rejected the request (%s). Check the API key with weglot-check-setup.',
                        $upstream,
                    ),
                    ['status' => 401, 'cause_code' => $upstream],
                );
            }
            return new \WP_Error(
                'weglot_cloud_invalid_shape',
                'Weglot Cloud responded with an empty or degenerate settings payload — refusing to overwrite the local cache.',
                ['status' => 502],
            );
        }

        $merged = array_replace($local, $cloud_safe);

        // Protect the keys that live ONLY in the local store. Weglot itself
        // does this: before letting a cloud-driven rewrite touch the options it
        // snapshots custom_urls / menu_switcher / flag_css / active_wc_reload
        // into `weglot_settings_backup` (Actions\Admin\Options_Weglot, the
        // reset handler) — you only back up what a sync can destroy. A cloud
        // payload that carries one of these keys as an empty value would
        // otherwise blank a configuration the cloud never held in the first
        // place. A cloud value with actual content still wins.
        $preserved = [];
        foreach (wg_refresh_local_only_keys() as $key) {
            if (!array_key_exists($key, $local) || !array_key_exists($key, $cloud_safe)) {
                continue;
            }
            if (!wg_value_is_empty($cloud_safe[$key]) || wg_value_is_empty($local[$key])) {
                continue;
            }
            $merged[$key] = $local[$key];
            $preserved[] = $key;
        }

        // Mirror upstream parity: apply Weglot's `weglot_get_options` filter
        // so third-party plugins that patch the merged blob keep working.
        /** @var array<string, mixed> $merged */
        $merged = apply_filters('weglot_get_options', $merged);

        // Drop the legacy `exclude_urls` alias for consistency with
        // add/delete-exclude-url, so a future read does not surface a
        // desynced shadow copy alongside the canonical excluded_paths.
        unset($merged['exclude_urls']);

        // Re-apply the LOCAL private credential LAST so neither the cloud
        // response nor a third-party `weglot_get_options` filter can rotate it
        // via this side-channel — this is the final word before persist.
        $merged = wg_refresh_pin_private_credential($merged, $local);

        wg_options_save($merged);

        // The local blob is now the cloud's own state, so nothing is awaiting
        // publication. Without this the marker survived a refresh and the next
        // read reported edits the user never made — pointing the agent at the
        // one irreversible operation on this surface.
        wg_pending_keys_clear();

        delete_transient('weglot_cache_cdn');
        delete_transient('weglot_slugs_cache');
        wg_user_info_cache_bust();

        $custom_urls_count = count(wg_custom_urls_from_options($merged));
        /** @var mixed $excluded_raw */
        $excluded_raw = $merged['excluded_paths'] ?? $merged['exclude_urls'] ?? [];
        $exclude_count = is_array($excluded_raw) ? count($excluded_raw) : 0;

        return [
            'refreshed' => true,
            'destination_languages' => wg_destination_codes_from_options($merged),
            'exclude_rules' => $exclude_count,
            'translated_url_languages' => $custom_urls_count,
            'preserved_local_keys' => $preserved,
        ];
    });
}

/**
 * Pin the `api_key_private` field of the blob about to be persisted to the
 * local credential.
 *
 * A key the host supplies through a PHP constant is kept OUT of the database:
 * keeping it there is what the constant exists to avoid, and Weglot overrides
 * this field with its own credential on every read. Whatever the local blob
 * already held stays as it was.
 *
 * @param  array<string, mixed> $merged
 * @param  array<string, mixed> $local
 * @return array<string, mixed>
 */
function wg_refresh_pin_private_credential(array $merged, array $local): array
{
    if (!wg_api_key_private_from_constant()) {
        $merged['api_key_private'] = wg_api_key_private();
        return $merged;
    }
    unset($merged['api_key_private']);
    if (array_key_exists('api_key_private', $local)) {
        $merged['api_key_private'] = $local['api_key_private'];
    }
    return $merged;
}

/**
 * Settings keys that exist only in the local store — the cloud never holds
 * them, so an empty cloud value for one of them means "absent", not "cleared".
 * Mirrors the set Weglot snapshots into `weglot_settings_backup` before a
 * cloud-driven rewrite.
 *
 * @return list<string>
 */
function wg_refresh_local_only_keys(): array
{
    return ['custom_urls', 'menu_switcher', 'flag_css', 'active_wc_reload'];
}

/**
 * Whether a persisted option value carries no information: null, empty string
 * or empty array. `false` and `0` are meaningful values for the toggles in
 * this set and are NOT treated as empty.
 */
function wg_value_is_empty(mixed $value): bool
{
    return $value === null || $value === '' || $value === [];
}

/**
 * Strip credential-shaped keys from a cloud /projects/settings response
 * before merging it into the local options blob. Defensive: prevents a
 * hostile or misconfigured cloud from silently rotating credentials.
 *
 * @param  array<array-key, mixed> $payload
 * @return array<array-key, mixed>
 */
function wg_refresh_filter_cloud_payload(array $payload): array
{
    foreach (array_keys($payload) as $key) {
        if (!is_string($key) || !wg_is_credential_key($key)) {
            continue;
        }
        unset($payload[$key]);
    }
    return $payload;
}

/**
 * The error a 200-with-error body carries, or null when the body says nothing
 * about a failure.
 *
 * Weglot answers HTTP 200 for a rejected or unprovisioned key and puts the
 * reason in the body, so transport-level status alone cannot tell a broken
 * credential from a transient hiccup. Credentials are redacted: upstream error
 * strings echo the key back.
 *
 * @param array<array-key, mixed> $payload
 */
function wg_refresh_upstream_error(array $payload): ?string
{
    foreach (['error', 'message', 'detail', 'error_name'] as $field) {
        /** @var mixed $value */
        $value = $payload[$field] ?? null;
        if (is_string($value) && trim($value) !== '') {
            return wg_redact_credentials_from_body($value);
        }
    }
    /** @var mixed $code */
    $code = $payload['error_code'] ?? $payload['code'] ?? null;
    if (is_string($code) && trim($code) !== '') {
        return $code;
    }
    if (is_int($code)) {
        return (string) $code;
    }
    return null;
}

/**
 * Whether the (already credential-filtered) cloud payload carries enough
 * shape to be worth merging into the local cache. A truly empty `{}` or one
 * missing every expected key is a degenerate response we refuse to persist.
 *
 * @param array<array-key, mixed> $payload
 */
function wg_refresh_cloud_payload_is_usable(array $payload): bool
{
    if ($payload === []) {
        return false;
    }
    foreach (['language_from', 'languages', 'custom_urls', 'excluded_paths', 'custom_settings'] as $expected) {
        if (array_key_exists($expected, $payload)) {
            return true;
        }
    }
    return false;
}
