<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use FrmAppHelper;
use FrmField;
use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/formidable-get-form-canonical', [
    'label' => __('Get Formidable Form (Canonical)', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Formidable form by id and returns it mapped to the plugin-agnostic form-model-schema vocabulary (17 canonical types: text/email/tel/url/number/textarea/date/time/datetime/select/radio/checkbox/file/hidden/submit/consent/group) used by the form-model-migration skill. Formidable native field types (~30 including Pro) fold onto those 17 — composites (`name`, `address`, `divider|repeat`) collapse to canonical `group`; structural-only types (`divider`, `end_divider`, `break`, `html`, `summary`, `captcha`) drop with informational warnings; Pro payment types (`credit_card`, `product`, `quantity`, `total`) drop with structural warnings. The form lives in `wp_frm_forms` (with the legacy `options` blob carrying `success_msg` / `success_action` / `success_url`); fields live in `wp_frm_fields` (one row each, loaded via FrmField::get_all_for_form). Notifications are `frm_form_actions` posts with `post_excerpt="email"`; the on-submit confirmation is a `frm_form_actions` post with `post_excerpt="on_submit"`, falling back to the legacy `options.success_*` keys. Output keys: `fields[]` with canonical types, `mail_config{}` from the first published email action, `notifications[]` from the rest, `messages{}` (empty — Formidable has no built-in message catalogue), `conditional_rules[]` from per-field `field_options.hide_field` / `hide_opt` (mapped 1:1 when expressible), `meta{}` (source plugin + version + lossless form options echo), plus `warnings[]` for everything that does not fit the canonical shape (multi-page `break`, repeater sections, payment fields, captcha, custom redirects). Smart-tag syntax `[FIELD_ID]` and `[field_key]` inside subject / message / recipient is rewritten to `[<canonical-name>]` to match the CF7 / WPForms / Gravity / Fluent canonical convention. Honours the same `max_field_bytes` truncation contract as the other canonical readers (default 8192 bytes for `mail_config.body`, `notifications[].body`, with `*_truncated` flags); pass `max_field_bytes: 0` to disable truncation when full-fidelity canonical is required before an execute-mode migration. Returns WP_Error `formidable_form_not_found` when the id does not exist or refers to a trashed form. Use this ability inside the form-model-migration workflow (Phase 2 — read source schema); for ad-hoc inspection outside the migration pipeline, prefer `formidable-get-form` which returns the Formidable-native shape.',
        domain: 'novamira-pro',
    ),
    'category' => 'formidable',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Formidable form id.',
            ],
            'max_field_bytes' => [
                'type' => 'integer',
                'description' => 'Per-field byte cap for the large text properties (`mail_config.body`, `notifications[].body`). Default 8192. Set to 0 to disable truncation entirely.',
                'minimum' => 0,
                'default' => 8192,
            ],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'title' => ['type' => 'string'],
            'slug' => ['type' => 'string'],
            'locale' => ['type' => 'string'],
            'fields' => ['type' => 'array'],
            'mail_config' => ['type' => 'object'],
            'notifications' => ['type' => 'array'],
            'messages' => ['type' => 'object'],
            'conditional_rules' => ['type' => 'array'],
            'meta' => ['type' => 'object'],
            'warnings' => ['type' => 'array'],
        ],
        'required' => [
            'form_id',
            'title',
            'slug',
            'locale',
            'fields',
            'mail_config',
            'notifications',
            'messages',
            'conditional_rules',
            'meta',
            'warnings',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Formidable\\formidable_get_form_canonical',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use as Phase 2 (read source) of the form-model-migration workflow when Formidable is the source plugin. The output is the canonical form-model-schema document directly — feed it into Phase 3 (translate) as-is; warnings[] already enumerates structural / informational losses (multi-page break, repeater sections, payments, captcha, custom redirects). Pass max_field_bytes:0 before an execute-mode migration so canonical bodies are not truncated.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, max_field_bytes?: int} $input
 *
 * @return array<string, mixed>|WP_Error
 */
function formidable_get_form_canonical(array $input): array|WP_Error
{
    if (!frm_detect_setup()['active']) {
        return frm_not_active();
    }

    $form = frm_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $cap_error = frm_capability_or_error('frm_view_forms');
    if ($cap_error instanceof WP_Error) {
        return $cap_error;
    }

    $max_field_bytes = isset($input['max_field_bytes']) ? max(0, (int) $input['max_field_bytes']) : 8192;

    return formidable_canonical_compose($form, $max_field_bytes);
}

/**
 * Build the canonical document from a Formidable form row. Loads field rows
 * via FrmField::get_all_for_form() and form actions (email + on_submit) via
 * FrmFormAction::get_all(). Settings come from the form's `options` blob
 * (legacy storage for success_msg / success_action / success_url).
 *
 * @return array<string, mixed>
 */
function formidable_canonical_compose(\stdClass $form, int $max_field_bytes): array
{
    $form_id = (int) ($form->id ?? 0);
    $title = is_string($form->name ?? null) ? $form->name : '';
    $form_key = is_string($form->form_key ?? null) ? $form->form_key : '';

    $field_rows = formidable_canonical_load_fields($form_id);
    $field_name_map = formidable_canonical_build_field_name_map($field_rows);
    $form_options = formidable_canonical_form_options($form);

    $email_actions = formidable_canonical_load_actions($form_id, type: 'email');
    $on_submit_actions = formidable_canonical_load_actions($form_id, type: 'on_submit');

    $detection = frm_detect_setup();
    $version = is_string($detection['version']) ? $detection['version'] : '';

    $fields_result = formidable_canonical_translate_fields($field_rows, $field_name_map);
    $primary_index = formidable_canonical_primary_action_index($email_actions);
    $mail_config = formidable_canonical_translate_mail_config(
        $primary_index >= 0 ? $email_actions[$primary_index] : null,
        $field_name_map,
        $max_field_bytes,
    );
    $notifications = formidable_canonical_translate_notifications(
        $email_actions,
        $primary_index,
        $field_name_map,
        $max_field_bytes,
    );
    $conditional_rules = formidable_canonical_collect_conditional_rules($field_rows, $field_name_map);
    $form_warnings = formidable_canonical_form_warnings($field_rows, $form_options, $on_submit_actions);

    return [
        'form_id' => $form_id,
        'title' => $title,
        'slug' => $form_key !== '' ? sanitize_title($form_key) : sanitize_title($title),
        'locale' => '',
        'fields' => $fields_result['fields'],
        'mail_config' => $mail_config,
        'notifications' => $notifications,
        'messages' => (object) [],
        'conditional_rules' => $conditional_rules,
        'meta' => formidable_canonical_build_meta($version, $form_options, $on_submit_actions),
        'warnings' => array_merge($fields_result['warnings'], $form_warnings),
    ];
}

/**
 * Load Formidable field rows for a form id and normalise each `stdClass`
 * to a plain associative array (mirrors the GF reader's normalisation
 * step). FrmField::get_all_for_form() returns rows ordered by `field_order`
 * with `field_options` / `options` already decoded.
 *
 * @return list<array<array-key, mixed>>
 */
function formidable_canonical_load_fields(int $form_id): array
{
    /** @var list<\stdClass> $rows */
    $rows = FrmField::get_all_for_form($form_id);
    /** @var list<array<array-key, mixed>> $out */
    $out = [];
    foreach ($rows as $row) {
        $out[] = get_object_vars($row);
    }
    return $out;
}

/**
 * Load Formidable form actions of a given type (`email`, `on_submit`).
 * Action rows are `WP_Post` objects of post_type `frm_form_actions` with
 * the typed settings serialised into `post_content`. The `post_excerpt`
 * column carries the action type slug; `menu_order` carries the form id
 * (Formidable's storage convention). Each row's `post_content` is
 * decoded once and the normalised array is stitched onto the returned
 * entry as `settings`.
 *
 * @return list<array<array-key, mixed>>
 */
function formidable_canonical_load_actions(int $form_id, string $type): array
{
    /** @var list<WP_Post> $posts */
    $posts = get_posts([
        'post_type' => FRM_ACTION_POST_TYPE,
        'post_status' => ['publish', 'draft'],
        'numberposts' => -1,
        'orderby' => 'menu_order title',
        'order' => 'ASC',
        'meta_query' => [],
        'suppress_filters' => false,
    ]);
    /** @var list<array<array-key, mixed>> $out */
    $out = [];
    foreach ($posts as $post) {
        if ($post->post_excerpt !== $type) {
            continue;
        }
        if ((int) $post->menu_order !== $form_id) {
            continue;
        }
        $entry = formidable_canonical_action_to_array($post);
        if ($entry === null) {
            continue;
        }
        $out[] = $entry;
    }
    return $out;
}

/**
 * Converts a `WP_Post` form-action row into a plain associative array
 * `{ID, post_title, post_status, post_excerpt, settings: array}`.
 * `post_content` is unserialised via the canonical FrmAppHelper decoder.
 *
 * @return array<string, mixed>|null
 */
function formidable_canonical_action_to_array(mixed $row): ?array
{
    if (!is_object($row)) {
        return null;
    }
    $vars = get_object_vars($row);
    if (!isset($vars['ID'])) {
        return null;
    }
    /** @var mixed $content_raw */
    $content_raw = $vars['post_content'] ?? '';
    $settings = [];
    // @mago-expect analysis:mixed-assignment
    if (is_array($content_raw)) {
        $settings = $content_raw;
    }
    if (is_string($content_raw) && $content_raw !== '') {
        FrmAppHelper::unserialize_or_decode($content_raw);
        // @mago-expect analysis:mixed-assignment
        if (is_array($content_raw)) {
            $settings = $content_raw;
        }
    }
    return [
        'ID' => (int) $vars['ID'],
        'post_title' => isset($vars['post_title']) && is_string($vars['post_title']) ? $vars['post_title'] : '',
        'post_status' => isset($vars['post_status']) && is_string($vars['post_status'])
            ? $vars['post_status']
            : 'publish',
        'post_excerpt' => isset($vars['post_excerpt']) && is_string($vars['post_excerpt']) ? $vars['post_excerpt'] : '',
        'settings' => $settings,
    ];
}

/**
 * Decode the form-level `options` blob (legacy storage for success_msg,
 * success_action, success_url, before_html, after_html, submit_value, etc.).
 *
 * @return array<array-key, mixed>
 */
function formidable_canonical_form_options(\stdClass $form): array
{
    /** @var mixed $raw */
    $raw = $form->options ?? null;
    if (is_array($raw)) {
        return $raw;
    }
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    FrmAppHelper::unserialize_or_decode($raw);
    return is_array($raw) ? $raw : [];
}

/**
 * Builds the field-id / field-key → canonical-name lookup. Formidable
 * smart-tags reference fields by either bare numeric id `[123]` or the
 * field_key `[my-field-key]`, so the map carries both forms keyed to the
 * same canonical slug (derived from the field's `name`, mirroring the
 * CF7 / WPForms / Gravity / Fluent convention).
 *
 * @param list<array<array-key, mixed>> $field_rows
 *
 * @return array<string, string>
 */
function formidable_canonical_build_field_name_map(array $field_rows): array
{
    $map = [];
    foreach ($field_rows as $field) {
        $id = isset($field['id']) ? (string) $field['id'] : '';
        $field_key = isset($field['field_key']) && is_string($field['field_key']) ? $field['field_key'] : '';
        $canonical = formidable_canonical_derive_name($field, $id);
        if ($canonical === '') {
            continue;
        }
        if ($id !== '') {
            $map[$id] = $canonical;
        }
        if ($field_key !== '') {
            $map[$field_key] = $canonical;
        }
    }
    return $map;
}

/**
 * Derives the canonical slug for a Formidable field. Prefers
 * `sanitize_title(name)` (matches CF7 / WPForms / GF / FF), then
 * `field_key`, then a `field-<id>` fallback.
 *
 * @param array<array-key, mixed> $field
 */
function formidable_canonical_derive_name(array $field, string $id): string
{
    $label = isset($field['name']) && is_string($field['name']) ? $field['name'] : '';
    if ($label !== '') {
        $slug = sanitize_title($label);
        if ($slug !== '') {
            return $slug;
        }
    }
    $field_key = isset($field['field_key']) && is_string($field['field_key']) ? $field['field_key'] : '';
    if ($field_key !== '') {
        $slug = sanitize_title($field_key);
        if ($slug !== '') {
            return $slug;
        }
    }
    return $id !== '' ? 'field-' . $id : '';
}

/**
 * Picks the index of the Formidable email action to surface as the
 * canonical `mail_config{}`. Strategy: first action with `post_status =
 * publish` (enabled), then the first overall, then -1 when there are no
 * email actions.
 *
 * @param list<array<array-key, mixed>> $actions
 */
function formidable_canonical_primary_action_index(array $actions): int
{
    if ($actions === []) {
        return -1;
    }
    foreach ($actions as $i => $action) {
        if (($action['post_status'] ?? '') === 'publish') {
            return $i;
        }
    }
    return 0;
}

/**
 * Translates the flat Formidable field list into canonical fields[],
 * accumulating warnings for losses (payment, structural, captcha,
 * conditional logic, composite collapse).
 *
 * @param list<array<array-key, mixed>> $field_rows
 * @param array<string, string>         $field_name_map
 *
 * @return array{fields: list<array<string, mixed>>, warnings: list<array<string, string>>}
 */
function formidable_canonical_translate_fields(array $field_rows, array $field_name_map): array
{
    /** @var list<array<string, mixed>> $fields */
    $fields = [];
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    $index = 0;
    foreach ($field_rows as $field_row) {
        $result = formidable_canonical_translate_field($field_row, $index, $field_name_map);
        foreach ($result['warnings'] as $warning) {
            $warnings[] = $warning;
        }
        if ($result['field'] === null) {
            continue;
        }
        $fields[] = $result['field'];
        $index++;
    }

    return ['fields' => $fields, 'warnings' => $warnings];
}

/**
 * Maps a single Formidable field to canonical. Returns null when the type
 * has no canonical representation (divider, end_divider, break, html,
 * summary, captcha, credit_card, product, quantity, total).
 *
 * @param array<array-key, mixed> $field
 * @param array<string, string>   $field_name_map
 *
 * @return array{field: array<string, mixed>|null, warnings: list<array<string, string>>}
 */
function formidable_canonical_translate_field(array $field, int $field_index, array $field_name_map): array
{
    $type = isset($field['type']) && is_string($field['type']) ? $field['type'] : '';
    $id = isset($field['id']) ? (string) $field['id'] : '';
    $canonical_name = $field_name_map[$id] ?? '';
    if ($canonical_name === '') {
        $canonical_name = formidable_canonical_derive_name($field, $id);
    }
    if ($canonical_name === '') {
        $canonical_name = 'field-' . $field_index;
    }

    if (formidable_canonical_field_is_skipped($type)) {
        return [
            'field' => null,
            'warnings' => [formidable_canonical_skip_warning($type, $field_index)],
        ];
    }

    $canonical_type = formidable_canonical_type_map($type);
    if ($canonical_type === null) {
        return [
            'field' => null,
            'warnings' => [formidable_canonical_unmapped_warning($type, $field_index)],
        ];
    }

    $payload = formidable_canonical_field_payload($field, $type, $canonical_name, $canonical_type);
    $warnings = formidable_canonical_field_warnings($field, $type, $field_index);

    return ['field' => $payload, 'warnings' => $warnings];
}

/**
 * Returns true when the Formidable field type has no canonical role
 * (pure layout / metadata / payment / captcha) and should be dropped from
 * fields[] with a single warning.
 */
function formidable_canonical_field_is_skipped(string $type): bool
{
    return in_array(
        $type,
        [
            'divider',
            'end_divider',
            'break',
            'html',
            'summary',
            'captcha',
            'form',
            'credit_card',
            'product',
            'quantity',
            'total',
            'coupon',
            'ssa-appointment',
            'ai',
        ],
        strict: true,
    );
}

/**
 * Builds the warning entry for a skipped Formidable field. Severity is
 * `structural` for semantically heavy drops (multi-page break, payments,
 * repeater dividers, embedded forms) and `informational` for pure
 * rendering (html, summary, captcha, end_divider).
 *
 * @return array<string, string>
 */
function formidable_canonical_skip_warning(string $type, int $field_index): array
{
    $structural = [
        'break',
        'form',
        'credit_card',
        'product',
        'quantity',
        'total',
        'coupon',
        'ssa-appointment',
        'ai',
    ];
    $severity = in_array($type, $structural, strict: true) ? 'structural' : 'informational';
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => sprintf(
            'Formidable [%s] field has no canonical equivalent — preserved as a warning for the migration plan; the target plugin must provide its own implementation.',
            $type,
        ),
        'severity' => $severity,
    ];
}

