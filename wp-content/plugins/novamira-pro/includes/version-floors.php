<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro;

if (!defined('ABSPATH')) {
    exit();
}

// Minimum (and, for Mosaic, maximum) third-party plugin/theme versions each
// specialization's gate enforces. Loaded eagerly so the gate closures in
// includes/gates.php can read them.

// Minimum Elementor version required by the Elementor abilities. Below this
// version Elementor lacks the container element and the get_controls() shape
// the schema extractor relies on, so all Elementor abilities are skipped.
define(constant_name: 'NOVAMIRA_PRO_ELEMENTOR_MIN_VERSION', value: '3.6.0');

// Minimum ASE Pro version required by the ASE abilities. Below 8.7.0 the
// Custom Field Types module's storage and helper API are not guaranteed.
define(constant_name: 'NOVAMIRA_PRO_ASE_MIN_VERSION', value: '8.7.0');

// Minimum Pods version required by the Pods abilities. 3.0 introduced the
// `Pods\Whatsit` object model the abilities rely on; the 3.2.1.1 floor
// matches the minimum patched release we support.
define(constant_name: 'NOVAMIRA_PRO_PODS_MIN_VERSION', value: '3.2.1.1');

// Minimum ACF / ACF Pro version required by the ACF abilities. Note: ACF
// 6.8+ ships its own native WordPress Abilities API integration with
// per-item AI access opt-in — see the acf-integration skill for the
// coexistence story.
define(constant_name: 'NOVAMIRA_PRO_ACF_MIN_VERSION', value: '6.5.0');

// Minimum JetEngine version required by the JetEngine abilities. 3.7
// introduced the MCP_Tools Registry and internal classes the helpers rely
// on; the 3.8.6.2 floor matches the minimum patched release we support.
define(constant_name: 'NOVAMIRA_PRO_JETENGINE_MIN_VERSION', value: '3.8.6.2');

// Minimum Meta Box version required by the Meta Box abilities. 5.6 ships
// the stable rwmb_get_object_fields helper and the modern `meta-box` CPT
// used by MB Builder for UI-defined field groups; the 5.11.2 floor matches
// the minimum patched release we support.
define(constant_name: 'NOVAMIRA_PRO_METABOX_MIN_VERSION', value: '5.11.2');

// Minimum ACPT version required by the ACPT abilities. The 2.0.x line
// ships the stable Repository classes the integration depends on; the
// 2.0.52 floor matches the minimum patched release we support.
define(constant_name: 'NOVAMIRA_PRO_ACPT_MIN_VERSION', value: '2.0.52');

// Minimum WooCommerce version required by the WooCommerce abilities.
// 9.0 (June 2024) makes HPOS canonical for orders and Block-based
// Cart/Checkout default; below this version several Data Store APIs
// the abilities rely on are unstable.
define(constant_name: 'NOVAMIRA_PRO_WOOCOMMERCE_MIN_VERSION', value: '9.0');

// Minimum WPBakery Page Builder version required by the WPBakery abilities.
// 6.5.0 is the first release officially compatible with PHP 8.0, which is
// the floor Novamira itself declares; older WPBakery branches fatal on
// PHP 8 in several code paths (sprintf with array, null-on-non-nullable).
define(constant_name: 'NOVAMIRA_PRO_WPBAKERY_MIN_VERSION', value: '6.5.0');

// Minimum Ninja Forms version required by the integration. The whole
// surface (forms / fields / notifications / submissions plus the canonical
// reader) drives Ninja through its PHP model layer — `Ninja_Forms()`, the
// `wp_nf3_*` tables, the `nf_sub` CPT — with no dependency on Ninja's own
// `ninjaforms/*` abilities. 3.13.0 (October 2025) is the floor: it sits on
// the current, maintained Ninja line, boots on modern PHP (verified on
// 8.5) and passes the integration smoke suite, whereas Ninja 3.6 / 3.7
// fatal on PHP 8.x. Older installs report `min_satisfied: false` in
// `ninja-forms-check-setup`.
define(constant_name: 'NOVAMIRA_PRO_NINJAFORMS_MIN_VERSION', value: '3.13.0');

