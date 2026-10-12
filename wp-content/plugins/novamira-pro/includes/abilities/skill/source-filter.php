<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Skill;

/**
 * Forward-compat hook for the Novamira (free) Skills admin UI.
 *
 * Novamira free, from the version that ships the Skills page, aggregates
 * skills from multiple sources via the `novamira_skill_lookup_sources`
 * filter. We register Pro's bundled skill catalog so the free admin can
 * list them read-only alongside the user's own skills.
 *
 * Older versions of free never call `apply_filters` on this filter, so
 * the handler is simply never invoked there — Pro keeps working exactly
 * as before (skill-get ability + MCP server instruction injection).
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * First Novamira (free) version that ships the unified skills system:
 * the `novamira_skill_lookup_sources` filter, the canonical
 * `novamira/skill-get` ability, and the discover-abilities catalog
 * injection. From this version onwards, Pro should NOT register its own
 * `skill-get` ability nor inject its catalog into MCP server
 * instructions — free does both, sourcing Pro's bundled skills through
 * the filter we feed in this file.
 */
const NOVAMIRA_FREE_SKILLS_MIN_VERSION = '1.3.0';

/**
 * Whether the running Novamira (free) version owns the skills system.
 * Returns false when free is older than `NOVAMIRA_FREE_SKILLS_MIN_VERSION`
 * or when the constant isn't defined at all (e.g. free not installed) —
 * in those cases Pro keeps its legacy behaviour.
 */
function novamira_free_owns_skill_get(): bool
{
    if (!defined('NOVAMIRA_VERSION')) {
        return false;
    }
    return version_compare((string) constant('NOVAMIRA_VERSION'), NOVAMIRA_FREE_SKILLS_MIN_VERSION, operator: '>=');
}

add_filter('novamira_skill_lookup_sources', static function (array $sources): array {
    $sources['novamira-pro'] = [
        'id' => 'novamira-pro',
        'priority' => 15, // after Built-in (priority 10), before user-cpt (priority 50).
        'label' => 'Novamira Pro',
        'loader' => __NAMESPACE__ . '\\load_skills_for_source_filter',
    ];
    return $sources;
});

/**
 * Adapt Pro's `skill_scan()` output to the shape Novamira free expects
 * from a source loader. Pro skills are surfaced to the agent through
 * description-matched discovery ("agentic" in the free plugin's
 * vocabulary); they are not exposed as user-invokable MCP prompts.
 *
 * @return list<array{slug: string, name: string, description: string, content: string, enable_prompt: bool, enable_agentic: bool}>
 */
function load_skills_for_source_filter(): array
{
    return array_map(static fn(array $skill): array => [
        'slug' => $skill['slug'],
        'name' => $skill['name'],
        'description' => $skill['description'],
        'content' => $skill['content'],
        'enable_prompt' => false,
        'enable_agentic' => true,
    ], skill_scan());
}
