<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Load every Etch ability file. check-setup is the read-only baseline; the
 * content abilities (list-elements, get-content, set-content) read and rewrite
 * the page element tree; manage-components handles the reusable component
 * library (wp_block patterns). Later sprints add the style and dynamic-data
 * abilities.
 */
function novamira_pro_load_etch_abilities(): void
{
    $etch_dir = __DIR__ . '/abilities/etch/';
    require_once $etch_dir . 'helpers.php';
    require_once $etch_dir . 'check-setup.php';
    require_once $etch_dir . 'list-elements.php';
    require_once $etch_dir . 'get-content.php';
    require_once $etch_dir . 'set-content.php';
    require_once $etch_dir . 'manage-nodes.php';
    require_once $etch_dir . 'manage-components.php';
    require_once $etch_dir . 'manage-styles.php';
    require_once $etch_dir . 'manage-loops.php';
    require_once $etch_dir . 'manage-post-types.php';
    require_once $etch_dir . 'manage-taxonomies.php';
    require_once $etch_dir . 'manage-queries.php';
    require_once $etch_dir . 'manage-custom-fields.php';
    require_once $etch_dir . 'manage-templates.php';

    // Per-post custom field VALUES — gated on Etch 1.5.0+ (the CustomFieldValues
    // REST API). Registered only when supported so the agent's tool list isn't
    // polluted on older Etch; every ability above works on all supported Etch.
    if (\Novamira\Pro\Abilities\Etch\etch_supports_field_values()) {
        require_once $etch_dir . 'manage-custom-field-values.php';
    }
}

/**
 * Load every ASE ability file.
 *
 * Extracted out of the `wp_abilities_api_init` closure to keep that
 * registration handler small and to give the ASE block a single named entry
 * point. Additional ability files (field groups, values, CPTs, taxonomies)
 * will be added here by their respective tasks.
 */
function novamira_pro_load_ase_abilities(): void
{
    $dir = __DIR__ . '/abilities/ase/';
    require_once $dir . 'helpers.php';

    // check-setup is always available — even on ASE Free — so an agent can
    // introspect the install and learn that the other abilities require Pro.
    require_once $dir . 'check-setup.php';

    // All other ASE abilities are Pro-only. We gate them at registration
    // rather than only at runtime so the agent's tool list isn't polluted
    // with 17 abilities that would all return ase_pro_required on a Free
    // install.
    if (!\Novamira\Pro\Abilities\ASE\ase_pro_active()) {
        return;
    }

    require_once $dir . 'list-field-groups.php';
    require_once $dir . 'get-field-group.php';
    require_once $dir . 'manage-field-groups.php';
    require_once $dir . 'read-values.php';
    require_once $dir . 'write-values.php';

    require_once $dir . 'list-post-types.php';
    require_once $dir . 'get-post-type.php';
    require_once $dir . 'manage-post-types.php';

    require_once $dir . 'list-taxonomies.php';
    require_once $dir . 'get-taxonomy.php';
    require_once $dir . 'manage-taxonomies.php';
}

/**
 * Load every Elementor ability file in the right order.
 *
 * Extracted out of the `wp_abilities_api_init` closure to keep that
 * registration handler small and to give the Elementor block a single
 * named entry point.
 */
function novamira_pro_load_elementor_abilities(): void
{
    $elementor_dir = __DIR__ . '/abilities/elementor/';
    require_once $elementor_dir . 'runtime.php';
    // Extraction library must load before helpers, write abilities, or the
    // unified schema ability so every caller sees the same functions.
    require_once $elementor_dir . 'schema-extractor.php';
    require_once $elementor_dir . 'helpers.php';
    require_once $elementor_dir . 'not-persisted.php';
    require_once $elementor_dir . 'get-content.php';
    require_once $elementor_dir . 'pipeline.php';
    require_once $elementor_dir . 'set-content.php';
    require_once $elementor_dir . 'manage-templates.php';
    require_once $elementor_dir . 'display-conditions.php';
    require_once $elementor_dir . 'add-element.php';
    require_once $elementor_dir . 'edit-element.php';
    require_once $elementor_dir . 'delete-element.php';
    require_once $elementor_dir . 'delete-element-style.php';
    require_once $elementor_dir . 'get-widget-schema.php';
    require_once $elementor_dir . 'manage-global-styles-v3.php';
    require_once $elementor_dir . 'manage-dynamic-tags.php';
    require_once $elementor_dir . 'check-setup.php';
    require_once $elementor_dir . 'clear-document-cache.php';

    if (\Novamira\Pro\Abilities\Elementor\el_atomic_runtime_available()) {
        require_once $elementor_dir . 'validate-widget.php';
        require_once $elementor_dir . 'create-atomic-widget.php';
    }

    if (\Novamira\Pro\Abilities\Elementor\el_style_schema_available()) {
        require_once $elementor_dir . 'get-style-schema.php';
    }

    if (\Novamira\Pro\Abilities\Elementor\el_global_classes_available()) {
        require_once $elementor_dir . 'manage-global-classes.php';
    }

    if (\Novamira\Pro\Abilities\Elementor\el_variables_available()) {
        require_once $elementor_dir . 'manage-variables.php';
    }

    if (\Novamira\Pro\Abilities\Elementor\el_interactions_available()) {
        require_once $elementor_dir . 'manage-interactions.php';
    }
}

/**
 * Load every Pods ability file in order. Pods-specific feature gates (the
 * Advanced Content Types component) are checked at the loader level so that
 * an ACT-disabled site does not register `pods-create-act` and the agent
 * sees an honest tool surface.
 */
function novamira_pro_load_pods_abilities(): void
{
    $pods_dir = __DIR__ . '/abilities/pods/';
    require_once $pods_dir . 'runtime.php';
    require_once $pods_dir . 'helpers.php';
    require_once $pods_dir . 'check-setup.php';
    require_once $pods_dir . 'list-pods.php';
    require_once $pods_dir . 'get-pod.php';
    require_once $pods_dir . 'get-field-type-schema.php';
    require_once $pods_dir . 'create-cpt.php';
    require_once $pods_dir . 'create-taxonomy.php';
    require_once $pods_dir . 'create-settings.php';
    require_once $pods_dir . 'extend-builtin.php';
    require_once $pods_dir . 'edit-pod.php';
    require_once $pods_dir . 'delete-pod.php';
    require_once $pods_dir . 'manage-fields.php';
    require_once $pods_dir . 'manage-groups.php';
    require_once $pods_dir . 'list-items.php';
    require_once $pods_dir . 'get-item.php';
    require_once $pods_dir . 'create-item.php';
    require_once $pods_dir . 'edit-item.php';
    require_once $pods_dir . 'delete-item.php';

    if (\Novamira\Pro\Abilities\Pods\pods_act_runtime_available()) {
        require_once $pods_dir . 'create-act.php';
    }
}

/**
 * Load every ACF ability file in the right order.
 *
 * Extracted out of the `wp_abilities_api_init` closure to keep that
 * registration handler small and to give the ACF block a single
 * named entry point.
 */
function novamira_pro_load_acf_abilities(): void
{
    $acf_dir = __DIR__ . '/abilities/acf/';
    require_once $acf_dir . 'helpers.php';
    require_once $acf_dir . 'check-setup.php';
    require_once $acf_dir . 'list-fieldgroups.php';
    require_once $acf_dir . 'get-fieldgroup-schema.php';
    require_once $acf_dir . 'read-values.php';
    require_once $acf_dir . 'write-values.php';
    require_once $acf_dir . 'manage-fieldgroups.php';

    // Internal post-type management abilities require ACF Pro 6.5+ (the
    // documented minimum). The acf_get_internal_post_type_posts() API is not
    // present in ACF Free; on Pro below 6.5 the runtime guard inside the
    // abilities returns acf_version_unsupported. Skip registration on Free so
    // the abilities never appear in discover-abilities on unsupported installs.
    if (function_exists('acf_get_internal_post_type_posts')) {
        require_once $acf_dir . 'list-post-types.php';
        require_once $acf_dir . 'get-post-type.php';
        require_once $acf_dir . 'manage-post-types.php';
        require_once $acf_dir . 'list-taxonomies.php';
        require_once $acf_dir . 'get-taxonomy.php';
        require_once $acf_dir . 'manage-taxonomies.php';
        require_once $acf_dir . 'list-options-pages.php';
        require_once $acf_dir . 'get-options-page.php';
        require_once $acf_dir . 'manage-options-pages.php';
    }
}

/**
 * Load every Yoast SEO ability file in the right order. Sprint 1 is read-only:
 * setup discovery plus per-post, per-term, and site-wide settings readers.
 */
function novamira_pro_load_yoast_abilities(): void
{
    $yoast_dir = __DIR__ . '/abilities/yoast/';
    require_once $yoast_dir . 'helpers.php';
    require_once $yoast_dir . 'check-setup.php';
    require_once $yoast_dir . 'get-post-seo.php';
    require_once $yoast_dir . 'get-term-seo.php';
    require_once $yoast_dir . 'get-settings.php';
    require_once $yoast_dir . 'edit-post-seo.php';
    require_once $yoast_dir . 'edit-term-seo.php';

    // Redirect abilities require Yoast SEO Premium (the redirect module). The
    // abilities also guard at runtime via yoast_redirect_premium(), but skipping
    // registration keeps them out of discover-abilities on the free edition.
    if (defined('WPSEO_PREMIUM_VERSION')) {
        require_once $yoast_dir . 'list-redirects.php';
        require_once $yoast_dir . 'create-redirect.php';
        require_once $yoast_dir . 'edit-redirect.php';
        require_once $yoast_dir . 'delete-redirect.php';
    }
}

