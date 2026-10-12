---
name: fluentforms-integration
description: Build and manage Fluent Forms (free + Pro) — forms CRUD, field management, submissions, settings, notifications, confirmations, and Pro-only payment transactions, subscriptions, draft submissions, and quiz reports. Activate when the user mentions Fluent Forms, FluentForms, "FF form", or asks to create/edit/list forms or read entries on a site using Fluent Forms. For cross-plugin migrations (CF7 / WPForms / Gravity Forms / Ninja Forms / Formidable ↔ Fluent Forms), the `fluentforms-get-form-canonical` reader plugs the Fluent Forms shape into the `form-model-schema` canonical vocabulary used by the `form-model-migration` workflow.
---

# Fluent Forms integration

## When to use

Trigger this skill when the user mentions:
- "Fluent Forms" / "FluentForms" / "WP Fluent Form" / "FF form"
- Asks to create, list, edit, delete, or duplicate a form on a site that has Fluent Forms active
- Asks to read or modify form submissions / entries on a Fluent Forms site
- Asks about Fluent Forms payments, subscriptions, draft submissions, quiz reports, or marketing integrations (Mailchimp, ActiveCampaign, HubSpot, …)

Do NOT activate for: Gravity Forms, Contact Form 7, WPForms, Formidable, Forminator — those have their own integrations.

## Domain model

- **Form** — the top-level entity. Stored in `wp_fluentform_forms`. Key columns: `id`, `title`, `status` (`published` / `unpublished` — an unpublished form renders as empty output), `type` (`form` / `post`), `form_fields` (JSON: `{fields:[…], submitButton:{…}}`), `has_payment`. Conversational forms are detected via the `has_conversion` flag (a Pro column; always false on free), not a `type` value.
- **Field** — element inside `form_fields.fields[]`. Agent-facing shape is `{type, label, required, name?, settings?}`. Native FF shape (what gets stored) is `{element, attributes, settings, editor_options}`. The integration translates the friendly shape via `ff_build_element`.
- **Submission** — row in `wp_fluentform_submissions`. `response` is a JSON map of `field_name → value`. Status is `unread` / `read` / `spam` / `trashed`. Payment fields (`payment_status`, `payment_total`, `currency`, …) populated only when Pro + the form has a payment field.
- **FormMeta** — sidecar rows in `wp_fluentform_form_meta`. Notifications, confirmations, settings, integration feeds, conditional logic all live here under distinct `meta_key` values (`formSettings`, `notifications`, `mailchimp_feeds`, …).
- **Transaction / Subscription** *(Pro)* — payment records linked to a submission via `submission_id`.

## Discover first

Always start with discovery — branch on the result before invoking write paths.

1. **`novamira/fluentforms-check-setup`** — returns `plugin_active`, `version`, `min_satisfied`, `pro_active`, `pro_version`, `pro_min_satisfied`, `license_active`, `capabilities.*` (forms_manager, entries_viewer, manage_entries, settings_manager, view_payments, manage_payments), `supports.*` (payments, conversational_forms, quiz, draft_submissions, file_upload, integrations, pdf_generator), `integrations_active.feeds.*` (16 named feeds). Use `supports.*` to know whether a Pro path is available BEFORE calling it.
2. **`novamira/fluentforms-get-field-types-schema`** — the catalog of every field type the integration can build. Cache the result for the session. Entries with `pro_only=true` are filtered out when Pro is absent — the agent never sees a type it cannot use.
3. **`novamira/fluentforms-list-forms`** — paginated index of forms (compact rows, no `fields[]` embed).
4. **`novamira/fluentforms-get-form`** — single form with opt-in `include[]` (`fields`, `settings`, `notifications`, `confirmation`, `meta_keys`).

## Workflows

### R1 — Build a contact form from a prompt

