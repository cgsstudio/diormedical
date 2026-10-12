<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\GravityForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/gravityforms-get-form-canonical', [
    'label' => __('Get Gravity Forms Form (Canonical)', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Gravity Forms form by id and returns it mapped to the plugin-agnostic form-model-schema vocabulary (17 canonical types: text/email/tel/url/number/textarea/date/time/datetime/select/radio/checkbox/file/hidden/submit/consent/group) used by the form-model-migration skill. The ~47 Gravity Forms native field types fold onto those 17 — composites (`name`, `address`) collapse to canonical `group` with sub-fields; structural-only types (`section`, `page`, `html`) drop with informational warnings; payment and post-creation types (`creditcard`, `product`, `singleproduct`, `quantity`, `option`, `shipping`, `total`, `donation`, `post_*`, `calculation`) drop with structural warnings. Output keys: `fields[]` with canonical types, `mail_config{}` from the first active notification, `notifications[]` from the remaining ones, `messages{}` (empty — Gravity Forms has no built-in message catalogue), `conditional_rules[]` from per-field GF conditionalLogic (mapped 1:1 when expressible), `meta{}` (source plugin + version + confirmations / settings echo for round-trip), plus `warnings[]` for everything that does not fit the canonical shape (multi-page forms, anti-spam captcha, complex conditional logic, payment + post-creation fields). Honours the same `max_field_bytes` truncation contract as `wpforms-get-form-canonical` (default 8192 bytes for `mail_config.body`, `notifications[].body`, with `*_truncated` flags); pass `max_field_bytes: 0` to disable truncation when full-fidelity canonical is required before an execute-mode migration. Returns WP_Error `gravityforms_form_not_found` when the id does not exist or refers to a trashed form. Use this ability inside the form-model-migration workflow (Phase 2 — read source schema); for ad-hoc inspection outside the migration pipeline, prefer `gravityforms-get-form` which returns the Gravity-native shape.',
        domain: 'novamira-pro',
    ),
    'category' => 'gravityforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Gravity Forms form id.',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\GravityForms\\gravityforms_get_form_canonical',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use as Phase 2 (read source) of the form-model-migration workflow when Gravity Forms is the source plugin. The output is the canonical form-model-schema document directly — feed it into Phase 3 (translate) as-is; warnings[] already enumerates structural / informational losses (composite fields, payment / post-creation types without canonical equivalent, multi-page, complex conditionals, captcha). Pass max_field_bytes:0 before an execute-mode migration so canonical bodies are not truncated.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{form_id: int, max_field_bytes?: int} $input
 * @return array<string, mixed>|WP_Error
 */
function gravityforms_get_form_canonical(array $input): array|WP_Error
{
    if (!gravityforms_detect_setup()['active']) {
        return gravityforms_not_active();
    }

    $form = gravityforms_form_or_error((int) $input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }

    $cap_error = gravityforms_capability_or_error('gravityforms_edit_forms');
    if ($cap_error !== null) {
        return $cap_error;
    }

    $max_field_bytes = isset($input['max_field_bytes']) ? max(0, $input['max_field_bytes']) : 8192;

    return gravityforms_canonical_compose($form, $max_field_bytes);
}

/**
 * Build the canonical document from a Gravity Forms form array. Normalises
 * GF_Field objects to plain arrays via get_object_vars() at the entry so the
 * downstream translation pipeline stays array-based (mirroring the WPForms
 * canonical reader). Notifications and confirmations are already plain
 * arrays keyed by hash id.
 *
 * @param array<string, mixed> $form
 * @return array<string, mixed>
 */
function gravityforms_canonical_compose(array $form, int $max_field_bytes): array
{
    $gf_fields_raw = gravityforms_canonical_normalise_fields($form['fields'] ?? []);
    $notifications_raw = is_array($form['notifications'] ?? null) ? $form['notifications'] : [];
    $confirmations_raw = is_array($form['confirmations'] ?? null) ? $form['confirmations'] : [];
    $sections = gravityforms_split_form_definition($form);
    $settings_raw = $sections['settings'];

    $field_name_map = gravityforms_canonical_build_field_name_map($gf_fields_raw);
    $detection = gravityforms_detect_setup();
    $version = is_string($detection['version']) ? $detection['version'] : '';

    $fields_result = gravityforms_canonical_translate_fields($gf_fields_raw, $field_name_map);
    $primary_id = gravityforms_canonical_primary_notification_id($notifications_raw);
    $mail_config = gravityforms_canonical_translate_mail_config(
        $notifications_raw[$primary_id] ?? null,
        $field_name_map,
        $max_field_bytes,
    );
    $notifications = gravityforms_canonical_translate_notifications(
        $notifications_raw,
        $primary_id,
        $field_name_map,
        $max_field_bytes,
    );
    $conditional_rules = gravityforms_canonical_collect_conditional_rules($gf_fields_raw, $field_name_map);
    $form_warnings = gravityforms_canonical_form_warnings($gf_fields_raw, $settings_raw, $confirmations_raw);

    return [
        'form_id' => (int) ($form['id'] ?? 0),
        'title' => is_string($form['title'] ?? null) ? $form['title'] : '',
        'slug' => sanitize_title(is_string($form['title'] ?? null) ? $form['title'] : ''),
        'locale' => '',
        'fields' => $fields_result['fields'],
        'mail_config' => $mail_config,
        'notifications' => $notifications,
        'messages' => (object) [],
        'conditional_rules' => $conditional_rules,
        'meta' => gravityforms_canonical_build_meta($version, $confirmations_raw, $settings_raw),
        'warnings' => array_merge($fields_result['warnings'], $form_warnings),
    ];
}

