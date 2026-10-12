<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/** Builder element-id lookup width and component child-class width. */
const NOVAMIRA_BRICKS_ELEMENT_ID_LOOKUP_LENGTH = 6;

/**
 * Shared tail of every Bricks element-meta write: persist the area, set the
 * editor mode, drop the post cache, and READ THE STORED TREE BACK so the
 * caller can report `verified` from what is actually in the database rather
 * than from the array it meant to save.
 *
 * Every ability that writes `_bricks_page_content_2` / header / footer meta
 * (set / insert / remove / patch, apply-global-class, apply-component,
 * delete-component, the interactions writers and create-template) goes
 * through {@see bricks_commit_area_write()}.
 */

/**
 * Persists a flat element tree into a post area and verifies it.
 *
 * Options:
 *  - `force` (bool, default false): write even when another user holds the
 *    WordPress edit lock on the post; without it the write is refused with
 *    `locked_by_other_user`.
 *  - `revision` (bool, default true): snapshot the post (post fields + every
 *    Bricks area meta + page settings) into a WordPress revision BEFORE the
 *    write, the way Bricks' own save does, so the write can be undone from
 *    the builder's revision panel. Skipped with a warning when revisions
 *    are disabled for the post type.
 *  - `sign` (bool, default true): resolve `%root%` selectors and sign code
 *    elements through Bricks' signer (set/insert/patch/create-template).
 *    Writers that never add or change code (remove, class apply, component
 *    instances, interactions) pass false so an unchanged code element is
 *    neither re-signed nor re-warned about.
 *  - `set_editor_mode` (bool, default true): also store `_bricks_editor_mode`
 *    = "bricks" so the post renders through Bricks.
 *
 * Returns the stored elements, the merged warnings, `verified` (the tree read
 * back from the database equals the tree that was written),
 * `stored_element_count` (the number of elements the database now holds),
 * `css_file` (whether Bricks' per-post CSS file was regenerated — external
 * file mode only) and `unresolved_variables` (var(--x) references with no
 * value on this site).
 *
 * @param list<array<string, mixed>> $elements
 * @param list<string>               $warnings
 * @param array{sign?: bool, set_editor_mode?: bool, force?: bool, revision?: bool} $options
 * @return array{elements: list<array<string, mixed>>, warnings: list<string>, verified: bool, stored_element_count: int, css_file: string, unresolved_variables: list<string>, revision_id: int|null}|WP_Error
 */
function bricks_commit_area_write(
    int $post_id,
    string $area,
    array $elements,
    array $warnings,
    array $options = [],
): array|WP_Error {
    $meta_key = bricks_area_meta_keys()[$area] ?? BRICKS_DB_PAGE_CONTENT;
    $sign = ($options['sign'] ?? true) !== false;

    if (($options['force'] ?? false) !== true) {
        $lock = bricks_post_lock_error($post_id);
        if ($lock !== null) {
            return $lock;
        }
    }

    // Validate BEFORE anything is written, the undo point included. A refused
    // write used to leave a revision behind: content unchanged, revision count
    // up, and the caller told nothing had happened — so repeated invalid calls
    // quietly consumed revision storage.
    $prepared = bricks_prepare_elements_for_commit($elements, $sign ? 'sign' : 'as-is', $warnings);
    if ($prepared instanceof WP_Error) {
        return $prepared;
    }
    $elements = $prepared;

    $revision_id = null;
    if (($options['revision'] ?? true) !== false) {
        $revision = bricks_create_pre_write_revision($post_id);
        $revision_id = $revision['id'];
        if ($revision['note'] !== null) {
            $warnings[] = $revision['note'];
        }
    }

    // update_post_meta() unslashes its value (it expects request-style slashed
    // data, which is what the Bricks builder hands it from $_POST). Slashing
    // the tree first keeps every backslash an agent wrote — regex in a code
    // element, `content: "\201C"` in custom CSS — exactly as intended.
    update_post_meta($post_id, $meta_key, wp_slash($elements));
    if (($options['set_editor_mode'] ?? true) !== false) {
        update_post_meta($post_id, BRICKS_DB_EDITOR_MODE, meta_value: 'bricks');
    }
    clean_post_cache($post_id);

    $verification = bricks_verify_area_write($post_id, $meta_key, $elements);
    if ($verification['warning'] !== null) {
        $warnings[] = $verification['warning'];
    }

    $unresolved = bricks_unresolved_variables(array_column($elements, 'settings'));
    if ($unresolved !== []) {
        $warnings[] = bricks_unresolved_variables_warning($unresolved);
    }

    $css_file = bricks_regenerate_post_css($post_id, $elements);
    if (str_starts_with($css_file, 'skipped(')) {
        $warnings[] = sprintf(
            'External CSS file mode is on but the per-post CSS file was not regenerated: %s. The page renders through a stale or missing stylesheet until it is.',
            substr($css_file, offset: 8, length: -1),
        );
    }

    return [
        'elements' => $elements,
        'warnings' => $warnings,
        'verified' => $verification['verified'],
        'stored_element_count' => $verification['stored_element_count'],
        'css_file' => $css_file,
        'unresolved_variables' => $unresolved,
        'revision_id' => $revision_id,
    ];
}

