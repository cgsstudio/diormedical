<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

/**
 * Abilities: Manage Bricks components (reusable element definitions).
 *
 * Components live in the `bricks_components` option as a flat array of
 * definitions. Each component is an array of the shape:
 *   [
 *     'id'         => <6-char hash>,
 *     'category'   => 'string',
 *     'elements'   => [ <root element>, ... ],   // first is root, its id == component id
 *     'properties' => [],
 *     '_created'   => timestamp,
 *     '_user_id'   => int,
 *     '_version'   => BRICKS_VERSION,
 *   ]
 *
 * The component label lives on the root element as `elements[0]['label']`.
 * Instances on pages/templates are regular Bricks elements with an added
 * `cid` key carrying the component id.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Property types the Bricks builder offers in its component property panel
 * (plus `textarea`, which it stores when a textarea control is connected).
 * Bricks persists the type without validating it, so an unknown type is
 * accepted here and only reported as a warning.
 */
const COMP_KNOWN_PROPERTY_TYPES = [
    'text',
    'textarea',
    'editor',
    'icon',
    'image',
    'image-gallery',
    'link',
    'select',
    'toggle',
    'query',
    'class',
];

wp_register_ability('novamira/bricks-list-components', [
    'label' => __('List Bricks Components', domain: 'novamira-pro'),
    'description' => __(
        'Lists Bricks components (reusable element definitions). Returns compact entries by default (id, label, category, root element name, element count, property count). Pass verbose=true to include the full element tree and every stored field of each property definition, including id, label, type, default, connections, options, replace, and fields added by Bricks or extensions — this can be large on sites with many components.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'search' => [
                'type' => 'string',
                'description' => 'Optional case-insensitive substring match against label, category, or id.',
            ],
            'verbose' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'If true, include the full element tree and the property definitions for each component.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'components' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'category' => ['type' => 'string'],
                        'root_name' => ['type' => 'string'],
                        'element_count' => ['type' => 'integer'],
                        'property_count' => ['type' => 'integer'],
                        'elements' => ['type' => 'array', 'items' => ['type' => 'object']],
                        'properties' => [
                            'type' => 'array',
                            'description' => 'Property definitions (verbose only). Every stored field is returned so the list can be passed back losslessly to bricks-edit-component patch.properties.',
                            'items' => [
                                'type' => 'object',
                                'properties' => [
                                    'id' => ['type' => 'string'],
                                    'label' => ['type' => 'string'],
                                    'type' => ['type' => 'string'],
                                    'default' => [
                                        'type' => ['string', 'number', 'integer', 'boolean', 'array', 'object', 'null'],
                                        'description' => 'Value used when an instance has no override.',
                                    ],
                                    'connections' => ['type' => 'object'],
                                    'options' => [
                                        'type' => 'array',
                                        'items' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'value' => [
                                                    'type' => [
                                                        'string',
                                                        'number',
                                                        'integer',
                                                        'boolean',
                                                        'array',
                                                        'object',
                                                        'null',
                                                    ],
                                                ],
                                                'label' => ['type' => 'string'],
                                            ],
                                            'required' => ['value', 'label'],
                                            'additionalProperties' => true,
                                        ],
                                    ],
                                    'replace' => [
                                        'type' => 'boolean',
                                        'description' => 'Present as true only when a class property replaces the element\'s own global classes. Merge behavior is represented by an omitted key.',
                                    ],
                                ],
                                'additionalProperties' => true,
                            ],
                        ],
                    ],
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_components',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to discover which components exist before creating, editing, or applying one. The id returned here is the `component_id` required by bricks-create-component (on conflict), bricks-edit-component, bricks-apply-component, and bricks-delete-component. Labels are extracted from the component root element — a component without a label falls back to the root element name. Use verbose=true to read the property definitions: their `id` values are the keys accepted by bricks-apply-component `properties`, and the ids to reuse when replacing the list via bricks-edit-component patch.properties. Property definitions are changed only through that patch, which replaces the whole list by id; after such an edit, reload any open Bricks builder tab before it saves.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/bricks-create-component', [
    'label' => __('Create Bricks Component', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Bricks component (a reusable element definition) and returns its generated id. Provide either `elements` (a fresh element tree) or `from_post_id` + `from_element_id` to carve the component out of an existing page subtree. Does not place an instance — call bricks-apply-component afterwards to insert the component onto a page.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'label' => [
                'type' => 'string',
                'description' => 'Display label shown in the Bricks builder component panel.',
                'minLength' => 1,
            ],
            'category' => [
                'type' => 'string',
                'description' => 'Optional category the component is grouped under.',
            ],
            'elements' => [
                'type' => 'array',
                'description' => 'Element tree for the component body. The first element is treated as the root; its id will be rewritten to the generated component id, and all references (parent/children) are remapped accordingly. Every other id that is not exactly 6 characters is regenerated and reported in warnings. Ids may be omitted (generated server-side, returned in generated_ids keyed by index); an element without an id can only be the root or a leaf. Mutually exclusive with from_post_id.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'parent' => ['type' => ['string', 'integer']],
                        'settings' => ['type' => 'object'],
                    ],
                    'required' => ['name', 'settings'],
                ],
            ],
            'from_post_id' => [
                'type' => 'integer',
                'description' => 'Copy the component body from an element subtree that already lives on this post.',
            ],
            'from_element_id' => [
                'type' => 'string',
                'description' => 'The id of the element to use as the component root. Required when from_post_id is provided.',
            ],
            'from_area' => [
                'type' => 'string',
                'description' => 'Which area to read when using from_post_id. Default "content".',
                'enum' => ['header', 'content', 'footer'],
                'default' => 'content',
            ],
            'properties' => comp_properties_input_schema(
                'Optional parameterized slots the component exposes. Each property connects one or more element settings to a label + default value; instances can override the value at placement. Example: {id:"prop_title", label:"Heading", type:"text", default:"Hello", connections:{"chldh1":["text"]}}. Omit `id` to have it generated automatically; a supplied id that repeats an earlier one is replaced by a generated id.',
            ),
        ],
        'required' => ['label'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'generated_ids' => [
                'type' => 'object',
                'description' => 'Index in the request => the id generated for that element, for every element sent without one.',
            ],
            'component_id' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'root_name' => ['type' => 'string'],
            'element_count' => ['type' => 'integer'],
            'property_count' => ['type' => 'integer'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_create_component',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Provide exactly one source: `elements` for an inline definition, or `from_post_id` + `from_element_id` to derive the component from an existing page subtree (the subtree is copied — the source page is not modified). The generated id is both the component id and the id of the root element in the component body. Use bricks-apply-component to place instances on pages afterwards.

Property connection sub-keys are one level deep only. A connection setting key of the form "setting:sub" writes settings[setting][sub] = value. Deeper paths like "_background:color:raw" do NOT work — Bricks writes settings[_background][color] = value and drops whatever was nested inside. Workarounds for the color case: (a) use a CSS custom property in _cssCustom and point the property at that variable, (b) use a type="class" property that swaps a global class, or (c) keep the color at the one-level-deep shape your setting supports. Link shapes connect cleanly via "link:url" because the real target is one level deep.

Use type="external" (not "url") when the default for a link-shaped setting needs to render an href on the frontend — Bricks button rendering checks settings.link.type against its fixed list (external / internal / taxonomy / meta / lightboxImage / lightboxVideo / media).',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/bricks-apply-component', [
    'label' => __('Apply Bricks Component', domain: 'novamira-pro'),
    'description' => __(
        'Inserts a component instance into a Bricks area. The instance is a tiny element pointing to the component (via cid) — the component body is not duplicated on the page. Position shape matches bricks-insert-content: pass exactly one of after / before / append_to / prepend_to.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or template ID.',
            ],
            'area' => [
                'type' => 'string',
                'description' => 'Which area to place the instance in.',
                'enum' => ['header', 'content', 'footer'],
                'default' => 'content',
            ],
            'component_id' => [
                'type' => 'string',
                'description' => 'Id of the component to instantiate. Use bricks-list-components to discover available ids.',
            ],
            'position' => [
                'type' => 'object',
                'description' => 'Optional insertion descriptor. Provide exactly one of: after, before, append_to, prepend_to. Omit to append the instance at the end of the area.',
                'properties' => [
                    'after' => ['type' => 'string'],
                    'before' => ['type' => 'string'],
                    'append_to' => ['type' => 'string'],
                    'prepend_to' => ['type' => 'string'],
                ],
                'additionalProperties' => false,
                'minProperties' => 1,
                'maxProperties' => 1,
                'oneOf' => [
                    ['required' => ['after']],
                    ['required' => ['before']],
                    ['required' => ['append_to']],
                    ['required' => ['prepend_to']],
                ],
            ],
            'properties' => [
                'type' => 'object',
                'description' => 'Property overrides for this instance. Keys are the property ids declared on the component — read them from the lossless `properties` list returned by bricks-list-components verbose=true. A missing override lets top-level setting connections use the property default; for colon-delimited nested connections, a missing override removes the nested setting and does not consult the default.',
                'additionalProperties' => true,
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Write even when another user currently holds the WordPress edit lock on this post (their builder or editor session is open). Without it such a write is refused with locked_by_other_user naming the user.',
            ],
        ],
        'required' => ['post_id', 'component_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'reference_id' => [
                'type' => 'string',
                'description' => 'The existing element the instance was placed relative to, when a position was given.',
            ],
            'instance_element_id' => ['type' => 'string'],
            'revision_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Id of the WordPress revision created before this write, carrying the Bricks element meta of every area as it was — restorable from the Bricks builder\'s revision panel or WordPress. Null when revisions are disabled for the post type (a warning says so).',
            ],
            'post_id' => ['type' => 'integer'],
            'area' => ['type' => 'string'],
            'component_id' => ['type' => 'string'],
            'position_applied' => ['type' => 'string'],
            'parent' => ['type' => ['string', 'integer']],
            'element_count' => ['type' => 'integer'],
            'saved' => [
                'type' => 'boolean',
                'description' => 'Deprecated alias of verified (kept for existing callers; it is no longer a literal true). Read verified instead.',
            ],
            'verified' => [
                'type' => 'boolean',
                'description' => 'True only when the element tree read back from the database after the write equals the tree that was written. False means the stored content differs — the warnings say how.',
            ],
            'stored_element_count' => [
                'type' => 'integer',
                'description' => 'Number of elements the area holds in the database after the write (read back, not computed from the request).',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => '"regenerated" when Bricks serves CSS from external files and the per-post CSS file was rewritten for this write; "inline" when Bricks serves CSS inline (nothing to regenerate); "skipped(reason)" when file mode is on but the file could not be written — the reason names the fix.',
            ],
            'unresolved_variables' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when a written value references var(--name) for a name that is neither a Bricks global variable, a palette colour nor a custom property declared in the site\'s custom CSS: those declarations are dropped by the browser until the token exists.',
            ],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_apply_component',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Applying a component twice creates two distinct instances — both render the same component body, and editing the component propagates to both. Position shape matches bricks-insert-content (one of after, before, append_to, prepend_to). Property overrides are keyed by the property ids that bricks-list-components verbose=true returns (the same ids bricks-edit-component patch.properties replaces the list by); reload a Bricks builder tab that is open on the target post before it saves, or it can overwrite the inserted instance.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/bricks-edit-component', [
    'label' => __('Edit Bricks Component', domain: 'novamira-pro'),
    'description' => __(
        'Patches a Bricks component definition in place (label, category, element tree, or property definitions). Edits to `elements` and `properties` are disruptive — every instance of this component on every page re-renders with the new body and property set. `properties` replaces the whole property list: reuse existing ids from bricks-list-components verbose=true to keep instance overrides and unrecognized stored fields working. For a top-level setting connection, an instance placed before the property existed renders the property default until a non-empty override is supplied; that override wins. For a colon-delimited nested connection, an absent override removes the nested setting and the property default is not consulted. Introducing such a nested connection while instances exist returns a warning with their count. Patching `elements` also regenerates the stored CSS of every page that carries an instance, because the page compiles its rules from the component body; those pages come back in `affected_posts` with the outcome per page. After any API edit of property definitions, reload every open Bricks builder tab before its next save or that stale tab can overwrite the API edit. Use bricks-list-components first to confirm the scope of affected pages via the instance count, or prefer bricks-create-component for variations.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'component_id' => [
                'type' => 'string',
                'description' => 'Id of the component to edit.',
            ],
            'patch' => [
                'type' => 'object',
                'description' => 'Fields to merge into the component definition. Any omitted key is left untouched.',
                'properties' => [
                    'label' => [
                        'type' => 'string',
                        'description' => 'New display label. Applied to the component root element.',
                    ],
                    'category' => ['type' => 'string'],
                    'elements' => [
                        'type' => 'array',
                        'description' => 'New element tree for the component body. The first element is taken as the root — its id will be rewritten to the component id. Every other id that is not exactly 6 characters is regenerated and reported in warnings.',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'string'],
                                'name' => ['type' => 'string'],
                                'parent' => ['type' => ['string', 'integer']],
                                'settings' => ['type' => 'object'],
                            ],
                            'required' => ['id', 'name', 'settings'],
                        ],
                    ],
                    'properties' => comp_properties_input_schema(
                        'New property definitions. Replaces the WHOLE property list (an empty array removes every property). Entries keep the `id` you pass — reuse the ids from bricks-list-components verbose=true so existing instance overrides keep matching; entries without an id get a generated one, and a duplicate id is refused (unlike create, which replaces it) because it would detach the overrides keyed by it. Connections are validated against the element tree in effect after this call (the new `elements` when both are patched).',
                    ),
                    'add_properties' => comp_properties_input_schema(
                        'Additive: appends new property definitions to the existing list without resending it. Every entry needs a `type`; an `id` that already exists is refused (edit it through `properties` instead); when instances of the component exist, pass a `default` so they keep rendering — a missing default is reported with the pages affected. Cannot be combined with `properties` in the same call.',
                    ),
                ],
                'additionalProperties' => false,
                'minProperties' => 1,
            ],
        ],
        'required' => ['component_id', 'patch'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'component_id' => ['type' => 'string'],
            'patched' => ['type' => 'array', 'items' => ['type' => 'string']],
            'affected_posts' => [
                'type' => 'array',
                'description' => 'Every post that carries an instance of this component, with the outcome of regenerating its CSS for the new body: "regenerated", "inline" on a site that serves CSS inline, or "skipped(<reason>)" when the file could not be written. Always present when the element tree was patched — an empty list means no post carries an instance.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'post_id' => ['type' => 'integer'],
                        'post_title' => ['type' => 'string'],
                        'areas' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'instance_count' => ['type' => 'integer'],
                        'css_file' => ['type' => 'string'],
                    ],
                ],
            ],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_edit_component',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Changes to `elements` and `properties` propagate site-wide to every instance — treat them as destructive changes to every page that uses the component, even though the data on those pages is not rewritten.

