<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Code Snippets abilities.
 *
 * Wrappers around the plugin's own `Code_Snippets\` namespace functions
 * (`get_snippet`, `get_snippets`, `save_snippet`, `delete_snippet`,
 * `activate_snippet`, `deactivate_snippet`). Centralised here so each
 * ability stays small and so the per-snippet response shape is consistent
 * across list / get / create / edit / enable / disable.
 *
 * The snippet model class lives in two places depending on the plugin
 * version: `Code_Snippets\Snippet` on 3.6–3.9.x and
 * `Code_Snippets\Model\Snippet` from 3.10 (PSR-4 restructure, no
 * class_alias for the old name). The ops functions above stayed in the
 * `Code_Snippets\` namespace. Every instantiation goes through
 * cs_new_snippet() and every signature accepts the union of both classes,
 * so one build of the integration runs on either line.
 */

/**
 * Fully-qualified name of the Snippet model class the active Code
 * Snippets version provides — `Code_Snippets\Model\Snippet` on 3.10+,
 * `Code_Snippets\Snippet` on 3.6–3.9.x.
 *
 * @return class-string<\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet>
 */
function cs_snippet_class(): string
{
    if (class_exists(\Code_Snippets\Model\Snippet::class)) {
        return \Code_Snippets\Model\Snippet::class;
    }
    return \Code_Snippets\Snippet::class;
}

/**
 * Whether a value is a Snippet instance from whichever class the active
 * plugin version ships. `instanceof` never autoloads, so checking against
 * the class name that does not exist on this install is a plain false.
 *
 * @phpstan-assert-if-true \Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $value
 */
function cs_is_snippet(mixed $value): bool
{
    return $value instanceof \Code_Snippets\Model\Snippet || $value instanceof \Code_Snippets\Snippet;
}

/**
 * Fresh, empty Snippet from the resolved model class. Replaces the former
 * hard `new \Code_Snippets\Snippet()` that fatals on 3.10 (class not found).
 */
function cs_new_snippet(): \Code_Snippets\Snippet|\Code_Snippets\Model\Snippet
{
    if (class_exists(\Code_Snippets\Model\Snippet::class)) {
        return new \Code_Snippets\Model\Snippet();
    }
    return new \Code_Snippets\Snippet();
}

/**
 * Whether the snippet is locked — Code Snippets 3.10 added per-snippet
 * locking: save_snippet() silently keeps the stored `name` / `code` of a
 * locked snippet and delete_snippet() returns false for it. The field
 * only exists on the 3.10+ model class, so a legacy `Code_Snippets\Snippet`
 * always reads as unlocked (reading `->locked` on it would trip the
 * model's invalid-property warning).
 */
function cs_is_locked(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $snippet): bool
{
    return $snippet instanceof \Code_Snippets\Model\Snippet && $snippet->locked === true;
}

/**
 * Scope slugs the active Code Snippets version lists, straight from the
 * resolved model class (`get_all_scopes()` exists since 3.6). Only used
 * to opt IN scopes added after the floor (cs_optional_scopes()) — never
 * to narrow the static reference, because the plugin's list is not a
 * reliable floor (3.6.0 omits `condition`; free builds list the Pro-only
 * css / js scopes). Empty when the class is not loaded.
 *
 * @return list<string>
 */
function cs_plugin_scopes(): array
{
    $class = cs_snippet_class();
    if (!class_exists($class) || !method_exists($class, 'get_all_scopes')) {
        return [];
    }
    if (!(new \ReflectionMethod($class, 'get_all_scopes'))->isStatic()) {
        return [];
    }
    /** @var mixed $raw */
    $raw = $class::get_all_scopes();
    if (!is_array($raw)) {
        return [];
    }
    return array_values(array_filter($raw, callback: 'is_string'));
}

/**
 * Insert `$scope` into an ordered scope list right before `$anchor`
 * (append when the anchor is absent). No-op when the slug is already
 * there, so promoting an optional scope into the static floor can never
 * yield a duplicate.
 *
 * @param list<string> $scopes
 * @return list<string>
 */
function cs_insert_scope(array $scopes, string $scope, string $anchor): array
{
    if (in_array($scope, $scopes, strict: true)) {
        return $scopes;
    }
    $at = array_search($anchor, $scopes, strict: true);
    if ($at === false) {
        $scopes[] = $scope;
        return $scopes;
    }
    array_splice($scopes, $at, length: 0, replacement: [$scope]);
    return array_values($scopes);
}

/**
 * The failure payload for an invalid / unavailable type + scope pair,
 * shared by create-snippet and edit-snippet so both emit the same
 * machine-stable `cs_pro_required` code when the pair is a known one that
 * this install cannot run (Pro-only type on the free plugin).
 *
 * @return array<string, mixed>
 */
function cs_type_scope_failure(string $type, string $error): array
{
    $out = ['success' => false, 'error' => $error, 'allowed_types' => cs_available_types()];
    if (isset(cs_scopes_by_type()[$type]) && !in_array($type, cs_available_types(), strict: true)) {
        $out['error_code'] = 'cs_pro_required';
    }
    return $out;
}

/**
 * Renders a Snippet object into the public response shape used by every
 * Code Snippets ability. Includes the executable code so an agent can
 * reason about the snippet without a follow-up `get-snippet` call.
 *
 * @return array<string, mixed>
 */
function cs_snippet_to_array(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $snippet): array
{
    $out = [
        'id' => (int) $snippet->id,
        'name' => $snippet->name,
        'desc' => $snippet->desc,
        'code' => $snippet->code,
        'type' => $snippet->type,
        'scope' => $snippet->scope,
        'tags' => array_values(array_map('strval', $snippet->tags)),
        'priority' => (int) $snippet->priority,
        'active' => $snippet->active,
        'network' => $snippet->network,
        'condition_id' => (int) $snippet->condition_id,
        'modified' => $snippet->modified,
        // `revision` is wpdb-returned as string on read paths and int after
        // post-save mutation — cast both ways to keep the response shape
        // consistent (Round 2 R8).
        'revision' => (int) $snippet->revision,
        // Code Snippets 3.10+ snippet locking; always false on older versions.
        'locked' => cs_is_locked($snippet),
    ];
    if ($snippet->type === 'html' && $snippet->scope === 'content') {
        // The plugin auto-registers a [code_snippet id="<id>"] shortcode for
        // html/content snippets on save. Echoing the resulting tag in every
        // read path lets agents paste it into post content without
        // reconstructing the format from the snippet id.
        $out['shortcode'] = sprintf('[code_snippet id="%d"]', $snippet->id);
    }
    return $out;
}

/**
 * Compact summary used by list-snippets — drops `code` to keep the
 * response small when listing many snippets. The full shape is available
 * via get-snippet on a specific id.
 *
 * @return array<string, mixed>
 */
function cs_snippet_summary(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $snippet): array
{
    return [
        'id' => (int) $snippet->id,
        'name' => $snippet->name,
        'type' => $snippet->type,
        'scope' => $snippet->scope,
        'tags' => array_values(array_map('strval', $snippet->tags)),
        'priority' => (int) $snippet->priority,
        'active' => $snippet->active,
        'network' => $snippet->network,
        'modified' => $snippet->modified,
    ];
}

/**
 * Validates that a `scope` value is allowed for the given `type`. Returns
 * an error message when the combination is wrong, or null when valid.
 * Used by create-snippet and edit-snippet so both reject bad pairings
 * with the same wording.
 */
function cs_validate_type_scope(string $type, string $scope): ?string
{
    $allowed = cs_scopes_by_type();
    if (!isset($allowed[$type])) {
        return sprintf(
            'Unknown snippet type "%s". Valid types: %s. Call code-snippets-get-types-schema for the full reference.',
            $type,
            implode(', ', array_keys($allowed)),
        );
    }
    if (!in_array($scope, $allowed[$type], strict: true)) {
        return sprintf(
            'Scope "%s" is not valid for type "%s". Valid scopes: %s. Call code-snippets-get-types-schema for the full reference.',
            $scope,
            $type,
            implode(', ', $allowed[$type]),
        );
    }
    if (!in_array($type, cs_available_types(), strict: true)) {
        return sprintf(
            'Snippet type "%s" requires Code Snippets Pro. The free plugin only supports: %s.',
            $type,
            implode(', ', cs_available_types()),
        );
    }
    return null;
}

/**
 * Loads a snippet by id, returns null when the snippet does not exist.
 * The plugin's `get_snippet()` returns a Snippet instance even for missing
 * ids (with id=0), so we check the returned id to decide. 3.10 declares
 * the return type nullable, so guard that branch too.
 */
function cs_load_snippet(int $id): \Code_Snippets\Snippet|\Code_Snippets\Model\Snippet|null
{
    $snippet = \Code_Snippets\get_snippet($id);
    if ($snippet === null || $snippet->id === 0) {
        return null;
    }
    return $snippet;
}

/**
 * Human-readable message from a Snippet's `code_error`, or '' when there is
 * none. Code Snippets sets this on save (it test-runs a PHP snippet's code) and
 * stores it as false/null when clean, or a string or a `[message, line]` array
 * when the code raised an error.
 */
function cs_code_error_message(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $snippet): string
{
    /** @var mixed $err */
    $err = $snippet->code_error;
    if (is_string($err)) {
        return $err;
    }
    if (is_array($err) && isset($err[0]) && is_string($err[0])) {
        return $err[0];
    }
    return '';
}

/**
 * Whether the fields we set on `$intended` are what the plugin actually
 * stored. save_snippet() reports success from the returned object, but
 * some builds return a re-read of the row without checking the UPDATE
 * result, so a failed DB write would otherwise look like a clean save.
 * Re-read the row and compare the writable fields (the plugin normalises
 * `code` on the passed object before writing, so it compares equal).
 */
function cs_saved_fields_landed(
    \Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $intended,
    \Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $saved,
): bool {
    $stored = cs_load_snippet((int) $saved->id);
    if ($stored === null) {
        return false;
    }
    return cs_writable_fields($stored) === cs_writable_fields($intended);
}

/**
 * The writable fields of a snippet in a normalised, directly comparable
 * shape. Only fields the plugin stores verbatim take part (name, desc and
 * code go into the row untouched — save_snippet() normalises `code` on
 * the passed object before writing, so the intended value already matches;
 * priority and condition_id are absint() on both sides). `tags` are the
 * one field the plugin rewrites on re-read (the stored comma list goes
 * through wp_strip_all_tags() + explode), so both sides are pushed through
 * that same normaliser — a landed save can never compare unequal. `active`
 * and `network` are excluded on purpose: the plugin may flip `active`
 * itself (code error on save, shared-network rows).
 *
 * @return array<string, mixed>
 */
function cs_writable_fields(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $snippet): array
{
    return [
        'name' => $snippet->name,
        'desc' => $snippet->desc,
        'code' => $snippet->code,
        'scope' => $snippet->scope,
        'priority' => (int) $snippet->priority,
        'condition_id' => (int) $snippet->condition_id,
        'tags' => cs_plugin_normalised_tags($snippet->tags),
    ];
}

/**
 * Tags as the plugin will hand them back after a round trip: it stores
 * `implode(', ', $tags)` and re-hydrates the string through its own
 * code_snippets_build_tags_array() (wp_strip_all_tags + explode). That
 * function is not idempotent on its own — `['<b></b>']` becomes `['']`
 * on the first pass and `[]` on the next (empty() short-circuits) — so
 * after running it, empty entries are dropped and the list re-indexed.
 * Both compared sides go through this exact pipeline, which makes it a
 * fixed point: applying it to its own output changes nothing.
 *
 * @param array<array-key, mixed> $tags
 * @return list<string>
 */
function cs_plugin_normalised_tags(array $tags): array
{
    $list = array_map('strval', array_values($tags));
    if ($list === []) {
        return [];
    }
    $normalised = array_map('strval', \Code_Snippets\code_snippets_build_tags_array(implode(', ', $list)));
    return array_values(array_filter($normalised, static fn(string $tag): bool => trim($tag) !== ''));
}

/**
 * Build the edit-snippet success result. Code Snippets only test-runs a PHP
 * snippet's code (and records a code_error) for a snippet it is keeping active,
 * flipping it inactive when that test fails (see save_snippet()). So a
 * non-empty code_error after save means this edit silently deactivated a live
 * snippet; surface it (deactivated_on_save + code_error + a note) instead of
 * returning a bare active:false the agent can miss.
 *
 * @return array<string, mixed>
 */
function cs_edit_result(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $saved): array
{
    $result = ['success' => true, 'snippet' => cs_snippet_to_array($saved)];

    $code_error = cs_code_error_message($saved);
    if ($code_error !== '') {
        $result['deactivated_on_save'] = true;
        $result['code_error'] = $code_error;
        $result['note'] = 'This snippet was active but Code Snippets deactivated it on save because its new code raised an error while being validated. Fix the code, or if it only runs in its real context, re-enable it with code-snippets-enable-snippet.';
    }
    return $result;
}

/**
 * Apply field updates from an input payload onto a Snippet instance.
 * Used by both create-snippet (with a fresh Snippet) and edit-snippet
 * (with a hydrated one) so the per-field "only update when present and
 * the right type" logic lives in a single place — and so each ability
 * callback stays small enough for the mago complexity checks.
 *
 * `scope` is intentionally NOT touched here — callers handle scope
 * because it needs the type+scope pair validation done first.
 *
 * @param array<string, mixed> $input
 */
function cs_apply_snippet_fields(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $snippet, array $input): void
{
    if (isset($input['name']) && is_string($input['name'])) {
        $snippet->name = $input['name'];
    }
    if (isset($input['code']) && is_string($input['code'])) {
        $snippet->code = $input['code'];
    }
    if (isset($input['desc']) && is_string($input['desc'])) {
        $snippet->desc = $input['desc'];
    }
    if (isset($input['tags']) && is_array($input['tags'])) {
        $snippet->tags = cs_normalize_tags($input['tags']);
    }
    if (isset($input['priority']) && is_int($input['priority'])) {
        $snippet->priority = $input['priority'];
    }
    if (array_key_exists('condition_id', $input) && is_int($input['condition_id'])) {
        $snippet->condition_id = $input['condition_id'];
    }
}

/**
 * Normalises a tag input list. Code Snippets stores tags as a
 * comma-separated string in the DB and re-explodes on read, so any tag
 * containing a comma silently splits on the next read — Round 2 R11
 * reproduced this on real input. Trim whitespace (the plugin's own
 * normaliser is asymmetric and lossy), drop empty entries, and refuse
 * the whole list when any tag carries a literal comma so the agent gets
 * an actionable signal instead of silent data loss.
 *
 * @param array<array-key, mixed> $tags
 * @return list<string>
 * @throws \InvalidArgumentException When a tag contains a comma.
 */
function cs_normalize_tags(array $tags): array
{
    $out = [];
    /** @var mixed $tag */
    foreach ($tags as $tag) {
        $s = is_string($tag) ? trim($tag) : trim((string) $tag);
        if ($s === '') {
            continue;
        }
        if (str_contains($s, ',')) {
            throw new \InvalidArgumentException(sprintf(
                'Tag "%s" contains a comma. Code Snippets stores tags as a comma-separated string and would silently split this tag on next read.',
                $s,
            ));
        }
        $out[] = $s;
    }
    return $out;
}

/**
 * Returns null when the snippet's type is currently runnable on this
 * install (free types always, Pro types only when Code Snippets Pro is
 * active), or an error message when it isn't. Pro-type rows survive a
 * Pro deactivation in the DB — operating on them via enable/disable/edit
 * would succeed at the storage layer but the plugin can't actually fire
 * the hooks, so we refuse with a machine-stable `cs_pro_required`
 * signal (Round 1 audit S4).
 */
function cs_require_type_available(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $snippet): ?string
{
    if (in_array($snippet->type, cs_available_types(), strict: true)) {
        return null;
    }
    return sprintf(
        'Snippet type "%s" requires Code Snippets Pro. The free plugin only supports: %s.',
        $snippet->type,
        implode(', ', cs_available_types()),
    );
}

/**
 * Cooperative cross-request lock around a critical section that touches
 * a single snippet row. Round 1 audit S7 reproduced ~40% silent data
 * loss across parallel `edit-snippet` calls to the same id because the
 * implementation is a read-modify-write at PHP level (load Snippet,
 * mutate one field, save_snippet writes ALL columns). The plugin
 * itself doesn't lock either, so we wrap our save path in a MySQL
 * advisory lock keyed on the snippet id + blog id.
 *
 * Mirrors the kadence kd_with_options_lock pattern. Falls through
 * silently when GET_LOCK isn't supported (SQLite drop-in / unusual
 * hosts) — still strictly better than no batching at all.
 *
 * @template T
 * @param callable(): T $fn
 * @return T
 */
function cs_with_snippet_lock(int $snippet_id, callable $fn): mixed
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    $lock_name = sprintf('nvp_cs_%d_%d', get_current_blog_id(), $snippet_id);

    /** @var mixed $acquired_raw */
    // @mago-expect analysis:possibly-invalid-argument
    $acquired_raw = $wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s, %d)', $lock_name, 5));
    $acquired = $acquired_raw === '1' || $acquired_raw === 1;

    try {
        return $fn();
    } finally {
        if ($acquired) {
            // @mago-expect analysis:possibly-invalid-argument
            $wpdb->query($wpdb->prepare('SELECT RELEASE_LOCK(%s)', $lock_name));
        }
    }
}