/**
 * Normalises raw Gravity Forms fields into plain arrays. After GFAPI::get_form
 * each field is a GF_Field_* object whose public properties match the editor
 * payload; get_object_vars() returns exactly those (the protected `\0GF_Field\0`
 * properties are filtered out, so the downstream translator never sees the
 * internal noise). Falls back gracefully on already-array entries — useful in
 * smoke contexts where the form has just been built and not re-fetched.
 *
 * @param mixed $raw
 * @return list<array<array-key, mixed>>
 */
function gravityforms_canonical_normalise_fields(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    /** @var list<array<array-key, mixed>> $out */
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $entry) {
        if (is_object($entry)) {
            $out[] = get_object_vars($entry);
            continue;
        }
        if (is_array($entry)) {
            $out[] = $entry;
        }
    }
    return $out;
}

/**
 * Builds the field-id → canonical-name lookup used to rewrite GF
 * `{Label:N}` mail-tags into the `[field-name]` canonical convention.
 * GF field ids are integers (or, on multi-input composites, dotted
 * strings like "2.3"); canonical names come from sanitize_title(label).
 *
 * @param list<array<array-key, mixed>> $gf_fields
 * @return array<string, string>
 */
function gravityforms_canonical_build_field_name_map(array $gf_fields): array
{
    $map = [];
    foreach ($gf_fields as $field) {
        $id = isset($field['id']) ? (string) $field['id'] : '';
        if ($id === '') {
            continue;
        }
        $label = isset($field['label']) && is_string($field['label']) ? $field['label'] : '';
        $name = $label !== '' ? sanitize_title($label) : '';
        if ($name === '') {
            $name = 'field-' . $id;
        }
        $map[$id] = $name;
    }
    return $map;
}

/**
 * Picks the GF notification id to surface as the canonical `mail_config{}`.
 * GF keys notifications by hash, so a deterministic pick uses (a) the first
 * active notification (b) the first one overall when none are active.
 *
 * @param array<array-key, mixed> $notifications
 */
function gravityforms_canonical_primary_notification_id(array $notifications): string
{
    if ($notifications === []) {
        return '';
    }
    // @mago-expect analysis:mixed-assignment
    foreach ($notifications as $id => $notification) {
        if (!is_array($notification)) {
            continue;
        }
        if (gravityforms_canonical_truthy($notification['isActive'] ?? true)) {
            return (string) $id;
        }
    }
    return (string) array_keys($notifications)[0];
}

/**
 * Translates all GF fields into canonical fields[], accumulating warnings
 * for losses (payment / post-creation / structural types, composite
 * collapse, complex conditional logic).
 *
 * @param list<array<array-key, mixed>> $gf_fields
 * @param array<string, string>      $field_name_map
 * @return array{fields: list<array<string, mixed>>, warnings: list<array<string, string>>}
 */
