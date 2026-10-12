---
name: bricks-build-page
description: Build, extend, rebuild or redesign any Bricks page or section with native Bricks elements — new landing/marketing pages, adding a hero / features / pricing / FAQ / testimonials / CTA section to an existing page, recreating an HTML/CSS mockup or a design reference in Bricks, converting static HTML into native elements, restyling a page with the site's design system, or configuring page-level layout (full-bleed, hide header/footer, body classes). Load this BEFORE writing any Bricks element tree, even for a single section. Router skill — phased workflow (read context → plan → design system → components → build per section → verify) plus companion skills loaded on demand via novamira/skill-get (bricks-query-loops, bricks-nestables, bricks-components, bricks-page-settings, bricks-element-gotchas, bricks-verify).
---

# Building in Bricks, native-first

Bricks pages are trees of **native elements** (`section`, `container`, `block`,
`heading`, `text-basic`, `button`, `image`, `slider-nested`, …) styled through
the site's **design system** (theme styles, global variables, colour palette,
global classes, components). Your job is to produce that — never a blob of
HTML inside one element, never a stylesheet the customer cannot edit.

Work in six phases. Do not skip a phase because the request looks small: a
"just add a hero" on a mature site still needs phase 0 and phase 5.

## Phase 0 — Read the site context (one pass, before any plan)

Call `novamira/bricks-get-design-system` first: one compact read of the
classes (with the naming prefixes in use), variables, palette, theme
styles, components, breakpoints, rendering templates and the authority
level, each section naming the detail tool. Then, in parallel, once:

- `novamira/bricks-check-setup` — read `issues[]` and fix or report them
  first; note `css_loading` (inline vs file), `supported_post_types`,
  `query_filters_enabled`, and whether Google Fonts are disabled.
- `novamira/bricks-list-breakpoints` — the keys and widths in effect and the
  `:<key>` suffix to use for responsive values (custom sets rename them).
- `novamira/bricks-list-theme-styles` — which style is `active` and what it
  already defines (typography, colours, spacing, buttons). Load one with
  `bricks-get-theme-style` only when you need its values.
- `novamira/bricks-list-variables` and `novamira/bricks-list-color-palette` —
  the token vocabulary. Note naming conventions and scales.
- `novamira/bricks-list-global-classes` — lean rows only; never
  `include_settings: true` on a full listing. Read one class with
  `bricks-get-global-class` when you need its settings.
- `novamira/bricks-list-components` — reusable blocks the site already has
  (cards, CTAs, headers). Reuse beats rebuild.
- `novamira/bricks-list-templates` — header/footer/section templates and
  their conditions, so you do not rebuild a header that already exists.
- If the page exists: `novamira/bricks-get-content` with `summary: true` to
  see its structure without settings.
- Page lifecycle is the base plugin's: a new page is `novamira/create-post`
  (`post_type: page`), pages and Bricks templates are removed with
  `novamira/delete-post`. The `bricks-*` tools only write inside a post.

**Scale the read to the site.** On a mature site (established framework,
hundreds of classes/variables) stop reading a listing the moment it is
clearly systematic, note the prefix convention (`fr-*`, `at-*`, `sb-*`) and
re-list with the `category` filter for what the current sections need. On a
fresh site everything is cheap: read it all.

**Authority rule.** The Bricks design system (theme styles, variables,
palette, global classes, components, breakpoints) is the source of truth for
Bricks work. Do not load the `novamira-design` skill, and do not create or
activate a Novamira design or a DESIGN.md unless the user asks for one — the
design feature may be switched off or absent altogether. Follow the design
authority level the site reports in its instructions (`novamira/get-active-design`
and `novamira/check-design` return it as `authority` when the tools exist):
`builder` (no active design) — build from the Bricks stores and the user's
brief only; `hybrid` (a Novamira design is active) — Bricks still leads for
everything it already defines; consult the active design only to fill a gap
Bricks has no value for, or when the user explicitly asks to apply it. In
both cases materialize any design token in Bricks (variables, palette, theme
style, classes; see phase 2) before referencing it, and let existing Bricks
values win any conflict the user has not decided. Never reference a token
that exists only in a design document.

## Phase 1 — Plan the page as numbered sections

Write a short plan before writing elements (show it to the user when the
request is open-ended or when a phase-2 confirmation is needed; otherwise
proceed):

For each section: purpose and copy source · the native element tree in one
line (`section > container > block(grid) > …`) · static or query loop (see
`bricks-query-loops`) · nestables it needs (slider / tabs / accordion / nav →
`bricks-nestables`) · components reused or created · tokens reused / new /
ambiguous.

Aim for **variety and hierarchy**: alternate full-bleed and constrained
sections, vary background treatment (paper, tint, brand, image), use at least
one asymmetric or media-led layout, one grid, one nestable where content
calls for it. A page made of identical centred stacks reads as generic.

When the source is an HTML/CSS mockup or a screenshot, map every semantic
node to a native element (table in phase 4) — the mockup is the spec for
structure and rhythm, the Bricks design system is the spec for values.

## Phase 2 — Design system: materialise what is missing, in Bricks

