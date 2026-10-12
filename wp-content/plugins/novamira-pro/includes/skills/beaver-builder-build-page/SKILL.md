---
name: beaver-builder-build-page
description: Build, inspect, or prepare to edit pages and templates in Beaver Builder. Activate when the user asks to create, rebuild, audit, migrate, or work with Beaver Builder layouts, modules, rows, columns, saved templates, module settings/styling, post loops (post-grid/carousel/slider), or Beaver Themer dynamic data and theme layouts. Covers setup discovery, module catalog lookup, per-module settings-schema lookup, and layout inspection.
---

# Building With Beaver Builder

Use this skill when the target editor is Beaver Builder. The specialization can
confirm the setup, discover native modules, list existing builder-managed
layouts, inspect one layout's node tree, replace a layout, and perform targeted
node CRUD.

Prefer dedicated `novamira/beaver-builder-*` abilities for Beaver Builder
discovery and reads. Use generic WordPress abilities only for supporting steps
such as creating a placeholder post before a future write workflow exists.

## Hard Rules

1. **Do not use HTML modules as a shortcut.** Beaver Builder pages should be
   composed from native rows, columns, and content modules. Do not use the HTML
   module for page sections, cards, stats, pricing tables, layout wrappers,
   visual styling, CSS injection, or a `<style>` block. Use an HTML module only
   when the user explicitly asks to embed a real code snippet; when the goal is
   a Beaver-native page, the expected HTML module count is `0`. **This rule is
   enforced at the code level:** `set-layout`, `add-node`, and `edit-node` reject
   any write that creates an HTML (`html` slug) module and return
   `beaver_builder_html_module_not_allowed` — `set-layout` names the offending
   node id(s). The write only goes through when you pass `allow_html_module: true`,
   which you must do **only** for a genuine, user-requested code embed, never to
   satisfy layout, styling, CSS, or content. Reach for native modules
   (`list-modules` / `get-module`) first.
2. **Discover before acting.** Call `beaver-builder-check-setup`, then
   `beaver-builder-list-modules` or `beaver-builder-list-layouts` depending on
   the task.
3. **Respect supported post types.** A post type must be in
   `supported_post_types` before Beaver Builder will behave like the editor for
   that post.
4. **Treat published and draft layouts separately.** Beaver Builder stores
   published layout data and draft layout data in separate meta keys. Read the
   right status before planning a change.
5. **Write complete structure.** A normal section is row -> column-group ->
   column -> module. Modules normally belong under columns; container modules
   that accept children, such as Beaver's Box module, may also sit directly under
   rows and contain child modules.

## Domain Model

Beaver Builder stores layouts in post meta:

- `_fl_builder_enabled` marks a post/template as builder-managed.
- `_fl_builder_data` is the published layout node map.
- `_fl_builder_draft` is the draft layout node map.
- `_fl_builder_data_settings` and `_fl_builder_draft_settings` hold layout-level
  settings.

The node tree is a flat map keyed by Beaver's node id. Each node has:

- `type`: usually `row`, `column`, or `module`.
- `parent`: parent node id, empty on roots.
- `position`: sibling order.
- `settings`: module/row/column settings. Module nodes usually store their
  module slug in `settings.type`.
- `global` / `dynamic`: flags for reusable or dynamically edited nodes.

## Discovery Workflow

1. `novamira/beaver-builder-check-setup`
   - Confirm `active` and `min_satisfied`.
   - Check `supported_post_types` before choosing a target post type.
   - Check `registered_modules_count` and `enabled_modules_count`; zero means
     the builder did not initialize correctly.
   - `theme_builder_active` tells you whether Beaver Themer is present.
   - Read `issues` and surface blocking setup problems to the user.

2. `novamira/beaver-builder-list-modules`
   - Use `search` to find a module by intent (`heading`, `button`, `photo`,
     `form`, `post`, `callout`, `separator`).
   - Use `category` or `group` to narrow when the catalog is large.
   - Keep `include_disabled=false` unless diagnosing why a module is missing.
   - The returned `slug` is the module or alias key; `module` is the underlying
     base module.
   - This is the catalog (slugs only). It does not list a module's setting
     keys — use `get-module` for that.

