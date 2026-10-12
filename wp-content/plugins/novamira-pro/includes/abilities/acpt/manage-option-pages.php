<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// acpt-create-option-page
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-create-option-page', [
    'label' => __('Create ACPT Option Page', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new ACPT-managed WordPress admin option page. Required: menu_slug (unique WP admin menu slug), page_title, menu_title. Optional: capability (default: manage_options), icon (dashicons slug or URL), description, position (int, WP menu position), parent_menu_slug (create a sub-page under an existing ACPT option page) or parent_slug (place the page under a native WordPress admin menu such as options-general.php, when the installed ACPT supports it). parent_menu_slug and parent_slug are mutually exclusive.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'menu_slug' => [
                'type' => 'string',
                'description' => 'Unique WP admin menu slug.',
            ],
            'page_title' => [
                'type' => 'string',
                'description' => 'Page title (shown in browser tab).',
            ],
            'menu_title' => [
                'type' => 'string',
                'description' => 'Menu label (shown in sidebar).',
            ],
            'capability' => [
                'type' => 'string',
                'description' => 'Required WP capability. Default: manage_options.',
            ],
            'icon' => [
                'type' => 'string',
                'description' => 'Dashicons class or URL.',
            ],
            'description' => ['type' => 'string'],
            'position' => [
                'type' => 'integer',
                'description' => 'WP admin menu position integer.',
            ],
            'parent_menu_slug' => [
                'type' => 'string',
                'description' => 'Parent ACPT option page menu_slug to create this as a sub-page.',
            ],
            'parent_slug' => [
                'type' => 'string',
                'description' => 'Native WordPress admin menu to place the page under (e.g. options-general.php, tools.php). Refused when the installed ACPT does not support it.',
            ],
        ],
        'required' => ['menu_slug', 'page_title', 'menu_title'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'id' => ['type' => 'string'],
            'menu_slug' => ['type' => 'string'],
        ],
        'required' => ['success', 'menu_slug'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_create_option_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates an ACPT option page. Use menu_slug as the unique identifier. After creation, attach meta groups with novamira/acpt-create-field-group using belongs: [{belongs_to: "optionPage", find: menu_slug}].',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// acpt-edit-option-page
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-edit-option-page', [
    'label' => __('Edit ACPT Option Page', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing ACPT-managed option page (PATCH semantics). Identify by menu_slug. Omitted fields keep their current value, including the native WordPress admin menu the page is placed under. Pass parent_slug to move a page that has no ACPT parent under a native WordPress admin menu, or an empty string to make it a top-level menu again (when the installed ACPT supports it).',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'menu_slug' => [
                'type' => 'string',
                'description' => 'Option page menu_slug to edit.',
            ],
            'page_title' => ['type' => 'string'],
            'menu_title' => ['type' => 'string'],
            'capability' => ['type' => 'string'],
            'icon' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'position' => ['type' => 'integer'],
            'parent_slug' => [
                'type' => 'string',
                'description' => 'Native WordPress admin menu to place the page under (e.g. options-general.php). Empty string removes it. Not allowed on a sub-page of another ACPT option page.',
            ],
        ],
        'required' => ['menu_slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'menu_slug' => ['type' => 'string'],
        ],
        'required' => ['success', 'menu_slug'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_edit_option_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'PATCH-style edit of an ACPT option page. Renaming the menu_slug is not supported. Only the fields you pass are changed. The response reports the stored parent_slug when the page sits under a native WordPress admin menu.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// acpt-delete-option-page
// ---------------------------------------------------------------------------

wp_register_ability('novamira/acpt-delete-option-page', [
    'label' => __('Delete ACPT Option Page', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes an ACPT-managed option page. Idempotent: deleting a non-existent menu_slug returns deleted=false.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'menu_slug' => [
                'type' => 'string',
                'description' => 'Option page menu_slug to delete.',
            ],
        ],
        'required' => ['menu_slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'menu_slug' => ['type' => 'string'],
            'reason' => ['type' => 'string'],
        ],
        'required' => ['deleted', 'menu_slug'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_delete_option_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Removes the ACPT option page record. Field values stored as WP options are NOT deleted automatically.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// Callbacks
// ---------------------------------------------------------------------------

/**
 * Resolve the parent of a new option page: an ACPT parent page id
 * (parent_menu_slug) or a native WordPress admin menu (parent_slug).
 *
 * @param array<string, mixed> $args
 * @return array{parent_id: string|null, parent_slug: string|null}|WP_Error
 */
function acpt_resolve_option_page_create_parent(array $args): array|WP_Error
{
    $parent_menu_slug = trim((string) ($args['parent_menu_slug'] ?? ''));
    $parent_slug = trim((string) ($args['parent_slug'] ?? ''));

    if ($parent_menu_slug !== '' && $parent_slug !== '') {
        return new WP_Error('acpt_invalid_input', __(
            'Pass parent_menu_slug (an ACPT option page) or parent_slug (a WordPress admin menu), not both.',
            domain: 'novamira-pro',
        ));
    }

    if ($parent_slug !== '') {
        if (!acpt_option_page_parent_slug_supported()) {
            return acpt_parent_slug_unsupported_error();
        }
        return ['parent_id' => null, 'parent_slug' => $parent_slug];
    }

    if ($parent_menu_slug === '') {
        return ['parent_id' => null, 'parent_slug' => null];
    }

    $parent_id = \ACPT\Core\Repository\OptionPageRepository::getId($parent_menu_slug);
    if ($parent_id === null || $parent_id === '') {
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: %s: parent menu slug. */
            __('Parent ACPT option page "%s" not found.', domain: 'novamira-pro'),
            $parent_menu_slug,
        ));
    }

    return ['parent_id' => $parent_id, 'parent_slug' => null];
}

function acpt_parent_slug_unsupported_error(): WP_Error
{
    return new WP_Error('acpt_parent_slug_unsupported', __(
        'The installed ACPT cannot place an option page under a WordPress admin menu (parent_slug). Use parent_menu_slug to nest it under another ACPT option page instead.',
        domain: 'novamira-pro',
    ));
}

/**
 * Hydrate and save an option page model. parentSlug is only sent to ACPT
 * versions whose model knows it: older models reject the key.
 *
 * @param array{id: string, page_title: string, menu_title: string, capability: string, menu_slug: string, sort: int, position: int, icon: string|null, description: string|null, parent_id: string|null, parent_slug: string|null} $data
 */
function acpt_save_option_page_model(array $data): void
{
    $model_data = [
        'id' => $data['id'],
        'pageTitle' => $data['page_title'],
        'menuTitle' => $data['menu_title'],
        'capability' => $data['capability'],
        'menuSlug' => $data['menu_slug'],
        'sort' => $data['sort'],
        'position' => $data['position'],
        'icon' => $data['icon'],
        'description' => $data['description'],
        'parentId' => $data['parent_id'],
    ];
    if (acpt_option_page_parent_slug_supported()) {
        $model_data['parentSlug'] = $data['parent_slug'];
    }
    $model = \ACPT\Core\Models\OptionPage\OptionPageModel::hydrateFromArray($model_data);
    \ACPT\Core\Repository\OptionPageRepository::save($model);
}

/**
 * @param array{menu_slug: string, page_title: string, menu_title: string, capability?: string, icon?: string, description?: string, position?: int, parent_menu_slug?: string, parent_slug?: string} $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_create_option_page(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $menu_slug = trim($args['menu_slug']);
    $page_title = trim($args['page_title']);
    $menu_title = trim($args['menu_title']);

    if ($menu_slug === '' || $page_title === '' || $menu_title === '') {
        return new WP_Error('acpt_invalid_input', __(
            'menu_slug, page_title, and menu_title are required.',
            domain: 'novamira-pro',
        ));
    }

    if (\ACPT\Core\Repository\OptionPageRepository::exists($menu_slug)) {
        return new WP_Error('acpt_already_exists', sprintf(
            /* translators: %s: menu slug. */
            __('ACPT option page "%s" already exists.', domain: 'novamira-pro'),
            $menu_slug,
        ));
    }

    $parent = acpt_resolve_option_page_create_parent($args);
    if ($parent instanceof WP_Error) {
        return $parent;
    }

    $data = [
        'id' => acpt_uuid_v4(),
        'page_title' => $page_title,
        'menu_title' => $menu_title,
        'capability' => $args['capability'] ?? 'manage_options',
        'menu_slug' => $menu_slug,
        'sort' => count(\ACPT\Core\Repository\OptionPageRepository::getAllIds()) + 1,
        'position' => $args['position'] ?? 2,
        'icon' => $args['icon'] ?? null,
        'description' => $args['description'] ?? null,
        'parent_id' => $parent['parent_id'],
        'parent_slug' => $parent['parent_slug'],
    ];

    $result = acpt_handle_api_result(static fn() => acpt_save_option_page_model($data));

    if (!$result['success']) {
        return new WP_Error(
            'acpt_create_option_page_failed',
            $result['error'] ?? __('Failed to create option page.', domain: 'novamira-pro'),
        );
    }

    $out = [
        'success' => true,
        'id' => (string) \ACPT\Core\Repository\OptionPageRepository::getId($menu_slug),
        'menu_slug' => $menu_slug,
        'page_title' => $page_title,
        'menu_title' => $menu_title,
    ];
    $saved = \ACPT\Core\Repository\OptionPageRepository::getByMenuSlug($menu_slug);
    $saved_parent_slug = $saved !== null ? acpt_option_page_parent_slug($saved) : null;
    if ($saved_parent_slug !== null) {
        $out['parent_slug'] = $saved_parent_slug;
    }

    return $out;
}

