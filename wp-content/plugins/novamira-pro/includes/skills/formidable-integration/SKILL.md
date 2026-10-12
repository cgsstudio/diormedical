---
name: formidable-integration
description: Activate when an agent needs to operate on Formidable Forms via the novamira/formidable-* abilities — surveying installed forms, building new forms with fields, managing entries (submissions), reading payment ledger data from Stripe Lite / Pro, organising forms into Pro Applications, or configuring notifications and on-submit confirmation flows. Loads on Formidable + Formidable Pro installs (free version 6.0+ required; Pro is optional, gates specific surfaces). For cross-plugin migrations (CF7 / WPForms / Gravity / Fluent / Ninja ↔ Formidable), the `formidable-get-form-canonical` reader plugs the Formidable shape into the `form-model-schema` canonical vocabulary used by the `form-model-migration` workflow.
---

# Formidable Forms integration

38 abilities for the Formidable Forms WordPress plugin (free 6.0+ and Pro). Surface covers form CRUD, field CRUD, entry CRUD, notifications, on-submit confirmation, form settings, styles, Pro applications, and Stripe Lite + Pro payment ledger.

## When to use

Activate when the user asks to:

- enumerate / inspect / create / edit / delete Formidable forms (`frm_form`s in `wp_frm_forms`)
- add / edit / delete / reorder fields on a form
- read or modify form notifications (email actions)
- configure the on-submit behaviour of a form (message / redirect / page)
- read or edit Formidable submissions (entries in `wp_frm_items`)
- bind / unbind a form to a Pro Application
- inspect Stripe Lite / Pro transactions or subscriptions
- bind a Formidable style to a form

**Do NOT** use this skill for:

- generic WordPress post / page management — use the `novamira/*-post` and `novamira/*-page` abilities from the base plugin
- field-type extension via custom-code (Formidable has its own filter chain — use `novamira/execute-php` with the FrmField API directly)
- gateway-side Stripe / PayPal lifecycle operations (create charge, capture, refund) — the cancel-subscription ability flips the local ledger only; the upstream API call belongs to the Pro gateway controllers and runs through `novamira/execute-php`

## Discover-before-act ladder

Every Formidable session starts here:

1. **`novamira/formidable-check-setup`** — confirms Formidable is active, returns version + Pro state + payments_supported flag + per-user capability map. If this ability is missing from your tool list, Formidable itself is not installed.
2. **`novamira/formidable-list-forms`** — pick a form id (or form_key) to operate on. Default filter returns published forms at all nesting levels.
3. **`novamira/formidable-get-form`** — read the full form payload (settings + fields). Use the `include` filter (`['settings']` or `['fields']`) to skip subsystems you don't need.
4. **`novamira/formidable-get-field-types-schema`** — required before `add-field` so you can validate the `type` slug. Pro types are mixed in when Pro is active; add-on upsell types and `hide=true` internal types are filtered out.

## Domain model

Formidable's data lives across several tables:

| Entity | Table | Identifier |
|---|---|---|
| Form | `wp_frm_forms` | `id` (int) OR `form_key` (slug) |
| Field | `wp_frm_fields` | `id` (int) OR `field_key` (slug) |
| Entry (submission) | `wp_frm_items` | `id` (int) OR `item_key` (slug) |
| Entry meta value | `wp_frm_item_metas` | `(item_id, field_id)` |
| Form action (notification / on-submit / webhook / payment) | `wp_posts` with `post_type='frm_action'`, `menu_order=form_id`, `post_excerpt=<type>` | `wp_posts.ID` |
| Form style | `wp_posts` with `post_type='frm_styles'` | `wp_posts.ID` |
| Pro Application | `wp_terms` in `frm_application` taxonomy | `term_id` |
| Transaction | `wp_frm_payments` (Stripe Lite + Pro) | `id` |
| Subscription | `wp_frm_subscriptions` (Stripe Lite + Pro) | `id` |

## Ability catalog (38 abilities)

### Sprint 1 — discovery (4)

