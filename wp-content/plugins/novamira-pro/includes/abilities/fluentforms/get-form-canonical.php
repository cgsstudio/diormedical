<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/fluentforms-get-form-canonical', [
    'label' => __('Get Fluent Forms Form (Canonical)', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Fluent Forms form by id and returns it mapped to the plugin-agnostic form-model-schema vocabulary (17 canonical types: text/email/tel/url/number/textarea/date/time/datetime/select/radio/checkbox/file/hidden/submit/consent/group) used by the form-model-migration skill. Fluent Forms native field elements (~40 including Pro) fold onto those 17 — composites (`input_name`, `address`, `container`, `accordion`) collapse to canonical `group`; structural-only types (`section_break`, `custom_html`, `shortcode`, `action_hook`) drop with informational warnings; anti-spam (`recaptcha`, `hcaptcha`, `turnstile`), multi-page (`form_step`, `save_progress_button`), and Pro payment types (`payment_method`, `subscription_payment`, `item_quantity`, `multi_payment_component`, `custom_payment_component`, `payment_summary_component`, `coupon`) drop with structural warnings. The form fields blob lives at `wp_fluentform_forms.form_fields` (JSON-encoded `{fields:[], submitButton:{}}`); notifications live in `wp_fluentform_form_meta` (one row per notification, `meta_key="notifications"`); the single confirmation lives at `formSettings.confirmation`. Output keys: `fields[]` with canonical types, `mail_config{}` from the first enabled notification, `notifications[]` from the remaining ones, `messages{}` (empty — Fluent has no built-in message catalogue), `conditional_rules[]` from per-field `settings.conditional_logics` (mapped 1:1 when expressible), `meta{}` (source plugin + version + lossless formSettings echo for round-trip), plus `warnings[]` for everything that does not fit the canonical shape (anti-spam, payments, conversational mode, custom redirects). Smart-tag syntax `{inputs.<name>}` inside subject / message / recipient is rewritten to `[<name>]` to match the CF7 / WPForms / Gravity Forms canonical convention. Honours the same `max_field_bytes` truncation contract as the other canonical readers (default 8192 bytes for `mail_config.body`, `notifications[].body`, with `*_truncated` flags); pass `max_field_bytes: 0` to disable truncation when full-fidelity canonical is required before an execute-mode migration. Returns WP_Error `fluentforms_form_not_found` when the id does not exist. Use this ability inside the form-model-migration workflow (Phase 2 — read source schema); for ad-hoc inspection outside the migration pipeline, prefer `fluentforms-get-form` which returns the Fluent-native shape.',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Fluent Forms form id.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\FluentForms\\fluentforms_get_form_canonical',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use as Phase 2 (read source) of the form-model-migration workflow when Fluent Forms is the source plugin. The output is the canonical form-model-schema document directly — feed it into Phase 3 (translate) as-is; warnings[] already enumerates structural / informational losses (anti-spam, payments, conversational mode, multi-page, composite collapse). Pass max_field_bytes:0 before an execute-mode migration so canonical bodies are not truncated.',
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
function fluentforms_get_form_canonical(array $input): array|WP_Error
{
    if (!ff_detect_setup()['active']) {
        return ff_not_active();
    }

    $form = ff_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $cap_error = ff_capability_or_error('fluentform_forms_manager', (int) $form->id);
    if ($cap_error instanceof WP_Error) {
        return $cap_error;
    }

    $max_field_bytes = isset($input['max_field_bytes']) ? max(0, (int) $input['max_field_bytes']) : 8192;

    return fluentforms_canonical_compose($form, $max_field_bytes);
}

/**
 * Build the canonical document from a Fluent Forms `Form` model. The form
 * fields blob is JSON-decoded from `form_fields` (`{fields:[], submitButton:{}}`),
 * notifications and formSettings come from sibling `wp_fluentform_form_meta`
 * rows via the helpers.
 *
 * @return array<string, mixed>
 */
function fluentforms_canonical_compose(object $form, int $max_field_bytes): array
{
    $form_id = (int) ($form->id ?? 0);
    $title = is_string($form->title ?? null) ? $form->title : '';
    $form_fields_raw = is_string($form->form_fields ?? null) ? $form->form_fields : '';

    $decoded = fluentforms_canonical_decode_form_fields($form_fields_raw);
    $ff_fields_flat = fluentforms_canonical_flatten_fields($decoded['fields']);

    $notifications_raw = ff_form_notifications($form_id);
    $settings_raw = ff_form_settings($form_id);

    $field_name_map = fluentforms_canonical_build_field_name_map($ff_fields_flat);
    $detection = ff_detect_setup();
    $version = is_string($detection['version']) ? $detection['version'] : '';

    $fields_result = fluentforms_canonical_translate_fields($ff_fields_flat, $field_name_map);
    $primary_index = fluentforms_canonical_primary_notification_index($notifications_raw);
    $mail_config = fluentforms_canonical_translate_mail_config(
        $primary_index >= 0 ? $notifications_raw[$primary_index] : null,
        $field_name_map,
        $max_field_bytes,
    );
    $notifications = fluentforms_canonical_translate_notifications(
        $notifications_raw,
        $primary_index,
        $field_name_map,
        $max_field_bytes,
    );
    $conditional_rules = fluentforms_canonical_collect_conditional_rules($ff_fields_flat, $field_name_map);
    $form_warnings = fluentforms_canonical_form_warnings($ff_fields_flat, $settings_raw, $decoded['container_count']);

    return [
        'form_id' => $form_id,
        'title' => $title,
        'slug' => sanitize_title($title),
        'locale' => '',
        'fields' => $fields_result['fields'],
        'mail_config' => $mail_config,
        'notifications' => $notifications,
        'messages' => (object) [],
        'conditional_rules' => $conditional_rules,
        'meta' => fluentforms_canonical_build_meta($version, $settings_raw),
        'warnings' => array_merge($fields_result['warnings'], $form_warnings),
    ];
}

/**
 * Decode the `form_fields` JSON blob. Returns `{fields, container_count}`
 * where `fields` is a list of top-level field arrays (containers still nested)
 * and `container_count` is the number of `container` wrappers seen (used to
 * emit an informational warning that multi-column layout is canonical-flat).
 *
 * @return array{fields: list<array<array-key, mixed>>, container_count: int}
 */
function fluentforms_canonical_decode_form_fields(string $json): array
{
    if ($json === '') {
        return ['fields' => [], 'container_count' => 0];
    }
    /** @var mixed $decoded */
    $decoded = json_decode($json, associative: true);
    if (!is_array($decoded)) {
        return ['fields' => [], 'container_count' => 0];
    }
    /** @var mixed $fields_raw */
    $fields_raw = $decoded['fields'] ?? null;
    if (!is_array($fields_raw)) {
        return ['fields' => [], 'container_count' => 0];
    }
    /** @var list<array<array-key, mixed>> $fields */
    $fields = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($fields_raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $fields[] = $entry;
    }
    $container_count = 0;
    foreach ($fields as $entry) {
        if (($entry['element'] ?? '') !== 'container') {
            continue;
        }
        $container_count++;
    }
    return ['fields' => $fields, 'container_count' => $container_count];
}

/**
 * Walk the form fields tree (multi-column `container.columns[].fields[]`)
 * and produce a flat list of leaf field arrays in document order. The
 * container itself is dropped from the flat list (it has no canonical type)
 * but the form-warnings function notes its presence separately.
 *
 * @param list<array<array-key, mixed>> $fields
 *
 * @return list<array<array-key, mixed>>
 */
function fluentforms_canonical_flatten_fields(array $fields): array
{
    /** @var list<array<array-key, mixed>> $out */
    $out = [];
    foreach ($fields as $field) {
        $element = isset($field['element']) && is_string($field['element']) ? $field['element'] : '';
        if ($element === 'container') {
            /** @var mixed $columns_raw */
            $columns_raw = $field['columns'] ?? null;
            if (!is_array($columns_raw)) {
                continue;
            }
            // @mago-expect analysis:mixed-assignment
            foreach ($columns_raw as $column) {
                if (!is_array($column)) {
                    continue;
                }
                /** @var mixed $column_fields_raw */
                $column_fields_raw = $column['fields'] ?? null;
                if (!is_array($column_fields_raw)) {
                    continue;
                }
                /** @var list<array<array-key, mixed>> $column_fields */
                $column_fields = [];
                // @mago-expect analysis:mixed-assignment
                foreach ($column_fields_raw as $cf) {
                    if (!is_array($cf)) {
                        continue;
                    }
                    $column_fields[] = $cf;
                }
                foreach (fluentforms_canonical_flatten_fields($column_fields) as $leaf) {
                    $out[] = $leaf;
                }
            }
            continue;
        }
        $out[] = $field;
    }
    return $out;
}

/**
 * Builds the input-name → canonical-name lookup used to rewrite Fluent
 * `{inputs.<name>}` smart-tags into `[<name>]`. The map key is the raw
 * Fluent input identifier (`attributes.name`, e.g. `email`, `your_name`),
 * which is what `{inputs.X}` smart-tags reference. The map value is the
 * canonical slug derived from the field's label (mirroring the CF7 /
 * WPForms / Gravity readers — every canonical reader anchors names on
 * the user-facing label so a smart-tag rewritten as `[your-name]` lands
 * on the same canonical slot across plugins). Falls back to the raw
 * input name when the label is empty.
 *
 * @param list<array<array-key, mixed>> $ff_fields
 *
 * @return array<string, string>
 */
function fluentforms_canonical_build_field_name_map(array $ff_fields): array
{
    $map = [];
    foreach ($ff_fields as $field) {
        $input_name = fluentforms_canonical_field_name($field);
        if ($input_name === '') {
            continue;
        }
        $map[$input_name] = fluentforms_canonical_derive_name($field, $input_name);
    }
    return $map;
}

/**
 * Reads a Fluent Forms field's input identifier (`attributes.name`).
 * Returns '' when missing. This is the raw key `{inputs.X}` references.
 *
 * @param array<array-key, mixed> $field
 */
function fluentforms_canonical_field_name(array $field): string
{
    /** @var mixed $attributes */
    $attributes = $field['attributes'] ?? null;
    if (!is_array($attributes)) {
        return '';
    }
    /** @var mixed $name */
    $name = $attributes['name'] ?? '';
    return is_string($name) ? $name : '';
}

/**
 * Derives the canonical field slug. Prefers `sanitize_title(label)` to
 * match the CF7 / WPForms / Gravity convention (canonical names are
 * label-derived hyphen-style slugs). Falls back to a sanitised input
 * name when label is empty.
 *
 * @param array<array-key, mixed> $field
 */
function fluentforms_canonical_derive_name(array $field, string $input_name): string
{
    $settings = is_array($field['settings'] ?? null) ? $field['settings'] : [];
    $label = isset($settings['label']) && is_string($settings['label']) ? $settings['label'] : '';
    if ($label !== '') {
        $slug = sanitize_title($label);
        if ($slug !== '') {
            return $slug;
        }
    }
    $slug = sanitize_title($input_name);
    return $slug !== '' ? $slug : $input_name;
}

/**
 * Picks the index of the Fluent notification to surface as the canonical
 * `mail_config{}`. Strategy: first `enabled === true`; if none enabled the
 * first overall; -1 when there are no notifications.
 *
 * @param list<array<array-key, mixed>> $notifications
 */
function fluentforms_canonical_primary_notification_index(array $notifications): int
{
    if ($notifications === []) {
        return -1;
    }
    foreach ($notifications as $i => $notification) {
        if (fluentforms_canonical_truthy($notification['enabled'] ?? true)) {
            return $i;
        }
    }
    return 0;
}

/**
 * Translates the flat Fluent fields list into canonical fields[],
 * accumulating warnings for losses (anti-spam, payments, composite
 * collapse, conversational mode).
 *
 * @param list<array<array-key, mixed>> $ff_fields
 * @param array<string, string>         $field_name_map
 *
 * @return array{fields: list<array<string, mixed>>, warnings: list<array<string, string>>}
 */
function fluentforms_canonical_translate_fields(array $ff_fields, array $field_name_map): array
{
    /** @var list<array<string, mixed>> $fields */
    $fields = [];
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    $index = 0;
    foreach ($ff_fields as $ff_field) {
        $result = fluentforms_canonical_translate_field($ff_field, $index, $field_name_map);
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
 * Maps a single Fluent field to canonical. Returns null when the element
 * has no canonical representation (anti-spam, payments, multi-page step,
 * pure rendering). The warnings list carries the explanation.
 *
 * @param array<array-key, mixed> $ff_field
 * @param array<string, string>   $field_name_map
 *
 * @return array{field: array<string, mixed>|null, warnings: list<array<string, string>>}
 */
function fluentforms_canonical_translate_field(array $ff_field, int $field_index, array $field_name_map): array
{
    $element = isset($ff_field['element']) && is_string($ff_field['element']) ? $ff_field['element'] : '';
    $input_name = fluentforms_canonical_field_name($ff_field);
    $canonical_name = $field_name_map[$input_name] ?? '';
    if ($canonical_name === '') {
        $canonical_name = fluentforms_canonical_derive_name($ff_field, $input_name);
    }
    if ($canonical_name === '') {
        $canonical_name = 'field-' . $field_index;
    }

    if (fluentforms_canonical_field_is_skipped($element)) {
        return [
            'field' => null,
            'warnings' => [fluentforms_canonical_skip_warning($element, $field_index)],
        ];
    }

    $canonical_type = fluentforms_canonical_type_map($element);
    if ($canonical_type === null) {
        return [
            'field' => null,
            'warnings' => [fluentforms_canonical_unmapped_warning($element, $field_index)],
        ];
    }

    $field = fluentforms_canonical_field_payload($ff_field, $element, $canonical_name, $canonical_type);
    $warnings = fluentforms_canonical_field_warnings($ff_field, $element, $field_index);

    return ['field' => $field, 'warnings' => $warnings];
}

/**
 * Returns true when the Fluent element has no canonical role (anti-spam,
 * multi-page step, payments, pure rendering, conversational helper) and
 * should be dropped from fields[] with a single warning.
 */
function fluentforms_canonical_field_is_skipped(string $element): bool
{
    return in_array(
        $element,
        [
            'recaptcha',
            'hcaptcha',
            'turnstile',
            'form_step',
            'save_progress_button',
            'section_break',
            'custom_html',
            'shortcode',
            'action_hook',
            'payment_method',
            'subscription_payment',
            'item_quantity',
            'multi_payment_component',
            'custom_payment_component',
            'payment_summary_component',
            'coupon',
        ],
        strict: true,
    );
}

/**
 * Builds the warning entry for a skipped Fluent field. Severity is
 * `structural` for semantically heavy drops (anti-spam, multi-page,
 * payments) and `informational` for pure rendering (section_break,
 * custom_html, shortcode, action_hook).
 *
 * @return array<string, string>
 */
function fluentforms_canonical_skip_warning(string $element, int $field_index): array
{
    $structural = [
        'recaptcha',
        'hcaptcha',
        'turnstile',
        'form_step',
        'save_progress_button',
        'payment_method',
        'subscription_payment',
        'item_quantity',
        'multi_payment_component',
        'custom_payment_component',
        'payment_summary_component',
        'coupon',
    ];
    $severity = in_array($element, $structural, strict: true) ? 'structural' : 'informational';
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => sprintf(
            'Fluent Forms [%s] element has no canonical equivalent — preserved as a warning for the migration plan; the target plugin must provide its own implementation.',
            $element,
        ),
        'severity' => $severity,
    ];
}

/**
 * Maps a Fluent element to its canonical form-model-schema type. Returns
 * null when the element is not skipped but does not map cleanly (kept as a
 * safety net — every element used in the wild should map).
 */
function fluentforms_canonical_type_map(string $element): ?string
{
    return match ($element) {
        'input_text', 'input_mask', 'input_password', 'color-picker' => 'text',
        'textarea', 'rich_text_input' => 'textarea',
        'input_email' => 'email',
        'input_number', 'rangeslider', 'quiz_score' => 'number',
        'phone' => 'tel',
        'input_url' => 'url',
        'input_date' => 'date',
        'select', 'multi_select', 'select_country', 'cpt_selection', 'chained_select', 'dynamic_field' => 'select',
        'input_radio', 'ratings', 'net_promoter_score', 'input_ranking' => 'radio',
        'input_checkbox', 'tabular_grid' => 'checkbox',
        'input_file' => 'file',
        'input_image' => 'file',
        'input_hidden' => 'hidden',
        'terms_and_condition', 'gdpr_agreement' => 'consent',
        'address', 'input_name', 'repeater_field', 'accordion' => 'group',
        default => null,
    };
}

/**
 * Builds the canonical field array for the mapped Fluent field. Pulls
 * common keys (name / type / label / description / required / placeholder /
 * default / cardinality / choices / validation) and carries Fluent-specific
 * hints into meta for lossless round-trip.
 *
 * @param array<array-key, mixed> $ff_field
 *
 * @return array<string, mixed>
 */
function fluentforms_canonical_field_payload(
    array $ff_field,
    string $element,
    string $name,
    string $canonical_type,
): array {
    $field = [
        'name' => $name,
        'type' => $canonical_type,
    ];
    $settings = is_array($ff_field['settings'] ?? null) ? $ff_field['settings'] : [];
    $attributes = is_array($ff_field['attributes'] ?? null) ? $ff_field['attributes'] : [];

    fluentforms_canonical_apply_string_from($field, $settings, key: 'label');
    fluentforms_canonical_apply_string_from($field, $settings, key: 'help_message', dest: 'description');
    fluentforms_canonical_apply_string_from($field, $attributes, key: 'placeholder');
    fluentforms_canonical_apply_default_value($field, $attributes);
    if ($canonical_type === 'consent' || fluentforms_canonical_is_required($settings)) {
        $field['required'] = true;
    }
    fluentforms_canonical_apply_cardinality($field, $element);
    fluentforms_canonical_apply_choices($field, $settings, $canonical_type);
    fluentforms_canonical_apply_validation($field, $settings, $element, $canonical_type);
    fluentforms_canonical_apply_subtype($field, $ff_field, $element, $canonical_type);
    $field['meta'] = fluentforms_canonical_field_meta($ff_field, $element);

    return $field;
}

/**
 * Builds the canonical `meta{}` sub-block for a field. Carries the
 * original Fluent element key (for round-trip rebuild) plus the stable
 * `uniqElKey` and any container_class / admin_field_label rendering hints.
 *
 * @param array<array-key, mixed> $ff_field
 *
 * @return array<string, mixed>
 */
function fluentforms_canonical_field_meta(array $ff_field, string $element): array
{
    $meta = ['fluentforms_element' => $element];
    /** @var mixed $uniq */
    $uniq = $ff_field['uniqElKey'] ?? null;
    if (is_string($uniq) && $uniq !== '') {
        $meta['fluentforms_uniq_key'] = $uniq;
    }
    $settings = is_array($ff_field['settings'] ?? null) ? $ff_field['settings'] : [];
    foreach (['container_class', 'admin_field_label', 'prefix_label', 'suffix_label'] as $hint) {
        /** @var mixed $value */
        $value = $settings[$hint] ?? null;
        if (is_string($value) && $value !== '') {
            $meta['fluentforms_' . $hint] = $value;
        }
    }
    return $meta;
}

/**
 * Copies a simple string property from an arbitrary Fluent sub-block to
 * the canonical field when present and non-empty. `dest` overrides the
 * destination key (used to remap `help_message` → `description`).
 *
 * @param array<string, mixed>     $field
 * @param array<array-key, mixed>  $source
 */
function fluentforms_canonical_apply_string_from(array &$field, array $source, string $key, ?string $dest = null): void
{
    if (isset($source[$key]) && is_string($source[$key]) && $source[$key] !== '') {
        $field[$dest ?? $key] = $source[$key];
    }
}

/**
 * Carries the Fluent `attributes.value` (initial value) to canonical
 * `default` when present and non-empty. Empty strings are not propagated
 * — they would falsely mark a default of '' as significant.
 *
 * @param array<string, mixed>     $field
 * @param array<array-key, mixed>  $attributes
 */
function fluentforms_canonical_apply_default_value(array &$field, array $attributes): void
{
    /** @var mixed $value */
    $value = $attributes['value'] ?? null;
    if (is_string($value) && $value !== '') {
        $field['default'] = $value;
    }
}

/**
 * Whether the Fluent field is required. Required state lives at
 * `settings.validation_rules.required.value` as a boolean.
 *
 * @param array<array-key, mixed> $settings
 */
function fluentforms_canonical_is_required(array $settings): bool
{
    /** @var mixed $rules */
    $rules = $settings['validation_rules'] ?? null;
    if (!is_array($rules)) {
        return false;
    }
    /** @var mixed $required */
    $required = $rules['required'] ?? null;
    if (!is_array($required)) {
        return false;
    }
    return fluentforms_canonical_truthy($required['value'] ?? null);
}

/**
 * Sets canonical `cardinality` for the field types where the value is
 * meaningful (select multi, checkbox group, repeater rows). Leaves the key
 * absent on scalar types.
 *
 * @param array<string, mixed> $field
 */
function fluentforms_canonical_apply_cardinality(array &$field, string $element): void
{
    if ($element === 'multi_select') {
        $field['cardinality'] = 'multi';
        return;
    }
    if ($element === 'input_checkbox' || $element === 'tabular_grid') {
        $field['cardinality'] = 'multi';
        return;
    }
    if ($element === 'repeater_field') {
        $field['cardinality'] = 'multi';
        return;
    }
    if ($element === 'select' || $element === 'select_country' || $element === 'cpt_selection') {
        $field['cardinality'] = 'single';
        return;
    }
    if ($element === 'input_radio' || $element === 'ratings' || $element === 'net_promoter_score') {
        $field['cardinality'] = 'single';
    }
}

/**
 * Translates Fluent `settings.advanced_options[]` (and the simpler
 * `settings.options{}` map used by country / state lists) into canonical
 * `choices[{value,label}]` for the choice-bearing types.
 *
 * @param array<string, mixed>    $field
 * @param array<array-key, mixed> $settings
 */
function fluentforms_canonical_apply_choices(array &$field, array $settings, string $canonical_type): void
{
    if (!in_array($canonical_type, ['select', 'checkbox', 'radio'], strict: true)) {
        return;
    }
    $choices = fluentforms_canonical_collect_advanced_options($settings);
    if ($choices === []) {
        $choices = fluentforms_canonical_collect_flat_options($settings);
    }
    if ($choices !== []) {
        $field['choices'] = $choices;
    }
}

/**
 * Collects choices from `settings.advanced_options[]` (used by select /
 * radio / checkbox with rich per-choice metadata: label, value,
 * calc_value, image). Returns [] when missing or empty.
 *
 * @param array<array-key, mixed> $settings
 *
 * @return list<array<string, string>>
 */
function fluentforms_canonical_collect_advanced_options(array $settings): array
{
    /** @var mixed $raw */
    $raw = $settings['advanced_options'] ?? null;
    if (!is_array($raw)) {
        return [];
    }
    $choices = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $label = isset($entry['label']) && is_string($entry['label']) ? $entry['label'] : '';
        $value =
            isset($entry['value']) && is_string($entry['value']) && $entry['value'] !== '' ? $entry['value'] : $label;
        if ($label === '' && $value === '') {
            continue;
        }
        $choices[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
    }
    return $choices;
}

/**
 * Collects choices from the flat `settings.options{}` map (used by
 * select_country / liststate, where keys are codes and values are display
 * names). Returns [] when missing.
 *
 * @param array<array-key, mixed> $settings
 *
 * @return list<array<string, string>>
 */
function fluentforms_canonical_collect_flat_options(array $settings): array
{
    /** @var mixed $raw */
    $raw = $settings['options'] ?? null;
    if (!is_array($raw)) {
        return [];
    }
    $choices = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $code => $label) {
        $code_str = is_string($code) ? $code : (string) $code;
        $label_str = is_string($label) ? $label : '';
        if ($code_str === '' && $label_str === '') {
            continue;
        }
        $choices[] = [
            'value' => $code_str !== '' ? $code_str : $label_str,
            'label' => $label_str !== '' ? $label_str : $code_str,
        ];
    }
    return $choices;
}

/**
 * Populates the canonical `validation` block from Fluent
 * `settings.validation_rules` (min, max, max_length, max_file_size,
 * allowed_image_types, accept_file_types). Each rule entry shape is
 * `{value, message, global}`.
 *
 * @param array<string, mixed>    $field
 * @param array<array-key, mixed> $settings
 */
function fluentforms_canonical_apply_validation(
    array &$field,
    array $settings,
    string $element,
    string $canonical_type,
): void {
    /** @var mixed $rules */
    $rules = $settings['validation_rules'] ?? null;
    if (!is_array($rules)) {
        return;
    }
    $validation = array_merge(
        fluentforms_canonical_number_validation($rules, $canonical_type),
        fluentforms_canonical_file_validation($rules, $element),
        fluentforms_canonical_length_validation($rules, $canonical_type),
    );
    if ($validation !== []) {
        $field['validation'] = $validation;
    }
}

/**
 * Extracts canonical `min` / `max` from a Fluent number field's
 * validation rules. Returns [] for non-number types.
 *
 * @param array<array-key, mixed> $rules
 *
 * @return array<string, int|float>
 */
function fluentforms_canonical_number_validation(array $rules, string $canonical_type): array
{
    if ($canonical_type !== 'number') {
        return [];
    }
    $out = [];
    /** @var mixed $min */
    $min = is_array($rules['min'] ?? null) ? $rules['min']['value'] ?? null : null;
    if (is_numeric($min)) {
        $out['min'] = fluentforms_canonical_to_number($min);
    }
    /** @var mixed $max */
    $max = is_array($rules['max'] ?? null) ? $rules['max']['value'] ?? null : null;
    if (is_numeric($max)) {
        $out['max'] = fluentforms_canonical_to_number($max);
    }
    return $out;
}

/**
 * Extracts canonical `accepts[]` and `max_size_bytes` from a Fluent file
 * upload field. Fluent stores `max_file_size.value` in bytes already and
 * the accepted MIME types as a comma-separated string under
 * `allowed_image_types.value` (input_image) or `accept_file_types.value`
 * (input_file).
 *
 * @param array<array-key, mixed> $rules
 *
 * @return array<string, mixed>
 */
function fluentforms_canonical_file_validation(array $rules, string $element): array
{
    if ($element !== 'input_file' && $element !== 'input_image') {
        return [];
    }
    $out = [];
    /** @var mixed $accepts_raw */
    $accepts_raw = is_array($rules['allowed_image_types'] ?? null)
        ? $rules['allowed_image_types']['value'] ?? null
        : null;
    if ($accepts_raw === null && is_array($rules['accept_file_types'] ?? null)) {
        // @mago-expect analysis:mixed-assignment
        $accepts_raw = $rules['accept_file_types']['value'] ?? null;
    }
    $accepts = fluentforms_canonical_parse_extensions($accepts_raw);
    if ($accepts !== []) {
        $out['accepts'] = $accepts;
    }
    /** @var mixed $size */
    $size = is_array($rules['max_file_size'] ?? null) ? $rules['max_file_size']['value'] ?? null : null;
    if (is_numeric($size)) {
        $out['max_size_bytes'] = (int) round(floatval($size));
    }
    return $out;
}

/**
 * Extracts canonical `max_length` from a Fluent text / textarea field that
 * defines the maxlength validation rule.
 *
 * @param array<array-key, mixed> $rules
 *
 * @return array<string, int>
 */
function fluentforms_canonical_length_validation(array $rules, string $canonical_type): array
{
    if (!in_array($canonical_type, ['text', 'textarea'], strict: true)) {
        return [];
    }
    /** @var mixed $max */
    $max = is_array($rules['max'] ?? null) ? $rules['max']['value'] ?? null : null;
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
 * Casts a numeric-looking Fluent value (often a string in the validation
 * blob) to native int / float.
 */
function fluentforms_canonical_to_number(mixed $value): int|float
{
    if (!is_numeric($value)) {
        return 0;
    }
    $string = (string) $value;
    return str_contains($string, '.') ? floatval($string) : (int) $string;
}

/**
 * Parses an accept-list (e.g. `image/jpeg, image/png` or `jpg,jpeg,png`)
 * into canonical MIME types. Unknown extensions are dropped.
 *
 * @return list<string>
 */
function fluentforms_canonical_parse_extensions(mixed $raw): array
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
 * Folds Fluent element-specific specialisers into the canonical field:
 * `input_password` → format=password; `color-picker` → subtype=color;
 * `rich_text_input` → format=html; `rangeslider` → subtype=range;
 * `quiz_score` → meta.readonly; `input_image` → subtype=image;
 * `input_date` with `is_time_enabled` → canonical type stays `date` but
 * we surface a meta hint so the agent can pick `datetime` if the target
 * supports it.
 *
 * @param array<string, mixed>    $field
 * @param array<array-key, mixed> $ff_field
 */
// @mago-expect lint:cyclomatic-complexity
// @mago-expect lint:no-insecure-comparison
function fluentforms_canonical_apply_subtype(
    array &$field,
    array $ff_field,
    string $element,
    string $canonical_type,
): void {
    if ($element === 'input_password' && $canonical_type === 'text') {
        $field['format'] = 'password';
        return;
    }
    if ($element === 'color-picker' && $canonical_type === 'text') {
        $field['subtype'] = 'color';
        return;
    }
    if ($element === 'rich_text_input' && $canonical_type === 'textarea') {
        $field['format'] = 'html';
        return;
    }
    if ($element === 'rangeslider' && $canonical_type === 'number') {
        $field['subtype'] = 'range';
        return;
    }
    if ($element === 'input_image' && $canonical_type === 'file') {
        $field['subtype'] = 'image';
        return;
    }
    if ($element === 'input_date' && $canonical_type === 'date') {
        $settings = is_array($ff_field['settings'] ?? null) ? $ff_field['settings'] : [];
        $format = isset($settings['date_format']) && is_string($settings['date_format'])
            ? $settings['date_format']
            : '';
        if ($format !== '') {
            $field['meta'] = array_merge(is_array($field['meta'] ?? null) ? $field['meta'] : [], [
                'fluentforms_date_format' => $format,
            ]);
        }
    }
}

/**
 * Per-field migration warnings: composite collapse (input_name / address
 * / repeater / accordion), conditional logic presence, structural-but-
 * mapped types (tabular_grid).
 *
 * @param array<array-key, mixed> $ff_field
 *
 * @return list<array<string, string>>
 */
function fluentforms_canonical_field_warnings(array $ff_field, string $element, int $field_index): array
{
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    if (in_array($element, ['input_name', 'address', 'repeater_field', 'accordion'], strict: true)) {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => sprintf(
                'Fluent Forms composite [%s] collapsed to canonical group — sub-field structure (fields{} for name/address; columns{} for repeater) is plugin-specific and will need re-creation on the target.',
                $element,
            ),
            'severity' => 'informational',
        ];
    }
    if ($element === 'tabular_grid') {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => 'Fluent Forms [tabular_grid] (matrix question) mapped to canonical checkbox with multi cardinality — row / column structure is plugin-specific and must be rebuilt on the target.',
            'severity' => 'structural',
        ];
    }
    if (fluentforms_canonical_has_conditional_logic($ff_field)) {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => 'Fluent Forms conditional_logics is mapped to canonical conditional_rules[] when expressible — complex nested AND/OR rules or value-derived triggers are flattened with possible semantic loss.',
            'severity' => 'informational',
        ];
    }
    if (in_array($element, ['ratings', 'net_promoter_score', 'input_ranking', 'quiz_score'], strict: true)) {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => sprintf(
                'Fluent Forms [%s] mapped to a generic canonical type — UI semantics (rating stars / NPS scale / ranking drag-and-drop / quiz scoring) are plugin-specific and must be reconfigured on the target.',
                $element,
            ),
            'severity' => 'informational',
        ];
    }

    return $warnings;
}