`properties` is a full replacement (PUT), not a merge: send the complete list every time. Read the current definitions with bricks-list-components verbose=true and keep the `id` of every property you want to preserve (instance overrides are keyed by property id — a property that comes back with a new id loses every override, and the result reports how many instances were overriding a removed id). Existing stored fields that this ability does not own are preserved by property id. If an existing property is submitted without `options`, its stored options are also preserved; send `options: []` to clear them. For a class property, only `replace: true` is stored; false or omission clears replacement so the property classes merge with the element\'s own global classes.

For top-level setting connections, instances placed before a new property existed use its non-empty `default` when they have no non-empty override, and a non-empty override wins. Colon-delimited nested connections behave differently: when an instance has no override, Bricks removes that nested setting and does not consult the property default. Introducing a nested connection on a new or existing property while instances exist returns a warning that names their count.

After an API edit of property definitions, reload every open Bricks builder tab before saving in it. A tab that loaded the old component list can overwrite the API edit on its next save.

Patching `elements` without `properties` keeps the current property list, so every existing connection must still point at an element id present in the new tree; the call is refused otherwise — include `properties` in the same call to re-target or drop the affected connection.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/bricks-delete-component', [
    'label' => __('Delete Bricks Component', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Bricks component. The `orphan_instances` parameter controls what happens to existing instances on pages/templates. Default is "error" — the deletion is refused if instances exist, and the caller is returned the list of affected posts so they can choose how to proceed.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'component_id' => [
                'type' => 'string',
                'description' => 'Id of the component to delete.',
            ],
            'orphan_instances' => [
                'type' => 'string',
                'enum' => ['error', 'inline', 'remove'],
                'default' => 'error',
                'description' => 'error: refuse when instances exist (safe default). inline: replace each instance in place with a copy of the component body (fresh element ids), then delete the component. remove: delete every instance element alongside the component.',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Write even when another user currently holds the WordPress edit lock on this post (their builder or editor session is open). Without it such a write is refused with locked_by_other_user naming the user.',
            ],
        ],
        'required' => ['component_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'component_id' => ['type' => 'string'],
            'orphan_strategy' => ['type' => 'string'],
            'verified' => [
                'type' => 'boolean',
                'description' => 'True when every affected post read back the element tree that was written to it.',
            ],
            'unverified_posts' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'revision_ids' => [
                'type' => 'object',
                'description' => 'Pre-write revision id per affected post (null where revisions are disabled).',
            ],
            'affected_posts' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'post_id' => ['type' => 'integer'],
                        'post_title' => ['type' => 'string'],
                        'areas' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'instance_count' => ['type' => 'integer'],
                    ],
                ],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_delete_component',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Default "error" strategy is safe — it does not delete anything when instances exist, it just reports them. "inline" and "remove" are both destructive across every affected post: "inline" preserves the visible output by copying the component body into each instance location with freshly generated ids, "remove" erases every instance. Prefer checking `affected_posts` with the default strategy first, then re-running with a non-error strategy once the scope is understood.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

function bricks_list_components(array $input): array
{
    $components = comp_load_all();
    /** @var string $search */
    $search = strtolower((string) ($input['search'] ?? ''));
    $verbose = ($input['verbose'] ?? false) === true;

    $out = [];
    foreach ($components as $component) {
        $entry = $verbose ? comp_summarize_verbose($component) : comp_summarize_compact($component);
        if ($search !== '' && !comp_matches_search($entry, $search)) {
            continue;
        }
        $out[] = $entry;
    }

    return [
        'components' => $out,
        'total' => count($out),
    ];
}

/**
 * Generates ids for `elements` entries sent without one (the `elements`
 * branch of create-component only) and returns the amended input plus the
 * index => id map.
 *
 * @param array<array-key, mixed> $input
 * @return array{input: array<array-key, mixed>, generated_ids: array<string, string>}
 */
function comp_assign_missing_input_ids(array $input): array
{
    if (!is_array($input['elements'] ?? null) || $input['elements'] === []) {
        return ['input' => $input, 'generated_ids' => []];
    }

    /** @var list<array<string, mixed>> $raw */
    $raw = array_values($input['elements']);
    ['elements' => $elements, 'generated_ids' => $generated_ids] = bricks_assign_missing_ids($raw);
    $input['elements'] = $elements;

    return ['input' => $input, 'generated_ids' => $generated_ids];
}

function bricks_create_component(array $input): array|WP_Error
{
    $label = (string) $input['label'];
    ['input' => $input, 'generated_ids' => $generated_ids] = comp_assign_missing_input_ids($input);

    $prepared = comp_prepare_create_elements($input, $label);
    if (is_wp_error($prepared)) {
        return $prepared;
    }
    [
        'component_id' => $component_id,
        'elements' => $elements,
        'renamed' => $renamed,
        'warnings' => $warnings,
    ] = $prepared;

    /** @var list<array<string, mixed>> $raw_properties */
    $raw_properties = is_array($input['properties'] ?? null) ? $input['properties'] : [];
    $raw_properties = comp_remap_connection_ids($raw_properties, $renamed);
    $properties = comp_normalize_properties($raw_properties, comp_ids_in_elements($elements));
    if (is_wp_error($properties)) {
        return $properties;
    }

    comp_store_new_component($component_id, (string) ($input['category'] ?? ''), $elements, $properties);

    return comp_create_result($component_id, $label, $elements, $properties, [
        'generated_ids' => $generated_ids,
        'warnings' => array_merge($warnings, comp_property_type_warnings($properties)),
    ]);
}

/**
 * Resolves the create-component source, assigns a fresh component id, re-ids
 * the subtree, labels the root and validates / normalises the element tree.
 *
 * @param array<array-key, mixed> $input
 * @return array{component_id: string, elements: list<array<string, mixed>>, renamed: array<string, string>, warnings: list<string>}|WP_Error
 */
function comp_prepare_create_elements(array $input, string $label): array|WP_Error
{
    $source = comp_resolve_create_source($input);
    if (is_wp_error($source)) {
        return $source;
    }
    ['elements' => $raw_elements, 'root_id' => $root_id] = $source;

    $component_id = comp_generate_id(comp_existing_ids(comp_load_all()));
    ['elements' => $elements, 'renamed' => $renamed] = comp_reid_subtree($raw_elements, $root_id, $component_id);

    $root = $elements[0];
    $root['label'] = $label;
    $elements[0] = $root;

    $validation = bricks_validate_elements($elements);
    if ($validation['errors'] !== []) {
        return bricks_error('invalid_elements', implode(' ', $validation['errors']));
    }

    $elements = bricks_ensure_children($elements);
    $elements = bricks_resolve_css_selectors($elements);

    return [
        'component_id' => $component_id,
        'elements' => $elements,
        'renamed' => $renamed,
        'warnings' => array_merge($validation['warnings'], comp_renamed_id_warnings($renamed, [])),
    ];
}

/**
 * Appends a new component record to the stored component list.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<array<string, mixed>> $properties
 */
function comp_store_new_component(string $component_id, string $category, array $elements, array $properties): void
{
    $component = [
        'id' => $component_id,
        'category' => $category,
        'elements' => $elements,
        'properties' => $properties,
        '_created' => time(),
        '_user_id' => get_current_user_id(),
        '_version' => defined('BRICKS_VERSION') ? (string) constant('BRICKS_VERSION') : '',
    ];

    $all = comp_load_all();
    $all[] = $component;
    comp_save_all($all);
}

/**
 * @param list<array<string, mixed>> $elements
 * @param list<array<string, mixed>> $properties
 * @param array{generated_ids: array<string, string>, warnings: list<string>} $extras
 * @return array<string, mixed>
 */
