<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Component-instance property overrides.
 *
 * Mosaic lets an editor override a single property (text, style, any
 * `data` field) on ONE instance of a shared component without touching
 * the shared component definition. Verified against the Mosaic Pro 1.0.5
 * source (`ComponentInstanceElementMResource`, `NodeMResourceManagerAbstract
 * ::createNodeOverride`, `NodeMResourceDataAbstract`, `OverrideSubData`):
 *
 *  - The visible instance is a node whose `type` is
 *    `component-instance/<component UUID>`.
 *  - A per-instance override is a SEPARATE `wp_mosaic_nodes` row, living in
 *    the HOST document (the instance's own document, not the component's):
 *    `parentType = 'override'`, `parentID = <instance node UUID>`,
 *    `type = <original node's type>`,
 *    `data = { override: { originalID: <original node UUID> }, ...overridden properties }`.
 *  - The overridden properties are SIBLINGS of `data.override`, not nested
 *    inside it. Properties the caller does not set keep inheriting from the
 *    shared component's node live.
 *  - Overrides for an ENTIRE instance subtree — including nodes reached
 *    through a nested component-instance several components deep — are all
 *    flat children of the SAME outermost instance node (Mosaic's
 *    `keepOverrideNodesInSync` always calls `createNodeOverride` against the
 *    outer `ComponentInstanceElementMResource`, never a nested one).
 *
 * No existing ability could create/edit this row safely: `get-element-tree`
 * only walks `parentType=node`, so override rows are invisible to it;
 * `add-element` forces non-root inserts to `parentType=node`; `edit-element`
 * can only touch a row whose UUID is already known some other way. Before
 * these abilities, the only way to set an override was raw SQL.
 */

