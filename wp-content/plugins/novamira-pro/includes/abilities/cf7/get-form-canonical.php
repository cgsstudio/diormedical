<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Cf7;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/cf7-get-form-canonical', [
    'label' => __('Get Contact Form 7 Form (Canonical)', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Contact Form 7 form by id and returns it mapped to the plugin-agnostic form-model-schema vocabulary (17 canonical types: text/email/tel/url/number/textarea/date/time/datetime/select/radio/checkbox/file/hidden/submit/consent/group) used by the form-model-migration skill. CF7 natively covers 14 of those 17 — `time`/`datetime`/`group` have no CF7 form-tag and appear only on migration targets. Output keys: `fields[]` with canonical types, `mail_config{}` for the primary mail, `notifications[]` for mail_2 (when active), `messages{}` flat string map, `meta{}` (source plugin + version + optional cf7_additional_settings echo), plus `warnings[]` for everything that does not fit the canonical shape (CF7 `[quiz]` anti-spam, `[recaptcha]` site-global keys, `[response]` output area, non-empty `additional_settings`, special mail-tags, `[acceptance optional]`). Honours the same `max_field_bytes` truncation contract as `cf7-get-form` (default 8192 bytes for `mail_config.body`, `notifications[].body`, `meta.cf7_additional_settings`, with `*_truncated` flags); pass `max_field_bytes: 0` to disable truncation when full-fidelity canonical is required before an execute-mode migration. Returns WP_Error `cf7_form_not_found` when the id does not exist or refers to a trashed / auto-draft / non-CF7 post. Use this ability inside the form-model-migration workflow (Phase 2 — read source schema); for ad-hoc inspection outside the migration pipeline, prefer `cf7-get-form` which returns the CF7-native shape.',
        domain: 'novamira-pro',
    ),
    'category' => 'cf7',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer', 'description' => 'Form id from cf7-list-forms.'],
            'max_field_bytes' => [
                'type' => 'integer',
                'description' => 'Per-field byte cap for the large text properties (`mail_config.body`, `notifications[].body`, `meta.cf7_additional_settings`). Default 8192. Set to 0 to disable truncation entirely.',
                'minimum' => 0,
                'default' => 8192,
            ],
        ],
        'required' => ['id'],
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
            'meta',
            'warnings',
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Cf7\\cf7_get_form_canonical',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use as Phase 2 (read source) of the form-model-migration workflow when CF7 is the source plugin. The output is the canonical form-model-schema document directly — feed it into Phase 3 (translate) as-is; warnings[] already enumerates structural / informational losses (quiz, recaptcha, additional_settings, special mail-tags). The form-tag parser handles the * required modifier, quoted labels, and key:value options. Pass max_field_bytes:0 before an execute-mode migration so canonical bodies are not truncated.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function cf7_get_form_canonical(array $input): array|WP_Error
{
    // Mirror cf7_get_form's explicit integer guard — the schema layer
    // accepts numeric strings, then strict_types would trip a TypeError
    // inside the parser. Reject at the boundary.
    $id = isset($input['id']) && is_int($input['id']) ? $input['id'] : 0;
    if ($id <= 0) {
        return cf7_error('cf7_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    $max_field_bytes = isset($input['max_field_bytes']) && is_int($input['max_field_bytes'])
        ? max(0, $input['max_field_bytes'])
        : cf7_default_max_field_bytes();

    $form = cf7_form_or_error($id);
    if ($form instanceof WP_Error) {
        return $form;
    }

    // Reuse the full-shape helper so canonical and raw responses agree
    // on truncation, mail normalisation, and messages handling — keeps
    // round-trip math honest across the two readers.
    $full = cf7_form_to_full($form, $max_field_bytes);

    $template_raw = (string) $form->prop('form');
    $parsed = cf7_canonical_parse_form_tags($template_raw);

    $fields = $parsed['fields'];
    /** @var list<array<string, mixed>> $warnings */
    $warnings = $parsed['warnings'];

    /** @var array<string, mixed> $mail */
    $mail = is_array($full['mail']) ? $full['mail'] : [];
    /** @var array<string, mixed> $mail_2 */
    $mail_2 = is_array($full['mail_2']) ? $full['mail_2'] : [];
    /** @var array<string, string> $messages */
    $messages = is_array($full['messages']) ? $full['messages'] : [];

    $additional_settings_raw = (string) $form->prop('additional_settings');

    // Surface integration-level losses the form-tag parser can't see:
    // additional_settings, special mail-tags inside the mail body /
    // subject of the primary and (when active) the autoreply notification.
    foreach (cf7_canonical_integration_warnings($mail, $mail_2, $additional_settings_raw) as $warning) {
        $warnings[] = $warning;
    }

    return [
        'form_id' => $form->id(),
        'title' => $form->title(),
        'slug' => $form->name(),
        'locale' => $form->locale(),
        'fields' => $fields,
        'mail_config' => cf7_canonical_mail_config($mail),
        'notifications' => cf7_canonical_notifications($mail_2),
        'messages' => $messages,
        'meta' => cf7_canonical_meta($additional_settings_raw, $max_field_bytes),
        'warnings' => $warnings,
    ];
}

/**
 * Builds the canonical `meta{}` block. Always carries `source_plugin`
 * and `source_version`; conditionally echoes `cf7_additional_settings`
 * with the same byte cap applied to mail bodies, so a multi-kilobyte
 * demo_mode dump can't blow up the response. The truncation flag rides
 * on the same envelope so the caller can re-request with
 * `max_field_bytes: 0` when needed.
 *
 * @return array<string, mixed>
 */
function cf7_canonical_meta(string $additional_settings_raw, int $max_field_bytes): array
{
    $meta = [
        'source_plugin' => 'cf7',
        'source_version' => cf7_version(),
    ];
    if ($additional_settings_raw !== '') {
        $truncated = cf7_truncate_field($additional_settings_raw, $max_field_bytes);
        $meta['cf7_additional_settings'] = $truncated['value'];
        $meta['cf7_additional_settings_truncated'] = $truncated['truncated'];
        $meta['cf7_additional_settings_bytes'] = $truncated['original_bytes'];
    }
    return $meta;
}

/**
 * Collects the integration-level warnings the form-tag parser cannot
 * emit on its own: non-empty `additional_settings` (preserved in meta,
 * but informational because it does not portfolio across plugins) and
 * special mail-tags (`[_serial_number]`, `[_remote_ip]`, ...) referenced
 * in the primary or autoreply body / subject.
 *
 * @param array<string, mixed> $mail
 * @param array<string, mixed> $mail_2
 * @return list<array<string, string>>
 */
function cf7_canonical_integration_warnings(array $mail, array $mail_2, string $additional_settings_raw): array
{
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    if ($additional_settings_raw !== '') {
        $warnings[] = [
            'path' => 'meta.cf7_additional_settings',
            'reason' => 'CF7 additional_settings is a raw newline-separated key:value blob with no canonical equivalent — preserved verbatim in meta for round-trip but not portable to other form plugins.',
            'severity' => 'informational',
        ];
    }

    $body_sources = [
        ['path' => 'mail_config.body', 'value' => (string) ($mail['body'] ?? '')],
        ['path' => 'mail_config.subject', 'value' => (string) ($mail['subject'] ?? '')],
    ];
    if (cf7_truthy($mail_2['active'] ?? false)) {
        $body_sources[] = ['path' => 'notifications[0].body', 'value' => (string) ($mail_2['body'] ?? '')];
        $body_sources[] = ['path' => 'notifications[0].subject', 'value' => (string) ($mail_2['subject'] ?? '')];
    }
    foreach (cf7_canonical_special_mail_tag_warnings($body_sources) as $warning) {
        $warnings[] = $warning;
    }

    return $warnings;
}

/**
 * Parses a CF7 form template string into canonical fields[] + warnings[].
 *
 * CF7 templates are bracket-syntax: `[<type><*?> <name> "label"? <options...>]`
 * where options are space-separated `key:value`, `key:`-prefixed flags, or
 * bare quoted strings (for choice values). We deliberately do NOT honour
 * `[/tag]` closers for `acceptance` — the closing tag carries the consent
 * label between the open/close pair, but the canonical `label` is filled
 * by the user separately, so we strip closers and surface the label as
 * the first quoted token after the name (CF7's `default:on` convention).
 *
 * Tag types this parser recognises: every entry in cf7-get-tags-schema
 * plus the leading `acceptance`/`response`/`recaptcha` module tags. Tag
 * types outside the recognised set produce a `warning: informational`
 * (unrecognised tag) and are still echoed in fields[] with `type:
 * "unknown"` so the user sees something didn't translate.
 *
 * @return array{fields: list<array<string, mixed>>, warnings: list<array<string, string>>}
 */
function cf7_canonical_parse_form_tags(string $template): array
{
    /** @var list<array<string, mixed>> $fields */
    $fields = [];
    /** @var list<array<string, string>> $warnings */
    $warnings = [];

    if ($template === '') {
        return ['fields' => $fields, 'warnings' => $warnings];
    }

    $matches = [];
    // Match each top-level tag: `[type*? name? options... ]` (closers
    // `[/...]` are filtered via the negative-lookahead `(?!\/)`).
    $count = preg_match_all('/\[(?!\/)([a-z][\w-]*)(\*?)([^\[\]]*)\]/i', $template, $matches, PREG_SET_ORDER);
    if ($count === false || $count === 0) {
        return ['fields' => $fields, 'warnings' => $warnings];
    }

    $field_index = 0;
    foreach ($matches as $match) {
        $result = cf7_canonical_process_tag_match($match, $field_index);
        foreach ($result['warnings'] as $warning) {
            $warnings[] = $warning;
        }
        if ($result['field'] === null) {
            continue;
        }
        $fields[] = $result['field'];
        $field_index++;
    }

    return ['fields' => $fields, 'warnings' => $warnings];
}

/**
 * Processes one preg_match_all hit on the CF7 form template. Returns
 * the canonical field (or null when the tag does not produce a field —
 * `response`, `quiz`, `recaptcha`, malformed) and any warnings to merge
 * into the parser's accumulator.
 *
 * @param array<int|string, string> $match
 * @return array{field: array<string, mixed>|null, warnings: list<array<string, string>>}
 */
function cf7_canonical_process_tag_match(array $match, int $field_index): array
{
    $tag_type = strtolower($match[1] ?? '');
    $required_modifier = ($match[2] ?? '') === '*';
    $body = trim($match[3] ?? '');

    // `response` is an output marker, not an input field — skip silently.
    if ($tag_type === 'response') {
        return ['field' => null, 'warnings' => []];
    }
    // quiz / recaptcha cannot be represented in canonical — emit a
    // warning and skip the field entry entirely.
    if ($tag_type === 'quiz' || $tag_type === 'recaptcha') {
        return [
            'field' => null,
            'warnings' => [cf7_canonical_warning_for_skipped_tag($tag_type, $field_index)],
        ];
    }

    $tokens = cf7_canonical_tokenize_tag_body($body);
    [$name, $name_error] = cf7_canonical_extract_field_name($tag_type, $tokens);
    if ($name_error !== null) {
        return ['field' => null, 'warnings' => [$name_error]];
    }
    if ($tag_type !== 'submit') {
        array_shift($tokens);
    }

    $field = cf7_canonical_field_from_tag($tag_type, $name, $required_modifier, $tokens, $field_index);
    /** @var list<array<string, string>> $warnings */
    $warnings = [];
    if ($field === null) {
        $warnings[] = cf7_canonical_warning_for_unknown_type($tag_type, $field_index);
        $field = cf7_canonical_unknown_field_payload($tag_type, $name);
        if ($required_modifier) {
            $field['required'] = true;
        }
    }
    $optional_warning = cf7_canonical_optional_consent_warning($field, $field_index);
    if ($optional_warning !== null) {
        $warnings[] = $optional_warning;
    }
    return ['field' => $field, 'warnings' => $warnings];
}

/**
 * Builds the informational warning emitted when a CF7 tag type is not
 * in the canonical vocabulary (rare — covers future / module-supplied
 * tags). The caller pairs this with cf7_canonical_unknown_field_payload.
 *
 * @return array<string, string>
 */
function cf7_canonical_warning_for_unknown_type(string $tag_type, int $field_index): array
{
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => sprintf(
            'CF7 [%s] tag type is not recognised by the canonical vocabulary — preserved with type "unknown".',
            $tag_type,
        ),
        'severity' => 'informational',
    ];
}

/**
 * Builds the placeholder canonical field for a CF7 tag type that the
 * canonical vocabulary does not recognise. Marks the field as
 * `type: "unknown"` and surfaces the original tag type in meta so the
 * agent / user can decide how to handle it downstream. The caller
 * applies the `required` modifier separately to keep this helper
 * boolean-flag-free.
 *
 * @return array<string, mixed>
 */
function cf7_canonical_unknown_field_payload(string $tag_type, string $name): array
{
    return [
        'name' => $name,
        'type' => 'unknown',
        'meta' => ['cf7_tag_type' => $tag_type],
    ];
}

/**
 * Returns the informational warning required by SCHEMA-001 / CORR-001
 * when `[acceptance optional]` was seen — canonical `consent` is always
 * required, so the opt-in semantics need explicit acknowledgement.
 * Returns null when the field is not an opt-in consent.
 *
 * @param array<string, mixed> $field
 * @return array<string, string>|null
 */
function cf7_canonical_optional_consent_warning(array $field, int $field_index): ?array
{
    if (($field['type'] ?? '') !== 'consent') {
        return null;
    }
    $meta = isset($field['meta']) && is_array($field['meta']) ? $field['meta'] : [];
    if (($meta['cf7_optional_consent'] ?? false) !== true) {
        return null;
    }
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => 'CF7 [acceptance optional] has no canonical equivalent — canonical consent is always required; the opt-in flag is preserved in meta.cf7_optional_consent for round-trip but will be lost on import to any other plugin.',
        'severity' => 'informational',
    ];
}

