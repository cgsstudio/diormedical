<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace NovamiraVisual;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * REST endpoints used by the in-editor builder tools.
 */
class Api
{
    public function __construct()
    {
        \add_action('rest_api_init', [$this, 'register_routes']);
    }

    public function register_routes(): void
    {
        \register_rest_route('novamira-visual/v1', route: '/validate-styles', args: [
            'methods' => 'POST',
            'callback' => [$this, 'validate_styles'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        \register_rest_route('novamira-visual/v1', route: '/validate-settings', args: [
            'methods' => 'POST',
            'callback' => [$this, 'validate_settings'],
            'permission_callback' => [$this, 'check_permission'],
        ]);

        \register_rest_route('novamira-visual/v1', route: '/dynamic-tags/list', args: [
            'methods' => 'POST',
            'callback' => __NAMESPACE__ . '\list_dynamic_tags',
            'permission_callback' => [$this, 'check_permission'],
        ]);

        \register_rest_route('novamira-visual/v1', route: '/dynamic-tags/get', args: [
            'methods' => 'POST',
            'callback' => __NAMESPACE__ . '\get_dynamic_tag',
            'permission_callback' => [$this, 'check_permission'],
        ]);

        \register_rest_route('novamira-visual/v1', route: '/dynamic-tags/format', args: [
            'methods' => 'POST',
            'callback' => __NAMESPACE__ . '\format_dynamic_tag',
            'permission_callback' => [$this, 'check_permission'],
        ]);

        \register_rest_route('novamira-visual/v1', route: '/v3-global-styles', args: [
            'methods' => 'POST',
            'callback' => __NAMESPACE__ . '\manage_v3_global_styles',
            'permission_callback' => [$this, 'check_permission'],
        ]);
    }

    public function check_permission(): bool
    {
        return \current_user_can('edit_posts');
    }

    public function validate_settings(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        $widget_type = (string) ($params['widget_type'] ?? '');
        /** @var array<array-key, mixed> $settings */
        $settings = is_array($params['settings'] ?? []) ? $params['settings'] : [];

        if (!class_exists('\Elementor\Plugin')) {
            return new \WP_REST_Response(['valid' => true, 'skipped' => true], 200);
        }

        // Try widgets manager first, then elements manager (for containers like e-flexbox)
        $instance = \Elementor\Plugin::$instance->widgets_manager->get_widget_types($widget_type);
        if (!$instance) {
            $instance = \Elementor\Plugin::$instance->elements_manager->get_element_types($widget_type);
        }

        if (!$instance || !is_object($instance) || !method_exists($instance, 'get_props_schema')) {
            return new \WP_REST_Response(['valid' => true, 'skipped' => true], 200);
        }

        $class = get_class($instance);
        /** @var array<array-key, mixed> $schema */
        $schema = $class::get_props_schema();
        $parser = \Elementor\Modules\AtomicWidgets\Parsers\Props_Parser::make($schema);
        $result = $parser->parse($settings);

        if ($result->is_valid()) {
            return new \WP_REST_Response(['valid' => true, 'errors' => (object) []], 200);
        }

        return new \WP_REST_Response([
            'valid' => false,
            'errors' => $result->errors()->to_string(),
        ], 200);
    }

    public function validate_styles(\WP_REST_Request $request): \WP_REST_Response
    {
        $params = $request->get_json_params();
        /** @var array<string, array<array-key, mixed>> $styles */
        $styles = is_array($params['styles'] ?? []) ? $params['styles'] : [];

        if (!class_exists('\Elementor\Modules\AtomicWidgets\Styles\Style_Schema')) {
            return new \WP_REST_Response(['valid' => true, 'skipped' => true], 200);
        }

        /** @var array<array-key, mixed> $schema */
        $schema = \Elementor\Modules\AtomicWidgets\Styles\Style_Schema::get();
        $parser = \Elementor\Modules\AtomicWidgets\Parsers\Style_Parser::make($schema);
        $errors = [];

        foreach ($styles as $style_id => $style_data) {
            $result = $parser->parse($style_data);

            if (!$result->is_valid()) {
                $errors[$style_id] = $result->errors()->to_string();
            }
        }

        return new \WP_REST_Response([
            'valid' => $errors === [],
            'errors' => (object) $errors,
        ], 200);
    }
}

function list_dynamic_tags(\WP_REST_Request $request): \WP_REST_Response
{
    $params = request_json_params($request);
    $manager = dynamic_tags_manager();
    if ($manager === null) {
        return new \WP_REST_Response(['success' => false, 'error' => 'Elementor dynamic tags are not available.'], 200);
    }

    /** @var array<string, mixed> $config */
    $config = $manager->get_config();
    $group_filter = is_string($params['group'] ?? null) ? $params['group'] : '';
    $category_filter = string_list($params['categories'] ?? null);
    $tags = [];

    /** @var array<string, array<string, mixed>> $raw_tags */
    $raw_tags = is_array($config['tags'] ?? null) ? $config['tags'] : [];
    foreach ($raw_tags as $name => $tag_config) {
        if (!dynamic_tag_matches_filters($tag_config, $group_filter, $category_filter)) {
            continue;
        }

        $group = (string) ($tag_config['group'] ?? '');
        $categories = string_list($tag_config['categories'] ?? null);
        $tags[] = [
            'name' => $name,
            'title' => $tag_config['title'] ?? '',
            'group' => $group,
            'categories' => $categories,
            'content_type' => $tag_config['content_type'] ?? '',
            'settings_required' => ($tag_config['settings_required'] ?? false) === true,
        ];
    }

    return new \WP_REST_Response([
        'success' => true,
        'tags' => $tags,
        'groups' => is_array($config['groups'] ?? null) ? $config['groups'] : [],
        'total' => count($tags),
    ], 200);
}

/**
 * @param array<string, mixed> $tag_config
 * @param list<string> $category_filter
 */
function dynamic_tag_matches_filters(array $tag_config, string $group_filter, array $category_filter): bool
{
    $group = (string) ($tag_config['group'] ?? '');
    if ($group_filter !== '' && $group !== $group_filter) {
        return false;
    }

    return (
        $category_filter === []
        || array_intersect($category_filter, string_list($tag_config['categories'] ?? null)) !== []
    );
}

function get_dynamic_tag(\WP_REST_Request $request): \WP_REST_Response
{
    $params = request_json_params($request);
    $tag_name = is_string($params['tag_name'] ?? null) ? $params['tag_name'] : '';
    $tag = dynamic_tag_details($tag_name);

    return new \WP_REST_Response(
        $tag ?? [
            'success' => false,
            'error' => $tag_name === '' ? 'Parameter "tag_name" is required.' : "Dynamic tag '{$tag_name}' not found.",
        ],
        200,
    );
}

function format_dynamic_tag(\WP_REST_Request $request): \WP_REST_Response
{
    $params = request_json_params($request);
    $tag_name = is_string($params['tag_name'] ?? null) ? $params['tag_name'] : '';
    $format = is_string($params['format'] ?? null) ? $params['format'] : '';
    /** @var array<string, mixed> $tag_settings */
    $tag_settings = is_array($params['tag_settings'] ?? null) ? $params['tag_settings'] : [];
    $manager = dynamic_tags_manager();
    if ($manager === null) {
        return new \WP_REST_Response(['success' => false, 'error' => 'Elementor dynamic tags are not available.'], 200);
    }

    /** @var array{instance: \Elementor\Core\DynamicTags\Base_Tag}|false|null $tag_info */
    $tag_info = $manager->get_tag_info($tag_name);
    if (!$tag_info) {
        return new \WP_REST_Response(['success' => false, 'error' => "Dynamic tag '{$tag_name}' not found."], 200);
    }

    $validation = validate_dynamic_tag_settings($tag_info['instance'], $tag_name, $tag_settings);
    if ($validation !== null) {
        return new \WP_REST_Response($validation, 200);
    }

    if ($format === 'v3') {
        $tag_id = bin2hex(random_bytes(4));
        $shortcode = $manager->tag_data_to_tag_text($tag_id, $tag_name, $tag_settings);

        return new \WP_REST_Response([
            'success' => $shortcode !== '',
            'format' => 'v3',
            'value' => $shortcode,
            'error' => $shortcode === '' ? 'Elementor returned an empty dynamic tag shortcode.' : null,
        ], 200);
    }

    if ($format === 'v4') {
        return new \WP_REST_Response([
            'success' => true,
            'format' => 'v4',
            'value' => [
                '$$type' => 'dynamic',
                'value' => [
                    'name' => $tag_name,
                    'group' => method_exists($tag_info['instance'], 'get_group')
                        ? $tag_info['instance']->get_group()
                        : '',
                    'settings' => wrap_atomic_dynamic_tag_settings($tag_settings),
                ],
            ],
        ], 200);
    }

    return new \WP_REST_Response(['success' => false, 'error' => 'Parameter "format" must be "v3" or "v4".'], 200);
}

function manage_v3_global_styles(\WP_REST_Request $request): \WP_REST_Response
{
    $params = request_json_params($request);
    $action = is_string($params['action'] ?? null) ? $params['action'] : 'list';

    if (!class_exists('\Elementor\Plugin')) {
        return new \WP_REST_Response(['success' => false, 'error' => 'Elementor is not active.'], 200);
    }

    $kit = \Elementor\Plugin::$instance->kits_manager->get_active_kit();

    $result = match ($action) {
        'list' => list_v3_global_styles($kit),
        'create_color' => create_v3_color($kit, $params),
        'edit_color' => edit_v3_color($kit, $params),
        'delete_color' => delete_v3_color($kit, $params),
        'create_typography' => create_v3_typography($kit, $params),
        'edit_typography' => edit_v3_typography($kit, $params),
        'delete_typography' => delete_v3_typography($kit, $params),
        default => ['success' => false, 'error' => "Unknown v3 global style action '{$action}'."],
    };

    return new \WP_REST_Response($result, 200);
}

/**
 * @return array<string, mixed>
 */
function request_json_params(\WP_REST_Request $request): array
{
    /** @var mixed $raw */
    $raw = $request->get_json_params();
    if (!is_array($raw)) {
        return [];
    }

    $params = [];
    /** @var mixed $value */
    foreach ($raw as $key => $value) {
        if (!is_string($key)) {
            continue;
        }
        $params[$key] = $value;
    }

    return $params;
}

function dynamic_tags_manager(): ?\Elementor\Core\DynamicTags\Manager
{
    if (!class_exists('\Elementor\Plugin')) {
        return null;
    }

    $manager = \Elementor\Plugin::$instance->dynamic_tags ?? null;
    return is_object($manager) && method_exists($manager, 'get_config') && method_exists($manager, 'get_tag_info')
        ? $manager
        : null;
}

/**
 * @return array<string, mixed>|null
 */
function dynamic_tag_details(string $tag_name): ?array
{
    if ($tag_name === '') {
        return null;
    }

    $manager = dynamic_tags_manager();
    if ($manager === null) {
        return null;
    }

    /** @var array{instance: \Elementor\Core\DynamicTags\Base_Tag}|false|null $tag_info */
    $tag_info = $manager->get_tag_info($tag_name);
    if (!$tag_info) {
        return null;
    }

    /** @var mixed $raw_config */
    $raw_config = method_exists($tag_info['instance'], 'get_editor_config')
        ? $tag_info['instance']->get_editor_config()
        : [];
    /** @var array<string, mixed> $config */
    $config = is_array($raw_config) ? $raw_config : [];

    return [
        'success' => true,
        'tag' => [
            'name' => $config['name'] ?? $tag_name,
            'title' => $config['title'] ?? '',
            'group' => $config['group'] ?? '',
            'categories' => string_list($config['categories'] ?? null),
            'content_type' => $config['content_type'] ?? '',
            'settings_required' => ($config['settings_required'] ?? false) === true,
            'controls' => extract_dynamic_tag_controls($config['controls'] ?? null),
        ],
    ];
}

/**
 * @param array<string, mixed> $tag_settings
 * @return array<string, mixed>|null
 */
function validate_dynamic_tag_settings(
    \Elementor\Core\DynamicTags\Base_Tag $tag,
    string $tag_name,
    array $tag_settings,
): ?array {
    /** @var mixed $raw_config */
    $raw_config = method_exists($tag, 'get_editor_config') ? $tag->get_editor_config() : [];
    /** @var array<string, mixed> $config */
    $config = is_array($raw_config) ? $raw_config : [];
    $controls = extract_dynamic_tag_controls($config['controls'] ?? null);

    if (($config['settings_required'] ?? false) === true && $tag_settings === []) {
        return [
            'success' => false,
            'error' => "Dynamic tag '{$tag_name}' requires tag_settings.",
            'tag_controls' => $controls,
        ];
    }

    $known = array_map(static fn(array $control): string => (string) $control['name'], $controls);
    $unknown = array_values(array_diff(array_keys($tag_settings), $known));
    if ($unknown !== []) {
        return [
            'success' => false,
            'error' => 'Unknown tag_settings keys: ' . implode(', ', $unknown) . '.',
            'unknown_tag_settings_keys' => $unknown,
            'tag_controls' => $controls,
        ];
    }

    return null;
}

/**
 * @return list<array<string, mixed>>
 */
function extract_dynamic_tag_controls(mixed $raw_controls): array
{
    if (!is_array($raw_controls)) {
        return [];
    }

    $controls = [];
    /** @var mixed $control */
    foreach ($raw_controls as $name => $control) {
        if (!is_string($name) || !is_array($control) || ($control['type'] ?? '') === 'section') {
            continue;
        }

        $info = [
            'name' => $name,
            'type' => $control['type'] ?? 'unknown',
            'label' => $control['label'] ?? '',
        ];
        foreach (['default', 'options', 'groups'] as $key) {
            if (!array_key_exists($key, $control)) {
                continue;
            }
            $info[$key] = $control[$key];
        }
        $controls[] = $info;
    }

    return $controls;
}

/**
 * @return list<string>
 */
function string_list(mixed $raw): array
{
    if (!is_array($raw)) {
        return [];
    }

    $result = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($raw as $value) {
        if (!is_string($value) || $value === '') {
            continue;
        }
        $result[] = $value;
    }

    return $result;
}

/**
 * @param array<string, mixed> $settings
 * @return array<string, mixed>
 */
function wrap_atomic_dynamic_tag_settings(array $settings): array
{
    $wrapped = [];
    // @mago-expect analysis:mixed-assignment
    foreach ($settings as $key => $value) {
        $wrapped[$key] = match (true) {
            is_array($value) && array_key_exists('$$type', $value) => $value,
            is_string($value) => ['$$type' => 'string', 'value' => $value],
            is_int($value) || is_float($value) => ['$$type' => 'number', 'value' => $value],
            is_bool($value) => ['$$type' => 'boolean', 'value' => $value],
            default => $value,
        };
    }

    return $wrapped;
}

/**
 * @return array<string, mixed>
 */
function list_v3_global_styles(\Elementor\Core\Kits\Documents\Kit $kit): array
{
    return [
        'success' => true,
        'system_colors' => array_map(__NAMESPACE__ . '\format_v3_color', kit_setting_list(
            $kit,
            setting_key: 'system_colors',
        )),
        'custom_colors' => array_map(__NAMESPACE__ . '\format_v3_color', kit_setting_list(
            $kit,
            setting_key: 'custom_colors',
        )),
        'system_typography' => array_map(__NAMESPACE__ . '\format_v3_typography', kit_setting_list(
            $kit,
            setting_key: 'system_typography',
        )),
        'custom_typography' => array_map(__NAMESPACE__ . '\format_v3_typography', kit_setting_list(
            $kit,
            setting_key: 'custom_typography',
        )),
    ];
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function create_v3_color(\Elementor\Core\Kits\Documents\Kit $kit, array $params): array
{
    $title = is_string($params['title'] ?? null) ? $params['title'] : '';
    $color = is_string($params['color'] ?? null) ? $params['color'] : '';
    if ($title === '' || $color === '') {
        return ['success' => false, 'error' => 'Parameters "title" and "color" are required.'];
    }

    $colors = kit_setting_list($kit, setting_key: 'custom_colors');
    $entry = ['_id' => bin2hex(random_bytes(4)), 'title' => $title, 'color' => $color];
    $colors[] = $entry;
    persist_kit_setting($kit, setting_key: 'custom_colors', value: $colors);

    return ['success' => true, 'color' => format_v3_color($entry)];
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function edit_v3_color(\Elementor\Core\Kits\Documents\Kit $kit, array $params): array
{
    $color_id = is_string($params['color_id'] ?? null) ? $params['color_id'] : '';
    if ($color_id === '') {
        return ['success' => false, 'error' => 'Parameter "color_id" is required.'];
    }

    foreach (['system_colors', 'custom_colors'] as $setting_key) {
        $colors = kit_setting_list($kit, $setting_key);
        foreach ($colors as $index => $color) {
            if (($color['_id'] ?? '') !== $color_id) {
                continue;
            }
            if (is_string($params['title'] ?? null)) {
                $colors[$index]['title'] = $params['title'];
            }
            if (is_string($params['color'] ?? null)) {
                $colors[$index]['color'] = $params['color'];
            }
            persist_kit_setting($kit, $setting_key, $colors);
            return ['success' => true, 'color' => format_v3_color($colors[$index])];
        }
    }

    return ['success' => false, 'error' => "Color '{$color_id}' not found."];
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function delete_v3_color(\Elementor\Core\Kits\Documents\Kit $kit, array $params): array
{
    $color_id = is_string($params['color_id'] ?? null) ? $params['color_id'] : '';
    if ($color_id === '') {
        return ['success' => false, 'error' => 'Parameter "color_id" is required.'];
    }

    $colors = kit_setting_list($kit, setting_key: 'custom_colors');
    $filtered = array_values(array_filter($colors, static fn(array $item): bool => ($item['_id'] ?? '') !== $color_id));
    if (count($filtered) === count($colors)) {
        return ['success' => false, 'error' => "Custom color '{$color_id}' not found."];
    }
    persist_kit_setting($kit, setting_key: 'custom_colors', value: $filtered);

    return ['success' => true];
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function create_v3_typography(\Elementor\Core\Kits\Documents\Kit $kit, array $params): array
{
    $title = is_string($params['title'] ?? null) ? $params['title'] : '';
    $font_family = is_string($params['font_family'] ?? null) ? $params['font_family'] : '';
    if ($title === '' || $font_family === '') {
        return ['success' => false, 'error' => 'Parameters "title" and "font_family" are required.'];
    }

    $entries = kit_setting_list($kit, setting_key: 'custom_typography');
    $entry = [
        '_id' => bin2hex(random_bytes(4)),
        'title' => $title,
        'typography_typography' => 'custom',
        'typography_font_family' => $font_family,
    ];
    apply_typography_params($entry, $params);
    $entries[] = $entry;
    persist_kit_setting($kit, setting_key: 'custom_typography', value: $entries);

    return ['success' => true, 'typography' => format_v3_typography($entry)];
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function edit_v3_typography(\Elementor\Core\Kits\Documents\Kit $kit, array $params): array
{
    $typography_id = is_string($params['typography_id'] ?? null) ? $params['typography_id'] : '';
    if ($typography_id === '') {
        return ['success' => false, 'error' => 'Parameter "typography_id" is required.'];
    }

    foreach (['system_typography', 'custom_typography'] as $setting_key) {
        $entries = kit_setting_list($kit, $setting_key);
        foreach ($entries as $index => $entry) {
            if (($entry['_id'] ?? '') !== $typography_id) {
                continue;
            }
            if (is_string($params['title'] ?? null)) {
                $entries[$index]['title'] = $params['title'];
            }
            apply_typography_params($entries[$index], $params);
            persist_kit_setting($kit, $setting_key, $entries);
            return ['success' => true, 'typography' => format_v3_typography($entries[$index])];
        }
    }

    return ['success' => false, 'error' => "Typography '{$typography_id}' not found."];
}

/**
 * @param array<string, mixed> $params
 * @return array<string, mixed>
 */
function delete_v3_typography(\Elementor\Core\Kits\Documents\Kit $kit, array $params): array
{
    $typography_id = is_string($params['typography_id'] ?? null) ? $params['typography_id'] : '';
    if ($typography_id === '') {
        return ['success' => false, 'error' => 'Parameter "typography_id" is required.'];
    }

    $entries = kit_setting_list($kit, setting_key: 'custom_typography');
    $filtered = array_values(array_filter(
        $entries,
        static fn(array $item): bool => ($item['_id'] ?? '') !== $typography_id,
    ));
    if (count($filtered) === count($entries)) {
        return ['success' => false, 'error' => "Custom typography '{$typography_id}' not found."];
    }
    persist_kit_setting($kit, setting_key: 'custom_typography', value: $filtered);

    return ['success' => true];
}

/**
 * @return list<array<string, mixed>>
 */
function kit_setting_list(\Elementor\Core\Kits\Documents\Kit $kit, string $setting_key): array
{
    /** @var mixed $value */
    $value = method_exists($kit, 'get_settings') ? $kit->get_settings($setting_key) : [];
    if (!is_array($value)) {
        return [];
    }

    $items = [];
    /** @var mixed $item */
    foreach ($value as $item) {
        if (!is_array($item)) {
            continue;
        }
        /** @var array<string, mixed> $typed_item */
        $typed_item = $item;
        $items[] = $typed_item;
    }

    return $items;
}

/**
 * @param list<array<string, mixed>> $value
 */
function persist_kit_setting(\Elementor\Core\Kits\Documents\Kit $kit, string $setting_key, array $value): void
{
    $kit_id = (int) $kit->get_id();
    /** @var mixed $raw_settings */
    $raw_settings = \get_post_meta($kit_id, \Elementor\Core\Base\Document::PAGE_META_KEY, single: true);
    /** @var array<string, mixed> $settings */
    $settings = is_array($raw_settings) ? $raw_settings : [];
    $settings[$setting_key] = $value;

    $page_settings_manager = \Elementor\Core\Settings\Manager::get_settings_managers('page');
    $has_page_settings_manager =
        is_object($page_settings_manager) && method_exists($page_settings_manager, 'save_settings');
    if ($has_page_settings_manager) {
        $page_settings_manager->save_settings($settings, $kit_id);
    }
    if (!$has_page_settings_manager && $settings !== []) {
        \update_metadata('post', $kit_id, \Elementor\Core\Base\Document::PAGE_META_KEY, \wp_slash($settings));
    }
    if (!$has_page_settings_manager && $settings === []) {
        \delete_metadata('post', $kit_id, \Elementor\Core\Base\Document::PAGE_META_KEY);
    }

    $kit->set_settings($setting_key, $value);
    \Elementor\Plugin::$instance->files_manager->clear_cache();
}

/**
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function format_v3_color(array $entry): array
{
    return [
        'id' => (string) ($entry['_id'] ?? ''),
        'title' => (string) ($entry['title'] ?? ''),
        'color' => (string) ($entry['color'] ?? ''),
    ];
}

/**
 * @param array<string, mixed> $entry
 * @return array<string, mixed>
 */
function format_v3_typography(array $entry): array
{
    $result = [
        'id' => (string) ($entry['_id'] ?? ''),
        'title' => (string) ($entry['title'] ?? ''),
    ];
    foreach ([
        'font_family',
        'font_size',
        'font_weight',
        'text_transform',
        'font_style',
        'text_decoration',
        'line_height',
        'letter_spacing',
        'word_spacing',
    ] as $field) {
        $key = 'typography_' . $field;
        if (($entry[$key] ?? null) !== null && $entry[$key] !== '') {
            $result[$field] = $entry[$key];
        }
    }

    return $result;
}

/**
 * @param array<string, mixed> $entry
 * @param array<string, mixed> $params
 */
function apply_typography_params(array &$entry, array $params): void
{
    foreach ([
        'font_family',
        'font_size',
        'font_weight',
        'text_transform',
        'font_style',
        'text_decoration',
        'line_height',
        'letter_spacing',
    ] as $field) {
        if (!array_key_exists($field, $params)) {
            continue;
        }
        $entry['typography_' . $field] = $params[$field];
    }
}
