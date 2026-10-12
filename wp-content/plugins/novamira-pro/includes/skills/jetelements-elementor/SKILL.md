---
name: jetelements-elementor
description: JetElements (Crocoblock) widgets in Elementor, on two axes. PLACING or STYLING: activate when the user names JetElements, Jet Elements or Crocoblock, or when the page holds a widget whose Elementor type starts with jet- (jet-carousel, jet-map, jet-weather, jet-pricing-table…). DIAGNOSING: activate whenever a jet- widget has disappeared from an Elementor editor, or a page that held jet- widgets now looks empty or truncated — switching these widgets off in the plugin settings removes them from Elementor and can blank a whole page while the content stays saved. Labels such as Weather or Pricing Table are not unique to JetElements: confirm the label maps to a jet- widget before placing.
---

# JetElements in Elementor

## Boundaries

- Composing, styling, v3 vs v4 — `elementor-build-page`. Dynamic data and query
  loops — `dynamic-data-binding`.
- **JetEngine** is a different Crocoblock plugin (post types, meta boxes, CCT,
  relations). If the request is about data modelling, this is the wrong skill.
- These widgets are **v3, not v4 atomic**: put them in a v3 `container`, since
  mixing them into a v4 `e-flexbox` tree produces a broken hybrid.

## Before diagnosing anything

Confirm the widget really is a `jet-*` **present in that post's
`_elementor_data`**. If it is not, or the post is not an Elementor page, there is
nothing here to diagnose — many packs ship a Map, a Weather, a Pricing Table.

When the user already names the cause — a revision, an autosave, a restored
version, a missing template, a permission error — that is the cause to work on,
even though the page looks empty the same way.

## Finding its widgets

Everything this plugin adds registers under **one category slug: `cherry`** —
not "jet", not "jetelements", not derivable from the name. Filter Elementor's
registry by that and you have its whole surface.

**How to query the registry is not described here.** Reading schemas, composing,
and every other Elementor mechanic belong to `elementor-build-page` and to the
schema ability's own description. Take them from there: that contract changes
often, and a copy of it in this file would go stale without anyone noticing.

**In the editor that category reads "JetElements".** Never send a user looking
for "cherry".

**The category is the only filter that isolates it.** JetElements declares its
classes inside the `Elementor\` namespace, so its widgets report the same source
as the core ones. The `jet-` prefix does not isolate it either: two vendor
widgets carry it too (`jet-booked-appointments`, `jet-booked-calendar`), and
being gated on Booked rather than on the widget map, diagnosing those as
"switched off" would be wrong.

## Why a widget is not there

A widget switched off **does not exist as far as Elementor is concerned**: not
in the registry, no schema, not insertable. Never report "this widget does not
exist" before checking the causes below.

**A page built with it looks empty, and it is not.** The canvas shows
"drag a widget here" and the Structure panel reports no content, while
`_elementor_data` still holds every widget and setting. Nothing is lost —
switching the widget back on restores the page exactly. **Do not rebuild it, and
do not save it in that state**: saving is what turns this into real data loss.

**Switching it back on is not the last step.** Elementor Pro stores the rendered
HTML in the `_elementor_element_cache` post meta, and the empty render sits there
with a future timeout, so the page can still come back empty after the widget is
registered again. Finish with
`novamira/elementor-clear-document-cache { "post_ids": [<id>] }`, or the fix
looks like it did not work.

Causes, in order:

1. JetElements is not active, or is older than the release that added that widget.
2. **Switched off in the plugin settings** — read it with the snippet below.
3. It is a vendor widget and its host plugin is missing (WooCommerce, Contact
   Form 7, SmartSlider3, Booked, MP Timetable).
4. **It is registered and the user just cannot find it** — the most common
   outcome once 1-3 are excluded. Give them the UI label and the panel category
   ("JetElements"), never the internal slug.

```php
// novamira/execute-php — which widgets are switched off.
// A widget is on when its key is truthy, or when no map is stored at all.
// Guard first: this reads an option and a directory, both of which answer just as
// confidently on a site with no JetElements — where the branches below would
// report "every widget on" about widgets that do not exist.
if (!class_exists('Jet_Elements')) { return ['plugin_absent' => true]; }
$row = get_option('jet-elements-settings', []);
$map = is_array($row) ? ($row['avaliable_widgets'] ?? null) : null;
$all = array_map(static fn($f) => basename($f, '.php'),
    glob(WP_PLUGIN_DIR . '/jet-elements/includes/addons/*.php'));

