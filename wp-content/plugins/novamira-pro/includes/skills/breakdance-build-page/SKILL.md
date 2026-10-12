---
name: breakdance-build-page
description: Build a Breakdance page from scratch end-to-end - create the template/page, populate its element tree, attach visibility conditions, and reference the project's design tokens. Activate when the user asks to create a new Breakdance-rendered page or template (landing page, header, footer, popup, global block, full-bleed page, marketing page, etc.), or when the user asks how to scope a template to a CPT/archive/single, hide the site header/footer on one page, or apply common page-level Breakdance settings.
---

# Building a Breakdance page from scratch

This skill is for creating a new Breakdance-rendered surface end-to-end. Breakdance ships five template-like CPTs and also renders ordinary pages/posts whose `_breakdance_data` postmeta is populated:

| Goal | Post type |
|---|---|
| One-off page (landing, marketing, custom slug) | `page` (or `post`) with Breakdance canvas |
| Reusable template scoped via conditions (single, archive, 404, search, everywhere) | `breakdance_template` |
| Site header | `breakdance_header` |
| Site footer | `breakdance_footer` |
| Popup | `breakdance_popup` |
| Global block (reusable element subtree referenceable from any canvas) | `breakdance_block` |

If the surface already exists, do not default to this workflow:

- Use `novamira/breakdance-edit-element` for targeted property changes on an existing element.
- Use `novamira/breakdance-add-element` to insert a new node into an existing tree.
- Use `novamira/breakdance-move-element` to relocate or reorder an existing element.
- Use `novamira/breakdance-delete-element` to remove an existing element and its subtree.

This skill covers the canonical sequence for creating a new Breakdance surface and configuring its visibility. For any element not covered by the common-elements table below, use `novamira/breakdance-list-element-types` to confirm the canonical slug, then `novamira/breakdance-get-element-schema` to discover its controls before creating it. Use `get-element` only to read an existing node or preserve its full nested properties during a patch. For the global settings shape call `novamira/breakdance-get-global-settings`. This skill is workflow knowledge, not a schema dump.

## Canonical sequence

1. **Arbitrate builder ownership.** Determine which builder owns the site and target post before building. Plugin activation alone does not establish ownership. For an existing post on a Breakdance site, use a read-only `novamira/execute-php` probe of `\Breakdance\Admin\get_mode($postId)` when ownership is not already clear; it returns `breakdance` or `wordpress`. If multiple builders are active for a new surface and the requested builder is ambiguous, ask the user which one should own it.

   Once a post is Breakdance-owned, never write raw HTML to `post_content` as its page body. Use `novamira/breakdance-set-content` or the granular `novamira/breakdance-*` element abilities. Create regular Breakdance pages with empty `content`.

   The generic `novamira/create-post` and `novamira/update-post` abilities enforce a raw-content handshake. On an active Breakdance site, create rejects non-empty content; update rejects non-empty content when `\Breakdance\Admin\get_mode($postId) === 'breakdance'`. If either returns `breakdance_post_content_needs_confirmation`, do not set `allow_raw_content_on_breakdance_post` yourself. Explain that `post_content` bypasses the native Breakdance canvas and ask the user whether they explicitly want that raw WordPress write. Only after an affirmative answer may you re-call with `allow_raw_content_on_breakdance_post: true`; the successful response keeps an audit warning.

2. **Check Breakdance setup.** Call `novamira/breakdance-check-setup` and inspect the result. If `plugin.min_satisfied` is false or `plugin.active` is false, warn the user and stop. If a canonical runtime result reports `license.mode=free`, note that Pro-only features are unavailable. If a fallback result reports `free`, say that license detection reported free and verify with the user before avoiding Pro features. The check is read-only and idempotent.

   If `warnings` reports Breakdance's native AI ability surface, choose either the native Breakdance surface or the `novamira/breakdance-*` surface for this page. Never alternate or mix both surfaces on the same page.

3. **Inventory existing styles.** Call in parallel:
   - `novamira/breakdance-get-global-settings`
   - `novamira/breakdance-list-variables`
   - `novamira/breakdance-list-global-classes`

   If global-settings has populated sections (`typography`, `colors`, `buttons`, `containers`, …): do NOT override their defaults in element properties unless the design intentionally diverges. If variables / global classes are non-empty: prefer references over hard-coded values.

4. **Identify missing tokens (tokens-first).** Read the brief. Derive the design system the page needs:
   - Color palette (≤ 6 entries typically)
   - Spacing scale (4-6 steps)
   - Typography scale (4-6 sizes)
   - Reusable bundles (e.g. `.btn-primary`, `.card`)

   For each token that does NOT already exist, create it before building the tree:
   - `novamira/breakdance-create-variable` for spacing / typography / raw color tokens. The CSS reference at render time is `var(--<cssVariableName>)`; pick `cssVariableName` carefully because uniqueness is enforced (renames require delete + create).
   - `novamira/breakdance-create-global-class` for reusable rule bundles. Class `name` must be CSS-safe (lowercase, dashes, no whitespace or non-ASCII); names are unique across the project.
   - `novamira/breakdance-edit-global-settings` to set element-type defaults (typography, colors, buttons, containers, forms, …). The ability shallow-merges categories inside Breakdance's native `settings` envelope. To patch one sub-tree (e.g. `typography.body`), call `get-global-settings` with `section=typography` first, modify the returned object locally, then pass the whole `typography` sub-object back.

   Naming: kebab-case, semantic (`brand-primary`, `space-md`, `text-xl`). NOT presentational (`blue-1`, `large`).

