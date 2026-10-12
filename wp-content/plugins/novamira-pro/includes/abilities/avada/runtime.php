<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Avada;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime for the Avada theme-side ability layer.
 *
 * The availability gate itself (`avada_theme_available()`) lives in
 * `includes/gates.php` alongside every other specialization gate so the
 * manifest entry + the loader can share one implementation. This file
 * exposes the narrow output-shaping helpers the theme-side abilities need:
 * version accessor, Fusion Settings snapshot, integer coercion with a
 * clamp, byte-cap truncation for large fields, and per-family map shapes
 * for `AWB_Global_Colors` / `AWB_Global_Typography`.
 *
 * The Fusion Builder plugin surface (element tree CRUD, Template Builder,
 * Fusion Forms) is intentionally NOT touched here — its runtime lives in
 * a separate sub-loader that is not wired in the theme-only phase.
 */

/**
 * Default byte cap for large text fields inside ability output (raw CSS,
 * raw JS, custom shortcode dumps). Callers opt out with `max_field_bytes: 0`.
 */
const AVADA_DEFAULT_MAX_FIELD_BYTES = 20_000;

/**
 * `fusion_options` is the single row every theme-side setting lives inside.
 * Multilingual installs suffix it (`fusion_options_<lang>`) — those are read
 * via `execute-php` recipes because the language switch is caller-scoped.
 */
const AVADA_OPTIONS_KEY = 'fusion_options';

/**
 * CPT slugs the theme registers on its own (independent of Fusion Builder
 * plugin). Kept as one canonical list so the ability enumerators do not
 * drift.
 */
const AVADA_CPT_PORTFOLIO = 'avada_portfolio';

const AVADA_CPT_FAQ = 'avada_faq';

/**
 * CPT slug of a single Fusion Slider slide. Registered by the Fusion Core
 * companion plugin (not the theme) — abilities that touch this must
 * additionally gate on `fusion_core_available()`.
 */
const AVADA_CPT_SLIDE = 'slide';

/**
 * Hierarchical taxonomy registered by Fusion Core that groups slides into
 * "sliders". One term of this taxonomy IS one slider — the term's
 * `_fusion` term_meta holds the slider's display / autoplay / animation
 * settings, and the slides assigned to the term make up the deck.
 */
const AVADA_TAX_SLIDER = 'slide-page';

/**
 * The single postmeta / termmeta key that Fusion Core (via the theme's
 * fusion-library `Fusion_Data_*` wrappers) uses as the storage envelope
 * for BOTH slide field bags and slider setting bags. Everything a Fusion
 * Slider surface reads or writes lives inside one serialised array
 * behind this key — heading/caption/type/video for slides, dimensions/
 * animation/autoplay/nav for sliders. The theme's `Fusion_Data_*` classes
 * back this up (they set `const ROOT = '_fusion'` in each subclass), so
 * writing here from our ability layer via `update_post_meta` /
 * `update_term_meta` is behaviourally identical to `fusion_data(...)->set()`.
 */
const AVADA_FUSION_META_KEY = '_fusion';

/**
 * Seconds after which a held `fusion_options` row lock is considered
 * orphaned and stealable — matches Divi's global-data lock TTL. A writer
 * that dies mid-request without releasing leaves a stale row; the next
 * acquire attempt steals it once older than this threshold instead of
 * spinning until timeout.
 */
const NOVAMIRA_AVADA_LOCK_TTL = 30;

/**
 * Total seconds a contended lock acquire spins before giving up and
 * proceeding unlocked — availability over the small residual race, same
 * trade-off Divi's global-data lock makes.
 */
const NOVAMIRA_AVADA_LOCK_WAIT = 5.0;

/**
 * Live Avada theme version, or empty string when the theme is inactive.
 * Reads the `AVADA_VERSION` constant defined in `Avada/functions.php`.
 *
 * Multisite gotcha: `AVADA_VERSION` is a PHP constant, so it stays defined
 * for the rest of the request even after `switch_to_blog()` moves to a
 * subsite that does NOT run Avada. Callers that need per-blog accuracy in
 * a multisite context should probe `wp_get_theme()->get('Name') === 'Avada'`
 * instead — the ability layer intentionally trusts the constant because
 * every ability call is scoped to a single blog on entry.
 */
