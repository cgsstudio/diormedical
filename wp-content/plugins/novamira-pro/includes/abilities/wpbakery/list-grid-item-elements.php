<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPBakery;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: List the WPBakery Grid Item shortcodes (vc_gitem_*).
 *
 * Grid Builder uses a separate, conditionally-loaded shortcode family
 * for the per-item layout of vc_basic_grid / vc_masonry_grid. These
 * shortcodes (vc_gitem_row, vc_gitem_col, vc_gitem_post_title,
 * vc_gitem_post_meta, …) are NOT in `WPBMap::getAllShortCodes()`, so
 * `wpbakery-list-elements` / `wpbakery-get-element-schema` cannot
 * surface them. This ability lists them with their key params so the
 * agent can build a vc_grid_item template (CPT post) without needing
 * the build-page skill in context.
 */

wp_register_ability('novamira/wpbakery-list-grid-item-elements', [
    'label' => __('List WPBakery Grid Item Elements', domain: 'novamira-pro'),
    'description' => __(
        'Lists the vc_gitem_* shortcodes used inside vc_grid_item template posts (the per-item layout consumed by vc_basic_grid / vc_masonry_grid via the `item` att). Each entry includes the tag, a short purpose, the is_container flag, and the most-used atts with a one-line description. The response also includes a `dynamic_links` block listing the magic string values (`post_link`, `image`, `image_lightbox`, `custom`, `none`) that `link` atts on vc_btn / vc_gitem_image / vc_gitem_zone_* accept inside a grid_item context — these bypass the normal vc_link object encoding and are resolved against the current loop post at render time. These shortcodes are not in the global WPBakery registry, so they are not returned by wpbakery-list-elements; call this ability whenever you are building or editing a vc_grid_item template.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpbakery',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'elements' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'tag' => ['type' => 'string'],
                        'label' => ['type' => 'string'],
                        'purpose' => ['type' => 'string'],
                        'is_container' => ['type' => 'boolean'],
                        'atts' => [
                            'type' => 'object',
                            'description' => 'Map of att_name -> one-line description of what it does. Only the most-used atts are listed.',
                        ],
                    ],
                    'required' => ['tag', 'label', 'purpose', 'is_container', 'atts'],
                ],
            ],
            'total' => ['type' => 'integer'],
            'dynamic_links' => [
                'type' => 'object',
                'description' => 'Reference table for the "magic" string values accepted by the `link` att on vc_btn / vc_gitem_image / vc_gitem_zone_* inside a grid_item context. Pass them as plain strings (NOT as vc_link {url, title, target, rel} objects) — the grid renderer resolves each against the post currently iterating in the loop. Outside a grid_item these values are not interpreted.',
                'properties' => [
                    'values' => [
                        'type' => 'object',
                        'description' => 'Map of magic_string -> one-line description of what it resolves to.',
                    ],
                    'applies_to' => [
                        'type' => 'array',
                        'description' => 'Tags whose `link` att accepts these magic values inside a vc_grid_item template.',
                        'items' => ['type' => 'string'],
                    ],
                    'example' => [
                        'type' => 'object',
                        'description' => 'Example node showing the magic value in use.',
                    ],
                ],
            ],
            'dynamic_data_notes' => [
                'type' => 'array',
                'description' => 'Short notes on how to surface custom field / taxonomy / vendor data inside a grid item.',
                'items' => ['type' => 'string'],
            ],
        ],
        'required' => ['elements', 'total'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPBakery\\wpbakery_list_grid_item_elements',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this when building a vc_grid_item template (a custom per-item card layout for a vc_basic_grid / vc_masonry_grid loop). The vc_gitem_* shortcodes are stable across WPBakery 6.5–8.x. Wrap content in vc_gitem_row > vc_gitem_col (the required scaffold). Inside, use vc_gitem_post_title / vc_gitem_post_excerpt / vc_gitem_image for core post fields, and vc_gitem_post_meta key="<meta_key>" for custom fields. To make an element (vc_btn, vc_gitem_image, vc_gitem_zone_*) clickable to the loop post\'s permalink, set `link="post_link"` as a plain string (not a vc_link object) — the response\'s `dynamic_links` block lists every magic value and the elements that accept them.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     elements: list<array{
 *         tag: string,
 *         label: string,
 *         purpose: string,
 *         is_container: bool,
 *         atts: array<string, string>,
 *     }>,
 *     total: int,
 *     dynamic_links: array<string, mixed>,
 *     dynamic_data_notes: list<string>,
 * }
 */
function wpbakery_list_grid_item_elements(): array
{
    $catalogue = wpb_grid_item_catalogue();
    return [
        'elements' => $catalogue,
        'total' => count($catalogue),
        'dynamic_links' => wpb_grid_item_dynamic_links(),
        'dynamic_data_notes' => wpb_grid_item_dynamic_data_notes(),
    ];
}

