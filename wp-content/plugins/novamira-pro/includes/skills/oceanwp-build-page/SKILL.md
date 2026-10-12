---
name: oceanwp-build-page
description: Activate when working with a WordPress site that uses the OceanWP theme (with or without the Ocean Extra companion plugin) — reading the Customizer setup, listing Customizer panels, fetching theme settings (typography, colors, header, footer, blog, WooCommerce), inspecting per-page layout overrides, discovering OceanWP render hooks, writing Customizer settings, applying typography presets, configuring the header, setting per-page meta overrides, toggling WooCommerce feature flags, mounting Library templates into header/footer/topbar/404 slots, and enabling/disabling Ocean Extra modules (Sticky Header, Side Panel, Cookie Notice, …). Establishes the "Customizer = theme_mod, hooks = render layer, library + modules = Ocean Extra" mental model and the allowlist boundary between agent-curated settings and `novamira/execute-php` overflow.
---

# Working with OceanWP

OceanWP is a highly configurable free WordPress theme with an optional free companion plugin (**Ocean Extra**) and a paid extensions bundle (**Ocean Pro**). Novamira Pro exposes ~15 curated abilities that cover the configuration surface an agent actually needs, plus a documented "use `novamira/execute-php` for these" boundary for everything outside that surface.

## When to use

Use this skill when the user is:

- Configuring OceanWP via the Customizer (typography, colors, header style, blog layout, WooCommerce features, …).
- Overriding per-page layout (sidebar position, breadcrumbs, page header) without touching the theme files.
- Attaching custom HTML or callbacks to OceanWP render hooks (`ocean_before_*` / `ocean_after_*`).
- Working with Ocean Extra's Library (custom header/footer/topbar/404 templates) or its Modules (sticky header, side panel, login popup, cookie notice, …).
- Diagnosing an OceanWP site (versions, third-party integrations OceanWP detects, capability map).

Do NOT use this skill when:

- The active theme is not OceanWP — nothing here applies to a different theme's Customizer, hooks, or companion plugin.
- The configuration question is about WordPress core / Gutenberg / page builders, not OceanWP itself.

## The big idea: Customizer = theme_mod, hooks = render layer, library = Ocean Extra

OceanWP's surface has three layers that map cleanly to different ability clusters:

1. **The Customizer layer (~1000 settings)** lives in `theme_mods_oceanwp` — every setting is a `theme_mod` read via `get_theme_mod()` and written via `set_theme_mod()`. The agent works on this layer through `oceanwp-get-settings` (read) and the Sprint-2 `oceanwp-set-*` abilities (write). The full ~1000 settings are too many for one-ability-per-setting, so Novamira ships an **agent-curated allowlist** (~35 high-leverage settings) and pushes the rest to `novamira/execute-php`.

2. **The render-hook layer (~158 actions + ~314 filters)** is how the theme exposes layout slots and overridable conditions. The agent never owns these directly — they're invoked from `novamira/execute-php` snippets via `add_action()` / `add_filter()`. `oceanwp-list-hooks` returns the curated catalogue of hook names + descriptions to make these snippets discoverable without grepping the theme source.

3. **The Ocean Extra layer (Library, Modules, Demo Import)** lives in the free companion plugin and is only addressable when Ocean Extra is installed and at the minimum supported version. Sprint-4 abilities (`oceanwp-list-library-items`, `oceanwp-apply-library-template`, `oceanwp-list-modules`, `oceanwp-enable-module`, `oceanwp-disable-module`) self-gate on `ocean_extra_available()` and return `oceanwp_requires_ocean_extra` when the gate fails — call `oceanwp-check-setup` first and read `capabilities.library` / `capabilities.modules` to know whether they will work.

Per-page meta overrides (Sprint-3 `oceanwp-get-page-overrides` / `oceanwp-set-page-overrides`) are a fourth, smaller surface: ~10 `ocean_*` postmeta keys the theme reads at render time. They work theme-only — Ocean Extra is not required for the base override keys.