wp_register_ability('novamira/mosaic-edit-component-instance-override', [
    'label' => __('Edit Mosaic Component-Instance Override', domain: 'novamira-pro'),
    'description' => __(
        'Overrides one or more properties of a single node ON ONE INSTANCE of a shared Mosaic component, without touching the shared component definition or any other instance. `instance_id` is the visible `component-instance/<uuid>` node in the host document (template / master / styleGuide / another component); `original_node_id` is the UUID of the node INSIDE that shared component (or a component nested inside it) whose property you want to change locally. Pass the new values via `data` (Mosaic-native shape) and/or the `style` / `attributes` / `css_classes` / `classes` / `detach_classes` / `variant` inputs — same shapes and semantics as mosaic-edit-element (`variant` is checked against the overridden node\'s element type). The call creates the underlying override row the first time it targets a given `original_node_id`, and merges onto it (preserving properties you do not send) on every call after that — safe to call repeatedly. Properties you never override keep inheriting live from the shared component.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'instance_id' => [
                'type' => 'string',
                'description' => 'The `component-instance/<component UUID>` node UUID in the host document — the visible instance, not the shared component itself. Get one from mosaic-get-element-tree.',
            ],
            'original_node_id' => [
                'type' => 'string',
                'description' => 'UUID of the node inside the shared component (or a component nested inside it, at any depth) whose property this override targets. Get one from mosaic-get-element-tree on the COMPONENT document (document_type=component, document_id=<the instance\'s component UUID>).',
            ],
            'data' => [
                'type' => 'object',
                'description' => 'Overridden property values (Mosaic-native shape, same as mosaic-edit-element\'s `data`). Merged onto the override\'s existing properties — keys you do not send are preserved. The reserved `override` key is always derived server-side; if present here it is ignored.',
            ],
            'style' => mo_style_input_property_schema(),
            'attributes' => mo_attributes_input_property_schema(),
            'css_classes' => mo_css_classes_input_property_schema(),
            'classes' => mo_classes_input_property_schema(),
            'detach_classes' => mo_detach_classes_input_property_schema(),
            'variant' => mo_variant_input_property_schema(),
            'expected_revision' => [
                'type' => 'string',
                'description' => 'Optional optimistic-concurrency guard. When editing an override that already exists, pass the `revision` you last read — a mismatch returns mo_concurrent_modification instead of silently overwriting a concurrent change. Omit when you expect this call to CREATE the override for the first time (there is no prior revision to pin); supplying it on a first-time create returns mo_invalid_input.',
            ],
        ],
        'required' => ['instance_id', 'original_node_id', 'data'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'override' => ['type' => 'object'],
            'created' => ['type' => 'boolean'],
        ],
        'required' => ['override', 'created'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_edit_component_instance_override',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover original_node_id by calling mosaic-get-element-tree with document_type=component against the instance\'s component UUID (parse it out of the instance node\'s `type`, after the `component-instance/` prefix). The override row this call writes does NOT show up in mosaic-get-element-tree on the HOST document — that ability only walks parentType=node. Use mosaic-clear-component-instance-override to revert to the shared value.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// clear-component-instance-override
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-clear-component-instance-override', [
    'label' => __('Clear Mosaic Component-Instance Override', domain: 'novamira-pro'),
    'description' => __(
        'Removes a component-instance property override entirely — the instance reverts to fully inheriting `original_node_id`\'s value from the shared component. Does NOT touch the shared component definition or any other override on the same instance. Safe to call when no override exists (no-op, `cleared: false`, not an error).',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'instance_id' => [
                'type' => 'string',
                'description' => 'The `component-instance/<component UUID>` node UUID in the host document.',
            ],
            'original_node_id' => [
                'type' => 'string',
                'description' => 'UUID of the node inside the shared component whose override should be removed (same id you passed to mosaic-edit-component-instance-override).',
            ],
        ],
        'required' => ['instance_id', 'original_node_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'instance_id' => ['type' => 'string'],
            'original_node_id' => ['type' => 'string'],
            'cleared' => ['type' => 'boolean'],
        ],
        'required' => ['instance_id', 'original_node_id', 'cleared'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_clear_component_instance_override',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Soft-delete of the single override row for this (instance_id, original_node_id) pair. Other overrides on the same instance are untouched.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// Shared resolution helpers
// ---------------------------------------------------------------------------

/**
 * Hard cap on the number of component documents walked while resolving
 * `original_node_id` through nested component-instances. Defense-in-depth
 * against a cyclic or pathologically large component graph — real-world
 * component nesting is a handful of levels deep.
 */
const MO_COMPONENT_OVERRIDE_WALK_BUDGET = 64;

/**
 * Extract the component UUID out of a `component-instance/<uuid>` node
 * type. Returns null for any other type (including a bare
 * `component-instance` with no flag, which Mosaic's own factory never
 * produces — see ComponentInstanceElementMResource::getType()).
 */
function mo_component_instance_component_id(string $type): ?string
{
    $prefix = 'component-instance/';
    if (substr($type, offset: 0, length: strlen($prefix)) !== $prefix) {
        return null;
    }
    $id = substr($type, strlen($prefix));
    return $id !== '' ? mo_norm_uuid($id) : null;
}

/**
 * Resolve + validate the host component-instance context shared by both
 * override abilities: the instance node exists and is published, its type
 * really is `component-instance/<uuid>`, and the referenced component
 * exists, is published, and belongs to the SAME theme as the instance
 * (a mismatched theme can only mean corrupted or hand-crafted data).
 *
 * @return array{instance:array<string, mixed>, document_type:string, document_id:string, theme_id:string, component_id:string}|WP_Error
 */
function mo_resolve_component_instance_context(string $instance_id): array|WP_Error
{
    $instance = mo_find_node_any_document($instance_id);
    if ($instance === null || ($instance['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: node UUID. */
            __('Mosaic node %s not found.', domain: 'novamira-pro'),
            $instance_id,
        ));
    }
    $component_id = mo_component_instance_component_id(mo_str($instance, key: 'type'));
    if ($component_id === null) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: 1: node UUID, 2: the node's actual type. */
            __(
                'Node %1$s is not a component-instance node (type is `%2$s`, expected `component-instance/<component UUID>`).',
                domain: 'novamira-pro',
            ),
            $instance_id,
            mo_str($instance, key: 'type'),
        ));
    }
    $component = mo_load('components', $component_id);
    if ($component === null || ($component['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: 1: component UUID, 2: instance node UUID. */
            __('Mosaic component %1$s (referenced by instance %2$s) not found.', domain: 'novamira-pro'),
            $component_id,
            $instance_id,
        ));
    }
    $instance_theme_id = mo_str($instance, key: 'themeID');
    $component_theme_id = mo_str($component, key: 'themeID');
    if ($component_theme_id === '' || $component_theme_id !== $instance_theme_id) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: 1: component UUID, 2: instance node UUID. */
            __('Component %1$s belongs to a different theme than instance %2$s.', domain: 'novamira-pro'),
            $component_id,
            $instance_id,
        ));
    }
    return [
        'instance' => $instance,
        'document_type' => mo_str($instance, key: 'documentType'),
        'document_id' => mo_str($instance, key: 'documentID'),
        'theme_id' => $instance_theme_id,
        'component_id' => $component_id,
    ];
}

