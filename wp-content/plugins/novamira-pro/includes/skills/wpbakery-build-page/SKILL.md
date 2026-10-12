---
name: wpbakery-build-page
description: Build a WPBakery page from scratch end-to-end — create the post, mark it as WPBakery-edited, populate its shortcode tree (rows → columns → elements), and reuse saved templates. Activate when the user asks to create a new WPBakery-rendered page, recreate a layout from another source in WPBakery, or save/apply reusable WPBakery sections.
---

# Building a WPBakery page from scratch

This skill is for creating a new WPBakery page end-to-end.

If the page already exists, do not default to this workflow:

- Use `novamira/wpbakery-edit-element` for targeted settings changes on a single element.
- Use `novamira/wpbakery-add-element` to insert a new section/row/column/element into an existing tree.
- Use `novamira/wpbakery-set-content` only when replacing the full shortcode tree.

This skill covers the canonical sequence for creating a new WPBakery-rendered
page and reusing saved layouts via templates. For the element catalogue see
`novamira/wpbakery-list-elements`; for the control schema of a single element
see `novamira/wpbakery-get-element-schema`; for an existing page's tree
snapshot see `novamira/wpbakery-get-content`. This skill is workflow
knowledge, not a schema dump.

## Canonical sequence

1. **Check WPBakery setup.** Call `novamira/wpbakery-check-setup` and inspect
   the result:
   - `min_satisfied: true` — WPBakery is at or above the minimum supported
     version. Stop and tell the user when this is `false`.
   - `elements_count > 0` and empty `issues` — the plugin is fully initialised.
   - `grid_builder_active: true` — `vc_basic_grid` / `vc_masonry_grid` loop
     widgets are available. When `false`, CMS loops cannot be wired through
     WPBakery on this site.
   - `supported_post_types` — the post types where WPBakery can edit.
     Match the target `post_type` you intend to create against this list;
     creating a WPBakery page on a type outside it saves the shortcodes
     but the "Edit with WPBakery" link does not appear in admin and
     rendering is theme-dependent.
   - `current_user_can_manage` — the current agent context has admin
     permission. Warn the user when this is `false`.
   - `categories` — element categories available on the site, including
     any added by the active theme or third-party plugins. WPBakery hands
     these back **translated into the site locale** ("Struttura", not
     "Structure"), so pass one of these names verbatim to the `category`
     filter on `wpbakery-list-elements` instead of guessing the English
     one. The match ignores case.
2. **Create the post.** Use `novamira/create-post` with `post_type=page` (or
   another post type WPBakery supports on this site). Capture the returned
   `post_id`.
3. **(Usually optional) Mark the post as WPBakery-edited.**
   `novamira/wpbakery-set-content` already sets `_wpb_vc_js_status=true`
   on the post — so step 4 alone is enough to make the admin lists show
   "Edit with WPBakery" and to keep the classic editor from stripping
   the shortcodes. To set page-level `custom_css` on creation, pass it
   inline to `set-content` (atomic; one round-trip). Reserve
   `novamira/wpbakery-set-settings` for flipping the WPBakery flag OFF
   on an existing page.
4. **Populate the shortcode tree.** Call `novamira/wpbakery-set-content` with
   the `post_id` and the `tree` array (array of root nodes). Each node has
   shape `{tag, atts, children, _content}`:

   ```jsonc
   {
     "tag": "vc_row",
     "atts": {},
     "children": [
       {
         "tag": "vc_column",
         "atts": {"width": "1/1"},
         "children": [
           {"tag": "vc_btn", "atts": {"title": "Click"}, "children": []},
           {"tag": "vc_column_text", "atts": {}, "children": [], "_content": "<p>Hi</p>"}
         ]
       }
     ]
   }
   ```

   The top-level input param is `tree` (the array of root nodes). Inside
   each node the recursive nesting key is **`children`**. Putting nested
   nodes under a `content` key on a node is silently dropped by the
   parser; the response surfaces this via a `warnings` field when it
   can detect the mistake. `_content` is reserved for raw inner HTML
   on elements like `vc_column_text` (textarea_html params).
