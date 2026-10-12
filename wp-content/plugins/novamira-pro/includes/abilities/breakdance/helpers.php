<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Breakdance;

if (!defined('ABSPATH')) {
    exit();
}

// Polyfill for `array_is_list` (PHP 8.1+). The plugin header declares
// `Requires PHP: 8.0`, but the input validation in add-element /
// edit-element calls `array_is_list` to refuse list-shaped `properties`.
// Without this polyfill the ability would fatal on PHP 8.0 on the first
// non-empty `properties` payload.
if (!function_exists('array_is_list')) {
    function array_is_list(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

/**
 * Shared helpers for the Breakdance abilities. Centralises the bits
 * each callback would otherwise repeat: CPT enumeration, id loading,
 * settings (JSON in postmeta) read/write, error builder with REST
 * status, and generic input coercion.
 */

/**
 * Build a WP_Error with the HTTP status the REST layer needs. Without
 * the `['status' => 4xx]` payload every WP_Error from the integration
 * collapses to 500 over REST. Status map mirrors the GP integration:
 * gp-style codes → 400/404/412/409.
 */
function bd_error(string $code, string $message): \WP_Error
{
    $status_map = [
        'bd_invalid_input' => 400,
        'bd_not_found' => 404,
        'bd_write_not_applied' => 409,
    ];
    $status = $status_map[$code] ?? 400;
    return new \WP_Error($code, $message, ['status' => $status]);
}

/**
 * Verify the observed result of a WordPress trash/delete operation. Hooks can
 * short-circuit either API with a truthy WP_Post while leaving the row exactly
 * where it was, so the return value alone is not proof of deletion.
 */
// @mago-expect lint:no-boolean-flag-parameter
function bd_verify_delete_outcome(int $id, bool $force): string|\WP_Error
{
    /** @var mixed $after */
    $after = get_post($id);
    if ($force) {
        if ($after !== null) {
            return bd_error('bd_write_not_applied', sprintf(
                __('Post %d was not deleted; a hook may have blocked the deletion.', domain: 'novamira-pro'),
                $id,
            ));
        }
        return 'force_deleted';
    }
    if ($after === null) {
        return 'force_deleted';
    }
    if ($after instanceof \WP_Post && $after->post_status === 'trash') {
        return 'trashed';
    }
    return bd_error('bd_write_not_applied', sprintf(
        __('Post %d was not trashed; a hook may have blocked the operation.', domain: 'novamira-pro'),
        $id,
    ));
}

/**
 * Breakdance's registered raw-code element. In 2.8.1 this single element owns
 * the "PHP & HTML Code", "CSS Code", and "JavaScript Code" controls.
 *
 * @var list<string>
 */
const NOVAMIRA_BREAKDANCE_CODE_ELEMENT_TYPES = [
    'EssentialElements\\CodeBlock',
];

/**
 * Whether a canonical Breakdance node is a raw code/HTML element.
 *
 * @param array<string, mixed> $node
 */
function bd_is_code_element(array $node): bool
{
    /** @var mixed $data */
    $data = $node['data'] ?? null;
    $type = is_array($data) && is_string($data['type'] ?? null) ? $data['type'] : '';
    return in_array($type, NOVAMIRA_BREAKDANCE_CODE_ELEMENT_TYPES, strict: true);
}

/**
 * Collect raw-code node ids depth-first.
 *
 * @param list<array<string, mixed>> $nodes
 * @return list<int|string>
 */
function bd_collect_code_element_ids(array $nodes): array
{
    $ids = [];
    foreach ($nodes as $node) {
        if (bd_is_code_element($node)) {
            /** @var mixed $id */
            $id = $node['id'] ?? '?';
            $ids[] = is_int($id) || is_string($id) ? $id : '?';
        }
        $ids = [...$ids, ...bd_collect_code_element_ids(bd_node_children($node))];
    }
    return $ids;
}

/**
 * True when every content-bearing node in a tree is a raw-code element.
 * Non-code parents that contain children are structural for this check, which
 * catches the common Section → CodeBlock raw-page shape.
 *
 * @param list<array<string, mixed>> $nodes
 */
function bd_code_tree_is_raw_html_only(array $nodes): bool
{
    [$content_count, $code_count] = bd_code_tree_content_counts($nodes);
    return $content_count > 0 && $content_count === $code_count;
}

/**
 * @param list<array<string, mixed>> $nodes
 * @return array{0: int, 1: int}
 */
function bd_code_tree_content_counts(array $nodes): array
{
    $content_count = 0;
    $code_count = 0;
    foreach ($nodes as $node) {
        $is_code = bd_is_code_element($node);
        if ($is_code) {
            $content_count++;
            $code_count++;
        }
        $children = bd_node_children($node);
        if ($children !== []) {
            [$child_content, $child_code] = bd_code_tree_content_counts($children);
            $content_count += $child_content;
            $code_count += $child_code;
            continue;
        }
        if (!$is_code) {
            $content_count++;
        }
    }
    return [$content_count, $code_count];
}

/**
 * Require explicit confirmation before a Breakdance write creates or edits a
 * raw code/HTML element.
 *
 * @param list<array<string, mixed>> $nodes
 * @param array<string, mixed>       $input
 */
function bd_code_element_gate(array $nodes, array $input): ?\WP_Error
{
    $node_ids = bd_collect_code_element_ids($nodes);
    if ($node_ids === []) {
        return null;
    }

    $count = count($node_ids);
    $shown_ids = array_slice($node_ids, offset: 0, length: 10);
    $shown = implode(', ', array_map(static fn(int|string $id): string => (string) $id, $shown_ids));
    $extra = $count - count($shown_ids);
    if ($extra > 0) {
        $shown = sprintf('%s, … and %d more', $shown, $extra);
    }

    if (($input['allow_code_elements'] ?? false) === true) {
        return null;
    }

    return new \WP_Error(
        'breakdance_code_element_needs_confirmation',
        sprintf(
            'This write would create or edit %1$d Breakdance Code Block element(s) (node ids: %2$s). '
            . 'The Code Block injects raw HTML/PHP/CSS/JS and is a LAST RESORT: it bypasses Breakdance\'s '
            . 'native controls, responsive styling, design tokens, and visual editability. Build content '
            . 'and layout with native Breakdance elements and style through their properties, variables, '
            . 'global classes, and global settings instead. Only if the user has EXPLICITLY confirmed they '
            . 'want a raw code/HTML element, re-call with allow_code_elements: true — do not set that flag '
            . 'on your own.',
            $count,
            $shown,
        ),
        ['status' => 422, 'count' => $count, 'node_ids' => $shown_ids],
    );
}

/**
 * Reject a set-content payload whose content-bearing nodes are all raw code,
 * even when the caller supplies the normal code-element opt-in.
 *
 * @param list<array<string, mixed>> $nodes
 */
function bd_code_only_tree_gate(array $nodes): ?\WP_Error
{
    if (!bd_code_tree_is_raw_html_only($nodes)) {
        return null;
    }
    $node_ids = bd_collect_code_element_ids($nodes);
    $count = count($node_ids);
    $shown_ids = array_slice($node_ids, offset: 0, length: 10);
    $shown = implode(', ', array_map(static fn(int|string $id): string => (string) $id, $shown_ids));
    $extra = $count - count($shown_ids);
    if ($extra > 0) {
        $shown = sprintf('%s, … and %d more', $shown, $extra);
    }
    return new \WP_Error(
        'breakdance_code_element_needs_confirmation',
        sprintf(
            'This Breakdance tree consists only of raw code/HTML elements (count: %1$d; node ids: %2$s). '
            . 'It is always rejected, including after opt-in. Build the page from native Breakdance '
            . 'elements such as Section, Heading, Text, Button, and Image2 instead of dumping raw HTML.',
            $count,
            $shown,
        ),
        ['status' => 422, 'count' => $count, 'node_ids' => $shown_ids],
    );
}

/**
 * Keep an in-band audit trace after an approved raw-code element write.
 *
 * @param list<array<string, mixed>> $nodes
 */
function bd_code_element_audit_warning(array $nodes): ?string
{
    $node_ids = bd_collect_code_element_ids($nodes);
    if ($node_ids === []) {
        return null;
    }
    $count = count($node_ids);
    $shown_ids = array_slice($node_ids, offset: 0, length: 10);
    $shown = implode(', ', array_map(static fn(int|string $id): string => (string) $id, $shown_ids));
    $extra = $count - count($shown_ids);
    if ($extra > 0) {
        $shown = sprintf('%s, … and %d more', $shown, $extra);
    }
    return sprintf(
        'AUDIT — This confirmed write includes %1$d Breakdance Code Block element(s) (node ids: %2$s). '
        . 'They inject raw HTML/PHP/CSS/JS and bypass native Breakdance controls and design-system tooling.',
        $count,
        $shown,
    );
}

/**
 * The five Breakdance CPTs that share the "template-like" shape (a
 * post with `_breakdance_template_settings` postmeta + a builder
 * content blob). Order matches what the admin UI lists.
 *
 * @return list<string>
 */
function bd_template_post_types(): array
{
    return [
        'breakdance_template',
        'breakdance_header',
        'breakdance_footer',
        'breakdance_popup',
        'breakdance_block',
    ];
}

/**
 * Friendly label for each template CPT — used in summary rows so the
 * agent does not need a per-CPT lookup table.
 */
function bd_template_label(string $post_type): string
{
    return match ($post_type) {
        'breakdance_template' => 'Template',
        'breakdance_header' => 'Header',
        'breakdance_footer' => 'Footer',
        'breakdance_popup' => 'Popup',
        'breakdance_block' => 'Global Block',
        default => $post_type,
    };
}

/**
 * Load a template post by id. Returns null when the id does not exist,
 * is trashed, or points at a non-template post type — saves every
 * caller a `get_post` + post_type + status check.
 */
function bd_load_template(int $id): ?\WP_Post
{
    if ($id <= 0) {
        return null;
    }
    /** @var mixed $post */
    $post = get_post($id);
    if (!$post instanceof \WP_Post) {
        return null;
    }
    if (!in_array($post->post_type, bd_template_post_types(), strict: true)) {
        return null;
    }
    if ($post->post_status === 'trash') {
        return null;
    }
    return $post;
}

/**
 * Resolve a post id to a live, non-trashed `WP_Post`, returning a typed
 * `WP_Error` when the post is missing, trashed, or — if `$allowed_post_types`
 * is provided — the wrong type. Element abilities accept ANY post id (not
 * just template CPTs), so we cannot reuse `bd_load_template` everywhere.
 * This helper closes the trash-filter gap that previously allowed silent
 * writes to trashed canvases.
 *
 * @param list<string>|null $allowed_post_types limit the post types accepted;
 *                                              pass null to accept any.
 */
function bd_post_or_error(int $post_id, ?array $allowed_post_types = null): \WP_Post|\WP_Error
{
    if ($post_id <= 0) {
        return bd_error('bd_invalid_input', __('post_id must be a positive integer.', domain: 'novamira-pro'));
    }
    /** @var mixed $post */
    $post = get_post($post_id);
    if (!$post instanceof \WP_Post) {
        return bd_error('bd_not_found', sprintf(__('Post %d not found.', domain: 'novamira-pro'), $post_id));
    }
    if ($post->post_status === 'trash') {
        return bd_error('bd_not_found', sprintf(__('Post %d is in the trash.', domain: 'novamira-pro'), $post_id));
    }
    // WP placeholders that look like posts but cannot host a canvas:
    // revisions are snapshot rows the renderer never picks up, and
    // auto-drafts are WP-cron-purged after 7 days. Without this guard
    // element abilities would silently write into placeholder postmeta.
    if ($post->post_type === 'revision' || $post->post_status === 'auto-draft') {
        return bd_error('bd_not_found', sprintf(
            __('Post %d is a placeholder (revision or auto-draft).', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    if ($allowed_post_types !== null && !in_array($post->post_type, $allowed_post_types, strict: true)) {
        return bd_error('bd_not_found', sprintf(__('Post %d not found.', domain: 'novamira-pro'), $post_id));
    }
    return $post;
}

/**
 * Read the `_breakdance_template_settings` postmeta and decode it as an
 * associative array. Returns [] when the meta is missing or unreadable.
 *
 * Breakdance stores this meta DOUBLE-encoded: the stored value is a JSON
 * string whose content is itself the JSON of the settings object (its
 * `Data\set_meta` json-encodes the already-JSON settings string, and
 * `getTemplateSettingsFromDatabase` json_decodes twice). We decode tolerantly:
 * one decode yields the inner JSON string for the canonical double-encoded
 * shape (Breakdance-native templates and our own writes), which we decode
 * again; a value that decodes straight to an array is a legacy single-encoded
 * blob written before this was fixed, returned as-is. Without the second
 * decode, conditions set natively in the Breakdance UI read back empty.
 *
 * @return array<string, mixed>
 */
function bd_read_template_settings(int $post_id): array
{
    /** @var mixed $raw */
    $raw = get_post_meta(post_id: $post_id, key: '_breakdance_template_settings', single: true);
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $first */
    $first = json_decode(json: $raw, associative: true);
    if (is_array($first)) {
        // Legacy single-encoded value (written before this fix).
        // @mago-expect analysis:less-specific-return-statement
        return $first;
    }
    if (is_string($first)) {
        /** @var mixed $second */
        $second = json_decode(json: $first, associative: true);
        if (is_array($second)) {
            // @mago-expect analysis:less-specific-return-statement
            return $second;
        }
    }
    return [];
}

/**
 * Persist the settings array to the `_breakdance_template_settings` postmeta in
 * Breakdance's canonical DOUBLE-encoded shape: the meta value is the JSON of
 * the settings JSON string, exactly what `Data\set_meta` +
 * `encode_before_writing_to_wp` produce. Storing a plain (single-encoded)
 * object instead makes the value unreadable to Breakdance (it reads null) and
 * breaks the Breakdance templates admin, a JS app that JSON-parses every
 * template's settings and chokes on the mismatched encoding.
 *
 * Round 11 B10 — mirrors `bd_write_tree`'s late-stage trash recheck.
 * A third-party hook (audit-log plugin, mass trash sweep, …) can trash
 * the target post AFTER our caller passed `bd_post_or_error` but BEFORE
 * `update_post_meta` commits. Without this guard the new settings land
 * on a now-trashed post and resurface if the post is restored.
 *
 * @param array<string, mixed> $settings
 */
function bd_write_template_settings(int $post_id, array $settings): bool|\WP_Error
{
    if (get_post_status($post_id) === 'trash') {
        return bd_error('bd_not_found', sprintf(
            __('Post %d is in the trash; refusing to write template settings.', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    if (get_post($post_id) === null) {
        return bd_error('bd_not_found', sprintf(
            __('Post %d not found; refusing to write template settings.', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    $inner = wp_json_encode($settings);
    if ($inner === false) {
        return bd_error('bd_invalid_input', __('Failed to encode the template settings.', domain: 'novamira-pro'));
    }
    $outer = wp_json_encode($inner);
    if ($outer === false) {
        return bd_error('bd_invalid_input', __(
            'Failed to encode the template settings envelope.',
            domain: 'novamira-pro',
        ));
    }
    update_post_meta(post_id: $post_id, meta_key: '_breakdance_template_settings', meta_value: wp_slash($outer));
    return true;
}

/**
 * Public summary row used by list-templates — drops the heavy content
 * blob and surfaces just the keys the agent picks an id with.
 *
 * @return array<string, mixed>
 */
function bd_template_summary(\WP_Post $post): array
{
    $settings = bd_read_template_settings($post->ID);
    $rule_groups = isset($settings['ruleGroups']) && is_array($settings['ruleGroups']) ? $settings['ruleGroups'] : [];
    return [
        'id' => $post->ID,
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'post_type' => $post->post_type,
        'label' => bd_template_label($post->post_type),
        'status' => $post->post_status,
        'modified' => $post->post_modified,
        'type' => isset($settings['type']) && is_string($settings['type']) ? $settings['type'] : '',
        'priority' => isset($settings['priority']) && is_int($settings['priority']) ? $settings['priority'] : 10,
        'rule_groups_count' => count($rule_groups),
    ];
}

/**
 * Full template payload used by get-template — every settings key + the
 * decoded conditions, ready for an edit roundtrip.
 *
 * @return array<string, mixed>
 */
function bd_template_to_array(\WP_Post $post): array
{
    $settings = bd_read_template_settings($post->ID);
    return [
        'id' => $post->ID,
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'post_type' => $post->post_type,
        'label' => bd_template_label($post->post_type),
        'status' => $post->post_status,
        'modified' => $post->post_modified,
        'settings' => $settings,
        'rule_groups' => isset($settings['ruleGroups']) && is_array($settings['ruleGroups'])
            ? $settings['ruleGroups']
            : [],
        'triggers' => isset($settings['triggers']) && is_array($settings['triggers']) ? $settings['triggers'] : [],
        'priority' => isset($settings['priority']) && is_int($settings['priority']) ? $settings['priority'] : 10,
        'fallback' => isset($settings['fallback']) && is_bool($settings['fallback']) ? $settings['fallback'] : false,
        'type' => isset($settings['type']) && is_string($settings['type']) ? $settings['type'] : '',
    ];
}

/**
 * Normalise a value to int. The REST schema layer accepts numeric
 * strings ("42") as `integer` — a typed `int $id` parameter would
 * crash with TypeError. Cast at the ability boundary.
 *
 * @param mixed $value
 */
function bd_coerce_int($value): int
{
    if (is_int($value)) {
        return $value;
    }
    if (is_string($value) && ctype_digit(ltrim($value, characters: '-'))) {
        return (int) $value;
    }
    return 0;
}

/**
 * Resolve a template id from `id` or its element-abilities-style `post_id`
 * alias (`id` wins when both are passed). 0 when neither is set.
 *
 * @param array<string, mixed> $input
 */
function bd_template_id_arg(array $input): int
{
    return bd_coerce_int($input['id'] ?? $input['post_id'] ?? 0);
}

/**
 * Normalise a value to bool. WP REST accepts "true"/"false"/1/0 — cast
 * before any `=== true` comparison.
 *
 * @param mixed $value
 */
function bd_coerce_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
    // Schema-validated REST inputs reach the callback as bool already (WP
    // sanitizes `"true"`/`"false"`/`"1"`/`"0"`/`1`/`0` upstream), so the
    // remaining branches mostly serve internal callers that bypass the
    // schema layer.
    if (is_string($value)) {
        $lower = strtolower($value);
        return $lower === 'true' || $lower === '1';
    }
    if (is_int($value)) {
        return $value === 1;
    }
    return false;
}

/**
 * Map of every Breakdance element type slug → its class, built from every
 * declared `Breakdance\Elements\Element` subclass. This is the set the editor
 * inserts from (mirrors `Breakdance\Elements\get_elements_for_builder()`) and
 * the authoritative answer to "is this a real element type". It deliberately
 * does NOT read Element Studio's authoring rows (`ElementStudioController`),
 * which omit the form, dynamic post/product, and menu elements (PostTitle,
 * GravityForms, FormBuilder, …) — those are valid, insertable elements that
 * register as Element subclasses but never as Element Studio rows. Keyed on the
 * class's `slug()` (normally its fully-qualified class name). Empty when
 * Breakdance is not loaded. ~0.1 ms — cheap enough for the per-add-element
 * resolution path.
 *
 * @return array<string, class-string>
 */
function bd_element_classes(): array
{
    $map = [];
    foreach (get_declared_classes() as $class) {
        if (!is_subclass_of($class, class: 'Breakdance\\Elements\\Element')) {
            continue;
        }
        $slug = bd_element_static_string($class, method: 'slug');
        if ($slug !== '') {
            $map[$slug] = $class;
        }
    }
    return $map;
}

/**
 * Map of every element type slug → its editor metadata (`label`, `category`,
 * `hidden`), keyed by the exact, case-sensitive slug the editor resolves
 * against. The `hidden` flag is true for always-hidden elements (the deprecated
 * "Image V1", the MissingElement placeholder, the Post Featured Image element,
 * preset/internal nodes); the helper reports the registry truthfully and
 * consumers decide whether to surface hidden rows. Empty when Breakdance is
 * not loaded.
 *
 * @return array<string, array{label: string, category: string, hidden: bool}>
 */
function bd_registered_element_types(): array
{
    $types = [];
    foreach (bd_element_classes() as $slug => $class) {
        $types[$slug] = [
            'label' => bd_element_static_string($class, method: 'name'),
            'category' => bd_element_static_string($class, method: 'category'),
            'hidden' => bd_element_is_always_hidden($class),
        ];
    }
    ksort($types);
    return $types;
}

/**
 * The exact, case-sensitive slug of every element type, as a flat list. Lighter
 * than bd_registered_element_types() — it skips the per-class name()/category()/
 * addPanelRules() calls — so it is cheap enough to run on every add-element for
 * type resolution.
 *
 * @return list<string>
 */
function bd_registered_element_slugs(): array
{
    return array_keys(bd_element_classes());
}

/**
 * Call a no-arg static string method (`name()`, `category()`) on a Breakdance
 * element class, guarding for a missing class/method. Returns '' on any miss
 * so a malformed registry entry never fatals the listing.
 */
function bd_element_static_string(string $element_class, string $method): string
{
    if (!class_exists($element_class) || !method_exists($element_class, $method)) {
        return '';
    }
    /** @var callable $callable */
    $callable = [$element_class, $method];
    /** @var mixed $value */
    $value = $callable();
    return is_string($value) ? $value : '';
}

/**
 * Whether an element is flagged `alwaysHide` in its static `addPanelRules()` —
 * i.e. Breakdance keeps it out of the insert panel (deprecated widgets like
 * "Image V1", the MissingElement placeholder, preset/internal nodes). Such
 * slugs are valid tree nodes but must never be auto-normalised to or suggested.
 * Returns false on any miss.
 */
function bd_element_is_always_hidden(string $element_class): bool
{
    if (!class_exists($element_class) || !method_exists($element_class, 'addPanelRules')) {
        return false;
    }
    /** @var callable $callable */
    $callable = [$element_class, 'addPanelRules'];
    /** @var mixed $rules */
    $rules = $callable();
    return is_array($rules) && ($rules['alwaysHide'] ?? false) === true;
}

/**
 * Resolve a caller-supplied element `type` against the live registry:
 *  - exact slug match → returned unchanged;
 *  - unique case-insensitive match → normalised to the registered slug
 *    (so `EssentialElements\heading` becomes `EssentialElements\Heading`);
 *  - no match → a `bd_invalid_input` WP_Error naming the nearest slug(s).
 * When the registry is unavailable (empty) the type passes through unchanged —
 * the calling ability is already gated on Breakdance being active, so this
 * only covers the degenerate empty-registry edge, never a real type.
 *
 * @return string|\WP_Error
 */
function bd_resolve_element_type(string $type): string|\WP_Error
{
    $slugs = bd_registered_element_slugs();
    if ($slugs === []) {
        return $type;
    }
    if (in_array($type, $slugs, strict: true)) {
        return $type;
    }

    // Case-insensitive normalisation, but never auto-correct onto an
    // always-hidden element (a deprecated widget like "Image V1" or an
    // internal placeholder) — those must be chosen explicitly, not landed on
    // by a casing slip. So lowercase `…\image` does NOT become the deprecated
    // `…\Image`; it falls through to a `…\Image2` suggestion below.
    $ci_matches = array_values(array_filter(
        $slugs,
        static fn(string $slug): bool => strcasecmp($slug, $type) === 0 && !bd_element_is_always_hidden($slug),
    ));
    if (count($ci_matches) === 1) {
        return $ci_matches[0];
    }

    $suggestions = bd_nearest_type_slugs($type, $slugs);
    $hint = '';
    if ($suggestions !== []) {
        /* translators: %s: slash-separated list of candidate element type slugs (leading space intentional). */
        $hint = sprintf(
            __(' Did you mean %s?', domain: 'novamira-pro'),
            implode(' / ', array_map(static fn(string $slug): string => "`{$slug}`", $suggestions)),
        );
    }
    return bd_error('bd_invalid_input', sprintf(
        /* translators: 1: rejected element type slug; 2: optional "Did you mean ...?" hint. */
        __(
            'Unknown Breakdance element type `%1$s`.%2$s Call novamira/breakdance-list-element-types to see every registered slug.',
            domain: 'novamira-pro',
        ),
        mb_strimwidth($type, start: 0, width: 80, trim_marker: '…'),
        $hint,
    ));
}

/**
 * The closest registered slugs to a mistyped `type`, for a "did you mean" hint.
 * Containment matches (the bare class name contains the needle or vice versa,
 * case-insensitively) rank first — they catch cross-builder vocabulary like
 * `Map`→`GoogleMap`, `List`→`Postslist`, `Container`→`FancyContainer` that
 * edit-distance buries. Only when nothing contains the needle does it fall back
 * to Levenshtein within a small threshold. Always-hidden elements
 * (deprecated/internal) are never suggested. Capped small to keep the error
 * compact. Pure.
 *
 * @param list<string> $slugs
 * @return list<string>
 */
function bd_nearest_type_slugs(string $type, array $slugs): array
{
    $needle = strtolower(bd_short_type_name($type));
    if ($needle === '') {
        return [];
    }
    $visible = array_values(array_filter($slugs, static fn(string $slug): bool => !bd_element_is_always_hidden($slug)));

    $contained = array_values(array_filter($visible, static function (string $slug) use ($needle): bool {
        $short = strtolower(bd_short_type_name($slug));
        return $short !== '' && (str_contains($short, $needle) || str_contains($needle, $short));
    }));
    if ($contained !== []) {
        usort(
            $contained,
            static fn(string $a, string $b): int => (
                abs(mb_strlen(bd_short_type_name($a)) - mb_strlen($needle)) <=> abs(
                    mb_strlen(bd_short_type_name($b)) - mb_strlen($needle),
                )
            ),
        );
        return array_slice($contained, offset: 0, length: 5);
    }

    $ranked = [];
    foreach ($visible as $slug) {
        $ranked[] = ['slug' => $slug, 'distance' => levenshtein($needle, strtolower(bd_short_type_name($slug)))];
    }
    usort($ranked, static fn(array $a, array $b): int => $a['distance'] <=> $b['distance']);

    $threshold = max(3, (int) (mb_strlen($needle) / 2));
    $out = [];
    foreach (array_slice($ranked, offset: 0, length: 3) as $row) {
        if ($row['distance'] > $threshold) {
            continue;
        }
        $out[] = $row['slug'];
    }
    return $out;
}

/**
 * The bare class name of a backslash-namespaced element slug
 * (`EssentialElements\Heading` → `Heading`). Used for fuzzy matching.
 */
function bd_short_type_name(string $slug): string
{
    $pos = strrpos($slug, needle: '\\');
    return $pos === false ? $slug : substr($slug, $pos + 1);
}
