<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-get-settings', [
    'label' => __('Get Weglot Settings', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Weglot settings actually in force, scoped to a section: "languages" (source + destination + auto-switch), "button" (language-switcher style: dropdown, flags, name visibility, flag type), "advanced" (translate-search, translate-amp, translate-email, custom CSS, and which languages are kept private), or "all" (combined). Resolved the way Weglot itself resolves its configuration, so cloud-managed values (languages, exclusions, engine) are included even on an install where the admin only pasted the API key. API keys are never returned. Use weglot-get-status for project/plan/quota data.',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'section' => [
                'type' => 'string',
                'enum' => ['languages', 'button', 'advanced', 'all'],
                'default' => 'all',
                'description' => 'Which configuration cluster to return. Default "all" returns every cluster nested under its section key.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'section' => ['type' => 'string'],
            'config_source' => [
                'type' => 'string',
                'enum' => ['weglot', 'local_fallback'],
                'description' => 'Where the values were read from. "weglot" is the configuration in force; "local_fallback" means Weglot\'s resolver was unavailable and only the local option could be read — cloud-managed fields (languages, auto-switch) are then unknown rather than empty.',
            ],
            'pending_publish' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Settings keys carrying a local edit that has NOT been published yet: the values below reflect them, but the live site does not until weglot-push-options-to-cloud runs. Empty means everything shown is live.',
            ],
            'languages' => [
                'type' => 'object',
                'properties' => [
                    'source_language' => ['type' => ['string', 'null']],
                    'destination_languages' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'auto_switch' => ['type' => 'boolean'],
                    'auto_switch_fallback' => ['type' => ['string', 'null']],
                ],
                'required' => ['source_language', 'destination_languages', 'auto_switch', 'auto_switch_fallback'],
                'additionalProperties' => false,
            ],
            'button' => [
                'type' => 'object',
                'properties' => [
                    'full_name' => ['type' => 'boolean'],
                    'with_name' => ['type' => 'boolean'],
                    'is_dropdown' => ['type' => 'boolean'],
                    'with_flags' => ['type' => 'boolean'],
                    'flag_type' => ['type' => ['string', 'null']],
                    'custom_css' => ['type' => 'string'],
                ],
                'required' => ['full_name', 'with_name', 'is_dropdown', 'with_flags', 'flag_type', 'custom_css'],
                'additionalProperties' => false,
            ],
            'advanced' => [
                'type' => 'object',
                'properties' => [
                    'translate_email' => ['type' => 'boolean'],
                    'translate_amp' => ['type' => 'boolean'],
                    'translate_search' => ['type' => 'boolean'],
                    'rtl_ltr_style' => ['type' => 'string'],
                    'flag_css' => ['type' => 'string'],
                    'private_languages' => [
                        'type' => 'array',
                        'items' => ['type' => 'string'],
                        'description' => 'Destination languages kept private (visible to logged-in admins only). Weglot has no single private-mode switch: privacy is per language and is the negation of that language being enabled.',
                    ],
                ],
                'required' => [
                    'translate_email',
                    'translate_amp',
                    'translate_search',
                    'rtl_ltr_style',
                    'flag_css',
                    'private_languages',
                ],
                'additionalProperties' => false,
            ],
        ],
        'required' => ['section', 'config_source', 'pending_publish'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reports the configuration in force, resolved through Weglot (cloud values included). Use section="languages" before adding/removing destinations, "button" before edit-button-settings, "advanced" for niche toggles. Pending edits made by the settings write abilities ARE reflected here, but the live site does not serve them until weglot-push-options-to-cloud runs — pending_publish lists exactly which keys are in that state. Check config_source: "local_fallback" means the resolver was unreachable and cloud-managed fields are unknown, not empty. API keys are intentionally never returned — call check-setup to confirm key presence.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{section?: string} $input
 * @return array{
 *     section: string,
 *     config_source: 'weglot'|'local_fallback',
 *     pending_publish: list<string>,
 *     languages?: array{
 *         source_language: string|null,
 *         destination_languages: list<string>,
 *         auto_switch: bool,
 *         auto_switch_fallback: string|null,
 *     },
 *     button?: array{
 *         full_name: bool,
 *         with_name: bool,
 *         is_dropdown: bool,
 *         with_flags: bool,
 *         flag_type: string|null,
 *         custom_css: string,
 *     },
 *     advanced?: array{
 *         translate_email: bool,
 *         translate_amp: bool,
 *         translate_search: bool,
 *         rtl_ltr_style: string,
 *         flag_css: string,
 *         private_languages: list<string>,
 *     },
 * }
 */
function weglot_get_settings(array $input): array
{
    $section = is_string($input['section'] ?? null) ? $input['section'] : 'all';
    // Resolved, not raw — the local option carries none of the cloud-managed
    // settings on a default install. See wg_options_resolution().
    $resolution = wg_options_resolution();
    $options = $resolution['options'];

    $out = [
        'section' => $section,
        'config_source' => $resolution['source'],
        'pending_publish' => $resolution['pending_keys'],
    ];

    if ($section === 'languages' || $section === 'all') {
        $out['languages'] = wg_section_languages($options);
    }
    if ($section === 'button' || $section === 'all') {
        $out['button'] = wg_section_button($options);
    }
    if ($section === 'advanced' || $section === 'all') {
        $out['advanced'] = wg_section_advanced($options);
    }

    return $out;
}

/**
 * @param  array<string, mixed> $options
 * @return array{source_language: string|null, destination_languages: list<string>, auto_switch: bool, auto_switch_fallback: string|null}
 */
function wg_section_languages(array $options): array
{
    /** @var mixed $source_raw */
    $source_raw = $options['language_from'] ?? null;
    /** @var mixed $auto_raw */
    $auto_raw = $options['auto_switch'] ?? false;
    /** @var mixed $fallback_raw */
    $fallback_raw = $options['auto_switch_fallback'] ?? null;

    return [
        'source_language' => is_string($source_raw) && $source_raw !== '' ? $source_raw : null,
        'destination_languages' => wg_destination_codes_from_options($options),
        'auto_switch' => wg_coerce_bool($auto_raw),
        'auto_switch_fallback' => is_string($fallback_raw) && $fallback_raw !== '' ? $fallback_raw : null,
    ];
}

/**
 * @param  array<string, mixed> $options
 * @return array{full_name: bool, with_name: bool, is_dropdown: bool, with_flags: bool, flag_type: string|null, custom_css: string}
 */
function wg_section_button(array $options): array
{
    /** @var mixed $custom_settings */
    $custom_settings = $options['custom_settings'] ?? [];
    /** @var array<string, mixed> $button */
    $button = is_array($custom_settings) && is_array($custom_settings['button_style'] ?? null)
        ? $custom_settings['button_style']
        : [];

    /** @var mixed $flag_type_raw */
    $flag_type_raw = $button['flag_type'] ?? null;
    /** @var mixed $custom_css_raw */
    $custom_css_raw = $button['custom_css'] ?? '';

    return [
        'full_name' => wg_coerce_bool_true_default($button['full_name'] ?? null),
        'with_name' => wg_coerce_bool_true_default($button['with_name'] ?? null),
        'is_dropdown' => wg_coerce_bool_true_default($button['is_dropdown'] ?? null),
        'with_flags' => wg_coerce_bool_true_default($button['with_flags'] ?? null),
        'flag_type' => is_string($flag_type_raw) || is_int($flag_type_raw) ? (string) $flag_type_raw : null,
        'custom_css' => is_string($custom_css_raw) ? $custom_css_raw : '',
    ];
}

/**
 * @param  array<string, mixed> $options
 * @return array{translate_email: bool, translate_amp: bool, translate_search: bool, rtl_ltr_style: string, flag_css: string, private_languages: list<string>}
 */
function wg_section_advanced(array $options): array
{
    /** @var array<string, mixed> $custom */
    $custom = is_array($options['custom_settings'] ?? null) ? $options['custom_settings'] : [];
    /** @var mixed $rtl_raw */
    $rtl_raw = $custom['rtl_ltr_style'] ?? '';
    // `flag_css` is TOP-LEVEL, not under custom_settings: that is where
    // Option_Service_Weglot::get_flag_css() reads it from
    // (`get_option('flag_css')`). Reading the nested path returned '' on every
    // install, whatever the user had configured.
    /** @var mixed $flag_css_raw */
    $flag_css_raw = $options['flag_css'] ?? $custom['flag_css'] ?? '';

    return [
        'translate_email' => wg_coerce_bool($custom['translate_email'] ?? null),
        'translate_amp' => wg_coerce_bool($custom['translate_amp'] ?? null),
        'translate_search' => wg_coerce_bool($custom['translate_search'] ?? null),
        'rtl_ltr_style' => is_string($rtl_raw) ? $rtl_raw : '',
        'flag_css' => is_string($flag_css_raw) ? $flag_css_raw : '',
        'private_languages' => wg_private_languages_from_options($options),
    ];
}