3. `novamira/beaver-builder-get-module`
   - Progressive disclosure: `list-modules` is the catalog; `get-module slug=<slug>`
     returns one module's settings schema so you set the **real** keys instead of
     guessing them (is a heading's text `heading` / `text` / `title`? — it's
     `heading`).
   - **Narrow with `search` / `tab` / `section` instead of reading the whole
     module.** A big module like `post-grid` has ~96 fields; filter to just what you
     need: `get-module slug=post-grid search="post"` for the query keys,
     `search="color"` or `tab="style"` for styling. `search` matches a field's key
     OR label (case-insensitive); `tab` and `section` scope to part of the form;
     the three combine with AND. `fields_total` / `fields_matched` then report the
     match count.
   - `fields` is the typed list walked from the module form: each row has `key`,
     `type` (`text`, `select`, `color`, `unit`, `photo`, `link`, `typography`, …),
     `label`, `tab`, `section`, that field's own `default`, and — for select-style
     fields — the allowed `options`. Those `key`s are exactly what you put in a
     node's `settings`.
   - `defaults` is the flat default-settings map Beaver fills in for the module. It
     is **off by default** (`include_defaults` defaults to `false`) because each
     field row already carries its own `default`; set `include_defaults=true` only
     when you need the exhaustive/responsive keys the form walk does not surface.
   - Output is bounded: `max_fields`, `max_options`, and `max_field_bytes` cap the
     payload, and `fields_truncated` / `options_truncated` flag any cut.
   - Call it for each module you intend to write before `set-layout` / `add-node`
     / `edit-node`.

4. `novamira/beaver-builder-get-structure-schema`
   - The counterpart of `get-module` for the structural nodes that are **not**
     modules: pass `type=row` or `type=column` for that node's settings schema
     (background, padding/margin, border, visibility; the column `size`).
   - Same progressive disclosure as `get-module`: narrow with `search` / `tab` /
     `section` (they AND-combine) instead of reading the whole ~80-field form —
     e.g. `type=row search="bg"` for background keys, `type=column search="size"`
     for the width. `include_defaults` is off by default.
   - Use it before styling a row/column so you set real keys — and remember
     `bg_type` must be set (not the default `none`) for any background to render.

5. `novamira/beaver-builder-list-layouts`
   - Lists posts/templates where Beaver Builder is enabled.
   - Use `post_type`, `status`, `search`, `limit`, and `offset` to narrow.
   - Use the returned `id` with `beaver-builder-get-layout`.
   - `node_counts` gives a quick sense of size before fetching settings.

6. `novamira/beaver-builder-get-layout`
   - Pass `post=<id>`.
   - Use `status=published` by default; use `status=draft` when inspecting
     unpublished builder changes.
   - Leave `include_settings=false` for navigation; set it true only when you
     need the actual module settings.
   - Use `include_layout_settings=true` only when checking page-level CSS/JS or
     layout settings.

## Common Workflows

### Build or Replace a Page

