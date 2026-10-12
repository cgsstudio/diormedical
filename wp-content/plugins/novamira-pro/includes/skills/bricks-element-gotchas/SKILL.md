---
name: bricks-element-gotchas
description: Companion of bricks-build-page with the element-level shapes that save fine but render wrong in Bricks — link objects (type external/internal), image and svg sources, icon-box content, button variants, layout vs non-layout control families, responsive and hover/focus key syntax, interactions (trigger/action), dynamic tokens and the @fallback spacing rule, and the code element policy. Load while writing element settings whenever a key or value shape is not obvious, or when adding responsive (tablet/mobile) or hover values.
---

# Element gotchas

Look up any key with `novamira/bricks-list-elements element=<name>`: it
returns the element's real controls with `value_format`. What follows are
the shapes that pass validation and still render wrong.

## Control families

Layout elements — `section`, `container`, `block`, `div` — use
`_direction`, `_rowGap`, `_columnGap`, `_gridGap` (plus `_display`,
`_justifyContent`, `_alignItems`, `_flexWrap`, `_gridTemplateColumns`).
**Most other elements** use `_flexDirection` and `_gap` for their own inner
layout. Writing `_direction`/`_rowGap` on a heading, button or icon-box is
silently ignored; the writes warn with the correct family.

A few elements are in neither family and take no shared layout keys at all —
`bricks-list-elements` reports them as `control_family: "none"`, and `slot`
is one of them. Read the family for an element you have not
written before instead of assuming it is flex-item.

`section`, `container` and `block` are already flex by default; `div` is a
plain block. You do not need `_display: "flex"` for flex keys to apply.

## Responsive, hover and pseudo keys

Desktop is the unsuffixed key: `_padding`, never `_padding:desktop`. Other
breakpoints append their key: `_padding:tablet_portrait`,
`_padding:mobile_landscape`, `_padding:mobile_portrait` (custom breakpoints
use their lowercase key with underscores, e.g. `_padding:large_tablet`).
States and pseudo-elements append after the breakpoint:
`_background:hover`, `_border:focus`, `_content:before`,
`_background:mobile_portrait:hover`. The same grammar applies inside global
class settings and to element-specific controls (`perPage:mobile_portrait`).
Bricks' default breakpoints are desktop (base), tablet_portrait (991),
mobile_landscape (767), mobile_portrait (478); a site may have custom
ones — do not assume, read them from the site when they matter.

```json
{"_padding": {"top": "var(--space-xl)", "bottom": "var(--space-xl)"},
 "_padding:mobile_portrait": {"top": "var(--space-l)", "bottom": "var(--space-l)"},
 "_background": {"color": {"raw": "var(--brand)"}},
 "_background:hover": {"color": {"raw": "var(--brand-dark)"}}}
```

Global classes are referenced from elements as an ordered array of class
**ids**: `"_cssGlobalClasses": ["<id>"]` — never class names.

## Links

The link control writes `{"type": "external", "url": "https://…"}` for a
plain URL — **not** `"type": "url"`, which saves but renders no `href`.
Other types: `internal` (with `postId`), `meta` (with `useDynamicData`, e.g.
`{"type": "meta", "useDynamicData": "{post_url}"}` for the current or looped
post), `taxonomy`, `lightboxImage`, `lightboxVideo`, `media`. Applies to
every element with a link control.

A `link` on a layout element (`section`, `container`, `block`, `div`)
renders only with `tag: "a"` — without it the element stays a `<div>` and
the link is silently ignored (the write verifies fine).

## List-typed controls

Some controls store a list, not a string: the form element's `actions`
(`["custom"]`, `["email", "redirect"]`), every `multiple` select, every
repeater. Bricks iterates them at render time, so a bare string saves but
breaks the page — the validator rejects it; `bricks-list-elements` marks
these with a "list of …" value_format.

## Site background

The theme-style `general.siteBackground` compiles to `html {background}`
plus `body {background: none}`. For a brand body colour set it there and do
not also target `body` in custom CSS (it loses to the reset); if you must
write CSS, target `html body`.

## Images and SVG

- `image`: `{"image": {"id": <attachment-id>}}` or
  `{"image": {"useDynamicData": "{featured_image}"}}`; never a bare URL
  string for library images.
- `svg` from the media library: send `{"file": {"id": <attachment-id>}}` and
  **omit** `source` (a literal `""` renders blank). `source: "code"` only for
  inline markup — that is code-gated like the `code` element.

## icon-box

Content is one `content` key holding the heading and body markup
(`<h4>…</h4><p>…</p>`); there are no `title` / `text` keys (they save and
render nothing). Style the two parts with `typographyHeading` /
`typographyBody`. Icon placement uses the element's own `direction` / `gap`
controls; the box's outer layout uses `_flexDirection` / `_gap`.

## Buttons

`style` selects the theme-style button variant (`primary`, `secondary`,
…) — prefer it over hand-set colours; `outline: true` for the outline
variant instead of zeroing background and adding a border.

## Dynamic tokens

`{post_title}`, `{featured_image}`, `{acf_headline}`… render as literal text
when unknown — Bricks never errors. Confirm with
`novamira/bricks-list-dynamic-data` (`search` / `provider`) and resolve on a
real post with `novamira/bricks-resolve-dynamic-data`. Always put a space
before `@fallback`: `{tag @fallback:'Untitled'}` works. Without it the text
element may print the token literally or render nothing, depending on the tag;
the resolve ability reports the same outcome. Field-plugin tokens have
provider-specific formats — load `dynamic-data-binding`.

## Interactions

Hover effects, click handlers, entry animations and scroll triggers live in
`settings._interactions`; never edit that array by hand. Discover valid
`trigger` (Bricks' word — not `event`) and `action` keys with
`novamira/bricks-list-interaction-events-and-actions`, then
`bricks-add-interaction` / `bricks-edit-interaction` /
`bricks-delete-interaction`.

## The code element

`code` (and `svg` with `source: "code"`) is raw HTML/CSS/JS and a last
resort for markup no native element expresses. The writes refuse a tree
containing it until the user explicitly confirms (`allow_code_elements:
true`). Even then it renders only when the site has code execution on and
the calling user may execute code (Bricks signs the payload server-side);
otherwise it saves empty and the write returns a warning — say so.