/**
 * Builds the warning emitted when a CF7 tag is recognised but cannot
 * be represented in the canonical vocabulary (`quiz`, `recaptcha`).
 *
 * @return array<string, string>
 */
function cf7_canonical_warning_for_skipped_tag(string $tag_type, int $field_index): array
{
    if ($tag_type === 'quiz') {
        return [
            'path' => 'fields[' . $field_index . ']',
            'reason' => 'CF7 [quiz] field has no canonical equivalent — Q/A anti-spam logic is plugin-specific.',
            'severity' => 'structural',
        ];
    }
    return [
        'path' => 'fields[' . $field_index . ']',
        'reason' => 'CF7 [recaptcha] is configured site-globally via the CF7 Integration screen, not per-form — keys cannot be migrated.',
        'severity' => 'informational',
    ];
}

/**
 * Extracts the canonical field name from a CF7 tag's token list. CF7's
 * [submit "Label"] does not carry a field name — the canonical assigns
 * the literal "submit". Returns [name, null] on success, or
 * [empty, warning-payload] when the first token is not a usable name.
 *
 * @param list<string> $tokens
 * @return array{0: string, 1: array<string, string>|null}
 */
function cf7_canonical_extract_field_name(string $tag_type, array $tokens): array
{
    if ($tag_type === 'submit') {
        return ['submit', null];
    }
    $first = $tokens[0] ?? '';
    if ($first === '' || str_contains($first, ':') || str_contains($first, '"')) {
        return [
            '',
            [
                'path' => 'fields[0]',
                'reason' => sprintf('CF7 [%s] tag has no parseable field name — skipped.', $tag_type),
                'severity' => 'structural',
            ],
        ];
    }
    return [$first, null];
}

