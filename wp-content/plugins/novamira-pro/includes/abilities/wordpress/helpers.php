<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WordPress;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Builder-content guard for the generic WP-core wrapper abilities.
 *
 * The wrappers must never write builder-managed content (owner policy): a
 * builder's layout belongs to its dedicated abilities, which validate the
 * structure and keep the page editable in that builder's editor. Agents that
 * never load a build-page skill sometimes bypass those abilities by writing
 * proprietary markup straight into post_content — on Divi that even destroyed
 * the page on the first Visual Builder open (the classic-content activation
 * wraps unmarked content in a divi/placeholder) — or by hand-writing a
 * builder's storage meta keys. Both are rejected with an error that names the
 * right ability, so the correction reaches the agent in-band.
 *
 * Markers and keys are storage facts verified against the builders this
 * plugin integrates: Divi 5 and Etch store block markup in post_content,
 * WPBakery stores shortcodes in post_content; Elementor, Bricks, Beaver
 * Builder, and Breakdance store their trees in postmeta.
 */

/**
 * Proprietary post_content markers.
 *
 * Block-based builders (Divi 5, Etch) are matched by a whitespace-aware regex,
 * not a literal, because WordPress's block grammar accepts any run of
 * whitespace (spaces, tabs, newlines) after `<!--` — a literal `<!-- wp:divi/`
 * would miss `<!--  wp:divi/` / `<!--\twp:divi/`, which still parse and render
 * as real builder blocks. Shortcode builders are matched by a plain substring
 * since their opening tag is prefix-stable.
 *
 * @var array<string, array{0: string, 1: string}>
 */
const NOVAMIRA_BUILDER_BLOCK_MARKERS = [
    'divi' => ['Divi 5', 'novamira/divi-set-content'],
    'etch' => ['Etch', 'novamira/etch-set-content'],
];

/**
 * @var array<string, array{0: string, 1: string}>
 */
const NOVAMIRA_BUILDER_SHORTCODE_MARKERS = [
    '[vc_row' => ['WPBakery', 'novamira/wpbakery-set-content'],
    '[et_pb_section' => ['Divi (legacy shortcodes)', 'novamira/divi-set-content'],
];

/**
 * Builder storage meta keys, mapped to the builder that owns them. Compared
 * case-insensitively (WordPress meta keys are stored verbatim but MySQL's
 * default collation matches them case-insensitively, so an uppercase variant
 * still pollutes "is this built with X" queries). List every key that stores a
 * builder's element tree, activation flag, or page settings — the keys are
 * conventionally lowercase, so keep them lowercase here. Ordinary post meta
 * must stay writable.
 *
 * @var array<string, string>
 */
const NOVAMIRA_BUILDER_STORAGE_META_KEYS = [
    // Element-tree / content storage.
    '_elementor_data' => 'Elementor',
    '_bricks_page_content_2' => 'Bricks',
    '_bricks_page_header_2' => 'Bricks',
    '_bricks_page_footer_2' => 'Bricks',
    '_fl_builder_data' => 'Beaver Builder',
    '_fl_builder_draft' => 'Beaver Builder',
    '_breakdance_data' => 'Breakdance',
    '_breakdance_template_settings' => 'Breakdance',
    // Activation flags (writing these flips a page into a builder without its tree).
    '_elementor_edit_mode' => 'Elementor',
    '_fl_builder_enabled' => 'Beaver Builder',
    '_bricks_editor_mode' => 'Bricks',
    '_et_pb_use_builder' => 'Divi',
    '_et_pb_use_divi_5' => 'Divi',
    '_et_pb_old_content' => 'Divi',
    '_et_pb_show_page_creation' => 'Divi',
    // Page/settings blobs.
    '_elementor_page_settings' => 'Elementor',
    '_elementor_css' => 'Elementor',
    '_bricks_page_settings' => 'Bricks',
    '_fl_builder_data_settings' => 'Beaver Builder',
    '_fl_builder_draft_settings' => 'Beaver Builder',
    // NOTE: _et_pb_page_layout is deliberately NOT here — it is Divi's per-post
    // theme layout (sidebar / full-width) on ordinary non-builder pages, not
    // element-tree storage, and there is no builder ability that owns it.
];

