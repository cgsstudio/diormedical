<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricksforge;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

const BRF_DEFAULT_LIST_LIMIT = 50;

const BRF_MAX_LIST_LIMIT = 500;

const BRF_EMAIL_CANVAS_WARN_BYTES = 262_144;

const BRF_MAX_LIST_RESPONSE_BYTES = 131_072;

const BRF_LARGE_GET_WARN_BYTES = 262_144;

/**
 * @param array<string, mixed> $data
 */
function brf_error(string $code, string $message, array $data = []): WP_Error
{
    return new WP_Error(code: $code, message: $message, data: $data);
}

/**
 * Lowest Bricksforge version that knows each tool ID added after the original
 * catalog (0-18). Tool IDs missing here exist in every supported version.
 * Bricksforge ignores IDs it does not know, so these gate availability.
 */
const BRF_TOOL_MIN_VERSIONS = [
    19 => '4.0',
    20 => '4.0',
    21 => '4.0',
    22 => '4.0',
    23 => '4.0',
];

/**
 * Canonical tool ID → tool descriptor map for the `brf_activated_tools`
 * option. Bricksforge stores activation as a flat array of plain integers and
 * ships NO id→name registry in PHP — the meaning of each ID is implicit in
 * the module→id switch in plugin.php (is_module_active), scattered
 * `in_array(N, get_option('brf_activated_tools'))` checks, and the admin Vue
 * app. Slugs are a stable public contract: `global-classes` (10) stayed on the
 * backups tool when Bricksforge split the Global Classes management UI out as
 * tool 23, so that one is `global-classes-manager`. Where tools 19 and 20
 * exist, tool 6 (the Panel) only runs Events: timelines need 19 and node flows
 * need 20. Where they do not exist, tool 6 runs all three. Storage is the same
 * either way: events (`instances`) and timelines in `brf_panel`, node flows in
 * `brf_panel_nodes`. Whether the installed Bricksforge knows an ID is
 * decided by brf_tool_available().
 *
 * @return array<int, array{slug: string, name: string}>
 */
function brf_tool_map(): array
{
    return [
        0 => ['slug' => 'custom-fonts', 'name' => 'Custom Fonts Bulk Upload'],
        1 => ['slug' => 'animations', 'name' => 'Animation Controls (per-element GSAP animations)'],
        2 => ['slug' => 'conditional-logic', 'name' => 'Element Conditions'],
        3 => ['slug' => 'mega-menu', 'name' => 'Mega Menu'],
        4 => ['slug' => 'maintenance', 'name' => 'Maintenance Mode'],
        5 => ['slug' => 'popups', 'name' => 'Popups'],
        6 => [
            'slug' => 'panel',
            'name' => brf_tool_available(19)
                ? 'Bricksforge Panel (Events)'
                : 'Bricksforge Panel (Events, GSAP timelines, Node Editor)',
        ],
        7 => ['slug' => 'scroll-smoother', 'name' => 'Scroll Smoother'],
        8 => ['slug' => 'terminal', 'name' => 'Bricksforge Terminal'],
        9 => ['slug' => 'backend-designer', 'name' => 'Backend Designer'],
        10 => ['slug' => 'global-classes', 'name' => 'Global Classes Backups'],
        11 => ['slug' => 'form-submissions', 'name' => 'Form Submissions (Pro Forms)'],
        12 => ['slug' => 'dynamic-data', 'name' => 'Dynamic Data Tags'],
        13 => ['slug' => 'email-designer', 'name' => 'Email Designer'],
        14 => ['slug' => 'ai', 'name' => 'Bricksforge Assistant (AI)'],
        15 => ['slug' => 'parallax', 'name' => 'Parallax Effects'],
        16 => ['slug' => 'page-transitions', 'name' => 'Page Transitions'],
        17 => ['slug' => 'admin-pages', 'name' => 'Admin Pages'],
        18 => ['slug' => 'external-api-loops', 'name' => 'API Query Builder (External API Loops)'],
        19 => ['slug' => 'animation-panel', 'name' => 'Animation Panel (GSAP timelines)'],
        20 => ['slug' => 'node-editor', 'name' => 'Node Editor'],
        21 => ['slug' => 'builder-customizer', 'name' => 'Builder Customizer (builder roles and permissions)'],
        22 => ['slug' => 'white-label', 'name' => 'White Label'],
        23 => ['slug' => 'global-classes-manager', 'name' => 'Global Classes (class sets manager)'],
    ];
}

