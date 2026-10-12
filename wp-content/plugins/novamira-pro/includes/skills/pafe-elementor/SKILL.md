---
name: pafe-elementor
description: Activate when working with Piotnet Addons for Elementor on a WordPress site — also called PAFE, PAFE Pro or Piotnet Addons, from Piotnet, shipped as a free edition and a paid one that install side by side. Covers its widgets, whose Elementor type starts with pafe- (pafe-table, pafe-form-builder-field…), grouped in the editor under several categories of its own; the pafe_ controls it grafts onto Elementor's own elements; the forms it builds out of those widgets, which are Elementor documents; and the widgets a site creates for itself with its widget creator. Explains how to find its widgets on any site and the name to call each one by.
---

# Piotnet Addons for Elementor

## Boundaries

- Composing a page, styling it, and choosing between v3 and v4 belong to the
  `elementor-build-page` skill. These widgets are v3, so place them inside a v3
  `container`.
- Elementor's dynamic tags and query loops belong to `dynamic-data-binding`.
  These widgets take Elementor's own tags the way any other v3 widget does, so
  work from the list `novamira/elementor-list-dynamic-tags` returns for the site.
- Which features are switched on, the paid edition's licence, and the service
  keys some widgets and form actions read are all edited under the plugin's own
  top-level menu in wp-admin. Point the user at the screen that matters and leave
  the change to them, and let a key be typed there rather than handed to an
  agent.
- What its forms collect — submissions, bookings, abandonment rows — is kept in
  post types of its own and read from that same menu. Building a form is
  Elementor work and is covered below; what arrives in one is not.

## Everything it registers follows a switch

Each of its features carries a switch on the plugin's own settings screen, one
list covering both editions, and a site can have every one of them off. In that
state Elementor's registry holds none of this plugin's widgets — the free
edition's included — so a listing by name or by category comes back empty, while
both plugins are active and the plugin's own code is loaded and running.

So read an empty answer as the switches rather than as the plugin being absent:
settle "is this plugin on the site?" from the constants its editions define,
`PAFE_VERSION` and `PAFE_PRO_VERSION`, either of which answers yes on its own and
both of which `novamira/execute-php` reads. Then name what the site is missing
and point the user at that screen — the switch is theirs to flip.

Answer "does this widget exist on this site?" from the registry every time. The
feature list on that screen is a second catalogue rather than a view of the
registry, and the switch a widget's name suggests is not always the one that
governs it — a feature can read as off there while its widget is registered and
placeable. Where such a widget already sits in a saved page, the editor drops it
from the canvas and from the structure panel while the saved document still holds
it, so read a gap there as a widget out of the registry rather than as content to
put back: rebuilding it and saving is what would genuinely lose it.

## Finding its widgets

- **The widget name prefix `pafe-`.** Every widget the plugin itself ships
  carries it, in both editions, and it is the filter that holds across all of
  them. Pass it as `name_contains` to `novamira/elementor-get-schema`, alongside
  `action: "list"` — that ability requires `action` on every call and refuses one
  that omits it.
- **Its categories, of which a site has several**, all with slugs beginning
  `pafe`. They divide the product rather than describe it: one is named for the
  free edition and holds only what that edition brings, one holds the form
  builder, and a site can report one of them with nothing in it at all. So take
  the whole family when you enumerate by category, never a single slug.

Reach for those rather than for the source: these widget classes declare no PHP
namespace, so Elementor files them under the catch-all source it keeps for every
un-namespaced widget on the site, which other addons land in too.

The two editions install side by side and their widgets add up — the free one
keeps registering its own set while the paid one is active — so a site offers
either set alone or the two combined. Read the registry in front of you rather
than taking any one list for the whole plugin.

How to query the registry, read a schema or compose a tree is not described here:
that belongs to `elementor-build-page` and to each ability's own description.

## Call a widget what Elementor calls it

Most titles carry the product name and turn into the widget name plainly enough,
though not all of them do: *PAFE Progress Bar* is `pafe-progressbar`. The
form-builder set carries no product name at all, and its titles are bare words
for what the piece does — *Field* is `pafe-form-builder-field`, *Submit* is
`pafe-form-builder-submit`, *Booking* is `pafe-form-booking`, *Form Entries* is
`pafe-display-form-submissions`, *Form Embedding* is `pafe-forms`. Those short
titles also read like Elementor's own, so a user asking for a submit button may
mean either.

When a user quotes a label, list the widgets by prefix or by category, find the
entry whose title matches, and take the registry name from that listing. Use that
registry name only where the target ability's input schema documents a widget
identifier, whether at the top level or inside a nested widget element such as
`set-content`'s `content` or `add-element`'s `tree`; if it documents none, do not
add the name. The family schemas show top-level `widget_type` and `widget_types`;
inside `set-content`'s `content` a widget element takes `widget_type` or
`widgetType`, and inside `add-element`'s `tree` it takes `widgetType`.

## Much of what it adds is not one of its widgets

It grafts a large body of controls onto Elementor's own elements — containers,
sections, headings, buttons, images, carousels, the Pro form — for visibility
rules, sticky and parallax behaviour, gradient text and buttons, per-element
breakpoints and tooltips. They carry the prefix `pafe_`, spelled with an
**underscore** where the widget names use a hyphen. Reading the schema of the
element in question reveals them: Elementor returns the plugin's controls with
the native ones, so a request for one of these belongs on the host element rather
than on a widget of this plugin's.

## Its forms are Elementor documents

A form is a post of the plugin's own type, and those posts are ordinary Elementor
documents, so once you have a post id the Elementor abilities compose and edit one
the way they do a page: fields, steps and a submit widget in a container. No
ability lists posts by type, so `novamira/execute-php` is the way to that id, and
a widget of its own places a finished form into a page by id.

Everything a form does once submitted — the mail it sends, the record it stores,
the payment it takes, the service it calls, the conditions that hide a field — is
configured as controls on the submit widget itself, which carries far more of them
than any other widget here. Read its schema before offering to change any of that;
there is no second place to look.

## Widgets a site makes for itself

The paid edition lets a site build widgets of its own, each kept as a post of a
type of its own, and every published one is registered as a full Elementor widget
in its own right. Its name, title and categories are whatever the person who made
it typed, so it carries neither the `pafe-` prefix nor any of the plugin's
categories and can sit among Elementor's own. To find these, list that post type
with `novamira/execute-php` and read their names back out of the registry.