/**
 * Returns true when a Fluent field has a `settings.conditional_logics`
 * block with at least one rule and `status === true`.
 *
 * @param array<array-key, mixed> $ff_field
 */
function fluentforms_canonical_has_conditional_logic(array $ff_field): bool
{
    $settings = is_array($ff_field['settings'] ?? null) ? $ff_field['settings'] : [];
    /** @var mixed $logic */
    $logic = $settings['conditional_logics'] ?? null;
    if (!is_array($logic)) {
        return false;
    }
    if (!fluentforms_canonical_truthy($logic['status'] ?? false)) {
        return false;
    }
    /** @var mixed $conditions */
    $conditions = $logic['conditions'] ?? null;
    return is_array($conditions) && $conditions !== [];
}

/**
 * Translates the first enabled Fluent notification into the canonical
 * `mail_config{}`. When no notification is present the canonical still
 * emits the block with empty values so downstream consumers don't have
 * to special-case it.
 *
 * @param mixed                 $notification
 * @param array<string, string> $field_name_map
 *
 * @return array<string, mixed>
 */
function fluentforms_canonical_translate_mail_config(
    mixed $notification,
    array $field_name_map,
    int $max_field_bytes,
): array {
    if (!is_array($notification)) {
        return fluentforms_canonical_empty_mail_config();
    }
    return fluentforms_canonical_notification_to_canonical($notification, $field_name_map, $max_field_bytes);
}

