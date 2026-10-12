---
name: kadence-build-page
description: Build or rebuild a page on a Kadence-themed site — read or write theme settings (palette, typography, layout, header / footer / mobile / archive surfaces), browse the Kadence Blocks catalogue, compose Gutenberg block_spec payloads using Kadence Blocks primitives, and submit them through Novamira's gutenberg pending-change pipeline. Activate when the user asks to create or configure a page on a Kadence site, change the theme palette / fonts / header / footer chrome, build a section with Kadence Blocks (rowlayout, advancedbtn, accordion, postgrid, query, advanced-form, header builder, …), or apply theme.json presets to block content.
---

# Building a page on Kadence

Kadence pages are NOT built by a single internal element tree — they are
standard WordPress pages whose visual stack is governed by three layers:

1. **Kadence theme settings** (`theme_mod`-stored, key bag mirrored under
   `kadence_settings`) — global typography, palette, layout, header /
   footer / mobile / archive chrome.
2. **theme.json** — WP-native global settings + styles + presets the
   block editor reads at render time. Kadence ships a theme.json that
   feeds the standard color / gradient / font-size / spacing-size
   palettes the agent can reference by slug.
3. **Kadence Blocks** (free + Pro) — ~87 Gutenberg blocks under the
   `kadence/*` namespace (rowlayout, column, advancedbtn, accordion,
   advancedheading, advancedgallery, postgrid, query, slider, modal,
   advanced-form*, header-*, off-canvas, …). The block tree lives in the
   page's `post_content`.

The integration provides **introspection + theme-settings CRUD**. Reading
and writing block trees on a post is handled by the
`novamira/gutenberg-*` abilities exposed by the **base Novamira plugin**;
this skill points the agent at those.

## First call: check setup

Always start with `novamira/kadence-check-setup`. It reports:

- `theme.active` / `theme.min_satisfied` / `theme.version`
- `storage.type` (`theme_mod` vs `option`) and `storage.option_name`
- `entities` — activation flags for the Kadence-introduced CPTs
  (`kadence_header`, `kadence_navigation`, `kadence_form`)
- `palette.name` + `palette.colors[]`
- `current_user_can_manage`

If `theme.active=false`, stop and surface — none of the other kadence-*
abilities will return useful data.

Then call `novamira/kadenceblocks-check-setup`. It reports:

- `active` (free Kadence Blocks plugin)
- `pro_active` (Kadence Blocks Pro)
- `blocks_registered.{free, pro, total}`

If `active=false`, the page body can only use core Gutenberg blocks —
warn the user before promising a Kadence-styled build, or propose
installing Kadence Blocks free from wp.org.

## Theme-settings workflow

Kadence exposes ~1000 individual settings keys (typography, colors,
layout, header bars, mobile menu, archive layouts, page-title block,
product / lms integrations, …). The catalogue is too large to dump in
full; the discovery flow is:

1. `novamira/kadence-list-customizer-settings` (no input) returns the
   areas overview: `[{name, count}, ...]` sorted by count desc. Pick
   the area you actually need.
2. `novamira/kadence-list-customizer-settings area=<name>
   with_defaults=true` returns the keys for that area + their types +
   default values.
3. `novamira/kadence-get-settings keys=[…]` reads the current values
   (post-override). Unknown keys come back under `unknown_keys` — treat
   as typos.
4. `novamira/kadence-edit-settings settings={key: value}` writes. Type
   mismatches between the input value and the registered default
   surface under `warnings[]` (the write still happens — Kadence is
   tolerant about shape). Unknown keys are NOT persisted.

The storage strategy is automatic — `theme_mod` mode writes one
theme_mod per key, `option` mode patches the shared `kadence_settings`
wp_option bag. Reads after a write are immediately consistent.

## Two color systems, used in different places

Kadence ships **two** parallel color sources that look similar but
serve different consumers — get this wrong and the agent ends up
referencing slugs that don't exist on the target consumer.

