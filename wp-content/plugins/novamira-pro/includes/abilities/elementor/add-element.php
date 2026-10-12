<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

use WP_Error;

/**
 * Ability: Add an element (widget or container) to an Elementor document.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/elementor-add-element', [
    'label' => __('Add Elementor Element', domain: 'novamira-pro'),
    'description' =>
        __(
            'Adds a single element to an Elementor document. Pick element_type carefully — this is the single most common mistake in a v4 page: "widget" + widget_type to add a widget; "container" to add a LEGACY v3 flex wrapper; "e-flexbox" or "e-div-block" to add a v4 ATOMIC container (layout goes in the `styles` map, NOT in settings), offered only where novamira/elementor-check-setup reports atomic.runtime_available: true. Mixing a legacy v3 container with atomic `e-*` children produces a broken hybrid tree — pick one world per subtree. The element is inserted at the given parent_id (another container/section/column) or at the document root when parent_id is null. Pass an optional kebab-case `element_id` ("hero", "pricing-card", "footer-cta") for any element with a natural name — that slug becomes the rendered `data-id` and (for v4 atomic elements with synthesized local styles) the rendered CSS class `s-<element_id>`, so the page is inspectable / styleable from custom CSS. Omit `element_id` for anonymous elements (loop items, decorators) to fall back to Elementor\'s 7-char hex auto-id. Validation runs server-side on every call: unknown control IDs or invalid enum values abort the write and return the compact content-only schema of the target element INLINE in the error, so you can correct and retry in a single roundtrip. If you try to put a CSS property (gap, padding, flex-direction, …) into an atomic container\'s `settings`, the error includes a targeted hint pointing at the `styles` map. Tree mode is also validated before insert: unknown widget types, invalid control values, and malformed v3 dynamic tag strings abort the subtree write instead of being silently persisted, elType is required on every node (root and children alike), and ten mistyped node keys (element_id, elementId, element_settings, elementSettings, children, style, elementType, is_inner, is_locked, editorSettings) are refused by name instead of being dropped in silence — a named list, not a check of the whole node shape. Every node accepts both spellings of the type — elType/element_type and widgetType/widget_type — so a subtree is interchangeable between this ability and novamira/elementor-set-content; the top-level element_type/widget_type/element_id/settings/styles parameters are for single mode and are refused when `tree` is present. novamira/elementor-get-schema is an OPTIONAL discovery tool — use widget_types:["__container__"] on it to preview container controls, and, on atomic-capable sites, novamira/elementor-get-style-schema for atomic style prop shapes. Shape rules: use only keys present in the element\'s "controls" map; select/choose values MUST come from the control\'s "opts"; switchers use "rv" for on and "" for off; typography requires typography_typography="custom" first; dimensions are {unit,top,right,bottom,left,isLinked}; sliders are {size,unit}; colors are #RRGGBB; v3 controls carrying an "r" flag in the schema accept responsive overrides via the suffixed key `<key>_<breakpoint>` (e.g. typography_font_size_tablet, padding_mobile, _padding_widescreen) — `r:1` means every breakpoint is allowed, while `r:{min:<bp>}` / `r:{max:<bp>}` / `r:{min,max}` restrict the suffix to a closed window in the canonical size order (mobile < mobile_extra < tablet < tablet_extra < laptop < desktop < widescreen). Breakpoint names are the v4 ones listed in check-setup.kit.active_breakpoints and the same value shape applies to every variant; ATOMIC (v4) widgets accept ergonomic scalar values which the server auto-wraps into {"$$type": "<prop_key>", "value": <scalar>} — for both `settings` AND inside the `styles` map\'s `props` (e.g. color:"#FFFFFF", font-size:72, padding:{block-start:16,...}); or you can pass the wrapped shape directly; v4 atomic responsive uses the `styles` map\'s `variants[]` with `meta.breakpoint` (NOT suffixed keys); CONTENT FIELDS — single-line text controls (heading.title, button.text, icon-list[].text, image.caption, …) take plain text only; wysiwyg controls (text-editor.editor, testimonial.testimonial_content, …) take inline formatting only (`<strong>`, `<em>`, `<a>`, `<br>`); NEVER wrap content in inline-styled `<p style="…">…</p>` or other layout markup — alignment goes in `align`, font sizing/weight/family in `typography_*`, max-width in `_element_custom_width`, spacing in `_padding`/`_margin` (all responsive via the suffix shape above); the dedicated `html` widget IS the place for arbitrary HTML; the server also fills schema defaults for any atomic control you don\'t supply (link, tag, attributes, classes, …), so passing minimal `settings` is safe — the persisted node will still load cleanly in the v4 editor; atomic containers (e-flexbox/e-div-block) have ONLY structural settings (tag, link, classes, attributes) — all layout, spacing, background, typography goes in `styles`; do not emit controls whose "if" condition is not satisfied.',
            domain: 'novamira-pro',
        )
            . el_not_persisted_description_sentence()
            . el_not_persisted_tree_conversion_sentence(),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The WordPress post ID.'],
            'element_type' => [
                'type' => 'string',
                'description' => 'What to insert: "widget" (requires widget_type), "container" (legacy v3 flex wrapper), and — only where novamira/elementor-check-setup reports atomic.runtime_available: true — "e-flexbox" / "e-div-block" (v4 atomic containers).',
                'enum' => el_element_type_enum(),
            ],
            'element_id' => [
                'type' => 'string',
                'description' => 'Optional kebab-case slug to use as the element id (e.g. "hero", "pricing-card", "footer-cta"). Single mode only — in tree mode it is REFUSED, because the id belongs in tree.id. When omitted Elementor\'s 7-char hex auto-id is used. The id is what shows up as `data-id` on the rendered HTML, and for v4 atomic elements with synthesized local styles it also drives the rendered CSS class name (`.s-<element_id>`) — so passing a semantic slug is what makes the page inspectable. Rules: 2-50 chars, must start with a lowercase letter, then `[a-z0-9_-]+`, cannot be `container`. Must be unique on the page (the call fails if another element already uses the same id).',
            ],
            'widget_type' => [
                'type' => 'string',
                'description' =>
                    'Elementor widget type (e.g. '
                        . el_widget_type_examples()
                        . '). REQUIRED when element_type="widget", IGNORED when element_type="container". Also accepted as `widgetType`. Use novamira/elementor-get-schema to inspect control maps.',
            ],
            'elType' => [
                'type' => 'string',
                'description' => 'Accepted alias of `element_type`, so the Elementor-native spelling used inside document nodes works here too. Same values; `element_type` wins when both are given. REFUSED in tree mode — the type belongs in tree.elType.',
                'enum' => el_element_type_enum(),
            ],
            'widgetType' => [
                'type' => 'string',
                'description' => 'Accepted alias of `widget_type`, the Elementor-native spelling. Same values; `widget_type` wins when both are given. REFUSED in tree mode — the widget name belongs in tree.widgetType.',
            ],
            'parent_id' => [
                'type' => ['string', 'null'],
                'description' => 'Parent element ID (another container/section/column). Pass null to add at the document root.',
            ],
            'settings' => [
                'type' => 'object',
                'description' => 'Element settings object. Single mode only — in tree mode it is REFUSED, because the settings belong in tree.settings. For widgets, the shape depends on the widget type — scalars for v3, scalars or wrapped pairs for atomic v4. For containers, use layout / padding / background controls. Unknown keys and invalid enum values cause the call to fail with the schema included in the error.',
            ],
            'styles' => [
                'type' => 'object',
                'description' => 'Per-element styles to attach to the new element. Single mode only — in tree mode it is REFUSED, because the styles belong in tree.styles (where both shapes below are accepted as well). TWO shapes are accepted. (1) ERGONOMIC — a flat `{css-prop: value}` map, the same shape `novamira/elementor-create-atomic-widget.base_styles` and `novamira/elementor-create-global-class.styles` take (e.g. {"background":{"color":"#ffffff"}, "padding":28, "border-radius":12}); scalars auto-wrap, compound types (background/border/box-shadow/…) need their nested shape, long-form `{$$type, value}` also works. Synthesized into a single desktop/null-state variant under `s-<element_id>` — use this when one breakpoint is enough (the common case). The rendered CSS class name follows the style `id` (`.s-<element_id>`), so to get a readable class name pass a semantic `element_id` on this same call. (2) VERBOSE — a `{"<style_id>": {id, type, label, variants: [{meta: {breakpoint, state}, props: {CSS_PROP: <value>, ...}}]}}` map when you need explicit control over breakpoints / states / multiple variants. Non-default STATES (hover, active, focus, focus-visible, checked, e--selected; null = default) are set ONLY via `variants[].meta.state` in this verbose shape — there is no top-level state parameter, and `styles` must always be an object keyed by style id, never a list of variants. Hover example: {"s-btn": {"id": "s-btn", "type": "class", "label": "local", "variants": [{"meta": {"breakpoint": "desktop", "state": "hover"}, "props": {"background": {"color": "#ff0000"}}}]}}. `id` (and not `label`) drives the rendered CSS class name — the value must be 2–50 chars, `[a-zA-Z0-9_-]+`, can\'t start with a digit / `--` / `-<digit>`, can\'t be `container`; `label` is editor-only metadata, set it to `"local"` to match Elementor\'s native convention for per-element styles. Shapes are auto-detected: every top-level key that matches a v4 Style Schema CSS property (and has no `variants` field) routes through the ergonomic path; otherwise verbose. Only valid on v4 atomic elements (e-* widgets, e-flexbox, e-div-block), which require the atomic runtime — for atomic containers this is where layout/background/typography go (settings carries only structural fields). The server validates via Style_Parser and syncs the style IDs into settings.classes so the styles attach on render.',
            ],
            'position' => [
                'type' => 'integer',
                'description' => 'Zero-based insertion index in the parent\'s children. Defaults to appending at the end.',
            ],
            'tree' => [
                'type' => 'object',
                'description' => 'A complete element subtree (with nested `elements` children) to insert as-is, inserted at parent_id (or the document root). In tree mode the subtree is the whole description of the element, so the top-level element_type / elType / widget_type / widgetType / element_id / settings / styles parameters are REFUSED rather than ignored — put those values in the tree node (tree.elType, tree.widgetType, tree.id, tree.settings, tree.styles) and keep only post_id, tree, parent_id and position at the top level. Each node takes `elType` or `element_type` and `widgetType` or `widget_type` — both spellings, at every depth, the native one winning a conflict — so a subtree accepted by novamira/elementor-set-content is accepted here unchanged, and vice versa. elType is REQUIRED on every node, root and children alike — a node carrying a `widgetType` has it read from that (the only envelope Elementor stores a widget in), but a node naming no type at all is refused here. That last point is a DELIBERATE difference from novamira/elementor-set-content, which treats a node with no type as a widget: writing a whole document is the surface that owns that default, and giving it to this ability would mean guessing a type for a subtree inserted into a page that already exists. State the type and both behave identically. Use this to insert pre-built subtrees — a v4 atomic subtree only where novamira/elementor-check-setup reports atomic.runtime_available: true, a v3 container subtree otherwise. The server still normalizes and validates the subtree before insert: unknown widget types, invalid control values, and malformed v3 dynamic tag strings fail hard with structured errors. Use the elementor-build-page skill to build valid trees.',
                'properties' => el_node_schema_properties(),
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'element_id' => ['type' => 'string'],
            'element_type' => ['type' => 'string'],
            'widget_type' => [
                'type' => 'string',
                'description' => 'Only present when element_type="widget".',
            ],
            EL_NOT_PERSISTED_KEY => el_not_persisted_output_schema(),
            'warnings' => el_markup_warnings_schema(),
            'dropped_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Unknown control IDs. Only present on validation failures.',
            ],
            'invalid_values' => [
                'type' => 'array',
                'description' => 'Validation failures, two entry variants: enum violations {key, value, opts}; type violations such as a non-string styles[*].variants[*].meta.breakpoint {key, value, expected}. Only present on validation failures.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'Path of the rejected value (control id, or styles["<id>"].variants[i].meta.breakpoint).',
                        ],
                        'value' => [
                            'type' => ['array', 'object', 'string', 'number', 'integer', 'boolean', 'null'],
                            'description' => 'The rejected value as received.',
                        ],
                        'opts' => ['type' => 'array', 'description' => 'Enum violations only: the allowed values.'],
                        'expected' => [
                            'type' => 'string',
                            'description' => 'Type violations only (e.g. a non-string breakpoint): the accepted type(s), e.g. "string|null".',
                        ],
                    ],
                    'required' => ['key', 'value'],
                ],
            ],
            'unknown_style_properties' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Ergonomic-styles validation failure: CSS property names that are not in the v4 Style Schema.',
            ],
            'invalid_style_values' => [
                'type' => 'object',
                'description' => 'Ergonomic-styles validation failure: per-property {received_type, expected_types, reason} describing why the value failed schema validation.',
            ],
            'schema' => [
                'type' => 'object',
                'description' => 'Compact schema of the target element. Only present on validation failures.',
            ],
            'errors' => [
                'type' => 'array',
                'description' => 'For tree-mode validation failures: per-element error records ({element_id, widget_type, dropped_keys, invalid_values, unknown_widget_type?}).',
            ],
            'schemas' => [
                'type' => 'object',
                'description' => 'For tree-mode validation failures: compact schemas keyed by widget type so the caller can correct and retry.',
            ],
            'dynamic_tag_errors' => [
                'type' => 'array',
                'description' => 'For tree mode: v3 dynamic tag strings that could not be parsed. Each entry contains {element_id, widget_type, setting_name, tag, reason}.',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\elementor_add_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function elementor_add_element(array $input): array|WP_Error
{
    // Tree mode: insert a pre-built subtree as-is after normalizing and validating it.
    if (is_array($input['tree'] ?? null) && $input['tree'] !== []) {
        return el_add_tree_element($input);
    }

    // Single mode names the type with the friendly snake_case keys, but a caller
    // coming from a document tree — or from this ability's own tree mode —
    // reaches for Elementor's own spellings. Both are declared in the schema and
    // mapped onto the primary key here, so the TYPE of an element is spelled the
    // same way wherever it is named. That is the extent of the claim: the two
    // node-shaped surfaces (set-content content, this ability's tree) take the
    // full node vocabulary, while single mode and edit-element take flat
    // parameters that keep their own names for everything else — an element's id
    // is `element_id` here and `id` inside a node, and edit-element never
    // receives a node at all. `element_type` / `widget_type` are this surface's
    // primary names and win a conflict, exactly as `elType` / `widgetType` win
    // one inside a node.
    $input = el_apply_add_element_type_aliases($input);

    // A scalar `settings` / `styles` passes schema validation ("" is accepted
    // for `type: object`) and used to be coerced to an empty map.
    $shape_error = el_find_non_object_input($input, ['settings', 'styles']);
    if ($shape_error !== null) {
        return $shape_error;
    }

    $parsed = el_parse_add_element_input($input);
    if (array_key_exists('error', $parsed)) {
        return ['success' => false, 'error' => $parsed['error']];
    }

    /** @var array{post_id: int, element_type: string, widget_type: string, settings: array<string, mixed>, styles: array<string, mixed>, parent_id: string|null, position: int, element_id: string} $parsed */
    if ($parsed['element_type'] === 'widget') {
        return el_add_widget_element($parsed);
    }

    if (in_array($parsed['element_type'], NOVAMIRA_ATOMIC_CONTAINER_TYPES, strict: true)) {
        return el_add_atomic_container_element($parsed);
    }

    return el_add_container_element($parsed);
}

/**
 * Build a new widget node, validate its settings, and insert it into the tree.
 *
 * @param array{post_id: int, element_type: string, widget_type: string, settings: array<string, mixed>, styles: array<string, mixed>, parent_id: string|null, position: int, element_id: string} $parsed
 * @return array<string, mixed>|WP_Error
 */