/**
 * Resolve the native WordPress admin menu an edited option page ends up
 * under. Omitting parent_slug keeps the stored one: ACPT writes the column
 * on every save, so leaving it out of the model would un-nest the page.
 *
 * @param array<string, mixed> $args
 */
function acpt_resolve_option_page_edit_parent_slug(
    array $args,
    \ACPT\Core\Models\OptionPage\OptionPageModel $model,
): string|null|WP_Error {
    if (!array_key_exists('parent_slug', $args)) {
        return acpt_option_page_parent_slug($model);
    }

    $parent_slug = trim((string) $args['parent_slug']);
    if ($parent_slug === '') {
        return null;
    }
    if (!acpt_option_page_parent_slug_supported()) {
        return acpt_parent_slug_unsupported_error();
    }
    $parent_id = $model->getParentId();
    if ($parent_id !== null && $parent_id !== '') {
        return new WP_Error('acpt_invalid_input', __(
            'parent_slug cannot be set on a sub-page of another ACPT option page: ACPT only places pages without an ACPT parent under a WordPress admin menu.',
            domain: 'novamira-pro',
        ));
    }

    return $parent_slug;
}

/**
 * @param array{menu_slug: string, page_title?: string, menu_title?: string, capability?: string, icon?: string, description?: string, position?: int, parent_slug?: string} $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_edit_option_page(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $menu_slug = trim($args['menu_slug']);
    if ($menu_slug === '') {
        return new WP_Error('acpt_invalid_input', __('menu_slug is required.', domain: 'novamira-pro'));
    }

    $model = \ACPT\Core\Repository\OptionPageRepository::exists($menu_slug)
        ? \ACPT\Core\Repository\OptionPageRepository::getByMenuSlug($menu_slug)
        : null;
    if ($model === null) {
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: %s: menu slug. */
            __('ACPT option page "%s" not found.', domain: 'novamira-pro'),
            $menu_slug,
        ));
    }

    $parent_slug = acpt_resolve_option_page_edit_parent_slug($args, $model);
    if ($parent_slug instanceof WP_Error) {
        return $parent_slug;
    }

    $data = [
        'id' => $model->getId(),
        'page_title' => isset($args['page_title']) ? trim($args['page_title']) : $model->getPageTitle(),
        'menu_title' => isset($args['menu_title']) ? trim($args['menu_title']) : $model->getMenuTitle(),
        'capability' => $args['capability'] ?? $model->getCapability(),
        'menu_slug' => $model->getMenuSlug(),
        'sort' => $model->getSort(),
        'position' => $args['position'] ?? $model->getPosition(),
        'icon' => $args['icon'] ?? $model->getIcon(),
        'description' => $args['description'] ?? $model->getDescription(),
        'parent_id' => $model->getParentId(),
        'parent_slug' => $parent_slug,
    ];

    $result = acpt_handle_api_result(static fn() => acpt_save_option_page_model($data));

    if (!$result['success']) {
        return new WP_Error(
            'acpt_edit_option_page_failed',
            $result['error'] ?? __('Failed to edit option page.', domain: 'novamira-pro'),
        );
    }

    $out = ['success' => true, 'menu_slug' => $menu_slug];
    $saved = \ACPT\Core\Repository\OptionPageRepository::getByMenuSlug($menu_slug);
    $saved_parent_slug = $saved !== null ? acpt_option_page_parent_slug($saved) : null;
    if ($saved_parent_slug !== null) {
        $out['parent_slug'] = $saved_parent_slug;
    }

    return $out;
}

/**
 * @param array{menu_slug: string} $args
 * @return array{deleted: bool, menu_slug: string, reason?: string}|WP_Error
 */
function acpt_delete_option_page(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $menu_slug = trim($args['menu_slug']);
    if ($menu_slug === '') {
        return new WP_Error('acpt_invalid_input', __('menu_slug is required.', domain: 'novamira-pro'));
    }

    if (!\ACPT\Core\Repository\OptionPageRepository::exists($menu_slug)) {
        return ['deleted' => false, 'menu_slug' => $menu_slug, 'reason' => 'not_found'];
    }

    $model = \ACPT\Core\Repository\OptionPageRepository::getByMenuSlug($menu_slug);
    if ($model === null) {
        return ['deleted' => false, 'menu_slug' => $menu_slug, 'reason' => 'not_found'];
    }

    $result = acpt_handle_api_result(static fn() => \ACPT\Core\Repository\OptionPageRepository::delete($model));

    if (!$result['success']) {
        return new WP_Error(
            'acpt_delete_option_page_failed',
            $result['error'] ?? __('Failed to delete option page.', domain: 'novamira-pro'),
        );
    }

    return ['deleted' => true, 'menu_slug' => $menu_slug];
}