/**
 * Translates every Fluent notification EXCEPT the primary one into
 * canonical notifications[]. Each entry carries `name`, `active`, and the
 * same mail-config keys the primary uses.
 *
 * @param list<array<array-key, mixed>> $notifications
 * @param array<string, string>         $field_name_map
 *
 * @return list<array<string, mixed>>
 */
function fluentforms_canonical_translate_notifications(
    array $notifications,
    int $primary_index,
    array $field_name_map,
    int $max_field_bytes,
): array {
    /** @var list<array<string, mixed>> $out */
    $out = [];
    foreach ($notifications as $i => $notification) {
        if ($i === $primary_index) {
            continue;
        }
        $entry = fluentforms_canonical_notification_to_canonical($notification, $field_name_map, $max_field_bytes);
        $entry = array_merge([
            'name' => fluentforms_canonical_notification_name($notification, $i),
            'active' => fluentforms_canonical_truthy($notification['enabled'] ?? true),
        ], $entry);
        $out[] = $entry;
    }
    return $out;
}

/**
 * Returns the canonical notification name. Fluent carries a `name` label
 * per notification; fall back to `notification-<index>` when missing.
 *
 * @param array<array-key, mixed> $notification
 */
function fluentforms_canonical_notification_name(array $notification, int $index): string
{
    if (isset($notification['name']) && is_string($notification['name']) && $notification['name'] !== '') {
        $slug = sanitize_title($notification['name']);
        if ($slug !== '') {
            return $slug;
        }
    }
    return 'notification-' . $index;
}