function el_add_widget_element(array $parsed): array|WP_Error
{
    $widget_type = $parsed['widget_type'];
    if ($widget_type === '') {
        return [
            'success' => false,
            'error' => 'Parameter "widget_type" (or "widgetType") is required when element_type="widget".',
        ];
    }

    if ($widget_type === NOVAMIRA_COMPACT_SCHEMA_CONTAINER_KEY) {
        return [
            'success' => false,
            'error' => '"__container__" is a schema-lookup pseudo-name, not a real widget. To insert a container call this ability with element_type="container" instead.',
        ];
    }

    $schema = el_extract_schema($widget_type);
    if ($schema === null) {
        // An `e-*` name on a site without the atomic runtime is not a typo —
        // say so instead of pointing at the widget list.
        $atomic_error = el_atomic_unavailable_error([$widget_type]);
        if ($atomic_error !== null) {
            return $atomic_error;
        }
        return [
            'success' => false,
            'error' => sprintf(
                'Unknown widget_type "%s". It is not registered with Elementor on this site. Use novamira/elementor-get-schema action:"list" to enumerate every available widget.',
                $widget_type,
            ),
            'widget_type' => $widget_type,
        ];
    }

    $validation = el_validate_settings($parsed['settings'], $schema);
    if (!$validation['ok']) {
        return build_single_element_error_response('widget', $widget_type, $validation);
    }

    $settings = el_fill_atomic_schema_defaults($validation['settings'], $schema);

    $id_resolution = el_resolve_new_element_id($parsed['post_id'], $parsed['element_id']);
    if (!$id_resolution['ok']) {
        return ['success' => false, 'error' => $id_resolution['error']];
    }
    $new_id = $id_resolution['id'];
    $node = [
        'id' => $new_id,
        'elType' => 'widget',
        'widgetType' => $widget_type,
        'settings' => $settings,
        'elements' => [],
    ];

    $with_styles = el_attach_add_element_styles($node, $parsed['styles']);
    if (array_key_exists('error_response', $with_styles)) {
        return $with_styles['error_response'];
    }
    $node = $with_styles['node'] ?? $node;

    $deep_error = el_deep_validate_single_element($node, element_type: 'widget');
    if ($deep_error !== null) {
        return $deep_error;
    }

    // Measured against the caller's own settings, before schema defaults
    // were filled in, and the styles as sent, before Style_Parser.
    $sent = [$new_id => el_sent_fields_of_single(
        $parsed['settings'],
        $validation['settings'],
        $with_styles['sent_styles'] ?? [],
    )];
    return el_insert_and_write($parsed, $node, $sent, [
        'element_id' => $new_id,
        'element_type' => 'widget',
        'widget_type' => $widget_type,
    ]);
}

/**
 * Build a new container node, validate its settings, and insert it into the tree.
 *
 * @param array{post_id: int, element_type: string, widget_type: string, settings: array<string, mixed>, styles: array<string, mixed>, parent_id: string|null, position: int, element_id: string} $parsed
 * @return array<string, mixed>|WP_Error
 */
function el_add_container_element(array $parsed): array|WP_Error
{
    $schema = el_extract_schema(NOVAMIRA_COMPACT_SCHEMA_CONTAINER_KEY);
    if ($schema === null) {
        return [
            'success' => false,
            'error' => EL_CONTAINER_UNAVAILABLE_MESSAGE,
        ];
    }

    $validation = el_validate_settings($parsed['settings'], $schema);
    if (!$validation['ok']) {
        return build_single_element_error_response('container', NOVAMIRA_COMPACT_SCHEMA_CONTAINER_KEY, $validation);
    }

    $settings = el_fill_atomic_schema_defaults($validation['settings'], $schema);

    $id_resolution = el_resolve_new_element_id($parsed['post_id'], $parsed['element_id']);
    if (!$id_resolution['ok']) {
        return ['success' => false, 'error' => $id_resolution['error']];
    }
    $new_id = $id_resolution['id'];
    $node = [
        'id' => $new_id,
        'elType' => 'container',
        'settings' => $settings,
        'elements' => [],
    ];

    $with_styles = el_attach_add_element_styles($node, $parsed['styles']);
    if (array_key_exists('error_response', $with_styles)) {
        return $with_styles['error_response'];
    }
    $node = $with_styles['node'] ?? $node;

    // Measured against the caller's own settings, before schema defaults
    // were filled in, and the styles as sent, before Style_Parser.
    $sent = [$new_id => el_sent_fields_of_single(
        $parsed['settings'],
        $validation['settings'],
        $with_styles['sent_styles'] ?? [],
    )];
    return el_insert_and_write($parsed, $node, $sent, [
        'element_id' => $new_id,
        'element_type' => 'container',
    ]);
}

/**
 * Build a new atomic container node (e-flexbox / e-div-block), validate its
 * settings, and insert it into the tree.
 *
 * @param array{post_id: int, element_type: string, widget_type: string, settings: array<string, mixed>, styles: array<string, mixed>, parent_id: string|null, position: int, element_id: string} $parsed
 * @return array<string, mixed>|WP_Error
 */
function el_add_atomic_container_element(array $parsed): array|WP_Error
{
    $element_type = $parsed['element_type'];
    $schema = el_extract_schema($element_type);
    if ($schema === null) {
        // Same explanation tree mode gives for the same site state.
        $atomic_error = el_atomic_unavailable_error([$element_type]);
        if ($atomic_error !== null) {
            return $atomic_error;
        }
        return [
            'success' => false,
            'error' => sprintf(
                'Atomic container type "%s" is not available on this Elementor installation.',
                $element_type,
            ),
        ];
    }

    $validation = el_validate_settings($parsed['settings'], $schema);
    if (!$validation['ok']) {
        return build_single_element_error_response($element_type, $element_type, $validation);
    }

    $settings = el_fill_atomic_schema_defaults($validation['settings'], $schema);

    $id_resolution = el_resolve_new_element_id($parsed['post_id'], $parsed['element_id']);
    if (!$id_resolution['ok']) {
        return ['success' => false, 'error' => $id_resolution['error']];
    }
    $new_id = $id_resolution['id'];
    $node = [
        'id' => $new_id,
        'elType' => $element_type,
        'settings' => $settings,
        'elements' => [],
    ];

    $with_styles = el_attach_add_element_styles($node, $parsed['styles']);
    if (array_key_exists('error_response', $with_styles)) {
        return $with_styles['error_response'];
    }
    $node = $with_styles['node'] ?? $node;

    $deep_error = el_deep_validate_single_element($node, $element_type);
    if ($deep_error !== null) {
        return $deep_error;
    }

    // Measured against the caller's own settings, before schema defaults
    // were filled in, and the styles as sent, before Style_Parser.
    $sent = [$new_id => el_sent_fields_of_single(
        $parsed['settings'],
        $validation['settings'],
        $with_styles['sent_styles'] ?? [],
    )];
    return el_insert_and_write($parsed, $node, $sent, [
        'element_id' => $new_id,
        'element_type' => $element_type,
    ]);
}

/**
 * Insert a pre-built subtree (with nested children) into the page. The server
 * normalizes the subtree and validates its element settings before writing, so
 * malformed dynamic tags, unknown widget types, and invalid control values
 * fail hard instead of being silently persisted.
 *
 * The tree must have at minimum an `elType` field. Missing `id` fields are
 * auto-generated. The `elements` array may contain arbitrarily nested children.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function el_add_tree_element(array $input): array|WP_Error
{
    $ignored_error = el_reject_tree_mode_element_params($input);
    if ($ignored_error !== null) {
        return $ignored_error;
    }

    $parsed = el_parse_tree_insert_input($input);
    if (array_key_exists('error', $parsed)) {
        return ['success' => false, 'error' => $parsed['error']];
    }

    /** @var array{post_id: int, tree: array<string, mixed>, parent_id: string|null, position: int} $parsed */
    // Input boundary on the RAW subtree: nested `elements` / `settings` keys
    // that are present must be arrays, otherwise el_ensure_tree_ids() would
    // silently replace a stringified child list with "no children".
    $invalid_shapes = el_find_invalid_tree_shapes($parsed['tree']);
    if ($invalid_shapes !== []) {
        return el_build_invalid_tree_shape_error('Invalid tree', $invalid_shapes);
    }

    // A node key that is close to a real one but not one of them is a caller
    // error, not an extra: it was dropped while the insert still reported
    // success.
    $confusable = el_find_confusable_node_keys($parsed['tree'], prefix: 'tree.');
    if ($confusable !== []) {
        return el_build_confusable_node_key_error(
            'Invalid tree',
            $confusable,
            el_confusable_residue_paths($confusable, $parsed['tree'], prefix: 'tree.'),
        );
    }

    // A node that carries a widgetType has stated what it is, so read the
    // envelope from it instead of demanding the caller repeat it. This is not
    // set-content's `widget` default, which invents a type for a node that
    // states nothing: it also un-breaks the documents this ability itself wrote
    // before the check below existed, whose typeless children are stored as
    // `elType: null`.
    $tree = el_derive_tree_element_types($parsed['tree']);

    // The type requirement applies to every node, not only to the root. A
    // child without one used to be accepted and persisted with `elType: null`
    // — the exact shape the root check exists to forbid.
    $typeless = el_find_typeless_tree_nodes($tree, prefix: 'tree');
    if ($typeless !== []) {
        return el_build_typeless_node_error($typeless);
    }

    $prepared = el_prepare_tree_for_insert($tree);
    if (array_key_exists('response', $prepared)) {
        return $prepared['response'];
    }

    /** @var array{tree: array<string, mixed>, sent: array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}>, lost: array<string, list<string>>} $prepared */
    return el_insert_tree_and_write($parsed, $prepared['tree'], $prepared['sent'], $prepared['lost']);
}

/**
 * Normalize and validate a tree-mode subtree before insertion.
 *
 * On success `sent` names the fields the caller sent (el_sent_fields()) where
 * they are after the boxed split, and `lost` the style props that split could
 * not keep in their requested variant, for the not-persisted report.
 *
 * @param array<string, mixed> $tree
 * @return array{tree: array<string, mixed>, sent: array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}>, lost: array<string, list<string>>}|array{response: array<string, mixed>|WP_Error}
 */
function el_prepare_tree_for_insert(array $tree): array
{
    // Input boundary first, on the RAW tree: a malformed breakpoint (false /
    // int / array) is a caller error reported by path, never coerced — and it
    // must not reach the finalize pipeline, whose Style_Parser validation fails
    // open when Elementor's schema classes are absent.
    $invalid_breakpoints = el_find_invalid_tree_breakpoints($tree);
    if ($invalid_breakpoints !== []) {
        return ['response' => el_build_invalid_breakpoint_response('Invalid styles in tree', $invalid_breakpoints)];
    }

    // V3-only steps run first so the universal finalize sees the final
    // node shape: the boxed splitter has produced its outer/inner pair,
    // and the v3 settings translator has drained the legacy keys into
    // wrapped style props. Then the universal pipeline (ensure ids,
    // normalize widget types, coerce settings, normalize breakpoints,
    // sanitize CSS class ids, sync classes) finishes the job.
    $tree = el_ensure_tree_ids($tree);

    // Integer style ids (a list-shaped `styles`, or a `"0"` key) must be
    // rejected before any styles walker runs: the boxed splitter, the CSS
    // class-id sanitizer and the validators all take string ids.
    $style_id_errors = el_collect_tree_style_id_errors($tree);
    if ($style_id_errors !== []) {
        return ['response' => el_build_tree_style_id_error_response($style_id_errors)];
    }

    // The flat `{css-prop: value}` styles shape means here what it means in
    // single mode and in edit-element. Runs after el_ensure_tree_ids() because
    // the synthesized style id is derived from the node's id.
    $ergonomic_errors = [];
    $tree = el_expand_ergonomic_tree_styles($tree, prefix: 'tree', errors: $ergonomic_errors);
    if ($ergonomic_errors !== []) {
        return ['response' => el_build_ergonomic_tree_styles_error($ergonomic_errors)];
    }

    // Two style ids that would be stored under one name are refused before
    // anything moves them (el_style_id_collisions()).
    $collisions = el_collect_tree_style_id_collisions($tree);
    if ($collisions !== []) {
        return ['response' => el_build_tree_style_id_collision_response($collisions)];
    }

    // What the caller sent, ids assigned and ergonomic styles expanded. The
    // boxed splitter and the v3 translator then move fields between nodes;
    // el_follow_moved_fields() follows each one to where it went, and names
    // a style prop that no longer exists in its requested variant as lost.
    $sent_before = el_sent_fields([$tree]);
    $tree = el_split_boxed_containers($tree);
    $tree = el_translate_v3_container_settings($tree);
    $followed = el_follow_moved_fields($sent_before, el_sent_fields([$tree]));

    $dynamic_tag_errors = [];
    $tree = el_finalize_atomic_node($tree, $dynamic_tag_errors);

    $validation = el_validate_tree([$tree]);
    if ($dynamic_tag_errors !== [] || !$validation['ok']) {
        // An unresolvable `e-*` type on a site without the atomic runtime is a
        // site-state problem, not a payload typo — report it as such.
        $atomic_error = el_atomic_unavailable_tree_error($validation['errors']);
        return ['response' => $atomic_error ?? build_tree_validation_error_response($validation, $dynamic_tag_errors)];
    }

    return ['tree' => $validation['tree'][0], 'sent' => $followed['sent'], 'lost' => $followed['lost']];
}

/**
 * Shared tail for tree-mode inserts after the subtree passed validation.
 *
 * @param array{post_id: int, tree: array<string, mixed>, parent_id: string|null, position: int} $parsed
 * @param array<string, mixed> $tree
 * @param array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}> $sent
 * @param array<string, list<string>> $lost_before_write Style props the boxed split lost before the write.
 * @return array<string, mixed>|WP_Error
 */
function el_insert_tree_and_write(
    array $parsed,
    array $tree,
    array $sent = [],
    array $lost_before_write = [],
): array|WP_Error {
    $root_id = (string) $tree['id'];
    $element_type = (string) ($tree['elType'] ?? '');

    [$elements, $error] = el_read_page($parsed['post_id']);
    if ($elements === null) {
        return ['success' => false, 'error' => $error ?? 'Unknown error.'];
    }

    $insert_error = el_insert_into_tree(
        $elements,
        $tree,
        $parsed['parent_id'],
        $parsed['position'],
        $parsed['post_id'],
    );
    if ($insert_error !== null) {
        return ['success' => false, 'error' => $insert_error];
    }

    $result = el_write_page($parsed['post_id'], $elements);
    if ($result instanceof WP_Error) {
        return $result;
    }

    /** @var string|null $widget_type */
    $widget_type = $tree['widgetType'] ?? null;

    $response = [
        'success' => true,
        'element_id' => $root_id,
        'element_type' => $element_type,
    ];
    if (is_string($widget_type) && $widget_type !== '') {
        $response['widget_type'] = $widget_type;
    }

    // First what was not stored, then what will render differently — and no
    // rendering claim for a field that was not stored (el_with_write_reports()).
    return el_with_write_reports($response, $parsed['post_id'], $sent, $lost_before_write, [
        'elements' => [$tree],
        'sent' => null,
    ]);
}

/**
 * Recursively normalize atomic widget elTypes in the tree.
 *
 * Agents pass `elType: "e-heading"` which is the ergonomic form, but
 * Elementor internally stores atomic widgets as `elType: "widget"` +
 * `widgetType: "e-heading"` (same as legacy widgets). Atomic containers
 * like e-flexbox/e-div-block are true element types and stay as-is.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_normalize_tree_widget_types(array $node): array
{
    $el_type = (string) ($node['elType'] ?? '');

    // Atomic widget types (e-heading, e-paragraph, …) are registered with
    // the widgets manager, not the elements manager. They need the standard
    // widget envelope: elType "widget" + widgetType "<name>".
    // Check at runtime against elements_manager so new Elementor versions
    // with additional element types (e-tabs, e-accordion, …) are handled
    // automatically without hardcoded lists.
    if (str_starts_with($el_type, 'e-') && !el_is_element_type($el_type)) {
        $node['widgetType'] = $el_type;
        $node['elType'] = 'widget';
    }

    // Move node-level __dynamic__ into settings. Agents may pass it at
    // either level, but Elementor and the coercion pipeline both expect
    // it inside settings.
    if (is_array($node['__dynamic__'] ?? null) && $node['__dynamic__'] !== []) {
        /** @var array<string, mixed> $settings */
        $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];
        $settings['__dynamic__'] = $node['__dynamic__'];
        $node['settings'] = $settings;
        unset($node['__dynamic__']);
    }

    /** @var list<array<string, mixed>> $children */
    $children = is_array($node['elements'] ?? null) ? $node['elements'] : [];
    $new_children = [];
    foreach ($children as $child) {
        $new_children[] = el_normalize_tree_widget_types($child);
    }
    $node['elements'] = $new_children;

    return $node;
}

