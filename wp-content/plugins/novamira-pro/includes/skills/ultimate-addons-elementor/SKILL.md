---
name: ultimate-addons-elementor
description: Activate when working with Ultimate Addons for Elementor (also called UAE, UAEL or Ultimate Addons Pro, by Brainstorm Force) on a WordPress site: its widgets, whose Elementor type starts with uael- (uael-advanced-heading, uael-modal-popup, uael-business-reviews, uael-nav-menu…), all registered in its single ultimate-elements category, and the display conditions it grafts onto Elementor's own elements. Covers finding its widgets in the Elementor registry, the name to call each one by, why one of them can be missing from that registry, and why an element can render nothing on the page.
---

# Ultimate Addons for Elementor

## Boundaries

- Composing a page, styling it, and choosing between v3 and v4 belong to the
  `elementor-build-page` skill. These widgets are v3, so place them inside a v3
  `container`.
- Binding data and query loops belong to `dynamic-data-binding`.
- Switching a feature on and typing a service key are the user's job in the
  plugin's own settings screens. Read that state when it explains what you are
  seeing, and leave the writing to the user: every widget and every Posts skin
  shares one stored value, so writing part of it back restores the defaults over
  whatever else the user had chosen.
- Some sites also expose this plugin's own abilities under a `uae/` prefix.
  Where those include page-building ones, keep composing through the
  `novamira/elementor-*` abilities, so one document has a single writer.

## Finding its widgets

Everything this plugin registers sits in one Elementor category,
**`ultimate-elements`**, and that is the filter to reach for. Two other markers
agree with it and are useful when you already hold a listing rather than a query:
its widgets report a **source of `ultimateelementor`**, and every **widget name
begins `uael-`**.

Do not identify the plugin by the heading shown above those widgets in the
editor. That heading is white-labellable, so on an agency site it reads as that
agency's own name followed by "Elements" with nothing pointing back to this
plugin — and no Elementor ability returns it, so you cannot quote it to a user
from a listing. Describe the widgets instead of naming the panel.

How to query the registry, read a schema, or compose a tree is not described
here: that belongs to `elementor-build-page` and to each ability's own
description.

## One widget, three names

A widget carries a different name in each place you might meet it:

- the **title** a person says and the editor shows — *Price Box*;
- the **Elementor registry name** — `uael-price-table`;
- the **settings key** the plugin's own screens use — `Price_Table`.

None of the three is derivable from the others: *Price Box* sits next to a
separate *Price List*, and elsewhere `Woo_Mini_Cart` is `uael-mini-cart` while
`Dual_Heading` is `uael-dual-color-heading`. The plugin's settings answer about an
Elementor registry name without reporting an error, and the answer does not apply
— ask them about `uael-advanced-heading` and they report that widget as switched
off while Elementor is rendering it. Use that registry name only where the target
ability's input schema documents a widget identifier, whether at the top level or
inside a nested widget element such as `set-content`'s `content` or
`add-element`'s `tree`; if it documents none, do not add the name. The family
schemas show top-level `widget_type` and `widget_types`; inside `set-content`'s
`content` a widget element takes `widget_type` or `widgetType`, and inside
`add-element`'s `tree` it takes `widgetType`.

So read the three together rather than deriving one from another. With
`novamira/execute-php`,
`UltimateElementor\Classes\UAEL_Helper::get_all_widgets_list()` returns one entry
per feature, keyed by the settings key, each carrying `title`, the Elementor name
in `slug`, and the current on/off state in `is_activate` — project it down to
those three, because each full entry also carries a paragraph of marketing
metadata. Prefer that call over the narrower one beside it: where the free
edition is also installed it covers both halves, and where it is not the two
return the same thing. Match a user's words
against `title` — the label the editor shows, in the site's own language — which
is also the only route back to a widget that has left the registry and so cannot
be looked up there. Reaching the plugin through its own
class rather than through the stored value has a second benefit: on a site
without the plugin the call returns a plain "class not found" instead of a
confident answer about widgets that are not there.

