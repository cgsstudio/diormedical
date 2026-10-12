---
name: avada-integration
description: Activate when working with Avada theme settings — Fusion Theme Options (`fusion_options`), AWB Global Colors and Global Typography tokens, Portfolio + FAQ CPTs, and Fusion hook discovery — or with the Fusion Slider (Fusion Core companion plugin: `slide` CPT + `slide-page` term settings) on a WordPress site running the Avada theme (ThemeFusion, `AVADA_VERSION` defined). Fusion Builder plugin surfaces (element tree, Template Builder, Fusion Forms) are out of scope for this skill. Do NOT activate for other themes, block-based themes (Spectra One, Kadence), or pure builder plugins (Elementor, Bricks, Breakdance).
---

# Avada Theme + Fusion Core Integration

Theme + Fusion-Core-plugin plumbing skill: Theme Options, global tokens, Portfolio + FAQ CPTs, Fusion Slider (slides + slider settings), and the `execute-php` recipes for one-off operations too small to warrant a dedicated ability. Fusion Builder plugin surfaces (shortcode element tree, Template Builder, Fusion Forms) are out of scope — those require the Fusion Builder plugin and are not covered here.

## When to use

Activate when the user asks about:

- **Theme Options** — `fusion_options` DB row, per-tab settings (color, typography, layout, header, footer, extras), custom CSS/JS.
- **Global tokens** — AWB Global Colors palette (`color1`..`colorN`), AWB Global Typography presets (`typography1`..`typographyN`).
- **Portfolio / FAQ CPTs** — enumerate posts of `avada_portfolio` / `avada_faq`.
- **Fusion hooks** — which filter / action hooks the theme exposes.
- **Ad-hoc reads** — raw `fusion_options` blob, transient introspection, cache clearing.
- **Fusion Slider** (Fusion Core plugin) — list / read / edit slides (`slide` CPT) and sliders (`slide-page` terms) with all their `_fusion` settings bag.

Do NOT activate for:

- **Element-tree CRUD on `[fusion_builder_container]...` shortcodes** in `post_content` — needs the Fusion Builder plugin and is not covered by this skill.
- **Template Builder / Layout Sections** (`fusion_tb_layout`, `fusion_tb_section`, `fusion_element`) — plugin-owned CPTs, unavailable without Fusion Builder installed.
- **Fusion Forms** (`fusion_form`) — same, plugin-scoped.
- **Registration Center / license management** — browser-only OAuth-like flow the theme's admin UI handles; NOT automatable from PHP.

## Discover first

Every workflow starts with `novamira/avada-check-setup`. It reports:

- `theme.{active, version, min_supported, min_satisfied}` — go / no-go gate. Minimum is `NOVAMIRA_PRO_AVADA_MIN_VERSION` (7.15.0 today).
- `fusion_builder.{active, version, min_supported, min_satisfied}` — plugin state. On this environment expect `active: false` — theme-only phase.
- `fusion_core.{active, version, min_supported, min_satisfied}` — companion plugin. Minimum is `NOVAMIRA_PRO_FUSION_CORE_MIN_VERSION` (5.15.5 today). When `min_satisfied: true` the Fusion Slider abilities are registered; when false they are not, and `issues[]` explains why.
- `capability.can_manage_options` — the ability layer's permission gate.
- `options.fusion_options_present` — true when the DB row exists (fresh installs return false until /wp-admin loads once).
- `counts.portfolio_items` + `counts.faqs` — reads via `WP_Query` (matches the list-* totals exactly, honours pre_get_posts filters).
- `issues[]` — actionable warnings (theme under floor, plugin missing, fresh install, Fusion Core missing).

If `theme.min_satisfied` is false, every write ability returns `avada_theme_not_active` — surface the version gap to the user first.

## Domain model

Avada stores nearly everything inside ONE giant `wp_options` row: **`fusion_options`**. Multilingual installs suffix it (`fusion_options_<lang>`). The row is a PHP array with ~450 top-level keys spanning six functional areas:

| Tab / area | Sample keys | Notes |
|---|---|---|
| **color** | `primary_color`, `secondary_color`, `body_typography_color`, `link_color`, `sep_color` | Single scalar colors (RGBA/hex). Global palette lives separately in `color_palette` (see below). |
| **typography** | `body_typography`, `h1_typography`..`h6_typography`, `button_typography`, `google_enabled_fonts` | Structured multi-field values (font-family + size + weight). Global presets live in `typography_sets`. |
| **layout** | `layout`, `site_width`, `main_padding`, `content_padding`, `grid_max_columns` | Numeric + string layout knobs. |
| **header** | `header_position`, `header_layout`, `header_sticky`, `logo`, `logo_retina` | Header/nav configuration. |
| **footer** | `footer_area_columns`, `footer_area_padding`, `copyright` | Footer configuration. |
| **extra** | `status_lightbox`, `status_gmap`, `status_totop`, `favicon`, `preloader` | Feature toggles + extras. |

