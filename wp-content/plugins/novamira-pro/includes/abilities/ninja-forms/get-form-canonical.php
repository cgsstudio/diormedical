<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\NinjaForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/ninja-forms-get-form-canonical', [
    'label' => __('Get Ninja Forms Form (Canonical)', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Ninja Forms form by id and returns it mapped to the plugin-agnostic form-model-schema vocabulary (17 canonical types: text/email/tel/url/number/textarea/date/time/datetime/select/radio/checkbox/file/hidden/submit/consent/group) used by the form-model-migration skill. Reads the form through the Novamira-native Ninja Forms model layer (the same source as ninja-forms-get-form), then folds the Ninja field shape onto the 17 canonical types: composites (`firstname` + `lastname` flat, `address` + `address2` + `city` + `zip` flat) emit `warnings[]` because Ninja does not nest them; native list types (`listcheckbox`, `listradio`, `listselect`, `listmultiselect`, `listcountry`, `liststate`, `listimage`, `listmodifier`) map to canonical `select` / `radio` / `checkbox` with cardinality; Ninja-specific UI types without a canonical equivalent (`starrating`, `signature`, `quantity`, `product`, `shipping`, `total`, `creditcard*`) emit `warnings[]` with severity `structural`; anti-spam types (`hcaptcha`, `recaptcha`, `recaptchav3`, `turnstile`, `spam`, `timedsubmit`) emit `warnings[]` with severity `informational`. Output keys: `fields[]` with canonical types, `mail_config{}` from the first email-action (the first `actions[]` entry with `type: "email"`), `notifications[]` from the remaining email-actions, `messages{}` from the form-level success / error / spam strings, `meta{}` (source plugin + version + edition + non-email actions echo for round-trip), plus `warnings[]` for everything that does not fit the canonical shape (composite collapse, Pro / spam fields, redirect actions, save-action, multi-action forms). Honours the `max_field_bytes` truncation contract (default 8192 bytes for `mail_config.body`, `notifications[].body`, with `*_truncated` flags); pass `max_field_bytes: 0` to disable truncation when full-fidelity canonical is required before an execute-mode migration. Returns WP_Error `ninjaforms_form_not_found` when the id does not exist. Use this ability inside the form-model-migration workflow (Phase 2 — read source schema); for ad-hoc inspection of the Ninja-native shape, use ninja-forms-get-form (include=["fields","actions","settings"]).',
        domain: 'novamira-pro',
    ),
    'category' => 'ninja-forms',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Ninja Forms form id (the NF3 wp_nf3_forms primary key).',
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
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\NinjaForms\\ninja_forms_get_form_canonical',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use as Phase 2 (read source) of the form-model-migration workflow when Ninja Forms is the source plugin. The output is the canonical form-model-schema document directly — feed it into Phase 3 (translate) as-is; warnings[] already enumerates structural / informational losses (Ninja-specific UI types, anti-spam, composite flat-fields, redirect / save / webhook actions). For WRITE operations use the Novamira-native ninja-forms-* abilities (create-form / edit-form / add-field / create-notification / …). Pass max_field_bytes:0 before an execute-mode migration so canonical bodies are not truncated.',
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
function ninja_forms_get_form_canonical(array $input): array|WP_Error
{
    if (!ninja_forms_runtime_available()) {
        return ninja_forms_error('ninjaforms_not_active', __(
            'Ninja Forms is inactive or below the minimum supported version. Call `ninja-forms-check-setup` for details.',
            domain: 'novamira-pro',
        ));
    }
    // Defense-in-depth capability gate (mirrors every other data-reading
    // ability): the canonical reader exposes the full form — fields, actions,
    // redacted secrets — so a non-admin must be refused here too, not only at
    // the MCP permission_callback. Round-2 audit SEC finding.
    $cap = ninja_forms_capability_or_error();
    if ($cap instanceof WP_Error) {
        return $cap;
    }

    $form_id = $input['form_id'];
    if ($form_id <= 0) {
        return ninja_forms_error('ninjaforms_invalid_input', __(
            'form_id must be a positive integer.',
            domain: 'novamira-pro',
        ));
    }
    $max_field_bytes = ninja_forms_resolve_max_field_bytes($input);

    $form_raw = ninja_forms_canonical_read_form($form_id);
    if ($form_raw === null) {
        return ninja_forms_error('ninjaforms_form_not_found', sprintf(
            /* translators: %d: Ninja Forms form id. */
            __('Ninja Forms form with id %d not found.', domain: 'novamira-pro'),
            $form_id,
        ));
    }

    return ninja_forms_canonical_compose($form_id, $form_raw, $max_field_bytes);
}

/**
 * Reads a Ninja Forms form straight from the model layer into the native-shaped
 * blob the canonical translator consumes: {title, fields[], actions[],
 * settings{}}. Replaces the old `ninja_forms_invoke_native('get-form')` call —
 * the specialization drives Ninja Forms through its model, never the native
 * `ninjaforms/*` abilities. Returns null when the form does not exist.
 *
 * Shape parity with the native reader:
 *  - each FIELD is the field's full settings map (type / key / label / options /
 *    num_min / default_value / …) with its `id` — flat top-level, exactly what
 *    `ninja_forms_canonical_flatten_field` expects.
 *  - each ACTION is {id, type, label, active, settings} where `settings` is the
 *    full action settings map (email_subject / email_message / to / cc / …),
 *    which `ninja_forms_canonical_email_action_to_canonical` reads.
 *  - SETTINGS is the form-level settings map; `key` (absent from the form
 *    model's get_settings()) is back-filled from the nf3_forms column.
 *
 * @return array<string, mixed>|null
 */
