---
name: dynamic-data-binding
description: Activate when wiring a custom field from any of the field plugins (ACF, Pods, JetEngine, ASE, Meta Box, ACPT) into a page builder widget (Elementor, Bricks, Oxygen) via the builder's dynamic data / dynamic tag system, OR when a user reports a builder widget showing a literal `{token}` / `[breakdance_dynamic]` as text, an empty / wrong value where a custom field should resolve, or PHP warnings from a dynamic-tag provider. Trigger phrases: "show this ACF/Pods/JE/MB/ACPT field in Elementor/Bricks/Oxygen", "bind this custom field to a widget", "dynamic data not working", "widget shows {field_name} as literal text", "ACF field empty in Elementor". Teaches when to use the dedicated Novamira Pro abilities (elementor-list/get/apply-dynamic-tag, bricks-list/resolve-dynamic-data, oxygen-list-dynamic-data-fields/apply-dynamic-data) instead of authoring raw widget settings, the three trap patterns that produce silent failures (composite keys, control-name variance, same-field-multiple-tags), and a reference of the specific key formats per plugin × builder for the edge cases. Empirically validated against Elementor Pro + ACF/JE/MB/ASE/ACPT, source-audited for Bricks, render-verified for Oxygen 6.
---

# Dynamic Data Binding (field plugins → page builders)

When the user asks to "show this custom field inside this widget", the agent reads from the field plugin (ACF / Pods / JE / ASE / MB / ACPT) and writes a configuration into a builder element (Elementor / Bricks). The bridge is the builder's **dynamic data / dynamic tag** system. It looks simple. It is not.

## Why this skill exists

Empirical test, same custom field `isbn` on a post, binding key passed to the dynamic tag's identifier control:

| Guess in the binding control | Elementor Pro ACF | Bricks ACF |
|---|---|---|
| `"ISBN"` (the field's label) | NULL + PHP warning | `{ISBN}` rendered literally |
| `"isbn"` (the field's name) | NULL + PHP warning | `{isbn}` rendered literally |
| `"field_<acf_key>"` (key only) | NULL + PHP warning | n/a |
| `"field_<acf_key>:isbn"` composite | `978-0441172719` ✓ | n/a |
| `{acf_isbn}` token | n/a | `978-0441172719` ✓ |
| Generic custom-field (`isbn` / `{cf_isbn}`) | `978-0441172719` ✓ | `978-0441172719` ✓ |

**3 of 5 naïve guesses fail. None of the failures throw a visible error to the user — they show the literal token, an empty heading, or a silent NULL.** Without this skill, the agent typically tries label → name → settles for the wrong widget content.

## The right tool first: use the dedicated abilities

Novamira Pro ships abilities specifically designed for this trap. **Use them before authoring raw widget settings.**

### Elementor

| Step | Ability | Purpose |
|---|---|---|
| Discover | `novamira/elementor-list-dynamic-tags` | Lists registered tags filtered by `group` or `categories`. Compact response by default. |
| Inspect | `novamira/elementor-get-dynamic-tag` | Returns the tag's `controls` shape — the agent learns the control name (key / field / meta_field / …) and the exact stored format of the select options. |
| Apply | `novamira/elementor-apply-dynamic-tag` | Attaches a tag to a widget setting with **HARD server-side validation**. If the element is wrong, the widget type is wrong, the setting name is wrong, or the tag settings are malformed, the call fails and **returns the compact widget schema inline** so you can correct and retry. Auto-detects v3 vs v4 atomic format. |

Workflow:

1. `list-dynamic-tags group: "post"` (or filter by `categories: ["text"]`) — find candidate tags.
2. `get-dynamic-tag tag_name: "acf-text"` — inspect controls. The `controls.key.groups[].options` map shows the exact key strings to use; the *label* visible to humans is the value, the **key** is the option string.
3. `apply-dynamic-tag post_id: ..., element_id: ..., setting_name: "title", tag_name: "acf-text", tag_settings: { key: "field_<hash>:isbn" }`.
4. Re-render the post or open the editor preview to confirm the widget shows the resolved value.

### Bricks

| Step | Ability | Purpose |
|---|---|---|
| Discover | `novamira/bricks-list-dynamic-data` | Lists `{tokens}` grouped by provider. Use `provider` or `search` to narrow on ACF-heavy sites. |
| Verify | `novamira/bricks-resolve-dynamic-data` | Renders a single token against a given post and returns the resolved value. **The only reliable way to confirm a token is valid before embedding it** — Bricks prints unknown tokens as their literal `{text}` with no error. |

Workflow:

1. `bricks-list-dynamic-data provider: "acf", search: "isbn"` — find candidate tokens.
2. `bricks-resolve-dynamic-data tag: "{acf_isbn}", post_id: ..., context: "text"` — confirm non-empty value.
3. Now safely embed `{acf_isbn}` in a Bricks element setting.

### Oxygen (6+)

Oxygen resolves dynamic data through the `[breakdance_dynamic field="<slug>"]` shortcode placed **in the target control VALUE** — never in a sibling `*_dynamic_meta` key (the renderer ignores that; it only feeds the builder UI).

| Step | Ability | Purpose |
|---|---|---|
| Discover | `novamira/oxygen-list-dynamic-data-fields` | Lists field `slug`s (optionally scoped by `post_type`). Its separate `repeater_sources` list is for LOOPS, not scalar binds. |
| Inspect | `novamira/oxygen-get-dynamic-data-field` | The field's `controls` — extra params some fields need (e.g. a meta key, a date format). |
| Apply | `novamira/oxygen-apply-dynamic-data` | Writes the shortcode at a dotted control `path` with **HARD server-side validation**: the field slug must be registered and the element must exist, or the call fails without touching the canvas. Preserves sibling keys. |

Workflow:

1. `oxygen-list-dynamic-data-fields post_type: "book"` — find the field slug (e.g. `post_title`, `acf_field`).
2. `oxygen-get-element-schema type: "OxygenElements\\Text"` — find the control path (e.g. `content.content.text`).
3. `oxygen-apply-dynamic-data post_id: ..., element_id: ..., path: "content.content.text", field: "post_title"` (add `attributes` for params like a date `format`).
4. Render the page to confirm the value resolves and no raw `[breakdance_dynamic ...]` leaks.

For a REPEATING loop over a repeater/relationship field, use `novamira/oxygen-set-loop` (with `repeater_field`), not `apply-dynamic-data`.

## When to use this skill

- The user wants a widget to show data from a custom field.
- The user reports a widget showing a literal token (`{ISBN}`) or unexpectedly empty content.
- The agent is about to author dynamic-tag settings programmatically without one of the abilities above (rare — almost always prefer the abilities).

## When NOT to use

- For field CRUD (creating / reading / editing the field itself) — use the `<plugin>-integration` skill.
- For building the layout / structural composition of the page — use `elementor-build-page` / `bricks-build-page`.

## You may be missing a query loop

If you find yourself adding N inline elements that each bind the same dynamic tag (heading → `{post_title}`, image → `{featured_image}`, button → `{post_url}`) **inside a non-loop container**, every iteration resolves against the same context (usually the current page) — so the N "cards" all show the same post. That's not a binding bug, that's a missing loop wrapper.

Symptoms to watch for:
- All N inlined cards show the same title / image / link in preview.
- The user wanted "latest 6 blog posts", "recent projects", "team grid", "rentals list", or similar repeated CMS content, and you are about to author 6 hand-typed cards each with `{post_title}` etc.
- The source page (when reconstructing) had a `loop-grid` widget / a Bricks container with `hasLoop:true`, and the rebuild dropped it.

Fix: go back to `elementor-build-page` / `bricks-build-page`, section **"Static vs. query loop on repeated content"**. Wire the loop, bind the per-item template once, then come back here for the per-provider token format.

## The three patterns that cause failure

When you cannot use the abilities (e.g. authoring an export JSON, configuring a third-party builder, debugging a saved page), recognize these.

### Pattern 1 — Composite keys

Every plugin packs more than just the field name into the bound key. The full key is a composite. The visible label is **only** for UI. Passing the label is the #1 silent failure.

The composite encodes one or more of: plugin namespace, post-type slug, parent metabox, ACF internal key (`field_<hash>`), field type, sub-type. The separator and segment order vary per plugin — see the reference tables below.

### Pattern 2 — Control name varies per provider

The agent often assumes the dynamic-tag's identifier control is called `key`. It isn't always.

| Provider in Elementor Pro | Control name |
|---|---|
| ACF (`acf-text` etc.) | `key` |
| JetEngine (`jet-post-custom-field`) | `meta_field` |
| ACPT (`acpt-text`) | `field` |
| Meta Box (`meta-box-text`) | `key` |
| ASE (`ase-text`) | `key` |
| Generic (`post-custom-field`) | `key` |

If the agent passes settings with the wrong control name, the tag is created but configured to no field — silent fail. `elementor-get-dynamic-tag` returns the exact controls shape; use it.

### Pattern 3 — Same field, multiple tags, different formats

A single ACF field `isbn` is bindable through at least three different tags in Elementor Pro:

- `acf-text` → needs composite `field_<hash>:isbn`
- `post-custom-field` (generic) → needs raw `isbn`
- (with JE active) `jet-post-custom-field` → needs `isbn` or the field title

All three render the same value. Mixing formats across tags (e.g. passing `field_<hash>:isbn` to `post-custom-field`) is a silent fail. **Pick one tag per binding and stay there.**

## Cross-cutting gotchas

- **Pro gate**: Elementor Free has *zero* dynamic tags — the button opens an upgrade modal. Check `typeof elementorPro !== 'undefined'` in the editor frame, or check the `field_group_counts` / similar setup ability output before promising dynamic-tag wiring on Free. Bricks dynamic data exists at all license tiers, but the *builder itself* requires a license to open.
- **Categories filter availability**: each widget control has a `categories` whitelist (`text`, `image`, `url`, `media`, …). Tags whose categories don't match the control don't appear in the picker. `acf-image` won't show in a Heading's title control — Heading title is `text`-category, ACF Image is `image`-category. Pick the right tag for the control type.
- **Container scope (Crocoblock pattern)**: `jet-post-custom-field` with `object_context: default` resolves on the **current loop item** when placed inside a Listing Grid / Theme Builder loop, or on the **displayed post** when placed in a Theme Builder template directly. Same tag string, two different sources. Validate per-row when configuring inside a Listing.
- **Field-type membership is part of the composite (ACPT)**: renaming a field's type from `Text` to `Textarea` breaks every binding silently because the type is encoded in the composite key (`...::::edition::::Text` → `...::::edition::::Textarea`).
- **Bricks `@fallback` whitespace**: always write a space before `@` — `{tag @fallback:'Untitled'}` renders the value or the fallback. Without the space (`{tag@fallback:'Untitled'}`) the result in a text element depends on the tag and the Bricks install: observed outcomes are the whole token printed as literal text, or nothing at all (for `{cf_*}` tags, even when the meta has a value). `novamira/bricks-resolve-dynamic-data` reports the same outcome the page shows (`unregistered: true` for a literal token) and adds a `note` whenever the space before `@` is missing. Same rule for `@fallback-image` (without the space an image can render with a broken `src`).

## Reference — Elementor Pro stored key formats

For the edge cases when you must author programmatically. `<name>` is the field slug, `<key>` is the ACF internal key (`field_<hash>` — read from the field group, do not invent).

| Provider | Tag name | Control | Stored value format | Example |
|---|---|---|---|---|
| ACF | `acf-text` (also `-image`, `-url`, `-number`, `-gallery`, `-file`, `-color`, `-date-time`) | `key` | `<acf_key>:<name>` | `field_7d54edd6a2074:isbn` |
| Pods | (no native Pods provider in plugin base) | — | — | — |
| JetEngine post meta | `jet-post-custom-field` | `meta_field` | `<name>` or `<title>` | `Publisher` |
| ACPT text | `acpt-text` | `field` | `<belongs_kind>::::<target>::::<box>::::<name>::::<type>` | `customPostType::::book::::primary::::edition::::Text` |
| Meta Box | `meta-box-text` (also `-image`, `-url`; each with `-archive-*` and `-settings-*` variants) | `key` | `<post_type>:<field_id>` | `book:publisher` |
| ASE | `ase-text` (also `-color`, `-file`, `-gallery`, `-image`, `-number`, `-url`) | `key` | `<name>__<type>__<sub_type>` | `publisher__text__plain` |
| Generic (always available) | `post-custom-field` | `key` | `<meta_key>` raw | `isbn` |

## Reference — Bricks token formats

Token syntax `{...}` embedded in any text/HTML-aware element setting. The token's prefix names the provider; the rest is the field path. Note the **double underscore** for ASE — the only provider that breaks the single-underscore convention.

| Provider | Token format | Example |
|---|---|---|
| ACF | `{acf_<name>}` | `{acf_isbn}` |
| Pods | `{pods_<object>_<name>}` | `{pods_book_isbn}` |
| JetEngine | meta field → Generic `{cf_<meta_key>}`; or `{je_current_object_field:<key>}` | `{cf_isbn}` / `{je_current_object_field:isbn}` |
| ACPT | `{acpt_<post_type>_<box>_<field>}` / `{acpt_tax_<taxonomy>_<box>_<field>}` / `{acpt_option_<menu_slug>_<box>_<field>}`, all lowercase (spaces become `_`); the first part is the group's location rule `find` | `{acpt_book_primary_edition}` |
| Meta Box | `{mb_<field_id>}` or `{mb_<group>_<field_id>}` | `{mb_book_details_isbn}` |
| ASE | `{ase__<name>}` (double underscore) | `{ase__publisher}` |
| Generic | `{cf_<meta_key>}` | `{cf_isbn}` |

> **JetEngine in Bricks has no per-field `{je_<object>_<name>}` token** — that format does not exist here (source-verified). Its Bricks provider registers only a few generic tags: `{je_current_object_field:<key>}`, `{je_macros:<macro>}`, `{je_query_results_count:<query_id>}` (+ `_filter`). For a JetEngine **meta field** the idiomatic path is the Generic `{cf_<meta_key>}` — JE stores meta as postmeta, so Bricks reads it natively (verified: `{cf_isbn}` resolves the value). Always discover with `novamira/bricks-list-dynamic-data provider: "jetengine"` and confirm with `novamira/bricks-resolve-dynamic-data` before pasting — an invented `{je_*}` token silently renders as the literal `{token}`.

## Things to NOT do

- Don't pass the field's human label as the binding key. Ever.
- Don't pass the bare field name to plugin-specific tags that expect a composite. Use the generic `post-custom-field` / `{cf_<name>}` if you only know the raw meta key.
- Don't assume the control name is `key`. Inspect with `elementor-get-dynamic-tag`.
- Don't mix formats across tags. Pick one and stay there.
- Don't ship a Bricks page without `bricks-resolve-dynamic-data` checking each new token first.

## Things that are safe and encouraged

- Use the abilities above as the primary path. Reference tables are for inspection and edge cases.
- After authoring a binding, re-render or preview and confirm the resolved output is not the literal token or empty.
- Prefer `post-custom-field` / `{cf_<name>}` when you only need a simple meta value and don't care about the plugin's UX niceties (label, fallback handling). It works everywhere and skips the composite-key trap.