// Minimum GeneratePress theme version required by the GeneratePress
// abilities. 3.0 is the modern flexbox-default release that exposes the
// API surface (`generate_get_option`, `generate_get_defaults`,
// `generate_get_layout`) we depend on; older releases lack the helpers
// or call signatures.
define(constant_name: 'NOVAMIRA_PRO_GP_THEME_MIN_VERSION', value: '3.0.0');

// Minimum GP Premium plugin version required by the Elements / Site
// Library / Font Library abilities. 2.0 is the line that introduced the
// `generatepress_is_module_active()` helper as the canonical module gate
// and the modern Elements CPT meta layout.
define(constant_name: 'NOVAMIRA_PRO_GP_PREMIUM_MIN_VERSION', value: '2.0.0');

// Minimum Kadence theme version required by the Kadence abilities. The
// 1.x line ships the modern Options Component template-tag API
// (`kadence()->option()`, `kadence()->defaults()`, `kadence()->get_palette()`)
// the integration reads from; older 0.x releases use a different
// option-bag layout.
define(constant_name: 'NOVAMIRA_PRO_KADENCE_THEME_MIN_VERSION', value: '1.2.0');

// Minimum Spectra One theme version required by the Spectra One abilities.
// The theme's own WordPress-Abilities-API surface (`spectra-one/*` category)
// requires WP 6.9+ and is only registered from 1.2.x onward; the 1.2.2 floor
// matches the release the Novamira integration was built and verified
// against. Below it the `theme.json` v2 schema and `wp_global_styles`
// overlay flow the write abilities depend on behave inconsistently.
define(constant_name: 'NOVAMIRA_PRO_SPECTRA_ONE_THEME_MIN_VERSION', value: '1.2.2');

// Minimum Spectra plugin version (formerly Ultimate Addons for Gutenberg,
// UAGB) recognised by the Spectra One integration. The Spectra plugin is
// OPTIONAL — every non-plugin ability works with the theme alone. Only the
// Spectra-plugin-specific bridge (`list-spectra-blocks`) gates on this
// version; the 2.16.0 line is the current stable UAGB release and the one
// the theme's own compatibility layer (`inc/compatibility/spectra.php`)
// targets.
define(constant_name: 'NOVAMIRA_PRO_SPECTRA_ONE_PLUGIN_MIN_VERSION', value: '2.16.0');

// Minimum Spectra plugin version required by the standalone Spectra (UAGB)
// specialization. Distinct from the Spectra One theme's optional-plugin
// bridge floor above: the plugin specialization drives the whole UAGB
// surface (popup CPT, editor settings, Google Fonts, form submissions) and
// needs the newer 2.19 line where the popup builder REST shape, the
// `spectra_pro_*` Pro extension filters, and the `_uagb_blocks` activation
// registry are stable. Below this the write abilities behave inconsistently.
define(constant_name: 'NOVAMIRA_PRO_SPECTRA_MIN_VERSION', value: '2.19.0');

// Minimum Spectra Pro version required by the Pro layer of the Spectra
// specialization. Spectra Pro is a separate add-on plugin
// (`spectra-pro/spectra-pro.php`) that extends the free UAGB with premium
// blocks, advanced popup display conditions, and form integrations. Note
// the version scheme is distinct from the free plugin — Spectra Pro is on
// the 1.x line while the free "Spectra" (UAGB) plugin is on the 2.19.x
// line. 1.2.0 is the modern stable floor for the Pro extension surfaces
// (`spectra_pro_popup_display_filters`, `spectra_pro_render_block`, and
// the license handler) our Pro-layer abilities hook into. Free abilities
// never gate on this — only the Pro sub-loader does, keeping Pro
// abilities out of `discover-abilities` on free-only installs.
define(constant_name: 'NOVAMIRA_PRO_SPECTRA_PRO_MIN_VERSION', value: '1.2.0');

