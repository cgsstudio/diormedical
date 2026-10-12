<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acpt;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acpt-get-field-type-schema', [
    'label' => __('Get ACPT Field Type Schema', domain: 'novamira-pro'),
    'description' => __(
        'Returns the option matrix and value format for a single ACPT field type. Call this before creating or editing a field to discover its advanced options, and before novamira/acpt-write-values to get the value shape the type accepts. Available types (case-sensitive): Text, Textarea, Editor, Number, Range, Email, Url, Password, Phone, Color, Date, DateTime, DateRange, Time, Image, File, Gallery, ImageSlider, Audio, AudioMulti, Video, VideoMulti, Post, PostObject, PostObjectMulti, User, UserMulti, TermObject, TermObjectMulti, Select, SelectMulti, Radio, Checkbox, Toggle, Repeater, FlexibleContent, List, Table, Address, AddressMulti, Embed, Icon, Currency, Country, Rating, Barcode, QRCode, HTML, ID, Length, Weight, Clone, Message (display-only, only where the installed ACPT has it).',
        domain: 'novamira-pro',
    ),
    'category' => 'acpt',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'ACPT field type (case-sensitive). E.g. "Text", "Repeater", "Select".',
            ],
        ],
        'required' => ['type'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'type' => ['type' => 'string'],
            'description' => ['type' => 'string'],
            'is_complex' => ['type' => 'boolean'],
            'supports_options' => ['type' => 'boolean'],
            'supports_children' => ['type' => 'boolean'],
            'storage_format' => [
                'type' => 'string',
                'description' => 'Shape of the value passed to novamira/acpt-write-values: string|number|boolean|list|object|attachment|attachment_list|varies|none.',
            ],
            'value_format' => [
                'type' => 'string',
                'description' => 'What to send to novamira/acpt-write-values, how ACPT stores it, and what novamira/acpt-read-values returns.',
            ],
            'advanced_options' => ['type' => 'object'],
        ],
        'required' => ['type', 'description', 'is_complex', 'supports_options', 'supports_children', 'storage_format'],
        'additionalProperties' => true,
    ],
    'execute_callback' => __NAMESPACE__ . '\\acpt_get_field_type_schema',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Returns the capability matrix for one ACPT field type. Call before novamira/acpt-create-field-group or novamira/acpt-edit-field-group when you need to configure a field type\'s advanced options (pass them as top-level keys of the field), and before novamira/acpt-write-values to get value_format. is_complex=true means the field has nested sub-fields (Repeater children, FlexibleContent blocks). supports_options=true means the field type accepts a choices list (Select, SelectMulti, Radio, Checkbox). supports_children=true means nested sub-fields are expected.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array{type: string} $args
 * @return array<string, mixed>|WP_Error
 */
function acpt_get_field_type_schema(array $args): array|WP_Error
{
    $guard = acpt_runtime_guard();
    if ($guard !== null) {
        return $guard;
    }

    $type = trim($args['type']);
    if ($type === '') {
        return new WP_Error('acpt_invalid_input', __('type is required.', domain: 'novamira-pro'));
    }

    $schema = acpt_field_type_schema($type);
    if ($schema === null) {
        return new WP_Error('acpt_invalid_field_type', sprintf(
            /* translators: %s: field type. */
            __(
                'Unknown ACPT field type "%s". Field types are case-sensitive; the full list is in the description of novamira/acpt-get-field-type-schema.',
                domain: 'novamira-pro',
            ),
            $type,
        ));
    }

    if (!acpt_field_type_available($type)) {
        return new WP_Error('acpt_field_type_unavailable', sprintf(
            /* translators: %s: field type. */
            __('The installed ACPT does not have the "%s" field type.', domain: 'novamira-pro'),
            $type,
        ));
    }

    return $schema;
}

/**
 * Whether the running ACPT knows a field type. Types newer than some
 * supported ACPT versions are detected from ACPT's own type constants.
 */
function acpt_field_type_available(string $type): bool
{
    if ($type !== 'Message') {
        return true;
    }
    return defined('ACPT\\Core\\Models\\Meta\\MetaFieldModel::MESSAGE_TYPE');
}

