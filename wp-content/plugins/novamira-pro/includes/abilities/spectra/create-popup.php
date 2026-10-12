<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Spectra;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/spectra-create-popup', [
    'label' => __('Create Spectra Popup', domain: 'novamira-pro'),
    'description' => __(
        'Create a new Spectra Popup Builder post (`spectra-popup` CPT). Accepts short names (title, content, status) or their WP-native aliases (post_title, post_content, post_status) — passing both is rejected. When `content` is omitted the popup is seeded with the locked `wp:uagb/popup-builder` root block so the popup builder editor opens on a valid tree. Meta defaults: popup_type=modal, enabled=true, repetition=0.',
        domain: 'novamira-pro',
    ),
    'category' => 'spectra',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'title' => ['type' => 'string', 'description' => 'Popup title. Alias: post_title.'],
            'post_title' => ['type' => 'string'],
            'content' => ['type' => 'string', 'description' => 'Block markup. Alias: post_content.'],
            'post_content' => ['type' => 'string'],
            'status' => [
                'type' => 'string',
                'enum' => ['draft', 'publish', 'private', 'pending'],
                'description' => 'Post status. Alias: post_status. Default: draft.',
            ],
            'post_status' => ['type' => 'string', 'enum' => ['draft', 'publish', 'private', 'pending']],
            'slug' => ['type' => 'string', 'description' => 'URL slug. Alias: post_name.'],
            'post_name' => ['type' => 'string'],
            'popup_type' => [
                'type' => 'string',
                'description' => 'Popup type meta (default: modal). Free-form; UAGB Pro extends this with slide-in, banner, etc.',
            ],
            'enabled' => ['type' => 'boolean', 'description' => 'Popup enabled flag (default: true).'],
            'repetition' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Display repetition count meta (default: 0).',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'popup_type' => ['type' => 'string'],
            'enabled' => ['type' => 'boolean'],
            'repetition' => ['type' => 'integer'],
            'modified' => ['type' => 'string'],
        ],
        'required' => ['id', 'title', 'slug', 'status', 'popup_type', 'enabled', 'repetition', 'modified'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Spectra\\spectra_create_popup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Creates a spectra-popup CPT. Requires manage_options. Content defaults to the locked wp:uagb/popup-builder root block.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Default `post_content` for a fresh popup — a single `uagb/popup-builder`
 * root block. Matches the template lock UAGB registers on the CPT (see
 * class-uagb-init-blocks.php:148-151) so the block editor opens on a valid
 * tree and does not immediately warn about a broken template.
 */
const SPECTRA_POPUP_DEFAULT_CONTENT = '<!-- wp:uagb/popup-builder /-->';

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function spectra_create_popup(array $input): array|WP_Error
{
    if (!spectra_plugin_available()) {
        return sp_not_active();
    }

    $conflict = spectra_create_popup_conflict($input);
    if ($conflict !== null) {
        return $conflict;
    }

    // A caller that passes `enabled` MUST pass a real boolean. Silently
    // defaulting to true on an `enabled: 'false'` typo would leave the
    // agent believing the popup is disabled when it isn't.
    if (array_key_exists('enabled', $input) && !is_bool($input['enabled'])) {
        return sp_invalid_input('enabled must be a boolean (true or false)');
    }

    // wp_insert_post declares a strict shape but accepts our `array<string, mixed>`
    // at runtime — every emitted key belongs to the accepted set.
    // @mago-expect analysis:possibly-invalid-argument
    $result = wp_insert_post(spectra_create_popup_post_args($input), wp_error: true);
    if ($result instanceof WP_Error) {
        return $result;
    }
    $id = (int) $result;
    if ($id <= 0) {
        return sp_invalid_input('wp_insert_post returned an invalid id');
    }

    sp_popup_meta_write($id, spectra_create_popup_meta_defaults($input));

    return spectra_popup_compact_response($id);
}

/**
 * Return the first alias-conflict error found across the four aliased
 * fields, or `null` when the payload is clean.
 *
 * @param array<string, mixed> $input
 */
function spectra_create_popup_conflict(array $input): ?WP_Error
{
    foreach (['title', 'content', 'status', 'slug'] as $alias_short) {
        if (sp_has_alias_conflict($input, short: $alias_short)) {
            return sp_alias_conflict($alias_short);
        }
    }
    return null;
}

/**
 * Build the wp_insert_post payload from the resolved alias inputs.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function spectra_create_popup_post_args(array $input): array
{
    /** @var mixed $title */
    $title = sp_pick_alias($input, short: 'title');
    /** @var mixed $content */
    $content = sp_pick_alias($input, short: 'content');
    /** @var mixed $status */
    $status = sp_pick_alias($input, short: 'status');
    /** @var mixed $slug */
    $slug = sp_pick_alias($input, short: 'slug');

    // Whitespace-only `content` (or any string with no block markers) would
    // open the block editor on a broken template — the whole point of the
    // template-locked wp:uagb/popup-builder default is defeated. Fall back
    // to the default seed when the caller's content is effectively empty.
    $content_valid = is_string($content) && trim($content) !== '';

    $args = [
        'post_type' => SPECTRA_POPUP_POST_TYPE,
        'post_title' => is_string($title) ? $title : '',
        'post_content' => $content_valid ? $content : SPECTRA_POPUP_DEFAULT_CONTENT,
        'post_status' => is_string($status) && $status !== '' ? $status : 'draft',
    ];
    if (is_string($slug) && $slug !== '') {
        $args['post_name'] = $slug;
    }
    return $args;
}

/**
 * Meta defaults for a fresh popup — `popup_type=modal`, `enabled=true`,
 * `repetition=0`. Callers can override any subset via the input payload.
 *
 * @param array<string, mixed> $input
 * @return array{type:string, enabled:bool, repetition:int}
 */
function spectra_create_popup_meta_defaults(array $input): array
{
    $type = 'modal';
    if (isset($input['popup_type']) && is_string($input['popup_type']) && $input['popup_type'] !== '') {
        $type = $input['popup_type'];
    }
    $enabled = isset($input['enabled']) && is_bool($input['enabled']) ? $input['enabled'] : true;
    $repetition = spectra_coerce_int($input['repetition'] ?? 0, minimum: 0);
    return ['type' => $type, 'enabled' => $enabled, 'repetition' => $repetition];
}

/**
 * Shared "compact popup response" builder — used by create/edit/duplicate/
 * enable/disable so the response shape is one place.
 *
 * @return array<string, mixed>|WP_Error
 */
function spectra_popup_compact_response(int $id): array|WP_Error
{
    $post = sp_post_or_error($id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $meta = sp_popup_meta_read($post->ID);
    return [
        'id' => $post->ID,
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'status' => $post->post_status,
        'popup_type' => $meta['type'],
        'enabled' => $meta['enabled'],
        'repetition' => $meta['repetition'],
        'modified' => $post->post_modified_gmt,
    ];
}
