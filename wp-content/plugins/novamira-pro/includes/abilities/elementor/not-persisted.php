<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Reporting the fields a successful write sent but Elementor did not store.
 *
 * Elementor decides on save which values are worth persisting: the atomic
 * `Props_Parser::sanitize()` skips every prop whose type answers no to
 * `should_persist()` — an object prop with an empty inner value, an array prop
 * with no items, and so on — and the style parser applies the same predicate
 * to style props. The write still succeeds and the element reads back without
 * the field. That is Elementor's decision, not a failed write, so it is
 * reported alongside the success payload rather than refused.
 *
 * WHAT IS CHECKED — key presence, at the level the caller addresses:
 *
 *  - a settings key: lost when the element read back after the write has no
 *    such key (or holds null there);
 *  - a style prop: lost when the SAME style of the element read back has no
 *    variant with the same breakpoint and state carrying the prop. Identities
 *    are compared after the normalisations the write applies and Elementor
 *    accepts — the style id after el_style_id_renames() (the deterministic
 *    CSS-safe rename the write applies; ids that would collide under it are
 *    refused before writing, see el_style_id_collisions()), the breakpoint after
 *    sanitize_key() (Style_Parser::sanitize_meta()) with an absent / empty one
 *    read as the base breakpoint — so a renamed style or a re-cased breakpoint
 *    is never reported, and one style cannot mask a prop lost from another.
 *
 * Anything whose key survives counts as stored, whatever happened to its
 * value: wrapped, completed, coerced — and also emptied or pruned inside (a
 * link whose destination esc_url_raw() cleared, an array prop that lost one of
 * several items). This limit is intentional: telling a lost value from a
 * normalised one needs per-type knowledge, and guessing would produce false
 * reports. The limit is stated to the
 * agent in the note and in the ability descriptions, so silence is never
 * presented as "nothing was lost".
 *
 * Only fields the caller sent are considered — keys the server added (schema
 * defaults, the synced `classes` setting) are never in the manifest — and a
 * field sent as null is a request to unset it, so it is never reported.
 *
 * Fields the write pipeline itself moves before writing (add-element tree
 * mode splits a boxed container into an outer and a generated inner node) are
 * followed by provenance to the inner node generated for THAT container, and
 * one that cannot be found there in its requested variant is reported as
 * lost. Settings the v3 container conversion consumes on purpose — the names
 * in el_v3_container_consumed_setting_bases(), rewritten into style props or
 * replaced — are not followed; exactly that list is the limit stated to the
 * agent. Any other setting the conversion removed is reported.
 *
 * The stored tree comes from the read-back every write already performs
 * ({@see el_read_back_comparison()}); nothing is read a second time. The
 * report is bounded (EL_NOT_PERSISTED_MAX_LISTED paths, a total, and a
 * per-field-name count that discloses its own truncation), because it rides
 * on every write.
 *
 * Writers without the report, and why: delete-element / delete-element-style
 * send nothing that is meant to survive; a template type conversion rewrites
 * the existing tree and sends no fields; dynamic tags and interactions set a
 * single binding whose envelope types Elementor always persists; global class
 * styles are stored through the global classes repository (its own data
 * normalizer), not through an element write, so there is no element read-back
 * to compare against.
 */

/** Response key carrying the report. Absent when nothing was lost. */
const EL_NOT_PERSISTED_KEY = 'not_persisted';

/** Most lost-field paths listed individually in one report. */
const EL_NOT_PERSISTED_MAX_LISTED = 20;

/** Most distinct field names counted in `by_field`. */
const EL_NOT_PERSISTED_MAX_KINDS = 20;

/**
 * The fields one node sent: its non-null settings keys, and its non-null
 * style props by style identity and variant.
 *
 * @param array<array-key, mixed> $node
 * @return array{settings: list<string>, styles: array<string, array<string, list<string>>>}
 */