```
novamira/fluentforms-check-setup
  → confirm plugin_active + min_satisfied + capabilities.forms_manager
novamira/fluentforms-get-field-types-schema
  → cache for session
novamira/fluentforms-create-form {
  "title": "Contact us",
  "fields": [
    {"type":"input_name","label":"Your name","required":true},
    {"type":"input_email","label":"Email","required":true},
    {"type":"textarea","label":"Message","required":true,
     "settings":{"placeholder":"How can we help?","rows":5}}
  ]
}
```

When the agent doesn't know the settings whitelist for a type, call `novamira/fluentforms-get-field-type-schema {type:"…"}` first — the response includes `supported_settings[]` (the keys add/edit-field will accept) and a copy-pasteable `example_input`.

### R2 — Add a field to an existing form (incremental)

DO NOT use `edit-form` for this — its `fields[]` parameter is REPLACE-semantics and would wipe the rest of the form. Use the manage-fields abilities:

```
novamira/fluentforms-add-field {
  "form_id": 12,
  "field": {"type":"phone_field","label":"Phone","required":false}
}
novamira/fluentforms-edit-field {
  "form_id": 12,
  "name": "phone",
  "settings": {"default_country":"IT"}
}
novamira/fluentforms-delete-field {
  "form_id": 12,
  "name": "phone"
}
```

The `name` is the FF submission-key (under `attributes.name`) — get it from `get-form?include=[fields]`.

### R3 — Duplicate a form for A/B testing

```
novamira/fluentforms-duplicate-form {
  "form_id": 12,
  "title": "Contact us (variant B)",
  "include_meta": true
}
```

`include_meta=true` (default) carries notifications, confirmation, integrations feeds, and conditional logic into the clone. Submissions are NEVER duplicated.

### R4 — Read submissions for a specific form *(Sprint 2)*

```
novamira/fluentforms-list-submissions { "form_id": 12, "status": "unread" }
novamira/fluentforms-get-submission { "submission_id": 4521 }
novamira/fluentforms-mark-submission { "submission_id": 4521, "status": "read" }
```

### R5 — Read payment transactions for a form *(Pro, Sprint 4)*

```
novamira/fluentforms-check-setup  → confirm supports.payments
novamira/fluentforms-list-transactions { "form_id": 12, "status": "paid" }
novamira/fluentforms-get-transaction { "transaction_id": 88 }
```

### R6 — Cancel a recurring subscription *(Pro, Sprint 4)*

```
novamira/fluentforms-list-subscriptions { "status": "active" }
novamira/fluentforms-cancel-subscription { "subscription_id": 7 }
```

Always confirm with the user before cancelling — there is no undo. The
ability dispatches to the per-vendor PaymentManager
(`Stripe\PaymentManager`, `PayPal\PayPalPaymentManager`, …) via
Reflection; vendors without a dedicated PaymentManager fall back to the
generic `\FluentFormPro\Payments\Classes\PaymentManagement`. Already-
cancelled subscriptions short-circuit to a no-op success
(idempotent).

### R7 — Clean up abandoned save-and-resume drafts *(Pro, Sprint 4)*

```
novamira/fluentforms-list-draft-submissions { "form_id": 12, "used": false }
novamira/fluentforms-delete-draft-submission { "draft_id": 91 }
```

`used: false` filters to drafts the user never came back to finish.
`delete-draft-submission` is permanent — no trash bin. There is no
bulk-delete ability; loop the singular delete or, for very large
sweeps, use the `novamira/execute-php` snippet below.

### R8 — Inventory marketing-integration feeds wired to a form *(Pro, Sprint 5)*

```
novamira/fluentforms-list-integrations { "form_id": 12 }
```

Returns `{form_id, integrations: [{feed_id, integration, name, enabled, raw}]}`
covering the 28-integration vocabulary (mailchimp, slack, hubspot,
zapier, …). Sensitive fields inside `raw` (`api_key`, `access_token`,
`webhook_url`, `merchant_id`, `password`, `client_secret`, `token`,
recursively at any depth) are replaced with `***REDACTED***` so the
agent payload + any logs can never leak vendor credentials.