// ! $map covers null, [], '', 0, false — an EMPTY ARRAY means every widget ON.
if (!$map) {
    return ['every_widget_on' => true, 'reason' => 'nothing stored, or a falsy value'];
}
// A truthy non-array yields false for every slug with no escape: NOTHING runs.
if (!is_array($map)) {
    return ['every_widget_off' => true, 'reason' => 'the stored value is a ' . gettype($map)
        . ', which registers no widget at all', 'repair_needed' => true];
}
// A non-empty map is authoritative: absent means off.
$off = [];
foreach ($all as $key) {
    if (!filter_var($map[$key] ?? false, FILTER_VALIDATE_BOOLEAN)) { $off[] = $key; }
}
return ['on' => count($all) - count($off), 'off' => $off];
```

The key is spelled **`avaliable_widgets`**, without the second "i". Read and
write it exactly as it is: the corrected spelling is a key the plugin never
writes, and reading that one reports every widget as on. To switch one back on: **Crocoblock → JetPlugins Settings →
JetElements → Widgets & Extensions**.

## Three names per widget, none derivable from the others

| what | example |
|---|---|
| addon file | `jet-elements-advanced-carousel.php` |
| key in the settings map | `jet-elements-advanced-carousel` |
| **name Elementor knows** | **`jet-carousel`** |
| label in the editor | `Advanced Carousel` |

`jet-advanced-carousel` exists nowhere; the Elementor registry name is
`jet-carousel`, while the settings key belongs only in the map. Use that registry
name only where the target ability's input schema documents a widget identifier,
whether at the top level or inside a nested widget element such as `set-content`'s
`content` or `add-element`'s `tree`; if it documents none, do not add the name.
The family schemas show top-level `widget_type` and `widget_types`; inside
`set-content`'s `content` a widget element takes `widget_type` or `widgetType`,
and inside `add-element`'s `tree` it takes `widgetType`.

## Never write a partial widget map

JetElements registers a widget when its key is truthy **or when the whole map is
falsy**: nothing stored means every widget on, but once the map is non-empty
**any widget missing from it is off**.

Storing one key alone removes **every** widget from the registry, and on a site
with no host plugins the `cherry` category disappears with them. On a site
running WooCommerce or Booked it does not — the vendor widgets are in the same
category and are not governed by this map, so `cherry` stays populated and the
symptom is subtler. Write the complete map or none.

The keys are the addon filenames, and this is the only way to obtain them:

```php
// novamira/execute-php — rewrite ONE widget without destroying anything else
// Guard first, and here it matters most: without JetElements get_option returns the
// [] default, which IS an array, so the check below passes and this would CREATE a
// bogus jet-elements-settings row on a site that has no JetElements at all.
if (!class_exists('Jet_Elements')) { return ['plugin_absent' => true]; }
$row = get_option('jet-elements-settings', []);
if (!is_array($row)) { return ['refused' => 'the option is not an array']; }

$map = is_array($row['avaliable_widgets'] ?? null) ? $row['avaliable_widgets'] : [];
foreach (glob(WP_PLUGIN_DIR . '/jet-elements/includes/addons/*.php') as $f) {
    $key = basename($f, '.php');
    // Values are the STRINGS 'true' / 'false'. Absent keys default to on,
    // matching the state a site has before anything is saved.
    $map[$key] = filter_var($map[$key] ?? true, FILTER_VALIDATE_BOOLEAN) ? 'true' : 'false';
}
$map['jet-elements-weather'] = 'false';       // the one change

