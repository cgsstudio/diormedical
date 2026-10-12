---
name: rank-math-integration
description: Activate when working with Rank Math SEO on a WordPress site — setting a post's or page's SEO title, meta description, focus keywords, indexing (index/noindex), canonical, social (Facebook / X) preview, cornerstone/pillar content, or schema type; optimizing a category or tag; or reading site-wide title/meta templates and indexing defaults. Establishes the friendly SEO shape, the robots-array trap Rank Math hides, which operations are abilities vs plain execute-php, and the read→edit workflow.
---

# Working with Rank Math SEO

This skill is for using the `novamira/rank-math-*` abilities together. Read it
once at the start of any Rank Math / SEO task and refer back when in doubt.

## When to use

Activate when the user asks to view or change SEO metadata managed by Rank Math:
a post or page's SEO title / meta description / focus keywords / canonical /
indexing / social preview / cornerstone / schema; a term's SEO; or site-wide
title & meta templates and indexing defaults.

Do **not** activate for other SEO plugins (Yoast, AIOSEO — different storage) or
for on-page content edits. Always call `novamira/rank-math-check-setup` first —
it reports the version, which **modules** are enabled (the schema abilities need
the `rich-snippet` module), and the public post types / taxonomies you can
target. If the Rank Math abilities are missing from your tool list, Rank Math is
not active.

## Why these are abilities (and what to do in plain PHP)

- **Abilities** — per-post SEO, per-term SEO, settings *reads*, and schema. They
  hide the robots-array trap, coerce the comma-separated focus keywords, and
  read modules from the persisted option.
- **execute-php** — site-wide settings *writes*, redirections (a free module,
  off by default), and module toggles. See the last section.

## The abilities at a glance

| Ability | Use |
|---|---|
| `rank-math-check-setup` | First call. Version, enabled modules, targetable types. |
| `rank-math-get-post-seo` | Read a post/page's full SEO. |
| `rank-math-edit-post-seo` | Change any subset of a post's SEO (partial merge). |
| `rank-math-get-term-seo` | Read a term's SEO. |
| `rank-math-edit-term-seo` | Change a term's SEO (partial merge). |
| `rank-math-get-settings` | Read site-wide settings (`scope`: general / titles / all). |
| `rank-math-get-post-schema` | Read a post's schema type (needs the rich-snippet module). |
| `rank-math-edit-post-schema` | Set a post's schema type. |

## Domain model

### Post / page SEO

`get-post-seo` / `edit-post-seo` speak one friendly shape — never the raw
`rank_math_*` postmeta:

```
{
  focus_keywords: [string],          // rank_math_focus_keyword (comma string)
  seo_title, meta_description, canonical,
  pillar_content: bool,              // cornerstone
  robots: { index: "default"|"index"|"noindex",
            follow: "follow"|"nofollow",
            advanced: ["noarchive"|"noimageindex"|"nosnippet", ...] },
  facebook: { title, description, image, image_id },
  twitter:  { title, description, image, image_id },
  primary_category: <term id>|null,
  schema_type: <string>              // read-only here; edit via the schema abilities
}
```

**The robots trap this hides.** Rank Math stores `rank_math_robots` as a token
**array** (e.g. `["noindex","nofollow"]`). `index` and `noindex` are mutually
exclusive, and the **absence of both** means "inherit the post-type default"
(not "indexed"). The ability exposes only `robots.index: default | index |
noindex` and builds the array for you. Use `"index"`/`"noindex"` to force, leave
`"default"` to inherit.

`seo_title`/`meta_description` may contain Rank Math variables (`%title%`,
`%sep%`, `%sitename%`); leave them intact unless deliberately changing the
pattern. `focus_keywords` is a list (stored comma-separated). The computed SEO
score is read-only and only returned when you pass `include_score: true`.

### Term SEO

`get-term-seo` / `edit-term-seo`, keyed by `term_id` + `taxonomy`. Same shape
minus pillar/primary/schema. Rank Math stores term SEO as ordinary term meta.

### Schema / rich snippet

`get-post-schema` / `edit-post-schema` manage the post's schema **type**
(Article, Product, Book, Event, …, or "off"). Needs the `rich-snippet` module —
`check-setup` reports `supports_schema`.

### Site-wide settings

`get-settings` (`scope`: general | titles | all) returns the title separator,
the knowledge-graph identity (organization/person), homepage templates, and
per-post-type / per-taxonomy title & description templates plus the **effective**
default robots. Each type carries a `custom_robots` flag: when `false` the type
inherits the site-wide default (and `robots` reflects that); when `true` it has
its own per-type override. Unlike the per-post `robots.index`, a site-level
default resolves concretely (`index` / `noindex`, never `default`). Keys:
`pt_<type>_title`, `pt_<type>_description`, `pt_<type>_custom_robots`,
`pt_<type>_robots`, `tax_<tax>_*`, etc.

## Workflows

### Optimize a page's SEO

1. `rank-math-check-setup` → confirm active.
2. `rank-math-get-post-seo` { post_id } → see current values.
3. `rank-math-edit-post-seo` { post_id, focus_keywords, seo_title, meta_description }.
   - Keep out of search: `{ post_id, robots: { index: "noindex" } }`.
   - Social: `{ post_id, facebook: { title, description, image_id } }`.

### Optimize a category / tag

1. `rank-math-get-term-seo` { term_id, taxonomy }.
2. `rank-math-edit-term-seo` { term_id, taxonomy, meta_description, robots: { index } }.

### Set a post's schema type

1. `rank-math-check-setup` → `supports_schema` must be true.
2. `rank-math-edit-post-schema` { post_id, schema_type: "Article" } — this
   **replaces** any existing per-post schema with one primary entry
   (destructive); `schema_type: "off"` clears it so the post inherits the
   post-type default.

## Use execute-php for these (no ability)

Site-wide settings *writes* and the redirections / module toggles are
`execute-php` recipes. Rank Math stores settings in a shared option per group —
read it, merge, write it back (a blind `update_option` clobbers the rest):

```php
// Change the post title template (titles group):
$opt = get_option( 'rank-math-options-titles', [] );
$opt['pt_post_title'] = '%title% %sep% %sitename%';
update_option( 'rank-math-options-titles', $opt );
// Knowledge-graph identity:
$opt['knowledgegraph_type'] = 'company';
$opt['knowledgegraph_name'] = 'Acme Inc';
update_option( 'rank-math-options-titles', $opt );
```

Read current values first with `rank-math-get-settings`. **Redirections** are a
free Rank Math module that is **off by default**; manage them via
`RankMath\Redirections\DB::add()/update()/delete()` (never write the
`wp_rank_math_redirections` table directly), and enable the module by adding
`'redirections'` to the `rank_math_modules` option.

## Gotchas

- **`robots.index: "default"` ≠ indexed.** It means "inherit the post-type
  setting". Use `"index"` to force inclusion.
- **Schema abilities need the `rich-snippet` module** (check-setup reports it).
- **Settings live in shared options** — merge, never blind-overwrite.
- **Don't run Rank Math and another SEO plugin (Yoast/AIOSEO) at once** — they
  conflict.

## Conventions

- Slugs: `novamira/rank-math-<verb>-<object>`. Reads are `get-*` (readonly);
  writes are `edit-*` (partial merge). `edit-*` send only the fields to change.