## Why these are abilities (and what to do in plain PHP)

The decision rule: an operation becomes an ability when it has a **distinct semantics, a sharp input schema, and is high-leverage enough that an agent will want it as a tool**. Everything else stays as `novamira/execute-php` and is documented here (see the "Use execute-php for these" section, landing in Sprint 2).

- **Customizer reads with curated coverage** → `oceanwp-get-settings`. The allowlist is ~35 settings. Anything outside the allowlist is an `execute-php` snippet: `get_theme_mod( 'ocean_<key>' )`.
- **Customizer writes** (S2) → `oceanwp-set-customizer-settings` / `oceanwp-set-header-config` / `oceanwp-set-typography-preset`. Same allowlist boundary. Outside the allowlist: `execute-php` with `set_theme_mod( 'ocean_<key>', $value )`.
- **Per-page layout overrides** (S3) → `oceanwp-set-page-overrides`. Per-page meta keys outside the override allowlist: `execute-php` with `update_post_meta( $post_id, 'ocean_<key>', $value )`.
- **Hook discovery** → `oceanwp-list-hooks`. **Hook attachment** stays in `execute-php` (`add_action()` / `add_filter()` with the names from the catalogue).
- **Library template CRUD** (S4) → Sprint-4 abilities. Ad-hoc reads of a single `oceanwp_library` CPT post: `execute-php` with `get_post()`.
- **Module on/off** (S4) → `oceanwp-enable-module` / `oceanwp-disable-module`. Querying which modules exist on Ocean Extra Pro that aren't in the curated catalogue: `execute-php`.

## The abilities at a glance

| Slug | Sprint | What it does | Annotation |
|---|---|---|---|
| `novamira/oceanwp-check-setup` | S1 | Snapshot: theme + Ocean Extra versions, integrations OceanWP detected at boot, capability map for downstream abilities. **Call this first.** | readonly, idempotent |
| `novamira/oceanwp-list-customizer-panels` | S1 | Static catalogue of the ~17 Customizer panels OceanWP exposes (typography, colors, header, blog, WooCommerce, …) with approximate settings counts and per-integration gating. | readonly, idempotent |
| `novamira/oceanwp-get-settings` | S1 | Reads the live value of allowlisted Customizer settings. Filter by `panel` or `settings[]`; typography entries return decoded objects. Outside the allowlist → use `novamira/execute-php`. | readonly, idempotent |
| `novamira/oceanwp-list-hooks` | S1 | Curated catalogue of `ocean_*` action and filter hooks an agent realistically attaches to. Use the returned names with `add_action()` / `add_filter()` from `novamira/execute-php`. | readonly, idempotent |
| `novamira/oceanwp-set-customizer-settings` | S2 | Partial-merge writer for allowlisted Customizer settings (name → value map). Unknown keys are reported in `rejected[]` without aborting the rest. Per-blog lock prevents concurrent read-modify-write conflicts. | write, idempotent |
| `novamira/oceanwp-set-header-config` | S2 | Narrow writer for the header subdomain — short-alias keys (`style`, `height`, `transparent`, `custom_template`) map onto the corresponding `ocean_header_*` theme_mods. | write, idempotent |
| `novamira/oceanwp-set-typography-preset` | S2 | Applies a typography preset (`body`, `headings`, `primary_color`, `links_color`) in one call. Each field optional — partial merge. | write, idempotent |
| `novamira/oceanwp-get-page-overrides` | S3 | Reads the ~13 `ocean_*` postmeta keys for a single post (layout, breadcrumbs colors, post-format media). `is_set` distinguishes explicit override from inherited global default. | readonly, idempotent |
| `novamira/oceanwp-set-page-overrides` | S3 | Writes the same per-page override keys. Allowlist-enforced + pre-validation (no partial state mutation on rejection). Passing `null` as a value DELETES the override and reverts the post to the global default. | write, idempotent |
| `novamira/oceanwp-set-woo-features` | S3 | Toggles the 9 high-leverage WooCommerce-specific theme_mods (off-canvas filter, distraction-free cart/checkout, quick view, floating bar, image hover, ajax-add-to-cart, menu cart style, mobile mini-cart). Gated on `integrations.woocommerce === true`. | write, idempotent |
| `novamira/oceanwp-list-library-items` | S4 | Lists `oceanwp_library` CPT posts (compact: id, title, slug, status, modified). **Requires Ocean Extra.** | readonly, idempotent |
| `novamira/oceanwp-apply-library-template` | S4 | Mounts a Library template id into one of the four custom-template slots (`header`, `topbar`, `footer_widgets`, `custom_404`). Validates the template id is an `oceanwp_library` post; pass `0` to clear the slot. **Requires Ocean Extra.** | write, idempotent |
| `novamira/oceanwp-list-modules` | S4 | Lists the 12 Ocean Extra modules (Sticky Header, Sticky Footer, Side Panel, Popup Login, Footer Callout, Instagram, Cookie Notice, Portfolio, WC Popup, Full Screen, White Label, Elementor Widgets). `enabled` derives from `class_exists` on the module's sentinel class. **Requires Ocean Extra.** | readonly, idempotent |
| `novamira/oceanwp-enable-module` / `oceanwp-disable-module` | S4 | Toggle individual modules by writing into the `oceanwp_modules` option (associative module-slug → bool map Ocean Extra reads on bootstrap). Idempotent. **Requires Ocean Extra.** | write, idempotent |