/**
 * Check whether an elType is a registered Elementor element type (as
 * opposed to a widget type). Element types live under elements_manager
 * and include containers (e-flexbox, e-div-block) and structural elements
 * (e-tabs, e-tab, etc.). Widget types (e-heading, e-paragraph, …) are
 * NOT element types.
 */
function el_is_element_type(string $el_type): bool
{
    // Live lookup, no caching: element types can be registered or
    // unregistered within a request, and the tree validator relies on the
    // current registry state.
    if ($el_type === '' || !class_exists('Elementor\\Plugin')) {
        return false;
    }
    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;
    /** @var object|null $em */
    $em = $plugin->elements_manager ?? null;
    if (!is_object($em) || !method_exists($em, 'get_element_types')) {
        return false;
    }
    return is_object($em->get_element_types($el_type));
}

/**
 * Recursively split atomic containers that carry a v3 `boxed_width`
 * setting into the canonical two-level outer/inner wrapper pattern.
 *
 * In v3, a `container` with `boxed_width: {size: 1300, unit: "px"}`
 * renders its background full-bleed but constrains its children to the
 * boxed width. Atomic v4 containers do not have an equivalent setting:
 * the same effect requires an outer full-width flexbox (background,
 * padding, justify-content: center) wrapping an inner max-width flexbox
 * (layout, gap). Agents converting v3 pages routinely either skip the
 * pattern (children render full-width) or apply it inconsistently across
 * sibling sections. Doing the split here makes the decision deterministic.
 *
 * The agent passes a single flat e-flexbox with `settings.boxed_width`
 * alongside any other v3 settings (background_color, padding,
 * flex_direction, ...). This step rewrites that node into:
 *
 *   outer e-flexbox (id preserved)
 *     ├─ visual settings (background_color, padding, margin, min_height,
 *     │   max_height, border_radius, tag/html_tag) + `justify_content: "center"`
 *     └─ inner e-flexbox (id `<original>-in`)
 *         ├─ layout settings (flex_direction, flex_wrap, flex_align_items,
 *         │   flex_justify_content, flex_gap) + `width: 100%`
 *         │   + `max_width: <boxed_width>`
 *         └─ original children
 *
 * The downstream `el_translate_v3_container_settings` step then turns
 * those v3-shaped settings on outer + inner into the right v4 style
 * props on each level.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_split_boxed_containers(array $node, string $context = 'top-level'): array
{
    $just_split = false;
    $el_type = (string) ($node['elType'] ?? '');
    if (in_array($el_type, NOVAMIRA_ATOMIC_CONTAINER_TYPES, strict: true)) {
        [$node, $just_split] = el_split_boxed_container_on_node($node, $context);
    }

    // Children become "inside a wrapper" when either this node just
    // produced one (split happened now) or the caller already was
    // inside one. A nested container without its own `boxed_width` then
    // inherits the boxing instead of producing a redundant outer/inner
    // of its own — matching v3's content-width inheritance rule and,
    // critically, preventing the walker from re-splitting the inner we
    // just generated.
    $child_context = $just_split || $context === 'inside-wrapper' ? 'inside-wrapper' : 'top-level';

    /** @var list<array<string, mixed>> $children */
    $children = is_array($node['elements'] ?? null) ? $node['elements'] : [];
    $new_children = [];
    foreach ($children as $child) {
        $new_children[] = el_split_boxed_containers($child, $child_context);
    }
    $node['elements'] = $new_children;

    return $node;
}

/**
 * Split a single atomic container node into the outer/inner wrapper
 * pattern when the container is boxed. Returns a tuple of the (maybe
 * rewritten) node and a flag telling the caller whether a split
 * actually happened — the walker uses that flag to know whether the
 * children are now inside a boxed wrapper and should not split again.
 *
 * `$context` is `"top-level"` (the node is rendered directly, so it
 * inherits no boxing from anywhere) or `"inside-wrapper"` (the node is
 * a descendant of a container that the walker has already split and is
 * therefore already inside a max-width band — a further split would
 * double-wrap and, if the inner is itself walked, recurse forever).
 *
 * Two server-side v3 default synthesizers run at the top, gated on a
 * v3-conversion-context marker (at least one v3-only key present,
 * unwrapped, in settings). Native v4 atomic writes pass through
 * unchanged because none of the markers match.
 *
 *   A. `flex_direction` absent → synthesize `"column"`. v3 containers
 *      default to column, v4 e-flexbox to the CSS default (row).
 *
 *   B. `boxed_width` absent AND `content_width != "full"` AND context
 *      is top-level → read `container_width` from the active
 *      Elementor kit, split at that value. Mirrors v3's default-boxed
 *      behavior without forcing the agent to read the kit themselves.
 *
 * Resolution rules (deterministic, mirror v3 semantics):
 *   1. Explicit `boxed_width<suffix>` → split at those sizes.
 *   2. Explicit `content_width: "full"` → no split (full-bleed is the
 *      agent's stated intent).
 *   3. `$context === "inside-wrapper"` → no split (inherit boxing;
 *      avoids a second redundant outer/inner).
 *   4. v3 context AND top-level AND kit available → split at kit
 *      container_width (synthesizer B above).
 *   5. Native v4 context OR kit value unavailable → no split, fallback
 *      to full-bleed. A misconfigured site is better than a broken
 *      write.
 *
 * @param array<string, mixed> $node
 * @return array{0: array<string, mixed>, 1: bool}
 */
function el_split_boxed_container_on_node(array $node, string $context): array
{
    /** @var array<string, mixed> $settings */
    $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];

    $settings = el_apply_v3_container_defaults($settings);

    [$boxed_sizes_by_bp, $settings] = el_extract_boxed_widths_by_breakpoint($settings);
    $boxed_sizes_by_bp = el_maybe_infer_default_boxed_width($boxed_sizes_by_bp, $settings, $context);

    // `content_width` keys never survive to v4 — the wrapper pattern
    // replaces them when split, and a full-width container doesn't
    // need the legacy toggle either.
    foreach (array_merge([''], array_keys(el_v3_breakpoint_suffixes())) as $suffix) {
        unset($settings['content_width' . $suffix]);
    }

    if ($boxed_sizes_by_bp === []) {
        // Explicitly full-width, inherited boxing, or kit value
        // unavailable — leave the node as-is. Persist any
        // boxed_width/content_width keys the extractor drained.
        $node['settings'] = $settings;
        return [$node, false];
    }

    [$outer_settings, $inner_settings] = el_partition_boxed_settings($settings, $boxed_sizes_by_bp);

    $el_type = (string) ($node['elType'] ?? '');
    $original_id = (string) ($node['id'] ?? 'el');

    /** @var list<array<string, mixed>> $original_children */
    $original_children = is_array($node['elements'] ?? null) ? $node['elements'] : [];
    /** @var array<string, mixed> $original_styles */
    $original_styles = is_array($node['styles'] ?? null) ? $node['styles'] : [];

    // The agent often pre-built `styles` on the boxed container including
    // child-layout properties (gap between cards, flex-direction of the
    // child row, flex-wrap, align-items). On the outer those props are
    // dead weight: the outer owns exactly one child (the inner wrapper),
    // so a `gap` between siblings has nothing to act on. Move those
    // properties onto the inner instead, where the agent's actual
    // children live.
    [$original_styles, $extra_inner_props_by_breakpoint] = el_extract_layout_props_from_styles($original_styles);
    $inner_styles = el_build_inner_layout_style($original_id, $extra_inner_props_by_breakpoint);

    $inner_node = [
        'id' => el_boxed_inner_id($original_id),
        'elType' => $el_type,
        'settings' => $inner_settings,
        'elements' => $original_children,
        'styles' => $inner_styles,
    ];

    $outer_node = [
        'id' => $original_id,
        'elType' => $el_type,
        'settings' => $outer_settings,
        'elements' => [$inner_node],
        // Remaining styles (background, padding, margin, justify-content
        // for centering the inner, etc.) stay on the outer.
        'styles' => $original_styles,
    ];

    return [$outer_node, true];
}

/**
 * Walk every variant in every style of an outer-bound styles map and
 * pull out child-layout properties that only make sense on the inner.
 * Returns the outer styles (with those props removed) and a map of
 * breakpoint key → props that the caller will graft onto the inner.
 *
 * The breakpoint key is the meta breakpoint string, with `__null__`
 * standing in for a null/desktop breakpoint so it can be used as an
 * array key.
 *
 * @param array<string, mixed> $styles
 * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>}
 */
function el_extract_layout_props_from_styles(array $styles): array
{
    /** @var array<string, array<string, mixed>> $extracted */
    $extracted = [];

    foreach (array_keys($styles) as $sid) {
        /** @var mixed $sdef_raw */
        $sdef_raw = $styles[$sid];
        if (!is_array($sdef_raw)) {
            continue;
        }
        /** @var array<string, mixed> $sdef */
        $sdef = $sdef_raw;
        /** @var list<array<string, mixed>> $variants */
        $variants = is_array($sdef['variants'] ?? null) ? $sdef['variants'] : [];
        $variants = el_strip_layout_props_from_variants($variants, $extracted);
        $sdef['variants'] = $variants;
        $styles[$sid] = $sdef;
    }

    return [$styles, $extracted];
}

/**
 * Walk a list of style variants, peel off the layout properties that
 * belong on the inner wrapper, and accumulate them into `$extracted`
 * keyed by the variant's breakpoint key. Returns the variants with
 * those props removed.
 *
 * @param list<array<string, mixed>> $variants
 * @param array<string, array<string, mixed>> $extracted
 * @return list<array<string, mixed>>
 */
function el_strip_layout_props_from_variants(array $variants, array &$extracted): array
{
    $layout_props = ['gap', 'flex-direction', 'flex-wrap', 'align-items', 'justify-content'];
    foreach (array_keys($variants) as $vi) {
        $variant = $variants[$vi];
        /** @var array<string, mixed> $props */
        $props = is_array($variant['props'] ?? null) ? $variant['props'] : [];
        /** @var mixed $bp */
        $bp = $variant['meta']['breakpoint'] ?? null;
        $bp_key = is_string($bp) ? $bp : '__null__';
        foreach ($layout_props as $lp) {
            if (!array_key_exists($lp, $props)) {
                continue;
            }
            if (!array_key_exists($bp_key, $extracted)) {
                $extracted[$bp_key] = [];
            }
            if (!array_key_exists($lp, $extracted[$bp_key])) {
                $extracted[$bp_key][$lp] = $props[$lp];
            }
            unset($props[$lp]);
        }
        $variant['props'] = $props;
        $variants[$vi] = $variant;
    }
    return $variants;
}

/**
 * Build the inner-wrapper style definition that holds the layout props
 * extracted from the agent's pre-built outer styles. Returns an empty
 * map when there are no extracted props (the typical case when the
 * agent passed only v3 settings, not pre-built styles). The `__null__`
 * grouping key becomes the persisted base breakpoint (EL_BASE_BREAKPOINT,
 * never null — Elementor's Style_Parser rejects a null breakpoint).
 *
 * @param array<string, array<string, mixed>> $props_by_breakpoint
 * @return array<string, mixed>
 */
function el_build_inner_layout_style(string $original_id, array $props_by_breakpoint): array
{
    if ($props_by_breakpoint === []) {
        return [];
    }
    $style_id = el_make_safe_class_id(el_boxed_inner_id($original_id)) . '-extra';
    $variants = [];
    foreach ($props_by_breakpoint as $bp_key => $props) {
        $variants[] = [
            'meta' => ['breakpoint' => el_bp_key_to_persisted($bp_key), 'state' => null],
            'props' => $props,
        ];
    }
    return [
        $style_id => [
            'id' => $style_id,
            'type' => 'class',
            'label' => '',
            'variants' => $variants,
        ],
    ];
}

/**
 * Partition a flat v3 container's settings into outer-level (visual)
 * and inner-level (layout) buckets, given the resolved boxed widths
 * per Elementor breakpoint.
 *
 * Outer keeps background/padding/margin/min-height/max-height/border-
 * radius/tag plus a forced `flex_justify_content: "center"` so the inner
 * sits in the middle of the full-bleed band. Inner gets `width: 100%`,
 * one `max_width<suffix>` per breakpoint that had a boxed_width, and
 * any flex-* layout keys (also per breakpoint). The caller passes the
 * input settings already stripped of every `boxed_width<suffix>` /
 * `content_width<suffix>`.
 *
 * Suffixed responsive keys (e.g. `padding_tablet`, `flex_gap_mobile`)
 * are routed to the same outer/inner bucket as their base key.
 *
 * @param array<string, mixed> $settings
 * @param array<string, array{unit: string, size: int|float}> $boxed_sizes_by_bp
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function el_partition_boxed_settings(array $settings, array $boxed_sizes_by_bp): array
{
    $outer_settings = ['flex_justify_content' => 'center'];

    /** @var mixed $tag_value */
    [$settings, $tag_value] = el_pop_v3_tag_setting($settings);
    if ($tag_value !== null) {
        $outer_settings['tag'] = $tag_value;
    }

    $outer_bases = ['background_color', 'padding', 'margin', 'min_height', 'max_height', 'border_radius'];
    [$settings, $outer_visual] = el_pop_v3_settings_with_bases($settings, $outer_bases);
    foreach (array_keys($outer_visual) as $k) {
        $outer_settings[$k] = $outer_visual[$k];
    }

    $inner_settings = el_build_inner_boxed_settings($boxed_sizes_by_bp);

    $inner_bases = ['flex_direction', 'flex_wrap', 'flex_align_items', 'flex_justify_content', 'flex_gap'];
    [$settings, $inner_layout] = el_pop_v3_settings_with_bases($settings, $inner_bases);
    foreach (array_keys($inner_layout) as $k) {
        $inner_settings[$k] = $inner_layout[$k];
    }

    // The agent's explicit `width<suffix>` is overridden by the inner's
    // 100% — drop every breakpoint variant so the v3 translator does
    // not also map them onto the outer.
    foreach (array_merge([''], array_keys(el_v3_breakpoint_suffixes())) as $suffix) {
        unset($settings['width' . $suffix]);
    }

    // Anything left over (unknown keys we didn't classify) lands on the
    // outer so the downstream translator/coercer decides what to do.
    foreach (array_keys($settings) as $key) {
        if (array_key_exists($key, $outer_settings)) {
            continue;
        }
        $outer_settings[$key] = $settings[$key];
    }

    return [$outer_settings, $inner_settings];
}

/**
 * Read the v3 `tag` / `html_tag` value (in that priority order) from a
 * settings array, remove both keys, and return the chosen value (or
 * null if neither was set).
 *
 * @param array<string, mixed> $settings
 * @return array{0: array<string, mixed>, 1: mixed}
 */
function el_pop_v3_tag_setting(array $settings): array
{
    /** @var mixed $tag */
    $tag = null;
    foreach (['html_tag', 'tag'] as $key) {
        if (!array_key_exists($key, $settings)) {
            continue;
        }
        /** @var mixed $tag */
        $tag = $settings[$key];
        unset($settings[$key]);
    }
    return [$settings, $tag];
}

/**
 * Pop every setting key (including responsive `_tablet` / `_mobile` /
 * ... variants) whose base name is in the given allowlist. Returns the
 * settings array stripped of those keys plus a map of the popped keys
 * to their values, preserving the original suffixed key so the
 * downstream translator can route each value to its own breakpoint
 * variant.
 *
 * @param array<string, mixed> $settings
 * @param list<string> $bases
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function el_pop_v3_settings_with_bases(array $settings, array $bases): array
{
    $popped = [];
    foreach (array_keys($settings) as $key) {
        $resolved = el_v3_resolve_breakpoint_key($key);
        if (!in_array($resolved['base'], $bases, strict: true)) {
            continue;
        }
        $popped[$key] = $settings[$key];
        unset($settings[$key]);
    }
    return [$settings, $popped];
}

/**
 * Build the inner wrapper's seed settings: width 100% plus a
 * `max_width<suffix>` per breakpoint that had a boxed_width value.
 *
 * @param array<string, array{unit: string, size: int|float}> $boxed_sizes_by_bp
 * @return array<string, mixed>
 */
function el_build_inner_boxed_settings(array $boxed_sizes_by_bp): array
{
    $inner_settings = ['width' => ['unit' => '%', 'size' => 100]];
    foreach ($boxed_sizes_by_bp as $bp_key => $size) {
        $bp = $bp_key === '__null__' ? null : $bp_key;
        $inner_settings[el_v3_key_with_breakpoint('max_width', $bp)] = $size;
    }
    return $inner_settings;
}

