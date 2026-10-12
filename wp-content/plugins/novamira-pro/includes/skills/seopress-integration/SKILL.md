---
name: seopress-integration
description: Activate when working with SeoPress (free or Pro) on a WordPress site — reading or setting a post's, page's, or term's SEO title, meta description, meta robots (noindex / nofollow / nosnippet / noimageindex), canonical, content-analysis target keywords, social (Facebook / X) preview, or single-post redirect; managing Pro redirects (the seopress_404 CPT) or per-post structured data / rich snippets; or reading site-wide title / social / sitemap settings. Establishes the friendly SEO shape, the "empty = inherit, never off" footgun, how robots are stored as "yes"/absent, and the read→edit workflow.
---

# Working with SeoPress

## When to use

Use these abilities when the task is about a page's or term's **search-engine
metadata** under SeoPress: SEO title / meta description / meta robots / canonical
/ target keywords / social preview / single-post redirect, the site-wide title,
social and sitemap settings, or (Pro) redirects and structured data. Not for
editing page content or layout — that is the builder's job.

## How SeoPress stores SEO (and why these are abilities)

SeoPress stores everything in **postmeta / termmeta** (`_seopress_*` keys) and
global config in **options** (`seopress_*_option_name`). The values are simple,
but three storage conventions are easy to get wrong by writing raw meta — the
abilities encapsulate them:

1. **Empty means INHERIT, never OFF.** A meta-title / description / canonical or
   a robots flag that is absent means "fall back to the global SeoPress template
   / default", not "disabled". To clear an override you DELETE the row (the
   edit abilities do this when you pass an empty string / `false`); storing `''`
   or `'no'` would be wrong.
2. **Robots are `"yes"`-or-absent, and the key is the NEGATIVE.**
   `_seopress_robots_index = "yes"` means **noindex**. The abilities expose
   explicit positive-sense booleans (`noindex`, `nofollow`, `nosnippet`,
   `noimageindex`) and map them back to `"yes"` / delete.
3. **Display ≠ storage.** A global or per-post-type default can force a directive
   while no per-post row exists. `get-post-seo` / `get-term-seo` therefore return
   both the raw per-entity override AND a `robots.effective` block that resolves
   the global / per-type defaults.