function comp_create_result(
    string $component_id,
    string $label,
    array $elements,
    array $properties,
    array $extras,
): array {
    ['generated_ids' => $generated_ids, 'warnings' => $warnings] = $extras;
    $root = $elements[0];
    $result = [
        'component_id' => $component_id,
        'label' => $label,
        'root_name' => (string) ($root['name'] ?? ''),
        'element_count' => count($elements),
        'property_count' => count($properties),
    ];
    if ($generated_ids !== []) {
        $result['generated_ids'] = bricks_generated_ids_object($generated_ids);
    }
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }

    return $result;
}

function bricks_apply_component(array $input): array|WP_Error
{
    $post_id = (int) $input['post_id'];
    if (!get_post($post_id) instanceof WP_Post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
    }

    $component_id = (string) $input['component_id'];
    [$component] = comp_require($component_id);
    if ($component === null) {
        return bricks_error('component_not_found', sprintf('Component "%s" does not exist.', $component_id));
    }

    $root = comp_root_element($component);
    if ($root === null) {
        return bricks_error('invalid_component', sprintf(
            'Component "%s" is stored without a root element, so it cannot be placed. Give it one with novamira/bricks-edit-component (patch.elements, whose first entry carries the root id), then retry.',
            $component_id,
        ));
    }

    $area = (string) ($input['area'] ?? 'content');
    $elements = bricks_get_area_elements($post_id, $area);

    /** @var array<string, mixed>|null $position */
    $position = is_array($input['position'] ?? null) ? $input['position'] : null;
    [$insertion_index, $root_parent, $position_applied, $reference_id] = bricks_resolve_insert_position(
        $elements,
        $position,
    );
    if (is_wp_error($insertion_index)) {
        return $insertion_index;
    }

    /** @var array<string, mixed> $instance_properties */
    $instance_properties = is_array($input['properties'] ?? null) ? $input['properties'] : [];

    return comp_save_instance_into_area([
        'post_id' => $post_id,
        'area' => $area,
        'component_id' => $component_id,
        'root_name' => (string) ($root['name'] ?? 'block'),
        'elements' => $elements,
        'insertion_index' => $insertion_index,
        'root_parent' => $root_parent,
        'position_applied' => $position_applied,
        'reference_id' => $reference_id,
        'instance_properties' => $instance_properties,
        'force' => ($input['force'] ?? false) === true,
    ]);
}

/**
 * @param array{post_id: int, area: string, component_id: string, root_name: string, elements: list<array<string, mixed>>, insertion_index: int, root_parent: string|int, position_applied: string, reference_id: string, instance_properties: array<string, mixed>, force: bool} $ctx
 */
function comp_save_instance_into_area(array $ctx): array|WP_Error
{
    $insertion_index = $ctx['insertion_index'];
    $root_parent = $ctx['root_parent'];
    $position_applied = $ctx['position_applied'];
    $reference_id = $ctx['reference_id'];
    $instance_id = comp_generate_id(comp_ids_in_elements($ctx['elements']));
    $instance = [
        'id' => $instance_id,
        'name' => $ctx['root_name'],
        'cid' => $ctx['component_id'],
        'parent' => $root_parent,
        'settings' => [],
    ];
    if ($ctx['instance_properties'] !== []) {
        $instance['properties'] = $ctx['instance_properties'];
    }

    $elements = array_values(array_merge(
        array_slice($ctx['elements'], offset: 0, length: $insertion_index),
        [$instance],
        array_slice($ctx['elements'], offset: $insertion_index),
    ));

    $validation = bricks_validate_elements($elements);
    if ($validation['errors'] !== []) {
        return bricks_write_validation_error($ctx['post_id'], $ctx['area'], $validation['errors']);
    }

    $elements = bricks_ensure_children($elements);
    // The instance is a {id, cid} stub: nothing to sign.
    $commit = bricks_commit_area_write($ctx['post_id'], $ctx['area'], $elements, $validation['warnings'], [
        'sign' => false,
        'force' => $ctx['force'],
    ]);
    if ($commit instanceof WP_Error) {
        return $commit;
    }

    $result = bricks_with_write_verification([
        'instance_element_id' => $instance_id,
        'post_id' => $ctx['post_id'],
        'area' => $ctx['area'],
        'component_id' => $ctx['component_id'],
        'position_applied' => $position_applied,
        'parent' => $root_parent,
        'element_count' => count($commit['elements']),
    ], $commit);

    if ($reference_id !== '') {
        $result['reference_id'] = $reference_id;
    }
    if ($commit['warnings'] !== []) {
        $result['warnings'] = $commit['warnings'];
    }

    return $result;
}

function bricks_edit_component(array $input): array|WP_Error
{
    $component_id = (string) $input['component_id'];
    [$component, $index] = comp_require($component_id);
    if ($component === null || $index === null) {
        return bricks_error('component_not_found', sprintf('Component "%s" does not exist.', $component_id));
    }

    /** @var array<string, mixed> $patch */
    $patch = $input['patch'];
    $applied = comp_apply_patch($component, $patch, $component_id);
    if (is_wp_error($applied)) {
        return $applied;
    }

    $all = comp_load_all();
    $all[$index] = $applied['component'];
    comp_save_all($all);

    $result = [
        'component_id' => $component_id,
        'patched' => $applied['patched'],
    ];
    $warnings = $applied['warnings'];

    // A body change alters the CSS every page that uses this component
    // compiles, and a re-ID changes the class its markup carries. Both leave
    // the stored per-post CSS describing the previous body until the page is
    // written again, so the pages are refreshed here rather than waiting for
    // an unrelated edit.
    $patched_body = in_array('elements', $applied['patched'], strict: true);
    $refreshed = $patched_body ? comp_refresh_instance_css($component_id) : [];
    if ($patched_body) {
        // Declared as always present on a body patch: an empty list is a
        // clearer answer than an absent field for a caller checking scope.
        $result['affected_posts'] = $refreshed;
    }
    foreach (comp_renamed_id_warnings($applied['renamed'], $refreshed) as $warning) {
        $warnings[] = $warning;
    }
    $stale = comp_stale_css_warning($refreshed);
    if ($stale !== null) {
        $warnings[] = $stale;
    }

    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }
    return $result;
}

/**
 * Regenerates the stored CSS of every post that carries an instance of this
 * component, and reports what happened per post.
 *
 * Cost: one CSS generation per post that holds an instance, through the same
 * generator a builder save uses — the work a re-save of each page would do,
 * done once here instead. It is deliberately not capped: a partial refresh
 * would leave some pages styled by the previous body with nothing saying
 * which, which is the failure this replaces. `css_file` carries the outcome
 * per post ("regenerated", "inline" on a site that serves CSS inline, or
 * "skipped(...)" with the reason), so a caller can see the scope it paid for.
 *
 * @return list<array<string, mixed>>
 */
function comp_refresh_instance_css(string $component_id): array
{
    $instances = comp_find_instances($component_id);
    $affected = comp_summarize_affected_posts($instances);
    foreach ($affected as $index => $entry) {
        $post_id = (int) $entry['post_id'];
        $affected[$index]['css_file'] = bricks_regenerate_post_css($post_id, bricks_get_area_elements(
            $post_id,
            bricks_template_expected_area(bricks_css_content_type($post_id)),
        ));
    }

    return $affected;
}

/**
 * @param array<string, mixed> $component
 * @param array<string, mixed> $patch
 * @return array{component: array<string, mixed>, patched: list<string>, renamed: array<string, string>, warnings: list<string>}|WP_Error
 */
function comp_apply_patch(array $component, array $patch, string $component_id): array|WP_Error
{
    $patched_keys = [];
    $warnings = [];

    if (array_key_exists('category', $patch)) {
        $component['category'] = (string) $patch['category'];
        $patched_keys[] = 'category';
    }

    $body = comp_patch_body($component, $patch, $component_id);
    if (is_wp_error($body)) {
        return $body;
    }
    $component = $body['component'];
    foreach ($body['patched'] as $key) {
        $patched_keys[] = $key;
    }
    $warnings = $body['warnings'];

    if (array_key_exists('label', $patch)) {
        $with_label = comp_patch_label($component, (string) $patch['label']);
        if (is_wp_error($with_label)) {
            return $with_label;
        }
        $component = $with_label;
        $patched_keys[] = 'label';
    }

    return [
        'component' => $component,
        'patched' => $patched_keys,
        'renamed' => $body['renamed'],
        'warnings' => $warnings,
    ];
}

/**
 * Applies the `elements` and `properties` parts of a patch. The properties
 * branch runs after the elements branch on purpose: connections are validated
 * against the tree that will actually be saved. When only `elements` is
 * patched, the existing property connections are checked against the new tree.
 *
 * @param array<string, mixed> $component
 * @param array<string, mixed> $patch
 * @return array{component: array<string, mixed>, patched: list<string>, renamed: array<string, string>, warnings: list<string>}|WP_Error
 */
function comp_patch_body(array $component, array $patch, string $component_id): array|WP_Error
{
    $patched_keys = [];
    $warnings = [];
    $renamed = [];

    if (array_key_exists('elements', $patch)) {
        $with_elements = comp_patch_elements($component, $patch, $component_id);
        if (is_wp_error($with_elements)) {
            return $with_elements;
        }
        $component = $with_elements['component'];
        $warnings = $with_elements['warnings'];
        $patched_keys[] = 'elements';
        $renamed = $with_elements['renamed'];
        $patch = comp_remap_patch_connections($patch, $renamed);
    }

    $properties = comp_patch_property_half($component, $patch, $component_id);
    if (is_wp_error($properties)) {
        return $properties;
    }
    if ($properties !== null) {
        $component = $properties['component'];
        foreach ($properties['warnings'] as $w) {
            $warnings[] = $w;
        }
        $patched_keys[] = $properties['key'];

        return ['component' => $component, 'patched' => $patched_keys, 'renamed' => $renamed, 'warnings' => $warnings];
    }

    if (array_key_exists('elements', $patch)) {
        $dangling = comp_find_dangling_connections($component);
        if ($dangling !== null) {
            return $dangling;
        }
    }

    return ['component' => $component, 'patched' => $patched_keys, 'renamed' => $renamed, 'warnings' => $warnings];
}

/**
 * The `properties` / `add_properties` half of a patch, or null when the patch
 * carries neither. The two are mutually exclusive: one replaces the whole
 * property list, the other appends to it.
 *
 * @param  array<string, mixed> $component
 * @param  array<string, mixed> $patch
 * @return array{component: array<string, mixed>, key: string, warnings: list<string>}|WP_Error|null
 */
function comp_patch_property_half(array $component, array $patch, string $component_id): array|WP_Error|null
{
    $additive = array_key_exists('add_properties', $patch);
    $replacing = array_key_exists('properties', $patch);
    if ($additive && $replacing) {
        return bricks_error(
            'invalid_property',
            message: 'Pass either patch.properties (full replacement) or patch.add_properties (additive), not both.',
        );
    }
    if (!$additive && !$replacing) {
        return null;
    }

    $patched = $additive
        ? comp_patch_add_properties($component, $patch, $component_id)
        : comp_patch_properties($component, $patch, $component_id);
    if (is_wp_error($patched)) {
        return $patched;
    }

    return [
        'component' => $patched['component'],
        'key' => $additive ? 'add_properties' : 'properties',
        'warnings' => $patched['warnings'],
    ];
}

/**
 * Connections sent in the same call as an `elements` patch name the ids the
 * caller wrote, so they follow the tree through the same rename.
 *
 * @param  array<string, mixed> $patch
 * @param  array<string, string> $renamed
 * @return array<string, mixed>
 */