function el_sent_fields_of_node(array $node): array
{
    $styles = [];
    /** @var mixed $raw_styles */
    $raw_styles = $node['styles'] ?? null;
    if (is_array($raw_styles)) {
        // The same collision-free map the write applies (el_style_id_renames()),
        // so two sent styles never share an identity here.
        $renames = el_style_id_renames(array_keys($raw_styles));
        foreach (array_keys($raw_styles) as $style_id) {
            if (!is_string($style_id) || !is_array($raw_styles[$style_id])) {
                continue;
            }
            $style_key = $renames[$style_id] ?? $style_id;
            foreach (el_array_items($raw_styles[$style_id]['variants'] ?? null) as $variant) {
                $props = el_non_null_keys($variant['props'] ?? null);
                if ($props === []) {
                    continue;
                }
                $variant_key = el_variant_key($variant);
                $styles[$style_key][$variant_key] = array_values(array_unique([
                    ...($styles[$style_key][$variant_key] ?? []),
                    ...$props,
                ]));
            }
        }
    }

    return ['settings' => el_non_null_keys($node['settings'] ?? null), 'styles' => $styles];
}

/**
 * el_sent_fields_of_node() for a single element whose settings went through
 * the atomic coercion first: a key the caller sent as null may come back as a
 * non-null envelope, so the settings keys are taken from the caller's raw map
 * (non-null there) and kept only where the validated map still has them.
 *
 * @param array<array-key, mixed> $raw_settings
 * @param array<array-key, mixed> $validated_settings
 * @param array<array-key, mixed> $sent_styles
 * @return array{settings: list<string>, styles: array<string, array<string, list<string>>>}
 */
function el_sent_fields_of_single(array $raw_settings, array $validated_settings, array $sent_styles): array
{
    $fields = el_sent_fields_of_node(['styles' => $sent_styles]);
    $fields['settings'] = array_values(array_filter(
        el_non_null_keys($raw_settings),
        static fn(string $key): bool => array_key_exists($key, $validated_settings),
    ));
    return $fields;
}

/**
 * The array-valued entries of a list or map, in order; [] for a non-array.
 *
 * @return list<array<array-key, mixed>>
 */
function el_array_items(mixed $items): array
{
    if (!is_array($items)) {
        return [];
    }
    $out = [];
    foreach (array_keys($items) as $key) {
        if (!is_array($items[$key])) {
            continue;
        }
        $out[] = $items[$key];
    }
    return $out;
}

/**
 * The string keys of a map whose value is not null; [] for a non-array.
 *
 * @return list<string>
 */
function el_non_null_keys(mixed $map): array
{
    if (!is_array($map)) {
        return [];
    }
    $keys = [];
    foreach (array_keys($map) as $key) {
        if (!is_string($key) || $map[$key] === null) {
            continue;
        }
        $keys[] = $key;
    }
    return $keys;
}

/**
 * The fields every node of a forest sent, keyed by element id. Nodes without
 * an id cannot be matched against the stored tree and are skipped — every
 * caller builds the manifest after ids were assigned. A duplicated id merges
 * its fields.
 *
 * @param array<array-key, mixed> $nodes
 * @return array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}>
 */
function el_sent_fields(array $nodes): array
{
    $manifest = [];
    foreach (el_array_items($nodes) as $node) {
        /** @var mixed $id */
        $id = $node['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $manifest = el_merge_sent_fields($manifest, [$id => el_sent_fields_of_node($node)]);
        }
        if (is_array($node['elements'] ?? null)) {
            $manifest = el_merge_sent_fields($manifest, el_sent_fields($node['elements']));
        }
    }
    return $manifest;
}

/**
 * Union of two manifests.
 *
 * @param array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}> $a
 * @param array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}> $b
 * @return array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}>
 */
function el_merge_sent_fields(array $a, array $b): array
{
    foreach ($b as $id => $fields) {
        if (!array_key_exists($id, $a)) {
            $a[$id] = $fields;
            continue;
        }
        $a[$id]['settings'] = array_values(array_unique([...$a[$id]['settings'], ...$fields['settings']]));
        foreach ($fields['styles'] as $style_key => $variants) {
            foreach ($variants as $variant_key => $props) {
                $a[$id]['styles'][$style_key][$variant_key] = array_values(array_unique([
                    ...($a[$id]['styles'][$style_key][$variant_key] ?? []),
                    ...$props,
                ]));
            }
        }
    }
    return $a;
}

