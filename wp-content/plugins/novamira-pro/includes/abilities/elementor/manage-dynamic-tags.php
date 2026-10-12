<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

use WP_Error;

/**
 * Abilities: Elementor Dynamic Tags — list / get / apply. The dt_* helpers
 * below host the actual logic; the ability callbacks are thin shells that
 * guard the runtime and delegate.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/elementor-list-dynamic-tags', [
    'label' => __('List Elementor Dynamic Tags', domain: 'novamira-pro'),
    'description' => __(
        'Lists Elementor Dynamic Tags registered on this site (core, Pro, third-party). Each entry includes name, title, group, categories, content_type, and a flag whether the tag requires settings. group is normally one group id string; when a tag uses Elementor\'s multi-group registration form it is a list of group id strings, and the group filter matches membership in that list. Returns the list of tag groups too so you can pick a group and drill into its tags by name. Progressive disclosure: the full list is ~4KB (38+ tags); prefer `group: "post"` (or any registered group) or `categories: ["url"]` to filter server-side when you know what you\'re looking for.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'group' => [
                'type' => 'string',
                'description' => 'Filter to tags that belong to this group id (e.g. "post", "site", "author"). For multi-group tags this matches membership in the group list. Unknown groups produce an empty tags list.',
            ],
            'categories' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Filter to tags that declare at least one of these categories (e.g. ["url"], ["text","post_meta"]). Matches ANY of the listed categories.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'tags' => ['type' => 'array'],
            'groups' => ['type' => 'object'],
            'total' => [
                'type' => 'integer',
                'description' => 'Count of tags returned after filtering.',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\list_dynamic_tags',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

wp_register_ability('novamira/elementor-get-dynamic-tag', [
    'label' => __('Get Elementor Dynamic Tag', domain: 'novamira-pro'),
    'description' => __(
        'Reads the configuration of a single Elementor Dynamic Tag by name. Returns its controls (the parameters the tag accepts) so the caller can know what to pass in tag_settings when applying it. group is normally one group id string; when a tag uses Elementor\'s multi-group registration form it is a list of group id strings.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'tag_name' => [
                'type' => 'string',
                'description' => 'The dynamic tag name (e.g. "post-title", "acf-field").',
            ],
        ],
        'required' => ['tag_name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'tag' => ['type' => 'object'],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\get_dynamic_tag',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => true, 'destructive' => false, 'idempotent' => true],
    ],
]);

wp_register_ability('novamira/elementor-apply-dynamic-tag', [
    'label' => __('Apply Elementor Dynamic Tag', domain: 'novamira-pro'),
    'description' => __(
        'Attaches an Elementor Dynamic Tag to a specific setting on a widget on a page. Validation runs server-side: if the target element does not exist, its widget type is unknown, or setting_name is not a real control on the widget the call fails HARD with the compact schema of the target widget INLINE in the error so you can correct and retry. Auto-detects v3 vs v4 atomic format and writes the appropriate shape.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'tag_name' => [
                'type' => 'string',
                'description' => 'The dynamic tag name to attach.',
            ],
            'post_id' => [
                'type' => 'integer',
                'description' => 'The page/post id where the target element lives.',
            ],
            'element_id' => [
                'type' => 'string',
                'description' => 'The Elementor element id of the widget to attach the tag to.',
            ],
            'setting_name' => [
                'type' => 'string',
                'description' => 'The widget setting (control id) to bind the tag to (e.g. "title", "url", "image").',
            ],
            'tag_settings' => [
                'type' => 'object',
                'description' => 'Optional parameters for the tag itself (e.g. {"key": "custom_field_name"} for post-custom-field).',
            ],
        ],
        'required' => ['tag_name', 'post_id', 'element_id', 'setting_name'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'format' => ['type' => 'string'],
            'widget_type' => ['type' => 'string'],
            'setting_name' => ['type' => 'string'],
            'setting_prop_type' => [
                'type' => 'string',
                'description' => 'On combination-validation failures: the compact prop type of the target setting (e.g. "link", "html-v3").',
            ],
            'dropped_keys' => ['type' => 'array', 'items' => ['type' => 'string']],
            'invalid_values' => ['type' => 'array'],
            'schema' => ['type' => 'object'],
            'tag_name' => ['type' => 'string'],
            'tag_categories' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'On category-mismatch failures: the categories declared by the dynamic tag.',
            ],
            'expected_categories' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'On category-mismatch failures: categories the target setting can accept.',
            ],
            'tag_controls' => [
                'type' => 'array',
                'description' => 'On required-settings / unknown-key failures: the compact list of the tag\'s own controls so the caller can retry with a valid tag_settings payload.',
            ],
            'unknown_tag_settings_keys' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'On unknown-key failures: keys in tag_settings that are not controls on the tag.',
            ],
            'applied_value' => [
                'type' => ['array', 'object', 'string', 'number', 'integer', 'boolean', 'null'],
                'description' => 'On success: the exact value stored under the target setting (the v4 atomic wrapped shape, or the v3 __dynamic__ shortcode) so the caller can confirm the write without a follow-up get-content call.',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Elementor\apply_dynamic_tag',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => ['readonly' => false, 'destructive' => false, 'idempotent' => false],
    ],
]);

/**
 * Guard that ensures Elementor is loaded. Returns null when ready.
 *
 * @return array{success: false, error: string}|null
 */
