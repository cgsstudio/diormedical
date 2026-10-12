<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-push-options-to-cloud', [
    'label' => __('Push Weglot Options to Cloud', domain: 'novamira-pro'),
    'description' => __(
        'Publishes the pending local Weglot edits (source and destination languages, exclude rules, button style) to the Weglot Cloud so they take effect for visitors. This is the missing piece for the settings write abilities: set-original-language, add/delete-destination-language, add/delete-exclude-url and edit-button-settings all persist LOCALLY and have no effect until this ability runs. Safe by construction: the endpoint merges server-side, and the ability sends only the keys the write abilities actually changed — settings edited on dashboard.weglot.com that no ability touched are untouched. NOTE: translated URL slugs are NOT publishable here and need no push: Weglot keeps them on a separate resource, and add/delete-translated-url take effect on this site immediately (they return cloud_sync_pending=false). Requires the private API key (the cloud rejects /projects/settings calls with only the short public key). Returns which keys were overlaid, plus the cloud answer, and clears the local `weglot_cache_cdn` transient so the next page load re-fetches.',
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
            'pushed' => ['type' => 'boolean'],
            'overlaid_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Which settings keys carried a pending local edit and therefore replaced the cloud value. An empty list means nothing was pending and the cloud settings were re-sent unchanged.',
            ],
            'cloud_response' => [
                'type' => 'object',
                'description' => 'The Weglot Cloud response body, with credential-shaped keys (api_key, api_key_private, token, secret) redacted before exposure.',
            ],
            'cache_busted' => [
                'type' => 'boolean',
                'description' => 'Whether the local weglot_cache_cdn transient was deleted as part of the push. It takes effect from the NEXT request, not this one: Weglot keeps the CDN payload in an in-process property it checks before the transient, so a read chained after the push inside the same request still answers from the pre-push snapshot. Each ability call is its own request, so an agent doing add -> push -> list sees the new value.',
            ],
        ],
        'required' => ['pushed', 'overlaid_keys', 'cloud_response', 'cache_busted'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_push_options_to_cloud',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call AFTER a batch of settings write abilities (set-original-language, add/delete-destination-language, edit-button-settings, add/delete-exclude-url) — the ones that return cloud_sync_pending=true. Those abilities write locally ONLY: Weglot resolves its live configuration from the cloud and the cloud wins the merge, so nothing an agent writes changes the site until this push lands. Writes to the cloud, so it stays flagged destructive — it can overwrite a value someone changed on dashboard.weglot.com. It is NOT a whole-project replace: the endpoint merges server-side (measured), and the ability sends only the keys with a pending local edit (language_from, languages, excluded_paths, custom_settings), leaving everything else at the cloud value. A key edited BOTH on dashboard.weglot.com and via an ability resolves in favour of the ability. Do not run it for add/delete-translated-url: slugs are already in force on this site and this push cannot carry them. Error codes: weglot_no_api_key (missing private key), weglot_cloud_push_rejected (the cloud answered 2xx but the body explicitly reports failure), weglot_cloud_http_<code> / weglot_cloud_transport (network / upstream failure), weglot_lock_busy (concurrent write in progress). The lock is held for up to ~90 seconds across the two cloud round-trips.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{pushed: bool, overlaid_keys: list<string>, cloud_response: array<array-key, mixed>, cache_busted: bool}|WP_Error
 */
function weglot_push_options_to_cloud(): array|WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }
    $private = wg_api_key_private();
    if ($private === '') {
        return new WP_Error(
            'weglot_no_api_key',
            'Weglot push requires the private (long) API key. Set it via weglot-set-api-key first.',
            ['status' => 400],
        );
    }

    // Lock TTL is set to comfortably exceed the cloud timeout so a slow upstream
    // cannot let a parallel writer steal the lock and double-write.
    return wg_with_options_lock(
        static function () use ($private): array|WP_Error {
            // NO v1/v2 BRANCH HERE, and that is deliberate — checked, because
            // the separate `save_options_to_weglot_v2` (PATCH,
            // application/merge-patch+json) looks at first glance like the
            // endpoint a v2 project should use. It is not the publish path: it
            // serves one admin AJAX handler and sends only `customSettings`
            // (Actions\Admin\Options_Weglot ~:141). The real settings save
            // calls `save_options_to_weglot` — this POST — on BOTH versions.
            // The host difference is handled for us: `wg_api_url()` delegates
            // to `Helper_API::get_api_url()`, which already switches base URL
            // when the onboarding version is 2.
            //
            // (Weglot derives that version from the private key's prefix:
            // anything not starting with `wg_` is v2. Untested empirically —
            // no v2 project was available.)
            //
            // READ-MODIFY-WRITE, and it is parity with upstream: the Weglot
            // admin save takes the GET body (`$option_from_api['result']`) and
            // re-POSTs it — Actions\Admin\Options_Weglot, ~:200-262.
            //
            // `POST /projects/settings` MERGES server-side. Measured on a live
            // throwaway project: a payload that deliberately omitted
            // `translation_engine` left it untouched, and a two-key payload
            // (credential + one field) left all 31 keys byte-identical. So the
            // read below is a safety belt, not a requirement — which is why a
            // failed read no longer blocks publishing.
            $cloud = wg_remote_get(path: '/projects/settings', query: ['api_key' => $private], timeout_seconds: 15);
            $base = $cloud instanceof WP_Error || !wg_refresh_cloud_payload_is_usable($cloud) ? null : $cloud;

            // Overlay ONLY keys explicitly marked as unpublished. Presence in
            // the local blob is not intent: refresh-from-cloud persists the
            // whole cloud payload into that same blob, so overlaying by
            // presence re-published settings nobody had edited — and, since
            // seeding also puts values there, it published them in whatever
            // shape the seed happened to have.
            //
            // When the pre-flight read failed, publish the pending keys alone.
            // Under merge that is exactly equivalent, and it removes a failure
            // mode where a transient hiccup on a read blocked a write that was
            // never at risk.
            $local = wg_options_raw();
            $payload = $base ?? [];
            $overlaid = [];
            foreach (wg_pending_keys() as $key) {
                if (!array_key_exists($key, $local)) {
                    continue;
                }
                $payload[$key] = $local[$key];
                $overlaid[] = $key;
            }
            // Never ship the legacy alias: the write abilities canonicalize to
            // excluded_paths and drop it, and sending both invites the cloud to
            // pick the stale one.
            unset($payload['exclude_urls']);

            // `custom_settings.menu_switcher` is fabricated on the WordPress
            // side: when the cloud returns it empty, Weglot fills it with the
            // local WP menu defaults before caching (Option_Service_Weglot
            // ~:283-287). Overlaying custom_settings wholesale would therefore
            // publish a value invented by this site — not a stale value, an
            // invented one. Keep whatever the cloud itself just returned,
            // including its absence.
            $payload = wg_push_restore_menu_switcher($payload, $base ?? []);

            // The upstream save_options_to_weglot reads api_key_private from the
            // payload itself; mirror that so the cloud sees a consistent value.
            $payload['api_key_private'] = $private;

            $result = wg_remote_post(
                path: '/projects/settings',
                body: $payload,
                query: ['api_key' => $private],
                timeout_seconds: 60,
            );

            if ($result instanceof WP_Error) {
                return $result;
            }

            // Success is OPT-OUT, not opt-in — and this is now measured, not
            // inferred. A real POST to /projects/settings answers with 50 bytes:
            // `{"api_key":"wg_…"}`. No `success`, no `succeeded`, no `error`.
            // The `success` key upstream callers read is constructed in PHP by
            // save_options_to_weglot (`return array('success' => true, 'result'
            // => json_decode($response['body']))`) and never appears in the
            // body — which is why its own consumer reaches INTO `result` for
            // `$response['result']['api_key']`. Demanding `success: true` here
            // reported every successful push as a rejection, and an agent that
            // retries a "failed" push re-issues a settings replace.
            //
            // wg_remote_post has already enforced the two conditions upstream
            // itself treats as success — HTTP 2xx and a decodable JSON object —
            // so only an EXPLICIT failure marker downgrades this to an error.
            $rejection = wg_push_explicit_rejection($result);
            if ($rejection !== null) {
                return new WP_Error(
                    'weglot_cloud_push_rejected',
                    sprintf('Weglot Cloud rejected the push (%s).', $rejection),
                    [
                        'status' => 502,
                        'cause_code' => $rejection,
                        'cloud_response' => wg_redact_credentials_from_payload($result),
                    ],
                );
            }

            // Published: nothing is pending any more.
            wg_pending_keys_clear();

            // Bust the local cache so the next read fetches fresh from cloud.
            //
            // "Next" is literal: this clears the transient, and Weglot consults
            // that transient only after an in-process short circuit on its own
            // `$options_cdn` property (Option_Service_Weglot ~:134-142), which is
            // protected and which Weglot never resets from outside. So a read
            // chained after this push WITHIN the same PHP request still answers
            // from the pre-push snapshot. Measured on a live project: the API and
            // the CDN both carried the new rule (the CDN caught up in ~1s) while
            // an in-request list-exclude-urls still reported the old set; a fresh
            // process read it correctly. Harmless in practice — every ability call
            // is its own request — and deliberately NOT worked around by reaching
            // into a protected property, which would couple us to an internal name
            // for a case that does not arise.
            delete_transient('weglot_cache_cdn');
            delete_transient('weglot_slugs_cache');
            wg_user_info_cache_bust();

            return [
                'pushed' => true,
                'overlaid_keys' => $overlaid,
                'cloud_response' => wg_redact_credentials_from_payload($result),
                'cache_busted' => true,
            ];
        },
        stale_after_seconds: 90,
    );
}