/**
 * Whether the installed Bricksforge knows a tool ID. IDs outside
 * BRF_TOOL_MIN_VERSIONS are always available; the newer ones need the
 * matching Bricksforge version, because an older Bricksforge silently ignores
 * them in brf_activated_tools.
 */
function brf_tool_available(int $tool_id): bool
{
    $min_version = BRF_TOOL_MIN_VERSIONS[$tool_id] ?? null;
    if ($min_version === null) {
        return true;
    }

    $version = brf_version();

    return $version !== '' && version_compare($version, $min_version, operator: '>=');
}

/**
 * Canonical custom-element ID → descriptor map for the
 * `brf_activated_elements` option. A SEPARATE numbering space from the tool
 * IDs (element 5 = Pro Forms bundle, but tool 5 = Popups). One ID can
 * register many Bricks elements: ID 5 activates the form container, the
 * steps container, and every Pro Forms field element in bulk. ID 13
 * (API Query Builder Load More) auto-activates whenever tool 18 is active,
 * regardless of the array contents. Recovered from
 * includes/elements/Elements.php of the 3.1.8.6 source.
 *
 * @return array<int, array{slug: string, name: string, bricks_elements: list<string>}>
 */
function brf_element_map(): array
{
    return [
        0 => ['slug' => 'flip-everything', 'name' => 'Flip Everything', 'bricks_elements' => ['brf-flip']],
        1 => ['slug' => 'font-awesome', 'name' => 'Font Awesome', 'bricks_elements' => ['brf-font-awesome']],
        2 => [
            'slug' => 'before-and-after',
            'name' => 'Before & After',
            'bricks_elements' => ['brf-before-and-after'],
        ],
        3 => ['slug' => 'popup-trigger', 'name' => 'Popup Trigger', 'bricks_elements' => ['brf-popup-trigger']],
        4 => ['slug' => 'table-of-contents', 'name' => 'Table of Contents', 'bricks_elements' => ['brf-toc']],
        5 => [
            'slug' => 'pro-forms',
            // brf-pro-forms is the nestable form container. brf-pro-forms-steps
            // is a standalone multi-step INDICATOR (not a container) — real
            // steps are brf-pro-forms-field-step markers + -next/-previous
            // placed directly inside brf-pro-forms.
            'name' => 'Pro Forms (form container + step indicator + all field elements)',
            'bricks_elements' => ['brf-pro-forms', 'brf-pro-forms-steps', 'brf-pro-forms-field-*'],
        ],
        6 => ['slug' => 'scroll-video', 'name' => 'Scroll Video', 'bricks_elements' => ['brf-scroll-video']],
        7 => ['slug' => 'option', 'name' => 'Option', 'bricks_elements' => ['brf-option']],
        8 => ['slug' => 'three-js', 'name' => 'Three JS', 'bricks_elements' => ['brf-three-js']],
        9 => ['slug' => 'lottie', 'name' => 'Lottie', 'bricks_elements' => ['brf-lottie']],
        10 => ['slug' => 'tour', 'name' => 'Tour', 'bricks_elements' => ['brf-tour']],
        11 => ['slug' => 'nestable-button', 'name' => 'Nestable Button', 'bricks_elements' => ['brf-nestable-button']],
        12 => [
            'slug' => 'add-to-calendar-button',
            'name' => 'Add to Calendar Button',
            'bricks_elements' => ['brf-add-to-calender-button'],
        ],
        13 => [
            'slug' => 'api-query-builder-load-more',
            'name' => 'API Query Builder: Load More',
            'bricks_elements' => ['brf-api-query-builder-load-more'],
        ],
    ];
}

/**
 * Normalize a raw option value to an integer ID, tolerating the numeric
 * strings the upstream raw-json_decode save path can persist.
 */
