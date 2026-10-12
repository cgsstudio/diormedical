---
name: oxygen-build-page
description: Build an Oxygen 6+ page from scratch end-to-end — create the template/page, populate its element tree, attach visibility conditions, and reference the project's design tokens. Activate when the user asks to create a new Oxygen-rendered page or template (landing page, header, footer, global block, full-bleed page, marketing page, etc.), or when the user asks how to scope a template to a CPT/archive/single, hide the site header/footer on one page, or apply common page-level Oxygen settings. Oxygen 6 is Soflyy's unified codebase running in `BREAKDANCE_MODE=oxygen`; this skill is dedicated to Oxygen 6 and does NOT cover Oxygen Classic (4.x/5.x), which is out of scope.
---

# Building an Oxygen 6 page from scratch

This skill covers the Oxygen 6+ builder from Soflyy. Oxygen 6 shares the `Breakdance\*` CODE with standalone Breakdance (`BREAKDANCE_MODE=oxygen`), but the PERSISTED identifiers are Oxygen-specific: canvas postmeta is `_oxygen_data`, template settings are `_oxygen_template_settings`, template CPTs are `oxygen_*`, and global options are prefixed `oxygen_`. The abilities go through the engine's own mode-aware Data API so they read and write exactly what the Oxygen editor sees. Every ability is `novamira/oxygen-*` and check-setup verifies `BREAKDANCE_MODE === 'oxygen'`, so the Oxygen and Breakdance specializations never co-register.

Oxygen 6 ships template-like CPTs and also renders ordinary pages/posts whose `_oxygen_data` postmeta is populated:

| Goal | Post type |
|---|---|
| One-off page (landing, marketing, custom slug) | `page` (or `post`) with Oxygen canvas |
| Reusable template scoped via conditions (single, archive, 404, search, everywhere) | `oxygen_template` |
| Site header | `oxygen_header` |
| Site footer | `oxygen_footer` |
| Global block (reusable element subtree referenceable from any canvas) | `oxygen_block` |

(Popups are NOT part of the editable template surface in Oxygen mode, so this skill does not create them.)

If the surface already exists, do not default to this workflow: read it with `oxygen-get-template` (or `oxygen-get-element-tree` on the page) and iterate.

## First decision

Call `oxygen-check-setup` first. Confirm:
- `plugin.mode === 'oxygen'` — the site is running Oxygen 6 (not standalone Breakdance).
- `plugin.min_satisfied === true` — Oxygen version is ≥ 6.0.0.
- `license.mode` — `pro` unlocks the full element registry (dynamic data, forms, design library). On `free`, avoid Pro-only elements and features or the page will render broken.
- `elements.oxygen_native_count` — the native `OxygenElements\*` set (always present on Oxygen 6).
- `elements.essential_extension_present` — whether the optional "Breakdance Elements for Oxygen" extension is active. When TRUE the builder set (`EssentialElements\Section`, `Heading`, `Image2`, `Div`, `Columns`, …) is available. When FALSE those builder elements are NOT registered — but note a base Oxygen 6 install still ships ~20 `EssentialElements\*` *integration* elements (forms, presets, atoms) that have nothing to do with the extension. Never assume a namespace means the extension; always discover the real, live set with `oxygen-list-element-types`.
- `companions.oxygen_gutenberg.active` / `companions.oxygen_woocommerce.active` — if either is `true`, warn the user: those are legacy Oxygen Classic add-ons that do NOT function on Oxygen 6. Direct them to the standard `novamira/gutenberg-*` and `novamira/woocommerce-*` surfaces.

If `mode !== 'oxygen'`, this skill is the wrong surface. Standalone Breakdance sites go through `breakdance-build-page`.

## Hard rules

