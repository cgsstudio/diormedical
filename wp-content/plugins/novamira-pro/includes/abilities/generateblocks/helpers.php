<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GenerateBlocks;

use WP_Block_Type;
use WP_Block_Type_Registry;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the GenerateBlocks introspection abilities. Pulls
 * block metadata from `WP_Block_Type_Registry` and shapes it into the
 * compact records the abilities return.
 */

/**
 * Returns every block currently registered in the `generateblocks/*` or
 * `generateblocks-pro/*` namespace. The array is keyed by block name.
 *
 * @return array<string, WP_Block_Type>
 */
function gb_registered_blocks(): array
{
    /** @var array<string, WP_Block_Type> $all */
    $all = WP_Block_Type_Registry::get_instance()->get_all_registered();
    $out = [];
    foreach ($all as $name => $block) {
        if (!str_starts_with($name, 'generateblocks/') && !str_starts_with($name, 'generateblocks-pro/')) {
            continue;
        }
        $out[$name] = $block;
    }
    return $out;
}

/**
 * Returns the family slug ('free' for `generateblocks/*`, 'pro' for
 * `generateblocks-pro/*`, '' for anything else). Lets callers tag each
 * record with its source without hardcoding the prefix.
 */
function gb_block_family(string $name): string
{
    if (str_starts_with($name, 'generateblocks-pro/')) {
        return 'pro';
    }
    if (str_starts_with($name, 'generateblocks/')) {
        return 'free';
    }
    return '';
}

/**
 * Builds the compact list record for a single block — what `list-blocks`
 * returns per entry. Keeps the catalogue lean: the per-attribute schema is
 * surfaced by `get-block-schema`, not here.
 *
 * @return array{
 *     name: string,
 *     title: string,
 *     family: string,
 *     category: string,
 *     is_dynamic: bool,
 *     attributes_count: int,
 *     parent: list<string>,
 *     ancestor: list<string>,
 * }
 */
function gb_compact_record(WP_Block_Type $block): array
{
    $title = $block->title !== '' ? $block->title : $block->name;
    return [
        'name' => $block->name,
        'title' => $title,
        'family' => gb_block_family($block->name),
        'category' => $block->category ?? '',
        'is_dynamic' => $block->is_dynamic(),
        'attributes_count' => count($block->attributes ?? []),
        'parent' => gb_string_list($block->parent ?? null),
        'ancestor' => gb_string_list($block->ancestor ?? null),
    ];
}

/**
 * Normalises a `parent` / `ancestor` value to a list<string>. WP stores
 * either null, a single string, or a list — the abilities surface a
 * consistent list shape so the agent doesn't need to branch.
 *
 * @param mixed $value
 * @return list<string>
 */
function gb_string_list(mixed $value): array
{
    if (is_string($value) && $value !== '') {
        return [$value];
    }
    if (!is_array($value)) {
        return [];
    }
    $out = [];
    /** @var mixed $v */
    foreach ($value as $v) {
        if (!is_string($v) || $v === '') {
            continue;
        }
        $out[] = $v;
    }
    return $out;
}

/**
 * Builds the full schema record for a single block — what `get-block-schema`
 * returns. Includes every attribute's type / default / enum lifted straight
 * from `WP_Block_Type::$attributes`, plus the parent / ancestor / context
 * relations and the supports map.
 *
 * @return array{
 *     name: string,
 *     title: string,
 *     family: string,
 *     category: string,
 *     is_dynamic: bool,
 *     attributes: array<string, array<string, mixed>>,
 *     parent: list<string>,
 *     ancestor: list<string>,
 *     uses_context: list<string>,
 *     provides_context: array<string, mixed>,
 *     supports: array<string, mixed>,
 * }
 */
function gb_full_schema(WP_Block_Type $block): array
{
    $title = $block->title !== '' ? $block->title : $block->name;
    /** @var array<string, array<string, mixed>> $attrs */
    $attrs = $block->attributes ?? [];
    // WP_Block_Type exposes uses_context via the get_uses_context() getter
    // (WP 6.5+ contract); the property access pattern triggers a stub-side
    // typing ambiguity in mago. array_values narrows the return shape to a
    // list as the function's declared return type expects.
    $uses_context = array_values($block->get_uses_context());
    /** @var array<string, mixed> $provides_context */
    $provides_context = is_array($block->provides_context ?? null) ? $block->provides_context : [];
    /** @var array<string, mixed> $supports */
    $supports = is_array($block->supports ?? null) ? $block->supports : [];

    return [
        'name' => $block->name,
        'title' => $title,
        'family' => gb_block_family($block->name),
        'category' => $block->category ?? '',
        'is_dynamic' => $block->is_dynamic(),
        'attributes' => $attrs,
        'parent' => gb_string_list($block->parent ?? null),
        'ancestor' => gb_string_list($block->ancestor ?? null),
        'uses_context' => $uses_context,
        'provides_context' => $provides_context,
        'supports' => $supports,
    ];
}