/**
 * Load every Rank Math ability file (discovery + per-post/term SEO read & write
 * + site-wide settings readers).
 */
function novamira_pro_load_rank_math_abilities(): void
{
    $rm_dir = __DIR__ . '/abilities/rank-math/';
    require_once $rm_dir . 'helpers.php';
    require_once $rm_dir . 'check-setup.php';
    require_once $rm_dir . 'get-post-seo.php';
    require_once $rm_dir . 'get-term-seo.php';
    require_once $rm_dir . 'get-settings.php';
    require_once $rm_dir . 'edit-post-seo.php';
    require_once $rm_dir . 'edit-term-seo.php';

    // Schema abilities require the rich-snippet module. They also guard at
    // runtime via rm_module_active(); skipping registration keeps them out of
    // discover-abilities when the module is off.
    if (\Novamira\Pro\Abilities\RankMath\rm_module_active('rich-snippet')) {
        require_once $rm_dir . 'get-post-schema.php';
        require_once $rm_dir . 'edit-post-schema.php';
    }
}

/**
 * Load every All in One SEO ability file in the right order. AIOSEO_VERSION is
 * defined by both the free and Pro editions; Pro-only surfaces (term SEO,
 * redirects) gate again at runtime on aioseo()->pro.
 */
function novamira_pro_load_aioseo_abilities(): void
{
    $aioseo_dir = __DIR__ . '/abilities/aioseo/';
    require_once $aioseo_dir . 'helpers.php';
    require_once $aioseo_dir . 'check-setup.php';
    require_once $aioseo_dir . 'get-post-seo.php';
    require_once $aioseo_dir . 'get-term-seo.php';
    require_once $aioseo_dir . 'get-settings.php';
    require_once $aioseo_dir . 'edit-post-seo.php';
    require_once $aioseo_dir . 'edit-term-seo.php';
    require_once $aioseo_dir . 'get-post-schema.php';
    require_once $aioseo_dir . 'edit-post-schema.php';

    // Redirect abilities require AIOSEO Pro (the redirect model). The abilities
    // also guard at runtime via aio_supports_redirects(), but skipping
    // registration keeps them out of discover-abilities on the free edition.
    if (\Novamira\Pro\Abilities\AIOSEO\aio_supports_redirects()) {
        require_once $aioseo_dir . 'list-redirects.php';
        require_once $aioseo_dir . 'create-redirect.php';
        require_once $aioseo_dir . 'edit-redirect.php';
        require_once $aioseo_dir . 'delete-redirect.php';
    }
}

/**
 * Load every WPForms ability file in order. Sprint 1 is read-only (check-setup
 * + discovery). Write paths and entries (Pro-only) land in later sprints, gated
 * inside this loader.
 */
function novamira_pro_load_wpforms_abilities(): void
{
    require_once __DIR__ . '/abilities/wpforms/runtime.php';
    $dir = __DIR__ . '/abilities/wpforms/';

    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-forms.php';
    require_once $dir . 'get-form.php';
    require_once $dir . 'get-form-canonical.php';
    require_once $dir . 'get-field-types-schema.php';
    require_once $dir . 'get-field-type-schema.php';
    require_once $dir . 'create-form.php';
    require_once $dir . 'edit-form.php';
    require_once $dir . 'delete-form.php';
    require_once $dir . 'duplicate-form.php';
    require_once $dir . 'manage-fields.php';
    require_once $dir . 'manage-notifications.php';
    require_once $dir . 'manage-confirmations.php';
    require_once $dir . 'edit-form-settings.php';

    // Entries CRUD (Pro-only). Skipping registration on Lite keeps these
    // abilities out of discover-abilities — agents see an honest tool list
    // that matches the edition actually installed.
    if (\Novamira\Pro\Abilities\WPForms\wpforms_pro_active()) {
        require_once $dir . 'list-entries.php';
        require_once $dir . 'get-entry.php';
        require_once $dir . 'edit-entry.php';
        require_once $dir . 'delete-entry.php';
        require_once $dir . 'mark-entry.php';
    }
}

/**
 * Load every SeoPress ability file in the right order. SEOPRESS_VERSION is
 * defined by both the free and Pro editions (Pro hard-depends on free); Pro-only
 * surfaces (redirects, structured data) gate on SEOPRESS_PRO_VERSION.
 */
function novamira_pro_load_seopress_abilities(): void
{
    $sp_dir = __DIR__ . '/abilities/seopress/';
    require_once $sp_dir . 'helpers.php';
    require_once $sp_dir . 'check-setup.php';
    require_once $sp_dir . 'get-post-seo.php';
    require_once $sp_dir . 'get-term-seo.php';
    require_once $sp_dir . 'edit-post-seo.php';
    require_once $sp_dir . 'edit-term-seo.php';
    require_once $sp_dir . 'get-settings.php';

    // Structured-data (manual rich snippets) abilities require SeoPress Pro but
    // NOT the seopress_404 CPT — they read/write the `_seopress_pro_schemas_manual`
    // postmeta directly. They also guard at runtime via sp_detect_setup()['pro'],
    // but skipping registration keeps them out of discover-abilities on free.
    if (\Novamira\Pro\Abilities\SeoPress\sp_detect_setup()['pro']) {
        require_once $sp_dir . 'get-post-schema.php';
        require_once $sp_dir . 'set-post-schema-type.php';
        require_once $sp_dir . 'edit-post-schema.php';
        require_once $sp_dir . 'list-schema-fields.php';
        require_once $sp_dir . 'list-schema-templates.php';
    }

    // Redirect abilities require SeoPress Pro (the seopress_404 CPT redirect
    // manager). They also guard at runtime via sp_redirect_supported(), but
    // skipping registration keeps them out of discover-abilities on free.
    if (\Novamira\Pro\Abilities\SeoPress\sp_redirect_supported()) {
        require_once $sp_dir . 'list-redirects.php';
        require_once $sp_dir . 'get-redirect.php';
        require_once $sp_dir . 'create-redirect.php';
        require_once $sp_dir . 'edit-redirect.php';
        require_once $sp_dir . 'delete-redirect.php';
    }
}

/**
 * Load every Weglot ability file in the right order: setup discovery, settings
 * snapshot, supported-language catalog, cloud project status, language and
 * button configuration, URL rules, and the cloud sync / cache surfaces.
 *
 * Plan-gated surfaces (glossary, etc.) gate again at runtime on the plan
 * reported by check-setup via the user-info endpoint.
 */
function novamira_pro_load_weglot_abilities(): void
{
    $weglot_dir = __DIR__ . '/abilities/weglot/';
    require_once $weglot_dir . 'helpers.php';
    require_once $weglot_dir . 'check-setup.php';
    require_once $weglot_dir . 'get-settings.php';
    require_once $weglot_dir . 'list-languages.php';
    require_once $weglot_dir . 'get-status.php';
    require_once $weglot_dir . 'list-exclude-urls.php';
    require_once $weglot_dir . 'set-api-key.php';
    require_once $weglot_dir . 'set-original-language.php';
    require_once $weglot_dir . 'add-destination-language.php';
    require_once $weglot_dir . 'delete-destination-language.php';
    require_once $weglot_dir . 'edit-button-settings.php';
    require_once $weglot_dir . 'add-exclude-url.php';
    require_once $weglot_dir . 'delete-exclude-url.php';
    require_once $weglot_dir . 'list-translated-urls.php';
    require_once $weglot_dir . 'add-translated-url.php';
    require_once $weglot_dir . 'delete-translated-url.php';
    require_once $weglot_dir . 'push-options-to-cloud.php';
    require_once $weglot_dir . 'refresh-from-cloud.php';
    require_once $weglot_dir . 'clear-translation-cache.php';
    require_once $weglot_dir . 'get-translation-cache-state.php';
}

/**
 * Load every JetEngine ability file in the right order.
 *
 * Extracted out of the wp_abilities_api_init closure to keep that
 * registration handler small and to give the JetEngine block a single
 * named entry point.
 */
function novamira_pro_load_jetengine_abilities(): void
{
    $dir = __DIR__ . '/abilities/jetengine/';

    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-meta-boxes.php';
    require_once $dir . 'get-meta-box.php';
    require_once $dir . 'manage-meta-boxes.php';
    require_once $dir . 'list-options-pages.php';
    require_once $dir . 'get-options-page.php';
    require_once $dir . 'manage-options-pages.php';
    require_once $dir . 'list-post-types.php';
    require_once $dir . 'get-post-type.php';
    require_once $dir . 'manage-post-types.php';

    $modules = function_exists('jet_engine') ? jet_engine()->modules : null;
    if ($modules instanceof \Jet_Engine_Modules && $modules->is_module_active('custom-content-types')) {
        require_once $dir . 'list-ccts.php';
        require_once $dir . 'get-cct.php';
        require_once $dir . 'manage-ccts.php';
        require_once $dir . 'list-cct-records.php';
        require_once $dir . 'get-cct-record.php';
        require_once $dir . 'manage-cct-records.php';
    }
}

/**
 * Load every Meta Box ability file in the right order. Pro-extension-specific
 * sub-features (MB CPT, MB Settings Page, MB Relationships) are gated at the
 * loader level so abilities only register when their underlying extension is
 * actually active. MB Builder is gated callback-level inside the field-group
 * write abilities — read-side abilities are always available.
 */