function dt_runtime_guard(): ?array
{
    if (!class_exists('Elementor\\Plugin')) {
        return ['success' => false, 'error' => 'Elementor is not active.'];
    }
    return null;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function list_dynamic_tags(array $input = []): array
{
    $guard = dt_runtime_guard();
    return $guard ?? dt_list($input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function get_dynamic_tag(array $input): array
{
    $guard = dt_runtime_guard();
    return $guard ?? dt_get($input);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function apply_dynamic_tag(array $input): array|WP_Error
{
    $guard = dt_runtime_guard();
    return $guard ?? dt_apply($input);
}

/**
 * List registered dynamic tags, optionally filtered by group or category.
 *
 * @param array<string, mixed> $input
 * @return array{success: bool, tags: list<array<string, mixed>>, groups: array<string, mixed>, total: int}
 */
function dt_list(array $input = []): array
{
    $manager = \Elementor\Plugin::$instance->dynamic_tags;
    $config = $manager->get_config();

    $group_filter = is_string($input['group'] ?? null) ? $input['group'] : '';
    $category_filter = dt_collect_category_filter($input['categories'] ?? null);

    $tags = [];
    foreach ($config['tags'] as $name => $tag_config) {
        /** @var mixed $raw_tag_group */
        $raw_tag_group = $tag_config['group'] ?? '';
        $tag_groups = dt_collect_groups($raw_tag_group);
        if ($group_filter !== '' && !in_array($group_filter, $tag_groups, strict: true)) {
            continue;
        }
        $tag_group = dt_present_group($raw_tag_group, $tag_groups);

        /** @var list<string> $tag_categories */
        $tag_categories = is_array($tag_config['categories'] ?? null) ? $tag_config['categories'] : [];
        if ($category_filter !== [] && array_intersect($category_filter, $tag_categories) === []) {
            continue;
        }

        $tags[] = [
            'name' => $name,
            'title' => $tag_config['title'] ?? '',
            'group' => $tag_group,
            'categories' => $tag_categories,
            'content_type' => $tag_config['content_type'] ?? '',
            'settings_required' => $tag_config['settings_required'] ?? false,
        ];
    }

    return [
        'success' => true,
        'tags' => $tags,
        'groups' => $config['groups'],
        'total' => count($tags),
    ];
}

/**
 * Normalize Elementor's two supported group shapes to ids used for filtering.
 * An array stays an array in the returned tag row so a multi-group registration
 * remains lossless; a scalar registration keeps the historical string output.
 *
 * @return list<string>
 */
function dt_collect_groups(mixed $raw): array
{
    if (is_string($raw)) {
        return $raw === '' ? [] : [$raw];
    }
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_string($entry) || $entry === '') {
            continue;
        }
        $out[] = $entry;
    }
    return array_values(array_unique($out));
}

/**
 * Keep historical string output for scalar registrations and expose every id
 * when Elementor supplied its array registration form.
 *
 * @param list<string> $groups
 * @return string|list<string>
 */
function dt_present_group(mixed $raw, array $groups): string|array
{
    if (is_array($raw)) {
        return $groups;
    }
    return $groups[0] ?? '';
}

/**
 * Extract the categories filter from the raw input, dropping non-strings and
 * empty entries. Returns the empty list when the input is absent/malformed so
 * the caller treats it as "no category filter".
 *
 * @return list<string>
 */
function dt_collect_category_filter(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }
    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_string($entry) || $entry === '') {
            continue;
        }
        $out[] = $entry;
    }
    return $out;
}

/**
 * Get details of a specific dynamic tag.
 *
 * @param array<string, mixed> $input
 * @return array{success: bool, tag?: array<string, mixed>, error?: string}
 */
function dt_get(array $input): array
{
    $tag_name = (string) ($input['tag_name'] ?? '');
    if ($tag_name === '') {
        return ['success' => false, 'error' => 'Parameter "tag_name" is required for get.'];
    }

    $manager = \Elementor\Plugin::$instance->dynamic_tags;
    $tag_info = $manager->get_tag_info($tag_name);

    if (!$tag_info) {
        return ['success' => false, 'error' => "Dynamic tag '{$tag_name}' not found."];
    }

    $instance = $tag_info['instance'];
    $editor_config = $instance->get_editor_config();
    /** @var array<string, array<string, mixed>> $raw_controls */
    $raw_controls = $editor_config['controls'] ?? [];
    $controls = dt_extract_controls($raw_controls);

    $tag = [
        'name' => $editor_config['name'] ?? $tag_name,
        'title' => $editor_config['title'] ?? '',
        'group' => $editor_config['group'] ?? '',
        'categories' => $editor_config['categories'] ?? [],
        'content_type' => $editor_config['content_type'] ?? '',
        'settings_required' => $editor_config['settings_required'] ?? false,
        'controls' => $controls,
    ];

    $tag = dt_enrich_with_atomic($tag, $tag_name);

    return ['success' => true, 'tag' => $tag];
}

/**
 * Extract and simplify controls from a tag's editor config.
 *
 * @param array<string, array<string, mixed>> $raw_controls
 * @return list<array<string, mixed>>
 */
function dt_extract_controls(array $raw_controls): array
{
    $controls = [];

    foreach ($raw_controls as $control_name => $control) {
        if (($control['type'] ?? '') === 'section') {
            continue;
        }

        $control_info = [
            'name' => $control_name,
            'type' => $control['type'] ?? 'unknown',
            'label' => $control['label'] ?? '',
        ];

        if (array_key_exists('default', $control)) {
            $control_info['default'] = $control['default'];
        }
        if (array_key_exists('options', $control)) {
            $control_info['options'] = $control['options'];
        }
        if (array_key_exists('groups', $control)) {
            $control_info['groups'] = $control['groups'];
        }

        $controls[] = $control_info;
    }

    return $controls;
}

/**
 * Enrich tag info with v4 atomic widget data if available.
 *
 * @param array<string, mixed> $tag
 * @return array<string, mixed>
 */
function dt_enrich_with_atomic(array $tag, string $tag_name): array
{
    if (!class_exists('Elementor\Modules\AtomicWidgets\DynamicTags\Dynamic_Tags_Module')) {
        return $tag;
    }

    $atomic_module = \Elementor\Modules\AtomicWidgets\DynamicTags\Dynamic_Tags_Module::instance();
    $atomic_tag = $atomic_module->registry->get_tag($tag_name);

    if ($atomic_tag) {
        $tag['atomic'] = [
            'props_schema' => array_keys($atomic_tag['props_schema']),
            'atomic_group' => $atomic_tag['group'],
        ];
    }

    return $tag;
}

/**
 * Read one converted tag from the same atomic registry Elementor uses to
 * validate and render Dynamic_Prop_Type values. A tag that has no converted
 * atomic definition deliberately returns null so callers retain their scalar
 * fallback instead of guessing a schema.
 *
 * @return array<array-key, mixed>|null
 */