/**
 * Maps a Formidable type to its canonical form-model-schema type. Returns
 * null when the type is not skipped but does not map cleanly (safety net
 * for types added by future Formidable releases or add-ons).
 */
function formidable_canonical_type_map(string $type): ?string
{
    return match ($type) {
        'text', 'rte', 'password' => 'text',
        'textarea' => 'textarea',
        'email' => 'email',
        'url' => 'url',
        'phone' => 'tel',
        'number', 'range', 'scale', 'star', 'nps' => 'number',
        'date' => 'date',
        'time' => 'time',
        'select', 'tag', 'data', 'lookup', 'ranking', 'likert' => 'select',
        'radio' => 'radio',
        'checkbox', 'toggle' => 'checkbox',
        'file', 'signature' => 'file',
        'hidden', 'user_id', 'virtual' => 'hidden',
        'gdpr' => 'consent',
        'name', 'address' => 'group',
        default => null,
    };
}

/**
 * Builds the canonical field array for the mapped Formidable field. Pulls
 * common keys (name / type / label / description / required / placeholder
 * / default / cardinality / choices / validation) and carries
 * Formidable-specific hints into meta for lossless round-trip.
 *
 * @param array<array-key, mixed> $field
 *
 * @return array<string, mixed>
 */
function formidable_canonical_field_payload(array $field, string $type, string $name, string $canonical_type): array
{
    $payload = [
        'name' => $name,
        'type' => $canonical_type,
    ];
    $field_options = is_array($field['field_options'] ?? null) ? $field['field_options'] : [];

    formidable_canonical_apply_string_property($payload, $field, key: 'name', dest: 'label');
    formidable_canonical_apply_string_property($payload, $field, key: 'description');
    formidable_canonical_apply_string_from($payload, $field_options, key: 'placeholder');
    formidable_canonical_apply_default_value($payload, $field);
    if ($canonical_type === 'consent' || formidable_canonical_is_required($field)) {
        $payload['required'] = true;
    }
    formidable_canonical_apply_cardinality($payload, $field, $type);
    formidable_canonical_apply_choices($payload, $field, $canonical_type);
    formidable_canonical_apply_validation($payload, $field, $field_options, $type, $canonical_type);
    formidable_canonical_apply_subtype($payload, $type, $canonical_type);
    $payload['meta'] = formidable_canonical_field_meta($field, $type);

    return $payload;
}

