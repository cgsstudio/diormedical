<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-get-site', [
    'label' => __('Get GeneratePress Site Library Template', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full record for a single starter template plus a probe of which remote assets (`options.json`, `content.xml`, `widgets.wie`) are present at the template directory. The asset-presence flags tell you which steps generatepress-apply-site can actually run — for example, a template without `widgets.wie` will silently skip the `widgets` step. Use the `plugins` map to know what activate_plugins would attempt before running an apply with `steps=["plugins"]`.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'The `name` key returned by generatepress-list-sites (the upstream catalogue is keyed by name, not slug).',
            ],
        ],
        'required' => ['name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'site' => ['type' => 'object'],
            'assets' => [
                'type' => 'object',
                'properties' => [
                    'has_options' => ['type' => 'boolean'],
                    'has_content' => ['type' => 'boolean'],
                    'has_widgets' => ['type' => 'boolean'],
                ],
            ],
        ],
        'required' => ['site', 'assets'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_get_site',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call this once after generatepress-list-sites and before generatepress-apply-site. The `assets` map predicts which apply-site steps will produce side effects — running a step whose asset is missing wastes a network round trip and returns a failure.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{name: string} $input
 * @return array<string, mixed>|WP_Error
 */
function generatepress_get_site(array $input): array|WP_Error
{
    $name = trim($input['name']);
    if ($name === '') {
        return gp_error('gp_invalid_input', __('name is required.', domain: 'novamira-pro'));
    }
    $site = gp_find_site($name);
    if ($site === null) {
        return gp_error('gp_not_found', sprintf(
            __(
                'Site Library template "%s" not found in the cached catalogue. Call generatepress-list-sites with refresh=true if you expect it to be there.',
                domain: 'novamira-pro',
            ),
            $name,
        ));
    }
    $directory = isset($site['directory']) && is_string($site['directory']) ? $site['directory'] : '';
    // Decode the raw upstream record's `plugins` JSON string + normalise
    // category / page_builder to a list so the agent gets the same shape
    // list-sites returns. Otherwise an agent would see a JSON string here
    // and a parsed map from list-sites — confusing inconsistency.
    if (isset($site['plugins'])) {
        $site['plugins'] = gp_normalise_site_plugins($site);
    }
    if (isset($site['category'])) {
        $site['category'] = gp_normalise_string_list(site: $site, key: 'category');
    }
    if (isset($site['page_builder'])) {
        $site['page_builder'] = gp_normalise_string_list(site: $site, key: 'page_builder');
    }
    return [
        'site' => $site,
        'assets' => gp_site_asset_presence($directory),
    ];
}
