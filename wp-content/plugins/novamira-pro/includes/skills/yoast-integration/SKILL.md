---
name: yoast-integration
description: Activate when working with Yoast SEO or Yoast SEO Premium on a WordPress site — setting a page's or post's SEO title, meta description, focus keyphrase, canonical, social (OpenGraph / X) preview, or search indexing (index/noindex); optimizing a category or tag; configuring site-wide title templates, indexing defaults, or brand identity; or managing Premium redirects. Establishes the friendly SEO shape, the indexing-code traps Yoast hides, which operations are abilities vs plain execute-php, and the read→edit workflow.
---

# Working with Yoast SEO and Yoast SEO Premium

This skill is for using the `novamira/yoast-*` abilities together. Read it once
at the start of any Yoast/SEO task and refer back when in doubt.

## When to use

Activate when the user asks to view or change SEO metadata managed by Yoast: a
post or page's SEO title / meta description / focus keyphrase / canonical /
social preview / indexing; a term's (category, tag, custom taxonomy) SEO;
site-wide title & meta templates, indexing defaults, separator, or brand
identity; or — on Premium — redirects.

Do **not** activate for non-Yoast meta (use the WordPress abilities), for other
SEO plugins (Rank Math, AIOSEO — different storage), or for on-page content
edits (that's the builder/post abilities; Yoast only stores the SEO *about* the
content).

Always call `novamira/yoast-check-setup` first. It reports the edition (redirects
need **Premium**), the version, whether the XML sitemap is on, and the public
post types and taxonomies you can target. If the Yoast abilities are missing
from your tool list, Yoast is not active.

## Why these are abilities (and what to do in plain PHP)

Yoast stores most things as ordinary postmeta and options. We ship abilities
**only** where raw PHP is a footgun or where discovery matters; everything else
is a documented `execute-php` recipe (see the last section). The dividing line:

- **Abilities** — per-post SEO, per-term SEO, settings *reads*, and Premium
  redirects. These hide the indexing-code traps, route writes through Yoast's
  validators, avoid clobbering shared storage, and paginate.
- **execute-php** — site-wide settings *writes* (title/meta templates, indexing
  toggles, separator, identity, social), sitemap toggle, schema defaults. Once a
  key is known, `WPSEO_Options::set()` is a safe one-liner.

## The abilities at a glance

| Ability | Use |
|---|---|
| `yoast-check-setup` | First call. Edition, version, sitemap, identity, targetable post types & taxonomies. |
| `yoast-get-post-seo` | Read a post/page's full SEO in one friendly shape. |
| `yoast-edit-post-seo` | Change any subset of a post's SEO (partial merge). |
| `yoast-get-term-seo` | Read a term's SEO. |
| `yoast-edit-term-seo` | Change a term's SEO (partial merge). |
| `yoast-get-settings` | Read site-wide settings (`scope`: general / titles / social / all). |
| `yoast-list-redirects` | (Premium) List redirects, paginated + searchable. |
| `yoast-create-redirect` | (Premium) Add a redirect. |
| `yoast-edit-redirect` | (Premium) Change a redirect by origin. |
| `yoast-delete-redirect` | (Premium) Remove a redirect. |

## Domain model

### Post / page SEO

`get-post-seo` / `edit-post-seo` speak one friendly shape — never the raw
`_yoast_wpseo_*` keys:

```
{
  focus_keyphrase, seo_title, meta_description, canonical, breadcrumb_title,
  cornerstone: bool,
  robots: { index: "default"|"index"|"noindex",
            follow: "follow"|"nofollow",
            advanced: ["noimageindex"|"noarchive"|"nosnippet", ...] },
  opengraph: { title, description, image, image_id },
  twitter:   { title, description, image, image_id },
  schema: { page_type, article_type },
  primary_category: <term id>|null,
  redirect: <url>          // Premium: the post-level redirect box
}
```

**The indexing trap this hides.** Yoast stores `meta-robots-noindex` as `0` =
"use the post-type default", `2` = index, `1` = noindex — a counter-intuitive
order where a naive `1 = index` assumption silently noindexes the page. The
ability exposes only `robots.index: default | index | noindex`. `"default"`
means "follow whatever the post type is set to" — it is **not** a synonym for
"index". To force a page into results regardless of the type default, use
`"index"`; to hide it, `"noindex"`.

`seo_title` / `meta_description` may contain Yoast template variables
(`%%title%%`, `%%sep%%`, `%%sitename%%`, `%%page%%`); leave them intact unless
deliberately changing the pattern. `primary_category` is the term id of the
chosen primary category. Computed SEO/readability scores are read-only and only
returned when you pass `include_scores: true`.

### Term (category / tag / custom taxonomy) SEO

`get-term-seo` / `edit-term-seo`, keyed by `term_id` + `taxonomy`. Same shape as
posts **minus** nofollow, advanced robots, schema, primary_category, and redirect
— terms carry only `robots.index` (`default | index | noindex`). Term SEO lives
in a single site-wide option, not termmeta; the abilities read and write it
safely so a write to one term never disturbs another.

