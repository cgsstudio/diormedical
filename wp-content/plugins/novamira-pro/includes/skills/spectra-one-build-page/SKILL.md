---
name: spectra-one-build-page
description: Activate when working with a WordPress site running the Spectra One theme (Brainstorm Force, block-based / FSE). Applies to reading and writing the theme's design tokens through the WordPress Global Styles overlay (theme.json + wp_global_styles post), applying bundled style variations, discovering FSE templates / template parts / block patterns, and — when the companion Spectra (UAGB) plugin is active — introspecting the Spectra block library. Do NOT activate for other block themes (GeneratePress, Kadence, OceanWP) or for classic Customizer-based themes.
---

# Working with Spectra One

Spectra One is a block-based / Full Site Editing WordPress theme by Brainstorm Force. It ships its own WordPress Abilities API surface (`spectra-one/*`) that requires WP 6.9+. Novamira Pro re-wraps that surface under the `novamira/spectra-one-*` namespace with the project's conventions (progressive-disclosure output, WP-native input aliases, stable `WP_Error` codes), plus a handful of gap-fill abilities the theme does not provide.

## When to use

Use this skill when:

- The site's active theme is `spectra-one` (verify with `novamira/spectra-one-check-setup`).
- You need to read or write design tokens the theme exposes through `theme.json` and the WordPress `wp_global_styles` overlay (palette, typography, spacing, layout).
- You need to apply a bundled style variation (aquamarine, dark, easter-green, mango, pink, rain-forest, sweet-corn, ultra-marine).
- You need to enumerate FSE templates, template parts, or bundled block patterns.
- The site also runs the Spectra (UAGB) plugin and you need to introspect its block library.

Do NOT use this skill when:

- Another block theme is active (GeneratePress, Kadence, Twenty Twenty-Four, ...). Their skills live under `generatepress-build-page`, `kadence-build-page`, and so on.
- A classic Customizer-based theme is active (Astra, OceanWP, ...). OceanWP has its own `oceanwp-build-page` skill.
- The site is running Spectra One below 1.2.2. Discovery abilities still work, but every `novamira/spectra-one-*` write path returns `spectra_one_not_active` — surface the version gap to the user first.

## The big idea

Two layers work together:

1. **`theme.json`** ships with the theme and declares the *defaults* — palette slugs, typography scales, spacing steps, layout widths, block-style declarations.
2. **`wp_global_styles`** is a WordPress custom post type; the user's saved overrides live in the `post_content` JSON of a single post per theme. Live output = theme.json defaults overlaid with the user post.

Every write in this specialization mutates the `wp_global_styles` post, then invalidates the `theme_json` object cache so the next request sees the new value. The theme.json file is never touched — that would be a code change, not a settings change.

Spectra One additionally ships:

- 8 bundled **style variations** (`styles/*.json`) that are pre-configured overrides you can *apply* to `wp_global_styles` wholesale.
- 85 bundled **block patterns** (`patterns/*.php`) that a user can insert from the block-inserter.
- 9 **FSE templates** (`templates/*.html`) + 3 **template parts** (`parts/*.html`) that drive the front-end render.

## Domain model

- **Palette** — 14 named color slugs: `primary`, `secondary`, `heading`, `body`, `background`, `tertiary`, `quaternary`, `surface`, `foreground`, `outline`, `neutral`, `transparent`, `currentColor`, `inherit`. Slots are stable across 1.2.x; a future minor that adds slugs must be re-audited before Novamira surfaces them as writeable.
- **Typography** — 1 bundled family (Inter) with 6 weights, 8 fluid font-size preset (X-Small → XXXX-Large, using `clamp()`), 5 line-height presets, 9 font-weight presets, 8 border-radius presets.
- **Style variation** — a JSON file whose shape mirrors `wp_global_styles.post_content`: `{title, settings: {color, typography, ...}, styles: {...}}`. Applying one = wholesale replace of the `wp_global_styles` payload.
- **Template / template part** — an HTML file with Gutenberg block markup. Reads / writes of the block tree flow through the base Novamira `read-file` + block-manipulation abilities, not through a Spectra-One-specific write.
- **Pattern** — a PHP file with header metadata (`Title`, `Slug`, `Categories`, `Keywords`) followed by HTML. WordPress auto-registers them from `patterns/`.
- **Hook** — a `swt_*` filter the theme exposes. 11 filter hooks, zero actions in 1.2.x.

