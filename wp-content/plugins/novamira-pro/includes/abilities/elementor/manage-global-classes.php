<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

use WP_Error;

/**
 * Abilities: Elementor v4 Global Classes — list / create / update / delete /
 * apply. Each operation is registered as its own ability so input schemas
 * stay sharp and discovery via the abilities list is direct. The shared
 * helpers (gc_*) below host the actual logic; the ability callbacks are
 * thin shells that guard the runtime and delegate.
 */

if (!defined('ABSPATH')) {
    exit();
}

const NOVAMIRA_GLOBAL_CLASSES_REPO = 'Elementor\\Modules\\GlobalClasses\\Global_Classes_Repository';

// Owners of the separate labels / order kit metas (Elementor 4.1.0+); absent on 4.0.x.
const NOVAMIRA_GLOBAL_CLASSES_LABELS = 'Elementor\\Modules\\GlobalClasses\\Global_Classes_Labels';

const NOVAMIRA_GLOBAL_CLASSES_ORDER = 'Elementor\\Modules\\GlobalClasses\\Global_Classes_Order';

// Breakpoint id for the base (unconditional) style variant. Elementor's own
// style-parser rejects a base variant whose meta.breakpoint is null
// ("missing_or_invalid_value"), and its atomic styles manager registers
// "desktop" as the canonical base breakpoint (DEFAULT_BREAKPOINT). Writing the
// base with breakpoint "desktop" (not null) is what makes the v4 editor treat
// the values as explicitly defined instead of inherited. The frontend renders
// either way because group_by_breakpoint() maps null -> "desktop", but the
// editor validates variants through the parser, which is why null must not ship.
// Shared with every other Elementor write path through EL_BASE_BREAKPOINT.
/** @var string */
const GC_BASE_BREAKPOINT = EL_BASE_BREAKPOINT;

// Elementor's atomic-styles cache key for the compiled global-classes CSS. The
// files are `uploads/elementor/css/global-<context>-<breakpoint>.css` and their
// validity lives in the `elementor_atomic_cache_validity__global` option
// (Atomic_Global_Styles::STYLES_KEY).
const GC_ATOMIC_STYLES_KEY = 'global';

// Per-rename element-cache sweep: documents that both use the class and hold a
// cache are fetched and cleared in batches of GC_ELEMENT_CACHE_CLEAR_BATCH
// (overridable through the internal `novamira/elementor/global-class-element-cache-batch`
// filter — tests lower it) until the query returns nothing, with a hard cap of
// GC_ELEMENT_CACHE_CLEAR_MAX_BATCHES iterations (100k documents at the default).
const GC_ELEMENT_CACHE_CLEAR_BATCH = 2000;

const GC_ELEMENT_CACHE_CLEAR_MAX_BATCHES = 50;

wp_register_ability('novamira/elementor-list-global-classes', [
    'label' => __('List Elementor Global Classes', domain: 'novamira-pro'),
    'description' => __(
        'Lists every Elementor v4 Global Class registered on this site. Returns each class\'s id, label, type, and style variants.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'classes' => ['type' => 'array'],
            'order' => ['type' => 'array'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\list_global_classes',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

wp_register_ability('novamira/elementor-create-global-class', [
    'label' => __('Create Elementor Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Creates an Elementor v4 Global Class. "styles" is the base variant (persisted with breakpoint "desktop") as a CSS property → value map; scalars auto-wrap against the v4 Style Schema (color:"#111", padding:24), `{$$type, value}` also works, unknown properties fail hard with the valid list. Optional "variants" add breakpoint/state overrides; a null breakpoint is accepted and normalized to "desktop". Compiled CSS is invalidated automatically. An already-open Elementor editor does not pick up global-class changes made through this ability — save unsaved editor work, then reload the editor before searching for or using the class.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'label' => [
                'type' => 'string',
                'description' => 'Label for the new global class.',
            ],
            'styles' => [
                'type' => 'object',
                'description' => 'CSS property → prop-value map for the base (default) variant. Scalars are auto-wrapped per the Style Schema (e.g. "color":"#111", "font-size":24, "padding":{block-start:24,...}); the long-form `{$$type, value}` shape also works. Use only valid top-level CSS property names — e.g. `background-color` is NOT valid, use the `background` shape with a nested `color` field. Call novamira/elementor-get-style-schema to discover valid names and shapes.',
                'additionalProperties' => true,
            ],
            'variants' => [
                'type' => 'array',
                'description' => 'Optional additional style variants for responsive breakpoints and/or states. Each variant is an object with `meta` ({breakpoint: string|null, state: string|null}) and `styles` (same shape as top-level styles). The base variant (breakpoint:"desktop", state:null) is created from the top-level `styles` parameter; pass breakpoint:null here for a base override and it is normalized to "desktop". Use this to add tablet/mobile overrides or hover/active/focus states. Common breakpoints: "tablet", "mobile". Common states: "hover", "active", "focus".',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'meta' => [
                            'type' => 'object',
                            'properties' => [
                                'breakpoint' => ['type' => ['string', 'null']],
                                'state' => ['type' => ['string', 'null']],
                            ],
                        ],
                        'styles' => ['type' => 'object', 'additionalProperties' => true],
                    ],
                    'required' => ['meta', 'styles'],
                ],
            ],
        ],
        'required' => ['label', 'styles'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'class' => ['type' => 'object'],
            'editor_reload_required' => [
                'type' => 'boolean',
                'description' => 'Always true: an already-open Elementor editor hydrates its global-classes store once at boot and does not see classes changed through this ability until it is reloaded.',
            ],
            'error' => ['type' => 'string'],
            'unknown_properties' => ['type' => 'array', 'items' => ['type' => 'string']],
            'invalid_values' => ['type' => 'object'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\create_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => false],
    ],
]);

wp_register_ability('novamira/elementor-edit-global-class', [
    'label' => __('Edit Elementor Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Edits an Elementor v4 Global Class in place: label (rename) and/or styles (replaces the BASE variant only, breakpoint "desktop"; other variants are preserved). The id never changes. Styles validate like create-global-class; a null breakpoint is accepted and normalized to "desktop". Compiled CSS is invalidated automatically; a rename also drops the rendered-element cache of documents using the class. An already-open Elementor editor does not pick up global-class changes made through this ability — save unsaved editor work, then reload the editor before searching for or using the class.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'class_id' => [
                'type' => 'string',
                'description' => 'The id of the global class to update.',
            ],
            'label' => [
                'type' => 'string',
                'description' => 'New label. Optional — pass at least one of label or styles.',
            ],
            'styles' => [
                'type' => 'object',
                'description' => 'CSS property → prop-value map that REPLACES the BASE variant only (breakpoint "desktop", no state). Existing tablet/mobile/widescreen/hover variants are preserved. Scalars are auto-wrapped per Style Schema (e.g. "color":"#111"); the long-form `{$$type, value}` shape also works. Optional — pass at least one of label or styles.',
                'additionalProperties' => true,
            ],
        ],
        'required' => ['class_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'class' => ['type' => 'object'],
            'element_cache_cleared' => [
                'type' => 'integer',
                'description' => 'Only after a rename: how many documents using the class had their rendered-element cache dropped (Elementor renders the class name from the label, so cached HTML would otherwise keep the old name).',
            ],
            'element_cache_truncated' => [
                'type' => 'boolean',
                'description' => 'Only after a rename: true when the sweep stopped early (batch cap reached, or a cache row could not be deleted) — some cached HTML may still carry the old class name; run novamira/elementor-clear-document-cache for the remaining documents.',
            ],
            'editor_reload_required' => [
                'type' => 'boolean',
                'description' => 'Always true: an already-open Elementor editor hydrates its global-classes store once at boot and does not see classes changed through this ability until it is reloaded.',
            ],
            'error' => ['type' => 'string'],
            'unknown_properties' => ['type' => 'array', 'items' => ['type' => 'string']],
            'invalid_values' => ['type' => 'object'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\edit_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => true],
    ],
]);

