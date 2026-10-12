<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Oxygen;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/oxygen-apply-dynamic-data', [
    'label' => __('Apply Oxygen Dynamic Data', domain: 'novamira-pro'),
    'description' => __(
        'Binds a dynamic-data field to an element control by writing the `[breakdance_dynamic field="<slug>"]` shortcode into the control VALUE at a dotted `path` (e.g. `content.content.text`). This is how Oxygen resolves live data at render time — the shortcode goes IN the property value, never in a sibling `*_dynamic_meta` key (which the renderer ignores). Validation runs server-side: the field slug must be a real registered dynamic-data source (discover via oxygen-list-dynamic-data-fields) and the element must exist, otherwise the call fails HARD without touching the canvas. The value at `path` is replaced by the shortcode while every sibling key on the element is preserved — so you can target one deep leaf without rebuilding the whole tab. For a repeating LOOP over a repeater field use oxygen-set-loop instead; this ability binds a single scalar control. EXECUTABLE payloads are gated: the `phpreturn` field (whose `code` attribute the engine eval()s) and the `process_value` attribute (piped through eval()) are rejected unless you pass `allow_code_elements: true` after the user explicitly confirms. The gate reads the FINAL merged shortcode attributes, so a `params=\'{…}\'` JSON blob (which the engine merges OVER the plain attributes) cannot hide either of them.',
        domain: 'novamira-pro',
    ),
    'category' => 'oxygen',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The post whose canvas holds the target element.'],
            'element_id' => ['type' => 'integer', 'description' => 'The id of the element to bind.'],
            'path' => [
                'type' => 'string',
                'description' => 'Dotted control path under content/design/settings, e.g. `content.content.text`. Discover paths with oxygen-get-element-schema.',
            ],
            'field' => [
                'type' => 'string',
                'description' => 'The dynamic-data field slug, e.g. `post_title`, from oxygen-list-dynamic-data-fields.',
            ],
            'attributes' => [
                'type' => 'object',
                'description' => 'Optional extra shortcode attributes (scalar values), e.g. a date format. Written as `key="value"` alongside `field`.',
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Confirmation gate for EXECUTABLE dynamic-data payloads: the `phpreturn` field (its `code` attribute is eval()\'d) and the `process_value` attribute (piped through eval()). Set true ONLY after the user has explicitly confirmed they want executable code — never set it on your own.',
            ],
        ],
        'required' => ['post_id', 'element_id', 'path', 'field'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'success' => ['type' => 'boolean'],
            'element_id' => ['type' => 'integer'],
            'path' => ['type' => 'string'],
            'field' => ['type' => 'string'],
            'value' => [
                'type' => 'string',
                'description' => 'The shortcode written at `path`, so the caller can confirm the binding.',
            ],
            'error' => ['type' => 'string'],
        ],
        'required' => ['success'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Oxygen\\oxygen_apply_dynamic_data',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The shortcode goes in the property VALUE (not a *_dynamic_meta sibling). Validate the slug with oxygen-list-dynamic-data-fields first. The `phpreturn` field and the `process_value` attribute are eval()\'d by the engine and are GATED behind allow_code_elements + explicit user approval — prefer a registered field that covers the value. For loops use oxygen-set-loop.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function oxygen_apply_dynamic_data(array $input): array|WP_Error
{
    $post_id = ox_coerce_int($input['post_id'] ?? 0);
    $element_id = ox_coerce_int($input['element_id'] ?? 0);
    if ($post_id <= 0 || $element_id <= 0) {
        return ox_error('oxygen_invalid_input', __(
            'post_id and element_id must both be positive integers.',
            domain: 'novamira-pro',
        ));
    }
    $segments = ox_dd_path_segments($input['path'] ?? null);
    if ($segments instanceof WP_Error) {
        return $segments;
    }
    $binding = ox_dd_resolve_binding($input);
    if ($binding instanceof WP_Error) {
        return $binding;
    }
    $post = ox_post_or_error($post_id);
    if ($post instanceof WP_Error) {
        return $post;
    }
    $field = $binding['field'];
    $shortcode = $binding['shortcode'];
    /** @var array<string, mixed>|WP_Error $result */
    return ox_with_tree_lock($post_id, static fn(): array|WP_Error => ox_apply_dynamic_data_locked(
        $post_id,
        $element_id,
        $segments,
        $field,
        $shortcode,
    ));
}

/**
 * Resolve the requested binding into the exact shortcode string that will be
 * persisted: validate the field slug against the live registry, build the
 * shortcode, then run the executable-payload gate on the BUILT string.
 *
 * `phpreturn` is a REGISTERED field, so slug validation alone lets it through —
 * and its handler is `eval($attributes['code'])` (engine php-function.php:68).
 * `process_value` is likewise piped through `eval('$value = $v; ' . $code)`
 * (pipe.php:37). Both are decided on the FINAL merged attribute map of the built
 * shortcode (see ox_executable_attribute_shapes), so neither a cased alias nor a
 * `params='{…}'` JSON blob that overrides `field` can dodge the scan.
 *
 * Extracted from the callback so it stays within the cyclomatic-complexity
 * budget, and so the gate provably runs before the tree lock and any write.
 *
 * @param array<string, mixed> $input
 * @return array{field: string, shortcode: string}|WP_Error
 */
function ox_dd_resolve_binding(array $input): array|WP_Error
{
    $field = isset($input['field']) && is_string($input['field']) ? trim($input['field']) : '';
    if ($field === '') {
        return ox_error('oxygen_invalid_input', __(
            '`field` is required and must be a non-empty string.',
            domain: 'novamira-pro',
        ));
    }
    $field_error = ox_dd_validate_field($field);
    if ($field_error instanceof WP_Error) {
        return $field_error;
    }
    $shortcode = ox_dd_build_shortcode($field, $input['attributes'] ?? null, ox_dd_field_attribute_keys($field));
    if ($shortcode instanceof WP_Error) {
        return $shortcode;
    }
    $payloads = ox_collect_executable_payloads($shortcode, path: 'shortcode');
    if ($payloads !== [] && !ox_coerce_bool($input['allow_code_elements'] ?? false)) {
        return ox_code_payload_block($payloads);
    }
    return ['field' => $field, 'shortcode' => $shortcode];
}

/**
 * Validate and split the dotted `path`. The first segment must be one of
 * Oxygen's real control tabs (content/design/settings); every segment must
 * be non-empty. Returns the segment list or an error.
 *
 * @return list<string>|WP_Error
 */
function ox_dd_path_segments(mixed $path): array|WP_Error
{
    if (!is_string($path) || trim($path) === '') {
        return ox_error('oxygen_invalid_input', __(
            '`path` is required and must be a non-empty dotted string.',
            domain: 'novamira-pro',
        ));
    }
    $segments = explode('.', trim($path));
    foreach ($segments as $segment) {
        if ($segment === '') {
            return ox_error('oxygen_invalid_input', __(
                '`path` must not contain empty segments (no leading/trailing/double dots).',
                domain: 'novamira-pro',
            ));
        }
    }
    if (!in_array($segments[0], ['content', 'design', 'settings'], strict: true)) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: %s: the offending first path segment */
            __(
                '`path` must start with `content`, `design`, or `settings` (got `%s`). Use oxygen-get-element-schema to find the exact path.',
                domain: 'novamira-pro',
            ),
            $segments[0],
        ));
    }
    return $segments;
}

