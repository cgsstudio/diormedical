---
name: form-model-schema
description: Activate when reasoning about a WordPress site's contact-form model in plugin-agnostic terms — snapshotting, auditing, migrating, diffing, replicating, or documenting forms, fields, mail configurations, notifications, and messages across Contact Form 7 / WPForms / Gravity Forms / Fluent Forms / Formidable Forms / Ninja Forms. Defines a canonical vocabulary (~17 field types + universal modifiers + validation block + mail_config + notifications[] + messages) for capturing a form independently of which plugin owns it. Vocabulary skill, not a workflow playbook.
---

# Form Model Schema

A plugin-agnostic vocabulary for representing a WordPress site's contact-form model — the structure of fields, mail notifications, validation messages, and conditional rules of a form. Use it as the **target shape** when you need to reason about form schema across plugins or describe it in neutral terms.

## When to use

Activate this skill when:

- The user wants to snapshot the structure of a contact form in neutral terms.
- The user wants to compare two environments (staging vs prod, two client sites) or two plugins on the same site.
- The user wants to migrate a form from one plugin to another. Combine this skill with the per-plugin integration skill for the source and the target — and use the `form-model-migration` skill for the seven-phase audit → plan → execute → verify workflow.
- The user wants to audit "what would I lose moving from plugin X to plugin Y?" — the dedicated playbook is `form-model-migration`; this skill provides only the vocabulary it uses.
- The user wants documentation of the form schema in plugin-neutral language.

## When NOT to use

- For single-plugin work — the corresponding `<plugin>-integration` skill is enough on its own.
- For migrating stored *submissions* (rows submitted by end users — Flamingo entries, WPForms entry table, Gravity Forms entries, Fluent Forms submissions, Formidable entries). This skill covers schemas only; submission migration is a separate problem with plugin-specific storage layouts.

## The vocabulary — 17 canonical field types

Each type is defined by semantics, not by which plugin happens to call it that name. Every form plugin's native types map onto one of these (possibly with specialization via `format`, `subtype`, or modifiers).

| Type | Semantic definition |
|---|---|
| `text` | Single-line text input. Use `format: plain \| slug \| password` to specialize. |
| `email` | Single-line email input with RFC-style validation. |
| `tel` | Phone number (loose validation — pattern check via `validation.pattern` when stricter rules are needed). |
| `url` | URL with schema validation. |
| `number` | Numeric input. Use `subtype: integer \| decimal`. |
| `textarea` | Multi-line text input. |
| `date` | Calendar date. |
| `time` | Time of day. |
| `datetime` | Combined date + time. |
| `select` | Drop-down. Provide `choices: [{ value, label }]` and `cardinality: single \| multi`. |
| `radio` | Radio-button group (cardinality is always `single` — choose `checkbox` for multi-select). Provide `choices: [{ value, label }]`. |
| `checkbox` | Checkbox or checkbox group. `cardinality: single` for a single yes/no (use `consent` instead when it gates submission), `cardinality: multi` for a multi-pick group. Provide `choices: [{ value, label }]` for `multi`. |
| `file` | File upload. Use `validation.accepts: ["image/*", "application/pdf"]` and `validation.max_size_bytes`. `cardinality: multi` for multi-upload. |
| `hidden` | Hidden input. Used for tracking referrers / campaign IDs / pre-filled values not visible to the user. |
| `submit` | Submit button. Always exactly one per form. `label` carries the button text. |
| `consent` | GDPR / terms-of-service / privacy-policy checkbox. Gates submission (always `required: true` by canonical convention — `optional` modifiers on the source side are noted in `warnings[]`). |
| `group` | Nested sub-fields. Carries `fields: [...]`. Use `cardinality: multi` (a.k.a. "repeater") when the group can repeat N times. |

If a plugin's native type doesn't fit any of the above, model it with the closest match plus modifiers, and surface the loss to the user. Do not invent new canonical types ad-hoc.

## Universal modifiers

Apply to any field regardless of type:

- `required: boolean`
- `default: <type-appropriate>` — initial value.
- `placeholder: string`
- `label: string`, `description: string`
- `cardinality: single | multi` — meaningful on `select`, `checkbox`, `file`, `group`. Orthogonal to `repeatable`.
- `repeatable: boolean` — the same SINGLE field can hold N independent instances (e.g. WPForms "list" of phone numbers). Different from `cardinality: multi`, which means ONE field instance accepts N values picked from a fixed choice set.
- `localizable: boolean` — opt-in for WPML / Polylang translation of label / placeholder / description.

Also useful and universal:

- `id`, `class` — HTML attribute hints. Drop into `meta` when emitting — the canonical shape does not enforce them, but most source plugins carry them and round-trip is cleaner with them preserved.

## Validation block

Wide vocabulary covering what every form plugin recognizes. Pick only the keys relevant to the type; plugins that don't enforce a given rule simply ignore it on import.