/**
 * Builds the canonical `meta{}` sub-block for a field. Carries the
 * original Formidable type and field_key (for round-trip rebuild) plus
 * any classes / admin labels.
 *
 * @param array<array-key, mixed> $field
 *
 * @return array<string, mixed>
 */
function formidable_canonical_field_meta(array $field, string $type): array
{
    $meta = ['formidable_type' => $type];
    if (isset($field['field_key']) && is_string($field['field_key']) && $field['field_key'] !== '') {
        $meta['formidable_field_key'] = $field['field_key'];
    }
    $field_options = is_array($field['field_options'] ?? null) ? $field['field_options'] : [];
    foreach (['classes', 'admin_only', 'label'] as $hint) {
        /** @var mixed $value */
        $value = $field_options[$hint] ?? null;
        if (is_string($value) && $value !== '') {
            $meta['formidable_' . $hint] = $value;
        }
    }
    return $meta;
}

/**
 * Copies a simple string property from the Formidable field row to the
 * canonical field when present and non-empty.
 *
 * @param array<string, mixed>     $payload
 * @param array<array-key, mixed>  $field
 */
function formidable_canonical_apply_string_property(
    array &$payload,
    array $field,
    string $key,
    ?string $dest = null,
): void {
    if (isset($field[$key]) && is_string($field[$key]) && $field[$key] !== '') {
        $payload[$dest ?? $key] = $field[$key];
    }
}