/**
 * Tokenises a CF7 tag body (the substring between `[type ` and `]`)
 * into a list of tokens, preserving quoted strings as single tokens.
 * Whitespace is the separator; double-quoted runs are joined.
 *
 * @return list<string>
 */
function cf7_canonical_tokenize_tag_body(string $body): array
{
    /** @var list<string> $tokens */
    $tokens = [];
    $length = strlen($body);
    $i = 0;
    while ($i < $length) {
        $ch = $body[$i];
        if (ctype_space($ch)) {
            $i++;
            continue;
        }
        if ($ch === '"') {
            // Walk to the next unescaped closing quote — CF7 supports
            // backslash-escaped quotes inside (`\"`), so respect them.
            $start = $i + 1;
            $j = $start;
            while ($j < $length) {
                if ($body[$j] === '\\' && ($j + 1) < $length) {
                    $j += 2;
                    continue;
                }
                if ($body[$j] === '"') {
                    break;
                }
                $j++;
            }
            $tokens[] = stripslashes(substr($body, $start, $j - $start));
            $i = $j + 1;
            continue;
        }
        $j = $i;
        while ($j < $length && !ctype_space($body[$j])) {
            $j++;
        }
        $tokens[] = substr($body, $i, $j - $i);
        $i = $j;
    }
    return $tokens;
}

