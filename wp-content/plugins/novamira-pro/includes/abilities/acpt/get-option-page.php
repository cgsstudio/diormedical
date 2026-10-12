<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acpt-get-option-page', [
    'label' => __('Get ACPT Option Page', domain: 'novamira-pro'),
    'description' => __(
        'Returns the full schema for one ACPT-managed option page identified by menu_slug. Includes id, menu_slug, page_title, menu_title, capability, icon, description, position, parent_slug (only when the page sits under a native WordPress admin menu, e.g. options-general.php), and children[] (ACPT sub-pages with the same shape).',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'menu_slug' => [
                'type' => 'string',
                'description' => 'The WP admin menu slug for the option page.',
            ],
        ],
        'required' => ['menu_slug'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'menu_slug' => ['type' => 'string'],
            'page_title' => ['type' => 'string'],
            'menu_title' => ['type' => 'string'],
            'capability' => ['type' => 'string'],
            'icon' => ['type' => ['string', 'null']],
            'description' => ['type' => ['string', 'null']],
            'position' => ['type' => 'integer'],
            'parent_slug' => ['type' => 'string'],
            'children' => ['type' => 'array'],
        ],
        'required' => ['id', 'menu_slug', 'page_title', 'menu_title'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_get_option_page',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Fetches one ACPT option page by its menu_slug. children[] lists any sub-pages. To read field values stored on this option page use novamira/acpt-read-values with target {type: "option-page", id: menu_slug}.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{menu_slug: string} $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_get_option_page(array $args): array|WP_Error
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
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: %s: option page menu_slug. */
            __('ACPT option page "%s" not found.', domain: 'novamira-pro'),
            $menu_slug,
        ));
    }

    $model = \ACPT\Core\Repository\OptionPageRepository::getByMenuSlug($menu_slug);
    if ($model === null) {
        return new WP_Error('acpt_not_found', sprintf(
            /* translators: %s: option page menu_slug. */
            __('ACPT option page "%s" not found.', domain: 'novamira-pro'),
            $menu_slug,
        ));
    }

    $children = [];
    foreach ($model->getChildren() as $child) {
        $children[] = [
            'id' => $child->getId(),
            'menu_slug' => $child->getMenuSlug(),
            'page_title' => $child->getPageTitle(),
            'menu_title' => $child->getMenuTitle(),
            'capability' => $child->getCapability(),
            'icon' => $child->getIcon(),
            'description' => $child->getDescription(),
            'position' => $child->getPosition(),
        ];
    }

    $out = [
        'id' => $model->getId(),
        'menu_slug' => $model->getMenuSlug(),
        'page_title' => $model->getPageTitle(),
        'menu_title' => $model->getMenuTitle(),
        'capability' => $model->getCapability(),
        'icon' => $model->getIcon(),
        'description' => $model->getDescription(),
        'position' => $model->getPosition(),
    ];
    $parent_slug = acpt_option_page_parent_slug($model);
    if ($parent_slug !== null) {
        $out['parent_slug'] = $parent_slug;
    }
    $out['children'] = $children;

    return $out;
}
