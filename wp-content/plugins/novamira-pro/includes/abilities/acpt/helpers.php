<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Runtime snapshot of the ACPT installation. Cached per request.
 *
 * @return array{
 *     active: bool,
 *     version: string|null,
 *     min_satisfied: bool,
 *     license_active: bool,
 *     option_pages_enabled: bool,
 *     option_pages_will_render: bool,
 *     counts: array{custom_post_types: int, taxonomies: int, meta_groups: int, option_pages: int},
 * }
 */
function acpt_check_setup(): array
{
    /** @var null|array{
     *     active: bool,
     *     version: string|null,
     *     min_satisfied: bool,
     *     license_active: bool,
     *     option_pages_enabled: bool,
     *     option_pages_will_render: bool,
     *     counts: array{custom_post_types: int, taxonomies: int, meta_groups: int, option_pages: int},
     * } $cache
     */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    $active = defined('ACPT_PLUGIN_VERSION') && class_exists('ACPT\\Includes\\ACPT_Plugin');
    $version = defined('ACPT_PLUGIN_VERSION') ? (string) constant('ACPT_PLUGIN_VERSION') : null;
    $min_satisfied = acpt_min_runtime_available();
    $license = acpt_license_active();
    // ACPT only registers option pages with WP admin when BOTH the toggle and
    // the license are valid. See ACPT_Admin::registerOptionPages — the row in
    // ACPT's DB is created regardless, but the menu page won't render.
    /** @var mixed $pages_const */
    $pages_const = defined('ACPT_ENABLE_PAGES') ? constant('ACPT_ENABLE_PAGES') : null;
    $pages_enabled = $pages_const === true || $pages_const === 1 || $pages_const === '1';
    $pages_will_render = $pages_enabled && $license;

    return $cache = [
        'active' => $active,
        'version' => $version,
        'min_satisfied' => $min_satisfied,
        'license_active' => $license,
        'option_pages_enabled' => $pages_enabled,
        'option_pages_will_render' => $pages_will_render,
        'counts' => [
            'custom_post_types' => acpt_count_safely('ACPT\\Core\\Repository\\CustomPostTypeRepository'),
            'taxonomies' => acpt_count_safely('ACPT\\Core\\Repository\\TaxonomyRepository'),
            'meta_groups' => acpt_count_safely('ACPT\\Core\\Repository\\MetaRepository'),
            'option_pages' => acpt_count_safely('ACPT\\Core\\Repository\\OptionPageRepository'),
        ],
    ];
}

/**
 * Defensive count() wrapper for ACPT Repository classes. Returns 0 if the
 * class is missing or throws — keeps check-setup robust on partial / freshly-
 * activated installs where some tables may not exist yet.
 *
 * @param class-string $repository
 */
function acpt_count_safely(string $repository): int
{
    if (!class_exists($repository) || !method_exists($repository, 'count')) {
        return 0;
    }
    try {
        /** @var mixed $result */
        $result = $repository::count();
        return is_numeric($result) ? (int) $result : 0;
    } catch (Throwable) {
        return 0;
    }
}

/**
 * Standard runtime guard returned from every ACPT ability callback's first line.
 */
function acpt_runtime_guard(): ?WP_Error
{
    if (!defined('ACPT_PLUGIN_VERSION') || !class_exists('ACPT\\Includes\\ACPT_Plugin')) {
        return new WP_Error('acpt_not_active', __('ACPT is not active.', domain: 'novamira-pro'));
    }
    if (!acpt_min_runtime_available()) {
        return new WP_Error('acpt_version_unsupported', sprintf(
            /* translators: 1: installed ACPT version, 2: minimum required version. */
            __('ACPT %1$s is below the minimum supported version %2$s.', domain: 'novamira-pro'),
            (string) constant('ACPT_PLUGIN_VERSION'),
            NOVAMIRA_PRO_ACPT_MIN_VERSION,
        ));
    }
    return null;
}

