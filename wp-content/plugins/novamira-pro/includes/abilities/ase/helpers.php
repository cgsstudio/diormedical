<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\ASE;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// Runtime guards
// ---------------------------------------------------------------------------

/**
 * Verify that ASE is active. Every ability callback calls this as its first
 * statement. Returns a WP_Error when the check fails so the caller can
 * immediately return it; returns null when the plugin is present and
 * the version requirement is satisfied.
 *
 * @return WP_Error|null
 */
function ase_runtime_guard(): ?WP_Error
{
    if (!defined('ASENHA_VERSION')) {
        return new WP_Error('ase_not_active', __(
            'The Admin and Site Enhancements (ASE) plugin is not active. Please install and activate ASE Pro to use these abilities.',
            domain: 'novamira-pro',
        ));
    }

    return null;
}

/**
 * Check whether the active ASE build includes the Pro modules.
 *
 * Pro detection is based on the Freemius `bwasenha_fs()` accessor, which is
 * only defined in the Pro distribution of ASE.
 *
 * @return bool
 */
function ase_pro_active(): bool
{
    return function_exists('bwasenha_fs');
}

/**
 * Check whether the active ASE Pro license actually authorizes the premium
 * code paths. Returns true only when Freemius confirms the install can run
 * premium features — typically when a paid license is activated, or during
 * a valid trial.
 *
 * Important: this is stricter than `ase_pro_active()`. The Pro plugin can be
 * installed and `bwasenha_fs()` defined without a valid license; in that
 * state ASE refuses to register the Custom Content Types module at all.
 * Our integration's abilities can still write the DB rows (and read them
 * back), but ASE will not call `register_post_type()` / `register_taxonomy()`
 * on those rows until the license is activated. Surface this through
 * `check-setup`'s `pro_licensed` field so the agent can warn the user.
 *
 * @return bool
 */
function ase_pro_licensed(): bool
{
    if (!ase_pro_active()) {
        return false;
    }
    $fs = bwasenha_fs();
    if (!method_exists($fs, 'can_use_premium_code__premium_only')) {
        return false;
    }
    return (bool) $fs->can_use_premium_code__premium_only();
}

/**
 * Return a WP_Error when ASE Pro is not active.
 *
 * @return WP_Error|null
 */
function ase_pro_guard(): ?WP_Error
{
    if (!ase_pro_active()) {
        return new WP_Error('ase_pro_required', __(
            'This ability requires Admin and Site Enhancements Pro. The free build of ASE does not include Custom Field Types, Custom Post Types, or Custom Taxonomies modules.',
            domain: 'novamira-pro',
        ));
    }

    return null;
}

// ---------------------------------------------------------------------------
// Module-level helpers
// ---------------------------------------------------------------------------

/**
 * Check whether a specific ASE module toggle is enabled.
 *
 * ASE stores all module toggles as a flat associative array in the
 * `admin_site_enhancements` option. Each key is a module identifier
 * (e.g. `enable_custom_field_types`) and the value is truthy when enabled.
 *
 * @param string $module_key The option key for the module (e.g. 'custom_content_types').
 * @return bool
 */
function ase_module_active(string $module_key): bool
{
    /** @var mixed $options */
    $options = get_option('admin_site_enhancements', []);
    if (!is_array($options)) {
        return false;
    }

    /** @var mixed $value */
    $value = $options[$module_key] ?? null;

    return $value !== null && $value !== false && $value !== '' && $value !== 0 && $value !== '0' && $value !== [];
}

/**
 * Check whether the Custom Field Types (CFT) module is enabled.
 *
 * Important: ASE has a single master toggle `custom_content_types` that
 * gates BOTH the CCT (CPT + taxonomies) machinery AND the CFT (cfgroup)
 * module — they ship inside the same `if` branch in ASE's bootstrap. There
 * is no separate `custom_field_types` option key. The two helpers therefore
 * return the same value; both are kept for readable call sites
 * (`ase_cft_module_active()` in CFT abilities, `ase_cct_module_active()`
 * in CPT/tax abilities).
 *
 * @return bool
 */
function ase_cft_module_active(): bool
{
    return ase_module_active('custom_content_types');
}

/**
 * Check whether the Custom Content Types (CCT) module is enabled.
 *
 * The same master toggle (`custom_content_types`) governs Custom Post Types,
 * Custom Taxonomies, and Custom Field Types in ASE — see ase_cft_module_active.
 *
 * @return bool
 */
