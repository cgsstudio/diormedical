<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Formidable;

use FrmAppHelper;
use FrmEntry;
use FrmField;
use FrmForm;
use FrmProDb;
use Throwable;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Detect Formidable presence, version, edition, and payment-surface
 * availability. Cached per request and PER BLOG so multisite installs with
 * per-site Formidable activation (or different free/Pro versions across
 * sites) don't return a stale read from the wrong site.
 *
 * Mirrors the WPForms audit-round-1 multisite cache fix (Cluster A): the
 * earlier static-scalar shape would have served Site A's "pro: true" to a
 * request resolved against Site B that runs free-only.
 *
 * Unlike FluentForms / WPForms (which read top-level constants) Formidable
 * exposes its version via the public static `FrmAppHelper::plugin_version()`
 * because free Formidable defines no `FRM_VERSION` constant. Pro version is
 * read from the public static `FrmProDb::$plug_version`.
 *
 * @return array{
 *     active: bool,
 *     version: string|null,
 *     min_satisfied: bool,
 *     pro: bool,
 *     pro_version: string|null,
 *     pro_min_satisfied: bool,
 *     licensed: bool,
 *     payments_supported: bool,
 * }
 */
function frm_detect_setup(): array
{
    /** @var array<int, array{active:bool,version:?string,min_satisfied:bool,pro:bool,pro_version:?string,pro_min_satisfied:bool,licensed:bool,payments_supported:bool}> $cache */
    static $cache = [];

    $blog_id = function_exists('get_current_blog_id') ? (int) get_current_blog_id() : 0;
    if (isset($cache[$blog_id])) {
        return $cache[$blog_id];
    }

    // `active` reports whether Formidable is loaded AT ALL (any version).
    // Decoupled from `min_satisfied` so that an install of Formidable 5.x
    // returns `plugin_active=true, min_satisfied=false` — the agent can then
    // prompt the user to upgrade rather than reporting "Formidable not
    // installed". The same decoupling applies to Pro below.
    $active = frm_plugin_present();
    $version = null;
    if ($active) {
        try {
            $version = FrmAppHelper::plugin_version();
        } catch (Throwable) {
            // Defensive: an upstream refactor could turn plugin_version() into
            // something that throws on a partially-initialised install. Fall
            // back to "active=false" rather than fatalling the request.
            $active = false;
        }
    }

    $min_satisfied =
        $active
        && $version !== null
        && version_compare(version1: $version, version2: \NOVAMIRA_PRO_FORMIDABLE_MIN_VERSION, operator: '>=');

    $pro = class_exists('\FrmProDb') && property_exists('\FrmProDb', 'plug_version');
    $pro_version = $pro ? FrmProDb::$plug_version : null;
    $pro_min_satisfied =
        $pro
        && $pro_version !== null
        && version_compare(version1: $pro_version, version2: \NOVAMIRA_PRO_FORMIDABLE_PRO_MIN_VERSION, operator: '>=');

    // License flag is read from Formidable's `frmpro-authorized` option. The
    // payload is `false` on un-initialised installs and a non-empty array of
    // credentials once Pro has authorized; cleared / partial states can
    // surface as `0`, `null`, or a string. We treat ONLY a non-empty array
    // as "licensed" — every other shape (including a truthy scalar that
    // somehow leaked into the option) reads as not-licensed. Never gate
    // features on this — Pro abilities are documented to run unlicensed.
    $licensed = false;
    if ($pro) {
        /** @var mixed $auth */
        $auth = get_option(option: 'frmpro-authorized', default_value: false);
        $licensed = is_array($auth) && $auth !== [];
    }

    return $cache[$blog_id] = [
        'active' => $active,
        'version' => $version,
        'min_satisfied' => $min_satisfied,
        'pro' => $pro,
        'pro_version' => $pro_version,
        'pro_min_satisfied' => $pro_min_satisfied,
        'licensed' => $licensed,
        'payments_supported' => frm_payments_supported(),
    ];
}

/**
 * Standard "Formidable is not active" error.
 */