function brf_int_or_null(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_float($value)) {
        return brf_integral_float($value);
    }

    return brf_numeric_string_int($value);
}

/**
 * A numeric string as int, anything else as null.
 */
function brf_numeric_string_int(mixed $value): ?int
{
    if (is_string($value) && is_numeric($value)) {
        return (int) $value;
    }

    return null;
}

/**
 * An integral float (5.0) as int, a fractional one (5.5) as null.
 */
function brf_integral_float(float $value): ?int
{
    $in_range = is_finite($value) && $value <= (float) PHP_INT_MAX && $value >= (float) PHP_INT_MIN;
    if (!$in_range || $value !== floor($value)) {
        return null;
    }

    return (int) $value;
}

/**
 * Read a Bricksforge option's RAW stored value, bypassing the `option_<name>`
 * / `pre_option_<name>` filters. Read-modify-write paths MUST use this for the
 * base read: a caching/config plugin filtering a `brf_*` key would otherwise
 * make our write persist the FILTERED value and clobber what is really in the
 * DB (upstream is immune because its save handlers take the value from the
 * request body, never get_option). Falls back to get_option only when the
 * direct row read is unavailable.
 */
function brf_raw_option(string $name): mixed
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = $wpdb->options;
    // @mago-expect analyzer:possibly-invalid-argument
    $prepared = $wpdb->prepare("SELECT option_value FROM {$table} WHERE option_name = %s", $name);
    /** @var mixed $raw */
    $raw = is_string($prepared) ? $wpdb->get_var($prepared) : null;
    if (!is_string($raw)) {
        // Row genuinely absent, or a backend where the direct read failed:
        // fall back to the filtered value rather than inventing an empty write.
        return get_option($name);
    }

    return maybe_unserialize($raw);
}

/**
 * Persist a Bricksforge option, first dropping the stale options-cache entry
 * so update_option's internal change-detection compares against the true DB
 * value. Without this a read-modify-write computed from the RAW DB value (see
 * brf_raw_option) can silently no-op: update_option reads its OWN cached copy
 * (primed before a concurrent writer touched the row), believes the value is
 * unchanged, and skips the DB write — a lost write under the very lock meant
 * to prevent it.
 */
function brf_update_option(string $name, mixed $value): void
{
    wp_cache_delete($name, group: 'options');
    wp_cache_delete('alloptions', group: 'options');
    update_option($name, $value);
}

/**
 * Extract the `id` from one `brf_activated_elements` / `brf_tool_settings`
 * row. Rows are `{id: N}` stdClass objects (json_decode without assoc in the
 * upstream save path); array-shaped rows are tolerated for the same reason
 * numeric strings are.
 */
function brf_row_id(mixed $row): ?int
{
    if (is_object($row)) {
        return brf_int_or_null(get_object_vars($row)['id'] ?? null);
    }
    if (is_array($row)) {
        return brf_int_or_null($row['id'] ?? null);
    }

    return null;
}

/**
 * A string property of an object ('' when missing or non-string). Avoids
 * arrow-access on generic `object` types.
 */
function brf_obj_str(object $obj, string $key): string
{
    /** @var mixed $value */
    $value = get_object_vars($obj)[$key] ?? null;

    return is_string($value) ? $value : '';
}

/**
 * The activated tool IDs from `brf_activated_tools` (flat int array) as a
 * normalized list of integers.
 *
 * @return list<int>
 */
function brf_activated_tool_ids(): array
{
    /** @var mixed $raw */
    $raw = get_option('brf_activated_tools');
    if (!is_array($raw)) {
        return [];
    }

    $ids = [];
    /** @var mixed $value */
    foreach ($raw as $value) {
        $id = brf_int_or_null($value);
        if ($id === null) {
            continue;
        }
        $ids[] = $id;
    }

    return array_values(array_unique($ids));
}

/**
 * The activated custom-element IDs from `brf_activated_elements` (array of
 * `{id: N}` OBJECTS — never flat integers) as a normalized list of integers.
 *
 * @return list<int>
 */
