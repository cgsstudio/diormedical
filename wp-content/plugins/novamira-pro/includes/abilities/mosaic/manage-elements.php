<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// add-element
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-add-element', [
    'label' => __('Add Mosaic Element', domain: 'novamira-pro'),
    'description' => __(
        'Inserts a new element node into a Mosaic document (template / component / master / styleGuide). Position with `placement` = `at_end` (default), `at_start`, `before` (requires `anchor_id`), or `after` (requires `anchor_id`). The new node gets a generated UUID and a fractional-index `ordering` that sorts correctly between siblings. The `data` blob is element-type-specific (see Mosaic NodeTypes source for the shape per element-type). Returns the full presented node so the caller knows the assigned id. Style the element through Mosaic\'s design system: attach Classes with `classes` and pick its Variant with `variant`; `css_classes` is only for class names that come from outside Mosaic. Auto-routing: passing `parent_id = document_id` on a template silently nests the new node under the template-internal canvas wrapper so Mosaic\'s renderer picks it up — adding directly under the template document would orphan the content. **Text elements are WYSIWYG holders**: `type=text` produces a container, the visible string lives in a child `type=wysiwyg-text` node with `data={text:"..."}` — add both to make text reach the rendered HTML.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => mo_element_add_input_schema(),
    'output_schema' => [
        'type' => 'object',
        'properties' => ['node' => ['type' => 'object']],
        'required' => ['node'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_add_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Parent_id should be the document_id when adding a root node and a node id otherwise. Use get-element-tree first to discover existing ids.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// edit-element
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-edit-element', [
    'label' => __('Edit Mosaic Element', domain: 'novamira-pro'),
    'description' => __(
        'Updates the `data` blob of an existing Mosaic node. The merge strategy is `merge` by default — a shallow array-merge of your supplied keys onto the existing blob, so keys you do NOT send (the element\'s classes, children, other attributes, content) are PRESERVED. Pass `mode = replace` for a full overwrite (your supplied data becomes the new blob verbatim — use this only when you are sending the complete blob, e.g. to also DROP keys). Nested `data` keys are not deep-merged, and the top-level `style` / `attributes` / `css_classes` shortcuts each rebuild their whole key. The Mosaic class inputs are surgical instead: `classes` / `detach_classes` merge into the element\'s attached Classes by Class, and `variant` replaces only the element\'s Variant — both leave the rest of the element\'s Mosaic style data untouched. Returns the updated node. Use mosaic-move-element for re-parenting and re-ordering, NOT this ability.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Node UUID.'],
            'data' => [
                'type' => 'object',
                'description' => 'New element settings blob (Mosaic-native shape — `attributes` is an array of `{name, value, uuid}` records here). Most callers should use the top-level `style` / `attributes` shortcuts instead.',
            ],
            'style' => mo_style_input_property_schema(),
            'attributes' => mo_attributes_input_property_schema(),
            'css_classes' => mo_css_classes_input_property_schema(),
            'classes' => mo_classes_input_property_schema(),
            'detach_classes' => mo_detach_classes_input_property_schema(),
            'variant' => mo_variant_input_property_schema(),
            'mode' => ['type' => 'string', 'enum' => ['replace', 'merge'], 'default' => 'merge'],
        ],
        'required' => ['id', 'data'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['node' => ['type' => 'object']],
        'required' => ['node'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_edit_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default `mode = merge` preserves the keys you do not send (classes, children, attributes) — safe for partial edits. Pass `mode = replace` only when sending the COMPLETE blob (it overwrites verbatim and can wipe untouched data); nested keys are not deep-merged.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// delete-element
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-delete-element', [
    'label' => __('Delete Mosaic Element', domain: 'novamira-pro'),
    'description' => __(
        'Soft-deletes a Mosaic node and every descendant: their `status` flips to `delete`. The rows stay in the table and can be resurrected manually via direct SQL, but they are hidden from every read ability and from Mosaic\'s own renderer. Returns the count of nodes that flipped status. Cross-document stale-ref note: deleting a node inside a Component document (documentType=`component`) does NOT cascade to `component-instance` nodes in other templates that reference it — Mosaic\'s renderer falls back to silent empty render for an unresolved instance, and CSS variable refs (`{var: <uuid>}`) emit `var(--unknown-variable)`. Both fallbacks are by Mosaic\'s design, but the agent should expect visible gaps on the front-end until the orphan instances are removed too.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Node UUID to delete.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['deleted_count' => ['type' => 'integer']],
        'required' => ['deleted_count'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_delete_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Soft delete — the descendants flip status=delete and disappear from every read path. Mosaic does not currently expose an in-app "trash" view, so consider the operation visually permanent. When deleting a Component (documentType=`component`), check for `component-instance` references in other templates and remove them too — Mosaic silently empty-renders stale refs rather than cascading.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// move-element
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-move-element', [
    'label' => __('Move Mosaic Element', domain: 'novamira-pro'),
    'description' => __(
        'Re-parents and/or re-orders a Mosaic node within the same document. Pass `parent_id` to re-parent (a node id to move under, or the document_id to make it a root); pass `placement` = `at_end` / `at_start` / `before` (with `anchor_id`) / `after` (with `anchor_id`) to position it. The node\'s subtree moves with it (children keep their relative orderings). Returns `{ node: {...} }` with the updated node. Cross-document moves are not supported: the move always stays inside the node\'s original document, so passing a parent_id from a different document returns mo_not_found.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Node UUID to move.'],
            'parent_id' => [
                'type' => 'string',
                'description' => 'New parent — a node id, or the document_id to attach as a root. Omit to keep the current parent.',
            ],
            'placement' => [
                'type' => 'string',
                'enum' => ['at_end', 'at_start', 'before', 'after'],
                'default' => 'at_end',
            ],
            'anchor_id' => [
                'type' => 'string',
                'description' => 'Sibling node id; required when placement is `before` or `after`.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['node' => ['type' => 'object']],
        'required' => ['node'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_move_element',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'When re-parenting to the document root, set parent_id = the document_id (NOT the literal string "document").',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// Shared input-schema for add (used in two places — keep DRY).
// ---------------------------------------------------------------------------

/**
 * @return array<string, mixed>
 */
function mo_element_add_input_schema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'document_type' => ['type' => 'string', 'enum' => ['template', 'master', 'styleGuide', 'component']],
            'document_id' => ['type' => 'string'],
            'parent_id' => [
                'type' => 'string',
                'description' => 'Parent node id, or the document_id to add at the root.',
            ],
            'type' => [
                'type' => 'string',
                'description' => 'Mosaic element type (e.g. `section`, `div`, `text`, `button`).',
            ],
            'data' => [
                'type' => 'object',
                'default' => [],
                'description' => 'Element settings blob (element-type specific). Internal Mosaic shape — `attributes` is an array of `{name, value, uuid}` records here. Most callers should pass HTML attributes via the top-level `style` / `attributes` shortcuts instead.',
            ],
            'style' => mo_style_input_property_schema(),
            'attributes' => mo_attributes_input_property_schema(),
            'css_classes' => mo_css_classes_input_property_schema(),
            'classes' => mo_classes_input_property_schema(),
            'detach_classes' => mo_detach_classes_input_property_schema(),
            'variant' => mo_variant_input_property_schema(),
            'placement' => [
                'type' => 'string',
                'enum' => ['at_end', 'at_start', 'before', 'after'],
                'default' => 'at_end',
            ],
            'anchor_id' => ['type' => 'string', 'description' => 'Sibling node id when placement is `before`/`after`.'],
        ],
        'required' => ['document_type', 'document_id', 'parent_id', 'type'],
        'additionalProperties' => false,
    ];
}

/**
 * Shared schema fragment for the agent-facing `style` parameter on
 * add-element + edit-element. Mirrors the dual-shape pattern Elementor's
 * specialization uses: accepts either a raw CSS string or a {prop: value}
 * map; both normalize internally to Mosaic's `attributes[].name='style'`
 * record so the agent never needs to know the array-of-records shape.
 *
 * @return array<string, mixed>
 */
function mo_style_input_property_schema(): array
{
    return [
        'type' => ['string', 'object', 'null'],
        'description' => 'Inline CSS for this element. Accepts TWO shapes. (1) CSS STRING — `"background: #4F46E5; padding: 60px 40px;"` — the most common case, written out verbatim. (2) PROPERTY MAP — `{"background":"#4F46E5","padding":"60px 40px","font-size":"48px"}` — auto-converted to a CSS string for storage. Both shapes land as `attributes[name=style, value=<css>]` on the node, matching Mosaic\'s internal schema. Pass `null` or omit to leave existing inline style untouched on edit; pass an empty string `""` to clear it. The Mosaic-native `data.attributes` array-of-records still works for power users who need full control over multiple HTML attributes at once.',
    ];
}

/**
 * Shared schema fragment for the agent-facing `attributes` parameter:
 * a {name: value} map for HTML attrs beyond style (e.g. `id`, `role`,
 * `aria-label`, `data-*`). Mosaic stores attributes as an array of
 * `{name, value, uuid}` records; we accept the natural agent-facing map and
 * convert. The Mosaic-native form on `data.attributes` still works.
 *
 * @return array<string, mixed>
 */
function mo_attributes_input_property_schema(): array
{
    return [
        'type' => 'object',
        'description' => 'Other HTML attributes for this element, as a `{name: value}` map (e.g. `{"id":"hero","role":"region","data-section":"hero"}`). Reserved names: `style` belongs on the top-level `style` parameter; for `class`, use `classes` (Mosaic Classes) or `css_classes` (class names from outside Mosaic). Both this map and the top-level `style` are normalized into Mosaic\'s internal `attributes[].name/.value/.uuid` array-of-records before storage — the Mosaic-native shape on `data.attributes` still works in parallel.',
    ];
}

/**
 * One-shot normalizer for the agent-facing element data: runs the
 * round-9 style/attributes shortcuts, then the class inputs
 * (`css_classes` / `classes` / `detach_classes` / `variant`, see
 * element-class-inputs.php — theme-scoped, so only when `$theme_id` is
 * known), then the depth/bytes blob guards. Extracted so add/edit
 * callbacks stay under the cyclomatic-complexity threshold.
 *
 * `$raw_data` is the caller's own `data` payload (so the class guard can
 * inspect a native `cssClasses` list the caller sent); `$element_type` is
 * the node type the `variant` input is validated against.
 *
 * Returns the normalized `data` (with string keys) or a WP_Error from
 * any of the validators.
 *
 * @param  array<string, mixed> $data
 * @param  array<string, mixed> $input
 * @param  array<string, mixed> $raw_data
 * @return array<string, mixed>|WP_Error
 */
function mo_normalize_element_data(
    array $data,
    array $input,
    ?string $theme_id,
    string $element_type,
    array $raw_data,
): array|WP_Error {
    $normalized = mo_normalize_element_attributes($data, $input['style'] ?? null, $input['attributes'] ?? null);
    if ($normalized instanceof WP_Error) {
        return $normalized;
    }
    $data = $normalized;
    if ($theme_id !== null) {
        $with_classes = mo_apply_element_class_inputs($data, $input, $raw_data, $theme_id, $element_type);
        if ($with_classes instanceof WP_Error) {
            return $with_classes;
        }
        $data = $with_classes;
    }
    $depth_err = mo_validate_blob_depth($data, field: 'data');
    if ($depth_err !== null) {
        return $depth_err;
    }
    $bytes_err = mo_validate_blob_bytes($data, field: 'data');
    if ($bytes_err !== null) {
        return $bytes_err;
    }
    return $data;
}

// ---------------------------------------------------------------------------
// Callbacks
// ---------------------------------------------------------------------------

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_add_element(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $ctx = mo_resolve_add_context($input);
    if ($ctx instanceof WP_Error) {
        return $ctx;
    }
    return mo_with_lock("nodes:{$ctx['document_id']}", static fn(): array|WP_Error => mo_insert_element_locked($ctx));
}

/**
 * Compute parent_type / theme_id + validate every input piece before
 * we take the lock. Extracted so mosaic_add_element stays lean.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mo_resolve_add_context(array $input): array|WP_Error
{
    $required = mo_extract_add_required($input);
    if ($required instanceof WP_Error) {
        return $required;
    }
    /** @var array<string, mixed> $data */
    $data = is_array($input['data'] ?? null) ? $input['data'] : [];
    $theme_id = mo_document_theme_id($required['document_type'], $required['document_id']);
    $normalized_data = mo_normalize_element_data($data, $input, $theme_id, $required['type'], $data);
    if ($normalized_data instanceof WP_Error) {
        return $normalized_data;
    }
    $data = $normalized_data;
    if ($theme_id === null) {
        return mo_error('mo_not_found', sprintf(
            /* translators: 1: document type ('template', 'master', 'styleGuide', 'component'), 2: document UUID. */
            __('Mosaic %1$s %2$s not found.', domain: 'novamira-pro'),
            $required['document_type'],
            $required['document_id'],
        ));
    }
    $route = mo_route_add_parent($required);
    if ($route instanceof WP_Error) {
        return $route;
    }
    return array_merge($required, [
        'theme_id' => $theme_id,
        'parent_type' => $route['parent_type'],
        'parent_id' => $route['parent_id'],
        'data' => $data,
        'placement' => is_string($input['placement'] ?? null) ? $input['placement'] : 'at_end',
        'anchor_id' => mo_norm_uuid($input['anchor_id'] ?? null),
    ]);
}

/**
 * Resolve `parent_id` / `parent_type` for an add. Centralizes the
 * "parent_id = document_id auto-routes under canvas" and "parent_id
 * must be a real node" logic so the surrounding context-resolver stays
 * flat (no if/elseif/else nesting).
 *
 * @param array{document_type:string, document_id:string, parent_id:string, type:string} $required
 * @return array{parent_id:string, parent_type:string}|WP_Error
 */
function mo_route_add_parent(array $required): array|WP_Error
{
    if ($required['parent_id'] === $required['document_id']) {
        return mo_resolve_root_parent($required['document_type'], $required['document_id']);
    }
    if (mo_node_ordering($required['document_type'], $required['document_id'], $required['parent_id']) === null) {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: parent node UUID. */
            __('Parent node %s not found in this document.', domain: 'novamira-pro'),
            $required['parent_id'],
        ));
    }
    return ['parent_id' => $required['parent_id'], 'parent_type' => 'node'];
}

/**
 * Resolve "add at root" semantics. Mosaic templates have a canvas
 * wrapper (`template-internal`) right under the document — the renderer
 * iterates THAT wrapper's children, not the document's direct children.
 *
 * Round 8 H2 found that non-template documents (master / styleGuide /
 * component) have STRUCTURAL roots (`master-root` / `styleguide-root` /
 * `component-root`) that are NOT canvas wrappers. Adding content directly
 * under one of those roots creates an orphan-prone shape that Mosaic's
 * `heal()` silently flips to `status=delete` the next time the editor
 * opens the document. There is no canonical "free content slot" the
 * agent can target by document_id alone — it has to pick a specific
 * inner node id (e.g. the `div(main)` inside a master's Document/Body
 * subtree).
 *
 * Returns the canvas wrapper for templates, a typed `mo_invalid_input`
 * for non-templates pointing the agent at mosaic-get-element-tree to
 * discover a valid inner node.
 *
 * @return array{parent_id:string, parent_type:string}|WP_Error
 */
function mo_resolve_root_parent(string $document_type, string $document_id): array|WP_Error
{
    $canvas = mo_document_canvas_root($document_type, $document_id);
    if ($canvas !== null) {
        return ['parent_id' => $canvas, 'parent_type' => 'node'];
    }
    return mo_error('mo_invalid_input', sprintf(
        /* translators: 1: document type (master / styleGuide / component), 2: document UUID. */
        __(
            'Mosaic %1$s documents have no canonical content slot under their structural root. Call mosaic-get-element-tree on document %2$s to discover a real inner node id (e.g. the `div(main)` inside the Document/Body subtree on a master), and pass that as parent_id. Adding directly under the document would create an orphan-prone shape that Mosaic\'s heal() silently flips to delete on the next editor open.',
            domain: 'novamira-pro',
        ),
        $document_type,
        $document_id,
    ));
}

/**
 * Find the content-canvas wrapper node for a Mosaic document. Only
 * `template` documents have a free-content canvas (`template-internal`)
 * — Mosaic's `master-root`, `component-root`, and `styleguide-root` are
 * STRUCTURAL roots that hold further wrappers (Document/Body/Main on
 * masters, etc.) and are NOT valid parents for free content. Adding
 * content under a master-root would put it as a sibling of the
 * Document/Body/Main subtree, which Mosaic's `heal()` orphans on next
 * editor open.
 *
 * We also match by exact `type` (not just `parentType`) so a stray
 * direct-child node — e.g. a `div` accidentally inserted at document
 * root by an older agent — does not get treated as the canvas wrapper.
 *
 * Returns the wrapper's UUID, or null when no canonical wrapper exists.
 */
function mo_document_canvas_root(string $document_type, string $document_id): ?string
{
    if ($document_type !== 'template') {
        return null;
    }
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('nodes');
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $wpdb->prepare(
        "SELECT `ID` FROM `{$table}` WHERE `documentType` = %s AND `documentID` = %s AND `parentType` = %s AND `parentID` = %s AND `type` = 'template-internal' AND `status` = 'publish' ORDER BY BINARY `ordering` ASC LIMIT 1",
        $document_type,
        $document_id,
        $document_type,
        $document_id,
    );
    /** @var mixed $id */
    // @mago-expect analysis:possibly-invalid-argument
    $id = $wpdb->get_var($prepared);
    return is_string($id) && $id !== '' ? $id : null;
}

/**
 * Pull the 4 required string fields out of the input array, fail fast
 * with mo_invalid_input if any is missing. Returned as an associative
 * array so the caller can spread it into the wider context map.
 *
 * @param array<string, mixed> $input
 * @return array{document_type:string, document_id:string, parent_id:string, type:string}|WP_Error
 */
function mo_extract_add_required(array $input): array|WP_Error
{
    $document_type = is_string($input['document_type'] ?? null) ? $input['document_type'] : '';
    $document_id = mo_norm_uuid($input['document_id'] ?? null);
    $parent_id = mo_norm_uuid($input['parent_id'] ?? null);
    $type = is_string($input['type'] ?? null) ? $input['type'] : '';
    if ($document_id === '' || $parent_id === '' || $type === '' || mo_root_parent_type($document_type) === null) {
        return mo_error('mo_invalid_input', __(
            'document_type, document_id, parent_id, type are required.',
            domain: 'novamira-pro',
        ));
    }
    $len_err = mo_validate_lengths(['type' => ['value' => $type, 'max' => 128]]);
    if ($len_err !== null) {
        return $len_err;
    }
    return [
        'document_type' => $document_type,
        'document_id' => $document_id,
        'parent_id' => $parent_id,
        'type' => $type,
    ];
}

/**
 * Narrow $ctx['data'] to the array<string,mixed> shape mo_encode_object_blob
 * expects, so the analyzer can flow types end-to-end without a less-specific
 * argument warning at the wpdb->insert call site. Uses the object-typed
 * encoder because `wp_mosaic_nodes.data` is decoded to `object` by Mosaic
 * (an empty `[]` literal would TypeError the NodeRevisionRecord constructor).
 *
 * @param array<string, mixed> $ctx
 */
function mo_encode_data_from_ctx(array $ctx): string
{
    /** @var array<string, mixed> $data */
    $data = is_array($ctx['data'] ?? null) ? $ctx['data'] : [];
    return mo_encode_object_blob($data);
}

/**
 * Compute the ordering + INSERT the node. Called under the document lock.
 *
 * @param array<string, mixed> $ctx
 * @return array<string, mixed>|WP_Error
 */
function mo_insert_element_locked(array $ctx): array|WP_Error
{
    $ordering = mo_compute_placement($ctx);
    if ($ordering instanceof WP_Error) {
        return $ordering;
    }
    $node_id = mo_uuid();
    global $wpdb;
    /** @var \wpdb $wpdb */
    $ok = $wpdb->insert(
        mo_table('nodes'),
        [
            'themeID' => $ctx['theme_id'],
            'documentType' => $ctx['document_type'],
            'documentID' => $ctx['document_id'],
            'ID' => $node_id,
            'parentType' => $ctx['parent_type'],
            'parentID' => $ctx['parent_id'],
            'ordering' => $ordering,
            'status' => 'publish',
            'type' => $ctx['type'],
            'data' => mo_encode_data_from_ctx($ctx),
            'revision' => mo_uuid(),
            'version' => mo_version(),
        ],
        array_fill(start_index: 0, count: 12, value: '%s'),
    );
    if ($ok === false) {
        return mo_error('mo_write_failed', __('Could not insert the Mosaic node.', domain: 'novamira-pro'));
    }
    $row = mo_load_node((string) $ctx['document_type'], (string) $ctx['document_id'], $node_id);
    if ($row === null) {
        return mo_error('mo_not_found', __('Inserted node could not be re-read.', domain: 'novamira-pro'));
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-add-element',
        operation: 'add',
        entity: 'node',
        entity_id: $node_id,
        context: [
            'document_type' => (string) $ctx['document_type'],
            'document_id' => (string) $ctx['document_id'],
            'theme_id' => (string) $ctx['theme_id'],
        ],
    );
    return ['node' => mo_present_node($row)];
}

/**
 * Resolve a `placement` request into the concrete `ordering` string the
 * new sibling should carry. Reads the surrounding siblings under the
 * lock (the caller owns the lock; this function is lock-free internally).
 *
 * @param array<string, mixed> $ctx
 * @return string|WP_Error
 */
function mo_compute_placement(array $ctx): string|WP_Error
{
    $scope = [
        'document_type' => (string) $ctx['document_type'],
        'document_id' => (string) $ctx['document_id'],
        'parent_type' => (string) $ctx['parent_type'],
        'parent_id' => (string) $ctx['parent_id'],
    ];
    $placement = (string) $ctx['placement'];
    if ($placement === 'at_end') {
        return mo_ordering_at_end(
            $scope['document_type'],
            $scope['document_id'],
            $scope['parent_type'],
            $scope['parent_id'],
        );
    }
    if ($placement === 'at_start') {
        $first = mo_sibling_ordering($scope, direction: 'ASC');
        return mo_ordering_between(before: null, after: $first);
    }
    return mo_compute_placement_relative($scope, $placement, (string) $ctx['anchor_id']);
}

/**
 * Handle `placement=before|after` — needs the anchor node's ordering
 * plus its neighbor on the relevant side. Split out so mo_compute_placement
 * stays simple.
 *
 * @param array{document_type:string, document_id:string, parent_type:string, parent_id:string} $scope
 * @return string|WP_Error
 */
function mo_compute_placement_relative(array $scope, string $placement, string $anchor_id): string|WP_Error
{
    if ($anchor_id === '') {
        return mo_error('mo_invalid_input', __('placement=before/after requires anchor_id.', domain: 'novamira-pro'));
    }
    $anchor_ord = mo_node_ordering($scope['document_type'], $scope['document_id'], $anchor_id);
    if ($anchor_ord === null) {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: anchor node UUID. */
            __('Anchor node %s not found in this document.', domain: 'novamira-pro'),
            $anchor_id,
        ));
    }
    if ($placement === 'before') {
        $prev = mo_neighbor_ordering($scope, $anchor_ord, direction: 'DESC');
        return mo_ordering_between($prev, $anchor_ord);
    }
    $next = mo_neighbor_ordering($scope, $anchor_ord, direction: 'ASC');
    return mo_ordering_between($anchor_ord, $next);
}

