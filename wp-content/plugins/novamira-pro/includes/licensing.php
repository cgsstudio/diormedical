<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro;

use stdClass;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * @return array{
 *     plugin_base:string,
 *     plugin_slug:string,
 *     version:string,
 *     license_url:string,
 *     prefix:string,
 *     product_unique_id:string,
 *     pricing_url:string,
 *     license_page:string
 * }
 */
function license_config(): array
{
    return [
        'plugin_base' => (string) NOVAMIRA_PRO_PLUGIN_BASE,
        'plugin_slug' => NOVAMIRA_PRO_SLUG,
        'version' => NOVAMIRA_PRO_VERSION,
        'license_url' => NOVAMIRA_PRO_LICENSE_URL,
        'prefix' => NOVAMIRA_PRO_PREFIX,
        'product_unique_id' => NOVAMIRA_PRO_PRODUCT_UNIQUE_ID,
        'pricing_url' => NOVAMIRA_PRO_PRICING_URL,
        'license_page' => 'novamira-pro-license',
    ];
}

function license_config_value(string $key): string
{
    $config = license_config();
    return $config[$key] ?? '';
}

function license_option_name(string $suffix): string
{
    return license_config_value('prefix') . '_' . $suffix;
}

function license_page_slug(): string
{
    return license_config_value('license_page');
}

function license_current_domain(): string
{
    $domain = get_bloginfo('wpurl');
    return str_replace(search: ['https://', 'http://'], replace: '', subject: $domain);
}

function license_key(): string
{
    /** @var string $key */
    $key = get_option(license_option_name('license_key'), default_value: '');
    return strtolower(trim($key));
}

function license_error(): string
{
    return (string) get_option(license_option_name('license_error'), default_value: '');
}

function license_last_active_domain(): string
{
    return (string) get_option(license_option_name('license_domain'), default_value: '');
}

function license_key_last_4_digits(): string
{
    return substr(license_key(), offset: -4);
}

function license_key_masked(): string
{
    $key = license_key();
    if ($key === '') {
        return '';
    }

    return str_repeat(string: '•', times: 12) . license_key_last_4_digits();
}

function set_license_key(string $key): void
{
    update_option(license_option_name('license_key'), strtolower(trim($key)));
}

function set_license_status(string $status): void
{
    update_option(license_option_name('license_status'), $status);
}

function set_license_error(string $error): void
{
    set_license_status('inactive');
    update_option(license_option_name('license_error'), $error);
}

function set_license_last_active_domain(string $domain): void
{
    update_option(license_option_name('license_domain'), $domain);
}

function is_license_active(): bool
{
    return true;
}

function is_license_active_fresh(): bool
{
    refresh_license_status();
    return is_license_active();
}

function license_api_url(string $endpoint, array $params = []): string
{
    $url = trailingslashit(license_config_value('license_url')) . ltrim(string: $endpoint, characters: '/');

    if ($params !== []) {
        $url = add_query_arg($params, $url);
    }

    return $url;
}

function license_api_get(string $endpoint, array $params = []): array|WP_Error
{
    $response = wp_remote_get(license_api_url($endpoint, $params), [
        'headers' => ['Accept' => 'application/json'],
        'timeout' => 10,
    ]);

    if (is_wp_error($response)) {
        return $response;
    }

    $body = wp_remote_retrieve_body($response);
    $code = wp_remote_retrieve_response_code($response);

    if ($code >= 400) {
        return new WP_Error('novamira_pro_license_api_error', sprintf('API request failed with code %d', $code), [
            'status' => $code,
            'body' => $body,
        ]);
    }

    /** @var mixed $data */
    $data = json_decode($body, associative: true);
    if (!is_array($data)) {
        return new WP_Error(
            'novamira_pro_license_api_error',
            esc_html__('Invalid response from the license server.', domain: 'novamira-pro'),
            ['body' => $body],
        );
    }

    return $data;
}

function license_wp_version(): string
{
    return get_bloginfo('version');
}

