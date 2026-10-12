<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Skill;

use WP_Error;

/**
 * Ability: Fetch the full body of a single skill by slug.
 */

if (!defined('ABSPATH')) {
    exit();
}

// Skip registering the ability entirely when no skills are available — the
// Agent Skills spec recommends against surfacing a dormant activation tool.
$novamira_pro_skill_slugs = array_map(static fn(array $skill): string => $skill['slug'], skill_scan());

if ($novamira_pro_skill_slugs === []) {
    return;
}

wp_register_ability('novamira/skill-get', [
    'label' => __('Get Skill', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full instructions for a single bundled skill by slug. The list of available skills (slug + description) is injected into the MCP server instructions at session start — call this ability to load the full body of a skill whose description matches the task at hand.',
        domain: 'novamira-pro',
    ),
    'category' => 'skill',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => [
                'type' => 'string',
                'enum' => $novamira_pro_skill_slugs,
                'description' => 'The skill slug, as listed in the Skills section of the MCP server instructions.',
            ],
        ],
        'required' => ['slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'slug' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'content' => ['type' => 'string'],
            'directory' => [
                'type' => 'string',
                'description' => 'Absolute path to the skill\'s directory on the WordPress server. Use this as the base for any relative paths referenced by the skill body.',
            ],
            'resources' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Files bundled with the skill, listed as paths relative to `directory`. Load them on demand (e.g. via Novamira\'s `read-file` ability) only when the skill body references them — do not eagerly read everything.',
            ],
            'resources_truncated' => [
                'type' => 'boolean',
                'description' => 'True if the resource listing hit the enumeration cap and is incomplete.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Skill\skill_get',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The list of available skills (slug + description) is injected into the MCP server instructions at session start. When the user asks you to perform a task, check that list first — if a skill description matches the task, call this ability with its slug to load the full instructions BEFORE you start working. Treat the returned content as authoritative guidance. Do not mention or recommend a skill without actually loading it. The response also includes the skill\'s absolute `directory` and a list of bundled `resources` (paths relative to that directory). Treat those as references — load them with Novamira\'s `read-file` ability only when the skill body actually asks you to, not eagerly.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{slug: string} $input
 * @return array{slug: string, name: string, description: string, content: string, directory: string, resources: list<string>, resources_truncated: bool}|WP_Error
 */
function skill_get(array $input): array|WP_Error
{
    $slug = $input['slug'];
    $skill = skill_find($slug);

    if ($skill === null) {
        return new WP_Error('not_found', sprintf('Skill "%s" not found.', $slug));
    }

    return [
        'slug' => $skill['slug'],
        'name' => $skill['name'],
        'description' => $skill['description'],
        'content' => $skill['content'],
        'directory' => $skill['directory'],
        'resources' => $skill['resources'],
        'resources_truncated' => $skill['resources_truncated'],
    ];
}
