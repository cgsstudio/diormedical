<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Woocommerce;

use WC_Product;
use WC_Product_Attribute;
use WC_Product_Variable;
use WC_Product_Variation;
use WP_Error;
use WP_Post;
use WP_Term;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Extract and coerce the `id` or `slug` product lookup key.
 * Returns the coerced key (int for numeric input, string otherwise),
 * or WP_Error when the key is missing or has an invalid type.
 *
 * @param array<string, mixed> $input
 * @return int|string|WP_Error
 */
function wc_resolve_product_key(array $input): int|string|WP_Error
{
    /** @var mixed $key */
    $key = $input['id'] ?? $input['slug'] ?? null;
    if ($key === null) {
        return wc_error('wc_invalid_input', __('`id` or `slug` is required.', domain: 'novamira-pro'));
    }
    if (!is_int($key) && !is_string($key) && !is_numeric($key)) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: actual type */
            __('`id` or `slug` must be an integer or string (got %1$s).', domain: 'novamira-pro'),
            gettype($key),
        ));
    }
    return is_numeric($key) ? (int) $key : $key;
}

/**
 * Reject a field whose value is not a string or numeric.
 * Returns WP_Error on the first non-scalar field found, null when all pass.
 *
 * @param array<string, mixed> $input
 * @param list<string>         $fields
 */
function wc_validate_scalar_string_fields(array $input, array $fields): ?WP_Error
{
    foreach ($fields as $field) {
        if (isset($input[$field]) && !is_string($input[$field]) && !is_numeric($input[$field])) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: field name, 2: actual type */
                __('`%1$s` must be a string (got %2$s).', domain: 'novamira-pro'),
                $field,
                gettype($input[$field]),
            ));
        }
    }
    return null;
}

/**
 * Reject a field whose string value is not in the allowed enum list.
 * When $field is "order", the comparison is case-insensitive.
 * Returns WP_Error when the value is non-string or not in $allowed.
 *
 * @param array<string, mixed> $input
 * @param list<string>         $allowed
 */
function wc_validate_enum_field(array $input, string $field, array $allowed): ?WP_Error
{
    if (!isset($input[$field])) {
        return null;
    }
    /** @var mixed $v */
    $v = $input[$field];
    $compare = $field === 'order' ? strtolower((string) $v) : (string) $v;
    if (!is_string($v) || !in_array($compare, $allowed, strict: true)) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: submitted value, 2: comma-separated list of allowed values, 3: field name */
            __('`%3$s` must be one of: %2$s (got "%1$s").', domain: 'novamira-pro'),
            is_scalar($v) ? (string) $v : gettype($v),
            implode(', ', $allowed),
            $field,
        ));
    }
    return null;
}

/**
 * Strict bool validation for boolean input fields.
 * Rejects string "true"/"false"/"1"/"0" — only native bool or int 0/1 are accepted.
 *
 * @param array<string, mixed> $input
 * @param list<string>         $fields
 */
function wc_strict_bool_check(array $input, array $fields): ?WP_Error
{
    foreach ($fields as $field) {
        if (!array_key_exists($field, $input)) {
            continue;
        }
        /** @var mixed $v */
        $v = $input[$field];
        if (is_bool($v) || $v === 1 || $v === 0) {
            continue;
        }
        return wc_error('wc_invalid_input', sprintf(
            /* translators: field name */
            __('`%s` must be true or false (bool), got %s.', domain: 'novamira-pro'),
            $field,
            is_scalar($v) || $v === null ? gettype($v) : gettype($v),
        ));
    }
    return null;
}

/**
 * Standard error map for the WooCommerce abilities.
 *
 * @return array<string, int>
 */
function wc_error_map(): array
{
    return [
        'wc_inactive' => 412,
        'wc_below_minimum_version' => 412,
        'wc_not_found' => 404,
        'wc_invalid_input' => 400,
        'wc_post_type_mismatch' => 400,
        'wc_internal_error' => 500,
        'wc_sku_exists' => 409,
        'wc_attachment_not_found' => 400,
        'wc_not_variable_parent' => 400,
        'wc_attribute_not_variable' => 400,
        'wc_option_not_on_parent' => 400,
        'wc_term_not_found' => 404,
        'wc_term_exists' => 409,
        'wc_term_has_products' => 409,
        'wc_invalid_parent' => 400,
        // Sprint 5: attributes + terms + shipping classes
        'wc_attribute_not_found' => 404,
        'wc_attribute_term_not_found' => 404,
        'wc_attribute_in_use' => 409,
        'wc_attribute_term_in_use' => 409,
        'wc_shipping_class_in_use' => 409,
        'wc_invalid_attribute_type' => 400,
        'wc_invalid_attribute_order_by' => 400,
    ];
}

function wc_error(string $code, string $message): WP_Error
{
    $status = wc_error_map()[$code] ?? 500;
    return new WP_Error($code, $message, ['status' => $status]);
}

/**
 * Strip NUL bytes and all ASCII control characters (U+0000–U+001F) from a string.
 *
 * MySQL truncates strings at NUL bytes in some collation paths, causing silent
 * data loss. Other low ASCII control codes (BEL, BS, etc.) produce unprintable
 * names that confuse search, CSV exports, and storefront rendering.
 *
 * Use for name, sku, slug, short_description — fields where no control character
 * is legitimate. For long text fields that may contain \t \n \r, use
 * wc_strip_unsafe_chars_preserve_ws() instead.
 */
function wc_strip_unsafe_chars(string $value): string
{
    return preg_replace(pattern: '/[\x00-\x1F]/u', replacement: '', subject: $value) ?? $value;
}

/**
 * Truncate a description string that exceeds 64 KB to prevent agent context bloat.
 *
 * Returns a two-element tuple: [string $value, bool $truncated].
 * If the value fits within 64 KB the original string is returned unchanged.
 *
 * @return array{string, bool}
 */
function wc_maybe_truncate_description(string $value): array
{
    // We use byte-level truncation ('8bit' encoding) rather than character-level
    // because the cap aligns to MySQL TEXT column behavior. A multibyte character
    // (e.g. 4-byte emoji) that straddles the 65536-byte boundary will be split;
    // downstream consumers should normalize the result if they need valid UTF-8.
    if (mb_strlen($value, encoding: '8bit') <= 65_536) {
        return [$value, false];
    }
    return [mb_substr($value, start: 0, length: 65_536, encoding: '8bit'), true];
}

/**
 * Strip NUL bytes and non-whitespace control characters from a long-text string.
 *
 * Keeps \t (0x09), \n (0x0A), and \r (0x0D) as legitimate formatting characters.
 * Use for description fields.
 */
function wc_strip_unsafe_chars_preserve_ws(string $value): string
{
    return preg_replace(pattern: '/[\x00-\x08\x0B\x0C\x0E-\x1F]/u', replacement: '', subject: $value) ?? $value;
}

/**
 * Returns a WP_Error if WooCommerce is not active or below the minimum version.
 * Returns null when the gate passes.
 */
function wc_check_minimum(): ?WP_Error
{
    if (!class_exists('WooCommerce')) {
        return wc_error('wc_inactive', __('WooCommerce is not active on this site.', domain: 'novamira-pro'));
    }
    if (
        !defined('WC_VERSION')
        || version_compare((string) constant('WC_VERSION'), NOVAMIRA_PRO_WOOCOMMERCE_MIN_VERSION, operator: '<')
    ) {
        return wc_error('wc_below_minimum_version', sprintf(
            __('WooCommerce %s or newer is required.', domain: 'novamira-pro'),
            NOVAMIRA_PRO_WOOCOMMERCE_MIN_VERSION,
        ));
    }
    return null;
}

/**
 * Coerce a product input (id or slug) into a WC_Product. Returns WP_Error
 * for missing / wrong post type / trashed / revision / auto-draft.
 */
function wc_product_or_error(int|string $idOrSlug): WC_Product|WP_Error
{
    if (!is_int($idOrSlug) && !ctype_digit($idOrSlug)) {
        $found = get_page_by_path(page_path: $idOrSlug, post_type: 'product');
        if (!$found instanceof WP_Post) {
            return wc_error('wc_not_found', sprintf(
                __('Product not found for slug `%s`.', domain: 'novamira-pro'),
                $idOrSlug,
            ));
        }
        return wc_post_to_product($found);
    }
    /** @var WP_Post|null $rawPost */
    $rawPost = get_post((int) $idOrSlug);
    if (!$rawPost instanceof WP_Post) {
        return wc_error('wc_not_found', sprintf(
            __('Product not found for id %d.', domain: 'novamira-pro'),
            (int) $idOrSlug,
        ));
    }
    return wc_post_to_product($rawPost);
}

/**
 * Convert a WP_Post to WC_Product, validating type, status, and loadability.
 */
function wc_post_to_product(WP_Post $post): WC_Product|WP_Error
{
    if ($post->post_type !== 'product') {
        return wc_error('wc_post_type_mismatch', sprintf(
            /* translators: 1: post id, 2: actual post_type value */
            __('Post %1$d exists but is not a product (post_type: %2$s).', domain: 'novamira-pro'),
            $post->ID,
            $post->post_type,
        ));
    }
    if (in_array($post->post_status, ['trash', 'auto-draft'], strict: true) || \wp_is_post_revision($post->ID)) {
        return wc_error('wc_not_found', sprintf(
            __(
                'Product %d is trashed, an auto-draft, or a revision. To restore, use WP admin or `delete-product { id, force: true }` then re-create.',
                domain: 'novamira-pro',
            ),
            (int) $post->ID,
        ));
    }
    $product = \wc_get_product($post->ID);
    if (!$product instanceof WC_Product) {
        return wc_error('wc_not_found', __('WooCommerce could not load the product.', domain: 'novamira-pro'));
    }
    return $product;
}

/**
 * Compact 15-field product shape for list responses.
 *
 * @return array<string, mixed>
 */
function wc_compact_product(WC_Product $p): array
{
    $modified = $p->get_date_modified();
    $is_variable = $p->get_type() === 'variable';
    // F8: variable parents must not leak per-product stock or price postmeta.
    $stock_quantity = method_exists($p, 'get_stock_quantity') ? $p->get_stock_quantity() : null;
    $manage_stock = method_exists($p, 'get_manage_stock') ? $p->get_manage_stock() : false;
    if ($is_variable) {
        $stock_quantity = null;
        $manage_stock = false;
    }
    $regular_price = $is_variable ? '' : $p->get_regular_price();
    $raw_sale = $p->get_sale_price();
    $sale_price = $is_variable || $raw_sale === '' ? null : $raw_sale;
    return [
        'id' => $p->get_id(),
        'name' => $p->get_name(),
        'slug' => $p->get_slug(),
        'sku' => $p->get_sku(),
        'status' => $p->get_status(),
        'type' => $p->get_type(),
        'stock_status' => $p->get_stock_status(),
        'stock_quantity' => $stock_quantity,
        'manage_stock' => $manage_stock,
        'price' => $p->get_price(),
        'regular_price' => $regular_price,
        'sale_price' => $sale_price,
        'featured' => $p->get_featured(),
        'permalink' => $p->get_permalink(),
        'date_modified' => $modified !== null ? $modified->date('c') : null,
    ];
}

/**
 * Cast an array of mixed values to a list<int>.
 *
 * @param array<mixed> $ids
 * @return list<int>
 */
function wc_to_int_list(array $ids): array
{
    return array_values(array_map('intval', $ids));
}

/**
 * Augment a compact product with caller-requested optional sections.
 *
 * @param list<string> $fields
 * @return array<string, mixed>
 */
function wc_compact_product_with_fields(WC_Product $p, array $fields): array
{
    $row = wc_compact_product($p);
    if (in_array('categories', $fields, strict: true)) {
        $row['categories'] = wc_term_list_for_product(wc_to_int_list($p->get_category_ids()), taxonomy: 'product_cat');
    }
    if (in_array('tags', $fields, strict: true)) {
        $row['tags'] = wc_term_list_for_product(wc_to_int_list($p->get_tag_ids()), taxonomy: 'product_tag');
    }
    if (in_array('images', $fields, strict: true)) {
        $row['images'] = wc_images_for_product($p);
    }
    if (in_array('attributes', $fields, strict: true)) {
        $row['attributes'] = wc_attributes_for_product($p);
    }
    if (in_array('dimensions', $fields, strict: true)) {
        $data = $p->get_data();
        $row['dimensions'] = [
            'length' => (string) ($data['length'] ?? ''),
            'width' => (string) ($data['width'] ?? ''),
            'height' => (string) ($data['height'] ?? ''),
            'weight' => (string) ($data['weight'] ?? ''),
        ];
    }
    if (in_array('meta_data', $fields, strict: true)) {
        $row['meta_data'] = wc_visible_meta($p);
    }
    if (in_array('variations', $fields, strict: true)) {
        $row['variations'] = $p->get_children();
    }
    if (in_array('brands', $fields, strict: true)) {
        if (!taxonomy_exists('product_brand')) {
            $row['brands'] = [];
            return $row;
        }
        $row['brands'] = wc_brand_terms_for_product($p);
    }
    return $row;
}

/**
 * Full product shape for get-product.
 *
 * @return array<string, mixed>
 */
function wc_full_product(WC_Product $p): array
{
    $data = $p->get_data();
    unset($data['meta_data']);
    // Normalize sale_price: empty string → null (matches compact shape contract)
    if (isset($data['sale_price']) && $data['sale_price'] === '') {
        $data['sale_price'] = null;
    }
    $data['permalink'] = $p->get_permalink();
    $data['images'] = wc_images_for_product($p);
    $data['categories'] = wc_term_list_for_product(wc_to_int_list($p->get_category_ids()), taxonomy: 'product_cat');
    $data['tags'] = wc_term_list_for_product(wc_to_int_list($p->get_tag_ids()), taxonomy: 'product_tag');
    // Resolve Brands (WC core 10+ or the separate WooCommerce Brands plugin)
    if (taxonomy_exists('product_brand')) {
        $data['brands'] = wc_brand_terms_for_product($p);
        unset($data['brand_ids']);
    }
    $data['shipping_class_id'] = $p->get_shipping_class_id();
    $data['shipping_class_slug'] = $p->get_shipping_class();
    $data['attributes'] = wc_attributes_for_product($p);
    $children = $p->get_children();
    $truncated = count($children) > 200;
    $data['variations'] = $truncated ? array_slice(array: $children, offset: 0, length: 200) : $children;
    $data['variations_truncated'] = $truncated;
    $data['meta_data'] = wc_visible_meta($p);
    $data['price_html'] = $p->get_price_html();
    // Truncate oversized legacy descriptions so they don't blow up agent context windows.
    [$data['description'], $data['description_truncated']] = wc_maybe_truncate_description(
        (string) ($data['description'] ?? ''),
    );
    // F8: variable parents must not leak per-product stock or price postmeta.
    if ($p->get_type() === 'variable') {
        $data['manage_stock'] = false;
        $data['stock_quantity'] = null;
        $data['regular_price'] = '';
        $data['sale_price'] = null;
    }
    // Ensure type is always present (WC_Product::get_data() may omit it on some versions).
    if (!array_key_exists('type', $data)) {
        $data['type'] = $p->get_type();
    }
    foreach (['date_created', 'date_modified', 'date_on_sale_from', 'date_on_sale_to'] as $key) {
        /** @var \WC_DateTime|mixed $val */
        $val = $data[$key] ?? null;
        if ($val instanceof \WC_DateTime) {
            $data[$key] = $val->date('c');
        }
    }
    return $data;
}