> All 15 abilities are live. S4 abilities self-gate on `ocean_extra_available()` and return `oceanwp_requires_ocean_extra` when the companion plugin is absent — they're discoverable in the catalogue regardless so the agent can read the schema, but every call short-circuits cleanly without state mutation.

## Quick start

The canonical first call is always:

```text
novamira/oceanwp-check-setup    →    { theme, ocean_extra, integrations, capabilities }
```

Read `theme.min_satisfied`: if `false`, no other ability will produce useful output — surface the version gap to the user before doing anything else.

Read `capabilities.{library,modules}`: if `false`, Sprint-4 abilities will return `oceanwp_requires_ocean_extra` regardless of input. Tell the user to install Ocean Extra instead of trying anyway.

Read `integrations.woocommerce`: if `false`, skip `oceanwp-set-woo-features` (it will return `oceanwp_requires_woocommerce` because the WC theme_mods are no-ops without WC active).

## Workflows

Concrete recipes the agent will run most. Always start with `oceanwp-check-setup` first; it's elided from the recipes below for brevity.

### Apply a brand typography + color preset

User: "set the site to use Inter for body, Playfair Display for headings, and our brand blue #2266ff as the primary."

```text
1. oceanwp-set-typography-preset
     body         = { font-family: "Inter", font-weight: "400", font-size: "16" }
     headings     = { font-family: "Playfair Display", font-weight: "700" }
     primary_color = "#2266ff"
     links_color   = "#0044cc"
2. oceanwp-get-settings { panel: "typography" }    # verify
```

The single call covers four theme_mods atomically (lock-guarded). For per-heading overrides (h1/h2/...) fall through to `oceanwp-set-customizer-settings` on `ocean_h1_typography` / `ocean_h2_typography`.

### Switch header style and tune height

User: "use the minimal header at 90px height."

```text
oceanwp-set-header-config { style: "minimal", height: 90, transparent: false }
```

For `style: "custom"` the agent must first locate a Library template id (`oceanwp-list-library-items` — S4, requires Ocean Extra) and pass it as `custom_template`.

### Inspect the current Customizer state of a single subdomain

User: "what colors are configured?"

```text
oceanwp-get-settings { panel: "colors" }
```