/**
 * Returns the option matrix for a known ACPT field type, or null for unknown types.
 *
 * @return array<string, mixed>|null
 */
function acpt_field_type_schema(string $type): ?array
{
    $map = acpt_field_type_map();
    return $map[$type] ?? null;
}

// ---------------------------------------------------------------------------
// Descriptor factories
// ---------------------------------------------------------------------------

/**
 * Builds a simple (single-value) field descriptor.
 *
 * @param array<string, string> $advanced
 * @return array<string, mixed>
 */
function acpt_ft_simple(string $type, string $desc, string $fmt, string $value, array $advanced = []): array
{
    return [
        'type' => $type,
        'description' => $desc,
        'is_complex' => false,
        'supports_options' => false,
        'supports_children' => false,
        'storage_format' => $fmt,
        'value_format' => $value,
        'advanced_options' => $advanced,
    ];
}

/**
 * Builds a choice field descriptor (select / radio / checkbox).
 *
 * @return array<string, mixed>
 */
function acpt_ft_choice(string $type, string $desc, string $fmt, string $value): array
{
    return array_merge(acpt_ft_simple(type: $type, desc: $desc, fmt: $fmt, value: $value), [
        'supports_options' => true,
        'advanced_options' => [
            'options' => 'list of {label, value, isDefault?} choices (top-level field key)',
        ],
    ]);
}

/**
 * Builds a media field descriptor.
 *
 * @return array<string, mixed>
 */
function acpt_ft_media(string $type, string $desc, string $fmt, string $value): array
{
    return acpt_ft_simple(type: $type, desc: $desc, fmt: $fmt, value: $value, advanced: [
        'accepts' => 'comma-separated MIME type list (e.g. "image/jpeg,image/png")',
    ]);
}

// ---------------------------------------------------------------------------
// Sub-maps (split to keep halstead under threshold)
// ---------------------------------------------------------------------------

/**
 * Text and scalar field types.
 *
 * @return array<string, array<string, mixed>>
 */