// Minimum Spectra Blocks version required by the Spectra Blocks specialization.
// Spectra Blocks is Brainstorm Force's ground-up rewrite of the block library
// (namespace `spectra/*`, runtime class `SpectraBlocks\BlockManager`) and is a
// DIFFERENT plugin from the legacy Spectra (UAGB) covered by
// NOVAMIRA_PRO_SPECTRA_MIN_VERSION above — the two specializations coexist and
// gate independently. 0.0.8 is the first public release the integration was
// built and verified against: it ships the 48-block `spectra/*` registry, the
// `spectra-blocks-popup` CPT with registered meta, the `spectra_blocks_{key}`
// settings options, and the `_spectra_blocks_blocks` activation map the
// abilities read. The plugin itself requires WP 6.6+ / PHP 8.1+ and refuses to
// initialize when the legacy UAGB spectra-v3 bootstrap (`SPECTRA_3_FILE`) is
// present — the gate mirrors that refusal so the surface never registers
// against a half-loaded runtime.
define(constant_name: 'NOVAMIRA_PRO_SPECTRA_BLOCKS_MIN_VERSION', value: '0.0.8');

// Minimum Spectra Blocks Pro version required by the Pro layer of the Spectra
// Blocks specialization. Spectra Blocks Pro is a separate add-on plugin
// (`spectra-blocks-pro/spectra-blocks-pro.php`) that registers 37
// `spectra-pro/*` blocks plus one `spectra/*` child block; registration is not
// license-gated, so an unlicensed Pro install still exposes the full Pro block
// registry. 0.0.8 is the first public release and requires Spectra Blocks
// free >= 0.0.8 to initialize at all. Sprint-1 abilities are free-layer only —
// this floor exists for check-setup reporting and future Pro sub-gating.
define(constant_name: 'NOVAMIRA_PRO_SPECTRA_BLOCKS_PRO_MIN_VERSION', value: '0.0.8');

// Minimum Code Snippets version required by the Code Snippets abilities.
// 3.6 is the first release with the stable Snippet object model + the
// `Code_Snippets\` namespaced helpers the integration calls. Both the
// free "Code Snippets" and "Code Snippets Pro" variants share the
// version line. 3.10 relocated the model class to
// `Code_Snippets\Model\Snippet` (no alias for the old name); the
// integration resolves the class at runtime (cs_snippet_class()) so the
// floor stays at 3.6.
define(constant_name: 'NOVAMIRA_PRO_CODE_SNIPPETS_MIN_VERSION', value: '3.6.0');

// Minimum Contact Form 7 version required by the CF7 abilities. 5.8 is
// the line we audited; it ships the stable `WPCF7_ContactForm` object
// model (find / get_instance / get_template / save / delete) the
// integration calls. CF7 has a single free distribution — no Pro
// variant.
define(constant_name: 'NOVAMIRA_PRO_CF7_MIN_VERSION', value: '5.8.0');

// Minimum Breakdance version required by the Breakdance abilities. 2.7
// is the line we audited; older releases lack the licensing API shape
// the check-setup ability relies on.
define(constant_name: 'NOVAMIRA_PRO_BREAKDANCE_MIN_VERSION', value: '2.7.0');

// Minimum Oxygen version required by the Oxygen abilities. Oxygen 6.0.0
// (October 2024) is the first release built on the unified Soflyy
// engine (`BREAKDANCE_MODE=oxygen`) — it shares the `Breakdance\Data\*`
// namespace and the `__BREAKDANCE_VERSION` constant with standalone
// Breakdance, but persists under mode-specific keys (`_oxygen_data` canvas,
// `_oxygen_template_settings`, `oxygen_*` CPTs, `oxygen_`-prefixed options).
// Below 6.0 sites run the shortcode-based Oxygen Classic
// (`_ct_builder_shortcodes` storage, `OxygenElement` class, `ct_*` hooks) —
// an entirely different codebase we explicitly do NOT support. The gate
// function distinguishes Oxygen from Breakdance-standalone via
// `defined('BREAKDANCE_MODE') && BREAKDANCE_MODE === 'oxygen'`.
define(constant_name: 'NOVAMIRA_PRO_OXYGEN_MIN_VERSION', value: '6.0.0');