function avada_version(): string
{
    return defined('AVADA_VERSION') ? (string) constant('AVADA_VERSION') : '';
}

/**
 * Byte-safe truncation for arbitrary strings inside ability output. Returns
 * `[$truncated_string, $was_truncated]`. When `$max_bytes` is zero the
 * string passes through untouched (opt-out).
 *
 * @return array{0: string, 1: bool}
 */
function avada_truncate_body(string $body, int $max_bytes = AVADA_DEFAULT_MAX_FIELD_BYTES): array
{
    if ($max_bytes <= 0) {
        return [$body, false];
    }
    if (strlen($body) <= $max_bytes) {
        return [$body, false];
    }
    // mb_strcut is byte-safe on UTF-8 (never splits mid-codepoint).
    return [mb_strcut($body, start: 0, length: $max_bytes), true];
}

/**
 * Clamp a caller-supplied value to a non-negative integer, with an optional
 * upper cap. Values that cannot be cast return the minimum — the schema
 * layer already rejects non-integer inputs so this is defense-in-depth.
 */
function avada_coerce_int(mixed $value, int $minimum = 0, ?int $maximum = null): int
{
    $out = match (true) {
        is_int($value) => $value,
        is_numeric($value) => (int) $value,
        default => $minimum,
    };
    if ($out < $minimum) {
        $out = $minimum;
    }
    if ($maximum !== null && $out > $maximum) {
        $out = $maximum;
    }
    return $out;
}

/**
 * Whether the `fusion_options` option row exists in the database. Queries
 * `wp_options` directly with `SELECT option_id` to avoid the
 * `pre_option_*` / `option_*` filter chain: caching layers (LiteSpeed,
 * some MU-plugins used in test harnesses) return `null` from
 * `pre_option_*` as a legitimate "stub for tests" pattern, and that made
 * `get_option(default_value: null)` misreport a fully-configured site as
 * "fresh install" (adversarial audit A1 finding B). The direct query
 * bypasses that vector — the only signal we consume is the row's
 * physical presence, not its value.
 */
function avada_options_row_exists(): bool
{
    global $wpdb;
    /** @var \wpdb $wpdb */
    /** @var mixed $row */
    // @mago-expect analysis:possibly-invalid-argument
    $row = $wpdb->get_var((string) $wpdb->prepare(
        "SELECT option_id FROM {$wpdb->options} WHERE option_name = %s LIMIT 1",
        AVADA_OPTIONS_KEY,
    ));
    return $row !== null;
}

/**
 * Compact snapshot of the `fusion_options` row. Returns the raw options
 * array (or empty when the row is missing) — callers layer their own
 * per-tab grouping / byte cap on top. Never triggers Fusion Library boot
 * as `get_option` reads directly from the object cache / DB.
 *
 * @return array<string, mixed>
 */
