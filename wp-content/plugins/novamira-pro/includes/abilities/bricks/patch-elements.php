<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

/**
 * Ability: Patch individual Bricks elements in place without replacing the tree.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-patch-elements', [
    'label' => __('Patch Bricks Elements', domain: 'novamira-pro'),
    'description' => __(
        'Applies targeted updates to specific elements in a Bricks area without replacing the full element tree. Use this for small fixes — correcting a setting on one element, changing a container\'s _direction, tweaking typography, removing a key with unset_settings — instead of re-sending the entire tree. Check the element\'s real control keys first with novamira/bricks-list-elements (element="<name>", include_shared_controls=true) and load the "bricks-build-page" skill via novamira/skill-get for the workflow. Patch settings only: it cannot change name, parent or structure (use bricks-insert-content / bricks-remove-content). NEVER paste raw HTML into a text, text-basic or code element\'s text — one native element per semantic node; layout markup written into a text is reported as raw_layout_html_ids[]. Use var(--token) / rem values, never raw px.',
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
                'description' => 'Which area to patch: "header", "content", or "footer".',
                'enum' => ['header', 'content', 'footer'],
            ],
            'patches' => [
                'type' => 'array',
                'description' => 'List of per-element patches.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => ['type' => 'string', 'description' => 'ID of the element to patch.'],
                        'settings' => [
                            'type' => 'object',
                            'description' => 'Settings to merge into (or replace) the element settings. Optional when unset_settings is given.',
                        ],
                        'unset_settings' => [
                            'type' => 'array',
                            'items' => ['type' => 'string'],
                            'description' => 'Setting keys to REMOVE from this element before the merge is applied, e.g. ["_display", "_background"]. Nested paths use dots: "_typography.font-size", "_border.radius", "link.newTab". A key that does not exist is a no-op. This is the only way to delete a key — merging null keeps the key with a null value. It is also how a member is taken out of the query object, e.g. ["query.randomSeedTtl"]; removing a member is not the same as switching its feature off, because an absent member falls back to Bricks\' own default for it.',
                        ],
                    ],
                    'required' => ['id'],
                ],
            ],
            'merge' => [
                'type' => 'boolean',
                'default' => true,
                'description' => 'True = deep-merge patch settings into existing ones. False = replace the element settings entirely. `_interactions` managed via novamira/bricks-add-interaction et al. is carried over across replace unless the patch explicitly sets the key; pass `_interactions: []` to clear, or use novamira/bricks-delete-interaction.',
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
        'required' => ['post_id', 'area', 'patches'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'area' => ['type' => 'string'],
            'patched' => ['type' => 'integer'],
            'revision_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Id of the WordPress revision created before this write, carrying the Bricks element meta of every area as it was — restorable from the Bricks builder\'s revision panel or WordPress. Null when revisions are disabled for the post type (a warning says so).',
            ],
            'element_count' => ['type' => 'integer', 'description' => 'Elements in the area after the patch.'],
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
            'not_found' => ['type' => 'array', 'items' => ['type' => 'string']],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            'raw_layout_html_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when a patched text / text-basic / text-link element now carries layout markup, <style>, <script> or inline style attributes in its text.',
            ],
            'empty_structural_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when a patched section / container / block has no children.',
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
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_patch_elements',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use this to fix or tweak specific elements without re-sending the whole tree. Ideal for: correcting one setting, switching a container to _display:"grid", tweaking _typography on a heading, fixing a button link, removing a key with unset_settings. Each patch targets an element by id and merges (or replaces) its settings. Unknown element ids are reported in "not_found" rather than erroring. Prefer this over bricks-set-content for targeted fixes — it is faster, safer, and avoids sending large payloads that can silently fail.

Style reuse hierarchy. Before writing raw values:
  Tier 1: respect active theme styles (do not override h1-h6/p/a defaults in element settings).
  Tier 2: apply existing global class via _cssGlobalClasses (look up via novamira/bricks-list-global-classes; attach via novamira/bricks-apply-global-class).
  Tier 3: reference existing variable: "color": {"raw": "var(--brand-primary)"} (look up via novamira/bricks-list-variables).
  Tier 4: create a token first via novamira/bricks-create-variable / bricks-create-global-class / bricks-add-color-palette-entry, then reference it.
  Tier 5: raw values in controls — only for non-systemic one-offs (animation timing, single transforms, clip-paths).
  Tier 6: _cssCustom — last resort.
Hex colors, px values on _padding/_margin/_gap, font sizes/weights/family, radii → NEVER raw. Always tokenize.

Applying global classes: "_cssGlobalClasses" is a flat list of class-ID strings, NOT {id, name} objects. Example: "_cssGlobalClasses": ["clshov"]. The {id, name, settings} shape returned by bricks-list-global-classes is the class *definition* — elements reference classes by ID only. Passing objects here would fatally crash Bricks at render time, so the call is rejected. Prefer novamira/bricks-apply-global-class over editing _cssGlobalClasses by hand — it is idempotent and validates the class id exists.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_patch_elements(array $input): array|WP_Error
{
    $post_id = (int) $input['post_id'];
    $area = (string) $input['area'];
    /** @var list<array{id: string, settings?: array<string, mixed>, unset_settings?: list<string>}> $patches */
    $patches = $input['patches'];
    $merge = ($input['merge'] ?? true) === true;

    $input_error = pe_validate_patch_inputs($patches);
    if ($input_error instanceof WP_Error) {
        return $input_error;
    }

    $loaded = pe_load_area_elements($post_id, $area);
    if ($loaded instanceof WP_Error) {
        return $loaded;
    }
    $elements = $loaded;

    [$elements, $patched, $not_found] = $merge
        ? bricks_apply_patches_merge($elements, $patches)
        : bricks_apply_patches_replace($elements, $patches);
    $patched_ids = pe_patched_ids($patches, $not_found);

    $elements = bricks_normalize_write_elements($elements);

    $validation = bricks_validate_elements($elements, $patched_ids);
    $warnings = $validation['warnings'];
    $errors = $validation['errors'];

    if (count($errors) > 0) {
        return bricks_write_validation_error($post_id, $area, $errors);
    }

    $commit = bricks_commit_area_write($post_id, $area, $elements, $warnings, [
        'force' => ($input['force'] ?? false) === true,
    ]);
    if ($commit instanceof WP_Error) {
        return $commit;
    }

    return pe_build_result([
        'input' => $input,
        'post_id' => $post_id,
        'area' => $area,
        'patched' => $patched,
        'patched_ids' => $patched_ids,
        'not_found' => $not_found,
    ], $commit);
}

