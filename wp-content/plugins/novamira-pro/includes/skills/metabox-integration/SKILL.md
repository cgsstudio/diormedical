---
name: metabox-integration
description: Activate when working with the Meta Box plugin on a WordPress site — field groups + values, Custom Post Types, Custom Taxonomies, Settings Pages, or Relationships. Covers source awareness (PHP-defined vs DB-stored), the polymorphic target model for value IO, edit semantics, the connect/disconnect lifecycle for relationships, and the discover→list→get→modify→verify workflow. Treats Meta Box as the site's chosen content-modeling tool; coexistence with ACF/Pods/JetEngine/ASE is a footnote.
---

# Meta Box Integration

## When to use

Activate this skill when:
- The user mentions Meta Box, metabox.io, or `rwmb_`.
- The user asks to create, edit, or delete a custom post type, taxonomy, field group, settings page, or relationship on a site that has Meta Box active.
- The user asks to read or write field values on a post, term, user, comment, or settings target on a site using Meta Box.
- `metabox-check-setup` returns `active: true`.

## When NOT to use

- If ACF, Pods, JetEngine, or ASE is the primary content-modeling tool on the site, use the corresponding integration skill instead.
- Always call `metabox-check-setup` first. If `min_satisfied: false`, Meta Box is below 5.6 — inform the user and stop.
- For a plugin-agnostic representation of the site's content model (snapshots, audits, cross-plugin migrations), activate the `content-model-schema` skill alongside this one.

## Discover before you act

Always start with `novamira/metabox-check-setup`. It returns:

| Field | Meaning |
|---|---|
| `active` | Meta Box loaded (RWMB_VER defined) |
| `version` | Installed version |
| `min_satisfied` | Meta Box >= 5.6.0 |
| `builder_active` | MB Builder active — required to CRUD field groups via API |
| `cpt_active` | MB Custom Post Type active — gates manage-post-types AND manage-taxonomies (single extension handles both) |
| `settings_page_active` | MB Settings Page active — gates settings-page abilities |
| `relationships_active` | MB Relationships active — gates relationship + connection abilities |
| `term_meta_active` | MB Term Meta active — required to bind field groups to taxonomies and to read/write values on a `term` target |
| `comment_meta_active` | MB Comment Meta active — required to bind field groups to comments and to read/write values on a `comment` target |
| `user_meta_active` | MB User Meta active — required to bind field groups to users and to read/write values on a `user` target |
| `group_active` | MB Group field type active — required when any field (including nested) has `type: group` |
| `custom_table_active` | MB Custom Table active. **Signal only** — read-/write-values do NOT currently support custom-table storage. When this is `true` AND a field group is flagged for custom-table storage, warn the user that value IO via this integration will silently miss the data. |
| `field_group_counts` | `{php: int, db: int}` — split by source |

If `active: false` or `min_satisfied: false`, stop and report. If a specific *_active flag is false, the corresponding manage-* / value-IO abilities return a runtime guard error — see Extension capability matrix below.

Then list before getting: call `metabox-list-*` to enumerate existing entities before calling `metabox-get-*` on a specific one.

## Extension capability matrix

The Meta Box plugin family is fragmented across many add-ons with different licensing. The integration tries to do the right thing for whatever extensions are installed — paid bundle, free bundle, or just the WP.org core. Each ability or call path is gated by a `*_active` flag on `check-setup`. A failing gate returns `WP_Error` with a code like `metabox_<gate>_not_active` and a message that names which source plugins provide the missing capability.

### Distribution map (informative)