function fusion_settings_snapshot(): array
{
    /** @var mixed $raw */
    $raw = get_option(AVADA_OPTIONS_KEY, default_value: []);
    if (!is_array($raw)) {
        return [];
    }
    /** @var array<string, mixed> $out */
    $out = [];
    /** @var mixed $value */
    foreach ($raw as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * Normalise a single AWB Global Color entry into the ability output shape:
 * `{ slug, label, color }`. Live palette shape (probed against Avada 7.15.5):
 * `[ 'color1' => [ 'label' => 'Color 1', 'color' => 'rgba(255,255,255,1)' ], ... ]`.
 * The slug IS the array key — this helper takes both so the caller doesn't
 * have to unpack.
 *
 * @param array<string, mixed> $entry
 */
function awb_color_map(string $slug, array $entry): array
{
    $label = isset($entry['label']) && is_string($entry['label']) ? $entry['label'] : '';
    $color = isset($entry['color']) && is_string($entry['color']) ? $entry['color'] : '';
    return ['slug' => $slug, 'label' => $label, 'color' => $color];
}

/**
 * Normalise a single AWB Global Typography entry into the ability output
 * shape: {slug, label, family, size, weight, lineHeight, letterSpacing,
 * textTransform}. Live shape (probed against Avada 7.15.5) has these
 * storage keys: label, font-family, font-size, variant, font-weight,
 * line-height, letter-spacing, text-transform, plus optional not_removable.
 * The slug IS the array key. Missing keys default to the empty string.
 *
 * @param array<string, mixed> $entry
 */
function awb_typography_map(string $slug, array $entry): array
{
    $label = isset($entry['label']) && is_string($entry['label']) ? $entry['label'] : '';
    $family = isset($entry['font-family']) && is_string($entry['font-family']) ? $entry['font-family'] : '';
    $size = isset($entry['font-size']) && is_string($entry['font-size']) ? $entry['font-size'] : '';
    $weight = isset($entry['font-weight']) && is_string($entry['font-weight']) ? $entry['font-weight'] : '';
    $line_height = isset($entry['line-height']) && is_string($entry['line-height']) ? $entry['line-height'] : '';
    $letter_spacing = isset($entry['letter-spacing']) && is_string($entry['letter-spacing'])
        ? $entry['letter-spacing']
        : '';
    $text_transform = isset($entry['text-transform']) && is_string($entry['text-transform'])
        ? $entry['text-transform']
        : '';
    return [
        'slug' => $slug,
        'label' => $label,
        'family' => $family,
        'size' => $size,
        'weight' => $weight,
        'lineHeight' => $line_height,
        'letterSpacing' => $letter_spacing,
        'textTransform' => $text_transform,
    ];
}

/**
 * Whitelist of `fusion_options` keys that `set-theme-options` can write.
 * Curated conservatively: excludes anything that stores raw CSS/JS
 * (`custom_css`, `custom_js`, per-page CSS/JS), anything that ties to
 * license state, and anything Avada Studio owns (imports, prebuilt data).
 *
 * @return list<string>
 */
function avada_writable_setting_keys(): array
{
    return [
        // Colors (single-value keys — palette live in `color_palette`).
        'primary_color',
        'secondary_color',
        'body_typography_color',
        'link_color',
        'link_hover_color',
        'sep_color',
        'main_border_color',
        // Layout.
        'layout',
        'site_width',
        'main_padding',
        // Header.
        'header_position',
        'header_layout',
        'header_sticky',
        // Footer.
        'footer_area_columns',
        'footer_area_display',
        // Extras.
        'status_lightbox',
        'status_gmap',
        'status_totop',
        'status_totop_mobile',
        'preloader_activate',
    ];
}

/**
 * Whether a `fusion_options` key is safe to write through the ability
 * layer. Reserved keys (raw CSS/JS, license fields, custom_fonts blob)
 * are always rejected — even the whitelist above is checked against
 * this stop-list first so an accidental future append cannot leak a
 * dangerous key.
 *
 * The `nvp_license_*` entries are Novamira's own license-storage
 * namespace and would never be a legitimate `fusion_options` key on
 * their own. They are on this stop-list as defense-in-depth: if any
 * future ability author (or a caller pushing an unexpected batch
 * through `set-theme-options`) tried to persist a Novamira license
 * field into Avada's option row, the write is rejected before it can
 * leak into a public theme-configuration blob. Intentionally paranoid.
 */
function avada_is_reserved_setting_key(string $key): bool
{
    return in_array(
        $key,
        [
            'custom_css',
            'custom_js',
            'space_custom_css',
            'custom_js_header',
            'custom_js_body',
            'custom_js_footer',
            'nvp_license_key',
            'nvp_license_status',
        ],
        strict: true,
    );
}

/**
 * Sanitise a caller-supplied label field before persisting to
 * `fusion_options`. WordPress does NOT auto-escape option values on
 * read, so any tags stored here render verbatim in the theme's UI +
 * dynamic-CSS pipeline. Defense-in-depth: strip HTML + kill invalid
 * UTF-8 sequences before write (malformed-input audit A2 F1).
 */
function avada_sanitize_label(string $value): string
{
    return sanitize_text_field(wp_check_invalid_utf8($value));
}

/**
 * Sanitise a caller-supplied color string. Accepts either the CSS hex
 * form (`#rgb` / `#rrggbb` / `#rrggbbaa`) or the `rgba(...)` / `rgb(...)`
 * / `hsl(...)` / `hsla(...)` functional form (Avada palette values are
 * `rgba(...)` in practice). Rejects anything with angle brackets,
 * semicolons, or backslashes — those would break out of the CSS custom
 * property Avada emits with the value inlined. Returns `null` when the
 * value cannot be normalised safely.
 */
function avada_sanitize_color(string $value): ?string
{
    $trimmed = trim(wp_check_invalid_utf8($value));
    if ($trimmed === '') {
        return null;
    }
    // Hex form: delegate to WP's built-in validator (returns '' on invalid).
    if ($trimmed[0] === '#') {
        $hex = sanitize_hex_color($trimmed);
        return is_string($hex) && $hex !== '' ? $hex : null;
    }
    // Functional form: allow rgba/rgb/hsl/hsla/currentColor/transparent
    // and named CSS colors. Reject any character that could terminate
    // the CSS declaration or open a new one.
    if (preg_match('/[<>;{}\\\\"\']/', $trimmed)) {
        return null;
    }
    return $trimmed;
}

/**
 * Sanitise a caller-supplied typography field (family / size / weight /
 * lineHeight / letterSpacing / textTransform). Strips tags and any
 * character that could terminate the CSS declaration.
 */
function avada_sanitize_typography_field(string $value): ?string
{
    $clean = trim(sanitize_text_field(wp_check_invalid_utf8($value)));
    if (preg_match('/[<>;{}\\\\]/', $clean)) {
        return null;
    }
    return $clean;
}

/**
 * Sanitise a scalar theme-option value. Callers pass whitelisted keys
 * whose values are always CSS-adjacent scalars, so we strip tags + kill
 * invalid UTF-8 for strings, and pass ints/floats/bools through untouched.
 * Returns `null` when a string cannot be normalised safely (`<` / `>` /
 * unpaired `{}`) to reject stored-XSS attempts.
 */
function avada_sanitize_theme_option_value(mixed $value): mixed
{
    if (is_bool($value) || is_int($value) || is_float($value)) {
        return $value;
    }
    if (!is_string($value)) {
        return null;
    }
    $clean = wp_check_invalid_utf8($value);
    // Strip HTML tags at the boundary — theme options are CSS/config, not
    // markup. Anything containing angle brackets or semicolons is refused
    // because it could break out of the CSS/JS emission context.
    if (preg_match('/[<>]/', $clean)) {
        return null;
    }
    return sanitize_text_field($clean);
}

/**
 * Whitelist of slide `_fusion` postmeta keys that `edit-slide` can write,
 * mapped to the type each key expects. The type drives the sanitizer in
 * {@see avada_sanitize_slide_value()}: `string` passes through
 * `avada_sanitize_label()`, `url` through `esc_url_raw`, `color` through
 * `avada_sanitize_color()`, `bool` coerces to '1' / '0' (Fusion Slider
 * stores checkboxes as string literals in `_fusion`), `int` coerces to
 * a positive integer, and `enum:x|y|z` accepts only listed literals.
 *
 * Deliberately EXCLUDED (never writable through the ability layer):
 *   - `preview_image`, `button_1`, `button_2` — the theme stores these as
 *     nested arrays / JSON blobs; writing them safely needs their own
 *     dedicated ability, not this generic bag writer
 *   - Anything that renders raw HTML/JS into the front-end (there is
 *     nothing of that shape in the slide `_fusion` bag today; kept as a
 *     placeholder for future migrations)
 *
 * @return array<string, string>
 */
function avada_writable_slide_keys(): array
{
    return [
        // Slide type + media routing.
        'type' => 'enum:image|youtube|vimeo|mp4',
        // aspect_ratio lands in a data-attribute + JS-driven style — restrict
        // to the WxH shape callers actually mean (audit A3 F5).
        'aspect_ratio' => 'aspect-ratio',
        // youtube_id / vimeo_id land in iframe src path — pattern-lock so
        // callers can't smuggle URL params (audit A3 F-J).
        'youtube_id' => 'youtube-id',
        'vimeo_id' => 'vimeo-id',
        // Media URLs (http/https only — audit A3 F-B for empty-string
        // silent-drop, F6 for data: schemes on media slots).
        'mp4' => 'url',
        'webm' => 'url',
        'ogv' => 'url',
        'video_display' => 'enum:cover|contain',
        'video_bg_color' => 'color',
        'mute_video' => 'bool',
        'autoplay_video' => 'bool',
        'loop_video' => 'bool',
        'hide_video_controls' => 'bool',
        // Layout of the caption block.
        'content_alignment' => 'enum:left|center|right',
        // Heading typography + background. heading/caption bodies pass through
        // `do_shortcode()` at render time — strip shortcodes at the boundary
        // (audit A3 F-C — stored shortcode injection).
        'heading' => 'plain-text',
        'heading_separator' => 'bool',
        'heading_size' => 'enum:1|2|3|4|5|6',
        // heading_font_size lands as a shortcode attribute value inside
        // `[fusion_title font_size="..."]` — quote-escape via css-length
        // (audit A3 F-D — shortcode attribute injection).
        'heading_font_size' => 'css-length',
        'heading_color' => 'color',
        'heading_bg' => 'bool',
        'heading_bg_color' => 'color',
        // Caption typography + background (same shortcode-body rendering).
        'caption' => 'plain-text',
        'caption_separator' => 'bool',
        'caption_size' => 'enum:1|2|3|4|5|6',
        'caption_font_size' => 'css-length',
        'caption_color' => 'color',
        'caption_bg' => 'bool',
        'caption_bg_color' => 'color',
        // Whole-slide link routing.
        'link_type' => 'enum:button|image|none',
        'slide_link' => 'url',
        'slide_target' => 'enum:_self|_blank',
    ];
}

/**
 * Whitelist of slider `_fusion` term_meta keys that
 * `edit-slider-settings` can write, mapped to type (same semantics as
 * {@see avada_writable_slide_keys()}). Setting keys not in this map are
 * rejected with `avada_unknown_setting`.
 *
 * @return array<string, string>
 */
function avada_writable_slider_setting_keys(): array
{
    return [
        // Dimensions land in `style="height:XXX;max-width:YYY"` at render
        // time — restrict to CSS-length shape so a caller can't inject
        // `;background:url(...)` (audit A3 F-I).
        'slider_width' => 'css-length',
        'slider_height' => 'css-length',
        'slider_content_width' => 'css-length',
        'full_screen' => 'bool',
        'parallax' => 'bool',
        // Navigation.
        'nav_arrows' => 'bool',
        'nav_arrow_size' => 'int',
        'nav_box_width' => 'int',
        'nav_box_height' => 'int',
        'slider_indicator' => 'enum:|scroll_down_indicator|pagination_circles',
        'slider_indicator_color' => 'color',
        // Autoplay + loop.
        'autoplay' => 'bool',
        'loop' => 'bool',
        'slideshow_speed' => 'int',
        'animation' => 'enum:fade|slide',
        'animation_speed' => 'int',
        // Order.
        'orderby' => 'enum:date|ID|title|modified|rand',
        'order' => 'enum:ASC|DESC',
        // Responsive typography — also lands in inline CSS custom properties.
        'typo_sensitivity' => 'css-length',
        'typo_factor' => 'css-length',
    ];
}

/**
 * Sanitize a caller-supplied slide/slider value against its declared type.
 * Returns the cleaned value or `null` when the value cannot be normalised
 * safely — the caller then rejects the whole batch (all-or-nothing writes,
 * same pattern as `set-theme-options`).
 *
 * `$type` is one of: `string`, `url`, `color`, `bool`, `int`, or
 * `enum:a|b|c` (pipe-separated literals; empty literal accepted when the
 * enum starts or contains `|| |`).
 */
function avada_sanitize_slide_value(string $type, mixed $value): mixed
{
    if (str_starts_with($type, 'enum:')) {
        return avada_sanitize_enum_value($type, $value);
    }
    return match ($type) {
        'string' => is_string($value) ? avada_sanitize_label($value) : null,
        'plain-text' => avada_sanitize_plain_text($value),
        'url' => avada_sanitize_url_field($value),
        'color' => is_string($value) ? avada_sanitize_color($value) : null,
        'bool' => avada_sanitize_slider_bool($value),
        'int' => avada_sanitize_slider_int($value),
        'css-length' => avada_sanitize_css_length($value),
        'youtube-id' => avada_sanitize_video_id($value, pattern: '/^[A-Za-z0-9_\-]{6,32}$/'),
        'vimeo-id' => avada_sanitize_video_id($value, pattern: '/^\d{6,15}$/'),
        'aspect-ratio' => avada_sanitize_aspect_ratio($value),
        default => null,
    };
}

/**
 * Free-text field that will be piped through `do_shortcode()` at render
 * time (heading / caption in Fusion Slider). Strip HTML tags AND
 * shortcode markers — a caller can't otherwise smuggle
 * `[fusion_code]&lt;iframe&gt;` payloads into the rendered slide (audit A3
 * F-C — stored shortcode injection).
 */
function avada_sanitize_plain_text(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $clean = strip_shortcodes(wp_check_invalid_utf8($value));
    return sanitize_text_field($clean);
}

/**
 * URL field that lands in an `href=` / `src=` attribute at render time.
 * `esc_url_raw` silently drops disallowed schemes to `''`, which would
 * make `values.mp4 = 'javascript:alert(1)'` come back as an empty
 * string persisted to the bag (audit A3 F-B). Explicitly reject: an
 * empty output from a non-empty input means the scheme was blocked
 * and the caller should hear about it. Only `http`/`https` schemes
 * accepted for media (data: / feed: / mailto: / tel: are refused on
 * the media-URL slots).
 */
function avada_sanitize_url_field(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }
    $clean = esc_url_raw($trimmed);
    if ($clean === '') {
        return null;
    }
    /** @var mixed $scheme */
    $scheme = wp_parse_url($clean, PHP_URL_SCHEME);
    if (!is_string($scheme) || !in_array($scheme, ['http', 'https'], strict: true)) {
        return null;
    }
    return $clean;
}

/**
 * CSS-length field that lands in a `style="width:XXX;height:YYY;"` at
 * render time. `esc_attr` at the render layer blocks attribute-breakout
 * but does NOT block CSS injection — `slider_width = "1200px;background:
 * url(evil.com)"` still gets echoed verbatim inside the style attribute
 * (audit A3 F-I). Reject any character that could terminate a CSS
 * declaration.
 */
function avada_sanitize_css_length(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $trimmed = trim(wp_check_invalid_utf8($value));
    if ($trimmed === '') {
        return '';
    }
    // Allow: digits, decimal, unit suffix (px em rem % vh vw), calc()/clamp()
    // with limited whitespace + arithmetic, and the literal 'auto'/'none'.
    // Reject: `;` `:` `{` `}` `<` `>` `\` `"` `'` — the CSS injection surface.
    if (preg_match('/[;:{}<>\\\\"\']/', $trimmed)) {
        return null;
    }
    // Positive allowlist: numeric or numeric+unit, keyword, or a
    // paren-scoped calc()/clamp() expression using [\d\s+\-*/,%.()a-z].
    if (!preg_match(
        '/^(?:auto|none|[0-9]+(?:\.[0-9]+)?(?:px|em|rem|%|vh|vw)?|(?:calc|clamp)\([\d\s+\-*\/,.%()a-z]+\))$/i',
        $trimmed,
    )) {
        return null;
    }
    return $trimmed;
}

/**
 * YouTube / Vimeo id field that lands in an iframe `src=` URL segment
 * at render time. Even though `esc_attr` blocks attribute-breakout, the
 * value becomes part of the URL path/query — a caller can inject
 * arbitrary URL params (`?autoplay=1&redirect=...`) that the video
 * platform then honours. Pattern-lock (audit A3 F-J).
 */
function avada_sanitize_video_id(mixed $value, string $pattern): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }
    return preg_match($pattern, $trimmed) === 1 ? $trimmed : null;
}