function novamira_pro_load_metabox_abilities(): void
{
    $dir = __DIR__ . '/abilities/metabox/';

    require_once $dir . 'runtime.php';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-field-groups.php';
    require_once $dir . 'get-field-group-schema.php';
    require_once $dir . 'get-field-type-schema.php';
    require_once $dir . 'read-values.php';
    require_once $dir . 'write-values.php';
    require_once $dir . 'manage-field-groups.php';

    // MB Custom Post Type extension handles both CPTs and Custom Taxonomies.
    if (\Novamira\Pro\Abilities\Metabox\mb_cpt_available()) {
        require_once $dir . 'list-post-types.php';
        require_once $dir . 'get-post-type.php';
        require_once $dir . 'manage-post-types.php';
        require_once $dir . 'list-taxonomies.php';
        require_once $dir . 'get-taxonomy.php';
        require_once $dir . 'manage-taxonomies.php';
    }
    if (\Novamira\Pro\Abilities\Metabox\mb_settings_page_available()) {
        require_once $dir . 'list-settings-pages.php';
        require_once $dir . 'get-settings-page.php';
        require_once $dir . 'manage-settings-pages.php';
    }
    if (\Novamira\Pro\Abilities\Metabox\mb_relationships_available()) {
        require_once $dir . 'list-relationships.php';
        require_once $dir . 'get-relationship.php';
        require_once $dir . 'manage-relationships.php';
        require_once $dir . 'connect-objects.php';
        require_once $dir . 'disconnect-objects.php';
        require_once $dir . 'list-connected-objects.php';
    }
}

/**
 * Load every ACPT ability file in the right order. All v1 abilities register
 * uniformly (no per-feature sub-gating) because the v1 scope is the license-
 * agnostic core of ACPT — CPTs, taxonomies, meta groups, option pages.
 */
function novamira_pro_load_acpt_abilities(): void
{
    $dir = __DIR__ . '/abilities/acpt/';

    require_once $dir . 'runtime.php';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-post-types.php';
    require_once $dir . 'get-post-type.php';
    require_once $dir . 'manage-post-types.php';
    require_once $dir . 'list-taxonomies.php';
    require_once $dir . 'get-taxonomy.php';
    require_once $dir . 'manage-taxonomies.php';
    require_once $dir . 'list-field-groups.php';
    require_once $dir . 'get-field-group-schema.php';
    require_once $dir . 'get-field-type-schema.php';
    require_once $dir . 'read-values.php';
    require_once $dir . 'write-values.php';
    require_once $dir . 'field-group-names.php';
    require_once $dir . 'manage-field-groups.php';
    require_once $dir . 'list-option-pages.php';
    require_once $dir . 'get-option-page.php';
    require_once $dir . 'manage-option-pages.php';
}

/**
 * Load every Ninja Forms ability file. Ninja Forms ships its own 32
 * `ninjaforms/*` abilities at 3.14+, so Novamira Pro only adds the
 * two wrappers the cross-plugin migration framework needs: the
 * Phase-1 setup gate and the Phase-2 canonical reader. CRUD stays
 * with the native abilities.
 */
function novamira_pro_load_ninja_forms_abilities(): void
{
    require_once __DIR__ . '/abilities/ninja-forms/runtime.php';
    $dir = __DIR__ . '/abilities/ninja-forms/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'get-field-types-schema.php';
    require_once $dir . 'get-field-type-schema.php';
    require_once $dir . 'list-forms.php';
    require_once $dir . 'get-form.php';
    require_once $dir . 'create-form.php';
    require_once $dir . 'edit-form.php';
    require_once $dir . 'delete-form.php';
    require_once $dir . 'duplicate-form.php';
    require_once $dir . 'manage-fields.php';
    require_once $dir . 'manage-notifications.php';
    require_once $dir . 'list-submissions.php';
    require_once $dir . 'get-submission.php';
    require_once $dir . 'delete-submission.php';
    require_once $dir . 'get-form-canonical.php';
}

/**
 * Load every WPBakery ability file in the right order.
 */
function novamira_pro_load_wpbakery_abilities(): void
{
    $dir = __DIR__ . '/abilities/wpbakery/';
    require_once $dir . 'runtime.php';
    require_once $dir . 'schema-extractor.php';
    require_once $dir . 'helpers/parse.php';
    require_once $dir . 'helpers/serialize.php';
    require_once $dir . 'helpers/tree.php';
    require_once $dir . 'helpers/post-io.php';
    require_once $dir . 'helpers/templates.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-elements.php';
    require_once $dir . 'get-element-schema.php';
    require_once $dir . 'list-grid-item-elements.php';
    require_once $dir . 'get-content.php';
    require_once $dir . 'inspect-page.php';
    require_once $dir . 'set-content.php';
    require_once $dir . 'add-element.php';
    require_once $dir . 'edit-element.php';
    require_once $dir . 'edit-elements.php';
    require_once $dir . 'delete-element.php';
    require_once $dir . 'move-element.php';
    require_once $dir . 'get-settings.php';
    require_once $dir . 'set-settings.php';
    require_once $dir . 'manage-templates.php';
    require_once $dir . 'apply-template.php';

    // WPBakery registers the vc_grid_item CPT only in admin. Mirror it on
    // non-admin request surfaces (REST, MCP, wp-cli) so the canonical
    // create-post(post_type=vc_grid_item) → set-content workflow succeeds
    // regardless of context. wp_abilities_api_init fires after init, so we
    // register directly instead of hooking another init callback.
    \Novamira\Pro\Abilities\WPBakery\wpb_register_grid_item_cpt();
}

/**
 * Breakdance abilities loader. Gated on bd_plugin_available() — every
 * sub-group below can additionally gate on license mode (pro vs free)
 * or on individual subsystems (templates, dynamic data, …) as the
 * scope grows.
 */
function novamira_pro_load_breakdance_abilities(): void
{
    require_once __DIR__ . '/abilities/breakdance/runtime.php';
    $dir = __DIR__ . '/abilities/breakdance/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'query-helpers.php';
    require_once $dir . 'tree-helpers.php';
    require_once $dir . 'design-helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-templates.php';
    require_once $dir . 'get-template.php';
    require_once $dir . 'create-template.php';
    require_once $dir . 'edit-template.php';
    require_once $dir . 'delete-template.php';
    require_once $dir . 'manage-template-conditions.php';
    require_once $dir . 'get-element-tree.php';
    require_once $dir . 'get-element.php';
    require_once $dir . 'list-element-types.php';
    require_once $dir . 'get-element-schema.php';
    require_once $dir . 'add-element.php';
    require_once $dir . 'set-content.php';
    require_once $dir . 'edit-element.php';
    require_once $dir . 'delete-element.php';
    require_once $dir . 'move-element.php';
    require_once $dir . 'list-variables.php';
    require_once $dir . 'create-variable.php';
    require_once $dir . 'edit-variable.php';
    require_once $dir . 'delete-variable.php';
    require_once $dir . 'manage-global-classes.php';
    require_once $dir . 'manage-global-settings.php';
    require_once $dir . 'list-dynamic-data-fields.php';
    require_once $dir . 'get-dynamic-data-field.php';
    require_once $dir . 'manage-form-submissions.php';
}

/**
 * Oxygen 6 abilities loader. Gated on `oxygen_plugin_available()` — the
 * gate verifies `BREAKDANCE_MODE === 'oxygen'` plus the version floor,
 * so this loader is mutually exclusive with the Breakdance loader (only
 * one of the two can run per request). Oxygen shares the Soflyy engine
 * with Breakdance but is registered under its own `oxygen` category with
 * its own `novamira/oxygen-*` slugs so the persona sees a coherent
 * Oxygen surface.
 */
function novamira_pro_load_oxygen_abilities(): void
{
    require_once __DIR__ . '/abilities/oxygen/runtime.php';
    $dir = __DIR__ . '/abilities/oxygen/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'tree-helpers.php';
    require_once $dir . 'design-helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-templates.php';
    require_once $dir . 'get-template.php';
    require_once $dir . 'create-template.php';
    require_once $dir . 'edit-template.php';
    require_once $dir . 'delete-template.php';
    require_once $dir . 'manage-template-conditions.php';
    require_once $dir . 'list-element-types.php';
    require_once $dir . 'get-element-schema.php';
    require_once $dir . 'get-element-tree.php';
    require_once $dir . 'get-element.php';
    require_once $dir . 'add-element.php';
    require_once $dir . 'edit-element.php';
    require_once $dir . 'delete-element.php';
    require_once $dir . 'move-element.php';
    require_once $dir . 'set-content.php';
    require_once $dir . 'set-loop.php';
    require_once $dir . 'list-variables.php';
    require_once $dir . 'create-variable.php';
    require_once $dir . 'edit-variable.php';
    require_once $dir . 'delete-variable.php';
    require_once $dir . 'manage-global-classes.php';
    require_once $dir . 'apply-global-class.php';
    require_once $dir . 'manage-global-settings.php';
    require_once $dir . 'list-dynamic-data-fields.php';
    require_once $dir . 'get-dynamic-data-field.php';
    require_once $dir . 'apply-dynamic-data.php';
    require_once $dir . 'manage-form-submissions.php';
}

/**
 * Load every Code Snippets ability file in the right order. The runtime
 * helper guards the per-type surface (css / js / cond are Pro-only).
 */
