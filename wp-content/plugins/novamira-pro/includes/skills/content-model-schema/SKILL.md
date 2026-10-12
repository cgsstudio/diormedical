---
name: content-model-schema
description: Activate when reasoning about a WordPress site's content model in plugin-agnostic terms — snapshotting, auditing, migrating, diffing, replicating, or documenting field groups, fields, post types, taxonomies, and options pages across ACF / Pods / JetEngine / ASE / Meta Box / ACPT. Defines a canonical vocabulary (~18 field types + universal modifiers + validation block) for capturing schema independently of which plugin owns it. Vocabulary skill, not a workflow playbook.
---

# Content Model Schema

A plugin-agnostic vocabulary for representing a WordPress site's content model — the structure of post types, taxonomies, options pages, field groups, and fields. Use it as the **target shape** when you need to reason about content schema across plugins or describe it in neutral terms.

## When to use

Activate this skill when:

- The user wants to snapshot the structure of a site's content model.
- The user wants to compare two environments (staging vs prod, two client sites).
- The user wants to migrate a content model from one plugin to another. Combine this skill with the per-plugin integration skill for the source and the target — and use the `content-model-migration` skill for the four-phase audit → plan → execute → verify workflow.
- The user wants to audit "what would I lose moving from plugin X to plugin Y?" — the dedicated playbook is `content-model-migration`; this skill provides only the vocabulary it uses.
- The user wants documentation of the content schema in plugin-neutral language.

## When NOT to use

- For single-plugin work — the corresponding `<plugin>-integration` skill is enough on its own.
- For migrating stored *values* (postmeta, term meta, custom-table rows). This skill covers schemas only; value migration is a separate problem with plugin-specific storage layouts.

## The vocabulary — 18 canonical field types

Each type is defined by semantics, not by which plugin happens to call it that name. Every plugin's native types map onto one of these (possibly with specialization via `format`, `subtype`, or modifiers).

| Type | Semantic definition |
|---|---|
| `text` | Short string. Use `format: slug \| password \| phone \| email \| url` to specialize. |
| `paragraph` | Long plain text (no HTML). |
| `rich_text` | HTML markup. |
| `number` | Numeric. Use `subtype: integer \| decimal`. |
| `boolean` | True / false. |
| `date` | Calendar date. |
| `time` | Time of day. |
| `datetime` | Combined date + time. |
| `color` | Color value (hex / rgba). |
| `media` | Reference to a media asset. Use `accepts: ["image/*", "application/pdf"]` to restrict. |
| `relationship` | Reference to a WordPress entity. Specify `target_kind: post \| term \| user` and optionally `target: <slug>` to restrict. |
| `choice` | Enumeration. Provide `choices: [{ value, label }]` and `cardinality: single \| multi`. |
| `repeater` | Homogeneous list of sub-fields. Carries `fields: [...]` (the row template). |
| `group` | Single instance of nested sub-fields. Carries `fields: [...]`. |
| `flexible` | Repeater with multiple alternate layouts. Carries `layouts: [{ name, fields: [...] }]`. |
| `code` | Source code. Use `language: php \| js \| css \| html \| sql \| ...`. |
| `oembed` | URL that resolves to embeddable media. |
| `coordinates` | Geographic point (lat / lng). |

If a plugin's native type doesn't fit any of the above, model it with the closest match plus modifiers, and surface the loss to the user. Do not invent new canonical types ad-hoc.

## Universal modifiers

Apply to any field regardless of type:

- `required: boolean`
- `repeatable: boolean` — the same field can hold N independent instances. Different from `cardinality: multi` (see below).
- `localizable: boolean` — opt-in for WPML / Polylang translation.
- `searchable: boolean` — indexed for admin search / filtering.
- `default: <type-appropriate>` — initial value.
- `cardinality: single | multi` — restricted to `choice` and `relationship`. Orthogonal to `repeatable`: `cardinality: multi` means ONE field instance points to N entities; `repeatable: true` means N field instances, each pointing to one entity.

Also useful and universal:

- `label: string`, `description: string`, `placeholder: string`

## Validation block

