<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Cloud API base. Weglot supports staging / US / production via the
 * Helper_API::get_environment() filter; abilities call the same getter so the
 * environment override the user already configured carries over. The constant
 * here is the safe default when the upstream helper is not reachable.
 */
const API_BASE_PROD = 'https://api.weglot.com';

/**
 * Persisted v3 settings cache option name. We do NOT use the WEGLOT_SLUG
 * constant because it is also the slug used by Weglot's admin URLs / textdomain;
 * the storage key is a sprintf of `WEGLOT_SLUG . '-v3'` and is stable across
 * the 5.x line.
 */
function wg_option_name_v3(): string
{
    return 'weglot-translate-v3';
}

/**
 * Public (short) API credential option name. Stored next to the v3 blob.
 */
function wg_option_name_public_credential(): string
{
    return 'weglot-translate-api_key';
}

/**
 * Private (long) API credential option name. Required by cloud writes.
 */
function wg_option_name_private_credential(): string
{
    return 'weglot-translate-api_key_private';
}

/**
 * Detect Weglot presence + version. Cached per request.
 *
 * @return array{active: bool, version: string|null}
 */
function wg_detect_setup(): array
{
    /** @var array{active: bool, version: string|null}|null $cache */
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $active = defined('WEGLOT_VERSION');

    return $cache = [
        'active' => $active,
        'version' => $active ? (string) constant('WEGLOT_VERSION') : null,
    ];
}

/**
 * Standard "Weglot is not active" error.
 */
function wg_not_active(): WP_Error
{
    return new WP_Error('weglot_not_active', 'Weglot Translate is not active', ['status' => 400]);
}

/**
 * Standard "missing API key" error. Most cloud-backed reads need at least the
 * short (public) credential — without it the upstream helpers return defaults
 * that look successful but contain no project data.
 */
function wg_no_api_key(): WP_Error
{
    return new WP_Error(
        'weglot_no_api_key',
        'Weglot API key is not configured. Set the public API key via the Weglot settings page or weglot-set-api-key first.',
        ['status' => 400],
    );
}

/**
 * Generic invalid-input error.
 */
function wg_invalid_input(string $message): WP_Error
{
    return new WP_Error('weglot_invalid_input', $message, ['status' => 400]);
}

/**
 * Public (short) API credential — the one Weglot itself uses. See
 * wg_credential().
 */
function wg_api_key(): string
{
    return wg_credential('public')['value'];
}

/**
 * Private (long) API credential — the one Weglot itself uses. Some cloud
 * surfaces (user-info, glossary, slugs) require this longer credential. See
 * wg_credential().
 */
function wg_api_key_private(): string
{
    return wg_credential('private')['value'];
}

/**
 * Whether the public credential is supplied by a PHP constant Weglot honours,
 * which makes the stored option inert while it is defined.
 */
function wg_api_key_from_constant(): bool
{
    return wg_credential('public')['from_constant'];
}

/**
 * Whether the private credential is supplied by a PHP constant Weglot honours.
 */
function wg_api_key_private_from_constant(): bool
{
    return wg_credential('private')['from_constant'];
}

/**
 * Resolve one credential the way Weglot resolves it.
 *
 * Weglot can take its credentials from PHP constants (`WEGLOT_API_KEY_PRIVATE`,
 * and `WEGLOT_API_KEY` for the public one) instead of wp_options, for hosts that
 * keep secrets in their server configuration. While a constant is defined
 * Weglot reads it and ignores the stored option, so reading the option alone
 * reports "no key" on a site that translates fine, or — worse — sends cloud
 * reads and pushes with a stale stored key to a project the site does not serve.
 *
 * Constant support is detected on Weglot's option service rather than assumed:
 * a Weglot build without it ignores the constants, and there the option is the
 * credential in force. Anything unexpected falls back to the option.
 *
 * @param  'public'|'private' $which
 * @return array{value: string, from_constant: bool}
 */
function wg_credential(string $which): array
{
    $from_constant = wg_credential_from_constant($which);
    if ($from_constant !== null) {
        return ['value' => $from_constant, 'from_constant' => true];
    }

    /** @var mixed $raw */
    $raw = get_option(
        $which === 'private' ? wg_option_name_private_credential() : wg_option_name_public_credential(),
        default_value: '',
    );
    return ['value' => is_string($raw) ? $raw : '', 'from_constant' => false];
}

/**
 * The credential Weglot reads from a PHP constant, or null when it reads the
 * stored option instead.
 *
 * The detectors exist only on Weglot builds that honour the constants; without
 * them the stored option is the credential in force. A resolver failure falls
 * back to the option rather than taking a credential read down.
 *
 * @param 'public'|'private' $which
 */
function wg_credential_from_constant(string $which): ?string
{
    $service = wg_option_service();
    if ($service === null) {
        return null;
    }
    try {
        $defined = $which === 'private'
            ? method_exists($service, 'has_api_key_private_constant') && $service->has_api_key_private_constant()
            : method_exists($service, 'has_api_key_constant') && $service->has_api_key_constant();
        if (!$defined) {
            return null;
        }
        /** @var mixed $value */
        $value = $which === 'private' ? $service->get_api_key_private() : $service->get_api_key();
    } catch (\Throwable) {
        return null;
    }
    return is_string($value) ? trim($value) : '';
}

/**
 * Weglot's option service, or null when its service container is unreachable.
 */
function wg_option_service(): ?\WeglotWP\Services\Option_Service_Weglot
{
    if (!function_exists('weglot_get_service')) {
        return null;
    }
    try {
        /** @var mixed $service */
        $service = \weglot_get_service(\WeglotWP\Services\Option_Service_Weglot::class);
    } catch (\Throwable) {
        return null;
    }
    return $service instanceof \WeglotWP\Services\Option_Service_Weglot ? $service : null;
}

/**
 * Error returned when a credential write would be ignored because Weglot reads
 * that credential from a PHP constant.
 */
function wg_credential_from_constant_error(string $field, string $constant): WP_Error
{
    return new WP_Error(
        'weglot_credential_from_constant',
        sprintf(
            'The Weglot %1$s is supplied by the %2$s PHP constant in the server configuration, and Weglot ignores the stored value while that constant is defined. Nothing was saved. Change the constant where it is defined instead.',
            $field,
            $constant,
        ),
        ['status' => 409],
    );
}

/**
 * Drop WordPress's cached copy of the v3 options row so the next read hits the
 * database.
 *
 * The option is stored with `autoload: false`, so `get_option` caches it under
 * its own name in the `options` group. Within one request every subsequent read
 * is served from there — including a re-read taken specifically to detect that
 * somebody else wrote in the meantime.
 */
function wg_options_cache_bust(): void
{
    wp_cache_delete(wg_option_name_v3(), group: 'options');
}

/**
 * The persisted v3 options blob. Returns an empty array when not yet provisioned
 * — Weglot persists it lazily after the first save, so a fresh install legitimately
 * has no row yet. Strict: arrays only, anything else becomes [].
 *
 * @return array<string, mixed>
 */
function wg_options_raw(): array
{
    /** @var mixed $raw */
    $raw = get_option(wg_option_name_v3(), default_value: []);
    if (!is_array($raw)) {
        return [];
    }
    /** @var array<string, mixed> $normalized */
    $normalized = [];
    /** @var mixed $value */
    foreach ($raw as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $normalized[$key] = $value;
    }
    return $normalized;
}

/**
 * Runtime-schema → API-schema key aliases.
 *
 * Weglot keeps THREE shapes of the same configuration and they do not share
 * key names:
 *
 *   - the API / CDN payload (`weglot_cache_cdn`, `GET /projects/settings`),
 *     which is what our abilities are written against;
 *   - the runtime blob returned by `weglot_get_options()`, remapped through
 *     `WeglotWP\Models\Schema_Option_V3`;
 *   - the local `weglot-translate-v3` option, a 7-key store that holds none of
 *     the configuration (see wg_options_resolution()).
 *
 * The mapping below is the inverse of the entries in Schema_Option_V3 (see
 * `'exclude_urls' => 'excluded_paths'` and neighbours, ~line 266). Adding the
 * API name back lets every existing reader keep using the API key names.
 *
 * DELIBERATELY ABSENT: `exclude_blocks`. The runtime mapper flattens each
 * entry down to `$item['value']` and discards the rest, so it is NOT
 * interchangeable with the API's `excluded_blocks`. No ability reads it today
 * — do not add it here without re-reading the mapper first.
 *
 * @return array<string, string> runtime key => API key
 */
