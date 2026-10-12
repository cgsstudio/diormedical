<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\GhostMode;

if (!defined('ABSPATH')) {
    exit();
}

function admin_ui_visible(): bool
{
    return !function_exists('novamira_admin_ui_visible') || \novamira_admin_ui_visible();
}

const SAVE_ACTION = 'novamira_ghost_mode_save';

const ACKNOWLEDGEMENT_FIELD = 'ack_understood';

function runtime_available(): bool
{
    return (
        function_exists('Novamira\GhostMode\save_policy')
        && defined('Novamira\GhostMode\RUNTIME_VERSION')
        && (int) constant('Novamira\GhostMode\RUNTIME_VERSION') >= 1
    );
}

function boot(): void
{
    if (!runtime_available()) {
        return;
    }
    add_action(\Novamira\GhostMode\PANEL_HOOK, __NAMESPACE__ . '\\render_panel');
    add_action('admin_post_' . SAVE_ACTION, __NAMESPACE__ . '\\handle_save');
    Cli\register();
}

/**
 * @return list<\WP_User>
 */
function candidates(): array
{
    $users = is_multisite()
        ? multisite_candidates()
        : get_users(['capability' => 'manage_options', 'orderby' => 'display_name']);

    $candidates = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($users as $user) {
        if (!$user instanceof \WP_User || !\novamira_user_can_manage($user)) {
            continue;
        }
        $candidates[] = $user;
    }

    return $candidates;
}

/**
 * @param list<\WP_User> $users
 * @return list<\WP_User>
 */
function order_candidates_current_first(array $users, int $current_user_id): array
{
    $current = [];
    $others = [];
    foreach ($users as $user) {
        if ($user->ID === $current_user_id) {
            $current[] = $user;
            continue;
        }
        $others[] = $user;
    }

    return [...$current, ...$others];
}

/**
 * @param list<int> $visible_to
 */
function render_candidate_row(\WP_User $user, array $visible_to, int $current_user_id): void
{
    $is_you = $user->ID === $current_user_id;
    $checked = $is_you || in_array($user->ID, $visible_to, strict: true);
    ?>
    <li><label><input type="checkbox" name="visible_to[]" value="<?php echo esc_attr((string) $user->ID); ?>"<?php echo
        $checked ? ' checked' : ''
    ; ?><?php echo $is_you ? ' disabled' : ''; ?>> <?php echo
        esc_html(\Novamira\GhostMode\describe_user($user->ID))
    ; ?><?php echo $is_you ? esc_html__(' (you)', domain: 'novamira-pro') : ''; ?></label><?php if (
        $is_you
    ): ?><input type="hidden" name="visible_to[]" value="<?php echo
        esc_attr((string) $user->ID)
    ; ?>"><?php endif; ?></li>
    <?php
}

/**
 * @return list<\WP_User>
 */
function multisite_candidates(): array
{
    $users = [];
    foreach (get_super_admins() as $login) {
        $user = get_user_by('login', $login);
        if ($user instanceof \WP_User) {
            $users[] = $user;
        }
    }

    return $users;
}

/**
 * @return array<string, string>
 */
function acknowledgement_labels(): array
{
    return [
        'security' => __(
            'Ghost Mode is not a security feature: it changes what other administrators see, not what they can do.',
            domain: 'novamira-pro',
        ),
        'elsewhere' => __(
            'Novamira may still appear outside the WordPress dashboard, for example in your hosting plugin list.',
            domain: 'novamira-pro',
        ),
        'connections' => __(
            'Other administrators can still connect an AI, or keep the one they already have.',
            domain: 'novamira-pro',
        ),
        'updates' => __('Updates are up to the administrators on this list.', domain: 'novamira-pro'),
        'switch' => __('Other administrators lose the AI Abilities switch in the admin bar.', domain: 'novamira-pro'),
    ];
}

function result_url(string $result): string
{
    $query = ['novamira_ghost_mode_result' => $result];

    return add_query_arg($query, \Novamira\GhostMode\page_url());
}

