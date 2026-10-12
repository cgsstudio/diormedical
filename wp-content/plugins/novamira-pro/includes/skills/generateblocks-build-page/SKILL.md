---
name: generateblocks-build-page
description: Build or rebuild the body of a page, post, or CPT with GenerateBlocks — browse the GenerateBlocks catalogue (free + Pro), read per-block attribute schemas, compose Gutenberg block_spec payloads with GenerateBlocks primitives (element, text, headline, button, media, shape, query / looper / loop-item), and submit them through Novamira's gutenberg pending-change pipeline. Works on any theme, not only GeneratePress. Activate when the user asks to build a section / hero / feature grid / CTA / query loop with GenerateBlocks, compose a page body out of GenerateBlocks blocks, or author against a GenerateBlocks-based framework / design system.
---

# Building content with GenerateBlocks

GenerateBlocks is a **block library**, not a theme. Its blocks are standard
Gutenberg blocks that live in a post's `post_content`, so they work under any
theme (GeneratePress, Kadence, Blocksy, a block theme, a classic theme). If the
site also runs the GeneratePress theme and you need theme chrome (header /
footer / hooks / global typography), pair this with the `generatepress-build-page`
skill; this skill is only about the block-composition layer.

The integration Novamira Pro ships for GenerateBlocks is **introspection only**:

- `novamira/generateblocks-check-setup` — presence + versions + block counts
- `novamira/generateblocks-list-blocks` — compact catalogue
- `novamira/generateblocks-get-block-schema` — full attribute schema for one block

Reading and writing the block tree on a post is done by the
`novamira/gutenberg-*` abilities exposed by the **base Novamira plugin** (free).
This skill points the agent at those.

## First call: check setup

Always start with `novamira/generateblocks-check-setup`. It reports:

- `active` / `version` — GenerateBlocks free
- `pro_active` / `pro_version` — GenerateBlocks Pro
- `blocks_registered.{free, pro}` — sanity counts per namespace

If `active=false`, stop and surface it — none of the other `generateblocks-*`
abilities return useful data, and the page body can only use core Gutenberg
blocks. Propose installing GenerateBlocks free from wp.org before continuing.

If `pro_active=true`, the extra `generateblocks-pro/*` blocks (tabs, accordion,
carousel, navigation, site-header) are available on top of the free set, plus
dynamic-data binding attributes wired into the free blocks.

## The catalogue (free)

Block names registered by GenerateBlocks 2.x (free), under the
`generateblocks/*` namespace:

| Block | Use for |
|---|---|
| `generateblocks/element` | Generic section / container with background, padding, max-width |
| `generateblocks/text` | Paragraph with fine typographic controls |
| `generateblocks/headline` | Heading with clamp / line-height / letter-spacing controls |
| `generateblocks/button` | Button with hover + icon support |
| `generateblocks/image` | Image with object-fit, ratio, hover |
| `generateblocks/media` | Image / video media block |
| `generateblocks/shape` | Decorative SVG shape (wave dividers, blobs) |
| `generateblocks/query` + `generateblocks/looper` + `generateblocks/loop-item` | Query loop for CMS content (posts / CPTs) |

`pro_active=true` adds these Pro families under the **distinct**
`generateblocks-pro/*` namespace:

| Family | Blocks | Use for |
|---|---|---|
| Accordion | `accordion`, `accordion-item`, `accordion-toggle`, `accordion-toggle-icon`, `accordion-content` | Collapsible FAQ-style content |
| Tabs | `tabs`, `tabs-menu`, `tab-menu-item`, `tab-items`, `tab-item` | Tabbed sections |
| Carousel | `carousel`, `carousel-items`, `carousel-item`, `carousel-pagination`, `carousel-control` | Sliders, testimonial reels |
| Navigation | `navigation`, `menu-toggle`, `menu-container`, `classic-menu`, `classic-menu-item`, `classic-sub-menu` | Full-featured nav + mobile menu toggle |
| Site Header | `site-header` | Composite site-header block |