function gravityforms_canonical_translate_fields(array $gf_fields, array $field_name_map): array
{
    /** @var list<array<string, mixed>> $fields */
    $fields = [];
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    $index = 0;
    foreach ($gf_fields as $gf_field) {
        $result = gravityforms_canonical_translate_field($gf_field, $index, $field_name_map);
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
 * Maps a single GF field to canonical. Returns null when the field has no
 * canonical representation at all (section, page, html, captcha, honeypot,
 * payment-*, post_*, calculation). In those cases the warnings list carries
 * the explanation for the user.
 *
 * @param array<array-key, mixed> $gf_field
 * @param array<string, string>   $field_name_map
 * @return array{field: array<string, mixed>|null, warnings: list<array<string, string>>}
 */
function gravityforms_canonical_translate_field(array $gf_field, int $field_index, array $field_name_map): array
{
    $gf_type = isset($gf_field['type']) && is_string($gf_field['type']) ? $gf_field['type'] : '';
    $id = isset($gf_field['id']) ? (string) $gf_field['id'] : '';
    $name = $field_name_map[$id] ?? 'field-' . $id;

    if (gravityforms_canonical_field_is_skipped($gf_type)) {
        return [
            'field' => null,
            'warnings' => [gravityforms_canonical_skip_warning($gf_type, $field_index)],
        ];
    }

    $canonical_type = gravityforms_canonical_type_map($gf_type);
    if ($canonical_type === null) {
        return [
            'field' => null,
            'warnings' => [gravityforms_canonical_unmapped_warning($gf_type, $field_index)],
        ];
    }

    $field = gravityforms_canonical_field_payload($gf_field, $gf_type, $name, $canonical_type);
    $warnings = gravityforms_canonical_field_warnings($gf_field, $gf_type, $field_index);

    return ['field' => $field, 'warnings' => $warnings];
}

/**
 * Returns true when the GF field type has no canonical role (pure layout /
 * metadata / UI-only / payment / post-creation / out-of-scope) and should
 * be dropped from fields[] with a single informational or structural
 * warning.
 */
function gravityforms_canonical_field_is_skipped(string $gf_type): bool
{
    return in_array(
        $gf_type,
        [
            'section',
            'page',
            'html',
            'captcha',
            'honeypot',
            'creditcard',
            'product',
            'singleproduct',
            'hiddenproduct',
            'price',
            'quantity',
            'option',
            'shipping',
            'singleshipping',
            'total',
            'donation',
            'post_title',
            'post_content',
            'post_excerpt',
            'post_tags',
            'post_category',
            'post_image',
            'post_custom_field',
            'calculation',
        ],
        strict: true,
    );
}

/**
 * Builds the warning entry for a skipped GF field. Severity is `structural`
 * for semantically heavy drops (multi-page, payment, post-creation,
 * calculation, anti-spam) and `informational` for pure rendering (section,
 * html).
 *
 * @return array<string, string>
 */
function gravityforms_canonical_skip_warning(string $gf_type, int $field_index): array
{
    $structural = [
        'page',
        'captcha',
        'creditcard',
        'product',
        'singleproduct',
        'hiddenproduct',
        'price',
        'quantity',
        'option',
        'shipping',
        'singleshipping',
        'total',
        'donation',
        'post_title',
        'post_content',
        'post_excerpt',
        'post_tags',
        'post_category',
        'post_image',
        'post_custom_field',
        'calculation',
    ];
    $severity = in_array($gf_type, $structural, strict: true) ? 'structural' : 'informational';
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => sprintf(
            'Gravity Forms [%s] field has no canonical equivalent — preserved as a warning for the migration plan; the target plugin must provide its own implementation.',
            $gf_type,
        ),
        'severity' => $severity,
    ];
}

/**
 * Maps a GF field type to its canonical form-model-schema type. Returns null
 * when the type is not skipped but does not map cleanly (kept as a safety
 * net — every type used in the wild should map).
 */
function gravityforms_canonical_type_map(string $gf_type): ?string
{
    return match ($gf_type) {
        'text' => 'text',
        'textarea' => 'textarea',
        'email' => 'email',
        'phone' => 'tel',
        'website' => 'url',
        'number' => 'number',
        'date' => 'date',
        'time' => 'time',
        'hidden' => 'hidden',
        'select' => 'select',
        'multiselect' => 'select',
        'checkbox' => 'checkbox',
        'multi_choice' => 'select',
        'radio' => 'radio',
        'image_choice' => 'radio',
        'password' => 'text',
        'consent' => 'consent',
        'fileupload' => 'file',
        'name' => 'group',
        'address' => 'group',
        'list' => 'group',
        'repeater' => 'group',
        'submit' => 'submit',
        default => null,
    };
}

/**
 * Builds the canonical field array for the mapped GF field. Pulls common
 * keys (name / type / label / description / required / placeholder /
 * default / cardinality / choices / validation) and carries GF-specific
 * hints into meta for lossless round-trip.
 *
 * @param array<array-key, mixed> $gf_field
 * @return array<string, mixed>
 */
function gravityforms_canonical_field_payload(
    array $gf_field,
    string $gf_type,
    string $name,
    string $canonical_type,
): array {
    $field = [
        'name' => $name,
        'type' => $canonical_type,
    ];
    gravityforms_canonical_apply_string_property($field, $gf_field, key: 'label');
    gravityforms_canonical_apply_string_property($field, $gf_field, key: 'description');
    gravityforms_canonical_apply_string_property($field, $gf_field, key: 'placeholder');
    gravityforms_canonical_apply_default_value($field, $gf_field);
    if ($canonical_type === 'consent' || gravityforms_canonical_truthy($gf_field['isRequired'] ?? null)) {
        $field['required'] = true;
    }
    gravityforms_canonical_apply_cardinality($field, $gf_field, $gf_type, $canonical_type);
    gravityforms_canonical_apply_choices($field, $gf_field, $canonical_type);
    gravityforms_canonical_apply_validation($field, $gf_field, $gf_type, $canonical_type);
    gravityforms_canonical_apply_subtype($field, $gf_field, $gf_type, $canonical_type);
    $field['meta'] = gravityforms_canonical_field_meta($gf_field, $gf_type);

    return $field;
}

/**
 * Builds the canonical `meta{}` sub-block for a field. Carries the original
 * GF type (so round-trip can rebuild the field) plus any non-canonical
 * rendering hints (CSS classes, size).
 *
 * @param array<array-key, mixed> $gf_field
 * @return array<string, mixed>
 */
function gravityforms_canonical_field_meta(array $gf_field, string $gf_type): array
{
    $meta = ['gravityforms_field_type' => $gf_type];
    if (isset($gf_field['cssClass']) && is_string($gf_field['cssClass']) && $gf_field['cssClass'] !== '') {
        $meta['gravityforms_css'] = $gf_field['cssClass'];
    }
    if (isset($gf_field['size']) && is_string($gf_field['size']) && $gf_field['size'] !== '') {
        $meta['gravityforms_size'] = $gf_field['size'];
    }
    if (isset($gf_field['adminLabel']) && is_string($gf_field['adminLabel']) && $gf_field['adminLabel'] !== '') {
        $meta['gravityforms_admin_label'] = $gf_field['adminLabel'];
    }
    return $meta;
}

/**
 * Copies a simple string property from the GF field to the canonical field
 * when it is present and non-empty.
 *
 * @param array<string, mixed> $field
 * @param array<array-key, mixed> $gf_field
 */
function gravityforms_canonical_apply_string_property(array &$field, array $gf_field, string $key): void
{
    if (isset($gf_field[$key]) && is_string($gf_field[$key]) && $gf_field[$key] !== '') {
        $field[$key] = $gf_field[$key];
    }
}

/**
 * Carries the GF `defaultValue` to the canonical `default` key when present
 * and non-empty.
 *
 * @param array<string, mixed> $field
 * @param array<array-key, mixed> $gf_field
 */
function gravityforms_canonical_apply_default_value(array &$field, array $gf_field): void
{
    if (isset($gf_field['defaultValue']) && is_string($gf_field['defaultValue']) && $gf_field['defaultValue'] !== '') {
        $field['default'] = $gf_field['defaultValue'];
    }
}

/**
 * Sets canonical `cardinality` for the field types where the value is
 * meaningful (select multi, checkbox group, multi-file upload, list /
 * repeater rows). Leaves the key absent on scalar types.
 *
 * @param array<string, mixed> $field
 * @param array<array-key, mixed> $gf_field
 */
function gravityforms_canonical_apply_cardinality(
    array &$field,
    array $gf_field,
    string $gf_type,
    string $canonical_type,
): void {
    if ($canonical_type === 'radio') {
        $field['cardinality'] = 'single';
        return;
    }
    if ($gf_type === 'select') {
        $field['cardinality'] = 'single';
        return;
    }
    if ($gf_type === 'multiselect' || $gf_type === 'multi_choice') {
        $field['cardinality'] = 'multi';
        return;
    }
    if ($gf_type === 'checkbox' && $canonical_type === 'checkbox') {
        $field['cardinality'] = 'multi';
        return;
    }
    if ($gf_type === 'fileupload') {
        $field['cardinality'] = gravityforms_canonical_truthy($gf_field['multipleFiles'] ?? null) ? 'multi' : 'single';
        return;
    }
    if (in_array($gf_type, ['list', 'repeater'], strict: true)) {
        $field['cardinality'] = 'multi';
    }
}

/**
 * Translates GF `choices[]` into canonical `choices[{value,label}]` for the
 * choice-bearing types (select / multiselect / checkbox / radio /
 * image_choice / multi_choice). GF carries `text` for label and `value`
 * fallback to `text`.
 *
 * @param array<string, mixed> $field
 * @param array<array-key, mixed> $gf_field
 */
function gravityforms_canonical_apply_choices(array &$field, array $gf_field, string $canonical_type): void
{
    if (!in_array($canonical_type, ['select', 'checkbox', 'radio'], strict: true)) {
        return;
    }
    /** @var mixed $raw */
    $raw = $gf_field['choices'] ?? null;
    if (!is_array($raw) || $raw === []) {
        return;
    }
    $choices = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $label = isset($entry['text']) && is_string($entry['text']) ? $entry['text'] : '';
        $value =
            isset($entry['value']) && is_string($entry['value']) && $entry['value'] !== '' ? $entry['value'] : $label;
        if ($label === '' && $value === '') {
            continue;
        }
        $choices[] = ['value' => $value, 'label' => $label !== '' ? $label : $value];
    }
    if ($choices !== []) {
        $field['choices'] = $choices;
    }
}