5. **Create the surface.** Two paths:

   - **Standalone template / header / footer / popup / global block**: `novamira/breakdance-create-template` with the matching `post_type`. Capture the returned `id`. Status is forced to `publish` by Breakdance itself; the schema rejects `draft`. Optional `slug` / `post_name` sets the URL slug (both accepted; `post_name` wins when both are passed).

   - **Regular page or post rendered through Breakdance**: create the post via `novamira/create-post` with empty `content`, then add elements directly. Breakdance picks up the canvas as soon as `_breakdance_data` is non-empty.

6. **Populate the element tree.** The tree starts as `{root: {id: 1, children: []}}`. Insert elements top-down using `novamira/breakdance-add-element` - one call per node, `parent_id=1` to attach under the root, or the id of a previously inserted element to nest. Each call returns the new `id`, allocated from a monotonic per-canvas `_nextNodeId` counter (starting at 100). The mutation is locked per-post so concurrent agent calls do not stomp each other - if you see `bd_invalid_input` with "tree lock", retry once.

7. **Attach visibility conditions** (templates / headers / footers / popups only - skip for `breakdance_block` and regular pages). Call `novamira/breakdance-list-template-conditions` with the matching `post_type` to discover the available `ruleCategorySlug` and `ruleSlug` values, then `novamira/breakdance-set-template-conditions` with the assembled `rule_groups`. A freshly-created `breakdance_header` or `breakdance_footer` with empty `rule_groups` does NOT render on the front end - it silently disappears until at least one rule is attached.

Steps 6 and 7 can be done in either order, but populating content first lets you verify the tree renders before flipping visibility on.

## Style hierarchy

Tier 1 - Global settings defaults: respect them. Don't override `typography` on the heading element if `global-settings.typography.headings` already defines a size scale.
Tier 2 - Apply existing global class via the element's `properties.settings.advanced.classes` array (the class `name`, no leading dot).
Tier 3 - Reference an existing variable in control values: `"color": "var(--brand-primary)"`.
Tier 4 - Create the missing token via the tokens-first step above, then reference.
Tier 5 - Raw values in `properties` - only for non-systemic one-offs: animation timings, single transforms, clip-paths.
Tier 6 - Custom CSS on the element - last resort, only for things no control covers.

Hex colors, px values on `padding`/`margin`/`gap`, font sizes/weights/family, radii → NEVER raw. Always tokenize.

**Raw code/HTML is outside the styling tiers and gated.** `EssentialElements\CodeBlock` embeds PHP/HTML, CSS, or JavaScript; never use it to fake layout, ordinary content, or styling that native elements and controls can express. `breakdance-add-element`, `breakdance-set-content`, and `breakdance-edit-element` reject Code Block writes with `breakdance_code_element_needs_confirmation`. Do not set `allow_code_elements` yourself: explain the native alternative and ask the user for explicit confirmation. Only after they affirm may you re-call with `allow_code_elements: true`; successful opt-ins retain an audit warning. A `set-content` tree whose content consists only of Code Blocks is always rejected, even with the flag.

### `design.md` bridge

`design.md` is a client-side convention, not a Breakdance input file. The Novamira plugin and Breakdance never read it, so creating or editing `design.md` alone has no effect on the site. Treat it as a design-system source: translate color, spacing, and typography tokens into Breakdance variables; translate reusable component recipes into global classes; translate intentional site-wide defaults into the appropriate categories returned by `breakdance-get-global-settings`. Then reference those variables/classes from element properties through the tokens-first flow above.

## Element hierarchy

Breakdance pages follow a three-tier nesting: **section → columns wrapper → column → block-level element**. This is the default the Breakdance editor uses when inserting new layout and the shape that picks up global-settings spacing predictably.

- `EssentialElements\Section` - full-width layout band, top-level child of the document root. Its typical direct child is a `Columns` wrapper or, for vertical stacks, a `Div`.
- `EssentialElements\Columns` - row wrapper that lays out its `Column` children horizontally. The column count equals the number of `EssentialElements\Column` children you add — there is no top-level `properties.layout`. Responsive stacking/reverse toggles live under the wrapper's `design.layout.*`; per-column width under each `Column`'s `design.layout`.
- `EssentialElements\Column` - a single column inside a `Columns` wrapper, conventional parent of block-level elements (`Heading`, `Text`, `Button`, `Image2`, …).
- `EssentialElements\Div` - generic block-level grouping. Use it inside a column to cluster related elements, or directly under a section for vertical stacks that do not need horizontal columns.

