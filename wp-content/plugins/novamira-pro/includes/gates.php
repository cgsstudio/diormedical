<?php

// includes/gates.php — plugin-scope gate helpers relocated out of each spec's runtime.php.
// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro {
    if (!defined('ABSPATH')) {
        exit();
    }

    // gate helpers pasted here per migration task (each keeps its original sub-namespace block).

    /**
     * Whether Etch is active AND meets the minimum supported version.
     *
     * Etch ships no version constant, so this reads the version back from the
     * plugin header via \Etch\Plugin::get_plugin_version() (which self-includes
     * wp-admin/includes/plugin.php when needed). The class_exists guard keeps the
     * static call safe, so the category registrar and the ability loader can gate
     * on this without first loading the Etch helpers file.
     */
    function novamira_pro_etch_available(): bool
    {
        return (
            defined('ETCH_PLUGIN_FILE')
            && class_exists('Etch\\Plugin')
            && version_compare(\Etch\Plugin::get_plugin_version(), NOVAMIRA_PRO_ETCH_MIN_VERSION, operator: '>=')
        );
    }

    /**
     * Resolve the best available Divi builder version.
     *
     * ET_BUILDER_VERSION can be boolean false when a child theme is active and
     * the renamed parent directory does not match the parent's Name header:
     * et_get_theme_version() passes that header to wp_get_theme(), whose failed
     * directory lookup produces the false value. Divi 5's product and core
     * constants provide reliable fallbacks in that case.
     */
    function novamira_pro_divi_builder_version(): string
    {
        foreach (['ET_BUILDER_VERSION', 'ET_BUILDER_PRODUCT_VERSION', 'ET_CORE_VERSION'] as $constant_name) {
            if (!defined($constant_name)) {
                continue;
            }

            if (is_string(constant($constant_name))) {
                $version = (string) constant($constant_name);
                if ($version !== '') {
                    return $version;
                }
            }
            if (is_int(constant($constant_name)) || is_float(constant($constant_name))) {
                return (string) constant($constant_name);
            }
        }

        return '';
    }

    /**
     * Whether the Divi 5 (D5) block-based builder and its module API are available.
     * Divi 4 (shortcode) content is out of scope: the abilities operate on the
     * divi/* WordPress-block module model. Kept at global plugin scope so both the
     * category registrar and the ability loader can gate on it without loading
     * the Divi helpers file.
     */
    function novamira_pro_divi_available(): bool
    {
        $version = novamira_pro_divi_builder_version();

        return (
            $version !== ''
            && version_compare($version, NOVAMIRA_PRO_DIVI_MIN_VERSION, operator: '>=')
            && function_exists('et_builder_d5_enabled')
            && et_builder_d5_enabled()
            && class_exists('ET\Builder\Packages\ModuleLibrary\ModuleRegistration')
        );
    }
}

namespace Novamira\Pro\Abilities\Breakdance {
    /**
     * Breakdance is active at or above the minimum supported release. The
     * version check uses the `__BREAKDANCE_VERSION` constant defined in
     * `breakdance/plugin.php`; the function sentinel ensures the
     * `data/api/options.php` helpers are loaded (any ability that reads
     * settings goes through those).
     *
     * Mutually exclusive with the Oxygen specialization: Soflyy ships
     * Oxygen 6.x as the same codebase in `BREAKDANCE_MODE=oxygen`, so
     * both integrations would otherwise register against the same runtime.
     * When the plugin identifies itself as Oxygen, this gate returns
     * false and `oxygen_plugin_available()` takes over — the two never
     * co-register.
     */
    function bd_plugin_available(): bool
    {
        if (defined('BREAKDANCE_MODE') && constant('BREAKDANCE_MODE') === 'oxygen') {
            return false;
        }
        return (
            defined('__BREAKDANCE_VERSION')
            && function_exists('Breakdance\\Data\\get_global_option')
            && version_compare(
                (string) constant('__BREAKDANCE_VERSION'),
                NOVAMIRA_PRO_BREAKDANCE_MIN_VERSION,
                operator: '>=',
            )
        );
    }
}

namespace Novamira\Pro\Abilities\Oxygen {
    /**
     * Oxygen 6.x is active at or above the minimum supported release. Oxygen 6
     * is the Soflyy unified codebase running in `BREAKDANCE_MODE=oxygen`: it
     * shares the `Breakdance\Data\*` namespace and the `__BREAKDANCE_VERSION`
     * constant with standalone Breakdance, but the PERSISTED identifiers are
     * mode-specific — in Oxygen mode the canvas postmeta is `_oxygen_data`,
     * template settings are `_oxygen_template_settings`, template CPTs are
     * `oxygen_*`, and global options are prefixed `oxygen_`. The abilities
     * resolve these through the engine's own mode-aware Data API. The
     * `BREAKDANCE_MODE` check distinguishes the two brands and
     * makes this gate mutually exclusive with `bd_plugin_available()` — so an
     * Oxygen site never triggers the Breakdance loader and vice versa. Oxygen
     * Classic 4.x/5.x (shortcode-based, `_ct_builder_shortcodes` storage) is
     * explicitly out of scope: the version_compare against 6.0.0 blocks it.
     */
    function oxygen_plugin_available(): bool
    {
        if (!defined('BREAKDANCE_MODE') || constant('BREAKDANCE_MODE') !== 'oxygen') {
            return false;
        }
        return (
            defined('__BREAKDANCE_VERSION')
            && function_exists('Breakdance\\Data\\get_global_option')
            && version_compare(
                (string) constant('__BREAKDANCE_VERSION'),
                NOVAMIRA_PRO_OXYGEN_MIN_VERSION,
                operator: '>=',
            )
        );
    }
}

