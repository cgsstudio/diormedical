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

/**
 * Field-type slugs the Lite edition ships out of the box. Used by check-setup
 * to advertise what callers can put in a form on this install. Pro adds the
 * wpforms_pro_field_types() set on top.
 *
 * @return list<string>
 */
function wpforms_lite_field_types(): array
{
    return [
        'text',
        'textarea',
        'select',
        'checkbox',
        'radio',
        'name',
        'email',
        'number',
        'number-slider',
        'gdpr-checkbox',
        'internal-information',
        'divider',
        'html',
        'content',
        'pagebreak',
        'hidden',
    ];
}

/**
 * Field-type slugs Pro adds on top of Lite. Reported by check-setup ONLY when
 * Pro is active so an agent never picks a type the install cannot render.
 *
 * @return list<string>
 */
function wpforms_pro_field_types(): array
{
    return [
        'address',
        'date-time',
        'phone',
        'rating',
        'richtext',
        'url',
        'file-upload',
        'camera',
        'password',
        'signature',
        'credit-card',
        'payment-single',
        'payment-multiple',
        'payment-checkbox',
        'payment-select',
        'payment-total',
        'entry-preview',
        'repeater',
        'layout',
        'custom-captcha',
    ];
}

/**
 * The field types this install ACTUALLY exposes (Lite + Pro on Pro installs).
 *
 * @return list<string>
 */
function wpforms_available_field_types(): array
{
    $types = wpforms_lite_field_types();
    if (wpforms_pro_active()) {
        $types = array_merge($types, wpforms_pro_field_types());
    }
    return array_values(array_unique($types));
}

/**
 * Detect WPForms presence, version, edition. Cached per request.
 *
 * Round-1 audit CRITICAL: cache is keyed by blog_id so multisite installs
 * with per-site WPForms activation (or different editions across sites)
 * don't return a stale read from the wrong site. The previous static
 * scalar would have served Site A's "pro: true" to a request resolved
 * against Site B that has Lite (or no WPForms at all).
 *
 * @return array{active: bool, version: string|null, pro: bool}
 */
function wpforms_detect_setup(): array
{
    /** @var array<int, array{active: bool, version: string|null, pro: bool}> $cache */
    static $cache = [];

    $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
    if (isset($cache[$blog_id])) {
        return $cache[$blog_id];
    }

    $active = wpforms_min_runtime_available();

    return $cache[$blog_id] = [
        'active' => $active,
        'version' => $active ? (string) constant('WPFORMS_VERSION') : null,
        'pro' => $active && wpforms_pro_active(),
    ];
}

/**
 * Standard "WPForms is not active" error.
 */
function wpforms_not_active(): WP_Error
{
    return new WP_Error('wpforms_not_active', 'WPForms is not active or does not meet the minimum supported version.', [
        'status' => 400,
    ]);
}

/**
 * Standard "this needs WPForms Pro" error (entries, premium field types).
 */
function wpforms_pro_required(string $feature): WP_Error
{
    return new WP_Error('wpforms_pro_required', "{$feature} requires WPForms Pro.", ['status' => 400]);
}

/**
 * Check a WPForms-native capability and return a 403 WP_Error when the
 * current user lacks it. Delegates to WPForms' canonical
 * wpforms_current_user_can() helper rather than bare current_user_can() so
 * the `wpforms_current_user_can` filter chain fires — admins reliably pass
 * via the manage_options → wpforms_* mapping WPForms registers, instead of
 * the bare cap lookup that only sees roles where the cap was explicitly
 * stored on the role row.
 *
 * When $resource_id is non-zero the WPForms access checker additionally fires
 * the `wpforms_user_can` filter chain — the documented extension point team /
 * multi-author setups hook to grant or deny per-form / per-entry access. Pass
 * the resolved form_id (for form-scoped caps) or entry_id (for entry-scoped
 * caps) here AFTER resolving the row via wpforms_form_or_error /
 * wpforms_entry_or_error so that "form not found" surfaces as 404 before any
 * 403 from the cap check.
 *
 * Accepts both short (`create_forms`) and long (`wpforms_create_forms`) cap
 * names. Mutation abilities call this AFTER the active-runtime check so an
 * inactive install fails with the canonical wpforms_not_active code first.
 */
function wpforms_capability_or_error(string $capability, int $resource_id = 0): ?WP_Error
{
    if (function_exists('wpforms_current_user_can') && \wpforms_current_user_can($capability, $resource_id)) {
        return null;
    }
    return new WP_Error(
        'wpforms_capability_missing',
        sprintf("Current user lacks the required '%s' capability.", $capability),
        ['status' => 403],
    );
}

/**
 * Standard "invalid input" error wrapper with a consistent prefix.
 */
function wpforms_invalid_input(string $message): WP_Error
{
    return new WP_Error('wpforms_invalid_input', $message, ['status' => 400]);
}

/**
 * Typed error returned by wpforms_persist_form() when its CAS-guarded UPDATE
 * matches zero rows — meaning the form's post_modified_gmt no longer matches
 * the timestamp the caller captured at the start of the read-modify-write
 * cycle. Another writer (the WPForms admin UI, a parallel ability call) won
 * the race; the caller should re-fetch the form and retry rather than blindly
 * overwriting the newer state. Status 409 (Conflict) so REST clients can
 * distinguish this from a generic 500.
 */
function wpforms_concurrent_modification_error(int $form_id): WP_Error
{
    return new WP_Error(
        'wpforms_concurrent_modification',
        sprintf(
            'WPForms form %d was modified by another writer between read and persist — re-fetch via wpforms-get-form and retry.',
            $form_id,
        ),
        ['status' => 409],
    );
}

/**
 * Typed error returned by wpforms_persist_entry_with_cas() when its CAS-guarded
 * UPDATE on wp_wpforms_entries matches zero rows — another writer (a form
 * submission persist, the WPForms admin "edit entry" surface, a parallel
 * ability call) bumped the entry between the caller's read and this persist.
 * The caller should re-fetch the entry and retry. Status 409 (Conflict).
 */
function wpforms_concurrent_entry_modification_error(int $entry_id): WP_Error
{
    return new WP_Error(
        'wpforms_concurrent_modification',
        sprintf(
            'WPForms entry %d was modified by another writer between read and persist — re-fetch via wpforms-get-entry and retry.',
            $entry_id,
        ),
        ['status' => 409],
    );
}

/**
 * Resolve a form id to its CPT row, rejecting non-existent ids and posts of any
 * other type. Accepts an int, integer-valued float, or all-digit string id.
 */
function wpforms_form_or_error(mixed $form_id): WP_Post|WP_Error
{
    $id = wpforms_to_int($form_id);
    if ($id <= 0) {
        return wpforms_invalid_input('form_id must be a positive integer.');
    }
    /** @var WP_Post|null $post */
    $post = get_post($id);
    if (!$post instanceof WP_Post || $post->post_type !== 'wpforms') {
        return new WP_Error('wpforms_form_not_found', "WPForms form not found: {$id}", ['status' => 404]);
    }
    if ($post->post_status === 'trash' || $post->post_status === 'auto-draft') {
        return new WP_Error('wpforms_form_unavailable', "WPForms form {$id} is in the {$post->post_status} state.", [
            'status' => 400,
        ]);
    }
    return $post;
}

/**
 * Coerce a mixed value to a non-negative integer (0 when not numeric or out of
 * range). Used for form/entry ids that may arrive as int/string/float from JSON.
 */
function wpforms_to_int(mixed $raw): int
{
    if (!is_numeric($raw)) {
        return 0;
    }
    $float = (float) $raw;
    if (!is_finite($float) || $float >= (float) PHP_INT_MAX || $float < 0.0) {
        return 0;
    }
    return (int) $raw;
}

/**
 * Decode a form CPT row's post_content (JSON) to a flat associative array.
 *
 * Distinguishes three states so a corrupt blob never silently degrades to "empty
 * form" — which would let a subsequent write wipe every field/notification/
 * confirmation the form previously had:
 *
 * - empty post_content        → []
 * - parseable JSON object     → the decoded array
 * - non-empty + unparseable   → WP_Error('wpforms_form_corrupt', status 500)
 *
 * Every mutator MUST bail on the error path; reads SHOULD surface it as the
 * `wpforms_form_corrupt` code instead of returning a misleading empty payload.
 *
 * @return array<array-key, mixed>|WP_Error
 */