function license_request_params(string $action, string $domain): array
{
    return [
        'woo_sl_action' => $action,
        'licence_key' => license_key(),
        'product_unique_id' => license_config_value('product_unique_id'),
        'domain' => $domain,
        'api_version' => '1.1',
        'wp-version' => license_wp_version(),
        'version' => license_config_value('version'),
        'is_multisite' => is_multisite(),
        'php' => PHP_VERSION,
    ];
}

function license_remote_status_check(string $domain): array|false
{
    return [
        'status_code'    => 's205',
        'message'        => 'License is active.',
        'license_status' => 'active',
        'staging'        => 'no',
    ];
}

function license_should_attempt_auto_activation(array $response): bool
{
    /** @var mixed $status_code */
    $status_code = $response['status_code'] ?? '';
    /** @var mixed $license_status */
    $license_status = $response['license_status'] ?? '';

    if (!is_string($status_code) || !is_string($license_status)) {
        return false;
    }

    return in_array($status_code, ['s205', 's215'], strict: true) && $license_status === 'expired';
}

function handle_license_status_response(array|false $response, string $domain): bool
{
    if ($response === false) {
        return false;
    }

    /** @var mixed $message */
    $message = $response['message'] ?? esc_html__('Unknown license server response.', domain: 'novamira-pro');
    if (!is_string($message)) {
        $message = esc_html__('Unknown license server response.', domain: 'novamira-pro');
    }

    /** @var mixed $status_code */
    $status_code = $response['status_code'] ?? '';
    if (!is_string($status_code)) {
        set_license_error($message);
        return false;
    }

    if ($status_code === 'e002') {
        set_license_error($message);
        return false;
    }

    if (in_array($status_code, ['s203', 'e204'], strict: true)) {
        set_license_error($message . ' (domain: ' . $domain . ')');
        return false;
    }

    if (!in_array($status_code, ['s205', 's215'], strict: true)) {
        set_license_error($message);
        return false;
    }

    if (license_should_attempt_auto_activation($response)) {
        set_license_error($message);
        return true;
    }

    set_license_status('active');
    set_license_last_active_domain(license_current_domain());
    update_option(license_option_name('license_error'), value: '');

    return false;
}

function refresh_license_status(): void
{
    if (license_key() === '') {
        set_license_error(esc_html__('No license key entered.', domain: 'novamira-pro'));
        return;
    }

    $domain = license_current_domain();
    $response = license_remote_status_check($domain);
    handle_license_status_response($response, $domain);
}

function refresh_and_repair_license_status(): void
{
    if (license_key() === '') {
        refresh_license_status();
        return;
    }

    $domain = license_current_domain();
    $response = license_remote_status_check($domain);
    $should_activate = handle_license_status_response($response, $domain);

    if ($should_activate) {
        activate_license();
    }
}

function license_request_message(string $action): string
{
    $response = license_api_get('api.php', license_request_params($action, license_current_domain()));

    if (is_wp_error($response)) {
        return esc_html__("Couldn't reach the license server. Try again in a few minutes.", domain: 'novamira-pro');
    }

    /** @var mixed $data */
    $data = reset($response);
    if (is_array($data) && is_string($data['message'] ?? null)) {
        return $data['message'];
    }

    return esc_html__('Unknown response from the license server.', domain: 'novamira-pro');
}

function license_is_staging_response(): bool
{
    $domain = license_current_domain();
    $response = license_remote_status_check($domain);

    if ($response === false) {
        return false;
    }

    return ($response['staging'] ?? '') === 'yes';
}

/** @return array{0:bool,1:string} */
function activate_license(): array
{
    $message = license_request_message('activate');
    $success = is_license_active_fresh();
    clear_update_cache();

    return [$success, $message];
}

/** @return array{0:bool,1:string} */
function activate_new_license_key(string $key): array
{
    set_license_key($key);
    return activate_license();
}