- `novamira/formidable-check-setup` — readonly, idempotent. Always your first call.
- `novamira/formidable-list-forms` — paginated, filters by status / is_template / parent_form_id / search.
- `novamira/formidable-get-form` — full form payload with `truncations[]` reporting per-string caps.
- `novamira/formidable-get-form-canonical` — Phase 2 of `form-model-migration`. Returns the form mapped to the plugin-agnostic 17-type canonical vocabulary so an agent can translate it to any of the other supported form plugins.
- `novamira/formidable-get-field-types-schema` — catalog of `type` slugs add-field accepts.

### Sprint 2 — form CRUD (3)

- `novamira/formidable-create-form` — `title|name` required. `source_form_id` opt for duplicate path.
- `novamira/formidable-edit-form` — partial merge on title/slug/description/status. REJECTS `fields[]` input (use add-/edit-/delete-field).
- `novamira/formidable-delete-form` — `force=false` soft-deletes (status=trash); `force=true` hard-deletes + cascades Repeater children + entries.

### Sprint 3 — field CRUD (3)

- `novamira/formidable-add-field` — required: form_id + type (catalog slug).
- `novamira/formidable-edit-field` — partial merge. `field_options` REPLACES the whole per-type map.
- `novamira/formidable-delete-field` — idempotent. **Does NOT cascade Repeater child sub-forms** — see Gotchas.

### Sprint 4 — notifications + on-submit (6)

- `novamira/formidable-list-notifications` / `get-notification` / `create-notification` / `edit-notification` / `delete-notification` — full CRUD on email actions. Header-injection validated.
- `novamira/formidable-set-on-submit-action` — PUT-style. type `message` | `redirect` | `page`.

### Sprint 5 — settings + actions + styles (6)

- `novamira/formidable-edit-form-settings` — whitelist of safe options. Rejects `custom_style` (use apply-style).
- `novamira/formidable-list-form-actions` / `get-form-action` / `delete-form-action` — generic over ALL action types (email, wppost, webhook, mailchimp, …). on_submit protected.
- `novamira/formidable-list-styles` / `apply-style` — list `frm_styles` posts and bind one to a form.

### Sprint 6 — entries (5)

- `novamira/formidable-list-entries` / `get-entry` / `edit-entry` / `delete-entry` / `set-entry-status` — full CRUD on submissions. Item meta keyed by `field_key` or `field_id`.

### Sprint 7 — applications (Pro, 6)

- `novamira/formidable-list-applications` / `get-application` / `create-application` / `apply-form-to-application` / `clear-form-from-application` / `delete-application` — taxonomy-backed organisation surface.

### Sprint 8 — payments (Stripe Lite + Pro, 5)

- `novamira/formidable-list-transactions` / `get-transaction` / `list-subscriptions` / `get-subscription` — read-only ledger access. Available whenever the `wp_frm_payments` table is provisioned (free Stripe Lite OR Pro).
- `novamira/formidable-cancel-subscription` — Pro-only. Local ledger flip — NOT a gateway API call.

## Conventions

### Identifiers

- Numeric `id` (int) is the canonical identifier. Slug-style `form_key` / `field_key` / `item_key` are surfaced for human readability and accepted on read paths where the slug-vs-id ambiguity can be resolved.
- Formidable auto-suffixes slug collisions silently — ALWAYS read the actual key from the response, never assume the slug you passed survived intact.

### Aliases (WordPress-native ↔ Formidable-native)

`create-form` and `edit-form` accept BOTH the short and the WP-native name for these fields. Pass whichever feels natural:

| Short | Formidable-native | Column |
|---|---|---|
| `title` | `name` | `wp_frm_forms.name` |
| `slug` | `form_key` | `wp_frm_forms.form_key` |
| `description` | `description` | `wp_frm_forms.description` |
| `status` | `status` | `wp_frm_forms.status` ∈ {published, draft, trash} |

The short form wins if both are supplied.

### Output shape patterns

