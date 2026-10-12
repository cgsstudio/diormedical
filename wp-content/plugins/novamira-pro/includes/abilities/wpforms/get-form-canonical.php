<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\WPForms;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/wpforms-get-form-canonical', [
    'label' => __('Get WPForms Form (Canonical)', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single WPForms form by id and returns it mapped to the plugin-agnostic form-model-schema vocabulary (17 canonical types: text/email/tel/url/number/textarea/date/time/datetime/select/radio/checkbox/file/hidden/submit/consent/group) used by the form-model-migration skill. The 40 WPForms native field types fold onto those 17 — composites (`name`, `address`) collapse to canonical `group` with sub-fields; Pro-only types without a canonical equivalent (`signature`, `rating`, `credit-card`, `payment-*`, `custom-captcha`, `entry-preview`, `layout`) emit `warnings[]` with severity. Output keys: `fields[]` with canonical types, `mail_config{}` from the first notification, `notifications[]` from the remaining ones, `messages{}` (empty — WPForms has no built-in message catalogue), `conditional_rules[]` from per-field WPForms conditionals (mapped 1:1 when expressible), `meta{}` (source plugin + version + edition + confirmations / settings echo for round-trip), plus `warnings[]` for everything that does not fit the canonical shape (multi-page forms, complex conditional logic, payment + signature + rating fields, anti-spam configuration, WPForms `{smart_tags}` that have no `[field-name]` equivalent). Honours the same `max_field_bytes` truncation contract as `wpforms-get-form` (default 8192 bytes for `mail_config.body`, `notifications[].body`, with `*_truncated` flags); pass `max_field_bytes: 0` to disable truncation when full-fidelity canonical is required before an execute-mode migration. Returns WP_Error `wpforms_form_not_found` when the id does not exist or refers to a trashed / auto-draft / non-WPForms post. Use this ability inside the form-model-migration workflow (Phase 2 — read source schema); for ad-hoc inspection outside the migration pipeline, prefer `wpforms-get-form` which returns the WPForms-native shape.',
        domain: 'novamira-pro',
    ),
    'category' => 'wpforms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'WPForms form id (the post id of the wpforms CPT row).',
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
    'execute_callback' => 'Novamira\\Pro\\Abilities\\WPForms\\wpforms_get_form_canonical',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use as Phase 2 (read source) of the form-model-migration workflow when WPForms is the source plugin. The output is the canonical form-model-schema document directly — feed it into Phase 3 (translate) as-is; warnings[] already enumerates structural / informational losses (composite fields, Pro types without canonical equivalent, multi-page, complex conditionals). Pass max_field_bytes:0 before an execute-mode migration so canonical bodies are not truncated.',
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
function wpforms_get_form_canonical(array $input): array|WP_Error
{
    if (!wpforms_detect_setup()['active']) {
        return wpforms_not_active();
    }

    $form = wpforms_form_or_error($input['form_id']);
    if ($form instanceof WP_Error) {
        return $form;
    }

    // Per-form read capability — mirrors the gate added by Round 1 audit
    // to wpforms-get-form. Without this the global novamira_permission_callback
    // would let a low-cap user pull canonical of a form they cannot view.
    $cap_error = wpforms_capability_or_error('wpforms_view_forms', (int) $form->ID);
    if ($cap_error !== null) {
        return $cap_error;
    }

    $decoded = wpforms_decode_form($form);
    if ($decoded instanceof WP_Error) {
        return $decoded;
    }

    $max_field_bytes = isset($input['max_field_bytes']) ? max(0, $input['max_field_bytes']) : 8192;

    return wpforms_canonical_compose($form, $decoded, $max_field_bytes);
}

/**
 * Builds the canonical document from the resolved post, the decoded
 * WPForms blob, and the byte cap. Split out of the entry point so the
 * top-level callback stays small and the translation pipeline is
 * trivially testable in isolation.
 *
 * @param array<string, mixed> $decoded
 * @return array<string, mixed>
 */
function wpforms_canonical_compose(WP_Post $form, array $decoded, int $max_field_bytes): array
{
    $wp_fields_raw = is_array($decoded['fields'] ?? null) ? $decoded['fields'] : [];
    $notifications_raw = is_array($decoded['notifications'] ?? null) ? $decoded['notifications'] : [];
    $confirmations_raw = is_array($decoded['confirmations'] ?? null) ? $decoded['confirmations'] : [];
    /** @var array<string, mixed> $settings_raw */
    $settings_raw = is_array($decoded['settings'] ?? null) ? $decoded['settings'] : [];

    $field_name_map = wpforms_canonical_build_field_name_map($wp_fields_raw);
    $detection = wpforms_detect_setup();
    $edition = $detection['pro'] === true ? 'pro' : 'lite';
    $version = is_string($detection['version']) ? $detection['version'] : '';

    $fields_result = wpforms_canonical_translate_fields($wp_fields_raw, $field_name_map, $edition);
    $primary_id = wpforms_canonical_primary_notification_id($notifications_raw);
    $mail_config = wpforms_canonical_translate_mail_config(
        $notifications_raw[$primary_id] ?? null,
        $field_name_map,
        $max_field_bytes,
    );
    $notifications = wpforms_canonical_translate_notifications(
        $notifications_raw,
        $primary_id,
        $field_name_map,
        $max_field_bytes,
    );
    $conditional_rules = wpforms_canonical_collect_conditional_rules($wp_fields_raw, $field_name_map);
    $form_warnings = wpforms_canonical_form_warnings($wp_fields_raw, $settings_raw, $confirmations_raw);

    return [
        'form_id' => (int) $form->ID,
        'title' => $form->post_title,
        'slug' => $form->post_name,
        'locale' => '',
        'fields' => $fields_result['fields'],
        'mail_config' => $mail_config,
        'notifications' => $notifications,
        'messages' => (object) [],
        'conditional_rules' => $conditional_rules,
        'meta' => wpforms_canonical_build_meta($version, $edition, $confirmations_raw, $settings_raw),
        'warnings' => array_merge($fields_result['warnings'], $form_warnings),
    ];
}