function acpt_ft_map_text(): array
{
    $text = 'A string. Stored and read back as written.';
    return [
        'Text' => acpt_ft_simple(type: 'Text', desc: 'Single-line text input.', fmt: 'string', value: $text),
        'Textarea' => acpt_ft_simple(type: 'Textarea', desc: 'Multi-line plain text.', fmt: 'string', value: $text),
        'Editor' => acpt_ft_simple(type: 'Editor', desc: 'WYSIWYG editor.', fmt: 'string', value: 'An HTML string.'),
        'Number' => acpt_ft_simple(type: 'Number', desc: 'Number input.', fmt: 'number', value: 'A number, e.g. 12.5.'),
        'Range' => acpt_ft_simple(type: 'Range', desc: 'Numeric range slider.', fmt: 'number', value: 'A number.'),
        'Email' => acpt_ft_simple(
            type: 'Email',
            desc: 'Email address.',
            fmt: 'string',
            value: 'An email address string.',
        ),
        'Url' => acpt_ft_simple(
            type: 'Url',
            desc: 'URL with an optional link label.',
            fmt: 'string',
            value: 'A URL string, or {url, label}. Stored as the URL plus a _label helper (the URL when no label). read-values returns {url, label, before, after}.',
        ),
        'Password' => acpt_ft_simple(
            type: 'Password',
            desc: 'Password input.',
            fmt: 'string',
            value: 'A plain string. ACPT stores a one-way hash and read-values returns the hash, never the original text.',
        ),
        'Phone' => acpt_ft_simple(
            type: 'Phone',
            desc: 'Phone number.',
            fmt: 'string',
            value: 'A phone number string. read-values returns {value, dial}.',
        ),
        'Color' => acpt_ft_simple(
            type: 'Color',
            desc: 'Color picker.',
            fmt: 'string',
            value: 'A hex color string, e.g. "#ff0000".',
        ),
        'Date' => acpt_ft_simple(
            type: 'Date',
            desc: 'Date picker.',
            fmt: 'string',
            value: 'A "Y-m-d" string. read-values returns {value (formatted for display), format, object}.',
        ),
        'DateTime' => acpt_ft_simple(
            type: 'DateTime',
            desc: 'Date and time picker.',
            fmt: 'string',
            value: 'A "Y-m-d H:i:s" string. read-values returns {value (formatted for display), format, object}.',
        ),
        'DateRange' => acpt_ft_simple(
            type: 'DateRange',
            desc: 'Date range picker.',
            fmt: 'list',
            value: 'A two-item list ["Y-m-d", "Y-m-d"] (from, to; from must not be after to). Stored as the string "from - to". read-values returns {value: [from, to] formatted, format, object}.',
        ),
        'Time' => acpt_ft_simple(
            type: 'Time',
            desc: 'Time picker.',
            fmt: 'string',
            value: 'An "H:i" string, e.g. "10:30".',
        ),
        'Toggle' => acpt_ft_simple(type: 'Toggle', desc: 'On/off switch.', fmt: 'boolean', value: 'true or false.'),
        'Embed' => acpt_ft_simple(
            type: 'Embed',
            desc: 'oEmbed URL.',
            fmt: 'string',
            value: 'A URL string (e.g. a YouTube link).',
        ),
        'Icon' => acpt_ft_simple(
            type: 'Icon',
            desc: 'Icon picker.',
            fmt: 'string',
            value: 'An icon class string, e.g. "dashicons-admin-home".',
        ),
        'Country' => acpt_ft_simple(
            type: 'Country',
            desc: 'Country picker.',
            fmt: 'object',
            value: '{value, country}: value is the country name, country its ISO 3166-1 alpha-2 code, e.g. {"value": "Italy", "country": "it"}. Stored as the name plus a _country helper.',
        ),
        'Rating' => acpt_ft_simple(type: 'Rating', desc: 'Star rating.', fmt: 'number', value: 'A number.'),
        'Barcode' => acpt_ft_simple(
            type: 'Barcode',
            desc: 'Barcode generated from a value.',
            fmt: 'object',
            value: '{value} with value a string (optional format, e.g. "code128", and color). ACPT stores the value and a generated SVG. read-values returns {text, value: {svg, ...}}.',
        ),
        'QRCode' => acpt_ft_simple(
            type: 'QRCode',
            desc: 'QR code generated from a URL.',
            fmt: 'string',
            value: 'A URL string (anything else is refused). ACPT stores the URL and a generated image. read-values returns {url, value: {img, ...}}.',
        ),
        'HTML' => acpt_ft_simple(type: 'HTML', desc: 'Raw HTML content.', fmt: 'string', value: 'An HTML string.'),
        'ID' => acpt_ft_simple(
            type: 'ID',
            desc: 'Unique identifier.',
            fmt: 'string',
            value: 'An identifier string. ACPT may refuse a value that does not match the field\'s id_strategy (a UUID v1 by default).',
        ),
        'Message' => acpt_ft_simple(
            type: 'Message',
            desc: 'Display-only message shown in the editor: the text comes from the field description. It stores no value, so do not write to it.',
            fmt: 'none',
            value: 'None: the field holds no value.',
            advanced: [
                'show_label' => 'whether the field label is shown',
                'css' => 'extra CSS classes',
                'width' => 'field width in percent',
            ],
        ),
    ];
}

/**
 * Media and file field types.
 *
 * @return array<string, array<string, mixed>>
 */