function frm_not_active(): WP_Error
{
    return new WP_Error(
        'formidable_not_active',
        'Formidable Forms is not active or does not meet the minimum supported version.',
        ['status' => 400],
    );
}

/**
 * Standard "this needs Formidable Pro" error (applications, cancel-subscription).
 */
function frm_pro_required(string $feature): WP_Error
{
    return new WP_Error('formidable_pro_required', "{$feature} requires Formidable Forms Pro.", [
        'status' => 400,
    ]);
}

/**
 * Standard "invalid input" error wrapper with a consistent prefix.
 */
function frm_invalid_input(string $message): WP_Error
{
    return new WP_Error('formidable_invalid_input', $message, ['status' => 400]);
}

/**
 * Standard "form not found" 404. Issued by `frm_form_or_error()` for both
 * non-existent ids and rows of an unexpected shape.
 */
function frm_form_not_found(int $form_id): WP_Error
{
    return new WP_Error('formidable_form_not_found', "Formidable form not found: {$form_id}", [
        'status' => 404,
    ]);
}

/**
 * Standard "form unavailable" 400 — issued when the row exists but is in a
 * state callers should not act on (e.g. soft-trashed). Force-delete paths
 * accept `force=true` to bypass.
 */
function frm_form_unavailable(int $form_id, string $reason): WP_Error
{
    return new WP_Error('formidable_form_unavailable', "Formidable form {$form_id} is unavailable: {$reason}.", [
        'status' => 400,
    ]);
}

/**
 * Canonical error factory for any other ad-hoc condition. Use the more
 * specific helpers above when one exists — this is the catch-all for
 * Formidable-internal failure modes (e.g. `FrmForm::create()` returning
 * false without a richer signal).
 */
function frm_error(string $code, string $message, int $status = 500): WP_Error
{
    return new WP_Error($code, $message, ['status' => $status]);
}

/**
 * Coerce a mixed value to a non-negative integer (0 when not numeric or out of
 * range). Used for form / entry / field ids that may arrive as int / string /
 * float from JSON. Centralised here so every ability rejects the same
 * non-numeric and overflow shapes identically.
 */
function frm_to_int(mixed $raw): int
{
    if (!is_numeric($raw)) {
        return 0;
    }
    $n = (int) $raw;
    return $n > 0 ? $n : 0;
}

/**
 * Resolve a form id to its `FrmForm` row, rejecting non-existent ids and rows
 * in the trash state.
 *
 * Returns the canonical Formidable form row (stdClass with id, form_key,
 * name, description, status, options, parent_form_id, …) on success.
 *
 * Callers that legitimately need to operate on trashed forms — i.e. the
 * `delete-form{force=true}` hard-delete path that bypasses the soft-trash
 * lost-write race — MUST use `frm_trashed_form_or_error()` instead.
 */
function frm_form_or_error(mixed $form_id): \stdClass|WP_Error
{
    $row = frm_form_row($form_id);
    if ($row instanceof WP_Error) {
        return $row;
    }
    if (($row->status ?? '') === 'trash') {
        return frm_form_unavailable((int) ($row->id ?? 0), reason: 'in the trash state');
    }
    return $row;
}

/**
 * Trash-tolerant variant of `frm_form_or_error()`. Returns rows in any
 * status (including `trash`) so callers can hard-delete or restore them.
 */
function frm_trashed_form_or_error(mixed $form_id): \stdClass|WP_Error
{
    return frm_form_row($form_id);
}

/**
 * Shared resolver — fetches the FrmForm row and surfaces the canonical
 * "invalid id" / "not found" errors. Trash-state filtering is layered on
 * top by the public wrappers above so each public helper has a single,
 * unambiguous contract.
 */
function frm_form_row(mixed $form_id): \stdClass|WP_Error
{
    $id = frm_to_int($form_id);
    if ($id <= 0) {
        return frm_invalid_input('form_id must be a positive integer.');
    }
    if (!class_exists(FrmForm::class)) {
        return frm_not_active();
    }
    $row = FrmForm::getOne($id);
    if (!is_object($row) || (int) ($row->id ?? 0) !== $id) {
        return frm_form_not_found($id);
    }
    return $row;
}

