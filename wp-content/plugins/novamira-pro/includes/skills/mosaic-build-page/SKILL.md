---
name: mosaic-build-page
description: Build or modify a page on a Mosaic Pro site. Manage Mosaic themes, templates, the element tree (the building canvas), the design system (collections, variables, modes, breakpoints), components, Classes (with sub classes) and Variants. Activate when the user asks to create or edit a page on a Mosaic site, switch themes, add elements to a template, set up design tokens, style elements with Mosaic Classes or Variants, or work with Mosaic's component library.
---

# Building a page on Mosaic Pro

Mosaic is a visual page builder by Nextend that stores its entire data model in roughly 21 custom `<prefix>mosaic_*` tables (NOT WordPress CPTs). Themes, templates, element trees, components, collections, variables, Classes, Variants, breakpoints, settings, and locks all live in those tables rather than `wp_posts` / `wp_postmeta`.

Architecture in one diagram:

```
Theme ─┬─ Templates ── nodes (element tree)
       ├─ Collections ── variables (per-skin/mode values)
       ├─ Components ── nodes (element tree)
       ├─ Classes ── sub classes (nested)            ← reusable styles, attached to elements
       ├─ Variants (built into Mosaic, per element type) ── variant sub classes (nested)
       ├─ Breakpoints
       ├─ Modes / Skins / Styleguides
       ├─ Masters (reusable templates)
       ├─ Settings (per-theme config)
       ├─ Locks (read-modify-write guards)
       └─ template_assigns (page → template binding)
```

Every entity except locks/breakpoints carries the same column shape: `ID` (varchar UUID), `themeID`, `ordering` (varchar fractional index), `status` (`publish` / `delete`), `revision`, `version`, plus per-entity columns and an optional `data` longtext JSON.

## How Mosaic styles an element

An element's look comes from four layers. Use them in this order of preference:

1. **Variant** — Mosaic's built-in style base for the element type. A `button` can be a Button, a Link or a Badge; a `div` a Div, Grid, Rows, Columns or Container. A theme can add **variant sub classes** (e.g. "Primary" under Button → class `m-button--primary`). Set with the element's `variant` input.
2. **Classes** — reusable styles from Mosaic's Class system, e.g. `card`. A Class can have **sub classes** (nested, e.g. `card--large`, `card--large--xl`) that refine it. Attach with the element's `classes` input; an element holds at most one entry per Class (the Class itself or one of its sub classes).
3. **Local Styles** — the element's own per-state / per-breakpoint styles in `data.style.states` (what Mosaic's editor writes when you style an element directly).
4. **Inline `style`** — the top-level `style` shortcut writes an HTML `style="…"` attribute. Last resort for genuine one-offs, and even then prefer `var(--token)` over a colour/font literal.

**Foreign CSS classes** (`css_classes`) are class names that come from OUTSIDE Mosaic — a theme or plugin stylesheet. Never put a Mosaic Class or Variant name there: Mosaic only emits a Class's CSS when it is attached through `classes`, so a Mosaic class name in `css_classes` renders with no styles. The tools refuse it with `mo_mosaic_class_in_css_classes`.

## First call: check-setup

Always start with `novamira/mosaic-check-setup`. It returns:

- `plugin.active` (constants defined + class loaded)
- `plugin.version` / `min_supported` / `max_supported` (min is inclusive, max is exclusive)
- `plugin.min_satisfied` / `max_satisfied` — version is at or above the supported minimum AND strictly below the supported maximum (independent of tables; Mosaic at or above max_supported is rejected)
- `plugin.tables_present` — the core read tables exist
- `plugin.runtime_ready` — the full AND-gate (active + min_satisfied + max_satisfied + tables_present). Every other ability requires this true; if false, surface the gap to the user before calling anything else.
- `plugin.class_storage` — `current` when Classes, sub classes, Variants and variant sub classes are available. `legacy` means this Mosaic install still stores classes in its legacy format: every Class / Variant ability and the element `css_classes` / `classes` / `detach_classes` / `variant` inputs return `mo_legacy_class_storage` until the user updates Mosaic, while themes, templates, elements, components, collections and variables keep working. Tell the user before any styling work.
- `plugin.edition` (free / pro) / `plugin.channel` (stable / beta / dev)
- `entities.{themes, templates, components, collections}` — quick smoke counts

## Conventions