/**
 * Convert a structured target {type, id} into ACPT's `(belongs_to, id)` pair
 * used by MetaRepository for read/write.
 *
 * @param array<string, mixed> $target
 * @return array{belongs_to: string, id: int|string}|WP_Error
 */
function acpt_normalize_target(array $target): array|WP_Error
{
    /** @var mixed $type_raw */
    $type_raw = $target['type'] ?? null;
    /** @var mixed $id_raw */
    $id_raw = $target['id'] ?? null;

    if (!is_string($type_raw) || $type_raw === '') {
        return new WP_Error('acpt_target_invalid', __('target.type is required.', domain: 'novamira-pro'));
    }
    if ($id_raw === null || $id_raw === '') {
        return new WP_Error('acpt_target_invalid', __('target.id is required.', domain: 'novamira-pro'));
    }

    $type = strtolower($type_raw);
    return match ($type) {
        'post' => ['belongs_to' => 'post', 'id' => (int) $id_raw],
        'term' => ['belongs_to' => 'taxonomy', 'id' => (int) $id_raw],
        'user' => ['belongs_to' => 'user', 'id' => (int) $id_raw],
        'comment' => ['belongs_to' => 'comment', 'id' => (int) $id_raw],
        'option-page' => ['belongs_to' => 'option_page', 'id' => (string) $id_raw],
        default => new WP_Error('acpt_target_invalid', sprintf(
            /* translators: %s: unknown target type. */
            __('Unknown target.type "%s". Use post|term|user|comment|option-page.', domain: 'novamira-pro'),
            $type,
        )),
    };
}

/**
 * Build the location half of a get_acpt_field() / save_acpt_meta_field_value()
 * call from a target resolved by acpt_normalize_target().
 *
 * @return array<string, int|string>
 */
function acpt_target_location_args(string $belongs_to, int|string $target_id): array
{
    return match ($belongs_to) {
        'taxonomy' => ['term_id' => (int) $target_id],
        'user' => ['user_id' => (int) $target_id],
        'comment' => ['comment_id' => (int) $target_id],
        'option_page' => ['option_page' => (string) $target_id],
        default => ['post_id' => (int) $target_id],
    };
}

/**
 * Whether the installed ACPT can place an option page under a native
 * WordPress admin menu (the option page `parentSlug` property). Detected
 * from the model rather than the version number.
 */
function acpt_option_page_parent_slug_supported(): bool
{
    return method_exists('ACPT\\Core\\Models\\OptionPage\\OptionPageModel', 'getParentSlug');
}

/**
 * The native WordPress admin menu an option page is placed under, or null
 * when it has none or the installed ACPT does not support it.
 */
function acpt_option_page_parent_slug(\ACPT\Core\Models\OptionPage\OptionPageModel $model): ?string
{
    if (!acpt_option_page_parent_slug_supported()) {
        return null;
    }
    $slug = $model->getParentSlug();
    return $slug !== null && $slug !== '' ? $slug : null;
}

/**
 * Try/catch wrapper that normalises Repository writes to a consistent shape.
 *
 * @param callable(): mixed $fn
 * @return array{success: bool, id?: int|string, error?: string}
 */
function acpt_handle_api_result(callable $fn): array
{
    try {
        /** @var mixed $result */
        $result = $fn();
    } catch (Throwable $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
    if ($result instanceof WP_Error) {
        return ['success' => false, 'error' => $result->get_error_message()];
    }
    if ($result === false || $result === null) {
        return ['success' => true];
    }
    if (is_int($result) && $result > 0) {
        return ['success' => true, 'id' => $result];
    }
    if (is_string($result) && $result !== '') {
        return ['success' => true, 'id' => $result];
    }
    return ['success' => true];
}

/**
 * Reserved CPT slugs the agent must not redefine.
 *
 * @return list<string>
 */
function acpt_reserved_post_type_slugs(): array
{
    return [
        'post',
        'page',
        'attachment',
        'revision',
        'nav_menu_item',
        'wp_block',
        'wp_template',
        'wp_template_part',
        'wp_navigation',
    ];
}
