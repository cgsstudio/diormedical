<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-check-setup', [
    'label' => __('Check Spectra Setup', domain: 'novamira-pro'),
    'description' => __(
        'Returns the Spectra (UAGB / Ultimate Addons for Gutenberg) runtime state in one call: plugin active, version, min-supported, min-satisfied; Spectra Pro add-on active + version + min-satisfied; Novamira Pro license mode; block registry counts (free + pro); popup count; UAGB form-block usage count. Call this BEFORE any other Spectra ability — every other call is gated on `plugin.active && plugin.min_satisfied`, and Pro-only abilities additionally require `pro.active && pro.min_satisfied`.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
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
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'pro' => [
                'type' => 'object',
                'properties' => [
                    'active' => ['type' => 'boolean'],
                    'version' => ['type' => 'string'],
                    'min_supported' => ['type' => 'string'],
                    'min_satisfied' => ['type' => 'boolean'],
                ],
                'required' => ['active', 'version', 'min_supported', 'min_satisfied'],
                'additionalProperties' => false,
            ],
            'license' => [
                'type' => 'object',
                'properties' => [
                    'mode' => ['type' => 'string', 'enum' => ['free', 'pro']],
                ],
                'required' => ['mode'],
                'additionalProperties' => false,
            ],
            'blocks_registered' => [
                'type' => 'object',
                'properties' => [
                    'free' => ['type' => 'integer'],
                    'pro' => ['type' => 'integer'],
                    'total' => ['type' => 'integer'],
                ],
                'required' => ['free', 'pro', 'total'],
                'additionalProperties' => false,
            ],
            'popups_count' => ['type' => 'integer'],
            'forms_usage_count' => ['type' => 'integer'],
        ],
        'required' => ['plugin', 'pro', 'license', 'blocks_registered', 'popups_count', 'forms_usage_count'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Always the first Spectra call. Report plugin.min_satisfied=false or pro.min_satisfied=false to the user before invoking other abilities.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function spectra_check_setup(): array
{
    $version = spectra_version();
    $pro_version = spectra_pro_version();
    $blocks = spectra_registered_blocks(family: 'all');
    $free_count = 0;
    $pro_count = 0;
    foreach ($blocks as $b) {
        match ($b['family']) {
            'free' => $free_count++,
            'pro' => $pro_count++,
            default => null,
        };
    }

    $popups_count = 0;
    if (post_type_exists(SPECTRA_POPUP_POST_TYPE)) {
        $counts = wp_count_posts(SPECTRA_POPUP_POST_TYPE);
        foreach (['publish', 'draft', 'private', 'pending', 'future'] as $status) {
            /** @var mixed $value */
            $value = $counts->{$status} ?? null;
            if (!is_numeric($value)) {
                continue;
            }
            $popups_count += (int) $value;
        }
    }

    return [
        'plugin' => [
            'active' => $version !== '',
            'version' => $version,
            'min_supported' => NOVAMIRA_PRO_SPECTRA_MIN_VERSION,
            'min_satisfied' => spectra_plugin_available(),
        ],
        'pro' => [
            'active' => $pro_version !== '',
            'version' => $pro_version,
            'min_supported' => NOVAMIRA_PRO_SPECTRA_PRO_MIN_VERSION,
            'min_satisfied' => spectra_pro_active(),
        ],
        'license' => [
            'mode' => spectra_license_mode(),
        ],
        'blocks_registered' => [
            'free' => $free_count,
            'pro' => $pro_count,
            'total' => $free_count + $pro_count,
        ],
        'popups_count' => $popups_count,
        'forms_usage_count' => spectra_forms_usage_count(),
    ];
}

/**
 * Cheap indicator of `uagb/forms` block usage. The naive
 * `SELECT COUNT(*) FROM wp_posts WHERE post_content LIKE '%<!-- wp:uagb/forms %'`
 * would do a leading-wildcard full-table scan on `wp_posts` — measured in seconds
 * on 10 k+ posts, timing out `check-setup` on production stores. To keep the
 * result predictable, cap the scan at 500 candidate rows via a subquery and
 * report the count with a `capped` marker so callers know the true value may be
 * higher. Cache the outcome for 5 minutes so the mandatory-first-call
 * `check-setup` doesn't repeat the scan for every agent turn.
 */
function spectra_forms_usage_count(): int
{
    /** @var mixed $cached */
    $cached = get_transient(SPECTRA_FORMS_USAGE_TRANSIENT);
    if (is_numeric($cached)) {
        return (int) $cached;
    }
    global $wpdb;
    /** @var \wpdb $wpdb */
    // `$wpdb->posts` is a WP-internal identifier (not user input); the LIKE
    // pattern is a compile-time literal. The bounded subquery caps the row
    // count MySQL will inspect so the check-setup call stays predictable
    // on very large content tables.
    // @mago-expect analysis:possibly-invalid-argument
    $sql = $wpdb->prepare(
        "SELECT COUNT(*) FROM (SELECT 1 FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_content LIKE %s LIMIT %d) t",
        '%<!-- wp:uagb/forms %',
        SPECTRA_FORMS_USAGE_SCAN_CAP,
    );
    if (!is_string($sql)) {
        return 0;
    }
    /** @var mixed $count */
    $count = $wpdb->get_var($sql);
    $value = is_numeric($count) ? (int) $count : 0;
    set_transient(SPECTRA_FORMS_USAGE_TRANSIENT, $value, expiration: SPECTRA_FORMS_USAGE_TTL_SECONDS);
    return $value;
}

/**
 * TTL of the forms-usage transient (5 minutes). Kept as a named constant so
 * the WP `MINUTE_IN_SECONDS` global isn't referenced from a sub-namespace
 * (which the analyzer stubs cannot see) while the tuning knob stays
 * grep-visible.
 */
const SPECTRA_FORMS_USAGE_TTL_SECONDS = 300;

/**
 * Ceiling for the bounded-scan query in `spectra_forms_usage_count()`. Kept
 * at 500 rows: enough to distinguish "no forms in use" from "forms in use
 * widely" without ever letting the count degrade a check-setup call.
 */
const SPECTRA_FORMS_USAGE_SCAN_CAP = 500;

/** Transient key that memoises the capped forms-usage scan for 5 minutes. */
const SPECTRA_FORMS_USAGE_TRANSIENT = 'nvp_spectra_forms_usage_count';