/**
 * Find `original_node_id`'s row inside `component_id`'s own element tree,
 * or inside a component reached by following nested
 * `component-instance/<uuid>` nodes any number of levels deep. Mosaic
 * flattens every override for an instance subtree onto the SAME outermost
 * instance node regardless of how deep the overridden node actually lives
 * (see file-level docblock), so a legitimate `original_node_id` is not
 * necessarily a direct child of `component_id`'s document.
 *
 * Two things this walk must NOT do, both confirmed against the 1.0.5
 * source:
 *  - Treat a `parentType=override` row in the scanned document as a valid
 *    descendant. `ComponentInstanceElementMResource::keepOverrideNodesInSync`
 *    walks a component's DEFINITIONAL tree via `createNodesChildrenGenerator`
 *    (the `node/node` tree connector only) when flattening overrides for a
 *    new instance — it never follows `override/node`. A component CAN host
 *    its own nested `component-instance` (and therefore its own override
 *    rows, scoped to that component's document) when a component author
 *    embeds one component inside another, but those shadow rows are not
 *    part of the shape Mosaic itself considers overridable structure, so
 *    matching one here would let an unrelated instance's override target a
 *    row that Mosaic's own instantiation logic would never expose.
 *  - Trust a nested component reference without re-validating it the same
 *    way {@see mo_resolve_component_instance_context()} validates the
 *    outer one (exists, published, same theme). A corrupted or hand-
 *    crafted `component-instance/<uuid>` type value pointing at a
 *    foreign-theme component must not expand the trust boundary — that
 *    component's nodes are skipped (not recursed into), not treated as a
 *    hard error, since one bad nested reference should not block finding
 *    a valid `original_node_id` elsewhere in the tree.
 *
 * `mo_select()` defaults every filter to `status=publish` (see
 * `mo_build_where()`), so this already only walks live rows.
 *
 * `$visited` guards a cyclic component graph; `$budget` bounds the total
 * number of component documents walked.
 *
 * @param  array<string, true> $visited
 * @return array<string, mixed>|null
 */
function mo_find_component_descendant_node(
    string $component_id,
    string $original_node_id,
    string $theme_id,
    array &$visited,
    int &$budget,
): ?array {
    if (isset($visited[$component_id]) || $budget <= 0) {
        return null;
    }
    $visited[$component_id] = true;
    $budget--;
    $rows = mo_select('nodes', ['documentType' => 'component', 'documentID' => $component_id], limit: 5000);
    /** @var list<string> $nested_component_ids */
    $nested_component_ids = [];
    foreach ($rows as $row) {
        // Override shadow rows are never part of the definitional tree —
        // see the docblock above. Skip both as a match candidate and as a
        // nested-component recursion source.
        if (mo_str($row, key: 'parentType') === 'override') {
            continue;
        }
        if (mo_norm_uuid($row['ID'] ?? null) === $original_node_id) {
            return $row;
        }
        $nested_id = mo_component_instance_component_id(mo_str($row, key: 'type'));
        if ($nested_id !== null) {
            $nested_component_ids[] = $nested_id;
        }
    }
    foreach ($nested_component_ids as $nested_id) {
        if (!mo_component_exists_in_theme($nested_id, $theme_id)) {
            continue;
        }
        $found = mo_find_component_descendant_node($nested_id, $original_node_id, $theme_id, $visited, $budget);
        if ($found !== null) {
            return $found;
        }
    }
    return null;
}