/**
 * Response shape of a completed patch: counts, structural lint output for
 * the patched elements, optional generated CSS and the merged warnings.
 *
 * @param array{input: array<array-key, mixed>, post_id: int, area: string, patched: int, patched_ids: list<string>, not_found: list<string>} $context
 * @param array{elements: list<array<string, mixed>>, warnings: list<string>, verified: bool, stored_element_count: int, css_file: string, unresolved_variables: list<string>, revision_id: int|null} $commit
 * @return array<string, mixed>
 */
function pe_build_result(array $context, array $commit): array
{
    $elements = $commit['elements'];
    $warnings = $commit['warnings'];
    $result = bricks_with_write_verification([
        'post_id' => $context['post_id'],
        'area' => $context['area'],
        'patched' => $context['patched'],
        'element_count' => count($elements),
    ], $commit);

    if ($context['not_found'] !== []) {
        $result['not_found'] = $context['not_found'];
    }

    [$result, $warnings] = bricks_apply_lint_result(
        $result,
        $warnings,
        bricks_lint_structure($elements, $context['patched_ids']),
    );

    if (($context['input']['return_css'] ?? false) === true) {
        [$result, $warnings] = bricks_append_return_css($result, $elements, $context['area'], $warnings);
    }

    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }

    return $result;
}

/**
 * Every patch must carry `settings` and/or `unset_settings`. The schema only
 * types the keys it declares, so the runtime shape is checked here.
 *
 * @param list<array<string, mixed>> $patches
 */
function pe_validate_patch_inputs(array $patches): ?WP_Error
{
    foreach ($patches as $i => $patch) {
        if (is_array($patch['settings'] ?? null) || is_array($patch['unset_settings'] ?? null)) {
            continue;
        }
        return bricks_error('invalid_input', sprintf(
            'Patch %d ("%s") needs "settings" (keys to merge or replace) and/or "unset_settings" (keys to remove).',
            $i,
            (string) ($patch['id'] ?? ''),
        ));
    }

    return null;
}

/**
 * Ids of the patches that targeted an existing element.
 *
 * @param list<array{id: string, settings?: array<string, mixed>, unset_settings?: list<string>}> $patches
 * @param list<string> $not_found
 * @return list<string>
 */
function pe_patched_ids(array $patches, array $not_found): array
{
    $ids = array_map(static fn(array $patch): string => $patch['id'], $patches);

    return array_values(array_diff($ids, $not_found));
}

/**
 * Loads the stored element tree of an area for patching. Returns WP_Error
 * when the post does not exist or the area holds no elements.
 *
 * @return list<array<string, mixed>>|WP_Error
 */
