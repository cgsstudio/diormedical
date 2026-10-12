<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// Box / field name integrity for acpt-create-field-group / acpt-edit-field-group.
//
// ACPT resolves a box or field sent without an id by NAME, and its field
// lookup (MetaRepository::getMetaFieldByName) is not scoped to the group: a
// new group whose box/field names match another group's re-uses that group's
// field record, so the other group silently loses the field. Values are also
// keyed by `{box}_{field}` site-wide. Some ACPT versions additionally rename a
// new box, field, repeater child or block field whose name already exists
// anywhere on the site (`general` -> `general_1`).
//
// So before saving we refuse box names (and box/field ids) owned by another
// group and pin every box and top-level field to an id, and after saving we
// read the group back and report every name ACPT stored differently.
// ---------------------------------------------------------------------------

/**
 * Normalize a name the way ACPT does before storing it.
 */
function fgn_slug(string $name): string
{
    if (class_exists('ACPT\\Utils\\PHP\\Sluggify')) {
        return \ACPT\Utils\PHP\Sluggify::slug($name);
    }
    return $name;
}

/**
 * The name a box or field definition asks for: its new_name when renaming.
 *
 * @param array<string, mixed> $item
 */
function fgn_requested_name(array $item): string
{
    foreach (['new_name', 'newName', 'name'] as $key) {
        /** @var mixed $value */
        $value = $item[$key] ?? null;
        if (is_string($value) && $value !== '') {
            return $value;
        }
    }
    return '';
}

/**
 * Index of the boxes a group owns and their top-level fields by name.
 *
 * @return array{boxes: array<string, array{id: string, fields: array<string, string>}>, box_ids: array<string, true>}
 */
function fgn_group_index(?\ACPT\Core\Models\Meta\MetaGroupModel $group): array
{
    $index = ['boxes' => [], 'box_ids' => []];
    if ($group === null) {
        return $index;
    }
    foreach ($group->getBoxes() as $box) {
        $fields = [];
        foreach ($box->getFields() as $field) {
            $fields[$field->getName()] = $field->getId();
        }
        $index['boxes'][$box->getName()] = ['id' => $box->getId(), 'fields' => $fields];
        $index['box_ids'][$box->getId()] = true;
    }
    return $index;
}

/**
 * Every box row ACPT has stored: id => name.
 *
 * @return array{boxes: array<string, string>}
 */
function fgn_site_names(): array
{
    $out = ['boxes' => []];
    foreach (\ACPT\Core\Repository\MetaRepository::getNames()['boxes'] as $row) {
        $out['boxes'][$row['id']] = $row['name'];
    }
    return $out;
}

/**
 * Name of the group that owns a box id, for error messages.
 */
function fgn_owner_group_name(string $box_id): string
{
    foreach (\ACPT\Core\Repository\MetaRepository::get([]) as $group) {
        foreach ($group->getBoxes() as $box) {
            if ($box->getId() === $box_id) {
                return $group->getName();
            }
        }
    }
    return '';
}

/**
 * Refuse a box name already used by a box of another group.
 *
 * @param array<string, string> $site_boxes id => name
 * @param string $group_name the group being saved, used to suggest a free name
 */
function fgn_check_box_name(string $box_name, string $box_id, array $site_boxes, string $group_name): ?WP_Error
{
    foreach ($site_boxes as $other_id => $other_name) {
        if ($other_name !== $box_name || $other_id === $box_id) {
            continue;
        }
        $owner = fgn_owner_group_name($other_id);
        return new WP_Error('acpt_box_name_taken', sprintf(
            /* translators: 1: box name, 2: field group name, 3: suggested box name. */
            __(
                'Box name "%1$s" is already used by ACPT field group "%2$s". ACPT box names must be unique across the whole site: a shared box name lets ACPT attach this group\'s fields to that group\'s records and mixes their stored values. Choose a box name no other group uses, for example "%3$s".',
                domain: 'novamira-pro',
            ),
            $box_name,
            $owner !== '' ? $owner : '?',
            fgn_slug($group_name . '_' . $box_name),
        ));
    }
    return null;
}

/**
 * Field ids this group owns at every depth (top-level fields, repeater
 * children, block fields: a field may move between those places within the
 * group), its block ids, and the names of nested items per parent, so nested
 * items are matched within this group only.
 *
 * @return array{ids: array<string, true>, block_ids: array<string, true>, members: array<string, array<string, string>>, blocks: array<string, array<string, string>>}
 */