/**
 * Follow the caller's fields across the tree-mode conversion that moves
 * fields before the tree is written (the boxed container split and the v3
 * container translation).
 *
 * `$before` is the manifest of the tree as sent, `$after` the one after the
 * step. Each field is followed by provenance, never by searching: a field
 * that left its node can only have gone to the inner node the split generated
 * FOR that node (el_boxed_inner_id()), and a style prop only counts as there
 * in the same variant (breakpoint and state) it was sent in.
 *
 *  - Still on its node (same style and variant for a style prop): kept there.
 *  - On the node's generated inner node: re-homed there, to the style that
 *    carries it.
 *  - A style prop found in neither: lost by the conversion itself (a hover
 *    `gap` the split rebuilds without its state) — returned under `lost`.
 *  - A setting found in neither: not followed when its name (responsive
 *    suffix stripped) is one the conversion consumes on purpose
 *    (el_v3_container_consumed_setting_bases() — rewritten into style props or
 *    replaced), which is the limit stated to the agent; any other setting is
 *    returned under `lost`.
 *
 * Element ids are array keys here, so an all-digit id arrives as an int and is
 * cast back before it is used as a string.
 *
 * @param array<array-key, array{settings: list<string>, styles: array<string, array<string, list<string>>>}> $before
 * @param array<array-key, array{settings: list<string>, styles: array<string, array<string, list<string>>>}> $after
 * @return array{sent: array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}>, lost: array<string, list<string>>}
 */
function el_follow_moved_fields(array $before, array $after): array
{
    $sent = [];
    $lost = [];
    foreach ($before as $key => $fields) {
        $id = (string) $key;
        $inner_id = el_boxed_inner_id($id);
        $inner = !array_key_exists($inner_id, $before) && array_key_exists($inner_id, $after)
            ? $after[$inner_id]
            : ['settings' => [], 'styles' => []];
        // A list of [id, fields] pairs, not a map: an all-digit id would turn
        // into an int key.
        $destinations = [[$id, $after[$id] ?? ['settings' => [], 'styles' => []]], [$inner_id, $inner]];

        [$settings_sent, $settings_lost] = el_follow_node_settings($id, $fields['settings'], $destinations);
        [$styles_sent, $styles_lost] = el_follow_node_styles($id, $inner_id, $fields['styles'], $destinations);
        $sent = el_merge_sent_fields(el_merge_sent_fields($sent, $settings_sent), $styles_sent);
        $node_lost = [...$settings_lost, ...$styles_lost];
        if ($node_lost !== []) {
            $lost[$id] = $node_lost;
        }
    }
    return ['sent' => $sent, 'lost' => $lost];
}

/**
 * One node's settings across the conversion (see el_follow_moved_fields()).
 * `$destinations` is the node itself, then its generated inner node.
 *
 * @param list<string> $keys
 * @param list<array{0: string, 1: array{settings: list<string>, styles: array<string, array<string, list<string>>>}}> $destinations
 * @return array{0: array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}>, 1: list<string>}
 */
function el_follow_node_settings(string $id, array $keys, array $destinations): array
{
    $consumed = el_v3_container_consumed_setting_bases();
    $sent = [];
    $lost = [];
    foreach ($keys as $key) {
        $home = el_destination_with_setting($destinations, $key);
        if ($home !== null) {
            $sent = el_merge_sent_fields($sent, [$home => ['settings' => [$key], 'styles' => []]]);
            continue;
        }
        if (!in_array(el_v3_resolve_breakpoint_key($key)['base'], $consumed, strict: true)) {
            $lost[] = 'settings.' . $key;
        }
    }
    return [$sent, $lost];
}

/**
 * The first destination (node, then its generated inner node) whose settings
 * carry `$key`, or null.
 *
 * @param list<array{0: string, 1: array{settings: list<string>, styles: array<string, array<string, list<string>>>}}> $destinations
 */