/**
 * Populates the canonical `validation` block from GF field-level limits
 * (rangeMin / rangeMax for number, max characters for text / textarea,
 * allowedExtensions for fileupload).
 *
 * @param array<string, mixed> $field
 * @param array<array-key, mixed> $gf_field
 */
function gravityforms_canonical_apply_validation(
    array &$field,
    array $gf_field,
    string $gf_type,
    string $canonical_type,
): void {
    $validation = array_merge(
        gravityforms_canonical_number_validation($gf_field, $canonical_type),
        gravityforms_canonical_file_validation($gf_field, $gf_type),
        gravityforms_canonical_length_validation($gf_field, $canonical_type),
    );
    if ($validation !== []) {
        $field['validation'] = $validation;
    }
}

/**
 * Extracts canonical `min` / `max` from a GF number field (`rangeMin`,
 * `rangeMax`). Returns an empty array for non-number types.
 *
 * @param array<array-key, mixed> $gf_field
 * @return array<string, int|float>
 */
function gravityforms_canonical_number_validation(array $gf_field, string $canonical_type): array
{
    if ($canonical_type !== 'number') {
        return [];
    }
    $out = [];
    if (isset($gf_field['rangeMin']) && is_numeric($gf_field['rangeMin'])) {
        $out['min'] = gravityforms_canonical_to_number($gf_field['rangeMin']);
    }
    if (isset($gf_field['rangeMax']) && is_numeric($gf_field['rangeMax'])) {
        $out['max'] = gravityforms_canonical_to_number($gf_field['rangeMax']);
    }
    return $out;
}

