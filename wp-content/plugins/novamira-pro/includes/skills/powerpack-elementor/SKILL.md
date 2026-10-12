---
name: powerpack-elementor
description: Activate when working with PowerPack for Elementor on a WordPress site — also called PowerPack Elements, PowerPack Addons, PPE, or by its editions PowerPack Lite and PowerPack Pro, from IdeaBox. Covers its widgets, whose Elementor type starts with pp- (pp-info-box, pp-advanced-tabs, pp-modal-popup, pp-instafeed…), grouped in the editor under a PowerPack category, and the three surfaces it adds beyond widgets: the controls its extensions graft onto Elementor's own elements, a dynamic tag group of its own, and the service keys several of its widgets read. Explains how to find its widgets on any site and the name to call each one by.
---

# PowerPack for Elementor

## Boundaries

- Composing a page, styling it, and choosing between v3 and v4 belong to the
  `elementor-build-page` skill. These widgets are v3, so place them inside a v3
  `container`.
- Elementor's dynamic tags and query loops belong to `dynamic-data-binding`. This
  plugin adds a tag group of its own, which stays empty until the feature behind
  it is switched on in the plugin's settings, so read the group back with
  `novamira/elementor-list-dynamic-tags` rather than assuming what is inside it.

## Finding its widgets

The plugin ships in two editions, a free one and a paid one, and the paid edition
takes the free one's place rather than sitting beside it. A site therefore has one
or the other, and you generally do not know which. Two filters hold across both,
so a call built on either works everywhere:

- **The widget name prefix `pp-`.** Every widget it registers carries it, in both
  editions, and it is the filter that reaches the whole widget surface. Pass it as
  `name_contains` to `novamira/elementor-get-schema`, alongside `action: "list"` —
  that ability requires `action` on every call and refuses one that omits it.
- **A category whose slug is `powerpack-elements`.** Both editions register that
  slug, and it is the grouping the editor panel draws. Prefer it when the user is
  pointing at something they saw in the panel; prefer the prefix when you want
  everything, because the plugin files some of its media widgets under a different
  category name.

**Reach for those two rather than for the source.** A widget's source identifier
is derived from the plugin's PHP namespace, and the two editions use different
namespaces, so a source that matches every widget on one site matches none at all
on the other. It fails by returning an empty list rather than an error, which
reads like a site that does not have the plugin.

The paid edition also registers store categories, which Elementor keeps out of the
way while they hold nothing and shows once WooCommerce is there. Read the category
list back from the registry instead of assuming which ones a given site has.

The category's **title** is a setting, so a site may show these widgets under a
name of its owner's choosing. Match on the slug, and quote back whatever title the
registry reports when you point someone at the panel.

How to query the registry, read a schema or compose a tree is not described here:
that belongs to `elementor-build-page` and to each ability's own description.

## The registry answers what exists, not the settings screen

The plugin keeps a per-widget switch list, and its settings screen is drawn from
that list rather than from Elementor. The two can disagree: a widget may be
switched on in the list and still not be registered with Elementor, and while that
is so it has no schema and cannot be inserted. Answer "does this widget exist on
this site?" from the Elementor registry every time. When the registry and the
switch list disagree, say so and leave the switch to the user — turning it on
again is not what closes that gap.

Some widgets are present only when the plugin they wrap is: the form widgets need
their form plugin installed, and the store widgets need WooCommerce. Those are
missing from the registry and from the switch list alike, and installing the host
plugin is what brings them back.

## Call a widget what Elementor calls it

The label in the plugin's panel and the name Elementor knows a widget by are two
strings, and often enough the second cannot be guessed from the first — *Popup
Box* is `pp-modal-popup`, *Instagram Feed* is `pp-instafeed`, *Advanced Posts* is
`pp-posts`. When a user quotes a label, list the widgets by prefix or category,
and read the matching Elementor registry name out of that listing. Use that
registry name only where the target ability's input schema documents a widget
identifier, whether at the top level or inside a nested widget element such as
`set-content`'s `content` or `add-element`'s `tree`; if it documents none, do not
add the name. The family schemas show top-level `widget_type` and `widget_types`;
inside `set-content`'s `content` a widget element takes `widget_type` or
`widgetType`, and inside `add-element`'s `tree` it takes `widgetType`.

## Much of what it adds to a page is not one of its widgets

Its extensions graft controls onto Elementor's own elements — display conditions,
wrapper links, tooltips, custom cursors, background effects — so they turn up on
widgets that have nothing to do with this plugin. Reading the schema of the
element in question is what reveals them: Elementor returns the plugin's controls
together with the native ones, so the schema of any element already contains
everything the plugin contributes to it.

## Its settings live in the plugin's own panel

Which widgets are switched on, the category title, and the keys for the services
some widgets call — maps, places, reviews, video and social feeds — are all edited
from the plugin's own settings screen under its menu in wp-admin. Changing any of
it is the user's job from that panel, and a service key must be typed there rather
than passed to an agent. A widget whose key is missing renders empty instead of
failing, so when a page shows one of those widgets and nothing inside it, the key
is the first thing to ask about.