function el_destination_with_setting(array $destinations, string $key): ?string
{
    foreach ($destinations as [$dest_id, $fields]) {
        if (in_array($key, $fields['settings'], strict: true)) {
            return $dest_id;
        }
    }
    return null;
}

/**
 * One node's style props across the conversion (see el_follow_moved_fields()):
 * kept in the same style and variant on the node, else re-homed to the style
 * of its generated inner node that carries the prop in the same variant, else
 * lost.
 *
 * @param array<string, array<string, list<string>>> $styles
 * @param list<array{0: string, 1: array{settings: list<string>, styles: array<string, array<string, list<string>>>}}> $destinations
 * @return array{0: array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}>, 1: list<string>}
 */
function el_follow_node_styles(string $id, string $inner_id, array $styles, array $destinations): array
{
    [[, $here], [, $inner]] = $destinations;
    $sent = [];
    $lost = [];
    foreach ($styles as $style_key => $variants) {
        foreach ($variants as $variant_key => $props) {
            foreach ($props as $prop) {
                $home = in_array($prop, $here['styles'][$style_key][$variant_key] ?? [], strict: true)
                    ? [$id, $style_key]
                    : el_inner_style_home($inner, $inner_id, $variant_key, $prop);
                if ($home === null) {
                    $lost[] = el_style_field_path($style_key, $variant_key, $prop);
                    continue;
                }
                $sent = el_merge_sent_fields($sent, [
                    $home[0] => [
                        'settings' => [],
                        'styles' => [
                            $home[1] => [$variant_key => [$prop]],
                        ],
                    ],
                ]);
            }
        }
    }
    return [$sent, $lost];
}

/**
 * [inner node id, style key] when the generated inner node carries `$prop`
 * in `$variant_key`, else null.
 *
 * @param array{settings: list<string>, styles: array<string, array<string, list<string>>>} $inner
 * @return array{0: string, 1: string}|null
 */
function el_inner_style_home(array $inner, string $inner_id, int|string $variant_key, string $prop): ?array
{
    $style = el_style_carrying($inner, $variant_key, $prop);
    return $style === null ? null : [$inner_id, $style];
}

/**
 * The style of one manifest entry that carries `$prop` in the variant
 * `$variant_key`, or null.
 *
 * @param array{settings: list<string>, styles: array<string, array<string, list<string>>>} $fields
 */
function el_style_carrying(array $fields, int|string $variant_key, string $prop): ?string
{
    foreach ($fields['styles'] as $style_key => $variants) {
        if (in_array($prop, $variants[$variant_key] ?? [], strict: true)) {
            // Never all-digit: every manifest style key went through
            // el_style_id_renames(), which prefixes a digit-leading id.
            return $style_key;
        }
    }
    return null;
}

/**
 * `<breakpoint>` or `<breakpoint>:<state>` for one variant, with the
 * breakpoint normalised the way Elementor stores it (sanitize_key()) and an
 * absent, null or empty breakpoint read as the base breakpoint, which is how
 * the write pipeline stores it (EL_BASE_BREAKPOINT). The state is compared as
 * given: Elementor accepts only its own state names and does not rewrite them.
 *
 * @param array<array-key, mixed> $variant
 */
function el_variant_key(array $variant): string
{
    /** @var mixed $meta */
    $meta = $variant['meta'] ?? null;
    $meta = is_array($meta) ? $meta : [];
    /** @var mixed $breakpoint */
    $breakpoint = $meta['breakpoint'] ?? null;
    $breakpoint = is_string($breakpoint) ? sanitize_key($breakpoint) : '';
    $breakpoint = $breakpoint !== '' ? $breakpoint : EL_BASE_BREAKPOINT;
    /** @var mixed $state */
    $state = $meta['state'] ?? null;
    return is_string($state) && $state !== '' ? $breakpoint . ':' . $state : $breakpoint;
}

/**
 * The reported path of a style prop: `styles.<style id>[<variant>].<prop>`.
 */
