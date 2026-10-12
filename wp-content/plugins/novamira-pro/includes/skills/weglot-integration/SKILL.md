---
name: weglot-integration
description: Activate when working with Weglot Translate on a WordPress site — configuring the API credentials, setting the source language, adding or removing destination languages, customizing the language-switcher button, adding URL exclusion rules, defining translated URL slugs per language, publishing local settings to the Weglot Cloud, refreshing from the cloud, or diagnosing translation-cache state. Establishes the local-vs-cloud split (the configuration lives on the cloud; the local option is only a pending-edit store), the pending_publish / cloud_sync_pending semantics, the storage orientation of custom_urls, the case-insensitive lookup of language codes, and which operations are abilities vs plain execute-php.
---

# Working with Weglot Translate

This skill is for using the `novamira/weglot-*` abilities together. Read it
once at the start of any Weglot task and refer back when in doubt.

## When to use

Activate when the user asks to view or change Weglot Translate state: API key
rotation, source / destination languages, language-switcher button style,
URL-exclusion rules, translated URL slugs, cloud sync (push/refresh/clear/cache
state). Do **not** activate for other translation plugins (Polylang, WPML,
TranslatePress — different storage and APIs) or for actual content
translation (Weglot translations live on weglot.com — only the dashboard
edits them).

Always call `novamira/weglot-check-setup` first. It reports the version, whether
the API key is configured (masked), whether the cloud accepts it (`cloud_state`
is `ok` / `no_credential` / `rejected` / `unreachable`), the plan, and whether
the plan includes translated URL slugs. If the Weglot abilities are missing from your tool
list, Weglot is not active or is below the minimum 5.0.

## The big idea: the configuration is not in WordPress

**Weglot is a cloud client, and the local option is not a mirror of the
settings.** This is the single most important thing to internalise, because
the obvious mental model is wrong.

`wp_options['weglot-translate-v3']` declares only seven keys of its own —
`has_first_settings`, `show_box_first_settings`, `menu_switcher`, `custom_urls`,
`flag_css`, `active_wc_reload`, `allowed`. Languages, exclusion rules, the
translation engine and the advanced toggles are **never written there** by
Weglot. They live on `api.weglot.com`, arrive via the `weglot_cache_cdn`
transient, and win the merge: `array_merge(defaults, local, cloud)` puts the
cloud last.

The practical consequence, and the default state of any site where someone
just pasted the API key: **the local option is empty of configuration while
the site translates perfectly.** Reading it would report an unconfigured site.

So the abilities work like this:

- **Reads** (`check-setup`, `get-settings`, `list-exclude-urls`,
  `list-translated-urls`) report the configuration **in force**, resolved
  through Weglot itself. All four carry `config_source`: `weglot` is the real
  thing, `local_fallback` means the resolver was unreachable and cloud-managed
  fields are *unknown*, not empty. On a list, `local_fallback` plus an empty
  result means "could not tell", never "nothing configured".
- **Writes** (`set-original-language`, `add`/`delete-destination-language`,
  `add`/`delete-exclude-url`, `edit-button-settings`) store a **pending edit**
  locally. They change nothing for visitors. Each returns
  `cloud_sync_pending: true`, and
  `get-settings` lists the affected keys in `pending_publish`. That list is a
  record of what the abilities changed, not a diff — a value merely sitting in
  the local option is not an unpublished edit, because `refresh-from-cloud`
  writes the cloud's own payload to the same place.
  A write starts from the settings currently in force, so it edits the real
  configuration rather than rebuilding it from an empty base — and it re-reads
  them even when the key is already in the local store, unless that key is
  itself an unpublished edit. Otherwise the first publish would freeze a
  snapshot and every later write would silently revert whatever changed on the
  dashboard in the meantime. If the settings cannot be read at all and there is
  no local value to fall back on, the write returns `weglot_config_unavailable`
  and changes nothing.
  `add`/`delete-translated-url` are the exception: they return
  `cloud_sync_pending: false` and are in force on this site at once — see the
  translated-slug section.
- **`weglot-push-options-to-cloud` is what makes an edit real.** Without it the
  edit is inert — the cloud wins the merge, so the site keeps serving the old
  value forever.

