---
name: code-snippets-integration
description: Activate when working with the Code Snippets or Code Snippets Pro plugin on a WordPress site — adding, editing, enabling, or removing PHP / HTML / CSS / JS snippets and Pro conditions. Covers the type/scope matrix, the safe validate→activate workflow, and the free-vs-Pro feature surface.
---

# Code Snippets Integration

## When to use

Activate this skill when:
- The user mentions Code Snippets, codesnippets.pro, or asks to "add a snippet" / "run code on every page" / "inject a script in the head" / "site-wide CSS" without touching theme files.
- The user asks to register a hook (filter/action) on the site without writing a custom plugin.
- The user asks to disable, edit, or remove an existing snippet.
- `code-snippets-check-setup` returns `plugin_active: true`.

## When NOT to use

- If the user wants permanent infrastructure code (services, complex APIs, multiple files), recommend building a real plugin instead — snippets are for individual hooks and small pieces.
- Always call `code-snippets-check-setup` first. If `plugin_active: false`, the plugin is not installed/active — inform the user and stop.
- If the user wants the snippet to be version-controlled / shared across sites, mention the "export to flat file" option (out of scope for these abilities).

## Free vs Pro

Both variants are built by the same vendor and share a version line. The integration detects which is active at runtime via the plugin slug — `available_types` in `check-setup` is the authoritative list of types you can use on this install.

| Type | Free | Pro | Use it for |
|---|---|---|---|
| `php` | ✓ | ✓ | Hooks, filters, custom logic. The bread and butter. |
| `html` | ✓ | ✓ | Shortcode body, head/footer markup, embed-ready HTML. |
| `css` |  | ✓ | Site-wide or admin-only stylesheets. |
| `js` |  | ✓ | Head/footer inline scripts. |
| `cond` |  | ✓ | Reusable boolean predicates that gate other snippets. |

Asking the user to install the free version is fine — Pro adds modes but the core CRUD experience is identical.

## Type ↔ scope matrix

