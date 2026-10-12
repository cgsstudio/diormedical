---
name: element-pack-elementor
description: Activate when working with Element Pack on a WordPress site — also called Element Pack Pro, Element Pack Lite, EP or bdthemes-element-pack, from BdThemes. Covers its widgets, whose Elementor type starts with bdt- (bdt-post-grid, bdt-advanced-heading, bdt-carousel, bdt-timeline…) together with a lightbox widget that carries no prefix, grouped in the editor under categories of its own; the controls its extensions graft onto Elementor's own elements; the dynamic tag groups it registers; and the theme templates it keeps as Elementor documents of its own. Explains how to find its widgets on any site and the name to call each one by.
---

# Element Pack in Elementor

## Boundaries

- Composing a page, styling it, and choosing between v3 and v4 belong to the
  `elementor-build-page` skill. These widgets are v3, so place them inside a v3
  `container`.
- Elementor's dynamic tags and query loops belong to `dynamic-data-binding`. The
  tags this plugin adds sit in groups whose slugs begin `element-pack-`, and
  which of them a site has turns on its edition, on a feature switch of the
  plugin's own, and on what else is installed. Read the list back with
  `novamira/elementor-list-dynamic-tags`, and take an empty answer as this site
  not offering them rather than as the plugin having none.
- Which widgets and extensions are switched on, and the keys for the services
  some widgets call, are edited under the plugin's own top-level menu in
  wp-admin, across its Core Widgets, 3rd Party Widgets, Extensions and API
  Settings screens. Point the user at the one that matters and leave the change
  to them, and let a service key be typed there rather than handed to an agent.

## Finding its widgets

Reach for the **source**, whose identifier is `elementpack`. Both editions
declare their widgets under one shared namespace, so this single filter returns
everything the plugin registers, whichever edition a site runs. Pass it as
`source` to `novamira/elementor-get-schema`, together with `action: "list"`:
that ability requires `action` on every call and refuses one that omits it,
rather than assuming a listing.

A site can carry both editions at once, and then they do not add up: the paid one
registers the widgets and the free one stands aside, so the site offers one set
rather than two. The paid edition also brings surfaces the free one has not —
further categories, the template post type, most of the extensions — so read back
what the site in front of you actually has instead of taking this whole map for
granted.

Two other filters are ways in rather than ways to enumerate:

- **The widget name prefix `bdt-`**, which almost every widget carries and which
  makes a good `name_contains` for a keyword search. It is a habit of the plugin
  rather than a rule of it: the **`lightbox`** widget carries no prefix at all,
  and neither does the one fronting the FooEvents calendar. Search by prefix,
  enumerate by source.
- **Its categories, of which a site can have several.** The bulk of the widgets
  sit under the slug `element-pack`; the theme-template widgets have their own,
  and a further one comes out only where the plugin it serves is there to fill
  it. Work from the categories a site reports rather than naming one and taking
  its answer for the whole plugin.

The heading the editor draws over these widgets is a title the plugin chooses and
not the category slug: it can name an edition the site is not running, or a name
the site's owner set for it. Match on the slug, and never read the edition off
that heading.

How to query the registry, read a schema or compose a tree is not described here:
that belongs to `elementor-build-page` and to each ability's own description.

## Call a widget what Elementor calls it

A widget's title and the name Elementor knows it by are two strings, and
composing the second from the first is what fails: *Read More Toggle* is
`bdt-toggle`, *Scroll Navigation* is `bdt-scrollnav`, *Simple Contact Form* is
`bdt-contact-form`, *Icon Nav* is `bdt-iconnav`. The plugin's own settings hold a
third string for the same widget, and it does not reliably convert into either of
the other two, so it is not the one to build a call from.

When a user quotes a label, list the widgets by source or by category, find the
entry whose title matches, and take the registry name from that listing. Match
those titles forgivingly: one arrives with a stray leading space, and one with an
HTML entity standing in for a degree sign. Use that registry name only where the
target ability's input schema documents a widget identifier, whether at the top
level or inside a nested widget element such as `set-content`'s `content` or
`add-element`'s `tree`; if it documents none, do not add the name. The family
schemas show top-level `widget_type` and `widget_types`; inside `set-content`'s
`content` a widget element takes `widget_type` or `widgetType`, and inside
`add-element`'s `tree` it takes `widgetType`.

## The registry answers what exists, not the settings screen

The editor panel is a second catalogue rather than a view of the registry, and it
lists widgets this site cannot place — a name read off the panel, or quoted
from it by the user, can come back as not registered. Answer "does this widget
exist on this site?" from the registry every time, say plainly when the panel and
the registry disagree, and leave the switch to the user. Where such a widget
already sits in a page, the editor drops it from the canvas and from the
structure panel while the saved document still holds it, so read a gap there as a
widget out of the registry rather than as content to put back — rebuilding it and
saving is what would genuinely lose it.

A widget that wraps another plugin is there only while that plugin is active, and
its name is no guide to that: the FAQ, portfolio and testimonial widgets carry
the ordinary prefix and front separate plugins of the same maker. One that calls
an outside service does not fail for want of its key: it comes out with nothing
useful in it, and may name the key it wants in the place its content should have
been. So read an empty one as an unsaved key rather than as a fault.

## Much of what it adds to a page is not one of its widgets

Its extensions graft controls onto Elementor's own elements — badges, parallax
and reveal effects, tooltips, wrapper links, visibility rules, cursor and shape
work — so they turn up on elements that have nothing to do with this plugin.
Reading the schema of the element in question is what reveals them: Elementor
returns the plugin's controls together with the native ones.

## `bdt-template-builder` names two different things

It is an Elementor widget category, and the widgets under it render the parts of
whatever post is being displayed. It is *also* the post type the plugin keeps its
own theme templates in — headers, footers, single and archive layouts, search and
error pages, store pages — and those posts are ordinary Elementor documents. The
two cover different ground, so decide which a request means: filter the registry
by that category to place a part widget, and to edit a template reach its post id
first — no ability lists posts by type, so `novamira/execute-php` is the way
there — then work on that post with the Elementor abilities.
