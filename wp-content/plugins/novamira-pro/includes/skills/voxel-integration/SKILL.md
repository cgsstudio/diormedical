---
name: voxel-integration
description: >-
  Use when the user works with the Voxel theme — a directory, listing, or
  marketplace site built on Voxel. Activate on mentions of "Voxel", "Voxel post
  type", "Voxel field", "listing", "Voxel places/collection/profile", inspecting
  a Voxel site's content model, reading a listing's field values, or creating,
  editing, or deleting listings. Covers discovery (list/inspect post types,
  fields, filters, order, templates, settings; list posts; read field values)
  and content CRUD (create a listing with field values, edit it, trash/delete
  it), plus the field-type prop reference, a post's relation graph, the
  per-post-type template slots (create, bind, unbind the single / card /
  archive / form templates), and Voxel dynamic data — discovering and rendering
  dynamic tags like @post(title) or @user(email) (VoxelScript). Do NOT activate
  for generic WordPress posts
  unmanaged by Voxel, for other themes (Astra, Avada, OceanWP), or for
  Elementor widget editing.
---

# Voxel integration

Voxel is a directory / marketplace theme. Its content model (post types, fields,
filters, templates, settings) is not stored as WordPress objects — it lives in
`voxel:*` JSON options, and field values are saved as post meta keyed by field
key. These abilities let an agent discover that model, read listing content, and
create / edit / delete listings.

## When to use

- The user asks what post types, fields, or filters a Voxel site has.
- The user wants to read a listing's field values.
- The user wants to create, edit, or delete a listing (a Voxel post).
- The user is auditing or documenting a Voxel site's structure before making
  changes elsewhere.

Do NOT use for:

- Plain WordPress posts/pages that Voxel does not manage (these abilities reject
  non-Voxel post types on purpose).
- Other themes or page builders.

## Minimum version

These abilities require the **Voxel theme 1.7.8.3 or newer**. Voxel has no
version constant, so the version is read from its `style.css` header.

- If `novamira/voxel-check-setup` is **missing from your tool list**, Voxel is
  not the active theme — nothing else here will work.
- If check-setup returns `theme.min_satisfied = false`, Voxel is present but too
  old. Every other `voxel-*` ability returns `voxel_version_unsupported`. Surface
  the gap to the user; do not work around it.

## Domain model

- **Post type** — a Voxel-managed content type (e.g. `places`, `collection`,
  `profile`, plus `post`). Identified by its key. Native WordPress types (`page`)
  and other plugins' types are not Voxel post types and are rejected.
- **Field** — a typed input on a post type (`text`, `location`, `work-hours`,
  `repeater`, `taxonomy`, `image`, ...). Values are stored as post meta keyed by
  the field key. Layout-only fields have a `ui-` type prefix (`ui-step`,
  `ui-heading`) and hold no value.
- **Filter** — a search filter on a post type (`keywords`, `location`, `terms`).
- **Order** — a sort option shown on archives.
- **Templates** — the Elementor template ids assigned per post type
  (`single`, `card`, `archive`, `form`). Read via `get-post-type
  include=["templates"]`; create/bind/unbind them with the
  `*-post-type-template` abilities (R24).
- **Settings** — the raw per-post-type settings slice.

## Discover first

Always work top-down; each step narrows the next:

1. `novamira/voxel-check-setup` — confirm the theme is active and the version is
   satisfied; read `content.post_type_keys`.
2. `novamira/voxel-list-post-types` — compact rows (key, labels, public,
   field/filter counts).
3. `novamira/voxel-get-post-type` — one post type; pass `include` for the
   sections you need.
4. `novamira/voxel-get-field` — one field's full definition.
5. `novamira/voxel-list-posts` — posts of a type (compact rows).
6. `novamira/voxel-get-post` — one post, with `include:["values"]` for field
   values.

## Workflows

### R1 — Map a site's content model

```
novamira/voxel-check-setup {}
novamira/voxel-list-post-types {}
novamira/voxel-get-post-type { "post_type": "places", "include": ["fields", "filters"] }
```

Returns the post type's fields and search filters as compact rows. Read one
field in full only when you need its props:

```
novamira/voxel-get-field { "post_type": "places", "field_key": "location" }
```

### R2 — Inspect a post type's templates, order, and settings

```
novamira/voxel-get-post-type { "post_type": "places", "include": ["order", "templates", "settings"] }
```

`templates` maps each slug (`single`, `card`, `archive`, `form`) to its Elementor
template id. To change those assignments, see R24.

