<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\CodeSnippets;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/code-snippets-delete-snippet', [
    'label' => __('Delete Code Snippet', domain: 'novamira-pro'),
    'description' => __(
        'Permanently removes a snippet by id. There is no soft-delete — once deleted the snippet cannot be recovered via the abilities. The plugin removes the row directly (no deactivation step); an active snippet simply stops executing on the next request because it no longer exists. IMPORTANT: delete only removes the snippet row — it does NOT undo durable side effects the snippet body created during prior executions (scheduled WP-Cron events via wp_schedule_event survive the delete and continue firing forever as silent no-ops; dbDelta-created tables remain; persisted options remain). For snippets that wired durable side effects, prefer code-snippets-disable-snippet first, then have the user run a cleanup snippet to reverse those effects, then delete.',
        domain: 'novamira-pro',
    ),
    'category' => 'code-snippets',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'deleted_id' => ['type' => 'integer'],
            'error' => ['type' => 'string'],
            'error_code' => [
                'type' => 'string',
                'description' => 'Machine-stable failure code. cs_snippet_locked: the installed Code Snippets supports snippet locking and this snippet is locked, so the plugin refuses to delete it until it is unlocked in the Code Snippets admin.',
            ],
            'attached_snippet_ids' => [
                'type' => 'array',
                'items' => ['type' => 'integer'],
                'description' => 'Always present when a condition (type=cond) was deleted: ids of the snippets that were gated by it (empty array when none). Details per id in attached_snippets.',
            ],
            'attached_snippets' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'type' => ['type' => 'string'],
                        'active' => ['type' => 'boolean'],
                        'detached' => [
                            'type' => 'boolean',
                            'description' => 'True when the plugin reset this snippet\'s condition_id to 0 as part of the delete. False when it still references the deleted condition.',
                        ],
                        'behaviour' => [
                            'type' => 'string',
                            'enum' => ['unconditional', 'may_run', 'skipped', 'inactive'],
                            'description' => 'What this consumer does on THIS install now that its condition is gone. unconditional: runs on every matching request (detached, or the install never evaluates conditions for its type). may_run: assume it runs — it still references the missing condition and this install treats that as no gate for its type (html in the head / body / footer scopes; css / js when the assets runtime is available), or it is a shortcode whose flat-file config still carries the old condition (renders on normal page loads, empty in bare render contexts), or it is a cond row: a chained condition that is still evaluated and may gate other snippets although its own gate is gone. skipped: does not run on this install (php and shortcode html — scope content — still referencing a missing condition where conditions are evaluated; a detached php snippet whose flat-file config still carries the old condition; css / js rows where the assets runtime is unavailable — see check-setup assets_runtime_available; cond rows where conditions are not evaluated). inactive: the snippet is not active, nothing runs until it is enabled. When flat-file storage is enabled the plugin only rewrites a snippet\'s file config when that snippet is saved, so a detached php or shortcode consumer keeps its old gate on disk until you re-save it (code-snippets-edit-snippet with any field) — after that it is plainly unconditional.',
                        ],
                    ],
                    'required' => ['id', 'type', 'active', 'detached', 'behaviour'],
                ],
                'description' => 'Observed state of each formerly gated snippet, re-read after the delete, with the resulting runtime behaviour on this install. Present whenever a condition was deleted.',
            ],
            'note' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\CodeSnippets\\code_snippets_delete_snippet',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Confirm with the user before deleting — there is no undo via the abilities. If you only want to stop a snippet from running, prefer code-snippets-disable-snippet (keeps the body around so it can be re-enabled). If the snippet wired durable side effects (scheduled WP-Cron events, custom DB tables, persisted options) those SURVIVE delete; the row goes away but the cron events keep firing into the void on every interval. For those, disable → reverse side effects → delete. Deleting a CONDITION (type=cond) changes how the snippets gated by it run, and the outcome depends on the installed plugin — so read the response instead of assuming: attached_snippet_ids / attached_snippets list every formerly gated snippet with observed `active` / `detached` flags and a computed `behaviour` for THIS install (unconditional | may_run | skipped | inactive; see the output schema). Treat unconditional and may_run as "this now runs without its gate": re-gate with code-snippets-edit-snippet { condition_id: <other condition> } or disable with code-snippets-disable-snippet, as the user intends. A type=cond row in the list is a chained condition: it is still evaluated and may gate other snippets — re-gate it or delete it too. skipped rows do not run here (php and shortcode html — scope content — still referencing a missing condition where conditions are evaluated; a detached php snippet whose flat-file config still carries the old condition; css / js rows while check-setup reports assets_runtime_available=false; cond rows where conditions are not evaluated) but still exist and would run once the install can execute them. With flat-file storage enabled, a detached php or shortcode consumer keeps the old gate in its file config until it is saved again: re-save it with code-snippets-edit-snippet (any field) to make it plainly unconditional, or disable it.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array{id: int} $input
 * @return array<string, mixed>|WP_Error
 */