function wg_runtime_to_api_aliases(): array
{
    return [
        'exclude_urls' => 'excluded_paths',
        'auto_redirect' => 'auto_switch',
        'autoswitch_fallback' => 'auto_switch_fallback',
        'original_language' => 'language_from',
        'destination_language' => 'languages',
    ];
}

/**
 * The settings keys the push is allowed to publish.
 *
 * `custom_urls` is deliberately absent even though write abilities own it.
 * Translated slugs are NOT part of the project settings: Weglot keeps them on a
 * separate resource, `/translations/slugs`, versioned independently
 * (Option_Service_Weglot::get_slugs_from_api_with_api_key). Verified against a
 * live project: the key is absent from `GET /projects/settings`, and POSTing it
 * changes nothing — the server accepts the request and ignores the field.
 *
 * Listing it here would have made the push report `custom_urls` among
 * `overlaid_keys` for a value the cloud never receives, and get-settings would
 * have shown work "pending publication" that no publish could ever clear.
 *
 * Slugs are not lost by this: the cloud does not carry them, so Weglot's own
 * merge takes them from the local store and they take effect on this site
 * immediately — see the note on add-translated-url.
 *
 * @return list<string>
 */
function wg_agent_owned_keys(): array
{
    return ['language_from', 'languages', 'excluded_paths', 'custom_settings'];
}

/**
 * Option holding the list of owned keys with an edit that has been written
 * locally and NOT yet published to the cloud.
 *
 * WHY A MARKER AND NOT A COMPARISON. Pendency used to be inferred as "the key
 * exists locally and differs from the resolved value". That is wrong twice
 * over. `refresh-from-cloud` persists the whole cloud payload into the same
 * local blob, so presence proves nothing about intent; and the resolved value
 * is the runtime PROJECTION of the cloud one, so a key can differ purely
 * because of the schema mapping — `languages` always does, since the projection
 * renames `enabled` to `public` and drops five other fields. The comparison
 * therefore reported pending edits nobody made.
 *
 * That is not cosmetic: `pending_publish` and `overlaid_keys` are the two
 * signals an agent uses to decide whether to run the push, the only
 * irreversible operation on this surface.
 */
function wg_option_name_pending(): string
{
    return 'nvp_weglot_pending_keys';
}

/**
 * The owned keys currently carrying an unpublished local edit.
 *
 * @return list<string>
 */
function wg_pending_keys(): array
{
    /** @var mixed $raw */
    $raw = get_option(wg_option_name_pending(), default_value: []);
    if (!is_array($raw)) {
        return [];
    }
    $owned = wg_agent_owned_keys();
    /** @var list<string> $out */
    $out = [];
    /** @var mixed $key */
    foreach ($raw as $key) {
        if (!is_string($key)) {
            continue;
        }
        if (!in_array($key, $owned, strict: true) || in_array($key, $out, strict: true)) {
            continue;
        }
        $out[] = $key;
    }
    return $out;
}

/**
 * Mark owned keys as carrying an unpublished edit. Callers should already hold
 * the options lock so the read-modify-write of the marker is serialized with
 * the write it describes.
 *
 * @param list<string> $keys
 */
function wg_pending_keys_add(array $keys): void
{
    $owned = wg_agent_owned_keys();
    $pending = wg_pending_keys();
    foreach ($keys as $key) {
        if (!in_array($key, $owned, strict: true) || in_array($key, $pending, strict: true)) {
            continue;
        }
        $pending[] = $key;
    }
    update_option(option: wg_option_name_pending(), value: $pending, autoload: false);
}

/**
 * Drop the pending marker entirely — after a successful publish, or after a
 * refresh that replaced the local blob with the cloud's own state.
 */
function wg_pending_keys_clear(): void
{
    delete_option(wg_option_name_pending());
}

/**
 * The subset of the owned keys the READ path may overlay: those the cloud
 * holds and therefore wins on in Weglot's own merge, hiding a pending local
 * edit unless we put it back on top.
 *
 * `custom_urls` is deliberately NOT here even though it is publishable. The
 * cloud settings payload does not carry it, so Weglot's merge already sources
 * it from the local blob — verified at runtime: a value written to
 * `weglot-translate-v3` shows up in the resolved options unchanged. Worse,
 * `get_options()` merges the cached cloud SLUGS on top of it on front-end and
 * REST requests, so overlaying the local value here would DELETE those slugs
 * from the read. Leave it to Weglot.
 *
 * @return list<string>
 */
function wg_cloud_owned_keys(): array
{
    return ['language_from', 'languages', 'excluded_paths', 'custom_settings'];
}

/**
 * Resolve the Weglot configuration the way Weglot itself resolves it, then
 * normalize it back to the API key names the abilities speak.
 *
 * WHY THIS EXISTS. `wg_options_raw()` reads `weglot-translate-v3`, which is not
 * a mirror of the project settings. `Option_Service_Weglot::$options_bdd_default`
 * declares exactly six keys (has_first_settings, show_box_first_settings,
 * menu_switcher, custom_urls, flag_css, active_wc_reload) plus `allowed`,
 * written at runtime by the admin pages controller. Languages, exclusion rules
 * and the translation engine live on the cloud and are merged in LAST by
 * `Option_Service_Weglot::get_options()` — `array_merge(default, local, cloud)`
 * — so the local blob can never hold them. Reading it alone reports an
 * unconfigured site on an install that is actively translating.
 *
 * COST. No HTTP request is attributable to this call: Weglot resolves and
 * memoizes the options during its own bootstrap, before any ability executes.
 * Measured at ~0.1ms in the REST context both with and without the
 * `weglot_cache_cdn` transient present.
 *
 * PENDING EDITS. Weglot's own merge puts the cloud LAST, so a local edit to a
 * cloud-held key loses. That is correct for what the site serves, but wrong for
 * what an agent needs to see: after add-exclude-url the rule must be readable
 * back, or "write then list" returns nothing. So the cloud-owned keys are
 * overlaid on top of the resolved view and reported in `pending_keys`. Keys the
 * cloud does not hold need no overlay — Weglot already sources them from the
 * local blob. See wg_cloud_owned_keys().
 *
 * DEGRADED MODE. When the Weglot resolver is unavailable or throws, we fall
 * back to the local blob and say so, rather than silently reproducing the very
 * blindness this helper exists to fix. Callers surface it as `config_source`.
 *
 * @return array{options: array<string, mixed>, source: 'weglot'|'local_fallback', degraded: bool, pending_keys: list<string>}
 */
function wg_options_resolution(): array
{
    $local = wg_options_raw();
    // The pending marker is readable whether or not the resolver is: reporting
    // an empty list here would tell an agent there is nothing to publish while
    // the push would publish plenty — and degraded mode is exactly when that
    // matters most.
    $fallback = [
        'options' => $local,
        'source' => 'local_fallback',
        'degraded' => true,
        'pending_keys' => wg_pending_keys(),
    ];

    if (!function_exists('weglot_get_options')) {
        return $fallback;
    }

    try {
        /** @var mixed $resolved */
        $resolved = \weglot_get_options();
    } catch (\Throwable) {
        // A resolver failure must never take an otherwise-working read down.
        return $fallback;
    }

    if (!is_array($resolved) || $resolved === []) {
        return $fallback;
    }

    /** @var array<string, mixed> $normalized */
    $normalized = [];
    /** @var mixed $value */
    foreach ($resolved as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $normalized[$key] = $value;
    }

    foreach (wg_runtime_to_api_aliases() as $runtime_key => $api_key) {
        if (array_key_exists($api_key, $normalized) || !array_key_exists($runtime_key, $normalized)) {
            continue;
        }
        $normalized[$api_key] = $normalized[$runtime_key];
    }

    // Overlay the edits that are genuinely unpublished, per the explicit
    // marker — never per a value comparison. See wg_option_name_pending().
    $pending = [];
    $cloud_owned = wg_cloud_owned_keys();
    foreach (wg_pending_keys() as $key) {
        if (!array_key_exists($key, $local)) {
            // Marked but absent: the blob was replaced under us. Report it as
            // pending anyway — the marker is the record of intent — but do not
            // fabricate a value.
            $pending[] = $key;
            continue;
        }
        $pending[] = $key;
        if (in_array($key, $cloud_owned, strict: true)) {
            $normalized[$key] = $local[$key];
        }
    }

    return ['options' => $normalized, 'source' => 'weglot', 'degraded' => false, 'pending_keys' => $pending];
}