/**
 * The error to return when another user holds a fresh WordPress edit lock
 * on the post (their Bricks builder or WordPress editor session is open and
 * keeps refreshing it): a write now would be overwritten by their next
 * save. Null when the post is unlocked, the lock is stale, or it belongs to
 * the current user.
 */
function bricks_post_lock_error(int $post_id): ?WP_Error
{
    if (!function_exists('wp_check_post_lock')) {
        require_once ABSPATH . 'wp-admin/includes/post.php';
    }

    $locker = wp_check_post_lock($post_id);
    if (!is_int($locker) || $locker <= 0 || $locker === get_current_user_id()) {
        return null;
    }

    $user = get_userdata($locker);
    $name = $user instanceof \WP_User ? $user->display_name : sprintf('user %d', $locker);

    return bricks_error(
        'locked_by_other_user',
        sprintf(
            'An edit lock on post %1$d belongs to %2$s (user id %3$d): they have the Bricks builder or the WordPress editor open on it, and their next save would discard anything written from here. Retry once that session is closed, or pass "force": true to write over the lock.',
            $post_id,
            $name,
            $locker,
        ),
        ['locked_by' => $locker, 'locked_by_name' => $name],
    );
}

/**
 * The steps a tree goes through before it is stored: presence-tested toggles
 * normalised, then selectors resolved and code elements signed. Returns a
 * WP_Error when signing is refused (a site with signature generation locked),
 * which is why this runs before the pre-write revision is taken.
 *
 * @param list<array<string, mixed>> $elements
 * @param 'sign'|'as-is'             $mode whether code elements still need signing
 * @param list<string>               $warnings
 * @return list<array<string, mixed>>|WP_Error
 */
function bricks_prepare_elements_for_commit(array $elements, string $mode, array &$warnings): array|WP_Error
{
    $elements = bricks_normalize_presence_toggles($elements, $warnings);
    if ($mode !== 'sign') {
        return $elements;
    }

    $elements = bricks_resolve_css_selectors($elements);
    $signed = bricks_sign_code_elements($elements);
    if ($signed instanceof WP_Error) {
        return $signed;
    }
    [$elements, $signature_warnings] = $signed;
    $warnings = array_merge($warnings, $signature_warnings);

    return $elements;
}

/**
 * Rewrites an explicit `false` on a presence-tested element setting into the
 * absence Bricks reads as OFF.
 *
 * Sits on the single write path every element-tree ability goes through, so
 * set / insert / patch / apply-component / create-template all get it.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<string>               $warnings
 * @return list<array<string, mixed>>
 */
function bricks_normalize_presence_toggles(array $elements, array &$warnings): array
{
    foreach ($elements as $index => $element) {
        if (!is_array($element['settings'] ?? null)) {
            continue;
        }
        $name = (string) ($element['name'] ?? '');
        /** @var array<string, mixed> $settings */
        $settings = $element['settings'];
        $elements[$index]['settings'] = bricks_apply_presence_toggle_offs(
            $settings,
            bricks_loaded_element_controls($name) ?? [],
            sprintf('element "%s" (%s)', (string) ($element['id'] ?? ''), $name),
            $warnings,
        );
    }

    return $elements;
}