/**
 * Run both builder guards against a wrapper ability's input (after alias
 * normalization): the `content` field against the markup markers and the
 * `meta` map against the storage-key blocklist. Returns the rejection error,
 * or null when the write may proceed.
 *
 * @param array<string, mixed> $input
 */
function wordpress_builder_write_guard(array $input): ?WP_Error
{
    if (array_key_exists('content', $input)) {
        $guard = wordpress_builder_content_error((string) $input['content']);
        if ($guard !== null) {
            return $guard;
        }
    }

    if (is_array($input['meta'] ?? null)) {
        /** @var array<string, mixed> $meta_map */
        $meta_map = $input['meta'];
        return wordpress_builder_meta_error($meta_map);
    }

    return null;
}

/**
 * True when a normalized wrapper input carries a non-empty post_content write.
 *
 * @param array<string, mixed> $input
 */
function wordpress_has_nonempty_content(array $input): bool
{
    return array_key_exists('content', $input) && (string) $input['content'] !== '';
}

/**
 * True when the active runtime is Breakdance-capable.
 */
function wordpress_breakdance_active(): bool
{
    return defined('__BREAKDANCE_VERSION') && function_exists('Breakdance\\Data\\get_global_option');
}

/**
 * True when Breakdance owns the target post.
 */
function wordpress_breakdance_owns_post(int $post_id): bool
{
    if (!wordpress_breakdance_active() || !function_exists('Breakdance\\Admin\\get_mode')) {
        return false;
    }
    /** @var mixed $mode */
    $mode = \Breakdance\Admin\get_mode($post_id);
    return $mode === 'breakdance';
}

/**
 * Require explicit user confirmation before a generic wrapper writes non-empty
 * post_content on a Breakdance site (create) or to a Breakdance-owned post
 * (update). The opt-in is deliberately ability-specific so an agent cannot
 * infer permission from an unrelated code-write approval.
 *
 * @param array<string, mixed> $input Normalized wrapper input.
 */
function wordpress_breakdance_content_gate(array $input, ?int $post_id = null): ?WP_Error
{
    if (!wordpress_has_nonempty_content($input) || !wordpress_breakdance_active()) {
        return null;
    }
    if ($post_id !== null && !wordpress_breakdance_owns_post($post_id)) {
        return null;
    }
    if (($input['allow_raw_content_on_breakdance_post'] ?? false) === true) {
        return null;
    }

    $ownership = $post_id === null
        ? 'while Breakdance is active on this site'
        : sprintf('on Breakdance-owned post %d', $post_id);

    return wordpress_error('breakdance_post_content_needs_confirmation', sprintf(
        'This write would store non-empty raw HTML/content in WordPress post_content %1$s. '
        . 'Breakdance renders its native element tree instead, so this content can bypass the editable '
        . 'builder canvas and may not render as intended. Leave post_content empty and build with '
        . 'novamira/breakdance-set-content plus the novamira/breakdance-* element abilities. Only if '
        . 'the user still wants raw WordPress content, re-call with '
        . 'allow_raw_content_on_breakdance_post: true only after the user has EXPLICITLY confirmed '
        . '— do not set that flag on your own.',
        $ownership,
    ));
}

/**
 * Keep an in-band audit trace after a confirmed raw-content write.
 *
 * For creates, Breakdance is active but does not own the new post yet, so the
 * note stays softer than the warning attached to an owned-post update.
 *
 * @param array<string, mixed> $input Normalized wrapper input.
 * @return list<string>
 */