function dt_get_atomic_tag(string $tag_name): ?array
{
    if (!class_exists('Elementor\Modules\AtomicWidgets\DynamicTags\Dynamic_Tags_Module')) {
        return null;
    }

    $module = \Elementor\Modules\AtomicWidgets\DynamicTags\Dynamic_Tags_Module::instance();
    /** @var mixed $raw */
    $raw = $module->registry->get_tag($tag_name);
    return is_array($raw) ? $raw : null;
}

/**
 * Validate required parameters for the apply action.
 *
 * @param array<string, mixed> $input
 * @return string|null Error message or null if valid.
 */
function dt_validate_apply(array $input): ?string
{
    if ((string) ($input['tag_name'] ?? '') === '') {
        return 'Parameter "tag_name" is required for apply.';
    }
    if ((int) ($input['post_id'] ?? 0) <= 0) {
        return 'Parameter "post_id" is required for apply.';
    }
    if ((string) ($input['element_id'] ?? '') === '') {
        return 'Parameter "element_id" is required for apply.';
    }
    if ((string) ($input['setting_name'] ?? '') === '') {
        return 'Parameter "setting_name" is required for apply.';
    }

    return null;
}

/**
 * Apply a dynamic tag to a widget setting on a page.
 *
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function dt_apply(array $input): array|WP_Error
{
    $error = dt_validate_apply($input);
    if ($error !== null) {
        return ['success' => false, 'error' => $error];
    }

    $prepared = dt_prepare_apply_pipeline($input);
    if ($prepared instanceof WP_Error) {
        return $prepared;
    }
    if (array_key_exists('error_response', $prepared)) {
        return $prepared['error_response'];
    }

    /**
     * @var array{
     *     post_id: int,
     *     elements: list<array<string, mixed>>,
     *     ctx: array{element_id: string, setting_name: string, tag_name: string, tag_settings: array<string, mixed>, tag_instance: \Elementor\Core\DynamicTags\Base_Tag, manager: \Elementor\Core\DynamicTags\Manager, is_atomic: bool, control: array<string, mixed>, widget_type: string},
     *     is_atomic: bool
     * } $prepared
     */
    $result = dt_apply_to_elements($prepared['elements'], $prepared['ctx']);
    if ($prepared['is_atomic']) {
        $validation_error = dt_validate_atomic_apply_result($result['elements'], $prepared['ctx']);
        if ($validation_error !== null) {
            return $validation_error;
        }
    }
    $format = $prepared['is_atomic'] ? 'v4-atomic' : 'v3-dynamic';
    $commit = dt_commit_apply($prepared['post_id'], $result['elements'], $format);
    if ($commit instanceof WP_Error) {
        return $commit;
    }
    if ($commit['success'] === true && $result['applied_value'] !== null) {
        // Echo the value actually stored under the target setting so the
        // caller can verify the shape without a follow-up get-content call.
        $commit['applied_value'] = $result['applied_value'];
    }
    return $commit;
}

/**
 * Resolve the input against the tag registry and the target element, run
 * the pre-write combination checks, and bundle everything `dt_apply` needs
 * to walk the tree and write. Returns an `error_response` on any early
 * rejection so `dt_apply` stays a short top-level function.
 *
 * @param array<string, mixed> $input
 * @return array{error_response: array<string, mixed>}|array{post_id: int, elements: list<array<string, mixed>>, ctx: array<string, mixed>, is_atomic: bool}|WP_Error
 */
function dt_prepare_apply_pipeline(array $input): array|WP_Error
{
    $tag_name = (string) $input['tag_name'];
    $post_id = (int) $input['post_id'];
    $element_id = (string) $input['element_id'];
    $setting_name = (string) $input['setting_name'];
    /** @var array<string, mixed> $tag_settings */
    $tag_settings = $input['tag_settings'] ?? [];

    $manager = \Elementor\Plugin::$instance->dynamic_tags;
    $tag_info = $manager->get_tag_info($tag_name);
    if (!$tag_info) {
        return [
            'error_response' => ['success' => false, 'error' => "Dynamic tag '{$tag_name}' not found."],
        ];
    }

    [$elements, $read_error] = el_read_page_required($post_id);
    if ($elements === null) {
        return [
            'error_response' => ['success' => false, 'error' => (string) $read_error],
        ];
    }

    // Validate that the target element exists, its widget_type is known, and
    // the requested setting_name is a real control on that widget; then run
    // the combination checks (category match, required settings, unknown
    // keys) before any write.
    $target_validation = dt_resolve_and_validate_target(
        $elements,
        $tag_info['instance'],
        ['element_id' => $element_id, 'setting_name' => $setting_name, 'post_id' => $post_id],
        ['tag_name' => $tag_name, 'tag_settings' => $tag_settings],
    );
    if (array_key_exists('error_response', $target_validation)) {
        return ['error_response' => $target_validation['error_response']];
    }

    // Analyzer can't narrow the union once the error branch is returned —
    // pin the success variant so the dereferences below are indexed access
    // instead of possibly-undefined checks.
    /** @var array{is_atomic: bool, control: array<string, mixed>, widget_type: string} $target_validation */
    $prepared_settings = $target_validation['is_atomic']
        ? dt_prepare_atomic_tag_settings($tag_settings, $tag_name)
        : $tag_settings;
    if ($prepared_settings instanceof WP_Error) {
        return $prepared_settings;
    }
    $tag_settings = $prepared_settings;

    return [
        'post_id' => $post_id,
        'elements' => $elements,
        'ctx' => [
            'element_id' => $element_id,
            'setting_name' => $setting_name,
            'tag_name' => $tag_name,
            'tag_settings' => $tag_settings,
            'tag_instance' => $tag_info['instance'],
            'manager' => $manager,
            'is_atomic' => $target_validation['is_atomic'],
            'control' => $target_validation['control'],
            'widget_type' => $target_validation['widget_type'],
        ],
        'is_atomic' => $target_validation['is_atomic'],
    ];
}

/**
 * Wrap tag settings only for atomic destinations; v3 shortcode settings stay
 * byte-for-byte compatible with the existing apply path.
 *
 * @param array<string, mixed> $tag_settings
 * @return array<string, mixed>|WP_Error
 */
