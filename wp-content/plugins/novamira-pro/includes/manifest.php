<?php

// includes/manifest.php — ordered specialization entries (data + callables only). Grows per migration task.
// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro;

if (!defined('ABSPATH')) {
    exit();
}

return [
    // entries in canonical category-registration order:
    // field -> seo -> builder -> theme-block -> commerce-code -> form -> translation -> core
    'ase' => [
        'slug' => 'ase',
        'category_label' => 'ASE',
        'category_description' => 'Abilities for the Admin and Site Enhancements (ASE) Pro plugin: custom field types, custom post types, and custom taxonomies.',
        'group' => 'field',
        'gate' => static fn(): bool => (
            defined('ASENHA_VERSION')
            && version_compare((string) constant('ASENHA_VERSION'), NOVAMIRA_PRO_ASE_MIN_VERSION, operator: '>=')
        ),
        'loader' => static function (): void {
            novamira_pro_load_ase_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_ASE_MIN_VERSION', 'value' => '8.7.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['admin-site-enhancements', 'admin-site-enhancements-pro'],
        'skills' => ['ase-integration', 'content-model-schema', 'content-model-migration', 'dynamic-data-binding'],
    ],
    'pods' => [
        'slug' => 'pods',
        'category_label' => 'Pods',
        'category_description' => 'Abilities for the Pods Custom Content Types and Fields plugin — model post types, taxonomies, advanced content types, fields, groups, and item content.',
        'group' => 'field',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Pods\pods_min_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_pods_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_PODS_MIN_VERSION', 'value' => '3.2.1.1', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['pods'],
        'skills' => ['pods-integration', 'content-model-schema', 'content-model-migration', 'dynamic-data-binding'],
    ],
    'jetengine' => [
        'slug' => 'jetengine',
        'category_label' => 'JetEngine',
        'category_description' => 'Abilities for JetEngine meta boxes, options pages, and Custom Content Types.',
        'group' => 'field',
        'gate' => static fn(): bool => (
            class_exists('Jet_Engine')
            && function_exists('jet_engine')
            && version_compare(jet_engine()->get_version(), NOVAMIRA_PRO_JETENGINE_MIN_VERSION, operator: '>=')
        ),
        'loader' => static function (): void {
            novamira_pro_load_jetengine_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_JETENGINE_MIN_VERSION', 'value' => '3.8.6.2', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['jet-engine'],
        'skills' => [
            'jetengine-integration',
            'content-model-schema',
            'content-model-migration',
            'dynamic-data-binding',
        ],
    ],
    'acf' => [
        'slug' => 'acf',
        'category_label' => 'Advanced Custom Fields',
        'category_description' => 'Abilities for ACF and ACF Pro field groups and values.',
        'group' => 'field',
        'gate' => static fn(): bool => (
            class_exists('ACF')
            && defined('ACF_VERSION')
            && version_compare((string) constant('ACF_VERSION'), NOVAMIRA_PRO_ACF_MIN_VERSION, operator: '>=')
        ),
        'loader' => static function (): void {
            novamira_pro_load_acf_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_ACF_MIN_VERSION', 'value' => '6.5.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['advanced-custom-fields', 'advanced-custom-fields-pro'],
        'skills' => ['acf-integration', 'content-model-schema', 'content-model-migration', 'dynamic-data-binding'],
    ],
    'metabox' => [
        'slug' => 'metabox',
        'category_label' => 'Meta Box',
        'category_description' => 'Abilities for Meta Box field groups, values, custom post types, taxonomies, settings pages, and object relationships.',
        'group' => 'field',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Metabox\mb_min_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_metabox_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_METABOX_MIN_VERSION', 'value' => '5.11.2', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['meta-box', 'meta-box-aio'],
        'skills' => ['metabox-integration', 'content-model-schema', 'content-model-migration', 'dynamic-data-binding'],
    ],
    'acpt' => [
        'slug' => 'acpt',
        'category_label' => 'ACPT',
        'category_description' => 'Abilities for the ACPT (Advanced Custom Post Types) plugin: custom post types, taxonomies, meta groups, fields, and option pages.',
        'group' => 'field',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Acpt\acpt_min_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_acpt_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_ACPT_MIN_VERSION', 'value' => '2.0.52', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['advanced-custom-post-type'],
        'skills' => ['acpt-integration', 'content-model-schema', 'content-model-migration', 'dynamic-data-binding'],
    ],
    'yoast' => [
        'slug' => 'yoast',
        'category_label' => 'Yoast SEO',
        'category_description' => 'Abilities for Yoast SEO and Yoast SEO Premium: per-post and per-term SEO metadata, site-wide settings, and redirects.',
        'group' => 'seo',
        'gate' => static fn(): bool => (
            defined('WPSEO_VERSION')
            && version_compare((string) constant('WPSEO_VERSION'), NOVAMIRA_PRO_YOAST_MIN_VERSION, operator: '>=')
        ),
        'loader' => static function (): void {
            novamira_pro_load_yoast_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_YOAST_MIN_VERSION', 'value' => '21.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['wordpress-seo', 'wordpress-seo-premium'],
        'skills' => ['yoast-integration'],
    ],
    'rank-math' => [
        'slug' => 'rank-math',
        'category_label' => 'Rank Math SEO',
        'category_description' => 'Abilities for Rank Math SEO: per-post and per-term SEO metadata, site-wide settings, and schema.',
        'group' => 'seo',
        'gate' => static fn(): bool => (
            defined('RANK_MATH_VERSION')
            && version_compare(
                (string) constant('RANK_MATH_VERSION'),
                NOVAMIRA_PRO_RANK_MATH_MIN_VERSION,
                operator: '>=',
            )
        ),
        'loader' => static function (): void {
            novamira_pro_load_rank_math_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_RANK_MATH_MIN_VERSION', 'value' => '1.0.200', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['seo-by-rank-math', 'seo-by-rank-math-pro'],
        'skills' => ['rank-math-integration'],
    ],
    'aioseo' => [
        'slug' => 'aioseo',
        'category_label' => 'All in One SEO',
        'category_description' => 'Abilities for All in One SEO (free + Pro): per-post and per-term SEO metadata, site-wide settings, schema, and redirects.',
        'group' => 'seo',
        'gate' => static fn(): bool => (
            defined('AIOSEO_VERSION')
            && version_compare((string) constant('AIOSEO_VERSION'), NOVAMIRA_PRO_AIOSEO_MIN_VERSION, operator: '>=')
        ),
        'loader' => static function (): void {
            novamira_pro_load_aioseo_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_AIOSEO_MIN_VERSION', 'value' => '4.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['all-in-one-seo-pack', 'all-in-one-seo-pack-pro'],
        'skills' => ['aioseo-integration'],
    ],
    'seopress' => [
        'slug' => 'seopress',
        'category_label' => 'SeoPress',
        'category_description' => 'Abilities for SeoPress (free + Pro): per-post and per-term SEO metadata, site-wide settings, structured data, and redirects.',
        'group' => 'seo',
        'gate' => static fn(): bool => (
            defined('SEOPRESS_VERSION')
            && version_compare((string) constant('SEOPRESS_VERSION'), NOVAMIRA_PRO_SEOPRESS_MIN_VERSION, operator: '>=')
        ),
        'loader' => static function (): void {
            novamira_pro_load_seopress_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_SEOPRESS_MIN_VERSION', 'value' => '8.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['wp-seopress', 'wp-seopress-pro'],
        'skills' => ['seopress-integration'],
    ],
    'bricks' => [
        'slug' => 'bricks',
        'category_label' => 'Bricks Builder',
        'category_description' => 'Abilities for the Bricks Builder page builder.',
        'group' => 'builder',
        'gate' => static fn(): bool => defined('BRICKS_VERSION'),
        'loader' => static function (): void {
            novamira_pro_load_bricks_abilities();
        },
        'floors' => [],
        'editions' => ['free', 'pro'],
        'themes' => ['bricks'],
        'skills' => [
            'bricks-build-page',
            'bricks-query-loops',
            'bricks-nestables',
            'bricks-components',
            'bricks-page-settings',
            'bricks-element-gotchas',
            'bricks-verify',
            'content-model-schema',
            'content-model-migration',
            'dynamic-data-binding',
        ],
    ],
    'bricksforge' => [
        'slug' => 'bricksforge',
        'category_label' => 'Bricksforge',
        'category_description' => 'Abilities for Bricksforge tools, elements, popups, and email templates.',
        'group' => 'builder',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Bricksforge\brf_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_bricksforge_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_BRICKSFORGE_MIN_VERSION', 'value' => '3.1.8', 'kind' => 'base']],
        'editions' => ['pro'],
        'plugins' => ['bricksforge'],
        'skills' => ['bricksforge-integration'],
    ],
    'dynamic-content-for-elementor' => [
        'slug' => 'dynamic-content-for-elementor',
        'category_label' => 'Dynamic Content for Elementor',
        'category_description' => 'Abilities for Dynamic Content for Elementor PDF templates, with specialized guidance for its widgets and extensions.',
        'group' => 'builder',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\DynamicContentForElementor\dce_min_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_dynamic_content_for_elementor_abilities();
        },
        'floors' => [
            [
                'constant' => 'NOVAMIRA_PRO_DYNAMIC_CONTENT_FOR_ELEMENTOR_MIN_VERSION',
                'value' => '3.4.0',
                'kind' => 'base',
            ],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['dynamic-content-for-elementor'],
        'skills' => ['dynamic-content-for-elementor'],
    ],
    'elementor' => [
        'slug' => 'elementor',
        'category_label' => 'Elementor',
        'category_description' => 'Abilities for the Elementor page builder.',
        'group' => 'builder',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Elementor\el_min_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_elementor_abilities();
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_ELEMENTOR_MIN_VERSION', 'value' => '3.6.0', 'kind' => 'base'],
            ['constant' => null, 'value' => '4.0.0', 'kind' => 'atomic'],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['elementor', 'elementor-pro'],
        'skills' => [
            'elementor-build-page',
            'elementor-convert-to-v4',
            'content-model-schema',
            'content-model-migration',
            'dynamic-data-binding',
            // Third-party addon skills. They register no abilities of their own, so
            // they have no specialization to be owned by; without an owner here a
            // skill has no feature to follow and no switch the user can reach.
            // Elementor is what they all orchestrate, so this is the entry they
            // belong to. Dynamic Content is absent on purpose: it ships abilities
            // and is owned by its own entry.
            'element-pack-elementor',
            'happy-addons-elementor',
            'ht-mega-elementor',
            'jetelements-elementor',
            'pafe-elementor',
            'powerpack-elementor',
            'qi-addons-elementor',
            'ultimate-addons-elementor',
            'unlimited-elements-for-elementor',
            'xpro-addons-elementor',
        ],
    ],
    'divi' => [
        'slug' => 'divi',
        'category_label' => 'Divi',
        'category_description' => 'Abilities for the Divi 5 page builder.',
        'group' => 'builder',
        'gate' => static fn(): bool => \Novamira\Pro\novamira_pro_divi_available(),
        'loader' => static function (): void {
            novamira_pro_load_divi_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_DIVI_MIN_VERSION', 'value' => '5.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'themes' => ['divi'],
        'skills' => ['divi-build-page'],
    ],
    'avada' => [
        'slug' => 'avada',
        'category_label' => 'Avada',
        'category_description' => 'Abilities for Avada theme options, global colors and typography, sliders, portfolios, FAQs, and hooks.',
        'group' => 'builder',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Avada\avada_theme_available(),
        'loader' => static function (): void {
            novamira_pro_load_avada_abilities();
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_AVADA_MIN_VERSION', 'value' => '7.15.0', 'kind' => 'base'],
            ['constant' => 'NOVAMIRA_PRO_FUSION_BUILDER_MIN_VERSION', 'value' => '3.13.0', 'kind' => 'plugin'],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['fusion-builder', 'fusion-core'],
        'themes' => ['avada'],
        'skills' => ['avada-integration'],
    ],
    'wpbakery' => [
        'slug' => 'wpbakery',
        'category_label' => 'WPBakery Page Builder',
        'category_description' => 'Abilities for WPBakery Page Builder (formerly Visual Composer).',
        'group' => 'builder',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\WPBakery\wpb_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_wpbakery_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_WPBAKERY_MIN_VERSION', 'value' => '6.5.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['js_composer'],
        'skills' => ['wpbakery-build-page', 'wpbakery-register-element'],
    ],
    'breakdance' => [
        'slug' => 'breakdance',
        'category_label' => 'Breakdance',
        'category_description' => 'Abilities for the Breakdance page builder: setup discovery, templates, global blocks, element tree, design tokens, dynamic data, and conditions.',
        'group' => 'builder',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Breakdance\bd_plugin_available(),
        'loader' => static function (): void {
            novamira_pro_load_breakdance_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_BREAKDANCE_MIN_VERSION', 'value' => '2.7.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['breakdance'],
        'skills' => ['breakdance-build-page'],
    ],
    'oxygen' => [
        'slug' => 'oxygen',
        'category_label' => 'Oxygen',
        'category_description' => 'Abilities for Oxygen 6 pages, templates, loops, design controls, dynamic data, conditions, and form submissions.',
        'group' => 'builder',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Oxygen\oxygen_plugin_available(),
        'loader' => static function (): void {
            novamira_pro_load_oxygen_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_OXYGEN_MIN_VERSION', 'value' => '6.0.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['oxygen'],
        'skills' => ['oxygen-build-page'],
    ],
    'mosaic' => [
        'slug' => 'mosaic',
        'category_label' => 'Mosaic',
        'category_description' => 'Abilities for the Mosaic Pro page builder by Nextend: themes, templates, element tree, design system collections and variables, components, Classes and Variants.',
        'group' => 'builder',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Mosaic\mo_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_mosaic_abilities();
        },
        'eager_bootstrap' => static function (): void {
            add_action(
                'plugins_loaded',
                callback: static function (): void {
                    novamira_pro_mosaic_repair_legacy_empty_blobs();
                },
                priority: 1,
            );
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_MOSAIC_MIN_VERSION', 'value' => '1.0.3', 'kind' => 'base']],
        'max_version' => ['constant' => 'NOVAMIRA_PRO_MOSAIC_MAX_VERSION', 'value' => '2.0.0'],
        'editions' => ['pro'],
        'plugins' => ['mosaic'],
        'skills' => ['mosaic-build-page'],
    ],
    'etch' => [
        'slug' => 'etch',
        'category_label' => 'Etch',
        'category_description' => 'Abilities for the Etch builder — an HTML-first WordPress builder that stores page content as Gutenberg block markup.',
        'group' => 'builder',
        'gate' => static fn(): bool => \Novamira\Pro\novamira_pro_etch_available(),
        'loader' => static function (): void {
            novamira_pro_load_etch_abilities();
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_ETCH_MIN_VERSION', 'value' => '1.4.19', 'kind' => 'base'],
            ['constant' => 'NOVAMIRA_PRO_ETCH_CF_VALUES_MIN_VERSION', 'value' => '1.5.0', 'kind' => 'feature'],
        ],
        'editions' => ['free'],
        'plugins' => ['etch'],
        'skills' => ['etch-build-page'],
    ],
    'beaver-builder' => [
        'slug' => 'beaver-builder',
        'category_label' => 'Beaver Builder',
        'category_description' => 'Abilities for building and editing Beaver Builder pages, templates, modules, global colors, and typography.',
        'group' => 'builder',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\BeaverBuilder\bb_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_beaver_builder_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_BEAVER_BUILDER_MIN_VERSION', 'value' => '2.9.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['bb-plugin', 'beaver-builder-lite-version'],
        'skills' => ['beaver-builder-build-page'],
    ],
    'generatepress' => [
        'slug' => 'generatepress',
        'category_label' => 'GeneratePress',
        'category_description' => 'Abilities for the GeneratePress theme and GP Premium: theme settings, Elements, Site Library and Font Library.',
        'group' => 'theme-block',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\GeneratePress\gp_theme_available(),
        'loader' => static function (): void {
            novamira_pro_load_generatepress_abilities();
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_GP_THEME_MIN_VERSION', 'value' => '3.0.0', 'kind' => 'theme'],
            ['constant' => 'NOVAMIRA_PRO_GP_PREMIUM_MIN_VERSION', 'value' => '2.0.0', 'kind' => 'premium'],
        ],
        'editions' => ['free', 'premium'],
        'plugins' => ['gp-premium'],
        'themes' => ['generatepress'],
        'skills' => ['generatepress-build-page'],
    ],
    'generateblocks' => [
        'slug' => 'generateblocks',
        'category_label' => 'GenerateBlocks',
        'category_description' => 'Abilities for discovering the GenerateBlocks available on the site and their settings.',
        'group' => 'theme-block',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\GenerateBlocks\gb_available(),
        'loader' => static function (): void {
            novamira_pro_load_generateblocks_abilities();
        },
        'floors' => [],
        'editions' => ['free', 'pro'],
        'plugins' => ['generateblocks', 'generateblocks-pro'],
        'skills' => ['generateblocks-build-page'],
    ],
    'kadence' => [
        'slug' => 'kadence',
        'category_label' => 'Kadence',
        'category_description' => 'Abilities for Kadence theme settings, colors, typography, headers, and navigation.',
        'group' => 'theme-block',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Kadence\kadence_theme_available(),
        'loader' => static function (): void {
            novamira_pro_load_kadence_abilities();
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_KADENCE_THEME_MIN_VERSION', 'value' => '1.2.0', 'kind' => 'theme'],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['kadence-pro'],
        'themes' => ['kadence'],
        'skills' => ['kadence-build-page'],
    ],
    'astra' => [
        'slug' => 'astra',
        'category_label' => 'Astra',
        'category_description' => 'Abilities for Astra theme settings, colors, typography, headers, footers, page layouts, and supported Pro features.',
        'group' => 'theme-block',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Astra\astra_theme_available(),
        'loader' => static function (): void {
            novamira_pro_load_astra_abilities();
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_ASTRA_THEME_MIN_VERSION', 'value' => '4.13.5', 'kind' => 'theme'],
            ['constant' => 'NOVAMIRA_PRO_ASTRA_ADDON_MIN_VERSION', 'value' => '4.13.5', 'kind' => 'base'],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['astra-addon'],
        'themes' => ['astra'],
        'skills' => ['astra-integration'],
    ],
    'blocksy' => [
        'slug' => 'blocksy',
        'category_label' => 'Blocksy',
        'category_description' => 'Abilities for Blocksy theme options, colors, headers, footers, page settings, Companion extensions, and Content Blocks.',
        'group' => 'theme-block',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Blocksy\blocksy_theme_present(),
        'loader' => static function (): void {
            novamira_pro_load_blocksy_abilities();
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_BLOCKSY_THEME_MIN_VERSION', 'value' => '2.1.47', 'kind' => 'theme'],
            ['constant' => 'NOVAMIRA_PRO_BLOCKSY_COMPANION_MIN_VERSION', 'value' => '2.1.47', 'kind' => 'base'],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['blocksy-companion', 'blocksy-companion-pro'],
        'themes' => ['blocksy'],
        'skills' => ['blocksy-build-page'],
    ],
    'voxel' => [
        'slug' => 'voxel',
        'category_label' => 'Voxel',
        'category_description' => 'Abilities for Voxel post types, fields, content, templates, taxonomies, memberships, commerce, and community data.',
        'group' => 'theme-block',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Voxel\voxel_theme_present(),
        'loader' => static function (): void {
            novamira_pro_load_voxel_abilities();
        },
        // The search-index and rewrite-rule maintenance must run on ordinary
        // requests too, not only when the Abilities API is instantiated. Without
        // this, a post type created by an agent gets its index table only on the
        // next MCP call: a human visiting the site in between sees stale permalinks,
        // and a post added from wp-admin never reaches the index. `wp_loaded` is the
        // earliest hook where the theme is loaded and its post types are registered
        // — `voxel_theme_present()` needs \Voxel\get(), which does not exist at
        // plugins_loaded. Including the files is what schedules the work.
        //
        // Deliberately not behind `license_allows_abilities()`, unlike the loader.
        // This finishes work the abilities already started and can create nothing
        // on its own: with no licence no abilities run, so no marker is ever
        // written and the pass costs one look at an array already in memory. But
        // when a licence lapses *after* a post type was created, gating here would
        // leave that site with a permanently broken search index — punishing the
        // content for a billing state. Do not add the gate.
        'eager_bootstrap' => static function (): void {
            add_action('wp_loaded', callback: static function (): void {
                if (!\Novamira\Pro\Abilities\Voxel\voxel_theme_present()) {
                    return;
                }
                $dir = __DIR__ . '/abilities/voxel/';
                require_once $dir . 'runtime.php';
                require_once $dir . 'helpers.php';
                require_once $dir . 'config-helpers.php';
            });
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_VOXEL_THEME_MIN_VERSION', 'value' => '1.7.8.3', 'kind' => 'theme'],
        ],
        'editions' => ['free', 'pro'],
        'themes' => ['voxel'],
        'skills' => ['voxel-integration'],
    ],
    'kadenceblocks' => [
        'slug' => 'kadenceblocks',
        'category_label' => 'Kadence Blocks',
        'category_description' => 'Abilities for discovering the Kadence Blocks available on the site and their settings.',
        'group' => 'theme-block',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\KadenceBlocks\kb_available(),
        'loader' => static function (): void {
            novamira_pro_load_kadenceblocks_abilities();
        },
        'floors' => [],
        'editions' => ['free', 'pro'],
        'plugins' => ['kadence-blocks', 'kadence-blocks-pro'],
        'skills' => [],
    ],
    'spectra-one' => [
        'slug' => 'spectra-one',
        'category_label' => 'Spectra One',
        'category_description' => 'Abilities for Spectra One design settings, templates, navigation, patterns, fonts, style variations, and page display settings.',
        'group' => 'theme-block',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\SpectraOne\spectra_one_theme_available(),
        'loader' => static function (): void {
            novamira_pro_load_spectra_one_abilities();
        },
        'floors' => [
            [
                'constant' => 'NOVAMIRA_PRO_SPECTRA_ONE_THEME_MIN_VERSION',
                'value' => '1.2.2',
                'kind' => 'theme',
            ],
            [
                'constant' => 'NOVAMIRA_PRO_SPECTRA_ONE_PLUGIN_MIN_VERSION',
                'value' => '2.16.0',
                'kind' => 'plugin',
            ],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['ultimate-addons-for-gutenberg'],
        'themes' => ['spectra-one'],
        'skills' => ['spectra-one-build-page'],
    ],
    'spectra' => [
        'slug' => 'spectra',
        'category_label' => 'Spectra',
        'category_description' => 'Abilities for Spectra blocks, popups, editor settings, and fonts.',
        'group' => 'theme-block',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Spectra\spectra_plugin_available(),
        'loader' => static function (): void {
            novamira_pro_load_spectra_abilities();
        },
        'floors' => [
            [
                'constant' => 'NOVAMIRA_PRO_SPECTRA_MIN_VERSION',
                'value' => '2.19.0',
                'kind' => 'base',
            ],
            [
                'constant' => 'NOVAMIRA_PRO_SPECTRA_PRO_MIN_VERSION',
                'value' => '1.2.0',
                'kind' => 'pro',
            ],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['ultimate-addons-for-gutenberg', 'spectra-pro'],
        'skills' => ['spectra-build-page', 'spectra-integration'],
    ],
    'spectra-blocks' => [
        'slug' => 'spectra-blocks',
        'category_label' => 'Spectra Blocks',
        'category_description' => 'Abilities for inspecting the Spectra Blocks available on the site, its popups, and its settings.',
        'group' => 'theme-block',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\SpectraBlocks\spectra_blocks_plugin_available(),
        'loader' => static function (): void {
            novamira_pro_load_spectra_blocks_abilities();
        },
        'floors' => [
            [
                'constant' => 'NOVAMIRA_PRO_SPECTRA_BLOCKS_MIN_VERSION',
                'value' => '0.0.8',
                'kind' => 'base',
            ],
            [
                'constant' => 'NOVAMIRA_PRO_SPECTRA_BLOCKS_PRO_MIN_VERSION',
                'value' => '0.0.8',
                'kind' => 'pro',
            ],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['spectra-blocks', 'spectra-blocks-pro'],
        'skills' => ['spectra-blocks-integration'],
    ],
    'oceanwp' => [
        'slug' => 'oceanwp',
        'category_label' => 'OceanWP',
        'category_description' => 'Abilities for OceanWP design and page settings, hooks, and Ocean Extra templates and modules.',
        'group' => 'theme-block',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\OceanWP\oceanwp_theme_available(),
        'loader' => static function (): void {
            novamira_pro_load_oceanwp_abilities();
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_OCEANWP_THEME_MIN_VERSION', 'value' => '4.1.0', 'kind' => 'theme'],
            ['constant' => 'NOVAMIRA_PRO_OCEAN_EXTRA_MIN_VERSION', 'value' => '2.0.0', 'kind' => 'base'],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['ocean-extra'],
        'themes' => ['oceanwp'],
        'skills' => ['oceanwp-build-page'],
    ],
    'woocommerce' => [
        'slug' => 'woocommerce',
        'category_label' => 'WooCommerce',
        'category_description' => 'Abilities for WooCommerce products and variations, catalog taxonomies, shipping classes, and store settings.',
        'group' => 'commerce-code',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Woocommerce\wc_min_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_woocommerce_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_WOOCOMMERCE_MIN_VERSION', 'value' => '9.0', 'kind' => 'base']],
        'editions' => ['free'],
        'plugins' => ['woocommerce'],
        'skills' => ['woocommerce-integration'],
    ],
    'code-snippets' => [
        'slug' => 'code-snippets',
        'category_label' => 'Code Snippets',
        'category_description' => 'Abilities for the Code Snippets / Code Snippets Pro plugin: list, read, create, edit, delete, enable, disable, and validate PHP / HTML / CSS / JS snippets and conditions.',
        'group' => 'commerce-code',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\CodeSnippets\cs_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_code_snippets_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_CODE_SNIPPETS_MIN_VERSION', 'value' => '3.6.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['code-snippets', 'code-snippets-pro'],
        'skills' => ['code-snippets-integration'],
    ],
    'dynamic-shortcodes' => [
        'slug' => 'dynamic-shortcodes',
        'category_label' => 'Dynamic Shortcodes',
        'category_description' => 'Abilities for the Dynamic Shortcodes plugin: validate, compose, evaluate, and manage Power Shortcodes.',
        'group' => 'commerce-code',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\DynamicShortcodes\dsh_min_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_dynamic_shortcodes_abilities();
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_DYNAMIC_SHORTCODES_MIN_VERSION', 'value' => '1.9.0', 'kind' => 'base'],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['dynamic-shortcodes'],
        'skills' => [
            'dynamic-shortcodes-integration',
            'dynamic-shortcodes-foundations',
            'dynamic-shortcodes-data',
            'dynamic-shortcodes-logic',
            'dynamic-shortcodes-loops',
            'dynamic-shortcodes-custom',
            'dynamic-shortcodes-custom-fields',
            'dynamic-shortcodes-post-data',
            'dynamic-shortcodes-request',
            'dynamic-shortcodes-wordpress',
        ],
    ],
    'cf7' => [
        'slug' => 'cf7',
        'category_label' => 'Contact Form 7',
        'category_description' => 'Abilities for Contact Form 7 forms, fields, and form-tag schemas.',
        'group' => 'form',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Cf7\cf7_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_cf7_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_CF7_MIN_VERSION', 'value' => '5.8.0', 'kind' => 'base']],
        'editions' => ['free'],
        'plugins' => ['contact-form-7'],
        'skills' => ['cf7-integration', 'form-model-schema', 'form-model-migration'],
    ],
    'wpforms' => [
        'slug' => 'wpforms',
        'category_label' => 'WPForms',
        'category_description' => 'Abilities for WPForms (Lite + Pro): form discovery, definition CRUD, field management, notifications, confirmations, and entries (Pro).',
        'group' => 'form',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\WPForms\wpforms_min_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_wpforms_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_WPFORMS_MIN_VERSION', 'value' => '1.8.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['wpforms-lite', 'wpforms'],
        'skills' => ['wpforms-integration', 'form-model-schema', 'form-model-migration'],
    ],
    'gravityforms' => [
        'slug' => 'gravityforms',
        'category_label' => 'Gravity Forms',
        'category_description' => 'Abilities for Gravity Forms: form discovery, definition CRUD, field management, notifications, confirmations, and entries.',
        'group' => 'form',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\GravityForms\gravityforms_min_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_gravityforms_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_GRAVITYFORMS_MIN_VERSION', 'value' => '2.7', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['gravityforms'],
        'skills' => ['gravityforms-integration', 'form-model-schema', 'form-model-migration'],
    ],
    'fluentforms' => [
        'slug' => 'fluentforms',
        'category_label' => 'Fluent Forms',
        'category_description' => 'Abilities for the Fluent Forms plugin (free + Pro): forms CRUD, field management, submissions, settings, notifications, confirmations, and Pro-only payment transactions, subscriptions, draft submissions, and quiz reports.',
        'group' => 'form',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\FluentForms\ff_min_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_fluentforms_abilities();
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_FLUENTFORMS_MIN_VERSION', 'value' => '6.0.0', 'kind' => 'base'],
            ['constant' => 'NOVAMIRA_PRO_FLUENTFORMS_PRO_MIN_VERSION', 'value' => '6.0.0', 'kind' => 'pro'],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['fluentform', 'fluentformpro'],
        'skills' => ['fluentforms-integration', 'form-model-schema', 'form-model-migration'],
    ],
    'formidable' => [
        'slug' => 'formidable',
        'category_label' => 'Formidable Forms',
        'category_description' => 'Abilities for Formidable Forms (free + Pro): form / field / entry CRUD, notifications, on-submit actions, styles, Pro applications, and Stripe Lite + Pro payment data.',
        'group' => 'form',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\Formidable\frm_plugin_present(),
        'loader' => static function (): void {
            novamira_pro_load_formidable_abilities();
        },
        'floors' => [
            ['constant' => 'NOVAMIRA_PRO_FORMIDABLE_MIN_VERSION', 'value' => '6.0.0', 'kind' => 'base'],
            ['constant' => 'NOVAMIRA_PRO_FORMIDABLE_PRO_MIN_VERSION', 'value' => '6.0.0', 'kind' => 'pro'],
        ],
        'editions' => ['free', 'pro'],
        'plugins' => ['formidable', 'formidable-pro'],
        'skills' => ['formidable-integration', 'form-model-schema', 'form-model-migration'],
    ],
    'ninja-forms' => [
        'slug' => 'ninja-forms',
        'category_label' => 'Ninja Forms',
        'category_description' => 'Abilities for Ninja Forms forms, fields, notifications, and submissions.',
        'group' => 'form',
        'gate' => static fn(): bool => \Novamira\Pro\Abilities\NinjaForms\ninja_forms_runtime_available(),
        'loader' => static function (): void {
            novamira_pro_load_ninja_forms_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_NINJAFORMS_MIN_VERSION', 'value' => '3.13.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['ninja-forms'],
        'skills' => ['ninja-forms-integration', 'form-model-schema', 'form-model-migration'],
    ],
    'weglot' => [
        'slug' => 'weglot',
        'category_label' => 'Weglot Translate',
        'category_description' => 'Abilities for Weglot languages, switcher settings, URL rules, translated slugs, cloud synchronization, and caches.',
        'group' => 'translation',
        'gate' => static fn(): bool => (
            defined('WEGLOT_VERSION')
            && version_compare((string) constant('WEGLOT_VERSION'), NOVAMIRA_PRO_WEGLOT_MIN_VERSION, operator: '>=')
        ),
        'loader' => static function (): void {
            novamira_pro_load_weglot_abilities();
        },
        'floors' => [['constant' => 'NOVAMIRA_PRO_WEGLOT_MIN_VERSION', 'value' => '5.0', 'kind' => 'base']],
        'editions' => ['free', 'pro'],
        'plugins' => ['weglot'],
        'skills' => ['weglot-integration'],
    ],
    // Core pseudo-specs. These register last (matching the legacy order where the
    // core categories registered after the optional plugin ones). The engine drives
    // ONLY their category registration + core-loader requires; their other lifecycle
    // wiring (the plugins_loaded:20 skill scan, the discover-abilities-instructions
    // filters, the memory CPT bootstrap + admin page) stays hand-wired in
    // novamira-pro.php.
    'wordpress' => [
        'slug' => 'wordpress',
        'category_label' => 'WordPress',
        'category_description' => 'General-purpose WordPress abilities (posts, pages, core APIs).',
        'group' => 'core',
        'core' => true,
        'gate' => static fn(): bool => true,
        'loader' => static function (): void {
            novamira_pro_load_wordpress_abilities();
        },
        'skills' => [],
    ],
    'skill' => [
        'slug' => 'skill',
        'category_label' => 'Skills',
        'category_description' => 'Bundled skill library — list and load specialized instructions, workflows, and domain knowledge on demand.',
        'group' => 'core',
        'core' => true,
        'gate' => static fn(): bool => !wp_has_ability_category('skill'),
        // Self-contained: the `plugins_loaded:20` require is license-gated and may not
        // have run by the time this gate is evaluated.
        'load_gate' => static function (): bool {
            require_once __DIR__ . '/abilities/skill/source-filter.php';
            return !\Novamira\Pro\Abilities\Skill\novamira_free_owns_skill_get();
        },
        'loader' => static function (): void {
            novamira_pro_load_skill_abilities();
        },
        'skills' => [],
    ],
    'memory' => [
        'slug' => 'memory',
        'category_label' => 'Memory',
        'category_description' => 'Persistent, database-backed memory for agents — list, read, write, and delete memories across conversations.',
        'group' => 'core',
        'core' => true,
        'gate' => static fn(): bool => (
            function_exists('Novamira\\Pro\\memory_is_enabled') && \Novamira\Pro\memory_is_enabled()
        ),
        'loader' => static function (): void {
            novamira_pro_load_memory_abilities();
        },
        'skills' => [],
    ],
];
