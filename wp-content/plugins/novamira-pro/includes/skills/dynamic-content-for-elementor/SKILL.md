---
name: dynamic-content-for-elementor
description: Activate when working with Dynamic Content for Elementor (also called DCE, by Dynamic.ooo) on a WordPress site: its widgets, whose Elementor type starts with dce-, dce_ or dyncontel- (dce-dynamicposts-v2, dce_pdf_button, dyncontel-acf…), and the four surfaces it adds beyond widgets — the PDF it generates from an Elementor page or form submission, the HTML Templates post type those PDFs are laid out in, the dynamic tags it registers, and the controls and submit actions it contributes to the Elementor Pro Form widget. Covers finding its widgets in the Elementor registry, the name to call each one by, and the abilities that own the HTML Templates.
---

# Dynamic Content for Elementor

## Boundaries

- Composing a page, styling it, and choosing between v3 and v4 belong to the
  `elementor-build-page` skill. These widgets are v3, so place them inside a v3
  `container`.
- Dynamic Content for Elementor contributes two Dynamic Shortcodes types of its own: `{form:…}`
  for a submitted form field, and `{data:…}` for the values a surrounding context
  provides, such as the page numbering inside an HTML Template. The syntax itself
  is the `dynamic-shortcodes-integration` skill, the entry point to that family.
- Elementor's dynamic tags and query loops belong to `dynamic-data-binding`.
  It registers dynamic tags of its own, and
  `novamira/elementor-list-dynamic-tags` returns them alongside everyone else's.

## Finding its widgets

Two filters isolate the plugin and they agree with each other. Every widget it
registers sits in a **category whose slug starts with
`dynamic-content-for-elementor-`**, and every one of its widget classes lives in
the plugin's own namespace, so its **source reads `dynamiccontentforelementor`**
and never blends into Elementor or Elementor Pro. Either one gives you the whole
widget surface.

Widget names are not a filter. The plugin uses more than one prefix and a few of
its widgets carry none at all, so a name pattern quietly returns part of the set.

**In the editor those categories read "Dynamic.ooo - …"** — "Dynamic.ooo - Query
& Lists", "Dynamic.ooo - PDF", and so on. Name those when pointing a user at the
panel, never the slug.

How to query the registry, read a schema or compose a tree is not described here:
that belongs to `elementor-build-page` and to each ability's own description.

## Call a widget what Elementor calls it

A widget carries a label in the plugin's own panel and a different name inside
Elementor, and the two are not variations of one another — the one labelled
*Dynamic Posts* is `dce-dynamicposts-v2`, *Before After* is
`dyncontel-twentytwenty`, and the PDF Button is `dce_pdf_button`. When a user
quotes a label, list the widgets in the plugin's categories and read the matching
Elementor name from that listing. Use that registry name only where the target
ability's input schema documents a widget identifier, whether at the top level or
inside a nested widget element such as `set-content`'s `content` or
`add-element`'s `tree`; if it documents none, do not add the name. The family
schemas show top-level `widget_type` and `widget_types`; inside `set-content`'s
`content` a widget element takes `widget_type` or `widgetType`, and inside
`add-element`'s `tree` it takes `widgetType`.

Read the pair off that listing rather than from a name you carry in already.
The plugin keeps superseded versions of several widgets, and any of its widgets
can be switched off from the Features screen — which of them a given site has on
is that site's own history, not something to predict. A widget that is off
registers nothing, so no listing names it and no ability reaches it. When a label
a user quotes has no answer in the listing, report it as not registered here and
leave switching it back on to them, rather than guessing a name for it.

## Most of what the plugin adds is not a widget

Beyond its widgets it contributes controls and submit actions to Elementor Pro's
own Form widget — PDF generation, payment providers, conditional fields,
save-to-post and more — plus dynamic tags and controls grafted onto existing
elements. Reading the schema of the widget in question is what reveals them:
Elementor returns the plugin's controls together with the native ones, so a
Form widget schema already contains everything the plugin adds to it.

The Form widget's `submit_actions` control is where those are switched on, and
its accepted values are the action names, not the labels the editor shows. Read
them from the control's `opts` when the schema carries them. When it does not —
Elementor drops enumerations from the controls it builds in some contexts, and
the schema ability says so rather than leaving you to guess — the actions this
plugin contributes are `dce_form_pdf`, `dce_form_save`, `dce_form_favorites`,
`dce_form_export`, `dce_form_email`, `dce_form_redirect`, `dce_form_message`,
`dce_form_telegram`, `dce_php_action` and `cookie_action`, alongside whatever
Elementor Pro and any other plugin register. Which of them a site offers still
depends on what is switched on there, so treat this as the vocabulary to
recognize rather than a list of what is available.

## HTML Templates are not Elementor documents

The PDF generator renders from a template kept in its own post type, and that
post type does not support Elementor, so every `novamira/elementor-*` ability
that writes a document refuses it. An Elementor write aimed at a template comes
back as an error whose text opens with `elementor_unsupported_post_type` and
then names the template, its post type and where that content belongs, and the
template is left exactly as it was — nothing is stored beside the HTML the PDF
is actually laid out in. Read the code off the front of that text: it is the
part that reaches you whatever the reply is carried over.

The `novamira/dynamic-content-for-elementor-*` abilities are its CRUD surface.
Reach for those rather than writing the post meta by hand: the whole
configuration shares one value, so a partial write drops the rest of it.

A template renders one of two ways, and they look identical from outside, so read
one before offering to edit it. The `renders` field says which:

- **`html`** — the template holds its own markup, rendered to PDF by mPDF. This is
  plain HTML and CSS, not an Elementor document, so compose it as markup.
- **`elementor_template`** — it renders a separate Elementor template by id. Only
  here is the visible content edited with the Elementor abilities, and that id
  belongs to the Elementor template, not to this one. When
  `elementor_template_exists` comes back false, that is the usual reason a PDF
  arrives empty.

## Its settings live in the plugin's own panel

Individual features can be switched on and off in **wp-admin → Dynamic Content for
Elementor → Features** — the menu carries the product name, not the "Dynamic.ooo"
brand the Elementor panel shows. Service keys are not on that screen: the Google
Maps one lives on **Integrations**, the sibling page under the same menu, together
with the PayPal and Stripe credentials. The OpenStreetMap widgets need no key at
all. Changing any of it is the user's job from those panels, and a key must be
typed there rather than passed to an agent.