/**
 * Find every `boxed_width<suffix>` setting on a container, resolve its
 * value to a normalized `{unit, size}` shape, and return the resulting
 * map of breakpoint key → size alongside the settings array stripped of
 * all `boxed_width*` keys (parsed or not).
 *
 * @param array<string, mixed> $settings
 * @return array{0: array<string, array{unit: string, size: int|float}>, 1: array<string, mixed>}
 */
function el_extract_boxed_widths_by_breakpoint(array $settings): array
{
    /** @var array<string, array{unit: string, size: int|float}> $sizes_by_bp */
    $sizes_by_bp = [];
    foreach (array_keys($settings) as $key) {
        $resolved = el_v3_resolve_breakpoint_key($key);
        if ($resolved['base'] !== 'boxed_width') {
            continue;
        }
        $size = el_extract_v3_size_value($settings[$key]);
        // Drop the v3 key whether or not it parsed — boxed_width has no
        // counterpart on v4 atomic containers.
        unset($settings[$key]);
        if ($size === null) {
            continue;
        }
        $bp_key = $resolved['breakpoint'] ?? '__null__';
        $sizes_by_bp[$bp_key] = $size;
    }
    return [$sizes_by_bp, $settings];
}

/**
 * Read the active Elementor kit's `container_width` setting and return
 * it normalized into a `{unit, size}` shape, or null when the value is
 * unavailable (older Elementor without kits, kit not configured,
 * etc.). Used by the boxed splitter to fill in a max-width when a v3
 * container relies on the kit default (neither `boxed_width` nor
 * `content_width: "full"` is set).
 *
 * @return array{unit: string, size: int|float}|null
 */
function el_get_kit_container_width(): ?array
{
    if (!class_exists('Elementor\\Plugin')) {
        return null;
    }
    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;
    /** @var object|null $kits_manager */
    $kits_manager = $plugin->kits_manager ?? null;
    if (!is_object($kits_manager) || !method_exists($kits_manager, 'get_current_settings')) {
        return null;
    }
    /** @var mixed $val */
    $val = $kits_manager->get_current_settings('container_width');
    return el_extract_v3_size_value($val);
}

/**
 * Inject missing v3 container defaults that don't map onto v4 e-flexbox
 * CSS defaults. Runs only when the node looks like it came from a v3
 * tree (at least one v3-only marker key present). Native v4 writes
 * pass through untouched and get pure CSS defaults.
 *
 * Today synthesizes:
 *   - `flex_direction: "column"` when absent (v3 default column vs
 *     v4 CSS default row).
 *
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function el_apply_v3_container_defaults(array $settings): array
{
    if (!el_has_v3_container_markers($settings)) {
        return $settings;
    }
    if (!array_key_exists('flex_direction', $settings)) {
        $settings['flex_direction'] = 'column';
    }
    return $settings;
}

/**
 * When no explicit `boxed_width` was drained AND the container is a
 * top-level v3 conversion without `content_width: "full"`, read the
 * kit's `container_width` and use it as the boxed size so the v4
 * output mirrors v3's default-boxed behavior.
 *
 * @param array<string, array{unit: string, size: int|float}> $boxed_sizes_by_bp
 * @param array<string, mixed> $settings
 * @return array<string, array{unit: string, size: int|float}>
 */
function el_maybe_infer_default_boxed_width(array $boxed_sizes_by_bp, array $settings, string $context): array
{
    if ($boxed_sizes_by_bp !== []) {
        return $boxed_sizes_by_bp;
    }
    if ($context === 'inside-wrapper') {
        return $boxed_sizes_by_bp;
    }
    if (($settings['content_width'] ?? null) === 'full') {
        return $boxed_sizes_by_bp;
    }
    if (!el_has_v3_container_markers($settings)) {
        return $boxed_sizes_by_bp;
    }
    $kit_width = el_get_kit_container_width();
    if ($kit_width === null) {
        return $boxed_sizes_by_bp;
    }
    return ['__null__' => $kit_width];
}

/**
 * Detect whether an atomic container's settings carry the fingerprint
 * of a v3 → v4 conversion. v3 container keys like `content_width`,
 * `boxed_width`, `flex_gap`, `flex_justify_content`, `html_tag`,
 * `__globals__`, etc. have no v4 atomic counterpart, so their
 * presence (unwrapped) in a node's `settings` is a strong signal that
 * the agent is converting a v3 tree rather than authoring v4 atomic
 * from scratch.
 *
 * Used to gate the v3-default synthesizers (flex_direction: column,
 * boxed_width from kit) — they must NOT fire on native v4 writes,
 * which are expected to behave like a plain `e-flexbox` with CSS
 * defaults.
 *
 * Ambiguous keys whose name also exists as a v4 style prop
 * (`padding`, `margin`, `background_color`, ...) are treated as v3
 * markers only when the value is NOT already `{$$type, value}`-
 * wrapped. A wrapped value means the agent speaks v4 and is
 * accidentally using a settings slot, so we do not flip into v3 mode.
 *
 * @param array<string, mixed> $settings
 */
function el_has_v3_container_markers(array $settings): bool
{
    $markers = [
        'content_width',
        'boxed_width',
        'html_tag',
        '__globals__',
        '__dynamic__',
        'flex_direction',
        'flex_wrap',
        'flex_gap',
        'flex_align_items',
        'flex_justify_content',
        'padding',
        'margin',
        'min_height',
        'max_height',
        'border_radius',
        'width',
        'min_width',
        'max_width',
        'background_color',
    ];
    foreach (array_keys($settings) as $key) {
        $resolved = el_v3_resolve_breakpoint_key($key);
        if (!in_array($resolved['base'], $markers, strict: true)) {
            continue;
        }
        /** @var mixed $value */
        $value = $settings[$key];
        if (is_array($value) && array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
            continue;
        }
        return true;
    }
    return false;
}

/**
 * Pull a v3 `{unit, size}` shape (or a bare numeric) into a normalized
 * `{unit, size}` array. Returns null when the value is empty or
 * unparseable.
 *
 * @return array{unit: string, size: int|float}|null
 */
function el_extract_v3_size_value(mixed $raw): ?array
{
    if (is_array($raw)) {
        /** @var mixed $size */
        $size = $raw['size'] ?? null;
        if ($size === '' || $size === null || !is_numeric($size)) {
            return null;
        }
        $unit = is_string($raw['unit'] ?? null) && $raw['unit'] !== '' ? $raw['unit'] : 'px';
        $num = is_int($size) ? $size : (float) $size;
        return ['unit' => $unit, 'size' => $num];
    }
    if (is_numeric($raw)) {
        $num = is_int($raw) ? $raw : (float) $raw;
        return ['unit' => 'px', 'size' => $num];
    }
    return null;
}

/**
 * Recursively translate v3 container-level settings into v4 style props
 * on atomic containers (e-flexbox, e-div-block). Agents converting v3
 * pages commonly pass legacy settings like `width: {unit: "%", size: 65}`
 * or `flex_direction: "row"` directly on the atomic container; these are
 * NOT valid settings on atomic elements — they belong in `styles` as v4
 * style props. Without this translation, `el_coerce_tree_settings` would
 * silently drop them and the layout breaks (65%/35% columns become
 * 100%, row layouts collapse to column, etc.).
 *
 * Only a curated allowlist of well-known v3 container settings is
 * translated — responsive variants, `boxed_width`, `content_width` and
 * dynamic properties require agent-level decisions and are left alone.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_translate_v3_container_settings(array $node): array
{
    $el_type = (string) ($node['elType'] ?? '');
    if (in_array($el_type, NOVAMIRA_ATOMIC_CONTAINER_TYPES, strict: true)) {
        $node = el_translate_v3_container_settings_on_node($node);
    }

    /** @var list<array<string, mixed>> $children */
    $children = is_array($node['elements'] ?? null) ? $node['elements'] : [];
    $new_children = [];
    foreach ($children as $child) {
        $new_children[] = el_translate_v3_container_settings($child);
    }
    $node['elements'] = $new_children;

    return $node;
}

/**
 * Apply the v3→v4 setting translation to a single atomic container
 * node. Drains recognized keys from `settings` and injects them as
 * wrapped style props into the node's first style variant.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_translate_v3_container_settings_on_node(array $node): array
{
    /** @var array<string, mixed> $settings */
    $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];
    if ($settings === []) {
        return $node;
    }

    [$settings, $new_props_by_bp, $touched] = el_drain_v3_container_settings($settings);

    if (!$touched) {
        return $node;
    }

    $node['settings'] = $settings;

    if ($new_props_by_bp === []) {
        return $node;
    }

    /** @var array<string, mixed> $existing_styles */
    $existing_styles = is_array($node['styles'] ?? null) ? $node['styles'] : [];
    $node['styles'] = el_inject_props_into_first_style(
        $existing_styles,
        (string) ($node['id'] ?? 'el'),
        $new_props_by_bp,
    );

    return $node;
}

/**
 * Walk a settings array, drain every key recognized by the v3 container
 * translation map (including responsive `_tablet` / `_mobile` / ...
 * variants), and return the stripped settings, a breakpoint-keyed map
 * of wrapped v4 props ready for injection into a style, and a flag
 * indicating whether any v3 key was consumed.
 *
 * @param array<string, mixed> $settings
 * @return array{0: array<string, mixed>, 1: array<string, array<string, mixed>>, 2: bool}
 */
function el_drain_v3_container_settings(array $settings): array
{
    $map = el_v3_container_translation_map();
    /** @var array<string, array<string, mixed>> $new_props_by_bp */
    $new_props_by_bp = [];
    $touched = false;

    foreach (array_keys($settings) as $key) {
        $resolved = el_v3_resolve_breakpoint_key($key);
        if (!array_key_exists($resolved['base'], $map)) {
            continue;
        }
        /** @var mixed $raw */
        $raw = $settings[$key];
        // Skip values already in v4-wrapped form — defer to coercion.
        if (is_array($raw) && array_key_exists('$$type', $raw) && array_key_exists('value', $raw)) {
            continue;
        }
        // Consume the v3 key unconditionally: if the value is empty
        // (e.g. `width: {size: ""}` — v3's "unset" marker) we want a
        // silent no-op, not a downstream validator error on the
        // leftover v3-shaped value.
        unset($settings[$key]);
        $touched = true;

        $translation = $map[$resolved['base']];
        $wrapped = el_v3_container_translate_value($raw, $translation['kind']);
        if ($wrapped === null) {
            continue;
        }

        $bp_key = $resolved['breakpoint'] ?? '__null__';
        if (!array_key_exists($bp_key, $new_props_by_bp)) {
            $new_props_by_bp[$bp_key] = [];
        }
        $new_props_by_bp[$bp_key][$translation['prop']] = $wrapped;
    }

    return [$settings, $new_props_by_bp, $touched];
}

/**
 * Map of v3 responsive suffixes to Elementor v4 breakpoint names. Used
 * by the translator and the boxed splitter to detect responsive
 * variants of a base v3 setting (e.g. `padding_tablet` → padding on
 * the `tablet` breakpoint variant).
 *
 * Order matters: longest suffix first, so `_tablet_extra` is matched
 * before the shorter `_tablet` would consume the prefix.
 *
 * @return array<string, string>
 */
function el_v3_breakpoint_suffixes(): array
{
    return [
        '_tablet_extra' => 'tablet_extra',
        '_mobile_extra' => 'mobile_extra',
        '_widescreen' => 'widescreen',
        '_laptop' => 'laptop',
        '_tablet' => 'tablet',
        '_mobile' => 'mobile',
    ];
}

/**
 * Decompose a v3 setting key into its base name and breakpoint. A key
 * that does not end in any known suffix returns breakpoint `null` and
 * the full key as the base — that is the desktop/default value.
 *
 * @return array{base: string, breakpoint: string|null}
 */
function el_v3_resolve_breakpoint_key(string $key): array
{
    foreach (el_v3_breakpoint_suffixes() as $suffix => $bp) {
        if (str_ends_with($key, $suffix)) {
            return ['base' => substr($key, offset: 0, length: -strlen($suffix)), 'breakpoint' => $bp];
        }
    }
    return ['base' => $key, 'breakpoint' => null];
}

/**
 * Compose a v3 setting key from a base name and an Elementor v4
 * breakpoint name. Returns the bare base name when the breakpoint is
 * null or unknown.
 */
function el_v3_key_with_breakpoint(string $base, ?string $breakpoint): string
{
    if ($breakpoint === null) {
        return $base;
    }
    foreach (el_v3_breakpoint_suffixes() as $suffix => $bp) {
        if ($bp === $breakpoint) {
            return $base . $suffix;
        }
    }
    return $base;
}

/**
 * The curated map of v3 container settings we translate automatically.
 * Each entry pairs the v3 setting key with the v4 style prop name and a
 * conversion kind that `el_v3_container_translate_value` knows how to
 * handle.
 *
 * @return array<string, array{prop: string, kind: string}>
 */
function el_v3_container_translation_map(): array
{
    return [
        'width' => ['prop' => 'width', 'kind' => 'size'],
        'min_width' => ['prop' => 'min-width', 'kind' => 'size'],
        'max_width' => ['prop' => 'max-width', 'kind' => 'size'],
        'min_height' => ['prop' => 'min-height', 'kind' => 'size'],
        'max_height' => ['prop' => 'max-height', 'kind' => 'size'],
        'flex_gap' => ['prop' => 'gap', 'kind' => 'size'],
        'border_radius' => ['prop' => 'border-radius', 'kind' => 'size'],
        'flex_direction' => ['prop' => 'flex-direction', 'kind' => 'string'],
        'flex_wrap' => ['prop' => 'flex-wrap', 'kind' => 'string'],
        'flex_align_items' => ['prop' => 'align-items', 'kind' => 'string'],
        'flex_justify_content' => ['prop' => 'justify-content', 'kind' => 'string'],
        'padding' => ['prop' => 'padding', 'kind' => 'dimensions'],
        'margin' => ['prop' => 'margin', 'kind' => 'dimensions'],
        'background_color' => ['prop' => 'background', 'kind' => 'background-color'],
    ];
}

/**
 * Convert a v3 setting value into the corresponding v4 wrapped style
 * prop. Returns null when the value is empty or cannot be converted.
 */
function el_v3_container_translate_value(mixed $raw, string $kind): ?array
{
    if ($kind === 'size' && is_array($raw)) {
        /** @var array<string, mixed> $raw */
        return el_v3_size_to_v4_wrapped($raw);
    }
    if ($kind === 'string' && is_string($raw) && $raw !== '') {
        return ['$$type' => 'string', 'value' => $raw];
    }
    if ($kind === 'dimensions' && is_array($raw)) {
        /** @var array<string, mixed> $raw */
        return el_v3_dimensions_to_v4_wrapped($raw);
    }
    if ($kind === 'background-color' && is_string($raw) && $raw !== '') {
        return el_v3_color_to_v4_background($raw);
    }
    return null;
}

/**
 * `{unit, size}` → `{$$type: "size", value: {size, unit}}`. Returns
 * null when size is empty (v3 "unset" marker) or invalid.
 *
 * @param array<string, mixed> $size
 */
function el_v3_size_to_v4_wrapped(array $size): ?array
{
    /** @var mixed $size_value */
    $size_value = $size['size'] ?? null;
    if ($size_value === '' || $size_value === null) {
        return null;
    }
    if (!is_numeric($size_value)) {
        return null;
    }
    $unit = is_string($size['unit'] ?? null) && $size['unit'] !== '' ? $size['unit'] : 'px';
    $num = is_int($size_value) ? $size_value : (float) $size_value;
    return [
        '$$type' => 'size',
        'value' => [
            'size' => $num,
            'unit' => $unit,
        ],
    ];
}

/**
 * `{unit, top, right, bottom, left, isLinked}` → v4 dimensions with
 * logical sides. Returns null when all four values are empty.
 *
 * @param array<string, mixed> $dim
 */