function acpt_ft_map_media(): array
{
    $single = 'An attachment id or the attachment URL. ACPT stores the URL plus an _attachment_id helper; read-values returns the attachment id.';
    $multi = 'A list of attachment ids or URLs. ACPT stores the list of URLs plus an _attachment_id helper (comma-separated ids); read-values returns the list of attachment ids.';
    return [
        'Image' => acpt_ft_media(type: 'Image', desc: 'Single image.', fmt: 'attachment', value: $single),
        'File' => acpt_ft_media(
            type: 'File',
            desc: 'Single file (not an image, audio or video).',
            fmt: 'object',
            value: '{id} with the attachment id as a string, or {url}; optional label. A bare id is refused. ACPT stores the URL plus _attachment_id and _label helpers; read-values returns {file: attachment id, label}.',
        ),
        'Audio' => acpt_ft_media(type: 'Audio', desc: 'Single audio file.', fmt: 'attachment', value: $single),
        'Video' => acpt_ft_media(type: 'Video', desc: 'Single video file.', fmt: 'attachment', value: $single),
        'AudioMulti' => acpt_ft_media(
            type: 'AudioMulti',
            desc: 'Multiple audio files.',
            fmt: 'attachment_list',
            value: $multi,
        ),
        'VideoMulti' => acpt_ft_media(
            type: 'VideoMulti',
            desc: 'Multiple video files.',
            fmt: 'attachment_list',
            value: $multi,
        ),
        'Gallery' => acpt_ft_media(type: 'Gallery', desc: 'Image gallery.', fmt: 'attachment_list', value: $multi),
        'ImageSlider' => acpt_ft_media(
            type: 'ImageSlider',
            desc: 'Before/after image slider.',
            fmt: 'attachment_list',
            value: 'A list of at most two image attachment ids or URLs. ' . $multi,
        ),
    ];
}

/**
 * Relationship field types.
 *
 * @return array<string, array<string, mixed>>
 */
function acpt_ft_map_relationship(): array
{
    return [
        'Post' => acpt_ft_simple(
            type: 'Post',
            desc: 'Bidirectional post relationship (configured through the field\'s relations).',
            fmt: 'varies',
            value: 'A related post id or a list of ids. ACPT writes the relation on both sides.',
        ),
        'PostObject' => acpt_ft_simple(
            type: 'PostObject',
            desc: 'Single related post.',
            fmt: 'number',
            value: 'A post id. read-values returns the id.',
        ),
        'PostObjectMulti' => acpt_ft_simple(
            type: 'PostObjectMulti',
            desc: 'Multiple related posts.',
            fmt: 'list',
            value: 'A list of post ids. read-values returns the list of ids.',
            advanced: ['filter_post_type' => 'CPT slug to restrict the picker to'],
        ),
        'User' => acpt_ft_simple(
            type: 'User',
            desc: 'Single related user.',
            fmt: 'number',
            value: 'A user id. read-values returns the id.',
        ),
        'UserMulti' => acpt_ft_simple(
            type: 'UserMulti',
            desc: 'Multiple related users.',
            fmt: 'list',
            value: 'A list of user ids. read-values returns the list of ids.',
        ),
        'TermObject' => acpt_ft_simple(
            type: 'TermObject',
            desc: 'Single related term.',
            fmt: 'number',
            value: 'A term id. read-values returns the id.',
        ),
        'TermObjectMulti' => acpt_ft_simple(
            type: 'TermObjectMulti',
            desc: 'Multiple related terms.',
            fmt: 'list',
            value: 'A list of term ids. read-values returns the list of ids.',
            advanced: ['filter_taxonomy' => 'taxonomy slug to restrict the picker to'],
        ),
    ];
}

/**
 * Choice field types.
 *
 * @return array<string, array<string, mixed>>
 */
function acpt_ft_map_choice(): array
{
    return [
        'Select' => acpt_ft_choice(
            type: 'Select',
            desc: 'Single-select dropdown.',
            fmt: 'string',
            value: 'One option value string.',
        ),
        'SelectMulti' => acpt_ft_choice(
            type: 'SelectMulti',
            desc: 'Multi-select dropdown.',
            fmt: 'list',
            value: 'A list of option value strings. Stored as a native array.',
        ),
        'Radio' => acpt_ft_choice(
            type: 'Radio',
            desc: 'Radio button group.',
            fmt: 'string',
            value: 'One option value string.',
        ),
        'Checkbox' => acpt_ft_choice(
            type: 'Checkbox',
            desc: 'Checkbox group.',
            fmt: 'list',
            value: 'A list of option value strings. Stored as a native array.',
        ),
    ];
}

/**
 * Complex and structured field types.
 *
 * @return array<string, array<string, mixed>>
 */
