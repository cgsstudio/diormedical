<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

/**
 * Abilities: Manage per-element Bricks interactions — list / add / edit /
 * delete — plus a readonly schema discovery ability that returns the
 * registered trigger and action keys with their per-action field
 * dependencies. Each operation is its own ability so input schemas stay
 * sharp.
 *
 * Storage: element `settings._interactions` is a flat PHP array of
 * associative arrays. Every item carries an `id` (Bricks 6-char hash) plus
 * `trigger`, `action`, and the action-specific keys Bricks' frontend reads
 * from `data-interactions` on the rendered element. The ix_* helpers below
 * host the actual logic.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-list-interactions', [
    'label' => __('List Bricks Interactions', domain: 'novamira-pro'),
    'description' => __(
        'Lists every interaction attached to a single Bricks element. Each interaction is returned as stored (flat assoc shape: id, trigger, action, target, targetSelector, templateId, plus action-specific keys). Call novamira/bricks-list-interaction-events-and-actions first to learn the valid trigger/action keys and their per-action field dependencies.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or template ID containing the element.',
            ],
            'element_id' => [
                'type' => 'string',
                'description' => 'Bricks element id (the 6-char cid shown by list-elements / get-content).',
            ],
            'area' => [
                'type' => 'string',
                'description' => 'Which area to read: "header", "content", or "footer". Defaults to "content".',
                'enum' => ['header', 'content', 'footer'],
                'default' => 'content',
            ],
        ],
        'required' => ['post_id', 'element_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'element_id' => ['type' => 'string'],
            'interactions' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_interactions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/bricks-add-interaction', [
    'label' => __('Add Bricks Interaction', domain: 'novamira-pro'),
    'description' => __(
        'Adds a new interaction to a Bricks element. Required fields in `interaction` are `trigger` (e.g. click, mouseover, enterView, scroll, formSubmit) and `action` (e.g. show, hide, toggleOffCanvas, startAnimation, scrollTo, setAttribute, javascript). Extra fields depend on the action — call novamira/bricks-list-interaction-events-and-actions to learn which. The server assigns the `id`. Values are checked against the control definitions THIS Bricks declares: a trigger or action it does not offer is refused rather than stored (it would never be dispatched), while a field it does not declare is stored as sent with a warning saying it was not checked.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or template ID containing the element.',
            ],
            'element_id' => [
                'type' => 'string',
                'description' => 'Bricks element id (the 6-char cid shown by list-elements / get-content).',
            ],
            'area' => [
                'type' => 'string',
                'description' => 'Which area to write: "header", "content", or "footer". Defaults to "content".',
                'enum' => ['header', 'content', 'footer'],
                'default' => 'content',
            ],
            'interaction' => [
                'type' => 'object',
                'description' => 'The interaction definition. Must include `trigger` and `action`; any other Bricks-native field (target, targetSelector, templateId, animationType, animationDuration, actionAttributeKey, actionAttributeValue, scrollToOffset, jsFunction, runOnce, …) is forwarded as-is. Do not include `id` — the server assigns it.',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Write even when another user currently holds the WordPress edit lock on this post (their builder or editor session is open). Without it such a write is refused with locked_by_other_user naming the user.',
            ],
        ],
        'required' => ['post_id', 'element_id', 'interaction'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'interaction_id' => ['type' => 'string'],
            'verified' => [
                'type' => 'boolean',
                'description' => 'True when the stored element tree read back after the write equals what was written.',
            ],
            'saved' => [
                'type' => 'boolean',
                'description' => 'Deprecated alias of verified, kept for callers that branch on it.',
            ],
            'stored_element_count' => [
                'type' => 'integer',
                'description' => 'How many elements the area holds after the write.',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => 'External CSS file mode: "regenerated" when the post CSS file was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise.',
            ],
            'revision_id' => [
                'type' => ['integer', 'null'],
                'description' => 'The WordPress revision stored before the write, or null when revisions are disabled for the post type (a warning says so).',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Advisories about the write, each naming its next step.',
            ],
            'interaction' => ['type' => 'object'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_add_interaction',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/bricks-edit-interaction', [
    'label' => __('Edit Bricks Interaction', domain: 'novamira-pro'),
    'description' => __(
        'Merges a patch into an existing interaction on a Bricks element. Only the keys present in `patch` are modified. Pass a `null` value for a key to remove that field (useful e.g. to clear `targetSelector` when switching `target` back to self). Re-running the same edit is a no-op (idempotent). The merged result — not the patch alone — is checked against the control definitions this Bricks declares, the same way novamira/bricks-add-interaction checks a new one.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or template ID containing the element.',
            ],
            'element_id' => [
                'type' => 'string',
                'description' => 'Bricks element id (the 6-char cid shown by list-elements / get-content).',
            ],
            'area' => [
                'type' => 'string',
                'description' => 'Which area to write: "header", "content", or "footer". Defaults to "content".',
                'enum' => ['header', 'content', 'footer'],
                'default' => 'content',
            ],
            'interaction_id' => [
                'type' => 'string',
                'description' => 'The id of the interaction to edit (as returned by list-interactions).',
            ],
            'patch' => [
                'type' => 'object',
                'description' => 'Partial interaction to merge in. Null values remove fields. The `id` key is ignored.',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Write even when another user currently holds the WordPress edit lock on this post (their builder or editor session is open). Without it such a write is refused with locked_by_other_user naming the user.',
            ],
        ],
        'required' => ['post_id', 'element_id', 'interaction_id', 'patch'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'interaction' => ['type' => 'object'],
            'verified' => [
                'type' => 'boolean',
                'description' => 'True when the stored element tree read back after the write equals what was written.',
            ],
            'saved' => [
                'type' => 'boolean',
                'description' => 'Deprecated alias of verified, kept for callers that branch on it.',
            ],
            'stored_element_count' => [
                'type' => 'integer',
                'description' => 'How many elements the area holds after the write.',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => 'External CSS file mode: "regenerated" when the post CSS file was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise.',
            ],
            'revision_id' => [
                'type' => ['integer', 'null'],
                'description' => 'The WordPress revision stored before the write, or null when revisions are disabled for the post type (a warning says so).',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Advisories about the write, each naming its next step.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_edit_interaction',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/bricks-delete-interaction', [
    'label' => __('Delete Bricks Interaction', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a single interaction from a Bricks element by its id. Destructive for that interaction only — other interactions on the element and the element itself are untouched. Re-running is safe: a missing id returns success=false with a `not_found` error message.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or template ID containing the element.',
            ],
            'element_id' => [
                'type' => 'string',
                'description' => 'Bricks element id (the 6-char cid shown by list-elements / get-content).',
            ],
            'area' => [
                'type' => 'string',
                'description' => 'Which area to write: "header", "content", or "footer". Defaults to "content".',
                'enum' => ['header', 'content', 'footer'],
                'default' => 'content',
            ],
            'interaction_id' => [
                'type' => 'string',
                'description' => 'The id of the interaction to delete (as returned by list-interactions).',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Write even when another user currently holds the WordPress edit lock on this post (their builder or editor session is open). Without it such a write is refused with locked_by_other_user naming the user.',
            ],
        ],
        'required' => ['post_id', 'element_id', 'interaction_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'verified' => [
                'type' => 'boolean',
                'description' => 'True when the stored element tree read back after the write equals what was written.',
            ],
            'saved' => [
                'type' => 'boolean',
                'description' => 'Deprecated alias of verified, kept for callers that branch on it.',
            ],
            'stored_element_count' => [
                'type' => 'integer',
                'description' => 'How many elements the area holds after the write.',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => 'External CSS file mode: "regenerated" when the post CSS file was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise.',
            ],
            'revision_id' => [
                'type' => ['integer', 'null'],
                'description' => 'The WordPress revision stored before the write, or null when revisions are disabled for the post type (a warning says so).',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Advisories about the write, each naming its next step.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_delete_interaction',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/bricks-list-interaction-events-and-actions', [
    'label' => __('List Bricks Interaction Events And Actions', domain: 'novamira-pro'),
    'description' => __(
        'Schema discovery: returns the registered trigger (event) keys and action keys Bricks exposes, plus the per-field `required` dependencies that describe which extra keys are relevant for each trigger/action combination. A field that holds repeating rows carries its own `fields` map, whose enumerations are checked on write the same way the top-level ones are. Call this once before authoring interactions so you pass valid trigger/action values and the right extra keys.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'triggers' => [
                'type' => 'object',
                'description' => 'Map of trigger key → human label (Bricks-native, matches the builder UI).',
            ],
            'actions' => [
                'type' => 'object',
                'description' => 'Map of action key → human label.',
            ],
            'fields' => [
                'type' => 'object',
                'description' => 'Per-field metadata: type, label, required dependency (if any). Used to decide which extra keys a given trigger/action combination needs.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_interaction_events_and_actions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @var array<string, string>
 */