- **Slugs**: `novamira/mosaic-<verb>-<object>` (e.g. `novamira/mosaic-list-themes`).
- **Error codes**: prefixed `mo_`. The full vocabulary: `mo_runtime_unavailable` (Mosaic loaded but version out of range, data upgrade not finished, or required class missing), `mo_legacy_class_storage` (this Mosaic install still uses the legacy class storage — the user must update Mosaic; only Class / Variant work is affected), `mo_not_found` (entity gone or soft-deleted, or a Class / Variant reference that does not resolve), `mo_invalid_input` (schema-level rejection — depth, length, type, malformed UUID, a class name Mosaic cannot use, or trying to add at the root of a non-template document), `mo_mosaic_class_in_css_classes` (a Mosaic Class or Variant name was sent to `css_classes`; use `classes` / `variant`), `mo_unsupported_variant` (the element type does not accept that Variant; the message lists the allowed ones), `mo_invalid_ordering` (placement / fractional-index resolution failed), `mo_lock_conflict` (concurrent write on the same scope), `mo_concurrent_modification` (the row was modified, soft-deleted, or hard-deleted between this call's load and update — most often a Mosaic editor save committing on a different MySQL connection that bypasses our per-connection GET_LOCK; re-fetch and retry), `mo_no_active_master` (theme has no published master MResource), `mo_orphan_chain_too_deep` (defense-in-depth: cleanup cap of 256 cascade passes hit), `mo_payload_too_large` (encoded blob exceeds 1 MiB), `mo_facade_failed` (Mosaic deep-stack error, message includes the upstream throwable), `mo_write_failed` (`wpdb` returned `false` on the insert/update, or Mosaic did not apply a delete), `mo_override_corrupt` (more than one component-instance override row matches the same `(instance_id, original_node_id)` pair — only reachable via direct SQL, resolve manually). When Mosaic itself is deactivated, the abilities are not registered at all — callers see the framework's `ability_not_found` rather than any `mo_*` code.
- **Permission gate**: every ability uses `novamira_permission_callback` (manage_options) — the trust boundary across all Novamira specializations.
- **Status semantics**: Mosaic uses `publish` / `delete` (NOT `active` / `deleted`). Every list/get filters `status=publish` by default. Classes, sub classes, variant sub classes and variables are deleted through Mosaic's own delete path, which removes the record.
- **Theme scoping**: every list except `mosaic-list-themes` is theme-scoped and requires `theme_id`. Get one via `mosaic-list-themes` first.
- **Casing**: Mosaic stores `themeID` / `parentID` / `parentType` in the DB but every ability surfaces `theme_id` / `parent_id` / `parent_type` (snake_case) in the response.
- **List vs get**: list responses carry only the compact identifying fields (id, name, status, ordering, plus a couple of key scalars per entity). The full `data` blob lives in `get-*` only. `list-templates` / `get-template` / `create-template` always surface `master_id` (empty string when the template is not derived from a master).
- **Names Mosaic owns**: Class / sub-class / variant-sub-class names are sanitized and deduped by Mosaic (`"Card Large"` → `card-large`; a taken name becomes `card-large-2`). Always use the `name` / `emitted_name` the ability returns, never your input.

## Workflows

### W1 — Discover what the install has

```
mosaic-check-setup
→ mosaic-list-themes               # always start here; pick a themeID
→ mosaic-list-templates(theme_id)
→ mosaic-list-collections(theme_id)
→ mosaic-list-components(theme_id)
→ mosaic-list-classes(theme_id)
→ mosaic-list-variants(theme_id, element_type=button)   # per element type
```

### W2 — Inspect one template fully

```
mosaic-list-themes                          # pick themeID
mosaic-list-templates(theme_id)             # pick templateID
mosaic-get-template(id=templateID)          # full record + conditions JSON
mosaic-get-element-tree(document_type=template, document_id=templateID)
```

The `get-element-tree` response carries both a flat `nodes` array and a hierarchical `roots` array (each node has `children`). Use `roots` for navigation, `nodes` for stable diffing. A node's Mosaic styling is in `data.style`: `variant` (`"<variantID>"` or `"<variantID>/<variantSubClassID>"`), `universalClasses` (`{<ClassID>: <attached ClassID or sub-class ID>}`), `states` (Local Styles). Foreign classes are in `data.cssClasses`.

### W3 — Walk a design system

```
mosaic-list-themes                          # pick themeID
mosaic-list-collections(theme_id)           # pick collectionID
mosaic-list-variables(collection_id, …)     # compact: id, name, custom_property, css_variable
mosaic-get-variable(id)                     # full record: usage, propertyGroups, skinsData
mosaic-list-classes(theme_id)               # compact: id, name, emitted_name, sub_class_count
mosaic-get-class(id)                        # styles + nested sub-class tree
```

### W4 — Build a page from scratch

```
mosaic-list-themes                                    # pick themeID
mosaic-create-template(theme_id, name="Home")         # facade-backed renderable template
# template ships with the `template-internal` canvas already seeded; add real content
mosaic-add-element(document_type=template, document_id=<tpl>, parent_id=<tpl>, type=section,
  classes=["card"],                                          # attach Mosaic Classes (UUID or emitted name)
  attributes={"data-section": "hero"})                       # top-level HTML attrs
mosaic-add-element(document_type=template, document_id=<tpl>, parent_id=<section-id>, type=button,
  variant="m-button--primary")                               # the button's Variant / variant sub class
mosaic-add-element(document_type=template, document_id=<tpl>, parent_id=<section-id>, type=text)
# Text widgets are WYSIWYG HOLDERS — the visible string lives in a child
# `wysiwyg-text` node, NOT in the `text` element's own data:
mosaic-add-element(document_type=template, document_id=<tpl>, parent_id=<text-id>, type=wysiwyg-text, data={text:"Hello from the agent"})
mosaic-edit-template(id=<tpl>, path="single-page.php")  # bind to the WP request layer Mosaic's renderer matches against; the wizard facade resets path to index.php on create
mosaic-edit-template(id=<tpl>, conditions=[…])         # OR-groups of ruleRecords; only honored under assign=auto. See "conditions wire shape" below.
mosaic-set-template-assign(theme_id, type="post", type_identifier="42", template_id=<tpl>)   # type is ALWAYS the literal "post" for singular bindings, regardless of WP post-type slug
mosaic-check-design-system(document_type=template, document_id=<tpl>)   # SELF-CHECK before finishing
```

`mosaic-create-template` routes through Mosaic's own `FinishWizardEditorInstance` facade (the same code path Mosaic's wizard uses), so the produced template carries a proper `masterID` linkage + root `template-internal` node, and the Mosaic editor opens it cleanly. **Front-end rendering needs an extra step**: the wizard facade always resets `path` to `index.php`, but Mosaic's renderer matches against the WP request type — `single-page.php` for `is_page()`, `single.php` for posts, `archive.php` for archives. After `create-template` + `set-template-assign`, call `edit-template(path=…)` with the matching template-path slug or Mosaic will fall through to the "Missing Mosaic template" page.

