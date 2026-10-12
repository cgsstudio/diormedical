<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-get-field-type-schema', [
    'label' => __('Get Meta Box Field Type Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the option matrix for a single Meta Box field type. Call this before creating or editing a field to understand which settings are supported. Pass field_type as one of: text, textarea, wysiwyg, image, file, single_image, file_upload, image_upload, date, color, taxonomy, post, user, group, switch, checkbox, checkbox_list, radio, select, select_advanced, number, range, slider, password, email, url, hidden, autocomplete, time, datetime, week, month, oembed, button, key_value, custom_html, divider, heading, map, osm, sidebar, fieldset_text. Returns name, description, and an options object describing each supported key with its type, description, and default.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'field_type' => [
                'type' => 'string',
                'description' => 'The Meta Box field type slug.',
                'enum' => [
                    'text',
                    'textarea',
                    'wysiwyg',
                    'image',
                    'file',
                    'single_image',
                    'file_upload',
                    'image_upload',
                    'date',
                    'color',
                    'taxonomy',
                    'post',
                    'user',
                    'group',
                    'switch',
                    'checkbox',
                    'checkbox_list',
                    'radio',
                    'select',
                    'select_advanced',
                    'number',
                    'range',
                    'slider',
                    'password',
                    'email',
                    'url',
                    'hidden',
                    'autocomplete',
                    'time',
                    'datetime',
                    'week',
                    'month',
                    'oembed',
                    'button',
                    'key_value',
                    'custom_html',
                    'divider',
                    'heading',
                    'map',
                    'osm',
                    'sidebar',
                    'fieldset_text',
                ],
            ],
        ],
        'required' => ['field_type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'field_type' => ['type' => 'string'],
            'label' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'common_options' => [
                'type' => 'object',
                'description' => 'Options shared by all field types.',
            ],
            'type_options' => [
                'type' => 'object',
                'description' => 'Options specific to this field type.',
            ],
        ],
        'required' => ['field_type', 'label', 'description', 'common_options', 'type_options'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_get_field_type_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call before creating or editing a field to understand which options are available for the chosen field type. common_options are shared by all types (id, name, label, desc, required, std, clone, etc.). type_options are specific to the requested type. Use the returned info to build a valid field definition for metabox-create-field-group or metabox-edit-field-group.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{field_type: string} $args
 * @return array<string, mixed>|WP_Error
 */
function metabox_get_field_type_schema(array $args): array|WP_Error
{
    $guard = mb_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $field_type = $args['field_type'];

    $common_options = mb_common_field_options();
    $type_info = mb_field_type_info($field_type);

    if ($type_info === null) {
        return new WP_Error('metabox_unknown_field_type', sprintf(
            /* translators: %s: field type. */
            __('Unknown field type "%s".', domain: 'novamira-pro'),
            $field_type,
        ));
    }

    return [
        'field_type' => $field_type,
        'label' => $type_info['label'],
        'description' => $type_info['description'],
        'common_options' => $common_options,
        'type_options' => $type_info['options'],
    ];
}

/**
 * Options shared by all Meta Box field types.
 *
 * @return array<string, mixed>
 */
function mb_common_field_options(): array
{
    return [
        'id' => [
            'type' => 'string',
            'description' => 'Field ID (unique within the group). Used as the meta key.',
            'required' => true,
        ],
        'type' => ['type' => 'string', 'description' => 'Field type slug.', 'required' => true],
        'name' => ['type' => 'string', 'description' => 'Field label shown in the admin UI.'],
        'desc' => ['type' => 'string', 'description' => 'Help text shown below the field.'],
        'std' => ['type' => 'mixed', 'description' => 'Default value.'],
        'required' => [
            'type' => 'boolean',
            'description' => 'Whether the field is required. Default false.',
            'default' => false,
        ],
        'disabled' => [
            'type' => 'boolean',
            'description' => 'Whether the field is read-only in the admin. Default false.',
            'default' => false,
        ],
        'readonly' => [
            'type' => 'boolean',
            'description' => 'HTML readonly attribute. Default false.',
            'default' => false,
        ],
        'placeholder' => ['type' => 'string', 'description' => 'Placeholder text for input fields.'],
        'class' => ['type' => 'string', 'description' => 'Extra CSS class for the field wrapper.'],
        'clone' => [
            'type' => 'boolean',
            'description' => 'Whether the field is clonable (repeatable). Default false.',
            'default' => false,
        ],
        'max_clone' => [
            'type' => 'integer',
            'description' => 'Maximum number of clones allowed (requires clone=true).',
        ],
        'min_clone' => [
            'type' => 'integer',
            'description' => 'Minimum number of clones required (requires clone=true).',
        ],
        'clone_default' => ['type' => 'boolean', 'description' => 'Whether to clone the default value. Default false.'],
        'clone_as_multiple' => [
            'type' => 'boolean',
            'description' => 'Store clones as separate meta entries instead of a serialized array. Default false.',
        ],
        'sort_clone' => [
            'type' => 'boolean',
            'description' => 'Whether to allow dragging clones to reorder. Default false.',
        ],
        'save_field' => [
            'type' => 'boolean',
            'description' => 'Whether to save this field. Set false to display-only. Default true.',
        ],
        'columns' => [
            'type' => 'integer',
            'description' => 'Bootstrap column width (1-12). Default 12.',
            'default' => 12,
        ],
        'before' => ['type' => 'string', 'description' => 'Custom HTML before the field.'],
        'after' => ['type' => 'string', 'description' => 'Custom HTML after the field.'],
        'prefix' => ['type' => 'string', 'description' => 'Text or HTML to prepend inside the input wrapper.'],
        'suffix' => ['type' => 'string', 'description' => 'Text or HTML to append inside the input wrapper.'],
    ];
}

/**
 * Return type-specific label, description, and option matrix.
 *
 * @return array{label: string, description: string, options: array<string, mixed>}|null
 */
function mb_field_type_info(string $type): ?array
{
    $map = mb_field_type_map();
    return $map[$type] ?? null;
}

/**
 * Static map of Meta Box field type → label + description + type-specific options.
 *
 * @return array<string, array{label: string, description: string, options: array<string, mixed>}>
 */
function mb_field_type_map(): array
{
    return array_merge(
        mb_field_type_map_text_input(),
        mb_field_type_map_datetime(),
        mb_field_type_map_choice(),
        mb_field_type_map_media(),
        mb_field_type_map_relational(),
        mb_field_type_map_structural(),
        mb_field_type_map_geo(),
    );
}

/**
 * Basic text and numeric input field type definitions.
 *
 * @return array<string, array{label: string, description: string, options: array<string, mixed>}>
 */
function mb_field_type_map_text_input(): array
{
    $num_opts = [
        'min' => ['type' => 'number', 'description' => 'Minimum allowed value.'],
        'max' => ['type' => 'number', 'description' => 'Maximum allowed value.'],
        'step' => ['type' => 'number', 'description' => 'Increment step size. Default 1.'],
    ];

    return [
        'text' => [
            'label' => 'Text',
            'description' => 'Single-line text input.',
            'options' => [
                'size' => ['type' => 'integer', 'description' => 'HTML size attribute.', 'default' => 30],
                'datalist' => ['type' => 'array', 'description' => 'Datalist suggestions shown as the user types.'],
                'pattern' => ['type' => 'string', 'description' => 'HTML5 pattern validation regex.'],
            ],
        ],
        'textarea' => [
            'label' => 'Textarea',
            'description' => 'Multi-line text area.',
            'options' => [
                'rows' => ['type' => 'integer', 'description' => 'Number of visible rows. Default 4.', 'default' => 4],
                'cols' => [
                    'type' => 'integer',
                    'description' => 'Number of visible columns. Default 60.',
                    'default' => 60,
                ],
            ],
        ],
        'wysiwyg' => [
            'label' => 'WYSIWYG (Visual Editor)',
            'description' => 'TinyMCE visual editor — stores HTML.',
            'options' => [
                'options' => ['type' => 'object', 'description' => 'TinyMCE init options passed to wp_editor().'],
                'raw' => ['type' => 'boolean', 'description' => 'Return raw HTML without wpautop. Default false.'],
            ],
        ],
        'number' => ['label' => 'Number', 'description' => 'Numeric input.', 'options' => $num_opts],
        'range' => ['label' => 'Range', 'description' => 'Range slider input.', 'options' => $num_opts],
        'slider' => [
            'label' => 'Slider (jQuery UI)',
            'description' => 'jQuery UI slider.',
            'options' => array_merge($num_opts, [
                'js_options' => ['type' => 'object', 'description' => 'jQuery UI slider options.'],
            ]),
        ],
        'email' => ['label' => 'Email', 'description' => 'Email input with format validation.', 'options' => []],
        'url' => ['label' => 'URL', 'description' => 'URL input with format validation.', 'options' => []],
        'password' => [
            'label' => 'Password',
            'description' => 'Password input (value is stored in plain text).',
            'options' => [],
        ],
        'hidden' => ['label' => 'Hidden', 'description' => 'Hidden input field.', 'options' => []],
        'color' => [
            'label' => 'Color Picker',
            'description' => 'Iris color picker — stores a hex color string.',
            'options' => [
                'alpha_channel' => ['type' => 'boolean', 'description' => 'Allow transparency (RGBA). Default false.'],
                'js_options' => ['type' => 'object', 'description' => 'Iris color picker options.'],
            ],
        ],
    ];
}

/**
 * Date and time field type definitions.
 *
 * @return array<string, array{label: string, description: string, options: array<string, mixed>}>
 */
function mb_field_type_map_datetime(): array
{
    return [
        'date' => [
            'label' => 'Date Picker',
            'description' => 'Date picker storing the date as a string.',
            'options' => [
                'js_options' => [
                    'type' => 'object',
                    'description' => 'jQuery UI datepicker options (dateFormat, etc.).',
                ],
                'save_format' => [
                    'type' => 'string',
                    'description' => 'PHP date() format used when saving (e.g. "Y-m-d"). Default "Y-m-d".',
                    'default' => 'Y-m-d',
                ],
                'timestamp' => [
                    'type' => 'boolean',
                    'description' => 'Save as Unix timestamp instead of formatted string. Default false.',
                ],
            ],
        ],
        'time' => [
            'label' => 'Time Picker',
            'description' => 'Time picker.',
            'options' => ['js_options' => [
                'type' => 'object',
                'description' => 'Timepicker options (timeFormat, etc.).',
            ]],
        ],
        'datetime' => [
            'label' => 'Datetime Picker',
            'description' => 'Combined date + time picker.',
            'options' => [
                'js_options' => ['type' => 'object', 'description' => 'Datetimepicker options.'],
                'save_format' => [
                    'type' => 'string',
                    'description' => 'PHP date() format. Default "Y-m-d H:i:s".',
                    'default' => 'Y-m-d H:i:s',
                ],
                'timestamp' => ['type' => 'boolean', 'description' => 'Save as Unix timestamp. Default false.'],
            ],
        ],
        'week' => ['label' => 'Week Picker', 'description' => 'HTML5 week input.', 'options' => []],
        'month' => ['label' => 'Month Picker', 'description' => 'HTML5 month input.', 'options' => []],
    ];
}

/**
 * Choice and toggle field type definitions.
 *
 * @return array<string, array{label: string, description: string, options: array<string, mixed>}>
 */
function mb_field_type_map_choice(): array
{
    $choice_opts = [
        'options' => [
            'type' => 'array|object',
            'description' => 'List of choices as ["value" => "Label"] map or indexed array of {label, value} objects.',
            'required' => true,
        ],
        'multiple' => [
            'type' => 'boolean',
            'description' => 'Allow selecting multiple values (select/checkbox_list). Default false.',
        ],
        'inline' => [
            'type' => 'boolean',
            'description' => 'Display choices inline (radio/checkbox_list). Default false.',
        ],
    ];

    return [
        'checkbox' => [
            'label' => 'Checkbox',
            'description' => 'Single checkbox — stores 1 or 0.',
            'options' => ['desc' => ['type' => 'string', 'description' => 'Label text shown next to the checkbox.']],
        ],
        'checkbox_list' => [
            'label' => 'Checkbox List',
            'description' => 'Group of checkboxes for multiple choices.',
            'options' => $choice_opts,
        ],
        'radio' => [
            'label' => 'Radio Buttons',
            'description' => 'Radio button group for single choice.',
            'options' => $choice_opts,
        ],
        'select' => ['label' => 'Select', 'description' => 'Native HTML select dropdown.', 'options' => $choice_opts],
        'select_advanced' => [
            'label' => 'Select Advanced',
            'description' => 'Enhanced select powered by select2.',
            'options' => array_merge($choice_opts, [
                'js_options' => ['type' => 'object', 'description' => 'select2 options.'],
            ]),
        ],
        'switch' => [
            'label' => 'Switch',
            'description' => 'Toggle switch — stores 1 or 0.',
            'options' => [
                'on_label' => ['type' => 'string', 'description' => 'Label for the "on" state. Default "On".'],
                'off_label' => ['type' => 'string', 'description' => 'Label for the "off" state. Default "Off".'],
                'style' => [
                    'type' => 'string',
                    'description' => 'Style variant: "rounded" or "square". Default "rounded".',
                ],
            ],
        ],
        'autocomplete' => [
            'label' => 'Autocomplete',
            'description' => 'Text input with autocomplete suggestions from a predefined list or AJAX source.',
            'options' => [
                'options' => [
                    'type' => 'array|object',
                    'description' => 'Suggestion list — same format as select options.',
                ],
            ],
        ],
    ];
}

/**
 * Media upload field type definitions.
 *
 * @return array<string, array{label: string, description: string, options: array<string, mixed>}>
 */
function mb_field_type_map_media(): array
{
    $file_opts = [
        'mime_type' => [
            'type' => 'string',
            'description' => 'Comma-separated MIME types to restrict the upload dialog.',
        ],
        'max_file_uploads' => ['type' => 'integer', 'description' => 'Maximum number of files allowed.'],
        'force_delete' => [
            'type' => 'boolean',
            'description' => 'Whether to permanently delete the attachment when the field value is removed. Default false.',
        ],
    ];

    return [
        'image' => [
            'label' => 'Image',
            'description' => 'Image upload field (stores attachment IDs).',
            'options' => $file_opts,
        ],
        'single_image' => [
            'label' => 'Single Image',
            'description' => 'Image upload field limited to one image.',
            'options' => $file_opts,
        ],
        'file' => [
            'label' => 'File',
            'description' => 'File upload field (stores attachment IDs).',
            'options' => $file_opts,
        ],
        'file_upload' => [
            'label' => 'File Upload (HTML5)',
            'description' => 'Direct file upload using HTML5 drag-and-drop.',
            'options' => $file_opts,
        ],
        'image_upload' => [
            'label' => 'Image Upload (HTML5)',
            'description' => 'Direct image upload using HTML5 drag-and-drop.',
            'options' => $file_opts,
        ],
    ];
}

/**
 * Relational (post/taxonomy/user) field type definitions.
 *
 * @return array<string, array{label: string, description: string, options: array<string, mixed>}>
 */
function mb_field_type_map_relational(): array
{
    return [
        'post' => [
            'label' => 'Post',
            'description' => 'Select one or more posts by title — stores post IDs.',
            'options' => [
                'post_type' => [
                    'type' => 'string|array',
                    'description' => 'Post type(s) to query. Default "post".',
                    'default' => 'post',
                ],
                'query_args' => ['type' => 'object', 'description' => 'WP_Query arguments to filter posts.'],
                'multiple' => ['type' => 'boolean', 'description' => 'Allow selecting multiple posts. Default false.'],
                'field_type' => [
                    'type' => 'string',
                    'description' => 'UI widget: "select", "select_advanced", "checkbox_list", "radio". Default "select".',
                ],
            ],
        ],
        'taxonomy' => [
            'label' => 'Taxonomy',
            'description' => 'Select one or more taxonomy terms — stores term IDs.',
            'options' => [
                'taxonomy' => [
                    'type' => 'string|array',
                    'description' => 'Taxonomy slug(s) to query. Default "category".',
                    'default' => 'category',
                ],
                'multiple' => ['type' => 'boolean', 'description' => 'Allow selecting multiple terms. Default false.'],
                'field_type' => [
                    'type' => 'string',
                    'description' => 'UI widget: "select", "select_advanced", "checkbox_list", "radio". Default "select".',
                ],
                'query_args' => ['type' => 'object', 'description' => 'get_terms() arguments.'],
            ],
        ],
        'user' => [
            'label' => 'User',
            'description' => 'Select one or more WordPress users — stores user IDs.',
            'options' => [
                'multiple' => ['type' => 'boolean', 'description' => 'Allow selecting multiple users. Default false.'],
                'field_type' => ['type' => 'string', 'description' => 'UI widget. Default "select".'],
                'query_args' => ['type' => 'object', 'description' => 'WP_User_Query arguments.'],
            ],
        ],
    ];
}

/**
 * Structural, group and utility field type definitions.
 *
 * @return array<string, array{label: string, description: string, options: array<string, mixed>}>
 */
function mb_field_type_map_structural(): array
{
    return [
        'group' => [
            'label' => 'Group',
            'description' => 'Nested group of sub-fields stored as a serialized array. Requires MB Group extension.',
            'options' => [
                'fields' => ['type' => 'array', 'description' => 'Array of sub-field definitions.', 'required' => true],
                'collapsible' => ['type' => 'boolean', 'description' => 'Allow collapsing the group. Default false.'],
                'default_state' => [
                    'type' => 'string',
                    'description' => '"expanded" or "collapsed". Default "expanded".',
                ],
                'group_title' => ['type' => 'string', 'description' => 'Template string for the group toggle label.'],
                'save_state' => ['type' => 'boolean', 'description' => 'Remember collapsed state. Default false.'],
            ],
        ],
        'button' => [
            'label' => 'Button',
            'description' => 'Action button (display only — does not store a value).',
            'options' => [
                'std' => ['type' => 'string', 'description' => 'Button label text.'],
                'attributes' => ['type' => 'object', 'description' => 'Extra HTML attributes for the button element.'],
            ],
        ],
        'key_value' => [
            'label' => 'Key-Value',
            'description' => 'Repeatable key-value pairs stored as a serialized array.',
            'options' => [
                'placeholder' => [
                    'type' => 'array',
                    'description' => 'Array of two strings: [key_placeholder, value_placeholder].',
                ],
            ],
        ],
        'custom_html' => [
            'label' => 'Custom HTML',
            'description' => 'Static HTML output in the meta box (not stored).',
            'options' => [
                'std' => ['type' => 'string', 'description' => 'HTML content to display.'],
                'callback' => ['type' => 'string', 'description' => 'PHP callback function name that returns HTML.'],
            ],
        ],
        'divider' => ['label' => 'Divider', 'description' => 'Visual separator line (not stored).', 'options' => []],
        'heading' => [
            'label' => 'Heading',
            'description' => 'Section heading text (not stored).',
            'options' => ['desc' => ['type' => 'string', 'description' => 'Heading text (uses desc instead of name).']],
        ],
    ];
}

/**
 * Geographic, embed and special input field type definitions.
 *
 * @return array<string, array{label: string, description: string, options: array<string, mixed>}>
 */
function mb_field_type_map_geo(): array
{
    return [
        'map' => [
            'label' => 'Google Map',
            'description' => 'Google Maps picker — stores lat,lng string.',
            'options' => [
                'std' => ['type' => 'string', 'description' => 'Default coordinates as "lat,lng" string.'],
                'api_key' => [
                    'type' => 'string',
                    'description' => 'Google Maps API key (overrides the plugin setting).',
                ],
                'region' => ['type' => 'string', 'description' => 'ISO 3166-1 alpha-2 country code bias.'],
                'language' => ['type' => 'string', 'description' => 'Language code for the map UI.'],
            ],
        ],
        'osm' => [
            'label' => 'OpenStreetMap',
            'description' => 'OpenStreetMap picker — stores lat,lng string.',
            'options' => [
                'std' => ['type' => 'string', 'description' => 'Default coordinates as "lat,lng" string.'],
                'address_field' => [
                    'type' => 'string',
                    'description' => 'ID of a text field to use for address lookup.',
                ],
            ],
        ],
        'oembed' => [
            'label' => 'oEmbed',
            'description' => 'oEmbed URL input — stores the URL.',
            'options' => ['size' => ['type' => 'integer', 'description' => 'Input size attribute.']],
        ],
        'sidebar' => ['label' => 'Sidebar', 'description' => 'Registered WordPress sidebar selector.', 'options' => []],
        'fieldset_text' => [
            'label' => 'Fieldset Text',
            'description' => 'Multiple text inputs displayed as a single fieldset row — stores values as an array keyed by the sub-field IDs.',
            'options' => [
                'options' => [
                    'type' => 'object',
                    'description' => '"id" => "Label" map of sub-inputs.',
                    'required' => true,
                ],
            ],
        ],
    ];
}