function brf_activated_element_ids(): array
{
    /** @var mixed $raw */
    $raw = get_option('brf_activated_elements');
    if (!is_array($raw)) {
        return [];
    }

    $ids = [];
    /** @var mixed $row */
    foreach ($raw as $row) {
        $id = brf_row_id($row);
        if ($id === null) {
            continue;
        }
        $ids[] = $id;
    }

    return array_values(array_unique($ids));
}

/**
 * Whether a Bricksforge tool is active, by ID. Replicates upstream's exact
 * check — a loose in_array() on the RAW option — so entries our normalizer
 * cannot map (floats, booleans) count the same way Bricksforge counts them.
 */
function brf_tool_active(int $tool_id): bool
{
    /** @var mixed $raw */
    $raw = get_option('brf_activated_tools');
    if (!is_array($raw)) {
        return false;
    }

    // @mago-expect lint:strict-behavior
    return in_array($tool_id, $raw);
}

/**
 * The tool IDs that carry a settings blob in `brf_tool_settings` (an array
 * of `{id, settings}` objects — same object-shape caveat as the elements).
 *
 * @return list<int>
 */
function brf_tool_ids_with_settings(): array
{
    /** @var mixed $raw */
    $raw = get_option('brf_tool_settings');
    if (!is_array($raw)) {
        return [];
    }

    $ids = [];
    /** @var mixed $row */
    foreach ($raw as $row) {
        $id = brf_row_id($row);
        if ($id === null) {
            continue;
        }
        $ids[] = $id;
    }

    return array_values(array_unique($ids));
}

/**
 * Clamp a list `limit` input to [1, BRF_MAX_LIST_LIMIT].
 */
function brf_clamp_limit(mixed $limit, int $default = BRF_DEFAULT_LIST_LIMIT): int
{
    if (!is_int($limit)) {
        return $default;
    }

    return max(1, min($limit, BRF_MAX_LIST_LIMIT));
}

/**
 * Resolve a tool reference (numeric id or slug) against the tool map.
 *
 * @param array<string, mixed> $input
 * @return int|WP_Error
 */
function brf_resolve_tool_id(array $input): int|WP_Error
{
    return brf_resolve_catalog_id($input, brf_tool_map(), what: 'tool');
}

/**
 * Resolve a custom-element reference (numeric id or slug) against the
 * element map.
 *
 * @param array<string, mixed> $input
 * @return int|WP_Error
 */
function brf_resolve_element_id(array $input): int|WP_Error
{
    return brf_resolve_catalog_id($input, brf_element_map(), what: 'element');
}

/**
 * Shared id/slug resolver for the two activation catalogs.
 *
 * @param array<string, mixed> $input
 * @param array<int, array{slug: string, name: string}|array{slug: string, name: string, bricks_elements: list<string>}> $map
 * @return int|WP_Error
 */
function brf_resolve_catalog_id(array $input, array $map, string $what): int|WP_Error
{
    $id = brf_int_or_null($input['id'] ?? null);
    $slug = isset($input['slug']) && is_string($input['slug']) ? $input['slug'] : '';

    if ($id === null && $slug === '') {
        return brf_error(
            code: 'bricksforge_invalid_input',
            message: sprintf('Provide the %1$s to target: either id (integer) or slug (string).', $what),
            data: ['status' => 400],
        );
    }

    if ($id === null) {
        foreach ($map as $map_id => $entry) {
            if ($entry['slug'] === $slug) {
                return $map_id;
            }
        }

        return brf_error(
            code: 'bricksforge_unknown_' . $what,
            message: sprintf('Unknown %1$s slug "%2$s". List the catalog first to see valid slugs.', $what, $slug),
            data: ['status' => 404],
        );
    }

    if (!isset($map[$id])) {
        return brf_error(
            code: 'bricksforge_unknown_' . $what,
            message: sprintf(
                'Unknown %1$s id %2$d. Known ids: %3$s.',
                $what,
                $id,
                implode(', ', array_map('strval', array_keys($map))),
            ),
            data: ['status' => 404],
        );
    }

    return $id;
}