const NOVAMIRA_BRICKS_INTERACTION_AREAS = [
    'header' => BRICKS_DB_PAGE_HEADER,
    'content' => BRICKS_DB_PAGE_CONTENT,
    'footer' => BRICKS_DB_PAGE_FOOTER,
];

/**
 * Resolve an input `area` to the post-meta key, defaulting to content.
 */
function ix_area_meta_key(mixed $area): ?string
{
    $area = is_string($area) && $area !== '' ? $area : 'content';
    return NOVAMIRA_BRICKS_INTERACTION_AREAS[$area] ?? null;
}

/**
 * Load the flat element array for a given post+area.
 *
 * @return list<array<string, mixed>>|WP_Error
 */
function ix_read_area(int $post_id, string $meta_key): array|WP_Error
{
    /** @var WP_Post|null $post */
    $post = get_post($post_id);
    if (!$post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
    }

    /** @var list<array<string, mixed>> $elements */
    $elements = [];
    /** @var array<array-key, mixed>|string|false $stored */
    $stored = get_post_meta($post_id, $meta_key, single: true);
    if (is_array($stored)) {
        /** @var list<array<string, mixed>> $elements */
        $elements = array_values($stored);
    }
    return $elements;
}

/**
 * Persist a modified element list back to a post+area through the shared
 * write pipeline (no re-signing: interactions never touch code) and return
 * the read-back verification fields for the response.
 *
 * @param list<array<string, mixed>> $elements
 * @param array{force?: bool}        $options
 * @return array{verified: bool, stored_element_count: int, css_file: string, revision_id: int|null, warnings: list<string>}|WP_Error
 */