| Capability | WP.org Meta Box (free core) | Meta Box Lite (free bundle, metabox.io) | MB Core (paid bundle, metabox.io) | Standalone paid |
|---|---|---|---|---|
| Core fields, postmeta values | ✅ | ✅ | ✅ | — |
| MB Term Meta | ✅ (bundled in core) | ✅ | ✅ | — |
| MB Comment Meta | ✅ (bundled in core) | ✅ | ✅ | — |
| MB Group field type | ✅ (bundled in core) | ✅ | ✅ | — |
| MB Custom Post Type | — | ✅ (bundled, free on WP.org as `mb-custom-post-type`) | ✅ | — |
| MB Relationships | — | ✅ (bundled, free on WP.org as `mb-relationships`) | ✅ | — |
| MB Builder (UI to define field groups) | — | ✅ (bundled) | ✅ | ✅ as MB Builder |
| MB Settings Page | — | — | ✅ | ✅ |
| MB User Meta | — | — | — | ✅ only |
| MB Custom Table | — | — | ✅ | ✅ |

If `cpt_active` or `relationships_active` is false because the user is on the WP.org Meta Box core, they can install `mb-custom-post-type` or `mb-relationships` from WP.org for free — point them there rather than upselling the paid bundle.

### Ability gates

| Group | Abilities | Gate(s) |
|---|---|---|
| Setup | check-setup | always |
| Field groups (read) | list-field-groups, get-field-group-schema, get-field-type-schema | always |
| Field groups (write) | create-field-group, edit-field-group, delete-field-group | `builder_active`; plus, per call: `term_meta_active` if `settings.taxonomies[]` non-empty, `settings_page_active` if `settings.settings_pages[]` non-empty, `group_active` if any field has `type: group` (recursive) |
| Value IO | read-values, write-values | always for `target.type=post` / `settings`; `term_meta_active` for `term`; `comment_meta_active` for `comment`; `user_meta_active` for `user` |
| CPTs + Taxonomies | list-post-types, get-post-type, manage-post-types (×3), list-taxonomies, get-taxonomy, manage-taxonomies (×3) | `cpt_active` |
| Settings Pages | list-settings-pages, get-settings-page, manage-settings-pages (×3) | `settings_page_active` |
| Relationships | list-relationships, get-relationship, manage-relationships (×3), connect-objects, disconnect-objects, list-connected-objects | `relationships_active` |

When a top-level extension (cpt / settings_page / relationships / builder) is missing, the corresponding manage-* abilities are not even registered on the site (loader-level gate) — except the field-group write side, which is registered but returns `metabox_builder_not_active` at runtime when MB Builder is absent. Rationale: PHP-defined field groups remain readable.

The per-call gates (term_meta / comment_meta / user_meta / group) run at execution time and surface a `WP_Error` describing which source plugin is missing — the abilities themselves stay registered.

### Custom-table storage — the silent-miss caveat

`custom_table_active` is reported but **not enforced** at the ability level. read-values / write-values go through `rwmb_meta()` / `update_post_meta()` etc., which target the postmeta table. A field group flagged for custom-table storage stores its values in a separate DB table — read-values will return nothing, write-values will write to postmeta (the wrong place) without raising an error.

When `custom_table_active: true` AND a field group's settings indicate custom-table storage, warn the user explicitly: "this field group stores values in a custom DB table, which this integration does not currently address — read/write through this ability will not see or update those values."

## Source awareness — the most important concept

Meta Box field groups, CPTs, taxonomies, settings pages, and relationships can be:

- **`source: 'db'`** — defined via the wp-admin UI, stored as a `meta-box` / `mb-post-type` / `mb-taxonomy` / `mb-settings-page` / `mb-relationship` CPT post. Editable via API.
- **`source: 'php'`** — registered via the `rwmb_meta_boxes` filter (or the equivalent for each extension) from a PHP file in the theme or another plugin. **Read-only via API** — the registration is code, not data.

Every list-* / get-* response includes a `source` field. The manage-* write abilities (`create-*`, `edit-*`, `delete-*`) return `metabox_php_registered` when called against a PHP-defined entity, with a message like:

> "books" is registered via PHP and is read-only at runtime. Edit the registering PHP file, or create a new DB-backed copy via the create-* ability to take ownership.

**Recovery pattern:** if the user wants to modify a PHP-registered field group, the agent should:
1. Read the original with `metabox-get-field-group-schema key=<k> include_fields=true`.
2. Use `metabox-create-field-group` with the same shape but a **different key** to make a DB copy.
3. Tell the user to either remove the PHP registration (so the DB copy takes over) or use the new key directly.