/**
 * The raw `brf_activated_tools` entries exactly as stored. Upstream saves a
 * raw json_decode of whatever the admin app (or a settings import) sent, so
 * writes must preserve entries verbatim — normalizing would silently drop
 * values upstream's loose in_array still honors.
 *
 * @return list<mixed>
 */
function brf_raw_tool_rows(): array
{
    /** @var mixed $raw */
    $raw = brf_raw_option('brf_activated_tools');
    if (!is_array($raw)) {
        return [];
    }

    return array_values($raw);
}

/**
 * The raw `brf_activated_elements` rows exactly as stored (stdClass rows,
 * possibly carrying per-element `settings` such as Pro Forms captcha keys).
 * Writes must preserve unrelated rows verbatim.
 *
 * @return list<mixed>
 */
function brf_raw_element_rows(): array
{
    /** @var mixed $raw */
    $raw = brf_raw_option('brf_activated_elements');
    if (!is_array($raw)) {
        return [];
    }

    return array_values($raw);
}

/**
 * Append a warning string to a successful ability response (`warnings[]`).
 * Degraded-but-successful outcomes must reach the agent in the response —
 * never only error_log.
 *
 * @param array<string, mixed> $response
 * @return array<string, mixed>
 */
function brf_append_warning(array $response, string $warning): array
{
    if ($warning === '') {
        return $response;
    }
    /** @var mixed $existing */
    $existing = $response['warnings'] ?? [];
    $response['warnings'] = array_merge(is_array($existing) ? $existing : [], [$warning]);

    return $response;
}

/**
 * The raw `brf_popups` rows exactly as stored (stdClass configs saved by the
 * admin app through a raw json_decode). Writes must preserve this
 * array-of-objects shape.
 *
 * @return list<object>
 */
function brf_raw_popups(): array
{
    /** @var mixed $raw */
    $raw = brf_raw_option('brf_popups');
    if (!is_array($raw)) {
        return [];
    }

    $rows = [];
    /** @var mixed $row */
    foreach ($raw as $row) {
        if (!is_object($row)) {
            continue;
        }
        $rows[] = $row;
    }

    return $rows;
}

/**
 * Persist the popups list, preserving the array-of-objects shape.
 *
 * @param list<object> $popups
 */
function brf_write_popups(array $popups): void
{
    brf_update_option('brf_popups', array_values($popups));
}

/**
 * The popup id of one raw popup row ('' when absent). Popup ids are
 * hex-uid strings generated by the admin app (or wp_generate_uuid4 here).
 */
function brf_popup_id(object $popup): string
{
    /** @var mixed $id */
    $id = get_object_vars($popup)['id'] ?? null;
    if (is_string($id)) {
        return $id;
    }
    if (is_int($id)) {
        return (string) $id;
    }

    return '';
}

/**
 * A popup config as a plain associative array for ability output.
 *
 * @return array<string, mixed>
 */
function brf_popup_to_output(object $popup): array
{
    /** @var mixed $decoded */
    $decoded = json_decode((string) wp_json_encode($popup), associative: true);

    /** @var array<string, mixed> */
    return is_array($decoded) ? $decoded : [];
}

/**
 * Whether a stored popup object can be faithfully represented as an array
 * (json round-trip succeeds). Configs deeper than the json depth limit fail;
 * edit-popup must refuse rather than silently overwrite such a row.
 */
function brf_popup_is_representable(object $popup): bool
{
    /** @var mixed $decoded */
    $decoded = json_decode((string) wp_json_encode($popup), associative: true);

    return is_array($decoded) && $decoded !== [];
}

/**
 * REST-truthy boolean coercion. The Abilities API schema layer accepts
 * 1/0/"true"/"false" for boolean properties, so a strict `=== true` check
 * would silently invert REST-valid truthy inputs. Mirrors
 * rest_sanitize_boolean(): the strings "false" and "0" are false, any other
 * value is cast.
 */