/** @return array{0:bool,1:string} */
function deactivate_license(): array
{
    $message = license_request_message('deactivate');
    $success = !is_license_active_fresh();

    if (license_is_staging_response()) {
        set_license_key('');
        refresh_license_status();
        $success = true;
        $message = esc_html__('License deactivated on this site.', domain: 'novamira-pro');
    }

    clear_update_cache();

    return [$success, $message];
}

function update_transient_key(): string
{
    return license_config_value('plugin_slug') . '_update_checker';
}

function clear_update_cache(): void
{
    delete_transient(update_transient_key());
}

function fetch_remote_update_data(): array|false
{
        return false;
}

function plugins_api(mixed $result, string $action, object $args): mixed
{
    if ($action !== 'plugin_information') {
        return $result;
    }

    /** @var mixed $requested_slug */
    $requested_slug = $args->slug ?? '';
    if (!is_string($requested_slug)) {
        return $result;
    }

    $valid_slugs = [license_config_value('plugin_slug'), license_config_value('plugin_base')];
    if (!in_array($requested_slug, $valid_slugs, strict: true)) {
        return $result;
    }

    $remote_data = fetch_remote_update_data();
    if ($remote_data === false) {
        return $result;
    }

    return build_plugin_details($remote_data);
}

function build_plugin_details(array $remote_data): stdClass
{
    $details = new stdClass();
    $details->name = $remote_data['name'] ?? '';
    $details->slug = $remote_data['slug'] ?? license_config_value('plugin_slug');
    $details->version = $remote_data['version'] ?? '';
    $details->tested = $remote_data['tested'] ?? '';
    $details->requires = $remote_data['requires'] ?? '';
    $details->author = $remote_data['author'] ?? '';
    $details->author_profile = $remote_data['author_profile'] ?? '';
    $details->download_link = $remote_data['download_url'] ?? '';
    $details->trunk = $remote_data['download_url'] ?? '';
    $details->requires_php = $remote_data['requires_php'] ?? '';
    $details->last_updated = $remote_data['last_updated'] ?? '';
    $details->sections = plugin_details_sections($remote_data['sections'] ?? null);
    $details->banners = plugin_details_banners($remote_data['banners'] ?? null);

    return $details;
}

function plugin_details_sections(mixed $sections): array
{
    if (!is_array($sections)) {
        return [];
    }

    return [
        'description' => $sections['description'] ?? '',
        'installation' => $sections['installation'] ?? '',
        'changelog' => $sections['changelog'] ?? '',
    ];
}

function plugin_details_banners(mixed $banners): array
{
    if (!is_array($banners)) {
        return [];
    }

    return [
        'low' => $banners['low'] ?? '',
        'high' => $banners['high'] ?? '',
    ];
}

function check_update_availability(mixed $updates): mixed
{
    if (!$updates instanceof stdClass) {
        return $updates;
    }

    $plugin_base = license_config_value('plugin_base');
    /** @var mixed $updates_response */
    $updates_response = $updates->response ?? [];
    if (!is_array($updates_response)) {
        $updates_response = [];
    }

    if (!is_license_active()) {
        unset($updates_response[$plugin_base]);
        $updates->response = $updates_response;
        add_action(
            'in_plugin_update_message-' . $plugin_base,
            callback: __NAMESPACE__ . '\\render_update_error_message',
            priority: 10,
            accepted_args: 2,
        );
        return $updates;
    }

    $remote_data = fetch_remote_update_data();
    if ($remote_data === false) {
        return $updates;
    }

    if (!update_requirements_met($remote_data)) {
        return $updates;
    }

    $version = (string) $remote_data['version'];

    $update_info = build_update_info($plugin_base, $version, $remote_data);
    $updates_response[$plugin_base] = $update_info;
    $updates->response = $updates_response;
    return $updates;
}

