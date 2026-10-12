<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-set-api-key', [
    'label' => __('Set Weglot API Key', domain: 'novamira-pro'),
    'description' => __(
        'Sets (or rotates) the Weglot API credentials. `public_key` (short "wg_..." token) is required unless the host supplies it through the WEGLOT_API_KEY PHP constant; when supplied it is ALWAYS validated against /projects/owner before being persisted; if the cloud rejects it (weglot_cloud_key_invalid) or is unreachable (weglot_cloud_unreachable), nothing is saved and the current key is preserved. The optional `private_key` is validated separately when supplied and reports weglot_private_key_invalid on failure. Both credentials carry the same "wg_" prefix on current projects — tell them apart by role, not by prefix: the public one is what the settings page shows as the site key, the private one is the longer token the dashboard issues for API writes. Required for cloud writes (glossary, custom URL slugs); omit the field to keep the current private key, or pass an empty string to CLEAR it. Returns the project name and plan reported by the richer probe on success. When the host supplies a credential through a PHP constant (WEGLOT_API_KEY for the public key, WEGLOT_API_KEY_PRIVATE for the private one — see check-setup api_key_source / private_key_source), Weglot ignores the stored value, so a write to that credential is refused with weglot_credential_from_constant and nothing is saved. The other credential stays writable: with WEGLOT_API_KEY defined, omit public_key and pass only private_key to set or clear the stored private key.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'public_key' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Short / public Weglot API key (typically starts with "wg_"). Required, except when check-setup reports api_key_source="constant": then omit it (sending it is refused).',
            ],
            'private_key' => [
                'type' => 'string',
                'description' => 'Long / private Weglot API key. Required for cloud writes. Do NOT assume a distinguishing prefix: on current projects both keys start with "wg_". The prefix is not cosmetic — Weglot derives the API version from it (anything not starting with "wg_" is treated as v2 and resolved against a different API host), so passing a mislabelled key changes more than authentication. Omit to keep the current value.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'persisted' => ['type' => 'boolean'],
            'public_key_masked' => [
                'type' => 'string',
                'description' => 'The public key now in force, masked — the constant-held one when the call left the public key alone.',
            ],
            'private_key_present' => ['type' => 'boolean'],
            'project_name' => ['type' => ['string', 'null']],
            'plan_id' => ['type' => ['integer', 'null']],
            'plan_name' => ['type' => ['string', 'null']],
        ],
        'required' => ['persisted', 'public_key_masked', 'private_key_present', 'project_name', 'plan_id', 'plan_name'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_set_api_key',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Validates the key with the cloud BEFORE persisting — an invalid key returns weglot_cloud_key_invalid and the previous key is kept. Use weglot-get-status afterwards if you need quota/words data; check-setup reflects the new credential immediately after this call. weglot_credential_from_constant means the host sets that key in its server configuration: tell the user to change it there, do not retry.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param  array{public_key?: string, private_key?: string} $input
 * @return array{persisted: bool, public_key_masked: string, private_key_present: bool, project_name: string|null, plan_id: int|null, plan_name: string|null}|WP_Error
 */
function weglot_set_api_key(array $input): array|WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }

    // Refuse before probing or persisting anything, so the call stays atomic.
    $plan = wg_set_api_key_plan($input);
    if ($plan instanceof WP_Error) {
        return $plan;
    }
    ['public' => $public, 'private_present' => $private_present, 'private' => $private] = $plan;

    // Validate every credential the call writes, each independently. The
    // private probe is preferred for the reported metadata: it carries plan /
    // quota / project data the public credential may not surface.
    $status = null;
    if ($public !== null) {
        $status = wg_probe_credential_or_error($public, field: 'public_key', invalid_code: 'weglot_cloud_key_invalid');
        if ($status instanceof WP_Error) {
            return $status;
        }
    }
    if ($private !== '') {
        $status = wg_probe_credential_or_error(
            $private,
            field: 'private_key',
            invalid_code: 'weglot_private_key_invalid',
        );
        if ($status instanceof WP_Error) {
            return $status;
        }
    }

    return wg_with_options_lock(static function () use ($public, $private, $private_present, $status): array {
        if ($public !== null) {
            update_option(option: wg_option_name_public_credential(), value: $public, autoload: false);
        }
        if ($private_present) {
            $private === ''
                ? delete_option(wg_option_name_private_credential())
                : update_option(option: wg_option_name_private_credential(), value: $private, autoload: false);
        }

        // Bust the per-request cache so check-setup / get-status reflect the
        // new credential immediately (cache is keyed by credential hash; this
        // matters when only the private key is changed and the cache hashed
        // its fallback off the public key, or when the same key was previously
        // observed as rejected and the cloud has since recovered).
        wg_user_info_cache_bust();

        // And the two Weglot caches, which hold PROJECT data under a global
        // key: `weglot_cache_cdn` is read (Option_Service_Weglot ~:147) before
        // the credential is even consulted to build the request URL, so a key
        // that now points at a different project would keep resolving the old
        // project's settings for the full 300s TTL — reported with
        // config_source "weglot", i.e. at full confidence. `weglot_slugs_cache`
        // is the same problem for translated slugs and never expires on its
        // own (cache duration 0). Clearing the private key changes the resolved
        // project exactly as replacing it does, so this runs on both branches.
        delete_transient('weglot_cache_cdn');
        delete_transient('weglot_slugs_cache');

        $info = $status['answer'] ?? [];
        $plan_id = wg_coerce_int_or_null($info['plan_id'] ?? null);
        /** @var mixed $project_name_raw */
        $project_name_raw = $info['project_name'] ?? $info['name'] ?? null;

        return [
            'persisted' => true,
            // The key in force after the write: the one just stored, or the
            // constant-held one when the call left the public key alone.
            'public_key_masked' => wg_mask_api_key(wg_api_key()),
            'private_key_present' => wg_api_key_private() !== '',
            'project_name' => wg_coerce_string_or_null($project_name_raw),
            'plan_id' => $plan_id,
            // From the cloud answer already in hand, not from the id table:
            // that table says "starter_free" where the cloud says "Free".
            'plan_name' => wg_plan_name_from_info($info, $plan_id),
        ];
    });
}