/**
 * Guard for {@see mo_find_component_descendant_node()}'s recursion into a
 * nested component reference: true only when `$component_id` exists, is
 * published, AND belongs to `$theme_id` — the same three checks
 * {@see mo_resolve_component_instance_context()} applies to the outer
 * component. Without this, a hand-crafted nested `component-instance/<uuid>`
 * type value could smuggle a foreign-theme component's nodes into the set
 * of valid `original_node_id` targets.
 */
function mo_component_exists_in_theme(string $component_id, string $theme_id): bool
{
    $component = mo_load('components', $component_id);
    return (
        $component !== null
        && ($component['status'] ?? '') === 'publish'
        && mo_str($component, key: 'themeID') === $theme_id
    );
}

/**
 * Locate the (at most one) logical override row for the
 * `(instance_id, original_node_id)` pair — a PUBLISHED `parentType=override`,
 * `parentID=$instance_id` sibling row whose `data.override.originalID`
 * matches (`mo_select()` defaults every filter to `status=publish`, see
 * `mo_build_where()`, so a soft-deleted override is invisible here — a
 * fresh `edit` call after `clear` correctly re-creates rather than
 * resurrecting the deleted row). Returns null when none exists, the single
 * matching row, or a typed WP_Error when MORE than one row claims the same
 * originalID. Mosaic's own editor never produces that shape
 * (`createNodeOverride` is always guarded by the same in-memory sync
 * pass), but direct SQL against `wp_mosaic_nodes` could — silently picking
 * one would risk editing or clearing the wrong copy, so this surfaces the
 * corruption instead.
 *
 * Paginates through every `parentType=override` row under this instance
 * rather than capping at a fixed limit: the query is already exact-match
 * filtered to ONE instance's overrides, so in practice a single page
 * covers it, but a hard cap here would let a pathological instance (an
 * agent overriding thousands of distinct nodes) silently miss an existing
 * override — indistinguishable from data corruption, which this function
 * exists specifically to catch.
 *
 * @return array<string, mixed>|null|WP_Error
 */
function mo_find_component_instance_override_row(
    string $document_type,
    string $document_id,
    string $instance_id,
    string $original_node_id,
): array|null|WP_Error {
    $filters = [
        'documentType' => $document_type,
        'documentID' => $document_id,
        'parentType' => 'override',
        'parentID' => $instance_id,
    ];
    $page_size = 1000;
    /** @var list<array<string, mixed>> $matches */
    $matches = [];
    for ($offset = 0;; $offset += $page_size) {
        $rows = mo_select('nodes', $filters, limit: $page_size, offset: $offset);
        foreach ($rows as $row) {
            $data = mo_decode_data($row['data'] ?? null);
            /** @var array<string, mixed> $override */
            $override = is_array($data['override'] ?? null) ? $data['override'] : [];
            $original_id = is_string($override['originalID'] ?? null) ? mo_norm_uuid($override['originalID']) : '';
            if ($original_id !== '' && $original_id === $original_node_id) {
                $matches[] = $row;
            }
        }
        if (count($rows) < $page_size) {
            break;
        }
    }
    if (count($matches) > 1) {
        return mo_error('mo_override_corrupt', sprintf(
            /* translators: 1: number of matching rows, 2: instance UUID, 3: original node UUID. */
            __(
                'Found %1$d override rows for instance %2$s + original node %3$s — expected at most one. This indicates data corruption (most likely from direct SQL bypassing this ability); resolve manually before retrying.',
                domain: 'novamira-pro',
            ),
            count($matches),
            $instance_id,
            $original_node_id,
        ));
    }
    return $matches[0] ?? null;
}