function ninja_forms_canonical_read_form(int $form_id): ?array
{
    if (!ninja_forms_row_exists('nf3_forms', $form_id)) {
        return null;
    }
    $settings = \Ninja_Forms()->form($form_id)->get()->get_settings();
    $cols = ninja_forms_form_columns($form_id);
    if (!isset($settings['key']) || !is_string($settings['key']) || $settings['key'] === '') {
        $settings['key'] = $cols['key'];
    }
    $title =
        isset($settings['title']) && is_string($settings['title']) && $settings['title'] !== ''
            ? $settings['title']
            : $cols['title'];

    return [
        'title' => $title,
        'fields' => ninja_forms_canonical_read_fields($form_id),
        'actions' => ninja_forms_canonical_read_actions($form_id),
        'settings' => $settings,
    ];
}

/**
 * The form's fields as native-shaped flat arrays (full settings map + id).
 *
 * @return list<array<string, mixed>>
 */
function ninja_forms_canonical_read_fields(int $form_id): array
{
    $out = [];
    foreach (\Ninja_Forms()->form($form_id)->get_fields() as $field) {
        $settings = $field->get_settings();
        $settings['id'] = (int) $field->get_id();
        $out[] = $settings;
    }
    return $out;
}

/**
 * The form's actions as {id, type, label, active, settings} — `settings` is the
 * full action settings map the email-action translator reads.
 *
 * @return list<array<string, mixed>>
 */
function ninja_forms_canonical_read_actions(int $form_id): array
{
    $out = [];
    foreach (\Ninja_Forms()->form($form_id)->get_actions() as $action) {
        $settings = $action->get_settings();
        $out[] = [
            'id' => (int) $action->get_id(),
            'type' => ninja_forms_str($settings['type'] ?? ''),
            'label' => ninja_forms_str($settings['label'] ?? ''),
            'active' => $settings['active'] ?? '1',
            'settings' => $settings,
        ];
    }
    return $out;
}

/**
 * Builds the canonical document from the resolved Ninja form blob.
 * Split from the entry point so the translation pipeline stays
 * trivially testable in isolation.
 *
 * @param array<array-key, mixed> $form_raw
 * @return array<string, mixed>
 */
function ninja_forms_canonical_compose(int $form_id, array $form_raw, int $max_field_bytes): array
{
    $fields_raw = is_array($form_raw['fields'] ?? null) ? $form_raw['fields'] : [];
    $actions_raw = is_array($form_raw['actions'] ?? null) ? $form_raw['actions'] : [];
    /** @var array<string, mixed> $settings_raw */
    $settings_raw = is_array($form_raw['settings'] ?? null) ? $form_raw['settings'] : [];

    $key_map = ninja_forms_canonical_build_key_map($fields_raw);
    $version = ninja_forms_version();

    $fields_result = ninja_forms_canonical_translate_fields($fields_raw, $key_map);

    [$mail_config, $notifications, $action_warnings] = ninja_forms_canonical_split_actions(
        $actions_raw,
        $key_map,
        $max_field_bytes,
    );

    $form_warnings = ninja_forms_canonical_form_warnings($settings_raw);

    return [
        'form_id' => $form_id,
        'title' => isset($form_raw['title']) && is_string($form_raw['title']) ? $form_raw['title'] : '',
        'slug' => isset($settings_raw['key']) && is_string($settings_raw['key']) ? $settings_raw['key'] : '',
        'locale' => '',
        'fields' => $fields_result['fields'],
        'mail_config' => $mail_config,
        'notifications' => $notifications,
        'messages' => ninja_forms_canonical_messages($settings_raw),
        'conditional_rules' => [],
        'meta' => ninja_forms_canonical_meta($version, $form_raw),
        'warnings' => array_merge($fields_result['warnings'], $action_warnings, $form_warnings),
    ];
}

/**
 * Emits form-level warnings the field-level translator cannot see. Ninja
 * Forms exposes form-level conditional logic via `settings.conditions` — a
 * plugin-specific shape we don't currently re-encode into the canonical
 * `conditional_rules[]` (the Ninja shape uses opaque trigger/when/then
 * blocks that don't map 1:1 to the canonical rule format). When the form
 * carries any conditional logic we surface an informational warning so
 * the migration plan can flag it as a manual re-config item on the
 * target plugin.
 *
 * @param array<string, mixed> $settings_raw
 *
 * @return list<array<string, string>>
 */
function ninja_forms_canonical_form_warnings(array $settings_raw): array
{
    /** @var mixed $conditions */
    $conditions = $settings_raw['conditions'] ?? null;
    if (!is_array($conditions) || $conditions === []) {
        return [];
    }
    return [
        [
            'path' => 'meta.ninja_forms_conditions',
            'reason' => 'Ninja Forms form-level conditional logic (`settings.conditions`) is echoed verbatim to `meta.ninja_forms_conditions` for round-trip but is NOT re-encoded into canonical `conditional_rules[]` — the Ninja trigger/when/then shape does not map 1:1 to the canonical operator/conditions schema and must be reconfigured on the target plugin.',
            'severity' => 'informational',
        ],
    ];
}

/**
 * Builds the field-key → canonical-name lookup the translator uses to
 * rewrite `{field:KEY}` smart tags into the `[KEY]` canonical
 * convention. Ninja Forms already uses author-readable keys (`name`,
 * `email`, `your_message`) so the canonical name == the source key —
 * no slug derivation is needed, unlike WPForms.
 *
 * @param array<array-key, mixed> $fields
 * @return array<string, string>
 */
function ninja_forms_canonical_build_key_map(array $fields): array
{
    $map = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $key = isset($field['key']) && is_string($field['key']) ? $field['key'] : '';
        if ($key === '') {
            continue;
        }
        $map[$key] = $key;
    }
    return $map;
}