/**
 * The resolved options array alone. Use wg_options_resolution() when the caller
 * also needs to report which store the answer came from.
 *
 * @return array<string, mixed>
 */
function wg_options_resolved(): array
{
    return wg_options_resolution()['options'];
}

/**
 * The current Weglot Cloud API base URL. Mirrors Helper_API::get_api_url()
 * by reading the same filter / constant chain Weglot itself uses, so any
 * environment override the host already configured (sandbox, US region,
 * staging) carries over to ability-issued requests.
 */
function wg_api_url(): string
{
    if (
        class_exists(\WeglotWP\Helpers\Helper_API::class)
        && method_exists(\WeglotWP\Helpers\Helper_API::class, 'get_api_url')
    ) {
        $url = \WeglotWP\Helpers\Helper_API::get_api_url();
        return $url !== '' ? rtrim(string: $url, characters: '/') : API_BASE_PROD;
    }
    return API_BASE_PROD;
}

/**
 * Map an upstream non-2xx HTTP code to the status an agent should act on.
 *
 * The WP_Error *code* stays `weglot_cloud_http_<code>` — wg_user_info_status()
 * classifies rejected-vs-unreachable off that exact string, so it must not be
 * renamed here. Only the status is refined so a direct caller (push / refresh)
 * gets an honest signal instead of a blanket 502: 401/403 = auth rejection
 * (rotate the key, do not retry), 5xx / 408 / 429 = transient upstream (retry),
 * any other 4xx = deterministic client error (do not retry).
 */
function wg_cloud_http_status(int $code): int
{
    if ($code === 401 || $code === 403) {
        return 403;
    }
    if ($code === 408 || $code === 429 || $code >= 500) {
        return 502;
    }
    return 400;
}

/**
 * GET against the Weglot Cloud API. Returns the decoded JSON body on success
 * or a WP_Error on transport / non-2xx / JSON-decode failure. Tests stub the
 * underlying HTTP via the `pre_http_request` filter — this helper never calls
 * out otherwise.
 *
 * @param  array<string, scalar> $query
 * @return array<string, mixed>|WP_Error
 */