/**
 * Extracts canonical `accepts[]` and `max_size_bytes` from a GF fileupload
 * field. GF stores extensions as a comma-separated string and `maxFileSize`
 * in megabytes.
 *
 * @param array<array-key, mixed> $gf_field
 * @return array<string, mixed>
 */
function gravityforms_canonical_file_validation(array $gf_field, string $gf_type): array
{
    if ($gf_type !== 'fileupload') {
        return [];
    }
    $out = [];
    $accepts = gravityforms_canonical_parse_extensions($gf_field['allowedExtensions'] ?? '');
    if ($accepts !== []) {
        $out['accepts'] = $accepts;
    }
    if (isset($gf_field['maxFileSize']) && is_numeric($gf_field['maxFileSize'])) {
        $out['max_size_bytes'] = (int) round(floatval($gf_field['maxFileSize']) * 1024 * 1024);
    }
    return $out;
}

/**
 * Extracts canonical `max_length` from a GF text / textarea field that has
 * the maxLength setting.
 *
 * @param array<array-key, mixed> $gf_field
 * @return array<string, int>
 */
function gravityforms_canonical_length_validation(array $gf_field, string $canonical_type): array
{
    if (!in_array($canonical_type, ['text', 'textarea'], strict: true)) {
        return [];
    }
    if (!isset($gf_field['maxLength']) || !is_numeric($gf_field['maxLength'])) {
        return [];
    }
    $limit = (int) $gf_field['maxLength'];
    if ($limit <= 0) {
        return [];
    }
    return ['max_length' => $limit];
}

/**
 * Casts a numeric-looking GF value (often a string in the JSON blob) to
 * native int / float.
 */
function gravityforms_canonical_to_number(mixed $value): int|float
{
    if (!is_numeric($value)) {
        return 0;
    }
    $string = (string) $value;
    return str_contains($string, '.') ? floatval($string) : (int) $string;
}

/**
 * Parses the GF `allowedExtensions` comma-separated list into canonical
 * MIME types. Unknown extensions are dropped.
 *
 * @return list<string>
 */
function gravityforms_canonical_parse_extensions(mixed $raw): array
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
    foreach (explode(separator: ',', string: strtolower($raw)) as $ext) {
        $ext = trim($ext);
        if ($ext !== '' && isset($map[$ext])) {
            $out[] = $map[$ext];
        }
    }
    return array_values(array_unique($out));
}

/**
 * Folds GF type-specific specialisers (password format, integer vs decimal
 * number) into the canonical field.
 *
 * @param array<string, mixed> $field
 * @param array<array-key, mixed> $gf_field
 */
// @mago-expect lint:no-insecure-comparison
function gravityforms_canonical_apply_subtype(
    array &$field,
    array $gf_field,
    string $gf_type,
    string $canonical_type,
): void {
    if ($gf_type === 'password' && $canonical_type === 'text') {
        $field['format'] = 'password';
        return;
    }
    if ($gf_type === 'number' && $canonical_type === 'number') {
        $format = isset($gf_field['numberFormat']) && is_string($gf_field['numberFormat'])
            ? $gf_field['numberFormat']
            : 'decimal_dot';
        $field['subtype'] = $format === 'decimal_dot' || $format === 'decimal_comma' ? 'decimal' : 'integer';
    }
}

/**
 * Per-field migration warnings: composite collapse (name / address / list),
 * conditional logic presence, multi-input fields.
 *
 * @param array<array-key, mixed> $gf_field
 * @return list<array<string, string>>
 */
function gravityforms_canonical_field_warnings(array $gf_field, string $gf_type, int $field_index): array
{
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    if (in_array($gf_type, ['name', 'address', 'list'], strict: true)) {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => sprintf(
                'Gravity Forms composite [%s] collapsed to canonical group — sub-field structure (inputs[] for name/address; columns[] for list) is plugin-specific and will need re-creation on the target.',
                $gf_type,
            ),
            'severity' => 'informational',
        ];
    }
    if (gravityforms_canonical_has_conditional_logic($gf_field)) {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => 'Gravity Forms conditional logic is mapped to canonical conditional_rules[] when expressible — complex nested AND/OR rules or value-derived triggers are flattened with possible semantic loss.',
            'severity' => 'informational',
        ];
    }

    return $warnings;
}

/**
 * Returns true when a GF field has a `conditionalLogic` block with at
 * least one rule (GF stores conditionalLogic = false when disabled, or an
 * object with `actionType`, `logicType`, `rules[]` when enabled).
 *
 * @param array<array-key, mixed> $gf_field
 */
function gravityforms_canonical_has_conditional_logic(array $gf_field): bool
{
    if (!isset($gf_field['conditionalLogic']) || !is_array($gf_field['conditionalLogic'])) {
        return false;
    }
    /** @var mixed $rules */
    $rules = $gf_field['conditionalLogic']['rules'] ?? null;
    return is_array($rules) && $rules !== [];
}

/**
 * Translates the first active GF notification into the canonical
 * `mail_config{}`. When no notification is present the canonical still
 * emits the block with empty values so downstream consumers don't have to
 * special-case it.
 *
 * @param mixed                 $notification
 * @param array<string, string> $field_name_map
 * @return array<string, mixed>
 */
