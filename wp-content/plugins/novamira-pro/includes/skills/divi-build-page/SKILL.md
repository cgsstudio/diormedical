---
name: divi-build-page
description: Build or rebuild a page in the Divi 5 builder end-to-end — from scratch or from a source such as Gutenberg, HTML, screenshots, Figma, or another builder. Activate when the user asks to create a new Divi page, recreate an existing page in Divi, style Divi modules or pages, or work with Divi modules, loops, or dynamic content. Covers the Divi 5 block-module model, the hard rules never to fake layouts with raw HTML and never to style with custom CSS, the styling hierarchy that replaces custom CSS, how loops and dynamic content work, and why Divi modules must never be routed through the Gutenberg block queue.
---

# Building a page in Divi 5

This is the Divi 5 playbook. Use it when the target is Divi, whether building
from scratch or reconstructing from another source. It targets **Divi 5** (the
WordPress-block module model), not Divi 4 shortcodes.

Do everything through the dedicated `novamira/divi-*` abilities — there is one
for every Divi operation (content, modules, dynamic content, loops, presets,
colors, variables, fonts, display conditions, library, theme builder, and
schema discovery). Supporting abilities such as `novamira/create-post` and
`novamira/memory-*` are allowed when they support the workflow.

If Divi is active but discovery exposes no `novamira/divi-*` abilities, the
Divi specialization is not active: it is turned off on the Novamira Features
page, the block-based builder is disabled, or the Pro license/availability gate
is not satisfied. Tell the user instead of falling back to the Gutenberg queue
or raw content.

**Never build or style a Divi page with `novamira/execute-php`,
`novamira/write-file`, or raw `post_content`.** There is no Divi gap that
requires PHP: writing the layout or its metadata by hand skips the validation
these abilities do and leaves the page uneditable in the Visual Builder (the
write abilities also refuse builder content pushed through `create-post` /
`update-post`). If you think an operation has no ability, call
`novamira/discover-abilities` and re-read this skill before reaching for PHP.

## Hard rules

These are not optional. They are the difference between a real Divi page and a
broken one.

1. **Never write Divi modules through the generic Gutenberg workflow.**
   Do NOT send `divi/*` modules through
   `novamira/gutenberg-add-pending-change`,
   `novamira/gutenberg-enable-batch-finalization` (the Block Editor Queue), or
   `novamira/gutenberg-write-content`. Divi registers its modules only inside
   its own Visual Builder runtime, so the standard block editor used by the
   queue finalizer cannot register or serialize them and the batch fails with a
   block-not-registered error. Use `novamira/divi-set-content` for a whole tree,
   or `novamira/divi-add-module`,
   `novamira/divi-edit-module`, `novamira/divi-move-module`, and
   `novamira/divi-delete-module` for targeted edits.

2. **Never fake a layout with raw HTML. Build real Divi modules.**
   Do NOT drop a `divi/code` or `divi/fullwidth-code` module containing a blob
   of HTML to reproduce a design. That bypasses Divi's styling, responsiveness,
   dynamic content, and editability — the page looks right once and is dead in
   the editor. Compose the design from native modules (`divi/heading`,
   `divi/text`, `divi/image`, `divi/button`, `divi/blurb`, …) inside the
   structural skeleton. The Code module is only for a genuine code snippet the
   user explicitly asked to embed. `divi-list-modules` flags the code modules
   `raw_html: true` as a reminder, and the write abilities reject code modules
   unless the explicit `allow_code_modules: true` opt-in is passed — only set it
   when the user has explicitly confirmed they want a raw code module.

3. **Never style with custom CSS. Style with module attrs.**
   Do NOT write a stylesheet — not in a `divi/code` module, not in an inline
   `<style>` block, not via `execute-php`/`write-file`, and not by dumping rules
   into `css.freeForm`. CSS written that way is invisible and uneditable in the
   Divi builder — one missed bracket breaks the whole page, and it is exactly
   what the customer does not want. Every visual property (background, color,
   spacing, typography, border, shadow, sizing) has a native attr under
   `module.decoration.*` — discover it with `divi-get-style-schema` **before**
   concluding it doesn't exist. If a rule applies to more than one module, it's
   a global preset, color, or variable — not a stylesheet. See *Styling
   hierarchy* below.

4. **Respect the structural grammar.** What may nest depends on the parent's
   **attributes**, not only its name — see *Structure* below. The everyday
   shape is `divi/section` › `divi/row` › `divi/column` › content modules;
   content modules live inside columns, never loose at the top level. Rows
   define the column structure. A **fullwidth** section is laid out
   differently, a column has two flavours, and a **specialty** section's own
   columns cannot be written here.

5. **Use loops for repeated, query-driven content** (see Loops) and **dynamic
   content for values bound to the post/site/query** (see Dynamic content) —
   never hardcode what should be dynamic.