function brf_bool(mixed $value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    if (is_string($value)) {
        return !in_array(strtolower($value), ['false', '0', ''], strict: true);
    }
    if (is_int($value) || is_float($value)) {
        return $value !== 0 && $value !== 0.0;
    }
    if (is_array($value)) {
        return $value !== [];
    }

    return $value !== null;
}

/**
 * Verify the active object cache drop-in implements `wp_cache_add` with real
 * atomicity (returns false when the key already exists). Cached per request.
 */
function brf_cache_add_is_atomic(): bool
{
    /** @var bool|null $verified */
    static $verified = null;
    if ($verified !== null) {
        return $verified;
    }
    $key = 'brf_atomic_probe_' . bin2hex(random_bytes(length: 4));
    $group = 'bricksforge_lock';
    $first = wp_cache_add(key: $key, data: 1, group: $group, expire: 5);
    $second = wp_cache_add(key: $key, data: 2, group: $group, expire: 5);
    wp_cache_delete($key, $group);

    return $verified = $first === true && $second === false;
}

/**
 * Acquire an exclusive lock for one of the Bricksforge option slots (tools,
 * elements, popups, unread). Every ability write path is a read-modify-write
 * on a whole wp_options row, so two concurrent MCP calls would otherwise
 * lose one write. Key is blog-scoped (multisite GET_LOCK namespace is
 * server-wide). Uses atomic wp_cache_add when a verified persistent object
 * cache is present, MySQL GET_LOCK otherwise; on backends without GET_LOCK
 * (SQLite dev installs) proceeds unlocked, like the other specializations.
 */
function brf_acquire_lock(string $slot): string|WP_Error
{
    $blog_id = function_exists('get_current_blog_id') ? get_current_blog_id() : 1;
    $key = 'brf_lock_' . $blog_id . '_' . $slot;

    if (wp_using_ext_object_cache() && brf_cache_add_is_atomic()) {
        $group = 'bricksforge_lock';
        $token = bin2hex(random_bytes(length: 8));
        for ($attempt = 0; $attempt < 10; $attempt++) {
            if (wp_cache_add($key, $token, $group, expire: 5)) {
                return 'cache:' . $key . '|' . $token;
            }
            usleep(microseconds: 50_000);
        }

        return brf_error(
            code: 'bricksforge_locked',
            message: 'Could not acquire the write lock; another write on the same Bricksforge data is in progress. Retry the call.',
            data: ['status' => 409],
        );
    }

    global $wpdb;
    /** @var \wpdb $wpdb */
    $sql = (string) $wpdb->prepare('SELECT GET_LOCK(%s, %d) AS got', $key, 5);
    /** @var mixed $got */
    $got = $wpdb->get_var($sql);
    if ((string) $got === '1') {
        return 'sql:' . $key;
    }
    if ((string) $got === '0') {
        return brf_error(
            code: 'bricksforge_locked',
            message: 'Could not acquire the write lock; another write on the same Bricksforge data is in progress. Retry the call.',
            data: ['status' => 409],
        );
    }

    // Not a MySQL-compatible GET_LOCK backend (SQLite dev/CI): run unlocked.
    return 'none:' . $key;
}

/**
 * Release a lock acquired via brf_acquire_lock.
 */
function brf_release_lock(string $token): void
{
    if (str_starts_with($token, 'cache:')) {
        $payload = substr(string: $token, offset: strlen('cache:'));
        $sep = strpos(haystack: $payload, needle: '|');
        if ($sep === false) {
            return;
        }
        $key = substr(string: $payload, offset: 0, length: $sep);
        $cache_token = substr(string: $payload, offset: $sep + 1);
        /** @var mixed $current */
        $current = wp_cache_get($key, group: 'bricksforge_lock');
        if (is_string($current) && hash_equals($current, $cache_token)) {
            wp_cache_delete($key, group: 'bricksforge_lock');
        }
        return;
    }
    if (str_starts_with($token, 'sql:')) {
        $name = substr(string: $token, offset: strlen('sql:'));
        global $wpdb;
        /** @var \wpdb $wpdb */
        $wpdb->query((string) $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $name));
    }
}

/**
 * Run a callable under the `$slot` write lock, releasing on success or
 * failure.
 *
 * @template T
 * @param callable(): T $fn
 * @return T|WP_Error
 */