`weglot-get-status` is different: it queries the cloud live for project, plan
and quota. It is never blind and needs no publishing step.

The push sends only the keys with a pending edit. `POST /projects/settings`
merges server-side — measured on a live project: a payload that deliberately
omitted a field left it untouched, and a two-key payload left all 31 keys
byte-identical — so nothing else on the project is at risk. If both sides
changed the same key, the ability's value wins. It still re-reads the settings
first as a safety belt, but a failed read no longer blocks publishing.

**Translated slugs cannot be published.** They are not part of the project
settings: Weglot keeps them on a separate resource, `/translations/slugs`.
Posting `custom_urls` to the settings endpoint is accepted and silently
ignored. This is not a limitation of the abilities — it is where Weglot keeps
the data. See the translated-slug section below.

`weglot-set-api-key` also clears Weglot's two project caches. They are stored
under global keys but hold data for whichever project the credential resolves
to, so without that a rotated key kept reading the previous project's settings.

## Why these are abilities (and what to do in plain PHP)

- **Abilities** — credential management, language config, button settings, URL
  rules, translated slugs, cloud sync, cache state. They wrap the v3 options
  lock, redact credentials in error messages, distinguish "cloud rejected the
  key" from "cloud unreachable", canonicalize language codes to lowercase, and
  sanitize slug inputs against control chars / absolute URLs / path traversal.
- **execute-php** — toggling individual feature flags inside `custom_settings`,
  raw blob inspection, bulk operations that don't fit the partial-update
  ability. See the last section.

## The abilities at a glance

| Ability | Use |
|---|---|
| `weglot-check-setup` | First call. Version, credential, cloud_state, plan, supports_slugs. |
| `weglot-get-settings` | Read settings cluster (`section`: languages / button / advanced / all). |
| `weglot-list-languages` | Catalog of 110+ supported languages with query filter. |
| `weglot-get-status` | Live cloud project status (plan, quota, language limit). |
| `weglot-list-exclude-urls` | List excluded paths/regex rules with `index` for delete. |
| `weglot-set-api-key` | Set or rotate credentials (cloud-validates before persist). |
| `weglot-set-original-language` | Change the source language. Destructive cloud-side. |
| `weglot-add-destination-language` | Add a target language. |
| `weglot-delete-destination-language` | Remove a target language. |
| `weglot-edit-button-settings` | Partial-update of the switcher style. |
| `weglot-add-exclude-url` | Append an exclusion rule (path/regex + scope). |
| `weglot-delete-exclude-url` | Remove an exclusion rule by index (indices shift). |
| `weglot-list-translated-urls` | Flatten custom_urls into `{language, from_slug, to_slug, active}`. |
| `weglot-add-translated-url` | Set a custom slug for a destination language. |
| `weglot-delete-translated-url` | Remove a custom slug. |
| `weglot-push-options-to-cloud` | Publish pending local edits. Re-reads the cloud, overlays only what changed. |
| `weglot-refresh-from-cloud` | Pull settings from cloud, rehydrate local. |
| `weglot-clear-translation-cache` | Drop local transients + in-process user-info cache. |
| `weglot-get-translation-cache-state` | Debug cache presence + remaining TTL. |

## Domain model

### Credentials

Two credentials, normally stored side-by-side in `wp_options`:

- **Public** (`weglot-translate-api_key`) — enough for reads
  (`/projects/owner`, `/projects/settings`).
- **Private** (`weglot-translate-api_key_private`) — required
  for cloud writes (push, glossary, slugs).

A host can instead supply either one through a PHP constant in its server
configuration — `WEGLOT_API_KEY` for the public key, `WEGLOT_API_KEY_PRIVATE`
for the private one — when the installed Weglot supports it. While the constant
is defined, Weglot uses it and ignores the stored option, and so do the
abilities. `check-setup` reports where each key comes from in `api_key_source`
/ `private_key_source` (`constant`, `option`, or null when unset).