function comp_remap_patch_connections(array $patch, array $renamed): array
{
    foreach (['properties', 'add_properties'] as $half) {
        if (!is_array($patch[$half] ?? null)) {
            continue;
        }
        /** @var list<array<string, mixed>> $sent */
        $sent = $patch[$half];
        $patch[$half] = comp_remap_connection_ids($sent, $renamed);
    }

    return $patch;
}

/**
 * @param array<string, mixed> $component
 * @param array<string, mixed> $patch
 * @return array{component: array<string, mixed>, renamed: array<string, string>, warnings: list<string>}|WP_Error
 */
function comp_patch_elements(array $component, array $patch, string $component_id): array|WP_Error
{
    /** @var list<array<string, mixed>> $raw_elements */
    $raw_elements = $patch['elements'];
    if ($raw_elements === []) {
        return bricks_error('invalid_elements', message: 'patch.elements must contain at least one element.');
    }
    $root_id = (string) ($raw_elements[0]['id'] ?? '');
    if ($root_id === '') {
        return bricks_error(
            'invalid_elements',
            message: 'The first element in patch.elements has no id — it must be the component root.',
        );
    }
    ['elements' => $new_elements, 'renamed' => $renamed] = comp_reid_subtree($raw_elements, $root_id, $component_id);

    $current_label = comp_component_label($component);
    if (!array_key_exists('label', $patch) && $current_label !== '') {
        $new_elements[0]['label'] = $current_label;
    }

    $validation = bricks_validate_elements($new_elements);
    if ($validation['errors'] !== []) {
        return bricks_error('invalid_elements', implode(' ', $validation['errors']));
    }

    $new_elements = bricks_ensure_children($new_elements);
    $new_elements = bricks_resolve_css_selectors($new_elements);

    $component['elements'] = $new_elements;
    if (is_array($component['properties'] ?? null)) {
        /** @var list<array<string, mixed>> $stored_properties */
        $stored_properties = $component['properties'];
        $component['properties'] = comp_remap_connection_ids($stored_properties, $renamed);
    }

    return [
        'component' => $component,
        'renamed' => $renamed,
        'warnings' => $validation['warnings'],
    ];
}

/**
 * @param array<string, mixed> $component
 * @return array<string, mixed>|WP_Error
 */
function comp_patch_label(array $component, string $label): array|WP_Error
{
    $elements = is_array($component['elements'] ?? null) ? $component['elements'] : [];
    if (!is_array($elements[0] ?? null)) {
        return bricks_error(
            'invalid_component',
            message: 'This component is stored without a root element, so there is nothing to label. Give it one with novamira/bricks-edit-component (patch.elements, whose first entry carries the root id), then set the label.',
        );
    }
    /** @var array<string, mixed> $root */
    $root = $elements[0];
    $root['label'] = $label;
    $elements[0] = $root;
    $component['elements'] = $elements;
    return $component;
}

/**
 * Full-replace (PUT) of the component property list. Ids passed by the caller
 * are preserved so instance overrides keyed by property id keep matching;
 * omitted ids are generated. Connections are validated against the element
 * tree currently on `$component` — the caller applies `patch.elements` first.
 *
 * @param array<string, mixed> $component
 * @param array<string, mixed> $patch
 * @return array{component: array<string, mixed>, warnings: list<string>}|WP_Error
 */
function comp_patch_properties(array $component, array $patch, string $component_id): array|WP_Error
{
    /** @var mixed $raw */
    $raw = $patch['properties'];
    if (!is_array($raw)) {
        return bricks_error('invalid_property', message: 'patch.properties must be a list of property definitions.');
    }
    /** @var list<array<string, mixed>> $raw_properties */
    $raw_properties = array_values($raw);

    /** @var list<array<string, mixed>> $elements */
    $elements = is_array($component['elements'] ?? null) ? $component['elements'] : [];
    $duplicate_id = comp_find_duplicate_property_id($raw_properties);
    if ($duplicate_id !== null) {
        return bricks_error('invalid_property', sprintf(
            'Property id "%s" is used more than once — property ids must be unique within a component.',
            $duplicate_id,
        ));
    }
    $stored_properties = comp_component_properties($component);
    $stored_by_id = comp_properties_by_id($stored_properties);
    $normalized = comp_normalize_properties($raw_properties, comp_ids_in_elements($elements), $stored_by_id);
    if (is_wp_error($normalized)) {
        return $normalized;
    }

    $warnings = comp_property_type_warnings($normalized);

    $warnings = array_merge($warnings, comp_nested_connection_change_warnings(
        $normalized,
        $stored_by_id,
        $component_id,
    ));

    $removed_ids = array_values(array_diff(comp_property_ids($stored_properties), comp_property_ids($normalized)));
    foreach (comp_count_instance_overrides($component_id, $removed_ids) as $property_id => $count) {
        $warnings[] = sprintf(
            '%d instance(s) override removed property "%s" — those overrides no longer apply.',
            $count,
            $property_id,
        );
    }

    $component['properties'] = $normalized;
    return ['component' => $component, 'warnings' => $warnings];
}

/**
 * Additive property patch: the new definitions are validated on their own
 * (connections against the tree in effect), refused when an id already
 * exists, and appended to the stored list. A definition without a type is
 * stored as "text" and reported; without a default while instances exist,
 * the affected pages are named so the caller can decide.
 *
 * @param array<string, mixed> $component
 * @param array<string, mixed> $patch
 * @return array{component: array<string, mixed>, warnings: list<string>}|WP_Error
 */
function comp_patch_add_properties(array $component, array $patch, string $component_id): array|WP_Error
{
    /** @var mixed $raw */
    $raw = $patch['add_properties'];
    if (!is_array($raw) || $raw === []) {
        return bricks_error(
            'invalid_property',
            message: 'patch.add_properties must be a non-empty list of property definitions.',
        );
    }
    /** @var list<array<string, mixed>> $raw_properties */
    $raw_properties = array_values($raw);
    $stored_properties = comp_component_properties($component);
    $stored_by_id = comp_properties_by_id($stored_properties);

    $refused = comp_find_existing_property_id($raw_properties, $stored_by_id);
    if ($refused !== null) {
        return bricks_error('invalid_property', sprintf(
            'Property id "%s" already exists on component "%s" — add_properties only appends; edit it through patch.properties.',
            $refused,
            $component_id,
        ));
    }
    $duplicate_id = comp_find_duplicate_property_id($raw_properties);
    if ($duplicate_id !== null) {
        return bricks_error('invalid_property', sprintf(
            'Property id "%s" is used more than once in add_properties.',
            $duplicate_id,
        ));
    }

    /** @var list<array<string, mixed>> $elements */
    $elements = is_array($component['elements'] ?? null) ? $component['elements'] : [];
    $normalized = comp_normalize_properties($raw_properties, comp_ids_in_elements($elements), $stored_by_id);
    if (is_wp_error($normalized)) {
        return $normalized;
    }

    $warnings = array_merge(
        comp_property_type_warnings($normalized),
        comp_added_property_warnings($raw_properties, $normalized, $component_id),
        comp_nested_connection_change_warnings($normalized, $stored_by_id, $component_id),
    );
    $component['properties'] = array_merge($stored_properties, $normalized);

    return ['component' => $component, 'warnings' => $warnings];
}

/**
 * First supplied id that a stored property already uses, or null.
 *
 * @param list<array<string, mixed>>          $raw
 * @param array<string, array<string, mixed>> $stored_by_id
 */
function comp_find_existing_property_id(array $raw, array $stored_by_id): ?string
{
    foreach ($raw as $entry) {
        $id = (string) ($entry['id'] ?? '');
        if ($id !== '' && isset($stored_by_id[$id])) {
            return $id;
        }
    }

    return null;
}

/**
 * Warnings for partial additive entries: a missing type (stored as text)
 * and a missing default while instances exist (naming the pages).
 *
 * @param list<array<string, mixed>> $raw
 * @param list<array<string, mixed>> $normalized
 * @return list<string>
 */
function comp_added_property_warnings(array $raw, array $normalized, string $component_id): array
{
    $warnings = [];
    foreach ($raw as $index => $entry) {
        if (array_key_exists('type', $entry)) {
            continue;
        }
        $warnings[] = sprintf(
            'Property "%s" was added without a type — stored as "text". Pass type explicitly for a new property.',
            (string) ($normalized[$index]['id'] ?? ''),
        );
    }
    $instances = comp_find_instances($component_id);
    $instance_count = comp_total_instance_count($instances);
    if ($instance_count === 0) {
        return $warnings;
    }
    $posts = array_map(static fn(array $row): string => sprintf(
        '%s (#%d)',
        (string) ($row['post_title'] ?? ''),
        (int) ($row['post_id'] ?? 0),
    ), comp_summarize_affected_posts($instances));
    foreach ($raw as $index => $entry) {
        if (array_key_exists('default', $entry)) {
            continue;
        }
        $warnings[] = sprintf(
            'Property "%s" has no default while %d instance(s) of this component exist on %s — those instances render an empty value until each one gets an override or the property gets a default.',
            (string) ($normalized[$index]['id'] ?? ''),
            $instance_count,
            implode(', ', $posts),
        );
    }

    return $warnings;
}

/**
 * Warn when a new property, or an existing property that used only top-level
 * targets, introduces nested connection semantics for existing instances.
 *
 * @param list<array<string, mixed>>              $properties
 * @param array<string, array<string, mixed>>     $stored_by_id
 * @return list<string>
 */
function comp_nested_connection_change_warnings(array $properties, array $stored_by_id, string $component_id): array
{
    $changes = [];
    foreach ($properties as $property) {
        $property_id = (string) ($property['id'] ?? '');
        $stored_property = $stored_by_id[$property_id] ?? null;
        if (
            comp_property_has_nested_connection($property)
            && ($stored_property === null || !comp_property_has_nested_connection($stored_property))
        ) {
            $changes[$property_id] = $stored_property === null;
        }
    }
    if ($changes === []) {
        return [];
    }

    $instance_count = comp_total_instance_count(comp_find_instances($component_id));
    if ($instance_count === 0) {
        return [];
    }

    $warnings = [];
    foreach ($changes as $property_id => $is_new_property) {
        $warnings[] = sprintf(
            $is_new_property
                ? 'New property "%s" has a nested connection, and this component already has %d instance(s). When an instance has no override, Bricks removes the nested setting and does not consult the property default.'
                : 'Property "%s" now has a nested connection, and this component already has %d instance(s). When an instance has no override, Bricks removes the nested setting and does not consult the property default.',
            $property_id,
            $instance_count,
        );
    }
    return $warnings;
}

/**
 * Guard for `patch.elements` without `patch.properties`: every existing
 * property connection must still target an element id present in the new
 * tree. Bricks silently skips connections whose element id does not exist,
 * so a dangling one would make the property stop doing anything.
 *
 * @param array<string, mixed> $component
 */
function comp_find_dangling_connections(array $component): ?WP_Error
{
    /** @var list<array<string, mixed>> $elements */
    $elements = is_array($component['elements'] ?? null) ? $component['elements'] : [];
    $valid_ids = comp_ids_in_elements($elements);

    foreach (comp_component_properties($component) as $property) {
        $property_id = (string) ($property['id'] ?? '');
        $connections = comp_property_connections($property);
        foreach (array_keys($connections) as $target_id) {
            $target_id = (string) $target_id;
            if (array_key_exists($target_id, $valid_ids)) {
                continue;
            }
            return bricks_error('dangling_connection', sprintf(
                'Property "%s" connects to element "%s", which is not present in the new element tree. Include patch.properties in the same call to re-target or drop that connection.',
                $property_id,
                $target_id,
            ));
        }
    }
    return null;
}