/**
 * Stores an undo point for a post before a Bricks write: a WordPress
 * revision that carries the Bricks area trees and page settings, so the
 * state that preceded the write can be restored from the revision panel.
 * Returns the revision id, or null with a note explaining why no restorable
 * snapshot exists (revisions disabled for the post type, WordPress declined
 * to create one, or the Bricks meta could not be copied onto it).
 *
 * @return array{id: int|null, note: string|null}
 */
function bricks_create_pre_write_revision(int $post_id): array
{
    /** @var mixed $post */
    $post = get_post($post_id);
    if (!$post instanceof \WP_Post) {
        return ['id' => null, 'note' => null];
    }
    if (!post_type_supports($post->post_type, feature: 'revisions') || !wp_revisions_enabled($post)) {
        return [
            'id' => null,
            'note' => sprintf(
                'Revisions are disabled for post type "%s", so no pre-write snapshot of this post was stored; the previous content cannot be restored from the revision panel.',
                $post->post_type,
            ),
        ];
    }

    $snapshot = bricks_snapshot_revision($post_id, bricks_owned_meta_keys());
    if ($snapshot instanceof WP_Error) {
        /** @var mixed $data */
        $data = $snapshot->get_error_data();
        $partial = is_array($data) && is_int($data['revision_id'] ?? null) ? $data['revision_id'] : null;

        return ['id' => $partial, 'note' => $snapshot->get_error_message()];
    }

    return ['id' => $snapshot, 'note' => null];
}

/**
 * Post meta the Bricks writers own: the three area trees and the page
 * settings. A snapshot carries all of them so a restore brings back the
 * whole page, not only the area that was written.
 *
 * @return list<string>
 */
function bricks_owned_meta_keys(): array
{
    return [BRICKS_DB_PAGE_HEADER, BRICKS_DB_PAGE_CONTENT, BRICKS_DB_PAGE_FOOTER, BRICKS_DB_PAGE_SETTINGS];
}

/**
 * Snapshots a post into a WordPress revision that also carries the given
 * meta keys, and returns the revision id.
 *
 * Why a forced revision: Bricks content lives in post meta, so from
 * WordPress' point of view nothing about the post changed and its
 * "no changes since the last revision" check would skip the snapshot. The
 * check is disabled only for this call. Why the meta copy: a revision only
 * stores the post fields WordPress versions; the Bricks meta is copied onto
 * the revision row so a restore (WordPress or the Bricks revision panel,
 * which reads its meta back from the revision) brings the trees back too.
 *
 * @param list<string> $meta_keys
 * @return int|WP_Error the revision id; a WP_Error carries `revision_id` in
 *                      its data when the revision exists but a meta copy failed
 */
function bricks_snapshot_revision(int $post_id, array $meta_keys): int|WP_Error
{
    $force = static fn(): bool => false;
    add_filter('wp_save_post_revision_check_for_changes', callback: $force);
    try {
        /** @var mixed $saved */
        $saved = wp_save_post_revision($post_id);
    } finally {
        remove_filter('wp_save_post_revision_check_for_changes', callback: $force);
    }

    $revision_id = is_int($saved) ? $saved : 0;
    if ($revision_id <= 0) {
        return bricks_error(
            'revision_not_created',
            message: 'WordPress did not create a revision for this post before the write (the post may be an auto-draft), so the previous content cannot be restored from the revision panel.',
        );
    }

    $not_copied = [];
    foreach ($meta_keys as $key) {
        /** @var mixed $value */
        $value = get_post_meta($post_id, $key, single: true);
        if (!is_array($value) || $value === []) {
            continue;
        }
        // The post-meta wrappers refuse revision rows; the generic metadata
        // API accepts them. Values are slashed because the API unslashes.
        $present = metadata_exists('post', $revision_id, $key);
        if ($present && get_metadata('post', $revision_id, $key, single: true) === $value) {
            continue;
        }
        $stored = $present
            ? update_metadata('post', $revision_id, $key, wp_slash($value))
            : add_metadata('post', $revision_id, $key, wp_slash($value));
        if ($stored === false) {
            $not_copied[] = $key;
        }
    }

    if ($not_copied !== []) {
        return bricks_error(
            'revision_meta_not_copied',
            sprintf(
                'Revision %d was created but %s could not be copied onto it, so restoring it would not bring that data back.',
                $revision_id,
                implode(', ', $not_copied),
            ),
            ['revision_id' => $revision_id],
        );
    }

    return $revision_id;
}