/**
 * Confirm the field slug is a registered dynamic-data source. Uses the
 * engine's own resolver so validation matches what the renderer will accept.
 * When the controller is unavailable we skip validation rather than block a
 * legitimate binding on an environment quirk.
 */
function ox_dd_validate_field(string $field): ?WP_Error
{
    if (!class_exists('Breakdance\\DynamicData\\DynamicDataController')) {
        return null;
    }
    $controller = \Breakdance\DynamicData\DynamicDataController::getInstance();
    if (!method_exists($controller, 'getField')) {
        return null;
    }
    /** @var mixed $resolved */
    $resolved = $controller->getField($field);
    if ($resolved === false || $resolved === null) {
        return ox_error('oxygen_not_found', sprintf(
            /* translators: %s: field slug */
            __(
                'Dynamic-data field `%s` is not registered. Discover valid slugs with oxygen-list-dynamic-data-fields.',
                domain: 'novamira-pro',
            ),
            $field,
        ));
    }
    return null;
}

/**
 * The canonical attribute-key set for a field: its `defaultAttributes()` keys
 * unioned with the slugs of its `getControls()` (the control slug IS the
 * shortcode attribute name). Returns null when the controller/field cannot be
 * resolved, so attribute validation is skipped rather than blocking a binding
 * on an environment quirk. The union matters because a control slug like
 * `format_custom` is a valid attribute even though it is absent from
 * `defaultAttributes()`.
 *
 * @return list<string>|null
 */