namespace Novamira\Pro\Abilities\BeaverBuilder {
    /**
     * Whether Beaver Builder's plugin runtime has loaded.
     */
    function bb_plugin_active(): bool
    {
        return defined('FL_BUILDER_VERSION') && class_exists('FLBuilder') && class_exists('FLBuilderModel');
    }

    /**
     * Beaver Builder plugin version, or empty string when inactive.
     */
    function bb_version(): string
    {
        return defined('FL_BUILDER_VERSION') ? (string) constant('FL_BUILDER_VERSION') : '';
    }

    /**
     * Whether Beaver Builder is installed, active, and at the supported runtime floor.
     */
    function bb_runtime_available(): bool
    {
        return bb_plugin_active()
        && version_compare(bb_version(), NOVAMIRA_PRO_BEAVER_BUILDER_MIN_VERSION, operator: '>=');
    }
}

namespace Novamira\Pro\Abilities\Bricksforge {
    /**
     * Whether the Bricksforge plugin runtime has loaded. Bricksforge bails out
     * before defining any constant unless the Bricks theme (or a Bricks child
     * theme) is active, so this check implies Bricks is the active theme.
     */
    function brf_plugin_active(): bool
    {
        return defined('BRICKSFORGE_VERSION');
    }

    /**
     * Bricksforge plugin version, or empty string when inactive.
     */
    function brf_version(): string
    {
        return defined('BRICKSFORGE_VERSION') ? (string) constant('BRICKSFORGE_VERSION') : '';
    }

    /**
     * Whether Bricksforge is installed, active, and at the supported runtime floor.
     */
    function brf_runtime_available(): bool
    {
        return brf_plugin_active()
        && version_compare(brf_version(), NOVAMIRA_PRO_BRICKSFORGE_MIN_VERSION, operator: '>=');
    }
}

namespace Novamira\Pro\Abilities\WPBakery {
    /**
     * Whether WPBakery Page Builder is installed, active, and at or above the
     * minimum supported version.
     *
     * Requires WPBMap (the registry class) and a version constant that meets
     * NOVAMIRA_PRO_WPBAKERY_MIN_VERSION. WPBMap::$is_init is not checked here
     * because it is set during the `init` action — after `wp_abilities_api_init`
     * fires.
     */
    function wpb_runtime_available(): bool
    {
        return (
            class_exists('WPBMap')
            && defined('WPB_VC_VERSION')
            && version_compare((string) constant('WPB_VC_VERSION'), NOVAMIRA_PRO_WPBAKERY_MIN_VERSION, operator: '>=')
        );
    }
}

namespace Novamira\Pro\Abilities\Woocommerce {
    /**
     * WooCommerce is present and at or above the minimum supported version.
     *
     * The cache is keyed by blog_id to avoid cross-blog leaks in multisite
     * environments where different blogs may have different WC versions active.
     */
    function wc_min_runtime_available(): bool
    {
        /** @var array<int, bool> $cache */
        static $cache = [];
        $blog_id = get_current_blog_id();
        if (isset($cache[$blog_id])) {
            return $cache[$blog_id];
        }
        $result =
            class_exists('WooCommerce')
            && defined('WC_VERSION')
            && version_compare((string) constant('WC_VERSION'), NOVAMIRA_PRO_WOOCOMMERCE_MIN_VERSION, operator: '>=');
        $cache[$blog_id] = $result;
        return $result;
    }
}

namespace Novamira\Pro\Abilities\Formidable {
    /**
     * Whether Formidable Forms is loaded at ALL (any version). Used to gate
     * registration of `check-setup` + the category itself, so an agent on an
     * install with an under-min Formidable still sees the discovery surface
     * and can report "upgrade Formidable" instead of silently missing the
     * integration. Every other ability registration uses `frm_runtime_available()`,
     * which additionally enforces the minimum version.
     */
    function frm_plugin_present(): bool
    {
        if (!class_exists('\FrmAppHelper') || !method_exists('\FrmAppHelper', 'plugin_version')) {
            return false;
        }
        // Multisite defensive check (audit round 1, probe #8): Formidable's
        // classes are process-global. Once Site A loads them, a subsequent
        // `switch_to_blog($B)` keeps `class_exists('\FrmAppHelper')` true even
        // if Site B does NOT have Formidable activated. Cross-blog detection
        // therefore requires an explicit per-blog plugin-active check.
        if (function_exists('is_multisite') && is_multisite()) {
            if (!function_exists('is_plugin_active')) {
                require_once ABSPATH . 'wp-admin/includes/plugin.php';
            }
            if (!is_plugin_active('formidable/formidable.php')) {
                return false;
            }
        }
        return true;
    }
}

