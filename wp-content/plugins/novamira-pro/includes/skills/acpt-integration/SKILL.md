---
name: acpt-integration
description: Activate when working with the ACPT (Advanced Custom Post Types) plugin on a WordPress site — Custom Post Types, Taxonomies, Meta groups (custom fields), or Option Pages. Covers ACPT's three-level field hierarchy (MetaGroup → MetaBox → MetaField), site-wide unique box names, the polymorphic target model for value IO, value shapes per field type, edit semantics, and the discover→list→get→modify→verify workflow.
---

# ACPT Integration

## When to use

Activate this skill when:
- The user mentions ACPT, acpt.io, or `wp_acpt_*` tables.
- The user asks to create, edit, or delete a custom post type, taxonomy, meta group, or option page on a site that has ACPT active.
- The user asks to read or write field values on a post, term, user, comment, or option page on a site using ACPT.
- `acpt-check-setup` returns `active: true`.

## When NOT to use

- If ACF, Pods, JetEngine, ASE, or Meta Box is the primary content-modeling tool on the site, use the corresponding integration skill instead.
- Always call `acpt-check-setup` first. If `min_satisfied: false`, the installed ACPT is older than Novamira supports — inform the user and stop.
- For a plugin-agnostic representation of the site's content model (snapshots, audits, cross-plugin migrations), activate the `content-model-schema` skill alongside this one.

## Discover before you act

Always start with `novamira/acpt-check-setup`. It returns:

| Field | Meaning |
|---|---|
| `active` | ACPT loaded (`ACPT_PLUGIN_VERSION` defined) |
| `version` | Installed version |
| `min_satisfied` | The installed ACPT is one Novamira supports |
| `license_active` | ACPT Pro license currently valid — informational (the abilities work without it, see the option page gotcha below) |
| `counts` | `{custom_post_types, taxonomies, meta_groups, option_pages}` |

If `active: false` or `min_satisfied: false`, stop and report. The abilities cover the license-agnostic core (CPTs, taxonomies, meta groups, option pages). Forms, dynamic blocks, datasets and the WooCommerce integration are not exposed.

Then list before getting: call `acpt-list-*` to enumerate existing entities before calling `acpt-get-*` on a specific one.

## The three-level field hierarchy — most important concept

ACPT splits a "field group" into three nested concepts:

- **MetaGroup** — the top-level container (a named collection of meta boxes). Has a name, label, display (`standard`, `accordion`, `verticalTabs`, `horizontalTabs`), and location rules (`belongs[]`) that attach it to CPTs, taxonomies, option pages, users or comments.
- **MetaBox** — a sub-container within a MetaGroup (a panel or tab in the editor UI).
- **MetaField** — the actual input (id, name, type, options, advanced options). The leaf.

Other plugins (ACF, Meta Box, Pods) typically flatten to two levels (group → fields). ACPT's model is exposed by `acpt-get-field-group-schema include_fields=true` (the READ shape, `meta_boxes[]`):

```jsonc
{
  "id": "…", "name": "product_details", "label": "Product Details",
  "context": "advanced", "priority": "default", "box_count": 2,
  "belongs": [{ "belongs_to": "customPostType", "operator": "=", "find": "product" }],
  "meta_boxes": [
    { "id": "…", "name": "product_general", "label": "General", "sort": 1, "fields": [
      { "id": "…", "name": "sku", "type": "Text", "label": "SKU", "sort": 1, "required": false, "default_value": "" },
      { "id": "…", "name": "price", "type": "Currency", "label": "Price", "sort": 2, "required": true, "default_value": "" }
    ]},
    { "id": "…", "name": "product_advanced", "label": "Advanced", "sort": 2, "fields": [
      { "id": "…", "name": "barcode", "type": "Text", "label": "Barcode", "sort": 1, "required": false, "default_value": "" }
    ]}
  ]
}
```

`acpt-create-field-group` / `acpt-edit-field-group` take the WRITE shape: `boxes[]` (not `meta_boxes[]`), each `{name, label?, fields[{name, type, label?, required?, default_value?, …type options}]}`. Field types are case-sensitive (`Text`, not `text`). Type-specific options (`options` for choices, `children` for Repeater, `blocks` for FlexibleContent, `minimum_blocks`, `accepts`, …) go as top-level keys of the field — call `acpt-get-field-type-schema` for the list.

## Box names are unique across the whole site

ACPT keys stored values by `{box}_{field}` and resolves a box or field sent without an id by **name, across all groups**. Two groups sharing a box name therefore share values, and saving the second group can take the first group's fields away from it.

- `acpt-create-field-group` / `acpt-edit-field-group` refuse a new box name that another group already uses (`acpt_box_name_taken`, naming that group). Prefix box names with the group or content type: `product_general`, not `general`.
- Field names should be distinctive too. ACPT may store a new field, repeater child or block field whose name exists in another group with a numeric suffix (`title` → `title_1`). When it does, the create/edit response lists every change in `renamed[]` as `{requested, stored}` box.field paths. Anything ACPT did not keep makes the call an error naming it (see Edit semantics). Use the stored names from then on (re-read with `acpt-get-field-group-schema include_fields=true` when unsure).