### R3 — Read a listing and its values

```
novamira/voxel-list-posts { "post_type": "places", "status": "publish", "per_page": 20 }
novamira/voxel-get-post { "post_id": 19223, "include": ["values"] }
```

Values come back per field as `{ type, read_ok, value, truncated, full_size }`.
Complex fields (location, work-hours, repeater) return decoded arrays. When
`truncated` is true, `full_size` gives the real byte size — raise
`max_field_bytes` (ceiling 65536) to get the full value. Do not fall back to raw
`get_post_meta()` via `execute-php`: the get-post surface applies a per-field
`read_ok` access check and truncation that the raw accessor silently skips.

### R4 — Read only specific field values

```
novamira/voxel-get-post { "post_id": 19223, "include": ["values"], "fields": ["title", "location"] }
```

`fields` limits which values are read on posts that have many fields.

### R5 — Create a listing

Discover the fields first, then create. Required fields are enforced for
publish/pending; use draft to save partial data.

```
novamira/voxel-get-post-type { "post_type": "places", "include": ["fields"] }
novamira/voxel-create-post {
  "post_type": "places",
  "title": "Blue Bottle Coffee",
  "status": "publish",
  "fields": {
    "location": { "address": "1 Ferry Building, SF", "latitude": 37.7955, "longitude": -122.3937 }
  }
}
```

Field value shapes: text = string, number = number, date = `{date: "Y-m-d"}` with
an optional `time: "H:i"` (a plain `"2026-09-18"` string is accepted too and
normalized for you), location = `{address, latitude, longitude}`, taxonomy =
array of term slugs, image/file = array of attachment ids, switcher = boolean.

The response is:

```
{
  "post_id": 19224,            // feed this to get-post / edit-post / delete-post / render-dynamic-data
  "post_type": "places",
  "status": "publish",
  "title": "Blue Bottle Coffee",
  "slug": "blue-bottle-coffee",
  "permalink": "https://example.com/places/blue-bottle-coffee/",
  "indexed": true,             // read back from the search index, not assumed
  "fields_written": ["location"],
  "fields_dropped": []
}
```

`edit-post` returns the same shape; `delete-post` returns
`{post_id, mode, ok}`. The key is `post_id` everywhere a write refers to the
post, matching the `post_id` input of every other post ability, so results
chain without renaming.

A value the field **rejects** refuses the whole create (`voxel_field_validation`,
`data.fields` maps each bad field to its message) and no post is created. But not
every bad value is rejected: several field types **discard** a value they cannot
parse rather than raising. The post is then created without it, and because the
write did run, `fields_written` still lists the key — so **`fields_dropped` is the
one to read**. It names every key whose value was accepted and then thrown away.
An empty `fields_dropped` is the signal that everything you sent was stored.

### R6 — Edit a listing (partial merge)

```
novamira/voxel-edit-post {
  "post_id": 19219,
  "status": "publish",
  "fields": { "location": { "address": "New address", "latitude": 40.0, "longitude": -73.0 } }
}
```

Only the keys you send change; everything else is left alone. Setting a field to
an empty value clears it. A value the field rejects refuses the whole edit and
changes nothing; a value it merely discards lets the edit succeed without that
value — the same asymmetry as R5.

### R7 — Trash or delete a listing

```
novamira/voxel-delete-post { "post_id": 19219 }              # trash (recoverable)
novamira/voxel-delete-post { "post_id": 19219, "force": true } # permanent
```

### R8 — Browse taxonomies and terms

```
novamira/voxel-list-taxonomies {}
novamira/voxel-list-terms { "taxonomy": "places_category", "per_page": 50 }
novamira/voxel-get-term { "term_id": 161 }
```

`list-terms` returns compact rows (id, name, slug, parent, count, order); read a
term's icon, color, image, geo area and post counts with `get-term`. Use `parent`
(0 = top-level) to walk a hierarchical taxonomy.

### R9 — Create / edit / delete a term

```
novamira/voxel-create-term { "taxonomy": "places_category", "name": "Bakeries", "icon": "svg:19104", "color": "#6933ff" }
novamira/voxel-edit-term { "term_id": 340, "name": "Bakery", "parent": 161 }
novamira/voxel-delete-term { "term_id": 340 }
```