function ox_dd_field_attribute_keys(string $field): ?array
{
    if (!class_exists('Breakdance\\DynamicData\\DynamicDataController')) {
        return null;
    }
    $controller = \Breakdance\DynamicData\DynamicDataController::getInstance();
    if (!method_exists($controller, 'getField')) {
        return null;
    }
    /** @var mixed $resolved */
    $resolved = $controller->getField($field);
    if (!is_object($resolved)) {
        return null;
    }
    $keys = [];
    if (method_exists($resolved, 'defaultAttributes')) {
        /** @var mixed $defaults */
        $defaults = $resolved->defaultAttributes();
        if (is_array($defaults)) {
            foreach (array_keys($defaults) as $key) {
                $keys[] = (string) $key;
            }
        }
    }
    if (method_exists($resolved, 'getControls')) {
        /** @var mixed $controls */
        $controls = $resolved->getControls();
        ox_dd_collect_control_slugs(is_array($controls) ? $controls : [], $keys);
    }
    return array_values(array_unique($keys));
}

/**
 * Recursively collect only LEAF control slugs from a control tree into `$keys`.
 * A control's `slug` is a real shortcode attribute ONLY when the node is a leaf
 * control, not a section/group container. The native `getControls()` marks a
 * container with `options.type === 'section'` (e.g. the `advanced` accordion) —
 * its slug (`advanced`) is NOT an attribute, so binding `advanced="…"` is
 * silently ignored by the field handler. We skip section slugs but still recurse
 * into every node's `children`, because real attribute controls (beforeContent /
 * afterContent / truncate / fallback) live inside those sections. `children` is
 * NOT a discriminator (both leaves and sections carry it); the section flag is.
 *
 * @param array<array-key, mixed> $controls
 * @param list<string>            $keys (by ref)
 */
function ox_dd_collect_control_slugs(array $controls, array &$keys): void
{
    /** @var mixed $control */
    foreach ($controls as $control) {
        if (!is_array($control)) {
            continue;
        }
        $is_section =
            isset($control['options'])
            && is_array($control['options'])
            && ($control['options']['type'] ?? null) === 'section';
        if (!$is_section && isset($control['slug']) && is_string($control['slug'])) {
            $keys[] = $control['slug'];
        }
        if (isset($control['children']) && is_array($control['children'])) {
            ox_dd_collect_control_slugs($control['children'], $keys);
        }
    }
}

/**
 * Build the `[breakdance_dynamic field="<slug>" ...]` shortcode. Extra
 * attributes are validated, not silently rewritten: each key must be one of the
 * field's canonical attribute slugs (`$allowed_keys`; skipped when null), and a
 * value carrying a character that would break the shortcode envelope (`"`, `[`,
 * `]`) is rejected rather than stripped, so the caller learns their input was
 * wrong instead of the field handler silently ignoring a mangled attribute.
 *
 * @param list<string>|null $allowed_keys
 * @return string|WP_Error
 */
