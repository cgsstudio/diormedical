<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-add-destination-language', [
    'label' => __('Add Weglot Destination Language', domain: 'novamira-pro'),
    'description' => __(
        'Adds an ISO language code to the Weglot destination-language list (translations Weglot generates TO). The code must exist in the SDK catalog, must NOT equal the source language, and must NOT already be configured. The optional `custom_name` overrides the language switcher display name. `enabled` (default true) is the only visibility switch Weglot has: set it to false to make the language PRIVATE — served to logged-in admins as a preview, hidden from visitors — which is what the Weglot dashboard calls a private language.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'language' => [
                'type' => 'string',
                'minLength' => 2,
                'description' => 'ISO 639-1 destination code (e.g. "fr", "es", "pt-br").',
            ],
            'custom_name' => [
                'type' => 'string',
                'description' => 'Optional display name override for the language switcher.',
            ],
            'enabled' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'Whether the language is served to visitors. Default true. False = private: admins previewing the site still see it, visitors do not. Weglot derives its private-language state from this single flag, there is no separate one.',
            ],
        ],
        'required' => ['language'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'persisted' => ['type' => 'boolean'],
            'language' => ['type' => 'string'],
            'destination_languages' => ['type' => 'array', 'items' => ['type' => 'string']],
            'over_plan_limit' => ['type' => 'boolean'],
            'plan_language_limit' => ['type' => ['integer', 'null']],
        ],
        'required' => ['persisted', 'language', 'destination_languages', 'over_plan_limit', 'plan_language_limit'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_add_destination_language',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns over_plan_limit:true when the new total exceeds the plan-allowed language_limit — the language is still persisted, but Weglot Cloud may refuse to serve it. Use weglot-check-setup to see the current plan_language_limit.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param  array{language: string, custom_name?: string, enabled?: bool} $input
 * @return array{persisted: bool, language: string, destination_languages: list<string>, over_plan_limit: bool, plan_language_limit: int|null}|WP_Error
 */
function weglot_add_destination_language(array $input): array|WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }

    $code = strtolower(trim($input['language']));
    if ($code === '') {
        return wg_invalid_input('language must be a non-empty string.');
    }
    if (!wg_language_code_supported($code)) {
        return wg_invalid_input(sprintf('Unknown language code "%s".', $code));
    }

    // NULL when not supplied, never the empty string. Weglot resolves the
    // switcher label with `$d['custom_name'] ?? $language_data->getEnglishName()`
    // (Language_Service_Weglot ~:145), and `''` is not null — it wins the
    // coalesce and the language ends up displayed with a blank label. The cloud
    // itself stores null here.
    $custom_name_raw = isset($input['custom_name']) ? trim($input['custom_name']) : '';
    $custom_name = $custom_name_raw === '' ? null : $custom_name_raw;
    $enabled = !isset($input['enabled']) || wg_coerce_bool($input['enabled']);

    // `language_from` is owned too, even though this ability never writes it:
    // the destination≠source check below reads it, and a key that is not owned
    // is neither refreshed from the live configuration nor drift-checked. On a
    // cloud-driven install the local blob has no `language_from` at all, so the
    // check silently passed for every code — you could add Italian as a
    // destination of an Italian site.
    return wg_write_options_delta(['languages', 'language_from'], static function (array $options) use (
        $code,
        $custom_name,
        $enabled,
    ): array|WP_Error {
        $source = wg_coerce_string_or_null($options['language_from'] ?? null);
        if ($source === $code) {
            return wg_invalid_input(sprintf(
                'Destination language "%s" matches the source language. Pick a different code.',
                $code,
            ));
        }

        $existing_codes = wg_destination_codes_from_options($options);
        if (in_array($code, $existing_codes, strict: true)) {
            return wg_invalid_input(sprintf('Destination language "%s" is already configured.', $code));
        }

        $languages = is_array($options['languages'] ?? null) ? $options['languages'] : [];
        $languages[] = [
            'language_to' => $code,
            'custom_name' => $custom_name,
            'enabled' => $enabled,
        ];
        $options['languages'] = $languages;

        $info = wg_user_info_cached();
        $plan_id = $info !== null ? wg_coerce_int_or_null($info['plan_id'] ?? null) : null;
        // From the cloud answer already in hand (`languages_limit`), not from
        // the hardcoded plan table — the same correction get-status and
        // check-setup already carry.
        $plan_limit = wg_language_limit_from_info(is_array($info) ? $info : [], $plan_id);
        $codes = wg_destination_codes_from_options($options);

        return [
            $options,
            [
                'persisted' => true,
                'language' => $code,
                'destination_languages' => $codes,
                'over_plan_limit' => $plan_limit !== null && count($codes) > $plan_limit,
                'plan_language_limit' => $plan_limit,
            ],
        ];
    });
}
