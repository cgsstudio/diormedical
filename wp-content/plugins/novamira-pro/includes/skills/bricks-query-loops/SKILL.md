---
name: bricks-query-loops
description: Companion of bricks-build-page for repeated, CMS-driven content in Bricks — blog/portfolio/team/testimonial/product grids, related strips, any list the customer edits over time. Covers the static-vs-loop decision, discovering the target post type (or proposing a CPT through the active field-plugin specialization), the loop shape that actually renders a grid (hasLoop on the card block, never on the grid container), per-item dynamic tokens, and Bricks query filters and pagination. Load when a section shows more than one of "the same thing".
---

# Query loops in Bricks

## Static or loop?

Repeated content on a WordPress site is a **query loop**, not N hand-typed
cards: posts, projects, case studies, products, services, team members,
events, testimonials, listings — anything the customer adds or edits over
time. Static markup traps the data inside Bricks and breaks search, archives,
single pages and related strips.

Static is the exception for fixed UI with bespoke copy authored for this
page: pricing tiers, feature pillars, "how it works" steps, hero, logo strip,
hand-picked testimonials, a small inline FAQ.

Three questions when in doubt — a "yes" to any means loop:

1. Would each item have its own detail page?
2. Would these items appear elsewhere on the site?
3. Will the customer add, remove or edit them without a developer?

The number of items at build time is irrelevant; an empty CPT with a wired
loop is the correct state to ship.

## Discover the target post type first

List what is registered before proposing a new CPT — mature sites usually
have it. Call the post-types list ability of the active field-plugin
specialization (`novamira/pods-list-pods`, `novamira/acf-list-post-types`,
`novamira/jetengine-list-post-types`, Meta Box / ACPT / ASE equivalents) and
match the user's wording to slug or label (plural/singular variation is
normal). When no specialization owns the type, a read-only
`novamira/execute-php` call
`return get_post_types(['_builtin' => false, 'public' => true], 'objects');`
lists every non-built-in public type regardless of how it was registered.

No matching CPT: if a field-plugin specialization is active, propose creating
the CPT through it and wire the loop on top. If none is active, stop and tell
the user — vanilla WordPress has no CPT UI; never register a CPT via raw
`register_post_type()` in agent code, and never fall back to a static grid.

## The loop shape that renders a grid

**Bricks repeats the element that carries the loop.** Grid styles go on a
plain `container` (or `block`); `hasLoop: true` + `query` go on its **direct
child block** — the card. Bricks renders one grid whose repeated cards flow
into the columns; dynamic tokens inside the card resolve per iteration.

```json
{"post_id": 42, "area": "content", "elements": [
  {"id": "svcsec", "name": "section",   "parent": 0,        "settings": {}},
  {"id": "svcgrd", "name": "container", "parent": "svcsec", "settings": {
    "_display": "grid", "_gridTemplateColumns": "repeat(2, 1fr)", "_gridGap": "var(--space-m)"}},
  {"id": "svccrd", "name": "block",     "parent": "svcgrd", "settings": {
    "hasLoop": true,
    "query": {"post_type": ["service"], "order": "ASC", "orderby": "title", "posts_per_page": 4, "objectType": "post"},
    "_cssGlobalClasses": ["<class-id-of card>"]}},
  {"id": "svcimg", "name": "image",     "parent": "svccrd", "settings": {"image": {"useDynamicData": "{featured_image}"}}},
  {"id": "svchd1", "name": "heading",   "parent": "svccrd", "settings": {"tag": "h3", "text": "{post_title}"}},
  {"id": "svctx1", "name": "text-basic","parent": "svccrd", "settings": {"tag": "p", "text": "{post_excerpt}"}},
  {"id": "svclnk", "name": "text-link", "parent": "svccrd", "settings": {"link": {"type": "meta", "useDynamicData": "{post_url}"}, "text": "See the service"}}
]}
```

- `hasLoop` on the grid container renders N one-card grids stacked
  vertically (verified on the front end: repeated `data-brx-loop-start`
  wrappers with one card each). On the section: N full-bleed bands.
- The looped block is the per-item template and the natural card boundary
  for a global class.
- `query.objectType`: `post` (with `post_type: ["slug"]`), `term`, `user`.
  The remaining `query` keys are passed to Bricks verbatim; confirm exact
  names against a working loop or the element schema — do not guess.
- **A `false` does not always mean "off".** Some builder settings are read by
  whether the key is THERE rather than by what it holds, so writing `false` can
  leave the feature switched on while every read-back agrees the value is
  `false`. The reverse also happens: for some members it is the ABSENCE of the
  key that switches a feature on, so removing it is not a safe way to turn
  something off either. Which of the two a given `query` member does is not
  something this skill can tell you, it differs between members, and reading
  the stored value back will not tell you.
- Because of that, do not treat any of these as a reliable off switch on their
  own: writing `false`, omitting the member, or removing it. What settles it is
  the FEATURE'S OWN OBSERVABLE on the rendered page — the markup, class or
  behaviour that appears only when the feature is on. Fetch the published page
  and look for that observable directly. `novamira/bricks-render-check` will
  tell you the page renders and its CSS is linked; it does not report query
  behaviour, so it cannot answer this question.
- If you must change one of these, change it and then verify against that
  observable. When a write did not take effect, `novamira/bricks-patch-elements`
  with `unset_settings: ["query.<member>"]` is how you remove a member — patch
  settings deep-merge, so simply omitting it from a patch leaves the old value
  in place — but verify the result rather than assuming removal means off.

## Filters and pagination

Bricks filter elements (`filter-select`, `filter-checkbox`, `filter-radio`,
`filter-search`, `filter-range`, `filter-datepicker`, `filter-submit`,
`filter-active-filters`) and `pagination` have three failure modes that look
like success. Follow this order:

1. `bricks-check-setup` → `query_filters_enabled` must be true **before** you
   write any filter; a filter saved while the feature is off is never indexed
   and stays inert until the content is re-saved.
2. Every filter and the pagination element sets `filterQueryId` to the id of
   the element that **carries the loop** (the card), never the grid. Place
   filters outside the looped element and never inside a component
   (instances remap ids and sever the link).
3. Sources: `filterSource: "taxonomy"` + `filterTaxonomy`; `"wpField"` for
   core fields; `"customField"` needs the separate custom-fields toggle.
4. Filters run on a background index (`bricks_indexer` cron). Until indexed,
   options show `(0)` and filtering is a no-op — wait or ask the user to open
   the Bricks settings filters tab.
5. Verify from the front end: fetch the page with `?brx_<filter-id>=<term-slug>`
   and confirm the loop shows only matching items with real counts.