function brf_with_lock(string $slot, callable $fn): mixed
{
    $token = brf_acquire_lock($slot);
    if ($token instanceof WP_Error) {
        return $token;
    }
    try {
        return $fn();
    } finally {
        brf_release_lock($token);
    }
}

/**
 * The raw `brf_email_designer_data` rows exactly as stored — an array of
 * template objects `{id, name, conditions[], useTheme, theme, canvas, status}`
 * (stdClass after get_option; the `canvas` holds the visual body and the
 * `emailSubject`). Read via brf_raw_option so a caching filter cannot make a
 * write clobber the real store.
 *
 * @return list<\stdClass>
 */
function brf_raw_email_templates(): array
{
    /** @var mixed $raw */
    $raw = brf_raw_option('brf_email_designer_data');
    if (!is_array($raw)) {
        return [];
    }

    $rows = [];
    /** @var mixed $row */
    foreach ($raw as $row) {
        if ($row instanceof \stdClass) {
            $rows[] = $row;
            continue;
        }
        // Legacy/imported templates can be associative arrays; the plugin
        // reads both shapes, but our write rewrites the whole option, so a
        // dropped array row would be lost forever. Coerce it to the canonical
        // stdClass instead. (Scalar/garbage rows the plugin cannot use either
        // are the only thing not carried over.)
        if (is_array($row)) {
            /** @var mixed $coerced */
            $coerced = json_decode((string) wp_json_encode($row));
            if ($coerced instanceof \stdClass) {
                $rows[] = $coerced;
            }
        }
    }

    return $rows;
}

/**
 * Persist the email-template list, preserving the array-of-objects shape and
 * busting the options cache first (see brf_update_option).
 *
 * @param list<\stdClass> $templates
 */
function brf_write_email_templates(array $templates): void
{
    brf_update_option('brf_email_designer_data', array_values($templates));
}

/**
 * One email template's id ('' when absent). IDs are hex-uid strings from the
 * admin app (or wp_generate_uuid4 here for duplicates).
 */
function brf_email_template_id(object $template): string
{
    /** @var mixed $id */
    $id = get_object_vars($template)['id'] ?? null;
    if (is_string($id)) {
        return $id;
    }
    if (is_int($id)) {
        return (string) $id;
    }

    return '';
}

/**
 * The subject stored on a template (`canvas.emailSubject`), or '' when unset.
 */
function brf_email_template_subject(object $template): string
{
    $vars = get_object_vars($template);
    /** @var mixed $canvas */
    $canvas = $vars['canvas'] ?? null;
    if (is_object($canvas)) {
        /** @var mixed $subject */
        $subject = get_object_vars($canvas)['emailSubject'] ?? null;

        return is_string($subject) ? $subject : '';
    }
    if (is_array($canvas)) {
        return is_string($canvas['emailSubject'] ?? null) ? $canvas['emailSubject'] : '';
    }

    return '';
}

/**
 * The `type` string of one condition row ('' when absent).
 */
function brf_condition_type(mixed $condition): string
{
    /** @var mixed $type */
    $type = null;
    if (is_object($condition)) {
        // @mago-expect analyzer:mixed-assignment
        $type = get_object_vars($condition)['type'] ?? null;
    }
    if (is_array($condition)) {
        // @mago-expect analyzer:mixed-assignment
        $type = $condition['type'] ?? null;
    }

    return is_string($type) ? $type : '';
}

/**
 * The condition `type` strings a template overrides. Each is the EXACT hook
 * name Bricksforge matches (===): a WordPress mail filter like
 * `retrieve_password_notification_email` / `wp_new_user_notification_email`,
 * a WooCommerce one like `woocommerce_new_order`, or a Bricks form type
 * (`bricks_form` / `bricks_form_confirmation` with a formId, or
 * `all_bricks_forms` / `all_bricks_forms_confirmations`). These name which
 * emails the template replaces.
 *
 * @return list<string>
 */