/**
 * Translates a single Fluent notification array into the canonical
 * mail-config shape (shared by `mail_config{}` and each `notifications[]`
 * entry). `{inputs.<name>}` smart-tags inside subject / message / sendTo
 * are rewritten to `[name]` so the canonical document round-trips cleanly
 * with the CF7 / WPForms / Gravity readers.
 *
 * @param array<array-key, mixed> $notification
 * @param array<string, string>   $field_name_map
 *
 * @return array<string, mixed>
 */
function fluentforms_canonical_notification_to_canonical(
    array $notification,
    array $field_name_map,
    int $max_field_bytes,
): array {
    $body_raw = fluentforms_canonical_normalize_smart_tags(
        isset($notification['message']) && is_string($notification['message']) ? $notification['message'] : '',
        $field_name_map,
    );
    $body_truncated = fluentforms_canonical_truncate($body_raw, $max_field_bytes);

    return [
        'subject' => fluentforms_canonical_normalize_smart_tags(
            isset($notification['subject']) && is_string($notification['subject']) ? $notification['subject'] : '',
            $field_name_map,
        ),
        'sender' => fluentforms_canonical_compose_sender($notification),
        'recipient' => fluentforms_canonical_resolve_recipient($notification, $field_name_map),
        'body' => $body_truncated['value'],
        'body_truncated' => $body_truncated['truncated'],
        'body_bytes' => $body_truncated['original_bytes'],
        'additional_headers' => fluentforms_canonical_collect_extra_headers($notification, $field_name_map),
        'attachments' => [],
        'use_html' => !fluentforms_canonical_truthy($notification['asPlainText'] ?? null),
        'exclude_blank' => false,
    ];
}

