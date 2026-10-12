<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro;

if (!defined('ABSPATH')) {
    exit();
}

/** @return array<string, array<string, mixed>> */
function specialization_manifest(): array
{
    /** @var array<string, array<string, mixed>>|null $manifest */
    static $manifest = null;
    if ($manifest === null) {
        /** @var array<string, array<string, mixed>> $loaded */
        $loaded = require __DIR__ . '/manifest.php';
        $manifest = $loaded;
    }
    return $manifest;
}

function specialization_register_eager_bootstraps(): void
{
    foreach (specialization_manifest() as $entry) {
        /** @var mixed $boot */
        $boot = $entry['eager_bootstrap'] ?? null;
        if (is_callable($boot)) {
            $boot();
        }
    }
}

/** @param array<string, mixed> $entry */
function specialization_gate_passes(string $slug, array $entry): bool
{
    /** @var callable(): bool $gate */
    $gate = $entry['gate'];
    return specialization_cached_gate($slug . ':category', $gate);
}

/** @param array<string, mixed> $entry */
function specialization_load_gate_passes(string $slug, array $entry): bool
{
    /** @var callable(): bool $gate */
    $gate = $entry['load_gate'] ?? $entry['gate'];
    return specialization_cached_gate($slug . ':load', $gate);
}

/** @param callable(): bool $gate */
function specialization_cached_gate(string $key, callable $gate): bool
{
    /** @var array<string, bool> $cache */
    static $cache = [];
    $cache[$key] ??= $gate() === true;

    return $cache[$key];
}

function specialization_register_categories(): void
{
    if (!license_allows_abilities()) {
        return;
    }
    foreach (specialization_manifest() as $slug => $entry) {
        $always = ($entry['category_always'] ?? false) === true;
        if (!$always && !specialization_gate_passes($slug, $entry)) {
            continue;
        }
        wp_register_ability_category((string) $entry['slug'], [
            'label' => (string) $entry['category_label'],
            'description' => (string) $entry['category_description'],
        ]);
    }
}

function specialization_load_abilities(): void
{
    if (!license_allows_abilities()) {
        return;
    }
    $before = array_keys(wp_get_abilities());
    foreach (specialization_manifest() as $slug => $entry) {
        if (!specialization_load_gate_passes($slug, $entry)) {
            continue;
        }
        /** @var callable(): void $loader */
        $loader = $entry['loader'];
        $loader();
    }
    $pro_ability_names = array_fill_keys(keys: array_diff(array_keys(wp_get_abilities()), $before), value: true);
    add_filter(
        'novamira_ability_source_label',
        static fn(string $label, \WP_Ability $ability): string => $pro_ability_names[$ability->get_name()] ?? false
            ? __('Novamira Pro', domain: 'novamira-pro')
            : $label,
        priority: 10,
        accepted_args: 2,
    );
}