The agent should NEVER fabricate a way to edit PHP-registered entities — the file is outside its writable surface.

## Target model for read-values / write-values

A `target` selects which entity holds the values:

```jsonc
{
  "target": {
    "type": "post|term|user|comment|settings",
    "id":   123 | "settings-page-slug"
  }
}
```

- `post`: numeric WP_Post id.
- `term`: numeric term id.
- `user`: numeric user id.
- `comment`: numeric comment id.
- `settings`: slug of a Meta Box settings page (the integration resolves the slug to the underlying option key).

The integration normalises this to Meta Box's `(object_id, object_type)` pair and routes through `rwmb_meta()` / `rwmb_set_meta()` etc.

## Edit semantics

`metabox-edit-field-group`, `metabox-edit-post-type`, `metabox-edit-taxonomy`, `metabox-edit-settings-page`, `metabox-edit-relationship` all follow **merge semantics**:

- Top-level keys you pass replace the corresponding key.
- For array-valued keys (`fields`, `post_types`, `supports`, `args`), you must pass the **entire desired array** — the integration does NOT do a deep merge that would silently lose deletions.

This mirrors ACF's edit semantics. Document this to the user when you propose a change so they know to pass the whole array.

## Relationships — definition vs connection (and the persistence gotcha)

Relationships have two surfaces:

1. **Definition** (`list-relationships`, `get-relationship`, `manage-relationships`): the **schema** — "books connect to authors". Define with `from` and `to`, each either a post-type slug string (shortcut for `post` object type) or an object `{object_type: 'post'|'term'|'user', post_types?: ['x','y']}`. Optional `reciprocal: true` for symmetric edges.
2. **Connection** (`connect-objects`, `disconnect-objects`, `list-connected-objects`): the **runtime data** — "book #45 is connected to author #12 via the `books_to_authors` relationship". These use the WP-native input shape: `relationship_id`, `from_id`, `to_id`.

### The persistence gotcha — MB Relationships definitions are PHP-only

MB Relationships does NOT persist relationship **definitions** to the database. `MB_Relationships_API::register()` is a per-request PHP filter call. When you call `metabox-create-relationship`, the integration registers the definition in the current request and returns a `persistence_warning` like:

> "MB Relationship definitions are PHP-registered and not persisted in the database. This registration applies to the current request only. Add MB_Relationships_API::register() to your theme or plugin code (or a must-use plugin) to persist it across requests."

The **connections themselves** (the edges between objects) DO persist in a custom DB table. So a workflow that creates a relationship and connects objects produces a half-persisted result: connections survive but the definition is gone on the next request.

**Mitigation**: tell the user to copy the `settings` payload returned by `create-relationship` into a `MB_Relationships_API::register([...])` call in their theme `functions.php` or a must-use plugin. Show them the snippet — do not pretend the registration is persistent.

`list-relationships` always returns `source: 'php'`, never `'db'`. `manage-relationships` therefore cannot meaningfully prevent `metabox_php_registered` errors on edit/delete — edit re-registers within the request, delete removes from the request registry only.

## Progressive disclosure conventions

Apply per the integration:

- `get-field-group-schema` returns `{ key, title, source, post_types, field_count }` by default. Pass `include_fields: true` to inflate the fields array. Realistic field groups can be 200+ lines of JSON.
- `get-field-type-schema` returns the option matrix for ONE field type — call this right before creating/editing a field. Avoids dumping every type's options in every response.
- `list-connected-objects` defaults to 25 per page. Pass `limit` (max 200) and `offset` to page through.

## Workflow example — modeling a small CRM

User asks: "Add a Customer post type with a phone field, and connect Customers to Companies."