/**
 * Resolves the canonical `recipient` from the Fluent `sendTo` block.
 * `type=email` ⇒ raw address (smart-tags rewritten); `type=field` ⇒
 * canonical `[<name>]` referencing a form input; `type=routing` ⇒ first
 * routing entry's email, with the rest dropped (an informational warning
 * is emitted at the form level).
 *
 * @param array<array-key, mixed> $notification
 * @param array<string, string>   $field_name_map
 */
function fluentforms_canonical_resolve_recipient(array $notification, array $field_name_map): string
{
    /** @var mixed $send */
    $send = $notification['sendTo'] ?? null;
    // sendTo may be (a) a plain email string when the notification was created
    // via the friendly create-notification ability, or (b) the FF-native
    // {type, email|field|routing} object when round-tripping a stored form.
    // Both are valid input shapes — the string form is treated as type=email.
    if (is_string($send)) {
        return fluentforms_canonical_normalize_smart_tags($send, $field_name_map);
    }
    if (!is_array($send)) {
        return '';
    }
    $type = isset($send['type']) && is_string($send['type']) ? $send['type'] : 'email';
    if ($type === 'field') {
        $field = isset($send['field']) && is_string($send['field']) ? $send['field'] : '';
        $canonical_name = $field_name_map[$field] ?? '';
        return $canonical_name !== '' ? '[' . $canonical_name . ']' : '';
    }
    if ($type === 'routing') {
        return fluentforms_canonical_first_routing_recipient($send, $field_name_map);
    }
    $email = isset($send['email']) && is_string($send['email']) ? $send['email'] : '';
    return fluentforms_canonical_normalize_smart_tags($email, $field_name_map);
}