function code_snippets_delete_snippet(array $input): array|WP_Error
{
    $id = $input['id'];
    if ($id <= 0) {
        return new WP_Error('cs_invalid_input', __('id must be a positive integer.', domain: 'novamira-pro'));
    }
    $snippet = cs_load_snippet($id);
    if ($snippet === null) {
        return new WP_Error('cs_not_found', sprintf(__('Snippet with id %d not found.', domain: 'novamira-pro'), $id));
    }
    // Code Snippets 3.10+ locking: delete_snippet() returns false for a
    // locked row, indistinguishable from a DB error. Name the cause.
    if (cs_is_locked($snippet)) {
        return [
            'success' => false,
            'error_code' => 'cs_snippet_locked',
            'error' => sprintf(
                'Snippet %d is locked in Code Snippets and cannot be deleted. Ask the user to unlock it from the Code Snippets admin first.',
                $id,
            ),
        ];
    }
    // Snapshot the consumers of a condition BEFORE the row goes: a build
    // that detaches consumers on delete resets their condition_id, so they
    // are unrecoverable from the DB afterwards.
    $attached = $snippet->type === 'cond' ? cs_condition_consumer_ids($id) : [];
    $ok = \Code_Snippets\delete_snippet($id);
    if (!$ok) {
        return ['success' => false, 'error' => 'Snippet delete failed (database error).'];
    }
    $out = ['success' => true, 'deleted_id' => $id];
    if ($snippet->type === 'cond') {
        $out['attached_snippet_ids'] = $attached;
        $out['attached_snippets'] = cs_condition_consumers_after_delete($attached);
        if ($attached !== []) {
            $out['note'] = cs_condition_delete_note();
        }
    }
    return $out;
}

/**
 * Ids of the snippets whose `condition_id` points at the given condition.
 * Pro builds expose DB::fetch_condition_consumer_ids() (one indexed
 * SELECT); fall back to scanning get_snippets() where it is absent.
 *
 * @return list<int>
 */
function cs_condition_consumer_ids(int $condition_id): array
{
    $db = \Code_Snippets\code_snippets()->db;
    if (method_exists($db, 'fetch_condition_consumer_ids')) {
        $ids = [];
        /** @var mixed $raw */
        foreach ($db->fetch_condition_consumer_ids($condition_id) as $raw) {
            if (!is_int($raw) && !is_numeric($raw)) {
                continue;
            }
            $ids[] = (int) $raw;
        }
        return $ids;
    }
    $ids = [];
    foreach (\Code_Snippets\get_snippets() as $row) {
        if ((int) $row->condition_id !== $condition_id) {
            continue;
        }
        $ids[] = (int) $row->id;
    }
    return $ids;
}

/**
 * Observed state of the former consumers, re-read after the delete. Some
 * builds reset a consumer's condition_id to 0 when its condition is
 * deleted (`detached`), others leave the dangling reference in place —
 * we report what the row says now rather than guessing from a version.
 *
 * @param list<int> $ids
 * @return list<array{id: int, type: string, active: bool, detached: bool, behaviour: string}>
 */
function cs_condition_consumers_after_delete(array $ids): array
{
    $out = [];
    foreach ($ids as $consumer_id) {
        $consumer = cs_load_snippet($consumer_id);
        if ($consumer === null) {
            continue;
        }
        $out[] = [
            'id' => $consumer_id,
            'type' => $consumer->type,
            'active' => $consumer->active,
            'detached' => (int) $consumer->condition_id === 0,
            'behaviour' => cs_consumer_behaviour($consumer),
        ];
    }
    return $out;
}

