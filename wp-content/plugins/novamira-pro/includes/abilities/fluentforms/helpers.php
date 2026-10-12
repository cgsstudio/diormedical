<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use FluentForm\App\Models\Form;
use FluentForm\App\Models\Submission;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Detect Fluent Forms presence, version, and edition. Cached per request.
 *
 * `FLUENTFORM_VERSION` is defined by the free plugin; `FLUENTFORMPRO_VERSION`
 * by the Pro add-on (which hard-depends on free). Pro presence does NOT imply
 * a valid license — Pro features run unlicensed, so the Pro gate keys on the
 * constant, not the license option. Mirrors the SeoPress / Yoast pattern.
 *
 * @return array{
 *     active: bool,
 *     version: string|null,
 *     min_satisfied: bool,
 *     pro: bool,
 *     pro_version: string|null,
 *     pro_min_satisfied: bool,
 *     licensed: bool,
 * }
 */
function ff_detect_setup(): array
{
    /** @var array{active:bool,version:?string,min_satisfied:bool,pro:bool,pro_version:?string,pro_min_satisfied:bool,licensed:bool}|null $cache */
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    $active = defined('FLUENTFORM_VERSION');
    $version = $active ? (string) constant('FLUENTFORM_VERSION') : null;
    $min_satisfied =
        $active
        && version_compare(
            version1: (string) $version,
            version2: \NOVAMIRA_PRO_FLUENTFORMS_MIN_VERSION,
            operator: '>=',
        );

    $pro = $active && defined('FLUENTFORMPRO_VERSION');
    $pro_version = $pro ? (string) constant('FLUENTFORMPRO_VERSION') : null;
    $pro_min_satisfied =
        $pro
        && version_compare(
            version1: (string) $pro_version,
            version2: \NOVAMIRA_PRO_FLUENTFORMS_PRO_MIN_VERSION,
            operator: '>=',
        );

    return $cache = [
        'active' => $active,
        'version' => $version,
        'min_satisfied' => $min_satisfied,
        'pro' => $pro,
        'pro_version' => $pro_version,
        'pro_min_satisfied' => $pro_min_satisfied,
        // Informational only; never gates a feature.
        'licensed' => $pro && get_option('fluentform_pro_license_status') === 'valid',
    ];
}

/**
 * Whether a Fluent Forms feature is supported in the current install. The map
 * below mirrors the `supports.*` block check-setup advertises so the agent can
 * branch BEFORE invoking an ability that would otherwise fail Pro-gated.
 *
 * - `payments`, `conversational_forms`, `quiz`, `draft_submissions`,
 *   `file_upload`, `pdf_generator` require Pro. `integrations` requires Pro
 *   for the bulk of marketing CRMs; the webhook and slack feeds are also Pro.
 * - When FF is absent, every feature reads `false`.
 */
function ff_supports(string $feature): bool
{
    $setup = ff_detect_setup();
    if (!$setup['active']) {
        return false;
    }

    return match ($feature) {
        // Free features available on any FF install at the min version.
        'forms' => true,
        'submissions' => true,
        'notifications' => true,
        // Pro-only feature flags. The class_exists guards mirror the runtime
        // bootstrap of fluentformpro.php so a half-loaded Pro install (e.g.
        // Pro file present but autoload not yet primed) reads as unsupported.
        'payments' => $setup['pro'] && class_exists('\\FluentFormPro\\Payments\\PaymentHandler'),
        'conversational_forms' => $setup['pro'] && class_exists('\\FluentFormPro\\classes\\ConversationalForms'),
        'quiz' => $setup['pro'] && class_exists('\\FluentFormPro\\classes\\Quiz\\QuizController'),
        'draft_submissions' => $setup['pro'] && class_exists('\\FluentFormPro\\classes\\DraftSubmissionsManager'),
        'file_upload' => $setup['pro'],
        'pdf_generator' => $setup['pro'] && class_exists('\\FluentFormPdf\\Classes\\Controller\\PdfManageController'),
        'integrations' => $setup['pro'],
        default => false,
    };
}

/**
 * Discover which of the known marketing-integration feeds Fluent Forms Pro
 * advertises in the current install. Used by check-setup to surface a useful
 * map without forcing the agent to enumerate the (large) GlobalIntegrationManager
 * settings table.
 *
 * @return array{active_count: int, feeds: array<string, bool>}
 */
function ff_active_integrations(): array
{
    $setup = ff_detect_setup();
    $feeds = [
        'mailchimp' => false,
        'activecampaign' => false,
        'hubspot' => false,
        'convertkit' => false,
        'getresponse' => false,
        'mailerlite' => false,
        'stripe' => false,
        'paypal' => false,
        'square' => false,
        'razorpay' => false,
        'google_sheet' => false,
        'slack' => false,
        'discord' => false,
        'zapier' => false,
        'make' => false,
        'webhook' => false,
    ];

    if (!$setup['pro']) {
        return ['active_count' => 0, 'feeds' => $feeds];
    }

    $bootstraps = [
        'mailchimp' => '\\FluentFormPro\\Integrations\\MailChimp\\Bootstrap',
        'activecampaign' => '\\FluentFormPro\\Integrations\\ActiveCampaign\\Bootstrap',
        'hubspot' => '\\FluentFormPro\\Integrations\\HubSpot\\Bootstrap',
        'convertkit' => '\\FluentFormPro\\Integrations\\ConvertKit\\Bootstrap',
        'getresponse' => '\\FluentFormPro\\Integrations\\GetResponse\\Bootstrap',
        'mailerlite' => '\\FluentFormPro\\Integrations\\MailerLite\\Bootstrap',
        'stripe' => '\\FluentFormPro\\Payments\\PaymentMethods\\Stripe\\StripeHandler',
        'paypal' => '\\FluentFormPro\\Payments\\PaymentMethods\\PayPal\\PayPalHandler',
        'square' => '\\FluentFormPro\\Payments\\PaymentMethods\\Square\\SquareHandler',
        'razorpay' => '\\FluentFormPro\\Payments\\PaymentMethods\\RazorPay\\RazorPayHandler',
        'google_sheet' => '\\FluentFormPro\\Integrations\\GoogleSheet\\Bootstrap',
        'slack' => '\\FluentFormPro\\Integrations\\Slack\\Bootstrap',
        'discord' => '\\FluentFormPro\\Integrations\\Discord\\Bootstrap',
        'zapier' => '\\FluentFormPro\\Integrations\\Zapier\\Bootstrap',
        'make' => '\\FluentFormPro\\Integrations\\Make\\Bootstrap',
        'webhook' => '\\FluentFormPro\\Integrations\\Webhook\\Bootstrap',
    ];

    $count = 0;
    foreach ($bootstraps as $key => $class) {
        if (!class_exists($class)) {
            continue;
        }
        $feeds[$key] = true;
        ++$count;
    }

    return ['active_count' => $count, 'feeds' => $feeds];
}

/**
 * Standard "Fluent Forms is not active" error.
 */
function ff_not_active(): WP_Error
{
    return new WP_Error('fluentforms_not_active', 'Fluent Forms is not active on this site.', ['status' => 400]);
}

/**
 * Standard "Fluent Forms version below minimum" error. Returned by every
 * ability except check-setup when the installed FF is older than the
 * NOVAMIRA_PRO_FLUENTFORMS_MIN_VERSION floor.
 */
function ff_version_unsupported(): WP_Error
{
    $detect = ff_detect_setup();
    return new WP_Error(
        'fluentforms_version_unsupported',
        sprintf(
            'Fluent Forms %s or newer is required; this site has %s.',
            \NOVAMIRA_PRO_FLUENTFORMS_MIN_VERSION,
            $detect['version'] ?? 'an unknown version',
        ),
        ['status' => 400],
    );
}

/**
 * Standard "this needs Fluent Forms Pro" error. Mirrors `sp_pro_required` from
 * SeoPress — caller decides whether to ISSUE it, the helper just constructs it.
 */
function ff_pro_required(string $feature): WP_Error
{
    return new WP_Error('fluentforms_pro_required', sprintf('%s requires Fluent Forms Pro.', $feature), [
        'status' => 400,
    ]);
}

/**
 * Standard "the Pro tier is loaded but the field type can't be used here"
 * error. Returned by add-field / edit-field when the requested catalog entry
 * is `pro_only:true` and Pro isn't loaded.
 */
function ff_pro_field_not_available(string $type): WP_Error
{
    return new WP_Error(
        'fluentforms_pro_field_not_available',
        sprintf("Field type '%s' requires Fluent Forms Pro.", $type),
        ['status' => 400],
    );
}

/**
 * Invalid-input error (400). Carries an actionable message; callers should
 * pass the schema path that failed, e.g. "title must be a non-empty string".
 */
function ff_invalid(string $message): WP_Error
{
    return new WP_Error('fluentforms_invalid_input', $message, ['status' => 400]);
}

/**
 * Not-found error (404). The error code carries the entity name so connected
 * agents can branch on it without parsing the message.
 */
function ff_not_found(string $entity, mixed $id): WP_Error
{
    return new WP_Error(
        sprintf('fluentforms_%s_not_found', $entity),
        sprintf("Fluent Forms %s '%s' was not found.", $entity, is_scalar($id) ? (string) $id : 'unknown'),
        ['status' => 404],
    );
}

/**
 * Operation-failed error (500). Wraps an unexpected runtime failure — DB
 * error, vendor SDK exception — that the agent can't act on directly but
 * the user/admin can.
 */
function ff_operation_failed(string $message): WP_Error
{
    return new WP_Error('fluentforms_operation_failed', $message, ['status' => 500]);
}

/**
 * Per-ability capability guard. Delegates to Fluent Forms's native `Acl` so
 * that team-mode (per-form) permissions are honoured: an editor granted
 * `fluentform_forms_manager` on form 5 but not form 7 must fail open on 5
 * and 403 on 7. When FF is not loaded the helper returns null (no guard)
 * so the abilities can still surface a clean "not active" error instead of
 * masking it behind a 403.
 *
 * @param int|null $form_id Per-form scope; null = global / non-form context.
 */
function ff_capability_or_error(string $cap, ?int $form_id = null): ?WP_Error
{
    // FF's ACL class is `\FluentForm\App\Modules\Acl\Acl` (verified in FF
    // 6.2.7). The integration previously probed `…\Services\Acl\Acl`, which
    // does not exist, so this guard ALWAYS took the "FF not loaded" branch
    // and returned null — the entire per-form capability layer was a silent
    // no-op. Try the current class, then the Services path as a fallback in
    // case an older/newer FF ever ships it, so the check works across
    // versions instead of failing open.
    $acl = null;
    foreach (['\\FluentForm\\App\\Modules\\Acl\\Acl', '\\FluentForm\\App\\Services\\Acl\\Acl'] as $candidate) {
        if (!class_exists($candidate) || !method_exists($candidate, 'hasPermission')) {
            continue;
        }
        $acl = $candidate;
        break;
    }
    if ($acl === null) {
        return null;
    }
    /** @var callable $has_permission */
    $has_permission = [$acl, 'hasPermission'];
    if ($has_permission($cap, $form_id ?? false) === true) {
        return null;
    }
    return new WP_Error(
        'fluentforms_forbidden',
        sprintf("You don't have permission '%s' on this Fluent Forms surface.", $cap),
        ['status' => 403],
    );
}

/**
 * Coerce a mixed value to a positive integer (>= 1). Returns 0 when the
 * input is not numeric or out of range. The float boundary guard prevents
 * a value beyond PHP_INT_MAX from casting lossily.
 */
function ff_pos_int(mixed $raw): int
{
    if (!is_numeric($raw)) {
        return 0;
    }
    $float = (float) $raw;
    if (!is_finite($float) || $float >= (float) PHP_INT_MAX || $float < 1.0) {
        return 0;
    }
    return (int) $raw;
}

/**
 * Coerce a mixed value to a non-negative integer (>= 0). Same boundary
 * guard as `ff_pos_int` but accepts 0 as a valid lower bound.
 */
function ff_non_neg_int(mixed $raw): int
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
 * Coerce a mixed value to a UTF-8 string. Returns '' when the input isn't a
 * scalar. Used to defensively read Fluent Forms model properties that the
 * stubs annotate as string but that may surface as null on stale rows.
 */
function ff_str(mixed $raw): string
{
    if (is_string($raw)) {
        return $raw;
    }
    if (is_int($raw) || is_float($raw)) {
        return (string) $raw;
    }
    return '';
}

/**
 * Read a positive integer `id` column from a stdClass result row. Returns
 * 0 when the property is missing or not a positive integer.
 */
function ff_row_id(object $row): int
{
    if (!property_exists($row, 'id')) {
        return 0;
    }
    /** @var mixed $value */
    $value = $row->id;
    if (is_int($value) && $value > 0) {
        return $value;
    }
    if (is_string($value) && ctype_digit($value)) {
        $cast = (int) $value;
        if ($cast > 0) {
            return $cast;
        }
    }
    return 0;
}

/**
 * Read an integer-typed column from a stdClass result row, with a fallback.
 * Strings of digits are accepted because MySQL drivers may return integer
 * columns as numeric strings depending on PDO emulation settings.
 */
function ff_row_int(object $row, string $column, int $default = 0): int
{
    $vars = get_object_vars($row);
    if (!array_key_exists($column, $vars)) {
        return $default;
    }
    /** @var mixed $value */
    $value = $vars[$column];
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && ($value === '' || ctype_digit(ltrim($value, characters: '-')))) {
        return (int) $value;
    }
    return $default;
}

/**
 * Read a string-typed column from a stdClass result row, with a fallback.
 * Numeric values coerce to their string form.
 */
