---
name: acf-integration
description: Activate when working with Advanced Custom Fields (ACF) on a WordPress site — discovering field groups, reading field values, writing field values, or managing field group definitions. Establishes the target model, the consent semantics for complex-type writes, source awareness (db / json / php), naming guidance (key vs name), and the standard discover→read→modify→write workflow shared across all ACF abilities.
---

# Working with ACF and ACF Pro

This skill is for using the eight `novamira/acf-*` abilities together. Read it once at the start of any ACF-related task and refer back when in doubt.

## When to use

Activate when the user asks to inspect, read, write, or manage ACF data: querying field values, populating a post's custom fields, creating a new field group, editing an existing one, or auditing what fields exist on the site.

Do not activate for unrelated WordPress meta operations (use the WordPress abilities directly), or for builder-specific dynamic data tags (those belong to Bricks/Elementor abilities). ACF Pro 6.1+ internal post types and taxonomies are covered by this skill — see the "Internal post types" section below.

For a plugin-agnostic representation of the site's content model (snapshots, audits, cross-plugin migrations), activate the `content-model-schema` skill alongside this one.

## Relationship with ACF 6.8+ native Abilities API

ACF 6.8 ships its **own native WordPress Abilities API integration**. The two surfaces are **complementary, not substitutes** — they cover different slices of ACF.

**What ACF native (`acf/*`) gives you:**
- Field-group / CPT / taxonomy CRUD with **per-item AI access opt-in** ("Allow AI Access" toggle on each item's settings) and an optional **AI Description** field per item.
- **Per-CPT content CRUD as dynamic abilities** — `acf/products` lists products, `acf/create-product` creates one, `acf/view-product` / `acf/update-product` / `acf/delete-product` round things out. Each registered CPT gets its own set of five abilities. Same pattern for taxonomies (`acf/{taxonomy}s`, `acf/create-{taxonomy}`, etc. per registered tax).
- Requires a one-time setup: `add_filter('acf/settings/enable_acf_ai', '__return_true')` plus toggling AI access on each item.

**What `novamira/acf-*` gives you:**
- Field-group / CPT / taxonomy / options-page CRUD with no setup — visible by default.
- **Options pages** (`list/get/create/edit/delete-options-page`) — not covered by ACF native at all.
- **Target-agnostic value IO** — one `acf-read-values` / `acf-write-values` covers `post` / `user` / `term` / `comment` / `options` with the same shape, mirroring Pods / JetEngine / ASE / Meta Box / ACPT in this plugin. Consistency across content-modeling plugins is the point.

**Content CRUD with Novamira Pro:**
Novamira Pro covers post-level CRUD via the **generic** `novamira/create-post`, `novamira/update-post`, `novamira/delete-post` abilities — they accept a `post_type` parameter and work on any registered CPT (ACF, Pods, ASE, hand-rolled, you name it). For ACF specifically the typical flow is two steps:

1. `novamira/create-post` with `post_type: "product", title: "..."` — creates the WP post.
2. `novamira/acf-write-values` with `target: {type: "post", id: <new_id>}, values: {...}` — populates the ACF field values.

ACF native bundles those two steps into one `acf/create-product` ability per CPT. Our pattern keeps the generic post writer and the per-plugin field writer cleanly separated, but the end result is the same.

**The genuine overlap** is on field-group / CPT / taxonomy definition CRUD (~10 abilities cover the same operations). For options pages and target-agnostic value IO we add capability; for per-CPT content CRUD ACF adds discovery convenience (one ability per CPT) over our generic shape.

**How to recommend to the user:**

| Situation | Suggested surface |
|---|---|
| Wants per-item RBAC-style permissions for AI access | ACF native (`acf/*`) |
| Wants a dedicated content-CRUD ability per CPT (`acf/create-product`) | ACF native — better discovery surface |
| OK with generic `create-post` + `post_type` parameter | `novamira/*` — same end result, fewer abilities |
| Needs options pages | `novamira/acf-*` — ACF native doesn't cover these |
| Wants consistent value-IO pattern across all content plugins | `novamira/acf-*` |
| ACF < 6.8 installed | `novamira/acf-*` — native doesn't exist there |
| Just wants schema CRUD without any setup | `novamira/acf-*` |

When the user explicitly mentions ACF's "Allow AI Access" toggle or asks about ACF 6.8 AI features, route them to ACF native. Otherwise our integration is the right default.

## The target model

Every value read or write addresses a target. Targets are structured, not raw ACF strings:

```json
{ "type": "post", "id": 123 }
{ "type": "user", "id": 7 }
{ "type": "term", "id": 45 }
{ "type": "comment", "id": 99 }
{ "type": "options" }
{ "type": "options", "id": "my-options-store" }
```

`id` is required for `post`, `user`, `term`, `comment`. For `options` it is optional; omitting it means ACF's main shared `"options"` store. The `block` target (ACF Blocks) is intentionally not supported.

**Important on options pages:** the `id` is the options page's `post_id` (ACF's storage key in `wp_options`), **not the `menu_slug`**. ACF defaults `post_id` to the shared string `"options"` for every options page that doesn't explicitly set its own — so values flow into one shared bucket unless the user passes a custom `post_id` on the options page payload. Common patterns:

- One options page, default storage: `target.id` omitted (or `"options"`).
- Multiple options pages, each with its own `post_id` explicitly set: pass `target.id = <that post_id>`. Inspect with `acf-get-options-page` first — the returned payload tells you what `post_id` is configured.
- Passing the `menu_slug` as `target.id` only works when the user happens to use the same value for both `menu_slug` and `post_id` (a convention, not enforced by ACF).

## Discover before you act

Start every non-trivial ACF task with discovery:

1. `novamira/acf-check-setup` — verify ACF is active, what edition (free vs pro), and whether the current user has admin capability. If `active` is false, stop.
2. `novamira/acf-list-fieldgroups` — overview of all field groups on the site. Note `editable: false` for PHP-registered groups (they cannot be edited at runtime), and `active: false` for dormant groups whose location rules are disabled.
3. `novamira/acf-get-fieldgroup-schema` — full schema for a specific group, in the same shape `acf-create-fieldgroup` and `acf-edit-fieldgroup` accept. Use this as a template when building a payload to write back.

Each field returned by the schema includes both `name` and `key`. Prefer `key` when writing values: keys are globally unique, names can be ambiguous when several groups define a field with the same name.

## Reading values

`novamira/acf-read-values` accepts:

- `target` — required.
- `fields[]` — optional list of names or keys to read; mixed allowed. If omitted, returns every field whose location rules apply to the target.
- `format_values` — defaults to `true`. Returns ACF's formatted output: image fields as arrays with sizes, post objects expanded, etc. Set to `false` to get raw stored data (typically IDs).
- `only_with_values` — when `true`, skips fields whose stored value is empty.

Caveat: `get_field()` returns the field's ACF default value when no value has ever been written. A freshly created post may appear pre-populated. If that ambiguity matters, pass `only_with_values: true`.

## Writing values

`novamira/acf-write-values` writes a batch of values to a single target. The shape:

```json
{
  "target": { "type": "post", "id": 123 },
  "values": {
    "subtitle": "Hello world",
    "features": { "value": [ {"title": "A"}, {"title": "B"} ], "mode": "replace" }
  }
}
```

**Atomic field types** (text, number, image, relationship, post_object, true_false, choice, etc.) accept the value directly.

**Complex field types** (`repeater`, `flexible_content`, `gallery`, `clone`, `group`) require an explicit consent wrapper `{ "value": ..., "mode": "replace" }`. This is because writing a complex value replaces the entire previous content — there is no append or patch. The wrapper forces the agent to acknowledge that the new array is the complete new state.

If a complex field appears in `values` without the wrapper, the entire batch is rejected before any write happens. This is intentional: better to surface the destructive nature than to silently delete rows.

ACF Extended (ACFE, both Free and Pro) does not register replacement field types for these — it extends the native ones via filters and settings, so an ACFE-flavored repeater is still `type: "repeater"` and the consent wrapper applies automatically. Other third-party field types (custom or from less common plugins) are treated as atomic by default; if a project relies on a third-party type with genuine array-replace semantics, the agent should still apply read-modify-write discipline manually — but without the safety net of an automatic rejection.

To modify a single row of a repeater: read the current value with `acf-read-values`, mutate the row in your local copy, then write the entire array back via `acf-write-values` with `mode: "replace"`.

### Pre-flight validation

Every value in the batch goes through ACF's `acf_validate_value()` before any field is written. This enforces `required` constraints, format rules (email, number range, length, etc.), and any custom validators registered via the `acf/validate_value` filter. If any field fails, the entire batch is rejected with `validation_failed` and the response includes a `failures[]` list with `{field, code, message}` per failing entry. Nothing is written.

