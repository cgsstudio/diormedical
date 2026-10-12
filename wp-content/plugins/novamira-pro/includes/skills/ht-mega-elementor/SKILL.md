---
name: ht-mega-elementor
description: Activate when working with HT Mega on a WordPress site — also called HTMega, HT Mega Addons or HT Mega Pro, from HasThemes, shipped as a free edition and a paid one that install side by side. Covers its widgets, whose Elementor type starts with htmega- (htmega-postgrid-addons, htmega-imagemarker-addons…), grouped in the editor under categories of its own; the htmega_ controls its modules graft onto Elementor's own elements; and the theme templates it keeps as Elementor documents. Also covers what a site running it reports as Elementor's own widgets, since its free edition's widgets are filed there too, and the name to call each widget by.
---

# HT Mega in Elementor

## Boundaries

- Composing a page, styling it, and choosing between v3 and v4 belong to the
  `elementor-build-page` skill. These widgets are v3, so place them inside a v3
  `container`.
- Elementor's dynamic tags and query loops belong to `dynamic-data-binding`. These
  widgets take Elementor's own tags the way any other v3 widget does, so work from
  the list `novamira/elementor-list-dynamic-tags` returns for the site.
- Which widgets and modules are switched on, the paid edition's licence and the
  service keys a few widgets read are edited under the plugin's own top-level menu
  in wp-admin, across its Elements, Sections, Modules and Settings screens. Point
  the user at the one that matters and leave the change to them, and let a service
  key be typed there rather than handed to an agent.

## Finding its widgets: enumerate by category

Its widgets sit under a family of categories whose slugs all begin `htmega`, and
taking the whole family is what returns the whole product. Read that family back
from the site — a `novamira/elementor-get-schema` call with `action: "list"` and
no filter names every category a registered widget declares, that parameter
being required on every call to it and a call omitting it refused — and keep
every slug beginning `htmega`, rather than working from a list learned in
advance: the family grows on its own, one category being named for a year and a
fresh one arriving with each year's collection. It divides the product by
vintage and by marketing rather than by edition — paid widgets turn up under a
category the free edition fills too — so read an edition from the registry, not
from the category a widget sits in.

**Search by widget-name prefix.** Nearly every widget's Elementor type begins
`htmega-`, a good `name_contains` for a keyword search. It is a habit rather than a
rule: one long-standing widget, the `section-title-addons` heading block, carries
no prefix at all, and only the category family holds it.

The two editions install side by side and their widgets add up, but the paid one
is not self-standing: it hands its widgets to the free edition to register them,
so a site running the paid plugin alone offers none of them and no categories
either. When such a site reports nothing at all, look first at whether the free
edition is active.

## The `source` filter reports something else here, in both directions

The free edition declares its widget classes inside Elementor's own PHP namespace,
so Elementor files them under its own source alongside the core widgets. Two
consequences follow, and the second lands on questions that have nothing to do
with this plugin:

- Filtering by this plugin's source returns the paid edition's widgets only — a
  full, plausible list that is a fraction of what the site offers.
- *Which widgets does Elementor itself ship?* comes back with the free edition's
  widgets mixed in. On a site that has HT Mega, answer that by taking Elementor's
  own source and keeping what carries neither an `htmega` category nor the
  `htmega-` name prefix, and tell the user that is what you did.

So enumerate by category, search by prefix, and leave `source` to plugins whose
classes carry a namespace of their own. How to query the registry, read a schema
or compose a tree belongs to `elementor-build-page` and to each ability's own
description.

## Call a widget what Elementor calls it

A widget's editor title, the name Elementor knows it by, and the key it carries on
the plugin's settings screen are three separate strings, and composing one from
another is what fails: *Lightbox* is `htmega-magnific-popup-addons`, *Tabs* is
`htmega-tab-addons`, *Section Title* is `section-title-addons`, *Special Day
Offer* is `htmega-specialdaybanner-addons`, *404 Content* is
`htmega-errorcontent-addons`. Several names are spelled differently from their
title —
*News Ticker* is `htmega-newtsicker-addons` — so a name spelled the obvious way
matches nothing. When a user quotes a label, list the widgets by category, find
the entry whose title matches, and take the registry name from that listing. Keep
the settings key for talking to the user about the panel, and build calls from
the registry name. Use that registry name only where the target ability's input
schema documents a widget identifier, whether at the top level or inside a
nested widget element such as `set-content`'s `content` or `add-element`'s
`tree`; if it documents none, do not add the name. The family schemas show
top-level `widget_type` and `widget_types`; inside `set-content`'s `content` a
widget element takes `widget_type` or `widgetType`, and inside `add-element`'s
`tree` it takes `widgetType`.

## The registry answers what exists; the screens hold the switches

Every widget carries a switch and a site ships with a fair number of them off, so
answer "does this widget exist here?" from the registry every time, say plainly
when a name read off the editor panel or the plugin's own screens comes back
unregistered, and leave the switch to the user. The switches sit on two screens,
one for the long-standing widget list and one for the year collection, so look on
both.

Where such a widget already sits in a saved page, the editor drops it from the
canvas and from the structure panel while the saved document still holds it, so
read a gap there as a widget out of the registry rather than as content to put
back: rebuilding it and saving is what would genuinely lose it.

## A widget that fronts another plugin registers either way

Its widgets for tables, forms, downloads, forums and feeds are in the registry
whether or not the plugin each one fronts is installed, and the tell is in the
schema: with that host absent, such a widget comes back carrying the controls
every Elementor widget has and none of its own, every one of them alike. Read that
shape as a missing host plugin — the widget is placeable and renders nothing —
rather than as a widget with nothing to configure; a few, the shop and calendar
ones, stay out of the registry altogether instead. A widget that calls an outside
service is the other case: it keeps its own controls and renders a line naming the
key it wants and the screen to type it on, so read an empty weather or map widget
as an unsaved key.

## Much of what it adds to a page is not one of its widgets

Its modules graft controls onto Elementor's own elements — a wrapper link on
containers, sections and columns, and parallax, particles, sticky, floating and
conditional-display behaviour as each module is switched on. They carry the prefix
`htmega_`, spelled with an **underscore** where the widget names use a hyphen, and
reading the schema of the element in question reveals them: Elementor returns them
together with the native controls, so a request for one belongs on the host
element.

## Its theme templates are Elementor documents

The plugin keeps its own headers, footers, single and archive layouts as posts of a
type of its own, and those posts are ordinary Elementor documents: once you have a
post id the Elementor abilities compose and edit one the way they do a page. That
post type is registered whether or not the template module is switched on, so
finding it empty says nothing about the module, and no ability lists posts by
type — `novamira/execute-php` is the way to that id.