/**
 * Counts, per removed property id, how many placed instances of the component
 * carry an override for it. Loads each affected area once; skipped entirely
 * when nothing was removed.
 *
 * @param list<string> $removed_ids
 * @return array<string, int>
 */
function comp_count_instance_overrides(string $component_id, array $removed_ids): array
{
    if ($removed_ids === []) {
        return [];
    }
    $counts = [];
    foreach (comp_find_instances($component_id) as $post_id => $area_to_ids) {
        foreach ($area_to_ids as $area => $instance_ids) {
            $targets = array_flip($instance_ids);
            foreach (bricks_get_area_elements($post_id, $area) as $element) {
                if (!array_key_exists((string) ($element['id'] ?? ''), $targets)) {
                    continue;
                }
                $overrides = is_array($element['properties'] ?? null) ? $element['properties'] : [];
                foreach ($removed_ids as $removed_id) {
                    if (!array_key_exists($removed_id, $overrides)) {
                        continue;
                    }
                    $counts[$removed_id] = ($counts[$removed_id] ?? 0) + 1;
                }
            }
        }
    }
    return $counts;
}

function bricks_delete_component(array $input): array|WP_Error
{
    $component_id = (string) $input['component_id'];
    $strategy = (string) ($input['orphan_instances'] ?? 'error');

    [$component, $index] = comp_require($component_id);
    if ($component === null || $index === null) {
        return bricks_error('component_not_found', sprintf('Component "%s" does not exist.', $component_id));
    }

    $instances = comp_find_instances($component_id);
    $affected_posts = comp_summarize_affected_posts($instances);

    if ($instances !== [] && $strategy === 'error') {
        return bricks_error(
            'instances_exist',
            sprintf(
                'Component "%s" has %d instance(s) across %d post(s). Re-run with orphan_instances="inline" to expand them into the pages, or "remove" to delete them alongside the component.',
                $component_id,
                comp_total_instance_count($instances),
                count($instances),
            ),
            ['affected_posts' => $affected_posts],
        );
    }

    $rewritten = comp_rewrite_instance_areas($instances, $component, $strategy, [
        'force' => ($input['force'] ?? false) === true,
    ]);
    if ($rewritten instanceof WP_Error) {
        return $rewritten;
    }
    ['unverified_posts' => $unverified_posts, 'revision_ids' => $revision_ids] = $rewritten;

    $all = comp_load_all();
    array_splice($all, $index, length: 1);
    comp_save_all($all);

    $result = [
        'deleted' => true,
        'component_id' => $component_id,
        'orphan_strategy' => $strategy,
        'affected_posts' => $affected_posts,
        'verified' => $unverified_posts === [],
        'revision_ids' => (object) $revision_ids,
    ];
    if ($unverified_posts !== []) {
        $result['unverified_posts'] = array_values(array_unique($unverified_posts));
        $result['warnings'] = [
            'The element tree read back from these posts does not match what was written; re-read them with novamira/bricks-get-content (summary: true) before continuing.',
        ];
    }

    return $result;
}

/**
 * Rewrites every area that holds an instance of the component — inlining the
 * component body or removing the instance — through the shared write
 * pipeline (lock check, pre-write revision, read-back, CSS file). Returns
 * the posts whose read-back did not match and the revision id per post.
 *
 * @param array<int, array<string, list<string>>> $instances post id => area => instance element ids
 * @param array<string, mixed> $component
 * @param array{force?: bool}  $options
 * @return array{unverified_posts: list<int>, revision_ids: array<string, int|null>}|WP_Error
 */
function comp_rewrite_instance_areas(
    array $instances,
    array $component,
    string $strategy,
    array $options,
): array|WP_Error {
    $unverified_posts = [];
    $revision_ids = [];
    $root = comp_root_element($component);
    foreach ($instances as $post_id => $area_to_ids) {
        foreach ($area_to_ids as $area => $ids) {
            $elements = bricks_get_area_elements($post_id, $area);
            $elements = $strategy === 'inline'
                ? comp_inline_instances_in_area($elements, $ids, $component, $root)
                : comp_remove_instances_in_area($elements, $ids);
            $elements = bricks_ensure_children($elements);
            // Inlined bodies carry the component's own signatures; nothing new to sign.
            $commit = bricks_commit_area_write(
                $post_id,
                $area,
                $elements,
                [],
                [
                    'sign' => false,
                    'set_editor_mode' => false,
                    'force' => ($options['force'] ?? false) === true,
                ],
            );
            if ($commit instanceof WP_Error) {
                return $commit;
            }
            $revision_ids[(string) $post_id] = $commit['revision_id'];
            if (!$commit['verified']) {
                $unverified_posts[] = $post_id;
            }
        }
    }

    return ['unverified_posts' => array_values(array_unique($unverified_posts)), 'revision_ids' => $revision_ids];
}

// -- Helpers ----------------------------------------------------------------

/**
 * @return list<array<string, mixed>>
 */
function comp_load_all(): array
{
    /** @var mixed $stored */
    $stored = get_option(BRICKS_DB_COMPONENTS, default_value: []);
    if (!is_array($stored)) {
        return [];
    }

    /** @var list<array<string, mixed>> */
    return array_values(array_filter($stored, static fn(mixed $c): bool => is_array($c)));
}

/**
 * @param list<array<string, mixed>> $components
 */
function comp_save_all(array $components): void
{
    update_option(BRICKS_DB_COMPONENTS, $components);
}

/**
 * @return array{0: array<string, mixed>|null, 1: int|null}
 */
function comp_require(string $component_id): array
{
    $all = comp_load_all();
    foreach ($all as $i => $component) {
        if ((string) ($component['id'] ?? '') === $component_id) {
            return [$component, $i];
        }
    }
    return [null, null];
}

/**
 * @param list<array<string, mixed>> $components
 * @return array<string, true>
 */
function comp_existing_ids(array $components): array
{
    $ids = [];
    foreach ($components as $component) {
        $id = (string) ($component['id'] ?? '');
        if ($id !== '') {
            $ids[$id] = true;
        }
        /** @var list<array<string, mixed>> $elements */
        $elements = is_array($component['elements'] ?? null) ? $component['elements'] : [];
        foreach ($elements as $element) {
            $eid = (string) ($element['id'] ?? '');
            if ($eid !== '') {
                $ids[$eid] = true;
            }
        }
    }
    return $ids;
}

/**
 * @param list<array<string, mixed>> $elements
 * @return array<string, true>
 */
function comp_ids_in_elements(array $elements): array
{
    $ids = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        if ($id !== '') {
            $ids[$id] = true;
        }
    }
    return $ids;
}

/**
 * Normalises the user-supplied property list into the shape Bricks stores on
 * a component. Fills in missing ids and type (`text` for new properties),
 * keeps `default` as supplied, and validates the fields this ability owns. On
 * edit, the optional `$stored_by_id` map preserves fields this ability does
 * not know about, plus existing `options` when the caller omits it. The
 * presence-based `replace` flag is canonicalized separately: only true is
 * stored. This keeps a verbose-list -> full-replacement-edit round trip
 * forward-compatible.
 * Every `connections` target must exist in the component tree — a connection
 * with no element to drive has nothing to apply, so it is refused up front
 * rather than stored.
 *
 * A supplied id that repeats an earlier one is replaced by a generated id
 * (create semantics). The edit path rejects duplicates up-front through
 * {@see comp_find_duplicate_property_id()} because silently renaming a
 * property there would detach the instance overrides keyed by it.
 *
 * @param list<array<string, mixed>> $raw
 * @param array<string, true>        $valid_element_ids
 * @param array<string, array<string, mixed>> $stored_by_id
 * @return list<array<string, mixed>>|WP_Error
 */
function comp_normalize_properties(array $raw, array $valid_element_ids, array $stored_by_id = []): array|WP_Error
{
    $normalized = [];
    $used_ids = [];
    $taken_ids = array_fill_keys(keys: array_keys($stored_by_id), value: true);
    foreach ($raw as $index => $entry) {
        $label = (string) ($entry['label'] ?? '');
        if ($label === '') {
            return bricks_error('invalid_property', sprintf('Property at index %d has no label.', $index));
        }
        /** @var array<string, mixed> $raw_connections */
        $raw_connections = comp_property_connections($entry);
        $connections = comp_validate_property_connections($raw_connections, $valid_element_ids, $label);
        if (is_wp_error($connections)) {
            return $connections;
        }
        $prop_id = (string) ($entry['id'] ?? '');
        if ($prop_id === '' || array_key_exists($prop_id, $used_ids)) {
            $prop_id = comp_generate_id($used_ids + $taken_ids);
        }
        $used_ids[$prop_id] = true;
        $property = comp_normalize_property_fields(
            $entry,
            $stored_by_id[$prop_id] ?? [],
            $prop_id,
            $label,
            $connections,
        );
        if (is_wp_error($property)) {
            return $property;
        }
        $normalized[] = $property;
    }
    return $normalized;
}

/**
 * Apply the fields this ability owns over an existing stored property. Fields
 * outside this set stay untouched so newer Bricks data survives an edit.
 *
 * @param array<string, mixed>        $entry
 * @param array<string, mixed>        $stored
 * @param array<string, list<string>> $connections
 * @return array<string, mixed>|WP_Error
 */
function comp_normalize_property_fields(
    array $entry,
    array $stored,
    string $prop_id,
    string $label,
    array $connections,
): array|WP_Error {
    $property = $stored;
    $property['id'] = $prop_id;
    $property['label'] = $label;
    if (!array_key_exists('type', $entry) && !array_key_exists('type', $property)) {
        $property['type'] = 'text';
    }
    if (array_key_exists('type', $entry)) {
        $property['type'] = (string) $entry['type'];
    }

    // Keep an explicit default exactly as supplied, including null and its
    // existing key position. Omitting it remains the supported way to clear a
    // stored default.
    if (!array_key_exists('default', $entry)) {
        unset($property['default']);
    }
    if (array_key_exists('default', $entry)) {
        $property['default'] = $entry['default'];
    }

    if (array_key_exists('options', $entry)) {
        $options = comp_validate_property_options($entry['options'], $label);
        if (is_wp_error($options)) {
            return $options;
        }
        // Instances of a class property store the option id, so every
        // class option needs one; select options are kept as given.
        $property['options'] = ($property['type'] ?? '') === 'class' ? comp_options_with_ids($options) : $options;
    }

    $property = comp_normalize_property_flags($entry, $property, $label);
    if ($property instanceof WP_Error) {
        return $property;
    }

    $property['connections'] = $connections;
    return $property;
}

/**
 * The presence-only flags of a property (`replace`, `multiple`): the builder
 * never stores a falsy flag, so only true is kept and anything else removes
 * the key.
 *
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $property
 * @return array<string, mixed>|WP_Error
 */