wp_register_ability('novamira/elementor-delete-global-class', [
    'label' => __('Delete Elementor Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an Elementor v4 Global Class by id. Widgets that still reference it will lose the styling but will not break — Elementor ignores missing class references at render. Elementor\'s compiled global-classes CSS is invalidated automatically, so the class\'s rules disappear from the front end on the next page load. An already-open Elementor editor does not pick up global-class changes made through this ability — save unsaved editor work, then reload the editor before searching for or using the class.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'class_id' => [
                'type' => 'string',
                'description' => 'The id of the global class to delete.',
            ],
        ],
        'required' => ['class_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'editor_reload_required' => [
                'type' => 'boolean',
                'description' => 'Always true: an already-open Elementor editor hydrates its global-classes store once at boot and does not see classes changed through this ability until it is reloaded.',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\delete_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => false, 'destructive' => true, 'idempotent' => false],
    ],
]);

wp_register_ability('novamira/elementor-apply-global-class', [
    'label' => __('Apply Elementor Global Class', domain: 'novamira-pro'),
    'description' => __(
        'Attaches an existing Elementor v4 Global Class to an atomic widget on a page. Validation runs server-side: if the target element does not exist, is not an atomic widget, or does not expose a "classes" control the call fails HARD with the compact schema of the target widget INLINE in the error so you can correct and retry. Global Classes are a v4-only concept; v3 widgets cannot accept them. The target document\'s compiled CSS and rendered-element cache are invalidated automatically, so the class takes effect on the front end on the next load of that page — no editor save or manual cache clear is needed.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'class_id' => ['type' => 'string', 'description' => 'The id of the global class to attach.'],
            'post_id' => ['type' => 'integer', 'description' => 'The page/post id where the target element lives.'],
            'element_id' => [
                'type' => 'string',
                'description' => 'The Elementor element id of the atomic widget to attach the class to.',
            ],
        ],
        'required' => ['class_id', 'post_id', 'element_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'classes' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'On success: the element\'s full post-apply classes list so the caller can confirm the binding.',
            ],
            'already_present' => [
                'type' => 'boolean',
                'description' => 'On success: true when the class was already on the element — the call is a no-op rather than a new binding.',
            ],
            'widget_type' => ['type' => 'string'],
            'dropped_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
            'invalid_values' => ['type' => 'array'],
            'schema' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\apply_global_class',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => false],
    ],
]);

/**
 * Guard that ensures Elementor and the Global Classes module are loaded.
 * Returns null when the runtime is ready, or an error response otherwise.
 *
 * @return array{success: false, error: string}|null
 */
function gc_runtime_guard(): ?array
{
    if (!class_exists('Elementor\\Plugin')) {
        return ['success' => false, 'error' => 'Elementor is not active.'];
    }

    if (!class_exists(NOVAMIRA_GLOBAL_CLASSES_REPO)) {
        return [
            'success' => false,
            'error' => 'Elementor Global Classes module is not available. Requires Elementor v4.',
        ];
    }

    return null;
}

/**
 * @return array<string, mixed>
 */
