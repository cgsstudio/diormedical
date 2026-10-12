---
name: astra-integration
description: Configure a WordPress site running the Astra theme (with optional Astra Pro addon) — read/write global theme settings, color palette, typography, Header/Footer Builder layouts, per-post overrides, Advanced Hooks, Advanced Headers, and WooCommerce store styling. Use when the user asks to customize Astra settings, swap the palette, edit the site header or footer, apply per-post layout overrides, or restyle the WooCommerce shop on an Astra site.
---

## When to use

- The site is running the Astra theme (active stylesheet or parent template).
- The user asks to change theme appearance: colors, typography, layout width, sidebar, header/footer.
- The user asks to configure HFB (Header/Footer Builder) — adding or rearranging elements in the header or footer grid.
- The user asks to apply per-post display overrides: hide header, set boxed layout, disable sidebar on a specific page.
- The user asks to create or edit an **Advanced Hook** (custom code injected at a theme hook position) or an **Advanced Header** (page hero banner) — requires Astra Pro addon with the corresponding module active.
- The user asks to restyle the WooCommerce shop / product / cart / checkout appearance on an Astra site — requires Astra Pro addon with the `woocommerce` module active.

## When NOT to use

- The user is authoring **Gutenberg post content** (blocks, paragraphs, images) — that is WordPress core content, not Astra styling. Use the base Novamira post-content abilities instead of any Astra ability here.
- The user wants to **install or update** Astra or Astra Pro — use plugin management abilities instead.
- The site is running a **different theme** (GeneratePress, Kadence, etc.) — use the corresponding theme integration skill.
- The user wants to create custom post types, fields, or taxonomies — those are field-plugin concerns (ACF, Pods, etc.).

## First call: `astra-check-setup`

Always start with `novamira/astra-check-setup`. Its response tells you exactly what is available:

```
theme.active          — must be true before any other astra-* ability makes sense
theme.min_satisfied   — must be true; if false, surface the version gap to the user
addon.active          — gate all Astra Pro Addon abilities on this flag
modules.<slug>        — gate module-specific abilities (Advanced Hooks, HFB Pro, WC styling, …)
entities              — whether the CPT post types are registered (Advanced Hooks, Advanced Headers)
integrations          — whether WooCommerce / EDD / LearnDash / LifterLMS are present
storage.settings_size_bytes — if large (> 50 kB), warn the user before pulling the full bag
```

Do NOT call any other `astra-*` ability if `theme.active=false`.

## Domain model

Astra's configuration surface has four tiers in the **free theme**:

1. **`astra-settings` option bag** — the global settings store. Colors (single values like `theme-color`, `link-color`), typography, layout decisions, header/footer config, and WooCommerce styling live here as a flat key-value map. Read via `astra_get_option(key)`, written as a single merged option row. Our lock (`astra_with_settings_lock`) prevents race conditions on concurrent writes. Palette *rosters* (the 9-color per-palette slots) are stored in a SEPARATE option row — see the palette bullet below.

2. **Header/Footer Builder (HFB) grid** — a sub-set of `astra-settings` keys that encode which element slugs (logo, menu-1, search, cart, …) are placed in which grid cells (above_left, primary_center, below_right, …) for desktop and mobile. Read via `astra-get-header-layout` / `astra-get-footer-layout`; write via `astra-set-header-layout` / `astra-set-footer-layout` (full PUT) or `astra-reorder-header-row` (per-row reshuffle).

3. **Per-post overrides** — individual posts/pages can override container layout, sidebar, header display, title visibility, and background via post meta (`ast-site-content-layout`, `site-sidebar-layout`, etc.). Read via `astra-get-post-settings`; write via `astra-set-post-settings` (individual overrides) or `astra-set-post-background`; wipe with `astra-clear-post-settings`. `astra-get-layout` resolves the effective per-post value with fallback to the global default.