function render_result_notice(): void
{
    $result = $_GET['novamira_ghost_mode_result'] ?? null;
    if (!is_string($result)) {
        return;
    }
    $messages = [
        'saved' => ['success', __('Ghost Mode settings saved.', domain: 'novamira-pro')],
        'saved_single' => [
            'warning',
            __(
                'Ghost Mode settings saved. Only one administrator can see Novamira: consider adding another, so access does not depend on a single account.',
                domain: 'novamira-pro',
            ),
        ],
        'ack' => [
            'error',
            __('Confirm that you understand how Ghost Mode works to turn it on.', domain: 'novamira-pro'),
        ],
        'license' => [
            'error',
            __('An active Novamira Pro license is needed to change Ghost Mode.', domain: 'novamira-pro'),
        ],
        'locked' => [
            'error',
            __(
                'These Ghost Mode settings cannot be changed here. You can still turn Ghost Mode off.',
                domain: 'novamira-pro',
            ),
        ],
        'no_owner' => ['error', __('Choose at least one administrator under Visible to.', domain: 'novamira-pro')],
        'self_missing' => [
            'error',
            __('Add yourself to Visible to, or you would lose access to Novamira.', domain: 'novamira-pro'),
        ],
        'invalid_owner' => [
            'error',
            __('Every administrator under Visible to must be able to manage Novamira.', domain: 'novamira-pro'),
        ],
    ];
    if (!array_key_exists($result, $messages)) {
        return;
    }
    [$type, $message] = $messages[$result];
    wp_admin_notice(esc_html($message), ['type' => $type]);
}