/**
 * Resolve brand term ids for a product into `{id, slug, name}` records.
 * Returns an empty array when the `product_brand` taxonomy does not exist.
 *
 * @return list<array{id:int, slug:string, name:string}>
 */
function wc_brand_terms_for_product(WC_Product $p): array
{
    if (!taxonomy_exists('product_brand')) {
        return [];
    }
    if (method_exists($p, 'get_brand_ids')) {
        /** @var array<mixed> $raw_ids */
        $raw_ids = $p->get_brand_ids();
        return wc_term_list_for_product(array_values(array_map('intval', $raw_ids)), taxonomy: 'product_brand');
    }
    /** @var mixed $raw_val */
    $raw_val = $p->get_data()['brand_ids'] ?? [];
    $raw_ids = is_array($raw_val) ? $raw_val : [];
    return wc_term_list_for_product(array_values(array_map('intval', $raw_ids)), taxonomy: 'product_brand');
}

/**
 * Resolve a list of term ids into `{id, slug, name}` records.
 *
 * @param list<int> $ids
 * @return list<array{id:int, slug:string, name:string}>
 */
function wc_term_list_for_product(array $ids, string $taxonomy): array
{
    if ($ids === []) {
        return [];
    }
    /** @var WP_Term[]|WP_Error $terms */
    $terms = get_terms(['taxonomy' => $taxonomy, 'include' => $ids, 'hide_empty' => false]);
    if ($terms instanceof WP_Error) {
        return [];
    }
    return array_values(array_map(static fn(WP_Term $t): array => [
        'id' => (int) $t->term_id,
        'slug' => $t->slug,
        'name' => $t->name,
    ], $terms));
}

/**
 * @return list<array{id:int, src:string, alt:string, position:int}>
 */
function wc_images_for_product(WC_Product $p): array
{
    $images = [];
    $position = 0;
    $featured = $p->get_image_id();
    if ($featured > 0) {
        $images[] = [
            'id' => $featured,
            'src' => (string) \wp_get_attachment_url($featured),
            'alt' => (string) get_post_meta($featured, key: '_wp_attachment_image_alt', single: true),
            'position' => $position++,
        ];
    }
    foreach ($p->get_gallery_image_ids() as $rawId) {
        $id = (int) $rawId;
        $images[] = [
            'id' => $id,
            'src' => (string) \wp_get_attachment_url($id),
            'alt' => (string) get_post_meta($id, key: '_wp_attachment_image_alt', single: true),
            'position' => $position++,
        ];
    }
    return $images;
}

/**
 * @return list<array{id:int, name:string, slug:string, options:list<string>, visible:bool, variation:bool, taxonomy_slug?:string}>
 */
function wc_attributes_for_product(WC_Product $p): array
{
    $out = [];
    foreach ($p->get_attributes() as $attr) {
        if (!method_exists($attr, 'get_name')) {
            continue;
        }
        $name = $attr->get_name();
        $options = method_exists($attr, 'get_options') ? $attr->get_options() : [];
        $is_taxonomy = method_exists($attr, 'is_taxonomy') ? (bool) $attr->is_taxonomy() : false;
        $attr_id = method_exists($attr, 'get_id') ? (int) $attr->get_id() : 0;
        $options_list = array_values(array_map(static fn($v): string => (string) $v, $options));

        // F9: filter ghost entries left over from a force-deleted global attribute.
        // After a force-delete, WC keeps the orphan postmeta on the product: the attribute
        // object has id=0 (wc_attribute_taxonomy_id_by_name returns 0 for gone taxonomy),
        // is_taxonomy()=false (taxonomy no longer registered), and options=[].
        // Real local attributes also have id=0 but always carry at least one option string.
        if ($attr_id === 0 && $options_list === []) {
            continue;
        }

        $row = [
            'id' => $attr_id,
            'name' => $name,
            'slug' => sanitize_title($name),
            'options' => $options_list,
            'visible' => method_exists($attr, 'get_visible') ? $attr->get_visible() : false,
            'variation' => method_exists($attr, 'get_variation') ? $attr->get_variation() : false,
        ];
        if ($is_taxonomy) {
            $row['taxonomy_slug'] = $name;
        }
        $out[] = $row;
    }
    return $out;
}

/**
 * Meta entries the user wrote. Strips underscore-prefixed system keys.
 *
 * @return list<array{key:string, value:mixed}>
 */
function wc_visible_meta(WC_Product $p): array
{
    $out = [];
    foreach ($p->get_meta_data() as $entry) {
        if (!method_exists($entry, 'get_data')) {
            continue;
        }
        /** @var mixed $row */
        $row = $entry->get_data();
        if (!is_array($row)) {
            continue;
        }
        $key = (string) ($row['key'] ?? '');
        if ($key === '' || str_starts_with($key, '_')) {
            continue;
        }
        $out[] = ['key' => $key, 'value' => $row['value'] ?? null];
    }
    return $out;
}

/**
 * Clamp a requested limit into the [1, 200] range and report truncation.
 *
 * @return array{limit:int, truncated:bool}
 */
function wc_clamp_limit(int $requested): array
{
    if ($requested < 1) {
        return ['limit' => 20, 'truncated' => false];
    }
    if ($requested > 200) {
        return ['limit' => 200, 'truncated' => true];
    }
    return ['limit' => $requested, 'truncated' => false];
}

/**
 * Translate WP-native field aliases to short names used by the write abilities.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function wc_normalize_product_input(array $input): array
{
    $map = [
        'post_title' => 'name',
        'post_name' => 'slug',
        'post_status' => 'status',
        'post_content' => 'description',
        'post_excerpt' => 'short_description',
    ];
    foreach ($map as $alias => $short) {
        if (array_key_exists($alias, $input) && !array_key_exists($short, $input)) {
            $input[$short] = $input[$alias];
        }
        unset($input[$alias]);
    }
    return $input;
}

/**
 * Convert a mixed slug/id array into a list of term ids for a given taxonomy.
 * Returns WP_Error with the first unresolved entry on failure.
 *
 * @param array<array-key, int|string>  $slugs_or_ids
 * @return list<int>|WP_Error
 */
function wc_resolve_term_ids(array $slugs_or_ids, string $taxonomy): array|WP_Error
{
    $ids = [];
    foreach ($slugs_or_ids as $rawItem) {
        $entry = is_int($rawItem) ? $rawItem : $rawItem;
        $resolved = wc_resolve_single_term($entry, $taxonomy);
        if ($resolved instanceof WP_Error) {
            return $resolved;
        }
        $ids[] = $resolved;
    }
    return $ids;
}

/**
 * Resolve a single term entry (id or slug) to its integer term_id.
 */
function wc_resolve_single_term(int|string $entry, string $taxonomy): int|WP_Error
{
    if (is_int($entry) || ctype_digit($entry)) {
        $term = get_term((int) $entry, $taxonomy);
        if (!$term instanceof WP_Term) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: numeric id, 2: taxonomy slug */
                __('Term id %1$d not found in taxonomy `%2$s`.', domain: 'novamira-pro'),
                (int) $entry,
                $taxonomy,
            ));
        }
        return (int) $term->term_id;
    }
    $term = get_term_by('slug', $entry, $taxonomy);
    if (!$term instanceof WP_Term) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: slug string, 2: taxonomy slug */
            __('Term slug `%1$s` not found in taxonomy `%2$s`.', domain: 'novamira-pro'),
            $entry,
            $taxonomy,
        ));
    }
    return (int) $term->term_id;
}

/**
 * Set the id and name on a WC_Product_Attribute from one input entry.
 * Returns true when the attribute was configured, false when it should be skipped,
 * or WP_Error when the entry is invalid (e.g. id-only without taxonomy_slug or name).
 *
 * @param array<mixed> $attr
 */
function wc_configure_attribute(WC_Product_Attribute $wc_attr, array $attr): bool|WP_Error
{
    if (isset($attr['taxonomy_slug'])) {
        $taxonomy = (string) $attr['taxonomy_slug'];
        $attr_id = \wc_attribute_taxonomy_id_by_name($taxonomy);
        if ($attr_id > 0) {
            $wc_attr->set_id($attr_id);
        }
        $wc_attr->set_name($taxonomy);
        return true;
    }
    if (isset($attr['name'])) {
        $wc_attr->set_id(0);
        $wc_attr->set_name((string) $attr['name']);
        return true;
    }
    if (isset($attr['id'])) {
        return wc_error('wc_invalid_input', __(
            'Attribute entries must include `taxonomy_slug` (for global attributes) or `name` (for custom attributes); `id` alone is not supported on input. Use list-product-attributes to find the `taxonomy_slug` of each attribute.',
            domain: 'novamira-pro',
        ));
    }
    return false;
}

/**
 * Build WC_Product_Attribute objects from the input array and apply them.
 *
 * Two shapes accepted per attribute entry:
 *   - {name, options[], visible?}           — local attribute
 *   - {taxonomy_slug, options[], visible?}  — global attribute (pa_* taxonomy)
 *
 * @param array<array-key, array<string, mixed>> $attributes
 */
function wc_apply_attributes(WC_Product $product, array $attributes): ?WP_Error
{
    $wc_attrs = [];
    foreach ($attributes as $attr) {
        $wc_attr = new WC_Product_Attribute();
        $wc_attr->set_variation(false);

        $configured = wc_configure_attribute($wc_attr, $attr);
        if ($configured instanceof WP_Error) {
            return $configured;
        }
        if (!$configured) {
            continue;
        }

        $options = isset($attr['options']) && is_array($attr['options'])
            ? array_values(array_map('strval', $attr['options']))
            : [];
        $wc_attr->set_options($options);
        $visible = isset($attr['visible']) ? $attr['visible'] === true || $attr['visible'] === 1 : true;
        $wc_attr->set_visible($visible);
        $variation = isset($attr['variation']) ? $attr['variation'] === true || $attr['variation'] === 1 : false;
        $wc_attr->set_variation($variation);

        $wc_attrs[$wc_attr->get_name()] = $wc_attr;
    }
    $product->set_attributes($wc_attrs);
    return null;
}

/**
 * Verify that each id in the list refers to an existing attachment.
 *
 * @param list<int> $ids
 */
function wc_validate_attachments(array $ids): ?WP_Error
{
    foreach ($ids as $id) {
        /** @var \WP_Post|\WP_Post[]|null $post */
        $post = get_post($id);
        if (!$post instanceof \WP_Post || $post->post_type !== 'attachment' || $post->post_status === 'trash') {
            return wc_error('wc_attachment_not_found', sprintf(
                __('Attachment %d not found.', domain: 'novamira-pro'),
                $id,
            ));
        }
    }
    return null;
}

/**
 * Apply stock-related fields to a product (D7 coercion rules).
 *
 * @param array<string, mixed> $input
 */
function wc_apply_stock_fields(WC_Product $product, array $input): ?WP_Error
{
    $has_manage = array_key_exists('manage_stock', $input);
    $manage_stock = $has_manage ? $input['manage_stock'] === true || $input['manage_stock'] === 1 : null;
    $has_quantity = array_key_exists('stock_quantity', $input);
    $qty_err = wc_validate_stock_quantity_input($input);
    if ($qty_err !== null) {
        return $qty_err;
    }
    $stock_quantity = $has_quantity ? (int) $input['stock_quantity'] : null;
    $stock_status = isset($input['stock_status']) ? (string) $input['stock_status'] : null;

    if ($manage_stock !== null) {
        $product->set_manage_stock($manage_stock);
    }
    // Effective manage_stock for the rest of this call: explicit input value
    // wins, otherwise fall back to the product's stored state. This makes
    // `edit-product { id, stock_quantity: N }` apply N when the product was
    // already managing stock; without this fallback the call silently no-ops.
    $effective_manage_stock = $manage_stock ?? $product->get_manage_stock();
    if ($effective_manage_stock === true && $stock_quantity !== null) {
        $product->set_stock_quantity($stock_quantity);
        if ($stock_status === null) {
            $product->set_stock_status($stock_quantity > 0 ? 'instock' : 'outofstock');
        }
    }
    if ($stock_status !== null) {
        $product->set_stock_status($stock_status);
    }
    return null;
}

/**
 * Validate the type of `stock_quantity` for direct PHP callers.
 *
 * The REST/MCP layer rejects non-integer values with ability_invalid_input
 * before the callback runs; this is the defense-in-depth path for direct
 * PHP execute() callers that bypass schema validation.
 *
 * @param array<string, mixed> $input
 */