// Minimum Mosaic Pro version required by the Mosaic abilities. Mosaic
// is very young — 1.0.3 is the first stable release that ships the 22
// `<prefix>mosaic_*` tables our integration reads + writes, so we floor
// at 1.0.3. Pre-1.0 builds used a different schema layout and would
// silently mis-route every ability.
define(constant_name: 'NOVAMIRA_PRO_MOSAIC_MIN_VERSION', value: '1.0.3');
// SemVer-major upper bound. Mosaic is young (first public beta Jan 2026,
// stable since April 2026) so we trust minor bumps but lock out the 2.x
// line until it's been audited — a major would be the natural release
// to rename tables, drop columns, or rework the data-blob shape.
define(constant_name: 'NOVAMIRA_PRO_MOSAIC_MAX_VERSION', value: '2.0.0');

// Minimum Divi version required by the Divi abilities. Divi 5 replaced the
// Divi 4 shortcode builder with a WordPress-block module model (divi/*) and
// the ET\Builder\Packages\ModuleLibrary\ModuleRegistration API the abilities
// build on. Divi 4 shortcode content is not supported.
define(constant_name: 'NOVAMIRA_PRO_DIVI_MIN_VERSION', value: '5.0');

// Minimum Etch version required by the Etch abilities. Etch ships no version
// constant, so the gate reads the version back from the plugin header; this
// floor is the release the Etch specialization was built and verified against.
define(constant_name: 'NOVAMIRA_PRO_ETCH_MIN_VERSION', value: '1.4.19');

// Etch 1.5.0 added the per-post custom-field VALUES REST API
// (etch-api/cms/post/<id>/field-values). The value abilities are gated on this
// feature FLOOR — a floor, not an upper ceiling: any newer Etch keeps working.
// Below it the value abilities are simply not registered, so the rest of the
// Etch integration is unaffected on older installs (down to the MIN above).
define(constant_name: 'NOVAMIRA_PRO_ETCH_CF_VALUES_MIN_VERSION', value: '1.5.0');

// Minimum Yoast SEO version required by the Yoast abilities. The legacy
// WPSEO_Meta / WPSEO_Options / WPSEO_Taxonomy_Meta APIs the abilities use are
// stable well below this, but 21.0 is a clean modern floor with the current
// schema and social-preview fields. Premium redirects are gated separately on
// WPSEO_PREMIUM_VERSION at runtime.
define(constant_name: 'NOVAMIRA_PRO_YOAST_MIN_VERSION', value: '21.0');

// Minimum Rank Math version required by the Rank Math abilities. The
// rank_math_* postmeta/termmeta keys and the Helper::get_settings() options API
// the abilities use are stable across the 1.0.x line; 1.0.200 is a recent floor.
define(constant_name: 'NOVAMIRA_PRO_RANK_MATH_MIN_VERSION', value: '1.0.200');

// All in One SEO (free + Pro). AIOSEO 4.x stores per-post/term SEO in custom
// tables accessed via its Post/Term models. Term SEO and redirects are Pro-only.
define(constant_name: 'NOVAMIRA_PRO_AIOSEO_MIN_VERSION', value: '4.0');

// Minimum Beaver Builder version for the Beaver Builder abilities. The core
// page/template APIs this spec reads (get_layout_data/update_layout_data,
// get_settings_form, get_user_templates/save_user_template/apply_*_template,
// get_module_defaults) exist since Beaver 1.x/2.0, and the Global Styles line
// (FLBuilderGlobalStyles, @since 2.8.0) the global-color/-typography abilities
// use means the spec is technically capable a notch lower. 2.9 ("Coyote",
// released 2025-03) is set as the supported modern floor: one major below the
// current 2.10 line, recent and tested. The global-style abilities additionally
// self-gate on class_exists('FLBuilderGlobalStyles'), and node/global templates
// on node_templates_enabled(), so the floor never over-claims a capability; it
// aligns the whole specialization to one modern Beaver line rather than encoding
// a hard core-API requirement.
define(constant_name: 'NOVAMIRA_PRO_BEAVER_BUILDER_MIN_VERSION', value: '2.9.0');

