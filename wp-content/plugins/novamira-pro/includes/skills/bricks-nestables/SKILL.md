---
name: bricks-nestables
description: Companion of bricks-build-page for Bricks nestable composites — slider-nested (carousels), tabs-nested, accordion-nested (FAQ), nav-nested with dropdown and mega menu, offcanvas, back-to-top — with the exact child structure Bricks expects (role blocks, `_hidden._cssClasses` markers, tags), the parent behaviour keys, which children are structural, and how query loops combine with slides and accordion items. Load whenever a section needs a carousel, tabs, an accordion/FAQ, a navigation menu or an offcanvas panel.
---

# Nestable composites

Nestables are elements whose behaviour is driven by **role children**.
Novamira's content writes do not validate these role children, so a missing
or renamed role child fails silently on the front end. Write the structure
below exactly; put your own content inside the role children. Bricks' own
abilities may reject a `nav-nested` or `dropdown` whose marked wrapper is
missing, but that check does not run on Novamira writes.

Two conventions used throughout:

- `_hidden._cssClasses` is where Bricks stores an internal **role marker**
  (`tab-title`, `brx-nav-nested-items`, …). It is not visibility. Copy the
  marker verbatim; never "clean it up".
- All layout composites (`section`, `container`, `block`, `div`) may carry
  `hasLoop` + `query`; other elements may not.

Nestable elements: `section`, `container`, `block`, `div`, `slot`,
`slider-nested`, `tabs-nested`, `accordion-nested`, `nav-nested`,
`dropdown`, `offcanvas`, `back-to-top`. `toggle` is **not** nestable (it is a
control used inside nav/offcanvas). `list-elements` reports `nestable`.

## slider-nested (carousel)

Each **direct** `block` (or `container`/`div`) child is one slide; any other
direct child is ignored as a slide. Slide order = child order.

```json
[
  {"id": "sldr01", "name": "slider-nested", "parent": "ctn001", "settings": {"perPage": 3, "perPage:mobile_portrait": 1, "gap": "var(--space-m)", "arrows": true, "pagination": true}},
  {"id": "slde01", "name": "block", "parent": "sldr01", "label": "Slide 1", "settings": {}},
  {"id": "s1head", "name": "heading", "parent": "slde01", "settings": {"text": "Slide 1", "tag": "h3"}},
  {"id": "slde02", "name": "block", "parent": "sldr01", "label": "Slide 2", "settings": {}},
  {"id": "s2head", "name": "heading", "parent": "slde02", "settings": {"text": "Slide 2", "tag": "h3"}}
]
```

Parent keys: `type`, `direction`, `perPage`, `perMove`, `gap`, `speed`,
`autoplay` + `interval` + `pauseOnHover`, `rewind`, `arrows`,
`pagination`, `height`/`autoHeight`, `keyboard`; `optionsType: "custom"` +
`options` (JSON) replaces the assembled options. Responsive values use the
normal suffixes (`perPage:tablet_portrait`).

**Loop**: put `hasLoop` + `query` on **one** slide block — each result
becomes a slide (Bricks re-initialises the slider after AJAX pagination).
Testimonials, logos and featured posts belong here.

## tabs-nested

Two role blocks whose children are paired **by index**: the n-th title opens
the n-th pane. Unequal counts or reordering one side only breaks pairing.

```json
[
  {"id": "tabs01", "name": "tabs-nested", "parent": "ctn001", "settings": {"openTabOn": "click", "openTab": 0}},
  {"id": "tmnu01", "name": "block", "parent": "tabs01", "label": "Tab menu", "settings": {"_direction": "row", "_hidden": {"_cssClasses": "tab-menu"}}},
  {"id": "tt0001", "name": "div",   "parent": "tmnu01", "label": "Title", "settings": {"_hidden": {"_cssClasses": "tab-title"}}},
  {"id": "tt1x01", "name": "text-basic", "parent": "tt0001", "settings": {"text": "Overview"}},
  {"id": "tt0002", "name": "div",   "parent": "tmnu01", "label": "Title", "settings": {"_hidden": {"_cssClasses": "tab-title"}}},
  {"id": "tt2x01", "name": "text-basic", "parent": "tt0002", "settings": {"text": "Details"}},
  {"id": "tcnt01", "name": "block", "parent": "tabs01", "label": "Tab content", "settings": {"_hidden": {"_cssClasses": "tab-content"}}},
  {"id": "tp0001", "name": "block", "parent": "tcnt01", "label": "Pane", "settings": {"_hidden": {"_cssClasses": "tab-pane"}}},
  {"id": "tp1x01", "name": "text",  "parent": "tp0001", "settings": {"text": "<p>Overview content.</p>"}},
  {"id": "tp0002", "name": "block", "parent": "tcnt01", "label": "Pane", "settings": {"_hidden": {"_cssClasses": "tab-pane"}}},
  {"id": "tp2x01", "name": "text",  "parent": "tp0002", "settings": {"text": "<p>Details content.</p>"}}
]
```

Parent keys: `direction`, `openTabOn` (`click` | `mouseenter`), `openTab`
(zero-based). **Loops**: there is no single tab item — avoid query loops on
tabs, or loop title and pane with the identical query and order.

## accordion-nested (FAQ)

Each direct `block` is one item; inside it, one descendant marked
`accordion-title-wrapper` and one marked `accordion-content-wrapper`.