**Type slugs are case-sensitive PascalCase class names.** The Breakdance editor keys its element registry by the exact string, so `EssentialElements\heading` / `\columns` / `\image` (lowercase) render as a red "This element is missing" card on the canvas even though the front end's case-insensitive class lookup would resolve them. Use the canonical casing: `Section`, `Columns`, `Column`, `Div`, `Heading`, `Text`, `Button`, `Image2`, `Globalblock`. The modern image element is **`EssentialElements\Image2`** (its editor label is just "Image"); the bare `EssentialElements\Image` is the **deprecated "Image V1"** — do not use it. When unsure of a slug, call `novamira/breakdance-list-element-types` (it lists every registered element — core, Pro, and third-party add-ons — with its canonical slug and label). `add-element` also normalises a wrong-cased slug to the registered one and hard-rejects a genuinely unknown type with the nearest match, so a typo fails fast instead of shipping a broken canvas.

Typical skeleton for a content section (4 calls - one section, one columns wrapper, one column, one heading):

```
add-element  parent_id=1     type=EssentialElements\Section  → id=100
add-element  parent_id=100   type=EssentialElements\Columns  → id=101
add-element  parent_id=101   type=EssentialElements\Column   → id=102
add-element  parent_id=102   type=EssentialElements\Heading  properties={"content":{"content":{"text":"Hello","tags":"h1"}}}  → id=103
```

**Property paths are double-nested under `content`.** Breakdance stores a control's value at `content.<section>.<control>`, and the text controls live in a `content` section — so a heading's/text's string is at `content.content.text` (NOT `content.text`), the heading tag selector is `content.content.tags` (plural, `h1`–`h6`), and a global-block reference is `content.content.block`. The abilities pass `properties` to Breakdance verbatim, so a wrong path stores cleanly but renders empty. For elements not in the table below, inspect `breakdance-get-element-schema` before adding the element; do not depend on finding a representative existing node on a new page.

## Common content elements

These slugs and recipes are verified against Breakdance 2.8.1. Use the exact casing.

| Intent | Canonical slug and recipe |
|---|---|
| FAQ repeater | `EssentialElements\FrequentlyAskedQuestions` — set `properties.content.settings.items` to a list such as `[{"question":"What is included?","answer":"<p>Everything listed above.</p>"}]`. The rendered template reads `items`; do not invent child nodes for individual questions. |
| Advanced accordion | `EssentialElements\AdvancedAccordion` — add one direct `EssentialElements\AccordionContent` child per panel. Set each child's title at `properties.content.content.title`, then put arbitrary body elements inside that `AccordionContent` child. `AccordionContent` is restricted to be a direct child of `AdvancedAccordion`. |
| Simple tabs (repeater) | `EssentialElements\Tabs` — no `TabContent` children. Set `properties.content.content.tabs` to repeater rows such as `[{"title":"Overview","content":"<p>...</p>"}]`; both label and HTML body live in the parent repeater. |
| Advanced tabs | `EssentialElements\AdvancedTabs` — add one direct `EssentialElements\TabContent` child per tab, and put arbitrary body elements inside each `TabContent`. Keep the parent `properties.content.content.tabs` rows in the same order for tab labels/settings. |
| Marquee text | `EssentialElements\Marqueetext` — the lowercase `t` in `text` is intentional and anomalous. This element is new in 2.8.1; confirm its live controls with `breakdance-get-element-schema` before setting properties. |

For every other element: first use `breakdance-list-element-types` to resolve the exact live slug, then use `breakdance-get-element-schema` for control paths, types, and options. Only use `breakdance-get-element` when a node already exists and you need its current values for a safe merge.

**Skip the columns wrapper when the intent calls for it**, not as a shortcut. Legitimate cases:

- **Full-bleed hero** - the section spans edge-to-edge and the content sits directly inside a `div` without horizontal columns.
- **Edge-to-edge media** - a gallery, slider, or image strip that must touch the viewport edges.
- **Section already constrained via global settings** - if `global-settings.containers` already sets a sensible `max-width` and `padding`, adding a constrained `columns` wrapper inside double-constrains the layout.

If the user's request is "build me a content section" without a specific full-bleed intent, default to the section → columns → column → block-level shape.

## Static vs. query loop on repeated content

WordPress sites are CMS-driven, so **repeated content on a page is a query loop, not N hand-typed cards.** Default to a loop whenever a section shows more than one of "the same thing" - blog posts, articles, projects, case studies, portfolio items, products, services, team members, events, testimonials, rentals, locations, jobs, listings, anything the customer will add or edit over time. The customer expects to manage these from the WordPress admin; static markup on the page traps the data inside Breakdance and breaks every other surface that should reference it (search, archives, single-post pages, related strips).

Static is the exception, reserved for **fixed UI sections with bespoke copy** authored for this page:

- Pricing tiers (multi-column comparison curated for this landing)
- Feature pillars / USP grid
- "How it works" steps
- Hero / sub-hero
- Brand or partner logo strip
- Hand-picked testimonials tied to this page
- Small fixed FAQ inline with the marketing copy