function wg_remote_get(string $path, array $query = [], int $timeout_seconds = 4): array|WP_Error
{
    $url = wg_api_url() . '/' . ltrim(string: $path, characters: '/');
    if ($query !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    $response = wp_remote_get($url, [
        'timeout' => $timeout_seconds,
        'headers' => ['Accept' => 'application/json'],
    ]);

    if ($response instanceof WP_Error) {
        // SECURITY: cURL/transport errors commonly echo the full request URL,
        // which carries api_key=... in the query string. Redact before wrap.
        return new WP_Error('weglot_cloud_transport', wg_redact_credentials_from_body($response->get_error_message()), [
            'status' => 502,
        ]);
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    /** @var mixed $body_raw */
    $body_raw = wp_remote_retrieve_body($response);
    $body_string = is_string($body_raw) ? $body_raw : '';

    if ($code < 200 || $code >= 300) {
        // SECURITY: redact credential before exposing the upstream body. The
        // request URL carries `api_key=...` and some upstream error bodies echo
        // back the request context.
        return new WP_Error('weglot_cloud_http_' . $code, sprintf('Weglot cloud returned HTTP %d', $code), [
            'status' => wg_cloud_http_status($code),
            'body' => wg_redact_credentials_from_body($body_string),
        ]);
    }

    /** @var mixed $decoded */
    $decoded = json_decode($body_string, associative: true);
    if (!is_array($decoded)) {
        return new WP_Error('weglot_cloud_invalid_json', 'Weglot cloud returned non-JSON body', ['status' => 502]);
    }
    if (wg_array_is_list($decoded)) {
        return new WP_Error(
            'weglot_cloud_invalid_shape',
            'Weglot cloud returned a JSON list where an object was expected',
            ['status' => 502],
        );
    }

    /** @var array<string, mixed> $decoded */
    return $decoded;
}

/**
 * POST against the Weglot Cloud API. Same error-handling contract as
 * wg_remote_get; the `$body` is JSON-encoded and the upstream
 * `technology: wordpress` header is sent to match the WP plugin's own writes.
 *
 * @param  array<string, mixed>  $body
 * @param  array<string, scalar> $query
 * @return array<string, mixed>|WP_Error
 */
function wg_remote_post(string $path, array $body, array $query = [], int $timeout_seconds = 8): array|WP_Error
{
    $url = wg_api_url() . '/' . ltrim(string: $path, characters: '/');
    if ($query !== []) {
        $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($query);
    }

    $json_body = wp_json_encode($body);
    if (!is_string($json_body)) {
        return new WP_Error('weglot_json_encode_fail', 'Could not JSON-encode the request body.', ['status' => 500]);
    }

    $response = wp_remote_post($url, [
        'timeout' => $timeout_seconds,
        'body' => $json_body,
        'headers' => [
            'Content-Type' => 'application/json; charset=utf-8',
            'Accept' => 'application/json',
            'technology' => 'wordpress',
        ],
    ]);

    if ($response instanceof WP_Error) {
        // SECURITY: same redaction as wg_remote_get — transport errors echo
        // the URL which contains api_key=...
        return new WP_Error('weglot_cloud_transport', wg_redact_credentials_from_body($response->get_error_message()), [
            'status' => 502,
        ]);
    }

    $code = (int) wp_remote_retrieve_response_code($response);
    /** @var mixed $body_raw */
    $body_raw = wp_remote_retrieve_body($response);
    $body_string = is_string($body_raw) ? $body_raw : '';

    if ($code < 200 || $code >= 300) {
        return new WP_Error('weglot_cloud_http_' . $code, sprintf('Weglot cloud returned HTTP %d', $code), [
            'status' => wg_cloud_http_status($code),
            'body' => wg_redact_credentials_from_body($body_string),
        ]);
    }

    /** @var mixed $decoded */
    $decoded = json_decode($body_string, associative: true);
    if (!is_array($decoded)) {
        return new WP_Error('weglot_cloud_invalid_json', 'Weglot cloud returned non-JSON body', ['status' => 502]);
    }
    if (wg_array_is_list($decoded)) {
        return new WP_Error(
            'weglot_cloud_invalid_shape',
            'Weglot cloud returned a JSON list where an object was expected',
            ['status' => 502],
        );
    }

    /** @var array<string, mixed> $decoded */
    return $decoded;
}

/**
 * Strip Weglot credentials from a body string that may be echoed back to the
 * agent. Covers the `api_key=value` query-string and the `"api_key":"value"`
 * JSON variants seen in upstream errors.
 */
function wg_redact_credentials_from_body(string $body): string
{
    $redacted = (string) preg_replace(
        pattern: '/(api_key(?:_private)?=)[^&\s"]+/i',
        replacement: '$1***REDACTED***',
        subject: $body,
    );
    return (string) preg_replace(
        pattern: '/("api_key(?:_private)?"\s*:\s*")[^"]+(")/i',
        replacement: '$1***REDACTED***$2',
        subject: $redacted,
    );
}

/**
 * Strip credential keys from a decoded array payload before exposing it to
 * the agent. Operates recursively on nested arrays. Use this before placing
 * a cloud response into a WP_Error or success payload.
 *
 * @param  array<array-key, mixed> $payload
 * @return array<array-key, mixed>
 */
function wg_redact_credentials_from_payload(array $payload): array
{
    /** @var mixed $value */
    foreach ($payload as $key => $value) {
        if (is_string($key) && wg_is_credential_key($key)) {
            $payload[$key] = '***REDACTED***';
            continue;
        }
        if (is_array($value)) {
            $payload[$key] = wg_redact_credentials_from_payload($value);
        }
    }
    return $payload;
}

/**
 * Whether a payload key name looks like a credential / secret holder. Used to
 * gate redaction without inlining literal credential-shaped strings that trip
 * mago's no-insecure-comparison lint rule.
 */
function wg_is_credential_key(string $key): bool
{
    static $exact_keys = ['token' => true, 'secret' => true, 'password' => true];
    if (str_contains($key, 'api_key')) {
        return true;
    }
    return isset($exact_keys[$key]);
}

/**
 * PHP 8.0-compatible `array_is_list()` polyfill. The project targets 8.0 and
 * the native function only exists on 8.1+.
 *
 * @param array<array-key, mixed> $value
 */
function wg_array_is_list(array $value): bool
{
    $expected = 0;
    foreach (array_keys($value) as $key) {
        if ($key !== $expected) {
            return false;
        }
        $expected++;
    }
    return true;
}

/**
 * Mask a credential for output. Keeps the leading prefix (wg_, wg2_ etc.) plus
 * the last 4 chars, hides everything in between. Empty string → empty string.
 */
function wg_mask_api_key(string $credential): string
{
    if ($credential === '') {
        return '';
    }
    $len = strlen($credential);
    if ($len <= 8) {
        return str_repeat(string: '*', times: $len);
    }
    $underscore_pos = strpos(haystack: $credential, needle: '_');
    $prefix_len = $underscore_pos !== false ? $underscore_pos + 1 : 3;
    $prefix = substr($credential, offset: 0, length: $prefix_len);
    $suffix = substr($credential, offset: $len - 4);
    return $prefix . str_repeat(string: '*', times: max(4, $len - $prefix_len - 4)) . $suffix;
}

/**
 * Cached cloud user-info / project payload. The user-info endpoint reports
 * the plan, language limits, and project name keyed by the private credential;
 * we use it both as a quick health check and as the gate for Pro-plan
 * abilities (glossary etc.).
 *
 * Returns null when the credential is missing or the cloud rejects it; the
 * caller decides whether that is an error or a soft "free tier" state.
 *
 * @return array<string, mixed>|null
 */
function wg_user_info_cached(): ?array
{
    $status = wg_user_info_status();
    return $status['answer'];
}

/**
 * Same as wg_user_info_cached but exposes the full status struct: which lets
 * callers distinguish "no credential", "cloud rejected the key", and "cloud
 * unreachable" instead of collapsing every failure to null.
 *
 * Cache is keyed by current blog id + credential hash so multisite
 * switch_to_blog() and credential rotations do not return stale answers.
 * Only successful and deterministic-no-credential outcomes are cached:
 * transient failures are NOT cached so a same-request retry can recover.
 *
 * @return array{state: 'ok'|'no_credential'|'rejected'|'unreachable', answer: array<string, mixed>|null, error_code: string|null, error_message: string|null}
 */
function wg_user_info_status(): array
{
    /** @var array<string, array{state: 'ok'|'no_credential'|'rejected'|'unreachable', answer: array<string, mixed>|null, error_code: string|null, error_message: string|null}> $cache */
    static $cache = [];

    if (wg_user_info_status_reset_flag()) {
        $cache = [];
    }

    $credential = wg_api_key_private();
    if ($credential === '') {
        $credential = wg_api_key();
    }
    $key = get_current_blog_id() . '|' . ($credential === '' ? '' : md5($credential));

    if (isset($cache[$key])) {
        return $cache[$key];
    }

    if ($credential === '') {
        $cache[$key] = wg_status_no_credential();
        return $cache[$key];
    }

    $result = wg_remote_get(path: '/projects/owner', query: ['api_key' => $credential]);
    $status = wg_status_from_result($result);

    // Cache only deterministic outcomes (ok, rejected). Transient unreachable
    // states are NOT cached so a same-request retry can recover.
    if ($status['state'] === 'ok' || $status['state'] === 'rejected') {
        $cache[$key] = $status;
    }
    return $status;
}

/**
 * Reset the wg_user_info_status per-request cache. Call after persisting a
 * new credential (set-api-key) so subsequent same-request reads observe the
 * rotation immediately instead of returning the stale rejected/ok entry for
 * the prior credential.
 */
function wg_user_info_cache_bust(): void
{
    // The static cache lives inside the wg_user_info_status() closure. We
    // bust it by invoking with a sentinel reset hook — implemented as a
    // static-bound flag the function checks at the top.
    wg_user_info_status_reset_flag(set: true);
}

/**
 * Internal: holds and reports a "should-reset" flag for wg_user_info_status.
 * Reading consumes the flag (so the next call resets and clears it).
 */
function wg_user_info_status_reset_flag(?bool $set = null): bool
{
    static $flag = false;
    if ($set !== null) {
        return $flag = $set;
    }
    $current = $flag;
    $flag = false;
    return $current;
}

/**
 * @return array{state: 'no_credential', answer: null, error_code: string, error_message: string}
 */
function wg_status_no_credential(): array
{
    return [
        'state' => 'no_credential',
        'answer' => null,
        'error_code' => 'weglot_no_api_key',
        'error_message' => 'No Weglot API credential configured.',
    ];
}

/**
 * One-shot cloud probe of a single credential. Used by set-api-key to
 * validate public and private keys independently before persistence. 8s
 * timeout — user-explicit validation, not opportunistic.
 *
 * @return array{state: 'ok'|'rejected'|'unreachable', answer: array<string, mixed>|null, error_code: string|null, error_message: string|null}
 */
function wg_validate_credential_via_cloud(string $credential): array
{
    $probe = wg_remote_get(path: '/projects/owner', query: ['api_key' => $credential], timeout_seconds: 8);
    return wg_status_from_result($probe);
}

/**
 * Reduce a wg_remote_get result (decoded array or WP_Error) into a status struct.
 *
 * @param  array<string, mixed>|WP_Error $result
 * @return array{state: 'ok'|'rejected'|'unreachable', answer: array<string, mixed>|null, error_code: string|null, error_message: string|null}
 */
function wg_status_from_result(array|WP_Error $result): array
{
    if ($result instanceof WP_Error) {
        return wg_status_from_wp_error($result);
    }

    // Accept both int 1, string "1", AND bool true as the upstream success
    // marker — wg_coerce_int_or_null returns null for booleans which
    // historically misclassified `succeeded: true` as a rejection.
    /** @var mixed $succeeded_raw */
    $succeeded_raw = $result['succeeded'] ?? null;
    $succeeded_ok = $succeeded_raw === true || wg_coerce_int_or_null($succeeded_raw) === 1;

    if ($succeeded_ok && isset($result['answer']) && is_array($result['answer'])) {
        /** @var array<string, mixed> $answer */
        $answer = $result['answer'];
        return ['state' => 'ok', 'answer' => $answer, 'error_code' => null, 'error_message' => null];
    }

    // SECURITY: upstream error messages can echo back the rejected
    // credential ("Invalid api_key=..."). Always redact before exposing.
    $upstream_error = is_string($result['error'] ?? null) ? wg_redact_credentials_from_body($result['error']) : '';

    // Differentiate genuine auth-rejection from transient malformed/degraded
    // responses. ONLY classify as 'rejected' (cached) when the upstream error
    // explicitly signals auth — everything else is treated as 'unreachable'
    // (not cached) so a same-request retry can recover from a transient
    // soft-fail or a partial outage.
    if ($succeeded_ok && (!isset($result['answer']) || !is_array($result['answer']))) {
        // succeeded but answer missing/malformed → upstream shape drift, not
        // an auth issue. Don't cache, let the caller retry.
        return [
            'state' => 'unreachable',
            'answer' => null,
            'error_code' => 'weglot_cloud_invalid_shape',
            'error_message' => $upstream_error !== ''
                ? $upstream_error
                : 'Weglot cloud answered success but the answer payload was missing or malformed.',
        ];
    }

    $is_auth_reject =
        $upstream_error !== ''
        && preg_match(pattern: '/invalid.*api[_ ]?key|unauthor|forbidden|expired.*key/i', subject: $upstream_error)
            === 1;
    if (!$is_auth_reject) {
        return [
            'state' => 'unreachable',
            'answer' => null,
            'error_code' => 'weglot_cloud_soft_fail',
            'error_message' => $upstream_error !== '' ? $upstream_error : 'Weglot cloud returned a soft-fail body.',
        ];
    }

    return [
        'state' => 'rejected',
        'answer' => null,
        'error_code' => 'weglot_cloud_key_invalid',
        'error_message' => $upstream_error,
    ];
}

/**
 * Classify a WP_Error from wg_remote_get into a status struct. HTTP-401/403
 * is auth rejection; anything else is transient unreachability.
 *
 * @return array{state: 'rejected'|'unreachable', answer: null, error_code: string, error_message: string}
 */
function wg_status_from_wp_error(WP_Error $err): array
{
    $code_raw = $err->get_error_code();
    $code = is_string($code_raw) ? $code_raw : '';
    $message = $err->get_error_message();
    // Weglot's /projects/owner returns HTTP 400 (and occasionally 404) for
    // genuinely invalid API keys per upstream User_Api_Service_Weglot — not
    // just 401/403. Treat those as auth-rejection too so the result gets
    // cached and the agent receives a clear "rotate the key" signal instead
    // of looping on "unreachable".
    static $auth_reject_codes = [
        'weglot_cloud_http_400' => true,
        'weglot_cloud_http_401' => true,
        'weglot_cloud_http_403' => true,
        'weglot_cloud_http_404' => true,
    ];
    $is_auth_reject = isset($auth_reject_codes[$code]);
    return [
        'state' => $is_auth_reject ? 'rejected' : 'unreachable',
        'answer' => null,
        'error_code' => $code !== '' ? $code : 'weglot_cloud_unreachable',
        'error_message' => $message !== '' ? $message : 'Weglot cloud request failed.',
    ];
}

/**
 * Whether the cloud plan supports a Pro-only feature. Reads the cached
 * user-info plan; missing / unknown → false. Conservative: gates Pro
 * surfaces out of discover-abilities when we cannot confirm the plan.
 */
function wg_plan_supports(string $feature): bool
{
    $info = wg_user_info_cached();
    if ($info === null) {
        return false;
    }

    $plan_id = wg_coerce_int_or_null($info['plan_id'] ?? null);

    return match ($feature) {
        // Translated URL slugs are a PRO-tier feature: Weglot's pricing table
        // lists them for Pro and above and excludes Free, Starter and Business,
        // its own admin notice says "Requires Pro plan minimum"
        // (Actions\Admin\Metabox_Url_Translate_Weglot ~:82), and get_plans()
        // puts Pro at ids 6-7. The previous `>= 4` reported the feature as
        // available on Business, where it is not.
        //
        // This stays a hint, not an authority: the plugin gates nothing by plan
        // in code — `plan_id` appears nowhere in its source outside get_plans()
        // — so the server is what actually decides. Measured: /translations/slugs
        // answers 403 on a Free project.
        'slugs' => $plan_id !== null && $plan_id >= 6,
        default => false,
    };
}

/**
 * Coerce a maybe-numeric value to int or return null. Used to digest
 * loosely-typed cloud responses.
 */
function wg_coerce_int_or_null(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && $value !== '' && is_numeric($value)) {
        return (int) $value;
    }
    return null;
}

/**
 * Coerce a maybe-anything value to a bool, defaulting to false when the input
 * is null or non-coercible. Truthy mixed values map to true, everything else
 * (0, '', '0', explicit "false") to false. Used to digest persisted option
 * blobs whose values are not strictly typed.
 */
function wg_coerce_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return $value !== 0 && $value !== 0.0;
    }
    if (is_string($value)) {
        return $value !== '' && $value !== '0' && strtolower($value) !== 'false';
    }
    return false;
}