For a focused read of one or two keys, prefer the explicit form: `{ settings: ["ocean_primary_color", "ocean_links_color"] }`.

### Suppress a layout region via a hook

User: "hide the top bar on this site."

```text
1. oceanwp-list-hooks { group: "topbar" }    # confirms `ocean_display_top_bar` exists
2. novamira/execute-php with the snippet:
     add_filter( 'ocean_display_top_bar', '__return_false' );
```

Hook attachment is NOT an ability — there's no semantic-rich CRUD over WP hook registrations. `oceanwp-list-hooks` exists for discoverability; the actual write goes through `novamira/execute-php`.

## Use execute-php for these (no ability)

Everything outside the agent-curated allowlist falls back to `novamira/execute-php`. Snippets are short and obvious because OceanWP keeps its API uniform: every Customizer setting is a theme_mod, every per-page override is post meta on a small set of keys, and the hook layer is plain WordPress.

```php
// Read any OceanWP theme_mod outside the allowlist.
$val = get_theme_mod( 'ocean_custom_css', '' );

// Write any OceanWP theme_mod outside the allowlist. ALWAYS write the
// specific key — never bulk-overwrite the theme_mods_oceanwp option blob.
set_theme_mod( 'ocean_custom_css', '.header{padding-top:1rem;}' );

// Remove (revert to default) — different from setting to ''.
remove_theme_mod( 'ocean_custom_css' );

// Inspect every OceanWP theme_mod at once (debugging).
var_export( get_option( 'theme_mods_oceanwp', [] ) );

// Attach a layout decorator to any ocean_* render hook.
add_action( 'ocean_after_header', static function (): void {
    echo '<div class="promo-banner">10% off until Friday</div>';
} );

// Suppress a layout region using boolean filter helpers.
add_filter( 'ocean_display_breadcrumbs', '__return_false' );

// Set a per-page override that the theme reads at render time. The full
// override allowlist ships in Sprint 3 as `oceanwp-set-page-overrides`.
update_post_meta( $post_id, 'ocean_post_layout', 'full-width' );
```

Two rules for these snippets:

1. **Never `update_option( 'theme_mods_oceanwp', $new )` directly** — that wipes every other theme_mod in the blob. Use `set_theme_mod( $key, $value )` so WP merges into the existing array.
2. **Guard `set_theme_mod` and `update_post_meta` with capability checks if the snippet might run on a context where `novamira_permission_callback` did not** — abilities apply the permission_callback automatically, plain `execute-php` does not.

## Domain model

The four data surfaces the abilities operate on, plus the two read-time meta keys an agent will want to know about.

### theme_mods_oceanwp (single wp_options row)

The whole OceanWP Customizer state is one PHP-serialised assoc array under `theme_mods_oceanwp`. Keys are namespaced `ocean_*` (e.g. `ocean_primary_color`, `ocean_header_style`, `ocean_body_typography`). Typography settings are stored as JSON-encoded objects with the sub-keys `font-family`, `font-weight`, `font-size`, `font-size-tablet`, `font-size-mobile`, `line-height`, `letter-spacing`, `text-transform`, `font-style` — `oceanwp-get-settings` decodes them; `oceanwp-set-*` JSON-encodes them on persist. Every write through Novamira abilities is per-blog mutex-protected (`ow_with_theme_mods_lock`), so concurrent `set-*` calls cannot interleave their read-modify-write.

### Per-post `ocean_*` postmeta (~13 keys)

Per-page overrides the theme reads at render time. The agent-curated allowlist (`ow_page_overrides_allowlist()`):

- `ocean_post_layout` (enum: right-sidebar | left-sidebar | both-sidebars | full-width | full-screen)
- `ocean_both_sidebars_style` (enum: scs-style | css-style | ccs-style)
- `ocean_breadcrumbs_color`, `ocean_breadcrumbs_separator_color`, `ocean_breadcrumbs_links_color`, `ocean_breadcrumbs_links_hover_color` (CSS colors)
- `ocean_post_video_embed`, `ocean_post_self_hosted_media`, `ocean_post_oembed` (post format: video)
- `ocean_quote_format`, `ocean_quote_format_link` (post format: quote)
- `ocean_link_format`, `ocean_link_format_target` (post format: link)