/**
 * Translates all Ninja Forms fields into canonical fields[], accumulating
 * warnings for losses (Ninja-specific UI types without canonical
 * equivalent, composite flat-fields, anti-spam fields).
 *
 * @param array<array-key, mixed> $fields_raw
 * @param array<string, string>   $key_map
 * @return array{fields: list<array<string, mixed>>, warnings: list<array<string, string>>}
 */
function ninja_forms_canonical_translate_fields(array $fields_raw, array $key_map): array
{
    /** @var list<array<string, mixed>> $fields */
    $fields = [];
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    // The warning `path` uses the field's RAW position in the source form so a
    // dropped field's warning points at the right field — distinct from the
    // canonical fields[] index, which only counts kept fields.
    $raw_index = 0;
    // @mago-expect analysis:mixed-assignment
    foreach ($fields_raw as $field) {
        if (!is_array($field)) {
            $raw_index++;
            continue;
        }
        $result = ninja_forms_canonical_translate_field($field, $raw_index, $key_map);
        foreach ($result['warnings'] as $warning) {
            $warnings[] = $warning;
        }
        if ($result['field'] !== null) {
            $fields[] = $result['field'];
        }
        $raw_index++;
    }

    return ['fields' => $fields, 'warnings' => $warnings];
}

/**
 * Merges a Ninja field's nested `settings{}` block into its top-level
 * keys so the translator can look up every property uniformly via
 * `$field['<key>']` regardless of whether Ninja Forms surfaced it at
 * the top level or only inside `settings`. Top-level wins on conflict
 * (Ninja Forms repeats the same value in both blocks for the common
 * keys — type / label / key / required — so picking either is fine).
 *
 * @param array<array-key, mixed> $field
 * @return array<array-key, mixed>
 */
function ninja_forms_canonical_flatten_field(array $field): array
{
    if (!isset($field['settings']) || !is_array($field['settings'])) {
        return $field;
    }
    $settings = $field['settings'];
    // @mago-expect analysis:mixed-assignment
    foreach ($settings as $key => $value) {
        if (array_key_exists($key, $field)) {
            continue;
        }
        $field[$key] = $value;
    }
    return $field;
}

/**
 * Maps a single Ninja field to canonical. Returns null when the field
 * has no canonical representation at all (rendering-only fields like
 * `note` / `html` / `hr`, anti-spam fields, Ninja-specific UI types
 * like `starrating` / `signature` / `creditcard*`).
 *
 * @param array<array-key, mixed> $field
 * @param array<string, string>   $key_map
 * @return array{field: array<string, mixed>|null, warnings: list<array<string, string>>}
 */
function ninja_forms_canonical_translate_field(array $field, int $field_index, array $key_map): array
{
    // Ninja Forms returns each field as a flat top-level (`id, type,
    // label, key, order, required`) PLUS a nested `settings{}` block
    // that carries the type-specific extras (options, num_min, num_max,
    // placeholder, default_value, etc.). Flatten the two into one
    // lookup so every property is reachable as `$field['<key>']`.
    $field = ninja_forms_canonical_flatten_field($field);

    $nf_type = isset($field['type']) && is_string($field['type']) ? $field['type'] : '';
    $key = isset($field['key']) && is_string($field['key']) ? $field['key'] : 'field-' . $field_index;

    if (ninja_forms_canonical_field_is_skipped($nf_type)) {
        return [
            'field' => null,
            'warnings' => [ninja_forms_canonical_skip_warning($nf_type, $field_index)],
        ];
    }

    $canonical_type = ninja_forms_canonical_type_map($nf_type);
    if ($canonical_type === null) {
        return [
            'field' => null,
            'warnings' => [ninja_forms_canonical_unmapped_warning($nf_type, $field_index)],
        ];
    }

    $payload = ninja_forms_canonical_field_payload($field, $nf_type, $key, $canonical_type, $key_map);
    $warnings = ninja_forms_canonical_field_warnings($nf_type, $field_index);

    // Canonical convention forces consent fields to required: true.
    // When the source `terms` field was NOT marked required, emit an
    // informational warning so the agent / user can verify on the
    // target side that gating the submit on consent is intentional.
    // Audit CORR-N-2 (mirrors the CF7 `[acceptance optional]` warning).
    if ($nf_type === 'terms' && !ninja_forms_truthy($field['required'] ?? null)) {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => 'Ninja Forms [terms] field was not marked as required on the source — canonical convention forces consent to required: true; verify this is intentional on the target.',
            'severity' => 'informational',
        ];
    }

    return ['field' => $payload, 'warnings' => $warnings];
}

/**
 * Returns true when the Ninja field type has no canonical role (pure
 * rendering / anti-spam / Ninja-specific UI without canonical analog)
 * and should be dropped from fields[] with a single warning.
 */
function ninja_forms_canonical_field_is_skipped(string $nf_type): bool
{
    return in_array(
        $nf_type,
        [
            'note',
            'html',
            'hr',
            'confirm',
            'starrating',
            'signature',
            'quantity',
            'product',
            'shipping',
            'total',
            'creditcard',
            'creditcardnumber',
            'creditcardcvc',
            'creditcardexpiration',
            'creditcardfullname',
            'creditcardzip',
            'hcaptcha',
            'recaptcha',
            'recaptchav3',
            'turnstile',
            'spam',
            'timedsubmit',
        ],
        strict: true,
    );
}

/**
 * Builds the warning entry for a skipped Ninja field. Severity is
 * `structural` for fields with semantic weight (payment / signature /
 * rating / quantity) and `informational` for rendering helpers
 * (note / html / hr / confirm) and site-global anti-spam.
 *
 * @return array<string, string>
 */