function el_style_field_path(int|string $style_key, int|string $variant_key, string $prop): string
{
    // Style and variant keys come back from array keys, where an all-digit
    // string (a breakpoint such as "768") is an int.
    return sprintf('styles.%s[%s].%s', $style_key, $variant_key, $prop);
}

/**
 * Compare the manifest against the tree read back after the write. Returns
 * the lost field paths keyed by element id — `settings.<key>` and
 * `styles.<style id>[<variant>].<prop>` — or [] when every sent key is
 * stored. An element that is not in the stored tree at all is skipped: a
 * dropped or moved node already fails the write verification before this
 * runs.
 *
 * @param array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}> $sent
 * @param array<array-key, mixed> $stored_tree
 * @return array<string, list<string>>
 */
function el_unpersisted_fields(array $sent, array $stored_tree): array
{
    if ($sent === []) {
        return [];
    }
    $stored = el_sent_fields($stored_tree);
    $lost = [];
    foreach ($sent as $id => $fields) {
        if (!array_key_exists($id, $stored)) {
            continue;
        }
        $paths = [];
        foreach (array_diff($fields['settings'], $stored[$id]['settings']) as $key) {
            $paths[] = 'settings.' . $key;
        }
        foreach ($fields['styles'] as $style_key => $variants) {
            foreach ($variants as $variant_key => $props) {
                $kept = $stored[$id]['styles'][$style_key][$variant_key] ?? [];
                foreach (array_diff($props, $kept) as $prop) {
                    $paths[] = el_style_field_path($style_key, $variant_key, $prop);
                }
            }
        }
        if ($paths !== []) {
            $lost[$id] = $paths;
        }
    }
    return $lost;
}

/**
 * Every field this write sent that is missing after it, keyed by element id:
 * `$lost_before_write` (fields the write pipeline lost before anything was
 * stored, el_follow_moved_fields()) plus the comparison of `$sent` against the
 * stored tree the write's own read-back recorded. [] when nothing was lost or
 * no read-back was recorded. Unbounded — el_not_persisted_report() bounds what
 * the agent sees; el_with_write_reports() hands the full map to the markup
 * warnings so no field beyond the listing cap gets a rendering claim.
 *
 * @param array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}> $sent
 * @param array<string, list<string>> $lost_before_write
 * @return array<array-key, list<string>>
 */
function el_collect_not_persisted(int $post_id, array $sent, array $lost_before_write = []): array
{
    $stored_tree = el_take_read_back($post_id);
    $lost = $lost_before_write;
    if ($stored_tree !== null) {
        foreach (el_unpersisted_fields($sent, $stored_tree) as $id => $paths) {
            $lost[$id] = array_values(array_unique([...($lost[$id] ?? []), ...$paths]));
        }
    }
    return $lost;
}

/**
 * Add the not-persisted report to a success payload (see
 * el_collect_not_persisted()). Leaves the payload untouched when nothing was
 * lost. Used by writers without markup warnings; the others go through
 * el_with_write_reports().
 *
 * @param array<string, mixed> $response
 * @param array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}> $sent
 * @param array<string, list<string>> $lost_before_write
 * @return array<string, mixed>
 */
function el_with_not_persisted(array $response, int $post_id, array $sent, array $lost_before_write = []): array
{
    $lost = el_collect_not_persisted($post_id, $sent, $lost_before_write);
    if ($lost !== []) {
        $response[EL_NOT_PERSISTED_KEY] = el_not_persisted_report($lost);
    }
    return $response;
}

/**
 * Both post-write reports, in a fixed order: first establish
 * what was STORED (`not_persisted`), then say what will be SHOWN differently
 * (`warnings`, el_with_markup_warnings()). A field that was not stored gets
 * only the not-persisted entry — never a claim about how a value that is not
 * there will render — and that holds for every lost field, including those
 * past the report's listing cap, because the full lost map is passed along.
 *
 * `$markup` names what the markup check examines, exactly as
 * el_with_markup_warnings() takes it: `elements` (the written elements) and
 * `sent` (the per-element setting keys; null for every setting of those
 * subtrees).
 *
 * @param array<string, mixed> $response
 * @param array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}> $sent
 * @param array<string, list<string>> $lost_before_write
 * @param array{elements: list<array<string, mixed>>, sent: array<string, list<string>>|null} $markup
 * @return array<string, mixed>
 */