### R9 — Quiz answer key + analytics *(Pro, Sprint 5)*

```
novamira/fluentforms-list-quiz-questions { "form_id": 12 }
novamira/fluentforms-get-quiz-report { "form_id": 12 }
novamira/fluentforms-get-quiz-report { "form_id": 12, "status_filter": "read" }
```

`list-quiz-questions` reads `_quiz_settings` form_meta and returns
`{is_quiz, questions: [{field_name, label, type, answer, points,
weight}], score_type, max_points}`. Non-quiz forms return
`is_quiz=false` + empty questions.

`get-quiz-report` aggregates correct/wrong counts across every
submission — capped at the **5000 most recent non-trashed submissions**
to keep the agent turn within memory. On larger datasets, narrow with
`status_filter` or split the form into sub-quizzes. The string compare
is case-insensitive; arrays are compared as sorted sets (multi-select
friendly).

## Cross-plugin migration

To migrate Fluent Forms → another supported plugin (CF7, WPForms, Gravity Forms, Ninja Forms, Formidable) or vice-versa, use the `form-model-migration` skill workflow. The Fluent-side plug-ins are:

- **Phase 1 (check setup)**: `novamira/fluentforms-check-setup`
- **Phase 2 (read source as canonical)**: `novamira/fluentforms-get-form-canonical`

`fluentforms-get-form-canonical` returns the form mapped to the plugin-agnostic `form-model-schema` vocabulary (17 canonical types) so an agent does not need to learn the Fluent-native shape (~40 elements, JSON-encoded `form_fields`, multi-row `wp_fluentform_form_meta` notifications) — the canonical document carries `fields[]`, `mail_config{}`, `notifications[]`, `conditional_rules[]`, `meta{}` (source_plugin/version + lossless formSettings echo), plus `warnings[]` for everything that does not fit (multi-page `form_step`, anti-spam `recaptcha` / `hcaptcha` / `turnstile`, Pro payment elements, multi-column container layout, custom redirects). Smart-tags inside subject / message / sendTo are rewritten from `{inputs.<name>}` to `[field-name]` to match CF7 / WPForms / Gravity Forms readers.