Two subordinate structures inside `fusion_options`:

- **`color_palette`** — keyed by slug (`color1`, `color2`, ...), each entry `{label, color}`. Managed by `AWB_Global_Colors::get_instance()`. Read + write via `novamira/avada-{list,set}-global-color`.
- **`typography_sets`** — keyed by slug (`typography1`, `typography2`, ...), each entry `{label, font-family, font-size, variant, font-weight, line-height, letter-spacing, text-transform, ...}`. Managed by `AWB_Global_Typography::get_instance()`. Read + write via `novamira/avada-{list,set}-global-typography`.

Theme-native CPTs (registered by the theme, not the plugin):

- **`avada_portfolio`** with taxonomy `portfolio_category` — Portfolio items.
- **`avada_faq`** — FAQ items.

## Fusion Slider domain model (Fusion Core plugin)

When Fusion Core is active (`fusion_core.min_satisfied: true`), six additional abilities light up:

- **Slider = `slide-page` term.** There is NO CPT called "slider". Every slider is a term of the `slide-page` (hierarchical) taxonomy; the display settings (dimensions, animation, autoplay, nav) live in a single `_fusion` **term_meta** bag on that term. Slug is the identity — every ability accepts either a slug or the numeric term id via `slider`.
- **Slide = post of `slide` CPT** assigned to one or more `slide-page` terms. All slide fields — `type` (image/youtube/vimeo/mp4), headings, captions, colors, video URLs, per-slide link — live in a single `_fusion` **postmeta** bag on the post.
- **Featured image** = slide background when `type: image` (WP core attachment, NOT part of the `_fusion` bag).
- **Serialised envelope**: both bags are ONE `_fusion` key holding a PHP array. Low-level access via `get_post_meta($id, '_fusion', true)` / `update_post_meta` is equivalent to Fusion Library's `fusion_data(...)->set_raw(...)` — the fusion-library wrapper backs it with the same key.

Whitelist of writable slide keys (see `avada_writable_slide_keys()` in runtime.php) — an unknown key rejects the whole batch:

| Type | Keys |
|---|---|
| `enum:image\|youtube\|vimeo\|mp4` | `type` |
| `enum:cover\|contain` | `video_display` |
| `enum:left\|center\|right` | `content_alignment` |
| `enum:1\|2\|3\|4\|5\|6` | `heading_size`, `caption_size` |
| `enum:button\|image\|none` | `link_type` |
| `enum:_self\|_blank` | `slide_target` |
| `bool` (stored `'0'`/`'1'`) | `heading_separator`, `heading_bg`, `caption_separator`, `caption_bg`, `mute_video`, `autoplay_video`, `loop_video`, `hide_video_controls` |
| `color` | `heading_color`, `heading_bg_color`, `caption_color`, `caption_bg_color`, `video_bg_color` |
| `url` | `mp4`, `webm`, `ogv`, `slide_link` |
| `string` | `aspect_ratio`, `youtube_id`, `vimeo_id`, `heading`, `heading_font_size`, `caption`, `caption_font_size` |

Whitelist of writable slider setting keys (see `avada_writable_slider_setting_keys()`):

| Type | Keys |
|---|---|
| `string` (dimension literal, e.g. `'1200'` or `'100%'`) | `slider_width`, `slider_height`, `slider_content_width`, `typo_sensitivity`, `typo_factor` |
| `bool` (`'0'`/`'1'`) | `full_screen`, `parallax`, `nav_arrows`, `autoplay`, `loop` |
| `int` (stored as digit string) | `nav_arrow_size`, `nav_box_width`, `nav_box_height`, `slideshow_speed`, `animation_speed` |
| `enum:\|scroll_down_indicator\|pagination_circles` | `slider_indicator` (empty = off) |
| `enum:fade\|slide` | `animation` |
| `enum:date\|ID\|title\|modified\|rand` | `orderby` |
| `enum:ASC\|DESC` | `order` |
| `color` | `slider_indicator_color` |

Storage-shape note: Fusion Slider stores `bool` and `int` as STRING literals in the `_fusion` bag (form inputs are `type="text"` in the admin). Sanitisers accept native `bool`/`int` from callers and coerce to the string form the theme expects — round-trip is safe.

## Fusion Slider read workflow