### Site-wide settings

`get-settings` returns a curated snapshot. Use `scope` to stay focused:

- `general` — brand identity: `seo_identity` (`company`|`person`), `company_name`, `person_name`.
- `titles` — `separator` (a *code*, see below), homepage templates, per-post-type and per-taxonomy `{title_template, metadesc_template, noindex}`, author/date-archive indexing, breadcrumbs on/off.
- `social` — OpenGraph/X toggles, default image, X card type, profile URLs.

The key vocabulary (for execute-php writes): templates are `title-<posttype>`,
`metadesc-<posttype>`, `noindex-<posttype>` (bool, `true` = excluded from
search), taxonomies `title-tax-<taxonomy>` etc., homepage `title-home-wpseo`.
Brand identity (`company_or_person`, `company_name`, `person_name`) lives in the
**`wpseo_titles`** group, not `wpseo_social`. The separator is a code, not a
character: `sc-dash` = "-", `sc-ndash` = "–", `sc-mdash` = "—", `sc-pipe` = "|",
`sc-middot` = "·", `sc-bull` = "•", `sc-star` = "*".

## Workflows

### Optimize a page's SEO

1. `yoast-check-setup` → confirm active.
2. `yoast-get-post-seo` { post_id } → see current values.
3. `yoast-edit-post-seo` { post_id, focus_keyphrase, seo_title, meta_description } → set only what changes.
   - To keep a page out of search: `{ post_id, robots: { index: "noindex" } }`.
   - Social preview: `{ post_id, opengraph: { title, description, image_id } }`.

### Optimize a category or tag

1. `yoast-get-term-seo` { term_id, taxonomy } → current values.
2. `yoast-edit-term-seo` { term_id, taxonomy, meta_description, robots: { index } }.

### Manage redirects (Premium)

1. `yoast-check-setup` → `supports_redirects` must be true.
2. `yoast-list-redirects` { search: "/old-" } → find existing ones (paginated).
3. `yoast-create-redirect` { origin: "/old-url", target: "/new-url", type: 301 }.
   - Formats: `plain` (default) or `regex`. Types: 301, 302, 307, 410 (gone, no target), 451.
4. `yoast-edit-redirect` / `yoast-delete-redirect` by `origin`.

Never write the `wpseo-premium-redirects-base` option directly — the redirect
manager regenerates the export caches (plain / regex / .htaccess) that make
redirects actually fire. The abilities go through it; a raw option write does
not, so the redirect silently never triggers.

## Use execute-php for these (no ability)

Site-wide settings *writes* are safe one-liners once you know the key from
`get-settings`. Use `novamira/execute-php` with `WPSEO_Options::set($key, $value,
$group)` — it sanitizes; do **not** `update_option()` the raw row.

```php
// Title template for the "post" post type:
WPSEO_Options::set( 'title-post', '%%title%% %%sep%% %%sitename%%', 'wpseo_titles' );
// Exclude an entire post type from search:
WPSEO_Options::set( 'noindex-product', true, 'wpseo_titles' );
// Brand identity (note: the wpseo_titles group, not wpseo_social):
WPSEO_Options::set( 'company_or_person', 'company', 'wpseo_titles' );
WPSEO_Options::set( 'company_name', 'Acme Inc', 'wpseo_titles' );
// Default social image + X card type (wpseo_social group):
WPSEO_Options::set( 'og_default_image', 'https://…/og.jpg', 'wpseo_social' );
// Toggle the XML sitemap (wpseo group):
WPSEO_Options::set( 'enable_xml_sitemap', true, 'wpseo' );
```

Always read with `get-settings` first so you write a real key into the right
group. Per-post-type / per-taxonomy keys only exist for registered public types.

## Gotchas

- **`robots.index: "default"` ≠ indexed.** It means "follow the post-type
  setting". Use `"index"` to force inclusion.
- **A read never shows "missing".** Unset fields come back as Yoast defaults
  (empty string, `index: default`, `follow`). Writing a value equal to the
  default makes Yoast delete the stored row — a subsequent read still shows the
  default, which is correct, not a lost write.
- **Term SEO is not termmeta.** It's a single shared option; only `edit-term-seo`
  writes it safely. Never `update_term_meta` for Yoast term SEO.
- **Redirects need Premium** and must go through the abilities (export-cache
  regeneration). `type: 410` is "gone" and takes no target.
- **Separator is a code** (`sc-dash`), not "-".
- **Identity lives in `wpseo_titles`**, a common wrong-group mistake.

## Conventions

- Slugs: `novamira/yoast-<verb>-<object>`. Reads are `get-*`/`list-*`
  (`readonly`); writes are `edit-*` (partial merge, idempotent);
  `delete-redirect` is destructive.
- `edit-*` abilities are partial: send only the fields to change. Nested blocks
  (`robots`, `opengraph`, `twitter`, `schema`) merge field-by-field.
