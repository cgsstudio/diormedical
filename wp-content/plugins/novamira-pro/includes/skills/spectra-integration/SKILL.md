---
name: spectra-integration
description: Activate when troubleshooting Spectra (UAGB) settings, Google Fonts delivery, block-activation registry, form submissions, reCAPTCHA keys, Instagram Feed, or Loop Builder configuration on a WordPress site where the Spectra plugin (`ultimate-addons-for-gutenberg`) is active. Also the home for the ~10 `novamira/execute-php` recipes that cover simple one-off operations too small to warrant a dedicated ability. Do NOT activate for the Spectra One theme (`spectra-one-*`) or the new Spectra Blocks AI plugin — those are separate surfaces.
---

# Spectra (UAGB) Integration

Companion to the `spectra-build-page` skill. That skill covers *building* pages and popups; this one covers *plumbing* — settings, fonts, submissions, third-party service keys, and the `execute-php` recipes for one-offs that are not worth a dedicated ability.

## When to use

Activate when the user asks about:

- **Settings** — global editor toggles, container defaults, block activation, load-locally / preload / FSE-globally font flags.
- **Google Fonts** — which families the install serves, from which storage source, in which delivery mode.
- **Form submissions** — "where are my form entries?", "how do I read a Spectra form submission?"
- **reCAPTCHA** — v2 / v3 site + secret keys, integration with `uagb/forms`.
- **Instagram Feed** — connection status, feed rendering, why an account isn't linking.
- **Loop Builder** — dynamic content markup vs `uagb/post-grid` / `uagb/post-carousel`.
- **Ad-hoc reads** — one-off queries on `_uagb_*` or `uag_*` options that don't need a dedicated ability.

