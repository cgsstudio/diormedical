<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Metabox;

use MB_Relationships_API;
use MBR_Relationship;
use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/metabox-create-relationship', [
    'label' => __('Create Meta Box Relationship', domain: 'novamira-pro'),
    'description' => __(
        'Creates an MB Relationships type. When the MB Relationships builder is active (the mb-relationship CPT, shipped with Meta Box AIO / MB Core) the relationship is persisted as a published mb-relationship post — editable under Meta Box and re-registered every request — like the other metabox-create-* abilities; the response carries source:"db" plus db_post_id. On the free, API-only MB Relationships it falls back to an in-memory register for the current request only (source:"php" plus persistence_warning). Required: id, from, to. from/to take a post type slug or {object_type: post|term|user, post_type/taxonomy}. Optional: reciprocal, label_from, label_to.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Unique relationship id.'],
            'from' => [
                'type' => ['string', 'object'],
                'description' => 'Origin side: a post type slug string, or object {object_type: post|term|user, post_type?/post_types?, taxonomy?}.',
            ],
            'to' => [
                'type' => ['string', 'object'],
                'description' => 'Destination side: a post type slug string, or object {object_type: post|term|user, post_type?/post_types?, taxonomy?}.',
            ],
            'reciprocal' => [
                'type' => 'boolean',
                'description' => 'When both sides are the same type, allow connecting items to each other in both directions.',
            ],
            'label_from' => [
                'type' => 'string',
                'description' => 'Meta-box title on the FROM side (the connected-items box on the origin edit screen).',
            ],
            'label_to' => ['type' => 'string', 'description' => 'Meta-box title on the TO side.'],
        ],
        'required' => ['id', 'from', 'to'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'source' => ['type' => 'string', 'enum' => ['db', 'php']],
            'settings' => ['type' => 'object'],
            'db_post_id' => ['type' => 'integer'],
            'persistence_warning' => ['type' => 'string'],
        ],
        'required' => ['id', 'source', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_create_relationship',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Call metabox-check-setup first. With the relationships builder active the relationship is saved to the database (source:"db", db_post_id returned) and behaves like any UI-created one; only on the free API-only build is it in-memory (source:"php" plus persistence_warning, then needs a PHP register call to persist). Re-running with the same id replaces the previous definition.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/metabox-edit-relationship', [
    'label' => __('Edit Meta Box Relationship', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing MB Relationships type. When the relationships builder is active this patches the DB-backed mb-relationship post in place (source:"db"); on the free API-only build it re-registers in memory for the current request (source:"php"). Supply only the fields to change: from, to, reciprocal, label_from, label_to.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Relationship id to edit.'],
            'from' => [
                'type' => ['string', 'object'],
                'description' => 'New origin side (post type slug or {object_type, post_type/taxonomy}).',
            ],
            'to' => ['type' => ['string', 'object'], 'description' => 'New destination side.'],
            'reciprocal' => ['type' => 'boolean'],
            'label_from' => ['type' => 'string', 'description' => 'Update the FROM side meta-box title.'],
            'label_to' => ['type' => 'string', 'description' => 'Update the TO side meta-box title.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'source' => ['type' => 'string', 'enum' => ['db', 'php']],
            'settings' => ['type' => 'object'],
            'db_post_id' => ['type' => 'integer'],
            'persistence_warning' => ['type' => 'string'],
        ],
        'required' => ['id', 'source', 'settings'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_edit_relationship',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'PATCH semantics — only the supplied fields change. With the builder active the change is persisted to the mb-relationship post; on the API-only build it is in-memory only.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/metabox-delete-relationship', [
    'label' => __('Delete Meta Box Relationship', domain: 'novamira-pro'),
    'description' => __(
        'Permanently deletes all connections for an MB Relationships type. When the builder is active it also deletes the DB-backed mb-relationship post, so the type is fully gone (php_cleanup_required is empty). On the free API-only build the definition is PHP-registered, so php_cleanup_required tells you to remove the register() call from your code.',
        domain: 'novamira-pro',
    ),
    'category' => 'metabox',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'Relationship id to delete.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'id' => ['type' => 'string'],
            'source' => ['type' => 'string', 'enum' => ['db', 'php']],
            'php_cleanup_required' => ['type' => 'string'],
        ],
        'required' => ['deleted', 'id', 'source', 'php_cleanup_required'],
        'additionalProperties' => false,
    ],
    'execute_callback' => __NAMESPACE__ . '\\metabox_delete_relationship',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Destructive — deletes all connection data. With the builder active the mb-relationship post is removed too (php_cleanup_required empty). On the API-only build, also remove the PHP register() call (see php_cleanup_required).',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

const MB_RELATIONSHIP_PERSISTENCE_WARNING = 'MB Relationship definitions are PHP-registered and not persisted in the database. This registration applies to the current request only. Add MB_Relationships_API::register() to your theme or plugin code (or a must-use plugin) to persist it across requests.';

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function metabox_create_relationship(array $args): array|WP_Error
{
    $guard = mb_relationships_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $id_raw */
    $id_raw = $args['id'] ?? null;
    if (!is_string($id_raw) || $id_raw === '') {
        return new WP_Error('metabox_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $id = $id_raw;

    // Builder active → persist as a DB-backed `mb-relationship` post (survives
    // across requests, editable in the MB admin), matching every other
    // metabox-create-* ability. Only the free, API-only MB Relationships falls
    // back to the in-memory register below.
    if (mb_relationships_builder_active()) {
        return mb_relationship_persist_to_db($id, mb_relationship_build_settings($id, $args), existing_post_id: null);
    }

    $register_args = mb_build_relationship_args($id, $args);

    MB_Relationships_API::register($register_args);

    $settings = MB_Relationships_API::get_relationship_settings($id);

    return [
        'id' => $id,
        'source' => 'php',
        'settings' => is_array($settings) ? $settings : $register_args,
        'persistence_warning' => MB_RELATIONSHIP_PERSISTENCE_WARNING,
    ];
}

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function metabox_edit_relationship(array $args): array|WP_Error
{
    $guard = mb_relationships_guard();
    if ($guard !== null) {
        return $guard;
    }

    /** @var mixed $id_raw */
    $id_raw = $args['id'] ?? null;
    if (!is_string($id_raw) || $id_raw === '') {
        return new WP_Error('metabox_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }
    $id = $id_raw;

    // Builder active → patch the DB-backed `mb-relationship` post in place.
    if (mb_relationships_builder_active()) {
        $post_id = mb_relationship_find_post($id);
        if ($post_id === null) {
            return new WP_Error('metabox_not_found', sprintf(
                /* translators: %s: relationship id. */
                __('Relationship "%s" not found.', domain: 'novamira-pro'),
                $id,
            ));
        }
        /** @var mixed $stored */
        $stored = get_post_meta($post_id, key: 'settings', single: true);
        $base = is_array($stored) ? $stored : mb_relationship_build_settings($id, []);
        return mb_relationship_persist_to_db(
            $id,
            mb_relationship_merge_settings($base, $args),
            existing_post_id: $post_id,
        );
    }

    // Load existing settings as a base for PATCH.
    $existing = MB_Relationships_API::get_relationship_settings($id);
    if (!is_array($existing) || $existing === []) {
        return new WP_Error('metabox_not_found', sprintf(
            /* translators: %s: relationship id. */
            __('Relationship "%s" not found.', domain: 'novamira-pro'),
            $id,
        ));
    }

    // Merge supplied from/to on top of existing.
    if (isset($args['from'])) {
        $existing['from'] = $args['from'];
    }
    if (isset($args['to'])) {
        $existing['to'] = $args['to'];
    }

    MB_Relationships_API::register($existing);

    $updated = MB_Relationships_API::get_relationship_settings($id);

    return [
        'id' => $id,
        'source' => 'php',
        'settings' => is_array($updated) ? $updated : $existing,
        'persistence_warning' => MB_RELATIONSHIP_PERSISTENCE_WARNING,
    ];
}

/**
 * @param array{id: string} $args
 * @return array<string, mixed>|WP_Error
 */
function metabox_delete_relationship(array $args): array|WP_Error
{
    $guard = mb_relationships_guard();
    if ($guard !== null) {
        return $guard;
    }

    $id = $args['id'];
    if ($id === '') {
        return new WP_Error('metabox_invalid_input', __('id is required.', domain: 'novamira-pro'));
    }

    // Builder active → delete the connections AND the DB-backed
    // `mb-relationship` post, so the type does not re-register next request.
    if (mb_relationships_builder_active()) {
        if (MB_Relationships_API::get_relationship($id) instanceof MBR_Relationship) {
            mb_delete_all_relationship_connections($id);
        }
        $post_id = mb_relationship_find_post($id);
        if ($post_id !== null) {
            wp_delete_post($post_id, force_delete: true);
        }
        return [
            'deleted' => $post_id !== null,
            'id' => $id,
            'source' => 'db',
            'php_cleanup_required' => '',
        ];
    }

    $settings = MB_Relationships_API::get_relationship_settings($id);
    if (!is_array($settings) || $settings === []) {
        return [
            'deleted' => false,
            'id' => $id,
            'source' => 'php',
            'php_cleanup_required' => 'Relationship not found in the current runtime registry. Remove the MB_Relationships_API::register() call from your PHP code if it still exists.',
        ];
    }

    $relationship = MB_Relationships_API::get_relationship($id);
    if ($relationship instanceof MBR_Relationship) {
        mb_delete_all_relationship_connections($id);
    }

    return [
        'deleted' => true,
        'id' => $id,
        'source' => 'php',
        'php_cleanup_required' => 'Connection data has been deleted. Remove the MB_Relationships_API::register() call from your PHP source code to prevent the relationship type from reappearing on the next request.',
    ];
}

/**
 * Delete all connections for a relationship type by iterating from-side objects.
 */
function mb_delete_all_relationship_connections(string $id): void
{
    $connected = MB_Relationships_API::get_connected(['id' => $id]);
    /** @var mixed $item */
    foreach ($connected as $item) {
        if (!is_object($item)) {
            continue;
        }
        /** @var mixed $from_id_raw */
        $from_id_raw = property_exists($item, 'ID') ? $item->ID : null;
        if (!is_int($from_id_raw) && !is_string($from_id_raw)) {
            continue;
        }
        $from_id = $from_id_raw;
        $to_items = MB_Relationships_API::get_connected_to($from_id, relationship_id: $id);
        /** @var mixed $to_item */
        foreach ($to_items as $to_item) {
            if (!is_object($to_item)) {
                continue;
            }
            /** @var mixed $to_id_raw */
            $to_id_raw = property_exists($to_item, 'ID') ? $to_item->ID : null;
            if (is_int($to_id_raw) || is_string($to_id_raw)) {
                MB_Relationships_API::delete($from_id, $to_id_raw, relationship_id: $id);
            }
        }
    }
}

/**
 * Build the args array for MB_Relationships_API::register().
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function mb_build_relationship_args(string $id, array $args): array
{
    $register = ['id' => $id];

    /** @var mixed $from_raw */
    $from_raw = $args['from'] ?? 'post';
    /** @var mixed $to_raw */
    $to_raw = $args['to'] ?? 'post';

    $register['from'] = mb_normalize_relationship_side($from_raw);
    $register['to'] = mb_normalize_relationship_side($to_raw);

    return $register;
}

/**
 * Normalize a from/to value to the shape MB Relationships expects.
 *
 * @return string|array<array-key, mixed>
 */
function mb_normalize_relationship_side(mixed $side): string|array
{
    if (is_string($side)) {
        return $side;
    }
    if (is_array($side)) {
        return $side;
    }
    return 'post';
}

/**
 * Persist a relationship the way the MB Relationships *builder* does — a
 * published `mb-relationship` post carrying the `settings` (UI form shape) and
 * `relationship` (parsed register args) post-meta, which the builder
 * re-registers every request via `mb_relationships_init`. We feed the
 * builder's OWN settings parser (the same one its REST save runs) so the
 * stored post is identical to a UI-created one: it registers correctly AND
 * stays editable in the Meta Box admin. Gated by mb_relationships_builder_active().
 *
 * @param array<array-key, mixed> $settings
 * @return array<string, mixed>|WP_Error
 */
function mb_relationship_persist_to_db(string $id, array $settings, ?int $existing_post_id): array|WP_Error
{
    $post_id = $existing_post_id;
    if ($post_id === null) {
        $inserted = wp_insert_post([
            'post_type' => 'mb-relationship',
            'post_status' => 'auto-draft',
            'post_title' => $id,
        ], wp_error: true);
        if ($inserted instanceof WP_Error) {
            return $inserted;
        }
        $post_id = (int) $inserted;
    }

    // Persist through the builder's own REST save handler
    // (mbb/relationships/save) rather than re-implementing its settings parser:
    // the handler runs the exact same parser the admin UI uses, so the stored
    // post is identical to a UI-created relationship (registers correctly AND
    // stays editable). Going through core REST also keeps this free of any
    // compile-time dependency on the premium builder classes.
    $request = new \WP_REST_Request('POST', '/mbb/relationships/save');
    $request->set_body_params([
        'post_id' => $post_id,
        'post_title' => $id,
        'settings' => $settings,
    ]);
    $response = rest_do_request($request);
    if ($response->is_error()) {
        $error = $response->as_error();
        return $error instanceof WP_Error
            ? $error
            : new WP_Error('metabox_relationship_save_failed', __(
                'Saving the relationship failed.',
                domain: 'novamira-pro',
            ));
    }

    // Register the saved definition in-memory for THIS request too. The
    // persisted `mb-relationship` post only re-registers on the next request
    // (via the `mb_relationships_init` hook), so without this a create- (or
    // edit-) then-connect within the same request would not find it. The
    // `relationship` post-meta holds the exact args the builder's parser feeds
    // to MB_Relationships_API::register(), so this matches the next-request
    // registration precisely.
    /** @var mixed $register */
    $register = get_post_meta($post_id, key: 'relationship', single: true);
    if (is_array($register) && $register !== []) {
        /** @var array<string, mixed> $register_args */
        $register_args = $register;
        MB_Relationships_API::register($register_args);
    }

    /** @var mixed $stored */
    $stored = get_post_meta($post_id, key: 'settings', single: true);
    return [
        'id' => $id,
        'source' => 'db',
        'db_post_id' => $post_id,
        'settings' => is_array($stored) ? $stored : $settings,
    ];
}

/**
 * Build the verbose `settings` shape the builder's parser consumes from the
 * agent-facing input. Covers from/to (object_type + post_type/taxonomy),
 * reciprocal, and per-side labels (stored as the side meta-box title). The
 * `meta_box` and `field` keys must be present on each side — the parser reads
 * them unconditionally.
 *
 * @param array<string, mixed> $args
 * @return array<string, mixed>
 */
function mb_relationship_build_settings(string $id, array $args): array
{
    /** @var mixed $label_from */
    $label_from = $args['label_from'] ?? null;
    /** @var mixed $label_to */
    $label_to = $args['label_to'] ?? null;

    $settings = [
        'id' => $id,
        'from' => mb_relationship_side_settings($args['from'] ?? 'post', is_string($label_from) ? $label_from : null),
        'to' => mb_relationship_side_settings($args['to'] ?? 'post', is_string($label_to) ? $label_to : null),
    ];
    if (array_key_exists('reciprocal', $args)) {
        $settings['reciprocal'] = mb_relationship_truthy($args['reciprocal']);
    }
    return $settings;
}

/**
 * Merge an edit's supplied fields onto the existing verbose settings of a
 * DB-backed relationship. Only keys the agent provided are touched; a bare
 * `label_from` / `label_to` (no new `from` / `to`) updates just that side's
 * meta-box title.
 *
 * @param array<array-key, mixed> $existing
 * @param array<string, mixed> $args
 * @return array<array-key, mixed>
 */
function mb_relationship_merge_settings(array $existing, array $args): array
{
    $existing = mb_relationship_merge_side($existing, side: 'from', args: $args, label: $args['label_from'] ?? null);
    $existing = mb_relationship_merge_side($existing, side: 'to', args: $args, label: $args['label_to'] ?? null);
    if (array_key_exists('reciprocal', $args)) {
        $existing['reciprocal'] = mb_relationship_truthy($args['reciprocal']);
    }
    return $existing;
}

/**
 * Apply an edit to one side ('from' or 'to'): replace it outright when a new
 * side value is supplied, otherwise just refresh its meta-box title from the
 * label. Split out of mb_relationship_merge_settings to keep each function
 * under the cyclomatic-complexity threshold.
 *
 * @param array<array-key, mixed> $existing
 * @param array<string, mixed> $args
 * @return array<array-key, mixed>
 */
function mb_relationship_merge_side(array $existing, string $side, array $args, mixed $label): array
{
    if (isset($args[$side])) {
        $existing[$side] = mb_relationship_side_settings($args[$side], is_string($label) ? $label : null);
        return $existing;
    }
    if (is_string($label) && $label !== '' && is_array($existing[$side] ?? null)) {
        $existing[$side]['meta_box'] = ['title' => $label];
    }
    return $existing;
}

/**
 * Normalize one from/to side into the verbose settings shape. Accepts a post
 * type slug string or an object {object_type, post_type(s)/taxonomy}.
 *
 * @return array<string, mixed>
 */
function mb_relationship_side_settings(mixed $side, ?string $label): array
{
    $object_type = 'post';
    $post_type = 'post';
    $taxonomy = 'category';

    if (is_string($side) && $side !== '') {
        $post_type = $side;
    }
    if (is_array($side)) {
        /** @var mixed $ot */
        $ot = $side['object_type'] ?? null;
        if (is_string($ot) && $ot !== '') {
            $object_type = $ot;
        }
        $post_type = mb_relationship_first_string($side['post_types'] ?? $side['post_type'] ?? null, $post_type);
        $taxonomy = mb_relationship_first_string($side['taxonomies'] ?? $side['taxonomy'] ?? null, $taxonomy);
    }

    // The parser reads `meta_box` + `field` on every side, so they must exist.
    $settings = ['object_type' => $object_type, 'meta_box' => [], 'field' => [], 'admin_column' => ['enable' => false]];
    if ($object_type === 'term') {
        $settings['taxonomy'] = $taxonomy;
    }
    if ($object_type !== 'term' && $object_type !== 'user') {
        $settings['post_type'] = $post_type;
    }
    if (is_string($label) && $label !== '') {
        $settings['meta_box'] = ['title' => $label];
    }
    return $settings;
}

/**
 * Resolve a post-type / taxonomy value that may arrive as a slug string or a
 * list of slugs to the first usable slug, falling back to $default.
 */
function mb_relationship_first_string(mixed $value, string $default): string
{
    if (is_string($value) && $value !== '') {
        return $value;
    }
    if (is_array($value) && isset($value[0]) && is_string($value[0]) && $value[0] !== '') {
        return $value[0];
    }
    return $default;
}

/**
 * Coerce a mixed value (a relaxed JSON boolean) to a real bool without a
 * `(bool)` cast on `mixed`, which the static analyzer flags.
 */
function mb_relationship_truthy(mixed $value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 'true';
}

/**
 * Find the published `mb-relationship` post that carries a given relationship
 * id (matched on its `relationship` meta `id`, falling back to the post slug).
 * Returns null when the builder is inactive or no post exists.
 */
function mb_relationship_find_post(string $id): ?int
{
    if (!mb_relationships_builder_active()) {
        return null;
    }
    /** @var mixed $posts */
    $posts = get_posts([
        'post_type' => 'mb-relationship',
        'post_status' => ['publish', 'draft'],
        'numberposts' => -1,
        'fields' => 'ids',
    ]);
    if (!is_array($posts)) {
        return null;
    }
    $slug = sanitize_title($id);
    /** @var mixed $post_id */
    foreach ($posts as $post_id) {
        if (!is_int($post_id)) {
            continue;
        }
        /** @var mixed $rel */
        $rel = get_post_meta($post_id, key: 'relationship', single: true);
        $rel_id = is_array($rel) && is_string($rel['id'] ?? null) ? $rel['id'] : '';
        if ($rel_id === $id || get_post_field(field: 'post_name', post: $post_id) === $slug) {
            return $post_id;
        }
    }
    return null;
}