function wc_validate_stock_quantity_input(array $input): ?WP_Error
{
    if (!array_key_exists('stock_quantity', $input)) {
        return null;
    }
    /** @var mixed $sq_raw */
    $sq_raw = $input['stock_quantity'];
    if ($sq_raw === null || is_int($sq_raw)) {
        return null;
    }
    if (!is_numeric($sq_raw) || (float) $sq_raw !== floor((float) $sq_raw)) {
        return wc_error('wc_invalid_input', __(
            '`stock_quantity` must be a whole number (integer).',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * Guard: when manage_stock=true on create, stock_quantity must be present.
 *
 * @param array<string, mixed> $input
 */
function wc_guard_create_stock_quantity(array $input): ?WP_Error
{
    $has_manage = array_key_exists('manage_stock', $input);
    $manage_stock = $has_manage && ($input['manage_stock'] === true || $input['manage_stock'] === 1);
    if ($manage_stock && !array_key_exists('stock_quantity', $input)) {
        return wc_error('wc_invalid_input', __(
            'When `manage_stock` is true on create, `stock_quantity` is required.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * Apply taxonomy (categories / tags) fields to a product.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_taxonomy_fields(WC_Product $product, array $input): ?WP_Error
{
    if (isset($input['categories']) && is_array($input['categories'])) {
        /** @var array<array-key, int|string> $cat_input */
        $cat_input = array_values(array_filter(
            $input['categories'],
            static fn(mixed $v): bool => is_int($v) || is_string($v),
        ));
        $cat_ids = wc_resolve_term_ids($cat_input, taxonomy: 'product_cat');
        if ($cat_ids instanceof WP_Error) {
            return $cat_ids;
        }
        $product->set_category_ids($cat_ids);
    }
    if (isset($input['tags']) && is_array($input['tags'])) {
        /** @var array<array-key, int|string> $tag_input */
        $tag_input = array_values(array_filter(
            $input['tags'],
            static fn(mixed $v): bool => is_int($v) || is_string($v),
        ));
        $tag_ids = wc_resolve_term_ids($tag_input, taxonomy: 'product_tag');
        if ($tag_ids instanceof WP_Error) {
            return $tag_ids;
        }
        $product->set_tag_ids($tag_ids);
    }
    return null;
}

/**
 * Apply image fields to a product, validating attachment ids.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_image_fields(WC_Product $product, array $input): ?WP_Error
{
    if (array_key_exists('featured_image_id', $input)) {
        $fid = (int) $input['featured_image_id'];
        if ($fid === 0) {
            // Explicit 0 means clear the featured image. WC's set_image_id
            // requires an empty string to actually clear the postmeta; passing
            // integer 0 is a silent no-op on WC 10.x.
            $product->set_image_id('');
        }
        if ($fid !== 0) {
            $err = wc_validate_attachments([$fid]);
            if ($err !== null) {
                return $err;
            }
            $product->set_image_id($fid);
        }
    }
    if (isset($input['gallery_image_ids']) && is_array($input['gallery_image_ids'])) {
        $gallery_ids = array_values(array_map('intval', $input['gallery_image_ids']));
        // Per WC convention, 0 in a gallery means "clear all"; pass an empty array instead.
        $gallery_ids = array_values(array_filter($gallery_ids, static fn(int $id): bool => $id !== 0));
        $err = wc_validate_attachments($gallery_ids);
        if ($err !== null) {
            return $err;
        }
        $product->set_gallery_image_ids($gallery_ids);
    }
    return null;
}

/**
 * Apply user-visible meta_data entries to a product.
 * Rejects underscore-prefixed keys silently.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_meta_fields(WC_Product $product, array $input): void
{
    if (!isset($input['meta_data']) || !is_array($input['meta_data'])) {
        return;
    }
    /** @var list<array<string, mixed>> $meta_entries */
    $meta_entries = array_values(array_filter($input['meta_data'], static fn(mixed $v): bool => is_array($v)));
    foreach ($meta_entries as $meta) {
        if (!isset($meta['key'])) {
            continue;
        }
        $key = (string) $meta['key'];
        if ($key === '' || str_starts_with($key, '_')) {
            continue;
        }
        /** @var mixed $value */
        $value = $meta['value'] ?? null;
        if (isset($meta['id'])) {
            $product->update_meta_data($key, $value, (int) $meta['id']);
            continue;
        }
        $product->add_meta_data($key, $value, unique: true);
    }
}

/**
 * Validate and apply the `name` field to a product.
 * Returns WP_Error on type mismatch or byte-length overflow, null on success.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_product_name_field(WC_Product $product, array $input): ?WP_Error
{
    if (!isset($input['name'])) {
        return null;
    }
    if (!is_string($input['name']) && !is_numeric($input['name'])) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: field name, 2: actual PHP type */
            __('`%1$s` must be a string or number (got %2$s).', domain: 'novamira-pro'),
            'name',
            gettype($input['name']),
        ));
    }
    $name = wc_strip_unsafe_chars((string) $input['name']);
    if (mb_strlen($name, encoding: '8bit') > 65_535) {
        return wc_error('wc_invalid_input', __('Product name cannot exceed 65535 bytes.', domain: 'novamira-pro'));
    }
    $product->set_name($name);
    return null;
}

/**
 * Apply text and boolean identity fields to a product.
 *
 * @param array<string, mixed> $input
 * @return WP_Error|null
 */
function wc_apply_identity_fields(WC_Product $product, array $input): ?WP_Error
{
    $err = wc_apply_product_name_field($product, $input);
    if ($err !== null) {
        return $err;
    }
    if (isset($input['slug'])) {
        $product->set_slug(wc_strip_unsafe_chars((string) $input['slug']));
    }
    if (isset($input['status'])) {
        $status = (string) $input['status'];
        $allowed_statuses = ['publish', 'draft', 'pending', 'private'];
        if (!in_array($status, $allowed_statuses, strict: true)) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: submitted status value, 2: comma-separated list of allowed statuses */
                __('Invalid status "%1$s". Allowed: %2$s', domain: 'novamira-pro'),
                $status,
                implode(', ', $allowed_statuses),
            ));
        }
        $product->set_status($status);
    }
    if (isset($input['description'])) {
        $desc = (string) $input['description'];
        $desc_len = mb_strlen($desc, encoding: '8bit');
        if ($desc_len > 65_536) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: actual byte length of description, 2: maximum allowed bytes */
                __('`description` cannot exceed %2$d bytes (got %1$d).', domain: 'novamira-pro'),
                $desc_len,
                65_536,
            ));
        }
        $product->set_description(wc_strip_unsafe_chars_preserve_ws($desc));
    }
    if (isset($input['short_description'])) {
        $product->set_short_description(wc_strip_unsafe_chars((string) $input['short_description']));
    }
    if (isset($input['featured'])) {
        $product->set_featured($input['featured'] === true || $input['featured'] === 1);
    }
    if (isset($input['virtual'])) {
        $product->set_virtual($input['virtual'] === true || $input['virtual'] === 1);
    }
    if (isset($input['downloadable'])) {
        $product->set_downloadable($input['downloadable'] === true || $input['downloadable'] === 1);
    }
    return null;
}

/**
 * Validate a single price value: rejects scientific notation, non-numeric strings, and negatives.
 * Returns WP_Error on failure, null on success.
 * Skip validation when $price is null or empty string (means "clear the price").
 *
 * @param mixed $price
 */
function wc_validate_price_field(string $field_name, mixed $price): ?WP_Error
{
    if ($price === null || $price === '') {
        return null;
    }
    if (!is_string($price) && !is_numeric($price)) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: field name (regular_price or sale_price), 2: actual PHP type */
            __('`%1$s` must be a string or number (got %2$s).', domain: 'novamira-pro'),
            $field_name,
            gettype($price),
        ));
    }
    if (preg_match('/[eE]/', (string) $price) === 1) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: field name (regular_price or sale_price), 2: the submitted value */
            __('`%1$s` does not accept scientific notation (got `%2$s`).', domain: 'novamira-pro'),
            $field_name,
            (string) $price,
        ));
    }
    $numeric = is_numeric($price) ? (float) $price : null;
    if ($numeric === null) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: field name (regular_price or sale_price) */
            __('`%s` must be a numeric string.', domain: 'novamira-pro'),
            $field_name,
        ));
    }
    if ($numeric < 0) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: field name (regular_price or sale_price) */
            __('`%s` cannot be negative.', domain: 'novamira-pro'),
            $field_name,
        ));
    }
    return null;
}

/**
 * Apply price and dimension fields to a product.
 *
 * @param array<string, mixed> $input
 * @return WP_Error|null
 */
function wc_apply_price_dimension_fields(WC_Product $product, array $input): ?WP_Error
{
    foreach (['regular_price', 'sale_price'] as $price_field) {
        if (!array_key_exists($price_field, $input)) {
            continue;
        }
        /** @var mixed $price */
        $price = $input[$price_field];
        $err = wc_validate_price_field($price_field, $price);
        if ($err !== null) {
            return $err;
        }
    }
    if (isset($input['regular_price'])) {
        $product->set_regular_price((string) $input['regular_price']);
    }
    if (array_key_exists('sale_price', $input)) {
        $product->set_sale_price($input['sale_price'] === null ? '' : (string) $input['sale_price']);
    }
    if (isset($input['length'])) {
        $product->set_length((string) $input['length']);
    }
    if (isset($input['width'])) {
        $product->set_width((string) $input['width']);
    }
    if (isset($input['height'])) {
        $product->set_height((string) $input['height']);
    }
    $weight_err = wc_validate_scalar_string_fields($input, ['weight']);
    if ($weight_err !== null) {
        return $weight_err;
    }
    if (isset($input['weight'])) {
        $product->set_weight((string) $input['weight']);
    }
    return null;
}

/**
 * Validate and apply the SKU field, enforcing global uniqueness.
 * Pass $excluding_id = 0 when creating; pass the product's own id when editing.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_sku_field(WC_Product $product, array $input, int $excluding_id): ?WP_Error
{
    if (!isset($input['sku'])) {
        return null;
    }
    $sku = wc_strip_unsafe_chars(trim((string) $input['sku']));
    if ($sku !== '' && preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200E}\x{200F}]/u', $sku) === 1) {
        return wc_error('wc_invalid_input', __(
            'SKU cannot contain bidirectional or directional override characters.',
            domain: 'novamira-pro',
        ));
    }
    if ($sku !== '') {
        $sku_len = mb_strlen($sku, encoding: '8bit');
        if ($sku_len > 100) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: actual byte length of the SKU, 2: maximum allowed bytes */
                __('SKU cannot exceed %2$d characters (got %1$d).', domain: 'novamira-pro'),
                $sku_len,
                100,
            ));
        }
    }
    if ($sku !== '' && !wc_product_has_unique_sku($excluding_id, $sku)) {
        return wc_error('wc_sku_exists', sprintf(
            /* translators: the SKU string that is already in use */
            __('SKU `%s` is already used by another product.', domain: 'novamira-pro'),
            $sku,
        ));
    }
    $product->set_sku($sku);
    return null;
}

/**
 * Apply basic scalar fields (name, slug, status, prices, dimensions, SKU) to a product.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_scalar_fields(WC_Product $product, array $input): ?WP_Error
{
    $err = wc_apply_identity_fields($product, $input);
    if ($err !== null) {
        return $err;
    }
    $err = wc_apply_price_dimension_fields($product, $input);
    if ($err !== null) {
        return $err;
    }
    $excluding_id = isset($input['_excluding_id']) ? (int) $input['_excluding_id'] : 0;
    return wc_apply_sku_field($product, $input, $excluding_id);
}

/**
 * @param array<string, mixed> $input
 */
function wc_apply_product_input_for_create(WC_Product $product, array $input): ?WP_Error
{
    if (!isset($input['name'])) {
        return wc_error('wc_invalid_input', __('`name` is required to create a product.', domain: 'novamira-pro'));
    }
    if (!is_string($input['name']) && !is_numeric($input['name'])) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: field name, 2: actual PHP type */
            __('`%1$s` must be a string or number (got %2$s).', domain: 'novamira-pro'),
            'name',
            gettype($input['name']),
        ));
    }
    if (trim((string) $input['name']) === '') {
        return wc_error('wc_invalid_input', __('`name` is required to create a product.', domain: 'novamira-pro'));
    }
    /** @var mixed $raw_type */
    $raw_type = $input['type'] ?? 'simple';
    $type = (string) $raw_type;
    if (!in_array($type, ['simple', 'variable'], strict: true)) {
        return wc_error('wc_invalid_input', __(
            'Only type=simple and type=variable are supported. Grouped and external product types arrive in a future release.',
            domain: 'novamira-pro',
        ));
    }
    // For simple products: validate stock fields.
    if ($type === 'simple') {
        $err = wc_guard_create_stock_quantity($input);
        if ($err !== null) {
            return $err;
        }
    }
    $input['_excluding_id'] = 0;
    return wc_apply_product_fields($product, $input);
}

/**
 * @param array<string, mixed> $input
 */
function wc_apply_product_input_for_edit(WC_Product $product, array $input): ?WP_Error
{
    $input['_excluding_id'] = $product->get_id();
    return wc_apply_product_fields($product, $input);
}

/**
 * Core field-application logic shared by create and edit paths.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_product_fields(WC_Product $product, array $input): ?WP_Error
{
    // Inject the excluding-id for SKU uniqueness check into the scalar block.
    if (!isset($input['_excluding_id'])) {
        $input['_excluding_id'] = 0;
    }

    $err = wc_strict_bool_check($input, ['manage_stock', 'virtual', 'downloadable']);
    if ($err !== null) {
        return $err;
    }

    $err = wc_apply_scalar_fields($product, $input);
    if ($err !== null) {
        return $err;
    }

    $err = wc_apply_stock_fields($product, $input);
    if ($err !== null) {
        return $err;
    }

    $err = wc_apply_taxonomy_fields($product, $input);
    if ($err !== null) {
        return $err;
    }

    $err = wc_apply_image_fields($product, $input);
    if ($err !== null) {
        return $err;
    }

    if (isset($input['attributes']) && is_array($input['attributes'])) {
        /** @var array<array-key, array<string, mixed>> $attributes_input */
        $attributes_input = array_values(array_filter($input['attributes'], static fn(mixed $v): bool => is_array($v)));
        $err = wc_apply_attributes($product, $attributes_input);
        if ($err !== null) {
            return $err;
        }
    }

    $err = wc_apply_product_shipping_class($product, $input);
    if ($err !== null) {
        return $err;
    }

    wc_apply_meta_fields($product, $input);

    return null;
}

/**
 * Fixed allowlist for check-setup extension detection.
 *
 * @return list<array{slug:string, name:string, version:?string, active:bool}>
 */
