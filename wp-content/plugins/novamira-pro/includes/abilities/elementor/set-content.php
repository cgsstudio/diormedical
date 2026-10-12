<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

use WP_Error;

/**
 * Ability: Set the Elementor document tree for a post.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/elementor-set-content', [
    'label' => __('Set Elementor Content', domain: 'novamira-pro'),
    'description' =>
        __(
            'Writes a full Elementor document tree to a post, invalidates Elementor\'s internal CSS caches, and fires WordPress\'s `clean_post_cache` so third-party optimization plugins (Perfmatters, WP Rocket, LiteSpeed, etc.) that listen for post changes can purge their per-post caches and the frontend reflects the change immediately. The write is verified by reading the document data back: success means the stored tree contains every top-level element that was sent (`elements_written`), otherwise the call fails instead of reporting a false success. Validation runs server-side on every call: unknown control IDs or invalid enum values abort the write (nothing is persisted) and return the compact content-only schemas of the affected widget types INLINE in the error, so you can correct and retry in a single roundtrip. novamira/elementor-get-schema is an OPTIONAL discovery tool for learning what widgets exist and what their controls look like — it is NOT required before writing, validation is automatic. Shape rules: use only keys present in the widget\'s "controls" map; select/choose values MUST come from the control\'s "opts"; switchers use "rv" for on and "" for off; typography requires typography_typography="custom" first; dimensions are {unit,top,right,bottom,left,isLinked}; sliders are {size,unit}; colors are #RRGGBB; scalars for "arr: true" v3 controls are auto-wrapped into a one-element array; v3 controls carrying an "r" flag in the schema accept responsive overrides via the suffixed key `<key>_<breakpoint>` (e.g. typography_font_size_tablet, padding_mobile, _padding_widescreen) — `r:1` means every breakpoint is allowed, while `r:{min:<bp>}` / `r:{max:<bp>}` / `r:{min,max}` restrict the suffix to a closed window in the canonical size order (mobile < mobile_extra < tablet < tablet_extra < laptop < desktop < widescreen). Breakpoint names are the v4 ones listed in check-setup.kit.active_breakpoints and the same value shape applies to every variant; where novamira/elementor-check-setup reports atomic.runtime_available: true, ATOMIC (v4) widgets accept ergonomic scalar values which the server auto-wraps into {"$$type": "<prop_key>", "value": <scalar>} — for both `settings` AND inside the `styles` map\'s `props` (e.g. color:"#FFFFFF", font-size:72, padding:{block-start:16,...}); or you can pass the wrapped shape directly; v4 atomic responsive uses the `styles` map\'s `variants[]` with `meta.breakpoint` (NOT suffixed keys); CONTENT FIELDS — single-line text controls (heading.title, button.text, icon-list[].text, image.caption, …) take plain text only; wysiwyg controls (text-editor.editor, testimonial.testimonial_content, …) take inline formatting only (`<strong>`, `<em>`, `<a>`, `<br>`); NEVER wrap content in inline-styled `<p style="…">…</p>` or other layout markup — alignment goes in `align`, font sizing/weight/family in `typography_*`, max-width in `_element_custom_width`, spacing in `_padding`/`_margin` (all responsive via the suffix shape above); the dedicated `html` widget IS the place for arbitrary HTML; do not emit controls whose "if" condition is not satisfied.',
            domain: 'novamira-pro',
        ) . el_not_persisted_description_sentence(),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post ID. Its post type has to support Elementor — a post type that does not (another plugin\'s own template or content store, for instance) is refused with an error whose text opens with elementor_unsupported_post_type and names the post type and where that content belongs instead. Elementor\'s own editor will not open a post of such a type either, so this is the same boundary the editor draws, not an extra one.',
            ],
            'content' => [
                'type' => 'array',
                'description' =>
                    'Top-level Elementor elements (containers/sections) as a JSON array of element objects. An empty array intentionally clears the document; a string or any other non-array value is rejected. Each element uses either `element_type` (consistent with add-element; this site accepts '
                        . el_element_type_enum_text()
                        . ') or the native Elementor `elType` key — both are accepted at every depth, and `elType` wins if both are given. Widget elements also accept `widget_type` or `widgetType`. Settings (a JSON object) and optional nested `elements` children (a JSON array — never a stringified list) complete each node. IDs are auto-generated where missing. The same subtree is accepted by novamira/elementor-add-element in tree mode, with one deliberate difference: an element that names no type at all is taken to be a widget HERE (and then fails for the missing widgetType), while add-element refuses it. This surface writes the whole document and owns that default; inserting a subtree into an existing page does not guess. An element that names a `widgetType` needs no `elType` on either surface — both read the envelope from it. Ten mistyped node keys are REFUSED by name with the right key given — `element_id`, `elementId`, `element_settings`, `elementSettings`, `children`, `style`, `elementType`, `is_inner`, `is_locked`, `editorSettings` — because each used to be dropped while the write still reported success. That is a named list, not a check of the whole node shape: another unknown key still goes through, and on a v3 tree Elementor drops it on save without either of us noticing.',
                'items' => [
                    'type' => 'object',
                    'properties' => el_node_schema_properties(),
                ],
            ],
            'template_type' => [
                'type' => 'string',
                'description' => 'Elementor template type, e.g. "wp-page", "header", "footer", "single-post", "loop-item". Omit it or pass an empty string to keep the post\'s existing Elementor template type, so rewriting a header, footer or loop item template leaves its type intact; when the post has no template type yet, "wp-page" is written, and Elementor\'s own save may replace it with the type it uses for that post type (a regular post ends up "wp-post"). A given value converts the document to that type and must be one Elementor accepts for this post: a template library post takes the template types Elementor registers for its template library (not "wp-page", "wp-post", or a document type Elementor keeps in a post type of its own; "widget" only on a post that already is a Global Widget, since Global Widgets are created from the Elementor editor), a page takes "wp-page", a post takes "wp-post", and a post type without its own Elementor document type takes "wp-page" or "wp-post". Any other value is refused with an error listing the accepted types, and nothing is changed. A post whose stored template type is not available on this site (typically because the plugin providing it, such as Elementor Pro, is inactive) is refused unless a valid template_type is given to convert it.',
            ],
        ],
        'required' => ['post_id', 'content'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'post_id' => ['type' => 'integer'],
            'edit_url' => ['type' => 'string'],
            'assigned_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Element IDs that were auto-generated for elements that did not provide one.',
            ],
            'elements_written' => [
                'type' => 'integer',
                'description' => 'Number of top-level elements persisted, confirmed by reading the document data back after the write. 0 for an intentional wipe (`content: []`).',
            ],
            EL_NOT_PERSISTED_KEY => el_not_persisted_output_schema(),
            'warnings' => el_markup_warnings_schema(),
            'invalid_values' => [
                'type' => 'array',
                'description' => 'Type violations keyed by content path (content[i].elements[j]…), each as {key, value, expected}: a non-string styles[*].variants[*].meta.breakpoint, or a `settings` / `elements` / element entry that is present but not an object / array (also carried in the error data of the `invalid_tree_shape` WP_Error). Enum violations are reported through `errors` / `schemas`. Only present on validation failures.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'key' => [
                            'type' => 'string',
                            'description' => 'Path of the rejected value: a control id, a styles["<id>"].variants[i].meta.breakpoint path, or a tree-shape path such as content[i].elements[j] / content[i].elements[j].settings for an entry that is not an object / array.',
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
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\elementor_set_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'instructions' => 'Each element accepts either the add-element-style keys (element_type + widget_type) or the native Elementor keys (elType + widgetType) — both work, at every depth, and the native key wins if both are given. The same is true of novamira/elementor-add-element tree mode, so a subtree written here can be inserted there unchanged. Top-level containers use element_type="container" or elType="container". Widgets use element_type="widget" + widget_type="heading" (or elType="widget" + widgetType="heading"). Children go in the "elements" array. Settings shape depends on the widget/container type — use novamira/elementor-get-schema to inspect it. IDs are 7-char hex; provide your own or let them be auto-generated. Where novamira/elementor-check-setup reports atomic.runtime_available: true, atomic v4 widgets auto-wrap scalar values into the $$type envelope — both for settings and inside per-element styles\' variant props; otherwise build the tree from "container" + v3 widgets.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function elementor_set_content(array $input): array|WP_Error
{
    if (!class_exists('Elementor\\Plugin')) {
        return ['success' => false, 'error' => 'Elementor is not active.'];
    }

    $post_id = (int) ($input['post_id'] ?? 0);
    if ($post_id <= 0 || !get_post($post_id)) {
        return ['success' => false, 'error' => "Post {$post_id} not found."];
    }

    // Null when omitted, so el_write_page() keeps the post's existing template
    // type instead of resetting a library template (loop item, header, …) to
    // a plain page.
    /** @var mixed $raw_template_type */
    $raw_template_type = $input['template_type'] ?? null;
    $template_type = is_string($raw_template_type) ? $raw_template_type : null;

    $prepared = el_prepare_set_content_tree($input['content'] ?? null);
    if (array_key_exists('response', $prepared)) {
        return $prepared['response'];
    }
    /** @var array{tree: list<array<string, mixed>>, assigned_ids: list<string>, sent: array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}>} $prepared */

    $result = el_write_page($post_id, $prepared['tree'], $template_type);
    if ($result instanceof WP_Error) {
        return $result;
    }

    // First what was not stored, then what will render differently — and no
    // rendering claim for a field that was not stored (el_with_write_reports()).
    return el_with_write_reports(
        [
            'success' => true,
            'post_id' => $post_id,
            'edit_url' => (string) get_edit_post_link($post_id, context: 'raw'),
            'assigned_ids' => $prepared['assigned_ids'],
            'elements_written' => count($prepared['tree']),
        ],
        $post_id,
        $prepared['sent'],
        [],
        ['elements' => $prepared['tree'], 'sent' => null],
    );
}