function render_panel(): void
{
    $policy = \Novamira\GhostMode\read_policy();
    $status = \Novamira\GhostMode\status();
    $licensed = \Novamira\Pro\is_license_active();
    $newer = $policy['schema_version'] > \Novamira\GhostMode\SCHEMA_VERSION;
    $readable = $status['state'] !== 'unreadable';
    $is_on = $status['state'] === 'on';
    $editable = $licensed && !$newer && $readable;
    $visible_to = $readable && $policy['visible_to'] !== [] ? $policy['visible_to'] : [get_current_user_id()];

    \novamira_render_admin_header();
    ?>
    <div class="wrap novamira-ghost-mode">
        <h1 class="wp-heading-inline"><?php esc_html_e('Ghost Mode', domain: 'novamira-pro'); ?></h1>
        <hr class="wp-header-end">
        <p class="novamira-gm-lead"><?php esc_html_e(
            'Show Novamira only to the administrators you choose.',
            domain: 'novamira-pro',
        ); ?></p>
        <?php \Novamira\GhostMode\render_disabled_notice(); ?>
        <?php render_result_notice(); ?>
        <?php render_license_notice($licensed, $is_on); ?>
        <?php render_schema_notice($newer); ?>
        <?php render_unreadable_notice($readable); ?>
        <?php if ($is_on && !$editable): ?>
            <?php render_status_card($status); ?>
        <?php endif; ?>
        <?php if ($editable): ?>
            <?php render_form($visible_to, $is_on, $status); ?>
        <?php endif; ?>
        <?php render_connections($is_on ? $status['owners'] : [get_current_user_id()]); ?>
        <?php if ($is_on || !$editable): ?>
            <?php \Novamira\GhostMode\render_security_callout(); ?>
        <?php endif; ?>
        <?php if ($is_on || !$readable): ?>
            <div class="novamira-gm-card novamira-gm-off">
                <h2><?php esc_html_e('Turn off Ghost Mode', domain: 'novamira-pro'); ?></h2>
                <?php \Novamira\GhostMode\render_disable_form(); ?>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// @mago-expect lint:no-boolean-flag-parameter -- $licensed and $is_on mirror the panel's own status fields.
function render_license_notice(bool $licensed, bool $is_on): void
{
    if ($licensed) {
        return;
    }
    if ($is_on) {
        ?>
        <p class="novamira-gm-warning"><?php esc_html_e(
            'Your Novamira Pro license is not active. Ghost Mode stays on and Novamira remains hidden from administrators not listed below. You can turn Ghost Mode off from this page at any time; to change the Visible to list or turn it back on, activate your license.',
            domain: 'novamira-pro',
        ); ?></p>
        <?php

        return;
    }
    ?>
    <p class="novamira-gm-warning"><?php echo
        sprintf(
            '%1$s <a href="%2$s">%3$s</a>.',
            esc_html__('An active Novamira Pro license is needed to turn on Ghost Mode.', domain: 'novamira-pro'),
            esc_url(admin_url('admin.php?page=novamira-pro-license')),
            esc_html__('Activate your license', domain: 'novamira-pro'),
        )
    ; ?></p>
    <?php
}

// @mago-expect lint:no-boolean-flag-parameter -- $newer mirrors the policy's own schema comparison.
function render_schema_notice(bool $newer): void
{
    if (!$newer) {
        return;
    }
    ?>
    <p class="novamira-gm-warning"><?php esc_html_e(
        'These Ghost Mode settings were written by a newer version of Novamira Pro. Update Novamira Pro to change them.',
        domain: 'novamira-pro',
    ); ?></p>
    <?php
}

// @mago-expect lint:no-boolean-flag-parameter -- $readable mirrors the status's own state check.
function render_unreadable_notice(bool $readable): void
{
    if ($readable) {
        return;
    }
    ?>
    <p class="novamira-gm-warning is-error"><?php esc_html_e(
        'The Ghost Mode settings cannot be read, so Novamira is hidden from every administrator. Turn Ghost Mode off to start over.',
        domain: 'novamira-pro',
    ); ?></p>
    <?php
}

/**
 * @param array{state: 'off'|'on'|'unreadable', bypass: bool, owners: list<int>, ignored_ids: list<int>, malformed: int, fail_closed: bool, updated_at: int, updated_by: int, source: string} $status
 */
function render_status_head(array $status): void
{ ?>
    <div class="novamira-gm-status-head">
        <h2><?php esc_html_e('Ghost Mode', domain: 'novamira-pro'); ?></h2>
        <span class="novamira-gm-pill <?php echo $status['fail_closed'] ? 'is-alert' : 'is-on'; ?>"><?php echo
            $status['fail_closed']
                ? esc_html__('Needs attention', domain: 'novamira-pro')
                : esc_html__('On', domain: 'novamira-pro')
        ; ?></span>
    </div>
    <?php }

/**
 * @param array{state: 'off'|'on'|'unreadable', bypass: bool, owners: list<int>, ignored_ids: list<int>, malformed: int, fail_closed: bool, updated_at: int, updated_by: int, source: string} $status
 */
function render_status_card(array $status): void
{ ?>
    <div class="novamira-gm-card">
        <?php render_status_head($status); ?>
        <p class="novamira-gm-label"><?php esc_html_e('Visible to', domain: 'novamira-pro'); ?></p>
        <ul class="novamira-gm-chips">
            <?php foreach ($status['owners'] as $owner_id): ?>
                <li><?php echo esc_html(\Novamira\GhostMode\describe_user($owner_id)); ?></li>
            <?php endforeach; ?>
        </ul>
        <?php \Novamira\GhostMode\render_status_details($status); ?>
    </div>
    <?php }

// @mago-expect lint:no-boolean-flag-parameter -- $is_on mirrors the panel's own status field.
/**
 * @param list<int> $visible_to
 * @param array{state: 'off'|'on'|'unreadable', bypass: bool, owners: list<int>, ignored_ids: list<int>, malformed: int, fail_closed: bool, updated_at: int, updated_by: int, source: string} $status
 */
function render_form(array $visible_to, bool $is_on, array $status): void
{ ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="novamira-gm-card">
        <input type="hidden" name="action" value="<?php echo esc_attr(SAVE_ACTION); ?>">
        <?php wp_nonce_field(SAVE_ACTION); ?>
        <?php if ($is_on): ?>
            <?php render_status_head($status); ?>
            <?php \Novamira\GhostMode\render_status_details($status); ?>
            <p class="novamira-gm-label"><?php esc_html_e('Visible to', domain: 'novamira-pro'); ?></p>
        <?php endif; ?>
        <?php if (!$is_on): ?>
            <h2><?php esc_html_e('Turn on Ghost Mode', domain: 'novamira-pro'); ?></h2>
        <?php endif; ?>
        <p><?php esc_html_e('Choose the administrators who keep seeing Novamira.', domain: 'novamira-pro'); ?></p>
        <ul class="novamira-gm-choices">
            <?php $current_user_id = get_current_user_id(); ?>
            <?php foreach (order_candidates_current_first(candidates(), $current_user_id) as $user): ?>
                <?php render_candidate_row($user, $visible_to, $current_user_id); ?>
            <?php endforeach; ?>
        </ul>
        <?php if (!$is_on): ?>
            <?php render_acknowledgements(); ?>
        <?php endif; ?>
        <div class="novamira-gm-actions">
            <?php if (!$is_on): ?>
                <label><input type="checkbox" name="<?php echo
                    esc_attr(ACKNOWLEDGEMENT_FIELD)
                ; ?>" value="1" required> <?php esc_html_e('I understand', domain: 'novamira-pro'); ?></label>
            <?php endif; ?>
            <?php submit_button(
                $is_on ? __('Save changes', domain: 'novamira-pro') : __('Turn on Ghost Mode', domain: 'novamira-pro'),
                type: 'primary',
                name: 'submit',
                wrap: false,
            ); ?>
        </div>
    </form>
    <?php }

function render_acknowledgements(): void
{ ?>
    <div class="novamira-gm-confirm-box">
        <p class="novamira-gm-confirm-title"><?php esc_html_e('Before you turn it on', domain: 'novamira-pro'); ?></p>
        <ul class="novamira-gm-list">
            <?php foreach (acknowledgement_labels() as $label): ?>
                <li><?php echo esc_html($label); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php }

/**
 * @param list<int> $excluded
 */
function render_connections(array $excluded): void
{
    $rows = array_filter(
        \Novamira\GhostMode\connected_users(),
        static fn(int $user_id): bool => (
            !in_array($user_id, $excluded, strict: true) && \novamira_user_can_manage($user_id)
        ),
        ARRAY_FILTER_USE_KEY,
    );
    if ($rows === []) {
        return;
    }
    ?>
    <div class="novamira-gm-card">
        <details class="novamira-gm-connections">
            <summary><?php echo
                esc_html(sprintf(
                    /* translators: %d: number of other administrators listed. */
                    __('AI connections of other administrators (%d)', domain: 'novamira-pro'),
                    count($rows),
                ))
            ; ?></summary>
            <?php render_connections_table($rows); ?>
            <p class="description"><?php esc_html_e(
                'Only Application Passwords whose name starts with Novamira are listed. Click a number to manage them in that administrator\'s profile.',
                domain: 'novamira-pro',
            ); ?></p>
        </details>
    </div>
    <?php
}

/**
 * @param array<int, array{oauth: list<string>, app_passwords: int}> $rows
 */
function render_connections_table(array $rows): void
{ ?>
    <table class="widefat striped">
        <thead><tr>
            <th><?php esc_html_e('Administrator', domain: 'novamira-pro'); ?></th>
            <th><?php esc_html_e('OAuth apps', domain: 'novamira-pro'); ?></th>
            <th><?php esc_html_e('Application Passwords', domain: 'novamira-pro'); ?></th>
        </tr></thead>
        <tbody>
            <?php foreach ($rows as $user_id => $connections): ?>
                <tr>
                    <td><?php echo esc_html(\Novamira\GhostMode\describe_user($user_id)); ?></td>
                    <td><?php echo esc_html(implode(', ', $connections['oauth'])); ?></td>
                    <td><?php render_app_password_count($user_id, $connections['app_passwords']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php }

function render_app_password_count(int $user_id, int $count): void
{
    if ($count === 0) {
        echo '0';

        return;
    }
    ?><a href="<?php echo esc_url(get_edit_user_link($user_id) . '#application-passwords-section'); ?>"><?php echo
        esc_html((string) $count)
    ; ?></a><?php
}

function handle_save(): void
{
    if (!\novamira_current_user_can_manage() || !admin_ui_visible()) {
        wp_die(esc_html__('Sorry, you are not allowed to access this page.', domain: 'default'), args: [
            'response' => 403,
        ]);
    }
    check_admin_referer(SAVE_ACTION);

    $policy = \Novamira\GhostMode\read_policy();
    $result = save_result($policy);

    wp_safe_redirect(result_url($result));
    exit();
}

/**
 * @param array{state: 'absent'|'ok'|'unreadable', enabled: bool, visible_to: list<int>, malformed: int, schema_version: int, updated_at: int, updated_by: int, source: string, pro_version: string} $policy
 */
function save_result(array $policy): string
{
    if (!\Novamira\Pro\is_license_active()) {
        return 'license';
    }
    if ($policy['state'] === 'unreadable' || $policy['schema_version'] > \Novamira\GhostMode\SCHEMA_VERSION) {
        return 'locked';
    }
    if (missing_acknowledgement($policy)) {
        return 'ack';
    }

    $raw = $_POST['visible_to'] ?? [];
    $error = \Novamira\GhostMode\save_policy(
        true,
        is_array($raw) ? $raw : [],
        get_current_user_id(),
        source: 'pro',
        pro_version: NOVAMIRA_PRO_VERSION,
    );
    if ($error instanceof \WP_Error) {
        return str_replace('novamira_ghost_mode_', replace: '', subject: (string) $error->get_error_code());
    }

    return count(\Novamira\GhostMode\read_policy()['visible_to']) === 1 ? 'saved_single' : 'saved';
}

/**
 * @param array{state: 'absent'|'ok'|'unreadable', enabled: bool, visible_to: list<int>, malformed: int, schema_version: int, updated_at: int, updated_by: int, source: string, pro_version: string} $policy
 */
function missing_acknowledgement(array $policy): bool
{
    $turning_on = !($policy['state'] === 'ok' && $policy['enabled']);
    if (!$turning_on) {
        return false;
    }
    return !array_key_exists(ACKNOWLEDGEMENT_FIELD, $_POST);
}
