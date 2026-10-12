<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

/**
 * Ability: Remove Bricks elements from an existing area without replacing it.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-remove-content', [
    'label' => __('Remove Bricks Content', domain: 'novamira-pro'),
    'description' => __(
        'Removes existing Bricks elements from an area without replacing the full element tree. Each target ID removes the full subtree rooted at that element, including all descendants. Read the area first with novamira/bricks-get-content (summary: true) to pick the right ids, and before rebuilding what you remove call novamira/bricks-list-elements (element="<name>", include_shared_controls=true) for the real control keys; for the page-building loop (inventory → plan → build section by section with native elements → verify) load the "bricks-build-page" skill via novamira/skill-get. Never replace removed sections with raw HTML pasted into a text, text-basic or code element — rebuild them as native elements with bricks-insert-content.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or template ID.',
            ],
            'area' => [
                'type' => 'string',
                'description' => 'Which area to modify: "header", "content", or "footer".',
                'enum' => ['header', 'content', 'footer'],
            ],
            'element_ids' => [
                'type' => 'array',
                'description' => 'Element IDs to remove. Each ID removes the full subtree rooted at that element.',
                'items' => ['type' => 'string'],
            ],
            'id' => [
                'type' => 'string',
                'description' => 'Single target element ID. Shorthand for element_ids=[id].',
            ],
            'return_css' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'If true, return the CSS for this write: in external CSS file mode the regenerated per-post CSS file, otherwise the inline CSS Bricks generates for the area (css_source says which). Useful for verifying that settings produced the expected styles.',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Write even when another user currently holds the WordPress edit lock on this post (their builder or editor session is open). Without it such a write is refused with locked_by_other_user naming the user.',
            ],
        ],
        'required' => ['post_id', 'area'],
        'additionalProperties' => false,
        'anyOf' => [
            ['required' => ['element_ids']],
            ['required' => ['id']],
        ],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'area' => ['type' => 'string'],
            'removed' => ['type' => 'integer'],
            'revision_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Id of the WordPress revision created before this write, carrying the Bricks element meta of every area as it was — restorable from the Bricks builder\'s revision panel or WordPress. Null when revisions are disabled for the post type (a warning says so).',
            ],
            'removed_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'All removed element IDs, including descendants.',
            ],
            'requested_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'The target IDs requested for removal after normalization and deduplication.',
            ],
            'element_count' => ['type' => 'integer'],
            'saved' => [
                'type' => 'boolean',
                'description' => 'Deprecated alias of verified (kept for existing callers; it is no longer a literal true). Read verified instead.',
            ],
            'verified' => [
                'type' => 'boolean',
                'description' => 'True only when the element tree read back from the database after the write equals the tree that was written. False means the stored content differs — the warnings say how.',
            ],
            'stored_element_count' => [
                'type' => 'integer',
                'description' => 'Number of elements the area holds in the database after the write (read back, not computed from the request).',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => '"regenerated" when Bricks serves CSS from external files and the per-post CSS file was rewritten for this write; "inline" when Bricks serves CSS inline (nothing to regenerate); "skipped(reason)" when file mode is on but the file could not be written — the reason names the fix.',
            ],
            'unresolved_variables' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when a written value references var(--name) for a name that is neither a Bricks global variable, a palette colour nor a custom property declared in the site\'s custom CSS: those declarations are dropped by the browser until the token exists.',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Validation warnings about the resulting element tree (data is still saved).',
            ],
            'css' => [
                'type' => 'string',
                'description' => 'Present only when return_css=true. In external CSS file mode the content of the post\'s regenerated CSS file (what the front end loads, every area of the post); otherwise the inline CSS Bricks generates for this area. css_source says which.',
            ],
            'css_source' => [
                'type' => 'string',
                'enum' => ['file', 'inline'],
                'description' => 'Present only when return_css=true: "file" when css is the regenerated per-post CSS file, "inline" when it is the inline generation result.',
            ],
            'css_truncated' => [
                'type' => 'boolean',
                'description' => 'Present (true) only when css_source is "file" and the file exceeded the return_css limit (256 KiB): css holds the first part and a warning names the file size.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_remove_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Structural Bricks removal without re-sending the full tree. Pass id or element_ids. Each target removes the full subtree rooted at that element, including descendants. Example: remove a section -> id="hero01"; remove two cards -> element_ids=["card-a","card-b"]. All target IDs must exist or the save fails unchanged. Prefer this over bricks-set-content when deleting existing sections, containers, or widgets.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

function bricks_remove_content(array $input): array|WP_Error
{
    $post_id = (int) $input['post_id'];
    /** @var WP_Post|null $post */
    $post = get_post($post_id);

    if (!$post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
    }

    $area = (string) $input['area'];
    $elements = bricks_get_area_elements($post_id, $area);
    $requested_ids = bricks_normalize_remove_ids($input);

    if ($requested_ids === []) {
        return bricks_error(
            'missing_element_ids',
            message: 'Pass id or element_ids with at least one element ID to remove.',
        );
    }

    $prepared_removal = bricks_prepare_remove_payload($elements, $requested_ids);
    if (is_wp_error($prepared_removal)) {
        return $prepared_removal;
    }

    ['elements' => $elements, 'removed_ids' => $removed_ids] = $prepared_removal;

    return bricks_finalize_remove_content([
        'input' => $input,
        'post' => $post,
        'post_id' => $post_id,
        'area' => $area,
        'requested_ids' => $requested_ids,
        'removed_ids' => $removed_ids,
    ], $elements);
}