/**
 * Validate and normalize a set-content payload without writing anything.
 * Template creation uses this same boundary before allocating a post.
 *
 * On success `sent` names the fields the caller sent (el_sent_fields()), taken
 * once ids are assigned and ergonomic styles expanded but before the finalize
 * pipeline and Style_Parser, for the not-persisted report.
 *
 * @return array{tree: list<array<string, mixed>>, assigned_ids: list<string>, sent: array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}>}|array{response: array<string, mixed>|WP_Error}
 */
function el_prepare_set_content_tree(mixed $raw_content): array
{
    // Schema validation accepts "" and false for `type: array`; coercing those
    // to [] would wipe the document on a mistyped payload. Only a real array is
    // a document tree — an explicit [] is the intentional wipe.
    if (!is_array($raw_content)) {
        return ['response' => new WP_Error(
            'invalid_content',
            sprintf(
                'Parameter "content" must be a JSON array of Elementor element objects; received %s. Pass an empty array ([]) to intentionally clear the document.',
                gettype($raw_content),
            ),
            ['status' => 400],
        )];
    }
    $content = array_values($raw_content);

    $boundary = el_set_content_input_boundary($content);
    if (array_key_exists('response', $boundary)) {
        return ['response' => $boundary['response']];
    }

    /** @var array{content: list<array<string, mixed>>} $boundary */
    $content = $boundary['content'];

    $assigned = [];
    $normalized = el_normalize_tree($content, $assigned);

    // Integer style ids (a list-shaped `styles`, or a `"0"` key) must be
    // rejected before the finalize pipeline runs its styles walkers.
    $style_id_errors = el_collect_forest_style_id_errors($normalized);
    if ($style_id_errors !== []) {
        return ['response' => el_build_tree_style_id_error_response($style_id_errors)];
    }

    // The flat `{css-prop: value}` styles shape means here what it means in
    // add-element single mode and edit-element. Runs after normalization
    // because the synthesized style id is derived from the node's id.
    $ergonomic_errors = [];
    $normalized = el_expand_ergonomic_forest_styles($normalized, prefix: 'content', errors: $ergonomic_errors);
    if ($ergonomic_errors !== []) {
        return ['response' => el_build_ergonomic_tree_styles_error($ergonomic_errors)];
    }

    // Two style ids that would be stored under one name are refused
    // (el_style_id_collisions()).
    $collisions = [];
    foreach ($normalized as $node) {
        $collisions = [...$collisions, ...el_collect_tree_style_id_collisions($node)];
    }
    if ($collisions !== []) {
        return ['response' => el_build_tree_style_id_collision_response($collisions)];
    }
    $sent = el_sent_fields($normalized);

    // Run the same universal atomic pipeline add-element does, so a
    // page written from scratch via set-content gets the same wrapping,
    // CSS-id sanitization, breakpoint normalization, and classes sync.
    // V3-only steps (boxed splitter, v3 settings translator) deliberately
    // do NOT run here — set-content is the "I already have v4 atomic"
    // surface and must not silently rewrite legitimate v4 values.
    $dynamic_tag_errors = [];
    $normalized = el_finalize_atomic_forest($normalized, $dynamic_tag_errors);

    $validation = el_validate_tree($normalized);
    if ($dynamic_tag_errors !== [] || !$validation['ok']) {
        // An unresolvable `e-*` type on a site without the atomic runtime is a
        // site-state problem, not a payload typo — report it as such.
        $atomic_error = el_atomic_unavailable_tree_error($validation['errors']);
        return ['response' => $atomic_error ?? build_tree_validation_error_response($validation, $dynamic_tag_errors)];
    }

    return ['tree' => $validation['tree'], 'assigned_ids' => $assigned, 'sent' => $sent];
}

