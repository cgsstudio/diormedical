<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

/**
 * Ability: Check Bricks setup and report environment status.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-check-setup', [
    'label' => __('Check Bricks Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Bricks environment status: license, supported post types, CSS loading mode with the health of the external CSS file directory (path, writability, Bricks-rendered posts whose per-post CSS file is missing), whether Google Fonts are disabled, the query-filters toggle, active theme and version. Every problem is listed in issues[] with its fix. Run it before building pages.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'verbose' => [
                'type' => 'boolean',
                'description' => 'Include extra diagnostic detail (the active theme name) in the report.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'version' => ['type' => 'string'],
            'theme_name' => ['type' => 'string'],
            'theme_active' => ['type' => 'boolean'],
            'license_valid' => [
                'type' => ['boolean', 'null'],
                'description' => 'Whether the Bricks licence reads as valid. Null means this Bricks does not expose the reader, so the licence was NOT checked — issues[] says so; it is not a claim that the licence is invalid.',
            ],
            'supported_post_types' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
            ],
            'css_loading' => [
                'type' => 'string',
                'description' => '"file" when Bricks serves CSS from external files under uploads/bricks/css (every content write asks Bricks to regenerate the affected post\'s file and reports the outcome in css_file — "regenerated", or "skipped(<reason>)" when it could not be written), "inline" otherwise.',
            ],
            'css_dir' => ['type' => 'string', 'description' => 'Absolute path of the Bricks CSS file directory.'],
            'css_dir_exists' => ['type' => 'boolean'],
            'css_dir_writable' => ['type' => 'boolean'],
            'css_files_missing' => [
                'type' => 'integer',
                'description' => 'File mode only: number of Bricks-rendered posts (up to the first 500) whose per-post CSS file does not exist — the element styles compiled for those pages are not reaching them until the file is generated. Global classes and theme styles come from elsewhere and still apply, so fetch one of the pages to see how much is affected.',
            ],
            'css_files_checked' => [
                'type' => 'integer',
                'description' => 'File mode only: how many Bricks-rendered posts were checked — pages/posts of the enabled post types and Bricks templates (header, footer, section, …).',
            ],
            'render_probe' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'url' => ['type' => 'string'],
                        'reachable' => [
                            'type' => 'boolean',
                            'description' => 'Whether the request reached the site at all. Read it first: when it is false the probe never got a response, so css_linked is null rather than false and nothing here describes the page.',
                        ],
                        'status' => [
                            'type' => 'integer',
                            'description' => 'HTTP status the target answered with; 0 when the request never reached it (reachable false, see error).',
                        ],
                        'css_linked' => [
                            'type' => ['boolean', 'null'],
                            'description' => 'Whether the response links the Bricks CSS files (file mode) or carries the Bricks inline stylesheet (inline mode). NULL when the probe was unreachable — there is no response to look at, and that is not the same as "no CSS".',
                        ],
                        'error' => ['type' => 'string'],
                    ],
                ],
                'description' => 'Lightweight runtime probe: the front page and, when a template is conditioned to specific posts, the first such post. A non-200 status (a PHP error on render, for example) is elevated into issues[].',
            ],
            'css_files_missing_ids' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'description' => 'Up to 20 of the post ids missing their CSS file.',
            ],
            'google_fonts_disabled' => [
                'type' => 'boolean',
                'description' => 'True when Bricks will not load Google Fonts (the disableGoogleFonts key is present — Bricks reads its presence, not its value — or a filter disables them). Theme-style and element font families that are Google fonts then fall back to the system font.',
            ],
            'capabilities' => [
                'type' => 'object',
                'properties' => [
                    'variables_trash' => [
                        'type' => 'boolean',
                        'description' => 'The installed Bricks keeps a variables trash: bricks-delete-variable moves variables there (restorable from the Variable Manager) instead of removing them for good.',
                    ],
                    'css_file_mode' => [
                        'type' => 'boolean',
                        'description' => 'Bricks serves CSS from external files. The abilities that write a post or template report the outcome per post — content writes and page-settings writes in css_file, component body edits per page under affected_posts — while the site-wide design writers (global classes among them) report verification and warnings instead, because their CSS is not a per-post file. bricks-read-css and bricks-render-check read what the front end actually loads.',
                    ],
                    'settings_scan' => [
                        'type' => 'string',
                        'enum' => ['complete', 'partial', 'unavailable'],
                        'description' => 'How much of the Bricks settings screen bricks-set-settings could derive its writable keys from. "complete": every setting the screen defines is accepted. "partial": the screen was read, but it defines settings the scan could not type or could not name, so those are refused unless the site already stores them — issues[] names the ones that can be named. "unavailable": the screen could not be read; only the built-in list and the keys already stored are writable.',
                    ],
                ],
                'description' => 'What the installed Bricks makes possible for these abilities, detected from what exists on the site.',
            ],
            'query_filters_enabled' => [
                'type' => 'boolean',
                'description' => 'Whether "Enable query sort / filter / live search" is on in the Bricks settings. Required BEFORE writing filter-* / pagination elements: filters saved while this is off are never registered for indexing and stay inert.',
            ],
            'issues' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'List of detected configuration issues, each with its fix.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run this before building pages to verify the Bricks environment is ready. Key things to check: license_valid (required to use the visual editor), supported_post_types (must include "page" for pages to be editable), css_loading + css_dir_writable + css_files_missing (in "file" mode every content write asks Bricks to regenerate the post\'s CSS file and reports the outcome in css_file, so a write can come back "skipped(<reason>)" — the directory must be writable, and pre-existing pages may still lack their file), google_fonts_disabled (a theme style built on Google fonts renders in the system font while this is true), and issues (actionable warnings).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Reads one Bricks global setting, or records why it could not be read.
 *
 * This ability exists to tell an agent what the environment can do. Calling
 * a reader that a future build has moved would make it fatal instead — the
 * one thing a diagnostic must not do — so a missing reader becomes an issue
 * in the report, and the caller still gets every other field.
 *
 * @param list<string> $unreadable
 */
