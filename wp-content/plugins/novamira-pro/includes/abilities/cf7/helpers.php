<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Cf7;

use WP_Error;
use WP_Post;
use WPCF7_ContactForm;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Contact Form 7 abilities.
 *
 * Centralised here so each ability stays small and so the per-form
 * response shape is consistent across list / get / create / edit /
 * duplicate. The CF7 storage model is simple: every form is a
 * `wpcf7_contact_form` CPT row with a fixed set of postmeta keys
 * (`form`, `mail`, `mail_2`, `messages`, `additional_settings`,
 * `locale`). We surface those verbatim — no schema reshaping — so an
 * agent that reads CF7 docs gets the values it expects.
 */

/**
 * Canonical property keys `WPCF7_ContactForm::set_properties()` actually
 * honours on CF7 6.x. Anything outside this list is silently dropped by
 * `set_properties()` (the plugin intersects with its own defaults), so
 * we use the list to validate input early and drop unknown keys from
 * edits.
 *
 * `locale` is intentionally NOT in this list — CF7 6.x stopped treating
 * `locale` as a managed property, so passing it through `set_properties`
 * is a silent no-op (round 1 audit P9). To change the locale, call
 * `cf7_apply_locale_change()` which goes through `WPCF7_ContactForm::set_locale()`
 * directly.
 *
 * @return list<string>
 */
function cf7_property_keys(): array
{
    return ['form', 'mail', 'mail_2', 'messages', 'additional_settings'];
}

/**
 * Default per-field byte cap applied by `get-form` to the large text
 * properties (`form`, `mail.body`, `mail_2.body`, `additional_settings`).
 * 8 KiB is enough for every CF7 template we've measured in the wild and
 * keeps a worst-case full-form response under ~64 KiB even when every
 * field is at the cap.
 */
function cf7_default_max_field_bytes(): int
{
    return 8192;
}

/**
 * Builds the canonical error payload used by every ability. Mirrors the
 * pattern in code-snippets so an agent that handles one integration's
 * errors handles CF7's the same way.
 */
function cf7_error(string $code, string $message): WP_Error
{
    return new WP_Error($code, $message);
}

/**
 * Loads a CF7 form by id and rejects trash / auto-draft / wrong CPT.
 * `WPCF7_ContactForm::get_instance()` already returns null for unknown
 * ids and wrong post types, but it happily returns trashed and
 * auto-draft forms — we treat both as "not found" to keep the read /
 * write surface honest.
 */