function comp_normalize_property_flags(array $entry, array $property, string $label): array|WP_Error
{
    foreach (['replace', 'multiple'] as $flag) {
        if (!array_key_exists($flag, $entry)) {
            continue;
        }
        if (!is_bool($entry[$flag])) {
            return bricks_error('invalid_property', sprintf('Property "%s" %s must be a boolean.', $label, $flag));
        }
        unset($property[$flag]);
        if ($entry[$flag] === true) {
            $property[$flag] = true;
        }
    }
    if (!array_key_exists('replace', $entry)) {
        // Omitting replace clears it (merge behaviour), as before.
        unset($property['replace']);
    }

    return $property;
}

/**
 * Returns the first supplied property id that appears more than once in the
 * raw list, or null when every supplied id is unique.
 *
 * @param list<array<string, mixed>> $raw
 */
function comp_find_duplicate_property_id(array $raw): ?string
{
    $seen = [];
    foreach ($raw as $entry) {
        $prop_id = (string) ($entry['id'] ?? '');
        if ($prop_id === '') {
            continue;
        }
        if (array_key_exists($prop_id, $seen)) {
            return $prop_id;
        }
        $seen[$prop_id] = true;
    }
    return null;
}

/**
 * @param array<string, mixed> $raw_connections
 * @param array<string, true>  $valid_element_ids
 * @return array<string, list<string>>|WP_Error
 */
function comp_validate_property_connections(
    array $raw_connections,
    array $valid_element_ids,
    string $label,
): array|WP_Error {
    if ($raw_connections === []) {
        return bricks_error('invalid_property', sprintf(
            'Property "%s" has no connections — a property must target at least one element/setting.',
            $label,
        ));
    }
    $out = [];
    foreach (array_keys($raw_connections) as $target_id) {
        /** @var mixed $keys */
        $keys = $raw_connections[$target_id];
        if (!array_key_exists($target_id, $valid_element_ids)) {
            return bricks_error('invalid_property', sprintf(
                'Property "%s" connects to unknown element id "%s". Connections must reference elements within the component body.',
                $label,
                $target_id,
            ));
        }
        if (!is_array($keys)) {
            return bricks_error('invalid_property', sprintf(
                'Property "%s" connection for element "%s" must be a list of setting keys.',
                $label,
                $target_id,
            ));
        }
        if ($keys === []) {
            return bricks_error('invalid_property', sprintf(
                'Property "%s" connection for element "%s" must contain at least one setting key.',
                $label,
                $target_id,
            ));
        }
        $out[$target_id] = array_values(array_map(static fn(mixed $k): string => (string) $k, $keys));
    }
    return $out;
}

/**
 * Validate one property's explicit option list while retaining any additional
 * option fields Bricks or an extension may have stored.
 *
 * @return list<array<string, mixed>>|WP_Error
 */
function comp_validate_property_options(mixed $raw_options, string $label): array|WP_Error
{
    if (!is_array($raw_options) || array_keys($raw_options) !== array_keys(array_values($raw_options))) {
        return bricks_error('invalid_property', sprintf(
            'Property "%s" options must be a list of {value, label} objects.',
            $label,
        ));
    }

    $options = [];
    foreach (array_keys($raw_options) as $index) {
        /** @var mixed $option */
        $option = $raw_options[$index];
        if (!is_array($option) || !array_key_exists('value', $option) || !is_string($option['label'] ?? null)) {
            return bricks_error('invalid_property', sprintf(
                'Property "%s" option at index %d must contain value and a string label.',
                $label,
                $index,
            ));
        }
        /** @var array<string, mixed> $option */
        $options[] = $option;
    }

    return $options;
}

/**
 * Gives every option without a string id a generated one, unique within the
 * list (instances of a class property store the option id).
 *
 * @param list<array<string, mixed>> $options
 * @return list<array<string, mixed>>
 */
function comp_options_with_ids(array $options): array
{
    $taken = [];
    foreach ($options as $option) {
        if (!is_string($option['id'] ?? null) || $option['id'] === '') {
            continue;
        }
        $taken[$option['id']] = true;
    }
    foreach ($options as $index => $option) {
        if (is_string($option['id'] ?? null) && $option['id'] !== '') {
            continue;
        }
        $id = comp_generate_id($taken);
        $taken[$id] = true;
        $options[$index] = ['id' => $id] + $option;
    }

    return $options;
}

/**
 * Input schema for a list of property definitions, shared by create and edit.
 *
 * @return array<string, mixed>
 */
function comp_properties_input_schema(string $description): array
{
    return [
        'type' => 'array',
        'description' =>
            $description
                . ' Supported types: '
                . implode(', ', COMP_KNOWN_PROPERTY_TYPES)
                . '. Other values are stored as-is but reported as a warning because the Bricks property panel has no control for them.',
        'items' => [
            'type' => 'object',
            'properties' => [
                'id' => [
                    'type' => 'string',
                    'description' => 'Stable property id used by instance overrides. Auto-generated when omitted.',
                ],
                'label' => ['type' => 'string'],
                'type' => [
                    'type' => 'string',
                    'description' => 'Control type shown in the Bricks property panel. Required for a new property (a missing type is stored as "text" and reported); omit it on an existing property to keep the stored type.',
                ],
                'multiple' => [
                    'type' => 'boolean',
                    'description' => 'select / class properties: allow several values per instance. Only true is stored; false or omission removes the flag.',
                ],
                'default' => [
                    'type' => ['string', 'number', 'integer', 'boolean', 'array', 'object', 'null'],
                    'description' => 'Default value used when an instance does not override the property.',
                ],
                'connections' => [
                    'type' => 'object',
                    'description' => 'Map of element id -> list of setting keys that receive this property value. Use a colon sub-key for one level of nesting (e.g. "link:url" for settings.link.url).',
                    'additionalProperties' => [
                        'type' => 'array',
                        'minItems' => 1,
                        'items' => ['type' => 'string'],
                    ],
                ],
                'options' => [
                    'type' => 'array',
                    'description' => 'Choices for class or select properties: {id?, label, value}. For a class property value is the list of global class ids the choice applies and an instance stores the option id; ids are generated when omitted. Omit this field on an existing property to keep its stored options; pass an empty list to clear them.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => [
                                'type' => 'string',
                                'description' => 'Stable option id (generated when omitted); instances of a class property store it.',
                            ],
                            'value' => [
                                'type' => ['string', 'number', 'integer', 'boolean', 'array', 'object', 'null'],
                            ],
                            'label' => ['type' => 'string'],
                        ],
                        'required' => ['value', 'label'],
                        'additionalProperties' => true,
                    ],
                ],
                'replace' => [
                    'type' => 'boolean',
                    'description' => 'Class-property replacement flag. Only true is stored and replaces the element\'s own global classes. False or omission removes the stored flag so property classes merge with the element\'s own classes.',
                ],
            ],
            'required' => ['label', 'connections'],
            'additionalProperties' => true,
        ],
    ];
}

/**
 * @param list<array<string, mixed>> $properties
 * @return list<string>
 */
function comp_property_type_warnings(array $properties): array
{
    $warnings = [];
    foreach ($properties as $property) {
        $type = (string) ($property['type'] ?? '');
        if (in_array($type, COMP_KNOWN_PROPERTY_TYPES, strict: true)) {
            continue;
        }
        $warnings[] = sprintf(
            'Property "%s" uses type "%s", which is not one of the types the Bricks property panel offers (%s). It is stored as-is but may not be editable in the builder.',
            (string) ($property['id'] ?? ''),
            $type,
            implode(', ', COMP_KNOWN_PROPERTY_TYPES),
        );
    }
    return $warnings;
}

/**
 * @param array<string, mixed> $component
 * @return list<array<string, mixed>>
 */
function comp_component_properties(array $component): array
{
    /** @var mixed $properties */
    $properties = $component['properties'] ?? null;
    if (!is_array($properties)) {
        return [];
    }
    /** @var list<array<string, mixed>> */
    return array_values(array_filter($properties, static fn(mixed $p): bool => is_array($p)));
}

/**
 * @param list<array<string, mixed>> $properties
 * @return array<string, array<string, mixed>>
 */
function comp_properties_by_id(array $properties): array
{
    $by_id = [];
    foreach ($properties as $property) {
        $id = (string) ($property['id'] ?? '');
        if ($id !== '') {
            $by_id[$id] = $property;
        }
    }
    return $by_id;
}

/**
 * Whether a property targets a colon-delimited nested setting.
 *
 * @param array<string, mixed> $property
 */
function comp_property_has_nested_connection(array $property): bool
{
    $connections = comp_property_connections($property);
    foreach (array_keys($connections) as $target_id) {
        /** @var mixed $setting_keys */
        $setting_keys = $connections[$target_id];
        if (!is_array($setting_keys)) {
            continue;
        }
        foreach (array_keys($setting_keys) as $index) {
            /** @var mixed $setting_key */
            $setting_key = $setting_keys[$index];
            if (str_contains((string) $setting_key, ':')) {
                return true;
            }
        }
    }
    return false;
}

/**
 * @param list<array<string, mixed>> $properties
 * @return list<string>
 */
function comp_property_ids(array $properties): array
{
    $ids = [];
    foreach ($properties as $property) {
        $id = (string) ($property['id'] ?? '');
        if ($id !== '') {
            $ids[] = $id;
        }
    }
    return $ids;
}

/**
 * Lossless view of stored property definitions for verbose list output.
 *
 * @param array<string, mixed> $component
 * @return list<array<string, mixed>>
 */
function comp_summarize_properties(array $component): array
{
    $out = [];
    foreach (comp_component_properties($component) as $property) {
        /** @var array<string, mixed> $shaped */
        $shaped = comp_property_for_output($property);
        $out[] = $shaped;
    }

    return $out;
}

/**
 * One stored property definition, shaped for the wire.
 *
 * `connections` is keyed by ELEMENT ID and declared an object, but a PHP array
 * key folds a canonical decimal string to an integer — so a property connected
 * to an element whose id is "0" (and "1", … contiguous) is a LIST, and
 * json_encode emits `[["text"]]` instead of `{"0":["text"]}`. The element-id
 * identity is then gone from the response, and the list is documented as
 * passing back losslessly to bricks-edit-component. Casting keeps the keys in
 * every case and changes nothing else about the entry.
 *
 * @param  array<string, mixed> $property
 * @return array<string, mixed>
 */
/**
 * A property's connections map as an array, from either shape it can arrive
 * in: the array a JSON request decodes to, or the object this surface emits
 * (see {@see comp_property_for_output()}). The lossless round trip the list
 * ability documents — read the properties, hand them straight back — is a
 * real caller path in-process as well as over the wire, and an emitted object
 * that the input path refused would break exactly that.
 *
 * @param  array<string, mixed> $property
 * @return array<array-key, mixed>
 */
function comp_property_connections(array $property): array
{
    /** @var mixed $connections */
    $connections = $property['connections'] ?? null;
    if (is_object($connections)) {
        $connections = get_object_vars($connections);
    }

    return is_array($connections) ? $connections : [];
}

function comp_property_for_output(array $property): array
{
    /** @var mixed $connections */
    $connections = $property['connections'] ?? null;
    if (is_array($connections)) {
        $property['connections'] = (object) $connections;
    }

    return $property;
}

/**
 * Component / element id that does not collide with any id in `$taken`
 * (shared generator, Bricks' 6-char hex format).
 *
 * @param array<string, true> $taken
 */
function comp_generate_id(array $taken): string
{
    return bricks_generate_element_id($taken);
}

/**
 * @param array<string, mixed> $component
 */