function novamira_pro_load_code_snippets_abilities(): void
{
    require_once __DIR__ . '/abilities/code-snippets/runtime.php';
    $dir = __DIR__ . '/abilities/code-snippets/';

    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'get-types-schema.php';
    require_once $dir . 'get-conditions-schema.php';
    require_once $dir . 'list-snippets.php';
    require_once $dir . 'get-snippet.php';
    require_once $dir . 'create-snippet.php';
    require_once $dir . 'edit-snippet.php';
    require_once $dir . 'delete-snippet.php';
    require_once $dir . 'enable-snippet.php';
    require_once $dir . 'disable-snippet.php';
    require_once $dir . 'validate-snippet.php';
}

/**
 * Load every Dynamic Content for Elementor ability file. All of them gate on
 * `dce_min_runtime_available()` (plugin active and at or above
 * `NOVAMIRA_PRO_DYNAMIC_CONTENT_FOR_ELEMENTOR_MIN_VERSION`). The surface is deliberately
 * limited to the HTML Templates post type, which is the one thing this plugin
 * owns that no Elementor ability can reach: its widgets are ordinary Elementor
 * widgets and its form additions are ordinary Elementor controls.
 */
function novamira_pro_load_dynamic_content_for_elementor_abilities(): void
{
    $dir = __DIR__ . '/abilities/dynamic-content-for-elementor/';

    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-html-templates.php';
    require_once $dir . 'get-html-template.php';
    require_once $dir . 'create-html-template.php';
    require_once $dir . 'edit-html-template.php';
    require_once $dir . 'delete-html-template.php';
}

/**
 * Load every Dynamic Shortcodes ability file. The whole specialization is
 * gated on `dsh_min_runtime_available()` (DSH active and at or above
 * `NOVAMIRA_PRO_DYNAMIC_SHORTCODES_MIN_VERSION`) by the manifest engine, so
 * below the floor this loader is never called and no dynamic-shortcodes
 * ability (not even check-setup) is registered.
 */
function novamira_pro_load_dynamic_shortcodes_abilities(): void
{
    $dir = __DIR__ . '/abilities/dynamic-shortcodes/';

    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'validate-shortcode.php';
    require_once $dir . 'compose-shortcode.php';
    require_once $dir . 'evaluate-shortcode.php';
    require_once $dir . 'get-context.php';
    require_once $dir . 'list-power-shortcodes.php';
    require_once $dir . 'create-power-shortcode.php';
    require_once $dir . 'edit-power-shortcode.php';
    require_once $dir . 'delete-power-shortcode.php';
}

/**
 * Load every Contact Form 7 ability file. All abilities gate on
 * `cf7_runtime_available()` (CF7 active + at or above
 * `NOVAMIRA_PRO_CF7_MIN_VERSION`). Submissions are intentionally NOT
 * covered in this surface — CF7 doesn't persist them and Flamingo is a
 * separate plugin.
 */
function novamira_pro_load_cf7_abilities(): void
{
    require_once __DIR__ . '/abilities/cf7/runtime.php';
    $dir = __DIR__ . '/abilities/cf7/';

    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'get-tags-schema.php';
    require_once $dir . 'list-forms.php';
    require_once $dir . 'get-form.php';
    require_once $dir . 'get-form-canonical.php';
    require_once $dir . 'create-form.php';
    require_once $dir . 'edit-form.php';
    require_once $dir . 'delete-form.php';
    require_once $dir . 'duplicate-form.php';
}

/**
 * Load every Kadence theme ability file. All abilities gate on the
 * `kadence_theme_available()` runtime check; per-ability sub-gating
 * (e.g. an ability that touches kadence_form CPT) happens inside the
 * ability itself so a missing module returns a clean error rather than
 * a fatal at load time.
 */
function novamira_pro_load_kadence_abilities(): void
{
    require_once __DIR__ . '/abilities/kadence/runtime.php';
    $dir = __DIR__ . '/abilities/kadence/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-customizer-settings.php';
    require_once $dir . 'get-settings.php';
    require_once $dir . 'edit-settings.php';
    require_once $dir . 'get-theme-json.php';
}

/**
 * Load every Kadence Blocks introspection ability file. Reads the WP block
 * registry to surface the catalogue and per-block attribute schemas;
 * never writes post_content. Reads and writes of block trees on a post
 * go through the `novamira/gutenberg-*` abilities exposed by the base
 * Novamira plugin.
 */
function novamira_pro_load_kadenceblocks_abilities(): void
{
    require_once __DIR__ . '/abilities/kadenceblocks/runtime.php';
    $dir = __DIR__ . '/abilities/kadenceblocks/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-blocks.php';
    require_once $dir . 'get-block-schema.php';
}

/**
 * Load every Spectra One theme ability file. All abilities gate on the
 * `spectra_one_theme_available()` runtime check (theme active at ≥ min
 * version). The Spectra plugin (UAGB) bridge ability additionally sub-gates
 * on `spectra_plugin_available()` at registration time, keeping it out of
 * `discover-abilities` on installs where the companion plugin is absent so
 * the agent's tool list matches the actual capability surface.
 *
 * Sprint 0 is infrastructure-only — it loads the shared runtime + helpers
 * so later sprints can `require_once` individual ability files without
 * duplicating the setup.
 */
function novamira_pro_load_spectra_one_abilities(): void
{
    require_once __DIR__ . '/abilities/spectra-one/runtime.php';
    $dir = __DIR__ . '/abilities/spectra-one/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'get-theme-info.php';
    require_once $dir . 'list-hooks.php';
    require_once $dir . 'list-style-variations.php';
    require_once $dir . 'list-templates.php';
    require_once $dir . 'get-color-palette.php';
    require_once $dir . 'get-typography-settings.php';
    require_once $dir . 'get-theme-settings.php';
    require_once $dir . 'list-patterns.php';
    require_once $dir . 'set-color-palette.php';
    require_once $dir . 'set-typography.php';
    require_once $dir . 'set-theme-settings.php';
    require_once $dir . 'apply-style-variation.php';
    require_once $dir . 'add-font-family.php';
    require_once $dir . 'edit-navigation.php';
    require_once $dir . 'edit-template-part.php';
    require_once $dir . 'get-post-display-settings.php';
    require_once $dir . 'list-posts-with-display-overrides.php';
    require_once $dir . 'set-post-display-settings.php';
    require_once $dir . 'export-design.php';
    require_once $dir . 'apply-design.php';
    // list-spectra-blocks is gated on the companion Spectra (UAGB)
    // plugin — register only when the plugin is active so the ability
    // never surfaces on installs that would only return
    // spectra_one_requires_plugin.
    if (\Novamira\Pro\Abilities\SpectraOne\spectra_plugin_available()) {
        require_once $dir . 'list-spectra-blocks.php';
    }
}

/**
 * Load every Spectra (UAGB / Ultimate Addons for Gutenberg) ability file. The
 * whole surface gates on `spectra_plugin_available()` (UAGB active at ≥
 * `NOVAMIRA_PRO_SPECTRA_MIN_VERSION`). The Pro sub-loader additionally gates on
 * `spectra_pro_active()`, keeping Pro abilities out of `discover-abilities` on
 * installs that only have the free UAGB — the agent's tool list stays honest.
 *
 * S1 (Discovery + catalogue) is read-only and validates the runtime helpers +
 * check-setup contract before any write path lands in S2.
 */
function novamira_pro_load_spectra_abilities(): void
{
    require_once __DIR__ . '/abilities/spectra/runtime.php';
    $dir = __DIR__ . '/abilities/spectra/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';

    // S1 — Discovery + catalogue.
    require_once $dir . 'list-blocks.php';
    require_once $dir . 'get-block-schema.php';
    require_once $dir . 'get-settings.php';
    require_once $dir . 'list-popups.php';

    // S2 — Popup CRUD + settings write + block activation.
    require_once $dir . 'get-popup.php';
    require_once $dir . 'create-popup.php';
    require_once $dir . 'edit-popup.php';
    require_once $dir . 'delete-popup.php';
    require_once $dir . 'duplicate-popup.php';
    require_once $dir . 'enable-popup.php';
    require_once $dir . 'disable-popup.php';
    require_once $dir . 'set-editor-settings.php';
    require_once $dir . 'set-block-activation.php';

    // S3 — Google Fonts + form-block discovery.
    require_once $dir . 'list-google-fonts.php';
    require_once $dir . 'set-google-fonts-mode.php';
    require_once $dir . 'list-form-blocks.php';

    // Pro layer — separate spectra-pro/ plugin. Loaded only when active + at
    // or above `NOVAMIRA_PRO_SPECTRA_PRO_MIN_VERSION`, so the free tool
    // surface never advertises Pro-only abilities.
    if (\Novamira\Pro\Abilities\Spectra\spectra_pro_active()) {
        $pro_dir = __DIR__ . '/abilities/spectra/pro/';
        require_once $pro_dir . 'runtime.php';

        // S4 — Pro layer discovery + popup display conditions.
        require_once $pro_dir . 'check-setup.php';
        require_once $pro_dir . 'list-popup-conditions.php';
        require_once $pro_dir . 'set-popup-conditions.php';
    }
}

