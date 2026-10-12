<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Taxonomy + term helpers for the Voxel taxonomy abilities (Sprint 3).
 *
 * Voxel taxonomies are plain WordPress taxonomies; terms are plain WP terms
 * enriched with a fixed set of term metas (voxel_icon, voxel_image, voxel_color,
 * voxel_area) plus an integer order column (wp_terms.voxel_order). Create/edit/
 * delete ride on wp_insert_term / wp_update_term / wp_delete_term, which fire the
 * Voxel hooks that bump the taxonomy cache version; the metas and order column are
 * written directly (the importer pattern), and the version is bumped explicitly as
 * a safety net after our direct writes.
 */

// ---------------------------------------------------------------------------
// Schemas (shared by the term abilities)
// ---------------------------------------------------------------------------

/**
 * The Voxel-extra input properties shared by create-term and edit-term.
 *
 * @return array<string, mixed>
 */
function vx_term_extra_props(): array
{
    return [
        'slug' => ['type' => 'string', 'description' => 'URL slug (auto-generated from the name if omitted).'],
        'parent' => [
            'type' => 'integer',
            'minimum' => 0,
            'description' => 'Parent term id (0 = top-level). Must exist in the taxonomy.',
        ],
        'description' => ['type' => 'string', 'description' => 'Term description.'],
        'icon' => [
            'type' => 'string',
            'description' => 'Voxel icon string (e.g. "svg:123" or an icon class). Empty clears it.',
        ],
        'color' => ['type' => 'string', 'description' => 'Color, e.g. "#6933ff". Empty clears it.'],
        'image' => ['type' => 'integer', 'minimum' => 0, 'description' => 'Image attachment id. 0 clears it.'],
        'area' => [
            'type' => 'object',
            'additionalProperties' => true,
            'description' => 'Geo bounds {address, swlat, swlng, nelat, nelng}.',
        ],
        'order' => [
            'type' => 'integer',
            'minimum' => 0,
            'maximum' => 2_147_483_647,
            'description' => 'Sort order (the voxel_order column, a 32-bit integer).',
        ],
    ];
}

/**
 * Input schema for create-term.
 *
 * @return array<string, mixed>
 */
function vx_term_create_input_schema(): array
{
    $props =
        [
            'taxonomy' => ['type' => 'string', 'description' => 'The taxonomy key, e.g. "places_category".'],
            'name' => ['type' => 'string', 'description' => 'The term name.'],
        ] + vx_term_extra_props();
    return [
        'type' => 'object',
        'properties' => $props,
        'required' => ['taxonomy', 'name'],
        'additionalProperties' => false,
    ];
}

/**
 * Input schema for edit-term.
 *
 * @return array<string, mixed>
 */
function vx_term_edit_input_schema(): array
{
    $props =
        [
            'term_id' => ['type' => 'integer', 'minimum' => 1, 'description' => 'The term id to edit.'],
            'name' => ['type' => 'string', 'description' => 'New name.'],
        ] + vx_term_extra_props();
    return ['type' => 'object', 'properties' => $props, 'required' => ['term_id'], 'additionalProperties' => false];
}

/**
 * The output schema shared by get-term / create-term / edit-term.
 *
 * @return array<string, mixed>
 */
function vx_term_output_schema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'taxonomy' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'parent' => ['type' => 'integer'],
            'ancestor_ids' => ['type' => 'array', 'items' => ['type' => 'integer']],
            'order' => ['type' => 'integer'],
            'icon' => ['type' => 'string'],
            'color' => ['type' => 'string'],
            'image_id' => ['type' => 'integer'],
            'area' => ['type' => ['object', 'null'], 'additionalProperties' => true],
            'post_counts' => ['type' => 'object', 'additionalProperties' => true],
        ],
        'required' => [
            'id',
            'taxonomy',
            'name',
            'slug',
            'description',
            'parent',
            'ancestor_ids',
            'order',
            'icon',
            'color',
            'image_id',
            'post_counts',
        ],
        'additionalProperties' => false,
    ];
}

// ---------------------------------------------------------------------------
// Gates
// ---------------------------------------------------------------------------

/**
 * Resolve a Voxel-managed taxonomy by key, or a WP_Error.
 *
 * @return \Voxel\Taxonomy|WP_Error
 */
