---
name: qi-addons-elementor
description: Activate when working with Qi Addons for Elementor on a WordPress site — also called Qi Addons, QI Addons, Qi Elementor Addons, and shipped as a free edition beside a paid Qi Addons Premium, from Qode Interactive. Covers its widgets, whose Elementor type carries the underscored prefix qi_addons_for_elementor_ (qi_addons_for_elementor_accordion, ..._blog_slider, ..._premium_advanced_timeline…), all grouped in the editor under one category of its own, and the Qode Templates the paid edition keeps as Elementor documents. Explains how to find its widgets on any site and the name to call each one by.
---

# Qi Addons for Elementor

## Boundaries

- Composing a page, styling it, and choosing between v3 and v4 belong to the
  `elementor-build-page` skill. These widgets are v3, so place them inside a v3
  `container`.
- Elementor's dynamic tags and query loops belong to `dynamic-data-binding`.
  These widgets take Elementor's own tags the way any other v3 widget does, so
  work from the list `novamira/elementor-list-dynamic-tags` returns for the site.
- Which widgets are switched on, the licence, and the service keys a few of the
  paid widgets read are all edited under the plugin's own top-level menu in
  wp-admin: a Widgets screen for the switches, a Registration screen for the
  licence, and an Integration screen for the keys. Point the user at the one that
  matters and leave the change to them, and let a service key be typed there
  rather than handed to an agent. A widget whose key is missing renders empty
  instead of failing, so when a map or a business reviews widget comes out blank,
  the key is the first thing to ask about.

## Finding its widgets

Two filters describe the whole widget surface, and both hold across the editions:

- **The widget name prefix `qi_addons_for_elementor_`.** Every widget carries it,
  with no exception in either edition. Note the **underscores**: this plugin
  spells its widget names with them throughout, so a name composed with hyphens
  matches nothing. Pass the prefix as `name_contains` to
  `novamira/elementor-get-schema`, alongside `action: "list"` — that ability
  requires `action` on every call and refuses one that omits it.
- **A single category, whose slug is `qi-addons`.** One grouping holds every
  widget of both editions, and it is the group the editor panel draws. Pass it as
  `category` to the same ability, with the same `action: "list"`.

**Reach for those two rather than for the source.** These widget classes declare
no PHP namespace, so Elementor files them under the catch-all source it keeps for
every un-namespaced widget on the site. Other addons land in that same bucket, so
it holds this plugin's widgets today and a mixture tomorrow.

The heading the editor draws over the group is a translated title rather than the
slug, so it reads differently on a site running in another language. Match on the
slug, and quote back whatever title the registry reports when you point someone at
the panel.

The two editions install side by side and their widgets add up, so a site can
offer either the free set alone or the larger combined one. The paid edition
builds on the free one and needs it active to register anything at all: where the
paid plugin is active and no Qi widgets are in the registry, the free plugin is
what is missing.

Some widgets front another plugin — a store, the form plugins — and are in the
registry only while that plugin is active. Read the registry of the site in front
of you rather than taking any one list for the whole plugin.

How to query the registry, read a schema or compose a tree is not described here:
that belongs to `elementor-build-page` and to each ability's own description.

## The name says which edition a widget needs

A widget from the paid edition carries a second segment right after the prefix:
`qi_addons_for_elementor_premium_advanced_timeline`,
`..._premium_business_reviews_google`, `..._premium_data_table`. Every widget
the paid edition adds carries it and no free widget does, so the name alone
answers "does this one need the paid plugin?" — worth saying plainly to a user
who is about to build on a widget their site cannot place.

## Call a widget what Elementor calls it

Titles here are descriptive and plural where names are concise and singular, so a
good share of them do not convert: *Blog Carousel* is
`qi_addons_for_elementor_blog_slider`, *Working Hours* is `..._business_hours`,
*Divider* is `..._separator`, *Info Box* is `..._info_cards`, *Pie and Donut
Charts* is `..._charts`, *Team* is `..._team_member`, *Accordions and Toggles* is
`..._accordion`. Watch the carousel and slider pair especially: a title saying
*Carousel* lands sometimes on a name ending `_slider` and sometimes on one ending
`_carousel`, so the word on the label does not settle it.

So when a user quotes a label, list the widgets by category or by prefix, find the
entry whose title matches, and take the registry name from that listing. Use that
registry name only where the target ability's input schema documents a widget
identifier, whether at the top level or inside a nested widget element such as
`set-content`'s `content` or `add-element`'s `tree`; if it documents none, do not
add the name. The family schemas show top-level `widget_type` and `widget_types`;
inside `set-content`'s `content` a widget element takes `widget_type` or
`widgetType`, and inside `add-element`'s `tree` it takes `widgetType`.

## The registry answers what exists, not the editor panel

The panel lists entries for the paid edition alongside the registered widgets,
under the same group and drawn from a list the free plugin ships. On a site with
the free edition alone they name widgets that are not registered; where the paid
plugin is installed but its licence has not been activated they appear a second
time beside the widgets that are. So a name read off the panel, or quoted from it
by a user, can come back as not registered — answer "does this widget exist on
this site?" from the registry every time, and read a doubled entry as that licence
rather than as two widgets.

A widget switched off from the plugin's Widgets screen is not registered either:
Elementor never sees it, it has no schema, and it cannot be inserted. Say so and
leave the switch to the user. Where such a widget already sits in a saved page,
the editor drops it from the canvas and from the structure panel while the saved
document still holds it, so read a gap there as a widget out of the registry
rather than as content to put back — rebuilding it and saving is what would
genuinely lose it.

## Its theme templates are Elementor documents

The paid edition keeps a Qode Templates post type of its own — headers, footers,
single and archive layouts, an error page, and the store layouts — and those posts
are ordinary Elementor documents, so once you have a post id the Elementor
abilities work on them the way they do on a page. No ability lists posts by type,
so `novamira/execute-php` is the way to that id. Where each template is displayed
is set from the plugin's own Theme Builder screen, and that part stays with the
user.