/**
 * Copies a simple string property from a Formidable sub-block (typically
 * `field_options`) to the canonical field when present.
 *
 * @param array<string, mixed>     $payload
 * @param array<array-key, mixed>  $source
 */
function formidable_canonical_apply_string_from(array &$payload, array $source, string $key, ?string $dest = null): void
{
    if (isset($source[$key]) && is_string($source[$key]) && $source[$key] !== '') {
        $payload[$dest ?? $key] = $source[$key];
    }
}

/**
 * Carries the Formidable `default_value` to the canonical `default` key
 * when present and non-empty. Multi-value defaults (arrays) are dropped —
 * the field-specific subtype handler will re-derive them from
 * field_options if applicable.
 *
 * @param array<string, mixed>    $payload
 * @param array<array-key, mixed> $field
 */
function formidable_canonical_apply_default_value(array &$payload, array $field): void
{
    /** @var mixed $value */
    $value = $field['default_value'] ?? null;
    if (is_string($value) && $value !== '') {
        $payload['default'] = $value;
    }
}

/**
 * Whether the Formidable field row is required. Required state lives at
 * the row-level `required` column (1 / 0 / "1" / "0").
 *
 * @param array<array-key, mixed> $field
 */
function formidable_canonical_is_required(array $field): bool
{
    return formidable_canonical_truthy($field['required'] ?? null);
}

/**
 * Sets canonical `cardinality` for the field types where the value is
 * meaningful (select multi, checkbox group, dynamic / tag / ranking /
 * likert). Leaves the key absent on scalar types.
 *
 * @param array<string, mixed>    $payload
 * @param array<array-key, mixed> $field
 */
function formidable_canonical_apply_cardinality(array &$payload, array $field, string $type): void
{
    $field_options = is_array($field['field_options'] ?? null) ? $field['field_options'] : [];
    if ($type === 'checkbox') {
        $payload['cardinality'] = 'multi';
        return;
    }
    if ($type === 'tag' || $type === 'ranking' || $type === 'likert') {
        $payload['cardinality'] = 'multi';
        return;
    }
    if ($type === 'select' || $type === 'data' || $type === 'lookup') {
        $payload['cardinality'] = formidable_canonical_truthy($field_options['multiple'] ?? null) ? 'multi' : 'single';
        return;
    }
    if ($type === 'radio') {
        $payload['cardinality'] = 'single';
    }
}

/**
 * Translates Formidable `options` (the choices array on the field row)
 * into canonical `choices[{value,label}]` for select / checkbox / radio.
 * Formidable choices may be (a) flat scalars `['A','B']` (label==value),
 * (b) `{label,value}` objects, or (c) keyed maps. All three normalise to
 * the canonical shape.
 *
 * @param array<string, mixed>    $payload
 * @param array<array-key, mixed> $field
 */
function formidable_canonical_apply_choices(array &$payload, array $field, string $canonical_type): void
{
    if (!in_array($canonical_type, ['select', 'checkbox', 'radio'], strict: true)) {
        return;
    }
    /** @var mixed $raw */
    $raw = $field['options'] ?? null;
    if (!is_array($raw) || $raw === []) {
        return;
    }
    $choices = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $key => $entry) {
        $choice = formidable_canonical_normalise_choice($key, $entry);
        if ($choice === null) {
            continue;
        }
        $choices[] = $choice;
    }
    if ($choices !== []) {
        $payload['choices'] = $choices;
    }
}

/**
 * Normalises one Formidable `options` entry into a canonical
 * `{value,label}` pair. Skips the "other_" marker and the synthetic
 * 0-keyed prompt that Formidable injects ahead of the user options on
 * dropdown fields.
 *
 * @return array<string, string>|null
 */
// @mago-expect lint:halstead
function formidable_canonical_normalise_choice(mixed $key, mixed $entry): ?array
{
    if (is_string($key) && str_starts_with($key, 'other_')) {
        return null;
    }
    if (is_array($entry)) {
        $label = isset($entry['label']) && is_string($entry['label']) ? $entry['label'] : '';
        $value =
            isset($entry['value']) && is_string($entry['value']) && $entry['value'] !== '' ? $entry['value'] : $label;
        if ($label === '' && $value === '') {
            return null;
        }
        return ['value' => $value, 'label' => $label !== '' ? $label : $value];
    }
    if (is_string($entry) || is_int($entry) || is_float($entry)) {
        $value = (string) $entry;
        if ($value === '') {
            return null;
        }
        return ['value' => $value, 'label' => $value];
    }
    return null;
}