function wpforms_decode_blob(WP_Post $form): array|WP_Error
{
    if ($form->post_content === '') {
        return [];
    }
    // Probe parseability with json_decode FIRST — wpforms_decode() returns []
    // on unparseable input (the same shape a legitimately-empty {} blob
    // produces), so it cannot distinguish corruption from emptiness on its
    // own. json_decode returning null on a non-empty post_content is the
    // signal we need to surface wpforms_form_corrupt before any caller
    // silently proceeds with empty fields[] (which a write would then
    // persist, wiping the original blob).
    /** @var mixed $probe */
    $probe = json_decode($form->post_content, associative: true);
    if (!is_array($probe)) {
        return new WP_Error(
            'wpforms_form_corrupt',
            sprintf('WPForms form %d has a corrupt or unparseable post_content blob.', (int) $form->ID),
            ['status' => 500],
        );
    }
    // Now that we know the blob is parseable, prefer the WPForms-native
    // decoder for the slash/key normalisation it applies on top of JSON.
    if (function_exists('wpforms_decode')) {
        /** @var mixed $decoded */
        $decoded = \wpforms_decode($form->post_content);
        if (is_array($decoded) && $decoded !== []) {
            return $decoded;
        }
    }
    return $probe;
}

/**
 * Decode a form CPT row's post_content (JSON) to a normalised shape with the
 * canonical top-level keys always present (empty when not seeded). Propagates
 * the corrupt-blob error from wpforms_decode_blob() so callers never have to
 * defend against a missing key OR against silent data corruption.
 *
 * @return array{
 *     id: int,
 *     fields: array<array-key, mixed>,
 *     settings: array<array-key, mixed>,
 *     notifications: array<array-key, mixed>,
 *     confirmations: array<array-key, mixed>,
 *     meta: array<array-key, mixed>,
 *     payments: array<array-key, mixed>,
 * }|WP_Error
 */
function wpforms_decode_form(WP_Post $form): array|WP_Error
{
    $data = wpforms_decode_blob($form);
    if ($data instanceof WP_Error) {
        return $data;
    }
    $settings = wpforms_section($data, key: 'settings');

    return [
        'id' => (int) $form->ID,
        'fields' => wpforms_section($data, key: 'fields'),
        'settings' => $settings,
        'notifications' => wpforms_section($settings, key: 'notifications'),
        'confirmations' => wpforms_section($settings, key: 'confirmations'),
        'meta' => wpforms_section($data, key: 'meta'),
        'payments' => wpforms_section($data, key: 'payments'),
    ];
}

/**
 * Read one top-level section from a decoded form payload, falling back to []
 * when the key is missing or not an array.
 *
 * @param array<array-key, mixed> $data
 * @return array<array-key, mixed>
 */
function wpforms_section(array $data, string $key): array
{
    /** @var mixed $value */
    $value = $data[$key] ?? null;
    return is_array($value) ? $value : [];
}

/**
 * Compact list-row shape for a form CPT — the small payload list-forms returns.
 * entry_count is null on Lite (no entries table) OR null when the caller did
 * not supply a precomputed value (e.g. single-form callers like create-form).
 *
 * Callers iterating over many forms SHOULD precompute counts via
 * wpforms_form_entry_counts_batch() and pass them in to avoid the N+1 COUNT
 * round-trip the unparameterised path would trigger.
 *
 * @param int|null $entry_count Precomputed entry count, or null to look it up
 *                              individually (single-form path).
 * @return array{
 *     id: int,
 *     title: string,
 *     slug: string,
 *     status: string,
 *     date_created: string,
 *     date_modified: string,
 *     entry_count: int|null,
 * }
 */
function wpforms_form_row(WP_Post $form, ?int $entry_count = null): array
{
    return [
        'id' => (int) $form->ID,
        'title' => $form->post_title,
        'slug' => $form->post_name,
        'status' => $form->post_status,
        'date_created' => $form->post_date_gmt,
        'date_modified' => $form->post_modified_gmt,
        'entry_count' => $entry_count ?? wpforms_form_entry_count((int) $form->ID),
    ];
}

/**
 * Batched entry-count lookup for a set of form ids. Replaces the per-row
 * COUNT(*) fan-out that drove list-forms's N+1 finding (audit batch 1 HIGH).
 *
 * Single GROUP BY query against wpforms_entries; form ids absent from the
 * result get a 0 count so the caller's keyed lookup is total. Returns null
 * on Lite (no entries table) so wpforms_form_row's default-null branch keeps
 * the legacy "entry_count: null when unavailable" contract.
 *
 * @param list<int> $form_ids
 * @return array<int, int>|null  map form_id → count, or null on Lite/missing table
 */
function wpforms_form_entry_counts_batch(array $form_ids): ?array
{
    if (!wpforms_pro_active() || !function_exists('wpforms') || $form_ids === []) {
        return $form_ids === [] ? [] : null;
    }
    global $wpdb;
    /** @var \wpdb $wpdb */
    $ids = array_values(array_unique(array_filter(array_map('intval', $form_ids), fn($id) => $id > 0)));
    if ($ids === []) {
        return [];
    }
    // $ids are intval()+positive-filtered above so direct interpolation is safe;
    // skipping $wpdb->prepare() avoids its literal-string requirement and lets
    // us pass a variable-length IN(...) list without per-cardinality templates.
    $ids_csv = implode(separator: ',', array: $ids);
    /** @var list<object{form_id: string|int, c: string|int}>|null $rows */
    $rows = $wpdb->get_results(
        "SELECT form_id, COUNT(*) AS c FROM {$wpdb->prefix}wpforms_entries WHERE form_id IN ({$ids_csv}) GROUP BY form_id",
    );
    if (!is_array($rows)) {
        return null;
    }
    /** @var array<int, int> $out */
    $out = array_fill_keys(keys: $ids, value: 0);
    foreach ($rows as $row) {
        $out[(int) $row->form_id] = (int) $row->c;
    }
    return $out;
}

/**
 * Encode a form definition array as the JSON blob WPForms stores in
 * post_content. Prefers the WPForms-native encoder (which adds editor-side
 * sanitisation in some versions) and falls back to a plain JSON encode.
 *
 * Returns WP_Error('wpforms_encode_failed', status 500) when BOTH the
 * WPForms encoder and wp_json_encode() fail — refusing to fall back to a
 * literal '{}' that would silently wipe the form's entire definition on
 * persist.
 *
 * @param array<array-key, mixed> $data
 */
function wpforms_encode_form(array $data): string|WP_Error
{
    if (function_exists('wpforms_encode')) {
        /** @var mixed $encoded */
        $encoded = \wpforms_encode($data);
        if (is_string($encoded)) {
            return $encoded;
        }
    }
    $json = wp_json_encode($data);
    if (is_string($json)) {
        return $json;
    }
    return new WP_Error(
        'wpforms_encode_failed',
        'Failed to encode WPForms form definition — refusing to persist an empty blob.',
        ['status' => 500],
    );
}

/**
 * Default empty form blob for a brand-new form — the canonical top-level keys
 * WPForms expects. Settings overrides merge on top of these defaults.
 *
 * @param array<string, mixed> $settings_overrides
 * @return array<string, mixed>
 */
function wpforms_blank_form_blob(string $title, string $description, array $settings_overrides): array
{
    $settings = array_replace(wpforms_default_settings($title, $description), $settings_overrides);
    return [
        'id' => 0,
        'field_id' => 0,
        'fields' => [],
        'settings' => $settings,
        'meta' => ['template' => 'blank'],
        'payments' => [],
    ];
}

/**
 * Default settings block for a new form — covers the keys the WPForms admin UI
 * seeds on create. Notifications and confirmations get one default entry each
 * so the form is functional out of the box.
 *
 * @return array<string, mixed>
 */