/**
 * Load every Spectra Blocks ability file. Spectra Blocks is Brainstorm
 * Force's ground-up rewrite of the block library (free `spectra-blocks` +
 * `spectra-blocks-pro`, namespaces `spectra/*` / `spectra-pro/*`) — a
 * different plugin pair from the legacy Spectra (UAGB) covered by
 * `novamira_pro_load_spectra_abilities()` above; the two specializations
 * gate independently and never coexist at runtime (Spectra Blocks refuses
 * to initialize next to the UAGB spectra-v3 bootstrap).
 *
 * The whole surface gates on `spectra_blocks_plugin_available()` (free
 * plugin initialized at ≥ NOVAMIRA_PRO_SPECTRA_BLOCKS_MIN_VERSION) at the
 * manifest level, so unavailable installs never see these abilities in
 * `discover-abilities`. S1 (baseline + discovery) is read-only and has no
 * Pro-only ability — `spectra-pro/*` blocks surface through list-blocks
 * whenever the Pro add-on registers them (registration is not
 * license-gated), and check-setup reports the Pro state; a Pro sub-loader
 * gated on `spectra_blocks_pro_active()` lands with the first Pro-only
 * ability.
 */
function novamira_pro_load_spectra_blocks_abilities(): void
{
    require_once __DIR__ . '/abilities/spectra-blocks/runtime.php';
    $dir = __DIR__ . '/abilities/spectra-blocks/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';

    // S1 — Discovery + catalogue (read-only).
    require_once $dir . 'list-blocks.php';
    require_once $dir . 'get-settings.php';
    require_once $dir . 'list-popups.php';
    require_once $dir . 'get-popup.php';
}

/**
 * Load every GeneratePress ability file. Group A (theme settings) runs
 * on any GeneratePress install. Groups B (Elements), C (Site Library)
 * and D (Font Library) are sub-gated by the matching GP Premium module
 * detector and conditionally required.
 */
function novamira_pro_load_generatepress_abilities(): void
{
    require_once __DIR__ . '/abilities/generatepress/runtime.php';
    $dir = __DIR__ . '/abilities/generatepress/';

    // Group A — Theme Settings (no GP Premium required).
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'get-settings.php';
    require_once $dir . 'set-settings.php';
    require_once $dir . 'get-defaults.php';
    require_once $dir . 'get-layout.php';
    require_once $dir . 'list-hooks.php';

    // Group B — Elements CRUD + conditions (requires GP Premium Elements).
    if (\Novamira\Pro\Abilities\GeneratePress\gp_elements_active()) {
        require_once $dir . 'list-elements.php';
        require_once $dir . 'get-element.php';
        require_once $dir . 'create-element.php';
        require_once $dir . 'edit-element.php';
        require_once $dir . 'delete-element.php';
        require_once $dir . 'manage-element-conditions.php';
    }

    // Group C — Site Library (requires GP Premium Site Library module).
    if (\Novamira\Pro\Abilities\GeneratePress\gp_site_library_active()) {
        require_once $dir . 'list-sites.php';
        require_once $dir . 'get-site.php';
        require_once $dir . 'apply-site.php';
        require_once $dir . 'restore-site.php';
    }

    // Group D — Font Library (requires GP Premium Font Library module).
    if (\Novamira\Pro\Abilities\GeneratePress\gp_font_library_active()) {
        require_once $dir . 'list-fonts.php';
        require_once $dir . 'add-font.php';
        require_once $dir . 'delete-font.php';
    }

    // Group E — Utility / introspection (theme always-on).
    require_once $dir . 'list-modules.php';
    require_once $dir . 'get-css-output.php';
    require_once $dir . 'get-customizer-schema.php';
}

/**
 * Load every GenerateBlocks ability file. Introspection-only — these
 * abilities read the WP block registry to surface the catalogue and
 * per-block attribute schemas; they never write post_content. Reads
 * and writes of block trees on a post go through the
 * `novamira/gutenberg-*` abilities exposed by the base Novamira plugin.
 */
function novamira_pro_load_generateblocks_abilities(): void
{
    require_once __DIR__ . '/abilities/generateblocks/runtime.php';
    $dir = __DIR__ . '/abilities/generateblocks/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-blocks.php';
    require_once $dir . 'get-block-schema.php';
}

/**
 * Load every OceanWP ability file. All abilities gate on
 * `oceanwp_theme_available()` (OceanWP active + at or above
 * NOVAMIRA_PRO_OCEANWP_THEME_MIN_VERSION). Ocean-Extra-gated abilities
 * (S4: Library + Modules) sub-gate on `ocean_extra_available()` so they
 * only register when the companion plugin is present and at or above its
 * floor — agents on a theme-only install see an honest tool list.
 */
function novamira_pro_load_oceanwp_abilities(): void
{
    $dir = __DIR__ . '/abilities/oceanwp/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-customizer-panels.php';
    require_once $dir . 'get-settings.php';
    require_once $dir . 'list-hooks.php';
    require_once $dir . 'set-customizer-settings.php';
    require_once $dir . 'set-header-config.php';
    require_once $dir . 'set-typography-preset.php';
    require_once $dir . 'get-page-overrides.php';
    require_once $dir . 'set-page-overrides.php';
    require_once $dir . 'set-woo-features.php';
    require_once $dir . 'list-library-items.php';
    require_once $dir . 'apply-library-template.php';
    require_once $dir . 'list-modules.php';
    require_once $dir . 'enable-module.php';
    require_once $dir . 'disable-module.php';
}

/**
 * Load WooCommerce abilities. Driven by the specialization manifest's
 * `woocommerce` loader entry, gated on the version floor
 * (wc_min_runtime_available()).
 */
function novamira_pro_load_woocommerce_abilities(): void
{
    require_once __DIR__ . '/abilities/woocommerce/helpers.php';
    require_once __DIR__ . '/abilities/woocommerce/runtime.php';
    $wc_dir = __DIR__ . '/abilities/woocommerce/';
    foreach ([
        'check-setup.php',
        'list-products.php',
        'get-product.php',
        'list-product-categories.php',
        'list-product-tags.php',
        'list-product-attributes.php',
        'list-product-types.php',
        'list-product-statuses.php',
        'get-store-settings.php',
        'create-product.php',
        'edit-product.php',
        'delete-product.php',
        'list-product-variations.php',
        'get-product-variation.php',
        'create-product-variation.php',
        'edit-product-variation.php',
        'delete-product-variation.php',
        'create-product-category.php',
        'edit-product-category.php',
        'delete-product-category.php',
        'create-product-tag.php',
        'edit-product-tag.php',
        'delete-product-tag.php',
        'list-product-attribute-terms.php',
        'list-product-shipping-classes.php',
        'create-product-attribute.php',
        'edit-product-attribute.php',
        'delete-product-attribute.php',
        'create-product-attribute-term.php',
        'edit-product-attribute-term.php',
        'delete-product-attribute-term.php',
        'create-product-shipping-class.php',
        'edit-product-shipping-class.php',
        'delete-product-shipping-class.php',
    ] as $file) {
        $path = $wc_dir . $file;
        if (file_exists($path)) {
            require_once $path;
        }
    }
}

/**
 * Load every Formidable Forms ability file. Group A (baseline + discovery)
 * runs on any Formidable install at the minimum version. Group B (form
 * CRUD) reads/writes the wp_frm_forms row. Pro-only surfaces (applications,
 * Pro-only payment ops like cancel-subscription) are sub-gated on
 * frm_pro_active(); the read-only Stripe Lite payment surfaces are gated
 * on frm_payments_supported() so they ship on free installs that have
 * Stripe Lite configured (wp_frm_payments / wp_frm_subscriptions present).
 *
 * Each ability is registered in its own file to keep input schemas sharp
 * and discoverability clean. Files that host multiple closely-related abilities
 * (manage-fields.php, manage-notifications.php) are named after the noun
 * and keep every wp_register_ability() call together.
 */
function novamira_pro_load_formidable_abilities(): void
{
    require_once __DIR__ . '/abilities/formidable/runtime.php';
    $dir = __DIR__ . '/abilities/formidable/';

    // Discovery surface is loaded on `frm_plugin_present()` (any version) so
    // an install with an under-min Formidable can still report state via
    // check-setup. Every OTHER ability is loaded only on `frm_runtime_available()`
    // (≥ min version) — they would crash or behave incorrectly on a 5.x install.
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';

    if (!\Novamira\Pro\Abilities\Formidable\frm_runtime_available()) {
        return;
    }

    // S1 — remaining discovery surfaces (gated on min version).
    require_once $dir . 'list-forms.php';
    require_once $dir . 'get-form.php';
    require_once $dir . 'get-form-canonical.php';
    require_once $dir . 'get-field-types-schema.php';

    // S2 — form CRUD.
    require_once $dir . 'create-form.php';
    require_once $dir . 'edit-form.php';
    require_once $dir . 'delete-form.php';

    // S3 — field CRUD (3 abilities in one file).
    require_once $dir . 'manage-fields.php';

    // S4 — notifications (5 email-action abilities in one file) +
    // on-submit action (one PUT-style ability).
    require_once $dir . 'manage-notifications.php';
    require_once $dir . 'set-on-submit-action.php';

    // S5 — settings + generic actions read/delete + styles.
    require_once $dir . 'edit-form-settings.php';
    require_once $dir . 'manage-form-actions.php';
    require_once $dir . 'list-styles.php';
    require_once $dir . 'apply-style.php';

    // S6 — entries CRUD (5 abilities in one file).
    require_once $dir . 'manage-entries.php';

    // S7 — applications (6 abilities, Pro-only — sub-gated inside the file).
    if (\Novamira\Pro\Abilities\Formidable\frm_pro_active()) {
        require_once $dir . 'manage-applications.php';
    }

    // S8 — payments (5 abilities, Stripe Lite + Pro). The first 4 read
    // surfaces ship as soon as the wp_frm_payments table exists; cancel
    // additionally gates on Pro inside the callback.
    if (\Novamira\Pro\Abilities\Formidable\frm_payments_supported()) {
        require_once $dir . 'manage-payments.php';
    }
}

