<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/code-snippets-get-types-schema', [
    'label' => __('Get Code Snippets Types Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the type / scope reference for Code Snippets: which type accepts which scope values, what each scope means in terms of when the snippet runs, and which types require Code Snippets Pro. Pass `type` (e.g. "php") to receive only that type entry — useful when an agent already knows the family and wants to keep the response small. Otherwise the full matrix is returned. Call this once before authoring a snippet so create / edit send the right type+scope pair on the first try.',
        domain: 'novamira-pro',
    ),
    'category' => 'code-snippets',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Optional. When provided, return only that single type entry under `types` and report it as the only `requested` type. Pass one of: php, html, css, js, cond.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'types' => [
                'type' => 'object',
                'description' => 'Map of type slug -> {label, lang, requires_pro, scopes[]}. Each scope entry is {slug, label, description}.',
            ],
            'pro_only_types' => ['type' => 'array', 'items' => ['type' => 'string']],
            'available_on_this_install' => ['type' => 'array', 'items' => ['type' => 'string']],
            'error' => [
                'type' => 'string',
                'description' => 'Present when an unknown `type` filter was supplied; lists known types in the message.',
            ],
        ],
        'required' => ['types', 'pro_only_types', 'available_on_this_install'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\CodeSnippets\\code_snippets_get_types_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reference of the types + scopes the installed Code Snippets accepts (scopes the installed plugin does not offer are not listed). For "run code on every page load" pick type=php scope=global. For "shortcode-driven HTML/JS that renders inside post content" pick type=html scope=content. For "site-wide stylesheet" pick type=css scope=site-css (Pro). The list `available_on_this_install` shows which types this site can actually use — gate your create-snippet calls accordingly.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{type?: string} $input
 * @return array<string, mixed>
 */
function code_snippets_get_types_schema(array $input = []): array
{
    $all = cs_types_reference();
    $filter = $input['type'] ?? '';

    if ($filter !== '') {
        if (!isset($all[$filter])) {
            return [
                // Cast to stdClass so the empty `types` map serialises
                // as a JSON `{}` (matching the declared object schema),
                // not the PHP array default which would emit `[]` and
                // flag the output validator (Round 1 audit S10).
                'types' => new \stdClass(),
                'pro_only_types' => ['css', 'js', 'cond'],
                'available_on_this_install' => cs_available_types(),
                'error' => sprintf('Unknown type "%s". Known types: %s.', $filter, implode(', ', array_keys($all))),
            ];
        }
        return [
            'types' => [$filter => $all[$filter]],
            'pro_only_types' => ['css', 'js', 'cond'],
            'available_on_this_install' => cs_available_types(),
        ];
    }

    return [
        'types' => $all,
        'pro_only_types' => ['css', 'js', 'cond'],
        'available_on_this_install' => cs_available_types(),
    ];
}

/**
 * Hard-coded reference of the snippet types + scopes Code Snippets ships.
 * The base entries are the 3.6–3.9.x line (verified against
 * `Code_Snippets\Snippet::get_all_scopes()` in 3.9.6) and are always
 * present — the schema on those versions is byte-identical to what it
 * was before 3.10 support landed. Scopes added later (`body-content`,
 * 3.10, verified against `Code_Snippets\Model\Snippet::get_all_scopes()`)
 * are appended only when the live plugin lists them, mirroring
 * cs_scopes_by_type(). Nothing is ever removed from the base entries.
 *
 * @return array<string, array<string, mixed>>
 */
function cs_types_reference(): array
{
    $reference = cs_types_reference_static();
    $enabled = cs_enabled_optional_scopes();
    foreach (cs_optional_scopes_reference() as $slug => $entry) {
        if (!isset($enabled[$slug])) {
            continue;
        }
        $type = $enabled[$slug]['type'];
        /** @var list<array{slug: string, label: string, description: string}> $scopes */
        $scopes = $reference[$type]['scopes'];
        $reference[$type]['scopes'] = cs_insert_scope_entry($scopes, $entry, $enabled[$slug]['before']);
    }
    return $reference;
}

/**
 * Entry-level twin of cs_insert_scope(): insert a reference entry before
 * the entry whose slug is `$anchor` (append when absent), skipping it when
 * an entry with the same slug is already present.
 *
 * @param list<array{slug: string, label: string, description: string}> $scopes
 * @param array{slug: string, label: string, description: string} $entry
 * @return list<array{slug: string, label: string, description: string}>
 */
function cs_insert_scope_entry(array $scopes, array $entry, string $anchor): array
{
    $slugs = array_map(static fn(array $scope): string => $scope['slug'], $scopes);
    $ordered = cs_insert_scope($slugs, $entry['slug'], $anchor);
    if ($ordered === $slugs) {
        return $scopes;
    }
    $at = array_search($entry['slug'], $ordered, strict: true);
    array_splice($scopes, $at === false ? count($scopes) : $at, length: 0, replacement: [$entry]);
    return array_values($scopes);
}

/**
 * Reference entries for the version-gated scopes in cs_optional_scopes().
 *
 * @return array<string, array{slug: string, label: string, description: string}>
 */
function cs_optional_scopes_reference(): array
{
    return [
        'body-content' => [
            'slug' => 'body-content',
            'label' => 'Body',
            'description' => 'Output verbatim right after the opening <body> tag (wp_body_open) on every front-end page. Use for no-script tag-manager iframes and anything that must sit at the top of the body.',
        ],
    ];
}

/**
 * The 3.6–3.9.x reference: every type + scope of the supported floor line.
 *
 * @return array<string, array<string, mixed>>
 */
function cs_types_reference_static(): array
{
    return [
        'php' => [
            'label' => 'PHP function',
            'lang' => 'php',
            'requires_pro' => false,
            'scopes' => [
                [
                    'slug' => 'global',
                    'label' => 'Global',
                    'description' => 'Runs on every request (admin + front-end). Default choice for filter / action hooks meant to apply everywhere.',
                ],
                [
                    'slug' => 'admin',
                    'label' => 'Admin only',
                    'description' => 'Runs only inside wp-admin requests. Use for admin-only hooks (menus, columns, dashboard widgets).',
                ],
                [
                    'slug' => 'front-end',
                    'label' => 'Front-end only',
                    'description' => 'Runs only on public-facing requests. Use for template / theme-level hooks where running in admin would waste cycles.',
                ],
                [
                    'slug' => 'single-use',
                    'label' => 'Run once',
                    'description' => 'Executes once on save then auto-deactivates. Use for one-off data migrations, repairs, cache flushes.',
                ],
            ],
        ],
        'html' => [
            'label' => 'Content',
            'lang' => 'html',
            'requires_pro' => false,
            'scopes' => [
                [
                    'slug' => 'content',
                    'label' => 'Shortcode',
                    'description' => 'Registered as a shortcode named `[code_snippet id="<id>"]` that renders the snippet body wherever placed in post content.',
                ],
                [
                    'slug' => 'head-content',
                    'label' => 'Head',
                    'description' => 'Output verbatim inside the <head> on every front-end page. Use for meta tags, third-party analytics tags, inline <style>.',
                ],
                [
                    'slug' => 'footer-content',
                    'label' => 'Footer',
                    'description' => 'Output verbatim just before </body> on every front-end page. Use for analytics / chat widgets / inline <script>.',
                ],
            ],
        ],
        'css' => [
            'label' => 'Styles',
            'lang' => 'css',
            'requires_pro' => true,
            'scopes' => [
                [
                    'slug' => 'admin-css',
                    'label' => 'Admin styles',
                    'description' => 'Enqueued only inside wp-admin. Use to tweak admin UI without a custom plugin.',
                ],
                [
                    'slug' => 'site-css',
                    'label' => 'Front-end styles',
                    'description' => 'Enqueued on every front-end page. The site-wide custom CSS knob.',
                ],
            ],
        ],
        'js' => [
            'label' => 'Scripts',
            'lang' => 'javascript',
            'requires_pro' => true,
            'scopes' => [
                [
                    'slug' => 'site-head-js',
                    'label' => 'Head scripts',
                    'description' => 'Inline <script> in the <head> on every front-end page. Use for synchronous setup (tag managers).',
                ],
                [
                    'slug' => 'site-footer-js',
                    'label' => 'Footer scripts',
                    'description' => 'Inline <script> just before </body> on every front-end page. Default choice for tracking / chat widgets.',
                ],
            ],
        ],
        'cond' => [
            'label' => 'Condition',
            'lang' => 'php',
            'requires_pro' => true,
            'scopes' => [
                [
                    'slug' => 'condition',
                    'label' => 'Condition',
                    'description' => 'Reusable boolean predicate other snippets can be linked to (Pro-only Conditions module). Conditions themselves do not run page-side; they gate the execution of other snippets.',
                ],
            ],
        ],
    ];
}
