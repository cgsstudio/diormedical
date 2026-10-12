<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Pod types supported by every CRUD ability in this category.
 *
 * @var list<string>
 */
const NOVAMIRA_PODS_TYPES = ['post_type', 'taxonomy', 'pod', 'settings', 'user', 'comment', 'media'];

/**
 * Pod types that accept the `extend` mode (existing WP object → Pods-extended).
 *
 * @var list<string>
 */
const NOVAMIRA_PODS_EXTENDABLE_TYPES = ['post_type', 'taxonomy', 'user', 'comment', 'media'];

/**
 * Storage values valid for the ACT (`pod` type) custom-table content type.
 *
 * @var list<string>
 */
const NOVAMIRA_PODS_ACT_STORAGE = ['table', 'meta', 'none'];

/**
 * Field types Pods 3.x ships with.
 *
 * @var list<string>
 */
const NOVAMIRA_PODS_FIELD_TYPES = [
    'avatar',
    'boolean',
    'code',
    'color',
    'currency',
    'date',
    'datetime',
    'email',
    'file',
    'heading',
    'html',
    'number',
    'oembed',
    'paragraph',
    'password',
    'phone',
    'pick',
    'slug',
    'text',
    'time',
    'website',
    'wysiwyg',
];

/**
 * Output keys retained when summarizing a Pod object in `list-pods`.
 *
 * @var list<string>
 */
const NOVAMIRA_POD_SUMMARY_KEYS = ['id', 'name', 'label', 'type', 'storage', 'object'];

/**
 * Post-type `supports` flags Pods recognises.
 *
 * @var list<string>
 */
const NOVAMIRA_PODS_SUPPORTS_FLAGS = [
    'title',
    'editor',
    'thumbnail',
    'excerpt',
    'trackbacks',
    'custom-fields',
    'comments',
    'revisions',
    'author',
    'page-attributes',
    'post-formats',
];

/**
 * Convert a Pod whatsit into a flat associative array. The Pods API may
 * return either a `Pods\Whatsit\Pod` instance (modern) or a plain array
 * (legacy paths) — handle both.
 *
 * @param object|array<array-key, mixed> $pod
 * @return array<string, mixed>
 */
function pods_pod_to_array(object|array $pod): array
{
    if (is_array($pod)) {
        /** @var array<string, mixed> $pod */
        return $pod;
    }
    if (method_exists($pod, 'get_args')) {
        /** @var array<string, mixed> */
        return $pod->get_args();
    }
    return [];
}

/**
 * Same shape as {@see pods_pod_to_array()} but for Field / Group whatsits.
 *
 * @param object|array<array-key, mixed> $field
 * @return array<string, mixed>
 */
function pods_field_to_array(object|array $field): array
{
    return pods_pod_to_array($field);
}

/**
 * Compact form for `list-pods`.
 *
 * @param object|array<array-key, mixed> $pod
 * @return array<string, mixed>
 */
function pods_pod_summary(object|array $pod): array
{
    $args = pods_pod_to_array($pod);
    return array_intersect_key($args, array_flip(NOVAMIRA_POD_SUMMARY_KEYS));
}

/**
 * Convert a `["title","editor"]` array into `["supports_title"=>1,"supports_editor"=>1]`
 * for Pods' options blob. Unknown flags are silently dropped.
 *
 * @param list<string> $supports
 * @return array<string, int>
 */
function pods_expand_supports(array $supports): array
{
    $out = [];
    foreach ($supports as $flag) {
        if ($flag === '') {
            continue;
        }
        $normalized = str_replace(search: '-', replace: '_', subject: strtolower($flag));
        $canonical = str_replace(search: '_', replace: '-', subject: $normalized);
        if (!in_array(needle: $canonical, haystack: NOVAMIRA_PODS_SUPPORTS_FLAGS, strict: true)) {
            continue;
        }
        $out['supports_' . $normalized] = 1;
    }
    return $out;
}

/**
 * Read a string from the input array. Returns the trimmed value or null
 * when missing / not a string / empty after trim.
 *
 * @param array<string, mixed> $input
 */