Pro also adds dynamic-data binding on the free blocks (Headline / Text / Button
read from post fields, meta, taxonomy) — no new block, just extra attributes on
the existing ones.

**The table above is an orientation map, not a substitute for introspection.**
The exact set of registered blocks and their attributes depends on the installed
GenerateBlocks version. Always confirm names against `generateblocks-list-blocks`
and read attributes with `generateblocks-get-block-schema` before authoring —
never author a block or attribute from memory. `get-block-schema` returns the WP
block-type attribute map (each attribute's `type` and `default`); it does NOT
enumerate allowed string values, so where a value is uncertain prefer the block's
declared default (a shape known to round-trip) over guessing.

## Canonical write flow

1. `novamira/generateblocks-check-setup` — confirm GB is active; note
   `pro_active`.
2. `novamira/generateblocks-list-blocks family=… name_contains=…` — pick the
   blocks for the section (e.g. `generateblocks/element` for the wrapper,
   `generateblocks/headline` for the title, `generateblocks/button` for the
   CTA). Filter `family="pro"` to see the `generateblocks-pro/*` blocks when
   available.
3. `novamira/generateblocks-get-block-schema name="…"` — read the attribute map
   of each block you intend to author; respect `parent` / `ancestor` when
   placing it (a block placed outside its required ancestor still renders but may
   break interactive behaviour).
4. `novamira/gutenberg-get-content target_id=<post_id>` — snapshot the current
   block tree (from base Novamira).
5. Compose the `block_spec` — an array of `{name, attributes, innerBlocks}`
   matching the WP block-spec shape.
6. `novamira/gutenberg-add-pending-change target_id=<post_id> block_spec=…` —
   queue the change. **Do not** call `novamira/gutenberg-write-content` for
   GenerateBlocks markup: that ability is reserved for `novamira/*` dynamic-only
   blocks; native / static blocks (including all `generateblocks/*`) must go
   through the pending-change pipeline so Gutenberg's browser-side validator runs
   on them.
7. `novamira/gutenberg-enable-batch-finalization batch_id=…` — produces a
   finalization URL. Send it to the user; opening it in the browser runs
   Gutenberg's JS validator on the queued specs and persists the tree. The
   server-side ingestion only checks block_spec STRUCTURE (every entry has a
   `name`, `attributes` is an object if present, `innerBlocks` is an array if
   present); it does NOT cross-check against the block registry or attribute
   schemas. Wrong block names, wrong attribute types, missing parent constraints
   — all of these are surfaced only when the user finalizes in the browser.

The introspection abilities (`generateblocks-*`) are read-only with no side
effects — safe to call any time.

> **Editor-assigned attributes: `uniqueId` and `blockVersion`.**
> GenerateBlocks assigns both from its `edit()` mount effects: per-block
> CSS is keyed to `uniqueId` (the block's wrapper class ends in `-<uniqueId>`), and
> `blockVersion` gates rendering paths. Missing values pass block
> validation silently and surface only as unstyled front-end output.
> On base Novamira 1.11.4 and later the Block Editor Queue mounts
> the submitted blocks in the hidden editor before serializing, so both
> are assigned automatically — do not supply them. On base 1.11.3 and
> earlier the finalizer serializes without mounting, so supply a unique
> lowercase-hex `uniqueId` (8 chars, e.g. `"a1b2c3d4"`) on every
> `generateblocks/*` block yourself.

> **A batch can finalize cleanly yet render unstyled.** GenerateBlocks owns CSS
> generation: the front-end stylesheet is built from each block's saved `css`
> attribute, which the GenerateBlocks editor derives from the block's `styles`.
> A block that carries only structure, or whose `css` is missing or does not
> match its `styles`, can pass finalization with no error while the page
> renders with no styles. Finalization confirms the block tree persisted, NOT
> that it looks right. After finalizing, run the checks below and verify the
> actual front end renders as intended (fetch the page, or ask the user to
> look) rather than trusting the "finalized" status alone — the styling layer
> belongs to GenerateBlocks, not to the pipeline.

### CSS rules for GenerateBlocks block_specs

These apply to `generateblocks/*` and `generateblocks-pro/*` blocks alike.

- **Put the styling in `styles` and let GenerateBlocks derive `css`.**
  Server-side, GenerateBlocks never compiles `styles`: the page stylesheet
  emits each block's saved `css` string as-is, and only for a block that has a
  `uniqueId`. When the Block Editor Queue mounts the submitted blocks (the
  newer-base case in the editor-assigned attributes note above), GenerateBlocks'
  own editor effects derive `css` from `styles` at finalization, just as they
  assign `uniqueId` — supply `styles` only and do not hand-write `css`. A
  hand-written `css` that already contains the block's selector is kept as-is,
  even when it does not match `styles`.
- **When the finalizer does not mount the blocks** (the older-base case in the
  same note), nothing derives `css`: supply it yourself, compiled from
  `styles`, together with the `uniqueId` you supply. Scope it to the block's
  own class followed by `-<uniqueId>`, taking that class from the saved
  wrapper of an editor-built instance of the same block
  (`novamira/gutenberg-get-content include_raw_content=true`) — never compose
  it from the block name (`accordion-item` → `gb-accordion__item`,
  `tabs-menu` → `gb-tabs__menu`). The classic menu family saves no wrapper:
  its classes are `gb-menu-`, `gb-menu-item-` and `gb-sub-menu-` followed by
  the block's `uniqueId`, and `classic-menu-item` / `classic-sub-menu` must
  carry `mi` / `sm` plus the menu's `uniqueId` from its third character,
  because the front end builds their classes from the menu's id.
- **Check the blocks after finalization.** Read the content back with
  `novamira/gutenberg-get-content include_raw_content=true` and confirm that
  every GenerateBlocks block with non-empty `styles` has a non-empty `css`
  containing a class selector that ends in `-<uniqueId>`, and that a block
  which saves its own wrapper carries that same class on it. The classic menu
  family (`generateblocks-pro/classic-menu`, `classic-menu-item`,
  `classic-sub-menu`) saves no wrapper: check its classes in the fetched
  front-end HTML, only on elements that are actually rendered — a menu with
  no sub-menus renders no sub-menu element. A block that fails this check
  renders unstyled.
- **Do not write the page stylesheet yourself.** Saving the post marks
  GenerateBlocks' per-page stylesheet stale, and GenerateBlocks rebuilds it on
  the next front-end visit of that page. Verify by loading the page's front end
  (fetch it), not by inspecting or regenerating the stylesheet file from
  `novamira/execute-php` — that is not a front-end request for the page, so
  GenerateBlocks cannot rebuild the file there.
- **A CSS custom property that shows up as the literal `u002du002d`** instead
  of `--` in the page's CSS means the site runs an older base Novamira plugin
  whose write path strips the backslashes from GenerateBlocks' encoding of
  `--`. Update the base plugin rather than patching the output.

### save() shapes Gutenberg's validator rejects (by design)

Two specs reliably fail browser finalization with `block_validation_failed`
because GB's own `save()` emits an EMPTY tag for them — the validator is
correctly refusing a content-losing save, so fix the spec, don't fight it:

- `generateblocks/text` with a custom `className` inside `htmlAttributes` —
  GB2 does not support that; use per-block `styles` or global classes.
- `generateblocks/element` with a heading `tagName` (h2/h3/…) AND
  `innerBlocks` — not a valid GB shape; use a single `generateblocks/text`
  with the heading `tagName` and the markup in `content`.

> **Requires Novamira free 1.5.0+ for `generateblocks/*` blocks.** Earlier
> releases of the base plugin ship a V1 Gutenberg pipeline that supports core
> blocks only; submitting a `generateblocks/*` (or any third-party static) block
> to `gutenberg-add-pending-change` against pre-1.5.0 returns
> `gutenberg_unsupported_static_block`. Verify via the `NOVAMIRA_VERSION`
> constant (or confirm the `gutenberg-*` ability set is present) before promising
> a GB-powered build.

## Query loop (CMS content)

The query loop (`generateblocks/query` → `generateblocks/looper` →
`generateblocks/loop-item`) is the native GenerateBlocks way to render post
archives, CPT collections, or filtered post lists.

**Query block** (`generateblocks/query`):
- `queryType` (string, default `"WP_Query"`) — currently only `WP_Query`.
- `query` (object) — WP_Query args (`posts_per_page`, `orderby`, `tax_query`,
  `meta_query`, …); empty object `{}` for defaults.
- `inheritQuery` (boolean) — when true, use the page's global `$wp_query` instead
  of building a new one.
- `paginationType` (string, `standard` | `instant`) — instant requires GB's
  frontend script.
- `uniqueId` (string) — editor-assigned identifier (see the
  editor-assigned attributes note above).
- `tagName` (string) — wrapper element (div, section, article, …).
- Provides block-context: `generateblocks/query`, `generateblocks/queryId`,
  `generateblocks/queryType`, `generateblocks/inheritQuery`,
  `generateblocks/paginationType`.

**Looper block** (`generateblocks/looper`):
- Required ancestor: `generateblocks/query`.
- Required children: one or more `generateblocks/loop-item` blocks.
- Reads context: `generateblocks/queryData` (executed query result),
  `generateblocks/queryType`, `generateblocks/loopIndex` (1-indexed iteration).
- Renders its inner loop-item once per query result.

**Loop-item block** (`generateblocks/loop-item`):
- Required parent: `generateblocks/looper`.
- Reads context: `generateblocks/loopItem` (current post object),
  `generateblocks/queryType`, `generateblocks/loopIndex`.
- Per-item binding: the looper sets `generateblocks/loopItem` to the current post
  (a sanitized WP_Post). Child blocks read it via block-context and expose it
  through dynamic attributes (Pro) or shortcodes (free). With Pro active, blocks
  like `generateblocks/headline` and `generateblocks/text` accept dynamic-data
  bindings for title / content / taxonomy / custom fields; without Pro, render
  post properties through `[post_title]`, `[post_content]` shortcodes.

Example `block_spec`:
```
{
  name: "generateblocks/query",
  attributes: { queryType: "WP_Query", query: { posts_per_page: 6, orderby: "date", order: "DESC" }, tagName: "section" },
  innerBlocks: [
    { name: "generateblocks/looper", attributes: { tagName: "div" }, innerBlocks: [
      { name: "generateblocks/loop-item", attributes: { tagName: "article" }, innerBlocks: [
        { name: "generateblocks/headline", attributes: { /* … */ } },
        { name: "generateblocks/text", attributes: { /* … */ } }
      ] }
    ] }
  ]
}
```

## Style through the design system, never inline

GenerateBlocks blocks carry structured styling attributes (background, spacing,
typography, colors) and support **global styles / global classes** so a site's
framework and design system re-resolve everywhere when the user re-skins once.
Prefer those. **Never reach for inline `style`** (including CSS baked into
rich-text HTML) — inline styling is invisible to the design system and the
customer cannot maintain or re-theme it. Reserve inline styling for genuine
one-offs only.

When the user says their framework / design system is built in GenerateBlocks,
lean into it: read the existing block attributes with `gutenberg-get-content`,
reuse the same global classes and style tokens the user already defined, and
author new sections against those patterns instead of introducing fresh
one-off styling.

## What this skill is not for

- Theme chrome (header / footer / hooks / global typography) on a GeneratePress
  site — use `generatepress-build-page`.
- Page bodies made of core Gutenberg blocks with no GenerateBlocks — use the base
  `novamira/gutenberg-*` abilities directly; no `generateblocks-*` ability
  needed.
- Editing GenerateBlocks' own global styles / pattern library storage — the
  integration is introspection + block composition, not a settings CRUD surface.
- Plugin installation — out of scope.