For anything without a dedicated ability (writing global settings, attaching a
schema template, regex redirects' edge cases), use `novamira/execute-php`.

## Domain model

**Post / term SEO** (free; same `_seopress_*` keys, postmeta vs termmeta):

```
title            meta-title template (may contain %%dynamic_variables%%)
description      meta-description template
robots {
  noindex, nofollow, nosnippet, noimageindex   per-entity overrides (booleans)
  breadcrumbs_hide                              Pro-written, read-only on free
  canonical                                     custom canonical URL
  effective { noindex, nofollow, nosnippet, noimageindex }   resolved directive
}
target_keywords  content-analysis keywords (a LIST; stored as one comma string)
facebook { title, description, image, image_id }    Open Graph
twitter  { title, description, image, image_id }     X / Twitter card
redirect { enabled, type, value, logged_status }     single-post redirect
```

**Redirects** (Pro) — the `seopress_404` CPT: source is the post title, target is
`_seopress_redirections_value`, and the same CPT holds both configured redirects
and monitored 404s (a redirect has a status `type`, a bare 404 does not).

**Structured data** (Pro) — per-post manual rich snippets plus the
`seopress_schemas` template CPT.

## Detection

Call `novamira/seopress-check-setup` FIRST. It reports `active`, `version`, `pro`
(the Pro add-on is loaded), `licensed` (informational only — Pro runs
unlicensed), `supports_redirects` / `supports_schema` (Pro-gated), the enabled
`modules` map, and the `public_post_types` / `public_taxonomies` you can target.
If the SeoPress abilities are missing from your tool list, SeoPress is not active.

## Workflows

### Read a post's SEO

```
novamira/seopress-check-setup
novamira/seopress-get-post-seo { post_id: 123 }
```

`title` / `description` may contain `%%dynamic_variables%%` (e.g. `%%sitetitle%%`,
`%%sep%%`) — leave them intact unless you mean to change the pattern. The
top-level `robots` flags are the per-post overrides (empty / false = inherit);
`robots.effective` is what SeoPress actually emits.

### Read a term's SEO

```
novamira/seopress-get-term-seo { term_id: 45, taxonomy: "category" }
```

Term SEO is a **free** feature and uses the same shape as posts. Both `term_id`
and `taxonomy` are required.

### Edit a post's SEO

```
novamira/seopress-get-post-seo { post_id: 123 }      # read the current shape
novamira/seopress-edit-post-seo {
  post_id: 123,
  title: "My SEO title %%sep%% %%sitetitle%%",
  description: "A compelling meta description.",
  robots: { noindex: false, nofollow: false },
  target_keywords: ["wordpress", "seo"],
  facebook: { title: "OG title", image: "https://…/og.jpg" },
  redirect: { enabled: false }
}
```

It is a **partial merge** — send only what you change. The returned shape from
get-post-seo can be edited and passed straight back. **Clearing:** to remove an
override (so the post inherits the global / per-post-type default) pass an empty
string for a text field, `false` for a robots flag, `[]` for target_keywords, or
`0` for redirect.type — the ability DELETES the row, it never stores a blank.
`robots` flags are **positive/negative-sense**: `noindex: true` means *do not
index*; `false` clears the override (it does NOT force "index"). `breadcrumbs_hide`
is read-only (Pro-written) and ignored on write.

### Edit a term's SEO

```
novamira/seopress-edit-term-seo { term_id: 45, taxonomy: "category",
  title: "Category SEO title", robots: { noindex: true } }
```

Same semantics as the post editor (term SEO is **free**); both `term_id` and
`taxonomy` are required. There are no target_keywords on a term.

### Manage redirects (Pro — the `seopress_404` CPT)

Redirects and monitored 404s share one CPT; a redirect has a status `type`, a
bare 404 does not. The `origin` (source) is the post title (a relative path or a
regex); the `destination` is the target URL.

```
novamira/seopress-list-redirects { view: "redirects" }            # or "404" / "all"
novamira/seopress-create-redirect { origin: "/old-page/", destination: "/new-page/", type: 301 }
novamira/seopress-edit-redirect { id: 99, type: 302 }             # also promotes a 404 → redirect (add a type)
novamira/seopress-delete-redirect { id: 99 }                      # HARD delete, no trash
```

A redirect only **fires** when it is enabled, has a destination and a valid type,
AND `logged_status` is set — create defaults `logged_status` to `both`, so leave
it unless you specifically want a logged-in/out-only rule. `create-redirect` is
not idempotent (each call makes a new entry) — pre-check with `list-redirects`
(`search` matches the origin). `delete-redirect` is a permanent hard delete and
refuses anything that is not a `seopress_404` entry.

For a **regex** redirect (`enabled_regex: true`), SeoPress matches the pattern
against the request path **with its leading slash**, so anchor it as
`^/old-(.*)` (or leave it unanchored) — a start-anchored `^old-(.*)` matches
nothing and the redirect silently never fires.

### Structured data / rich snippets (Pro)

SeoPress keeps the MANUAL per-post rich snippets as an array of typed rows. The
field keys differ per type and are many, so discover them on demand:

```
novamira/seopress-get-post-schema { post_id: 123 }                    # what rows exist (populated fields only)
novamira/seopress-set-post-schema-type { post_id: 123, type: "articles" }   # PUT the row's type (switching type clears its fields)
novamira/seopress-list-schema-fields { type: "articles" }            # the field keys for that type
novamira/seopress-edit-post-schema { post_id: 123, fields: {
  "_seopress_pro_rich_snippets_article_title": "…",
  "_seopress_pro_rich_snippets_article_desc": "…"
} }
```

Type values carry plural/stem quirks (`articles` → `_article_*`, `products` →
`_product_*`, `localbusiness` → `_lb_*`, `howto` → `_how_to*`) — always read the
keys from `list-schema-fields` rather than guessing. The `custom` type holds a
raw `<script type="application/ld+json">` blob (preserved verbatim). For the
deeply-nested repeaters (FAQ items, How-To steps) use `novamira/execute-php`.
`list-schema-templates` is a lean index of the Pro "automatic" schema templates
(the `seopress_schemas` CPT); editing a template's rules is an execute-php job.

### Read site-wide settings

```
novamira/seopress-get-settings { group: "titles" }   # all|titles|social|knowledge|sitemap|advanced|analytics
```

Read-only and secret-free (the license key, indexing API keys and analytics
tokens are never returned). To CHANGE a global setting use `novamira/execute-php`
with `update_option` on the relevant `seopress_*_option_name` array.

## Gotchas

- **Empty = inherit, not off.** Clearing any field deletes the override; the get
  abilities report an absent override as empty/false, and `robots.effective`
  shows the resolved directive once globals are applied.
- **Robots are stored as `"yes"`/absent** with negative-sense keys — the abilities
  hide that; you work in positive booleans.
- **target_keywords** is one comma-joined row — the whole list is rewritten each
  edit.
- **Redirect source is the post title**, the target is a meta; `logged_status`
  must be present for the redirect to fire (defaulted to `both`).
- **Schema field keys are type-specific and numerous** — use `list-schema-fields`;
  switching a row's type drops the old type's fields.
- **On a WooCommerce product, a manual `products` schema DUPLICATES** WooCommerce's
  (and SeoPress's automatic) native Product JSON-LD — two competing Product nodes
  for one page. Prefer SeoPress's automatic WooCommerce schema for products; use a
  manual `products` row only when you've disabled the automatic one.
- **Stored schema only renders when the site-wide rich-snippets feature is on.**
  `get-post-schema` returns `rendering_enabled`; if it is `false`, the manual rows
  you write are stored but SeoPress emits no JSON-LD until the feature is enabled
  (`update_option` on `seopress_pro_option_name['seopress_rich_snippets_enable']
  = '1'` via execute-php, or in SeoPress's settings).
- **Writes to global settings and template rules are not abilities** — use
  `execute-php`.