function pods_input_string(array $input, string $key): ?string
{
    /** @var mixed $value */
    $value = $input[$key] ?? null;
    if (!is_string($value)) {
        return null;
    }
    $trimmed = trim($value);
    return $trimmed === '' ? null : $trimmed;
}

/**
 * Read an integer from the input array. Returns null when missing / not int.
 *
 * @param array<string, mixed> $input
 */
function pods_input_int(array $input, string $key): ?int
{
    /** @var mixed $value */
    $value = $input[$key] ?? null;
    return is_int($value) ? $value : null;
}

/**
 * Read a boolean from the input array. Returns null when the key is
 * missing so callers can distinguish "user did not pass it" from
 * "user passed false".
 *
 * @param array<string, mixed> $input
 */
function pods_input_bool(array $input, string $key): ?bool
{
    /** @var mixed $value */
    $value = $input[$key] ?? null;
    return is_bool($value) ? $value : null;
}

/**
 * Read a free-form `options` object from the input array.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function pods_input_options(array $input, string $key = 'options'): array
{
    /** @var mixed $value */
    $value = $input[$key] ?? null;
    if (!is_array($value)) {
        return [];
    }
    /** @var array<string, mixed> $value */
    return $value;
}

/**
 * Pretty-print the (`pod` | `pod_id`) lookup pair into a label for error
 * messages. Returns `#<id>` for an id, the slug for a name, or '' if
 * neither resolves to something printable.
 *
 * @param array<string, mixed> $pod_params
 */
function pods_format_pod_lookup_label(array $pod_params): string
{
    /** @var mixed $pod_id */
    $pod_id = $pod_params['pod_id'] ?? null;
    if (is_int($pod_id)) {
        return '#' . $pod_id;
    }
    /** @var mixed $pod_name */
    $pod_name = $pod_params['pod'] ?? null;
    return is_string($pod_name) ? $pod_name : '';
}

/**
 * Coerce a value Pods stores as id-or-numeric-string (e.g. `group`,
 * `parent` on Whatsit fields) into a clean int. Returns null when the
 * value is missing or not a valid id.
 */
function pods_normalize_int_id(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && ctype_digit($value)) {
        return (int) $value;
    }
    return null;
}

/**
 * Apply a set of boolean Pods flags to a params array, defaulting missing
 * keys to the supplied defaults and converting `true` / `false` into Pods'
 * `1` / `0` int-coded shape. Lets callers describe their flag matrix in a
 * single declarative array instead of repeating the same `?? default ? 1 : 0`
 * pattern for every key.
 *
 * @param array<string, mixed> $params
 * @param array<string, mixed> $input
 * @param array<string, bool>  $defaults
 * @return array<string, mixed>
 */
function pods_apply_bool_flags(array $params, array $input, array $defaults): array
{
    foreach ($defaults as $key => $default) {
        $value = pods_input_bool($input, key: $key) ?? $default;
        $params[$key] = $value ? 1 : 0;
    }
    return $params;
}

/**
 * Build the human-friendly singular and plural labels for a create-* ability,
 * falling back to a humanised version of the pod name when not supplied.
 *
 * @param array<string, mixed> $input
 * @return array{singular: string, plural: string}
 */
function pods_normalize_labels(array $input, string $name): array
{
    $singular_input = pods_input_string($input, key: 'label_singular');
    $singular = $singular_input ?? ucwords(str_replace(search: '_', replace: ' ', subject: $name));
    $plural_input = pods_input_string($input, key: 'label_plural');
    $plural = $plural_input ?? $singular;
    return ['singular' => $singular, 'plural' => $plural];
}

/**
 * Resolve the `pod` / `pod_id` input pair into a load_pod params array.
 *
 * @param array<string, mixed> $input
 * @return array{0: array<string, mixed>, 1: ?string}
 */
function pods_resolve_pod_params(array $input): array
{
    $pod_id = pods_input_int($input, key: 'pod_id');
    if ($pod_id !== null) {
        return [['pod_id' => $pod_id], null];
    }
    $pod = pods_input_string($input, key: 'pod');
    if ($pod !== null) {
        return [['pod' => $pod], null];
    }
    return [[], 'Either `pod` (string) or `pod_id` (integer) is required.'];
}