/**
 * mo_present_node() plus the two fields that only make sense on an
 * override row: `instance_id` (readable alias of `parent_id`, which is
 * only meaningful once you know `parent_type=override`) and
 * `original_node_id` (pulled out of `data.override.originalID`).
 *
 * @param  array<string, mixed> $row
 * @return array<string, mixed>
 */
function mo_present_component_instance_override(array $row, string $instance_id): array
{
    $node = mo_present_node($row);
    $node['instance_id'] = $instance_id;
    /** @var array<string, mixed> $data */
    $data = is_array($node['data'] ?? null) ? $node['data'] : [];
    /** @var array<string, mixed> $override */
    $override = is_array($data['override'] ?? null) ? $data['override'] : [];
    $node['original_node_id'] = is_string($override['originalID'] ?? null) ? $override['originalID'] : '';
    return $node;
}

// ---------------------------------------------------------------------------
// edit-component-instance-override callback
// ---------------------------------------------------------------------------

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_edit_component_instance_override(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $instance_id = mo_norm_uuid($input['instance_id'] ?? null);
    $original_node_id = mo_norm_uuid($input['original_node_id'] ?? null);
    /** @var array<string, mixed>|null $data */
    $data = is_array($input['data'] ?? null) ? $input['data'] : null;
    if ($instance_id === '' || $original_node_id === '' || $data === null) {
        return mo_error('mo_invalid_input', __(
            'instance_id, original_node_id, and data are required.',
            domain: 'novamira-pro',
        ));
    }
    $expected_revision = is_string($input['expected_revision'] ?? null) && $input['expected_revision'] !== ''
        ? $input['expected_revision']
        : null;

    $instance_ctx = mo_resolve_component_instance_context($instance_id);
    if ($instance_ctx instanceof WP_Error) {
        return $instance_ctx;
    }

    $visited = [];
    $budget = MO_COMPONENT_OVERRIDE_WALK_BUDGET;
    $original_row = mo_find_component_descendant_node(
        $instance_ctx['component_id'],
        $original_node_id,
        $instance_ctx['theme_id'],
        $visited,
        $budget,
    );
    if ($original_row === null) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: 1: original node UUID, 2: component UUID, 3: instance UUID. */
            __(
                'original_node_id %1$s does not belong to component %2$s (or any component nested inside it) referenced by instance %3$s.',
                domain: 'novamira-pro',
            ),
            $original_node_id,
            $instance_ctx['component_id'],
            $instance_id,
        ));
    }

    $lock_ctx = [
        'document_type' => $instance_ctx['document_type'],
        'document_id' => $instance_ctx['document_id'],
        'instance_id' => $instance_id,
        'original_node_id' => $original_node_id,
        'original_type' => mo_str($original_row, key: 'type'),
        'raw_data' => $data,
        'input' => $input,
        'expected_revision' => $expected_revision,
    ];
    return mo_with_lock(
        "nodes:{$instance_ctx['document_id']}",
        static fn(): array|WP_Error => mo_apply_component_instance_override_edit_locked($lock_ctx),
    );
}

/**
 * @param  array<string, mixed> $ctx
 * @return array<string, mixed>|WP_Error
 */
function mo_apply_component_instance_override_edit_locked(array $ctx): array|WP_Error
{
    $document_type = (string) $ctx['document_type'];
    $document_id = (string) $ctx['document_id'];
    $instance_id = (string) $ctx['instance_id'];
    $original_node_id = (string) $ctx['original_node_id'];

    // Re-verify the instance is still there under the lock — it may have
    // been deleted or detached between the pre-lock read and now.
    $instance = mo_load_node($document_type, $document_id, $instance_id);
    if ($instance === null) {
        return mo_concurrent_modification_error('node', $instance_id);
    }

    $existing = mo_find_component_instance_override_row($document_type, $document_id, $instance_id, $original_node_id);
    if ($existing instanceof WP_Error) {
        return $existing;
    }

    return $existing === null
        ? mo_create_component_instance_override_locked($ctx, $instance)
        : mo_update_component_instance_override_locked($ctx, $existing);
}