/**
 * Builds the field-id → canonical-name lookup the translator uses to
 * rewrite `{field_id="N"}` smart tags into the `[field-name]` canonical
 * convention. WPForms field ids are stable strings of digits in the
 * stored blob; canonical names come from sanitize_title(label) and fall
 * back to `field-<id>` when the label is empty.
 *
 * @param array<array-key, mixed> $wp_fields
 * @return array<string, string>
 */
function wpforms_canonical_build_field_name_map(array $wp_fields): array
{
    $map = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($wp_fields as $id => $field) {
        if (!is_array($field)) {
            continue;
        }
        $id_str = (string) $id;
        $label = isset($field['label']) && is_string($field['label']) ? $field['label'] : '';
        $name = $label !== '' ? sanitize_title($label) : '';
        if ($name === '') {
            $name = 'field-' . $id_str;
        }
        $map[$id_str] = $name;
    }
    return $map;
}

/**
 * Picks the WPForms notification id to surface as the canonical
 * `mail_config{}`. WPForms keys notifications by string-int starting
 * at "1" — that one is usually the admin notification. We pick the
 * lowest-numbered id present so the result is deterministic regardless
 * of how the form was authored.
 *
 * @param array<array-key, mixed> $notifications
 */
function wpforms_canonical_primary_notification_id(array $notifications): string
{
    if ($notifications === []) {
        return '';
    }
    $keys = array_map('strval', array_keys($notifications));
    sort($keys, flags: SORT_NATURAL);
    return $keys[0];
}

/**
 * Translates all WPForms fields into canonical fields[], accumulating
 * warnings for losses (Pro-only types without canonical equivalent,
 * structural / page-break fields, complex conditional logic).
 *
 * @param array<array-key, mixed> $wp_fields
 * @param array<string, string>    $field_name_map
 * @return array{fields: list<array<string, mixed>>, warnings: list<array<string, string>>}
 */