## Domain model

Divi 5 stores a page as WordPress blocks in `post_content`
(`<!-- wp:divi/section {…attrs…} -->…`), round-tripped with the core block
parser. You work with a tree of module nodes.

- **Module names** are `divi/<slug>` (e.g. `divi/text`, `divi/blog`).
- **Structure** is decided by the parent's *attributes*, not only its name.
  This is Divi's own rule, and the write abilities enforce it:

  | Parent | What it accepts |
  |---|---|
  | the page root | `divi/section` |
  | a regular `divi/section` (no `type`, or `"regular"`) | `divi/row` |
  | a **fullwidth** `divi/section` (`module.advanced.type` = `"fullwidth"`) | content and `fullwidth-*` modules **directly** — no row |
  | a **specialty** `divi/section` (`type` = `"specialty"`) | its direct `divi/column` children are **refused**; a `divi/row` saves with a warning — see below |
  | `divi/row` | `divi/column` |
  | `divi/row-inner` | `divi/column-inner` |
  | an **ordinary** `divi/column` | content **and fullwidth** modules, `divi/group`, and a nested `divi/row` (that column's own sub-grid) |
  | a **specialty** `divi/column` (carries `module.advanced.specialtyColumns`) | `divi/row-inner` only |
  | `divi/column-inner` | content **and fullwidth** modules, and `divi/group` |
  | `divi/group` | content **and fullwidth** modules, another `divi/group`, and a `divi/row` |
  | `divi/group-carousel` | `divi/group` |
  | a container module whose `children` list names child modules (`divi/accordion`, `divi/tabs`, `divi/slider`, …) | those child modules; the ones that set "allow all elements" (`divi/accordion`, `divi/contact-form`, `divi/counters`, `divi/post-filter`) also take content and fullwidth modules (`divi/post-filter-item` can also nest deeper — see below) |
  | a module with an **empty** `children` list (most content modules, e.g. `divi/text`) | any registered module of **any** category except `child-module` — content, fullwidth **and structural** (so `divi/row`, `divi/group`, `divi/global-layout` all nest here) — with four names always excluded: `divi/section`, `divi/row-inner`, `divi/column`, `divi/column-inner` |
  | a module with **no** `children` list (`divi/code`, `divi/divider`, the WooCommerce modules, …) | nothing |

  "Content modules" means category `module` and "fullwidth modules" category
  `fullwidth-module` — everywhere both are listed, either is accepted, and a
  `fullwidth-*` module is not confined to a fullwidth section. Divi has a third
  placeable category, `unsupported`, for a module its registry does not know;
  these abilities refuse an unknown module name before any nesting rule runs,
  so nothing you can write falls into it and no error offers it.

  So a fullwidth section is built as `section(type=fullwidth)` ›
  `divi/fullwidth-header`.

  **Post-filter items** (`divi/post-filter-item`, one filter control) belong
  inside a `divi/post-filter`. As its direct child they are always accepted.
  Where the site's Divi builder supports deeper nesting, an item may also sit
  at any depth below the filter inside any module that takes content modules —
  e.g. `post-filter` › `text` › `post-filter-item`, or `post-filter` › `text` ›
  `group` › `post-filter-item` (a `divi/group` cannot sit directly in the
  filter) — and it binds to its nearest `divi/post-filter`. Outside a post
  filter an item is always refused, so a subtree holding items cannot be moved
  out of its filter unless the destination is inside a post filter too; moving
  the whole filter carries them along. Where that Divi does not support the
  deeper nesting, only the direct child is accepted and the error says so — a
  nested item would render there as a control that is not bound to the loop.

  **A specialty section is not supported here.** Divi lays one out with direct
  `divi/column` children, and how wide each renders is decided by *effective*
  sizing: the column's sizing attrs after preset resolution, across every
  breakpoint. These abilities cannot resolve that, so they cannot tell a layout
  that renders as asked from one that does not.

  What is actually guaranteed, and what is not:

  | | |
  |---|---|
  | `divi-set-content` | validates the **whole tree you supply**, so a `divi/column` directly inside a specialty section is refused |
  | `divi-add-module`, `divi-move-module`, non-Global `divi-apply-library-item` | validate the **inserted subtree against its immediate destination**, so a `divi/column` cannot be placed directly in one |
  | **Global** `divi-apply-library-item` | **bypasses that validation entirely** — a `divi/global-layout` reference lands inside a specialty section with no error and no warning |
  | every other path | **no guarantee the specialty ancestor is checked**, so it may succeed with no specialty-related warning: editing the column itself (its sizing above all), deleting it, moving it out, and the loop, dynamic-content, preset, interaction and display-condition writers; editing a global numbers/strings variable changes referenced values without touching the page at all |

  Two things do still fire, and they are worth knowing because they are the
  only signal you get in there:

  - an insert is graded against its **own immediate parent**, so an illegal
    child is refused and a tolerated one warns, even deep inside a specialty
    section;
  - an attrs write that changes a **section's type or a column's inner-row
    flag** is re-graded and reported.

  What is genuinely silent is the non-structural change — sizing most of all.

  > **Rule of thumb** (not a precise statement of the above): a page containing
  > a specialty section is not safe to edit through these abilities. Use the
  > Visual Builder.

  A `divi/row` in a specialty section saves with a **warning** — Divi rejects
  that shape too, but earlier content carries it.

  So: if `divi-get-content` shows a section whose `module.advanced.type` is
  `"specialty"`, edit that page in the Visual Builder, or build the layout you
  want as a regular `section` › `row` › `column` instead. A `divi/column` marked
  `module.advanced.specialtyColumns` still round-trips wherever it already
  sits.

  A placement Divi rejects is refused with an error naming what the parent
  does accept. **Exactly five** shapes are the exception, because earlier
  content written by these abilities can carry them; each is saved with a
  **warning** instead of an error so such a page keeps round-tripping:

  1. `divi/row` in a **fullwidth** section
  2. `divi/row` in a **specialty** section
  3. `divi/row-inner` in an **ordinary** column
  4. `divi/row` or `divi/group` in a **specialty** column
  5. a content or fullwidth module in a **specialty** column

  Nothing else is tolerated — a `divi/section` in a specialty column, for
  instance, is still refused outright. (A nested `divi/post-filter-item`, where
  accepted, is valid rather than tolerated, so it raises no warning.) Treat a tolerance warning as a bug to
  fix, not as approval.
- **Attributes** are nested with responsive wrappers. Most settable values sit
  at `<group>.<...>.<breakpoint>.value`, e.g. a text body is
  `content.innerContent.desktop.value`. Universal styles live under
  `module.decoration.<group>` (background, spacing, border, font, sizing, …).
  A path like `module.decoration.spacing.tablet.value` is **notation for nested
  objects** — pass it as `{"module":{"decoration":{"spacing":{"tablet":{"value":
  …}}}}}`, not as a single flat `"module.decoration.spacing.tablet.value"` key.
  Divi 5 only renders the nested shape; the abilities auto-nest a flat dotted key
  for you and warn, but write it nested from the start so a get round-trips
  cleanly.
- **Presets**: `attrs.modulePreset` stacks global presets onto a module.

## Discovery workflow (available now)

1. `novamira/divi-check-setup` — confirm Divi 5 is active (`d5_enabled`,
   `module_api_available`), that `page` is a builder post type, and the active
   `breakpoints` (the valid `<breakpoint>` keys for responsive values, e.g.
   `desktop`, `tablet`, `phone`).
2. `novamira/divi-list-modules` — browse the ~100 module types. Filter by
   `category` (`structure`, `module`, `child-module`, `fullwidth-module`) or
   `search` to keep the response small. `category` tells you the role; `raw_html`
   marks the code modules; `children` lists the named child modules a container
   hosts.
3. `novamira/divi-get-module-schema module=<name>` — for a chosen module, read
   its content/config `fields` (each with its `attrName` path and whether it
   accepts dynamic content) and its `style_groups`.
3b. `novamira/divi-get-style-schema module=<name>` — the styling counterpart:
   the universal **decoration** groups (background, spacing, border, fonts, …)
   plus the **advanced** module options that are otherwise hard to find — the CSS
   ID/class (group `htmlAttributes`), link, column gutter, text-shadow. Each row
   is tagged `segment` (`decoration` | `advanced`). Call without `group` for the
   summary, then `group=<name>` for its fields. Set a value via `divi-edit-module`
   at `<attrName>.<breakpoint>.value.<field>` — e.g.
   `module.decoration.spacing.desktop.value.padding.top`, or
   `module.advanced.htmlAttributes.desktop.value.id` to give a section a CSS ID
   for a nav anchor or custom CSS hook.
   **Style paths are per module — never reuse one module's path on another.**
   Text styling is the classic trap: a `divi/heading` aligns and colors its
   title at `title.decoration.font.font.desktop.value` (`textAlign`, `color`,
   `size`, …), but a `divi/text` module's body lives at
   `content.decoration.bodyFont.body.font.desktop.value` — writing the heading
   path on a text module is silently ignored. Read the target module's own
   `divi-get-style-schema` before styling it.
4. `novamira/divi-get-content post=<id>` — read an existing page as a flat,
   addressable node list. Each node has an `address` path (top-level nodes `0`,
   `1`, … in document order; children append `/<index>`), `parent`, `children`, and flags
   (`loop_enabled`, `dynamic_bindings`, `raw_html`). Pass `include_attrs=true`
   for the full per-node attributes. Divi wraps saved page content in a root
   `divi/placeholder` envelope; that envelope consumes no address and is never
   listed, so top-level nodes keep their ordinary indices (`0`, `1`, … in
   document order — on a Divi-only page those are the sections; a non-Divi
   block at the top level occupies a slot too). Always use the addresses this
   call returns rather than assuming `0` is a section.

## Build workflow (writes)

Build incrementally and re-read after structural changes (addresses shift).

- **From scratch, whole page**: assemble the tree and call
  `novamira/divi-set-content post=<id> content=[…]`. `content` is a nested list
  of `{name, attrs?, children?}`. Shape it as
  `section › row › column › leaf modules`. Set text via attrs (e.g.
  `content.innerContent.desktop.value`), never via a code module.
- **Incremental**: `novamira/divi-add-module` (insert under a `parent` address at
  an optional `position`; omit `parent` to add a top-level section — it lands
  inside the page's `divi/placeholder` envelope when there is one, and the
  returned address is its top-level index as `divi-get-content` reports it),
  `novamira/divi-edit-module` (deep-merge partial `attrs` into the module at an
  `address`), `novamira/divi-delete-module` (remove a subtree),
  `novamira/divi-move-module` (reparent/reorder).
- **Addresses shift** after add/delete/move. Re-run `novamira/divi-get-content`
  before the next address-based edit rather than guessing the new path.
- Nesting and the raw-HTML rules are enforced on every write: an invalid
  parent, a leaf at the root, an unknown module, any code module without the
  `allow_code_modules` opt-in, or a code-only page is rejected with an
  actionable error. Structurally questionable but writable shapes come back in
  `warnings` — read them.
- **Retyping a section or a column re-shapes what it may contain.** Setting
  `module.advanced.type` to `"fullwidth"`/`"specialty"` on a section, or
  `module.advanced.specialtyColumns` on a column, changes the grammar for
  everything already inside it. `divi-edit-module` re-checks the subtree after
  such a write and returns the mismatches in `warnings`; move or rebuild the
  children rather than leaving them.

Worked example — a hero section:
1. `divi-add-module module=section` → `0`
2. `divi-add-module parent=0 module=row` → `0/0`
3. `divi-add-module parent=0/0 module=column` → `0/0/0`
4. `divi-add-module parent=0/0/0 module=heading attrs={"content":{"innerContent":{"desktop":{"value":"Welcome"}}}}`
5. `divi-add-module parent=0/0/0 module=button`

(Or do all of it in one `divi-set-content` call — fewer round-trips.)

**Fix forward, never rebuild.** When the page doesn't match the reference, fix
the specific failing modules with targeted `divi-edit-module` calls at their
addresses — do not re-run `divi-set-content` on the whole page. Re-read with
`divi-get-content` first, verify one section at a time against the reference.
If two targeted attempts don't fix a module, stop and re-read its
`divi-get-module-schema` / `divi-get-style-schema` — the attr path is almost
certainly wrong; retrying the same write louder wastes the user's tokens.

## Styling hierarchy — use in this order, do not skip

The most common agent failure on Divi is reaching for CSS when a native attr
already covers the intent. **Before writing any CSS, call
`divi-get-style-schema` on the target module** and look for the property among
the decoration groups. If an attr exists, using CSS instead is wrong.

1. **Module decoration/advanced attrs** — the native mechanism, not a
   workaround. `module.decoration.<group>.<breakpoint>.value.*` for background,
   spacing, fonts, border, boxShadow, sizing; content-part styling lives on the
   module's own paths (`title.decoration.*`, `content.decoration.bodyFont.*` —
   read the module's schema). This is where ~95% of styling belongs.
2. **Global presets** for styling repeated across modules of the same type
   (buttons, cards, headings) — style once, apply everywhere.
3. **Global colors, design variables, global fonts** for brand tokens
   referenced by many modules.
4. **`css.freeForm`** — true escape hatch, ONLY for what attrs cannot express
   (pseudo-elements, keyframes, child selectors). Set it with
   `divi-edit-module` at `css.<breakpoint>.value.freeForm`; inside the rules,
   the literal word `selector` is replaced with the module's own selector, so
   the CSS stays scoped to the module. Writing `background`, `padding`,
   `font-size` or any other attr-covered property here is wrong — the write
   succeeds but comes back with a warning for a reason.
5. **Never** a `divi/code` module containing `<style>`. There is no tier below
   this — it is not styling, it is a bug you are introducing.

Anti-patterns (all observed in the field, all wrong):
- Writing a page-wide stylesheet into a `divi/code` module "to save N edits"
- Inline `<style>` blocks targeting module CSS IDs
- `css.freeForm` for properties `divi-get-style-schema` exposes
- Styling via `execute-php` / `write-file` instead of module attrs

## Columns and column widths

A row lays its columns out side by side automatically. Build a row with N
columns (2–6) and they render as an even N-up grid — equal widths are filled in
for you, so `section › row › [column, column, column]` is a three-up row with no
extra attributes. A single-column row stays full width.

The `divi/row-inner` › `divi/column-inner` grid inside an existing specialty
section is distributed exactly like a normal row.

For an uneven layout, set each column's width fraction at
`module.advanced.type.desktop.value` — a Divi fraction: `1_2`, `1_3`, `2_3`,
`1_4`, `3_4`, `1_5`, `2_5`, `3_5`, `4_5`, `1_6`, `5_6` (the fractions in one row
should add up to a full width, e.g. `2_3` + `1_3`). You only set the per-column
`type`; the row's overall `columnStructure` is derived from the columns for you.
Adding or removing a column in an even row re-distributes the widths
automatically; after changing the column count of a *custom* layout, re-set the
fractions you want.

## Loops

A query loop makes any loop-capable module repeat once per query result. Use the
abilities rather than hand-writing the attrs:

- `novamira/divi-list-loop-query-types` — valid `query_type` values, what each
  one's `sub_types` mean, and `order_by`/`order` options.
- `novamira/divi-enable-loop` — turn a loop on at `address` with `query_type`
  (default `post_types`), `sub_types` (e.g. `["post"]`), `order_by`, `order`,
  `per_page`, `offset`. Only modules flagged `loop_capable` by
  `divi-list-modules` accept one — note **`divi/blog` is not loop_capable**: it
  runs its own internal posts query, the generic loop repeats other modules.
- `novamira/divi-edit-loop` — change query params on a live loop (partial).
- `novamira/divi-disable-loop` — switch it off, keeping the config.

Worked example — a grid of the 6 latest posts:
1. Build `section › row › column › blurb` (the blurb is the module that repeats).
2. `divi-enable-loop address=0/0/0/0 query_type=post_types sub_types=["post"] order_by=date order=DESC per_page=6`.
3. Bind the blurb's title and image to the current post (next).

Inside a loop, bind the repeating module's fields to the current item with the
`loop_*` dynamic sources below.

### Per-item binding in loops

To make a looped child render the current item, bind its field to a `loop_*` dynamic source with `novamira/divi-apply-dynamic-content`: pass the field's `attrName` (from `novamira/divi-get-module-schema`, e.g. `content.innerContent`, `title.innerContent`) as `field`, and a `loop_*` id as `source`. Which `loop_*` ids resolve depends on the loop's `query_type`:

- **post_types** → post sources: `loop_post_title`, `loop_post_excerpt`, `loop_post_date`, `loop_post_featured_image`, `loop_post_author`, `loop_post_link`, ….
- **post_taxonomies / terms** (a `post_taxonomies` loop iterates terms) → term sources: `loop_term_name`, `loop_term_description`, `loop_term_featured_image`, `loop_term_permalink`, `loop_term_count`, ….
- **users / user_roles** → user sources: `loop_user_name`, `loop_user_email`, `loop_user_avatar`, `loop_user_username`, `loop_user_url`, ….
- **menus** → menu sources: `loop_menu_text`, `loop_menu_link`, `loop_menu_menu_order`, `loop_menu_attr_title`, `loop_menu_description`, ….

To bind a manually named post custom field, use source
`loop_post_meta_key_manual_custom_field` with settings
`{"select_loop_meta_key":"loop_post_meta_key_manual_custom_field_value","loop_meta_key":"<meta_key>"}`.
For a key listed by Divi, use the same source with
`{"select_loop_meta_key":"loop_post_meta_key_<key>"}`. Do not use
`settings.meta_key` for a loop meta source: Divi reads only
`select_loop_meta_key` and `loop_meta_key` there. Call
`divi-list-dynamic-sources source=loop_post_meta_key_manual_custom_field` to
discover the sentinel/default, available choices, and conditional field. The
categorized reference per query type is
`novamira/divi-list-loop-query-types`.

`divi-get-content` reports `loop_enabled: true` on a module whose loop is on.
(Under the hood the loop lives at `module.advanced.loop.desktop.value` with
`enable:"on"` and `subTypes` as `[{"value":"post"}, …]`.)

## Dynamic content

Bind a module field to a dynamic source so it resolves at render time instead of
hardcoding the value. Don't hand-encode it — use the abilities:

- `novamira/divi-list-dynamic-sources` — find the source id (compact list; pass
  `source=<id>` for its settings fields, `post=<id>` to surface ACF/WooCommerce
  sources). Common ids: `post_title`, `post_excerpt`, `post_featured_image`,
  `post_meta_key` (+ `settings.meta_key`), `site_title`, `product_*`, `loop_*`.
- `novamira/divi-apply-dynamic-content` — bind `field` (a `dynamic:true` attrName
  from `get-module-schema`, e.g. `content.innerContent`, `title.innerContent`) on
  the module at `address` to a `source`, with optional `settings`
  (e.g. `{"before":"$","after":""}`). If the schema row has `value_fields`,
  pass `value_field` to bind one structured leaf — for example, bind a button
  URL with `field=button.innerContent value_field=linkUrl`. An unknown leaf is
  refused. When omitted, the ability selects `src` for image values and `text`
  for text-bearing values.
  Divi reports `post_link` as type `text` and renders anchor markup, while
  `post_link_url` and `loop_post_link` are type `url`; bind a URL slot such as
  `linkUrl` to a source whose type is `url`. Verify the type by passing
  `source=<id>` to `novamira/divi-list-dynamic-sources`: the compact listing
  returns only `id`, `label` and `group`, and `type` appears in the
  single-source detail. Watch the look-alike ids: `post_link_url` is the
  current post, while `post_link_url_<post_type>` targets one specific post
  chosen through `settings.post_id`. There is no `loop_post_link_url`; the
  similarly named `loop_product_post_link_url` is available only when
  WooCommerce is active.
- `novamira/divi-clear-dynamic-content` — revert a field to a static value; pass
  the same `value_field` when clearing an explicitly selected structured leaf.

Under the hood the binding is stored as a render-resolvable inline token
`$variable({"type":"content","value":{"name":<source>,"settings":{…}}})$` at
`<field>.<breakpoint>.value` for a simple field or below its selected structured
leaf (such as `.src`, `.text`, or `.linkUrl`) — a bare JSON object would render
literally and never resolve. `divi-get-content` reports bindings at either
shape in `dynamic_bindings` per node.

## Global presets (the reusable styling layer)

Global module presets are reusable, named styling bundles for a module type —
Divi's nearest thing to a design system. Author a preset once and apply it to
many modules, so a restyle touches one place. **Prefer presets over repeating
the same raw `divi-edit-module` styling on every module.**

**Keep DOM ids out of reusable presets.** Some Divi runtimes emit a custom
attribute row named `id` under `module.decoration.attributes` on every module
that uses the preset. Reusing that preset then creates duplicate DOM ids, which
can break fragment links, `getElementById`, `#id` selectors, labels/ARIA
references, and third-party scripts. The preset abilities detect the runtime's
behavior directly. Existing presets can also acquire this row when Divi
migrates older CSS-ID data, so inspect the reported capability instead of
assuming an older preset is safe:

- when this Divi emits preset custom ids, list results mark affected rows with
  `custom_id_hazard: true` and return a warning; get, create, edit, apply, and
  set-default return the same warning;
- when this Divi suppresses preset custom ids, `custom_id_hazard` is false and
  those operations stay silent because there is no current duplicate-id
  hazard;
- the abilities do not reject the operation because a preset used by exactly
  one module is legitimate, but do not reuse it or make it the default while
  the custom id remains;
- remove the `id` row from the preset, then set a unique ID on each module with
  `novamira/divi-edit-module` at
  `module.advanced.htmlAttributes.desktop.value.id`. That per-module CSS-ID
  field is distinct from the preset custom-attributes plane at issue here.

Discover and apply:
- `novamira/divi-list-global-presets` — discover presets (module, id, name,
  is_default, custom_id_hazard); filter by `module`. An empty list means none
  exist yet.
- `novamira/divi-get-global-preset` — inspect one preset's styling attrs and
  custom-id hazard status.
- `novamira/divi-apply-global-preset` — attach a preset to the module at
  `address` (sets `attrs.modulePreset`). The preset must be for that module's
  type — a `divi/text` preset only applies to a `divi/text` module.

Author and maintain:
- `novamira/divi-create-global-preset` — create a preset for a `module` with
  `attrs` and an optional `name`; returns its `preset_id`. **`attrs` uses the
  exact same shape as `divi-edit-module`** — the module's attributes object,
  e.g. `{"module":{"decoration":{"spacing":{"desktop":{"value":{"padding":{"top":"40px"}}}}}}}`.
  Discover attr paths with `divi-get-style-schema`.
- `novamira/divi-edit-global-preset` — rename (`name`) and/or restyle (`attrs`,
  deep-merged into the preset). Editing a preset restyles every module it is
  applied to at once.
- `novamira/divi-delete-global-preset` — remove a preset; modules keep their own
  attrs and simply stop inheriting it.
- `novamira/divi-set-default-preset` — make a preset the default for its module
  type, so every newly inserted module of that type starts on-brand (it does not
  restyle modules that already exist).

A good build flow: create presets for the components you repeat (buttons,
headings, cards), set sensible defaults, then build the page — applying presets
instead of hand-styling each module.

## Global colors (the brand palette)

Global colors are named, reusable colors — define the brand palette once and
bind module color fields to it, so a palette change restyles the whole site.
**Prefer global colors over hardcoding the same hex on many modules.**

- `novamira/divi-list-global-colors` — list the palette: each row has an `id`
  (`gcid-…`), `color`, `status`, `named` (true for the five built-in theme
  colors — primary, secondary, heading, body, link), and `var` (the CSS value
  to bind with).
- `novamira/divi-create-global-color` — add a custom color (`color` = any CSS
  color); returns its `id` and `var(--gcid-…)`.
- `novamira/divi-edit-global-color` — change a color's value and/or status by
  `id`. Works for custom colors and the five built-in theme colors (editing a
  named color updates it site-wide).
- `novamira/divi-delete-global-color` — remove a custom color (the five built-in
  theme colors can't be deleted; edit their value instead).

Bind a module to a global color by setting any color field to the `var` value
with `novamira/divi-edit-module` — e.g. a section background at
`module.decoration.background.desktop.value.color`, or a text/heading color.
Divi emits the color as a CSS custom property, so every bound element updates
when the global color changes.

## Design variables (tokens)

Design variables are named, reusable values — spacing, sizes, radii, strings,
image URLs, links — referenced as `var(--gvid-…)`. Define a token once and a
change propagates everywhere it is used. **Prefer tokens over repeating literal
values across modules.**

- `novamira/divi-list-variables` — list tokens: each row has `id` (`gvid-…`),
  `type` (numbers, strings, images, links, fonts), `label`, `value`, `var`, and
  `builtin` (true for the theme's font variables, which are read-only here).
- `novamira/divi-create-variable` — create a token of `type` (numbers, strings,
  images, links) with a `label` and `value`; returns its `id` and
  `var(--gvid-…)`. Use numbers for sizes/spacing/radii (e.g. `"16px"`,
  `"clamp(1rem,2vw,3rem)"`).
- `novamira/divi-edit-variable` — change a token's `label`, `value`, and/or
  `order` by `id`.
- `novamira/divi-delete-variable` — remove a token.

Reference a token by setting a module value to its `var` value with
`novamira/divi-edit-module` — e.g.
`module.decoration.spacing.desktop.value.padding.top` = `var(--gvid-…)`. Divi
emits the token as a `:root` custom property, so everything referencing it
updates when the token changes. For brand colors use the global-color
abilities; font variables come from the theme typography.

## Global fonts

Set the site-wide typography once instead of styling every module:

- `novamira/divi-get-global-fonts` — read the global fonts (an empty value means
  the Divi default is in effect).
- `novamira/divi-set-global-fonts` — set any of `heading_font`, `body_font`
  (family names), `heading_font_weight`, `body_font_weight`, `body_font_size`,
  `body_font_height`, and the tablet/phone base sizes. Only the fields you pass
  change; it applies site-wide on the front end. For per-module fonts, use
  `divi-edit-module`.

## Display conditions (conditional visibility)

Show or hide a module by visitor or context — login status, role, post/term,
date & time, device/browser, cookie, or WooCommerce state:

- `novamira/divi-list-condition-types` — the condition types (`conditionName`),
  each with its `display_rules` (the `displayRule` values) and the extra
  `settings` keys it reads.
- `novamira/divi-set-display-conditions` — set the conditions on the module at
  `address`. `conditions` is a list of `{conditionName, settings, operator?}`:
  `settings` carries the type's `displayRule` plus its keys; `operator` (`OR`
  default, or `AND`) joins to the next condition. The list replaces the module's
  current conditions. Example —
  `[{"conditionName":"loggedInStatus","settings":{"displayRule":"loggedIn"}}]`
  shows the module only to logged-in visitors.

## Interactions (motion)

Add motion to a module — a trigger on the module runs an effect on the module
itself. Most motion is already available as decoration attrs
(`module.decoration.animation` / `scroll` / `sticky` / `transform` /
`transition` / `filters` — discover them with `divi-get-style-schema` and set
via `divi-edit-module`). Use `module.decoration.animation` or
`module.decoration.scroll` via `divi-edit-module` for scroll-based reveals. The
trigger→effect **interactions** envelope has its own two abilities:

- `novamira/divi-list-interaction-types` — the `triggers` (click, mouseEnter,
  mouseExit, viewportEnter, viewportExit, load, breakpointEnter,
  breakpointExit) and `effects` (toggleVisibility, scrollToElement,
  mirrorMouseMovement), each with its extra `settings` keys.
- `novamira/divi-set-interactions` — set the interactions on the module at
  `address`. `interactions` is a list of `{trigger, effect, settings?}`; the
  list replaces the module's current interactions. Interactions are **desktop
  only** (not responsive) and **target the module itself**. Example —
  `[{"trigger":"click","effect":"toggleVisibility"}]` hides the module when
  clicked. On a self-target, `toggleVisibility` is a one-way hide except with
  breakpoint triggers: once hidden, the module cannot fire a pointer or
  viewport trigger again, and an initially hidden module is not observed.
  `viewportExit` may fire immediately when the module starts outside the
  viewport. Pair `mirrorMouseMovement` with `mouseEnter`; the effect starts when
  the page loads.

## Divi Library (reusable layouts)

Save a section, row, module, or whole page to the Divi Library and reuse it
across pages:

- `novamira/divi-list-library-items` — the saved items (id, title, type,
  `global`).
- `novamira/divi-create-library-item` — save the subtree at an `address` on a
  `post` (omit `address` to save the whole page) under a `title`; returns the
  `library_id`. Pass `global: true` for a **Global** item (live-linked).
- `novamira/divi-apply-library-item` — insert a saved item into a page at a
  `parent` address (omit for the top level — a saved section). A regular item is
  copied (nesting validated); a **Global** item is inserted as a live-linked
  reference, so editing the saved item later updates every page using it.
- `novamira/divi-delete-library-item` — remove a saved item.

A library item id is an ordinary Divi post — edit its content with the normal
content/module abilities by passing the id as the post. Editing a **Global**
item's content propagates to every page that references it.

## Theme Builder (header / footer / body)

Build custom headers, footers, and body/archive/404 layouts that apply by
condition:

- `novamira/divi-list-theme-builder-templates` — list templates with their
  header/body/footer **layout post ids** and assignment conditions (`use_on` /
  `exclude_from`).
- `novamira/divi-list-theme-builder-conditions` — the catalog of valid
  assignment condition ids (e.g. `singular:post_type:page:all`, `homepage`,
  `archive:post_type:post`); a `dynamic` id ending in `:` needs an object id
  appended.
- `novamira/divi-create-theme-builder-template` — create a template: pick
  `areas` (`header`/`body`/`footer`), set `use_on`/`exclude_from`, optionally
  `default: true`. Returns the new layout post ids.
- `novamira/divi-set-theme-builder-conditions` — re-assign (`use_on` /
  `exclude_from` replace the lists) or enable/disable an existing template.
- `novamira/divi-delete-theme-builder-template` — remove a template (and, by
  default, its layouts).

**The header/body/footer layout ids are ordinary Divi posts.** After creating a
template, build each returned layout id with the normal content/module abilities
(`divi-set-content`, `divi-add-module`, …) passing the layout id as the post.
The template applies on the front end immediately.

## Gotchas

- **Responsive wrapper required.** Most attributes need the
  `<breakpoint>.value` shape (at least `desktop`). A bare scalar is ignored.
- **Some content fields take a structured value, not a string.** The button's
  `button.innerContent.<breakpoint>.value` is an object `{text, linkUrl, …}`
  and an image's bindable `value_fields` include `src` and `linkUrl` (a plain
  string renders neither module correctly). An image can store `alt` alongside
  those leaves, but `alt` is not a bindable `value_field`. Dynamic-content
  binding targets `src`/`text` automatically, and `value_field` selects another
  advertised leaf such as `linkUrl`.
- **Structural nesting is enforced, and it reads the parent's attrs.** A
  content module placed directly in a *regular* section (skipping row/column)
  is rejected — but a *fullwidth* section is exactly where content and
  fullwidth modules go directly. A `divi/row` nested inside an **ordinary**
  `divi/column` is valid (that column's own sub-grid); a **specialty** column
  (the one carrying `module.advanced.specialtyColumns`) takes `divi/row-inner`
  › `divi/column-inner` and nothing else; a **specialty section**'s direct
  columns cannot be created or replaced. See the Structure table above.
- **Do not use these abilities on a page holding an unconverted legacy
  shortcode.** Divi parks a shortcode it could not convert in
  `divi/shortcode-module`, whose payload lives in the block's own markup rather
  than in attrs, and this model carries only name + attrs + children. Refusing
  that module by name stops you *building* one — it does **not** protect a page
  that already has one: **any** write that resaves the page (editing, moving,
  adding or deleting a different module anywhere on it) reserializes every
  block and drops that payload, and the write reports success. Read the page
  first; if `divi-get-content` shows a `divi/shortcode-module`, edit that page
  in the Visual Builder instead.
- **Cache after structural writes.** `update_post_meta` does not fire
  `save_post`; structural writes should `clean_post_cache($post_id)`.
- **Legacy shortcode layouts are out of scope.** These abilities operate on the
  Divi block model only.

> Use `novamira/divi-list-modules` to see which abilities are currently
> registered on this site.