5. **(Optional) Reuse saved layouts.** If a section template already exists,
   skip building from scratch and call
   `novamira/wpbakery-list-templates` → `novamira/wpbakery-apply-template`
   with `mode=append|prepend|replace`.

Steps 4 and 5 are not exclusive: build the page skeleton with `set-content`,
then `apply-template` saved sections on top with `mode=append`.

## Tool choice

Use the smallest tool that matches the edit:

| Intent | Tool |
| --- | --- |
| Create a new WPBakery page from scratch | `create-post` + `wpbakery-set-content` (the flag is set automatically) |
| Insert a new section into an existing page | `wpbakery-add-element` (with `parent_idx` / `position`; the new node goes inside `element: {tag, atts, children?, _content?}` — same shape as a set-content node, so a subtree is passed in one call, e.g. `element: {tag:"vc_row", atts:{}, children:[{tag:"vc_column", atts:{width:"1/1"}, children:[{tag:"vc_btn", atts:{title:"X"}, children:[]}]}]}`) |
| Modify a single element's attributes | `wpbakery-edit-element` (merges by default; pass `replace: true` to wipe untouched atts) |
| Apply identical atts to many elements in one call | `wpbakery-edit-elements` (match by `tag` and/or `element_idxs`; atts are NOT schema-validated per match) |
| Remove a single element | `wpbakery-delete-element` |
| Move a single element to a different parent or position | `wpbakery-move-element` (resolves source + target in one tree snapshot, so a single move call is safe without a mid-call re-fetch — but between separate move calls `_idx` still renumbers, re-fetch first) |
| Replace the entire tree | `wpbakery-set-content` |
| Read the WPBakery-edited flag and page-level CSS on a post | `wpbakery-get-settings` |
| Inspect everything about a page in one read call (setup + settings + tree + foreign shortcodes) | `wpbakery-inspect-page` |
| Set page-level `custom_css` while writing the tree (atomic) | `wpbakery-set-content` with the `custom_css` arg |
| Flip the WPBakery-edited flag on an existing post (typically OFF) | `wpbakery-set-settings` |

> CSS knob distinction: `custom_css` is the free-form page-level CSS textarea (stored in postmeta `_wpb_post_custom_css`). `shortcodes_custom_css` is auto-generated by WPBakery from every element's `css=".vc_custom_XXX{…}"` Design Options att and rebuilt on save (postmeta `_wpbakery_shortcodes_custom_css`). Agents normally write only `custom_css`; leave `shortcodes_custom_css` alone unless you have a specific reason to overwrite it.
| Save a section as a reusable template | `wpbakery-create-template` (`post_id` here is the SOURCE page you are reading the section from; combine with `element_idx`) |
| Insert a saved template into a page | `wpbakery-apply-template` (`post_id` here is the TARGET page; pass `template_id` from create-template) |
| List saved templates (filtered by category) | `wpbakery-list-templates` |
| Permanently remove a template | `wpbakery-delete-template` (pass `template_id`) |

## Static vs. query loop on repeated content

WordPress sites are CMS-driven, so **repeated content on a page is a query
loop, not N hand-typed shortcodes.** Default to a loop whenever a section
shows more than one of "the same thing" — blog posts, projects, products,
services, team members, events, testimonials, rentals, locations, listings,
anything the customer will add or edit over time. Static markup on the page
traps the data inside the shortcode tree and breaks every other surface that
should reference it (search, archives, single-post pages, related strips).

Static is the exception, reserved for **fixed UI sections with bespoke copy**
authored for this page:

- Pricing tiers, feature pillars, USP grid
- "How it works" steps
- Hero / sub-hero
- Brand or partner logo strip
- Hand-picked testimonials tied to this page
- Small fixed FAQ inline with the marketing copy

Three-question sanity check when unsure: would each card have a dedicated
detail page? Would these entities appear elsewhere on the site? Will the
customer add, remove, or edit them over time? "Yes" to any → loop. The number
of items at build time is irrelevant.

