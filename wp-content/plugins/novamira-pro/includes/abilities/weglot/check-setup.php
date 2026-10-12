<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-check-setup', [
    'label' => __('Check Weglot Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Weglot Translate environment: whether Weglot is active, its version, whether the API key is configured (masked output), the cloud project name and plan, language limits, the source language and the destination-language codes, and whether the plan includes translated URL slugs. Call this first before any other Weglot ability.',
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
            'active' => ['type' => 'boolean'],
            'version' => ['type' => ['string', 'null']],
            'api_key_present' => ['type' => 'boolean'],
            'api_key_masked' => ['type' => ['string', 'null']],
            'private_key_present' => ['type' => 'boolean'],
            'api_key_source' => [
                'type' => ['string', 'null'],
                'enum' => ['constant', 'option', null],
                'description' => 'Where Weglot reads the public key from: "constant" (the WEGLOT_API_KEY PHP constant in the server configuration — read-only, weglot-set-api-key cannot change it), "option" (the database, managed by weglot-set-api-key), or null when no key is set.',
            ],
            'private_key_source' => [
                'type' => ['string', 'null'],
                'enum' => ['constant', 'option', null],
                'description' => 'Same as api_key_source, for the private key and the WEGLOT_API_KEY_PRIVATE constant.',
            ],
            'cloud_reachable' => ['type' => 'boolean'],
            'project_name' => ['type' => ['string', 'null']],
            'plan_id' => ['type' => ['integer', 'null']],
            'plan_name' => ['type' => ['string', 'null']],
            'language_limit' => ['type' => ['integer', 'null']],
            'source_language' => ['type' => ['string', 'null']],
            'destination_languages' => ['type' => 'array', 'items' => ['type' => 'string']],
            'supports_slugs' => ['type' => 'boolean'],
            'config_source' => [
                'type' => 'string',
                'enum' => ['weglot', 'local_fallback'],
                'description' => 'Where source_language / destination_languages were read from. "weglot" is the configuration actually in force; "local_fallback" means Weglot\'s resolver was unavailable and only the local option could be read, which on a cloud-driven install holds no languages — treat those two fields as unknown, not as empty.',
            ],
            'cloud_state' => [
                'type' => 'string',
                'enum' => ['ok', 'no_credential', 'rejected', 'unreachable'],
                'description' => 'Distinguishes "credential missing", "cloud rejected the key", and "transient cloud failure" — agents pick a different remediation for each.',
            ],
            'last_cloud_error' => [
                'type' => ['object', 'null'],
                'properties' => [
                    'code' => ['type' => 'string'],
                    'message' => ['type' => 'string'],
                ],
                'required' => ['code', 'message'],
                'additionalProperties' => false,
            ],
        ],
        'required' => [
            'active',
            'version',
            'api_key_present',
            'api_key_masked',
            'private_key_present',
            'api_key_source',
            'private_key_source',
            'cloud_reachable',
            'cloud_state',
            'last_cloud_error',
            'project_name',
            'plan_id',
            'plan_name',
            'language_limit',
            'source_language',
            'destination_languages',
            'supports_slugs',
            'config_source',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this first. (If this ability is missing from your tool list, Weglot is not active.) api_key_present indicates the short (public) key is set; private_key_present indicates the long key needed for cloud writes. api_key_source / private_key_source say where Weglot reads each key: "constant" means the host sets it in its server configuration, where only the host can change it. cloud_state is the authoritative signal: "ok" means the cloud accepted the key; "no_credential" means no key is set yet; "rejected" means the key is invalid (rotate it); "unreachable" means transient network/server failure (retry later). last_cloud_error carries the upstream diagnostic when cloud_state != "ok". supports_slugs is a HINT derived from the plan id, not an authority: it says whether the plan of the project includes translated URL slugs (Pro tier and above). Weglot gates nothing by plan in its own code — the server decides, and answers 403 on plans without the feature. source_language and destination_languages report the configuration actually in force, resolved through Weglot — an install where the admin only pasted the API key still reports its real languages. If config_source is "local_fallback", Weglot\'s resolver could not be reached: read those two fields as unknown rather than as "nothing configured".',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     active: bool,
 *     version: string|null,
 *     api_key_present: bool,
 *     api_key_masked: string|null,
 *     private_key_present: bool,
 *     api_key_source: 'constant'|'option'|null,
 *     private_key_source: 'constant'|'option'|null,
 *     cloud_reachable: bool,
 *     cloud_state: 'ok'|'no_credential'|'rejected'|'unreachable',
 *     last_cloud_error: array{code: string, message: string}|null,
 *     project_name: string|null,
 *     plan_id: int|null,
 *     plan_name: string|null,
 *     language_limit: int|null,
 *     source_language: string|null,
 *     destination_languages: list<string>,
 *     supports_slugs: bool,
 *     config_source: 'weglot'|'local_fallback',
 * }
 */
function weglot_check_setup(): array
{
    $detect = wg_detect_setup();
    if (!$detect['active']) {
        return wg_check_setup_inactive();
    }

    $credentials = wg_check_setup_credentials();
    $local = wg_check_setup_local($credentials);
    $cloud = wg_check_setup_cloud();

    return $local + $cloud + ['active' => true, 'version' => $detect['version']];
}