## Sprint 1 + 2 abilities (available today)

Every ability below is `readonly: true`. Every non-`check-setup` ability returns `WP_Error('spectra_one_not_active')` when the theme is missing / under-min — so `check-setup` is the only ability an agent can *always* invoke.

**Sprint 1 — discovery**

- **`novamira/spectra-one-check-setup`** — Diagnostic snapshot: theme active / version / min_satisfied, companion Spectra plugin state, companion Custom Fonts plugin state, WP 6.9+ Abilities API availability, current-user capability. Call once at the start of any Spectra-One-touching session.
- **`novamira/spectra-one-get-theme-info`** — Portable theme metadata + counts of bundled assets (style variations, patterns, templates, template parts). Use to size the surface before deep-dive `list-*` calls.
- **`novamira/spectra-one-list-hooks`** — Curated catalogue of the 11 custom `swt_*` filter hooks. Optional `type` narrows to `action` / `filter` / `all`.
- **`novamira/spectra-one-list-style-variations`** — Compact list of the 8 bundled variations: slug + title + file + palette_count + up-to-3 palette_preview hexes. Full JSON is deliberately *not* echoed — load the returned `file` path via an `execute-php` snippet when you need the full payload.
- **`novamira/spectra-one-list-templates`** — Compact list of FSE templates + template parts: slug + type + area + file. Optional `type` narrows to `templates` / `parts` / `all`.

**Sprint 2 — settings reads + patterns**

- **`novamira/spectra-one-get-color-palette`** — Active palette (14 named slugs) with theme-vs-user origin per slot, plus bundled gradients + duotones. `source` reports `user` / `theme` / `mixed` for one-glance overlay state. This is the writable allowlist for Sprint 3 `set-color-palette`.
- **`novamira/spectra-one-get-typography-settings`** — Body + headings settings (font_family / font_weight / line_height / responsive font_size) plus the theme.json preset allowlists (5 lists: font families, font sizes, font weights, line heights, border radii). Presets are the writable allowlist for Sprint 3 `set-typography`.
- **`novamira/spectra-one-get-theme-settings`** — Options bag (scroll_top toggle), layout widths (contentSize / wideSize), spacing scale, block-style names, custom-templates inventory. Every slug returned is on the Sprint 3 `set-theme-settings` allowlist.
- **`novamira/spectra-one-list-patterns`** — Paginated catalogue of the 85 bundled block patterns (default limit 25, max 100). Compact rows (slug + title + categories + keywords + file path). Content is NOT echoed — use `novamira/read-file` or an execute-php snippet with the returned `file` path to load a single pattern's HTML.

**Sprint 3 — write path**

Every write ability is `destructive: true` (except `add-font-family`) and is protected by a lock + a theme_json / options cache flush so the next request renders the new state. Non-`add-font-family` writes require a saved `wp_global_styles` user post (any Site Editor save creates it once); calling before that returns `spectra_one_no_global_styles`.

- **`novamira/spectra-one-set-color-palette`** — Replace one or more palette slots by hex. Progressive-disclosure output: `updated_slots` returns ONLY the slots that changed (call `get-color-palette` for the full snapshot). Idempotent — re-passing the same colors reports `updated: 0`.
- **`novamira/spectra-one-set-typography`** — Update body / headings settings and the responsive body font size. Every value is validated against the theme.json preset allowlist reported by `get-typography-settings`; passing an unknown slug returns `spectra_one_unknown_setting`. On a fresh install where the theme.json presets have not yet loaded, returns `spectra_one_presets_unavailable` — reload the Site Editor once and retry.
- **`novamira/spectra-one-set-theme-settings`** — Toggle the global options (`scroll_top` today, more flags as the theme exposes them). `updated_keys` reports exactly what changed.
- **`novamira/spectra-one-apply-style-variation`** — Apply a bundled style variation (`aquamarine`, `dark`, `easter-green`, `mango`, `pink`, `rain-forest`, `sweet-corn`, `ultra-marine`) to the overlay wholesale. Preview safely with `dry_run: true` before the actual write — the response reports `palette_count`, `has_typography`, `has_styles` as change indicators. Call `export-design` (Sprint 5) first if you want a rollback point.
- **`novamira/spectra-one-add-font-family`** — Install a Google Font family into the WordPress Font Library. Idempotent (`already_installed: true` on repeat), returns the slug you then pass to `set-typography.body.font_family`. Delegates to the theme-native `spectra-one/install-font` for the SSRF-safe fetch + WOFF2 verification.