function fgn_nested_index(?\ACPT\Core\Models\Meta\MetaGroupModel $group): array
{
    $index = ['ids' => [], 'block_ids' => [], 'members' => [], 'blocks' => []];
    $fields = [];
    foreach ($group === null ? [] : $group->getBoxes() as $box) {
        foreach ($box->getFields() as $field) {
            $index['ids'][$field->getId()] = true;
            $fields[] = $field;
        }
    }
    while ($fields !== []) {
        $field = array_pop($fields);
        foreach ($field->getChildren() as $child) {
            $index['ids'][$child->getId()] = true;
            $index['members'][$field->getId()][$child->getName()] = $child->getId();
            $fields[] = $child;
        }
        foreach ($field->getBlocks() as $block) {
            $index['block_ids'][$block->getId()] = true;
            $index['blocks'][$field->getId()][$block->getName()] = $block->getId();
            foreach ($block->getFields() as $block_field) {
                $index['ids'][$block_field->getId()] = true;
                $index['members'][$block->getId()][$block_field->getName()] = $block_field->getId();
                $fields[] = $block_field;
            }
        }
    }
    return $index;
}

/**
 * The id a nested item (child, block, block field) is saved under: its own id
 * when this group owns it, the id of the same-named item under the same
 * parent, or a fresh one. An id this group does not own is refused: ACPT
 * keeps the id when saving and would move that record out of its group.
 *
 * @param array<string, mixed> $item
 * @param array<string, true> $own_ids
 * @param array<string, string> $own_by_name
 */
function fgn_nested_id(array $item, array $own_ids, array $own_by_name, string $path): string|WP_Error
{
    $id = isset($item['id']) && is_string($item['id']) && $item['id'] !== '' ? $item['id'] : null;
    if ($id === null) {
        return $own_by_name[fgn_slug((string) ($item['name'] ?? ''))] ?? acpt_uuid_v4();
    }
    if (!isset($own_ids[$id])) {
        return new WP_Error('acpt_nested_id_foreign', sprintf(
            /* translators: 1: id, 2: box.field path. */
            __(
                'Id "%1$s" at %2$s is not a field or block of this field group. Omit the id to create a new one.',
                domain: 'novamira-pro',
            ),
            $id,
            $path,
        ));
    }
    return $id;
}

/**
 * Pin the children (Repeater) or blocks and block fields (FlexibleContent) of
 * a field that already has its id. Lists the request does not contain are
 * left out (on edit they come from the stored definition).
 *
 * @param array<string, mixed> $field
 * @param array{ids: array<string, true>, block_ids: array<string, true>, members: array<string, array<string, string>>, blocks: array<string, array<string, string>>} $nested
 * @return array<string, mixed>|WP_Error
 */
function fgn_pin_nested(array $field, array $nested, string $path): array|WP_Error
{
    $id = (string) ($field['id'] ?? '');
    $path .= '.' . fgn_requested_name($field);
    if (array_key_exists('children', $field)) {
        $children = fgn_pin_members(fgn_items($field['children']), $id, $nested, $path);
        if ($children instanceof WP_Error) {
            return $children;
        }
        $field['children'] = $children;
    }
    if (!array_key_exists('blocks', $field)) {
        return $field;
    }
    $blocks = [];
    foreach (fgn_items($field['blocks']) as $block) {
        $block_path = $path . '.' . (string) ($block['name'] ?? '');
        $block_id = fgn_nested_id($block, $nested['block_ids'], $nested['blocks'][$id] ?? [], $block_path);
        if ($block_id instanceof WP_Error) {
            return $block_id;
        }
        $block['id'] = $block_id;
        if (array_key_exists('fields', $block)) {
            $block_fields = fgn_pin_members(fgn_items($block['fields']), $block_id, $nested, $block_path);
            if ($block_fields instanceof WP_Error) {
                return $block_fields;
            }
            $block['fields'] = $block_fields;
        }
        $blocks[] = $block;
    }
    $field['blocks'] = $blocks;
    return $field;
}

/**
 * Pin a list of nested fields (children of a field, or fields of a block).
 *
 * @param list<array<string, mixed>> $fields
 * @param array{ids: array<string, true>, block_ids: array<string, true>, members: array<string, array<string, string>>, blocks: array<string, array<string, string>>} $nested
 * @return list<array<string, mixed>>|WP_Error
 */
function fgn_pin_members(array $fields, string $parent_id, array $nested, string $path): array|WP_Error
{
    foreach ($fields as $i => $field) {
        $id = fgn_nested_id(
            $field,
            $nested['ids'],
            $nested['members'][$parent_id] ?? [],
            $path . '.' . fgn_requested_name($field),
        );
        if ($id instanceof WP_Error) {
            return $id;
        }
        $field['id'] = $id;
        $pinned = fgn_pin_nested($field, $nested, $path);
        if ($pinned instanceof WP_Error) {
            return $pinned;
        }
        $fields[$i] = $pinned;
    }
    return $fields;
}

