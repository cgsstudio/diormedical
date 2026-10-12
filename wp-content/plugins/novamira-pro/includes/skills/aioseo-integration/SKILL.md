---
name: aioseo-integration
description: Activate when working with All in One SEO (AIOSEO) on a WordPress site — setting a post's or page's SEO title, meta description, focus keyphrase, indexing (index/noindex), canonical, social (Facebook / X) preview, cornerstone (pillar) content, or schema type; optimizing a category or tag (Pro); managing redirects (Pro); or reading site-wide title/meta templates. Establishes the friendly SEO shape, the robots "use defaults" footgun AIOSEO hides, which operations are abilities vs plain execute-php, and the read→edit workflow.
---

# Working with All in One SEO (AIOSEO)

## When to use

Use these abilities when the task is about a page's or term's **search-engine
metadata** under AIOSEO: SEO title / meta description / focus keyphrase /
canonical / indexing / social preview / cornerstone / schema type, the site-wide
title & meta templates, or (Pro) term SEO and redirects. Not for editing page
content or layout — that is the builder's job.

## Why these are abilities (and what to do in plain PHP)

AIOSEO does **not** store SEO in postmeta. Per-post SEO lives in a custom
table (`wp_aioseo_posts`) reached through its `Post` model; per-term SEO and
redirects live in their own Pro tables. Writing those raw risks corrupting the
row (missing URL hashes, stale lookup cache). The abilities go through the
models, hide the robots footgun, and curate the shape. **Site-wide settings
writes** are a safe one-liner once you know the key — do those with
`novamira/execute-php` (recipes below), not an ability.

## The abilities at a glance

- `aioseo-check-setup` — active, version, edition (pro), which Pro surfaces are
  available (term SEO, redirects), and the targetable post types / taxonomies.
  **Call this first.**
- `aioseo-get-post-seo` / `aioseo-edit-post-seo` — a post or page's SEO.
- `aioseo-get-term-seo` / `aioseo-edit-term-seo` — a term's SEO (**Pro**).
- `aioseo-get-settings` — site-wide separator + title/meta templates.
- `aioseo-get-post-schema` / `aioseo-edit-post-schema` — read the post's schema
  state / toggle its default schema graph (a specific type → execute-php recipe).
- `aioseo-list-redirects` / `aioseo-create-redirect` / `aioseo-edit-redirect` /
  `aioseo-delete-redirect` — redirects (**Pro**).

## Domain model

### Post / page SEO

```
{
  focus_keyphrase: string,
  additional_keyphrases: [string],          // read-only here; reflects AIOSEO editor/importer data; no ability writes it
  seo_title: string,                        // AIOSEO smart tags: #post_title #separator_sa #site_title
  meta_description: string,
  canonical: string,
  pillar_content: bool,                     // cornerstone
  robots: { index: "default"|"index"|"noindex",
            follow: "follow"|"nofollow",
            advanced: ["noarchive"|"noimageindex"|"nosnippet"|"noodp"|"notranslate"],
            // SERP-preview limits, present on an explicit-robots row; -1 = unlimited, 0 = none
            max_snippet: int, max_image_preview: "large"|"standard"|"none", max_video_preview: int },
  facebook: { title, description, image, object_type },   // Open Graph
  twitter:  { use_og, title, description, image, card },
  schema: "enabled"|"off",                  // stored default-graph toggle; see get-post-schema for the full picture
}
```

`robots.index: "default"` means **inherit the global / post-type robots**, not
"indexed". Title / description use AIOSEO **smart tags** (`#post_title`,
`#separator_sa`, `#site_title`, `#tagline`, `#current_date`) — leave them intact
unless deliberately changing the pattern.

### Term SEO (Pro)

Same shape as a post minus `focus_keyphrase` / `schema`. Identify by
`term_id` + `taxonomy`. `check-setup.supports_term_seo` must be true.

### Schema

AIOSEO renders a post's **default (primary) schema graph** as the post type's own
default type — posts → **BlogPosting**, pages → **WebPage** — and **resets that
type at render**, so the default graph's type is NOT per-post changeable.
`get-post-schema` reports:

- `default_graph`: `"enabled"` or `"off"` — the **stored** per-post toggle.
- `default_type`: the schema.org type that default graph renders (the post-type default).
- `custom_graphs`: the types of any **custom** graphs stored on the post
  (`schema.graphs[]`, AIOSEO's Schema Generator store).
- `has_custom_graphs`: true when the post has any custom graphs (generator / raw
  JSON-LD / block); `edit-post-schema` never touches them.

**`default_graph` and `custom_graphs` are stored intent, not a guarantee they
render.** Run `check-setup` first: `pro: false` means AIOSEO's Pro schema renderer
is absent. Only that renderer consults the per-post toggle and emits
`schema.graphs[]`; without it the front end always emits the post-type default
graph and ignores custom graphs, while the stored values still read back exactly
as written. Verify the page's `application/ld+json` before reporting a schema
change as done, and say so when the graph list does not move.

`edit-post-schema` takes only `"default"` (store enabled) or `"off"` (store
disabled). To emit a **specific** schema.org type (Recipe, Product, FAQPage,
Event, HowTo, …) — which needs type-specific properties — add a custom graph
with execute-php (recipe below).

### Site-wide settings

`get-settings` (`scope`: general | titles | all) returns the title separator
(decoded) and the homepage title/description templates, plus the per-post-type
and per-taxonomy title & description templates. Read-only — change settings with
execute-php (below).

### Redirects (Pro)

`{ id, source_url, target_url, type, regex, enabled }`. `type` is the HTTP
status: 301 permanent, 302/307 temporary, 410 gone, 451 unavailable for legal
reasons (410/451 need no target). One redirect per `source_url`.

## Workflows

### Optimize a page's SEO

1. `aioseo-check-setup` → confirm active.
2. `aioseo-get-post-seo` { post_id } → see current values.
3. `aioseo-edit-post-seo` { post_id, focus_keyphrase, seo_title, meta_description }.
   - Keep out of search: `{ post_id, robots: { index: "noindex" } }`.
   - Social: `{ post_id, facebook: { title, description, image } }`.

### Optimize a category / tag (Pro)

1. `aioseo-get-term-seo` { term_id, taxonomy }.
2. `aioseo-edit-term-seo` { term_id, taxonomy, meta_description, robots: { index } }.

### Set a post's schema type

The default graph's type is fixed by AIOSEO. To change its stored toggle:

1. `aioseo-edit-post-schema` { post_id, schema_type: "default" } to store enabled,
   or `"off"` to store disabled.

To emit a **specific** type (Recipe / Product / FAQPage / …), add a custom graph
via execute-php — see "Add a specific schema type" under execute-php below.

### Create a redirect (Pro)

1. `aioseo-check-setup` → `supports_redirects` must be true.
2. `aioseo-create-redirect` { source_url: "/old", target_url: "/new", type: 301 }.
3. `aioseo-list-redirects` { search: "old" } to find an id, then edit/delete.

## Use execute-php for these (no ability)

Site-wide settings **writes** are an execute-php one-liner — AIOSEO keeps the
settings in the `aioseo_options` (global) and `aioseo_options_dynamic`
(per-post-type / per-taxonomy) options as JSON, exposed through the live
`aioseo()->options` object. **Always write through the object and `->save(true)`,
never raw `update_option()`** — the title/description templates are "localized"
fields stored in a separate `_localized` layer that AIOSEO renders from (and that
`get-settings` reads); a raw option write does not touch it, so the edit is
silently lost. The object path syncs both:

```php
// Change the global title separator and homepage title template:
aioseo()->options->searchAppearance->global->separator    = '&#8211;'; // an en dash
aioseo()->options->searchAppearance->global->siteTitle    = '#site_title #separator_sa #tagline';
// Per-post-type title template (dynamic options):
aioseo()->dynamicOptions->searchAppearance->postTypes->post->title = '#post_title #separator_sa #site_title';
aioseo()->options->save( true );
aioseo()->dynamicOptions->save( true );
```

Local SEO (business info / locations), Link Assistant, and the sitemap settings
are likewise execute-php territory via `aioseo()->options->...` — read the
current shape first, then assign the specific key.

### Add a specific schema type (Recipe, Product, FAQPage, …)

A specific per-post schema type lives in `schema.graphs[]` (the Schema
Generator's store), NOT in the default graph — AIOSEO resets the default graph's
type at render, so writing a type there never renders. Each entry needs `id`,
`graphName`, and a **non-empty** `properties` object (an empty one is skipped).
Write through the Post model so the schema column and cache stay correct:

```php
$model  = AIOSEO\Plugin\Common\Models\Post::getPost( $postId );
$schema = json_decode( wp_json_encode( $model->schema ), true );
$schema['graphs'][] = [
    'id'         => '#aioseo-recipe-' . $postId,    // any unique id
    'graphName'  => 'Recipe',                        // Article, Product, FAQPage, Event, HowTo, …
    'properties' => [ 'name' => '#post_title' ],      // type-specific fields; MUST be non-empty
];
// Optional: store the generic default graph as disabled (rendering caveat above):
// $schema['default']['isEnabled'] = false;
$model->schema = wp_json_encode( $schema );
$model->save();
```

Fill `properties` with the fields the type needs for valid rich results (a Recipe
wants recipeIngredient / recipeInstructions, a Product wants offers, etc.).
`aioseo-get-post-schema` then lists the type under `custom_graphs` — but that only
confirms it was stored. Check the page's `application/ld+json` for the type before
reporting it as live; renderer behavior is explained under "Schema" above.

## Gotchas

- **`robots.index: "default"` is all-or-nothing.** AIOSEO gates per-post robots
  behind a single "use defaults" flag. `"default"` makes the post inherit ALL
  robots (and the edit resets follow/advanced); to set `follow` or `advanced`
  you must set `index` to `"index"` or `"noindex"`. The abilities handle the
  flag for you — just send the friendly labels.
- **Term SEO and redirects need AIOSEO Pro.** On the free edition those abilities
  return `aioseo_pro_required` (and the redirect ones are not even registered).
  Check `check-setup` first.
- **SEO is in custom tables, not postmeta.** Always read/write through these
  abilities (or `aioseo()`'s models in execute-php) — never `update_post_meta`.
- **Smart tags use `#`, not `%`, and only registered ids resolve.** The separator
  tag is `#separator_sa` — `#sep` is not a tag and renders literally in the
  title. Write `#post_title #separator_sa #site_title`. Unknown tags are not
  rejected on write and read back intact, so an unregistered tag only shows up in
  the rendered page. They are plain text; keep them intact.
- **One redirect per source_url.** `create-redirect` rejects a duplicate source;
  use `edit-redirect` with the id from `list-redirects`.

## Conventions

- Slugs: `novamira/aioseo-<verb>-<object>`. Reads are `get-*` / `list-*`
  (readonly); writes are `edit-*` (partial merge), `create-*`, `delete-*`.
- `edit-*` send only the fields to change; nested blocks (robots, facebook,
  twitter) merge field-by-field.
- A fresh post returns empty / default values, not missing fields.