1. `beaver-builder-check-setup`
2. `beaver-builder-list-modules` for every module type you intend to use
3. `beaver-builder-get-module slug=<slug>` for each of those modules to learn the
   exact setting keys before composing the tree — narrow with the filters rather
   than dumping every field (a Posts module's query keys: `get-module
   slug=post-grid search="post"`; a module's style keys: `get-module … search="color"`
   or `tab="style"`)
4. `beaver-builder-set-layout post=<id> nodes=[...]` — a tree that contains an
   HTML module is rejected (Hard Rule #1, enforced) unless you pass
   `allow_html_module: true`; build sections/cards/styling from native modules.
5. `beaver-builder-get-layout post=<id> include_settings=true` to verify the
   saved tree and Beaver defaults

Use `status=both` unless the user explicitly asks to edit only the draft or only
the published layout.

### Targeted Node Edit

1. `beaver-builder-get-layout post=<id> include_settings=true`
2. Use the returned node id with one of:
   - `beaver-builder-add-node`
   - `beaver-builder-edit-node`
   - `beaver-builder-move-node`
   - `beaver-builder-delete-node`
3. Re-read after structural writes because sibling positions are compacted.

### Audit an Existing Beaver Builder Page

1. `beaver-builder-check-setup`
2. `beaver-builder-list-layouts search="<page title>"`
3. `beaver-builder-get-layout post=<id>`
4. If the tree is large, re-run with `include_settings=true` and a targeted
   `max_nodes` only when you need settings details.

### Find the Right Native Module

1. `beaver-builder-list-modules search="heading"`
2. If no row appears, retry with `include_disabled=true` to distinguish "not
   installed" from "disabled".
3. Use the module's `slug`/`module` in planning, then `beaver-builder-get-module
   slug=<slug>` to read its setting keys (narrow large modules with
   `search=`/`tab=`/`section=`). Do not substitute a raw HTML module for missing
   native structure, visual styling, or CSS — the write abilities enforce this and
   reject an HTML module unless `allow_html_module: true` is passed for a genuine
   code embed.

### Compare Published and Draft Layouts

1. `beaver-builder-get-layout post=<id> status=published`
2. `beaver-builder-get-layout post=<id> status=draft`
3. Compare `node_counts`, module node ids, and settings only after enabling
   `include_settings=true`.

## Styling

Beaver Builder styling lives **inside each node's `settings`** — the Style and
Advanced fields of the module, column, and row. There is no separate stylesheet
or class-store entity: a heading's text color is the `color` key in its settings,
a row's background is the `bg_type` selector **plus** its matching key (`bg_color`,
or `bg_gradient`, or `bg_image`/`bg_image_url`), padding/margin are
`margin`/`padding` (and their responsive variants). Set these on the node, not in
CSS.

- **Discover the exact style keys with `get-module`, never guess them — and filter
  to the styling surface** with `tab="style"` (plus the spacing/visibility fields
  under `tab="advanced"`), or `search="color"` / `search="border"` to jump straight
  to a property family instead of reading all ~96 fields. Each row's `type` tells
  you the value shape (`color` → a hex string like `ff0000` *without* the `#`,
  `unit` → a number, `typography` → an object, `select` → one of its `options`).
  Example: `get-module slug=heading tab="style"` returns `{key:"color",
  type:"color", tab:"style"}` and `{key:"typography", type:"typography",
  tab:"style"}`.
- **Rows and columns are NOT modules** — `get-module` does not describe them.
  Discover their style/advanced keys with **`get-structure-schema type=row`** /
  **`type=column`** (same filters: `search`, `tab`, `section`). E.g.
  `get-structure-schema type=row search="bg"` gives `bg_type`, `bg_color`,
  `bg_gradient`, `bg_image`, …; `type=column search="size"` gives the column width.
- **Backgrounds need `bg_type`.** A row/column background only renders when
  `bg_type` is set to the kind you want: `color` (+`bg_color`), `gradient`
  (+`bg_gradient` = `{type,angle,position,colors,stops}`), `photo` (+`bg_image`, or
  `bg_image_source:"url"` + `bg_image_url` for an external image), `video`, or
  `parallax`. `bg_type` defaults to `none`, so **setting `bg_color` alone renders
  nothing** — the most common "my background didn't show" bug.
- **Every column needs a width (`size`).** A column with no `size` renders
  collapsed (`width:0`, with content wrapping one word per line). The write
  abilities now auto-default a sizeless column to an equal share of its
  column-group (3 bare columns → ~33.33 each), but set `size` explicitly for any
  non-equal split (e.g. a 70/30 content+sidebar).
- **Prefer Beaver Global Colors (reusable design tokens) over hardcoded hex.**
  Discover them with **`list-global-colors`**; if a needed brand/accent color is
  missing, **`create-global-color`** (returns a `uid` + `css_var`) instead of
  freezing a one-off hex on every node, so a later palette change cascades
  everywhere (`edit-global-color` / `delete-global-color` manage them). Bind a node
  setting to a token by writing a connection on the node:
  `settings.connections.<field> = {object:"site", property:"global_color_<uid>", field:"<field>"}`
  (via set-layout / edit-node). Beaver has **no** global *font* tokens — typography
  is a site-wide per-element default (text / h1–h6 / link / button): read it with
  **`get-global-typography`** and set it with **`edit-global-typography`** rather
  than repeating font size/weight on every heading. Reach for a raw hex/px only for
  a genuine one-off. (Mirrors the project policy of steering toward the builder's
  token system rather than inline literals.)