/**
 * The input boundary for a raw `content` list: everything that has to be true
 * of the payload as it arrived, before ids are assigned and before any
 * normalizer can turn a caller mistake into a plausible-looking document.
 *
 * In order: every entry is an element object and every nested `elements` /
 * `settings` is an array (a normalizer would otherwise drop a stringified
 * child list); no node carries a key that merely looks like a node key (each of
 * those used to be discarded while the write still reported success); the two
 * structural type aliases are mapped onto Elementor's own keys by the same
 * shared helper add-element tree mode uses, so both writers accept the same
 * trees; and malformed style breakpoints are reported by path.
 *
 * Returns the alias-normalized content, or the first failure to hand straight
 * back to the caller — the same `{content}` / `{response}` split
 * el_prepare_tree_for_insert() uses, because a breakpoint failure is reported
 * as a response array and everything else as a WP_Error.
 *
 * @param array<array-key, mixed> $content
 * @return array{content: list<array<string, mixed>>}|array{response: array<string, mixed>|WP_Error}
 */
function el_set_content_input_boundary(array $content): array
{
    $invalid_shapes = el_find_invalid_forest_shapes($content);
    if ($invalid_shapes !== []) {
        return ['response' => el_build_invalid_tree_shape_error('Invalid content', $invalid_shapes)];
    }

    /** @var list<array<string, mixed>> $content */
    $confusable = el_find_confusable_forest_keys($content, prefix: 'content');
    if ($confusable !== []) {
        return ['response' => el_build_confusable_node_key_error(
            'Invalid content',
            $confusable,
            el_confusable_forest_residue_paths($confusable, $content, prefix: 'content'),
        )];
    }

    $aliased = el_apply_forest_type_aliases($content);

    // Path shape: content[i].elements[j].styles["id"].variants[k].meta.breakpoint
    $invalid_breakpoints = [];
    foreach (array_keys($aliased) as $index) {
        $invalid_breakpoints += el_find_invalid_tree_breakpoints($aliased[$index], sprintf('content[%d].', $index));
    }
    if ($invalid_breakpoints !== []) {
        return ['response' => el_build_invalid_breakpoint_response('Invalid styles in content', $invalid_breakpoints)];
    }

    return ['content' => $aliased];
}

/**
 * Run el_find_invalid_tree_shapes() over the raw top-level node list, naming
 * each entry `content[i]` — a non-object entry is reported under that path.
 *
 * @param array<array-key, mixed> $content
 * @return array<string, mixed>
 */
function el_find_invalid_forest_shapes(array $content): array
{
    $invalid = [];
    foreach (array_keys($content) as $index) {
        /** @var mixed $item */
        $item = $content[$index];
        $path = sprintf('content[%s]', $index);
        if (!is_array($item)) {
            $invalid[$path] = $item;
            continue;
        }
        /** @var array<string, mixed> $item */
        $invalid += el_find_invalid_tree_shapes($item, $path . '.');
    }
    return $invalid;
}