function cs_read_setting(string $key, mixed $default_value, array &$unreadable): mixed
{
    if (!class_exists(\Bricks\Database::class) || !method_exists(\Bricks\Database::class, 'get_setting')) {
        $unreadable[] = sprintf(
            'This Bricks does not expose the settings reader these abilities use, so "%s" could not be read and the value reported here is the default. Everything else in this report is unaffected. Call novamira/bricks-get-settings with no arguments to read the stored global settings from the option itself.',
            $key,
        );
        return $default_value;
    }
    try {
        return \Bricks\Database::get_setting($key, $default_value);
    } catch (\Throwable $e) {
        $unreadable[] = sprintf(
            'Reading the Bricks setting "%s" failed (%s), so the value reported here is the default. Call novamira/bricks-get-settings with no arguments to read the stored global settings from the option itself.',
            $key,
            $e->getMessage(),
        );
        return $default_value;
    }
}

/**
 * Whether the Bricks licence reads as valid, or null when this build does not
 * expose the reader — reported rather than guessed either way.
 *
 * @param list<string> $unreadable
 */
function cs_read_license_valid(array &$unreadable): ?bool
{
    if (!class_exists(\Bricks\License::class) || !method_exists(\Bricks\License::class, 'license_is_valid')) {
        $unreadable[] = 'This Bricks does not expose the licence reader these abilities use, so license_valid is null: the licence was NOT checked, and this says nothing about whether it is activated. Open Bricks > Settings > License to see its state.';
        return null;
    }
    try {
        return \Bricks\License::license_is_valid();
    } catch (\Throwable $e) {
        $unreadable[] = sprintf(
            'Reading the Bricks licence state failed (%s), so license_valid is null and the licence was NOT checked. Open Bricks > Settings > License to see its state.',
            $e->getMessage(),
        );
        return null;
    }
}