function wpforms_canonical_translate_fields(array $wp_fields, array $field_name_map, string $edition): array
{
    /** @var list<array<string, mixed>> $fields */
    $fields = [];
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    $index = 0;
    // @mago-expect analysis:mixed-assignment
    foreach ($wp_fields as $id => $wp_field) {
        if (!is_array($wp_field)) {
            continue;
        }
        $result = wpforms_canonical_translate_field($wp_field, (string) $id, $index, $field_name_map, $edition);
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
 * Maps a single WPForms field to canonical. Returns null when the
 * field has no canonical representation at all (divider, html, content,
 * pagebreak, entry-preview, layout, payment-*, signature, rating,
 * credit-card, custom-captcha, internal-information). In those cases
 * the warnings list carries the explanation for the user.
 *
 * @param array<array-key, mixed> $wp_field
 * @param array<string, string>   $field_name_map
 * @return array{field: array<string, mixed>|null, warnings: list<array<string, string>>}
 */
function wpforms_canonical_translate_field(
    array $wp_field,
    string $wp_id,
    int $field_index,
    array $field_name_map,
    string $edition,
): array {
    $wp_type = isset($wp_field['type']) && is_string($wp_field['type']) ? $wp_field['type'] : '';
    $name = $field_name_map[$wp_id] ?? 'field-' . $wp_id;

    if (wpforms_canonical_field_is_skipped($wp_type)) {
        return [
            'field' => null,
            'warnings' => [wpforms_canonical_skip_warning($wp_type, $field_index)],
        ];
    }

    $canonical_type = wpforms_canonical_type_map($wp_type);
    if ($canonical_type === null) {
        return [
            'field' => null,
            'warnings' => [wpforms_canonical_unmapped_warning($wp_type, $field_index)],
        ];
    }

    $field = wpforms_canonical_field_payload($wp_field, $wp_type, $name, $canonical_type);
    $warnings = wpforms_canonical_field_warnings($wp_field, $wp_type, $field_index, $edition);

    return ['field' => $field, 'warnings' => $warnings];
}

/**
 * Returns true when the WPForms field type has no canonical role (pure
 * layout / metadata / UI-only / out-of-scope) and should be dropped from
 * fields[] with a single informational or structural warning.
 */
function wpforms_canonical_field_is_skipped(string $wp_type): bool
{
    return in_array(
        $wp_type,
        [
            'divider',
            'html',
            'content',
            'pagebreak',
            'internal-information',
            'entry-preview',
            'layout',
            'rating',
            'signature',
            'credit-card',
            'custom-captcha',
            'payment-single',
            'payment-multiple',
            'payment-checkbox',
            'payment-select',
            'payment-total',
        ],
        strict: true,
    );
}

/**
 * Builds the warning entry for a skipped WPForms field. Severity is
 * `structural` when the field has semantic weight (payment / signature /
 * multi-page / anti-spam) and `informational` when it is pure rendering
 * (divider, html, content, internal information, entry-preview, layout).
 *
 * @return array<string, string>
 */
function wpforms_canonical_skip_warning(string $wp_type, int $field_index): array
{
    $structural = [
        'pagebreak',
        'rating',
        'signature',
        'credit-card',
        'custom-captcha',
        'payment-single',
        'payment-multiple',
        'payment-checkbox',
        'payment-select',
        'payment-total',
    ];
    $severity = in_array($wp_type, $structural, strict: true) ? 'structural' : 'informational';
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => sprintf(
            'WPForms [%s] field has no canonical equivalent — preserved as a warning for the migration plan; the target plugin must provide its own implementation.',
            $wp_type,
        ),
        'severity' => $severity,
    ];
}

/**
 * Maps a WPForms field type to its canonical form-model-schema type.
 * Returns null when the type is not skipped but does not map cleanly
 * (kept as a safety net — every type used in the wild should map).
 */
function wpforms_canonical_type_map(string $wp_type): ?string
{
    return match ($wp_type) {
        'text' => 'text',
        'textarea' => 'textarea',
        'select' => 'select',
        'checkbox' => 'checkbox',
        'radio' => 'radio',
        'name' => 'group',
        'email' => 'email',
        'number' => 'number',
        'number-slider' => 'number',
        'gdpr-checkbox' => 'consent',
        'hidden' => 'hidden',
        'address' => 'group',
        'date-time' => 'datetime',
        'phone' => 'tel',
        'richtext' => 'textarea',
        'url' => 'url',
        'file-upload', 'camera' => 'file',
        'password' => 'text',
        'repeater' => 'group',
        default => null,
    };
}

/**
 * Builds the canonical field array for the mapped WPForms field.
 * Pulls common keys (name / type / label / description / required /
 * placeholder / default / cardinality / choices / validation) and
 * carries WPForms-specific hints into meta for lossless round-trip.
 *
 * @param array<array-key, mixed> $wp_field
 * @return array<string, mixed>
 */
function wpforms_canonical_field_payload(array $wp_field, string $wp_type, string $name, string $canonical_type): array
{
    $field = [
        'name' => $name,
        'type' => $canonical_type,
    ];
    wpforms_canonical_apply_string_property($field, $wp_field, key: 'label');
    wpforms_canonical_apply_string_property($field, $wp_field, key: 'description');
    wpforms_canonical_apply_string_property($field, $wp_field, key: 'placeholder');
    wpforms_canonical_apply_default_value($field, $wp_field);
    // Canonical convention: consent fields are ALWAYS required — they
    // gate submission semantically, even if the source plugin allowed
    // an opt-out variant (CF7 [acceptance optional], WPForms gdpr-checkbox
    // without an explicit required flag). The opt-out hint, when present,
    // is preserved in meta via the per-field warning path.
    if ($canonical_type === 'consent' || wpforms_canonical_truthy($wp_field['required'] ?? null)) {
        $field['required'] = true;
    }
    wpforms_canonical_apply_cardinality($field, $wp_field, $wp_type, $canonical_type);
    wpforms_canonical_apply_choices($field, $wp_field, $canonical_type);
    wpforms_canonical_apply_validation($field, $wp_field, $wp_type, $canonical_type);
    wpforms_canonical_apply_subtype($field, $wp_field, $wp_type, $canonical_type);
    $field['meta'] = wpforms_canonical_field_meta($wp_field, $wp_type);

    return $field;
}

/**
 * Builds the canonical `meta{}` sub-block for a field. Carries the
 * original WPForms type (so round-trip can rebuild the field) plus any
 * non-canonical rendering hints (CSS classes, size). Split out of
 * `field_payload` to keep that function under Mago's cyclomatic
 * threshold.
 *
 * @param array<array-key, mixed> $wp_field
 * @return array<string, mixed>
 */
function wpforms_canonical_field_meta(array $wp_field, string $wp_type): array
{
    $meta = ['wpforms_field_type' => $wp_type];
    if (isset($wp_field['css']) && is_string($wp_field['css']) && $wp_field['css'] !== '') {
        $meta['wpforms_css'] = $wp_field['css'];
    }
    if (isset($wp_field['size']) && is_string($wp_field['size']) && $wp_field['size'] !== '') {
        $meta['wpforms_size'] = $wp_field['size'];
    }
    return $meta;
}

/**
 * Copies a simple string property from the WPForms field to the
 * canonical field when it is present and non-empty. Centralised so the
 * three optional string-shaped keys (label / description / placeholder)
 * share the same null / type / empty handling.
 *
 * @param array<string, mixed>    $field
 * @param array<array-key, mixed> $wp_field
 */
function wpforms_canonical_apply_string_property(array &$field, array $wp_field, string $key): void
{
    if (isset($wp_field[$key]) && is_string($wp_field[$key]) && $wp_field[$key] !== '') {
        $field[$key] = $wp_field[$key];
    }
}

/**
 * Carries the WPForms `default_value` to the canonical `default` key
 * when present and non-empty. Kept separate from generic strings so a
 * future numeric default (slider) can reuse the same insertion point.
 *
 * @param array<string, mixed>    $field
 * @param array<array-key, mixed> $wp_field
 */
function wpforms_canonical_apply_default_value(array &$field, array $wp_field): void
{
    if (
        isset($wp_field['default_value'])
        && is_string($wp_field['default_value'])
        && $wp_field['default_value'] !== ''
    ) {
        $field['default'] = $wp_field['default_value'];
    }
}

/**
 * Sets canonical `cardinality` for the field types where the value is
 * meaningful (select multiple, checkbox group, file-upload multi-file,
 * repeater). Leaves the key absent on scalar types.
 *
 * @param array<string, mixed>    $field
 * @param array<array-key, mixed> $wp_field
 */
function wpforms_canonical_apply_cardinality(
    array &$field,
    array $wp_field,
    string $wp_type,
    string $canonical_type,
): void {
    if ($canonical_type === 'radio') {
        $field['cardinality'] = 'single';
        return;
    }
    if ($wp_type === 'select') {
        $field['cardinality'] = wpforms_canonical_truthy($wp_field['multiple'] ?? null) ? 'multi' : 'single';
        return;
    }
    if ($wp_type === 'checkbox' && $canonical_type === 'checkbox') {
        $field['cardinality'] = 'multi';
        return;
    }
    if (in_array($wp_type, ['file-upload', 'camera'], strict: true)) {
        $max_files = isset($wp_field['max_file_number']) ? (int) $wp_field['max_file_number'] : 1;
        $field['cardinality'] = $max_files > 1 ? 'multi' : 'single';
        return;
    }
    if ($wp_type === 'repeater') {
        $field['cardinality'] = 'multi';
    }
}

/**
 * Translates WPForms `choices[]` into canonical `choices[{value,label}]`
 * for the choice-bearing types (select / checkbox / radio). WPForms
 * accepts `value` optional with `label` as fallback; canonical always
 * carries both keys.
 *
 * @param array<string, mixed>    $field
 * @param array<array-key, mixed> $wp_field
 */
function wpforms_canonical_apply_choices(array &$field, array $wp_field, string $canonical_type): void
{
    if (!in_array($canonical_type, ['select', 'checkbox', 'radio'], strict: true)) {
        return;
    }
    /** @var mixed $raw */
    $raw = $wp_field['choices'] ?? null;
    if (!is_array($raw) || $raw === []) {
        return;
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
    if ($choices !== []) {
        $field['choices'] = $choices;
    }
}

/**
 * Populates the canonical `validation` block from WPForms field-level
 * limits (min / max for number, accepts for file-upload, character /
 * word limits for text + textarea). Only the keys that make sense for
 * the source type are emitted — canonical schema ignores absent ones.
 *
 * @param array<string, mixed>    $field
 * @param array<array-key, mixed> $wp_field
 */
function wpforms_canonical_apply_validation(
    array &$field,
    array $wp_field,
    string $wp_type,
    string $canonical_type,
): void {
    $validation = array_merge(
        wpforms_canonical_number_validation($wp_field, $canonical_type),
        wpforms_canonical_file_validation($wp_field, $wp_type),
        wpforms_canonical_length_validation($wp_field),
    );
    if ($validation !== []) {
        $field['validation'] = $validation;
    }
}

/**
 * Extracts canonical `min` / `max` / `step` from a WPForms number /
 * number-slider field. Returns an empty array for non-number types.
 *
 * @param array<array-key, mixed> $wp_field
 * @return array<string, int|float>
 */
function wpforms_canonical_number_validation(array $wp_field, string $canonical_type): array
{
    if ($canonical_type !== 'number') {
        return [];
    }
    $out = [];
    if (isset($wp_field['min']) && is_numeric($wp_field['min'])) {
        $out['min'] = wpforms_canonical_to_number($wp_field['min']);
    }
    if (isset($wp_field['max']) && is_numeric($wp_field['max'])) {
        $out['max'] = wpforms_canonical_to_number($wp_field['max']);
    }
    if (isset($wp_field['step']) && is_numeric($wp_field['step'])) {
        $out['step'] = wpforms_canonical_to_number($wp_field['step']);
    }
    return $out;
}

/**
 * Extracts canonical `accepts[]` (MIME list) and `max_size_bytes` from a
 * WPForms file-upload / camera field. Returns an empty array for other
 * types so the caller can merge unconditionally.
 *
 * @param array<array-key, mixed> $wp_field
 * @return array<string, mixed>
 */
function wpforms_canonical_file_validation(array $wp_field, string $wp_type): array
{
    if (!in_array($wp_type, ['file-upload', 'camera'], strict: true)) {
        return [];
    }
    $out = [];
    $accepts = wpforms_canonical_parse_extensions($wp_field['extensions'] ?? '');
    if ($accepts !== []) {
        $out['accepts'] = $accepts;
    }
    if (isset($wp_field['max_size']) && is_numeric($wp_field['max_size'])) {
        $out['max_size_bytes'] = (int) round(floatval($wp_field['max_size']) * 1024 * 1024);
    }
    return $out;
}

/**
 * Extracts canonical `max_length` from a WPForms text / textarea field
 * that has the character-count limiter enabled. Word-count limits are
 * intentionally not mapped — canonical only knows about character
 * length.
 *
 * @param array<array-key, mixed> $wp_field
 * @return array<string, int>
 */
function wpforms_canonical_length_validation(array $wp_field): array
{
    if (!wpforms_canonical_truthy($wp_field['limit_enabled'] ?? null)) {
        return [];
    }
    $limit_count = isset($wp_field['limit_count']) ? (int) $wp_field['limit_count'] : 0;
    $limit_mode = isset($wp_field['limit_mode']) && is_string($wp_field['limit_mode'])
        ? $wp_field['limit_mode']
        : 'characters';
    if ($limit_count <= 0 || $limit_mode !== 'characters') {
        return [];
    }
    return ['max_length' => $limit_count];
}

/**
 * Casts a numeric-looking WPForms value (always a string in the JSON
 * blob) to its native int / float so canonical consumers don't have to
 * re-parse `"5"` from a min/max/step entry. Returns the original when
 * the parse fails (defensive fallback — won't happen with is_numeric()).
 */
function wpforms_canonical_to_number(mixed $value): int|float
{
    if (!is_numeric($value)) {
        return 0;
    }
    $string = (string) $value;
    return str_contains($string, '.') ? floatval($string) : (int) $string;
}

/**
 * Parses the WPForms `extensions` comma-separated list (e.g. `pdf,jpg`)
 * into a canonical MIME-type array. Unknown extensions are dropped.
 *
 * @return list<string>
 */
function wpforms_canonical_parse_extensions(mixed $raw): array
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
 * Folds WPForms type-specific specialisers (password format, number
 * subtype, date-time variant) into the canonical field. Canonical's
 * `format` / `subtype` modifiers carry the nuance that would otherwise
 * be lost when collapsing 40 WPForms types onto the 17 canonical ones.
 *
 * @param array<string, mixed>    $field
 * @param array<array-key, mixed> $wp_field
 */
// @mago-expect lint:no-insecure-comparison
function wpforms_canonical_apply_subtype(array &$field, array $wp_field, string $wp_type, string $canonical_type): void
{
    if ($wp_type === 'password' && $canonical_type === 'text') {
        $field['format'] = 'password';
        return;
    }
    if ($wp_type === 'richtext' && $canonical_type === 'textarea') {
        $field['format'] = 'rich';
        return;
    }
    if ($wp_type === 'number-slider' && $canonical_type === 'number') {
        $field['subtype'] = 'integer';
        return;
    }
    if ($wp_type === 'number' && $canonical_type === 'number') {
        $field['subtype'] = 'decimal';
        return;
    }
    if ($wp_type === 'date-time') {
        $format = isset($wp_field['format']) && is_string($wp_field['format']) ? $wp_field['format'] : 'date-time';
        $field['type'] = match ($format) {
            'date' => 'date',
            'time' => 'time',
            default => 'datetime',
        };
    }
}

/**
 * Generates per-field migration warnings the type translator alone
 * cannot surface: composite collapse (name / address), Pro-only fields
 * on a Lite install (when the target downgrades), conditional logic
 * presence, smart-tag references in default_value / placeholder.
 *
 * @param array<array-key, mixed> $wp_field
 * @return list<array<string, string>>
 */
function wpforms_canonical_field_warnings(array $wp_field, string $wp_type, int $field_index, string $edition): array
{
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    if (in_array($wp_type, ['name', 'address'], strict: true)) {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => sprintf(
                'WPForms composite [%s] collapsed to canonical group — sub-field structure (first/middle/last for name; address1/address2/city/state/postal/country for address) is plugin-specific and will need re-creation on the target.',
                $wp_type,
            ),
            'severity' => 'informational',
        ];
    }
    if (wpforms_canonical_pro_only_type($wp_type) && $edition !== 'pro') {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => sprintf(
                'WPForms [%s] is a Pro field type but the current install is Lite — schema preserved canonically but the target install must also be Pro for round-trip.',
                $wp_type,
            ),
            'severity' => 'informational',
        ];
    }
    if (wpforms_canonical_truthy($wp_field['conditional_logic'] ?? null)) {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => 'WPForms conditional logic is mapped to canonical conditional_rules[] when expressible — complex nested AND/OR rules or value-derived triggers are flattened with possible semantic loss.',
            'severity' => 'informational',
        ];
    }

    return $warnings;
}