namespace Novamira\Pro\Abilities\Mosaic {
    /**
     * Verify the core `<prefix>mosaic_*` tables exist. Mosaic creates the
     * full set of ~21 tables lazily on first admin pageview through its
     * `MosaicSchemaManager`; a freshly installed-but-never-loaded site (or
     * a partial-restore from backup) can leave the plugin "active" with
     * constants defined but tables missing. We probe the tables whose shape
     * every Mosaic release in the supported band shares — if any one is
     * missing the schema bootstrap hasn't completed.
     *
     * The class tables are deliberately NOT part of this gate: Mosaic 1.0.8
     * renamed them (`utility_classes` → `universal_classes`, …), and gating on
     * either name would unregister the whole category on the other storage.
     * Class-dependent abilities check the class model themselves
     * (`mo_require_class_model()` in abilities/mosaic/class-model.php).
     */
    function mo_tables_present(): bool
    {
        global $wpdb;
        /** @var \wpdb $wpdb */
        $required = ['themes', 'templates', 'components', 'collections', 'collection_variables', 'nodes'];
        foreach ($required as $entity) {
            $name = $wpdb->prefix . 'mosaic_' . $entity;
            /** @var mixed $found */
            // @mago-expect analysis:possibly-invalid-argument
            $found = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $name));
            if (!is_string($found) || $found !== $name) {
                return false;
            }
        }
        return true;
    }

    /**
     * Mosaic is active at or above the minimum supported release AND the
     * custom schema is installed. The version check uses the
     * `MOSAIC_VERSION` constant; the class sentinel ensures the autoloader
     * has registered the Mosaic namespace; the table check verifies the
     * schema bootstrap has created the core `<prefix>mosaic_*` tables
     * (Mosaic creates them lazily on first admin pageview, so a freshly
     * installed-but-never-loaded site would fail this check).
     */
    function mo_runtime_available(): bool
    {
        if (!defined('MOSAIC_VERSION')) {
            return false;
        }
        $version = (string) constant('MOSAIC_VERSION');
        if (!version_compare($version, NOVAMIRA_PRO_MOSAIC_MIN_VERSION, operator: '>=')) {
            return false;
        }
        if (!version_compare($version, NOVAMIRA_PRO_MOSAIC_MAX_VERSION, operator: '<')) {
            return false;
        }
        if (!class_exists('Mosaic\\Database\\MosaicDB')) {
            return false;
        }
        // Mosaic defines MOSAIC_SITE_RUNNING (MosaicRunLayer/Runner.php) only
        // when this site's data version matches the running code. Without it —
        // e.g. a multisite subsite whose data upgrade has not run yet — the
        // code and the tables disagree, so nothing may touch them.
        if (!defined('MOSAIC_SITE_RUNNING')) {
            return false;
        }
        return mo_tables_present();
    }
}

namespace Novamira\Pro\Abilities\Pods {
    /**
     * Base Pods surface required by every ability in this category.
     */
    function pods_min_runtime_available(): bool
    {
        return (
            defined('PODS_VERSION')
            && version_compare(pods_version(), version2: NOVAMIRA_PRO_PODS_MIN_VERSION, operator: '>=')
            && function_exists('pods_api')
        );
    }

    /**
     * Reads the live Pods version constant in a static-analyzer-friendly way.
     */
    function pods_version(): string
    {
        return defined('PODS_VERSION') ? (string) constant('PODS_VERSION') : '0.0.0';
    }
}

namespace Novamira\Pro\Abilities\Metabox {
    /**
     * Meta Box core is present and at or above the minimum supported version.
     */
    function mb_min_runtime_available(): bool
    {
        /** @var bool|null $cache */
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        return (
            defined('RWMB_VER')
            && class_exists('RW_Meta_Box')
            && version_compare((string) constant('RWMB_VER'), NOVAMIRA_PRO_METABOX_MIN_VERSION, operator: '>=')
        );
    }
}

namespace Novamira\Pro\Abilities\Acpt {
    /**
     * ACPT core is present and at or above the minimum supported version.
     */
    function acpt_min_runtime_available(): bool
    {
        /** @var bool|null $cache */
        static $cache = null;
        if ($cache !== null) {
            return $cache;
        }
        return (
            defined('ACPT_PLUGIN_VERSION')
            && class_exists('ACPT\\Includes\\ACPT_Plugin')
            && version_compare((string) constant('ACPT_PLUGIN_VERSION'), NOVAMIRA_PRO_ACPT_MIN_VERSION, operator: '>=')
        );
    }
}

namespace Novamira\Pro\Abilities\GeneratePress {
    /**
     * The free GeneratePress theme is active and at or above the minimum
     * supported release. Combines the constant + a sentinel public function
     * (`generate_get_option`) so older releases that lack the API shape we
     * call into are excluded — defined() alone isn't enough.
     */
    function gp_theme_available(): bool
    {
        return (
            defined('GENERATE_VERSION')
            && function_exists('generate_get_option')
            && version_compare((string) constant('GENERATE_VERSION'), NOVAMIRA_PRO_GP_THEME_MIN_VERSION, operator: '>=')
        );
    }
}

namespace Novamira\Pro\Abilities\GenerateBlocks {
    /**
     * GenerateBlocks free version string, or '' when not installed / inactive.
     */
    function gb_version(): string
    {
        return defined('GENERATEBLOCKS_VERSION') ? (string) constant('GENERATEBLOCKS_VERSION') : '';
    }

    /**
     * Whether the free GenerateBlocks plugin is active at any version.
     */
    function gb_available(): bool
    {
        return gb_version() !== '';
    }
}

namespace Novamira\Pro\Abilities\Kadence {
    /**
     * Whether the Kadence theme is installed, active, and at or above the
     * minimum supported version. The minimum is the floor where the
     * Options Component template-tag API (`kadence()->option()`,
     * `kadence()->defaults()`, `kadence()->get_palette()`) is reliable.
     */
    function kadence_theme_available(): bool
    {
        return (
            defined('KADENCE_VERSION')
            && class_exists('Kadence\\Theme')
            && function_exists('Kadence\\kadence')
            && version_compare(
                (string) constant('KADENCE_VERSION'),
                NOVAMIRA_PRO_KADENCE_THEME_MIN_VERSION,
                operator: '>=',
            )
        );
    }
}

namespace Novamira\Pro\Abilities\KadenceBlocks {
    /**
     * Kadence Blocks (free) version string, or '' when not installed / inactive.
     */
    function kb_version(): string
    {
        return defined('KADENCE_BLOCKS_VERSION') ? (string) constant('KADENCE_BLOCKS_VERSION') : '';
    }