function ix_write_area(int $post_id, string $meta_key, array $elements, array $options = []): array|WP_Error
{
    $commit = bricks_commit_area_write(
        $post_id,
        bricks_area_from_meta_key($meta_key),
        $elements,
        [],
        [
            'sign' => false,
            'force' => ($options['force'] ?? false) === true,
        ],
    );
    if ($commit instanceof WP_Error) {
        return $commit;
    }

    return [
        'verified' => $commit['verified'],
        'stored_element_count' => $commit['stored_element_count'],
        'css_file' => $commit['css_file'],
        'revision_id' => $commit['revision_id'],
        'warnings' => $commit['warnings'],
    ];
}

/**
 * Adds the write-verification fields (and any read-back warning) to an
 * interaction ability response.
 *
 * @param array<string, mixed> $result
 * @param array{verified: bool, stored_element_count: int, css_file: string, revision_id: int|null, warnings: list<string>} $written
 * @param list<string> $extra_warnings advisories from the installed-definition check
 * @return array<string, mixed>
 */
function ix_with_verification(array $result, array $written, array $extra_warnings = []): array
{
    $result['verified'] = $written['verified'];
    $result['saved'] = $written['verified'];
    $result['stored_element_count'] = $written['stored_element_count'];
    $result['css_file'] = $written['css_file'];
    $result['revision_id'] = $written['revision_id'];
    $warnings = array_merge($written['warnings'], $extra_warnings);
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }
    return $result;
}

/**
 * Locate an element by id in a flat element list. Returns the numeric index
 * or null if missing.
 *
 * @param list<array<string, mixed>> $elements
 */
