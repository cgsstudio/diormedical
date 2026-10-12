<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use const BRICKS_PATH;

/**
 * Ability: List Bricks page/template/global settings schema.
 *
 * Bricks builds its settings panel from a `controls` array analogous to how
 * elements expose their controls. We introspect `Bricks\Settings_Page` and
 * `Bricks\Settings_Template` directly so the schema stays in sync with the
 * installed Bricks version without a curated key list.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Control types that exist in the Bricks controls array for UI structuring
 * only and do not correspond to a storable setting key. These are filtered
 * out of the validation key-set (but still returned by the ability so callers
 * can see the full panel shape if they want).
 *
 * @var list<string>
 */
const NOVAMIRA_BRICKS_SETTINGS_UI_ONLY_TYPES = ['separator', 'info', 'apply'];

wp_register_ability('novamira/bricks-list-settings', [
    'label' => __('List Bricks Settings', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Bricks settings schema: page settings (for regular posts/pages), template settings (additional keys available on bricks_template posts), and global settings. By default returns a compact shape (type, label, group, description, value_format, options) sufficient for key discovery and validation. Pass key="…" to drill into a single control with all fields, or verbose=true to return every field of every control.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'scope' => [
                'type' => 'string',
                'enum' => ['page', 'template', 'global', 'all'],
                'default' => 'all',
                'description' => 'Which schema to return. "page" for regular posts/pages, "template" for bricks_template-only keys, "global" for site-wide Bricks settings, "all" for everything.',
            ],
            'key' => [
                'type' => 'string',
                'description' => 'If provided, return only the control with this key (looked up in the selected scope). The returned control is always verbose — use this to drill into one setting without paying for the full list.',
            ],
            'verbose' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Return every field of every control (css selectors, placeholders, tooltips, defaults, etc.). Only useful for debugging — defaults to a compact shape.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'page' => ['type' => 'object'],
            'template' => ['type' => 'object'],
            'global' => ['type' => 'object'],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when a scope came back empty because its definitions could not be read, so an empty list is not mistaken for "no settings".',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Progressive disclosure: the default call is compact (~1-2k tokens). Use key="…" to get one control with full details, or verbose=true for every field of every control (~13k tokens — avoid unless you need every field). Page settings apply to any post/page rendered with Bricks. Template settings are additional keys only valid on bricks_template posts (popup behaviour, password protection, template conditions, sticky header options, etc.) — they are stored in the same _bricks_page_settings meta alongside page settings. Global settings are site-wide Bricks configuration stored in the bricks_global_settings option.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{scope?: string, key?: string, verbose?: bool} $input
 * @return array<string, mixed>
 */
function bricks_list_settings(array $input): array
{
    $scope = $input['scope'] ?? 'all';
    $key = is_string($input['key'] ?? null) ? $input['key'] : null;
    $verbose = ($input['verbose'] ?? false) === true;

    $scopes = bricks_collect_settings_scopes($scope);

    if ($key !== null) {
        return bricks_drill_into_setting($scopes, $key);
    }

    $result = [];
    foreach ($scopes as $scope_name => $data) {
        $result[$scope_name] = $verbose ? bricks_format_scope_verbose($data) : bricks_format_scope_compact($data);
    }

    // An empty page/template scope means the classes that define those
    // controls could not be loaded — say which, rather than handing back a
    // silent zero that reads like "this Bricks has no page settings".
    $unreadable = [];
    foreach (['page', 'template'] as $scope_name) {
        if (!array_key_exists($scope_name, $scopes) || $scopes[$scope_name]['controls'] !== []) {
            continue;
        }
        $unreadable[] = $scope_name;
    }
    if ($unreadable !== []) {
        $result['warnings'] = [bricks_list_settings_degraded_warning($unreadable)];
    }

    return $result;
}

/**
 * What an empty page/template scope means, and which calls still work.
 *
 * Both calls it names were executed against a real post: bricks-set-settings
 * accepted a page setting and bricks-get-settings returned the stored value.
 * It used to name bricks-list-settings for stored values — that returns
 * control definitions with no value at all, and is the very call that just
 * came up empty.
 *
 * @param list<string> $unreadable
 */
function bricks_list_settings_degraded_warning(array $unreadable): string
{
    return sprintf(
        'The %s setting definitions could not be read from this Bricks: the files it keeps them in are missing, unreadable, or define no controls, so the list above is empty and is NOT a statement that this Bricks has no such settings. The keys a post already stores can still be read and written — novamira/bricks-get-settings with the post id returns the stored values, and novamira/bricks-set-settings writes them.',
        implode(' and ', $unreadable),
    );
}

/**
 * @return array<string, array{controls: array<string, array<string, mixed>>, controlGroups?: array<string, mixed>}>
 */
function bricks_collect_settings_scopes(string $scope): array
{
    $scopes = [];
    if ($scope === 'page' || $scope === 'all') {
        $scopes['page'] = bricks_get_settings_schema('page');
    }
    if ($scope === 'template' || $scope === 'all') {
        $scopes['template'] = bricks_get_settings_schema('template');
    }
    if ($scope === 'global' || $scope === 'all') {
        $scopes['global'] = bricks_get_global_settings_schema();
    }
    return $scopes;
}

/**
 * Find a control by key across the given scopes and return it with full fields.
 *
 * @param array<string, array{controls: array<string, array<string, mixed>>, controlGroups?: array<string, mixed>}> $scopes
 * @return array{scope: string|null, key: string, control: array<string, mixed>|null}
 */
function bricks_drill_into_setting(array $scopes, string $key): array
{
    foreach ($scopes as $scope_name => $data) {
        if (array_key_exists($key, $data['controls'])) {
            return ['scope' => $scope_name, 'key' => $key, 'control' => $data['controls'][$key]];
        }
    }
    return ['scope' => null, 'key' => $key, 'control' => null];
}

/**
 * @param array{controls: array<string, array<string, mixed>>, controlGroups?: array<string, mixed>} $data
 * @return array<string, mixed>
 */
function bricks_format_scope_verbose(array $data): array
{
    $formatted = ['controls' => $data['controls']];
    if (array_key_exists('controlGroups', $data)) {
        $formatted['controlGroups'] = $data['controlGroups'];
    }
    return $formatted;
}

/**
 * @param array{controls: array<string, array<string, mixed>>, controlGroups?: array<string, mixed>} $data
 * @return array<string, mixed>
 */
function bricks_format_scope_compact(array $data): array
{
    $controls = $data['controls'];
    foreach ($controls as $ckey => $control) {
        $controls[$ckey] = bricks_compact_control($control);
    }

    $formatted = ['controls' => $controls];
    if (array_key_exists('controlGroups', $data)) {
        $formatted['controlGroups'] = $data['controlGroups'];
    }
    return $formatted;
}

/**
 * Introspect `Bricks\Settings_Page` or `Bricks\Settings_Template` and return
 * its controls + control groups, with each control annotated via
 * `bricks_annotate_control()` for the agent's benefit.
 *
 * Instantiation happens with no query/post context. `Settings_Page` branches
 * on `get_the_ID()` / `Database::$global_settings` but with an empty context
 * all branches that matter for the maximum key superset are taken (verified
 * against Bricks 2.x: bodyClasses, header/footerDisabled, one-page nav, SEO,
 * social media, custom code, scroll snap, and the Theme Styles merge).
 *
 * @param 'page'|'template' $type
 * @return array{controls: array<string, array<string, mixed>>, controlGroups: array<string, mixed>}
 */
function bricks_get_settings_schema(string $type): array
{
    if (!bricks_load_settings_classes()) {
        return ['controls' => [], 'controlGroups' => []];
    }

    try {
        $instance = $type === 'template' ? new \Bricks\Settings_Template($type) : new \Bricks\Settings_Page($type);
        $data = $instance->get_controls_data();
    } catch (\Throwable) {
        return ['controls' => [], 'controlGroups' => []];
    }

    $controls = $data['controls'];
    foreach ($controls as $key => $control) {
        $controls[$key] = bricks_annotate_control($control);
    }

    return [
        'controls' => $controls,
        'controlGroups' => $data['controlGroups'],
    ];
}

/**
 * Returns the global Bricks settings schema, derived from Bricks itself
 * ({@see bricks_global_settings_registry()}): the keys of the installed
 * build's settings screen, the keys the abilities always supported and the
 * keys already stored in the option, typed, with the store-by-presence
 * semantics of its toggles spelled out. Credentials and
 * the code-execution toggle are never listed (nor writable).
 *
 * @return array{controls: array<string, array<string, mixed>>}
 */
function bricks_get_global_settings_schema(): array
{
    $controls = [];
    foreach (bricks_global_settings_registry() as $key => $entry) {
        $type = (string) ($entry['type'] ?? 'text');
        $control = [
            'type' => $type === 'post_type_list' ? 'array' : $type,
            'label' => (string) ($entry['label'] ?? $key),
            'value_format' => bricks_registry_value_format($key, $entry),
        ];
        if ($type === 'select' && is_array($entry['options'] ?? null)) {
            $options = [];
            /** @var mixed $option */
            foreach ($entry['options'] as $option) {
                $option = (string) $option;
                $options[$option] = $option === '' ? '(default — key removed)' : $option;
            }
            $control['options'] = $options;
        }
        $controls[$key] = $control;
    }

    return ['controls' => $controls];
}

/**
 * Ensures `Bricks\Settings_Base`, `Settings_Page`, and `Settings_Template`
 * are loaded. In a REST/MCP context `Bricks\Settings::set_controls()` has not
 * fired yet (it hooks `wp` / `admin_init`), so the subclass files are not
 * required by Bricks itself. This mirrors what `Bricks\Settings::set_controls`
 * does internally.
 *
 * Returns false when the classes cannot be made available — a build that
 * moved one of the three files, or a file that is not readable. Callers
 * degrade to an empty schema and say what could not be read.
 */
function bricks_load_settings_classes(): bool
{
    if (!defined('BRICKS_PATH')) {
        return false;
    }

    // Each path stays a literal suffix on BRICKS_PATH, never assembled from a
    // variable: the guard smoke refuses a computed path into the builder's
    // install, and it is right to — a computed one is how a bypass would hide.
    // Guarded with is_readable() on purpose: a build that moves or renames one
    // of these three files would otherwise fatal a READ, and a read that
    // cannot answer has to say so rather than take the request down with it.
    if (!class_exists(\Bricks\Settings_Base::class)) {
        if (!is_readable((string) BRICKS_PATH . 'includes/settings/base.php')) {
            return false;
        }
        require_once (string) BRICKS_PATH . 'includes/settings/base.php';
    }
    if (!class_exists(\Bricks\Settings_Page::class)) {
        if (!is_readable((string) BRICKS_PATH . 'includes/settings/settings-page.php')) {
            return false;
        }
        require_once (string) BRICKS_PATH . 'includes/settings/settings-page.php';
    }
    if (!class_exists(\Bricks\Settings_Template::class)) {
        if (!is_readable((string) BRICKS_PATH . 'includes/settings/settings-template.php')) {
            return false;
        }
        require_once (string) BRICKS_PATH . 'includes/settings/settings-template.php';
    }

    return class_exists(\Bricks\Settings_Page::class) && class_exists(\Bricks\Settings_Template::class);
}

/**
 * Returns the set of setting keys considered valid for a given post type.
 * For `bricks_template` posts, valid keys are the union of page and template
 * controls (both control sets are stored in the same `_bricks_page_settings`
 * meta). For every other post type, only page controls are valid.
 *
 * UI-only controls (separator, info, apply) are excluded — they do not
 * correspond to storable keys.
 *
 * @return array<string, array<string, mixed>> key => annotated control
 */
function bricks_get_valid_post_settings(string $post_type): array
{
    $page = bricks_get_settings_schema('page')['controls'];
    $template = $post_type === 'bricks_template' ? bricks_get_settings_schema('template')['controls'] : [];

    $merged = $page + $template;

    return array_filter(
        $merged,
        static fn(array $c): bool => !in_array($c['type'] ?? '', NOVAMIRA_BRICKS_SETTINGS_UI_ONLY_TYPES, strict: true),
    );
}