function ff_row_str(object $row, string $column, string $default = ''): string
{
    $vars = get_object_vars($row);
    if (!array_key_exists($column, $vars)) {
        return $default;
    }
    /** @var mixed $value */
    $value = $vars[$column];
    if (is_string($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return $default;
}

/**
 * Read a nullable string column (e.g. `created_at` / `updated_at`). Returns
 * null when the property is missing OR explicitly null in the row.
 */
function ff_row_nullable_str(object $row, string $column): ?string
{
    $vars = get_object_vars($row);
    if (!array_key_exists($column, $vars)) {
        return null;
    }
    /** @var mixed $value */
    $value = $vars[$column];
    if ($value === null) {
        return null;
    }
    if (is_string($value)) {
        return $value;
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    return null;
}

/**
 * Truncate a value to a byte cap, preserving UTF-8 boundaries. Returns a
 * truncation marker `{value, truncated, full_size}` so callers can surface
 * progressive disclosure without losing the total-size signal.
 *
 * @return array{value: string, truncated: bool, full_size: int}
 */
function ff_truncate_field(string $value, int $max_bytes): array
{
    $size = strlen($value);
    if ($max_bytes <= 0 || $size <= $max_bytes) {
        return ['value' => $value, 'truncated' => false, 'full_size' => $size];
    }
    // mb_strcut() truncates on UTF-8 boundaries — strlen() returns BYTES, not
    // characters, so the cap is byte-accurate even for multibyte content.
    $clipped = function_exists('mb_strcut')
        ? mb_strcut($value, start: 0, length: $max_bytes, encoding: 'UTF-8')
        : substr($value, offset: 0, length: $max_bytes);
    return ['value' => $clipped, 'truncated' => true, 'full_size' => $size];
}

/**
 * Default per-field byte cap for `get-submission` / `get-form` responses.
 * The agent can override via `max_field_bytes`, capped at 64 KiB by the
 * abilities themselves so a single ability call can never blow past the
 * MCP envelope.
 */
function ff_default_max_field_bytes(): int
{
    return 4096;
}

/**
 * The hard ceiling for `max_field_bytes` regardless of caller intent.
 */
function ff_absolute_max_field_bytes(): int
{
    return 65_536;
}

/**
 * Resolve a Fluent Forms form id to a `Form` model. Rejects non-positive ids,
 * non-numeric strings, and ids that don't exist in the `fluentform_forms`
 * table.
 */
function ff_form_or_error(mixed $form_id): Form|WP_Error
{
    if (!is_int($form_id) && !is_float($form_id) && !(is_string($form_id) && ctype_digit($form_id))) {
        return ff_invalid('form_id must be a numeric form id (positive integer).');
    }
    $id = ff_pos_int($form_id);
    if ($id <= 0) {
        return ff_invalid('form_id must be a positive integer.');
    }
    /** @var Form|null $form */
    $form = Form::find($id);
    if (!$form instanceof Form) {
        return ff_not_found('form', $id);
    }
    return $form;
}

/**
 * Resolve a Fluent Forms submission id to a `Submission` model. Same shape
 * as `ff_form_or_error` — strict on id type, returns 404 when absent.
 */
function ff_submission_or_error(mixed $submission_id): Submission|WP_Error
{
    if (
        !is_int($submission_id)
        && !is_float($submission_id)
        && !(is_string($submission_id) && ctype_digit($submission_id))
    ) {
        return ff_invalid('submission_id must be a numeric submission id (positive integer).');
    }
    $id = ff_pos_int($submission_id);
    if ($id <= 0) {
        return ff_invalid('submission_id must be a positive integer.');
    }
    /** @var Submission|null $sub */
    $sub = Submission::find($id);
    if (!$sub instanceof Submission) {
        return ff_not_found('submission', $id);
    }
    return $sub;
}

/**
 * Ensure a form carries the default meta FluentForms itself writes when a
 * form is created natively (FormMeta::prepare/store in FormService::store):
 * `formSettings` from the canonical defaults source and `template_name`.
 *
 * Our create path calls the Form model directly, which skips
 * FormService::store — and WITHOUT a `formSettings` row the FluentForms
 * renderer silently returns an EMPTY string for the form (the editor's
 * field previews do not mount either), so a form born without this meta is
 * invisible everywhere while every read reports it as perfectly healthy.
 * Idempotent: a form that already has `formSettings` is left untouched, so
 * this doubles as a self-heal on edit/duplicate for forms created before
 * the fix.
 */
function ff_bootstrap_default_form_meta(int $form_id): void
{
    if ($form_id <= 0 || !function_exists('wpFluent')) {
        return;
    }
    $existing = wpFluent()
        ->table('fluentform_form_meta')
        ->where('form_id', $form_id)
        ->where('meta_key', 'formSettings')
        ->first();
    if ($existing !== null) {
        return;
    }

    $settings = [];
    if (method_exists(Form::class, 'getFormsDefaultSettings')) {
        /** @var mixed $defaults */
        $defaults = Form::getFormsDefaultSettings();
        if (is_array($defaults)) {
            $settings = $defaults;
        }
    }
    if ($settings === []) {
        // Minimal render-valid fallback, mirroring the shape the renderer
        // reads (verified: with this row the shortcode renders; without any
        // formSettings row it outputs 0 bytes).
        $settings = [
            'confirmation' => [
                'redirectTo' => 'samePage',
                'messageToShow' => __('Thank you for your submission.', domain: 'novamira-pro'),
                'customPage' => null,
                'samePageFormBehavior' => 'hide_form',
                'customUrl' => null,
            ],
            'restrictions' => [
                'limitNumberOfEntries' => ['enabled' => false],
                'scheduleForm' => ['enabled' => false],
                'requireLogin' => ['enabled' => false],
                'denyEmptySubmission' => ['enabled' => false],
                'restrictForm' => ['enabled' => false],
            ],
            'layout' => [
                'labelPlacement' => 'top',
                'helpMessagePlacement' => 'with_label',
                'errorMessagePlacement' => 'inline',
                'asteriskPlacement' => 'asterisk-right',
            ],
        ];
    }

    ff_set_form_meta($form_id, key: 'formSettings', value: $settings);

    $template = wpFluent()
        ->table('fluentform_form_meta')
        ->where('form_id', $form_id)
        ->where('meta_key', 'template_name')
        ->first();
    if ($template === null) {
        ff_set_form_meta($form_id, key: 'template_name', value: 'blank_form');
    }
}

/**
 * Upsert a form_meta row. Encodes arrays/objects to JSON; scalars stored
 * as-is. Returns true on success. Mirrors the FF admin save path so
 * downstream modules observe the same row shape.
 */
function ff_set_form_meta(int $form_id, string $key, mixed $value): bool
{
    if ($form_id <= 0 || $key === '' || !function_exists('wpFluent')) {
        return false;
    }
    $value_str = ff_encode_meta_value($value);
    $existing = wpFluent()->table('fluentform_form_meta')->where('form_id', $form_id)->where('meta_key', $key)->first();
    $existing_id = $existing instanceof \stdClass ? ff_row_id($existing) : 0;
    if ($existing_id > 0) {
        // fluentform_form_meta has only 4 columns: id, form_id, meta_key, value.
        // No created_at / updated_at — inventing them yields "Unknown column" on
        // every write. (Mirrors what the FF native FormMeta::firstOrCreate does.)
        wpFluent()->table('fluentform_form_meta')->where('id', $existing_id)->update(['value' => $value_str]);
        return true;
    }
    wpFluent()
        ->table('fluentform_form_meta')
        ->insert([
            'form_id' => $form_id,
            'meta_key' => $key,
            'value' => $value_str,
        ]);
    return true;
}

/**
 * Serialise a form_meta value: arrays/objects → JSON, scalars → string,
 * anything else → empty string. Extracted from ff_set_form_meta to keep
 * the upsert function under Halstead.
 */
function ff_encode_meta_value(mixed $value): string
{
    if (is_array($value) || is_object($value)) {
        $encoded = wp_json_encode($value, flags: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($encoded) ? $encoded : '';
    }
    if (is_scalar($value)) {
        return (string) $value;
    }
    return '';
}

/**
 * Delete a single form_meta row by key. Returns the rows removed (0 when
 * absent).
 */
function ff_delete_form_meta(int $form_id, string $key): int
{
    if ($form_id <= 0 || $key === '' || !function_exists('wpFluent')) {
        return 0;
    }
    return wpFluent()->table('fluentform_form_meta')->where('form_id', $form_id)->where('meta_key', $key)->delete();
}

/**
 * Run a read-modify-write of a form's shared `formSettings` blob under a
 * per-form MySQL advisory lock, so two concurrent edits of different subkeys
 * (edit-confirmation vs edit-form-settings) do not lose one update — both
 * read the same baseline, merge their own subkey, and the last writer would
 * otherwise silently discard the other. The callback must do the full
 * read (ff_form_settings) + merge + write (ff_set_form_meta) INSIDE, so the
 * read sees a prior holder's committed write. Falls through unlocked (no
 * worse than before) if GET_LOCK is unavailable or contended past the wait.
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function ff_with_form_settings_lock(int $form_id, callable $fn)
{
    global $wpdb;
    $lock = null;
    if ($form_id > 0 && $wpdb instanceof \wpdb) {
        // GET_LOCK names are global to the whole MySQL server, so scope the
        // name by the site's table prefix (wp_, wp_2_, …). Without it, two
        // blogs on one multisite / co-tenant installs sharing a server would
        // false-contend on the same form_id. Stays well under the 64-char cap.
        $name = 'nvp_ff_fs_' . $wpdb->prefix . $form_id;
        $acquire = $wpdb->prepare('SELECT GET_LOCK(%s, %d)', $name, 5);
        if (is_string($acquire)) {
            /** @var mixed $got */
            $got = $wpdb->get_var($acquire);
            if ((string) $got === '1') {
                $lock = $name;
            }
        }
    }
    try {
        return $fn();
    } finally {
        if ($lock !== null && $wpdb instanceof \wpdb) {
            $release = $wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock);
            if (is_string($release)) {
                $wpdb->query($release);
            }
        }
    }
}

/**
 * Decode the formSettings blob for a form. Carries confirmation,
 * restrictions, layout, submitButton, requireLogin, formExpires, …
 * Returns [] when the row is absent or the JSON is not an object.
 *
 * @return array<array-key, mixed>
 */
function ff_form_settings(int $form_id): array
{
    /** @var mixed $raw */
    $raw = ff_get_form_meta($form_id, key: 'formSettings');
    return is_array($raw) ? $raw : [];
}

/**
 * Read a form's email notifications. FluentForms stores each notification
 * as its OWN `fluentform_form_meta` row (meta_key `notifications`, value = a
 * single JSON object), and the notification `id` IS that row's id — this is
 * what `getEnabledFeeds` iterates at send time and what native/other
 * plugins write. The integration previously stored ALL notifications as one
 * row holding a JSON ARRAY: FF's per-row `json_decode` then saw a list with
 * no top-level `enabled`, so our notifications NEVER SENT, and reading via
 * `->first()` both missed native rows and let a write clobber a native
 * notification. This reads every row, keying each entry by its row id.
 *
 * @return list<array<array-key, mixed>>
 */
function ff_form_notifications(int $form_id): array
{
    if ($form_id <= 0 || !function_exists('wpFluent')) {
        return [];
    }
    $rows = wpFluent()
        ->table('fluentform_form_meta')
        ->where('form_id', $form_id)
        ->where('meta_key', 'notifications')
        ->orderBy('id', 'asc')
        ->get();

    $out = [];
    /** @var mixed $row */
    foreach ($rows as $row) {
        if (!is_object($row)) {
            continue;
        }
        /** @var mixed $decoded */
        $decoded = json_decode((string) ($row->value ?? ''), associative: true);
        if (!is_array($decoded)) {
            continue;
        }
        $decoded['id'] = (int) ($row->id ?? 0);
        $out[] = $decoded;
    }
    return $out;
}

/**
 * Normalise a `sendTo` value to the native object shape. A bare string is an
 * email recipient; an object is merged over the defaults; anything else is an
 * empty email recipient.
 *
 * @param mixed $send_to
 * @return array<string, mixed>
 */
function ff_normalize_send_to(mixed $send_to): array
{
    $defaults = ['type' => 'email', 'email' => '', 'field' => '', 'routing' => []];
    if (is_string($send_to)) {
        return array_merge($defaults, ['email' => trim($send_to)]);
    }
    if (!is_array($send_to)) {
        return $defaults;
    }
    // A plain list of recipient strings (numeric keys) is not the native
    // object — merging it verbatim left `email` empty and stashed the
    // addresses under "0"/"1", producing an enabled feed that delivers to
    // nobody. Fold a string list into the comma-separated `email` FF accepts.
    if ($send_to !== [] && array_keys($send_to) === range(0, count($send_to) - 1)) {
        $emails = [];
        /** @var mixed $entry */
        foreach ($send_to as $entry) {
            if (!is_string($entry) || trim($entry) === '') {
                continue;
            }
            $emails[] = trim($entry);
        }
        return array_merge($defaults, ['email' => implode(', ', $emails)]);
    }
    $clean = [];
    /** @var mixed $value */
    foreach ($send_to as $key => $value) {
        $clean[(string) $key] = $value;
    }
    return array_merge($defaults, $clean);
}

/**
 * Normalise an agent-supplied notification body to the native FluentForms
 * notification object shape so it actually SENDS: `sendTo` as an object
 * `{type, email, field, routing}` (a bare string becomes an email recipient),
 * a top-level boolean `enabled` (the flag `getEnabledFeeds` checks),
 * `conditionals` present, and the `id` key stripped (the row id is the id).
 *
 * @param array<array-key, mixed> $body
 * @return array<string, mixed>
 */
function ff_normalize_notification(array $body): array
{
    unset($body['id']);
    $body['sendTo'] = ff_normalize_send_to($body['sendTo'] ?? null);
    $body['enabled'] = !array_key_exists('enabled', $body) || $body['enabled'] === true || $body['enabled'] === 'true';
    if (!array_key_exists('conditionals', $body) || !is_array($body['conditionals'])) {
        $body['conditionals'] = [];
    }
    if (!array_key_exists('name', $body) || !is_string($body['name']) || $body['name'] === '') {
        $body['name'] = 'Notification';
    }

    $out = [];
    /** @var mixed $value */
    foreach ($body as $key => $value) {
        $out[(string) $key] = $value;
    }
    return $out;
}

/**
 * Insert one notification as its own `notifications` meta row. Returns the
 * new row id (the notification id), or 0 on failure.
 *
 * @param array<array-key, mixed> $notification
 */
function ff_insert_notification_row(int $form_id, array $notification): int
{
    if ($form_id <= 0 || !function_exists('wpFluent')) {
        return 0;
    }
    $id = wpFluent()
        ->table('fluentform_form_meta')
        ->insertGetId([
            'form_id' => $form_id,
            'meta_key' => 'notifications',
            'value' => ff_encode_meta_value($notification),
        ]);
    return (int) $id;
}

/**
 * Overwrite the value of one notification row (by its row id + form scope).
 * Returns true when a row was updated.
 *
 * @param array<array-key, mixed> $notification
 */
function ff_update_notification_row(int $form_id, int $id, array $notification): bool
{
    if ($form_id <= 0 || $id <= 0 || !function_exists('wpFluent')) {
        return false;
    }
    wpFluent()
        ->table('fluentform_form_meta')
        ->where('id', $id)
        ->where('form_id', $form_id)
        ->where('meta_key', 'notifications')
        ->update(['value' => ff_encode_meta_value($notification)]);
    // Success = the row exists after the write. MySQL reports 0 affected rows
    // when the new value is byte-identical to the current one, so relying on
    // affected-rows made an idempotent re-save (same patch twice) fail — the
    // ability is annotated idempotent:true. Check existence instead.
    $row = wpFluent()
        ->table('fluentform_form_meta')
        ->where('id', $id)
        ->where('form_id', $form_id)
        ->where('meta_key', 'notifications')
        ->first();
    return $row !== null;
}

/**
 * Delete one notification row (by row id + form scope). Returns rows removed.
 */