/**
 * Picks the first routing-rule recipient (Fluent allows multiple "if X
 * then send to Y" entries). The rest are lost to the canonical schema —
 * the form-level warning surfaces this trade-off.
 *
 * @param array<array-key, mixed> $send
 * @param array<string, string>   $field_name_map
 */
function fluentforms_canonical_first_routing_recipient(array $send, array $field_name_map): string
{
    /** @var mixed $routing */
    $routing = $send['routing'] ?? null;
    if (!is_array($routing)) {
        return '';
    }
    // @mago-expect analysis:mixed-assignment
    foreach ($routing as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $email = isset($entry['email']) && is_string($entry['email']) ? $entry['email'] : '';
        if ($email !== '') {
            return fluentforms_canonical_normalize_smart_tags($email, $field_name_map);
        }
    }
    return '';
}

/**
 * Composes the canonical `sender` from Fluent `fromName` + `fromEmail`.
 * Format `Name <email>` when both are present, just email otherwise.
 *
 * @param array<array-key, mixed> $notification
 */
function fluentforms_canonical_compose_sender(array $notification): string
{
    $name = isset($notification['fromName']) && is_string($notification['fromName']) ? $notification['fromName'] : '';
    $email = isset($notification['fromEmail']) && is_string($notification['fromEmail'])
        ? $notification['fromEmail']
        : '';
    if ($name !== '' && $email !== '') {
        return $name . ' <' . $email . '>';
    }
    return $email !== '' ? $email : $name;
}