1. **WP theme.json palette** — `novamira/kadence-get-theme-json
   slice=presets` returns `{colors, gradients, fontSizes,
   spacingSizes}` records with **WP-core preset slugs** like `black`,
   `vivid-red`, `cyan-bluish-gray` and (from Kadence's theme.json)
   `theme-palette1` .. `theme-palette9` etc. These slugs resolve to
   `var(--wp--preset--color--<slug>)` CSS variables and are what
   **core Gutenberg blocks** (`core/paragraph.textColor`,
   `core/heading.backgroundColor`, …) expect.
2. **Kadence theme palette** — `novamira/kadence-check-setup.palette.
   colors[]` returns the 9 user-editable hex strings (color1..color9
   plus a few accent slots). Kadence Blocks attributes like
   `kadence/advancedheading.colorClass` /
   `kadence/advancedbtn.btns[].colorClass` expect the matching slugs
   `palette1`..`palette9` (NOT `theme-palette1`) which resolve to
   `var(--global-palette<n>)` CSS variables.

When composing a `kadence/*` block, use **Kadence-palette** slugs in
`colorClass` / `color` attributes; the indexes match
`check-setup.palette.colors[i]`. When composing a `core/*` block, use
the **WP theme.json** slugs. The two systems do converge at CSS
render time (the WP `--wp--preset--color--theme-paletteN` and
Kadence `--global-paletteN` both point at the same hex) but the slug
names are NOT interchangeable.

Prefer slugs over hardcoded colors in both cases so the user can
re-skin once and have every reference re-resolve. More broadly, **never
reach for inline `style`** — express presentation through the block's own
attributes (palette/`colorClass`, typography, spacing) and theme.json /
Kadence global presets, and reserve inline `style="…"` (incl. style baked
into rich-text HTML) for genuine one-offs. Inline styling is invisible to
the Customizer and the design system, and the customer cannot re-skin it.

## Kadence Blocks workflow

The actual read / write of block trees on a post lives in the
`novamira/gutenberg-*` abilities exposed by the **base Novamira plugin**.
This integration only surfaces:

- `novamira/kadenceblocks-list-blocks` — compact catalogue
- `novamira/kadenceblocks-get-block-schema name="<kadence/...>"` — full
  attribute schema for one block

Base Novamira 1.11.4 and later also expose the generic
`novamira/gutenberg-list-block-types` / `novamira/gutenberg-get-block-type`.
Kadence registers its full attribute maps server-side, so both surfaces
return the same schema; prefer the `kadenceblocks-*` pair when you also
need the free/pro family classification.

### Canonical write flow

1. `novamira/kadenceblocks-check-setup` — confirm free + Pro presence
2. `novamira/kadenceblocks-list-blocks family=…  name_contains=…` —
   pick the blocks. Common picks: `kadence/rowlayout` (section wrapper,
   parent of columns), `kadence/column` (column inside a rowlayout),
   `kadence/advancedheading`, `kadence/advancedbtn`, `kadence/spacer`,
   `kadence/iconlist`, `kadence/accordion`, `kadence/tabs`,
   `kadence/postgrid` / `kadence/query` (loops, Pro), `kadence/slider`,
   `kadence/advancedgallery`, the `kadence/advanced-form*` family for
   contact forms
3. `novamira/kadenceblocks-get-block-schema name="kadence/rowlayout"`
   etc. — read attribute map for each block you intend to author
4. `novamira/gutenberg-get-content target_id=<page_id>` — snapshot
5. Compose the block_spec — an array of `{name, attributes,
   innerBlocks}` matching the WP block-spec shape. Use preset slugs
   from `kadence-get-theme-json` where applicable. **On base Novamira
   1.11.3 and earlier, every `kadence/*` block also needs a manual
   `uniqueID`, and six block types need `kbVersion: 2`** — see
   "Editor-assigned attributes" below
6. **`novamira/gutenberg-add-pending-change target_id=<page_id>
   block_spec=…`** — queue the change. Kadence Blocks go through the
   pending-change pipeline like any third-party block (every Kadence
   Block reports `is_dynamic: true` because it has a render_callback,
   but composition still goes through the standard block_spec path).
   Do NOT call `novamira/gutenberg-write-content` directly: that is
   reserved for `novamira/*` dynamic-only blocks
7. `novamira/gutenberg-enable-batch-finalization batch_id=…` — produces
   a finalization URL. Send it to the user; opening it in the browser
   runs Gutenberg's JS validator on the queued specs and persists the
   tree. The server-side ingestion only checks block_spec STRUCTURE
   (every entry has a `name`, `attributes` is an object if present,
   `innerBlocks` is an array if present); it does NOT cross-check
   against the block registry or attribute schemas. Wrong block names,
   wrong attribute types, missing parent constraints — all of these
   are surfaced only when the user finalizes in the browser
8. `novamira/gutenberg-get-pending-batch batch_id=…` — after the user
   has opened the finalization URL, read the batch outcome. Never
   assume the write succeeded; see "Reading the finalization outcome"

### Reading the finalization outcome

`gutenberg-get-pending-batch` reports the batch `status` and, on every
item, `serialization_runtime` plus a `serialization_runtime_reason`.
When recorded, the runtime is `iframe` (the hidden block editor
serialized the item) or `fallback` (the Queue page had to use its own
block runtime); it is empty when no runtime metadata was stored for
the item (for example a conflicted item, or an item the queue never
processed). Act on them:

- **`finalized`** with `serialization_runtime: iframe` — the tree was
  persisted through the hidden block editor.
- **`failed` with `editor_frame_inaccessible` (when emitted), or with
  `missing_block_registration` rows for `kadence/*` blocks while the
  item reports `serialization_runtime: fallback`** (or a reason naming
  the hidden editor iframe / serializer bridge) — the Queue page could
  not use the hidden block editor and serialized against its own
  registry, which Novamira populates with core blocks only: Kadence's
  editor scripts are not loaded on that page, so every `kadence/*`
  block is reported as unregistered even though the names are correct. This
  is an environment problem on the finalizing browser/site, NOT a
  block_spec problem: do not "fix" the spec, do not retry with
  different block names, and do not bypass the pipeline by writing
  block markup through `update-post`, `execute-php` or
  `gutenberg-write-content` (hand-authored Kadence markup lacks the
  editor-assigned attributes and renders unstyled). Report the
  `serialization_runtime_reason` to the user, ask them to update the
  base Novamira plugin to its latest release and to re-open the Queue
  page in a current browser, then re-enable finalization for the same
  batch. If it still falls back, forward the reason text to support.
- **`failed` with `missing_block_registration` while
  `serialization_runtime` is `iframe`** — the editor itself does not
  register that block. This often means Kadence Blocks (or Pro, for
  `postgrid` / `query`) is inactive or the block name is wrong, but a
  Kadence editor bundle that failed to initialise leaves the same
  trace. Re-check with `kadenceblocks-list-blocks` before
  resubmitting.
- **`failed` with an item in `conflicted` status** — the target
  changed since the snapshot; re-read with `gutenberg-get-content` and
  resubmit.

> **Requires Novamira free 1.5.0+ for `kadence/*` blocks.** Earlier
> releases of the base plugin ship a V1 Gutenberg pipeline that
> supports core blocks only; submitting a `kadence/*` block to
> `gutenberg-add-pending-change` against pre-1.5.0 returns
> `gutenberg_unsupported_static_block`. Verify the version via the
> `NOVAMIRA_VERSION` constant before promising a Kadence-Blocks-powered
> build.

### Editor-assigned attributes: `uniqueID` and `kbVersion`

Kadence assigns two attributes from its `edit()` mount effects, not
from defaults — and when they are missing, both failure modes pass
block validation silently (verified empirically on Kadence Blocks
3.7.9):

1. **`uniqueID` (every `kadence/*` block).** Kadence emits per-block
   CSS keyed to it (`.kt-adv-heading<uniqueID>`,
   `#kt-layout-id<uniqueID> > .kt-row-column-wrap`, …). Without it,
   every style-carrying attribute — colors, background, padding,
   column widths, button styling — produces NO css (and the markup
   ships a literal `data-kb-block="kb-adv-headingundefined"`).
   Symptom: the layout renders but looks completely unstyled.
2. **`kbVersion: 2` (the six blocks that declare it)**: `rowlayout`,
   `column`, `infobox`, `testimonials`, `advancedgallery`,
   `googlemaps`. Their `save()` emits inner content only and the PHP
   renderer rebuilds the structural wrapper only when
   `kbVersion > 1` (the attribute default is `''`; the editor sets 2
   on mount). Symptom without it: `kadence/rowlayout` renders its
   columns stacked full-width — the grid CSS is generated but the
   `kb-row-layout-wrap > kt-row-column-wrap` wrapper element it
   targets is never emitted.

Whether the agent must supply them depends on the base Novamira
version (check the `NOVAMIRA_VERSION` constant):

- **Base 1.11.4 and later** — the Block Editor Queue mounts the
  submitted blocks in the hidden editor before serializing, so
  Kadence's own mount effects assign `uniqueID` and `kbVersion`
  automatically. Do NOT supply them; the editor generates fresh
  site-unique values.
- **Base 1.11.3 and earlier** — the finalizer serializes with
  `createBlock()` only; `edit()` never mounts and the agent MUST
  supply both explicitly in the block_spec. For `uniqueID` use the
  editor's shape `<postId>_xxxxxx-yy` (lowercase hex, e.g.
  `5_a1b2c3-07`) and keep every value unique within the site.

