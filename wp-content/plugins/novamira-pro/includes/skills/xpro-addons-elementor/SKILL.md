---
name: xpro-addons-elementor
description: Activate when working with Xpro Elementor Addons on a WordPress site — also called Xpro Addons, Xpro, wpxpro, or by its paid edition Xpro Elementor Addons Pro. Covers its widgets, whose Elementor type starts with xpro- (xpro-hero-slider, xpro-interactive-circle, xpro-step-flow…), shipped as a free edition and a paid one that register under two sources and three groupings; the xpro_ controls its extensions graft onto Elementor's own elements; the dynamic tags the paid edition adds; and the templates it keeps as Elementor documents.
---

# Xpro Elementor Addons

## Boundaries

- Composing a page, styling it, and choosing between v3 and v4 belong to the
  `elementor-build-page` skill. These widgets are v3, so place them inside a v3
  `container`.
- Binding data belongs to `dynamic-data-binding`. The paid edition provides two
  groups without another host: `xpro-dynamic-tags` and `xpro-post-dynamic`. Two
  more are host-gated: `xpro-woo-dynamic` appears only while WooCommerce is
  active, and `xpro-dynamic` for its ACF tags only while ACF is active. Finding
  two rather than four means a missing host, not a broken install or a licence
  problem. Asking `novamira/elementor-list-dynamic-tags` for a native group such
  as `post` reaches none of them and answers with a full, plausible list of
  native tags, so read the groups back from the site and filter on one of those.
- Which widgets and extensions are switched on, the paid edition's licence, and
  the keys its service-backed widgets read are all edited under the plugin's own
  top-level menu in wp-admin: Widgets and Extensions tabs for the switches, a
  License screen, and a User Data screen for the keys. Point the user at the one
  that matters and leave the change to them, and let a key be typed there rather
  than handed to an agent.

## Finding its widgets: three groupings, and take the source too

Three category slugs hold the whole widget surface — `xpro-widgets`,
`xpro-themer` for the theme-building set, and `xpro-widgets-pro` — so a filter
naming one of them returns a full, plausible list that is a portion of what the
site offers. Read the set back from the site: a `novamira/elementor-get-schema`
call with `action: "list"` and no filter names every category and every source a
registered widget declares, that parameter being required on every call and a
call omitting it refused. Then pass each slug back with the same `action`.

**The grouping does not tell you which edition a widget needs.** The names
suggest a clean split and there isn't one: the paid edition fills part of the
theme-building group as well as the group named for itself, so a widget under a
free-looking category can still be one the site would lose with the paid plugin
gone.

**The `source` filter is what separates the editions here, and it is reliable**,
unlike most addons: each edition declares its widget classes under a PHP
namespace of its own, so they arrive under one source each, apart from the core
widgets and from every other plugin. That makes `source` the way to answer *which
of these needs the paid plugin?*, and taking both the way to see the whole
product. The two values are `xproelementoraddons` and `xproelementoraddonspro`;
the hyphenated forms that look right — the plugin folder, the category slugs —
match nothing, so copy both off the unfiltered listing instead of composing them.

**The widget-name prefix `xpro-` is a good `name_contains` for a keyword search,
and it is a habit rather than a rule**: the WPForms widget carries no prefix at
all, its Elementor type being simply the name of the form plugin it fronts.
Reach for a category or a source when the question is *what is the whole set?*,
and keep the prefix for searching.

The two editions install side by side and their widgets add up, but the paid one
is not self-standing: it registers through the free one and stops early when the
free plugin is not active, so a site running the paid plugin alone offers none of
its widgets and no categories either. When such a site reports nothing at all,
look first at whether the free plugin is active.

How to query the registry, read a schema or compose a tree is not described
here: that belongs to `elementor-build-page` and to each ability's own
description.

## What an unactivated licence looks like, and what it does not