Tokens live in Bricks, not in your head and not in a design file. Order:

1. **Colours** → `bricks-add-color-palette-entry` (brand colours the customer
   should see in the picker; `dark` variant when the site uses native dark
   mode). Reference as `{"raw": "var(--bricks-color-<id>)"}` or the palette
   entry; read the exact reference shape from `bricks-list-color-palette`.
2. **Scales** (spacing, radii, shadows, fluid type sizes) →
   `bricks-create-variable`. Name without the leading `--`; kebab-case,
   semantic (`space-l`, `radius-s`, `shadow-soft`, `text-xl`), following the
   site's existing prefix when there is one. Prefer fluid `clamp()` values
   for type and section spacing on greenfield sites.
3. **Font families** → the theme style's typography groups (body, headings,
   buttons) as **bare family names** (`"Cormorant Garamond"`, `"Lato"`),
   with the fallback stack in the typography control's `fallback` field.
   Never put a font family in a variable and never write
   `font-family: var(--x)`: Bricks quotes the value literally and does not
   load the font. Google Fonts load only from literal family names in
   typography controls.
4. **Site-wide defaults** (body background, base typography, headings,
   links, buttons, section/container spacing) → the active theme style via
   `bricks-edit-theme-style`, or `bricks-create-theme-style` with
   `conditions: [{"main": "any"}]` when none exists. A theme style with no
   conditions is inert — the abilities return `active: false`; always set
   conditions. Read the writable groups and keys with
   `bricks-get-theme-style` on an existing style before writing; custom CSS
   for a theme style lives under `css.stylesheet`.
5. **Reusable style bundles** (`card`, `btn-primary`, `section--tint`) →
   `bricks-create-global-class`, applied with `_cssGlobalClasses` or
   `bricks-apply-global-class`. One class per repeated pattern; naming
   follows the site convention, BEM-style when starting fresh.

Rules that prevent the classic "it looks unstyled on the front end":

- **A `var(--x)` may be referenced only after `x` exists in Bricks** (a
  variable or a palette colour). Writes report `unresolved_variables[]`;
  treat any entry as a bug to fix before moving on.
- Never write the design-store options through `execute-php`; the
  dedicated abilities generate ids and reject duplicates.
- Do not invent numeric caps ("max 6 colours"): the site's scale is whatever
  the design needs; keep it consistent and named.

**Confirm with the user before**: creating or modifying a theme style,
modifying existing variables/classes/palette entries, deleting anything. Show
names and values. Creating new tokens on a fresh site needs no confirmation
beyond the plan.

## Phase 3 — Components

Load `bricks-components` when a pattern repeats across pages (cards, CTA
bands, team member, pricing tier) or the site already has components. Reuse
with `bricks-apply-component` + property values; create with
`bricks-create-component` from a built subtree. To add a field to an existing
component use `bricks-edit-component` — never rebuild it and re-point pages.

## Phase 4 — Build, one section at a time

Think in semantic HTML, write native elements. Mapping:

| Semantic node | Bricks element |
| --- | --- |
| `<section>` / page band | `section` |
| centred wrapper / `.container` | `container` |
| grouping `<div>` / card / column | `block` (or `div` for a bare wrapper) |
| `<h1>`–`<h6>` | `heading` (`tag`) |
| `<p>`, short copy | `text-basic` |
| rich text with inline markup / lists | `text` |
| `<a class="btn">`, CTA | `button` |
| `<a>` plain | `text-link` |
| `<img>` / `<picture>` | `image` |
| inline `<svg>` / icon | `icon` or `svg` |
| `<video>` | `video` |
| logo strip, gallery, carousel | `image-gallery`, `slider-nested` |
| tabs / accordion / FAQ | `tabs-nested`, `accordion-nested` |
| `<nav>` / menu | `nav-nested` (in a header template) |
| `<form>` | `form` |
| repeated CMS items | one card `block` with `hasLoop` — `bricks-query-loops` |

**Never** paste layout markup into `text`, `text-basic` or `code`. One
element per node. The writes report it in `raw_layout_html_ids[]`, and
`empty_structural_ids[]` when a section/container has no children — both
mean "model it natively".

`raw_layout_html_ids[]` is a named set of patterns, not a general HTML
check. In any text element it reports layout containers (`section`, `div`,
`main`, `article`, `aside`, `header`, `footer`, `nav`, `form`, `table`,
`figure`), `<style>`, `<script>` and an inline `style=` attribute; in the
plain `text-basic` / `text-link` it also reports `h1`-`h6`, `ul`, `ol`,
`img`, `button`, `picture`, `video` and `iframe`, which are legitimate
prose inside the rich `text`. Anything outside that set — inline
`<strong>`, `<em>`, `<a>`, `<br>`, and `<blockquote>`, which is in neither
list — is not reported, so a write that comes back without
`raw_layout_html_ids[]` is not evidence that the text carries no markup:
check what you wrote. Split separate lines (an address, a two-line label)
into their own elements instead of joining them with `<br>` — separate
elements are separately editable and styleable in the builder; a `<br>`
inside one string is not.