// Minimum Bricksforge version for the Bricksforge abilities. The surfaces the
// spec wraps — brf_activated_tools (flat int array), brf_activated_elements
// (array of {id} objects), brf_popups config, and the
// Email Designer store (brf_email_designer_data / _themes) — were verified
// against BOTH the 3.1.8 and 3.1.8.6 source (identical tool/element ID maps —
// Elements.php is byte-identical — option save/read shapes, and Email Designer
// template shape), so 3.1.8 is a genuinely verified floor, not assumed.
// BRICKSFORGE_VERSION is only defined when the Bricks theme is active
// (the plugin bails out before defining anything otherwise), so the gate
// implies Bricks too. No max ceiling (project policy).
define(constant_name: 'NOVAMIRA_PRO_BRICKSFORGE_MIN_VERSION', value: '3.1.8');

// SeoPress (free + Pro). SeoPress stores per-post/term SEO in postmeta/termmeta
// (_seopress_* keys) and global config in options; redirects are a Pro CPT
// (seopress_404) and structured data is Pro. SEOPRESS_VERSION is defined by the
// free plugin (a hard dependency of Pro), so this gate covers both editions;
// Pro-only surfaces gate again at runtime on SEOPRESS_PRO_VERSION. The
// _seopress_* keys and seopress_*_option_name groups are stable across the 9.x
// line; 8.0 is a conservative modern floor.
define(constant_name: 'NOVAMIRA_PRO_SEOPRESS_MIN_VERSION', value: '8.0');

// WPForms (Lite + Pro). 1.8 is the line that stabilised the
// wpforms()->obj('form'|'entry') accessor + the field-type registry shape this
// integration depends on. WPFORMS_VERSION is defined by both editions, so this
// gate covers both; Pro-only surfaces (entries CRUD, premium field types) gate
// again at runtime via wpforms_pro_active().
define(constant_name: 'NOVAMIRA_PRO_WPFORMS_MIN_VERSION', value: '1.8.0');

// Gravity Forms (single-SKU paid plugin, no Lite/Pro split). 2.7 is the line
// where GFAPI::current_user_can_any() and the modern field-type registry
// stabilised — every ability in the gravityforms category depends on those
// surfaces. GFForms::$version is read on the loader class as the version
// source of truth.
define(constant_name: 'NOVAMIRA_PRO_GRAVITYFORMS_MIN_VERSION', value: '2.7');

// Fluent Forms (free + Pro). The free plugin defines `FLUENTFORM` and
// `FLUENTFORM_VERSION`; Pro hard-depends on free and defines `FLUENTFORMPRO`
// + `FLUENTFORMPRO_VERSION`. We floor at 6.0 because both editions ship the
// stable `\FluentForm\App\Models\*` ORM facade and the per-form Acl helper
// the integration relies on from that line onward.
define(constant_name: 'NOVAMIRA_PRO_FLUENTFORMS_MIN_VERSION', value: '6.0.0');
define(constant_name: 'NOVAMIRA_PRO_FLUENTFORMS_PRO_MIN_VERSION', value: '6.0.0');

// Formidable Forms. Free plugin defines no top-level FRM_VERSION constant —
// `\FrmAppHelper::plugin_version()` is the canonical accessor. Pro hard-depends
// on free and exposes `\FrmProDb::$plug_version`. License status is informational
// only: Pro abilities run unlicensed (same semantics as the SeoPress / Yoast
// integrations). The 6.0 floor is the first release where the modern
// FrmForm / FrmField / FrmEntry surface is stable and the on-submit action
// migration (`FrmOnSubmitHelper::maybe_migrate_submit_settings_to_action`) is
// canonical — below it the field-options shape and the styles post-type model
// behave differently.
define(constant_name: 'NOVAMIRA_PRO_FORMIDABLE_MIN_VERSION', value: '6.0.0');
define(constant_name: 'NOVAMIRA_PRO_FORMIDABLE_PRO_MIN_VERSION', value: '6.0.0');