### Discover the target post type before building

Before wiring the loop (and before proposing to create a new CPT), list
what's already registered on the site and match against the user's intent
— they may already have set up the type, especially on a mature site.

Call the post-types list ability of whichever field-plugin specialization
is active in this session (e.g. `novamira/pods-list-pods`,
`novamira/acf-list-post-types`, and the equivalent in any other
specialization the abilities list shows). Match the user's wording to the
returned slug or label; account for plural/singular and reasonable
variation. Use any reasonable match instead of proposing a new CPT; only
fall through to "No matching CPT yet" when nothing in the listing matches.

There is no vanilla `novamira/wordpress-list-post-types` ability yet, so a
CPT registered by anything other than a Novamira-supported field-plugin
specialization (a theme, JetEngine's own UI, raw `register_post_type()` in
custom code, etc.) is invisible to the specialization-aware discovery path.
When you suspect the type exists but no specialization owns it, fall back to
a read-only `novamira/execute-php` call — `return get_post_types(['_builtin'
=> false, 'public' => true], 'objects');` returns every non-built-in public
post type registered on the site, regardless of how it was declared.

### Loop widget choice

Pick the widget based on what's registered on the site (discoverable via
`novamira/wpbakery-list-elements`). The right question is "which query
element is available here", not "is Grid Builder on".

| Available query element | When to pick it |
|---|---|
| `vc_basic_grid` (WPBakery core) | Default. Uses a Grid Item template referenced via `item="<template>"`. Stock item templates ship with WPBakery in three families: `basicGrid_*` (e.g. `basicGrid_NoAnimation`, `basicGrid_GoTopSlideout`, `basicGrid_TextFirst`), `mediaGrid_*` (e.g. `mediaGrid_Default`, `mediaGrid_SimpleOverlay`, `mediaGrid_FadeInWithIcon`), `masonryGrid_*` (e.g. `masonryGrid_Default`, `masonryGrid_FadeIn`). No template creation needed if one fits the design. Also accepts the post id of a custom `vc_grid_item` post (see below). |
| `vc_masonry_grid` (WPBakery core) | Like `vc_basic_grid` but masonry layout — when card heights vary and the design wants them packed without vertical gaps. Same `item` parameter. |
| Theme or third-party loop element | Some themes (Avada, Salient, Bridge, …) and WPBakery add-ons register their own loop shortcodes. Find them with `novamira/wpbakery-list-elements`; their query controls live in the schema returned by `novamira/wpbakery-get-element-schema`. |
| None of the above registered | The section can't loop. Tell the user the page needs Grid Builder or a third-party query-capable plugin; don't fake it with N static `vc_column` cards. |

There is no separate `vc_grid` shortcode on modern WPBakery (8.x). The
"Grid Builder" experience lives entirely through `vc_basic_grid` /
`vc_masonry_grid` with their `item` parameter — either a stock template
slug or the post id of a custom `vc_grid_item` (see below).

### Custom Grid Item template flow

When no stock `item="..."` template fits the design, build a custom one:

1. Create the template post: `novamira/create-post` with
   `post_type="vc_grid_item"`. WPBakery registers this CPT when Grid Builder
   loads.
2. Build the per-item layout inside the template's `post_content` via
   `novamira/wpbakery-set-content`. Grid items use a dedicated set of
   `vc_gitem_*` shortcodes that are **not** in the global `WPBMap`
   registry — `wpbakery-list-elements` and `wpbakery-get-element-schema`
   do not return them. Call **`novamira/wpbakery-list-grid-item-elements`**
   to discover the 18-element catalogue (vc_gitem_row, vc_gitem_col,
   vc_gitem_post_title, vc_gitem_post_meta key="<meta_key>", vc_gitem_image,
   …) with the most-used atts inline. Wrap content in `vc_gitem_row >
   vc_gitem_col`; that's the required scaffold.