/**
 * Total number of forms on the install (any status). Used by check-setup to
 * advertise install size. Returns 0 — never an error — on an install where
 * Formidable is missing or the count helper itself throws, because
 * check-setup is a discovery surface that must never fail.
 */
function frm_count_forms(): int
{
    if (!class_exists(FrmForm::class) || !method_exists(FrmForm::class, 'get_count')) {
        return 0;
    }
    try {
        $count = FrmForm::get_count();
    } catch (Throwable) {
        return 0;
    }
    return is_numeric($count) ? (int) $count : 0;
}

/**
 * Total number of entries (`wp_frm_items` rows) on the install. Same
 * never-fail contract as `frm_count_forms()`.
 */
function frm_count_entries(): int
{
    if (!class_exists(FrmEntry::class) || !method_exists(FrmEntry::class, 'get_entries_count')) {
        return 0;
    }
    try {
        $count = FrmEntry::get_entries_count();
    } catch (Throwable) {
        return 0;
    }
    return is_numeric($count) ? (int) $count : 0;
}

/**
 * Strip CR / LF / NULL control characters from a header-like value. Mirrors
 * the FluentForms audit round 3 #D fix: notification headers and form-title
 * values that flow into email subjects MUST never carry embedded line
 * separators (which open RFC 5322 header-injection vectors).
 *
 * Applied at the boundary of create-form / edit-form / create-notification
 * for `name`, `subject`, `email_to`, `cc`, `bcc`, `from`, `reply_to`.
 */
function frm_strip_crlf(string $value): string
{
    return str_replace(search: ["\r", "\n", "\0"], replace: '', subject: $value);
}

/**
 * Normalise WP-native field aliases into the short names the rest of the
 * pipeline uses. Abilities that wrap WP-core-shaped surfaces accept
 * BOTH the short and the WP-native key — the callback
 * normalises before validating, so the rest of the function only sees one
 * shape. Mapping:
 *
 *   title         → name
 *   slug          → form_key
 *   description   → description (no rename)
 *   status        → status (no rename)
 *
 * If both forms are supplied for the same field, the SHORT form wins (it
 * is the canonical one in this project; the WP-native alias is a
 * convenience for agents already thinking in WP terms).
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function frm_normalize_form_input(array $input): array
{
    $aliases = ['title' => 'name', 'slug' => 'form_key'];
    foreach ($aliases as $alias => $canonical) {
        if (!array_key_exists($alias, $input)) {
            continue;
        }
        if (!array_key_exists($canonical, $input)) {
            $input[$canonical] = $input[$alias];
        }
        unset($input[$alias]);
    }
    return $input;
}

/**
 * Standard "current user lacks capability" 403. Mutation / list abilities
 * call this after the active-runtime check so an inactive install fails
 * with the canonical `formidable_not_active` code first.
 */
function frm_capability_or_error(string $capability): ?WP_Error
{
    if (current_user_can($capability)) {
        return null;
    }
    return new WP_Error(
        'formidable_capability_missing',
        sprintf("Current user lacks the required '%s' capability.", $capability),
        ['status' => 403],
    );
}

/**
 * Reduce a Formidable form row (stdClass from `FrmForm::getOne` / `getAll`)
 * to the compact shape `list-forms` returns. Keeps Progressive Disclosure
 * honest: callers that need full settings (options blob, fields[], actions)
 * call `get-form` for the heavy payload.
 *
 * @return array{
 *     id: int,
 *     form_key: string,
 *     name: string,
 *     description: string,
 *     status: string,
 *     parent_form_id: int,
 *     is_template: bool,
 *     created_at: string,
 * }
 */
function frm_form_to_compact_row(\stdClass $form): array
{
    /** @var array{id?: int|string, form_key?: string, name?: string, description?: string, status?: string, parent_form_id?: int|string, is_template?: int|string, created_at?: string} $row */
    $row = (array) $form;
    return [
        'id' => (int) ($row['id'] ?? 0),
        'form_key' => $row['form_key'] ?? '',
        'name' => $row['name'] ?? '',
        'description' => $row['description'] ?? '',
        'status' => $row['status'] ?? '',
        'parent_form_id' => (int) ($row['parent_form_id'] ?? 0),
        'is_template' => (int) ($row['is_template'] ?? 0) === 1,
        'created_at' => $row['created_at'] ?? '',
    ];
}