### Nesting hints

## Loops and per-item data binding

Kadence renders repeating post layouts with two block families:

- **`kadence/posts`** (free) — a self-contained dynamic grid. It queries
  posts via `postType`, `categories`, `tags`, `order`, `orderBy`,
  `postsToShow`, `offsetQuery` and lays them out with `columns` /
  `loopStyle`. Which post elements appear is toggled by `image`, `excerpt`,
  `author`, `date`, `meta`, `comments` (note: there is no `title`
  attribute — the title is always rendered; `titleFont` only styles it).
  This block has **no `innerBlocks` and no `providesContext`** — the layout
  is hard-coded and it cannot host custom per-item templates. Use it when
  the built-in grid is enough.
- **`kadence/postgrid`** / **`kadence/query`** (Pro, `kadence/*` namespace) —
  loop blocks that DO accept an inner template and provide per-item context
  to their children. These are NOT in Kadence Blocks free; when
  `pro_active=false` they come back `not_found` from `get-block-schema`,
  so their exact attribute map cannot be introspected from this install.
  Do not assume postgrid mirrors `posts` attribute names — observed Pro
  usage uses plural/different keys (e.g. `postTypes` as an array,
  `showImage`), so always confirm via `get-block-schema` on a site that
  has Pro active before authoring its attributes.

