---
name: ninja-forms-integration
description: Activate when working with the Ninja Forms plugin on a WordPress site — listing, reading, creating, editing, duplicating or deleting forms; adding/editing/deleting fields; managing email notifications; and reading, triaging or deleting submissions. Novamira Pro drives Ninja Forms entirely through the Novamira-native `novamira/ninja-forms-*` abilities (model layer, no dependency on Ninja's own `ninjaforms/*` abilities). Trigger phrases: "Ninja Forms", "nf3 tables", "[ninja_form id=...]" shortcode, "nf_sub" submissions.
---

# Ninja Forms Integration

How to drive Ninja Forms with the Novamira-native abilities. Read this once at the start of any Ninja Forms task and refer back when in doubt.

## When to use

Activate when the user mentions Ninja Forms, the `nf3_*` storage tables, a `[ninja_form id=...]` shortcode, the `nf_sub` submissions, or asks to inspect / build / edit / migrate a form on a site where Ninja Forms is active.

Do not activate for other form plugins (CF7, WPForms, Gravity Forms, Fluent Forms, Formidable) — each has its own integration skill.

## One surface: Novamira-native

Novamira Pro exposes a complete `novamira/ninja-forms-*` ability set that drives Ninja Forms through its **PHP model layer** (`Ninja_Forms()->form()`, the `nf3_*` tables, the `nf_sub` CPT). You do **not** need — and should not reach for — Ninja's own `ninjaforms/*` abilities; everything below is self-contained.

| Group | Abilities |
|---|---|
| Setup | `check-setup` |
| Field-type catalog | `get-field-types-schema`, `get-field-type-schema` |
| Forms | `list-forms`, `get-form`, `create-form`, `edit-form`, `delete-form`, `duplicate-form` |
| Fields | `add-field`, `edit-field`, `delete-field` |
| Notifications (email actions) | `list-notifications`, `get-notification`, `create-notification`, `edit-notification`, `delete-notification` |
| Submissions | `list-submissions`, `get-submission`, `delete-submission` |
| Migration | `get-form-canonical` |

All abilities are gated on the `manage_options` capability and namespaced `novamira/ninja-forms-<verb>-<object>`.

## Domain model

- **Form** — `nf3_forms` row. Identified by integer `id` (also has a `key` slug). Form-level settings (title, success/error messages, label position, conditional logic) live in `nf3_form_meta`.
- **Field** — `nf3_fields` row, `parent_id` = form id. Identified by integer `id` and a per-form `key` slug. Type is a registry slug (`textbox`, `email`, `listselect`, `submit`, …) — enumerate with `get-field-types-schema`, never hardcode. A usable form needs a `submit` field.
- **Action** — `nf3_actions` row, `parent_id` = form id. An action with `type: "email"` IS a **notification**; other types (`successmessage`, `redirect`, `save`, `webhook`) are not notifications.
- **Submission** — an `nf_sub` CPT post; answers in postmeta `_field_<fieldId>`, plus `_form_id`, `_seq_num`. Status is the WP post_status: `publish` (active) or `trash` (trashed). Ninja has **no** read/starred/spam flag.

## Discover before you act

1. **`check-setup`** — confirm `plugin_active`, `min_satisfied`, `storage_ready: true`, `forms_total`, `available_field_types`, and the current user's capability flags. Stop if inactive / storage not ready (tell the user to open the Ninja Forms admin once so the NF3 upgrader finishes).
2. **`list-forms`** — compact rows {id, title, key, fields_count, actions_count, submissions_count, …}. Locate the target form.
3. **`get-form form_id=<id>`** — compact by default; opt into heavy blocks with `include: ["fields","actions","settings"]`. The source of truth for an edit.
4. **`get-field-types-schema`** then **`get-field-type-schema type=<slug>`** — the catalog + the per-type supported-settings whitelist, before composing an `add-field` / `create-form` payload.

## Workflows

### Build a contact form
1. `get-field-types-schema` → confirm the slugs you need (`textbox`, `email`, `textarea`, `submit`, …).
2. `create-form` with `title` + `fields[]` (each `{type, label, key?, required?, settings?}`) + optional `actions[]`. A submit button is auto-appended if you pass fields without one. Field keys are auto-derived and uniquified — **read the returned keys back, don't guess**.
3. `create-notification form_id=<id> to=<recipient> subject=<…> message=<…>` to add the admin email.
4. `get-form include=["fields","actions"]` to verify.

### Edit fields incrementally
- `add-field form_id type label [key] [required] [order] [settings]` — append (omit `order`) or insert. Validate `type` first.
- `edit-field form_id (field_id|field_key) [label] [key] [required] [order] [settings]` — shallow per-key merge; idempotent. `type` is **not** mutable (delete + add-field to change a type). Renaming `key` re-uniquifies.
- `delete-field form_id (field_id|field_key)` — idempotent (missing → `deleted:false`). Don't delete the only submit.

### Manage notifications
- `list-notifications form_id` → compact rows (only email actions). `get-notification form_id notification_id` → full settings (secrets redacted, body byte-capped).
- `create-notification` — `to` required; friendly `subject`/`message` map to native `email_subject`/`email_message`; put any other native key (`from_name`, `reply_to`, `cc`, `bcc`, `email_format`) under `settings`.
- `edit-notification` — partial merge; `changed[]` reports the **native** keys (you pass `subject`, you get back `["email_subject"]`). To silence a notification without deleting it, `edit-notification active=false`.
- `delete-notification` — idempotent; a non-email action id is a safe no-op (`deleted:false`), never deletes a non-notification.

### Read / triage submissions
- `list-submissions form_id [status=active|trashed|any] [per_page] [page]` — compact rows {id, seq_num, status, date}, newest first, NO answers. `per_page` ≤ 100.
- `get-submission form_id submission_id [fields] [max_field_bytes]` — decoded answers `[{field_id, field_key, label, value, truncated, full_size}]`. Use `fields[]` to fetch only the keys you need; large values are byte-capped (default 8192, `0` for full).
- `delete-submission form_id submission_id [force]` — default **trashes** (reversible); `force=true` permanently deletes. Idempotent.

### Cross-plugin migration (form-model-migration)
`get-form-canonical form_id [max_field_bytes]` is **Phase 2** (read source) of the cross-plugin form-model-migration workflow. It maps the Ninja form onto the 17-type canonical `form-model-schema` and enumerates losses in `warnings[]` (Ninja-specific UI types, anti-spam, composite flat-fields, non-email actions, form-level conditions). Pass `max_field_bytes: 0` before an execute-mode migration so canonical bodies are not truncated. For ad-hoc inspection prefer `get-form` (Ninja-native shape).

## Simple operations via `novamira/execute-php`

A few Ninja operations are not worth a dedicated ability — do them inline with `novamira/execute-php` (keep the answer small; the abilities above cover the structured CRUD):

- **Restore a trashed submission** — `delete-submission` trashes; to restore:
  ```php
  return ['restored' => (bool) wp_untrash_post(<submission_id>)];
  ```
  (WP restores to `draft`; re-publish with `wp_update_post(['ID'=>$id,'post_status'=>'publish'])` if needed.)
- **Read / write a non-email action** (redirect / successmessage / save / webhook) — these aren't notifications. Read via `get-form include=["actions"]`; to create/edit one:
  ```php
  $a = Ninja_Forms()->form(<form_id>)->action()->get();
  $a->update_settings(['type'=>'redirect','label'=>'Go','redirect_url'=>'https://…','active'=>1])->save();
  WPN_Helper::delete_nf_cache(<form_id>);
  return ['action_id' => (int) $a->get_id()];
  ```
- **Bulk / ad-hoc queries** on the `nf3_*` tables or `nf_sub` postmeta (counts, aggregates) — a single `$wpdb` query inside execute-php.

Always bust the form cache after a model write outside the abilities: `WPN_Helper::delete_nf_cache($form_id)`.

## Gotchas

- **Capability.** Every ability needs `manage_options`. A non-admin gets `ninja_forms_forbidden` (403). This is deliberately stricter than the other five form-plugin integrations in this project (which delegate to plugin-native caps like `wpcf7_edit_contact_forms` / `wpforms_create_forms` / `gravityforms_edit_forms` / Fluent ACL / `frm_edit_forms`): Ninja Forms 3.x does not expose a stable plugin-specific capability we can rely on, so we gate on the safest WordPress floor. Sites that need to widen the gate (e.g. let a custom "form-editor" role manage Ninja forms without being a full admin) can swap the helper via a code customisation rather than relying on a Ninja-side capability filter.
- **Settings are stored as strings.** Ninja persists field/action settings (incl. `required`, `active`, `order`) as `"1"`/`"0"` strings. The edit abilities normalise this, so re-running an edit with the same value is a true no-op (`changed: []`). Don't be surprised that booleans come back as strings in raw reads.
- **Keys are auto-uniquified.** `create-form` / `add-field` / `edit-field` derive and uniquify field keys per form and surface any change in `warnings[]`. Read the returned `key` — never assume it.
- **Submit is auto-added.** `create-form` appends a submit field when you pass fields without one.
- **Secrets are redacted in reads.** `get-form (include=actions/settings)` and `get-notification` replace secret-looking setting values (webhook URLs, API keys, SMTP creds — including nested ones) with `[REDACTED]` and set `has_secrets`. Field settings are not redacted (a field key is a slug, not a credential).
- **Submissions have no read/star/spam flag.** State is only active vs trashed. "Mark as read/important" has no Ninja equivalent — don't invent one.
- **delete-form cascades.** Deleting a form removes its fields, actions, and all `nf_sub` submissions + meta. `delete-form` and `delete-submission` are idempotent (missing → `deleted:false`).
- **Trash vs permanent.** `delete-submission` trashes by default (reversible); pass `force=true` only when you mean an unrecoverable delete.
- **Each MCP call is a fresh request.** Reads after a write are fresh because the cache is busted and the next request reloads from the DB. (Within a single PHP request Ninja's in-memory `_fields` memo can be stale after a delete — irrelevant across separate ability calls.)
- **Edits are not locked.** `edit-form` / `edit-field` / `edit-notification` read the current settings, merge your keys, and save — and Ninja rewrites the *whole* settings row on save. Two edits to the **same** entity racing in parallel are last-write-wins on the entire blob: the later save can silently drop the earlier edit, even on a different key. For a single-operator agent this never bites; if you fan out edits, serialise the ones that touch the same form/field/notification.

## Conventions

- Slugs: `novamira/ninja-forms-<verb>-<object>`. Verbs from the fixed vocabulary (`list`/`get`/`create`/`edit`/`delete`/`duplicate`/`add`).
- Compact list, full get: `list-*` returns minimal rows; `get-*` / `get-submission` opt into heavy data (`include[]` / `fields[]`) with byte caps.
- Errors: `ninja_forms_invalid_input` (400), `ninja_forms_<entity>_not_found` (404), `ninja_forms_forbidden` (403), `ninja_forms_not_active` (400). The canonical reader keeps the legacy `ninjaforms_*` codes for migration-pipeline compatibility.
- Annotations: list/get are `readonly`; delete and permanent writes are `destructive`; edits and idempotent deletes are `idempotent`.