The validation runs per **top-level** entry only — it does not recurse into the sub_fields of a complex value (repeater rows, group fields, flexible_content layouts). A required constraint on a nested sub_field is checked by ACF only at form submission time, not at this pre-flight step. If a project relies on required-on-sub_field, validate the rows yourself before calling `acf-write-values`.

### ACF hooks behavior

The integration uses ACF's official `update_field` and `get_field` APIs, so the hooks tied to those calls fire normally:

- **On write:** `acf/update_value` and the type/name/key-specific variants fire for every value.
- **On read:** `acf/load_value` fires; `acf/format_value` fires when `format_values: true` (default).

The `acf/save_post` action is fired by ACF only during the admin form submission flow (when the user clicks Update in the WP editor). Direct `update_field()` calls — which is what `acf-write-values` uses — do **not** trigger `acf/save_post`. If a third-party plugin relies on `acf/save_post` (for cache busting, post-save sync, audit logging), those side effects will not run when an agent writes via the abilities. Plugins listening to `acf/update_value` are unaffected.

## Managing field groups

`novamira/acf-create-fieldgroup`, `acf-edit-fieldgroup`, and `acf-delete-fieldgroup` work with ACF's native export format — the same shape `acf-get-fieldgroup-schema` returns. The simplest way to author a new group is to fetch a similar existing one, modify it, and create.

### Passing the payload

- **Send the settings flat, as the ability's arguments.** `{"title": "...", "fields": [...], "location": [[...]]}` — never nested inside a `parameters` object. A wrapped payload is refused, because ACF would otherwise store the wrapper as if it were field group settings and produce a group with no title and no fields.
- **`title` is required on create** and must be non-empty. A titleless group is invisible in the admin UI.
- **Unknown top-level keys are refused, not ignored.** Per-field settings (`label`, `name`, `type`, `instructions`, …) belong inside `fields[]`; location rules belong inside `location[][]`. The error names the keys at fault and lists the accepted ones.
- **Do not pass post ids.** `acf-get-fieldgroup-schema` deliberately omits the group's post id, and any id inside `fields[]` is ignored on both create and edit — including ids on the fields nested inside a repeater or a flexible content layout. Field groups are addressed by `key`, and fields by their own `key`.
- **The write is verified before it is reported as done.** The group is re-read from the database and compared against the payload: the key, every setting you supplied, the location rules group by group, and the fields down through their nesting. A create that does not match is deleted again, in the database and in Local JSON. Only rows the call can prove it created are removed; anything else it recorded is left in place and named in the error, and the reply's `rolled_back` says whether the rollback was clean. An edit that does not match is **not** undone — a failed edit is reported, not repaired. The error names the differences found and the field tree the group holds after the write. `fields[]` is REPLACE semantics, so a failed edit may have removed fields: re-read the group with `acf-get-fieldgroup-schema`, see what it actually holds, and repair it from there rather than assuming the call left nothing behind.
- **Address a field group by its `key`, never by a post id.** A numeric value is not rejected as nonsense by ACF, it is resolved as a post id — so `acf-edit-fieldgroup` refuses anything that is not a `group_` key rather than editing whatever post that number happens to name.

### Copying a group

When you build a new group from another group's schema, give the copy a new `key` **and** new `fields[].key` values — or drop the field keys entirely and let them be generated. ACF field keys are global: two groups sharing a field key each keep their own stored field, but ACF's key-based lookup can only resolve one of them, so the copy and the original become indistinguishable to later reads and writes.

### Respect the project's storage convention

ACF field groups can live in three places: the WordPress database, JSON files inside the active theme (`/acf-json/`), or PHP code that calls `acf_add_local_field_group()`. **Which one a project uses is an architectural decision made by the developer, not by the agent.** Before creating or editing a field group, check what convention the project already follows by inspecting the `source` of existing groups via `acf-list-fieldgroups`:

- **All existing groups are `db` or `json`** → use `acf-create-fieldgroup` / `acf-edit-fieldgroup` / `acf-delete-fieldgroup`. The DB is the authoring surface; for `json` projects ACF re-syncs the file automatically when the directory is writable.
- **Existing groups are `php`** → the project manages field groups as code. Do **not** create a new group in the DB just because the abilities make it easy: that would silently split the source of truth. Instead, edit the file that registers the group, using `novamira/read-file` / `novamira/edit-file` / `novamira/write-file` to modify the PHP array passed to `acf_add_local_field_group()`. Our `acf-edit-fieldgroup` and `acf-delete-fieldgroup` will refuse `php`-sourced groups with `field_group_not_editable` anyway.
- **Mixed sources** → ask the user where the new group should live. Do not guess.

