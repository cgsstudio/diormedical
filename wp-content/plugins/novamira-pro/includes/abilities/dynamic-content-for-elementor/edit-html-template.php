<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicContentForElementor;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/dynamic-content-for-elementor-edit-html-template', [
    'label' => __('Edit Dynamic Content for Elementor HTML Template', domain: 'novamira-pro'),
    'description' => __(
        'Changes one or more fields of an existing HTML Template, leaving everything else as it was. Only the fields you pass are touched: editing the page format keeps the markup, and editing the markup keeps the format. Switching how a template renders is explicit — pass code to make it hold its own markup, or elementor_template_id to make it render an Elementor template. Non-empty code clears the Elementor reference; pointing at an Elementor template leaves the stored markup where it is, unrendered, so it is still there if you switch back. WordPress shortcodes in the template\'s own code are not expanded. Legacy compatibility only: do not use Twig for new templates or mix the syntaxes; before changing an existing template that contains Twig, read twig_expansion in novamira/dynamic-content-for-elementor-check-setup, which explains when all of that template\'s Twig syntax reaches the PDF literally with no error. Use Dynamic Shortcodes for dynamic values, and read that setup ability before writing them: they expand only when dynamic_shortcodes_expansion is true. Returns which fields actually changed. There is no undo: this post type stores no revisions and the markup lives in post meta, so read the template first and keep what you are replacing.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-content-for-elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Post id of the HTML Template to change.',
            ],
            'title' => ['type' => 'string', 'minLength' => 1],
            'status' => [
                'type' => 'string',
                'enum' => ['publish', 'draft', 'pending', 'private'],
            ],
            'code' => [
                'type' => 'string',
                'description' => 'Replaces the stored markup, and switches the template to rendering its own markup. WordPress shortcodes in this stored code are not expanded, and unexpanded syntax is printed literally into the PDF without warning. Legacy compatibility only: do not use Twig for new templates or mix the syntaxes; before changing an existing template that contains Twig, read twig_expansion in novamira/dynamic-content-for-elementor-check-setup, which explains when all of that template\'s Twig syntax reaches the PDF literally with no error. Use Dynamic Shortcodes for dynamic values; they expand at render time only when dynamic_shortcodes_expansion is true. The replacement is not undoable.',
            ],
            'elementor_template_id' => [
                'type' => 'integer',
                'minimum' => 0,
                'description' => 'Renders this Elementor template instead of stored markup, and should be a saved Elementor template since nothing else renders. Zero switches back to markup and clears the reference.',
            ],
            'format' => [
                'type' => 'string',
                'description' => 'Page format. The plugin\'s own template screen offers A4, A5, A6, Letter, Legal, Executive and Folio; any other name mPDF knows is stored as given and answered for at render time. It cannot be empty.',
            ],
            'orientation' => [
                'type' => 'string',
                'enum' => ['P', 'L', 'portrait', 'landscape'],
            ],
        ],
        'required' => ['template_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'renders' => ['type' => 'string'],
            'changed' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Names of the fields whose value is different from before. Empty means the call was a no-op.',
            ],
        ],
        'required' => ['id', 'renders', 'changed'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicContentForElementor\\dynamic_content_for_elementor_edit_html_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Partial by design, and irreversible: nothing keeps the previous markup. Read the template first, both to know which rendering mode you are changing and to hold on to what you are about to replace.',
            'readonly' => false,
            // Destructive: an edit replaces stored markup with no undo path. The
            // post type declares support for the title alone, so WordPress keeps
            // no revisions of it, and the markup lives in post meta, which a
            // revision would not carry even if there were one.
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function dynamic_content_for_elementor_edit_html_template(array $input): array|WP_Error
{
    $post = dce_template_or_error((int) ($input['template_id'] ?? 0));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $changes = dce_edit_template_fields($input, $post->ID);
    if ($changes instanceof WP_Error) {
        return $changes;
    }

    // An edit has two persistences — the stored configuration and the post fields
    // — and both of them can fail: the meta write can be refused by the database,
    // and wp_update_post() can be refused by WordPress or by a filter. There is no
    // transaction to wrap them in, so the order is chosen to make one of them
    // undoable from the other's failure:
    //
    //   1. everything that can be decided without writing is decided first, so
    //      most refusals happen with nothing written at all;
    //   2. the meta is written next and read back, so a write the database did not
    //      take is a refusal rather than a reported success — and one that leaves
    //      the post fields untouched, because they have not been written yet;
    //   3. the post fields are written last, and if that is refused the stored
    //      configuration is put back before the refusal is returned.
    //
    // So every failure either leaves the template exactly as it was, or — when
    // the undo in step 3 is itself refused — returns dce_partial_write, which
    // names the fields that did land.
    //
    // The lock is taken for every edit, not only for one that touches the meta: it
    // is what makes "either all of this lands or none of it does" true against a
    // concurrent writer, and a title is not exempt from that.
    return dce_with_template_lock($post->ID, static function () use ($post, $input, $changes): array|WP_Error {
        // Compare against what is stored now, not against whatever this process
        // read earlier: a value another process wrote in between is the state
        // being edited, so reporting it as "changed by this call" would be wrong.
        wp_cache_delete($post->ID, group: 'post_meta');
        // Read as a list, not as a single value: an absent row and a row holding
        // an empty string are the same thing to a single read, and an undo has to
        // tell them apart to put back what was actually there.
        /** @var list<mixed> $before_raw */
        $before_raw = get_post_meta($post->ID, DCE_TEMPLATE_META_KEY, single: false);
        $before = dce_read_template_meta($post->ID);

        /** @var array<string, mixed>|null $merged */
        $merged = null;
        if ($changes !== []) {
            $decided = dce_merged_template_meta($post->ID, $changes);
            if ($decided instanceof WP_Error) {
                return $decided;
            }
            $merged = $decided;
        }

        $changed = [];
        $after = $before;
        if ($merged !== null) {
            $written = dce_persist_template_meta($post->ID, $merged, $before);
            if ($written instanceof WP_Error) {
                // Nothing has been written yet, the post fields included.
                return $written;
            }
            $after = $written;
            $changed = dce_changed_field_names($before, $after);
        }

        $post_changed = dce_apply_post_fields($post, $input);
        if ($post_changed instanceof WP_Error) {
            return dce_undo_meta_after_refusal($post->ID, $before_raw, $changed, $post_changed);
        }

        return [
            'id' => $post->ID,
            'renders' => dce_present_template_meta($after)['renders'],
            'changed' => array_merge($changed, $post_changed),
        ];
    });
}

/**
 * Put the stored configuration back after the post-field write was refused, and
 * answer with the refusal the caller should see.
 *
 * When the undo succeeds the caller is told exactly why the edit was refused, and
 * the template is as it was. When the undo is itself refused the original reason
 * is no longer the whole truth, so a different error is returned: the caller is
 * told which fields did land, because "it failed" would leave them believing a
 * change they can still see is not there.
 *
 * @param list<mixed> $before_raw the meta row as it was, as `get_post_meta(single: false)` gives it
 * @param list<string> $changed the stored fields this call had already moved
 */
function dce_undo_meta_after_refusal(int $post_id, array $before_raw, array $changed, WP_Error $refusal): WP_Error
{
    if ($changed === []) {
        return $refusal;
    }

    if (dce_restore_template_meta($post_id, $before_raw)) {
        return $refusal;
    }

    /** @var mixed $refusal_data */
    $refusal_data = $refusal->get_error_data();
    $refused_because = is_array($refusal_data) && is_string($refusal_data['wp_code'] ?? null)
        ? $refusal_data['wp_code']
        : $refusal->get_error_code();

    return dce_error(
        'partial_write',
        sprintf(
            'HTML Template %d was refused (%s) after its stored configuration had already changed, and putting it back failed too. The fields named in changed are the ones that landed; read the template before editing it again.',
            $post_id,
            $refused_because,
        ),
        ['changed' => $changed, 'refused_because' => $refused_because],
    );
}

/**
 * Turn the edit input into the subset of stored fields to change, or the first
 * reason it cannot be applied. Only the keys the caller mentioned appear, which
 * is what keeps an edit from blanking the fields it was not about.
 *
 * @param array<string, mixed> $input
 * @param int $self_id the template being edited, which cannot render itself
 * @return array<string, string>|WP_Error
 */
function dce_edit_template_fields(array $input, int $self_id): array|WP_Error
{
    $mode = dce_edit_mode_changes($input, $self_id);
    if ($mode instanceof WP_Error) {
        return $mode;
    }

    return dce_edit_page_setup_changes($input, $mode);
}

/**
 * The changes that decide what a template renders, or the reason they cannot be
 * applied. Kept separate from the page setup because the two answer different
 * questions, and the rendering mode has to agree with what create accepts.
 *
 * @param array<string, mixed> $input
 * @param int $self_id the template being edited, which cannot render itself
 * @return array<string, string>|WP_Error
 */
function dce_edit_mode_changes(array $input, int $self_id = 0): array|WP_Error
{
    $has_code = array_key_exists('code', $input);
    $has_elementor = array_key_exists('elementor_template_id', $input);
    $code = (string) ($input['code'] ?? '');

    // The schema's `minimum` is checked against the raw value, and a numeric string
    // in exponent form passes it and then casts to zero — which would read as
    // "switch back to markup" and quietly drop the reference. Anything that is not
    // a plain non-negative integer is refused instead.
    if ($has_elementor && !dce_is_plain_id($input['elementor_template_id'])) {
        return dce_error('invalid_input', message: 'elementor_template_id must be a whole number.');
    }

    $elementor_id = (int) ($input['elementor_template_id'] ?? 0);

    // Same rule as create: an explicitly empty `code` alongside a reference is not
    // a contradiction, it is "render that template and keep no markup".
    if ($has_code && $code !== '' && $has_elementor && $elementor_id > 0) {
        return dce_ambiguous_mode_error();
    }

    $changes = [];

    if ($has_code) {
        $changes[DCE_TEMPLATE_FIELDS['code']] = $code;
        // Non-empty markup means this template is no longer an Elementor one, and
        // leaving the flag on would keep the markup from ever being rendered. An
        // empty `code` is not that statement, so the mode is left alone.
        if ($code !== '' || !$has_elementor) {
            $changes += dce_render_mode_fields(0);
        }
    }

    if ($has_elementor) {
        $target_error = dce_elementor_target_error($elementor_id, $self_id);
        if ($target_error instanceof WP_Error) {
            return $target_error;
        }
        $changes = dce_render_mode_fields($elementor_id) + $changes;
    }

    return $changes;
}

/**
 * Add the page setup to a set of changes, or return the first invalid value.
 *
 * @param array<string, mixed> $input
 * @param array<string, string> $changes
 * @return array<string, string>|WP_Error
 */
function dce_edit_page_setup_changes(array $input, array $changes): array|WP_Error
{
    foreach (['format', 'orientation'] as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }

        $validated = $key === 'format'
            ? dce_validate_format((string) $input[$key])
            : dce_validate_orientation((string) $input[$key]);

        if ($validated instanceof WP_Error) {
            return $validated;
        }

        $changes[DCE_TEMPLATE_FIELDS[$key]] = $validated;
    }

    return $changes;
}

