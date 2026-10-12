<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

const EL_TEMPLATE_WRITE_STATUSES = ['publish', 'draft', 'pending', 'private'];

const EL_TEMPLATE_LIST_STATUSES = ['any', 'publish', 'draft', 'pending', 'private', 'trash'];

const EL_TEMPLATE_PER_PAGE_MAX = 100;

wp_register_ability('novamira/elementor-create-template', [
    'label' => __('Create Elementor Template', domain: 'novamira-pro'),
    'description' =>
        __(
            'Creates an Elementor library template through Elementor\'s document API, so its template type, edit mode, library term, and normal Elementor bookkeeping are initialized together. `title`/`post_title` and `status`/`post_status` are aliases, with the short name winning when both are present. A title and `type` are required; status defaults to publish so the template is immediately visible (pass status: draft to create a draft instead). Optional `content` uses exactly the same node vocabulary and validated write pipeline as novamira/elementor-set-content. The type must be a creatable library template type registered on this site; an invalid, currently unavailable, or editor-only type is refused with the accepted types in the error.',
            domain: 'novamira-pro',
        ) . el_not_persisted_description_sentence(),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'title' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Template title. Alias: post_title.',
            ],
            'post_title' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'WordPress-native alias for title.',
            ],
            'type' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'Creatable Elementor library template type, such as section, container, page, header, footer, or loop-item. Global Widgets must be created in the Elementor editor. The types accepted on this site are named in an invalid-type error.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => EL_TEMPLATE_WRITE_STATUSES,
                'default' => 'publish',
                'description' => 'Initial WordPress post status. Alias: post_status. Defaults to publish.',
            ],
            'post_status' => [
                'type' => 'string',
                'enum' => EL_TEMPLATE_WRITE_STATUSES,
                'description' => 'WordPress-native alias for status.',
            ],
            'content' => [
                'type' => 'array',
                'description' => 'Optional Elementor element tree. Uses the same recursive node vocabulary, normalization, validation, ID assignment, persistence, and verification as novamira/elementor-set-content.',
                'items' => [
                    'type' => 'object',
                    'properties' => el_node_schema_properties(),
                ],
            ],
        ],
        'required' => ['type'],
        'anyOf' => [
            ['required' => ['title']],
            ['required' => ['post_title']],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => el_template_identity_schema_properties()
            + [
                EL_NOT_PERSISTED_KEY => el_not_persisted_output_schema(),
            ],
        'required' => ['id', 'type', 'title', 'status', 'edit_url'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Elementor\\elementor_create_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'instructions' => 'Use this instead of creating an elementor_library post and writing Elementor meta by hand. If content is omitted, populate the new template with novamira/elementor-set-content.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/elementor-list-templates', [
    'label' => __('List Elementor Templates', domain: 'novamira-pro'),
    'description' => __(
        'Lists Elementor library templates as compact rows. Registered foreign documents that are not editable content or templates, including the active Site Kit, are excluded; library posts with a missing type or a registered content type stored under the wrong post type remain visible so they can be repaired. Optional filters narrow by a currently registered library template `type`, WordPress `status`, or title/content `search`. Pagination is controlled by `page` and `per_page` (capped at 100), with `total` and `total_pages` describing the full result. `type_available=false` flags a stored template type that is not registered on this site, commonly because its provider is inactive. `type_mismatch=true` flags missing or inconsistent type state, including a registered document type that is not valid for the template library.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'type' => [
                'type' => 'string',
                'description' => 'Filter by a library template type registered on this site. Invalid or unavailable types are refused with the accepted types in the error.',
            ],
            'status' => [
                'type' => 'string',
                'enum' => EL_TEMPLATE_LIST_STATUSES,
                'default' => 'any',
                'description' => 'Filter by WordPress post status. `any` includes normal non-trashed statuses; use `trash` explicitly for trashed templates.',
            ],
            'search' => [
                'type' => 'string',
                'description' => 'WordPress search text applied to template titles and content.',
            ],
            'page' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 1,
                'description' => 'One-based result page. Defaults to 1.',
            ],
            'per_page' => [
                'type' => 'integer',
                'minimum' => 1,
                'default' => 20,
                'description' => 'Rows per page. Defaults to 20 and is capped at 100.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'templates' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => el_template_summary_schema_properties(),
                    'required' => [
                        'id',
                        'title',
                        'type',
                        'library_term',
                        'status',
                        'modified',
                        'type_available',
                        'type_mismatch',
                        'conditions_count',
                    ],
                    'additionalProperties' => false,
                ],
            ],
            'page' => ['type' => 'integer'],
            'per_page' => ['type' => 'integer'],
            'total' => ['type' => 'integer'],
            'total_pages' => ['type' => 'integer'],
        ],
        'required' => ['templates', 'page', 'per_page', 'total', 'total_pages'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Elementor\\elementor_list_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'instructions' => 'Use the compact list to choose an id, then call get-template for metadata or get-content for the element tree. Treat type_available=false and type_mismatch=true as integrity warnings before editing.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/elementor-get-template', [
    'label' => __('Get Elementor Template', domain: 'novamira-pro'),
    'description' => __(
        'Returns one Elementor library template\'s metadata: identity, stored type and library term, status, author, dates, Elementor edit URL, current type availability, type/term agreement, and element count. Content is intentionally excluded; read the element tree with novamira/elementor-get-content using the returned id.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => el_template_id_input_schema(),
    'output_schema' => [
        'type' => 'object',
        'properties' => el_template_metadata_schema_properties(),
        'required' => [
            'id',
            'title',
            'type',
            'library_term',
            'status',
            'author',
            'date',
            'modified',
            'edit_url',
            'type_available',
            'type_mismatch',
            'element_count',
        ],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Elementor\\elementor_get_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'instructions' => 'This is the metadata read. Use novamira/elementor-get-content with post_id=<id> for the compact tree, a subtree, or a full dump.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/elementor-edit-template', [
    'label' => __('Edit Elementor Template', domain: 'novamira-pro'),
    'description' => __(
        'Partially edits an Elementor library template. Only supplied fields change. `title`/`post_title` and `status`/`post_status` are aliases, with the short name winning when both are present. Supplying `type` performs an explicit Elementor document conversion through the same validated conversion path as novamira/elementor-set-content, keeping `_elementor_template_type` and the `elementor_library_type` term synchronized. An edit is refused, including a title-only or status-only edit, while the stored template type is unavailable on this site because loading it through a fallback document could discard content owned by the missing provider; reactivate that provider or pass an available `type` for a deliberate conversion. Content remains on novamira/elementor-set-content.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Elementor library template post ID. Alias: post_id.',
            ],
            'post_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Alias for template_id.',
            ],
            'title' => ['type' => 'string', 'description' => 'New template title. Alias: post_title.'],
            'post_title' => ['type' => 'string', 'description' => 'WordPress-native alias for title.'],
            'status' => [
                'type' => 'string',
                'enum' => EL_TEMPLATE_WRITE_STATUSES,
                'description' => 'New WordPress post status. Alias: post_status.',
            ],
            'post_status' => [
                'type' => 'string',
                'enum' => EL_TEMPLATE_WRITE_STATUSES,
                'description' => 'WordPress-native alias for status.',
            ],
            'type' => [
                'type' => 'string',
                'minLength' => 1,
                'description' => 'New registered Elementor library template type. This is an explicit conversion; invalid, unavailable, or editor-only values are refused before anything changes. A template cannot be converted into a Global Widget here.',
            ],
        ],
        'additionalProperties' => false,
        'anyOf' => [
            ['required' => ['template_id']],
            ['required' => ['post_id']],
        ],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => el_template_identity_schema_properties()
            + [
                'updated_fields' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'The supplied fields applied by this call, using the short names title, status, and type.',
                ],
            ],
        'required' => ['id', 'type', 'title', 'status', 'edit_url', 'updated_fields'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Elementor\\elementor_edit_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'instructions' => 'Partial update. Pass type only for a deliberate template conversion; the ability validates it and synchronizes Elementor\'s type meta and library term. Use set-content separately for the element tree.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/elementor-delete-template', [
    'label' => __('Delete Elementor Template', domain: 'novamira-pro'),
    'description' => __(
        'Deletes an Elementor library template with the same trash/force semantics as novamira/delete-post. The default moves it to trash when the site keeps trash; `force=true` permanently deletes it. Elementor\'s normal WordPress deletion hooks run, and Elementor Pro\'s Theme Builder conditions cache is purged for both trash and permanent deletion. References from other Elementor documents, such as a loop-grid widget\'s template_id, are not rewritten and become unresolved when the template is unavailable.',
        domain: 'novamira-pro',
    ),
    'category' => 'elementor',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'template_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Elementor library template post ID. Alias: post_id.',
            ],
            'post_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Alias for template_id.',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'When true, permanently deletes the template. Defaults to false, which moves it to trash when trash is enabled.',
            ],
        ],
        'additionalProperties' => false,
        'anyOf' => [
            ['required' => ['template_id']],
            ['required' => ['post_id']],
        ],
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'integer'],
            'type' => ['type' => 'string'],
            'title' => ['type' => 'string'],
            'previous_status' => ['type' => 'string'],
            'result' => [
                'type' => 'string',
                'enum' => ['trashed', 'deleted'],
                'description' => '`trashed` when recoverable, `deleted` when permanently removed.',
            ],
        ],
        'required' => ['id', 'type', 'title', 'previous_status', 'result'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Elementor\\elementor_delete_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'instructions' => 'Trash is the default and is recoverable unless the site disables trash. Pass force=true only for confirmed permanent deletion. Other documents that refer to this template are not edited.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function el_template_id_input_schema(): array
{
    return [
        'type' => 'object',
        'properties' => [
            'template_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Elementor library template post ID. Alias: post_id.',
            ],
            'post_id' => [
                'type' => 'integer',
                'minimum' => 1,
                'description' => 'Alias for template_id.',
            ],
        ],
        'additionalProperties' => false,
        'anyOf' => [
            ['required' => ['template_id']],
            ['required' => ['post_id']],
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function el_template_identity_schema_properties(): array
{
    return [
        'id' => ['type' => 'integer'],
        'type' => ['type' => 'string'],
        'title' => ['type' => 'string'],
        'status' => ['type' => 'string'],
        'edit_url' => ['type' => 'string'],
    ];
}

/**
 * @return array<string, mixed>
 */
function el_template_summary_schema_properties(): array
{
    return [
        'id' => ['type' => 'integer'],
        'title' => ['type' => 'string'],
        'type' => [
            'type' => ['string', 'null'],
            'description' => 'Stored `_elementor_template_type` meta value. Null when the meta is absent.',
        ],
        'library_term' => [
            'type' => 'string',
            'description' => 'Stored `elementor_library_type` term slug. Empty when absent; comma-separated if a damaged template has multiple terms.',
        ],
        'status' => ['type' => 'string'],
        'modified' => ['type' => 'string', 'description' => 'WordPress post_modified timestamp in site local time.'],
        'type_available' => [
            'type' => 'boolean',
            'description' => 'Whether the stored type is registered on this site now.',
        ],
        'type_mismatch' => [
            'type' => 'boolean',
            'description' => 'Whether the type meta and library term are missing, multiple, different, or name a registered document type that is not valid for the template library.',
        ],
        'conditions_count' => [
            'type' => 'integer',
            'description' => 'Number of display-condition entries in the raw _elementor_conditions post meta. Non-zero values on a non-Theme-Builder template indicate stale meta. Use novamira/elementor-get-template-conditions for the structured, validated list.',
        ],
    ];
}

/**
 * @return array<string, mixed>
 */
function el_template_metadata_schema_properties(): array
{
    return el_template_summary_schema_properties()
    + [
        'author' => ['type' => 'integer'],
        'date' => ['type' => 'string', 'description' => 'WordPress post_date timestamp in site local time.'],
        'edit_url' => ['type' => 'string'],
        'element_count' => [
            'type' => ['integer', 'null'],
            'description' => 'All elements in the stored tree, recursively. Null when the stored Elementor data cannot be decoded.',
        ],
        'conditions' => [
            'type' => 'array',
            'items' => [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['include', 'exclude']],
                    'name' => ['type' => 'string'],
                    'sub_name' => ['type' => 'string'],
                    'sub_id' => ['type' => 'string'],
                ],
                'required' => ['type', 'name'],
                'additionalProperties' => false,
            ],
            'description' => 'Saved display conditions. Empty when no conditions are set or the template type does not support conditions. Only present when Elementor Pro is active.',
        ],
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function elementor_create_template(array $input): array|WP_Error
{
    $input = el_template_normalize_aliases($input);
    $context = el_template_create_context($input);
    if ($context instanceof WP_Error) {
        return $context;
    }

    $prepared_content = null;
    if (array_key_exists('content', $input)) {
        $prepared_content = el_prepare_set_content_tree($input['content']);
        if (array_key_exists('response', $prepared_content)) {
            return el_template_create_content_error($prepared_content['response']);
        }
    }

    $created = el_template_create_document($context);
    if ($created instanceof WP_Error) {
        return $created;
    }
    $template_id = (int) $created->get_main_id();

    $sent = [];
    if ($prepared_content !== null) {
        /** @var array{tree: list<array<string, mixed>>, assigned_ids: list<string>, sent: array<string, array{settings: list<string>, styles: array<string, array<string, list<string>>>}>} $prepared_content */
        $sent = $prepared_content['sent'];
        $content_error = el_template_store_created_content($template_id, $context['type'], $prepared_content['tree']);
        if ($content_error !== null) {
            return $content_error;
        }
    }

    /** @var WP_Post|null $post */
    $post = get_post($template_id);
    if (!$post instanceof WP_Post) {
        return el_write_target_error(
            code: 'elementor_template_create_failed',
            message: 'The created template could not be reloaded.',
            data: ['template_id' => $template_id],
            status: 500,
        );
    }
    return el_with_not_persisted(el_template_identity($post), $template_id, $sent);
}

/**
 * @param array<string, mixed> $input
 * @return array{title: string, type: string, status: string}|WP_Error
 */
function el_template_create_context(array $input): array|WP_Error
{
    $title = isset($input['title']) && is_string($input['title']) ? trim($input['title']) : '';
    $type = isset($input['type']) && is_string($input['type']) ? trim($input['type']) : '';
    $status = isset($input['status']) && is_string($input['status']) ? $input['status'] : 'publish';

    if ($title === '') {
        return el_write_target_error(
            code: 'elementor_invalid_template_title',
            message: 'Template title must be a non-empty string. Nothing was created.',
            data: [],
        );
    }
    $type_error = el_template_type_conversion_error(
        post_type: 'elementor_library',
        stored_template_type: '',
        template_type: $type,
        post_id: null,
    );
    if ($type_error !== null) {
        return el_template_contextual_type_error($type_error, context: 'create');
    }
    $status_error = el_template_write_status_error($status);
    if ($status_error !== null) {
        return $status_error;
    }
    return ['title' => $title, 'type' => $type, 'status' => $status];
}

/**
 * @param array{title: string, type: string, status: string} $context
 */
function el_template_create_document(array $context): \Elementor\Core\Base\Document|WP_Error
{
    $manager = \Elementor\Plugin::$instance->documents;
    if (!$manager instanceof \Elementor\Core\Documents_Manager) {
        return el_write_target_error(
            code: 'elementor_documents_unavailable',
            message: 'Elementor document management is not available on this request.',
            data: [],
            status: 503,
        );
    }

    /** @var \Elementor\Core\Base\Document|WP_Error $created */
    $created = $manager->create($context['type'], [
        'post_title' => wp_slash($context['title']),
        'post_status' => $context['status'],
    ]);
    if ($created instanceof WP_Error) {
        return el_write_target_error(
            code: 'elementor_template_create_failed',
            message: 'Elementor could not create the template: ' . $created->get_error_message(),
            data: ['type' => $context['type']],
            status: 500,
        );
    }
    $template_id = (int) $created->get_main_id();
    if ($template_id <= 0 || !get_post($template_id) instanceof WP_Post) {
        return el_write_target_error(
            code: 'elementor_template_create_failed',
            message: 'Elementor did not persist the created template.',
            data: ['type' => $context['type']],
            status: 500,
        );
    }

    el_sync_document_template_type($template_id);
    $type_state = el_template_type_snapshot($template_id);
    $terms_match =
        $type_state['terms'] !== null
        && count($type_state['terms']) === 1
        && $type_state['terms'][0] === $context['type'];
    if ($type_state['meta'] !== $context['type'] || !$terms_match) {
        wp_delete_post($template_id, force_delete: true);
        return el_write_target_error(
            code: 'elementor_template_create_failed',
            message: 'Elementor created the template but did not synchronize its template type. The incomplete template was deleted.',
            data: [
                'type' => $context['type'],
                'stored_type' => $type_state['meta'],
                'library_terms' => $type_state['terms'],
            ],
            status: 500,
        );
    }
    return $created;
}

/**
 * Preserve set-content's validation detail while adapting its array failures
 * to the structured error contract used by template creation.
 *
 * @param array<string, mixed>|WP_Error $response
 */
function el_template_create_content_error(array|WP_Error $response): WP_Error
{
    if ($response instanceof WP_Error) {
        /** @var mixed $raw_data */
        $raw_data = $response->get_error_data();
        $data = is_array($raw_data) ? $raw_data : [];
        $data['status'] = 400;
        return new WP_Error($response->get_error_code(), $response->get_error_message(), $data);
    }

    $message = is_string($response['error'] ?? null)
        ? $response['error']
        : 'The supplied Elementor content is invalid.';
    unset($response['success'], $response['error']);
    return el_write_target_error('elementor_invalid_template_content', $message, $response);
}

/**
 * @param list<array<string, mixed>> $content
 */
function el_template_store_created_content(int $template_id, string $type, array $content): ?WP_Error
{
    $result = el_write_page($template_id, $content, $type);
    if ($result === true) {
        return null;
    }

    $deleted = wp_delete_post($template_id, force_delete: true) instanceof WP_Post;
    $cleanup_message = $deleted
        ? ' The incomplete template was deleted, so nothing was created.'
        : sprintf(' The incomplete template could not be deleted and remains as post %d.', $template_id);
    if ($result instanceof WP_Error) {
        /** @var mixed $raw_data */
        $raw_data = $result->get_error_data();
        $data = is_array($raw_data) ? $raw_data : [];
        $data['template_deleted'] = $deleted;
        return new WP_Error($result->get_error_code(), $result->get_error_message() . $cleanup_message, $data);
    }

    return el_write_target_error(
        code: 'elementor_template_content_failed',
        message: 'Elementor content could not be stored.' . $cleanup_message,
        data: ['type' => $type, 'template_id' => $template_id, 'template_deleted' => $deleted],
        status: 500,
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function elementor_list_templates(array $input): array|WP_Error
{
    $type = isset($input['type']) && is_string($input['type']) ? trim($input['type']) : '';
    if ($type !== '') {
        $type_error = el_template_type_conversion_error(
            post_type: 'elementor_library',
            stored_template_type: $type,
            template_type: $type,
            post_id: null,
        );
        if ($type_error !== null) {
            return el_template_contextual_type_error($type_error, context: 'list');
        }
    }

    $status = isset($input['status']) && is_string($input['status']) ? $input['status'] : 'any';
    if (!in_array($status, EL_TEMPLATE_LIST_STATUSES, strict: true)) {
        return el_write_target_error(
            'elementor_invalid_template_status',
            sprintf(
                'Template status "%s" is invalid. Accepted list statuses: "%s".',
                $status,
                implode('", "', EL_TEMPLATE_LIST_STATUSES),
            ),
            ['status_value' => $status, 'accepted' => EL_TEMPLATE_LIST_STATUSES],
        );
    }

    $page = max(1, (int) ($input['page'] ?? 1));
    $per_page = min(EL_TEMPLATE_PER_PAGE_MAX, max(1, (int) ($input['per_page'] ?? 20)));
    $args = [
        'post_type' => 'elementor_library',
        'post_status' => $status,
        'posts_per_page' => $per_page,
        'paged' => $page,
        'orderby' => 'modified',
        'order' => 'DESC',
    ];
    $args += el_template_list_type_query_args($type);
    if (isset($input['search']) && is_string($input['search']) && trim($input['search']) !== '') {
        $args['s'] = trim($input['search']);
    }

    $query = new \WP_Query($args);
    $templates = [];
    foreach ($query->posts as $post) {
        if (!$post instanceof WP_Post) {
            continue;
        }
        $templates[] = el_template_summary($post);
    }

    return [
        'templates' => $templates,
        'page' => $page,
        'per_page' => $per_page,
        'total' => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
    ];
}

/**
 * Reword the shared conversion refusal for a create or list operation while
 * preserving its code, accepted types, and other structured data.
 */
function el_template_contextual_type_error(WP_Error $error, string $context): WP_Error
{
    $replacements = match ($context) {
        'create' => [
            'cannot be set on this post.' => 'cannot be used to create a template.',
            'for this post on this site' => 'for new Elementor library templates on this site',
        ],
        'list' => [
            'for this post on this site' => 'for the Elementor template library on this site',
            'Nothing was created.' => 'No templates were returned.',
        ],
        default => [],
    };
    return new WP_Error(
        $error->get_error_code(),
        str_replace(array_keys($replacements), array_values($replacements), $error->get_error_message()),
        $error->get_error_data(),
    );
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function elementor_get_template(array $input): array|WP_Error
{
    $post = el_template_or_error(el_template_id_arg($input));
    if ($post instanceof WP_Error) {
        return $post;
    }

    [$elements] = el_read_page($post->ID);

    $result = el_template_summary($post)
    + [
        'author' => (int) $post->post_author,
        'date' => $post->post_date,
        'edit_url' => el_template_edit_url($post->ID),
        'element_count' => $elements === null ? null : el_count_elements($elements),
    ];

    if (dc_theme_builder_available()) {
        $result['conditions'] = dc_read_conditions($post->ID);
    }

    return $result;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function elementor_edit_template(array $input): array|WP_Error
{
    $input = el_template_normalize_aliases($input);

    $post = el_template_or_error(el_template_id_arg($input));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $updated_fields = array_values(array_intersect(['title', 'status', 'type'], array_keys($input)));
    if ($updated_fields === []) {
        return el_write_target_error(
            code: 'elementor_template_no_changes',
            message: 'Pass at least one of title/post_title, status/post_status, or type.',
            data: ['template_id' => $post->ID],
        );
    }

    $edit_error = el_template_apply_edit($post, $input);
    if ($edit_error !== null) {
        return $edit_error;
    }

    /** @var WP_Post|null $fresh */
    $fresh = get_post($post->ID);
    if (!$fresh instanceof WP_Post) {
        return el_write_target_error(
            code: 'elementor_template_edit_failed',
            message: 'The edited template could not be reloaded.',
            data: ['template_id' => $post->ID],
            status: 500,
        );
    }
    return el_template_identity($fresh) + ['updated_fields' => $updated_fields];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function el_template_normalize_aliases(array $input): array
{
    foreach (['post_title' => 'title', 'post_status' => 'status'] as $wp_name => $short_name) {
        if (array_key_exists($short_name, $input) || !array_key_exists($wp_name, $input)) {
            continue;
        }
        $input[$short_name] = $input[$wp_name];
    }
    return $input;
}

/**
 * @param array<string, mixed> $input
 */
function el_template_apply_edit(WP_Post $post, array $input): ?WP_Error
{
    $context = el_template_edit_context($post, $input);
    if ($context instanceof WP_Error) {
        return $context;
    }
    $title = $context['title'];
    $status = $context['status'];

    /** @var list<array<string, mixed>> $elements */
    $elements = [];
    if (array_key_exists('type', $input)) {
        [$elements, $read_error] = el_read_page($post->ID);
        if ($elements === null) {
            return el_write_target_error(
                code: 'elementor_template_content_invalid',
                message: sprintf(
                    '%s Template %d cannot change type until its stored Elementor data is repaired. Nothing was changed.',
                    $read_error ?? 'The stored Elementor data is invalid.',
                    $post->ID,
                ),
                data: ['template_id' => $post->ID],
                status: 409,
            );
        }
    }

    $post_update = ['ID' => $post->ID];
    $applied_fields = [];
    if ($title !== null) {
        $post_update['post_title'] = wp_slash($title);
        $applied_fields[] = 'title';
    }
    if ($status !== null) {
        $post_update['post_status'] = $status;
        $applied_fields[] = 'status';
    }
    if (count($post_update) > 1) {
        $updated = wp_update_post($post_update, wp_error: true);
        if ($updated instanceof WP_Error) {
            return el_write_target_error(
                code: 'elementor_template_edit_failed',
                message: 'WordPress could not edit the template: ' . $updated->get_error_message(),
                data: ['template_id' => $post->ID],
                status: 500,
            );
        }
    }

    $conversion_error = array_key_exists('type', $input)
        ? el_template_apply_type_conversion($post->ID, $input, $elements)
        : null;
    return $conversion_error === null
        ? null
        : el_template_conversion_after_edit_error($conversion_error, $applied_fields);
}

/**
 * @param array<string, mixed> $input
 * @return array{title: ?string, status: ?string}|WP_Error
 */
function el_template_edit_context(WP_Post $post, array $input): array|WP_Error
{
    $title = null;
    if (array_key_exists('title', $input)) {
        $title = is_string($input['title']) ? trim($input['title']) : '';
        if ($title === '') {
            return el_write_target_error(
                code: 'elementor_invalid_template_title',
                message: 'Template title must be a non-empty string. Nothing was changed.',
                data: ['template_id' => $post->ID],
            );
        }
    }

    $status = isset($input['status']) && is_string($input['status']) ? $input['status'] : null;
    if ($status !== null) {
        $status_error = el_template_write_status_error($status);
        if ($status_error !== null) {
            return $status_error;
        }
    }

    $type_error = el_unavailable_template_type_error($post->ID);
    if (array_key_exists('type', $input)) {
        $type = is_string($input['type']) ? trim($input['type']) : '';
        $type_error = el_invalid_template_type_error($post->ID, $type);
    }
    if ($type_error !== null) {
        return $type_error;
    }
    return ['title' => $title, 'status' => $status];
}

/**
 * Make a conversion failure explicit when title or status was already saved.
 *
 * @param list<string> $applied_fields
 */
function el_template_conversion_after_edit_error(WP_Error $error, array $applied_fields): WP_Error
{
    if ($applied_fields === []) {
        return $error;
    }
    /** @var mixed $raw_data */
    $raw_data = $error->get_error_data();
    $data = is_array($raw_data) ? $raw_data : [];
    $data['applied_fields'] = $applied_fields;
    return new WP_Error(
        $error->get_error_code(),
        sprintf(
            '%s The %s change was applied before the template conversion failed.',
            $error->get_error_message(),
            implode(' and ', $applied_fields),
        ),
        $data,
    );
}

/**
 * @param array<string, mixed> $input
 * @param list<array<string, mixed>> $elements
 */
function el_template_apply_type_conversion(int $template_id, array $input, array $elements): ?WP_Error
{
    if (!array_key_exists('type', $input)) {
        return null;
    }

    $type = is_string($input['type']) ? trim($input['type']) : '';
    if (el_stored_template_type($template_id) === $type && el_library_type_term_slugs($template_id) === [$type]) {
        return null;
    }

    $converted = el_write_page($template_id, $elements, $type);
    return $converted instanceof WP_Error ? $converted : null;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function elementor_delete_template(array $input): array|WP_Error
{
    $post = el_template_or_error(el_template_id_arg($input));
    if ($post instanceof WP_Error) {
        return $post;
    }

    $force = ($input['force'] ?? false) === true;
    if (!$force && $post->post_status === 'trash') {
        return el_write_target_error(
            'elementor_template_already_trashed',
            sprintf(
                'Elementor template %d is already in the trash. Pass force: true to delete it permanently, or restore it first.',
                $post->ID,
            ),
            ['template_id' => $post->ID],
            status: 409,
        );
    }

    $type = el_stored_template_type($post->ID);
    $keeps_trash = !defined('EMPTY_TRASH_DAYS') || (int) constant('EMPTY_TRASH_DAYS') > 0;
    $permanent = $force || !$keeps_trash;
    $deleted = $permanent ? wp_delete_post($post->ID, force_delete: true) : wp_trash_post($post->ID);
    if (!$deleted instanceof WP_Post) {
        return el_write_target_error(
            'elementor_template_delete_failed',
            sprintf('WordPress refused to %s Elementor template %d.', $permanent ? 'delete' : 'trash', $post->ID),
            ['template_id' => $post->ID],
            status: 500,
        );
    }

    // Conditions_Manager listens to wp_trash_post, which covers the normal
    // trash path. WordPress bypasses that action for force deletion (and when
    // EMPTY_TRASH_DAYS is zero), so purge the same cache explicitly there.
    if ($permanent) {
        el_purge_theme_builder_conditions_cache($post->ID);
    }

    return [
        'id' => $post->ID,
        'type' => $type,
        'title' => $post->post_title,
        'previous_status' => $post->post_status,
        'result' => $permanent ? 'deleted' : 'trashed',
    ];
}

/**
 * @param array<string, mixed> $input
 */
function el_template_id_arg(array $input): int
{
    return (int) ($input['template_id'] ?? $input['post_id'] ?? 0);
}

function el_template_or_error(int $template_id): WP_Post|WP_Error
{
    /** @var WP_Post|null $post */
    $post = $template_id > 0 ? get_post($template_id) : null;
    if (!$post instanceof WP_Post) {
        return el_write_target_error(
            'elementor_template_not_found',
            sprintf('Elementor template %d was not found.', $template_id),
            ['template_id' => $template_id],
            status: 404,
        );
    }
    if ($post->post_type !== 'elementor_library') {
        return el_write_target_error(
            'elementor_not_library_template',
            sprintf(
                'Post %d is a "%s" post, not an Elementor library template. Nothing was changed.',
                $post->ID,
                $post->post_type,
            ),
            ['post_id' => $post->ID, 'post_type' => $post->post_type],
        );
    }
    $foreign_error = el_foreign_document_error($post->ID);
    if ($foreign_error !== null) {
        return $foreign_error;
    }
    return $post;
}

/**
 * Registered foreign document types that do not belong in the template library.
 * Unregistered stored types remain visible so a template whose provider is
 * inactive can still be inspected. Registered content types stored on the
 * wrong post type remain visible so they can be repaired.
 *
 * @return list<string>
 */
function el_foreign_library_document_types(): array
{
    return array_values(array_filter(
        array_keys(el_registered_document_types()),
        static fn(string $type): bool => el_is_foreign_document_type('elementor_library', $type),
    ));
}

/**
 * @return array<string, mixed>
 */
function el_template_list_type_query_args(string $type): array
{
    if ($type !== '') {
        return ['meta_key' => '_elementor_template_type', 'meta_value' => $type];
    }
    $excluded_types = el_foreign_library_document_types();
    if ($excluded_types === []) {
        return [];
    }
    return [
        'meta_query' => [
            'relation' => 'OR',
            [
                'key' => '_elementor_template_type',
                'compare' => 'NOT EXISTS',
            ],
            [
                'key' => '_elementor_template_type',
                'value' => $excluded_types,
                'compare' => 'NOT IN',
            ],
        ],
    ];
}

function el_template_write_status_error(string $status): ?WP_Error
{
    if (in_array($status, EL_TEMPLATE_WRITE_STATUSES, strict: true)) {
        return null;
    }
    return el_write_target_error(
        'elementor_invalid_template_status',
        sprintf(
            'Template status "%s" is invalid. Accepted write statuses: "%s". Nothing was changed.',
            $status,
            implode('", "', EL_TEMPLATE_WRITE_STATUSES),
        ),
        ['status_value' => $status, 'accepted' => EL_TEMPLATE_WRITE_STATUSES],
    );
}

/**
 * @return array<string, mixed>
 */
function el_template_identity(WP_Post $post): array
{
    return [
        'id' => $post->ID,
        'type' => el_stored_template_type($post->ID),
        'title' => $post->post_title,
        'status' => $post->post_status,
        'edit_url' => el_template_edit_url($post->ID),
    ];
}

/**
 * @return array<string, mixed>
 */
function el_template_summary(WP_Post $post): array
{
    $type = metadata_exists(meta_type: 'post', object_id: $post->ID, meta_key: '_elementor_template_type')
        ? el_stored_template_type($post->ID)
        : null;
    $terms = el_template_library_terms($post->ID);
    $type_available = is_string($type) && $type !== '' && array_key_exists($type, el_registered_document_types());
    $type_mismatch = $type === null || count($terms) !== 1 || $terms[0] !== $type;
    if (
        !$type_mismatch
        && $type_available
        && !in_array($type, el_accepted_template_types('elementor_library'), strict: true)
    ) {
        $type_mismatch = true;
    }
    /** @var mixed $raw_conditions */
    $raw_conditions = get_post_meta($post->ID, key: '_elementor_conditions', single: true);
    $conditions_count = is_array($raw_conditions) ? count($raw_conditions) : 0;

    return [
        'id' => $post->ID,
        'title' => $post->post_title,
        'type' => $type,
        'library_term' => implode(',', $terms),
        'status' => $post->post_status,
        'modified' => $post->post_modified,
        'type_available' => $type_available,
        'type_mismatch' => $type_mismatch,
        'conditions_count' => $conditions_count,
    ];
}

/**
 * @return list<string>
 */
function el_template_library_terms(int $template_id): array
{
    $found = get_the_terms($template_id, taxonomy: 'elementor_library_type');
    if (!is_array($found)) {
        return [];
    }
    $terms = [];
    foreach ($found as $term) {
        if ($term->slug === '') {
            continue;
        }
        $terms[] = $term->slug;
    }
    sort($terms);
    return $terms;
}

function el_template_edit_url(int $template_id): string
{
    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;
    /** @var object|null $manager */
    $manager = $plugin->documents ?? null;
    if ($manager instanceof \Elementor\Core\Documents_Manager) {
        $document = $manager->get($template_id);
        if ($document instanceof \Elementor\Core\Base\Document) {
            return (string) $document->get_edit_url();
        }
    }
    return (string) get_edit_post_link($template_id, context: 'raw');
}

/**
 * Elementor Pro removes a template from its Theme Builder conditions cache on
 * wp_trash_post, but WordPress's force-delete path does not fire that action.
 * Reach the same public manager callback after a successful hard delete. Core
 * Elementor cleanup still runs through wp_delete_post's deleted_post hooks.
 */
function el_purge_theme_builder_conditions_cache(int $template_id): void
{
    $cm = dc_conditions_manager();
    if ($cm === null) {
        return;
    }
    $cm->purge_post_from_cache($template_id);
}