Term extras: `icon` (a Voxel icon string), `color` (hex), `image` (attachment
id), `area` (geo bounds `{address, swlat, swlng, nelat, nelng}`), `order`. An
empty value clears an extra. `parent` must be an existing term in the same
taxonomy; a duplicate name under the same parent is refused. Deleting a term
re-parents its children (WordPress behavior), it does not delete them.

### R10 — Create a post type and give it fields

```
novamira/voxel-create-post-type { "key": "event", "singular": "Event", "plural": "Events" }
novamira/voxel-add-field { "post_type": "event", "type": "text", "key": "venue", "label": "Venue" }
novamira/voxel-add-field { "post_type": "event", "type": "date", "key": "starts_at", "label": "Start date", "required": true }
```

`create-post-type` writes the config; the type registers on the next request
(`registers_on_next_request: true`). `key` must be a fresh lowercase slug (max
20 chars) and cannot collide with a WordPress/Voxel reserved key or an existing
type. `add-field` needs a registered field type — list them with
`novamira/voxel-get-field-type-schema {}` and read a type's prop reference with
`{ "field_type": "image" }` (see R21); the `product` type is not supported here.
Common types include `text`, `number`, `date`, `switcher`, `select`, `taxonomy`,
`location`, and `image`. Put type-specific settings under `props`.

### R11 — Edit a post type or a field

```
novamira/voxel-edit-post-type { "post_type": "event", "singular": "Happening" }
novamira/voxel-edit-field { "post_type": "event", "field_key": "venue", "label": "Location", "required": true }
novamira/voxel-reorder-fields { "post_type": "event", "order": ["starts_at", "venue"] }
```

`edit-post-type` and `edit-field` are partial merges — send only what changes.
A field's `type` and `key` are immutable (changing them would strip stored
values); pass a fresh `add-field` instead. `reorder-fields` accepts a partial
`order`; any field you leave out keeps its place after the listed ones, so no
field is ever dropped. Changing a post type's search filters or indexing is
intentionally not available here (it rebuilds the search index) — do that in the
Voxel admin.

### R12 — Create / edit a taxonomy

```
novamira/voxel-create-taxonomy { "key": "event_type", "singular": "Event type", "plural": "Event types", "post_types": ["event"] }
novamira/voxel-edit-taxonomy { "taxonomy": "event_type", "hierarchical": true }
```

A taxonomy must be attached to at least one existing post type. `key` follows the
same rules as a post-type key (fresh lowercase slug, max 32, not reserved).
`hierarchical` and `publicly_queryable` are booleans. Registers on the next
request. Once it exists, add its terms with R9.

### R13 — Delete a field, post type, or taxonomy

```
novamira/voxel-delete-field { "post_type": "event", "field_key": "venue" }
novamira/voxel-delete-taxonomy { "taxonomy": "event_type" }               # terms kept
novamira/voxel-delete-taxonomy { "taxonomy": "event_type", "delete_terms": true }
novamira/voxel-delete-post-type { "post_type": "event" }                  # posts kept
novamira/voxel-delete-post-type { "post_type": "event", "delete_posts": true }
```

Deleting a post type always drops its leftover `voxel_index_<type>` search tables.
By default the existing posts and terms are kept (they become unmanaged once the
type/taxonomy stops registering); pass `delete_posts` / `delete_terms` to also
remove them. These deletes are permanent config changes.

### R14 — Inspect orders (read-only)

```
novamira/voxel-list-orders { "status": "completed", "limit": 50 }
novamira/voxel-get-order { "order_id": 812, "include": ["items", "pricing", "customer"] }
```

`list-orders` returns compact rows; filter by `customer_id`, `vendor_id`, `status`,
`shipping_status` or `payment_method`. `get-order` returns the summary (including the
gateway transaction id) and, with `include`, its line items, pricing breakdown, a
coarse shipping destination (fulfillment status + city/state/zip/country, not the
full name/street/phone) and a customer summary (id + display name, never email).
These are **read-only and never contact the payment provider** — `get-order` reads
the stored order without triggering a gateway sync. Orders are scoped to the store's
current payments mode (see Gotchas).

### R15 — Browse product types, plans and roles (read-only)

```
novamira/voxel-list-product-types {}
novamira/voxel-get-product-type { "product_type": "simple-product", "include": ["fields"] }
novamira/voxel-list-plans {}
novamira/voxel-get-plan { "plan": "premium", "include": ["pricing"] }
novamira/voxel-list-roles {}
novamira/voxel-get-role { "role": "subscriber" }
```

`get-plan include=["pricing"]` shows the price tiers, which are linked to Stripe and
are read-only reference values. `list-plans` hides archived plans unless you pass
`include_archived: true`.