function ase_cct_module_active(): bool
{
    return ase_module_active('custom_content_types');
}

/**
 * Return a WP_Error when the given ASE module is disabled.
 *
 * @param string $module_key The option key for the module.
 * @param string $message    Human-readable message explaining what to enable.
 * @return WP_Error|null
 */
function ase_module_guard(string $module_key, string $message): ?WP_Error
{
    if (!ase_module_active($module_key)) {
        return new WP_Error('ase_module_disabled', $message);
    }

    return null;
}

// ---------------------------------------------------------------------------
// Entity lookup helpers
// ---------------------------------------------------------------------------

/**
 * Look up a field group CPT post by its key (slug / post_name).
 *
 * @param string $key The field group slug (post_name of the asenha_cfgroup CPT).
 * @return WP_Post|null
 */
function ase_get_field_group_by_key(string $key): ?WP_Post
{
    $query = new \WP_Query([
        'post_type' => 'asenha_cfgroup',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'no_found_rows' => true,
        'name' => $key,
    ]);

    $posts = $query->posts;

    if ($posts === []) {
        return null;
    }

    $first = $posts[0];

    return $first instanceof WP_Post ? $first : null;
}

/**
 * Look up a Custom Post Type definition post by its registered slug.
 *
 * ASE stores the CPT slug in the `cpt_key` postmeta on the `asenha_cpt` CPT.
 *
 * @param string $slug The CPT slug (e.g. "book").
 * @return WP_Post|null
 */
function ase_get_cpt_by_slug(string $slug): ?WP_Post
{
    $query = new \WP_Query([
        'post_type' => 'asenha_cpt',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'no_found_rows' => true,
        'meta_query' => [
            [
                'key' => 'cpt_key',
                'value' => $slug,
            ],
        ],
    ]);

    $posts = $query->posts;

    if ($posts === []) {
        return null;
    }

    $first = $posts[0];

    return $first instanceof WP_Post ? $first : null;
}

/**
 * Look up a Custom Taxonomy definition post by its registered slug.
 *
 * ASE stores the taxonomy slug in the `ctax_key` postmeta on the `asenha_ctax` CPT.
 *
 * @param string $slug The taxonomy slug (e.g. "genre").
 * @return WP_Post|null
 */
function ase_get_taxonomy_by_slug(string $slug): ?WP_Post
{
    $query = new \WP_Query([
        'post_type' => 'asenha_ctax',
        'post_status' => 'publish',
        'posts_per_page' => 1,
        'no_found_rows' => true,
        'meta_query' => [
            [
                'key' => 'ctax_key',
                'value' => $slug,
            ],
        ],
    ]);

    $posts = $query->posts;

    if ($posts === []) {
        return null;
    }

    $first = $posts[0];

    return $first instanceof WP_Post ? $first : null;
}

// ---------------------------------------------------------------------------
// Reserved slug lists
// ---------------------------------------------------------------------------

/**
 * WordPress core post type slugs that must not be used as custom CPT slugs.
 *
 * Attempting to register a CPT with one of these slugs would conflict with
 * WordPress internals. `ase-create-post-type` refuses with
 * `ase_post_type_slug_reserved` when the provided slug matches.
 *
 * @return list<string>
 */
function ase_reserved_post_type_slugs(): array
{
    return [
        'post',
        'page',
        'attachment',
        'revision',
        'nav_menu_item',
        'custom_css',
        'customize_changeset',
        'oembed_cache',
        'user_request',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_global_styles',
        'wp_navigation',
    ];
}

/**
 * WordPress core taxonomy slugs that must not be used as custom taxonomy slugs.
 *
 * `ase-create-taxonomy` refuses with `ase_taxonomy_slug_reserved` when the
 * provided slug matches one of these.
 *
 * @return list<string>
 */
function ase_reserved_taxonomy_slugs(): array
{
    return [
        'category',
        'post_tag',
        'nav_menu',
        'link_category',
        'post_format',
    ];
}

// ---------------------------------------------------------------------------
// Term field-group resolver
// ---------------------------------------------------------------------------

/**
 * Return the IDs of all published `asenha_cfgroup` posts whose location rules
 * match a given taxonomy — correctly handling the catch-all case that ASE's
 * own `get_matching_groups_for_taxonomy()` skips.
 *
 * ASE's built-in method skips any field group that has either (a) no
 * `taxonomies` rule key at all, or (b) an `operator='!='` rule with an empty
 * values list — even though both of those configurations semantically mean
 * "applies to all taxonomy terms". This helper treats both cases as catch-all
 * and includes those groups in the returned list.
 *
 * @param string $taxonomy_slug The registered taxonomy slug (e.g. 'genre').
 * @return list<int> Matching field-group post IDs.
 */