function ninja_forms_canonical_skip_warning(string $nf_type, int $field_index): array
{
    $structural = [
        'starrating',
        'signature',
        'quantity',
        'product',
        'shipping',
        'total',
        'creditcard',
        'creditcardnumber',
        'creditcardcvc',
        'creditcardexpiration',
        'creditcardfullname',
        'creditcardzip',
    ];
    $severity = in_array($nf_type, $structural, strict: true) ? 'structural' : 'informational';
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => sprintf(
            /* translators: %s: Ninja Forms native field type. */
            'Ninja Forms [%s] field has no canonical equivalent — preserved as a warning for the migration plan; the target plugin must provide its own implementation.',
            $nf_type,
        ),
        'severity' => $severity,
    ];
}

/**
 * Maps a Ninja field type to its canonical form-model-schema type.
 * Returns null when the type is recognised by Ninja but does not map
 * cleanly (handled by the skip list above) or is unknown.
 */
function ninja_forms_canonical_type_map(string $nf_type): ?string
{
    return match ($nf_type) {
        'textbox' => 'text',
        'textarea' => 'textarea',
        'email' => 'email',
        'number' => 'number',
        'phone' => 'tel',
        'date' => 'date',
        'hidden' => 'hidden',
        'submit' => 'submit',
        'password', 'passwordconfirm' => 'text',
        'checkbox' => 'checkbox',
        'terms' => 'consent',
        'listcheckbox' => 'checkbox',
        'listradio' => 'radio',
        'listselect', 'listcountry', 'liststate', 'listimage', 'listmodifier' => 'select',
        'listmultiselect' => 'select',
        'address', 'address2', 'city', 'zip', 'firstname', 'lastname' => 'text',
        'repeater' => 'group',
        default => null,
    };
}

/**
 * Builds the canonical field array for the mapped Ninja field. Pulls
 * common keys (name / type / label / required / placeholder / default /
 * cardinality / choices / validation) and carries Ninja-specific hints
 * into meta for lossless round-trip.
 *
 * @param array<array-key, mixed> $field
 * @param array<string, string>   $key_map
 * @return array<string, mixed>
 */
function ninja_forms_canonical_field_payload(
    array $field,
    string $nf_type,
    string $key,
    string $canonical_type,
    array $key_map,
): array {
    $payload = [
        'name' => $key,
        'type' => $canonical_type,
    ];
    ninja_forms_canonical_apply_string_property($payload, $field, source_key: 'label', target_key: 'label');
    ninja_forms_canonical_apply_string_property($payload, $field, source_key: 'help_text', target_key: 'description');
    ninja_forms_canonical_apply_string_property($payload, $field, source_key: 'placeholder', target_key: 'placeholder');
    ninja_forms_canonical_apply_default_value($payload, $field, $key_map);
    // Canonical convention: consent fields are ALWAYS required —
    // they gate submission semantically, even if the source plugin
    // allowed an opt-out variant.
    if ($canonical_type === 'consent' || ninja_forms_truthy($field['required'] ?? null)) {
        $payload['required'] = true;
    }
    ninja_forms_canonical_apply_cardinality($payload, $nf_type);
    ninja_forms_canonical_apply_choices($payload, $field, $canonical_type);
    ninja_forms_canonical_apply_validation($payload, $field, $nf_type, $canonical_type);
    ninja_forms_canonical_apply_subtype($payload, $nf_type, $canonical_type);
    $payload['meta'] = ninja_forms_canonical_field_meta($field, $nf_type);

    return $payload;
}

/**
 * Copies a simple string property from the Ninja field to the canonical
 * field. Centralised so the three optional string-shaped keys
 * (label / description / placeholder) share the same null / type /
 * empty handling, plus the source / target key remapping (Ninja uses
 * `help_text`, canonical uses `description`).
 *
 * @param array<string, mixed>    $payload
 * @param array<array-key, mixed> $field
 */
function ninja_forms_canonical_apply_string_property(
    array &$payload,
    array $field,
    string $source_key,
    string $target_key,
): void {
    if (isset($field[$source_key]) && is_string($field[$source_key]) && $field[$source_key] !== '') {
        $payload[$target_key] = $field[$source_key];
    }
}

/**
 * Carries the Ninja `default_value` to the canonical `default` key
 * when present and non-empty. Ninja default values can include the
 * `{field:KEY}` smart tag — rewrite them to canonical `[KEY]` notation
 * so the canonical document round-trips with the CF7 reader.
 *
 * @param array<string, mixed>    $payload
 * @param array<array-key, mixed> $field
 * @param array<string, string>   $key_map
 */
function ninja_forms_canonical_apply_default_value(array &$payload, array $field, array $key_map): void
{
    if (!isset($field['default_value']) || !is_string($field['default_value']) || $field['default_value'] === '') {
        return;
    }
    $payload['default'] = ninja_forms_canonical_normalize_smart_tags($field['default_value'], $key_map);
}

/**
 * Sets canonical `cardinality` for the Ninja field types where the
 * value is meaningful (single vs multi list types, checkbox group,
 * repeater). Leaves the key absent on scalar types.
 *
 * @param array<string, mixed> $payload
 */
function ninja_forms_canonical_apply_cardinality(array &$payload, string $nf_type): void
{
    if ($nf_type === 'listradio') {
        $payload['cardinality'] = 'single';
        return;
    }
    if ($nf_type === 'listcheckbox') {
        $payload['cardinality'] = 'multi';
        return;
    }
    if ($nf_type === 'listmultiselect') {
        $payload['cardinality'] = 'multi';
        return;
    }
    if (in_array($nf_type, ['listselect', 'listcountry', 'liststate', 'listimage', 'listmodifier'], strict: true)) {
        $payload['cardinality'] = 'single';
        return;
    }
    if ($nf_type === 'checkbox') {
        // Single checkbox in Ninja is a yes/no toggle stored as
        // `checked_value` / `unchecked_value` — semantically a single
        // boolean. Canonical models it as `checkbox` with
        // `cardinality: single`.
        $payload['cardinality'] = 'single';
        return;
    }
    if ($nf_type === 'repeater') {
        $payload['cardinality'] = 'multi';
    }
}