/**
 * Maps a single CF7 tag (type + name + tokens) to a canonical field.
 * Returns null when the tag type is outside the known set — the caller
 * records an informational warning and emits a `type: "unknown"` field.
 *
 * @param list<string> $tokens
 * @return array<string, mixed>|null
 */
// @mago-expect lint:no-boolean-flag-parameter
function cf7_canonical_field_from_tag(
    string $tag_type,
    string $name,
    bool $required,
    array $tokens,
    int $field_index,
): ?array {
    $canonical_type = cf7_canonical_type_map($tag_type);
    if ($canonical_type === null) {
        return null;
    }

    // Submit is special: the tokens contain only the button label (a
    // quoted string), and CF7 enforces exactly one [submit] per form.
    if ($canonical_type === 'submit') {
        $label = $tokens === [] ? 'Send' : $tokens[0];
        return [
            'name' => $name,
            'type' => 'submit',
            'label' => $label,
        ];
    }

    $field = [
        'name' => $name,
        'type' => $canonical_type,
    ];
    if ($required) {
        $field['required'] = true;
    }

    $accumulators = cf7_canonical_scan_tokens($canonical_type, $tokens, $field);
    return cf7_canonical_finalize_field($canonical_type, $field, $accumulators);
}

/**
 * Token scan loop split out of cf7_canonical_field_from_tag to keep
 * cyclomatic complexity within Mago's threshold. Returns the four
 * accumulators (validation / choices / cardinality / meta) and may
 * mutate `$field` in place via cf7_canonical_apply_keyed_option /
 * cf7_canonical_apply_bare_flag (placeholder / default writes).
 *
 * @param list<string>         $tokens
 * @param array<string, mixed> $field
 * @return array{validation: array<string, mixed>, choices: list<array{value: string, label: string}>, cardinality: ?string, meta: array<string, mixed>}
 */
