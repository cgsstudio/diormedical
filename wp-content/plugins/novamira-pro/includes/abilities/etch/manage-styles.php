<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Etch;

use WP_Error;

/**
 * Abilities: manage Etch global stylesheets (the etch_global_stylesheets option
 * — named CSS sheets like "Main") and the styles layer (etch_styles — the
 * per-element/class CSS records Etch surfaces in the editor). Both get full CRUD.
 *
 * Styles-layer writes are safe: records created here are preserved when the page
 * is later edited and saved in Etch, so they stay editable after the agent hands
 * off. Each record keeps the selector and the rule body separate: css is the body
 * — declarations plus any nested rules (states like `&:hover`, `@media`
 * breakpoints, descendant selectors), exactly what the Etch editor stores per
 * class. Etch's readonly built-ins are never written or deleted.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * The global stylesheets option as a normalized map { id => {name, css, type} }.
 *
 * @return array<array-key, mixed>
 */
function etch_stylesheets_option(): array
{
    return etch_option_array('etch_global_stylesheets');
}

/**
 * Resolve a stylesheet id within the given map, or a WP_Error.
 *
 * @param array<array-key, mixed> $sheets
 * @return array<array-key, mixed>|WP_Error
 */
function etch_stylesheet_or_error(array $sheets, string $id): array|WP_Error
{
    if (!isset($sheets[$id])) {
        return etch_error('etch_not_found', sprintf(
            /* translators: %s: stylesheet ID. */
            __('Stylesheet "%s" was not found.', domain: 'novamira-pro'),
            $id,
        ));
    }

    /** @var mixed $sheet */
    $sheet = $sheets[$id];

    return is_array($sheet) ? $sheet : [];
}

/**
 * A non-empty stylesheet_id string from input, or a WP_Error.
 *
 * @return string|WP_Error
 */
function etch_stylesheet_id_or_error(mixed $id): string|WP_Error
{
    if (!is_string($id) || $id === '') {
        return etch_error('etch_invalid_input', __('A non-empty "stylesheet_id" is required.', domain: 'novamira-pro'));
    }

    return $id;
}

/**
 * Validate a css string input within the size cap.
 *
 * @return string|WP_Error
 */
function etch_css_or_error(mixed $css): string|WP_Error
{
    if (!is_string($css)) {
        return etch_error('etch_invalid_input', __('"css" must be a string.', domain: 'novamira-pro'));
    }

    if (strlen($css) > ETCH_MAX_HTML_BYTES) {
        return etch_error('etch_css_too_large', __('"css" exceeds the maximum size.', domain: 'novamira-pro'));
    }

    return $css;
}

/**
 * Generate a stylesheet id not already present in the map (mirrors Etch's
 * substr(uniqid(), -7)).
 *
 * @param array<array-key, mixed> $existing
 */
function etch_generate_stylesheet_id(array $existing): string
{
    do {
        $id = substr(uniqid(), offset: -7);
    } while ($id === '' || isset($existing[$id]));

    return $id;
}

/**
 * Shape one stylesheet entry for output.
 *
 * @param array<array-key, mixed> $sheet
 * @return array{id: string, name: string, css: string, type: string}
 */
function etch_stylesheet_view(string $id, array $sheet): array
{
    /** @var mixed $name */
    $name = $sheet['name'] ?? '';
    /** @var mixed $css */
    $css = $sheet['css'] ?? '';
    /** @var mixed $type */
    $type = $sheet['type'] ?? 'default';

    return [
        'id' => $id,
        'name' => is_string($name) ? $name : '',
        'css' => is_string($css) ? $css : '',
        'type' => is_string($type) ? $type : 'default',
    ];
}