function ff_delete_notification_row(int $form_id, int $id): int
{
    if ($form_id <= 0 || $id <= 0 || !function_exists('wpFluent')) {
        return 0;
    }
    return (int) wpFluent()
        ->table('fluentform_form_meta')
        ->where('id', $id)
        ->where('form_id', $form_id)
        ->where('meta_key', 'notifications')
        ->delete();
}

/**
 * Find a notification by id. Returns [$index, $entry] when present,
 * [-1, null] when absent.
 *
 * @param list<array<array-key, mixed>> $notifications
 *
 * @return array{0:int, 1:?array<array-key, mixed>}
 */
function ff_find_notification_index(array $notifications, int $id): array
{
    foreach ($notifications as $i => $entry) {
        if (!isset($entry['id'])) {
            continue;
        }
        $entry_id = is_int($entry['id']) ? $entry['id'] : (int) ff_str($entry['id']);
        if ($entry_id === $id) {
            return [$i, $entry];
        }
    }
    return [-1, null];
}

/**
 * Read a single Fluent Forms form-meta row. Decodes JSON when the value
 * looks like a JSON object/array; falls back to the raw string otherwise.
 * Returns null when the row is absent.
 */
function ff_get_form_meta(int $form_id, string $key): mixed
{
    if ($form_id <= 0 || !function_exists('wpFluent')) {
        return null;
    }
    $row = wpFluent()->table('fluentform_form_meta')->where('form_id', $form_id)->where('meta_key', $key)->first();
    if (!$row instanceof \stdClass || !property_exists($row, 'value')) {
        return null;
    }
    /** @var mixed $raw */
    $raw = $row->value;
    if (!is_string($raw)) {
        return $raw;
    }
    $trim = ltrim($raw);
    if ($trim === '' || $trim[0] !== '{' && $trim[0] !== '[') {
        return $raw;
    }
    /** @var mixed $decoded */
    $decoded = json_decode($raw, associative: true);
    return $decoded === null ? $raw : $decoded;
}

/**
 * Decode the `form_fields` JSON blob from a Fluent Forms `Form` model. The
 * upstream shape is `{fields: [...], submitButton: {...}}` plus optional
 * top-level keys such as `stepsWrapper` (multi-step forms). Falls back to a
 * minimal empty structure on a malformed/missing blob so the agent always sees
 * the documented shape (no nulls).
 *
 * Only `fields` and `submitButton` are normalised (associative arrays). Every
 * other top-level entry is returned untouched, in stored order, because the
 * write paths re-encode this array over the whole blob — dropping or reshaping
 * a key here deletes or corrupts it on the form (a multi-step form loses its
 * `stepsWrapper`). See ff_fields_blob_entries() for how those values keep
 * their JSON type.
 *
 * @return array{fields: list<array<array-key, mixed>>, submitButton: array<array-key, mixed>, ...<array-key, mixed>}
 */
function ff_decode_fields(string $json): array
{
    if ($json === '') {
        return ['fields' => [], 'submitButton' => []];
    }
    /** @var mixed $decoded */
    $decoded = json_decode($json, associative: true);
    if (!is_array($decoded)) {
        return ['fields' => [], 'submitButton' => []];
    }

    $preserved = ff_fields_blob_entries($json, $decoded);

    $fields = [];
    if (isset($decoded['fields']) && is_array($decoded['fields'])) {
        /** @var mixed $field */
        foreach ($decoded['fields'] as $field) {
            if (!is_array($field)) {
                continue;
            }
            $fields[] = $field;
        }
    }
    $submit = isset($decoded['submitButton']) && is_array($decoded['submitButton']) ? $decoded['submitButton'] : [];
    $preserved['fields'] = $fields;
    $preserved['submitButton'] = $submit;
    return $preserved;
}

/**
 * Top-level entries of a `form_fields` blob, in stored order, for
 * ff_decode_fields() to carry through a write unchanged.
 *
 * Values come from an object-mode decode: associative decoding turns an empty
 * object `{}` into `[]` and a numeric-keyed object `{"0":"a"}` into the list
 * `["a"]`, which re-encode as JSON arrays. As stdClass they re-encode as the
 * same objects. Numeric-looking keys ("0") become int array keys here and are
 * still encoded as object keys, because the caller's array always holds the
 * string keys `fields` / `submitButton`.
 *
 * A top-level JSON list yields no entries (it has no named keys to keep). If
 * object-mode decoding rejects a blob the associative decode accepted (a
 * property name starting with NUL), the associative entries are the fallback.
 *
 * @param array<array-key, mixed> $assoc The same blob, decoded associatively.
 *
 * @return array<array-key, mixed>
 */
function ff_fields_blob_entries(string $json, array $assoc): array
{
    /** @var mixed $object */
    $object = json_decode($json);
    if ($object instanceof \stdClass) {
        return get_object_vars($object);
    }
    return is_array($object) ? [] : $assoc;
}

/**
 * Build the compact list-row representation of a Form. Used by list-forms
 * and as a building block by the duplicate-form / create-form output.
 *
 * Stays small on purpose — never embeds fields[] or settings (progressive
 * disclosure). `submissions_total` is OPTIONAL and computed by the caller
 * because it requires a per-row count() query — list-forms batches them
 * once across the page so the row builder doesn't issue N queries.
 *
 * @return array{
 *     id: int,
 *     title: string,
 *     status: string,
 *     type: string,
 *     has_payment: bool,
 *     has_conversion: bool,
 *     submissions_total: int|null,
 *     submissions_unread: int|null,
 *     created_at: string|null,
 *     updated_at: string|null,
 * }
 */
function ff_form_row(Form $form, ?int $submissions_total = null, ?int $submissions_unread = null): array
{
    return [
        'id' => (int) $form->id,
        'title' => ff_str($form->title),
        'status' => ff_str($form->status),
        'type' => ff_str($form->type),
        'has_payment' => (int) $form->has_payment === 1,
        'has_conversion' => (int) $form->has_conversion === 1,
        'submissions_total' => $submissions_total,
        'submissions_unread' => $submissions_unread,
        'created_at' => ff_to_iso_string($form->created_at),
        'updated_at' => ff_to_iso_string($form->updated_at),
    ];
}

/**
 * Drop non-string + duplicate entries from a caller-supplied `fields`
 * whitelist. Returns null when the resulting list is empty (treat as
 * "no whitelist"). Shared by get-submission and export-submissions —
 * previously lived only in get-submission.php and was a hidden
 * cross-file dependency until audit round 2 flagged it.
 *
 * @param list<mixed> $raw
 *
 * @return list<string>|null
 */
function ff_normalize_field_names(array $raw): ?array
{
    $out = [];
    /** @var mixed $v */
    foreach ($raw as $v) {
        if (!is_string($v) || $v === '') {
            continue;
        }
        if (in_array($v, $out, strict: true)) {
            continue;
        }
        $out[] = $v;
    }
    return $out === [] ? null : $out;
}

/**
 * The submission columns the compact list row is built from — every column
 * ff_submission_from_row copies EXCEPT `response` (the full submission JSON,
 * which the compact row never surfaces). list-submissions selects exactly
 * these so a huge `response` is not loaded into memory just to be discarded.
 *
 * @var list<string>
 */
const FF_SUBMISSION_COMPACT_COLUMNS = [
    'id',
    'form_id',
    'status',
    'user_id',
    'payment_status',
    'payment_method',
    'payment_type',
    'currency',
    'payment_total',
    'total_paid',
    'source_url',
    'ip',
    'browser',
    'device',
    'city',
    'country',
    'is_favourite',
    'created_at',
    'updated_at',
];

/**
 * Hydrate a `\stdClass` row (from wpFluent()->get()) into a Submission
 * model without round-tripping through Submission::find(). Sprint-2 audit
 * round 2 flagged the N+1 in list-submissions and export-submissions
 * where every row triggered an extra SELECT; the rows already carry every
 * column we project, so we copy them directly.
 *
 * Only columns the integration actually surfaces are copied — anything
 * else on the row is ignored.
 */
function ff_submission_from_row(\stdClass $row): Submission
{
    $sub = new Submission();
    $vars = get_object_vars($row);
    foreach ([
        'id',
        'form_id',
        'status',
        'response',
        'user_id',
        'payment_status',
        'payment_method',
        'payment_type',
        'currency',
        'payment_total',
        'total_paid',
        'source_url',
        'ip',
        'browser',
        'device',
        'city',
        'country',
        'is_favourite',
        'created_at',
        'updated_at',
    ] as $col) {
        if (!array_key_exists($col, $vars)) {
            continue;
        }
        $sub->{$col} = $vars[$col];
    }
    return $sub;
}

// ============================================================================
// Sprint 4 — Pro entity helpers (transactions, subscriptions, drafts)
// ============================================================================
//
// Unlike the free-tier entities, FF Pro does NOT expose Eloquent Models for
// transactions / subscriptions / draft_submissions — every read and write
// flows through wpFluent() against the raw tables. The resolvers below
// return stdClass rows (or a WP_Error); projection helpers (ff_transaction_
// row / ff_subscription_row / ff_draft_row) take stdClass too.

/**
 * Resolve a transaction id to its stdClass row. Returns
 * fluentforms_transaction_not_found (404) for missing ids.
 */
function ff_transaction_or_error(mixed $tx_id): \stdClass|WP_Error
{
    if (!is_int($tx_id) && !is_float($tx_id) && !(is_string($tx_id) && ctype_digit($tx_id))) {
        return ff_invalid('transaction_id must be a numeric id (positive integer).');
    }
    $id = ff_pos_int_value($tx_id);
    if ($id <= 0) {
        return ff_invalid('transaction_id must be a positive integer.');
    }
    if (!function_exists('wpFluent')) {
        return ff_operation_failed('Fluent Forms query builder is unavailable.');
    }
    $row = wpFluent()->table('fluentform_transactions')->where('id', $id)->first();
    if (!$row instanceof \stdClass) {
        return ff_not_found('transaction', $id);
    }
    return $row;
}

/**
 * Resolve a subscription id to its stdClass row.
 */
function ff_subscription_or_error(mixed $sub_id): \stdClass|WP_Error
{
    if (!is_int($sub_id) && !is_float($sub_id) && !(is_string($sub_id) && ctype_digit($sub_id))) {
        return ff_invalid('subscription_id must be a numeric id (positive integer).');
    }
    $id = ff_pos_int_value($sub_id);
    if ($id <= 0) {
        return ff_invalid('subscription_id must be a positive integer.');
    }
    if (!function_exists('wpFluent')) {
        return ff_operation_failed('Fluent Forms query builder is unavailable.');
    }
    $row = wpFluent()->table('fluentform_subscriptions')->where('id', $id)->first();
    if (!$row instanceof \stdClass) {
        return ff_not_found('subscription', $id);
    }
    return $row;
}

/**
 * Resolve a draft-submission id to its stdClass row.
 */
function ff_draft_or_error(mixed $draft_id): \stdClass|WP_Error
{
    if (!is_int($draft_id) && !is_float($draft_id) && !(is_string($draft_id) && ctype_digit($draft_id))) {
        return ff_invalid('draft_id must be a numeric id (positive integer).');
    }
    $id = ff_pos_int_value($draft_id);
    if ($id <= 0) {
        return ff_invalid('draft_id must be a positive integer.');
    }
    if (!function_exists('wpFluent')) {
        return ff_operation_failed('Fluent Forms query builder is unavailable.');
    }
    $row = wpFluent()->table('fluentform_draft_submissions')->where('id', $id)->first();
    if (!$row instanceof \stdClass) {
        return ff_not_found('draft_submission', $id);
    }
    return $row;
}

/**
 * Coerce a mixed scalar to a positive int (>=1). Used by the resolvers
 * above; smaller than ff_pos_int because it doesn't reach into $input.
 */
function ff_pos_int_value(mixed $raw): int
{
    if (!is_numeric($raw)) {
        return 0;
    }
    $float = (float) $raw;
    if (!is_finite($float) || $float >= (float) PHP_INT_MAX || $float < 1.0) {
        return 0;
    }
    return (int) $raw;
}

/**
 * Compact transaction row projection. Used by list-transactions and as the
 * core of get-transaction. Reads via ff_row_int / ff_row_str so an arbitrary
 * stdClass shape is tolerated.
 *
 * @return array<string, mixed>
 */
function ff_transaction_row(\stdClass $tx): array
{
    $sub_id = ff_row_int($tx, column: 'subscription_id');
    $card_last4 = ff_row_int($tx, column: 'card_last_4');
    return [
        'id' => ff_row_id($tx),
        'transaction_hash' => ff_row_str($tx, column: 'transaction_hash'),
        'form_id' => ff_row_int($tx, column: 'form_id'),
        'submission_id' => ff_row_int($tx, column: 'submission_id'),
        'subscription_id' => $sub_id > 0 ? $sub_id : null,
        'transaction_type' => ff_row_str($tx, column: 'transaction_type'),
        'payment_method' => ff_row_str($tx, column: 'payment_method'),
        'charge_id' => ff_row_str($tx, column: 'charge_id'),
        'card_brand' => ff_row_str($tx, column: 'card_brand'),
        'card_last_4' => $card_last4 > 0 ? $card_last4 : null,
        'payment_total' => ff_row_int($tx, column: 'payment_total'),
        'currency' => ff_row_str($tx, column: 'currency'),
        'status' => ff_row_str($tx, column: 'status'),
        'payment_mode' => ff_row_str($tx, column: 'payment_mode'),
        'payer_name' => ff_row_str($tx, column: 'payer_name'),
        'payer_email' => ff_row_str($tx, column: 'payer_email'),
        'created_at' => ff_row_nullable_str($tx, column: 'created_at'),
        'updated_at' => ff_row_nullable_str($tx, column: 'updated_at'),
    ];
}

/**
 * Compact subscription row projection.
 *
 * @return array<string, mixed>
 */
function ff_subscription_row(\stdClass $sub): array
{
    $parent_tx = ff_row_int($sub, column: 'parent_transaction_id');
    return [
        'id' => ff_row_id($sub),
        'form_id' => ff_row_int($sub, column: 'form_id'),
        'submission_id' => ff_row_int($sub, column: 'submission_id'),
        'item_name' => ff_row_str($sub, column: 'item_name'),
        'plan_name' => ff_row_str($sub, column: 'plan_name'),
        'billing_interval' => ff_row_str($sub, column: 'billing_interval'),
        'recurring_amount' => ff_row_int($sub, column: 'recurring_amount'),
        'initial_amount' => ff_row_int($sub, column: 'initial_amount'),
        'quantity' => ff_row_int($sub, column: 'quantity'),
        'trial_days' => ff_row_int($sub, column: 'trial_days'),
        'bill_times' => ff_row_int($sub, column: 'bill_times'),
        'bill_count' => ff_row_int($sub, column: 'bill_count'),
        'vendor_subscription_id' => ff_row_str($sub, column: 'vendor_subscription_id'),
        'vendor_customer_id' => ff_row_str($sub, column: 'vendor_customer_id'),
        'vendor_plan_id' => ff_row_str($sub, column: 'vendor_plan_id'),
        'status' => ff_row_str($sub, column: 'status'),
        'parent_transaction_id' => $parent_tx > 0 ? $parent_tx : null,
        'expiration_at' => ff_row_nullable_str($sub, column: 'expiration_at'),
        'created_at' => ff_row_nullable_str($sub, column: 'created_at'),
        'updated_at' => ff_row_nullable_str($sub, column: 'updated_at'),
    ];
}