Three-question sanity check when in doubt:

1. Would each card have a dedicated detail page (`/projects/xyz`, `/villas/santorini-cliff-house`)?
2. Would these entities appear anywhere else on the site (search, archive, related strips, single pages)?
3. Will the customer add, remove, or edit them over time without a developer?

A "yes" to any of the three means the section is loop territory. The number of items at build time is irrelevant - the CPT can be empty or have ten thousand entries, the page tree is identical either way.

### Discover the target post type before building

Before wiring the loop (and before proposing to create a new CPT), list what's already registered on the site and match against the user's intent - they may already have set up the type, especially on a mature site. The same calls are the right answer when the user explicitly asks "what items do we have under X here?".

Call the post-types list ability of whichever field-plugin specialization is active in this session (e.g. `novamira/pods-list-pods`, `novamira/acf-list-post-types`, and the equivalent in any other specialization the abilities list shows). Match the user's wording to the returned slug or label; account for plural/singular and reasonable variation - the customer's spoken term may not be the verbatim slug. Use any reasonable match instead of proposing a new CPT; only fall through to "No matching CPT yet" when nothing in the listing matches.

There is no vanilla `novamira/wordpress-list-post-types` ability yet, so a CPT registered by anything other than a Novamira-supported field-plugin specialization is invisible to the specialization-aware discovery path. When you suspect the type exists but no specialization owns it, fall back to a read-only `novamira/execute-php` call - `return get_post_types(['_builtin' => false, 'public' => true], 'objects');` returns every non-built-in public post type registered on the site, regardless of how it was declared.

### Loop shape in Breakdance

Breakdance has two distinct loop primitives, NOT interchangeable:

- `EssentialElements\PostsLoop` (editor label "Post Loop Builder") - canonical CMS loop. Holds a `query` object (see below) and renders a chosen **Global Block** once per post. The per-item template is a `breakdance_block`, **NOT** the loop's tree children — point `properties.content.repeated_block.global_block` at that block's id. With no block assigned it renders the "Choose a Global Block from the dropdown" placeholder, once per queried post.
- `EssentialElements\DynamicDataLoop` (editor label "Repeater Field") - generic loop over a dynamic-data-provided array (e.g. an ACF repeater, a JetEngine relation), not necessarily a WP_Query.
- `EssentialElements\Postslist` (editor label "Post List") is a DIFFERENT element: a fixed-layout post listing widget, not a true loop. Use `PostsLoop` + a Global Block when you need a real per-post template.

(These slugs have no underscores and a specific casing — the folder names under Breakdance differ from the registered slug. `PostsLoop`, not `Posts_Loop`; `DynamicDataLoop`, not `Dynamic_Data_Loop`; `Postslist`, not `PostsList`.)

The query lives in `properties.content.query.query`; the per-item template is a **Global Block** referenced by id at `properties.content.repeated_block.global_block`. Inside that block, dynamic data is a **shortcode** placed in a text value — `[breakdance_dynamic field="post_title"]` (NOT `{post_title}`) — which resolves per post because Breakdance switches the global query post for each iteration.

#### Query object structure for PostsLoop

The loop's `properties.content.query.query` is an envelope, not a flat WP_Query — Breakdance reads it from `content.query.query` (a `wp_query` control), NOT from a top-level `query` key. Its top-level discriminator is `active`:

- `active: "custom"` — visual query builder; the real query lives in a nested `custom` object (below). This is the only mode an agent should author.
- `active: "text"` — raw WP_Query arg string in `content.query.query.text`.
- `active: "php"` — `eval`-ed PHP in `content.query.query.php` returning a WP_Query args array.

For `active: "custom"`, set `properties.content.query.query.custom`. Its `source` key selects one of three shapes. Note: keys are **camelCase** (the Vue serialized shape, passed to Breakdance verbatim) — do NOT use the snake_case WP_Query output names (`post_type`, `posts_per_page`, `orderby`).

**`custom.source: "post_types"`** (standard post type query)
- `postTypes`: string[] — post type slugs (e.g. `["post"]`, `["event","project"]`). NOT `post_type`.
- `postsPerPage`: int — items per page (default 8). NOT `posts_per_page`.
- `totalPosts`: int (optional) — hard cap on total items across pagination.
- `orderBy`: string — read unconditionally; set `date`, `title`, `modified`, `meta_value`, `acf_field`, `metabox_field`, … NOT `orderby`.
- `order`: string — read unconditionally; set `ASC` or `DESC`.
- `acfField` / `metaboxField`: string (optional) — meta key used when `orderBy` is `acf_field` / `metabox_field`.
- `offset`: int (optional) — skip N posts.
- `ignoreStickyPosts`: boolean — read unconditionally; set it explicitly.
- `ignoreCurrentPost`: boolean — read unconditionally; set it explicitly (`false` unless excluding the current post).
- `date`: string — read unconditionally; set `all` for no filter, `custom` to use `beforeDate`/`afterDate`, or a bare date string as an `after` lower bound.
- `beforeDate` / `afterDate`: string|null — read unconditionally; set both to `null` unless `date` is `custom`.
- `metaQuery`: object (optional) — `{relation, metaQueries:[…]}` nested meta conditions.
- `conditions`: array (optional) — template-condition rules; only the first group is honored.