/**
 * Default check-setup payload returned when Weglot is not active.
 *
 * @return array{active: false, version: null, api_key_present: false, api_key_masked: null, private_key_present: false, api_key_source: null, private_key_source: null, cloud_reachable: false, cloud_state: 'no_credential', last_cloud_error: null, project_name: null, plan_id: null, plan_name: null, language_limit: null, source_language: null, destination_languages: array{}, supports_slugs: false, config_source: 'local_fallback'}
 */
function wg_check_setup_inactive(): array
{
    return [
        'active' => false,
        'version' => null,
        'api_key_present' => false,
        'api_key_masked' => null,
        'private_key_present' => false,
        'api_key_source' => null,
        'private_key_source' => null,
        'cloud_reachable' => false,
        'cloud_state' => 'no_credential',
        'last_cloud_error' => null,
        'project_name' => null,
        'plan_id' => null,
        'plan_name' => null,
        'language_limit' => null,
        'source_language' => null,
        'destination_languages' => [],
        'supports_slugs' => false,
        'config_source' => 'local_fallback',
    ];
}

/**
 * Read both credentials and derive their presence + the masked display value.
 *
 * @return array{public: string, private: string, present: bool, masked: string|null, public_source: 'constant'|'option'|null, private_source: 'constant'|'option'|null}
 */
function wg_check_setup_credentials(): array
{
    $public_credential = wg_credential('public');
    $private_credential = wg_credential('private');
    $public = $public_credential['value'];
    $private = $private_credential['value'];
    // api_key_present is documented as "the short (public) key is set" and the
    // masked display pairs with it; the long key is reported separately via
    // private_key_present. Keep this honest so an agent is not told the public
    // key exists when only the private one does.
    $present = $public !== '';
    $masked = $public !== '' ? wg_mask_api_key($public) : null;
    return [
        'public' => $public,
        'private' => $private,
        'present' => $present,
        'masked' => $masked,
        'public_source' => wg_credential_source($public_credential),
        'private_source' => wg_credential_source($private_credential),
    ];
}

/**
 * Where a resolved credential comes from, or null when there is none.
 *
 * @param  array{value: string, from_constant: bool} $credential
 * @return 'constant'|'option'|null
 */
function wg_credential_source(array $credential): ?string
{
    if ($credential['value'] === '') {
        return null;
    }
    return $credential['from_constant'] ? 'constant' : 'option';
}

/**
 * Local-only portion of the check-setup payload (credentials + persisted options).
 *
 * @param  array{public: string, private: string, present: bool, masked: string|null, public_source: 'constant'|'option'|null, private_source: 'constant'|'option'|null} $credentials
 * @return array{api_key_present: bool, api_key_masked: string|null, private_key_present: bool, api_key_source: 'constant'|'option'|null, private_key_source: 'constant'|'option'|null, source_language: string|null, destination_languages: list<string>, config_source: 'weglot'|'local_fallback'}
 */
function wg_check_setup_local(array $credentials): array
{
    // Resolved, not raw: the local option holds no languages on a cloud-driven
    // install, which is the default state after pasting an API key. Reading it
    // reported "no source language, no destinations" on sites that were
    // translating fine. See wg_options_resolution().
    $resolution = wg_options_resolution();
    $options = $resolution['options'];
    /** @var mixed $source_raw */
    $source_raw = $options['language_from'] ?? null;

    return [
        'api_key_present' => $credentials['present'],
        'api_key_masked' => $credentials['masked'],
        'private_key_present' => $credentials['private'] !== '',
        'api_key_source' => $credentials['public_source'],
        'private_key_source' => $credentials['private_source'],
        'source_language' => wg_coerce_string_or_null($source_raw),
        'destination_languages' => wg_destination_codes_from_options($options),
        'config_source' => $resolution['source'],
    ];
}

/**
 * Cloud-derived portion of the check-setup payload (cached user-info digest).
 *
 * @return array{cloud_reachable: bool, cloud_state: 'ok'|'no_credential'|'rejected'|'unreachable', last_cloud_error: array{code: string, message: string}|null, project_name: string|null, plan_id: int|null, plan_name: string|null, language_limit: int|null, supports_slugs: bool}
 */
function wg_check_setup_cloud(): array
{
    $status = wg_user_info_status();
    $info = $status['answer'];

    /** @var mixed $project_name_raw */
    $project_name_raw = $info['project_name'] ?? $info['name'] ?? null;
    $plan_id = wg_coerce_int_or_null($info['plan_id'] ?? null);

    $last_error = null;
    if ($status['error_code'] !== null && $status['error_message'] !== null) {
        $last_error = ['code' => $status['error_code'], 'message' => $status['error_message']];
    }

    return [
        'cloud_reachable' => $status['state'] === 'ok',
        'cloud_state' => $status['state'],
        'last_cloud_error' => $last_error,
        'project_name' => wg_coerce_string_or_null($project_name_raw),
        'plan_id' => $plan_id,
        'plan_name' => wg_plan_name_from_info(is_array($info) ? $info : [], $plan_id),
        'language_limit' => wg_language_limit_from_info(is_array($info) ? $info : [], $plan_id),
        'supports_slugs' => wg_plan_supports(feature: 'slugs'),
    ];
}