// Astra theme. 4.13.5 is the baseline release the specialization was built
// and verified against. The theme itself requires the addon >= 4.12.0
// (ASTRA_EXT_MIN_VER) when Astra Pro is installed alongside.
define(constant_name: 'NOVAMIRA_PRO_ASTRA_THEME_MIN_VERSION', value: '4.13.5');

// Astra Pro Addon. 4.13.5 is the baseline; the addon requires the theme >=
// 4.13.2 (ASTRA_THEME_MIN_VER). The two minima embrace each other.
define(constant_name: 'NOVAMIRA_PRO_ASTRA_ADDON_MIN_VERSION', value: '4.13.5');
// Minimum OceanWP theme version required by the OceanWP abilities. The 4.1.x
// line finalised the new Theme Panel API (`oceanwp_theme_panel_sections`
// filter, the `extra-settings` pane) and the modern
// `ocean_customize_options_data` filter pipeline the integration walks. Below
// 4.1 the Customizer option tree uses a different walker shape and the
// Theme Panel registration filter is absent. The 4.1.6 release (May 2026)
// is the current shipping line; we floor at 4.1.0 to keep LTS 4.1.0–4.1.5
// installs supported.
define(constant_name: 'NOVAMIRA_PRO_OCEANWP_THEME_MIN_VERSION', value: '4.1.0');

// Minimum Ocean Extra (free companion plugin) version required by the
// Ocean-Extra-gated abilities (Library, Modules, Demo Import). The theme
// home pane (`themepanel/views/panes/home.php`) declares `$required_oe_version
// = '2.0.0'` as the line that introduced the new Theme Panel API + the
// metabox-migration option (`ocean_metabox_migration_status`) that gates
// per-page overrides. Ocean Extra is OPTIONAL — the OceanWP integration
// gracefully degrades when the plugin is missing (S1–S3 abilities work
// theme-only; S4 abilities self-skip with `oceanwp_requires_ocean_extra`).
define(constant_name: 'NOVAMIRA_PRO_OCEAN_EXTRA_MIN_VERSION', value: '2.0.0');
// Minimum Dynamic Shortcodes version for the power/shortcode abilities. They call
// Manager::lint() / get_power_shortcodes() / save_power_shortcodes(), which ship in
// DSH 1.9.0 (the abilities-api work). DSH 1.8.x does not have them, so the gate keeps
// the power/shortcode abilities off any DSH below 1.9.0 and they never call a missing
// method. Below the floor nothing registers, check-setup included (the gate in
// includes/gates.php blocks the whole loader).
define(constant_name: 'NOVAMIRA_PRO_DYNAMIC_SHORTCODES_MIN_VERSION', value: '1.9.0');

// Minimum Dynamic Content for Elementor version required by the HTML Templates
// abilities. Version 3.4.0 is the minimum at which the HTML Templates post type,
// its single array-valued meta and that meta's seven sub-keys have the shape these
// abilities address. Below the floor nothing registers, check-setup included, so
// an ability never addresses a shape the installed plugin may not have.
define(constant_name: 'NOVAMIRA_PRO_DYNAMIC_CONTENT_FOR_ELEMENTOR_MIN_VERSION', value: '3.4.0');

// Minimum Avada theme version required by the Avada abilities. 7.15 is the
// modern line that stabilised `Fusion_Settings` (`fusion_options` option row)
// and the `AWB_Global_Colors` / `AWB_Global_Typography` token APIs the theme-
// side abilities read + write. `AVADA_VERSION` is defined in the theme's
// bootstrap so the gate lights up as soon as Avada is the active theme.
define(constant_name: 'NOVAMIRA_PRO_AVADA_MIN_VERSION', value: '7.15.0');

// Minimum Fusion Builder plugin version required when the (currently blocked)
// element-tree CRUD / Template Builder / Fusion Forms abilities are wired
// into a Pro sub-loader. Declared here so gate + manifest floors reference
// one constant when the plugin-scoped surface is opened. Unused today —
// Fusion Builder is a bundled companion plugin that is not installed in
// this environment, so no ability calls into it. 3.13 is a conservative
// modern floor matching the Fusion Builder line paired with Avada 7.15.
define(constant_name: 'NOVAMIRA_PRO_FUSION_BUILDER_MIN_VERSION', value: '3.13.0');