### R16 — Check a user's membership (read-only)

```
novamira/voxel-get-user-membership { "user_id": 42 }
```

Returns the membership type (default / order / subscription), whether it is active,
the active plan (key + label), and the linking `order_id` when the plan came from an
order — for the store's current payments mode. It never exposes the raw Stripe
subscription or price ids.

### R17 — Read the community timeline (read-only)

```
novamira/voxel-list-timeline { "post_id": 611, "feed": "post_reviews", "limit": 50 }
novamira/voxel-get-status { "status_id": 4120 }
novamira/voxel-list-replies { "status_id": 4120 }
```

`list-timeline` returns approved statuses (activity posts, reviews with a
`review_score`, post-wall entries); filter by `post_id`, `user_id` or `feed`
(`post_reviews`, `post_wall`, `post_timeline`, `user_timeline`). **Only approved
content is ever returned** — pending/unmoderated statuses and replies are never
shown, and `get-status` on a pending id reads as not found. Content is truncated
to a `{value, truncated, full_size}` marker (`max_field_bytes`, default 4096).

### R18 — Notifications (read + mark)

```
novamira/voxel-list-notifications { "user_id": 42 }
novamira/voxel-get-notification { "notification_id": 980 }
novamira/voxel-mark-notification { "notification_id": 980, "seen": true }
```

`list-notifications` needs a `user_id` and returns compact rows; `get-notification`
adds the rendered subject line and link. `mark-notification` flips only the `seen`
flag on one row and is reversible. The subject describes the event and never
contains private message contents.

### R19 — Follow graph (read-only)

```
novamira/voxel-list-followers { "type": "user", "id": 42, "direction": "followers" }
novamira/voxel-list-followers { "type": "post", "id": 611, "direction": "following" }
novamira/voxel-get-follow-status { "from_type": "user", "from_id": 42, "to_type": "post", "to_id": 611 }
```

`direction: "followers"` = who follows the subject; `"following"` = who/what the
subject follows. `get-follow-status` returns `accepted`, `requested`, `blocked` or
`none`. Read-only — there is no follow/unfollow write here.

### R20 — Site templates and settings

```
novamira/voxel-list-templates {}
novamira/voxel-apply-template { "area": "header", "template_id": 19239 }
novamira/voxel-clear-template { "area": "header" }
novamira/voxel-list-settings {}
novamira/voxel-get-setting { "path": "messages.maxlength" }
```

