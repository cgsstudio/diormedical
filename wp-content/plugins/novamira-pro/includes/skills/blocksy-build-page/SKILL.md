---
name: blocksy-build-page
description: Inspect and build on a Blocksy-themed site — check the theme/companion setup, read the global color palette, discover customizer options (layout, typography, blog/archive surfaces) with correct default resolution, change customizer settings, typography (Google/system fonts, weights, responsive sizes) or palette colors with validated writes that refresh Blocksy's dynamic CSS automatically, set PER-PAGE Blocksy options on one post/page/CPT entry (hide the page title band, full-width structure, no header/footer, no content spacing, custom hero) without touching global settings, inspect or restructure the header/footer builder (place, edit and remove items like logo, menus, text, buttons, socials across header rows/placements and footer columns), manage Blocksy Companion extensions (list free/Pro extensions, enable/disable them, read their dashboard settings), and manage Companion Pro Content Blocks (list/create/delete hook, popup, 404, header, footer, archive, single, nothing-found and maintenance blocks, edit their display conditions and hook positions). Activate when the user asks to inspect or configure a Blocksy site, audit or change its palette / typography / layout settings, make one page look different (landing page without title/header/footer, boxed or narrow content, custom page title), edit its header or footer, enable or disable a Companion extension, create or target a Content Block (hooks, popups, custom 404/header/footer/maintenance templates), or prepare a page build on a site running the Blocksy theme (with or without Blocksy Companion / Companion Pro).
---

# Building on a Blocksy site

Blocksy pages are standard WordPress pages; the visual stack is governed by
two layers the agent can currently introspect:

1. **Blocksy customizer options** — nearly every setting (palette,
   typography, layout widths, header/footer builder trees, blog/archive
   cards) is a `theme_mod`, all living in ONE wp_options row:
   `theme_mods_<stylesheet>`. The exception: the WooCommerce settings
   Blocksy re-exposes in its customizer (checkout field toggles, the
   privacy-policy / terms page pickers, demo-store notice, catalog
   columns/rows, thumbnail cropping, catalog ordering, shop/category
   display, single image width) plus core's `wp_page_for_privacy_policy`
   are declared `setting.type=option` and live in their OWN wp_options row
   — `blocksy-list-options` rows, `blocksy-get-option` and
   `blocksy-edit-option` all report `storage: option` for those, and the
   read/write goes to that row (scalars come back as strings there).
2. **Per-post Blocksy options** — the editor's "Blocksy Settings" panel,
   stored as ONE post meta (`blocksy_post_meta_options`, an array of
   option id → value) per post/page/CPT entry: page-title/hero mode,
   page structure, content style/spacing, element switches
   (header/footer/featured image/…). Same rule as the customizer:
   defaults are never stored, an absent key means "inherit".
3. **Blocksy Companion** (free or Pro plugin) — extensions, Content Blocks,
   dynamic data. Extensions and Content Blocks are both covered by the
   abilities below.

The current integration surface is discovery + customizer writes + per-page
options + the header/footer builder + companion extensions + Content Blocks:

| Ability | Use for |
|---|---|
| `novamira/blocksy-check-setup` | First call, always |
| `novamira/blocksy-get-color-palette` | Global palette with default resolution |
| `novamira/blocksy-list-options` | Progressive option-schema discovery |
| `novamira/blocksy-get-option` | One option: effective value + default + choices |
| `novamira/blocksy-edit-option` | Set one customizer option (validated, siblings preserved) |
| `novamira/blocksy-edit-color-palette` | Change palette slots per-slot (unmentioned slots keep their value) |
| `novamira/blocksy-get-page-options` | One post/page/CPT entry's per-post Blocksy options (hero, structure, spacing, switches) with default resolution |
| `novamira/blocksy-edit-page-options` | Merge-edit per-post options on ONE entry (validated against that post's own schema; other keys preserved) |
| `novamira/blocksy-get-header` | Header builder snapshot; with `item` → one item's settings + option schema |
| `novamira/blocksy-get-footer` | Footer builder snapshot (columns model); same `item` drill-down |
| `novamira/blocksy-list-builder-items` | Available item types per builder, rules, what's placed where |
| `novamira/blocksy-add-builder-item` | Place an item into a header placement / footer column |
| `novamira/blocksy-edit-builder-item` | Merge-edit one placed item's (or row's) settings |
| `novamira/blocksy-delete-builder-item` | Remove a placed item (settings cleaned with the last placement) |
| `novamira/blocksy-list-extensions` | Companion extension catalog: free/pro, enabled, available, has_settings |
| `novamira/blocksy-enable-extension` | Enable one extension (Companion's own path, verified persist) |
| `novamira/blocksy-disable-extension` | Disable one extension (state only — settings/content survive) |
| `novamira/blocksy-get-extension-settings` | One extension's dashboard settings with its own defaults |
| `novamira/blocksy-list-content-blocks` | Content Blocks inventory: type, status, enabled, condition + position summaries |
| `novamira/blocksy-get-content-block` | One block: full conditions, hook position, content pointer |
| `novamira/blocksy-create-content-block` | New block with template type, optional conditions + position (draft by default) |
| `novamira/blocksy-edit-content-block-conditions` | REPLACE a block's display-condition list (validated against the live grammar) |
| `novamira/blocksy-edit-content-block-position` | Merge-edit a hook block's location/priority/extra locations |
| `novamira/blocksy-delete-content-block` | Trash (default) or force-delete a block |

## First call: check setup

Always start with `novamira/blocksy-check-setup`. It reports:

- `theme.active` / `theme.version` / `theme.min_satisfied` (floor 2.1.47)
  plus `is_child_theme`, `template`, `stylesheet`
- `companion.active`, `companion.pro_code_present` and `companion.licensed`
  — these can diverge: a Pro zip can be installed with an inactive license.
  Gate Pro-only expectations (Content Blocks, dark mode, custom sidebars,
  mega menu) on `licensed=true`, not on `pro_code_present`.
- `extensions` — companion extension slugs the user activated
- `storage.mods_key` — the live theme_mods row. **With a child theme this is
  `theme_mods_<child-slug>`**, and the parent row is dormant.
- `storage.pending_migrations` — the stored Blocksy database version is behind the theme version (read from the per-stylesheet version map when the theme keeps one, otherwise from the legacy single-value row)
  version means option shapes may be transitional; prefer re-checking later.
- `warnings[]` — human-readable divergences worth surfacing to the user.

If `theme.active=false` or `theme.min_satisfied=false`, stop and surface the
gap — the other blocksy-* abilities will refuse with `bly_theme_inactive`.

## The one rule that changes everything: defaults are never stored

Blocksy **never persists defaults**. A fresh install's theme_mods row has no
`colorPalette`, no `rootTypography`, no layout keys at all — an absent key
means "the schema default applies", not "unset". The read abilities resolve
this for you:

- `blocksy-get-option` returns `has_stored_value` alongside the effective
  `value` (stored when present, else the schema default from the theme's
  `inc/options/` definitions).
- `blocksy-get-color-palette` tags every color `stored` or `default`.

Never interpret `has_stored_value=false` as "no value" — the `value` field
is still the truth of what renders.

## Option discovery workflow

The schema is ~650 real options — filter, don't page through everything:

1. `novamira/blocksy-list-options` with no input → section overview
   (`General`, `Colors`, `Typography`, `Blog Posts`, `Single Post`, …with
   counts).
2. `novamira/blocksy-list-options section=<name>` (or `search=<term>`) →
   option rows `{id, label, type, section, storage}` (`storage` is
   `theme_mod` or `option` — see above).
3. `novamira/blocksy-get-option id=<id>` → effective value, schema default,
   `choices` (valid values for enum-like controls).

Useful anchors: `maxSiteWidth` (layout), `colorPalette` (palette),
`rootTypography` (base font), `header_placements` / `footer_placements`
(builder trees — large; get-option caps oversized values with a marker).

## Reading the palette

`novamira/blocksy-get-color-palette` returns 8 slots (`color1`..`color8`),
each with its CSS custom property (`--theme-palette-color-N`). Derived
globals (`fontColor`, `linkColor`, `headingColor`, …) default to
`var(--theme-palette-color-N)` references, and the same variables reach
Gutenberg's editor palette — so a slot is the semantic unit to reason about
when the user asks about brand colors.

## Changing settings

`novamira/blocksy-edit-option` sets one option; `novamira/blocksy-edit-color-palette`
changes palette slots. Recipes:

- **Change a setting**: `blocksy-get-option id=<id>` (learn the value shape,
  `choices` and `storage`) → `blocksy-edit-option id=<id> value=<new>`. The
  write replaces that option's value only — every sibling theme_mod is
  preserved (or, for `storage: option` ids, only that id's own wp_options
  row changes) — and unknown ids or shape-mismatched values are rejected
  with nothing written. ct-switch options take `yes`/`no` or booleans (coerced to the
  vocabulary the option declares — a few WooCommerce switches store literal
  booleans). Slider/number values must sit inside the option's declared
  min/max, and unit-bearing slider strings must be number+unit from the
  declared units (e.g. `"20px"`); out-of-range or non-grammar values are
  rejected, never clamped.
- **Recolor the brand**: `blocksy-get-color-palette` → pick the slots →
  `blocksy-edit-color-palette colors={"color1": "#0f766e", ...}`. Only the
  slots you pass change; the rest keep their stored or default color, and
  legacy bookkeeping keys in a stored palette survive untouched. Prefer
  editing slots over hardcoding colors elsewhere — derived globals
  (fontColor, linkColor, headings, buttons) reference the slots via
  `var(--theme-palette-color-N)`, so one slot edit recolors everything
  bound to it.

Both writes run Blocksy's full refresh sequence automatically, the same one
a customizer save triggers: merge into the `theme_mods_<stylesheet>` row →
wipe Blocksy's theme-mod memoization (`Blocksy\Database::wipe_cache()`) →
`do_action('blocksy:dynamic-css:refresh-caches')`, which regenerates the
dynamic-CSS transient (theme) and rewrites the `uploads/blocksy/css/*.css`
file cache (companion). No manual cache step is needed for the change to
reach the front end; only external page caches (WP Rocket, LiteSpeed, …)
remain the site's own concern. Writes are serialized under a cross-request
lock with a fresh re-read, so concurrent edits do not clobber each other —
and they fail closed: if the lock cannot be acquired the write returns
`bly_lock_unavailable` (retry shortly) instead of running unlocked, and
persistence is verified by re-reading the row: `bly_write_failed` means
nothing changed (the prior state was re-established and verified), while
the rare `bly_rollback_failed` means the stored option may now differ
from both the requested and the prior value — read it back with
blocksy-get-option before proceeding. Values pass two validation gates —
schema-derived typed checks per leaf, then Blocksy's own per-control
sanitizer (`bly_sanitizer_unavailable` if that sanitizer cannot run).
Choice vocabularies resolved from live site content (the nav-menu picker,
page pickers, content-block pickers) are re-resolved and re-checked
INSIDE the write lock right before the persist: `bly_choice_stale` (409)
means the entity behind your value was deleted or emptied between
validation and the write — nothing was written; re-read the choices and
retry with a currently valid value. Static enums never trigger this.

## Typography

Typography is a set of `ct-typography` customizer options, all in the
`Typography` section of `blocksy-list-options`:

| Option id | Styles |
|---|---|
| `rootTypography` | Base font (`:root` — body text, everything that inherits) |
| `h1Typography` … `h6Typography` | Headings |
| `buttons` | Buttons (`--theme-button-font-*` variables) |
| `quote`, `pullquote`, `pre` | Blockquote, pullquote, preformatted |
| `font_family_fallback` | The generic family appended after every non-system font (`Sans-Serif` by default) |

Header/footer builder items (menu, text, button) and many blog/archive
cards carry their own `*Font` typography settings — same shape, edited
through `blocksy-edit-builder-item` / `blocksy-edit-option`.

**Shape** (read any of them with `blocksy-get-option` first):

```json
{"family": "Inter", "variation": "n6",
 "size": {"desktop": "40px", "tablet": "32px", "mobile": "26px"},
 "line-height": "1.2", "letter-spacing": "-0.02em",
 "text-transform": "none", "text-decoration": "none"}
```

- `family` — a Google Fonts family name exactly as Google spells it
  (`Inter`, `Playfair Display`, `Lora`), one of the theme's system fonts
  (`System Default`, `Arial`, `Verdana`, `Trebuchet`, `Georgia`,
  `Times New Roman`, `Palatino`, `Helvetica`, `Myriad Pro`, `Lucida`,
  `Gill Sans`, `Impact`, `Serif`, `monospace`), or `Default` (= inherit
  from `rootTypography`). Google families are detected automatically from
  the theme's bundled Google Fonts catalog: the front end gets a
  `fonts.googleapis.com/css2?family=…` link carrying every family +
  weight in use, and the generated CSS sets `--theme-font-family: Inter,
  Sans-Serif` (fallback appended) for the selector. A misspelled family is
  stored verbatim and renders the fallback stack — no error — so check the
  spelling against fonts.google.com. System fonts are never requested
  from Google.
- `variation` — weight + style as one token: `n` normal / `i` italic
  followed by the weight digit: `n4` = 400 regular, `n7` = 700 bold,
  `i7` = 700 italic, `n1`…`n9`. `Default` inherits the base font's.
  The weight must exist for that Google family or the browser synthesizes it.
- `size`, `line-height`, `letter-spacing` — a scalar or a
  `{desktop, tablet, mobile}` map (send all three devices when you use a
  map). `size` takes number+unit (`px`, `em`, `rem`, `%`, `vw`… — the
  same grammar the option default uses; `18 px` or `clamp(...)` are
  refused), `line-height` a unitless number or unit value, `letter-spacing`
  `em`/`px`. `CT_CSS_SKIP_RULE` is the legitimate "emit nothing" sentinel
  the defaults use for untouched properties — leave it where you find it.
- `text-transform` — `none|uppercase|capitalize` (the customizer UI's
  set; the value is emitted verbatim as `--theme-text-transform`, so
  `lowercase` works too); `text-decoration` — `none|underline|line-through`.

**Partial objects are completed for you**: `blocksy-edit-option
id=h2Typography value={"family":"Lora","variation":"i7"}` changes only
the family and weight — the missing keys are filled from the option's
CURRENT effective value (stored, else the schema default; a legacy or
half stored object is topped up from the schema default first), so a
sequence of partial edits builds up, and the theme never gets a half
object (its CSS output reads every key and would warn). A responsive
`size` / `line-height` / `letter-spacing` map is completed per device
too: `{"size": {"desktop": "44px"}}` keeps tablet/mobile at what renders
today (the current map's devices, or the current scalar on every device),
and a `{"tablet": "31px"}`-only map gets its desktop (then mobile) from
the same source — the stored map never lacks a device; pass all three
devices when you want them to change. The completion
reads the stored value INSIDE the write lock, so a concurrent customizer
save is never overwritten with stale keys. The response `value` shows
the complete object that was written.

Recipes (all verified write → front-end render):

- **Brand font pair**: `rootTypography {"family":"Inter","variation":"n4","size":"17px","line-height":"1.7"}`
  then `h1Typography`…`h3Typography` with the display family
  (`{"family":"Fraunces","variation":"n6","size":{"desktop":"56px","tablet":"44px","mobile":"34px"},"line-height":"1.1","letter-spacing":"-0.02em"}`)
  and `buttons {"variation":"n6","text-transform":"uppercase","letter-spacing":"0.04em"}`.
  Each write refreshes the dynamic CSS — the Google Fonts link and the
  `--theme-font-*` variables are live on the next page load.
- **Responsive heading scale**: give `h1`/`h2` a three-device `size` map
  — the theme emits the tablet/mobile values inside its media queries.
  Block-level hero headings in page content can still carry their own
  sizes (see the landing-page notes).
- Typography inside ONE page's hero band (`pageTitleFont`, `pageMetaFont`,
  `pageExcerptFont`) is a per-page option — see the next section.

## Per-page options (the editor's "Blocksy Settings" panel)

Every post, page and Blocksy-supported CPT entry has its own override
panel; its keys are resolved per post from the theme's own per-post
option files, so **read first**: `blocksy-get-page-options post_id=<id>`
(pass `ids=[…]` to keep the response small). The response says which
`schema` applies and lists every key with `value`, `source`
(`stored`|`default`), `choices` (+ `choice_labels` for opaque values) and,
for stored keys, the `default` it overrides. Writes go through
`blocksy-edit-page-options post_id=<id> options={…}` — **merge semantics**:
only the keys you pass change, every other stored key is preserved
byte-for-byte (including keys the schema does not model — Companion
keys, listed under `extra_stored_keys` / `preserved_keys` by name). The
one exception is the theme's own `styles_descriptor` CSS cache: it is
NOT preserved but regenerated on every effective write (see below) —
never write it yourself. `has_meta_row` says whether ANY
`blocksy_post_meta_options` row exists for the post; `has_stored_options`
whether it holds at least one key (a row holding an empty array reads
`has_meta_row=true, has_stored_options=false`).

Schemas (all keys come from the installed theme — these are the 2.1.52
anchors):

- **page** and **post** — the page-title block (`has_hero_section`:
  `default` inherit | `enabled` custom | `disabled` hidden; when
  `enabled`: `hero_section` type-1/type-2, `hero_elements` layers,
  alignment, `hero_height`, backgrounds, `pageTitleFont`,
  `pageTitleFontColor`, padding…), structure (`page_structure_type`:
  `default` inherit | `type-4` normal width, no sidebar | `type-3`
  narrow | `type-2` left sidebar | `type-1` right sidebar),
  content style (`content_style_source` inherit|custom →
  `content_style` wide|boxed, responsive map allowed; design tab:
  `background`, `content_background`, `boxed_content_spacing`,
  `content_boxed_radius/border/shadow`), vertical spacing
  (`vertical_spacing_source` inherit|custom → `content_area_spacing`
  both|top|bottom|none), element switches (`disable_featured_image`,
  `disable_share_box`, `disable_header`, `disable_footer`; posts add
  `disable_post_tags`, `disable_author_box`, `disable_posts_navigation`,
  `disable_related_posts`). Page-only vs post-only keys are enforced —
  `disable_author_box` on a page is an unknown key.
- **default** — Blocksy-supported public CPTs (everything public and
  non-builtin minus the theme's deny list): the post schema shape.
  `product`, attachments and builder/plugin CPTs have NO per-post options
  (refused with a clear message); Content Blocks are redirected to the
  content-block abilities, the Companion's WooCommerce-extra builders
  (`ct_thank_you_page`, `ct_product_tab`, `ct_size_guide`) are refused.
- **integration** — post types a Blocksy integration registers for the
  editor panel through `blocksy:editor:post_types_for_rest_field` and
  gives a schema through `blocksy:editor:post_meta_options` (The Events
  Calendar's `tribe_events` on 2.1.52): the filter-provided key set,
  resolved exactly like the editor resolves it.
- **blog** — the posts page and the WooCommerce shop page accept ONLY
  `disable_header` / `disable_footer` (the theme drops every other key
  there at read time too). `special` tells you when that applies.

Values: ct-switch keys take `yes`/`no` or booleans (written as yes/no);
finite choices are enforced; slider values must sit inside the declared
range/units (`hero_margin` `"40px"` ok, `"900px"` refused);
responsive maps are completed (tablet ← desktop, mobile ← tablet);
`hero_elements` is a list of `{id, enabled, …}` layer rows — `enabled`
is a required boolean, ids must be layers the control knows
(`custom_title`, `custom_description`, `custom_meta`, `breadcrumbs`, …)
and every nested setting is validated against that layer's own
declaration (`heading_tag` only `h1`…`h6`, sliders inside their range,
nested `meta_elements` layers recursively; unknown settings are refused,
a layer may repeat only when the UI lets you clone it and only up to the
UI's own cap — `clone: true` = 2 rows in total, `clone: N` = N+1, so
`custom_meta` twice, `content-block` up to six times; the `custom_title`
layer's `custom_attribute` is a space-separated `name=value` list whose
names must be `id`, `class`, `title`, `role`, `lang`, `dir`, `data-*` or
`aria-*` — `onclick=…`, `style=…`, `href=…` and malformed/quoted names
are refused because the theme writes the names unescaped into the title
tag; link settings of header items (socials `custom_url`, contacts/
account `link`) refuse `javascript:`/`data:` schemes);
read the current list and send it back with your changes. Typography
keys are completed like the global ones; every value passes Blocksy's
own post-meta sanitizer — a string carrying `<` or `>` is refused, never
blanked. Any invalid key or value means nothing is written.

After a verified persist the theme's per-post CSS cache
(`styles_descriptor`) is regenerated exactly like an editor save
(`styles_descriptor: regenerated`), so the change renders on the next
front-end request — no cache step needed (external page caches aside).

Recipes:

- **Hide the page title on one page**:
  `blocksy-edit-page-options post_id=<id> options={"has_hero_section":"disabled"}`.
- **Custom hero on one page** (the rest inherit): `{"has_hero_section":
  "enabled", "hero_section": "type-2", "hero_height": "420px",
  "hero_alignment2": "center", "pageTitleFont": {"family": "Fraunces",
  "variation": "n6", "size": {"desktop": "56px", "tablet": "44px",
  "mobile": "34px"}}}` — read `hero_elements` first if you want to drop
  the excerpt/meta layers (send the whole list back with `enabled` flipped).
- **Full-width, no chrome (landing page)**: `{"has_hero_section":
  "disabled", "page_structure_type": "type-4", "vertical_spacing_source":
  "custom", "content_area_spacing": "none", "disable_header": "yes",
  "disable_footer": "yes"}` — one call, this page only.
- **Narrow reading layout for one post**: `{"page_structure_type":
  "type-3", "content_style_source": "custom", "content_style": "boxed",
  "disable_share_box": "yes", "disable_author_box": "yes"}`.
- **Revert a page to inherit**: write the `default` / `inherit` / `no`
  choice back (`{"has_hero_section": "default"}`) — the key stays stored
  with the inherit value, which renders exactly like an absent key.

## Header & footer builder

The WHOLE header builder state is ONE theme_mod (`header_placements`), the
footer likewise (`footer_placements`) — placements AND per-item settings
together, defaults never persisted. The header stores per-device trees
(`desktop`/`mobile` → rows `top-row|middle-row|bottom-row|offcanvas` →
placements `start|middle|end|start-middle|end-middle`; offcanvas has only
`start`); the footer stores per-row column lists. Don't hand-edit these
trees through `blocksy-edit-option` — use the builder abilities, which
validate targets and rules and keep the structure consistent.

**Inspect:** `blocksy-get-header` / `blocksy-get-footer` show the structure
and which section renders (`rendered_section` — with Companion Pro header
conditions this can differ per page; `current_section` is only UI
bookkeeping). Re-run with `item=<instance id>` (or a row id like
`middle-row`) to see that item's stored settings plus its option schema
(ids, types, defaults, choices) — that's the key list `edit-builder-item`
accepts. `choices` are the exact values a setting accepts (pass them
verbatim); for dynamic sources like the menu picker they are resolved
live, and a `choice_labels` map translates opaque values to names (e.g.
nav-menu term id → menu name). `blocksy-list-builder-items
builder=header|footer` enumerates the placeable types with their rules
(`devices`, `allowed_in`/`excluded_from` row rules, `clone`, `enabled`)
and everything currently placed.

**Modify safely:**

- `blocksy-add-builder-item` places a type into a target
  (header: device+row+placement; footer: row+column, 1-based). It enforces
  the customizer's own rules and duplicate policy: one instance of a
  non-clone item per header device tree / footer section; placing the same
  id in the OTHER header tree reuses the shared instance (like the default
  logo — same settings on both devices); clone items get a fresh
  `type~hash` instance automatically (`duplicate=true` forces one), capped
  at the customizer's own `(clone||1)+1` total-instance limit (button:
  clone true → 2; text: clone 9 → 10 — read `clone_limit` and `instances`
  from list-builder-items). A fresh clone starts as a settings copy of the
  base instance, exactly like the customizer's clone button. Items without
  copied settings render with option defaults.
- `blocksy-edit-builder-item` merge-edits settings by instance id — only
  passed keys change. Row settings (heights, backgrounds, sticky) are
  edited the same way with the row id as `item`. Send COMPLETE structured
  values (typography and similar) — read the current shape first; the theme
  tolerates partial structures poorly at render time.
- `blocksy-delete-builder-item` removes placements (optionally one header
  `device` only). The settings entry is dropped with the last placement —
  scope by `device` if you want to keep them. Rows cannot be deleted; empty
  them instead.

All three run the full dynamic-CSS refresh, so header/footer changes reach
the generated CSS and the live pages immediately. HTML-bearing settings
(the text item's `header_text`, the copyright item's `copyright_text`, any
wp-editor control) go through Blocksy's own capability-aware sanitizer for
EVERY instance — including clones and the footer copyright, which the
theme's own literal-id gate misses (script tags survive only for
`unfiltered_html` users, matching the customizer).

## Companion extensions

Extensions are a **Companion** feature (the four abilities refuse with
`bly_companion_inactive` when only the bare theme runs). The catalog is
four free extensions (cookies-consent, newsletter-subscribe,
product-reviews, trending) plus the Pro set (mega-menu, woocommerce-extra,
custom-fonts, sidebars, white-label, …); activation state is one option,
`blocksy_active_extensions`.

- Start from `blocksy-list-extensions`: `enabled` is the stored state,
  `available` says whether enable can work HERE (files present + license
  plan passes the Companion's boot gate). Pro extensions on a free plan
  are listed but `available=false` — enabling them is refused with
  `bly_pro_required`, never stored-but-dormant.
- `blocksy-enable-extension` / `blocksy-disable-extension` run the
  Companion's own activation path (onActivation/onDeactivation hooks +
  dynamic-CSS refresh) and verify persistence with a raw re-read.
  Re-running is a no-op success (`changed=false`). Init-time effects
  (product-reviews' `blc-product-review` CPT, woo-extra features) land on
  the NEXT request; for rewrite-registering extensions the cached rules
  are dropped automatically (`rewrite_rules_flushed=true`) so permalinks
  regenerate correctly. Disabling never deletes the extension's settings
  or content. Watch `warnings` — e.g. woocommerce-extra enabled without
  WooCommerce stays inert.
- `blocksy-get-extension-settings` reads the dashboard-level settings
  (per-extension `blocksy_ext_*` options) with defaults resolved by the
  extension's own accessor: product-reviews (CPT slugs),
  newsletter-subscribe (provider credentials — `api_key` comes back
  redacted; a marker means one IS configured), local-google-fonts,
  custom-fonts, adobe-typekit (readable only while enabled), white-label,
  post-types-extra and woocommerce-extra (feature toggles).
  `has_settings=false` extensions keep their options in the customizer —
  use the option abilities above (e.g. `trending_posts_ext` /
  `cookie_consent_ext` sections). Extension settings are read-only by
  design: each extension persists through its own side-effect chain
  (rewrite flushes, feature boots, font downloads), so writes go through
  the extension's dashboard, not a generic path.

## Content Blocks (Companion Pro)

Content Blocks are **Companion Pro** territory: the whole surface (the
`ct_content_block` CPT included) exists only while the license plan
carries `base_pro` — on a free plan every content-block ability refuses
with `bly_pro_required`. Gate expectations on `companion.licensed` from
check-setup.

A content block is a regular WordPress post plus two metas: a
`template_type` (hook / popup / 404 / header / footer / archive /
single / nothing_found / maintenance) and one options array holding its
display conditions, hook position and design keys.

- **Content is edited elsewhere.** Blocks are posts — read their markup
  with `novamira/gutenberg-get-content` and write it with
  `novamira/update-post` (`content` / `post_content`, the full serialized
  block markup) using the block's post id.
  `novamira/gutenberg-write-content` is NOT a general write path: it only
  accepts registered `novamira/*` dynamic-only blocks and refuses native
  Gutenberg markup — native blocks that need editor-assigned attributes go
  through the pending-change pipeline instead
  (`gutenberg-add-pending-change` → `gutenberg-enable-batch-finalization`
  → send the user the finalization link). `blocksy-get-content-block`
  returns a byte count and pointer, never the content itself.
- **Rendering needs three switches at once:** status `publish`, the
  `enabled` kill switch, and a matching condition tree. A fresh block
  is a draft with no conditions — it renders nowhere until you publish
  it and give it conditions.
- **Conditions are a `{relation, conditions}` descriptor** — the exact
  shape the customizer saves. Rows are `{type: include|exclude, rule,
  payload?}` rules or nested `{relation: AND|OR, conditions}` groups
  (Advanced Mode). Excludes are evaluated within their OWN group's
  relation, and a nested group acts as an include in its parent — root
  `OR(include:everywhere, AND(exclude:everywhere))` still matches,
  because the direct include succeeds. A plain JSON array is
  also accepted on write and persists as the root-OR descriptor, like
  the UI. EMPTY conditions match nowhere — except `maintenance` /
  `nothing_found` blocks, which then default to everywhere. Valid rule
  ids are install-dependent (CPTs, taxonomies, roles, WooCommerce,
  languages); an unknown rule is refused with the valid ids listed.
  ID-based rules take `payload.post_id` / `payload.taxonomy_id`.
- **Condition edits REPLACE the whole descriptor** (the same semantics
  as the customizer modal): to add a rule, send the existing descriptor
  plus the new row. The write is one atomic verified meta update — a
  failure leaves the stored set untouched, never partial.
- **Position is hook-blocks only** and merge-edited per key: `location`
  is a Blocksy hook id (`wp_body_open`, `blocksy:content:before`, …) or
  `custom_hook` + `custom_location` for any WordPress action;
  `priority` 1–100; the count fields drive the virtual locations
  (after-paragraph N, before-heading N, after-card N);
  `additional_locations` renders the same block at extra hooks.
- **Everything lands on the NEXT front-end request** (blocks attach
  their hooks on `wp`) — re-fetch the page to verify, don't trust the
  request that made the change.
- **Delete trashes by default** (recoverable, stops rendering); pass
  `force=true` only for a permanent removal — it also erases the
  block's conditions/position meta.

## Landing-page builds (verified end-to-end)

A full landing page (hero + sections + header/footer + announcement bar)
is buildable through this surface plus the base-plugin post abilities.
Recipes proven by a real build:

- **Create the page with `novamira/create-post`** — pass
  `post_type: "page"` explicitly. The short alias `type` is NOT accepted
  (the input schema rejects unknown keys); the other aliases (`title`,
  `status`, `content`, …) work as usual. Then write the block markup via
  `content`/`post_content` on create, or `novamira/update-post` later.
- **Full-bleed hero prep — per page, not global**: right after creating
  the page, `blocksy-edit-page-options post_id=<id>
  options={"has_hero_section": "disabled", "vertical_spacing_source":
  "custom", "content_area_spacing": "none", "page_structure_type":
  "type-4"}` removes the page-title band and the content-area top/bottom
  padding and forces the normal-width no-sidebar structure on THIS page
  only; add `"disable_header": "yes", "disable_footer": "yes"` for a
  chrome-less landing. Do NOT flip the global `single_page_hero_enabled`
  / `contentAreaSpacing` customizer options for this — they change every
  page on the site (if you find them already flipped from an older build,
  offer to restore them and move the override to the landing page). A
  per-page `has_hero_section: enabled` also works the other way round: it
  shows a custom hero on one page even while the global switch is off.
- **Wire the navigation menu** on the placed header `menu` item:
  `blocksy-get-header item=menu` → the `menu` setting's `choices` list the
  live vocabulary (`blocksy_location` = the theme-location default, plus
  one nav-menu term id per existing menu, named in `choice_labels`) →
  `blocksy-edit-builder-item builder=header item=menu
  settings={"menu": <term id>}`. Only menus that already contain at least
  one item are assignable (Blocksy's picker hides empty menus and its own
  sanitizer refuses them — the error tells you when that is the case).
  There is NO ability for creating menus or menu items yet: if no suitable
  menu exists, create one through the base plugin's `novamira/run-wp-cli`
  (`wp menu create`, `wp menu item add-custom`) when that surface is
  available, otherwise tell the user a menu must be created in
  wp-admin first — don't improvise raw term writes.
- **Responsive headings**: a desktop-sized hero H1 easily wraps badly at
  390px. Give hero headings explicit responsive font sizes in the block
  markup (e.g. a `clamp()` font-size or the block's per-device size
  attributes) instead of relying on the theme's global heading scale, and
  sanity-check the mobile viewport after the build.

## Gotchas

- **Child themes have their own mods row.** Everything read-side is handled
  for you (abilities follow the active stylesheet), but when reporting
  storage facts to the user, quote `storage.mods_key` from check-setup.
- **Responsive shapes vary.** Size-like values may be a scalar or a
  `{desktop, tablet, mobile}` map — both are legitimate for the same option.
- **`CT_CSS_SKIP_RULE`** is a legitimate stored sentinel value, not garbage.
- **Stored palettes may carry legacy keys.** Pre-2.0 shapes kept extra
  bookkeeping inside `colorPalette`; the palette ability filters them and
  lists them under `extra_stored_keys`.
- **Write through the abilities, never ad-hoc `set_theme_mod`.** A raw
  `set_theme_mod()` (via code snippets or generic option abilities) leaves
  Blocksy's theme-mod memoization and dynamic-CSS caches stale — the front
  end keeps rendering the old value. The edit abilities run the full
  refresh sequence for you.
- **`colorPalette` is deliberately blocked in `blocksy-edit-option`** — use
  `blocksy-edit-color-palette`, which validates colors and merges per slot
  instead of replacing the whole structure.
- **Per-page options are per POST, not per URL.** The posts page and the
  shop page take the `blog` schema (header/footer switches only); a
  page that becomes the posts page later keeps its stored keys but the
  theme ignores all but those two. `styles_descriptor` in a post's meta is
  the theme's CSS cache — never write it, the edit ability manages it.
- **Typography objects need every key** — write through the abilities
  (they complete partial objects from the current value); a raw
  `set_theme_mod('h2Typography', ['family' => 'Lora'])` makes the theme
  warn on every CSS regeneration and drop the other properties.