function bricks_check_setup(array $input): array
{
    $verbose = isset($input['verbose']) && $input['verbose'] === true;

    $theme = wp_get_theme();
    $theme_active = $theme->get_template() === 'bricks';
    // A diagnostic must never be the thing that breaks: when a reader this
    // ability depends on is not there, that becomes an issue it REPORTS.
    $unreadable = [];
    /** @var list<string> $supported */
    $supported = cs_read_setting('postTypes', [], $unreadable);
    $css_loading = bricks_css_loading_is_file() ? 'file' : 'inline';
    /** @var mixed $query_filters_raw */
    $query_filters_raw = cs_read_setting('enableQueryFilters', default_value: false, unreadable: $unreadable);
    // Stored as checkbox truthy ("1"/true) when on, absent when off.
    $query_filters_enabled = $query_filters_raw !== false && $query_filters_raw !== '' && $query_filters_raw !== null;
    $license_valid = cs_read_license_valid($unreadable);
    $google_fonts_disabled = bricks_google_fonts_disabled();

    $issues = [];

    foreach ($unreadable as $note) {
        $issues[] = $note;
    }

    if ($license_valid === false) {
        $issues[] = 'Bricks license is not activated. The visual editor will not be available. Frontend rendering of programmatic content still works.';
    }

    if ($supported === []) {
        $issues[] = 'No post types are enabled for Bricks. The "Edit with Bricks" button will not appear. Use novamira/bricks-set-post-types with post_types=["page","post"].';
    }

    if ($supported !== [] && !in_array(needle: 'page', haystack: $supported, strict: true)) {
        $issues[] = '"page" is not in the supported post types list. Pages cannot be edited with Bricks.';
    }

    if ($google_fonts_disabled) {
        $issues[] = 'Google Fonts are disabled (the disableGoogleFonts global setting is present — Bricks reads its presence, not its value). Google font families set in theme styles or elements fall back to the system font. If this is unintended, remove the key with novamira/bricks-set-settings {"settings": {"disableGoogleFonts": false}} (false removes the key).';
    }

    $result = [
        'version' => defined('BRICKS_VERSION') ? BRICKS_VERSION : 'unknown',
        'theme_active' => $theme_active,
        'license_valid' => $license_valid,
        'supported_post_types' => $supported,
        'css_loading' => $css_loading,
        'google_fonts_disabled' => $google_fonts_disabled,
        'query_filters_enabled' => $query_filters_enabled,
    ];
    $settings_scan = bricks_settings_scan_status();
    $result['capabilities'] = [
        'variables_trash' => bricks_variables_trash_available(),
        'css_file_mode' => $css_loading === 'file',
        'settings_scan' => $settings_scan,
    ];
    if ($settings_scan === 'partial') {
        $issues[] = bricks_settings_scan_gap_message();
    }
    if ($settings_scan === 'unavailable') {
        $issues[] = 'The Bricks settings screen could not be read, so the writable global settings fall back to the built-in list plus the keys this site already stores. A setting outside those is refused by novamira/bricks-set-settings; set it once in the Bricks settings screen, then novamira/bricks-list-settings with scope="global" lists it and it becomes editable here.';
    }
    $result += bricks_check_css_files($css_loading, $issues);
    $result['render_probe'] = bricks_render_probe($css_loading, $issues);
    $result['issues'] = $issues;

    if ($verbose) {
        $result['theme_name'] = $theme->get('Name');
    }

    return $result;
}

/**
 * External CSS file health: directory path / existence / writability and,
 * in file mode, the Bricks-rendered posts whose per-post file is missing.
 * Appends the matching issues (with the fix) to `$issues`.
 *
 * @param list<string> $issues
 * @return array<string, mixed>
 */
