<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acpt-list-option-pages', [
    'label' => __('List ACPT Option Pages', domain: 'novamira-pro'),
    'description' => __(
        'Returns a lightweight list of the ACPT-managed option pages that have no ACPT parent page: top-level menus and pages placed under a native WordPress admin menu (those carry parent_slug, e.g. options-general.php). Each entry includes id, menu_slug, page_title, menu_title, capability, icon, position, child_count, and parent_slug when set. Sub-pages of an ACPT option page are counted in child_count, not listed: use novamira/acpt-get-option-page on the parent to see them.',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'option_pages' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string'],
                        'menu_slug' => ['type' => 'string'],
                        'page_title' => ['type' => 'string'],
                        'menu_title' => ['type' => 'string'],
                        'capability' => ['type' => 'string'],
                        'icon' => ['type' => ['string', 'null']],
                        'position' => ['type' => 'integer'],
                        'child_count' => ['type' => 'integer'],
                        'parent_slug' => ['type' => 'string'],
                    ],
                    'additionalProperties' => true,
                ],
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['option_pages', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_list_option_pages',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the ACPT option pages without an ACPT parent page; a page placed under a native WordPress admin menu is included and carries parent_slug. Use menu_slug as the canonical identifier. child_count shows how many ACPT sub-pages exist (list them with novamira/acpt-get-option-page). For field values stored on an option page, use novamira/acpt-read-values with target {type: "option-page", id: menu_slug}.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $args
 * @return array{option_pages: list<array<string, mixed>>, total: int}|WP_Error
 */
function acpt_list_option_pages(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $rows = \ACPT\Core\Repository\OptionPageRepository::get();
    $option_pages = [];
    foreach ($rows as $model) {
        $entry = [
            'id' => $model->getId(),
            'menu_slug' => $model->getMenuSlug(),
            'page_title' => $model->getPageTitle(),
            'menu_title' => $model->getMenuTitle(),
            'capability' => $model->getCapability(),
            'icon' => $model->getIcon(),
            'position' => $model->getPosition(),
            'child_count' => count($model->getChildren()),
        ];
        $parent_slug = acpt_option_page_parent_slug($model);
        if ($parent_slug !== null) {
            $entry['parent_slug'] = $parent_slug;
        }
        $option_pages[] = $entry;
    }

    return ['option_pages' => $option_pages, 'total' => count($option_pages)];
}