/**
 * Returns true when a WPForms field type is gated behind the Pro
 * edition. Used to add an informational warning so a Lite-to-Pro
 * migration plan can surface the gate to the user.
 */
function wpforms_canonical_pro_only_type(string $wp_type): bool
{
    return in_array(
        $wp_type,
        [
            'address',
            'date-time',
            'phone',
            'richtext',
            'url',
            'file-upload',
            'camera',
            'password',
            'repeater',
        ],
        strict: true,
    );
}

/**
 * Translates the first WPForms notification (the "admin" mail by
 * convention) into the canonical `mail_config{}`. When no notification
 * is present the canonical still emits the block with empty values so
 * downstream consumers don't have to special-case it.
 *
 * @param mixed                 $notification
 * @param array<string, string> $field_name_map
 * @return array<string, mixed>
 */
function wpforms_canonical_translate_mail_config(
    mixed $notification,
    array $field_name_map,
    int $max_field_bytes,
): array {
    if (!is_array($notification)) {
        return wpforms_canonical_empty_mail_config();
    }
    return wpforms_canonical_notification_to_canonical($notification, $field_name_map, $max_field_bytes);
}

/**
 * Translates every WPForms notification EXCEPT the primary one into
 * canonical notifications[]. Each entry carries `name` (the WPForms
 * notification_name), `active: true`, and the same mail-config keys
 * the primary uses.
 *
 * @param array<array-key, mixed> $notifications
 * @param array<string, string>    $field_name_map
 * @return list<array<string, mixed>>
 */