function el_with_write_reports(
    array $response,
    int $post_id,
    array $sent,
    array $lost_before_write,
    array $markup,
): array {
    $lost = el_collect_not_persisted($post_id, $sent, $lost_before_write);
    if ($lost !== []) {
        $response[EL_NOT_PERSISTED_KEY] = el_not_persisted_report($lost);
    }
    return el_with_markup_warnings($response, $markup['elements'], $markup['sent'], $lost);
}

/**
 * The bounded report: at most EL_NOT_PERSISTED_MAX_LISTED paths listed under
 * `fields` in tree order, the `total`, and — only when paths were left out —
 * `unlisted` plus `by_field`, the count of every lost field by its name
 * without element or style id (the most frequent EL_NOT_PERSISTED_MAX_KINDS).
 *
 * @param array<array-key, list<string>> $lost
 * @return array<string, mixed>
 */
function el_not_persisted_report(array $lost): array
{
    $fields = [];
    $listed = 0;
    $total = 0;
    $by_field = [];
    foreach ($lost as $id => $paths) {
        foreach ($paths as $path) {
            $total++;
            $name = el_field_name($path);
            $by_field[$name] = ($by_field[$name] ?? 0) + 1;
            if ($listed >= EL_NOT_PERSISTED_MAX_LISTED) {
                continue;
            }
            $fields[$id][] = $path;
            $listed++;
        }
    }

    // An object, not an array: element ids are its keys, and PHP turns an
    // all-digit id into an int key — ids "0", "1", … would make `fields` a
    // JSON list instead of the object keyed by element id the schema promises.
    $report = ['fields' => (object) $fields, 'total' => $total];
    if ($total > $listed) {
        arsort($by_field);
        $report['unlisted'] = $total - $listed;
        $report['by_field'] = array_slice(
            $by_field,
            offset: 0,
            length: EL_NOT_PERSISTED_MAX_KINDS,
            preserve_keys: true,
        );
        $other = array_slice($by_field, offset: EL_NOT_PERSISTED_MAX_KINDS, length: null, preserve_keys: true);
        if ($other !== []) {
            $report['by_field_other'] = ['names' => count($other), 'fields' => array_sum($other)];
        }
    }
    $report['note'] = $total > $listed
        ? el_not_persisted_note() . el_not_persisted_truncation_note()
        : el_not_persisted_note();
    return $report;
}

/**
 * A lost-field path without its style id — `styles.s-1[desktop].color` →
 * `styles[desktop].color` — so the same field counts once across elements.
 */
function el_field_name(string $path): string
{
    return (string) preg_replace('/^styles\.[^\[]*\[/', replacement: 'styles[', subject: $path);
}

/**
 * The note carried by every report. States what is and is not checked, so an
 * agent never reads the absence of a field as proof that its content survived.
 */
function el_not_persisted_note(): string
{
    return 'The write succeeded, but these fields you sent were not stored: the saved element reads back without them. Usually Elementor left them out because it does not store values it considers empty; send a non-empty value to keep a field, or omit it. A field can also be lost when a boxed v3-style container is split into an outer and an inner element. Only fields whose key is missing entirely are detected: a value stored in changed or emptied form (for example a link whose address was removed, or a list that lost some items) is not reported, and neither are the v3 container settings the conversion rewrites or replaces (boxed_width, content_width, width, html_tag and the layout settings it turns into styles).';
}

/**
 * The sentence added when the report lists only part of the lost fields. It
 * says what `by_field` covers, and that the unlisted entries themselves cannot
 * be fetched — get-content shows what an element stored, not this report.
 */