function brf_email_template_condition_types(object $template): array
{
    $vars = get_object_vars($template);
    /** @var mixed $conditions */
    $conditions = $vars['conditions'] ?? null;
    if (!is_array($conditions)) {
        return [];
    }

    $types = [];
    /** @var mixed $condition */
    foreach ($conditions as $condition) {
        $type = brf_condition_type($condition);
        if ($type !== '') {
            $types[] = $type;
        }
    }

    return array_values(array_unique($types));
}

/**
 * A template's status ('active' by default, matching the plugin's runtime
 * which treats a missing status as active).
 */
function brf_email_template_status(object $template): string
{
    /** @var mixed $status */
    $status = get_object_vars($template)['status'] ?? null;

    return $status === 'inactive' ? 'inactive' : 'active';
}

/**
 * A template as a plain associative array for full output.
 *
 * @return array<array-key, mixed>
 */
function brf_email_template_to_output(object $template): array
{
    /** @var mixed $decoded */
    $decoded = json_decode((string) wp_json_encode($template), associative: true);

    return is_array($decoded) ? $decoded : [];
}

/**
 * The raw `brf_email_designer_themes` rows exactly as stored (array of theme
 * objects referenced by a template's `theme` id).
 *
 * @return list<object>
 */
function brf_raw_email_themes(): array
{
    /** @var mixed $raw */
    $raw = brf_raw_option('brf_email_designer_themes');
    if (!is_array($raw)) {
        return [];
    }

    $rows = [];
    /** @var mixed $row */
    foreach ($raw as $row) {
        if (!is_object($row)) {
            continue;
        }
        $rows[] = $row;
    }

    return $rows;
}

/**
 * A theme's id ('' when absent).
 */
function brf_email_theme_id(object $theme): string
{
    /** @var mixed $id */
    $id = get_object_vars($theme)['id'] ?? null;
    if (is_string($id)) {
        return $id;
    }
    if (is_int($id)) {
        return (string) $id;
    }

    return '';
}

/**
 * Cap a list of output rows to a total response-byte budget. Rows are kept in
 * order until the budget is spent; the rest are dropped and reported as a
 * count. Keeps any list read bounded regardless of how large the stored data
 * grows (a pathological form/store can't flood the agent context).
 *
 * @param list<array<string, mixed>> $rows
 * @return array{rows: list<array<string, mixed>>, omitted: int}
 */
function brf_cap_list(array $rows, int $budget = BRF_MAX_LIST_RESPONSE_BYTES): array
{
    $kept = [];
    $omitted = 0;
    foreach ($rows as $row) {
        if ($budget > 0) {
            $budget -= strlen((string) wp_json_encode($row));
            $kept[] = $row;
            continue;
        }
        $omitted++;
    }

    return ['rows' => $kept, 'omitted' => $omitted];
}

/**
 * Append an "omitted rows" warning to a list response when brf_cap_list
 * dropped entries.
 *
 * @param array<string, mixed> $response
 * @return array<string, mixed>
 */
function brf_list_omitted_warning(array $response, int $omitted, string $noun): array
{
    if ($omitted <= 0) {
        return $response;
    }

    return brf_append_warning($response, warning: sprintf(
        '%d %s were omitted to keep the response bounded (~%d KB). Narrow the query (filters/paging) or read the stored option directly if you need them all.',
        $omitted,
        $noun,
        (int) (BRF_MAX_LIST_RESPONSE_BYTES / 1024),
    ));
}

/**
 * Warn (do not truncate) when a full-object GET response is large — a config /
 * theme / design returned in full for inspection can still be multi-MB. A cut
 * JSON object would be unusable, so this only flags it.
 *
 * @param array<string, mixed> $response
 * @return array<string, mixed>
 */
function brf_warn_if_large(array $response, mixed $payload, string $what): array
{
    $bytes = strlen((string) wp_json_encode($payload));
    if ($bytes <= BRF_LARGE_GET_WARN_BYTES) {
        return $response;
    }

    return brf_append_warning($response, warning: sprintf(
        'The %s is large (~%d KB), returned in full (not truncated). Expect a big response.',
        $what,
        (int) ($bytes / 1024),
    ));
}