```
1. novamira/avada-check-setup                         → confirm fusion_core.min_satisfied
2. novamira/avada-list-sliders                        → discover slugs (include_empty:true if needed)
3. novamira/avada-get-slider slider=<slug>            → term_meta settings + slide list
   [include_slides: false] to skip slide enumeration
4. novamira/avada-list-slides [slider=<slug>]         → paginated slides (limit clamp 100)
5. novamira/avada-get-slide id=<n>                    → single slide with full _fusion bag
   [max_field_bytes: 0] to opt out of byte cap
```

## Fusion Slider write workflow

```
1. novamira/avada-edit-slide id=<n> values={heading: "...", type: "image", ...}
   → {changed, written[], unchanged[], meta{post-write bag}}
   Partial-merge — siblings preserved. Unknown key or bad value → whole batch rejected.

2. novamira/avada-edit-slider-settings slider=<slug> values={slider_width: "1400", autoplay: true, ...}
   → {changed, written[], unchanged[], settings{post-write bag}}
   Same partial-merge semantics. Write is serialised against concurrent editors of
   the SAME slider via a per-term INSERT-based lock (INSERT into a wp_options mutex
   row; TTL 30s; multisite-safe by blog_id namespacing).
```

Both writers verify the DB row AFTER commit (bypassing filter caches) and surface `avada_write_failed` if a caching / security plugin's `pre_update_*_meta` filter vetoed the write. Both flush the `avada` wp_cache group + fire `fusion_cache_clear` after a successful change.

## Read workflow

```
1. novamira/avada-check-setup             → confirm environment
2. novamira/avada-get-theme-options       → snapshot of fusion_options tabs
   [include: ['color', 'typography']] to narrow
   [max_field_bytes: 0] to opt out of byte cap on custom CSS/JS
3. novamira/avada-list-global-colors      → 8-slot palette
4. novamira/avada-list-global-typography  → typography presets
5. novamira/avada-list-portfolio-items    → CPT inventory (paginated)
6. novamira/avada-list-faqs               → CPT inventory (paginated)
```

## Write workflow — global tokens

```
1. novamira/avada-list-global-colors                     → discover slugs
2. novamira/avada-edit-global-color slug=color1 label=... color=...
   → response reports {slug, label, color, changed}
3. Second identical call → changed: false (idempotent no-op)
```

Every write goes through a WP-transient CAS lock scoped by option key + blog id, then reads the row BACK from the DB via a raw wpdb query to verify persistence. A caching-plugin filter vetoing `pre_update_option_fusion_options` surfaces as `avada_write_failed` — the ability never lies about `changed`.

## Write workflow — theme options

```
1. novamira/avada-get-theme-options → read current values
2. novamira/avada-set-theme-options settings={key1: value1, key2: value2, ...}
   → response: {written: [...], unchanged: [...], settings: {...snapshot}}
```

Whitelist-only: ~20 curated safe keys (colors, layout, header/footer toggles, extras). Any unknown key OR reserved key (`custom_css`, `custom_js`, `nvp_license_key`, …) rejects the ENTIRE batch with `avada_unknown_setting` — no partial write.

Every value goes through a scalar sanitiser at the boundary: strings with `<` / `>` are rejected (potential stored XSS in the dynamic-CSS pipeline), non-scalars rejected, valid values pass through `sanitize_text_field`.

## Simple operations via `novamira/execute-php`

A few Avada operations don't warrant a dedicated ability — do them inline with `novamira/execute-php`.

**1. Read the raw `fusion_options` row** — full option payload, bypasses tab grouping:

```php
return get_option('fusion_options', []);
```

**2. Read a single Fusion setting** — cheaper than `get-theme-options` when you only need one key:

```php
return function_exists('fusion_get_option') ? fusion_get_option('primary_color') : null;
```

**3. Manual full-cache flush** — after an execute-php write, before front-end verification:

```php
if (class_exists('Fusion_Cache')) {
    Fusion_Cache::instance()->reset_all_caches();
}
delete_transient('fusion_dynamic_css');
return true;
```

**4. Read raw `_fusion` postmeta** — per-page Fusion Builder data (only meaningful when the plugin is installed):

```php
return get_post_meta(<post_id>, '_fusion', true);
```

**5. Count posts containing a Fusion Builder shortcode** — inventory before enabling the plugin:

```php
global $wpdb;
return (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_content LIKE %s",
        '%[fusion_builder_container%'
    )
);
```

**6. Enumerate `fusion_tb_layout` posts** — Template Builder layouts (only when Fusion Builder plugin is installed):