function ase_resolve_term_group_ids(string $taxonomy_slug): array
{
    $query = new \WP_Query([
        'post_type' => 'asenha_cfgroup',
        'post_status' => 'publish',
        'posts_per_page' => -1,
        'no_found_rows' => true,
        'fields' => 'ids',
    ]);

    /** @var array<mixed> $ids */
    $ids = $query->posts;

    $group_ids = [];

    /** @var mixed $id */
    foreach ($ids as $id) {
        if (!is_int($id)) {
            continue;
        }

        $rules = ase_fg_read_rules($id);

        /** @var mixed $raw_placement */
        $raw_placement = $rules['placement']['values'] ?? '';
        if ($raw_placement !== 'taxonomy-terms') {
            continue;
        }

        // If no taxonomies rule is set, the group applies to all taxonomy terms.
        /** @var mixed $tax_rule */
        $tax_rule = $rules['taxonomies'] ?? null;
        /** @var mixed $tax_operator */
        $tax_operator = is_array($tax_rule) ? $tax_rule['operator'] ?? null : null;
        if ($tax_operator === null) {
            // No taxonomies rule — catch-all, include unconditionally.
            $group_ids[] = $id;
            continue;
        }

        // Evaluate the operator against the stored taxonomy list.
        $tax_values = ase_fg_extract_rule_values('taxonomies', $rules);
        /** @var mixed $raw_op */
        $raw_op = is_array($tax_operator) ? $tax_operator[0] ?? '' : $tax_operator;
        $operator = is_string($raw_op) ? $raw_op : '';

        $matches = match ($operator) {
            // == and IN are treated identically — include when the taxonomy
            // appears in the list. ASE's admin UI uses '==' for single-value
            // rules and stores 'IN' when the rule is multi-value, but our
            // manage-field-groups.php writes 'IN' uniformly. Accept both.
            '==', 'IN' => in_array($taxonomy_slug, $tax_values, strict: true),
            // != and NOT IN are inverse. Empty exclude-list = catch-all.
            '!=', 'NOT IN' => !in_array($taxonomy_slug, $tax_values, strict: true),
            default => false,
        };

        if ($matches) {
            $group_ids[] = $id;
        }
    }

    return $group_ids;
}

// ---------------------------------------------------------------------------
// CFT runtime loader
// ---------------------------------------------------------------------------

/**
 * Ensure ASE's CFT (Custom Field Types) helper functions are loaded and initialized.
 *
 * ASE only requires cfgroup.php from within an admin web request (bootstrap.php
 * → Custom_Content_Types registered on `init`). The CFG object is additionally
 * bootstrapped via cfgroup_init::init(), which sets up CFG()->api, CFG()->fields,
 * etc. In CLI / REST / cron contexts neither is done automatically — we must do
 * both here.
 *
 * Call this at the start of every ability callback that uses find_cf / get_cf /
 * update_cf. It is safe to call repeatedly — both require_once and the CFG guard
 * make it a no-op after the first call.
 *
 * @return bool True when the helpers are available after this call.
 */
function ase_ensure_cfgroup_functions_loaded(): bool
{
    // If find_cf is defined the cfgroup functions are already loaded.
    if (function_exists('find_cf')) {
        return true;
    }

    if (!defined('ASENHA_PATH')) {
        return false;
    }

    /** @var string $asenha_path */
    $asenha_path = constant('ASENHA_PATH');
    $cfgroup_file = $asenha_path . 'includes/premium/custom-content/cfgroup/cfgroup.php';
    if (!file_exists($cfgroup_file)) {
        return false;
    }

    // Load cfgroup.php — defines the Custom_Field_Group class, CFG() singleton,
    // cfgroup_init class, and requires cfgroup_functions.php.
    require_once $cfgroup_file;

    // cfgroup_init::__construct() registers its init() on the 'init' action.
    // In a web request that fires automatically. In CLI, the 'init' hook has
    // already fired by the time wp eval-file runs, so we must call init()
    // manually to set up CFG()->api, CFG()->fields, etc.
    if (class_exists('cfgroup_init')) {
        $initializer = new \cfgroup_init();
        $initializer->init();
    }

    return function_exists('find_cf');
}