function wpforms_canonical_translate_notifications(
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
        $entry = wpforms_canonical_notification_to_canonical($notification, $field_name_map, $max_field_bytes);
        $entry = array_merge([
            'name' => wpforms_canonical_notification_name($notification, (string) $id),
            'active' => true,
        ], $entry);
        $out[] = $entry;
    }
    return $out;
}

/**
 * Returns the canonical notification name. WPForms keys notifications
 * by numeric string ("1", "2") but each carries a human label in
 * `notification_name`; fall back to `notification-<id>` when missing.
 *
 * @param array<array-key, mixed> $notification
 */
function wpforms_canonical_notification_name(array $notification, string $id): string
{
    if (
        isset($notification['notification_name'])
        && is_string($notification['notification_name'])
        && $notification['notification_name'] !== ''
    ) {
        $slug = sanitize_title($notification['notification_name']);
        if ($slug !== '') {
            return $slug;
        }
    }
    return 'notification-' . $id;
}

/**
 * Translates a single WPForms notification array into the canonical
 * mail-config shape (shared by `mail_config{}` and each
 * `notifications[]` entry). `{field_id="N"}` smart tags inside the
 * body and the subject are rewritten to `[field-name]` so the
 * canonical document round-trips cleanly with the CF7 reader.
 *
 * @param array<array-key, mixed> $notification
 * @param array<string, string>   $field_name_map
 * @return array<string, mixed>
 */
