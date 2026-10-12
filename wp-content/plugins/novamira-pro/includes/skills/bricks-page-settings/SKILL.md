---
name: bricks-page-settings
description: Companion of bricks-build-page for page-level and template-level Bricks configuration — blank canvas / full-bleed recipe (hide header/footer, zero content margin), the intent → settings-key map (body classes, scripts, page CSS, boxed/wide layout, lazy load), header/footer/popup/section templates with their display conditions, and permalink caveats when returning URLs. Load when the user asks to hide the header/footer, make a page full-bleed, or create a header/footer/popup template.
---

# Page and template settings

Page settings are written with `novamira/bricks-set-settings` (`post_id` +
only the keys you need; shallow merge by default). Discover keys and shapes
with `novamira/bricks-list-settings` (`scope` page / template / global,
`key=<name>` for one control). Never pass `templateType` on a regular page —
it is reserved for `bricks_template` posts and breaks "Edit with Bricks".

## Blank canvas / full-bleed

Bricks already renders wide by default; full-bleed is mostly about removing
chrome:

```json
{"post_id": 17, "settings": {
  "headerDisabled": true,
  "footerDisabled": true,
  "contentMargin": {"top": 0, "right": 0, "bottom": 0, "left": 0}
}}
```

- `siteLayout` is already `wide`; set it only if a theme style or previous
  save forced `boxed`.
- `contentMargin` is a spacing object (`top/right/bottom/left`, optional
  `unit`); confirm with `bricks-list-settings key=contentMargin`.
- Shapes are not guessable from the intent: `bodyClasses` and the
  `customScripts*` / `customCss` keys are single strings, `contentMargin` is
  an object, and `metaRobots` is a list. Read the control before writing an
  unfamiliar key — `novamira/bricks-list-settings scope="page"` gives the
  type of each. A single-value key written as a list is refused rather than
  saved, because Bricks would fatal on it at render time.
- There is no `hideTitle` key: Bricks pages do not inject a theme title
  banner. A visible title comes from your first element.
- Try header/footer off + zero margin before touching `customCss`.

## Intent → keys

| Intent | Keys |
| --- | --- |
| Disable site header / footer | `headerDisabled: true` / `footerDisabled: true` |
| Disable popups on this page | `popupDisabled: true` |
| Zero the content margin | `contentMargin: {top:0,right:0,bottom:0,left:0}` |
| Force boxed / wide layout | `siteLayout: "boxed"` / `"wide"` |
| Constrain container max width | `containerMaxWidth: "1200px"` (a number control with a unit — one value, not an object) |
| Body classes | `bodyClasses: "landing dark"` — ONE space-separated string, never a list: Bricks splits this value on spaces when the page renders, so a list fatals the front end |
| `<head>` / opening `<body>` / closing `<body>` scripts | `customScriptsHeader` / `customScriptsBodyHeader` / `customScriptsBodyFooter` |
| Page-scoped custom CSS (last resort) | `customCss` |
| Disable lazy loading on this page | `disableLazyLoad: true` |

The real keys are `headerDisabled` / `footerDisabled` — not `noHeader` /
`noFooter`; there is no `customJs`.

## Templates and conditions

Headers, footers, popups and section templates are `bricks_template` posts
created with `novamira/bricks-create-template` (not `create-post`), with
their elements written to the matching area (`header` / `footer` / `content`).

**A template with no conditions never renders** — headers and footers
silently disappear. Learn the condition grammar with
`novamira/bricks-list-template-condition-schema` (worked examples per `main`
value), then `novamira/bricks-set-template-conditions` with at least one
condition — `[{"main": "any"}]` for the whole site. Read existing conditions
with `novamira/bricks-list-template-conditions`; the same array shape applies
to theme-style conditions.

Before creating a header or footer, check `bricks-list-templates`: reuse or
edit the existing one rather than adding a second template with overlapping
conditions.

The template root must not carry the `header` / `footer` tag: the template
area already emits the landmark, so a root `section` tagged `header`
renders `<header><header>`. Keep the root a `section` (or `div`) with its
default tag. Templates are removed with `novamira/delete-post`.

## Permalinks

`create-post` accepts a slug, but the displayed URL depends on the site's
permalink structure. With plain permalinks the page resolves at `?page_id=N`
and `/<slug>/` does not exist — verify `permalink_structure` (or note the
caveat) before handing a URL to the user.
