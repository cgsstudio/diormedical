<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\KadenceBlocks;

use WP_Block_Type;
use WP_Block_Type_Registry;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Kadence Blocks introspection abilities.
 */

/**
 * Returns every block in the `kadence/*` namespace, keyed by block name.
 *
 * @return array<string, WP_Block_Type>
 */
function kb_registered_blocks(): array
{
    /** @var array<string, WP_Block_Type> $all */
    $all = WP_Block_Type_Registry::get_instance()->get_all_registered();
    $out = [];
    foreach ($all as $name => $block) {
        if (!str_starts_with($name, 'kadence/')) {
            continue;
        }
        $out[$name] = $block;
    }
    return $out;
}

/**
 * Returns 'pro' when the block's render_callback resolves to a class in
 * the Kadence Blocks Pro plugin namespace (`Kadence_Blocks_Pro_*` /
 * `KadenceWP\KadenceBlocksPro\*`), 'free' otherwise. Kadence Blocks Pro
 * extends the same `kadence/*` namespace as the free plugin instead of
 * registering its own namespace, so this is the only reliable way to
 * tell the two apart from the runtime registry.
 */
function kb_block_family(WP_Block_Type $block): string
{
    $class = kb_render_callback_class($block);
    if ($class === '') {
        return 'free';
    }
    if (stripos($class, needle: 'KadenceBlocksPro') !== false) {
        return 'pro';
    }
    // Require the trailing underscore so the substring match doesn't
    // fire on free-plugin classes that just happen to start with
    // `Kadence_Blocks_Pro` (e.g. `Kadence_Blocks_Progress_Bar_Block`,
    // a free block whose class name embeds "Pro" as a literal substring
    // of "Progress"). The Pro plugin's actual classes are named
    // `Kadence_Blocks_Pro_<X>_Block` — the underscore reliably separates
    // the prefix from the rest.
    if (str_contains($class, 'Kadence_Blocks_Pro_')) {
        return 'pro';
    }
    return 'free';
}

/**
 * Extracts a class-or-callable name from a block's render_callback, or
 * '' when the callback shape is not recognised. WP's render_callback is
 * a `callable` — typically `[$instance, 'method']` for class-based
 * renderers or a function-name string. We surface the class name so the
 * family detection can pattern-match for the Pro-plugin namespace.
 */
function kb_render_callback_class(WP_Block_Type $block): string
{
    /** @var mixed $cb */
    $cb = $block->render_callback;
    if (is_string($cb)) {
        return $cb;
    }
    if (is_array($cb) && isset($cb[0])) {
        if (is_object($cb[0])) {
            return $cb[0]::class;
        }
        if (is_string($cb[0])) {
            return $cb[0];
        }
    }
    return '';
}

/**
 * Normalises a `parent` / `ancestor` value to `list<string>`. WP stores
 * either null, a single string, or a list — abilities surface a
 * consistent list shape so the agent does not have to branch.
 *
 * @param mixed $value
 * @return list<string>
 */
function kb_string_list(mixed $value): array
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
 * Builds the compact list record for a single block — what `list-blocks`
 * returns per entry.
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
function kb_compact_record(WP_Block_Type $block): array
{
    $title = $block->title !== '' ? $block->title : $block->name;
    return [
        'name' => $block->name,
        'title' => $title,
        'family' => kb_block_family($block),
        'category' => $block->category ?? '',
        'is_dynamic' => $block->is_dynamic(),
        'attributes_count' => count($block->attributes ?? []),
        'parent' => kb_string_list($block->parent ?? null),
        'ancestor' => kb_string_list($block->ancestor ?? null),
    ];
}

/**
 * Builds the full schema record for a single block — what `get-block-schema`
 * returns. Includes every attribute's type / default / enum lifted from
 * `WP_Block_Type::$attributes`, plus parent / ancestor / context relations
 * and the supports map.
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
function kb_full_schema(WP_Block_Type $block): array
{
    $title = $block->title !== '' ? $block->title : $block->name;
    /** @var array<string, array<string, mixed>> $attrs */
    $attrs = $block->attributes ?? [];
    $uses_context = array_values($block->get_uses_context());
    /** @var array<string, mixed> $provides_context */
    $provides_context = is_array($block->provides_context ?? null) ? $block->provides_context : [];
    /** @var array<string, mixed> $supports */
    $supports = is_array($block->supports ?? null) ? $block->supports : [];

    return [
        'name' => $block->name,
        'title' => $title,
        'family' => kb_block_family($block),
        'category' => $block->category ?? '',
        'is_dynamic' => $block->is_dynamic(),
        'attributes' => $attrs,
        'parent' => kb_string_list($block->parent ?? null),
        'ancestor' => kb_string_list($block->ancestor ?? null),
        'uses_context' => $uses_context,
        'provides_context' => $provides_context,
        'supports' => $supports,
    ];
}