/**
 * Translates Ninja Forms `options[]` into canonical
 * `choices[{value,label}]` for the list-style canonical types
 * (`select` / `checkbox` / `radio`). Ninja stores each option as
 * `{label, value, calc, selected, order}` — canonical only carries
 * value + label.
 *
 * @param array<string, mixed>    $payload
 * @param array<array-key, mixed> $field
 */
function ninja_forms_canonical_apply_choices(array &$payload, array $field, string $canonical_type): void
{
    if (!in_array($canonical_type, ['select', 'checkbox', 'radio'], strict: true)) {
        return;
    }
    /** @var mixed $raw */
    $raw = $field['options'] ?? null;
    if (!is_array($raw) || $raw === []) {
        return;
    }
    /** @var list<array{value: string, label: string}> $choices */
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
        $payload['choices'] = $choices;
    }
}

/**
 * Populates the canonical `validation` block from Ninja field-level
 * limits (number min/max, character / word limits for text/textarea).
 * Only the keys that make sense for the source type are emitted —
 * canonical schema ignores absent ones.
 *
 * @param array<string, mixed>    $payload
 * @param array<array-key, mixed> $field
 */
function ninja_forms_canonical_apply_validation(
    array &$payload,
    array $field,
    string $nf_type,
    string $canonical_type,
): void {
    $validation = array_merge(
        ninja_forms_canonical_number_validation($field, $canonical_type),
        ninja_forms_canonical_length_validation($field, $nf_type),
    );
    if ($validation !== []) {
        $payload['validation'] = $validation;
    }
}

/**
 * Extracts canonical `min` / `max` from a Ninja number field. Returns
 * an empty array for non-number types so the caller can merge
 * unconditionally.
 *
 * @param array<array-key, mixed> $field
 * @return array<string, int|float>
 */
function ninja_forms_canonical_number_validation(array $field, string $canonical_type): array
{
    if ($canonical_type !== 'number') {
        return [];
    }
    $out = [];
    if (isset($field['num_min']) && is_numeric($field['num_min'])) {
        $out['min'] = ninja_forms_canonical_to_number($field['num_min']);
    }
    if (isset($field['num_max']) && is_numeric($field['num_max'])) {
        $out['max'] = ninja_forms_canonical_to_number($field['num_max']);
    }
    if (isset($field['num_step']) && is_numeric($field['num_step'])) {
        $out['step'] = ninja_forms_canonical_to_number($field['num_step']);
    }
    return $out;
}

/**
 * Extracts canonical `max_length` from a Ninja text / textarea field
 * with the character-count limiter enabled. Ninja's `input_limit` +
 * `input_limit_type` mirror WPForms' limit_count + limit_mode.
 *
 * @param array<array-key, mixed> $field
 * @return array<string, int>
 */
function ninja_forms_canonical_length_validation(array $field, string $nf_type): array
{
    if (!in_array($nf_type, ['textbox', 'textarea'], strict: true)) {
        return [];
    }
    if (!isset($field['input_limit']) || !is_numeric($field['input_limit'])) {
        return [];
    }
    $limit = (int) $field['input_limit'];
    $mode = isset($field['input_limit_type']) && is_string($field['input_limit_type'])
        ? $field['input_limit_type']
        : 'characters';
    if ($limit <= 0 || $mode !== 'characters') {
        return [];
    }
    return ['max_length' => $limit];
}

/**
 * Casts a numeric-looking Ninja value (often a string in the get-form
 * response) to its native int / float so canonical consumers don't
 * have to re-parse.
 */
function ninja_forms_canonical_to_number(mixed $value): int|float
{
    if (!is_numeric($value)) {
        return 0;
    }
    $string = (string) $value;
    return str_contains($string, '.') ? floatval($string) : (int) $string;
}

/**
 * Folds Ninja-specific specialisers (password format, composite flat
 * fields, repeater multi) into the canonical field's `format` /
 * `subtype` modifiers. The canonical type has already been picked by
 * `ninja_forms_canonical_type_map` — this fn only refines it.
 *
 * @param array<string, mixed> $payload
 */
function ninja_forms_canonical_apply_subtype(array &$payload, string $nf_type, string $canonical_type): void
{
    if (in_array($nf_type, ['password', 'passwordconfirm'], strict: true) && $canonical_type === 'text') {
        $payload['format'] = 'password';
        return;
    }
    if ($canonical_type === 'number') {
        $payload['subtype'] = 'decimal';
    }
}

/**
 * Builds the canonical `meta{}` sub-block for a field. Carries the
 * original Ninja type (so round-trip can rebuild the field) plus any
 * non-canonical rendering hints (CSS classes).
 *
 * @param array<array-key, mixed> $field
 * @return array<string, mixed>
 */
function ninja_forms_canonical_field_meta(array $field, string $nf_type): array
{
    $meta = ['ninja_forms_field_type' => $nf_type];
    if (isset($field['container_class']) && is_string($field['container_class']) && $field['container_class'] !== '') {
        $meta['ninja_forms_container_class'] = $field['container_class'];
    }
    if (isset($field['element_class']) && is_string($field['element_class']) && $field['element_class'] !== '') {
        $meta['ninja_forms_element_class'] = $field['element_class'];
    }
    return $meta;
}