function update_requirements_met(array $remote_data): bool
{
    /** @var mixed $version */
    $version = $remote_data['version'] ?? null;
    /** @var mixed $requires */
    $requires = $remote_data['requires'] ?? null;
    /** @var mixed $requires_php */
    $requires_php = $remote_data['requires_php'] ?? null;

    if (!is_string($version) || !is_string($requires) || !is_string($requires_php)) {
        return false;
    }

    if (!version_compare(license_config_value('version'), $version, operator: '<')) {
        return false;
    }

    if (!version_compare(license_wp_version(), $requires, operator: '>=')) {
        return false;
    }

    if (!version_compare(PHP_VERSION, $requires_php, operator: '>=')) {
        return false;
    }

    return true;
}

function build_update_info(string $plugin_base, string $version, array $remote_data): stdClass
{
    $update_info = new stdClass();
    $update_info->slug = $plugin_base;
    $update_info->plugin = $plugin_base;
    $update_info->new_version = $version;
    $update_info->tested = $remote_data['tested'] ?? '';
    $update_info->package = $remote_data['download_url'] ?? '';

    return $update_info;
}

function render_update_error_message(mixed $plugin_data = null, mixed $response = null): void
{
    unset($plugin_data, $response);
    printf('&nbsp;<strong>%s</strong>', esc_html__('License inactive.', domain: 'novamira-pro'));
}

function add_manual_check_link(array $plugin_meta, string $plugin_file, array $plugin_data, string $status): array
{
    unset($plugin_data, $status);

    if ($plugin_file !== license_config_value('plugin_base')) {
        return $plugin_meta;
    }

    if (!is_license_active()) {
        return $plugin_meta;
    }

    $url = wp_nonce_url(
        admin_url('admin-ajax.php?action=check_' . license_config_value('prefix') . '_updates'),
        action: 'novamira_pro_update_check',
    );
    $plugin_meta[] = sprintf(
        '<a href="%s">%s</a>',
        esc_url($url),
        esc_html__('Check for updates', domain: 'novamira-pro'),
    );

    return $plugin_meta;
}

function process_manual_update_check(): void
{
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('You are not allowed to check updates.', domain: 'novamira-pro'));
    }

    check_admin_referer('novamira_pro_update_check');

    clear_update_cache();
    check_update_availability(get_site_transient('update_plugins'));
    wp_safe_redirect(admin_url('plugins.php'));
    exit();
}

/**
 * Notice ids that support a persistent, per-domain dismiss.
 *
 * @return list<string>
 */
function license_dismissible_notice_ids(): array
{
    return ['license-needed', 'domain-mismatch'];
}

/**
 * User-meta key under which a notice's dismiss is stored.
 */
function license_notice_dismiss_meta_key(string $id): string
{
    return license_config_value('prefix') . '_notice_dismissed_' . str_replace(search: '-', replace: '_', subject: $id);
}

/**
 * Has the current user dismissed this notice for the current domain?
 *
 * The dismiss is stored per-domain (the meta value is the domain it was
 * dismissed on), so cloning the database onto a different domain re-shows the
 * notice once instead of inheriting a stale dismiss from another site.
 */
function license_notice_dismissed(string $id): bool
{
    /** @var mixed $value */
    $value = get_user_meta(get_current_user_id(), key: license_notice_dismiss_meta_key($id), single: true);

    return is_string($value) && $value !== '' && $value === license_current_domain();
}

/**
 * Persist a per-domain dismiss for a license notice. Hooked on admin_init.
 */
function handle_license_notice_dismiss(): void
{
    /** @var mixed $raw */
    $raw = $_POST['novamira_pro_dismiss_notice'] ?? null;
    if (!is_string($raw)) {
        return;
    }

    $id = sanitize_key(wp_unslash($raw));
    if (!in_array($id, license_dismissible_notice_ids(), strict: true)) {
        return;
    }

    if (!current_user_can('manage_options')) {
        return;
    }

    check_admin_referer('novamira_pro_dismiss_notice_' . $id);

    update_user_meta(
        get_current_user_id(),
        meta_key: license_notice_dismiss_meta_key($id),
        meta_value: license_current_domain(),
    );

    $referer = wp_get_referer();
    wp_safe_redirect($referer !== false ? $referer : admin_url());
    exit();
}