`list-templates` shows the site areas and their assigned template ids.
`apply-template` assigns an **existing** template/page to an area (the target must
exist and match the area's expected `page`/`template` type); it returns the
previous id so the change can be reverted. `clear-template` unassigns an area
without deleting the template. Both take a lock and are a **visible site change**.
`list-settings` / `get-setting` read the configuration with all secret values
(API keys, client secrets) redacted; a secret path is refused outright.

### R21 — Discover field types and their props

```
novamira/voxel-get-field-type-schema {}
novamira/voxel-get-field-type-schema { "field_type": "image" }
```

Without input, lists every field type registered on this site (from Voxel's live
config — custom types show too). With `field_type`, returns `common_props` (the
base props every field shares) and `type_props` (type-specific settings — for
`file`/`image`: `allowed-types` MIME list, `max-size` in kilobytes, `max-count`).
Each prop row carries `verified`: `true` means the prop name is confirmed
against the Voxel theme source (1.7.8.3); `false` means it is documented
secondhand — confirm it with `novamira/voxel-get-field` on an existing field of
the same type before relying on it. An empty `type_props` means no curated
reference exists for that type, not that the type has no settings.

### R22 — Read a post's relation graph

```
novamira/voxel-list-related-posts { "post_id": 19223 }
novamira/voxel-list-related-posts { "post_id": 19223, "relation_key": "linked_places", "direction": "outgoing" }
```

Reads the edges Voxel's post-relation fields persist in the `voxel_relations`
table. `direction: "outgoing"` = rows where the post is the parent;
`"incoming"` = rows where it is the child; default `both`. Which side owns the
relation field depends on its `relation_type`: `has_one`/`has_many` store the
owner as parent, so its selected posts are its outgoing rows;
`belongs_to_one`/`belongs_to_many` store the owner as child, so its selected
posts are its incoming rows. `relation_key` is the field key, or the field's
`custom_key` when it enables `use_custom_key`. Each row has the related post
(id, title, post type), the relation key, the direction, and the stored order.
Read-only — relations are written through the post-relation field value on
`voxel-create-post` / `voxel-edit-post`, never here.

### R23 — Dynamic data (discover + render)

Voxel's dynamic data system (also called dynamic tags) resolves VoxelScript
expressions like `@post(title)` anywhere the theme accepts them — Elementor
widgets, email subjects, navigation labels, field defaults.

```
novamira/voxel-list-dynamic-data {}
novamira/voxel-list-dynamic-data { "group": "post", "post_type": "places" }
novamira/voxel-list-dynamic-data { "include_modifiers": true }
novamira/voxel-render-dynamic-data { "expression": "@post(title).fallback(Untitled)", "post_id": 19223 }
```

`list-dynamic-data` with no input returns the group summary and the post type
keys; with `group` it returns that group's property tree as flat dot-paths —
for `group: "post"` pass `post_type`, because each post type's configured
fields produce a different `@post(...)` tree. Rows carrying a `subgroup` nest
another group's whole tree (list that group separately). `include_modifiers`
lists the modifier registry.

`render-dynamic-data` resolves an expression against **explicitly bound entity
contexts**: `post_id` binds `@post` and derives `@author`, `user_id` binds
`@user`, `term_id` binds `@term`; `@site` always works with no context. Entity
contexts never fall back to ambient request state, so entity tags are
deterministic. One exception by design: the site group method
`@site().query_var(name)` reads Voxel's ambient **visibility-context stack**
(`$GLOBALS['vx_visibility_context_stack']`) first and falls back to the
**current request's** `$_GET`. The ability neither supplies nor clears that
stack, so a render using `query_var` can depend on ambient Voxel state — a
warning is emitted when `query_var` appears in the expression.

Expression grammar essentials:

- A tag is `@group(property.path)` followed by zero or more modifiers:
  `@post(title).uppercase().truncate(40)`. Modifiers run left to right.
- Fallback for empty values: `@post(phone).fallback(No phone listed)`.
- Conditionals are modifier chains:
  `@post(status.key).is_equal_to(publish).then(Live).else(Not live)`.
  Control structures gate the modifiers after them.
- `@value()` inside a modifier argument refers to the value being modified.
- Escaping: `\)` `\.` `\\` inside property paths; `\(` `\)` `\,` `\\` inside
  modifier arguments. Nested tags in arguments are rendered recursively.
- Engine limits: the tokenizer's nominal maxima are 32 chars for a group key,
  60 for one path segment, 240 for the whole path, and 100 for a modifier key —
  but its `<=` loops accept one character more, so the effective maxima are
  33 / 61 / 241 / 101. Stay within the nominal values.

Resolution semantics — read the output with these in mind:

- A tag whose **group has no context** stays as literal text in the output
  (and a warning names it). Supply the matching id input.
- An **unknown property** renders as an empty string — chain `.fallback()` to
  distinguish "unknown/empty" from a real value.
- An **unknown modifier** is silently ignored.

### R24 — Bind a post type's templates (single / card / archive / form)

A post type renders through four base template slots: `single` (the listing
page), `card` (the preview card in search results and grids), `archive` (the
archive/search page) and `form` (the front-end create/edit page). single/card/
archive are `elementor_library` documents; form is a WP page. Without a bound
template, a listing page renders blank — this recipe is what makes a post type
built through R10 actually render.

```
novamira/voxel-create-post-type-template { "title": "Events: Single", "template_key": "single" }
novamira/voxel-apply-post-type-template { "post_type": "event", "template_key": "single", "template_id": 19310 }
novamira/voxel-get-post-type { "post_type": "event", "include": ["templates"] }
novamira/voxel-clear-post-type-template { "post_type": "event", "template_key": "single" }
```

`create-post-type-template` creates a new, **unbound**, empty document of the
right kind for the slot (the Elementor document type for single/card/archive, a
page for form) — it takes no post type on purpose; binding is `apply`'s job.
`apply-post-type-template` validates the target (elementor_library id for
single/card/archive, page id for form), corrects its Elementor document type
the way Voxel's own admin does, and returns the previous id so the change can
be reverted. `clear-post-type-template` unbinds without deleting the document.
A freshly created template renders empty until a human designs it in Elementor
(see the blank-page gotcha); on Voxel sites build those layouts with **legacy
v3 Elementor widgets** (see "Elementor dynamic tags" below). Voxel re-creates
missing single/card/archive templates when an admin opens the post type in the
Voxel editor screen, so a cleared slot for those keys lasts only until then.

## Elementor dynamic tags: build Voxel templates with legacy v3 widgets

Rule: **on Voxel sites, put `@tags()` dynamic-data expressions only in legacy
v3 widgets — any v3 widget works (Elementor core, Pro, and third-party alike).
Never put a `@tags()` string into an atomic v4 element (`e-heading`,
`e-paragraph`, ...): the write succeeds, the editor shows no error, and the
tag renders literally on the frontend.** `novamira/voxel-check-setup` →
`integrations.elementor_atomic` tells you whether the atomic runtime is
present on the site.

Why, in brief:

- Voxel hooks the v3 **control layer**, not widgets: it re-registers
  Elementor's text-family control types (text, textarea, number, wysiwyg,
  color, media, gallery, icons, url, repeater, ...) with subclasses whose
  `get_value()` renders `@tags()` values. Re-registering an existing control
  type replaces it globally, so every v3 widget built on those controls
  resolves tags — a core `heading` or `text-editor` as much as a Voxel widget.
  Six other registered Voxel controls (loop, relation, post-select,
  term-select, nested-repeater, visibility) do override `get_type()` — those
  are new Voxel-own control types, not global replacements.