function vx_taxonomy_or_error(string $key): object
{
    if ($key === '') {
        return vx_invalid(__('A non-empty taxonomy key is required.', domain: 'novamira-pro'));
    }
    $taxonomy = \Voxel\Taxonomy::get($key);
    if (!is_object($taxonomy) || !$taxonomy->is_managed_by_voxel()) {
        return vx_not_found('Voxel taxonomy', $key);
    }
    return $taxonomy;
}

/**
 * Resolve a term belonging to a Voxel-managed taxonomy, or a WP_Error.
 *
 * @return \Voxel\Term|WP_Error
 */
function vx_term_or_error(int $term_id): object
{
    if ($term_id <= 0) {
        return vx_invalid(__('A positive term id is required.', domain: 'novamira-pro'));
    }
    $term = \Voxel\Term::get($term_id);
    if (!is_object($term) || !$term->is_managed_by_voxel()) {
        return vx_not_found('Voxel term', (string) $term_id);
    }
    return $term;
}

// ---------------------------------------------------------------------------
// Projections
// ---------------------------------------------------------------------------

/**
 * Compact row for a Voxel taxonomy.
 *
 * @param \Voxel\Taxonomy $taxonomy
 * @return array{key: string, label: string, singular: string, plural: string, public: bool, hierarchical: bool, post_types: list<string>, terms_count: int}
 */
function vx_taxonomy_row(object $taxonomy): array
{
    $key = $taxonomy->get_key();
    $post_types = [];
    foreach ($taxonomy->get_post_types() as $pt) {
        $post_types[] = $pt;
    }
    return [
        'key' => $key,
        'label' => $taxonomy->get_label(),
        'singular' => $taxonomy->get_singular_name(),
        'plural' => $taxonomy->get_plural_name(),
        'public' => $taxonomy->is_public(),
        'hierarchical' => is_taxonomy_hierarchical($key),
        'post_types' => $post_types,
        'terms_count' => vx_taxonomy_terms_count($key),
    ];
}

/**
 * Number of terms in a taxonomy (all, including empty).
 */
function vx_taxonomy_terms_count(string $key): int
{
    $count = wp_count_terms(['taxonomy' => $key, 'hide_empty' => false]);
    return is_numeric($count) ? (int) $count : 0;
}

/**
 * Compact row for a term.
 *
 * @param WP_Term $term
 * @return array{id: int, name: string, slug: string, parent: int, count: int, order: int}
 */
function vx_term_row(WP_Term $term, int $order): array
{
    return [
        'id' => (int) $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'parent' => (int) $term->parent,
        'count' => (int) $term->count,
        'order' => $order,
    ];
}

/**
 * Full read of a Voxel term including its Voxel metas.
 *
 * @param \Voxel\Term $term
 * @return array<string, mixed>
 */
function vx_term_full(object $term): array
{
    $id = $term->get_id();
    /** @var mixed $image_id */
    $image_id = $term->get_image_id();
    $area = $term->get_area();

    return [
        'id' => $id,
        'taxonomy' => is_object($term->taxonomy) ? $term->taxonomy->get_key() : '',
        'name' => $term->get_label(),
        'slug' => $term->get_slug(),
        'description' => $term->get_description(),
        'parent' => $term->get_parent_id(),
        'ancestor_ids' => vx_int_list($term->get_ancestor_ids()),
        'order' => vx_read_term_order($id),
        'icon' => $term->get_icon(),
        'color' => $term->get_color(),
        'image_id' => is_numeric($image_id) ? (int) $image_id : 0,
        // get_area() always returns a 5-key null-filled array; only report it when
        // the meta actually exists so an unset area is a true null.
        'area' => metadata_exists('term', $id, meta_key: 'voxel_area') ? $area : null,
        // Cast to object so an empty map serializes as {} (matching the object
        // schema), never []. Read the cached meta directly rather than
        // Term::post_counts->get_counts(), which writes on a cache miss (this
        // ability is readonly).
        'post_counts' => (object) vx_read_term_post_counts($id),
    ];
}

/**
 * Read a term's cached per-post-type publish counts (voxel:post_counts) without
 * triggering Voxel's cache rebuild (keeps the read side truly read-only).
 *
 * @return array<string, int>
 */
