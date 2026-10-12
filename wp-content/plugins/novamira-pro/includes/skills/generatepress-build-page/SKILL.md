---
name: generatepress-build-page
description: Build or rebuild a page on a GeneratePress site — set up theme settings, optionally bootstrap from a Site Library starter template, and layer header / footer / hook / block elements via GP Premium Elements with display conditions. Activate when the user asks to create or configure a page on a GeneratePress site, customize the GeneratePress header / footer, inject markup at a theme hook, scope an element to specific pages or roles, or import a starter template from the GP Site Library.
---

# Building a page on GeneratePress

GeneratePress pages are NOT built by an internal page-builder element tree — they
are standard WordPress pages whose layout is governed by:

1. **Theme settings** (`generate_settings` option) — global typography, colors,
   spacing, container width, sidebar layout, navigation location.
2. **GP Premium Elements** (`gp_elements` CPT) — site header, site footer,
   sidebars, content templates, hooks (custom markup injected at a theme
   action), block elements (templated parts).
3. **Display conditions** on each element — where and to whom it renders.

The page content itself is written with whatever editor the site uses
(Gutenberg, GenerateBlocks, classic editor). This skill is about getting the
chrome, hooks, and global layout right — the page body is whatever the user
writes inside it.

## First call: check setup

Always start with `novamira/generatepress-check-setup`. It reports:

- `theme.active` / `theme.min_satisfied` / `theme.version`
- `gp_premium.active` / `gp_premium.modules` (map of `elements`, `site_library`,
  `font_library`)
- `generateblocks.active` / `generateblocks.version`
- `generateblocks.pro_active` / `generateblocks.pro_version`

If `theme.active` is false, stop and tell the user to activate GeneratePress.
If `gp_premium.modules.elements` is false, the element abilities will not be
loaded — fall back to theme-settings-only work and warn the user.

**If `generateblocks.active` is false, warn the user before promising a rich
page body.** GP itself is a layout shell — typography, colors, header / footer
chrome, sidebar / container layout. The visual composition layer (sections
with backgrounds, columns, buttons, grids) comes from the GenerateBlocks
companion plugin. Without it the page body is limited to core Gutenberg
blocks; propose installing GenerateBlocks free from wp.org before continuing,
or constrain the work to settings + Hook / Block elements with code-only
content.

## Decision tree

```
1. Settings + chrome
   ├── Site Library template fits? → bootstrap from template ("Starter from Site Library")
   └── No                          → start from defaults ("Start from defaults")

2. Visual content (page body, hero, sections)
   ├── GenerateBlocks active? → compose with GB blocks in page body / Block Elements
   └── GB not active          → propose installing GB free, or constrain to
                                core Gutenberg blocks (limited result)

3. Header / footer / page-hero modifier → Block Elements (see "Block elements")

4. Snippets (analytics, schema, cookie banner, footer credit) → Hook Elements
   (see "Hook elements") — NOT used for visual layout
```

## Starter from Site Library

When the brief matches a known industry / layout vertical, the Site Library
saves an entire round of decisions. Required: `gp_premium.modules.site_library`
is true.

1. `novamira/generatepress-list-sites` — browse the catalogue. If the cached
   list is empty, pass `refresh=true` (forces a remote fetch from gpsites.co).
   Filter with `category` or `page_builder` when the user named a vertical.
2. `novamira/generatepress-get-site name="…"` — read the `assets` map. The
   booleans (`has_options`, `has_content`, `has_widgets`) tell you which
   apply-site steps will produce side effects. The `plugins` map tells you
   what `steps=["plugins"]` would attempt.
3. Decide which steps to apply:
   - **Settings-only preview**: `steps=["theme_options"]`. Lets the user see
     the template's typography / colors / spacing applied to their existing
     content. Cheapest, most reversible.
   - **Full bootstrap**: omit `steps` for the default
     `["theme_options", "content", "site_options", "widgets"]`. Imports the
     demo content as posts/pages/media. Does NOT install plugins.
   - **With plugin installs**: add `"plugins"` to the steps list only when
     the user explicitly accepts plugin downloads from wp.org.
4. `novamira/generatepress-apply-site name="…" confirm=true steps=[…]` —
   apply-site is destructive (overwrites `generate_settings`, imports posts,
   sets element locations). `confirm=true` is mandatory: omitting the
   field entirely yields `ability_invalid_input` (schema-level reject),
   passing `confirm=false` yields `gp_confirmation_required`. Always
   surface the destructive nature to the user before the call.