function wc_detect_extensions(): array
{
    $checks = [
        [
            'slug' => 'subscriptions',
            'name' => 'WooCommerce Subscriptions',
            'class' => 'WC_Subscriptions',
            'version_const' => 'WC_SUBSCRIPTIONS_VERSION',
        ],
        [
            'slug' => 'bookings',
            'name' => 'WooCommerce Bookings',
            'class' => 'WC_Bookings',
            'version_const' => 'WC_BOOKINGS_VERSION',
        ],
        [
            'slug' => 'brands',
            'name' => 'WooCommerce Brands',
            'class' => 'WC_Brands',
            'version_const' => 'WC_BRANDS_VERSION',
        ],
        [
            'slug' => 'memberships',
            'name' => 'WooCommerce Memberships',
            'class' => 'WC_Memberships',
            'version_const' => 'WC_MEMBERSHIPS_VERSION',
        ],
        [
            'slug' => 'product-add-ons',
            'name' => 'WooCommerce Product Add-Ons',
            'class' => 'WC_Product_Addons',
            'version_const' => 'WC_PRODUCT_ADDONS_VERSION',
        ],
        [
            'slug' => 'advanced-shipping',
            'name' => 'WooCommerce Advanced Shipping',
            'class' => 'WCAS_Advanced_Shipping',
            'version_const' => 'WCAS_VERSION',
        ],
        [
            'slug' => 'stripe-gateway',
            'name' => 'WooCommerce Stripe Gateway',
            'class' => 'WC_Stripe',
            'version_const' => 'WC_STRIPE_VERSION',
        ],
        [
            'slug' => 'woopayments',
            'name' => 'WooPayments',
            'class' => 'WC_Payments',
            'version_const' => 'WCPAY_VERSION',
        ],
        [
            'slug' => 'paypal-payments',
            'name' => 'WooCommerce PayPal Payments',
            'class' => 'WooCommerce\\PayPalCommerce\\PluginModule',
            'version_const' => 'PAYPAL_API_VERSION',
        ],
    ];
    $out = [];
    foreach ($checks as $c) {
        $className = $c['class'];
        $constName = $c['version_const'];
        $active = class_exists($className);
        $version = $active && defined($constName) ? (string) constant($constName) : null;
        $out[] = ['slug' => $c['slug'], 'name' => $c['name'], 'version' => $version, 'active' => $active];
    }
    return $out;
}

/**
 * Re-checks SKU uniqueness after a Throwable during product->save().
 *
 * WC's lookup-table guard may throw when a concurrent request claimed the SKU
 * between our pre-validation and the actual save (race window). If the SKU is
 * now taken, return the clean wc_sku_exists 409; otherwise fall through to the
 * generic wc_internal_error 500.
 *
 * @param array<string, mixed> $input   Normalised ability input.
 * @param int                  $own_id  Product id being saved (0 for new products).
 * @return \WP_Error
 */
function wc_sku_race_or_internal(array $input, int $own_id): \WP_Error
{
    $sku = isset($input['sku']) && is_string($input['sku']) ? trim($input['sku']) : '';
    if ($sku !== '' && !\wc_product_has_unique_sku($own_id, $sku)) {
        return wc_error('wc_sku_exists', sprintf(__('SKU `%s` is already in use.', domain: 'novamira-pro'), $sku));
    }
    return wc_error('wc_internal_error', __('WooCommerce failed to save the product.', domain: 'novamira-pro'));
}

// ---------------------------------------------------------------------------
// Variation helpers (Sprint 3)
// ---------------------------------------------------------------------------

/**
 * Coerce an id to a WC_Product_Variation.
 * Returns wc_not_found for missing, non-variation, or trashed posts.
 */
function wc_post_to_variation(int $id): WC_Product_Variation|WP_Error
{
    /** @var WP_Post|WP_Post[]|null $raw */
    $raw = get_post($id);
    $post = $raw instanceof WP_Post ? $raw : null;
    if (!$post instanceof WP_Post) {
        return wc_error('wc_not_found', sprintf(__('Variation %d not found.', domain: 'novamira-pro'), $id));
    }
    if ($post->post_type !== 'product_variation') {
        return wc_error('wc_not_found', sprintf(
            /* translators: 1: post id, 2: actual post_type value */
            __('Post %1$d is not a product variation (post_type: %2$s).', domain: 'novamira-pro'),
            $id,
            $post->post_type,
        ));
    }
    if ($post->post_status === 'trash') {
        return wc_error('wc_not_found', sprintf(__('Variation %d is trashed.', domain: 'novamira-pro'), $id));
    }
    return new WC_Product_Variation($id);
}

/**
 * Validate that a product exists, is type=variable, is not trashed,
 * and has at least one attribute with variation=true.
 * Returns WC_Product on success, WP_Error on failure.
 */
function wc_validate_variable_parent(int $parent_id): WC_Product|WP_Error
{
    /** @var WP_Post|WP_Post[]|null $raw */
    $raw = get_post($parent_id);
    $post = $raw instanceof WP_Post ? $raw : null;
    if (!$post instanceof WP_Post || $post->post_type !== 'product') {
        return wc_error('wc_not_found', sprintf(
            __('Parent product %d not found.', domain: 'novamira-pro'),
            $parent_id,
        ));
    }
    if ($post->post_status === 'trash') {
        return wc_error('wc_not_found', sprintf(
            __('Parent product %d is trashed.', domain: 'novamira-pro'),
            $parent_id,
        ));
    }
    $product = \wc_get_product($parent_id);
    if (!$product instanceof WC_Product) {
        return wc_error('wc_not_found', __('WooCommerce could not load the parent product.', domain: 'novamira-pro'));
    }
    if ($product->get_type() !== 'variable') {
        return wc_error('wc_not_variable_parent', sprintf(
            /* translators: 1: product id, 2: actual product type string */
            __(
                'Product %1$d is type=%2$s, not type=variable. Variations can only be added to variable products.',
                domain: 'novamira-pro',
            ),
            $parent_id,
            $product->get_type(),
        ));
    }
    // Must have at least one attribute with variation=true
    $has_variation_attr = false;
    foreach ($product->get_attributes() as $attr) {
        if (!method_exists($attr, 'get_variation')) {
            continue;
        }
        if ($attr->get_variation()) {
            $has_variation_attr = true;
            break;
        }
    }
    if (!$has_variation_attr) {
        return wc_error('wc_not_variable_parent', sprintf(
            __(
                'Product %d has no variation-enabled attributes. Add at least one attribute with variation=true via edit-product.',
                domain: 'novamira-pro',
            ),
            $parent_id,
        ));
    }
    return $product;
}

/**
 * Translate the variation input attribute list `[{taxonomy_slug|name, option}]`
 * into the postmeta map `['attribute_pa_size' => 'm']` that
 * WC_Product_Variation::set_attributes() expects.
 *
 * @param array<array-key, array<string, mixed>> $attrs_input
 * @return array<string, string>|WP_Error
 */
function wc_resolve_variation_attributes(int $parent_id, array $attrs_input): array|WP_Error
{
    $product = \wc_get_product($parent_id);
    if (!$product instanceof WC_Product) {
        return wc_error('wc_not_found', __('Parent product not found.', domain: 'novamira-pro'));
    }
    $parent_attrs = $product->get_attributes();
    $result = [];
    foreach ($attrs_input as $entry) {
        $pair = wc_resolve_single_variation_attr($entry, $parent_attrs, $parent_id);
        if ($pair instanceof WP_Error) {
            return $pair;
        }
        $result[$pair[0]] = $pair[1];
    }
    return $result;
}

/**
 * Resolve a single variation attribute entry to a [meta_key, meta_value] pair.
 *
 * @param array<string, mixed>  $entry        One entry from the attributes input list.
 * @param array<string, object> $parent_attrs Parent product's get_attributes() map.
 * @return array{string, string}|WP_Error
 */
function wc_resolve_single_variation_attr(array $entry, array $parent_attrs, int $parent_id): array|WP_Error
{
    $has_taxonomy = array_key_exists('taxonomy_slug', $entry);
    $has_name = array_key_exists('name', $entry);
    if ($has_taxonomy && $has_name) {
        return wc_error('wc_invalid_input', __(
            'Provide either taxonomy_slug or name, not both.',
            domain: 'novamira-pro',
        ));
    }
    if (!$has_taxonomy && !$has_name) {
        return wc_error('wc_invalid_input', __(
            'Attribute entry must include taxonomy_slug or name.',
            domain: 'novamira-pro',
        ));
    }
    if (!isset($entry['option'])) {
        return wc_error('wc_invalid_input', __('Attribute entry must include `option`.', domain: 'novamira-pro'));
    }
    /** @var mixed $option_raw */
    $option_raw = $entry['option'];
    if (!is_scalar($option_raw)) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: attribute key, 2: detected PHP type */
            __('`option` for `%1$s` must be a string or number (got %2$s).', domain: 'novamira-pro'),
            $has_taxonomy ? (string) ($entry['taxonomy_slug'] ?? '') : (string) ($entry['name'] ?? ''),
            gettype($option_raw),
        ));
    }
    $option = (string) $option_raw;
    if ($has_taxonomy) {
        return wc_resolve_taxonomy_variation_attr((string) $entry['taxonomy_slug'], $option, $parent_attrs, $parent_id);
    }
    return wc_resolve_custom_variation_attr((string) $entry['name'], $option, $parent_attrs, $parent_id);
}

/**
 * Resolve a taxonomy-based variation attribute entry.
 *
 * @param array<string, object> $parent_attrs
 * @return array{string, string}|WP_Error
 */
function wc_resolve_taxonomy_variation_attr(
    string $taxonomy_slug,
    string $option,
    array $parent_attrs,
    int $parent_id,
): array|WP_Error {
    $parent_attr = $parent_attrs[$taxonomy_slug] ?? null;
    if ($parent_attr === null) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: attribute taxonomy slug, 2: parent product id */
            __('Attribute `%1$s` not found on parent product %2$d.', domain: 'novamira-pro'),
            $taxonomy_slug,
            $parent_id,
        ));
    }
    if (method_exists($parent_attr, 'get_variation') && !$parent_attr->get_variation()) {
        return wc_error('wc_attribute_not_variable', sprintf(
            /* translators: attribute taxonomy slug */
            __('Attribute `%s` on parent product is not variation-enabled.', domain: 'novamira-pro'),
            $taxonomy_slug,
        ));
    }
    /** @var list<mixed> $parent_options */
    $parent_options = method_exists($parent_attr, 'get_options') ? (array) $parent_attr->get_options() : [];
    $err = wc_validate_variation_option_for_taxonomy($option, $taxonomy_slug, $parent_options, $parent_id);
    if ($err !== null) {
        return $err;
    }
    return ['attribute_' . $taxonomy_slug, sanitize_title($option)];
}

/**
 * Resolve a custom (non-taxonomy) variation attribute entry.
 *
 * @param array<string, object> $parent_attrs
 * @return array{string, string}|WP_Error
 */
function wc_resolve_custom_variation_attr(
    string $attr_name,
    string $option,
    array $parent_attrs,
    int $parent_id,
): array|WP_Error {
    $attr_key = sanitize_title($attr_name);
    $parent_attr = $parent_attrs[$attr_key] ?? null;
    if ($parent_attr === null) {
        foreach ($parent_attrs as $k => $pa) {
            if (!method_exists($pa, 'get_name')) {
                continue;
            }
            if (strtolower((string) $pa->get_name()) === strtolower($attr_name)) {
                $parent_attr = $pa;
                $attr_key = $k;
                break;
            }
        }
    }
    if ($parent_attr === null) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: attribute name, 2: parent product id */
            __('Attribute `%1$s` not found on parent product %2$d.', domain: 'novamira-pro'),
            $attr_name,
            $parent_id,
        ));
    }
    if (method_exists($parent_attr, 'get_variation') && !$parent_attr->get_variation()) {
        return wc_error('wc_attribute_not_variable', sprintf(
            /* translators: attribute name */
            __('Attribute `%s` on parent product is not variation-enabled.', domain: 'novamira-pro'),
            $attr_name,
        ));
    }
    /** @var list<mixed> $parent_options */
    $parent_options = method_exists($parent_attr, 'get_options') ? (array) $parent_attr->get_options() : [];
    $err = wc_validate_variation_option_for_custom($option, $attr_name, $parent_options, $parent_id);
    if ($err !== null) {
        return $err;
    }
    return ['attribute_' . $attr_key, $option];
}

/**
 * Validate that a taxonomy option exists in the parent attribute's options list.
 * Options are term ids; resolved to slugs + names for comparison.
 *
 * @param list<mixed> $parent_options
 */
function wc_validate_variation_option_for_taxonomy(
    string $option,
    string $attr_identifier,
    array $parent_options,
    int $parent_id,
): ?WP_Error {
    if ($parent_options === []) {
        return null;
    }
    $slugs = [];
    $names = [];
    /** @var list<int|string> $parent_options */
    $parent_options = array_values(array_map(static fn(mixed $v): int|string => is_int($v)
        ? $v
        : (string) $v, $parent_options));
    foreach ($parent_options as $raw) {
        $term_id = is_int($raw) ? $raw : 0;
        if ($term_id > 0) {
            /** @var WP_Term|WP_Error|array<mixed>|null $term */
            $term = get_term($term_id);
            if ($term instanceof WP_Term) {
                $slugs[] = $term->slug;
                $names[] = strtolower($term->name);
            }
            continue;
        }
        // May already be a slug string.
        $slugs[] = (string) $raw;
    }
    $option_slug = sanitize_title($option);
    $option_lower = strtolower($option);
    if (!in_array($option_slug, $slugs, strict: true) && !in_array($option_lower, $names, strict: true)) {
        return wc_error('wc_option_not_on_parent', sprintf(
            /* translators: 1: submitted option value, 2: attribute identifier slug, 3: parent product id */
            __('Option `%1$s` is not defined on parent attribute `%2$s` (product %3$d).', domain: 'novamira-pro'),
            $option,
            $attr_identifier,
            $parent_id,
        ));
    }
    return null;
}

/**
 * Validate that a custom-attribute option exists (plain string comparison).
 *
 * @param list<mixed> $parent_options
 */
function wc_validate_variation_option_for_custom(
    string $option,
    string $attr_identifier,
    array $parent_options,
    int $parent_id,
): ?WP_Error {
    if ($parent_options === []) {
        return null;
    }
    $option_lower = strtolower($option);
    $lc_options = array_map(static fn(mixed $v): string => strtolower((string) $v), $parent_options);
    if (!in_array($option_lower, $lc_options, strict: true)) {
        return wc_error('wc_option_not_on_parent', sprintf(
            /* translators: 1: submitted option value, 2: attribute identifier slug, 3: parent product id */
            __('Option `%1$s` is not defined on parent attribute `%2$s` (product %3$d).', domain: 'novamira-pro'),
            $option,
            $attr_identifier,
            $parent_id,
        ));
    }
    return null;
}

