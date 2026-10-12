---
name: spectra-blocks-integration
description: Activate when inspecting or troubleshooting the NEW Spectra Blocks plugin or its Spectra Blocks Pro add-on (Brainstorm Force) — block catalogue and activation state, Popup Builder posts, spectra_blocks_* settings, reCAPTCHA key state, Global Block Styles — on a site where the `spectra-blocks` plugin is active. NOT for sites running the legacy Spectra (UAGB / ultimate-addons-for-gutenberg) plugin.
---

# Spectra Blocks Integration

Read-only discovery surface for the Spectra Blocks plugin pair: the free
`spectra-blocks` plugin (48 blocks under the `spectra/*` namespace) and the
`spectra-blocks-pro` add-on (37 blocks under `spectra-pro/*` plus one
`spectra/*` child block). Both are a ground-up rewrite: new PHP namespaces
(`SpectraBlocks\`, `SpectraBlocksPro\`), new block names, new CPT, new
option keys. Nothing here reads or writes `uagb/*` data.

## When to use

Activate when the user asks about:

- **Install state** — "is Spectra Blocks set up correctly?", version pairs,
  why the plugin refuses to run, Pro add-on presence.
- **Block catalogue** — which `spectra/*` / `spectra-pro/*` blocks exist on
  this install, which are deactivated, which are child blocks that cannot
  stand alone in markup.
- **Popups** — listing Popup Builder posts, reading one popup's block
  markup, checking whether a popup is enabled and how often it repeats.
- **Settings** — editor-experience toggles, extension toggles (responsive,
  dynamic content, animations, Global Block Styles, display conditions),
  font-delivery flags, reCAPTCHA key state, CSS-cache flag.
- **Global Block Styles** — how many saved styles exist and which blocks
  they target.

Do NOT activate for:

- Sites running the **legacy Spectra plugin** (`ultimate-addons-for-gutenberg`,
  `uagb/*` blocks, `UAGB_VER` constant). That is a different product with a
  different data model; these abilities return nothing useful for it.
- **Composing page content** out of blocks. Reading and writing block trees
  in `post_content` goes through the generic `novamira/gutenberg-*`
  abilities from the base plugin; this surface tells you *which* Spectra
  blocks are available, not how to place them.

  Base Novamira 1.11.4 and later also expose the generic
  `novamira/gutenberg-list-block-types` / `novamira/gutenberg-get-block-type`.
  Spectra Blocks registers every block from per-block `block.json` metadata
  server-side, so `gutenberg-get-block-type name="spectra/..."` (or
  `spectra-pro/...`) returns the complete attribute schema, and
  `gutenberg-list-block-types namespace="spectra"` gives a generic
  catalogue view. Division of labor: `spectra-blocks-list-blocks` is the
  surface for Spectra-specific state (activation map, free/pro
  classification, `top_level_only` / child semantics); the generic pair is
  the surface for attribute schemas and cross-namespace enumeration.
- **Writing** anything. Sprint 1 is read-only — no create/edit/delete
  abilities exist yet on this surface.

## Discover first

**Absence is the availability signal.** The whole `novamira/spectra-blocks-*`
category registers only when the free plugin passed its availability gate
(installed, initialized, at `>= 0.0.8`, no legacy spectra-v3 conflict). If
these abilities are missing from the tool list, the surface is unavailable —
there is no check-setup to call in that state, and nothing in this skill can
diagnose it further. Tell the user the possible causes and ask them to
verify manually in their WordPress admin: the Spectra Blocks plugin is not
installed or not active, its version is below `0.0.8`, WordPress is below
6.6 or PHP below 8.1, or the legacy UAGB spectra-v3 bootstrap
(`SPECTRA_3_FILE`) is loaded alongside (see Gotchas).

When the abilities ARE present, begin with
`novamira/spectra-blocks-check-setup`. It returns:

- `plugin.{active,version,min_supported,min_satisfied}` — free plugin state.
  When the ability is callable at all, `min_satisfied` is true by
  construction (the gate already passed); the block exists so version and
  floor are reportable in one place.
- `pro.{active,version,min_supported,min_satisfied}` — Spectra Blocks Pro
  add-on state. Pro requires the free plugin at `>= 0.0.8` to initialize;
  `active` true with `min_satisfied` false means the add-on is present but
  not running.
- `environment.{wp_version,php_version}` — the live runtime versions.
- `legacy.{spectra_v3_present,uagb_present}` — coexistence flags.
  `spectra_v3_present` reads false whenever this ability answers (the gate
  refuses otherwise); `uagb_present` flags a classic UAGB 2.x running
  alongside.
- `blocks_registered.{free,pro,total}` — registry counts. Expect free = 48
  (49 with Pro active, which contributes one `spectra/*` child block) and
  pro = 37 when the Pro add-on runs.
- `popups.{total,by_status,compat_total}` — Popup Builder inventory.
  `by_status` is a fixed six-bucket object (publish, draft, private,
  pending, future, trash); trash never counts toward `total`.
  `compat_total` counts the migration-compat `spectra-popup` CPT (posts
  created in the UAGB spectra-v3 beta).
- `features` — compact toggle snapshot (templates button, responsive,
  dynamic content, animations, GBS, display conditions, font locality,
  CSS cache).

## Domain model

### Blocks (free + pro)

- **Free**: 48 blocks under `spectra/*`, registered from per-block
  `block.json` metadata into the standard `WP_Block_Type_Registry`. There
  is no plugin-internal shadow list to reconcile — the WP registry is the
  source of truth, and on base Novamira 1.11.4 and later the generic
  `novamira/gutenberg-list-block-types` / `gutenberg-get-block-type`
  abilities read it directly (server-side schemas are complete because
  every block registers its full `block.json` metadata server-side).
- **Pro**: 37 blocks under `spectra-pro/*` plus `spectra/countdown-child-expiry-wrapper`.
  **Registration is not license-gated** — an unlicensed but active Pro
  install still registers all Pro blocks.
- **Child blocks**: many blocks are parent-scoped composites (accordion
  items, countdown digits, form fields, loop-builder parts). They declare a
  `parent` list in block.json and cannot stand alone in markup. Use the
  `parent` field (or `top_level_only: true`) from `list-blocks` to tell
  them apart.
- **Activation**: the option `_spectra_blocks_blocks` maps *unprefixed*
  free block slugs (`container`, not `spectra/container`) to activation
  values. Two vocabularies coexist in the same option: the plugin's native
  toggle ability writes `'yes'`/`'no'`, the dashboard path writes
  `'disabled'` for off. Both `'no'` and `'disabled'` mean deactivated;
  anything else (including absent) means active. Deactivated blocks stay
  registered in the WP registry — activation only affects the editor
  inserter and asset enqueueing — so `list-blocks` reports them with
  `active: false` rather than dropping them.

### Popups

CPT `spectra-blocks-popup` (admin-only capabilities, `template_lock: all`
on a single `spectra/popup-builder` wrapper block). Three registered meta
keys with defaults:

| Meta key | Type | Default |
|---|---|---|
| `spectra-blocks-popup-type` | string | `unset` |
| `spectra-blocks-popup-enabled` | boolean | `false` |
| `spectra-blocks-popup-repetition` | number | `1` |

A second CPT `spectra-popup` exists for migration compat (popups created in
the UAGB spectra-v3 beta, with `spectra-popup-*` meta keys). Sprint 1 only
counts it in check-setup; list/get target the canonical CPT.

### Settings

Options follow the `spectra_blocks_{key}` pattern (per-blog option first,
site option fallback on multisite). `get-settings` groups the whitelisted
keys into buckets:

- `editor` — templates button, on-page CSS button, block condition, masonry
  gallery, quick-action sidebar, collapse panels, copy-paste, visibility
  mode, editor spacing, Font Awesome 5, auto block recovery.
- `extensions` — block responsive, dynamic content, animations, Global
  Block Styles (GBS), display conditions.
- `fonts` — the four delivery flags plus a count of globally-selected fonts.
- `recaptcha` — v2/v3 site keys verbatim, secrets as presence booleans.
- `misc` — CSS-cache disable flag, content-width source.
- `global_block_styles` — summary of the shared `spectra_global_block_styles`
  option: entry count + distinct target block names.

Values are stored strings and are not uniform: most toggles use
`enabled`/`disabled`, but the templates button uses `yes`/`no`. Compare
against the stored vocabulary, not a normalized boolean.

## Workflows

### 1. Health check / support triage

1. Check the tool list for `novamira/spectra-blocks-*`. **Absent?** The
   surface gate refused — report the possible causes (plugin not
   installed/active, version below `0.0.8`, WP < 6.6 / PHP < 8.1, or the
   legacy spectra-v3 conflict — see Gotchas) and ask the user to check
   their WordPress admin to confirm which one applies; nothing in this
   surface can do it for them.
2. Present? Call `novamira/spectra-blocks-check-setup` and report the
   install: versions, block counts, popup inventory, feature snapshot.
3. Report Pro state separately: `pro.active && !pro.min_satisfied` means
   the add-on is present but not running; `legacy.uagb_present` means a
   classic UAGB 2.x coexists (tolerated, but a fully separate product).

### 2. "Which blocks can I use here?"

1. `novamira/spectra-blocks-list-blocks` with `top_level_only: true` —
   the insertable catalogue without composite children.
2. For a specific feature, filter: `{"name_contains": "form"}` or
   `{"namespace": "pro"}`.
3. Check `active` per record — a block with `active: false` is hidden from
   the editor inserter; tell the user to re-enable it in the Spectra Blocks
   dashboard (or note that existing content still renders).
4. `pro: true` records need the Pro add-on on any site the content is
   migrated to; markup referencing them breaks on free-only installs.
5. For a chosen block's full attribute schema, call
   `novamira/gutenberg-get-block-type name="spectra/..."` (base Novamira
   1.11.4 and later).

### 3. Popup inventory and inspection

1. `novamira/spectra-blocks-list-popups` (optionally `{"status": ["publish"]}`).
2. Interpret each record: `enabled` gates front-end display entirely;
   `popup_type` stays `unset` until first configured; `repetition` is how
   many times a visitor sees the popup (default 1).
3. `novamira/spectra-blocks-get-popup` with the chosen id for the full
   block markup. The tree is always a single `spectra/popup-builder`
   wrapper (the CPT template-locks it). If `content_truncated` is true,
   re-call with a larger `max_field_bytes` (hard maximum `100000`); still
   truncated at the maximum means the stored markup exceeds what a single
   response will carry — there is no uncapped mode.
4. `popups.compat_total > 0` in check-setup means beta-era popups exist in
   the `spectra-popup` CPT — visible in wp-admin but outside this surface's
   list/get; count them as inventory when auditing.

### 4. Settings / reCAPTCHA audit

1. `novamira/spectra-blocks-get-settings`.
2. Forms + reCAPTCHA: a configured pair needs BOTH the site key (echoed
   verbatim) and the secret (`has_secret_v2` / `has_secret_v3` true). One
   without the other = misconfigured; never ask the user to paste the
   secret into chat — have them set it in the Spectra Blocks dashboard.
3. Stale-CSS complaints: check `misc.disable_css_cache` and the GBS
   summary — a large `global_block_styles.count` with `extensions.gbs_extension`
   disabled means saved styles exist but are not being served.
4. Font performance: `fonts.load_gfonts_locally` / `preload_local_fonts`
   are the self-hosting knobs; `select_font_globally_count > 0` with
   `load_select_font_globally` disabled means global fonts are picked but
   not loaded site-wide.

## Gotchas

- **Legacy-conflict refusal.** If the UAGB spectra-v3 bootstrap defines
  `SPECTRA_3_FILE`, Spectra Blocks bails before loading its runtime — both
  register the same PHP namespace and block names. The availability gate
  mirrors that refusal, so in this state NO `novamira/spectra-blocks-*`
  ability registers at all: the missing category is the symptom, and the
  user confirming both plugins are active on their plugins screen is the
  verification. The fix is deactivating one of the two plugins — the user
  must choose.
- **Legacy UAGB (2.x) is a different case.** `legacy.uagb_present` true
  with `spectra_v3_present` false means classic Spectra (UAGB) is active
  alongside; the plugins tolerate each other, but content uses two disjoint
  block namespaces. Keep the two products separate in any advice.
- **Pro registration is not license-gated.** Never infer licensing from
  `blocks_registered.pro` or `pro: true` records — an expired-license site
  still registers everything. License state is a dashboard concern.
- **Activation-map semantics.** `_spectra_blocks_blocks` keys are
  unprefixed slugs; the literal values `'no'` (native toggle ability) and
  `'disabled'` (dashboard) both deactivate. Historic admin writes seed
  slug-echo values (`'container' => 'container'`) that mean active — treat
  anything except those two as active, and absent as active. Pro blocks do
  not participate in the map at all.
- **reCAPTCHA secrets are masked.** `get-settings` returns `has_secret_v2`
  / `has_secret_v3` booleans, never the secret values, and no ability will
  echo them. Site keys are public by design and are returned verbatim.
- **`unset` popup type is normal.** Fresh popups report
  `popup_type: "unset"` (the registered default) until the user configures
  the popup — it is not a data error.
- **Trash is never listable.** `list-popups` defaults to
  publish+draft+private, rejects explicit `trash` / `auto-draft` status
  requests as invalid input, and `get-popup` refuses trashed ids with
  `spectra_blocks_popup_not_found`. Check-setup's `popups.by_status.trash`
  bucket is the only window into soft-deleted popups — that is where "my
  popup disappeared" usually lives.
- **The plugin ships its own abilities.** Spectra Blocks registers a native
  `spectra-blocks/*` ability surface (and Pro adds more), including write
  operations. The `novamira/spectra-blocks-*` abilities are the compact
  discovery layer; if a native write ability appears alongside in the tool
  list, prefer reading here first and be aware both operate on the same
  options and CPT rows.
- **Bundled shared libraries are not Spectra.** Options and classes
  prefixed `ast_*`, `bsf_*`, `brainstrom_*`, `zip_ai_*`, `zipwp_*` come
  from Brainstorm Force's shared libraries bundled in the plugin. They are
  out of scope — do not report them as Spectra Blocks state.

## Conventions

- Slugs: `novamira/spectra-blocks-<verb>-<object>`; category
  `spectra-blocks`. Sprint 1 surface: `check-setup`, `list-blocks`,
  `get-settings`, `list-popups`, `get-popup` — all read-only and
  idempotent.
- Error codes are stable: `spectra_blocks_not_active`,
  `spectra_blocks_invalid_input`, `spectra_blocks_popup_not_found`.
  Schema-level violations (unknown input keys, out-of-range values) return
  `ability_invalid_input` before the callback runs.
- List abilities return `{<records>, total, truncated}` (popups add
  `limit` + `offset`); `total` counts all matches, records are capped.
- Large text fields are byte-capped with an explicit `*_truncated` flag and
  a `max_field_bytes` override bounded by a hard 100 000-byte maximum —
  output is always capped.
- Version floors: free `0.0.8` (`NOVAMIRA_PRO_SPECTRA_BLOCKS_MIN_VERSION`),
  Pro `0.0.8` (`NOVAMIRA_PRO_SPECTRA_BLOCKS_PRO_MIN_VERSION`).