/**
 * Pin each top-level field of a box to an id: the id of the same-named field
 * already in this box of this group, or a fresh one. A field sent without an
 * id is otherwise resolved by ACPT across ALL groups. Nested items are pinned
 * the same way.
 *
 * @param list<array<string, mixed>> $fields
 * @param array<string, string> $own_fields name => id of the fields this box already has
 * @param array{ids: array<string, true>, block_ids: array<string, true>, members: array<string, array<string, string>>, blocks: array<string, array<string, string>>} $nested
 * @return list<array<string, mixed>>|WP_Error
 */
function fgn_pin_field_ids(array $fields, string $box_name, array $own_fields, array $nested): array|WP_Error
{
    foreach ($fields as $i => $field) {
        $id = isset($field['id']) && is_string($field['id']) && $field['id'] !== '' ? $field['id'] : null;
        if ($id !== null && !isset($nested['ids'][$id])) {
            return new WP_Error('acpt_field_id_foreign', sprintf(
                /* translators: 1: field id, 2: box name. */
                __(
                    'Field id "%1$s" in box "%2$s" is not a field of this ACPT field group (ACPT would move that record out of its own group). Omit the id to create a new field.',
                    domain: 'novamira-pro',
                ),
                $id,
                $box_name,
            ));
        }
        $field['id'] = $id ?? $own_fields[fgn_slug((string) ($field['name'] ?? ''))] ?? acpt_uuid_v4();
        $pinned = fgn_pin_nested($field, $nested, $box_name);
        if ($pinned instanceof WP_Error) {
            return $pinned;
        }
        $fields[$i] = $pinned;
    }
    return $fields;
}

/**
 * The id a payload box is saved under: its own id (refused when it belongs to
 * another group), the id of the same-named box of this group, or a fresh one.
 *
 * @param array<string, mixed> $box
 * @param array{boxes: array<string, array{id: string, fields: array<string, string>}>, box_ids: array<string, true>} $index
 * @param array{boxes: array<string, string>} $site
 */
function fgn_resolve_box_id(array $box, string $name, array $index, array $site): string|WP_Error
{
    $id = isset($box['id']) && is_string($box['id']) && $box['id'] !== '' ? $box['id'] : null;
    if ($id === null) {
        return $index['boxes'][$name]['id'] ?? acpt_uuid_v4();
    }
    if (isset($site['boxes'][$id]) && !isset($index['box_ids'][$id])) {
        return new WP_Error('acpt_box_id_foreign', sprintf(
            /* translators: %s: box id. */
            __(
                'Box id "%s" belongs to another ACPT field group. Omit the id to create a new box.',
                domain: 'novamira-pro',
            ),
            $id,
        ));
    }
    return $id;
}

/**
 * Validate and pin one payload box. Returns the box with ids set.
 *
 * @param array<string, mixed> $box
 * @param array{boxes: array<string, array{id: string, fields: array<string, string>}>, box_ids: array<string, true>} $index
 * @param array{ids: array<string, true>, block_ids: array<string, true>, members: array<string, array<string, string>>, blocks: array<string, array<string, string>>} $nested
 * @param array{boxes: array<string, string>} $site
 * @return array<string, mixed>|WP_Error
 */
function fgn_prepare_box(array $box, array $index, array $nested, array $site, string $group_name): array|WP_Error
{
    $name = fgn_slug((string) ($box['name'] ?? ''));
    $id = fgn_resolve_box_id($box, $name, $index, $site);
    if ($id instanceof WP_Error) {
        return $id;
    }
    $box['id'] = $id;

    $stored_name = fgn_slug(fgn_requested_name($box));
    // A box this group already has keeps its name even when an older ACPT
    // let another group take the same one; only new names are checked.
    $taken = isset($index['box_ids'][$id]) && $stored_name === $name
        ? null
        : fgn_check_box_name($stored_name, $id, $site['boxes'], $group_name);
    if ($taken !== null) {
        return $taken;
    }

    $own_fields = [];
    foreach ($index['boxes'] as $own_box) {
        if ($own_box['id'] !== $id) {
            continue;
        }
        $own_fields = $own_box['fields'];
    }
    $fields = fgn_items($box['fields'] ?? null);
    $pinned = fgn_pin_field_ids($fields, $stored_name, $own_fields, $nested);
    if ($pinned instanceof WP_Error) {
        return $pinned;
    }
    $box['fields'] = $pinned;

    return $box;
}