/**
 * Resolve the `name` / `id` input pair into a load_pod params array (top-level
 * pod identifier — used by get-pod / edit-pod / delete-pod).
 *
 * @param array<string, mixed> $input
 * @return array{0: array<string, mixed>, 1: ?string}
 */
function pods_resolve_top_level_pod(array $input): array
{
    $id = pods_input_int($input, key: 'id');
    if ($id !== null) {
        return [['id' => $id], null];
    }
    $name = pods_input_string($input, key: 'name');
    if ($name !== null) {
        return [['name' => $name], null];
    }
    return [[], 'Either `name` (string) or `id` (integer) is required.'];
}

/**
 * Resolve `name` / `id` for a sub-entity (field / group) — same shape as the
 * pod resolver but with a customisable error message.
 *
 * @param array<string, mixed> $input
 * @return array{0: array<string, mixed>, 1: ?string}
 */
function pods_resolve_entity(array $input): array
{
    $id = pods_input_int($input, key: 'id');
    if ($id !== null) {
        return [['id' => $id], null];
    }
    $name = pods_input_string($input, key: 'name');
    if ($name !== null) {
        return [['name' => $name], null];
    }
    return [[], 'Either `name` (string) or `id` (integer) is required.'];
}

/**
 * Run a `pods_api()->save_*()` / `delete_*()` call and normalize whatever it
 * returns to a `{success, id?, error?}` shape. Pods can return an int (id),
 * false, a `WP_Error`, or throw — all four paths land here.
 *
 * @return array{success: bool, id?: int, error?: string}
 */
function pods_handle_api_result(callable $fn): array
{
    try {
        /** @var mixed $result */
        $result = $fn();
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
    return pods_normalize_api_result($result);
}

/**
 * Normalize a raw Pods API return value into the standard ability response
 * shape. Split out from {@see pods_handle_api_result()} to keep both
 * functions below mago's complexity threshold.
 *
 * @return array{success: bool, id?: int, error?: string}
 */
function pods_normalize_api_result(mixed $result): array
{
    if ($result instanceof WP_Error) {
        return ['success' => false, 'error' => $result->get_error_message()];
    }
    if ($result === false || $result === null) {
        return ['success' => false, 'error' => 'Pods returned a falsy result without raising an error.'];
    }
    if (is_int($result)) {
        return ['success' => true, 'id' => $result];
    }
    if (is_string($result) && ctype_digit($result)) {
        return ['success' => true, 'id' => (int) $result];
    }
    if (is_object($result)) {
        return pods_extract_id_from_whatsit($result);
    }
    return ['success' => true];
}

/**
 * Extract the `id` from a Pod / Field / Group whatsit returned by save_*().
 *
 * @return array{success: bool, id?: int, error?: string}
 */
function pods_extract_id_from_whatsit(object $whatsit): array
{
    $args = pods_pod_to_array($whatsit);
    /** @var mixed $maybe_id */
    $maybe_id = $args['id'] ?? null;
    if (is_int($maybe_id)) {
        return ['success' => true, 'id' => $maybe_id];
    }
    if (is_string($maybe_id) && ctype_digit($maybe_id)) {
        return ['success' => true, 'id' => (int) $maybe_id];
    }
    return ['success' => true];
}

/**
 * Format a save_* result into the standard ability response shape used by
 * every create-* / edit-* ability — `{success, id, name}` on hit,
 * `{success: false, error}` on miss.
 *
 * @param array{success: bool, id?: int, error?: string} $result
 * @return array<string, mixed>
 */
function pods_format_save_response(array $result, string $name): array
{
    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error'] ?? 'Unknown error.'];
    }
    return ['success' => true, 'id' => $result['id'] ?? 0, 'name' => $name];
}

/**
 * Format a delete_* result into `{success, name}` / `{success: false, error}`.
 *
 * @param array{success: bool, id?: int, error?: string} $result
 * @return array<string, mixed>
 */