```json
[
  {"id": "acc001", "name": "accordion-nested", "parent": "ctn001", "settings": {"expandItem": "0", "faqSchema": true}},
  {"id": "item01", "name": "block", "parent": "acc001", "label": "Item", "settings": {}},
  {"id": "it1t01", "name": "block", "parent": "item01", "label": "Title", "settings": {"_direction": "row", "_alignItems": "center", "_justifyContent": "space-between", "_hidden": {"_cssClasses": "accordion-title-wrapper"}}},
  {"id": "it1h01", "name": "heading", "parent": "it1t01", "settings": {"text": "Do you offer sliding-scale pricing?", "tag": "h3"}},
  {"id": "it1i01", "name": "icon", "parent": "it1t01", "settings": {"icon": {"library": "ionicons", "icon": "ion-ios-arrow-forward"}, "iconSize": "1em", "isAccordionIcon": true}},
  {"id": "it1c01", "name": "block", "parent": "item01", "label": "Content", "settings": {"_hidden": {"_cssClasses": "accordion-content-wrapper"}}},
  {"id": "it1x01", "name": "text",  "parent": "it1c01", "settings": {"text": "<p>Yes — ask when booking.</p>"}}
]
```

Repeat the item block per question. Parent keys: `expandItem`
(comma-separated zero-based indexes), `independentToggle`, `transition`,
`faqSchema` (emits FAQ structured data — use it for real FAQs). **Loop**:
`hasLoop` + `query` on one item block repeats the whole item per result.

## nav-nested (menu) with dropdown / mega menu

Belongs in a **header template**. Structure:

```json
[
  {"id": "nav001", "name": "nav-nested", "parent": "hdr001", "settings": {"mobileMenu": "mobile_landscape"}},
  {"id": "navi01", "name": "block", "parent": "nav001", "label": "Nav items", "settings": {"tag": "ul", "_hidden": {"_cssClasses": "brx-nav-nested-items"}}},
  {"id": "link01", "name": "text-link", "parent": "navi01", "label": "Nav link", "settings": {"text": "Home", "link": {"type": "internal", "postId": 2}}},
  {"id": "drop01", "name": "dropdown", "parent": "navi01", "label": "Dropdown", "settings": {"text": "Services"}},
  {"id": "drpcnt", "name": "div", "parent": "drop01", "label": "Content", "settings": {"tag": "ul", "_hidden": {"_cssClasses": "brx-dropdown-content"}}},
  {"id": "drpln1", "name": "text-link", "parent": "drpcnt", "label": "Nav link", "settings": {"text": "Bodywork", "link": {"type": "external", "url": "/bodywork/"}}},
  {"id": "tglcls", "name": "toggle", "parent": "navi01", "label": "Toggle (Close: Mobile)", "settings": {"_hidden": {"_cssClasses": "brx-toggle-div"}}},
  {"id": "tglopn", "name": "toggle", "parent": "nav001", "label": "Toggle (Open: Mobile)", "settings": {}}
]
```

Structural facts: the items wrapper is a `block` with `tag: "ul"` and the
`brx-nav-nested-items` marker; a dropdown's content child is a **`div`**
(not a block) with `tag: "ul"` and `brx-dropdown-content`; the mobile close
toggle carries `brx-toggle-div` inside the items block; the open toggle is
a sibling of the items block, outside it. Without the markers the mobile
overlay and submenus do not work.

Parent keys: `mobileMenu` (a breakpoint key, `custom` +
`mobileMenuCustomBreakpoint`, `always`, `never`; default
`mobile_landscape`), `mobileMenuWidth/Height/Position/BackgroundColor`,
`mobileMenuContentPadding`, `mobileMenuAlignItems`,
`mobileMenuJustifyContent`.

**Mega menu**: set `megaMenu: true` on the **dropdown** (optionally
`megaMenuSelector` / `megaMenuSelectorVertical` for the reference box) and
build a real layout (blocks, headings, links, images) inside its content
`div`. Other dropdown keys: `toggleOn` (hover default), `multiLevel`,
`static`, `icon`, `ariaLabel`.

## offcanvas

```json
[
  {"id": "off001", "name": "offcanvas", "parent": "hdr001", "settings": {"direction": "right", "width": "min(90vw, 26rem)", "closeOn": "esc"}},
  {"id": "offi01", "name": "block", "parent": "off001", "label": "Content", "settings": {"_hidden": {"_cssClasses": "brx-offcanvas-inner"}}},
  {"id": "offx01", "name": "text-basic", "parent": "offi01", "settings": {"text": "Panel content"}},
  {"id": "offc01", "name": "toggle", "parent": "offi01", "label": "Toggle (Close)", "settings": {"ariaLabel": "Close"}},
  {"id": "offb01", "name": "block", "parent": "off001", "label": "Backdrop", "settings": {"_hidden": {"_cssClasses": "brx-offcanvas-backdrop"}}}
]
```

The inner block (`brx-offcanvas-inner`) is required; the backdrop block is
optional (omit it to disable the backdrop). Open it from anywhere with a
`toggle` whose `toggleSelector` targets the offcanvas (`#brxe-off001`). Parent
keys: `direction`, `effect`, `closeOn`, `width`, `height`,
`transitionDuration`, `noScrollBody`, `openByDefault`, `ariaLabel`.

## back-to-top

`back-to-top` with an `icon` and a `text` child (visible content only);
parent keys `visibleAfter`, `visibleOnScrollUp`, `smoothScroll`.

## Verifying a nestable

`bricks-get-content summary: true` shows the child structure; on the front
end confirm the composite initialises (slides/tabs/accordion respond) — a
successful Novamira write never reports a missing role child.