function ix_find_index(array $elements, string $element_id): ?int
{
    foreach ($elements as $i => $el) {
        if ((string) ($el['id'] ?? '') === $element_id) {
            return $i;
        }
    }
    return null;
}

/**
 * Return the `_interactions` list stored on an element, normalized to a list.
 *
 * @param array<string, mixed> $element
 * @return list<array<string, mixed>>
 */
function ix_read_list(array $element): array
{
    /** @var list<array<string, mixed>> $list */
    $list = [];
    /** @var array<string, mixed> $settings */
    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
    /** @var mixed $raw */
    $raw = $settings['_interactions'] ?? null;
    if (is_array($raw)) {
        /** @var list<array<string, mixed>> $list */
        $list = array_values(array_filter($raw, callback: 'is_array'));
    }
    return $list;
}

/**
 * Write the `_interactions` list back into an element, dropping the key
 * when the list is empty so Bricks does not emit a stray attribute.
 *
 * @param array<string, mixed> $element
 * @param list<array<string, mixed>> $list
 * @return array<string, mixed>
 */
function ix_write_list(array $element, array $list): array
{
    /** @var array<string, mixed> $settings */
    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
    if ($list === []) {
        unset($settings['_interactions']);
        $element['settings'] = $settings;
        return $element;
    }
    $settings['_interactions'] = $list;
    $element['settings'] = $settings;
    return $element;
}

/**
 * Generate a Bricks-style short id for a new interaction. Uses the same
 * helper Bricks uses internally so the ids are indistinguishable from those
 * the builder UI assigns.
 */
function ix_generate_id(): string
{
    if (class_exists(\Bricks\Helpers::class) && method_exists(\Bricks\Helpers::class, 'generate_random_id')) {
        $id = \Bricks\Helpers::generate_random_id(false);
        if ($id !== '') {
            return $id;
        }
    }
    return substr(bin2hex(random_bytes(3)), offset: 0, length: 6);
}

/**
 * Shared entry-point preamble: validate post+element, return the (element,
 * area-meta-key, whole elements list) tuple or an error.
 *
 * @return array{element: array<string, mixed>, meta_key: string, elements: list<array<string, mixed>>, index: int}|WP_Error
 */
