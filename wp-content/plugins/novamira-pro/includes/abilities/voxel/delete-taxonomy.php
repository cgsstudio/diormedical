<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: delete a Voxel taxonomy (config), optionally deleting its terms.
 */

wp_register_ability('novamira/voxel-delete-taxonomy', [
    'label' => __('Delete Voxel Taxonomy', domain: 'novamira-pro'),
    'description' => __(
        'Remove a Voxel taxonomy from the site configuration. By default the taxonomy\'s terms are kept in the database (they simply become unmanaged once the taxonomy stops being registered on the next request). Pass delete_terms=true to also delete every term. This is a configuration change and cannot be undone.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'taxonomy' => ['type' => 'string', 'description' => 'The taxonomy key to delete.'],
            'delete_terms' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Also delete all of the taxonomy\'s terms.',
            ],
        ],
        'required' => ['taxonomy'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
            'terms_deleted' => [
                'type' => 'integer',
                'description' => 'Terms genuinely removed. A term WordPress refused to delete is not counted.',
            ],
        ],
        'required' => ['key', 'deleted', 'terms_deleted'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_delete_taxonomy',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Permanent configuration change. By default terms are kept; delete_terms=true also removes every term. Takes effect next request.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{key: string, deleted: bool, terms_deleted: int}|WP_Error
 */
function voxel_delete_taxonomy(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $key = is_string($input['taxonomy'] ?? null) ? $input['taxonomy'] : '';
    $delete_terms = ($input['delete_terms'] ?? false) === true;

    // Refuse to delete a core taxonomy the config blob happens to carry (e.g.
    // 'category', 'post_tag'); delete_terms=true would otherwise wipe core terms.
    // Symmetric with create-taxonomy, which rejects the same reserved keys.
    if (in_array($key, vx_reserved_taxonomy_keys(), strict: true)) {
        return vx_invalid(sprintf(
            /* translators: %s the taxonomy key. */
            __('"%s" is a built-in taxonomy and cannot be deleted here.', domain: 'novamira-pro'),
            $key,
        ));
    }

    return vx_with_lock('taxonomies', static function () use ($key, $delete_terms): array|WP_Error {
        $all = vx_read_taxonomies_config();
        $config = vx_taxonomy_config_or_error($all, $key);
        if ($config instanceof WP_Error) {
            return $config;
        }

        $terms_deleted = 0;
        if ($delete_terms && taxonomy_exists($key)) {
            /** @var mixed $ids */
            $ids = get_terms(['taxonomy' => $key, 'hide_empty' => false, 'fields' => 'ids']);
            if (is_array($ids)) {
                /** @var mixed $tid */
                foreach ($ids as $tid) {
                    if (!is_numeric($tid)) {
                        continue;
                    }
                    // wp_delete_term returns false/0/WP_Error when the term is
                    // missing, is the default term, or a filter vetoes it. Only
                    // a literal true means the row is gone.
                    if (wp_delete_term((int) $tid, $key) !== true) {
                        continue;
                    }
                    $terms_deleted++;
                }
            }
        }

        unset($all[$key]);
        vx_write_taxonomies_config($all);
        // Deferred: the rules WordPress would build right now still describe the
        // configuration as it was when init ran. See index-maintenance.php.
        vx_mark_rewrite_pending();

        // \Voxel\set() discards the update_option() result; read the stored
        // config back rather than asserting the write we intended.
        $deleted = !array_key_exists($key, vx_read_taxonomies_config());

        return ['key' => $key, 'deleted' => $deleted, 'terms_deleted' => $terms_deleted];
    });
}
