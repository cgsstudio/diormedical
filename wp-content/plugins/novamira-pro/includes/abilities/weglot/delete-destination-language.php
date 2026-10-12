<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-delete-destination-language', [
    'label' => __('Delete Weglot Destination Language', domain: 'novamira-pro'),
    'description' => __(
        'Removes a destination language from the Weglot configuration. The local-side translations cache is dropped immediately; cloud-side translations stay in the Weglot project until the language is re-added or the project is reset.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'language' => [
                'type' => 'string',
                'minLength' => 2,
                'description' => 'ISO destination-language code to remove.',
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
        ],
        'required' => ['persisted', 'language', 'destination_languages'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_delete_destination_language',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns weglot_unknown_language when the code is not currently configured. Use weglot-list-languages or weglot-get-settings (section=languages) to discover the active destination codes.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param  array{language: string} $input
 * @return array{persisted: bool, language: string, destination_languages: list<string>}|WP_Error
 */
function weglot_delete_destination_language(array $input): array|WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }

    $code = strtolower(trim($input['language']));
    if ($code === '') {
        return wg_invalid_input('language must be a non-empty string.');
    }

    return wg_write_options_delta(['languages'], static function (array $options) use ($code): array|WP_Error {
        $existing_codes = wg_destination_codes_from_options($options);
        if (!in_array($code, $existing_codes, strict: true)) {
            return new WP_Error(
                'weglot_unknown_language',
                sprintf('Destination language "%s" is not currently configured.', $code),
                ['status' => 404],
            );
        }

        $kept = [];
        /** @var mixed $entry */
        foreach (is_array($options['languages'] ?? null) ? $options['languages'] : [] as $entry) {
            // wg_destination_codes_from_options canonicalizes stored codes to
            // lowercase for the membership check above; do the same here so
            // entries persisted as e.g. `"DE"` are actually removed.
            $stored = wg_extract_language_to($entry);
            if ($stored !== null && strtolower($stored) === $code) {
                continue;
            }
            $kept[] = $entry;
        }
        $options['languages'] = $kept;

        return [
            $options,
            [
                'persisted' => true,
                'language' => $code,
                'destination_languages' => wg_destination_codes_from_options($options),
            ],
        ];
    });
}