function el_v3_dimensions_to_v4_wrapped(array $dim): ?array
{
    $unit = is_string($dim['unit'] ?? null) && $dim['unit'] !== '' ? $dim['unit'] : 'px';
    $positions = [
        'block-start' => 'top',
        'inline-end' => 'right',
        'block-end' => 'bottom',
        'inline-start' => 'left',
    ];

    $value = [];
    foreach ($positions as $v4_side => $v3_side) {
        /** @var mixed $raw */
        $raw = $dim[$v3_side] ?? null;
        if ($raw === '' || $raw === null || !is_numeric($raw)) {
            continue;
        }
        $num = is_int($raw) ? $raw : (float) $raw;
        $value[$v4_side] = [
            '$$type' => 'size',
            'value' => ['size' => $num, 'unit' => $unit],
        ];
    }

    if ($value === []) {
        return null;
    }

    return ['$$type' => 'dimensions', 'value' => $value];
}

/**
 * `"#E52600"` → full background prop with color overlay.
 */
function el_v3_color_to_v4_background(string $color): array
{
    return [
        '$$type' => 'background',
        'value' => [
            'color' => ['$$type' => 'color', 'value' => $color],
        ],
    ];
}

/**
 * Merge a breakpoint-keyed map of wrapped props into the first style
 * definition of a node. Creates a new style definition when the node
 * has none yet, otherwise grafts each breakpoint's props onto a new
 * or existing variant on the first style. Never overwrites existing
 * props — agent-provided style values win over translated v3 settings.
 *
 * Each key of `$props_by_bp` is either an Elementor v4 breakpoint name
 * (`tablet`, `mobile`, `widescreen`, ...) or `__null__` for the base
 * desktop variant.
 *
 * @param array<string, mixed> $styles
 * @param array<string, array<string, mixed>> $props_by_bp
 * @return array<string, mixed>
 */
function el_inject_props_into_first_style(array $styles, string $element_id, array $props_by_bp): array
{
    if ($props_by_bp === []) {
        return $styles;
    }
    if ($styles === []) {
        $style_id = $element_id . '-auto';
        $styles[$style_id] = [
            'id' => $style_id,
            'type' => 'class',
            // "local" is the canonical label Elementor's own atomic widgets use
            // for per-element styles (the editor displays it as the badge text).
            'label' => 'local',
            'variants' => el_build_variants_for_props_by_bp($props_by_bp),
        ];
        return $styles;
    }

    $first_style_id = array_key_first($styles);
    /** @var array<string, mixed> $first_style */
    $first_style = is_array($styles[$first_style_id]) ? $styles[$first_style_id] : [];
    /** @var list<array<string, mixed>> $variants */
    $variants = is_array($first_style['variants'] ?? null) ? $first_style['variants'] : [];

    foreach ($props_by_bp as $bp_key => $props) {
        $variants = el_merge_props_into_variant_for_bp($variants, $bp_key, $props);
    }

    $first_style['variants'] = $variants;
    $styles[$first_style_id] = $first_style;

    return $styles;
}

/**
 * Build a list of style variants from a breakpoint-keyed map of props.
 * `__null__` becomes the base variant persisted with breakpoint
 * EL_BASE_BREAKPOINT ("desktop", never null), every other key becomes a
 * named-breakpoint variant. Used when the first style on a node has no
 * pre-existing variants to merge into.
 *
 * @param array<string, array<string, mixed>> $props_by_bp
 * @return list<array<string, mixed>>
 */
function el_build_variants_for_props_by_bp(array $props_by_bp): array
{
    $variants = [];
    foreach ($props_by_bp as $bp_key => $props) {
        $variants[] = [
            'meta' => ['breakpoint' => el_bp_key_to_persisted($bp_key), 'state' => null],
            'props' => $props,
        ];
    }
    return $variants;
}

/**
 * Map an internal breakpoint grouping key onto the value persisted in
 * `meta.breakpoint`. `__null__` is the in-memory stand-in for the base
 * (desktop) variant — it must never leak out as a null breakpoint, so it
 * becomes EL_BASE_BREAKPOINT; every other key is an Elementor breakpoint
 * name and passes through unchanged.
 */
function el_bp_key_to_persisted(string $bp_key): string
{
    return $bp_key === '__null__' ? EL_BASE_BREAKPOINT : $bp_key;
}

/**
 * Add a single breakpoint's props onto an existing variants list. If a
 * variant already exists for that breakpoint, the new props are merged
 * into it without overwriting existing entries (agent values win).
 * Otherwise a new variant is appended. `__null__` matches both null and
 * `desktop` so the base variant is treated as the same surface
 * regardless of which spelling Elementor wrote it under; a NEW base
 * variant is always persisted with breakpoint EL_BASE_BREAKPOINT.
 *
 * @param list<array<string, mixed>> $variants
 * @param array<string, mixed> $props
 * @return list<array<string, mixed>>
 */
function el_merge_props_into_variant_for_bp(array $variants, string $bp_key, array $props): array
{
    $bp_normalized = $bp_key === '__null__' ? null : $bp_key;
    $found = -1;
    foreach (array_keys($variants) as $i) {
        $variant = $variants[$i];
        /** @var mixed $existing */
        $existing = $variant['meta']['breakpoint'] ?? null;
        $is_base = $existing === null || $existing === 'desktop';
        $bp_is_base = $bp_normalized === null || $bp_normalized === 'desktop';
        if ($bp_is_base ? $is_base : $existing === $bp_normalized) {
            $found = $i;
            break;
        }
    }
    if ($found === -1) {
        $variants[] = [
            'meta' => ['breakpoint' => el_bp_key_to_persisted($bp_key), 'state' => null],
            'props' => $props,
        ];
        return $variants;
    }
    $variant = $variants[$found];
    /** @var array<string, mixed> $existing_props */
    $existing_props = is_array($variant['props'] ?? null) ? $variant['props'] : [];
    foreach (array_keys($props) as $key) {
        if (array_key_exists($key, $existing_props)) {
            continue;
        }
        $existing_props[$key] = $props[$key];
    }
    $variant['props'] = $existing_props;
    $variants[$found] = $variant;
    return $variants;
}

/**
 * Recursively auto-wrap settings of atomic elements in the tree into the
 * `{$$type, value}` format expected by Elementor v4. Uses the element's
 * prop schema to determine the correct `$$type` for each setting.
 *
 * This makes tree mode deterministic: the caller can pass plain strings
 * (e.g. `"title": "Hello"`) and the server wraps them into
 * `{"$$type": "html-v3", "value": {"content": {"$$type": "string", "value": "Hello"}, "children": []}}`
 * exactly like the single-element validation path does.
 *
 * @param array<string, mixed> $node
 * @param list<array{element_id: string, widget_type: string, setting_name: string, tag: mixed, reason: string}> $dynamic_tag_errors
 * @return array<string, mixed>
 */
function el_coerce_tree_settings(array $node, array &$dynamic_tag_errors): array
{
    $schema_key = el_element_widget_type($node);

    // Only coerce for elements that have a resolvable schema.
    if ($schema_key !== '' && $schema_key !== NOVAMIRA_COMPACT_SCHEMA_CONTAINER_KEY) {
        $schema = el_extract_schema($schema_key);
        if ($schema !== null && ($schema['is_atomic'] ?? false) === true) {
            $node = el_coerce_atomic_node_settings($node, $schema, $dynamic_tag_errors);
        }
    }

    /** @var list<array<string, mixed>> $children */
    $children = is_array($node['elements'] ?? null) ? $node['elements'] : [];
    $new_children = [];
    foreach ($children as $child) {
        $new_children[] = el_coerce_tree_settings($child, $dynamic_tag_errors);
    }
    $node['elements'] = $new_children;

    return $node;
}

/**
 * Auto-wrap the settings of a single atomic element using its schema.
 * Delegates to `el_coerce_atomic_value()` for each setting key.
 *
 * @param array<string, mixed> $node
 * @param array<string, mixed> $schema
 * @param list<array{element_id: string, widget_type: string, setting_name: string, tag: mixed, reason: string}> $dynamic_tag_errors
 * @return array<string, mixed>
 */
function el_coerce_atomic_node_settings(array $node, array $schema, array &$dynamic_tag_errors): array
{
    /** @var array<string, mixed> $settings */
    $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];
    $controls = el_schema_controls($schema);
    if ($controls === null || $settings === []) {
        return $node;
    }

    // Convert v3 __dynamic__ tags to v4 inline {$$type: "dynamic"} values
    // and coerce all other settings to the {$$type, value} wrapped format.
    $widget_type = (string) ($schema['widgetType'] ?? '');
    $element_id = (string) ($node['id'] ?? '');
    $dynamic_result = el_build_dynamic_overrides($settings, $element_id, $widget_type);
    if ($dynamic_result['errors'] !== []) {
        array_push($dynamic_tag_errors, ...$dynamic_result['errors']);
    }
    $node['settings'] = el_coerce_settings_with_overrides($settings, $controls, $dynamic_result['overrides']);
    return $node;
}

/**
 * Extract v3 `__dynamic__` entries and convert them to v4 `{$$type: "dynamic"}`
 * values. Returns a map of setting key → dynamic value.
 *
 * @param array<string, mixed> $settings
 * @return array{overrides: array<string, array<string, mixed>>, errors: list<array{element_id: string, widget_type: string, setting_name: string, tag: mixed, reason: string}>}
 */
function el_build_dynamic_overrides(array $settings, string $element_id, string $widget_type): array
{
    /** @var array<string, mixed> $dynamic */
    $dynamic = is_array($settings['__dynamic__'] ?? null) ? $settings['__dynamic__'] : [];
    $overrides = [];
    $errors = [];
    foreach (array_keys($dynamic) as $setting_name) {
        /** @var mixed $tag */
        $tag = $dynamic[$setting_name];
        if (!is_string($tag) || $tag === '') {
            $errors[] = [
                'element_id' => $element_id,
                'widget_type' => $widget_type,
                'setting_name' => $setting_name,
                'tag' => $tag,
                'reason' => 'Expected a non-empty v3 dynamic tag string.',
            ];
            continue;
        }

        $parsed = el_parse_v3_dynamic_tag_result($tag)['parsed'] ?? null;
        if ($parsed === null) {
            $errors[] = [
                'element_id' => $element_id,
                'widget_type' => $widget_type,
                'setting_name' => $setting_name,
                'tag' => $tag,
                'reason' =>
                    el_parse_v3_dynamic_tag_result($tag)['error'] ?? 'Failed to parse the v3 dynamic tag string.',
            ];
            continue;
        }

        $overrides[$setting_name] = ['$$type' => 'dynamic', 'value' => $parsed];
    }
    return ['overrides' => $overrides, 'errors' => $errors];
}

/**
 * Coerce settings values applying dynamic overrides and {$$type, value} wrapping.
 *
 * @param array<string, mixed> $settings
 * @param array<string, mixed> $controls
 * @param array<string, array<string, mixed>> $dynamic_overrides
 * @return array<string, mixed>
 */
function el_coerce_settings_with_overrides(array $settings, array $controls, array $dynamic_overrides): array
{
    $coerced = [];
    foreach (array_keys($settings) as $key) {
        if ($key === '__dynamic__' || $key === '__globals__') {
            continue;
        }
        if (array_key_exists($key, $dynamic_overrides)) {
            /** @var array<string, mixed> $dyn_control */
            $dyn_control = is_array($controls[$key] ?? null) ? $controls[$key] : [];
            $coerced[$key] = el_wrap_dynamic_override_for_prop($dynamic_overrides[$key], $dyn_control);
            continue;
        }
        if (!array_key_exists($key, $controls)) {
            $coerced[$key] = $settings[$key];
            continue;
        }
        /** @var array<string, mixed> $control */
        $control = $controls[$key];
        $result = el_coerce_atomic_value($key, $settings[$key], $control);
        $coerced[$key] = $result['value'];
    }

    // Add dynamic overrides for keys not in settings (e.g. link from __dynamic__ only).
    foreach ($dynamic_overrides as $dk => $dv) {
        if (array_key_exists($dk, $coerced)) {
            continue;
        }
        /** @var array<string, mixed> $dk_control */
        $dk_control = is_array($controls[$dk] ?? null) ? $controls[$dk] : [];
        $coerced[$dk] = el_wrap_dynamic_override_for_prop($dv, $dk_control);
    }

    return $coerced;
}

/**
 * Wrap a dynamic override value for the target prop type. For most prop
 * types, the dynamic value replaces the entire prop. For `link`, the
 * dynamic tag goes inside `destination` because Elementor's dynamic tag
 * system supports `Url_Prop_Type` but not `Link_Prop_Type` directly.
 *
 * @param array<string, mixed> $dynamic_value  e.g. {$$type: "dynamic", value: {name, group, settings}}
 * @param array<string, mixed> $control        compact schema control for the target prop
 * @return array<string, mixed>
 */
function el_wrap_dynamic_override_for_prop(array $dynamic_value, array $control): array
{
    $prop_type = (string) ($control['t'] ?? '');
    if ($prop_type === 'link') {
        return [
            '$$type' => 'link',
            'value' => [
                'destination' => $dynamic_value,
                'isTargetBlank' => ['$$type' => 'boolean', 'value' => false],
                'tag' => ['$$type' => 'string', 'value' => 'a'],
            ],
        ];
    }
    return $dynamic_value;
}

/**
 * Parse a v3 dynamic tag string into the v4 {name, settings} shape.
 *
 * v3 format: `[elementor-tag id="xxx" name="tag-name" settings="url-encoded-json"]`
 * v4 format: `{name: "tag-name", settings: {key: value, ...}}`
 *
 * @return array{name: string, group: string, settings: array<string, mixed>}|null
 */
function el_parse_v3_dynamic_tag(string $tag): ?array
{
    $result = el_parse_v3_dynamic_tag_result($tag);
    return $result['parsed'] ?? null;
}

/**
 * Parse a v3 dynamic tag string and return either the normalized v4 payload
 * or a human-readable parse error.
 *
 * @return array{parsed: array{name: string, group: string, settings: array<string, mixed>}}|array{error: string}
 */
function el_parse_v3_dynamic_tag_result(string $tag): array
{
    // Match [elementor-tag ... name="xxx" ... settings="xxx" ...]
    if (!preg_match('/\[elementor-tag\s/', $tag)) {
        return ['error' => 'Expected a v3 [elementor-tag ...] string.'];
    }

    $name_matches = [];
    if (preg_match('/name="([^"]+)"/', $tag, $name_matches) !== 1) {
        return ['error' => 'Dynamic tag string is missing the name attribute.'];
    }
    $name = $name_matches[1];

    $settings = [];
    $settings_matches = [];
    if (preg_match('/settings="([^"]*)"/', $tag, $settings_matches) === 1) {
        $decoded = urldecode($settings_matches[1]);
        if ($decoded !== '') {
            /** @var array<string, mixed>|null $parsed */
            $parsed = json_decode(json: $decoded, associative: true);
            if (!is_array($parsed)) {
                return ['error' => 'Failed to decode the dynamic tag settings JSON.'];
            }
            // Wrap each setting value in {$$type: "string", value: ...}
            // to match the v4 format the editor produces.
            array_walk($parsed, static function (mixed $value, string $key) use (&$settings): void {
                if (!is_string($value)) {
                    $settings[$key] = $value;
                    return;
                }
                $settings[$key] = ['$$type' => 'string', 'value' => $value];
            });
        }
    }

    // Resolve the tag group from the Elementor registry.
    $group = el_resolve_dynamic_tag_group($name);

    return ['parsed' => ['name' => $name, 'group' => $group, 'settings' => $settings]];
}

/**
 * Shape a fail-hard error response for tree-mode inserts. Combines standard
 * subtree validation errors with malformed v3 dynamic tag strings so the
 * caller can correct the converted subtree and retry.
 *
 * @param array{ok: bool, tree: list<array<string, mixed>>, errors: list<array<string, mixed>>, schemas: array<string, array<string, mixed>>} $validation
 * @param list<array{element_id: string, widget_type: string, setting_name: string, tag: mixed, reason: string}> $dynamic_tag_errors
 * @return array<string, mixed>
 */
function build_tree_validation_error_response(array $validation, array $dynamic_tag_errors): array
{
    $errors = $validation['errors'];
    $schemas = $validation['schemas'];

    $counts = el_count_tree_issues($errors);
    $message = el_build_tree_error_message($counts, count($dynamic_tag_errors));

    // Inline the first few concrete failures into the message itself so the
    // MCP wrapper's error-only unwrap path still surfaces the reasons.
    $summary = el_summarize_tree_errors($errors, $dynamic_tag_errors, limit: 3);
    if ($summary !== '') {
        $message .= ' ' . $summary;
    }

    $response = [
        'success' => false,
        'error' => $message,
    ];

    if ($errors !== []) {
        $response['errors'] = $errors;
        $response['schemas'] = $schemas;
    }

    if ($dynamic_tag_errors !== []) {
        $response['dynamic_tag_errors'] = $dynamic_tag_errors;
    }

    return $response;
}