function pods_format_delete_response(array $result, string $name): array
{
    if (!$result['success']) {
        return ['success' => false, 'error' => $result['error'] ?? 'Unknown error.'];
    }
    return ['success' => true, 'name' => $name];
}

/**
 * Lookup table of the most agent-relevant options per Pods field type.
 * Powers `pods-get-field-type-schema`.
 *
 * @var array<string, list<array{key: string, type: string, description: string}>>
 */
const NOVAMIRA_PODS_FIELD_TYPE_OPTIONS = [
    'text' => [
        ['key' => 'text_max_length', 'type' => 'integer', 'description' => 'Maximum input length (0 = unlimited).'],
        ['key' => 'text_allow_html', 'type' => 'boolean', 'description' => 'Whether HTML is allowed in the value.'],
    ],
    'paragraph' => [
        [
            'key' => 'paragraph_max_length',
            'type' => 'integer',
            'description' => 'Maximum input length (0 = unlimited).',
        ],
        ['key' => 'paragraph_allow_html', 'type' => 'boolean', 'description' => 'Whether HTML tags are allowed.'],
        [
            'key' => 'paragraph_html_format',
            'type' => 'string',
            'description' => 'plain | wptexturize | autop | wpautop',
        ],
    ],
    'wysiwyg' => [
        ['key' => 'wysiwyg_editor', 'type' => 'string', 'description' => 'tinymce | cleditor | none'],
        ['key' => 'wysiwyg_media_buttons', 'type' => 'boolean', 'description' => 'Show the WP media-uploader buttons.'],
        [
            'key' => 'wysiwyg_allowed_html_tags',
            'type' => 'string',
            'description' => 'Space-separated list (empty = all).',
        ],
    ],
    'code' => [
        ['key' => 'code_max_length', 'type' => 'integer', 'description' => 'Maximum input length (0 = unlimited).'],
    ],
    'slug' => [
        ['key' => 'slug_placeholder', 'type' => 'string', 'description' => 'Placeholder shown in admin.'],
        ['key' => 'slug_separator', 'type' => 'string', 'description' => 'Separator character (default `-`).'],
    ],
    'password' => [
        ['key' => 'password_max_length', 'type' => 'integer', 'description' => 'Maximum input length (0 = unlimited).'],
    ],
    'heading' => [
        ['key' => 'heading_tag', 'type' => 'string', 'description' => 'h1 | h2 | h3 | h4 | h5 | h6'],
    ],
    'html' => [
        ['key' => 'html_content', 'type' => 'string', 'description' => 'Raw HTML rendered in the admin form.'],
    ],
    'number' => [
        ['key' => 'number_format_type', 'type' => 'string', 'description' => 'number | float'],
        ['key' => 'number_decimals', 'type' => 'integer', 'description' => 'Number of decimal digits.'],
        ['key' => 'number_format', 'type' => 'string', 'description' => 'i18n format key (9,999.99 | 9.999,99 | …).'],
        ['key' => 'number_min', 'type' => 'number', 'description' => 'Minimum allowed value.'],
        ['key' => 'number_max', 'type' => 'number', 'description' => 'Maximum allowed value.'],
    ],
    'currency' => [
        ['key' => 'currency_format_sign', 'type' => 'string', 'description' => 'Currency code (usd, eur, gbp, …).'],
        ['key' => 'currency_format_placement', 'type' => 'string', 'description' => 'before | after | none'],
        ['key' => 'currency_decimals', 'type' => 'integer', 'description' => 'Number of decimal digits.'],
    ],
    'date' => [
        [
            'key' => 'date_format',
            'type' => 'string',
            'description' => 'PHP date format (Y-m-d, …) or Pods preset (mdy, dmy, …).',
        ],
        ['key' => 'date_allow_empty', 'type' => 'boolean', 'description' => 'Allow empty value.'],
    ],
    'time' => [
        ['key' => 'time_format', 'type' => 'string', 'description' => 'PHP time format or Pods preset (h_mm_a, …).'],
        ['key' => 'time_allow_empty', 'type' => 'boolean', 'description' => 'Allow empty value.'],
    ],
    'datetime' => [
        ['key' => 'datetime_format', 'type' => 'string', 'description' => 'Date format string or preset.'],
        ['key' => 'datetime_time_format', 'type' => 'string', 'description' => 'Time format string or preset.'],
        ['key' => 'datetime_allow_empty', 'type' => 'boolean', 'description' => 'Allow empty value.'],
    ],
    'boolean' => [
        ['key' => 'boolean_yes_label', 'type' => 'string', 'description' => 'Custom label for the truthy state.'],
        ['key' => 'boolean_no_label', 'type' => 'string', 'description' => 'Custom label for the falsy state.'],
    ],
    'color' => [
        ['key' => 'color_select_type', 'type' => 'string', 'description' => 'wp-color-picker | text'],
    ],
    'file' => [
        ['key' => 'file_format_type', 'type' => 'string', 'description' => 'single | multi'],
        ['key' => 'file_uploader', 'type' => 'string', 'description' => 'attachment | plupload'],
        ['key' => 'file_attachment_tab', 'type' => 'string', 'description' => 'upload | browse'],
        ['key' => 'file_type', 'type' => 'string', 'description' => 'images | video | audio | text | any'],
        ['key' => 'file_limit', 'type' => 'integer', 'description' => 'Max number of files (0 = unlimited).'],
        ['key' => 'file_restrict_filesize', 'type' => 'string', 'description' => 'Limit string (e.g. `10MB`).'],
    ],
    'avatar' => [
        ['key' => 'avatar_uploader', 'type' => 'string', 'description' => 'attachment | plupload'],
    ],
    'oembed' => [
        ['key' => 'oembed_width', 'type' => 'integer', 'description' => 'Embed width in pixels.'],
        ['key' => 'oembed_height', 'type' => 'integer', 'description' => 'Embed height in pixels.'],
        ['key' => 'oembed_show_provider', 'type' => 'boolean', 'description' => 'Show the provider name.'],
    ],
    'phone' => [
        ['key' => 'phone_format', 'type' => 'string', 'description' => 'us | international'],
    ],
    'email' => [
        ['key' => 'email_max_length', 'type' => 'integer', 'description' => 'Maximum input length (0 = unlimited).'],
    ],
    'website' => [
        [
            'key' => 'website_format',
            'type' => 'string',
            'description' => 'normal | no-www | force-www | force-http | force-https',
        ],
    ],
    'pick' => [
        [
            'key' => 'pick_object',
            'type' => 'string',
            'description' => 'post_type | taxonomy | user | role | capability | role-and-capability | site | post-status | comment_status | ping_status | image-size | nav_menu | sidebar | post-format | language | country | us_state | ca_province | au_state | role_select | pod | custom-simple — pairs with pick_val to identify the target.',
        ],
        [
            'key' => 'pick_val',
            'type' => 'string',
            'description' => 'Object name when pick_object is post_type | taxonomy | pod (e.g. `book` for post_type, `genre` for taxonomy).',
        ],
        ['key' => 'pick_format_type', 'type' => 'string', 'description' => 'single | multi'],
        ['key' => 'pick_format_single', 'type' => 'string', 'description' => 'dropdown | radio | autocomplete | list'],
        [
            'key' => 'pick_format_multi',
            'type' => 'string',
            'description' => 'checkbox | multiselect | autocomplete | list',
        ],
        [
            'key' => 'pick_limit',
            'type' => 'integer',
            'description' => 'Maximum related items (0 = unlimited; only honored when format_type=multi).',
        ],
        ['key' => 'pick_orderby', 'type' => 'string', 'description' => 'ORDER BY clause for related lookups.'],
        [
            'key' => 'pick_show_select_text',
            'type' => 'integer',
            'description' => '0 | 1 — show the placeholder option in dropdowns.',
        ],
        [
            'key' => 'pick_allow_add_new',
            'type' => 'integer',
            'description' => '0 | 1 — let users create new related items inline.',
        ],
        [
            'key' => 'sister_id',
            'type' => 'integer',
            'description' => 'Bidirectional relationship — id of the inverse field on the related pod.',
        ],
    ],
];