/**
 * Single source of truth for create + edit variation input application.
 * Applies attributes, price, SKU, stock, dimensions, image, description,
 * virtual, downloadable, shipping_class, status.
 *
 * Rejects `name` and `post_title` per D11.
 * Pass $own_id = 0 when creating; pass the variation's own id when editing.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_input(WC_Product_Variation $v, array $input, int $own_id): ?WP_Error
{
    $err = wc_strict_bool_check($input, ['manage_stock', 'virtual', 'downloadable', 'is_default']);
    if ($err !== null) {
        return $err;
    }
    $err = wc_prepare_variation_input($v, $input);
    if ($err instanceof WP_Error) {
        return $err;
    }
    // $err here is null (gate passed), $input was mutated in-place via reference.
    // Re-read from the actual array passed-by-value — the preamble was passed by-value too,
    // so we re-normalize here directly:
    if (array_key_exists('post_excerpt', $input) && !array_key_exists('description', $input)) {
        $input['description'] = $input['post_excerpt'];
    }
    unset($input['post_excerpt']);

    return wc_apply_variation_scalar_fields($v, $input, $own_id);
}

/**
 * Preamble: normalize aliases, reject D11 fields, resolve and apply attributes.
 * Returns WP_Error on any failure, null on success.
 *
 * @param array<string, mixed> $input
 */
function wc_prepare_variation_input(WC_Product_Variation $v, array $input): ?WP_Error
{
    // D11: reject name and post_title.
    if (array_key_exists('name', $input) || array_key_exists('post_title', $input)) {
        return wc_error('wc_invalid_input', __(
            'Variation names are auto-derived from the parent and attributes; do not pass `name`.',
            domain: 'novamira-pro',
        ));
    }

    // Attributes: resolve then set.
    if (!array_key_exists('attributes', $input)) {
        return null;
    }
    if (!is_array($input['attributes'])) {
        return wc_error('wc_invalid_input', __('`attributes` must be an array.', domain: 'novamira-pro'));
    }
    /** @var array<array-key, array<string, mixed>> $attrs_input */
    $attrs_input = array_values(array_filter($input['attributes'], static fn(mixed $v): bool => is_array($v)));
    $resolved = wc_resolve_variation_attributes($v->get_parent_id(), $attrs_input);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    $v->set_attributes($resolved);
    return null;
}

/**
 * Apply virtual / downloadable flag fields and the description text field.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_flag_fields(WC_Product_Variation $v, array $input): ?WP_Error
{
    if (array_key_exists('description', $input)) {
        $desc = (string) $input['description'];
        $desc_len = mb_strlen($desc, encoding: '8bit');
        if ($desc_len > 65_536) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: actual byte length of description, 2: maximum allowed bytes */
                __('`description` cannot exceed %2$d bytes (got %1$d).', domain: 'novamira-pro'),
                $desc_len,
                65_536,
            ));
        }
        $v->set_description(wc_strip_unsafe_chars_preserve_ws($desc));
    }
    if (array_key_exists('virtual', $input)) {
        $v->set_virtual($input['virtual'] === true || $input['virtual'] === 1);
    }
    if (array_key_exists('downloadable', $input)) {
        $v->set_downloadable($input['downloadable'] === true || $input['downloadable'] === 1);
    }
    return null;
}

/**
 * Apply physical fields: stock, dimensions, image, shipping class, status.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_physical_fields(WC_Product_Variation $v, array $input): ?WP_Error
{
    $err = wc_apply_variation_stock_and_dimensions($v, $input);
    if ($err !== null) {
        return $err;
    }

    return wc_apply_variation_image_shipping_status($v, $input);
}

/**
 * Apply stock fields (with validation) then dimension fields to a variation.
 * Extracted to keep wc_apply_variation_physical_fields within halstead limits.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_stock_and_dimensions(WC_Product_Variation $v, array $input): ?WP_Error
{
    $err = wc_apply_stock_fields($v, $input);
    if ($err !== null) {
        return $err;
    }
    wc_apply_variation_dimension_fields($v, $input);
    return null;
}

/**
 * Apply image, shipping-class, and status fields to a variation.
 * Extracted to keep wc_apply_variation_physical_fields within halstead limits.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_image_shipping_status(WC_Product_Variation $v, array $input): ?WP_Error
{
    $err = wc_apply_variation_image_field($v, $input);
    if ($err !== null) {
        return $err;
    }

    $err = wc_apply_variation_shipping_class($v, $input);
    if ($err !== null) {
        return $err;
    }

    return wc_apply_variation_status($v, $input);
}

/**
 * Apply all scalar fields to a variation (prices, SKU, stock, dimensions, image,
 * description, virtual, downloadable, shipping_class, status).
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_scalar_fields(WC_Product_Variation $v, array $input, int $own_id): ?WP_Error
{
    $err = wc_apply_variation_price_fields($v, $input);
    if ($err !== null) {
        return $err;
    }

    $err = wc_apply_variation_sku_field($v, $input, $own_id);
    if ($err !== null) {
        return $err;
    }

    $err = wc_apply_variation_flag_fields($v, $input);
    if ($err !== null) {
        return $err;
    }

    return wc_apply_variation_physical_fields($v, $input);
}

/**
 * Validate and apply price fields to a variation.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_price_fields(WC_Product_Variation $v, array $input): ?WP_Error
{
    foreach (['regular_price', 'sale_price'] as $price_field) {
        if (!array_key_exists($price_field, $input)) {
            continue;
        }
        /** @var mixed $price */
        $price = $input[$price_field];
        $err = wc_validate_price_field($price_field, $price);
        if ($err !== null) {
            return $err;
        }
    }
    if (array_key_exists('regular_price', $input)) {
        /** @var mixed $rp */
        $rp = $input['regular_price'];
        $v->set_regular_price($rp === null ? '' : (string) $rp);
    }
    if (array_key_exists('sale_price', $input)) {
        /** @var mixed $sp */
        $sp = $input['sale_price'];
        $v->set_sale_price($sp === null ? '' : (string) $sp);
    }
    return null;
}

/**
 * Validate and apply SKU field to a variation.
 * $own_id = 0 for create; variation's own id for edit.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_sku_field(WC_Product_Variation $v, array $input, int $own_id): ?WP_Error
{
    if (!array_key_exists('sku', $input)) {
        return null;
    }
    $sku = wc_strip_unsafe_chars(trim((string) $input['sku']));
    if ($sku !== '' && preg_match('/[\x{202A}-\x{202E}\x{2066}-\x{2069}\x{200E}\x{200F}]/u', $sku) === 1) {
        return wc_error('wc_invalid_input', __(
            'SKU cannot contain bidirectional or directional override characters.',
            domain: 'novamira-pro',
        ));
    }
    if ($sku !== '') {
        $sku_len = mb_strlen($sku, encoding: '8bit');
        if ($sku_len > 100) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: actual byte length of the SKU, 2: maximum allowed bytes */
                __('SKU cannot exceed %2$d characters (got %1$d).', domain: 'novamira-pro'),
                $sku_len,
                100,
            ));
        }
    }
    if ($sku !== '' && !\wc_product_has_unique_sku($own_id, $sku)) {
        return wc_error('wc_sku_exists', sprintf(
            /* translators: the SKU string that is already in use */
            __('SKU `%s` is already used by another product.', domain: 'novamira-pro'),
            $sku,
        ));
    }
    $v->set_sku($sku);
    return null;
}

/**
 * Apply dimension and weight fields to a variation.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_dimension_fields(WC_Product_Variation $v, array $input): void
{
    if (array_key_exists('weight', $input)) {
        $v->set_weight((string) $input['weight']);
    }
    if (array_key_exists('dimensions', $input) && is_array($input['dimensions'])) {
        /** @var array<string, mixed> $dims */
        $dims = $input['dimensions'];
        if (array_key_exists('length', $dims)) {
            $v->set_length((string) $dims['length']);
        }
        if (array_key_exists('width', $dims)) {
            $v->set_width((string) $dims['width']);
        }
        if (array_key_exists('height', $dims)) {
            $v->set_height((string) $dims['height']);
        }
    }
    // Also accept top-level length/width/height for convenience.
    if (array_key_exists('length', $input)) {
        $v->set_length((string) $input['length']);
    }
    if (array_key_exists('width', $input)) {
        $v->set_width((string) $input['width']);
    }
    if (array_key_exists('height', $input)) {
        $v->set_height((string) $input['height']);
    }
}

/**
 * Validate and apply image_id to a variation.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_image_field(WC_Product_Variation $v, array $input): ?WP_Error
{
    if (!array_key_exists('image_id', $input)) {
        return null;
    }
    $img_id = (int) $input['image_id'];
    if ($img_id === 0) {
        $v->set_image_id(0);
        return null;
    }
    $err = wc_validate_attachments([$img_id]);
    if ($err !== null) {
        return $err;
    }
    $v->set_image_id($img_id);
    return null;
}

/**
 * Apply shipping class to a parent product (simple or variable).
 * Accepts `shipping_class` (slug) or `shipping_class_id` (int). Omitting both is a no-op.
 * Pass shipping_class="" or shipping_class_id=0 to clear.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_product_shipping_class(WC_Product $product, array $input): ?WP_Error
{
    $has_slug = array_key_exists('shipping_class', $input);
    $has_id = array_key_exists('shipping_class_id', $input);

    if (!$has_slug && !$has_id) {
        return null;
    }

    if ($has_id) {
        $sc_id = (int) $input['shipping_class_id'];
        if ($sc_id === 0) {
            $product->set_shipping_class_id(0);
            return null;
        }
        $sc_term = get_term($sc_id, taxonomy: 'product_shipping_class');
        if (!$sc_term instanceof WP_Term) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: shipping class id */
                __('Shipping class id %d not found.', domain: 'novamira-pro'),
                $sc_id,
            ));
        }
        $product->set_shipping_class_id($sc_term->term_id);
        return null;
    }

    // $has_slug path
    if (!is_string($input['shipping_class'])) {
        return null;
    }
    $sc_slug = $input['shipping_class'];
    if ($sc_slug === '') {
        $product->set_shipping_class_id(0);
        return null;
    }
    $sc_term = get_term_by(field: 'slug', value: $sc_slug, taxonomy: 'product_shipping_class');
    if (!$sc_term instanceof WP_Term) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: shipping class slug */
            __('Shipping class slug `%s` not found.', domain: 'novamira-pro'),
            $sc_slug,
        ));
    }
    $product->set_shipping_class_id($sc_term->term_id);
    return null;
}

/**
 * Apply shipping class to a variation.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_shipping_class(WC_Product_Variation $v, array $input): ?WP_Error
{
    if (!array_key_exists('shipping_class', $input) || !is_string($input['shipping_class'])) {
        return null;
    }
    $sc_slug = $input['shipping_class'];
    if ($sc_slug === '') {
        $v->set_shipping_class_id(0);
        return null;
    }
    $sc_term = get_term_by(field: 'slug', value: $sc_slug, taxonomy: 'product_shipping_class');
    if ($sc_term instanceof WP_Term) {
        $v->set_shipping_class_id($sc_term->term_id);
    }
    return null;
}

/**
 * Validate and apply status to a variation.
 *
 * @param array<string, mixed> $input
 */
function wc_apply_variation_status(WC_Product_Variation $v, array $input): ?WP_Error
{
    if (!array_key_exists('status', $input)) {
        return null;
    }
    $status = (string) $input['status'];
    $allowed = ['publish', 'private', 'draft'];
    if (!in_array($status, $allowed, strict: true)) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: submitted status value, 2: comma-separated list of allowed statuses */
            __('Invalid variation status "%1$s". Allowed: %2$s', domain: 'novamira-pro'),
            $status,
            implode(', ', $allowed),
        ));
    }
    $v->set_status($status);
    return null;
}

/**
 * Compact variation row for list responses.
 * Pass $fields[] for opt-in extra fields.
 *
 * @param list<string> $fields
 * @return array<string, mixed>
 */
function wc_variation_to_array(WC_Product_Variation $v, array $fields = []): array
{
    $parent_id = $v->get_parent_id();
    $row = [
        'id' => $v->get_id(),
        'parent_id' => $parent_id,
        'sku' => $v->get_sku(),
        'attributes_summary' => wc_variation_attributes_summary($v),
        'regular_price' => $v->get_regular_price(),
        'sale_price' => $v->get_sale_price() !== '' ? $v->get_sale_price() : null,
        'stock_status' => $v->get_stock_status(),
        'stock_quantity' => $v->get_stock_quantity(),
        'is_default' => wc_is_default_variation($parent_id, $v),
    ];
    if (!in_array('full', $fields, strict: true)) {
        return $row;
    }
    return array_merge($row, wc_variation_full_to_array($v));
}

/**
 * Full variation shape for get-product-variation.
 *
 * @return array<string, mixed>
 */
function wc_variation_full_to_array(WC_Product_Variation $v): array
{
    $parent_id = $v->get_parent_id();
    $modified = $v->get_date_modified();
    $data = $v->get_data();
    $image_id = $v->get_image_id();
    [$desc, $desc_truncated] = wc_maybe_truncate_description($v->get_description());
    return [
        'id' => $v->get_id(),
        'parent_id' => $parent_id,
        'name' => $v->get_name(),
        'sku' => $v->get_sku(),
        'status' => $v->get_status(),
        'attributes' => wc_variation_attributes_structured($v),
        'attributes_summary' => wc_variation_attributes_summary($v),
        'regular_price' => $v->get_regular_price(),
        'sale_price' => $v->get_sale_price() !== '' ? $v->get_sale_price() : null,
        'price' => $v->get_price(),
        'manage_stock' => $v->get_manage_stock(),
        'stock_quantity' => $v->get_stock_quantity(),
        'stock_status' => $v->get_stock_status(),
        'weight' => array_key_exists('weight', $data) ? (string) $data['weight'] : '',
        'dimensions' => [
            'length' => array_key_exists('length', $data) ? (string) $data['length'] : '',
            'width' => array_key_exists('width', $data) ? (string) $data['width'] : '',
            'height' => array_key_exists('height', $data) ? (string) $data['height'] : '',
        ],
        'image_id' => $image_id > 0 ? $image_id : null,
        'image_src' => $image_id > 0 ? (string) \wp_get_attachment_url($image_id) : null,
        'description' => $desc,
        'description_truncated' => $desc_truncated,
        'virtual' => $v->get_virtual(),
        'downloadable' => $v->get_downloadable(),
        'shipping_class' => $v->get_shipping_class(),
        'is_default' => wc_is_default_variation($parent_id, $v),
        'date_modified' => $modified !== null ? $modified->date('c') : null,
    ];
}