/**
 * Put `custom_settings.menu_switcher` back to exactly what the cloud returned —
 * present with its value, or absent. Everything else in the payload is left
 * alone.
 *
 * @param  array<string, mixed> $payload
 * @param  array<string, mixed> $cloud
 * @return array<string, mixed>
 */
function wg_push_restore_menu_switcher(array $payload, array $cloud): array
{
    if (!is_array($payload['custom_settings'] ?? null)) {
        return $payload;
    }
    /** @var array<string, mixed> $settings */
    $settings = $payload['custom_settings'];

    $cloud_settings = is_array($cloud['custom_settings'] ?? null) ? $cloud['custom_settings'] : [];
    unset($settings['menu_switcher']);
    if (array_key_exists('menu_switcher', $cloud_settings)) {
        $settings['menu_switcher'] = $cloud_settings['menu_switcher'];
    }

    $payload['custom_settings'] = $settings;
    return $payload;
}

/**
 * Detect an EXPLICIT failure marker in a cloud settings response. Returns the
 * diagnostic string to report, or null when nothing in the body says the write
 * failed.
 *
 * Absence of a success marker is NOT failure here — see the caller for why.
 *
 * @param array<string, mixed> $result
 */
function wg_push_explicit_rejection(array $result): ?string
{
    if (($result['success'] ?? null) === false) {
        return 'success=false';
    }

    /** @var mixed $succeeded */
    $succeeded = $result['succeeded'] ?? null;
    if ($succeeded === false || $succeeded !== null && wg_coerce_int_or_null($succeeded) === 0) {
        return 'succeeded=0';
    }

    /** @var mixed $error */
    $error = $result['error'] ?? null;
    if (is_string($error) && trim($error) !== '') {
        return wg_redact_credentials_from_body($error);
    }

    return null;
}