/**
 * Validate an RFC 5322 email-header value (Subject, From, To, Reply-To, Cc, Bcc).
 * Rejects CR / LF / NULL characters — the mechanism a header-injection attack
 * uses to smuggle additional headers (BCC: attacker@…, To: target@…, blank
 * line then attacker-controlled body). Returns the unmodified input when safe,
 * a WP_Error otherwise.
 *
 * Use at the boundary of notification-write paths for every value that lands
 * in an email header (subject, fromEmail, replyTo, cc, bcc). Mirrors the
 * FluentForms audit-round-3 #D fix applied at the same surface.
 */
function frm_validate_email_header(string $value): string|WP_Error
{
    if ($value === '') {
        return $value;
    }
    // ASCII CR / LF / NULL — the canonical RFC 5322 header-injection vectors
    // PHPMailer's secureHeader() guards against.
    if (strpbrk(string: $value, characters: "\r\n\0") !== false) {
        return new WP_Error(
            'formidable_invalid_email_header',
            'Email header value must not contain CR / LF / NULL characters (RFC 5322 header-injection guard).',
            ['status' => 400],
        );
    }
    // Unicode line terminators (audit round 1, probe #2). Some downstream
    // parsers treat NEL (U+0085), LINE SEPARATOR (U+2028), and PARAGRAPH
    // SEPARATOR (U+2029) as line breaks even though they're not part of
    // PHPMailer's ASCII guard. Reject them defensively so the "RFC 5322
    // header-injection guard" claim in the doc covers Unicode terminators
    // too.
    if (
        str_contains($value, "\xC2\x85")
        || str_contains($value, "\xE2\x80\xA8")
        || str_contains($value, "\xE2\x80\xA9")
    ) {
        return new WP_Error(
            'formidable_invalid_email_header',
            'Email header value must not contain Unicode line terminators (NEL, LINE SEPARATOR, PARAGRAPH SEPARATOR).',
            ['status' => 400],
        );
    }
    return $value;
}

/**
 * Whitelist-validate a URL value bound for an action's `redirect_url` or
 * similar surface. Rejects `javascript:`, `data:`, `vbscript:`, and any
 * other unsafe scheme. `esc_url_raw()` alone does NOT block these (it only
 * filters characters); the whitelist below is the actual safety boundary.
 * Mirrors the FluentForms audit-round-3 #C fix.
 *
 * Returns the trimmed URL on success, a WP_Error otherwise. An empty input
 * is accepted as-is (caller decides whether empty is meaningful).
 */
function frm_validate_url_safe(string $url): string|WP_Error
{
    $url = trim($url);
    if ($url === '') {
        return $url;
    }
    // wp_parse_url returns string|int|array|null|false depending on the
    // input — explicitly mixed by signature.
    // @mago-expect analyzer:mixed-assignment
    $parsed = wp_parse_url($url);
    if ($parsed === false || !is_array($parsed)) {
        return new WP_Error('formidable_invalid_url', "Malformed URL: '{$url}'.", ['status' => 400]);
    }
    $scheme = isset($parsed['scheme']) && is_string($parsed['scheme']) ? strtolower($parsed['scheme']) : '';
    $allowed = ['http', 'https', 'mailto', 'tel'];
    if ($scheme !== '' && !in_array($scheme, $allowed, strict: true)) {
        return new WP_Error(
            'formidable_invalid_url_scheme',
            sprintf("URL scheme '%s' is not allowed. Allowed schemes: %s.", $scheme, implode(', ', $allowed)),
            ['status' => 400],
        );
    }
    return $url;
}

/**
 * Resolve a field reference (numeric id OR field_key slug) against a given
 * form, returning the matching FrmField row or a canonical 404 error. The
 * form-scoping check ensures the agent never accidentally edits a field on
 * a different form because of a recycled / cross-form key collision.
 *
 * `$ref` accepts: int (treated as field id), numeric string (cast to int),
 * non-numeric string (treated as field_key).
 */