/**
 * Validate the boxes of a create/edit payload against the rest of the site
 * and pin every box, field, child, block and block field to an id.
 *
 * @param list<array<string, mixed>> $boxes
 * @return list<array<string, mixed>>|WP_Error
 */
function acpt_prepare_group_boxes(
    array $boxes,
    ?\ACPT\Core\Models\Meta\MetaGroupModel $group,
    string $group_name,
): array|WP_Error {
    $index = fgn_group_index($group);
    $nested = fgn_nested_index($group);
    $site = fgn_site_names();
    $seen = [];
    $out = [];
    foreach ($boxes as $box) {
        $prepared = fgn_prepare_box($box, $index, $nested, $site, $group_name);
        if ($prepared instanceof WP_Error) {
            return $prepared;
        }
        $name = fgn_slug(fgn_requested_name($prepared));
        if (isset($seen[$name])) {
            return new WP_Error('acpt_box_name_duplicate', sprintf(
                /* translators: %s: box name. */
                __('Box name "%s" appears twice in boxes[]. Each box needs its own name.', domain: 'novamira-pro'),
                $name,
            ));
        }
        $seen[$name] = true;
        $out[] = $prepared;
    }
    return fgn_check_identity($out) ?? $out;
}

/**
 * Refuse a payload in which two siblings share a name, or two items resolve
 * to the same field or block id. ACPT keeps only one item per id (the second
 * is dropped without a trace), and same-name siblings resolve to the same
 * stored item.
 *
 * @param list<array<string, mixed>> $boxes pinned boxes
 */
function fgn_check_identity(array $boxes): ?WP_Error
{
    $seen = ['field' => [], 'block' => []];
    /** @var list<array{0: list<array<string, mixed>>, 1: string, 2: string}> $lists */
    $lists = [];
    foreach ($boxes as $box) {
        $lists[] = [fgn_items($box['fields'] ?? null), 'field', fgn_requested_name($box)];
    }
    while ($lists !== []) {
        [$items, $kind, $path] = array_pop($lists);
        $error = fgn_check_siblings($items, $kind, $path, $seen);
        if ($error !== null) {
            return $error;
        }
        foreach ($items as $item) {
            array_push($lists, ...fgn_nested_lists($item, $kind, $path . '.' . fgn_requested_name($item)));
        }
    }
    return null;
}

/**
 * The child / block / block-field lists directly under one item.
 *
 * @param array<string, mixed> $item
 * @return list<array{0: list<array<string, mixed>>, 1: string, 2: string}>
 */
function fgn_nested_lists(array $item, string $kind, string $path): array
{
    if ($kind === 'block') {
        return [[fgn_items($item['fields'] ?? null), 'field', $path]];
    }
    return [
        [fgn_items($item['children'] ?? null), 'field', $path],
        [fgn_items($item['blocks'] ?? null), 'block', $path],
    ];
}

/**
 * @param list<array<string, mixed>> $items siblings
 * @param array<string, array<string, string>> $seen kind => id => path, across the payload
 */
function fgn_check_siblings(array $items, string $kind, string $path, array &$seen): ?WP_Error
{
    $names = [];
    foreach ($items as $item) {
        $name = fgn_slug(fgn_requested_name($item));
        $item_path = $path . '.' . $name;
        if (isset($names[$name])) {
            return new WP_Error('acpt_duplicate_sibling_name', sprintf(
                /* translators: %s: box.field path. */
                __('%s appears twice under the same parent. Give each sibling its own name.', domain: 'novamira-pro'),
                $item_path,
            ));
        }
        $names[$name] = true;
        $id = (string) ($item['id'] ?? '');
        if (isset($seen[$kind][$id])) {
            return new WP_Error('acpt_duplicate_item', sprintf(
                /* translators: 1: box.field path, 2: other box.field path, 3: id. */
                __(
                    '%1$s and %2$s are the same stored item (id "%3$s"): ACPT would keep only one of them. Send each item once, with its own id or none.',
                    domain: 'novamira-pro',
                ),
                $item_path,
                $seen[$kind][$id],
                $id,
            ));
        }
        $seen[$kind][$id] = $item_path;
    }
    return null;
}

// ---------------------------------------------------------------------------
// Read-back report
// ---------------------------------------------------------------------------

/**
 * The array entries of a list of requested box / field / block definitions.
 *
 * @param mixed $items
 * @return list<array<string, mixed>>
 */