`weglot-set-api-key` accepts both. `private_key` is tri-state: omit the field
to keep the current value, pass an empty string to **clear** the stored
private key, pass non-empty to set. The cloud is probed BEFORE persisting; an
invalid key returns `weglot_cloud_key_invalid` and the current credential is
preserved. A key supplied by a constant cannot be changed here: sending it
returns `weglot_credential_from_constant` and saves nothing — the user has to
change the constant where the host defines it. The other key stays writable:
`public_key` is required only while it is stored in the database, so with
`WEGLOT_API_KEY` defined, omit `public_key` and pass just `private_key` to set
or clear the stored private key.

### Settings blob shape

`weglot-get-settings` exposes three clusters (`section`: `languages`, `button`,
`advanced`, or `all`):

```
{
  languages: { source_language, destination_languages: [..],
               auto_switch, auto_switch_fallback },
  button:    { full_name, with_name, is_dropdown, with_flags,
               flag_type: "rectangle_mat"|"shiny"|"circle"|"square",
               custom_css },
  advanced:  { translate_email, translate_amp, translate_search, allow_simple,
               rtl_ltr_style, flag_css, private_mode_enabled }
}
```

API keys are intentionally never returned by `get-settings` — call
`check-setup` to confirm presence (masked) instead.

### Exclude rules

`excluded_paths` is a flat list. Each rule is `{type: "path"|"regex", value,
languages: [...]}`. Empty `languages` applies to every destination; a non-empty
list scopes the rule. `add-exclude-url` REFUSES a slash-wrapped value with no explicit `type`: it is
ambiguous, and both readings break. As a regex the /…/ must go (Weglot adds its
own delimiters, so keeping yours yields a pattern that matches nothing); read
literally, `/^foo$/` is a CONTAIN of that exact string. Pass `type: "regex"` or
`type: "CONTAIN"`. A value with no slashes and no type is CONTAIN, Weglot's own
default. Duplicates of `(type, value, languages)` are rejected.

`list-exclude-urls` never guesses: a rule with no `type` is reported as
CONTAIN, because that is what the engine applies to it. Rules stored in the
legacy string form come back with `active: false` — Weglot's reader only
handles object entries, so they exist in the data and exclude nothing.
`delete-exclude-url` removes by `index` (matches `list-exclude-urls.rules[].index`)
and re-numbers — **delete from highest index downward** when batching, or
re-fetch the list between deletes.

### Translated URL slugs

The abilities speak `{language, from_slug, to_slug}` — original → translated —
and that is the only shape you need. Both slugs must be a SINGLE path segment:
Weglot compares one segment at a time, so `blog/2024/post` (or a trailing
`blog/`) could never match and is refused rather than stored as a rule that
does nothing.

That refusal applies to **creating** a pair, not to naming one that already
exists. A multi-segment pair can still reach the blob from the Weglot
dashboard, a v2→v3 migration or a cloud refresh — so `list-translated-urls`
reports every stored pair with an **`active`** flag, and `delete-translated-url`
accepts a multi-segment `from_slug` so the inert pair can be removed. **Read
`active` before telling anyone a slug is working**: `active: false` means the
page is served untranslated no matter what the pair says.

Slugs are stored without the leading "/";
either input form is normalized. `add-translated-url` replaces any existing
pair for the same source page and returns `previous_to_slug` for rollback. The
destination language must already be configured (see `add-destination-language`).

**These two abilities do not follow the pending-then-publish rule.** Because
the settings payload has no `custom_urls`, Weglot's merge takes the value from
the local store, so a slug written here **is already what this site serves** —
no publish step, and `cloud_sync_pending` is false. The flip side: the Weglot
project never learns about it. Another install of the same project will not
have it, it will not appear on dashboard.weglot.com, and
`weglot-refresh-from-cloud` can discard it. For a slug that must live on the
project, edit it at dashboard.weglot.com.

**Storage note, if you ever inspect the raw option:** Weglot persists this
cluster the other way round, `{language: {translated: original}}`. Its SDK
iterates it as `foreach (... as $translatedURL => $originalURL)` and the plugin
flips the API answer with `array_flip` before caching it. The abilities convert
in both directions; raw reads via execute-php will look inverted, and they are
not.

### Cloud surfaces

- `check-setup` reports `cloud_state`: `ok` / `no_credential` / `rejected`
  (4xx — rotate the key) / `unreachable` (transient — retry later) plus
  `last_cloud_error` for diagnosis.