function dt_prepare_atomic_tag_settings(array $tag_settings, string $tag_name): array|WP_Error
{
    $atomic_tag = dt_get_atomic_tag($tag_name);
    /** @var array<string, object>|null $props_schema */
    $props_schema = is_array($atomic_tag['props_schema'] ?? null) ? $atomic_tag['props_schema'] : null;
    return dt_wrap_atomic_settings($tag_settings, $props_schema, $tag_name);
}

/**
 * Resolve the target element's validator output and run the combination
 * checks against the tag's editor config. Returns either the normalized
 * target struct (is_atomic + control) or an error response ready to hand
 * back up to `dt_apply`.
 *
 * @param list<array<string, mixed>>                                     $elements
 * @param array{element_id: string, setting_name: string, post_id: int}  $target_args
 * @param array{tag_name: string, tag_settings: array<string, mixed>}    $tag_args
 * @return array{error_response: array<string, mixed>}|array{is_atomic: bool, control: array<string, mixed>, widget_type: string}
 */
function dt_resolve_and_validate_target(
    array $elements,
    object $tag_instance,
    array $target_args,
    array $tag_args,
): array {
    $target_check = dt_validate_apply_target(
        $elements,
        $target_args['element_id'],
        $target_args['setting_name'],
        $target_args['post_id'],
    );
    if (array_key_exists('error_response', $target_check)) {
        return ['error_response' => $target_check['error_response']];
    }

    $control = is_array($target_check['control'] ?? null) ? $target_check['control'] : [];

    /** @var mixed $raw_editor_config */
    $raw_editor_config = method_exists($tag_instance, 'get_editor_config') ? $tag_instance->get_editor_config() : [];
    /** @var array<string, mixed> $editor_config */
    $editor_config = is_array($raw_editor_config) ? $raw_editor_config : [];
    $combo_error = dt_run_combination_checks(
        $editor_config,
        [
            'control' => $control,
            'setting_name' => $target_args['setting_name'],
            'widget_type' => $target_check['widget_type'] ?? '',
            'is_atomic' => ($target_check['is_atomic'] ?? false) === true,
        ],
        $tag_args['tag_name'],
        $tag_args['tag_settings'],
    );
    if ($combo_error !== null) {
        return ['error_response' => $combo_error];
    }

    return [
        'is_atomic' => ($target_check['is_atomic'] ?? false) === true,
        'control' => $control,
        'widget_type' => $target_check['widget_type'] ?? '',
    ];
}

/**
 * Persist the mutated tree via the shared writer (which takes care of
 * encoding, supporting meta keys, and CSS cache invalidation) and shape
 * the public response. The caller passes the ready `format` string
 * (v4-atomic / v3-dynamic) rather than a boolean flag.
 *
 * @param list<array<string, mixed>> $elements
 * @return array{success: bool, format?: string, error?: string}|WP_Error
 */
function dt_commit_apply(int $post_id, array $elements, string $format): array|WP_Error
{
    $save = el_write_page($post_id, $elements);
    if ($save instanceof WP_Error) {
        return $save;
    }

    return ['success' => true, 'format' => $format];
}

/**
 * Run the existing deep atomic validator against the element after the dynamic
 * value has been inserted but before the document writer is called. The normal
 * single-element failure shape keeps the widget schema inline; the tag schema
 * example makes nested Dynamic_Prop_Type failures actionable as well.
 *
 * @param list<array<string, mixed>> $elements
 * @param array<string, mixed>       $ctx
 * @return array<string, mixed>|null
 */
function dt_validate_atomic_apply_result(array $elements, array $ctx): ?array
{
    $element_id = (string) ($ctx['element_id'] ?? '');
    $widget_type = (string) ($ctx['widget_type'] ?? '');
    $element = el_find($elements, $element_id);
    if ($element === null || !is_array($element['settings'] ?? null)) {
        return null;
    }

    /** @var array<string, mixed> $settings */
    $settings = $element['settings'];
    $invalid = el_validate_atomic_props_deep($settings, el_get_raw_props_schema($widget_type));
    if ($invalid === []) {
        return null;
    }

    $response = build_single_element_error_response('widget', $widget_type, [
        'ok' => false,
        'settings' => $settings,
        'dropped' => [],
        'invalid' => $invalid,
    ]);
    $expected = dt_expected_atomic_tag_settings_shape((string) ($ctx['tag_name'] ?? ''));
    $response_error = is_string($response['error'] ?? null) ? $response['error'] : '';
    $response['error'] = 'Dynamic tag apply aborted before write. ' . $response_error;
    if ($expected !== '') {
        $response['error'] .= ' Expected atomic tag_settings shape: ' . $expected . '.';
    }
    return $response;
}

/**
 * Render the registered tag prop keys as a compact typed-envelope example for
 * validation errors. This is guidance only; Elementor's Props_Parser remains
 * the validator and the returned example is never used to accept input.
 */
function dt_expected_atomic_tag_settings_shape(string $tag_name): string
{
    $atomic_tag = dt_get_atomic_tag($tag_name);
    /** @var array<string, object> $props_schema */
    $props_schema = is_array($atomic_tag['props_schema'] ?? null) ? $atomic_tag['props_schema'] : [];
    $expected = [];
    foreach ($props_schema as $key => $prop_type) {
        $type_key = dt_atomic_prop_type_key($prop_type);
        if ($type_key === null) {
            continue;
        }
        $expected[$key] = $type_key === 'query'
            ? [
                '$$type' => 'query',
                'value' => [
                    'id' => ['$$type' => 'number', 'value' => '<id>'],
                    'label' => ['$$type' => 'string', 'value' => '<label>'],
                ],
            ]
            : ['$$type' => $type_key, 'value' => '<value>'];
    }
    if ($expected === []) {
        return '';
    }
    $encoded = wp_json_encode($expected);
    return is_string($encoded) ? $encoded : '';
}