3. Reference the template by post id from the loop element on the target
   page: `vc_basic_grid item="<grid_item_post_id>"` plus the query attributes
   (`post_type`, `max_items`, `meta_key`, `orderby`, `order`). Verified end
   to end: `vc_gitem_post_meta key="<meta_key>"` resolves to the actual meta
   value during iteration.

`novamira/wpbakery-get-content` recognises the `vc_gitem_*` set even though
those tags are not in `WPBMap`, so reading and re-writing a `vc_grid_item`
template round-trips correctly.

### No matching CPT yet

The CPT may not exist when you start. Discovery first: list installed plugins
(each field-plugin specialization's `<plugin>-check-setup` ability tells you
whether that plugin is active — call the ones you have).

- **A field-plugin specialization is active** (Pods / ACF Pro / JetEngine /
  Meta Box / ACPT / ASE Pro): propose creating the CPT through that
  plugin's specialization, then wire the loop on top. An empty CPT plus a
  wired Grid Item template is the right state to ship.
- **No field-plugin specialization is active**: there is no CPT-management
  UI on this site. Stop and tell the user — vanilla WordPress does not
  expose CPT registration to non-developers, and the section cannot loop
  against real content until one of those plugins is installed. Do not
  register the CPT via raw `register_post_type()` calls in agent-generated
  code; the customer can't manage that afterwards.

**Do not fall back to a static grid because the CPT is missing.**

## Styling — prefer element params and `el_class`, not hand-written `css`

WPBakery styles content three ways, in order of preference:

1. **The element's own style params.** Most elements ship presentation params — e.g. `vc_btn` has `style`, `color`, `shape`, `size`; `vc_separator` has `style`, `color`. Read them with `wpbakery-get-element-schema { tag }` (`controls`) and set them directly. This is the idiomatic path and renders the builder's real classes (`vc_btn3-style-modern vc_btn3-color-blue`, …).
2. **Custom CSS via `el_class` + a stylesheet.** For styling the schema doesn't cover, put a class name in the element's `el_class` common-control and define the rule in a real stylesheet (a `code-snippets` CSS snippet, the theme/customizer, or a `vc_raw_html` `<style>`). `el_class` and `el_id` are common controls on every element. Do **not** scatter inline `style=""`.
3. **The `css` Design-Options param (last resort).** It stores an encoded `.vc_custom_<hash>{prop:value !important;…}` blob that WPBakery normally auto-generates from the admin Design Options UI; hand-authoring the hash/format is brittle — reach for `el_class` instead unless you are round-tripping a value WPBakery already produced.

## Container basics — the row/column constraint

Every content element in WPBakery must live inside a `vc_column`, and every
`vc_column` must live inside a `vc_row`. Putting a `vc_btn` directly at the
top level produces a tree the backend will save but the frontend will not
render correctly — visual styles, animations and responsive controls assume
a row/column ancestor.

Minimum valid structure:

```
vc_row
 └─ vc_column (width="1/1")
     └─ <content elements>
```

For multi-column layouts, distribute columns inside a single row using the
fractional `width` notation (`1/2`, `1/3`, `2/3`, `1/4`, `3/4`, `1/6`, `5/6`):

```
vc_row
 ├─ vc_column (width="1/2") → <left content>
 └─ vc_column (width="1/2") → <right content>
```

Inner rows are supported: nest a `vc_row_inner` containing `vc_column_inner`
inside an outer `vc_column` to subdivide it. Do not nest `vc_row` directly
inside another `vc_row`.

### Composite content elements (tabs, tour, accordion)

WPBakery ships a `vc_tta_*` family for multi-pane content. They all share the same
parent/child contract: a `vc_tta_tabs` / `vc_tta_tour` / `vc_tta_accordion` wrapper
holds one or more `vc_tta_section` children, and each section's `title` att is the
visible label. The section's body lives in `children` (NOT `_content` — sections
are containers, they hold shortcode children).

```
vc_tta_tabs
 ├─ vc_tta_section (atts: { title: "Overview" })
 │   └─ vc_column_text (_content: "<p>…body…</p>")
 ├─ vc_tta_section (atts: { title: "Features" })
 │   └─ <any vc_* children>
 └─ vc_tta_section (atts: { title: "Pricing" })
     └─ vc_btn (atts: { title: "Buy now" })
```