> **Styling: use the design system, not per-element CSS.** For anything reused — colours, spacing, typography — define it once as a **variable** (a CSS custom property, see W6) and/or a **Class**, then attach the Class with `classes`, and pick the element's **Variant** with `variant`. Reserve the top-level `style` for genuine one-offs, and even then prefer `var(--token)` over a hardcoded colour/font literal. This keeps the theme's design tokens authoritative and matches how the Mosaic editor expects styling to live. (The same applies to text: put presentation on the element, not as inline `style="…"` baked into `wysiwyg-text` HTML.)

> **Self-check before you finish: `mosaic-check-design-system`.** It reports elements that styled themselves outside the design system — a hardcoded colour (`#hex`, `rgb()`, `hsl()`) or `font-family` literal in inline `style` instead of a `var(--token)`, an inline style with no Class / Variant / variable at all, and Mosaic class names sitting in the foreign `css_classes` list (`misplaced_css_classes`, rendered without their CSS). Fix each flagged element (swap the literal for a variable from `mosaic-list-variables`, move the rule into a Class attached with `classes`, or move a misplaced name from `css_classes` to `classes` / `variant`) and re-run until `flagged_nodes` is 0.

> **Text element pattern.** Adding `type=text` alone produces a visually empty widget. Text is a holder for one or more child `wysiwyg-text` nodes (the slug uses a dash). The agent must add a child of that type to make text reach the DOM.

> **`set-template-assign.type` is `"post"`, not the WP post-type slug.** Mosaic's resource model labels every singular content surface as resource type `"post"` regardless of WP post_type. A `page`-post-type WP page IS still `type="post"` in this row. The WP post-type lives implicitly on the template's `path` field instead (e.g. `path="single-page.php"` for pages, `path="single-post.php"` for blog posts, `path="single-<cpt-slug>.php"` for custom types). Mosaic's `PathPostTypePage` and `PathPostTypePost` both call `setResourceType('post')` regardless of which post-type they target.

> **`set-template-assign` requires post-type opt-in.** Mosaic only honors rows for post types that declare `post_type_supports($post_type, 'mosaic_manual_assign')`. By default `page` is supported but `post` is not — agents targeting blog posts (path=`single-post.php`) need a mu-plugin snippet: `add_post_type_support('post', 'mosaic_manual_assign');`. Without the support flag the binding row writes successfully but the renderer skips it.

> `type_identifier` is the numeric post ID, validated as a string — pass `"42"`, not `42`.

### W5 — Restructure an existing tree

```
mosaic-get-element-tree(document_type=template, document_id=<tpl>)
mosaic-move-element(id=<node>, parent_id=<new-parent>, placement=after, anchor_id=<sibling>)
mosaic-edit-element(id=<node>, data={…}, mode=merge)
mosaic-delete-element(id=<dead-section>)
```

### W6 — Build a design system

```
mosaic-list-themes                                              # pick themeID
mosaic-edit-theme-settings(theme_id, settings={"settings.layout.contentSize":"760px"})   # WordPress content width (data.settings)

# Buckets: one collection per design-token family
mosaic-create-collection(theme_id, name="Brand colors")          # → collection_id_a
mosaic-create-collection(theme_id, name="Spacings")              # → collection_id_b

# Tokens: variables under a collection, emitted as CSS custom properties
mosaic-create-variable(collection_id=<a>, name="Primary",   custom_property="--brand-primary",   type="color")
mosaic-create-variable(collection_id=<b>, name="Spacing md", custom_property="--space-md",       type="length")
# reference a variable as var(<css_variable>) only when it pins a custom_property (see Gotchas)

# Classes: reusable styles, attached to elements
mosaic-create-class(theme_id, name="Card")                       # → class {id, name:"card", emitted_name:"card"}
mosaic-set-class-style(id=<class-id>, state=normal, breakpoint_id=base,
  property=padding-top, value="var(--space-md)")
mosaic-set-class-style(id=<class-id>, state=hover, breakpoint_id=base,
  property=background-color, value="var(--brand-primary)")
mosaic-create-sub-class(parent_id=<class-id>, name="Large")      # → emitted_name "card--large"
mosaic-set-sub-class-style(id=<sub-class-id>, state=normal, breakpoint_id=base,
  property=padding-top, value="48px")

# Variants: built in per element type; extend one with variant sub classes
mosaic-list-variants(theme_id, element_type=button)              # Button (default) / Link / Badge
mosaic-create-variant-sub-class(theme_id, parent_id="m-button", name="Primary")   # → m-button--primary
mosaic-set-variant-sub-class-style(id=<variant-sub-class-id>, state=normal, breakpoint_id=base,
  property=background-color, value="var(--brand-primary)")

# Apply them
mosaic-add-element(..., type=section, classes=["card--large"])   # a sub class carries its Class with it
mosaic-add-element(..., type=button, variant="m-button--primary")
```