1. **Never write raw CSS blobs.** Style through the tiered hierarchy: design variables → global classes → element-level design settings. Raw custom CSS is a last resort and must be per-element `settings.advanced.css` (the key the engine's CSS pipeline actually renders; the legacy `custom_css` spelling is auto-normalised to it), not a global stylesheet.
2. **Never write `_oxygen_data` postmeta directly** (through the WP API or `execute-php`). It goes through the native Oxygen Data API (mode-aware key + envelope encoding); a hand-written value corrupts the canvas. Use `oxygen-set-content` / `oxygen-add-element` / `oxygen-edit-element`.
3. **Always pick element types from the live registry.** Call `oxygen-list-element-types` and use the exact `slug` it returns. Never hard-code a type — the available set depends on whether the EssentialElements extension is active. A wrong-cased slug is normalised (and reported in `warnings` on `set-content`) or hard-rejected with a "did you mean" hint.
4. **Never move the root** (id=1). Root is immutable and cannot be edited, deleted, or moved. Root children replace via `set-content`.
5. **Never write a sibling `*_dynamic_meta` key.** Dynamic data goes as a shortcode in the target property VALUE (see § Dynamic data tokens below).
6. **Never guess a property path.** Read `oxygen-get-element-schema` for the exact element type. Add/edit/set-content and apply-dynamic-data reject paths that are not live control leaves; compound/responsive values remain atomic at the leaf.

## Domain model

- **Storage**: postmeta `_oxygen_data` on every canvas-bearing post. Envelope is `{ tree_json_string: "<JSON>" }`. Template settings live in `_oxygen_template_settings`. Both are written through the native `Breakdance\Data\set_meta`/`get_meta` API so the encoding matches the Oxygen editor.
- **Root**: element id `1`, type `root`. Immutable.
- **Id allocation**: monotonic counter `_nextNodeId` starting at 100. Deleted ids are burnt (never reused).
- **Element types**: PascalCase case-sensitive slugs. Two origins:
  - **Native `OxygenElements\*`** (always present): `Container`, `Text`, `RichText`, `Image`, `TextLink`, `ContainerLink`, `Html5Video`, `SvgIcon`, `PostsLoop`, `DynamicDataLoop`, `TermLoopBuilder`, `Component`, `TemplateContentArea`, plus the code elements `PhpCode`, `HtmlCode`, `CssCode`, `JavaScriptCode` (all four write-gated behind `allow_code_elements` — see § Native code elements), `Shortcode`, `ContainerShortcode`, `WpWidget`, `oEmbed`.
  - **`EssentialElements\*`** — base Oxygen 6 already registers ~20 of these as INTEGRATION elements (form providers like ContactForm7 / GravityForms, presets, atoms) even when the extension is absent. The BUILDER pack (`Section`, `Heading`, `Image2`, `Div`, `Columns`, and many more) is what the optional "Breakdance Elements for Oxygen" extension adds — gate ONLY those on `elements.essential_extension_present`, not the whole namespace.
  The slug is the fully-qualified class name — always read it from `oxygen-list-element-types`, never guess.
- **Design tokens**: variables use `variables_json_string` plus their collection registry; Oxygen-native global selectors use `oxy_selectors_json_string` plus `oxy_selectors_collections_json_string`; optional Global Settings use `global_settings_json_string` only when the live registry exposes sections. All are mode-prefixed to `oxygen_*`, locked per surface, read back after write, and compensated on partial persistence.

## Canonical sequence

Standard flow to build a new page or template from scratch:

1. **Discovery** — parallelisable:
   - `oxygen-check-setup` (mandatory first call — tells you if EssentialElements is available)
   - `oxygen-list-element-types` to learn the exact vocabulary this install supports
   - `oxygen-list-templates` if you might want to reuse an existing header/footer/block
   - `oxygen-list-variables` + `oxygen-list-global-classes` — if the project has design tokens, use them.
   - If dynamic data is needed: `oxygen-list-dynamic-data-fields` scoped to the target CPT.
2. **Pick surface** — page vs template CPT via the goal→post_type table above.
3. **Create shell** — `oxygen-create-template` (with `type` from `oxygen-list-template-conditions`.`template_types` when scoping) OR a bare `page`/`post` via the base `novamira/create-post` if you want a URL-slug-first page.
4. **Populate canvas** — `oxygen-set-content` with the nested top-level elements. Ids auto-allocated. Casing normalised. Root children replaced wholesale. Or, if incremental: `oxygen-add-element` under `parent_id: 1` for the root children, then nested add-elements.
5. **Attach conditions** (template CPTs only) — `oxygen-set-template-conditions` with the `ruleGroups` composed from `oxygen-list-template-conditions`. A new template is already seeded with `ruleGroups: []`, so it matches EVERYWHERE its `settings.type` applies from the moment it is created — attach narrowing conditions promptly (or create it with `settings.disabled: true`) if that is too broad. Note `[[]]` (one empty group) is rejected: the engine evaluates an empty group to false, so it would silently never match.
6. **Style** — apply globally via variables / native Oxygen selectors; use Global Settings only when `oxygen-get-global-settings.available_sections` is non-empty. Per-element paths come from `oxygen-get-element-schema`.
7. **Verify the observed result** — rely on each write ability's compact read-back/rollback receipt. Then render the affected page once after the related batch of edits (mandatory for loops, dynamic data, template visibility, and CSS). Do not repeatedly fetch the full canvas merely to prove every small write.

## Style hierarchy

Apply styles in tier order, prefer the highest tier when possible:

1. **Design variables** (`oxygen-*-variable`) — scalar design tokens only: `color`, `text`, `number`, `unit`, `font_family`, `image_url` (there is NO composite `size`/`typography`/`shadow` variable kind — the create/edit validators reject those). For composite typography use global settings / global classes. Referenced from CSS as `var(--<cssVariableName>)`. Change once, ripple everywhere.
2. **Global classes / selectors** (`oxygen-*-global-class`) — Oxygen 6 stores `{id,name,properties,type,children,collection}` rows in `oxy_selectors_json_string`; elements bind stable selector ids at `properties.meta.classes`. Discover the live selector property schema with `oxygen-get-global-class-schema`, then attach with `oxygen-apply-global-class`. Never use the Breakdance `settings.advanced.classes` path.
3. **Global settings** (`oxygen-edit-global-settings`) — base Oxygen 6 disables this surface. Use it only when `oxygen-get-global-settings.available_sections` advertises live sections (typically supplied by an extension); unknown sections are rejected.
4. **Element inline** (`oxygen-edit-element` → `properties.design.*`) — one-off tweaks that don't earn a variable or class.
5. **Raw CSS** — last resort, per-element `settings.advanced.css` only (value: `{"breakpoint_base": "%%SELECTOR%% { … }"}`; `%%SELECTOR%%` is replaced with the element's own selector).

## Element hierarchy

Container-like elements accept children. On a base install (native elements only) the workhorses are:
- `OxygenElements\Container` — the general-purpose layout container (rows, columns, wrappers)
- `OxygenElements\PostsLoop` / `OxygenElements\DynamicDataLoop` / `OxygenElements\TermLoopBuilder` — loop containers (see § Static vs. query loop)

Leaf elements: `OxygenElements\Text`, `OxygenElements\RichText`, `OxygenElements\Image`, `OxygenElements\TextLink`, `OxygenElements\SvgIcon`, `OxygenElements\Html5Video`.

When the EssentialElements extension is active you also get `EssentialElements\Section`, `Heading`, `Image2`, `Div`, `Columns`, etc. — check `elements.essential_extension_present` before using them, and confirm each slug with `oxygen-list-element-types`.

## Static vs. query loop

Answer three questions before building repeated content:

1. **Same content everywhere?** → static. Add each element manually.
2. **Repeats over CMS records?** → query loop. Use `OxygenElements\PostsLoop` for post-driven content, `OxygenElements\DynamicDataLoop` for arbitrary dynamic-data-driven sources, `OxygenElements\TermLoopBuilder` for taxonomy terms.
3. **Not sure the target post type exists?** — Call `novamira/list-post-types` first. If no CPT matches, decide with the user whether to create one (via `novamira/acf-*` / `novamira/pods-*` / etc. per the site's field plugin) or fall back to static.

**PostsLoop query envelope** lives at property path `properties.content.query.query`: it selects a mode with `active`, and for a custom post query a `custom` object whose `source` is one of `post_types`, `related`, `acf_relationship`. A straight post query:
```
{
  "active": "custom",
  "custom": { "source": "post_types", "postTypes": ["post"], "postsPerPage": 6, "orderBy": "date", "order": "DESC" }
}
```
Fields under `custom` are camelCase (`postTypes`, `postsPerPage`, NOT snake_case). The builder validates this envelope, so guessed field names silently drop — read the exact control shape with `oxygen-get-element-schema` for `OxygenElements\PostsLoop` first.

**Per-item template**: a loop element carries the engine's `nestingRule.type === 'final'` — the builder renders NO child slot for it, so its tree children are silently ignored. The repeated item is a SEPARATE `oxygen_block` post, referenced from the loop element's `content.repeated_block.global_block` (the block's post id). Build the item subtree in that `oxygen_block`, then point the loop at it. (`add-element` / `set-content` now REJECT children under a loop element with this hint, so you cannot lay the item markup in the wrong place by accident.)

> **Configure loops with `oxygen-set-loop`.** Each loop type has its OWN contract and the ability rejects inputs meant for a different one (never silently ignores them). Pass `element_id`, `item_block_id` (an `oxygen_block`), and the type-specific inputs:
> - **PostsLoop** → `post_types` + `posts_per_page` (clamped 1..100) + `order_by` (`date`/`title`/`menu_order`/`rand`/`modified`/`ID`) / `order` / `offset` → writes `content.query.query`.
> - **TermLoopBuilder** → `taxonomy` (required, must be registered) + `hide_empty` + `posts_per_page` (term limit) + `order_by` / `order` → writes `content.query.taxonomy/hide_empty/limit/orderby/order` (the native term query, NOT the post envelope). `order_by` here is a TERM ordering (`name` default, `slug`, `term_id`, `count`, `description`, `parent`, `term_group`, `none`) — the POST values (`date`/`title`/…) are rejected because `WP_Term_Query` silently ignores them.
> - **DynamicDataLoop** → `repeater_field` (a slug from `oxygen-list-dynamic-data-fields`'s `repeater_sources`) → writes `content.field.repeater_field`.
> It validates the element is a loop, the block is an `oxygen_block`, and the post types / taxonomy / repeater exist, then verifies the write. Always confirm with a front-end render.

If the item template needs a custom field from a field plugin (ACF, Meta Box, Pods, JetEngine), see § Dynamic data tokens below for the `[breakdance_dynamic ...]` binding shape.

## Native code elements (OxygenElements)

The native `OxygenElements\*` set includes low-level "code" elements. **The raw-code types are WRITE-GATED**: `add-element` and `set-content` reject `OxygenElements\PhpCode`, `HtmlCode`, `CssCode`, `JavaScriptCode` (and the extension's `EssentialElements\CodeBlock`) — at any nesting depth — unless the call passes `allow_code_elements: true`. That flag requires the USER's explicit confirmation: never set it on your own initiative; ask first, and prefer native elements + the style hierarchy instead. The non-code utility elements below are not gated.

**The same gate covers executable SETTINGS, not just element types.** Several engine features eval() operator-supplied values at render time, so writing one is equivalent to writing a `PhpCode` element even on a plain `Text` node. All of these are rejected without `allow_code_elements: true`, on every write boundary that can introduce them — `add-element`, `edit-element` (yes: the element type is immutable there, but settings are not), `set-content` (any depth), `set-template-conditions`, and `apply-dynamic-data`:

| Executable payload | Where it lives | What the engine does |
|---|---|---|
| `custom-php` condition rule | element `settings.conditions.conditions`, or a template's `ruleGroups` | `eval()`s the rule's `value` on every render/request |
| Query envelope in PHP mode (`active: "php"`) | `content.query.query` on loop elements | `eval()`s the `php` string to build the query |
| Term-query envelope (`load_terms_by_query`) | `content.query.term_query` on `OxygenElements\TermLoopBuilder` | `eval()`s the `term_query` string to build the term query |
| `phpreturn` dynamic-data field | any property value carrying `[breakdance_dynamic field="phpreturn" …]` | `eval()`s the field's `code` attribute |
| `process_value` shortcode attribute | any `[breakdance_dynamic … process_value="…"]` | pipes the value through `eval()` |

Use registered conditions, the structured query controls (`oxygen-set-loop`), and registered dynamic-data fields instead — they cover nearly every real need without executing code.

**The `params='{…}'` attribute counts as the attribute it decodes to.** Oxygen parses that single-quoted JSON blob separately and merges it *over* the plain attributes, so `params` WINS: `[breakdance_dynamic field="post_title" params='{"field":"phpreturn","code":"…"}']` runs `phpreturn`, not `post_title`. The gate reads the final merged attribute map, so hiding an executable field or a `process_value` inside `params` does not get you past it — it just produces a confusing rejection. Write what you mean.

**Two template edits are gated even though they carry no code at all.** The gate's promise is that no agent write starts running operator code without explicit approval, and these two make the engine execute code that is *already* stored:

| Operation | Why it needs approval |
|---|---|
| `edit-template` clearing `settings.disabled` on a disabled template | The engine skips a disabled template entirely; clearing the flag makes it evaluate that template's stored `ruleGroups` and render its stored tree — including any `PhpCode` element or `custom-php` rule already sitting there. |
| `edit-template` / `create-template` setting `settings.parentId` | The parent's stored tree is spliced into the render hierarchy, so attaching an armed parent arms it. |

Both inspect what is ALREADY persisted on the affected template and reject only when that content is executable. Enabling or re-parenting a clean template needs no flag.

| Slug | Purpose | Gotcha |
|---|---|---|
| `OxygenElements\PhpCode` | Inline PHP block | **Gated.** Server-side eval on every render. Slow. Prefer `oxygen-list-dynamic-data-fields` for CMS values. |
| `OxygenElements\JavaScriptCode` | Inline JS | **Gated.** Runs on `DOMReady` — DOM must exist. Wrap in try/catch or it breaks the page render. |
| `OxygenElements\CssCode` | Inline CSS | **Gated.** Not scoped — leaks to the whole page. Use global classes when reusability matters. |
| `OxygenElements\HtmlCode` | Raw HTML | **Gated.** No sanitisation — user input goes through raw. Never bind untrusted content here. |
| `OxygenElements\oEmbed` | oEmbed URL | Depends on WP's oEmbed cache; first render fetches. Same-domain iframe restrictions apply. |
| `OxygenElements\Shortcode` | Any WP shortcode | Renders whatever the shortcode returns; for Oxygen dynamic data use the `[breakdance_dynamic ...]` shortcode in an element's control VALUE instead. |
| `OxygenElements\ContainerShortcode` | Shortcode wrapping children | Rare use case. Prefer `Container` + `Shortcode` unless the shortcode strictly demands children. |
| `OxygenElements\WpWidget` | Legacy WP widget | Widget API is deprecated in modern WP. Consider migrating to a block. |
| `OxygenElements\Image` | Native image | The native image element. If the EssentialElements extension is active, `EssentialElements\Image2` adds extra responsive controls. |
| `OxygenElements\DynamicDataLoop` | Dynamic-data-driven loop | Different envelope from `OxygenElements\PostsLoop`. Use PostsLoop for straight post queries; use this when the source is a dynamic-data field. |

## Visibility rules for templates

Template CPTs (`oxygen_template`, `oxygen_header`, `oxygen_footer`) are gated by `ruleGroups` on the template settings. Compose via `oxygen-set-template-conditions`; the ability replaces the whole `ruleGroups` bucket wholesale.

- **AND within a group**: multiple rules in the same group all must match.
- **OR across groups**: a template renders when ANY group matches.
- Common ruleCategorySlug/ruleSlug values (call `oxygen-list-template-conditions` per CPT for the live catalogue):
  - Single: `post-type` (value = CPT slug list), `post-id` (value = int).
  - Archive: `archive-type` (value = post type), `taxonomy-term` (value = `<taxonomy>:<term_id>`, string).
  - Everywhere: pseudo-rule for header/footer CPTs.

**Rule shape — validated against the live catalogue and the native matcher before writing (get it right the first time):**
- `ruleSlug` is REQUIRED on every rule. An empty rule `{}` is rejected — it makes its whole AND-group impossible.
- `operand`: `oxygen-list-template-conditions` returns each rule's supported `operands` (e.g. `is` / `is not` for a single, `is one of` / `is none of` for `post-type`); a lenient `is_not` is auto-canonicalised to `is not`. Unknown operands are rejected.
- `value` is REQUIRED for every operand except `is empty` / `is not empty`.
- **MULTISELECT conditions** (`post-status`, `post-type`, `has-taxonomy`, `post-author`, …) need `value` as an ARRAY, e.g. `["publish","draft"]` — a bare scalar is a native error and is rejected. Single-select / free-form conditions (`post-id`, `comments-number`, `featured-image`) take a scalar. `oxygen-list-template-conditions` shows each condition's `values` (the allowed set) and `valueInputType`.
- Each value must be a MEMBER of the condition's fixed set when it has one.
- The `dynamic-data` condition needs exactly one `[breakdance_dynamic …]` shortcode whose field and attributes resolve against the live dynamic-data registry; arbitrary non-empty strings are rejected.
- On a `oxygen_template` with a concrete `settings.type`, a condition not offered for that type (its `availableForType`) is rejected — e.g. a `search` rule on a `post` template.

The `ruleGroups` KEY must exist for the engine to consider a template at all — `create-template` seeds `ruleGroups: []` on every new template (as Oxygen's own template manager does). An empty `ruleGroups: []` means **no additional constraints**: after `settings.type` matches, Oxygen applies the template everywhere within that type. It does not disable rendering. To disable a template, set `settings.disabled: true` with `oxygen-edit-template`. A group with zero rules (`[[]]`) is the opposite — the engine evaluates it to false (never matches) — and is rejected by `set-template-conditions`.

**Template `settings` is a closed schema** (via `create-template` / `edit-template`): only `type` (a registered template-type slug — note the front-page type slug is `front-page`, hyphenated), `priority` (int), `fallback` (bool), `disabled` (bool), and `parentId` (an existing, acyclic template id) are accepted. Literal `null` removes one of those keys. `priority`: when several templates match the same request the HIGHEST priority wins; a template without the key falls back to `1` at request time. Oxygen's own UI seeds each new template with its type's `defaultPriority` — `1` for everywhere/catch-all, `10` for all-singles / all-archives, `20` for a specific single, archive, or front-page — while `create-template` seeds `1`, so pass an explicit type-appropriate `priority` when layering templates. `triggers` is popup-only and is not writable because Oxygen mode exposes no popup CPT. `ruleGroups` / `conditions` go through `set-template-conditions`.

## Reusable subtrees: global blocks

The `oxygen_block` CPT is a reusable subtree. Note the embed element that places a block on a canvas — `EssentialElements\Globalblock` (lowercase `b`) — ships ONLY with the optional "Breakdance Elements for Oxygen" extension; on a base install there is no Global Block embed element (check `elements.essential_extension_present` before relying on it). A block is also consumed as a loop's repeated item via a loop element's `content.repeated_block.global_block` (see § query loops), which works on base. Edit the block once and it ripples to every canvas that references it.

Do not duplicate content: prefer a single reusable block over hand-copying the same subtree into multiple pages.

## Common element gotchas

- **PascalCase case-sensitivity**: slugs are exact (`OxygenElements\Container`, not `oxygenelements\container`). The resolver normalises casing and reports it in `warnings` on `set-content`; on `add-element` it silently normalises.
- **Slug set is dynamic**: the BUILDER `EssentialElements\*` elements (Section, Heading, Image2, …) exist only with the extension — check `elements.essential_extension_present`. But base Oxygen also registers unrelated `EssentialElements\*` integration elements, so never infer availability from the namespace; discover the live set with `oxygen-list-element-types`.
- **Root immutable**: never `edit-element` / `delete-element` / `move-element` on id=1.
- **Shallow-merge trap — `edit-element` replaces the WHOLE top-level bucket it touches**: the merge is shallow over `content` / `design` / `settings` only, NOT a deep merge. Editing any sub-key of a bucket (e.g. `design.spacing.padding`) replaces the entire `design` object, dropping every sibling you don't resend (`design.background`, `design.typography`, …). Before a one-off tweak, read the element with `oxygen-get-element`, modify the sub-object, and resend the whole bucket — or carry every key you want to keep in that bucket in one call. A responsive map (`{ desktop, tablet, mobile }`) is the same rule at the leaf: send all breakpoints or the omitted ones drop. (The `apply-global-class` / `apply-dynamic-data` abilities instead deep-set a single leaf and preserve siblings.)
- **Query envelope camelCase**: `postTypes`, `postsPerPage`, `orderBy`. Snake_case silently ignored.
- **Text nested twice**: text elements store their content at `properties.content.content.text`, not `properties.content.text`.
- **Dynamic data via shortcode in VALUE, not sibling `_dynamic_meta`** — see § Dynamic data tokens.
- **`edit-template` treats literal `null` as an unset operation** for an allowed settings key; it never stores a literal null. `edit-element` drops null keys from the replaced top-level property bucket.
- **`set-template-conditions` is not idempotent** on paper (it always writes), but semantically it is. This is intentional: the WP abilities-api DELETE routing cannot decode JSON bodies, so the annotations force POST routing.
- **Corrupt canvas fails closed**: if a post's `_oxygen_data` is present but unreadable, every read/mutation returns `oxygen_corrupt_canvas` instead of silently overwriting it. Inspect the post meta before retrying.

## Intent → keys map

Common tasks → target property path:

| I want to... | Path |
|---|---|
| Change a heading's text | `properties.content.content.text` |
| Set a section background image | `properties.design.background.image` |
| Set an element's padding | `properties.design.spacing.padding` (spacing_complex) |
| Attach a global class | `oxygen-apply-global-class` → `properties.meta.classes` (stable selector-id list) |
| Hide an element | `settings.conditions.visible: false` |
| Conditional visibility | `settings.conditions.conditions` with a REGISTERED condition (`custom-php` is gated — see § Native code elements) |
| Toggle draft (hide but keep in tree) | `settings.advanced.draft: true` |
| Configure a query/dynamic loop | `oxygen-set-loop` (writes `content.query.query` + `content.repeated_block.global_block`) |
| Set a loop's repeated item | `oxygen-set-loop` `item_block_id` (an `oxygen_block` id) |
| Embed a block (needs the extension) | `EssentialElements\Globalblock` element |
| Add responsive value | `{"breakpoint_base": <value>}` wrapper on responsive controls |

## Dynamic data tokens

Bind a control to dynamic data by placing a shortcode in the target control VALUE (never in a sibling `*_dynamic_meta` key — that only feeds the builder UI and is ignored by the renderer):

```
content.content.text = [breakdance_dynamic field="post_title"]
```

With field controls:
```
content.content.text = [breakdance_dynamic field="acf_field" params='{"key":"my_field_name"}']
```

Prefer `oxygen-apply-dynamic-data` over hand-writing the shortcode: pass `element_id`, the dotted `path` (e.g. `content.content.text`), and the `field` slug, plus optional `attributes`. It validates the slug against the live registry, writes the shortcode at the exact leaf (preserving siblings), and fails hard on an unknown field. Extra `attributes` are validated too: each key must be a REAL leaf attribute of the field (its `defaultAttributes()` + the leaf control slugs — a section container like `advanced` is NOT an attribute and is rejected), and a value carrying a quote / bracket / angle-bracket / backtick is rejected (it could break the shortcode or inject markup — `beforeContent` / `afterContent` are concatenated raw by the renderer). Inspect a field's real attributes with `oxygen-get-dynamic-data-field`. For a repeating loop over a repeater field use `oxygen-set-loop`, not this.

> **`path` validation is closed-world.** `apply-dynamic-data` requires an exact live control leaf for that element type, and refuses a path that descends through an existing scalar. Add/edit/set-content use the same live schema boundary. Compound controls and responsive maps are accepted as atomic leaf values; specialised helpers such as `oxygen-set-loop` own engine-internal envelopes that should not be hand-written.

**`phpreturn` is GATED, not merely discouraged** (`[breakdance_dynamic field="phpreturn" params='{"code":"return ...;"}']`): it evaluates arbitrary PHP on every render, so `apply-dynamic-data` (and any element write carrying such a shortcode) refuses it unless `allow_code_elements: true` is passed after explicit user approval. The same applies to the `process_value` attribute, which pipes the value through `eval()`. Slow, security-sensitive, and traps logic in the page instead of the CMS. Nearly every value has a registered field slug — call `oxygen-list-dynamic-data-fields` first.

## CSS regeneration

Every write via `oxygen-edit-variable` / `oxygen-*-global-class` / `oxygen-edit-global-settings` uses the native revision/cache lifecycle. Selector writes additionally render-check the changed selector rows. Tree writes run Oxygen's document-save effects and invalidate page caches. On a mismatch, high-risk writes restore the snapshot and verify that compensating write too.

## Verification without wasting context

- Do not treat `success: true` or HTTP 200 as proof; use the ability's observed-state receipt.
- Do not read the whole canvas before and after each leaf edit. The write ability snapshots and compares the affected persisted surface internally.
- Schema discovery is once per runtime/version: cache the result of setup, element schema, selector schema, and dynamic-field discovery during the workflow.
- Batch related edits, then perform one targeted final render of the affected page/template. Render verification is mandatory where persistence cannot prove behavior: selector CSS, loops, dynamic values, and template matching.
- If a write reports `oxygen_write_not_applied`, inspect its rollback result/fingerprint. Never continue building on a partial retained state.

## Ability quick map

Sprint 1 — discovery:
- `oxygen-check-setup`, `oxygen-list-templates`, `oxygen-get-template`
- `oxygen-list-element-types`, `oxygen-get-element-schema`
- `oxygen-get-element-tree`, `oxygen-get-element`
- `oxygen-list-dynamic-data-fields`, `oxygen-get-dynamic-data-field`

Sprint 2 — element + template writes:
- `oxygen-add-element`, `oxygen-edit-element`, `oxygen-delete-element`, `oxygen-move-element`, `oxygen-set-content`
- `oxygen-set-loop` (configure PostsLoop / TermLoopBuilder / DynamicDataLoop)
- `oxygen-create-template`, `oxygen-edit-template`, `oxygen-delete-template`
- `oxygen-list-template-conditions`, `oxygen-set-template-conditions`

Sprint 3 — design tokens:
- `oxygen-list-variables`, `oxygen-create-variable`, `oxygen-edit-variable`, `oxygen-delete-variable`
- `oxygen-list-global-classes`, `oxygen-get-global-class`, `oxygen-get-global-class-schema`, `oxygen-create-global-class`, `oxygen-edit-global-class`, `oxygen-delete-global-class`, `oxygen-apply-global-class`
- `oxygen-get-global-settings`, `oxygen-edit-global-settings`

Sprint 4 — dynamic data + forms:
- `oxygen-list-dynamic-data-fields`, `oxygen-get-dynamic-data-field`, `oxygen-apply-dynamic-data`
- `oxygen-list-form-submissions`, `oxygen-get-form-submission`, `oxygen-delete-form-submission`

**Total: 37 abilities.**

## Execute-php recipes (simple one-offs)

For the handful of things that don't earn a dedicated ability, use the base `novamira/execute-php` with a small snippet. Keep these narrow and read-only whenever possible. All keys below use the mode-aware Oxygen values (`oxygen_block`, `_oxygen_data`).

**1. Check if a Global Block id resolves to an actual block.**
```php
$post = get_post(42);
return $post && $post->post_type === 'oxygen_block' && $post->post_status !== 'trash';
```

**2. Count Oxygen canvases site-wide.**
```php
global $wpdb;
return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = '_oxygen_data' AND meta_value != ''");
```

**3. Find all posts embedding a Global Block by id.**
```php
global $wpdb;
$id = 42;
return $wpdb->get_col($wpdb->prepare(
    "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_oxygen_data' AND meta_value LIKE %s",
    '%"block":' . $id . '%'
));
```

**4. Enumerate active shortcodes discoverable by an `OxygenElements\Shortcode` element.**
```php
global $shortcode_tags;
return array_keys($shortcode_tags);
```

**5. Read the Oxygen license mode via the native Data API.**
```php
$info = \Breakdance\Data\get_global_option('license_key_validity_info');
return is_array($info) ? ($info['intended_subscription_mode'] ?? 'free') : 'free';
```

## What belongs elsewhere

- **Live editor-driven changes** — use Novamira Visual, not this skill.
- **WooCommerce store data** (products, categories, orders) — `novamira/woocommerce-*` abilities.
- **Gutenberg block trees on non-Oxygen posts** — `novamira/gutenberg-*` abilities.
- **Standalone Breakdance sites** — `breakdance-build-page` skill (Novamira Pro detects mode and routes accordingly).
- **ACF / Meta Box / JetEngine / Pods field CRUD** — the field plugin's own specialization.

## What this skill is not

- **Not for Oxygen Classic (pre-6)**. Oxygen Classic uses shortcode-based storage (`_ct_builder_shortcodes`), the `OxygenElement` class, and `ct_*` hooks. This skill and every `novamira/oxygen-*` ability is Oxygen 6+ only. If `oxygen-check-setup` reports `plugin.mode !== 'oxygen'` or `plugin.min_satisfied === false` at version < 6.0.0, direct the user to the Oxygen 6 upgrade path.
- **Not for the two legacy companion plugins** (`oxygen-gutenberg`, `oxygen-woocommerce`). Both target Oxygen Classic and do not function on Oxygen 6. Prefer the standard `novamira/gutenberg-*` and `novamira/woocommerce-*` abilities.
- **Not for Fusion Builder / Fusion Slider** — those are Avada surfaces (`avada-integration` skill).