/**
 * Populates the canonical `validation` block from Formidable
 * `field_options` limits (min / max / max characters / file accept).
 *
 * @param array<string, mixed>    $payload
 * @param array<array-key, mixed> $field
 * @param array<array-key, mixed> $field_options
 */
function formidable_canonical_apply_validation(
    array &$payload,
    array $field,
    array $field_options,
    string $type,
    string $canonical_type,
): void {
    $validation = array_merge(
        formidable_canonical_number_validation($field_options, $canonical_type),
        formidable_canonical_file_validation($field_options, $type),
        formidable_canonical_length_validation($field_options, $canonical_type),
    );
    if ($validation !== []) {
        $payload['validation'] = $validation;
    }
}

/**
 * Extracts canonical `min` / `max` from a Formidable number / range /
 * scale field. Returns [] for non-number types.
 *
 * @param array<array-key, mixed> $field_options
 *
 * @return array<string, int|float>
 */
function formidable_canonical_number_validation(array $field_options, string $canonical_type): array
{
    if ($canonical_type !== 'number') {
        return [];
    }
    $out = [];
    if (isset($field_options['minnum']) && is_numeric($field_options['minnum'])) {
        $out['min'] = formidable_canonical_to_number($field_options['minnum']);
    }
    if (isset($field_options['maxnum']) && is_numeric($field_options['maxnum'])) {
        $out['max'] = formidable_canonical_to_number($field_options['maxnum']);
    }
    return $out;
}

/**
 * Extracts canonical `accepts[]` and `max_size_bytes` from a Formidable
 * file field. Formidable stores accepts as a comma-separated string under
 * `ftypes` (alt: `mime_types`) and `max_filesize` in MB.
 *
 * @param array<array-key, mixed> $field_options
 *
 * @return array<string, mixed>
 */
function formidable_canonical_file_validation(array $field_options, string $type): array
{
    if ($type !== 'file' && $type !== 'signature') {
        return [];
    }
    $out = [];
    /** @var mixed $ftypes */
    $ftypes = $field_options['ftypes'] ?? $field_options['mime_types'] ?? null;
    $accepts = formidable_canonical_parse_extensions($ftypes);
    if ($accepts !== []) {
        $out['accepts'] = $accepts;
    }
    /** @var mixed $size */
    $size = $field_options['max_filesize'] ?? null;
    if (is_numeric($size)) {
        $out['max_size_bytes'] = (int) round(floatval($size) * 1024 * 1024);
    }
    return $out;
}

/**
 * Extracts canonical `max_length` from a Formidable text / textarea
 * field's `size` (Formidable repurposes `size` as the char limit on
 * single-line text, and `max` on textarea).
 *
 * @param array<array-key, mixed> $field_options
 *
 * @return array<string, int>
 */
function formidable_canonical_length_validation(array $field_options, string $canonical_type): array
{
    if (!in_array($canonical_type, ['text', 'textarea'], strict: true)) {
        return [];
    }
    /** @var mixed $max */
    $max = $field_options['max'] ?? null;
    if (!is_numeric($max)) {
        return [];
    }
    $limit = (int) $max;
    if ($limit <= 0) {
        return [];
    }
    return ['max_length' => $limit];
}

/**
 * Casts a numeric-looking Formidable value to native int / float. Same
 * convention as the GF / Fluent canonical readers.
 */
function formidable_canonical_to_number(mixed $value): int|float
{
    if (!is_numeric($value)) {
        return 0;
    }
    $string = (string) $value;
    return str_contains($string, '.') ? floatval($string) : (int) $string;
}

/**
 * Parses a Formidable accept-list (e.g. `jpg,png,pdf` or `image/jpeg,
 * application/pdf`) into canonical MIME types. Unknown extensions are
 * dropped.
 *
 * @return list<string>
 */
function formidable_canonical_parse_extensions(mixed $raw): array
{
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $map = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'gif' => 'image/gif',
        'webp' => 'image/webp',
        'svg' => 'image/svg+xml',
        'zip' => 'application/zip',
        'txt' => 'text/plain',
        'csv' => 'text/csv',
    ];
    $out = [];
    foreach (explode(separator: ',', string: strtolower($raw)) as $token) {
        $token = trim($token);
        if ($token === '') {
            continue;
        }
        if (str_contains($token, '/')) {
            $out[] = $token;
            continue;
        }
        if (isset($map[$token])) {
            $out[] = $map[$token];
        }
    }
    return array_values(array_unique($out));
}

/**
 * Folds Formidable type-specific specialisers into the canonical field
 * (password → format=password; range → subtype=range; scale → subtype=
 * scale; star → subtype=rating; nps → subtype=nps; rte → format=html;
 * signature → subtype=signature; user_id / virtual → subtype hint).
 *
 * @param array<string, mixed> $payload
 */
function formidable_canonical_apply_subtype(array &$payload, string $type, string $canonical_type): void
{
    $subtype = match ($type) {
        'password' => ['key' => 'format', 'value' => 'password'],
        'rte' => ['key' => 'format', 'value' => 'html'],
        'range' => ['key' => 'subtype', 'value' => 'range'],
        'scale' => ['key' => 'subtype', 'value' => 'scale'],
        'star' => ['key' => 'subtype', 'value' => 'rating'],
        'nps' => ['key' => 'subtype', 'value' => 'nps'],
        'tag' => ['key' => 'subtype', 'value' => 'tags'],
        'data' => ['key' => 'subtype', 'value' => 'dynamic'],
        'lookup' => ['key' => 'subtype', 'value' => 'lookup'],
        'ranking' => ['key' => 'subtype', 'value' => 'ranking'],
        'likert' => ['key' => 'subtype', 'value' => 'likert'],
        'signature' => ['key' => 'subtype', 'value' => 'signature'],
        'toggle' => ['key' => 'subtype', 'value' => 'toggle'],
        'user_id' => ['key' => 'subtype', 'value' => 'user_id'],
        'virtual' => ['key' => 'subtype', 'value' => 'virtual'],
        default => null,
    };
    if ($subtype === null) {
        return;
    }
    if (
        $canonical_type === 'text'
        || $canonical_type === 'textarea'
        || $canonical_type === 'number'
        || $canonical_type === 'select'
        || $canonical_type === 'checkbox'
        || $canonical_type === 'file'
        || $canonical_type === 'hidden'
    ) {
        $payload[$subtype['key']] = $subtype['value'];
    }
}