    /**
     * Whether the free Kadence Blocks plugin is active.
     */
    function kb_available(): bool
    {
        return kb_version() !== '';
    }
}

namespace Novamira\Pro\Abilities\SpectraOne {
    /**
     * Whether the Spectra One theme is installed, active, and at or above
     * the minimum supported version. Combines the namespaced `Swt\SWT_VER`
     * constant with a sentinel public helper (`Swt\get_theme_json`) so older
     * 1.0/1.1 releases that lack the exposed helper surface are excluded —
     * `defined()` alone would leak stale constants after a `switch_theme()`
     * inside the same request. The per-blog stylesheet check makes sure a
     * multisite request that entered a sub-site via `switch_to_blog()` —
     * where Spectra One is loaded process-wide but is NOT the active theme
     * — is refused at the gate rather than allowed to write against a
     * foreign theme's data surfaces (audit round 6, Sprint 6 Multisite
     * probe). The theme declares its version constant inside its own `Swt`
     * namespace (`functions.php:12` → `namespace Swt;`, `:18` → `const
     * SWT_VER = ...`), so a global-scope `defined('SWT_VER')` check always
     * returns false against the shipping theme and must be namespaced.
     */
    function spectra_one_theme_available(): bool
    {
        if (!defined('Swt\\SWT_VER')) {
            return false;
        }
        if (!function_exists('Swt\\get_theme_json')) {
            return false;
        }
        if (!version_compare(
            (string) constant('Swt\\SWT_VER'),
            NOVAMIRA_PRO_SPECTRA_ONE_THEME_MIN_VERSION,
            operator: '>=',
        )) {
            return false;
        }
        $stylesheet = function_exists('get_stylesheet') ? get_stylesheet() : '';
        $template = function_exists('get_template') ? get_template() : '';
        return $stylesheet === 'spectra-one' || $template === 'spectra-one';
    }

    /**
     * Whether the Spectra plugin (UAGB) is installed, active, and at or
     * above the minimum supported version. The plugin is OPTIONAL — most
     * `spectra-one/*` abilities work without it. Only the Spectra-plugin
     * bridge (`novamira/spectra-one-list-spectra-blocks`) is gated on this.
     */
    function spectra_plugin_available(): bool
    {
        return (
            defined('UAGB_VER')
            && version_compare(
                (string) constant('UAGB_VER'),
                NOVAMIRA_PRO_SPECTRA_ONE_PLUGIN_MIN_VERSION,
                operator: '>=',
            )
        );
    }
}

namespace Novamira\Pro\Abilities\Spectra {
    /**
     * Spectra (UAGB / Ultimate Addons for Gutenberg) plugin version string,
     * or '' when the plugin is inactive.
     */
    function spectra_plugin_version(): string
    {
        return defined('UAGB_VER') ? (string) constant('UAGB_VER') : '';
    }

    /**
     * Whether the Spectra plugin is installed, active, and at or above
     * NOVAMIRA_PRO_SPECTRA_MIN_VERSION. Distinct from the Spectra One theme
     * integration's own `SpectraOne\spectra_plugin_available()`, which uses a
     * lower floor (`NOVAMIRA_PRO_SPECTRA_ONE_PLUGIN_MIN_VERSION` = 2.16.0)
     * because there the plugin is optional and only one bridge ability reads it.
     * Here it drives the whole specialization surface — popup CPT, editor
     * settings, Google Fonts, form submissions — so the floor is higher (2.19.0)
     * and the abilities are gated on this at load time.
     */
    function spectra_plugin_available(): bool
    {
        return (
            defined('UAGB_VER')
            && version_compare((string) constant('UAGB_VER'), NOVAMIRA_PRO_SPECTRA_MIN_VERSION, operator: '>=')
        );
    }

    /**
     * Spectra Pro plugin version string, or '' when the Pro add-on is inactive.
     */
    function spectra_pro_version(): string
    {
        return defined('SPECTRA_PRO_VER') ? (string) constant('SPECTRA_PRO_VER') : '';
    }

    /**
     * Whether Spectra Pro (the paid add-on) is installed, active, and at or
     * above NOVAMIRA_PRO_SPECTRA_PRO_MIN_VERSION. Used by the Pro sub-loader
     * to gate registration of `novamira/spectra-pro-*` abilities, so a free-only
     * install never sees Pro abilities in `discover-abilities`.
     */
    function spectra_pro_active(): bool
    {
        return (
            defined('SPECTRA_PRO_VER')
            && version_compare(
                (string) constant('SPECTRA_PRO_VER'),
                NOVAMIRA_PRO_SPECTRA_PRO_MIN_VERSION,
                operator: '>=',
            )
        );
    }
}

namespace Novamira\Pro\Abilities\SpectraBlocks {
    /**
     * Spectra Blocks (free) plugin version string, or '' when the plugin is
     * inactive. Note the constant is defined at the very top of the plugin
     * bootstrap, BEFORE the WP / PHP / legacy-conflict requirement checks
     * early-return — so a non-empty version alone does NOT mean the runtime
     * initialized. `spectra_blocks_plugin_available()` adds the runtime
     * sentinel; check-setup uses this raw accessor to report the installed
     * version even when the gate refuses.
     */
    function spectra_blocks_version(): string
    {
        return defined('SPECTRA_BLOCKS_VER') ? (string) constant('SPECTRA_BLOCKS_VER') : '';
    }