$row['avaliable_widgets'] = $map;             // put it back INTO THE ROW,
update_option('jet-elements-settings', $row); // and write the ROW, not the map
return ['written' => count($map), 'kept' => array_keys($row)];
```

**Write the row, not the map.** `update_option('jet-elements-settings', $map)`
replaces the option with the bare map: every credential and
`avaliable_extensions` is silently deleted, and the widget you meant to switch
off stays on, because the map now sits at the wrong depth.

Only files directly in `includes/addons/` are switchable; `addons/vendor/` is
gated on host plugins and never appears in the map.

## Service-backed widgets: each one fails differently

Do not expect a single symptom — the four behave in four ways, and knowing which
tells you where to look.

| widget | needs | symptom when missing |
|---|---|---|
| `jet-map` | `map_provider` + its key: `api_key` (Google) or `mapbox_access_token`; Leaflet needs none | **blank, no message anywhere** — the Maps script is never enqueued |
| `jet-weather` | the key for the provider set **on the widget** (`weather_api_provider`, default `weatherbit`): `weather_api_key`, or `openweathermap_api_key` for that provider | **prints a visible notice**: "Please set Weather API key…". That notice IS the diagnosis |
| `jet-instagram-gallery` | `insta_access_token`, or a per-widget token (`access_token_source: custom`); the hashtag endpoint also needs an **activated Crocoblock licence** | prints "Please, enter Access Token." **only to logged-in users** |
| `jet-subscribe-form` | `mailchimp-api-key` and `mailchimp-list-id` (hyphens here, underscores elsewhere); the list id can be overridden per widget | **the form renders fine** and fails at submit |

A blank area points at `jet-map`, a visible notice at Weather, a form that fails
on submit at Mailchimp. "Renders empty" for all four misdirects the search.

They are top-level keys of the same option; the snippet above reads only
`avaliable_widgets` and will **not** find them:

```php
// novamira/execute-php — credential presence only, never values
// Guard first: without JetElements every key below reports false, which reads as
// "the credentials are missing" rather than "this plugin is not here".
if (!class_exists('Jet_Elements')) { return ['plugin_absent' => true]; }
$row = get_option('jet-elements-settings', []);
$out = ['map_provider' => is_array($row) ? ($row['map_provider'] ?? '(default)') : '(unreadable)'];
foreach (['api_key', 'mapbox_access_token', 'weather_api_key', 'openweathermap_api_key',
          'insta_access_token', 'insta_business_access_token', 'insta_business_user_id',
          'mailchimp-api-key'] as $k) {
    $out[$k] = is_array($row) && is_string($row[$k] ?? null) && trim($row[$k]) !== '';
}
return $out;
```

**Never echo a credential value** back to the user or into the conversation —
report presence only.

`disable_api_js` is stored as `['disable' => 'true'|'false']`, not a boolean.
When `'true'`, `jet-map` renders empty on Google **even with a valid key** — the
one cause that survives a credential check.

## Two things Elementor cannot report

**Section Parallax** adds controls to Elementor's own container under
`avaliable_extensions`, a key with the OPPOSITE rule to the widget map: indexed
with no `isset` and no `! $map` escape, so a stored map omitting
`section_parallax` raises `Undefined array key` on every request that loads the
plugin. `jet_parallax_layout_list` in a container's schema comes from there, and
can vanish without the container changing.

**`widgets_load_level`** (0/25/50/75/100, default 100, same option) suppresses
controls at registration: below the default a widget exposes fewer controls than
it really has, while a widget that does not use that wrapper is unaffected. A
short schema is not proof a control does not exist.

## Writing these settings

One shared `wp_options` row: read it, change only what you mean to, write it all
back. The procedures above exist because a partial write is destructive, not as
an invitation: switching widgets is the user's job from the plugin panel, and
credentials must be typed there, never passed inline.