function fgn_items(mixed $items): array
{
    if (!is_array($items)) {
        return [];
    }
    $out = [];
    /** @var mixed $item */
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        /** @var array<string, mixed> $item */
        $out[] = $item;
    }
    return $out;
}

/**
 * Every stored field (any depth) and block of a group, by id, with the id of
 * the box / field / block each one actually sits in and its stored path.
 *
 * @return array{fields: array<string, \ACPT\Core\Models\Meta\MetaFieldModel>, blocks: array<string, \ACPT\Core\Models\Meta\MetaFieldBlockModel>, boxes: array<string, \ACPT\Core\Models\Meta\MetaBoxModel>, parents: array<string, string>, paths: array<string, string>}
 */
function fgn_stored_by_id(\ACPT\Core\Models\Meta\MetaGroupModel $group): array
{
    $out = ['fields' => [], 'blocks' => [], 'boxes' => [], 'parents' => [], 'paths' => []];
    $queue = [];
    foreach ($group->getBoxes() as $box) {
        $out['boxes'][$box->getId()] = $box;
        $out['paths'][$box->getId()] = $box->getName();
        foreach ($box->getFields() as $field) {
            $queue[] = [$field, $box->getId(), $box->getName()];
        }
    }
    while ($queue !== []) {
        [$field, $parent, $path] = array_pop($queue);
        $id = $field->getId();
        $path .= '.' . $field->getName();
        $out['fields'][$id] = $field;
        $out['parents'][$id] = $parent;
        $out['paths'][$id] = $path;
        foreach ($field->getChildren() as $child) {
            $queue[] = [$child, $id, $path];
        }
        foreach ($field->getBlocks() as $block) {
            $block_path = $path . '.' . $block->getName();
            $out['blocks'][$block->getId()] = $block;
            $out['parents'][$block->getId()] = $id;
            $out['paths'][$block->getId()] = $block_path;
            foreach ($block->getFields() as $block_field) {
                $queue[] = [$block_field, $block->getId(), $block_path];
            }
        }
    }
    return $out;
}

/**
 * Record one requested item: renamed when stored under another name, missing
 * (with where the id actually sits, if anywhere) when not stored under the
 * requested parent.
 *
 * @param array{fields: array<string, \ACPT\Core\Models\Meta\MetaFieldModel>, blocks: array<string, \ACPT\Core\Models\Meta\MetaFieldBlockModel>, boxes: array<string, \ACPT\Core\Models\Meta\MetaBoxModel>, parents: array<string, string>, paths: array<string, string>} $stored
 * @param array{renamed: list<array{requested: string, stored: string}>, missing: list<array{path: string, id: string, stored_at: string|null}>, options: list<array{path: string, option: string, sent: string, stored: string|null}>} $report
 */
function fgn_record(string $requested_path, ?string $stored_path, string $id, array $stored, array &$report): void
{
    if ($stored_path === null) {
        $report['missing'][] = ['path' => $requested_path, 'id' => $id, 'stored_at' => $stored['paths'][$id] ?? null];
        return;
    }
    if ($requested_path !== $stored_path) {
        $report['renamed'][] = ['requested' => $requested_path, 'stored' => $stored_path];
    }
}

/**
 * Compare a requested field (with pinned ids at every depth) with what ACPT
 * stored under the same id, in the same parent, including its advanced
 * option values. Items are matched by id, never by name.
 *
 * @param array<string, mixed> $field
 * @param array{fields: array<string, \ACPT\Core\Models\Meta\MetaFieldModel>, blocks: array<string, \ACPT\Core\Models\Meta\MetaFieldBlockModel>, boxes: array<string, \ACPT\Core\Models\Meta\MetaBoxModel>, parents: array<string, string>, paths: array<string, string>} $stored
 * @param array{req: string, sto: string, id: string} $parent requested path, stored path and id of the parent
 * @param array{renamed: list<array{requested: string, stored: string}>, missing: list<array{path: string, id: string, stored_at: string|null}>, options: list<array{path: string, option: string, sent: string, stored: string|null}>} $report
 */