- `list-*` returns `{<entity_plural>: [...compact rows], pagination: {...}}` with `pagination.page` / `per_page` / `total` / `total_pages` / `truncated`. Default per_page=50, max 500, hard pool cap 5000.
- `get-*` returns `{<entity_singular>: {...full payload}, truncations: [...]}` when string truncation applies.
- Write paths return the resulting compact row plus a `changed[]` list of fields that were actually written.
- Idempotent re-calls return a `*=false` flag (`applied`, `cleared`, `deleted`, `changed`) rather than 404.

### Error codes

| Code | Status | Meaning |
|---|---|---|
| `formidable_not_active` | 400 | Formidable plugin missing / below min version |
| `formidable_pro_required` | 400 | Pro-only ability called on a non-Pro install |
| `formidable_payments_not_supported` | 400 | wp_frm_payments table not provisioned |
| `formidable_invalid_input` | 400 | Caller-supplied value failed validation |
| `formidable_capability_missing` | 403 | Current user lacks the required Formidable cap |
| `formidable_form_not_found` | 404 | form_id resolves to no row |
| `formidable_form_unavailable` | 400 | form is in the trash state (write paths reject by default) |
| `formidable_field_not_found` | 404 | field_id / field_key resolves to no row on the form |
| `formidable_field_form_mismatch` | 400 | field exists but belongs to a different form |
| `formidable_entry_not_found` | 404 | entry_id resolves to no row |
| `formidable_notification_not_found` | 404 | action_id is not a `frm_action` post |
| `formidable_notification_type_mismatch` | 400 | action exists but is not type=email |
| `formidable_notification_form_mismatch` | 400 | action exists but belongs to a different form |
| `formidable_action_not_found` | 404 | generic-action surface — action_id is not a `frm_action` post |
| `formidable_action_form_mismatch` | 400 | action exists but belongs to a different form |
| `formidable_application_not_found` | 404 | application term not found |
| `formidable_transaction_not_found` / `formidable_subscription_not_found` | 404 | payment id not found |
| `formidable_invalid_email_header` | 400 | CR / LF / NULL in an email header value |
| `formidable_invalid_url_scheme` | 400 | URL scheme outside `http/https/mailto/tel` |

## Workflows

### W1 — Inspect what's installed

```
1. novamira/formidable-check-setup
   → reads plugin_active, min_satisfied, pro_active, pro_min_satisfied,
     payments_supported, capabilities, available_field_types
2. novamira/formidable-list-forms
   → enumerate forms with default filters
3. (per-form) novamira/formidable-get-form { form_id, include: ['settings','fields'] }
   → inspect the full payload
```

### W2 — Build a contact form from scratch

```
1. novamira/formidable-get-field-types-schema
   → confirm the type slugs you'll use (text, email, textarea, submit)
2. novamira/formidable-create-form { title: "Contact" }
   → returns form_id (call it FID below)
3. novamira/formidable-add-field { form_id: FID, type: "text", name: "Your name", required: true }
4. novamira/formidable-add-field { form_id: FID, type: "email", name: "Email", required: true }
5. novamira/formidable-add-field { form_id: FID, type: "textarea", name: "Message", required: true }
6. novamira/formidable-create-notification { form_id: FID, name: "Admin alert",
     email_to: "[admin_email]",
     email_subject: "New message from {field_id=N}",
     email_message: "...field placeholders here..." }
7. novamira/formidable-set-on-submit-action { form_id: FID, type: "message",
     message: "Thanks! We'll get back to you within 24 hours." }
```

### W3 — Clone an existing form and tweak it

```
1. novamira/formidable-list-forms { search: "Contact" }
   → find the source form_id (SRC)
2. novamira/formidable-create-form { title: "Contact (long form)", source_form_id: SRC }
   → returns the new form id (NEW) and duplicated_from = SRC
   → fields + Repeater child sub-forms are copied via FrmForm::duplicate
3. novamira/formidable-add-field { form_id: NEW, type: "textarea", name: "Tell us more" }
   → add the extra field
```

### W4 — Export entries to JSON

```
1. novamira/formidable-list-entries { form_id: FID, per_page: 500 }
2. for each entry.id in the response:
     novamira/formidable-get-entry { entry_id: id, include_meta: true }
   → entry.meta is keyed by field_key so the export is human-friendly
```