// ---------------------------------------------------------------------------
// list-stylesheets
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-list-stylesheets', [
    'label' => __('List Etch Stylesheets', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Etch global stylesheets (named CSS sheets such as "Main", stored in the etch_global_stylesheets option). Returns a compact entry per sheet { id, name, type, css_bytes } — fetch the CSS with novamira/etch-get-stylesheet.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'count' => ['type' => 'integer'],
            'stylesheets' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'css_bytes' => ['type' => 'integer'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_list_stylesheets',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Compact — no CSS bodies. The "default" sheet (Main) and "custom-media" sheet are Etch built-ins. Use the id with novamira/etch-get-stylesheet to read the CSS. type is "default" or "@custom-media".',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{count: int, stylesheets: list<array{id: string, name: string, type: string, css_bytes: int}>}
 */
function etch_list_stylesheets(): array
{
    $sheets = etch_stylesheets_option();

    $out = [];
    foreach (array_keys($sheets) as $id) {
        /** @var mixed $sheet */
        $sheet = $sheets[$id];
        if (!is_array($sheet)) {
            continue;
        }

        $view = etch_stylesheet_view((string) $id, $sheet);
        $out[] = [
            'id' => $view['id'],
            'name' => $view['name'],
            'type' => $view['type'],
            'css_bytes' => strlen($view['css']),
        ];
    }

    return [
        'count' => count($out),
        'stylesheets' => $out,
    ];
}

// ---------------------------------------------------------------------------
// get-stylesheet
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-get-stylesheet', [
    'label' => __('Get Etch Stylesheet', domain: 'novamira-pro'),
    'description' => __('Returns one Etch global stylesheet in full: id, name, css, type.', domain: 'novamira-pro'),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'stylesheet_id' => ['type' => 'string', 'description' => 'The stylesheet id (e.g. "default").'],
        ],
        'required' => ['stylesheet_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'css' => ['type' => 'string'],
            'type' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_stylesheet',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read a stylesheet before editing its CSS with novamira/etch-edit-stylesheet.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{id: string, name: string, css: string, type: string}|WP_Error
 */
function etch_get_stylesheet(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $id = etch_stylesheet_id_or_error($input['stylesheet_id'] ?? null);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $sheets = etch_stylesheets_option();
    $sheet = etch_stylesheet_or_error($sheets, $id);
    if ($sheet instanceof WP_Error) {
        return $sheet;
    }

    return etch_stylesheet_view($id, $sheet);
}

// ---------------------------------------------------------------------------
// create-stylesheet
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-create-stylesheet', [
    'label' => __('Create Etch Stylesheet', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Etch global stylesheet. Requires name and css; type defaults to "default" (use "@custom-media" for custom-media definitions). Returns the new id, name, and type.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => ['type' => 'string'],
            'css' => ['type' => 'string'],
            'type' => ['type' => 'string', 'description' => '"default" or "@custom-media".'],
        ],
        'required' => ['name', 'css'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'type' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_create_stylesheet',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'css is stored verbatim and enqueued globally on the front end. The new sheet is added to etch_global_stylesheets with a generated id.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{id: string, name: string, type: string}|WP_Error
 */
function etch_create_stylesheet(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $name */
    $name = $input['name'] ?? '';
    if (!is_string($name) || trim($name) === '') {
        return etch_error('etch_invalid_input', __('A non-empty "name" is required.', domain: 'novamira-pro'));
    }

    $css = etch_css_or_error($input['css'] ?? null);
    if ($css instanceof WP_Error) {
        return $css;
    }

    /** @var mixed $type */
    $type = $input['type'] ?? 'default';
    $type = is_string($type) && $type !== '' ? $type : 'default';

    $clean_name = sanitize_text_field($name);

    // Lock the read-modify-write so a concurrent writer can't clobber the option
    // (and so the generated id is unique against the freshly re-read map).
    $id = '';
    $lock = etch_option_lock_acquire('etch_global_stylesheets');
    try {
        $sheets = etch_stylesheets_option();
        $id = etch_generate_stylesheet_id($sheets);
        $sheets[$id] = [
            'name' => $clean_name,
            'css' => $css,
            'type' => $type,
        ];
        update_option('etch_global_stylesheets', $sheets);
    } finally {
        etch_option_lock_release($lock);
    }

    return [
        'id' => $id,
        'name' => $clean_name,
        'type' => $type,
    ];
}

// ---------------------------------------------------------------------------
// edit-stylesheet
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-edit-stylesheet', [
    'label' => __('Edit Etch Stylesheet', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Etch global stylesheet in place (partial update). Provide stylesheet_id and any of name, css, type. Returns the id, name, and type.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'stylesheet_id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'css' => ['type' => 'string'],
            'type' => ['type' => 'string'],
        ],
        'required' => ['stylesheet_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'type' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_edit_stylesheet',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update: pass only the fields you change. Editing "default" (Main) or "custom-media" rewrites those Etch built-ins.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Validate the provided (optional) stylesheet fields, returning only those given
 * (sanitized) or a WP_Error. Split out to keep etch_edit_stylesheet simple.
 *
 * @param array<array-key, mixed> $input
 * @return array{name?: string, css?: string, type?: string}|WP_Error
 */
function etch_stylesheet_fields_or_error(array $input): array|WP_Error
{
    $fields = [];

    if (isset($input['name'])) {
        /** @var mixed $name */
        $name = $input['name'];
        if (!is_string($name) || trim($name) === '') {
            return etch_error('etch_invalid_input', __('"name" must be a non-empty string.', domain: 'novamira-pro'));
        }
        $fields['name'] = sanitize_text_field($name);
    }

    if (isset($input['css'])) {
        $css = etch_css_or_error($input['css']);
        if ($css instanceof WP_Error) {
            return $css;
        }
        $fields['css'] = $css;
    }

    if (isset($input['type'])) {
        /** @var mixed $type */
        $type = $input['type'];
        if (!is_string($type) || $type === '') {
            return etch_error('etch_invalid_input', __('"type" must be a non-empty string.', domain: 'novamira-pro'));
        }
        $fields['type'] = $type;
    }

    return $fields;
}

/**
 * @return array{id: string, name: string, type: string}|WP_Error
 */
function etch_edit_stylesheet(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $id = etch_stylesheet_id_or_error($input['stylesheet_id'] ?? null);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $fields = etch_stylesheet_fields_or_error($input);
    if ($fields instanceof WP_Error) {
        return $fields;
    }

    $result = ['id' => $id, 'name' => '', 'type' => ''];
    $lock = etch_option_lock_acquire('etch_global_stylesheets');
    try {
        $sheets = etch_stylesheets_option();
        $sheet = etch_stylesheet_or_error($sheets, $id);
        if ($sheet instanceof WP_Error) {
            return $sheet;
        }

        $view = etch_stylesheet_view($id, $sheet);
        $merged = [
            'name' => $fields['name'] ?? $view['name'],
            'css' => $fields['css'] ?? $view['css'],
            'type' => $fields['type'] ?? $view['type'],
        ];
        $sheets[$id] = $merged;
        update_option('etch_global_stylesheets', $sheets);

        $result = ['id' => $id, 'name' => $merged['name'], 'type' => $merged['type']];
    } finally {
        etch_option_lock_release($lock);
    }

    return $result;
}

// ---------------------------------------------------------------------------
// delete-stylesheet
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-delete-stylesheet', [
    'label' => __('Delete Etch Stylesheet', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an Etch global stylesheet by id. Deleting the built-ins is allowed: Etch recreates "custom-media" on the next request, and recreates "default" (Main) only if the whole stylesheet set becomes empty — otherwise "default" stays deleted. Returns id and deleted:true.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'stylesheet_id' => ['type' => 'string'],
        ],
        'required' => ['stylesheet_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_delete_stylesheet',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'DESTRUCTIVE. Etch recreates "custom-media" automatically; "default" (Main) only comes back if no stylesheets remain at all, so deleting it normally sticks and drops its CSS.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{id: string, deleted: bool}|WP_Error
 */
function etch_delete_stylesheet(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $id = etch_stylesheet_id_or_error($input['stylesheet_id'] ?? null);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $lock = etch_option_lock_acquire('etch_global_stylesheets');
    try {
        $sheets = etch_stylesheets_option();
        $sheet = etch_stylesheet_or_error($sheets, $id);
        if ($sheet instanceof WP_Error) {
            return $sheet;
        }

        unset($sheets[$id]);
        update_option('etch_global_stylesheets', $sheets);
    } finally {
        etch_option_lock_release($lock);
    }

    return [
        'id' => $id,
        'deleted' => true,
    ];
}

// ---------------------------------------------------------------------------
// get-styles (read-only view of the styles layer)
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-get-styles', [
    'label' => __('Get Etch Styles', domain: 'novamira-pro'),
    'description' => __(
        'Reads the Etch styles layer (the etch_styles option) — the per-element/class CSS Etch manages. Returns a compact entry per style { id, collection, selector, type, readonly, css_bytes } (the selector identifies the rule; css_bytes is its CSS size, not inlined to keep the response bounded). readonly:true marks Etch built-ins. Author records with novamira/etch-create-style, novamira/etch-edit-style and novamira/etch-delete-style.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'count' => ['type' => 'integer'],
            'total' => ['type' => 'integer'],
            'truncated' => ['type' => 'boolean'],
            'styles' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'collection' => ['type' => 'string'],
                        'selector' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'readonly' => ['type' => 'boolean'],
                        'css_bytes' => ['type' => 'integer'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_get_styles',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Inspect the CSS-class/element style records and get each id. readonly:true marks Etch built-ins (do not write them). Create/edit/delete records with novamira/etch-create-style, -edit-style and -delete-style; use stylesheets for global foundations and design tokens.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{count: int, total: int, truncated: bool, styles: list<array{id: string, collection: string, selector: string, type: string, readonly: bool, css_bytes: int}>}|WP_Error
 */
function etch_get_styles(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $styles = etch_option_array('etch_styles');

    $cap = 2000;
    $total = 0;
    $out = [];
    foreach (array_keys($styles) as $id) {
        /** @var mixed $style */
        $style = $styles[$id];
        if (!is_array($style)) {
            continue;
        }

        $total++;
        if (count($out) >= $cap) {
            continue;
        }

        /** @var mixed $collection */
        $collection = $style['collection'] ?? '';
        /** @var mixed $selector */
        $selector = $style['selector'] ?? '';
        /** @var mixed $type */
        $type = $style['type'] ?? '';
        /** @var mixed $css */
        $css = $style['css'] ?? '';

        $out[] = [
            'id' => (string) $id,
            'collection' => is_string($collection) ? $collection : '',
            'selector' => is_string($selector) ? $selector : '',
            'type' => is_string($type) ? $type : '',
            'readonly' => ($style['readonly'] ?? null) === true,
            'css_bytes' => strlen(is_string($css) ? $css : ''),
        ];
    }

    return [
        'count' => count($out),
        'total' => $total,
        'truncated' => $total > count($out),
        'styles' => $out,
    ];
}

// ---------------------------------------------------------------------------
// styles-layer helpers (per-element/class records in etch_styles)
// ---------------------------------------------------------------------------

/**
 * The styles layer option as a normalized map { id => {collection, selector, css, readonly, type} }.
 *
 * @return array<array-key, mixed>
 */
function etch_styles_option(): array
{
    return etch_option_array('etch_styles');
}

/**
 * A non-empty style_id string from input, or a WP_Error.
 *
 * @return string|WP_Error
 */
function etch_style_id_or_error(mixed $id): string|WP_Error
{
    if (!is_string($id) || $id === '') {
        return etch_error('etch_invalid_input', __('A non-empty "style_id" is required.', domain: 'novamira-pro'));
    }

    return $id;
}

/**
 * Resolve a style id within the given map, or a WP_Error.
 *
 * @param array<array-key, mixed> $styles
 * @return array<array-key, mixed>|WP_Error
 */
function etch_style_or_error(array $styles, string $id): array|WP_Error
{
    if (!isset($styles[$id])) {
        return etch_error('etch_not_found', sprintf(
            /* translators: %s: style ID. */
            __('Style "%s" was not found.', domain: 'novamira-pro'),
            $id,
        ));
    }

    /** @var mixed $style */
    $style = $styles[$id];

    return is_array($style) ? $style : [];
}

/**
 * Validate a selector string from input, or a WP_Error.
 *
 * @return string|WP_Error
 */
function etch_selector_or_error(mixed $selector): string|WP_Error
{
    if (!is_string($selector) || trim($selector) === '') {
        return etch_error('etch_invalid_input', __(
            'A non-empty "selector" is required (e.g. ".hero").',
            domain: 'novamira-pro',
        ));
    }

    $selector = trim($selector);
    if (strlen($selector) > 500) {
        return etch_error('etch_invalid_input', __('"selector" is too long.', domain: 'novamira-pro'));
    }

    return $selector;
}

/**
 * Infer an Etch style type from its selector (class / id / attribute / element).
 */
function etch_style_type_from_selector(string $selector): string
{
    $trimmed = ltrim($selector);
    $first = $trimmed === '' ? '' : $trimmed[0];

    return match ($first) {
        '.' => 'class',
        '#' => 'id',
        '[' => 'attribute',
        default => 'element',
    };
}

/**
 * Generate a style id not already present in the map (mirrors Etch's client id).
 *
 * @param array<array-key, mixed> $existing
 */
function etch_generate_style_id(array $existing): string
{
    do {
        $id = substr(uniqid(), offset: -7);
    } while ($id === '' || isset($existing[$id]));

    return $id;
}

/**
 * Whether a style record is an Etch readonly built-in (never written/deleted).
 *
 * @param array<array-key, mixed> $style
 */
function etch_style_is_readonly(array $style): bool
{
    return ($style['readonly'] ?? null) === true;
}

/**
 * Find an existing style id by collection + selector, or null.
 *
 * @param array<array-key, mixed> $styles
 */
function etch_find_style_id(array $styles, string $collection, string $selector): ?string
{
    foreach (array_keys($styles) as $id) {
        /** @var mixed $style */
        $style = $styles[$id];
        if (!is_array($style)) {
            continue;
        }

        /** @var mixed $sel */
        $sel = $style['selector'] ?? null;
        /** @var mixed $col */
        $col = $style['collection'] ?? 'default';
        if (is_string($sel) && $sel === $selector && $col === $collection) {
            return (string) $id;
        }
    }

    return null;
}

/**
 * Reject a css value that wraps the OUTER selector (the common "full rule"
 * mistake) — Etch renders each record as "selector { css }", so the css must be
 * the rule body only. Nested rules inside the body (states like `&:hover`,
 * `@media` breakpoints, descendant selectors) are fine and pass through. Returns
 * a WP_Error or null.
 *
 * @return WP_Error|null
 */
function etch_reject_wrapped_css(string $css, string $selector): ?WP_Error
{
    if (preg_match('/^\s*' . preg_quote($selector, delimiter: '/') . '\s*\{/', $css) === 1) {
        return etch_error('etch_invalid_input', __(
            '"css" must not repeat the outer selector — Etch wraps it for you. Write declarations and any nested rules (states, @media, descendants) directly, e.g. "color: red; &:hover { color: blue; }".',
            domain: 'novamira-pro',
        ));
    }

    return null;
}

// ---------------------------------------------------------------------------
// create-style
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-create-style', [
    'label' => __('Create Etch Style', domain: 'novamira-pro'),
    'description' => __(
        'Creates an editable per-class (or per-element) style record in the Etch styles layer (etch_styles) — the records Etch surfaces when you select a class/element in the editor. Provide selector (e.g. ".hero") and css. css is the rule body: declarations PLUS optional nested rules — states (&:hover, &:focus, &:active), breakpoints (@media), and descendant/child selectors — the same things the Etch editor edits per class (Etch renders "selector { css }"). Do NOT repeat the outer selector. e.g. "color: var(--brand); padding: 24px; &:hover { color: white; }". collection defaults to "default"; type is inferred from the selector (class/id/element/attribute). Use this (not a global stylesheet) for component/button/card/text styling you want editable in Etch, and put the same class on elements via attributes.class. For many records in one call use novamira/etch-create-styles. Returns { id, selector, type, collection }.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'selector' => ['type' => 'string', 'description' => 'CSS selector, typically a class like ".hero".'],
            'css' => [
                'type' => 'string',
                'description' => 'The rule body: declarations plus optional nested rules — states (&:hover, &:focus, &:active), breakpoints (@media), descendant selectors. Do not repeat the outer selector (Etch wraps it). e.g. "color: var(--brand); &:hover { color: white; }".',
            ],
            'collection' => ['type' => 'string', 'description' => 'Style collection; defaults to "default".'],
        ],
        'required' => ['selector', 'css'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'selector' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'collection' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_create_style',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'css is the rule body: declarations plus optional nested rules (&:hover/&:focus/&:active states, @media breakpoints, descendant selectors) — the same things the Etch editor edits per class. Do not repeat the outer selector; Etch renders "selector { css }". The record is editable in the Etch editor and survives editor saves. Fails if a style with the same selector already exists in the collection (use novamira/etch-edit-style). For dozens of styles in one call use novamira/etch-create-styles. Prefer this over a global stylesheet for component styling that should be class-editable.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{id: string, selector: string, type: string, collection: string}|WP_Error
 */
function etch_create_style(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $selector = etch_selector_or_error($input['selector'] ?? null);
    if ($selector instanceof WP_Error) {
        return $selector;
    }

    $css = etch_css_or_error($input['css'] ?? null);
    if ($css instanceof WP_Error) {
        return $css;
    }

    $wrapped = etch_reject_wrapped_css($css, $selector);
    if ($wrapped instanceof WP_Error) {
        return $wrapped;
    }

    /** @var mixed $raw_collection */
    $raw_collection = $input['collection'] ?? 'default';
    $collection = is_string($raw_collection) && $raw_collection !== '' ? $raw_collection : 'default';

    $type = etch_style_type_from_selector($selector);

    $id = '';
    $lock = etch_option_lock_acquire('etch_styles');
    try {
        $styles = etch_styles_option();

        $existing = etch_find_style_id($styles, $collection, $selector);
        if ($existing !== null) {
            return etch_error('etch_style_exists', sprintf(
                /* translators: 1: selector, 2: existing style ID. */
                __(
                    'A style for selector "%1$s" already exists (id "%2$s"). Edit it with novamira/etch-edit-style.',
                    domain: 'novamira-pro',
                ),
                $selector,
                $existing,
            ));
        }

        $id = etch_generate_style_id($styles);
        $styles[$id] = [
            'collection' => $collection,
            'selector' => $selector,
            'css' => $css,
            'readonly' => false,
            'type' => $type,
        ];
        update_option('etch_styles', $styles);
    } finally {
        etch_option_lock_release($lock);
    }

    return [
        'id' => $id,
        'selector' => $selector,
        'type' => $type,
        'collection' => $collection,
    ];
}

// ---------------------------------------------------------------------------
// create-styles (batch)
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-create-styles', [
    'label' => __('Create Etch Styles (batch)', domain: 'novamira-pro'),
    'description' => __(
        'Creates many editable per-class/element style records in one call — the batch form of novamira/etch-create-style, for a whole-page design (dozens of records) without one call each. Pass styles: an array of { selector, css, collection? } where css is the rule body (declarations plus optional nested rules: &:hover/&:focus/&:active states, @media breakpoints, descendant selectors; do not repeat the selector). Each record is validated independently: a bad or duplicate one is reported in errors[] (with its index) and the rest still succeed. Returns { created: [{ id, selector, type, collection }], errors: [{ index, selector, error }], created_count }.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'styles' => [
                'type' => 'array',
                'description' => 'Style records to create.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'selector' => ['type' => 'string', 'description' => 'CSS selector, e.g. ".hero".'],
                        'css' => [
                            'type' => 'string',
                            'description' => 'Rule body (declarations plus optional nested states/@media/descendant rules; no outer selector).',
                        ],
                        'collection' => [
                            'type' => 'string',
                            'description' => 'Style collection; defaults to "default".',
                        ],
                    ],
                    'required' => ['selector', 'css'],
                    'additionalProperties' => false,
                ],
            ],
        ],
        'required' => ['styles'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'created' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'selector' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'collection' => ['type' => 'string'],
                    ],
                ],
            ],
            'errors' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'index' => ['type' => 'integer'],
                        'selector' => ['type' => 'string'],
                        'error' => ['type' => 'string'],
                    ],
                ],
            ],
            'created_count' => ['type' => 'integer'],
        ],
        'required' => ['created', 'errors', 'created_count'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_create_styles',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Batch create. Each item is validated independently; a duplicate selector or invalid css is reported in errors[] (with its index) and does not abort the others. Use for a full page design; for a single record use novamira/etch-create-style.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{created: list<array{id: string, selector: string, type: string, collection: string}>, errors: list<array{index: int, selector: string, error: string}>, created_count: int}|WP_Error
 */
function etch_create_styles(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    /** @var mixed $items_raw */
    $items_raw = $input['styles'] ?? null;
    if (!is_array($items_raw) || $items_raw === []) {
        return etch_error('etch_invalid_input', __(
            'styles must be a non-empty array of { selector, css, collection? } objects.',
            domain: 'novamira-pro',
        ));
    }

    $created = [];
    $errors = [];

    $lock = etch_option_lock_acquire('etch_styles');
    try {
        $styles = etch_styles_option();
        $index = -1;
        /** @var mixed $item */
        foreach ($items_raw as $item) {
            $index++;
            $valid = etch_validate_batch_style($item);
            $selector_label = is_array($item) && is_string($item['selector'] ?? null) ? $item['selector'] : '';
            if ($valid instanceof WP_Error) {
                $errors[] = ['index' => $index, 'selector' => $selector_label, 'error' => $valid->get_error_message()];
                continue;
            }
            if (etch_find_style_id($styles, $valid['collection'], $valid['selector']) !== null) {
                $errors[] = [
                    'index' => $index,
                    'selector' => $valid['selector'],
                    'error' => __(
                        'A style for this selector already exists in the collection.',
                        domain: 'novamira-pro',
                    ),
                ];
                continue;
            }
            $id = etch_generate_style_id($styles);
            $styles[$id] = [
                'collection' => $valid['collection'],
                'selector' => $valid['selector'],
                'css' => $valid['css'],
                'readonly' => false,
                'type' => $valid['type'],
            ];
            $created[] = [
                'id' => $id,
                'selector' => $valid['selector'],
                'type' => $valid['type'],
                'collection' => $valid['collection'],
            ];
        }
        update_option('etch_styles', $styles);
    } finally {
        etch_option_lock_release($lock);
    }

    return [
        'created' => $created,
        'errors' => $errors,
        'created_count' => count($created),
    ];
}

/**
 * Validate one batch styles entry into a normalized record (selector, css,
 * collection, type) or a WP_Error. Reuses the same selector/css guards as the
 * single create-style. Split out to keep etch_create_styles lean.
 *
 * @return array{selector: string, css: string, collection: string, type: string}|WP_Error
 */
function etch_validate_batch_style(mixed $item): array|WP_Error
{
    if (!is_array($item)) {
        return etch_error('etch_invalid_input', __(
            'Each styles entry must be an object with selector and css.',
            domain: 'novamira-pro',
        ));
    }

    $selector = etch_selector_or_error($item['selector'] ?? null);
    if ($selector instanceof WP_Error) {
        return $selector;
    }
    $css = etch_css_or_error($item['css'] ?? null);
    if ($css instanceof WP_Error) {
        return $css;
    }
    $wrapped = etch_reject_wrapped_css($css, $selector);
    if ($wrapped instanceof WP_Error) {
        return $wrapped;
    }

    /** @var mixed $raw_collection */
    $raw_collection = $item['collection'] ?? 'default';
    $collection = is_string($raw_collection) && $raw_collection !== '' ? $raw_collection : 'default';

    return [
        'selector' => $selector,
        'css' => $css,
        'collection' => $collection,
        'type' => etch_style_type_from_selector($selector),
    ];
}

// ---------------------------------------------------------------------------
// edit-style
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-edit-style', [
    'label' => __('Edit Etch Style', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing per-class/element style record in the Etch styles layer in place (partial update). Provide style_id (from novamira/etch-get-styles) and any of css (the rule body: declarations plus optional nested rules — states, @media, descendant selectors; do not repeat the selector) or selector. Etch readonly built-ins cannot be edited. Returns { id, selector, type, collection }.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'style_id' => ['type' => 'string'],
            'css' => [
                'type' => 'string',
                'description' => 'The rule body: declarations plus optional nested rules (states like &:hover, @media breakpoints, descendant selectors). Do not repeat the outer selector.',
            ],
            'selector' => ['type' => 'string'],
        ],
        'required' => ['style_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'selector' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'collection' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_edit_style',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial update: pass only the fields you change. css is the full rule body (declarations plus nested states/@media/descendant rules; do not repeat the selector). Refuses on Etch readonly built-ins and on a selector change that would collide with another record.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Validate the optional editable style fields, returning only those given or a
 * WP_Error. Split out to keep etch_edit_style lean.
 *
 * @param array<array-key, mixed> $input
 * @return array{css?: string, selector?: string}|WP_Error
 */
function etch_style_edit_fields_or_error(array $input): array|WP_Error
{
    $fields = [];

    if (isset($input['selector'])) {
        $selector = etch_selector_or_error($input['selector']);
        if ($selector instanceof WP_Error) {
            return $selector;
        }
        $fields['selector'] = $selector;
    }

    if (isset($input['css'])) {
        $css = etch_css_or_error($input['css']);
        if ($css instanceof WP_Error) {
            return $css;
        }
        $fields['css'] = $css;
    }

    if ($fields === []) {
        return etch_error('etch_invalid_input', __(
            'Provide at least one of "css" or "selector" to edit.',
            domain: 'novamira-pro',
        ));
    }

    return $fields;
}

/**
 * Build the merged style record for an edit, guarding readonly built-ins, selector
 * collisions and the wrapped-css mistake. Returns the record or a WP_Error.
 *
 * @param array<array-key, mixed> $styles full styles map (for the collision check)
 * @param array<array-key, mixed> $style the existing record
 * @param array{css?: string, selector?: string} $fields
 * @return array{collection: string, selector: string, css: string, readonly: bool, type: string}|WP_Error
 */
function etch_merge_style(array $styles, array $style, array $fields, string $id): array|WP_Error
{
    if (etch_style_is_readonly($style)) {
        return etch_error('etch_style_readonly', __(
            'This style is an Etch read-only built-in and cannot be edited.',
            domain: 'novamira-pro',
        ));
    }

    /** @var mixed $raw_collection */
    $raw_collection = $style['collection'] ?? 'default';
    $collection = is_string($raw_collection) && $raw_collection !== '' ? $raw_collection : 'default';
    /** @var mixed $raw_selector */
    $raw_selector = $style['selector'] ?? '';
    $current_selector = is_string($raw_selector) ? $raw_selector : '';
    $selector = $fields['selector'] ?? $current_selector;

    if ($selector !== $current_selector) {
        $dupe = etch_find_style_id($styles, $collection, $selector);
        if ($dupe !== null && $dupe !== $id) {
            return etch_error('etch_style_exists', sprintf(
                /* translators: 1: selector, 2: existing style ID. */
                __('Another style (id "%2$s") already uses selector "%1$s".', domain: 'novamira-pro'),
                $selector,
                $dupe,
            ));
        }
    }

    if (isset($fields['css'])) {
        $wrapped = etch_reject_wrapped_css($fields['css'], $selector);
        if ($wrapped instanceof WP_Error) {
            return $wrapped;
        }
    }

    /** @var mixed $raw_css */
    $raw_css = $style['css'] ?? '';
    $css = $fields['css'] ?? (is_string($raw_css) ? $raw_css : '');

    return [
        'collection' => $collection,
        'selector' => $selector,
        'css' => $css,
        'readonly' => false,
        'type' => etch_style_type_from_selector($selector),
    ];
}

/**
 * @return array{id: string, selector: string, type: string, collection: string}|WP_Error
 */
function etch_edit_style(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $id = etch_style_id_or_error($input['style_id'] ?? null);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $fields = etch_style_edit_fields_or_error($input);
    if ($fields instanceof WP_Error) {
        return $fields;
    }

    $result = ['id' => $id, 'selector' => '', 'type' => '', 'collection' => 'default'];
    $lock = etch_option_lock_acquire('etch_styles');
    try {
        $styles = etch_styles_option();
        $style = etch_style_or_error($styles, $id);
        if ($style instanceof WP_Error) {
            return $style;
        }

        $merged = etch_merge_style($styles, $style, $fields, $id);
        if ($merged instanceof WP_Error) {
            return $merged;
        }

        $styles[$id] = $merged;
        update_option('etch_styles', $styles);

        $result = [
            'id' => $id,
            'selector' => $merged['selector'],
            'type' => $merged['type'],
            'collection' => $merged['collection'],
        ];
    } finally {
        etch_option_lock_release($lock);
    }

    return $result;
}

// ---------------------------------------------------------------------------
// delete-style
// ---------------------------------------------------------------------------

wp_register_ability('novamira/etch-delete-style', [
    'label' => __('Delete Etch Style', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a per-class/element style record from the Etch styles layer by id (from novamira/etch-get-styles). Etch readonly built-ins cannot be deleted. Returns { id, deleted: true }.',
        domain: 'novamira-pro',
    ),
    'category' => 'etch',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'style_id' => ['type' => 'string'],
        ],
        'required' => ['style_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Etch\etch_delete_style',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'DESTRUCTIVE. Removes the record (and its CSS) from etch_styles. Refuses on Etch readonly built-ins. Elements keeping that class fall back to other matching rules.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array{id: string, deleted: bool}|WP_Error
 */
function etch_delete_style(array $input): array|WP_Error
{
    if (!etch_active()) {
        return etch_not_active_error();
    }

    $id = etch_style_id_or_error($input['style_id'] ?? null);
    if ($id instanceof WP_Error) {
        return $id;
    }

    $lock = etch_option_lock_acquire('etch_styles');
    try {
        $styles = etch_styles_option();
        $style = etch_style_or_error($styles, $id);
        if ($style instanceof WP_Error) {
            return $style;
        }
        if (etch_style_is_readonly($style)) {
            return etch_error('etch_style_readonly', __(
                'This style is an Etch read-only built-in and cannot be deleted.',
                domain: 'novamira-pro',
            ));
        }

        unset($styles[$id]);
        update_option('etch_styles', $styles);
    } finally {
        etch_option_lock_release($lock);
    }

    return [
        'id' => $id,
        'deleted' => true,
    ];
}
