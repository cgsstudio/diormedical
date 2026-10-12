<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-check-setup', [
    'label' => __('Check Oxygen Setup', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Oxygen 6 runtime state in one call: whether the plugin is active, its version, whether the minimum supported release is satisfied, the license mode (`free` vs `pro`), a snapshot of the live element registry, and the detection state of the two legacy Oxygen Classic companion plugins (`oxygen-gutenberg`, `oxygen-woocommerce`). Use this BEFORE any other Oxygen ability. Every other call is gated on `plugin.active && plugin.min_satisfied`. The `elements` block reports how many native `OxygenElements\\*` elements are registered and whether the optional "Breakdance Elements for Oxygen" extension is active (it adds the `EssentialElements\\*` set — Section, Heading, Image2, etc.). Always pick element types from the live registry via `oxygen-list-element-types`, never from a fixed list. Oxygen 6 shares its engine with Breakdance (`BREAKDANCE_MODE=oxygen`); the two integrations are mutually exclusive at the gate level, so this ability only registers when the running plugin identifies itself as Oxygen. Companions are legacy Oxygen Classic add-ons that do NOT function on Oxygen 6 — surface `companions.*.active=true` as a warning so an agent guides the user toward the standard Gutenberg / WooCommerce abilities instead.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'plugin' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                    'mode' => ['type' => 'string', 'enum' => ['oxygen', 'breakdance', 'unknown']],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied', 'mode'],
                'additionalProperties' => false,
            ],
            'license' => [
                'type' => 'object',
                'properties' => [
                    'has_key' => ['type' => 'boolean'],
                    'mode' => ['type' => 'string', 'enum' => ['free', 'pro']],
                    'status' => ['type' => 'string'],
                ],
                'required' => ['has_key', 'mode'],
                'additionalProperties' => false,
            ],
            'elements' => [
                'type' => 'object',
                'properties' => [
                    'registry_total' => [
                        'type' => 'integer',
                        'description' => 'Total registered element types (all namespaces).',
                    ],
                    'oxygen_native_count' => [
                        'type' => 'integer',
                        'description' => 'Count of native `OxygenElements\\*` elements (always present on Oxygen 6).',
                    ],
                    'essential_extension_present' => [
                        'type' => 'boolean',
                        'description' => 'Whether the optional "Breakdance Elements for Oxygen" extension (the builder pack adding Section / Heading / Image2 / Div / Columns / …) is active. This is NOT "any EssentialElements type exists": base Oxygen 6 already ships ~22 EssentialElements integration types, counted in `registry_total`.',
                    ],
                    'essential_extension_count' => [
                        'type' => 'integer',
                        'description' => 'Number of `EssentialElements\\*` types registered when the extension is active — `0` when it is absent. When present this includes both the extension\'s builder pack and the base integration types, so treat it as "how many EssentialElements are available", not "how many the extension added".',
                    ],
                ],
                'required' => [
                    'registry_total',
                    'oxygen_native_count',
                    'essential_extension_present',
                    'essential_extension_count',
                ],
                'additionalProperties' => false,
            ],
            'companions' => [
                'type' => 'object',
                'properties' => [
                    'oxygen_gutenberg' => ['type' => 'object'],
                    'oxygen_woocommerce' => ['type' => 'object'],
                ],
                'required' => ['oxygen_gutenberg', 'oxygen_woocommerce'],
                'additionalProperties' => false,
            ],
            'issues' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['plugin', 'license', 'elements', 'companions', 'issues'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always the first call on an Oxygen site. Surface plugin.min_satisfied=false to the user before continuing. Surface license.mode=free before using Pro-only elements. If elements.essential_extension_present=false, the builder elements (Section/Heading/Image2) are NOT available — build with the native OxygenElements\\* set from oxygen-list-element-types. Surface companions.*.active=true as a warning: they are legacy Oxygen Classic and do NOT function on Oxygen 6.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function oxygen_check_setup(): array
{
    $version = ox_version();
    $mode = ox_current_mode();
    $active = $version !== '' && function_exists('Breakdance\\Data\\get_global_option') && $mode === 'oxygen';
    $license_key = ox_license_key();
    $validity = ox_license_validity();
    $status = '';
    if (
        is_array($validity)
        && isset($validity['edd_key_info']['license'])
        && is_string($validity['edd_key_info']['license'])
    ) {
        $status = $validity['edd_key_info']['license'];
    }

    // Element registry snapshot — derived live, never from a fixed table.
    $native = ox_oxygen_native_slugs();
    $essential = ox_essential_element_slugs();
    $registry_total = count(ox_registered_element_slugs());
    // The extension flag is driven by a real marker, NOT by "any Essential
    // slug exists": base Oxygen 6 already registers ~22 Essential integration
    // types, so a namespace count would false-positive on every base install.
    $essential_extension = ox_essential_extension_present();

    $companions = ox_companions_state();

    $issues = ox_check_setup_issues([
        'mode' => $mode,
        'license_mode' => ox_license_mode(),
        'oxygen_native_count' => count($native),
        'companions' => $companions,
    ]);

    return [
        'plugin' => [
            'active' => $active,
            'version' => $version,
            'min_supported' => NOVAMIRA_PRO_OXYGEN_MIN_VERSION,
            'min_satisfied' => oxygen_plugin_available(),
            'mode' => $mode,
        ],
        'license' => [
            'has_key' => $license_key !== '',
            'mode' => ox_license_mode(),
            'status' => $status,
        ],
        'elements' => [
            'registry_total' => $registry_total,
            'oxygen_native_count' => count($native),
            'essential_extension_present' => $essential_extension,
            'essential_extension_count' => $essential_extension ? count($essential) : 0,
        ],
        'companions' => $companions,
        'issues' => $issues,
    ];
}

/**
 * Resolve the currently running plugin mode: `oxygen` when Oxygen 6+ is
 * loaded (`BREAKDANCE_MODE=oxygen`), `breakdance` when standalone
 * Breakdance is loaded, `unknown` otherwise.
 */
function ox_current_mode(): string
{
    if (ox_mode_is_oxygen()) {
        return 'oxygen';
    }
    if (defined('BREAKDANCE_MODE')) {
        return 'breakdance';
    }
    return 'unknown';
}

/**
 * Build a list of human-readable issue strings the agent can surface
 * to the user. Empty when the setup is healthy.
 *
 * @param array{
 *   mode: string,
 *   license_mode: string,
 *   oxygen_native_count: int,
 *   companions: array<string, array<string, mixed>>,
 * } $context
 * @return list<string>
 */
function ox_check_setup_issues(array $context): array
{
    $issues = [];
    foreach ([
        ox_issue_plugin_floor(),
        ox_issue_mode($context['mode']),
        ox_issue_license($context['license_mode']),
        ox_issue_no_native_elements($context['oxygen_native_count']),
        ox_issue_companion($context['companions'], slug: 'oxygen_gutenberg'),
        ox_issue_companion($context['companions'], slug: 'oxygen_woocommerce'),
    ] as $issue) {
        if ($issue === null) {
            continue;
        }
        $issues[] = $issue;
    }
    return $issues;
}

/**
 * Diagnostic when Oxygen is not active or is below the supported floor.
 */
function ox_issue_plugin_floor(): ?string
{
    if (oxygen_plugin_available()) {
        return null;
    }
    return sprintf(
        /* translators: %s: minimum supported Oxygen version. */
        __('Oxygen is not active or is below the minimum supported version (%s).', domain: 'novamira-pro'),
        NOVAMIRA_PRO_OXYGEN_MIN_VERSION,
    );
}

/**
 * Diagnostic when the plugin is not running in `BREAKDANCE_MODE=oxygen`.
 */
function ox_issue_mode(string $mode): ?string
{
    if ($mode === 'oxygen') {
        return null;
    }
    return __(
        'The plugin is not running in `BREAKDANCE_MODE=oxygen`. This specialization requires Oxygen 6+; use the Breakdance specialization for standalone Breakdance installs.',
        domain: 'novamira-pro',
    );
}

/**
 * Diagnostic when the license is in `free` mode (Pro-only elements will
 * render broken).
 */
function ox_issue_license(string $license_mode): ?string
{
    if ($license_mode !== 'free') {
        return null;
    }
    return __(
        'License mode is `free`. Pro-only elements and features (advanced forms, dynamic data, design library) will render broken; avoid them or activate a Pro license.',
        domain: 'novamira-pro',
    );
}

/**
 * Diagnostic when the live registry exposes zero native `OxygenElements\*`
 * elements — a signal that the element registry has not loaded (e.g. this
 * ran too early, or the install is broken). Under normal conditions a base
 * Oxygen 6 install always registers the native set.
 */
function ox_issue_no_native_elements(int $oxygen_native_count): ?string
{
    if ($oxygen_native_count > 0) {
        return null;
    }
    return __(
        'No native `OxygenElements\\*` element types are registered. The element registry may not have loaded yet, or the install is incomplete — element abilities will have nothing to build with.',
        domain: 'novamira-pro',
    );
}

/**
 * Diagnostic when a legacy Oxygen Classic companion plugin is active on
 * an Oxygen 6 install. Returns null when the companion is not active
 * (nothing to warn about) or when the slug is not recognised.
 *
 * @param array<string, array<string, mixed>> $companions
 */
function ox_issue_companion(array $companions, string $slug): ?string
{
    if (!isset($companions[$slug])) {
        return null;
    }
    if (($companions[$slug]['active'] ?? false) !== true) {
        return null;
    }
    return match ($slug) {
        'oxygen_gutenberg' => __(
            '`oxygen-gutenberg` companion plugin is active — legacy Oxygen Classic add-on, non-functional on Oxygen 6. Prefer the standard `novamira/gutenberg-*` abilities.',
            domain: 'novamira-pro',
        ),
        'oxygen_woocommerce' => __(
            '`oxygen-woocommerce` companion plugin is active — legacy Oxygen Classic add-on, non-functional on Oxygen 6. Prefer the standard `novamira/woocommerce-*` abilities.',
            domain: 'novamira-pro',
        ),
        default => null,
    };
}
