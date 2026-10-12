<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicContentForElementor;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/dynamic-content-for-elementor-check-setup', [
    'label' => __('Check Dynamic Content for Elementor Setup', domain: 'novamira-pro'),
    'description' => __(
        'Reports the Dynamic Content for Elementor environment for the HTML Templates surface: the plugin version, whether the HTML Templates post type is registered, and how many templates exist. The other dynamic-content-for-elementor abilities only register when the plugin is present and at or above the minimum, so active and min_satisfied are here to report which version you are on, not to discover an absence. Also reports whether either feature that renders a template into a PDF is switched on, because a template can be created and edited while the thing that would render it is off, and whether the Dynamic Shortcodes pass is available for template code, because without it Dynamic Shortcodes syntax in the template\'s own code reaches the PDF literally and without an error. It separately exposes Dynamic Content for Elementor\'s legacy Timber condition only to identify a site where code left unchanged by Dynamic Shortcodes would be sent to the legacy branch, not to present Twig as a choice for new work. Call this before the other dynamic-content-for-elementor abilities. Note that a PDF template is NOT an Elementor document: composing or styling Elementor pages is the novamira/elementor-* surface.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-content-for-elementor',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'active' => ['type' => 'boolean'],
            'version' => ['type' => ['string', 'null']],
            'min_version' => ['type' => 'string'],
            'min_satisfied' => ['type' => 'boolean'],
            'post_type_registered' => [
                'type' => 'boolean',
                'description' => 'False means the plugin is active but has not registered the HTML Templates post type in this request. Creating a template is refused while it is false; reading, editing, deleting and listing existing templates still work, because those reach a template by id rather than through the registration. templates_count also reads zero in that state, since WordPress counts nothing for a post type it does not know about.',
            ],
            'templates_count' => ['type' => 'integer'],
            'dynamic_shortcodes_expansion' => [
                'type' => 'boolean',
                'description' => 'True means Dynamic Content for Elementor runs the Dynamic Shortcodes pass over the template\'s own code at render time; this requires the separate Dynamic Shortcodes plugin to be active and booted. False means Dynamic Shortcodes syntax remains literal in the PDF without an error.',
            ],
            'twig_expansion' => [
                'type' => 'boolean',
                'description' => 'Legacy compatibility signal: true means Dynamic Content for Elementor\'s Timber condition is met, so code that the Dynamic Shortcodes pass leaves wholly unchanged is sent to the legacy Twig branch. It does not prove that Twig rendering succeeds. False means Dynamic Content for Elementor does not attempt Timber expansion, so Twig syntax such as {{…}} stays literal in the PDF without an error. If Dynamic Shortcodes changes any part, expansion ends there and Timber does not run; PDF generation continues with that result, so the template\'s Twig syntax is not rendered and stays literal in the PDF. This can happen even without intentional mixing when a Twig hash opens with a registered Dynamic Shortcodes type ID followed directly by a colon, as in {date: \'2024-01-02\'}. Do not use Twig for new templates or mix the syntaxes. When dynamic_shortcodes_expansion is also true and a change to an existing Twig template is requested, leave it unchanged and report the compatibility risk unless the user chooses one of two paths: a Twig-only edit, which must keep the template entirely in Twig and requires asking the user to check the generated PDF because no ability in this surface renders one; or a full conversion, which requires the user to decide to use Dynamic Shortcodes, arrange for it to be available if needed, and explicitly authorize replacing the whole template. Retain the original code because this post type has no revisions.',
            ],
            'pdf_feature_active' => [
                'type' => ['boolean', 'null'],
                'description' => 'Whether either feature that turns a template into a PDF is switched on: the PDF Button widget or the PDF generator added to the Elementor Pro form. False means templates still exist and stay editable but nothing renders them, and they are switched on in the plugin own Features screen. Null means the plugin had not finished booting when asked, usually because Elementor is inactive or below the version it requires.',
            ],
        ],
        'required' => ['active', 'min_version', 'min_satisfied', 'post_type_registered', 'templates_count'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicContentForElementor\\dynamic_content_for_elementor_check_setup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Run first. If post_type_registered is false, creating a template is refused and templates_count reads zero, while the other abilities still reach existing templates.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function dynamic_content_for_elementor_check_setup(): array
{
    $version = defined('DCE_VERSION') ? (string) constant('DCE_VERSION') : null;

    $total = dce_count_templates();

    return [
        'active' => $version !== null,
        'version' => $version,
        'min_version' => NOVAMIRA_PRO_DYNAMIC_CONTENT_FOR_ELEMENTOR_MIN_VERSION,
        'min_satisfied' =>
            $version !== null
                && version_compare($version, NOVAMIRA_PRO_DYNAMIC_CONTENT_FOR_ELEMENTOR_MIN_VERSION, operator: '>='),
        'post_type_registered' => post_type_exists(DCE_TEMPLATE_POST_TYPE),
        'templates_count' => $total,
        'pdf_feature_active' => dce_pdf_feature_active(),
        'dynamic_shortcodes_expansion' => dce_dynamic_shortcodes_expansion_active(),
        'twig_expansion' => dce_twig_expansion_active(),
    ];
}

/**
 * Whether the Dynamic Shortcodes pass over a template's code would expand anything.
 *
 * Asked of the plugin's own manager rather than of the plugin list, because that
 * is the object the render path consults: it holds a shortcodes manager only once
 * the companion plugin has booted and handed one over, so an installed-but-not-yet-
 * booted companion answers false here exactly as it would behave at render time.
 */
function dce_dynamic_shortcodes_expansion_active(): bool
{
    if (!class_exists('DynamicContentForElementor\\Plugin')) {
        return false;
    }

    $plugin = \DynamicContentForElementor\Plugin::instance();
    if (!$plugin instanceof \DynamicContentForElementor\Plugin) {
        return false;
    }

    $text_templates = $plugin->text_templates ?? null;
    if (!is_object($text_templates)) {
        return false;
    }

    $shortcodes = $text_templates->dce_shortcodes ?? null;
    if (!is_object($shortcodes) || !method_exists($shortcodes, 'is_dsh_active')) {
        return false;
    }

    return $shortcodes->is_dsh_active();
}

/**
 * Whether Dynamic Content for Elementor's Timber condition is met for attempting
 * the legacy Twig branch. This does not verify that rendering will succeed.
 */
function dce_twig_expansion_active(): bool
{
    if (!class_exists('DynamicContentForElementor\\Helper')) {
        return false;
    }

    return \DynamicContentForElementor\Helper::is_plugin_active('timber');
}

/**
 * How many HTML Templates exist, excluding the two statuses a caller never means.
 *
 * `wp_count_posts()` answers with an object whose properties are the statuses, and
 * the statuses counted here are named rather than filtered, so a status added by
 * another plugin never quietly inflates the number.
 */
function dce_count_templates(): int
{
    $counts = wp_count_posts(DCE_TEMPLATE_POST_TYPE);

    $total = 0;
    foreach (['publish', 'draft', 'pending', 'private', 'future'] as $status) {
        /** @var mixed $value */
        $value = $counts->{$status} ?? null;
        if (!is_numeric($value)) {
            continue;
        }
        $total += (int) $value;
    }

    return $total;
}

/**
 * Whether either feature that renders an HTML Template is switched on.
 *
 * Two of them do: the PDF Button widget and the PDF generator the plugin adds to
 * the Elementor Pro form. Upstream shows its HTML Templates screen when **either**
 * is active, so answering on the widget alone would report "nothing renders these"
 * on a site whose forms produce PDFs perfectly well.
 *
 * Read through the plugin's feature object rather than its stored option: the
 * option is absent until the plugin first saves it and in-code defaults apply
 * until then, so the option alone answers "off" on a site where the feature is on.
 *
 * Returns null rather than guessing whenever the plugin has not finished booting.
 * Its `Plugin::instance()` hands back null when Elementor is absent or below its
 * minimum, and its feature map is only filled on `elementor/init` — and the class
 * exists either way, because the plugin registers its autoloader before it checks
 * Elementor at all.
 */
function dce_pdf_feature_active(): ?bool
{
    if (!class_exists('DynamicContentForElementor\\Plugin')) {
        return null;
    }

    $plugin = \DynamicContentForElementor\Plugin::instance();
    if (!$plugin instanceof \DynamicContentForElementor\Plugin) {
        return null;
    }

    $features = $plugin->features ?? null;
    if (!is_object($features) || !method_exists($features, 'is_feature_active')) {
        return null;
    }

    // The feature map is populated on elementor/init; before that every lookup
    // would index null. Unknown is the honest answer, not false.
    if (!did_action('elementor/init')) {
        return null;
    }

    return $features->is_feature_active('wdg_pdf') || $features->is_feature_active('ext_form_pdf');
}