> **Element class inputs** (on `mosaic-add-element`, `mosaic-edit-element`, `mosaic-edit-component-instance-override`):
> - `classes: [...]` — attach Classes / sub classes by UUID or emitted name. Merges by Class: attaching `card--small` to an element that has `card--large` switches that entry; other attached Classes stay. Two entries of the same Class in one call is an error.
> - `detach_classes: [...]` — remove a Class's whole entry (name the Class or any of its sub classes). Runs before `classes`, so both in one call swaps.
> - `variant: "..."` — the Variant (UUID or class name such as `m-link`) or a variant sub class (UUID or emitted name such as `m-button--primary`). Replaces the current one; `null` / `""` resets to the element type's default. Must be a Variant the element type supports (`mosaic-list-variants`), otherwise `mo_unsupported_variant`.
> - `css_classes: [...]` — foreign class names only (theme / plugin stylesheets). Replaces the list; `[]` clears it. Mosaic class names are refused.
> - These inputs only touch their own keys: the element's Local Styles and other data are preserved.

> **Use the `set-*-style` abilities for Class / sub class / variant sub class CSS; `mosaic-set-variable-value` for design-token VALUES.**
> `mosaic-set-class-style` (and `-sub-class-` / `-variant-sub-class-`) writes one property at
> `data.states[state][breakpoint]` through Mosaic's style validator and resource
> facade. `state` defaults to `hover`; `normal` aliases `&`. Breakpoint aliases are
> `base` (`_`), `tablet` (`_t`), and `mobile` (`_m`), and custom breakpoint UUIDs are
> accepted when registered in the theme. The call merges one property and preserves
> all unrelated states, breakpoints, and properties. Make one call per property.
>
> Set a variable's actual value with `mosaic-set-variable-value(id, value, skin?, mode?)`
> for the `color`, `length`, and `n-length` types (plain CSS values — hex/named/rgb()/hsl()/…
> for color; number+unit or calc()/clamp()/var() for length). `skin` / `mode` default to the
> collection's first published skin/mode. `font`, `background`, `box-shadow` and `text-shadow`
> variables hold a structured value that stays editor-only: the agent creates them (Mosaic seeds a
> default value), the user fills in the real values in Mosaic's editor. A `background` variable emits
> one custom property per longhand (`<css_variable>-background-image`, `-background-size`, …), never
> a bare `<css_variable>`.
>
> Theme settings (`mosaic-edit-theme-settings`) take Mosaic's flat, dotted keys — the literal key
> is `"settings.layout.contentSize"`, not a nested object. The keys Mosaic supports:
> - `settings.layout.contentSize`, `settings.layout.wideSize` (WordPress content / wide widths) and
>   `styles.spacing.blockGap` (WordPress block gap), each a CSS length.
> - `theme.json` — a JSON **string** (e.g. `"{\"version\":3,\"settings\":{…}}"`) merged into
>   WordPress's theme.json; the three keys above are applied after it and win.
> - `connectedApiThemeID` and the `wizard…Category` IDs — bookkeeping Mosaic's library and wizard
>   write themselves; leave them as they are.
>
> The three length keys and `theme.json` feed WordPress's theme.json, so they style WordPress blocks in post content, not
> Mosaic elements. Keys Mosaic does not define (e.g. `containerWidth`) are stored but have no effect.
>
> Never use `novamira/execute-php`, direct SQL, or a hand-built JSON blob for Classes, Variants or
> variables: Mosaic maintains unique class and variable names itself, and bypassing its model can
> corrupt the row or make the next save in the Mosaic editor fail.

### W7 — Create loops

Loops repeat a template section over a collection of items (posts, attachments, users, terms, or a custom array). The structure is hierarchical: a `loop` element wraps `loop-items`, which contains `loop-item` children, optionally flanked by `loop-no-result` (rendered when the loop is empty) and `loop-pagination` (pagination controls).