/**
 * The earliest (ASC) or latest (DESC) sibling ordering under a parent.
 *
 * @param array{document_type:string, document_id:string, parent_type:string, parent_id:string} $scope
 */
function mo_sibling_ordering(array $scope, string $direction): ?string
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('nodes');
    $dir = $direction === 'DESC' ? 'DESC' : 'ASC';
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $wpdb->prepare(
        "SELECT `ordering` FROM `{$table}` WHERE `documentType` = %s AND `documentID` = %s AND `parentType` = %s AND `parentID` = %s AND `status` = 'publish' ORDER BY BINARY `ordering` {$dir} LIMIT 1",
        $scope['document_type'],
        $scope['document_id'],
        $scope['parent_type'],
        $scope['parent_id'],
    );
    /** @var mixed $ord */
    // @mago-expect analysis:possibly-invalid-argument
    $ord = $wpdb->get_var($prepared);
    return is_string($ord) && $ord !== '' ? $ord : null;
}

/**
 * The neighbor ordering immediately above (DESC) or below (ASC) the
 * given anchor among siblings of the same parent. Returns null when the
 * anchor is already the first or last.
 *
 * @param array{document_type:string, document_id:string, parent_type:string, parent_id:string} $scope
 */
function mo_neighbor_ordering(array $scope, string $anchor_ord, string $direction): ?string
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('nodes');
    $cmp = $direction === 'DESC' ? '<' : '>';
    $dir = $direction === 'DESC' ? 'DESC' : 'ASC';
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $wpdb->prepare(
        "SELECT `ordering` FROM `{$table}` WHERE `documentType` = %s AND `documentID` = %s AND `parentType` = %s AND `parentID` = %s AND `status` = 'publish' AND BINARY `ordering` {$cmp} %s ORDER BY BINARY `ordering` {$dir} LIMIT 1",
        $scope['document_type'],
        $scope['document_id'],
        $scope['parent_type'],
        $scope['parent_id'],
        $anchor_ord,
    );
    /** @var mixed $ord */
    // @mago-expect analysis:possibly-invalid-argument
    $ord = $wpdb->get_var($prepared);
    return is_string($ord) && $ord !== '' ? $ord : null;
}