/**
 * Aspect-ratio field. Value shape is `WIDTH:HEIGHT` (e.g. `16:9`,
 * `4:3`). Reject anything else — the value lands in a
 * `data-video-aspect-ratio` attribute and is consumed by JS that may
 * concat it into CSS or an inline style (audit A3 F5).
 */
function avada_sanitize_aspect_ratio(mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $trimmed = trim($value);
    if ($trimmed === '') {
        return '';
    }
    return preg_match('/^\d{1,3}:\d{1,3}$/', $trimmed) === 1 ? $trimmed : null;
}

/**
 * Enum-form sanitizer: `$type` MUST start with `enum:` — the caller
 * ({@see avada_sanitize_slide_value()}) checks that already. Only string
 * values are accepted; anything else is rejected outright.
 */
function avada_sanitize_enum_value(string $type, mixed $value): ?string
{
    if (!is_string($value)) {
        return null;
    }
    $allowed = explode('|', substr(string: $type, offset: 5));
    return in_array($value, $allowed, strict: true) ? $value : null;
}

/**
 * Fusion Slider stores checkboxes as the string literals '1' / '0' in
 * the `_fusion` bag — mirror that shape so the front-end reads them the
 * same way the admin form writes them. Accepts `bool`, `'0'`/`'1'`,
 * `0`/`1` for caller convenience; rejects everything else.
 */