/**
 * Decide which credentials the call writes, or the error that refuses it.
 *
 * `public_key` null means "leave the public key alone" — allowed only when a
 * constant supplies it, because otherwise the public key is what every call
 * validates and sets. `private_key` is tri-state: omitted (keep), empty string
 * (clear), non-empty (set).
 *
 * A credential Weglot reads from a PHP constant cannot be written: Weglot
 * ignores the stored option while the constant is defined, so saving it would
 * report success for a key that never takes effect. Only a write to THAT
 * credential is refused; the other one stays writable.
 *
 * @param  array{public_key?: string, private_key?: string} $input
 * @return array{public: string|null, private_present: bool, private: string}|WP_Error
 */
function wg_set_api_key_plan(array $input): array|WP_Error
{
    $public_constant = wg_api_key_from_constant();
    $private_present = array_key_exists('private_key', $input);

    if (array_key_exists('public_key', $input) && $public_constant) {
        return wg_credential_from_constant_error(field: 'public API key', constant: 'WEGLOT_API_KEY');
    }
    if ($private_present && wg_api_key_private_from_constant()) {
        return wg_credential_from_constant_error(field: 'private API key', constant: 'WEGLOT_API_KEY_PRIVATE');
    }

    $public = array_key_exists('public_key', $input) ? trim($input['public_key']) : null;
    if ($public === '') {
        return wg_invalid_input('public_key must be a non-empty string.');
    }
    if ($public === null && !$public_constant) {
        return wg_invalid_input('public_key is required unless the WEGLOT_API_KEY PHP constant supplies it.');
    }
    if ($public === null && !$private_present) {
        return wg_invalid_input(
            'Nothing to set: the public key comes from the WEGLOT_API_KEY PHP constant. Pass private_key to set or clear the stored private key.',
        );
    }

    return [
        'public' => $public,
        'private_present' => $private_present,
        'private' => $private_present ? trim($input['private_key'] ?? '') : '',
    ];
}

/**
 * Probe one credential against the cloud, returning its status on success or
 * the error the caller should hand back.
 *
 * Both credentials go through the same three outcomes, so they share one path:
 * unreachable is a 503 (nothing persisted, retry later), a rejection is a 401
 * under the code the caller names, and anything else is the usable status.
 * Probes use the longer 8s timeout — this is a user-explicit validation, not
 * an opportunistic read.
 *
 * @param  string $field the input field name, for the message
 * @param  string $invalid_code the error code to use when the cloud rejects it
 * @return array{state: 'ok'|'rejected'|'unreachable', answer: array<string, mixed>|null, error_code: string|null, error_message: string|null}|WP_Error
 */
function wg_probe_credential_or_error(string $credential, string $field, string $invalid_code): array|WP_Error
{
    $status = wg_validate_credential_via_cloud($credential);
    if ($status['state'] === 'unreachable') {
        return new WP_Error(
            'weglot_cloud_unreachable',
            sprintf(
                'Could not validate the %s — Weglot Cloud is currently unreachable. The previous credential is preserved; retry shortly.',
                $field,
            ),
            [
                'status' => 503,
                'cause_code' => $status['error_code'],
                'cause_message' => $status['error_message'],
            ],
        );
    }
    if ($status['state'] !== 'ok') {
        return new WP_Error($invalid_code, sprintf('Weglot Cloud rejected the supplied %s.', $field), [
            'status' => 401,
            'cause_code' => $status['error_code'],
            'cause_message' => $status['error_message'],
        ]);
    }
    return $status;
}