/**
 * Generates per-field migration warnings the type translator alone
 * cannot surface: composite flat-fields (Ninja's first-name / last-name
 * / address split-up pattern with no parent), and password-confirm
 * paired with password.
 *
 * @return list<array<string, string>>
 */
function ninja_forms_canonical_field_warnings(string $nf_type, int $field_index): array
{
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    if (in_array($nf_type, ['firstname', 'lastname', 'address', 'address2', 'city', 'zip'], strict: true)) {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => sprintf(
                /* translators: %s: Ninja Forms native field type. */
                'Ninja Forms [%s] is a flat field; the target plugin\'s composite name / address widget (if any) must be reconstructed manually around it.',
                $nf_type,
            ),
            'severity' => 'informational',
        ];
    }
    if ($nf_type === 'passwordconfirm') {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => 'Ninja Forms [passwordconfirm] is a paired field — the link to its sibling [password] is lost on canonical translation; re-establish the confirmation logic on the target plugin manually.',
            'severity' => 'informational',
        ];
    }
    if ($nf_type === 'repeater') {
        $warnings[] = [
            'path' => 'fields[' . $field_index . ']',
            'reason' => 'Ninja Forms [repeater] collapsed to canonical group — repeated sub-field structure is plugin-specific and must be rebuilt on the target plugin.',
            'severity' => 'informational',
        ];
    }

    return $warnings;
}

/**
 * Builds the warning for a Ninja Forms field type the canonical map
 * does not cover. Distinct from skipped types (which the agent KNOWS
 * will not survive) — an unmapped type is the safety net for new
 * releases or third-party field modules.
 *
 * @return array<string, string>
 */
function ninja_forms_canonical_unmapped_warning(string $nf_type, int $field_index): array
{
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => sprintf(
            /* translators: %s: Ninja Forms native field type. */
            'Ninja Forms [%s] field type is not yet mapped to the canonical vocabulary — dropped from canonical fields[]. Report this to the integration maintainer; the type may have been added in a recent Ninja Forms release or by a third-party module.',
            $nf_type,
        ),
        'severity' => 'informational',
    ];
}

/**
 * Picks the first email-action as `mail_config{}`, the remaining
 * email-actions as `notifications[]`, and emits warnings for every
 * non-email action (redirect / save / successmessage / webhook).
 *
 * @param array<array-key, mixed> $actions
 * @param array<string, string>   $key_map
 * @return array{0: array<string, mixed>, 1: list<array<string, mixed>>, 2: list<array<string, string>>}
 */
function ninja_forms_canonical_split_actions(array $actions, array $key_map, int $max_field_bytes): array
{
    /** @var list<array<string, mixed>> $email_actions */
    $email_actions = [];
    /** @var list<array<string, string>> $warnings */
    $warnings = [];
    $non_email_index = 0;

    // @mago-expect analysis:mixed-assignment
    foreach ($actions as $action) {
        if (!is_array($action)) {
            continue;
        }
        $type = isset($action['type']) && is_string($action['type']) ? $action['type'] : '';
        if ($type === 'email') {
            $email_actions[] = $action;
            continue;
        }
        $warnings[] = [
            'path' => 'meta.ninja_forms_non_email_actions[' . $non_email_index . ']',
            'reason' => sprintf(
                /* translators: %s: Ninja Forms native action type. */
                'Ninja Forms action type [%s] has no canonical equivalent — echoed verbatim in meta.ninja_forms_non_email_actions for round-trip but the target plugin must be configured manually.',
                $type,
            ),
            'severity' => 'informational',
        ];
        $non_email_index++;
    }

    $mail_config = ninja_forms_canonical_empty_mail_config();
    if ($email_actions !== []) {
        $first = array_shift($email_actions);
        $mail_config = ninja_forms_canonical_email_action_to_canonical($first, $key_map, $max_field_bytes);
    }

    /** @var list<array<string, mixed>> $notifications */
    $notifications = [];
    foreach ($email_actions as $extra) {
        $entry = ninja_forms_canonical_email_action_to_canonical($extra, $key_map, $max_field_bytes);
        $notifications[] = array_merge([
            'name' => ninja_forms_canonical_action_name($extra),
            'active' => ninja_forms_truthy($extra['active'] ?? '1'),
        ], $entry);
    }

    return [$mail_config, $notifications, $warnings];
}

/**
 * Returns the canonical notification name. Ninja Forms stores a
 * human-readable label per action — slugify it so the canonical name
 * is portable, and fall back to `notification-<id>` when missing.
 *
 * @param array<array-key, mixed> $action
 */
function ninja_forms_canonical_action_name(array $action): string
{
    if (isset($action['label']) && is_string($action['label']) && $action['label'] !== '') {
        $slug = sanitize_title($action['label']);
        if ($slug !== '') {
            return $slug;
        }
    }
    $id = isset($action['id']) ? (string) $action['id'] : '';
    return 'notification-' . ($id !== '' ? $id : 'x');
}

/**
 * Translates one Ninja email-action into the canonical mail-config
 * shape (shared by `mail_config{}` and each `notifications[]` entry).
 * `{field:KEY}` smart tags inside body / subject / recipients are
 * rewritten to `[KEY]` so the canonical document round-trips cleanly
 * with the CF7 reader.
 *
 * @param array<array-key, mixed> $action
 * @param array<string, string>   $key_map
 * @return array<string, mixed>
 */