Do NOT activate for page composition (that's `spectra-build-page`), for the Spectra One theme, or for the AI-based Spectra Blocks plugin (`spectra-blocks/`).

## Discover first

Always begin with `novamira/spectra-check-setup`. It returns:

- `plugin.{active,version,min_supported,min_satisfied}` — go / no-go gate. Minimum is `NOVAMIRA_PRO_SPECTRA_MIN_VERSION` (2.19.0 today).
- `pro.{active,version,min_supported,min_satisfied}` — Spectra Pro presence + version (min 1.2.0). Pro ships its own version scheme independent of UAGB.
- `license.mode` — `free` / `pro`.
- `blocks_registered` — how many `uagb/*` blocks the install exposes.
- `popups_count` — how many `spectra-popup` CPT posts exist.
- `forms_usage_count` — how many posts contain at least one `uagb/forms` block (transient-backed; second call in the same request is free).

If `plugin.min_satisfied` is false, every write in this skill will fail with `spectra_not_active`. Surface the gap to the user first.

## Settings shape

Spectra stores editor settings across three surfaces:

1. **`uag_*` options** — top-level editor toggles and container / typography / visibility defaults. `spectra-get-settings` returns the 17 whitelisted keys with source annotation (`option`, `default`, `filtered`).
2. **`_uagb_blocks` option** — per-block on/off registry (single JSON blob). `spectra-set-block-activation` wraps the atomic-merge write; see the `execute-php` recipes below when you need to read the raw structure.
3. **`spectra_gbs_google_fonts`, `spectra_global_fse_fonts`, `uag_select_font_globally`** — Google Fonts sources across editor / block-styles / FSE contexts. `spectra-list-google-fonts` merges all three; `spectra-set-google-fonts-mode` flips delivery flags.

Every write ability in this surface flushes the relevant cache (`spectra_assets_files_indicator` transient + `uagb` cache group) so the next request serves fresh CSS.

## Submission triage — the hard truth

**Spectra does NOT persist form submissions.** Every `uagb/forms` submit fires an AJAX POST that flows through `UAGB_Front_Assets::sanitize_form_inputs` → email delivery → nothing else. No submissions table, no CPT, no postmeta, no options row. There is nothing to list, nothing to trash, nothing to mark as read.

Options when the user asks for submissions:

1. **Show them the email inbox.** That IS the storage.
2. **Recommend a real form plugin.** Ninja Forms (`novamira/ninja-forms-*`), WPForms (`novamira/wpforms-*`), Gravity Forms (`novamira/gravityforms-*`), Fluent Forms (`novamira/fluentforms-*`), Formidable (`novamira/formidable-*`), CF7 (`novamira/cf7-*`) all have full submission triage with dedicated Novamira abilities.
3. **Bridge with a plugin like FluentSMTP or WP Mail SMTP** — those DO log outbound email including the form payload. Not a Spectra ability; suggest the plugin.

Use `novamira/spectra-list-form-blocks` to enumerate *posts* that use a form block (with per-post `form_count`) — that's inventory, not submissions. Useful for the "which pages have a contact form?" question.

## Google Fonts delivery modes

Two orthogonal decisions:

- **Local vs remote** — `uag_load_gfonts_locally = 'enabled'|'disabled'`. Local downloads the WOFF2 files to `wp-content/uploads/uag-fonts/` and serves from origin (better privacy, no third-party request). Remote uses `fonts.googleapis.com` (heavier third-party).
- **Preload local** — `uag_preload_local_fonts = 'enabled'|'disabled'`. Only meaningful when local mode is enabled; adds `<link rel=preload>` for critical font files. Marginal LCP benefit; ignore on sites without a documented font-loading problem.
- **FSE globally** — `uag_load_fse_font_globally = 'enabled'|'disabled'`. Applies the loaded Google Fonts to the FSE canvas (only relevant on block themes). Off by default because most FSE themes ship their own font stack.

Flip via `spectra-set-google-fonts-mode`. Idempotent — `changed: false` when the mode already matches.

## Simple operations via `novamira/execute-php`

A few Spectra operations do not warrant a dedicated ability — do them inline with `novamira/execute-php`. Every recipe below is safe on any install (bails when Spectra is missing).

**1. Read a single `uag_enable_*` option** — cheaper than `get-settings` when you only need one flag:

```php
return get_option('uag_enable_animations_extension', 'disabled');
```

**2. Toggle one editor setting when it's outside the 17-key allowlist** — `set-editor-settings` will refuse an unknown key; sometimes you need a raw write:

```php
update_option('uag_enable_masonry_gallery', 'enabled');
delete_transient('spectra_assets_files_indicator');
wp_cache_flush_group('uagb');
return get_option('uag_enable_masonry_gallery');
```

**3. Count posts using a specific block** — inventory before a destructive `set-block-activation enabled=false`:

```php
global $wpdb;
return (int) $wpdb->get_var(
    $wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_status IN ('publish','draft','private') AND post_content LIKE %s",
        '%<!-- wp:uagb/testimonial %'
    )
);
```

**4. Read a popup's repetition rule** — an integer meta that controls how often a `time-delay` popup re-shows to the same visitor:

```php
return get_post_meta(<popup_id>, 'spectra-popup-repetition', true);
```

**5. Bulk-read enabled popups** — outside the `list-popups enabled=true` filter, e.g. when you also need to project custom meta:

```php
$ids = get_posts([
    'post_type'      => 'spectra-popup',
    'post_status'    => 'publish',
    'posts_per_page' => 100,
    'fields'         => 'ids',
    'meta_query'     => [
        ['key' => 'spectra_popup_enable', 'value' => '1', 'compare' => '='],
    ],
]);
return array_map(fn($id) => [
    'id'    => $id,
    'title' => get_the_title($id),
    'delay' => (int) get_post_meta($id, 'spectra-popup-trigger-delay', true),
], $ids);
```

**6. Read the raw `_uagb_blocks` registry** — when you need to diff or export the full per-block state:

```php
$raw = get_option('_uagb_blocks', []);
return is_array($raw) ? $raw : json_decode((string) $raw, true);
```

Note: legacy installs store the registry as a JSON string; newer ones as a PHP array. The recipe accepts both. Do NOT `update_option` the same key without the atomic-merge logic `spectra-set-block-activation` wraps — you'll clobber concurrent writes.

**7. Clear Spectra cache after any option write** — needed whenever you write a `uag_*` / `spectra_*` option outside a Novamira ability:

```php
delete_transient('spectra_assets_files_indicator');
wp_cache_flush_group('uagb');
return true;
```

**8. Read reCAPTCHA site key (masked secret)** — Spectra Pro stores v2 + v3 keys in `uag_recaptcha_*` options. Never echo the secret to the agent; return only the site key + a boolean for the secret's presence:

```php
return [
    'v2_site'       => get_option('uag_recaptcha_site_key_v2', ''),
    'v2_has_secret' => (bool) get_option('uag_recaptcha_secret_key_v2', ''),
    'v3_site'       => get_option('uag_recaptcha_site_key_v3', ''),
    'v3_has_secret' => (bool) get_option('uag_recaptcha_secret_key_v3', ''),
];
```

**9. Write reCAPTCHA keys** — one-shot setup, no bulk workflow:

```php
update_option('uag_recaptcha_site_key_v3',   '<site_key>');
update_option('uag_recaptcha_secret_key_v3', '<secret_key>');
delete_transient('spectra_assets_files_indicator');
return ['written' => ['v3_site', 'v3_secret']];
```

Ask the user for the keys interactively — do NOT hardcode. Confirm the secret is a plausible reCAPTCHA v3 key (starts with `6L`, 40 chars) before writing.

**10. Fire a Spectra Pro filter for debug** — inspect what conditions Spectra Pro currently evaluates for a popup:

```php
if (!defined('SPECTRA_PRO_VER')) {
    return ['error' => 'spectra_pro_not_active'];
}
return apply_filters('spectra_pro_popup_display_filters', [], <popup_id>);
```

**11. Dry-run render a UAGB block** — simulate the front-end output without saving:

```php
$markup = '<!-- wp:uagb/heading {"headingTitle":"Hello","headingTag":"h1"} /-->';
$blocks = parse_blocks($markup);
return render_block($blocks[0]);
```

Useful to preview a block's rendered HTML before inserting it into `post_content`.

## Instagram Feed

Spectra Pro ships an `Instagram Feed` block (`uagb/instagram`) that wraps the Instagram Basic Display API. **Account linking is a browser-OAuth flow — not automatable via ability or `execute-php`.**

- **Discovery**: `spectra-check-setup` reports whether the block is registered. The presence of a linked account lives in `uag_instagram_feed_access_token` (empty string when no account is connected).
- **Read connection status**:
  ```php
  return [
      'connected'    => (bool) get_option('uag_instagram_feed_access_token', ''),
      'token_length' => strlen((string) get_option('uag_instagram_feed_access_token', '')),
      'expiry'       => get_option('uag_instagram_feed_token_expiry', null),
  ];
  ```
- **Refresh a long-lived token** (when a token is present but expiring):
  ```php
  return apply_filters('uagb_instagram_feed_refresh_token', null);
  ```
- **What to tell the user**: linking a new Instagram account requires opening `wp-admin/admin.php?page=spectra&path=/dashboard/instagram-feeds`, clicking "Connect Instagram Account", and completing the OAuth redirect in the browser. There is NO server-side ability that can substitute for the OAuth handshake. Once the token is stored, all subsequent reads / renders / refreshes ARE server-side.

## Loop Builder

Spectra Pro ships a **Loop Builder** block (`uagb/loop-builder`) that renders a WP_Query loop with a custom template block subtree. It's a specialised dynamic-render block; no dedicated Novamira ability wraps it.

- **Discover instances**: narrow candidates with an `execute-php` `WP_Query` on `s: "wp:uagb/loop-builder"`, then confirm with `novamira/gutenberg-get-content target_id=<id>` and scan the compact tree for `uagb/loop-builder` nodes.
- **Read one loop's config**: the same `gutenberg-get-content` snapshot (`include_attributes=true`) returns the loop-builder attributes (query args) and its template subtree (children).
- **Edit query args**: the query controls (`postType`, `postsPerPage`, `orderby`, `order`, `taxQuery`, `metaQuery`) live in the block attributes. Recompose the post's FULL tree with the modified attributes and submit it via `novamira/gutenberg-add-pending-change` → `gutenberg-enable-batch-finalization` (the pipeline's only operation is `replace-content`); the front-end re-renders the loop on the next request after finalization.
- **Loop template markup**: the template lives as child blocks in the loop-builder's `innerBlocks` — compose the per-item subtree in the same `block_spec`. Common pattern: `uagb/container` → `uagb/advanced-heading` (bound to `{{title}}`) → `uagb/image` (bound to `{{featured_image}}`) → `uagb/buttons`.
- **Dynamic tokens**: Spectra's dynamic-content tokens (`{{title}}`, `{{content}}`, `{{featured_image}}`, `{{author_name}}`, `{{permalink}}`, `{{acf_field:<key>}}`, `{{meta:<key>}}`) resolve at render time; write them raw in the block attributes of the composed spec.