    /**
     * Whether the Spectra Blocks (free) plugin is installed, active, at or
     * above NOVAMIRA_PRO_SPECTRA_BLOCKS_MIN_VERSION, AND actually initialized.
     * The last part matters: `SPECTRA_BLOCKS_VER` is defined before the
     * plugin's own requirement guards (WP 6.6+, PHP 8.1+, legacy UAGB
     * spectra-v3 conflict via `SPECTRA_3_FILE`), each of which early-returns
     * WITHOUT loading the runtime. `class_exists('SpectraBlocks\BlockManager')`
     * is the sentinel that the loader ran and the autoloader is registered —
     * without it the gate would light up against an install where no
     * `spectra/*` block, CPT, or option surface exists. The explicit
     * `SPECTRA_3_FILE` check is kept as well so the refusal reason stays
     * greppable and the gate never trusts a foreign autoloader that happens
     * to expose the same class name.
     */
    function spectra_blocks_plugin_available(): bool
    {
        return (
            defined('SPECTRA_BLOCKS_VER')
            && !defined('SPECTRA_3_FILE')
            && class_exists('SpectraBlocks\\BlockManager')
            && version_compare(
                (string) constant('SPECTRA_BLOCKS_VER'),
                NOVAMIRA_PRO_SPECTRA_BLOCKS_MIN_VERSION,
                operator: '>=',
            )
        );
    }

    /**
     * Spectra Blocks Pro plugin version string, or '' when the Pro add-on is
     * inactive.
     */
    function spectra_blocks_pro_version(): string
    {
        return defined('SPECTRA_BLOCKS_PRO_VER') ? (string) constant('SPECTRA_BLOCKS_PRO_VER') : '';
    }

    /**
     * Whether Spectra Blocks Pro is installed, active, at or above
     * NOVAMIRA_PRO_SPECTRA_BLOCKS_PRO_MIN_VERSION, and running on top of an
     * available free plugin. The free-plugin condition mirrors the Pro
     * bootstrap's own dependency check (`spectra_blocks_pro_init()` refuses
     * when `SPECTRA_BLOCKS_VER` is missing or below its required floor) —
     * Pro never initializes without free, so the gate must not either. A
     * `class_exists` sentinel on the Pro side would be meaningless here:
     * Pro registers its autoloader at file-load time, before its own
     * requirement guards, so the class resolves even when init refused.
     */
    function spectra_blocks_pro_active(): bool
    {
        return (
            defined('SPECTRA_BLOCKS_PRO_VER')
            && spectra_blocks_plugin_available()
            && version_compare(
                (string) constant('SPECTRA_BLOCKS_PRO_VER'),
                NOVAMIRA_PRO_SPECTRA_BLOCKS_PRO_MIN_VERSION,
                operator: '>=',
            )
        );
    }
}

namespace Novamira\Pro\Abilities\Elementor {
    /**
     * Elementor version string, or the empty string when Elementor is inactive.
     */
    function el_elementor_version(): string
    {
        return defined('ELEMENTOR_VERSION') ? (string) constant('ELEMENTOR_VERSION') : '';
    }

    /**
     * Base Elementor surface required by the generic Elementor abilities.
     */
    function el_min_runtime_available(): bool
    {
        return (
            defined('ELEMENTOR_VERSION')
            && version_compare(el_elementor_version(), version2: NOVAMIRA_PRO_ELEMENTOR_MIN_VERSION, operator: '>=')
            && class_exists('Elementor\\Plugin')
        );
    }
}

namespace Novamira\Pro\Abilities\Cf7 {
    /**
     * Whether the Contact Form 7 plugin is installed, active, and at or
     * above the minimum supported version.
     *
     * `WPCF7_VERSION` is defined at the very top of the plugin bootstrap
     * (`wp-contact-form-7.php`) so it lights up as soon as CF7 is loaded;
     * `WPCF7_ContactForm` is the canonical entity class we call into.
     */
    function cf7_runtime_available(): bool
    {
        if (!defined('WPCF7_VERSION')) {
            return false;
        }
        if (!class_exists('WPCF7_ContactForm')) {
            return false;
        }
        return version_compare((string) constant('WPCF7_VERSION'), NOVAMIRA_PRO_CF7_MIN_VERSION, operator: '>=');
    }
}

namespace Novamira\Pro\Abilities\WPForms {
    /**
     * Whether WPForms is active and meets the minimum version Novamira Pro requires.
     *
     * Gates registration of the entire wpforms category, so a too-old install never
     * pollutes the agent tool list. The minimum (NOVAMIRA_PRO_WPFORMS_MIN_VERSION,
     * 1.8.0) is the line where wpforms()->obj('form'|'entry') accessors and the
     * field-type registry shape this integration depends on stabilised.
     */
    function wpforms_min_runtime_available(): bool
    {
        return (
            defined('WPFORMS_VERSION')
            && function_exists('wpforms')
            && version_compare(
                version1: (string) constant('WPFORMS_VERSION'),
                version2: \NOVAMIRA_PRO_WPFORMS_MIN_VERSION,
                operator: '>=',
            )
        );
    }
}

namespace Novamira\Pro\Abilities\GravityForms {
    /**
     * Whether Gravity Forms is active and meets the minimum version Novamira Pro
     * requires.
     *
     * Gates registration of the entire gravityforms category, so a too-old install
     * never pollutes the agent tool list. The minimum (NOVAMIRA_PRO_GRAVITYFORMS_MIN_VERSION,
     * 2.7) is the line where GFAPI::current_user_can_any() and the modern field
     * registry shape this integration depends on stabilised.
     *
     * Gravity Forms is a single-SKU paid plugin (no Lite/Pro edition split), so
     * this is the only runtime gate — there is no separate Pro check.
     */
    function gravityforms_min_runtime_available(): bool
    {
        if (!class_exists('GFForms') || !class_exists('GFAPI')) {
            return false;
        }
        $installed = \GFForms::$version;
        if ($installed === '') {
            return false;
        }
        return version_compare($installed, \NOVAMIRA_PRO_GRAVITYFORMS_MIN_VERSION, operator: '>=');
    }
}