/**
 * Compact draft-submission row projection. Same progressive-disclosure
 * rules as the submission rows — never embeds `response`.
 *
 * @return array<string, mixed>
 */
function ff_draft_row(\stdClass $draft): array
{
    $user_id = ff_row_int($draft, column: 'user_id');
    return [
        'id' => ff_row_id($draft),
        'form_id' => ff_row_int($draft, column: 'form_id'),
        'hash' => ff_row_str($draft, column: 'hash'),
        'type' => ff_row_str($draft, column: 'type'),
        'step_completed' => ff_row_int($draft, column: 'step_completed'),
        'user_id' => $user_id > 0 ? $user_id : null,
        'source_url' => ff_row_str($draft, column: 'source_url'),
        'browser' => ff_row_str($draft, column: 'browser'),
        'device' => ff_row_str($draft, column: 'device'),
        'country' => '',
        'used' => ff_row_int($draft, column: 'used') === 1,
        'created_at' => ff_row_nullable_str($draft, column: 'created_at'),
        'updated_at' => ff_row_nullable_str($draft, column: 'updated_at'),
    ];
}

/**
 * Resolve the form for a settings READ ability + run the version + cap
 * gates. Reads accept either fluentform_forms_manager OR
 * fluentform_settings_manager. Returns the Form or a WP_Error. Moved
 * from manage-form-settings.php in audit round 3 (was a hidden cross-
 * file dependency used by manage-notifications + manage-confirmations).
 */
function ff_settings_load_form_for_read(mixed $form_id_raw): Form|WP_Error
{
    return ff_settings_resolve_with_caps(
        $form_id_raw,
        primary: 'fluentform_forms_manager',
        fallback: 'fluentform_settings_manager',
    );
}

/**
 * Resolve the form for a settings WRITE ability + run the version + cap
 * gates. Writes prefer fluentform_settings_manager; forms_manager is the
 * fallback. Moved to helpers.php in audit round 3.
 */
function ff_settings_load_form_for_write(mixed $form_id_raw): Form|WP_Error
{
    return ff_settings_resolve_with_caps(
        $form_id_raw,
        primary: 'fluentform_settings_manager',
        fallback: 'fluentform_forms_manager',
    );
}

/**
 * Internal: version gate + resolve form + try `$primary` cap, fall back
 * to `$fallback` cap, surface the primary's WP_Error if both fail.
 */
function ff_settings_resolve_with_caps(mixed $form_id_raw, string $primary, string $fallback): Form|WP_Error
{
    if (!ff_detect_setup()['min_satisfied']) {
        return ff_version_unsupported();
    }
    $form = ff_form_or_error($form_id_raw);
    if ($form instanceof WP_Error) {
        return $form;
    }
    $cap = ff_capability_or_error($primary, form_id: (int) $form->id);
    if (!$cap instanceof WP_Error) {
        return $form;
    }
    $cap2 = ff_capability_or_error($fallback, form_id: (int) $form->id);
    if ($cap2 instanceof WP_Error) {
        return $cap;
    }
    return $form;
}

/**
 * Validate a URL field is safe to render in an <a href> / <form action>
 * attribute. `esc_url_raw` does NOT filter `javascript:`, `data:`,
 * `vbscript:` URIs — round-3 audit CRITICAL #C. This helper enforces
 * a strict scheme whitelist (http, https, mailto, tel). Returns a clean
 * URL string OR an empty string when the input is rejected.
 */
function ff_validate_url_safe(string $url): string
{
    $trimmed = trim($url);
    if ($trimmed === '') {
        return '';
    }
    // Reject protocol-relative URLs. "//evil.com" (and the backslash variants
    // "\\/evil.com" / "/\evil.com" browsers normalise to it) carry no scheme,
    // so parse_url returns none and the scheme whitelist below is skipped;
    // esc_url_raw keeps them, and on redirectTo=customUrl they inherit the
    // current scheme and navigate off-site — an open redirect.
    if (str_starts_with(str_replace(search: '\\', replace: '/', subject: $trimmed), '//')) {
        return '';
    }
    // esc_url_raw drops javascript: silently on later versions — strict
    // scheme check first so behaviour is identical across WP versions.
    $scheme = parse_url($trimmed, component: PHP_URL_SCHEME);
    if (is_string($scheme)) {
        $scheme_lower = strtolower($scheme);
        $allowed = ['http', 'https', 'mailto', 'tel'];
        if (!in_array($scheme_lower, $allowed, strict: true)) {
            return '';
        }
    }
    return esc_url_raw($trimmed);
}

/**
 * Validate an email-header field: reject CRLF (\r, \n) and NULL bytes so
 * a caller can't inject extra `Bcc:` / `Cc:` lines via `sendTo`,
 * `fromEmail`, `replyTo`, `bcc`. Round-3 audit CRITICAL #D. Returns the
 * cleaned string OR null when the input contains injection characters.
 */
function ff_validate_email_header(string $value): ?string
{
    if (strpbrk($value, characters: "\r\n\0") !== false) {
        return null;
    }
    return $value;
}

/**
 * True when a sendTo value carries no CRLF / NUL byte in any string that would
 * become a mail header. Accepts the bare-string form and the native object
 * form {type, email, field, routing:[{email, field, …}]}. The is_string CRLF
 * guard in ff_filter_notification_body alone let an object-shaped recipient
 * skip the check, so this inspects the recipient strings inside it.
 *
 * @param mixed $send_to
 */
function ff_send_to_headers_safe(mixed $send_to): bool
{
    if (is_string($send_to)) {
        return ff_validate_email_header($send_to) !== null;
    }
    if (!is_array($send_to)) {
        // Non-string / non-array: later normalisation + validation handles it.
        return true;
    }
    foreach (['email', 'field'] as $recipient_key) {
        /** @var mixed $recipient */
        $recipient = $send_to[$recipient_key] ?? null;
        if (is_string($recipient) && ff_validate_email_header($recipient) === null) {
            return false;
        }
    }
    /** @var mixed $routing */
    $routing = $send_to['routing'] ?? null;
    if (is_array($routing)) {
        /** @var mixed $route */
        foreach ($routing as $route) {
            if (!is_array($route)) {
                continue;
            }
            /** @var mixed $route_email */
            $route_email = $route['email'] ?? null;
            if (is_string($route_email) && ff_validate_email_header($route_email) === null) {
                return false;
            }
        }
    }
    return true;
}

/**
 * Per-key type guard for the formSettings whitelist. Round-3 audit
 * CRITICAL #E. Returns true when the value is acceptable for the given
 * key, false otherwise — caller drops rejected keys and surfaces them in
 * warnings. The set covers the keys edit-form-settings whitelists; null
 * is allowed everywhere (means "remove the key").
 *
 * - restrictions / layout / submitButton / conditionals must be objects
 *   (login-required and form scheduling live under restrictions.requireLogin
 *   and restrictions.scheduleForm — FF reads no top-level requireLogin /
 *   formExpires key).
 */
function ff_validate_form_setting_value(string $key, mixed $value): bool
{
    if ($value === null) {
        return true;
    }
    return match ($key) {
        'restrictions', 'layout', 'submitButton', 'conditionals' => is_array($value),
        default => false,
    };
}

/**
 * Per-key type guard for the confirmation whitelist. Round-3 audit
 * CRITICAL #C + #E (combined: customUrl scheme whitelist + type check).
 */
function ff_validate_confirmation_value(string $key, mixed $value): bool
{
    if ($value === null) {
        return true;
    }
    return match ($key) {
        'messageToShow', 'redirectTo', 'samePageFormBehavior', 'customUrl' => is_string($value),
        'customPage' => is_int($value) || is_string($value) && ctype_digit($value),
        default => false,
    };
}

/**
 * Whitelisted top-level keys for a notification body. Round-3 audit
 * CRITICAL #F — previously every caller-supplied top-level key landed
 * in the stored notification (arbitrary-field injection).
 *
 * @return list<string>
 */
function ff_notification_whitelist(): array
{
    return [
        'name',
        'sendTo',
        'fromName',
        'fromEmail',
        'replyTo',
        'bcc',
        'cc',
        'subject',
        'message',
        'enabled',
        'conditionals',
    ];
}

/**
 * Filter a notification body to the whitelisted top-level keys + sanitise
 * the email-header columns to reject CRLF injection. Returns the cleaned
 * body, the list of dropped keys, and a flag set when an email-header
 * field was rejected (so the caller can surface a 400).
 *
 * @param array<array-key, mixed> $body
 *
 * @return array{0: array<string, mixed>, 1: list<string>, 2: ?string}
 */
function ff_filter_notification_body(array $body): array
{
    $whitelist = array_flip(ff_notification_whitelist());
    $kept = [];
    $dropped = [];
    /** @var mixed $value */
    foreach ($body as $key => $value) {
        $key_str = (string) $key;
        if (!isset($whitelist[$key_str])) {
            $dropped[] = $key_str;
            continue;
        }
        // CRLF guard on fields that become mail headers. fromName + subject
        // land in the From:/Subject: headers; fromEmail/replyTo/bcc/cc are
        // address headers. The message body is intentionally NOT guarded (it
        // is multi-line HTML). Non-strings fall through to later validation.
        $header_scalar_keys = ['fromName', 'fromEmail', 'replyTo', 'bcc', 'cc', 'subject'];
        if (in_array($key_str, $header_scalar_keys, strict: true) && is_string($value)) {
            $clean = ff_validate_email_header($value);
            if ($clean === null) {
                return [[], [], sprintf('%s contains CRLF / NULL bytes (email header injection).', $key_str)];
            }
            $kept[$key_str] = $clean;
            continue;
        }
        // sendTo may be a bare string OR the object form {type,email,field,
        // routing:[{email,field,…}]}; the is_string guard alone let an
        // object-shaped recipient skip the check, so inspect the recipient
        // strings inside it too.
        if ($key_str === 'sendTo' && !ff_send_to_headers_safe($value)) {
            return [[], [], 'sendTo contains CRLF / NULL bytes (email header injection).'];
        }
        $kept[$key_str] = $value;
    }
    sort($dropped);
    return [$kept, $dropped, null];
}

/**
 * Read an integer input field with a default + minimum. Coerces "wrong type"
 * to the default rather than throwing — abilities API already validates the
 * schema, so the callback only needs to be defensive against missing keys.
 *
 * @param array<string, mixed> $input
 */
function ff_input_int_or(array $input, string $key, int $default, int $min = 0): int
{
    $value = ff_coerce_int($input[$key] ?? null);
    if ($value === null || $value < $min) {
        return $default;
    }
    return $value;
}

/**
 * Coerce a loosely-typed scalar to int. The abilities validator accepts a
 * numeric string ("633") or an integral float (633.0) for an `integer`
 * schema property, so a strict is_int() read would silently DROP such a value.
 * For a FILTER input that means the filter — and any per-form capability gate
 * keyed off it — silently vanishes, broadening the query. This mirrors the
 * numeric-string tolerance the single-entity resolvers already apply, so
 * form_id="633" behaves the same on the list abilities as on get / delete.
 * Returns null when the value is not an integer.
 *
 * @param mixed $value
 */
function ff_coerce_int(mixed $value): ?int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_float($value) && (float) (int) $value === $value) {
        return (int) $value;
    }
    if (is_string($value)) {
        $trimmed = trim($value);
        $digits = $trimmed !== '' && $trimmed[0] === '-' ? substr($trimmed, offset: 1) : $trimmed;
        if ($digits !== '' && ctype_digit($digits)) {
            return (int) $trimmed;
        }
    }
    return null;
}

/**
 * Read a positive integer input field (≥ 1). Returns null when the key is
 * absent or invalid — caller uses null to mean "no filter".
 *
 * @param array<string, mixed> $input
 */
function ff_input_pos_int(array $input, string $key): ?int
{
    $value = ff_coerce_int($input[$key] ?? null);
    return $value !== null && $value >= 1 ? $value : null;
}

/**
 * Read a non-negative integer input field (≥ 0). Same shape as
 * ff_input_pos_int but accepts 0 as a valid lower bound. Returns null when
 * the key is absent or invalid.
 *
 * @param array<string, mixed> $input
 */
function ff_input_non_neg_int(array $input, string $key): ?int
{
    $value = ff_coerce_int($input[$key] ?? null);
    return $value !== null && $value >= 0 ? $value : null;
}

/**
 * Read a string input field with a default of ''. Wrong type / absent both
 * collapse to the empty string.
 *
 * @param array<string, mixed> $input
 */
function ff_input_str(array $input, string $key, string $default = ''): string
{
    return isset($input[$key]) && is_string($input[$key]) ? $input[$key] : $default;
}

/**
 * Read a bool input field. Returns null when absent / not boolean-like —
 * caller uses null to mean "no filter". Accepts the loose forms the abilities
 * validator lets through for a `boolean` property (0/1, "true"/"false",
 * "1"/"0") so a schema-valid value can't be silently dropped, turning a
 * `is_favourite=false` filter into a no-op.
 *
 * @param array<string, mixed> $input
 */
function ff_input_nullable_bool(array $input, string $key): ?bool
{
    /** @var mixed $value */
    $value = $input[$key] ?? null;
    if (is_bool($value)) {
        return $value;
    }
    if (is_int($value) && ($value === 0 || $value === 1)) {
        return $value === 1;
    }
    if (is_string($value)) {
        $normalised = strtolower(trim($value));
        if (in_array($normalised, ['true', '1'], strict: true)) {
            return true;
        }
        if (in_array($normalised, ['false', '0'], strict: true)) {
            return false;
        }
    }
    return null;
}

/**
 * Compact list-row representation of a Submission. Used by list-submissions
 * and export-submissions. NEVER embeds the decoded `response` JSON — that
 * lives only in get-submission for progressive disclosure.
 *
 * @return array{
 *     id: int,
 *     form_id: int,
 *     status: string,
 *     user_id: int|null,
 *     payment_status: string|null,
 *     payment_method: string|null,
 *     currency: string|null,
 *     payment_total: int|null,
 *     source_url: string,
 *     ip: string,
 *     browser: string,
 *     device: string,
 *     country: string,
 *     is_favourite: bool,
 *     created_at: string|null,
 *     updated_at: string|null,
 * }
 */