function ninja_forms_canonical_email_action_to_canonical(array $action, array $key_map, int $max_field_bytes): array
{
    /** @var array<string, mixed> $settings */
    $settings = isset($action['settings']) && is_array($action['settings']) ? $action['settings'] : [];

    $body_raw = ninja_forms_canonical_normalize_smart_tags(
        isset($settings['email_message']) && is_string($settings['email_message']) ? $settings['email_message'] : '',
        $key_map,
    );
    $body_truncated = ninja_forms_truncate_field($body_raw, $max_field_bytes);

    $cc = ninja_forms_canonical_normalize_smart_tags(
        isset($settings['cc']) && is_string($settings['cc']) ? $settings['cc'] : '',
        $key_map,
    );
    $bcc = ninja_forms_canonical_normalize_smart_tags(
        isset($settings['bcc']) && is_string($settings['bcc']) ? $settings['bcc'] : '',
        $key_map,
    );
    $additional_headers = ninja_forms_canonical_additional_headers($cc, $bcc);

    return [
        'subject' => ninja_forms_canonical_normalize_smart_tags(
            isset($settings['email_subject']) && is_string($settings['email_subject'])
                ? $settings['email_subject']
                : '',
            $key_map,
        ),
        'sender' => isset($settings['from_address']) && is_string($settings['from_address'])
            ? $settings['from_address']
            : '',
        'recipient' => ninja_forms_canonical_normalize_smart_tags(
            isset($settings['to']) && is_string($settings['to']) ? $settings['to'] : '',
            $key_map,
        ),
        'body' => $body_truncated['value'],
        'body_truncated' => $body_truncated['truncated'],
        'body_bytes' => $body_truncated['original_bytes'],
        'additional_headers' => $additional_headers,
        'attachments' => [],
        // Ninja Forms defaults `email_format` to 'html' when absent
        // (see Ninja_Forms\Config\ActionEmailSettings). Treat absence
        // as HTML — only an explicit 'plain' downgrades use_html.
        // Audit CORR-N-1.
        'use_html' => !isset($settings['email_format']) || $settings['email_format'] !== 'plain',
        'exclude_blank' => false,
    ];
}

/**
 * Composes the canonical `additional_headers` string from Ninja's Cc /
 * Bcc settings. CF7 expects multi-line `Name: value` pairs; we emit
 * `Cc: ...` and `Bcc: ...` when the values are non-empty.
 */
function ninja_forms_canonical_additional_headers(string $cc, string $bcc): string
{
    $lines = [];
    if ($cc !== '') {
        $lines[] = 'Cc: ' . $cc;
    }
    if ($bcc !== '') {
        $lines[] = 'Bcc: ' . $bcc;
    }
    return implode("\n", $lines);
}

/**
 * Returns an empty canonical mail-config block. Used when the form
 * ships zero email-actions so the shape stays predictable.
 *
 * @return array<string, mixed>
 */
function ninja_forms_canonical_empty_mail_config(): array
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
 * Rewrites Ninja Forms `{field:KEY}` smart tags to the canonical
 * `[KEY]` convention (which matches the CF7 syntax). Other Ninja smart
 * tags (`{all_fields_table}`, `{wp:admin_email}`, `{system:date}`,
 * etc.) are preserved verbatim — the caller / playbook surfaces them
 * as informational warnings.
 *
 * @param array<string, string> $key_map
 */
function ninja_forms_canonical_normalize_smart_tags(string $input, array $key_map): string
{
    if ($input === '' || $key_map === []) {
        return $input;
    }
    $rewritten = preg_replace_callback(
        pattern: '/\{field:([a-zA-Z0-9_\-]+)\}/',
        callback: static function (array $match) use ($key_map): string {
            $key = $match[1] ?? '';
            $name = $key_map[$key] ?? '';
            return $name !== '' ? '[' . $name . ']' : $match[0];
        },
        subject: $input,
    );
    // Defence: preg_replace_callback returns null on PCRE errors
    // (backtrack limit, OOM mid-callback). Casting null to string
    // would silently zero a mail body — fall back to the original
    // input so smart tags are at worst left verbatim.
    return $rewritten ?? $input;
}

/**
 * Maps the Ninja form-level message strings to canonical `messages{}`.
 * Ninja keeps them under settings (`success_message`, `validate_required_field`,
 * `not_logged_in_msg`, ...) — we pass through every string-valued key
 * so module-supplied / user-customised messages survive on round-trip.
 *
 * @param array<string, mixed> $settings
 * @return array<string, string>
 */
function ninja_forms_canonical_messages(array $settings): array
{
    // Allowlist of Ninja Forms core message-string keys. We intentionally
    // do NOT scan for keys containing the substring "msg" / "message":
    // third-party add-ons (Stripe NF, custom webhook modules, payment
    // gateways) can inject settings keys whose names contain "message"
    // while their values carry API tokens / credentials. The canonical
    // reader is `readonly: true` and surfaces its output to MCP-connected
    // agents, so a substring filter would be a credential-leakage vector.
    // Audit SEC-N-001.
    $allowed = [
        'success_message',
        'error_message',
        'validate_required_field',
        'not_logged_in_msg',
        'sub_limit_msg',
        'error_header',
        'error_footer',
        'changeDateErrorMsg',
        'changeEmailErrorMsg',
        'confirmFieldErrorMsg',
        'fieldNumberNumMinError',
        'fieldNumberNumMaxError',
        'fieldNumberIncrementBy',
        'formErrorsCorrectErrors',
        'validateRequiredField',
        'honeypotHoneypotError',
        'fieldsMarkedRequired',
    ];
    $out = [];
    foreach ($allowed as $key) {
        if (!isset($settings[$key]) || !is_string($settings[$key])) {
            continue;
        }
        $out[$key] = $settings[$key];
    }
    return $out;
}

