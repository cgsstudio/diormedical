<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Visual;

if (!defined('ABSPATH')) {
    exit();
}

define(constant_name: 'NOVAMIRA_PRO_VISUAL_VERSION', value: NOVAMIRA_PRO_VERSION);
define(constant_name: 'NOVAMIRA_PRO_VISUAL_PLUGIN_DIR', value: __DIR__ . '/');
define(constant_name: 'NOVAMIRA_PRO_VISUAL_PLUGIN_URL', value: plugin_dir_url(NOVAMIRA_PRO_FILE) . 'novamira-visual/');

require_once __DIR__ . '/includes/Api.php';

new \NovamiraVisual\Api();

function asset_version(string $asset_file): string
{
    $modified_time = file_exists($asset_file) ? filemtime($asset_file) : false;

    return is_int($modified_time) ? (string) $modified_time : (string) NOVAMIRA_PRO_VISUAL_VERSION;
}

/**
 * Keep the Elementor editor drivable from the Novamira Visual workspace.
 *
 * Elementor 4.1+ sends a `Document-Isolation-Policy: isolate-and-credentialless`
 * header on both the editor screen and its preview iframe (see
 * \Elementor\Core\Editor\Editor::send_document_isolation_policy_header()). That
 * header places those documents in a cross-origin-isolated agent cluster, so
 * when the Visual workspace embeds the editor in an iframe, the workspace page
 * (which carries no such header) can no longer reach the editor window: every
 * synchronous frame access throws "Blocked a frame with origin ... from
 * accessing a cross-origin frame" and the page-tools bridge is dead.
 *
 * The policy is disabled whenever Novamira Visual is loaded rather than per
 * Visual session, because the header is sent while the editor page is being
 * generated (`admin_action_elementor`), before any workspace context is
 * knowable server-side — the workspace loads the plain editor URL into its
 * iframe with no marker to key on — and because the editor screen and the
 * preview iframe must agree on the header or Elementor's own editor <-> preview
 * frame access breaks. The result matches Elementor's pre-4.1 behavior; editor
 * features that need cross-origin isolation (e.g. SharedArrayBuffer-backed
 * media processing) are traded away for a workspace-drivable editor.
 *
 * Site owners who prefer Elementor's isolation, accepting that the Visual
 * workspace cannot drive the Elementor editor, can opt back in with:
 *
 *     add_filter( 'novamira/visual/use_elementor_document_isolation', '__return_true' );
 */
function filter_elementor_document_isolation(mixed $use_isolation): bool
{
    /**
     * Opt back into Elementor's editor document isolation policy.
     *
     * @param bool $opt_in Default false: Novamira Visual disables the policy
     *                     so the workspace can drive the editor. Return true
     *                     to hand the decision back to Elementor.
     */
    // @mago-expect analysis:mixed-assignment
    $opt_in = apply_filters('novamira/visual/use_elementor_document_isolation', value: false);
    if ($opt_in === true) {
        return $use_isolation === true;
    }

    return false;
}

add_filter('elementor/editor/use_document_isolation_policy', __NAMESPACE__ . '\filter_elementor_document_isolation');

/**
 * Enqueue the builder tool bundle inside the Elementor editor.
 */
add_action('elementor/editor/after_enqueue_scripts', static function (): void {
    $asset_file = NOVAMIRA_PRO_VISUAL_PLUGIN_DIR . 'dist/index.js';
    $version = asset_version($asset_file);

    wp_enqueue_script(
        'novamira-visual-elementor-tools',
        NOVAMIRA_PRO_VISUAL_PLUGIN_URL . 'dist/index.js',
        [],
        $version,
        args: true,
    );

    wp_localize_script('novamira-visual-elementor-tools', object_name: 'novamiraVisualData', l10n: [
        'restUrl' => esc_url_raw(rest_url('novamira-visual/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
});

/**
 * Enqueue the builder tool bundle inside the Bricks builder editor (main window only, not the preview iframe).
 */
add_action('wp_enqueue_scripts', static function (): void {
    if (!function_exists('bricks_is_builder_main') || !\bricks_is_builder_main()) {
        return;
    }

    $asset_file = NOVAMIRA_PRO_VISUAL_PLUGIN_DIR . 'dist/bricks.js';
    $version = asset_version($asset_file);

    wp_enqueue_script(
        'novamira-visual-bricks-tools',
        NOVAMIRA_PRO_VISUAL_PLUGIN_URL . 'dist/bricks.js',
        [],
        $version,
        args: true,
    );

    wp_localize_script('novamira-visual-bricks-tools', object_name: 'novamiraVisualData', l10n: [
        'restUrl' => esc_url_raw(rest_url('novamira-visual/v1')),
        'nonce' => wp_create_nonce('wp_rest'),
    ]);
});
