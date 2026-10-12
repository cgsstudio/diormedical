---
name: happy-addons-elementor
description: Activate when working with Happy Elementor Addons on a WordPress site — also called HappyAddons, Happy Addons, Happy Addons Pro or Happy Elementor Addons Pro, from Leevio. Covers its widgets, whose Elementor type starts with ha- (ha-infobox, ha-creative-button, ha-advanced-slider…), shipped as a free edition and a paid one that register under two sources and two categories of their own; the ha_ controls its effects graft onto Elementor's own elements; and the theme templates and mega-menu panels it keeps as Elementor documents.
---

# Happy Addons in Elementor

## Boundaries

- Composing a page, styling it, and choosing between v3 and v4 belong to the
  `elementor-build-page` skill. These widgets are v3, so place them inside a v3
  `container`.
- Elementor's dynamic tags and query loops belong to `dynamic-data-binding`. This
  plugin brings no tags of its own, so work from the list
  `novamira/elementor-list-dynamic-tags` returns for the site.
- Which widgets are switched on, which effects are switched on, the paid
  edition's licence and the service keys a few widgets read are all edited under
  the plugin's own top-level menu in wp-admin, across its Widgets, Features,
  Credentials, Analytics and License screens. Point the user at the one that
  matters and leave the change to them, and let a service key be typed there
  rather than handed to an agent.

## Finding its widgets: take both editions

The two editions install side by side and their widgets add up, and each
registers under a grouping of its own. A filter naming one of them returns a
full, plausible list that is a fraction of what the site offers, so take the
pair every time:

- **Two categories**, `happy_addons_category` for the free edition and
  `happy_addons_pro_category` for the paid one. Note the **underscores**: a slug
  composed with hyphens matches nothing.
- **Two sources**, `happy_addons` and `happy_addons_pro`, one per edition.
  Filtering by source is reliable here, unlike most addons: these classes carry
  namespaces of their own, so the source filter separates them cleanly from the
  core widgets and from every other plugin on the site.
- **The widget name prefix `ha-`**, carried by every widget of both editions with
  no exception, and a good `name_contains` for a keyword search.

A `novamira/elementor-get-schema` call with `action: "list"` and no filter names
every source and every category a registered widget declares, so read the pair
off the site in front of you and pass each value back to the same ability with
the same `action`. That parameter is required on every call to it and a call
omitting it is refused, so carry it even when the filter is the only thing you
are changing. A third grouping,
`happy_addons_theme_builder`, is a second label on part of the free edition
rather than a set of its own — every widget in it also carries the free category,
so the two categories above already hold the whole product.

How to query the registry, read a schema or compose a tree is not described
here: that belongs to `elementor-build-page` and to each ability's own
description.

## Answer "does this widget exist here?" from the registry

Placing a widget takes it being registered, and on a site running this plugin a
name that plainly ought to be there often is not. Read the registry every time,
say plainly when a name quoted from the editor panel or from the plugin's
documentation comes back unregistered, and read the state to say **why**:

- **The paid group is drawn in the editor and holds nothing.** That is the
  licence: with the paid plugin active and its licence not activated, its widgets
  never reach the registry while its category is still registered and its effects
  still graft onto elements. Distinguish it from the paid plugin being absent
  altogether, where the category is gone too.
- **Nothing at all is registered, not one category, with the paid plugin
  active.** The paid edition is not self-standing — it registers through the free
  one and stops early when the free plugin is not active — so look first at
  whether the free plugin is active.
- **A widget is switched off on the plugin's Widgets screen.** Elementor never
  sees it: no schema, no insertion. Its key on that screen is the widget name
  without the `ha-` prefix, and one key can cover the same widget in both
  editions. The Analytics screen also offers to switch off in one go every widget
  the site is not currently using, so a large part of the catalogue can be off
  without anyone having chosen widget by widget.
- **A widget that is already in a saved page.** With its switch off, the editor
  drops it from the canvas and from the structure panel while the saved document
  still holds it. Read that gap as a widget out of the registry rather than as
  content to put back: rebuilding it and saving is what would genuinely lose it.

The switched-off widgets live in one option row, `happyaddons_inactive_widgets`,
and it holds the keys that are **off** — an absent or empty row means every
widget is on. Reading it with `novamira/execute-php` answers the switch question
directly; guard on `HAPPY_ADDONS_VERSION` first, so that a site without the
plugin says so instead of reporting that everything is on.