/**
 * Build the tree-mode error response for nodes whose `styles` map carries
 * non-string style ids (see {@see el_collect_tree_style_id_errors}). Uses the
 * standard tree validation response so `errors[].style_errors` keeps its
 * usual shape, and appends the verbose-shape hint (including how a state is
 * targeted) so the caller can correct the payload in one roundtrip.
 *
 * @param list<array<string, mixed>> $style_id_errors
 * @return array<string, mixed>
 */
function el_build_tree_style_id_error_response(array $style_id_errors): array
{
    $response = build_tree_validation_error_response([
        'ok' => false,
        'tree' => [],
        'errors' => $style_id_errors,
        'schemas' => [],
    ], []);
    $response['error'] = (string) $response['error'] . ' ' . el_styles_map_shape_hint();
    return $response;
}

/**
 * Tally the per-category error counts across the tree validation error list.
 * Unknown widget types are counted separately from dropped keys so the
 * headline can name each category — an agent that only reads the headline
 * otherwise misses whole rejected subtrees when a widget's type itself is
 * unknown and thus has no dropped_keys to flag.
 *
 * @param list<array<string, mixed>> $errors
 * @return array{dropped: int, invalid: int, style: int, unknown_widgets: int}
 */
function el_count_tree_issues(array $errors): array
{
    $dropped = 0;
    $invalid = 0;
    $style = 0;
    $unknown_widgets = 0;
    foreach ($errors as $error) {
        if (is_array($error['dropped_keys'] ?? null)) {
            $dropped += count($error['dropped_keys']);
        }
        if (is_array($error['invalid_values'] ?? null)) {
            $invalid += count($error['invalid_values']);
        }
        if (is_array($error['style_errors'] ?? null)) {
            $style += count($error['style_errors']);
        }
        if (($error['unknown_widget_type'] ?? false) === true) {
            $unknown_widgets++;
        }
    }
    return ['dropped' => $dropped, 'invalid' => $invalid, 'style' => $style, 'unknown_widgets' => $unknown_widgets];
}

/**
 * Render the headline string from the per-category counts. Unknown widget
 * types lead because they are the most severe class of rejection — a
 * subtree whose widget type doesn't exist can't be recovered by just
 * fixing a few keys.
 *
 * @param array{dropped: int, invalid: int, style: int, unknown_widgets: int} $counts
 */
function el_build_tree_error_message(array $counts, int $dynamic_tag_errors): string
{
    $parts = [];
    if ($dynamic_tag_errors > 0) {
        $parts[] = sprintf('%d dynamic tag parse error(s)', $dynamic_tag_errors);
    }
    if ($counts['unknown_widgets'] > 0) {
        $parts[] = sprintf('%d unknown widget type(s)', $counts['unknown_widgets']);
    }
    if ($counts['dropped'] > 0) {
        $parts[] = sprintf('%d unknown key(s)', $counts['dropped']);
    }
    if ($counts['invalid'] > 0) {
        $parts[] = sprintf('%d invalid value(s)', $counts['invalid']);
    }
    if ($counts['style'] > 0) {
        $parts[] = sprintf('%d style entry error(s)', $counts['style']);
    }

    if ($parts === []) {
        return 'Tree validation failed.';
    }
    return sprintf('Tree validation failed: %s. Fix the reported subtree issues and retry.', implode(', ', $parts));
}

/**
 * Render up to $limit concrete failures as a compact inline summary so the
 * reasons survive even when an MCP wrapper strips every response key except
 * `error`. Walks the tree error records in priority order (unknown widget,
 * style, dropped, invalid) and finally the dynamic tag parse errors.
 *
 * @param list<array<string, mixed>> $errors
 * @param list<array{element_id: string, widget_type: string, setting_name: string, tag: mixed, reason: string}> $dynamic_tag_errors
 */
function el_summarize_tree_errors(array $errors, array $dynamic_tag_errors, int $limit): string
{
    $picked = [];
    foreach ($errors as $error) {
        if (count($picked) >= $limit) {
            break;
        }
        el_append_tree_error_summary($error, $limit, $picked);
    }

    foreach ($dynamic_tag_errors as $dt_error) {
        if (count($picked) >= $limit) {
            break;
        }
        $picked[] = sprintf(
            'dynamic_tag widget="%s" setting="%s" reason=%s',
            $dt_error['widget_type'],
            $dt_error['setting_name'],
            $dt_error['reason'],
        );
    }

    if ($picked === []) {
        return '';
    }

    return sprintf('First %d: %s.', count($picked), implode('; ', $picked));
}

/**
 * Append up to $limit concrete failure strings extracted from a single tree
 * error record into $picked. Split from `el_summarize_tree_errors` to keep
 * each function's branching below the cyclomatic-complexity gate.
 *
 * @param array<string, mixed> $error
 * @param list<string> $picked
 */
function el_append_tree_error_summary(array $error, int $limit, array &$picked): void
{
    $widget_type = is_string($error['widget_type'] ?? null) ? $error['widget_type'] : '';

    if (($error['unknown_widget_type'] ?? false) === true) {
        $element_id = is_string($error['element_id'] ?? null) ? $error['element_id'] : '';
        $reason = is_string($error['reason'] ?? null) ? $error['reason'] : '';
        $picked[] = $reason === ''
            ? sprintf('unknown widget_type="%s" element_id="%s"', $widget_type, $element_id)
            : sprintf('%s element_id="%s"', rtrim($reason, characters: '.'), $element_id);
        return;
    }

    if (is_array($error['style_errors'] ?? null)) {
        /** @var list<array{style_id: string, reason: string, path: string}> $style_errors */
        $style_errors = $error['style_errors'];
        el_append_style_error_summaries($widget_type, $style_errors, $limit, $picked);
    }

    if (is_array($error['dropped_keys'] ?? null)) {
        /** @var list<string> $dropped */
        $dropped = $error['dropped_keys'];
        el_append_dropped_key_summaries($widget_type, $dropped, $limit, $picked);
    }

    if (is_array($error['invalid_values'] ?? null)) {
        /** @var list<array{key: string, value: mixed, opts: list<mixed>}> $invalid */
        $invalid = $error['invalid_values'];
        el_append_invalid_value_summaries($widget_type, $invalid, $limit, $picked);
    }
}

/**
 * True when any invalid-value entry is keyed by a number — the mark of a
 * list-shaped `settings` payload, whose indexes can never name a control.
 *
 * @param list<array{key: string, value: mixed, opts: list<mixed>}> $invalid
 */
function el_has_numeric_settings_key(array $invalid): bool
{
    foreach ($invalid as $entry) {
        if (ctype_digit($entry['key'])) {
            return true;
        }
    }
    return false;
}

/**
 * @param list<string> $dropped
 * @param list<string> $picked
 */
function el_append_dropped_key_summaries(string $widget_type, array $dropped, int $limit, array &$picked): void
{
    foreach ($dropped as $key) {
        if (count($picked) >= $limit) {
            return;
        }
        $picked[] = sprintf('unknown_key widget="%s" key="%s"', $widget_type, $key);
    }
}

/**
 * @param list<array{key: string, value: mixed, opts: list<mixed>}> $invalid
 * @param list<string> $picked
 */
function el_append_invalid_value_summaries(string $widget_type, array $invalid, int $limit, array &$picked): void
{
    foreach ($invalid as $entry) {
        if (count($picked) >= $limit) {
            return;
        }
        $picked[] = sprintf(
            'invalid_value widget="%s" key="%s" value=%s',
            $widget_type,
            $entry['key'],
            el_short_json($entry['value']),
        );
    }
}

/**
 * @param list<array{style_id: string, reason: string, path: string}> $style_errors
 * @param list<string> $picked
 */
function el_append_style_error_summaries(string $widget_type, array $style_errors, int $limit, array &$picked): void
{
    foreach ($style_errors as $style_error) {
        if (count($picked) >= $limit) {
            return;
        }
        $picked[] = sprintf(
            'style widget="%s" style_id="%s" reason=%s path=%s',
            $widget_type,
            $style_error['style_id'],
            $style_error['reason'],
            $style_error['path'] === '' ? '(root)' : $style_error['path'],
        );
    }
}

/**
 * Render up to $limit concrete key-level failures from a single-element
 * validation result so the reasons are visible even when only the `error`
 * string is passed through.
 *
 * @param list<string> $dropped
 * @param list<array{key: string, value: mixed, opts: list<mixed>}> $invalid
 */
function el_summarize_single_element_errors(array $dropped, array $invalid, int $limit): string
{
    $picked = [];
    foreach ($dropped as $key) {
        if (count($picked) >= $limit) {
            break;
        }
        $picked[] = sprintf('unknown_key key="%s"', $key);
    }
    foreach ($invalid as $entry) {
        if (count($picked) >= $limit) {
            break;
        }
        $picked[] = sprintf('invalid_value key="%s" value=%s', $entry['key'], el_short_json($entry['value']));
    }

    if ($picked === []) {
        return '';
    }

    return sprintf('First %d: %s.', count($picked), implode('; ', $picked));
}

/**
 * JSON-encode a value for inclusion in an error message, trimming long output
 * so the inline summary stays readable.
 */
function el_short_json(mixed $value): string
{
    $encoded = json_encode($value);
    if (!is_string($encoded)) {
        return '<unencodable>';
    }
    if (strlen($encoded) > 60) {
        return substr($encoded, offset: 0, length: 57) . '...';
    }
    return $encoded;
}

/**
 * Look up the group of a dynamic tag by name from the Elementor registry.
 */
function el_resolve_dynamic_tag_group(string $tag_name): string
{
    if (!class_exists('Elementor\\Plugin')) {
        return '';
    }

    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;
    /** @var object|null $dt_manager */
    $dt_manager = $plugin->dynamic_tags ?? null;
    if (!is_object($dt_manager) || !method_exists($dt_manager, 'get_tag_info')) {
        return '';
    }

    /** @var array<string, mixed>|null $info */
    $info = $dt_manager->get_tag_info($tag_name);
    if (!is_array($info)) {
        return '';
    }

    // The group is on the tag instance, not in the info array.
    /** @var object|null $instance */
    $instance = $info['instance'] ?? null;
    if (is_object($instance) && method_exists($instance, 'get_group')) {
        /** @var mixed $group */
        $group = $instance->get_group();
        if (is_string($group) && $group !== '') {
            return $group;
        }
        // Some tags return group as an array.
        if (is_array($group) && $group !== []) {
            return (string) $group[0];
        }
    }

    return '';
}

/**
 * Extract the plain list of class names from a `classes` setting value
 * which may be in wrapped `{$$type, value}` format or a plain array.
 *
 * @return list<string>
 */
function el_extract_classes_list(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    if (array_key_exists('value', $raw) && is_array($raw['value'])) {
        /** @var list<string> */
        return array_values($raw['value']);
    }

    if (el_array_is_list($raw)) {
        /** @var list<string> $raw */
        return $raw;
    }

    return [];
}

/**
 * Normalize breakpoints in a bare per-element styles map (no surrounding
 * element node). Used by edit-element so the null-breakpoint base variant
 * shape — which add-element tree: already rewrites silently — is accepted
 * through the update path too instead of bouncing off Style_Parser with a
 * `missing_or_invalid_value` at `meta.breakpoint`.
 *
 * @param array<string, mixed> $styles
 * @return array<string, mixed>
 */
function el_normalize_styles_map_breakpoints(array $styles): array
{
    $out = [];
    foreach (array_keys($styles) as $sid) {
        $normalized = el_normalize_style_breakpoint_definition($styles[$sid]);
        $out[$sid] = $normalized ?? $styles[$sid];
    }
    return $out;
}

/**
 * Recursively normalize style variant breakpoints in a subtree.
 *
 * Elementor's Style_Parser rejects `breakpoint: null` even though
 * Style_Variant::build() produces it. Replace null (or any non-string /
 * empty) breakpoints with EL_BASE_BREAKPOINT ("desktop"), which is what
 * Atomic_Styles_Manager uses as DEFAULT_BREAKPOINT.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_normalize_style_breakpoints(array $node): array
{
    // Only rewrite keys that already exist: this also runs on every tree
    // el_write_page() persists (v3 pages included), where adding an empty
    // `styles` / `elements` to nodes that never had one would change the
    // stored shape for no reason.
    if (is_array($node['styles'] ?? null)) {
        /** @var array<string, mixed> $styles */
        $styles = $node['styles'];
        foreach (array_keys($styles) as $sid) {
            $normalized_style = el_normalize_style_breakpoint_definition($styles[$sid]);
            if ($normalized_style === null) {
                continue;
            }
            $styles[$sid] = $normalized_style;
        }
        $node['styles'] = $styles;
    }

    if (is_array($node['elements'] ?? null)) {
        $children = $node['elements'];
        foreach (array_keys($children) as $key) {
            /** @var mixed $child */
            $child = $children[$key];
            if (!is_array($child)) {
                continue;
            }
            /** @var array<string, mixed> $child */
            $children[$key] = el_normalize_style_breakpoints($child);
        }
        $node['elements'] = $children;
    }

    return $node;
}

/**
 * Normalize missing / null / "" breakpoints inside a single style definition
 * (healer: any other value, including a wrong type, is left untouched —
 * see el_is_healable_breakpoint). Variants are rewritten under their existing
 * keys; non-array variants and non-array metas are skipped.
 *
 * @param mixed $style
 * @return array<string, mixed>|null
 */
function el_normalize_style_breakpoint_definition(mixed $style): ?array
{
    if (!is_array($style) || !is_array($style['variants'] ?? null)) {
        return null;
    }

    $variants = $style['variants'];
    foreach (array_keys($variants) as $vi) {
        /** @var mixed $variant */
        $variant = $variants[$vi];
        if (!is_array($variant) || !is_array($variant['meta'] ?? null)) {
            continue;
        }
        /** @var array<string, mixed> $meta */
        $meta = $variant['meta'];
        if (!el_is_healable_breakpoint($meta)) {
            continue;
        }
        $meta['breakpoint'] = EL_BASE_BREAKPOINT;
        $variant['meta'] = $meta;
        $variants[$vi] = $variant;
    }

    $style['variants'] = $variants;
    /** @var array<string, mixed> $style */
    return $style;
}

/**
 * Recursively rename per-element style IDs that are not valid CSS class
 * identifiers (e.g. start with a digit). Elementor element IDs are 7-hex
 * strings and frequently begin with a digit, and the agent commonly
 * derives style IDs from them (`8750d6d-bg`, `22a3022-h1`, ...). The
 * Elementor CSS generator emits these literally as selectors
 * (`.elementor .8750d6d-bg`) — but per the CSS Syntax spec, a class
 * selector may not start with a digit, so the browser silently drops
 * the entire rule and the page renders unstyled.
 *
 * For every per-element style id that begins with a digit (or any other
 * pattern that would be invalid as a CSS identifier), prepend `e-` so
 * the rule survives the parser. Update the matching `id` field inside
 * the style definition and rewrite the corresponding entries in
 * `settings.classes` so the HTML class attribute and the generated CSS
 * selector stay in sync.
 *
 * Global Class IDs referenced in `settings.classes` but not present in
 * the node's `styles` map are left alone — those are managed by the
 * Global Classes API, not per-element.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_sanitize_style_class_ids(array $node): array
{
    $node = el_sanitize_style_class_ids_on_node($node);

    /** @var list<array<string, mixed>> $children */
    $children = is_array($node['elements'] ?? null) ? $node['elements'] : [];
    $new_children = [];
    foreach ($children as $child) {
        $new_children[] = el_sanitize_style_class_ids($child);
    }
    $node['elements'] = $new_children;

    return $node;
}

/**
 * Apply the CSS-identifier rename to a single node's `styles` map and
 * mirror the renames into `settings.classes`.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_sanitize_style_class_ids_on_node(array $node): array
{
    $styles = is_array($node['styles'] ?? null) ? $node['styles'] : [];
    if ($styles === []) {
        return $node;
    }

    $renames = el_style_id_renames(array_keys($styles));
    if ($renames === []) {
        return $node;
    }

    $node['styles'] = el_apply_style_id_renames($styles, $renames);

    /** @var array<string, mixed> $settings */
    $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];
    $node['settings'] = el_apply_class_renames_to_settings($settings, $renames);

    return $node;
}