function gravityforms_canonical_translate_mail_config(
    mixed $notification,
    array $field_name_map,
    int $max_field_bytes,
): array {
    if (!is_array($notification)) {
        return gravityforms_canonical_empty_mail_config();
    }
    return gravityforms_canonical_notification_to_canonical($notification, $field_name_map, $max_field_bytes);
}

/**
 * Translates every GF notification EXCEPT the primary one into canonical
 * notifications[]. Each entry carries `name`, `active`, and the same
 * mail-config keys the primary uses.
 *
 * @param array<array-key, mixed> $notifications
 * @param array<string, string>   $field_name_map
 * @return list<array<string, mixed>>
 */
function gravityforms_canonical_translate_notifications(
    array $notifications,
    string $primary_id,
    array $field_name_map,
    int $max_field_bytes,
): array {
    /** @var list<array<string, mixed>> $out */
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($notifications as $id => $notification) {
        if ((string) $id === $primary_id || !is_array($notification)) {
            continue;
        }
        $entry = gravityforms_canonical_notification_to_canonical($notification, $field_name_map, $max_field_bytes);
        $entry = array_merge([
            'name' => gravityforms_canonical_notification_name($notification, (string) $id),
            'active' => gravityforms_canonical_truthy($notification['isActive'] ?? true),
        ], $entry);
        $out[] = $entry;
    }
    return $out;
}

/**
 * Returns the canonical notification name. GF keys notifications by hash
 * but each carries a human label in `name`; fall back to `notification-<id>`
 * when missing.
 *
 * @param array<array-key, mixed> $notification
 */
function gravityforms_canonical_notification_name(array $notification, string $id): string
{
    if (isset($notification['name']) && is_string($notification['name']) && $notification['name'] !== '') {
        $slug = sanitize_title($notification['name']);
        if ($slug !== '') {
            return $slug;
        }
    }
    return 'notification-' . $id;
}

/**
 * Translates a single GF notification array into the canonical mail-config
 * shape (shared by `mail_config{}` and each `notifications[]` entry).
 * `{Label:N}` mail-tags inside the body / subject / recipient / cc / bcc
 * are rewritten to `[field-name]` so the canonical document round-trips
 * cleanly with the CF7 / WPForms readers.
 *
 * @param array<array-key, mixed> $notification
 * @param array<string, string>   $field_name_map
 * @return array<string, mixed>
 */
function gravityforms_canonical_notification_to_canonical(
    array $notification,
    array $field_name_map,
    int $max_field_bytes,
): array {
    $body_raw = gravityforms_canonical_normalize_smart_tags(
        isset($notification['message']) && is_string($notification['message']) ? $notification['message'] : '',
        $field_name_map,
    );
    $body_truncated = gravityforms_canonical_truncate($body_raw, $max_field_bytes);

    return [
        'subject' => gravityforms_canonical_normalize_smart_tags(
            isset($notification['subject']) && is_string($notification['subject']) ? $notification['subject'] : '',
            $field_name_map,
        ),
        'sender' => isset($notification['from']) && is_string($notification['from']) ? $notification['from'] : '',
        'recipient' => gravityforms_canonical_normalize_smart_tags(
            isset($notification['to']) && is_string($notification['to']) ? $notification['to'] : '',
            $field_name_map,
        ),
        'body' => $body_truncated['value'],
        'body_truncated' => $body_truncated['truncated'],
        'body_bytes' => $body_truncated['original_bytes'],
        'additional_headers' => gravityforms_canonical_collect_extra_headers($notification, $field_name_map),
        'attachments' => [],
        'use_html' => !gravityforms_canonical_truthy($notification['disableAutoformat'] ?? null),
        'exclude_blank' => false,
    ];
}

/**
 * Collects GF cc / bcc / replyTo / fromName as a canonical
 * `additional_headers` string. Empty string when none are set. The
 * canonical contract carries these as opaque text so the target plugin can
 * re-emit them as it sees fit.
 *
 * @param array<array-key, mixed> $notification
 * @param array<string, string>   $field_name_map
 */
function gravityforms_canonical_collect_extra_headers(array $notification, array $field_name_map): string
{
    $parts = [];
    foreach (['cc' => 'Cc', 'bcc' => 'Bcc', 'replyTo' => 'Reply-To', 'fromName' => 'From-Name'] as $key => $header) {
        if (!isset($notification[$key]) || !is_string($notification[$key]) || $notification[$key] === '') {
            continue;
        }
        $value = gravityforms_canonical_normalize_smart_tags($notification[$key], $field_name_map);
        $parts[] = $header . ': ' . $value;
    }
    return implode("\n", $parts);
}

/**
 * Returns an empty canonical mail-config block.
 *
 * @return array<string, mixed>
 */
function gravityforms_canonical_empty_mail_config(): array
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
 * Rewrites GF `{Label:N}` mail-tags to the canonical `[field-name]`
 * convention. The GF mail-tag syntax embeds the field label as the prefix
 * (sometimes with sub-input modifiers like `{Name:1.3}`), so the regex
 * captures the numeric id (with optional `.N` modifier) and looks it up
 * in the field_name_map keyed by base id. Other GF merge tags
 * (`{admin_email}`, `{date_mdy}`, `{embed_url}`, etc.) are preserved
 * verbatim — the caller may add an informational warning.
 *
 * @param array<string, string> $field_name_map
 */