**Switching a widget on or off is the user's job on that screen**, and the shape
of the row is why: it names what is off, not what is on, so a write naming the
single widget to disable switches every other disabled widget back on in the same
stroke.

## Switching a widget does not change the published page on its own

Elementor keeps the last render of a document and serves it to visitors, so the
live page and the editor disagree for a while after any of these switches move: a
widget switched off is still on the published page, and a widget switched back on
leaves it still missing. Refresh the document's saved render with
`novamira/elementor-clear-document-cache` before reading the front end as
evidence, and before concluding from a page that still looks wrong that the
diagnosis was wrong.

## Call a widget what Elementor calls it

Titles are marketing labels and names are terse, so composing one from the other
is what fails: *Team Member* is `ha-member`, *Info Box* is `ha-infobox` while the
neighbouring *Icon Box* is `ha-icon-box`, *Advanced Accordion* is `ha-accordion`,
*Advanced Toggle* is `ha-toggle`, *X Feed Carousel* is `ha-twitter-carousel`,
*Pie & Doughnut Chart* is `ha-pie-chart`, *Single Image Scroll* is
`ha-image-scroller`. A family of the paid edition's post and store widgets ends
in `-new` with nothing in the title to suggest it — *Post Grid* is
`ha-post-grid-new`, *Product Grid* is `ha-product-grid-new` — and the two menu
widgets cross over: the free *Nav Menu* is `ha-navigation-menu` while the paid
*Happy Menu* is `ha-nav-menu`.

So when a user quotes a label, list the widgets by category, find the entry whose
title matches, and take the registry name from that listing. Use that registry
name only where the target ability's input schema documents a widget identifier,
whether at the top level or inside a nested widget element such as `set-content`'s
`content` or `add-element`'s `tree`; if it documents none, do not add the name.
The family schemas show top-level `widget_type` and `widget_types`; inside
`set-content`'s `content` a widget element takes `widget_type` or `widgetType`,
and inside `add-element`'s `tree` it takes `widgetType`.

## Much of what it adds to a page is not one of its widgets

Its effects graft controls onto Elementor's own elements, native and third-party
alike: floating and transform effects, a wrapper link, equal height, particles,
background parallax, a global badge, a custom mouse cursor, display conditions.
They carry the prefix `ha_`, spelled with an **underscore** where the widget
names use a hyphen, and Elementor returns them from the host element's schema
together with the native controls. So a request for one of those behaviours
belongs on the element the user is pointing at, not on a Happy widget — and they
stay grafted even where the paid licence leaves the paid widgets unregistered.

The Features screen switches these on and off as a separate list from the
widgets, so an effect can be missing from an element whose widgets are all
present.

## A widget that fronts another plugin registers either way

Its widgets for forms, stores and downloads are in the registry whether or not
the plugin each one fronts is installed, so being able to place one says nothing
about being able to render one. The tell is in the schema and it differs by
widget: some come back with their own content controls replaced by a single
notice, others keep every control and print the notice only when the page
renders. Read either as a missing host plugin rather than as a broken widget.

The widgets that call an outside service — the map, the social feeds, the
sheet-backed data table — read their key from the plugin's Credentials screen,
and without it they render empty instead of failing. Most of them also carry a
`credentials` control choosing between that shared key and one typed on the
widget itself, and it **defaults to the per-widget one**: a widget inserted
without touching that control ignores the key saved for the site and comes out
blank. Set it to `global` to use the site's key. The map widget has no such
control and takes the site's key only.

## Its templates and mega-menu panels are Elementor documents

The free edition keeps headers, footers, single and archive layouts as posts of
type `ha_library`, and the paid edition keeps each mega-menu panel as a post of
type `ha_nav_content`. Both are ordinary Elementor documents, so once you have a
post id the Elementor abilities compose and edit one the way they do a page.
That is where the contents of a mega-menu dropdown live — not in the page that
shows the menu and not in the menu item — and it is worth saying so before
anyone goes looking in the page. No ability lists posts by type, so
`novamira/execute-php` is the way to those ids. Where each template is displayed
is set on the plugin's own screen, and that part stays with the user.