function fgn_compare_field(array $field, array $stored, array $parent, array &$report): void
{
    $id = (string) ($field['id'] ?? '');
    $req = $parent['req'] . '.' . fgn_requested_name($field);
    $stored_field = ($stored['parents'][$id] ?? null) === $parent['id'] ? $stored['fields'][$id] ?? null : null;
    $sto = $stored_field !== null ? $parent['sto'] . '.' . $stored_field->getName() : null;
    fgn_record($req, $sto, $id, $stored, $report);
    if ($stored_field === null || $sto === null) {
        return;
    }
    foreach (fgn_option_mismatches($field, $stored_field) as $mismatch) {
        $report['options'][] = ['path' => $sto] + $mismatch;
    }
    foreach (fgn_items($field['children'] ?? null) as $child) {
        fgn_compare_field($child, $stored, ['req' => $req, 'sto' => $sto, 'id' => $id], $report);
    }
    foreach (fgn_items($field['blocks'] ?? null) as $block) {
        fgn_compare_block($block, $stored, ['req' => $req, 'sto' => $sto, 'id' => $id], $report);
    }
}

/**
 * A stored or sent advanced option value in one comparable form: arrays
 * (stored serialized) as JSON, booleans as "1" / "", everything else as a
 * string. Null means absent.
 */
function fgn_canonical_option(mixed $value): ?string
{
    /** @var mixed $value */
    $value = fgn_unserialized($value);
    return match (true) {
        $value === null => null,
        is_bool($value) => $value ? '1' : '',
        is_array($value) => (string) wp_json_encode($value),
        is_scalar($value) => (string) $value,
        default => '',
    };
}

/**
 * ACPT stores array option values serialized.
 */
function fgn_unserialized(mixed $value): mixed
{
    return is_string($value) && is_serialized($value) ? maybe_unserialize($value) : $value;
}

/**
 * Advanced options whose stored value differs from the value sent (an empty
 * value sent means the option must end up empty or absent; a stored "0" only
 * counts as empty when false was sent). ACPT keeps only
 * the option keys it knows for a field and may skip a write without an error.
 *
 * @param array<string, mixed> $field
 * @return list<array{option: string, sent: string, stored: string|null}>
 */
function fgn_option_mismatches(array $field, \ACPT\Core\Models\Meta\MetaFieldModel $stored_field): array
{
    $stored = [];
    foreach ($stored_field->getAdvancedOptions() as $option) {
        $stored[$option->getKey()] = fgn_canonical_option($option->getValue());
    }
    $mismatches = [];
    /** @var mixed $sent_options */
    $sent_options = $field['advancedOptions'] ?? $field['advanced_options'] ?? [];
    foreach (fgn_items($sent_options) as $option) {
        $key = (string) ($option['key'] ?? '');
        /** @var mixed $raw */
        $raw = $option['value'] ?? null;
        $sent = fgn_canonical_option($raw) ?? '';
        $have = $stored[$key] ?? null;
        // An empty value matches an empty or absent option; "0" only stands for
        // "off" when false was sent (a numeric or text option set to 0 is a value).
        $empty_ok = $sent === '' && in_array($have, $raw === false ? [null, '', '0'] : [null, ''], strict: true);
        if ($have === $sent || $empty_ok) {
            continue;
        }
        $mismatches[] = ['option' => $key, 'sent' => $sent, 'stored' => $have];
    }
    return $mismatches;
}

/**
 * Compare a requested flexible block, and its fields, with the stored block of
 * the same id in the same field.
 *
 * @param array<string, mixed> $block
 * @param array{fields: array<string, \ACPT\Core\Models\Meta\MetaFieldModel>, blocks: array<string, \ACPT\Core\Models\Meta\MetaFieldBlockModel>, boxes: array<string, \ACPT\Core\Models\Meta\MetaBoxModel>, parents: array<string, string>, paths: array<string, string>} $stored
 * @param array{req: string, sto: string, id: string} $parent the field the block belongs to
 * @param array{renamed: list<array{requested: string, stored: string}>, missing: list<array{path: string, id: string, stored_at: string|null}>, options: list<array{path: string, option: string, sent: string, stored: string|null}>} $report
 */
function fgn_compare_block(array $block, array $stored, array $parent, array &$report): void
{
    $block_id = (string) ($block['id'] ?? '');
    $req = $parent['req'] . '.' . fgn_requested_name($block);
    $stored_block = ($stored['parents'][$block_id] ?? null) === $parent['id']
        ? $stored['blocks'][$block_id] ?? null
        : null;
    $sto = $stored_block !== null ? $parent['sto'] . '.' . $stored_block->getName() : null;
    fgn_record($req, $sto, $block_id, $stored, $report);
    if ($sto === null) {
        return;
    }
    foreach (fgn_items($block['fields'] ?? null) as $block_field) {
        fgn_compare_field($block_field, $stored, ['req' => $req, 'sto' => $sto, 'id' => $block_id], $report);
    }
}