`vc_tta_tour` and `vc_tta_accordion` reuse the exact same `vc_tta_section`
shape, so the pattern above transfers verbatim.

## Param-group and encoded fields — the main gotcha

Several WPBakery controls (`vc_btn` *design options*, `vc_row` *design
options*, icon picker, image gallery, custom CSS) save a sub-array as a
single URL-encoded string attribute. Two consequences:

- `wpbakery-get-content` returns the **decoded** form (a PHP array). Pass
  the same decoded form back to `wpbakery-edit-element` /
  `wpbakery-set-content` — the abilities re-encode on save. Don't hand-
  encode unless you have a specific reason.
- For image fields, use **integer attachment IDs**, not URLs. WPBakery
  resolves the URL at render time from the ID.
- The **link** att (`vc_btn.link`, `vc_single_image.link`, `vc_cta.*_link`)
  is an object: `{url, title, target, rel}`. `target` is `_blank` or
  `_self`; `rel` is a space-separated list like `noopener nofollow`.
  One exception, and it bites: **inside a Grid Item template the same att
  is a magic string**, not an object — `post_link`, `image`,
  `image_lightbox`, `custom`, `none`. Read `wpbakery-list-grid-item-elements`
  before authoring one. The abilities pass a non-object value straight
  through, so a tree read from a grid item can be written back unchanged.

When in doubt, fetch the current attribute shape with
`wpbakery-get-content` on a known-good page first, mirror that shape in
your edit.

## A container can never sit inside another of the same kind

WordPress's shortcode parser cannot handle a shortcode nested inside
itself. `[x]a[x]b[/x]c[/x]` matches the **first** closing tag, so the outer
one is left over and printed to the visitor as literal `[/x]` text, and
everything after it stops rendering. This is a WordPress limitation, not a
WPBakery one, and no att changes it.

WPBakery designed around it — that is why `vc_row_inner` and
`vc_column_inner` exist. The trap is `vc_tta_section`, because **tabs,
tours, accordions and toggles all take sections**. So this, which is the
natural way to express "an accordion inside a tab", cannot render:

```
vc_tta_tabs
 └─ vc_tta_section          ← outer
     └─ vc_tta_accordion
         └─ vc_tta_section  ← same tag inside itself: broken
```

Put the inner container in its own row instead:

```
vc_row → vc_column → vc_tta_tabs → vc_tta_section → …
vc_row → vc_column → vc_tta_accordion → vc_tta_section → …
```

`wpbakery-set-content`, `wpbakery-add-element` and `wpbakery-move-element`
refuse a tree with this shape and return `code:
"wpbakery_self_nested_tag"` plus a `self_nested[]` list naming the exact
path — nothing is written, so the post is left as it was. For content that
already carries the shape (imported, or written before this check),
`wpbakery-inspect-page` raises the `self_nested_tag` diagnostic.

## Params with no control in the editor panel

`wpbakery-get-element-schema` marks some controls `"hidden": true`. That
means WPBakery shows no field for them in its edit panel, **not** that they
are unusable: they are ordinary atts, they are accepted on write, and some
of them are the most important att the element has.

The ones worth knowing:

| Att | Element | What it does |
|---|---|---|
| `width` | `vc_column`, `vc_column_inner` | the fractional column width (`1/2`, `1/3`, …). Set it on every column. |
| `offset` | `vc_column`, `vc_column_inner` | responsive width and offset classes, one group per breakpoint. |
| `sku` / `skus` / `id` / `ids` | the WooCommerce product elements | which products the element shows. |

A separate flag shows up on a write: `deprecated_keys` in the response of
`wpbakery-add-element` / `wpbakery-edit-element`, and `issue:
"deprecated_key"` inside `invalid_atts` from `wpbakery-set-content`. The
write succeeded, but WPBakery no longer honours that att and the element
ignores it at render time. `vc_btn.color` and `vc_separator.color` are the
common ones — the styling moved to the per-state colour pickers
(`custom_background`, `custom_text`, …). Deprecated atts are not offered in
the element schema, so an agent that builds from the schema will not reach
for them.