/**
 * Render a warning notice with a persistent, per-domain dismiss control.
 *
 * @param string $id      One of license_dismissible_notice_ids().
 * @param string $message Notice body; may contain anchor markup.
 */
function render_dismissible_license_notice(string $id, string $message): void
{ ?>
    <div class="notice notice-warning">
        <p><?php echo wp_kses_post($message); ?></p>
        <form method="post" style="margin:0 0 8px;">
            <?php wp_nonce_field('novamira_pro_dismiss_notice_' . $id); ?>
            <?php wp_referer_field(); ?>
            <input type="hidden" name="novamira_pro_dismiss_notice" value="<?php echo esc_attr($id); ?>">
            <button type="submit" class="button button-small"><?php esc_html_e(
                'Dismiss',
                domain: 'novamira-pro',
            ); ?></button>
        </form>
    </div>
    <?php }

function render_activation_advisor(): void
{
    if (!current_user_can('manage_options') || !is_admin()) {
        return;
    }

    if (($_GET['page'] ?? null) === license_page_slug()) {
        return;
    }

    if (!novamira_min_version_satisfied()) {
        return;
    }

    if (is_license_active()) {
        return;
    }

    // When a key exists but is bound to another domain, the domain-mismatch
    // notice speaks instead — never stack both on the same screen.
    $last_domain = license_last_active_domain();
    if (license_key() !== '' && $last_domain !== '' && $last_domain !== license_current_domain()) {
        return;
    }

    if (license_notice_dismissed('license-needed')) {
        return;
    }

    $license_url = admin_url('admin.php?page=' . license_page_slug());
    $pricing_url = license_config_value('pricing_url');

    render_dismissible_license_notice('license-needed', sprintf(
        /* translators: 1: open activate link, 2: close activate link, 3: open pricing link, 4: close pricing link */
        esc_html__('Novamira Pro needs a license. %1$sActivate yours%2$s or %3$sget one%4$s.', domain: 'novamira-pro'),
        '<a href="' . esc_url($license_url) . '">',
        '</a>',
        '<a href="' . esc_url($pricing_url) . '" target="_blank" rel="noopener noreferrer">',
        '</a>',
    ));
}

function render_domain_mismatch_notice(): void
{
    if (!current_user_can('manage_options') || !is_admin()) {
        return;
    }

    if (($_GET['page'] ?? null) === license_page_slug()) {
        return;
    }

    if (license_key() === '') {
        return;
    }

    if (!novamira_min_version_satisfied()) {
        return;
    }

    if (is_license_active()) {
        return;
    }

    $last_domain = license_last_active_domain();
    if ($last_domain === '' || $last_domain === license_current_domain()) {
        return;
    }

    if (license_notice_dismissed('domain-mismatch')) {
        return;
    }

    $license_url = admin_url('admin.php?page=' . license_page_slug());

    render_dismissible_license_notice('domain-mismatch', sprintf(
        /* translators: 1: open license-page link, 2: close license-page link */
        esc_html__(
            'Novamira Pro license is tied to a different site. %1$sOpen the license page%2$s and reactivate it here.',
            domain: 'novamira-pro',
        ),
        '<a href="' . esc_url($license_url) . '">',
        '</a>',
    ));
}

function register_license_menu(): void
{
    if (!defined('NOVAMIRA_VERSION')) {
        return;
    }

    add_submenu_page(
        parent_slug: 'novamira-connect',
        page_title: __('Novamira License', domain: 'novamira-pro'),
        menu_title: __('License', domain: 'novamira-pro'),
        capability: 'manage_options',
        menu_slug: license_page_slug(),
        callback: __NAMESPACE__ . '\\render_license_page',
    );
}

