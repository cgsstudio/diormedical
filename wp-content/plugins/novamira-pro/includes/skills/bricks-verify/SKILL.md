---
name: bricks-verify
description: Companion of bricks-build-page — how to prove a Bricks write actually landed and renders as designed, using the write responses (verified, stored_element_count, warnings, raw_layout_html_ids, empty_structural_ids, unresolved_variables, css_file, revision_id), return_css, get-content summary, bricks-check-setup CSS health, and a front-end fetch at desktop and mobile widths. Load after every section write and before declaring a page done; also when the front end does not match what was written.
---

# Verifying Bricks work

A write response is not proof. Verify per section, then per page.

## After every write (set / insert / patch / remove / apply)

Read the response fields and act on them before the next write:

- `verified: false` — the stored tree differs from what was sent. Re-read
  with `bricks-get-content summary: true`, do not retry blindly (a retry with
  new ids inserts the subtree twice).
- `stored_element_count` / `element_count` — compare with your plan.
- `warnings[]` — each names the element, the key and the fix.
- `raw_layout_html_ids[]` — text elements carrying block-level HTML: model
  those nodes as native elements.
- `empty_structural_ids[]` — section/container/block with no children.
- `unresolved_variables[]` — `var(--x)` with no Bricks variable or palette
  colour behind it: create the token, then patch.
- `css_file` — `regenerated` / `inline` / `skipped(reason)`; a `skipped`
  on a site that loads CSS as files means the page keeps the CSS file it
  already had, so the element styles from this write are not on it. Other
  sources (global classes, theme styles) still apply, so the page may look
  partly right — fetch it rather than assuming either way.
- `revision_id` — the pre-write revision; tell the user it exists if they
  want to undo.
- `locked_by_other_user` error — someone has the page open in the builder;
  ask before forcing.

When you changed styling, pass `return_css: true` and scan the returned CSS
for the values you expect (the variable names, the class selectors, the
breakpoint blocks). `css_source` says what you got: `file` — the post's
regenerated CSS file, i.e. what the front end loads, on sites that serve
CSS from external files; `inline` — the inline generation for the area.
Orphan custom-CSS declarations are reported as warnings. To inspect the
compiled CSS later without another write, `novamira/bricks-read-css` with
`selector_contains: "brxe-<id>"` returns just that element's rules (or a
class / variable name to see where it is used).

## Structure check

`bricks-get-content` with `summary: true` returns id / name / parent /
label / children count without settings — cheap enough to run after every
section. Confirm hierarchy (section > container > …), that nestables have
their required children, that looped blocks carry `hasLoop`, and that no
element is a text node with a whole layout inside.

## Design-system check

- Fonts: heading and body families come from the theme style's typography
  groups as bare names; Google Fonts are not disabled site-wide
  (`bricks-check-setup`).
- Colours and spacing reference palette / variables; no raw hex or px for
  systemic values in the elements you wrote (`bricks-patch-elements` to fix).
- The theme style you rely on is `active` (`bricks-list-theme-styles`) —
  i.e. it has conditions.

## Front-end check (end of page, or when something looks wrong)

1. `bricks-check-setup` — no `issues[]`; when CSS loads as files, the css
   directory is writable and `css_files_missing` is 0 (pages built before
   the writes regenerated files need one re-save or a regeneration from
   Bricks → Settings → Performance).
2. `novamira/bricks-render-check` on the post: `status` 200, `fatal` false,
   `bricks_rendered` true, `css_linked` true, `nestable_roots` matching the
   sliders / tabs / accordions / navs you wrote; read `issues[]`. Then
   fetch the page URL (a browser or a plain HTTP fetch of the HTML): confirm
   the heading and body fonts are the intended families (not the system
   fallback), the body background is the brand paper colour, section
   spacing follows the scale, and that the stylesheet or inline CSS
   contains your class selectors and `--` variables. With Novamira Visual
   connected, take a desktop and a mobile screenshot and compare with the
   reference.
3. For loops and filters, fetch with a filter parameter and confirm the
   items change (see `bricks-query-loops`).

## When the front end still disagrees

- Missing fonts → family written as a variable or inside custom CSS;
  Google Fonts disabled; typography set on elements instead of theme style.
- Variables print literally → not created in Bricks; name stored with a
  leading `--`.
- Unstyled page on a file-mode site → do not conclude the file was not
  written; find out, in this order. Read the `css_file` field of the write
  you made: `regenerated`, `inline`, or `skipped(<reason>)` naming what
  stopped it. Then `bricks-read-css` on the post — `file_status` says whether
  the file exists, and the CSS it returns says whether the rules you expect
  were compiled at all. Then fetch the page and look for those rules in what
  is served. Only if the file is present and the rules are in it is the
  problem elsewhere (a template replacing the content, a caching layer). When
  a regeneration is genuinely needed, ask the user to run it from Bricks →
  Settings → Performance, never via PHP helpers.
- Duplicate or vanished elements → duplicate ids across writes; read the
  summary and fix with `bricks-remove-content`, never with `execute-php`.