function bricks_check_css_files(string $css_loading, array &$issues): array
{
    $file_mode = $css_loading === 'file';
    $dir = bricks_css_dir();
    $exists = $dir !== '' && is_dir($dir);
    $writable = $exists ? wp_is_writable($dir) : $dir !== '' && wp_is_writable(dirname($dir));
    $out = [
        'css_dir' => $dir,
        'css_dir_exists' => $exists,
        'css_dir_writable' => $writable,
    ];

    if (!$file_mode) {
        return $out;
    }

    if (!$writable) {
        $issues[] = sprintf(
            'Bricks serves CSS from external files but %s is not writable, so no per-post CSS file can be created or rewritten there. Files that already exist stay readable and keep being served, but they now go stale: a page edited from here keeps the styles of its previous version, and a page that has no file yet gets none of its per-post element styles (global classes and theme styles come from elsewhere and still apply). A write that touches a post reports its own outcome — in css_file, or per page under affected_posts for a component body edit — so read that to see which pages were affected. Make the uploads directory writable by the web server, then regenerate the CSS files from Bricks > Settings > Performance (or re-save each page).',
            $dir,
        );
    }

    $missing = bricks_count_missing_post_css_files();
    $out['css_files_checked'] = $missing['checked'];
    $out['css_files_missing'] = $missing['missing'];
    $out['css_files_missing_ids'] = $missing['missing_ids'];
    if ($missing['missing'] > 0) {
        $issues[] = sprintf(
            '%d of %d Bricks-rendered posts%s have no per-post CSS file under %s (first ids: %s), so they render without their element styles (global classes and theme styles come from elsewhere and still apply). A write made through these abilities asks Bricks to regenerate the file for the post it touches and reports what happened in css_file, so check that rather than assuming; for the rest, regenerate the CSS files from Bricks > Settings > Performance (CSS loading method) or re-save each page in the builder.',
            $missing['missing'],
            $missing['checked'],
            $missing['truncated'] ? ' (first 500 checked)' : '',
            $dir,
            implode(', ', array_map('strval', $missing['missing_ids'])),
        );
    }

    return $out;
}

/**
 * Runtime probe: fetches the front page — and the first post a template is
 * conditioned to, when there is one — through a loopback request and reports
 * the HTTP status plus whether the Bricks CSS is linked. Persisted trees can
 * be valid and still fail to render (a settings value Bricks cannot iterate,
 * a fatal in a code element), which only a real request reveals. At most two
 * requests, short timeouts; a non-200 becomes an issue.
 *
 * The request base can be redirected with the `novamira/bricks/render-probe-base`
 * filter for setups where the site's own host is not reachable from the
 * server (the Host header always carries the site's host).
 *
 * @param list<string> $issues
 * @return list<array{url: string, reachable: bool, status: int, css_linked: bool|null, error?: string}>
 */
function bricks_render_probe(string $css_loading, array &$issues): array
{
    $urls = [home_url('/')];
    $conditioned = bricks_first_template_conditioned_post();
    if ($conditioned !== null) {
        $permalink = get_permalink($conditioned);
        if (is_string($permalink) && $permalink !== '' && $permalink !== $urls[0]) {
            $urls[] = $permalink;
        }
    }

    $probes = [];
    foreach ($urls as $url) {
        $probe = bricks_probe_url($url, $css_loading);
        $probes[] = $probe;
        if ($probe['reachable'] !== true) {
            // Could not REACH the target — that is not the target failing, and
            // saying so would send an agent hunting a bug that is not there.
            $issues[] = sprintf(
                'This site could not open its own front end at %s from the server%s, so nothing about how pages render was checked. This is a network fact, not a verdict on the pages: they may be perfectly healthy. It happens when the public URL does not resolve from inside the host (a container mapping the site to a port on the outside, or a firewall). Open the URL in a browser to see the real state; the site owner can point the probe at an address the server can reach through the novamira/bricks/render-probe-base filter.',
                $url,
                isset($probe['error']) ? ' (' . $probe['error'] . ')' : '',
            );
            continue;
        }
        if ($probe['status'] !== 200) {
            $issues[] = sprintf(
                'A request to %s returned HTTP %d. The target answered, so this is the page failing, not the network: something in the rendered content fails at runtime (for example a settings value in a shape Bricks cannot iterate, or a fatal in a code element) — read the page with novamira/bricks-get-content and fix the last write, or check the PHP error log.',
                $url,
                $probe['status'],
            );
            continue;
        }
        if ($probe['css_linked'] === false) {
            $issues[] = sprintf(
                'The response of %s does not carry the Bricks CSS (%s), so the element styles this site compiles per post are not reaching it. Other sources (global classes, theme styles) can still apply, so fetch the page to see how much of it is affected. Regenerate the CSS files from Bricks > Settings > Performance and check that the theme is active on the front end.',
                $url,
                $css_loading === 'file' ? 'no uploads/bricks/css file linked' : 'no inline Bricks stylesheet',
            );
        }
    }

    return $probes;
}