function gravityforms_canonical_normalize_smart_tags(string $input, array $field_name_map): string
{
    if ($input === '' || $field_name_map === []) {
        return $input;
    }
    $rewritten = preg_replace_callback(
        pattern: '/\{[^{}:]+:(\d+(?:\.\d+)?)\}/',
        callback: static function (array $match) use ($field_name_map): string {
            $id_full = $match[1] ?? '';
            $base_id = explode('.', $id_full)[0];
            $name = $field_name_map[$base_id] ?? '';
            return $name !== '' ? '[' . $name . ']' : $match[0];
        },
        subject: $input,
    );
    // Defence: preg_replace_callback returns null on a PCRE error
    // (PREG_BACKTRACK_LIMIT_ERROR on very long bodies with many tags,
    // OOM mid-callback). Falling back to the original input preserves the
    // tags verbatim so the agent can resolve them on the target side.
    return $rewritten ?? $input;
}

/**
 * Collects canonical conditional_rules[] from per-field GF conditionalLogic.
 * Maps the field id under `conditionalLogic.rules[].fieldId` to canonical
 * names and translates the `actionType` (show / hide) + `logicType` (all /
 * any). The target field name comes from the parent field's canonical name.
 *
 * @param list<array<array-key, mixed>> $gf_fields
 * @param array<string, string>      $field_name_map
 * @return list<array<string, mixed>>
 */
function gravityforms_canonical_collect_conditional_rules(array $gf_fields, array $field_name_map): array
{
    /** @var list<array<string, mixed>> $rules */
    $rules = [];
    foreach ($gf_fields as $gf_field) {
        if (!gravityforms_canonical_has_conditional_logic($gf_field)) {
            continue;
        }
        $id = isset($gf_field['id']) ? (string) $gf_field['id'] : '';
        $target_name = $field_name_map[$id] ?? '';
        $logic = is_array($gf_field['conditionalLogic'] ?? null) ? $gf_field['conditionalLogic'] : null;
        if ($logic === null) {
            continue;
        }
        $rule = gravityforms_canonical_conditional_to_rule($logic, $target_name, $field_name_map);
        if ($rule !== null) {
            $rules[] = $rule;
        }
    }
    return $rules;
}

/**
 * Translates one GF conditionalLogic block into a canonical rule
 * `{target_field, operator, logic, conditions[]}`. Returns null when the
 * block has no parseable conditions.
 *
 * @param array<array-key, mixed> $logic
 * @param array<string, string>   $field_name_map
 * @return array<string, mixed>|null
 */
function gravityforms_canonical_conditional_to_rule(array $logic, string $target_name, array $field_name_map): ?array
{
    if ($target_name === '') {
        return null;
    }
    $raw_rules = isset($logic['rules']) && is_array($logic['rules']) ? array_values($logic['rules']) : [];
    $conditions = gravityforms_canonical_translate_conditions($raw_rules, $field_name_map);
    if ($conditions === []) {
        return null;
    }
    $action = isset($logic['actionType']) && is_string($logic['actionType']) ? $logic['actionType'] : 'show';
    return [
        'target_field' => $target_name,
        'operator' => $action === 'hide' ? 'hide_if' : 'show_if',
        'logic' => isset($logic['logicType']) && is_string($logic['logicType']) ? $logic['logicType'] : 'all',
        'conditions' => $conditions,
    ];
}

/**
 * Translates the inner `rules[]` array of one GF conditionalLogic (each
 * entry references a field by `fieldId`) into canonical-named conditions.
 * Rows whose `fieldId` does not resolve to a known canonical name are
 * dropped silently — the parent rule survives on the remaining conditions.
 *
 * @param list<mixed>           $raw_rules
 * @param array<string, string> $field_name_map
 * @return list<array<string, string>>
 */
function gravityforms_canonical_translate_conditions(array $raw_rules, array $field_name_map): array
{
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw_rules as $row) {
        if (!is_array($row)) {
            continue;
        }
        $field_id = isset($row['fieldId']) ? (string) $row['fieldId'] : '';
        $base_id = explode('.', $field_id)[0];
        $field_name = $field_name_map[$base_id] ?? '';
        if ($field_name === '') {
            continue;
        }
        $out[] = [
            'field' => $field_name,
            'op' => isset($row['operator']) && is_string($row['operator']) ? $row['operator'] : 'is',
            'value' => isset($row['value']) && is_string($row['value']) ? $row['value'] : '',
        ];
    }
    return $out;
}

/**
 * Form-level warnings the field-level translator cannot see: multi-page
 * forms (any `page` field), anti-spam in fields (captcha / honeypot
 * already warned), confirmations with non-message types.
 *
 * @param list<array<array-key, mixed>> $gf_fields
 * @param array<string, mixed>       $settings
 * @param array<array-key, mixed>    $confirmations
 * @return list<array<string, string>>
 */