function comp_component_label(array $component): string
{
    $root = comp_root_element($component);
    if ($root === null) {
        return '';
    }
    return (string) ($root['label'] ?? '');
}

/**
 * @param array<string, mixed> $component
 * @return array<string, mixed>|null
 */
function comp_root_element(array $component): ?array
{
    /** @var mixed $elements */
    $elements = $component['elements'] ?? null;
    if (!is_array($elements)) {
        return null;
    }
    /** @var mixed $first */
    $first = $elements[0] ?? null;
    if (!is_array($first)) {
        return null;
    }
    /** @var array<string, mixed> */
    return $first;
}

/**
 * @param array<string, mixed> $component
 * @return array<string, mixed>
 */
function comp_summarize_compact(array $component): array
{
    $root = comp_root_element($component);
    $label = comp_component_label($component);
    if ($label === '' && $root !== null) {
        $label = (string) ($root['name'] ?? '');
    }
    $elements = is_array($component['elements'] ?? null) ? $component['elements'] : [];

    return [
        'id' => (string) ($component['id'] ?? ''),
        'label' => $label,
        'category' => (string) ($component['category'] ?? ''),
        'root_name' => $root === null ? '' : (string) ($root['name'] ?? ''),
        'element_count' => count($elements),
        'property_count' => count(comp_component_properties($component)),
    ];
}

/**
 * @param array<string, mixed> $component
 * @return array<string, mixed>
 */
function comp_summarize_verbose(array $component): array
{
    $summary = comp_summarize_compact($component);
    $summary['elements'] = is_array($component['elements'] ?? null) ? $component['elements'] : [];
    $summary['properties'] = comp_summarize_properties($component);
    return $summary;
}

/**
 * @param array<string, mixed> $entry
 */
function comp_matches_search(array $entry, string $needle): bool
{
    foreach (['label', 'category', 'id', 'root_name'] as $field) {
        $value = strtolower((string) ($entry[$field] ?? ''));
        if ($value !== '' && str_contains($value, $needle)) {
            return true;
        }
    }
    return false;
}

/**
 * Resolves the `elements` vs `from_post_id`/`from_element_id` input into a
 * concrete subtree. Returns the raw elements plus the id of the element that
 * should be treated as the root.
 *
 * @param array<array-key, mixed> $input
 * @return array{elements: list<array<string, mixed>>, root_id: string}|WP_Error
 */
function comp_resolve_create_source(array $input): array|WP_Error
{
    $has_elements = is_array($input['elements'] ?? null) && $input['elements'] !== [];
    $has_from = array_key_exists('from_post_id', $input) || array_key_exists('from_element_id', $input);

    if ($has_elements && $has_from) {
        return bricks_error(
            'ambiguous_source',
            message: 'Provide exactly one of: elements, or from_post_id + from_element_id.',
        );
    }
    if (!$has_elements && !$has_from) {
        return bricks_error(
            'missing_source',
            message: 'Provide either an `elements` tree or from_post_id + from_element_id.',
        );
    }

    if ($has_elements) {
        /** @var list<array<string, mixed>> $elements */
        $elements = $input['elements'];
        $root_id = (string) ($elements[0]['id'] ?? '');
        if ($root_id === '') {
            return bricks_error(
                'invalid_elements',
                message: 'The first element must carry an id to act as the component root.',
            );
        }
        return ['elements' => array_values($elements), 'root_id' => $root_id];
    }

    if (!array_key_exists('from_post_id', $input) || !array_key_exists('from_element_id', $input)) {
        return bricks_error(
            'missing_source',
            message: 'from_post_id and from_element_id must both be provided together.',
        );
    }

    $from_post_id = (int) $input['from_post_id'];
    $from_element_id = (string) $input['from_element_id'];
    $area = (string) ($input['from_area'] ?? 'content');

    if (!get_post($from_post_id) instanceof WP_Post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $from_post_id));
    }

    $elements = bricks_get_area_elements($from_post_id, $area);
    $subtree = comp_extract_subtree($elements, $from_element_id);
    if ($subtree === null) {
        return bricks_error('not_found', sprintf(
            'Element "%s" not found in area "%s" of post %d.',
            $from_element_id,
            $area,
            $from_post_id,
        ));
    }

    return ['elements' => $subtree, 'root_id' => $from_element_id];
}

/**
 * Pulls the subtree rooted at `$root_id` out of a flat Bricks element list.
 * The returned subtree keeps original ids and parent refs. The root's parent
 * is normalised to 0 to detach it from its original context.
 *
 * @param list<array<string, mixed>> $elements
 * @return list<array<string, mixed>>|null
 */
function comp_extract_subtree(array $elements, string $root_id): ?array
{
    $id_index = bricks_index_elements_by_id($elements);
    if (!array_key_exists($root_id, $id_index)) {
        return null;
    }

    // The row map, not the id map: these are the semantics this walk had
    // before the two were briefly folded together.
    $children_by_parent = bricks_child_rows_by_parent($elements);
    $included = [];
    comp_collect_subtree_ids($root_id, $children_by_parent, $included);

    $subtree = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        if (!array_key_exists($id, $included)) {
            continue;
        }
        if ($id === $root_id) {
            $element['parent'] = 0;
        }
        $subtree[] = $element;
    }
    return $subtree;
}

/**
 * @param array<string, list<string>> $children_by_parent
 * @param array<string, bool> $included
 */
function comp_collect_subtree_ids(string $id, array $children_by_parent, array &$included): void
{
    if (array_key_exists($id, $included)) {
        return;
    }
    $included[$id] = true;
    foreach ($children_by_parent[$id] ?? [] as $child_id) {
        comp_collect_subtree_ids($child_id, $children_by_parent, $included);
    }
}

/**
 * Re-ids a subtree onto `$target_root_id` and conforms every other id to a
 * shape the builder can render and resolve through its element menu.
 *
 * Two renames happen here. The root always becomes the component id. And any
 * other element id that does not match the builder's six-character lookup
 * width is replaced with a fresh generated id. Ids longer than six also
 * compile component CSS for a selector the page never carries. Truncating
 * instead of regenerating could collide, so every non-six-character id
 * receives a new value.
 *
 * @param list<array<string, mixed>> $elements
 * @return array{elements: list<array<string, mixed>>, renamed: array<string, string>}
 */
function comp_reid_subtree(array $elements, string $root_id, string $target_root_id): array
{
    $rename = [$root_id => $target_root_id];
    $kept_ids = comp_ids_in_elements($elements);

    $taken = $kept_ids;
    $taken[$target_root_id] = true;
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        if ($id === '' || array_key_exists($id, $rename) || bricks_element_id_is_builder_safe($id)) {
            continue;
        }
        $fresh = bricks_generate_element_id($taken);
        $taken[$fresh] = true;
        $rename[$id] = $fresh;
    }

    /** @var list<array<string, mixed>> $result */
    $result = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        $element['id'] = $rename[$id] ?? $id;
        $element['parent'] = $id === $root_id ? 0 : comp_remap_parent($element['parent'] ?? 0, $rename, $kept_ids);
        $element = comp_remap_children($element, $rename, $kept_ids);

        if ($id === $root_id) {
            array_unshift($result, $element);
            continue;
        }
        $result[] = $element;
    }

    $renamed = $rename;
    unset($renamed[$root_id]);

    return ['elements' => $result, 'renamed' => $renamed];
}

/**
 * The class a component instance carries for a body element with this id.
 */
function bricks_component_child_class(string $id): string
{
    return 'brxe-' . substr($id, offset: 0, length: NOVAMIRA_BRICKS_ELEMENT_ID_LOOKUP_LENGTH);
}

/**
 * Rewrites property connection targets through an element-id rename map, so
 * connections keep pointing at the elements they were written for.
 *
 * @param list<array<string, mixed>> $properties
 * @param array<string, string>      $rename
 * @return list<array<string, mixed>>
 */
function comp_remap_connection_ids(array $properties, array $rename): array
{
    if ($rename === []) {
        return $properties;
    }
    foreach ($properties as $index => $property) {
        $connections = comp_property_connections($property);
        if ($connections === []) {
            continue;
        }
        $remapped = [];
        foreach (array_keys($connections) as $target) {
            $target = (string) $target;
            $remapped[$rename[$target] ?? $target] = $connections[$target];
        }
        $properties[$index]['connections'] = $remapped;
    }

    return $properties;
}

/**
 * One warning per conformed id, naming the replacement, the pages whose CSS
 * was refreshed with it, and the read-back call.
 *
 * @param  array<string, string>     $renamed
 * @param  list<array<string, mixed>> $affected
 * @return list<string>
 */
function comp_renamed_id_warnings(array $renamed, array $affected): array
{
    $warnings = [];
    foreach ($renamed as $old => $new) {
        $warnings[] = sprintf(
            'Component element id "%1$s" was stored as "%2$s": %3$s Property connections were retargeted with it.%4$s Read the stored body back with novamira/bricks-list-components verbose=true.',
            $old,
            $new,
            comp_renamed_id_reason($old),
            comp_affected_pages_phrase($affected),
        );
    }

    return $warnings;
}

/** Why a component-body id must be regenerated. */
function comp_renamed_id_reason(string $id): string
{
    $width_problem = sprintf(
        'component body ids must contain exactly %d characters to match the builder\'s element lookup width; this id contains %d.',
        NOVAMIRA_BRICKS_ELEMENT_ID_LOOKUP_LENGTH,
        strlen($id),
    );
    if (strlen($id) <= NOVAMIRA_BRICKS_ELEMENT_ID_LOOKUP_LENGTH) {
        return $width_problem;
    }

    return sprintf(
        '%s A component instance also renders its children under the first %d characters of their id, so "%s" would have been styled through "%s" — a selector the page never carries, leaving the CSS stored but dead.',
        $width_problem,
        NOVAMIRA_BRICKS_ELEMENT_ID_LOOKUP_LENGTH,
        $id,
        bricks_component_child_class($id),
    );
}

/**
 * What actually happened to the CSS of the pages that carry an instance,
 * appended to a rename warning.
 *
 * Reports the outcomes that were observed rather than the intent: a site that
 * serves CSS inline has no per-page file to rewrite, and a page whose file
 * could not be written still carries the previous body's CSS. The pages in
 * that last state are named by comp_stale_css_warning(), which is emitted
 * alongside this one, so the caller is told once and precisely.
 *
 * @param list<array<string, mixed>> $affected
 */
function comp_affected_pages_phrase(array $affected): string
{
    if ($affected === []) {
        return ' No page carries an instance of this component yet, so nothing else needed rewriting.';
    }

    $buckets = comp_css_outcome_buckets($affected);
    $total = count($affected);
    if ($buckets['regenerated'] === $total) {
        return sprintf(
            ' The CSS of the %d page(s) that carry an instance was regenerated with the new id: %s. They are listed in affected_posts.',
            $total,
            comp_named_pages($affected),
        );
    }
    if ($buckets['inline'] === $total) {
        return sprintf(
            ' This site serves Bricks CSS inline, so there is no per-page CSS file to rewrite: the %d page(s) that carry an instance render the new id from the inline generation and are listed in affected_posts.',
            $total,
        );
    }

    return sprintf(
        ' Of the %d page(s) that carry an instance, %d had their CSS regenerated with the new id, %d serve it inline (no file to rewrite), and %d still carry the previous body\'s CSS — those are named in the warning that follows, and every page is listed in affected_posts with its own outcome.',
        $total,
        $buckets['regenerated'],
        $buckets['inline'],
        $buckets['skipped'],
    );
}