5. **Rolling back** — `novamira/generatepress-restore-site target=…
   confirm=true` undoes the last apply by replaying the
   `_generatepress_site_library_backup` GP wrote at apply-time.
   `target=theme_options` reverts Customizer + theme_mods + module
   activation and PRESERVES the backup (safe to re-run);
   `target=content` deletes the imported posts/terms AND consumes the
   backup; `target=all` (default) runs both, leaving no backup. Returns
   `gp_no_backup` when there's nothing stored.

If apply-site reports `steps_failed`, the partial state is already on the
site — investigate before retrying.

## Start from defaults

When no template fits or the user wants full control:

1. Inventory the current values with `novamira/generatepress-get-settings`
   (no `keys` filter returns everything currently in the DB) and
   `novamira/generatepress-get-defaults section="all"` for the canonical
   defaults.
2. Plan the changes against the defaults — only write keys you actually
   need to override. `set-settings` is a merge, not a replace, but
   minimising the patch keeps the DB clean.
3. `novamira/generatepress-set-settings settings={…}` — accepts both
   GP-known keys and unknown keys; unknown keys are stored anyway but
   surfaced in the response `warnings` list. Use the warning to spot
   typos.
4. Use `novamira/generatepress-get-layout` (optionally with `post_id`)
   to confirm the resolved sidebar / nav / footer layout for the page
   you are building.

## GenerateBlocks: the visual composition layer

GP itself does not ship section / column / button primitives. The companion
plugin **GenerateBlocks** (free, from wp.org) provides them as native Gutenberg
blocks — the canonical way to build visual content on a GP site. When
`check-setup.generateblocks.active=true`, prefer GB blocks over raw HTML +
inline CSS for every visual section: hero, feature grids, CTA strips, content
columns.

Block names registered by GenerateBlocks 2.x (free):

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

`generateblocks.pro_active=true` adds these Pro block families on top (all under
the `generateblocks-pro/` namespace — distinct namespace from the free blocks
above):

| Family | Blocks | Use for |
|---|---|---|
| Accordion | `accordion`, `accordion-item`, `accordion-toggle`, `accordion-toggle-icon`, `accordion-content` | Collapsible FAQ-style content |
| Tabs | `tabs`, `tabs-menu`, `tab-menu-item`, `tab-items`, `tab-item` | Tabbed sections |
| Carousel | `carousel`, `carousel-items`, `carousel-item`, `carousel-pagination`, `carousel-control` | Sliders, testimonial reels |
| Navigation | `navigation`, `menu-toggle`, `menu-container`, `classic-menu`, `classic-menu-item`, `classic-sub-menu` | Full-featured nav + mobile menu toggle |
| Site Header | `site-header` | Composite site-header block (use inside a `block_type=site-header` Block Element) |

Pro also adds dynamic-data binding on the free blocks (Headline / Text / Button
read from post fields, meta, taxonomy) — no new block, just additional
attributes wired into the existing ones.