function gravityforms_canonical_form_warnings(array $gf_fields, array $settings, array $confirmations): array
{
    $warnings = [];
    $multipage = gravityforms_canonical_multipage_warning($gf_fields);
    if ($multipage !== null) {
        $warnings[] = $multipage;
    }
    $captcha = gravityforms_canonical_captcha_warning($settings);
    if ($captcha !== null) {
        $warnings[] = $captcha;
    }
    $confirm = gravityforms_canonical_confirmations_warning($confirmations);
    if ($confirm !== null) {
        $warnings[] = $confirm;
    }
    return $warnings;
}

/**
 * Detects multi-page forms (any `page` field) and returns the canonical
 * structural warning. Returns null when the form is single-page.
 *
 * @param list<array<array-key, mixed>> $gf_fields
 * @return array<string, string>|null
 */
function gravityforms_canonical_multipage_warning(array $gf_fields): ?array
{
    foreach ($gf_fields as $gf_field) {
        if (($gf_field['type'] ?? '') !== 'page') {
            continue;
        }
        return [
            'path' => 'fields',
            'reason' => 'Gravity Forms multi-page form structure (one or more page fields) collapsed to a single-page canonical — the target plugin will receive a flat list of fields.',
            'severity' => 'structural',
        ];
    }
    return null;
}

/**
 * Detects the GF anti-spam honeypot toggle in settings and returns the
 * informational warning. Returns null when disabled.
 *
 * @param array<string, mixed> $settings
 * @return array<string, string>|null
 */
function gravityforms_canonical_captcha_warning(array $settings): ?array
{
    if (!gravityforms_canonical_truthy($settings['enableHoneypot'] ?? null)) {
        return null;
    }
    return [
        'path' => 'meta.gravityforms_form_settings.enableHoneypot',
        'reason' => 'Gravity Forms honeypot anti-spam is enabled — anti-spam logic is plugin-specific and must be reconfigured on the target.',
        'severity' => 'informational',
    ];
}

/**
 * Detects confirmations with non-message types (page / redirect) or
 * multiple entries and returns the informational warning that the full
 * set is echoed to meta. Returns null when the form ships a single
 * message-only confirmation.
 *
 * @param array<array-key, mixed> $confirmations
 * @return array<string, string>|null
 */
function gravityforms_canonical_confirmations_warning(array $confirmations): ?array
{
    if (count($confirmations) === 0) {
        return null;
    }
    $needs_warning = count($confirmations) > 1;
    // @mago-expect analysis:mixed-assignment
    foreach ($confirmations as $confirmation) {
        if (!is_array($confirmation)) {
            continue;
        }
        $type = isset($confirmation['type']) && is_string($confirmation['type']) ? $confirmation['type'] : 'message';
        if ($type !== 'message') {
            $needs_warning = true;
            break;
        }
    }
    if (!$needs_warning) {
        return null;
    }
    return [
        'path' => 'meta.gravityforms_confirmations',
        'reason' => sprintf(
            'Gravity Forms ships %d confirmation(s) including non-message types (page / redirect) — canonical has no first-class confirmation block, so the full set is echoed to meta.gravityforms_confirmations for round-trip but the target plugin needs to be configured manually.',
            count($confirmations),
        ),
        'severity' => 'informational',
    ];
}

/**
 * Builds the canonical `meta{}` block. Carries source plugin and version;
 * echoes gravityforms_confirmations and a slim gravityforms_form_settings
 * snapshot for lossless round-trip on a gravityforms-to-gravityforms
 * migration.
 *
 * @param array<array-key, mixed> $confirmations
 * @param array<string, mixed>    $settings
 * @return array<string, mixed>
 */
function gravityforms_canonical_build_meta(string $version, array $confirmations, array $settings): array
{
    return [
        'source_plugin' => 'gravityforms',
        'source_version' => $version,
        'gravityforms_confirmations' => $confirmations,
        'gravityforms_form_settings' => $settings,
    ];
}

/**
 * Builds the warning for a GF field type the canonical map does not cover.
 * Distinct from skipped types — an unmapped type is the safety net for
 * field types registered by future GF releases or add-ons.
 *
 * @return array<string, string>
 */
function gravityforms_canonical_unmapped_warning(string $gf_type, int $field_index): array
{
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => sprintf(
            'Gravity Forms [%s] field type is not yet mapped to the canonical vocabulary — dropped from canonical fields[]. Report this to the integration maintainer; the type may have been added by a recent GF release or add-on.',
            $gf_type,
        ),
        'severity' => 'informational',
    ];
}

/**
 * Boolean coercion matching the wpforms / cf7 helpers. GF stores booleans
 * as native bool plus the occasional "1" / "" string variant in the JSON
 * blob.
 */
function gravityforms_canonical_truthy(mixed $value): bool
{
    if (in_array($value, [null, false, 0, 0.0, '', '0', 'false', 'False', 'FALSE'], strict: true)) {
        return false;
    }
    return true;
}

/**
 * Truncates a string at a byte cap, mirroring `cf7_truncate_field()` and
 * `wpforms_canonical_truncate()`. Used on mail / notification bodies so
 * the canonical response stays under control even when the source form
 * ships a multi-kilobyte body.
 *
 * @return array{value: string, truncated: bool, original_bytes: int}
 */
function gravityforms_canonical_truncate(string $value, int $max_bytes): array
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