### W5 — Mark a saved-draft submission as completed

```
1. novamira/formidable-list-entries { form_id: FID, is_draft: true }
   → enumerate drafts only
2. novamira/formidable-set-entry-status { entry_id: id, is_draft: false }
```

### W6 — Apply a style across multiple forms

```
1. novamira/formidable-list-styles
   → pick the style id (SID)
2. for each form_id in scope:
     novamira/formidable-apply-style { form_id, style_id: SID }
   → re-calls on the same binding are no-op (applied=false)
```

### W7 — Audit payment activity for a specific form

```
1. novamira/formidable-list-transactions { paysys: "stripe", status: "complete" }
   → reads the wp_frm_payments ledger (free Stripe Lite OR Pro)
2. (optional) novamira/formidable-list-subscriptions { status: "active" }
3. (Pro) novamira/formidable-cancel-subscription { subscription_id: id }
   → flips the local ledger to status=cancelled
   → REMEMBER: the upstream gateway (Stripe) is NOT contacted automatically
```

### W8 — Organise forms into a Pro Application

```
1. novamira/formidable-create-application { name: "Customer onboarding" }
   → returns application_id (APP_ID)
2. novamira/formidable-apply-form-to-application { application_id: APP_ID, form_id: F1 }
3. novamira/formidable-apply-form-to-application { application_id: APP_ID, form_id: F2 }
4. novamira/formidable-get-application { application_id: APP_ID }
   → returns the form_ids list to verify
```

## Gotchas

### G1 — `dropdown` is an alias of `select`, but add-field requires `select`

Formidable internally accepts `dropdown` as a synonym of `select` in its update path (`FrmField::update` at FrmField.php:742). The catalog returned by `get-field-types-schema` reports `select` ONLY. Passing `type: "dropdown"` to add-field returns `formidable_invalid_input` — always use the canonical catalog slug.

### G2 — Repeater fields don't cascade-delete their child sub-form

A Repeater field (Pro) auto-creates a child form (a `wp_frm_forms` row with `parent_form_id` pointing at the parent). When you call `delete-field` on the Repeater, the child form REMAINS in `wp_frm_forms` — `FrmField::destroy` doesn't cascade to it. Two ways to clean up:

- delete the child form separately via `delete-form` on its id (find it via `list-forms { parent_form_id: <parent_id> }`)
- delete the parent form via `delete-form { force: true }` — that cascades child forms via `FrmForm::destroy`

### G3 — `edit-form-settings` won't write `custom_style`

The whitelist intentionally excludes `custom_style` so style binding goes through `apply-style` exclusively. If you pass `custom_style` in the `settings` map, it's reported in `dropped[]` and silently ignored — use `apply-style` instead.

### G4 — `success_msg` / `success_action` / `success_url` / `success_page_id` migrated to the on-submit action

In Formidable 6.0+, the form-level options no longer hold the on-submit confirmation. Use `set-on-submit-action` for those fields — `edit-form-settings` drops them.

### G5 — Trash state on forms is a soft-delete; entries don't have a trash state

`delete-form` (default `force=false`) sets `status='trash'` + writes a `trash_time` timestamp into the options blob. Re-calling `delete-form` on an already-trashed form is a no-op (`deleted=false`) — this avoids a lost-write race on the `trash_time` field. To purge from trash: call `delete-form { force: true }` which goes through `FrmForm::destroy` and cascades.

Entries (`wp_frm_items`) have NO trash state — they have an `is_draft` flag instead, which means "saved-for-later submission", not "soft-deleted". `delete-entry` is always a hard delete.

### G6 — Header injection guard on notifications

Every email-bound value (`subject`, `email_to`, `cc`, `bcc`, `reply_to`, `from`) is RFC 5322 validated: CR / LF / NULL characters return `formidable_invalid_email_header`. This applies to both `create-notification` and `edit-notification`.

### G7 — URL scheme whitelist on redirect