function el_not_persisted_truncation_note(): string
{
    return (
        ' Only the first '
        . EL_NOT_PERSISTED_MAX_LISTED
        . ' fields are listed; `total` counts all of them. `by_field` counts them by field name for the '
        . EL_NOT_PERSISTED_MAX_KINDS
        . ' most frequent names only; when there are more names, `by_field_other` gives how many names and fields it leaves out. The unlisted entries cannot be fetched later: to see what an element stored, read it with novamira/elementor-get-content (element_id) and compare it with what you sent.'
    );
}

/**
 * The output-schema entry every reporting ability declares.
 *
 * @return array<string, mixed>
 */
function el_not_persisted_output_schema(): array
{
    return [
        'type' => 'object',
        'description' =>
            'Present only when the write succeeded but some fields you sent were not stored: the saved element reads back without their keys, usually because Elementor leaves out values it does not consider worth storing, such as empty ones. `fields` maps element id to the missing paths — `settings.<key>` or `styles.<style id>[<breakpoint>(:<state>)].<prop>` — at most '
                . EL_NOT_PERSISTED_MAX_LISTED
                . ' of them; `total` counts all. When some are unlisted, `unlisted` counts them, `by_field` counts all missing fields by name for the '
                . EL_NOT_PERSISTED_MAX_KINDS
                . ' most frequent names, and `by_field_other` (only when there are more names) gives how many names and fields `by_field` leaves out. Only missing keys are detected: a value stored in changed or emptied form is not reported, so the absence of this field does not prove every value was stored as sent.',
        'properties' => [
            'fields' => [
                'type' => 'object',
                'additionalProperties' => ['type' => 'array', 'items' => ['type' => 'string']],
            ],
            'total' => ['type' => 'integer'],
            'unlisted' => ['type' => 'integer'],
            'by_field' => [
                'type' => 'object',
                'additionalProperties' => ['type' => 'integer'],
            ],
            'by_field_other' => [
                'type' => 'object',
                'properties' => [
                    'names' => ['type' => 'integer'],
                    'fields' => ['type' => 'integer'],
                ],
            ],
            'note' => ['type' => 'string'],
        ],
    ];
}

/**
 * The extra sentence add-element's description carries: tree mode runs the v3
 * container conversion, and the settings it consumes are not checked.
 */
function el_not_persisted_tree_conversion_sentence(): string
{
    return ' In tree mode, the v3 container settings the conversion rewrites or replaces (boxed_width, content_width, width, html_tag and the layout settings it turns into styles) are not checked by `not_persisted`.';
}

/**
 * The sentence each reporting ability's description carries, so the contract
 * is visible before the first write.
 */
function el_not_persisted_description_sentence(): string
{
    return ' After a successful write, `not_persisted` names any field you sent whose key Elementor did not store (it leaves out values it considers empty); it detects missing keys only, not values stored in changed or emptied form, so its absence does not prove every value was stored as sent.';
}

/**
 * Keep the tree a write read back, for el_with_not_persisted() to pick up.
 * Called by the write verification ({@see el_read_back_comparison()}); the
 * last read-back of a post wins, which is the one of the path that stored it.
 *
 * @param array<array-key, mixed> $tree
 */
function el_remember_read_back(int $post_id, array $tree): void
{
    el_read_back_slot($post_id, $tree);
}

/**
 * Hand out — and forget — the tree the last write of a post read back.
 * el_write_page() calls this before writing, so a failed write never leaves a
 * previous write's tree behind.
 *
 * @return array<array-key, mixed>|null
 */
function el_take_read_back(int $post_id): ?array
{
    return el_read_back_slot($post_id, tree: null);
}

/**
 * Per-request storage behind el_remember_read_back() / el_take_read_back():
 * with a tree, store it; with null, return and forget the stored one.
 *
 * @param array<array-key, mixed>|null $tree
 * @return array<array-key, mixed>|null
 */
function el_read_back_slot(int $post_id, ?array $tree): ?array
{
    /** @var array<int, array<array-key, mixed>> $trees */
    static $trees = [];
    if ($tree !== null) {
        $trees[$post_id] = $tree;
        return null;
    }
    $taken = $trees[$post_id] ?? null;
    unset($trees[$post_id]);
    return $taken;
}
