---
name: wpforms-integration
description: Activate when working with WPForms (Lite or Pro) on a WordPress site — discovering forms, reading form definitions, inspecting field-type schemas, and (in later sprints) creating/editing forms, managing fields and notifications, or working with submissions (Pro). Establishes the Lite-vs-Pro feature split, the JSON-encoded form-blob model that lives inside the `wpforms` CPT's post_content, and the canonical discover → read → modify → write workflow shared across every `novamira/wpforms-*` ability.
---

# Working with WPForms (Lite + Pro)

This skill is for using the `novamira/wpforms-*` abilities together. Read it once at the start of any WPForms-related task and refer back when in doubt.

## When to use

Activate when the user asks to inspect, read, create, edit, delete, or query WPForms data: listing forms, reading a form's structure, adding or removing fields, configuring notifications and confirmations, or (on Pro) looking at submissions.

Do not activate for unrelated form plugins (Contact Form 7, Gravity Forms, Ninja Forms, Formidable Forms) — each has its own integration when present. Do not activate for builder-rendered front-end forms that don't actually use WPForms.

## Lite vs Pro

WPForms ships Lite (free) and Pro (paid) from the same plugin slug branch. Most form-definition operations work on both. **Entries / submissions and the premium field types are Pro-only.**

| Surface | Lite | Pro |
|---|---|---|
| Form discovery + read | ✓ | ✓ |
| Form CRUD (create / edit / delete / duplicate) | ✓ | ✓ |
| Field management (add / edit / delete / reorder) | ✓ | ✓ |
| Notifications + confirmations + settings | ✓ | ✓ |
| Field types (text / email / textarea / select / checkbox / radio / name / number / number-slider / gdpr-checkbox / internal-information / divider / html / content / pagebreak / hidden) | ✓ | ✓ |
| Field types (address / date-time / phone / rating / richtext / url / file-upload / camera / password / signature / credit-card / payment-* / entry-preview / repeater / layout / custom-captcha) | — | ✓ |
| Entries / submissions (list, get, edit, delete, mark) | — | ✓ |

Always call `novamira/wpforms-check-setup` first — it advertises `pro`, `supports_entries`, `supports_payments`, `supports_file_uploads`, `supports_signature`, and the authoritative `available_field_types` list for the install. Picking a field type not in `available_field_types` will fail at write time.

## The form data model

A WPForms form is a single row in the `wp_posts` table with `post_type = 'wpforms'`. The structural definition lives **as a JSON blob inside `post_content`** — fields, settings, notifications, confirmations, payment config, meta. WPForms ships `wpforms_decode()` / `wpforms_encode()` helpers to round-trip the blob safely; all `novamira/wpforms-*` abilities call these so you never touch raw JSON yourself.

When `get-form` returns a form, the decoded blob is normalised into top-level sections so the caller doesn't have to know which keys live under `settings.*`:

```jsonc
{
  "id": 42,
  "title": "Contact Form",
  "slug": "contact",
  "status": "publish",
  "date_created": "...",
  "date_modified": "...",
  "entry_count": 17,           // null on Lite
  "fields":        { ... },    // indexed by field id (string keys, monotonic integer values)
  "settings":      { ... },    // form_title, submit_text, anti_spam, ajax_submit, etc.
  "notifications": { ... },    // settings.notifications surfaced separately
  "confirmations": { ... },    // settings.confirmations surfaced separately
  "payments":      { ... },    // include_payments=true (off by default)
  "meta":          { ... }     // include_meta=true (off by default)
}
```

The raw blob still has `notifications` and `confirmations` UNDER `settings`. `get-form` surfaces them at the top level so the agent can opt out of either independently — `include_notifications: false` skips a notification block that could be hundreds of lines without affecting `settings`.

### Field shape

Each entry in `fields` is keyed by the field's WPForms-internal numeric id (as a STRING key — JSON does not distinguish). Inside:

```jsonc
"3": {
  "id": "3",
  "type": "email",
  "label": "Your email",
  "description": "We'll only use this to reply.",
  "required": "1",
  "size": "medium",
  "css": "u-mb-4",
  "conditional_logic": "1",
  "conditionals": [ ... ]
}
```

Different field types add type-specific keys (`choices` for select / radio / checkbox, `format` for name / date-time / phone, `extensions` + `max_size` for file-upload, etc.). Call `novamira/wpforms-get-field-type-schema` to see the supported options for a specific type before authoring a payload.

## Discover before you act

For every non-trivial WPForms task, walk the discovery ladder before touching anything:

1. **`novamira/wpforms-check-setup`** — confirms WPForms is active, reports edition and the authoritative `available_field_types` list. Stop if `active: false`. Note `pro` — if the task needs entries and `supports_entries: false`, surface that to the user immediately.
2. **`novamira/wpforms-list-forms`** — paginated minimal rows (id, title, slug, status, dates, entry_count). Use to locate the target form by title or to sweep the catalog. Default limit is 50, cap is 500.
3. **`novamira/wpforms-get-form`** — full definition of a single form. Use the `include_*` flags to slim the payload: pass `include_fields: true, include_settings: false, include_notifications: false, include_confirmations: false` when you only need the field structure.
4. **`novamira/wpforms-get-field-type-schema`** — per-type descriptor (label_text, edition, supports, options). Call once for each field type you're about to write.

This sequence is cheap (all reads) and surfaces version/edition mismatches before you start composing a write payload that would have failed at submit.

## Reading forms

`get-form` is the workhorse. Three patterns:

- **Audit a form's fields:** `{form_id, include_fields: true, include_settings: false, include_notifications: false, include_confirmations: false}` — smallest payload that still answers "what does this form collect?"
- **Inspect notifications:** `{form_id, include_fields: false, include_settings: false, include_notifications: true, include_confirmations: false}` — only the notification rules.
- **Full snapshot for editing:** every flag on (the defaults already include fields/settings/notifications/confirmations). Add `include_payments: true` and `include_meta: true` only when the task actually touches those.

`list-forms` returns minimal rows — when the agent needs the full shape, follow up with `get-form` per id. Don't ask for all-forms-with-fields; the list ability would have to grow unbounded.

## Field-type catalog

`get-field-type-schema` returns:

```jsonc
{
  "type": "select",
  "label_text": "Dropdown",
  "edition": "lite",
  "supports": ["label", "description", "required", "css", "conditional_logic"],
  "options": {
    "choices":         { "description": "Array of {label, value, image?} entries.", "type": "array" },
    "choices_images":  { "description": "Enable image choices.", "type": "boolean" },
    "random":          { "description": "Randomise the choice order on render.", "type": "boolean" },
    "multiple":        { "description": "Allow multiple selections.", "type": "boolean" },
    "style":           { "description": "classic | modern.", "type": "string" },
    "placeholder":     { "description": "First placeholder option.", "type": "string" }
  }
}
```

- **`supports`** lists the cross-cutting features every field of this type exposes (label is universal, description / required / css / conditional_logic are common).
- **`options`** is the type-specific surface — the only knobs the agent needs to reason about when authoring add-field / edit-field input in later sprints.
- **`edition`** is `"lite"` or `"pro"`. The ability returns `null` (a `wpforms_unknown_type` error) when the type is Pro and the install is Lite.

## Creating a form

`novamira/wpforms-create-form` makes a new empty form. The minimum payload is `{title: "..."}`. Optional knobs:

- `description` — mirrored into `settings.form_desc` so the rendered form shows it.
- `status` — `publish` (default) or `draft`. Start as draft if you intend to populate fields before exposing it.
- `settings` — partial override. Common keys: `submit_text`, `anti_spam`, `ajax_submit`, `form_class`. Notifications and confirmations live under `settings` too, but in 99% of cases you want to let the default notification + confirmation get seeded and refine them later with `edit-form`.

The new form is empty — no fields. Add fields in a follow-up call (Sprint 3 will land `add-field`; for now, populate via `edit-form.fields` if you have a ready-to-go map).

```jsonc
// Minimal
{ "title": "Contact" }

// With overrides
{
  "title": "Quote Request",
  "status": "draft",
  "description": "Tell us about your project — we reply within 24h.",
  "settings": { "submit_text": "Request a Quote", "ajax_submit": "1" }
}
```

## Editing a form

`novamira/wpforms-edit-form` is **partial merge** for `title`, `description`, `status`, `settings`, and `meta` — keys you don't pass are preserved. For `notifications`, `confirmations`, and `fields` it is a **full REPLACE**: passing the key swaps the whole id-keyed map. Always `get-form` first to fetch the current map, mutate it locally, then write back.

The response includes an `updated[]` array naming the top-level keys that actually changed, plus the standard compact row. Passing nothing modifiable is a no-op — `updated[]` is empty and nothing is persisted.

```jsonc
// Rename + republish a draft
{ "form_id": 42, "title": "Contact (v2)", "status": "publish" }

// Tweak one settings key without touching fields/notifications
{ "form_id": 42, "settings": { "submit_text": "Get in touch" } }

// Replace the notifications block (read-modify-write pattern)
{
  "form_id": 42,
  "notifications": {
    "1": { "notification_name": "Admin", "email": "{admin_email}", "subject": "New entry", "message": "{all_fields}" },
    "2": { "notification_name": "Sales", "email": "sales@example.com", "subject": "Lead", "message": "{all_fields}" }
  }
}
```

`edit-form.fields` works the same way (REPLACE) but is a last-resort blunt instrument — prefer the field-level abilities below (`add-field`, `edit-field`, `delete-field`, `reorder-fields`) since they handle `field_id` sequencing automatically and never wipe an adjacent field by mistake.