Hierarchy: `section > container > block-level elements`; use `block` for
grids and cards inside the container; skip the container only for a
deliberate full-bleed band. Every element needs `parent`; ids are optional
(the server generates Bricks-style ids and returns them) — if you assign ids,
keep them unique within the payload and use exactly six characters. A new id
of any other length is refused; omit the id to have a six-character one
generated.

Write per section with `novamira/bricks-insert-content` (`area: "content"`
for pages; `header` / `footer` for those templates) — not one giant
`set-content` for the whole page. Use `bricks-set-content` only to replace
an entire area, `bricks-patch-elements` for targeted changes
(`unset_settings` removes keys).

Style hierarchy, in order: theme-style defaults (do not override what the
theme style already sets) → existing global class → variable / palette
reference → new token → raw value only for non-systemic one-offs (animation
timings, a single transform) → `_cssCustom` last, for what no control
covers. Raw hex, raw px spacing, raw font sizes/families are never
acceptable for systemic values.

Control families: layout elements (`section`, `container`, `block`, `div`)
take `_direction`, `_rowGap`, `_columnGap`, `_gridGap`; most other elements
take `_flexDirection` and `_gap`. A few take neither and report
`control_family: "none"` — `slot` does — so read the family rather than
assuming one. Use `bricks-list-elements element=<name>`
for the exact keys and `value_format` of anything you are unsure about, and
`bricks-element-gotchas` for the link/image/svg/icon-box shapes and the
responsive / hover key syntax.

Minimal example (one section):

```json
{"post_id": 17, "area": "content", "elements": [
  {"id": "hero01", "name": "section", "parent": 0,
   "settings": {"_cssGlobalClasses": ["<class-id-of section--tint>"]}},
  {"id": "herc01", "name": "container", "parent": "hero01",
   "settings": {"_direction": "column", "_rowGap": "var(--space-l)", "_alignItems": "flex-start"}},
  {"id": "herh01", "name": "heading", "parent": "herc01",
   "settings": {"text": "Calm, grounded care", "tag": "h1"}},
  {"id": "herp01", "name": "text-basic", "parent": "herc01",
   "settings": {"text": "Trauma-informed bodywork in Portland.", "tag": "p"}},
  {"id": "herb01", "name": "button", "parent": "herc01",
   "settings": {"text": "Book a session", "link": {"type": "external", "url": "/book/"}, "style": "primary"}}
]}
```

## Phase 5 — Verify every section, then the page

Load `bricks-verify` for the full checklist. Minimum after each write: read
`verified`, `stored_element_count`, `warnings`, `raw_layout_html_ids`,
`empty_structural_ids`, `unresolved_variables`, `css_file` from the response
and act on them; `return_css: true` on a write when you changed styling and
scan the returned CSS for the values you expect. At the end fetch the page
URL and confirm fonts, colours and spacing come from the design system, and
that `bricks-check-setup` reports no CSS-file issues when the site loads CSS
as files.

## Things that look like success and are not

- Repeated content typed by hand instead of a query loop (`bricks-query-loops`).
- `hasLoop` on the grid container instead of the card block.
- A theme style without conditions; a `var(--x)` never created in Bricks;
  a font family inside a variable.
- Writing Bricks data with `execute-php`, or saving build scripts into the
  sandbox directory (they re-run on every request and corrupt trees).
- A `code` element for anything a native element can express. The writes
  refuse `code` until the user explicitly confirms (`allow_code_elements`).
- "Regenerating" CSS through PHP helpers: the writes already refresh Bricks'
  CSS; if `check-setup` still reports missing CSS files, ask the user to
  regenerate from Bricks → Settings → Performance.

## Tool choice

| Intent | Tool |
| --- | --- |
| New page | `novamira/create-post` (`page`, then `bricks-set-post-types` if `check-setup` says the type is not enabled) → `bricks-insert-content` per section |
| Delete a page or a Bricks template | `novamira/delete-post` — the Bricks tools do not duplicate the base plugin's post lifecycle (create, list, delete) |
| Add a section to an existing page | `bricks-insert-content` (`after` / `append_to`) |
| Change settings on existing elements | `bricks-patch-elements` |
| Replace an entire area | `bricks-set-content` |
| Remove a subtree | `bricks-remove-content` |
| Page-level layout (full-bleed, header/footer off, body classes) | `bricks-set-settings` — see `bricks-page-settings` |
| Header / footer / popup / section templates and their conditions | `bricks-create-template`, `bricks-set-template-conditions` — see `bricks-page-settings` |

## Companion skills (load with `novamira/skill-get`)

- `bricks-query-loops` — CMS-driven repeated content, CPT discovery, filters and pagination.
- `bricks-nestables` — sliders, tabs, accordions, nav / mega menu, offcanvas: exact child structures.
- `bricks-components` — reuse, create, extend components; property definitions by id.
- `bricks-page-settings` — blank canvas / full-bleed recipe, intent → key map, templates and conditions, permalinks.
- `bricks-element-gotchas` — link / image / svg / icon-box shapes, responsive and hover keys, interactions, dynamic tokens.
- `bricks-verify` — per-section and whole-page verification with the write responses, CSS and the front end.