/**
 * Re-reads the area meta and compares it with the tree that was written.
 * `verified` is true only when the stored tree is identical to the intended
 * one (same elements, same order, same settings). The warning explains a
 * mismatch in the terms an agent can act on.
 *
 * @param list<array<string, mixed>> $intended
 * @return array{verified: bool, stored_element_count: int, warning: string|null}
 */
function bricks_verify_area_write(int $post_id, string $meta_key, array $intended): array
{
    wp_cache_delete($post_id, group: 'post_meta');
    /** @var mixed $stored */
    $stored = get_post_meta($post_id, $meta_key, single: true);

    if (!is_array($stored)) {
        return [
            'verified' => false,
            'stored_element_count' => 0,
            'warning' => sprintf(
                'Read-back failed: the "%s" meta of post %d holds no element array after the write. A meta sanitizer or a database error rejected it — re-read the area with novamira/bricks-get-content (summary: true) before continuing.',
                $meta_key,
                $post_id,
            ),
        ];
    }

    /** @var list<array<string, mixed>> $stored */
    $stored = array_values(array_filter($stored, static fn(mixed $item): bool => is_array($item)));
    if ($stored === array_values($intended)) {
        return ['verified' => true, 'stored_element_count' => count($stored), 'warning' => null];
    }

    $stored_ids = bricks_element_id_list($stored);
    $intended_ids = bricks_element_id_list($intended);
    $missing = array_values(array_diff($intended_ids, $stored_ids));
    $extra = array_values(array_diff($stored_ids, $intended_ids));

    $detail = match (true) {
        $missing !== [] => sprintf('elements %s are not in the stored tree', implode(', ', $missing)),
        $extra !== [] => sprintf('the stored tree carries unexpected elements %s', implode(', ', $extra)),
        $stored_ids !== $intended_ids => 'the stored element order differs',
        default => 'the stored settings differ from what was written (a meta sanitizer altered the values)',
    };

    return [
        'verified' => false,
        'stored_element_count' => count($stored),
        'warning' => sprintf(
            'Read-back mismatch: %s. Re-read the area with novamira/bricks-get-content (summary: true) and repair it before continuing.',
            $detail,
        ),
    ];
}

/**
 * Element ids of a flat tree, in order.
 *
 * @param list<array<string, mixed>> $elements
 * @return list<string>
 */
function bricks_element_id_list(array $elements): array
{
    $ids = [];
    foreach ($elements as $element) {
        $ids[] = (string) ($element['id'] ?? '');
    }
    return $ids;
}

/**
 * Copies the verification fields of a commit result onto an ability
 * response. `saved` stays as a deprecated alias of `verified` (it used to be
 * a hard-coded true) so existing callers keep working until they migrate.
 *
 * @param array<string, mixed> $result
 * @param array{elements?: list<array<string, mixed>>, warnings?: list<string>, verified: bool, stored_element_count: int, css_file?: string, unresolved_variables?: list<string>, revision_id?: int|null} $commit
 * @return array<string, mixed>
 */
function bricks_with_write_verification(array $result, array $commit): array
{
    $result['verified'] = $commit['verified'];
    // Deprecated alias kept for callers that branch on `saved`: derived from
    // the read-back, never a literal true.
    $result['saved'] = $commit['verified'];
    $result['stored_element_count'] = $commit['stored_element_count'];
    if (array_key_exists('revision_id', $commit)) {
        $result['revision_id'] = $commit['revision_id'];
    }
    if (isset($commit['css_file'])) {
        $result['css_file'] = $commit['css_file'];
    }
    if (isset($commit['unresolved_variables']) && $commit['unresolved_variables'] !== []) {
        $result['unresolved_variables'] = $commit['unresolved_variables'];
    }

    return $result;
}