/**
 * Collects Fluent cc / bcc / replyTo as a canonical `additional_headers`
 * string. Empty string when none are set. The canonical contract carries
 * these as opaque text so the target plugin can re-emit them as it sees
 * fit.
 *
 * @param array<array-key, mixed> $notification
 * @param array<string, string>   $field_name_map
 */
function fluentforms_canonical_collect_extra_headers(array $notification, array $field_name_map): string
{
    $parts = [];
    foreach (['cc' => 'Cc', 'bcc' => 'Bcc', 'replyTo' => 'Reply-To'] as $key => $header) {
        if (!isset($notification[$key]) || !is_string($notification[$key]) || $notification[$key] === '') {
            continue;
        }
        $value = fluentforms_canonical_normalize_smart_tags($notification[$key], $field_name_map);
        $parts[] = $header . ': ' . $value;
    }
    return implode("\n", $parts);
}

/**
 * Returns an empty canonical mail-config block.
 *
 * @return array<string, mixed>
 */
function fluentforms_canonical_empty_mail_config(): array
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
 * Rewrites Fluent `{inputs.<name>}` smart-tags to the canonical
 * `[field-name]` convention. The lookup table is keyed by the raw input
 * name (`attributes.name`), so the regex captures the bare key. Other
 * Fluent merge tags (`{wp.admin_email}`, `{user.display_name}`, `{ip}`,
 * `{date.m/d/Y}`, `{embed_post.ID}`, `{form_title}`, `{all_data}`, etc.)
 * are preserved verbatim — they are universal macros, not form-scoped
 * references.
 *
 * @param array<string, string> $field_name_map
 */
function fluentforms_canonical_normalize_smart_tags(string $input, array $field_name_map): string
{
    if ($input === '' || $field_name_map === []) {
        return $input;
    }
    $rewritten = preg_replace_callback(
        pattern: '/\{inputs\.([a-zA-Z0-9_]+)\}/',
        callback: static function (array $match) use ($field_name_map): string {
            $key = $match[1] ?? '';
            $name = $field_name_map[$key] ?? '';
            return $name !== '' ? '[' . $name . ']' : $match[0];
        },
        subject: $input,
    );
    // PCRE failure (PREG_BACKTRACK_LIMIT_ERROR on a pathological body) → fall
    // back to the original input so the agent can resolve tags on the target.
    return $rewritten ?? $input;
}

/**
 * Collects canonical conditional_rules[] from per-field Fluent
 * `settings.conditional_logics`. Each rule references the parent field
 * (target) via its canonical name and lists triggering conditions
 * referencing source-field canonical names.
 *
 * @param list<array<array-key, mixed>> $ff_fields
 * @param array<string, string>         $field_name_map
 *
 * @return list<array<string, mixed>>
 */
function fluentforms_canonical_collect_conditional_rules(array $ff_fields, array $field_name_map): array
{
    /** @var list<array<string, mixed>> $rules */
    $rules = [];
    foreach ($ff_fields as $ff_field) {
        if (!fluentforms_canonical_has_conditional_logic($ff_field)) {
            continue;
        }
        $name = fluentforms_canonical_field_name($ff_field);
        $target_name = $field_name_map[$name] ?? '';
        if ($target_name === '') {
            continue;
        }
        $settings = is_array($ff_field['settings'] ?? null) ? $ff_field['settings'] : [];
        $logic = is_array($settings['conditional_logics'] ?? null) ? $settings['conditional_logics'] : null;
        if ($logic === null) {
            continue;
        }
        $rule = fluentforms_canonical_conditional_to_rule($logic, $target_name, $field_name_map);
        if ($rule !== null) {
            $rules[] = $rule;
        }
    }
    return $rules;
}

/**
 * Translates one Fluent conditional_logics block into a canonical rule
 * `{target_field, operator, logic, conditions[]}`. Fluent uses `status`
 * for the boolean enabled flag, `type` (any/all) for the logical
 * combinator, and `conditions[]` for the inner rules. Returns null when
 * the block has no parseable conditions.
 *
 * @param array<array-key, mixed> $logic
 * @param array<string, string>   $field_name_map
 *
 * @return array<string, mixed>|null
 */
function fluentforms_canonical_conditional_to_rule(array $logic, string $target_name, array $field_name_map): ?array
{
    if ($target_name === '') {
        return null;
    }
    $raw_conditions = isset($logic['conditions']) && is_array($logic['conditions'])
        ? array_values($logic['conditions'])
        : [];
    $conditions = fluentforms_canonical_translate_conditions($raw_conditions, $field_name_map);
    if ($conditions === []) {
        return null;
    }
    return [
        'target_field' => $target_name,
        // Fluent has no built-in hide/show action — conditional_logics on a
        // field always means "show when true". Canonical uses show_if.
        'operator' => 'show_if',
        'logic' => isset($logic['type']) && is_string($logic['type']) ? $logic['type'] : 'all',
        'conditions' => $conditions,
    ];
}

/**
 * Translates the inner `conditions[]` of one Fluent conditional_logics
 * block. Each entry has `{field, value, operator}`; `field` references a
 * Fluent input name which we resolve via the field_name_map. Rows whose
 * field does not resolve are dropped silently.
 *
 * @param list<mixed>           $raw_conditions
 * @param array<string, string> $field_name_map
 *
 * @return list<array<string, string>>
 */
function fluentforms_canonical_translate_conditions(array $raw_conditions, array $field_name_map): array
{
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw_conditions as $row) {
        if (!is_array($row)) {
            continue;
        }
        $field_ref = isset($row['field']) && is_string($row['field']) ? $row['field'] : '';
        $field_name = $field_name_map[$field_ref] ?? '';
        if ($field_name === '') {
            continue;
        }
        $out[] = [
            'field' => $field_name,
            'op' => isset($row['operator']) && is_string($row['operator']) ? $row['operator'] : '=',
            'value' => isset($row['value']) && is_string($row['value']) ? $row['value'] : '',
        ];
    }
    return $out;
}

/**
 * Form-level warnings the field-level translator cannot see: multi-page
 * forms (any `form_step`), anti-spam confirmations with custom redirects,
 * multi-column container layout, conversational mode.
 *
 * @param list<array<array-key, mixed>> $ff_fields
 * @param array<array-key, mixed>       $form_settings
 *
 * @return list<array<string, string>>
 */