Fluent-specific quirks the reader smooths over for you:
- Storage split — `form_fields` lives in `wp_fluentform_forms`, notifications in `wp_fluentform_form_meta` (one row per notification, `meta_key='notifications'`), single confirmation under `formSettings.confirmation`.
- Composite `input_name` and `address` collapse to canonical `group` with an informational warning (children re-creation is the target plugin's job).
- Multi-column `container` layout flattens to a single canonical fields[] order.
- `tabular_grid` (matrix question) maps to canonical `checkbox` with multi cardinality and a structural warning.

## Simple operations via `novamira/execute-php`

For one-off reads/writes that don't warrant a dedicated ability, use the parent
`novamira/execute-php`. The integrations skills (seopress, woocommerce, …) follow
the same pattern.

### Count submissions per form (single query)

```php
return wpFluent()->table('fluentform_submissions')
    ->select('form_id')
    ->selectRaw('COUNT(*) as total')
    ->groupBy('form_id')
    ->get();
```

### Find a form by partial title

```php
return \FluentForm\App\Models\Form::where('title', 'LIKE', '%Contact%')->get();
```

### Read an arbitrary form_meta value

```php
$row = wpFluent()->table('fluentform_form_meta')
    ->where('form_id', 5)
    ->where('meta_key', 'formSettings')
    ->first();
return $row ? json_decode($row->value, true) : null;
```

### Bulk mark spam submissions as trashed

```php
return wpFluent()->table('fluentform_submissions')
    ->where('status', 'spam')
    ->update(['status' => 'trashed']);
```

### Bulk delete abandoned drafts older than N days *(Pro)*

```php
$cutoff = (new \DateTimeImmutable('-30 days'))->format('Y-m-d H:i:s');
return wpFluent()->table('fluentform_draft_submissions')
    ->where('used', 0)
    ->where('created_at', '<', $cutoff)
    ->delete();
```

### Total revenue per currency over a date range *(Pro)*

```php
return wpFluent()->table('fluentform_transactions')
    ->where('status', 'paid')
    ->where('created_at', '>=', '2026-01-01 00:00:00')
    ->select('currency')
    ->selectRaw('SUM(payment_total) as cents')
    ->groupBy('currency')
    ->get();
```

## Gotchas

- **`fields[]` on `edit-form` is REPLACE, not patch.** Passing it overwrites the whole array. For partial mutations use `add-field` / `edit-field` / `delete-field`.
- **The field `name` is the submission key.** Renaming a field orphans every prior submission's response under the old name. `edit-field` deliberately doesn't expose a rename path; to rename, delete + add.
- **`formSettings` is one big JSON blob.** Read it with `get-form?include=[settings]`; write with `edit-form-settings` (Sprint 3) — never POST the whole blob unless you mean a full replace.
- **Confirmation is a single block per form.** Stored under `formSettings.confirmation`. Don't expect a `notifications`-style array.
- **Conversational forms** use a different fields[] shape and are not built through these abilities — use the FF admin UI. There is no `type='conversational'`; the Conversational add-on marks a form via the `has_conversion` (Pro) column, and the field abilities do not currently guard against editing such a form, so avoid pointing them at one.
- **Capabilities are per-form, not global.** A user granted `fluentform_forms_manager` on form A may fail on form B (team-mode). Errors surface as `fluentforms_forbidden` (403).
- **Cache invalidation.** Direct DB writes bypass `FormService::clearFormCache` — our write paths call it for you, but `novamira/execute-php` snippets should call it manually when touching `form_fields` or `form_meta` directly.
- **Default submit button.** `create-form` auto-injects one. `edit-form` with `fields[]` keeps the existing submit button if present, else injects the default.
- **`delete-form` is permanent.** No trash bin. `cascade=true` wipes submissions, submission_meta, entry_details, form_meta in 4 queries; `cascade=false` (default) leaves orphan rows.
- **License is informational only.** `license_active` reflects `fluentform_pro_license_status`; Pro features run unlicensed (same semantics as SeoPress and Yoast).
- **Concurrent field mutations on the same form are NOT serialised.** `add-field`, `edit-field`, `delete-field`, and `edit-form` all read the `form_fields` JSON blob, mutate it in memory, then write the whole blob back. Two concurrent calls on the same form will race: the second save overwrites the first, silently losing the first mutation. In single-agent workflows this is rare; in multi-agent / parallel workflows do not fan out field mutations on the same form — serialise them yourself, or batch the changes through a single `edit-form` with the full fields[] payload. Mirrors the behaviour of the sibling Gravity Forms, Contact Form 7, and SeoPress integrations.
- **Settings, confirmation, and notification writes ARE concurrency-safe (unlike field mutations).** `edit-form-settings` and `edit-confirmation` share the one `formSettings` row, but their read-modify-write runs under a per-form MySQL advisory lock, so a concurrent confirmation change no longer loses an unrelated settings change. Each notification is stored as its own `wp_fluentform_form_meta` row (not a shared blob) and every notification write is `WHERE id=X` scoped, so concurrent notification edits are independent and race-safe. You do not need to serialise these yourself — only the `form_fields` field mutations above still require it.
- **Notification body is top-level-whitelisted.** Only `name`, `sendTo`, `fromName`, `fromEmail`, `replyTo`, `bcc`, `cc`, `subject`, `message`, `enabled`, `conditionals` survive; anything else is dropped and surfaced in `warnings.dropped_keys`.
- **Email-header fields reject CRLF.** `sendTo`, `fromEmail`, `replyTo`, `bcc`, `cc` containing `\r`, `\n`, or NULL bytes are rejected with `fluentforms_invalid_input` to block email header injection.
- **`customUrl` enforces a scheme whitelist.** `http`, `https`, `mailto`, `tel` only — `javascript:`, `data:`, `vbscript:` are rejected. The native WP `esc_url_raw` is NOT enough on its own.
- **`edit-form-settings` whitelist is `restrictions` / `layout` / `submitButton` / `conditionals` — all objects.** These are the only top-level `formSettings` keys it accepts; a mistyped value (e.g. a string where an object is expected) returns `fluentforms_invalid_input`. Login-required and form scheduling are NOT top-level keys — they live under `restrictions.requireLogin` and `restrictions.scheduleForm`. Passing top-level `requireLogin` / `formExpires` is silently dropped into `warnings.dropped_keys` (it has no effect), so set them inside the `restrictions` object.
- **Pro abilities don't appear on free installs.** Every Pro slug (`list-transactions`, `list-subscriptions`, `cancel-subscription`, `list-draft-submissions`, `delete-draft-submission`, `list-integrations`, `list-quiz-questions`, `get-quiz-report`) is registration-gated by `ff_pro_runtime_available()`. On a free install they never show up in `wp_get_abilities()` — defence in depth at the callback layer too via `ff_pro_required()`.
- **Pro error codes (Sprint 4/5).** Branch on these in addition to the free-tier codes: `fluentforms_pro_required` (Pro feature called without FF Pro loaded), `fluentforms_pro_integration_missing` (vendor handler class not in this FF Pro release — e.g. cancel-subscription with a vendor that ships no PaymentManager), `fluentforms_transaction_not_found`, `fluentforms_subscription_not_found`, `fluentforms_draft_submission_not_found`.
- **`cancel-subscription` is idempotent + irreversible vendor-side.** Re-cancelling an already-`cancelled`/`canceled`/`expired` row returns `success=true` with `previous_status` and `status` both set to the prior terminal value — no second vendor call is made. Vendor-side billing stops immediately on the first successful cancel; there is no resume path.
- **`list-integrations.raw` is redacted.** The decoded feed body is returned but `api_key`, `api_secret`, `access_token`, `refresh_token`, `bearer_token`, `webhook_url`, `client_secret`, `password`, `merchant_id`, `token`, `callback_url` are replaced with the literal string `***REDACTED***` (recursive, any depth). To inspect a true feed body the admin must read `wp_fluentform_form_meta` directly.
- **`get-quiz-report` caps at 5000 submissions.** Bigger quizzes need to be sampled via `status_filter` or read in batches via `novamira/execute-php`. The default order is most-recent-first.
- **Quiz answer comparison is forgiving but strict on shape.** Strings match case-insensitively + trimmed (`Rome` == `rome` == ` Rome `); arrays match as sorted sets (multi-select friendly). But a comma-separated string answer vs an array submission is **not** a match — re-encode in the answer key to align with what the field submits.

## Conventions

- **Slug pattern**: every ability is `novamira/fluentforms-<verb>-<object>`.
- **Output shape**: ids are int, timestamps are ISO 8601 strings, large field values are wrapped as `{value, truncated, full_size}` when truncated, error codes are `fluentforms_<kind>` (e.g. `fluentforms_form_not_found`, `fluentforms_invalid_input`, `fluentforms_pro_required`, `fluentforms_pro_field_not_available`, `fluentforms_field_not_found`, `fluentforms_forbidden`).
- **Progressive disclosure**: `list-*` returns compact rows; `get-*` accepts `include[]` for the heavy sub-resources. Per-field byte caps are overridable via `max_field_bytes`; the default depends on the ability (`get-submission` 4 KiB, `export-submissions` 2 KiB, `get-form-canonical` 8 KiB) — check the ability's own description for its value.
- **Annotations**: `readonly: true` for list/get; `destructive: true` for delete + edit paths that overwrite; `idempotent: true` for stable read paths and partial updates.

## Minimum versions

- Fluent Forms (free): **6.0.0**
- Fluent Forms Pro: **6.0.0**

Sites below the floor see `fluentforms_version_unsupported` from every ability except `check-setup` (which still reports the install state so the agent can explain the gap to the user).