function ox_dd_build_shortcode(string $field, mixed $attributes, ?array $allowed_keys): string|WP_Error
{
    if (!is_array($attributes)) {
        return sprintf('[breakdance_dynamic field="%s"]', $field);
    }
    if (array_is_list($attributes) && $attributes !== []) {
        return ox_error('oxygen_invalid_input', __(
            '`attributes` must be a JSON object (key/value map), not a list.',
            domain: 'novamira-pro',
        ));
    }
    $attr_string = '';
    /** @var mixed $value */
    foreach ($attributes as $key => $value) {
        if (!is_string($key) || $key === '' || $key === 'field') {
            continue;
        }
        if ($allowed_keys !== null && !in_array($key, $allowed_keys, strict: true)) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: 1: attribute key, 2: field slug, 3: allowed keys */
                __(
                    'Attribute `%1$s` is not a valid attribute of field `%2$s`. Valid attributes: %3$s. Inspect them with oxygen-get-dynamic-data-field.',
                    domain: 'novamira-pro',
                ),
                $key,
                $field,
                $allowed_keys === [] ? '(none)' : implode(', ', $allowed_keys),
            ));
        }
        if (!is_scalar($value)) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: %s: attribute key */
                __('Attribute `%s` must be a scalar value.', domain: 'novamira-pro'),
                $key,
            ));
        }
        $value_string = (string) $value;
        // Reject shortcode-envelope breakers (" [ ]) AND markup characters
        // (< > `). Some canonical attributes (beforeContent / afterContent) are
        // concatenated RAW by the renderer, bypassing its wp_kses on the field
        // value, so an unescaped `<script>` here is stored XSS. No legitimate
        // attribute value (date formats, text prefixes) needs these characters.
        if (preg_match('/["\[\]<>`]/', $value_string) === 1) {
            return ox_error('oxygen_invalid_input', sprintf(
                /* translators: %s: attribute key */
                __(
                    'Attribute `%s` value contains a character (a quote, square bracket, angle bracket, or backtick) that is not allowed — it could break the shortcode or inject markup. Remove it.',
                    domain: 'novamira-pro',
                ),
                $key,
            ));
        }
        $attr_string .= sprintf(' %s="%s"', $key, $value_string);
    }
    return sprintf('[breakdance_dynamic field="%s"%s]', $field, $attr_string);
}

/**
 * Write the shortcode at the requested path under the post's tree lock.
 *
 * @param list<string> $segments
 * @return array<string, mixed>|WP_Error
 */
function ox_apply_dynamic_data_locked(
    int $post_id,
    int $element_id,
    array $segments,
    string $field,
    string $shortcode,
): array|WP_Error {
    $tree = ox_read_tree($post_id);
    if ($tree instanceof WP_Error) {
        return $tree;
    }
    if ($element_id === 1) {
        return ox_error('oxygen_invalid_input', __(
            'The root element cannot carry a dynamic-data binding.',
            domain: 'novamira-pro',
        ));
    }
    $hit = ox_find_node($tree, $element_id);
    if ($hit === null) {
        return ox_error('oxygen_not_found', sprintf(
            __('Element %1$d not found in the canvas of post %2$d.', domain: 'novamira-pro'),
            $element_id,
            $post_id,
        ));
    }
    /** @var array<string, mixed> $data */
    $data = isset($hit['node']['data']) && is_array($hit['node']['data']) ? $hit['node']['data'] : [];
    $type = isset($data['type']) && is_string($data['type']) ? $data['type'] : '';
    $path = implode('.', $segments);
    /** @var array<string, mixed> $properties */
    $properties = isset($data['properties']) && is_array($data['properties']) ? $data['properties'] : [];
    $conflict = ox_property_path_conflict($properties, $segments);
    if ($conflict !== null) {
        return ox_error('oxygen_invalid_input', sprintf(
            /* translators: 1: full path, 2: the conflicting prefix */
            __(
                'Cannot write `%1$s`: the path descends INTO an existing non-object value at `%2$s`. That would destroy the value there. Bind at `%2$s` directly, or pick a deeper leaf under a different branch (oxygen-get-element-schema shows the real paths).',
                domain: 'novamira-pro',
            ),
            implode('.', $segments),
            $conflict,
        ));
    }
    if (!ox_element_property_path_is_valid($type, $path)) {
        return ox_error('oxygen_invalid_input', sprintf(
            __(
                '`path` `%1$s` is not a live control leaf for `%2$s`. Use oxygen-get-element-schema.',
                domain: 'novamira-pro',
            ),
            $path,
            $type,
        ));
    }
    $properties = ox_set_property_at_path($properties, $segments, $shortcode);

    $write = ox_persist_element_properties($tree, $hit, $post_id, $element_id, $properties);
    if ($write instanceof WP_Error) {
        return $write;
    }
    return [
        'success' => true,
        'element_id' => $element_id,
        'path' => $path,
        'field' => $field,
        'value' => $shortcode,
    ];
}