function wordpress_breakdance_content_warnings(array $input, ?int $post_id = null): array
{
    if (
        !wordpress_has_nonempty_content($input)
        || !wordpress_breakdance_active()
        || ($input['allow_raw_content_on_breakdance_post'] ?? false) !== true
    ) {
        return [];
    }

    if ($post_id === null) {
        return [
            'AUDIT NOTE — Breakdance is active on this site. After explicit confirmation, the initial content '
                . 'was saved as ordinary WordPress post_content. If this post is intended for Breakdance, build '
                . 'the canvas with novamira/breakdance-set-content and the other novamira/breakdance-* abilities.',
        ];
    }

    if (!wordpress_breakdance_owns_post($post_id)) {
        return [];
    }

    return [
        'AUDIT — Raw post_content was written to a Breakdance-owned post after explicit confirmation. It does '
            . 'not update the Breakdance element tree and may not render with the builder canvas. Prefer '
            . 'novamira/breakdance-set-content and the other novamira/breakdance-* abilities.',
    ];
}

/**
 * Reject post_content that carries a builder's proprietary markup. Returns
 * the rejection error, or null when the content is ordinary (plain HTML and
 * native Gutenberg blocks pass).
 */
function wordpress_builder_content_error(string $content): ?WP_Error
{
    foreach (NOVAMIRA_BUILDER_BLOCK_MARKERS as $slug => [$builder, $ability]) {
        // Mirror WordPress's block opening delimiter: "<!--", one-or-more
        // whitespace, an optional void-close "/", then the lowercase namespace.
        if (preg_match('#<!--\s+/?wp:' . preg_quote($slug, delimiter: '#') . '/#', $content) === 1) {
            return wordpress_builder_content_rejected($builder, $ability);
        }
    }
    foreach (NOVAMIRA_BUILDER_SHORTCODE_MARKERS as $marker => [$builder, $ability]) {
        // Anchor to the start of a line (optional leading whitespace): a real
        // shortcode-builder page BEGINS its content with the wrapper shortcode,
        // whereas prose that merely mentions "[vc_row]" in a sentence or a code
        // block has it mid-line — matching that would refuse to save a tutorial
        // ABOUT the builder. The brackets aren't HTML-escaped in a code block,
        // so a substring test can't tell the two apart; a line-start anchor can.
        if (preg_match('#^[ \t]*' . preg_quote($marker, delimiter: '#') . '#m', $content) === 1) {
            return wordpress_builder_content_rejected($builder, $ability);
        }
    }
    return null;
}

/**
 * Canonical HTTP status for every error code raised by the WordPress core
 * wrapper abilities. Without an explicit status the REST layer answers 500,
 * telling the caller a definitive refusal such as an unknown post id is a
 * transient server fault worth retrying.
 *
 * Unknown codes stay 500, so a new call site cannot be silently labelled
 * caller-correctable by omission.
 *
 * Scope: this covers the errors an ability itself returns. WP_Ability::execute()
 * runs the callback and only then validates its output, so a response that
 * fails its own output schema reaches the caller as an error for a write that
 * has already been committed. That failure is produced outside the callback,
 * never passes through this map, and is a different problem — tracked
 * separately as validate-before-commit. Nothing here should be read as
 * covering it.
 *
 * @return array<string, int>
 */
function wordpress_error_map(): array
{
    return [
        // The caller can fix these by changing the input and calling again.
        'invalid_attachment' => 400,
        'invalid_post_type' => 400,
        'missing_id' => 400,
        'missing_title' => 400,
        // The addressed post genuinely does not exist.
        'not_found' => 404,
        // The post is already in the trash, so trashing it again cannot
        // succeed. Definitive, and the caller resolves it by passing force or
        // restoring the post first.
        'already_trashed' => 409,
        // Understood, well-formed, and refused on policy — the caller must
        // change approach rather than retry. Matches the builder guards below.
        'builder_content_rejected' => 422,
        'builder_meta_rejected' => 422,
        'breakdance_post_content_needs_confirmation' => 422,
        // WordPress refused a write. The status is decided per WordPress error
        // code by wordpress_wp_post_error_status(); these are the fallbacks for
        // a refusal we could not classify.
        'post_create_failed' => 500,
        'post_update_failed' => 500,
        // WordPress accepted the request and the operation still failed; the
        // caller has nothing to correct.
        'delete_failed' => 500,
        'thumbnail_failed' => 500,
    ];
}