## Round-trip caveats — read these before set-content on existing pages

WPBakery stores its tree as a shortcode string in `post_content`. Three
behaviours of the parser / serializer matter when an agent reads a page,
edits it, and writes it back.

### 1. Empty atts are dropped on save

The serializer omits attributes whose value is `null` or `""`. The
practical consequence: assigning an empty string to an att does NOT reset
its default — the att simply disappears from the saved shortcode, and the
default kicks back in. To override a default with "no value", omit the
key entirely. To explicitly reset every att on an element to a known
state, call `wpbakery-edit-element` with `replace: true` and only the
keys you want to keep.

### 2. Non-WPBakery shortcodes are preserved across writes, but appended at the end

The parser only recognises shortcodes whose tag is in the WPBakery
registry (plus the stable `vc_gitem_*` set), so `get-content` returns
only the WPBakery tree. To prevent silent data loss on mixed-content
pages, every mutating ability (`set-content`, `add-element`,
`edit-element`, `delete-element`, `move-element`, `apply-template`)
detects non-WPBakery shortcodes in the pre-write `post_content` and
**re-appends them after the serialized WPBakery tree** in the saved
`post_content`. The response includes a `preserved_foreign_shortcodes`
field whenever at least one was preserved. Each entry is an object
`{tag, raw}` — use `tag` to name the shortcode to the user
("gallery", "wpforms", "contact-form-7") without parsing the raw
string.

Caveats of the preservation:
- The original position of the foreign shortcode is NOT retained. If
  the foreign shortcode was between two `vc_row` blocks, it now sits
  after the entire WPBakery tree.
- If the foreign shortcode was nested INSIDE a WPBakery element's
  `_content` (e.g. inside a `vc_column_text`), it stays there
  untouched — `_content` is treated as opaque inner HTML.

### 3. Raw inner text is dropped when an element also has child shortcodes

For containers that mix raw HTML/text with nested shortcodes — for
example `[vc_message]Hello [vc_btn title="x"] world[/vc_message]` — the
parser preserves the children but loses the surrounding "Hello " and
" world" text. The `_content` field is only populated when the container
has zero shortcode children. If you need text and a child shortcode in
the same container, use two adjacent `vc_column_text` (or equivalent)
elements instead of mixing them in one wrapper.

### 4. `_idx` is renumbered after STRUCTURAL writes — re-fetch before the next call

Only the writes that change the tree structure renumber `_idx`:
**add-element**, **delete-element**, **move-element**, **set-content**,
**apply-template**. After any of these, the `_idx` values you saw on
the previous `wpbakery-get-content` are stale (e.g. after deleting
`_idx 2`, the node that was `_idx 3` becomes `_idx 2`) — always call
`wpbakery-get-content` again before the next call that targets a
specific `_idx`.

**`wpbakery-edit-element` is safe to chain** without re-fetching: it
only mutates atts on an existing node, the tree structure and the
indices stay identical. Apply edits to multiple known indices in a
row without intermediate get-content calls.

To reorder content without managing indices manually, prefer
`wpbakery-move-element` over a manual delete-then-add — it resolves
source and target inside a single tree snapshot.

### 5. A handful of literal characters in att values are unsafe

Three characters in att string values either get silently rewritten or
break the shortcode parser. Rewrite the source content rather than
fight the format:

- **Literal `\` (backslash)**: stripped by WordPress slashing before the
  value reaches storage. `title="path\to\file"` round-trips as
  `"pathtofile"`. Use forward slashes (`/`) or substitute the backslash
  with another character.
- **Literal `[` or `]`**: confuse the shortcode parser, which then
  drops the whole element. `title="section [1]"` causes the get-content
  tree to come back empty. Use parentheses, encode as HTML entities
  (`&#91;` / `&#93;`), or rephrase.