/**
 * Build a human-readable attributes summary like "M / Red".
 */
function wc_variation_attributes_summary(WC_Product_Variation $v): string
{
    $attrs = $v->get_variation_attributes();
    $labels = [];
    foreach ($attrs as $key => $value) {
        if ($value === '') {
            $labels[] = __('Any', domain: 'novamira-pro');
            continue;
        }
        // For taxonomy attributes (attribute_pa_*), resolve term slug → term name.
        $taxonomy = str_replace(search: 'attribute_', replace: '', subject: $key);
        if (!taxonomy_exists($taxonomy)) {
            $labels[] = $value;
            continue;
        }
        $term = get_term_by(field: 'slug', value: $value, taxonomy: $taxonomy);
        $labels[] = $term instanceof WP_Term ? $term->name : $value;
    }
    return implode(' / ', $labels);
}

/**
 * Build the structured attributes list for full variation response.
 *
 * @return list<array{key:string, taxonomy:string, label:string, option:string}>
 */
function wc_variation_attributes_structured(WC_Product_Variation $v): array
{
    $attrs = $v->get_variation_attributes();
    $out = [];
    foreach ($attrs as $key => $value) {
        $taxonomy = str_replace(search: 'attribute_', replace: '', subject: $key);
        $label = $taxonomy;
        $option_label = $value;
        if (taxonomy_exists($taxonomy)) {
            $attr_obj = get_taxonomy($taxonomy);
            if ($attr_obj !== false) {
                /** @var mixed $singular */
                $singular = $attr_obj->labels->singular_name ?? null;
                $label = is_string($singular) ? $singular : $taxonomy;
            }
            if ($value !== '') {
                $term = get_term_by(field: 'slug', value: $value, taxonomy: $taxonomy);
                $option_label = $term instanceof WP_Term ? $term->name : $value;
            }
        }
        $out[] = ['key' => $key, 'taxonomy' => $taxonomy, 'label' => $label, 'option' => $option_label];
    }
    return $out;
}

/**
 * Write parent's default_attributes meta from the variation's attribute map.
 */
function wc_set_default_variation(int $parent_id, WC_Product_Variation $v): void
{
    $attrs = $v->get_variation_attributes();
    // WC stores default_attributes with the "attribute_" prefix stripped.
    $defaults = [];
    foreach ($attrs as $key => $value) {
        $short_key = str_replace(search: 'attribute_', replace: '', subject: $key);
        $defaults[$short_key] = $value;
    }
    $parent = \wc_get_product($parent_id);
    if ($parent instanceof WC_Product_Variable) {
        $parent->set_default_attributes($defaults);
        $parent->save();
        clean_post_cache($parent_id);
    }
}

/**
 * Clear parent's default_attributes meta (no variation preselected).
 */
function wc_clear_default_variation(int $parent_id): void
{
    $parent = \wc_get_product($parent_id);
    if ($parent instanceof WC_Product_Variable) {
        $parent->set_default_attributes([]);
        $parent->save();
        clean_post_cache($parent_id);
    }
}

/**
 * Return true if the variation's attribute combo matches parent's default_attributes.
 */
function wc_is_default_variation(int $parent_id, WC_Product_Variation $v): bool
{
    $parent = \wc_get_product($parent_id);
    if (!$parent instanceof WC_Product_Variable) {
        return false;
    }
    $defaults = $parent->get_default_attributes();
    if ($defaults === []) {
        return false;
    }
    $attrs = $v->get_variation_attributes();
    /** @var array<string, string> $string_defaults */
    $string_defaults = array_map(static fn(mixed $v): string => (string) $v, $defaults);
    foreach ($string_defaults as $key => $value) {
        $full_key = 'attribute_' . $key;
        $attr_val = $attrs[$full_key] ?? null;
        if ($attr_val !== $value) {
            return false;
        }
    }
    return true;
}

/**
 * Return variation ids that reference a specific attribute key (and optional option slug).
 * Used to guard against attribute edits that would orphan existing variations.
 *
 * @return list<int>
 */
function wc_find_variations_referencing(int $parent_id, string $attribute_key, ?string $removed_option = null): array
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return [];
    }
    $rows = $removed_option !== null
        ? wc_find_variations_by_option($wpdb, $parent_id, $attribute_key, $removed_option)
        : wc_find_variations_by_key($wpdb, $parent_id, $attribute_key);
    if (!is_array($rows)) {
        return [];
    }
    return array_values(array_map(static fn(array $r): int => (int) $r['post_id'], $rows));
}

/**
 * Query variation ids that reference a specific attribute key + option slug.
 *
 * @return list<array{post_id:string}>|null
 */
function wc_find_variations_by_option(\wpdb $wpdb, int $parent_id, string $attribute_key, string $option): ?array
{
    $pm = $wpdb->postmeta;
    $p = $wpdb->posts;
    /** @var literal-string $sql */
    $sql = "SELECT pm.post_id FROM {$pm} pm INNER JOIN {$p} p ON p.ID = pm.post_id WHERE p.post_parent = %d AND p.post_type = 'product_variation' AND pm.meta_key = %s AND pm.meta_value = %s";
    $prepared = (string) $wpdb->prepare($sql, $parent_id, $attribute_key, $option);
    /** @var list<array{post_id:string}>|null */
    return $wpdb->get_results($prepared, 'ARRAY_A');
}

/**
 * Query variation ids that reference a specific attribute key (any non-empty value).
 *
 * @return list<array{post_id:string}>|null
 */
function wc_find_variations_by_key(\wpdb $wpdb, int $parent_id, string $attribute_key): ?array
{
    $pm = $wpdb->postmeta;
    $p = $wpdb->posts;
    /** @var literal-string $sql */
    $sql = "SELECT pm.post_id FROM {$pm} pm INNER JOIN {$p} p ON p.ID = pm.post_id WHERE p.post_parent = %d AND p.post_type = 'product_variation' AND pm.meta_key = %s AND pm.meta_value != ''";
    $prepared = (string) $wpdb->prepare($sql, $parent_id, $attribute_key);
    /** @var list<array{post_id:string}>|null */
    return $wpdb->get_results($prepared, 'ARRAY_A');
}

/**
 * Build a lookup map from canonical attribute key to new attribute entry.
 *
 * @param array<array-key, array<string, mixed>> $new_attrs
 * @return array<string, array<string, mixed>>
 */
function wc_build_attr_lookup_map(array $new_attrs): array
{
    $map = [];
    foreach ($new_attrs as $a) {
        if (isset($a['taxonomy_slug'])) {
            $map[(string) $a['taxonomy_slug']] = $a;
            continue;
        }
        if (isset($a['name'])) {
            $map[sanitize_title((string) $a['name'])] = $a;
        }
    }
    return $map;
}

/**
 * Guard variable parent attribute edits that would orphan existing variations.
 * Only runs when $product->get_type() === 'variable' and $input['attributes'] is present.
 *
 * @param array<array-key, array<string, mixed>> $new_attrs Input attributes list (already filtered to arrays).
 * @return WP_Error|null
 */
function wc_guard_variable_attribute_edit(WC_Product $product, array $new_attrs): ?WP_Error
{
    $parent_id = $product->get_id();
    $existing = $product->get_attributes();
    $new_by_key = wc_build_attr_lookup_map($new_attrs);

    foreach ($existing as $attr_key => $attr) {
        $err = wc_guard_single_attr_edit($parent_id, $attr_key, $attr, $new_by_key);
        if ($err !== null) {
            return $err;
        }
    }
    return null;
}

/**
 * Guard checks for a single existing attribute against the proposed new attribute set.
 *
 * @param array<string, array<string, mixed>> $new_by_key Lookup map from canonical key to new attr entry.
 */
function wc_guard_single_attr_edit(
    int $parent_id,
    string $attr_key,
    WC_Product_Attribute $attr,
    array $new_by_key,
): ?WP_Error {
    $is_variation = $attr->get_variation();
    $is_taxonomy = method_exists($attr, 'is_taxonomy') ? (bool) $attr->is_taxonomy() : false;
    $canonical_key = 'attribute_' . ($is_taxonomy ? $attr_key : sanitize_title($attr->get_name()));

    if (!array_key_exists($attr_key, $new_by_key)) {
        if ($is_variation) {
            return wc_check_attr_removal($parent_id, $attr_key, $canonical_key);
        }
        return null;
    }

    $new_a = $new_by_key[$attr_key];

    if ($is_variation) {
        $err = wc_check_attr_variation_disable($parent_id, $attr_key, $canonical_key, $new_a);
        if ($err !== null) {
            return $err;
        }
    }

    if ($is_variation && method_exists($attr, 'get_options')) {
        /** @var list<mixed> $raw_opts */
        $raw_opts = $attr->get_options();
        $old_slugs = $is_taxonomy
            ? array_map(static fn(mixed $v): string => wc_term_id_to_slug($v), $raw_opts)
            : array_map(static fn(mixed $v): string => sanitize_title((string) $v), $raw_opts);
        $err = wc_check_attr_option_removal($parent_id, $attr_key, $canonical_key, $old_slugs, $new_a);
        if ($err !== null) {
            return $err;
        }
    }

    if (!$is_taxonomy && isset($new_a['name'])) {
        return wc_check_custom_attr_rename($parent_id, $canonical_key, $attr, $new_a);
    }

    return null;
}

/**
 * Check 1: attribute removed from the new set while variations still reference it.
 */
function wc_check_attr_removal(int $parent_id, string $attr_key, string $canonical_key): ?WP_Error
{
    $refs = wc_find_variations_referencing($parent_id, $canonical_key);
    if ($refs === []) {
        return null;
    }
    return wc_error('wc_invalid_input', sprintf(
        /* translators: 1: attribute slug, 2: comma-separated variation ids */
        __(
            'Cannot remove attribute `%1$s`: variation IDs [%2$s] still reference it. Delete those variations first.',
            domain: 'novamira-pro',
        ),
        $attr_key,
        implode(', ', $refs),
    ));
}

/**
 * Check 2: variation:true → variation:false while variations still reference the attribute.
 *
 * @param array<string, mixed> $new_a
 */
function wc_check_attr_variation_disable(
    int $parent_id,
    string $attr_key,
    string $canonical_key,
    array $new_a,
): ?WP_Error {
    $new_variation = isset($new_a['variation']) ? $new_a['variation'] === true || $new_a['variation'] === 1 : true;
    if ($new_variation) {
        return null;
    }
    $refs = wc_find_variations_referencing($parent_id, $canonical_key);
    if ($refs === []) {
        return null;
    }
    return wc_error('wc_invalid_input', sprintf(
        /* translators: 1: attribute slug, 2: comma-separated variation ids */
        __(
            'Cannot disable `variation: true` on `%1$s`: variation IDs [%2$s] still reference it. Delete those variations first.',
            domain: 'novamira-pro',
        ),
        $attr_key,
        implode(', ', $refs),
    ));
}

/**
 * Check 3: option removed from a variation-enabled attribute while variations reference that option.
 *
 * @param list<string>         $old_option_slugs Pre-resolved slugs from the existing attribute options.
 * @param array<string, mixed> $new_a
 */
function wc_check_attr_option_removal(
    int $parent_id,
    string $attr_key,
    string $canonical_key,
    array $old_option_slugs,
    array $new_a,
): ?WP_Error {
    if (!isset($new_a['options']) || !is_array($new_a['options'])) {
        return null;
    }
    $new_options_lc = array_map(static fn(mixed $v): string => sanitize_title((string) $v), $new_a['options']);
    foreach ($old_option_slugs as $opt_slug) {
        if (in_array($opt_slug, $new_options_lc, strict: true)) {
            continue;
        }
        $refs = wc_find_variations_referencing($parent_id, $canonical_key, $opt_slug);
        if ($refs === []) {
            continue;
        }
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: option slug, 2: attribute slug, 3: comma-separated variation ids */
            __(
                'Cannot remove option `%1$s` from `%2$s`: variation IDs [%3$s] still reference it. Delete those variations first.',
                domain: 'novamira-pro',
            ),
            $opt_slug,
            $attr_key,
            implode(', ', $refs),
        ));
    }
    return null;
}

/**
 * Resolve a taxonomy attribute option (raw term id or slug string) to its slug.
 */
function wc_term_id_to_slug(mixed $raw_opt): string
{
    $term_id = is_int($raw_opt) ? $raw_opt : 0;
    if ($term_id <= 0) {
        return sanitize_title((string) $raw_opt);
    }
    /** @var \WP_Term|\WP_Error|array<mixed>|null $term */
    $term = get_term($term_id);
    return $term instanceof WP_Term ? $term->slug : sanitize_title((string) $raw_opt);
}

/**
 * Check 4: custom attribute renamed while variations still reference the old postmeta key.
 *
 * @param array<string, mixed> $new_a
 */
function wc_check_custom_attr_rename(
    int $parent_id,
    string $canonical_key,
    WC_Product_Attribute $attr,
    array $new_a,
): ?WP_Error {
    $old_name_key = sanitize_title($attr->get_name());
    $new_name_key = sanitize_title((string) $new_a['name']);
    if ($old_name_key === $new_name_key) {
        return null;
    }
    $refs = wc_find_variations_referencing($parent_id, $canonical_key);
    if ($refs === []) {
        return null;
    }
    return wc_error('wc_invalid_input', sprintf(
        /* translators: 1: old attribute name, 2: comma-separated variation ids */
        __(
            'Cannot rename custom attribute `%1$s`: variation IDs [%2$s] still reference it. Delete those variations first.',
            domain: 'novamira-pro',
        ),
        $attr->get_name(),
        implode(', ', $refs),
    ));
}

/**
 * Re-checks SKU uniqueness after a Throwable during variation->save().
 *
 * @param array<string, mixed> $input
 */
function wc_sku_race_or_internal_variation(array $input, int $own_id): WP_Error
{
    $sku = isset($input['sku']) && is_string($input['sku']) ? trim($input['sku']) : '';
    if ($sku !== '' && !\wc_product_has_unique_sku($own_id, $sku)) {
        return wc_error('wc_sku_exists', sprintf(__('SKU `%s` is already in use.', domain: 'novamira-pro'), $sku));
    }
    return wc_error('wc_internal_error', __('WooCommerce failed to save the variation.', domain: 'novamira-pro'));
}