**`custom.source: "related"`** (posts sharing taxonomy/author with the current post)
- `postsPerPage`: int (default 3).
- `includeByTaxonomies`: string[] — taxonomy slugs to match terms on.
- `includeByAuthor`: boolean — restrict to the current post's author.
- `date` / `beforeDate` / `afterDate`: as above.
  (The queried post type is taken from the current post automatically — there is no `post_type` input here.)

**`custom.source: "acf_relationship"`** (posts linked via an ACF relationship field; requires ACF active)
- `acfField`: string — the ACF relationship field name.
- `postsPerPage`: int (default 3).
- `orderBy`: string — same vocabulary as post_types.
- `order`: string — `ASC` or `DESC`.
- `date` / `beforeDate` / `afterDate`: as above.

**Per-item binding (verified recipe):** the loop has no per-item children of its own — all per-post markup lives in a **Global Block** you build separately, then reference by id. Inside that block, dynamic data is the `[breakdance_dynamic field="<slug>"]` shortcode placed in a text control's value.

```
# 1) per-item card = a Global Block (breakdance_block CPT)
create-template  post_type=breakdance_block  title="Loop Card"          → block_id=900
add-element  post_id=900  parent_id=1    type=EssentialElements\Div      → id=910
add-element  post_id=900  parent_id=910  type=EssentialElements\Heading
             properties={"content":{"content":{"text":"[breakdance_dynamic field=\"post_title\"]","tags":"h3"}}}
add-element  post_id=900  parent_id=910  type=EssentialElements\Text
             properties={"content":{"content":{"text":"[breakdance_dynamic field=\"post_excerpt\"]"}}}

# 2) on the page: the loop points at that block id + a query
add-element  parent_id=1     type=EssentialElements\Section              → id=200
add-element  parent_id=200   type=EssentialElements\PostsLoop
             properties={
               "content":{
                 "repeated_block":{"global_block":900,"tag":"div"},
                 "query":{"query":{"active":"custom","custom":{
                   "source":"post_types","postTypes":["post"],
                   "postsPerPage":6,"orderBy":"date","order":"DESC",
                   "ignoreStickyPosts":false,"ignoreCurrentPost":false,
                   "date":"all","beforeDate":null,"afterDate":null
                 },"text":"","php":""}}
               }
             }
```

- All per-post markup lives in the Global Block — design the card (wrapper `Div`, spacing, image, …) **there**, not as loop children. Reuse the same block across pages; editing it updates every loop that references it.
- For CPTs registered by a field plugin (Pods / ACF / JE / MB / ACPT / ASE), put the CPT slug(s) in `custom.postTypes` (a string array, e.g. `["event"]`) under the query envelope above — NOT a flat `post_type` key — discover slugs via the active specialization's list ability.
- Confirm the dynamic `field` slug with `novamira/breakdance-list-dynamic-data-fields` (filter by `category=Post` / `ACF` / `Metabox` / `JetEngine`; it returns the `slug` to use as `field="<slug>"`). Each provider exposes its own slugs and silent-fails differently. Load the `dynamic-data-binding` skill the moment the card touches a custom field.

### No matching CPT yet

The CPT may not exist when you start. Discovery first: list installed plugins (the `<plugin>-check-setup` ability of each field-plugin specialization tells you whether that plugin is active - call the ones you have).

- **A field-plugin specialization is active** (Pods / ACF Pro / JetEngine / Meta Box / ACPT / ASE Pro): propose creating the CPT through that plugin's specialization, then wire the loop on top. An empty CPT plus a wired loop is the right state to ship; the customer fills the content from the admin afterwards.
- **No field-plugin specialization is active**: there is no CPT-management UI on this site. Stop and tell the user - vanilla WordPress does not expose CPT registration to non-developers, and the section cannot loop against real content until one of those plugins is installed. Do not register the CPT via raw `register_post_type()` calls in agent-generated code; that would create a CPT the customer can't manage afterwards.

**Do not fall back to a static grid because the CPT is missing.** The whole point of the loop is that the content lives in the CMS - a static grid is the failure mode this section exists to prevent.

## High-impact actions need explicit confirmation

Before any of these, ask the user:

- Modifying any existing global setting section (`edit-global-settings`) - site-wide blast radius.
- Creating an initial set of tokens upfront on a sparse store - concretely: 3+ new variables, 2+ new global classes, or any combination on top of an empty/near-empty store. Show the user the proposed names, cssVariableName, and values before persisting. On a site with an established design system the agent reuses existing tokens (Step 3 inventory) and rarely hits this threshold.
- Modifying any existing variable / global class / global settings section.
- Force-deleting a template via `breakdance-delete-template force=true` - irreversible. Trash is recoverable from the WP admin.
- Force-deleting a template that other templates reference (any of: `parentId` pointers, `rule_groups` rule values targeting this template, or `breakdance_block` references inside another tree's element data). Breakdance does not cascade-clean those references - they remain as dangling pointers until manually fixed.

Phrasing: "Sto per [azione]. Confermi?"

## Tool choice

Use the smallest tool that matches the edit:

| Intent | Tool |
|---|---|
| Lay down or fully replace a whole page/template tree in one shot | `create-post` (or `breakdance-create-template`) + `breakdance-set-content` (nested `elements`, ids auto-allocated) |
| Build a tree incrementally / add one element to an existing canvas | `breakdance-add-element` |
| Patch one element's properties | `breakdance-edit-element` (shallow merge on top-level keys of `properties`) |
| Remove an element and its subtree | `breakdance-delete-element` |
| Relocate / reorder an existing element | `breakdance-move-element` |
| Change a template's visibility rules | `breakdance-set-template-conditions` |
| Rename or replace a template's slug | `breakdance-edit-template` with `slug` / `post_name` |

`breakdance-set-content` takes a nested `elements` tree (`{type, properties?, children?}`), allocates ids for you, normalises element-type casing against the live registry (a wrong-cased slug is fixed and reported in `warnings`; an unknown type aborts the whole write with a suggestion), and stores the `_breakdance_data` envelope correctly. **Never write `_breakdance_data` yourself via `execute-php` / `update_post_meta`** — that postmeta is a double-encoded JSON string that must be `wp_slash()`-ed before saving, and a raw write strips the inner escaping and corrupts the canvas to a blank page. Always go through these abilities.

## Visibility rules for templates / headers / footers / popups

A template post by itself does not render - it needs at least one rule group attached for Breakdance to know when to swap it in. `rule_groups` is a list of OR-groups (any group matching wins) where each group is a list of AND-rules (every rule in the group must match). Each rule is `{operand: "is" | "is_not", ruleSlug: string, value?: string | int | string[], ruleDynamic?: string, ruleCategorySlug?: string}`.

Only `operand` + `ruleSlug` (+ `value`) drive Breakdance's matcher at render time; `ruleCategorySlug` is informational metadata that the catalogue surfaces for grouping rules in the UI and the matcher itself ignores it. Always validate `ruleSlug` against `list-template-conditions` before writing - unknown slugs are stored verbatim and silently never match.

Discover the catalogue first - the available slugs vary per CPT and per installed plugin (WooCommerce, ACF, JetEngine, etc. each contribute additional rule slugs):

```
list-template-conditions  post_type=breakdance_template
list-template-conditions  post_type=breakdance_header
```

Real slugs by category on a stock install (sampled - the full list is dynamic):

| Category | Sample slugs |
|---|---|
| Singular | `post-dropdown-post`, `post-dropdown-page`, `post-dropdown-attachment`, `has-parent-post`, `has-parent-page`, `post-id`, `post-type`, `post-status`, `post-author`, `post-date`, `featured-image`, `comments-number`, `has-taxonomy` |
| Archive | `author`, `post-type-archive` (the CPT slug goes in `value`) |
| Taxonomy | `taxonomy` (with `value` set to the taxonomy slug + term) |
| User | `user-logged-in-status`, `user-role`, `user-registration-date` |
| Other | `search`, `wp_query_found_posts_count`, `dynamic-data`, `custom-php` |
| Date & Time | `day-of-week`, `current-time`, `current-date`, `post-date` |
| Referrer | `referer_url`, `referer_type` |
| Sessions | `page_views`, `session_count` |

Each slug has its OWN operand vocabulary and value shape. Sending the wrong shape stores fine but the runtime's `doesRuleApply` returns `false` and the template silently never matches. Always inspect the catalogue row for the slug you intend to use (each row carries `operands`, `valueInputType`, and the available `values`).

Verified shapes (the catalogue agrees with what the runtime accepts):

| Intent | rule_groups payload |
|---|---|
| Single specific post (id=42) | `[[{"operand":"is","ruleSlug":"post-dropdown-post","value":"42"}]]` |
| Single specific page (id=17) | `[[{"operand":"is","ruleSlug":"post-dropdown-page","value":"17"}]]` |
| Any post of post type `event` (multiselect operand) | `[[{"operand":"is one of","ruleSlug":"post-type","value":["event"]}]]` |
| Single posts OR single pages (multi-value of one rule) | `[[{"operand":"is one of","ruleSlug":"post-type","value":["post","page"]}]]` |
| Posts in the news term (AND with post-type) | `[[{"operand":"is one of","ruleSlug":"post-type","value":["post"]},{"operand":"is","ruleSlug":"taxonomy","value":"{\"taxonomySlug\":\"category\",\"termId\":62}"}]]` |
| All posts in a taxonomy (no specific term) | `[[{"operand":"is","ruleSlug":"taxonomy","value":"{\"allInTax\":\"category\"}"}]]` |
| Archive of CPT `event` | `[[{"operand":"is","ruleSlug":"post-type-archive","value":"event"}]]` |
| Logged-in users only | `[[{"operand":"is","ruleSlug":"user-logged-in-status","value":"logged in"}]]` |
| Search results page (no value needed) | `[[{"operand":"is","ruleSlug":"search"}]]` |

Gotchas to memorise:

- `post-type` uses the MULTISELECT operands (`is one of` / `is none of`) with a `string[]` value. `operand:"is"` + single string is the most common silent-fail.
- `taxonomy` value is a JSON-ENCODED STRING with keys `taxonomySlug` + `termId` (or `allInTax`), not a JSON object. The catalogue row hints this via `valueInputType:null` and the runtime json_decodes the string.
- `user-logged-in-status` accepted values are `"logged in"` and `"logged out"` (a literal space, not a hyphen).
- `post-dropdown-*` rules store the id as a NUMERIC STRING; the runtime casts to int internally.

To clear all conditions on a template (e.g. before reattaching the right ones), pass `rule_groups: []`. The rest of the template settings (priority, type, triggers for popups, fallback) are preserved.

## Dynamic data tokens

Breakdance embeds dynamic values with the **`[breakdance_dynamic field="<slug>"]` shortcode** placed in a text control's value — NOT a `{curly}` token. The shortcode is resolved per render context (e.g. per post inside a loop). A `{post_title}`-style token is printed **literally** because Breakdance never parses it (verified), so use the shortcode and confirm the `field` slug first.

Before embedding a token:

1. Call `novamira/breakdance-list-dynamic-data-fields` (filter with `category` or `return_type`) to confirm the token is registered in this environment. Categories include `Post`, `Author`, `Site Info`, `URL & Query`, `Utility`, `Archive`, `Featured Image`, plus the field-plugin integration blocks (`ACF`, `Metabox`, `Repeaters`, `Term`, `Current User`).
2. Call `novamira/breakdance-get-dynamic-data-field` on the chosen `slug` to read the full `controls` tree (default attributes, format options, fallback configuration). The list-vs-get split keeps the catalogue cheap; only fetch the controls when you need them.

Meta Box repeaters/groups are registered as one `metabox_group_<group_id>` repeater source plus separate child fields. The group source's `controls` can therefore be empty by design; its get response adds `subfields` with each child's `slug`, label, and Meta Box field type. Bind those child slugs inside the repeater context rather than treating `controls: []` as an undiscoverable group.

When binding a field-plugin field (ACF / Pods / JE / MB / ACPT / ASE), the wrapper is always the `[breakdance_dynamic field="<slug>"]` shortcode, but each provider exposes its own `field` slugs (and extra atts) and silent-fails differently - activate the `dynamic-data-binding` skill for the per-provider reference and the trap patterns to avoid.

## Popup triggers (popup CPT only)

A `breakdance_popup` post needs both `rule_groups` (where to show up) and `triggers` (when to fire). Triggers live in `settings.triggers` and are written via `edit-template`. The triggers field is an array of objects: `[{type: <enum>, ...extras}]`.

| `type` | Extra keys | Notes |
|---|---|---|
| `page_load` | `delay: <int ms>` | Fires on every page load after the delay. |
| `exit_intent` | (none) | Mouse leaves the viewport. |
| `scroll` | `percentage: <int 0-100>` | Fires when the user scrolls past the threshold. |
| `click` | `selector: <css>` | Fires on click of a matching element. |
| `inactivity` | `seconds: <int>` | Fires after N seconds of no input. |
| `time_on_page` | `seconds: <int>` | Fires after N seconds on the page. |

Multiple triggers in the array are OR'd: the popup fires on the first one that matches. To clear all triggers pass `triggers: []`, NOT `triggers: null` (the integration's settings merge stores literal null, which Breakdance's runtime then ignores - the visual result is the same but the postmeta carries a junk key).

## Reusable subtrees: global blocks

When a fragment repeats across pages (header strip, footer ribbon, CTA card, loop item template), build it ONCE as a `breakdance_block` post and embed it elsewhere through the `EssentialElements\Globalblock` placeholder element. The block stays a single source of truth; editing it updates every embed.

```
# Step 1 - build the reusable fragment as its own post
create-template post_type=breakdance_block title="Brand CTA"  -> id=42
add-element     parent_id=1   post_id=42  type=EssentialElements\Section
... (populate the block's tree)

# Step 2 - embed the block on the host
create-template post_type=breakdance_template title="Landing" -> id=99
add-element     parent_id=1   post_id=99  type=EssentialElements\Globalblock
                properties={"content":{"content":{"block": 42}}}
```

The reference key is `properties.content.content.block` and the value is the block post id (integer). Other host elements that accept a global-block reference (loop item templates, menu items, mini-cart, flexible content slots) use the same `content.content.block` shape - inspect their live controls with `get-element-schema` before creating them.

Deletion of the block leaves a DANGLING POINTER in every host - see the `delete-template` description for the non-cascade contract.

## Common element gotchas

- **Element type slugs are namespaced and case-sensitive.** The namespace separator is a single backslash (`EssentialElements\Section`, not forward-slash). The registered slug's exact casing is what the editor matches on — `Section`/`Column`/`Heading` capitalised, `Image2` with a trailing digit, `Postslist` with a lowercase trailing `l`, `PostsLoop`/`DynamicDataLoop` camel-cased with no separator, and the anomalous `Marqueetext`. For anything outside the verified table, first call `novamira/breakdance-list-element-types`, then `novamira/breakdance-get-element-schema`; do not guess from a folder or label.
- **Element `type` may be replaced via `edit-element`.** The optional `type` is resolved against the live registry before writing. Changing any node into `EssentialElements\CodeBlock` triggers the same explicit-confirmation gate as adding one; changing away from Code Block does not.
- **`properties` is shallow-merged.** Patching `{design: {layout: "flex"}}` replaces the entire `design` sub-tree, losing any other `design.*` keys. To partial-patch a nested sub-tree, call `get-element` first, merge locally, then pass the whole sub-object back.
- **Responsive values are the most common shallow-merge trap.** Spacing / sizing controls (`padding`, `margin`, `width`, `fontSize`, …) often carry a per-breakpoint map (`{desktop, tablet, mobile}`). Patching `{design: {padding: {desktop: "48px"}}}` wipes tablet/mobile silently. Always `get-element` first, modify the single breakpoint in the returned object, then pass the WHOLE `design` (or whichever sub-tree owns the responsive value) back.
- **Pass `null` to drop a key.** `edit-element` with `{design: null}` removes the `design` key from the stored properties; `{design: {}}` keeps the key with an empty object. Different states - prefer null when you want the key gone. NOTE: null-drop only applies to `edit-element`. `edit-template` writes literal null into settings.* keys instead of dropping them, so to clear a settings key (e.g. `triggers`) pass `[]` / `{}` / the appropriate empty shape, not null.
- **The builder's per-element Settings tab lives under `properties.settings.*`.** CSS classes at `settings.advanced.classes` (array of class-name strings, no leading dot), display conditions at `settings.conditions.visible` (boolean) and `settings.conditions.conditions` (same rule-group shape as template conditions), draft flag at `settings.advanced.draft` (true hides the element on the front end without deleting it). These are shared by every element type and returned by `get-element-schema` under the `settings` tab. Shallow-merge applies here too: patching `{settings: {advanced: {classes: […]}}}` wipes `settings.conditions` - get-element first, send the whole `settings` object back.
- **The root element (id 1) is immutable.** `edit-element`, `delete-element`, and `move-element` on id 1 all reject with `bd_invalid_input`. Add and reshape the tree starting from `parent_id=1`, don't try to edit the root in place.
- **`get-element-tree` truncates large trees.** Default `max_nodes` is 500. A canvas with more than that comes back with `truncated: true` and only the root summary - re-issue with a deeper `depth` only for the subtree you actually need.
- **Form-submission `fields` are truncated by default.** `get-form-submission` clamps every string value to 64 KiB and appends `(truncated, N bytes)`. Raise `max_field_bytes` only when you really need the full payload, and treat the result as sensitive (the response carries user IP, referer, user-agent, and submitted values verbatim).
- **Do not mix AI surfaces.** `breakdance-check-setup` reports `ai.ai_supported` separately from `ai.native_ai_surface_loaded`. Capability alone is informational. When `native_ai_surface_loaded` is true and the strong native-AI warning is present, choose either that surface or `novamira/breakdance-*` for the page and keep that choice for the entire workflow.

## Permalink awareness

`create-post` / `wp_insert_post` accepts `post_name` for the slug, and `breakdance-create-template` / `breakdance-edit-template` accept both `slug` and `post_name` (WordPress-native alias - `post_name` wins when both are passed). But the *displayed* permalink depends on `get_option('permalink_structure')`. If that option is empty (plain permalinks), the returned URL will be `?page_id=N` and the slug is invisible in the URL. When handing a URL back to the user, either verify `permalink_structure` is non-empty first, or note the caveat so the user is not surprised when `/<slug>/` doesn't resolve.

## What belongs elsewhere

- **Per-element control shapes** - for an element outside the verified table, call `novamira/breakdance-list-element-types` for the canonical live slug and then `novamira/breakdance-get-element-schema` for its controls. `get-element` is for an existing node's current values, especially before shallow-merge edits.
- **Global settings shape** - call `novamira/breakdance-get-global-settings` (optionally `section=<typography|colors|buttons|containers|forms|woocommerce|...>`) to read the live categories before patching. `edit-global-settings` shallow-merges those categories inside Breakdance's native `settings` envelope.
- **Form submissions** - read via `novamira/breakdance-list-form-submissions` (form-id and limit filters) and `novamira/breakdance-get-form-submission`. Each submission is a `breakdance_form_res` CPT post; the CPT does not declare WP trash support, so `delete-form-submission` is effectively permanent regardless of the `force` flag.
- **License gating** - some elements and the design library are Pro-only. `breakdance-check-setup` returns `license.mode` (`free` or `pro`) and `license.detection_source`. Respect a canonical `free` result. A fallback `free` result is not a reason to silently avoid Pro features: tell the user detection reported free and verify their license first.