- **Never inject CSS or use an HTML module for styling** (Hard Rule #1, enforced —
  the write abilities reject an `html`-slug module unless `allow_html_module: true`).
  There is a native field for layout, spacing, color, typography, border, and
  visibility on every node — discover it with `get-module` rather than dropping a
  `<style>` block or an HTML module. Page-level custom CSS (in layout settings) is a
  last resort for something no field covers, never the default path for visual
  styling.
- Styled builds render through Beaver's generated per-layout CSS, so set the keys
  on the node and re-read with `get-layout include_settings=true` to confirm they
  persisted; the structure must still be complete (`row → column-group → column →
  module`), because a styled but malformed subtree (e.g. a column placed directly
  under a row with no column-group) can render empty.

## Loops / repeated CMS content

When a section shows more than one of "the same thing" (posts, projects, products,
team members, testimonials), that is **looped CMS content, not N hand-built
modules.** Beaver has **no loop-wrapper node** — there is no Bricks-style
`hasLoop` flag or Etch-style `etch/loop` node, and rows/columns do not iterate. In
Beaver **the loop *is* a module**: the Posts modules.

- **`post-grid`** is the primary Posts module (grid / columns / gallery / feed
  layouts); **`post-carousel`** and **`post-slider`** are the carousel/slider
  variants. Add one as an ordinary module node (under a column, like any other).
- **Discover the query keys with `get-module slug=post-grid search="post"`** — do
  not guess them (the `search` filter keeps the post/query fields out of the ~96-field
  dump). The query is built by Beaver's loop settings from keys such as `data_source`
  (set it to `custom_query` to query by type), `post_type`, `posts_per_page`,
  `order`, `order_by`, and `offset`. Note: only some of these (e.g.
  `posts_per_page`, default `"10"`) appear as static form fields / in the module's
  flat `defaults`; the rest are injected by Beaver's loop-settings UI at edit time,
  so set them explicitly in the node `settings`. The Posts module renders one card
  per matched post automatically — you do **not** build a per-item child template
  under it.
- Minimal looped section (built with the node abilities):

  ```json
  [
    {"id":"lr","type":"row","position":0,"settings":{}},
    {"id":"lg","type":"column-group","parent":"lr","position":0,"settings":{}},
    {"id":"lc","type":"column","parent":"lg","position":0,"settings":{}},
    {"id":"pg","type":"module","parent":"lc","position":0,"module":"post-grid",
     "settings":{"layout":"grid","data_source":"custom_query","post_type":"post",
     "posts_per_page":6,"order":"DESC","order_by":"date"}}
  ]
  ```

- For a CPT, set `post_type` to the CPT slug — discover slugs via the active
  field-plugin specialization's list ability (`pods-list-pods`,
  `acf-list-post-types`, `jetengine-list-post-types`, …).
- A native Posts module shows the standard post fields (title, excerpt, featured
  image, …) controlled by its `show_*` settings. For a **custom per-item layout or
  custom/ACF fields inside the loop**, you need **Beaver Themer** (next section):
  Themer adds the "Posts" custom layout and field connections that the base Posts
  module cannot express.

## Dynamic data & Beaver Themer

**Base Beaver Builder has no dynamic data.** Connecting a field to a post's title,
a custom field, the site name, an archive value, etc. requires **Beaver Themer**
(`bb-theme-builder`). Gate on it: read `theme_builder_active` from
`beaver-builder-check-setup` before promising any dynamic behavior; when it is
`false`, dynamic fields and theme templates are unavailable and you can only build
static layouts.

Themer provides two distinct things, both reachable with the **existing** abilities
(there is no dedicated Themer ability surface — and you do not need one):