/**
 * Read-back verification of a design-store write (variables, palette, theme
 * styles, global classes): the option's object-cache entry is dropped, the
 * option is read back with get_option() and compared with what was meant to
 * be stored. That is a read of what the store now answers with, NOT of the
 * database row — an autoloaded option can still come from the alloptions
 * cache, and the get_option filters sit in front of both. `null` as the intended value
 * means the row was meant to be deleted. Mirrors the content-write
 * contract: `verified` from the read-back, `saved` as its deprecated alias,
 * `stored_count` counting the stored entries (`$count` may override how
 * entries are counted, e.g. colours across palettes).
 *
 * @param array<string, mixed>           $result
 * @param array<array-key, mixed>|null   $intended
 * @param (callable(mixed): int)|null    $count
 * @return array<string, mixed>
 */
function bricks_with_option_write_verification(
    array $result,
    string $option,
    ?array $intended,
    ?callable $count = null,
    ?callable $read = null,
): array {
    wp_cache_delete($option, group: 'options');
    /** @var mixed $stored */
    $stored = $read === null ? get_option($option, default_value: null) : $read();
    $verified = $intended === null ? $stored === null : is_array($stored) && $stored === $intended;
    $result['verified'] = $verified;
    // Deprecated alias kept for callers that branch on `saved`.
    $result['saved'] = $verified;
    $result['stored_count'] = is_array($stored) ? count($stored) : 0;
    if ($count !== null) {
        $result['stored_count'] = $count($stored);
    }
    if (!$verified) {
        $result['warnings'] = array_merge(is_array($result['warnings'] ?? null) ? $result['warnings'] : [], [sprintf(
            'The stored %s option differs from what was written (verified: false). Another process may have written it at the same time — read it back before writing again.',
            $option,
        )]);
    }

    return $result;
}

/**
 * Output-schema properties shared by every verified design-store write.
 *
 * @return array<string, array<string, mixed>>
 */
function bricks_option_write_verification_schema(string $what): array
{
    return [
        'verified' => [
            'type' => 'boolean',
            'description' => sprintf('True when the %s read back from the database equal what was written.', $what),
        ],
        'saved' => ['type' => 'boolean', 'description' => 'Deprecated alias of verified.'],
        'stored_count' => [
            'type' => 'integer',
            'description' => sprintf('Number of %s stored after the write.', $what),
        ],
    ];
}

/** Whether an element id exactly matches the builder's six-character lookup width. */
function bricks_element_id_is_builder_safe(string $id): bool
{
    return strlen($id) === NOVAMIRA_BRICKS_ELEMENT_ID_LOOKUP_LENGTH;
}

/**
 * Supplied unsafe ids that are not already present in the target area.
 *
 * Existing ids are exempt so a whole-tree resend can preserve older stored
 * content. Missing and empty ids are omitted here because the write pipeline
 * replaces them with generated six-character ids.
 *
 * @param  list<array<string, mixed>> $elements
 * @param  list<array<string, mixed>> $stored_elements
 * @return list<string>
 */
function bricks_unsafe_new_element_ids(array $elements, array $stored_elements = []): array
{
    /** @var array<array-key, true> $stored_ids Numeric-looking ids become integer keys. */
    $stored_ids = [];
    foreach ($stored_elements as $element) {
        $id = (string) ($element['id'] ?? '');
        if ($id !== '') {
            $stored_ids[$id] = true;
        }
    }

    /** @var array<array-key, true> $seen Numeric-looking ids become integer keys. */
    $seen = [];
    $unsafe = [];
    foreach ($elements as $element) {
        /** @var mixed $raw_id */
        $raw_id = $element['id'] ?? null;
        if (!is_string($raw_id) || $raw_id === '') {
            continue;
        }
        if (
            bricks_element_id_is_builder_safe($raw_id)
            || array_key_exists($raw_id, $stored_ids)
            || array_key_exists($raw_id, $seen)
        ) {
            continue;
        }

        $seen[$raw_id] = true;
        $unsafe[] = $raw_id;
    }

    return $unsafe;
}

/**
 * Blocking error for newly supplied ids the builder cannot look up safely.
 *
 * Uses the same code and status behaviour as the existing duplicate-id
 * validation errors.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<array<string, mixed>> $stored_elements
 */