function ix_locate(int $post_id, string $element_id, mixed $area_input): array|WP_Error
{
    if ($post_id <= 0) {
        return bricks_error('invalid_input', message: 'Parameter "post_id" is required.');
    }
    if ($element_id === '') {
        return bricks_error('invalid_input', message: 'Parameter "element_id" is required.');
    }

    $meta_key = ix_area_meta_key($area_input);
    if ($meta_key === null) {
        return bricks_error('invalid_input', message: 'Parameter "area" must be one of header, content, footer.');
    }

    $elements = ix_read_area($post_id, $meta_key);
    if ($elements instanceof WP_Error) {
        return $elements;
    }

    $index = ix_find_index($elements, $element_id);
    if ($index === null) {
        return bricks_error('not_found', sprintf(
            "Element '%s' not found in area '%s' on post %d.",
            $element_id,
            is_string($area_input) && $area_input !== '' ? $area_input : 'content',
            $post_id,
        ));
    }

    $element = $elements[$index];

    return [
        'element' => $element,
        'meta_key' => $meta_key,
        'elements' => $elements,
        'index' => $index,
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricks_list_interactions(array $input): array|WP_Error
{
    $located = ix_locate(
        (int) ($input['post_id'] ?? 0),
        (string) ($input['element_id'] ?? ''),
        $input['area'] ?? 'content',
    );
    if ($located instanceof WP_Error) {
        return $located;
    }

    return [
        'element_id' => (string) $located['element']['id'],
        'interactions' => ix_read_list($located['element']),
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricks_add_interaction(array $input): array|WP_Error
{
    $located = ix_locate(
        (int) ($input['post_id'] ?? 0),
        (string) ($input['element_id'] ?? ''),
        $input['area'] ?? 'content',
    );
    if ($located instanceof WP_Error) {
        return $located;
    }

    /** @var mixed $interaction_input */
    $interaction_input = $input['interaction'] ?? null;
    if (!is_array($interaction_input)) {
        return bricks_error('invalid_input', message: 'Parameter "interaction" must be an object.');
    }

    $trigger = (string) ($interaction_input['trigger'] ?? '');
    $action = (string) ($interaction_input['action'] ?? '');
    if ($trigger === '') {
        return bricks_error(
            'invalid_input',
            message: 'Interaction "trigger" is required (e.g. click, mouseover, enterView).',
        );
    }
    if ($action === '') {
        return bricks_error(
            'invalid_input',
            message: 'Interaction "action" is required (e.g. show, hide, toggleOffCanvas).',
        );
    }

    // Drop a user-supplied id — the server assigns it. Strip nulls so the
    // stored array only carries meaningful keys.
    unset($interaction_input['id']);
    $interaction = ['id' => ix_generate_id()];
    /** @var mixed $value */
    foreach ($interaction_input as $key => $value) {
        if ($value === null) {
            continue;
        }
        $interaction[(string) $key] = $value;
    }

    $checked = ix_check_against_installed($interaction);
    if ($checked['errors'] !== []) {
        return bricks_error('unsupported_interaction', implode(' ', $checked['errors']));
    }

    $list = ix_read_list($located['element']);
    $list[] = $interaction;

    $elements = $located['elements'];
    $elements[$located['index']] = ix_write_list($located['element'], $list);
    $written = ix_write_area((int) $input['post_id'], $located['meta_key'], $elements, [
        'force' => ($input['force'] ?? false) === true,
    ]);
    if ($written instanceof WP_Error) {
        return $written;
    }

    return ix_with_verification(
        [
            'interaction_id' => (string) $interaction['id'],
            'interaction' => $interaction,
        ],
        $written,
        $checked['warnings'],
    );
}

/**
 * Position of the interaction with the given id in an element's list, or null.
 *
 * @param list<array<string, mixed>> $list
 */
function ix_find_interaction_index(array $list, string $interaction_id): ?int
{
    foreach ($list as $i => $item) {
        if ((string) ($item['id'] ?? '') === $interaction_id) {
            return $i;
        }
    }
    return null;
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricks_edit_interaction(array $input): array|WP_Error
{
    $located = ix_locate(
        (int) ($input['post_id'] ?? 0),
        (string) ($input['element_id'] ?? ''),
        $input['area'] ?? 'content',
    );
    if ($located instanceof WP_Error) {
        return $located;
    }

    $interaction_id = (string) ($input['interaction_id'] ?? '');
    if ($interaction_id === '') {
        return bricks_error('invalid_input', message: 'Parameter "interaction_id" is required.');
    }

    /** @var mixed $patch */
    $patch = $input['patch'] ?? null;
    if (!is_array($patch)) {
        return bricks_error('invalid_input', message: 'Parameter "patch" must be an object.');
    }

    $list = ix_read_list($located['element']);
    $target_index = ix_find_interaction_index($list, $interaction_id);
    if ($target_index === null) {
        return bricks_error('not_found', sprintf(
            "Interaction '%s' not found on element '%s'.",
            $interaction_id,
            (string) $located['element']['id'],
        ));
    }

    $updated = $list[$target_index];
    /** @var mixed $value */
    foreach ($patch as $key => $value) {
        $key = (string) $key;
        if ($key === 'id') {
            continue; // the id is immutable.
        }
        if ($value === null) {
            unset($updated[$key]);
            continue;
        }
        $updated[$key] = $value;
    }
    // The merged interaction is what gets stored, so that is what is checked.
    $checked = ix_check_against_installed($updated);
    if ($checked['errors'] !== []) {
        return bricks_error('unsupported_interaction', implode(' ', $checked['errors']));
    }

    $list[$target_index] = $updated;

    $elements = $located['elements'];
    $elements[$located['index']] = ix_write_list($located['element'], $list);
    $written = ix_write_area((int) $input['post_id'], $located['meta_key'], $elements, [
        'force' => ($input['force'] ?? false) === true,
    ]);
    if ($written instanceof WP_Error) {
        return $written;
    }

    return ix_with_verification(['interaction' => $updated], $written, $checked['warnings']);
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricks_delete_interaction(array $input): array|WP_Error
{
    $located = ix_locate(
        (int) ($input['post_id'] ?? 0),
        (string) ($input['element_id'] ?? ''),
        $input['area'] ?? 'content',
    );
    if ($located instanceof WP_Error) {
        return $located;
    }

    $interaction_id = (string) ($input['interaction_id'] ?? '');
    if ($interaction_id === '') {
        return bricks_error('invalid_input', message: 'Parameter "interaction_id" is required.');
    }

    $list = ix_read_list($located['element']);
    $filtered = [];
    $deleted = false;
    foreach ($list as $item) {
        if ((string) ($item['id'] ?? '') === $interaction_id) {
            $deleted = true;
            continue;
        }
        $filtered[] = $item;
    }

    if (!$deleted) {
        return bricks_error('not_found', sprintf(
            "Interaction '%s' not found on element '%s'.",
            $interaction_id,
            (string) $located['element']['id'],
        ));
    }

    $elements = $located['elements'];
    $elements[$located['index']] = ix_write_list($located['element'], $filtered);
    $written = ix_write_area((int) $input['post_id'], $located['meta_key'], $elements, [
        'force' => ($input['force'] ?? false) === true,
    ]);
    if ($written instanceof WP_Error) {
        return $written;
    }

    return ix_with_verification(['deleted' => true], $written);
}

/**
 * Checks an interaction against the control definitions the INSTALLED Bricks
 * declares, and reports separately on what was checked and what was not.
 *
 * A trigger or action this build does not offer is stored happily and never
 * dispatched, so a write that reports verified:true is telling the caller
 * something that is not true of the page. Where the installed definition
 * enumerates the allowed values, an unknown one is refused and the message
 * names what this build actually offers.
 *
 * Deliberately narrow. Only fields the installed definition ENUMERATES are
 * judged; a field it declares without options is free-form and passes
 * untouched, and a field it does not declare at all is accepted with a
 * warning that says we could not check it — refusing there would reject
 * anything a fork or a newer build adds, which is the failure mode this
 * whole surface exists to avoid. When the definitions cannot be read the
 * write proceeds exactly as before, and says so.
 *
 * @param  array<array-key, mixed> $interaction
 * @return array{errors: list<string>, warnings: list<string>}
 */
function ix_check_against_installed(array $interaction): array
{
    $fields = ix_installed_fields();
    if ($fields === null) {
        return [
            'errors' => [],
            'warnings' => [
                'This Bricks does not expose its interaction control definitions, so the trigger, action and fields written here were NOT checked against what it supports. Read what the site offers with novamira/bricks-list-interaction-events-and-actions; if it errors, the definitions are unavailable and only the front end can tell you whether the interaction fires.',
            ],
        ];
    }

    $found = ['errors' => [], 'undeclared' => []];
    ix_check_level($interaction, $fields, prefix: '', depth: 0, found: $found);

    $warnings = [];
    if ($found['undeclared'] !== []) {
        $warnings[] = sprintf(
            'Interaction field(s) %s are not declared by this Bricks, so they were stored as sent but NOT checked — this build may ignore them. Compare against novamira/bricks-list-interaction-events-and-actions, and confirm the behaviour on the front end.',
            implode(', ', $found['undeclared']),
        );
    }

    return ['errors' => $found['errors'], 'warnings' => $warnings];
}

/**
 * How deep the walk follows declared repeaters before it stops.
 *
 * Bricks' definitions are plain arrays, so a literal cycle cannot exist in
 * them, but a pathological or hand-built structure must not be able to
 * recurse without end. Reaching the cap is reported rather than passed over
 * in silence — the one thing this check must never do is claim it looked.
 */
const NOVAMIRA_BRICKS_INTERACTION_MAX_DEPTH = 8;

/**
 * Checks one level of an interaction against the definitions for that level,
 * recursing into declared repeaters.
 *
 * The same three-way rule applies at every depth, and it is deliberately
 * conservative — a legitimate nested condition being refused is a worse
 * outcome than a bogus one being stored:
 *   * declared WITH an enumeration + a scalar outside it -> refuse;
 *   * declared as a repeater + an array value -> recurse into each row;
 *   * anything else declared -> pass untouched;
 *   * not declared at all -> pass, recorded so the caller is told it was not
 *     checked.
 *
 * @param array<array-key, mixed>             $values
 * @param array<string, array<string, mixed>>  $fields
 * @param array{errors: list<string>, undeclared: list<string>} $found
 */
function ix_check_level(array $values, array $fields, string $prefix, int $depth, array &$found): void
{
    if ($depth > NOVAMIRA_BRICKS_INTERACTION_MAX_DEPTH) {
        $found['undeclared'][] = $prefix . '(nested deeper than this check follows)';
        return;
    }

    /** @var mixed $value */
    foreach ($values as $key => $value) {
        $key = (string) $key;
        if ($key === 'id' || $value === null || $value === '') {
            continue;
        }
        $path = $prefix . $key;
        if (!array_key_exists($key, $fields)) {
            $found['undeclared'][] = $path;
            continue;
        }
        ix_check_one($value, $fields[$key], $path, $depth, $found);
    }
}

/**
 * One declared key: recurse into a repeater, or judge a scalar against the
 * enumeration when the definition carries one.
 *
 * @param array<string, mixed> $definition
 * @param array{errors: list<string>, undeclared: list<string>} $found
 */
function ix_check_one(mixed $value, array $definition, string $path, int $depth, array &$found): void
{
    $sub_fields = ix_definition_fields($definition);
    if ($sub_fields !== null && is_array($value)) {
        foreach (array_keys($value) as $row_key) {
            /** @var mixed $row */
            $row = $value[$row_key];
            if (!is_array($row)) {
                continue;
            }
            ix_check_level($row, $sub_fields, sprintf('%s[%s].', $path, (string) $row_key), $depth + 1, $found);
        }
        return;
    }

    if (!is_string($value) && !is_int($value)) {
        return;
    }
    $allowed = ix_flatten_select_options(is_array($definition['options'] ?? null) ? $definition['options'] : []);
    if ($allowed === [] || array_key_exists((string) $value, $allowed)) {
        return;
    }
    $found['errors'][] = sprintf(
        'Interaction "%s" value "%s" is not one this Bricks offers. It would be stored and never dispatched. This build accepts: %s. Read them any time with novamira/bricks-list-interaction-events-and-actions.',
        $path,
        (string) $value,
        implode(', ', array_keys($allowed)),
    );
}

/**
 * The sub-field definitions of a declared repeater, or null when this
 * definition does not describe a nested structure.
 *
 * @param  array<string, mixed> $definition
 * @return array<string, array<string, mixed>>|null
 */
function ix_definition_fields(array $definition): ?array
{
    if (!is_array($definition['fields'] ?? null)) {
        return null;
    }

    /** @var array<string, array<string, mixed>> $fields */
    $fields = [];
    foreach (array_keys($definition['fields']) as $name) {
        /** @var mixed $sub */
        $sub = $definition['fields'][$name];
        if (!is_array($sub)) {
            continue;
        }
        /** @var array<string, mixed> $sub */
        $fields[(string) $name] = $sub;
    }

    return $fields === [] ? null : $fields;
}

/**
 * The installed interaction field definitions, or null when this Bricks does
 * not expose them.
 *
 * @return array<string, array<string, mixed>>|null
 */
function ix_installed_fields(): ?array
{
    if (
        !class_exists(\Bricks\Interactions::class) || !method_exists(\Bricks\Interactions::class, 'get_controls_data')
    ) {
        return null;
    }
    try {
        /** @var mixed $controls */
        $controls = \Bricks\Interactions::get_controls_data();
    } catch (\Throwable) {
        return null;
    }
    if (!is_array($controls) || !is_array($controls['fields'] ?? null)) {
        return null;
    }

    /** @var array<string, array<string, mixed>> $fields */
    $fields = [];
    foreach (array_keys($controls['fields']) as $name) {
        /** @var mixed $definition */
        $definition = $controls['fields'][$name];
        if (!is_array($definition)) {
            continue;
        }
        /** @var array<string, mixed> $definition */
        $fields[(string) $name] = $definition;
    }

    return $fields === [] ? null : $fields;
}

/**
 * @return array<string, mixed>|WP_Error
 */
function bricks_list_interaction_events_and_actions(): array|WP_Error
{
    if (!class_exists(\Bricks\Interactions::class)) {
        return bricks_error('not_available', message: 'Bricks Interactions are not available in this environment.');
    }

    $controls = \Bricks\Interactions::get_controls_data();
    /** @var array<string, array<string, mixed>> $fields */
    $fields = is_array($controls['fields'] ?? null) ? $controls['fields'] : [];

    return [
        'triggers' => ix_field_options($fields, key: 'trigger'),
        'actions' => ix_field_options($fields, key: 'action'),
        'fields' => ix_field_meta($fields),
    ];
}

/**
 * Extract a flat `key => label` map from one of the repeater's select fields.
 *
 * @param array<string, array<string, mixed>> $fields
 * @return array<string, string>
 */
function ix_field_options(array $fields, string $key): array
{
    $field = is_array($fields[$key] ?? null) ? $fields[$key] : [];
    $options = is_array($field['options'] ?? null) ? $field['options'] : [];
    return ix_flatten_select_options($options);
}

/**
 * Build the per-field meta map: every non-trigger/action field with its
 * type, label, description, `required` dependency, and options.
 *
 * @param array<string, array<string, mixed>> $fields
 * @return array<string, array<string, mixed>>
 */
function ix_field_meta(array $fields): array
{
    $skip = ['id' => true, 'trigger' => true, 'action' => true, 'conditionsSep' => true];

    $meta = [];
    foreach ($fields as $name => $def) {
        if (array_key_exists($name, $skip)) {
            continue;
        }
        $meta[$name] = ix_describe_field($def);
    }
    return $meta;
}

/**
 * @param array<string, mixed> $def
 * @return array<string, mixed>
 */
function ix_describe_field(array $def): array
{
    $entry = [
        'type' => is_string($def['type'] ?? null) ? $def['type'] : 'text',
    ];
    if (is_string($def['label'] ?? null) && $def['label'] !== '') {
        $entry['label'] = $def['label'];
    }
    $description = ix_pick_description($def);
    if ($description !== null) {
        $entry['description'] = $description;
    }
    if (is_array($def['required'] ?? null)) {
        $entry['required'] = $def['required'];
    }
    if (is_array($def['options'] ?? null)) {
        $entry['options'] = ix_flatten_select_options($def['options']);
    }
    // A repeater's own fields, because its rows are checked against them on
    // write: without this an agent cannot learn the values it must send, and
    // being refused for a value the discovery call never showed is a bad
    // trade for the check being there.
    $nested = ix_definition_fields($def);
    if ($nested !== null) {
        $described = [];
        foreach ($nested as $name => $definition) {
            $described[$name] = ix_describe_field($definition);
        }
        $entry['fields'] = $described;
    }
    return $entry;
}

/**
 * @param array<string, mixed> $def
 */
function ix_pick_description(array $def): ?string
{
    /** @var mixed $desc */
    $desc = $def['desc'] ?? null;
    if (is_string($desc) && $desc !== '') {
        return $desc;
    }
    /** @var mixed $description */
    $description = $def['description'] ?? null;
    return is_string($description) && $description !== '' ? $description : null;
}

/**
 * Collapse a Bricks select-options map — which interleaves `*GroupTitle` keys
 * with real options — into a flat `key => label` map. Group titles are
 * dropped; they carry no value for callers writing interactions.
 *
 * @param array<array-key, mixed> $options
 * @return array<string, string>
 */
function ix_flatten_select_options(array $options): array
{
    $flat = [];
    /** @var mixed $label */
    foreach ($options as $key => $label) {
        $str_key = (string) $key;
        if ($str_key === '' || str_ends_with($str_key, 'GroupTitle')) {
            continue;
        }
        $flat[$str_key] = is_string($label) ? $label : $str_key;
    }
    return $flat;
}
