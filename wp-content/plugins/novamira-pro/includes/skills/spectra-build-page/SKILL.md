---
name: spectra-build-page
description: Activate when composing pages with Spectra (UAGB) blocks — containers, buttons, forms, popups, image galleries, testimonials — or when managing Spectra popup display conditions from the Pro layer. Applies to any WordPress site where the Spectra plugin (`ultimate-addons-for-gutenberg`, gate `UAGB_VER`) is active, with or without the Spectra Pro add-on (`spectra-pro/spectra-pro.php`, gate `SPECTRA_PRO_VER`). Do NOT activate for the Spectra One theme (that has its own `spectra-one-build-page` skill), for the new Spectra Blocks AI plugin (`spectra-blocks/`), or for other block-editor plugins.
---

# Building pages with Spectra (UAGB)

Spectra ships ~77 free blocks in the `uagb/*` namespace and, when the Pro add-on is licensed and active, extends the catalogue plus the `spectra-popup` CPT with display-condition metadata. Novamira Pro exposes a Novamira-native `novamira/spectra-*` surface (21 free abilities + 3 Pro-gated) that layers progressive-disclosure output, WP-native input aliases, and stable `WP_Error` codes on top.

## When to use

Activate when:

- The site has the Spectra plugin active (`UAGB_VER` defined) and the user wants to add / rearrange / read Spectra blocks in a post, a page, or the popup CPT.
- The user wants to compose a Spectra popup — create the CPT post, edit its block tree, then attach display conditions (Pro-gated).
- The user wants to toggle individual Spectra blocks on/off across the whole install (block-activation registry).
- The user wants to enable / disable a Google Fonts delivery mode (local vs remote, preload, FSE-globally).

Do NOT activate when:

- Only the Spectra One theme is active without the plugin — use `spectra-one-build-page`.
- The Spectra Blocks AI plugin (`spectra-blocks/`) is the target — that has its own `spectra/*` ability set already exposed by the plugin itself, disjoint from this surface.
- The user is editing block trees on a non-Spectra site (Gutenberg core blocks) — reach for the base `novamira/gutenberg-*` abilities directly.

## The big idea

Spectra makes three distinct kinds of state:

1. **`uagb/*` blocks inside `post_content`** — regular Gutenberg block markup. Every read / write of the tree flows through the **base `novamira/gutenberg-*` pipeline**: `gutenberg-get-content` snapshots the tree; changes are composed as a FULL replacement `block_spec` and queued with `gutenberg-add-pending-change` (the pipeline's only operation is `replace-content` — always submit the whole tree, never a delta); `gutenberg-enable-batch-finalization` produces the URL whose browser page validates and persists. Spectra adds NO block-tree CRUD ability of its own.
2. **`spectra-popup` CPT posts** — the popup builder lives on a dedicated post type. Titles / status / block content are wrapped by `novamira/spectra-{list,get,create,edit,duplicate,delete,enable,disable}-popup`; the block tree inside the popup post is edited via the same `gutenberg-*` abilities on the popup's post id.
3. **Global settings / options** — `uag_*` and `spectra_*` options that drive editor defaults, Google-Fonts delivery, and the block-activation registry. Wrapped by `novamira/spectra-{get-settings,set-editor-settings,set-block-activation,list-google-fonts,set-google-fonts-mode}`.

Everything Pro adds sits under `novamira/spectra-pro-*` and is registered only when `spectra_pro_active()` (`SPECTRA_PRO_VER` defined). On a free install those abilities are absent from the agent's tool catalogue — do not attempt to call them.

## Discover before you act

Always start with:

1. **`novamira/spectra-check-setup`** — reports `plugin.{active,version,min_supported,min_satisfied}`, `pro.{active,version,min_supported,min_satisfied}`, `license.mode`, `blocks_registered`, `popups_count`, `forms_usage_count`. Stop if `plugin.min_satisfied` is false and surface the version gap to the user. The minimum-supported floor is deliberate (`NOVAMIRA_PRO_SPECTRA_MIN_VERSION` — 2.19.0 today).
2. **`novamira/spectra-list-blocks`** — compact catalogue: `name`, `family`, `category`, `dynamic_render`, `title`. Filter by `family: free|pro|all`. Use before you pick a block slug; do not hardcode `uagb/*` names.
3. **`novamira/spectra-get-block-schema name=uagb/<...>`** — supports + attributes shape for one block. Note: UAGB defines block attributes in JavaScript, not PHP, so the `attributes` map is populated only for blocks that also register from PHP (dynamic-render blocks like `uagb/forms`, `uagb/post-grid`, `uagb/popup-builder`). Static-render blocks return an empty attributes map — the block still works in `post_content`, you just cannot introspect its editor controls from PHP.

## Canonical sequence — add a Spectra section to an existing post

1. **`spectra-check-setup`** — confirm plugin state.
2. **`spectra-list-blocks family=free`** — pick the section building blocks (typical trio: `uagb/container`, `uagb/advanced-heading`, `uagb/buttons`).
3. **`gutenberg-get-content target_id=<id>`** (`include_attributes=true`) — snapshot the existing tree and locate the insertion point.
4. **Compose the `block_spec`** — the existing blocks plus the new section as `{name, attributes, innerBlocks}` entries (container → heading / text / buttons). The pipeline replaces the whole `post_content`, so the spec must carry everything the page should keep.
5. **`gutenberg-add-pending-change target_id=<id> block_spec=…`** — queue the change. Do NOT call `gutenberg-write-content` for `uagb/*` markup: that ability is reserved for `novamira/*` dynamic-only blocks; native/static blocks go through the pending-change pipeline so browser-side validation runs.
6. **`gutenberg-enable-batch-finalization batch_id=…`** — send the finalization URL to the user; the Block Editor Queue page validates and persists. Check the outcome with `gutenberg-get-pending-batch`.
7. **Optional: `spectra-set-block-activation`** — if the user disabled the block globally, enable it before the front end can render it (see gotcha below).

## Canonical sequence — build a Spectra popup

1. **`spectra-check-setup`** — plus check `pro.active` if the popup needs display conditions.
2. **`spectra-list-popups`** — compact rows to see what already exists (id, title, status, enabled, modified).
3. **`spectra-create-popup title=<...>`** — accepts short `title` OR WP-native `post_title`; passing both returns `spectra_alias_conflict`. Optional `content` seeds the block tree; omit to use Spectra's default popup template.
4. **Populate the popup's block tree** — compose the `block_spec` (typical: `uagb/container` → heading + text + CTA button) and run the same pending-change pipeline on the popup's post id: `gutenberg-add-pending-change target_id=<popup_id> block_spec=…` → `gutenberg-enable-batch-finalization`.
5. **`spectra-edit-popup id=<popup_id>`** — refine title / status / content in one call. Idempotent.
6. **`spectra-enable-popup id=<popup_id>`** — flip the `enabled` flag so the popup is eligible to render.
7. **Pro only: `spectra-pro-set-popup-conditions`** — attach display conditions (see next section).

To **remove** a popup: `spectra-delete-popup id=<popup_id> confirm=true`. `confirm=true` is required — the ability rejects a bare `id` with `spectra_invalid_input` so an agent cannot delete by typo.

To **clone** a popup as a template for a variant: `spectra-duplicate-popup id=<popup_id> title_suffix=" (copy)"`. Deep-copies settings + block tree + meta; returns the new id and `source_id`. `title_suffix` caps at 128 bytes.

## Pro popup display conditions

Available only when `spectra_pro_active()` returns true. Three abilities under the `spectra-pro-*` prefix:

- **`spectra-pro-check-setup`** — `plugin.{active,version,min_supported,min_satisfied}` (min 1.2.0 — Spectra Pro versions independently of UAGB) plus `license.active` (via BSF License Manager). Call once before any Pro workflow.
- **`spectra-pro-list-popup-conditions id=<popup_id>`** — returns the current 4-key bundle: `trigger` (string — typical values `load`, `click`, `time-delay`, `exit-intent`, `scroll`), `trigger_delay` (int seconds), `display_inclusions` (object `{rule, specific, specificText, ...}`), `display_exclusions` (same shape). Empty bundles return as `{}` (JSON object literal). Fresh popups arrive with the Spectra-side defaults BSF sets on `register_pro_meta` — do not assume "empty" means "no defaults".
- **`spectra-pro-set-popup-conditions id=<popup_id> [trigger] [trigger_delay] [display_inclusions] [display_exclusions]`** — merge-update. `minProperties: 2` at the schema layer — `id` alone is rejected (`spectra_invalid_input`). `display_inclusions` and `display_exclusions` are **REPLACE-ALL** writes: the whole bundle is overwritten, not merged key-by-key. Response `written[]` lists ONLY the meta keys whose stored value actually changed on this call — a key you submitted whose value already matched target is a silent no-op (idempotent). Not transaction-safe: two concurrent calls on the same popup with overlapping keys are last-write-wins per meta row.

Typical Pro workflow:

```
spectra-pro-check-setup                                 → gate on plugin.min_satisfied + license.active
spectra-pro-list-popup-conditions id=42                 → read current bundle
spectra-pro-set-popup-conditions id=42
  trigger="time-delay" trigger_delay=5
  display_inclusions={"rule":["basic-singulars"],"specific":[],"specificText":[]}
```

For `display_inclusions.rule`, discover valid slugs on the target install by inspecting an existing conditioned popup (BSF ships the vocabulary in JS, not PHP — no catalogue ability exists). Common slugs: `basic-singulars`, `basic-archives`, `basic-404`, `basic-search`, `special-front`, `special-blog`, `special-date-archives`.

## Building block trees — delegate to the `gutenberg-*` pipeline

The Novamira base plugin ships one write path for block trees, and it operates on `post_content` for ANY block namespace, including `uagb/*`. Novamira Pro deliberately does NOT re-implement it per plugin. The surface:

| Task | Ability |
|---|---|
| Read a post's block tree | `novamira/gutenberg-get-content target_id=<id>` — compact tree; `include_attributes=true` for attribute values, `max_depth` to bound nesting |
| Any structural change (insert / edit / move / delete) | Compose the modified FULL tree as a `block_spec`, then `novamira/gutenberg-add-pending-change target_id=<id> block_spec=…` (the only operation is `replace-content`) |
| Persist the queued change | `novamira/gutenberg-enable-batch-finalization batch_id=…` → the user opens the returned URL; the Block Editor Queue page validates browser-side and saves |
| Inspect a batch's outcome | `novamira/gutenberg-get-pending-batch batch_id=…` |

There are no per-block CRUD abilities (`gutenberg-add-block`, `gutenberg-edit-block`, and similar granular names do not exist) — every change is a full-tree read-modify-write. Use the same pipeline on the popup post id too — the popup CPT is regular Gutenberg content stored in `post_content` like any other post type. Do not look for `spectra-add-block` / `spectra-edit-block` either — they do not exist by design.

### Editor-assigned attribute: `block_id`

Every `uagb/*` block carries a `block_id` that Spectra's editor assigns at mount — per-block CSS and the `uagb-block-<id>` wrapper class are keyed to it, and a missing value passes block validation silently and surfaces only as an unstyled front end. Whether the agent must supply it depends on the base Novamira version (`NOVAMIRA_VERSION` constant):

- **Base 1.11.4 and later** — the Block Editor Queue mounts the submitted blocks in the hidden editor before serializing, so `block_id` (and Spectra's mount normalizations such as `classMigrate`) are assigned automatically. Do not supply it.
- **Base 1.11.3 and earlier** — the finalizer serializes without mounting; supply a unique 8-char lowercase-hex `block_id` (e.g. `"a1b2c3d4"`) on every `uagb/*` block yourself.

## Attribute shapes: read before you write

Most `uagb/*` blocks have hundreds of controls (typography, spacing, borders, animation, responsive breakpoints, hover states). Do NOT guess attribute names or shapes. The reliable pattern:

1. Read existing Spectra content with `gutenberg-get-content include_attributes=true` — pages built in the Spectra editor carry the exact attribute shapes the plugin assigns, and they are your best documentation.
2. Copy the relevant attribute keys into your composed `block_spec` and merge your changes on top.
3. After finalization, re-read with `gutenberg-get-content` to verify the persisted shape matches what you intended.

If the site has no existing Spectra content to crib from, `spectra-get-block-schema` covers dynamic-render blocks; for static-render blocks (empty PHP-side attributes) start minimal and let the finalizer's editor pass normalize the block, then read the result back.

## Style hierarchy

Tier 1 — **Global editor settings**: `spectra-get-settings` exposes the 17 whitelisted editor keys (containers defaults, animation-extension toggle, block-visibility mode, dynamic-content mode, load-locally toggle, and 13 more). If the container padding / font family / button radius the user wants matches the global default, do NOT override at the block level — write it once with `spectra-set-editor-settings` and every future block inherits.
Tier 2 — **Container-inherited styles**: `uagb/container` propagates typography + spacing to its `uagb/*` children via UAGB's own inheritance. Set once on the container; do not repeat on every heading / text / button inside.
Tier 3 — **Block-level attribute overrides**: for genuine one-offs (a hero heading with a distinct size scale, a CTA with a bespoke color) set the value on the block's attributes.
Tier 4 — **Custom CSS on the block**: last resort — every UAGB block exposes a `UAGCSS` (or `UAGCustomCSS`) attribute for raw CSS. Use only when no existing control covers the intent.

Colors, spacing, typography scale should follow the site's theme.json / global editor settings — never raw hex / pixel values scattered across block attributes.

## Static blocks vs dynamic-render blocks

Spectra ships two flavours:

| Kind | Render path | Examples | Notes |
|---|---|---|---|
| **Static-render** | Server sends the saved block HTML from `post_content` verbatim; no PHP callback runs. | `uagb/container`, `uagb/advanced-heading`, `uagb/buttons`, `uagb/testimonial`, `uagb/image-gallery`, `uagb/marketing-button` | `spectra-get-block-schema` returns EMPTY `attributes` (UAGB registers them in JS only). The block still renders correctly — just no PHP-side schema. |
| **Dynamic-render** | Block invokes a PHP render callback on every request; the saved `post_content` holds only a placeholder comment. | `uagb/forms`, `uagb/post-grid`, `uagb/post-carousel`, `uagb/popup-builder`, `uagb/post-timeline`, `uagb/how-to`, `uagb/faq`, `uagb/table-of-contents` | `spectra-get-block-schema` returns the FULL attributes + supports map. Query args live in the block attributes; changing them means editing the block, then Spectra re-runs its query on the next front-end request. |

`spectra-list-blocks` reports `dynamic_render: true|false` on every row. When you need to introspect attribute controls, prefer a dynamic block for `get-block-schema` — the answer will be complete.

## Popup triggers & display rules

Popup rendering is a two-step gate:

1. **Enabled?** — the `spectra_popup_enable` meta on the popup post. `spectra-enable-popup` / `spectra-disable-popup` flip this. A disabled popup never renders.
2. **Display rules matched?** — Pro-only. The `spectra-popup-display-inclusions` and `spectra-popup-display-exclusions` bundles gate which pages the popup appears on. WITHOUT the Pro add-on, an enabled popup with no rules renders **everywhere** (that is Spectra's free-tier behaviour). Warn the user before enabling a popup on a free install.
3. **Trigger fired?** — `spectra-popup-trigger` decides how the popup appears (`load`, `click`, `time-delay`, `exit-intent`, `scroll`). `trigger_delay` in seconds when `trigger=time-delay`. Free-tier defaults render on load; Pro extends the vocabulary.

## Block-activation registry

The `_uagb_blocks` option stores per-block on/off flags. Disabled blocks are hidden from the block-inserter AND stripped from the front-end assets — a disabled block that already exists in `post_content` still renders as raw HTML but loses its Spectra CSS/JS.

- **`spectra-set-block-activation block=uagb/<name> enabled=true|false`** — flips one row. Idempotent. Flushes the Spectra asset cache + `_uagb_blocks` transient so the change is visible on the next request. Rejects non-Spectra namespaces (`spectra_block_not_found`).
- **Bulk enable/disable** — no dedicated ability; call `set-block-activation` in a loop for the subset you want to flip. Do NOT reach into `_uagb_blocks` directly with `execute-php` unless you know the atomic-merge helper the ability wraps (see integration skill).

## Google Fonts delivery

Two orthogonal knobs:

- **`spectra-list-google-fonts`** — enumerates every font family declared across the three UAGB storage locations: global settings (`uag_select_font_globally`), global block-style presets (`spectra_gbs_google_fonts`), and FSE-globally (`spectra_global_fse_fonts`). Reports the merged catalogue + per-mode delivery flags (`loaded_locally`, `preload_local`, `load_fse_globally`).
- **`spectra-set-google-fonts-mode`** — flips the three delivery flags. `mode=local|remote` is a convenience for the `loaded_locally` toggle; `preload_local` and `load_fse_globally` are boolean opt-ins. `changed: true` in the response when the write actually mutated the option; `changed: false` on an idempotent no-op.

Font *choice* (which family a specific block uses) lives on the block's attribute — set it by editing the block's attributes in a composed `block_spec` through the pending-change pipeline, not through a Spectra ability.

## Form blocks — inventory only

`novamira/spectra-list-form-blocks` enumerates posts that contain at least one `uagb/forms` block, with per-post `form_count` (marker occurrences). Compact rows: `id`, `post_type`, `status`, `form_count`, `modified`.

Spectra does NOT persist form submissions — every UAGB form fires an AJAX request that goes through email delivery only. There is no submissions table, no CPT, no postmeta. If the user asks to "read past submissions", explain the pipeline is email-only and offer to point them at a proper form plugin (Ninja Forms, WPForms, Gravity Forms) — those integrations already have submission triage under `novamira/<plugin>-list-submissions`. See `spectra-integration` skill for a full recipe.

## High-impact confirmation

For irreversible actions, echo back what you're about to touch and wait for explicit user confirmation:

- **`spectra-delete-popup id=<id> confirm=true`** — echo `title` from `spectra-get-popup` first; deletion is permanent (WP forces `force_delete` on the popup CPT — no trash).
- **`spectra-set-block-activation enabled=false`** on a widely-used block — count the affected posts with an `execute-php` snippet (see integration skill) before flipping; a disabled `uagb/container` breaks every page that uses containers.
- **`spectra-pro-set-popup-conditions display_inclusions={rule:[]}`** — an empty `rule` array with Spectra Pro active means "no pages match" = the popup silently disappears. Confirm the user meant to disable-by-scope rather than by `disable-popup`.

## Gotchas

- **`manage_options` capability everywhere.** Every ability (free + Pro) is gated on the `novamira_permission_callback` which requires `manage_options`. A non-admin gets `403 novamira_forbidden` — this is deliberately stricter than a per-plugin cap because Spectra exposes no stable role-mapping filter.
- **`min_satisfied` gates every write.** `spectra-check-setup.plugin.min_satisfied` is your one gate — if false, every write returns a hard error. Surface the version gap to the user before attempting a fix.
- **Popup CPT is admin-only.** UAGB registers `spectra-popup` with `capability_type: manage_options`. This is inherited by every popup ability — the extra permission callback layer is defense-in-depth.
- **`update_post_meta` does NOT fire `save_post`.** Every ability that writes popup meta calls `clean_post_cache($id)` immediately after. If you write meta yourself via `execute-php`, do the same or the next read comes back stale.
- **`_uagb_blocks` is a single JSON blob.** Naïve read-modify-write races with concurrent editors. The `set-block-activation` ability wraps an atomic merge helper (`sp_option_write_json_atomic`); replicating that logic in `execute-php` requires the same `wp_cache_delete → get_option → merge → update_option` sequence. Not transaction-safe cross-request — acceptable for admin ability, worth flagging to the user.
- **UAGB attribute maps are JS-only for static blocks.** `spectra-get-block-schema` returning `attributes: {}` for `uagb/container` is not a bug — see the "Static vs dynamic-render blocks" table above.
- **Spectra One theme + Spectra plugin double-load Google Fonts.** If both are installed, both may try to enqueue the same family. `spectra-check-setup` reports Spectra plugin state; cross-check with `spectra-one-check-setup` if the theme is also `spectra-one`, and prefer configuring fonts on one surface, not both.
- **`spectra-blocks/` (AI plugin) is a DIFFERENT surface.** Namespace disjoint (`spectra/*` vs `uagb/*`), CPT disjoint (`spectra-blocks-popup` vs `spectra-popup`), options disjoint (`spectra_blocks_*` vs `uag_*`/`_uagb_*`). Do not conflate the two. This skill covers UAGB + Spectra Pro; the AI plugin already exposes ~45 native abilities under `spectra/*`.
- **BSF popup meta defaults.** Fresh popups arrive with the Spectra-side default trigger set to `load` (BSF's own `register_pro_meta` default). Read `list-popup-conditions` on a fresh popup and you'll see `trigger: "load"` — that is the plugin's default, not our write.
- **Pro `display_inclusions` bundle shape.** It's an associative object (`{rule, specific, specificText, ...}`), NOT a list. Passing a JSON array `[]` will be rejected at the schema layer. Empty bundle → JSON `{}`.

## Tool choice matrix

| Intent | Ability | Notes |
|---|---|---|
| Sanity check | `spectra-check-setup` (+ `spectra-pro-check-setup` if Pro is claimed) | Always first. |
| Discover blocks | `spectra-list-blocks family=free\|pro\|all` | Compact catalogue. |
| Discover popup posts | `spectra-list-popups [status] [enabled] [limit] [offset]` | Compact rows. |
| Read one block schema | `spectra-get-block-schema name=uagb/<...>` | Empty attrs for static-render is expected. |
| Read editor settings | `spectra-get-settings` | 17 whitelisted keys. |
| Toggle one editor setting | `spectra-set-editor-settings settings={...}` | Whitelist-only; unknown key → `spectra_unknown_setting`. |
| Enable / disable a block globally | `spectra-set-block-activation block=uagb/<...> enabled=bool` | Flushes asset cache. |
| Full popup lifecycle | create → compose block_spec → gutenberg-add-pending-change → finalize → edit → duplicate → enable → disable → delete | See canonical sequence. |
| Attach popup display rules | `spectra-pro-set-popup-conditions` | Pro-gated. |
| Google Fonts delivery | `spectra-list-google-fonts` / `spectra-set-google-fonts-mode` | Delivery only — font choice lives on the block. |
| Find posts that use a form block | `spectra-list-form-blocks` | Inventory; not submissions. |
| Everything else | `spectra-integration` skill → `execute-php` recipes | See that skill for reCAPTCHA, Instagram, loop-builder, ad-hoc queries. |

## What belongs elsewhere

- **Block-tree changes on `uagb/*` in `post_content`** → base `novamira/gutenberg-*` pending-change pipeline. This skill points at it; it does not re-implement it.
- **Global editor settings troubleshooting, Google Fonts, block activation queries, ad-hoc `_uagb_*` reads, reCAPTCHA keys, Instagram Feed OAuth, loop-builder markup** → `spectra-integration` skill. Anything that is not "build a page / build a popup" is over there.
- **Spectra One theme design tokens (color palette, typography, style variations)** → `spectra-one-build-page` skill. Even on a site running both, keep the theme layer separate from the block layer.
- **Novamira Visual live-editor tasks** → the Visual workspace MCP + Visual skills; abilities in this skill are all server-side / persisted changes, no live-editor bridge.

## Conventions

- Ability slugs follow `novamira/spectra-<verb>-<object>` for free abilities and `novamira/spectra-pro-<verb>-<object>` for the Pro sub-layer. Verbs from the fixed vocabulary (`list`, `get`, `check`, `create`, `edit`, `delete`, `duplicate`, `enable`, `disable`, `set`).
- Compact list, full get: every `list-*` returns minimal rows; every `get-*` returns full payload with byte caps on large fields (`max_field_bytes` default 20_000, opt-in `0` for full).
- WP-native input aliases: `title`↔`post_title`, `content`↔`post_content`, `status`↔`post_status`, `slug`↔`post_name` on every popup CRUD ability. Passing both forms returns `spectra_alias_conflict`.
- Errors: `spectra_invalid_input` (400), `spectra_popup_not_found` (404), `spectra_block_not_found` (404), `spectra_unknown_setting` (400), `spectra_write_failed` (500), `spectra_alias_conflict` (400). Pro layer adds `spectra_pro_required` (409).
- Annotations: list / get / read → `readonly`; delete + destructive writes → `destructive`; edits and idempotent flips → `idempotent`.