/**
 * Shared normalization step for both the create and update paths: merge
 * the caller's requested properties onto whatever base data already
 * exists (empty on create), run the same style/attributes shortcuts, class
 * inputs + depth/bytes guards mosaic-edit-element uses, then stamp the
 * server-derived `data.override` back on — last, so nothing the caller
 * sent can smuggle in a different `originalID` or drop the field.
 *
 * @param  array{base_data:array<string, mixed>, raw_data:array<string, mixed>, input:array<string, mixed>, theme_id:string, original_node_id:string, element_type:string, override_extra:array<string, mixed>} $parts
 *         `override_extra` carries any existing `hierarchy` / `isRemoved` to preserve (empty on create).
 * @return array<string, mixed>|WP_Error
 */
function mo_resolve_component_instance_override_data(array $parts): array|WP_Error
{
    $base_data = $parts['base_data'];
    $raw_data = $parts['raw_data'];
    unset($base_data['override'], $raw_data['override']);
    $merged = array_merge($base_data, $raw_data);
    $normalized = mo_normalize_element_data(
        $merged,
        $parts['input'],
        $parts['theme_id'] !== '' ? $parts['theme_id'] : null,
        $parts['element_type'],
        $raw_data,
    );
    if ($normalized instanceof WP_Error) {
        return $normalized;
    }
    $override_extra = $parts['override_extra'];
    $override_extra['originalID'] = $parts['original_node_id'];
    $normalized['override'] = $override_extra;
    return $normalized;
}

/**
 * @param  array<string, mixed> $ctx
 * @param  array<string, mixed> $instance
 * @return array<string, mixed>|WP_Error
 */
function mo_create_component_instance_override_locked(array $ctx, array $instance): array|WP_Error
{
    if ($ctx['expected_revision'] !== null) {
        return mo_error('mo_invalid_input', __(
            'expected_revision was supplied but no override exists yet for this original_node_id — omit expected_revision when creating a new override.',
            domain: 'novamira-pro',
        ));
    }
    $document_type = (string) $ctx['document_type'];
    $document_id = (string) $ctx['document_id'];
    $instance_id = (string) $ctx['instance_id'];
    $theme_id = mo_str($instance, key: 'themeID');

    /** @var array<string, mixed> $raw_data */
    $raw_data = is_array($ctx['raw_data'] ?? null) ? $ctx['raw_data'] : [];
    /** @var array<string, mixed> $input */
    $input = is_array($ctx['input'] ?? null) ? $ctx['input'] : [];
    $normalized = mo_resolve_component_instance_override_data([
        'base_data' => [],
        'raw_data' => $raw_data,
        'input' => $input,
        'theme_id' => $theme_id,
        'original_node_id' => (string) $ctx['original_node_id'],
        'element_type' => mo_str($ctx, key: 'original_type'),
        'override_extra' => [],
    ]);
    if ($normalized instanceof WP_Error) {
        return $normalized;
    }

    $ordering = mo_ordering_at_end($document_type, $document_id, parent_type: 'override', parent_id: $instance_id);
    if ($ordering instanceof WP_Error) {
        return $ordering;
    }
    $node_id = mo_uuid();
    global $wpdb;
    /** @var \wpdb $wpdb */
    $ok = $wpdb->insert(
        mo_table('nodes'),
        [
            'themeID' => $theme_id,
            'documentType' => $document_type,
            'documentID' => $document_id,
            'ID' => $node_id,
            'parentType' => 'override',
            'parentID' => $instance_id,
            'ordering' => $ordering,
            'status' => 'publish',
            'type' => (string) $ctx['original_type'],
            'data' => mo_encode_object_blob($normalized),
            'revision' => mo_uuid(),
            'version' => mo_version(),
        ],
        array_fill(start_index: 0, count: 12, value: '%s'),
    );
    if ($ok === false) {
        return mo_error('mo_write_failed', __(
            'Could not create the component-instance override.',
            domain: 'novamira-pro',
        ));
    }
    $row = mo_load_node($document_type, $document_id, $node_id);
    if ($row === null) {
        return mo_error('mo_not_found', __('Inserted override could not be re-read.', domain: 'novamira-pro'));
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-edit-component-instance-override',
        operation: 'create',
        entity: 'node',
        entity_id: $node_id,
        context: ['document_type' => $document_type, 'document_id' => $document_id, 'theme_id' => $theme_id],
    );
    return ['override' => mo_present_component_instance_override($row, $instance_id), 'created' => true];
}