/**
 * Validate that the target element for an apply operation exists, that its
 * widget type is registered, and that the requested setting name is a known
 * control on that widget. Returns `{ok: true, is_atomic: bool, control: ...,
 * widget_type: ...}` on success (atomicness comes from the target element's
 * own schema, so mixed pages are handled correctly; the matched control
 * descriptor is returned so callers can shape the dynamic value to match
 * the prop type — e.g. wrap it inside a Link_Prop_Type envelope — and the
 * widget type goes through so combination validators can put it in their
 * error messages) or `{error_response: ...}` on any failure, with the
 * compact content-only schema of the widget embedded inline so the caller
 * can self-correct.
 *
 * @param list<array<string, mixed>> $elements
 * @return array{ok: true, is_atomic: bool, control: array<string, mixed>, widget_type: string}|array{error_response: array<string, mixed>}
 */
function dt_validate_apply_target(array $elements, string $element_id, string $setting_name, int $post_id): array
{
    $target = el_find($elements, $element_id);
    if ($target === null) {
        return [
            'error_response' => [
                'success' => false,
                'error' => "Element '{$element_id}' not found on page {$post_id}.",
            ],
        ];
    }

    $widget_type = el_element_widget_type($target);
    if ($widget_type === '') {
        return [
            'error_response' => [
                'success' => false,
                'error' => "Target element '{$element_id}' has no resolvable widget type — cannot attach a dynamic tag to a broken element.",
            ],
        ];
    }

    $schema = el_extract_schema($widget_type);
    if ($schema === null) {
        return [
            'error_response' => [
                'success' => false,
                'error' => sprintf(
                    'Target element has widget_type "%s" which is not registered with Elementor on this site. The element may come from a plugin that was deactivated.',
                    $widget_type,
                ),
                'widget_type' => $widget_type,
            ],
        ];
    }

    $controls = el_schema_controls($schema);
    if ($controls === null || !array_key_exists($setting_name, $controls)) {
        $element_type = $widget_type === NOVAMIRA_COMPACT_SCHEMA_CONTAINER_KEY ? 'container' : 'widget';
        return [
            'error_response' => build_single_element_error_response($element_type, $widget_type, [
                'ok' => false,
                'settings' => [],
                'dropped' => [$setting_name],
                'invalid' => [],
            ]),
        ];
    }

    /** @var array<string, mixed> $control */
    $control = is_array($controls[$setting_name]) ? $controls[$setting_name] : [];

    return [
        'ok' => true,
        'is_atomic' => ($schema['is_atomic'] ?? false) === true,
        'control' => $control,
        'widget_type' => $widget_type,
    ];
}

/**
 * Run the three pre-write combination checks (3.1 / 3.2 / 3.3) in sequence
 * and return the first rejection response, or null when the combination is
 * valid. Extracted from `dt_apply` so the orchestrator stays below the
 * complexity threshold the analyzer enforces.
 *
 * The target (control + setting_name + widget_type + is_atomic) is passed as
 * one array.
 *
 * @param array<string, mixed>                                                                               $editor_config
 * @param array{control: array<string, mixed>, setting_name: string, widget_type: string, is_atomic: bool}   $target
 * @param array<string, mixed>                                                               $tag_settings
 * @return array<string, mixed>|null
 */
function dt_run_combination_checks(array $editor_config, array $target, string $tag_name, array $tag_settings): ?array
{
    $category_error = dt_check_category_match($editor_config, $target, $tag_name);
    if ($category_error !== null) {
        return $category_error;
    }

    $required_error = dt_check_required_settings($editor_config, $tag_settings, $tag_name);
    if ($required_error !== null) {
        return $required_error;
    }

    return dt_check_unknown_tag_settings_keys($editor_config, $tag_settings, $tag_name);
}

/**
 * Pull the list of categories declared by a dynamic tag, keeping only the
 * string entries. Coercing through this helper gives the analyzer a
 * guaranteed `list<string>` instead of the `mixed`-soup that comes out of
 * direct array access.
 *
 * @param array<string, mixed> $editor_config
 * @return list<string>
 */
function dt_collect_tag_categories(array $editor_config): array
{
    /** @var mixed $raw */
    $raw = $editor_config['categories'] ?? null;
    if (!is_array($raw)) {
        return [];
    }

    $out = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        if (!is_string($entry)) {
            continue;
        }
        $out[] = $entry;
    }
    return $out;
}

/**
 * Enforce check 3.1 — the target prop must accept dynamic tags at all, and
 * the tag's categories must intersect the prop's accepted categories.
 *
 * An atomic prop is judged from the union Elementor builds for it on this
 * site ({@see dt_live_dynamic_categories}), never from its type name: the
 * same prop has changed type without changing whether it takes a dynamic
 * value, and a list of accepted type names falls behind the first time it
 * does.
 *
 * @param array<string, mixed>                                                                             $editor_config
 * @param array{control: array<string, mixed>, setting_name: string, widget_type: string, is_atomic: bool} $target
 * @return array<string, mixed>|null
 */
function dt_check_category_match(array $editor_config, array $target, string $tag_name): ?array
{
    $control = $target['control'];
    $setting_name = $target['setting_name'];
    $widget_type = $target['widget_type'];
    $expected = $target['is_atomic']
        ? dt_live_dynamic_categories($widget_type, $setting_name, $control)
        : dt_expected_categories_for_control($control);
    $prop_type = (string) ($control['t'] ?? '');
    if ($expected === null) {
        return [
            'success' => false,
            'error' => sprintf(
                'Setting "%s" on widget "%s" has prop type "%s", which does not accept a dynamic tag as a value — Elementor offers no dynamic value for it on this site (it is an enum, or a structural control that stores the tag inside one of its members rather than in place). Bind the tag to a content prop (title / paragraph / text / link / color) instead, or write this setting directly with a fully-formed value.',
                $setting_name,
                $widget_type,
                $prop_type,
            ),
            'widget_type' => $widget_type,
            'setting_name' => $setting_name,
            'setting_prop_type' => $prop_type,
            'tag_name' => $tag_name,
        ];
    }

    $tag_categories = dt_collect_tag_categories($editor_config);
    if (array_intersect($expected, $tag_categories) === []) {
        return [
            'success' => false,
            'error' => sprintf(
                'Dynamic tag "%s" has categories [%s], but setting "%s" on widget "%s" (prop type "%s") only accepts sources in [%s]. Pick a tag whose categories intersect the expected list.',
                $tag_name,
                implode(', ', $tag_categories),
                $setting_name,
                $widget_type,
                $prop_type,
                implode(', ', $expected),
            ),
            'tag_name' => $tag_name,
            'tag_categories' => $tag_categories,
            'expected_categories' => $expected,
            'widget_type' => $widget_type,
            'setting_name' => $setting_name,
            'setting_prop_type' => $prop_type,
        ];
    }

    return null;
}

