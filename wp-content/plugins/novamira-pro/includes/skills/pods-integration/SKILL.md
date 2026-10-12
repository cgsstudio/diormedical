---
name: pods-integration
description: Activate when working with the Pods plugin on a WordPress site — Pods-defined custom post types, taxonomies, advanced content types (ACT), settings pages, fields, groups, and items. Covers the Pods 3.x Whatsit data model, the group-required-for-fields rule, the discover→list→get→modify→verify workflow, and how to populate items end-to-end. Treats Pods as the site's chosen content-modeling tool; coexistence with ACF/JetEngine/ASE/Meta Box/ACPT is a footnote.
---

# Pods Integration

## When to use

Activate this skill when:
- The user mentions Pods or `pods_api()`.
- The user asks to create, edit, or delete a custom post type, taxonomy, advanced content type, settings pod, field, or group on a site that has Pods active.
- The user asks to read or write item-level content on a Pods-managed pod (post / term / user / settings).
- `pods-check-setup` returns `api_available: true`.

## When NOT to use

- If ACF, JetEngine, ASE, Meta Box, or ACPT is the primary content-modeling tool on the site, use the corresponding integration skill instead.
- For a plugin-agnostic representation of the site's content model (snapshots, audits, cross-plugin migrations), activate the `content-model-schema` skill alongside this one.

## Discover before you act

Always start with `novamira/pods-check-setup`. It returns:

| Field | Meaning |
|---|---|
| `version` | Installed Pods version |
| `api_available` | Whether `pods_api()` is callable — true means abilities can run |
| `act_component_active` | Advanced Content Types component enabled — required for ACT pods |
| `pod_counts` | `{post_type, taxonomy, settings, pod, …}` — counts per pod type |

(Note: if this ability is missing from your tool list, Pods is not active or below the supported minimum — there is nothing for the agent to do.)

If `act_component_active` is false, `pods-create-act` is not callable — recommend the user enable the ACT component or use a regular post-type pod instead.

Then list before getting: `pods-list-pods` enumerates everything; `pods-get-pod name=<slug>` returns the pod definition + options. By default `pods-get-pod` omits fields and groups for payload size — pass `include_fields: true` and/or `include_groups: true` to inflate, OR call the dedicated drill-down abilities (see Progressive disclosure below).

## The Pods 3.x data model

Pods 3.x uses the "Whatsit" object model. Every entity is a Whatsit instance:

- **Pod** — the top-level definition (CPT / taxonomy / ACT / settings / extended built-in). Has `name` (slug), `type`, `options` blob.
- **Group** — a logical grouping of fields inside a pod. **Required in 3.x**: every field MUST belong to a group.
- **Field** — the actual input definition. Has `name`, `type`, type-specific options. Belongs to one group.
- **Item** — a content row (post for CPT pods, term for taxonomy pods, etc.).

## The group-required-for-fields rule

Pods 3.0+ enforces that every field belongs to a group. If you try to `pods-create-field` on a pod with no groups yet, it fails. The agent's workflow for setting up a new pod is:

1. `pods-create-cpt` (or `pods-create-taxonomy` / `pods-create-act` / `pods-create-settings`)
2. `pods-create-group pod=<slug> name=details`
3. `pods-create-field pod=<slug> group=details name=<field_name> type=<type> ...`

Skip step 2 and you get `pods-create-field` errors.

## Field type discovery

Pods has dozens of field types, each with its own option matrix. Use `pods-get-field-type-schema field_type=<type>` to fetch the option keys for one type at a time. Avoid hardcoding options — ask for the schema.

Canonical types accepted by Pods 3.x: `text`, `paragraph` (multi-line text — Pods does NOT have a `textarea` type), `wysiwyg`, `number`, `currency`, `date`, `datetime`, `time`, `email`, `phone`, `password`, `website`, `color`, `slug`, `boolean`, `pick`, `file`, `avatar`, `oembed`, `code`, plus layout-only `heading` and `html`. Pods does NOT have a `repeater` field type — instead any field can be marked `repeatable: true` to allow multiple values. Always call `pods-get-field-type-schema field_type=<t>` to discover the option keys before configuring.

The `pick` type is especially important — it covers post-to-post, post-to-term, post-to-user, and post-to-custom-list relationships. Always call `pods-get-field-type-schema field_type=pick` before configuring a relationship field.