function wpforms_default_settings(string $title, string $description): array
{
    return [
        'form_title' => $title,
        'form_desc' => $description,
        'form_class' => '',
        'submit_text' => 'Submit',
        'submit_text_processing' => 'Sending...',
        'anti_spam' => '1',
        'ajax_submit' => '1',
        'notifications' => [
            '1' => [
                'notification_name' => 'Default Notification',
                'email' => '{admin_email}',
                'subject' => sprintf('New %s Entry', $title),
                'sender_name' => '{site_name}',
                'sender_address' => '{admin_email}',
                'replyto' => '',
                'message' => '{all_fields}',
            ],
        ],
        'confirmations' => [
            '1' => [
                'name' => 'Default Confirmation',
                'type' => 'message',
                'message' => '<p>Thanks for contacting us! We will be in touch with you shortly.</p>',
                'message_scroll' => '1',
            ],
        ],
    ];
}

/**
 * Persist a decoded form definition back into its CPT row. Keeps the in-blob
 * id in sync with the post id (WPForms relies on that for several admin
 * surfaces) and clears the post cache so a follow-up read sees the new state.
 *
 * When $expected_modified_gmt is non-null the UPDATE is CAS-guarded against
 * the form's post_modified_gmt: a parallel writer (admin save, another
 * ability call) that bumped the timestamp between the caller's read and this
 * persist will cause zero rows to match and return wpforms_concurrent_modification
 * instead of silently overwriting the newer state. Edit-path callers MUST
 * pass the timestamp they captured before decoding the blob; create-path
 * callers (where no race is possible — the post id is private until they
 * return) pass null to keep wp_update_post()'s full save_post hook chain.
 *
 * @param array<array-key, mixed> $blob
 */
function wpforms_persist_form(int $form_id, array $blob, ?string $expected_modified_gmt = null): bool|WP_Error
{
    $blob['id'] = $form_id;
    $encoded = wpforms_encode_form($blob);
    if ($encoded instanceof WP_Error) {
        return $encoded;
    }
    // Pass unslashed JSON: the Abilities API hands input straight to the
    // callback without the magic-quote treatment wp_insert_post expects from
    // form posts, so a wp_slash() pre-pass here would double-escape every
    // quote (a stored {\"id\":...} that wpforms_decode can no longer parse).
    if ($expected_modified_gmt === null) {
        $updated = wp_update_post([
            'ID' => $form_id,
            'post_content' => $encoded,
        ], wp_error: true);
        if ($updated instanceof WP_Error) {
            return $updated;
        }
        clean_post_cache($form_id);
        return true;
    }
    return wpforms_persist_form_with_cas($form_id, $encoded, $expected_modified_gmt);
}

/**
 * CAS-guarded UPDATE on wp_posts.post_content for the edit path of
 * wpforms_persist_form(). Split out so the parent function stays under the
 * cyclomatic-complexity threshold and the no-CAS branch reads top-down.
 */
function wpforms_persist_form_with_cas(int $form_id, string $encoded, string $expected_modified_gmt): bool|WP_Error
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    $now_local = current_time('mysql');
    $now_gmt = current_time('mysql', gmt: true);
    // wpforms_encode() pre-slashes its JSON for compatibility with
    // wp_update_post() (which strips one layer via wp_unslash before re-
    // escaping for DB write). $wpdb->update() handles escaping itself and
    // does NOT strip — pass slashed input here and the stored JSON ends
    // up with literal backslashes that break wpforms_decode on read-back.
    $payload = wp_unslash($encoded);
    $result = $wpdb->update(
        $wpdb->posts,
        [
            'post_content' => $payload,
            'post_modified' => $now_local,
            'post_modified_gmt' => $now_gmt,
        ],
        [
            'ID' => $form_id,
            'post_modified_gmt' => $expected_modified_gmt,
        ],
    );
    if ($result === false) {
        return new WP_Error(
            'wpforms_persist_db_error',
            sprintf('Database error persisting WPForms form %d.', $form_id),
            ['status' => 500],
        );
    }
    if ($result === 0) {
        return wpforms_concurrent_modification_error($form_id);
    }
    clean_post_cache($form_id);
    return true;
}

/**
 * Compute the next available field id for a form blob. WPForms tracks the
 * monotonic counter in the top-level `field_id` key; we add max(existing keys)
 * as a defensive lower bound in case a hand-imported form has gaps.
 *
 * @param array<array-key, mixed> $blob
 */
function wpforms_next_field_id(array $blob): int
{
    $counter = is_numeric($blob['field_id'] ?? null) ? (int) $blob['field_id'] : 0;
    $fields = is_array($blob['fields'] ?? null) ? $blob['fields'] : [];
    return max($counter, wpforms_max_int_key($fields)) + 1;
}

/**
 * Compute the next available id for a collection keyed by string-int (the
 * pattern WPForms uses for settings.notifications, settings.confirmations,
 * etc.). Unlike wpforms_next_field_id, there is no top-level counter — the
 * collection's own max key drives the id.
 *
 * @param array<array-key, mixed> $collection
 */
function wpforms_next_collection_id(array $collection): int
{
    return wpforms_max_int_key($collection) + 1;
}

/**
 * Largest int-valued key in an associative array, or 0 when none. Mago infers
 * array<array-key,*> keys as `mixed`, so coerce before comparing.
 *
 * @param array<array-key, mixed> $collection
 */
function wpforms_max_int_key(array $collection): int
{
    $max = 0;
    foreach (array_keys($collection) as $key) {
        if (!is_numeric($key)) {
            continue;
        }
        $max = max($max, (int) $key);
    }
    return $max;
}

/**
 * Field keys that callers MUST NOT be able to set via the `options` map. These
 * are either structural (id/type — set by the helper, agent overriding them
 * lets one field impersonate another id or shapeshift to a different type) or
 * already handled by named top-level params (label/description/required/size/
 * css/conditional_logic — letting `options` override them would silently win
 * over the explicit input). The helpers SILENTLY drop these keys; ability
 * layers that want fail-loud behaviour can call wpforms_reserved_key_error().
 *
 * @return list<string>
 */
function wpforms_reserved_field_keys(): array
{
    return ['id', 'type', 'label', 'description', 'required', 'size', 'css', 'conditional_logic'];
}

/**
 * Standard error for an ability that wants to fail-loud when an agent tries
 * to set a reserved field key via `options`. The helpers themselves drop the
 * key silently; this is the opt-in stricter behaviour.
 */
function wpforms_reserved_key_error(string $key): WP_Error
{
    return new WP_Error(
        'wpforms_reserved_field_key',
        sprintf("'%s' is a reserved field key and cannot be set via options.", $key),
        ['status' => 400],
    );
}

/**
 * Build a normalised WPForms field entry from agent input. The output uses
 * STRING keys (WPForms stores ids as strings in the JSON blob), normalises
 * common cross-cutting options (label, description, required, css,
 * conditional_logic), and accepts arbitrary additional keys for type-specific
 * options — except the reserved ones (see wpforms_reserved_field_keys), which
 * are silently dropped to prevent agent input from shadowing structural state.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function wpforms_build_field(int $field_id, string $type, array $input): array
{
    $field = [
        'id' => (string) $field_id,
        'type' => $type,
        'label' => isset($input['label']) ? (string) $input['label'] : '',
        'description' => isset($input['description']) ? (string) $input['description'] : '',
        'required' => wpforms_bool_string($input['required'] ?? false),
        'size' => isset($input['size']) ? (string) $input['size'] : 'medium',
        'css' => isset($input['css']) ? (string) $input['css'] : '',
    ];
    if (isset($input['conditional_logic'])) {
        $field['conditional_logic'] = wpforms_bool_string($input['conditional_logic']);
    }
    $options = is_array($input['options'] ?? null) ? $input['options'] : [];
    $reserved = wpforms_reserved_field_keys();
    /** @var mixed $value */
    foreach ($options as $key => $value) {
        if (!is_string($key) || $key === '') {
            continue;
        }
        if (in_array($key, $reserved, strict: true)) {
            continue;
        }
        $field[$key] = $value;
    }
    return $field;
}

/**
 * Apply a partial-merge update to an existing field. Cross-cutting keys are
 * normalised in the same way wpforms_build_field() handles them on create;
 * type-specific options merge in flat under their own key. Returns the list
 * of top-level keys that changed.
 *
 * @param array<string, mixed> $field
 * @param array<string, mixed> $input
 * @return list<string>
 */