/**
 * Runtime behaviour of a formerly gated snippet on this install, derived
 * from variant + licence + type + active + detached. Verified against the
 * plugin:
 *  - a cond row is a chained condition: the model forces its `active` to
 *    false, but the Pro runtime fetches condition-scope rows regardless
 *    of `active` and evaluates their JSON, so on Pro it is still
 *    evaluated and may gate other snippets (may_run); the free runtime
 *    never evaluates conditions (skipped). Decided before the active
 *    check on purpose;
 *  - the free plugin loads only the php / html evaluators (no assets
 *    evaluator, no condition evaluation): active php / html rows run
 *    unconditionally whatever their condition_id, css / js rows never
 *    run there even though get_snippets() still returns them;
 *  - on Pro the asset evaluators register nothing unless the licence
 *    check passes, so css / js rows are skipped while
 *    cs_assets_runtime_available() is false;
 *  - otherwise on Pro a detached row runs unconditionally — unless flat
 *    file storage is on and the row's file config still carries the old
 *    condition (the lifecycle handler detaches in SQL only; the config
 *    is rewritten only when the snippet is saved): the php flat path puts
 *    such a row in its conditional bucket and never finds a result →
 *    skipped, and the shortcode handler reads the stale config → empty
 *    in a bare render context but rendered on a normal page (the head
 *    printer has already required the same index, so the handler's
 *    require_once falls back to the detached DB row) → may_run; head /
 *    body / footer html and css / js flat paths treat the absent
 *    condition as no gate → unconditional. Verified at runtime;
 *  - a php row still referencing a missing condition is skipped (no
 *    entry in the results map); a shortcode html row (scope content) is
 *    skipped too — the handler loads the missing condition as an empty
 *    model and evaluates its empty code to false, returning no output —
 *    while head / body / footer html (the content printer treats an
 *    absent result as passing), css and js rows run → may_run.
 */
function cs_consumer_behaviour(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $consumer): string
{
    $type = $consumer->type;
    if ($type === 'cond') {
        return cs_is_pro() ? 'may_run' : 'skipped';
    }
    if (!$consumer->active) {
        return 'inactive';
    }
    if (!cs_is_pro()) {
        return in_array($type, cs_available_types(), strict: true) ? 'unconditional' : 'skipped';
    }
    if (in_array($type, ['css', 'js'], strict: true) && !cs_assets_runtime_available()) {
        return 'skipped';
    }
    if ((int) $consumer->condition_id === 0) {
        return cs_detached_consumer_behaviour($consumer);
    }
    if ($type === 'html') {
        return $consumer->scope === 'content' ? 'skipped' : 'may_run';
    }
    return in_array($type, ['css', 'js'], strict: true) ? 'may_run' : 'skipped';
}

/**
 * Behaviour of a detached (condition_id = 0) Pro consumer: unconditional,
 * except when the flat-file config the runtime reads still carries the
 * old condition — see cs_consumer_behaviour(). Observed from the file,
 * never assumed; a missing or unreadable config counts as regenerated
 * (the safer direction: the agent is told the code runs). Each type's
 * index is loaded once per request (cs_flat_file_index()).
 */
function cs_detached_consumer_behaviour(\Code_Snippets\Snippet|\Code_Snippets\Model\Snippet $consumer): string
{
    $type = $consumer->type;
    if ($type !== 'php' && !($type === 'html' && $consumer->scope === 'content')) {
        return 'unconditional';
    }
    $on_disk = cs_flat_file_condition_id((int) $consumer->id, cs_snippets_table($consumer), $type);
    if ($on_disk === null || $on_disk === 0) {
        return 'unconditional';
    }
    return $type === 'php' ? 'skipped' : 'may_run';
}

/**
 * Short, generic pointer to the per-item behaviour — the per-variant /
 * per-type reasoning lives in cs_consumer_behaviour().
 */
function cs_condition_delete_note(): string
{
    return 'Check attached_snippets[].behaviour: unconditional and may_run rows now run without their gate — re-gate each with code-snippets-edit-snippet { condition_id: <another condition> } or disable it with code-snippets-disable-snippet (a type=cond row is a chained condition that is still evaluated: re-gate or delete it too); skipped rows do not run on this install; inactive rows run nothing until enabled. With flat-file storage enabled, a detached php or shortcode row keeps its old gate on disk until it is saved again — re-save it to make it plainly unconditional.';
}