/**
 * Enforce check 3.2 — `settings_required: true` tags need non-empty
 * tag_settings. The tag's own compact controls are embedded so the agent
 * sees exactly which keys it needs to provide on retry.
 *
 * @param array<string, mixed> $editor_config
 * @param array<string, mixed> $tag_settings
 * @return array<string, mixed>|null
 */
function dt_check_required_settings(array $editor_config, array $tag_settings, string $tag_name): ?array
{
    $settings_required = ($editor_config['settings_required'] ?? false) === true;
    if (!$settings_required || $tag_settings !== []) {
        return null;
    }

    /** @var array<string, array<string, mixed>> $raw_controls */
    $raw_controls = is_array($editor_config['controls'] ?? null) ? $editor_config['controls'] : [];

    return [
        'success' => false,
        'error' => sprintf(
            'Dynamic tag "%s" requires tag_settings (e.g. a "key" control). None were provided, so the tag would render empty. See "tag_controls" for the control list and retry with tag_settings filled in.',
            $tag_name,
        ),
        'tag_name' => $tag_name,
        'tag_controls' => dt_extract_controls($raw_controls),
    ];
}

/**
 * Enforce check 3.3 — every key in tag_settings must be a real control name
 * on the tag. Typos are the common source of "the stored tag looks fine but
 * renders empty" — `{wrong_field_name: ...}` never feeds the `key` control.
 *
 * @param array<string, mixed> $editor_config
 * @param array<string, mixed> $tag_settings
 * @return array<string, mixed>|null
 */
function dt_check_unknown_tag_settings_keys(array $editor_config, array $tag_settings, string $tag_name): ?array
{
    if ($tag_settings === []) {
        return null;
    }

    /** @var array<string, array<string, mixed>> $raw_controls */
    $raw_controls = is_array($editor_config['controls'] ?? null) ? $editor_config['controls'] : [];
    $known = dt_tag_control_keys($raw_controls);
    $unknown = array_values(array_diff(array_keys($tag_settings), $known));
    if ($unknown === []) {
        return null;
    }

    return [
        'success' => false,
        'error' => sprintf(
            'Dynamic tag "%s" does not accept tag_settings keys: %s. Known controls: %s. See "tag_controls" for the full shape.',
            $tag_name,
            implode(', ', $unknown),
            implode(', ', $known),
        ),
        'tag_name' => $tag_name,
        'unknown_tag_settings_keys' => $unknown,
        'tag_controls' => dt_extract_controls($raw_controls),
    ];
}

/**
 * Map a compact-schema control descriptor to the list of dynamic-tag
 * categories that are semantically valid as a source. Return null for
 * control types that should not accept a dynamic tag at all (enums,
 * structural controls, unknown kinds).
 *
 * Used for v3 controls only. Atomic props are judged from Elementor's live
 * union by {@see dt_live_dynamic_categories}; the atomic type names left in
 * this map are no longer consulted for them.
 *
 * The target list is kept intentionally narrow — relaxing later is cheap,
 * but a silent accept that stores a broken prop is exactly the category
 * of bug this validator exists to close.
 *
 * A prop only belongs here when {@see el_wrap_dynamic_override_for_prop}
 * can place the tag where the runtime expects it: either the prop stores the
 * dynamic value directly, or the wrapper knows which member to nest it into
 * (`link` → `destination`). The structured image prop qualifies for neither —
 * the dynamic-capable member is its nested source, not the wrapper — so it
 * stays out and its nested source key carries the categories instead.
 *
 * @param array<string, mixed> $control
 * @return list<string>|null
 */
function dt_expected_categories_for_control(array $control): ?array
{
    $t = (string) ($control['t'] ?? '');

    return match ($t) {
        'html-v3', 'text', 'textarea' => ['text', 'post_meta', 'number', 'datetime'],
        'link', 'url' => ['url'],
        'image-src', 'media' => ['image', 'media'],
        'color' => ['color'],
        'number', 'size' => ['number'],
        default => null,
    };
}

/**
 * The dynamic-tag categories an atomic prop accepts, read from the prop type
 * Elementor builds for it on this site, or null when it accepts none.
 *
 * A prop takes a dynamic value in place when its union carries a `dynamic`
 * member; that member's categories are exactly what Elementor's own
 * Dynamic_Prop_Type validation checks a tag against. A link takes one in its
 * `destination` member, which is where
 * {@see el_wrap_dynamic_override_for_prop} nests the tag, so its categories
 * come from there. Any other shape — a bare prop, an enum, a structured
 * image whose dynamic-capable member is nested deeper — is refused, as is a
 * prop whose schema cannot be read.
 *
 * @param array<string, mixed> $control
 * @return list<string>|null
 */
function dt_live_dynamic_categories(string $widget_type, string $setting_name, array $control): ?array
{
    $prop_type = el_get_raw_props_schema($widget_type)[$setting_name] ?? null;
    if (!is_object($prop_type)) {
        return null;
    }

    $in_place = dt_union_dynamic_categories($prop_type);
    if ($in_place !== null || ($control['t'] ?? '') !== 'link') {
        return $in_place;
    }

    $link = dt_union_member($prop_type, key: 'link');
    if ($link === null || !method_exists($link, 'get_shape')) {
        return null;
    }
    /** @var mixed $shape */
    $shape = $link->get_shape();
    /** @var mixed $destination */
    $destination = is_array($shape) ? $shape['destination'] ?? null : null;

    return is_object($destination) ? dt_union_dynamic_categories($destination) : null;
}

/**
 * The categories of a union prop type's `dynamic` member, or null when the
 * prop is not a union, has no such member, or the member lists none.
 *
 * @return list<string>|null
 */