```php
if (!post_type_exists('fusion_tb_layout')) {
    return ['error' => 'fusion_tb_layout post type not registered; Fusion Builder plugin missing'];
}
return get_posts([
    'post_type'      => 'fusion_tb_layout',
    'posts_per_page' => 100,
    'post_status'    => 'any',
    'fields'         => 'ids',
]);
```

**7. Read Avada theme version + Fusion Builder plugin version** — quick probe:

```php
return [
    'theme'          => defined('AVADA_VERSION') ? AVADA_VERSION : null,
    'fusion_builder' => defined('FUSION_BUILDER_VERSION') ? FUSION_BUILDER_VERSION : null,
    'fusion_core'    => defined('FUSION_CORE_VERSION') ? FUSION_CORE_VERSION : null,
    'fusion_library' => defined('FUSION_LIBRARY_VERSION') ? FUSION_LIBRARY_VERSION : null,
];
```

**8. Regenerate Fusion Dynamic CSS** — after an out-of-band change:

```php
if (class_exists('Fusion_Dynamic_CSS')) {
    Fusion_Dynamic_CSS::regenerate();
    return true;
}
return ['error' => 'Fusion_Dynamic_CSS class not loaded'];
```

**9. Read the FULL AWB Global Colors palette WITH filter chain applied** — useful for debugging what the theme actually renders (vs the raw stored value the ability writes):

```php
if (!class_exists('AWB_Global_Colors')) {
    return ['error' => 'AWB_Global_Colors class not loaded'];
}
return AWB_Global_Colors::get_instance()->get_palette();
```

**10. Read multilingual `fusion_options_<lang>`** — the ability layer operates on the current language only; other languages need direct reads:

```php
$lang = '<lang_slug>';  // e.g. 'en', 'it', 'de'
return get_option('fusion_options_' . $lang, null);
```

**11. List `slide-page` terms with slide counts** — same signal as `list-sliders` but with your own filter (e.g. only terms whose slug starts with `hero-`):

```php
$terms = get_terms(['taxonomy' => 'slide-page', 'hide_empty' => false]);
return array_map(fn($t) => ['slug' => $t->slug, 'name' => $t->name, 'count' => (int) $t->count], $terms);
```

**12. Verify Fusion Slider is enabled globally** — the theme has an opt-out toggle in Global Options (`status_fusion_slider`); when off, the CPT is not registered:

```php
if (!post_type_exists('slide')) {
    return ['enabled' => false, 'reason' => 'slide CPT not registered'];
}
$setting = function_exists('fusion_get_option') ? fusion_get_option('status_fusion_slider') : null;
return ['enabled' => $setting !== '0', 'raw_setting' => $setting];
```

**13. Force-flush the Fusion Core `avada` cache group** — after an out-of-band write to a slide or slider term (e.g. a bulk import). Ability writes already do this, so only needed when working outside the ability layer:

```php
if (function_exists('wp_cache_flush_group')) {
    wp_cache_flush_group('avada');
}
if (class_exists('Fusion_Cache')) {
    Fusion_Cache::instance()->reset_all_caches();
}
return true;
```

**14. Duplicate a slider (term + slides) — low-level** — no dedicated ability yet; combine the term-clone workflow:

```php
$src = get_term_by('slug', '<source-slug>', 'slide-page');
if (!$src) { return ['error' => 'source slider not found']; }
$new = wp_insert_term($src->name . ' (Copy)', 'slide-page', ['slug' => $src->slug . '-copy']);
if (is_wp_error($new)) { return $new; }
$src_settings = get_term_meta($src->term_id, '_fusion', true);
if (is_array($src_settings)) { update_term_meta($new['term_id'], '_fusion', $src_settings); }
return ['new_term_id' => $new['term_id'], 'new_slug' => $src->slug . '-copy'];
```

## Gotchas

