---
name: etch-build-page
description: Build and edit pages with the Etch builder (the HTML-first WordPress builder by Digital Gravy). Activate when the user asks to build, edit, or inspect an Etch page or template, work with Etch components, styles, loops, or queries, or diagnose why Etch is unavailable. Etch stores page content as Gutenberg block markup (etch/* blocks) in post_content and its global libraries in wp_options.
---

# Building pages with Etch

Etch is an HTML-first WordPress builder. Unlike JSON-tree builders, Etch authors **real markup**: a page is a tree of Gutenberg blocks under the `etch/` namespace, serialized as block-comment markup inside the post's `post_content`. Global state (components, styles, loops, queries) lives in dedicated `wp_options` rows.

## When to use

Use this skill when the task involves an Etch-built page or template, Etch components/styles/loops/queries, or diagnosing Etch availability. Do **not** use it for classic Gutenberg blocks unrelated to Etch, or for editing pages built with a different builder — the storage model and block vocabulary are Etch-specific.

Always start by calling `novamira/etch-check-setup`. It reports whether Etch is active, the version, PHP compatibility (Etch requires PHP 8.1+), license status, the Etch-managed custom post types, and how much global state already exists. If `active` is false or `issues` is non-empty, surface that to the user before attempting any build work.

## Domain model

**Page / template content.** Stored as block markup in `post_content`. The Etch block vocabulary (all in the `etch/` namespace):

| Block | Role |
|---|---|
| `etch/element` | A generic HTML element (the workhorse — tag, attributes, children) |
| `etch/text` | A text node |
| `etch/component` | An instance of a reusable component |
| `etch/loop` | Repeats its children over a query / collection |
| `etch/condition` | Conditionally renders its children |
| `etch/dynamic-element` | An element whose tag/attributes resolve from dynamic data |
| `etch/dynamic-image` | A dynamically-resolved image |
| `etch/raw-html` | Verbatim HTML passthrough |
| `etch/slot-placeholder` | A component slot insertion point |
| `etch/slot-content` | Content provided into a named component slot |
| `etch/svg` | An inline SVG |

**Global libraries (wp_options rows).** Each is a keyed array:

| Option | Holds |
|---|---|
| `etch_components` | Reusable components |
| `etch_styles` | Per-class / per-element style records (the editable style layer — CRUD via `*-style`) |
| `etch_global_stylesheets` | Global stylesheets (foundations/tokens — CRUD via `*-stylesheet`) |
| `etch_loops` | Loop definitions |
| `etch_queries` | Query definitions (data sources for loops) |
| `etch_taxonomies` | Etch-managed custom taxonomies |
| `etch_cpts` | Etch-managed custom post types |
| `etch_settings` | Plugin settings (some values encrypted) |
| `etch_ui_state` | Editor UI state — not content; avoid writing |

**Components** are also backed by `wp_block` / pattern posts and carry the post meta `etch_component_html_key`, `etch_component_legacy_id`, and `etch_component_properties`.

## Workflows

The page element tree is a list of nodes, each `{ block, attrs?, children?, html? }`:
- `block` — the Gutenberg block name (`etch/element`, `etch/text`, `etch/component`, …). A `"namespace/name"` slug; an empty string `""` marks a freeform HTML node (its markup is in `html`).
- `attrs` — the block attributes, a keyed object (e.g. `tag`, `attributes`, `styles` for `etch/element`; `content` for `etch/text`; `ref` for `etch/component`). Not a list.
- `children` — nested nodes, present only for container blocks.
- `html` — inner HTML for leaf blocks that store content outside `attrs` (core blocks, raw HTML) and for freeform nodes. Round-trips losslessly.

Beyond a block's own keys, **every node may carry these base attrs** (all optional, all preserved on round-trip): `hidden` (bool — excluded from render), `script` (`{ id, code }`), and `options` (an editor bag). An `etch/condition` node additionally carries `conditionString` (the string form of its `condition` object). `novamira/etch-list-elements` returns the full per-block attribute list plus these shared `base_attributes`.

### Diagnose the environment
1. `novamira/etch-check-setup` → read `active`, `version`, `php_compatible`, `issues`.
2. If `active` is false, tell the user Etch must be installed and activated (https://etchwp.com) and stop.
3. **Minimum supported Etch version is `1.4.19`** — these abilities are verified against it. If `version` is older (compare with the standard `version_compare` semantics), warn the user to update Etch, as Etch's option shapes and internal routes may differ on older builds.
4. If `license_status` is empty, note the visual editor may be limited — programmatic content still renders on the front end.

### Learn the block vocabulary
Call `novamira/etch-list-elements` to see every block type, whether it is a container, and its attributes. Do this before building with unfamiliar blocks — guessing attribute keys risks silent drops.

### Read a page's structure
`novamira/etch-get-content { post_id }` → `{ tree, node_count, truncated, lossy, warnings }`. If `truncated` is true, raise `max_nodes`. The tree mirrors the visual nesting. **If `lossy` is true**, the page mixes in content the compact tree cannot fully represent (raw HTML *between* child blocks, or core-block wrappers) — read `warnings`, and do **not** write the whole tree back with `set-content` (that would drop the unrepresented content). These abilities are lossless for pages built entirely with Etch (`etch/*`) blocks; `lossy` is the honest signal for mixed/non-Etch pages.

### Build a new page (replace all content)
1. `novamira/etch-list-elements` if unsure of the vocabulary.
2. Compose the tree and call `novamira/etch-set-content { post_id, tree }`. Example — a hero section:
   ```json
   [{ "block": "etch/element", "attrs": { "tag": "section", "attributes": { "class": "hero" } },
      "children": [
        { "block": "etch/element", "attrs": { "tag": "h1" },
          "children": [ { "block": "etch/text", "attrs": { "content": "Welcome" } } ] }
      ] }]
   ```
3. `set-content` overwrites the whole page — read first if you mean to edit.
4. **Style by class, and make that CSS editable in Etch.** Etch is class-driven: bind elements with `attributes.class` (as the `"class": "hero"` above), then author the class's CSS. For the section/component/button/card/text styling a user expects to edit in Etch, create a **per-class style record** — `novamira/etch-create-style { selector: ".hero", css }` — these are the records Etch surfaces when you select a class on an element, so the page stays editable after you hand off. Keep only true foundations (design tokens, resets, broad shared rules) in a **global stylesheet** (`etch-create-stylesheet`). Reserve inline `attributes.style` for genuine one-off overrides — it never shows in the Etch style editor. See *Styling: per-class records vs global stylesheets* below. A styled page is typically `etch-set-content` plus one `etch-create-style` per component class. Once a class's record exists, the write abilities **auto-attach the record's id to the element's `attrs.styles`** when you set `attributes.class`, so you just put the class on the element (the class still needs its record to render; see *Styling* below).

### Edit an existing page
1. `novamira/etch-get-content { post_id }` → get the tree. Check `lossy` first — if true, the get → set loop is not safe for this page (see above).
2. Mutate the returned tree (add/remove/change nodes).
3. `novamira/etch-set-content { post_id, tree }` to write it back. This is the get → mutate → set loop; `set-content` is idempotent for a given tree.

`set-content` enforces the lossy contract: if the page's **current** content is lossy, the write is refused with `etch_lossy_overwrite` rather than silently dropping the unrepresented HTML. Re-read with `get-content`, and only if you truly mean to replace the whole page pass `force: true`.

### Targeted edits (one node, by address)
For a small change, prefer the node-scoped abilities over re-sending the whole tree — you don't even have to hold it. Each takes an `address`: the index path into the tree `get-content` returns (`"0/1/2"` = first top-level node → its child 1 → its child 2; `""` = the page root).
- `novamira/etch-add-node { post_id, parent_address, node, position? }` — insert a compact node `{ block, attrs?, children?, html? }` into a container (parent_address `""` = root; position omitted = append).
- `novamira/etch-edit-node { post_id, address, attrs?, html?, block? }` — in-place: `attrs` merges over the node's existing attrs — top-level keys shallow-merge (a key set to `null` is dropped), while the HTML `attributes` sub-object **deep-merges**, so a partial attribute edit keeps untouched attributes like `class` (set a nested attribute to `null` to remove just that one); `html` replaces a leaf's html, `block` changes the type. Children untouched.
- `novamira/etch-delete-node { post_id, address }` — remove a node and its subtree.
- `novamira/etch-move-node { post_id, address, new_parent_address, position? }` — relocate/reorder; the destination index is auto-adjusted for the source's removal; cannot move into its own subtree.

They re-serialize the page through the same path as `set-content`, so the **same lossy guard applies** (a lossy page is refused unless `force: true`). Any structural write (add/delete/move) **renumbers index paths** — re-read with `get-content` before the next address-based call.

### Clear a page
`novamira/etch-set-content { post_id, tree: [] }`. (On a lossy page this also requires `force: true`, since clearing destroys the unrepresented content.)

### Literal HTML (`etch/raw-html` vs a freeform node)
Three distinct ways literal HTML reaches the page — don't conflate them:
- **`etch/raw-html`** — a real Etch block: `{ "block":"etch/raw-html", "attrs":{ "content":"<b>hi</b>", "unsafe": false } }`. `content` is the HTML; `unsafe:true` opts **out** of sanitization (⚠️ stored-XSS risk — only when the markup is trusted/author-controlled; default `false`).
- **A freeform node** — `block:""` with the markup in the node's `html` field. This is what `get-content` emits for raw HTML the parser captured verbatim from a non-Etch page; it is the reason a page can be `lossy`. Prefer real `etch/*` blocks when authoring.
- **The `html` field on a leaf** — inner content for core-block wrappers. Constraint: an `html` field must **not** contain `<!-- wp: … -->` block delimiters (Etch re-parses it).

### Work with components
Components are reusable, synced blocks (stored as `wp_block` posts). A component has `{ id, name, key, description, properties, tree }`; its `tree` is the same compact node format as a page.
- `novamira/etch-list-components` → compact `{ id, name, key, description }` per component.
- `novamira/etch-get-component { component_id }` → the full component (tree + properties + `lossy`).
- `novamira/etch-create-component { name, tree?, description?, properties?, key? }` → returns the new `id` (the `key` auto-generates). Build the `tree` exactly as for `set-content`.
- `novamira/etch-edit-component { component_id, name?, tree?, description?, properties?, key? }` → partial update.
- `novamira/etch-delete-component { component_id }` → permanent.
- `novamira/etch-duplicate-component { component_id, name? }` → faithful copy (tree + properties) under a new unique key; a good starting point to then `edit-component`.

Place a component on a page with an `etch/component` node whose `attrs.ref` is the component id (and `attrs.attributes` carries its prop values).

**Props, variants, dynamic usage.** A component's `properties` is a keyed map `{ propKey => { type } }` defining its inputs, where `type` is `string`, `number`, `boolean`, `array`, `object`, `group`, `repeater-group`, `class`, `condition`, or `loop` (e.g. `{ "heading": { "type": "string" } }`). The **map key is the prop's identifier**: the component tree reads it as `{props.<propKey>}` (so `{props.heading}`), and a component **instance** supplies the value under that same key in `attrs.attributes`. `create-component` / `edit-component` normalize this shape for you — internally Etch wants each property to carry its own `key` and a `type` object, which the abilities fill in from the map key, so the simple `{ propKey => { type } }` form binds correctly. A component instance is an `etch/component` node — `{ block:"etch/component", attrs:{ ref: <id>, attributes:{ heading:"Hello", image:"{featured_image.url}" } } }` — whose attribute values are literals *or* `{...}` dynamic expressions; inside an `etch/loop` they resolve per item. A **variant** is a prop of type `condition` that toggles parts of the component. Discover bindable `{...}` paths with `etch-list-dynamic-fields`.

**Slots (composing components with caller-supplied content).** A component's `tree` declares insertion points with `etch/slot-placeholder` nodes (`attrs.name`, e.g. `{ "block":"etch/slot-placeholder", "attrs":{ "name":"main" } }`). A component **instance** fills them by including `etch/slot-content` children whose `attrs.name` matches the placeholder, with the content as that node's `children`:
```json
{ "block": "etch/component", "attrs": { "ref": <id> },
  "children": [ { "block": "etch/slot-content", "attrs": { "name": "main" },
    "children": [ { "block": "etch/text", "attrs": { "content": "injected" } } ] } ] }
```
A placeholder with no matching `slot-content` renders its own default children (if any).

### Styling: per-class records vs global stylesheets
Etch has **two** CSS layers. Match the layer to intent — getting this right is what keeps a generated page editable in Etch rather than "styled but not owned by the builder".

**1. Per-class style records** (`etch_styles`) — the records Etch surfaces when you select a class/element in the editor. **Use these for the component / section / button / card / text styling a user should be able to edit in Etch.** This is the editable, builder-owned layer.
- `novamira/etch-get-styles` → compact `{ id, collection, selector, type, readonly, css_bytes }` per record (and the `id` you pass to edit/delete).
- `novamira/etch-create-style { selector, css, collection? }` → create a record and return its `id`. `selector` is the rule selector (e.g. `".hero"`); **`css` is the declaration block ONLY — no selector, no braces** (e.g. `"color: var(--brand); padding: 24px;"`). `type` is inferred from the selector (class/id/element/attribute); `collection` defaults to `"default"`. An element renders the rule only when it carries both `attributes.class: "hero"` and the record id in `attrs.styles` — but you no longer attach that id by hand: once the record exists, the write abilities (`set-content`, `add-node`, `edit-node`) **auto-attach its id to any element whose class matches the record's single-class selector**, so just set the class. (Passing `attrs.styles` explicitly still works and is preserved; Etch only outputs a class record's CSS for records referenced by a rendered element.) Fails if a record with that selector already exists in the collection (edit it instead).
- `novamira/etch-edit-style { style_id, css?, selector? }` → partial in-place update (`css` is declarations only here too).
- `novamira/etch-delete-style { style_id }` → remove a record.
- Etch's readonly built-ins (`readonly:true` — the element bases like `section`/`container` and `:root`) cannot be edited or deleted.
- These records stay editable in Etch and are preserved when the page is later edited and saved there, so they remain editable after you hand off.

**2. Global stylesheets** (`etch_global_stylesheets`) — named CSS sheets like "Main". **Use these for foundations only: design tokens (`:root` variables), resets, broad shared primitives** — *not* per-component styling (that belongs in per-class records above, or it won't be class-editable in Etch). Built-ins: `default` (Main) and `custom-media`. Here `css` is full rules (with selectors).
- `novamira/etch-list-stylesheets` → `{ id, name, type, css_bytes }`.
- `novamira/etch-get-stylesheet { stylesheet_id }` → full sheet incl. `css`.
- `novamira/etch-create-stylesheet { name, css, type? }` → new id (`type` defaults `default`; `@custom-media` for media definitions).
- `novamira/etch-edit-stylesheet { stylesheet_id, name?, css?, type? }` → partial update.
- `novamira/etch-delete-stylesheet { stylesheet_id }` → permanent.

**A per-class record binds through two attrs** — `attributes.class: "hero"` (matches the record's `.hero` selector) and the record id in `attrs.styles` (what makes Etch emit the rule on the front end) — and the abilities keep them in sync: once the record exists, setting the class **auto-attaches its id to `attrs.styles`** on write (single-class selectors only; compound or pseudo selectors stay manual). So in practice you set the class and the binding follows; explicitly setting `attrs.styles` still works and is preserved. Create the record before or in the same flow as the element so its id is available to attach. Foundations from a **global stylesheet** need no `styles` id — they're emitted globally. `attributes.style` is inline CSS (last-resort one-off, never shows in the Etch style editor); never scatter it.

### Work with loops (dynamic data)

#### Loop config types

A loop's `config.type` determines how the data is resolved. The supported types are:

| Type | Data source | Notes |
|---|---|---|
| `wp-query` | WordPress posts via `WP_Query` | Default; pass `post_type`, `posts_per_page`, etc. in `config.args` |
| `wp-terms` | WordPress taxonomy terms via `WP_Term_Query` | Requires `taxonomy` in `config.args` |
| `wp-users` | WordPress users via `WP_User_Query` | Pass user query args in `config.args` |
| `main-query` | Current page's main query | Inherits the primary post/archive context (the global `WP_Query` vars); `config.args` is merged on top as an override |
| `json` | Static JSON array | The array lives in `config.data` (**not** `config.args`). For a one-off inline array you don't have to save a preset at all — put the literal array (or a `{...}` expression that resolves to one) in the `etch/loop` node's `target` attribute instead |

When using the source discovery abilities (`novamira/etch-list-dynamic-fields`, `novamira/etch-get-query-preview`), the `source` parameter (`post`, `term`, `user`) maps to loop types: `post` → `wp-query`, `term` → `wp-terms`, `user` → `wp-users`.
Loops are the data sources an `etch/loop` node repeats over (`etch_loops` option) — `{ id, name, key, global, config }`, where `config` is `{ type (e.g. "wp-query"), args }` and `global:true` marks an Etch built-in preset.
- `novamira/etch-list-loops` → compact `{ id, name, key, global, config_type }`.
- `novamira/etch-get-loop { loop_id }` → the full loop incl. `config`.
- `novamira/etch-create-loop { name, config, key? }` → returns the new id. Model `config` on an existing loop (e.g. `{ "type":"wp-query", "args":{ "post_type":"post", "posts_per_page":10 } }`).
- `novamira/etch-edit-loop { loop_id, name?, key?, config? }` → partial update (built-in preset fields are preserved).
- `novamira/etch-delete-loop { loop_id }` → permanent.

Repeat content on a page with an `etch/loop` node. Its attrs select the data source AND name the per-iteration variable — knowing only `loopId` is **not** enough to read item data:
- `target` — an expression that evaluates to the array to iterate (e.g. `"this.relatedItems"`, `"site.url.split(\"\")"`, a literal `"[1, 2, 3]"`, or `"item.children"` for a nested loop). This is the general source.
- `loopId` — id of a saved loop preset (from `create-loop`); overrides detection from `target`. Use `loopId` **or** `target`.
- `itemId` — names the per-iteration variable. **Defaults to `item`**; children then read fields as `{item.<field>}`. Set it (e.g. `"post"`) to read `{post.<field>}`, and give nested loops distinct names (`outerItem`, `nestedItem`) so inner children can reference the outer item unambiguously.
- `indexId` — names the loop index variable (e.g. `"i"` → `{i}`).
- `loopParams` — an object of dynamic `{…}` values passed into the query/preset at render.

```json
{ "block": "etch/loop",
  "attrs": { "loopId": "<id from create-loop>", "itemId": "post", "indexId": "i" },
  "children": [
    { "block": "etch/element", "attrs": { "tag": "h3" },
      "children": [ { "block": "etch/text", "attrs": { "content": "{post.title}" } } ] }
  ] }
```

### Bind dynamic data (`{...}` expressions)
Etch is dynamic via **expressions written inline in the markup**, not a separate data entity. Any attribute value or text content can be a `{path}` expression that Etch resolves at render — this works in **`etch/text` content and in `etch/element` attribute values** too (a bare `etch/element`/`etch/text` is not "static"). Reach for the dynamic blocks only when the *structure* is dynamic: `etch/dynamic-element` when the **tag itself** comes from data, and `etch/dynamic-image` for an `<img>` whose `src`/`alt`/`srcset` are dynamic.

**Expression contexts** — a `{path}` resolves against one of these roots:

| Root | Means | Example |
|---|---|---|
| *(bare)* | the current object (implicit `this`) | `{title}`, `{permalink}` |
| `this.*` | the current object explicitly, incl. its meta/fields | `{this.title}`, `{this.meta.<key>}` (raw post meta), `{this.acf.<field>}` (ACF when present), `{this.baseUrl}` |
| `item.*` | the current **loop item** — renamed by the loop's `itemId` (so `{post.*}` when `itemId:"post"`) | `{item.title}`, `{post.featured_image.url}` |
| `props.*` | a **component instance's** declared props — how a component tree reads its own inputs | `{props.title}`, `{props.className}` |
| `site.*` | site globals | `{site.name}`, `{site.url}` |
| `user.*` | current user | `{user.displayName}` |

(Etch custom fields resolve as `{etch.<key>}` — set their per-post values with `novamira/etch-set-field-values` on Etch 1.5.0+; see *Work with custom fields* below.)

Don't guess the paths — **discover them**:
- `novamira/etch-list-dynamic-fields { source?, post_type?, taxonomy?, query_args? }` → samples real items and returns a compact catalogue of bindable dot-paths with type + sample value. `source` is `post` (default; choose `post_type`), `term` (give a `taxonomy`), or `user`. `sampled:0` means no content of that type exists yet — create one first.
- `novamira/etch-get-query-preview { source?, post_type?, taxonomy?, query_args?, limit? }` → runs the query and returns up to `limit` (≤10) real items as flattened `{ path: value }` maps, so you can see actual values before binding.

Workflow for a dynamic card grid: (1) `etch-create-loop { config:{ type:"wp-query", args:{ post_type:"post" } } }`; (2) `etch-list-dynamic-fields { post_type:"post" }` to learn the paths; (3) `etch-set-content` with an `etch/loop` (its `attrs.loopId`) whose children are `etch/dynamic-element`/`etch/text` nodes binding `{title}`, `{meta.subtitle}`, etc.

Saved **queries** (the `etch_queries` option) are reusable data-source presets, read-only here:
- `novamira/etch-list-queries` → compact `{ key, name, type }` (empty by default on most sites).
- `novamira/etch-get-query { key }` → the full stored definition. For *discovering* data prefer `list-dynamic-fields`/`get-query-preview` above; the loop `config` already carries the query args needed at render.

### Conditional logic (show/hide)
Conditional rendering is authored inline as an `etch/condition` node (no separate entity) — write it with `set-content`. Its `attrs.condition` is an object `{ operator, leftHand?, rightHand? }`; the node's `children` render only when the condition is truthy. Operators: `==`, `===`, `!=`, `!==`, `>`, `<`, `>=`, `<=`, `&&`, `||`, plus the unary `isTruthy` / `isFalsy` (which use only `leftHand`).

**Operand rules — get these exactly right, or the condition silently mis-evaluates** (Etch runs each operand through the dynamic-expression engine, NOT as a raw literal):
- A **dynamic path** is `{...}`: `{this.title}`, `{post.status}`, `{this.meta.count}`. Resolved against the in-scope sources.
- A **string literal must be QUOTED inside the value**: `"'publish'"` (or `"\"publish\""`) → the string `publish`. A **bare unquoted word resolves to `null`** (it is read as a path), so `"rightHand": "publish"` compares against `null`, not the text — a classic silent bug.
- **Numbers and booleans are bare**: `5`, `"5"`, `true` resolve to themselves.
- A **nested condition object** (with its own `operator`) nests for compound logic.

So `isTruthy` only fires on a resolvable operand — a `{...}` path that resolves truthy, or a quoted/number literal; a bare word is always falsy (null). Examples:
```json
{ "block": "etch/condition",
  "attrs": { "condition": { "operator": "isTruthy", "leftHand": "{meta.subtitle}" } },
  "children": [ { "block": "etch/element", "attrs": { "tag": "p" },
    "children": [ { "block": "etch/text", "attrs": { "content": "{meta.subtitle}" } } ] } ] }
```
Compound (AND of two comparisons):
```json
{ "operator": "&&",
  "leftHand":  { "operator": "===", "leftHand": "{post.status}", "rightHand": "'publish'" },
  "rightHand": { "operator": ">",   "leftHand": "{meta.count}",  "rightHand": 5 } }
```
A component can also carry a prop of type `condition` (a "variant") to toggle parts of itself — see the components section.

### Work with custom fields (Etch's native ACF-like fields)
Etch ships its own custom-field system (the `etch_cfs` option): **field groups** assigned to post types and/or post ids, each holding **fields** `{ key, label, type }` where `type` is `text`, `textarea`, `number`, `boolean`. A field's `key` is the post meta key; its value resolves as `{etch.<key>}` in dynamic content.
- `novamira/etch-list-field-groups` → compact `{ id, label, post_types, field_count }`.
- `novamira/etch-get-field-group { group_id }` → full group incl. its `fields`.
- `novamira/etch-create-field-group { label, assigned_to, fields? }` → returns the new id. `assigned_to` is `{ post_types: [slugs] }` and/or `{ post_ids: [ids] }`.
- `novamira/etch-edit-field-group { group_id, label?, assigned_to? }` → partial (fields are managed separately).
- `novamira/etch-delete-field-group { group_id }` → permanent (existing meta values are left in place).
- `novamira/etch-add-field { group_id, key, label, type, required?, default? }` → append a field (rejects a duplicate key).
- `novamira/etch-edit-field { group_id, field_key, label?, type?, key?, … }` → partial merge; passing `key` renames the meta key.
- `novamira/etch-delete-field { group_id, field_key }` → remove a field.

These abilities manage the field **definitions**. The per-post **values** are written with the value abilities below (Etch 1.5.0+):
- `novamira/etch-set-field-values { post_id, values: { "<key>": value, … } }` → set one or many values in one call. Etch resolves each key against the groups **assigned to that post**, validates by type, and writes both the value and the `{etch.<key>}` marker meta — so the binding resolves immediately, no editor save needed. A key not in a group assigned to the post is rejected.
- `novamira/etch-get-field-values { post_id, keys? }` → the post's current values, each `{ key, label, type, group_id, value }`.
- `novamira/etch-delete-field-value { post_id, key }` → remove a stored value (the definition stays).

Availability: the value abilities register only on **Etch 1.5.0+** — check `custom_field_values_supported` from `novamira/etch-check-setup`. On older Etch, write values via `update_post_meta`, but then `{etch.<key>}` only resolves after an editor save (which writes the marker meta); `{meta.<key>}` always resolves a raw meta. Use `novamira/etch-list-dynamic-fields` to confirm which paths are live for a post type.

### Work with custom post types & taxonomies
Etch registers CPTs from the `etch_cpts` option (`{ slug => register_post_type args }`) and taxonomies from `etch_taxonomies` (`{ slug => { args, object_types } }`). Slugs are caller-chosen (1–20 lowercase letters/digits/`_`/`-`).
- `novamira/etch-list-post-types` + `novamira/etch-{get,create,edit,delete}-post-type` — create needs `slug` + `label` (public/show_in_rest default true; extra `args` are register_post_type options); edit merges `args`; delete unregisters but leaves existing posts. (Note the **list** slug is plural: `list-post-types`.)
- `novamira/etch-list-taxonomies` + `novamira/etch-{get,create,edit,delete}-taxonomy` — create needs `slug` + `label` + `object_types` (the post type slugs it attaches to); edit can replace `object_types`; delete unregisters but leaves existing terms. **Taxonomy `args` use Etch's camelCase allowlist** (`showInRest`, `showInAdminColumn`, `showInMenu`, `hierarchical`, …) and labels via `labels.pluralName`/`labels.singularName` — snake_case keys and a top-level `label` are dropped by Etch's mapper (the `label` input is written into `labels.pluralName` for you). CPT `args` are plain `register_post_type` options (snake_case).
- Slugs that already exist as a WordPress post type/taxonomy (core built-ins like `post`, `page`, `category`, or another plugin's) are **rejected** (`etch_exists`) so a create never clobbers an existing type.
- `object_types` is stored verbatim and **not** validated against registered post types — attaching a taxonomy to a type that does not exist (yet) is accepted silently. Pass slugs that exist (or will, e.g. an Etch CPT you also create) so the taxonomy actually attaches.

`novamira/etch-check-setup` reports the Etch CPT slugs in `custom_post_types`.

### Work with templates (which layout renders where)
Templates are WordPress block templates (the `wp_template` FSE system). A template fills a slot in the template hierarchy identified by its **slug** — `index`, `single`, `archive`, `page`, `front-page`, `404`, `search`, `category`, `tag`, `author`, or `page-<slug>` / `single-<posttype>` for a specific target. Its **content is the same Etch tree as a page**, so you read/write the body with `etch-get-content` / `etch-set-content` using the template id; these abilities manage the template **entity**:
- `novamira/etch-list-templates` → compact `{ id, title, slug, theme, status }` (database templates only).
- `novamira/etch-get-template { template_id }` → metadata incl. `node_count`.
- `novamira/etch-create-template { title, slug, theme?, tree? }` → returns the new id (`theme` defaults to the active theme; `tree` optionally seeds the body).
- `novamira/etch-edit-template { template_id, title?, slug?, theme? }` → metadata partial (body via `set-content`).
- `novamira/etch-delete-template { template_id }` → removes it; the slot falls back to the theme file template.
- `novamira/etch-duplicate-template { template_id, name?, slug? }` → faithful copy (content + theme) under a new unique slug; not auto-routed — set its slot via `edit-template`.

> Scope: these manage `wp_template` only. Reusable template **parts** (`wp_template_part`: header/footer/sidebar) are not exposed — compose headers/footers as components or within the template body.

Build a dynamic archive: `etch-create-template { slug:"archive" }`, then `etch-set-content` a tree with an `etch/loop` (a wp-query loop) whose children bind `{title}` / `{permalink}` / `{featured_image.url}` per post.

## Gotchas

- **PHP 8.1 floor.** Etch requires PHP 8.1+. On older runtimes the plugin will not load even if the files are present; `check-setup` reports this via `php_compatible`.
- **Content is block markup, not JSON.** When read/write abilities arrive, expect `post_content` containing `<!-- wp:etch/... -->` comments — treat it as serialized blocks, not a JSON tree.
- **Global libraries are options, not posts.** Components/styles/loops/queries live in `wp_options`. A read-modify-write on one of these rows must be careful not to clobber concurrent edits.
- **`etch_ui_state` is editor state, not content.** Do not treat it as part of a page.
- **No version constant.** Etch exposes no `ETCH_VERSION`; the version is read from the plugin header. Use `check-setup` rather than assuming a constant.

## Conventions

- Ability slugs are `novamira/etch-<verb>-<object>`; the verbs come from the project's fixed vocabulary (`list`, `get`/`read`, `create`, `add`, `edit`, `delete`, `apply`, `enable`/`disable`).
- `check-setup` is read-only and safe to call any time; it never mutates state.
- List-style abilities return compact entries; fetch full detail with the matching `get`/`read` ability.