/**
 * Variant of wg_coerce_bool whose unknown-input default is `true`. Used for
 * option fields whose persisted default is `true` (e.g. Weglot button
 * full_name / with_flags / is_dropdown), so a missing key reads as "the
 * Weglot-shipped default" rather than as `false`.
 */
function wg_coerce_bool_true_default(mixed $value): bool
{
    if ($value === null) {
        return true;
    }
    return wg_coerce_bool($value);
}

/**
 * Coerce a maybe-string value to a trimmed string or null. Empty strings
 * become null so the caller can keep null as the explicit "absent" marker.
 */
function wg_coerce_string_or_null(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $trimmed = trim($value);
    return $trimmed === '' ? null : $trimmed;
}

/**
 * Map a Weglot plan_id to a human label. Mirrors User_Api_Service_Weglot::get_plans()
 * with the documented ID ranges; unknown IDs map to null.
 */
function wg_plan_name_from_id(?int $plan_id): ?string
{
    if ($plan_id === null) {
        return null;
    }
    return match (true) {
        $plan_id >= 1 && $plan_id <= 3 => 'starter_free',
        $plan_id >= 4 && $plan_id <= 5 => 'business',
        $plan_id >= 6 && $plan_id <= 7 => 'pro',
        default => null,
    };
}

/**
 * The plan name as the cloud reports it, falling back to the id-derived guess.
 *
 * /projects/owner returns `plan_name` directly — "Free" on the account this was
 * checked against, where the derived table said "starter_free". Reporting an
 * invented label when the authoritative one is in the same payload is how an
 * agent ends up telling the user about a plan that does not exist by that name.
 *
 * @param array<string, mixed> $info the /projects/owner answer
 */
function wg_plan_name_from_info(array $info, ?int $plan_id): ?string
{
    $reported = wg_coerce_string_or_null($info['plan_name'] ?? null);
    return $reported ?? wg_plan_name_from_id($plan_id);
}

/**
 * The destination-language limit as the cloud reports it, falling back to the
 * id-derived guess.
 *
 * /projects/owner returns `languages_limit`. Deriving it from a hardcoded plan
 * table means the number silently goes wrong the moment Weglot changes a tier.
 *
 * @param array<string, mixed> $info the /projects/owner answer
 */
function wg_language_limit_from_info(array $info, ?int $plan_id): ?int
{
    $reported = wg_coerce_int_or_null($info['languages_limit'] ?? null);
    return $reported ?? wg_language_limit_from_plan($plan_id);
}

/**
 * Map a Weglot plan_id to its language limit. Mirrors User_Api_Service_Weglot::get_plans().
 */
function wg_language_limit_from_plan(?int $plan_id): ?int
{
    if ($plan_id === null) {
        return null;
    }
    return match (true) {
        $plan_id >= 1 && $plan_id <= 3 => 1,
        $plan_id >= 4 && $plan_id <= 5 => 3,
        $plan_id >= 6 && $plan_id <= 7 => 5,
        default => null,
    };
}

/**
 * Extract destination-language ISO codes from the v3 options blob. Weglot
 * stores destination languages as an array of objects/arrays with a
 * `language_to` key; we accept either shape and drop entries that lack it.
 *
 * @param  array<string, mixed> $options
 * @return list<string>
 */
function wg_destination_codes_from_options(array $options): array
{
    /** @var mixed $languages */
    $languages = $options['languages'] ?? [];
    if (!is_array($languages)) {
        return [];
    }
    $codes = [];
    /** @var mixed $entry */
    foreach ($languages as $entry) {
        $code = wg_extract_language_to($entry);
        if ($code !== null) {
            // Canonicalize to lowercase so callers (add/delete-translated-url,
            // list-translated-urls, etc.) compare consistently regardless of
            // how the destination was persisted.
            $codes[] = strtolower($code);
        }
    }
    return $codes;
}

/**
 * The destination languages currently kept private — visible to logged-in
 * admins only.
 *
 * Weglot has no `private_mode.enabled` flag. `private_mode` is DERIVED from the
 * language list by Schema_Option_V3 as `{language_code: !enabled}`, so privacy
 * is per language and is the negation of `enabled`. Reading it as a single
 * boolean returned false on every install, whatever was configured.
 *
 * @param  array<string, mixed> $options
 * @return list<string>
 */
function wg_private_languages_from_options(array $options): array
{
    /** @var mixed $map */
    $map = $options['private_mode'] ?? null;
    $private = [];
    if (is_array($map)) {
        /** @var mixed $is_private */
        foreach ($map as $code => $is_private) {
            if (!is_string($code) || $code === '' || !wg_coerce_bool($is_private)) {
                continue;
            }
            $private[] = strtolower($code);
        }
        return $private;
    }

    // No derived map available (degraded read): fall back to the language list,
    // applying the same rule Weglot applies — private means NOT enabled.
    /** @var mixed $languages */
    $languages = $options['languages'] ?? [];
    if (!is_array($languages)) {
        return [];
    }
    /** @var mixed $entry */
    foreach ($languages as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $code = wg_extract_language_to($entry);
        if ($code === null) {
            continue;
        }
        $enabled = array_key_exists('enabled', $entry)
            ? wg_coerce_bool($entry['enabled'])
            : wg_coerce_bool_true_default($entry['public'] ?? null);
        if (!$enabled) {
            $private[] = strtolower($code);
        }
    }
    return $private;
}