function dt_union_dynamic_categories(object $prop_type): ?array
{
    $dynamic = dt_union_member($prop_type, key: 'dynamic');
    if ($dynamic === null || !method_exists($dynamic, 'get_categories')) {
        return null;
    }

    /** @var mixed $raw */
    $raw = $dynamic->get_categories();
    $categories = [];
    /** @var mixed $category */
    foreach (is_array($raw) ? $raw : [] as $category) {
        if (!is_string($category) || $category === '') {
            continue;
        }
        $categories[] = $category;
    }

    return $categories === [] ? null : $categories;
}

/**
 * The member of a union prop type registered under $key. A bare (non-union)
 * prop type is returned as its own member when its key matches, since with
 * Elementor's Components module off the same prop arrives unwrapped.
 */
function dt_union_member(object $prop_type, string $key): ?object
{
    if (method_exists($prop_type, 'get_prop_types')) {
        /** @var mixed $members */
        $members = $prop_type->get_prop_types();
        /** @var mixed $member */
        $member = is_array($members) ? $members[$key] ?? null : null;
        return is_object($member) ? $member : null;
    }

    if (method_exists($prop_type, 'get_key') && $prop_type::get_key() === $key) {
        return $prop_type;
    }

    return null;
}

/**
 * Extract the flat list of control names from a tag's raw editor-config
 * controls, skipping `section` pseudo-controls which don't accept values.
 *
 * @param array<string, array<string, mixed>> $raw_controls
 * @return list<string>
 */
function dt_tag_control_keys(array $raw_controls): array
{
    $keys = [];
    foreach (array_keys($raw_controls) as $name) {
        if (($raw_controls[$name]['type'] ?? '') === 'section') {
            continue;
        }
        $keys[] = $name;
    }
    return $keys;
}

/**
 * Recursively walk elements to find and apply a dynamic tag to a specific
 * element's setting. Returns the updated tree plus the post-update value
 * actually stored under `setting_name` — `dt_apply` echoes it back to the
 * caller so agents can confirm the exact shape landed (e.g. the wrapped
 * link envelope on a `link` prop, or the bare dynamic value on html-v3)
 * rather than inferring it from a bare `success:true`.
 *
 * @param list<array<string, mixed>> $elements
 * @param array{element_id: string, setting_name: string, tag_name: string, tag_settings: array<string, mixed>, tag_instance: \Elementor\Core\DynamicTags\Base_Tag, manager: \Elementor\Core\DynamicTags\Manager, is_atomic: bool, control: array<string, mixed>, widget_type?: string} $ctx
 * @return array{elements: list<array<string, mixed>>, found: bool, applied_value: mixed}
 */
function dt_apply_to_elements(array $elements, array $ctx): array
{
    foreach ($elements as &$element) {
        if (($element['id'] ?? '') === $ctx['element_id']) {
            /** @var array<string, mixed> $settings */
            $settings = $element['settings'] ?? [];
            $settings = dt_set_dynamic_value($settings, $ctx);
            $element['settings'] = $settings;
            /** @var mixed $applied_value */
            $applied_value = $settings[$ctx['setting_name']] ?? null;

            return ['elements' => $elements, 'found' => true, 'applied_value' => $applied_value];
        }

        // Recurse into children.
        if (($element['elements'] ?? null) !== null && is_array($element['elements'])) {
            /** @var list<array<string, mixed>> $children */
            $children = $element['elements'];
            $result = dt_apply_to_elements($children, $ctx);
            $element['elements'] = $result['elements'];
            if ($result['found']) {
                return ['elements' => $elements, 'found' => true, 'applied_value' => $result['applied_value']];
            }
        }
    }

    return ['elements' => $elements, 'found' => false, 'applied_value' => null];
}

/**
 * Set the dynamic tag value on an element's settings (v3 or v4 format).
 *
 * @param array<string, mixed> $settings
 * @param array{element_id: string, setting_name: string, tag_name: string, tag_settings: array<string, mixed>, tag_instance: \Elementor\Core\DynamicTags\Base_Tag, manager: \Elementor\Core\DynamicTags\Manager, is_atomic: bool, control: array<string, mixed>, widget_type?: string} $ctx
 * @return array<string, mixed>
 */
function dt_set_dynamic_value(array $settings, array $ctx): array
{
    $setting_name = $ctx['setting_name'];

    if ($ctx['is_atomic']) {
        $dynamic_value = [
            '$$type' => 'dynamic',
            'value' => [
                'name' => $ctx['tag_name'],
                'group' => el_resolve_dynamic_tag_group($ctx['tag_name']),
                'settings' => $ctx['tag_settings'],
            ],
        ];

        // Composite props (link, …) reject a bare {$$type: "dynamic"} at the
        // top level — the dynamic tag must be wedged inside the prop's own
        // envelope. Same wrap the tree-mode coerce path applies, reused so
        // both write paths converge on the shape Props_Parser accepts.
        $settings[$setting_name] = el_wrap_dynamic_override_for_prop($dynamic_value, $ctx['control']);

        return $settings;
    }

    // v3 format: uses __dynamic__ key with tag shortcode.
    $tag_id = bin2hex(random_bytes(4));
    $manager = $ctx['manager'];
    $shortcode = $manager->tag_data_to_tag_text($tag_id, $ctx['tag_name'], $ctx['tag_settings']);

    if ($shortcode === '') {
        return $settings;
    }

    /** @var array<string, mixed> $dynamic */
    $dynamic = is_array($settings['__dynamic__']) ? $settings['__dynamic__'] : [];
    $dynamic[$setting_name] = $shortcode;
    $settings['__dynamic__'] = $dynamic;

    return $settings;
}

/**
 * Wrap tag settings values in $$type format for v4 atomic widgets. Registered
 * prop types decide the outer key; controls missing from the schema retain the
 * historical scalar fallback. Query props also gain the nested id/label shape
 * Elementor's Query_Prop_Type validates.
 *
 * @param array<string, mixed>       $settings
 * @param array<string, object>|null $props_schema
 * @return array<string, mixed>|WP_Error
 */