This principle applies only to schema management. Read operations (`acf-list-fieldgroups`, `acf-get-fieldgroup-schema`, `acf-read-values`) and value writes (`acf-write-values`) work uniformly on every source — the agent does not need to think about storage when reading or when writing field *values*.

### Source awareness

Field groups have one of three sources, exposed by `acf-list-fieldgroups` and `acf-get-fieldgroup-schema` via `source` and `editable`:

- `db` — created via the WP admin or via abilities. Fully editable.
- `json` — created in DB but auto-synced to a `/acf-json/` file by ACF. Edits via abilities continue to work; ACF re-syncs automatically.
- `php` — registered in code via `acf_add_local_field_group()`. **Cannot be edited or deleted at runtime.** The corresponding abilities refuse with `field_group_not_editable`. To change a PHP-registered group, edit the source file.

### Key collisions on create

Passing a `key` that already exists is rejected with `key_already_exists`. Either pick a different key, omit it (auto-generated), or use `acf-edit-fieldgroup` to modify the existing one.

### Renaming keys on edit

`acf-edit-fieldgroup` matches fields by `key`. **Renaming a key creates a new field and orphans any values stored under the old key**. Those values remain in `wp_postmeta` but become unreachable via the ACF API. Preserve keys when editing if you want to keep data; only rename when migrating intentionally.

### Replace semantics on edit

The `fields[]` array passed to edit defines the new state in full. Fields existing previously but absent from the payload are removed. To partially update, fetch the schema, mutate it, and write it back — do not send only the diff.

### ACF Pro field types

Some field types only exist on **ACF Pro**. `acf-create-fieldgroup` and `acf-edit-fieldgroup` reject Pro-only types when the runtime is ACF Free with `invalid_payload` and an `offending` list of `{name, type}` entries.

**Detection** — call `novamira/acf-check-setup` and inspect:

| Field | Value | Meaning |
|---|---|---|
| `edition` | `"pro"` or `"free"` | Direct edition flag. Use this before authoring a payload that includes Pro types. |
| `supports_internal_post_types` | boolean | Pro 6.1+ when true. |
| `supports_internal_options_pages` | boolean | Pro 6.2+ when true. |

**Pro-only field types** (rejected on ACF Free): `repeater`, `flexible_content`, `gallery`, `clone`. Plus options pages (managed via `acf-create-options-page` — Pro-only feature, silently accepted by Free but never registers at runtime).

Verified Pro features in 6.8.x: `repeater`, `flexible_content`, `gallery`, `clone`, options pages, internal post types, internal taxonomies.

Likely Free in 6.x: `text`, `textarea`, `wysiwyg`, `number`, `range`, `select`, `radio`, `checkbox`, `true_false`, `email`, `url`, `password`, `date_picker`, `time_picker`, `image`, `file`, `link`, `post_object`, `relationship`, `taxonomy`, `user`, `oembed`, `color_picker`, `accordion`, `tab`, `group`, `date_time_picker`. (`group` is ACF Free since 6.0; it is NOT in the Pro-only rejection list.)

**Payload skeletons** for the 4 Pro types — paste into `fields[]` of `acf-create-fieldgroup`:

```jsonc
// Repeater — homogeneous repeating rows. sub_fields is the row template.
{ "label": "Chapters", "name": "chapters", "type": "repeater",
  "sub_fields": [
    { "label": "Title", "name": "title", "type": "text" },
    { "label": "Pages", "name": "pages", "type": "number" }
  ] }

// Flexible Content — repeating rows, multiple alternate layouts per row.
{ "label": "Body", "name": "body", "type": "flexible_content",
  "layouts": [
    { "name": "text_block", "label": "Text Block",
      "sub_fields": [
        { "label": "Content", "name": "content", "type": "wysiwyg" }
      ] },
    { "name": "image_block", "label": "Image Block",
      "sub_fields": [
        { "label": "Image", "name": "image", "type": "image" }
      ] }
  ] }

// Gallery — multi-image picker. Stores an array of attachment IDs.
{ "label": "Photos", "name": "photos", "type": "gallery" }

// Clone — embeds the fields of another field group by reference.
// "clone" is an array of field-group OR field keys to mirror.
{ "label": "Cloned chapters", "name": "cloned_chapters", "type": "clone",
  "clone": ["group_42856abb236c9"],
  "display": "group",      // or "seamless"
  "layout": "block",       // or "table" / "row"
  "prefix_label": 0,
  "prefix_name": 0 }
```