function wpforms_apply_field_update(array &$field, array $input): array
{
    /** @var list<string> $touched */
    $touched = [];
    foreach (['label', 'description', 'size', 'css'] as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $next = (string) $input[$key];
        if ($next === ($field[$key] ?? null)) {
            continue;
        }
        $field[$key] = $next;
        $touched[] = $key;
    }
    foreach (['required', 'conditional_logic'] as $key) {
        if (!array_key_exists($key, $input)) {
            continue;
        }
        $coerced = wpforms_bool_string($input[$key]);
        if (($field[$key] ?? null) === $coerced) {
            continue;
        }
        $field[$key] = $coerced;
        $touched[] = $key;
    }
    $options = is_array($input['options'] ?? null) ? $input['options'] : [];
    return [...$touched, ...wpforms_apply_field_options_update($field, $options)];
}

/**
 * Apply the per-key options merge on a field. Split out of
 * wpforms_apply_field_update() to keep that function under the cyclomatic
 * threshold. Skips reserved keys (deny-list) and no-op writes (equality with
 * current value) — the equality check is the side-effect-layer idempotency
 * fix the batch 2 audit flagged.
 *
 * @param array<string, mixed>    $field
 * @param array<array-key, mixed> $options
 * @return list<string>
 */
function wpforms_apply_field_options_update(array &$field, array $options): array
{
    /** @var list<string> $touched */
    $touched = [];
    $reserved = wpforms_reserved_field_keys();
    /** @var mixed $value */
    foreach ($options as $key => $value) {
        if (!is_string($key) || $key === '' || in_array($key, $reserved, strict: true)) {
            continue;
        }
        if (array_key_exists($key, $field) && $field[$key] === $value) {
            continue;
        }
        $field[$key] = $value;
        $touched[] = "options.{$key}";
    }
    return $touched;
}

/**
 * Strip CR/LF from a value destined for an SMTP header (sender_name, subject,
 * replyto, sender_address). Prevents email-header injection — without this an
 * input like "X\r\nBcc: attacker@x" would inject an extra Bcc into every
 * notification email WPForms sends.
 */
function wpforms_strip_crlf(string $value): string
{
    return str_replace(search: ["\r", "\n"], replace: '', subject: $value);
}

/**
 * Sanitize a comma-separated email list (the shape WPForms uses for
 * notification recipients). Splits on commas, trims each entry, runs
 * sanitize_email() on real addresses while preserving WPForms smart tags
 * ({admin_email}, {field_id="N"}, etc.) that would not survive a plain
 * sanitize_email() call, drops empties, then re-joins with ", ".
 */
function wpforms_sanitize_email_list(string $value): string
{
    $parts = explode(',', $value);
    /** @var list<string> $clean */
    $clean = [];
    foreach ($parts as $part) {
        $trimmed = trim($part);
        if ($trimmed === '') {
            continue;
        }
        if (str_contains($trimmed, '{') && str_contains($trimmed, '}')) {
            // Preserve WPForms smart tags as-is; only strip CRLF for safety.
            $clean[] = wpforms_strip_crlf($trimmed);
            continue;
        }
        $email = sanitize_email($trimmed);
        if ($email === '') {
            continue;
        }
        $clean[] = $email;
    }
    return implode(', ', $clean);
}

/**
 * Sanitize an HTML body (notification message, confirmation message). Wraps
 * wp_kses_post() so the allowed-tag set matches what a regular post can hold —
 * matches WPForms' own behaviour in its admin UI, which lets users author
 * rich messages but rejects <script> / <iframe> / on* handlers.
 */
function wpforms_sanitize_html_message(string $value): string
{
    return wp_kses_post($value);
}

/**
 * Type-aware sanitization per notification field. Centralised so EVERY write
 * surface that touches a notification (manage-notifications create/edit AND
 * edit-form via top-level `notifications` AND edit-form via settings merge AND
 * create-form via settings_overrides) routes through the same per-field
 * policy. Defined in helpers.php (loaded first) so callers later in the load
 * order can reach it without a load-order dance.
 */
function wpforms_sanitize_notification_field(string $key, string $value): string
{
    return match ($key) {
        'email' => wpforms_sanitize_email_list($value),
        'message' => wpforms_sanitize_html_message($value),
        'subject', 'sender_name', 'sender_address', 'replyto' => sanitize_text_field(wpforms_strip_crlf($value)),
        default => sanitize_text_field($value),
    };
}

/**
 * Sanitize a single notification entry (associative array) using
 * wpforms_sanitize_notification_field() per known key. Unknown keys pass
 * through untouched — WPForms ships additional notification options (smart
 * tags, file-upload attachments, conditional logic blocks) that we do not
 * want to mangle.
 *
 * @param array<array-key, mixed> $entry
 * @return array<array-key, mixed>
 */
function wpforms_sanitize_notification_entry(array $entry): array
{
    foreach ([
        'notification_name',
        'email',
        'subject',
        'sender_name',
        'sender_address',
        'replyto',
        'message',
    ] as $field) {
        if (!isset($entry[$field]) || !is_string($entry[$field])) {
            continue;
        }
        $entry[$field] = wpforms_sanitize_notification_field($field, $entry[$field]);
    }
    return $entry;
}

/**
 * Apply wpforms_sanitize_notification_entry() across every entry in a
 * notifications map (the shape settings.notifications uses: string-int keys →
 * notification entry). Non-array entries pass through unchanged so a corrupt
 * map structure surfaces upstream rather than being silently rewritten.
 *
 * @param array<array-key, mixed> $notifications
 * @return array<array-key, mixed>
 */
function wpforms_sanitize_notifications_map(array $notifications): array
{
    /** @var array<array-key, mixed> $clean */
    $clean = [];
    /** @var mixed $entry */
    foreach ($notifications as $key => $entry) {
        $clean[$key] = is_array($entry) ? wpforms_sanitize_notification_entry($entry) : $entry;
    }
    return $clean;
}

/**
 * Sanitize a single confirmation entry. Per-field policy differs from
 * notifications: `name` and `message` cover every type, plus per-type fields
 * (`redirect` URL, `page` int). Keys not listed pass through — WPForms has
 * type-specific options (message_scroll, scroll_anchor) that need no
 * transformation.
 *
 * @param array<array-key, mixed> $entry
 * @return array<array-key, mixed>
 */
function wpforms_sanitize_confirmation_entry(array $entry): array
{
    $entry = wpforms_sanitize_confirmation_text_field($entry, key: 'name');
    if (isset($entry['message']) && is_string($entry['message'])) {
        $entry['message'] = wpforms_sanitize_html_message($entry['message']);
    }
    if (isset($entry['redirect']) && is_string($entry['redirect'])) {
        $entry['redirect'] = esc_url_raw($entry['redirect']);
    }
    return $entry;
}

/**
 * Apply sanitize_text_field + CRLF strip to one key of a confirmation entry,
 * preserving the rest. Split out so wpforms_sanitize_confirmation_entry()
 * stays under the halstead-difficulty threshold.
 *
 * @param array<array-key, mixed> $entry
 * @return array<array-key, mixed>
 */
function wpforms_sanitize_confirmation_text_field(array $entry, string $key): array
{
    if (isset($entry[$key]) && is_string($entry[$key])) {
        $entry[$key] = sanitize_text_field(wpforms_strip_crlf($entry[$key]));
    }
    return $entry;
}

/**
 * Apply wpforms_sanitize_confirmation_entry() across every entry in a
 * confirmations map.
 *
 * @param array<array-key, mixed> $confirmations
 * @return array<array-key, mixed>
 */
function wpforms_sanitize_confirmations_map(array $confirmations): array
{
    /** @var array<array-key, mixed> $clean */
    $clean = [];
    /** @var mixed $entry */
    foreach ($confirmations as $key => $entry) {
        $clean[$key] = is_array($entry) ? wpforms_sanitize_confirmation_entry($entry) : $entry;
    }
    return $clean;
}

/**
 * Validate a confirmation `page` target. Returns the canonical int id on
 * success, WP_Error on any reason the page would be unusable as a post-
 * submit redirect target: non-positive id, non-existent post, status of
 * trash/auto-draft/inherit, or a post_type WPForms cannot link to (revision,
 * attachment, or any private post_type). Shared by create-confirmation and
 * edit-confirmation so the two surfaces enforce the same rule.
 */