function cf7_canonical_scan_tokens(string $canonical_type, array $tokens, array &$field): array
{
    $validation = [];
    /** @var list<array{value: string, label: string}> $choices */
    $choices = [];
    $cardinality = null;
    $extra_meta = [];

    // CF7 syntax quirk: the bare flag `placeholder` (no colon, anywhere
    // in the option list) tells CF7 to USE the next quoted string as
    // the placeholder attribute instead of the default value. Without
    // the flag, the first quoted string is the default. Detect it in a
    // pre-scan so the main loop assigns bare strings correctly.
    $placeholder_flag_seen = in_array('placeholder', array_map('strtolower', $tokens), strict: true);
    $is_choice_tag = in_array($canonical_type, ['select', 'checkbox', 'radio'], strict: true);
    $default_or_placeholder_consumed = false;

    foreach ($tokens as $token) {
        if (str_contains($token, ':')) {
            cf7_canonical_apply_keyed_option($token, $field, $validation, $extra_meta);
            continue;
        }
        if (cf7_canonical_apply_bare_flag($token, $field, $cardinality, $extra_meta)) {
            continue;
        }
        if ($is_choice_tag) {
            $choices[] = ['value' => $token, 'label' => $token];
            continue;
        }
        $first_target = $placeholder_flag_seen ? 'placeholder' : 'default';
        cf7_canonical_consume_bare_string($token, $first_target, $default_or_placeholder_consumed, $field, $extra_meta);
    }

    return [
        'validation' => $validation,
        'choices' => $choices,
        'cardinality' => $cardinality,
        'meta' => $extra_meta,
    ];
}

/**
 * Consumes a single bare-string token on a non-choice tag: the first
 * one fills the field key named by `$first_target` (`'default'` or
 * `'placeholder'`), subsequent ones land in `meta.cf7_extra` for
 * round-trip.
 *
 * @param 'default'|'placeholder' $first_target
 * @param array<string, mixed>    $field
 * @param array<string, mixed>    $extra_meta
 */
// @mago-expect lint:no-boolean-flag-parameter
function cf7_canonical_consume_bare_string(
    string $token,
    string $first_target,
    bool &$default_or_placeholder_consumed,
    array &$field,
    array &$extra_meta,
): void {
    if (!$default_or_placeholder_consumed) {
        $field[$first_target] = $token;
        $default_or_placeholder_consumed = true;
        return;
    }
    if (!isset($extra_meta['cf7_extra']) || !is_array($extra_meta['cf7_extra'])) {
        $extra_meta['cf7_extra'] = [];
    }
    $extra_meta['cf7_extra'][] = $token;
}