## Working with fields

The four field-level abilities operate on the `fields` map inside a single form's blob. They are atomic per call — each call reads the blob, mutates one field, persists it, and clears the post cache. No partial state is ever stored.

### add-field

Append a new field. Required: `form_id`, `type`, `label`. The type must be in `check-setup.available_field_types` (a Pro type on a Lite install returns `wpforms_unknown_type`). The new field gets the form's next sequential id (the `field_id` counter is bumped); position equals the current count.

Cross-cutting options (`description`, `required`, `size`, `css`, `conditional_logic`) are first-class top-level keys. Type-specific knobs (choices, format, extensions, min/max, etc.) go under `options` and are merged flat into the stored entry.

```jsonc
// Plain text field
{
  "form_id": 42, "type": "text", "label": "Your name",
  "required": true, "description": "First and last."
}

// Select with options
{
  "form_id": 42, "type": "select", "label": "Pick a colour",
  "options": {
    "choices": [{"label": "Red"}, {"label": "Green"}, {"label": "Blue"}],
    "multiple": false,
    "style": "modern"
  }
}
```

### edit-field

Partial merge update on one existing field. Required: `form_id`, `field_id`. Pass only the keys you want to change — anything you don't pass keeps its current value. The response carries `updated[]` listing the keys that actually changed (each `options.<key>` mutation shows as `"options.<key>"`).

**Type cannot be changed.** Use `delete-field` + `add-field` if a field needs to change type — its stored value semantics don't survive a type switch.

```jsonc
// Rename and make optional
{ "form_id": 42, "field_id": 3, "label": "Full name", "required": false }

// Grow the choice list on a select (merge — replaces the choices key flat)
{ "form_id": 42, "field_id": 5, "options": { "choices": [
  {"label": "Red"}, {"label": "Green"}, {"label": "Blue"}, {"label": "Yellow"}
]}}
```

### delete-field

Remove one field. Required: `form_id`, `field_id`. The form's `field_id` counter is **not** decremented — new fields always get the next monotonic id, never a recycled one. Submissions previously stored for this field (Pro) are not modified; they survive in the entries table for historical reference.

```jsonc
{ "form_id": 42, "field_id": 5 }
```

### reorder-fields

Reorder the fields by passing a complete permutation of the current ids. Required: `form_id`, `order` (list of ids in the desired top-to-bottom sequence). The list **must** contain every existing field id exactly once — missing, extra, or duplicate ids → `wpforms_invalid_order`. Use `add-field` / `delete-field` for membership changes, then reorder.

```jsonc
{ "form_id": 42, "order": [3, 1, 2, 5] }
```

### Recipe: build a contact form from scratch

```text
1. create-form              → form_id
2. add-field text           → name (required)
3. add-field email          → email (required)
4. add-field textarea       → message
5. reorder-fields           → optional, only if the agent wants a different order
6. edit-notification id=1   → set subject / sender_name to taste
7. create-notification      → second copy to a sales address if needed
8. edit-confirmation id=1   → custom thank-you message (or switch to redirect)
9. edit-form-settings       → submit_text, ajax_submit, anti_spam
```

## Notifications

A notification is the email WPForms sends when a submission arrives. Every form gets one default notification on creation; you can add more, edit any of them, or remove non-last entries.

| Ability | What it does |
|---|---|
| `wpforms-list-notifications` | All notifications keyed by string-int id. |
| `wpforms-create-notification` | Append one. Required: form_id, notification_name, email. |
| `wpforms-edit-notification` | Partial merge. Required: form_id, notification_id. |
| `wpforms-delete-notification` | Remove. Refuses to delete the last surviving notification. |

**Smart tags** are accepted verbatim — WPForms resolves them at submission time:
- `{admin_email}` — the WP admin email
- `{site_name}` — the WP site title
- `{all_fields}` — every field/value rendered as a labelled list (the default message body)
- `{field_id="N"}` — a specific field's submitted value (e.g. `{field_id="3"}` for the email field at id 3)

Multiple recipients on a single notification: separate emails with commas in the `email` field (`"admin@example.com, sales@example.com"`).

`file_upload_attachments: true` attaches uploads from file-upload fields to the email — **Pro only**, ignored on Lite.

```jsonc
// Default-shaped notification
{ "form_id": 42, "notification_name": "Default",
  "email": "{admin_email}", "subject": "New entry on {site_name}",
  "message": "{all_fields}" }

// Routed copy
{ "form_id": 42, "notification_name": "Sales lead",
  "email": "sales@example.com, ops@example.com",
  "subject": "Lead from {field_id=\"2\"}",
  "sender_name": "{site_name}",
  "replyto": "{field_id=\"2\"}" }
```

The last-survivor guard: trying to delete the only notification returns `wpforms_last_notification`. Edit it instead, or add a new one before deleting the old.

## Confirmations