function vx_read_term_post_counts(int $term_id): array
{
    /** @var mixed $raw */
    $raw = get_term_meta($term_id, key: 'voxel:post_counts', single: true);
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode($raw, associative: true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    /** @var mixed $value */
    foreach ($decoded as $key => $value) {
        if (!is_numeric($value)) {
            continue;
        }
        $out[(string) $key] = (int) $value;
    }
    return $out;
}

/**
 * Coerce a mixed value into a list of ints.
 *
 * @param mixed $value
 * @return list<int>
 */
function vx_int_list(mixed $value): array
{
    if (!is_array($value)) {
        return [];
    }
    $out = [];
    /** @var mixed $item */
    foreach ($value as $item) {
        if (!is_numeric($item)) {
            continue;
        }
        $out[] = (int) $item;
    }
    return $out;
}

// ---------------------------------------------------------------------------
// Order column (wp_terms.voxel_order)
// ---------------------------------------------------------------------------

/**
 * Read a term's order from the wp_terms.voxel_order column (0 when unset).
 */
function vx_read_term_order(int $term_id): int
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    // term_id is cast to int, so this is injection-safe without prepare (which
    // requires a literal-string query and cannot interpolate $wpdb->terms).
    $value = $wpdb->get_var("SELECT voxel_order FROM {$wpdb->terms} WHERE term_id = " . (int) $term_id);
    return is_numeric($value) ? (int) $value : 0;
}

/**
 * Batch-read the order column for a set of term ids.
 *
 * @param list<int> $term_ids
 * @return array<int, int> term id => order
 */
function vx_term_orders(array $term_ids): array
{
    if ($term_ids === []) {
        return [];
    }
    global $wpdb;
    /** @var \wpdb $wpdb */
    $in = implode(', ', array_map('intval', $term_ids)); // ints only — injection-safe
    $rows = $wpdb->get_results("SELECT term_id, voxel_order FROM {$wpdb->terms} WHERE term_id IN ({$in})");
    $map = [];
    if (is_array($rows)) {
        foreach ($rows as $row) {
            if (!is_object($row)) {
                continue;
            }
            $map[(int) $row->term_id] = (int) $row->voxel_order;
        }
    }
    return $map;
}

/**
 * Persist a term's order to the wp_terms.voxel_order column.
 */
function vx_set_term_order(int $term_id, int $order): void
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $wpdb->update($wpdb->terms, ['voxel_order' => $order], ['term_id' => $term_id]);
}

// ---------------------------------------------------------------------------
// Meta writes
// ---------------------------------------------------------------------------

/**
 * Write the Voxel term extras the caller provided (icon / color / image / area /
 * order). Only keys present in $input are touched; an empty value clears the meta.
 *
 * @param array<string, mixed> $input the ability input
 */
function vx_write_term_extras(int $term_id, array $input): void
{
    if (array_key_exists('icon', $input)) {
        vx_write_string_meta($term_id, meta_key: 'voxel_icon', value: $input['icon']);
    }
    if (array_key_exists('color', $input)) {
        vx_write_string_meta($term_id, meta_key: 'voxel_color', value: $input['color']);
    }
    if (array_key_exists('image', $input)) {
        $image = vx_coerce_int($input['image']);
        $image !== null && $image > 0
            ? update_term_meta($term_id, meta_key: 'voxel_image', meta_value: $image)
            : delete_term_meta($term_id, meta_key: 'voxel_image');
    }
    if (array_key_exists('area', $input)) {
        vx_write_term_area($term_id, $input['area']);
    }
    $order = array_key_exists('order', $input) ? vx_coerce_int($input['order']) : null;
    if ($order !== null) {
        vx_set_term_order($term_id, $order);
    }
}

/**
 * Update or delete a plain-text term meta (delete when empty).
 *
 * @param mixed $value
 */
function vx_write_string_meta(int $term_id, string $meta_key, mixed $value): void
{
    $clean = is_string($value) ? sanitize_text_field($value) : '';
    $clean === '' ? delete_term_meta($term_id, $meta_key) : update_term_meta($term_id, $meta_key, $clean);
}

