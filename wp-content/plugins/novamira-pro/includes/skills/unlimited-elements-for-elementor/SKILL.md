---
name: unlimited-elements-for-elementor
description: Activate when working with Unlimited Elements for Elementor on a WordPress site — also called Unlimited Elements, UE, Unlimited Elements Pro or Premium, from unlimited-elements.com. Covers its widgets, whose Elementor type starts with ucaddon_ (ucaddon_flip_box, ucaddon_hotspot, ucaddon_ue_listing_grid…), grouped in the editor under the categories of its own widget library; the library screen a site installs those widgets from one at a time; and the custom widgets its users build there. Explains how to find them on any site and the name to call each one by.
---

# Unlimited Elements for Elementor

## Boundaries

- Composing a page, styling it, and choosing between v3 and v4 belong to the
  `elementor-build-page` skill. These widgets are v3, so place them inside a v3
  `container`.
- Elementor's dynamic tags and query loops belong to `dynamic-data-binding`.
- The widget library, the builder that makes custom widgets, and the plugin's
  settings are screens of its own under its menu in wp-admin. Installing a
  widget, building one and saving a service key are the user's job from there:
  name the screen and hand it back.

## What reaches Elementor is what the library holds

This plugin keeps its widgets as records rather than as files: a site installs
each one into the plugin's own library, and Elementor is handed exactly that
set. The widget surface is therefore per-site — two sites on the same version
and the same edition can offer different widgets, and a site that has installed
none offers none.

So read the registry to learn what this site has, every time. An empty result
means nothing has been installed into the library yet, which the user fixes from
the library screen — never that the plugin has no widgets.

## Finding its widgets

Two filters reach the whole surface, and both hold in the free and the paid
edition: **the widget name prefix `ucaddon_`**, which every widget it registers
carries and which you can pass as `name_contains` to
`novamira/elementor-get-schema`, and **a category whose slug is
`unlimited_elements`**, under which the abilities report all of them. Send either
one with `action: "list"`: that ability requires `action` on every call and refuses
one that omits it.

**Reach for those two rather than for the source.** These widget classes are
built at run time out of the library records, so Elementor cannot attribute them
to a plugin and files them under the source it keeps for whatever it cannot
place, which on another site holds other plugins' widgets too.

How to query the registry, read a schema or compose a tree is not described here:
that belongs to `elementor-build-page` and to each ability's own description.

## Call a widget what Elementor calls it

The title in the panel and the name Elementor knows a widget by are two
independent strings here, and composing the second from the first is wrong often
enough to be the normal case — *Icon Tabs* is `ucaddon_uc_bullet_tabs`, *Shape
Bullets* is `ucaddon_uc_diamond_bullets`, *Toggle Dropdown* is
`ucaddon_dropbar`, *Number Box* is `ucaddon_circle_number_widget`, and some names
are spelled differently from their title.

Searching the registry for the words of the title is unreliable: those
words belong to other widgets too, so the search answers confidently with the
wrong one. **List the widgets by prefix or category, find the entry whose title
matches, and take the registry name from that listing.** A name composed from a
title comes back as "not registered with Elementor on this site", which reads
like the plugin not having the widget at all. Use that registry name only where
the target ability's input schema documents a widget identifier, whether at the
top level or inside a nested widget element such as `set-content`'s `content` or
`add-element`'s `tree`; if it documents none, do not add the name. The family
schemas show top-level `widget_type` and `widget_types`; inside `set-content`'s
`content` a widget element takes `widget_type` or `widgetType`, and inside
`add-element`'s `tree` it takes `widgetType`.

When you point someone at the editor panel, give them that title too: the panel
groups these widgets under the library's own categories, whose names each site
chooses, so the category slug above is not a heading anyone will see there.

## Some option lists only fill in the editor

A few of its controls build their choices while the editor panel is drawn and
come back all but empty anywhere else — the item-template picker on its loop
widgets is the one you will meet. Read an empty option list there as "not
readable from here" rather than "nothing to choose", and take the value from
WordPress: an item template is an ordinary Elementor library template, so find
or build one with the Elementor abilities and pass its post id.

## On the free edition the paid values are visible, and marked

Both editions register the same widgets with the same controls, and a widget
already in the library keeps working when the edition changes. Where an option
belongs to the paid edition the free one returns it with a marker worked into
its key and a Pro note on its label, while the paid one returns the plain key.

So take enum values from the schema of the site you are on. A value carried over
from a paid site is refused on a free one, and the marked value is a label for
the paid edition rather than a setting: it is accepted on write and changes
nothing. Pick a
plain value, and say that the option asked for comes with the paid edition.

## Widgets that front another plugin or a service

A widget wrapping another plugin or an outside service is registered and fully
configurable whether or not that thing is there: the form widgets keep their
controls with no form plugin active, the map, weather, calendar and currency
widgets theirs with no service key saved. They render empty instead of failing,
so when a page shows one of these and nothing inside it, the absent host plugin
or the unsaved key is the first thing to ask about.