// ---------------------------------------------------------------------------
// Term helpers (Sprint 4 — Categories + Tags CRUD)
// ---------------------------------------------------------------------------

/**
 * Coerce a term id into a WP_Term, validating taxonomy membership.
 * Returns wc_term_not_found when the id is missing or in the wrong taxonomy.
 */
function wc_post_to_term(int $term_id, string $taxonomy): WP_Term|WP_Error
{
    /** @var WP_Term|WP_Error|null $term */
    $term = get_term($term_id, $taxonomy);
    if (!$term instanceof WP_Term) {
        return wc_error('wc_term_not_found', sprintf(
            /* translators: 1: term id, 2: taxonomy slug */
            __('Term %1$d not found in taxonomy `%2$s`.', domain: 'novamira-pro'),
            $term_id,
            $taxonomy,
        ));
    }
    return $term;
}

/**
 * Normalize parent_id|parent_slug to an integer term_id (0 = root).
 * Enforces mutual exclusion.
 *
 * @param array<string, mixed> $input
 */
function wc_resolve_parent_term(array $input, string $taxonomy): int|WP_Error
{
    $has_id = array_key_exists('parent_id', $input) && $input['parent_id'] !== null;
    $has_slug =
        array_key_exists('parent_slug', $input) && $input['parent_slug'] !== null && $input['parent_slug'] !== '';

    if ($has_id && $has_slug) {
        return wc_error('wc_invalid_input', __(
            'Provide either parent_id or parent_slug, not both.',
            domain: 'novamira-pro',
        ));
    }

    if (!$has_id && !$has_slug) {
        return 0;
    }

    if ($has_id) {
        $pid = (int) $input['parent_id'];
        if ($pid === 0) {
            return 0;
        }
        $parent = get_term($pid, $taxonomy);
        if (!$parent instanceof WP_Term) {
            return wc_error('wc_invalid_parent', sprintf(
                /* translators: 1: parent term id, 2: taxonomy slug */
                __('Parent term %1$d not found in taxonomy `%2$s`.', domain: 'novamira-pro'),
                $pid,
                $taxonomy,
            ));
        }
        return (int) $parent->term_id;
    }

    // parent_slug path
    $slug = trim((string) $input['parent_slug']);
    if ($slug === '' || $slug === '0') {
        return 0;
    }
    $parent = get_term_by('slug', $slug, $taxonomy);
    if (!$parent instanceof WP_Term) {
        return wc_error('wc_invalid_parent', sprintf(
            /* translators: 1: parent slug, 2: taxonomy slug */
            __('Parent term with slug `%1$s` not found in taxonomy `%2$s`.', domain: 'novamira-pro'),
            $slug,
            $taxonomy,
        ));
    }
    return (int) $parent->term_id;
}

/**
 * Guard: block edit/delete on the WC default product category.
 * Returns wc_invalid_input when $term_id matches the stored default, null otherwise.
 */
function wc_validate_default_category(int $term_id): ?WP_Error
{
    $default = (int) \get_option(option: 'default_product_cat', default_value: 0);
    if ($default > 0 && $term_id === $default) {
        return wc_error('wc_invalid_input', __(
            'The default product category cannot be modified or deleted.',
            domain: 'novamira-pro',
        ));
    }
    return null;
}

/**
 * Count published products assigned to a term (by taxonomy slug).
 */
function wc_count_products_in_term(int $term_id, string $taxonomy): int
{
    $term = get_term($term_id, $taxonomy);
    if (!$term instanceof WP_Term) {
        return 0;
    }
    // Use get_terms count field: it counts all posts but we want only publish status.
    // Direct db count is fastest and avoids loading product objects.
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return 0;
    }
    return wc_count_products_in_term_db($wpdb, $term_id, $taxonomy);
}

/**
 * @internal
 */
function wc_count_products_in_term_db(\wpdb $wpdb, int $term_id, string $taxonomy): int
{
    $tr_table = $wpdb->term_relationships;
    $tt_table = $wpdb->term_taxonomy;
    $p_table = $wpdb->posts;
    /** @var literal-string $sql */
    $sql = "SELECT COUNT(DISTINCT tr.object_id)
            FROM {$tr_table} tr
            INNER JOIN {$tt_table} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$p_table} p ON tr.object_id = p.ID
            WHERE tt.term_id = %d
              AND tt.taxonomy = %s
              AND p.post_type = 'product'
              AND p.post_status = 'publish'";
    $count = (string) $wpdb->get_var((string) $wpdb->prepare($sql, $term_id, $taxonomy));
    return (int) $count;
}

/**
 * Apply WC-specific category extras (thumbnail, display_type, menu_order).
 * Pass only the fields that are present in $input (PATCH semantics).
 *
 * @param array<string, mixed> $input
 */
function wc_set_category_extras(int $term_id, array $input): ?WP_Error
{
    if (array_key_exists('thumbnail_id', $input)) {
        $tid = $input['thumbnail_id'] === null ? 0 : (int) $input['thumbnail_id'];
        if ($tid !== 0) {
            /** @var \WP_Post|\WP_Post[]|null $att */
            $att = get_post($tid);
            $att_post = $att instanceof \WP_Post ? $att : null;
            if ($att_post === null || $att_post->post_type !== 'attachment' || $att_post->post_status === 'trash') {
                return wc_error('wc_attachment_not_found', sprintf(
                    __('Attachment %d not found.', domain: 'novamira-pro'),
                    $tid,
                ));
            }
            $mime = $att_post->post_mime_type;
            $allowed_image_mimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/avif'];
            if (!in_array(needle: $mime, haystack: $allowed_image_mimes, strict: true)) {
                return wc_error('wc_invalid_input', sprintf(
                    /* translators: 1: detected MIME type, 2: comma-separated allowed list */
                    __(
                        '`thumbnail_id` must reference a raster image (got MIME `%1$s`). Allowed: %2$s.',
                        domain: 'novamira-pro',
                    ),
                    $mime,
                    implode(', ', $allowed_image_mimes),
                ));
            }
        }
        update_term_meta(term_id: $term_id, meta_key: 'thumbnail_id', meta_value: $tid);
    }

    if (array_key_exists('display_type', $input)) {
        $allowed_display_types = ['default', 'products', 'subcategories', 'both'];
        $dt = (string) $input['display_type'];
        if (!in_array($dt, $allowed_display_types, strict: true)) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: submitted value, 2: allowed values */
                __('Invalid display_type `%1$s`. Allowed: %2$s', domain: 'novamira-pro'),
                $dt,
                implode(', ', $allowed_display_types),
            ));
        }
        update_term_meta(term_id: $term_id, meta_key: 'display_type', meta_value: $dt);
    }

    if (array_key_exists('menu_order', $input)) {
        $mo = (int) $input['menu_order'];
        if ($mo < 0) {
            return wc_error('wc_invalid_input', __('`menu_order` must be an integer >= 0.', domain: 'novamira-pro'));
        }
        update_term_meta(term_id: $term_id, meta_key: 'order', meta_value: $mo);
    }

    return null;
}

/**
 * Require a non-empty string `name` in the input (for term create).
 *
 * @param array<string, mixed> $input
 */
function wc_require_term_name(array $input): ?WP_Error
{
    if (!isset($input['name'])) {
        return wc_error('wc_invalid_input', __('`name` is required.', domain: 'novamira-pro'));
    }
    if (!is_string($input['name'])) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: actual PHP type */
            __('`name` must be a string (got %1$s).', domain: 'novamira-pro'),
            gettype($input['name']),
        ));
    }
    if (trim($input['name']) === '') {
        return wc_error('wc_invalid_input', __('`name` cannot be empty.', domain: 'novamira-pro'));
    }
    return null;
}

/**
 * Validate and normalize the `name` field for a term.
 * Returns the sanitized name string, or WP_Error on failure.
 *
 * @param array<string, mixed> $input
 */
function wc_validate_term_name(array $input): string|WP_Error
{
    if (!array_key_exists('name', $input)) {
        return '';
    }
    if (!is_string($input['name'])) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: actual PHP type */
            __('`name` must be a string (got %1$s).', domain: 'novamira-pro'),
            gettype($input['name']),
        ));
    }
    $name = wc_strip_unsafe_chars(trim($input['name']));
    if ($name === '') {
        return wc_error('wc_invalid_input', __('`name` cannot be empty.', domain: 'novamira-pro'));
    }
    $name_len = mb_strlen($name);
    if ($name_len > 200) {
        return wc_error('wc_invalid_input', sprintf(
            /* translators: 1: actual char length, 2: cap */
            __('`name` cannot exceed %2$d characters (got %1$d).', domain: 'novamira-pro'),
            $name_len,
            200,
        ));
    }
    return $name;
}

/**
 * Check for circular parent references when moving a term.
 *
 * @return WP_Error|null
 */
function wc_check_circular_parent(int $term_id, int $parent, string $taxonomy): ?WP_Error
{
    if ($term_id <= 0 || $parent <= 0) {
        return null;
    }
    if ($parent === $term_id) {
        return wc_error('wc_invalid_parent', __('A category cannot be its own parent.', domain: 'novamira-pro'));
    }
    // Walk up from $parent; if we hit $term_id it's a circular ref.
    $ancestor = get_term($parent, $taxonomy);
    while ($ancestor instanceof WP_Term && (int) $ancestor->parent > 0) {
        if ((int) $ancestor->parent === $term_id) {
            return wc_error('wc_invalid_parent', __(
                'Setting this parent would create a circular reference.',
                domain: 'novamira-pro',
            ));
        }
        $ancestor = get_term((int) $ancestor->parent, $taxonomy);
    }
    return null;
}

/**
 * Validate and prepare term fields (shared by create + edit).
 * Does NOT call wp_insert_term / wp_update_term — the caller does that.
 * Returns the wp_insert/update args array, or WP_Error on validation failure.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error  {name, description, slug, parent}
 */
function wc_apply_term_input(int $term_id, array $input, string $taxonomy): array|WP_Error
{
    $args = [];

    $name = wc_validate_term_name($input);
    if ($name instanceof WP_Error) {
        return $name;
    }
    if ($name !== '') {
        $args['name'] = $name;
    }

    // description
    if (array_key_exists('description', $input)) {
        if (!is_string($input['description'])) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: actual PHP type */
                __('`description` must be a string (got %1$s).', domain: 'novamira-pro'),
                gettype($input['description']),
            ));
        }
        $desc = wc_strip_unsafe_chars_preserve_ws($input['description']);
        $desc_len = mb_strlen($desc, encoding: '8bit');
        if ($desc_len > 65_536) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: actual byte length, 2: max allowed bytes */
                __('`description` cannot exceed %2$d bytes (got %1$d).', domain: 'novamira-pro'),
                $desc_len,
                65_536,
            ));
        }
        $args['description'] = $desc;
    }

    // slug
    if (array_key_exists('slug', $input) && $input['slug'] !== null && $input['slug'] !== '') {
        if (!is_string($input['slug'])) {
            return wc_error('wc_invalid_input', sprintf(
                /* translators: 1: actual PHP type */
                __('`slug` must be a string (got %1$s).', domain: 'novamira-pro'),
                gettype($input['slug']),
            ));
        }
        $args['slug'] = wc_strip_unsafe_chars($input['slug']);
    }

    // parent (categories only)
    if (
        $taxonomy === 'product_cat'
        && (array_key_exists('parent_id', $input) || array_key_exists('parent_slug', $input))
    ) {
        $parent = wc_resolve_parent_term($input, $taxonomy);
        if ($parent instanceof WP_Error) {
            return $parent;
        }
        $circ = wc_check_circular_parent($term_id, $parent, $taxonomy);
        if ($circ !== null) {
            return $circ;
        }
        $args['parent'] = $parent;
    }

    return $args;
}

/**
 * Read the menu_order for a product category term, migrating a legacy
 * 'order_<id>' key (written by sprint-4 earlier builds) to the canonical
 * 'order' key on first read.
 *
 * WooCommerce's native category sort UI and sort queries use the plain
 * 'order' meta key.
 */
function wc_resolve_menu_order(int $term_id): int
{
    /** @var mixed $mo_raw */
    $mo_raw = get_term_meta(term_id: $term_id, key: 'order', single: true);
    if ($mo_raw !== '' && $mo_raw !== false) {
        return (int) $mo_raw;
    }
    // Migrate legacy key written as 'order_<id>' (sprint 4, earlier writes)
    /** @var mixed $mo_legacy */
    $mo_legacy = get_term_meta(term_id: $term_id, key: 'order_' . $term_id, single: true);
    if ($mo_legacy !== '' && $mo_legacy !== false) {
        $menu_order = (int) $mo_legacy;
        update_term_meta(term_id: $term_id, meta_key: 'order', meta_value: $menu_order);
        delete_term_meta(term_id: $term_id, meta_key: 'order_' . $term_id);
        return $menu_order;
    }
    return 0;
}

/**
 * Build the full term output shape, consistent across list/create/edit.
 * Category-only fields (thumbnail_id, thumbnail_src, display_type, menu_order)
 * are included when $taxonomy = 'product_cat'; omitted for tags.
 *
 * @return array<string, mixed>
 */
function wc_term_to_array(WP_Term $term, string $taxonomy): array
{
    $term_id = (int) $term->term_id;
    $parent_id = (int) $term->parent;

    $parent_slug = null;
    if ($parent_id > 0) {
        $parent_term = get_term($parent_id, $taxonomy);
        $parent_slug = $parent_term instanceof WP_Term ? $parent_term->slug : null;
    }

    $row = [
        'id' => $term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'description' => $term->description,
        'count' => (int) $term->count,
    ];

    if ($taxonomy === 'product_cat') {
        $row['parent_id'] = $parent_id;
        $row['parent_slug'] = $parent_slug;
        $thumbnail_id = (int) get_term_meta(term_id: $term_id, key: 'thumbnail_id', single: true);
        $row['thumbnail_id'] = null;
        $row['thumbnail_src'] = null;
        if ($thumbnail_id > 0) {
            $thumbnail_src_raw = \wp_get_attachment_url($thumbnail_id);
            // Only expose the ID if the attachment file is still retrievable.
            // Attachment deleted but term meta not pruned — keep null to avoid stale IDs.
            if (is_string($thumbnail_src_raw) && $thumbnail_src_raw !== '') {
                $row['thumbnail_id'] = $thumbnail_id;
                $row['thumbnail_src'] = $thumbnail_src_raw;
            }
        }
        $dt = (string) get_term_meta(term_id: $term_id, key: 'display_type', single: true);
        $row['display_type'] = $dt !== '' ? $dt : 'default';
        $row['menu_order'] = wc_resolve_menu_order($term_id);
    }

    return $row;
}

