<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Ability: resolve a VoxelScript dynamic data expression against explicitly
 * bound entity contexts (@post/@author/@user/@term never come from ambient
 * request globals). One ambient surface remains: the site group method
 * query_var() reads Voxel's visibility-context stack first and falls back to
 * the current request's $_GET; this ability neither supplies nor clears that
 * stack, so expressions using query_var can depend on ambient Voxel state.
 */

/** Byte cap on the expression input (Voxel tokenizes without an own cap). */
const VX_DD_MAX_EXPRESSION_BYTES = 16_384;

wp_register_ability('novamira/voxel-render-dynamic-data', [
    'label' => __('Render Voxel Dynamic Data', domain: 'novamira-pro'),
    'description' => __(
        'Resolve a Voxel dynamic data expression (VoxelScript — dynamic tags like @post(title), @user(email), @site(url), with modifiers such as .fallback(text), .date_format(F j Y), .truncate(80) and conditionals like .is_equal_to(x).then(y).else(z)) against explicitly bound entity contexts. Pass post_id (enables @post and @author), user_id (@user) and/or term_id (@term); @site always works and needs no context. Entity contexts never fall back to the ambient request; the one ambient-dependent surface is the site group method @site().query_var(name), which reads Voxel\'s ambient visibility-context stack first and falls back to the current request\'s $_GET — this ability neither supplies nor clears that stack, so query_var results can depend on ambient Voxel state. Engine semantics: a tag whose group has no context stays as literal text, an unknown property renders as an empty string (use .fallback()), an unknown modifier is ignored. Rendering runs Voxel\'s real pipeline: @post(content) applies the_content filters (shortcodes, third-party hooks) and stats/count properties may populate Voxel\'s cache storage (options, post meta or user meta depending on the group) — do not treat a render as fully side-effect free; a best-effort warnings[] entry flags these when spotted in the expression.',
        domain: 'novamira-pro',
    ),
    'category' => 'voxel',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'expression' => [
                'type' => 'string',
                'minLength' => 1,
                'maxLength' => VX_DD_MAX_EXPRESSION_BYTES,
                'description' => 'The VoxelScript expression. Plain text passes through; tags are @group(property.path) with optional .modifier(args) chains.',
            ],
            'post_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Post to bind @post to; @author becomes that post\'s author.',
            ],
            'user_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'User to bind @user to.',
            ],
            'term_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Term to bind @term to.',
            ],
            'max_output_bytes' => [
                'type' => 'integer',
                'description' => 'Byte cap for the rendered value (default 4096, hard ceiling 65536). Truncation is marked, never silent.',
            ],
        ],
        'required' => ['expression'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'value' => ['type' => 'string', 'description' => 'The rendered expression.'],
            'truncated' => ['type' => 'boolean'],
            'full_size' => ['type' => 'integer', 'description' => 'Byte size of the untruncated rendered value.'],
            'contexts' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'The tag groups that were bound to real entities for this render.',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Degraded-but-successful outcomes, e.g. tags referencing a context that was not supplied.',
            ],
        ],
        'required' => ['value', 'truncated', 'full_size', 'contexts', 'warnings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Voxel\\voxel_render_dynamic_data',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover groups, property paths and modifiers with novamira/voxel-list-dynamic-data first. A tag left as literal text in the output means its group had no context (check warnings); an empty string usually means an unknown property or an empty value — chain .fallback(text) to tell them apart. Expressions can read user emails and arbitrary meta via @user(email) or .meta(key); treat rendered output as sensitive.',
            // Not readonly: rendering executes Voxel's real property callbacks —
            // stats properties persist cached stats (options / post meta / user
            // meta) and @post(content) runs the_content filters — so a readonly
            // promise would be untruthful even though the ability never
            // intentionally writes content.
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array{value: string, truncated: bool, full_size: int, contexts: list<string>, warnings: list<string>}|WP_Error
 */
function voxel_render_dynamic_data(array $input): array|WP_Error
{
    $guard = vx_require_min();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    /** @var mixed $expression */
    $expression = $input['expression'] ?? null;
    if (!is_string($expression) || $expression === '') {
        return vx_invalid('expression must be a non-empty string.');
    }
    if (strlen($expression) > VX_DD_MAX_EXPRESSION_BYTES) {
        return vx_invalid(sprintf('expression exceeds the %d-byte limit.', VX_DD_MAX_EXPRESSION_BYTES));
    }

    // Voxel's renderer performs no capability or privacy checks of its own
    // (voxel/app/utils/utils.php render() calls straight into the engine), so
    // novamira_permission_callback on this ability is the entire authorization
    // layer: only agents already trusted with admin-level access can render.
    $groups = ['site' => \Voxel\Dynamic_Data\Group::Site()];
    $contexts = ['site'];
    $warnings = [];

    $bind_error =
        dd_bind_post_context($input, $groups, $contexts, $warnings) ?? dd_bind_user_context(
            $input,
            $groups,
            $contexts,
        ) ?? dd_bind_term_context($input, $groups, $contexts);
    if ($bind_error instanceof WP_Error) {
        return $bind_error;
    }

    $missing_warning = dd_missing_context_warning($expression, $groups);
    if ($missing_warning !== null) {
        $warnings[] = $missing_warning;
    }
    foreach (dd_side_effect_warnings($expression) as $side_effect_warning) {
        $warnings[] = $side_effect_warning;
    }

    try {
        $rendered = \Voxel\render($expression, $groups);
    } catch (\Throwable $e) {
        return vx_operation_failed(sprintf('Voxel failed to render the expression: %s', $e->getMessage()));
    }

    $clip = vx_truncate_field($rendered, vx_resolve_max_field_bytes($input['max_output_bytes'] ?? null));

    return [
        'value' => $clip['value'],
        'truncated' => $clip['truncated'],
        'full_size' => $clip['full_size'],
        'contexts' => $contexts,
        'warnings' => $warnings,
    ];
}

/**
 * Bind @post (and the derived @author) when post_id was supplied. Returns a
 * WP_Error for an invalid or unknown id, null otherwise.
 *
 * @param array<string, mixed> $input
 * @param array<string, \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group|null> $groups
 * @param list<string> $contexts
 * @param list<string> $warnings
 */
function dd_bind_post_context(array $input, array &$groups, array &$contexts, array &$warnings): ?WP_Error
{
    if (!array_key_exists('post_id', $input)) {
        return null;
    }
    $post_id = vx_coerce_int($input['post_id']);
    if ($post_id === null || $post_id < 1) {
        return vx_invalid('post_id must be a positive integer.');
    }
    $post = \Voxel\Post::get($post_id);
    if ($post === null) {
        return vx_not_found('post', (string) $post_id);
    }
    if ($post->post_type === null) {
        // Voxel cannot build a Post_Data_Group for a post whose post type is no
        // longer registered; fail cleanly instead of letting the theme throw.
        return vx_invalid(sprintf(
            'Post %d belongs to an unregistered post type and cannot provide a @post context.',
            $post_id,
        ));
    }

    $groups['post'] = \Voxel\Dynamic_Data\Group::Post($post);
    $contexts[] = 'post';

    // A null author entry becomes a no-op group in Voxel's renderer, so
    // @author(...) renders as empty strings instead of leaking literal text.
    $author = $post->get_author();
    $groups['author'] = \Voxel\Dynamic_Data\Group::User($author);
    if ($author === null) {
        $warnings[] = sprintf('Post %d has no resolvable author; @author(...) renders as empty strings.', $post_id);
        return null;
    }
    $contexts[] = 'author';

    return null;
}

/**
 * Bind @user when user_id was supplied. Returns a WP_Error for an invalid or
 * unknown id, null otherwise.
 *
 * @param array<string, mixed> $input
 * @param array<string, \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group|null> $groups
 * @param list<string> $contexts
 */
function dd_bind_user_context(array $input, array &$groups, array &$contexts): ?WP_Error
{
    if (!array_key_exists('user_id', $input)) {
        return null;
    }
    $user_id = vx_coerce_int($input['user_id']);
    if ($user_id === null || $user_id < 1) {
        return vx_invalid('user_id must be a positive integer.');
    }
    $user = \Voxel\User::get($user_id);
    if ($user === null) {
        return vx_not_found('user', (string) $user_id);
    }

    $groups['user'] = \Voxel\Dynamic_Data\Group::User($user);
    $contexts[] = 'user';

    return null;
}

/**
 * Bind @term when term_id was supplied. Returns a WP_Error for an invalid or
 * unknown id, null otherwise.
 *
 * @param array<string, mixed> $input
 * @param array<string, \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group|null> $groups
 * @param list<string> $contexts
 */
function dd_bind_term_context(array $input, array &$groups, array &$contexts): ?WP_Error
{
    if (!array_key_exists('term_id', $input)) {
        return null;
    }
    $term_id = vx_coerce_int($input['term_id']);
    if ($term_id === null || $term_id < 1) {
        return vx_invalid('term_id must be a positive integer.');
    }
    $term = \Voxel\Term::get($term_id);
    if ($term === null) {
        return vx_not_found('term', (string) $term_id);
    }

    $groups['term'] = \Voxel\Dynamic_Data\Group::Term($term);
    $contexts[] = 'term';

    return null;
}

/**
 * Best-effort side-effect notes for the expression, so degraded expectations
 * reach the agent as warnings[] instead of staying buried in documentation.
 * The scan is textual: nested/indirect reads (e.g. a subgroup reached through
 * a relation field) can slip past it, and a plain-text mention can over-warn —
 * each warning says so.
 *
 * @return list<string>
 */
function dd_side_effect_warnings(string $expression): array
{
    $warnings = [];
    if (preg_match('/@post\(\s*:?content\b/', $expression) === 1) {
        $warnings[] = 'Side-effect note (best-effort expression scan): @post(content) executes the_content filters — shortcodes and third-party hooks run during this render.';
    }
    if (preg_match('/visit_stats|:stats\b/', $expression) === 1) {
        $warnings[] = 'Side-effect note (best-effort expression scan): visit-stats properties may populate Voxel\'s cache storage (options, post meta or user meta depending on the group) on a cold read.';
    }
    if (preg_match('/\b:?(?:reviews|followers|timeline|wall)\b/', $expression) === 1) {
        $warnings[] = 'Side-effect note (best-effort expression scan): review/follower/timeline/wall counts may populate Voxel\'s cache storage (voxel:*_stats keys in post meta, user meta or options depending on the group) on a cold read.';
    }
    if (str_contains($expression, 'query_var')) {
        $warnings[] = 'Ambient-dependence note (best-effort expression scan): @site().query_var() reads Voxel\'s ambient visibility-context stack first, then falls back to the current request\'s $_GET; this ability neither supplies nor clears that stack, so that part of the result can depend on ambient Voxel state.';
    }

    return $warnings;
}

/**
 * A warning naming the expression's tag groups that have no bound context, or
 * null when every referenced group is available.
 *
 * @param array<string, \Voxel\Dynamic_Data\Data_Groups\Base_Data_Group|null> $groups
 */
function dd_missing_context_warning(string $expression, array $groups): ?string
{
    $matches = [];
    preg_match_all('/@([A-Za-z0-9_]+)\(/', $expression, $matches);
    $referenced = $matches[1];

    // `value`, `tags` and `endtags` are engine-internal groups the renderer
    // always provides on its own.
    $available = array_merge(array_keys($groups), ['value', 'tags', 'endtags']);
    $missing = array_values(array_unique(array_diff($referenced, $available)));
    if ($missing === []) {
        return null;
    }

    return sprintf(
        'No context available for @%s; those tags are left as literal text. Contexts: post_id enables @post and @author, user_id enables @user, term_id enables @term; @site needs none.',
        implode(', @', $missing),
    );
}