/**
 * Load every Gravity Forms ability file in order. Sprint 1 is read-only
 * (check-setup + discovery). Write paths land in later sprints, gated inside
 * this loader.
 */
function novamira_pro_load_gravityforms_abilities(): void
{
    require_once __DIR__ . '/abilities/gravityforms/runtime.php';
    $dir = __DIR__ . '/abilities/gravityforms/';

    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-forms.php';
    require_once $dir . 'get-form.php';
    require_once $dir . 'get-form-canonical.php';
    require_once $dir . 'get-field-types-schema.php';
    require_once $dir . 'get-field-type-schema.php';
    require_once $dir . 'create-form.php';
    require_once $dir . 'edit-form.php';
    require_once $dir . 'edit-form-settings.php';
    require_once $dir . 'delete-form.php';
    require_once $dir . 'duplicate-form.php';
    require_once $dir . 'manage-fields.php';
    require_once $dir . 'manage-notifications.php';
    require_once $dir . 'manage-confirmations.php';
    require_once $dir . 'list-entries.php';
    require_once $dir . 'get-entry.php';
    require_once $dir . 'edit-entry.php';
    require_once $dir . 'mark-entry.php';
    require_once $dir . 'delete-entry.php';
}

/**
 * Load every Fluent Forms ability file in the right order. `FLUENTFORM` is
 * defined by both the free and Pro editions (Pro hard-depends on free), so
 * the runtime gate covers both; Pro-only surfaces gate AGAIN at registration
 * inside this loader on `ff_pro_runtime_available()`, keeping them out of
 * `discover-abilities` on free installs.
 */
function novamira_pro_load_fluentforms_abilities(): void
{
    require_once __DIR__ . '/abilities/fluentforms/runtime.php';
    $dir = __DIR__ . '/abilities/fluentforms/';

    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'get-field-types-schema.php';
    require_once $dir . 'list-forms.php';
    require_once $dir . 'get-form.php';
    require_once $dir . 'get-form-canonical.php';
    require_once $dir . 'create-form.php';
    require_once $dir . 'edit-form.php';
    require_once $dir . 'delete-form.php';
    require_once $dir . 'duplicate-form.php';
    require_once $dir . 'manage-fields.php';
    require_once $dir . 'list-submissions.php';
    require_once $dir . 'get-submission.php';
    require_once $dir . 'mark-submission.php';
    require_once $dir . 'delete-submission.php';
    require_once $dir . 'export-submissions.php';
    require_once $dir . 'manage-form-settings.php';
    require_once $dir . 'manage-notifications.php';
    require_once $dir . 'manage-confirmations.php';

    // Sprint 4 — Pro CRUD. Each file early-returns at the top when
    // ff_pro_runtime_available() is false, so the abilities never
    // register on a free FF install. Including the files is still safe
    // because the Pro model stubs cover the worst case where the file
    // is loaded but the runtime isn't there.
    require_once $dir . 'list-transactions.php';
    require_once $dir . 'get-transaction.php';
    require_once $dir . 'list-subscriptions.php';
    require_once $dir . 'get-subscription.php';
    require_once $dir . 'cancel-subscription.php';
    require_once $dir . 'list-draft-submissions.php';
    require_once $dir . 'delete-draft-submission.php';

    // Sprint 5 (optional) — Pro-only quiz + integrations index.
    require_once $dir . 'list-integrations.php';
    require_once $dir . 'list-quiz-questions.php';
    require_once $dir . 'get-quiz-report.php';
}

/**
 * Load Bricks Builder ability files. Called by the manifest loader; caller
 * must gate on defined('BRICKS_VERSION').
 */
function novamira_pro_load_bricks_abilities(): void
{
    $bricks_dir = __DIR__ . '/abilities/bricks/';
    require_once $bricks_dir . 'helpers.php';
    require_once $bricks_dir . 'list-elements.php';
    require_once $bricks_dir . 'list-settings.php';
    require_once $bricks_dir . 'get-content.php';
    require_once $bricks_dir . 'set-content.php';
    require_once $bricks_dir . 'insert-content.php';
    require_once $bricks_dir . 'remove-content.php';
    require_once $bricks_dir . 'patch-elements.php';
    require_once $bricks_dir . 'list-templates.php';
    require_once $bricks_dir . 'create-template.php';
    require_once $bricks_dir . 'manage-template-conditions.php';
    require_once $bricks_dir . 'get-settings.php';
    require_once $bricks_dir . 'set-settings.php';
    require_once $bricks_dir . 'set-post-types.php';
    require_once $bricks_dir . 'manage-dynamic-data.php';
    require_once $bricks_dir . 'manage-components.php';
    require_once $bricks_dir . 'manage-interactions.php';
    require_once $bricks_dir . 'manage-color-palette.php';
    require_once $bricks_dir . 'manage-global-classes.php';
    require_once $bricks_dir . 'manage-variables.php';
    require_once $bricks_dir . 'manage-theme-styles.php';
    require_once $bricks_dir . 'check-setup.php';
    require_once $bricks_dir . 'list-breakpoints.php';
    require_once $bricks_dir . 'get-element.php';
    require_once $bricks_dir . 'get-design-system.php';
    require_once $bricks_dir . 'read-css.php';
    require_once $bricks_dir . 'render-check.php';
}

/**
 * Load Bricksforge abilities. Gated on
 * \Novamira\Pro\Abilities\Bricksforge\brf_runtime_available().
 */
function novamira_pro_load_bricksforge_abilities(): void
{
    $dir = __DIR__ . '/abilities/bricksforge/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-tools.php';
    require_once $dir . 'list-custom-elements.php';
    require_once $dir . 'enable-tool.php';
    require_once $dir . 'disable-tool.php';
    require_once $dir . 'enable-element.php';
    require_once $dir . 'disable-element.php';
    require_once $dir . 'manage-popups.php';
    require_once $dir . 'manage-email-templates.php';
    require_once $dir . 'manage-email-themes.php';
}

/**
 * Load Beaver Builder abilities. Gated on
 * \Novamira\Pro\Abilities\BeaverBuilder\bb_runtime_available().
 */
function novamira_pro_load_beaver_builder_abilities(): void
{
    require_once __DIR__ . '/abilities/beaver-builder/runtime.php';
    $dir = __DIR__ . '/abilities/beaver-builder/';
    require_once $dir . 'helpers.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-modules.php';
    require_once $dir . 'get-module.php';
    require_once $dir . 'get-structure-schema.php';
    require_once $dir . 'list-layouts.php';
    require_once $dir . 'get-layout.php';
    require_once $dir . 'set-layout.php';
    require_once $dir . 'add-node.php';
    require_once $dir . 'edit-node.php';
    require_once $dir . 'delete-node.php';
    require_once $dir . 'move-node.php';
    require_once $dir . 'manage-global-colors.php';
    require_once $dir . 'manage-global-typography.php';
    require_once $dir . 'manage-templates.php';
}

/**
 * Load the always-available core abilities (generic WordPress, Skills) and
 * the optional Memory abilities.
 */
/**
 * Load the generic WordPress abilities (always available). Gating is handled by
 * the manifest entry; this loader only requires the files.
 */
function novamira_pro_load_wordpress_abilities(): void
{
    $wordpress_dir = __DIR__ . '/abilities/wordpress/';
    require_once $wordpress_dir . 'helpers.php';
    require_once $wordpress_dir . 'create-post.php';
    require_once $wordpress_dir . 'update-post.php';
    require_once $wordpress_dir . 'delete-post.php';
}

/**
 * Load the canonical `novamira/skill-get` ability. `bootstrap.php` (skill_scan +
 * parser) and `source-filter.php` (the `novamira_skill_lookup_sources` callback)
 * are loaded earlier at `plugins_loaded` priority 20 so the Skills admin page can
 * see Pro's catalog even when nothing has touched the abilities registry yet.
 *
 * The skip when Novamira free owns the ability itself lives in the manifest entry
 * `load_gate` — free routes Pro lookups through the lookup-sources filter that
 * source-filter.php already feeds.
 */
function novamira_pro_load_skill_abilities(): void
{
    require_once __DIR__ . '/abilities/skill/get.php';
}

/**
 * Load the memory abilities (backed by a custom post type). The
 * `nvp_memory_enabled` toggle is enforced by the manifest entry `gate`; this
 * loader only requires the files.
 */
function novamira_pro_load_memory_abilities(): void
{
    $memory_dir = __DIR__ . '/abilities/memory/';
    require_once $memory_dir . 'list.php';
    require_once $memory_dir . 'get.php';
    require_once $memory_dir . 'save.php';
    require_once $memory_dir . 'delete.php';
}

/**
 * Load every Mosaic ability for the current request. Gated by
 * mo_runtime_available() at the caller — this function trusts that the
 * runtime + schema are ready.
 */