function bricks_unsafe_new_element_ids_error(array $elements, array $stored_elements = []): ?WP_Error
{
    $unsafe = bricks_unsafe_new_element_ids($elements, $stored_elements);
    if ($unsafe === []) {
        return null;
    }

    $described = array_map(static fn(string $id): string => sprintf('"%s" (%d characters)', $id, strlen($id)), $unsafe);

    return bricks_error('invalid_elements', sprintf(
        'New element id(s) cannot be saved: %s. The builder\'s right-click element menu uses a six-character lookup width, so every new id must contain exactly six characters. Legacy ids are exempt only when already stored in this exact target post and area. When copying a tree to another post or area, replace each listed id with exactly six characters and update every parent, children, selector, and property-connection reference. Alternatively, omit an id to have one generated. Nothing was written.',
        implode(', ', $described),
    ));
}

/**
 * Whether an element tree contains an id that stringifies to "0".
 *
 * @param list<array<string, mixed>> $elements
 */
function bricks_tree_has_zero_id(array $elements): bool
{
    foreach ($elements as $element) {
        if ((string) ($element['id'] ?? '') === '0') {
            return true;
        }
    }

    return false;
}

/**
 * Store every root parent as integer zero without changing an id of "0".
 *
 * A string "0" is a root marker unless the tree contains an element whose id
 * is "0"; in that tree it remains a valid parent reference for other rows.
 * The "0" element itself cannot parent itself, so its own string parent is
 * still normalized to the root marker. Empty ids are generated later and can
 * never be a parent target, making an empty parent unambiguously top-level.
 *
 * @param  list<array<string, mixed>> $elements
 * @return list<array<string, mixed>>
 */
function bricks_normalize_root_parents(array $elements): array
{
    $has_zero_id = bricks_tree_has_zero_id($elements);

    foreach ($elements as &$element) {
        $element['parent'] ??= 0;
        /** @var mixed $parent */
        $parent = $element['parent'];
        $id = (string) ($element['id'] ?? '');
        if ($parent === '' || $parent === '0' && (!$has_zero_id || $id === '0')) {
            $element['parent'] = 0;
        }
    }
    unset($element);

    return $elements;
}

/**
 * Fresh element / component id: six lowercase hex characters (the id format
 * Bricks accepts) that is not in `$taken`. Candidates come from the CSPRNG;
 * when no randomness source is available the id is derived from the taken
 * set instead, so the generator always returns a free id deterministically.
 *
 * @param array<string, true> $taken
 */
function bricks_generate_element_id(array $taken = []): string
{
    for ($attempt = 0; $attempt < 32; $attempt++) {
        $candidate = bricks_random_hex_id();
        if ($candidate !== null && !bricks_id_is_type_ambiguous($candidate) && !isset($taken[$candidate])) {
            return $candidate;
        }
    }

    // No usable randomness (or an extremely crowded id space): walk the
    // 24-bit id space from a digest of the taken ids until a free slot.
    $seed = (int) hexdec(substr(hash('sha256', implode(',', array_keys($taken))), offset: 0, length: 6));
    for ($step = 0; $step < 0x100_0000; $step++) {
        $candidate = str_pad(dechex(($seed + $step) % 0x100_0000), length: 6, pad_string: '0', pad_type: STR_PAD_LEFT);
        if (!bricks_id_is_type_ambiguous($candidate) && !isset($taken[$candidate])) {
            return $candidate;
        }
    }

    return '000000';
}

/**
 * Whether PHP would store this id as an integer array key rather than as the
 * string it is.
 *
 * An all-digit id changes type the moment it is used as a key, and comes back
 * from array_keys() as an int — which breaks a strict comparison, and which
 * array_merge() renumbers away entirely. Our own code handles ids that
 * already exist in that shape (they cannot be regenerated on a live site),
 * but there is no reason to mint new ones: the builder keys by these ids too,
 * in code we do not control.
 *
 * Deliberately conservative — PHP only coerces a canonical decimal integer,
 * so "007700" would in fact stay a string, but rejecting it as well costs a
 * handful of candidates out of sixteen million and keeps the rule one line
 * that anyone can check.
 */
function bricks_id_is_type_ambiguous(string $id): bool
{
    return $id !== '' && ctype_digit($id);
}