/**
 * Per-field migration warnings: composite collapse (name / address),
 * conditional logic presence, plugin-specific UI hints.
 *
 * @param array<array-key, mixed> $field
 *
 * @return list<array<string, string>>
 */
function formidable_canonical_field_warnings(array $field, string $type, int $field_index): array
{
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    if ($type === 'name' || $type === 'address') {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => sprintf(
                'Formidable composite [%s] collapsed to canonical group — sub-input structure (first/middle/last for name; line1/line2/city/state/zip for address) is plugin-specific and will need re-creation on the target.',
                $type,
            ),
            'severity' => 'informational',
        ];
    }
    if (in_array($type, ['scale', 'star', 'nps', 'tag', 'data', 'lookup', 'ranking', 'likert'], strict: true)) {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => sprintf(
                'Formidable [%s] mapped to a generic canonical type — UI semantics (scale / star rating / NPS / tags / dynamic-from-form / lookup / ranking / matrix likert) are plugin-specific and must be reconfigured on the target.',
                $type,
            ),
            'severity' => 'informational',
        ];
    }
    if (formidable_canonical_has_conditional_logic($field)) {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => 'Formidable conditional logic (`hide_field` / `hide_opt` / `show_hide`) is mapped to canonical conditional_rules[] when expressible — complex nested AND/OR rules or value-derived triggers are flattened with possible semantic loss.',
            'severity' => 'informational',
        ];
    }

    return $warnings;
}

/**
 * Returns true when a Formidable field has a `field_options.hide_field`
 * with at least one entry and a non-empty `show_hide` action.
 *
 * @param array<array-key, mixed> $field
 */
function formidable_canonical_has_conditional_logic(array $field): bool
{
    $field_options = is_array($field['field_options'] ?? null) ? $field['field_options'] : [];
    /** @var mixed $hide_field */
    $hide_field = $field_options['hide_field'] ?? null;
    return is_array($hide_field) && $hide_field !== [];
}

/**
 * Translates the first published Formidable email action into the
 * canonical `mail_config{}`. When no action is present the canonical
 * still emits the block with empty values so downstream consumers don't
 * have to special-case it.
 *
 * @param mixed                 $action
 * @param array<string, string> $field_name_map
 *
 * @return array<string, mixed>
 */
function formidable_canonical_translate_mail_config(mixed $action, array $field_name_map, int $max_field_bytes): array
{
    if (!is_array($action)) {
        return formidable_canonical_empty_mail_config();
    }
    return formidable_canonical_action_to_canonical($action, $field_name_map, $max_field_bytes);
}

/**
 * Translates every Formidable email action EXCEPT the primary one into
 * canonical notifications[]. Each entry carries `name`, `active`, and
 * the mail-config keys the primary uses.
 *
 * @param list<array<array-key, mixed>> $actions
 * @param array<string, string>         $field_name_map
 *
 * @return list<array<string, mixed>>
 */
function formidable_canonical_translate_notifications(
    array $actions,
    int $primary_index,
    array $field_name_map,
    int $max_field_bytes,
): array {
    /** @var list<array<string, mixed>> $out */
    $out = [];
    foreach ($actions as $i => $action) {
        if ($i === $primary_index) {
            continue;
        }
        $entry = formidable_canonical_action_to_canonical($action, $field_name_map, $max_field_bytes);
        $entry = array_merge([
            'name' => formidable_canonical_action_name($action, $i),
            'active' => ($action['post_status'] ?? '') === 'publish',
        ], $entry);
        $out[] = $entry;
    }
    return $out;
}

/**
 * Returns the canonical notification name. Formidable carries the user
 * label in `post_title`; fall back to `notification-<index>` when empty.
 *
 * @param array<array-key, mixed> $action
 */
function formidable_canonical_action_name(array $action, int $index): string
{
    if (isset($action['post_title']) && is_string($action['post_title']) && $action['post_title'] !== '') {
        $slug = sanitize_title($action['post_title']);
        if ($slug !== '') {
            return $slug;
        }
    }
    return 'notification-' . $index;
}

/**
 * Translates a single Formidable email-action settings blob into the
 * canonical mail-config shape (shared by `mail_config{}` and each
 * `notifications[]` entry). `[FIELD_ID]` and `[field_key]` smart-tags
 * inside subject / message / recipient / cc / bcc are rewritten to
 * `[canonical-name]` to match the CF7 / WPForms / Gravity / Fluent
 * canonical convention.
 *
 * @param array<array-key, mixed> $action
 * @param array<string, string>   $field_name_map
 *
 * @return array<string, mixed>
 */
function formidable_canonical_action_to_canonical(array $action, array $field_name_map, int $max_field_bytes): array
{
    $settings = is_array($action['settings'] ?? null) ? $action['settings'] : [];
    $body_raw = formidable_canonical_normalize_smart_tags(
        isset($settings['email_message']) && is_string($settings['email_message']) ? $settings['email_message'] : '',
        $field_name_map,
    );
    $body_truncated = formidable_canonical_truncate($body_raw, $max_field_bytes);

    return [
        'subject' => formidable_canonical_normalize_smart_tags(
            isset($settings['email_subject']) && is_string($settings['email_subject'])
                ? $settings['email_subject']
                : '',
            $field_name_map,
        ),
        'sender' => isset($settings['from']) && is_string($settings['from']) ? $settings['from'] : '',
        'recipient' => formidable_canonical_normalize_smart_tags(
            isset($settings['email_to']) && is_string($settings['email_to']) ? $settings['email_to'] : '',
            $field_name_map,
        ),
        'body' => $body_truncated['value'],
        'body_truncated' => $body_truncated['truncated'],
        'body_bytes' => $body_truncated['original_bytes'],
        'additional_headers' => formidable_canonical_collect_extra_headers($settings, $field_name_map),
        'attachments' => [],
        'use_html' => !formidable_canonical_truthy($settings['plain_text'] ?? null),
        'exclude_blank' => formidable_canonical_truthy($settings['inc_user_info'] ?? null),
    ];
}

/**
 * Collects Formidable cc / bcc / reply_to as a canonical
 * `additional_headers` string. Empty string when none are set.
 *
 * @param array<array-key, mixed> $settings
 * @param array<string, string>   $field_name_map
 */