- `get-status` calls `/projects/owner` for plan / quota / words_used /
  hosted_url.
- `push-options-to-cloud` GETs `/projects/settings`, overlays the pending local
  keys, then POSTs the result back. That endpoint **merges** server-side —
  measured against a live project, and then measured again through the ability
  itself: a push carrying one key left the other 30 byte-identical and dropped
  none. So the read is a safety belt, not a requirement, and a failed read no
  longer blocks publishing. It reports `overlaid_keys` — what it actually sent.
  `cache_busted: true` means the local transient was dropped and the next
  request re-fetches; a read chained inside the SAME request still sees the
  pre-push snapshot, because Weglot short-circuits on an in-process copy it
  checks before the transient. Every ability call is its own request, so
  `push` then `list` reads correctly.
- `refresh-from-cloud` GETs the same endpoint and rehydrates the local store;
  it **discards pending local edits**, so publish before refreshing. The
  cloud-side credential fields are dropped from the merge so a misconfigured /
  hostile cloud cannot rotate the local key via the response, and local-only
  keys the cloud returns empty (`custom_urls`, `menu_switcher`, `flag_css`,
  `active_wc_reload`) are preserved and reported in `preserved_local_keys`.

## Workflows

### Set up a fresh project end-to-end

1. `weglot-check-setup` → confirm active. `api_key_present` likely false.
2. `weglot-set-api-key` { public_key, private_key } — cloud-validates. Leave
   out any key whose `check-setup` source is `constant` (the host already
   supplies it and the ability refuses to change it); skip the call when both
   are constants.
3. `weglot-set-original-language` { language: "en" }.
4. `weglot-add-destination-language` { language: "fr" } (repeat per target).
5. `weglot-edit-button-settings` { is_dropdown: true, with_flags: true }.
6. `weglot-push-options-to-cloud` → propagate to cloud.

### Add a single destination language

1. `weglot-list-languages` { query: "japanese" } → confirm code (`ja`).
2. `weglot-add-destination-language` { language: "ja" }. Watch
   `over_plan_limit` — true means the cloud may refuse.
3. `weglot-push-options-to-cloud`.

### Add a URL exclusion that only applies to French

1. `weglot-add-exclude-url` { value: "/legal/", languages: ["fr"] }.
2. `weglot-push-options-to-cloud`.

### Set a custom slug per language

1. `weglot-add-translated-url` { language: "fr", from_slug: "about-us",
   to_slug: "qui-sommes-nous" }. Response carries `cloud_sync_pending: false`:
   the slug is already what this site serves, and there is nothing to push
   (see the translated-slug section — the Weglot project itself never
   learns about it).

### Pull from the cloud after a dashboard edit

1. Admin edits the project settings at dashboard.weglot.com.
2. `weglot-refresh-from-cloud` → local cache rehydrated. Credentials
   preserved against any cloud-side rotation attempt.

### Diagnose stale local data

1. `weglot-get-translation-cache-state` → check `present` and
   `seconds_remaining` (only meaningful when `ttl_known: true` — false on
   sites using Redis/Memcached).
2. `weglot-clear-translation-cache` → wipe both transients and the
   in-process user-info cache to force a fresh cloud fetch.

## Use execute-php for these (no ability)

Per-feature toggles inside `custom_settings` and one-off raw reads are
`execute-php` recipes. Always merge — never blind-overwrite the whole blob:

```php
// Toggle the AMP translator without touching anything else:
$opt = get_option( 'weglot-translate-v3', [] );
$opt['custom_settings']['translate_amp'] = true;
update_option( 'weglot-translate-v3', $opt, false );

// Inspect the raw blob for debugging:
$opt = get_option( 'weglot-translate-v3', [] );
var_export( $opt['custom_settings'] ?? [] );

// Force the Weglot CDN cache to refresh on next request without calling the cloud:
delete_transient( 'weglot_cache_cdn' );
delete_transient( 'weglot_slugs_cache' );

// Set a single per-language switcher custom name in bulk:
$opt = get_option( 'weglot-translate-v3', [] );
foreach ( $opt['languages'] as &$lang ) {
    if ( $lang['language_to'] === 'fr' ) { $lang['custom_name'] = 'Français'; }
}
unset( $lang );
update_option( 'weglot-translate-v3', $opt, false );
```