`set-on-submit-action { type: "redirect", redirect_url }` rejects `javascript:`, `data:`, `vbscript:`, and any other non-allowed scheme. Allowed: `http`, `https`, `mailto`, `tel`. Returns `formidable_invalid_url_scheme`.

### G8 — Cache invalidation gap on stock WP

Formidable's `clear_form_cache` / `FrmEntry::clear_cache` use `FrmDb::cache_delete_group`, which is a no-op on stock WordPress without an external object cache plugin (Redis / Memcached / WP Engine drop-in). The novamira-pro `frm_invalidate_form_caches` helper also calls `wp_cache_delete($form_id, 'frm_form')` per-key to cover the gap. For entry-list queries we bypass the cache entirely with a direct `$wpdb` read.

### G9 — `arbitrary-key injection` guard on notifications + edit-form-settings

Both `create-notification` and `edit-notification` apply a settings-key whitelist (`FRM_NOTIFICATION_KEYS`) — anything outside is silently dropped at the boundary. Same for `edit-form-settings` (`FRM_FORM_SETTINGS_KEYS`). This protects against `__proto__` / arbitrary-key shenanigans without complicating the API.

### G10 — `cancel-subscription` flips the local ledger only

The Pro-only `cancel-subscription` ability writes `status='cancelled'` on the `wp_frm_subscriptions` row. It does NOT contact Stripe / PayPal — the gateway-side cancel is owned by Formidable Pro's own gateway controllers. Use this ability when the upstream cancel has happened externally (refund, admin override) and the local ledger needs to catch up.

### G11 — Entry meta uses field_id internally; map to field_key for human use

`get-entry { include_meta: true }` returns `entry.meta` keyed by `field_key` (with `field_id` fallback when the field row is gone). `edit-entry` accepts BOTH `field_id` and `field_key` as keys in `item_meta` and resolves them internally.

### G12 — On-submit is PUT semantics per event

A form has at most one on-submit action per event (`create` and / or `update`). `set-on-submit-action` finds any existing on-submit whose `event` list overlaps with the requested events and UPDATES it in place; if no match, it CREATES a new one. The response carries `created` so you know which path ran.

### G13 — Entries cache is not invalidated after edits — we sidestep it

After `edit-entry` / `set-entry-status`, the underlying Formidable cache for entry-list queries may be stale (see G8). `list-entries` queries `wp_frm_items` directly via `$wpdb` to avoid this.

### G14 — Style apply bypasses FrmForm::update

`apply-style` writes `options.custom_style` directly via `$wpdb->update` because `FrmForm::update_form_options` filters the options map against a known-key schema and silently drops `custom_style`. This is the same mechanism `FrmStylesController` uses internally — direct DB write is the canonical path.

### G15 — Application member-form-deletion is not cascaded

`delete-application` removes only the term itself. The forms previously bound to it (via `_frm_form_id` term meta) remain in `wp_frm_forms` — they're just unbound. Re-bind them to another application or leave them as orphans.

## Use novamira/execute-php for surfaces NOT covered by abilities

The following operations don't have dedicated abilities — use `novamira/execute-php` with the Formidable model classes directly. Snippets are intentionally short — agents adapt them per task.

### EP1 — Create a non-email form action (wppost / webhook / mailchimp / payment)

```php
// post_excerpt determines the action TYPE; settings shape varies per type.
$action_id = wp_insert_post([
    'post_type'    => 'frm_action',
    'post_status'  => 'publish',
    'post_title'   => 'My webhook',
    'post_excerpt' => 'webhook',   // or 'wppost' | 'mailchimp' | 'stripe' | …
    'menu_order'   => $form_id,
    'post_content' => maybe_serialize([
        'webhook_url' => 'https://example.com/intake',
        'request_method' => 'POST',
        // …other type-specific keys
    ]),
]);
```

### EP2 — Edit a non-email form action (partial merge)

```php
$post = get_post($action_id);
$settings = maybe_unserialize($post->post_content) ?: [];
$settings['webhook_url'] = 'https://example.com/new-intake';
wp_update_post(['ID' => $action_id, 'post_content' => maybe_serialize($settings)]);
```