**1. Field connections (dynamic field values).** Themer's data engine
(`FLPageData`) exposes bindable values grouped under `posts`, `term`, `archives`,
`author`, `site`, `user`, `comments`, `general`, and `advanced` (plus `woo` on
WooCommerce sites). A connection is stored **inside the node's `settings`** under a
`connections` map keyed by the connected setting name. A flat connection is
`settings.connections.<field_key> = { object, property, field, settings }` — note
**`property`**, NOT `key`: Themer's reader (`FLThemeBuilderFieldConnections`) only
treats a connection as flat when `property` is set, then resolves it via
`FLPageData::get_value(object, property, settings)`. A connection missing `property`
is read as a *compound* one and silently falls back. `object` is the data object
(`post`, `term`, `site`, …), `property` is the registered property key, `field` is
the connected field's type (e.g. text/photo/color/background), and `settings` holds
per-connection options. The connected setting's plain value is the fallback.

**Do not hand-guess the `object`/`property`/`field` strings** — they come from
Themer's registry (which only populates in the Themer editor/render context) and a
wrong value fails silently to the fallback. Create the connection once in the Themer
UI, read the node back with `get-layout include_settings=true`, and replicate the
exact shape with `set-layout` / `add-node` / `edit-node`. Illustrative shape only
(confirm the real values from a discovered connection):

```json
{"id":"hd","type":"module","parent":"col","position":0,"module":"heading",
 "settings":{"heading":"Fallback title","tag":"h1",
   "connections":{"heading":{"object":"post","property":"title","field":"text","settings":{}}}}}
```

The `property` is Themer's registered property **key**, not the WordPress column
name: the post title is `property:"title"` (registered via
`FLPageData::add_post_property('title', …)`), **not** `"post_title"` — the latter
is not a registered key and resolves to nothing, so the field silently shows the
fallback. `object` is `"post"` (singular). For a plain-text setting like a heading,
`field` is not enumerated by Themer's resolver — it only branches on
`background` / `photo` / `color`, so `"text"` (or any value) just assigns the
resolved string. This shape was confirmed to resolve on a real front-end HTTP
render (heading showed the post title, not the fallback).

The `connections` object round-trips cleanly: it persists through `set-layout`,
comes back from `get-layout include_settings=true`, and survives an `edit-node`
shallow merge (changing `heading` does not drop `connections`). Because the
property registry only populates inside Themer's front-end/render context (it
hooks the `wp` action), confirm the exact `object` / `property` / `field` against
a real connection rather than guessing — and note this is exactly why a bare
wp-cli render can't be trusted to validate a connection: the property registry and
the loop context that Themer sets up (`connect_all_layout_settings` runs
`the_post()` on `wp`) are not present in a bare `wp eval`, so a connection that
resolves fine on the real front end reads as the fallback there. **Validate over a
real HTTP request, not wp-cli.**

Where connections resolve: a `post`-object connection (e.g. `post`/`title`)
resolves on any builder-rendered singular view — including a **plain published
page**, because the page *is* the current post in the loop (verified: a heading
bound to `post`/`title` on an ordinary page renders the page's own title, not the
fallback). What a plain static page cannot supply is context that depends on a
*different* object — `archive` / `term` values, or per-item loop fields that only
exist inside a Posts-loop item or a Themer archive/single theme layout (below).
Those fall back on a standalone page. So gate by which `object` you bind: `post`
and `site` resolve on a normal page; archive/term/loop-scoped objects need the
matching Themer context.

**2. Theme templates (theme layouts).** Themer's header / footer / archive / single
/ part templates are stored as **`fl-theme-layout` posts** with location rules that
decide where each one displays. `fl-theme-layout` is a Beaver-supported post type,
so the existing abilities already handle these end to end:

- `beaver-builder-list-layouts` lists them (filter `post_type=fl-theme-layout` to
  see only theme templates).
- `beaver-builder-get-layout` reads a theme layout's node tree.
- `set-layout` / `add-node` / `edit-node` / `move-node` / `delete-node` edit it
  exactly like a page — including writing field connections (above) into its module
  nodes, which is the whole point of a single/archive template.

What the abilities do **not** manage is the Themer-specific metadata: the layout
*type* (header/footer/single/archive/part) and its *location rules* are set on the
`fl-theme-layout` post by Themer's own UI / post meta, not by these abilities. So:
create or configure the theme layout (type + location) in Themer, then use the node
abilities to build and connect its content. Discover existing ones with
`list-layouts post_type=fl-theme-layout` before assuming none exist.