With the paid plugin active and its licence not activated, its widgets, its
dynamic tags and the controls its extensions graft all stay away, while the free
edition carries on untouched. From the registry that state reads **exactly** like
the paid plugin not being installed at all: the same single source, the same two
categories, the same theme-building group holding only its free widgets. Do not
promise to tell the two apart from the registry, because nothing there separates
them.

What does separate them is the editor panel, and it is the user who sees it: the
paid category is still drawn there, empty, because the plugin registers the
grouping without waiting for the licence. So a user reporting an empty paid group
is reporting the licence, and that is worth asking about — as is the plugin's own
License screen, which answers it outright.

The plugin's dashboard is a different thing again: it lists the paid edition's
widgets on a site running the free plugin alone, so a name quoted from that
screen can be one the site cannot place. Answer "does this widget exist here?"
from the registry every time.

## Call a widget what Elementor calls it

Titles are marketing labels and names are terse or misspelt, so composing one
from the other is what fails: *Info List* is `xpro-infolist` with no hyphen,
*Circle Progress Bar* is `xpro-pie-chart`, whose title and name describe two
different shapes, and a family of the paid edition's widgets clips the word in
the title, *Advanced Gallery* landing on `xpro-advance-gallery`. Two names carry
a typo that has outlived several releases and has to be spelled the wrong way to
match: *Donation Form Grid* is `xpro-dontation-form-grid` and *Gravity Form* is
`xpro-gravity-from`.

So when a user quotes a label, list the widgets by category or by source, find
the entry whose title matches, and take the registry name from that listing. Use
that registry name only where the target ability's input schema documents a
widget identifier, whether at the top level or inside a nested widget element
such as `set-content`'s `content` or `add-element`'s `tree`; if it documents
none, do not add the name. The family schemas show top-level `widget_type` and
`widget_types`; inside `set-content`'s `content` a widget element takes
`widget_type` or `widgetType`, and inside `add-element`'s `tree` it takes
`widgetType`.

**A quoted title can also match more than one widget.** Several of its titles are
words other plugins use too — a countdown, a post title, a chart — so the same
label can belong to an Xpro widget, to one of Elementor's own, and to a theme's,
all on one site. Search every source rather than this plugin's, and say which one
you took when there is more than one. The gap between what a person reads and
what a call takes runs through the rest of the plugin as well: its category
titles and its dynamic-tag groups are labelled one way in the editor and named
another in the registry, and one tag name is spelled with an underscore where the
rest use hyphens. Take identifiers from what the site returns, never from what
the screen shows.

## Much of what it adds to a page is not one of its widgets

Its extensions graft controls onto Elementor's own elements, native and
third-party alike, and Elementor returns them from the host element's schema
together with the native controls. They carry the prefix `xpro_`, spelled with
an **underscore** where the widget names use a hyphen. That prefix is a habit
rather than a rule: its sole exception is the free edition's reveal master
switch. Set `enable_reveal_effect: "enabled"`; `xpro_reveal_*` controls alone
cannot enable the effect, and the paid edition adds no further unprefixed
controls. So a request for one of those behaviours belongs on the element the
user is pointing at, not on an Xpro widget.

**Which behaviours reach an element depends on the element.** A widget takes the
larger set — floating, scroll and tilt effects, a sound effect, a background
overlay — and a `container` takes none of those five, while being the one that
takes mouse effects, background parallax, particles, an animated gradient and
equal height. A reveal, a wrapper link and display conditions reach both.
Ripples, liquid glass and backdrop filter from the free edition, and confetti
from the paid edition, also reach both. Switch values are not uniform:
`xpro_ripples_enable` takes `"enabled"`, while
`xpro_elementor_display_conditions_enable` takes `"yes"`. So read the schema of
the element in front of you before promising a behaviour on it, and expect the
same native widget to offer different sets on two sites, because the paid
edition adds a second wave of these to the same elements.

Reveal comes from the free edition and can affect widgets from either edition.
Once `enable_reveal_effect` is on, it hides content in
`.block-revealer__content` until the animation completes. For a build that must
stay visible without relying on that animation, also set
`xpro_reveal_content_show: "yes"`. The element's own box, opacity and text can
all look healthy while that intermediate wrapper is at zero opacity. The reveal
script inserts the wrapper at runtime, so inspect it in a page executed by a
browser; it is absent from the server's HTML response.