/**
 * Attaches the four accumulators back onto the canonical field and
 * applies CF7-specific finalisation: cardinality default for
 * checkbox/select/radio with choices, and the consent-always-required
 * semantics for [acceptance] (except when the optional flag was seen).
 *
 * @param array<string, mixed>                                                                                                                                    $field
 * @param array{validation: array<string, mixed>, choices: list<array{value: string, label: string}>, cardinality: ?string, meta: array<string, mixed>}           $accumulators
 * @return array<string, mixed>
 */
function cf7_canonical_finalize_field(string $canonical_type, array $field, array $accumulators): array
{
    $validation = $accumulators['validation'];
    $choices = $accumulators['choices'];
    $cardinality = $accumulators['cardinality'];
    $extra_meta = $accumulators['meta'];

    if ($validation !== []) {
        $field['validation'] = $validation;
    }
    if ($choices !== []) {
        $field['choices'] = $choices;
    }
    $field['cardinality'] = $cardinality ?? cf7_canonical_default_cardinality($canonical_type, $choices);
    if ($field['cardinality'] === null) {
        unset($field['cardinality']);
    }
    if ($extra_meta !== []) {
        $field['meta'] = $extra_meta;
    }

    // CF7's [acceptance] is always a consent gate — canonical consent
    // semantics override the * modifier handling unless `optional` was
    // explicitly declared.
    if ($canonical_type === 'consent' && !isset($extra_meta['cf7_optional_consent'])) {
        $field['required'] = true;
    }

    return $field;
}

/**
 * Picks the implicit cardinality for choice-bearing canonical types
 * when the source did NOT declare one explicitly. Returns null for
 * non-choice tag types (caller drops the key entirely).
 *
 * @param list<array{value: string, label: string}> $choices
 */
function cf7_canonical_default_cardinality(string $canonical_type, array $choices): ?string
{
    if ($canonical_type === 'radio') {
        return 'single';
    }
    if (in_array($canonical_type, ['checkbox', 'select'], strict: true) && $choices !== []) {
        return $canonical_type === 'checkbox' ? 'multi' : 'single';
    }
    return null;
}

/**
 * Applies a single `key:value` CF7 tag option to the canonical field /
 * validation / meta accumulators. Strips surrounding double-quotes from
 * the value (CF7 accepts both `default:abc` and `default:"abc"`).
 *
 * @param array<string, mixed> $field
 * @param array<string, mixed> $validation
 * @param array<string, mixed> $extra_meta
 */
function cf7_canonical_apply_keyed_option(string $token, array &$field, array &$validation, array &$extra_meta): void
{
    [$key, $value] = explode(separator: ':', string: $token, limit: 2);
    $key = strtolower($key);
    $value = cf7_canonical_strip_quotes($value);
    switch ($key) {
        case 'placeholder':
            $field['placeholder'] = $value;
            return;
        case 'default':
            $field['default'] = $value;
            return;
        case 'minlength':
            $validation['min_length'] = (int) $value;
            return;
        case 'maxlength':
            $validation['max_length'] = (int) $value;
            return;
        case 'min':
            $validation['min'] = cf7_canonical_to_number($value);
            return;
        case 'max':
            $validation['max'] = cf7_canonical_to_number($value);
            return;
        case 'step':
            $validation['step'] = cf7_canonical_to_number($value);
            return;
        case 'filetypes':
            $validation['accepts'] = cf7_canonical_filetypes_to_mimes($value);
            return;
        case 'limit':
            $bytes = cf7_canonical_limit_to_bytes($value);
            if ($bytes !== null) {
                $validation['max_size_bytes'] = $bytes;
            }
            return;
        default:
            // Rendering / HTML attribute hints (id, class, autocomplete,
            // cols, rows, size) and any other unknown key:value pair are
            // preserved in meta for round-trip without polluting canonical.
            $extra_meta[$key] = $value;
    }
}

/**
 * Applies a single bare CF7 tag flag (no colon) to the field accumulators.
 * Returns true if the token was a recognised flag and was consumed;
 * false if the caller should treat it as a bare string (choice value or
 * default/placeholder content).
 *
 * @param array<string, mixed> $field
 * @param array<string, mixed> $extra_meta
 */