/**
 * Write the voxel_area geo-bounds meta ({address, swlat, swlng, nelat, nelng}),
 * or clear it when the value is empty/invalid. JSON is wp_slash-ed to survive
 * update_term_meta's stripslashes, matching Voxel core.
 *
 * @param mixed $area
 */
function vx_write_term_area(int $term_id, mixed $area): void
{
    if (!is_array($area) || $area === []) {
        delete_term_meta($term_id, meta_key: 'voxel_area');
        return;
    }
    $address = isset($area['address']) && is_string($area['address']) ? sanitize_text_field($area['address']) : '';
    $payload = [
        'address' => $address,
        'swlat' => vx_area_float($area, key: 'swlat'),
        'swlng' => vx_area_float($area, key: 'swlng'),
        'nelat' => vx_area_float($area, key: 'nelat'),
        'nelng' => vx_area_float($area, key: 'nelng'),
    ];
    update_term_meta($term_id, meta_key: 'voxel_area', meta_value: wp_slash((string) wp_json_encode($payload)));
}

/**
 * Read a float coordinate from a geo-bounds input array.
 *
 * @param array<array-key, mixed> $area
 */
function vx_area_float(array $area, string $key): float
{
    /** @var mixed $value */
    $value = $area[$key] ?? 0;
    return is_numeric($value) ? (float) $value : 0.0;
}

// ---------------------------------------------------------------------------
// Cache
// ---------------------------------------------------------------------------

/**
 * Bump the Voxel taxonomy cache version after a term change. The core term hooks
 * already do this, but direct meta/order writes do not fire them.
 */
function vx_bump_taxonomy_version(string $taxonomy_key): void
{
    $taxonomy = \Voxel\Taxonomy::get($taxonomy_key);
    if (is_object($taxonomy)) {
        $taxonomy->update_version();
    }
}

/**
 * Build the shared wp_insert_term / wp_update_term core args (slug, description,
 * parent) from input, validating the parent belongs to the taxonomy.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function vx_term_core_args(array $input, string $taxonomy): array|WP_Error
{
    $args = [];
    if (isset($input['slug']) && is_string($input['slug']) && $input['slug'] !== '') {
        $args['slug'] = sanitize_title($input['slug']);
    }
    if (array_key_exists('description', $input) && is_string($input['description'])) {
        $args['description'] = $input['description'];
    }
    if (array_key_exists('parent', $input)) {
        $parent = max(0, vx_coerce_int($input['parent']) ?? 0);
        if ($parent > 0 && !is_taxonomy_hierarchical($taxonomy)) {
            return vx_invalid(sprintf(
                /* translators: %s taxonomy key. */
                __('Taxonomy "%s" is not hierarchical; its terms cannot have a parent.', domain: 'novamira-pro'),
                $taxonomy,
            ));
        }
        if ($parent > 0 && !get_term($parent, $taxonomy) instanceof WP_Term) {
            return vx_invalid(sprintf(
                /* translators: %1$d parent id, %2$s taxonomy. */
                __('Parent term %1$d does not exist in taxonomy "%2$s".', domain: 'novamira-pro'),
                $parent,
                $taxonomy,
            ));
        }
        $args['parent'] = $parent;
    }
    return $args;
}

/**
 * Map a wp_insert_term / wp_update_term WP_Error to a Voxel ability error: a bad
 * slug/duplicate/reserved term is a 400, anything else a 500.
 */
function vx_map_term_core_error(WP_Error $error): WP_Error
{
    $client_codes = ['term_exists', 'duplicate_term_slug', 'empty_term_name', 'invalid_taxonomy', 'invalid_term_id'];
    if (in_array($error->get_error_code(), $client_codes, strict: true)) {
        return vx_invalid($error->get_error_message());
    }
    return vx_operation_failed($error->get_error_message());
}

/**
 * The write-result envelope for a term (shared by create/edit).
 *
 * @return array<string, mixed>|WP_Error
 */
function vx_term_write_result(int $term_id): array|WP_Error
{
    $term = \Voxel\Term::get($term_id);
    if (!is_object($term)) {
        return vx_operation_failed(__('The term was saved but could not be reloaded.', domain: 'novamira-pro'));
    }
    return vx_term_full($term);
}
