<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-set-original-language', [
    'label' => __('Set Weglot Original (Source) Language', domain: 'novamira-pro'),
    'description' => __(
        'Sets the source language Weglot translates FROM (stored as `language_from` in the v3 options). DESTRUCTIVE on a configured project: Weglot Cloud invalidates every existing translation when the source language changes, so the agent should confirm with the user before invoking this on a non-empty project. The language code must exist in the bundled SDK catalog (use weglot-list-languages to validate); a destination language matching the new source is automatically removed to keep the configuration consistent.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'language' => [
                'type' => 'string',
                'minLength' => 2,
                'description' => 'ISO 639-1 source-language code (e.g. "en", "it", "pt-br"). Must exist in the SDK catalog.',
            ],
        ],
        'required' => ['language'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'persisted' => ['type' => 'boolean'],
            'previous_source_language' => ['type' => ['string', 'null']],
            'source_language' => ['type' => 'string'],
            'destination_languages' => ['type' => 'array', 'items' => ['type' => 'string']],
            'removed_destination' => ['type' => ['string', 'null']],
        ],
        'required' => [
            'persisted',
            'previous_source_language',
            'source_language',
            'destination_languages',
            'removed_destination',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_set_original_language',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Destructive on configured projects (cloud drops existing translations). If `language` equals one of the configured destinations, that destination is silently removed (Weglot does not allow source == destination). Returns previous_source_language for rollback. NOT idempotent: re-applying the same source may re-trigger upstream invalidation even when the locally persisted value is unchanged.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param  array{language: string} $input
 * @return array{persisted: bool, previous_source_language: string|null, source_language: string, destination_languages: list<string>, removed_destination: string|null}|WP_Error
 */
function weglot_set_original_language(array $input): array|WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }

    $new_source = strtolower(trim($input['language']));
    if ($new_source === '') {
        return wg_invalid_input('language must be a non-empty string.');
    }
    if (!wg_language_code_supported($new_source)) {
        return wg_invalid_input(sprintf(
            'Unknown language code "%s". Use weglot-list-languages to see supported codes.',
            $new_source,
        ));
    }

    return wg_write_options_delta(['language_from', 'languages'], static function (array $options) use (
        $new_source,
    ): array {
        $previous = wg_coerce_string_or_null($options['language_from'] ?? null);

        $options['language_from'] = $new_source;

        // Remove destination matching the new source (Weglot rejects self-translation).
        $removed = null;
        $kept = [];
        /** @var mixed $entry */
        foreach (is_array($options['languages'] ?? null) ? $options['languages'] : [] as $entry) {
            $code = wg_extract_language_to($entry);
            if ($code === $new_source) {
                $removed = $code;
                continue;
            }
            $kept[] = $entry;
        }
        $options['languages'] = $kept;

        return [
            $options,
            [
                'persisted' => true,
                'previous_source_language' => $previous,
                'source_language' => $new_source,
                'destination_languages' => wg_destination_codes_from_options($options),
                'removed_destination' => $removed,
            ],
        ];
    });
}