namespace Novamira\Pro\Abilities\FluentForms {
    /**
     * Whether Fluent Forms is active and meets the minimum version Novamira Pro requires.
     *
     * Free FF defines both `FLUENTFORM` (the plugin basename) and
     * `FLUENTFORM_VERSION`. FF Pro hard-depends on free and defines
     * `FLUENTFORMPRO` + `FLUENTFORMPRO_VERSION`. License status is informational
     * only — Pro abilities run unlicensed on a Pro install.
     */
    function ff_min_runtime_available(): bool
    {
        return (
            defined('FLUENTFORM_VERSION')
            && version_compare(
                version1: (string) constant('FLUENTFORM_VERSION'),
                version2: \NOVAMIRA_PRO_FLUENTFORMS_MIN_VERSION,
                operator: '>=',
            )
        );
    }
}

namespace Novamira\Pro\Abilities\NinjaForms {
    /**
     * Whether Ninja Forms is loaded and at or above the minimum supported
     * version. Ninja Forms publishes its version as a class constant on
     * its main entity (`Ninja_Forms::VERSION`) rather than as a top-level
     * `define()`. The singleton class is loaded on `plugins_loaded` and is
     * the canonical "Ninja Forms is alive on this request" signal — the
     * class-constant lookup doubles as the version gate.
     */
    function ninja_forms_runtime_available(): bool
    {
        if (!class_exists('Ninja_Forms')) {
            return false;
        }
        $version = ninja_forms_version();
        if ($version === '') {
            return false;
        }
        return version_compare($version, NOVAMIRA_PRO_NINJAFORMS_MIN_VERSION, operator: '>=');
    }

    /**
     * Ninja Forms version string, or empty when the plugin is inactive.
     * Reads from the `Ninja_Forms::VERSION` class constant — Ninja Forms
     * does not expose a top-level `NF_PLUGIN_VERSION` define.
     */
    function ninja_forms_version(): string
    {
        if (!class_exists('Ninja_Forms')) {
            return '';
        }
        /** @var mixed $version */
        $version = constant('Ninja_Forms::VERSION');
        return is_string($version) ? $version : '';
    }
}

namespace Novamira\Pro\Abilities\CodeSnippets {
    /**
     * Whether the Code Snippets plugin (free or Pro) is installed and active.
     *
     * The plugin exposes a `code_snippets()` accessor in its own namespace
     * once `code-snippets.php` has loaded; we use that as the canonical
     * "is the plugin live in this request" signal.
     */
    function cs_runtime_available(): bool
    {
        if (!defined('CODE_SNIPPETS_VERSION')) {
            return false;
        }
        if (!function_exists('Code_Snippets\\code_snippets')) {
            return false;
        }
        return version_compare(
            (string) constant('CODE_SNIPPETS_VERSION'),
            NOVAMIRA_PRO_CODE_SNIPPETS_MIN_VERSION,
            operator: '>=',
        );
    }
}

namespace Novamira\Pro\Abilities\Astra {
    /**
     * The Astra theme is installed, active, and at or above the minimum
     * supported version. `ASTRA_THEME_VERSION` is defined in the theme
     * bootstrap (`functions.php`); the `astra_get_option` sentinel confirms
     * that the theme API has loaded for this request — the constant alone is
     * not enough because PHP keeps it defined after a mid-request theme switch.
     */
    function astra_theme_available(): bool
    {
        return (
            defined('ASTRA_THEME_VERSION')
            && function_exists('astra_get_option')
            && version_compare(
                (string) constant('ASTRA_THEME_VERSION'),
                NOVAMIRA_PRO_ASTRA_THEME_MIN_VERSION,
                operator: '>=',
            )
        );
    }

    /**
     * Astra Pro Addon is present and at or above the minimum supported version.
     * The authoritative presence check is the `ASTRA_EXT_VER` constant plus
     * the `Astra_Ext_Extension` class — both are defined in the addon bootstrap
     * (`astra-addon.php`) and disappear when the plugin is deactivated.
     */
    function astra_addon_available(): bool
    {
        return (
            defined('ASTRA_EXT_VER')
            && class_exists('Astra_Ext_Extension')
            && version_compare((string) constant('ASTRA_EXT_VER'), NOVAMIRA_PRO_ASTRA_ADDON_MIN_VERSION, operator: '>=')
        );
    }
}

namespace Novamira\Pro\Abilities\OceanWP {
    /**
     * The OceanWP theme is active and at or above the minimum supported
     * release. The version constant is defined inside the theme bootstrap
     * (`functions.php`) so it lights up as soon as the theme is active.
     * `oceanwp_post_layout` is the canonical render-time helper every layout
     * override path goes through — using it as the sentinel proves the API
     * shape, not just constant presence.
     */
    function oceanwp_theme_available(): bool
    {
        return (
            defined('OCEANWP_THEME_VERSION')
            && function_exists('oceanwp_post_layout')
            && version_compare(
                (string) constant('OCEANWP_THEME_VERSION'),
                \NOVAMIRA_PRO_OCEANWP_THEME_MIN_VERSION,
                operator: '>=',
            )
        );
    }

    /**
     * Whether the Ocean Extra companion plugin's bootstrap class has loaded
     * on this request. Cheaper than `is_plugin_active()` and matches the
     * sentinel OceanWP itself uses in `functions.php:166`.
     */
    function ocean_extra_active(): bool
    {
        return class_exists('Ocean_Extra');
    }