function formidable_canonical_collect_extra_headers(array $settings, array $field_name_map): string
{
    $parts = [];
    foreach (['cc' => 'Cc', 'bcc' => 'Bcc', 'reply_to' => 'Reply-To'] as $key => $header) {
        if (!isset($settings[$key]) || !is_string($settings[$key]) || $settings[$key] === '') {
            continue;
        }
        $value = formidable_canonical_normalize_smart_tags($settings[$key], $field_name_map);
        $parts[] = $header . ': ' . $value;
    }
    return implode("\n", $parts);
}

/**
 * Returns an empty canonical mail-config block.
 *
 * @return array<string, mixed>
 */
function formidable_canonical_empty_mail_config(): array
{
    return [
        'subject' => '',
        'sender' => '',
        'recipient' => '',
        'body' => '',
        'body_truncated' => false,
        'body_bytes' => 0,
        'additional_headers' => '',
        'attachments' => [],
        'use_html' => true,
        'exclude_blank' => false,
    ];
}

/**
 * Rewrites Formidable `[FIELD_ID]` and `[field_key]` smart-tags to the
 * canonical `[canonical-name]` convention. The regex captures the bare
 * bracketed token and looks it up against both the id-keyed and
 * key-keyed entries of the field_name_map. Globals like `[admin_email]`,
 * `[sitename]`, `[siteurl]`, `[id]`, `[key]`, `[ip]` are preserved
 * verbatim — they are universal macros, not form-scoped references.
 *
 * @param array<string, string> $field_name_map
 */
function formidable_canonical_normalize_smart_tags(string $input, array $field_name_map): string
{
    if ($input === '' || $field_name_map === []) {
        return $input;
    }
    $globals = [
        'admin_email',
        'default-email',
        'default-from-email',
        'sitename',
        'siteurl',
        'id',
        'key',
        'ip',
        'editlink',
        'form_name',
        'created_at',
        'created-at',
        'updated_at',
        'updated-at',
        'updated_by',
        'updated-by',
        'post_id',
        'post-id',
        'parent_id',
        'parent-id',
    ];
    $rewritten = preg_replace_callback(
        pattern: '/\[([a-zA-Z0-9_-]+)\]/',
        callback: static function (array $match) use ($field_name_map, $globals): string {
            $token = $match[1] ?? '';
            if ($token === '' || in_array($token, $globals, strict: true)) {
                return $match[0];
            }
            $name = $field_name_map[$token] ?? '';
            return $name !== '' ? '[' . $name . ']' : $match[0];
        },
        subject: $input,
    );
    return $rewritten ?? $input;
}

/**
 * Collects canonical conditional_rules[] from per-field Formidable
 * `field_options.hide_field`. Each rule references the parent field
 * (target) via its canonical name and lists triggering conditions
 * referencing source-field canonical names.
 *
 * @param list<array<array-key, mixed>> $field_rows
 * @param array<string, string>         $field_name_map
 *
 * @return list<array<string, mixed>>
 */
function formidable_canonical_collect_conditional_rules(array $field_rows, array $field_name_map): array
{
    /** @var list<array<string, mixed>> $rules */
    $rules = [];
    foreach ($field_rows as $field) {
        if (!formidable_canonical_has_conditional_logic($field)) {
            continue;
        }
        $id = isset($field['id']) ? (string) $field['id'] : '';
        $target_name = $field_name_map[$id] ?? '';
        if ($target_name === '') {
            continue;
        }
        $rule = formidable_canonical_field_to_rule($field, $target_name, $field_name_map);
        if ($rule !== null) {
            $rules[] = $rule;
        }
    }
    return $rules;
}

/**
 * Translates one Formidable field's `field_options.hide_field` block
 * into a canonical rule `{target_field, operator, logic, conditions[]}`.
 * Formidable uses `show_hide` (`show`/`hide`) for the action and
 * `any_all` (`any`/`all`) for the combinator.
 *
 * @param array<array-key, mixed> $field
 * @param array<string, string>   $field_name_map
 *
 * @return array<string, mixed>|null
 */
// @mago-expect lint:cyclomatic-complexity
function formidable_canonical_field_to_rule(array $field, string $target_name, array $field_name_map): ?array
{
    $field_options = is_array($field['field_options'] ?? null) ? $field['field_options'] : [];
    $hide_field = is_array($field_options['hide_field'] ?? null) ? $field_options['hide_field'] : [];
    $hide_opt = is_array($field_options['hide_field_cond'] ?? null) ? $field_options['hide_field_cond'] : [];
    $hide_val = is_array($field_options['hide_opt'] ?? null) ? $field_options['hide_opt'] : [];
    $conditions = [];
    // @mago-expect analysis:mixed-assignment
    foreach (array_values($hide_field) as $i => $hide_id) {
        $hid = is_scalar($hide_id) ? (string) $hide_id : '';
        if ($hid === '') {
            continue;
        }
        $name = $field_name_map[$hid] ?? '';
        if ($name === '') {
            continue;
        }
        $op = isset($hide_opt[$i]) && is_string($hide_opt[$i]) ? $hide_opt[$i] : '==';
        $val = isset($hide_val[$i]) && is_scalar($hide_val[$i]) ? (string) $hide_val[$i] : '';
        $conditions[] = ['field' => $name, 'op' => $op, 'value' => $val];
    }
    if ($conditions === []) {
        return null;
    }
    $show_hide = isset($field_options['show_hide']) && is_string($field_options['show_hide'])
        ? $field_options['show_hide']
        : 'show';
    return [
        'target_field' => $target_name,
        'operator' => $show_hide === 'hide' ? 'hide_if' : 'show_if',
        'logic' => isset($field_options['any_all']) && is_string($field_options['any_all'])
            ? $field_options['any_all']
            : 'all',
        'conditions' => $conditions,
    ];
}

/**
 * Form-level warnings the field-level translator cannot see: multi-page
 * forms (any `break` field), confirmation redirects, repeater section
 * dividers, payment fields presence (already field-warned).
 *
 * @param list<array<array-key, mixed>> $field_rows
 * @param array<array-key, mixed>       $form_options
 * @param list<array<array-key, mixed>> $on_submit_actions
 *
 * @return list<array<string, string>>
 */