/**
 * Names the pages whose CSS could not be rewritten, with the reason each
 * gave and what to do about them — or null when every page is accounted for.
 *
 * @param list<array<string, mixed>> $affected
 */
function comp_stale_css_warning(array $affected): ?string
{
    $stale = [];
    foreach ($affected as $entry) {
        $outcome = (string) ($entry['css_file'] ?? '');
        if (!str_starts_with($outcome, 'skipped(')) {
            continue;
        }
        $stale[] = sprintf(
            '"%s" (id %d): %s',
            (string) ($entry['post_title'] ?? ''),
            (int) ($entry['post_id'] ?? 0),
            rtrim(substr($outcome, offset: 8), characters: ')'),
        );
    }
    if ($stale === []) {
        return null;
    }

    return sprintf(
        '%d of the %d page(s) that carry an instance of this component still serve the CSS of the PREVIOUS body, because their CSS file could not be rewritten: %s. Until that is fixed those pages render the new element ids with the old rules, so their component styling does not apply. Fix what each reason names, then read the stored body with novamira/bricks-list-components verbose=true and send it back through novamira/bricks-edit-component patch.elements to rewrite their CSS.',
        count($stale),
        count($affected),
        implode('; ', $stale),
    );
}

/**
 * Counts of the three per-post CSS outcomes.
 *
 * @param  list<array<string, mixed>> $affected
 * @return array{regenerated: int, inline: int, skipped: int}
 */
function comp_css_outcome_buckets(array $affected): array
{
    $buckets = ['regenerated' => 0, 'inline' => 0, 'skipped' => 0];
    foreach ($affected as $entry) {
        $outcome = (string) ($entry['css_file'] ?? '');
        $key = match (true) {
            $outcome === 'regenerated' => 'regenerated',
            $outcome === 'inline' => 'inline',
            default => 'skipped',
        };
        $buckets[$key]++;
    }

    return $buckets;
}

/**
 * The first few pages by title and id, with a count of the rest.
 *
 * @param list<array<string, mixed>> $affected
 */
function comp_named_pages(array $affected): string
{
    $named = [];
    foreach (array_slice($affected, offset: 0, length: 5) as $entry) {
        $named[] = sprintf('"%s" (id %d)', (string) ($entry['post_title'] ?? ''), (int) ($entry['post_id'] ?? 0));
    }
    $rest = count($affected) - count($named);

    return implode('; ', $named) . ($rest > 0 ? sprintf(' and %d more', $rest) : '');
}

/**
 * @param array<string, string> $rename
 * @param array<string, true>   $kept_ids
 */
function comp_remap_parent(mixed $parent, array $rename, array $kept_ids): string|int
{
    if (is_string($parent) && $parent !== '' && array_key_exists($parent, $kept_ids)) {
        return $rename[$parent] ?? $parent;
    }
    if (is_int($parent)) {
        return $parent;
    }
    return 0;
}

/**
 * @param array<string, mixed>   $element
 * @param array<string, string>  $rename
 * @param array<string, true>    $kept_ids
 * @return array<string, mixed>
 */
function comp_remap_children(array $element, array $rename, array $kept_ids): array
{
    if (!array_key_exists('children', $element) || !is_array($element['children'])) {
        return $element;
    }
    $new_children = [];
    /** @var list<string|int> $children */
    $children = $element['children'];
    foreach ($children as $child_id) {
        $child_id_str = (string) $child_id;
        if (array_key_exists($child_id_str, $kept_ids)) {
            $new_children[] = $rename[$child_id_str] ?? $child_id_str;
        }
    }
    $element['children'] = $new_children;
    return $element;
}

/**
 * Scans every post that has Bricks area meta for instances of the given
 * component. Returns a map: post_id -> area -> list of instance element ids.
 *
 * @return array<int, array<string, list<string>>>
 */
function comp_find_instances(string $component_id): array
{
    $instances = [];
    foreach (bricks_area_meta_keys() as $area => $meta_key) {
        foreach (comp_candidate_post_ids($meta_key, $component_id) as $post_id) {
            $found = comp_instance_ids_in_area($post_id, $area, $component_id);
            if ($found !== []) {
                $instances[$post_id][$area] = $found;
            }
        }
    }
    return $instances;
}

/**
 * Cheap first pass: find posts whose area meta contains the component id as a
 * substring. LIKE on a serialized blob may return false positives (a collision
 * with some other string), so the caller still loads the meta and checks each
 * element's `cid` field before committing to a match.
 *
 * Every registered post type is queried explicitly: `post_type => 'any'`
 * skips types registered with `exclude_from_search` (Bricks templates among
 * them), which would hide instances placed inside templates. Revisions and
 * non-live statuses are excluded — see comp_live_post_types() — because an
 * instance only exists for a reader on a post that can render.
 *
 * @return list<int>
 */
function comp_candidate_post_ids(string $meta_key, string $component_id): array
{
    /** @var list<int> */
    return get_posts([
        'post_type' => comp_live_post_types(),
        'post_status' => comp_live_post_statuses(),
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'meta_query' => [
            [
                'key' => $meta_key,
                'value' => $component_id,
                'compare' => 'LIKE',
            ],
        ],
    ]);
}

/**
 * The post types an instance can live on for a reader.
 *
 * WordPress core does NOT copy post meta onto a revision — a plain
 * wp_update_post() leaves the Bricks tree off it entirely. What puts the tree
 * there is our own pre-write snapshot: every write through these abilities
 * copies the area meta onto the revision it takes, so the undo point can
 * restore it. A page edited five times therefore holds five more copies of
 * the same tree, and a scan over every registered post type counted each copy
 * as another live instance. That number feeds the delete-component refusal
 * and the design-system summary, so it reported pages that do not exist and
 * blast radii that cannot happen.
 *
 * @return list<string>
 */
function comp_live_post_types(): array
{
    $types = array_values(get_post_types([], output: 'names'));

    return array_values(array_filter($types, static fn(string $type): bool => $type !== 'revision'));
}

/**
 * The post statuses that count as live usage.
 *
 * Drafts, pending, scheduled and private posts count: they hold the instance
 * and an owner still edits them. Revisions ("inherit"), autosaves, trashed
 * posts and auto-drafts do not: nothing renders them, and counting them
 * refuses deletions that are safe.
 *
 * @return list<string>
 */
function comp_live_post_statuses(): array
{
    $statuses = array_values(get_post_stati());
    $skip = ['inherit', 'trash', 'auto-draft'];

    return array_values(array_filter(
        $statuses,
        static fn(string $status): bool => !in_array($status, $skip, strict: true),
    ));
}

/**
 * @return list<string>
 */
function comp_instance_ids_in_area(int $post_id, string $area, string $component_id): array
{
    $elements = bricks_get_area_elements($post_id, $area);
    $found = [];
    foreach ($elements as $element) {
        if ((string) ($element['cid'] ?? '') !== $component_id) {
            continue;
        }
        $found[] = (string) ($element['id'] ?? '');
    }
    return $found;
}

/**
 * @param array<int, array<string, list<string>>> $instances
 * @return list<array<string, mixed>>
 */
function comp_summarize_affected_posts(array $instances): array
{
    $out = [];
    foreach ($instances as $post_id => $area_to_ids) {
        $count = 0;
        foreach ($area_to_ids as $ids) {
            $count += count($ids);
        }
        /** @var WP_Post|array<string, mixed>|null $post */
        $post = get_post($post_id);
        $out[] = [
            'post_id' => $post_id,
            'post_title' => $post instanceof WP_Post ? $post->post_title : '',
            'areas' => array_keys($area_to_ids),
            'instance_count' => $count,
        ];
    }
    return $out;
}

/**
 * @param array<int, array<string, list<string>>> $instances
 */
function comp_total_instance_count(array $instances): int
{
    $count = 0;
    foreach ($instances as $area_to_ids) {
        foreach ($area_to_ids as $ids) {
            $count += count($ids);
        }
    }
    return $count;
}

/**
 * Removes each instance element by id from the area. Instances do not own
 * their subtree in page data (children live inside the component definition),
 * so a flat filter is enough.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<string> $instance_ids
 * @return list<array<string, mixed>>
 */
function comp_remove_instances_in_area(array $elements, array $instance_ids): array
{
    $targets = array_flip($instance_ids);
    return array_values(array_filter(
        $elements,
        static fn(array $element): bool => !array_key_exists((string) ($element['id'] ?? ''), $targets),
    ));
}

/**
 * Replaces each instance of the component with a freshly-ided copy of the
 * component body at the instance's position. Each expansion lands in the same
 * parent the instance was sitting under, preserving sibling order.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<string> $instance_ids
 * @param array<string, mixed> $component
 * @param array<string, mixed>|null $root
 * @return list<array<string, mixed>>
 */
function comp_inline_instances_in_area(array $elements, array $instance_ids, array $component, ?array $root): array
{
    if ($root === null) {
        return comp_remove_instances_in_area($elements, $instance_ids);
    }

    /** @var list<array<string, mixed>> $component_elements */
    $component_elements = is_array($component['elements'] ?? null) ? $component['elements'] : [];
    $targets = array_flip($instance_ids);

    /** @var list<array<string, mixed>> $result */
    $result = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        if (!array_key_exists($id, $targets)) {
            $result[] = $element;
            continue;
        }

        /** @var string|int $parent */
        $parent = $element['parent'] ?? 0;
        $taken = comp_ids_in_elements($result);
        foreach ($component_elements as $ce) {
            $eid = (string) ($ce['id'] ?? '');
            if ($eid !== '') {
                $taken[$eid] = true;
            }
        }

        $cloned = comp_clone_subtree_with_new_ids($component_elements, $taken);
        if ($cloned === []) {
            continue;
        }
        $cloned[0]['parent'] = $parent;
        foreach ($cloned as $inlined) {
            $result[] = $inlined;
        }
    }

    return $result;
}

/**
 * Clones a component's element tree with brand-new element ids so the copy
 * can live alongside the original on the same page (or any other tree that
 * already uses the original ids).
 *
 * @param list<array<string, mixed>> $elements
 * @param array<string, true> $taken_ids
 * @return list<array<string, mixed>>
 */
function comp_clone_subtree_with_new_ids(array $elements, array $taken_ids): array
{
    $rename = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        if ($id === '') {
            continue;
        }
        $new_id = comp_generate_id($taken_ids);
        $taken_ids[$new_id] = true;
        $rename[$id] = $new_id;
    }

    /** @var list<array<string, mixed>> $cloned */
    $cloned = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        $element['id'] = $rename[$id] ?? $id;
        /** @var string|int $parent */
        $parent = $element['parent'] ?? 0;
        if (is_string($parent) && $parent !== '' && array_key_exists($parent, $rename)) {
            $element['parent'] = $rename[$parent];
        }
        if (array_key_exists('children', $element) && is_array($element['children'])) {
            $new_children = [];
            /** @var list<string|int> $children */
            $children = $element['children'];
            foreach ($children as $child_id) {
                $child_id_str = (string) $child_id;
                $new_children[] = $rename[$child_id_str] ?? $child_id_str;
            }
            $element['children'] = $new_children;
        }
        $cloned[] = $element;
    }
    return $cloned;
}