function wpforms_validate_confirmation_page(mixed $raw): int|WP_Error
{
    $page_id = (int) $raw;
    if ($page_id <= 0) {
        return wpforms_invalid_input('page (target post id) must be a positive integer.');
    }
    /** @var WP_Post|null $post */
    $post = get_post($page_id);
    if (!$post instanceof WP_Post) {
        return wpforms_invalid_input("page {$page_id} does not exist.");
    }
    if (in_array($post->post_status, ['trash', 'auto-draft', 'inherit'], strict: true)) {
        return wpforms_invalid_input(
            "page {$page_id} is in the {$post->post_status} state — pick a publish/draft/private post.",
        );
    }
    if (in_array($post->post_type, ['revision', 'attachment', 'nav_menu_item'], strict: true)) {
        return wpforms_invalid_input("page {$page_id} is a {$post->post_type} — pick a viewable post type instead.");
    }
    return $page_id;
}

/**
 * Validate a confirmation `redirect` URL. Returns the URL-safe sanitised
 * string on success, WP_Error on empty input or a protocol esc_url_raw()
 * strips (javascript:, data:, file: → empty). Shared by create-confirmation
 * and edit-confirmation so disallowed-protocol payloads cannot silently
 * persist as empty strings.
 */
function wpforms_validate_confirmation_redirect(mixed $raw): string|WP_Error
{
    $trimmed = trim((string) $raw);
    if ($trimmed === '') {
        return wpforms_invalid_input('redirect (target URL) is required when type=redirect.');
    }
    $escaped = esc_url_raw($trimmed);
    if ($escaped === '') {
        return wpforms_invalid_input(
            'redirect URL is empty after sanitisation — disallowed protocol or malformed scheme.',
        );
    }
    return $escaped;
}

/**
 * Apply notification + confirmation map sanitization to a settings-override
 * payload coming from create-form (input.settings) or any other surface that
 * accepts a partial settings shape. Returns the same array with the relevant
 * sub-blocks sanitized in place — non-array sub-blocks are left alone so a
 * malformed shape surfaces upstream rather than being silently rewritten.
 *
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function wpforms_sanitize_settings_overrides(array $settings): array
{
    if (isset($settings['notifications']) && is_array($settings['notifications'])) {
        $settings['notifications'] = wpforms_sanitize_notifications_map($settings['notifications']);
    }
    if (isset($settings['confirmations']) && is_array($settings['confirmations'])) {
        $settings['confirmations'] = wpforms_sanitize_confirmations_map($settings['confirmations']);
    }
    return $settings;
}

/**
 * WPForms stores boolean flags as the strings "0" / "1" (legacy admin form
 * convention). Coerce a mixed agent input — bool, int, "true"/"false",
 * "yes"/"no", "1"/"0" — to that shape.
 */
function wpforms_bool_string(mixed $raw): string
{
    if (is_bool($raw)) {
        return $raw ? '1' : '0';
    }
    if (is_numeric($raw)) {
        return (int) $raw !== 0 ? '1' : '0';
    }
    if (is_string($raw)) {
        return in_array(strtolower($raw), ['1', 'true', 'yes', 'on'], strict: true) ? '1' : '0';
    }
    return '0';
}

/**
 * Entry count for a form id, or null when entries are unavailable (Lite, or
 * the entry handler is missing). Used by list-forms / check-setup output.
 */
function wpforms_form_entry_count(int $form_id): ?int
{
    if (!wpforms_pro_active() || !function_exists('wpforms')) {
        return null;
    }
    /** @var mixed $entry */
    $entry = \wpforms()->obj('entry');
    if (!is_object($entry) || !method_exists($entry, 'get_entries')) {
        return null;
    }
    /** @var mixed $count */
    $count = $entry->get_entries(['form_id' => $form_id], true);
    return is_numeric($count) ? (int) $count : null;
}

/**
 * Validate a fields map (the {field_id => field_def} dict that
 * edit-form swaps into the blob's fields[]). Round-1 audit CRITICAL —
 * before this guard an agent could land a malformed fields[] that
 * either crashes the WPForms renderer at preview time or silently
 * smuggled a Pro-only field type into a Lite install.
 *
 * Rejects:
 *   - non-array entries
 *   - entries missing the required `id`/`type` keys
 *   - field types not present in the per-edition catalogue
 *     (`wpforms_field_type_schema` returns null on Lite for Pro types)
 *   - key/id mismatch (e.g. `["7" => ["id" => 9, …]]`)
 *
 * @param array<array-key, mixed> $fields
 */
function wpforms_validate_fields_map(array $fields): ?WP_Error
{
    /** @var mixed $entry */
    foreach ($fields as $key => $entry) {
        $key_str = (string) $key;
        if (!is_array($entry)) {
            return wpforms_invalid_input(sprintf('fields[%s] must be an object.', $key_str));
        }
        if (!isset($entry['id'])) {
            return wpforms_invalid_input(sprintf('fields[%s] missing required `id`.', $key_str));
        }
        if (!isset($entry['type']) || !is_string($entry['type']) || $entry['type'] === '') {
            return wpforms_invalid_input(sprintf('fields[%s] missing required `type`.', $key_str));
        }
        if ((string) $entry['id'] !== $key_str) {
            return wpforms_invalid_input(sprintf(
                'fields[%s].id (%s) does not match the map key.',
                $key_str,
                (string) $entry['id'],
            ));
        }
        $type_str = $entry['type'];
        if (wpforms_field_type_schema($type_str) === null) {
            return wpforms_invalid_input(sprintf(
                'fields[%s] uses unknown / unavailable type `%s`.',
                $key_str,
                $type_str,
            ));
        }
    }
    return null;
}

/**
 * Resolve the field-type schema for a single type slug. Returns null when the
 * type is unknown on this install (Lite asked for a Pro type, or a typo).
 *
 * @return array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}|null
 */
function wpforms_field_type_schema(string $type): ?array
{
    $catalog = wpforms_field_type_catalog();
    if (!array_key_exists($type, $catalog)) {
        return null;
    }
    if ($catalog[$type]['edition'] === 'pro' && !wpforms_pro_active()) {
        return null;
    }
    return $catalog[$type];
}

/**
 * The full field-type catalog: per-slug descriptor with edition + supported
 * options. Split across helpers so each chunk stays under the cyclomatic-
 * complexity threshold and the catalog reads top-down.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_field_type_catalog(): array
{
    return array_merge(
        wpforms_catalog_lite_text(),
        wpforms_catalog_lite_choice(),
        wpforms_catalog_lite_structural(),
        wpforms_catalog_pro_inputs(),
        wpforms_catalog_pro_payments(),
        wpforms_catalog_pro_structural(),
    );
}

/**
 * @return list<string>
 */
function wpforms_base_supports(): array
{
    return ['label', 'description', 'required', 'css', 'conditional_logic'];
}

/**
 * @return array<string, array{description: string, type: string}>
 */
function wpforms_choice_options(): array
{
    return [
        'choices' => ['description' => 'Array of {label, value, image?} entries.', 'type' => 'array'],
        'choices_images' => ['description' => 'Enable image choices.', 'type' => 'boolean'],
        'random' => ['description' => 'Randomise the choice order on render.', 'type' => 'boolean'],
    ];
}