## Progressive disclosure

Pods has the most aggressive progressive-disclosure design among our 6 integrations. Use the abilities in this order:

- `pods-list-pods` returns a one-line summary per pod
- `pods-get-pod name=<slug>` returns identity + options. **No fields, no groups by default.**
- `pods-list-groups pod=<slug>` enumerates groups (id, name, label, weight)
- `pods-list-fields pod=<slug>` enumerates fields (name, label, type, group_id). **Filter by `group_id` to page through a field-heavy pod one group at a time.** Also filter by `type` to find all fields of a kind.
- `pods-get-field pod=<slug> name=<field_name>` returns the full type-specific options blob for one field

This pattern keeps payloads under control on pods with 50+ fields.

## Item CRUD

For content (not schema):

- `pods-list-items pod=<slug>` paginated list with `limit`, `offset`, `where`, `orderby`. Use `fields=[]` to pick only specific fields for projection.
- `pods-get-item pod=<slug> id=<item_id>` full row including all fields.
- `pods-create-item pod=<slug> data={...}` creates a new item. For CPT/taxonomy pods, this calls `wp_insert_post` / `wp_insert_term` internally.
- `pods-edit-item pod=<slug> id=<item_id> data={...}` partial update — only the keys in `data` are written.
- `pods-delete-item pod=<slug> id=<item_id>` removes the row. For CPT pods, also deletes the WP post unless `delete_wp_object=false`.

For settings pods (`type: settings`), the entire pod is a single options blob — there is no per-row id. `pods-get-item` and `pods-edit-item` both still require an `id` parameter; for settings pods pass `id: 0` (the convention Pods accepts internally). The fields you read or write are then keyed against the settings pod's option store, not against a specific row.

## Extend-builtin

`pods-extend-builtin` attaches a Pods schema (groups + fields) to a built-in WP entity: post, page, attachment, user, comment, or one of WP's registered taxonomies. This is how to add custom fields to `post` without creating a new CPT. After extending, treat the pod just like a normal CPT pod for fields and items.

## Reserved field names

Pods refuses field names that collide with WP query vars (`author`, `category`, `post`, `tag`, ...). The reserved list is maintained via the `pods_reserved_keywords` filter — see [docs.pods.io/fields/settings-reserved-list-of-names](https://docs.pods.io/fields/settings-reserved-list-of-names/). When the agent hits a reserved-name error, suggest a prefixed alternative (`book_author` instead of `author`).

## Cache behavior

Pods caches pod definitions and field lookups within a request. After a write (create/edit field, group, or pod), the cache is automatically flushed by our abilities so the next read sees fresh data. The agent doesn't need to do anything special — read-after-write works.

## Workflow example — modeling a Book CPT with custom fields

User asks: "Set up a Book CPT with title, author, and isbn fields."

1. `pods-check-setup` → confirm `api_available: true`.
2. `pods-list-pods` → see what already exists.
3. `pods-create-cpt name=book label="Books" singular_label="Book"` → CPT registered.
4. `pods-create-group pod=book name=details label="Book Details"` → required before fields.
5. `pods-get-field-type-schema field_type=text` → discover text-field options.
6. `pods-create-field pod=book group=details name=author type=text label="Author"`.
7. `pods-create-field pod=book group=details name=isbn type=text label="ISBN"`.
8. To populate: `pods-create-item pod=book data={post_title: "...", author: "...", isbn: "..."}`.
9. To verify: `pods-get-item pod=book id=<new_id>`.

## Things to NOT do

- Don't try to create fields without a group on Pods 3.x — it will fail.
- Don't hardcode field type options — call `pods-get-field-type-schema` first.
- Don't bypass `pods-create-item` to write content directly via `wp_insert_post` — the Pods data layer handles relationship serialization and meta key naming differently from raw post inserts.
- Don't use reserved field names — Pods will reject them.

## Things that are safe and encouraged

- Run `pods-check-setup` at the start of every session involving Pods.
- Use `pods-list-fields group_id=<id>` to page through field-heavy pods.
- Use `pods-get-field-type-schema` whenever configuring a complex field (pick, file, oembed, currency).
- Use `extend-builtin` instead of creating a new CPT when the user just wants to add custom fields to `post` or `page`.
- For relationships, configure `pick_object` from the field-type schema before populating.