```json
"validation": {
  "min": <number>,
  "max": <number>,
  "step": <number>,
  "min_length": <int>,
  "max_length": <int>,
  "min_items": <int>,
  "max_items": <int>,
  "pattern": "<regex>",
  "accepts": ["<mime>", ...],
  "max_size_bytes": <int>
}
```

## Top-level shape

A complete form snapshot has the keys below at the root. All except `fields[]` are optional — emit only what's present on the source.

```json
{
  "form_id": "<source-id>",
  "title": "Contact us",
  "slug": "contact-us",
  "locale": "en_US",
  "fields": [
    {
      "name": "your-name",
      "type": "text",
      "label": "Your Name",
      "required": true,
      "validation": { "max_length": 100 }
    },
    {
      "name": "your-email",
      "type": "email",
      "label": "Your Email",
      "required": true
    },
    {
      "name": "your-message",
      "type": "textarea",
      "label": "Your Message",
      "required": true
    },
    {
      "name": "submit",
      "type": "submit",
      "label": "Send"
    }
  ],
  "mail_config": {
    "subject": "New contact from [your-name]",
    "sender": "[your-email]",
    "recipient": "info@example.com",
    "additional_headers": "",
    "body": "Name: [your-name]\nEmail: [your-email]\n\n[your-message]",
    "attachments": [],
    "use_html": false,
    "exclude_blank": false
  },
  "notifications": [
    {
      "name": "autoreply",
      "active": true,
      "subject": "We received your message",
      "sender": "info@example.com",
      "recipient": "[your-email]",
      "body": "Hi [your-name], thanks — we'll get back to you shortly.",
      "use_html": false,
      "exclude_blank": false
    }
  ],
  "messages": {
    "validation_error": "Please check the fields and try again.",
    "mail_sent_ok": "Thank you, your message was sent.",
    "mail_sent_ng": "There was an error sending the message.",
    "spam": "This message was flagged as spam."
  },
  "conditional_rules": [
    {
      "target_field": "phone",
      "operator": "show_if",
      "logic": "all",
      "conditions": [
        { "field": "contact_method", "op": "equals", "value": "phone" }
      ]
    }
  ],
  "meta": {
    "source_plugin": "cf7",
    "source_version": "5.8.0"
  },
  "warnings": [
    {
      "path": "fields[3].quiz",
      "reason": "CF7 quiz field has no canonical equivalent — anti-spam logic is plugin-specific.",
      "severity": "structural"
    }
  ]
}
```

### Distinguishing `mail_config` from `notifications[]`

- `mail_config` is the **primary** notification (the one sent to the form owner / site admin). Every form has exactly one.
- `notifications[]` is the array of **additional** notifications — autoresponders to the visitor, secondary admin alerts, internal CRM bcc, etc. Source plugins differ wildly:
  - CF7 has exactly `mail` + `mail_2` (so `notifications[]` carries 0 or 1 entries on CF7-sourced canonicals).
  - WPForms, Gravity Forms, Fluent Forms, Formidable can have N notifications — `notifications[]` carries all of them.
- The `name` key on each `notifications[]` entry is a stable identifier the agent invents (`autoreply`, `admin_2`, `crm_sync`, …) — it does not need to match a source-side key.

### Conditional rules — same structure for all plugins that have them

`conditional_rules[]` captures show/hide logic where the source plugin exposes it in structured form. Shape:

```json
{
  "target_field": "<field name being shown/hidden/required>",
  "operator": "show_if | hide_if | require_if | skip_if",
  "logic": "all | any",
  "conditions": [
    { "field": "<other field>", "op": "equals | not_equals | contains | greater_than | less_than | empty | not_empty", "value": "<value>" }
  ]
}
```

CF7 has no canonical conditional logic — emit no entries when the source is CF7. WPForms / Gravity / Fluent / Formidable expose structured rules — map them through `conditional_rules[]` 1:1 when possible, and emit a `warning: structural` for anything that doesn't fit (nested AND/OR, calculated values, regex match).

### Mail tags vs field names

