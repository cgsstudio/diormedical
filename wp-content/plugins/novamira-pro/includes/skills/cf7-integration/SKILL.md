---
name: cf7-integration
description: Activate when working with the Contact Form 7 plugin (CF7, wpcf7) on a WordPress site — listing, reading, creating, editing, duplicating, or deleting contact forms, mail configurations, and message strings. Covers the CF7 form-tag syntax, the partial-merge edit semantics, and the WP-native field-name aliases. Does NOT cover submissions (CF7 doesn't persist them — Flamingo is a separate plugin).
---

# Contact Form 7 Integration

## When to use

Activate this skill when:
- The user mentions Contact Form 7, CF7, `wpcf7`, "modulo contatti", "form di contatto", or asks to "create a contact form", "change the recipient of the contact form", "translate the validation messages", "duplicate the contact form for the Italian site".
- The user wants to inspect or modify a form's template, mail config (`mail`, `mail_2`), messages, or additional settings.
- `cf7-check-setup` returns `plugin_active: true` and `min_satisfied: true`.

## When NOT to use

- The user wants to read form **submissions** (who submitted what, when). CF7 does NOT persist submissions on its own; the Flamingo companion plugin is the canonical store and is OUT OF SCOPE for this integration. `cf7-check-setup` reports `integrations.flamingo_active` as an informational signal.
- The user wants reCAPTCHA / Akismet / Stripe credentials configured. Those live in CF7's Integration screen (site-global, separate from each form) and are not exposed by these abilities.
- The user wants to render a form (front-end). That's the `[contact-form-7 id="<id>"]` shortcode in post content — these abilities author the form, not the rendering.
- Always call `cf7-check-setup` first. If `plugin_active: false`, the plugin is not active — surface the gap and stop.

## Domain model

Every form is one `wpcf7_contact_form` CPT row with a fixed property bag:

| Property | Shape | Purpose |
|---|---|---|
| `form` | string | CF7 form-tag template (e.g. `[text* your-name][email* your-email][submit "Send"]`) |
| `mail` | object | Primary mail config: subject, sender, recipient, additional_headers, body, attachments, use_html, exclude_blank |
| `mail_2` | object | Secondary mail (auto-reply). Includes `active` (bool) — `false` on a fresh form. Same shape as `mail` |
| `messages` | object | Map of message key → string (validation_error, mail_sent_ok, mail_sent_ng, spam, accept_terms, …). Module-supplied keys (akismet, reCAPTCHA) are accepted as-is |
| `additional_settings` | string | Newline-separated `key: value` pairs CF7 modules read (e.g. `demo_mode: on`, `skip_mail: on`) |
| `locale` | string | Form locale (e.g. `it`, `en_US`). Drives the default message pack and the template returned by `get_template()`. |
| `hash` | string | Auto-generated unique form identifier. Read-only |

Plus CPT-level fields:

| Field | Alias | Purpose |
|---|---|---|
| `title` | `post_title` | Admin label, shown in the CF7 forms list |
| `slug` | `post_name` | Used in shortcodes / URLs |
| `status` | `post_status` | `publish` (default) or `draft` |

The integration accepts BOTH the short (`title`, `slug`, `status`) and the WP-native (`post_title`, `post_name`, `post_status`) names everywhere — pick whichever feels natural in context.

## CF7 form-tag syntax (recap)

Tag shape: `[<type><modifier?> <name> "label"? <options...>]`

- Type slug: `text`, `email`, `tel`, `url`, `number`, `date`, `textarea`, `select`, `checkbox`, `radio`, `file`, `acceptance`, `quiz`, `submit`, `hidden`, `response`, `recaptcha`. Call `cf7-get-tags-schema` for the full vocabulary with modifiers, options, and examples per tag.
- Required modifier: append `*` (e.g. `[email* your-email]` — required).
- Field name doubles as the **mail-tag** used to interpolate the submitted value into mail bodies. A field named `your-message` is referenced in `mail.body` as `[your-message]`.
- HTML attributes: append `id:<value>` / `class:<value>` to the options.
- Special read-only mail tags (no form declaration needed): `[_serial_number]`, `[_remote_ip]`, `[_user_agent]`, `[_date]`, `[_time]`, `[_url]`, `[_post_title]`, `[_user_email]`.
- Every form needs exactly one `[submit "Label"]`.

## Workflows

### 1. Create a contact form with a custom recipient

```
cf7-create-form
  title: "Pagina contatti"
  form: "[text* your-name placeholder \"Nome\"]\n[email* your-email]\n[textarea* your-message rows:8]\n[submit \"Invia\"]"
  mail:
    recipient: "info@example.com"
    subject: "Nuovo contatto da [your-name]"
    sender: "[your-email]"
    body: "Nome: [your-name]\nEmail: [your-email]\n\n[your-message]"
```

Returns the full form shape including the auto-generated `id` and `hash`. Embed in a page with `[contact-form-7 id="<id>"]`.

### 2. Change only the recipient of an existing form

`cf7-edit-form` does a **shallow per-key merge** on `mail`, `mail_2`, and `messages` — pass only the keys you want to change.

```
cf7-edit-form
  id: 142
  mail:
    recipient: "new-recipient@example.com"
```

`mail.subject`, `mail.body`, `mail.sender`, etc. stay untouched.

### 3. Duplicate a form and localise it

```
cf7-duplicate-form
  source_id: 142
  title: "Pagina contatti — IT"

# then on the copy:
cf7-edit-form
  id: <new id>
  locale: "it"
  messages:
    validation_error: "C'è stato un problema con la tua compilazione. Controlla i campi e riprova."
    mail_sent_ok: "Grazie, ti ricontatteremo a breve."
```

Duplicates default to `status: draft` — call `cf7-edit-form` with `status: publish` (or pass `status: publish` in duplicate) when ready to go live.

### 4. Inspect a form before editing it

```
cf7-list-forms
  search: "contatti"
  limit: 10

# pick the id, then:
cf7-get-form
  id: 142
```

`list-forms` returns the compact summary (no template / mail / messages). `get-form` returns the full body with per-field truncation at 8192 bytes (override via `max_field_bytes`; pass `0` to disable truncation).

### 5. Build a template from the tag schema

```
cf7-get-tags-schema
  type: "select"
```

Returns the `select` tag with its modifiers, options, and an example. Useful when the agent needs to assemble a multi-option dropdown without remembering the exact CF7 syntax.

### 6. Soft-delete vs force-delete

```
# Recoverable — form goes to trash, can be restored from wp-admin
cf7-delete-form
  id: 142
  force: false

# Permanent — gone forever, embedded shortcodes break
cf7-delete-form
  id: 142
  force: true     # the default
```

The destructive default is `force: true`. Prefer `force: false` when in doubt, especially if pages embed the form via `[contact-form-7 id="<id>"]`.

## Gotchas

- **CF7 does NOT persist submissions.** Plain CF7 sends the mail and forgets. To read submissions later you need the Flamingo plugin (a separate install). The CF7 abilities surface `integrations.flamingo_active` but do NOT cover submission reads/writes in v1. **GDPR caveat**: `cf7-delete-form` removes only the form CPT; if Flamingo is active its `flamingo_inbound` / `flamingo_outbound` rows survive and contain submission PII. Plan a separate cleanup step when honouring a data-removal request.
- **Multilingual + spam detection.** `check-setup` returns five `integrations` flags: `flamingo_active`, `recaptcha_configured`, `turnstile_configured`, `wpml_active`, `polylang_active`. Use them to decide whether to warn the user about an unprotected form (`!recaptcha_configured && !turnstile_configured`) or to coordinate per-language edits (under WPML/Polylang `forms_total` typically includes per-language duplicates).
- **Pre-delete embed check.** `cf7-list-forms` / `cf7-get-form` don't tell you which pages embed `[contact-form-7 id="<id>"]`. Before `cf7-delete-form` with `force: true` on a publish form, run a direct DB scan (e.g. `SELECT ID, post_title FROM wp_posts WHERE post_content LIKE '%[contact-form-7 id="<id>"%'` via wp-cli) so you can warn the user that those pages will start rendering CF7's "this contact form does not exist" fallback.
- **Page-cache invalidation is manual.** Plugins like W3 Total Cache / WP Rocket / LiteSpeed Cache hold their own page cache. CF7 ability mutations do NOT purge it — after `create-form` / `edit-form` / `delete-form` on a publish-state form, the agent should advise the user to clear the page cache or wait for the natural TTL.
- **`mail` extra keys are stripped.** Round-tripping `cf7-get-form` → `cf7-create-form` / `cf7-edit-form` is safe: the integration drops `body_truncated`, `body_bytes`, and any unknown mail keys before persisting (so a re-passed `get-form` payload won't accumulate noise in postmeta).
- **Audit hook for delta tracking.** `cf7-edit-form` fires the action `novamira_cf7_form_changed` with `($form_id, ['before' => …, 'after' => …])` where each side carries the `mail` and `mail_2` snapshots. Plug into it for CRM sync / audit log / change tracking (CF7 itself only fires `wpcf7_after_save` / `wpcf7_after_create` without a diff).
- **`mail_2` is opt-in.** A fresh form has `mail_2.active = false`. To enable the auto-reply, edit `mail_2` with `active: true` plus the other keys.
- **Mail tags vs form tags.** `[text* your-name]` (in the template) declares a field; `[your-name]` (in a mail body) interpolates its value. Don't confuse them — the bracket structure is the same but the context flips the meaning.
- **`additional_settings` is a raw string, not an object.** CF7 stores it newline-separated, `key: value` per line (e.g. `demo_mode: on\nskip_mail: on`). The agent passes it as a string; CF7 parses it.
- **Locale change is partial.** Setting `locale` switches the **default message pack** used when a message string is empty, but does NOT translate the template (`form`) or existing message overrides. To localise a form, change `locale` AND patch the `messages` and `form` accordingly. The integration accepts IETF-style locales (`en`, `it`, `fr_FR`); malformed values are rejected with `cf7_invalid_locale`.
- **Renaming a form field breaks the mail.** If you change `[text* your-name]` to `[text* full-name]` in the template, every `[your-name]` reference in `mail.body` / `mail.subject` / `mail_2.body` stops resolving. Edit them together.
- **Force-delete leaves no trace.** Pages that embed `[contact-form-7 id="<id>"]` will render CF7's "this contact form does not exist" message after a force-delete. Soft-delete (`force: false`) is reversible — prefer it when in doubt.
- **Trashed forms vanish from default `list-forms`.** The default `status` filter is `publish + draft`. Pass `status: trash` explicitly to see trashed forms.
- **`hash` is read-only.** Each form has an auto-generated hash CF7 uses for its API surface. A duplicate gets a fresh hash; editing other fields does NOT change it.

## Conventions

- **Slug pattern**: every ability is `novamira/cf7-<verb>-<object>`. No action enum dispatchers.
- **Output shape**: every CRUD response returns the form under the `form` key with the same shape as `cf7-get-form` (id, title, slug, status, locale, hash, tag_count, modified, form, mail, mail_2, messages, additional_settings + truncation flags on large strings). One shape, one mental model.
- **Error codes**: every write ability (`create-form`, `edit-form`, `duplicate-form`, `delete-form`) returns the uniform shape `{success: false, error, error_code}` on failure — read abilities (`get-form`) return `WP_Error`. Machine-stable codes: `cf7_form_not_found` (unknown / trashed / auto-draft / future / inherit / wrong CPT), `cf7_invalid_input` (schema / stranger keys / non-int id / whitespace-only title / out-of-enum status), `cf7_invalid_locale` (malformed locale), `cf7_save_failed` (CF7 storage refused the write), `cf7_delete_failed` (wp_delete_post / wp_trash_post returned false). A delete that races a parallel delete on the same id returns `cf7_form_not_found` (not `cf7_delete_failed`) so the agent gets a stable signal.
- **WP-native aliases**: `post_title` ↔ `title`, `post_name` ↔ `slug`, `post_status` ↔ `status`. Accepted on every write ability; the integration normalises before validation.
- **Progressive disclosure**: `list-forms` is compact (id, title, slug, status, locale, hash, tag_count, modified). `get-form` is full. Large text fields truncate at 8192 bytes by default with `*_truncated` + `*_bytes` flags — re-call with a larger `max_field_bytes` (or `0` for unlimited) when the full body is needed.

## Annotations summary

| Ability | readonly | destructive | idempotent |
|---|---|---|---|
| `cf7-check-setup` | ✓ |  | ✓ |
| `cf7-list-forms` | ✓ |  | ✓ |
| `cf7-get-form` | ✓ |  | ✓ |
| `cf7-get-form-canonical` | ✓ |  | ✓ |
| `cf7-get-tags-schema` | ✓ |  | ✓ |
| `cf7-create-form` |  |  |  |
| `cf7-edit-form` |  |  |  |
| `cf7-duplicate-form` |  |  |  |
| `cf7-delete-form` |  | ✓ |  |

## Cross-plugin migration mapping

When the user wants to migrate a CF7 form **to** or **from** another form plugin (WPForms, Gravity Forms, Fluent Forms, Formidable Forms), activate the `form-model-migration` skill — it drives the seven-phase workflow (audit → plan → execute → verify) using `form-model-schema` as the canonical intermediate format. This section is the plugin-specific mapping reference the migration skill loads alongside its workflow.

### Canonical snapshot — preferred reader

`cf7-get-form-canonical id=<id>` is the dedicated reader for migration work. It returns the form already mapped to the `form-model-schema` vocabulary plus the relevant `warnings[]`, saving the agent the manual translation step. The parsing of the CF7 form-tag string (regex over the bracket syntax, `*` modifier handling, options extraction) is non-trivial and lives in PHP — do not attempt to re-implement it in `execute-php`.

For interactive single-form inspection unrelated to migration, keep using `cf7-get-form` (raw shape) — `cf7-get-form-canonical` is purpose-built for the canonical pipeline and applies the same `max_field_bytes` truncation contract.

### Form-tag → canonical type

| CF7 form-tag | Canonical type | Notes |
|---|---|---|
| `text`, `text*` | `text` | `*` → `required: true`. `minlength:`, `maxlength:`, `placeholder` map to `validation` / `placeholder`. |
| `email`, `email*` | `email` | |
| `tel`, `tel*` | `tel` | Loose validation; tighter rules go in `validation.pattern`. |
| `url`, `url*` | `url` | |
| `number`, `number*` | `number` | `min:`, `max:`, `step:` → `validation`. |
| `date`, `date*` | `date` | `min:`, `max:` → `validation` (ISO `YYYY-MM-DD`). |
| `textarea`, `textarea*` | `textarea` | `cols:` / `rows:` go to `meta` (rendering hint, not canonical). |
| `select`, `select*` | `select` | `multiple` → `cardinality: multi`. `include_blank` → `meta`. Quoted tokens become `choices[]`. |
| `checkbox`, `checkbox*` | `checkbox` | `cardinality: multi` by default. `exclusive` → `meta` (single-pick mode). |
| `radio` | `radio` | `cardinality: single` always. |
| `file`, `file*` | `file` | `filetypes:` → `validation.accepts` (extension-to-MIME mapped). `limit:` → `validation.max_size_bytes`. |
| `acceptance` | `consent` | `optional` modifier → `warning: informational` (canonical `consent` is always required). |
| `quiz` | — | `warning: structural` — anti-spam Q&A logic has no canonical equivalent. |
| `submit` | `submit` | Quoted token → `label`. |
| `hidden` | `hidden` | `default:` → `default`. |
| `response` | — | Output area, no canonical equivalent — skipped in translation. |
| `recaptcha` | — | `warning: informational` — keys are site-global (CF7 Integration screen), not per-form. |

### Canonical → CF7 form-tag

When CF7 is the **target** of a migration, the canonical fields[] must be serialised back into the CF7 form-tag string. The transformation is mechanical and lives as an `execute-php` snippet in `form-model-migration` (Phase 5). Summary:

- `text|email|tel|url|number|date|textarea|select|radio|checkbox|file|hidden` → tag type stays the same name.
- `consent` → `acceptance`.
- `group` → **no native equivalent**; emit `warning: structural` and either flatten with prefixed names or drop the group. CF7 has no row-repeater.
- `submit` → always exactly one — emit `[submit "<label>"]` with the canonical `label`.

Mail-tag references in `mail_config.body` / `mail_config.subject` stay as `[field-name]` — this is the canonical convention AND CF7's native syntax, so the round-trip is lossless.

### Mail config mapping

- `mail` (primary notification) ↔ `mail_config{}` canonical.
- `mail_2` (secondary autoreply, `active: bool`) ↔ first entry in `notifications[]` with `name: "autoreply"`. When `mail_2.active == false`, emit the entry with `active: false` rather than dropping it (the source intent is preserved).

When CF7 is the **target**: canonical `notifications[]` with more than one entry → only the FIRST entry is mapped to `mail_2`; remaining entries are dropped with `warning: structural` ("CF7 supports only one secondary notification; <N-1> entries dropped").

### Messages mapping

CF7 ships ~20 message keys (`validation_error`, `mail_sent_ok`, `mail_sent_ng`, `spam`, `accept_terms`, …). The integration does NOT whitelist them — unknown keys (module-supplied or custom) are preserved as-is on read and write. Canonical `messages{}` accepts arbitrary string keys → round-trip is lossless.

### Special mail-tags

CF7 ships these without requiring a corresponding field declaration:

`[_serial_number]`, `[_remote_ip]`, `[_user_agent]`, `[_date]`, `[_time]`, `[_url]`, `[_post_title]`, `[_user_email]`

They are preserved verbatim in `mail_config.body` / `mail_config.subject` during canonical translation. When migrating TO a target plugin that lacks equivalents, emit a `warning: informational` per occurrence and let the user replace them post-migration with the target plugin's equivalent (e.g. Gravity Forms `{date_mdy}`, WPForms `{date format="m/d/Y"}`).

### Quirks specific to CF7

- **Form-tag string asymmetry.** CF7 stores the template as a single string of bracket-syntax tags; every other supported form plugin uses a structured array. The read side parses (`cf7-get-form-canonical`); the write side serialises (`execute-php` snippet in `form-model-migration`).
- **reCAPTCHA / Turnstile** keys live in `wp_options.wpcf7` (site-global, not per-form). `check-setup` reports `integrations.recaptcha_configured` / `turnstile_configured` as informational signals — never include in canonical, always `warning: informational`.
- **Submissions** are NOT persisted by CF7 itself. The Flamingo plugin owns the submission store; out of canonical scope. Migrating Flamingo `flamingo_inbound` / `flamingo_outbound` rows is a separate problem with its own tooling.
- **Mail-tags vs form-tags.** `[text* your-name]` (in the template) declares a field; `[your-name]` (in a mail body) interpolates its value. Canonical uses `[field-name]` syntax in body strings — this matches CF7's native syntax, so no translation needed when CF7 is on either side.
- **`additional_settings`** is a raw newline-separated `key: value` blob (`demo_mode: on\nskip_mail: on`). Out of canonical scope — preserve in `meta.cf7_additional_settings` for lossless round-trip when CF7 is both source and target, emit `warning: informational` per non-empty entry when crossing to another plugin.
- **Locale.** Changing CF7's `locale` swaps the default message pack but does NOT translate the template or existing message overrides. Canonical `locale` preserves the IETF tag; the target plugin's own locale handling kicks in on import.

### Blind spot — compact-read

`cf7-get-form-canonical` honours the same `max_field_bytes` truncation contract as `cf7-get-form` (default 8192 bytes per large text field, with `*_truncated` + `*_bytes` flags). Set `max_field_bytes: 0` to disable truncation when full-fidelity canonical is required (e.g. before an execute-mode migration). The truncation does NOT apply to canonical `fields[]` (already structurally bounded by the form-tag count), only to `mail_config.body`, `notifications[].body`, and the optional `meta.cf7_additional_settings` echo.

### When to escalate to the migration skill

If the user asks any of:
- "Voglio passare da CF7 a WPForms / Gravity / Fluent / Formidable" (or vice-versa)
- "Migra questo form CF7 in <other plugin>"
- "Cosa perdo se sposto questo form su <other plugin>?"
- "Riscrivi questo form CF7 come <other plugin>"

→ activate `form-model-migration` (which loads `form-model-schema` plus the source and target integration skills) and follow the seven-phase workflow. Do NOT attempt ad-hoc canonical translation outside that pipeline.