A confirmation is what the user sees after submitting — a thank-you message, a redirect to a WP page, or a redirect to an external URL.

| Ability | What it does |
|---|---|
| `wpforms-list-confirmations` | All confirmations keyed by string-int id. |
| `wpforms-create-confirmation` | Append one. Required: form_id, name, type. |
| `wpforms-edit-confirmation` | Partial merge. Required: form_id, confirmation_id. |
| `wpforms-delete-confirmation` | Remove. Refuses to delete the last surviving confirmation. |

Three confirmation types, each with its own required key:

| `type` | Required key | Notes |
|---|---|---|
| `message` | `message` | HTML allowed. Pair with `message_scroll: true` to auto-scroll. |
| `page` | `page` (WP post id) | The target post must exist — non-existent ids → `wpforms_invalid_input`. |
| `redirect` | `redirect` (URL) | URL is sanitised with `esc_url_raw` on the way in. |

```jsonc
// In-place thank-you
{ "form_id": 42, "name": "Thanks", "type": "message",
  "message": "<p>Thank you — we'll get back to you shortly.</p>",
  "message_scroll": true }

// Redirect to a WP page
{ "form_id": 42, "name": "Go to thanks page", "type": "page", "page": 87 }

// External redirect
{ "form_id": 42, "name": "Off to checkout", "type": "redirect",
  "redirect": "https://checkout.example.com/sign-up" }
```

**Type cannot be changed via edit.** A `message` confirmation has different meaningful keys than a `redirect` — to switch type, delete and create fresh. Passing a `redirect` key to a `message`-typed confirmation returns `wpforms_invalid_input`. Same for the other type-mismatches.

## Form-level settings

`wpforms-edit-form-settings` is the sharp editor for the most-used form-level knobs. Use it instead of `edit-form.settings` when you only need to tweak common stuff (typed schema, clearer validation):

| Input | Stored as | Notes |
|---|---|---|
| `submit_text` | string | Submit button label. |
| `submit_text_processing` | string | Label shown while submitting. |
| `form_class` | string | Extra CSS classes on the `<form>` wrapper. |
| `anti_spam` | `"0"` / `"1"` | Modern token-based check; on by default. |
| `honeypot` | `"0"` / `"1"` | Legacy honeypot; usually unnecessary once anti_spam is on. |
| `ajax_submit` | `"0"` / `"1"` | Submit without a full page reload. |
| `dynamic_population` | `"0"` / `"1"` | URL query params can pre-fill matching fields. |
| `store_spam_entries` | `"0"` / `"1"` | Persist spam-flagged entries (Pro only). |

Booleans are accepted as actual JSON booleans but stored as the `"0"` / `"1"` strings WPForms reads. `updated[]` reports the keys that actually changed; an empty list means a no-op.

For anything not in the table above, fall back to `edit-form` with a `settings` merge — the same partial-merge semantics, just without the typed shape guarantees of this sharp editor.

## Deleting a form

`novamira/wpforms-delete-form` trashes by default (status flips to `trash` — restorable from the WPForms admin). Pass `force: true` for a permanent delete. The response carries `status_was` and `status_now` so the caller can confirm what happened. A second call on an already-trashed form is a no-op when `force` is false; with `force: true` the row goes away.

```jsonc
{ "form_id": 42 }                  // trash (restorable)
{ "form_id": 42, "force": true }   // permanent delete
```

## Duplicating a form

`novamira/wpforms-duplicate-form` clones fields + settings + notifications + confirmations + payments + meta. The new form starts as a **draft** unless `status: "publish"` is passed (a guardrail against accidentally publishing two live copies). Title defaults to `"<original> (Copy)"`; pass `title` to override. Entries (Pro) are NOT cloned — only the form definition.

```jsonc
{ "form_id": 42 }                                        // draft, " (Copy)" suffix
{ "form_id": 42, "title": "Contact - landing", "status": "publish" }
```

## Things to do via `novamira/execute-php` (not via a dedicated ability)

The project rule is **simple things → `novamira/execute-php` + a snippet here, NOT a new ability**. Every ability registered consumes the agent's context window via its MCP schema, so the dedicated tools should be reserved for operations whose validation, sanitization, or multi-branch logic is worth the discoverability cost. One-off reads, simple counters, ad-hoc filters that don't justify a new tool — those go through `execute-php` directly.

Three patterns that come up often:

**1. Count forms by status (no need for a dedicated count-forms ability)**

```php
return (new WP_Query([
    'post_type'      => 'wpforms',
    'post_status'    => ['publish', 'draft', 'pending', 'private'],
    'fields'         => 'ids',
    'posts_per_page' => -1,
    'no_found_rows'  => false,
]))->found_posts;
```

**2. Bulk soft-delete a list of form ids (instead of N `delete-form` calls)**