In `mail_config.body`, `mail_config.subject`, and `notifications[i].body`, references to field values look like `[field-name]` (square brackets around the field's `name`). This is the canonical convention — it matches CF7's mail-tag syntax natively. For source plugins that use a different syntax (`{field_id_3}` in Gravity, `{textfield-1}` in Fluent, `{field-name}` in WPForms), the **export side** normalises to `[field-name]` notation in the canonical, and the **import side** of the migration translates back to the target plugin's syntax.

**Special mail-tags** (CF7 ships these, others differ): `[_serial_number]`, `[_remote_ip]`, `[_user_agent]`, `[_date]`, `[_time]`, `[_url]`, `[_post_title]`, `[_user_email]`. Preserve them as-is in `mail_config.body` when CF7 is the source. When migrating to a target plugin that doesn't support them, emit a `warning: informational` per occurrence and let the user replace them post-migration.

### Warnings

`warnings[]` is an out-of-band channel for things the canonical shape *cannot* represent. Each entry:

- `path` — JSON-pointer-ish reference into the snapshot (e.g. `fields[3].quiz`, `mail_config.additional_headers`).
- `reason` — human-readable explanation of what was lost or transformed.
- `severity: structural | informational` — `structural` when the loss changes semantics (anti-spam logic dropped, conditional logic flattened, payment field discarded), `informational` when only UI sugar or rendering hints were dropped (CSS class names, column layout, label position).

Warnings are produced by the **export side** (when reading from a plugin and not everything fits in the canonical shape) and surfaced to the user before the import side acts.

## Mini-examples

Atomic field with validation:

```json
{ "name": "your-quantity", "type": "number", "subtype": "integer", "label": "Quantity",
  "validation": { "min": 1, "max": 99 } }
```

Multi-pick checkbox group:

```json
{ "name": "topics", "type": "checkbox", "label": "Interests",
  "cardinality": "multi",
  "choices": [
    { "value": "sales", "label": "Sales" },
    { "value": "support", "label": "Support" },
    { "value": "billing", "label": "Billing" }
  ] }
```

File upload with restrictions:

```json
{ "name": "your-cv", "type": "file", "label": "Your CV",
  "validation": { "accepts": ["application/pdf"], "max_size_bytes": 5242880 } }
```

Single-instance group (nested sub-fields):

```json
{ "name": "address", "type": "group", "label": "Address",
  "fields": [
    { "name": "street", "type": "text", "label": "Street" },
    { "name": "city",   "type": "text", "label": "City"   },
    { "name": "zip",    "type": "text", "label": "ZIP"    }
  ] }
```

Repeating group (a.k.a. repeater):

```json
{ "name": "phone_numbers", "type": "group", "label": "Phone Numbers",
  "cardinality": "multi",
  "fields": [
    { "name": "label",  "type": "text", "label": "Label"  },
    { "name": "number", "type": "tel",  "label": "Number" }
  ] }
```

Consent gate:

```json
{ "name": "accept-terms", "type": "consent", "label": "I accept the terms.",
  "required": true }
```

## Frontier of portability

The vocabulary deliberately does NOT capture these concepts. They survive when a snapshot is round-tripped within the same plugin (because the source plugin still owns the data), but are lost when crossing plugin boundaries. Each loss should produce a `warnings[]` entry on export so the user sees it before the import side acts.

- **Anti-spam configuration** — Akismet keys, reCAPTCHA / hCaptcha / Turnstile site/secret keys, honeypot field names. They live at the **plugin level** (site-global), not the form level. Lost; warning severity `informational` on per-form snapshots. Mention in the migration plan: "Spam protection must be reconfigured on the target plugin manually."
- **Anti-spam logic** — CF7 `quiz` tag, WPForms anti-spam token, Gravity honeypot toggle. The intent (gate spam) is portable but the implementation is plugin-specific. Lost; warning severity `structural`.
- **Conditional logic beyond `conditional_rules[]` shape** — nested AND/OR within a single rule, calculated-value triggers, regex value match. Lost; warning severity `structural` if the source explicitly relied on it.
- **Third-party integrations** — Mailchimp connectors, Salesforce push, Stripe / PayPal payments, Zapier webhooks, CRM sync. They are configured on the source plugin's integration screen and reference per-integration credentials. Lost; warning severity `informational`. Mention in the migration plan: "Reconfigure third-party integrations on the target plugin manually."
- **Credentials / API tokens** — Never migrate these. They live outside the schema and must be recreated on the target side from the user's source of truth.
- **Multi-step / multi-page forms** — page-break field types (Gravity `page`, WPForms `pagebreak`, Fluent `step_start/step_end`, Formidable `divider:page-break`). Canonical has no `page` field type. Lost; warning severity `structural` — the migration produces a single-page equivalent.
- **Form-level styling / custom CSS / custom JS** — out of scope. Re-apply on the target plugin manually.
- **Plugin-specific rendering tags** — section headers, dividers, HTML blocks, summary panels. They are layout, not data. Drop with `severity: informational`.
- **Submitted entries** — the canonical schema is the *form*, not the submissions. Migrating submissions is a separate problem (see `form-model-migration` "Not covered" section).

If a user asks to preserve any of these across a migration, the answer is: the canonical schema does not carry them; recreate manually after import using the target plugin's UI or the corresponding `<plugin>-integration` skill.

## What this skill does NOT do

- It does NOT prescribe a workflow. No "read source → translate → write target" recipe. The agent orchestrates using the per-plugin integration skills it already knows, with `form-model-migration` as the playbook.
- It does NOT include translation tables (canonical ↔ plugin-native). The agent infers mappings from this skill's vocabulary plus its existing knowledge of each plugin's surface (per-plugin sections in `form-model-migration`).
- It does NOT version the JSON. The representation is built and consumed within a single conversation; no archived files, no schema evolution to manage.
- It does NOT cover submission migration. Schemas only.
