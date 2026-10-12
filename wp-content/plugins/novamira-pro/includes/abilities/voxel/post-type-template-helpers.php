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
 * Shared helpers for the per-post-type base template abilities
 * (create/apply/clear-post-type-template).
 *
 * A Voxel post type renders through four base template slots stored in its
 * config under `templates`: `single`, `card` and `archive` are
 * elementor_library documents; `form` is a regular WP page holding the create
 * flow. Voxel persists the map by replacing the whole `templates` key of the
 * post type's config entry (post-type-base-templates-controller.php,
 * repository set_config), normalizing it on read to exactly those four keys
 * with numeric ids (post-type-templates.php get_templates()). These helpers
 * mirror that storage model at config level — like the S4 config abilities —
 * so a post type created in this same request is already bindable.
 */

/**
 * The per-post-type base template slots.
 *
 * @return list<string>
 */
function ptt_template_keys(): array
{
    return ['single', 'card', 'archive', 'form'];
}

/**
 * Elementor document type + theme-builder location for a template slot,
 * mirroring Voxel's own assignment fixup (post-type-base-templates-controller
 * .php, set_elementor_template_type): single => single-post @ single,
 * card => card, archive => archive @ archive. The form slot is a regular
 * WordPress page and has no Elementor document type (null).
 *
 * @return array{type: string, location: string}|null
 */
function ptt_document_type(string $template_key): ?array
{
    return match ($template_key) {
        'single' => ['type' => 'single-post', 'location' => 'single'],
        'card' => ['type' => 'card', 'location' => ''],
        'archive' => ['type' => 'archive', 'location' => 'archive'],
        default => null,
    };
}

/**
 * Validate and return the template_key input, or a WP_Error naming the
 * accepted slots.
 *
 * @param array<string, mixed> $input
 */
function ptt_template_key_or_error(array $input): string|WP_Error
{
    $key = is_string($input['template_key'] ?? null) ? $input['template_key'] : '';
    if (!in_array($key, ptt_template_keys(), strict: true)) {
        return vx_invalid(sprintf(
            /* translators: %s comma-separated list of accepted template keys. */
            __('template_key must be one of: %s.', domain: 'novamira-pro'),
            implode(', ', ptt_template_keys()),
        ));
    }
    return $key;
}

/**
 * The normalized base-template map of one post type's stored config: exactly
 * the four slots, numeric ids as positive ints, everything else null — the
 * same normalization Voxel applies on read (post-type-templates.php,
 * get_templates()). Unknown keys are dropped, as Voxel itself drops them on
 * its next write.
 *
 * @param array<array-key, mixed> $config one post type's config entry
 * @return array<string, int|null>
 */
function ptt_read_templates(array $config): array
{
    $templates = [];
    foreach (ptt_template_keys() as $slot) {
        $templates[$slot] = null;
    }
    /** @var mixed $stored */
    $stored = $config['templates'] ?? [];
    if (!is_array($stored)) {
        return $templates;
    }
    /** @var mixed $template_id */
    foreach ($stored as $slot => $template_id) {
        if (!is_string($slot) || !array_key_exists($slot, $templates) || !is_numeric($template_id)) {
            continue;
        }
        $templates[$slot] = absint($template_id);
    }
    return $templates;
}

/**
 * Apply Voxel's document-type fixup to a template assigned to a slot: set the
 * _elementor_template_type meta, the elementor_library_type term, and — for
 * slots with a theme-builder location — the _elementor_location meta. A no-op
 * for the form slot (a page) and for any post that is not an
 * elementor_library document, exactly like Voxel's set_elementor_template_type.
 */
function ptt_fix_document_type(int $template_id, string $template_key): void
{
    $doc = ptt_document_type($template_key);
    if ($doc === null || get_post_type($template_id) !== 'elementor_library') {
        return;
    }
    update_post_meta($template_id, meta_key: '_elementor_template_type', meta_value: $doc['type']);
    wp_set_object_terms($template_id, $doc['type'], taxonomy: 'elementor_library_type');
    if ($doc['location'] !== '') {
        update_post_meta($template_id, meta_key: '_elementor_location', meta_value: $doc['location']);
    }
}

/**
 * Explain why a slot target was refused, naming the post type actually found
 * (mirrors vx_template_target_error's reasoning for site areas: "does not
 * exist" would mislead whenever the id is real but holds the other kind of
 * document — the common mistake being an elementor_library id passed to the
 * form slot, which takes a page).
 */
function ptt_target_error(int $id, string $template_key, string $expected): WP_Error
{
    $found = get_post_type($id);
    if ($found === false) {
        return vx_invalid(sprintf(
            /* translators: 1: post id, 2: expected document kind. */
            __('No post with id %1$d exists, so it cannot be assigned as a %2$s template.', domain: 'novamira-pro'),
            $id,
            $expected,
        ));
    }
    return vx_invalid(sprintf(
        /* translators: 1: template slot key, 2: expected document kind, 3: post id, 4: actual post type. */
        __(
            'The "%1$s" slot expects a %2$s, but post %3$d is a "%4$s". Create a matching one with novamira/voxel-create-post-type-template.',
            domain: 'novamira-pro',
        ),
        $template_key,
        $expected,
        $id,
        $found,
    ));
}