function cf7_canonical_apply_bare_flag(string $token, array &$field, ?string &$cardinality, array &$extra_meta): bool
{
    $flag = strtolower($token);
    switch ($flag) {
        case 'multiple':
            $cardinality = 'multi';
            return true;
        case 'exclusive':
            // Checkbox exclusive == single-pick semantics; canonical
            // checkbox already supports cardinality: single.
            $cardinality = 'single';
            $extra_meta['cf7_exclusive'] = true;
            return true;
        case 'include_blank':
            $extra_meta['cf7_include_blank'] = true;
            return true;
        case 'first_as_label':
            $extra_meta['cf7_first_as_label'] = true;
            return true;
        case 'optional':
            // [acceptance optional] — canonical consent is always
            // required; surface the opt-in via a warning at caller
            // level. Carry the hint in meta for lossless round-trip.
            $extra_meta['cf7_optional_consent'] = true;
            unset($field['required']);
            return true;
        case 'use_label_element':
            $extra_meta['cf7_use_label_element'] = true;
            return true;
        case 'placeholder':
            // Already accounted for in the pre-scan; swallow silently.
            return true;
        default:
            return false;
    }
}

/**
 * Strips a single pair of surrounding double-quotes from a `key:value`
 * option value. CF7 accepts both forms (`default:abc` and
 * `default:"abc"`) but the structured canonical should never carry the
 * quote characters as part of the value itself.
 */
function cf7_canonical_strip_quotes(string $value): string
{
    if (strlen($value) >= 2 && $value[0] === '"' && substr($value, offset: -1) === '"') {
        return stripslashes(substr($value, offset: 1, length: -1));
    }
    return $value;
}

/**
 * Casts a numeric-looking string from a CF7 `min:` / `max:` / `step:`
 * option to its native int or float. Returns the original string when
 * it isn't numeric, so the validation entry preserves whatever shape
 * the source author wrote (a downstream consumer can still warn).
 */
function cf7_canonical_to_number(string $value): int|float|string
{
    if (!is_numeric($value)) {
        return $value;
    }
    return str_contains($value, '.') ? (float) $value : (int) $value;
}

/**
 * Maps a CF7 tag type to a canonical form-model-schema type.
 * Returns null when the tag type is not in the canonical vocabulary
 * (caller emits the informational warning).
 */
function cf7_canonical_type_map(string $cf7_type): ?string
{
    return match ($cf7_type) {
        'text' => 'text',
        'email' => 'email',
        'tel' => 'tel',
        'url' => 'url',
        'number' => 'number',
        'date' => 'date',
        'textarea' => 'textarea',
        'select' => 'select',
        'checkbox' => 'checkbox',
        'radio' => 'radio',
        'file' => 'file',
        'acceptance' => 'consent',
        'submit' => 'submit',
        'hidden' => 'hidden',
        default => null,
    };
}

/**
 * Converts CF7's `filetypes:pdf|jpg|png` pipe-separated extension list
 * to a canonical MIME-type array (`["application/pdf", "image/jpeg",
 * "image/png"]`). Unknown extensions are dropped (caller has no clean
 * way to warn per-extension without polluting warnings[]; the user can
 * always re-add MIME entries on the target side).
 *
 * @return list<string>
 */
function cf7_canonical_filetypes_to_mimes(string $filetypes): array
{
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
    foreach (explode('|', strtolower($filetypes)) as $ext) {
        $ext = trim($ext);
        if ($ext === '') {
            continue;
        }
        if (isset($map[$ext])) {
            $out[] = $map[$ext];
        }
    }
    return array_values(array_unique($out));
}

/**
 * Parses CF7's `limit:` value (`2mb`, `100kb`, `1048576`) into bytes.
 * Returns null when the input is unparseable so the caller skips the
 * validation entry rather than persisting a wrong cap.
 */
function cf7_canonical_limit_to_bytes(string $limit): ?int
{
    $limit = strtolower(trim($limit));
    if ($limit === '') {
        return null;
    }
    $match = [];
    if (preg_match('/^(\d+(?:\.\d+)?)\s*(kb|mb|gb|b)?$/', $limit, $match) !== 1) {
        return null;
    }
    // The regex anchors at `^\d+(\.\d+)?` so $match[1] is guaranteed
    // numeric — floatval is safer than (float) cast for the static
    // analyser which can't follow the regex constraint.
    $value = floatval($match[1]);
    $unit = $match[2] ?? 'b';
    $multiplier = match ($unit) {
        'kb' => 1024,
        'mb' => 1024 * 1024,
        'gb' => 1024 * 1024 * 1024,
        default => 1,
    };
    return (int) round($value * $multiplier);
}