    /**
     * Ocean Extra version string, or empty when the plugin is inactive or
     * its header cannot be read. The plugin does not consistently expose a
     * top-level version constant across releases, so we read from the
     * plugin header via `get_plugin_data()` (self-including
     * `wp-admin/includes/plugin.php` when needed). Same pattern Etch uses
     * for the equivalent header read.
     */
    function ocean_extra_version(): string
    {
        if (!ocean_extra_active()) {
            return '';
        }
        if (!function_exists('get_plugin_data')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }
        $file = WP_PLUGIN_DIR . '/ocean-extra/ocean-extra.php';
        if (!file_exists($file)) {
            return '';
        }
        /** @var array{Version?: string} $data */
        $data = get_plugin_data($file, markup: false, translate: false);
        return $data['Version'] ?? '';
    }

    /**
     * Ocean Extra is installed, active, and at or above the minimum supported
     * version. Ocean Extra is OPTIONAL: the S1–S3 abilities work theme-only
     * and self-degrade. Sprint-4 abilities (Library, Modules, Demo Import)
     * gate on this and return `oceanwp_requires_ocean_extra` when false.
     */
    function ocean_extra_available(): bool
    {
        if (!ocean_extra_active()) {
            return false;
        }
        $version = ocean_extra_version();
        if ($version === '') {
            return false;
        }
        return version_compare($version, \NOVAMIRA_PRO_OCEAN_EXTRA_MIN_VERSION, operator: '>=');
    }
}

namespace Novamira\Pro\Abilities\DynamicContentForElementor {
    /**
     * Whether Dynamic Content for Elementor is installed, active, and at or above
     * NOVAMIRA_PRO_DYNAMIC_CONTENT_FOR_ELEMENTOR_MIN_VERSION. What that floor means
     * is stated where it is declared, in includes/version-floors.php. Below it the
     * whole specialization stays unregistered — not even check-setup is offered —
     * rather than exposing abilities that might address a shape the installed
     * plugin does not have.
     *
     * The class that names that shape is checked as well as the version. Every
     * file in the specialization dereferences `PdfHtmlTemplates` at file scope —
     * the post type, the meta key and the meta's seven sub-keys are all
     * `const X = PdfHtmlTemplates::Y` declarations, resolved while the file is
     * being included. A rename upstream that left the version alone would turn
     * every request into a fatal error instead of an absent surface, so it is
     * made to behave exactly like a version below the floor: nothing registers.
     */
    function dce_min_runtime_available(): bool
    {
        return (
            defined('DCE_VERSION')
            && class_exists('DynamicContentForElementor\\Plugin')
            && class_exists('DynamicContentForElementor\\PdfHtmlTemplates')
            && version_compare(
                (string) constant('DCE_VERSION'),
                NOVAMIRA_PRO_DYNAMIC_CONTENT_FOR_ELEMENTOR_MIN_VERSION,
                operator: '>=',
            )
        );
    }
}

namespace Novamira\Pro\Abilities\DynamicShortcodes {
    /**
     * Whether the Dynamic Shortcodes plugin is installed, active, and at least
     * at the version where the public lint() and Power Shortcodes facade are
     * available. Below the floor the whole specialization stays unregistered —
     * nothing (not even check-setup) is offered — so an agent never sees an
     * ability that would call a method the installed plugin does not have.
     */
    function dsh_min_runtime_available(): bool
    {
        return (
            defined('DYNAMIC_SHORTCODES_VERSION')
            && class_exists('DynamicShortcodes\\Plugin')
            && version_compare(
                (string) constant('DYNAMIC_SHORTCODES_VERSION'),
                NOVAMIRA_PRO_DYNAMIC_SHORTCODES_MIN_VERSION,
                operator: '>=',
            )
        );
    }
}

namespace Novamira\Pro\Abilities\Avada {
    /**
     * Whether the Avada theme is active AND meets the minimum supported version.
     *
     * `AVADA_VERSION` is defined at the top of the theme's `functions.php`
     * (a require of `Avada/functions.php` occurs before any REST request
     * reaches abilities-api-init when Avada is the active theme). Every
     * theme-side ability gates on this — the two Fusion Builder-plugin-
     * scoped abilities (currently blocked) additionally gate on
     * `fusion_builder_available()`.
     */
    function avada_theme_available(): bool
    {
        return (
            defined('AVADA_VERSION')
            && version_compare((string) constant('AVADA_VERSION'), NOVAMIRA_PRO_AVADA_MIN_VERSION, operator: '>=')
        );
    }

    /**
     * Whether the (bundled) Fusion Builder plugin is present AND at the
     * supported floor. Distinct from `avada_theme_available()`: the theme
     * ships some capabilities on its own, but the element-tree CRUD, the
     * Template Builder runtime, and the Fusion Forms CPT live in the
     * separate `fusion-builder` companion plugin. Guarded here so the
     * eventual Pro sub-loader in `novamira_pro_load_avada_abilities()`
     * can require its own file set without a first-word `if (…)` check.
     * `FUSION_BUILDER_VERSION` is defined in the plugin's bootstrap.
     */
    function fusion_builder_available(): bool
    {
        return (
            defined('FUSION_BUILDER_VERSION')
            && version_compare(
                (string) constant('FUSION_BUILDER_VERSION'),
                NOVAMIRA_PRO_FUSION_BUILDER_MIN_VERSION,
                operator: '>=',
            )
        );
    }

