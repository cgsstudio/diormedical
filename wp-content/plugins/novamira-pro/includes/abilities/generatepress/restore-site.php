<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GeneratePress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/generatepress-restore-site', [
    'label' => __('Restore GeneratePress Site Library Backup', domain: 'novamira-pro'),
    'description' => __(
        'Undo for generatepress-apply-site. GP Premium snapshots `theme_options` (Customizer settings + theme mods + active modules) and `content` (post / term ids imported during an apply) into a single `_generatepress_site_library_backup` option at apply time. This ability dispatches the matching REST restore endpoint(s): `target=theme_options` reverts Customizer / theme_mods / module activation (backup option preserved — safe to re-run); `target=content` deletes the posts and terms imported by the last apply AND removes the backup option (single-use — a second call returns `gp_no_backup`); `target=all` (default) runs both, leaving no backup. `confirm=true` is required. Returns `gp_no_backup` WP_Error when no apply has run yet or the backup was already consumed.',
        domain: 'novamira-pro',
    ),
    'category' => 'generatepress',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'target' => [
                'type' => 'string',
                'enum' => ['theme_options', 'content', 'all'],
                'description' => 'Which part of the backup to restore. `theme_options` reverts Customizer settings + theme_mods + active modules. `content` deletes the imported posts/terms. `all` runs both (theme_options first).',
                'default' => 'all',
            ],
            'confirm' => [
                'type' => 'boolean',
                'description' => 'Must be true. The restore is destructive — it overwrites current settings or deletes the imported content.',
            ],
        ],
        'required' => ['confirm'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'restored' => ['type' => 'array', 'items' => ['type' => 'string']],
            'errors' => ['type' => 'array'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GeneratePress\\generatepress_restore_site',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use after generatepress-apply-site has run and the user wants to roll it back. The backup is the one GP Premium itself created at apply-time — there is no manual snapshot taken by this integration. If the user wants to roll back beyond the last apply, they need a database backup outside this ability.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{target?: string, confirm?: bool} $input
 * @return array<string, mixed>|WP_Error
 */
function generatepress_restore_site(array $input): array|WP_Error
{
    if (!gp_coerce_bool($input['confirm'] ?? false)) {
        return gp_error('gp_confirmation_required', __(
            'restore-site is destructive — pass confirm=true once the user has approved the rollback.',
            domain: 'novamira-pro',
        ));
    }

    /** @var mixed $backup */
    $backup = get_option('_generatepress_site_library_backup', []);
    if (!is_array($backup) || $backup === []) {
        return gp_error('gp_no_backup', __(
            'No Site Library backup is stored — apply a template first, or restore from a database backup.',
            domain: 'novamira-pro',
        ));
    }

    $target = isset($input['target']) ? $input['target'] : 'all';
    $steps = match ($target) {
        'theme_options' => ['theme_options'],
        'content' => ['content'],
        default => ['theme_options', 'content'],
    };

    $restored = [];
    $errors = [];
    foreach ($steps as $step) {
        $route = $step === 'theme_options' ? 'restore_theme_options' : 'restore_content';
        $err = gp_dispatch_site_step($route, []);
        if ($err !== null) {
            $errors[] = ['step' => $step, 'error' => $err];
            continue;
        }
        $restored[] = $step;
    }

    // GP Premium's `restore_theme_options` writes the settings back but does
    // NOT invalidate `generate_dynamic_css_output` (their `restore_content`
    // does — inconsistency in the plugin). Flush both unconditionally so
    // restore is reflected on the next pageload.
    if ($restored !== []) {
        gp_invalidate_dynamic_css_cache();
    }

    return [
        'success' => $errors === [],
        'restored' => $restored,
        'errors' => $errors,
    ];
}