### Per-item context keys

Inside a Pro loop, child blocks read the current item via block context.
A block opts in by listing the keys in its `usesContext` array (verify per
block with `get-block-schema`). The keys, exactly as they appear:

- **`postId`** — integer ID of the current post in the loop
- **`queryId`** — query identifier (caching / state)
- **`kadence/dynamicSource`** — data-source type string
- **`kadence/repeaterRowData`** — current item's row-data payload
- **`kadence/repeaterRow`** — current item's row index

Many free content blocks already declare these and will pick up loop
context automatically when nested under a Pro loop — confirmed on
`kadence/advancedheading`, `kadence/rowlayout`, `kadence/column`,
`kadence/image`, `kadence/infobox`, `kadence/advancedgallery`,
`kadence/single-icon`, `kadence/iconlist`, `kadence/listitem`,
`kadence/videopopup` (e.g. `kadence/advancedheading` declares
`"usesContext": ["postId", "queryId", "kadence/dynamicSource", "kadence/repeaterRowData", "kadence/repeaterRow"]`).
Note `kadence/posts` is NOT a context provider — nesting blocks "inside"
it is not a thing; reach for `kadence/postgrid` / `kadence/query` when you
need a custom per-item template.

Some Kadence Blocks have parent / ancestor constraints. Always check
`parent` / `ancestor` on the schema before placing a block:

- `kadence/column` requires a `kadence/rowlayout` ancestor — placing it
  elsewhere renders but breaks the row layout
- `kadence/accordion-pane` requires `kadence/accordion` parent
- `kadence/tab` requires `kadence/tabs` parent
- `kadence/header-*` blocks live inside `kadence_header` CPT content,
  not in a regular page

### Schema is shape-only, not value-domain