/**
 * Rewrite the `classes` value in a settings array so any per-element
 * style id mentioned there is replaced with its sanitized counterpart.
 * Accepts both the wrapped `{$$type: "classes", value: [...]}` shape
 * and the legacy bare list form.
 *
 * @param array<string, mixed> $settings
 * @param array<string, string> $renames
 * @return array<string, mixed>
 */
function el_apply_class_renames_to_settings(array $settings, array $renames): array
{
    if (!array_key_exists('classes', $settings)) {
        return $settings;
    }

    /** @var mixed $raw_classes */
    $raw_classes = $settings['classes'];

    if (
        is_array($raw_classes)
        && ($raw_classes['$$type'] ?? null) !== null
        && is_array($raw_classes['value'] ?? null)
    ) {
        /** @var list<mixed> $values */
        $values = $raw_classes['value'];
        $raw_classes['value'] = el_rename_class_list($values, $renames);
        $settings['classes'] = $raw_classes;
        return $settings;
    }

    if (is_array($raw_classes)) {
        /** @var list<mixed> $values */
        $values = $raw_classes;
        $settings['classes'] = el_rename_class_list($values, $renames);
    }

    return $settings;
}

/**
 * Apply rename map to a flat list of class names.
 *
 * @param list<mixed> $values
 * @param array<string, string> $renames
 * @return list<mixed>
 */
function el_rename_class_list(array $values, array $renames): array
{
    $out = [];
    foreach (array_keys($values) as $i) {
        /** @var mixed $cls */
        $cls = $values[$i];
        if (is_string($cls) && array_key_exists($cls, $renames)) {
            $out[] = $renames[$cls];
            continue;
        }
        $out[] = $cls;
    }
    return $out;
}

/**
 * The rename map for one node's per-element style ids: every id that is not a
 * valid CSS class name mapped to its CSS-safe form (el_make_safe_class_id()).
 * Deterministic and independent of key order — no id is ever invented.
 *
 * An id whose safe form is already taken — by another key of the map, or by
 * the safe form of another id — is NOT renamed: renaming it would overwrite a
 * style. Every write boundary refuses such a map before anything is written
 * (el_style_id_collisions()), so this only protects styles already stored in
 * that shape by something else; they keep their unsafe id instead of being
 * lost.
 *
 * The not-persisted report derives style identities from the same map, so
 * what it compares is what this rename stores.
 *
 * @param list<array-key> $keys
 * @return array<string, string>
 */
function el_style_id_renames(array $keys): array
{
    $targets = [];
    foreach ($keys as $key) {
        if (!is_string($key)) {
            continue;
        }
        $target = el_make_safe_class_id($key);
        $targets[$target] = ($targets[$target] ?? 0) + 1;
    }
    $renames = [];
    foreach ($keys as $key) {
        if (!is_string($key)) {
            continue;
        }
        $safe = el_make_safe_class_id($key);
        if ($safe === $key || $targets[$safe] > 1) {
            continue;
        }
        $renames[$key] = $safe;
    }
    return $renames;
}

/**
 * The sent style ids that cannot be stored without overwriting another style:
 * an id whose CSS-safe form (el_make_safe_class_id()) is also another sent id
 * or the safe form of one (`1foo` with `e-1foo`, or with `-1foo`), or — on a
 * merge into an element's existing styles — the id of a style already stored
 * (`1foo` when `e-1foo` is stored).
 *
 * The storage format keeps no record of which id a style was sent under, so
 * such an id cannot be resolved to one intended style; the write is refused
 * and the caller picks the id. The result depends only on the SET of ids,
 * never on their order.
 *
 * @param list<array-key> $sent_keys
 * @param list<array-key> $stored_keys Ids already stored on the element that the sent map is merged into.
 * @return list<array{style_id: string, reason: string, path: string, stored_as: string, conflicts_with: list<string>}>
 */
function el_style_id_collisions(array $sent_keys, array $stored_keys = []): array
{
    /** @var array<string, list<string>> $by_target */
    $by_target = [];
    foreach ($sent_keys as $key) {
        if (!is_string($key)) {
            continue;
        }
        $by_target[el_make_safe_class_id($key)][] = $key;
    }
    ksort($by_target, SORT_STRING);
    $stored = array_flip(array_map(static fn(int|string $key): string => (string) $key, $stored_keys));

    $collisions = [];
    foreach ($by_target as $target => $ids) {
        sort($ids, SORT_STRING);
        foreach ($ids as $id) {
            if ($id === $target) {
                continue;
            }
            $others = array_values(array_diff($ids, [$id]));
            if ($others === [] && array_key_exists($target, $stored)) {
                $others = [$target];
            }
            if ($others === []) {
                continue;
            }
            $collisions[] = [
                'style_id' => $id,
                'reason' => 'style_id_collision',
                'path' => '',
                'stored_as' => $target,
                'conflicts_with' => $others,
            ];
        }
    }
    return $collisions;
}

/**
 * The sentence explaining refused style ids to the caller.
 *
 * @param list<array{style_id: string, reason: string, path: string, stored_as: string, conflicts_with: list<string>}> $collisions
 */
function el_style_id_collision_message(array $collisions): string
{
    $parts = [];
    foreach ($collisions as $collision) {
        $parts[] = sprintf(
            'style id "%s" would be stored as "%s", colliding with %s',
            $collision['style_id'],
            $collision['stored_as'],
            implode(' / ', array_map(static fn(string $other): string => sprintf(
                '"%s"',
                $other,
            ), $collision['conflicts_with'])),
        );
    }
    return sprintf(
        'Nothing was written: %s. A style id cannot start with a digit, "-<digit>" or "--", so such an id is stored with an "e" prefix, and it would overwrite the other style. To change a style that is already stored, send it under its stored id; otherwise choose ids that stay distinct.',
        implode('; ', $parts),
    );
}

/**
 * The collision records of every node in a subtree, in the tree validation
 * error shape (see el_collect_tree_style_id_errors()). Nodes of a subtree are
 * new, so only ids sent together can collide.
 *
 * @param array<string, mixed> $node
 * @return list<array<string, mixed>>
 */
function el_collect_tree_style_id_collisions(array $node): array
{
    $errors = [];
    $styles = is_array($node['styles'] ?? null) ? $node['styles'] : [];
    $collisions = el_style_id_collisions(array_keys($styles));
    if ($collisions !== []) {
        $errors[] = [
            'element_id' => (string) ($node['id'] ?? ''),
            'widget_type' => (string) ($node['widgetType'] ?? $node['elType'] ?? ''),
            'dropped_keys' => [],
            'invalid_values' => [],
            'style_errors' => $collisions,
        ];
    }
    /** @var list<array<string, mixed>> $children */
    $children = is_array($node['elements'] ?? null) ? $node['elements'] : [];
    foreach ($children as $child) {
        $errors = [...$errors, ...el_collect_tree_style_id_collisions($child)];
    }
    return $errors;
}

/**
 * The tree-mode / set-content response refusing colliding style ids: the
 * standard tree validation response, with the headline replaced by the
 * collision explanation.
 *
 * @param list<array<string, mixed>> $collision_errors
 * @return array<string, mixed>
 */
function el_build_tree_style_id_collision_response(array $collision_errors): array
{
    $response = build_tree_validation_error_response([
        'ok' => false,
        'tree' => [],
        'errors' => $collision_errors,
        'schemas' => [],
    ], []);
    /** @var list<array{style_id: string, reason: string, path: string, stored_as: string, conflicts_with: list<string>}> $all */
    $all = [];
    foreach ($collision_errors as $error) {
        /** @var list<array{style_id: string, reason: string, path: string, stored_as: string, conflicts_with: list<string>}> $style_errors */
        $style_errors = $error['style_errors'] ?? [];
        $all = [...$all, ...$style_errors];
    }
    $response['error'] = el_style_id_collision_message($all);
    return $response;
}

/**
 * Rename the keys of a per-element styles map (and each entry's own `id`
 * when it matches its key) by an el_style_id_renames() map. Keys not in the
 * map keep their name; with a collision-free map no entry overwrites another.
 *
 * @param array<array-key, mixed> $styles
 * @param array<string, string> $renames
 * @return array<array-key, mixed>
 */
function el_apply_style_id_renames(array $styles, array $renames): array
{
    $renamed = [];
    foreach (array_keys($styles) as $key) {
        $new_key = is_string($key) && array_key_exists($key, $renames) ? $renames[$key] : $key;
        /** @var mixed $value */
        $value = $styles[$key];
        if (is_array($value) && ($value['id'] ?? null) === $key) {
            $value['id'] = $new_key;
        }
        $renamed[$new_key] = $value;
    }
    return $renamed;
}

/**
 * The id the boxed-container split gives the inner node it generates for the
 * container `$original_id`. The not-persisted report follows moved fields to
 * exactly this node.
 */
function el_boxed_inner_id(string $original_id): string
{
    return $original_id . '-in';
}

/**
 * The v3 container setting names (responsive suffixes stripped) that the
 * tree-mode conversion consumes without leaving the same key behind: the
 * translation map's keys (rewritten into style props), `boxed_width` and
 * `content_width` (turned into the outer/inner split), `width` (replaced by
 * the inner node's 100%) and `html_tag` (stored as `tag`). The not-persisted
 * report leaves exactly these unchecked, and says so to the agent.
 *
 * @return list<string>
 */
function el_v3_container_consumed_setting_bases(): array
{
    return array_values(array_unique([
        ...array_keys(el_v3_container_translation_map()),
        'boxed_width',
        'content_width',
        'width',
        'html_tag',
    ]));
}

/**
 * Return a CSS-identifier-safe version of an arbitrary string id by
 * prepending `e-` when the input would be illegal as a class selector
 * (begins with a digit, with `-<digit>`, or with `--`). Leaves valid
 * ids untouched. Empty input is returned unchanged.
 */
function el_make_safe_class_id(string $id): string
{
    if ($id === '') {
        return $id;
    }
    $first = $id[0];
    if ($first >= '0' && $first <= '9') {
        return 'e-' . $id;
    }
    if ($first !== '-' || strlen($id) < 2) {
        return $id;
    }
    $second = $id[1];
    if ($second >= '0' && $second <= '9' || $second === '-') {
        return 'e' . $id;
    }
    return $id;
}

/**
 * Recursively sync per-element style IDs into settings.classes.
 *
 * Elementor v4 needs style IDs in TWO places: the `styles` field (for CSS
 * generation) and `settings.classes` (for the class to appear in HTML).
 * This function walks the tree and auto-adds any style IDs from `styles`
 * into `settings.classes` so the agent doesn't need to duplicate them.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_sync_style_classes(array $node): array
{
    /** @var array<string, mixed> $settings */
    $settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];

    // Always normalize classes to wrapped {$$type, value} format.
    // Also merge per-element style IDs from the `styles` field.
    $existing = el_extract_classes_list($settings['classes'] ?? null);

    $styles = is_array($node['styles'] ?? null) ? $node['styles'] : [];
    foreach (array_keys($styles) as $sid) {
        // Only string ids can become CSS classes; non-string ids are
        // rejected by the validators rather than synced.
        if (!is_string($sid) || in_array($sid, $existing, strict: true)) {
            continue;
        }
        $existing[] = $sid;
    }

    if ($existing !== []) {
        $settings['classes'] = ['$$type' => 'classes', 'value' => $existing];
        $node['settings'] = $settings;
    }

    // Recurse into children.
    /** @var list<array<string, mixed>> $children */
    $children = is_array($node['elements'] ?? null) ? $node['elements'] : [];
    $new_children = [];
    foreach ($children as $child) {
        $new_children[] = el_sync_style_classes($child);
    }
    $node['elements'] = $new_children;

    return $node;
}

/**
 * Recursively ensure every element in a subtree has an `id` field.
 * Missing IDs are auto-generated.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_ensure_tree_ids(array $node): array
{
    /** @var string|null $id */
    $id = $node['id'] ?? null;
    if (!is_string($id) || $id === '') {
        $node['id'] = el_generate_id();
    }

    /** @var list<array<string, mixed>> $children */
    $children = is_array($node['elements'] ?? null) ? $node['elements'] : [];
    $new_children = [];
    foreach ($children as $child) {
        $new_children[] = el_ensure_tree_ids($child);
    }
    $node['elements'] = $new_children;

    return $node;
}

/**
 * Shared tail: read the current page, insert the validated node, write it
 * back. Returns the success payload or an error.
 *
 * @param array{post_id: int, element_type: string, widget_type: string, settings: array<string, mixed>, styles: array<string, mixed>, parent_id: string|null, position: int, element_id: string} $parsed
 * @param array<string, mixed> $node
 * @param array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}> $sent The fields
 *        the caller sent, reported under `not_persisted` when the stored node lacks them.
 * @param array<string, mixed> $success_extras Merged into the success response.
 * @return array<string, mixed>|WP_Error
 */
function el_insert_and_write(array $parsed, array $node, array $sent, array $success_extras): array|WP_Error
{
    [$elements, $error] = el_read_page($parsed['post_id']);
    if ($elements === null) {
        return ['success' => false, 'error' => $error ?? 'Unknown error.'];
    }

    $insert_error = el_insert_into_tree(
        $elements,
        $node,
        $parsed['parent_id'],
        $parsed['position'],
        $parsed['post_id'],
    );
    if ($insert_error !== null) {
        return ['success' => false, 'error' => $insert_error];
    }

    $result = el_write_page($parsed['post_id'], $elements);
    if ($result instanceof WP_Error) {
        return $result;
    }

    // Only what this call sent for the new element: a schema default filled in
    // by the server is not the caller's text to report on, and neither report
    // covers it. Not-stored fields first, markup warnings second.
    return el_with_write_reports(
        ['success' => true] + $success_extras,
        $parsed['post_id'],
        $sent,
        [],
        [
            'elements' => [$node],
            'sent' => [
                (string) ($node['id'] ?? '') => array_map(
                    static fn(int|string $key): string => (string) $key,
                    array_keys($parsed['settings']),
                ),
            ],
        ],
    );
}

/**
 * Validate the user-supplied per-element styles map for a single-element
 * insert and attach it to the freshly built node. Reuses the same
 * `el_validate_update_styles` validator the edit-element path uses, so
 * non-atomic targets and malformed shapes get the same error reporting.
 * After attaching, runs the universal atomic finalize so style IDs sync
 * into `settings.classes` (otherwise the styles never bind on render).
 *
 * `sent_styles` is the styles map as the caller sent it (see
 * el_validate_update_styles()).
 *
 * @param array<string, mixed> $node
 * @param array<string, mixed> $styles
 * @return array{node: array<string, mixed>, sent_styles: array<string, mixed>}|array{error_response: array<string, mixed>}
 */
function el_attach_add_element_styles(array $node, array $styles): array
{
    if ($styles === []) {
        return ['node' => $node, 'sent_styles' => []];
    }

    $validation = el_validate_update_styles($node, $styles);
    if (array_key_exists('error_response', $validation)) {
        return ['error_response' => $validation['error_response']];
    }

    $node['styles'] = $validation['styles'] ?? [];
    // The styles map arrives with its ids already made CSS-safe; mirror the
    // same renames into any classes the caller listed in settings.
    /** @var array<string, mixed> $node_settings */
    $node_settings = is_array($node['settings'] ?? null) ? $node['settings'] : [];
    $node['settings'] = el_apply_class_renames_to_settings($node_settings, $validation['renames'] ?? []);

    /** @var list<array{element_id: string, widget_type: string, setting_name: string, tag: mixed, reason: string}> $dynamic_tag_errors */
    $dynamic_tag_errors = [];
    $node = el_finalize_atomic_node($node, $dynamic_tag_errors);
    if ($dynamic_tag_errors !== []) {
        return [
            'error_response' => [
                'success' => false,
                'error' => sprintf(
                    'Add aborted: %d dynamic tag parse error(s) on the new element. Fix the reported tag string(s) and retry.',
                    count($dynamic_tag_errors),
                ),
                'dynamic_tag_errors' => $dynamic_tag_errors,
            ],
        ];
    }

    return ['node' => $node, 'sent_styles' => $validation['sent_styles'] ?? []];
}