/**
 * Magic string values accepted by the `link` att on certain grid-item
 * elements (vc_btn, vc_gitem_image, vc_gitem_zone_*) when nested inside
 * a vc_grid_item template. The grid renderer resolves each value against
 * the post currently iterating in the loop. Pass them as plain strings,
 * NOT as vc_link {url, title, target, rel} objects.
 *
 * Outside a grid_item context these values are not interpreted.
 *
 * @return array<string, mixed>
 */
function wpb_grid_item_dynamic_links(): array
{
    return [
        'values' => [
            'post_link' => 'Permalink of the current loop post. Most common choice for "card → article" navigation.',
            'image' => 'Direct URL of the featured image at full size. Use on vc_gitem_image to link the image to itself.',
            'image_lightbox' => 'Same as image but opens in WPBakery\'s built-in lightbox overlay.',
            'custom' => 'Use a user-supplied URL provided via the sibling `link_custom` att. Static across all loop items — rare.',
            'none' => 'No anchor wrapping. Default when omitted.',
        ],
        'applies_to' => [
            'vc_btn',
            'vc_gitem_image',
            'vc_gitem_zone',
            'vc_gitem_zone_a',
            'vc_gitem_zone_b',
            'vc_gitem_zone_c',
        ],
        'example' => [
            'tag' => 'vc_btn',
            'atts' => [
                'title' => 'Read more',
                'link' => 'post_link',
            ],
            'children' => [],
        ],
        'gotcha' => 'vc_btn outside a grid_item expects link as a vc_link OBJECT ({url, title, target, rel}). Inside a grid_item the same att accepts the magic STRING values above. The encoding switches based on context — write the right shape for where the node lives.',
    ];
}

/**
 * Short notes on how to surface custom fields / taxonomies / vendor data
 * inside a grid item. These complement the per-element atts and reduce
 * the need for the agent to know WPBakery folklore.
 *
 * @return list<string>
 */
function wpb_grid_item_dynamic_data_notes(): array
{
    return [
        'For any meta_key the post has (ACF, Pods, Meta Box, JetEngine — all store in wp_postmeta), use vc_gitem_post_meta key="<meta_key>".',
        'For ACF specifically, vc_gitem_acf is registered when ACF is active. It handles formatted output for complex fields (image, gallery, repeater) better than vc_gitem_post_meta.',
        'For built-in post fields beyond title/excerpt/date (post_id, post_author, post_categories, post_tags, post_comments), use vc_gitem_post_data source="<field>".',
        'For inline dynamic text inside a vc_column_text (NOT a grid item — for one-off pages), embed the source plugin\'s own shortcode in `_content`, e.g. _content="Price: [acf field=\'price\']" — WPBakery passes foreign shortcodes through and our abilities preserve them.',
        'Vendor extensions register more vc_gitem_template_attribute_<name> filters at runtime (WooCommerce adds price/rating/sku, etc.). Their availability is not introspectable from this ability; rely on the vendor\'s own docs.',
    ];
}

/**
 * The hardcoded catalogue of vc_gitem_* shortcodes — stable across
 * WPBakery 6.5–8.x. Extracted from the ability callback to keep the
 * callback small.
 *
 * @return list<array{
 *     tag: string,
 *     label: string,
 *     purpose: string,
 *     is_container: bool,
 *     atts: array<string, string>,
 * }>
 */
function wpb_grid_item_catalogue(): array
{
    return array_merge(
        wpb_grid_item_catalogue_scaffold(),
        wpb_grid_item_catalogue_post_fields(),
        wpb_grid_item_catalogue_zones(),
    );
}

/**
 * The required structural wrappers (row, col, link wrapper, animated block,
 * template wrapper) — everything you need to position other elements.
 *
 * @return list<array{tag: string, label: string, purpose: string, is_container: bool, atts: array<string, string>}>
 */
function wpb_grid_item_catalogue_scaffold(): array
{
    return [
        [
            'tag' => 'vc_gitem_row',
            'label' => 'Grid Item Row',
            'purpose' => 'Required outer scaffold of every Grid Item template. Contains one or more vc_gitem_col children.',
            'is_container' => true,
            'atts' => [],
        ],
        [
            'tag' => 'vc_gitem_col',
            'label' => 'Grid Item Column',
            'purpose' => 'Column inside a vc_gitem_row. Holds the bound content shortcodes.',
            'is_container' => true,
            'atts' => [
                'width' => 'Fractional column width like 1/1, 1/2, 1/3, 2/3, 1/4, 3/4. Defaults to 1/1.',
            ],
        ],
        [
            'tag' => 'vc_gitem_post_link',
            'label' => 'Post Permalink (wrapper)',
            'purpose' => 'Wraps inner content in an anchor to the current post\'s permalink. Put the link text/label in the node\'s `_content` (e.g. _content="Read more"), same as any textarea_html element.',
            'is_container' => true,
            'atts' => [],
        ],
        [
            'tag' => 'vc_gitem_animated_block',
            'label' => 'Animated Block',
            'purpose' => 'Wraps content in an animation effect (fade, slide, etc.).',
            'is_container' => true,
            'atts' => [],
        ],
        [
            'tag' => 'vc_gitem_template',
            'label' => 'Template Wrapper',
            'purpose' => 'Internal wrapper used by Grid Builder; rarely authored directly.',
            'is_container' => true,
            'atts' => [],
        ],
    ];
}

