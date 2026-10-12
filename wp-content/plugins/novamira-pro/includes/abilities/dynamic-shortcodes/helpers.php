<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicShortcodes;

use DynamicShortcodes\Plugin;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Lint a Dynamic Shortcode `code` string, returning a WP_Error that carries the
 * specific lint errors when it is invalid, or null when it passes. Shared by
 * create-/edit-power-shortcode so a syntactically broken definition is never
 * stored and the agent gets the exact errors to fix and retry.
 */
function dsh_lint_code_or_error(string $code, string $name): ?WP_Error
{
    $lint = Plugin::instance()->shortcodes_manager->lint($code);
    if ($lint['valid'] === true) {
        return null;
    }

    return new WP_Error(
        'invalid_shortcode_syntax',
        sprintf("Power Shortcode '%s' code has syntax errors: %s", $name, implode('; ', $lint['errors'])),
        ['errors' => $lint['errors']],
    );
}

/**
 * Apply an edit to the named Power Shortcode within the stored list, returning
 * the updated list plus the previous code/description, or null when the name is
 * not present. Extracted from edit-power-shortcode to keep that callback simple.
 *
 * @param list<mixed> $existing
 * @param array{name?: string, code?: string, description?: string} $input
 * @return array{list: list<mixed>, previous_code: string, previous_description: string}|null
 */
function dsh_apply_power_edit(array $existing, string $name, array $input): ?array
{
    // @mago-expect analysis:mixed-assignment
    foreach ($existing as $idx => $item) {
        if (!is_array($item) || ($item['name'] ?? null) !== $name) {
            continue;
        }
        $previous_code = (string) ($item['code'] ?? '');
        $previous_description = (string) ($item['description'] ?? '');

        if (isset($input['code'])) {
            $item['code'] = $input['code'];
        }
        if (isset($input['description'])) {
            $item['description'] = $input['description'];
        }
        $existing[$idx] = $item;

        return [
            'list' => $existing,
            'previous_code' => $previous_code,
            'previous_description' => $previous_description,
        ];
    }

    return null;
}

/**
 * Build the site-context string injected into every ability's
 * annotations.instructions so the agent knows, on every call, which DSH
 * types are active and which dependency plugins are detected.
 *
 * Falls back to an honest "unavailable" line when the runtime gate is not
 * satisfied (matters for check-setup, which is registered even then).
 */
function dsh_site_context(): string
{
    if (!dsh_min_runtime_available()) {
        return 'Site context: Dynamic Shortcodes is not active or below the minimum supported version.';
    }

    $plugin = Plugin::instance();
    $types_accepted = $plugin->demo_manager->get_types_accepted();
    $types_str = $types_accepted === [] ? 'none' : implode(', ', $types_accepted);

    $available = [];
    foreach (['acf', 'woocommerce', 'jet-engine', 'metabox', 'pods', 'toolset', 'acpt'] as $dep) {
        try {
            if ($plugin->shortcodes_manager->check_plugin_dependency($dep)) {
                $available[] = $dep;
            }
        } catch (\Throwable) {
            // Unknown dependency name — skip.
            continue;
        }
    }
    $plugins_str = $available === [] ? 'none' : implode(', ', $available);

    return sprintf(
        'Site context: active_types=[%s], available_plugins=[%s], power_shortcodes=enabled',
        $types_str,
        $plugins_str,
    );
}

/**
 * Compose an ability `instructions` string with the live site context
 * appended. Used by every ability registration in this scope.
 */
function dsh_make_instructions(string $base): string
{
    return $base . "\n\n" . dsh_site_context();
}

/**
 * Restore the global $post to a value captured before a temporary post-context
 * switch. wp_reset_postdata() alone is insufficient — it restores $post from the
 * MAIN query, not from the caller's prior global — so an ability that set post
 * context would otherwise leak a corrupted $post back to the caller (page
 * builders render in a loop where $GLOBALS['post'] is load-bearing).
 *
 * @param mixed $prev_post the value of $GLOBALS['post'] captured before the switch
 */
function dsh_restore_post(mixed $prev_post): void
{
    // phpcs:ignore WordPress.WP.GlobalVariablesOverride
    $GLOBALS['post'] = $prev_post;
    if ($prev_post instanceof \WP_Post) {
        setup_postdata($prev_post);
        return;
    }
    wp_reset_postdata();
}

/**
 * Return the first field name whose value is not valid UTF-8, or null when all
 * are clean. The Power Shortcode store is persisted via wp_json_encode(), which
 * uses JSON_PARTIAL_OUTPUT_ON_ERROR and would silently replace invalid bytes
 * with "?" instead of failing — so invalid UTF-8 must be rejected up front.
 *
 * @param array<string, string> $fields
 */