Writes call `clean_post_cache` after persistence (postmeta writes don't fire `save_post`, so the post-object cache needs an explicit flush).

### oceanwp_modules (Ocean Extra option)

A single `wp_options` row that Ocean Extra reads on bootstrap to decide which modules to load: `{ sticky_header: true, side_panel: false, ... }`. The 12 supported modules are catalogued in `ow_ocean_extra_modules()` with their sentinel classes. Writes go through `ow_with_oceanwp_modules_lock` (separate key from theme_mods, so a module toggle doesn't block a typography write).

### oceanwp_library CPT (Ocean Extra)

A standard CPT (post_type=`oceanwp_library`) that holds reusable header / footer / topbar / 404 template content. Mounting one into a slot writes its post id into the matching `ocean_*_template` theme_mod (`ocean_header_template`, `ocean_topbar_template`, `ocean_footer_widgets_replace`, `ocean_custom_404_template`). The mount itself requires the template post be in `publish` state — `apply-library-template` enforces this with the dedicated `oceanwp_template_not_published` error code.

### Render-time integration constants

OceanWP defines a set of `OCEANWP_*_ACTIVE` constants on every request (`OCEANWP_STICKY_HEADER_ACTIVE`, `OCEANWP_ECOMM_ACTIVE`, `OCEANWP_ELEMENTOR_ACTIVE`, `OCEANWP_WOOCOMMERCE_ACTIVE`, …). `check-setup.integrations` mirrors them. Agents should read this block once and branch off `integrations.woocommerce` before invoking `set-woo-features`.

## Gotchas

Common gotchas on this surface. Keep them in mind whenever you write to OceanWP settings.

- **The Customizer layer is one big blob.** A naive `update_option('theme_mods_oceanwp', $new)` from an `execute-php` snippet WIPES every other setting. Always use `set_theme_mod($key, $value)` — it merges.
- **Typography is JSON-encoded inside the option.** `ocean_body_typography` is stored as `wp_json_encode([…])`. Reading via plain `get_theme_mod` returns a JSON string, not an array — use `oceanwp-get-settings` (or call `json_decode` yourself in execute-php).
- **Postmeta writes don't invalidate the post-object cache.** Every page-overrides write calls `clean_post_cache($post_id)` for this reason. Skipping the flush gives the next read stale data inside the same request.
- **Per-blog locks are real locks.** The mutex around `theme_mods_oceanwp` uses object-cache (`wp_cache_add`) with an `add_option` INSERT-IGNORE fallback. The release path is CAS-guarded via `hash_equals` on a per-acquisition owner token — a stale-lock cleanup will not steal the next holder's lock. Lock TTL is `stale_after + 30s`.
- **Ocean Extra capability mismatch between contexts.** `class_exists('Ocean_Extra')` can be `true` in the HTTP request context (autoloader) but `false` at wp-cli. ALWAYS branch off `check-setup.capabilities.{library,modules}` rather than the local `is-active` probe.
- **WC features no-op without WooCommerce.** Writing `ocean_woo_off_canvas_filter = true` when WooCommerce isn't active persists the theme_mod but nothing on the front-end renders. `set-woo-features` short-circuits with the dedicated `oceanwp_requires_woocommerce` code.
- **Header style="custom" needs a template id.** Setting `ocean_header_style = 'custom'` without a `ocean_custom_header_template` value leaves the site rendering an empty header. `set-header-config` enforces the cross-field requirement; doing the same write through `execute-php` skips that guard.
- **`set-page-overrides` null = delete.** Passing `null` as a value DELETES the postmeta key and reverts the post to the global default. Passing an empty string `""` sets the override to `""` — different observable state.
- **Wrappers speak aliases in BOTH directions.** `set-header-config`, `set-typography-preset`, `set-woo-features` all accept short alias keys (`style`, `body`, `quick_view`) AND return responses keyed by the same aliases. Internal `ocean_*` theme_mod names never appear in agent-visible output. An `execute-php` snippet that writes `ocean_*` directly bypasses the alias contract — fine for one-offs, surprising for chains that pipe wrapper outputs into other wrapper inputs.
- **Long strings get truncated on read.** `get-settings` and `get-page-overrides` cap string values at `OW_FIELD_BYTES_DEFAULT` (4 KiB) using UTF-8-codepoint-aware `mb_strcut`. The ellipsis marker `…` is appended on truncation; the persisted value is full-fidelity. To recover the full value, use `execute-php` with the raw `get_theme_mod` / `get_post_meta`.
- **Hook attachment is execute-php, not an ability.** `oceanwp-list-hooks` exists for discoverability; the actual `add_action`/`add_filter` calls go through `novamira/execute-php`. There's no `oceanwp-attach-hook` ability and the surface intentionally doesn't grow one — WordPress has no rich CRUD over hook registrations.

## Conventions

- **Slugs** follow the Novamira ability-naming convention `novamira/oceanwp-<verb>-<object>`. No `manage-*` enums — every operation is its own ability.
- **Error codes** are mutually exclusive: `oceanwp_not_active` (theme missing), `oceanwp_requires_ocean_extra` (companion missing), `oceanwp_requires_woocommerce` (WC missing), `oceanwp_invalid_input` (input shape wrong), `oceanwp_unknown_module` (slug not in the module allowlist), `oceanwp_invalid_post` (post id rejects via `ow_post_or_error`), `oceanwp_template_not_published` (library template not in publish state), `oceanwp_lock_busy` (mutex contention, 503), `oceanwp_persist_failed` (`update_option` returned false, 500). Agents can dispatch on these without reading the message. Customizer settings outside the agent allowlist are not rejected with a top-level error — `set-customizer-settings` reports them per-item in its `rejected[]` array so the rest of the batch still writes.
- **Annotations**: every read ability is `readonly: true, destructive: false, idempotent: true`. Every write ability uses `readonly: false, destructive: false, idempotent: true` — including delete-like operations (`set-page-overrides` with `null` value, `apply-library-template` with `template_id: 0`), because the delete is keyed and re-running it on an already-cleared slot is a no-op.
- **Compact list, full get.** `list-library-items` returns 5-field summaries (id, title, slug, status, modified). For the full library item body call `execute-php` with `get_post($id)`.
- **Aliases for wrappers, raw names for the generic writer.** `set-customizer-settings` accepts the full `ocean_*` namespace (allowlist-enforced). `set-header-config` / `set-typography-preset` / `set-woo-features` accept short aliases; their responses are translated back to aliases via `ow_translate_result_to_aliases`.
- **Per-field byte cap on reads (4 KiB).** Applies to all string values returned by `get-settings` and `get-page-overrides`. Truncation is signalled by a trailing `…` codepoint. UTF-8-safe (`mb_strcut`).

## Capability map cheat-sheet

After `check-setup`, branch on `capabilities`:

```text
capabilities.customizer_writes   → theme active + min satisfied. Run S2 abilities.
capabilities.page_overrides      → theme active + min satisfied. Run S3 page-override abilities.
capabilities.woo_features        → above + integrations.woocommerce === true. Run set-woo-features.
capabilities.library             → above + ocean_extra active + min satisfied. Run library S4 abilities.
capabilities.modules             → above + ocean_extra active + min satisfied. Run module S4 abilities.
```

Treat any `false` as a hard "tell the user, then stop" — calling the ability anyway will return the matching `requires_*` / `not_active` error and waste a round-trip.