- **Literal `[/<tag>]` matching the enclosing element** (e.g.
  `title="end [/vc_btn] of section"` on a `vc_btn`): the substring
  closes the shortcode prematurely and the rest is lost. Rewrite the
  copy so this literal never appears inside that element.

These are properties of the WordPress shortcode format itself, not of
the WPBakery encoder. The values agents normally write (titles,
descriptions, button labels in real languages) never hit these — the
caveat is here for the rare edge case (code samples, file paths,
literal tag references in technical docs).

### 6. Very deep nesting (15+ levels) is silently truncated

The parser caps recursion at 15 levels. Real WPBakery pages rarely
approach this (a typical layout is 3-6 deep). When you do build a
tree deeper than 15 levels via `set-content`, the write succeeds but a
follow-up `wpbakery-get-content` returns a tree shallower than what you
sent — there is no explicit warning today. To detect: compare your input
tree depth against the depth of `$result['tree']`.

## Template flow

- **`wpbakery-create-template` from `post_id` + `element_idx` is the
  robust path.** The ability reads the live tree, finds the node by index,
  and serializes it cleanly. The captured layout always matches what is on
  the page.
- **`wpbakery-create-template` from a raw `shortcode` string** is a fallback
  for layouts assembled outside the page tree. Validate the shortcode parses
  before saving.
- **`wpbakery-apply-template` defaults to `mode=append`.** Use `prepend` to
  put the template at the top, and `replace` only when the user explicitly
  wants to discard the existing page content.
- The `category` argument on `wpbakery-create-template` is a free-form label
  (e.g. `"Hero Sections"`, `"Pricing"`). It is stored on the template as the
  custom field `novamira_category` — WPBakery itself ignores it; Novamira
  uses it to filter `wpbakery-list-templates`.
- Templates saved by `wpbakery-create-template` also appear in WPBakery's
  visual editor under **My Templates**, so the human editor and the agent
  share the same library.

## Diagnostic loop (inspect-page → auto-fix)

`wpbakery-inspect-page` returns a `diagnostics[]` array. Each entry carries a
`severity` enum that drives the recommended handling:

- `severity: "action_required"` — safe to auto-apply. Always paired with a
  `fix_call: {ability, args}`. Execute it mechanically:
  `wp_get_ability($d['fix_call']['ability'])->execute($d['fix_call']['args'])`.
- `severity: "warning"` — surface the `user_message` to the user before
  mutating. Do NOT auto-apply (the fix would require user judgement, e.g.
  repositioning preserved foreign shortcodes).
- `severity: "info"` — informational only.

Canonical loop on an unfamiliar page:

```
1. r = wpbakery-inspect-page(post_id)
2. for d in r.diagnostics:
     if d.severity == "action_required": execute d.fix_call
     else if d.severity == "warning":   relay d.user_message to user
3. r2 = wpbakery-inspect-page(post_id)   # confirm action_required cleared
4. proceed with the mutation the user asked for
```

The `code` field is for branching on specific diagnostics; `severity` is
for branching on handling strategy.

## Third-party elements

`wpbakery-list-elements` enumerates every element registered through
WPBakery's `WPBMap` registry, including those added by themes and plugins.
The agent does not need a special workflow to use third-party elements —
treat them like core ones. If a third-party element is missing from the
list, the issue is registration timing on the site (the plugin/theme is
registering after the request scope ends), not in this skill.

## What belongs elsewhere

- **Element catalogue** — see `novamira/wpbakery-list-elements` (compact
  overview, no controls). For the control schema of a single element, call
  `novamira/wpbakery-get-element-schema tag="<vc_xxx>"` once you know the
  tag you want.
- **Snapshot of an existing page** before editing it — see
  `novamira/wpbakery-get-content`. Use it to learn `_idx` values for
  `wpbakery-edit-element`, `wpbakery-add-element` and
  `wpbakery-create-template`.
- **Setup, version, registered categories, environment issues** — see
  `novamira/wpbakery-check-setup`.