function ff_submission_row(Submission $sub): array
{
    // "This submission has payment data" = a non-empty payment_status OR a
    // non-zero total. The earlier `!== ''` check let through the NULL default
    // FF writes on non-payment submissions, surfacing `""` where the contract
    // declared `null`.
    $status_str = ff_str($sub->payment_status);
    $is_payment_set = (int) $sub->payment_total > 0 || $status_str !== '';
    return [
        'id' => (int) $sub->id,
        'form_id' => (int) $sub->form_id,
        'status' => ff_str($sub->status),
        'user_id' => $sub->user_id === null ? null : (int) $sub->user_id,
        'payment_status' => $is_payment_set ? $status_str : null,
        'payment_method' => $is_payment_set ? ff_str($sub->payment_method) : null,
        'currency' => $is_payment_set ? ff_str($sub->currency) : null,
        'payment_total' => $is_payment_set ? (int) $sub->payment_total : null,
        'source_url' => ff_str($sub->source_url),
        'ip' => ff_str($sub->ip),
        'browser' => ff_str($sub->browser),
        'device' => ff_str($sub->device),
        'country' => ff_str($sub->country),
        'is_favourite' => (int) $sub->is_favourite === 1,
        'created_at' => ff_to_iso_string($sub->created_at),
        'updated_at' => ff_to_iso_string($sub->updated_at),
    ];
}

/**
 * Full get-submission payload: compact row + decoded response mapped to
 * friendly `{field_name, label, value}` triples + optional payment block
 * (when Pro present and the form has_payment). Progressive disclosure
 * applies per-field via `max_field_bytes`.
 *
 * @param list<string>|null $only_fields Optional whitelist — when non-null, only these field names appear in response[].
 *
 * @return array<string, mixed>
 */
function ff_submission_full(Submission $sub, ?array $only_fields, int $max_field_bytes): array
{
    $payload = ff_submission_row($sub);
    $payload['response'] = ff_submission_response($sub, $only_fields, $max_field_bytes);
    $payload['payment'] = ff_supports('payments') ? ff_payment_block($sub) : null;
    return $payload;
}

/**
 * Decode the submission response JSON into a list of friendly triples
 * `{field_name, label, value, truncated?, full_size?}`. Looks up labels
 * from the parent form's form_fields when possible.
 *
 * @param list<string>|null $only_fields
 *
 * @return list<array<string, mixed>>
 */
function ff_submission_response(Submission $sub, ?array $only_fields, int $max_field_bytes): array
{
    /** @var mixed $decoded */
    $decoded = json_decode(ff_str($sub->response), associative: true);
    if (!is_array($decoded)) {
        return [];
    }
    $labels = ff_field_label_map((int) $sub->form_id);
    $cap = max(1, min(ff_absolute_max_field_bytes(), $max_field_bytes));

    $out = [];
    /** @var mixed $value */
    foreach ($decoded as $name => $value) {
        $name_str = (string) $name;
        if ($only_fields !== null && !in_array($name_str, $only_fields, strict: true)) {
            continue;
        }
        $out[] = ff_response_triple($name_str, $value, $labels, $cap);
    }
    return $out;
}

/**
 * Build the `{field_name, label, value, truncated?, full_size?}` triple
 * for one entry in the submission response. Scalar values get truncated
 * to `cap` bytes; arrays / objects are serialised to JSON first.
 *
 * @param array<string, string> $labels
 *
 * @return array<string, mixed>
 */
function ff_response_triple(string $name, mixed $value, array $labels, int $cap): array
{
    $value_str = ff_value_to_str($value);
    $truncated = ff_truncate_field($value_str, $cap);
    $triple = [
        'field_name' => $name,
        'label' => $labels[$name] ?? $name,
        'value' => $truncated['value'],
    ];
    if ($truncated['truncated']) {
        $triple['truncated'] = true;
        $triple['full_size'] = $truncated['full_size'];
    }
    return $triple;
}

/**
 * Coerce a mixed value to a string for response surfacing. Arrays are
 * serialised to JSON; scalars are cast; everything else returns ''.
 */
function ff_value_to_str(mixed $value): string
{
    if (is_array($value)) {
        $serialised = wp_json_encode($value, flags: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return is_string($serialised) ? $serialised : '';
    }
    if (is_scalar($value)) {
        return (string) $value;
    }
    return '';
}

/**
 * Map of `attributes.name` → `settings.label` for every field in a form.
 * Used by ff_submission_response to surface friendly labels alongside the
 * raw response keys. Empty when the form is missing or has no fields[].
 *
 * @return array<string, string>
 */
function ff_field_label_map(int $form_id): array
{
    if ($form_id <= 0) {
        return [];
    }
    /** @var Form|null $form */
    $form = Form::find($form_id);
    if (!$form instanceof Form) {
        return [];
    }
    $fields = ff_decode_fields(ff_str($form->form_fields))['fields'];
    $map = [];
    foreach ($fields as $field) {
        $attrs = isset($field['attributes']) && is_array($field['attributes']) ? $field['attributes'] : [];
        $name = isset($attrs['name']) && is_string($attrs['name']) ? $attrs['name'] : '';
        if ($name === '') {
            continue;
        }
        $settings = isset($field['settings']) && is_array($field['settings']) ? $field['settings'] : [];
        $label = isset($settings['label']) && is_string($settings['label']) ? $settings['label'] : $name;
        $map[$name] = $label;
    }
    return $map;
}

/**
 * Read-only payment block for a submission, surfaced when FF Pro is loaded
 * AND the submission has payment data. Returns null when either is false.
 *
 * @return array{
 *     status: string,
 *     method: string,
 *     type: string,
 *     currency: string,
 *     total: int,
 *     paid: int,
 * }|null
 */
function ff_payment_block(Submission $sub): ?array
{
    $total = (int) $sub->payment_total;
    $status = ff_str($sub->payment_status);
    if ($total <= 0 && $status === '') {
        return null;
    }
    return [
        'status' => $status,
        'method' => ff_str($sub->payment_method),
        'type' => ff_str($sub->payment_type),
        'currency' => ff_str($sub->currency),
        'total' => $total,
        'paid' => (int) $sub->total_paid,
    ];
}

/**
 * Coerce the result of a `wpFluent()->...->get()` chain to a plain PHP
 * array. The real Fluent ORM returns a `\FluentForm\Framework\Support\Collection`
 * (Laravel-style wrapper) — our stubs annotate it as `list<\stdClass>` for
 * mago analyze, but the runtime instance is the Collection. Every ability
 * that consumes get() results goes through this helper so we don't sprinkle
 * `->all()` / instanceof checks across the codebase.
 *
 * @return list<\stdClass>
 */
function ff_rows_to_array(mixed $rows): array
{
    if (is_array($rows)) {
        return array_values(array_filter($rows, static fn($r): bool => $r instanceof \stdClass));
    }
    if ($rows instanceof \stdClass) {
        return [$rows];
    }
    if (is_object($rows) && method_exists($rows, 'all')) {
        /** @var mixed $all */
        $all = $rows->all();
        if (is_array($all)) {
            return array_values(array_filter($all, static fn($r): bool => $r instanceof \stdClass));
        }
    }
    if (is_iterable($rows)) {
        $out = [];
        /** @var mixed $r */
        foreach ($rows as $r) {
            if (!$r instanceof \stdClass) {
                continue;
            }
            $out[] = $r;
        }
        return $out;
    }
    return [];
}

/**
 * Coerce an Eloquent date-ish value to an ISO-8601-ish string, or null when
 * absent. Eloquent's $dates magic surfaces `created_at` / `updated_at` as
 * Carbon objects (which implement __toString), but the declared output
 * schema is `string|null` — left as objects WP Abilities rejects the
 * payload with ability_invalid_output. The runtime can also surface a
 * plain MySQL datetime string, an empty string, or null on a freshly
 * inserted unsaved row, so this helper covers all four shapes.
 */
function ff_to_iso_string(mixed $value): ?string
{
    if ($value === null || $value === '') {
        return null;
    }
    if (is_string($value)) {
        return $value;
    }
    if ($value instanceof \Stringable) {
        return (string) $value;
    }
    return null;
}

/**
 * Build the full get-form payload. The `include` parameter is the list of
 * sub-resources to materialise — each is OPT-IN (progressive disclosure):
 * `fields`, `settings`, `notifications`, `confirmation`, `meta_keys`.
 *
 * @param list<string> $include
 *
 * @return array{
 *     id: int,
 *     title: string,
 *     status: string,
 *     type: string,
 *     has_payment: bool,
 *     has_conversion: bool,
 *     created_at: string|null,
 *     updated_at: string|null,
 *     fields: list<array<array-key, mixed>>|null,
 *     submit_button: array<array-key, mixed>|null,
 *     settings: mixed,
 *     notifications: mixed,
 *     confirmation: mixed,
 *     meta_keys: list<string>|null,
 * }
 */
function ff_form_full(Form $form, array $include): array
{
    $payload = [
        'id' => (int) $form->id,
        'title' => ff_str($form->title),
        'status' => ff_str($form->status),
        'type' => ff_str($form->type),
        'has_payment' => (int) $form->has_payment === 1,
        'has_conversion' => (int) $form->has_conversion === 1,
        'created_at' => ff_to_iso_string($form->created_at),
        'updated_at' => ff_to_iso_string($form->updated_at),
        'fields' => null,
        'submit_button' => null,
        'settings' => null,
        'notifications' => null,
        'confirmation' => null,
        'meta_keys' => null,
    ];

    if (in_array('fields', $include, strict: true)) {
        $decoded = ff_decode_fields(ff_str($form->form_fields));
        $payload['fields'] = $decoded['fields'];
        $payload['submit_button'] = $decoded['submitButton'];
    }
    if (in_array('settings', $include, strict: true)) {
        $payload['settings'] = ff_get_form_meta((int) $form->id, key: 'formSettings');
    }
    if (in_array('notifications', $include, strict: true)) {
        $payload['notifications'] = ff_form_notifications((int) $form->id);
    }
    if (in_array('confirmation', $include, strict: true)) {
        /** @var mixed $settings_for_confirmation */
        $settings_for_confirmation = ff_get_form_meta((int) $form->id, key: 'formSettings');
        $payload['confirmation'] = is_array($settings_for_confirmation)
        && isset($settings_for_confirmation['confirmation'])
            ? $settings_for_confirmation['confirmation']
            : null;
    }
    if (in_array('meta_keys', $include, strict: true)) {
        $payload['meta_keys'] = ff_list_meta_keys((int) $form->id);
    }

    return $payload;
}

/**
 * List the meta_key column for a form, deduplicated. Used so the agent can
 * discover what `formMeta` rows exist (notifications, integrations feeds,
 * advanced validations, …) without having to fetch each blob.
 *
 * @return list<string>
 */
function ff_list_meta_keys(int $form_id): array
{
    if ($form_id <= 0 || !function_exists('wpFluent')) {
        return [];
    }
    $rows = ff_rows_to_array(wpFluent()->table('fluentform_form_meta')->where('form_id', $form_id)->get());
    $keys = [];
    foreach ($rows as $row) {
        if (!property_exists($row, 'meta_key')) {
            continue;
        }
        /** @var mixed $key */
        $key = $row->meta_key;
        if (is_string($key) && $key !== '' && !in_array($key, $keys, strict: true)) {
            $keys[] = $key;
        }
    }
    sort($keys);
    return $keys;
}

/**
 * Source-of-truth field catalog. Each entry is the agent-facing handle on
 * one Fluent Forms element type. `supported_settings` is the whitelist of
 * keys the add-field / edit-field callbacks accept inside `settings.*` —
 * anything outside is dropped silently (the input-shape vocabulary is small
 * and stable on purpose; the FF UI is far larger and changes between
 * releases). All entries here are pinned to the v6.0 baseline.
 *
 * The catalog is intentionally curated, NOT a runtime scan of the FF
 * Component registry, because FF doesn't expose per-type default settings
 * in a structured way and a scan would surface internal types the agent
 * can't usefully construct (admin-only widgets, payment-summary helpers
 * that depend on a payment_method sibling, etc.).
 *
 * @return array<string, array{type: string, label: string, description: string, category: string, pro_only: bool, supported_settings: list<string>}>
 */
function ff_field_catalog(): array
{
    /** @var array<string, array{type:string,label:string,description:string,category:string,pro_only:bool,supported_settings:list<string>}>|null $cache */
    static $cache = null;

    if ($cache !== null) {
        return $cache;
    }

    return $cache = array_merge(
        ff_field_catalog_general_inputs(),
        ff_field_catalog_general_choices(),
        ff_field_catalog_advanced(),
        ff_field_catalog_pro_advanced(),
        ff_field_catalog_pro_dynamic(),
        ff_field_catalog_pro_payment(),
    );
}

/**
 * Shared settings whitelist applied to almost every input field. Per-type
 * entries extend this list rather than replacing it.
 *
 * @return list<string>
 */
function ff_field_catalog_base_settings(): array
{
    return [
        'label',
        'admin_field_label',
        'placeholder',
        'help_message',
        'container_class',
        'class',
        'visible',
        'conditional_logics',
        'validation_rules',
    ];
}

/**
 * Free-tier general "input" field types: text, email, number, textarea, url,
 * password, hidden, date. The choice fields (select / checkbox / radio) live
 * in `ff_field_catalog_general_choices` to keep each function under the
 * Halstead volume threshold mago lints for.
 *
 * @return array<string, array{type:string,label:string,description:string,category:string,pro_only:bool,supported_settings:list<string>}>
 */
function ff_field_catalog_general_inputs(): array
{
    $base = ff_field_catalog_base_settings();
    $items = [];

    $items['input_text'] = [
        'type' => 'input_text',
        'label' => 'Single Line Text',
        'description' => 'Standard one-line text input.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['maxlength', 'minlength', 'default_value']),
    ];
    $items['input_email'] = [
        'type' => 'input_email',
        'label' => 'Email',
        'description' => 'Email input with built-in format validation.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['email_validation_action', 'default_value']),
    ];
    $items['input_number'] = [
        'type' => 'input_number',
        'label' => 'Number',
        'description' => 'Numeric input with min/max/step.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['min', 'max', 'step', 'default_value']),
    ];
    $items['textarea'] = [
        'type' => 'textarea',
        'label' => 'Paragraph Text',
        'description' => 'Multi-line text area.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['maxlength', 'rows', 'cols', 'default_value']),
    ];
    $items['input_url'] = [
        'type' => 'input_url',
        'label' => 'URL',
        'description' => 'URL input with format validation.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['default_value']),
    ];
    $items['input_password'] = [
        'type' => 'input_password',
        'label' => 'Password',
        'description' => 'Masked password input.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['minlength', 'maxlength']),
    ];
    $items['input_hidden'] = [
        'type' => 'input_hidden',
        'label' => 'Hidden Field',
        'description' => 'Hidden value carried with the submission.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => ['label', 'admin_field_label', 'default_value'],
    ];
    $items['input_date'] = [
        'type' => 'input_date',
        'label' => 'Date / Time',
        'description' => 'Date / time picker.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['date_format', 'min_date', 'max_date', 'disable_dates']),
    ];

    return $items;
}

/**
 * Free-tier general "choice" field types: dropdown, multi-select, checkbox,
 * radio. Split out of the input set so each function stays under the
 * Halstead volume threshold.
 *
 * @return array<string, array{type:string,label:string,description:string,category:string,pro_only:bool,supported_settings:list<string>}>
 */
function ff_field_catalog_general_choices(): array
{
    $base = ff_field_catalog_base_settings();
    $items = [];

    $items['select'] = [
        'type' => 'select',
        'label' => 'Dropdown',
        'description' => 'Single-select dropdown.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['advanced_options', 'enable_select_2']),
    ];
    $items['multi_select'] = [
        'type' => 'multi_select',
        'label' => 'Multiple Choice',
        'description' => 'Multi-select dropdown.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['advanced_options']),
    ];
    $items['input_checkbox'] = [
        'type' => 'input_checkbox',
        'label' => 'Checkboxes',
        'description' => 'One or more checkable options.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['advanced_options', 'layout_class']),
    ];
    $items['input_radio'] = [
        'type' => 'input_radio',
        'label' => 'Radio Choice',
        'description' => 'Exactly-one-of options.',
        'category' => 'general',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['advanced_options', 'layout_class']),
    ];

    return $items;
}