function novamira_pro_load_mosaic_abilities(): void
{
    require_once __DIR__ . '/abilities/mosaic/runtime.php';
    $dir = __DIR__ . '/abilities/mosaic/';

    require_once $dir . 'helpers.php';
    require_once $dir . 'class-model.php';
    require_once $dir . 'element-class-inputs.php';
    require_once $dir . 'check-setup.php';
    require_once $dir . 'check-design-system.php';
    require_once $dir . 'list-themes.php';
    require_once $dir . 'list-templates.php';
    require_once $dir . 'list-components.php';
    require_once $dir . 'list-collections.php';
    require_once $dir . 'list-variables.php';
    require_once $dir . 'list-classes.php';
    require_once $dir . 'list-variants.php';
    require_once $dir . 'get-theme.php';
    require_once $dir . 'get-template.php';
    require_once $dir . 'get-element-tree.php';
    require_once $dir . 'manage-elements.php';
    require_once $dir . 'edit-component-instance-override.php';
    require_once $dir . 'manage-templates.php';
    require_once $dir . 'manage-template-assigns.php';
    require_once $dir . 'manage-theme-settings.php';
    require_once $dir . 'manage-classes.php';
    require_once $dir . 'manage-sub-classes.php';
    require_once $dir . 'manage-variant-sub-classes.php';
    require_once $dir . 'manage-collections.php';
    require_once $dir . 'manage-variables.php';
}

/**
 * Load the Divi 5 builder ability files. Caller must gate on
 * {@see novamira_pro_divi_available()}.
 */
function novamira_pro_load_divi_abilities(): void
{
    $divi_dir = __DIR__ . '/abilities/divi/';
    require_once $divi_dir . 'discovery-instructions.php';
    require_once $divi_dir . 'helpers.php';
    require_once $divi_dir . 'tree.php';
    require_once $divi_dir . 'check-setup.php';
    require_once $divi_dir . 'list-modules.php';
    require_once $divi_dir . 'get-module-schema.php';
    require_once $divi_dir . 'get-content.php';
    require_once $divi_dir . 'set-content.php';
    require_once $divi_dir . 'add-module.php';
    require_once $divi_dir . 'edit-module.php';
    require_once $divi_dir . 'delete-module.php';
    require_once $divi_dir . 'move-module.php';
    require_once $divi_dir . 'list-dynamic-sources.php';
    require_once $divi_dir . 'apply-dynamic-content.php';
    require_once $divi_dir . 'clear-dynamic-content.php';
    require_once $divi_dir . 'list-loop-query-types.php';
    require_once $divi_dir . 'enable-loop.php';
    require_once $divi_dir . 'disable-loop.php';
    require_once $divi_dir . 'edit-loop.php';
    require_once $divi_dir . 'manage-global-presets.php';
    require_once $divi_dir . 'manage-global-colors.php';
    require_once $divi_dir . 'manage-variables.php';
    require_once $divi_dir . 'manage-global-fonts.php';
    require_once $divi_dir . 'manage-display-conditions.php';
    require_once $divi_dir . 'manage-interactions.php';
    require_once $divi_dir . 'manage-library.php';
    require_once $divi_dir . 'get-style-schema.php';
    require_once $divi_dir . 'list-theme-builder-templates.php';
    require_once $divi_dir . 'manage-theme-builder.php';
}

/**
 * Load the Avada theme ability files. Caller must gate on
 * {@see \Novamira\Pro\Abilities\Avada\avada_theme_available()}.
 *
 * Theme-side layer only (Fusion Settings, AWB global tokens, Portfolio + FAQ
 * CPTs, Fusion hook discovery). A separate Fusion Builder plugin-scoped
 * sub-loader (element tree CRUD, Template Builder, Fusion Forms) is not
 * implemented today and is intentionally not scaffolded here — no dangling
 * conditional includes.
 */
function novamira_pro_load_avada_abilities(): void
{
    $avada_dir = __DIR__ . '/abilities/avada/';
    require_once $avada_dir . 'runtime.php';
    require_once $avada_dir . 'helpers.php';
    require_once $avada_dir . 'check-setup.php';
    require_once $avada_dir . 'list-hooks.php';
    require_once $avada_dir . 'get-theme-options.php';
    require_once $avada_dir . 'list-portfolio-items.php';
    require_once $avada_dir . 'list-faqs.php';
    require_once $avada_dir . 'list-global-colors.php';
    require_once $avada_dir . 'list-global-typography.php';
    require_once $avada_dir . 'edit-global-color.php';
    require_once $avada_dir . 'edit-global-typography.php';
    require_once $avada_dir . 'set-theme-options.php';

    // Fusion Core Slider surface. Registered only when the companion plugin
    // is active at the version floor — the theme-side surface above stays
    // available regardless. `fusion_core_available()` guards against the
    // constants-defined-but-classes-not-loaded window during plugins_loaded.
    if (\Novamira\Pro\Abilities\Avada\fusion_core_available()) {
        require_once $avada_dir . 'list-sliders.php';
        require_once $avada_dir . 'get-slider.php';
        require_once $avada_dir . 'list-slides.php';
        require_once $avada_dir . 'get-slide.php';
        require_once $avada_dir . 'edit-slide.php';
        require_once $avada_dir . 'edit-slider-settings.php';
    }
}

/**
 * One-shot data repair for empty `data` / `conditions` blobs that
 * mismatch Mosaic's per-column revision-record property type:
 *
 *   - wp_mosaic_templates.conditions → TemplateRevisionRecord::$conditions
 *     is `?array`. Decoded with `json_decode($raw)` (no assoc). A `{}`
 *     literal lands as stdClass and crashes with a TypeError on every
 *     URL the template resolves to AND the admin Templates list.
 *     Empty must be `[]`.
 *
 *   - wp_mosaic_nodes.data → NodeRevisionRecord::$data is strictly `object`.
 *     A `[]` literal would decode to a PHP array and crash on assign to
 *     the object-typed property — same TypeError vector. Empty must be `{}`.
 *
 * Pre-Round-2 builds got both wrong (Round 1 normalised everything to
 * `{}` then Round 2 flipped everything to `[]`). The fix below converges
 * each table to the column's correct empty literal idempotently. Guarded
 * by a versioned sentinel so we don't re-scan on every request.
 *
 * Round 7 G4 timing fix: the repair MUST run before Mosaic's own
 * `init` hook reads `wp_mosaic_themes.data`. A corrupted `data='[]'`
 * row crashes `ThemeRevisionRecord` with a TypeError before any other
 * code in the request runs — including the abilities surface that
 * would have healed it. Five independent round-7 probes (G3, G4, G5,
 * G7, G1) hit this crash mid-session. The hook below is `plugins_loaded`
 * priority 1, which fires before `init` and gives us a chance to heal
 * data before Mosaic touches it.
 *
 * Round 7 G2 defense: the function also re-checks that the 3 core target
 * tables exist before running queries. If a Mosaic schema bootstrap
 * partially failed (e.g. on a multisite subsite where Mosaic has not
 * yet provisioned its tables), we exit early WITHOUT arming the
 * sentinel — so a future request retries once Mosaic finishes
 * provisioning.
 *
 * The Class table is repaired when present but never required: Mosaic 1.0.8
 * renamed `utility_classes` to `universal_classes`, and requiring the old
 * name kept the sentinel from ever arming on a fresh 1.0.8 install, so the
 * INFORMATION_SCHEMA lookup below ran on every request. Only the current name
 * is handled — this integration targets the current class model.
 */
function novamira_pro_mosaic_repair_legacy_empty_blobs(): void
{
    $sentinel = 'novamira_pro_mosaic_repair_v2';
    if (get_option($sentinel) === '1') {
        return;
    }
    if (!defined('MOSAIC_VERSION')) {
        // Mosaic is not loaded on this site (likely a multisite subsite
        // without the plugin network-activated). Nothing to repair.
        return;
    }
    global $wpdb;
    /** @var \wpdb $wpdb */
    $templates = $wpdb->prefix . 'mosaic_templates';
    $nodes = $wpdb->prefix . 'mosaic_nodes';
    $themes = $wpdb->prefix . 'mosaic_themes';
    $classes = $wpdb->prefix . 'mosaic_universal_classes';
    // Verify the 3 core target tables exist before issuing UPDATEs. Bypassing
    // the outer mo_tables_present() gate (which lives in the abilities
    // layer that isn't loaded yet at plugins_loaded priority 1) — use
    // INFORMATION_SCHEMA so we don't need the helpers file.
    $candidates = [$templates, $nodes, $themes, $classes];
    $placeholders = implode(', ', array_fill(start_index: 0, count: count($candidates), value: '%s'));
    $found_sql = (string) $wpdb->prepare(
        "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME IN ({$placeholders})",
        $candidates,
    );
    $found = array_map('strval', $wpdb->get_col($found_sql));
    if (array_diff([$templates, $nodes, $themes], $found) !== []) {
        // Mosaic schema incomplete — likely lazy-provisioning hasn't fired
        // on this subsite yet. Do NOT arm the sentinel; retry next request.
        return;
    }
    // Array-typed columns: `{}` -> `[]`
    $wpdb->query("UPDATE `{$templates}` SET `conditions` = '[]' WHERE `conditions` = '{}'");
    // Object-typed columns: `[]` -> `{}`
    $wpdb->query("UPDATE `{$nodes}` SET `data` = '{}' WHERE `data` = '[]'");
    $wpdb->query("UPDATE `{$themes}` SET `data` = '{}' WHERE `data` = '[]'");
    if (in_array($classes, $found, strict: true)) {
        $wpdb->query("UPDATE `{$classes}` SET `data` = '{}' WHERE `data` = '[]'");
    }
    update_option($sentinel, value: '1', autoload: false);
}

