---
name: gravityforms-integration
description: Work with Gravity Forms — create and manage forms, fields, notifications, and entries via the novamira/gravityforms-* abilities. Covers form discovery (list, get), CRUD (create, edit, delete, duplicate), field management, and entries (Pro only). For cross-plugin migrations (CF7/WPForms/Ninja/Fluent/Formidable ↔ Gravity Forms), the `gravityforms-get-form-canonical` reader plugs the Gravity Forms shape into the `form-model-schema` canonical vocabulary used by the `form-model-migration` workflow.
---

# Gravity Forms Integration

This skill guides you through using the Gravity Forms integration in Novamira Pro.

## When to use

Activate when the user asks to:
- Create, list, or edit Gravity Forms
- Add, edit, or delete form fields
- Manage form settings, notifications, confirmations
- Query or manage form submissions/entries (Pro)

Do NOT activate for other form builders (WPForms, Contact Form 7, Formidable, etc.).

## The domain model

Gravity Forms organizes data as:

- **Form**: Container with metadata (title, description, is_active) + fields + settings + notifications + confirmations
- **Field**: Input element (text, select, repeater, etc.) with type + label + options
- **Notification**: Email trigger (on form submission, conditional)
- **Confirmation**: Post-submit action (redirect, show message) — **Pro only**
- **Entry**: Submitted form data (Pro) — field values as id → value map

## Discover first

1. **Call check-setup**. Reports GF active, Pro/Free, available field types, form count.
2. **list-forms** for overview.
3. **get-form** for full form schema.

## Cross-plugin migration

To migrate Gravity Forms → another supported plugin (CF7, WPForms, Ninja, Fluent, Formidable) or vice-versa, use the `form-model-migration` skill workflow. The Gravity-side plug-ins are:

- **Phase 1 (check setup)**: `gravityforms-check-setup`
- **Phase 2 (read source as canonical)**: `gravityforms-get-form-canonical`

`gravityforms-get-form-canonical` returns the form mapped to the plugin-agnostic `form-model-schema` vocabulary (17 canonical types) so an agent does not need to learn the Gravity-native shape (~47 field types, `inputs[]` composites, `{Label:N}` mail-tags) — the canonical document carries fields[], mail_config{}, notifications[], conditional_rules[], meta{} (source_plugin/version + lossless GF settings echo), plus `warnings[]` for everything that does not fit (multi-page forms, payment / post-creation / calculation types, captcha, complex conditional logic). Smart-tags inside subject/body/recipient/cc/bcc are rewritten to `[field-name]` to match CF7 / WPForms readers.

## Forms workflow

### Create form
1. `create-form` title="Contact Us"
2. `manage-fields` to add fields
3. `edit-form` to refine settings (optional)

### Edit form
1. `get-form` form_id=<id>
2. `edit-form` with title/description/is_active changes
3. `manage-fields` for field edits

### List & duplicate
- `list-forms` paginated (default 50, max 500)
- `duplicate-form` to copy (fields + settings + notifications)

## Fields workflow

**manage-fields** (single ability for add/edit/delete):

```json
{
  "form_id": 123,
  "fields": [
    {"type": "text", "label": "Name", "required": true},
    {"id": 2, "type": "select", "label": "Option", "options": {"choices": [...]}}
  ],
  "delete_field_ids": [5, 6]
}
```

- Omit `id` → append
- Provide `id` → edit
- List `delete_field_ids` → delete

## Field types

Call `check-setup` for canonical `available_field_types`.

**Free**: text, textarea, select, checkbox, radio, name, email, phone, date, time, html, hidden, captcha, etc.

**Pro**: repeater, calculation, creditcard, consent, payment-*, post-title, post-content, etc.

Use `get-field-type-schema` to inspect a type before using it.

## Progressive disclosure

- **list-forms**: Compact rows (id, title, status, dates, entry count)
- **get-form**: Full schema. Use include_* to trim large blocks
- **get-field-type-schema**: Lazy-loaded per type (avoids enum bloat)

## Gotchas

1. **Read-modify-write for edits**. Always fetch current state, mutate, update — don't assume you know the full state.
2. **Settings merge**. When calling edit-form with settings, they merge on top of existing (not replaced).
3. **Cache after create**. GF caches form schema; if you create then immediately get-form, you might read stale. Call again to be sure.
4. **Confirmations Pro-only**. Only on Pro license. Check check-setup.pro.
5. **Entries Pro-only**. Entry CRUD needs GF Pro (entry table doesn't exist on Free).
6. **No native duplicate**. duplicate-form is read → copy → create (not a native GF call).
7. **Idempotent delete**. Deleting non-existent form succeeds silently (idempotent).

## Typical flow

```
1. check-setup → verify GF active, Pro/Free, field types available
2. list-forms → show forms on site
3. get-form <id> → inspect full definition
4. manage-fields <id> → add/edit/delete fields
5. edit-form <id> → refine settings, title, status
6. (if Pro) create-entry / list-entries for submissions
```

## Common patterns

### Simple contact form

```
1. create-form title="Contact" description="Get in touch"
2. manage-fields form_id=<id>
   - add field type=name label="Full Name" required=true
   - add field type=email label="Email" required=true
   - add field type=textarea label="Message" required=true
3. edit-form form_id=<id> settings={button_text: "Send"}
```

### Duplicate & customize

```
1. duplicate-form form_id=<old> new_title="My Form Copy"
2. get-form form_id=<new_id>
3. manage-fields form_id=<new_id>
   - edit field id=2 label="Updated label"
   - delete_field_ids=[5]
```

## Reference

All abilities return JSON with:
- `id`: form/entry id
- `title`: human label
- `status`: "active" or "inactive"
- `date_created`, `date_modified`: ISO 8601

Errors are `WP_Error` with `code` and `message`.

---