- Atomic v4 elements never pass through the controls manager: their typed
  props (`{$$type, value}`) go through Elementor's props resolver, so a flat
  `@tags()` string is architecturally unresolvable there — not a per-widget
  gap that some atomic element might not have.
- Voxel's atomic integration does resolve a tag in exactly one shape: a
  `{"$$type": "vx", "value": "@tags()..."}` envelope on a dynamic-capable
  prop, which only Voxel's own editor UI writes. The Novamira Elementor
  abilities cannot produce that envelope today — their atomic validator
  accepts only the prop's own `$$type` (or `dynamic`) and rejects `vx` before
  saving. Do not chase this path; use a v3 widget.
- `_vx_visibility` / `_vx_loop` / `_vx_dynamic_css` on atomic elements come
  from a separate injection path: Voxel renders those values itself, with
  `elementor/frontend/before_render` running the dynamic styling and the
  atomic element data injected on `elementor/frontend/after_render` at
  priority 1000. Their presence does NOT imply text-tag support.

## Simple operations via novamira/execute-php

For one-off reads that do not warrant a dedicated ability, use the parent
`novamira/execute-php`. Snippets:

Count posts per Voxel post type:

```php
$out = [];
foreach ( \Voxel\Post_Type::get_voxel_types() as $key => $pt ) {
    $out[ $key ] = array_sum( (array) wp_count_posts( $key ) );
}
return $out;
```

To read a Voxel setting, always use the `novamira/voxel-get-setting` ability —
never `\Voxel\get( 'settings.…' )` through `execute-php`. The ability refuses
secret paths (`api_key`, `client_secret`, …) and redacts any nested secret; the
raw accessor does neither, so `\Voxel\get( 'settings.stripe.secret_key' )` would
return the key in clear text. `execute-php` is for non-setting operational reads
only.

List the field keys of a post type without the full definitions:

```php
return array_keys( \Voxel\Post_Type::get( 'places' )->get_fields() );
```

Count how many listings each author owns for a post type:

```php
global $wpdb;
return $wpdb->get_results( $wpdb->prepare(
    "SELECT post_author, COUNT(*) AS n FROM {$wpdb->posts}
     WHERE post_type = %s AND post_status = 'publish' GROUP BY post_author",
    'places'
), ARRAY_A );
```

## Gotchas

- **Field key = meta key.** A field's value lives in post meta under the field's
  key. Complex fields JSON-encode their value in meta; `voxel-get-post` decodes
  it for you.
- **UI fields have no value.** Types starting with `ui-` (step, heading, image,
  html) are layout only and are skipped by `voxel-get-post` values.
- **Non-Voxel types are rejected.** `page`, `post` (if not Voxel-managed), and
  other plugins' types return `voxel_not_found` / `voxel_invalid_input`. This is
  intentional — these abilities only speak Voxel.
- **Trashed / revision / auto-draft posts are rejected** by `voxel-get-post`. Draft,
  pending, private, and scheduled (future) posts ARE readable — the surface is
  admin-gated, so it mirrors what an admin sees in wp-admin.
- **Structured field values are objects.** `location`, `work-hours` and similar
  fields return a JSON object (map); repeaters and galleries return a list.
