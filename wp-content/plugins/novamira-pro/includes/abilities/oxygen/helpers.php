<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

if (!defined('ABSPATH')) {
    exit();
}

// Polyfill for `array_is_list` (PHP 8.1+). The plugin header declares
// `Requires PHP: 8.0`, but the input validation in add-element /
// edit-element calls `array_is_list` to refuse list-shaped `properties`.
// Without this polyfill the ability would fatal on PHP 8.0 on the first
// non-empty `properties` payload. Defined inside the Oxygen namespace so
// each specialization owns its own copy — the Breakdance integration
// carries an identical one, they cannot collide.
if (!function_exists('array_is_list')) {
    /**
     * @param array<int|string, mixed> $arr
     */
    function array_is_list(array $arr): bool
    {
        if ($arr === []) {
            return true;
        }
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}

/**
 * Shared helpers for the Oxygen abilities. Centralises the bits each
 * callback would otherwise repeat: CPT enumeration, template loading,
 * settings read/write, error builder with REST status, generic input
 * coercion, and Oxygen element registry resolution.
 *
 * MODE-AWARE STORAGE. Oxygen 6 shares the `Breakdance\*` code namespaces
 * with standalone Breakdance, but the persisted identifiers differ:
 * post types are `oxygen_*` (from the `BREAKDANCE_*_POST_TYPE` global
 * constants), the postmeta prefix is `_oxygen_` (from
 * `__bdox('_meta_prefix')`), and global options are prefixed `oxygen_`.
 * This integration therefore NEVER hard-codes a `breakdance_*` identifier
 * — it always derives it from the engine's own constants and the native
 * `Breakdance\Data\get_meta`/`set_meta` API, which resolve to the
 * `oxygen_*` values at runtime.
 */

/**
 * Build a WP_Error with the HTTP status the REST layer needs. Without
 * the `['status' => 4xx]` payload every WP_Error from the integration
 * collapses to 500 over REST.
 */
/**
 * @param array<string, mixed> $details compact observed-state / rollback data
 */
function ox_error(string $code, string $message, array $details = []): \WP_Error
{
    $status_map = [
        'oxygen_invalid_input' => 400,
        'oxygen_not_found' => 404,
        'oxygen_corrupt_canvas' => 409,
        'oxygen_write_not_applied' => 409,
    ];
    $status = $status_map[$code] ?? 400;
    return new \WP_Error($code, $message, array_merge(['status' => $status], $details));
}

/**
 * The mode-aware postmeta prefix the engine uses for every builder meta
 * key — `_oxygen_` in Oxygen mode, `_breakdance_` in Breakdance mode.
 * Read from the plugin's own `__bdox('_meta_prefix')` helper so canvas /
 * template-settings / form keys always match what the editor writes.
 * Falls back to `_oxygen_` (this specialization is gated on
 * `BREAKDANCE_MODE=oxygen`, so that is the correct default).
 */
function ox_meta_prefix(): string
{
    if (function_exists('Breakdance\\BreakdanceOxygen\\Strings\\__bdox')) {
        /** @var mixed $prefix */
        $prefix = \Breakdance\BreakdanceOxygen\Strings\__bdox('_meta_prefix');
        if (is_string($prefix) && $prefix !== '') {
            return $prefix;
        }
    }
    return '_oxygen_';
}

/**
 * The template-like CPTs an agent can create/edit on this install,
 * derived from the engine's own mode-aware `BREAKDANCE_*_POST_TYPE`
 * global constants (which resolve to `oxygen_template`, `oxygen_header`,
 * `oxygen_footer`, `oxygen_block` in Oxygen mode). Each slug is confirmed
 * with `post_type_exists()` so we never expose a CPT the runtime has not
 * registered. The popup CPT is intentionally excluded: in Oxygen mode it
 * is NOT part of the editable template surface
 * (`BREAKDANCE_ALL_EDITABLE_POST_TYPES` omits it).
 *
 * @return list<string>
 */
function ox_template_post_types(): array
{
    $constants = [
        'BREAKDANCE_TEMPLATE_POST_TYPE',
        'BREAKDANCE_HEADER_POST_TYPE',
        'BREAKDANCE_FOOTER_POST_TYPE',
        'BREAKDANCE_BLOCK_POST_TYPE',
    ];
    $types = [];
    foreach ($constants as $const) {
        if (!defined($const)) {
            continue;
        }
        $slug = (string) constant($const);
        if ($slug !== '' && post_type_exists($slug)) {
            $types[] = $slug;
        }
    }
    // Degenerate fallback (constants not loaded — should not happen since
    // the specialization is gated on Oxygen being active).
    return $types !== [] ? $types : ['oxygen_template', 'oxygen_header', 'oxygen_footer', 'oxygen_block'];
}

/**
 * Friendly label for a template CPT — matched on the slug SUFFIX so it is
 * correct in either mode (`oxygen_template` and `breakdance_template`
 * both → "Template").
 */
function ox_template_label(string $post_type): string
{
    if (str_ends_with($post_type, '_template')) {
        return 'Template';
    }
    if (str_ends_with($post_type, '_header')) {
        return 'Header';
    }
    if (str_ends_with($post_type, '_footer')) {
        return 'Footer';
    }
    if (str_ends_with($post_type, '_block')) {
        return 'Global Block';
    }
    if (str_ends_with($post_type, '_part')) {
        return 'Design Part';
    }
    if (str_ends_with($post_type, '_popup')) {
        return 'Popup';
    }
    return $post_type;
}

/**
 * Load a template post by id. Returns null when the id does not exist,
 * is trashed, or points at a non-template post type — saves every
 * caller a `get_post` + post_type + status check.
 */
function ox_load_template(int $id): ?\WP_Post
{
    if ($id <= 0) {
        return null;
    }
    /** @var mixed $post */
    $post = get_post($id);
    if (!$post instanceof \WP_Post) {
        return null;
    }
    if (!in_array($post->post_type, ox_template_post_types(), strict: true)) {
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
 * is provided — the wrong type. Element abilities accept ANY post id
 * (not just template CPTs), so we cannot reuse `ox_load_template`
 * everywhere. This helper closes the trash-filter gap that previously
 * allowed silent writes to trashed canvases in sibling integrations.
 *
 * Also refuses revisions and auto-drafts — WP placeholders that look
 * like posts but cannot host a canvas.
 *
 * @param list<string>|null $allowed_post_types limit the post types accepted;
 *                                              pass null to accept any.
 */
function ox_post_or_error(int $post_id, ?array $allowed_post_types = null): \WP_Post|\WP_Error
{
    if ($post_id <= 0) {
        return ox_error('oxygen_invalid_input', __('post_id must be a positive integer.', domain: 'novamira-pro'));
    }
    /** @var mixed $post */
    $post = get_post($post_id);
    if (!$post instanceof \WP_Post) {
        return ox_error('oxygen_not_found', sprintf(__('Post %d not found.', domain: 'novamira-pro'), $post_id));
    }
    if ($post->post_status === 'trash') {
        return ox_error('oxygen_not_found', sprintf(__('Post %d is in the trash.', domain: 'novamira-pro'), $post_id));
    }
    if ($post->post_type === 'revision' || $post->post_status === 'auto-draft') {
        return ox_error('oxygen_not_found', sprintf(
            __('Post %d is a placeholder (revision or auto-draft).', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    if ($allowed_post_types !== null && !in_array($post->post_type, $allowed_post_types, strict: true)) {
        return ox_error('oxygen_not_found', sprintf(__('Post %d not found.', domain: 'novamira-pro'), $post_id));
    }
    return $post;
}

/**
 * The mode-aware template-settings meta key — `_oxygen_template_settings`
 * in Oxygen mode.
 */
function ox_template_settings_meta_key(): string
{
    return ox_meta_prefix() . 'template_settings';
}

/**
 * Read the template-settings blob via the native mode-aware Data API and
 * decode it to an associative array. Returns [] when the meta is missing
 * or unreadable.
 *
 * Native shape (`plugin/data/save.php:77` + `plugin/themeless/popups.php:15`):
 * the settings are stored as a JSON STRING; `set_meta` json-encodes it once
 * more on write, and `get_meta` decodes once on read, so `get_meta` returns
 * the settings JSON string, which we `json_decode` once to the array. A
 * value that comes back already as an array is tolerated (legacy / direct
 * writes).
 *
 * @return array<string, mixed>
 */
function ox_read_template_settings(int $post_id): array
{
    if (!function_exists('Breakdance\\Data\\get_meta')) {
        return [];
    }
    /** @var mixed $raw */
    $raw = \Breakdance\Data\get_meta($post_id, ox_template_settings_meta_key());
    if (is_array($raw)) {
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
    if (!is_string($raw) || $raw === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode(json: $raw, associative: true);
    if (!is_array($decoded)) {
        return [];
    }
    $out = [];
    /** @var mixed $value */
    foreach ($decoded as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $out[$key] = $value;
    }
    return $out;
}

/**
 * Compact fingerprint for observed-state diagnostics. It lets callers identify
 * the retained state without returning a potentially huge builder blob.
 */
function ox_state_fingerprint(mixed $value): string
{
    $encoded = wp_json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    return hash('sha256', $encoded === false ? serialize($value) : $encoded);
}

/** Exact observed-state comparison behind a mixed boundary. */
function ox_state_matches(mixed $expected, mixed $observed): bool
{
    return $expected === $observed;
}

/** Capture the raw template-settings value returned by the native Data API. */
function ox_snapshot_template_settings_raw(int $id): mixed
{
    if (!function_exists('Breakdance\\Data\\get_meta')) {
        return null;
    }
    return \Breakdance\Data\get_meta($id, ox_template_settings_meta_key());
}

/**
 * Restore a raw template-settings snapshot, run the same cache/save lifecycle,
 * and then prove the compensating write itself landed.
 *
 * @return array{restored: bool, observed_fingerprint: string}
 */
function ox_restore_template_settings_snapshot(int $id, mixed $snapshot): array
{
    if ($snapshot === null || !function_exists('Breakdance\\Data\\set_meta')) {
        return ['restored' => false, 'observed_fingerprint' => ox_state_fingerprint(null)];
    }
    if ($snapshot === false) {
        delete_post_meta($id, ox_template_settings_meta_key());
        ox_after_document_save($id);
        // The lifecycle fires save_post and a listener may re-create the value.
        // Re-apply the compensating mutation LAST, then observe that final state.
        delete_post_meta($id, ox_template_settings_meta_key());
        /** @var mixed $observed */
        $observed = \Breakdance\Data\get_meta($id, ox_template_settings_meta_key());
        return [
            'restored' => $observed === false,
            'observed_fingerprint' => ox_state_fingerprint($observed),
        ];
    }
    \Breakdance\Data\set_meta($id, ox_template_settings_meta_key(), $snapshot);
    ox_after_document_save($id);
    // See the false/missing branch above: cache lifecycle first, exact snapshot
    // last, otherwise the same hook that caused the failure can defeat rollback.
    \Breakdance\Data\set_meta($id, ox_template_settings_meta_key(), $snapshot);
    /** @var mixed $observed */
    $observed = \Breakdance\Data\get_meta($id, ox_template_settings_meta_key());
    return [
        'restored' => $observed === $snapshot,
        'observed_fingerprint' => ox_state_fingerprint($observed),
    ];
}

/**
 * Persist the settings array via the native mode-aware Data API. We JSON-
 * encode the array to a string and hand that string to `set_meta`, which
 * json-encodes+slashes it exactly as the Oxygen UI does (see the native
 * save path). Writing the decoded array directly would produce a shape the
 * builder cannot read.
 *
 * A third-party hook (audit-log plugin, mass trash sweep, …) can trash the
 * target post AFTER the caller passed `ox_post_or_error` but BEFORE the
 * write commits, so we re-check trash/existence just before writing.
 *
 * @param array<string, mixed> $settings
 */
function ox_write_template_settings(int $post_id, array $settings): bool|\WP_Error
{
    if (get_post_status($post_id) === 'trash') {
        return ox_error('oxygen_not_found', sprintf(
            __('Post %d is in the trash; refusing to write template settings.', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    if (get_post($post_id) === null) {
        return ox_error('oxygen_not_found', sprintf(
            __('Post %d not found; refusing to write template settings.', domain: 'novamira-pro'),
            $post_id,
        ));
    }
    if (!function_exists('Breakdance\\Data\\set_meta')) {
        return ox_error('oxygen_invalid_input', __(
            'The Oxygen Data API is unavailable; cannot write template settings.',
            domain: 'novamira-pro',
        ));
    }
    $json = wp_json_encode($settings);
    if ($json === false) {
        return ox_error('oxygen_invalid_input', __('Failed to encode the template settings.', domain: 'novamira-pro'));
    }
    // @mago-expect analysis:mixed-assignment
    $before = ox_snapshot_template_settings_raw($post_id);
    \Breakdance\Data\set_meta($post_id, ox_template_settings_meta_key(), $json);
    // Template CPTs render too — reproduce the native document-save effects
    // (modified date, CSS/dependency cache regen, cache-clear hook) FIRST, THEN
    // verify. `ox_after_document_save` runs `wp_update_post`, whose `save_post` /
    // `post_updated` listeners can still revert `_oxygen_template_settings`, so
    // the observed-state check must run AFTER the full lifecycle.
    ox_after_document_save($post_id);
    // Observed-state: read the persisted settings string back and require an
    // exact match, so a hook that blocks/rewrites/reverts the meta is caught
    // here instead of the ability returning success with settings missing.
    /** @var mixed $stored */
    $stored = \Breakdance\Data\get_meta($post_id, ox_template_settings_meta_key());
    if ($stored !== $json) {
        $rollback = ox_restore_template_settings_snapshot($post_id, $before);
        $rollback_message = $rollback['restored']
            ? 'The previous settings were restored and re-verified.'
            : sprintf(
                'Rollback was not fully applied; retained settings fingerprint: %s.',
                $rollback['observed_fingerprint'],
            );
        return ox_error(
            'oxygen_write_not_applied',
            sprintf(
                __('The template settings of post %1$d did not persist as requested. %2$s', domain: 'novamira-pro'),
                $post_id,
                $rollback_message,
            ),
            [
                'requested_fingerprint' => ox_state_fingerprint($json),
                'rollback' => $rollback,
            ],
        );
    }
    return true;
}

/**
 * Confirm a wp_trash_post / wp_delete_post actually changed the row's state,
 * and return the mode that really happened. A `pre_trash_post` / `pre_delete_post`
 * filter can short-circuit and return a truthy value while leaving the row
 * untouched, so the truthy return is a declaration, not proof. We read the row
 * back:
 *  - force: the row must be GONE (get_post === null), else the delete was blocked.
 *  - safe : the row must be in `trash`; if it is GONE the CPT force-empties its
 *    trash (EMPTY_TRASH_DAYS === 0) so it was effectively force-deleted; if it is
 *    still present in any other status the trash was blocked.
 * Returns the verified mode (`trashed` | `force_deleted`) or a WP_Error.
 */
// @mago-expect lint:no-boolean-flag-parameter
function ox_verify_delete_outcome(int $id, bool $force): string|\WP_Error
{
    /** @var mixed $after */
    $after = get_post($id);
    if ($force) {
        if ($after !== null) {
            return ox_error('oxygen_write_not_applied', sprintf(
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
    return ox_error('oxygen_write_not_applied', sprintf(
        __('Post %d was not trashed; a hook may have blocked the operation.', domain: 'novamira-pro'),
        $id,
    ));
}

/**
 * The CLOSED set of top-level keys the Oxygen 6 engine actually CONSUMES from a
 * template-settings blob for the editable template CPTs (verified against the
 * native read sites: `plugin/themeless/request.php`, `god-controller.php`,
 * `renderer.php`). `type`, `priority`, `fallback`, `disabled`, `parentId` are
 * read for every template CPT. `triggers` is popup-only and this integration
 * exposes no popup CPT, so it is deliberately read-only. Every OTHER key the
 * engine ignores — persisting one is a silent
 * no-op that reports success while doing nothing, so we reject it. The
 * visibility rules (`ruleGroups` / `conditions`) are handled separately with a
 * pointer to oxygen-set-template-conditions.
 *
 * @return list<string>
 */
function ox_template_settings_allowed_keys(): array
{
    return ['type', 'priority', 'fallback', 'disabled', 'parentId'];
}

/**
 * Apply the public template-settings patch contract: omitted keys are kept and
 * a literal null removes an allowed key instead of persisting an engine-ignored
 * null value. Used for both create (empty base) and edit.
 *
 * @param array<string, mixed> $existing
 * @param array<string, mixed> $patch
 * @return array<string, mixed>
 */
function ox_merge_template_settings_patch(array $existing, array $patch): array
{
    $merged = array_merge($existing, $patch);
    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        if ($value !== null) {
            continue;
        }
        unset($merged[$key]);
    }
    return $merged;
}

/**
 * Validate a create/edit-template `settings` input bag BEFORE any write, as a
 * CLOSED schema — every key must be one the engine consumes, with its real type:
 *  - `type` must be a registered template type (empty/absent = unscoped).
 *  - `priority` must be an integer (a numeric STRING "20" persists verbatim while
 *    the is_int read-surfaces report the default, so the agent cannot tell).
 *  - `fallback` / `disabled` must be booleans.
 *  - `parentId` must be an integer; when non-zero it must reference an existing
 *    template post and must not introduce an inheritance cycle.
 *  - A literal `null` unsets an allowed key during a merge edit (and is dropped
 *    from create input); this matches the agent-facing patch contract.
 *  - `triggers` is intentionally not writable: Oxygen consumes it only for the
 *    popup controller, while this integration exposes no popup CPT.
 *  - The visibility rules (`ruleGroups` / `conditions`) are OWNED by
 *    oxygen-set-template-conditions (the single source of truth, with the
 *    slug/operand/value-membership validator) — routed there, not persisted here.
 *  - Any UNKNOWN key is rejected: the engine never reads it, so persisting it
 *    would report success for a write that does nothing.
 * Returns a WP_Error to bail with, or null when the bag is clean.
 *
 * @param array<array-key, mixed> $settings
 */
function ox_validate_template_settings_input(array $settings, int $target_id = 0): ?\WP_Error
{
    foreach (['ruleGroups', 'conditions'] as $key) {
        if (!array_key_exists($key, $settings)) {
            continue;
        }
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: %s: the rejected settings key */
            __(
                'Template visibility rules cannot be set through `settings.%s`. Use the oxygen-set-template-conditions ability, which validates rule slugs, operands, and values against the live catalogue.',
                domain: 'novamira-pro',
            ),
            $key,
        ));
    }
    $allowed = ox_template_settings_allowed_keys();
    foreach (array_keys($settings) as $key) {
        // A non-string (integer) key can never be an allowed setting, so it must
        // be REJECTED, not skipped: a JSON object like `{"5":"x"}` decodes to an
        // int-keyed array, and skipping it would persist engine-ignored garbage
        // that read-back (which drops non-string keys) hides — the exact silent
        // no-op this closed schema exists to prevent.
        if (is_string($key) && in_array($key, $allowed, strict: true)) {
            continue;
        }
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: the rejected key, 2: the allowed key list */
            __(
                '`settings.%1$s` is not a template setting the Oxygen engine reads, so persisting it would silently do nothing. Allowed keys: %2$s (visibility rules go through oxygen-set-template-conditions).',
                domain: 'novamira-pro',
            ),
            (string) $key,
            implode(', ', $allowed),
        ));
    }
    $type_error = ox_validate_template_settings_types($settings, $target_id);
    if ($type_error instanceof \WP_Error) {
        return $type_error;
    }
    if (array_key_exists('type', $settings)) {
        return ox_validate_template_type($settings['type']);
    }
    return null;
}

/**
 * Per-key type checks for the closed template-settings schema. Split out so the
 * validator stays under the cyclomatic-complexity budget.
 *
 * @param array<array-key, mixed> $settings
 */
function ox_validate_template_settings_types(array $settings, int $target_id = 0): ?\WP_Error
{
    if (array_key_exists('priority', $settings) && $settings['priority'] !== null && !is_int($settings['priority'])) {
        return ox_error('oxygen_invalid_input', __(
            '`settings.priority` must be an integer, not a string (higher wins when several templates match).',
            domain: 'novamira-pro',
        ));
    }
    foreach (['fallback', 'disabled'] as $bool_key) {
        if (array_key_exists($bool_key, $settings) && $settings[$bool_key] !== null && !is_bool($settings[$bool_key])) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: %s: the settings key */
                __('`settings.%s` must be a boolean (true/false).', domain: 'novamira-pro'),
                $bool_key,
            ));
        }
    }
    if (array_key_exists('parentId', $settings)) {
        /** @var mixed $parent */
        $parent = $settings['parentId'];
        if ($parent === null) {
            return null;
        }
        if (!is_int($parent)) {
            return ox_error('oxygen_invalid_input', __(
                '`settings.parentId` must be an integer post id.',
                domain: 'novamira-pro',
            ));
        }
        if ($parent !== 0) {
            return ox_validate_template_parent($target_id, $parent);
        }
    }
    return null;
}

/**
 * Validate a prospective template parent against the persisted ancestor chain.
 * Oxygen's native hierarchy builder terminates the request when it encounters a
 * cycle, so existence alone is not enough: reject self-parenting, two-node
 * cycles, and an already-corrupt ancestor chain before writing.
 */
function ox_validate_template_parent(int $target_id, int $parent_id): ?\WP_Error
{
    $seen = [];
    $current = $parent_id;
    while ($current > 0) {
        if ($current === $target_id && $target_id > 0) {
            return ox_error('oxygen_invalid_input', __(
                '`settings.parentId` would create a circular Oxygen template hierarchy.',
                domain: 'novamira-pro',
            ));
        }
        if (isset($seen[$current])) {
            return ox_error('oxygen_invalid_input', sprintf(
                __(
                    'Template parent chain is already circular at template %d; refusing to attach to it.',
                    domain: 'novamira-pro',
                ),
                $current,
            ));
        }
        $seen[$current] = true;
        if (ox_load_template($current) === null) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: %d: the rejected parent id */
                __(
                    '`settings.parentId` %d is not an existing Oxygen template; the inheritance walk would break on it.',
                    domain: 'novamira-pro',
                ),
                $current,
            ));
        }
        $settings = ox_read_template_settings($current);
        /** @var mixed $next */
        $next = $settings['parentId'] ?? 0;
        if (!is_int($next)) {
            return ox_error('oxygen_invalid_input', sprintf(
                __('Template %d has a malformed parentId; refusing to extend its hierarchy.', domain: 'novamira-pro'),
                $current,
            ));
        }
        $current = $next;
    }
    return null;
}

/**
 * Public summary row used by list-templates — drops the heavy content
 * blob and surfaces just the keys the agent picks an id with.
 *
 * @return array<string, mixed>
 */
function ox_template_summary(\WP_Post $post): array
{
    $settings = ox_read_template_settings($post->ID);
    $rule_groups = isset($settings['ruleGroups']) && is_array($settings['ruleGroups']) ? $settings['ruleGroups'] : [];
    return [
        'id' => $post->ID,
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'post_type' => $post->post_type,
        'label' => ox_template_label($post->post_type),
        'status' => $post->post_status,
        'modified' => $post->post_modified,
        'type' => isset($settings['type']) && is_string($settings['type']) ? $settings['type'] : '',
        'priority' => isset($settings['priority']) && is_int($settings['priority']) ? $settings['priority'] : 1,
        'rule_groups_count' => count($rule_groups),
    ];
}

/**
 * Full template payload used by get-template — every settings key + the
 * decoded conditions, ready for an edit roundtrip.
 *
 * @return array<string, mixed>
 */
function ox_template_to_array(\WP_Post $post): array
{
    $settings = ox_read_template_settings($post->ID);
    return [
        'id' => $post->ID,
        'title' => $post->post_title,
        'slug' => $post->post_name,
        'post_type' => $post->post_type,
        'label' => ox_template_label($post->post_type),
        'status' => $post->post_status,
        'modified' => $post->post_modified,
        'settings' => $settings,
        'rule_groups' => isset($settings['ruleGroups']) && is_array($settings['ruleGroups'])
            ? $settings['ruleGroups']
            : [],
        'triggers' => isset($settings['triggers']) && is_array($settings['triggers']) ? $settings['triggers'] : [],
        'priority' => isset($settings['priority']) && is_int($settings['priority']) ? $settings['priority'] : 1,
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
function ox_coerce_int($value): int
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
function ox_template_id_arg(array $input): int
{
    return ox_coerce_int($input['id'] ?? $input['post_id'] ?? 0);
}

/**
 * Normalise a value to bool. WP REST accepts "true"/"false"/1/0 — cast
 * before any `=== true` comparison.
 *
 * @param mixed $value
 */
function ox_coerce_bool($value): bool
{
    if (is_bool($value)) {
        return $value;
    }
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
 * Map of every registered element type slug → its class, built from every
 * declared `Breakdance\Elements\Element` subclass (the shared Soflyy
 * element registry, used in Oxygen mode too). On a base Oxygen 6 install
 * this is the ~21 native `OxygenElements\*` elements (Container, Text,
 * RichText, Image, PostsLoop, PhpCode, HtmlCode, …); installing the
 * optional "Breakdance Elements for Oxygen" extension adds the
 * `EssentialElements\*` set (Section, Heading, Image2, …).
 *
 * The slug is each element's `slug()` return value — for the built-in
 * elements this is the fully-qualified class name (CamelCase, e.g.
 * `OxygenElements\PhpCode`). Keyed on that slug. Empty when Oxygen is not
 * loaded. Cheap enough for the per-add-element resolution path.
 *
 * @return array<string, class-string>
 */
function ox_element_classes(): array
{
    $map = [];
    foreach (get_declared_classes() as $class) {
        if (!is_subclass_of($class, class: 'Breakdance\\Elements\\Element')) {
            continue;
        }
        $slug = ox_element_static_string($class, method: 'slug');
        if ($slug !== '') {
            $map[$slug] = $class;
        }
    }
    return $map;
}

/**
 * Map of every element type slug → its editor metadata (`label`,
 * `category`, `hidden`, `available`), keyed by the exact, case-sensitive
 * slug the editor resolves against. Reproduces the engine's builder
 * visibility logic:
 *  - `available` is false when the element's `availableIn()` does not
 *    contain the current `BREAKDANCE_MODE` — the builder then excludes it
 *    entirely (`plugin/elements/elements-for-builder.php:62-65`).
 *  - `hidden` is true when the element is flagged `alwaysHide` in its own
 *    `addPanelRules()` OR was dropped by the `breakdance_builder_elements`
 *    filter (the `builder_hidden_elements` option Oxygen mode seeds by
 *    default) — the builder keeps it renderable but out of the add panel
 *    (`plugin/elements/filtered-gets.php:166-172`).
 * The helper reports the registry truthfully and consumers decide whether
 * to surface hidden/unavailable rows.
 *
 * @return array<string, array{label: string, category: string, hidden: bool, available: bool}>
 */
function ox_registered_element_types(): array
{
    $visible = ox_panel_visible_classnames();
    $types = [];
    foreach (ox_element_classes() as $slug => $class) {
        $types[$slug] = [
            'label' => ox_element_static_string($class, method: 'name'),
            'category' => ox_element_static_string($class, method: 'category'),
            'hidden' => ox_element_is_always_hidden($class) || !in_array($class, $visible, strict: true),
            'available' => ox_element_available_in_mode($class),
        ];
    }
    ksort($types);
    return $types;
}

/**
 * The exact, case-sensitive slug of every element type, as a flat list.
 * Lighter than ox_registered_element_types() — it skips the per-class
 * name()/category()/addPanelRules() calls — so it is cheap enough to run
 * on every add-element for type resolution.
 *
 * @return list<string>
 */
function ox_registered_element_slugs(): array
{
    return array_keys(ox_element_classes());
}

/**
 * Call a no-arg static string method (`name()`, `category()`) on an
 * Oxygen element class, guarding for a missing class/method. Returns ''
 * on any miss so a malformed registry entry never fatals the listing.
 */
function ox_element_static_string(string $element_class, string $method): string
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
 * Whether an element is flagged `alwaysHide` in its static
 * `addPanelRules()` — i.e. Oxygen keeps it out of the insert panel
 * (deprecated widgets, MissingElement placeholder, preset / internal
 * nodes). Such slugs are valid tree nodes but must never be
 * auto-normalised to or suggested. Returns false on any miss.
 */
function ox_element_is_always_hidden(string $element_class): bool
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
 *  - unique case-insensitive match → normalised to the registered slug;
 *  - no match → a WP_Error naming the nearest slug(s).
 * When the registry is unavailable (empty) the type passes through
 * unchanged — the calling ability is already gated on Oxygen being
 * active, so this only covers the degenerate empty-registry edge.
 *
 * @return string|\WP_Error
 */
function ox_resolve_element_type(string $type): string|\WP_Error
{
    $slugs = ox_registered_element_slugs();
    if ($slugs === []) {
        return $type;
    }
    if (in_array($type, $slugs, strict: true)) {
        return $type;
    }

    $ci_matches = array_values(array_filter(
        $slugs,
        static fn(string $slug): bool => strcasecmp($slug, $type) === 0 && !ox_element_is_always_hidden($slug),
    ));
    if (count($ci_matches) === 1) {
        return $ci_matches[0];
    }

    $suggestions = ox_nearest_type_slugs($type, $slugs);
    $hint = '';
    if ($suggestions !== []) {
        /* translators: %s: slash-separated list of candidate element type slugs (leading space intentional). */
        $hint = sprintf(
            __(' Did you mean %s?', domain: 'novamira-pro'),
            implode(' / ', array_map(static fn(string $slug): string => "`{$slug}`", $suggestions)),
        );
    }
    return ox_error('oxygen_invalid_input', sprintf(
        /* translators: 1: rejected element type slug; 2: optional "Did you mean ...?" hint. */
        __(
            'Unknown Oxygen element type `%1$s`.%2$s Call novamira/oxygen-list-element-types to see every registered slug.',
            domain: 'novamira-pro',
        ),
        mb_strimwidth($type, start: 0, width: 80, trim_marker: '…'),
        $hint,
    ));
}

/**
 * The closest registered slugs to a mistyped `type`, for a "did you
 * mean" hint. Containment matches rank first — they catch cross-builder
 * vocabulary like `Map`→`GoogleMap`, `List`→`Postslist`,
 * `Container`→`FancyContainer` that edit-distance buries. Only when
 * nothing contains the needle does it fall back to Levenshtein within a
 * small threshold. Always-hidden elements are never suggested. Capped
 * small to keep the error compact.
 *
 * @param list<string> $slugs
 * @return list<string>
 */
function ox_nearest_type_slugs(string $type, array $slugs): array
{
    $needle = strtolower(ox_short_type_name($type));
    if ($needle === '') {
        return [];
    }
    $visible = array_values(array_filter($slugs, static fn(string $slug): bool => !ox_element_is_always_hidden($slug)));

    $contained = array_values(array_filter($visible, static function (string $slug) use ($needle): bool {
        $short = strtolower(ox_short_type_name($slug));
        return $short !== '' && (str_contains($short, $needle) || str_contains($needle, $short));
    }));
    if ($contained !== []) {
        usort(
            $contained,
            static fn(string $a, string $b): int => (
                abs(mb_strlen(ox_short_type_name($a)) - mb_strlen($needle)) <=> abs(
                    mb_strlen(ox_short_type_name($b)) - mb_strlen($needle),
                )
            ),
        );
        return array_slice($contained, offset: 0, length: 5);
    }

    $ranked = [];
    foreach ($visible as $slug) {
        $ranked[] = ['slug' => $slug, 'distance' => levenshtein($needle, strtolower(ox_short_type_name($slug)))];
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
function ox_short_type_name(string $slug): string
{
    $pos = strrpos($slug, needle: '\\');
    return $pos === false ? $slug : substr($slug, $pos + 1);
}

/**
 * Slugs registered under the native `OxygenElements\` namespace — the
 * Oxygen-native integration elements shipped by the base plugin's
 * `subplugins/oxygen-elements/` (Container, Text, RichText, Image,
 * PostsLoop, PhpCode, HtmlCode, CssCode, JavaScriptCode, oEmbed,
 * Shortcode, ContainerShortcode, WpWidget, DynamicDataLoop,
 * TermLoopBuilder, …). Derived from the LIVE registry, never hard-coded,
 * so it stays correct across plugin versions and reports exactly what the
 * runtime registered.
 *
 * @return list<string>
 */
function ox_oxygen_native_slugs(): array
{
    return array_values(array_filter(ox_registered_element_slugs(), static fn(string $slug): bool => str_starts_with(
        $slug,
        'OxygenElements\\',
    )));
}

/**
 * Slugs registered under the `EssentialElements\` namespace. NOTE: a base
 * Oxygen 6.1.1 install already registers ~22 EssentialElements *integration*
 * types (ContactForm7, GravityForms, AtomV1Button, Image, MissingElement,
 * …) that are NOT the builder pack. The mere presence of EssentialElements
 * slugs therefore does NOT mean the extension is installed — use
 * ox_essential_extension_present() for that. Derived from the live registry.
 *
 * @return list<string>
 */
function ox_essential_element_slugs(): array
{
    return array_values(array_filter(ox_registered_element_slugs(), static fn(string $slug): bool => str_starts_with(
        $slug,
        'EssentialElements\\',
    )));
}

/**
 * The element-type slugs gated behind the explicit `allow_code_elements`
 * confirmation flag, mirroring the Bricks "code" element gate. Each of
 * these renders operator-supplied code verbatim on every front-end
 * render — `OxygenElements\PhpCode` and `EssentialElements\CodeBlock`
 * are literally `eval()`'d server-side (see the engine's
 * `subplugins/oxygen-elements/elements/PHP_Code/ssr.php` and the
 * extension's `Code_Block/ssr.php`), Html/Css/JavaScriptCode inject raw
 * unsanitised markup/CSS/JS. The shortcode embeds
 * (`OxygenElements\Shortcode` / `ContainerShortcode`) are deliberately
 * NOT gated: they can only invoke already-registered WP shortcodes, the
 * same capability every WP content editor has — matching Bricks, which
 * gates only its raw `code` element.
 *
 * @return list<string>
 */
function ox_gated_code_element_slugs(): array
{
    return [
        'OxygenElements\\PhpCode',
        'OxygenElements\\HtmlCode',
        'OxygenElements\\CssCode',
        'OxygenElements\\JavaScriptCode',
        'EssentialElements\\CodeBlock',
    ];
}

/**
 * Whether a RESOLVED element-type slug is one of the gated raw-code
 * elements. Callers must resolve the type first (`ox_resolve_element_type`)
 * so a case-normalised alias cannot slip past the gate.
 */
function ox_is_gated_code_type(string $type): bool
{
    return in_array($type, ox_gated_code_element_slugs(), strict: true);
}

/**
 * The confirmation gate returned when a write would introduce executable
 * code — either a gated code ELEMENT type or an executable SETTINGS payload
 * (see ox_collect_executable_payloads) — without the explicit
 * `allow_code_elements` opt-in. The agent must confirm with the USER and
 * re-call with `allow_code_elements: true`; it must never set the flag on
 * its own initiative (double-approval). Mirrors `bricks_code_element_block()`.
 *
 * @param list<string> $labels the offending nodes/paths, e.g. `OxygenElements\PhpCode` or `properties.settings.conditions.conditions[0][0] (custom-php condition — eval()'d PHP)`
 */
function ox_code_payload_block(array $labels): \WP_Error
{
    return ox_error('oxygen_code_element_needs_confirmation', sprintf(
        /* translators: 1: number of offending payloads, 2: list of offending element types/paths */
        __(
            'This write would introduce or ARM %1$d executable code payload(s) (%2$s). These run operator-supplied code on every render — code elements (PhpCode/CodeBlock are eval()\'d server-side, Html/Css/JavaScriptCode inject raw unsanitised markup), `custom-php` display/visibility conditions, PHP-mode wp_query envelopes, `term_query` envelopes, and `phpreturn` / `process_value` dynamic-data payloads are ALL eval()\'d by the engine — and are a LAST RESORT. A payload counts even when the request carries no code itself: clearing `settings.disabled` or attaching a `settings.parentId` makes the engine start running code a template ALREADY stores. Build content, layout, visibility and queries with native elements, registered conditions, the query controls, and registered dynamic-data fields instead. Only if the user has EXPLICITLY confirmed they want raw executable code, re-call with "allow_code_elements": true — do NOT set that flag on your own.',
            domain: 'novamira-pro',
        ),
        count($labels),
        implode(', ', $labels),
    ));
}

/**
 * Condition slugs whose native callback EXECUTES the rule's `value` as PHP.
 * `custom-php`'s callback is literally `return !!eval($value);` (engine
 * `plugin/themeless/rules/conditions/custom-php.php:35`) and it declares
 * `supports => ['element_display', 'templating']` (custom-php.php:15), so it
 * is reachable BOTH from an element's `settings.conditions.conditions` (engine
 * `plugin/conditions/utils.php:15` → `should-show-node.php:29-31` →
 * `doesRuleGroupApply`) and from a template's `ruleGroups`
 * (`plugin/themeless/request.php:186-193`). Writing one is therefore
 * equivalent to writing a PhpCode element and gets the same gate.
 *
 * @return list<string>
 */
function ox_executable_condition_slugs(): array
{
    return ['custom-php'];
}

/**
 * Dynamic-data field slugs whose handler EXECUTES a caller-supplied
 * attribute as PHP. `phpreturn`'s handler is `eval($attributes['code'])`
 * (engine `plugin/dynamic-data/fields/utility/php-function.php:27,68`).
 *
 * @return list<string>
 */
function ox_executable_dynamic_field_slugs(): array
{
    return ['phpreturn'];
}

/**
 * Normalise a slug/operand for tolerant comparison: lowercase, with spaces,
 * hyphens and underscores stripped. Alias spellings (`custom_php`,
 * `Custom PHP`, `CUSTOMPHP`) must not slip past a gate that the canonical
 * spelling would catch.
 */
function ox_normalize_slug(string $value): string
{
    return strtr(strtolower($value), [' ' => '', '_' => '', '-' => '']);
}

/** Whether a rule slug names a condition whose value the engine eval()s. */
function ox_is_executable_condition_slug(mixed $slug): bool
{
    if (!is_string($slug)) {
        return false;
    }
    $needle = ox_normalize_slug($slug);
    foreach (ox_executable_condition_slugs() as $executable) {
        if (ox_normalize_slug($executable) === $needle) {
            return true;
        }
    }
    return false;
}

/**
 * THE EXECUTABLE-PAYLOAD SHAPE TABLE — array-node shapes.
 *
 * HOW THIS SET WAS ENUMERATED. Not "by inspection": every construct in the
 * extracted Oxygen 6.1.1 engine that can run a value read back out of PERSISTED
 * element properties or template settings was located mechanically — `eval(`,
 * `call_user_func`, `create_function`, and the dynamic-shortcode evaluators
 * (`breakdanceDoShortcode` / `renderDynamicShortcodes` / `pipeValueThroughProcessValueReturn`)
 * — and each hit was followed back to the property path it reads. A site earns a
 * row here when the value it executes is reachable through one of our write
 * abilities. Sites deliberately NOT rowed are recorded under
 * ox_non_agent_writable_execution_sites() with the reason.
 *
 * | shape id              | engine site                                          | value it executes                                                    |
 * |-----------------------|------------------------------------------------------|----------------------------------------------------------------------|
 * | custom-php-condition  | plugin/themeless/rules/conditions/custom-php.php:35   | a rule's `value` (element `settings.conditions.conditions` + template `ruleGroups`) |
 * | wp-query-php-envelope | plugin/wp-query-control/query-control.php:58-61       | `…query.php` when `…query.active === 'php'`                          |
 * | term-query-envelope   | subplugins/breakdance-elements/lib/term-builder.php:52-57 | `content.query.term_query` when `content.query.load_terms_by_query` |
 *
 * Matching is shape-driven, NOT path-driven: the engine reads these structures
 * from fixed paths, but an agent can nest them anywhere the property schema
 * tolerates, so matching by SHAPE closes the alias / nesting variants a path
 * allowlist would miss. Adding a newly-found execution site is ONE ROW — the
 * walker never changes.
 *
 * @return list<array{id: string, why: string, match: callable(array<array-key, mixed>): bool}>
 */
function ox_executable_node_shapes(): array
{
    return [
        [
            'id' => 'custom-php-condition',
            'why' => 'a `custom-php` condition rule — the engine eval()s its `value` as PHP (custom-php.php:35)',
            'match' => static fn(array $node): bool => ox_is_executable_condition_rule($node),
        ],
        [
            'id' => 'wp-query-php-envelope',
            'why' => 'a wp_query envelope in PHP mode — the engine eval()s `php` (query-control.php:58-61)',
            'match' => static fn(array $node): bool => ox_is_php_query_envelope($node),
        ],
        [
            'id' => 'term-query-envelope',
            'why' => 'a term-query envelope — the engine eval()s `term_query` (term-builder.php:52-57)',
            'match' => static fn(array $node): bool => ox_is_term_query_envelope($node),
        ],
    ];
}

/**
 * Engine execution sites found by the sweep above that are deliberately NOT
 * gated, each with the reason it is not agent-writable. Kept as executable
 * documentation so a future round can re-audit the exemption list instead of
 * re-deriving it, and so an exemption that stops holding is a visible edit.
 *
 * @return array<string, string>
 */
function ox_non_agent_writable_execution_sites(): array
{
    return [
        'frontendCondition' => 'CONDITIONAL, so it is verified at runtime rather than argued in prose: ox_raw_frontend_condition_paths() inspects the elements the site actually has installed and reports any that fall outside the exemption, and the smoke suite fails on a non-empty result.',
        'plugin/dynamic-data/ajax-get-data.php:100' => 'Evaluates a shortcode taken from the REQUEST, not from persisted properties; it is the builder\'s own preview endpoint and is gated by Oxygen\'s builder capability check, not by a write ability.',
        'includes/abilities/code-snippets/validate-snippet.php' => 'Ours, not the engine\'s: parse-only eval inside `if (false)`, the snippet body is never invoked.',
    ];
}

/**
 * The installed element classes whose `frontendCondition` interpolates a BARE
 * property path, keyed by class name, with the offending paths as the value.
 *
 * This is the executable form of the frontendCondition exemption above. A
 * `{{ … }}` whose body is a bare dotted path prints the STORED VALUE into the
 * string renderer.php:560 eval()s; a `{{ … }}` whose body is a comparison
 * expression prints only Twig's boolean rendering ("1"/""), and a `{% … %}` tag
 * prints nothing — neither can carry a payload. So a non-empty return here means
 * the exemption has stopped holding on THIS install and the value at those paths
 * needs gating on write.
 *
 * Reads the LIVE registry rather than a hardcoded list, so a third-party or
 * extension element is covered the moment it is activated.
 *
 * @return array<string, list<string>>
 */
function ox_raw_frontend_condition_paths(): array
{
    $out = [];
    foreach (ox_element_classes() as $class) {
        $paths = ox_raw_paths_in_conditions(ox_element_frontend_conditions($class));
        if ($paths === []) {
            continue;
        }
        $out[$class] = $paths;
    }
    return $out;
}

/**
 * The `frontendCondition` Twig templates one element class declares.
 *
 * @return list<string>
 */
function ox_element_frontend_conditions(string $element_class): array
{
    if (!class_exists($element_class) || !method_exists($element_class, 'dependencies')) {
        return [];
    }
    /** @var callable $callable */
    $callable = [$element_class, 'dependencies'];
    /** @var mixed $dependencies */
    $dependencies = $callable();
    if (!is_array($dependencies)) {
        return [];
    }
    $out = [];
    /** @var mixed $dependency */
    foreach ($dependencies as $dependency) {
        /** @var mixed $condition */
        $condition = is_array($dependency) ? $dependency['frontendCondition'] ?? null : null;
        if (!is_string($condition) || trim($condition) === '') {
            continue;
        }
        $out[] = $condition;
    }
    return $out;
}

/**
 * The bare dotted property paths a set of Twig templates interpolates with
 * `{{ … }}`.
 *
 * @param list<string> $conditions
 * @return list<string>
 */
function ox_raw_paths_in_conditions(array $conditions): array
{
    $paths = [];
    foreach ($conditions as $condition) {
        $matches = [];
        $hits = preg_match_all('/\{\{\s*([A-Za-z_][A-Za-z0-9_.]*)\s*\}\}/', $condition, $matches);
        if (!is_int($hits) || $hits < 1) {
            continue;
        }
        foreach ($matches[1] as $path) {
            $paths[] = $path;
        }
    }
    return array_values(array_unique($paths));
}

/**
 * Recursively collect every EXECUTABLE payload inside an arbitrary
 * properties / rule-groups structure, as `path (why)` labels for the
 * confirmation gate. Array nodes are matched against
 * ox_executable_node_shapes(); string leaves go to
 * ox_executable_shortcode_labels().
 *
 * @return list<string>
 */
function ox_collect_executable_payloads(mixed $value, string $path = 'properties'): array
{
    if (is_string($value)) {
        return ox_executable_shortcode_labels($value, $path);
    }
    if (!is_array($value)) {
        return [];
    }
    $found = [];
    foreach (ox_executable_node_shapes() as $shape) {
        if (!$shape['match']($value)) {
            continue;
        }
        $found[] = sprintf('%s (%s)', $path, $shape['why']);
    }
    /** @var mixed $child */
    foreach ($value as $key => $child) {
        $child_path = $path . '.' . (is_string($key) ? $key : '[' . (string) $key . ']');
        foreach (ox_collect_executable_payloads($child, $child_path) as $label) {
            $found[] = $label;
        }
    }
    return $found;
}

/**
 * Whether an array node IS a condition rule naming an executable condition.
 * Keyed on `ruleSlug` — the exact key the native matcher reads
 * (`plugin/themeless/request.php` doesRuleApply) — so the check holds
 * wherever the rule is nested.
 *
 * @param array<array-key, mixed> $node
 */
function ox_is_executable_condition_rule(array $node): bool
{
    return array_key_exists('ruleSlug', $node) && ox_is_executable_condition_slug($node['ruleSlug']);
}

/**
 * Whether an array node is a TermLoopBuilder term-query envelope that would run
 * PHP. `getTermQueryArgs()` eval()s `content.query.term_query` whenever
 * `content.query.load_terms_by_query` is truthy (engine
 * `subplugins/breakdance-elements/lib/term-builder.php:52-57`), reached from the
 * NATIVE `OxygenElements\TermLoopBuilder` element
 * (`subplugins/oxygen-elements/elements/Term_Loop_Builder/ssr.php:12,43`, whose
 * `availableIn()` is `['oxygen']` — element.php:467-470), so it is a base-install
 * control, not an extension one. Like the wp_query envelope, a non-empty
 * `term_query` is gated even while the toggle is OFF: flipping
 * `load_terms_by_query` afterwards is a non-code edit that would silently arm it.
 *
 * @param array<array-key, mixed> $node
 */
function ox_is_term_query_envelope(array $node): bool
{
    /** @var mixed $code */
    $code = $node['term_query'] ?? null;
    if (is_string($code) && trim($code) !== '') {
        return true;
    }
    /** @var mixed $load */
    $load = $node['load_terms_by_query'] ?? false;
    // The engine's own gate is a bare `if ($loadFromQuery)` (:55), so the
    // decision is PHP truthiness — spelled out as the explicit falsy set
    // because a mixed-to-bool cast is not statically decidable.
    return !in_array($load, [false, null, 0, 0.0, '', '0', []], strict: true);
}

/**
 * Whether an array node is a wp_query control envelope that would run PHP.
 * The engine reads `$props['active']` and eval()s `$props['php']` only when
 * active === 'php' (`query-control.php:46-61`), but a non-empty `php` string
 * inside such an envelope is gated too: flipping `active` later is a
 * non-code edit that would silently arm it.
 *
 * @param array<array-key, mixed> $node
 */
function ox_is_php_query_envelope(array $node): bool
{
    if (!array_key_exists('active', $node)) {
        return false;
    }
    /** @var mixed $active */
    $active = $node['active'];
    if (is_string($active) && ox_normalize_slug($active) === 'php') {
        return true;
    }
    /** @var mixed $php */
    $php = $node['php'] ?? null;
    return is_string($php) && trim($php) !== '';
}

/**
 * THE EXECUTABLE-PAYLOAD SHAPE TABLE — dynamic-shortcode ATTRIBUTE shapes.
 *
 * These are matched against the FINAL, MERGED attribute map the engine itself
 * would hand to `breakdanceShortcodeHandler()` — never against the raw
 * shortcode text. That distinction is the whole point: the engine decodes the
 * single-quoted `params='{…}'` JSON blob separately and `array_merge()`es it ON
 * TOP of the ordinary attributes (`getAttributesFromShortcode()`,
 * breakdance-dynamic-shortcode.php:88-119), so `params` WINS. A regex over the
 * raw text therefore reads `[breakdance_dynamic field="post_title"
 * params='{"field":"phpreturn","code":"return 1;"}']` as an innocent
 * `post_title` binding while the engine executes `phpreturn`.
 *
 * | shape id            | engine site                                        | attribute (post-merge)                |
 * |---------------------|----------------------------------------------------|---------------------------------------|
 * | executable-dd-field | plugin/dynamic-data/fields/utility/php-function.php:63-68 | `field` ∈ ox_executable_dynamic_field_slugs() |
 * | process-value-pipe  | plugin/dynamic-data/pipe.php:33-37 (called from breakdance-dynamic-shortcode.php:33-35) | `process_value` present |
 *
 * @return list<array{id: string, why: string, match: callable(array<array-key, mixed>): bool}>
 */
function ox_executable_attribute_shapes(): array
{
    return [
        [
            'id' => 'executable-dd-field',
            'why' => 'a dynamic-data `phpreturn` field — the engine eval()s its `code` (php-function.php:68)',
            'match' => static fn(array $atts): bool => ox_attributes_select_executable_field($atts),
        ],
        [
            'id' => 'process-value-pipe',
            'why' => 'a dynamic-data `process_value` pipe — the engine eval()s the piped code (pipe.php:37)',
            // The engine's own precondition is
            // `isset($atts['process_value']) && ($atts['process_value'] ?? false)`
            // (breakdance-dynamic-shortcode.php:33), so an EMPTY process_value
            // runs nothing. Gating its mere presence would cost precision
            // without buying safety: arming it later means writing the code,
            // and that write hits this same shape.
            'match' => static fn(array $atts): bool => ox_attributes_pipe_code($atts),
        ],
    ];
}

/**
 * Whether a merged attribute map carries a non-empty `process_value` pipe.
 *
 * @param array<array-key, mixed> $atts
 */
function ox_attributes_pipe_code(array $atts): bool
{
    /** @var mixed $pipe */
    $pipe = $atts['process_value'] ?? null;
    return is_string($pipe) && trim($pipe) !== '';
}

/**
 * Whether a merged attribute map selects a dynamic-data field whose handler
 * executes a caller-supplied attribute. Compared through ox_normalize_slug so a
 * cased/underscored alias cannot dodge a gate the canonical spelling would hit.
 *
 * @param array<array-key, mixed> $atts
 */
function ox_attributes_select_executable_field(array $atts): bool
{
    /** @var mixed $field */
    $field = $atts['field'] ?? null;
    if (!is_string($field)) {
        return false;
    }
    $needle = ox_normalize_slug($field);
    foreach (ox_executable_dynamic_field_slugs() as $slug) {
        if (ox_normalize_slug($slug) === $needle) {
            return true;
        }
    }
    return false;
}

/**
 * Executable-payload labels for a single STRING value, decided by PARSING every
 * span the engine would treat as a dynamic shortcode and inspecting its merged
 * attribute map — not by regexing the raw text.
 *
 * The entry test mirrors the engine's own precondition
 * `isDynamicShortcode() = str_contains($v, '[breakdance_dynamic')`
 * (breakdance-dynamic-shortcode.php:156-158), which guards every unspanned
 * evaluation site (oEmbed ssr.php:12-14, forms/custom/renderer.php:221-222) and
 * without which neither span regex in `renderDynamicShortcodes()` (:61,:68) can
 * match. It is applied case-INSENSITIVELY here — one notch wider than the
 * engine's case-sensitive test — so the gate fails closed on a cased alias.
 *
 * Requiring that literal is also what RETIRES the round-2 false positives: prose
 * that merely mentions `process_value=` or `field=phpreturn` without the
 * `[breakdance_dynamic` opener is not executable anywhere in the engine, and is
 * no longer flagged.
 *
 * @return list<string>
 */
function ox_executable_shortcode_labels(string $value, string $path): array
{
    if (stripos(haystack: $value, needle: '[breakdance_dynamic') === false) {
        return [];
    }
    $found = [];
    foreach (ox_dynamic_shortcode_spans($value) as $span) {
        foreach (ox_shortcode_attribute_maps($span) as $atts) {
            foreach (ox_attribute_map_labels($atts, $path) as $label) {
                $found[] = $label;
            }
        }
    }
    return array_values(array_unique($found));
}

/**
 * Apply the attribute shape table to one merged attribute map.
 *
 * @param array<array-key, mixed> $atts
 * @return list<string>
 */
function ox_attribute_map_labels(array $atts, string $path): array
{
    $found = [];
    foreach (ox_executable_attribute_shapes() as $shape) {
        if (!$shape['match']($atts)) {
            continue;
        }
        $found[] = sprintf('%s (%s)', $path, $shape['why']);
    }
    return $found;
}

/**
 * Every substring of a stored property value that the engine will hand to
 * `getAttributesFromShortcode()`. The engine has THREE such entries and they do
 * not agree with each other, so all three are reproduced and unioned:
 *
 *  1. `renderDynamicShortcodes():61` — when the whole (trimmed) value is exactly
 *     one shortcode, that value is passed through verbatim.
 *  2. `renderDynamicShortcodes():68` — otherwise every `[breakdance_dynamic …]`
 *     match is replaced individually. Later spans can override earlier ones only
 *     within their own span, so each is parsed on its own.
 *  3. The value AS A WHOLE — `breakdanceDoShortcode()` is also called with an
 *     un-spanned property value (oEmbed `ssr.php:14`, forms
 *     `renderer.php:222`, `themeless/rules/conditions/dynamic-data.php:42,46`),
 *     and `shortcode_parse_atts()` happily harvests `key=value` pairs from
 *     anywhere in that string. Without this entry, a payload placed OUTSIDE the
 *     bracketed span would be missed at exactly those three sites.
 *
 * @return list<string>
 */
function ox_dynamic_shortcode_spans(string $value): array
{
    $spans = [$value];
    $trimmed = trim($value);
    if (preg_match('/^\[breakdance_dynamic\b[^\]]*\]$/', $trimmed) === 1) {
        $spans[] = $trimmed;
    }
    $matches = [];
    $span_count = preg_match_all('/\[breakdance_dynamic\b(?:[^\[\]]|\[.*?\])*\]/', $value, $matches);
    if (is_int($span_count) && $span_count > 0) {
        foreach ($matches[0] as $match) {
            $spans[] = $match;
        }
    }
    return array_values(array_unique($spans));
}

/**
 * The merged attribute map(s) for one shortcode span. Returns our own mirror of
 * the engine parser plus — when Oxygen is loaded — the engine's own
 * `getAttributesFromShortcode()` output, and the caller gates on the UNION. If
 * the two ever disagree (a future engine change to the params regex, a WordPress
 * change to `shortcode_parse_atts()`), the WIDER answer wins and the gate stays
 * closed rather than silently narrowing.
 *
 * @return list<array<array-key, mixed>>
 */
function ox_shortcode_attribute_maps(string $span): array
{
    $maps = [ox_parse_shortcode_attributes($span)];
    if (function_exists('Breakdance\\DynamicData\\getAttributesFromShortcode')) {
        /** @var mixed $engine */
        $engine = \Breakdance\DynamicData\getAttributesFromShortcode($span);
        if (is_array($engine)) {
            $maps[] = $engine;
        }
    }
    return $maps;
}

/**
 * Local mirror of the engine's `getAttributesFromShortcode()`
 * (breakdance-dynamic-shortcode.php:88-119): normalise the closing bracket so
 * `shortcode_parse_atts()` cooperates, parse the ordinary attributes, then merge
 * the separately-decoded `params` JSON ON TOP of them. The merge direction is
 * the engine's and is load-bearing — `params` overrides an identically-named
 * top-level attribute.
 *
 * (The engine also strips `params` out of the shortcode string at :113, but that
 * happens AFTER `shortcode_parse_atts()` has already run at :93 and the stripped
 * copy is never used again, so reproducing it would change nothing.)
 *
 * @return array<array-key, mixed>
 */
function ox_parse_shortcode_attributes(string $span): array
{
    // Engine :90 — "Shortcodes should end with ' /]', otherwise
    // shortcode_parse_atts doesn't work."
    $normalized = preg_replace(pattern: '/\s*\/?\]\s*$/', replacement: ' /]', subject: $span, limit: 1) ?? $span;
    /** @var mixed $defaults */
    $defaults = shortcode_parse_atts($normalized);
    return array_merge(is_array($defaults) ? $defaults : [], ox_shortcode_params_attributes($normalized));
}

/**
 * The decoded `params='{…}'` attribute blob. Regex and unescaping are copied
 * verbatim from the engine (breakdance-dynamic-shortcode.php:97-110) so a value
 * that decodes there decodes identically here.
 *
 * @return array<array-key, mixed>
 */
function ox_shortcode_params_attributes(string $span): array
{
    $matches = [];
    if (preg_match("/params='((?:[^'\\\\]|\\\\.|'(?!\s*\/?\s*\]))*)'/", $span, $matches) !== 1) {
        return [];
    }
    // Engine :103-107 — unescape single/double quotes before json_decode.
    $json = str_replace(search: '\\\\"', replace: '\\"', subject: $matches[1]);
    $json = str_replace(search: "\\\\'", replace: "\\'", subject: $json);
    /** @var mixed $decoded */
    $decoded = json_decode($json, associative: true);
    return is_array($decoded) ? $decoded : [];
}

/**
 * Collect every gated payload anywhere in a node list of the canonical Oxygen
 * tree shape (`{id, data: {type, properties}, children}`) — a raw-code ELEMENT
 * TYPE, plus any executable SETTINGS payload on an otherwise-innocuous node.
 * Used both on a freshly BUILT tree (set-content, pre-write) and on a STORED
 * tree (the template arming gate, which must inspect code it did not receive).
 *
 * @param array<array-key, mixed> $nodes
 * @return list<string>
 */
function ox_collect_tree_executable_payloads(array $nodes, string $path): array
{
    $labels = [];
    /** @var mixed $node */
    foreach ($nodes as $i => $node) {
        if (!is_array($node)) {
            continue;
        }
        foreach (ox_collect_node_executable_payloads($node, $path . '[' . (string) $i . ']') as $label) {
            $labels[] = $label;
        }
    }
    return $labels;
}

/**
 * One tree node's gated payloads: its element type, its properties, its subtree.
 *
 * @param array<array-key, mixed> $node
 * @return list<string>
 */
function ox_collect_node_executable_payloads(array $node, string $node_path): array
{
    $labels = [];
    $data = isset($node['data']) && is_array($node['data']) ? $node['data'] : [];
    $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : '';
    if (ox_is_gated_code_type($type)) {
        $labels[] = sprintf('%s (%s)', $node_path, $type);
    }
    $properties = isset($data['properties']) && is_array($data['properties']) ? $data['properties'] : [];
    foreach (ox_collect_executable_payloads($properties, $node_path . '.properties') as $label) {
        $labels[] = $label;
    }
    $children = isset($node['children']) && is_array($node['children']) ? $node['children'] : [];
    foreach (ox_collect_tree_executable_payloads($children, $node_path . '.children') as $label) {
        $labels[] = $label;
    }
    return $labels;
}

/**
 * Executable payloads a TEMPLATE-SETTINGS patch would ARM without carrying any
 * code in the request itself — the property the gate is supposed to guarantee.
 * Two engine mechanisms make an already-persisted payload live again:
 *
 *  - `settings.disabled`: `doesTemplateApply()` short-circuits to false while it
 *    is `true` (engine `plugin/themeless/request.php:164-166`). Clearing it lets
 *    the same function fall through to the stored `ruleGroups`
 *    (`request.php:181-193`) and render the stored tree. A `true` → `false` flip
 *    is a pure boolean edit that the payload scanner never sees.
 *  - `settings.parentId`: `getTemplateHierarchy()` walks it to splice ANOTHER
 *    template into this request's render hierarchy (engine
 *    `plugin/themeless/god-controller.php:131-158`), so attaching a parent arms
 *    whatever that parent already stores.
 *
 * Pass `$template_id = 0` for a create, where only the parent branch applies.
 *
 * @param array<array-key, mixed> $patch
 * @return list<string>
 */
function ox_template_arming_payloads(int $template_id, array $patch): array
{
    $labels = [];
    if ($template_id > 0 && ox_patch_enables_template($template_id, $patch)) {
        foreach (ox_stored_template_payloads($template_id, sprintf('template#%d', $template_id)) as $label) {
            $labels[] = $label . ' — already stored, armed by clearing `settings.disabled`';
        }
    }
    $parent = ox_patch_attached_parent($patch);
    if ($parent > 0) {
        foreach (ox_stored_template_payloads($parent, sprintf('template#%d', $parent)) as $label) {
            $labels[] = $label . ' — already stored, armed by attaching `settings.parentId`';
        }
    }
    return $labels;
}

/**
 * Executable payloads that ATTACHING a selector to an element would arm.
 *
 * The third arming mechanism, alongside the two template ones above, and the
 * only one that lives on the ELEMENT rather than on a template. Per-selector
 * display conditions are stored on the element at
 * `properties.meta.classes_conditions[<selectorId>].ruleGroups`, but the engine
 * only reaches them for selectors the element ACTUALLY WEARS: the renderer
 * resolves `properties.meta.classes` into selector rows
 * (`getElementOxySelectors()`, engine `plugin/breakdance-oxygen/selectors.php:314-332`)
 * and filters that list through `shouldSelectorApply()` (`renderer.php:1479`),
 * which hands the stored `ruleGroups` to `doesRuleGroupApply()`
 * (`selectors.php:504-517`) — the same evaluator that eval()s a `custom-php`
 * rule's `value` (`themeless/rules/conditions/custom-php.php:35`).
 *
 * Conditions stored for an UNATTACHED selector id are therefore inert, and
 * attaching that id is what makes them run — a request that carries no code of
 * its own, exactly like clearing `settings.disabled` on a template. Writing the
 * conditions is already gated at every element-write boundary (the payload
 * scanner walks `properties` at any depth and matches the rule by shape), so
 * this closes the remaining half: the attachment.
 *
 * @param array<array-key, mixed> $properties the element's STORED properties
 * @return list<string>
 */
function ox_class_attachment_arming_payloads(array $properties, string $class_id): array
{
    /** @var mixed $meta */
    $meta = $properties['meta'] ?? null;
    if (!is_array($meta)) {
        return [];
    }
    /** @var mixed $all */
    $all = $meta['classes_conditions'] ?? null;
    if (!is_array($all)) {
        return [];
    }
    /** @var mixed $for_selector */
    $for_selector = $all[$class_id] ?? null;
    if (!is_array($for_selector)) {
        return [];
    }
    $path = sprintf('properties.meta.classes_conditions.%s', $class_id);
    $labels = [];
    foreach (ox_collect_executable_payloads($for_selector, $path) as $label) {
        $labels[] = $label . ' — already stored, armed by attaching the selector to `properties.meta.classes`';
    }
    return $labels;
}

/**
 * Whether a patch would take a template out of the disabled state. A patch that
 * sets `disabled` on a template that is NOT currently disabled changes nothing
 * about reachability, so it is not treated as arming.
 *
 * @param array<array-key, mixed> $patch
 */
function ox_patch_enables_template(int $template_id, array $patch): bool
{
    if (!array_key_exists('disabled', $patch) || $patch['disabled'] === true) {
        return false;
    }
    return (ox_read_template_settings($template_id)['disabled'] ?? false) === true;
}

/**
 * The template id a patch would attach as parent, or 0 when it attaches none.
 *
 * @param array<array-key, mixed> $patch
 */
function ox_patch_attached_parent(array $patch): int
{
    /** @var mixed $parent */
    $parent = $patch['parentId'] ?? null;
    return is_int($parent) && $parent > 0 ? $parent : 0;
}

/**
 * Every executable payload ALREADY PERSISTED on a template: its stored element
 * tree at any depth (gated element types AND executable settings) and its stored
 * `ruleGroups`. Both surfaces render as a consequence of an arming edit, so both
 * are inspected.
 *
 * @return list<string>
 */
function ox_stored_template_payloads(int $template_id, string $path): array
{
    $labels = [];
    $tree = ox_read_tree($template_id);
    if (is_array($tree)) {
        $root = isset($tree['root']) && is_array($tree['root']) ? $tree['root'] : [];
        $children = isset($root['children']) && is_array($root['children']) ? $root['children'] : [];
        foreach (ox_collect_tree_executable_payloads($children, $path . '.tree') as $label) {
            $labels[] = $label;
        }
    }
    /** @var mixed $rule_groups */
    $rule_groups = ox_read_template_settings($template_id)['ruleGroups'] ?? null;
    if (is_array($rule_groups)) {
        foreach (ox_collect_executable_payloads($rule_groups, $path . '.ruleGroups') as $label) {
            $labels[] = $label;
        }
    }
    return $labels;
}

/**
 * Whether an element type is available AT ALL in the current engine mode.
 * The builder excludes an element whose static `availableIn()` does not
 * contain `BREAKDANCE_MODE` (engine `plugin/elements/elements-for-builder.php:62-65`);
 * the base-class default is `['breakdance']`
 * (`plugin/elements/Element.class.php:293-296`), so in Oxygen mode only
 * elements that explicitly declare `'oxygen'` exist in the builder.
 * Missing class/method or a malformed return tolerantly counts as
 * available so a third-party registry quirk never blanks the catalogue.
 */
function ox_element_available_in_mode(string $element_class): bool
{
    if (!class_exists($element_class) || !method_exists($element_class, 'availableIn')) {
        return true;
    }
    /** @var callable $callable */
    $callable = [$element_class, 'availableIn'];
    /** @var mixed $modes */
    $modes = $callable();
    if (!is_array($modes)) {
        return true;
    }
    $mode = defined('BREAKDANCE_MODE') ? (string) constant('BREAKDANCE_MODE') : 'oxygen';
    return in_array($mode, $modes, strict: true);
}

/**
 * The element CLASS names visible in the builder's add panel after the
 * engine's own `breakdance_builder_elements` filter — the exact mechanism
 * the builder uses (`plugin/elements/elements-for-builder.php:15`). The
 * option-backed filter (`plugin/admin/settings-page/tabs/elements.php:163-175`)
 * subtracts the `builder_hidden_elements` global option, which Oxygen mode
 * seeds at activation with ten `EssentialElements\*` classnames (Section,
 * Div, Heading, Text, RichText, CodeBlock, … — engine
 * `plugin/breakdance-oxygen/elements.php:8-33`). Classes dropped by the
 * filter stay registered and renderable but are hidden from the add panel
 * (`plugin/elements/filtered-gets.php:166-172` flags them `alwaysHide`).
 *
 * @return list<string>
 */
function ox_panel_visible_classnames(): array
{
    $classnames = array_values(ox_element_classes());
    if (!function_exists('bdox_run_filters')) {
        return $classnames;
    }
    /** @var mixed $filtered */
    $filtered = \bdox_run_filters('breakdance_builder_elements', $classnames);
    if (!is_array($filtered)) {
        return $classnames;
    }
    $out = [];
    /** @var mixed $class */
    foreach ($filtered as $class) {
        if (!is_string($class)) {
            continue;
        }
        $out[] = $class;
    }
    return $out;
}

/**
 * Whether the optional "Breakdance Elements for Oxygen" extension (the
 * builder pack that adds Section / Heading / Image2 / Div / Columns / …) is
 * active. This is deliberately NOT "any EssentialElements slug exists":
 * base Oxygen 6.1.1 ships ~22 EssentialElements integration types on its own,
 * so a namespace count would false-positive on every base install (verified
 * on the synced runtime: 22 Essential slugs, extension absent).
 *
 * Detection uses two corroborating signals, either of which is sufficient:
 *  1. the extension's own version constant, when defined;
 *  2. the presence of a signature builder slug the base plugin never
 *     registers (`EssentialElements\Section` / `EssentialElements\Heading`),
 *     confirmed absent on a base install.
 */
function ox_essential_extension_present(): bool
{
    if (defined('BREAKDANCE_ELEMENTS_FOR_OXYGEN_VERSION')) {
        return true;
    }
    $slugs = ox_essential_element_slugs();
    return (
        in_array('EssentialElements\\Section', $slugs, strict: true)
        || in_array('EssentialElements\\Heading', $slugs, strict: true)
    );
}