## Reusable templates

Build a section once and reuse it instead of rebuilding. Templates are saved posts
managed with four abilities:

- **`create-template`** — save a whole page (`type=layout`) or a single node
  (`type=row|column|module` + its `node_id`) as a reusable template. `global=true`
  saves a node template as a GLOBAL one (linked instances).
- **`list-templates`** — the catalog (`post_id`, `template_id`, `name`, `type`,
  `is_global`).
- **`apply-template`** — insert a template into a target page: `scope=layout`
  (replaces the page, or `append=true` to add its rows) or `scope=node` (insert
  under `parent_id` at `position`). Node ids are regenerated on apply.
- **`delete-template`** — trash (default; unlinks a global from every page first)
  or `force` to delete permanently.

Saved `row`/`column`/`module` (node) templates and global templates need Beaver's
node-templates feature (not Lite); `check-setup` reports `node_templates_available`.
Whole-page `layout` templates need only the builder.

**Global rows/modules are created this way** — `create-template global=true`, then
`apply-template` — NOT by setting a `global` flag on a node in set-layout/add-node.
A bare `global=true` on a node does nothing (a real global instance needs the
template linkage Beaver sets here), so the node abilities ignore that flag. Reach
for a global node template when the user wants edit-in-one-place shared content
(a header, a CTA) across pages.

## Limitations & workarounds

A few operations have no dedicated ability — handle them like this, never with an
`html` module or custom code:

- **Duplicate a node, or reuse a section within one page.** No duplicate ability:
  read the node with `get-layout include_settings=true`, then re-create it with
  `add-node` / `set-layout` under fresh ids, copying its `settings`. For something
  you will reuse more than once, prefer a saved template (`create-template` →
  `apply-template`).
- **Copy a section or a whole page to another page (export/import).** `get-layout
  include_settings=true` on the source, then feed the nodes into `set-layout`
  (replace) or `add-node` (append) on the target. Or `create-template` from the
  source and `apply-template` onto the target.
- **Page-level custom CSS / JS, or the layout CSS class.** Not writable by an
  ability, on purpose: style with node settings and Global Colors / Fonts, never
  page CSS. `get-layout include_layout_settings=true` READS these; changing them is
  a deliberate last-resort step in the Beaver editor (Tools → Layout CSS &
  JavaScript), not part of an automated build.
- **Global builder settings** (default content width, responsive breakpoints,
  default page heading) live in Beaver → Tools → Global Settings — out of
  page-building scope; configure them in the UI, not via these abilities.
- **Discovering Themer dynamic fields.** There is no `list-dynamic-fields` ability:
  Themer's property registry only populates inside the editor/render context, so
  create a connection once in the Themer UI, read the node back with `get-layout
  include_settings=true`, and replicate the exact `connections` shape (see *Dynamic
  data & Beaver Themer*).

## Gotchas

- The editor enablement flag is separate from the layout data. A post can have
  stale `_fl_builder_data`; trust `_fl_builder_enabled` when deciding whether it
  is a Beaver Builder page.
- Module settings are plugin- and add-on-dependent. Always discover the module
  catalog from the live site, and a specific module's keys with `get-module`.
- Beaver Themer is optional. When it is inactive, normal page layouts still
  work, but theme headers/footers/archive layouts are unavailable.
- Layout settings can include custom CSS/JS. Fetch them only when needed.
- Node ids are Beaver Builder ids, not positional addresses. Parent/children
  fields define the tree.

## Ability Conventions

- Ability slugs use `novamira/beaver-builder-*`.
- `list-*` abilities return compact rows and counts.
- `get-module` returns one module's settings schema (typed `fields`; the flat
  `defaults` map is opt-in via `include_defaults=true`), narrowable by
  `search` / `tab` / `section` and bounded by `max_fields` / `max_options` /
  `max_field_bytes`.
- `get-layout` returns a flat node list by default and hides settings unless
  `include_settings=true`.
- `set-layout` and node write abilities default to `status=both`.
- Large strings in settings are byte-capped with a truncation marker.