`kadenceblocks-get-block-schema` returns the WP block-type attribute
map: each attribute's `type` and `default`. It does NOT enumerate
allowed values. Attributes like `htmlTag`, `align`, `colorClass`,
`sizeType`, `paddingType`, `iconStyle` are all `type: string` with
no `enum` constraint. The Gutenberg pipeline accepts any string and
defers semantic validation to browser-side block validation at
finalization time — a wrong value will silently render as the block's
fallback rather than be rejected at submit. Where the agent is
unsure, prefer the block's default (a value-shape that's known to
round-trip) over guessing.

### Advanced Forms — the two-post pattern

`kadence/advanced-form` (page-side) is **not** a container — its
schema declares `id: integer`, and that id points at a `kadence_form`
CPT post which holds the actual form-field block tree
(`kadence/advanced-form-text`, `-email`, `-submit`, …). Nesting form
fields directly under the page-side `kadence/advanced-form` is
accepted at submit time but ignored at render time. To build a
contact form:

1. `novamira/create-post post_type=kadence_form post_title="Contact"`
   — returns the form CPT id (call this `$FORM_ID`)
2. Compose the field tree as a single `kadence/advanced-form` ROOT
   block with its `advanced-form-*` children — and submit via
   `novamira/gutenberg-add-pending-change target_id=$FORM_ID`.
   **CRITICAL: the CPT-side root advanced-form must NOT carry an `id`
   attribute** — only the page-side reference does. If both sides
   carry the same id, the renderer follows the id-pointer recursively
   (form 8091 references form 8091…) until PHP OOMs at ~512 MB.
   Symptom from the public frontend is an HTTP 200 with
   `Content-Length: 0` (the fatal is swallowed by output buffering).
   Verified empirically — kadence/advanced-form has no self-reference
   guard
3. After finalization of (2), compose the page-side
   `kadence/advanced-form` with `attributes.id=$FORM_ID` and **no
   `innerBlocks`**, and submit it as part of the page's
   `gutenberg-add-pending-change` batch
4. Finalize the page batch

This pattern mirrors how Kadence's editor UI manages forms behind
the scenes; the agent has to reproduce it explicitly because
`kadenceblocks-get-block-schema` doesn't surface the cross-post link
or the no-self-id rule.

### Free vs Pro

Kadence Blocks Pro extends the same `kadence/*` namespace as the free
plugin — it does NOT use a separate `kadence-pro/*` namespace. The
`family` field on each list-blocks record (`free` / `pro`) is computed
by inspecting the block's `render_callback` class. When `pro_active=
false`, every Pro block name comes back as `not_found` from
`get-block-schema` — fall back to free equivalents or surface the gap
to the user.

## Header / Navigation / Forms — CPT entities

Kadence ships three CPTs that hold block-tree content:

- `kadence_header` — Header Builder content; visible in admin under
  Kadence > Header
- `kadence_navigation` — Menu-as-blocks (`kadence/navigation` block
  family)
- `kadence_form` — Advanced Forms (`kadence/advanced-form*` blocks);
  see "Advanced Forms — the two-post pattern" above

These are standard WP CPTs. To create/edit one:

1. `novamira/create-post post_type=<cpt_slug>` — creates an empty
   post for the CPT
2. Compose a `block_spec` with the relevant Kadence block family
   inside
3. `novamira/gutenberg-add-pending-change target_id=<new_cpt_post_id>
   block_spec=…` — populate the content via the standard pipeline
4. Finalize via the URL

No dedicated kadence-* abilities cover this — the existing WP +
gutenberg pipeline is sufficient and avoids duplicating the surface.

## What this skill is not for

- Page body content for non-Kadence-Blocks sections — use core Gutenberg
  blocks via the base `novamira/gutenberg-*` abilities (no kadence-*
  ability needed)
- Plugin / theme installation — out of scope
- Customizer schema introspection beyond the per-area key catalogue —
  Kadence's React-based Customizer is rich but the keys + defaults
  surfaced by `list-customizer-settings` cover the value-shape side that
  the agent needs to compose a write payload
- Direct theme.json file editing — read-only via
  `kadence-get-theme-json`; user-level overrides go through the active
  `wp_global_styles` CPT post which is outside this skill's scope