```php
$ids = [42, 99, 105];
$deleted = [];
foreach ($ids as $id) {
    $r = wp_delete_post((int) $id, false);
    if ($r) { $deleted[] = (int) $id; }
}
return $deleted;
```

**3. Quick stat on entries this week (Pro-only, no dedicated stats ability)**

```php
if (!\Novamira\Pro\Abilities\WPForms\wpforms_pro_active()) {
    return ['skipped' => 'WPForms Pro not active'];
}
global $wpdb;
$since = gmdate('Y-m-d H:i:s', time() - 7 * 86400);
return (int) $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM {$wpdb->prefix}wpforms_entries WHERE date_modified >= %s",
    $since,
));
```

**4. Find forms whose title contains a string (without going through list-forms search)**

```php
$needle = 'contact';
return get_posts([
    'post_type'      => 'wpforms',
    'post_status'    => 'any',
    's'              => $needle,
    'posts_per_page' => 20,
    'fields'         => 'ids',
]);
```

**5. Bulk mark every spam entry as read (Pro)** — useful before a cleanup pass

```php
if (!\Novamira\Pro\Abilities\WPForms\wpforms_pro_active()) { return 0; }
global $wpdb;
return (int) $wpdb->query(
    "UPDATE {$wpdb->prefix}wpforms_entries SET viewed = 1 WHERE status = 'spam' AND viewed = 0"
);
```

When in doubt — if the operation is a single SQL or a single WP_Query call with no agent-facing validation contract, prefer `execute-php` over registering a new tool.

## Gotchas

- **JSON round-trip is mandatory.** The form definition lives inside `post_content`. Direct `update_post_meta()` or raw `wp_update_post(['post_content' => ...])` calls would corrupt it. The abilities call `wpforms_decode()` / `wpforms_encode()` for you; never bypass them.
- **`entry_count` is null on Lite.** WPForms Lite has no entries table — submissions go straight to the admin email. Treat null and 0 differently in any UI that displays it.
- **Trash and auto-draft on read paths.** `get-form` / `edit-form` reject `trash` and `auto-draft` posts with `wpforms_form_unavailable`. `delete-form` accepts a trashed form (so you can force-delete it).
- **`status` filter on list-forms.** The default `"any"` excludes trash. Pass `status: "trash"` explicitly to find trashed forms (e.g. to restore them).
- **Field ids are STRING keys in the fields map.** They look like numeric ids ("3", "7", "12") but JSON encodes them as strings. Sort numerically when displaying; treat as strings when looking up.
- **Notifications / confirmations are objects keyed by id, not arrays.** The keys are stringy integers that WPForms increments as the user adds rules. Don't assume sequential ids when reading.
- **REPLACE vs MERGE.** `edit-form.settings` and `edit-form.meta` MERGE. `edit-form.notifications`, `confirmations`, and `fields` REPLACE. Read first, mutate, write back when amending an existing map.
- **Duplicate defaults to draft.** This is intentional — duplicating a published form silently into another published form is almost always wrong (two live copies, two notification streams). Pass `status: "publish"` to opt in.
- **Capability layer.** Form CRUD requires `create_forms` / `edit_forms` / `delete_forms`; entries (Pro) need the matching `*_entries` caps. `novamira_permission_callback` already gates every ability — but a user with `edit_posts` but no WPForms caps will be rejected with the standard Novamira permission error.
- **Read paths are per-form gated too** *(round-1 audit fix)*. `get-form`, `list-confirmations`, `list-notifications` now run `wpforms_view_forms` per-form (not just the global Novamira gate) — so team-mode installs that restrict view-access at the form level are honoured on reads, not only on writes.
- **`list-entries` requires `form_id`** *(round-1 audit fix)*. Cross-form listing is intentionally not exposed — without a concrete form id the per-form capability filter wouldn't fire. Omitting it returns `wpforms_invalid_input`.
- **`delete-field` is idempotent** *(round-1 audit fix)*. Calling it twice with the same `field_id` returns success on both calls — the second one with `deleted: false, remaining: <count>` instead of a 404. The annotation `idempotent: true` finally matches the runtime contract.
- **Title + description are CRLF- and HTML-stripped** *(round-1 audit fix)*. `create-form` and `edit-form` run the inputs through `sanitize_text_field` + a CRLF strip before they reach the default notification subject — closes the email-header-injection path through the form title.
- **`edit-form.fields` is validated before swap** *(round-1 audit fix)*. A malformed map (missing `id` / `type`, key/id mismatch, or a Pro-only field type on a Lite install) is rejected with `wpforms_invalid_input` before the blob is touched. Previously the blob would land and crash the WPForms renderer at preview time.
- **Trashed / partial entries are 404 on `get-entry`** *(round-1 audit fix)*. `get-entry` now mirrors `get-form`'s "reject non-published states" semantics — trash and partial entries return `wpforms_entry_not_found`. Spam entries stay accessible since the WPForms admin surfaces them for review. Use `list-entries` with the `status` filter when you intentionally want the rejected ones.