function avada_sanitize_slider_bool(mixed $value): ?string
{
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    if (is_string($value) && in_array($value, ['0', '1'], strict: true)) {
        return $value;
    }
    if (is_int($value) && in_array($value, [0, 1], strict: true)) {
        return (string) $value;
    }
    return null;
}

/**
 * Fusion Slider stores non-negative integers as string literals in the
 * `_fusion` bag (form inputs are `type="text"` in the admin, so the raw
 * POST is a string). Accepts either an `int` or a decimal-digit string.
 */
function avada_sanitize_slider_int(mixed $value): ?string
{
    if (is_int($value) && $value >= 0) {
        return (string) $value;
    }
    if (is_string($value) && ctype_digit($value)) {
        return $value;
    }
    return null;
}

/**
 * Bust every Fusion Core cache surface after a slide/slider write. The
 * `avada` wp_cache group is where `FusionCore_Plugin::fusion_core_cached_query()`
 * memoises WP_Query results for the front-end shortcode render — it is NOT
 * invalidated by any Fusion Core hook on `save_post_slide` / `edited_slide-page`,
 * so ability writers must call this explicitly (see the Fusion Core report
 * gotcha under §13). Also fires the theme's `fusion_cache_clear` action so
 * any third-party listeners (LiteSpeed, WP Rocket integrations) can react.
 */