/**
 * Lite text/value inputs. Split into 2 chunks (string-style + numeric-and-misc)
 * so each chunk stays under the halstead-volume threshold.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_lite_text(): array
{
    return array_merge(wpforms_catalog_lite_string_inputs(), wpforms_catalog_lite_value_inputs());
}

/**
 * Lite string-style inputs: text, textarea, name, email.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_lite_string_inputs(): array
{
    $supports = wpforms_base_supports();
    return [
        'text' => [
            'type' => 'text',
            'label_text' => 'Single Line Text',
            'edition' => 'lite',
            'supports' => $supports,
            'options' => [
                'default_value' => ['description' => 'Pre-filled default value.', 'type' => 'string'],
                'placeholder' => ['description' => 'Placeholder shown when empty.', 'type' => 'string'],
                'limit_enabled' => ['description' => 'Enforce a character/word limit.', 'type' => 'boolean'],
                'limit_count' => ['description' => 'Maximum count when limit_enabled.', 'type' => 'integer'],
                'limit_mode' => ['description' => 'characters | words.', 'type' => 'string'],
                'input_mask' => ['description' => 'Input mask pattern.', 'type' => 'string'],
            ],
        ],
        'textarea' => [
            'type' => 'textarea',
            'label_text' => 'Paragraph Text',
            'edition' => 'lite',
            'supports' => $supports,
            'options' => [
                'default_value' => ['description' => 'Pre-filled default value.', 'type' => 'string'],
                'placeholder' => ['description' => 'Placeholder shown when empty.', 'type' => 'string'],
                'size' => ['description' => 'small | medium | large.', 'type' => 'string'],
                'limit_enabled' => ['description' => 'Enforce a character/word limit.', 'type' => 'boolean'],
                'limit_count' => ['description' => 'Maximum count when limit_enabled.', 'type' => 'integer'],
                'limit_mode' => ['description' => 'characters | words.', 'type' => 'string'],
            ],
        ],
        'name' => [
            'type' => 'name',
            'label_text' => 'Name',
            'edition' => 'lite',
            'supports' => $supports,
            'options' => [
                'format' => ['description' => 'simple | first-last | first-middle-last.', 'type' => 'string'],
                'default_value' => ['description' => 'Pre-filled default value.', 'type' => 'string'],
                'sublabel_hide' => ['description' => 'Hide first/middle/last sublabels.', 'type' => 'boolean'],
            ],
        ],
        'email' => [
            'type' => 'email',
            'label_text' => 'Email',
            'edition' => 'lite',
            'supports' => $supports,
            'options' => [
                'default_value' => ['description' => 'Pre-filled email address.', 'type' => 'string'],
                'placeholder' => ['description' => 'Placeholder shown when empty.', 'type' => 'string'],
                'confirmation' => ['description' => 'Show a confirm-email field.', 'type' => 'boolean'],
                'filter_type' => ['description' => 'none | allowlist | denylist.', 'type' => 'string'],
                'allowlist' => ['description' => 'Allowed domains (when filter_type=allowlist).', 'type' => 'string'],
                'denylist' => ['description' => 'Blocked domains (when filter_type=denylist).', 'type' => 'string'],
            ],
        ],
    ];
}

/**
 * Lite numeric / informational inputs: number, number-slider, gdpr-checkbox,
 * internal-information.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_lite_value_inputs(): array
{
    $supports = wpforms_base_supports();
    return [
        'number' => [
            'type' => 'number',
            'label_text' => 'Numbers',
            'edition' => 'lite',
            'supports' => $supports,
            'options' => [
                'default_value' => ['description' => 'Pre-filled default value.', 'type' => 'string'],
                'placeholder' => ['description' => 'Placeholder shown when empty.', 'type' => 'string'],
            ],
        ],
        'number-slider' => [
            'type' => 'number-slider',
            'label_text' => 'Numbers Slider',
            'edition' => 'lite',
            'supports' => $supports,
            'options' => [
                'default_value' => ['description' => 'Pre-filled value.', 'type' => 'string'],
                'min' => ['description' => 'Minimum value.', 'type' => 'number'],
                'max' => ['description' => 'Maximum value.', 'type' => 'number'],
                'step' => ['description' => 'Step increment.', 'type' => 'number'],
            ],
        ],
        'gdpr-checkbox' => [
            'type' => 'gdpr-checkbox',
            'label_text' => 'GDPR Agreement',
            'edition' => 'lite',
            'supports' => $supports,
            'options' => [
                'gdpr' => ['description' => 'The consent checkbox label.', 'type' => 'string'],
            ],
        ],
        'internal-information' => [
            'type' => 'internal-information',
            'label_text' => 'Internal Information',
            'edition' => 'lite',
            'supports' => ['label', 'description', 'css'],
            'options' => [],
        ],
    ];
}

/**
 * Lite choice-based inputs: select, checkbox, radio.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_lite_choice(): array
{
    $supports = wpforms_base_supports();
    $choices = wpforms_choice_options();
    return [
        'select' => [
            'type' => 'select',
            'label_text' => 'Dropdown',
            'edition' => 'lite',
            'supports' => $supports,
            'options' => array_merge($choices, [
                'multiple' => ['description' => 'Allow multiple selections.', 'type' => 'boolean'],
                'style' => ['description' => 'classic | modern.', 'type' => 'string'],
                'placeholder' => ['description' => 'First placeholder option.', 'type' => 'string'],
            ]),
        ],
        'checkbox' => [
            'type' => 'checkbox',
            'label_text' => 'Checkboxes',
            'edition' => 'lite',
            'supports' => $supports,
            'options' => $choices,
        ],
        'radio' => [
            'type' => 'radio',
            'label_text' => 'Multiple Choice',
            'edition' => 'lite',
            'supports' => $supports,
            'options' => $choices,
        ],
    ];
}

/**
 * Lite structural / layout helpers: divider, html, content, pagebreak, hidden.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_lite_structural(): array
{
    return [
        'divider' => [
            'type' => 'divider',
            'label_text' => 'Section Divider',
            'edition' => 'lite',
            'supports' => ['label', 'description', 'css'],
            'options' => [],
        ],
        'html' => [
            'type' => 'html',
            'label_text' => 'HTML',
            'edition' => 'lite',
            'supports' => ['label', 'css', 'conditional_logic'],
            'options' => [
                'code' => ['description' => 'Raw HTML markup.', 'type' => 'string'],
            ],
        ],
        'content' => [
            'type' => 'content',
            'label_text' => 'Content',
            'edition' => 'lite',
            'supports' => ['label', 'css', 'conditional_logic'],
            'options' => [
                'content' => ['description' => 'Block editor content.', 'type' => 'string'],
            ],
        ],
        'pagebreak' => [
            'type' => 'pagebreak',
            'label_text' => 'Page Break',
            'edition' => 'lite',
            'supports' => ['label', 'css'],
            'options' => [
                'position' => ['description' => 'top | normal | bottom.', 'type' => 'string'],
                'title' => ['description' => 'Page label shown in the indicator.', 'type' => 'string'],
                'next' => ['description' => 'Next button label.', 'type' => 'string'],
                'prev' => ['description' => 'Previous button label.', 'type' => 'string'],
            ],
        ],
        'hidden' => [
            'type' => 'hidden',
            'label_text' => 'Hidden Field',
            'edition' => 'lite',
            'supports' => ['label'],
            'options' => [
                'default_value' => ['description' => 'Pre-filled value (smart tags allowed).', 'type' => 'string'],
            ],
        ],
    ];
}

/**
 * Pro premium input types. Split into 3 chunks (contact info, rich/web/media,
 * secure inputs) so each chunk stays well under the halstead-volume threshold.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_pro_inputs(): array
{
    return array_merge(
        wpforms_catalog_pro_contact_inputs(),
        wpforms_catalog_pro_rich_inputs(),
        wpforms_catalog_pro_secure_inputs(),
    );
}

/**
 * Pro contact-style inputs: address, date-time, phone, rating.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_pro_contact_inputs(): array
{
    $supports = wpforms_base_supports();
    return [
        'address' => [
            'type' => 'address',
            'label_text' => 'Address',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'format' => ['description' => 'us | international.', 'type' => 'string'],
                'scheme' => ['description' => 'us | international.', 'type' => 'string'],
            ],
        ],
        'date-time' => [
            'type' => 'date-time',
            'label_text' => 'Date / Time',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'format' => ['description' => 'date | time | date-time.', 'type' => 'string'],
                'date_format' => ['description' => 'PHP date() format string.', 'type' => 'string'],
                'time_format' => ['description' => 'PHP time format string.', 'type' => 'string'],
                'date_type' => ['description' => 'datepicker | dropdown.', 'type' => 'string'],
            ],
        ],
        'phone' => [
            'type' => 'phone',
            'label_text' => 'Phone',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'format' => ['description' => 'us | international | smart.', 'type' => 'string'],
            ],
        ],
        'rating' => [
            'type' => 'rating',
            'label_text' => 'Rating',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'scale' => ['description' => 'Max number of stars (1-10).', 'type' => 'integer'],
                'icon' => ['description' => 'Icon style (star / heart / thumb / smile).', 'type' => 'string'],
                'icon_size' => ['description' => 'small | medium | large.', 'type' => 'string'],
            ],
        ],
    ];
}

/**
 * Pro rich/web/media inputs: richtext, url, file-upload, camera.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_pro_rich_inputs(): array
{
    $supports = wpforms_base_supports();
    return [
        'richtext' => [
            'type' => 'richtext',
            'label_text' => 'Rich Text',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'style' => ['description' => 'full | basic.', 'type' => 'string'],
                'media_enabled' => ['description' => 'Allow uploading inline media.', 'type' => 'boolean'],
            ],
        ],
        'url' => [
            'type' => 'url',
            'label_text' => 'Website / URL',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'default_value' => ['description' => 'Pre-filled URL.', 'type' => 'string'],
                'placeholder' => ['description' => 'Placeholder shown when empty.', 'type' => 'string'],
            ],
        ],
        'file-upload' => [
            'type' => 'file-upload',
            'label_text' => 'File Upload',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'extensions' => ['description' => 'Comma-separated allowed extensions.', 'type' => 'string'],
                'max_size' => ['description' => 'Maximum file size in MiB.', 'type' => 'number'],
                'max_file_number' => ['description' => 'Maximum number of files.', 'type' => 'integer'],
                'media_library' => ['description' => 'Store uploads in the WP Media Library.', 'type' => 'boolean'],
                'style' => ['description' => 'classic | modern.', 'type' => 'string'],
            ],
        ],
        'camera' => [
            'type' => 'camera',
            'label_text' => 'Camera',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'mode' => ['description' => 'capture | upload | both.', 'type' => 'string'],
            ],
        ],
    ];
}

/**
 * Pro secure / specialised inputs: password, signature, credit-card,
 * custom-captcha.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_pro_secure_inputs(): array
{
    $supports = wpforms_base_supports();
    return [
        'password' => [
            'type' => 'password',
            'label_text' => 'Password',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'confirmation' => ['description' => 'Show a confirm-password field.', 'type' => 'boolean'],
                'password_strength' => ['description' => 'Enforce a minimum strength.', 'type' => 'boolean'],
                'password_strength_level' => ['description' => '1 (weak) - 4 (strongest).', 'type' => 'integer'],
            ],
        ],
        'signature' => [
            'type' => 'signature',
            'label_text' => 'Signature',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [],
        ],
        'credit-card' => [
            'type' => 'credit-card',
            'label_text' => 'Credit Card',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [],
        ],
        'custom-captcha' => [
            'type' => 'custom-captcha',
            'label_text' => 'Custom Captcha',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'format' => ['description' => 'math | question.', 'type' => 'string'],
                'questions' => ['description' => 'Array of {question, answer} entries.', 'type' => 'array'],
            ],
        ],
    ];
}

/**
 * Pro payment-related types. Split across two chunks (configurable +
 * presentational) so each chunk stays under the halstead-volume threshold.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_pro_payments(): array
{
    return array_merge(wpforms_catalog_pro_payment_items(), wpforms_catalog_pro_payment_total());
}

/**
 * Pro payment item types: payment-single, payment-multiple, payment-checkbox,
 * payment-select.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_pro_payment_items(): array
{
    $supports = wpforms_base_supports();
    $choices = wpforms_choice_options();
    return [
        'payment-single' => [
            'type' => 'payment-single',
            'label_text' => 'Single Item',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'format' => ['description' => 'single | user | hidden.', 'type' => 'string'],
                'price' => ['description' => 'Item price (string with currency-friendly format).', 'type' => 'string'],
            ],
        ],
        'payment-multiple' => [
            'type' => 'payment-multiple',
            'label_text' => 'Multiple Items',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => $choices,
        ],
        'payment-checkbox' => [
            'type' => 'payment-checkbox',
            'label_text' => 'Checkbox Items',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => $choices,
        ],
        'payment-select' => [
            'type' => 'payment-select',
            'label_text' => 'Dropdown Items',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => $choices,
        ],
    ];
}

/**
 * Pro payment-total type — the running total field. Its own chunk so the
 * payment-items helper stays small.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_pro_payment_total(): array
{
    return [
        'payment-total' => [
            'type' => 'payment-total',
            'label_text' => 'Total',
            'edition' => 'pro',
            'supports' => wpforms_base_supports(),
            'options' => [],
        ],
    ];
}

/**
 * Pro structural / layout types: entry-preview, repeater, layout.
 *
 * @return array<string, array{type: string, label_text: string, edition: string, supports: list<string>, options: array<string, array{description: string, type: string}>}>
 */