/**
 * Builds the canonical `meta{}` block. Always carries source plugin
 * and version; echoes the non-email actions (redirect / save /
 * successmessage / webhook) verbatim for round-trip when the target
 * is also Ninja Forms, plus a slim form-level settings snapshot.
 *
 * @param array<array-key, mixed> $form_raw
 * @return array<string, mixed>
 */
function ninja_forms_canonical_meta(string $version, array $form_raw): array
{
    /** @var list<array<string, mixed>> $non_email_actions */
    $non_email_actions = [];
    $actions = is_array($form_raw['actions'] ?? null) ? $form_raw['actions'] : [];
    // @mago-expect analysis:mixed-assignment
    foreach ($actions as $action) {
        if (!is_array($action)) {
            continue;
        }
        $type = isset($action['type']) && is_string($action['type']) ? $action['type'] : '';
        if ($type === 'email') {
            continue;
        }
        /** @var array<string, mixed> $action */
        $non_email_actions[] = ninja_forms_canonical_redact_action($action);
    }

    return [
        'source_plugin' => 'ninja-forms',
        'source_version' => $version,
        'ninja_forms_non_email_actions' => $non_email_actions,
        // Echo the form-level conditional logic blob verbatim so an agent
        // running an execute-mode migration can re-apply Ninja's trigger/
        // when/then shape on the target plugin manually. Stored as opaque
        // data — canonical does not re-encode it into conditional_rules[]
        // because the Ninja shape is plugin-specific (the warnings[] entry
        // emitted by ninja_forms_canonical_form_warnings declares the
        // semantic loss explicitly). Echoes [] when the form has none.
        'ninja_forms_conditions' => ninja_forms_canonical_extract_conditions($form_raw),
    ];
}

/**
 * Reads the form-level `settings.conditions` blob from the raw Ninja
 * form payload, returning a list of plain arrays. Used by the meta
 * builder to surface the conditions for round-trip; the form-warnings
 * emitter checks the same source so both agree on what "has
 * conditions" means.
 *
 * @param array<array-key, mixed> $form_raw
 *
 * @return list<array<array-key, mixed>>
 */
function ninja_forms_canonical_extract_conditions(array $form_raw): array
{
    $settings = is_array($form_raw['settings'] ?? null) ? $form_raw['settings'] : [];
    /** @var mixed $raw */
    $raw = $settings['conditions'] ?? null;
    if (!is_array($raw) || $raw === []) {
        return [];
    }
    /** @var list<array<array-key, mixed>> $out */
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $out[] = $entry;
    }
    return $out;
}

/**
 * Redacts secrets and high-risk transport fields from a non-email
 * action before it lands in `meta.ninja_forms_non_email_actions`.
 *
 * Webhook actions in Ninja Forms (`Webhook` add-on, third-party CRM /
 * Mailchimp / Stripe integrations) keep `webhook_url` with auth tokens
 * embedded as URL query params and freeform HTTP headers under
 * `webhook_headers` / `webhook_args` that often carry
 * `Authorization: Bearer ...` lines. The canonical reader is
 * `readonly: true` and the result is consumed by MCP-connected agents,
 * so leaking these verbatim would be a credential leakage vector.
 * Audit SEC-N-002.
 *
 * Strategy:
 * - For `webhook` (and any third-party action with `url` / `headers` /
 *   `args` / `secret` / `token` / `api_key`) emit only `id`, `type`,
 *   `label`, `active`, plus a `has_secrets: true` flag, plus a
 *   sanitised `settings{}` with sensitive keys redacted.
 * - For `redirect`, `successmessage`, `save` and other low-risk action
 *   types, echo the action with the same key-level redaction applied
 *   defensively so any future third-party add-on key that shouts
 *   "this is a secret" gets caught.
 *
 * @param array<string, mixed> $action
 * @return array<string, mixed>
 */
function ninja_forms_canonical_redact_action(array $action): array
{
    $redacted = $action;
    $settings = isset($action['settings']) && is_array($action['settings']) ? $action['settings'] : [];
    $redacted['settings'] = ninja_forms_canonical_redact_settings($settings);
    $redacted['has_secrets'] = $settings !== $redacted['settings'];
    return $redacted;
}

/**
 * Redacts the sensitive keys from a non-email action's `settings{}`
 * blob. Keys whose name contains any of the high-risk substrings
 * (`url`, `key`, `secret`, `token`, `header`, `password`, `auth`,
 * `credential`, `webhook`) and whose value is a non-empty string have
 * their value replaced with the literal `[REDACTED]`. The key name
 * itself is preserved so the agent / user can SEE that a secret was
 * present without learning its value.
 *
 * @param array<array-key, mixed> $settings
 * @return array<string, mixed>
 */
function ninja_forms_canonical_redact_settings(array $settings): array
{
    $sensitive_substrings = ['url', 'key', 'secret', 'token', 'header', 'password', 'auth', 'credential', 'webhook'];
    $out = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($settings as $key => $value) {
        $name = is_string($key) ? $key : (string) $key;
        if (
            ninja_forms_canonical_name_is_sensitive($name, $sensitive_substrings)
            && is_string($value)
            && $value !== ''
        ) {
            $out[$name] = '[REDACTED]';
            continue;
        }
        $out[$name] = $value;
    }
    return $out;
}

/**
 * Returns true when any of the sensitive substrings appears in the
 * key name (case-insensitive). Extracted from
 * `ninja_forms_canonical_redact_settings` to keep that function's
 * nesting under Mago's threshold.
 *
 * @param list<string> $sensitive_substrings
 */
function ninja_forms_canonical_name_is_sensitive(string $name, array $sensitive_substrings): bool
{
    $lower = strtolower($name);
    foreach ($sensitive_substrings as $needle) {
        if (str_contains($lower, $needle)) {
            return true;
        }
    }
    return false;
}