/**
 * Maps CF7's primary `mail` array to canonical mail_config{}.
 *
 * @param array<string, mixed> $mail
 * @return array<string, mixed>
 */
function cf7_canonical_mail_config(array $mail): array
{
    return [
        'subject' => (string) ($mail['subject'] ?? ''),
        'sender' => (string) ($mail['sender'] ?? ''),
        'recipient' => (string) ($mail['recipient'] ?? ''),
        'body' => (string) ($mail['body'] ?? ''),
        'body_truncated' => cf7_truthy($mail['body_truncated'] ?? false),
        'body_bytes' => (int) ($mail['body_bytes'] ?? 0),
        'additional_headers' => (string) ($mail['additional_headers'] ?? ''),
        'attachments' => cf7_canonical_parse_attachments($mail['attachments'] ?? ''),
        'use_html' => cf7_truthy($mail['use_html'] ?? false),
        'exclude_blank' => cf7_truthy($mail['exclude_blank'] ?? false),
    ];
}

/**
 * Maps CF7's `mail_2` (secondary autoreply) to canonical notifications[].
 * Returns an empty array when `mail_2.active` is false — preserves the
 * source intent that the autoreply is disabled.
 *
 * @param array<string, mixed> $mail_2
 * @return list<array<string, mixed>>
 */
function cf7_canonical_notifications(array $mail_2): array
{
    if (!cf7_truthy($mail_2['active'] ?? false)) {
        return [];
    }
    return [
        [
            'name' => 'autoreply',
            'active' => true,
            'subject' => (string) ($mail_2['subject'] ?? ''),
            'sender' => (string) ($mail_2['sender'] ?? ''),
            'recipient' => (string) ($mail_2['recipient'] ?? ''),
            'body' => (string) ($mail_2['body'] ?? ''),
            'body_truncated' => cf7_truthy($mail_2['body_truncated'] ?? false),
            'body_bytes' => (int) ($mail_2['body_bytes'] ?? 0),
            'additional_headers' => (string) ($mail_2['additional_headers'] ?? ''),
            'attachments' => cf7_canonical_parse_attachments($mail_2['attachments'] ?? ''),
            'use_html' => cf7_truthy($mail_2['use_html'] ?? false),
            'exclude_blank' => cf7_truthy($mail_2['exclude_blank'] ?? false),
        ],
    ];
}

/**
 * Parses the CF7 `attachments` blob into a canonical list of field
 * names. CF7 stores them as a whitespace/comma-separated string with
 * each name wrapped in square brackets (`[your-cv]`); we strip the
 * brackets so the canonical carries pure field-name tokens.
 *
 * @param mixed $raw
 * @return list<string>
 */
function cf7_canonical_parse_attachments(mixed $raw): array
{
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    $parts = preg_split(pattern: '/[\s,]+/', subject: $raw);
    if ($parts === false) {
        return [];
    }
    $out = [];
    foreach ($parts as $entry) {
        $entry = trim($entry, characters: " \t\n\r\0\x0B[]");
        if ($entry !== '') {
            $out[] = $entry;
        }
    }
    return $out;
}

/**
 * Scans the mail body/subject strings for CF7's special mail-tags
 * (`[_serial_number]`, `[_remote_ip]`, ...) and emits one informational
 * warning per occurrence so the user knows they will not survive a
 * cross-plugin migration without manual replacement.
 *
 * @param list<array{path: string, value: string}> $sources
 * @return list<array<string, string>>
 */
function cf7_canonical_special_mail_tag_warnings(array $sources): array
{
    $special = [
        '_serial_number',
        '_remote_ip',
        '_user_agent',
        '_date',
        '_time',
        '_url',
        '_post_title',
        '_user_email',
    ];
    /** @var list<array<string, string>> $warnings */
    $warnings = [];
    foreach ($sources as $source) {
        foreach ($special as $tag) {
            if (!str_contains($source['value'], '[' . $tag . ']')) {
                continue;
            }
            $warnings[] = [
                'path' => $source['path'],
                'reason' => sprintf(
                    'CF7 special mail-tag [%s] has no canonical equivalent — preserved verbatim, but the target plugin must provide its own equivalent on import.',
                    $tag,
                ),
                'severity' => 'informational',
            ];
        }
    }
    return $warnings;
}