function avada_flush_fusion_core_cache(): void
{
    // Every sub-flush is best-effort — a third-party listener throwing
    // out of a filter callback must NOT propagate up into the ability
    // response (audit A3 F-E). The DB write has already committed by
    // the time we get here, so a fatal in flush would leave the caller
    // with a 500 while the persistent state is fresh — actionable
    // inconsistency.
    if (function_exists('wp_cache_flush_group')) {
        try {
            wp_cache_flush_group('avada');
        } catch (\Throwable $e) {
            unset($e);
        }
    }
    if (class_exists('Fusion_Cache')) {
        try {
            (new \Fusion_Cache())->reset_all_caches();
        } catch (\Throwable $e) {
            unset($e);
        }
    }
    try {
        do_action('fusion_cache_clear');
    } catch (\Throwable $e) {
        unset($e);
    }
}

/**
 * Bust every Fusion-owned cache surface after a write. Called by every
 * ability that persists to `fusion_options` or a companion option so the
 * next front-end request — AND subsequent read paths in the same request —
 * serve fresh values. `AWB_Global_Colors` and `AWB_Global_Typography`
 * cache their palette / preset arrays in a per-singleton property that
 * survives across ability calls in the same request; reset them
 * explicitly so a write→read roundtrip in the same call chain sees the
 * new value.
 */