function wpforms_catalog_pro_structural(): array
{
    $supports = wpforms_base_supports();
    return [
        'entry-preview' => [
            'type' => 'entry-preview',
            'label_text' => 'Entry Preview',
            'edition' => 'pro',
            'supports' => ['label', 'css'],
            'options' => [],
        ],
        'repeater' => [
            'type' => 'repeater',
            'label_text' => 'Repeater',
            'edition' => 'pro',
            'supports' => $supports,
            'options' => [
                'minimum_rows' => ['description' => 'Minimum rows required.', 'type' => 'integer'],
                'maximum_rows' => ['description' => 'Maximum rows allowed.', 'type' => 'integer'],
            ],
        ],
        'layout' => [
            'type' => 'layout',
            'label_text' => 'Layout',
            'edition' => 'pro',
            'supports' => ['label', 'css', 'conditional_logic'],
            'options' => [
                'preset' => ['description' => 'Layout column preset id.', 'type' => 'string'],
            ],
        ],
    ];
}

// ─── Entries (Pro-only) ───────────────────────────────────────────────────

/**
 * Resolve the WPForms entry handler — wpforms()->obj('entry'). Returns null
 * on Lite (no entry handler) or on a half-loaded Pro install.
 */
function wpforms_entry_handler(): ?\WPForms_Entry_Handler
{
    if (!wpforms_pro_active() || !function_exists('wpforms')) {
        return null;
    }
    /** @var mixed $handler */
    $handler = \wpforms()->obj('entry');
    return $handler instanceof \WPForms_Entry_Handler ? $handler : null;
}

/**
 * Resolve an entry id to its stdClass row, rejecting non-existent ids and
 * surfacing the Pro requirement when the entry handler is missing.
 *
 * The return type uses \stdClass (not the broader `object`) because PHP 8.1+
 * rejects a union of `object` with a concrete class (WP_Error) at runtime —
 * "Type contains both object and a class type, which is redundant".
 */
function wpforms_entry_or_error(mixed $entry_id): \stdClass|WP_Error
{
    $id = wpforms_to_int($entry_id);
    if ($id <= 0) {
        return wpforms_invalid_input('entry_id must be a positive integer.');
    }
    $handler = wpforms_entry_handler();
    if ($handler === null) {
        return wpforms_pro_required('entries');
    }
    /** @var mixed $entry */
    $entry = $handler->get($id);
    if (!$entry instanceof \stdClass || (int) ($entry->entry_id ?? 0) !== $id) {
        return new WP_Error('wpforms_entry_not_found', "WPForms entry not found: {$id}", ['status' => 404]);
    }
    // Round-1 audit CRITICAL: reject trashed and partial entries by default,
    // matching the semantics of wpforms_form_or_error (which rejects auto-
    // draft / trash forms). Spam entries stay accessible because the WPForms
    // admin UI itself surfaces them for review. Callers that need the
    // rejected statuses can fetch via list-entries with `status` filter.
    $status = is_string($entry->status ?? null) ? $entry->status : '';
    if (in_array($status, ['trash', 'partial'], strict: true)) {
        return new WP_Error('wpforms_entry_not_found', "WPForms entry not found: {$id}", ['status' => 404]);
    }
    return $entry;
}

/**
 * Compact list-row shape for an entry — what list-entries returns per row.
 *
 * @return array{
 *     entry_id: int,
 *     form_id: int,
 *     status: string,
 *     type: string,
 *     viewed: bool,
 *     starred: bool,
 *     date: string,
 *     date_modified: string,
 *     ip_address: string,
 *     user_id: int,
 * }
 */
function wpforms_entry_row(object $entry): array
{
    return [
        'entry_id' => (int) ($entry->entry_id ?? 0),
        'form_id' => (int) ($entry->form_id ?? 0),
        'status' => (string) ($entry->status ?? ''),
        'type' => (string) ($entry->type ?? ''),
        'viewed' => (bool) (int) ($entry->viewed ?? 0),
        'starred' => (bool) (int) ($entry->starred ?? 0),
        'date' => (string) ($entry->date ?? ''),
        'date_modified' => (string) ($entry->date_modified ?? ''),
        'ip_address' => (string) ($entry->ip_address ?? ''),
        'user_id' => (int) ($entry->user_id ?? 0),
    ];
}