4. **`astra-color-palettes` option row** — the palette rosters (each registered palette holds 9 color slots and a display name) live in a SEPARATE `wp_options` row, NOT in `astra-settings`. Read via `astra-get-color-palette` (optionally filtered by `palette_id`); write the 9-slot roster via `astra-set-color-palette` (full PUT; the palette's active-id is separately tracked). Its own lock (`astra_with_palette_lock`) keeps concurrent palette rewrites serialised.

When **Astra Pro Addon** is active, three additional tiers unlock:

5. **Advanced Hooks CPT** (`astra-advanced-hook`, `advanced-hooks` module) — custom code / HTML / shortcode injected at named theme hook positions. Full CRUD via `astra-list-hooks`, `astra-get-hook`, `astra-create-hook`, `astra-edit-hook`, `astra-delete-hook`.

6. **Advanced Headers CPT** (`astra_adv_header`, `advanced-headers` module) — per-post hero banners with layout / design payload. Full CRUD via `astra-list-page-headers`, `astra-get-page-header`, `astra-create-page-header`, `astra-edit-page-header`, `astra-delete-page-header`.

7. **WooCommerce styling** (`woocommerce` module + WC plugin) — ~90 `astra-settings` keys prefixed `shop-*`, `single-product-*`, `cart-*`, `checkout-*`. Read via `astra-get-woocommerce-settings`; write via `astra-edit-woocommerce-settings` (whitelist merge patch).

## Workflows

### Discover current setup

```
1. novamira/astra-check-setup
2. novamira/astra-get-settings   — keys: [layout, color, typography keys you need]
3. novamira/astra-get-layout     — post_id: <page id> or 0
```

Branch on check-setup results:
- `addon.active=false` → module and CPT abilities not available; inform user
- `modules.advanced-hooks=false` → Advanced Hooks abilities unavailable; suggest enabling the module via `astra-enable-module slug=advanced-hooks`
- `integrations.woocommerce=false` → WC styling abilities not available

### Change global settings (colors, typography, layout)

Merge patch on the shared `astra-settings` bag. Only the keys you send are written; siblings preserved. Every write holds the settings lock + flushes the CSS cache.

```
1. astra-get-settings                                → snapshot current values you plan to touch
2. astra-edit-settings settings={key: value, ...}     → merge patch (~200 whitelisted keys across
                                                       layout/color/typography/header/footer/blog/misc)
   → response reports written[], unchanged[], unknown_keys[], type_mismatches[]
3. astra-get-settings                                 → verify the write
```

Palette swap:
```
astra-set-color-palette colors=['#hex', '#hex', ..., '#hex']   # exactly 9 items, required
                        palette_id=palette_1                    # optional; defaults to active
                        name='My palette'                       # optional rename
   → full PUT on the 9-slot palette. Idempotent.
```

Typography merge patch (scoped to typography allowlist):
```
astra-set-typography typography={body-font-family: '...', body-font-size: '...', ...}
                     strict=false                              # optional; true = 400 on any refused key
   → response reports written{}, unknown_keys[], type_mismatch[]
```

Toggle an Astra Pro module (addon required):
```
astra-check-setup                                     → confirm addon.active + modules[<slug>]
astra-enable-module slug=sticky-header                → activate a module
astra-disable-module slug=advanced-hooks confirm=true → disable; `confirm=true` required when
                                                        the module owns CPT posts (orphan guard)
```

### Reshape the HFB header or footer

The HFB grid is a set of cells (`above_left`, `primary_center`, `below_right`, ...) each holding an ordered list of element slugs (`logo`, `menu-1`, `search`, `cart`, ...).

```
1. astra-get-header-layout                            → current grid, desktop + mobile
2. astra-set-header-layout desktop={above: {above_left: [...], above_center: [...], ...},
                                    primary: {primary_left: [...], ...},
                                    below: {...}, popup: {...}}
                           mobile={above: {...}, primary: {...}, below: {...}, popup: {...}}
                           strict=false
                                                      → full PUT on the grid; both `desktop` and
                                                        `mobile` are optional (omit one to leave it
                                                        untouched). Unknown cell keys / element
                                                        slugs are silently dropped unless strict=true.
```

Same shape for footer via `astra-get-footer-layout` / `astra-set-footer-layout` (`desktop` / `mobile` top-level, not `layout={}`).

Move a single element between cells within the same header row (surgical, atomic under advisory lock):
```
astra-reorder-header-row variant=desktop           # or 'mobile'; defaults to desktop
                         row=primary               # 'above' | 'primary' | 'below'
                         element=search            # slug to move
                         from_cell=primary_left    # current cell
                         to_cell=primary_right     # destination cell (may equal from_cell)
                         to_index=-1               # optional; -1 = append, 0 = first
   → idempotent: if element is no longer in from_cell, returns moved=false without writing.
     For bulk grid changes, use astra-set-header-layout instead.
```

### Apply per-post display overrides

Individual posts/pages can override the global content layout, sidebar, header/footer visibility, title/breadcrumbs, and background.

```
1. astra-get-post-settings post_id=<id>                → current per-post overrides
2. astra-set-post-settings post_id=<id> settings={site-content-layout: 'boxed-container',
                                                     site-sidebar: 'no-sidebar',
                                                     ast-hfb-above-header-display: 'disabled', ...}
                                                     → writes the postmeta rows and busts post cache
3. astra-set-post-background post_id=<id>
                              target=page                                   # or 'content'; REQUIRED
                              desktop={background-color: '#hex',
                                       background-type: 'color'}            # or 'image' / 'gradient'
                              tablet={...} mobile={...}                     # optional viewports
                              merge=true                                    # partial viewport merge (default)
                                                     → separate ability because it's a responsive
                                                       compound field (per-viewport). Field allowlist:
                                                       background-color, background-image,
                                                       background-repeat, background-position,
                                                       background-size, background-attachment,
                                                       background-type, background-media,
                                                       overlay-type, overlay-color, overlay-opacity,
                                                       overlay-gradient.
```

Wipe every per-post override so the page falls back to the theme defaults:
```
astra-clear-post-settings post_id=<id>                # omit `keys` to clear ALL 20 meta keys
```
Pass `keys=[<subset>]` to clear only a chosen list of the per-post meta keys (an empty array is refused — omit the field to clear all).

Verify the effective (resolved) value for a given post:
```
astra-get-layout post_id=<id>
```

### Create / edit an Advanced Hook (addon-gated)

Advanced Hooks inject custom HTML / shortcode content at named theme hook positions (`astra_header_before`, `astra_footer_after`, custom locations from other plugins, etc.).

```
1. astra-check-setup                                   → confirm addon.active + modules.advanced-hooks=true
2. astra-list-hooks status=publish                     → compact rows: {id, title, status, layout, action, priority}
3. astra-get-hook id=<id>                              → full payload including `content` (HTML/shortcode blob)
4. astra-create-hook title='...'
                    layout=hooks                            # REQUIRED enum: header|footer|404-page|hooks|content|template
                    action='astra_header_before'            # REQUIRED when layout=hooks (the WP action name)
                    priority=10
                    content='<div>...</div>'
                    location={rule: 'basic-singulars', ...} # targeting bag — string-keyed object
                    users={rule: 'logged-in'}               # user role bag — string-keyed object
   → creates the astra-advanced-hook CPT row, wires the targeting + user rule postmeta, returns the new id
5. astra-edit-hook id=<id> content='<updated>...</updated>' priority=20
   → merge patch: only supplied fields changed
6. astra-delete-hook id=<id>                          # trashes the CPT row (recoverable from Trash)
   astra-delete-hook id=<id> force=true                # permanently deletes it
```

Content is HTML / shortcode only. NO raw PHP execution surface (see Gotchas).

### Create / edit an Advanced Header / page hero (addon-gated)

Advanced Headers replace the theme's default page title area with a custom hero (background image, overlay, heading, breadcrumbs) applied to a selected post scope.

```
1. astra-check-setup                                   → confirm addon.active + modules.advanced-headers=true
2. astra-list-page-headers                             → compact rows
3. astra-get-page-header id=<id>                       → full layout + design + display-rules payload
4. astra-create-page-header title='...'
                              layout='advanced-headers-layout-2'   # REQUIRED enum: advanced-headers-layout-1|advanced-headers-layout-2|disable
                              design={colors: {...}, background-color: '...', bg-image: '...', ...}
                              location={rule: 'basic-singulars', specific: [<post_ids>]}   # targeting bag — string-keyed object
                              users={rule: 'logged-in'}
                              status=publish                        # optional; defaults to draft
   → creates astra_adv_header CPT row + companion postmeta
5. astra-edit-page-header id=<id> design={background: {type: 'image', image_id: <att_id>}}
   → merge patch
6. astra-delete-page-header id=<id>                   # trashes the CPT row (recoverable from Trash)
   astra-delete-page-header id=<id> force=true         # permanently deletes it
```

### Restyle the WooCommerce shop / cart / checkout (addon-gated)

The Astra Pro `woocommerce` module unlocks ~90 additional `astra-settings` keys that control the shop archive, single product page, cart, and checkout appearance. Reads and writes go through dedicated abilities that scope the allowlist to these WC-specific keys.

```
1. astra-check-setup                                   → confirm addon.active + modules.woocommerce=true
                                                        + integrations.woocommerce=true
2. astra-get-woocommerce-settings                      → snapshot of the ~90 WC-scoped keys
3. astra-edit-woocommerce-settings settings={shop-grids: {...}, single-product-tabs-style: '...',
                                              cart-btn-color: '#hex', checkout-labels-color: '#hex', ...}
   → merge patch; validates every key against the WC allowlist (unknown keys rejected).
     Same lock + cache flush as astra-edit-settings.
```

## Gotchas

- **Race on `astra-settings`**: our write abilities hold a GET_LOCK advisory lock scoped by blog_id. External code that calls `astra_update_option()` directly (e.g. Customizer saves, other plugins) does NOT hold our lock — a concurrent Customizer save can silently overwrite our write. Avoid running writes at the same time as a user is in the Customizer.

- **Palette cache**: after writing palette colors via `astra-set-color-palette`, the CSS cache is flushed automatically. Do NOT manually call `astra_update_option` for palette keys — the cache will stay stale.

- **Module CPT orphans**: disabling a module (`astra-disable-module`) while CPT posts exist (`entities.astra-advanced-hook=true` with published posts) leaves the posts orphaned — they are not deleted, but the CPT is no longer registered. The ability warns and requires `confirm=true` in that case.

- **HFB cell keys**: unknown cell keys are silently ignored by Astra and the element falls into the "unused pool". Always validate cell keys against `astra_hfb_cell_keys_desktop()` / `astra_hfb_cell_keys_mobile()` before writing.

- **Multisite**: our advisory lock names are scoped by `get_current_blog_id()` — no cross-blog contention.

- **No PHP execution**: this surface does NOT expose raw PHP execution via Astra mechanisms. For Advanced Hooks content, use the `post_content` field (HTML / shortcodes). For raw PHP snippets, redirect to the Code Snippets integration (`code-snippets-integration` skill).

- **Content sanitization on Advanced Hooks / Headers**: the `content` field of `astra-create-hook` / `astra-edit-hook` passes through `wp_kses_post` on write. Iframes, script tags, and unsafe attributes are stripped — this is intentional. For markup that requires those, the caller must handle them at the block / shortcode layer, not by embedding raw HTML.

## Conventions

Verb mapping follows the global Novamira ability-naming rules:

| Goal | Verb |
|---|---|
| Read one entity or a structural snapshot | `get` |
| Enumerate a collection | `list` |
| Replace the entire content of an existing thing (PUT) | `set` |
| Modify in place — partial / merge update | `edit` |
| Create a brand-new entity | `create` |
| Permanently remove | `delete` |
| Detach a binding without deleting the entity | `clear` |
| Reorder items within an existing collection | `reorder` |
| Toggle a binary state | `enable` / `disable` |

Never use: `update`, `patch`, `modify`, `remove`, `fetch`, `retrieve`.

`set-*` is a full PUT: the entire value is replaced. `edit-*` is a merge patch: only the supplied keys are changed, others untouched. For `astra-settings`, `astra-edit-settings` and `astra-edit-woocommerce-settings` do merges; `astra-set-color-palette` replaces the palette wholesale; `astra-set-header-layout` / `astra-set-footer-layout` replace the grid wholesale.

## Simple operations via `novamira/execute-php`

For trivial ad-hoc reads/writes of a single `astra-settings` key, presence checks on Astra Pro modules, or manual cache flushes, use `novamira/execute-php` from the base Novamira plugin instead of a dedicated Astra ability. Examples:

```php
// Read one setting
echo astra_get_option('site-title-color');

// Check if a module is active
var_export(class_exists('Astra_Ext_Extension') && Astra_Ext_Extension::is_active('sticky-header'));

// Force-flush the dynamic CSS transient
delete_option('astra-addon-cache-css-key');
```

**Do NOT use `execute-php` for**: multi-key writes (use `astra-edit-settings` — it holds the option lock), palette rewrites (use `astra-set-color-palette` — it invalidates the CSS cache), CPT create/edit (use the `create-*` / `edit-*` abilities — they run the meta validators). `execute-php` bypasses our lock, sanitizer, and cache invalidation.