The native authoring surface for these blocks is the Gutenberg editor. When
authoring through abilities, never hand-write the block-serialised markup
(`<!-- wp:generateblocks/… -->`): it does not match what the blocks' own
`save()` produces, so the editor opens it as invalid blocks, and it lacks the
`uniqueId` / `css` GenerateBlocks derives in the editor. Compose a
`block_spec` and submit it through the base Block Editor Queue
(`novamira/gutenberg-add-pending-change` →
`novamira/gutenberg-enable-batch-finalization`), which serializes each block
with its own editor code — for a page body (see "Writing GenerateBlocks
content into a post" below) and for a Block Element body alike (see "Block
elements"). The `generateblocks-build-page` skill covers the CSS rules.

### Query loop details

The **query loop** pattern (`generateblocks/query` → `generateblocks/looper` → `generateblocks/loop-item`) is the native GenerateBlocks way to render post archives, custom post type collections, or filtered post lists.

**Query block** (`generateblocks/query`):
- Attributes:
  - `queryType` (string, default `"WP_Query"`) — currently only `WP_Query` supported; use this block for standard WordPress queries
  - `query` (object) — WP_Query args (posts_per_page, orderby, tax_query, meta_query, etc.); empty object `{}` for defaults
  - `inheritQuery` (boolean) — when true, use the current page's global `$wp_query` instead of building a new one
  - `paginationType` (string, enum `standard` | `instant`) — pagination style; instant requires GB's frontend script
  - `uniqueId` (string) — editor-assigned unique identifier for the query block. On base Novamira 1.11.4 and later the Block Editor Queue assigns it automatically at finalization; on 1.11.3 and earlier supply a unique lowercase-hex value yourself (GenerateBlocks keys its per-block CSS to it — without it the block renders unstyled, with no validation error)
  - `tagName` (string enum) — wrapper HTML element (div, section, article, etc.)
  - `styles`, `globalClasses`, `htmlAttributes` — styling (`css` is derived from `styles`; see the CSS rules in `generateblocks-build-page`)

- Provides block-context:
  - `generateblocks/query` — the query object
  - `generateblocks/queryId` — the block's uniqueId
  - `generateblocks/queryType` — copy of the `queryType` attribute
  - `generateblocks/inheritQuery` — copy of the `inheritQuery` attribute
  - `generateblocks/paginationType` — copy of the `paginationType` attribute

**Looper block** (`generateblocks/looper`):
- Required ancestor: `generateblocks/query` (the query block must wrap it)
- Required children: one or more `generateblocks/loop-item` blocks
- Reads context: `generateblocks/queryData` (the executed query result), `generateblocks/queryType`, `generateblocks/loopIndex` (current loop iteration, 1-indexed)
- Renders the inner loop-item blocks once per result in the query

**Loop-item block** (`generateblocks/loop-item`):
- Required parent: `generateblocks/looper`
- Reads context: `generateblocks/loopItem` (current post object), `generateblocks/queryType`, `generateblocks/loopIndex`
- **Per-item data binding mechanism**: The looper sets `generateblocks/loopItem` to the current post (a sanitized WP_Post object with disallowed keys removed for security). Child blocks inside loop-item read this via block-context and expose it through dynamic attributes (Pro only) or shortcodes (free).
- When GenerateBlocks Pro is active, blocks like `generateblocks/headline` and `generateblocks/text` accept dynamic-data bindings that read post title, content, taxonomy, custom fields from the looped post. When Pro is inactive, render post properties through `[post_title]`, `[post_content]` shortcodes or inline JS.

Example structure in block_spec form:
```
{
  name: "generateblocks/query",
  attributes: {
    queryType: "WP_Query",
    query: { posts_per_page: 6, orderby: "date", order: "DESC" },
    tagName: "section"
  },
  innerBlocks: [
    {
      name: "generateblocks/looper",
      attributes: { tagName: "div" },
      innerBlocks: [
        {
          name: "generateblocks/loop-item",
          attributes: { tagName: "article" },
          innerBlocks: [
            { name: "generateblocks/headline", attributes: { ... } },
            { name: "generateblocks/text", attributes: { ... } }
          ]
        }
      ]
    }
  ]
}
```

### When to use raw HTML / inline `<style>` instead

Hook Elements accept raw HTML strings in `content`, and an agent CAN inject a
`<style>` block plus hand-written markup. **Do not.** That bypasses every
control a human editor would use, makes the design invisible to the
Customizer / Site Editor, and produces output the customer cannot maintain.

Raw HTML + inline CSS is only appropriate for **snippets** Hook Elements
are designed to host — analytics tags, schema.org JSON-LD, cookie banner,
sponsorship strip, sticky CTA. Anything that's a *visual section* belongs
in a Block Element or in the page body, composed of GenerateBlocks (or core
Gutenberg blocks when GB is absent).

### Writing GenerateBlocks content into a post

The actual read / write of block trees on a post lives in the
`novamira/gutenberg-*` abilities exposed by the **base Novamira plugin**
(free). Pro contributes only the *introspection* layer: the catalogue and
the per-block attribute schema (`generateblocks-list-blocks`,
`generateblocks-get-block-schema`).

> **Requires Novamira free 1.5.0+ for `generateblocks/*` blocks.** Earlier
> releases of the base plugin ship a V1 Gutenberg pipeline that supports
> core blocks only; submitting a `generateblocks/*` (or any third-party
> static) block to `gutenberg-add-pending-change` against pre-1.5.0
> returns `gutenberg_unsupported_static_block`. Verify the version via
> the `NOVAMIRA_VERSION` constant (or by calling `wp_get_abilities()`
> and confirming the gutenberg-* set is present) before promising a
> GB-powered build.

Canonical write flow when composing a page body with GenerateBlocks:

1. **`novamira/generateblocks-check-setup`** — confirm GB is active and
   note `pro_active`.
2. **`novamira/generateblocks-list-blocks`** — pick the blocks for the
   section (e.g. `generateblocks/element` for the wrapper,
   `generateblocks/headline` for the title, `generateblocks/button` for the
   CTA). Filter by `family="pro"` to see the additional
   `generateblocks-pro/*` blocks when available.
3. **`novamira/generateblocks-get-block-schema name="…"`** — read the
   attribute map of each block before authoring; pass the values you want
   under the same keys in the block_spec.
4. **`novamira/gutenberg-get-content target_id=<page_id>`** — snapshot
   the current block tree (from base Novamira).
5. Compose the block_spec — an array of `{name, attributes, innerBlocks}`
   matching the WP block-spec shape.
6. **`novamira/gutenberg-add-pending-change target_id=<page_id>
   block_spec=…`** — queue the change. **Do not** call
   `novamira/gutenberg-write-content` for GenerateBlocks markup: that
   ability is reserved for `novamira/*` dynamic-only blocks; native /
   static blocks (incl. all `generateblocks/*`) must go through the
   pending-change pipeline so Gutenberg's browser-side validator runs on
   them.
7. **`novamira/gutenberg-enable-batch-finalization`** — produces a
   finalization URL. Send it to the user; they open it in the browser,
   Gutenberg validates the queued specs, and the post is updated.
8. Run the post-finalization CSS checks from the `generateblocks-build-page`
   skill, then fetch the page's front end to confirm it renders styled.

The introspection abilities exposed by this integration (`generateblocks-*`)
are read-only and have no side effects — they're safe to call any time.

## Hook elements

A **hook element** injects markup at a theme action (e.g. `generate_after_header`,
`generate_before_footer`, `wp_body_open`). Requires `gp_premium.modules.elements`.

**Use Hook Elements for code snippets, not for visual layout.** Good fits:

- Analytics / pixel tags at `wp_head` or `wp_body_open`
- Schema.org JSON-LD on `wp_head`
- Cookie banners / consent prompts at `wp_body_open`
- Top-of-page advisory bars (one line, semantic — not a hero)
- Sticky CTA / sponsorship strip at `generate_after_header`
- Footer credit / signature at `generate_after_footer_content`

For a hero banner above the body, a feature grid, a pricing section, or any
other visual block: use a Block Element or the page body itself with
GenerateBlocks — not a Hook Element. See "Block elements" and the page-hero
worked example below.

Canonical sequence:

1. `novamira/generatepress-list-hooks` — returns `{groups: [...], source}`.
   Each group has `{group, label, hooks: [{name, label}, ...]}`. The
   `name` field of each entry IS the action name to pass to
   `create-element.hook` (e.g. `generate_after_header`). Filter via
   `group="…"` (header / nav / content / footer / wc / etc.) when you
   already know the surface.
2. `novamira/generatepress-create-element` with:
   - `type="hook"`
   - `hook="<hook-name>"` (or `hook="custom"` plus `custom_hook="<action>"`
     when the action you need isn't in list-hooks)
   - `content="<raw HTML / shortcode / serialised Gutenberg blocks>"` — the
     integration stores it verbatim
   - `priority=10` (default — lower runs earlier)
   - `execute_shortcodes=true` when the content includes `[shortcode]` calls
3. `novamira/generatepress-set-element-conditions` to scope visibility
   (see "Conditions").

Special hooks: `generate_header` and `generate_footer` accept an element
that replaces the theme's site header / footer entirely — pair the hook
element with `disable_site_header=true` / `disable_site_footer=true`.

> **Never** ask to enable the element's PHP execution flag
> (`_generate_hook_execute_php`). The integration does not accept it as
> input and does not return it in any read. The element content is a
> string only — HTML, shortcodes, and Gutenberg block markup are fine;
> raw PHP is not exposed.

## Block elements

A **block element** is a templated site part — site header, site footer,
sidebars, content / loop / page-hero templates, post navigation, search
modal. Used to replace the default theme part on the pages the conditions
match.

Canonical sequence:

1. `novamira/generatepress-create-element` with:
   - `type="block"`
   - `block_type="<one of>"` — site-header | site-footer | right-sidebar |
     left-sidebar | content-template | loop-template | search-modal |
     page-hero | post-meta-template | post-navigation-template |
     archive-navigation-template | hook
   - `title="…"` (visible in the admin list — keep it descriptive, the
     same site usually has multiple block elements per type scoped to
     different conditions)

> **`page-hero` caveat**: this block_type is a *modifier* — when its
> display_conditions match a page, it disables that page's default title,
> featured image, and primary post meta so the agent can compose a custom
> hero above the body. The Block Element's own `content` field is NOT
> printed by GP Premium's renderer; the actual hero markup lives in the
> target page's body (composed of GenerateBlocks blocks or core blocks),
> not in this Element. See the "Page-hero workflow" worked example below.
2. Author the body through the Block Editor Queue, not through `content`.
   A Block Element renders its `post_content` and is edited in the block
   editor, so pass the element's id as `target_id` to
   `novamira/gutenberg-add-pending-change` with a `block_spec` of
   GenerateBlocks blocks (core Gutenberg blocks — group / columns / image /
   heading / paragraph — without GB), then
   `novamira/gutenberg-enable-batch-finalization`, exactly as for a page
   body. The `content` field of `create-element` / `edit-element` stores
   its string verbatim with no block serialization, so keep it for
   shortcode-driven content (set `execute_shortcodes=true`), not for
   visual sections. Hook Elements are not a queue target: their content
   lives in postmeta, outside the block editor.
3. `novamira/generatepress-set-element-conditions` — block elements
   without conditions render everywhere of their block_type, which is
   usually the wrong default.

### Page-hero workflow (worked example)

Goal: a landing page with a custom hero above the body content, without the
default WP title / featured image.

1. **Create the target page** via `novamira/create-post` (`post_type=page`).
   The page body will hold the hero + the rest of the content; leave it
   empty here and compose it in step 4.
2. **Create a `page-hero` Block Element**:
   ```
   create-element {
     title: "Disable default hero on landing pages",
     type: "block",
     block_type: "page-hero",
   }
   ```
   This Element's `content` doesn't render — only the modifier behaviour
   matters.
3. **Scope it** with `set-element-conditions` to the specific landing page
   id (`display_conditions=[{rule:"post:post:<page_id>"}]`) or to a broader
   set if multiple pages share the same hero pattern.
4. **Write the actual hero** into the body of the page you created in
   step 1 through the Block Editor Queue (`gutenberg-add-pending-change
   target_id=<page_id>` → `gutenberg-enable-batch-finalization`), as in
   "Writing GenerateBlocks content into a post". With GenerateBlocks
   active, the hero is a `generateblocks/element` (section) wrapping a
   `generateblocks/headline`
   + `generateblocks/text` + `generateblocks/button`. Configure the section's
   background colors / gradient / image through the block attributes, not
   inline CSS.

Counter-pattern to avoid: building the hero as a Hook Element on
`generate_after_header` with a `<style>` block + hand-written HTML. The
output renders, but the customer cannot edit it visually and the design is
invisible to every other tool that reads block markup.

## Conditions

GP Premium evaluates conditions in two buckets per element:

- **display_conditions** — when to render. OR semantics across rows.
- **exclude_conditions** — when NOT to render even if display matched.
- **user_conditions** — who sees it. AND semantics with display/exclude.

Each row is `{rule: "<group>:<key>", object?: "<id-or-empty>"}`. The
catalogue varies per site (active post types / plugins shape it), so:

1. `novamira/generatepress-list-element-conditions` — read the live
   catalogue. Use `bucket="display_exclude"` or `bucket="user"` to
   trim. Each top-level entry is a group `{group, label, locations}`;
   `locations` is a `{rule => human label}` map — the rule strings on
   the LEFT are the values to pass to `set-element-conditions`.
2. `novamira/generatepress-set-element-conditions id=<element-id>
   display_conditions=[…] exclude_conditions=[…] user_conditions=[…]`
   — pass only the buckets you want to change; omitted buckets stay
   as-is, an empty array clears a bucket.

Bad rule strings are stored as-is and silently never match — validate
against the catalogue before writing.

## Cleanup

`novamira/generatepress-delete-element` defaults to the trash (recoverable
from `/wp-admin/edit.php?post_type=gp_elements&post_status=trash`). Use
`force=true` only when the user has explicitly confirmed a permanent
delete.

## What this skill is not for

- Page body composition itself — compose it through the base Block Editor
  Queue (`novamira/gutenberg-*`), following the `generateblocks-build-page`
  skill when GenerateBlocks is active.
- Plugin or theme installation outside the Site Library `plugins` step.
- The Customizer schema itself — `get-defaults` returns the default
  values, not the Customizer control tree.