/**
 * Read a saved group back and report every box / field / child / block stored
 * under another name, every requested item not stored where requested (with
 * its actual place, if any), and every advanced option value that differs.
 *
 * @param list<array<string, mixed>> $requested_boxes boxes with pinned ids
 * @return array{renamed: list<array{requested: string, stored: string}>, missing: list<array{path: string, id: string, stored_at: string|null}>, options: list<array{path: string, option: string, sent: string, stored: string|null}>}
 */
function acpt_field_group_save_report(\ACPT\Core\Models\Meta\MetaGroupModel $group, array $requested_boxes): array
{
    $report = ['renamed' => [], 'missing' => [], 'options' => []];
    $stored = fgn_stored_by_id($group);
    foreach ($requested_boxes as $box) {
        $id = (string) ($box['id'] ?? '');
        $req_box = fgn_requested_name($box);
        $stored_box = $stored['boxes'][$id] ?? null;
        fgn_record($req_box, $stored_box?->getName(), $id, $stored, $report);
        if ($stored_box === null) {
            continue;
        }
        $parent = ['req' => $req_box, 'sto' => $stored_box->getName(), 'id' => $id];
        foreach (fgn_items($box['fields'] ?? null) as $field) {
            fgn_compare_field($field, $stored, $parent, $report);
        }
    }
    return $report;
}

/**
 * The stored group with this id, if any.
 */
function acpt_group_by_id(string $group_id): ?\ACPT\Core\Models\Meta\MetaGroupModel
{
    foreach (\ACPT\Core\Repository\MetaRepository::get(['id' => $group_id]) as $group) {
        if ($group->getId() === $group_id) {
            return $group;
        }
    }
    return null;
}

/** Recovery data keeps at most this many entries per list (the rest is counted in "omitted"). */
const FGN_RECOVERY_MAX_ENTRIES = 10;

/** Recovery data shortens longer string values to this many characters. */
const FGN_RECOVERY_MAX_VALUE = 120;

/**
 * Compact recovery data for a failed or incomplete save: the group as stored
 * (or null), requested items not stored where requested (id and actual
 * place), option values that differ, and renames. Bounded: at most
 * FGN_RECOVERY_MAX_ENTRIES entries per list with shortened values; the number
 * of entries left out is reported, with the ability that shows the rest.
 *
 * @param array{renamed: list<array{requested: string, stored: string}>, missing: list<array{path: string, id: string, stored_at: string|null}>, options: list<array{path: string, option: string, sent: string, stored: string|null}>} $report
 * @return array<string, mixed>
 */
function fgn_recovery_data(?\ACPT\Core\Models\Meta\MetaGroupModel $group, array $report): array
{
    $data = ['group' => $group === null ? null : ['id' => $group->getId(), 'name' => $group->getName()]];
    $omitted = [];
    foreach (['missing' => 'missing', 'options' => 'option_mismatches', 'renamed' => 'renamed'] as $key => $out_key) {
        if ($report[$key] === []) {
            continue;
        }
        $data[$out_key] = array_map(
            static fn(array $entry): array => fgn_preview_entry($entry),
            array_slice($report[$key], offset: 0, length: FGN_RECOVERY_MAX_ENTRIES),
        );
        $left = count($report[$key]) - FGN_RECOVERY_MAX_ENTRIES;
        if ($left > 0) {
            $omitted[$out_key] = $left;
        }
    }
    if ($omitted !== []) {
        $data['omitted'] = $omitted;
    }
    $paths = fgn_omitted_paths($report);
    $shortened = fgn_any_shortened($report);
    if ($group !== null && ($paths !== [] || $shortened)) {
        // The exact call (plus path: an entry's path above, or one of these) that
        // returns the stored state of everything shortened or left out.
        $data['more'] = [
            'ability' => 'novamira/acpt-get-field-group-schema',
            'params' => ['name' => $group->getName(), 'full_definition' => true],
        ];
        if ($paths !== []) {
            $data['more']['omitted_paths'] = array_slice($paths, offset: 0, length: FGN_RECOVERY_MAX_ENTRIES);
        }
        if (count($paths) > FGN_RECOVERY_MAX_ENTRIES) {
            $data['more']['omitted_paths_not_listed'] = count($paths) - FGN_RECOVERY_MAX_ENTRIES;
        }
    }
    return $data;
}

/**
 * Whether any entry shown in the recovery data had a value shortened.
 *
 * @param array{renamed: list<array{requested: string, stored: string}>, missing: list<array{path: string, id: string, stored_at: string|null}>, options: list<array{path: string, option: string, sent: string, stored: string|null}>} $report
 */
