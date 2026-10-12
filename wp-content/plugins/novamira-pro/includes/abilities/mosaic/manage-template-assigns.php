<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Mosaic;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

// ---------------------------------------------------------------------------
// list-template-assigns
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-list-template-assigns', [
    'label' => __('List Mosaic Template Assigns', domain: 'novamira-pro'),
    'description' => __(
        'Lists template-assignment bindings under a theme. Each binding maps one singular post to a Mosaic template — Mosaic only reads this table for single-post bindings, which use `type = "post"` for every post type (pages and custom post types included) + `type_identifier = "42"` (the post ID). A row with `type = "page"` is never matched, so the page keeps rendering without the template. Other content surfaces (archives, taxonomies, search, 404, front page) are matched via the template\'s `conditions` JSON at render time, not via this table. Each record carries theme_id, type, type_identifier, template_id, and parent_type (always `template`).',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string', 'description' => 'Theme UUID — required.'],
        ],
        'required' => ['theme_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'assigns' => [
                'type' => 'array',
                'items' => mo_template_assign_item_schema(),
            ],
            'total' => ['type' => 'integer'],
        ],
        'required' => ['assigns', 'total'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_list_template_assigns',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to discover what front-end surfaces a theme already binds, before calling set/clear-template-assign.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// set-template-assign
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-set-template-assign', [
    'label' => __('Set Mosaic Template Assign', domain: 'novamira-pro'),
    'description' => __(
        'Binds a single post to a Mosaic template at render time (Mosaic\'s "manual template assignment" feature). Wire shape: `type` is Mosaic\'s INTERNAL resource type — for singular post bindings always pass the literal string `"post"`, regardless of WP\'s post-type slug (page, post, custom CPT all share resource type `"post"`; a binding stored with `"page"` is never matched). `type_identifier` is the numeric post ID as a string. The template the agent binds also needs `path` to match Mosaic\'s renderer expectation for the WP request type — call `mosaic-edit-template(id, path="single-page.php")` for page posts, `path="single-post.php"` for blog posts, or `path="single-<cpt-slug>.php"` for custom post types. The template the wizard facade creates carries `path=index.php` by default which only renders for front-page / index requests. Other content surfaces (archives, taxonomies, search, 404, front page) are matched via the template\'s `conditions` JSON instead of this table — use mosaic-edit-template to set `conditions` for those. Calling with the same triple replaces any existing binding (PUT-style, idempotent). Field length caps: `type` ≤ 36 chars, `type_identifier` ≤ 100 chars. Returns the binding: theme_id, type, type_identifier, template_id, parent_type (always `template`).',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string'],
            'type' => [
                'type' => 'string',
                'description' => 'Mosaic resource type — for singular post bindings (any post type) always pass `"post"`. The WP post-type slug lives on the template\'s `path` field, not here.',
            ],
            'type_identifier' => [
                'type' => 'string',
                'description' => 'Numeric post ID as a string (Mosaic stores it as varchar). Required.',
            ],
            'template_id' => ['type' => 'string', 'description' => 'Template UUID to bind.'],
        ],
        'required' => ['theme_id', 'type', 'type_identifier', 'template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['assign' => mo_template_assign_item_schema()],
        'required' => ['assign'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_set_template_assign',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Idempotent — re-running with the same triple overwrites the previous template_id binding.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

// ---------------------------------------------------------------------------
// clear-template-assign
// ---------------------------------------------------------------------------

wp_register_ability('novamira/mosaic-clear-template-assign', [
    'label' => __('Clear Mosaic Template Assign', domain: 'novamira-pro'),
    'description' => __(
        'Removes a (theme_id, type, type_identifier) binding. Hard-delete: the row is removed from `wp_mosaic_template_assigns` entirely (this table is not soft-deletable). Returns `deleted: true` whether or not a binding existed (idempotent). All three fields are required to scope the delete to the exact row mosaic-set-template-assign would have written — passing only two fields would match every binding under that pair.',
        domain: 'novamira-pro',
    ),
    'category' => 'mosaic',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'type_identifier' => ['type' => 'string'],
        ],
        'required' => ['theme_id', 'type', 'type_identifier'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => ['deleted' => ['type' => 'boolean'], 'rows_affected' => ['type' => 'integer']],
        'required' => ['deleted', 'rows_affected'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Mosaic\\mosaic_clear_template_assign',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Idempotent — returns deleted: true even when there was no row to remove.',
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
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_list_template_assigns(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    if ($theme_id === '') {
        return mo_error('mo_invalid_input', __('theme_id is required.', domain: 'novamira-pro'));
    }
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('template_assigns');
    // @mago-expect analysis:possibly-invalid-argument
    $prepared = $wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE `themeID` = %s ORDER BY `type` ASC, `typeIdentifier` ASC",
        $theme_id,
    );
    /** @var mixed $rows */
    // @mago-expect analysis:possibly-invalid-argument
    $rows = $wpdb->get_results($prepared, 'ARRAY_A');
    if (!is_array($rows)) {
        $rows = [];
    }
    /** @var list<array<string, mixed>> $rows */
    $mapped = array_map('Novamira\\Pro\\Abilities\\Mosaic\\mo_map_template_assign_row', $rows);
    return ['assigns' => $mapped, 'total' => count($mapped)];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_set_template_assign(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $args = mo_validate_set_assign_input($input);
    if ($args instanceof WP_Error) {
        return $args;
    }
    return mo_with_lock("template-assigns:{$args['theme_id']}", static fn(): array|WP_Error => mo_replace_assign_locked(
        $args['theme_id'],
        $args['type'],
        $args['type_identifier'],
        $args['template_id'],
    ));
}

/**
 * Validate every input piece + verify the template belongs to the theme
 * before we take the lock. Extracted so mosaic_set_template_assign stays
 * under mago's cyclomatic-complexity threshold.
 *
 * @param array<string, mixed> $input
 * @return array{theme_id:string, type:string, type_identifier:string, template_id:string}|WP_Error
 */
function mo_validate_set_assign_input(array $input): array|WP_Error
{
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    $type = is_string($input['type'] ?? null) ? $input['type'] : '';
    $type_identifier = is_string($input['type_identifier'] ?? null) ? $input['type_identifier'] : '';
    $template_id = mo_norm_uuid($input['template_id'] ?? null);
    $shape_err = mo_check_assign_shape($theme_id, $type, $type_identifier, $template_id);
    if ($shape_err !== null) {
        return $shape_err;
    }
    $template_err = mo_check_assign_template($theme_id, $template_id);
    if ($template_err !== null) {
        return $template_err;
    }
    return [
        'theme_id' => $theme_id,
        'type' => $type,
        'type_identifier' => $type_identifier,
        'template_id' => $template_id,
    ];
}

/**
 * Required-field check. Mosaic only reads template_assigns rows
 * matching its post-resource query (`type` = the resource type `post`
 * for every post type, `type_identifier` = the numeric post ID), so every
 * triple must be non-empty. Other content surfaces (archive / taxonomy
 * / 404 / search / front page) are bound via the template `conditions`
 * JSON at render time, not through this table.
 */
function mo_check_assign_shape(string $theme_id, string $type, string $type_identifier, string $template_id): ?WP_Error
{
    if ($theme_id === '' || $type === '' || $template_id === '' || $type_identifier === '') {
        return mo_error('mo_invalid_input', __(
            'theme_id, type, type_identifier, and template_id are all required.',
            domain: 'novamira-pro',
        ));
    }
    return mo_validate_lengths([
        'type' => ['value' => $type, 'max' => 36],
        'type_identifier' => ['value' => $type_identifier, 'max' => 100],
    ]);
}

/**
 * Verify the template exists, is published, and belongs to the supplied
 * theme so we never bind a cross-theme template to a content surface.
 */
function mo_check_assign_template(string $theme_id, string $template_id): ?WP_Error
{
    $template = mo_load('templates', $template_id);
    if ($template === null || ($template['status'] ?? '') !== 'publish') {
        return mo_error('mo_not_found', sprintf(
            /* translators: %s: template UUID. */
            __('Mosaic template %s not found.', domain: 'novamira-pro'),
            $template_id,
        ));
    }
    if (mo_str($template, key: 'themeID') !== $theme_id) {
        return mo_error('mo_invalid_input', __('template_id does not belong to theme_id.', domain: 'novamira-pro'));
    }
    return null;
}

/**
 * Single template_assign record shape, shared between list-template-assigns
 * (items[]) and set-template-assign (assign). Kept here so the two
 * abilities never drift apart.
 *
 * @return array<string, mixed>
 */
function mo_template_assign_item_schema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'theme_id' => ['type' => 'string'],
            'type' => ['type' => 'string'],
            'type_identifier' => ['type' => 'string'],
            'template_id' => ['type' => 'string'],
            'parent_type' => ['type' => 'string'],
        ],
        'required' => ['theme_id', 'type', 'type_identifier', 'template_id', 'parent_type'],
        'additionalProperties' => false,
    ];
}

/**
 * Composite primary key (themeID, type, typeIdentifier) means we
 * REPLACE-style replace via DELETE + INSERT, so the call stays
 * idempotent regardless of whether a binding already existed.
 *
 * @return array<string, array<string, string>>|WP_Error
 */
function mo_replace_assign_locked(
    string $theme_id,
    string $type,
    string $type_identifier,
    string $template_id,
): array|WP_Error {
    global $wpdb;
    /** @var \wpdb $wpdb */
    $table = mo_table('template_assigns');
    $wpdb->delete(
        $table,
        ['themeID' => $theme_id, 'type' => $type, 'typeIdentifier' => $type_identifier],
        ['%s', '%s', '%s'],
    );
    $ok = $wpdb->insert(
        $table,
        [
            'ID' => mo_uuid(),
            'themeID' => $theme_id,
            'parentType' => 'template',
            'parentID' => $template_id,
            'type' => $type,
            'typeIdentifier' => $type_identifier,
        ],
        array_fill(start_index: 0, count: 6, value: '%s'),
    );
    if ($ok === false) {
        return mo_error('mo_write_failed', __('Could not insert the template-assign binding.', domain: 'novamira-pro'));
    }
    // @mago-expect analysis:possibly-invalid-argument
    $reread_sql = (string) $wpdb->prepare(
        "SELECT * FROM `{$table}` WHERE `themeID` = %s AND `type` = %s AND `typeIdentifier` = %s LIMIT 1",
        $theme_id,
        $type,
        $type_identifier,
    );
    /** @var mixed $row */
    $row = $wpdb->get_row($reread_sql, 'ARRAY_A');
    if (!is_array($row)) {
        return mo_error('mo_not_found', __('Inserted assign could not be re-read.', domain: 'novamira-pro'));
    }
    /** @var array<string, mixed> $row */
    mo_emit_changed(
        ability: 'novamira/mosaic-set-template-assign',
        operation: 'set',
        entity: 'template_assign',
        entity_id: mo_str($row, key: 'ID'),
        context: ['theme_id' => $theme_id, 'template_id' => $template_id],
    );
    return ['assign' => mo_map_template_assign_row($row)];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function mosaic_clear_template_assign(array $input): array|WP_Error
{
    $err = mo_require_runtime();
    if ($err !== null) {
        return $err;
    }
    $theme_id = mo_norm_uuid($input['theme_id'] ?? null);
    $type = isset($input['type']) && is_string($input['type']) ? $input['type'] : '';
    $type_identifier = is_string($input['type_identifier'] ?? null) ? $input['type_identifier'] : '';
    if ($theme_id === '' || $type === '' || $type_identifier === '') {
        return mo_error('mo_invalid_input', __(
            'theme_id, type, and type_identifier are all required to scope the delete to a single binding.',
            domain: 'novamira-pro',
        ));
    }
    return mo_with_lock("template-assigns:{$theme_id}", static function () use (
        $theme_id,
        $type,
        $type_identifier,
    ): array {
        global $wpdb;
        /** @var \wpdb $wpdb */
        $rows = $wpdb->delete(
            mo_table('template_assigns'),
            ['themeID' => $theme_id, 'type' => $type, 'typeIdentifier' => $type_identifier],
            ['%s', '%s', '%s'],
        );
        $rows_affected = is_int($rows) ? $rows : 0;
        if ($rows_affected > 0) {
            mo_emit_changed(
                ability: 'novamira/mosaic-clear-template-assign',
                operation: 'clear',
                entity: 'template_assign',
                entity_id: $type . ':' . $type_identifier,
                context: ['theme_id' => $theme_id],
            );
        }
        return ['deleted' => true, 'rows_affected' => $rows_affected];
    });
}
