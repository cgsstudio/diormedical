<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\FeatureIntegration;

use Novamira\Features\Registrar;

if (!defined('ABSPATH')) {
    exit();
}

const MEMORY_FEATURE_ID = 'novamira-pro/memory';

/**
 * Project the existing Pro specialization manifest into Novamira Features without changing its schema.
 */
function register(Registrar $features): void
{
    $features->register('novamira-pro/visual-integration', [
        'kind' => 'feature',
        'provider' => 'Novamira Pro',
        'label' => 'Novamira Pro Visual Integration',
        'description' => 'Elementor and Bricks live-editor tools provided by Novamira Pro.',
        'toggleable' => false,
        'visible' => false,
        'default_active' => true,
        'depends_on' => ['novamira/visual'],
        'skills' => [],
        'abilities' => [],
        'boot' => __NAMESPACE__ . '\\boot_visual_extension',
    ]);

    // Older Novamira releases ignore legacy preference metadata. Wait for the
    // migration-aware registry so updating Pro before the base plugin keeps
    // the existing Memory toggle authoritative until both sides are ready.
    if (method_exists($features, 'supports_legacy_preference_migration')) {
        $features->register(MEMORY_FEATURE_ID, [
            'kind' => 'feature',
            'provider' => 'Novamira Pro',
            'label' => 'Memory',
            'description' => 'Persistent, database-backed memory shared with agents across conversations.',
            'visible' => \Novamira\Pro\license_allows_abilities(),
            'default_active' => true,
            'depends_on' => [],
            'skills' => [],
            'abilities' => [],
            'ability_categories' => ['memory'],
            'legacy_options' => [\Novamira\Pro\license_option_name('memory_enabled')],
        ]);
    }

    $manifest = \Novamira\Pro\specialization_manifest();
    $sharedSkills = shared_specialization_skills($manifest);
    foreach ($manifest as $slug => $entry) {
        if (($entry['core'] ?? false) === true) {
            continue;
        }
        $features->register('novamira-pro/' . $slug, [
            'kind' => 'specialization',
            'provider' => 'Novamira Pro',
            'label' => (string) $entry['category_label'],
            'description' => (string) $entry['category_description'],
            'visible' => true,
            'default_active' => true,
            'depends_on' => feature_dependencies($entry),
            'skills' => feature_skills($entry, $sharedSkills),
            'abilities' => [],
            'ability_categories' => [(string) $entry['slug']],
        ]);
    }
}

function boot_visual_extension(): void
{
    require_once __DIR__ . '/backend/VisualSafeTools.php';
    require_once dirname(__DIR__) . '/novamira-visual/bootstrap.php';
}

/**
 * @param array<string, array<string, mixed>> $manifest
 * @return array<string, true>
 */
function shared_specialization_skills(array $manifest): array
{
    $uses = [];
    foreach ($manifest as $entry) {
        /** @var mixed $skills */
        $skills = $entry['skills'] ?? [];
        if (!is_array($skills)) {
            continue;
        }
        /** @var mixed $skill */
        foreach ($skills as $skill) {
            if (!is_string($skill)) {
                continue;
            }
            $uses[$skill] = ($uses[$skill] ?? 0) + 1;
        }
    }

    return array_fill_keys(
        keys: array_keys(array_filter($uses, static fn(int $count): bool => $count > 1)),
        value: true,
    );
}

/**
 * @param array<string, mixed> $entry
 * @param array<string, true> $sharedSkills
 * @return array<array-key, string>
 */
function feature_skills(array $entry, array $sharedSkills): array
{
    $result = [];
    /** @var mixed $skills */
    $skills = $entry['skills'] ?? [];
    if (!is_array($skills)) {
        return [];
    }
    /** @var mixed $skill */
    foreach ($skills as $skill) {
        if (!is_string($skill)) {
            continue;
        }
        if (array_key_exists($skill, $sharedSkills)) {
            $result[$skill] = 'shared';
            continue;
        }
        $result[] = $skill;
    }

    return $result;
}

/**
 * @param array<string, mixed> $entry
 * @return list<string>
 */
function feature_dependencies(array $entry): array
{
    /** @var mixed $dependencies */
    $dependencies = $entry['depends_on'] ?? [];
    if (!is_array($dependencies)) {
        return [];
    }
    $result = [];
    /** @var mixed $dependency */
    foreach ($dependencies as $dependency) {
        if (!is_string($dependency) || $dependency === '') {
            continue;
        }
        $result[] = str_contains($dependency, '/') ? $dependency : 'novamira-pro/' . $dependency;
    }

    return array_values(array_unique($result));
}