function wpforms_canonical_notification_to_canonical(
    array $notification,
    array $field_name_map,
    int $max_field_bytes,
): array {
    $body_raw = wpforms_canonical_normalize_smart_tags(
        isset($notification['message']) && is_string($notification['message']) ? $notification['message'] : '',
        $field_name_map,
    );
    $body_truncated = wpforms_canonical_truncate($body_raw, $max_field_bytes);

    return [
        'subject' => wpforms_canonical_normalize_smart_tags(
            isset($notification['subject']) && is_string($notification['subject']) ? $notification['subject'] : '',
            $field_name_map,
        ),
        'sender' => isset($notification['sender_address']) && is_string($notification['sender_address'])
            ? $notification['sender_address']
            : '',
        'recipient' => wpforms_canonical_normalize_smart_tags(
            isset($notification['email']) && is_string($notification['email']) ? $notification['email'] : '',
            $field_name_map,
        ),
        'body' => $body_truncated['value'],
        'body_truncated' => $body_truncated['truncated'],
        'body_bytes' => $body_truncated['original_bytes'],
        'additional_headers' => '',
        'attachments' => [],
        'use_html' => true,
        'exclude_blank' => false,
    ];
}

/**
 * Returns an empty canonical mail-config block. Kept as a helper so the
 * empty-notification path and the "no notification at all" path always
 * agree on the shape.
 *
 * @return array<string, mixed>
 */
function wpforms_canonical_empty_mail_config(): array
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
 * Rewrites WPForms `{field_id="N"}` smart tags to the canonical
 * `[field-name]` convention (which matches the CF7 syntax). Other
 * WPForms smart tags (`{admin_email}`, `{site_name}`, `{all_fields}`,
 * `{date}`, `{user_ip}`, etc.) are preserved verbatim — the caller is
 * expected to add an informational warning.
 *
 * @param array<string, string> $field_name_map
 */
function wpforms_canonical_normalize_smart_tags(string $input, array $field_name_map): string
{
    if ($input === '' || $field_name_map === []) {
        return $input;
    }
    $rewritten = preg_replace_callback(
        pattern: '/\{field_id\s*=\s*"(\d+)"\}/',
        callback: static function (array $match) use ($field_name_map): string {
            $id = $match[1] ?? '';
            $name = $field_name_map[$id] ?? '';
            return $name !== '' ? '[' . $name . ']' : $match[0];
        },
        subject: $input,
    );
    // Defence: preg_replace_callback returns null on a PCRE error
    // (PREG_BACKTRACK_LIMIT_ERROR on very long bodies with many tags,
    // OOM mid-callback). Casting null to string would silently zero a
    // perfectly good mail body. Fall back to the original input so the
    // canonical preserves the smart tags verbatim — agents can still
    // resolve them manually on the target side.
    return $rewritten ?? $input;
}