- **Search index is separate.** Voxel keeps its own `voxel_index_<type>` tables
  for front-end search; these abilities read WordPress posts and post meta, not
  the index.
- **Large values are truncated.** Watch `truncated` and `full_size`; the default
  per-field cap is 4096 bytes.
- **Child themes.** If a child theme is active, the version is still read from
  the Voxel parent, so detection is correct.
- **Writes are validated like the form.** create/edit run each value through the
  field's own sanitize + validate, so a bad value is rejected with a clear
  per-field message and nothing is written (the operation is atomic).
- **Required fields.** Enforced for publish and pending; skipped for draft. To
  publish a listing you must provide every required field (see get-post-type
  include=["fields"], `required: true`).
- **title and content are WordPress fields.** `title` sets post_title; `content`
  sets post_content (the Voxel "description" field). Put custom fields in the
  `fields` object. WordPress refuses a post with an empty title AND empty content.
- **The search index updates automatically — when there is an index to update.**
  create/edit reindex the post; trash and delete remove it. You never reindex
  manually. But a post type only has a search index once a `voxel_index_<key>`
  table exists for it, and a type created through `create-post-type` does not get
  one on its own. While it is missing, every index write fails silently and the
  type is invisible to Voxel's front-end search. `indexed` in the create/edit
  response is read back from the index, so it tells you the truth; the full
  picture is `novamira/voxel-check-setup` → `index_tables_missing`.
- **Delete is trash by default.** `force: true` is permanent and also cleans up
  related Voxel data (index, relations, timeline, followers).
- **Uploads are not supported through the API.** For image/file fields pass an
  array of existing WordPress attachment ids (numbers), not new uploads. A new
  upload is refused with a clear error.
- **Field value shapes.** location = `{address, latitude, longitude}` (all three
  required, or it is dropped); date = `{date: "Y-m-d"}` with an optional
  `time: "H:i"` — a bare `"2026-09-18"` string is accepted and normalized for you;
  taxonomy = an array of term slugs (read back as slugs too).
- **`fields_dropped` is where a lost value shows up, not `fields_written`.**
  Several field types return an empty value from their own sanitizer instead of
  raising — an unknown `select` choice, a `location` whose latitude or longitude
  is exactly `0` (the equator and the Greenwich meridian: Voxel tests those
  coordinates for truthiness, so zero reads as absent). The write still runs, so
  the key appears in `fields_written`; `fields_dropped` is what tells you the
  value did not survive. Clearing a field on purpose is never listed there.
  One limit: `fields_dropped` compares whole values and list lengths, so it does
  not catch a value that survives with its *contents* stripped — a repeater row
  whose unknown sub-fields were discarded still counts as one row. Read the post
  back when a structured value matters.
- **Structure changes register next request.** Creating a post type or taxonomy
  writes the config now, but WordPress only registers it on the next request, so
  `registers_on_next_request: true` is returned rather than the type being live
  in the same call. Content abilities (create-post, list-terms, …) see it on the
  following call. The search index table and the rewrite rules are built on that
  same next request, so permalinks and term archives work without any manual step.
- **A blank page and a 404 are different problems — do not confuse them.**
  - **Blank page on a listing you just created**, with the header and footer
    rendering normally: the post type's Elementor templates exist but are *empty*.
    Voxel creates single/card/archive templates automatically, and nobody has
    designed them yet. `voxel-get-post-type include=["templates"]` will show real
    ids. This is not something these abilities can fix — the layout is human work
    in Elementor. Say so rather than hunting a data bug.
  - **404 on a term archive or a post type archive**: the rewrite rules do not yet
    describe that type. This should not happen through these abilities, which
    defer the flush to a request where the type is registered. If it does, the
    site's permalink rules are stale for another reason — re-saving Settings →
    Permalinks rebuilds them.
- **Field type and key are frozen.** `edit-field` never changes a field's `type`
  or `key` (even if you pass them under `props`) because stored values are keyed
  by them. To change a field's type, delete it and add a new one — the old values
  are left behind.
- **Search filters / indexing are off-limits here.** `edit-post-type` refuses any
  `settings.indexing` or `settings.search` change; those rebuild the whole search
  index and belong in the Voxel admin. Everything else in a type's settings merges
  shallowly, so send nested settings whole, not half.
- **Builder writes are locked.** Post-type and taxonomy config each live in one
  shared option, so these abilities take a lock while they read-modify-write. Two
  agents editing structure at once take turns rather than clobbering each other.
