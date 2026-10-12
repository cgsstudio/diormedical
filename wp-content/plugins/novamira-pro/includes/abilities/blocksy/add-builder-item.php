<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Blocksy;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: place an available item into the Blocksy header or footer
 * builder.
 */

wp_register_ability('novamira/blocksy-add-builder-item', [
    'label' => __('Add Blocksy Builder Item', domain: 'novamira-pro'),
    'description' => __(
        'Places an available item type into the Blocksy header or footer builder, exactly like dropping it in the customizer. Header targets are device (desktop|mobile) + row (top-row|middle-row|bottom-row|offcanvas) + placement (start|middle|end|start-middle|end-middle; offcanvas has only start); footer targets are row (top-row|middle-row|bottom-row) + column (1-based). Discover valid item ids and their placement rules with blocksy-list-builder-items; the same rules the customizer enforces are enforced here (devices, allowed_in/excluded_from, enabled). Duplicates are rejected where Blocksy\'s UI would reject them: a non-clone item can exist once per header device tree / footer section — placing it in the other header device tree reuses the SAME instance (shared settings, like the default logo). Clone-enabled items get a fresh `type~hash` instance automatically when the type is already placed, or explicitly with duplicate=true, up to the customizer\'s own per-type cap (clone: true → 2 total instances, clone: 9 → 10; see clone_limit in blocksy-list-builder-items); a new clone starts as a settings copy of the base instance, exactly like the customizer\'s clone button. The write is validated, atomic, and runs Blocksy\'s dynamic-CSS refresh sequence.',
        domain: 'novamira-pro',
    ),
    'category' => 'blocksy',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'builder' => [
                'type' => 'string',
                'enum' => ['header', 'footer'],
                'description' => 'Which builder to place into.',
            ],
            'item' => [
                'type' => 'string',
                'description' => 'Item type id from blocksy-list-builder-items (e.g. text, button, socials) — or an existing instance id (e.g. button~a1b2…) to also show that instance in the other header device tree.',
            ],
            'section' => [
                'type' => 'string',
                'description' => 'Section id (default: the section that currently renders).',
            ],
            'device' => [
                'type' => 'string',
                'enum' => ['desktop', 'mobile'],
                'description' => 'Header only (required there): which device tree to place into.',
            ],
            'row' => [
                'type' => 'string',
                'description' => 'Target row id. Header: top-row, middle-row, bottom-row, offcanvas. Footer: top-row, middle-row, bottom-row.',
            ],
            'placement' => [
                'type' => 'string',
                'description' => 'Header only (required there): placement slot inside the row — start, middle, end, start-middle, end-middle (offcanvas rows only have start).',
            ],
            'column' => [
                'type' => 'integer',
                'description' => 'Footer only (required there): 1-based column inside the row, within the row\'s existing column count.',
            ],
            'position' => [
                'type' => 'integer',
                'description' => 'Index inside the target placement/column item list (0-based). Omit to append.',
            ],
            'duplicate' => [
                'type' => 'boolean',
                'description' => 'Force a fresh instance of a clone-enabled item (type~hash id, starting as a settings copy of the base instance) even when the type could be reused. Rejected for items without clone support or past the per-type clone_limit.',
            ],
        ],
        'required' => ['builder', 'item', 'row'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'builder' => ['type' => 'string'],
            'section' => ['type' => 'string'],
            'item' => [
                'type' => 'string',
                'description' => 'The item TYPE that was placed.',
            ],
            'instance' => [
                'type' => 'string',
                'description' => 'The instance id now in the structure — the plain type id, a reused existing instance, or a fresh type~hash clone. Use it with edit-builder-item / delete-builder-item.',
            ],
            'created_new_instance' => [
                'type' => 'boolean',
                'description' => 'True when a fresh clone instance id was generated for this placement.',
            ],
            'location' => [
                'type' => 'object',
                'description' => 'Where it landed: {device, row, placement, column, position} — device/placement null for footer, column null for header.',
            ],
        ],
        'required' => ['builder', 'section', 'item', 'instance', 'created_new_instance', 'location'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Blocksy\\blocksy_add_builder_item',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read blocksy-get-header / blocksy-get-footer first for the actual rows/placements/columns, and blocksy-list-builder-items for valid item ids and their rules. The new item renders with its option defaults; customize it afterwards with edit-builder-item using the returned instance id.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{
 *     builder?: string,
 *     item?: string,
 *     section?: string,
 *     device?: string,
 *     row?: string,
 *     placement?: string,
 *     column?: int,
 *     position?: int,
 *     duplicate?: bool,
 * } $input
 * @return array<string, mixed>|WP_Error
 */
// Validation-dense write callback — the branch count is the checks.
// @mago-expect lint:cyclomatic-complexity
// @mago-expect lint:halstead
function blocksy_add_builder_item(array $input): array|WP_Error
{
    // Pin the storage identity as the VERY FIRST statements — the schema
    // preload, config readers and Blocksy's sanitizer all run theme code
    // later; none of them may precede or redirect the pin.
    $stylesheet = get_stylesheet();
    $template = get_template();
    $mods_key = 'theme_mods_' . $stylesheet;

    if (!blocksy_theme_available($template)) {
        return bly_theme_inactive_error();
    }

    $builder = bly_builder_input($input['builder'] ?? null);
    if ($builder instanceof WP_Error) {
        return $builder;
    }

    $item_input = isset($input['item']) ? trim($input['item']) : '';
    if ($item_input === '') {
        return bly_error(
            'bly_invalid_input',
            message: '`item` is required — an item type id from blocksy-list-builder-items.',
        );
    }
    $type = bly_original_item_id($item_input);
    $explicit_instance = $item_input !== $type;
    $duplicate = ($input['duplicate'] ?? false) === true;
    $section_id = isset($input['section']) && trim($input['section']) !== '' ? trim($input['section']) : null;
    $position = $input['position'] ?? null;
    if ($position !== null && $position < 0) {
        return bly_error(
            'bly_invalid_input',
            message: '`position` must be a non-negative integer (0-based index; omit to append).',
        );
    }

    $target = bly_add_target_input($builder, $input);
    if ($target instanceof WP_Error) {
        return $target;
    }

    $registered = bly_builder_registered_items($builder);
    if ($registered instanceof WP_Error) {
        return $registered;
    }
    if (!isset($registered[$type])) {
        return bly_error('bly_not_found', sprintf(
            'Item type "%s" is not registered for the %s builder. Use blocksy-list-builder-items to discover valid ids — items of an inactive Companion/Pro plugin are not available.',
            $type,
            $builder,
        ));
    }
    $registration = $registered[$type];

    // Everything the placement decision needs, bundled once: the raw item
    // input, its resolved type, whether an explicit instance id / a forced
    // duplicate was requested, the validated target, the position, and a
    // fresh clone id generated OUTSIDE the lock (pure) — used when
    // duplicate=true, or when a clone-enabled type is already in the
    // target tree (the same situation where the customizer generates one).
    $request = [
        'item_input' => $item_input,
        'type' => $type,
        'explicit_instance' => $explicit_instance,
        'duplicate' => $duplicate,
        'target' => $target,
        'position' => $position,
        'clone_id' => bly_new_instance_id($type),
    ];

    $rule_error = bly_add_check_item_rules($builder, $registration, $request);
    if ($rule_error !== null) {
        return $rule_error;
    }

    // Gate 2 must be resolvable — fail fast before taking the lock.
    $index = bly_option_index_or_error();
    if ($index instanceof WP_Error) {
        return $index;
    }

    $result = [
        'section' => '',
        'instance' => '',
        'created_new_instance' => false,
        'position' => 0,
    ];

    $config = $registration['config'];
    /** @var mixed $written */
    $written = bly_locked_mod_write(
        bly_builder_mod_id($builder),
        function (array $mods) use ($builder, $section_id, $config, $request, $target, $position, &$result): mixed {
            $prepared = bly_builder_prepare_write_value($builder, $mods, $section_id);
            if ($prepared instanceof WP_Error) {
                return $prepared;
            }
            ['value' => $value, 'index' => $index, 'section' => $section] = $prepared;

            $instance = bly_add_resolve_instance($builder, $section, $config, $request);
            if ($instance instanceof WP_Error) {
                return $instance;
            }

            $result['created_new_instance'] = $instance === $request['clone_id'];
            $result['position'] = $position ?? bly_add_target_count($builder, $section, $target);

            $insert_error = $builder === 'header'
                ? bly_header_insert($section, $target, $instance, $position)
                : bly_footer_insert($section, $target['row'], $target['column'], $instance, $position);
            if ($insert_error !== null) {
                return $insert_error;
            }

            // A freshly created clone starts as a settings copy of the
            // BASE instance — the exact CLONE_ITEM reducer behavior.
            if ($result['created_new_instance']) {
                bly_builder_copy_base_values($section, $request['type'], $instance);
            }

            $result['instance'] = $instance;
            /** @var mixed $section_id_raw */
            $section_id_raw = $section['id'] ?? '';
            $result['section'] = is_string($section_id_raw) ? $section_id_raw : '';

            $value = bly_builder_replace_section($value, $index, $section);
            return bly_builder_gate2($builder, $value);
        },
        $stylesheet,
        $mods_key,
    );
    if ($written instanceof WP_Error) {
        return $written;
    }

    return [
        'builder' => $builder,
        'section' => $result['section'],
        'item' => $type,
        'instance' => $result['instance'],
        'created_new_instance' => $result['created_new_instance'],
        'location' => [
            'device' => $builder === 'header' ? $target['device'] : null,
            'row' => $target['row'],
            'placement' => $builder === 'header' ? $target['placement'] : null,
            'column' => $builder === 'footer' ? $target['column'] : null,
            'position' => $result['position'],
        ],
    ];
}

/**
 * Validated builder-specific target from the input: header needs
 * device+row+placement, footer needs row+column.
 *
 * @param array{
 *     builder?: string,
 *     item?: string,
 *     section?: string,
 *     device?: string,
 *     row?: string,
 *     placement?: string,
 *     column?: int,
 *     position?: int,
 *     duplicate?: bool,
 * } $input
 * @return array{device: string, row: string, placement: string, column: int}|WP_Error
 */
function bly_add_target_input(string $builder, array $input): array|WP_Error
{
    $row = isset($input['row']) ? trim($input['row']) : '';
    if ($row === '') {
        return bly_error('bly_invalid_input', message: '`row` is required (e.g. middle-row).');
    }

    if ($builder === 'header') {
        $device = $input['device'] ?? null;
        if ($device !== 'desktop' && $device !== 'mobile') {
            return bly_error('bly_invalid_input', message: 'Header placements need `device` — "desktop" or "mobile".');
        }
        $placement = isset($input['placement']) ? trim($input['placement']) : '';
        if ($placement === '') {
            return bly_error(
                'bly_invalid_input',
                message: 'Header placements need `placement` — start, middle, end, start-middle or end-middle (offcanvas rows only have start).',
            );
        }
        return ['device' => $device, 'row' => $row, 'placement' => $placement, 'column' => 0];
    }

    $column = $input['column'] ?? null;
    if (!is_int($column) || $column < 1) {
        return bly_error(
            'bly_invalid_input',
            message: 'Footer placements need `column` — a 1-based column number inside the row.',
        );
    }
    return ['device' => '', 'row' => $row, 'placement' => '', 'column' => $column];
}

/**
 * Static (schema-level) placement rules for an add: primaries are not
 * placeable, disabled items are refused, device availability and the
 * allowed_in/excluded_from row rules are enforced exactly as the
 * customizer enforces them, and duplicate=true needs clone support.
 *
 * @param array{id: string, path: string, is_primary: bool, config: array<array-key, mixed>} $registration
 * @param array{
 *     item_input: string,
 *     type: string,
 *     explicit_instance: bool,
 *     duplicate: bool,
 *     target: array{device: string, row: string, placement: string, column: int},
 *     position: int|null,
 *     clone_id: string,
 * } $request
 */
function bly_add_check_item_rules(string $builder, array $registration, array $request): ?WP_Error
{
    $type = $request['type'];
    $config = $registration['config'];
    $target = $request['target'];
    $duplicate = $request['duplicate'];

    if ($registration['is_primary']) {
        return bly_error('bly_invalid_input', sprintf(
            '"%s" is a row container, not a placeable item — rows always exist in the structure; put items INTO them instead.',
            $type,
        ));
    }
    if (!bly_builder_item_enabled($config)) {
        return bly_error('bly_invalid_input', sprintf(
            'Item "%s" is registered but currently disabled (its config gates it on another plugin — e.g. cart needs WooCommerce, language-switcher needs a multilanguage plugin).',
            $type,
        ));
    }
    if ($duplicate && bly_builder_clone_limit($config) <= 1) {
        return bly_error('bly_invalid_input', sprintf(
            'Item "%s" does not support multiple instances (no clone flag) — duplicate=true is not available for it.',
            $type,
        ));
    }
    if ($duplicate && $request['explicit_instance']) {
        return bly_error(
            'bly_invalid_input',
            message: 'Pass the plain item type id with duplicate=true — an explicit instance id and a fresh duplicate are mutually exclusive.',
        );
    }

    $device = $builder === 'header' ? $target['device'] : null;
    if ($device !== null && !in_array($device, bly_builder_item_devices($config), strict: true)) {
        return bly_error('bly_invalid_input', sprintf(
            'Item "%s" is not available in the %s tree — its config admits: %s.',
            $type,
            $device,
            implode(', ', bly_builder_item_devices($config)),
        ));
    }
    if (!bly_builder_item_allowed_at($config, $target['row'], $device)) {
        return bly_error('bly_invalid_input', sprintf(
            'Item "%s" is not allowed in the %s row — the same allowed_in/excluded_from rule the customizer enforces. Check its rules in blocksy-list-builder-items.',
            $type,
            $target['row'],
        ));
    }
    return null;
}

/**
 * Fresh-state prefix shared by the three builder writes, run INSIDE the
 * locked compute: effective value from the in-lock mods (REFUSING a
 * present-but-unusable stored value — merging a mutated default over it
 * would silently discard whatever the corrupted row held), preview keys
 * stripped, the sections list normalized, and the target section located.
 *
 * @param array<array-key, mixed> $mods
 * @return array{value: array<array-key, mixed>, index: int, section: array<array-key, mixed>}|WP_Error
 */
function bly_builder_prepare_write_value(string $builder, array $mods, ?string $section_id): array|WP_Error
{
    $effective = bly_builder_effective_value($builder, $mods);
    if ($effective instanceof WP_Error) {
        return $effective;
    }
    if ($effective['stored_invalid']) {
        return bly_error('bly_invalid_input', sprintf(
            'The stored %s theme_mod exists but is structurally unusable — refusing to overwrite it with a modified default, which would silently discard whatever it holds. Inspect it with blocksy-get-option and repair or delete it first.',
            bly_builder_mod_id($builder),
        ));
    }

    $value = bly_builder_strip_preview_keys($effective['value']);
    $value['sections'] = array_values(is_array($value['sections'] ?? null) ? $value['sections'] : []);

    $found = bly_builder_find_section($builder, $value, $section_id);
    if ($found instanceof WP_Error) {
        return $found;
    }
    return ['value' => $value, 'index' => $found['index'], 'section' => $found['section']];
}

/**
 * Resolve WHICH instance id an add places, against the FRESH section state
 * (inside the lock). Mirrors what the customizer would do:
 *  - duplicate=true → the pre-generated fresh clone id, within the clone
 *    limit.
 *  - explicit instance id → that instance, which must already exist in the
 *    section and must not already sit in the target tree.
 *  - plain type id, type already in the target tree → fresh clone within
 *    the limit for clone-enabled items, rejection otherwise (the UI
 *    removes such items from the available list).
 *  - plain type id, type elsewhere in the section (the other header device
 *    tree) → the existing instance is REUSED when unambiguous, so both
 *    trees share settings exactly like the default logo does.
 *  - otherwise → the plain type id.
 *
 * Every clone-creating path enforces the customizer's instance cap
 * (bly_builder_clone_limit — `(parseInt(clone)||1)+1` total instances),
 * counting placed AND settings-only instances like the UI state does.
 *
 * @param array<array-key, mixed> $section
 * @param array<array-key, mixed> $config
 * @param array{
 *     item_input: string,
 *     type: string,
 *     explicit_instance: bool,
 *     duplicate: bool,
 *     target: array{device: string, row: string, placement: string, column: int},
 *     position: int|null,
 *     clone_id: string,
 * } $request
 */
function bly_add_resolve_instance(string $builder, array $section, array $config, array $request): string|WP_Error
{
    $item_input = $request['item_input'];
    $type = $request['type'];
    $clone_id = $request['clone_id'];
    if ($request['duplicate']) {
        return bly_add_clone_within_limit($builder, $section, $type, $config, $clone_id);
    }

    $device = $builder === 'header' ? $request['target']['device'] : null;

    if ($request['explicit_instance']) {
        $anywhere = bly_builder_location_strings($builder, $section, $item_input);
        if ($anywhere === [] && bly_builder_item_values($section, $item_input) === null) {
            return bly_error('bly_not_found', sprintf(
                'Instance "%s" does not exist in this section. Instance ids are created by placing the item (duplicate=true for extra copies) — pass the plain type id "%s" instead.',
                $item_input,
                $type,
            ));
        }
        if (bly_add_instance_in_scope($builder, $section, $item_input, $device)) {
            return bly_error('bly_invalid_input', sprintf(
                'Instance "%s" is already placed %s (%s). Blocksy shows one instance once per tree — delete it first to move it.',
                $item_input,
                $device !== null ? 'in the ' . $device . ' tree' : 'in this section',
                implode('; ', $anywhere),
            ));
        }
        return $item_input;
    }

    $in_scope = [];
    $elsewhere = [];
    foreach (bly_builder_placed_ids($builder, $section) as $placed) {
        if (bly_original_item_id($placed) !== $type) {
            continue;
        }
        if (bly_add_instance_in_scope($builder, $section, $placed, $device)) {
            $in_scope[] = $placed;
            continue;
        }
        $elsewhere[] = $placed;
    }

    if ($in_scope !== []) {
        if (bly_builder_clone_limit($config) > 1) {
            return bly_add_clone_within_limit($builder, $section, $type, $config, $clone_id);
        }
        return bly_error('bly_invalid_input', sprintf(
            'Item "%s" is already placed %s (as %s) and does not support multiple instances — Blocksy\'s UI would reject this too. Edit the existing instance, or delete it first.',
            $type,
            $device !== null ? 'in the ' . $device . ' tree' : 'in this section',
            implode(', ', $in_scope),
        ));
    }

    if ($elsewhere === []) {
        return $type;
    }
    if (count($elsewhere) === 1) {
        return $elsewhere[0];
    }
    return bly_error('bly_invalid_input', sprintf(
        'Multiple instances of "%s" exist in this section (%s) — pass the specific instance id to show one of them here, or duplicate=true for a fresh independent copy.',
        $type,
        implode(', ', $elsewhere),
    ));
}

/**
 * The clone-limit gate for every clone-creating path: counts the type's
 * existing instances in the section (placed + settings-only, like the UI
 * state) against bly_builder_clone_limit and returns the fresh clone id
 * only while under the cap — otherwise a WP_Error naming the limit and
 * the current count.
 *
 * @param array<array-key, mixed> $section
 * @param array<array-key, mixed> $config
 */
function bly_add_clone_within_limit(
    string $builder,
    array $section,
    string $type,
    array $config,
    string $clone_id,
): string|WP_Error {
    $limit = bly_builder_clone_limit($config);
    $existing = bly_builder_type_instances($builder, $section, $type);
    if (count($existing) >= $limit) {
        return bly_error('bly_invalid_input', sprintf(
            'Item "%s" already has %d instance(s) in this section (%s) — Blocksy caps it at %d total (its clone config), and the customizer would refuse another one too. Delete an instance first.',
            $type,
            count($existing),
            implode(', ', $existing),
            $limit,
        ));
    }
    return $clone_id;
}

/**
 * Whether an instance already sits in the tree an add targets: the given
 * header device tree, or anywhere in the (single-tree) footer section.
 *
 * @param array<array-key, mixed> $section
 */
function bly_add_instance_in_scope(string $builder, array $section, string $instance, ?string $device): bool
{
    if ($builder === 'footer') {
        return bly_footer_locations($section, $instance) !== [];
    }
    foreach (bly_header_locations($section, $instance) as $location) {
        if ($device === null || $location['device'] === $device) {
            return true;
        }
    }
    return false;
}

/**
 * Current item count of the add target (for the reported append position).
 * Missing targets count 0 — the subsequent insert reports the real error.
 *
 * @param array<array-key, mixed> $section
 * @param array{device: string, row: string, placement: string, column: int} $target
 */
// Defensive walk over the two untyped stored tree shapes.
// @mago-expect lint:cyclomatic-complexity
function bly_add_target_count(string $builder, array $section, array $target): int
{
    if ($builder === 'header') {
        /** @var mixed $bar */
        foreach (is_array($section[$target['device']] ?? null) ? $section[$target['device']] : [] as $bar) {
            if (!is_array($bar) || ($bar['id'] ?? null) !== $target['row']) {
                continue;
            }
            /** @var mixed $slot */
            foreach (is_array($bar['placements'] ?? null) ? $bar['placements'] : [] as $slot) {
                if (is_array($slot) && ($slot['id'] ?? null) === $target['placement']) {
                    return is_array($slot['items'] ?? null) ? count($slot['items']) : 0;
                }
            }
        }
        return 0;
    }
    /** @var mixed $row_node */
    foreach (is_array($section['rows'] ?? null) ? $section['rows'] : [] as $row_node) {
        if (!is_array($row_node) || ($row_node['id'] ?? null) !== $target['row']) {
            continue;
        }
        $columns = array_values(is_array($row_node['columns'] ?? null) ? $row_node['columns'] : []);
        /** @var mixed $column */
        $column = $columns[$target['column'] - 1] ?? null;
        return is_array($column) ? count($column) : 0;
    }
    return 0;
}