function acpt_ft_map_complex(): array
{
    $blocks = [
        'minimum_blocks' => 'minimum number of rows/blocks',
        'maximum_blocks' => 'maximum number of rows/blocks',
    ];
    return [
        'Repeater' => array_merge(
            acpt_ft_simple(
                type: 'Repeater',
                desc: 'Repeatable rows of sub-fields.',
                fmt: 'list',
                value: 'A list of rows, each {child_field_name: value}, e.g. [{"title": "A"}, {"title": "B"}]. read-values returns the same list.',
                advanced: array_merge([
                    'children' => 'array of nested field definitions (top-level field key)',
                ], $blocks),
            ),
            ['is_complex' => true, 'supports_children' => true],
        ),
        'FlexibleContent' => array_merge(
            acpt_ft_simple(
                type: 'FlexibleContent',
                desc: 'Flexible content layout built from named blocks.',
                fmt: 'object',
                value: '{blocks: [{block_name: [{block_field_name: value}]}, ...]}, one entry per block instance in page order, e.g. {"blocks": [{"hero": [{"heading": "Hi"}]}]}. read-values returns {blocks: [{block_name: {block_field_name: [value]}}]}.',
                advanced: array_merge([
                    'blocks' => 'array of {name, label, fields[]} block definitions (top-level field key)',
                ], $blocks),
            ),
            ['is_complex' => true, 'supports_children' => true],
        ),
        'List' => acpt_ft_simple(
            type: 'List',
            desc: 'Simple list of text values.',
            fmt: 'list',
            value: 'A list of strings. Stored as a native array.',
        ),
        'Table' => acpt_ft_simple(
            type: 'Table',
            desc: 'Table editor.',
            fmt: 'object',
            value: 'The table definition object ACPT\'s table editor produces; ACPT refuses a value it cannot parse as one (a plain list of rows is refused). Prefer editing tables in the ACPT editor.',
        ),
        'Address' => acpt_ft_simple(
            type: 'Address',
            desc: 'Postal address with map lookup.',
            fmt: 'string',
            value: 'One address string, e.g. "Via Roma 1, Milano" (an object is refused). ACPT geocodes it and stores _lat, _lng, _city and _country helpers when the lookup succeeds; read-values returns {address, city, country, lat, lng}.',
        ),
        'AddressMulti' => acpt_ft_simple(
            type: 'AddressMulti',
            desc: 'Multiple addresses.',
            fmt: 'list',
            value: 'A list of address strings.',
        ),
        'Currency' => acpt_ft_simple(
            type: 'Currency',
            desc: 'Amount with a currency.',
            fmt: 'object',
            value: '{amount, unit}: amount a number, unit a currency code, e.g. {"amount": 12, "unit": "USD"}. Stored as the amount plus a _currency helper; read-values returns {amount, unit}.',
        ),
        'Length' => acpt_ft_simple(
            type: 'Length',
            desc: 'Length measurement.',
            fmt: 'object',
            value: '{length, unit}, e.g. {"length": 3, "unit": "cm"}. Stored as the number plus a _length helper holding the unit; read-values returns {length, unit}.',
        ),
        'Weight' => acpt_ft_simple(
            type: 'Weight',
            desc: 'Weight measurement.',
            fmt: 'object',
            value: '{weight, unit}, e.g. {"weight": 3, "unit": "kg"}. Stored as the number plus a _weight helper holding the unit; read-values returns {weight, unit}.',
        ),
        'Clone' => acpt_ft_simple(
            type: 'Clone',
            desc: 'Re-uses the fields of another box. Holds no value of its own: values belong to the cloned fields.',
            fmt: 'varies',
            value: 'Write the cloned fields themselves.',
            advanced: [
                'cloned_fields' => 'list of {box_name, field_name} pairs identifying the source fields to clone',
            ],
        ),
    ];
}

/**
 * Static capability matrix for all known ACPT field types.
 *
 * @return array<string, array<string, mixed>>
 */
function acpt_field_type_map(): array
{
    return array_merge(
        acpt_ft_map_text(),
        acpt_ft_map_media(),
        acpt_ft_map_relationship(),
        acpt_ft_map_choice(),
        acpt_ft_map_complex(),
    );
}