/**
 * Strip thumbnail_id and thumbnail_src from a product category row.
 *
 * Used by list-product-categories when include_thumbnail is false (the default).
 * Keeping the stripping here avoids a boolean flag on wc_term_to_array.
 *
 * @param array<string, mixed> $row
 * @return array<string, mixed>
 */
function wc_term_strip_thumbnail(array $row): array
{
    unset($row['thumbnail_id'], $row['thumbnail_src']);
    return $row;
}

// ---------------------------------------------------------------------------
// Attribute helpers (Sprint 5 — Attributes + Attribute Terms + Shipping Classes)
// ---------------------------------------------------------------------------

/**
 * Ensure a pa_* attribute taxonomy is registered in the current PHP process.
 *
 * `wc_create_attribute()` stores the row in `wp_woocommerce_attribute_taxonomies` but WC only
 * calls `register_taxonomy` for those rows during `woocommerce_register_taxonomy` (an early
 * init hook). Attributes created mid-request therefore won't have their taxonomy registered
 * until the next page load. This helper registers the taxonomy on the fly so abilities that
 * create an attribute and immediately create its terms work within the same wp-cli invocation.
 */
function wc_ensure_attribute_taxonomy_registered(string $taxonomy_slug): void
{
    if (taxonomy_exists($taxonomy_slug)) {
        return;
    }

    $default_args = [
        'hierarchical' => false,
        'show_ui' => false,
        'query_var' => true,
        'rewrite' => false,
        'public' => false,
    ];
    /** @var mixed $filtered */
    $filtered = apply_filters('woocommerce_taxonomy_args_' . $taxonomy_slug, $default_args);
    /** @var array{'hierarchical'?: bool, 'show_ui'?: bool, 'query_var'?: bool, 'rewrite'?: bool|array{'ep_mask'?: int, 'hierarchical'?: bool, 'slug'?: string, 'with_front'?: bool}, 'public'?: bool} $args */
    $args = is_array($filtered) ? $filtered : $default_args;
    register_taxonomy($taxonomy_slug, ['product', 'product_variation'], $args);
}

/**
 * Extract the full pa_* taxonomy slug from a wc_get_attribute() result object.
 *
 * `wc_get_attribute()` returns the REST-shaped object {name, slug (full "pa_xxx"), ...}.
 * Older DB-shaped objects (from wc_get_attribute_taxonomies) use {attribute_name, ...}.
 * This helper handles both, always returning the full "pa_xxx" taxonomy slug.
 */
function wc_attr_object_to_taxonomy_slug(object $attr): string
{
    $slug_raw = (string) ($attr->slug ?? $attr->attribute_name ?? '');
    return wc_add_pa_prefix(wc_strip_pa_prefix($slug_raw));
}

/**
 * Convert a wp_count_terms() return value (int|string|WP_Error) to a plain int.
 * Returns 0 on WP_Error (e.g. unregistered taxonomy).
 *
 * @param int|string|\WP_Error $raw
 */
function wc_wp_count_to_int(int|string|\WP_Error $raw): int
{
    if ($raw instanceof \WP_Error) {
        return 0;
    }
    return is_int($raw) ? $raw : (int) $raw;
}

/**
 * Ensure a pa_* taxonomy is registered and return a WP_Error if it cannot be registered.
 */
function wc_ensure_taxonomy_or_error(string $taxonomy_slug): ?WP_Error
{
    wc_ensure_attribute_taxonomy_registered($taxonomy_slug);
    if (taxonomy_exists($taxonomy_slug)) {
        return null;
    }
    return wc_error('wc_attribute_not_found', sprintf(
        /* translators: taxonomy slug */
        __('Attribute taxonomy `%s` could not be registered.', domain: 'novamira-pro'),
        $taxonomy_slug,
    ));
}

/**
 * Strip all leading `pa_` prefixes from an attribute slug.
 * Handles double-prefixed slugs like "pa_pa_size" → "size".
 */
function wc_strip_pa_prefix(string $slug): string
{
    return preg_replace(pattern: '/^(pa_)+/', replacement: '', subject: $slug) ?? $slug;
}

/**
 * Add the `pa_` prefix to an attribute slug if not already present.
 */
function wc_add_pa_prefix(string $slug): string
{
    if (!str_starts_with($slug, 'pa_')) {
        return 'pa_' . $slug;
    }
    return $slug;
}

/**
 * Resolve an attribute from input: accept `attribute_id` OR `taxonomy_slug`, mutual exclusion.
 * Returns the integer attribute_id, or WP_Error on failure.
 *
 * @param array<string, mixed> $input
 * @return int|WP_Error
 */
function wc_resolve_attribute_id(array $input): int|WP_Error
{
    $has_id = array_key_exists('attribute_id', $input) && $input['attribute_id'] !== null;
    $has_slug =
        array_key_exists('taxonomy_slug', $input) && $input['taxonomy_slug'] !== null && $input['taxonomy_slug'] !== '';

    if ($has_id && $has_slug) {
        return wc_error('wc_invalid_input', __(
            'Provide either `attribute_id` or `taxonomy_slug`, not both.',
            domain: 'novamira-pro',
        ));
    }

    if (!$has_id && !$has_slug) {
        return wc_error('wc_invalid_input', __(
            'Either `attribute_id` or `taxonomy_slug` is required.',
            domain: 'novamira-pro',
        ));
    }

    if ($has_id) {
        $attr_id = (int) $input['attribute_id'];
        $attr = \wc_get_attribute($attr_id);
        if (!is_object($attr)) {
            return wc_error('wc_attribute_not_found', sprintf(
                /* translators: attribute id */
                __('Attribute with id %d not found.', domain: 'novamira-pro'),
                $attr_id,
            ));
        }
        return $attr_id;
    }

    // taxonomy_slug path
    $tax_slug = wc_add_pa_prefix(wc_strip_pa_prefix(trim((string) $input['taxonomy_slug'])));
    $attr_id = \wc_attribute_taxonomy_id_by_name($tax_slug);
    if ($attr_id <= 0) {
        return wc_error('wc_attribute_not_found', sprintf(
            /* translators: taxonomy slug */
            __('Attribute with taxonomy `%s` not found.', domain: 'novamira-pro'),
            $tax_slug,
        ));
    }
    return $attr_id;
}

/**
 * Extract the has_archives boolean from a WC attribute object.
 * Accepts both attribute_public (DB field) and has_archives (REST shape).
 */
function wc_attribute_has_archives(object $attr): bool
{
    if (property_exists($attr, 'attribute_public')) {
        /** @var mixed $val */
        $val = $attr->attribute_public;
        return $val === true || $val === 1 || $val === '1';
    }
    if (property_exists($attr, 'has_archives')) {
        /** @var mixed $val */
        $val = $attr->has_archives;
        return $val === true || $val === 1 || $val === '1';
    }
    return false;
}

/**
 * Build the full attribute output shape (D4).
 *
 * @return array<string, mixed>|WP_Error
 */
function wc_attribute_to_array(int $attribute_id): array|WP_Error
{
    $attr = \wc_get_attribute($attribute_id);
    if (!is_object($attr)) {
        return wc_error('wc_attribute_not_found', sprintf(
            /* translators: attribute id */
            __('Attribute with id %d not found.', domain: 'novamira-pro'),
            $attribute_id,
        ));
    }
    $slug = wc_strip_pa_prefix((string) ($attr->attribute_name ?? $attr->slug ?? ''));
    $taxonomy_slug = wc_add_pa_prefix($slug);
    $term_count = 0;
    /** @var list<WP_Term>|WP_Error $terms_raw */
    $terms_raw = get_terms(['taxonomy' => $taxonomy_slug, 'hide_empty' => false]);
    if (is_array($terms_raw)) {
        $term_count = count($terms_raw);
    }
    return [
        'id' => (int) ($attr->attribute_id ?? $attribute_id),
        'name' => (string) ($attr->attribute_label ?? $attr->name ?? ''),
        'slug' => $slug,
        'taxonomy_slug' => $taxonomy_slug,
        'type' => (string) ($attr->attribute_type ?? $attr->type ?? 'select'),
        'order_by' => (string) ($attr->attribute_orderby ?? $attr->order_by ?? 'menu_order'),
        'has_archives' => wc_attribute_has_archives($attr),
        'term_count' => $term_count,
        'has_products' => wc_count_products_using_attribute($attribute_id) > 0,
    ];
}

/**
 * Count products that use an attribute as a variation axis.
 * Queries products where `_product_attributes` postmeta contains the taxonomy key.
 */
function wc_count_products_using_attribute(int $attribute_id): int
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return 0;
    }
    $attr = \wc_get_attribute($attribute_id);
    if (!is_object($attr)) {
        return 0;
    }
    $slug_raw = (string) ($attr->slug ?? $attr->attribute_name ?? '');
    if ($slug_raw === '') {
        return 0;
    }
    $tax_slug = wc_add_pa_prefix(wc_strip_pa_prefix($slug_raw));

    $pm_table = $wpdb->postmeta;
    $p_table = $wpdb->posts;
    $pattern = '%"' . $wpdb->esc_like($tax_slug) . '"%is_taxonomy%i:1%';
    /** @var literal-string $sql */
    $sql = "SELECT COUNT(DISTINCT p.ID)
            FROM {$pm_table} pm
            INNER JOIN {$p_table} p ON pm.post_id = p.ID
            WHERE pm.meta_key = '_product_attributes'
              AND pm.meta_value LIKE %s
              AND p.post_type = 'product'
              AND p.post_status != 'trash'";
    $count = (string) $wpdb->get_var((string) $wpdb->prepare($sql, $pattern));
    return (int) $count;
}

/**
 * Count products/variations that reference a specific attribute term.
 */
function wc_count_products_using_attribute_term(int $term_id, string $taxonomy): int
{
    global $wpdb;
    if (!$wpdb instanceof \wpdb) {
        return 0;
    }
    $tr_table = $wpdb->term_relationships;
    $tt_table = $wpdb->term_taxonomy;
    $p_table = $wpdb->posts;
    /** @var literal-string $sql */
    $sql = "SELECT COUNT(DISTINCT tr.object_id)
            FROM {$tr_table} tr
            INNER JOIN {$tt_table} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
            INNER JOIN {$p_table} p ON tr.object_id = p.ID
            WHERE tt.term_id = %d
              AND tt.taxonomy = %s
              AND p.post_type IN ('product', 'product_variation')
              AND p.post_status != 'trash'";
    $count = (string) $wpdb->get_var((string) $wpdb->prepare($sql, $term_id, $taxonomy));
    return (int) $count;
}

/**
 * Build the full attribute term output shape (D6).
 *
 * @return array<string, mixed>
 */
function wc_attribute_term_to_array(WP_Term $term, int $attribute_id, string $taxonomy_slug): array
{
    return [
        'id' => (int) $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'description' => $term->description,
        'count' => (int) $term->count,
        'attribute_id' => $attribute_id,
        'taxonomy_slug' => $taxonomy_slug,
    ];
}

/**
 * Validate attribute field enums: type and order_by.
 * Returns validated args array with only the present + valid fields, or WP_Error.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function wc_validate_attribute_fields(array $input): array|WP_Error
{
    $args = [];

    if (array_key_exists('type', $input)) {
        $allowed_types = ['select', 'text'];
        $type = (string) $input['type'];
        if (!in_array($type, $allowed_types, strict: true)) {
            return wc_error('wc_invalid_attribute_type', sprintf(
                /* translators: 1: submitted type, 2: allowed values */
                __('Invalid attribute type `%1$s`. Allowed: %2$s', domain: 'novamira-pro'),
                $type,
                implode(', ', $allowed_types),
            ));
        }
        $args['type'] = $type;
    }

    if (array_key_exists('order_by', $input)) {
        $allowed_orderby = ['menu_order', 'name', 'name_num', 'id'];
        $order_by = (string) $input['order_by'];
        if (!in_array($order_by, $allowed_orderby, strict: true)) {
            return wc_error('wc_invalid_attribute_order_by', sprintf(
                /* translators: 1: submitted value, 2: allowed values */
                __('Invalid order_by `%1$s`. Allowed: %2$s', domain: 'novamira-pro'),
                $order_by,
                implode(', ', $allowed_orderby),
            ));
        }
        $args['order_by'] = $order_by;
    }

    if (array_key_exists('has_archives', $input)) {
        /** @var mixed $ha */
        $ha = $input['has_archives'];
        if (!is_bool($ha) && $ha !== 0 && $ha !== 1) {
            return wc_error('wc_invalid_input', __(
                '`has_archives` must be true or false (bool).',
                domain: 'novamira-pro',
            ));
        }
        $args['has_archives'] = is_bool($ha) ? $ha : $ha === 1;
    }

    return $args;
}

/**
 * Coerce a term id into a WP_Term that belongs to a `pa_*` taxonomy.
 * Returns wc_attribute_term_not_found when not found or not a global attribute term.
 */
function wc_post_to_attribute_term(int $term_id): WP_Term|WP_Error
{
    /** @var WP_Term|WP_Error|null $term */
    $term = get_term($term_id);
    if ($term instanceof WP_Term && $term->taxonomy === '') {
        // Stale cache entry (rare; Redis/Memcached scenario). Re-fetch from DB.
        /** @var list<object{attribute_name: string}> $taxonomies */
        $taxonomies = wc_get_attribute_taxonomies();
        foreach ($taxonomies as $tax) {
            /** @var WP_Term|WP_Error|null $candidate */
            $candidate = get_term($term_id, 'pa_' . $tax->attribute_name);
            if ($candidate instanceof WP_Term) {
                $term = $candidate;
                break;
            }
        }
    }
    if (!$term instanceof WP_Term) {
        return wc_error('wc_attribute_term_not_found', sprintf(
            /* translators: term id */
            __('Attribute term %d not found.', domain: 'novamira-pro'),
            $term_id,
        ));
    }
    if (!str_starts_with($term->taxonomy, 'pa_')) {
        return wc_error('wc_attribute_term_not_found', sprintf(
            /* translators: 1: term id, 2: actual taxonomy */
            __('Term %1$d does not belong to a global product attribute (taxonomy: %2$s).', domain: 'novamira-pro'),
            $term_id,
            $term->taxonomy,
        ));
    }
    return $term;
}