function frm_field_or_error(int $form_id, mixed $ref): \stdClass|WP_Error
{
    if (!class_exists(FrmField::class)) {
        return frm_not_active();
    }

    $field = null;
    if (is_numeric($ref)) {
        $id = (int) $ref;
        if ($id > 0) {
            $field = FrmField::getOne($id);
        }
    }
    if ($field === null && is_string($ref) && !is_numeric($ref) && $ref !== '') {
        $field = FrmField::getOne($ref);
    }

    if (!$field instanceof \stdClass || (int) ($field->id ?? 0) <= 0) {
        return new WP_Error(
            'formidable_field_not_found',
            sprintf('Formidable field not found: %s', is_scalar($ref) ? (string) $ref : 'unknown'),
            ['status' => 404],
        );
    }

    if ((int) ($field->form_id ?? 0) !== $form_id) {
        return new WP_Error(
            'formidable_field_form_mismatch',
            sprintf(
                'Field %d belongs to form %d, not the requested form %d.',
                (int) ($field->id ?? 0),
                (int) ($field->form_id ?? 0),
                $form_id,
            ),
            ['status' => 400],
        );
    }

    return $field;
}

/**
 * Reduce a Formidable field row to the basic shape that mutation abilities
 * (add-field / edit-field) emit — no truncation, no recursion. Mirrors
 * `frm_form_to_compact_row` for the field surface. The deep get-form pipeline
 * lives in `get-form.php` because it carries the truncations[] accumulator.
 *
 * @return array{
 *     id: int,
 *     field_key: string,
 *     type: string,
 *     name: string,
 *     description: string,
 *     required: bool,
 *     field_order: int,
 *     form_id: int,
 * }
 */
function frm_field_to_basic_row(\stdClass $field): array
{
    /** @var array{id?: int|string, field_key?: string, type?: string, name?: string, description?: string, required?: int|string, field_order?: int|string, form_id?: int|string} $row */
    $row = (array) $field;
    return [
        'id' => (int) ($row['id'] ?? 0),
        'field_key' => $row['field_key'] ?? '',
        'type' => $row['type'] ?? '',
        'name' => $row['name'] ?? '',
        'description' => $row['description'] ?? '',
        'required' => (int) ($row['required'] ?? 0) === 1,
        'field_order' => (int) ($row['field_order'] ?? 0),
        'form_id' => (int) ($row['form_id'] ?? 0),
    ];
}

/**
 * Invalidate every transient cache key Formidable maintains for a form's
 * fields. Wraps `FrmField::delete_form_transient` (which clears the 4
 * suffix variants and recurses up `parent_form_id` for Repeater children)
 * with a no-op fallback when the helper isn't available — applies after
 * any write path that bypasses the FrmField model layer (e.g. direct
 * `$wpdb->update` writes that don't fire the model's own invalidation).
 */
function frm_invalidate_form_caches(int $form_id): void
{
    if ($form_id <= 0) {
        return;
    }
    if (class_exists(FrmField::class) && method_exists(FrmField::class, 'delete_form_transient')) {
        FrmField::delete_form_transient($form_id);
    }
    if (class_exists(FrmForm::class) && method_exists(FrmForm::class, 'clear_form_cache')) {
        FrmForm::clear_form_cache();
    }
    // FrmForm::clear_form_cache() delegates to FrmDb::cache_delete_group()
    // which is a no-op on stock WP without an external object cache plugin
    // (Redis / Memcached / WP Engine's drop-in). The per-key wp_cache_delete
    // below covers stock-WP installs explicitly — applies the WPForms audit
    // round 1 cluster "cache-delete-group-no-op" lesson at the same surface.
    wp_cache_delete($form_id, group: 'frm_form');
}