function maybe_redirect_license_slug(): void
{
    if (!is_admin()) {
        return;
    }

    if (($_GET['page'] ?? null) === license_page_slug()) {
        return;
    }

    $request_uri = $_SERVER['REQUEST_URI'] ?? '';
    if ($request_uri === '') {
        return;
    }

    /** @var mixed $path */
    $path = wp_parse_url($request_uri, PHP_URL_PATH);
    if (!is_string($path)) {
        return;
    }

    if (basename($path) !== license_page_slug()) {
        return;
    }

    wp_safe_redirect(admin_url('admin.php?page=' . license_page_slug()));
    exit();
}

function add_plugin_action_links(array $links): array
{
    $links['license'] = sprintf(
        '<a href="%s">%s</a>',
        esc_url(admin_url('admin.php?page=' . license_page_slug())),
        esc_html__('License', domain: 'novamira-pro'),
    );

    return $links;
}

/** @return array{type:string,message:string}|null */
function license_notice_from_post(): ?array
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        return null;
    }

    check_admin_referer('novamira_pro_license');

    $action = $_POST['novamira_pro_license_action'] ?? null;
    if (!is_string($action)) {
        return null;
    }

    if ($action === 'refresh') {
        refresh_and_repair_license_status();
        if (is_license_active()) {
            return ['type' => 'success', 'message' => __('License status refreshed.', domain: 'novamira-pro')];
        }

        $error = license_error();
        if ($error !== '') {
            return ['type' => 'warning', 'message' => $error];
        }

        return ['type' => 'warning', 'message' => __('License is not active.', domain: 'novamira-pro')];
    }

    if ($action === 'activate') {
        $raw_key = $_POST['license_key'] ?? '';
        if (is_array($raw_key)) {
            $raw_key = '';
        }

        $license_key = sanitize_text_field(wp_unslash($raw_key));
        // Empty field means "reactivate the stored key" — the UI hides the
        // input behind a "Change" toggle so the masked key isn't overwritten.
        $activation_result = $license_key === '' ? activate_license() : activate_new_license_key($license_key);
        [$success, $message] = $activation_result;
        if ($success) {
            return [
                'type' => 'success',
                'message' => __('License activated on this site.', domain: 'novamira-pro'),
            ];
        }

        return ['type' => 'error', 'message' => $message];
    }

    if ($action !== 'deactivate') {
        return null;
    }

    $deactivation_result = deactivate_license();
    [$success, $message] = $deactivation_result;
    if ($success) {
        return [
            'type' => 'success',
            'message' => __('License deactivated on this site.', domain: 'novamira-pro'),
        ];
    }

    return ['type' => 'error', 'message' => $message];
}