/**
 * Free-tier "advanced" field types — composite fields and decorative blocks
 * (address, name, ratings, terms / GDPR consent, HTML block, section break).
 *
 * @return array<string, array{type:string,label:string,description:string,category:string,pro_only:bool,supported_settings:list<string>}>
 */
function ff_field_catalog_advanced(): array
{
    $base = ff_field_catalog_base_settings();
    $items = [];

    $items['address'] = [
        'type' => 'address',
        'label' => 'Address',
        'description' => 'Composite address field (street, city, state, zip, country).',
        'category' => 'advanced',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['address_fields', 'countries_list']),
    ];
    $items['input_name'] = [
        'type' => 'input_name',
        'label' => 'Name',
        'description' => 'Composite name field (first / middle / last).',
        'category' => 'advanced',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['name_fields']),
    ];
    $items['ratings'] = [
        'type' => 'ratings',
        'label' => 'Ratings',
        'description' => 'Star rating field.',
        'category' => 'advanced',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['number_of_stars', 'default_value']),
    ];
    $items['terms_and_condition'] = [
        'type' => 'terms_and_condition',
        'label' => 'Terms & Conditions',
        'description' => 'Checkbox-style consent gate (required by default).',
        'category' => 'advanced',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['tnc_html']),
    ];
    $items['gdpr_agreement'] = [
        'type' => 'gdpr_agreement',
        'label' => 'GDPR Agreement',
        'description' => 'GDPR consent checkbox.',
        'category' => 'advanced',
        'pro_only' => false,
        'supported_settings' => array_merge($base, ['tnc_html']),
    ];
    $items['custom_html'] = [
        'type' => 'custom_html',
        'label' => 'Custom HTML',
        'description' => 'Free-form HTML block (display only, not collected).',
        'category' => 'advanced',
        'pro_only' => false,
        'supported_settings' => ['label', 'html_codes', 'container_class', 'class', 'visible', 'conditional_logics'],
    ];
    $items['section_break'] = [
        'type' => 'section_break',
        'label' => 'Section Break',
        'description' => 'Visual section divider with title + description.',
        'category' => 'advanced',
        'pro_only' => false,
        'supported_settings' => ['label', 'description', 'container_class', 'class', 'visible', 'conditional_logics'],
    ];

    return $items;
}

/**
 * Pro-tier "advanced" field types: file / image upload, repeater, multi-step,
 * range slider, ranking, NPS, phone, color, chained / dynamic / post selection,
 * rich text, shortcode. The payment family lives in
 * `ff_field_catalog_pro_payment` to keep each function under the Halstead
 * threshold.
 *
 * The catalog ALWAYS includes Pro entries so `get-field-type-schema` can
 * describe a Pro type even on a free install (so the agent learns what would
 * unlock with Pro); `get-field-types-schema` filters them out by `pro_only`
 * when Pro is absent, and `add-field` checks Pro presence before construction.
 *
 * @return array<string, array{type:string,label:string,description:string,category:string,pro_only:bool,supported_settings:list<string>}>
 */
function ff_field_catalog_pro_advanced(): array
{
    $base = ff_field_catalog_base_settings();
    $items = [];

    $items['input_file'] = [
        'type' => 'input_file',
        'label' => 'File Upload',
        'description' => 'Upload one or more files.',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['max_file_count', 'max_file_size', 'allowed_file_types']),
    ];
    $items['input_image'] = [
        'type' => 'input_image',
        'label' => 'Image Upload',
        'description' => 'Upload one or more images.',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['max_file_count', 'max_file_size', 'image_resize']),
    ];
    $items['input_repeat'] = [
        'type' => 'input_repeat',
        'label' => 'Repeater Field',
        'description' => 'Dynamically repeated row of nested fields.',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['columns', 'max_repeat']),
    ];
    $items['form_step'] = [
        'type' => 'form_step',
        'label' => 'Multi-Step',
        'description' => 'Form step marker (wizard / multi-page forms).',
        'category' => 'pro_container',
        'pro_only' => true,
        'supported_settings' => ['label', 'container_class', 'class', 'visible'],
    ];
    $items['range_slider'] = [
        'type' => 'range_slider',
        'label' => 'Range Slider',
        'description' => 'Numeric slider input.',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['min', 'max', 'step', 'default_value']),
    ];
    $items['ranking_field'] = [
        'type' => 'ranking_field',
        'label' => 'Ranking',
        'description' => 'Sortable preference ranking.',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['advanced_options', 'enable_sortable']),
    ];
    $items['net_promoter_score'] = [
        'type' => 'net_promoter_score',
        'label' => 'NPS Score',
        'description' => '0-10 Net Promoter Score scale.',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['lowest_rating_label', 'highest_rating_label']),
    ];
    $items['phone_field'] = [
        'type' => 'phone_field',
        'label' => 'Phone',
        'description' => 'International phone input with country code.',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['enable_country_list', 'default_country']),
    ];
    $items['color_picker'] = [
        'type' => 'color_picker',
        'label' => 'Color Picker',
        'description' => 'Hex / RGBA color selector.',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['default_value']),
    ];

    return $items;
}

/**
 * Pro-tier "dynamic" field types: dependent / data-source dropdowns, post
 * picker, rich text, shortcode. All Pro-only. Split out of the wider Pro
 * advanced bucket to keep each function under the Halstead volume threshold.
 *
 * @return array<string, array{type:string,label:string,description:string,category:string,pro_only:bool,supported_settings:list<string>}>
 */
function ff_field_catalog_pro_dynamic(): array
{
    $base = ff_field_catalog_base_settings();
    $items = [];

    $items['chained_select'] = [
        'type' => 'chained_select',
        'label' => 'Chained Select',
        'description' => 'Dependent dropdowns sourced from a CSV.',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['csv_source']),
    ];
    $items['dynamic_field'] = [
        'type' => 'dynamic_field',
        'label' => 'Dynamic Field',
        'description' => 'Conditionally populated dropdown (AJAX-driven).',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['source_url']),
    ];
    $items['post_selection_field'] = [
        'type' => 'post_selection_field',
        'label' => 'Post Selection',
        'description' => 'Pick one or more WordPress posts / pages.',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['post_type', 'post_status']),
    ];
    $items['rich_text_input'] = [
        'type' => 'rich_text_input',
        'label' => 'Rich Text',
        'description' => 'WYSIWYG editor (TinyMCE-style).',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['rows', 'toolbar']),
    ];
    $items['shortcode'] = [
        'type' => 'shortcode',
        'label' => 'Shortcode',
        'description' => 'Inject a WordPress shortcode in the form.',
        'category' => 'pro_advanced',
        'pro_only' => true,
        'supported_settings' => ['label', 'shortcode_value', 'container_class', 'class', 'visible'],
    ];

    return $items;
}

/**
 * Pro-tier payment field types: gateway selector, summary block, custom
 * payment item, item-quantity selector, subscription, coupon. All Pro-only.
 *
 * @return array<string, array{type:string,label:string,description:string,category:string,pro_only:bool,supported_settings:list<string>}>
 */
function ff_field_catalog_pro_payment(): array
{
    $base = ff_field_catalog_base_settings();
    $items = [];

    $items['payment_method'] = [
        'type' => 'payment_method',
        'label' => 'Payment Method',
        'description' => 'Choose between configured gateways (Stripe, PayPal, …).',
        'category' => 'pro_payment',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['payment_methods']),
    ];
    $items['payment_summary_component'] = [
        'type' => 'payment_summary_component',
        'label' => 'Payment Summary',
        'description' => 'Read-only summary of cart line items + total.',
        'category' => 'pro_payment',
        'pro_only' => true,
        'supported_settings' => ['label', 'container_class', 'class', 'visible'],
    ];
    $items['custom_payment_component'] = [
        'type' => 'custom_payment_component',
        'label' => 'Custom Payment',
        'description' => 'Fixed-price payment item.',
        'category' => 'pro_payment',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['price', 'currency']),
    ];
    $items['item_quantity_component'] = [
        'type' => 'item_quantity_component',
        'label' => 'Item Quantity',
        'description' => 'Quantity selector tied to a payment item.',
        'category' => 'pro_payment',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['min', 'max', 'step']),
    ];
    $items['subscription_payment_component'] = [
        'type' => 'subscription_payment_component',
        'label' => 'Subscription Payment',
        'description' => 'Recurring billing payment item.',
        'category' => 'pro_payment',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['pricing_options', 'billing_interval', 'trial_days']),
    ];
    $items['payment_coupon'] = [
        'type' => 'payment_coupon',
        'label' => 'Coupon',
        'description' => 'Discount-code field for payment forms.',
        'category' => 'pro_payment',
        'pro_only' => true,
        'supported_settings' => array_merge($base, ['coupon_apply_button_text']),
    ];

    return $items;
}

/**
 * Whether a field type appears in the catalog. Unknown types are rejected
 * up-front (404-style) so we don't write garbage into form_fields. Use
 * `ff_field_type_pro_only` to additionally branch on Pro presence.
 */
function ff_field_type_known(string $type): bool
{
    return array_key_exists($type, ff_field_catalog());
}

/**
 * Whether a field type is Pro-only. Used by add-field / edit-field to fail
 * fast with `fluentforms_pro_field_not_available` when Pro is absent.
 */
function ff_field_type_pro_only(string $type): bool
{
    $entry = ff_field_catalog()[$type] ?? null;
    return $entry !== null && $entry['pro_only'];
}

/**
 * Reduce a settings array to only the keys the catalog whitelists for the
 * given type. Returns the filtered copy AND the list of keys that were
 * dropped (so the ability can surface them as a `warnings.dropped_settings`
 * note instead of failing the whole call).
 *
 * @param array<array-key, mixed> $settings
 *
 * @return array{settings: array<string, mixed>, dropped: list<string>}
 */
function ff_filter_settings_whitelist(string $type, array $settings): array
{
    $catalog = ff_field_catalog();
    $entry = $catalog[$type] ?? null;
    if ($entry === null) {
        return ['settings' => [], 'dropped' => array_map('strval', array_keys($settings))];
    }
    $allowed = array_flip($entry['supported_settings']);
    $kept = [];
    $dropped = [];
    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        $string_key = (string) $key;
        if (!isset($allowed[$string_key])) {
            $dropped[] = $string_key;
            continue;
        }
        $kept[$string_key] = $value;
    }
    sort($dropped);
    return ['settings' => $kept, 'dropped' => $dropped];
}

/**
 * Compute the next available index for a new field. FF uses the `index` key
 * as a 0-based ordering hint within `form_fields.fields[]`; siblings sharing
 * an index would render in non-deterministic order, so we always assign
 * (max+1).
 *
 * @param list<array<array-key, mixed>> $fields
 */
function ff_next_field_index(array $fields): int
{
    $max = -1;
    foreach ($fields as $field) {
        if (!isset($field['index']) || !is_int($field['index'])) {
            continue;
        }
        if ($field['index'] > $max) {
            $max = $field['index'];
        }
    }
    return $max + 1;
}

/**
 * Derive a unique HTML `name` attribute for a new field. FF uses the `name`
 * as the submission-key — collisions would alias one field's response over
 * another, so the helper appends a numeric suffix until the candidate is
 * unique within the current field list.
 *
 * @param list<array<array-key, mixed>> $existing
 */
function ff_assign_field_name(string $type, array $existing): string
{
    // Stable, conventional prefix derived from the type (input_text → text_input).
    $stem = match ($type) {
        'input_text' => 'text_input',
        'input_email' => 'email',
        'input_number' => 'numeric_field',
        'input_url' => 'url_field',
        'input_password' => 'password_field',
        'input_hidden' => 'hidden_field',
        'input_date' => 'date_field',
        'input_checkbox' => 'checkbox',
        'input_radio' => 'radio',
        'textarea' => 'textarea',
        'select' => 'dropdown',
        'multi_select' => 'multi_select',
        'address' => 'address',
        'input_name' => 'name',
        'ratings' => 'ratings',
        'phone_field' => 'phone',
        'input_file' => 'file_upload',
        'input_image' => 'image_upload',
        'input_repeat' => 'repeater_field',
        'range_slider' => 'range_slider',
        'ranking_field' => 'ranking',
        'net_promoter_score' => 'nps',
        'color_picker' => 'color',
        'chained_select' => 'chained_select',
        'dynamic_field' => 'dynamic_field',
        'post_selection_field' => 'post_selection',
        'rich_text_input' => 'rich_text',
        'payment_method' => 'payment_method',
        'subscription_payment_component' => 'subscription',
        'custom_payment_component' => 'payment_item',
        'item_quantity_component' => 'item_quantity',
        'payment_coupon' => 'coupon',
        default => $type,
    };

    $used = [];
    foreach ($existing as $field) {
        $attrs = isset($field['attributes']) && is_array($field['attributes']) ? $field['attributes'] : [];
        if (isset($attrs['name']) && is_string($attrs['name'])) {
            $used[$attrs['name']] = true;
        }
    }

    if (!isset($used[$stem])) {
        return $stem;
    }
    $i = 1;
    while (isset($used["{$stem}_{$i}"])) {
        ++$i;
    }
    return "{$stem}_{$i}";
}

/**
 * Existing field names (attributes.name) across a built-field list.
 *
 * @param list<array<array-key, mixed>> $existing
 * @return array<string, true>
 */