Alternative: for most listing intents, `uagb/post-grid` and `uagb/post-carousel` are simpler pre-built dynamic blocks and are documented in `spectra-list-blocks`. Reach for Loop Builder only when the per-item layout needs to be a full block subtree.

## Gotchas

- **Every write flushes Spectra cache.** The abilities do this for you; the `execute-php` recipes above do it too. If you write a `uag_*` / `spectra_*` option any other way, `delete_transient('spectra_assets_files_indicator')` + `wp_cache_flush_group('uagb')` afterward or the front end serves stale CSS.
- **`_uagb_blocks` is a single-blob option — not transaction-safe.** Naïve read-modify-write races. `spectra-set-block-activation` wraps an atomic-merge helper. Replicating it in `execute-php` requires the same `wp_cache_delete → get_option → merge → update_option` sequence.
- **Spectra option prefixes are shared with Spectra Blocks AI plugin.** On installs that run BOTH, some option keys overlap. Prefer the Novamira abilities (they check plugin gate first); avoid raw `get_option` on ambiguous keys.
- **reCAPTCHA secrets never leave the server.** Recipe 8 above deliberately returns `has_secret: bool` instead of the value. Do not write a recipe that echoes a secret to the agent context.
- **Instagram token refresh happens automatically.** Spectra Pro re-refreshes the long-lived token via a scheduled event; only intervene when the token is definitively expired (empty option or an API error). Recipe manual refresh is a repair, not a routine call.
- **Loop Builder attributes are undocumented in PHP.** Read them from a live block via `gutenberg-get-block` on an existing loop before writing. The JS-side attribute registry is the source of truth.
- **`uag_recaptcha_*` keys have per-version storage.** v2 and v3 live under distinct option keys. Do not overwrite one when the user asked for the other.

## Conventions

- Ability slugs used here: `novamira/spectra-*` (free) + `novamira/execute-php` (recipes). See `spectra-build-page` skill for the Pro sub-layer.
- Every recipe above assumes an admin session (`wp_set_current_user` on an administrator). `novamira/execute-php` runs under the caller's cap — usually admin — but check `current_user_can('manage_options')` at the top if you're paranoid.
- Errors mirror the ability layer: `spectra_not_active`, `spectra_invalid_input`, `spectra_write_failed`. Recipes surface errors via return arrays (`['error' => 'reason']`), never via `WP_Error` (execute-php returns raw).
- Companion skill: `spectra-build-page` for page / popup composition, block-tree tasks, and the Pro popup-conditions workflow.