    /**
     * Whether the (bundled) Fusion Core plugin is present AND at the
     * supported floor. Fusion Core registers the `slide` CPT + `slide-page`
     * taxonomy that back the Fusion Slider abilities; when it's inactive
     * the Fusion Slider ability sub-loader stays wired-off and the CPT-
     * touching helpers safely early-return. `FUSION_CORE_VERSION` is
     * defined at the top of `fusion-core.php` so the gate lights up as
     * soon as the plugin loads. Extra `class_exists('FusionCore_Plugin')`
     * check guards against the (rare) constants-defined-but-classes-not-
     * loaded window during `plugins_loaded` before Fusion Core's own
     * bootstrap has finished — the ability code calls into the class
     * (namespaced statics + singleton) so the class must exist by the
     * time any ability executes.
     */
    function fusion_core_available(): bool
    {
        return (
            defined('FUSION_CORE_VERSION')
            && class_exists('FusionCore_Plugin')
            && version_compare(
                (string) constant('FUSION_CORE_VERSION'),
                NOVAMIRA_PRO_FUSION_CORE_MIN_VERSION,
                operator: '>=',
            )
        );
    }
}

namespace Novamira\Pro\Abilities\Voxel {
    /**
     * Whether the Voxel theme is installed, active for THIS request, and its
     * API has loaded — a PRESENCE check only, NOT a version check.
     *
     * Voxel exposes no version constant, so presence is proven by the `\Voxel\get`
     * function + the `\Voxel\Post_Type` class (both defined in the theme bootstrap
     * and gone the moment Voxel is not the active theme's code). The
     * `get_template()` / `get_stylesheet()` check makes the gate multisite-safe:
     * a `switch_to_blog()` request where Voxel is loaded process-wide but is NOT
     * the active theme of the switched-to site is refused, so we never operate
     * against a foreign site's data (same reasoning as `spectra_one_theme_available`).
     *
     * The version floor is deliberately enforced per-callback
     * (`vx_require_min()`), not here, so `voxel-check-setup` remains registered on
     * an older Voxel and can report the gap. See NOVAMIRA_PRO_VOXEL_THEME_MIN_VERSION.
     */
    function voxel_theme_present(): bool
    {
        return (
            function_exists('Voxel\\get')
            && class_exists('Voxel\\Post_Type')
            && (
                function_exists('get_template')
                && get_template() === 'voxel'
                || function_exists('get_stylesheet')
                && get_stylesheet() === 'voxel'
            )
        );
    }

    /**
     * Voxel theme version, read from the `style.css` header of the parent
     * template (so a child theme's own version never masks Voxel's), or '' when
     * Voxel is not resolvable. There is no `\Voxel\VERSION` constant to read.
     */
    function voxel_theme_version(): string
    {
        if (!function_exists('wp_get_theme') || !function_exists('get_template')) {
            return '';
        }
        $version = wp_get_theme(get_template())->get('Version');

        /**
         * Override the detected Voxel version. Default is the `style.css` header
         * of the active parent template. Provided as an override seam for unusual
         * installs and to make the below-minimum gate state observable in tests;
         * it only affects the per-callback version floor, never the presence gate.
         *
         * @param string $version
         */
        /** @var mixed $filtered */
        $filtered = apply_filters('novamira/voxel/detected_version', $version);
        return is_string($filtered) ? $filtered : $version;
    }
}

namespace Novamira\Pro\Abilities\Blocksy {
    /**
     * The Blocksy theme (or a child of it) is the active theme. Presence
     * check only — Blocksy defines no version constant, so the version
     * lives in the parent `style.css` header and is checked separately by
     * `blocksy_theme_available()`. The two-sentinel check proves the theme
     * API actually loaded for this request: `blocksy_manager` is the
     * bootstrap singleton accessor, `blocksy_get_options` is the option
     * schema reader every read ability depends on.
     *
     * The manifest gate deliberately uses THIS presence check, not the
     * version floor, so `novamira/blocksy-check-setup` stays registered on
     * an older Blocksy and can report `min_satisfied: false` — a below-min
     * install must be observable, not a silently empty tool list (same
     * policy as the Voxel gate).
     */
    function blocksy_theme_present(): bool
    {
        return function_exists('blocksy_manager') && function_exists('blocksy_get_options');
    }

    /**
     * The Blocksy theme is present AND at or above the minimum supported
     * version (the 2.1.47 CompanionBridge floor). Every blocksy-* ability
     * except check-setup refuses early with `bly_theme_inactive` when this
     * is false.
     *
     * The write abilities pass their PINNED template slug so this gate
     * performs no filtered theme lookup of its own — get_template() runs
     * the `template` / `pre_option_template` filters, and the storage
     * identity must be resolved exactly once, before ANY such lookup.
     */
    function blocksy_theme_available(?string $template = null): bool
    {
        if (!blocksy_theme_present()) {
            return false;
        }
        $version = wp_get_theme($template ?? get_template())->get('Version');
        return version_compare($version, NOVAMIRA_PRO_BLOCKSY_THEME_MIN_VERSION, operator: '>=');
    }

    /**
     * Blocksy Companion (free or Pro) is active and at or above the minimum
     * supported version. `BLOCKSY__FILE__` is defined in the companion
     * bootstrap; `blocksy_companion_get_version()` is the canonical version
     * accessor. Sprint-1 read abilities do not require the companion — this
     * feeds check-setup reporting and gates future companion-backed surfaces.
     */
    function blocksy_companion_available(): bool
    {
        if (!defined('BLOCKSY__FILE__') || !function_exists('blocksy_companion_get_version')) {
            return false;
        }
        return version_compare(
            blocksy_companion_get_version(),
            NOVAMIRA_PRO_BLOCKSY_COMPANION_MIN_VERSION,
            operator: '>=',
        );
    }
}