Edits on these types follow the project's REPLACE semantics: passing a `repeater` field in `acf-edit-fieldgroup` rewrites every sub_field (the old sub_field keys are removed, new ones are created, even if names match). When you want to preserve sub_field history, fetch the schema first and merge against it on the agent side before sending the edit payload.

For **cross-plugin migration** of repeater / flexible / gallery / clone (e.g. into Pods, ACPT, Meta Box, JE), see the `content-model-migration` skill — it has explicit translation strategies for each (canonical `repeater` → Pods ACT or flatten-with-warning, canonical `flexible` → ACPT FlexibleContent direct mapping, etc.).

## Typical workflow

For most ACF tasks the canonical sequence is:

1. **Discover** — `acf-check-setup` → `acf-list-fieldgroups` → `acf-get-fieldgroup-schema` for the relevant group.
2. **Read** — `acf-read-values` to inspect current state if values matter.
3. **Modify** — locally compose the new schema or value payload from the discovered shape.
4. **Write** — `acf-write-values` for values, or `acf-edit-fieldgroup` / `acf-create-fieldgroup` for schema.
5. **Verify** — read back and confirm.

Skip steps you do not need (e.g. for a brand-new field group, skip read; for an atomic value update on a known field, skip discovery if the agent already has the key).

## Internal post types (CPT & taxonomies)

ACF Pro 6.1+ stores user-managed post types and taxonomies as internal records (`acf-post-type`, `acf-taxonomy`). Novamira Pro exposes these via 10 abilities: `acf-list-post-types`, `acf-get-post-type`, `acf-create-post-type`, `acf-edit-post-type`, `acf-delete-post-type`, plus the symmetric five for taxonomies.

### When to manage via ACF vs PHP

Use ACF-managed CPT/taxonomy when the user owns the structure and wants to edit it later via the ACF UI or via these abilities. Register via PHP (theme/plugin code) when the structure is part of a contract — it must exist regardless of ACF being active, and it should not be editable by site admins.

### Workflow pattern

1. Call `novamira/acf-check-setup`. If `supports_internal_post_types` is `false`, stop — the runtime is on ACF free or an older Pro. Inform the user.
2. The two count fields (`registered_acf_post_types_count`, `registered_acf_taxonomies_count`) tell you whether structure already exists. Zero means starting fresh, non-zero means inspect first.
3. Use `acf-list-*` to discover what exists; use `acf-get-*` to read a full payload before editing.
4. `acf-create-*` / `acf-edit-*` accept the same native ACF payload shape — round-trip via `get` is the safest editing pattern.
5. `acf-delete-*` requires `force: true` when content (posts under the CPT, terms in the taxonomy) exists. Without `force`, the call returns `delete_blocked_by_content` with `affected_count`.

### Edge case: slug change on a record with content

Renaming a CPT or taxonomy slug does NOT migrate existing posts/terms. The old `post_type` / `taxonomy` values remain in the database, but they no longer match a registered type. The edit response includes a `slug_change_orphans_content` warning with the affected count when this happens. Migration is the agent's responsibility (typically `wp post update --post_type=<new>` via WP-CLI, or a direct `UPDATE wp_posts` if you understand the implications).

### Cross-reference with field groups

A field group's `location[][]` rules can target a CPT/taxonomy created via these abilities — use the slug, not the ACF key, in the location rule (`{ param: "post_type", operator: "==", value: "book" }`).

Deleting a CPT or taxonomy does NOT modify field groups whose location rules referenced it. Those field groups remain valid records but stop matching anything. Use `acf-list-fieldgroups` after a delete if you want to find and clean them up.

### Storage convention

Same as field groups: records are stored in the `wp_posts` table as `acf-post-type` / `acf-taxonomy` post types, and ACF syncs them to `acf-json/` if the directory exists and JSON sync is enabled. The output's `source` field reports where the active copy lives (`db` / `json` / `php`). Never write to `acf-json/` directly — let ACF own that file.