function list_global_classes(): array
{
    $guard = gc_runtime_guard();
    return $guard ?? gc_list();
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function create_global_class(array $input): array
{
    $guard = gc_runtime_guard();
    return $guard ?? gc_create($input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function edit_global_class(array $input): array
{
    $guard = gc_runtime_guard();
    return $guard ?? gc_update($input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function delete_global_class(array $input): array
{
    $guard = gc_runtime_guard();
    return $guard ?? gc_delete($input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function apply_global_class(array $input): array|WP_Error
{
    $guard = gc_runtime_guard();
    return $guard ?? gc_apply($input);
}

/**
 * List all global classes.
 *
 * @return array{success: bool, classes: list<array<string, mixed>>, order: array<array-key, mixed>}
 */
function gc_list(): array
{
    $repo = \Elementor\Modules\GlobalClasses\Global_Classes_Repository::make();
    $all = $repo->all();

    /** @var array<string, array<string, mixed>> $items */
    $items = $all->get_items()->all();
    $order = $all->get_order()->all();

    $classes = [];
    foreach ($items as $id => $item) {
        $classes[] = [
            'id' => $id,
            'label' => $item['label'] ?? '',
            'type' => $item['type'] ?? 'class',
            'variants' => $item['variants'] ?? [],
        ];
    }

    return ['success' => true, 'classes' => $classes, 'order' => $order];
}

/**
 * Mirror the kit's frontend global-classes labels (and, where Elementor has
 * one, order) onto their preview twins so an agent-created class is visible
 * AND styled in the Elementor editor, not only on the frontend.
 *
 * Elementor 4.1.0 (commit b3bf13f1, "Global classes modifications
 * [ED-23093]") moved labels out of the combined `_elementor_global_classes`
 * meta into `Global_Classes_Labels` with a frontend key and a `_preview` twin.
 * The editor's class list is served through `Global_Classes_Labels::
 * get_ordered_labels()`, which in the preview context fills ids missing from
 * the preview map from the frontend labels — but `get_labels()` (used by the
 * publish flow's diff and by the class data) has no such fallback, and a
 * frontend-context write clears the preview labels for the touched ids. Mirroring
 * the frontend labels onto the preview twin after every write keeps both readers
 * consistent. The order key started as a single `_elementor_global_classes_order`
 * (no preview twin) and gained `_elementor_global_classes_order_preview` — read
 * with a fallback to the frontend order when empty — in commit 9e8bd748 ("Global
 * classes order to have preview context [ED-24345]"); it is mirrored only where
 * `Global_Classes_Order::META_KEY_PREVIEW` exists.
 *
 * Elementor 4.0.x keeps labels and order inside the combined
 * `_elementor_global_classes` / `_elementor_global_classes_preview` metas and
 * never reads the separate keys, so on that line neither class exists and this
 * is a no-op instead of writing meta nothing consumes. Every key name comes from
 * Elementor's own constants, so a key that is not declared is never written.
 */
function gc_sync_preview_context(): void
{
    if (!class_exists(\Elementor\Plugin::class)) {
        return;
    }
    $kit_id = (int) \Elementor\Plugin::$instance->kits_manager->get_active_id();
    if ($kit_id <= 0) {
        return;
    }
    $pairs = [
        [NOVAMIRA_GLOBAL_CLASSES_LABELS, 'META_KEY_FRONTEND', 'META_KEY_PREVIEW'],
        [NOVAMIRA_GLOBAL_CLASSES_ORDER,  'META_KEY',          'META_KEY_PREVIEW'],
    ];
    foreach ($pairs as [$class, $frontend_const, $preview_const]) {
        $frontend_key = gc_class_string_constant($class, $frontend_const);
        $preview_key = gc_class_string_constant($class, $preview_const);
        if ($frontend_key === null || $preview_key === null) {
            continue;
        }
        /** @var mixed $value */
        $value = get_post_meta($kit_id, key: $frontend_key, single: true);
        update_post_meta($kit_id, meta_key: $preview_key, meta_value: $value);
    }
}

/**
 * Read a non-empty string class constant, or null when the class or the
 * constant does not exist on the installed Elementor.
 */
function gc_class_string_constant(string $class, string $constant): ?string
{
    if (!class_exists($class) || !defined($class . '::' . $constant)) {
        return null;
    }
    /** @var mixed $value */
    $value = constant($class . '::' . $constant);
    return is_string($value) && $value !== '' ? $value : null;
}

/**
 * Invalidate Elementor's compiled global-classes CSS after a repository write.
 *
 * Global classes are site-wide: they compile into
 * `uploads/elementor/css/global-<context>-<breakpoint>.css`, whose validity is
 * tracked in the `elementor_atomic_cache_validity__global` option — NOT into
 * the per-document CSS that `el_clear_css_cache()` scopes to. A page can be
 * 100% correct in the database and still not render a class whose CSS was
 * compiled before the class existed.
 *
 * Elementor's own listener does exactly one thing on an editor save:
 * `Global_Classes_Repository::put()` fires `elementor/global_classes/update`
 * and `Atomic_Global_Styles::invalidate_cache()` answers with
 * `do_action('elementor/atomic-widgets/styles/clear', ['global'])`, which makes
 * `Atomic_Styles_Manager::clear_styles()` unlink the `global-*` files and drop
 * the validity node. That listener is only attached while the Global Classes
 * module booted with its applicable experiment gates satisfied, `e_atomic_elements`
 * among them — which ones apply is Elementor's business and has changed — so
 * re-fire the same narrow clear here (it is idempotent) instead of relying on
 * it. Only when nothing at all listens to
 * the atomic clear action (no atomic styles manager in this request) fall back
 * to Elementor's global `files_manager->clear_cache()`, the same site-wide
 * wipe the variables and global-styles abilities use; it also fires
 * `elementor/core/files/clear_cache`.
 */
function gc_invalidate_compiled_css(): void
{
    if (!class_exists(\Elementor\Plugin::class)) {
        return;
    }

    if (has_action('elementor/atomic-widgets/styles/clear') !== false) {
        do_action('elementor/atomic-widgets/styles/clear', [GC_ATOMIC_STYLES_KEY]);
        return;
    }

    \Elementor\Plugin::$instance->files_manager->clear_cache();
}

/**
 * Normalize the persisted `meta.breakpoint` of every variant so the base
 * variant never ships as null (see EL_BASE_BREAKPOINT). Classes written by
 * older releases (or by hand) can still carry a null base; rewriting it on
 * every save self-heals them.
 *
 * Contract: ONLY a missing / null / "" `meta.breakpoint` changes (→ the base
 * id). Everything else is preserved byte-for-byte — array keys and their
 * order, entries that are not arrays, a `meta` that is not an array (left
 * untouched), any other breakpoint value (`false`, an int, an array — those are
 * rejected at the input boundary, never coerced here), props, states, labels.
 * The list is walked in place, never rebuilt.
 *
 * @param mixed $variants Raw value of a class's `variants`.
 */
function gc_normalize_variants(mixed $variants): mixed
{
    if (!is_array($variants)) {
        return $variants;
    }

    foreach (array_keys($variants) as $key) {
        /** @var mixed $variant */
        $variant = $variants[$key];
        if (!is_array($variant) || !is_array($variant['meta'] ?? null)) {
            continue;
        }
        /** @var array<string, mixed> $meta */
        $meta = $variant['meta'];
        if (!el_is_healable_breakpoint($meta)) {
            continue;
        }
        $meta['breakpoint'] = GC_BASE_BREAKPOINT;
        $variant['meta'] = $meta;
        $variants[$key] = $variant;
    }

    return $variants;
}

/**
 * Normalize every class of the collection before it is written back with
 * `$repo->put()`. `put()` replaces the WHOLE collection, so a create, a delete,
 * or a label-only edit of one class re-persists every other class verbatim —
 * including bases stored by older builds with a null / "" breakpoint. Only
 * `meta.breakpoint` is touched (missing / null / "" → the base id); ids, labels,
 * props, states, and the order are left exactly as they were.
 *
 * @param array<string, array<string, mixed>> $items
 * @return array<string, array<string, mixed>>
 */
function gc_normalize_collection(array $items): array
{
    foreach (array_keys($items) as $id) {
        if (!is_array($items[$id]['variants'] ?? null)) {
            continue;
        }
        $items[$id]['variants'] = gc_normalize_variants($items[$id]['variants']);
    }

    return $items;
}

/**
 * Drop the rendered-element HTML cache of every document that USES a global
 * class. Elementor renders the class LABEL, not the id, as the CSS class name
 * (`Atomic_Global_Styles`: `$item['id'] = $item['label']` when compiling, and
 * `transform_classes_names()` maps stored ids to labels at render time) and
 * `Document::print_elements_with_wrapper()` serves `_elementor_element_cache`
 * HTML verbatim. After a rename the global CSS is recompiled under the new
 * label while cached markup still carries the old one, so those documents must
 * re-render.
 *
 * Targeted, never site-wide: a bounded query for Elementor documents whose
 * `_elementor_data` references the class id AND that currently hold an element
 * cache, repeated in batches until it returns no rows — deleting a batch's
 * cache rows removes them from the next JOIN, so no OFFSET is needed — with a
 * hard cap on iterations. When the cap is hit `truncated` is true so the caller
 * can fall back to novamira/elementor-clear-document-cache. Elementor's own
 * `Applied_Global_Classes_Usage` is not reused because it walks every Elementor
 * document through the parser and skips template types, which do cache too.
 *
 * @return array{cleared: int, truncated: bool}
 */
function gc_clear_element_cache_for_class(string $class_id): array
{
    $batch_size = gc_element_cache_batch_size();
    $cleared = 0;
    $failed = 0;
    // Keyset cursor: every batch selects ids strictly greater than the last one
    // handled, in ascending order. Deleted rows are behind the cursor anyway,
    // and a row whose delete FAILED is left behind too instead of being
    // re-selected forever — the sweep keeps clearing the rest and reports the
    // failure through `truncated`.
    $after = 0;
    for ($batch = 0; $batch < GC_ELEMENT_CACHE_CLEAR_MAX_BATCHES; $batch++) {
        $post_ids = gc_query_cached_documents_using_class($class_id, $batch_size, $after);
        if ($post_ids === []) {
            // The JOIN has nothing left beyond the cursor: only the rows that
            // failed to delete (if any) still carry the old class name.
            return ['cleared' => $cleared, 'truncated' => $failed > 0];
        }
        foreach ($post_ids as $post_id) {
            $after = max($after, $post_id);
            if (el_clear_element_cache($post_id)) {
                $cleared++;
                continue;
            }
            $failed++;
        }
    }

    // Batch cap reached with rows possibly still beyond the cursor.
    return ['cleared' => $cleared, 'truncated' => true];
}

/**
 * Batch size of the rename sweep. Internal filter so tests can drive the
 * multi-batch path with a handful of documents.
 */
function gc_element_cache_batch_size(): int
{
    /** @var mixed $filtered */
    $filtered = apply_filters('novamira/elementor/global-class-element-cache-batch', GC_ELEMENT_CACHE_CLEAR_BATCH);
    $size = is_int($filtered) ? $filtered : (int) GC_ELEMENT_CACHE_CLEAR_BATCH;
    // Clamped to [1, default]: a filter may only make batches smaller.
    return min((int) GC_ELEMENT_CACHE_CLEAR_BATCH, max(1, $size));
}

/**
 * One bounded page of Elementor documents (never revisions) with an id greater
 * than `$after` that reference `$class_id` in their `_elementor_data` AND
 * currently hold a rendered-element cache row, in ascending id order.
 *
 * @return list<int>
 */
function gc_query_cached_documents_using_class(string $class_id, int $limit, int $after = 0): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    $cache_key = el_element_cache_meta_key();
    $needle = '%' . $wpdb->esc_like('"' . $class_id . '"') . '%';
    // Revisions are excluded: Elementor copies its metas onto revisions, so a
    // revision can hold a cache row too, but the front end never renders a
    // revision and WordPress's delete_post_meta() redirects a revision id to its
    // parent — such a row could never be deleted and would keep the sweep busy.
    $prepared = $wpdb->prepare(
        'SELECT DISTINCT data.post_id FROM %i AS data'
        . ' INNER JOIN %i AS cache ON cache.post_id = data.post_id AND cache.meta_key = %s'
        . " INNER JOIN %i AS post ON post.ID = data.post_id AND post.post_type <> 'revision'"
        . " WHERE data.meta_key = '_elementor_data' AND data.meta_value LIKE %s AND data.post_id > %d"
        . ' ORDER BY data.post_id ASC LIMIT %d',
        $wpdb->postmeta,
        $wpdb->postmeta,
        $cache_key,
        $wpdb->posts,
        $needle,
        $after,
        $limit,
    );
    if (!is_string($prepared) || $prepared === '') {
        return [];
    }
    /** @var list<string> $rows */
    $rows = $wpdb->get_col($prepared);

    return array_map(static fn(string $id): int => (int) $id, $rows);
}

/**
 * Create a new global class.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function gc_create(array $input): array
{
    $label = (string) ($input['label'] ?? '');
    if ($label === '') {
        return ['success' => false, 'error' => 'Parameter "label" is required for create.'];
    }

    /** @var array<string, mixed> $styles */
    $styles = is_array($input['styles'] ?? null) ? $input['styles'] : [];
    if ($styles === []) {
        return ['success' => false, 'error' => 'Parameter "styles" is required for create.'];
    }

    $variants_result = gc_assemble_variants($styles, $input);
    if (array_key_exists('error', $variants_result)) {
        return ['success' => false] + $variants_result;
    }

    $all_variants = $variants_result['variants'] ?? [];

    // Generate a unique ID (Elementor uses this format).
    $class_id = 'gc-' . bin2hex(random_bytes(8));

    $new_item = [
        'id' => $class_id,
        'type' => 'class',
        'label' => $label,
        'variants' => $all_variants,
    ];

    $repo = \Elementor\Modules\GlobalClasses\Global_Classes_Repository::make();
    $all = $repo->all();

    /** @var array<string, array<string, mixed>> $items */
    $items = $all->get_items()->all();
    $order = $all->get_order()->all();

    $items[$class_id] = $new_item;
    $order[] = $class_id;
    $items = gc_normalize_collection($items);

    try {
        $repo->put($items, $order);
        gc_sync_preview_context();
        gc_invalidate_compiled_css();
    } catch (\Exception $e) {
        return ['success' => false, 'error' => 'Failed to save global class: ' . $e->getMessage()];
    }

    return ['success' => true, 'class' => $new_item, 'editor_reload_required' => true];
}

/**
 * Update an existing global class. The caller must provide `class_id` and at
 * least one of `label` (rename) or `styles` (replace the variant's CSS). The
 * existing class id is preserved so any widget already referencing it keeps
 * its binding.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function gc_update(array $input): array
{
    $class_id = (string) ($input['class_id'] ?? '');
    if ($class_id === '') {
        return ['success' => false, 'error' => 'Parameter "class_id" is required for update.'];
    }

    if (!array_key_exists('label', $input) && !array_key_exists('styles', $input)) {
        return [
            'success' => false,
            'error' => 'Update requires at least one of "label" (rename) or "styles" (replace CSS).',
        ];
    }

    $repo = \Elementor\Modules\GlobalClasses\Global_Classes_Repository::make();
    $all = $repo->all();

    /** @var array<string, array<string, mixed>> $items */
    $items = $all->get_items()->all();
    $order = $all->get_order()->all();

    if (($items[$class_id] ?? null) === null) {
        return ['success' => false, 'error' => "Global class '{$class_id}' not found."];
    }

    $update_result = gc_apply_update_overrides($items[$class_id], $input);
    if (array_key_exists('error', $update_result)) {
        return ['success' => false] + $update_result;
    }

    $updated = $update_result['item'] ?? $items[$class_id];
    $label_changed = ($updated['label'] ?? null) !== ($items[$class_id]['label'] ?? null);
    $items[$class_id] = $updated;

    $response = gc_persist_update($repo, $items, $order, $class_id);
    if ($label_changed && ($response['success'] ?? false) === true) {
        // The label IS the rendered class name: cached document HTML still
        // carries the old one, so the documents using this class must re-render.
        $sweep = gc_clear_element_cache_for_class($class_id);
        $response['element_cache_cleared'] = $sweep['cleared'];
        $response['element_cache_truncated'] = $sweep['truncated'];
    }

    return $response;
}

/**
 * Persist an updated collection for `gc_update` and build its success/error
 * response. Normalizes every class first (self-heals a legacy null base
 * breakpoint on this class AND every other one re-persisted by `put()` — a
 * label-only rename must not re-persist it), then invalidates the compiled CSS.
 *
 * @param array<string, array<string, mixed>> $items
 * @param array<array-key, mixed> $order
 * @return array<string, mixed>
 */
function gc_persist_update(
    \Elementor\Modules\GlobalClasses\Global_Classes_Repository $repo,
    array $items,
    array $order,
    string $class_id,
): array {
    $items = gc_normalize_collection($items);

    try {
        $repo->put($items, $order);
        gc_sync_preview_context();
        gc_invalidate_compiled_css();
    } catch (\Exception $e) {
        return ['success' => false, 'error' => 'Failed to save global class: ' . $e->getMessage()];
    }

    return ['success' => true, 'class' => $items[$class_id], 'editor_reload_required' => true];
}

/**
 * Apply the optional `label` and `styles` overrides from an update payload to
 * an existing class item. Returns the merged item or an error message when
 * any override is malformed.
 *
 * @param array<string, mixed> $existing
 * @param array<string, mixed> $input
 * @return array{item: array<string, mixed>}|array{error: string, unknown_properties?: list<string>, invalid_values?: array<string, mixed>}
 */
function gc_apply_update_overrides(array $existing, array $input): array
{
    if (array_key_exists('label', $input)) {
        $label = (string) ($input['label'] ?? '');
        if ($label === '') {
            return ['error' => '"label" cannot be empty when updating.'];
        }
        $existing['label'] = $label;
    }

    if (array_key_exists('styles', $input)) {
        /** @var array<string, mixed> $styles */
        $styles = is_array($input['styles']) ? $input['styles'] : [];
        if ($styles === []) {
            return ['error' => '"styles" must be a non-empty object of CSS property/value pairs.'];
        }

        $validation = el_validate_style_props($styles);
        if (!array_key_exists('props', $validation)) {
            /** @var array{error: string, unknown_properties?: list<string>, invalid_values?: array<string, mixed>} $validation */
            return $validation;
        }

        $existing['variants'] = gc_replace_base_variant($existing['variants'] ?? null, $validation['props']);
    }

    return ['item' => $existing];
}

/**
 * Replace only the base variant (breakpoint null / desktop, no state) of an
 * existing `variants` list with a new base carrying the given props. Every
 * other variant (tablet, mobile, widescreen, hover states, etc.) is
 * preserved. When the existing class has no base variant yet the new one
 * is prepended.
 *
 * This is the edit-in-place semantic the ability advertises: "replaces
 * the existing variant" (singular, the base), NOT "wipes the variant
 * list".
 *
 * @param mixed $existing_variants Raw value of the class's `variants`.
 * @param array<string, mixed> $base_props
 * @return list<array<string, mixed>>
 */
function gc_replace_base_variant(mixed $existing_variants, array $base_props): array
{
    $new_base = [
        'meta' => ['breakpoint' => GC_BASE_BREAKPOINT, 'state' => null],
        'props' => $base_props,
    ];

    if (!is_array($existing_variants) || $existing_variants === []) {
        return [$new_base];
    }

    /** @var list<array<string, mixed>> $variants */
    $variants = array_values($existing_variants);

    $base_index = -1;
    foreach (array_keys($variants) as $i) {
        $variant = $variants[$i];
        if (!is_array($variant['meta'] ?? null)) {
            continue;
        }
        /** @var mixed $bp */
        $bp = $variant['meta']['breakpoint'] ?? null;
        /** @var mixed $state */
        $state = $variant['meta']['state'] ?? null;
        $is_base_breakpoint = $bp === null || $bp === 'desktop';
        $is_base_state = $state === null || $state === '';
        if ($is_base_breakpoint && $is_base_state) {
            $base_index = $i;
            break;
        }
    }

    if ($base_index === -1) {
        array_unshift($variants, $new_base);
        return $variants;
    }

    $variants[$base_index] = $new_base;
    return $variants;
}

/**
 * Validate the base styles and assemble all variants (base + optional extras).
 *
 * @param array<string, mixed> $styles    Base styles map.
 * @param array<string, mixed> $input     Full input (may contain `variants` key).
 * @return array{variants: list<array<string, mixed>>}|array{error: string, unknown_properties?: list<string>, invalid_values?: array<string, mixed>}
 */
function gc_assemble_variants(array $styles, array $input): array
{
    $validation = el_validate_style_props($styles);
    if (!array_key_exists('props', $validation)) {
        /** @var array{error: string, unknown_properties?: list<string>, invalid_values?: array<string, mixed>} $validation */
        return $validation;
    }

    $all = [
        ['meta' => ['breakpoint' => GC_BASE_BREAKPOINT, 'state' => null], 'props' => $validation['props']],
    ];

    /** @var list<array<string, mixed>> $extra_raw */
    $extra_raw = is_array($input['variants'] ?? null) ? $input['variants'] : [];
    $extra_result = gc_build_extra_variants($extra_raw);
    if (array_key_exists('error', $extra_result)) {
        return $extra_result;
    }

    $extra_built = $extra_result['variants'] ?? [];

    return ['variants' => array_merge($all, $extra_built)];
}

/**
 * Validate and build extra variants (responsive breakpoints, hover states)
 * from the caller's `variants` array. Each entry must have `meta` (with
 * `breakpoint` and/or `state`) and `styles` (validated against Style Schema).
 *
 * @param list<array<string, mixed>> $raw_variants
 * @return array{variants: list<array<string, mixed>>}|array{error: string, unknown_properties?: list<string>, invalid_values?: array<string, mixed>}
 */
function gc_build_extra_variants(array $raw_variants): array
{
    if ($raw_variants === []) {
        return ['variants' => []];
    }

    $built = [];
    foreach ($raw_variants as $i => $variant) {
        $parsed = gc_parse_single_variant($variant, $i);
        if (array_key_exists('error', $parsed)) {
            return $parsed;
        }
        $v = $parsed['variant'] ?? [];
        $built[] = $v;
    }

    return ['variants' => $built];
}

/**
 * Parse and validate a single variant entry from the caller's `variants` array.
 *
 * @param array<string, mixed> $variant
 * @return array{variant: array<string, mixed>}|array{error: string, unknown_properties?: list<string>, invalid_values?: array<string, mixed>}
 */
function gc_parse_single_variant(array $variant, int $index): array
{
    /** @var array<string, mixed>|null $meta */
    $meta = is_array($variant['meta'] ?? null) ? $variant['meta'] : null;
    if ($meta === null) {
        return ['error' => sprintf('variants[%d].meta is required (object with breakpoint and/or state).', $index)];
    }

    /** @var array<string, mixed> $styles */
    $styles = is_array($variant['styles'] ?? null) ? $variant['styles'] : [];
    if ($styles === []) {
        return ['error' => sprintf('variants[%d].styles must be a non-empty object.', $index)];
    }

    $validation = el_validate_style_props($styles);
    if (!array_key_exists('props', $validation)) {
        $err = $validation['error'] ?? 'Invalid style payload.';
        $validation['error'] = sprintf('variants[%d]: %s', $index, $err);
        /** @var array{error: string, unknown_properties?: list<string>, invalid_values?: array<string, mixed>} $validation */
        return $validation;
    }

    $breakpoint = gc_resolve_variant_breakpoint($meta, $index);
    if (array_key_exists('error', $breakpoint)) {
        return $breakpoint;
    }
    /** @var array{breakpoint: string} $breakpoint */
    $state = is_string($meta['state'] ?? null) ? $meta['state'] : null;

    return [
        'variant' => [
            'meta' => ['breakpoint' => $breakpoint['breakpoint'], 'state' => $state],
            'props' => $validation['props'],
        ],
    ];
}

/**
 * Resolve a caller-supplied variant breakpoint. Absent / null / "" mean the
 * base variant and become the canonical base id "desktop" (Elementor rejects a
 * null breakpoint); a string is taken as is; any other type is a caller error,
 * never silently coerced.
 *
 * @param array<string, mixed> $meta
 * @return array{breakpoint: string}|array{error: string}
 */
function gc_resolve_variant_breakpoint(array $meta, int $index): array
{
    if (el_is_healable_breakpoint($meta)) {
        return ['breakpoint' => GC_BASE_BREAKPOINT];
    }
    /** @var mixed $bp */
    $bp = $meta['breakpoint'];
    if (is_string($bp)) {
        return ['breakpoint' => $bp];
    }
    return [
        'error' => sprintf(
            'variants[%d].meta.breakpoint must be a breakpoint name (string) or null for the base variant, got %s.',
            $index,
            gettype($bp),
        ),
    ];
}

/**
 * Delete a global class.
 *
 * @param array<string, mixed> $input
 * @return array{success: bool, error?: string, editor_reload_required?: bool}
 */
function gc_delete(array $input): array
{
    $class_id = (string) ($input['class_id'] ?? '');
    if ($class_id === '') {
        return ['success' => false, 'error' => 'Parameter "class_id" is required for delete.'];
    }

    $repo = \Elementor\Modules\GlobalClasses\Global_Classes_Repository::make();
    $all = $repo->all();

    /** @var array<string, array<string, mixed>> $items */
    $items = $all->get_items()->all();
    $order = $all->get_order()->all();

    if (($items[$class_id] ?? null) === null) {
        return ['success' => false, 'error' => "Global class '{$class_id}' not found."];
    }

    unset($items[$class_id]);
    $order = array_values(array_filter($order, fn(string $id): bool => $id !== $class_id));
    $items = gc_normalize_collection($items);

    try {
        $repo->put($items, $order);
        gc_sync_preview_context();
        gc_invalidate_compiled_css();
    } catch (\Exception $e) {
        return ['success' => false, 'error' => 'Failed to delete global class: ' . $e->getMessage()];
    }

    return ['success' => true, 'editor_reload_required' => true];
}

/**
 * Apply a global class to a widget on a page.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function gc_apply(array $input): array|WP_Error
{
    $parsed = gc_parse_apply_input($input);
    if (array_key_exists('error', $parsed)) {
        return ['success' => false, 'error' => $parsed['error']];
    }
    /** @var array{class_id: string, post_id: int, element_id: string} $parsed */

    [$elements, $read_error] = el_read_page_required($parsed['post_id']);
    if ($elements === null) {
        return ['success' => false, 'error' => $read_error ?? 'Unknown error.'];
    }

    // Validate that the target element exists, has a known widget type, and
    // actually supports the "classes" prop — v3 widgets don't, so applying a
    // global class to one would create an orphan setting silently ignored by
    // Elementor at render time. Fail hard with the schema inline instead.
    $target_check = gc_validate_apply_target($elements, $parsed['element_id'], $parsed['post_id']);
    if (array_key_exists('error_response', $target_check)) {
        return $target_check['error_response'];
    }

    // Find and update the element.
    $updated = gc_apply_class_to_elements($elements, $parsed['element_id'], $parsed['class_id']);
    if ($updated === null) {
        return [
            'success' => false,
            'error' => "Element '{$parsed['element_id']}' not found on page {$parsed['post_id']}.",
        ];
    }

    // el_write_page() invalidates the target document's compiled CSS (v3 file
    // + `_elementor_css`, the v4 `local-<id>-*` files + validity node) and its
    // rendered-element cache on every branch, then regenerates post CSS after
    // verification. Deliberately NOT a global files_manager->clear_cache():
    // applying a class changes one document, and the class's own CSS already
    // lives in the site-wide `global-*` files compiled at create/edit time.
    $save = el_write_page($parsed['post_id'], $updated['elements']);
    if ($save instanceof WP_Error) {
        return $save;
    }

    return [
        'success' => true,
        'classes' => $updated['classes'],
        'already_present' => $updated['already_present'],
    ];
}

/**
 * Parse apply-global-class input into the normalized struct or an error
 * string. Class existence is verified here too so the orchestrator stays
 * thin enough to pass the complexity threshold after the richer response
 * we now build.
 *
 * @param array<string, mixed> $input
 * @return array{error: string}|array{class_id: string, post_id: int, element_id: string}
 */
function gc_parse_apply_input(array $input): array
{
    $class_id = (string) ($input['class_id'] ?? '');
    $post_id = (int) ($input['post_id'] ?? 0);
    $element_id = (string) ($input['element_id'] ?? '');

    if ($class_id === '') {
        return ['error' => 'Parameter "class_id" is required for apply.'];
    }
    if ($post_id <= 0) {
        return ['error' => 'Parameter "post_id" is required for apply.'];
    }
    if ($element_id === '') {
        return ['error' => 'Parameter "element_id" is required for apply.'];
    }

    $repo = \Elementor\Modules\GlobalClasses\Global_Classes_Repository::make();
    $items = $repo->all()->get_items()->all();
    if (($items[$class_id] ?? null) === null) {
        return ['error' => "Global class '{$class_id}' not found."];
    }

    return ['class_id' => $class_id, 'post_id' => $post_id, 'element_id' => $element_id];
}

/**
 * Validate that the target element for a global class apply operation is a
 * real v4 atomic widget that exposes the "classes" control. Returns
 * `{ok: true}` on success or `{error_response: ...}` on any failure, with
 * the compact content-only schema of the widget embedded inline so the
 * caller can see what controls are actually available.
 *
 * @param list<array<string, mixed>> $elements
 * @return array{ok: true}|array{error_response: array<string, mixed>}
 */
function gc_validate_apply_target(array $elements, string $element_id, int $post_id): array
{
    $target = el_find($elements, $element_id);
    if ($target === null) {
        return [
            'error_response' => [
                'success' => false,
                'error' => "Element '{$element_id}' not found on page {$post_id}.",
            ],
        ];
    }

    $widget_type = el_element_widget_type($target);
    if ($widget_type === '') {
        return [
            'error_response' => [
                'success' => false,
                'error' => "Target element '{$element_id}' has no resolvable widget type — cannot attach a global class to a broken element.",
            ],
        ];
    }

    $schema = el_extract_schema($widget_type);
    if ($schema === null) {
        return [
            'error_response' => [
                'success' => false,
                'error' => sprintf(
                    'Target element has widget_type "%s" which is not registered with Elementor on this site.',
                    $widget_type,
                ),
                'widget_type' => $widget_type,
            ],
        ];
    }

    if (($schema['is_atomic'] ?? false) !== true) {
        return [
            'error_response' => [
                'success' => false,
                'error' => sprintf(
                    "Global classes are a v4-only feature. Widget '%s' is a legacy v3 widget and cannot accept global classes. Convert it to its atomic equivalent first (see novamira/skill-get slug=elementor-convert-to-v4).",
                    $widget_type,
                ),
                'widget_type' => $widget_type,
                'is_atomic' => false,
            ],
        ];
    }

    $controls = el_schema_controls($schema);
    if ($controls === null || !array_key_exists('classes', $controls)) {
        $element_type = $widget_type === NOVAMIRA_COMPACT_SCHEMA_CONTAINER_KEY ? 'container' : 'widget';
        return [
            'error_response' => build_single_element_error_response($element_type, $widget_type, [
                'ok' => false,
                'settings' => [],
                'dropped' => ['classes'],
                'invalid' => [],
            ]),
        ];
    }

    return ['ok' => true];
}

/**
 * Recursively walk elements to find and apply a global class to a specific element.
 *
 * Returns the updated elements list if the element was found and updated, or null if not found.
 *
 * Returns the updated tree plus the post-update classes list and a flag
 * telling the caller whether the class was already present — the flag lets
 * `gc_apply` distinguish "newly applied" from "already on the element" in
 * its response, which agents otherwise can't tell apart.
 *
 * @param list<array<string, mixed>> $elements
 * @return array{elements: list<array<string, mixed>>, classes: list<string>, already_present: bool}|null
 */
function gc_apply_class_to_elements(array $elements, string $element_id, string $class_id): ?array
{
    foreach ($elements as &$element) {
        if (($element['id'] ?? '') === $element_id) {
            /** @var array<string, mixed> $settings */
            $settings = $element['settings'] ?? [];

            // Get current classes prop (Elementor stores it as $$type/value).
            /** @var list<string> $current_classes */
            $current_classes = $settings['classes']['value'] ?? [];
            $already_present = in_array($class_id, $current_classes, strict: true);

            if (!$already_present) {
                $current_classes[] = $class_id;
            }

            $settings['classes'] = [
                '$$type' => 'classes',
                'value' => $current_classes,
            ];

            $element['settings'] = $settings;

            return [
                'elements' => $elements,
                'classes' => $current_classes,
                'already_present' => $already_present,
            ];
        }

        // Recurse into children.
        /** @var list<array<string, mixed>>|null $children */
        $children = ($element['elements'] ?? null) !== null && is_array($element['elements'])
            ? $element['elements']
            : null;
        if ($children !== null) {
            $updated = gc_apply_class_to_elements($children, $element_id, $class_id);
            if ($updated !== null) {
                $element['elements'] = $updated['elements'];

                return [
                    'elements' => $elements,
                    'classes' => $updated['classes'],
                    'already_present' => $updated['already_present'],
                ];
            }
        }
    }

    return null;
}
