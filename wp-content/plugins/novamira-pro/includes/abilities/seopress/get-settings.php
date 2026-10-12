<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\SeoPress;

use stdClass;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// SECRET-REDACTION GUARANTEE: every assembler below reads ONLY explicit, named,
// non-secret subkeys out of a SeoPress option array (via sp_sub_str / sp_sub_on /
// sp_slug_list). No assembler ever returns a whole option array, so no secret can
// leak by construction. Deliberately NOT read anywhere: the Pro license key
// (option seopress_pro_license_key / constant SEOPRESS_LICENSE_KEY), the
// instant-indexing API keys (seopress_instant_indexing_bing_api_key /
// _google_api_key / _api_key_txt), and any analytics auth token/secret. Do NOT add
// a generic "dump the option" path — that would reintroduce the leak surface.

wp_register_ability('novamira/seopress-get-settings', [
    'label' => __('Get SeoPress Settings', domain: 'novamira-pro'),
    'description' => __(
        'Reads a curated, read-only snapshot of the site-wide SeoPress configuration. `group` selects the block(s) (default "all"): "titles" = the title separator, homepage title/description, site alternative name, global robots toggles (noindex/nofollow/nosnippet/noimageindex), archive noindex toggles, and the LISTS of post types / taxonomies that carry a per-type title override; "social" = Open Graph + Twitter/X card toggles, the default social images, and the linked social account URLs; "knowledge" = the Person/Organisation knowledge-graph identity (type, name, image, phone, email, legal name, founding date); "sitemap" = the XML sitemap enable flag, the LISTS of included post types / taxonomies, and the image/video/author/HTML toggles; "advanced" = category/product permalink stripping, attachment redirects, and the PUBLIC site-verification meta codes (Google/Bing/Yandex/Baidu/Pinterest); "analytics" = the Google Analytics / GA4 / Matomo / Microsoft Clarity public ids and enable flags. ALL secrets are REDACTED and never returned: the SeoPress Pro license key, the instant-indexing API keys, and any analytics auth token. Writing settings is NOT an ability — use novamira/execute-php (e.g. update_option on the relevant seopress_*_option_name array) to change them.',
        domain: 'novamira-pro',
    ),
    'category' => 'seopress',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'group' => [
                'type' => 'string',
                'enum' => ['all', 'titles', 'social', 'knowledge', 'sitemap', 'advanced', 'analytics'],
                'default' => 'all',
                'description' => 'Which settings block to return.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'group' => ['type' => 'string'],
            'license_active' => ['type' => 'boolean'],
            'settings' => ['type' => 'object'],
        ],
        'required' => ['group', 'license_active', 'settings'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\SeoPress\seopress_get_settings',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Inspect the site-wide SeoPress configuration. Use group to stay focused. Every secret is redacted (the Pro license key, the instant-indexing API keys, and analytics auth tokens are NEVER returned); the verification codes and analytics ids surfaced here are public values already shipped in the page HTML. Writes are not an ability — use execute-php to change a setting (update_option on the relevant seopress_*_option_name array).',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * The titles/metas block (option seopress_titles_option_name). Override lists are
 * the LISTS of slugs that carry a per-type entry — never the full nested map.
 *
 * @return array<string, mixed>
 */
function spgs_titles(): array
{
    $o = sp_option_array('seopress_titles_option_name');

    /** @var mixed $single */
    $single = $o['seopress_titles_single_titles'] ?? null;
    /** @var mixed $tax */
    $tax = $o['seopress_titles_tax_titles'] ?? null;

    return [
        'separator' => sp_sub_str('seopress_titles_sep', $o),
        'home_title' => sp_sub_str('seopress_titles_home_site_title', $o),
        'home_description' => sp_sub_str('seopress_titles_home_site_desc', $o),
        'site_alt_name' => sp_sub_str('seopress_titles_home_site_title_alt', $o),
        'global_robots' => [
            'noindex' => sp_sub_on('seopress_titles_noindex', $o),
            'nofollow' => sp_sub_on('seopress_titles_nofollow', $o),
            'nosnippet' => sp_sub_on('seopress_titles_nosnippet', $o),
            'noimageindex' => sp_sub_on('seopress_titles_noimageindex', $o),
        ],
        'archive_noindex' => [
            'author' => sp_sub_on('seopress_titles_archives_author_noindex', $o),
            'date' => sp_sub_on('seopress_titles_archives_date_noindex', $o),
            'search' => sp_sub_on('seopress_titles_archives_search_title_noindex', $o),
            'attachment' => sp_sub_on('seopress_titles_attachments_noindex', $o),
            'paged' => sp_sub_on('seopress_titles_paged_noindex', $o),
        ],
        'single_overridden_post_types' => sp_slug_list(
            is_array($single) ? $single : [],
            static fn(mixed $entry): bool => sp_titles_entry_set($entry),
        ),
        'tax_overridden_taxonomies' => sp_slug_list(
            is_array($tax) ? $tax : [],
            static fn(mixed $entry): bool => sp_titles_entry_set($entry),
        ),
    ];
}

/**
 * The social / Open Graph block (option seopress_social_option_name).
 *
 * @return array<string, mixed>
 */
function spgs_social(): array
{
    $o = sp_option_array('seopress_social_option_name');
    return [
        'og_enabled' => sp_sub_on('seopress_social_facebook_og', $o),
        'default_og_image' => sp_clean_url(sp_sub_str('seopress_social_facebook_img', $o)),
        'twitter_card_enabled' => sp_sub_on('seopress_social_twitter_card', $o),
        'default_twitter_image' => sp_clean_url(sp_sub_str('seopress_social_twitter_card_img', $o)),
        'accounts' => [
            'facebook' => sp_clean_url(sp_sub_str('seopress_social_accounts_facebook', $o)),
            'twitter' => sp_sub_str('seopress_social_accounts_twitter', $o),
            'instagram' => sp_clean_url(sp_sub_str('seopress_social_accounts_instagram', $o)),
            'linkedin' => sp_clean_url(sp_sub_str('seopress_social_accounts_linkedin', $o)),
            'youtube' => sp_clean_url(sp_sub_str('seopress_social_accounts_youtube', $o)),
            'pinterest' => sp_clean_url(sp_sub_str('seopress_social_accounts_pinterest', $o)),
        ],
    ];
}

/**
 * The knowledge-graph identity block (also option seopress_social_option_name).
 *
 * @return array<string, mixed>
 */
function spgs_knowledge(): array
{
    $o = sp_option_array('seopress_social_option_name');
    return [
        'type' => sp_sub_str('seopress_social_knowledge_type', $o),
        'name' => sp_sub_str('seopress_social_knowledge_name', $o),
        'image' => sp_clean_url(sp_sub_str('seopress_social_knowledge_img', $o)),
        'phone' => sp_sub_str('seopress_social_knowledge_phone', $o),
        'email' => sp_sub_str('seopress_social_knowledge_email', $o),
        'legal_name' => sp_sub_str('seopress_social_knowledge_legal_name', $o),
        'founding_date' => sp_sub_str('seopress_social_knowledge_founding_date', $o),
    ];
}

/**
 * The XML sitemap block (option seopress_xml_sitemap_option_name). The included
 * lists are slug LISTS — never the full per-slug include map.
 *
 * @return array<string, mixed>
 */
function spgs_sitemap(): array
{
    $o = sp_option_array('seopress_xml_sitemap_option_name');

    /** @var mixed $pt */
    $pt = $o['seopress_xml_sitemap_post_types_list'] ?? null;
    /** @var mixed $tax */
    $tax = $o['seopress_xml_sitemap_taxonomies_list'] ?? null;

    return [
        'enabled' => sp_sub_on('seopress_xml_sitemap_general_enable', $o),
        'included_post_types' => sp_slug_list(
            is_array($pt) ? $pt : [],
            static fn(mixed $entry): bool => sp_sitemap_included($entry),
        ),
        'included_taxonomies' => sp_slug_list(
            is_array($tax) ? $tax : [],
            static fn(mixed $entry): bool => sp_sitemap_included($entry),
        ),
        'images' => sp_sub_on('seopress_xml_sitemap_img_enable', $o),
        'video' => sp_sub_on('seopress_xml_sitemap_video_enable', $o),
        'author' => sp_sub_on('seopress_xml_sitemap_author_enable', $o),
        'html' => sp_sub_on('seopress_xml_sitemap_html_enable', $o),
    ];
}

/**
 * The advanced block (option seopress_advanced_option_name). The verification
 * codes are PUBLIC site-verification meta tags shipped in the page <head>, so
 * they are safe to return — they are not secrets.
 *
 * @return array<string, mixed>
 */
function spgs_advanced(): array
{
    $o = sp_option_array('seopress_advanced_option_name');
    return [
        'category_url_stripped' => sp_sub_on('seopress_advanced_advanced_category_url', $o),
        'product_cat_url_stripped' => sp_sub_on('seopress_advanced_advanced_product_cat_url', $o),
        'attachments_redirect_parent' => sp_sub_on('seopress_advanced_advanced_attachments', $o),
        'attachments_redirect_file' => sp_sub_on('seopress_advanced_advanced_attachments_file', $o),
        'verification' => [
            'google' => sp_sub_str('seopress_advanced_advanced_google', $o),
            'bing' => sp_sub_str('seopress_advanced_advanced_bing', $o),
            'yandex' => sp_sub_str('seopress_advanced_advanced_yandex', $o),
            'baidu' => sp_sub_str('seopress_advanced_advanced_baidu', $o),
            'pinterest' => sp_sub_str('seopress_advanced_advanced_pinterest', $o),
        ],
    ];
}

/**
 * The analytics block (option seopress_google_analytics_option_name). Every value
 * here is a PUBLIC tracking id (G-XXXX, UA-XXXX, a Clarity project id, a Matomo
 * site id) — the auth tokens/secrets that pair with them are NOT read.
 *
 * @return array<string, mixed>
 */
function spgs_analytics(): array
{
    $o = sp_option_array('seopress_google_analytics_option_name');
    return [
        'enabled' => sp_sub_on('seopress_google_analytics_enable', $o),
        'ga4_id' => sp_sub_str('seopress_google_analytics_ga4', $o),
        'ua_id' => sp_sub_str('seopress_google_analytics_ga', $o),
        'clarity_enabled' => sp_sub_on('seopress_google_analytics_clarity_enable', $o),
        'clarity_project_id' => sp_sub_str('seopress_google_analytics_clarity_project_id', $o),
        'matomo_url' => sp_sub_str('seopress_google_analytics_matomo_id', $o),
        'matomo_site_id' => sp_sub_str('seopress_google_analytics_matomo_site_id', $o),
    ];
}

/**
 * Assemble one named group's block. One small assembler per group keeps the
 * dispatcher's complexity flat; an unknown group yields an empty object.
 *
 * @return array<string, mixed>|stdClass
 */
function spgs_block(string $group): array|stdClass
{
    return match ($group) {
        'titles' => spgs_titles(),
        'social' => spgs_social(),
        'knowledge' => spgs_knowledge(),
        'sitemap' => spgs_sitemap(),
        'advanced' => spgs_advanced(),
        'analytics' => spgs_analytics(),
        default => new stdClass(),
    };
}

/**
 * @return list<string>
 */
function spgs_groups(): array
{
    return ['titles', 'social', 'knowledge', 'sitemap', 'advanced', 'analytics'];
}

/**
 * @param array{group?: string} $args
 * @return array{group: string, license_active: bool, settings: array<string, mixed>}|WP_Error
 */
function seopress_get_settings(array $args): array|WP_Error
{
    if (!sp_detect_setup()['active']) {
        return sp_not_active();
    }

    $group = $args['group'] ?? 'all';
    $allowed = array_merge(['all'], spgs_groups());
    if (!in_array($group, $allowed, strict: true)) {
        return new WP_Error('seopress_invalid_group', 'group must be one of: ' . implode(', ', $allowed), [
            'status' => 400,
        ]);
    }

    $settings = [];
    foreach (spgs_groups() as $name) {
        if ($group !== 'all' && $group !== $name) {
            continue;
        }
        $settings[$name] = spgs_block($name);
    }

    return [
        'group' => $group,
        // Informational only — the license KEY itself is never read or returned.
        'license_active' => sp_detect_setup()['licensed'],
        'settings' => $settings,
    ];
}
