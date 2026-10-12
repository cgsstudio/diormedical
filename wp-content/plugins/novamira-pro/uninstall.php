<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit();
}

const NOVAMIRA_PRO_UNINSTALL_LICENSE_URL = 'https://license.dynamic.ooo/novamira-pro/';

const NOVAMIRA_PRO_UNINSTALL_PRODUCT_UNIQUE_ID = 'WP-NVP-1';

const NOVAMIRA_PRO_UNINSTALL_OPTION_MISSING = '__novamira_pro_uninstall_option_missing__';

function novamira_pro_uninstall_version(): string
{
    /** @var mixed $version */
    $version = get_file_data(__DIR__ . '/novamira-pro.php', ['Version' => 'Version'])['Version'] ?? '';
    return is_string($version) ? $version : '';
}

/**
 * Best-effort counterpart to the regular licensing deactivation request.
 *
 * The plugin bootstrap is not loaded during uninstall, so the endpoint and
 * request parameters are kept here. WordPress returns WP_Error for transport
 * failures; cleanup must also continue if a third-party HTTP hook throws.
 */
function novamira_pro_uninstall_deactivate_license(): void
{
    /** @var mixed $raw_license_key */
    $raw_license_key = get_option('nvp_license_key', default_value: '');
    if (!is_string($raw_license_key)) {
        return;
    }

    $license_key = strtolower(trim($raw_license_key));
    if ($license_key === '') {
        return;
    }

    $domain = str_replace(search: ['https://', 'http://'], replace: '', subject: get_bloginfo('wpurl'));
    $url = add_query_arg(
        [
            'woo_sl_action' => 'deactivate',
            'licence_key' => $license_key,
            'product_unique_id' => NOVAMIRA_PRO_UNINSTALL_PRODUCT_UNIQUE_ID,
            'domain' => $domain,
            'api_version' => '1.1',
            'wp-version' => get_bloginfo('version'),
            'version' => novamira_pro_uninstall_version(),
            'is_multisite' => is_multisite(),
            'php' => PHP_VERSION,
        ],
        trailingslashit(NOVAMIRA_PRO_UNINSTALL_LICENSE_URL) . 'api.php',
    );

    try {
        wp_remote_get($url, [
            'headers' => ['Accept' => 'application/json'],
            'timeout' => 5,
        ]);
    } catch (Throwable) {
        // @mago-expect lint:no-empty-catch-clause -- Third-party HTTP hook exceptions must not block local cleanup.
    }
}

function novamira_pro_uninstall_handover_context_option(string $legacy_option, string $base_option): void
{
    /** @var mixed $base_value */
    $base_value = get_option($base_option, default_value: NOVAMIRA_PRO_UNINSTALL_OPTION_MISSING);
    if ($base_value !== NOVAMIRA_PRO_UNINSTALL_OPTION_MISSING) {
        return;
    }

    /** @var mixed $legacy_value */
    $legacy_value = get_option($legacy_option, default_value: NOVAMIRA_PRO_UNINSTALL_OPTION_MISSING);
    if ($legacy_value === NOVAMIRA_PRO_UNINSTALL_OPTION_MISSING) {
        return;
    }

    update_option($base_option, $legacy_value);
}

function novamira_pro_uninstall_handover_context(): void
{
    novamira_pro_uninstall_handover_context_option(
        legacy_option: 'nvp_instructions_content',
        base_option: 'novamira_instructions_content',
    );
    novamira_pro_uninstall_handover_context_option(
        legacy_option: 'nvp_instructions_enabled',
        base_option: 'novamira_instructions_enabled',
    );
}

function novamira_pro_uninstall_current_site(): void
{
    novamira_pro_uninstall_deactivate_license();
    novamira_pro_uninstall_handover_context();

    $options = [
        'nvp_license_key',
        'nvp_license_status',
        'nvp_license_error',
        'nvp_license_domain',
        'nvp_instructions_enabled',
        'nvp_instructions_content',
        'nvp_memory_enabled',
        'novamira_pro_mosaic_repair_v2',
        'nvp_weglot_pending_keys',
    ];
    foreach ($options as $option) {
        delete_option($option);
    }

    delete_transient('novamira-pro_update_checker');
    delete_transient('nvp_spectra_forms_usage_count');
    wp_clear_scheduled_hook('nvp_check_license_cron');
}

function novamira_pro_uninstall_user_meta(): void
{
    $meta_keys = [
        'nvp_notice_dismissed_license_needed',
        'nvp_notice_dismissed_domain_mismatch',
    ];
    foreach ($meta_keys as $meta_key) {
        delete_metadata(meta_type: 'user', object_id: 0, meta_key: $meta_key, meta_value: '', delete_all: true);
    }
}

function novamira_pro_uninstall_sites(): void
{
    if (!is_multisite()) {
        novamira_pro_uninstall_current_site();
        return;
    }

    // @mago-expect analysis:mixed-assignment -- WordPress returns site ids when fields=ids.
    $site_ids = get_sites(['fields' => 'ids', 'number' => 0]);
    if (!is_array($site_ids)) {
        return;
    }

    // @mago-expect analysis:mixed-assignment
    foreach ($site_ids as $site_id) {
        switch_to_blog((int) $site_id);
        novamira_pro_uninstall_current_site();
        restore_current_blog();
    }
}

novamira_pro_uninstall_sites();
novamira_pro_uninstall_user_meta();