/**
 * Load every Astra ability. Sprint 1 (discovery + read-only) is always loaded
 * on any active Astra install. Later sprints (S2 global CRUD, S3 HFB, S4 per-post
 * overrides, S5 Advanced Hooks, S6 Advanced Headers, S7 WooCommerce styling)
 * will extend this function; module gates for the addon-specific abilities live
 * inside the callbacks themselves via `astra_module_active()`.
 */
function novamira_pro_load_astra_abilities(): void
{
    require_once __DIR__ . '/abilities/astra/runtime.php';
    $dir = __DIR__ . '/abilities/astra/';
    require_once $dir . 'helpers.php';

    // Sprint 1 — Discovery + read-only globals.
    require_once $dir . 'check-setup.php';
    require_once $dir . 'list-modules.php';
    require_once $dir . 'get-settings.php';
    require_once $dir . 'get-defaults.php';
    require_once $dir . 'get-layout.php';
    require_once $dir . 'list-customizer-settings.php';
    require_once $dir . 'get-css-output.php';

    // Sprint 2 — Global settings CRUD + palette + typography + module toggle.
    require_once $dir . 'edit-settings.php';
    require_once $dir . 'set-color-palette.php';
    require_once $dir . 'get-color-palette.php';
    require_once $dir . 'set-typography.php';
    require_once $dir . 'enable-module.php';
    require_once $dir . 'disable-module.php';

    // Sprint 3 — Header/Footer Builder.
    require_once $dir . 'get-header-layout.php';
    require_once $dir . 'get-footer-layout.php';
    require_once $dir . 'set-header-layout.php';
    require_once $dir . 'set-footer-layout.php';
    require_once $dir . 'reorder-header-row.php';

    // Per-post display overrides.
    require_once $dir . 'get-post-settings.php';
    require_once $dir . 'set-post-settings.php';
    require_once $dir . 'clear-post-settings.php';
    require_once $dir . 'set-post-background.php';

    // Advanced Hooks CPT (addon-gated advanced-hooks module).
    if (\Novamira\Pro\Abilities\Astra\astra_module_active('advanced-hooks')) {
        require_once $dir . 'list-hooks.php';
        require_once $dir . 'get-hook.php';
        require_once $dir . 'create-hook.php';
        require_once $dir . 'edit-hook.php';
        require_once $dir . 'delete-hook.php';
    }

    // Advanced Headers CPT (addon-gated advanced-headers module).
    if (\Novamira\Pro\Abilities\Astra\astra_module_active('advanced-headers')) {
        require_once $dir . 'list-page-headers.php';
        require_once $dir . 'get-page-header.php';
        require_once $dir . 'create-page-header.php';
        require_once $dir . 'edit-page-header.php';
        require_once $dir . 'delete-page-header.php';
    }

    // WooCommerce Astra Pro styling (addon + module + WC gated).
    if (
        \Novamira\Pro\Abilities\Astra\astra_module_active('woocommerce')
        && \Novamira\Pro\Abilities\Woocommerce\wc_min_runtime_available()
    ) {
        require_once $dir . 'get-woocommerce-settings.php';
        require_once $dir . 'edit-woocommerce-settings.php';
    }
}

/**
 * Load the Voxel theme ability files. check-setup is the presence-gated
 * baseline (it reports the version gap even below the minimum); the read-only
 * discovery abilities (list-post-types, get-post-type, get-field, list-posts,
 * get-post) each refuse below the floor via vx_require_min(). Later sprints add
 * content CRUD, taxonomies, commerce, and community abilities.
 */
function novamira_pro_load_voxel_abilities(): void
{
    $dir = __DIR__ . '/abilities/voxel/';
    $files = [
        'runtime.php',
        'helpers.php',
        'write-helpers.php',
        'term-helpers.php',
        'config-helpers.php',

        // Sprint 1 — Baseline + discovery (read-only).
        'check-setup.php',
        'list-post-types.php',
        'get-post-type.php',
        'get-field.php',
        'list-posts.php',
        'get-post.php',

        // Sprint 2 — Listing/content CRUD (writes postmeta per post).
        'create-post.php',
        'edit-post.php',
        'delete-post.php',

        // Sprint 3 — Taxonomies + terms.
        'list-taxonomies.php',
        'get-taxonomy.php',
        'list-terms.php',
        'get-term.php',
        'create-term.php',
        'edit-term.php',
        'delete-term.php',

        // Sprint 4 — Site config / builder (mutates the shared voxel:post_types /
        // voxel:taxonomies option blobs under a lock; excludes search-filter/index edits).
        'create-taxonomy.php',
        'edit-taxonomy.php',
        'delete-taxonomy.php',
        'create-post-type.php',
        'edit-post-type.php',
        'delete-post-type.php',
        'add-field.php',
        'edit-field.php',
        'delete-field.php',
        'reorder-fields.php',

        // Sprint 5 — Commerce (read-only: orders, product types, plans, roles,
        // membership). No writes, no Stripe calls; orders/membership are scoped to
        // the site's current payments mode.
        'commerce-helpers.php',
        'list-orders.php',
        'get-order.php',
        'list-product-types.php',
        'get-product-type.php',
        'list-plans.php',
        'get-plan.php',
        'list-roles.php',
        'get-role.php',
        'get-user-membership.php',

        // Sprint 6 — Community + templates + settings. Reads are strict (approved
        // content only, secrets redacted); the only writes are a per-row notification
        // seen flip and lock-wrapped template assignment. Private messages are
        // excluded entirely (PII), as are settings secrets and destructive/moderation
        // community operations.
        'community-helpers.php',
        'list-timeline.php',
        'get-status.php',
        'list-replies.php',
        'list-notifications.php',
        'get-notification.php',
        'mark-notification.php',
        'list-followers.php',
        'get-follow-status.php',
        'list-templates.php',
        'apply-template.php',
        'clear-template.php',
        'list-settings.php',
        'get-setting.php',

        // Audit additions — read-only introspection: the field-type prop reference
        // and the post-relation graph.
        'get-field-type-schema.php',
        'list-related-posts.php',

        // Sprint 7 — Dynamic data (VoxelScript). Discovery is read-only via Voxel's
        // own exporter; rendering binds explicit entity contexts (the query_var
        // site method still reads Voxel's ambient visibility-context stack, then
        // $_GET — documented, warned about, not sandboxed).
        'list-dynamic-data.php',
        'render-dynamic-data.php',

        // Per-post-type base templates — create the Elementor documents (or the
        // form page) for a post type's single/card/archive/form slots and
        // bind/unbind them (config write under the post_types lock, document-type
        // fixup mirroring Voxel's admin).
        'post-type-template-helpers.php',
        'create-post-type-template.php',
        'apply-post-type-template.php',
        'clear-post-type-template.php',
    ];
    foreach ($files as $file) {
        require_once $dir . $file;
    }
}

/**
 * Load every Blocksy ability file. Sprint 1 is discovery + read-only
 * customizer access; Sprint 2 adds the locked write path (option +
 * palette edits with the dynamic-CSS refresh sequence); Sprint 3 adds the
 * header/footer builder surface on top of the same write path; Sprint 4
 * adds the Companion extensions surface. Sprints 1–3 run on the free
 * theme alone (companion state is reported by check-setup, not required
 * by it — companion-registered builder items simply appear when the
 * companion is active); the Sprint-4 extension abilities wrap a
 * Companion feature and gate on it at call time. Sprint 5 adds Content
 * Blocks (Companion Pro); Sprint 6 adds the per-post options (theme only).
 */
function novamira_pro_load_blocksy_abilities(): void
{
    $dir = __DIR__ . '/abilities/blocksy/';
    require_once $dir . 'runtime.php';
    require_once $dir . 'helpers.php';
    require_once $dir . 'write-helpers.php';
    require_once $dir . 'builder-helpers.php';
    require_once $dir . 'extension-helpers.php';

    // Sprint 1 — baseline + read-only discovery.
    require_once $dir . 'check-setup.php';
    require_once $dir . 'get-color-palette.php';
    require_once $dir . 'list-options.php';
    require_once $dir . 'get-option.php';

    // Sprint 2 — customizer writes.
    require_once $dir . 'edit-option.php';
    require_once $dir . 'edit-color-palette.php';

    // Sprint 3 — header/footer builder.
    require_once $dir . 'get-header.php';
    require_once $dir . 'get-footer.php';
    require_once $dir . 'list-builder-items.php';
    require_once $dir . 'add-builder-item.php';
    require_once $dir . 'edit-builder-item.php';
    require_once $dir . 'delete-builder-item.php';

    // Sprint 4 — companion extensions.
    require_once $dir . 'list-extensions.php';
    require_once $dir . 'enable-extension.php';
    require_once $dir . 'disable-extension.php';
    require_once $dir . 'get-extension-settings.php';

    // Sprint 5 — Content Blocks (Companion Pro).
    require_once $dir . 'content-block-helpers.php';
    require_once $dir . 'list-content-blocks.php';
    require_once $dir . 'get-content-block.php';
    require_once $dir . 'create-content-block.php';
    require_once $dir . 'edit-content-block-conditions.php';
    require_once $dir . 'edit-content-block-position.php';
    require_once $dir . 'delete-content-block.php';

    // Sprint 6 — per-post options (the editor's "Blocksy Settings" panel).
    require_once $dir . 'page-options-helpers.php';
    require_once $dir . 'get-page-options.php';
    require_once $dir . 'edit-page-options.php';
}