/**
 * @param  array<string, mixed> $ctx
 * @param  array<string, mixed> $row
 * @return array<string, mixed>|WP_Error
 */
function mo_update_component_instance_override_locked(array $ctx, array $row): array|WP_Error
{
    $row_id = mo_str($row, key: 'ID');
    $old_revision = mo_str($row, key: 'revision');
    /** @var string|null $expected_revision */
    $expected_revision = $ctx['expected_revision'];
    if ($expected_revision !== null && $expected_revision !== $old_revision) {
        return mo_concurrent_modification_error('node', $row_id);
    }
    $document_type = (string) $ctx['document_type'];
    $document_id = (string) $ctx['document_id'];
    $theme_id = mo_str($row, key: 'themeID');

    /** @var array<string, mixed> $existing_data */
    $existing_data = mo_decode_data($row['data'] ?? null);
    /** @var array<string, mixed> $existing_override */
    $existing_override = is_array($existing_data['override'] ?? null) ? $existing_data['override'] : [];

    /** @var array<string, mixed> $raw_data */
    $raw_data = is_array($ctx['raw_data'] ?? null) ? $ctx['raw_data'] : [];
    /** @var array<string, mixed> $input */
    $input = is_array($ctx['input'] ?? null) ? $ctx['input'] : [];
    // Preserves any hierarchy/isRemoved fields Mosaic's own editor may have
    // set on this row (passed through as $existing_override); only
    // originalID is ours to guarantee.
    $normalized = mo_resolve_component_instance_override_data([
        'base_data' => $existing_data,
        'raw_data' => $raw_data,
        'input' => $input,
        'theme_id' => $theme_id,
        'original_node_id' => (string) $ctx['original_node_id'],
        'element_type' => mo_str($row, key: 'type'),
        'override_extra' => $existing_override,
    ]);
    if ($normalized instanceof WP_Error) {
        return $normalized;
    }

    return mo_persist_component_instance_override_update([
        'document_type' => $document_type,
        'document_id' => $document_id,
        'row_id' => $row_id,
        'old_revision' => $old_revision,
        'theme_id' => $theme_id,
        'instance_id' => (string) $ctx['instance_id'],
        'normalized' => $normalized,
    ]);
}

/**
 * Writes the revision-pinned UPDATE for an existing override row, then
 * re-reads + emits the change hook. Split out of
 * {@see mo_update_component_instance_override_locked()} purely to keep
 * that function's cyclomatic/halstead complexity within mago's limits.
 *
 * @param  array{document_type:string, document_id:string, row_id:string, old_revision:string, theme_id:string, instance_id:string, normalized:array<string, mixed>} $args
 * @return array<string, mixed>|WP_Error
 */
function mo_persist_component_instance_override_update(array $args): array|WP_Error
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $ok = $wpdb->update(
        mo_table('nodes'),
        [
            'data' => mo_encode_object_blob($args['normalized']),
            'revision' => mo_uuid(),
            'version' => mo_version(),
        ],
        [
            'ID' => $args['row_id'],
            'documentType' => $args['document_type'],
            'documentID' => $args['document_id'],
            'status' => 'publish',
            'revision' => $args['old_revision'],
        ],
        ['%s', '%s', '%s'],
        ['%s', '%s', '%s', '%s', '%s'],
    );
    if ($ok === false) {
        return mo_error('mo_write_failed', __(
            'Could not update the component-instance override.',
            domain: 'novamira-pro',
        ));
    }
    if ($ok === 0) {
        return mo_concurrent_modification_error('node', $args['row_id']);
    }
    $fresh = mo_load_node($args['document_type'], $args['document_id'], $args['row_id']);
    if ($fresh === null) {
        return mo_concurrent_modification_error('node', $args['row_id']);
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-edit-component-instance-override',
        operation: 'edit',
        entity: 'node',
        entity_id: $args['row_id'],
        context: [
            'document_type' => $args['document_type'],
            'document_id' => $args['document_id'],
            'theme_id' => $args['theme_id'],
        ],
    );
    return [
        'override' => mo_present_component_instance_override($fresh, $args['instance_id']),
        'created' => false,
    ];
}