/**
 * Six random lowercase hex characters, or null when the CSPRNG is unavailable.
 */
function bricks_random_hex_id(): ?string
{
    try {
        return substr(bin2hex(random_bytes(3)), offset: 0, length: 6);
    } catch (\Exception) {
        return null;
    }
}

/**
 * The generated-ids map as it must reach the caller: a JSON OBJECT keyed by
 * request index, which is what every ability declaring this field says it
 * emits.
 *
 * WHY A CAST IS NEEDED. The map is keyed by the element's index in the
 * request, and PHP folds a numeric string key back to an integer — so
 * `["0" => "abc123"]` is an integer-keyed array, and when the generated
 * indices happen to be 0..n-1 it is a LIST, which json_encode emits as
 * `["abc123"]`.
 *
 * The defect is the type, not a mislabelled index. A JSON list requires the
 * keys to be exactly 0..n-1, so index 0 is present in every case that encodes
 * as one — measured over all 31 non-empty subsets of indices 0..4: five encode
 * as a list, and none of them lacks index 0. Drop index 0 and the keys are
 * non-contiguous, which already encodes as an object. So the entry a caller
 * reads at position 0 of such a list is in fact the id generated for index 0;
 * what breaks is that the value is a list at all — the field is declared an
 * object, a caller that treats it as one is handed a different type without
 * warning, and a client validating against the declared schema is handed
 * something it did not agree to accept. Casting keeps the declared type in
 * every case, without changing the contract.
 *
 * @param array<array-key, string> $generated request index => generated id
 */
function bricks_generated_ids_object(array $generated): object
{
    return (object) $generated;
}

/**
 * Gives every element without an id (missing, empty, or non-string) a
 * generated one that collides neither with the ids already in the payload
 * nor with `$taken` (the ids already stored in the target area). Returns the
 * elements plus a map of request index => generated id so the caller can
 * report which ids were assigned. Descendants reference their parent by id,
 * so an element that omits its id can only be a root or a leaf.
 *
 * @param list<array<string, mixed>> $elements
 * @param array<string, true>        $taken
 * @return array{elements: list<array<string, mixed>>, generated_ids: array<string, string>}
 */
function bricks_assign_missing_ids(array $elements, array $taken = []): array
{
    foreach ($elements as $element) {
        /** @var mixed $id */
        $id = $element['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $taken[$id] = true;
        }
    }

    $generated = [];
    foreach ($elements as $index => $element) {
        /** @var mixed $id */
        $id = $element['id'] ?? null;
        if (is_string($id) && $id !== '') {
            continue;
        }
        $new_id = bricks_generate_element_id($taken);
        $taken[$new_id] = true;
        $elements[$index]['id'] = $new_id;
        $generated[(string) $index] = $new_id;
    }

    return ['elements' => $elements, 'generated_ids' => $generated];
}

/**
 * Area name (header / content / footer) for a Bricks area meta key; content
 * for anything unrecognised.
 */
function bricks_area_from_meta_key(string $meta_key): string
{
    $area = array_search($meta_key, bricks_area_meta_keys(), strict: true);

    return is_string($area) ? $area : 'content';
}

/**
 * Compact per-element summary of a stored area: id, name, parent, label,
 * children_count, has_loop, cid — no settings. Sized for a verification read
 * after a write or before a structural edit.
 *
 * @param list<array<string, mixed>> $elements
 * @return list<array<string, mixed>>
 */
function bricks_summarize_elements(array $elements): array
{
    $child_counts = bricks_child_counts($elements);
    $summary = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        /** @var array<string, mixed> $settings */
        $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
        $row = [
            'id' => $id,
            'name' => (string) ($element['name'] ?? ''),
            'parent' => $element['parent'] ?? 0,
            'children_count' => $child_counts[$id] ?? 0,
            'has_loop' => ($settings['hasLoop'] ?? false) === true,
        ];
        if (is_string($element['label'] ?? null) && $element['label'] !== '') {
            $row['label'] = $element['label'];
        }
        if (is_string($element['cid'] ?? null) && $element['cid'] !== '') {
            $row['cid'] = $element['cid'];
        }
        $summary[] = $row;
    }

    return $summary;
}