/**
 * Wrap a WP_Error returned by wp_insert_post()/wp_update_post(). Handed on
 * untouched these carry no status, so a refusal WordPress raised over the
 * caller's own field values — an unparseable date, an unknown page template —
 * reached the caller as a server fault.
 *
 * The original code and data stay available under `wp_code` / `wp_data` so the
 * refusal is still diagnosable without leaking an unnamespaced error.
 *
 * @param 'create'|'update' $operation
 */
function wordpress_wp_post_error(WP_Error $error, string $operation): WP_Error
{
    $wp_code = (string) $error->get_error_code();
    /** @var mixed $wp_data */
    $wp_data = $error->get_error_data();
    $data = ['wp_code' => $wp_code];
    if ($wp_data !== null) {
        $data['wp_data'] = $wp_data;
    }

    return new WP_Error(
        $operation === 'create' ? 'post_create_failed' : 'post_update_failed',
        sprintf('WordPress refused to %s the post: %s', $operation, $error->get_error_message()),
        ['status' => wordpress_wp_post_error_status($wp_code, $wp_data)] + $data,
    );
}

/**
 * The REST status for a WordPress post-write refusal. An upstream status is
 * honoured; known input refusals are the caller's to fix; anything else stays a
 * real 500 rather than being guessed into a 4xx.
 */
function wordpress_wp_post_error_status(string $wp_code, mixed $wp_data): int
{
    if (is_array($wp_data)) {
        /** @var mixed $upstream_status */
        $upstream_status = $wp_data['status'] ?? null;
        if (is_int($upstream_status) && $upstream_status >= 400 && $upstream_status <= 599) {
            return $upstream_status;
        }
    }

    return match ($wp_code) {
        'empty_content', 'invalid_date', 'invalid_page_template', 'invalid_post_type' => 400,
        'invalid_post' => 404,
        default => 500,
    };
}

/**
 * Build a WP_Error carrying the HTTP status this surface has agreed on for the
 * code, so the REST layer stops reporting caller mistakes as server faults.
 *
 * @param array<string, mixed> $data
 */
function wordpress_error(string $code, string $message, array $data = []): WP_Error
{
    return new WP_Error($code, $message, ['status' => wordpress_error_map()[$code] ?? 500] + $data);
}

/**
 * The shared rejection error for builder markup in post_content.
 */
function wordpress_builder_content_rejected(string $builder, string $ability): WP_Error
{
    return wordpress_error('builder_content_rejected', sprintf(
        'The content contains %1$s builder markup. Never write builder-managed content '
        . 'through this ability: use %2$s (and that builder\'s other abilities) instead, '
        . 'so the layout is validated and the page stays editable in the builder.',
        $builder,
        $ability,
    ));
}

/**
 * Reject a meta map that hand-writes a builder's storage keys. Returns the
 * rejection error, or null when every key is ordinary post meta.
 *
 * @param array<string, mixed> $meta
 */
function wordpress_builder_meta_error(array $meta): ?WP_Error
{
    foreach (array_keys($meta) as $key) {
        $builder = NOVAMIRA_BUILDER_STORAGE_META_KEYS[strtolower($key)] ?? null;
        if ($builder === null) {
            continue;
        }
        return wordpress_error('builder_meta_rejected', sprintf(
            '"%1$s" is %2$s builder storage. Never hand-write builder storage meta through '
            . 'this ability: use the %2$s-specific abilities instead, so the data stays '
            . 'consistent and the page stays editable in the builder.',
            $key,
            $builder,
        ));
    }
    return null;
}