function dt_wrap_atomic_settings(array $settings, ?array $props_schema, string $tag_name): array|WP_Error
{
    $wrapped = [];

    foreach (array_keys($settings) as $key) {
        /** @var mixed $value */
        $value = $settings[$key];
        $prop_type = $props_schema[$key] ?? null;
        $type_key = dt_atomic_prop_type_key($prop_type);
        if ($type_key === null) {
            $wrapped[$key] = dt_wrap_atomic_setting_fallback($value);
            continue;
        }
        if (is_array($value) && ($value['$$type'] ?? null) !== null) {
            $wrapped[$key] = $value;
            continue;
        }
        if ($type_key === 'query') {
            $query = dt_wrap_query_setting($key, $value, $tag_name);
            if ($query instanceof WP_Error) {
                return $query;
            }
            $wrapped[$key] = $query;
            continue;
        }
        $wrapped[$key] = ['$$type' => $type_key, 'value' => $value];
    }

    return $wrapped;
}

/** Preserve the scalar-derived wrapping used before atomic tag schemas existed. */
function dt_wrap_atomic_setting_fallback(mixed $value): mixed
{
    return match (true) {
        is_array($value) && ($value['$$type'] ?? null) !== null => $value,
        is_string($value) => ['$$type' => 'string', 'value' => $value],
        is_int($value) || is_float($value) => ['$$type' => 'number', 'value' => $value],
        is_bool($value) => ['$$type' => 'boolean', 'value' => $value],
        default => $value,
    };
}

/** Return the persisted key declared by an Elementor prop type, when known. */
function dt_atomic_prop_type_key(mixed $prop_type): ?string
{
    if (!is_object($prop_type) || !method_exists($prop_type, 'get_key')) {
        return null;
    }
    /** @var mixed $key */
    $key = $prop_type::get_key();
    return is_string($key) && $key !== '' ? $key : null;
}

/**
 * Build Query_Prop_Type's nested typed shape from an id scalar or {id,label}.
 *
 * @return array<string, mixed>|WP_Error
 */
function dt_wrap_query_setting(string $setting_name, mixed $value, string $tag_name): array|WP_Error
{
    $label = null;
    $id = dt_query_id($value);
    if (is_array($value)) {
        $unknown = array_diff(array_keys($value), ['id', 'label']);
        if ($unknown !== [] || !array_key_exists('id', $value)) {
            return dt_query_input_error($tag_name, $setting_name, $value);
        }
        /** @var mixed $raw_label */
        $raw_label = $value['label'] ?? null;
        if ($raw_label !== null) {
            if (!is_string($raw_label)) {
                return dt_query_input_error($tag_name, $setting_name, $value);
            }
            $label = $raw_label;
        }
        $id = dt_query_id($value['id']);
    }

    if ($id === null) {
        return dt_query_input_error($tag_name, $setting_name, $value);
    }

    $resolved = dt_resolve_query_label($setting_name, $id, $tag_name);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    if ($label === null) {
        $label = $resolved;
    }

    $query_value = ['id' => ['$$type' => 'number', 'value' => $id]];
    if ($label !== null) {
        $query_value['label'] = ['$$type' => 'string', 'value' => $label];
    }
    return ['$$type' => 'query', 'value' => $query_value];
}

/** Positive integer ids accepted by atomic query controls. */
function dt_query_id(mixed $value): ?int
{
    if (is_int($value)) {
        return $value > 0 ? $value : null;
    }
    if (!is_string($value) || $value === '' || !ctype_digit($value)) {
        return null;
    }
    $id = (int) $value;
    return $id > 0 ? $id : null;
}

/**
 * Resolve the label and existence check for Elementor's known query controls.
 * Unknown query-control names keep Query_Prop_Type's optional label omitted.
 */
function dt_resolve_query_label(string $setting_name, int $id, string $tag_name): string|WP_Error|null
{
    if ($setting_name === 'post_id') {
        /** @var \WP_Post|null $post */
        $post = get_post($id);
        return $post instanceof \WP_Post
            ? get_the_title($post)
            : dt_query_not_found_error($tag_name, $setting_name, $id, object_type: 'post');
    }
    if ($setting_name === 'taxonomy_id') {
        $term = get_term($id);
        return $term instanceof \WP_Term
            ? $term->name
            : dt_query_not_found_error($tag_name, $setting_name, $id, object_type: 'term');
    }
    if ($setting_name === 'attachment_id') {
        /** @var \WP_Post|null $attachment */
        $attachment = get_post($id);
        return $attachment instanceof \WP_Post && $attachment->post_type === 'attachment'
            ? get_the_title($attachment)
            : dt_query_not_found_error($tag_name, $setting_name, $id, object_type: 'attachment');
    }
    if ($setting_name === 'author_id') {
        $author = get_userdata($id);
        return $author instanceof \WP_User
            ? $author->display_name
            : dt_query_not_found_error($tag_name, $setting_name, $id, object_type: 'author');
    }
    return null;
}

/** A malformed query setting with the expected ergonomic and stored shapes. */
function dt_query_input_error(string $tag_name, string $setting_name, mixed $value): WP_Error
{
    $code = 'elementor_dynamic_tag_invalid_query';
    return new WP_Error(
        $code,
        sprintf(
            '%s: Dynamic tag "%s" setting "%s" must be a positive integer id, a numeric id string, or an object with id and optional label. Expected stored shape: {"$$type":"query","value":{"id":{"$$type":"number","value":123},"label":{"$$type":"string","value":"Title"}}}.',
            $code,
            $tag_name,
            $setting_name,
        ),
        ['status' => 400, 'tag_name' => $tag_name, 'setting_name' => $setting_name, 'value' => $value],
    );
}

/** A query id that cannot resolve to the entity implied by its control key. */
function dt_query_not_found_error(string $tag_name, string $setting_name, int $id, string $object_type): WP_Error
{
    $code = 'elementor_dynamic_tag_query_not_found';
    return new WP_Error(
        $code,
        sprintf(
            '%s: Dynamic tag "%s" setting "%s" references %s id %d, which does not exist.',
            $code,
            $tag_name,
            $setting_name,
            $object_type,
            $id,
        ),
        [
            'status' => 400,
            'tag_name' => $tag_name,
            'setting_name' => $setting_name,
            'object_type' => $object_type,
            'object_id' => $id,
        ],
    );
}