/**
 * Pull `language_to` from one destination-language entry (array or object form).
 */
function wg_extract_language_to(mixed $entry): ?string
{
    if (is_array($entry) && isset($entry['language_to']) && is_string($entry['language_to'])) {
        return $entry['language_to'];
    }
    if (is_object($entry) && property_exists($entry, 'language_to') && is_string($entry->language_to)) {
        return $entry->language_to;
    }
    return null;
}

/**
 * Persist the v3 options blob back to wp_options. Returns true on actual
 * change, false when the new value matches the persisted one (update_option
 * is a no-op then). Callers should already be holding wg_with_options_lock.
 *
 * @param array<string, mixed> $options
 */
function wg_options_save(array $options): bool
{
    return update_option(option: wg_option_name_v3(), value: $options, autoload: false);
}

/**
 * Error returned when the v3 options blob changed under an in-flight write —
 * i.e. a native Weglot admin save landed between our read and our commit. It is
 * distinct from weglot_lock_busy (another ABILITY holds the lock): here the lock
 * WAS held, but a non-locking actor (the Weglot admin UI) wrote concurrently.
 */
function wg_options_changed_error(): WP_Error
{
    return new WP_Error(
        'weglot_options_changed',
        'The Weglot options changed under this write (a concurrent Weglot admin save). Re-read with weglot-list-* / weglot-get-settings and retry.',
        ['status' => 409],
    );
}

/**
 * Serialize a scoped read-modify-write of the v3 options blob with cross-actor
 * drift detection and delta-merge. The wg_with_options_lock() mutex only
 * serializes ability-to-ability writes; it does NOT cover the native Weglot
 * admin save, which writes the same blob without taking the lock. This helper
 * closes that gap:
 *
 *   1. read the blob and hand it to $mutator, which returns [modified blob, result];
 *   2. RE-READ the blob; if any owned sub-tree drifted since step 1 (a native
 *      save landed), abort 409 weglot_options_changed instead of clobbering it;
 *   3. otherwise splice ONLY the owned keys from the mutator's blob onto the
 *      FRESH blob — so a concurrent native edit to OTHER fields survives — and save.
 *
 * The mutator returns a WP_Error to reject the write (validation, duplicate, …)
 * without persisting.
 *
 * @template TResult of array<string, mixed>
 * @param  list<string> $owned_keys the top-level option keys this write may change
 * @param  callable(array<string, mixed>): (array{0: array<string, mixed>, 1: TResult}|WP_Error) $mutator
 * @return TResult|WP_Error
 */
function wg_write_options_delta(array $owned_keys, callable $mutator): array|WP_Error
{
    return wg_with_options_lock(static function () use ($owned_keys, $mutator): array|WP_Error {
        // Read past WordPress's object cache. `get_option` caches the row for
        // the whole request, so both the baseline and the drift re-read below
        // would return the same snapshot and the guard would never fire — a
        // native Weglot admin save landing in ANOTHER request is exactly what
        // it exists to catch, and that is precisely what the cache hides.
        wg_options_cache_bust();
        $before = wg_options_raw();

        // SEEDING. The mutator must see the configuration that is actually in
        // force, not the local blob — which on a cloud-driven install holds
        // none of it (see wg_options_resolution). Without this, a mutator that
        // reads `excluded_paths` off an empty local blob would rebuild the key
        // from scratch, and the next push-options-to-cloud would ship that
        // truncated set as the project's full rule list, deleting every rule
        // configured on weglot.com. Seeding makes the local blob a genuine
        // delta over the live configuration.
        //
        // Only ABSENT keys are seeded: once an ability has written a key, that
        // local value is the pending edit and must win.
        $seeded = wg_seed_owned_keys($before, $owned_keys);
        if ($seeded instanceof WP_Error) {
            return $seeded;
        }

        $outcome = $mutator($seeded);
        if ($outcome instanceof WP_Error) {
            return $outcome;
        }
        [$after, $result] = $outcome;

        // Drift detection deliberately compares the RAW local blob before and
        // after, never the seeded view: seeded values were never persisted, so
        // comparing them would report drift on every first write.
        wg_options_cache_bust();
        $fresh = wg_options_raw();
        foreach ($owned_keys as $key) {
            $before_has = array_key_exists($key, $before);
            $fresh_has = array_key_exists($key, $fresh);
            if ($before_has !== $fresh_has || ($before[$key] ?? null) !== ($fresh[$key] ?? null)) {
                return wg_options_changed_error();
            }
        }

        foreach ($owned_keys as $key) {
            if (!array_key_exists($key, $after)) {
                unset($fresh[$key]);
                continue;
            }
            $fresh[$key] = $after[$key];
        }
        wg_options_save($fresh);

        wg_pending_keys_add(wg_keys_the_mutator_changed($seeded, $after, $owned_keys));
        wg_bust_resolver_cache_after_custom_urls_write($owned_keys);

        // NOTE: for every OTHER key the `weglot_cache_cdn` transient is
        // deliberately NOT busted here. It does not hold the cloud answer verbatim —
        // get_options_from_cdn_with_api_key stores
        // `array_merge(get_options_bdd_v3(), $body)`, the local blob merged
        // with the cloud one — so a stale copy carries pre-write local values
        // in the slot that wins the merge, which would hide the edit just made.
        // The pending overlay already closes that: a key recorded as pending is
        // read straight from the local blob, whatever the transient holds. And
        // busting here would be actively harmful, because that transient is the
        // API-shaped base the NEXT write seeds from (wg_api_shaped_options);
        // dropping it forces an extra cloud round-trip per write and makes the
        // whole write surface fail whenever the cloud is briefly unreachable.
        // The push and the refresh do bust it — there the cached copy really is
        // superseded.

        return $result;
    });
}

/**
 * The project settings in the API schema — the shape the cloud sends and
 * accepts, NOT the runtime projection.
 *
 * This distinction is the whole point. `weglot_get_options()` runs the payload
 * through `Schema_Option_V3`, which is lossy: a `languages` entry arrives from
 * the cloud with ten fields and comes out with five, `enabled` renamed to
 * `public`. Reading that projection is fine. SEEDING a write from it, and then
 * publishing the result, would send the cloud a truncated languages array and
 * silently drop `enabled`, `automatic_translation_enabled`, `provider`,
 * `deleted_at`, `connect_host_destination` and `custom_flag` from every
 * language on the project — the exact data-loss class this whole fix exists to
 * prevent, reintroduced through the back door.
 *
 * Source order: the CDN transient (already API-shaped, no HTTP), then a live
 * `/projects/settings` read. Returns null when neither can establish the
 * current settings — the caller must then refuse to write rather than invent a
 * partial base.
 *
 * @return array<string, mixed>|null
 */
function wg_api_shaped_options(): ?array
{
    /** @var mixed $cached */
    $cached = get_transient('weglot_cache_cdn');
    if (is_array($cached) && wg_refresh_cloud_payload_is_usable($cached)) {
        /** @var array<string, mixed> $cached */
        return $cached;
    }

    $private = wg_api_key_private();
    if ($private === '') {
        return null;
    }
    $fresh = wg_remote_get(path: '/projects/settings', query: ['api_key' => $private], timeout_seconds: 8);
    if ($fresh instanceof WP_Error || !wg_refresh_cloud_payload_is_usable($fresh)) {
        return null;
    }
    return $fresh;
}

/**
 * Error returned when a write cannot establish the configuration it would be
 * editing. Refusing beats seeding from nothing: a mutator that starts from an
 * empty base produces a complete-looking value which the next publish would
 * ship as the project's entire setting.
 */
function wg_config_unavailable_error(): WP_Error
{
    return new WP_Error(
        'weglot_config_unavailable',
        'Could not read the current Weglot project settings, so this edit would be built on an unknown base and could truncate the published configuration. Check the API credentials with weglot-check-setup and retry.',
        ['status' => 503],
    );
}