**Loop data shape (`loop` element `data`):**
- `loopType` — the query source IDENTIFIER, NOT a category. Mosaic generates one identifier per registered source at runtime:
  - public post types → `wpPostType<Name>`: `post` → `wpPostTypePost`, `page` → `wpPostTypePage`, `attachment` → `wpPostTypeAttachment`, any public CPT `book` → `wpPostTypeBook` (Mosaic's exact rule is `'wpPostType' . ucfirst(camelCased post_type name)`).
  - users → the literal `wpUser`.
  - public taxonomies → `wpTaxonomy<Name>`, one per taxonomy: `category` → `wpTaxonomyCategory`, `post_tag` → `wpTaxonomyPostTag`.
  - custom data → `localContext`.
  All of the above except `localContext` share the same underlying source TYPE (`simple`); `localContext` is the only other type. Pick the exact identifier string for the collection you want, not a generic placeholder.
- `loopNamespace` — scopes the per-item variables. The agent sets this freely; Mosaic's built-in defaults are `post` (post-type loops), `users` (the user loop), and the taxonomy slug itself for term loops (`category`, `post_tag`, … — NOT a generic `term`). Per-item properties are accessed as `@VAR('<loopNamespace>/<property>')` inside child nodes.
- Type-specific options live under a `<loopType>Options` block (`getOptionsName()` = identifier + `Options`), e.g. `wpPostTypePostOptions`, `wpUserOptions`, `wpTaxonomyCategoryOptions`. For `simple` sources the keys are `filters`, `orderByClauses`, `offset`, `maxItems` (the per-page count — the property is literally `maxItems`, NOT `itemsPerPage`), and `paginationKey`. For `localContext` the block is `localContextOptions` and holds a single `loopSource` (a dynamic-code object of the form `{"v": "<dynamic-code>"}`).
- Sorting goes in `orderByClauses` (the literal key — an `orderBy` key is ignored and the loop keeps its default order). It is an array of clauses applied in order, each `{"uuid": "<new uuid>", "type": "<orderByType>", "<orderByType>Options": {"settings": {…}}}`. Post-type sources offer `postVariable` (settings `key` = a `wp_posts` column such as `post_title` / `post_date` / `menu_order`), `postMeta` (`key` = a meta key) and `random` (no options block); users offer `userVariable` / `userMeta` / `random`; taxonomies `termVariable` / `termMeta` / `random`. Variable and meta types also take `direction` (`ASC` default, or `DESC`) and `cast` (`auto` default). Example — titles Z→A: `"wpPostTypePostOptions": {"maxItems": "6", "orderByClauses": [{"uuid": "<uuid>", "type": "postVariable", "postVariableOptions": {"settings": {"key": "post_title", "direction": "DESC", "cast": "auto"}}}]}`.

**Nested structure:**
```
loop (data: {loopType, loopNamespace, <loopType>Options})
├── loop-items
│   └── loop-item (data: {mode: 'item'|'beforeMatch'|'afterMatch'|'separator'})
│       └── [any content — image, text → wysiwyg-variable, etc., referencing @VAR('namespace/property')]
├── loop-no-result (optional)
└── loop-pagination (optional)
    ├── loop-pagination-button-previous
    ├── loop-pagination-numbers
    │   └── loop-pagination-number
    └── loop-pagination-button-next
```

**Per-item binding:**
Variables inside `loop-item` children bind to the current item via `loopNamespace`, written `@VAR('<loopNamespace>/<property>')`. Where Mosaic injects the value depends on the host element: an `image` carries `data.image = {v: "@VAR('post/featuredImage')"}`, a `button` carries `data.url = {v: "@VAR('post/permalink')"}`, and a `wysiwyg-variable` text node carries `data.dynamicCode = "@VAR('post/title')"` (verbatim from Mosaic's seed layout). Property names vary by source:
- posts/pages/attachments (namespace `post`): `title`, `content`, `permalink`, `publish_date`, `featuredImage`, `featuredImageAlt`, `author_id`, `author_name`, … Blog posts, and custom post types that support excerpts, also have `excerpt` (WordPress's excerpt, auto-generated from the content when empty); pages and attachments do not. There is NO `date` — use `publish_date` (or `modified_date`).
- users (namespace `users`): `user_id`, `user_login`, `display_name`, `user_email`, `avatar_url`, `author_permalink`, `roles`, `user_registered`, …
- terms (namespace = the taxonomy slug, e.g. `category`): `name`, `slug`, `count`, …

**Pagination:**
Only `simple` sources whose definition enables it (the `wpPostType*` sources do; `localContext` cannot) support pagination. Set `paginationKey` to the query-parameter name (defaults to the loop element's UUID when omitted); Mosaic's renderer reads `$_GET[$paginationKey]` to pick the current page.

## Classes and Variants reference

- **`mosaic-list-classes`** / **`mosaic-get-class`** — compact list per theme; full record with styles (`data.states`), `sub_classes` tree, `favored_variants` (editor suggestion list only — no effect on rendering) and `legacy_class_name` (an older flat name some migrated Classes still render as an alias).
- **`mosaic-create-class`** / **`mosaic-edit-class`** (`name`, `label`) / **`mosaic-delete-class`** / **`mosaic-set-class-style`**. A Class name must start with a letter; a leading `m-` is reserved for Mosaic and stripped. Renaming a Class renames all its sub classes' compound names; elements keep the attachment (they reference it by UUID).
- **`mosaic-get-sub-class`** / **`mosaic-create-sub-class`** (`parent_id` = a Class or a sub class) / **`mosaic-edit-sub-class`** / **`mosaic-delete-sub-class`** / **`mosaic-set-sub-class-style`**. A sub-class name may start with a digit (`2xl`).
- **`mosaic-list-variants(theme_id, element_type)`** — the Variants an element type accepts, default first, with their variant sub classes and `can_have_sub_classes`.
- **`mosaic-get-variant-sub-class`** / **`mosaic-create-variant-sub-class`** (`parent_id` = a Variant UUID / class name, or a variant sub class) / **`mosaic-edit-variant-sub-class`** / **`mosaic-delete-variant-sub-class`** / **`mosaic-set-variant-sub-class-style`**.
- **Deletes** (Class, sub class, variant sub class) remove the record together with everything nested under it, the way Mosaic's editor does, and Mosaic updates the elements that used it: a deleted Class is detached; an element that used a deleted sub class falls back to the nearest surviving parent.

## Element-tree primitives

Every node lives in the shared `wp_mosaic_nodes` table; the abilities resolve the owning document automatically:

- **`mosaic-get-element-tree`** — flat + hierarchical view, capped at `max_nodes` (default 500, hard cap 5000).
- **`mosaic-add-element`** — inserts under a parent (parent_id = document_id for root nodes, else a node id). Position with `placement` = `at_end` (default) / `at_start` / `before` (+`anchor_id`) / `after` (+`anchor_id`).
- **`mosaic-edit-element`** — patches the node's `data` blob. `mode=merge` (default) shallow-merges your supplied keys onto the existing blob, so the keys you do NOT send are PRESERVED; `mode=replace` overwrites the whole blob verbatim (use only when sending the complete blob). Nested `data` keys are NOT deep-merged — but the class inputs (`classes`, `detach_classes`, `variant`) only touch their own entries inside `data.style`. The same `merge` default applies to `mosaic-edit-theme-settings`.
- **`mosaic-delete-element`** — soft-deletes the node AND every descendant (BFS over the document's nodes). Status flips to `delete`; rows stay in the table. Cross-document stale refs are NOT cascaded: deleting a node inside a `component` document leaves every `component-instance` reference in other templates dangling. Mosaic silently empty-renders those instances by design — sweep referencing instances explicitly when deleting a Component subtree.
- **`mosaic-move-element`** — re-parents and/or re-orders. Cycle prevention is built in. `parent_id = document_id` moves the node to the root.

`ordering` is a fractional-index string (Mosaic's `FractionalIndex::generateKeyBetween`). Never edit it directly — let the placement parameters do the work.

### Component-instance overrides

A Mosaic component is a reusable subtree (`mosaic-list-components` / documentType=`component`); a `component-instance/<component UUID>` node is one placement of it inside a template/master/styleGuide/another component. By default every property on an instance inherits LIVE from the shared component. An **override** lets an editor change ONE property on ONE instance without touching the shared definition (Mosaic's `ComponentInstanceElementMResource`, `NodeMResourceManagerAbstract::createNodeOverride`):

- The override is a SEPARATE `wp_mosaic_nodes` row, living in the HOST document (the instance's own document, not the component's): `parentType = 'override'`, `parentID = <instance node UUID>`, `type = <original node's type>`, `data = { override: { originalID: <original node UUID> }, ...overridden properties }`. The overridden properties are SIBLINGS of `data.override`, not nested inside it.
- Overrides for an entire instance subtree — including nodes reached through a component nested several levels deep — are all FLAT children of the SAME outermost instance node; Mosaic never nests an override under another override.
- `mosaic-get-element-tree` on the HOST document never surfaces these rows (it only walks `parentType=node`); `mosaic-add-element` cannot create them; `mosaic-edit-element` can only touch one if its UUID is already known some other way. Use the two abilities below instead of raw SQL.

- **`mosaic-edit-component-instance-override`** — `instance_id` (the `component-instance/<uuid>` node) + `original_node_id` (the node inside that component, or a component nested inside it, whose property you're overriding) + `data` / `style` / `attributes` / `css_classes` / `classes` / `detach_classes` / `variant` (`variant` is checked against the overridden node's element type). Creates the override row on first call for a given `original_node_id`, merges onto it (preserving properties you don't send) on every call after. `expected_revision` is an optional optimistic-concurrency guard for the UPDATE path only — supplying it on a first-time create returns `mo_invalid_input`.
- **`mosaic-clear-component-instance-override`** — `instance_id` + `original_node_id`. Soft-deletes the override row; the instance reverts to fully inheriting from the shared component. No-op (`cleared: false`) when no override exists.
- Both abilities validate `original_node_id` actually belongs to the instance's component (or a component nested inside it) before writing, and reject with `mo_override_corrupt` if more than one override row is ever found for the same `(instance_id, original_node_id)` pair — that shape only happens via direct SQL bypassing these abilities.

## Template CRUD

- **`mosaic-create-template`** — fresh, empty tree. `name` required; `assign` defaults to `manual`, `path` is coerced to `index.php` by Mosaic's wizard facade regardless of input, `conditions` defaults to `[]`.
- **`mosaic-edit-template`** — partial patch of `name` / `assign` / `path` / `conditions`. Conditions are replaced wholesale.
- **`mosaic-delete-template`** — soft-delete. Element tree is left untouched: re-publishing the template row via SQL (`UPDATE wp_mosaic_templates SET status='publish' WHERE ID = ?`) restores the canvas exactly as it was. `mosaic-get-element-tree` keeps returning the canvas even while the template is soft-deleted (it filters on node status, not parent template status) — useful for "preview before restore". Caveat: if both template AND nodes were independently soft-deleted, republishing the template alone gives an empty canvas — node rows must be republished separately.

### `conditions` wire shape

Mosaic stores `conditions` as a JSON ARRAY of OR-groups. Each group is `{ruleRecords: [{ruleID, uuid, "<ruleID>Options": {operator, settings, value}}]}`. Recognized `ruleID`s:

| ruleID | Matches |
|---|---|
| `httpGet` | a GET query parameter |
| `httpCookie` | a cookie value |
| `userLoggedIn` | the visitor's auth state |
| `userRole` / `userCapability` / `userFields` / `userMeta` | properties of the logged-in user |
| `post<Tax>` (e.g. `postCategory`, `postPostTag`) | a public taxonomy on the current post — one rule auto-registered per public taxonomy |

Empty `[]` matches every page under `assign=auto`. Unknown `ruleID`s silently never match. Conditions are **ignored entirely under `assign=manual`** — to bind a manual template, use `mosaic-set-template-assign` instead. There is no `posts` / `archive` / `singular` / `front_page` rule at this layer.

## Template assigns

`wp_mosaic_template_assigns` is Mosaic's **manual template assignment** feature: bind a single post to a specific template. Mosaic reads ONE pattern from this table:

- `type` = Mosaic's INTERNAL resource type. For singular post bindings always pass `"post"` — Mosaic uses the same resource type for pages, blog posts, and every registered CPT.
- `type_identifier` = the numeric post ID as a string.
- The WP post-type lives on the template's `path` field instead: `single-page.php` for pages, `single-post.php` for blog posts, `single-<cpt-slug>.php` for CPTs.

Every other content surface (archives, taxonomies, 404, search, front page) is matched at render time via the template's `conditions` JSON, NOT via this table — use `mosaic-edit-template` to set `conditions` for those.

- **`mosaic-list-template-assigns`** — every binding under a theme.
- **`mosaic-set-template-assign`** — PUT-style binding. Re-running the same triple replaces the previous template_id.
- **`mosaic-clear-template-assign`** — removes a binding. Idempotent (returns `deleted: true` even when no row matched).

## Gotchas

- **Onboarding is license-gated.** On a free Mosaic install the wizard cannot complete; the install can show `tables_present=true` but `entities.themes=0`. Surface this state to the user and ask them to complete the wizard before write abilities can run.
- **`var(--<row-id>)` is wrong — and so is `var(<css_variable>)` on a variable with an empty `custom_property`.** A variable with a pinned `custom_property` is always emitted under that name, so `var(<css_variable>)` is safe — except for `background` variables, which emit only suffixed longhands (`var(<css_variable>-background-image)`, `-background-size`, …). A variable whose `custom_property` is empty (Mosaic's editor and library themes create these) gets a name Mosaic derives from the collection and variable labels, and Mosaic emits it only when an element references it natively (`{"var": "<variable-uuid>"}` as a style value). So a literal `var(<derived name>)` resolves to nothing unless something else uses the variable, and stops resolving the moment anyone renames the collection or variable label — Mosaic recalculates the derived name, including when its own updates rename library tokens — while existing literal references are never rewritten. Before you write a literal `var(...)`, check `custom_property` in `mosaic-get-variable`; if it is empty, pin one with `mosaic-edit-variable(id, custom_property="--…")` and use the returned `css_variable`. Pinning changes the emitted name: native `{"var": uuid}` references follow it automatically, older literal references to the derived name do not.
- **Mosaic class names in `css_classes` render unstyled.** If `mosaic-check-design-system` reports `misplaced_css_classes` on an existing page, move each name to `classes` (Classes) or `variant` (Variants) and drop it from `css_classes`.
- **`assign` enum.** `auto` (Mosaic decides which front-end pages match) vs `manual` (the user wires it via template_assigns).
- **`status='delete'` is soft for templates and elements.** Deleted rows stay in the table; `mosaic-get-theme` / `mosaic-get-template` / `mosaic-get-element-tree` hide them. Resurrection is manual SQL. Classes, Variants' sub classes and variables are removed for good.
- **Lock keys are hashed.** `mo_with_lock()` uses sha1 of `(blog_id, key)` because Mosaic UUIDs blow past MySQL's 64-char GET_LOCK limit.
- **Ordering uses BINARY collation.** Every `ORDER BY ordering` and `WHERE ordering <op> ?` is wrapped in `BINARY` because the table is `utf8mb4_unicode_520_ci` (case-insensitive) but `FractionalIndex` assumes ASCII binary collation.
- **JSON object/list shapes are type contracts, not interchangeable.** Template `conditions` is typed `?array`, so its empty form is `[]`; `{}` decodes to `stdClass` and can cause a site-wide TypeError. Object-typed entity `data` columns (nodes, themes, classes, settings) use `{}` when empty. Inside a class `data` object, `states`, each state, and each breakpoint are objects. `localStates` and `interactions`, when non-empty/present, are arrays. The `set-*-style` abilities verify this envelope before Mosaic persists it. Never hand-encode or write these blobs through `execute-php` / SQL.
- **Blob depth ≤ 255.** `data` / `conditions` get rejected with `mo_invalid_input` when nested past 255 levels. Flatten before retrying.
- **Blob bytes ≤ 1 MiB.** `data` / `conditions` payloads larger than 1 048 576 bytes (after JSON-encoding) are rejected with `mo_payload_too_large`.
- **Field-length caps.** Column-level ceilings are enforced at the boundary with `mo_invalid_input` rather than silently truncating: `template.name` ≤ 191, `template.path` ≤ 191, `node.type` ≤ 128, `template_assign.type` ≤ 36, `template_assign.type_identifier` ≤ 100, class `name` / `label` ≤ 191. Counts are in characters.
- **Read-path decode failure marker.** If a blob persisted via direct SQL is too deep or malformed for `mo_decode_data` to parse, the response carries `{_decode_failed: true, _bytes: N}` instead of an empty `{}`.
- **UUIDs are case-insensitive in queries, case-sensitive in PHP.** Mosaic stores UUIDs lowercase and the abilities lowercase every UUID-bearing input at the boundary so PHP `===` comparisons work regardless of casing the agent passes.

## `novamira_pro_mosaic_changed` hook

Every successful write ability fires `do_action('novamira_pro_mosaic_changed', $payload)`. The payload always carries `ability`, `operation`, `entity`, `entity_id`, `document_type`, `document_id`, `theme_id`, `template_id`, `user_id` (nullable fields default to `''`). Class writes use `entity` = `class`, `sub_class` or `variant_sub_class`. Use this to mirror agent writes into your own audit log / cache invalidation / activity stream.

Contract details listeners must respect:

- **Emits fire under the write lock.** MySQL `GET_LOCK` is re-entrant on the same connection, and WordPress runs the whole request on one wpdb connection, so a listener that calls back into Mosaic abilities in-process does NOT deadlock. The two real hazards instead: (1) the listener's writes commit *while the outer write still holds its session lock*; (2) a **separate** HTTP request / WP-CLI process contending for the same lock key WILL block up to 5s and return `mo_lock_conflict`. Defer outbound work via `wp_schedule_single_event` / queue dispatch when the listener's target can hit the same lock key.
- **Listener exceptions don't roll back the outer write.** A throw inside a `novamira_pro_mosaic_changed` handler propagates up as `ability_callback_exception`, but the outer ability's DB write has already committed. Listeners that may fail should catch their own throws.
- **Idempotent no-ops on the delete path do NOT fire.** `delete-element` on an already-soft-deleted node, `clear-template-assign` on a non-existent binding, `delete-template` on an already-deleted row and `delete-class` on a missing Class return without firing the hook because no rows changed.
- **No-op edits DO currently fire** (known gap). `edit-template` / `edit-element` / `move-element` rotate the `revision` UUID and emit the hook every call, even when the supplied fields match the current values. If you need to react only to real state changes, compute a content-level diff in your listener.

## Cohabitation with the Mosaic editor

When a user has the Mosaic admin editor open on the same template the agent is writing to, two contracts surface:

- **Last-writer-wins, no compare-and-swap.** Mosaic's editor commit endpoint (`RESTEditorInstanceCommit::isCommitAllowed()`) returns `true` unconditionally. If the user opens the editor at T0, the agent edits a node at T1, and the user clicks Update at T3, the editor commits its local snapshot (taken at T0) for every dirtied node, silently overwriting the agent's T1 edit on any node the user didn't visibly touch. Mitigation today is procedural: ask the user to close the editor before scripted edits, or re-run the agent's writes if the user-edited revision wins.
- **Template revision doesn't rotate on per-node edits.** Every node edit rotates the node's own `revision` UUID. The parent template's `revision` does NOT rotate. Third-party consumers of `novamira_pro_mosaic_changed` should track `entity` + `entity_id` from the payload, not just `template_id`.

## Ability map

- **Setup / audit** — `check-setup`, `check-design-system`
- **Discovery** — `list-themes`, `list-templates`, `list-components`, `list-collections`, `list-variables`, `list-classes`, `list-variants`, `get-theme`, `get-template`
- **Element tree + templates + assigns** — `get-element-tree`, `add-element`, `edit-element`, `delete-element`, `move-element`, `create-template`, `edit-template`, `delete-template`, `list-template-assigns`, `set-template-assign`, `clear-template-assign`
- **Classes** — `get-class`, `create-class`, `edit-class`, `delete-class`, `set-class-style`, `get-sub-class`, `create-sub-class`, `edit-sub-class`, `delete-sub-class`, `set-sub-class-style`
- **Variants** — `get-variant-sub-class`, `create-variant-sub-class`, `edit-variant-sub-class`, `delete-variant-sub-class`, `set-variant-sub-class-style`
- **Design tokens** — `get-collection`, `create-collection`, `edit-collection`, `delete-collection`, `get-variable`, `create-variable`, `edit-variable`, `delete-variable`, `set-variable-value` (`color` / `length` / `n-length`), `get-theme-settings`, `edit-theme-settings`
- **Component-instance overrides** — `edit-component-instance-override`, `clear-component-instance-override`
- **Not yet available** — `font` / `background` / `box-shadow` / `text-shadow` variable values (structured value, still editor-only), styling a built-in Variant itself (its sub classes are writable), an element-data schema per element type