/**
 * Compact list-row shape for list-entries — drops the IP address. The IP
 * is PII (GDPR); keeping it out of the bulk listing surface (where the
 * agent's typical use case is enumerate-and-pick) reduces the chance of
 * accidental over-exposure. Callers that need the IP can fetch one entry
 * at a time via get-entry, which still surfaces the full row.
 *
 * @return array{
 *     entry_id: int,
 *     form_id: int,
 *     status: string,
 *     type: string,
 *     viewed: bool,
 *     starred: bool,
 *     date: string,
 *     date_modified: string,
 *     user_id: int,
 * }
 */
function wpforms_entry_compact_row(object $entry): array
{
    $row = wpforms_entry_row($entry);
    unset($row['ip_address']);
    return $row;
}

/**
 * Decode an entry's serialised fields JSON and apply a per-field byte cap so a
 * large textarea / richtext / file-upload value does not blow the agent's
 * context window. The marker per-field {value_truncated: true,
 * value_original_bytes: N} is returned in place of the truncated value's tail.
 *
 * Meta is always returned as an array (decoded entry.meta blob). When a caller
 * wants to omit it from a response, set it to null on the way out — keeping
 * the decision at the callsite rather than as a flag on this helper.
 *
 * @return array{
 *     entry: array<string, mixed>,
 *     fields: array<array-key, mixed>,
 *     meta: array<array-key, mixed>,
 *     truncated_fields: list<int>,
 * }
 */
function wpforms_entry_full(object $entry, int $max_field_bytes): array
{
    $fields = wpforms_entry_decode_object_blob($entry, property: 'fields');

    /** @var list<int> $truncated_fields */
    $truncated_fields = [];
    /** @var mixed $field_data */
    foreach ($fields as $field_id => $field_data) {
        if (!is_array($field_data)) {
            continue;
        }
        $result = wpforms_entry_cap_field_strings($field_data, $max_field_bytes);
        if (!$result['truncated']) {
            continue;
        }
        $fields[$field_id] = $result['field_data'];
        if (is_numeric($field_id)) {
            $truncated_fields[] = (int) $field_id;
        }
    }

    return [
        'entry' => wpforms_entry_row($entry),
        'fields' => $fields,
        'meta' => wpforms_entry_decode_object_blob($entry, property: 'meta'),
        'truncated_fields' => $truncated_fields,
    ];
}

/**
 * Cap every user-content string key in a single entry's field_data, not just
 * `value`. Composite field types persist sibling keys whose sum can blow the
 * agent context just as easily as a giant `value` does — name stores
 * first/middle/last, address stores address1/address2/city/state/postal/country,
 * file-upload stores file/file_user_name/file_original/ext (plus the per-file
 * URL), richtext can carry HTML under additional keys, repeater nests further.
 *
 * Walks the array's top-level string-valued keys (skips structural metadata
 * the agent does not consume as content: id, type, name as in field label,
 * style, etc.) and runs each through wpforms_entry_field_truncate(). When any
 * key was truncated the entry-level value_truncated flag is set so consumers
 * can detect partial data without scanning every key.
 *
 * @param array<array-key, mixed> $field_data
 * @return array{field_data: array<array-key, mixed>, truncated: bool}
 */
function wpforms_entry_cap_field_strings(array $field_data, int $max_field_bytes): array
{
    $structural = ['id', 'type', 'style', 'value_truncated', 'value_original_bytes'];
    $any_truncated = false;
    /** @var list<string> $truncated_keys */
    $truncated_keys = [];
    /** @var mixed $val */
    foreach ($field_data as $key => $val) {
        if (!is_string($key) || in_array($key, $structural, strict: true)) {
            continue;
        }
        if (!is_string($val)) {
            continue;
        }
        $capped = wpforms_entry_field_truncate($val, $max_field_bytes);
        if (!$capped['truncated']) {
            continue;
        }
        $field_data[$key] = $capped['value'];
        $field_data[$key . '_truncated'] = true;
        $field_data[$key . '_original_bytes'] = $capped['original_bytes'];
        $any_truncated = true;
        $truncated_keys[] = $key;
    }
    if ($any_truncated) {
        $field_data['value_truncated'] = true;
        $field_data['truncated_keys'] = $truncated_keys;
    }
    return ['field_data' => $field_data, 'truncated' => $any_truncated];
}

/**
 * Decode one of an entry's serialised JSON blobs (fields / meta) to an
 * associative array, defending against missing properties on the generic
 * stdClass the entry handler returns.
 *
 * @return array<array-key, mixed>
 */
function wpforms_entry_decode_object_blob(object $entry, string $property): array
{
    /** @var mixed $raw */
    $raw = match ($property) {
        'fields' => $entry->fields ?? null,
        'meta' => $entry->meta ?? null,
        default => null,
    };
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode($raw, associative: true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Truncate a string value to at most $max_bytes bytes, returning the result
 * plus a truncated flag and the original byte length. UTF-8 safe.
 *
 * @return array{value: string, truncated: bool, original_bytes: int}
 */
function wpforms_entry_field_truncate(string $value, int $max_bytes): array
{
    $bytes = strlen($value);
    if ($bytes <= $max_bytes) {
        return ['value' => $value, 'truncated' => false, 'original_bytes' => $bytes];
    }
    // mb_strcut is UTF-8 safe — it stops at a full multibyte character boundary.
    $cut = function_exists('mb_strcut')
        ? mb_strcut($value, start: 0, length: $max_bytes, encoding: 'UTF-8')
        : substr($value, offset: 0, length: $max_bytes);
    return ['value' => $cut, 'truncated' => true, 'original_bytes' => $bytes];
}

/**
 * The set of field types the Pro entry handler allows to be edited via the
 * admin UI. Used by edit-entry to refuse a write on a non-editable type
 * before WPForms surfaces a generic failure. Falls back to a static list when
 * the handler is unavailable (Lite, half-loaded Pro).
 *
 * @return list<string>
 */
function wpforms_editable_entry_field_types(): array
{
    $handler = wpforms_entry_handler();
    if ($handler !== null) {
        return array_values(array_filter($handler->get_editable_field_types(), callback: 'is_string'));
    }
    return [
        'checkbox',
        'email',
        'name',
        'number',
        'number-slider',
        'radio',
        'select',
        'text',
        'textarea',
        'address',
        'date-time',
        'phone',
        'rating',
        'richtext',
        'url',
        'file-upload',
        'camera',
    ];
}

/**
 * CAS-guarded UPDATE on wpforms_entries.fields for edit-entry. Mirrors the
 * pattern wpforms_persist_form_with_cas uses on wp_posts: the caller captured
 * date_modified at read time; this UPDATE matches only when nothing else has
 * advanced it. Zero rows matched → wpforms_concurrent_modification 409 so the
 * agent re-fetches and retries rather than silently overwriting a newer entry
 * state (the audit's "lost update" race the entry handler's own update()
 * cannot detect).
 */
function wpforms_persist_entry_with_cas(
    int $entry_id,
    string $fields_json,
    string $expected_date_modified,
): bool|WP_Error {
    global $wpdb;
    /** @var \wpdb $wpdb */
    $now_gmt = current_time('mysql', gmt: true);
    // Pass unslashed JSON: wp_json_encode() output is unslashed; $wpdb->update()
    // does its own escaping. A wp_slash() pre-pass here would double-escape.
    $result = $wpdb->update(
        $wpdb->prefix . 'wpforms_entries',
        [
            'fields' => $fields_json,
            'date_modified' => $now_gmt,
            'status' => 'edited',
        ],
        [
            'entry_id' => $entry_id,
            'date_modified' => $expected_date_modified,
        ],
    );
    if ($result === false) {
        return new WP_Error(
            'wpforms_persist_entry_db_error',
            sprintf('Database error persisting WPForms entry %d.', $entry_id),
            ['status' => 500],
        );
    }
    if ($result === 0) {
        return wpforms_concurrent_entry_modification_error($entry_id);
    }
    return true;
}