**Sprint 4 — templates + per-post display**

Two delegating write abilities that lock around the theme-native update-navigation / update-template-part (which do NOT lock on their own) + a read / list / set trio for the per-post display overrides.

- **`novamira/spectra-one-edit-navigation`** — Sets or appends items to the primary navigation menu. `items` accepts `{ label, url, id?, children? }`; children render as wp:navigation-submenu dropdowns. `append: true` merges with the existing menu deduplicating by id / url; `append: false` (default) replaces wholesale. Wires the header + footer template parts to the resulting wp_navigation post so one call sets the menu everywhere it renders. Delegates to `spectra-one/update-navigation` under a shared lock.
- **`novamira/spectra-one-edit-template-part`** — Switches the active header or footer design by activating a different pattern on the corresponding FSE template part. Discover valid slugs with `list-patterns` filtered by category `spectra-one-headers` or `spectra-one-footers`. Optional `use_site_title` swaps the logo image for a wp:site-title block. Delegates to `spectra-one/update-template-part` under a per-area lock.
- **`novamira/spectra-one-get-post-display-settings`** — Reads the 5 per-post display overrides (hide_header / hide_footer / hide_title / sticky_header / transparent_header) plus post_title / post_type for the post identified by `post_id` (or the WP-native alias `id`).
- **`novamira/spectra-one-list-posts-with-display-overrides`** — Enumerates posts that carry at least one override, paginated. Compact rows: post_id, post_title, post_type, `overrides` (the list of override keys the row has set). Full values are NOT echoed — call `get-post-display-settings` for a specific post.
- **`novamira/spectra-one-set-post-display-settings`** — Replaces the 5 overrides wholesale on a specific post (all 5 boolean fields are required). Passing `false` on a slot removes the postmeta row so the theme defaults render again. Always confirm the subject with the user by echoing `post_title` from `get-post-display-settings` first. The write is lock-protected + cache-flushed. Additional contextual capability check on `edit_post`.

**Sprint 5 — design snapshot + Spectra plugin bridge**

Rollback pattern (export → destructive-write → import) + a companion-plugin discovery ability. `list-spectra-blocks` is only registered when the Spectra (UAGB) plugin is available; the agent's tool list on installs without the plugin will not show it at all.

- **`novamira/spectra-one-export-design`** — Returns the active design state as a portable JSON snapshot: `schema_version: 1`, ISO 8601 export timestamp, theme identity block, the full `wp_global_styles` user overlay + the full `swt_theme_options` bag, and a compact `stats` counter block. Take this BEFORE any destructive write (set-color-palette, set-typography, set-theme-settings, apply-style-variation, apply-design) so a bad outcome can be rolled back. Typical payload size is 10–50 KiB — stash the snapshot rather than re-fetching it in a hot path. Empty overlay / empty options serialise as `{}` (not `[]`) so the snapshot survives the strict-object schema on the way back through `apply-design`.
- **`novamira/spectra-one-apply-design`** — Applies a snapshot produced by `export-design` to the current install (replaces `wp_global_styles` + `swt_theme_options` wholesale). Snapshot data is filtered through the same allowlists the per-slot set-* writers enforce — unknown top-level `global_styles` keys and unknown `theme_options` keys are dropped and reported in `filtered.global_styles` / `filtered.theme_options`, so a hand-edited snapshot can never smuggle in surfaces Novamira does not maintain. `dry_run: true` runs schema validation + theme-version comparison + allowlist filtering without persisting so the caller can preview what will land AND what will be dropped. `version_match` in the output is informational (apply proceeds regardless). Refuses snapshots from a different theme (`slug !== 'spectra-one'`) or an unsupported `schema_version`. Serialises under the shared design lock so apply-design and per-slot writes cannot interleave.
- **`novamira/spectra-one-list-spectra-blocks`** — Enumerates the Spectra (UAGB) block library over the WP_Block_Type_Registry (`uagb/*` prefix). Compact rows: name, title, category. Paginated (default limit 100, max 200). Per-block attribute schemas live on the base Novamira gutenberg / block-schema abilities. Registered ONLY when the Spectra plugin is active — agents on installs without UAGB will not see this ability in the catalogue at all.

## Simple operations via `novamira/execute-php`

For one-off lookups that don't warrant a dedicated ability, use `novamira/execute-php`. Every snippet below is safe on any install — it early-returns when the theme is not booted.