/**
 * Refresh every owned key that is NOT a live pending edit from the API-shaped
 * project settings, so the mutator works on the configuration actually in force.
 *
 * THE GATE IS THE MARKER, NOT PRESENCE. Presence used to decide this, and it
 * was wrong in a way that only shows up after the first publish: the push
 * clears the marker but leaves the published values in the local blob, so from
 * then on every key looked "already present" and was never refreshed again. A
 * write would start from the snapshot published minutes or weeks earlier, and
 * the next push would send it back — silently reverting anything edited on
 * dashboard.weglot.com in between. That is the cross-actor lost-write this
 * integration documents elsewhere, reintroduced by the seeding gate.
 *
 * A key listed in the marker is a real unpublished edit and must survive.
 * Everything else is refreshed. Keys the settings payload does not carry
 * (`custom_urls`) are left alone: there the local blob IS the truth.
 *
 * Returns a WP_Error when a key needs refreshing and the settings cannot be read.
 *
 * @param  array<string, mixed> $local
 * @param  list<string>         $owned_keys
 * @return array<string, mixed>|WP_Error
 */
function wg_seed_owned_keys(array $local, array $owned_keys): array|WP_Error
{
    $pending = wg_pending_keys();
    $cloud_owned = wg_cloud_owned_keys();
    // A key is a candidate for refreshing only when it is BOTH not a live
    // pending edit (those must survive untouched) AND actually held by the
    // cloud. `custom_urls` is owned and publishable but absent from the settings
    // payload, so the local blob is its only source.
    //
    // Candidates split by how bad it is to proceed without the settings:
    //   REQUIRED — no local value at all. Building from nothing produces a
    //     complete-looking value that the next publish ships as the project's
    //     entire setting. Refuse.
    //   PREFERRED — a local value exists but may predate a dashboard edit.
    //     Refresh when we can; when we cannot, keep it. Failing the write here
    //     would make the whole surface depend on cloud reachability for no
    //     safety gain — the existing value is the same base the write would
    //     have used anyway.
    $required = [];
    $preferred = [];
    foreach ($owned_keys as $key) {
        if (in_array($key, $pending, strict: true) || !in_array($key, $cloud_owned, strict: true)) {
            continue;
        }
        if (array_key_exists($key, $local)) {
            $preferred[] = $key;
            continue;
        }
        $required[] = $key;
    }
    if ($required === [] && $preferred === []) {
        return $local;
    }

    $api_shaped = wg_api_shaped_options();
    if ($api_shaped === null) {
        return $required === [] ? $local : wg_config_unavailable_error();
    }

    foreach (array_merge($required, $preferred) as $key) {
        if (!array_key_exists($key, $api_shaped)) {
            continue;
        }
        $local[$key] = $api_shaped[$key];
    }
    return $local;
}

/**
 * Which publishable keys the mutator actually changed, compared against the
 * seeded view it was handed.
 *
 * Not "every owned key": that marked a no-op write as pending — an idempotent
 * edit-button-settings call changes nothing yet claimed work to publish. And
 * not "whatever differs from the previously stored value" either, because
 * seeding rewrites the blob too: a key merely refreshed from the cloud would be
 * marked, and the push would republish a value it had just read.
 *
 * `exclude_urls` never appears here — the exclude abilities declare it as owned
 * but normalize it away on write.
 *
 * @param  array<string, mixed> $seeded what the mutator received
 * @param  array<string, mixed> $after  what it returned
 * @param  list<string>         $owned_keys
 * @return list<string>
 */
function wg_keys_the_mutator_changed(array $seeded, array $after, array $owned_keys): array
{
    $agent_owned = wg_agent_owned_keys();
    $changed = [];
    foreach ($owned_keys as $key) {
        if (!in_array($key, $agent_owned, strict: true)) {
            continue;
        }
        $existed = array_key_exists($key, $seeded);
        $exists = array_key_exists($key, $after);
        if ($existed === $exists && ($seeded[$key] ?? null) === ($after[$key] ?? null)) {
            continue;
        }
        $changed[] = $key;
    }
    return $changed;
}

/**
 * Drop the resolver cache after a write that touched `custom_urls`.
 *
 * That is the only owned key the read path does not overlay (see
 * wg_cloud_owned_keys), so it is the only one a stale cache still answers for.
 * And the cache CAN hold it: on the Weglot settings page,
 * get_options_from_cdn_with_api_key stores `array_merge(local, cloud)` —
 * custom_urls included — and get_options() then lets that frozen copy win over
 * the blob just written. Reproduced on a live site: add-translated-url reported
 * persisted=true and list-translated-urls returned zero entries.
 *
 * Narrow on purpose: the pure-REST cache holds the bare cloud body, has no
 * custom_urls, and must survive as the base wg_api_shaped_options() seeds from.
 *
 * @param list<string> $owned_keys
 */
function wg_bust_resolver_cache_after_custom_urls_write(array $owned_keys): void
{
    if (!in_array('custom_urls', $owned_keys, strict: true)) {
        return;
    }
    /** @var mixed $cached */
    $cached = get_transient('weglot_cache_cdn');
    if (is_array($cached) && array_key_exists('custom_urls', $cached)) {
        delete_transient('weglot_cache_cdn');
    }
}

/**
 * Read the raw `custom_urls` cluster preserving every persisted key and value
 * verbatim. Used by write-path mutators that need to merge their change into
 * the existing structure WITHOUT discarding malformed entries that other
 * actors (Weglot admin save, legacy migrations) may have left behind.
 *
 * @param  array<string, mixed> $options
 * @return array<array-key, mixed>
 */
function wg_custom_urls_raw(array $options): array
{
    /** @var mixed $raw */
    $raw = $options['custom_urls'] ?? [];
    return is_array($raw) ? $raw : [];
}

/**
 * Read the translated-URL (`custom_urls`) cluster as a normalized map of
 * language → from-slug (original) → to-slug (translated). Discards malformed
 * entries. Reading is always safe (no lock) because the helper does not mutate.
 *
 * STORAGE ORIENTATION. Weglot persists this cluster the other way round —
 * `translated => original`. That is not a guess: the SDK iterates it as
 * `foreach ($customUrls[...] as $translatedURL => $originalURL)` (weglot-php
 * `Util/Url.php`, both the request-matching and the URL-building paths), and
 * the plugin flips the API answer into that shape with `array_flip($body)`
 * when it caches slugs (`Option_Service_Weglot::get_slugs_from_api_with_api_key`).
 *
 * This helper is the single place that knows about the orientation: it flips
 * storage into the logical from → to direction every caller reasons in. The
 * write paths flip back via wg_custom_urls_store_entry(). Do not "simplify"
 * either one without re-reading both upstream sites.
 *
 * @param  array<string, mixed> $options
 * @return array<string, array<string, string>>
 */
function wg_custom_urls_from_options(array $options): array
{
    /** @var mixed $raw */
    $raw = $options['custom_urls'] ?? [];
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $slug_map */
    foreach ($raw as $language => $slug_map) {
        if (!is_string($language) || $language === '' || !is_array($slug_map)) {
            continue;
        }
        $clean = [];
        /** @var mixed $original */
        foreach ($slug_map as $translated => $original) {
            // Cast, do not type-check: PHP array keys are int|string, and it
            // silently converts a decimal-string key, so a slug like "2024" is
            // stored as the integer 2024. The previous `is_string()` filter
            // dropped exactly those pairs — invisible in every listing and
            // undeletable, because the delete lookup used the same view.
            $translated = (string) $translated;
            if ($translated === '' || !is_string($original) || $original === '') {
                continue;
            }
            // Flip: stored translated => original becomes original => translated.
            $clean[$original] = $translated;
        }
        if ($clean !== []) {
            // Canonicalize the language key to lowercase so reads via this
            // helper are case-stable regardless of how the entry was stored.
            $out[strtolower($language)] = $clean;
        }
    }
    return $out;
}

/**
 * Write one original → translated pair into a stored slug map, in the
 * orientation Weglot consumes (`translated => original`).
 *
 * Any pre-existing entry for the same ORIGINAL slug is dropped first: the
 * original is the logical key, but it lives in the value position, so a plain
 * assignment would leave the superseded pair behind and Weglot would end up
 * with two translated URLs claiming the same source page.
 *
 * @param  array<array-key, mixed> $stored_map storage-oriented map
 * @return array<array-key, mixed>
 */