### EP3 — Edit / create / delete a Formidable Style (the 250+ CSS variable set)

```php
$style = FrmStyle::getOne($style_id);
FrmStyle::save_settings($settings); // see formidable/classes/models/FrmStyle.php:408
```

### EP4 — Bulk reset entry meta values

```php
foreach ($entry_ids as $id) {
    FrmEntryMeta::update_entry_metas($id, [$field_id => '']);
}
FrmEntry::clear_cache();
```

### EP5 — Contact the upstream Stripe API for a gateway-side cancel

Pro-only. The Formidable Pro Stripe controller owns the lifecycle.

```php
// Pro: search FrmProStrpLite*Controller / FrmProTransaction for the
// canonical cancel flow.
do_action('frm_cancel_subscription', $subscription_id);
```

### EP6 — Read the form's complete options blob without the edit-form-settings whitelist

```php
$form = FrmForm::getOne($form_id);
$options = is_array($form->options) ? $form->options : (array) maybe_unserialize($form->options);
```

### EP7 — Trigger Formidable's entry-create hook chain after a manual insert

```php
do_action('frm_after_create_entry', $entry_id, $form_id, ['is_child' => false]);
do_action("frm_after_create_entry_{$form_id}", $entry_id, ['is_child' => false]);
```

## Pro-vs-free decision table

Always branch on `check-setup` flags BEFORE invoking these surfaces:

| Surface | Free | Pro | Stripe Lite (free) only | Note |
|---|---|---|---|---|
| form / field / entry / notification / on-submit / styles / settings | ✅ | ✅ | — | All core abilities |
| applications (S7) | — | ✅ | — | Pro-only |
| transactions / subscriptions read (S8 read) | — | ✅ | ✅ | Needs `payments_supported=true` |
| cancel-subscription | — | ✅ | — | Pro + `payments_supported` |

`pro_active=true && pro_min_satisfied=true` → Pro abilities are loaded.
`payments_supported=true` → S8 read abilities are loaded.
`pro_active && payments_supported` → cancel-subscription is loaded.

If a Pro ability is in your tool list, it's safe to call. If it isn't, branch via `novamira/execute-php` or report back to the user.

## Annotations summary

| Ability | readonly | destructive | idempotent |
|---|---|---|---|
| check-setup | ✓ | | ✓ |
| list-forms | ✓ | | ✓ |
| get-form | ✓ | | ✓ |
| get-field-types-schema | ✓ | | ✓ |
| create-form | | | |
| edit-form | | ✓ | ✓ |
| delete-form | | ✓ | ✓ |
| add-field | | | |
| edit-field | | ✓ | ✓ |
| delete-field | | ✓ | ✓ |
| list-notifications | ✓ | | ✓ |
| get-notification | ✓ | | ✓ |
| create-notification | | | |
| edit-notification | | ✓ | ✓ |
| delete-notification | | ✓ | ✓ |
| set-on-submit-action | | ✓ | ✓ |
| edit-form-settings | | ✓ | ✓ |
| list-form-actions | ✓ | | ✓ |
| get-form-action | ✓ | | ✓ |
| delete-form-action | | ✓ | ✓ |
| list-styles | ✓ | | ✓ |
| apply-style | | ✓ | ✓ |
| list-entries | ✓ | | ✓ |
| get-entry | ✓ | | ✓ |
| edit-entry | | ✓ | |
| delete-entry | | ✓ | ✓ |
| set-entry-status | | ✓ | ✓ |
| list-applications | ✓ | | ✓ |
| get-application | ✓ | | ✓ |
| create-application | | | |
| apply-form-to-application | | ✓ | ✓ |
| clear-form-from-application | | ✓ | ✓ |
| delete-application | | ✓ | ✓ |
| list-transactions | ✓ | | ✓ |
| get-transaction | ✓ | | ✓ |
| list-subscriptions | ✓ | | ✓ |
| get-subscription | ✓ | | ✓ |
| cancel-subscription | | ✓ | ✓ |