## Entries (Pro-only)

An entry is one submission of a form. Five abilities cover read / get-detail / triage / edit / delete. **All five register only when `check-setup.pro === true`** — on Lite they are absent from the tool catalog and from `discover-abilities`. On a Pro install they replace the Lite stub story end-to-end.

| Ability | What it does |
|---|---|
| `wpforms-list-entries` | Paginated minimal rows (entry_id, form_id, status, viewed, starred, date, ip, user_id). Filterable by `form_id`, `status` (publish/spam/trash/partial), `date_after` / `date_before`. |
| `wpforms-get-entry` | Single entry + decoded field values + optional meta. `max_field_bytes` cap protects against large textarea/richtext/file-upload values flooding the agent context. |
| `wpforms-edit-entry` | Per-field update on an editable field type. Rejects non-editable types (payment, signature, captcha, etc.) with `wpforms_field_not_editable`. |
| `wpforms-delete-entry` | Permanent delete — entries have no trash state. Per-field rows and meta cascade with the entry. |
| `wpforms-mark-entry` | Toggle `viewed` (flag=`read`) or `starred` flags without touching field values. Idempotent. |

### Entry data model

The Pro entry handler stores submissions in three tables:

- `wp_wpforms_entries` — one row per entry, JSON-encoded `fields` + `meta` blobs.
- `wp_wpforms_entry_fields` — one row per field per entry (used for filtering / search).
- `wp_wpforms_entry_meta` — per-entry meta key/value (used by payments, custom logging).

`list-entries` and `get-entry` read from the consolidated `wp_wpforms_entries` row. `edit-entry` writes back the full JSON `fields` blob; the per-field row updates are handled by WPForms internally.

**Status semantics:**
- Empty string (`""`) — normal entry. Exposed as `"publish"` in our schema enum (renamed for clarity).
- `"spam"` — flagged by anti-spam.
- `"trash"` — soft-deleted from admin UI.
- `"partial"` — abandoned (saved-and-continue, lead-form drop-off).

### Editable field types

`edit-entry` mirrors `WPForms_Entry_Handler::get_editable_field_types()`:

`checkbox`, `email`, `name`, `number`, `number-slider`, `radio`, `select`, `text`, `textarea`, `address`, `date-time`, `phone`, `rating`, `richtext`, `url`, `file-upload`, `camera`

Out of scope: payment-*, signature, credit-card, custom-captcha, entry-preview, layout, repeater, html, content, divider, pagebreak, hidden, gdpr-checkbox, internal-information.

### Workflow examples

```text
# Triage inbox: mark new entries as read after reviewing
1. list-entries form_id=42 status=publish     → entry rows
2. (for each) get-entry entry_id=NN           → inspect field values
3. mark-entry entry_id=NN flag=read value=true

# Correct a typo a user made
1. get-entry entry_id=NN                      → see field id of the email
2. edit-entry entry_id=NN fields={"3": "fixed@example.com"}

# Clear spam
1. list-entries form_id=42 status=spam        → spam rows
2. (for each) delete-entry entry_id=NN

# Star high-value leads
1. list-entries form_id=42                    → all
2. mark-entry entry_id=NN flag=starred value=true
```

### Per-field byte cap

`get-entry.max_field_bytes` (default 4096, max 65536) protects the agent from a runaway textarea or richtext value. When a value exceeds the cap:

- `fields[field_id].value` contains the truncated head (UTF-8 safe via `mb_strcut`)
- `fields[field_id].value_truncated` is `true`
- `fields[field_id].value_original_bytes` carries the full original byte length
- `truncated_fields[]` at the top level lists the affected field ids

Raise `max_field_bytes` only when the agent genuinely needs the full value — file-upload metadata and payment receipts can easily exceed 10 KiB per field.

### Capability layer

Entry abilities respect the same `novamira_permission_callback` gate as the rest of the specialization. WPForms additionally checks `view_entries` / `edit_entries` / `delete_entries` internally on its handler methods, so a user without those caps will see the row but get a `wpforms_*_failed` on write.

## What this skill covers today (S1 + S2 + S3 + S4 + S5)