/**
 * @param array<array-key, mixed> $input
 * @return list<string>
 */
function bricks_normalize_remove_ids(array $input): array
{
    $ids = [];

    if (is_string($input['id'] ?? null) && $input['id'] !== '') {
        $ids[] = $input['id'];
    }

    if (is_array($input['element_ids'] ?? null)) {
        /** @var list<string> $element_ids */
        $element_ids = array_values(array_filter(
            $input['element_ids'],
            static fn(mixed $id): bool => is_string($id) && $id !== '',
        ));
        foreach ($element_ids as $id) {
            $ids[] = $id;
        }
    }

    return array_values(array_unique($ids));
}

/**
 * @param list<array<string, mixed>> $elements
 * @param list<string> $requested_ids
 * @return array{elements: list<array<string, mixed>>, removed_ids: list<string>}|WP_Error
 */
function bricks_prepare_remove_payload(array $elements, array $requested_ids): array|WP_Error
{
    $id_index = bricks_index_elements_by_id($elements);
    $missing_ids = [];

    foreach ($requested_ids as $id) {
        if (array_key_exists($id, $id_index)) {
            continue;
        }

        $missing_ids[] = $id;
    }

    if ($missing_ids !== []) {
        return bricks_error('invalid_elements', sprintf('Target element IDs were not found: %s.', implode(
            ', ',
            $missing_ids,
        )));
    }

    // The row map, not the id map: these are the semantics this walk had
    // before the two were briefly folded together.
    $children_by_parent = bricks_child_rows_by_parent($elements);
    $removed_lookup = [];
    foreach ($requested_ids as $id) {
        bricks_collect_subtree_ids($id, $children_by_parent, $removed_lookup);
    }

    // Take the ids back from the elements, not from the lookup's keys: an
    // all-digit id is an INTEGER key, and removed_ids is declared a list of
    // strings — the ability's own output validation refuses the response
    // otherwise. Document order, which is what the caller sees on the page.
    $removed_ids = [];
    $remaining = [];
    foreach ($elements as $element) {
        $id = (string) ($element['id'] ?? '');
        if ($id !== '' && array_key_exists($id, $removed_lookup)) {
            $removed_ids[] = $id;
            continue;
        }
        $remaining[] = $element;
    }

    return [
        'elements' => $remaining,
        'removed_ids' => $removed_ids,
    ];
}

/**
 * @param array<string, list<string>> $children_by_parent
 * @param array<string, bool> $removed_lookup
 */
function bricks_collect_subtree_ids(string $id, array $children_by_parent, array &$removed_lookup): void
{
    if (array_key_exists($id, $removed_lookup)) {
        return;
    }

    $removed_lookup[$id] = true;
    foreach ($children_by_parent[$id] ?? [] as $child_id) {
        bricks_collect_subtree_ids($child_id, $children_by_parent, $removed_lookup);
    }
}

/**
 * @param array{input: array<array-key, mixed>, post: WP_Post, post_id: int, area: string, requested_ids: list<string>, removed_ids: list<string>} $context
 * @param list<array<string, mixed>> $elements
 */
function bricks_finalize_remove_content(array $context, array $elements): array|WP_Error
{
    $input = $context['input'];
    $post = $context['post'];
    $post_id = $context['post_id'];
    $area = $context['area'];
    $validation = bricks_validate_elements($elements);
    $warnings = $validation['warnings'];
    $errors = $validation['errors'];

    if (count($errors) > 0) {
        return bricks_write_validation_error($post_id, $area, $errors);
    }

    if (in_array($area, ['header', 'footer'], strict: true) && $post->post_type !== BRICKS_DB_TEMPLATE_SLUG) {
        $warnings[] = sprintf(
            'Writing the "%s" area to a non-template post (type: "%s") has no visible effect — Bricks renders headers and footers from template CPTs with conditions. Create a template of type "%s" instead.',
            $area,
            $post->post_type,
            $area,
        );
    }

    $elements = bricks_ensure_children($elements);
    // A removal never adds code: the remaining elements are stored as they
    // are, without re-signing.
    $commit = bricks_commit_area_write($post_id, $area, $elements, $warnings, [
        'sign' => false,
        'force' => ($input['force'] ?? false) === true,
    ]);
    if ($commit instanceof WP_Error) {
        return $commit;
    }

    return bricks_build_remove_result($context, $input, $area, $commit);
}

/**
 * @param array{input: array<array-key, mixed>, post: WP_Post, post_id: int, area: string, requested_ids: list<string>, removed_ids: list<string>} $context
 * @param array{elements: list<array<string, mixed>>, warnings: list<string>, verified: bool, stored_element_count: int, css_file: string, unresolved_variables: list<string>, revision_id: int|null} $commit
 * @return array<string, mixed>
 */
function bricks_build_remove_result(array $context, array $input, string $area, array $commit): array
{
    $elements = $commit['elements'];
    $warnings = $commit['warnings'];
    $result = bricks_with_write_verification([
        'post_id' => $context['post_id'],
        'area' => $area,
        'removed' => count($context['removed_ids']),
        'removed_ids' => $context['removed_ids'],
        'requested_ids' => $context['requested_ids'],
        'element_count' => count($elements),
    ], $commit);

    if (($input['return_css'] ?? false) === true) {
        [$result, $warnings] = bricks_append_return_css($result, $elements, $area, $warnings);
    }

    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }

    return $result;
}