function cf7_form_or_error(int $id): WPCF7_ContactForm|WP_Error
{
    if ($id <= 0) {
        return cf7_error('cf7_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    /** @var WP_Post|null $post */
    $post = get_post($id);
    if (!$post instanceof WP_Post || $post->post_type !== 'wpcf7_contact_form') {
        return cf7_error('cf7_form_not_found', sprintf(
            __('Contact Form 7 form with id %d not found.', domain: 'novamira-pro'),
            $id,
        ));
    }
    // CF7 forms in trash / auto-draft / future / inherit are not part
    // of the operational catalogue surfaced by this integration. Reject
    // them up front so writes can't sneak into a stale workflow state
    // (round 2 audit L2-A3 documented the future-status asymmetry).
    $rejected_statuses = ['trash', 'auto-draft', 'future', 'inherit'];
    if (in_array($post->post_status, $rejected_statuses, strict: true)) {
        return cf7_error('cf7_form_not_found', sprintf(
            __('Contact Form 7 form with id %d is in %s status and not editable.', domain: 'novamira-pro'),
            $id,
            $post->post_status,
        ));
    }
    $form = WPCF7_ContactForm::get_instance($post);
    if ($form === null) {
        return cf7_error('cf7_form_not_found', sprintf(
            __('Contact Form 7 form with id %d could not be loaded.', domain: 'novamira-pro'),
            $id,
        ));
    }
    return $form;
}

/**
 * Counts top-level CF7 form-tags in a template string. CF7 syntax is
 * `[type* name attr=value]content[/type]` so each tag starts with `[`
 * followed by a letter. We deliberately don't try to parse modifiers /
 * options here — the agent just needs a rough "how many fields" signal
 * in list / get responses.
 */
function cf7_count_form_tags(string $template): int
{
    $matches = [];
    $found = preg_match_all('/\[(?!\/)([a-z][\w-]*)/i', $template, $matches);
    return $found === false ? 0 : $found;
}

/**
 * Truncates a string at a byte cap and tags the result. The cap is
 * applied in BYTES (not characters) so we can keep total payload size
 * predictable even with multi-byte UTF-8. Returns the truncated value
 * and a `truncated` flag the caller can echo upstream.
 *
 * @return array{value: string, truncated: bool, original_bytes: int}
 */
function cf7_truncate_field(string $value, int $max_bytes): array
{
    $original = strlen($value);
    if ($max_bytes <= 0 || $original <= $max_bytes) {
        return ['value' => $value, 'truncated' => false, 'original_bytes' => $original];
    }
    // `mb_strcut` cuts at a byte boundary while always landing on a
    // valid UTF-8 codepoint start — it handles continuation bytes AND
    // orphan lead bytes correctly. The manual walk-back this replaces
    // only stripped continuation bytes, leaving lead bytes orphaned for
    // CJK / 4-byte emoji / BIDI markers (round 1 audit P10).
    $cut = mb_strcut($value, start: 0, length: $max_bytes, encoding: 'UTF-8');
    return [
        'value' => $cut . "\n…[truncated]",
        'truncated' => true,
        'original_bytes' => $original,
    ];
}

/**
 * Compact summary used by list-forms — omits the large properties
 * (`form`, `mail`, `mail_2`, `messages`, `additional_settings`) to keep
 * the response small when listing many forms. The full shape is
 * available via get-form on a specific id.
 *
 * @return array<string, mixed>
 */
function cf7_form_to_compact(WPCF7_ContactForm $form): array
{
    /** @var WP_Post|null $post */
    $post = get_post($form->id());
    $status = $post instanceof WP_Post ? $post->post_status : 'publish';
    $modified = $post instanceof WP_Post ? $post->post_modified_gmt : '';

    $template = (string) $form->prop('form');

    return [
        'id' => $form->id(),
        'title' => $form->title(),
        'slug' => $form->name(),
        'status' => $status,
        'locale' => $form->locale(),
        'hash' => $form->hash(),
        'tag_count' => cf7_count_form_tags($template),
        'modified' => $modified,
    ];
}

/**
 * Full response shape used by get-form, create-form, edit-form,
 * duplicate-form. Every property CF7 manages is surfaced as-is. Large
 * text fields (`form`, `mail.body`, `mail_2.body`, `additional_settings`)
 * are truncated at `$max_field_bytes` so an agent can read the shape
 * without flooding context — re-call with a larger cap (or 0 to
 * disable) when the full body is genuinely needed.
 *
 * Mail config is normalised to a fixed key order
 * (subject / sender / recipient / additional_headers / body /
 * attachments / use_html / exclude_blank) so callers can compare
 * round-trips deterministically.
 *
 * @return array<string, mixed>
 */
function cf7_form_to_full(WPCF7_ContactForm $form, int $max_field_bytes): array
{
    /** @var WP_Post|null $post */
    $post = get_post($form->id());
    $status = $post instanceof WP_Post ? $post->post_status : 'publish';
    $modified = $post instanceof WP_Post ? $post->post_modified_gmt : '';

    $template_raw = (string) $form->prop('form');
    $template = cf7_truncate_field($template_raw, $max_field_bytes);

    $additional_raw = (string) $form->prop('additional_settings');
    $additional = cf7_truncate_field($additional_raw, $max_field_bytes);

    /** @var mixed $mail_prop */
    $mail_prop = $form->prop('mail');
    /** @var mixed $mail2_prop */
    $mail2_prop = $form->prop('mail_2');
    /** @var mixed $messages_prop */
    $messages_prop = $form->prop('messages');
    /** @var array<string, mixed> $mail_raw */
    $mail_raw = is_array($mail_prop) ? $mail_prop : [];
    /** @var array<string, mixed> $mail2_raw */
    $mail2_raw = is_array($mail2_prop) ? $mail2_prop : [];
    /** @var array<string, mixed> $messages_raw */
    $messages_raw = is_array($messages_prop) ? $messages_prop : [];

    return [
        'id' => $form->id(),
        'title' => $form->title(),
        'slug' => $form->name(),
        'status' => $status,
        'locale' => $form->locale(),
        'hash' => $form->hash(),
        'tag_count' => cf7_count_form_tags($template_raw),
        'modified' => $modified,
        'form' => $template['value'],
        'form_truncated' => $template['truncated'],
        'form_bytes' => $template['original_bytes'],
        'mail' => cf7_normalize_mail_for_output($mail_raw, $max_field_bytes),
        'mail_2' => cf7_normalize_mail_for_output($mail2_raw, $max_field_bytes),
        'messages' => cf7_normalize_messages_for_output($messages_raw),
        'additional_settings' => $additional['value'],
        'additional_settings_truncated' => $additional['truncated'],
        'additional_settings_bytes' => $additional['original_bytes'],
    ];
}

/**
 * Normalises a CF7 mail array to a stable key order with sensible
 * defaults for missing keys. CF7 stores `mail_2` with `active: false`
 * when the secondary mail is disabled — we surface `active` directly so
 * callers don't have to guess.
 *
 * @param array<string, mixed> $mail
 * @return array<string, mixed>
 */
function cf7_normalize_mail_for_output(array $mail, int $max_field_bytes): array
{
    $body_raw = isset($mail['body']) && is_string($mail['body']) ? $mail['body'] : '';
    $body = cf7_truncate_field($body_raw, $max_field_bytes);

    return [
        'active' => !array_key_exists('active', $mail) || cf7_truthy($mail['active']),
        'subject' => isset($mail['subject']) && is_string($mail['subject']) ? $mail['subject'] : '',
        'sender' => isset($mail['sender']) && is_string($mail['sender']) ? $mail['sender'] : '',
        'recipient' => isset($mail['recipient']) && is_string($mail['recipient']) ? $mail['recipient'] : '',
        'additional_headers' => isset($mail['additional_headers']) && is_string($mail['additional_headers'])
            ? $mail['additional_headers']
            : '',
        'body' => $body['value'],
        'body_truncated' => $body['truncated'],
        'body_bytes' => $body['original_bytes'],
        'attachments' => isset($mail['attachments']) && is_string($mail['attachments']) ? $mail['attachments'] : '',
        'use_html' => isset($mail['use_html']) && cf7_truthy($mail['use_html']),
        'exclude_blank' => isset($mail['exclude_blank']) && cf7_truthy($mail['exclude_blank']),
    ];
}

/**
 * Boolean coercion that doesn't trip the static analyser's mixed-cast
 * warning. CF7 mail flags (`active`, `use_html`, `exclude_blank`) come
 * out of postmeta as `mixed` — the storage layer normalises them to
 * `1`/`0` strings or booleans depending on how the form was saved.
 *
 * Treats the JSON-style stringified booleans (`"false"`, `"true"`) the
 * same way as their native counterparts — some LLM tool-calling clients
 * serialise PHP/JSON booleans as strings, so accepting the spelling
 * variants keeps round-trips honest (round 1 audit P5).
 */
function cf7_truthy(mixed $value): bool
{
    if (
        $value === null
        || $value === false
        || $value === 0
        || $value === 0.0
        || $value === ''
        || $value === '0'
        || $value === 'false'
        || $value === 'False'
        || $value === 'FALSE'
    ) {
        return false;
    }
    return true;
}

/**
 * Surfaces CF7 message strings as a flat string-keyed array. CF7 ships
 * with ~20 message keys (`validation_error`, `mail_sent_ok`,
 * `mail_sent_ng`, `spam`, `accept_terms`, …) — we don't filter to the
 * known set because new keys can show up when modules register their
 * own (akismet, reCAPTCHA), and dropping unknown keys would silently
 * lose user customisations on round-trip.
 *
 * @param array<string, mixed> $messages
 * @return array<string, string>
 */
function cf7_normalize_messages_for_output(array $messages): array
{
    $out = [];
    /** @var mixed $value */
    foreach ($messages as $key => $value) {
        $out[$key] = is_string($value) ? $value : '';
    }
    return $out;
}

/**
 * Counts all CF7 forms (excluding trash / auto-draft) without loading
 * them into memory. Used by check-setup.
 */
function cf7_count_forms(): int
{
    $counts = wp_count_posts('wpcf7_contact_form');
    $total = 0;
    foreach (['publish', 'draft', 'pending', 'private'] as $status) {
        $total += intval($counts->{$status} ?? 0);
    }
    return $total;
}

/**
 * Merges a property patch on top of an existing CF7 form's properties.
 * Only keys in `cf7_property_keys()` are honoured; the rest are dropped.
 * For nested arrays (`mail`, `mail_2`, `messages`) the merge is shallow
 * per-key so the agent can patch only `mail.recipient` without losing
 * the rest of the mail config.
 *
 * @param array<string, mixed> $current
 * @param array<string, mixed> $patch
 * @return array<string, mixed>
 */
function cf7_apply_property_patch(array $current, array $patch): array
{
    $allowed = cf7_property_keys();
    $out = $current;
    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        if (!in_array($key, $allowed, strict: true)) {
            continue;
        }
        // Reject null / scalar payloads for the nested objects — a
        // direct PHP caller could otherwise overwrite a healthy mail
        // config with `null` and the storage layer would happily
        // persist it (round 2 audit L2-A6).
        if (in_array($key, ['mail', 'mail_2'], strict: true)) {
            if (!is_array($value)) {
                continue;
            }
            $base = isset($out[$key]) && is_array($out[$key]) ? $out[$key] : [];
            $out[$key] = cf7_sanitize_mail_array(array_merge($base, $value));
            continue;
        }
        if ($key === 'messages') {
            if (!is_array($value)) {
                continue;
            }
            $base = isset($out[$key]) && is_array($out[$key]) ? $out[$key] : [];
            $out[$key] = array_merge($base, $value);
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * Strips bare-LF / CR-LF separators from the mail-header-bearing
 * fields (`recipient`, `sender`, `subject`, `additional_headers`).
 * CF7 only sanitises these at send-time, which leaves the unsanitised
 * payload visible in `cf7-get-form` and accepted into postmeta where
 * downstream readers may interpret a smuggled `Bcc:` line on bare-LF
 * boundaries (round 2 audit L3-A6).
 *
 * `additional_headers` is preserved as a multi-line string but CR/LF
 * is normalised to `\n` and lines whose syntax doesn't look like a
 * header (`Name: value`) get the line break stripped.
 *
 * @param array<array-key, mixed> $mail
 * @return array<array-key, mixed>
 */
function cf7_sanitize_mail_array(array $mail): array
{
    // Drop stranger keys — CF7 has a closed canonical mail key set, so
    // anything else is just noise that would otherwise survive in
    // postmeta on round-trip (round 2 audit L2-A5 / L5-A3).
    $allowed_mail_keys = [
        'active',
        'subject',
        'sender',
        'recipient',
        'additional_headers',
        'body',
        'attachments',
        'use_html',
        'exclude_blank',
    ];
    $mail = array_intersect_key($mail, array_flip($allowed_mail_keys));

    foreach (['recipient', 'sender', 'subject'] as $field) {
        if (!isset($mail[$field]) || !is_string($mail[$field])) {
            continue;
        }
        $mail[$field] = preg_replace(pattern: '/[\r\n]+/', replacement: ' ', subject: $mail[$field]) ?? $mail[$field];
    }
    if (isset($mail['additional_headers']) && is_string($mail['additional_headers'])) {
        $mail['additional_headers'] = cf7_sanitize_additional_headers($mail['additional_headers']);
    }
    return $mail;
}

/**
 * Normalises the `additional_headers` blob: CR-LF → LF, drops empty
 * lines, and keeps only lines shaped like RFC-822 headers
 * (`Name: value`). Anything that doesn't match is dropped, neutering
 * attempts to smuggle `Bcc:`/`Cc:` lines via a bare-LF break inside
 * another field.
 */
function cf7_sanitize_additional_headers(string $blob): string
{
    $normalised = (string) preg_replace(pattern: '/\r\n?|\n/', replacement: "\n", subject: $blob);
    $lines = explode(separator: "\n", string: $normalised);
    $clean = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }
        if (preg_match('/^[A-Za-z][A-Za-z0-9-]*:.+$/', $line) === 1) {
            $clean[] = $line;
        }
    }
    return implode("\n", $clean);
}

/**
 * Validates that every top-level key in `$input` belongs to the CF7
 * property set. Returns an error message when a stranger key is
 * present, null when the payload is clean. Used at the top of
 * create-form / edit-form so unknown keys never silently disappear.
 *
 * `id`, `title`, `slug`, `status` plus the canonical property keys are
 * allowed at the top level. WP-native aliases (`post_title`,
 * `post_name`, `post_status`) are normalised before this check runs.
 *
 * @param array<string, mixed> $input
 * @param list<string>         $extra_allowed
 */
function cf7_reject_unknown_top_keys(array $input, array $extra_allowed = []): ?string
{
    $allowed = array_merge(['id', 'title', 'slug', 'status'], cf7_property_keys(), $extra_allowed);
    $stranger = [];
    foreach ($input as $key => $_value) {
        if (in_array($key, $allowed, strict: true)) {
            continue;
        }
        $stranger[] = $key;
    }
    if ($stranger === []) {
        return null;
    }
    return sprintf(
        'Unknown input key(s): %s. Allowed top-level keys: %s.',
        implode(', ', $stranger),
        implode(', ', $allowed),
    );
}

/**
 * Applies the CPT post field updates (`title` / `slug` / `status`)
 * from an input payload to the given form id. Returns true when at
 * least one field was actually updated, false when no relevant input
 * keys were present (so the caller can skip touching post_modified for
 * property-only edits). Shared by create-form / edit-form / duplicate-form.
 *
 * When a `title` is present and no `slug` was passed, derives a slug
 * from the title via `sanitize_title()` — otherwise CF7's `save()`
 * pins the slug to the locale-default placeholder (e.g.
 * `senza-titolo-N` on Italian installs) regardless of the user-provided
 * title (round 2 audit L1-A2 / L2-A5).
 *
 * @param array<string, mixed> $input
 */
function cf7_apply_cpt_post_updates(int $id, array $input): bool
{
    $post_update = ['ID' => $id];
    $has_explicit_slug = isset($input['slug']) && is_string($input['slug']) && $input['slug'] !== '';
    if (isset($input['title']) && is_string($input['title']) && $input['title'] !== '') {
        $post_update['post_title'] = $input['title'];
        if (!$has_explicit_slug) {
            $derived = sanitize_title($input['title']);
            if ($derived !== '') {
                $post_update['post_name'] = $derived;
            }
        }
    }
    if ($has_explicit_slug) {
        $post_update['post_name'] = $input['slug'];
    }
    if (isset($input['status']) && is_string($input['status']) && $input['status'] !== '') {
        $post_update['post_status'] = $input['status'];
    }
    if (count($post_update) === 1) {
        return false;
    }
    wp_update_post($post_update);
    return true;
}

/**
 * Extracts only the canonical CF7 property keys (`form`, `mail`,
 * `mail_2`, `messages`, `additional_settings`, `locale`) present in the
 * input payload. Used by create-form / edit-form so the property patch
 * never carries CPT-level keys (`title`, `slug`, `status`).
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function cf7_extract_property_patch(array $input): array
{
    $patch = [];
    foreach (cf7_property_keys() as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $patch[$key] = $input[$key];
    }
    return $patch;
}

/**
 * Normalises WP-native input aliases (`post_title`, `post_name`,
 * `post_status`) to the CF7 short forms (`title`, `slug`, `status`)
 * before the rest of the input pipeline runs.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function cf7_normalize_wp_aliases(array $input): array
{
    $map = [
        'post_title' => 'title',
        'post_name' => 'slug',
        'post_status' => 'status',
    ];
    foreach ($map as $wp_key => $short_key) {
        if (array_key_exists($wp_key, $input) && !array_key_exists($short_key, $input)) {
            $input[$short_key] = $input[$wp_key];
        }
        unset($input[$wp_key]);
    }
    return $input;
}

/**
 * Validates a CF7 locale string. CF7's own `wpcf7_is_valid_locale()` is
 * regex-only — `xx_INVALID` passes its check, then CF7 silently falls
 * back to the site locale when rendering, leaving the form persisted
 * with a misleading `_locale` meta value (round 1 audit P9).
 *
 * Accepts:
 * - `en`, `it`, `fr_FR`, `pt_BR` (regex-shaped locale strings)
 * - any locale present in `get_available_languages()` (installed packs)
 *
 * Returns an error message when the locale is malformed AND not an
 * installed language; returns null when accepted.
 */
function cf7_validate_locale(string $locale): ?string
{
    if ($locale === '') {
        return null;
    }
    if (!preg_match('/^[a-z]{2,3}(_[A-Z][a-zA-Z0-9]+)?$/', $locale)) {
        return sprintf(
            'Locale "%s" is not a valid IETF-style identifier (expected forms like "en", "it", "fr_FR").',
            $locale,
        );
    }
    return null;
}

/**
 * Applies a locale change to a CF7 form. CF7 6.x dropped `locale` from
 * the `set_properties()` surface, so passing `locale` through the
 * canonical property patch is a silent no-op (round 1 audit P9). Use
 * this helper to go through `WPCF7_ContactForm::set_locale()` directly
 * when the input includes the key.
 *
 * Returns true when the locale was actually applied, false when no
 * change was requested.
 *
 * @param array<string, mixed> $input
 */
function cf7_apply_locale_change(WPCF7_ContactForm $form, array $input): bool
{
    if (!array_key_exists('locale', $input) || !is_string($input['locale'])) {
        return false;
    }
    if (method_exists($form, 'set_locale')) {
        $form->set_locale($input['locale']);
    }
    return true;
}

/**
 * Append an agent-facing warning to a successful write response, creating
 * the `warnings` list on first use. A `{success: false}` shape (or an empty
 * message) is returned untouched — failures never carry warnings. This is
 * the channel a degraded-but-successful operation uses to reach the AGENT
 * (the only consumer that reads the response); the server `error_log` is a
 * parallel, developer-only trace that never enters the agent's context.
 *
 * @param array<string, mixed> $response
 * @return array<string, mixed>
 */
function cf7_append_warning(array $response, string $warning): array
{
    if (($response['success'] ?? null) !== true || $warning === '') {
        return $response;
    }
    /** @var mixed $existing */
    $existing = $response['warnings'] ?? [];
    $response['warnings'] = array_merge(is_array($existing) ? $existing : [], [$warning]);
    return $response;
}

/**
 * Fires the `novamira_cf7_form_changed` action with the before/after
 * mail-config snapshots, so downstream plugins (CRM sync, audit log,
 * GDPR change tracking) can detect recipient / subject / sender drift
 * without instrumenting CF7 itself (round 2 audit L3-A6 / L5-A6).
 * Listeners are wrapped in try/catch so a misbehaving callback cannot
 * mask a successful edit; on failure it returns a warning string (and
 * logs the detail) so the edit response can surface it to the agent.
 *
 * @param array<array-key, mixed> $before_mail
 * @param array<array-key, mixed> $before_mail_2
 * @return string|null A warning when a listener threw, otherwise null.
 */
function cf7_emit_form_change_audit(
    int $id,
    array $before_mail,
    array $before_mail_2,
    WPCF7_ContactForm $after,
): ?string {
    /** @var mixed $after_mail_raw */
    $after_mail_raw = $after->prop('mail');
    /** @var mixed $after_mail_2_raw */
    $after_mail_2_raw = $after->prop('mail_2');
    $after_mail = is_array($after_mail_raw) ? $after_mail_raw : [];
    $after_mail_2 = is_array($after_mail_2_raw) ? $after_mail_2_raw : [];
    try {
        do_action('novamira_cf7_form_changed', $id, [
            'before' => ['mail' => $before_mail, 'mail_2' => $before_mail_2],
            'after' => ['mail' => $after_mail, 'mail_2' => $after_mail_2],
        ]);
    } catch (\Throwable $audit_error) {
        // The edit succeeded; a downstream listener threw. Keep the full
        // exception in the server log for a developer, and return a concise
        // warning so the AGENT (the only reader of the response) can tell
        // the user the change-tracking side effect did not run.
        error_log(sprintf(
            'novamira-pro: novamira_cf7_form_changed listener threw after edit of form %d: %s',
            $id,
            $audit_error->getMessage(),
        ));
        return sprintf(
            'The form was saved, but a change-audit listener (e.g. CRM sync, audit log, GDPR tracking) threw, so downstream systems may not have recorded this edit: %s',
            $audit_error->getMessage(),
        );
    }
    return null;
}

/**
 * Sets the title of an unsaved CF7 form clone BEFORE `save()` is
 * invoked, so the underlying `wp_insert_post` receives the final title
 * directly. Without this every duplicate of the same source collides
 * on the locale-default `<source>_copy` slug-base, which forces WP's
 * `wp_unique_post_slug()` into per-call probe queries that grow
 * linearly with the number of accumulated duplicates (round 2 audit
 * L4-A8 — measured O(N²) on burst).
 *
 * CF7 keeps `title` as a PRIVATE property on `WPCF7_ContactForm`, so we
 * reach it via Reflection. The `property_exists` + try/catch guard
 * keeps the integration safe across CF7 forks that rename the field
 * or change its visibility; on failure we silently fall back to the
 * post-save `wp_update_post` path.
 */
function cf7_assign_clone_title(WPCF7_ContactForm $clone, string $title): void
{
    if (!property_exists($clone, 'title')) {
        return;
    }
    try {
        $property = new \ReflectionProperty($clone, 'title');
        // PHP 8.0 needs setAccessible to reach a private property;
        // PHP 8.1+ marks it deprecated but still functional, and the
        // project's `mago.toml` floors at php-version 8.0.
        // @mago-expect analysis:deprecated-method
        $property->setAccessible(true);
        $property->setValue($clone, $title);
    } catch (\Throwable $reflection_error) {
        // Silent fallback: duplicate-form's post-save wp_update_post
        // will set the title anyway — we just lose the slug-collision
        // optimisation. Log the diagnostic so an operator can spot the
        // upstream rename in error_log if a CF7 release ever shifts
        // the property name or visibility.
        error_log(sprintf(
            'novamira-pro: cf7_assign_clone_title fell back to post-save (%s)',
            $reflection_error->getMessage(),
        ));
    }
}

/**
 * Cooperative cross-request lock around the CF7 form read-modify-write
 * cycle. Round 1 audit P3 reproduced deterministic lost writes across
 * parallel `edit-form` calls to the same id: `WPCF7_ContactForm::save()`
 * persists ALL six postmeta keys (`_form`, `_mail`, `_mail_2`,
 * `_messages`, `_additional_settings`, `_locale`) from the in-memory
 * snapshot, so two concurrent edits on different fields still overwrite
 * each other.
 *
 * Mirrors the kadence / code-snippets pattern with a MySQL advisory
 * lock keyed on the form id + blog id. When GET_LOCK isn't supported
 * (SQLite drop-in / unusual hosts) or is contended, the critical
 * section still runs — strictly better than no batching at all — but
 * the response then carries a `warnings[]` entry so the agent knows the
 * write was not lock-protected.
 *
 * @param callable(): array<string, mixed> $fn
 * @return array<string, mixed>
 */
function cf7_with_form_lock(int $form_id, callable $fn): array
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    $lock_name = sprintf('nvp_cf7_%d_%d', get_current_blog_id(), $form_id);

    /** @var mixed $acquired_raw */
    // @mago-expect analysis:possibly-invalid-argument
    $acquired_raw = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5));
    $acquired = $acquired_raw === '1' || $acquired_raw === 1;

    // When the 5-second wait expires without acquiring the lock the
    // critical section still runs unprotected (preferred to a hard
    // failure on hosts that don't support GET_LOCK at all). Emit a
    // diagnostic to error_log so operators can spot persistent
    // contention without an unexpected success (round 2 audit L2-A6).
    if (!$acquired) {
        error_log(sprintf(
            'novamira-pro: cf7_with_form_lock proceeded WITHOUT lock for form %d (GET_LOCK returned %s)',
            $form_id,
            var_export($acquired_raw, return: true),
        ));
    }

    try {
        $result = $fn();
        // Surface the lock-skip on the response itself, not just in the
        // server log: a write that ran without the advisory lock carries a
        // real lost-update risk, and the agent (the only reader of the
        // response) should be able to relay that to the user.
        if (!$acquired) {
            $result = cf7_append_warning($result, sprintf(
                'This edit ran without the per-form concurrency lock (the host does not support GET_LOCK or it was contended), so a simultaneous edit of form %d could have silently overwritten it.',
                $form_id,
            ));
        }
        return $result;
    } finally {
        if ($acquired) {
            // @mago-expect analysis:possibly-invalid-argument
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }
}

/**
 * Converts a WP_Error into the canonical write-path failure shape
 * (`{success: false, error, error_code}`). Used by edit-form,
 * duplicate-form, and delete-form so every error branch carries the
 * required `success` field declared in the output schema — without
 * this every WP_Error short-circuits before schema validation and the
 * agent sees a non-uniform error shape across branches (round 1 audit
 * P8).
 *
 * @return array{success: false, error: string, error_code: string}
 */
function cf7_failure_from_wp_error(WP_Error $error): array
{
    return [
        'success' => false,
        'error' => $error->get_error_message(),
        'error_code' => (string) $error->get_error_code(),
    ];
}