Use the `weglot-push-options-to-cloud` ability after any execute-php write —
a local write on its own never reaches visitors, because the cloud wins the
merge Weglot performs on every request.

## Gotchas

- **`cloud_sync_pending: true` on every settings write** (translated slugs
  excepted — see above). The write abilities persist
  locally only, and the local store is NOT what Weglot serves from: the cloud
  wins the merge, so an unpublished edit is inert forever, not merely stale.
  Always close a batch of writes with one `push-options-to-cloud`. Use
  `get-settings.pending_publish` to see what is still unpublished.
- **`set-original-language` is destructive cloud-side.** Changing the source
  language invalidates existing translations on weglot.com. Annotated
  `destructive: true` and `idempotent: false`.
- **Language codes are canonicalized to lowercase.** Stored uppercase entries
  (legacy or imported) are matched case-insensitively by add/delete/list-
  translated-url and add/delete-destination-language. Reads via
  `get-settings` and `check-setup` always return lowercase.
- **Slug sanitization rejects** control chars, absolute URLs / scheme-like
  prefixes, and `..` path-traversal segments. An invalid slug returns
  `weglot_invalid_input` with a sanitization note.
- **`cloud_state: rejected` vs `unreachable`.** Rejected = the key is bad,
  ask the user to rotate. Unreachable = transient (5xx / network), retry
  later. Both carry `last_cloud_error.code / .message` for diagnosis.
- **`push-options-to-cloud` HTTP 200 ≠ success.** The upstream endpoint can
  reply `{success: false, code: "…"}` even with 200; the ability maps that
  to `weglot_cloud_push_rejected`.
- **`ttl_known: false` on object-cache sites.** Redis / Memcached do not
  populate `_transient_timeout_*` in wp_options, so cache-state cannot
  report `seconds_remaining`. The `present` flag still works.
- **Lock contention during push.** A push holds the lock for up to ~90 s
  (HTTP timeout 60 s + margin). Concurrent writes return `weglot_lock_busy`
  for up to ~2 s before giving up.
- **Cross-actor lost-write.** Weglot's own admin save path writes to the
  same v3 option WITHOUT our lock. If the admin clicks "Save" in the Weglot
  settings page mid-ability-write, the last writer wins. The ability lock
  (with the new CAS owner-token release) protects against ability-vs-ability
  races but is structurally unable to cover ability-vs-admin. When a user is
  actively editing in the Weglot UI, ask them to refrain from saving until
  the agent's batch + `weglot-push-options-to-cloud` completes — or call
  `weglot-refresh-from-cloud` first to recover whatever they just saved.

## Conventions

- Slugs: `novamira/weglot-<verb>-<object>`. Reads are `get-*` / `list-*`
  (readonly); writes are `set-*` (full replace), `add-*` / `delete-*`
  (collection mutator), `edit-*` (partial merge). Cloud-touching ability
  names contain `cloud` only when the local + cloud distinction matters
  (`push-options-to-cloud`, `refresh-from-cloud`, `get-translation-cache-state`).
- All write paths hold `wg_with_options_lock` and read raw cluster shapes
  via `wg_custom_urls_raw` / `wg_options_raw` to preserve unrelated entries
  other actors may have left behind.
- Error codes: `weglot_not_active`, `weglot_no_api_key`,
  `weglot_credential_from_constant`, `weglot_private_key_invalid`,
  `weglot_invalid_input`, `weglot_unknown_language`, `weglot_unknown_index`,
  `weglot_unknown_slug`, `weglot_lock_busy`, `weglot_options_changed`,
  `weglot_config_unavailable`, `weglot_cloud_key_invalid`,
  `weglot_cloud_unreachable`, `weglot_cloud_push_rejected`,
  `weglot_cloud_http_<code>`, `weglot_cloud_transport`,
  `weglot_cloud_invalid_json`, `weglot_cloud_invalid_shape`,
  `weglot_cloud_soft_fail` (credential probes; reported in
  `check-setup.last_cloud_error`), `weglot_json_encode_fail`.
