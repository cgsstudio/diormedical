<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Weglot;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/weglot-get-status', [
    'label' => __('Get Weglot Cloud Status', domain: 'novamira-pro'),
    'description' => __(
        'Queries the Weglot Cloud /projects/owner endpoint with the configured API key and reports the live project status: project name, plan id and label, the destination-language limit, and word usage against the plan quota. The organization and project slugs come from the project settings, which are resolved locally. Error contract: weglot_no_api_key when no key is set, weglot_cloud_key_invalid when the cloud rejects the key (rotate it, do not retry), weglot_cloud_unreachable for a transient network or upstream failure (retry).',
        domain: 'novamira-pro',
    ),
    'category' => 'weglot',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'project_name' => ['type' => ['string', 'null']],
            'plan_id' => ['type' => ['integer', 'null']],
            'plan_name' => ['type' => ['string', 'null']],
            'language_limit' => ['type' => ['integer', 'null']],
            'words_limit' => ['type' => ['integer', 'null']],
            'words_used' => ['type' => ['integer', 'null']],
            'words_remaining' => ['type' => ['integer', 'null']],
            'over_quota' => ['type' => 'boolean'],
            'organization_slug' => ['type' => ['string', 'null']],
            'project_slug' => ['type' => ['string', 'null']],
        ],
        'required' => [
            'project_name',
            'plan_id',
            'plan_name',
            'language_limit',
            'words_limit',
            'words_used',
            'words_remaining',
            'over_quota',
            'organization_slug',
            'project_slug',
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Weglot\weglot_get_status',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Live cloud read — costs one HTTP round-trip per call. Result is per-request memoized; subsequent calls in the same request are free. Use words_used / words_limit to surface quota pressure before agent-driven bulk operations; over_quota turns true AT the limit, matching what Weglot itself shows. A rejected key returns weglot_cloud_key_invalid, NOT weglot_cloud_unreachable — do not retry that one.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array{
 *     project_name: string|null,
 *     plan_id: int|null,
 *     plan_name: string|null,
 *     language_limit: int|null,
 *     words_limit: int|null,
 *     words_used: int|null,
 *     words_remaining: int|null,
 *     over_quota: bool,
 *     organization_slug: string|null,
 *     project_slug: string|null,
 * }|\WP_Error
 */
function weglot_get_status(): array|\WP_Error
{
    if (!wg_detect_setup()['active']) {
        return wg_not_active();
    }

    $status = wg_user_info_status();
    if ($status['state'] === 'no_credential') {
        return wg_no_api_key();
    }
    if ($status['state'] === 'rejected') {
        return new \WP_Error(
            'weglot_cloud_key_invalid',
            'Weglot Cloud rejected the configured API key. Rotate it and retry — call check-setup to confirm.',
            [
                'status' => 401,
                'cause_code' => $status['error_code'],
                'cause_message' => $status['error_message'],
            ],
        );
    }
    if ($status['state'] === 'unreachable' || $status['answer'] === null) {
        return new \WP_Error(
            'weglot_cloud_unreachable',
            'Weglot Cloud is currently unreachable. Network or upstream failure — retry shortly.',
            [
                'status' => 502,
                'cause_code' => $status['error_code'],
                'cause_message' => $status['error_message'],
            ],
        );
    }

    return wg_status_payload($status['answer']);
}

/**
 * Assemble the get-status response payload from the cached user-info answer.
 *
 * @param  array<string, mixed> $info
 * @return array{project_name: string|null, plan_id: int|null, plan_name: string|null, language_limit: int|null, words_limit: int|null, words_used: int|null, words_remaining: int|null, over_quota: bool, organization_slug: string|null, project_slug: string|null}
 */
function wg_status_payload(array $info): array
{
    $plan_id = wg_coerce_int_or_null($info['plan_id'] ?? null);
    $quota = wg_status_quota($info);

    /** @var mixed $project_name_raw */
    $project_name_raw = $info['project_name'] ?? $info['name'] ?? null;
    // organization_slug / project_slug are PROJECT SETTINGS keys
    // (Schema_Option_V3 ~:328-329), not fields of the /projects/owner answer —
    // reading them from $info returned null on every install. They are already
    // resolved locally, so taking them from there costs nothing.
    //
    // `hosted_url` (and its `cdn_url` fallback) is gone: neither name occurs
    // anywhere in the Weglot plugin or its SDK, and neither is in the live
    // owner payload, whose keys are plan, plan_id, plan_name, project_name,
    // project_id, project_integration_id, user, registration, usage, limit,
    // languages_limit, projects_limit, allowed. It could only ever be null.
    $settings = wg_options_resolved();

    return [
        'project_name' => wg_coerce_string_or_null($project_name_raw),
        'plan_id' => $plan_id,
        'plan_name' => wg_plan_name_from_info($info, $plan_id),
        'language_limit' => wg_language_limit_from_info($info, $plan_id),
        'words_limit' => $quota['limit'],
        'words_used' => $quota['used'],
        'words_remaining' => $quota['remaining'],
        'over_quota' => $quota['over'],
        'organization_slug' => wg_coerce_string_or_null($settings['organization_slug'] ?? null),
        'project_slug' => wg_coerce_string_or_null($settings['project_slug'] ?? null),
    ];
}

/**
 * Derive the word-quota cluster from the loosely-typed user-info answer.
 *
 * @param  array<string, mixed> $info
 * @return array{used: int|null, limit: int|null, remaining: int|null, over: bool}
 */
function wg_status_quota(array $info): array
{
    // `usage` and `limit` are the names /projects/owner actually uses —
    // confirmed against a live response, whose keys are: plan, plan_id,
    // plan_name, project_name, project_id, project_integration_id, user,
    // registration, usage, limit, languages_limit, projects_limit, allowed.
    //
    // The previous names (`words_used`, `words_limit`, and their aliases) are
    // in none of them, so every quota field came back null and `over_quota` was
    // structurally false — it could never report an over-quota project. The old
    // names are kept as a fallback in case a different plan tier answers with
    // them, but they are no longer the primary read.
    $used = wg_coerce_int_or_null($info['usage'] ?? $info['words_used'] ?? $info['total_words'] ?? null);
    $limit = wg_coerce_int_or_null($info['limit'] ?? $info['words_limit'] ?? $info['plan_words_limit'] ?? null);
    $remaining = $used !== null && $limit !== null ? max(0, $limit - $used) : null;
    // `>=`, not `>`: Weglot considers the quota reached at equality —
    // `if ( $user_info['limit'] <= $user_info['usage'] )`, Actions\Admin\
    // Pages_Weglot ~:221. With a strict `>` we reported "fine" on exactly the
    // request where the plugin itself starts showing limit-reached, and that
    // boundary is the one state no test here has ever observed.
    $over = $used !== null && $limit !== null && $used >= $limit;

    return ['used' => $used, 'limit' => $limit, 'remaining' => $remaining, 'over' => $over];
}