- **Deleting structure keeps content by default.** `delete-post-type` /
  `delete-taxonomy` remove only the config unless you pass `delete_posts` /
  `delete_terms`; a deleted post type always has its orphaned search-index tables
  dropped.
- **Commerce is read-only (this integration).** The order / product-type / plan /
  role / membership abilities only read. There is no create/edit/delete of orders
  or plans here, and nothing touches the payment provider (no refunds, captures,
  cancellations or subscription changes) — those move real money and stay in the
  Voxel/Stripe admin.
- **Orders and memberships are mode-scoped.** A store runs in either test or live
  payments mode. `list-orders`, `get-order` and `get-user-membership` only see the
  data for the *current* mode, and report it as `test_mode`. An order that exists
  in the other mode reads as not found — check the store's mode if an order id
  seems missing.
- **Order amounts are raw.** Totals/subtotals come straight from the order's stored
  pricing in that order's own currency unit — they are not reformatted. A missing
  or non-numeric amount reads as null, never 0.
- **Private messages are out of scope.** This integration never reads, lists,
  searches, sends or exposes Voxel direct messages. There is no messages ability;
  do not attempt to reach `voxel_messages` through it.
- **Timeline reads are approved-only.** `list-timeline`, `get-status` and
  `list-replies` never return pending/unmoderated content, and they do not act on
  the "current user" (so nobody's private pending posts leak). There is no
  moderation here — approving, declining or deleting community content is left to
  the Voxel admin.
- **Settings secrets are redacted.** `list-settings`/`get-setting` mask any secret
  value (API keys, client secrets) as `"__redacted__"`, and reading a secret path
  directly is refused. Payment/Stripe keys are a separate option and not exposed.
- **Field-type props: check the `verified` flag.** `get-field-type-schema` rows
  with `verified: true` are confirmed against the Voxel theme source (1.7.8.3);
  rows with `verified: false` are documented secondhand. Before writing an
  unverified prop through `add-field`/`edit-field`, confirm the name against a
  live field of the same type with `voxel-get-field`.
- **Relations need their table.** `list-related-posts` reads the
  `voxel_relations` table directly. A site where the relations module never ran
  its installer returns a clean `voxel_not_found` error — `voxel-check-setup` →
  `tables` shows which tables exist before you ask.
- **Visit analytics are out of scope.** There is no ability over the
  `voxel_visits` table; `voxel-check-setup` only reports whether it exists.
- **Template writes change the live site.** `apply-template` and `clear-template`
  edit which template renders a site area, under a lock, and return the previous id
  so you can revert. `apply-template` only accepts an id that already exists and
  matches the area's type; `clear-template` unassigns without deleting the template.
  The `*-post-type-template` abilities (R24) behave the same way for a post type's
  single/card/archive/form slots.
- **`@tags()` do not resolve inside atomic v4 Elementor elements.** A plain
  `@tags()` string in an `e-heading`/`e-paragraph` prop renders literally on the
  frontend while every write succeeds. Build Voxel layouts with legacy v3
  widgets (see "Elementor dynamic tags" above).
- **Rendering dynamic data is not fully side-effect free.** `@post(content)`
  applies `the_content` filters (shortcodes, third-party hooks execute), and
  stats/count properties (`visit_stats`, review/follower/timeline/wall counts)
  may populate Voxel's cache storage on a cold read (post meta, user meta or
  options depending on the group). Nothing is intentionally
  written, but do not treat `render-dynamic-data` as a pure read. The ability
  flags these with a best-effort `warnings[]` entry when it spots them in the
  expression text (an indirect read through a subgroup can slip past the scan).
- **Rendered dynamic data can be sensitive.** `@user(email)` returns the real
  email; `@post().meta(key)` / `@user().meta(key)` read arbitrary metadata. The
  ability is admin-gated precisely because Voxel's engine does no permission
  checks of its own — handle rendered output accordingly.
- **A literal `@group(...)` in render output is a missing context, not a bug.**
  The engine leaves tags of unknown groups untouched; the ability warns which
  context input to add. An empty string instead means unknown property or a
  genuinely empty value — `.fallback()` tells them apart.

## Conventions

- Slugs follow `novamira/voxel-<verb>-<object>`.
- `list-*` returns compact rows; `get-*` returns full detail with opt-in
  `include`.
- Errors carry a stable code (`voxel_not_active`, `voxel_version_unsupported`,
  `voxel_invalid_input`, `voxel_not_found`) plus a human message. Branch on the
  code, not the message text.