function wg_custom_urls_store_entry(array $stored_map, string $original, string $translated): array
{
    /** @var mixed $stored_original */
    foreach ($stored_map as $stored_translated => $stored_original) {
        if (!is_string($stored_original) || $stored_original !== $original) {
            continue;
        }
        unset($stored_map[$stored_translated]);
    }
    $stored_map[$translated] = $original;
    return $stored_map;
}

/**
 * Drop every stored pair whose ORIGINAL slug matches, returning the pruned map
 * and how many entries were removed. Counterpart of wg_custom_urls_store_entry
 * for the delete path.
 *
 * @param  array<array-key, mixed> $stored_map storage-oriented map
 * @return array{0: array<array-key, mixed>, 1: int}
 */
function wg_custom_urls_forget_entry(array $stored_map, string $original): array
{
    $removed = 0;
    /** @var mixed $stored_original */
    foreach ($stored_map as $stored_translated => $stored_original) {
        if (!is_string($stored_original) || $stored_original !== $original) {
            continue;
        }
        unset($stored_map[$stored_translated]);
        $removed++;
    }
    return [$stored_map, $removed];
}

/**
 * Whether a given ISO code exists in the bundled Weglot SDK catalog. Used to
 * validate set-original-language / add-destination-language inputs before
 * touching the options blob.
 */
function wg_language_code_supported(string $code): bool
{
    if ($code === '') {
        return false;
    }
    foreach (wg_collect_language_entries() as $entry) {
        if ($entry['code'] === $code) {
            return true;
        }
    }
    return false;
}

/**
 * Run a callback while holding an exclusive lock on the Weglot v3 options
 * blob. When an external object cache is available, the lock uses
 * `wp_cache_add` (atomic add semantics on Redis/Memcached/etc.). Otherwise we
 * fall back to `add_option` (INSERT IGNORE on the underlying SQL).
 *
 * Lock keys include get_current_blog_id() so multisite subsites do not
 * collide. After $stale_after_seconds a stuck lock (PHP OOM / SIGKILL would
 * skip the try/finally unwind) gets stolen.
 *
 * @template T
 * @param  callable(): (T|WP_Error) $fn
 * @return T|WP_Error
 */
function wg_with_options_lock(callable $fn, int $stale_after_seconds = 30): mixed
{
    $key = sprintf('nvp_weglot_lock_%d', get_current_blog_id());
    $use_cache = function_exists('wp_using_ext_object_cache') && wp_using_ext_object_cache();

    // Per-acquisition owner token. The release path will only delete the lock
    // when the stored value still matches this token — preventing this
    // process from accidentally releasing a lock that was stolen as stale by
    // another process mid-callback (which would then let a third process
    // race in and produce a lost-write).
    $owner_token = wg_lock_new_owner_token();
    $owner = null;

    $max_attempts = 20;
    for ($attempt = 0; $attempt < $max_attempts; $attempt++) {
        $acquired_owner = $use_cache
            ? wg_lock_try_acquire_cache($key, $stale_after_seconds, $owner_token)
            : wg_lock_try_acquire_db($key, $stale_after_seconds, $owner_token);
        if ($acquired_owner !== null) {
            $owner = $acquired_owner;
            break;
        }
        usleep(microseconds: 100_000);
    }

    if ($owner === null) {
        return new WP_Error('weglot_lock_busy', 'Could not acquire the Weglot options lock', ['status' => 503]);
    }

    try {
        return $fn();
    } finally {
        $use_cache ? wg_lock_release_cache($key, $owner) : wg_lock_release_db($key, $owner);
    }
}

/**
 * Generate a fresh per-acquisition owner token. Hex 32 chars — enough entropy
 * to make collisions vanishingly unlikely across all concurrent processes.
 */
function wg_lock_new_owner_token(): string
{
    return bin2hex(random_bytes(16));
}

/**
 * Release a lock held in the external object cache. CAS: only delete the
 * key when the stored owner token still matches ours — prevents this
 * process from accidentally freeing a lock that was stolen as stale by a
 * different process mid-callback (which would then let a third process race
 * in and produce a lost-write).
 */
function wg_lock_release_cache(string $key, string $owner_token): void
{
    $current = wp_cache_get($key, group: 'nvp_weglot');
    $current_owner = wg_lock_extract_owner($current);
    if ($current_owner !== null && hash_equals($owner_token, $current_owner)) {
        wp_cache_delete($key, group: 'nvp_weglot');
    }
}

/**
 * Release a lock held in wp_options. CAS — same intent as
 * wg_lock_release_cache: only release when we still own the lock.
 */
function wg_lock_release_db(string $key, string $owner_token): void
{
    /** @var mixed $current */
    $current = get_option($key, default_value: '');
    $current_owner = wg_lock_extract_owner($current);
    if ($current_owner !== null && hash_equals($owner_token, $current_owner)) {
        delete_option($key);
    }
}

/**
 * Coerce a wp_cache_get value (mixed) into the int seconds timestamp it was
 * stored as, or null when the value is neither an int nor a digit-only string.
 */
function wg_lock_coerce_timestamp(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && ctype_digit($value)) {
        return (int) $value;
    }
    return null;
}

/**
 * Extract the timestamp portion from a lock value, which is now stored as
 * "<owner_token>|<timestamp>". Falls back to legacy integer values for
 * backward compatibility with older lock rows still in flight.
 */
function wg_lock_extract_timestamp(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (!is_string($value) || $value === '') {
        return null;
    }
    $pipe = strpos(haystack: $value, needle: '|');
    if ($pipe === false) {
        return ctype_digit($value) ? (int) $value : null;
    }
    $tail = substr($value, offset: $pipe + 1);
    return ctype_digit($tail) ? (int) $tail : null;
}

/**
 * Extract the owner token portion from a lock value stored as
 * "<owner_token>|<timestamp>". Returns null for legacy / malformed values.
 */
function wg_lock_extract_owner(mixed $value): ?string
{
    if (!is_string($value) || $value === '') {
        return null;
    }
    $pipe = strpos(haystack: $value, needle: '|');
    if ($pipe === false) {
        return null;
    }
    return substr($value, offset: 0, length: $pipe);
}

/**
 * Try to acquire the lock once via the external object cache (Redis/Memcached
 * etc.). wp_cache_add has atomic add semantics on most backends; TTL ensures
 * the lock cannot be permanently orphaned by a hard kill.
 */
function wg_lock_try_acquire_cache(string $key, int $stale_after_seconds, string $owner_token): ?string
{
    // Store the owner token plus the timestamp so the release path can do CAS
    // and the stale-steal can still use the timestamp portion.
    $value = $owner_token . '|' . time();
    // Margin generously above the longest expected critical section (push to
    // cloud at 60s + DNS/connect overhead). +30s rather than +10s closes the
    // race window where a slow upstream could let the cache entry expire
    // before the call completes.
    $expire = $stale_after_seconds + 30;
    if (wp_cache_add($key, $value, group: 'nvp_weglot', expire: $expire)) {
        return $owner_token;
    }
    $existing = wp_cache_get($key, group: 'nvp_weglot');
    $existing_seconds = wg_lock_extract_timestamp($existing);
    if ($existing_seconds !== null && (time() - $existing_seconds) > $stale_after_seconds) {
        wp_cache_delete($key, group: 'nvp_weglot');
        return wp_cache_add($key, $value, group: 'nvp_weglot', expire: $expire) ? $owner_token : null;
    }
    return null;
}

/**
 * Try to acquire the lock once via wp_options (INSERT IGNORE on the SQL).
 * Steals stale locks older than $stale_after_seconds.
 */
function wg_lock_try_acquire_db(string $key, int $stale_after_seconds, string $owner_token): ?string
{
    $now = time();
    $value = $owner_token . '|' . $now;
    if (add_option(option: $key, value: $value, deprecated: '', autoload: false)) {
        return $owner_token;
    }
    /** @var mixed $existing_raw */
    $existing_raw = get_option($key, default_value: '');
    $existing_seconds = wg_lock_extract_timestamp($existing_raw);
    // Treat unparseable / non-numeric / zero-value rows as stale so a corrupt
    // sentinel from another code path cannot wedge the write path forever.
    $is_stale =
        $existing_seconds === null || $existing_seconds === 0 || ($now - $existing_seconds) > $stale_after_seconds;
    if (!$is_stale) {
        return null;
    }
    delete_option($key);
    return add_option(option: $key, value: $value, deprecated: '', autoload: false) ? $owner_token : null;
}
