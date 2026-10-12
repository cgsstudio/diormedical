<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicContentForElementor;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/dynamic-content-for-elementor-delete-html-template', [
    'label' => __('Delete Dynamic Content for Elementor HTML Template', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an HTML Template. By default it goes to the trash and can be restored from the plugin own screen; pass force to remove it and its stored markup for good, which also works on a template already sitting in the trash. Read the deleted field rather than assuming: on a site configured to keep no trash there is nowhere to put it, so a plain delete is permanent and says so. Anything still pointing at this template, such as a PDF Button widget on a page, keeps pointing at an id that no longer resolves, so the buttons that used it stop producing a document. Deleting a template that renders an Elementor template never touches that Elementor template.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-content-for-elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Post id of the HTML Template to delete.',
            ],
            'force' => [
                'type' => 'boolean',
                'description' => 'Delete permanently instead of trashing. Defaults to false.',
            ],
        ],
        'required' => ['template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'deleted' => [
                'type' => 'string',
                'description' => 'What actually happened: "trashed" when it can still be restored, "permanently" when it cannot. A site with EMPTY_TRASH_DAYS at zero keeps no trash, so a delete there is permanent even without force.',
            ],
        ],
        'required' => ['id', 'title', 'deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicContentForElementor\\dynamic_content_for_elementor_delete_html_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Trashes by default so the markup survives a mistake. force is irreversible.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function dynamic_content_for_elementor_delete_html_template(array $input): array|WP_Error
{
    $force = ($input['force'] ?? false) === true;

    // A template the user already trashed from the plugin's own screen is exactly
    // what a permanent delete is usually asked for, so the trash gate is lifted
    // here and only here.
    $post = dce_template_or_error(
        (int) ($input['template_id'] ?? 0),
        blocked_statuses: $force ? ['auto-draft'] : ['trash', 'auto-draft'],
    );
    if ($post instanceof WP_Error) {
        return $post;
    }

    $title = $post->post_title;

    // WordPress turns a trash into a permanent delete when the site keeps no trash,
    // so what actually happened is decided here rather than assumed from which
    // branch was taken. Reporting "trashed" for something unrecoverable is worse
    // than the deletion itself: it tells the user they can undo it.
    $keeps_trash = !defined('EMPTY_TRASH_DAYS') || (int) constant('EMPTY_TRASH_DAYS') > 0;
    $permanent = $force || !$keeps_trash;

    $result = $permanent ? wp_delete_post($post->ID, force_delete: true) : wp_trash_post($post->ID);

    if (!$result) {
        return dce_error('delete_failed', message: sprintf(
            'WordPress refused to %s HTML Template %d.',
            $permanent ? 'delete' : 'trash',
            $post->ID,
        ));
    }

    return [
        'id' => $post->ID,
        'title' => $title,
        'deleted' => $permanent ? 'permanently' : 'trashed',
    ];
}