/**
 * Collects canonical conditional_rules[] from per-field WPForms
 * conditionals. Maps `target_field_id` and `conditions[].field` from
 * numeric ids to canonical field names. Complex nested rules that
 * cannot be expressed as a single rule with `all|any` logic are
 * dropped (the parent field already emitted an informational warning).
 *
 * @param array<array-key, mixed> $wp_fields
 * @param array<string, string>    $field_name_map
 * @return list<array<string, mixed>>
 */
function wpforms_canonical_collect_conditional_rules(array $wp_fields, array $field_name_map): array
{
    /** @var list<array<string, mixed>> $rules */
    $rules = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($wp_fields as $id => $wp_field) {
        if (!is_array($wp_field) || !wpforms_canonical_truthy($wp_field['conditional_logic'] ?? null)) {
            continue;
        }
        $conditionals = isset($wp_field['conditionals']) && is_array($wp_field['conditionals'])
            ? $wp_field['conditionals']
            : [];
        $target_name = $field_name_map[(string) $id] ?? '';
        // @mago-expect analysis:mixed-assignment
        foreach ($conditionals as $conditional) {
            if (!is_array($conditional)) {
                continue;
            }
            $rule = wpforms_canonical_conditional_to_rule($conditional, $target_name, $field_name_map);
            if ($rule !== null) {
                $rules[] = $rule;
            }
        }
    }
    return $rules;
}

/**
 * Translates one WPForms conditional entry into a canonical rule
 * `{target_field, operator, logic, conditions[]}`. Returns null when
 * the entry has no parseable conditions.
 *
 * @param array<array-key, mixed> $conditional
 * @param array<string, string>   $field_name_map
 * @return array<string, mixed>|null
 */
function wpforms_canonical_conditional_to_rule(array $conditional, string $target_name, array $field_name_map): ?array
{
    if ($target_name === '') {
        return null;
    }
    $raw_conditions = isset($conditional['conditions']) && is_array($conditional['conditions'])
        ? $conditional['conditions']
        : [];
    $conditions = wpforms_canonical_translate_conditions($raw_conditions, $field_name_map);
    if ($conditions === []) {
        return null;
    }
    return [
        'target_field' => $target_name,
        'operator' => isset($conditional['operator']) && is_string($conditional['operator'])
            ? $conditional['operator']
            : 'show_if',
        'logic' => isset($conditional['logic']) && is_string($conditional['logic']) ? $conditional['logic'] : 'all',
        'conditions' => $conditions,
    ];
}

/**
 * Translates the inner `conditions[]` array of one WPForms conditional
 * (each entry references a field by numeric id) into canonical-named
 * conditions. Rows whose `field` does not resolve to a known canonical
 * name are dropped silently — the parent rule is allowed to survive on
 * the remaining valid conditions.
 *
 * @param array<array-key, mixed> $raw_conditions
 * @param array<string, string>   $field_name_map
 * @return list<array<string, string>>
 */
function wpforms_canonical_translate_conditions(array $raw_conditions, array $field_name_map): array
{
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw_conditions as $row) {
        if (!is_array($row)) {
            continue;
        }
        $field_id = isset($row['field']) ? (string) $row['field'] : '';
        $field_name = $field_name_map[$field_id] ?? '';
        if ($field_name === '') {
            continue;
        }
        $out[] = [
            'field' => $field_name,
            'op' => isset($row['op']) && is_string($row['op']) ? $row['op'] : 'equals',
            'value' => isset($row['value']) && is_string($row['value']) ? $row['value'] : '',
        ];
    }
    return $out;
}

/**
 * Form-level warnings the field-level translator cannot see: multi-page
 * forms (any pagebreak), anti-spam config in settings, confirmation
 * surfaces that have no canonical equivalent. Each check lives in its
 * own helper so the cyclomatic complexity stays under Mago's threshold
 * and a new check (Pro-only setting, e.g.) plugs in cleanly.
 *
 * @param array<array-key, mixed> $wp_fields
 * @param array<string, mixed>    $settings
 * @param array<array-key, mixed> $confirmations
 * @return list<array<string, string>>
 */
function wpforms_canonical_form_warnings(array $wp_fields, array $settings, array $confirmations): array
{
    $warnings = [];
    $multipage = wpforms_canonical_multipage_warning($wp_fields);
    if ($multipage !== null) {
        $warnings[] = $multipage;
    }
    $antispam = wpforms_canonical_antispam_warning($settings);
    if ($antispam !== null) {
        $warnings[] = $antispam;
    }
    $confirm = wpforms_canonical_confirmations_warning($confirmations);
    if ($confirm !== null) {
        $warnings[] = $confirm;
    }
    return $warnings;
}