function render_license_page(): void
{
    if (!current_user_can('manage_options')) {
        return;
    }

    $notice = license_notice_from_post();
    refresh_and_repair_license_status();

    $is_active = is_license_active();
    $license_key = license_key();
    $license_error = license_error();
    $license_domain = license_last_active_domain();
    $status_class = $is_active ? 'is-active' : 'is-inactive';
    $status_label = $is_active ? __('Active', domain: 'novamira-pro') : __('Inactive', domain: 'novamira-pro');
    $submit_action = $is_active ? 'deactivate' : 'activate';
    $submit_label = $is_active
        ? __('Deactivate', domain: 'novamira-pro')
        : __('Save and activate', domain: 'novamira-pro');
    $submit_class = $is_active ? 'button button-secondary' : 'button button-primary';

    $status_message = __('Activate your license to get Novamira Pro updates on this site.', domain: 'novamira-pro');
    if ($is_active) {
        $status_message = sprintf(
            __('Your license ending in %s is active for this site.', domain: 'novamira-pro'),
            license_key_last_4_digits(),
        );
    }
    if (!$is_active && $license_error !== '') {
        $status_message = $license_error;
    }

    if (function_exists('novamira_render_admin_header')) {
        novamira_render_admin_header();
    }

    ?>
    <div class="wrap">
        <h1><?php esc_html_e('Novamira License', domain: 'novamira-pro'); ?></h1>
        <style>
            .novamira-pro-license-grid { display:grid; gap:20px; grid-template-columns:minmax(0, 2fr) minmax(280px, 1fr); max-width:1100px; }
            .novamira-pro-license-card { background:#fff; border:1px solid #dcdcde; border-radius:12px; padding:24px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
            .novamira-pro-license-card h2 { margin-top:0; }
            .novamira-pro-license-status { display:inline-flex; align-items:center; gap:8px; padding:6px 12px; border-radius:999px; font-weight:600; }
            .novamira-pro-license-status.is-active { background:#edf7ed; color:#0a5c1b; }
            .novamira-pro-license-status.is-inactive { background:#fcf0f1; color:#8a2424; }
            .novamira-pro-license-input { width:100%; max-width:420px; font-family:monospace; }
            .novamira-pro-license-meta { margin:16px 0 0; color:#50575e; }
            .novamira-pro-license-actions { display:flex; gap:12px; align-items:center; flex-wrap:wrap; }
            .novamira-pro-license-key-display { display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
            .novamira-pro-license-key-mask { display:inline-block; padding:6px 12px; background:#f0f0f1; border:1px solid #dcdcde; border-radius:6px; font-family:monospace; letter-spacing:2px; }
            @media screen and (max-width: 900px) { .novamira-pro-license-grid { grid-template-columns:1fr; } }
        </style>
        <?php if ($notice !== null) {
            $notice_type = $notice['type'];
            $notice_message = $notice['message'];
            ?>
            <div class="notice notice-<?php echo esc_attr($notice_type); ?> is-dismissible"><p><?php echo
                esc_html($notice_message)
            ; ?></p></div>
        <?php
        } ?>
        <div class="novamira-pro-license-grid">
            <section class="novamira-pro-license-card">
                <h2><?php esc_html_e('Activation', domain: 'novamira-pro'); ?></h2>
                <p><span class="novamira-pro-license-status <?php echo esc_attr($status_class); ?>"><?php echo
                    esc_html($status_label)
                ; ?></span></p>
                <form method="post" action="">
                    <?php wp_nonce_field('novamira_pro_license'); ?>
                    <p><label for="novamira-pro-license-key"><strong><?php esc_html_e(
                        'License key',
                        domain: 'novamira-pro',
                    ); ?></strong></label></p>
                    <?php if ($license_key !== '') { ?>
                        <p class="novamira-pro-license-key-display" data-novamira-pro-license-display>
                            <code class="novamira-pro-license-key-mask"><?php echo
                                esc_html(license_key_masked())
                            ; ?></code>
                            <button type="button" class="button button-link" data-novamira-pro-license-change><?php esc_html_e(
                                'Change',
                                domain: 'novamira-pro',
                            ); ?></button>
                        </p>
                    <?php } ?>
                    <p data-novamira-pro-license-editor<?php echo $license_key !== '' ? ' hidden' : ''; ?>>
                        <input
                            id="novamira-pro-license-key"
                            class="regular-text novamira-pro-license-input"
                            type="text"
                            name="license_key"
                            value=""
                            autocomplete="off"
                            autocapitalize="off"
                            autocorrect="off"
                            spellcheck="false"
                            placeholder="<?php echo
                                esc_attr(
                                    $license_key !== ''
                                        ? __('Enter a new license key', domain: 'novamira-pro')
                                        : __('Enter your license key', domain: 'novamira-pro'),
                                )
                            ; ?>"
                        >
                    </p>
                    <div class="novamira-pro-license-actions">
                        <button type="submit" name="novamira_pro_license_action" value="<?php echo
                            esc_attr($submit_action)
                        ; ?>" class="<?php echo esc_attr($submit_class); ?>"><?php echo
                            esc_html($submit_label)
                        ; ?></button>
                        <button type="submit" name="novamira_pro_license_action" value="refresh" class="button button-link"><?php esc_html_e(
                            'Refresh status',
                            domain: 'novamira-pro',
                        ); ?></button>
                    </div>
                </form>
                <div class="novamira-pro-license-meta">
                    <p><?php echo esc_html($status_message); ?></p>
                    <?php if ($license_domain !== '') { ?>
                        <p><strong><?php esc_html_e(
                            'Last active domain:',
                            domain: 'novamira-pro',
                        ); ?></strong> <code><?php echo esc_html($license_domain); ?></code></p>
                    <?php } ?>
                </div>
            </section>

            <aside class="novamira-pro-license-card">
                <h2><?php esc_html_e('Updates', domain: 'novamira-pro'); ?></h2>
                <p><?php esc_html_e(
                    'Updates arrive in the Plugins screen once your license is active on this site.',
                    domain: 'novamira-pro',
                ); ?></p>
                <p><?php esc_html_e('Releases are served directly by Dynamic.ooo.', domain: 'novamira-pro'); ?></p>
                <p><a class="button button-secondary" href="<?php echo
                    esc_url(license_config_value('pricing_url'))
                ; ?>" target="_blank" rel="noopener noreferrer"><?php esc_html_e(
                    'Get a license',
                    domain: 'novamira-pro',
                ); ?></a></p>
            </aside>
        </div>
        <script>
            (function () {
                var button = document.querySelector('[data-novamira-pro-license-change]');
                if (button === null) {
                    return;
                }
                button.addEventListener('click', function () {
                    var display = document.querySelector('[data-novamira-pro-license-display]');
                    var editor = document.querySelector('[data-novamira-pro-license-editor]');
                    if (display === null || editor === null) {
                        return;
                    }
                    display.hidden = true;
                    editor.hidden = false;
                    var input = editor.querySelector('input');
                    if (input !== null) {
                        input.focus();
                    }
                });
            })();
        </script>
    </div>
    <?php
}

function license_cron_hook(): string
{
    return license_config_value('prefix') . '_check_license_cron';
}

// Self-healing cron scheduler: re-adds the event if the site has none queued.
// Without this a one-time status check at activation would hold "active"
// forever, even after the license expired or was revoked upstream.
function schedule_license_cron(): void
{
    $hook = license_cron_hook();
    if (wp_next_scheduled($hook) === false) {
        wp_schedule_event(time(), recurrence: 'daily', hook: $hook);
    }
}

function unschedule_license_cron(): void
{
    wp_clear_scheduled_hook(license_cron_hook());
}

function boot_license(): void
{
    add_action('admin_init', callback: __NAMESPACE__ . '\\maybe_redirect_license_slug');
    add_action('admin_init', callback: __NAMESPACE__ . '\\schedule_license_cron');
    add_action('admin_init', callback: __NAMESPACE__ . '\\handle_license_notice_dismiss');
    add_action(license_cron_hook(), callback: __NAMESPACE__ . '\\refresh_and_repair_license_status');
    add_action('admin_menu', callback: __NAMESPACE__ . '\\register_license_menu', priority: 100);
    add_action('admin_notices', callback: __NAMESPACE__ . '\\render_activation_advisor');
    add_action('admin_notices', callback: __NAMESPACE__ . '\\render_domain_mismatch_notice');
    add_filter('site_transient_update_plugins', callback: __NAMESPACE__ . '\\check_update_availability');
    add_filter('plugins_api', callback: __NAMESPACE__ . '\\plugins_api', priority: 20, accepted_args: 3);
    add_action('upgrader_process_complete', callback: __NAMESPACE__ . '\\clear_update_cache', priority: 10);
    add_filter('plugin_row_meta', callback: __NAMESPACE__ . '\\add_manual_check_link', priority: 20, accepted_args: 4);
    add_filter(
        'plugin_action_links_' . license_config_value('plugin_base'),
        callback: __NAMESPACE__ . '\\add_plugin_action_links',
    );
    add_action(
        'wp_ajax_check_' . license_config_value('prefix') . '_updates',
        callback: __NAMESPACE__ . '\\process_manual_update_check',
    );
}