- **`fusion_options` is a single blob**: every write is a full read-modify-write on a ~50KB serialised array. Concurrent writers race — the ability layer serialises them with a per-key transient lock; direct `update_option` calls in `execute-php` do NOT get that lock.
- **Multilingual `fusion_options_<lang>`**: WPML / Polylang stores per-language option rows. `Fusion_Settings` resolves to the active language on read. The ability layer works on whichever row is active for the current request — for cross-language writes use the execute-php recipe.
- **`AWB_Global_Colors`/`AWB_Global_Typography` are singletons with public property caches**: `->palette` and `->typography`. The ability layer flushes them after every write. If you write via execute-php, do the same (`AWB_Global_Colors::get_instance()->palette = null`) or the next read in the same request sees stale data.
- **`Fusion_Cache::reset_all_caches()` is HEAVY** — flushes dynamic CSS files, transients, object cache group. Called by the ability layer only after a successful write; do NOT call it on every read.
- **Registration Center is browser-only**: the Avada admin `Register` flow uses a token exchange the theme handles from `wp-admin/admin.php?page=avada-registration`. No PHP ability can substitute for the browser step. Skip when the user asks to "activate the theme license".
- **CSS injection surface**: color / label / typography-field values feed the theme's dynamic CSS pipeline verbatim. The ability layer sanitises + rejects `<`, `>`, `;`, `{`, `}` in values (defense-in-depth); direct execute-php writes have no such guard — sanitise before persisting anything a user typed.
- **`AVADA_VERSION` is a PHP constant**: multisite `switch_to_blog()` does NOT re-run theme bootstrap, so `AVADA_VERSION` stays defined for the rest of the request even after switching to a subsite where Avada is NOT the active theme. Trust the constant only when the current-blog scope is clear.
- **Fusion Builder plugin absent → plugin-scoped abilities do not appear** in the catalogue (e.g. hypothetical `avada-list-tb-layouts` — not implemented). The theme-only surface is fully functional independently.
- **Fresh install (`options.fusion_options_present: false`)**: writes still succeed (they create the row), but any read expecting theme defaults returns empty. Prompt the user to load `/wp-admin` once so the theme populates its defaults.
- **`avada` wp_cache group is NOT auto-invalidated**: Fusion Core memoises `WP_Query` results in `wp_cache group='avada'` via `FusionCore_Plugin::fusion_core_cached_query()` and does NOT invalidate the group on `save_post_slide` or `edited_slide-page`. Ability writes call `wp_cache_flush_group('avada')` explicitly; out-of-band writes (execute-php, WP-CLI) MUST call it too or the front-end shortcode `[fusion_fusionslider]` serves stale data on persistent object cache backends (Redis/Memcached).
- **Fusion Slider bool + int stored as strings**: `_fusion` bag values for booleans are string literals `'0'`/`'1'`, for integers digit strings. Sanitisers accept native `bool`/`int` from callers and coerce; direct execute-php writes MUST match this shape or the front-end reads mismatch (Fusion admin does string comparisons).
- **Slide postmeta writes are serialised per-post**: `edit-slide` wraps the read-modify-write cycle in `av_with_slide_post_lock` (per-post INSERT-based mutex, mirror of the per-term slider lock — audit A3 F-A). Concurrent callers editing different keys of the same slide serialise cleanly on the lock; the read-back verify additionally catches storage-layer vetoes. Out-of-band writes via `execute-php` bypass this lock, so a manual bulk-import loop must either coordinate at the caller layer or route each write through the ability.
- **`custom_css` / `custom_js` are RESERVED**: the ability rejects them by design. If a user genuinely wants to write CSS/JS, use the execute-php recipe below and be VERY intentional about it — this is the biggest stored-XSS surface in the theme.

```php
// EXECUTE-PHP RECIPE: write custom CSS after CAREFUL sanitisation.
$css = /* the CSS string, already reviewed */;
$options = get_option('fusion_options', []);
$options['custom_css'] = wp_check_invalid_utf8($css);  // NO tag stripping — CSS may look like tags
update_option('fusion_options', $options);
if (class_exists('Fusion_Cache')) {
    Fusion_Cache::instance()->reset_all_caches();
}
return ['written' => strlen($css)];
```

## Conventions

- Ability slugs: `novamira/avada-<verb>-<object>`. Verbs: `check`, `list`, `get`, `set`, `edit` — never `update`/`patch`/`modify`. `edit-*` = partial-merge; `set-*` = full replace.
- Compact list, full get: `list-portfolio-items`, `list-faqs`, `list-slides` return compact rows paginated with `limit` default 20-25, cap 100.
- Errors: `avada_theme_not_active` (400), `avada_fusion_core_not_active` (400), `avada_invalid_input` (400), `avada_unknown_setting` (400), `avada_write_failed` (500), `avada_post_not_found` (404), `avada_slider_not_found` (404).
- Annotations: list/get/check → `readonly:true`; `edit-*` (partial-merge) → `readonly:false + destructive:false + idempotent:true`; `set-*` (full replace) → `readonly:false + destructive:true + idempotent:true`.
- Cache flush: automatic after every successful write. Theme-option writes go through `avada_flush_fusion_cache()` (AWB singletons + `Fusion_Settings::reset_all_options()` + dynamic CSS transient); slide/slider writes go through `avada_flush_fusion_core_cache()` (`avada` wp_cache group + `Fusion_Cache::reset_all_caches()`). Both fire `fusion_cache_clear` for third-party listeners.