/**
 * Detects multi-page forms (any pagebreak field) and returns the
 * canonical structural warning. Returns null when the form is a
 * single page.
 *
 * @param array<array-key, mixed> $wp_fields
 * @return array<string, string>|null
 */
function wpforms_canonical_multipage_warning(array $wp_fields): ?array
{
    // @mago-expect analysis:mixed-assignment
    foreach ($wp_fields as $wp_field) {
        if (!is_array($wp_field)) {
            continue;
        }
        if (($wp_field['type'] ?? '') !== 'pagebreak') {
            continue;
        }
        return [
            'path' => 'fields',
            'reason' => 'WPForms multi-page form structure (one or more pagebreak fields) collapsed to a single-page canonical — the target plugin will receive a flat list of fields.',
            'severity' => 'structural',
        ];
    }
    return null;
}

/**
 * Detects the WPForms anti-spam token or honeypot toggle in settings
 * and returns the informational warning. Returns null when neither is
 * enabled.
 *
 * @param array<string, mixed> $settings
 * @return array<string, string>|null
 */
function wpforms_canonical_antispam_warning(array $settings): ?array
{
    $enabled =
        wpforms_canonical_truthy($settings['anti_spam'] ?? null)
        || wpforms_canonical_truthy($settings['honeypot'] ?? null);
    if (!$enabled) {
        return null;
    }
    return [
        'path' => 'meta.wpforms_form_settings.anti_spam',
        'reason' => 'WPForms anti-spam token / honeypot is enabled — anti-spam logic is plugin-specific and must be reconfigured on the target.',
        'severity' => 'informational',
    ];
}

/**
 * Detects multiple confirmation blocks and returns the informational
 * warning that the full set is echoed to meta but must be re-configured
 * on the target. Returns null when the form ships <= 1 confirmation.
 *
 * @param array<array-key, mixed> $confirmations
 * @return array<string, string>|null
 */
function wpforms_canonical_confirmations_warning(array $confirmations): ?array
{
    if (count($confirmations) <= 1) {
        return null;
    }
    return [
        'path' => 'meta.wpforms_confirmations',
        'reason' => sprintf(
            'WPForms form ships %d confirmations — canonical has no first-class confirmation block, so the full set is echoed to meta.wpforms_confirmations for round-trip but the target plugin needs to be configured manually if more than one matters.',
            count($confirmations),
        ),
        'severity' => 'informational',
    ];
}

/**
 * Builds the canonical `meta{}` block. Always carries source plugin
 * and version + edition; echoes wpforms_confirmations and a slim
 * wpforms_form_settings snapshot (excluding notifications + confirmations,
 * which are already promoted) for lossless round-trip on a CF7-style
 * `wpforms-to-wpforms` migration.
 *
 * @param array<array-key, mixed> $confirmations
 * @param array<string, mixed>     $settings
 * @return array<string, mixed>
 */
function wpforms_canonical_build_meta(string $version, string $edition, array $confirmations, array $settings): array
{
    $slim_settings = $settings;
    // Drop notifications + confirmations (already promoted to top-level
    // mail_config / notifications / meta.wpforms_confirmations) and the
    // payments block, which on WPForms Pro can carry sender-side PII
    // (paypal_email, stripe_connection_id) that the canonical consumer
    // never needs. Defence-in-depth: secret API keys are NOT stored in
    // the form blob (WPForms keeps them at the plugin level), but
    // dropping `payments` keeps the canonical output minimal.
    unset($slim_settings['notifications'], $slim_settings['confirmations'], $slim_settings['payments']);
    return [
        'source_plugin' => 'wpforms',
        'source_version' => $version,
        'edition' => $edition,
        'wpforms_confirmations' => $confirmations,
        'wpforms_form_settings' => $slim_settings,
    ];
}

/**
 * Builds the warning for a WPForms field type the canonical map does
 * not cover. Distinct from skipped types (which the agent KNOWS will
 * not survive) — an unmapped type is the safety net for new releases.
 *
 * @return array<string, string>
 */
function wpforms_canonical_unmapped_warning(string $wp_type, int $field_index): array
{
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => sprintf(
            'WPForms [%s] field type is not yet mapped to the canonical vocabulary — dropped from canonical fields[]. Report this to the integration maintainer; the type may have been added in a recent WPForms release.',
            $wp_type,
        ),
        'severity' => 'informational',
    ];
}

/**
 * Boolean coercion matching the wpforms-side helper. WPForms stores
 * booleans as `"1"` / `""` in the JSON blob so PHP's native cast would
 * trip on the string variants. Mirrors `cf7_truthy()` on the CF7
 * side so canonical helpers share their truthiness contract.
 */
function wpforms_canonical_truthy(mixed $value): bool
{
    if (in_array($value, [null, false, 0, 0.0, '', '0', 'false', 'False', 'FALSE'], strict: true)) {
        return false;
    }
    return true;
}

/**
 * Truncates a string at a byte cap, mirroring `cf7_truncate_field()`.
 * Used on mail / notification bodies so the canonical response stays
 * under control even when the source form ships a multi-kilobyte body.
 *
 * @return array{value: string, truncated: bool, original_bytes: int}
 */
function wpforms_canonical_truncate(string $value, int $max_bytes): array
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