**Read the raw `theme.json` merged content** (defaults + user overlay, from the WP theme-json data layer):

```php
return function_exists('wp_get_global_styles')
    ? wp_get_global_styles([], ['origin' => 'all'])
    : null;
```

**Read a single Customizer / theme-mod value** (if the site has any residual mods):

```php
return get_theme_mod('background_color', '#ffffff');
```

**Check whether the Spectra companion plugin is loaded**:

```php
return function_exists('Swt\\Utilities\\is_spectra_plugin')
    ? \Swt\Utilities\is_spectra_plugin()
    : false;
```

**List the font families declared in `theme.json`** (short-cut to avoid loading the full theme.json):

```php
return function_exists('Swt\\get_theme_json')
    ? array_column(\Swt\get_theme_json()['settings']['typography']['fontFamilies'] ?? [], 'name')
    : [];
```

**Load an FSE template's block markup by slug** (a follow-up after `list-templates` surfaces the `file` field):

```php
$path = get_template_directory() . '/templates/single.html';
return is_readable($path) ? file_get_contents($path) : null;
```

## Native theme abilities

The theme itself registers `spectra-one/*` abilities (WP 6.9+). Novamira re-wraps every one under `novamira/spectra-one-*` with tighter output schemas, WP-native input aliases, and stable `WP_Error` codes — prefer the Novamira variants for coherence with the rest of the agent's tool surface. If a WordPress core release surfaces a new theme-native ability before Novamira wraps it, fall back to the raw `spectra-one/<ability>` slug and note the drift in your response.

## Gotchas

- **WP 6.9+ requirement** — the theme-native abilities need WP 6.9. The Novamira wrappers work on any WP that runs the base plugin, but `check-setup.abilities_api.available` reports the theme-native surface's state so you can decide whether to fall back.
- **`min_satisfied` gates every write** — Sprint 3+ write abilities return `spectra_one_not_active` when the theme is missing / under-min. `check-setup` is the only one that keeps working; use it to surface the gap.
- **Spectra plugin is optional** — most abilities work without UAGB. Only `list-spectra-blocks` (Sprint 5) gates on it. `check-setup.plugin.min_satisfied` tells you if the bridge is safe to call.
- **`is_stylesheet` matters** — `SWT_VER` stays defined after any code path pulls the theme's bootstrap, even after a mid-request `switch_theme()`. Trust the `is_stylesheet` flag rather than `SWT_VER` alone when deciding whether the theme is *really* in charge of the render.
- **Progressive disclosure is on `list-*`** — none of the `list-*` abilities echo full content. Use the `file` path they return to load the HTML / JSON via the base Novamira `read-file` ability or an `execute-php` snippet.
- **Two write stores feed the same cascade** — `set-color-palette` and `apply-style-variation` write into `wp_global_styles` (the WordPress user overlay). `set-typography` and `set-theme-settings` write into `swt_theme_options` (the theme's Customizer bag). Both stores contribute to the block-editor render cascade, so a single logical change (e.g. "make body Inter 16px") can be expressed as a write in either store — but the resolved value at render time is decided by the WP-core → theme overlay order, not by which ability was called last. All four writers now serialise through the same `global_styles → swt_theme_options` lock pair (`so_with_design_lock`), so concurrent calls can't interleave; but if you write the same conceptual field through both surfaces (e.g. body typography via `set-typography` AND via `apply-style-variation`), the second write does NOT overwrite the first — both persist and the cascade picks one. Prefer one surface per logical field; use `apply-design` to reset both stores wholesale when you want a clean slate.
- **`apply-design` reset scope** — `apply-design` (formerly `import-design`) wholesale-replaces BOTH `wp_global_styles` AND `swt_theme_options`. This is the ONLY way to reset the cascade without leaving stray keys from a previous session in one of the two stores.

## Conventions

- Ability slugs follow `novamira/spectra-one-<verb>-<object>`; the same file naming holds under `includes/abilities/spectra-one/`.
- Output shapes always declare `additionalProperties: false` recursively — agents can rely on the field set being exactly what the schema says.
- Every non-`check-setup` ability starts with a `so_not_active()` guard so failure modes stay stable across the entire surface.
- WP-native input aliases (`post_title`, `post_status`, ...) appear on `set-post-display-settings` and `apply-design`; earlier abilities take no such aliases because they don't wrap WP-core writes.