| Surface | Abilities | Status |
|---|---|---|
| Setup discovery | `wpforms-check-setup` | ✓ ready |
| Form discovery | `wpforms-list-forms`, `wpforms-get-form` | ✓ ready |
| Field-type catalog | `wpforms-get-field-type-schema` | ✓ ready |
| Form CRUD | `wpforms-create-form`, `wpforms-edit-form`, `wpforms-delete-form`, `wpforms-duplicate-form` | ✓ ready |
| Field management | `wpforms-add-field`, `wpforms-edit-field`, `wpforms-delete-field`, `wpforms-reorder-fields` | ✓ ready |
| Notifications | `wpforms-list-notifications`, `wpforms-create-notification`, `wpforms-edit-notification`, `wpforms-delete-notification` | ✓ ready |
| Confirmations | `wpforms-list-confirmations`, `wpforms-create-confirmation`, `wpforms-edit-confirmation`, `wpforms-delete-confirmation` | ✓ ready |
| Form-level settings | `wpforms-edit-form-settings` | ✓ ready |
| Entries (Pro) | `wpforms-list-entries`, `wpforms-get-entry`, `wpforms-edit-entry`, `wpforms-delete-entry`, `wpforms-mark-entry` | ✓ ready (Pro-gated at loader) |
| Cross-plugin migration | `wpforms-get-form-canonical` | ✓ ready — see "Cross-plugin migration mapping" + `form-model-migration` skill |

## Conventions

- **Slug pattern:** `novamira/wpforms-<verb>-<object>` — see the per-ability descriptions for the supported verbs.
- **Compact list / full get:** `list-forms` returns minimal rows; `get-form` returns the full decoded shape with opt-in slimming.
- **Per-section opt-out on get-form:** every block is independently include/exclude-able to keep the agent in control of payload size.
- **Pro detection is centralised** in `wpforms-check-setup`. Don't try to infer Pro from anything else (entry counts, feature flags) — `check-setup.pro` is the single source of truth.
- **Errors are namespaced.** `wpforms_not_active`, `wpforms_pro_required`, `wpforms_invalid_input`, `wpforms_form_not_found`, `wpforms_form_unavailable`, `wpforms_unknown_type`, `wpforms_entry_not_found`. Any client surfacing these to a user can match on the code prefix.

## Minimum versions

- WPForms (Lite or Pro): **1.8.0** — `NOVAMIRA_PRO_WPFORMS_MIN_VERSION` in `novamira-pro.php`.

Sites running an older WPForms release see `wpforms_not_active` from every ability except `check-setup` (which still reports the install state so the agent can explain the gap to the user). `check-setup.active` reflects whether the install meets BOTH "plugin loaded" AND "version ≥ 1.8.0" — a 1.7.x install registers as `active: false` here even though WordPress shows the plugin as active.

## Cross-plugin migration mapping

When the user wants to migrate a WPForms form **to** or **from** another form plugin (Contact Form 7, Gravity Forms, Fluent Forms, Formidable Forms), activate the `form-model-migration` skill — it drives the seven-phase workflow (audit → plan → execute → verify) using `form-model-schema` as the canonical intermediate format. This section is the plugin-specific mapping reference the migration skill loads alongside its workflow.

### Canonical snapshot — preferred reader

`wpforms-get-form-canonical id=<id>` is the dedicated reader for migration work. It returns the form already mapped to the `form-model-schema` vocabulary (17 canonical types) plus the relevant `warnings[]`, saving the agent the manual translation step. The mapping is direct from the structured WPForms JSON blob (no string parsing needed, unlike CF7), so this ability is mostly a translator + warnings emitter.

For interactive single-form inspection unrelated to migration, keep using `wpforms-get-form` (full WPForms-native shape) — `wpforms-get-form-canonical` is purpose-built for the canonical pipeline and applies the same `max_field_bytes` truncation contract.

### WPForms field type → canonical type

| WPForms type | Edition | Canonical type | Notes |
|---|---|---|---|
| `text` | Lite | `text` | `limit_enabled` + `limit_mode: characters` → `validation.max_length`. |
| `textarea` | Lite | `textarea` | Same character-limit mapping as text. |
| `email` | Lite | `email` | |
| `number` | Lite | `number` (subtype `decimal`) | `min` / `max` → `validation`. |
| `number-slider` | Lite | `number` (subtype `integer`) | `min` / `max` / `step` → `validation`. |
| `select` | Lite | `select` | `multiple: 1` → `cardinality: multi`. |
| `checkbox` | Lite | `checkbox` | Always `cardinality: multi`. `choices[]` flattened to `[{value,label}]`. |
| `radio` | Lite | `radio` | `cardinality: single` always. |
| `name` | Lite | `group` | Composite — `warning: informational` (sub-field structure first/middle/last is plugin-specific). |
| `gdpr-checkbox` | Lite | `consent` | |
| `hidden` | Lite | `hidden` | |
| `address` | Pro | `group` | Composite — `warning: informational`. |
| `date-time` | Pro | `date` / `time` / `datetime` | Picked from the `format` option. |
| `phone` | Pro | `tel` | |
| `richtext` | Pro | `textarea` (format `rich`) | |
| `url` | Pro | `url` | |
| `file-upload` | Pro | `file` | `extensions` → `validation.accepts` (MIME-mapped). `max_size` (MiB) → `validation.max_size_bytes`. `max_file_number > 1` → `cardinality: multi`. |
| `camera` | Pro | `file` | Same as `file-upload`. |
| `password` | Pro | `text` (format `password`) | |
| `repeater` | Pro | `group` (`cardinality: multi`) | The closest canonical mapping for a multi-field repeating row. |