function avada_flush_fusion_cache(): void
{
    delete_transient('fusion_dynamic_css');
    delete_transient('fusion_dynamic_css_files');
    wp_cache_delete(AVADA_OPTIONS_KEY, group: 'options');
    wp_cache_delete(AVADA_OPTIONS_KEY, group: 'fusion');
    // Reset the AWB singleton caches so the next read sees the fresh
    // value written to `fusion_options`. Both properties are declared
    // `public` on their respective classes (checked against Avada 7.15.5).
    if (class_exists('AWB_Global_Colors')) {
        \AWB_Global_Colors::get_instance()->palette = null;
    }
    if (class_exists('AWB_Global_Typography')) {
        \AWB_Global_Typography::get_instance()->typography = null;
    }
    // Reset the Fusion_Settings-level cache too — it sits BELOW the AWB
    // singletons and would otherwise return the pre-write value on the
    // next `fusion_library()->get_option()` call in the same request.
    if (function_exists('Avada') && class_exists('\\Avada')) {
        $avada = \Avada();
        if (property_exists($avada, 'settings') && method_exists($avada->settings, 'reset_all_options')) {
            $avada->settings->reset_all_options();
        }
    }
    // Fire the theme's own cache-clear hook so third-party listeners
    // (LiteSpeed, WP Rocket integrations, custom MU-plugins) can react.
    do_action('fusion_cache_clear');
}