1. `metabox-check-setup` → confirm `cpt_active: true`, `relationships_active: true`.
2. `metabox-list-post-types` → see what already exists.
3. `metabox-create-post-type` with slug `customer`, singular_label `Customer`, plural_label `Customers`.
4. `metabox-create-field-group` (needs `builder_active`) with `id: customer_details`, `post_types: ['customer']`, `fields: [{ name: 'phone', type: 'text', label: 'Phone' }]`.
5. `metabox-list-relationships` → see if a customer↔company link already exists.
6. `metabox-create-relationship` with `id: customer_company`, `from: 'customer'`, `to: 'company'`. **Capture the returned `settings` payload** and ask the user to add `MB_Relationships_API::register($settings)` to their theme — without that step the definition vanishes on the next request.
7. To populate: `metabox-connect-objects relationship_id=customer_company from_id=123 to_id=45`.
8. To verify: `metabox-list-connected-objects relationship_id=customer_company from_id=123`.

## Reading and writing values — quick recipes

`read-values` and `write-values` operate on the **target** + field-id pair directly — they do not require a `field_group` selector because Meta Box reads/writes through the meta-key namespace, which is shared across field groups attached to the same object.

```jsonc
// Read all known meta-key values from a post
{ "ability": "metabox-read-values", "args": { "target": { "type": "post", "id": 42 } } }

// Read specific fields only (faster, smaller payload)
{ "ability": "metabox-read-values", "args": { "target": { "type": "settings", "id": "site-options" }, "fields": ["analytics_id"] } }

// Hint the integration about which field group's schema to consult (for clone/file/image handling)
{ "ability": "metabox-read-values", "args": { "target": { "type": "post", "id": 42 }, "field_group_key": "customer_details" } }

// Write multiple values on a term
{ "ability": "metabox-write-values", "args": { "target": { "type": "term", "id": 7 }, "values": { "color": "#abc", "icon": "star" } } }
```

`field_group_key` is **optional** on both sides. On read, pass it to scope the read to that group's fields (clone/file/image values come back decoded against the right schema). On write, pass it as a typo guard: every key in `values` is validated against the group BEFORE anything is written, so a misspelled field ID fails the whole call instead of silently creating a stray meta key.

## Recovery from partial failures

WordPress has no transactional rollback. If a multi-step workflow fails midway:
- `metabox-delete-*` abilities are **idempotent by key** — re-running `delete-field-group key=customer_details` after a partial create returns success cleanly.
- The agent should clean up by deleting what was successfully created, then retry from the failed step.

## Out of scope (deliberately)

The following Meta Box extensions are out of scope for this integration. If the user asks for one of these, tell them the integration does not cover it — do not pretend to handle it.

- **MB Blocks** — registering Gutenberg blocks via Meta Box. Block registration is a code surface, not a data surface; the integration does not generate or edit block PHP.
- **MB Frontend Submission** — rendering submission forms on the public site. UI/templating concern outside the abilities surface.
- **MB Views** — template builder for displaying Meta Box fields. Templating/rendering concern.
- **MB Geolocation** — autofilling address fields from a map. Field-runtime behavior with no API surface to manage.
- **MB Revision** — versioning of field values. The integration reads/writes the current value only.
- **MB Custom Table — value IO**. The flag is exposed (`custom_table_active`) but read/write-values do not route to custom tables. See the Custom-table caveat above.

## Things to NOT do

- Don't fabricate a way to edit PHP-registered field groups / CPTs / etc. Tell the user they need to edit the PHP file or duplicate the entity in DB.
- Don't use `update_post_meta` directly to write Meta Box field values — go through `metabox-write-values` so clone/file/image fields are handled.
- Don't create relationships without checking the target object types are valid (post types must exist; users/terms must exist).
- Don't bulk-connect more than ~50 objects in one workflow — each call fires the `mbr_add` action, which other extensions may listen to. Batch carefully.

## Things that are safe and encouraged

- Run `metabox-check-setup` at the start of any session involving Meta Box.
- Use `get-field-type-schema` whenever building or editing a complex field — avoids guessing option keys.
- Use `list-connected-objects` to inspect existing relationships before connecting new ones (avoid duplicate edges).
- Prefer `key` selectors over `id` / `slug` — they work for both PHP and DB sources uniformly.