// Minimum Fusion Core plugin version required by the Fusion Slider abilities
// (slide CPT + slide-page taxonomy read/write). 5.15.5 is the release the
// Fusion Slider specialization was built and verified against; the `_fusion`
// term_meta/post_meta storage shape (single serialised array under one key)
// is stable across the 5.x line, but we floor at the tested release so we
// never claim compatibility with a shape we haven't observed. The
// `FUSION_CORE_VERSION` constant is defined at the top of Fusion Core's
// bootstrap so the gate lights up as soon as the plugin loads.
define(constant_name: 'NOVAMIRA_PRO_FUSION_CORE_MIN_VERSION', value: '5.15.5');

// Weglot Translate. Weglot is a cloud client: the WP plugin keeps a thin
// settings cache (`weglot-translate-v3` option, `weglot-translate-api_key*`)
// while the source of truth lives at api.weglot.com. The v3 options surface we
// read (`weglot-translate-v3`, get_options_bdd_v3) exists @since Weglot 3.0.0,
// but we deliberately floor at 5.0: it is the oldest line we actually
// test/support, and several global helpers the abilities call (weglot_get_options,
// weglot_get_settings, weglot_get_status, …) have never been exercised on
// 3.x–4.x, so we do not claim a compatibility we cannot stand behind. Pro-plan
// features (glossary, etc.) gate again at runtime on the plan reported by
// check-setup via the user-info endpoint.
define(constant_name: 'NOVAMIRA_PRO_WEGLOT_MIN_VERSION', value: '5.0');

// Minimum Voxel theme version required by the Voxel abilities. Unlike most
// themes, Voxel exposes NO version constant — the version lives only in the
// `style.css` header and is read at runtime via
// `wp_get_theme( get_template() )->get('Version')`. Because we can only
// exercise the API against the installed release, the floor is pinned to the
// exact version the specialization was built and verified against (1.7.8.3):
// we never claim compatibility with a shape we have not observed. Note the
// gate (includes/gates.php) is a PRESENCE check, NOT a version check — so
// `novamira/voxel-check-setup` stays registered on an older Voxel and can
// report `min_satisfied:false`, while every other voxel-* callback refuses
// early with `voxel_version_unsupported`. This is deliberate: the below-min
// state must be observable, not a silently empty tool list.
define(constant_name: 'NOVAMIRA_PRO_VOXEL_THEME_MIN_VERSION', value: '1.7.8.3');

// Minimum Blocksy theme version required by the Blocksy abilities. Blocksy
// defines NO version constant — the version is read at runtime from the parent
// theme's `style.css` header (`wp_get_theme(get_template())`), so child themes
// never mask it. 2.1.47 is the release that introduced `Blocksy\CompanionBridge`
// (theme ↔ companion dispatch over `blocksy:companion:*` filters) — flooring
// there gives every future sprint a single clean code path into companion
// features instead of the legacy `blc_*` shims, whose 2.1.x replacements
// silently return null. The specialization was built and verified against
// 2.1.52. Like Voxel, the manifest gate is a PRESENCE check so check-setup can
// report `min_satisfied: false` on older installs.
define(constant_name: 'NOVAMIRA_PRO_BLOCKSY_THEME_MIN_VERSION', value: '2.1.47');

// Minimum Blocksy Companion (free or Pro) version. Theme and companion release
// in lockstep with identical version numbers, so the floor mirrors the theme
// floor. The companion itself refuses to boot features when the theme is below
// its own declared floor (`Blocksy Minimum Companion Version` header) —
// check-setup surfaces that divergence as a warning. Sprint-1 read abilities
// run without the companion; this floor feeds check-setup reporting and gates
// future companion-backed surfaces (extensions, Content Blocks).
define(constant_name: 'NOVAMIRA_PRO_BLOCKSY_COMPANION_MIN_VERSION', value: '2.1.47');