Display conditions come only from the paid edition. Do not set
`xpro_elementor_display_conditions_enable: "yes"` unless the user requested
conditional visibility and you also write the
`xpro_elementor_display_conditions` repeater in the same call. With that
repeater left at its default, the element disappears from the rendered page but
remains in the saved tree, so `elementor-get-content` read-back looks
successful. Verify this control with an HTTP fetch of the rendered page, not a
document read-back.

## A widget that fronts another plugin registers either way

Several of its widgets exist to display something another plugin owns — a form
plugin, a store, a donations or courses plugin — and each is in the registry
whether or not that plugin is installed, so being able to place one says nothing
about being able to render one. Judge the family by what a widget is *for*, not
by a list of names. The schema is where it shows: with the host absent, such a
widget comes back carrying the controls every Elementor widget has and none of
its own, and every widget in that state looks alike; with the host present, its
own controls appear beside them, the form picker populated with the forms the
site actually has. So read a widget of this family with no controls of its own as
a missing host rather than as a widget with nothing to configure.

The widgets that call an outside service need a key, and there are two places it
can live. Most — maps, social feeds, reviews, mailing lists — read a site-wide
key saved on the plugin's User Data screen, so one key there serves every copy on
the site. The weather widget is the other shape, taking the key on the widget
itself, so that screen holds nothing for it. What a missing key produces varies,
from nothing at all, through a line saying the key is missing, to a widget that
renders its normal shape and simply never reaches the service — so when one of
these comes out wrong, ask about the key before looking for a fault in the
widget.

## One option row holds every switch, and it lists what is on

The widgets and the extensions share a single option row,
`xpro_elementor_addons_options`, and inside it `xpro_elementor_widget_list` and
`xpro_elementor_module_list` name what is **switched on**, so an absent row means
everything is on rather than nothing. A widget switched off there is invisible to
Elementor: no schema, no insertion. Where such a widget already sits in a saved
page, the editor drops it from the canvas and from the structure panel while the
saved document still holds it, so read that gap as a widget out of the registry
rather than as content to put back — rebuilding it and saving is what would
genuinely lose it.

Reading that row with `novamira/execute-php` answers the switch question
directly; guard on `XPRO_ELEMENTOR_ADDONS_VERSION` first, so that a site without
the plugin says so instead of reporting that everything is on. Take the row by
that exact name: the site carries other `xpro_` rows holding per-post settings
for individual extensions, and reading one of those answers a different question
without saying so.

The shape of the row is why switching stays on that screen and out of a write of
ours. Because the lists name what is on, a write naming the single widget to
enable switches off everything else in the same stroke; and the row holds more
than switches, the keys saved on the User Data screen sitting in it under
`xpro_elementor_user_data`, so a write that reproduces only the part it meant to
change takes those with it too.

## Its templates are Elementor documents

The plugin keeps a Templates post type of its own, `xpro_content`, and those
posts are ordinary Elementor documents: once you have a post id the Elementor
abilities compose and edit one the way they do a page. It is kept out of the
WordPress admin menu on purpose and reached from the plugin's own screens, so
finding no entry there says nothing about whether it holds anything.

Its Template widget places one of those documents into a page and takes it by
id — but the ids it accepts are not only that post type's. It serves Elementor's
own template library too, which on most sites is where the usable templates
already are, so look there as well before concluding a site has none to place. No
ability lists posts by type, so `novamira/execute-php` is the way to those ids.

Two things about that list are worth carrying to the user. It is not filtered by
what a template is *for*, so headers, footers, archive layouts and the site's
global style kit are offered beside the ones that belong inside a page. And the
widget renders the template rather than copying it, so editing one changes it
everywhere it is used, including wherever a theme has it placed: when someone
wants a variation, duplicate first and edit the copy.