function pe_load_area_elements(int $post_id, string $area): array|WP_Error
{
    /** @var WP_Post|null $post */
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
    }

    $elements = bricks_area_elements_or_error($post_id, $area, action: 'nothing can be patched into it');
    if ($elements instanceof WP_Error) {
        return $elements;
    }

    if ($elements === []) {
        return bricks_error('empty_area', sprintf(
            'The "%s" area on post %d has no elements to patch.',
            $area,
            $post_id,
        ));
    }

    return $elements;
}

/**
 * Applies settings patches to a flat Bricks element array by deep-merging
 * each patch into the element's existing settings.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<array{id: string, settings?: array<string, mixed>, unset_settings?: list<string>}> $patches
 * @return array{0: list<array<string, mixed>>, 1: int, 2: list<string>}
 */
function bricks_apply_patches_merge(array $elements, array $patches): array
{
    return bricks_apply_patches_with($elements, $patches, static fn(
        array $existing,
        array $new,
    ): array => bricks_deep_merge($existing, $new));
}

/**
 * Applies settings patches to a flat Bricks element array by replacing each
 * targeted element's settings entirely. `_interactions` is carried over from
 * the existing settings unless the patch explicitly sets it, so callers using
 * the dedicated interactions abilities don't silently wipe their animations
 * when force-replacing another field.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<array{id: string, settings?: array<string, mixed>, unset_settings?: list<string>}> $patches
 * @return array{0: list<array<string, mixed>>, 1: int, 2: list<string>}
 */
function bricks_apply_patches_replace(array $elements, array $patches): array
{
    return bricks_apply_patches_with($elements, $patches, static function (array $existing, array $new): array {
        if (array_key_exists('_interactions', $existing) && !array_key_exists('_interactions', $new)) {
            /** @var mixed $interactions */
            $interactions = $existing['_interactions'];
            $new['_interactions'] = $interactions;
        }
        return $new;
    });
}

/**
 * Shared worker for the merge/replace patch-application variants. Per patch:
 * the keys listed in `unset_settings` are removed from the existing settings
 * first (so an unset followed by a merge of the same key ends with the new
 * value), then `settings` is combined in. A patch with only `unset_settings`
 * combines an empty object, which in merge mode is a no-op and in replace
 * mode empties the element except for the carried-over `_interactions`.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<array{id: string, settings?: array<string, mixed>, unset_settings?: list<string>}> $patches
 * @param callable(array<string, mixed>, array<string, mixed>): array<string, mixed> $combine
 * @return array{0: list<array<string, mixed>>, 1: int, 2: list<string>}
 */
function bricks_apply_patches_with(array $elements, array $patches, callable $combine): array
{
    /** @var array<string, int> $index */
    $index = [];
    foreach ($elements as $i => $el) {
        $eid = (string) ($el['id'] ?? '');
        if ($eid !== '') {
            $index[$eid] = $i;
        }
    }

    $not_found = [];
    $patched = 0;

    foreach ($patches as $patch) {
        $pid = $patch['id'];
        if (!array_key_exists($pid, $index)) {
            $not_found[] = $pid;
            continue;
        }

        $i = $index[$pid];
        /** @var array<string, mixed> $existing */
        $existing = is_array($elements[$i]['settings'] ?? null) ? $elements[$i]['settings'] : [];

        /** @var mixed $path */
        foreach ($patch['unset_settings'] ?? [] as $path) {
            if (!is_string($path) || $path === '') {
                continue;
            }
            $existing = bricks_unset_setting_path($existing, $path);
        }

        $new = is_array($patch['settings'] ?? null) ? $patch['settings'] : [];
        $elements[$i]['settings'] = $combine($existing, $new);
        $patched++;
    }

    return [$elements, $patched, $not_found];
}

/**
 * Removes a (possibly dotted) key path from a settings array. Missing
 * segments are a no-op; a segment that lands on a non-array stops the walk.
 * The path is matched literally first (Bricks keys such as
 * `_typography:tablet_portrait` contain no dots, but a key could), then as a
 * dotted path.
 *
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function bricks_unset_setting_path(array $settings, string $path): array
{
    if (array_key_exists($path, $settings)) {
        unset($settings[$path]);
        return $settings;
    }

    $segments = explode('.', $path);
    if (count($segments) < 2) {
        return $settings;
    }

    $head = array_shift($segments);
    if (!is_array($settings[$head] ?? null)) {
        return $settings;
    }

    /** @var array<string, mixed> $child */
    $child = $settings[$head];
    $settings[$head] = bricks_unset_setting_path($child, implode('.', $segments));

    return $settings;
}