Wide vocabulary covering what every field plugin recognizes. Pick only the keys relevant to the type; plugins that don't enforce a given rule simply ignore it on import.

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
  "accepts": ["<mime>", ...]
}
```

## Top-level shape

A complete content-model snapshot has up to six arrays at the root. All are optional — emit only what's present on the site.

```json
{
  "post_types": [
    {
      "slug": "book",
      "label_singular": "Book",
      "label_plural": "Books",
      "hierarchical": false,
      "public": true,
      "supports": ["title", "editor", "thumbnail", "excerpt"]
    }
  ],
  "content_types": [
    {
      "slug": "review",
      "label_singular": "Review",
      "label_plural": "Reviews",
      "storage": "custom_table",
      "key_field": "id"
    }
  ],
  "taxonomies": [
    {
      "slug": "genre",
      "label_singular": "Genre",
      "label_plural": "Genres",
      "post_types": ["book"],
      "hierarchical": true,
      "public": true
    }
  ],
  "options_pages": [
    {
      "slug": "book-settings",
      "label": "Book Settings",
      "capability": "manage_options",
      "parent_slug": "options-general.php",
      "storage_key": "book_settings_options"
    }
  ],
  "field_groups": [
    {
      "name": "book_details",
      "label": "Book Details",
      "targets": [
        { "kind": "post_type", "slug": "book" }
      ],
      "fields": [
        { "name": "isbn", "type": "text", "label": "ISBN", "required": true,
          "validation": { "pattern": "^[0-9-]{10,17}$" } },
        { "name": "summary", "type": "rich_text", "label": "Summary" }
      ]
    }
  ],
  "warnings": [
    {
      "path": "field_groups[0].fields[3]",
      "reason": "Pods sister_id (bidirectional pick) — emitted as one-way relationship; reciprocal save behaviour is not represented.",
      "severity": "structural"
    }
  ]
}
```

`targets[].kind ∈ { post_type, content_type, taxonomy, options_page, user, comment, term }`.

### Distinguishing `post_types` from `content_types`

- `post_types` are first-class WordPress post types stored in `wp_posts`. Use for ACF/Pods/JetEngine/ASE/Meta Box/ACPT CPTs, as well as ACF Pro and ACPT options-page-attached posts.
- `content_types` are content categories that do NOT live in `wp_posts` — they have their own database table. The two canonical examples:
  - **JetEngine CCT** — custom-table records, separate from `wp_posts`.
  - **Pods ACT** with `storage: "table"` (the default and the point of ACT) — backed by `wp_pods_<name>`. A Pods ACT with `storage: "meta"` is effectively a CPT and belongs in `post_types[]` instead.
- Use the `storage` field on `content_types[]` to distinguish (`custom_table` is currently the only non-trivial value).
- A field group can target a `content_type` exactly like a `post_type` — set `targets[].kind: "content_type"`.

### Options-page slug vs storage key

`options_pages[].slug` is the admin menu identifier (what shows in the URL).
`options_pages[].storage_key` is the `wp_options` row name (or equivalent) where values are actually persisted.

Across plugins these are not always the same:
- ACF: `menu_slug` ≠ `post_id` (the underlying ACF option key).
- ACPT: menu_slug == option_name by convention but they're separate concepts.
- JetEngine: declared separately as `slug` and `slug` (storage), often equal but not enforced.

When you don't know the storage key, omit it — the importing plugin will derive a default.

### Warnings

`warnings[]` is an out-of-band channel for things the canonical shape *cannot* represent. Each entry:

- `path` — JSON-pointer-ish reference into the snapshot.
- `reason` — human-readable explanation of what was lost or transformed.
- `severity: structural | informational` — `structural` when the loss changes semantics (bidirectional → unidirectional, custom-table → post type), `informational` when only UI sugar was dropped (admin column order, icon hint).

Warnings are produced by the **export side** (when reading from a plugin and not everything fits in the canonical shape) and surfaced to the user before the import side acts.

## Mini-examples

Atomic field with validation:

```json
{ "name": "rating", "type": "number", "subtype": "integer", "label": "Rating",
  "validation": { "min": 1, "max": 5 } }
```

Relationship — single vs multi cardinality:

```json
{ "name": "editor", "type": "relationship", "label": "Editor",
  "target_kind": "user", "cardinality": "single" }

{ "name": "tags", "type": "relationship", "label": "Tags",
  "target_kind": "term", "target": "post_tag", "cardinality": "multi" }
```

Nested repeater:

```json
{ "name": "chapters", "type": "repeater", "label": "Chapters",
  "fields": [
    { "name": "title", "type": "text" },
    { "name": "pages", "type": "number", "subtype": "integer" }
  ] }
```

Flexible content with two layouts:

```json
{ "name": "body", "type": "flexible", "label": "Body",
  "layouts": [
    { "name": "text_block", "fields": [
      { "name": "content", "type": "rich_text" }
    ] },
    { "name": "image_block", "fields": [
      { "name": "image", "type": "media", "accepts": ["image/*"] },
      { "name": "caption", "type": "text" }
    ] }
  ] }
```

## Frontier of portability

The vocabulary deliberately does NOT capture these concepts. They survive when a snapshot is round-tripped within the same plugin (because the source plugin still owns the data), but are lost when crossing plugin boundaries. Each loss should produce a `warnings[]` entry on export so the user sees it before the import side acts.

- **Conditional logic / visibility rules** — ACPT visibility, ACF conditional display. Lost; warning severity `informational` (no data loss) or `structural` if the user explicitly relied on it.
- **Admin UI hints** — column visibility, admin position, icons, JetEngine `filterable_in_admin`. Lost; warning severity `informational`.
- **Bidirectional relationships** — Pods `sister_id`. Becomes two independent uni-directional `relationship` fields; pair them by convention but the reciprocal-save behaviour is not reproduced. Warning severity `structural`.
- **Custom-table content storage** — JetEngine CCT and Pods ACT (with `storage: "table"`) both go in `content_types[]` with `storage: "custom_table"`. Migrating to a plugin without custom-table support requires converting to `post_types[]` (records will land in `wp_posts`); this conversion is explicit and the user must approve. Warning severity `structural`.
- **Plugin-specific structural fields** — Meta Box `button`, `divider`, `heading`, `custom_html`. No canonical representation; drop with a warning of severity `informational`.
- **Computed / virtual fields** — fields whose value is derived at read time. Not part of the schema vocabulary. Warning severity `structural` if the agent assumed it would be migrated.

If a user asks to preserve any of these across a migration, the answer is: the canonical schema does not carry them; recreate manually after import using the target plugin's UI or the corresponding `<plugin>-integration` skill.

## What this skill does NOT do

- It does NOT prescribe a workflow. No "read source → translate → write target" recipe. The agent orchestrates using the per-plugin integration skills it already knows.
- It does NOT include translation tables (canonical ↔ plugin-native). The agent infers mappings from this skill's vocabulary plus its existing knowledge of each plugin's surface.
- It does NOT version the JSON. The representation is built and consumed within a single conversation; no archived files, no schema evolution to manage.
- It does NOT cover value migration. Schemas only.