/**
 * Snapshot of the current user's Formidable-native capabilities. Reported by
 * `check-setup` so an agent can branch BEFORE invoking a write surface that
 * would otherwise 403. Default Formidable maps every cap to `administrator`
 * via its activation routine; the gate here uses `current_user_can()`
 * directly so any role customisation (CapsMan, Members, Formidable's own
 * `frm_capability` filter) is honoured.
 *
 * @return array{
 *     view_forms: bool,
 *     edit_forms: bool,
 *     change_settings: bool,
 *     view_entries: bool,
 *     edit_entries: bool,
 *     delete_entries: bool,
 * }
 */
function frm_capability_snapshot(): array
{
    return [
        'view_forms' => current_user_can('frm_view_forms'),
        'edit_forms' => current_user_can('frm_edit_forms'),
        'change_settings' => current_user_can('frm_change_settings'),
        'view_entries' => current_user_can('frm_view_entries'),
        'edit_entries' => current_user_can('frm_edit_entries'),
        'delete_entries' => current_user_can('frm_delete_entries'),
    ];
}

/**
 * The field-type slugs this install ACTUALLY exposes. Free types come from
 * `FrmField::field_selection()`; Pro types come from
 * `FrmField::pro_field_selection()` filtered to entries that DON'T have
 * the `'addon'` key (those are upsells for separate add-ons like Surveys,
 * not core Pro types). When Formidable is missing the list is empty.
 *
 * Sorted + deduplicated for stable output across requests.
 *
 * @return list<string>
 */
function frm_available_field_types(): array
{
    $types = [];
    if (!class_exists(FrmField::class)) {
        return $types;
    }

    try {
        if (method_exists(FrmField::class, 'field_selection')) {
            foreach (array_keys(FrmField::field_selection()) as $slug) {
                if ($slug === '') {
                    continue;
                }
                $types[] = $slug;
            }
        }
        if (frm_pro_active() && method_exists(FrmField::class, 'pro_field_selection')) {
            foreach (FrmField::pro_field_selection() as $slug => $def) {
                if ($slug === '') {
                    continue;
                }
                // Drop add-on upsells (e.g. signature in older builds) — we
                // only advertise types the install can actually render.
                if (array_key_exists('addon', $def)) {
                    continue;
                }
                $types[] = $slug;
            }
        }
    } catch (Throwable) {
        // @mago-expect lint:no-empty-catch-clause
        // check-setup is a discovery surface and must never raise. A partial
        // type list is strictly better than a fatal — the agent will retry
        // discovery on the next request once the upstream is healthy.
    }

    $types = array_values(array_unique($types));
    sort($types);
    return $types;
}

/**
 * Shape returned by `check-setup` when Formidable is not active. Every
 * documented key is present so the schema validator and downstream consumers
 * see a stable envelope regardless of plugin state. Mirrors the FluentForms /
 * WPForms "discovery-never-errors" contract.
 *
 * @return array{
 *     plugin_active: bool,
 *     version: string|null,
 *     min_required: string,
 *     min_satisfied: bool,
 *     pro_active: bool,
 *     pro_version: string|null,
 *     pro_min_required: string,
 *     pro_min_satisfied: bool,
 *     license_active: bool,
 *     payments_supported: bool,
 *     forms_total: int,
 *     entries_total: int,
 *     capabilities: array{
 *         view_forms: bool,
 *         edit_forms: bool,
 *         change_settings: bool,
 *         view_entries: bool,
 *         edit_entries: bool,
 *         delete_entries: bool,
 *     },
 *     available_field_types: list<string>,
 * }
 */
function frm_check_setup_inactive(): array
{
    return [
        'plugin_active' => false,
        'version' => null,
        'min_required' => \NOVAMIRA_PRO_FORMIDABLE_MIN_VERSION,
        'min_satisfied' => false,
        'pro_active' => false,
        'pro_version' => null,
        'pro_min_required' => \NOVAMIRA_PRO_FORMIDABLE_PRO_MIN_VERSION,
        'pro_min_satisfied' => false,
        'license_active' => false,
        'payments_supported' => false,
        'forms_total' => 0,
        'entries_total' => 0,
        'capabilities' => [
            'view_forms' => false,
            'edit_forms' => false,
            'change_settings' => false,
            'view_entries' => false,
            'edit_entries' => false,
            'delete_entries' => false,
        ],
        'available_field_types' => [],
    ];
}