The Posts widget's skins work the same way but live in their own list, the one
`get_post_skin_options()` returns; a skin switched off there disappears from the
widget as a switched-off widget disappears from the registry. Its `slug` is the
long form, `uael-skin-card`, while the value Elementor stores in `_skin` is the
bare `card`. Only the switchable skins are in that list, so one the widget
offers and the list does not mention is always on rather than unknown.

## The free edition can sit beside it

The family has a free edition that ships inside the header-and-footer plugin, and
the two run together rather than replacing one another. It adds a second Elementor
category of its own, holding widgets whose names follow no shared pattern — a few
carry an `hfe-` prefix and several carry none at all, so the `uael-` prefix finds
the paid half only. The editor labels that second category with the product name
as well, which is why a person can see the same brand twice in one panel.

The two halves also overlap: both ship a navigation menu, a retina image, a posts
grid and a search widget, all registered at the same time. A request that names
only the brand therefore points at either one. Check which half the page already
uses, or ask, instead of taking whichever you meet first.

## Not everything in its panel reaches the editor

Its settings list more than Elementor shows, and each cause is worth reporting
rather than concluding the feature does not exist:

- a widget switched off, which leaves the Elementor registry entirely: it has no
  schema, and inserting it fails with a message saying it is not registered on
  this site, which reads exactly like a widget that was never installed;
- a widget belonging to a host plugin the site does not have — the WooCommerce
  ones, and the stylers that restyle third-party form plugins;
- a feature that was never a widget. Several of them are controls it grafts
  onto elements that already exist: the display conditions onto widgets and onto
  containers, sections and columns, an animated particle background onto the
  latter three, and others that surface only once switched on, one of which
  widens the choices a native Elementor control offers instead of adding any. Read and write those on the element that carries them,
  and note that the particle background needs its own `uae_enable_particles`
  switch set, not only the `uae_particles_*` values around it. Each of these
  grafted features also has its own on/off entry in the plugin's settings.

**A widget switched off costs the user no content.** The element stays in the
document with its settings intact and returns the moment the widget is switched
back on. Say so, and rebuild nothing: replacing the element is what turns a
reversible switch into lost work.

## When an element renders nothing

Three different causes produce the same blank result, so establish which one
before touching anything.

**A display condition is hiding it.** This is the one that catches people out,
because it reaches past this plugin's own widgets: a condition set through the
plugin removes *any* Elementor element from the published page — a native
heading as readily as a `uael-` widget — while the editor still shows it. When a
published page is missing something the editor displays, read
`display_condition_enable` and `display_condition_list` on that element before
suspecting the theme or Elementor.

These conditions are decided per visitor while the rendered document is cached
by default, and the two collide: whichever visitor's result was stored first is
then served to everyone,
and the element looks hidden for all or none rather than for the audience you
chose. Clearing the document's cached render is what makes the condition
observable again.

When you write one of these yourself, take the condition type from the repeater's
own fields in that element's schema — each per-type field names the type it
belongs to — instead of inventing a plausible one. An unrecognised type is not
ignored: rendering stops dead at that element, so everything below it goes too
and the page never closes its markup, while the editor still looks right. A page
that ends abruptly is that mistake; a page merely missing one element is a
condition doing its job.

Give every row in `display_condition_list` an `_id` of its own. Two rows of the
same condition type that share one — and a blank counts as shared — collapse into
a single result, so the element ends up with the opposite visibility from the one
you asked for, silently and with the page otherwise intact.

**Its widget is switched off**, as above: nothing renders, and the page keeps its
content.

**A service key is missing.** Several widgets draw from an external service and
none of them fails without its key — they simply have nothing to show. The
Instagram, X/Twitter and business-review widgets leave the published page with
nothing visible and no error at all, and some of them emit no markup whatever, so
their element id is simply absent when you go looking for it; the map draws, but the map provider watermarks every
tile and lays its own warning over it. In the editor each of these puts a notice
in its own settings panel, which is where a person can see the cause, but that
notice does not come back in the widget's schema — so treat an empty result as a
key to check with the user rather than a broken widget.