/**
 * Resolve the `themeID` for a document. Reads the document table that
 * owns the document_type (templates / masters / styleguides / components)
 * and returns the themeID column. Returns null when the document does
 * not exist or has been soft-deleted.
 */
function mo_document_theme_id(string $document_type, string $document_id): ?string
{
    $entity_map = [
        'template' => 'templates',
        'master' => 'masters',
        'styleGuide' => 'styleguides',
        'component' => 'components',
    ];
    $entity = $entity_map[$document_type] ?? null;
    if ($entity === null) {
        return null;
    }
    $row = mo_load($entity, $document_id);
    if ($row === null || ($row['status'] ?? '') !== 'publish') {
        return null;
    }
    return mo_str($row, key: 'themeID');
}

/**
 * Fetch a node row scoped by document so that we never accidentally
 * touch a node from a different document with the same ID. Returns
 * null on miss (including soft-deleted rows).
 *
 * @return array<string, mixed>|null
 */
function mo_load_node(string $document_type, string $document_id, string $node_id): ?array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('nodes');
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE `documentType` = %s AND `documentID` = %s AND `ID` = %s LIMIT 1",
        $document_type,
        $document_id,
        $node_id,
    );
    /** @var mixed $row */
    // @mago-expect analysis:possibly-invalid-argument
    $row = $wpdb->get_row($prepared, 'ARRAY_A');
    if (!is_array($row)) {
        return null;
    }
    /** @var array<string, mixed> $row */
    if (($row['status'] ?? '') !== 'publish') {
        return null;
    }
    return $row;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_edit_element(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    /** @var array<string, mixed>|null $data */
    $data = is_array($input['data'] ?? null) ? $input['data'] : null;
    // Default to merge (partial-update, the `edit` verb's contract): only an
    // explicit `replace` overwrites the whole blob.
    $mode = is_string($input['mode'] ?? null) && $input['mode'] === 'replace' ? 'replace' : 'merge';
    if ($id === '' || $data === null) {
        return mo_error('mo_invalid_input', __('id and data are required.', domain: 'novamira-pro'));
    }
    $row = mo_find_node_any_document($id);
    if ($row === null) {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: node UUID. */
            __('Mosaic node %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    $document_id = mo_str($row, key: 'documentID');
    // Normalization (style / attributes shortcuts, class inputs + blob guards)
    // runs INSIDE the lock, against the merged base, so the shortcuts resolve
    // against the node's real current values rather than the partial payload —
    // see mo_apply_edit_locked.
    $ctx = [
        'document_type' => mo_str($row, key: 'documentType'),
        'document_id' => $document_id,
        'id' => $id,
        'data' => $data,
        'input' => $input,
        'mode' => $mode,
    ];
    return mo_with_lock("nodes:{$document_id}", static fn(): array|WP_Error => mo_apply_edit_locked($ctx));
}

/**
 * Resolve the final node `data` blob for an edit. Merge mode overlays the
 * supplied keys onto the stored blob FIRST, so the style / attributes /
 * class shortcuts (and their empty-clears) resolve against the element's
 * real current values rather than the partial payload; replace starts from a
 * blank base. Normalization then rebuilds the shortcut keys.
 *
 * @param  array<string, mixed> $row
 * @param  array<string, mixed> $raw_data
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mo_resolve_edit_blob(array $row, array $raw_data, array $input, string $mode): array|WP_Error
{
    /** @var array<string, mixed> $base */
    $base = $mode === 'merge' ? array_merge(mo_decode_data($row['data'] ?? null), $raw_data) : $raw_data;
    $theme_id = mo_str($row, key: 'themeID');
    return mo_normalize_element_data(
        $base,
        $input,
        $theme_id !== '' ? $theme_id : null,
        mo_str($row, key: 'type'),
        $raw_data,
    );
}

/**
 * @param array<string, mixed> $ctx
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_edit_locked(array $ctx): array|WP_Error
{
    $document_type = (string) $ctx['document_type'];
    $document_id = (string) $ctx['document_id'];
    $id = (string) $ctx['id'];
    $row = mo_load_node($document_type, $document_id, $id);
    if ($row === null) {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: node UUID. */
            __('Mosaic node %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    /** @var array<string, mixed> $raw_data */
    $raw_data = is_array($ctx['data'] ?? null) ? $ctx['data'] : [];
    /** @var array<string, mixed> $input */
    $input = is_array($ctx['input'] ?? null) ? $ctx['input'] : [];
    $merged = mo_resolve_edit_blob($row, $raw_data, $input, (string) $ctx['mode']);
    if ($merged instanceof WP_Error) {
        return $merged;
    }
    // Round 8 H6 / H3: pin the UPDATE to the loaded revision + publish
    // status. MySQL GET_LOCK serializes Mosaic-issued writes, but external
    // SQL writers (e.g. Mosaic editor's REST save endpoint, which runs on
    // a different connection) bypass it. Without this guard the UPDATE
    // would silently overwrite a peer's mutation, mutate a tombstoned
    // row, or commit against a row that was hard-deleted mid-flight.
    $old_revision = mo_str($row, key: 'revision');
    global $wpdb;
    /** @var \wpdb $wpdb */
    $ok = $wpdb->update(
        mo_table('nodes'),
        ['data' => mo_encode_object_blob($merged), 'revision' => mo_uuid(), 'version' => mo_version()],
        [
            'ID' => $id,
            'documentType' => $document_type,
            'documentID' => $document_id,
            'status' => 'publish',
            'revision' => $old_revision,
        ],
        ['%s', '%s', '%s'],
        ['%s', '%s', '%s', '%s', '%s'],
    );
    if ($ok === false) {
        return mo_error('mo_write_failed', __('Could not update the Mosaic node.', domain: 'novamira-pro'));
    }
    if ($ok === 0) {
        return mo_concurrent_modification_error('node', $id);
    }
    $fresh = mo_load_node($document_type, $document_id, $id);
    if ($fresh === null) {
        return mo_concurrent_modification_error('node', $id);
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-edit-element',
        operation: 'edit',
        entity: 'node',
        entity_id: $id,
        context: [
            'document_type' => $document_type,
            'document_id' => $document_id,
            'theme_id' => mo_str($fresh, key: 'themeID'),
        ],
    );
    return ['node' => mo_present_node($fresh)];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_delete_element(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $row = mo_find_node_any_document($id);
    if ($row === null) {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: node UUID. */
            __('Mosaic node %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    $document_type = mo_str($row, key: 'documentType');
    $document_id = mo_str($row, key: 'documentID');
    return mo_with_lock("nodes:{$document_id}", static fn(): array|WP_Error => mo_delete_subtree_locked(
        $document_type,
        $document_id,
        $id,
    ));
}

/**
 * Soft-delete a node + its descendants. Walks the parent→children
 * relationships within the document via a BFS so we do not need a
 * recursive SQL CTE (MariaDB without CTE support still ships in some
 * users' stacks).
 *
 * @return array<string, int>|WP_Error
 */
function mo_delete_subtree_locked(string $document_type, string $document_id, string $root_id): array|WP_Error
{
    $to_delete = [$root_id];
    $queue = [$root_id];
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('nodes');
    while ($queue !== []) {
        $batch = array_splice($queue, offset: 0);
        $placeholders = implode(', ', array_fill(start_index: 0, count: count($batch), value: '%s'));
        // @mago-expect analysis:possibly-invalid-argument
        $children_sql = (string) $wpdb->prepare(
            "SELECT `ID` FROM `{$table}` WHERE `documentType` = %s AND `documentID` = %s AND `parentType` = 'node' AND `parentID` IN ({$placeholders}) AND `status` = 'publish'",
            [...[$document_type, $document_id], ...$batch],
        );
        /** @var mixed $children */
        /** @var mixed $raw_children */
        $raw_children = $wpdb->get_col($children_sql);
        $children = is_array($raw_children) ? $raw_children : [];
        if ($children === []) {
            continue;
        }
        // @mago-expect analysis:mixed-assignment
        foreach ($children as $cid) {
            $cid_str = (string) $cid;
            $to_delete[] = $cid_str;
            $queue[] = $cid_str;
        }
    }
    $placeholders = implode(', ', array_fill(start_index: 0, count: count($to_delete), value: '%s'));
    // @mago-expect analysis:possibly-invalid-argument
    $update_sql = (string) $wpdb->prepare(
        "UPDATE `{$table}` SET `status` = 'delete', `revision` = %s WHERE `documentType` = %s AND `documentID` = %s AND `ID` IN ({$placeholders}) AND `status` = 'publish'",
        [mo_uuid(), $document_type, $document_id, ...$to_delete],
    );
    /** @var mixed $updated */
    $updated = $wpdb->query($update_sql);
    $count = is_int($updated) ? $updated : 0;
    // Round 6 F9: sweep dangling orphans whose parent row is gone OR
    // soft-deleted. The BFS above stops at any node missing from the live
    // chain — if an external actor hard-deleted a mid-chain row between
    // mosaic-delete-element's read and write, the subtree below the hole
    // would survive as `status=publish` orphans pointing at nothing.
    $count += mo_sweep_document_orphans($document_type, $document_id);
    if ($count > 0) {
        mo_emit_changed(
            ability: 'novamira/mosaic-delete-element',
            operation: 'delete',
            entity: 'node',
            entity_id: $root_id,
            context: [
                'document_type' => $document_type,
                'document_id' => $document_id,
            ],
        );
    }
    return ['deleted_count' => $count];
}

/**
 * Flip any `status=publish` node in this document to `status=delete` when
 * its parent is no longer reachable: either the parent row is gone from
 * `wp_mosaic_nodes` entirely (external hard delete), or the parent row
 * survives but is itself `status=delete`. Bounded iteration mirrors the
 * cascade pattern in `mo_facade_clean_orphans`, but scoped to one document
 * so a delete-element call does not touch the rest of the theme.
 *
 * Returns the count of publish-rows the sweep flipped.
 */
function mo_sweep_document_orphans(string $document_type, string $document_id): int
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('nodes');
    // The `parentID = ID OR parentID NOT IN (...)` form catches both
    // missing-parent orphans AND self-referencing rows (round 7 G1).
    // A row where `ID == parentID` is its own parent, so the original
    // NOT-IN-live-set check would never exclude it (the row's own ID is
    // in the live-set) and the sweep would leave the self-ref permanently
    // orphan. Mosaic never produces this shape itself, but the sweep was
    // added to defend against state Mosaic did NOT produce — so it has
    // to cover this case too.
    // @mago-expect analysis:possibly-invalid-argument
    $sql = (string) $wpdb->prepare(
        "UPDATE `{$table}` SET `status` = 'delete', `revision` = %s WHERE `documentType` = %s AND `documentID` = %s AND `parentType` = 'node' AND `status` = 'publish' AND (`parentID` = `ID` OR `parentID` NOT IN (SELECT * FROM (SELECT `ID` FROM `{$table}` WHERE `documentType` = %s AND `documentID` = %s AND `status` = 'publish') AS live))",
        mo_uuid(),
        $document_type,
        $document_id,
        $document_type,
        $document_id,
    );
    $swept = 0;
    // Re-run until quiescent (cascade) but cap iterations as defense-in-depth.
    for ($i = 0; $i < 256; $i++) {
        /** @var mixed $affected */
        $affected = $wpdb->query($sql);
        if (!is_int($affected) || $affected === 0) {
            break;
        }
        $swept += $affected;
    }
    return $swept;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_move_element(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $id = mo_norm_uuid($input['id'] ?? null);
    if ($id === '') {
        return mo_error('mo_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $row = mo_find_node_any_document($id);
    if ($row === null) {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: node UUID. */
            __('Mosaic node %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    $document_type = mo_str($row, key: 'documentType');
    $document_id = mo_str($row, key: 'documentID');
    $input_parent = mo_norm_uuid($input['parent_id'] ?? null);
    $new_parent_id = $input_parent !== '' ? $input_parent : mo_str($row, key: 'parentID');
    $placement = is_string($input['placement'] ?? null) ? $input['placement'] : 'at_end';
    $anchor_id = mo_norm_uuid($input['anchor_id'] ?? null);
    // Auto-route "move to root" to the canvas wrapper, mirroring add-element
    // so the agent's intent matches Mosaic's render path. On non-template
    // documents (master / styleGuide / component) the resolver returns an
    // mo_invalid_input WP_Error pointing the agent at a real inner node —
    // see round 8 H2.
    $new_parent_type = 'node';
    if ($new_parent_id === $document_id) {
        $route = mo_resolve_root_parent($document_type, $document_id);
        if ($route instanceof WP_Error) {
            return $route;
        }
        $new_parent_id = $route['parent_id'];
        $new_parent_type = $route['parent_type'];
    }
    $ctx = [
        'id' => $id,
        'document_type' => $document_type,
        'document_id' => $document_id,
        'parent_type' => $new_parent_type,
        'parent_id' => $new_parent_id,
        'placement' => $placement,
        'anchor_id' => $anchor_id,
    ];
    return mo_with_lock("nodes:{$document_id}", static fn(): array|WP_Error => mo_apply_move_locked($ctx));
}

/**
 * @param array<string, mixed> $ctx
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_move_locked(array $ctx): array|WP_Error
{
    $document_type = (string) $ctx['document_type'];
    $document_id = (string) $ctx['document_id'];
    $id = (string) $ctx['id'];
    $new_parent_type = (string) $ctx['parent_type'];
    $new_parent_id = (string) $ctx['parent_id'];
    $guard = mo_validate_move_target($ctx);
    if ($guard instanceof WP_Error) {
        return $guard;
    }
    $ordering = mo_compute_placement($ctx);
    if ($ordering instanceof WP_Error) {
        return $ordering;
    }
    // Round 8 H6 / H3: pin the UPDATE to the node's current revision +
    // publish status. Round-8 H6 found that mo_validate_move_target
    // running before the UPDATE meant an external SQL writer could
    // delete the new-parent target between the validate and the write,
    // and we would commit `parentID=<dead-uuid>` silently.
    $row = mo_load_node($document_type, $document_id, $id);
    if ($row === null) {
        return mo_concurrent_modification_error('node', $id);
    }
    $old_revision = mo_str($row, key: 'revision');
    global $wpdb;
    /** @var \wpdb $wpdb */
    $ok = $wpdb->update(
        mo_table('nodes'),
        [
            'parentType' => $new_parent_type,
            'parentID' => $new_parent_id,
            'ordering' => $ordering,
            'revision' => mo_uuid(),
        ],
        [
            'ID' => $id,
            'documentType' => $document_type,
            'documentID' => $document_id,
            'status' => 'publish',
            'revision' => $old_revision,
        ],
        ['%s', '%s', '%s', '%s'],
        ['%s', '%s', '%s', '%s', '%s'],
    );
    if ($ok === false) {
        return mo_error('mo_write_failed', __('Could not move the Mosaic node.', domain: 'novamira-pro'));
    }
    if ($ok === 0) {
        return mo_concurrent_modification_error('node', $id);
    }
    $fresh = mo_load_node($document_type, $document_id, $id);
    if ($fresh === null) {
        return mo_concurrent_modification_error('node', $id);
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-move-element',
        operation: 'move',
        entity: 'node',
        entity_id: $id,
        context: [
            'document_type' => $document_type,
            'document_id' => $document_id,
            'theme_id' => mo_str($fresh, key: 'themeID'),
        ],
    );
    return ['node' => mo_present_node($fresh)];
}

/**
 * Test whether `$descendant_id` is somewhere under `$ancestor_id` in
 * the same document — used by move-element to prevent cycles.
 */
function mo_is_descendant(string $document_type, string $document_id, string $descendant_id, string $ancestor_id): bool
{
    $current = $descendant_id;
    $guard = 0;
    while ($guard < 500) {
        $row = mo_load_node($document_type, $document_id, $current);
        if ($row === null) {
            return false;
        }
        $parent_id = mo_str($row, key: 'parentID');
        $parent_type = mo_str($row, key: 'parentType');
        if ($parent_type !== 'node') {
            return false;
        }
        if ($parent_id === $ancestor_id) {
            return true;
        }
        $current = $parent_id;
        $guard++;
    }
    return false;
}

/**
 * Verify the move target exists in this document and that the new
 * parent is neither the node itself nor one of its descendants
 * (Mosaic would render an infinite-recursion tree otherwise).
 *
 * @param array<string, mixed> $ctx
 */
function mo_validate_move_target(array $ctx): ?WP_Error
{
    $document_type = (string) $ctx['document_type'];
    $document_id = (string) $ctx['document_id'];
    $id = (string) $ctx['id'];
    $new_parent_type = (string) $ctx['parent_type'];
    $new_parent_id = (string) $ctx['parent_id'];
    $node = mo_load_node($document_type, $document_id, $id);
    if ($node === null) {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: node UUID. */
            __('Mosaic node %s not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    if ($new_parent_type !== 'node') {
        return null;
    }
    $parent_exists = mo_node_ordering($document_type, $document_id, $new_parent_id);
    if ($parent_exists === null) {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: candidate new-parent node UUID. */
            __('New parent %s not found in this document.', domain: 'novamira-pro'),
            $new_parent_id,
        ));
    }
    if ($new_parent_id === $id || mo_is_descendant($document_type, $document_id, $new_parent_id, $id)) {
        return mo_error('mo_invalid_input', __(
            'Cannot move a node into itself or one of its descendants.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * Find a node by id alone — used by edit/delete/move where the caller
 * only supplies the node id and we have to discover its document.
 * Returns the raw row (any status) so the caller can decide.
 *
 * @return array<string, mixed>|null
 */
function mo_find_node_any_document(string $id): ?array
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('nodes');
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $wpdb->prepare("SELECT * FROM `{$table}` WHERE `ID` = %s LIMIT 1", $id);
    /** @var mixed $row */
    // @mago-expect analysis:possibly-invalid-argument
    $row = $wpdb->get_row($prepared, 'ARRAY_A');
    if (!is_array($row)) {
        return null;
    }
    /** @var array<string, mixed> $row */
    return $row;
}