/**
 * Run the deep atomic check set-content applies to every node on the single
 * element a direct write (add-element single mode, edit-element) is about to
 * persist. The compact-schema validator checks only the outer `$$type` of an
 * enveloped value, so a correct envelope around an inner payload Elementor
 * refuses on save (untyped `attributes` items, a non-numeric
 * `component_instance.component_id`, …) passed it; this runs Elementor's own
 * `Props_Parser` over the settings via {@see el_deep_validate_atomic_element()}.
 *
 * Returns the single-element error response, carrying the violations in the
 * same `{key, value, opts}` records set-content reports, or null when there is
 * nothing to refuse. Elements without a raw props schema (v3 widgets, the v3
 * container) are skipped, exactly as in set-content.
 *
 * @param array<string, mixed> $element
 * @return array<string, mixed>|null
 */
function el_deep_validate_single_element(array $element, string $element_type): ?array
{
    if (!is_array($element['settings'] ?? null)) {
        return null;
    }

    $widget_type = el_element_widget_type($element);
    $violations = el_deep_validate_atomic_element($element, $widget_type);
    if ($violations === []) {
        return null;
    }

    /** @var array<string, mixed> $settings */
    $settings = $element['settings'];
    return build_single_element_error_response($element_type, $widget_type, [
        'ok' => false,
        'settings' => $settings,
        'dropped' => [],
        'invalid' => $violations,
    ]);
}

/**
 * Build a fail-hard error response for a single-element write that failed
 * validation. Embeds the element's CONTENT-ONLY compact schema inline so
 * the caller can correct the settings without a second schema lookup and
 * without bloating the error payload with every style/advanced control.
 *
 * @param array{ok: bool, settings: array<string, mixed>, dropped: list<string>, invalid: list<array{key: string, value: mixed, opts: list<mixed>}>} $validation
 * @return array<string, mixed>
 */
function build_single_element_error_response(string $element_type, string $widget_type, array $validation): array
{
    $dropped = $validation['dropped'];
    $invalid = $validation['invalid'];

    $content_schema = resolve_compact_schema($widget_type, ['include_styles' => false]);
    $is_atomic = is_array($content_schema) && $content_schema['is_atomic'] === true;

    $misplaced_style_props = $is_atomic ? el_misplaced_style_props_from_dropped($dropped) : [];
    $label = $element_type === 'container' ? 'container' : sprintf('widget "%s"', $widget_type);

    $message = sprintf(
        'Invalid settings for %s: %d unknown key(s) and %d invalid value(s). Use the "schema" field to correct your payload and retry — do not invent control IDs or enum values.',
        $label,
        count($dropped),
        count($invalid),
    );
    $summary = el_summarize_single_element_errors($dropped, $invalid, limit: 3);
    if ($summary !== '') {
        $message .= ' ' . $summary;
    }
    if (el_has_numeric_settings_key($invalid)) {
        $message .= ' Note: `settings` must be an object keyed by control id, not a list.';
    }
    if ($misplaced_style_props !== []) {
        $message .= sprintf(
            ' Note: [%s] are CSS properties from the atomic Style Schema, not controls — they belong in the element\'s `styles` map (see `styling_hint`).',
            implode(', ', $misplaced_style_props),
        );
    }

    $response = [
        'success' => false,
        'error' => $message,
        'element_type' => $element_type,
        'dropped_keys' => $dropped,
        'invalid_values' => $invalid,
    ];

    if ($element_type === 'widget') {
        $response['widget_type'] = $widget_type;
    }

    if ($content_schema !== null) {
        $response['schema'] = $content_schema;
    }

    if ($misplaced_style_props !== []) {
        $response['styling_hint'] = el_build_styling_hint($misplaced_style_props);
    }

    return $response;
}

/**
 * Pick the subset of dropped setting keys that are actually CSS properties in
 * the atomic Style Schema. These are the ones an agent almost certainly meant
 * to send through the `styles` map instead of via `settings`, so the caller
 * can fix the shape in a single retry. Returns the empty list when nothing
 * dropped matches a known style prop.
 *
 * @param list<string> $dropped
 * @return list<string>
 */
function el_misplaced_style_props_from_dropped(array $dropped): array
{
    if ($dropped === []) {
        return [];
    }
    $style_props = el_style_schema_property_names();
    if ($style_props === []) {
        return [];
    }
    $style_set = array_flip($style_props);
    $out = [];
    foreach ($dropped as $key) {
        if (!array_key_exists($key, $style_set)) {
            continue;
        }
        $out[] = $key;
    }
    return $out;
}

/**
 * Build the inline `styling_hint` payload that teaches the caller the shape
 * the `styles` map expects. Ships a minimal example wrapping the first
 * misplaced prop so the retry is copy-paste-able.
 *
 * @param list<string> $misplaced_style_props
 * @return array{message: string, example: array<string, mixed>, reference: string}
 */
function el_build_styling_hint(array $misplaced_style_props): array
{
    // Caller guarantees at least one entry; `?? 'gap'` would be dead code
    // and the analyzer flags it.
    $first = $misplaced_style_props[0];
    $message = sprintf(
        'On atomic (v4) elements, layout/spacing/typography live in the `styles` map on the element, not in `settings`. Move [%s] into a per-element style like the example, then retry. Call novamira/elementor-get-style-schema properties:["%s"] for the exact value shape.',
        implode(', ', $misplaced_style_props),
        $first,
    );

    $example = [
        'styles' => [
            's-1' => [
                'id' => 's-1',
                'type' => 'class',
                'label' => 's-1',
                'variants' => [
                    [
                        'meta' => ['breakpoint' => EL_BASE_BREAKPOINT, 'state' => null],
                        'props' => [
                            $first => ['$$type' => '<see get-style-schema>', 'value' => '<see get-style-schema>'],
                        ],
                    ],
                ],
            ],
        ],
    ];

    return [
        'message' => $message,
        'example' => $example,
        'reference' => 'novamira/elementor-get-style-schema',
    ];
}

/**
 * Map single mode's native type spellings onto its primary parameter names.
 *
 * The direction is the mirror image of {@see el_apply_node_type_aliases}: in a
 * node the Elementor key is primary and the snake_case one is the alias, while
 * in these top-level parameters `element_type` / `widget_type` are the declared
 * primary names — the ones carrying the enum and named by every error message.
 * One rule covers both: the key the surface documents as primary answers when
 * both are given.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function el_apply_add_element_type_aliases(array $input): array
{
    foreach (['element_type' => 'elType', 'widget_type' => 'widgetType'] as $primary => $alias) {
        if (!array_key_exists($alias, $input)) {
            continue;
        }
        if (($input[$primary] ?? null) === null) {
            /** @var mixed $value */
            $value = $input[$alias];
            $input[$primary] = $value;
        }
        unset($input[$alias]);
    }
    return $input;
}

/**
 * Validate and normalize the input for add-element.
 *
 * @param array<string, mixed> $input
 * @return array{error: string}|array{post_id: int, element_type: string, widget_type: string, settings: array<string, mixed>, styles: array<string, mixed>, parent_id: string|null, position: int, element_id: string}
 */
function el_parse_add_element_input(array $input): array
{
    $preflight = el_parse_add_element_preflight($input);
    if (array_key_exists('error', $preflight)) {
        return $preflight;
    }

    /** @var array{post_id: int, element_type: string} $preflight */
    /** @var array<string, mixed> $settings */
    $settings = is_array($input['settings'] ?? null) ? $input['settings'] : [];
    /** @var array<string, mixed> $styles */
    $styles = is_array($input['styles'] ?? null) ? $input['styles'] : [];

    return [
        'post_id' => $preflight['post_id'],
        'element_type' => $preflight['element_type'],
        'widget_type' => (string) ($input['widget_type'] ?? ''),
        'settings' => $settings,
        'styles' => $styles,
        'parent_id' => el_parse_parent_id($input['parent_id'] ?? null),
        'position' => el_parse_position($input['position'] ?? null),
        'element_id' => is_string($input['element_id'] ?? null) ? $input['element_id'] : '',
    ];
}

/**
 * Refuse a tree-mode call that also carries element-describing top-level
 * parameters, naming where the value belongs inside `tree`.
 *
 * In tree mode the subtree is the whole description of what gets inserted, so
 * every one of these was ignored. Three of them said so in the `tree`
 * description; `element_id` and `styles` did not, which made them the worst
 * kind of parameter — schema-valid, silently discarded, and reported as
 * `success: true` with a generated id and no styles. Refusing all of them keeps
 * one rule instead of a list of exceptions: describe the element inside
 * `tree`, and use `parent_id` / `position` to say where it goes.
 *
 * @param array<string, mixed> $input
 */
function el_reject_tree_mode_element_params(array $input): ?WP_Error
{
    $misplaced = [];
    foreach ([
        'element_type' => 'tree.elType',
        'elType' => 'tree.elType',
        'widget_type' => 'tree.widgetType',
        'widgetType' => 'tree.widgetType',
        'element_id' => 'tree.id',
        'settings' => 'tree.settings',
        'styles' => 'tree.styles',
    ] as $param => $target) {
        if (!array_key_exists($param, $input) || $input[$param] === null) {
            continue;
        }
        $misplaced[$param] = $target;
    }
    if ($misplaced === []) {
        return null;
    }

    $messages = [];
    foreach ($misplaced as $param => $target) {
        $messages[] = sprintf('%s → %s', $param, $target);
    }

    return new WP_Error(
        'tree_mode_conflicting_parameter',
        sprintf(
            'In tree mode the subtree describes the element, so these top-level parameters have no effect and were refused instead of being dropped: %s. Move the value into the tree node and keep only post_id, tree, parent_id and position at the top level.',
            implode(', ', $messages),
        ),
        [
            'status' => 400,
            'invalid_values' => array_map(
                static fn(string $param, string $target): array => [
                    'key' => $param,
                    'value' => $param,
                    'expected' => $target,
                ],
                array_keys($misplaced),
                array_values($misplaced),
            ),
        ],
    );
}

/**
 * Validate and normalize the input for tree-mode add-element.
 *
 * @param array<string, mixed> $input
 * @return array{error: string}|array{post_id: int, tree: array<string, mixed>, parent_id: string|null, position: int}
 */
function el_parse_tree_insert_input(array $input): array
{
    if (!class_exists('Elementor\\Plugin')) {
        return ['error' => 'Elementor is not active.'];
    }

    $post_id = (int) ($input['post_id'] ?? 0);
    if ($post_id <= 0 || !get_post($post_id)) {
        return ['error' => "Post {$post_id} not found."];
    }

    if (!is_array($input['tree'] ?? null) || $input['tree'] === []) {
        return ['error' => 'tree is required in tree mode.'];
    }

    /** @var array<string, mixed> $tree */
    $tree = $input['tree'];

    // Structural type aliases are mapped onto the native keys at the boundary,
    // recursively and with the same helper set-content uses, so both writers
    // accept the same subtree. The type requirement itself is checked by
    // el_add_tree_element() at every depth, after this mapping.
    $tree = el_apply_tree_type_aliases($tree);

    return [
        'post_id' => $post_id,
        'tree' => $tree,
        'parent_id' => el_parse_parent_id($input['parent_id'] ?? null),
        'position' => el_parse_position($input['position'] ?? null),
    ];
}

/**
 * Preflight checks for add-element input. Enforces that Elementor is loaded,
 * the post exists, and element_type is one of the allowed values.
 *
 * @param array<string, mixed> $input
 * @return array{error: string}|array{post_id: int, element_type: string}
 */
function el_parse_add_element_preflight(array $input): array
{
    if (!class_exists('Elementor\\Plugin')) {
        return ['error' => 'Elementor is not active.'];
    }

    $post_id = (int) ($input['post_id'] ?? 0);
    if ($post_id <= 0 || !get_post($post_id)) {
        return ['error' => "Post {$post_id} not found."];
    }

    $element_type = (string) ($input['element_type'] ?? '');
    if ($element_type === '') {
        return [
            'error' => 'Parameter "element_type" (or "elType") is required (unless using "tree" mode). Use "widget", "container", "e-flexbox", or "e-div-block".',
        ];
    }
    $valid_types = ['widget', 'container', ...NOVAMIRA_ATOMIC_CONTAINER_TYPES];
    if (!in_array($element_type, $valid_types, strict: true)) {
        return [
            'error' => sprintf(
                'Unknown element_type "%s". Valid values: "widget", "container", "e-flexbox", "e-div-block".',
                $element_type,
            ),
        ];
    }

    return ['post_id' => $post_id, 'element_type' => $element_type];
}

function el_parse_parent_id(mixed $raw): ?string
{
    return is_string($raw) && $raw !== '' ? $raw : null;
}

function el_parse_position(mixed $raw): int
{
    return $raw === null ? -1 : (int) $raw;
}

/**
 * Validate a user-supplied element_id slug. Pure check, no tree access.
 *
 * The id flows directly into rendered HTML (`data-id`, `elementor-element-<id>`)
 * and, for v4 atomic elements with synthesized local styles, into the rendered
 * CSS class name (`s-<id>`). The slug rules are the intersection of
 * "safe as a CSS class name" and "safe as an HTML attribute value".
 *
 * @return array{ok: true, slug: string}|array{ok: false, error: string}
 */
function el_validate_element_id_slug(string $raw): array
{
    if ($raw === '') {
        return ['ok' => false, 'error' => 'element_id cannot be empty.'];
    }
    $len = strlen($raw);
    if ($len < 2 || $len > 50) {
        return [
            'ok' => false,
            'error' => sprintf('element_id "%s" must be 2-50 characters long (got %d).', $raw, $len),
        ];
    }
    if (preg_match('/^[a-z][a-z0-9_-]*$/', $raw) !== 1) {
        return [
            'ok' => false,
            'error' => sprintf(
                'element_id "%s" must start with a lowercase letter and contain only lowercase letters, digits, hyphens, or underscores.',
                $raw,
            ),
        ];
    }
    if ($raw === 'container') {
        return [
            'ok' => false,
            'error' => 'element_id "container" is reserved by Elementor — pick a different slug.',
        ];
    }

    return ['ok' => true, 'slug' => $raw];
}

/**
 * Resolve the id to assign to a new element. When the caller passed a custom
 * `element_id`, validate the slug and ensure no node in the existing tree
 * already uses it; otherwise generate a fresh 7-char hex id matching
 * Elementor's own auto-id format. The tree read is shared with the
 * subsequent insert path but is cheap (single post-meta read).
 *
 * @return array{ok: true, id: string}|array{ok: false, error: string}
 */
function el_resolve_new_element_id(int $post_id, string $raw): array
{
    if ($raw === '') {
        return ['ok' => true, 'id' => el_generate_id()];
    }

    $validation = el_validate_element_id_slug($raw);
    if (!$validation['ok']) {
        return $validation;
    }
    $slug = $validation['slug'];

    [$elements, $error] = el_read_page($post_id);
    if ($elements === null) {
        return ['ok' => false, 'error' => $error ?? 'Unknown error.'];
    }

    if (el_find($elements, $slug) !== null) {
        return [
            'ok' => false,
            'error' => sprintf(
                'element_id "%s" is already used by another element on this page. Pick a different slug or omit element_id to auto-generate.',
                $slug,
            ),
        ];
    }

    return ['ok' => true, 'id' => $slug];
}

/**
 * Insert an element into a list at the given position (or append when position < 0).
 *
 * @param list<array<string, mixed>> $list
 * @param array<string, mixed>       $element
 */
function el_insert(array &$list, array $element, int $position): void
{
    if ($position < 0 || $position >= count($list)) {
        $list[] = $element;
        return;
    }
    array_splice($list, $position, length: 0, replacement: [$element]);
}

/**
 * Insert an element into the tree, either at the root or under a parent ID.
 *
 * @param list<array<string, mixed>> $elements
 * @param array<string, mixed>       $element
 * @return string|null Error message, or null on success.
 */
function el_insert_into_tree(array &$elements, array $element, ?string $parent_id, int $position, int $post_id): ?string
{
    if ($parent_id === null) {
        el_insert($elements, $element, $position);
        return null;
    }

    $found = el_mutate($elements, $parent_id, static function (array $node) use ($element, $position): array {
        /** @var list<array<string, mixed>> $children */
        $children = is_array($node['elements'] ?? null) ? $node['elements'] : [];
        el_insert($children, $element, $position);
        $node['elements'] = $children;
        return $node;
    });

    return $found ? null : "Parent '{$parent_id}' not found on post {$post_id}.";
}
