<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\AIOSEO;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/aioseo-get-settings', [
    'label' => __('Get AIOSEO Settings', domain: 'novamira-pro'),
    'description' => __(
        'Reads a curated snapshot of the site-wide All in One SEO settings. `scope` selects the block(s) (default "all"): "general" = the title separator and the homepage title & meta-description templates; "titles" = the per-post-type and per-taxonomy title & description templates. Template strings use AIOSEO smart tags like #post_title, #separator_sa, #site_title. To CHANGE a setting use novamira/execute-php and write through the aioseo()->options object then ->save(true) (e.g. aioseo()->options->searchAppearance->global->siteTitle = ...). Do NOT raw update_option(\'aioseo_options\') for title/description templates: those fields are "localized" and AIOSEO renders (and this ability reads) from a separate localized layer the raw write does not touch, so the edit would be silently lost. This ability reveals the keys and current values.',
        domain: 'novamira-pro',
    ),
    'category' => 'aioseo',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'scope' => [
                'type' => 'string',
                'enum' => ['all', 'general', 'titles'],
                'default' => 'all',
                'description' => 'Which settings block to return.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'scope' => ['type' => 'string'],
            'settings' => ['type' => 'object'],
        ],
        'required' => ['scope', 'settings'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\AIOSEO\aioseo_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Inspect the site-wide AIOSEO configuration. Use scope to stay focused — "titles" can be large with many post types. Template strings contain AIOSEO smart tags (#post_title, #separator_sa, #site_title); leave them intact unless changing the pattern. Writes are not an ability — use execute-php.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{separator: string, home_title: string, home_description: string}
 */
function aiogs_general(): array
{
    $options = aio_options_array('aioseo_options');
    $localized = aio_options_array('aioseo_options_localized');
    return [
        // AIOSEO entity-encodes these on save and decodes them at render; report
        // the rendered (decoded) value. The separator is decoded but NOT
        // tag-stripped by AIOSEO (a "<" separator renders literally), so use
        // decode-only; it is not localized, so its base value is authoritative.
        // siteTitle / metaDescription are tag-stripped titles and localised, so
        // prefer the _localized layer.
        'separator' => aio_separator_display(aio_nested_str($options, ['searchAppearance', 'global', 'separator'])),
        'home_title' => aio_display_text(aio_setting_str(
            $options,
            $localized,
            ['searchAppearance', 'global', 'siteTitle'],
        )),
        'home_description' => aio_display_text(aio_setting_str(
            $options,
            $localized,
            ['searchAppearance', 'global', 'metaDescription'],
        )),
    ];
}

/**
 * Per-post-type and per-taxonomy title/description templates.
 *
 * @return array<string, mixed>
 */
function aiogs_titles(): array
{
    $dynamic = aio_options_array('aioseo_options_dynamic');
    // Per-type templates edited in the admin UI are stored in the _localized layer.
    $localized = aio_options_array('aioseo_options_dynamic_localized');

    $post_types = [];
    /** @var array<array-key, mixed>|null $default_templates */
    $default_templates = null;
    foreach (aio_public_post_type_names() as $name) {
        // AIOSEO persists this registered name under an internal key; keep the response keyed by the public name.
        $key = $name === 'type' ? '_aioseo_type' : $name;
        $post_types[$name] = [
            'title_template' => aiogs_template(
                $dynamic,
                $localized,
                ['searchAppearance', 'postTypes', $key, 'title'],
                default_templates: $default_templates,
            ),
            'description_template' => aiogs_template(
                $dynamic,
                $localized,
                ['searchAppearance', 'postTypes', $key, 'metaDescription'],
                default_templates: $default_templates,
            ),
        ];
    }

    $taxonomies = [];
    foreach (aio_public_taxonomy_names() as $name) {
        // AIOSEO persists this registered name under an internal key; keep the response keyed by the public name.
        $key = $name === 'type' ? '_aioseo_type' : $name;
        $taxonomies[$name] = [
            'title_template' => aiogs_template(
                $dynamic,
                $localized,
                ['searchAppearance', 'taxonomies', $key, 'title'],
                default_templates: $default_templates,
            ),
            'description_template' => aiogs_template(
                $dynamic,
                $localized,
                ['searchAppearance', 'taxonomies', $key, 'metaDescription'],
                default_templates: $default_templates,
            ),
        ];
    }

    // These are keyed maps ({post: {...}}); force an empty one to a JSON object
    // {} (not []) so an agent can index it uniformly regardless of count.
    return [
        'post_types' => $post_types === [] ? new \stdClass() : $post_types,
        'taxonomies' => $taxonomies === [] ? new \stdClass() : $taxonomies,
    ];
}

/**
 * The persisted/localized template at $path, falling back to AIOSEO's effective
 * default (from its default-aware dynamic-options tree) when the stored value
 * is empty — so a runtime-registered public type whose Search Appearance row
 * has not been saved reports the template AIOSEO actually renders instead of
 * an empty string.
 *
 * @param array<array-key, mixed> $base
 * @param array<array-key, mixed> $localized
 * @param list<string> $path
 * @param array<array-key, mixed>|null $default_templates
 */
function aiogs_template(array $base, array $localized, array $path, ?array &$default_templates): string
{
    $stored = aio_setting_str($base, $localized, $path);
    if ($stored !== '') {
        return aio_display_text($stored);
    }

    if ($default_templates === null) {
        // Memoize [] too: an unavailable API is attempted once per settings read, not once per public type.
        $default_templates = aio_default_template_options();
    }

    $template = aio_nested_str($default_templates, $path);
    // AIOSEO entity-encodes these templates on save; report the decoded value.
    return aio_display_text($template);
}

/**
 * @param array{scope?: string} $args
 * @return array{scope: string, settings: array<string, mixed>}|WP_Error
 */
function aioseo_get_settings(array $args): array|WP_Error
{
    if (!aio_detect_setup()['active']) {
        return aio_not_active();
    }

    $scope = $args['scope'] ?? 'all';
    if (!in_array($scope, ['all', 'general', 'titles'], strict: true)) {
        return new WP_Error('aioseo_invalid_scope', 'scope must be one of: all, general, titles', ['status' => 400]);
    }

    $settings = [];
    if ($scope === 'all' || $scope === 'general') {
        $settings['general'] = aiogs_general();
    }
    if ($scope === 'all' || $scope === 'titles') {
        $settings['titles'] = aiogs_titles();
    }

    return ['scope' => $scope, 'settings' => $settings];
}