## Where values live

Field values are native WordPress meta, not ACPT tables: post meta, term meta, user meta, comment meta, and options for option pages. The meta key is `{box}_{field}` (option pages prefix it with the option page's menu slug), plus helper keys such as `_type`, `_id`, `_label`, `_attachment_id`, `_currency`, `_length`, `_weight`, `_lat`/`_lng`/`_city`/`_country`. Always write through `acpt-write-values` so ACPT builds the helpers.

## License gotcha — option pages require Pro

ACPT distinguishes license-agnostic features (CPTs, taxonomies, meta groups) from **Pro-gated** features. **Option pages are Pro-gated** at runtime:

- The `acpt-create-option-page` / `edit` / `delete` / `list` / `get` abilities ALWAYS work — they read and write ACPT's DB rows.
- BUT the **menu page** is only registered with WordPress when both `ACPT_IS_LICENSE_VALID` and `ACPT_ENABLE_PAGES` are true. Without a valid ACPT Pro license, the option page exists in the DB but does not appear in the wp-admin sidebar.

When `check-setup` returns `license_active: false`:
- Tell the user that any option page they create through the abilities will be persisted but invisible in wp-admin until the Pro license is activated.
- Meta values written to those option pages still round-trip correctly via `read-values` / `write-values` (the storage works regardless of license state) — they're just not editable through the admin UI.

Option page placement: `parent_menu_slug` (create) makes a sub-page of another ACPT option page; `parent_slug` places a page under a native WordPress admin menu such as `options-general.php` (only where the installed ACPT supports it — otherwise the ability refuses with `acpt_parent_slug_unsupported`). `acpt-list-option-pages` returns the pages without an ACPT parent (with `child_count`); `acpt-get-option-page` lists a page's ACPT sub-pages and shows `parent_slug` when set. Editing a page keeps its `parent_slug` unless you pass a new one (`""` removes it).

## Timing — registration is on init, not synchronous

ACPT registers WP-native post types and taxonomies via the `init` hook, not synchronously inside `register_acpt_post_type()` / `register_acpt_taxonomy()`. So:

- An agent that creates a CPT then immediately calls `wp_insert_post` for that CPT slug in the **same request** will get `Invalid post type` — the CPT registration hasn't run yet.
- The agent should split CPT-creation and post-insertion across separate calls / requests, OR call ACPT's lower-level registration manually (advanced).

Same applies to taxonomies + their terms.

## Target model for read-values / write-values

```jsonc
{
  "target": {
    "type": "post|term|user|comment|option-page",
    "id":   123 | "option-page-menu-slug"
  }
}
```

- `post` / `term` / `user` / `comment`: numeric ID
- `option-page`: the menu slug

`acpt-write-values` takes `values: {box_name: {field_name: value}}`. `acpt-read-values` takes `group_name` and optionally `fields: [{box_name, field_name}]` (omit to read the whole group) and returns `values: {box_name: {field_name: value}}`.

The value shape depends on the field type and is NOT always a plain string: Currency is `{amount, unit}`, Length `{length, unit}`, Weight `{weight, unit}`, DateRange a `[from, to]` list, File `{id: "<attachment id>"}`, Address a single string, Repeater a list of rows. `acpt-get-field-type-schema` returns `value_format` for every type — read it before writing an unfamiliar type. A write that ACPT rejects comes back as `status: "error"` for that field only.

## Edit semantics

`acpt-edit-*` abilities use:
- **PATCH** for top-level keys: keys you omit are preserved
- **REPLACE** for arrays (`boxes`, `belongs`, `labels`, `supports`, etc.): pass the full desired array

Always fetch the current state with `get-*` before editing if you only want to add or change something — otherwise you might lose siblings. For a field group, take `meta_boxes[]` from `acpt-get-field-group-schema full_definition=true` (every field's stored definition: choices with defaults, advanced options, repeater children, flexible blocks; add `path` such as `box.field` or `box.field.child` for one part only), change what you need, and send it back as `boxes[]`; keeping the `id`s keeps the existing boxes and fields (and their stored values) attached.

Inside `boxes[]`, each field you send, at any depth (repeater children and block fields included), is **merged over its stored definition**:
- a key you omit keeps its stored value — choices (`options`), repeater sub-fields (`children`), flexible `blocks`, `advanced_options`, the default choice;
- a key you send replaces the stored value, an empty list included: `advanced_options: []` clears every advanced option, `advanced_options: [{key, value}]` keeps only that one, `options: []` removes all choices, `children: []` removes all sub-fields; to remove a default choice, send the options with `isDefault: false`;
- an advanced option sent as a top-level field key (e.g. `"maximum_blocks": "5"`) sets only that key and keeps the others.

Ids of fields, sub-fields, blocks and block fields must be this group's own (from its schema); to add a new one, omit the id. To move a field into a repeater or flexible block of the same group, send it with its id at the new place and leave it out of the old one; stored values do not follow a move, since each place stores them differently. Taking a field out of a repeater or block (to the top level, or between a repeater and a block) is always refused before anything is written (`acpt_field_move_unsupported`), because ACPT does not reliably clear the old place: send the field at its new place without its id (a new field) and leave the old one out, which deletes it. Siblings (fields of a box, children of a repeater, blocks of a flexible field, fields of a block) need distinct names: a repeated name or a repeated id is refused before anything is saved.

ACPT keeps only the advanced options it knows for a field type and drops others without an error. Create and edit read the group back (by id: ACPT normalizes the group name, so use the `name` the response returns) and answer success only when everything sent is stored as sent. Otherwise the call is an error: `acpt_create_field_group_failed` / `acpt_edit_field_group_failed` when the save failed, `acpt_field_group_incomplete` when a box, field, block or advanced option value is not stored as sent. The message ends with compact recovery data (group id and stored name, each missing item with its id and actual place or null, option values sent vs stored; at most 10 entries per list with long values shortened, the rest counted under `omitted`). `more` names the exact `acpt-get-field-group-schema` call (`full_definition: true`, plus `path`: a shown entry's path or one of `more.omitted_paths`) that returns the full stored state of anything shortened or left out. Some changes may already be stored: re-read the group that way and reconcile before retrying.

## Workflow example — modeling a Product CPT

User asks: "Set up a Product CPT with general fields and an advanced tab."

1. `acpt-check-setup` → confirm `active: true, min_satisfied: true`.
2. `acpt-list-post-types` and `acpt-list-field-groups` → see what already exists (and which box names are taken).
3. `acpt-create-post-type` name=`product`, singular=`Product`, plural=`Products`.
4. `acpt-get-field-type-schema` for any unfamiliar field types (`Currency` here) before defining the group.
5. `acpt-create-field-group`:
   ```jsonc
   {
     "name": "product_details",
     "label": "Product Details",
     "display": "verticalTabs",
     "belongs": [{ "belongs_to": "customPostType", "operator": "=", "find": "product" }],
     "boxes": [
       { "name": "product_general", "label": "General", "fields": [
         { "name": "sku", "type": "Text", "label": "SKU" },
         { "name": "price", "type": "Currency", "label": "Price", "required": true }
       ]},
       { "name": "product_advanced", "label": "Advanced", "fields": [
         { "name": "barcode", "type": "Text", "label": "Barcode" }
       ]}
     ]
   }
   ```
   If the response contains `renamed[]`, use the stored names in the next steps.
6. In a later request (after the CPT is registered), populate: `acpt-write-values target={type:"post", id:<product_id>} values={"product_general": {"sku": "X1", "price": {"amount": 9.99, "unit": "EUR"}}, "product_advanced": {"barcode": "1234"}}`.
7. Verify: `acpt-read-values target={type:"post", id:<product_id>} group_name="product_details"` (or pass `fields=[{box_name:"product_general", field_name:"sku"}, …]`).

## Progressive disclosure conventions

- `get-post-type` returns identity + `meta_group_count` by default. Pass `include_meta_groups: true` to inflate.
- `get-field-group-schema` returns `{id, name, label, context, priority, box_count, belongs}` by default. Pass `include_fields: true` to add the `meta_boxes[]` hierarchy with basic field properties, or `full_definition: true` for every field's stored definition (choices, advanced options, children, blocks) in the shape edit accepts; add `path` to get one box, field, child, block or block field only.
- `get-field-type-schema` returns options and the value format for ONE field type — call it right before creating/editing a field or writing its value.

## Recovery from partial failures

WordPress has no transactional rollback. If a multi-step workflow fails midway:
- `acpt-delete-*` abilities are idempotent — re-running `delete-field-group name=product_details` after a partial create returns success cleanly.
- The agent should clean up successfully-created entities, then retry from the failed step.

## Things to NOT do

- Don't bypass ACPT's Repository classes by writing directly to `wp_acpt_*` tables — ACPT enforces invariants and cache flushes through its Repositories.
- Don't use `update_post_meta` directly to write ACPT field values — go through `acpt-write-values` so type-specific storage (helper keys, attachments, repeaters, dates) is handled.
- Don't reuse a box name another group has, and don't assume a field name was stored as sent — check `renamed[]`.
- Don't try to register ACPT-style meta groups via PHP filters — ACPT is DB-first.
- Don't create forms, dynamic blocks, datasets or WooCommerce features via the abilities — they're not exposed.

## Things that are safe and encouraged

- Run `acpt-check-setup` at the start of any session involving ACPT.
- Use `get-field-type-schema` whenever building / editing fields or writing values — avoids guessing option keys and value shapes.
- Use `list-field-groups` filtered by `belongs_to` to find groups attached to a specific CPT before editing.
- Prefer `name` selectors over `id` — they're human-readable.
- Combine `create-post-type` + `create-field-group` in a single workflow to set up a content type end-to-end.