function formidable_canonical_form_warnings(array $field_rows, array $form_options, array $on_submit_actions): array
{
    $warnings = [];
    $multipage = formidable_canonical_multipage_warning($field_rows);
    if ($multipage !== null) {
        $warnings[] = $multipage;
    }
    $repeater = formidable_canonical_repeater_warning($field_rows);
    if ($repeater !== null) {
        $warnings[] = $repeater;
    }
    $confirm = formidable_canonical_confirmation_warning($form_options, $on_submit_actions);
    if ($confirm !== null) {
        $warnings[] = $confirm;
    }
    return $warnings;
}

/**
 * Detects multi-page forms (any `break` field) and emits the canonical
 * structural warning. Returns null when single-page.
 *
 * @param list<array<array-key, mixed>> $field_rows
 *
 * @return array<string, string>|null
 */
function formidable_canonical_multipage_warning(array $field_rows): ?array
{
    foreach ($field_rows as $field) {
        if (($field['type'] ?? '') !== 'break') {
            continue;
        }
        return [
            'path' => 'fields',
            'reason' => 'Formidable multi-page form (one or more break fields) collapsed to a single-page canonical — the target plugin will receive a flat list of fields.',
            'severity' => 'structural',
        ];
    }
    return null;
}

/**
 * Detects repeater section dividers (`type=divider, field_options.repeat`)
 * and emits the canonical structural warning. Returns null when no
 * repeater is present.
 *
 * @param list<array<array-key, mixed>> $field_rows
 *
 * @return array<string, string>|null
 */
function formidable_canonical_repeater_warning(array $field_rows): ?array
{
    foreach ($field_rows as $field) {
        if (($field['type'] ?? '') !== 'divider') {
            continue;
        }
        $field_options = is_array($field['field_options'] ?? null) ? $field['field_options'] : [];
        if (!formidable_canonical_truthy($field_options['repeat'] ?? null)) {
            continue;
        }
        return [
            'path' => 'fields',
            'reason' => 'Formidable repeater section (divider with field_options.repeat = 1) collapsed — canonical has no first-class repeater block; the nested entry structure must be rebuilt on the target.',
            'severity' => 'structural',
        ];
    }
    return null;
}

/**
 * Detects post-submit redirects (either modern on_submit action
 * `success_action=redirect|page` or legacy options `success_action !=
 * message`) and emits the informational warning that the redirect target
 * is echoed to meta but is not directly representable in canonical.
 * Returns null when the form uses the default same-page message.
 *
 * @param array<array-key, mixed>       $form_options
 * @param list<array<array-key, mixed>> $on_submit_actions
 *
 * @return array<string, string>|null
 */
function formidable_canonical_confirmation_warning(array $form_options, array $on_submit_actions): ?array
{
    foreach ($on_submit_actions as $action) {
        $settings = is_array($action['settings'] ?? null) ? $action['settings'] : [];
        $success_action = isset($settings['success_action']) && is_string($settings['success_action'])
            ? $settings['success_action']
            : 'message';
        if ($success_action !== 'message') {
            return [
                'path' => 'meta.formidable_on_submit',
                'reason' => sprintf(
                    'Formidable on-submit action uses success_action=%s — the redirect target is echoed to meta.formidable_on_submit but canonical has no first-class redirect block; the target plugin must be configured manually.',
                    $success_action,
                ),
                'severity' => 'informational',
            ];
        }
    }
    /** @var mixed $legacy */
    $legacy = $form_options['success_action'] ?? null;
    if (is_string($legacy) && $legacy !== '' && $legacy !== 'message') {
        return [
            'path' => 'meta.formidable_form_options.success_action',
            'reason' => sprintf(
                'Formidable form options carry legacy success_action=%s — the redirect target is echoed to meta.formidable_form_options but canonical has no first-class redirect block; the target plugin must be configured manually.',
                $legacy,
            ),
            'severity' => 'informational',
        ];
    }
    return null;
}

/**
 * Builds the canonical `meta{}` block. Carries source plugin and version;
 * echoes the entire form options blob and on_submit action settings for
 * lossless round-trip on a formidable-to-formidable migration.
 *
 * @param array<array-key, mixed>       $form_options
 * @param list<array<array-key, mixed>> $on_submit_actions
 *
 * @return array<string, mixed>
 */
function formidable_canonical_build_meta(string $version, array $form_options, array $on_submit_actions): array
{
    return [
        'source_plugin' => 'formidable',
        'source_version' => $version,
        'formidable_form_options' => $form_options,
        'formidable_on_submit' => $on_submit_actions,
    ];
}

/**
 * Builds the warning for a Formidable field type the canonical map does
 * not cover. Safety net for types added by future Formidable releases or
 * add-ons.
 *
 * @return array<string, string>
 */
function formidable_canonical_unmapped_warning(string $type, int $field_index): array
{
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => sprintf(
            'Formidable [%s] field type is not yet mapped to the canonical vocabulary — dropped from canonical fields[]. Report this to the integration maintainer; the type may have been added by a recent Formidable release or add-on.',
            $type,
        ),
        'severity' => 'informational',
    ];
}

/**
 * Boolean coercion matching the cf7 / wpforms / gravityforms / fluent
 * canonical readers. Formidable stores booleans as 1/0 strings on the
 * row plus the occasional native bool inside field_options.
 */
function formidable_canonical_truthy(mixed $value): bool
{
    if (in_array($value, [null, false, 0, 0.0, '', '0', 'false', 'False', 'FALSE', 'no', 'No'], strict: true)) {
        return false;
    }
    return true;
}

/**
 * Truncates a string at a byte cap, mirroring the cf7 / wpforms /
 * gravityforms / fluent canonical readers. Used on mail / notification
 * bodies so the canonical response stays under control even when the
 * source form ships a multi-kilobyte body.
 *
 * @return array{value: string, truncated: bool, original_bytes: int}
 */
function formidable_canonical_truncate(string $value, int $max_bytes): array
{
    $original = strlen($value);
    if ($max_bytes <= 0 || $original <= $max_bytes) {
        return ['value' => $value, 'truncated' => false, 'original_bytes' => $original];
    }
    $cut = mb_strcut($value, start: 0, length: $max_bytes, encoding: 'UTF-8');
    return [
        'value' => $cut . "\n…[truncated]",
        'truncated' => true,
        'original_bytes' => $original,
    ];
}