/**
 * Friendly names of the stored fields whose value actually moved.
 *
 * @param array<string, string> $before
 * @param array<string, string> $after
 * @return list<string>
 */
function dce_changed_field_names(array $before, array $after): array
{
    $changed = [];
    foreach (DCE_TEMPLATE_FIELDS as $name => $field) {
        if (($before[$field] ?? '') === ($after[$field] ?? '')) {
            continue;
        }
        $changed[] = $name;
    }

    return $changed;
}

/**
 * Apply the two post fields this post type supports, returning the names of the
 * ones that moved. The post type declares support for the title only, but the
 * status is a post column rather than a support, so both are settable.
 *
 * @param array<string, mixed> $input
 * @return list<string>|WP_Error
 */
function dce_apply_post_fields(WP_Post $post, array $input): array|WP_Error
{
    $fields = [];
    $changed = [];

    // WordPress unslashes and trims a title on the way in, so the value that comes
    // back is not always the value sent. Matching that here keeps the comparison
    // honest — otherwise an identical call reports "title changed" for ever.
    if (array_key_exists('title', $input)) {
        $title = trim((string) $input['title']);
        if ($title !== $post->post_title) {
            $fields['post_title'] = wp_slash($title);
            $changed[] = 'title';
        }
    }

    if (array_key_exists('status', $input) && (string) $input['status'] !== $post->post_status) {
        $fields['post_status'] = (string) $input['status'];
        $changed[] = 'status';
    }

    if ($fields === []) {
        return $changed;
    }

    $fields['ID'] = $post->ID;
    $updated = wp_update_post($fields, wp_error: true);

    return $updated instanceof WP_Error ? dce_wp_post_error($updated, operation: 'update') : $changed;
}