function dsh_first_invalid_utf8(array $fields): ?string
{
    foreach ($fields as $name => $value) {
        if (!mb_check_encoding($value, encoding: 'UTF-8')) {
            return $name;
        }
    }
    return null;
}

/**
 * Validate a Power Shortcode name against the identifier rules Dynamic
 * Shortcodes enforces (start with a letter, then letters/digits/_/-), returning
 * a clear, actionable message when invalid or null when fine. Catches the common
 * naming mistakes (spaces, a leading digit, punctuation) up front instead of the
 * opaque save_failed the underlying store would otherwise return.
 */
function dsh_invalid_power_name(string $name): ?string
{
    if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name) !== 1) {
        return sprintf(
            'Invalid Power Shortcode name "%s". It must start with a letter and contain only letters, digits, "_" or "-" (convention: lowercase-with-hyphens, e.g. "welcome-banner").',
            $name,
        );
    }
    return null;
}

/**
 * Read which content surfaces Dynamic Shortcodes resolves shortcodes in. EVERY
 * toggle defaults to false, so a fresh install resolves shortcodes nowhere until
 * the user enables areas in the DSH settings — the usual reason a shortcode
 * renders as literal text (especially inside builders). Keys are friendly;
 * values come straight from DSH's own per-area options.
 *
 * @return array<string, bool>
 */
function dsh_enabled_areas(): array
{
    $map = [
        'post_title' => 'enabled_in_post_title',
        'post_content' => 'enabled_in_post_content',
        'post_excerpt' => 'enabled_in_post_excerpt',
        'gutenberg_blocks' => 'enabled_in_blocks_content',
        'text_widget' => 'enabled_in_widget_text',
        'elementor_dynamic_tag' => 'enabled_in_elementor_dynamic_tag',
        'elementor_widgets' => 'enabled_in_elementor_widgets_v',
        'bricks' => 'enabled_in_bricks',
        'oxygen' => 'enabled_in_oxygen',
        'breakdance' => 'enabled_in_breakdance',
        'wpbakery' => 'enabled_in_wpbakery',
    ];
    $areas = [];
    foreach ($map as $key => $option) {
        // @mago-expect analysis:mixed-operand
        $areas[$key] = (bool) get_option('dynamic_shortcodes_' . $option, default_value: false);
    }
    return $areas;
}

/**
 * Detect which of the Dynamic Shortcodes dependency plugins are live, using
 * the plugin's own dependency check. Extracted from check-setup to keep that
 * function under mago's halstead-effort threshold.
 *
 * @return list<string>
 */
function dsh_detect_dependency_plugins(\DynamicShortcodes\Core\Shortcodes\Manager $manager): array
{
    $available = [];
    foreach (['acf', 'woocommerce', 'jet-engine', 'metabox', 'pods', 'toolset', 'acpt'] as $dep) {
        try {
            if ($manager->check_plugin_dependency($dep)) {
                $available[] = $dep;
            }
        } catch (\Throwable) {
            // Unknown dependency name — skip.
            continue;
        }
    }
    return $available;
}

/**
 * Per-site advisory-lock key for serialising Power Shortcode store writes.
 * Includes the blog id so the server-scoped MySQL lock does not collide across
 * sites on multisite.
 */
function dsh_power_lock_key(): string
{
    return 'novamira_dsh_power_' . get_current_blog_id();
}

/**
 * Acquire the Power Shortcode store write lock (best-effort, 5s timeout) so two
 * concurrent writers cannot clobber the shared option (get -> modify -> save is
 * otherwise a last-writer-wins lost update). Best-effort: the caller proceeds
 * whether or not the lock is granted (a timeout, or a DB without GET_LOCK,
 * degrades to the prior unserialised behaviour rather than blocking the write).
 * The read MUST happen after this; pair every call with dsh_power_lock_release()
 * in a finally.
 */
function dsh_power_lock_acquire(): void
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $query = $wpdb->prepare('SELECT GET_LOCK(%s, %d)', dsh_power_lock_key(), 5);
    if (is_string($query)) {
        $wpdb->query($query);
    }
}

/**
 * Release the Power Shortcode store write lock. Safe to call unconditionally:
 * MySQL RELEASE_LOCK only releases a lock held by this same session, so a call
 * after a failed or best-effort acquire is a harmless no-op.
 */
function dsh_power_lock_release(): void
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $query = $wpdb->prepare('SELECT RELEASE_LOCK(%s)', dsh_power_lock_key());
    if (is_string($query)) {
        $wpdb->query($query);
    }
}