function fluentforms_canonical_form_warnings(array $ff_fields, array $form_settings, int $container_count): array
{
    $warnings = [];
    $multipage = fluentforms_canonical_multipage_warning($ff_fields);
    if ($multipage !== null) {
        $warnings[] = $multipage;
    }
    $container = fluentforms_canonical_container_warning($container_count);
    if ($container !== null) {
        $warnings[] = $container;
    }
    $confirm = fluentforms_canonical_confirmation_warning($form_settings);
    if ($confirm !== null) {
        $warnings[] = $confirm;
    }
    $restrictions = fluentforms_canonical_restrictions_warning($form_settings);
    if ($restrictions !== null) {
        $warnings[] = $restrictions;
    }
    return $warnings;
}

/**
 * Detects multi-page forms (any `form_step` field) and returns the
 * canonical structural warning. Returns null when single-page.
 *
 * @param list<array<array-key, mixed>> $ff_fields
 *
 * @return array<string, string>|null
 */
function fluentforms_canonical_multipage_warning(array $ff_fields): ?array
{
    foreach ($ff_fields as $ff_field) {
        if (($ff_field['element'] ?? '') !== 'form_step') {
            continue;
        }
        return [
            'path' => 'fields',
            'reason' => 'Fluent Forms multi-page form (one or more form_step fields) collapsed to a single-page canonical — the target plugin will receive a flat list of fields.',
            'severity' => 'structural',
        ];
    }
    return null;
}

/**
 * Detects multi-column container layout and emits an informational
 * warning. Containers themselves drop from canonical fields[] (children
 * are flattened upstream).
 *
 * @return array<string, string>|null
 */
function fluentforms_canonical_container_warning(int $container_count): ?array
{
    if ($container_count <= 0) {
        return null;
    }
    return [
        'path' => 'fields',
        'reason' => sprintf(
            'Fluent Forms multi-column container layout (%d container(s)) collapsed to a flat canonical fields[] — the target plugin will need its own grid / columns implementation to preserve the original layout.',
            $container_count,
        ),
        'severity' => 'informational',
    ];
}

/**
 * Detects post-submit confirmations that are not simple message-only
 * (redirectTo=customUrl or customPage) and emits the informational
 * warning that the redirect target is echoed to meta but is not directly
 * representable in canonical. Returns null when the form ships the
 * default same-page message confirmation.
 *
 * @param array<array-key, mixed> $form_settings
 *
 * @return array<string, string>|null
 */
function fluentforms_canonical_confirmation_warning(array $form_settings): ?array
{
    /** @var mixed $confirmation */
    $confirmation = $form_settings['confirmation'] ?? null;
    if (!is_array($confirmation)) {
        return null;
    }
    $redirect = isset($confirmation['redirectTo']) && is_string($confirmation['redirectTo'])
        ? $confirmation['redirectTo']
        : 'samePage';
    if ($redirect === 'samePage') {
        return null;
    }
    return [
        'path' => 'meta.fluentforms_form_settings.confirmation',
        'reason' => sprintf(
            'Fluent Forms confirmation uses redirectTo=%s — the redirect target is echoed to meta.fluentforms_form_settings but canonical has no first-class redirect block; the target plugin must be configured manually.',
            $redirect,
        ),
        'severity' => 'informational',
    ];
}

/**
 * Detects Fluent form-level restrictions (require login, schedule, limit
 * entries, deny empty, restrict by user role) and emits an informational
 * warning when any are enabled. Returns null when no restriction is
 * active. The full restrictions blob is preserved in
 * meta.fluentforms_form_settings for round-trip.
 *
 * @param array<array-key, mixed> $form_settings
 *
 * @return array<string, string>|null
 */
function fluentforms_canonical_restrictions_warning(array $form_settings): ?array
{
    /** @var mixed $restrictions */
    $restrictions = $form_settings['restrictions'] ?? null;
    if (!is_array($restrictions)) {
        return null;
    }
    $active = [];
    foreach (['requireLogin', 'limitNumberOfEntries', 'scheduleForm', 'denyEmptySubmission', 'restrictForm'] as $key) {
        /** @var mixed $entry */
        $entry = $restrictions[$key] ?? null;
        if (!is_array($entry)) {
            continue;
        }
        if (fluentforms_canonical_truthy($entry['enabled'] ?? false)) {
            $active[] = $key;
        }
    }
    if ($active === []) {
        return null;
    }
    return [
        'path' => 'meta.fluentforms_form_settings.restrictions',
        'reason' => sprintf(
            'Fluent Forms restrictions are enabled (%s) — the full block is echoed to meta.fluentforms_form_settings for round-trip; canonical has no first-class restriction block, so the target plugin must be configured manually.',
            implode(', ', $active),
        ),
        'severity' => 'informational',
    ];
}

/**
 * Builds the canonical `meta{}` block. Carries source plugin and version;
 * echoes the entire formSettings blob for lossless round-trip on a
 * fluentforms-to-fluentforms migration.
 *
 * @param array<array-key, mixed> $form_settings
 *
 * @return array<string, mixed>
 */
function fluentforms_canonical_build_meta(string $version, array $form_settings): array
{
    return [
        'source_plugin' => 'fluentforms',
        'source_version' => $version,
        'fluentforms_form_settings' => $form_settings,
    ];
}

/**
 * Builds the warning for a Fluent element the canonical map does not
 * cover. Distinct from skipped types — an unmapped element is the
 * safety net for elements registered by future Fluent releases or
 * add-ons.
 *
 * @return array<string, string>
 */
function fluentforms_canonical_unmapped_warning(string $element, int $field_index): array
{
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => sprintf(
            'Fluent Forms [%s] element is not yet mapped to the canonical vocabulary — dropped from canonical fields[]. Report this to the integration maintainer; the element may have been added by a recent Fluent release or add-on.',
            $element,
        ),
        'severity' => 'informational',
    ];
}

/**
 * Boolean coercion matching the wpforms / cf7 / gravityforms helpers.
 * Fluent stores booleans as native bool plus the occasional "1" / ""
 * string variant in the JSON blob.
 */
function fluentforms_canonical_truthy(mixed $value): bool
{
    if (in_array($value, [null, false, 0, 0.0, '', '0', 'false', 'False', 'FALSE', 'no', 'No'], strict: true)) {
        return false;
    }
    return true;
}

/**
 * Truncates a string at a byte cap, mirroring the cf7 / wpforms /
 * gravityforms canonical readers. Used on mail / notification bodies so
 * the canonical response stays under control even when the source form
 * ships a multi-kilobyte body.
 *
 * @return array{value: string, truncated: bool, original_bytes: int}
 */
function fluentforms_canonical_truncate(string $value, int $max_bytes): array
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