function ff_used_field_names(array $existing): array
{
    $used = [];
    foreach ($existing as $field) {
        $attrs = is_array($field['attributes'] ?? null) ? $field['attributes'] : [];
        if (isset($attrs['name']) && is_string($attrs['name']) && $attrs['name'] !== '') {
            $used[$attrs['name']] = true;
        }
    }
    return $used;
}

/**
 * Resolve a field's submission-key `name`: sanitize a caller-supplied name
 * to a valid FF key and dedupe it against the existing fields. Two fields
 * sharing a name silently overwrite each other's value on submit, and an
 * invalid/reserved key (spaces, dots, `a[b]`, `id`/`status`) breaks FF — the
 * old code trusted the supplied name verbatim despite a comment claiming
 * otherwise. Empty/invalid input falls back to the auto-assigner.
 *
 * @param list<array<array-key, mixed>> $existing
 */
function ff_resolve_field_name(string $type, string $supplied, array $existing): string
{
    // Valid FF field key: lowercase letters/digits/underscore, not starting
    // with a digit. Reserved submission columns must not be used as a field
    // key or they collide with the row's own columns.
    $reserved = ['id', 'form_id', 'status', 'created_at', 'updated_at', 'user_id', 'serial_number', 'response'];
    $sanitized = strtolower($supplied);
    $sanitized = (string) preg_replace(pattern: '/[^a-z0-9_]+/', replacement: '_', subject: $sanitized);
    $sanitized = trim($sanitized, characters: '_');
    if ($sanitized !== '' && preg_match('/^[0-9]/', $sanitized) === 1) {
        $sanitized = 'f_' . $sanitized;
    }
    if ($sanitized === '' || in_array($sanitized, $reserved, strict: true)) {
        return ff_assign_field_name($type, $existing);
    }

    $used = ff_used_field_names($existing);
    if (!isset($used[$sanitized])) {
        return $sanitized;
    }
    $i = 1;
    while (isset($used["{$sanitized}_{$i}"])) {
        ++$i;
    }
    return "{$sanitized}_{$i}";
}

/**
 * Choice field types whose `advanced_options` must be a native list of
 * `{label, value, calc_value}` objects.
 *
 * @return list<string>
 */
function ff_choice_field_types(): array
{
    return ['select', 'multi_select', 'input_radio', 'input_checkbox'];
}

/**
 * Normalize a choice field's options to FF's native `advanced_options` list.
 * Accepts `options` as a friendly alias, and coerces the shapes agents
 * naturally pass — an assoc map `{value:label}` or a flat string list
 * `["A","B"]` — which FF's renderer FATALS on if stored verbatim. Leaves
 * non-choice fields untouched.
 *
 * @param array<array-key, mixed> $settings
 * @return array<array-key, mixed>
 */
function ff_normalize_choice_options(string $type, array $settings): array
{
    if (!in_array($type, ff_choice_field_types(), strict: true)) {
        return $settings;
    }
    // `options` alias → advanced_options when the native key is absent.
    if (!array_key_exists('advanced_options', $settings) && array_key_exists('options', $settings)) {
        $settings['advanced_options'] = $settings['options'];
    }
    unset($settings['options']);

    if (!array_key_exists('advanced_options', $settings)) {
        return $settings;
    }
    /** @var mixed $raw */
    $raw = $settings['advanced_options'];
    if (!is_array($raw)) {
        unset($settings['advanced_options']);
        return $settings;
    }

    $native = [];
    /** @var mixed $entry */
    foreach ($raw as $key => $entry) {
        $native[] = ff_option_to_native($key, $entry);
    }
    $settings['advanced_options'] = $native;
    return $settings;
}

/**
 * Convert one option entry to the native `{label, value, calc_value}` shape:
 * an option object (fill gaps), a flat-list entry with an integer key
 * (value == label), or an assoc-map entry with a string key (key = value).
 *
 * @param array-key $key
 * @param mixed $entry
 * @return array{label: string, value: string, calc_value: string}
 */
function ff_option_to_native(mixed $key, mixed $entry): array
{
    if (is_array($entry)) {
        $value = ff_str($entry['value'] ?? $entry['label'] ?? '');
        return [
            'label' => ff_str($entry['label'] ?? $value),
            'value' => $value,
            'calc_value' => ff_str($entry['calc_value'] ?? ''),
        ];
    }
    if (is_int($key)) {
        $value = ff_str($entry);
        return ['label' => $value, 'value' => $value, 'calc_value' => ''];
    }
    return ['label' => ff_str($entry), 'value' => $key, 'calc_value' => ''];
}

/**
 * Build the FF-native `{element, attributes, settings, editor_options}`
 * shape from the agent-facing `{type, label, required, settings, ...}`
 * input. Centralised here so add-field and create-form (with bundled
 * fields[]) emit identical structures.
 *
 * @param array<array-key, mixed> $input Caller-supplied payload. `type` is required; everything else falls back to a sane default when missing or of the wrong type.
 * @param list<array<array-key, mixed>> $existing_fields
 *
 * @return array<string, mixed>
 */
function ff_build_element(array $input, array $existing_fields): array
{
    $type = isset($input['type']) && is_string($input['type']) ? $input['type'] : '';
    $label = isset($input['label']) ? ff_str($input['label']) : '';
    $required = isset($input['required']) && $input['required'] === true;

    $catalog = ff_field_catalog()[$type] ?? null;
    $supplied_settings = isset($input['settings']) && is_array($input['settings']) ? $input['settings'] : [];

    // Validation rules — merge the `required` flag into the rules block, then
    // splice in any caller-supplied validation_rules so we don't overwrite
    // type-specific rules they may have set.
    /** @var array<string, mixed> $validation_rules */
    $validation_rules = isset($supplied_settings['validation_rules'])
    && is_array($supplied_settings['validation_rules'])
        ? $supplied_settings['validation_rules']
        : [];
    if ($required) {
        $validation_rules['required'] = ['value' => true, 'message' => 'This field is required.'];
    }
    $supplied_settings['validation_rules'] = $validation_rules;

    // Accept `options` as a friendly alias for `advanced_options` on choice
    // fields (agents naturally reach for it), then normalize whatever option
    // shape was passed to FF's native list — an assoc map or a flat string
    // list stored verbatim makes FF's renderer FATAL the whole front page.
    $supplied_settings = ff_normalize_choice_options($type, $supplied_settings);

    // Apply the catalog whitelist — UNKNOWN settings keys are dropped.
    $filtered = ff_filter_settings_whitelist($type, $supplied_settings);
    $settings = $filtered['settings'];
    // Always ensure label lives under settings.label even when caller omits it
    // (FF reads from there for every field except hidden/custom-html).
    $settings['label'] = $label;

    // Resolve the input name: sanitize a caller-supplied name to a valid FF
    // key and dedupe it against the existing fields, so two fields never
    // share a submission key (which silently overwrites one value on submit).
    $supplied_name = isset($input['name']) && is_string($input['name']) ? $input['name'] : '';
    $name = ff_resolve_field_name($type, $supplied_name, $existing_fields);

    // Seed from Fluent Forms' OWN native element skeleton for this type when
    // available. The skeleton carries the render/editor-critical bits our
    // hand-built shape kept getting wrong: the camelCase editor `template`
    // (the Vue canvas mounts a field's preview only when it matches a
    // registered component — a slug like `input_text` renders a blank row),
    // the HTML `attributes.*` FF's renderer actually reads (placeholder,
    // maxlength, value live there, NOT under settings.*), and the composite
    // `fields` children of name/address (a leaf-only `address` element makes
    // the whole page fatal with array_filter(null)).
    $skeleton = ff_native_element_skeleton($type);
    if ($skeleton !== null) {
        return ff_overlay_element(
            $skeleton,
            [
                'type' => $type,
                'name' => $name,
                'label' => $label,
                'settings' => $settings,
            ],
            $existing_fields,
        );
    }

    // Fallback (no native skeleton — e.g. FF too old, or a type FF does not
    // register): the legacy hand-built shape.
    $attributes = ff_default_attributes($type);
    $attributes['name'] = $name;

    return [
        'index' => ff_next_field_index($existing_fields),
        'element' => $type,
        'attributes' => $attributes,
        'settings' => $settings,
        'editor_options' => [
            'title' => $catalog['label'] ?? $type,
            'icon_class' => '',
            'template' => $type,
        ],
        'uniqElKey' => $type . '_' . uniqid(prefix: '', more_entropy: false),
    ];
}

/**
 * Our field-type slug → Fluent Forms native element key, for the few that
 * differ. Identity for everything else.
 */
function ff_native_element_key(string $type): string
{
    return match ($type) {
        'phone_field' => 'phone',
        'input_repeat' => 'repeater_field',
        // FF has no `multi_select` element — it is a `select` with the
        // `multiple` attribute. Without this mapping the skeleton lookup
        // missed and the legacy fallback emitted a template FF never
        // registers, so the field rendered nothing (invisible field).
        'multi_select' => 'select',
        default => $type,
    };
}

/**
 * A deep copy of Fluent Forms' native default skeleton for a field type
 * (attributes, settings, editor_options.template, composite `fields`), or
 * null when FF does not register the type / the components service is
 * unavailable. Read from FF's own `components` binding so the shapes stay
 * correct across FF versions instead of being hand-maintained here.
 *
 * @return array<string, mixed>|null
 */
function ff_native_element_skeleton(string $type): ?array
{
    /** @var array<string, array<string, mixed>>|null $by_element */
    static $by_element = null;

    if ($by_element === null) {
        $by_element = ff_load_native_element_index();
    }

    $native = $by_element[ff_native_element_key($type)] ?? null;
    if (!is_array($native) || $native === []) {
        return null;
    }
    // Deep copy so overlays never mutate the cached skeleton.
    /** @var mixed $copy */
    $copy = json_decode((string) wp_json_encode($native), associative: true);
    if (!is_array($copy)) {
        return null;
    }
    $out = [];
    /** @var mixed $value */
    foreach ($copy as $key => $value) {
        $out[(string) $key] = $value;
    }
    return $out;
}

/**
 * Build the [element_key => native skeleton] index from Fluent Forms' own
 * `components` binding. Empty array when FF or the binding is unavailable.
 *
 * @return array<string, array<string, mixed>>
 */
function ff_load_native_element_index(): array
{
    if (!function_exists('wpFluentForm')) {
        return [];
    }
    try {
        /** @var mixed $app */
        $app = wpFluentForm();
        if (!is_object($app) || !method_exists($app, 'make')) {
            return [];
        }
        /** @var mixed $components */
        $components = $app->make('components');
        if (!is_object($components) || !method_exists($components, 'toArray')) {
            return [];
        }
        /** @var mixed $items */
        $items = $components->toArray();

        // @phpstan-ignore-next-line — toArray() is untyped in FF
    } catch (\Throwable) {
        return [];
    }
    if (!is_array($items)) {
        return [];
    }

    $index = [];
    foreach (['general', 'advanced'] as $group) {
        $group_items = is_array($items[$group] ?? null) ? $items[$group] : [];
        /** @var mixed $component */
        foreach ($group_items as $key => $component) {
            if (!is_array($component)) {
                continue;
            }
            $element = is_string($component['element'] ?? null) ? $component['element'] : (string) $key;
            /** @var array<string, mixed> $normalized */
            $normalized = $component;
            $index[$element] = $normalized;
        }
    }
    return $index;
}

/**
 * HTML-attribute settings that FluentForms reads from `attributes.*`, not
 * `settings.*`. An agent naturally passes these under settings; routed here
 * so they actually render.
 *
 * @var list<string>
 */
const FF_ATTRIBUTE_LEVEL_SETTINGS = ['placeholder', 'maxlength', 'value', 'rows', 'cols'];

/**
 * Overlay the caller's resolved values onto a native FF element skeleton.
 * Preserves the skeleton's template, composite `fields`, and full native
 * attribute set; routes attribute-level settings (placeholder/maxlength/…,
 * plus default_value → attributes.value) into `attributes.*` so they render.
 *
 * @param array<string, mixed> $skeleton
 * @param array{type: string, name: string, label: string, settings: array<string, mixed>} $resolved
 * @param list<array<array-key, mixed>> $existing_fields
 * @return array<string, mixed>
 */
/**
 * Route a field's settings to their FF-native homes: `default_value` and the
 * attribute-level settings (placeholder/maxlength/value/rows/cols) into
 * `attributes.*` where FF's renderer reads them, `input_number` min/max/step
 * into settings.validation_rules/number_step, and `multi_select`'s `multiple`
 * attribute. Shared by create/add (ff_overlay_element) and edit-field
 * (ff_patch_field), so an edited placeholder/number/option isn't left inert.
 *
 * @param array<string, mixed> $attributes
 * @param array<string, mixed> $settings
 * @return array{0: array<string, mixed>, 1: array<string, mixed>}
 */
function ff_route_field_settings(string $type, array $attributes, array $settings): array
{
    // Values routed into attributes.* become HTML attribute values, which FF's
    // renderer runs through htmlspecialchars(). A non-scalar (array/object)
    // there throws a TypeError that kills the WHOLE form render, so drop it
    // instead of routing — only scalars/null may become an attribute.
    if (array_key_exists('default_value', $settings)) {
        if (ff_routable_attr_value($settings['default_value'])) {
            $attributes['value'] = $settings['default_value'];
        }
        unset($settings['default_value']);
    }
    foreach (FF_ATTRIBUTE_LEVEL_SETTINGS as $key) {
        if (!array_key_exists($key, $settings)) {
            continue;
        }
        if (ff_routable_attr_value($settings[$key])) {
            $attributes[$key] = $settings[$key];
        }
        unset($settings[$key]);
    }
    if ($type === 'input_number') {
        $settings = ff_route_number_constraints($settings);
    }
    if ($type === 'multi_select') {
        $attributes['multiple'] = true;
    }
    $string_settings = [];
    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        $string_settings[(string) $key] = $value;
    }
    return [$attributes, $string_settings];
}

/**
 * @param array<string, mixed> $skeleton
 * @param array{type: string, name: string, label: string, settings: array<string, mixed>} $resolved
 * @param list<array<array-key, mixed>> $existing_fields
 * @return array<string, mixed>
 */
function ff_overlay_element(array $skeleton, array $resolved, array $existing_fields): array
{
    $settings = $resolved['settings'];
    /** @var array<string, mixed> $attributes */
    $attributes = is_array($skeleton['attributes'] ?? null) ? $skeleton['attributes'] : [];
    $attributes['name'] = $resolved['name'];

    [$attributes, $settings] = ff_route_field_settings($resolved['type'], $attributes, $settings);

    /** @var array<string, mixed> $native_settings */
    $native_settings = is_array($skeleton['settings'] ?? null) ? $skeleton['settings'] : [];
    // Caller settings win, native fills the gaps (validation_rules already
    // carries the required flag from the caller).
    $merged_settings = array_merge($native_settings, $settings);
    $label = $resolved['label'];
    $merged_settings['label'] = $label !== '' ? $label : (string) ($native_settings['label'] ?? '');

    $skeleton['index'] = ff_next_field_index($existing_fields);
    $skeleton['attributes'] = $attributes;
    $skeleton['settings'] = $merged_settings;
    $skeleton['uniqElKey'] = $resolved['type'] . '_' . uniqid(prefix: '', more_entropy: false);

    return $skeleton;
}