function fgn_any_shortened(array $report): bool
{
    foreach ([$report['missing'], $report['options'], $report['renamed']] as $entries) {
        foreach (array_slice($entries, offset: 0, length: FGN_RECOVERY_MAX_ENTRIES) as $entry) {
            if (fgn_preview_entry($entry) !== $entry) {
                return true;
            }
        }
    }
    return false;
}

/**
 * Stored paths (usable as the path parameter of get-field-group-schema) of the
 * recovery entries left out of the recovery data.
 *
 * @param array{renamed: list<array{requested: string, stored: string}>, missing: list<array{path: string, id: string, stored_at: string|null}>, options: list<array{path: string, option: string, sent: string, stored: string|null}>} $report
 * @return list<string>
 */
function fgn_omitted_paths(array $report): array
{
    $paths = [];
    foreach ([$report['missing'], $report['options'], $report['renamed']] as $entries) {
        foreach ($entries as $k => $entry) {
            // Option mismatch: the field's path; rename: the stored path; missing
            // item: where it is actually stored (if anywhere).
            $path = match (true) {
                array_key_exists('option', $entry) => $entry['path'] ?? null,
                array_key_exists('stored_at', $entry) => $entry['stored_at'],
                default => $entry['stored'] ?? null,
            };
            if ($path === null || $k < FGN_RECOVERY_MAX_ENTRIES) {
                continue;
            }
            $paths[$path] = true;
        }
    }
    return array_keys($paths);
}

/**
 * One recovery entry with every string value shortened to a preview.
 *
 * @param array<string, string|null> $entry
 * @return array<string, string|null>
 */
function fgn_preview_entry(array $entry): array
{
    foreach ($entry as $key => $value) {
        if ($value === null || mb_strlen($value, encoding: 'UTF-8') <= FGN_RECOVERY_MAX_VALUE) {
            continue;
        }
        $entry[$key] = sprintf(
            '%s… (%d characters)',
            mb_substr($value, start: 0, length: FGN_RECOVERY_MAX_VALUE, encoding: 'UTF-8'),
            mb_strlen($value, encoding: 'UTF-8'),
        );
    }
    return $entry;
}

/**
 * Turn a save into the ability response, reading the group back by its pinned
 * id first. A failed save (including an error ACPT only logged), a group that
 * cannot be found, any requested item not stored where requested, or any
 * advanced option stored with another value makes the response an error with
 * compact recovery data in its message, never a success.
 *
 * @param array{success: bool, error?: string} $result
 * @param list<array<string, mixed>> $requested_boxes boxes with pinned ids
 * @return array<string, mixed>|WP_Error
 */
function acpt_finish_save(
    array $result,
    string $group_id,
    string $requested_name,
    array $requested_boxes,
    string $failed_code,
): array|WP_Error {
    $group = acpt_group_by_id($group_id);
    $report = $group === null
        ? ['renamed' => [], 'missing' => [], 'options' => []]
        : acpt_field_group_save_report($group, $requested_boxes);
    $complete = $group !== null && $report['missing'] === [] && $report['options'] === [];
    if ($result['success'] && $complete) {
        $response = ['success' => true, 'name' => $group->getName()];
        if ($group->getName() !== $requested_name) {
            $response['requested_name'] = $requested_name;
        }
        if ($report['renamed'] !== []) {
            $response['renamed'] = $report['renamed'];
        }
        if ($report['renamed'] !== [] || $group->getName() !== $requested_name) {
            $response['note'] = __(
                'ACPT stored some names differently from the request (it normalizes names and keeps box, field and block names unique across the site). Use the stored names (box.field paths) with read-values, write-values and edit-field-group.',
                domain: 'novamira-pro',
            );
        }
        return $response;
    }
    $data = fgn_recovery_data($group, $report);
    $reason = match (true) {
        !$result['success'] => $result['error'] ?? __('ACPT did not save the field group.', domain: 'novamira-pro'),
        $group === null => __('ACPT reported the save, but the field group is not stored.', domain: 'novamira-pro'),
        default => __('ACPT did not store everything sent.', domain: 'novamira-pro'),
    };
    return new WP_Error(
        $result['success'] ? 'acpt_field_group_incomplete' : $failed_code,
        sprintf(
            /* translators: 1: reason, 2: compact JSON recovery data. */
            __(
                '%1$s Some changes may already be stored: re-read the group (novamira/acpt-get-field-group-schema with full_definition=true, or the call under "more") and reconcile before retrying. Recovery data: %2$s',
                domain: 'novamira-pro',
            ),
            $reason,
            (string) wp_json_encode($data, JSON_UNESCAPED_SLASHES),
        ),
        $data,
    );
}