// ---------------------------------------------------------------------------
// clear-component-instance-override callback
// ---------------------------------------------------------------------------

/**
 * @param  array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_clear_component_instance_override(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $instance_id = mo_norm_uuid($input['instance_id'] ?? null);
    $original_node_id = mo_norm_uuid($input['original_node_id'] ?? null);
    if ($instance_id === '' || $original_node_id === '') {
        return mo_error('mo_invalid_input', __(
            'instance_id and original_node_id are required.',
            domain: 'novamira-pro',
        ));
    }
    $instance = mo_find_node_any_document($instance_id);
    if ($instance === null || ($instance['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: node UUID. */
            __('Mosaic node %s not found.', domain: 'novamira-pro'),
            $instance_id,
        ));
    }
    if (mo_component_instance_component_id(mo_str($instance, key: 'type')) === null) {
        return mo_error('mo_invalid_input', sprintf(
            /* translators: %s: node UUID. */
            __('Node %s is not a component-instance node.', domain: 'novamira-pro'),
            $instance_id,
        ));
    }
    $ctx = [
        'document_type' => mo_str($instance, key: 'documentType'),
        'document_id' => mo_str($instance, key: 'documentID'),
        'instance_id' => $instance_id,
        'original_node_id' => $original_node_id,
    ];
    return mo_with_lock(
        "nodes:{$ctx['document_id']}",
        static fn(): array|WP_Error => mo_clear_component_instance_override_locked($ctx),
    );
}

/**
 * @param  array<string, mixed> $ctx
 * @return array<string, mixed>|WP_Error
 */
function mo_clear_component_instance_override_locked(array $ctx): array|WP_Error
{
    $document_type = (string) $ctx['document_type'];
    $document_id = (string) $ctx['document_id'];
    $instance_id = (string) $ctx['instance_id'];
    $original_node_id = (string) $ctx['original_node_id'];

    $existing = mo_find_component_instance_override_row($document_type, $document_id, $instance_id, $original_node_id);
    if ($existing instanceof WP_Error) {
        return $existing;
    }
    if ($existing === null) {
        return ['instance_id' => $instance_id, 'original_node_id' => $original_node_id, 'cleared' => false];
    }
    $row_id = mo_str($existing, key: 'ID');
    global $wpdb;
    /** @var \wpdb $wpdb */
    $ok = $wpdb->update(
        mo_table('nodes'),
        ['status' => 'delete', 'revision' => mo_uuid()],
        ['ID' => $row_id, 'documentType' => $document_type, 'documentID' => $document_id, 'status' => 'publish'],
        ['%s', '%s'],
        ['%s', '%s', '%s', '%s'],
    );
    if ($ok === false) {
        return mo_error('mo_write_failed', __(
            'Could not clear the component-instance override.',
            domain: 'novamira-pro',
        ));
    }
    if ($ok === 0) {
        // $existing was loaded moments ago with status=publish, so 0 rows
        // affected means it was raced away between that read and this
        // UPDATE (an external writer, not another call through this
        // ability — mo_with_lock serializes those). A genuine race, not
        // the "nothing to clear" no-op case above.
        return mo_concurrent_modification_error('node', $row_id);
    }
    mo_emit_changed(
        ability: 'novamira/mosaic-clear-component-instance-override',
        operation: 'clear',
        entity: 'node',
        entity_id: $row_id,
        context: [
            'document_type' => $document_type,
            'document_id' => $document_id,
            'theme_id' => mo_str($existing, key: 'themeID'),
        ],
    );
    return ['instance_id' => $instance_id, 'original_node_id' => $original_node_id, 'cleared' => true];
}