/**
 * Per-post field shortcodes (title, excerpt, image, meta, …) — the
 * dynamic-data primitives that resolve against the post currently
 * iterating in the loop.
 *
 * @return list<array{tag: string, label: string, purpose: string, is_container: bool, atts: array<string, string>}>
 */
function wpb_grid_item_catalogue_post_fields(): array
{
    return [
        [
            'tag' => 'vc_gitem_post_title',
            'label' => 'Post Title',
            'purpose' => 'Renders the current post\'s title (auto-bound to the loop iteration).',
            'is_container' => false,
            'atts' => [
                'align' => 'Text alignment: left | center | right.',
            ],
        ],
        [
            'tag' => 'vc_gitem_post_excerpt',
            'label' => 'Post Excerpt',
            'purpose' => 'Renders the current post\'s excerpt (auto-bound).',
            'is_container' => false,
            'atts' => [],
        ],
        [
            'tag' => 'vc_gitem_post_date',
            'label' => 'Post Date',
            'purpose' => 'Renders the current post\'s publish date.',
            'is_container' => false,
            'atts' => [],
        ],
        [
            'tag' => 'vc_gitem_post_content',
            'label' => 'Post Content',
            'purpose' => 'Renders the full current post\'s content.',
            'is_container' => false,
            'atts' => [],
        ],
        [
            'tag' => 'vc_gitem_image',
            'label' => 'Featured Image',
            'purpose' => 'Renders the current post\'s featured image.',
            'is_container' => false,
            'atts' => [
                'img_size' => 'WordPress image size: thumbnail | medium | large | full | custom slug. Defaults to medium.',
                'link' => 'Click target. Magic string only (see dynamic_links): "post_link" | "image" | "image_lightbox" | "custom" | "none". NOT a vc_link object here.',
            ],
        ],
        [
            'tag' => 'vc_gitem_post_meta',
            'label' => 'Custom Field Value',
            'purpose' => 'Renders the value of any single postmeta key on the current loop post. ACF / Pods / Meta Box / JetEngine all write to wp_postmeta, so any of their text/number/url fields work here — pass the underlying meta_key. Composite/serialized values (ACF gallery, repeater) won\'t render usefully; use vc_gitem_acf when ACF is active.',
            'is_container' => false,
            'atts' => [
                'key' => 'The meta_key to read (e.g. "isbn", "price", "rating", or any ACF/Pods/MB/JE field slug stored in postmeta). Required.',
            ],
        ],
        [
            'tag' => 'vc_gitem_post_data',
            'label' => 'Built-in Post Field',
            'purpose' => 'Renders a built-in WordPress post field selected by `source`.',
            'is_container' => false,
            'atts' => [
                'source' => 'One of: post_id, post_link, post_author, post_categories, post_tags, post_comments. Required.',
            ],
        ],
        [
            'tag' => 'vc_gitem_post_data_source',
            'label' => 'Post Data Source (linked)',
            'purpose' => 'Like vc_gitem_post_data but wraps the value in a link to the related taxonomy or author archive.',
            'is_container' => false,
            'atts' => [
                'source' => 'post_categories | post_tags | post_author. Required.',
            ],
        ],
        [
            'tag' => 'vc_gitem_post_categories',
            'label' => 'Post Categories',
            'purpose' => 'Renders the current post\'s categories as a comma-separated list.',
            'is_container' => false,
            'atts' => [],
        ],
    ];
}

/**
 * Animation zones (a / b / c) used by complex templates that flip between
 * a baseline card and a hover-revealed overlay. All accept the magic
 * `link` string values.
 *
 * @return list<array{tag: string, label: string, purpose: string, is_container: bool, atts: array<string, string>}>
 */
function wpb_grid_item_catalogue_zones(): array
{
    $zone_link_att = ['link' => 'Optional click target for the zone. Magic string only (see dynamic_links).'];
    return [
        [
            'tag' => 'vc_gitem_zone',
            'label' => 'Animation Zone',
            'purpose' => 'Defines an animated overlay zone (a / b / c). Wraps the zone-specific shortcodes below.',
            'is_container' => true,
            'atts' => $zone_link_att,
        ],
        [
            'tag' => 'vc_gitem_zone_a',
            'label' => 'Zone A',
            'purpose' => 'The first animation zone. Typically the always-visible card body.',
            'is_container' => true,
            'atts' => $zone_link_att,
        ],
        [
            'tag' => 'vc_gitem_zone_b',
            'label' => 'Zone B',
            'purpose' => 'The second animation zone. Typically the hover-revealed overlay.',
            'is_container' => true,
            'atts' => $zone_link_att,
        ],
        [
            'tag' => 'vc_gitem_zone_c',
            'label' => 'Zone C',
            'purpose' => 'The third animation zone (used by complex templates).',
            'is_container' => true,
            'atts' => $zone_link_att,
        ],
    ];
}
