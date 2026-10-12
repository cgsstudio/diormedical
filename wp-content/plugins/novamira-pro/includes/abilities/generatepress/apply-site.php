<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-apply-site', [
    'label' => __('Apply GeneratePress Site Library Template', domain: 'novamira-pro'),
    'description' => __(
        'DESTRUCTIVE — overwrites Customizer settings, imports posts/pages/media, sets element locations, and (optionally) installs/activates plugins from a Site Library starter template. Steps run in fixed order: theme_options → plugins → content → site_options → widgets. The default step list excludes `plugins` because plugin installation is the riskiest path; pass `steps=["theme_options","plugins","content","site_options","widgets"]` to opt in. `confirm=true` is required — the call returns a `gp_confirmation_required` WP_Error otherwise. Use generatepress-get-site first to know which assets are present (a step whose asset is missing fails fast and is recorded under steps_failed). Rollback is partial: `restore_theme_options` and `restore_content` exist in the WP admin Site Library panel but are not exposed via abilities yet.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'The `name` field from generatepress-list-sites.',
            ],
            'confirm' => [
                'type' => 'boolean',
                'description' => 'Must be true. Apply-site is destructive (overwrites Customizer settings and imports content) — the flag forces the agent to surface the confirmation to the user explicitly.',
            ],
            'steps' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['theme_options', 'plugins', 'content', 'site_options', 'widgets'],
                ],
                'description' => 'Which steps to run. Pass an explicit subset to limit the side effects (e.g. ["theme_options"] for a settings-only preview).',
                'default' => ['theme_options', 'content', 'site_options', 'widgets'],
            ],
        ],
        'required' => ['name', 'confirm'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'steps_completed' => ['type' => 'array', 'items' => ['type' => 'string']],
            'steps_skipped' => ['type' => 'array', 'items' => ['type' => 'string']],
            'steps_failed' => ['type' => 'array'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_apply_site',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Workflow: generatepress-list-sites → generatepress-get-site (read `assets` map) → generatepress-apply-site with `confirm=true` and the right `steps` subset. Recommend the user a settings-only first pass (`steps=["theme_options"]`) when they want to evaluate a template before committing to content / widgets / plugin installs.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{name: string, confirm?: bool, steps?: list<string>} $input
 * @return array<string, mixed>|WP_Error
 */
function generatepress_apply_site(array $input): array|WP_Error
{
    $name = gp_strip_nul_bytes(trim($input['name']));
    if ($name === '') {
        return gp_error('gp_invalid_input', __('name is required.', domain: 'novamira-pro'));
    }
    if (!gp_coerce_bool($input['confirm'] ?? false)) {
        return gp_error('gp_confirmation_required', __(
            'apply-site is destructive — pass confirm=true once the user has approved the import.',
            domain: 'novamira-pro',
        ));
    }

    $site = gp_find_site($name);
    if ($site === null) {
        return gp_error('gp_not_found', sprintf(
            __('Site Library template "%s" not found in the cached catalogue.', domain: 'novamira-pro'),
            $name,
        ));
    }

    $requested_steps = $input['steps'] ?? gp_site_default_steps();
    if ($requested_steps === []) {
        // An empty steps list is almost always a confused call — short
        // circuit with a clear error rather than reporting success=true
        // for a no-op. Use gp_site_default_steps() (or list at least one
        // step) to opt in explicitly.
        return [
            'success' => false,
            'error' => 'Pass at least one step in `steps` — empty list is a no-op (no apply runs).',
        ];
    }

    $assets = gp_site_asset_presence(
        isset($site['directory']) && is_string($site['directory']) ? $site['directory'] : '',
    );
    $steps_map = gp_site_steps_map();

    // The plugins step requires options.json upstream (GP Premium pulls
    // the plugin list out of it). Gate it the same way we gate the other
    // import_* endpoints — without this guard a bogus template would
    // dispatch activate_plugins, which silently rewrites the existing
    // `_generatepress_site_library_backup` option with the current
    // active_plugins, destroying the rollback target for a prior apply.
    $asset_gated = [
        'theme_options' => 'has_options',
        'plugins' => 'has_options',
        'content' => 'has_content',
        'widgets' => 'has_widgets',
    ];

    $completed = [];
    $skipped = [];
    $failed = [];

    foreach (array_keys($steps_map) as $step) {
        if (!in_array($step, $requested_steps, strict: true)) {
            $skipped[] = $step;
            continue;
        }
        if (isset($asset_gated[$step])) {
            $asset_key = $asset_gated[$step];
            if (!($assets[$asset_key] ?? false)) {
                $failed[] = ['step' => $step, 'error' => 'asset_missing:' . $asset_key];
                continue;
            }
        }
        $err = gp_dispatch_site_step($steps_map[$step], $site);
        if ($err !== null) {
            $failed[] = ['step' => $step, 'error' => $err];
            continue;
        }
        $completed[] = $step;
    }

    // The GP Premium `import_options` route already invalidates the dynamic
    // CSS cache, but other completed steps may have touched site_options /
    // module activations that affect the inline stylesheet. Flush again
    // unconditionally so partial successes don't leave a stale cache.
    if ($completed !== []) {
        gp_invalidate_dynamic_css_cache();
    }

    return [
        'success' => $failed === [],
        'steps_completed' => $completed,
        'steps_skipped' => $skipped,
        'steps_failed' => $failed,
    ];
}