/**
 * Move agent-supplied `min`/`max`/`step` settings on an input_number into the
 * native homes FF's number renderer reads: min/max into
 * `settings.validation_rules.min|max.value` (with a message), step into
 * `settings.number_step`. Leaves other settings untouched.
 *
 * @param array<array-key, mixed> $settings
 * @return array<array-key, mixed>
 */
function ff_route_number_constraints(array $settings): array
{
    /** @var array<string, mixed> $rules */
    $rules = is_array($settings['validation_rules'] ?? null) ? $settings['validation_rules'] : [];
    foreach (['min', 'max'] as $bound) {
        if (!array_key_exists($bound, $settings)) {
            continue;
        }
        // min/max become the number field's HTML min/max attributes; a
        // non-scalar there fataled the whole form render, so drop it.
        if (!ff_routable_attr_value($settings[$bound])) {
            unset($settings[$bound]);
            continue;
        }
        /** @var array<string, mixed> $existing */
        $existing = is_array($rules[$bound] ?? null) ? $rules[$bound] : [];
        $rules[$bound] = array_merge(
            ['value' => $settings[$bound], 'message' => sprintf('The %s value is invalid.', $bound)],
            $existing,
            ['value' => $settings[$bound]],
        );
        unset($settings[$bound]);
    }
    if ($rules !== []) {
        $settings['validation_rules'] = $rules;
    }
    if (array_key_exists('step', $settings)) {
        if (ff_routable_attr_value($settings['step'])) {
            $settings['number_step'] = $settings['step'];
        }
        unset($settings['step']);
    }
    return $settings;
}

/**
 * True when a value may safely become an HTML attribute value. FF's renderer
 * runs attribute values through htmlspecialchars(), which throws a TypeError
 * on an array/object and kills the entire form render — so only scalars and
 * null are routable into attributes.* / min / max / step.
 *
 * @param mixed $value
 */
function ff_routable_attr_value(mixed $value): bool
{
    return $value === null || is_scalar($value);
}

/**
 * Default `attributes.*` for a field type — LEGACY fallback used only when
 * no native FF skeleton is available (see ff_native_element_skeleton).
 *
 * @return array<string, mixed>
 */
function ff_default_attributes(string $type): array
{
    return match ($type) {
        'input_text' => ['type' => 'text', 'value' => ''],
        'input_email' => ['type' => 'email', 'value' => ''],
        'input_number' => ['type' => 'number', 'value' => ''],
        'input_password' => ['type' => 'password', 'value' => ''],
        'input_url' => ['type' => 'url', 'value' => ''],
        'input_hidden' => ['type' => 'hidden', 'value' => ''],
        'input_date' => ['type' => 'date', 'value' => ''],
        'phone_field' => ['type' => 'tel', 'value' => ''],
        'color_picker' => ['type' => 'text', 'value' => ''],
        default => [],
    };
}

/**
 * Encode a `form_fields` array back to the JSON shape FF expects. Uses
 * UNESCAPED_SLASHES + UNESCAPED_UNICODE because FF stores literal slashes
 * in URL settings and the admin UI displays the resulting string back to
 * users (escaped slashes show as "\/" which is jarring).
 *
 * Returns null when encoding fails (e.g. a caller-supplied `settings` object
 * nested past wp_json_encode's 512-level depth cap makes it return false).
 * Callers MUST treat null as a hard error — the previous behaviour returned an
 * empty-form fallback that, when persisted, silently WIPED every field.
 *
 * @param array{fields: list<array<array-key, mixed>>, submitButton: array<array-key, mixed>, ...<array-key, mixed>} $payload
 */
function ff_encode_fields(array $payload): ?string
{
    $json = wp_json_encode($payload, flags: JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    return is_string($json) ? $json : null;
}

/**
 * Re-emit the native FF lifecycle hook for a write-path edit so modules that
 * listen (ACL allow-list, default styles, per-form integrations, audit
 * history) stay in sync — our write paths bypass FF's own controllers, which
 * are where FF fires these.
 *
 * Only hooks that are correct to fire post-write with a bare form id are
 * re-emitted. FF has no generic post-update hook (`before_updating_form` is a
 * pre-save hook that hands listeners a live model + payload), so the 'updated'
 * event emits nothing and relies on ff_clear_form_cache() for parity.
 *
 * These fire AFTER the DB write has already committed, so a third-party
 * listener that throws must not turn a successful write into a reported
 * failure (which would leave the response and the DB disagreeing). The
 * do_action is wrapped so a listener exception is swallowed here.
 *
 * @param 'created'|'updated'|'deleted'|'duplicated' $event
 */
function ff_after_form_save(int $form_id, string $event): void
{
    if ($form_id <= 0) {
        return;
    }
    try {
        switch ($event) {
            case 'created':
                // Listeners: maybeAddUserAllowedFormIds($id) registers the form
                // in the current user's ACL allow-list; applyDefaultStyles($id,
                // $data) applies the site default style template. Both are safe
                // with an empty data array (the styler reads only option + id).
                do_action('fluentform/inserted_new_form', $form_id, []);
                break;
            case 'duplicated':
                do_action('fluentform/form_duplicated', $form_id);
                break;
            case 'deleted':
                // Fired after the row is already gone, so only the post-delete
                // cleanup hook applies — before_form_delete expects a live row.
                do_action('fluentform/after_form_delete', $form_id);
                break;
        }
    } catch (\Throwable) {
        // A misbehaving third-party listener must not fail the (already
        // committed) write. The lifecycle notification is best-effort.
        return;
    }
}

/**
 * Reset FF's per-request parse caches. FF caches parsed form fields and
 * shortcode values in static registries and resets them after its own saves;
 * our direct-DB writes bypass that, so a later read/render in the same request
 * would serve stale data. `FormService::clearFormCache` (called here before)
 * does not exist — this was a silent no-op — so we reset the same registries
 * FF itself resets (FormFieldsParser + ShortCodeParser).
 */
function ff_clear_form_cache(int $form_id): void
{
    if ($form_id <= 0) {
        return;
    }
    $fields_parser = '\\FluentForm\\App\\Modules\\Form\\FormFieldsParser';
    if (class_exists($fields_parser) && method_exists($fields_parser, 'resetData')) {
        $fields_parser::resetData();
    }
    $shortcode_parser = '\\FluentForm\\App\\Services\\FormBuilder\\ShortCodeParser';
    if (class_exists($shortcode_parser) && method_exists($shortcode_parser, 'resetData')) {
        $shortcode_parser::resetData();
    }
}

/**
 * Validate + translate the caller-supplied fields[] to the FF native shape.
 * Returns a tuple of (built fields, dropped-settings map keyed by field
 * index, list of WP_Error rejections — non-empty means hard fail).
 *
 * Shared by create-form (initial fields[] payload) and edit-form (REPLACE-
 * semantics fields[] patch). Previously lived in create-form.php and was
 * called cross-file from edit-form / manage-fields — the audit (round 1)
 * flagged the hidden dependency, so it moved here as the canonical home.
 *
 * @param list<mixed> $raw
 *
 * @return array{0: list<array<string, mixed>>, 1: array<int, list<string>>, 2: list<WP_Error>, 3: array<int, list<string>>}
 */
function ff_normalize_input_fields(array $raw): array
{
    $built = [];
    $dropped = [];
    $rejected = [];
    $dropped_keys = [];
    $running = [];
    $idx = 0;
    /** @var mixed $field_input */
    foreach ($raw as $field_input) {
        if (!is_array($field_input)) {
            $rejected[] = ff_invalid(sprintf('fields[%d] must be an object.', $idx));
            return [$built, $dropped, $rejected, $dropped_keys];
        }
        $check = ff_check_field_input($field_input);
        if ($check instanceof WP_Error) {
            $rejected[] = $check;
            return [$built, $dropped, $rejected, $dropped_keys];
        }
        $type = isset($field_input['type']) && is_string($field_input['type']) ? $field_input['type'] : '';
        $supplied_settings = isset($field_input['settings']) && is_array($field_input['settings'])
            ? $field_input['settings']
            : [];
        $filtered = ff_filter_settings_whitelist($type, $supplied_settings);
        if ($filtered['dropped'] !== []) {
            $dropped[$idx] = $filtered['dropped'];
        }
        // Top-level keys ff_build_element ignores (it reads only
        // type/label/required/name/settings) — a common mistake is passing
        // `placeholder`/`attributes` at the field's top level instead of
        // under `settings`, which was silently discarded. Surface them.
        $unknown_top = array_values(array_diff(array_map('strval', array_keys($field_input)), [
            'type',
            'label',
            'required',
            'name',
            'settings',
        ]));
        if ($unknown_top !== []) {
            $dropped_keys[$idx] = $unknown_top;
        }
        $built[] = ff_build_element($field_input, $running);
        $running = $built;
        ++$idx;
    }
    return [$built, $dropped, $rejected, $dropped_keys];
}

/**
 * Validate the per-field input shape — type required + known + Pro-allowed.
 * Returns null on success, a WP_Error otherwise. Called once per field so
 * the rejection points at the first invalid entry rather than the whole
 * batch. Shared by create-form, edit-form, and add-field (was a hidden
 * cross-file dependency until audit round 1 surfaced the issue).
 *
 * @param array<array-key, mixed> $field_input
 */
function ff_check_field_input(array $field_input): ?WP_Error
{
    if (!isset($field_input['type']) || !is_string($field_input['type']) || $field_input['type'] === '') {
        return ff_invalid('Each field requires a non-empty `type` string.');
    }
    $type = $field_input['type'];
    if (!ff_field_type_known($type)) {
        return ff_invalid(sprintf(
            "Unknown field type '%s'. Call novamira/fluentforms-get-field-types-schema to discover supported types.",
            $type,
        ));
    }
    if (ff_field_type_pro_only($type) && !ff_detect_setup()['pro']) {
        return ff_pro_field_not_available($type);
    }
    return null;
}

/**
 * FF's default submit button structure. Embedded into newly-created forms
 * so the rendered HTML actually has a submit control. Used by both
 * create-form (always) and edit-form (only when the existing form somehow
 * lost its submitButton block). Moved here from create-form.php after
 * audit round 1 flagged the cross-file dependency.
 *
 * @return array<string, mixed>
 */
// ============================================================================
// Sprint 5 — quiz + integration-feed helpers
// ============================================================================

/**
 * Read `saved_quiz_fields` from the decoded _quiz_settings form_meta and
 * project to the agent-facing shape `{field_name, label, type, answer,
 * points, weight}`. Returns [$questions, $sum_of_points] so callers can
 * surface max_points in their envelope.
 *
 * Moved out of list-quiz-questions.php after round-5 audit flagged the
 * hidden cross-file dependency from get-quiz-report.
 *
 * @param array<array-key, mixed> $quiz_settings
 *
 * @return array{0:list<array<string, mixed>>, 1:int}
 */
function ff_extract_quiz_questions(array $quiz_settings): array
{
    if (!isset($quiz_settings['saved_quiz_fields']) || !is_array($quiz_settings['saved_quiz_fields'])) {
        return [[], 0];
    }
    $questions = [];
    $max_points = 0;
    /** @var mixed $entry */
    foreach ($quiz_settings['saved_quiz_fields'] as $name => $entry) {
        if (!is_array($entry)) {
            continue;
        }
        $points = ff_quiz_question_points($entry);
        $max_points += $points;
        $questions[] = [
            'field_name' => (string) $name,
            'label' => isset($entry['label']) && is_string($entry['label']) ? $entry['label'] : (string) $name,
            'type' => isset($entry['element']) && is_string($entry['element']) ? $entry['element'] : '',
            'answer' => $entry['answer'] ?? null,
            'points' => $points,
            'weight' => isset($entry['weight']) && is_numeric($entry['weight']) ? (float) $entry['weight'] : 1.0,
        ];
    }
    return [$questions, $max_points];
}

/**
 * Resolve the per-question points value. FF stores it under either
 * `points` (numeric) or `weight` (numeric multiplier); default 1 when
 * neither is set.
 *
 * @param array<array-key, mixed> $entry
 */
function ff_quiz_question_points(array $entry): int
{
    if (isset($entry['points']) && is_numeric($entry['points'])) {
        return (int) $entry['points'];
    }
    if (isset($entry['weight']) && is_numeric($entry['weight'])) {
        return (int) round(num: (float) $entry['weight']);
    }
    return 1;
}

/**
 * Redact known sensitive credentials from an integration feed body
 * before returning it to the agent. Round-5 audit flagged that the
 * `raw` envelope on list-integrations could include API keys / OAuth
 * tokens / webhook URLs. Replaces those fields with the literal string
 * `***REDACTED***` while preserving the rest of the structure so the
 * agent can still see destination ids, list_id, channel, etc.
 *
 * @param mixed $decoded
 *
 * @return mixed
 */
function ff_redact_integration_secrets(mixed $decoded): mixed
{
    if (!is_array($decoded)) {
        return $decoded;
    }
    $secret_keys = [
        'api_key',
        'apikey',
        'api_secret',
        'secret',
        'secret_key',
        'private_key',
        'access_token',
        'refresh_token',
        'oauth_token',
        'bearer_token',
        'auth_token',
        'webhook_url',
        'webhook',
        'web_hook',
        'callback_url',
        'merchant_id',
        'merchant_key',
        'client_secret',
        'password',
        'pass',
        'pwd',
        'token',
    ];
    $out = [];
    /** @var mixed $value */
    foreach ($decoded as $key => $value) {
        $key_str = strtolower((string) $key);
        if (in_array($key_str, $secret_keys, strict: true)) {
            $out[$key] = '***REDACTED***';
            continue;
        }
        $out[$key] = is_array($value) ? ff_redact_integration_secrets($value) : $value;
    }
    return $out;
}

function ff_default_submit_button(): array
{
    return [
        'uniqElKey' => 'el_' . uniqid(prefix: '', more_entropy: false),
        'element' => 'button',
        'attributes' => ['type' => 'submit', 'class' => ''],
        'settings' => [
            'container_class' => '',
            'help_message' => '',
            'align' => 'left',
            'button_style' => 'default',
            'button_size' => 'md',
            'color' => '#ffffff',
            'background_color' => '#409EFF',
            'button_ui' => ['text' => 'Submit', 'type' => 'default', 'img_url' => ''],
            'normal_styles' => [],
            'hover_styles' => [],
        ],
        'editor_options' => [
            'title' => 'Submit Button',
            'icon_class' => 'ff-edit-submit',
            'template' => 'submitButton',
        ],
    ];
}