### WPForms types skipped from canonical fields[]

These never produce a canonical field — only a warning entry so the migration plan tells the user what was lost:

| WPForms type | Severity | Reason |
|---|---|---|
| `divider`, `html`, `content`, `internal-information`, `entry-preview`, `layout` | `informational` | Rendering / metadata only, not a data field. |
| `pagebreak` | `structural` | Multi-page form collapses to a single-page canonical. |
| `rating`, `signature`, `credit-card`, `custom-captcha`, `payment-single`, `payment-multiple`, `payment-checkbox`, `payment-select`, `payment-total` | `structural` | No canonical equivalent — payment / signature / rating / anti-spam semantics are plugin-specific. |

### Smart-tag normalisation

WPForms uses `{field_id="N"}` smart tags in mail subject / body to reference field values. The canonical reader rewrites them to `[field-name]` (the CF7 syntax, adopted as canonical) using `sanitize_title(label)` for the name. Tags whose id does not resolve to a known field stay verbatim.

Site-global smart tags (`{admin_email}`, `{site_name}`, `{all_fields}`, `{date}`, `{user_ip}`, `{user_name}`, `{user_email}`) are preserved verbatim — the target plugin must provide its own equivalents on import.

### Notifications mapping

WPForms supports N notifications natively (`settings.notifications`). The canonical reader picks the lowest-id notification as `mail_config{}` (the primary / admin mail) and emits the rest in `notifications[]`. Each notification carries `subject`, `sender` (from `sender_address`), `recipient` (from `email`), `body` (from `message`), `use_html: true` (WPForms always sends HTML), and `attachments: []` (file-upload attachment toggles are stored per-notification but resolved at submission time, not in the schema).

### Confirmations — out of canonical scope

WPForms confirmations (success message / page / redirect) have no canonical equivalent — canonical only models the schema of the form itself, not the post-submission UX. The complete `settings.confirmations` block is echoed verbatim into `meta.wpforms_confirmations` for lossless round-trip when both source and target are WPForms; for cross-plugin migrations the user must re-configure the confirmation on the target.

When the form ships **more than one** confirmation, the reader emits a `warning: informational` so the user knows there is non-trivial behaviour the target plugin will not inherit automatically.

### Conditional logic

WPForms stores per-field conditional rules as `conditional_logic: "1"` + `conditionals[]` array with `target_field_id`, `operator` (`show_if` / `hide_if`), `logic` (`all` / `any`), and `conditions[{field, op, value}]`. The canonical reader maps these to `conditional_rules[]` at the form root, rewriting numeric field ids to canonical names through the same `sanitize_title(label)` lookup used for smart tags.

Complex nested AND/OR groups inside a single rule, calculated-value triggers, and regex value matches are flattened with possible semantic loss — the affected field carries an informational warning so the user can verify on the target.

### Quirks specific to WPForms

- **Lite vs Pro gate.** When the source is Lite but a Pro-only field type is encountered (because the form was authored under Pro and the site downgraded), the canonical reader still maps the field but emits an informational warning. The migration target must also be Pro for a clean round-trip.
- **Reserved field names on write side.** Other plugins reserve names (`entry_id`, `form_id`, `wpforms_*`, `frm_*`, `gform_*`); the WPForms canonical does not pre-filter — the reserved-name check happens at the target's `<plugin>-create-form` call (Safety rule 8 of `form-model-migration`).
- **Anti-spam config** (`anti_spam`, `honeypot` in settings) is site-global per WPForms install — the canonical reader emits an informational warning when it's enabled, but never tries to migrate the actual token.
- **Multi-page forms** (any `pagebreak` field) collapse to single-page canonical with a structural warning. The target plugin needs manual page-break re-insertion if its model supports multi-page forms.
- **Composite fields** (`name`, `address`) collapse to canonical `group` — the sub-field structure (first/middle/last for name; address1/city/state/postal for address) is plugin-specific and gets dropped with an informational warning. The user re-creates the composite on the target.
- **Truncation contract** mirrors the rest of the integration: `mail_config.body` and each `notifications[].body` are capped at `max_field_bytes` (default 8192) with `*_truncated` flags. Pass `max_field_bytes: 0` before an execute-mode migration so canonical bodies are not truncated.

### When to escalate to the migration skill

If the user asks any of:
- "Voglio passare da WPForms a CF7 / Gravity / Fluent / Formidable" (or vice-versa)
- "Migra questo form WPForms in <other plugin>"
- "Cosa perdo se sposto questo form su <other plugin>?"
- "Riscrivi questo form WPForms come <other plugin>"

→ activate `form-model-migration` (which loads `form-model-schema` plus the source and target integration skills) and follow the seven-phase workflow. Do NOT attempt ad-hoc canonical translation outside that pipeline.