Call `code-snippets-get-types-schema` for the full reference (it's the source of truth). Quick map:

```
php
 ├─ global       runs on every request (admin + front-end) — DEFAULT for hooks
 ├─ admin        runs only in wp-admin
 ├─ front-end    runs only on public-facing requests
 └─ single-use   runs once on save, then auto-deactivates (data migrations)

html
 ├─ content       shortcode [code_snippet id="<id>"]
 ├─ head-content  inlined in <head> on every front-end page
 ├─ body-content  inlined right after <body> (wp_body_open) — only when the installed plugin offers it: check get-types-schema
 └─ footer-content inlined just before </body> on every front-end page

css   (Pro)
 ├─ admin-css
 └─ site-css

js    (Pro)
 ├─ site-head-js
 └─ site-footer-js

cond  (Pro)
 └─ condition    reusable boolean (does not run page-side)
```

If the user's intent is "add a filter that runs everywhere", that's `php` / `global`. If it's "site-wide custom CSS", that's `css` / `site-css` (Pro). If it's "tracking script in the footer", `js` / `site-footer-js` (Pro) or `html` / `footer-content` (free fallback — same outcome, different storage).

## Canonical workflow (PHP — the risky case)

PHP snippets execute on the site as soon as they are activated. A bad snippet won't kill the site (the plugin catches ParseError at run time) but a bad snippet that gets activated still hides somewhere broken until a user notices. The safe path is:

```
1. code-snippets-get-types-schema             — confirm the right type+scope pair
2. code-snippets-create-snippet               — saves INACTIVE by default; capture the id
3. code-snippets-validate-snippet  (id or code) — token-level parse check, NO execution
4. (show the body to the user, get explicit consent)
5. code-snippets-enable-snippet               — flip active=true; the code runs from here
```

`create-snippet` is deliberately inactive-by-default so you have a chance to validate before flipping the active flag. If the agent is confident and the user has already confirmed the code, you can shorten the loop by passing `activate: true` to `create-snippet` — but that bypasses the validation step, so reserve it for trivial / well-known patterns.

For non-PHP types (html / css / js) `validate-snippet` is a no-op (`skipped: true`), but you can still call it as a uniform "ready?" step in a programmatic loop.

## Common patterns

### Add a filter that runs everywhere

```json
{
  "name": "Strip query strings from static assets",
  "code": "add_filter('script_loader_src', fn($src) => preg_replace('/\\?ver=.*/', '', $src));\nadd_filter('style_loader_src', fn($src) => preg_replace('/\\?ver=.*/', '', $src));",
  "type": "php",
  "scope": "global",
  "tags": ["perf"]
}
```

PHP snippets do NOT include the leading `<?php` tag — the plugin adds it on save.

### Inject a tracking pixel in the footer

Pro:

```json
{ "name": "GA4", "code": "<script>...</script>", "type": "js", "scope": "site-footer-js" }
```

Free fallback (same outcome via the html type):

```json
{ "name": "GA4", "code": "<script>...</script>", "type": "html", "scope": "footer-content" }
```

### Site-wide custom CSS (Pro)

```json
{ "name": "Brand overrides", "code": ".btn { border-radius: 0 !important; }", "type": "css", "scope": "site-css" }
```

### One-shot data migration

```json
{
  "name": "Backfill book ISBN meta",
  "code": "foreach (get_posts(['post_type' => 'book', 'posts_per_page' => -1]) as $p) { update_post_meta($p->ID, 'isbn', '...'); }",
  "type": "php",
  "scope": "single-use"
}
```

`single-use` auto-deactivates after one execution, so an agent doesn't need to remember to disable it.

### Shortcode that renders dynamic content

```json
{ "name": "User greeting", "code": "<p>Hi, {$current_user->display_name}!</p>", "type": "html", "scope": "content" }
```

WordPress registers a `[code_snippet id="<id>"]` shortcode on save; the user pastes that wherever they want the body to render. The `create-snippet` response surfaces the exact shortcode under `snippet.shortcode` — copy it from there.

The shortcode renders on both classic themes and block themes because `do_shortcode` is hooked on `the_content` regardless of how `post_content` was parsed. You can inject the raw `[code_snippet id="<id>"]` into a page's `post_content`; wrapping it in a Gutenberg `<!-- wp:shortcode -->…<!-- /wp:shortcode -->` envelope is OPTIONAL — both forms render. The wrap helps with editor round-trip when a human opens the page in Gutenberg (the editor recognises the block boundary), so prefer the wrapped form when the page is human-edited; the raw shortcode is fine when the page is only ever rendered.

## Tool choice

| Intent | Tool |
| --- | --- |
| Check whether the plugin is active + which variant (free / Pro) | `code-snippets-check-setup` |
| Look up which scopes a type accepts (or vice versa) | `code-snippets-get-types-schema` |
| Browse the snippet catalogue (filtered by type / scope / active / tag) | `code-snippets-list-snippets` |
| Read the full body + metadata of a specific snippet | `code-snippets-get-snippet` |
| Create a new snippet (inactive by default) | `code-snippets-create-snippet` |
| Modify an existing snippet's body / metadata | `code-snippets-edit-snippet` |
| Permanently delete a snippet | `code-snippets-delete-snippet` |
| Activate (run on every matching request) | `code-snippets-enable-snippet` |
| Deactivate (stop running but keep the body) | `code-snippets-disable-snippet` |
| Syntax-check PHP without saving / activating | `code-snippets-validate-snippet` |
| Look up the condition subject / operator catalogue (Pro) | `code-snippets-get-conditions-schema` |

## Conditions (Pro-only)

A "condition" is itself a snippet of `type: cond` and `scope: condition`. Its `code` field stores a JSON tree describing when the condition evaluates true. Other snippets gate their execution on a condition by setting their `condition_id` to the condition's snippet id.

Workflow:

```
1. code-snippets-get-conditions-schema           → catalog of subjects + operators + worked example
2. code-snippets-create-snippet {                → save the condition
     type: "cond", scope: "condition",
     code: JSON.stringify([[{subject,operator,object}, ...]]),
     activate: true
   }                                            → captures id = 42 (say)
3. code-snippets-create-snippet { ..., condition_id: 42 } OR
   code-snippets-edit-snippet { id: <target>, condition_id: 42 }
                                                → target now only runs when condition 42 evaluates true
4. Detach with edit-snippet { id: <target>, condition_id: 0 }.
```

Condition JSON shape (the `code` field of a `cond` snippet): a top-level ARRAY of groups (NOT wrapped in `{"rules": …}` — the plugin's evaluator iterates the top level directly and will fatal if it finds an object there).

```json
[
  [ { "subject": "siteArea", "operator": null, "object": ["frontend"] },
    { "subject": "postType", "operator": null, "object": ["post", "page"] } ],
  [ { "subject": "userRole", "operator": null, "object": ["administrator"] } ]
]
```

The outer array is OR-joined (any inner group true → condition true). Each inner array is AND-joined (every rule must match). `operator: null` is the "subject matches any of objects" default; date subjects (`currentDate`, `postPublished`, `timeOfDay`, …) additionally accept `before` / `after` / `between`. Negation: pass `operator: "not"` (or its alias `"not in"`) to invert the match, or `operator: "false"` to force the rule false. Call `code-snippets-get-conditions-schema` for the authoritative subject catalogue and the per-subject `object` shape.

## Safety notes

- **`create-snippet` saves INACTIVE by default.** This is deliberate. The activate flag is opt-in via `activate: true` (avoid that for non-trivial code) or via a follow-up `enable-snippet` call.
- **`enable-snippet` is destructive.** From the moment it returns, the snippet code runs on every matching request. For PHP that means it can hook into actions / filters, fire HTTP requests, touch the DB, etc.
- **PHP fatal errors do not kill the site.** The plugin wraps execution in try/catch and records the error on the snippet (visible in the admin list). Still, "doesn't kill the site" is not the same as "works" — validate first.
- **Validate before enable on every PHP snippet you author.** `validate-snippet` uses `token_get_all(TOKEN_PARSE)` to catch syntax errors without executing the body. The same parse check happens at activation in the plugin's own pipeline, so a validate pass strongly predicts an enable pass.
- **`edit-snippet` does NOT touch the active flag.** Editing an active snippet replaces the executing code on the next request. For risky changes, prefer disable → edit → validate → enable.
- **Tag your snippets for organisation.** The integration uses tags as a free-form bookkeeping mechanism; consider adding a consistent tag (e.g. the user's name, the project, or `nvp` for agent-authored ones) so a later list-snippets call can find them.

## "I saved a CSS/JS snippet, why isn't it rendering?"

The most likely cause on a Pro install is an unactivated Freemius license. The storage layer works without a license (you can save css / js / cond snippets and they round-trip cleanly through `code-snippets-get-snippet`), but the runtime enqueue path is gated by `code_snippets()->licensing->was_licensed()`. Without an active license the snippet exists in the DB but the front-end never sees it.

When the user reports a saved CSS / JS snippet not applying, tell them to activate the license under **Code Snippets → License** in admin. Do not attempt to activate it yourself — that's a setup step the site owner does once.

## Common mistakes

- **Including `<?php` in PHP snippet `code`.** Don't. The plugin adds it on save; including it produces a parse error at `<?` inside `<?`.
- **Picking `single-use` for hooks.** `single-use` runs once and auto-deactivates — the hook never registers on subsequent requests. Use `global` for filters / actions.
- **Choosing `js` / `site-css` on a free install.** Those types only exist on Pro. The error message lists the available types on the current install; gate via `check-setup.available_types` or `get-types-schema.available_on_this_install`.
- **Changing `type` without changing `scope` (or vice versa).** Most type changes invalidate the existing scope. The edit-snippet ability rejects the pair with an explanatory error — pass both fields together when changing the snippet's nature.