/**
 * One loopback request; the HTTP status, whether the Bricks CSS is present in
 * the response, and the transport error when the request itself failed.
 *
 * @return array{url: string, reachable: bool, status: int, css_linked: bool|null, error?: string}
 */
function bricks_probe_url(string $url, string $css_loading): array
{
    $fetched = bricks_loopback_fetch($url);
    if (isset($fetched['error'])) {
        // Nothing came back, so there is nothing to conclude: css_linked is
        // null, not false, because "no response" is not "no CSS".
        return [
            'url' => $url,
            'reachable' => false,
            'status' => 0,
            'css_linked' => null,
            'error' => $fetched['error'],
        ];
    }

    return [
        'url' => $url,
        'reachable' => true,
        'status' => $fetched['status'],
        'css_linked' => bricks_body_links_bricks_css($fetched['body'], $css_loading),
    ];
}

/**
 * Fetches a front-end URL of this site from the server, the way a visitor
 * would: short timeout, the site's own Host header, and a request base that
 * the `novamira/bricks/render-probe-base` filter can redirect for setups
 * where the site's host is not reachable from the server itself.
 *
 * @return array{status: int, body: string, error?: string}
 */
function bricks_loopback_fetch(string $url): array
{
    /** @var mixed $base */
    $base = apply_filters('novamira/bricks/render-probe-base', home_url());
    $home = rtrim(home_url(), characters: '/');
    $request_url = $url;
    if (is_string($base) && $base !== '' && rtrim($base, characters: '/') !== $home) {
        $request_url = rtrim($base, characters: '/') . '/' . ltrim(substr($url, strlen($home)), characters: '/');
    }
    $host = (string) wp_parse_url(home_url(), PHP_URL_HOST);
    $port = (int) wp_parse_url(home_url(), PHP_URL_PORT);
    $headers = ['Host' => $port > 0 ? sprintf('%s:%d', $host, $port) : $host];

    $external = static fn(): bool => true;
    add_filter('http_request_host_is_external', $external);
    try {
        $response = wp_remote_get($request_url, [
            'timeout' => 8,
            'redirection' => 3,
            'sslverify' => false,
            'headers' => $headers,
        ]);
    } finally {
        remove_filter('http_request_host_is_external', $external);
    }

    if (is_wp_error($response)) {
        return ['status' => 0, 'body' => '', 'error' => $response->get_error_message()];
    }

    return [
        'status' => (int) wp_remote_retrieve_response_code($response),
        'body' => wp_remote_retrieve_body($response),
    ];
}

/**
 * Whether a front-end response carries the Bricks CSS: a linked file under
 * bricks/css in file mode, the inline Bricks stylesheet otherwise.
 */
function bricks_body_links_bricks_css(string $body, string $css_loading): bool
{
    return $css_loading === 'file'
        ? str_contains($body, 'bricks/css/')
        : str_contains($body, 'bricks-frontend-inline-inline-css');
}

/**
 * Id of the first post a Bricks template is conditioned to through an
 * `ids` condition, or null when no template targets specific posts.
 */
function bricks_first_template_conditioned_post(): ?int
{
    /** @var list<int> $template_ids */
    $template_ids = get_posts([
        'post_type' => BRICKS_DB_TEMPLATE_SLUG,
        'post_status' => 'publish',
        'posts_per_page' => 50,
        'fields' => 'ids',
        'no_found_rows' => true,
        'suppress_filters' => true,
    ]);
    foreach ($template_ids as $template_id) {
        /** @var mixed $settings */
        $settings = get_post_meta($template_id, BRICKS_DB_TEMPLATE_SETTINGS, single: true);
        if (!is_array($settings) || !is_array($settings['templateConditions'] ?? null)) {
            continue;
        }
        /** @var mixed $condition */
        foreach ($settings['templateConditions'] as $condition) {
            if (
                !is_array($condition)
                || ($condition['main'] ?? null) !== 'ids'
                || !is_array($condition['ids'] ?? null)
            ) {
                continue;
            }
            /** @var mixed $first */
            foreach ($condition['ids'] as $first) {
                if (is_numeric($first) && (int) $first > 0) {
                    return (int) $first;
                }
            }
        }
    }

    return null;
}
