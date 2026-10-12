<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Elementor;

use WP_Error;

use const ELEMENTOR_VERSION;

/**
 * Shared helpers for Elementor abilities — read/write the document tree,
 * normalize elements (auto-generate IDs), and trigger CSS regeneration.
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Breakpoint id of the base (unconditional) style variant, as persisted.
 *
 * Elementor's atomic styles manager registers "desktop" as the canonical base
 * breakpoint (`Atomic_Styles_Manager::DEFAULT_BREAKPOINT`, also
 * `Breakpoints_Manager::BREAKPOINT_KEY_DESKTOP`) and its `Style_Parser::validate_meta()`
 * rejects a variant whose `meta.breakpoint` is not a string
 * ("missing_or_invalid_value"). The frontend renders a null breakpoint fine
 * because `group_by_breakpoint()` maps null onto "desktop", but the v4 editor
 * validates every persisted variant through the parser and flags a null base as
 * a broken/duplicated style origin. Every write path that synthesizes a base
 * variant must therefore persist this string — null is accepted on INPUT and
 * normalized, never written.
 */
const EL_BASE_BREAKPOINT = 'desktop';

/**
 * Error message for a legacy `container` element on an installation where the
 * container element type is not registered (the Elementor container feature is
 * inactive). Shared by the single-element add path and the tree validator so
 * both surfaces report the same reason.
 */
const EL_CONTAINER_UNAVAILABLE_MESSAGE = 'Container element is not available on this Elementor installation.';

/**
 * Separator between a parent id and a child id in the ancestry census keys
 * built by {@see el_tree_ancestry_census}. Known limitation: this is not
 * collision-proof. A JSON string may contain U+001F and set-content persists
 * any non-empty string id, so `(parent "a", id "b<US>c")` and
 * `(parent "a<US>b", id "c")` build the same key, and splitting a key at its
 * first separator can name the wrong id in `moved_ids`. Neither can make an
 * unchanged write fail — the two trees are censused the same way, so equal
 * input still produces equal keys. Hardening would mean an unambiguous tuple
 * encoding, nested maps, or length-prefixing.
 */
const EL_ANCESTRY_SEPARATOR = "\x1F";

/**
 * Generate a 7-character hex ID, matching the format Elementor uses for elements.
 */
function el_generate_id(): string
{
    return substr(bin2hex(random_bytes(4)), offset: 0, length: 7);
}

/**
 * Read and decode `_elementor_data` for a post.
 *
 * @return array{0: list<array<string, mixed>>, 1: null}|array{0: null, 1: string}
 */
function el_read_page(int $post_id): array
{
    /** @var mixed $raw */
    $raw = get_post_meta($post_id, key: '_elementor_data', single: true);
    if (!is_string($raw) || $raw === '') {
        return [[], null];
    }

    /** @var mixed $decoded */
    $decoded = json_decode($raw, associative: true);
    if (!is_array($decoded)) {
        return [null, 'Failed to parse Elementor data.'];
    }

    /** @var list<array<string, mixed>> $decoded */
    return [array_values($decoded), null];
}

/**
 * Read the Elementor tree for a post, treating an empty document as an
 * error. Use this from abilities that mutate existing content (dynamic
 * tags, global classes, interactions) where "no data yet" is not a
 * valid starting state.
 *
 * @return array{0: list<array<string, mixed>>, 1: null}|array{0: null, 1: string}
 */
function el_read_page_required(int $post_id): array
{
    [$elements, $error] = el_read_page($post_id);
    if ($elements === null) {
        return [null, $error ?? 'Unknown error.'];
    }
    if ($elements === []) {
        return [null, "No Elementor data found for page {$post_id}."];
    }
    return [$elements, null];
}

/**
 * A write-target refusal from el_write_page(), with its own error code repeated
 * at the front of the message.
 *
 * The code is already the WP_Error's code and reaches a REST caller in the
 * `code` field alongside the status. It is spelled into the text as well
 * because the MCP transport does not carry either: the adapter reduces a
 * WP_Error to `get_error_message()` (`ExecuteAbilityAbility`, and again in
 * `ToolsHandler`) and hands the agent one text block with `isError: true`, no
 * code, no status, no structured data. Agents reach these abilities over MCP,
 * and the skills tell them to recognise the code — so when the text is the only
 * thing that survives, the text has to carry what they are told to look for.
 *
 * @param array<string, mixed> $data
 */
function el_write_target_error(string $code, string $message, array $data, int $status = 400): WP_Error
{
    return new WP_Error($code, $code . ': ' . $message, ['status' => $status] + $data);
}

/**
 * Elementor's document type registry: type name => document class. Empty when
 * the documents manager is not available.
 *
 * @return array<string, class-string<\Elementor\Core\Base\Document>>
 */
function el_registered_document_types(): array
{
    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;
    /** @var object|null $manager */
    $manager = $plugin->documents ?? null;
    if (!$manager instanceof \Elementor\Core\Documents_Manager) {
        return [];
    }
    /** @var array<array-key, mixed> $registered */
    $registered = $manager->get_document_types();
    $types = [];
    foreach (array_keys($registered) as $type) {
        /** @var mixed $class */
        $class = $registered[$type];
        if (!is_string($class) || !class_exists($class)) {
            continue;
        }
        /** @var class-string<\Elementor\Core\Base\Document> $class */
        $types[(string) $type] = $class;
    }
    return $types;
}

/**
 * The template types Elementor accepts for a post of this post type, mirroring
 * Elementor's own rules:
 *
 *  - an `elementor_library` post takes a registered type whose document class
 *    belongs to the library alone — the check Source_Local::is_valid_template_type()
 *    applies before saving a template, whose filter may refuse a type but never
 *    admit one of another post type (el_is_library_template_cpt()) — and that
 *    Elementor registers as a library template type: its `register_type`
 *    property, which Documents_Manager::register_document_type() reads to call
 *    Source_Local::add_template_type(), is true. That excludes the page and post
 *    documents ("wp-page", "wp-post") and the "not-supported" placeholder
 *    (register_type false), and keeps types that are not opened in the editor
 *    but are real templates, such as Elementor Pro's global widget ("widget");
 *  - any other post type takes the registered types whose document class
 *    names that post type ("wp-page" for pages, "wp-post" for posts, a
 *    landing page type for landing pages, …);
 *  - a post type no document class names is opened by Elementor as a generic
 *    content document, so it takes "wp-page" and "wp-post".
 *
 * `register_type` only matters for the library: Elementor's page and post
 * documents never set it.
 *
 * @return list<string>
 */
function el_accepted_template_types(string $post_type): array
{
    $accepted = [];
    foreach (el_registered_document_types() as $type => $class) {
        /** @var mixed $cpt */
        $cpt = $class::get_property('cpt');
        if (!is_array($cpt)) {
            continue;
        }
        $valid = $post_type === 'elementor_library'
            ? $class::get_property('register_type') === true && el_is_library_template_cpt($cpt)
            : in_array($post_type, $cpt, strict: true);
        if (!$valid) {
            continue;
        }
        $accepted[] = $type;
    }

    if ($accepted !== [] || $post_type === 'elementor_library') {
        return $accepted;
    }

    return array_values(array_intersect(['wp-page', 'wp-post'], array_keys(el_registered_document_types())));
}

/**
 * Source_Local::is_valid_template_type(), applied to a document class's `cpt`
 * property for an existing library post: the class belongs to the template
 * library and nothing else, and the filter Elementor runs may still refuse it.
 *
 * The filter may only narrow the check, never widen it. Elementor runs it when
 * creating a template, where Documents_Manager::create() puts the post into the
 * document's own post type — so a module can approve a document of another
 * post type there (the floating buttons document, for one). A write here only
 * changes the type meta and term of a post that already is an
 * `elementor_library` post, so a document class that does not name that post
 * type is refused whatever the filter says.
 *
 * @param array<array-key, mixed> $cpt
 */
function el_is_library_template_cpt(array $cpt): bool
{
    $valid = count($cpt) === 1 && in_array('elementor_library', $cpt, strict: true);
    if (!$valid) {
        return false;
    }
    /** @var bool $filtered */
    $filtered = apply_filters('elementor/template_library/sources/local/is_valid_template_type', $valid, $cpt);
    return $filtered === true;
}

/**
 * Template types a write keeps on a post that already has them but never
 * converts a post into, each with the reason given to the caller.
 *
 * "widget" is Elementor Pro's Global Widget. Such a template also needs
 * `_elementor_template_widget_type`, the type of the one widget it holds, which
 * Elementor Pro records only when a widget is saved as global from the editor
 * (through the local template source; Widget::save() does not write it), and
 * Elementor Pro leaves a global widget template without a valid value out of
 * the editor. A document converted here would carry the type without that
 * record, so the conversion is refused rather than producing a global widget
 * that does not work.
 *
 * @return array<string, string>
 */
function el_non_convertible_template_types(): array
{
    return [
        'widget' => 'Global Widgets are created from the Elementor editor, by saving a widget as a global widget; an existing template cannot be converted into one.',
    ];
}

/**
 * The post's stored `_elementor_template_type`, or "" when there is none.
 */
function el_stored_template_type(int $post_id): string
{
    /** @var mixed $stored */
    $stored = get_post_meta($post_id, key: '_elementor_template_type', single: true);
    return is_string($stored) ? $stored : '';
}

/**
 * The template types a new post may be created as or an existing post may be
 * converted into: el_accepted_template_types() for its post type, minus the
 * types a post can keep but not be converted into
 * (el_non_convertible_template_types()) unless the post already has that type.
 * This is also the list error messages offer.
 *
 * @return list<string>
 */
function el_convertible_template_types(string $post_type, string $stored_template_type = ''): array
{
    $blocked = el_non_convertible_template_types();
    $types = [];
    foreach (el_accepted_template_types($post_type) as $type) {
        if (array_key_exists($type, $blocked) && $type !== $stored_template_type) {
            continue;
        }
        $types[] = $type;
    }
    return $types;
}

/**
 * The template types an explicit `template_type` may name for this post.
 *
 * @return list<string>
 */
function el_conversion_template_types(int $post_id): array
{
    return el_convertible_template_types((string) get_post_type($post_id), el_stored_template_type($post_id));
}

/**
 * The refusal for a requested creation/conversion type Elementor would not
 * accept, or null when it is accepted. A null post id means the target has not
 * been created yet; otherwise the stored type remains available so restricted
 * types such as an existing Global Widget can be kept and edited.
 */
function el_template_type_conversion_error(
    string $post_type,
    string $stored_template_type,
    string $template_type,
    ?int $post_id,
): ?WP_Error {
    if ($post_id !== null && el_is_foreign_document_type($post_type, $stored_template_type)) {
        return el_foreign_document_error($post_id);
    }
    $accepted = el_convertible_template_types($post_type, $stored_template_type);
    if (in_array($template_type, $accepted, strict: true)) {
        return null;
    }

    $guidance = $post_id === null
        ? 'Nothing was created.'
        : 'Omit template_type to keep the post\'s current template type. Nothing was changed.';
    $data = ['post_type' => $post_type, 'template_type' => $template_type, 'accepted' => $accepted];
    if ($post_id !== null) {
        $data['post_id'] = $post_id;
    }

    return el_write_target_error(
        'elementor_invalid_template_type',
        sprintf(
            'Template type "%s" %s Accepted template types for this post on this site: %s. %s',
            $template_type,
            el_invalid_template_type_reason($template_type, $post_type),
            $accepted === [] ? '(none)' : '"' . implode('", "', $accepted) . '"',
            $guidance,
        ),
        $data,
    );
}

/**
 * The refusal for an explicit template type Elementor would not accept for this
 * post, or null when it is accepted. Nothing has been written at this point.
 */
function el_invalid_template_type_error(int $post_id, string $template_type): ?WP_Error
{
    return el_template_type_conversion_error(
        (string) get_post_type($post_id),
        el_stored_template_type($post_id),
        $template_type,
        $post_id,
    );
}

/**
 * Why an explicit template type was refused, as one or more full sentences.
 */
function el_invalid_template_type_reason(string $template_type, string $post_type): string
{
    if ($template_type === '') {
        return 'is required.';
    }
    if (!array_key_exists($template_type, el_registered_document_types())) {
        return 'is not a template type registered on this site.';
    }
    if (in_array($template_type, el_accepted_template_types($post_type), strict: true)) {
        $blocked = el_non_convertible_template_types();
        return 'cannot be set on this post. ' . ($blocked[$template_type] ?? '');
    }
    return sprintf('is not a template type Elementor uses for a "%s" post.', $post_type);
}

/**
 * Whether a stored template type names a registered Elementor document that is
 * not a content or template document at all, so no tree write and no type
 * change may touch the post.
 *
 * That is a registered type Elementor does not accept for the post type
 * (el_accepted_template_types()) whose document either holds no element tree
 * (`has_elements` false) or belongs to no post type (`cpt` unset). The site
 * Kit is the case that matters: it is an `elementor_library` post of type
 * "kit" holding the global colors, fonts and site settings, with no elements
 * and no `cpt`, and Kits\Manager only treats a Kit document as the active kit —
 * converted to any template type, the site loses its global styles.
 *
 * A registered content document stored under the wrong post type (a "wp-page"
 * library post or post, left by earlier writes) is not covered: it holds a
 * normal tree, and an explicit accepted type is how it gets repaired. A type
 * that is not registered is el_unavailable_template_type_error()'s case.
 */
function el_is_foreign_document_type(string $post_type, string $stored_template_type): bool
{
    if ($stored_template_type === '') {
        return false;
    }
    $registered = el_registered_document_types();
    if (!array_key_exists($stored_template_type, $registered)) {
        return false;
    }
    if (in_array($stored_template_type, el_accepted_template_types($post_type), strict: true)) {
        return false;
    }
    $class = $registered[$stored_template_type];
    return $class::get_property('has_elements') === false || !is_array($class::get_property('cpt'));
}

/**
 * The refusal for a post whose stored type is a registered Elementor document
 * that is not a content or template document (el_is_foreign_document_type()),
 * or null for any other post. Nothing has been written at this point.
 */
function el_foreign_document_error(int $post_id): ?WP_Error
{
    $post_type = (string) get_post_type($post_id);
    $stored = el_stored_template_type($post_id);
    if (!el_is_foreign_document_type($post_type, $stored)) {
        return null;
    }

    $what = $stored === 'kit'
        ? 'the Elementor Kit that holds the site\'s global colors, fonts and site settings'
        : sprintf('an Elementor "%s" document', $stored);
    $instead = $stored === 'kit'
        ? ' Edit global colors and fonts with the novamira/elementor-*-v3-color, novamira/elementor-*-v3-typography and novamira/elementor-*-variable abilities, and other site settings in Elementor -> Site Settings.'
        : '';
    return el_write_target_error(
        'elementor_not_a_template',
        sprintf(
            'Post %d is %s, not an editable content or template document. Its Elementor content and its template type cannot be written by this ability, and a type change would break it. Nothing was changed.%s',
            $post_id,
            $what,
            $instead,
        ),
        ['post_id' => $post_id, 'post_type' => $post_type, 'template_type' => $stored],
    );
}

/**
 * The post's template type state: the `_elementor_template_type` meta (null
 * when absent) and, for a post whose type carries the `elementor_library_type`
 * taxonomy, its term slugs sorted (null otherwise; el_library_type_term_slugs()).
 *
 * @return array{meta: ?string, terms: ?list<string>}
 */
function el_template_type_snapshot(int $post_id): array
{
    wp_cache_delete($post_id, group: 'post_meta');
    $meta = metadata_exists(meta_type: 'post', object_id: $post_id, meta_key: '_elementor_template_type')
        ? el_stored_template_type($post_id)
        : null;

    return ['meta' => $meta, 'terms' => el_library_type_term_slugs($post_id)];
}

/**
 * The `elementor_library_type` term slugs of a post, sorted; null when its
 * post type does not carry that taxonomy. Elementor associates it with the
 * template library and with the post types of other documents that use its
 * Library trait (landing pages, floating buttons), whose save_template_type()
 * assigns the term as well.
 *
 * @return ?list<string>
 */
function el_library_type_term_slugs(int $post_id): ?array
{
    $post_type = get_post_type($post_id);
    if (!is_string($post_type) || !is_object_in_taxonomy($post_type, taxonomy: 'elementor_library_type')) {
        return null;
    }
    clean_object_term_cache($post_id, object_type: $post_type);
    /** @var array<array-key, mixed>|WP_Error $found */
    $found = wp_get_object_terms($post_id, taxonomies: 'elementor_library_type', args: ['fields' => 'slugs']);
    $terms = [];
    $found = is_array($found) ? $found : [];
    foreach (array_keys($found) as $key) {
        $terms[] = is_string($found[$key]) ? $found[$key] : '';
    }
    sort($terms);
    return $terms;
}

/**
 * After a failed write, put back the template type meta and library term this
 * write changed, and say in the error what state both are in.
 *
 * Ownership is proven per value, never by diffing the whole post: a value is
 * restored only when it differs from the snapshot taken before the write AND
 * is exactly one this write stored (`$written`: only stores made by this
 * write's own calls — the pre-save type and library term, and the raw writer's
 * type — each recorded once that call is proven to have made it; nothing
 * Document::save() stores is recorded). A value someone else changed
 * meanwhile, or one save() changed, is left alone and reported.
 *
 * @param array{meta: ?string, terms: ?list<string>} $before
 * @param array{meta: list<string>, terms: list<string>} $written
 */
function el_restore_template_type_after_failure(int $post_id, array $before, array $written, WP_Error $error): WP_Error
{
    $now = el_template_type_snapshot($post_id);

    $current_meta = $now['meta'];
    if (
        $current_meta !== null
        && $current_meta !== $before['meta']
        && in_array($current_meta, $written['meta'], strict: true)
    ) {
        el_restore_template_type_meta($post_id, $before['meta'], $current_meta);
    }
    if (el_terms_written_by_this_write($now['terms'], $before['terms'], $written['terms'])) {
        wp_set_object_terms($post_id, $before['terms'] ?? [], taxonomy: 'elementor_library_type');
    }

    return el_error_with_type_outcome($error, $before, el_template_type_snapshot($post_id));
}

/**
 * Whether the library term now on the post is one this write stored and
 * differs from the term before the write.
 *
 * @param ?list<string> $current
 * @param ?list<string> $before
 * @param list<string> $written
 */
function el_terms_written_by_this_write(?array $current, ?array $before, array $written): bool
{
    return (
        $current !== null
        && $current !== $before
        && count($current) === 1
        && in_array($current[0], $written, strict: true)
    );
}

/**
 * Put the meta back to its value before the write (or remove it when there was
 * none), only while it still holds the value this write stored.
 */
function el_restore_template_type_meta(int $post_id, ?string $before, string $written): void
{
    if ($before === null) {
        delete_post_meta($post_id, meta_key: '_elementor_template_type', meta_value: $written);
        return;
    }
    update_post_meta($post_id, meta_key: '_elementor_template_type', meta_value: $before, prev_value: $written);
}

/**
 * The failed write's error, with the state of the template type and of the
 * library term appended to its message and added to its data:
 * `template_type` and `library_type_terms` as {before, after} (a null meta
 * means none was stored; null terms mean the post type has no library type
 * taxonomy) and
 * `template_type_unchanged`: whether both are back to their state before the
 * write.
 *
 * @param array{meta: ?string, terms: ?list<string>} $before
 * @param array{meta: ?string, terms: ?list<string>} $after
 */
function el_error_with_type_outcome(WP_Error $error, array $before, array $after): WP_Error
{
    /** @var mixed $data */
    $data = $error->get_error_data();
    $data = is_array($data) ? $data : [];
    $unchanged = $after === $before;
    $data['template_type_unchanged'] = $unchanged;
    $data['template_type'] = ['before' => $before['meta'], 'after' => $after['meta']];
    $data['library_type_terms'] = ['before' => $before['terms'], 'after' => $after['terms']];

    $sentence = $unchanged
        ? ' The post\'s template type and library type were left as they were.'
        : sprintf(
            ' The post\'s template type and library type could not both be put back: template type is %s (was %s), library type is %s (was %s).',
            el_describe_type_meta($after['meta']),
            el_describe_type_meta($before['meta']),
            el_describe_type_terms($after['terms']),
            el_describe_type_terms($before['terms']),
        );
    return new WP_Error($error->get_error_code(), $error->get_error_message() . $sentence, $data);
}

/**
 * A template type meta value for an error message.
 */
function el_describe_type_meta(?string $meta): string
{
    return $meta === null ? 'none' : sprintf('"%s"', $meta);
}

/**
 * Library type term slugs for an error message.
 *
 * @param ?list<string> $terms
 */
function el_describe_type_terms(?array $terms): string
{
    if ($terms === null) {
        return 'not applicable';
    }
    return $terms === [] ? 'none' : '"' . implode('", "', $terms) . '"';
}

/**
 * The refusal for a post whose stored template type is not a document type
 * registered on this site right now, or null when it is (or none is stored).
 *
 * Elementor opens such a post as whatever document its post type falls back
 * to, and saving it through that document stores the fallback type in place of
 * the real one and drops the elements only the missing document type can
 * build, so the template is gone once the plugin providing the type is back.
 * The raw path is refused the same way, so no write to such a document depends
 * on which path the tree happens to take. An explicit, accepted template type
 * is the deliberate way past it.
 */
function el_unavailable_template_type_error(int $post_id): ?WP_Error
{
    $stored = el_stored_template_type($post_id);
    $registered = el_registered_document_types();
    // An empty registry means the registry could not be read, not that no type exists.
    if ($stored === '' || $registered === [] || array_key_exists($stored, $registered)) {
        return null;
    }

    return el_write_target_error(
        'elementor_template_type_unavailable',
        sprintf(
            'Post %d is an Elementor "%s" document, and that template type is not available on this site — commonly because the plugin that provides it (such as Elementor Pro) is inactive. Writing it now would turn it into a different document type and drop the content only that type can hold, so nothing was changed. Reactivate the plugin that provides it, or pass template_type to convert the document to one of the accepted types: %s.',
            $post_id,
            $stored,
            implode(', ', el_conversion_template_types($post_id)),
        ),
        ['post_id' => $post_id, 'template_type' => $stored],
        status: 409,
    );
}

/**
 * The WP_Error for a post whose type cannot hold an Elementor document, or null
 * when it can.
 *
 * The predicate is Elementor's own: `Utils::is_post_type_support()` — the post
 * type exists and carries the `elementor` feature. That is the same structural
 * gate Elementor puts in front of its editor
 * (`User::is_current_user_can_edit_post_type()`), and it describes the site as
 * the site actually is: the post types ticked in Elementor -> Settings ->
 * General -> Post Types, plus every post type a plugin registers with
 * `elementor` among its `supports` or adds the feature to afterwards. A list
 * of post types spelled out here would answer for the sites it was written on
 * and go stale on the next one.
 *
 * `Utils::is_post_support()` — the same check plus two filters — is
 * deliberately NOT used. Those filters carry admin-screen scoping rather than a
 * statement about the data model: Elementor Pro's Global Widget module answers
 * `elementor/utils/is_post_support` with false for an `elementor_library`
 * widget template while the library list table is the current screen, and
 * refusing a legitimate Elementor document is precisely what this guard must
 * not start doing.
 *
 * There is no exemption for a post that already stores a tree. The state that
 * exemption would key on is reachable by anything that can write post meta —
 * an importer, a CLI call, `wp_insert_post()`'s `meta_input`, and Elementor
 * itself through `Db::copy_elementor_meta()` and a revision restore, neither of
 * which looks at the post type — so it would have handed the guard back to
 * whoever tripped it in the first place.
 *
 * Dropping it costs nothing that Elementor does not already refuse. Support is
 * a setting and can be switched off after a page was built, and the case for
 * keeping such a page writable was that it stays live: measured on a page built
 * with Elementor and then un-ticked in Elementor -> Settings -> General -> Post
 * Types, the front end still renders it (`is_built_with_elementor()` does not
 * consult post type support) but `post.php?action=elementor` answers 302 to
 * `edit.php` and `User::is_current_user_can_edit()` — the same
 * `is_post_type_support()` call this guard makes — returns false. Elementor's
 * own editor will not open that page, so writing to it is not the rescue it
 * looked like. Re-ticking the post type restores both at once, which is why the
 * refusal names that screen.
 */
function el_unsupported_post_type_error(int $post_id): ?WP_Error
{
    $post_type = (string) get_post_type($post_id);
    if ($post_type === '' || \Elementor\Utils::is_post_type_support($post_type)) {
        return null;
    }

    return el_write_target_error(
        'elementor_unsupported_post_type',
        sprintf(
            'Post %d has post type "%s"%s, which does not support Elementor, so it cannot hold an Elementor document: Elementor\'s own editor refuses to open a post of this type, and where the post type belongs to another plugin the tree would sit unread beside the content that plugin actually renders, with nothing to clean it up. %s',
            $post_id,
            $post_type,
            el_post_type_label_suffix($post_type),
            el_unsupported_post_type_advice($post_type),
        ),
        ['post_id' => $post_id, 'post_type' => $post_type],
    );
}

/**
 * The post type's registered singular label in parentheses, so the refusal
 * names the post type the way wp-admin shows it to the user and not only by
 * its slug. Empty when the post type registers no label to quote.
 */
function el_post_type_label_suffix(string $post_type): string
{
    $object = get_post_type_object($post_type);
    /** @var mixed $labels */
    $labels = $object->labels ?? null;
    /** @var mixed $singular */
    $singular = is_object($labels) ? $labels->singular_name ?? null : null;
    if (!is_string($singular) || trim($singular) === '') {
        return '';
    }

    return sprintf(' ("%s" in wp-admin)', $singular);
}

/**
 * What to do instead. Dynamic Content for Elementor is named from its own
 * constant, which the specialization already reads from the plugin rather than
 * spelling out, so a rename upstream surfaces as a failure instead of a hint
 * that quietly stops matching. Every other post type gets the general answer,
 * which stays correct for post types nobody here has heard of.
 */
function el_unsupported_post_type_advice(string $post_type): string
{
    $dce_constant = 'Novamira\\Pro\\Abilities\\DynamicContentForElementor\\DCE_TEMPLATE_POST_TYPE';
    if (defined($dce_constant) && $post_type === constant($dce_constant)) {
        return 'This is a Dynamic Content for Elementor HTML Template, which the PDF generator renders from its own HTML; the novamira/dynamic-content-for-elementor-* abilities are the ones that read and edit it. A template that should be laid out in Elementor instead points at a separate Elementor template by id, and that id — not this post — is what the Elementor abilities target.';
    }

    return 'Target a post whose type supports Elementor. When the post type belongs to another plugin, that plugin stores and renders its content its own way and its own novamira abilities are the ones that edit it. When this post type genuinely should be built with Elementor — including a post that was built with it before the post type was un-ticked — it has to be ticked in Elementor -> Settings -> General -> Post Types, which is the site owner\'s call to make; until it is, Elementor\'s own editor will not open such a post either.';
}

/**
 * The refusal for a revision or autosave id, or null for any other post.
 */
function el_revision_target_error(int $post_id): ?WP_Error
{
    // A revision or autosave id splits the two write paths: update_post_meta()
    // redirects a revision id to its parent, so the raw path would silently
    // land on the parent post, while Document::save() writes `_elementor_data`
    // onto the revision row itself (revisions do carry a copy of the document
    // meta). Neither is what a caller targeting a revision id expects, and the
    // timestamp bump / read-back would target the revision row in both cases.
    // Refuse revisions and autosaves before anything is written.
    $parent_id = wp_is_post_revision($post_id);
    if ($parent_id === false) {
        $parent_id = wp_is_post_autosave($post_id);
    }
    if ($parent_id !== false) {
        return el_write_target_error(
            'elementor_revision_target',
            sprintf(
                'Post %d is a revision or autosave of post %d; Elementor content is written on the parent post. Target the parent post id %d instead.',
                $post_id,
                $parent_id,
                $parent_id,
            ),
            ['post_id' => $post_id, 'parent_id' => (int) $parent_id],
        );
    }

    return null;
}

/**
 * Write the document tree to `_elementor_data` and set the supporting meta keys
 * Elementor expects on every editable post.
 *
 * @param list<array<string, mixed>> $elements
 */
function el_write_page(int $post_id, array $elements, ?string $template_type = null): bool|WP_Error
{
    if (!class_exists('Elementor\\Plugin')) {
        return new WP_Error('no_elementor', 'Elementor is not active.', ['status' => 412]);
    }

    // Forget any tree an earlier write of this post read back, so the one
    // el_with_not_persisted() picks up is always this write's own.
    el_take_read_back($post_id);

    $revision = el_revision_target_error($post_id);
    if ($revision !== null) {
        return $revision;
    }

    // A post type that does not accept Elementor documents at all. Writing a
    // tree there produces a document Elementor's own editor will not open, and
    // where the post type belongs to another plugin — the measured case is a
    // Dynamic Content for Elementor HTML Template, laid out in its own HTML
    // meta — it lands beside the content that plugin does render, is never read
    // and is never cleaned up, while the ability reported success. Refuse
    // before anything is persisted.
    $unsupported = el_unsupported_post_type_error($post_id);
    if ($unsupported !== null) {
        return $unsupported;
    }

    // An explicit type is a deliberate conversion and has to be one Elementor
    // accepts for this post; without one, the stored type has to be a document
    // type registered right now. Both checks run before anything is persisted.
    $type_error = el_template_type_write_error($post_id, $template_type);
    if ($type_error !== null) {
        return $type_error;
    }
    $converted_type = el_explicit_template_type($template_type);

    // A Global Widget's tree is one root widget of the type Elementor Pro
    // recorded when it was saved as global. Any other root leaves that record
    // stale and breaks every page that places the widget. Refused before
    // anything is persisted, on both write paths.
    $global_widget = el_global_widget_root_error($post_id, $elements);
    if ($global_widget !== null) {
        return $global_widget;
    }

    // Preserve the post's existing template type when the caller didn't
    // specify one. Overwriting with "wp-page" on every save would reset
    // elementor_library posts (header, footer, container, single-post,
    // etc.) to a plain page type, breaking Theme Builder display rules.
    $template_type = el_resolve_template_type($post_id, $template_type);

    // Both write paths store the template type before the tree is read back,
    // so a write that fails after that point would leave the type changed.
    $type_before = el_template_type_snapshot($post_id);
    $written = ['meta' => [], 'terms' => []];
    try {
        $result = el_write_page_tree($post_id, $elements, $template_type, $converted_type, $written);
    } catch (\Throwable $e) {
        // Whatever threw — this write's own stores or a hook they fired — the
        // caller gets a structured error, and the ledger kept so far still
        // drives the rollback below.
        $result = el_write_page_exception_error($post_id, $e);
    }
    if (!$result instanceof WP_Error) {
        return $result;
    }
    return el_restore_template_type_after_failure($post_id, $type_before, $written, $result);
}

/**
 * The error for a write that stopped with an exception.
 */
function el_write_page_exception_error(int $post_id, \Throwable $e): WP_Error
{
    return new WP_Error(
        'elementor_write_failed',
        sprintf('Writing the Elementor document of post %d stopped with an error: %s', $post_id, $e->getMessage()),
        ['status' => 500, 'post_id' => $post_id],
    );
}

/**
 * The write itself, once el_write_page() has accepted the target and resolved
 * the template type: the raw path for atomic trees and posts without a
 * document, Document::save() otherwise.
 *
 * `$written` collects the template type values this write's own code was
 * observed to store (el_prepare_document_for_write(), el_write_page_raw_as());
 * values Document::save() stores are never recorded. A rollback only touches
 * recorded values.
 *
 * @param list<array<string, mixed>> $elements
 * @param array{meta: list<string>, terms: list<string>} $written
 */
function el_write_page_tree(
    int $post_id,
    array $elements,
    string $template_type,
    ?string $converted_type,
    array &$written,
): bool|WP_Error {
    // Ensure the Atomic Widgets Dynamic Tags module is initialized so that
    // the props-schema filter includes "dynamic" as a valid alternative.
    // Without this, {$$type: "dynamic"} values fail Props_Parser validation.
    el_ensure_dynamic_tags_module();

    // Last line of defence for the persisted base breakpoint (EL_BASE_BREAKPOINT):
    // partial-tree writes (apply-global-class, edit-element, interactions,
    // dynamic tags) re-persist every untouched sibling verbatim, so a legacy
    // null / "" base stored by an older build would otherwise pass straight
    // through. Runs on both the Document and the raw path below.
    /** @var list<array<string, mixed>> $elements */
    $elements = el_normalize_tree_base_breakpoints($elements);

    // Document::save() instantiates every node before persisting it and
    // silently skips the ones it cannot build: an element type that is not
    // registered on this site yields no instance, so the node is dropped from
    // the stored tree while save() still reports success. The drop is
    // registration-driven, not shape-driven — get_data_for_save() preserves an
    // atomic element fine once its type IS registered — and atomic (`e-*`)
    // types are the ones whose registration is conditional. When the tree
    // contains any atomic element, bypass the Document API and write directly
    // to post meta.
    if (el_tree_has_atomic_elements($elements)) {
        return el_write_page_raw_as($post_id, $elements, $template_type, $converted_type, $written);
    }

    // Use the Elementor Document API when available. This runs the full save
    // pipeline: element data normalization (get_data_for_save, which for
    // legacy widgets validates settings), plain text generation for SEO,
    // and all save hooks.
    $document = el_prepare_document_for_write($post_id, $template_type, $converted_type, $written);
    if ($document === null) {
        // Fallback for posts without an existing Elementor document.
        return el_write_page_raw_as($post_id, $elements, $template_type, $converted_type, $written);
    }

    if (!el_save_document_tree($document, $elements)) {
        return el_write_page_raw_as($post_id, $elements, $template_type, $converted_type, $written);
    }

    el_clear_css_cache($post_id);

    return el_finish_document_write($post_id, $elements);
}

/**
 * Save a tree through Elementor's Document::save(), reporting whether the save
 * claims success. False sends the caller to the raw path:
 *
 *  - save() may throw or produce a PHP Error (e.g. calling get_default_args()
 *    on an unexpected array in the elements manager);
 *  - save() returns false on permission checks in non-editor context, and a
 *    document subclass overriding save() without a return value yields null —
 *    neither proves the tree was stored.
 *
 * Nothing save() stores is recorded as this write's. Elementor offers no hook
 * right around save_template_type(): the nearest ones also cover settings and
 * element persistence, their listeners, nested saves started from them, and
 * the version update. A value save() changes therefore stays out of the
 * rollback and is only reported (el_error_with_type_outcome()). A conversion
 * does not depend on it: el_prepare_document_for_write() stores the new type
 * itself before save() runs, and save() then stores the same values again.
 *
 * @param list<array<string, mixed>> $elements
 */
function el_save_document_tree(\Elementor\Core\Base\Document $document, array $elements): bool
{
    try {
        return $document->save(['elements' => $elements]) === true;
    } catch (\Throwable) {
        return false;
    }
}

/**
 * Store a document's own template type ahead of Document::save(): the type
 * meta and, for a document that keeps the library type term (Elementor's
 * Library trait, used beyond the template library too), that term — the same values
 * save_template_type() stores, written by this write's own calls so each can
 * be proven (el_store_type_meta(), el_store_library_type_term()).
 *
 * @param array{meta: list<string>, terms: list<string>} $written
 */
function el_store_document_type(int $post_id, \Elementor\Core\Base\Document $document, array &$written): void
{
    /** @var mixed $name */
    $name = $document->get_name();
    if (!is_string($name) || $name === '') {
        return;
    }
    el_store_type_meta($post_id, $name, $written);
    if (el_document_keeps_library_term($document)) {
        el_store_library_type_term($post_id, $name, $written);
    }
}

/**
 * Whether a document class uses Elementor's Library trait, whose
 * save_template_type() also assigns the `elementor_library_type` term.
 */
function el_document_keeps_library_term(\Elementor\Core\Base\Document $document): bool
{
    $trait = 'Elementor\\Modules\\Library\\Traits\\Library';
    $parents = class_parents($document);
    $classes = [get_class($document), ...($parents === false ? [] : array_values($parents))];
    foreach ($classes as $class) {
        $traits = class_uses($class);
        if ($traits !== false && in_array($trait, $traits, strict: true)) {
            return true;
        }
    }
    return false;
}

/**
 * Store one `_elementor_template_type` value with this write's own
 * update_post_meta() call, and record it into `$written` only when that call is
 * proven to have changed it: its non-false return, or — when a hook fired by
 * the store throws before the call returns — WordPress's own report of that
 * exact store, heard by a listener that runs first and only during the call.
 * Returns whether the call reported a change; a throw propagates.
 *
 * @param array{meta: list<string>, terms: list<string>} $written
 */
function el_store_type_meta(int $post_id, string $value, array &$written): bool
{
    $seen = false;
    $listener = static function (mixed $meta_id, mixed $object_id, mixed $meta_key, mixed $stored) use (
        $post_id,
        $value,
        &$seen,
    ): void {
        if ($object_id === $post_id && $meta_key === '_elementor_template_type' && $stored === $value) {
            $seen = true;
        }
    };
    add_action('added_post_meta', $listener, priority: PHP_INT_MIN, accepted_args: 4);
    add_action('updated_post_meta', $listener, priority: PHP_INT_MIN, accepted_args: 4);
    $changed = null;
    try {
        $changed = update_post_meta($post_id, meta_key: '_elementor_template_type', meta_value: $value) !== false;
    } finally {
        remove_action('added_post_meta', $listener, priority: PHP_INT_MIN);
        remove_action('updated_post_meta', $listener, priority: PHP_INT_MIN);
        if ($changed ?? $seen) {
            el_note_written_type_value($written['meta'], $value);
        }
    }
    return $changed;
}

/**
 * Assign a post's `elementor_library_type` term with this write's own
 * wp_set_object_terms() call (a no-op for a post type without that taxonomy),
 * and record it into `$written` only when that call
 * is proven to have changed the term to `$slug`: its result and a re-read of
 * the term, or — when a hook fired by the assignment throws before the call
 * returns — the `set_object_terms` report of that assignment, heard by a
 * listener that runs first and only during the call. A throw propagates.
 *
 * @param array{meta: list<string>, terms: list<string>} $written
 */
function el_store_library_type_term(int $post_id, string $slug, array &$written): void
{
    $before = el_library_type_term_slugs($post_id);
    if ($before === null || $before === [$slug]) {
        return;
    }
    $seen = false;
    $listener = static function (mixed $object_id, mixed $terms, mixed $tt_ids, mixed $taxonomy) use (
        $post_id,
        $slug,
        &$seen,
    ): void {
        if (
            (int) $object_id === $post_id
            && $taxonomy === 'elementor_library_type'
            && el_library_type_term_slugs($post_id) === [$slug]
        ) {
            $seen = true;
        }
    };
    add_action('set_object_terms', $listener, priority: PHP_INT_MIN, accepted_args: 4);
    $stored = null;
    try {
        $result = wp_set_object_terms($post_id, $slug, taxonomy: 'elementor_library_type');
        $stored = !$result instanceof WP_Error && el_library_type_term_slugs($post_id) === [$slug];
    } finally {
        remove_action('set_object_terms', $listener, priority: PHP_INT_MIN);
        if ($stored ?? $seen) {
            el_note_written_type_value($written['terms'], $slug);
        }
    }
}

/**
 * The caller's template type when it names one, null when it was omitted or
 * empty (both of which keep the stored type).
 */
function el_explicit_template_type(?string $template_type): ?string
{
    return $template_type === '' ? null : $template_type;
}

/**
 * The template type check el_write_page() runs before persisting anything: an
 * explicit type must be one Elementor accepts for the post, and without one the
 * stored type must be registered on this site.
 */
function el_template_type_write_error(int $post_id, ?string $template_type): ?WP_Error
{
    $foreign = el_foreign_document_error($post_id);
    if ($foreign !== null) {
        return $foreign;
    }
    $explicit = el_explicit_template_type($template_type);
    if ($explicit !== null) {
        return el_invalid_template_type_error($post_id, $explicit);
    }
    return el_unavailable_template_type_error($post_id);
}

/**
 * The refusal for a tree that would change what an Elementor Pro Global Widget
 * holds, or null when the post is not a Global Widget or the tree keeps it.
 *
 * Elementor Pro renders every placed copy of a Global Widget from the
 * template's first root node (its `widgetType`, `settings` and, for a nested
 * widget such as Nested Tabs, its child elements), fails with "Original widget
 * type not found." when that type is not a registered widget, and lists the
 * template in the editor by `_elementor_template_widget_type`, which it writes
 * only when a widget is first saved as global. So the tree must stay exactly
 * one root node, a widget of that recorded type; what the widget holds below
 * it is free to change. When the record is missing, the widget type of the
 * stored root stands in for it; when neither exists there is no type to keep
 * and the write is refused rather than picking one.
 *
 * The check keys on the stored type, so it also holds for a write that passes
 * an explicit template_type: converting a Global Widget to another type keeps
 * this root (and then follows the usual conversion rules), and one that
 * replaces the root is refused. It runs after the template type checks, so
 * with Elementor Pro inactive a Global Widget write without a template_type is
 * answered first by the unavailable template type refusal (409) and one naming
 * "widget" by the invalid template type refusal (400); only an explicit
 * conversion to a type accepted there reaches this rule.
 *
 * @param list<array<string, mixed>> $elements
 */
function el_global_widget_root_error(int $post_id, array $elements): ?WP_Error
{
    if (el_stored_template_type($post_id) !== 'widget') {
        return null;
    }

    $expected = el_global_widget_type($post_id);
    if ($expected !== '' && el_single_widget_root_type($elements) === $expected) {
        return null;
    }

    return el_write_target_error(
        'elementor_global_widget_root',
        sprintf(
            'Post %d is an Elementor Pro Global Widget%s. A Global Widget holds exactly one %s; change its settings, or build a different widget from the Elementor editor. Nothing was changed.',
            $post_id,
            $expected === '' ? ' whose widget type is not recorded, so its content cannot be rewritten here' : '',
            $expected === '' ? 'widget, of the type it was saved with' : '"' . $expected . '" widget',
        ),
        ['post_id' => $post_id, 'template_type' => 'widget', 'widget_type' => $expected],
    );
}

/**
 * The widget type a Global Widget template holds: Elementor Pro's
 * `_elementor_template_widget_type` record, else the type of the stored tree's
 * single root widget, else "".
 */
function el_global_widget_type(int $post_id): string
{
    /** @var mixed $recorded */
    $recorded = get_post_meta($post_id, key: '_elementor_template_widget_type', single: true);
    if (is_string($recorded) && $recorded !== '') {
        return $recorded;
    }

    [$stored] = el_read_page($post_id);
    return $stored === null ? '' : el_single_widget_root_type($stored);
}

/**
 * The widget type of a tree made of exactly one root node that is a widget, or
 * "" for any other tree. The root's own children (a nested widget's items) do
 * not matter.
 *
 * @param list<array<string, mixed>> $elements
 */
function el_single_widget_root_type(array $elements): string
{
    if (count($elements) !== 1) {
        return '';
    }
    $root = $elements[0];
    if (($root['elType'] ?? null) !== 'widget') {
        return '';
    }
    /** @var mixed $type */
    $type = $root['widgetType'] ?? null;
    return is_string($type) ? $type : '';
}

/**
 * The Elementor document to save a tree through, with the builder meta and the
 * resolved template type already stored — or null when there is no documents
 * manager or no document, and the caller falls back to the raw path.
 *
 * The cached document was built for the stored type, and save() writes its own
 * type back. After a conversion ($converted_type set) it is rebuilt from the
 * new meta, so the save stores the requested type and, for a library
 * template, its `elementor_library_type` term — stored here, before save(),
 * by el_store_document_type(). Every store here is recorded into `$written`
 * only when this write's own call is proven to have made it.
 *
 * @param array{meta: list<string>, terms: list<string>} $written
 */
function el_prepare_document_for_write(
    int $post_id,
    string $template_type,
    ?string $converted_type,
    array &$written,
): ?\Elementor\Core\Base\Document {
    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;
    /** @var object|null $documents_manager */
    $documents_manager = $plugin->documents ?? null;
    if (!is_object($documents_manager) || !method_exists($documents_manager, 'get')) {
        return null;
    }

    /** @var \Elementor\Core\Base\Document|false|null $document */
    $document = $documents_manager->get($post_id);
    if (!$document instanceof \Elementor\Core\Base\Document) {
        return null;
    }

    update_post_meta($post_id, meta_key: '_elementor_edit_mode', meta_value: 'builder');
    el_store_type_meta($post_id, $template_type, $written);

    if ($converted_type === null || $document->get_name() === $converted_type) {
        return $document;
    }

    /** @var \Elementor\Core\Base\Document|false|null $rebuilt */
    $rebuilt = $documents_manager->get($post_id, false);
    if (!$rebuilt instanceof \Elementor\Core\Base\Document) {
        return null;
    }
    // Store the converted type (and library term) here, where this write can
    // record it, instead of leaving it to save(), whose stores are not recorded.
    el_store_document_type($post_id, $rebuilt, $written);
    return $rebuilt;
}

/**
 * el_write_page_raw(), followed — when the write converted the document to an
 * explicit template type — by letting Elementor store that type the way
 * Document::save() does (el_sync_document_template_type()).
 *
 * The raw writer records only its own type update into `$written`
 * (el_store_type_meta()); a value stored by anyone else before or around it,
 * including listeners of `novamira_pro_elementor_write_page_raw`, is not
 * counted.
 *
 * @param list<array<string, mixed>> $elements
 * @param array{meta: list<string>, terms: list<string>} $written
 */
function el_write_page_raw_as(
    int $post_id,
    array $elements,
    string $template_type,
    ?string $converted_type,
    array &$written,
): bool|WP_Error {
    $result = el_write_page_raw($post_id, $elements, $template_type, $written);
    if ($result === true && $converted_type !== null) {
        el_sync_document_template_type($post_id);
    }
    return $result;
}

/**
 * Record a template type value this write was seen to store.
 *
 * @param list<string> $values
 */
function el_note_written_type_value(array &$values, string $value): void
{
    if (!in_array($value, $values, strict: true)) {
        $values[] = $value;
    }
}

/**
 * Verify a Document::save() write before rebuilding its post CSS.
 *
 * Document::save() reports success even when get_elements_raw_data() skipped
 * elements it could not instantiate, so confirm the stored tree before
 * reporting success.
 *
 * @param list<array<string, mixed>> $elements
 */
function el_finish_document_write(int $post_id, array $elements): bool|WP_Error
{
    $unverified = el_verify_page_write($post_id, $elements);
    if ($unverified !== null) {
        return $unverified;
    }

    el_regenerate_post_css($post_id);
    return true;
}

/**
 * Drop one document's rendered-element HTML cache (`_elementor_element_cache`,
 * resolved through Elementor's own constant by el_element_cache_meta_key()).
 * Post-scoped by construction; shared by every write path and by the targeted
 * global-class rename sweep. Returns true when a cache row actually existed and
 * was deleted.
 */
function el_clear_element_cache(int $post_id): bool
{
    $meta_key = el_element_cache_meta_key();
    if (!metadata_exists(meta_type: 'post', object_id: $post_id, meta_key: $meta_key)) {
        return false;
    }
    return delete_post_meta($post_id, meta_key: $meta_key);
}

/**
 * Whether a variant's `meta.breakpoint` is one the healers may rewrite to the
 * base id: the key is absent, null, or "". Any other value — a real breakpoint
 * name, or a wrong type such as `false` / an int — is left alone (wrong types
 * are rejected at the input boundary instead, see el_find_invalid_breakpoints).
 *
 * @param array<string, mixed> $meta
 */
function el_is_healable_breakpoint(array $meta): bool
{
    if (!array_key_exists('breakpoint', $meta)) {
        return true;
    }
    /** @var mixed $bp */
    $bp = $meta['breakpoint'];
    return $bp === null || $bp === '';
}

/**
 * Input-boundary check shared by add-element, edit-element and set-content:
 * every `variants[*].meta.breakpoint` must be absent / null / "" (the base) or
 * a string. Returns the offending paths mapped to their raw values, e.g.
 * `styles["s-1"].variants[2].meta.breakpoint => false`; `$prefix` is prepended
 * to every path (tree walkers pass the node's position). Empty when everything
 * is acceptable.
 *
 * @param array<string, mixed> $styles
 * @return array<string, mixed>
 */
function el_find_invalid_breakpoints(array $styles, string $prefix = ''): array
{
    $invalid = [];
    foreach (array_keys($styles) as $sid) {
        /** @var mixed $style */
        $style = $styles[$sid];
        if (!is_array($style) || !is_array($style['variants'] ?? null)) {
            continue;
        }
        $variants = $style['variants'];
        foreach (array_keys($variants) as $vi) {
            /** @var mixed $variant */
            $variant = $variants[$vi];
            if (!is_array($variant) || !is_array($variant['meta'] ?? null)) {
                continue;
            }
            /** @var array<string, mixed> $meta */
            $meta = $variant['meta'];
            if (el_is_healable_breakpoint($meta) || is_string($meta['breakpoint'] ?? null)) {
                continue;
            }
            $path = sprintf('%sstyles[%s].variants[%s].meta.breakpoint', $prefix, json_encode($sid), $vi);
            /** @var mixed $value */
            $value = $meta['breakpoint'];
            $invalid[$path] = $value;
        }
    }
    return $invalid;
}

/**
 * Recursively run el_find_invalid_breakpoints() over a RAW element tree
 * (before ids are assigned or anything is normalized), prefixing each path
 * with the node's position: `elements[2].elements[0].styles["s-1"]…`. Children
 * are walked under their existing keys.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_find_invalid_tree_breakpoints(array $node, string $prefix = ''): array
{
    $invalid = [];
    if (is_array($node['styles'] ?? null)) {
        /** @var array<string, mixed> $styles */
        $styles = $node['styles'];
        $invalid = el_find_invalid_breakpoints($styles, $prefix);
    }
    if (!is_array($node['elements'] ?? null)) {
        return $invalid;
    }
    $children = $node['elements'];
    foreach (array_keys($children) as $key) {
        /** @var mixed $child */
        $child = $children[$key];
        if (!is_array($child)) {
            continue;
        }
        /** @var array<string, mixed> $child */
        $invalid += el_find_invalid_tree_breakpoints($child, sprintf('%selements[%s].', $prefix, $key));
    }
    return $invalid;
}

/**
 * Build the fail-hard response for malformed breakpoints found by
 * el_find_invalid_breakpoints() / el_find_invalid_tree_breakpoints(): a compact
 * message listing every offending path with its type, plus a structured
 * `invalid_values` list of `{key, value, expected}` entries — the TYPE-violation
 * variant of the list; enum violations use `{key, value, opts}`. Both share
 * `key` + `value`, and the abilities' output schemas document both variants.
 *
 * @param array<string, mixed> $invalid Path → raw value.
 * @return array<string, mixed>
 */
function el_build_invalid_breakpoint_response(string $context, array $invalid): array
{
    $messages = [];
    $entries = [];
    /** @var mixed $value */
    foreach ($invalid as $path => $value) {
        $messages[] = sprintf('%s is %s.', $path, gettype($value));
        $entries[] = ['key' => $path, 'value' => $value, 'expected' => 'string|null'];
    }
    return [
        'success' => false,
        'error' => sprintf(
            '%s: meta.breakpoint must be a breakpoint name (string) or null for the base variant. %s',
            $context,
            implode(' ', $messages),
        ),
        'invalid_values' => $entries,
    ];
}

/**
 * Input-boundary check shared by set-content and add-element tree mode: a
 * `settings` or `elements` key that is present must hold an array (a JSON
 * object / list), and every entry of `elements` must itself be an element
 * object. Absent keys and JSON null keep their defaults (empty settings, no
 * children) — only wrong types are reported, because the normalizers would
 * otherwise turn a stringified `elements` list into "no children" and a
 * scalar `settings` into an empty map, silently dropping the caller's data.
 * Returns the offending paths mapped to their raw values, e.g.
 * `elements[1].elements => "[…]"`; `$prefix` is prepended to every path.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_find_invalid_tree_shapes(array $node, string $prefix = ''): array
{
    $invalid = [];
    if (array_key_exists('settings', $node) && $node['settings'] !== null && !is_array($node['settings'])) {
        $invalid[$prefix . 'settings'] = $node['settings'];
    }
    if (!array_key_exists('elements', $node) || $node['elements'] === null) {
        return $invalid;
    }
    if (!is_array($node['elements'])) {
        $invalid[$prefix . 'elements'] = $node['elements'];
        return $invalid;
    }
    $children = $node['elements'];
    foreach (array_keys($children) as $key) {
        /** @var mixed $child */
        $child = $children[$key];
        $path = sprintf('%selements[%s]', $prefix, $key);
        if (!is_array($child)) {
            $invalid[$path] = $child;
            continue;
        }
        /** @var array<string, mixed> $child */
        $invalid += el_find_invalid_tree_shapes($child, $path . '.');
    }
    return $invalid;
}

/**
 * Build the WP_Error for wrong-typed `settings` / `elements` entries found by
 * el_find_invalid_tree_shapes(): the message names every offending path with
 * the type received, and the error data carries the same `invalid_values`
 * list of `{key, value, expected}` entries the breakpoint boundary emits.
 *
 * @param array<string, mixed> $invalid Path → raw value.
 */
function el_build_invalid_tree_shape_error(string $context, array $invalid): WP_Error
{
    $messages = [];
    $entries = [];
    /** @var mixed $value */
    foreach ($invalid as $path => $value) {
        $expected = str_ends_with($path, 'elements') ? 'array' : 'object';
        $messages[] = sprintf('%s is %s, expected %s.', $path, gettype($value), $expected);
        $entries[] = ['key' => $path, 'value' => $value, 'expected' => $expected];
    }
    return new WP_Error(
        'invalid_tree_shape',
        sprintf(
            '%s: "elements" must be a JSON array of element objects and "settings" a JSON object — not a string or other scalar; omit the key when there is nothing to set. %s',
            $context,
            implode(' ', $messages),
        ),
        ['status' => 400, 'invalid_values' => $entries],
    );
}

/**
 * Reject a top-level ability input that must be a JSON object but arrived as
 * a scalar. WordPress schema validation accepts "" for `type: object`, and
 * the callbacks used to coerce such values to an empty map — turning a
 * mistyped `settings` into "nothing to change". Absent keys and JSON null
 * still mean "not provided". Returns the first offending key as a WP_Error
 * (code `invalid_<key>`), or null when every listed key is acceptable.
 *
 * @param array<string, mixed> $input
 * @param list<string> $keys
 */
function el_find_non_object_input(array $input, array $keys): ?WP_Error
{
    foreach ($keys as $key) {
        if (!array_key_exists($key, $input) || $input[$key] === null || is_array($input[$key])) {
            continue;
        }
        /** @var mixed $value */
        $value = $input[$key];
        return new WP_Error(
            'invalid_' . $key,
            sprintf('Parameter "%s" must be a JSON object; received %s.', $key, gettype($value)),
            ['status' => 400, 'invalid_values' => [['key' => $key, 'value' => $value, 'expected' => 'object']]],
        );
    }
    return null;
}

/**
 * Run the pipeline's recursive breakpoint normalizer
 * (`el_normalize_style_breakpoints`) over every top-level node of a document
 * tree. Only `styles[*].variants[*].meta.breakpoint` values that are missing /
 * null / "" change (→ EL_BASE_BREAKPOINT); nothing else in the tree is touched —
 * nodes are replaced in place under their existing keys, so key names, order,
 * and non-array entries survive.
 *
 * @param array<array-key, mixed> $elements
 * @return array<array-key, mixed>
 */
function el_normalize_tree_base_breakpoints(array $elements): array
{
    foreach (array_keys($elements) as $key) {
        /** @var mixed $node */
        $node = $elements[$key];
        if (!is_array($node)) {
            continue;
        }
        /** @var array<string, mixed> $node */
        $elements[$key] = el_normalize_style_breakpoints($node);
    }
    return $elements;
}

/**
 * Resolve the template type to persist. Uses the caller's explicit
 * value when provided, otherwise reads the existing post meta to
 * preserve it, falling back to "wp-page" only when the post has no
 * existing template type at all.
 *
 * The `elementor_library_type` term is deliberately not consulted: Elementor
 * resolves a document's type from this meta alone (then its post type map);
 * the taxonomy mirrors the type for library classification and listing.
 */
function el_resolve_template_type(int $post_id, ?string $explicit): string
{
    if ($explicit !== null && $explicit !== '') {
        return $explicit;
    }
    if (!metadata_exists(meta_type: 'post', object_id: $post_id, meta_key: '_elementor_template_type')) {
        return 'wp-page';
    }
    $existing = (string) get_post_meta($post_id, key: '_elementor_template_type', single: true);
    if ($existing !== '') {
        return $existing;
    }
    return 'wp-page';
}

/**
 * Raw write fallback when the Document API is not available or fails
 * (e.g. permission checks in headless/MCP context).
 *
 * @param list<array<string, mixed>> $elements
 * @param array{meta: list<string>, terms: list<string>} $written Receives the raw writer's own
 *        proven type store (el_store_type_meta()).
 */
function el_write_page_raw(
    int $post_id,
    array $elements,
    string $template_type,
    array &$written = ['meta' => [], 'terms' => []],
): bool|WP_Error {
    // Elementor's `elementor/document/save/data` filter runs the interactions
    // Parser to convert `temp-...` ids (the marker for client-side unsaved
    // interactions) into stable, post/element-scoped ids. The raw write path
    // bypasses that filter, so run the Parser here too — otherwise temp ids
    // leak into `_elementor_data` and Elementor treats every load as an
    // unsaved interaction.
    $elements = el_assign_interaction_ids($post_id, $elements);

    $encoded = wp_json_encode($elements);
    if ($encoded === false) {
        return new WP_Error('encode_failed', 'Failed to encode Elementor data.');
    }

    /**
     * Fires immediately before `_elementor_data` is written directly to post
     * meta, bypassing Elementor's Document::save() pipeline. The tree has been
     * normalized and encoded at this point, so the action means "raw write
     * performed" — it does not fire when encoding fails.
     *
     * @param int $post_id The post whose `_elementor_data` is being written.
     */
    do_action('novamira_pro_elementor_write_page_raw', $post_id);

    update_post_meta($post_id, meta_key: '_elementor_data', meta_value: wp_slash($encoded));
    update_post_meta($post_id, meta_key: '_elementor_edit_mode', meta_value: 'builder');
    el_store_type_meta($post_id, $template_type, $written);

    if (defined('ELEMENTOR_VERSION')) {
        update_post_meta($post_id, meta_key: '_elementor_version', meta_value: ELEMENTOR_VERSION);
    }

    el_clear_css_cache($post_id);

    // update_post_meta() returns false both on failure and on an unchanged
    // value, so its result cannot tell the two apart — read the row back.
    $unverified = el_verify_raw_page_write($post_id, $elements);
    if ($unverified !== null) {
        return $unverified;
    }

    // Document::save() goes through wp_update_post(), which advances the
    // post's modified timestamps; this path writes meta only, so bump them
    // here — and only for a write that verified, so a failed write never
    // looks newer than an editor autosave.
    $touched = el_touch_post_modified($post_id);
    if ($touched instanceof WP_Error) {
        return $touched;
    }

    el_regenerate_post_css($post_id);
    return true;
}

/**
 * After an explicit template type was written on the raw path, rebuild the
 * post's document from the new meta and let Elementor store its type the way
 * Document::save() does — for a library template that also moves the
 * `elementor_library_type` term, so the meta and the library listing agree.
 * Rebuilding also replaces the cached document of the old type. It only runs
 * after a verified write, so nothing it stores is ever rolled back.
 */
function el_sync_document_template_type(int $post_id): void
{
    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;
    /** @var object|null $manager */
    $manager = $plugin->documents ?? null;
    if (!$manager instanceof \Elementor\Core\Documents_Manager) {
        return;
    }
    /** @var \Elementor\Core\Base\Document|false|null $document */
    $document = $manager->get($post_id, from_cache: false);
    if (!$document instanceof \Elementor\Core\Base\Document) {
        return;
    }
    $document->save_template_type();
}

/**
 * Advance `post_modified` / `post_modified_gmt` of a post to now after a raw
 * `_elementor_data` write, without wp_update_post(): that would run the save
 * hooks, create a revision and trigger Elementor's own save handlers for a
 * write that already happened. Elementor's editor compares the newest
 * autosave against `post_modified_gmt` (Document::get_newer_autosave()), so a
 * raw write that leaves the timestamp behind is shadowed by any older editor
 * autosave. Returns true when the row was touched (0 affected rows is fine:
 * MySQL reports 0 when the values did not change) and a WP_Error when the
 * update itself failed.
 */
function el_touch_post_modified(int $post_id): bool|WP_Error
{
    global $wpdb;
    /** @var \wpdb $wpdb */

    $modified = current_time('mysql');
    $modified_gmt = current_time('mysql', gmt: true);

    $updated = $wpdb->update(
        $wpdb->posts,
        ['post_modified' => $modified, 'post_modified_gmt' => $modified_gmt],
        ['ID' => $post_id],
        ['%s', '%s'],
        ['%d'],
    );
    if ($updated === false) {
        return new WP_Error(
            'elementor_post_modified_not_updated',
            sprintf(
                'Elementor data for post %d was stored but its modified timestamp could not be updated: %s',
                $post_id,
                $wpdb->last_error !== '' ? $wpdb->last_error : 'database update failed',
            ),
            ['status' => 500, 'post_id' => $post_id],
        );
    }

    // A raw write already fired this while its post CSS was absent. A purge
    // listener may synchronously render there; the eager rebuild after this
    // timestamp update can harmlessly repeat that CSS work.
    clean_post_cache($post_id);
    el_refresh_document_post($post_id, $modified, $modified_gmt);
    return true;
}

/**
 * Propagate freshly written modified timestamps into Elementor's cached
 * Document. `Documents_Manager::get()` keeps one Document per post id and each
 * Document holds its own WP_Post copy taken at construction, so
 * clean_post_cache() alone leaves an already-instantiated Document (and thus
 * get_newer_autosave()) on the old timestamp; re-fetching with
 * `get($id, false)` would only swap the cache entry, not an instance a caller
 * already holds. Update the WP_Post the cached instance exposes in place.
 */
function el_refresh_document_post(int $post_id, string $modified, string $modified_gmt): void
{
    if (!class_exists('Elementor\\Plugin')) {
        return;
    }
    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;
    /** @var object|null $documents_manager */
    $documents_manager = $plugin->documents ?? null;
    if (!is_object($documents_manager) || !method_exists($documents_manager, 'get')) {
        return;
    }
    /** @var mixed $document */
    $document = $documents_manager->get($post_id);
    if (!is_object($document) || !method_exists($document, 'get_post')) {
        return;
    }
    /** @var mixed $post */
    $post = $document->get_post();
    if (!$post instanceof \WP_Post || (int) $post->ID !== $post_id) {
        return;
    }
    $post->post_modified = $modified;
    $post->post_modified_gmt = $modified_gmt;
}

/**
 * Confirm that `_elementor_data` now holds the tree that was just written.
 *
 * Neither write path can trust its own return value: `Document::save()`
 * reports success even when `get_elements_raw_data()` skipped elements it
 * could not instantiate, and `update_post_meta()` returns false both on
 * failure and on an unchanged value — while an `update_post_metadata`
 * short-circuit filter or a stale object cache can leave the row untouched
 * without either path noticing. One meta read after dropping the post's meta
 * cache settles it, and the comparison runs over the WHOLE tree (see
 * {@see el_compare_written_tree}): every node that was sent must still be in
 * the stored tree AND still hang under the same sent parent, id-less nodes
 * included. Extra stored nodes are tolerated on the Document path because
 * `elementor/document/save/data` filters may legitimately inject them —
 * including a wrapper between a sent parent and a sent child, which is why
 * ancestry is measured against the nearest SENT ancestor rather than against
 * a raw depth. The raw write path uses {@see el_verify_raw_page_write}
 * instead, which requires the stored shape to be identical.
 * Returns null when the persisted tree matches, otherwise a WP_Error the
 * caller must surface instead of a success payload.
 *
 * @param list<array<string, mixed>> $elements
 */
function el_verify_page_write(int $post_id, array $elements): ?WP_Error
{
    $comparison = el_read_back_comparison($post_id, $elements);
    if ($comparison instanceof WP_Error) {
        return $comparison;
    }
    if ($comparison['ok']) {
        return null;
    }
    return el_write_unverified_error($post_id, $comparison);
}

/**
 * Same read-back for the raw write path, which requires the stored tree to be
 * structurally identical to the sent one — same nodes, same order, same
 * nesting. Nothing runs between the encode and the meta row there, so any
 * difference at all is a bug rather than a filter's doing.
 *
 * @param list<array<string, mixed>> $elements
 */
function el_verify_raw_page_write(int $post_id, array $elements): ?WP_Error
{
    $comparison = el_read_back_comparison($post_id, $elements);
    if ($comparison instanceof WP_Error) {
        return $comparison;
    }
    if ($comparison['ok'] && $comparison['structure_matches']) {
        return null;
    }
    return el_write_unverified_error($post_id, $comparison, el_raw_write_structure_note($comparison));
}

/**
 * The extra sentence the raw write path adds when nothing else in the message
 * already explains the difference. Drops and relocations have their own notes
 * and name the nodes involved, so they suppress this one; what is left is a
 * node count that does not match, or a shape that does not match while every
 * sent node is present and in place.
 *
 * @param array{found_total: int, expected_total: int, dropped_count: int, moved_count: int, structure_matches: bool, ...} $comparison
 */
function el_raw_write_structure_note(array $comparison): string
{
    if ($comparison['structure_matches'] || $comparison['dropped_count'] > 0 || $comparison['moved_count'] > 0) {
        return '';
    }
    if ($comparison['found_total'] !== $comparison['expected_total']) {
        return ' The raw write path runs no save filters, so the stored node count must match the sent one exactly.';
    }
    return ' The raw write path runs no save filters, so the stored tree must match the sent one exactly, including node order and nesting.';
}

/**
 * Read `_elementor_data` back past the meta cache and compare it against the
 * tree that was sent. Returns the comparison, or the WP_Error for a row that
 * could not be read back at all.
 *
 * @param list<array<string, mixed>> $elements
 * @return array{ok: bool, top_level_matches: bool, structure_matches: bool, expected_top: int, found_top: int, expected_total: int, found_total: int, missing_ids: list<string>, unexpected_ids: list<string>, dropped_ids: list<string>, dropped_count: int, moved_ids: list<string>, moved_count: int}|WP_Error
 */
function el_read_back_comparison(int $post_id, array $elements): array|WP_Error
{
    wp_cache_delete($post_id, group: 'post_meta');
    [$found, $read_error] = el_read_page($post_id);

    if ($found === null) {
        $expected_top = count($elements);
        return new WP_Error(
            'elementor_write_unverified',
            sprintf(
                'Elementor document data was not persisted for post %d: expected %d top-level element(s) but the stored _elementor_data could not be read back (%s).',
                $post_id,
                $expected_top,
                $read_error ?? 'unknown error',
            ),
            [
                // The write went through and the tree is not in the document:
                // a real server-side failure the caller cannot correct.
                'status' => 500,
                'post_id' => $post_id,
                'expected_top_level' => $expected_top,
                'expected_total' => el_count_tree_elements($elements),
            ],
        );
    }

    // The stored tree is also what the not-persisted report compares the sent
    // fields against (el_with_not_persisted()), so it is read only once.
    el_remember_read_back($post_id, $found);

    return el_compare_written_tree($elements, $found);
}

/**
 * Compare the tree that was sent against the tree that came back.
 *
 * Top-level ids are compared as a frequency map (so a duplicate id cannot
 * cancel out against a different stored id) together with the top-level node
 * count. The rest of the decision is the ancestry census: every sent node must
 * still be in the stored tree (an id sent N times must appear at least N
 * times) AND still hang under the same sent parent. Counting alone is not
 * enough in either direction — a child dropped while a differently identified
 * node is injected keeps the totals intact, and reparenting or flattening a
 * subtree keeps every id intact — so `dropped_count` reports the nodes
 * actually missing and `moved_count` the ones stored under a different sent
 * parent, never the net difference. Id-less nodes are counted against their
 * nearest sent ancestor, so they are neither a free pass nor cancelled out by
 * an id-less node under some other parent. `structure_matches` is the exact
 * shape comparison the raw write path needs on top of all this.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<array<string, mixed>> $found
 * @return array{ok: bool, top_level_matches: bool, structure_matches: bool, expected_top: int, found_top: int, expected_total: int, found_total: int, missing_ids: list<string>, unexpected_ids: list<string>, dropped_ids: list<string>, dropped_count: int, moved_ids: list<string>, moved_count: int}
 */
function el_compare_written_tree(array $elements, array $found): array
{
    $expected_counts = el_id_frequency(el_top_level_ids($elements));
    $found_counts = el_id_frequency(el_top_level_ids($found));
    $expected_top = count($elements);
    $found_top = count($found);
    $top_level_matches = $expected_counts === $found_counts && $found_top === $expected_top;

    $sent_ids = el_tree_id_set($elements);
    $shortfalls = el_census_shortfalls(
        el_tree_ancestry_census($elements, $sent_ids),
        el_tree_ancestry_census($found, $sent_ids),
    );

    return [
        'ok' => $top_level_matches && $shortfalls['dropped_count'] === 0 && $shortfalls['moved_count'] === 0,
        'top_level_matches' => $top_level_matches,
        'structure_matches' => el_tree_skeleton($elements) === el_tree_skeleton($found),
        'expected_top' => $expected_top,
        'found_top' => $found_top,
        'expected_total' => el_count_tree_elements($elements),
        'found_total' => el_count_tree_elements($found),
        'missing_ids' => el_ids_short_of($expected_counts, $found_counts),
        'unexpected_ids' => el_ids_short_of($found_counts, $expected_counts),
        'dropped_ids' => $shortfalls['dropped_ids'],
        'dropped_count' => $shortfalls['dropped_count'],
        'moved_ids' => $shortfalls['moved_ids'],
        'moved_count' => $shortfalls['moved_count'],
    ];
}

/**
 * Render the failed read-back into the WP_Error every write path surfaces
 * instead of a success payload.
 *
 * @param array{ok: bool, top_level_matches: bool, structure_matches: bool, expected_top: int, found_top: int, expected_total: int, found_total: int, missing_ids: list<string>, unexpected_ids: list<string>, dropped_ids: list<string>, dropped_count: int, moved_ids: list<string>, moved_count: int} $comparison
 */
function el_write_unverified_error(int $post_id, array $comparison, string $extra_note = ''): WP_Error
{
    $missing_ids = $comparison['missing_ids'];
    $missing_note = $missing_ids === [] ? '' : sprintf(' Missing top-level id(s): %s.', implode(', ', $missing_ids));
    $notes = $missing_note . el_dropped_nodes_note($comparison) . el_moved_nodes_note($comparison) . $extra_note;

    return new WP_Error(
        'elementor_write_unverified',
        sprintf(
            'Elementor document data was not persisted for post %d: expected %d top-level element(s) (%d in total) but the stored _elementor_data contains %d (%d in total).%s The document does not hold the tree that was sent.',
            $post_id,
            $comparison['expected_top'],
            $comparison['expected_total'],
            $comparison['found_top'],
            $comparison['found_total'],
            $notes,
        ),
        [
            // As above: persistence disagreed with what was sent. Nothing for
            // the caller to fix, so this stays a 500.
            'status' => 500,
            'post_id' => $post_id,
            'expected_top_level' => $comparison['expected_top'],
            'found_top_level' => $comparison['found_top'],
            'expected_total' => $comparison['expected_total'],
            'found_total' => $comparison['found_total'],
            'missing_ids' => $missing_ids,
            'unexpected_ids' => $comparison['unexpected_ids'],
            'dropped_ids' => $comparison['dropped_ids'],
            'dropped_count' => $comparison['dropped_count'],
            'moved_ids' => $comparison['moved_ids'],
            'moved_count' => $comparison['moved_count'],
        ],
    );
}

/**
 * The sentence naming how many sent nodes are missing from the stored tree,
 * with the first few ids inline; empty when nothing was dropped.
 *
 * @param array{top_level_matches: bool, dropped_ids: list<string>, dropped_count: int, ...} $comparison
 */
function el_dropped_nodes_note(array $comparison): string
{
    $dropped_count = $comparison['dropped_count'];
    if ($dropped_count === 0) {
        return '';
    }
    $ids_note = el_first_ids_note($comparison['dropped_ids']);
    return $comparison['top_level_matches']
        ? sprintf(
            ' Every top-level element is present, so %d nested element(s) were dropped%s.',
            $dropped_count,
            $ids_note,
        )
        : sprintf(' %d element(s) that were sent are missing from the stored tree%s.', $dropped_count, $ids_note);
}

/**
 * The sentence naming how many sent nodes are stored under a different parent
 * than they were sent under — relocated, not dropped; empty when none were.
 *
 * @param array{moved_ids: list<string>, moved_count: int, ...} $comparison
 */
function el_moved_nodes_note(array $comparison): string
{
    if ($comparison['moved_count'] === 0) {
        return '';
    }
    return sprintf(
        ' %d element(s) that were sent are stored under a different parent%s.',
        $comparison['moved_count'],
        el_first_ids_note($comparison['moved_ids']),
    );
}

/**
 * ` (id(s): a, b, …)` for the first few ids of a list, empty for none.
 *
 * @param list<string> $ids
 */
function el_first_ids_note(array $ids): string
{
    $shown = array_slice($ids, offset: 0, length: 5);
    if ($shown === []) {
        return '';
    }
    return sprintf(' (id(s): %s%s)', implode(', ', $shown), count($ids) > count($shown) ? ', …' : '');
}

/**
 * Two known limitations of the census below, neither of which can make an
 * unchanged write fail:
 *
 * 1. Id-less nodes carry no identity beyond their nearest sent ancestor, so
 *    under the SAME parent they are interchangeable: a sent id-less heading
 *    and a stored id-less button both census as one anonymous node under that
 *    parent and compare equal (the raw path's skeleton comparison does not
 *    distinguish them either). By the same token, an id-less node dropped
 *    under one parent while another is injected under a second parent is
 *    reported as a relocation rather than a drop, because the overall
 *    anonymous count did not change. This is tolerable because every write
 *    surface here assigns ids before persisting and Elementor's own nodes
 *    carry ids, so id-less nodes reach the verifier only through direct calls.
 *    Hardening would mean requiring ids at the verifier boundary, or folding a
 *    limited structural/type signature into the anonymous census key.
 * 2. EL_ANCESTRY_SEPARATOR is not collision-proof (see the constant).
 *
 * Census of a tree keyed by ANCESTRY, not by depth: for every node whose id
 * was sent, one entry under its own id and one under
 * `<nearest sent ancestor><US><id>`; for every id-less node, one entry under
 * its nearest sent ancestor. Nodes whose id was not sent (a filter's injection)
 * are transparent — they are neither counted nor treated as ancestors — so an
 * injected wrapper between a sent parent and a sent child does not look like a
 * move, while re-hanging a sent node under a different sent parent does. All
 * maps are key-sorted so two censuses compare with `===`.
 *
 * @param list<array<string, mixed>> $elements
 * @param array<string, true>        $sent_ids
 * @return array{ids: array<array-key, int>, parented: array<array-key, int>, anonymous: array<array-key, int>}
 */
function el_tree_ancestry_census(array $elements, array $sent_ids, string $parent = ''): array
{
    /** @var array{ids: array<array-key, int>, parented: array<array-key, int>, anonymous: array<array-key, int>} $census */
    $census = ['ids' => [], 'parented' => [], 'anonymous' => []];
    foreach ($elements as $element) {
        /** @var mixed $raw_id */
        $raw_id = $element['id'] ?? null;
        $id = is_string($raw_id) && $raw_id !== '' ? $raw_id : null;
        $tracked = $id !== null && array_key_exists($id, $sent_ids);
        $census = el_count_node_in_census($census, $id, $tracked ? $id : null, $parent);

        /** @var list<array<string, mixed>> $children */
        $children = is_array($element['elements'] ?? null) ? $element['elements'] : [];
        if ($children === []) {
            continue;
        }
        $child_parent = $tracked ? $id : $parent;
        $census = el_merge_ancestry_census($census, el_tree_ancestry_census($children, $sent_ids, $child_parent));
    }
    ksort($census['ids']);
    ksort($census['parented']);
    ksort($census['anonymous']);
    return $census;
}

/**
 * Add one node to a census: an id entry plus an ancestry entry when the node
 * is one that was sent (`$tracked_id`), an anonymous entry when it carries no
 * id at all (`$id` null). A node with an id that was not sent — a filter's
 * injection — lands in neither bucket.
 *
 * @param array{ids: array<array-key, int>, parented: array<array-key, int>, anonymous: array<array-key, int>} $census
 * @return array{ids: array<array-key, int>, parented: array<array-key, int>, anonymous: array<array-key, int>}
 */
function el_count_node_in_census(array $census, ?string $id, ?string $tracked_id, string $parent): array
{
    if ($tracked_id !== null) {
        $census['ids'][$tracked_id] = ($census['ids'][$tracked_id] ?? 0) + 1;
        $key = $parent . EL_ANCESTRY_SEPARATOR . $tracked_id;
        $census['parented'][$key] = ($census['parented'][$key] ?? 0) + 1;
    }
    if ($id === null) {
        $census['anonymous'][$parent] = ($census['anonymous'][$parent] ?? 0) + 1;
    }
    return $census;
}

/**
 * Sum a child census into its parent census, bucket by bucket.
 *
 * @param array{ids: array<array-key, int>, parented: array<array-key, int>, anonymous: array<array-key, int>} $census
 * @param array{ids: array<array-key, int>, parented: array<array-key, int>, anonymous: array<array-key, int>} $child
 * @return array{ids: array<array-key, int>, parented: array<array-key, int>, anonymous: array<array-key, int>}
 */
function el_merge_ancestry_census(array $census, array $child): array
{
    $census['ids'] = el_sum_frequencies($census['ids'], $child['ids']);
    $census['parented'] = el_sum_frequencies($census['parented'], $child['parented']);
    $census['anonymous'] = el_sum_frequencies($census['anonymous'], $child['anonymous']);
    return $census;
}

/**
 * Add two frequency maps together.
 *
 * @param array<array-key, int> $counts
 * @param array<array-key, int> $add
 * @return array<array-key, int>
 */
function el_sum_frequencies(array $counts, array $add): array
{
    foreach ($add as $key => $n) {
        $counts[$key] = ($counts[$key] ?? 0) + $n;
    }
    return $counts;
}

/**
 * Every non-empty string id in a tree, as a set, for use as the "these nodes
 * were sent" reference when censusing the stored tree.
 *
 * @param list<array<string, mixed>> $elements
 * @return array<string, true>
 */
function el_tree_id_set(array $elements): array
{
    $set = [];
    foreach ($elements as $element) {
        /** @var mixed $id */
        $id = $element['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $set[$id] = true;
        }
        if (!is_array($element['elements'] ?? null)) {
            continue;
        }
        /** @var list<array<string, mixed>> $children */
        $children = $element['elements'];
        $set += el_tree_id_set($children);
    }
    return $set;
}

/**
 * The bare shape of a tree — each node's id (null when it has none) and its
 * children, in document order. Two skeletons compare with `===` only when the
 * trees hold the same nodes in the same positions under the same ancestors,
 * which is what the raw write path must see: it encoded the json itself, so
 * any structural difference in the row is a bug rather than a filter's doing.
 *
 * @param list<array<string, mixed>> $elements
 * @return list<array{id: string|null, elements: mixed}>
 */
function el_tree_skeleton(array $elements): array
{
    $skeleton = [];
    foreach ($elements as $element) {
        /** @var mixed $id */
        $id = $element['id'] ?? null;
        /** @var list<array<string, mixed>> $children */
        $children = is_array($element['elements'] ?? null) ? $element['elements'] : [];
        $skeleton[] = [
            'id' => is_string($id) && $id !== '' ? $id : null,
            'elements' => el_tree_skeleton($children),
        ];
    }
    return $skeleton;
}

/**
 * Split the census difference into nodes that went MISSING and nodes that are
 * still stored but hang under a different sent parent. An id that is short
 * overall was dropped; an id that is present overall but short under its sent
 * parent was moved. Id-less nodes carry no identity, so the shortfall of the
 * overall count is what counts as dropped and the rest of the per-parent
 * shortfall is counted as relocation — with the anonymous-node limitation
 * documented above {@see el_tree_ancestry_census}.
 *
 * @param array{ids: array<array-key, int>, parented: array<array-key, int>, anonymous: array<array-key, int>} $expected
 * @param array{ids: array<array-key, int>, parented: array<array-key, int>, anonymous: array<array-key, int>} $found
 * @return array{dropped_ids: list<string>, dropped_count: int, moved_ids: list<string>, moved_count: int}
 */
function el_census_shortfalls(array $expected, array $found): array
{
    $dropped_ids = el_ids_short_of($expected['ids'], $found['ids']);
    $dropped_by_id = el_frequency_shortfall($expected['ids'], $found['ids']);
    $anonymous_dropped = max(0, array_sum($expected['anonymous']) - array_sum($found['anonymous']));

    $moved_by_id = max(0, el_frequency_shortfall($expected['parented'], $found['parented']) - $dropped_by_id);
    $anonymous_moved = max(0, el_frequency_shortfall($expected['anonymous'], $found['anonymous']) - $anonymous_dropped);

    $moved_ids = [];
    foreach (el_ids_short_of($expected['parented'], $found['parented']) as $key) {
        $separator = strpos($key, EL_ANCESTRY_SEPARATOR);
        $id = $separator === false ? $key : substr($key, $separator + 1);
        if (in_array($id, $dropped_ids, strict: true) || in_array($id, $moved_ids, strict: true)) {
            continue;
        }
        $moved_ids[] = $id;
    }

    return [
        'dropped_ids' => $dropped_ids,
        'dropped_count' => $dropped_by_id + $anonymous_dropped,
        'moved_ids' => $moved_ids,
        'moved_count' => $moved_by_id + $anonymous_moved,
    ];
}

/**
 * How many occurrences `$counts` has that `$against` does not cover — the
 * actual shortfall, never offset by keys `$against` has in surplus.
 *
 * @param array<array-key, int> $counts
 * @param array<array-key, int> $against
 */
function el_frequency_shortfall(array $counts, array $against): int
{
    $short = 0;
    foreach ($counts as $key => $n) {
        $short += max(0, $n - ($against[$key] ?? 0));
    }
    return $short;
}

/**
 * Occurrences of each id, keyed by id and sorted by key so two maps built
 * from differently ordered lists compare equal with `===`.
 *
 * @param list<string> $ids
 * @return array<array-key, int>
 */
function el_id_frequency(array $ids): array
{
    $counts = array_count_values($ids);
    ksort($counts);
    return $counts;
}

/**
 * Ids that occur fewer times in `$against` than in `$counts`.
 *
 * @param array<array-key, int> $counts
 * @param array<array-key, int> $against
 * @return list<string>
 */
function el_ids_short_of(array $counts, array $against): array
{
    $short = [];
    foreach ($counts as $id => $n) {
        if (($against[$id] ?? 0) >= $n) {
            continue;
        }
        $short[] = (string) $id;
    }
    return $short;
}

/**
 * Non-empty string ids of the top-level nodes, in document order.
 *
 * @param list<array<string, mixed>> $elements
 * @return list<string>
 */
function el_top_level_ids(array $elements): array
{
    $ids = [];
    foreach ($elements as $element) {
        /** @var mixed $id */
        $id = $element['id'] ?? null;
        if (is_string($id) && $id !== '') {
            $ids[] = $id;
        }
    }
    return $ids;
}

/**
 * Count every node of a tree, children included.
 *
 * @param list<array<string, mixed>> $elements
 */
function el_count_tree_elements(array $elements): int
{
    $count = 0;
    foreach ($elements as $element) {
        $count++;
        if (is_array($element['elements'] ?? null)) {
            /** @var list<array<string, mixed>> $children */
            $children = $element['elements'];
            $count += el_count_tree_elements($children);
        }
    }
    return $count;
}

/**
 * Replace any `temp-...` interaction id in the tree with a stable id, using
 * Elementor's own Parser so the rules (id format, lookup deduplication,
 * JSON-encoded interactions field) match what the editor would produce on
 * save. Returns the input unchanged when the Parser class is absent.
 *
 * @param list<array<string, mixed>> $elements
 * @return list<array<string, mixed>>
 */
function el_assign_interaction_ids(int $post_id, array $elements): array
{
    if (!class_exists(\Elementor\Modules\Interactions\Parser::class)) {
        return $elements;
    }

    $parser = new \Elementor\Modules\Interactions\Parser($post_id);
    /** @var array{elements?: list<array<string, mixed>>} $assigned */
    $assigned = $parser->assign_interaction_ids(['elements' => $elements]);

    return $assigned['elements'] ?? $elements;
}

/**
 * Ensure the Atomic Widgets Dynamic Tags module is initialized. In MCP/REST
 * context this module may not be auto-loaded, which means the
 * `elementor/atomic-widgets/props-schema` filter that adds "dynamic" as a
 * valid prop alternative is missing — causing Props_Parser to reject
 * {$$type: "dynamic"} values during Document::save().
 */
function el_ensure_dynamic_tags_module(): void
{
    $class = 'Elementor\\Modules\\AtomicWidgets\\DynamicTags\\Dynamic_Tags_Module';
    if (!class_exists($class)) {
        return;
    }

    // The module is a singleton — instance() returns the existing one or
    // creates it. register_hooks() is idempotent (WordPress deduplicates
    // identical add_filter/add_action calls).
    $module = $class::instance();
    $module->register_hooks();
}

/**
 * Invalidate one Elementor document's compiled CSS and render caches.
 *
 * Invalidation is scoped to the edited document. The previous implementation
 * called the GLOBAL `files_manager->clear_cache()`, which globs and unlinks
 * EVERY document's compiled CSS in `uploads/elementor/css/` (and wipes all
 * posts' Post_CSS + atomic cache-validity meta). Editing one post therefore
 * discarded every other page's compiled CSS — including the v4 atomic
 * editor-preview files (`local-<id>-preview-*.css`) an open editor canvas
 * depends on — forcing a needless site-wide recompile on every write and
 * opening a window where another page can render file-missing-but-flag-valid.
 * Scope it to `$post_id`. Genuinely site-wide changes (global variables, kit
 * styles) keep their own `clear_cache()` in their own abilities, where it is
 * correct. Post CSS regeneration happens separately, after the write has been
 * verified and its modified timestamp has been advanced.
 */
function el_clear_css_cache(int $post_id): void
{
    if (!class_exists('Elementor\\Plugin')) {
        return;
    }

    // Drop only THIS document's compiled v3 CSS file (+ its meta), without
    // touching any other document.
    $post_css = el_post_css_file($post_id);
    if ($post_css !== null) {
        $post_css->delete();
    }

    delete_post_meta($post_id, meta_key: '_elementor_css');

    // Drop THIS document's rendered-element HTML cache as well. Elementor's own
    // `Document::save()` deletes it on every editor save (core/base/document.php,
    // `delete_cache()`), but the raw write path every atomic tree takes bypasses
    // `save()`, so a front-end request could keep serving the pre-write markup
    // (e.g. without a freshly applied global class) until the cache TTL expires
    // even though `_elementor_data` is already correct.
    el_clear_element_cache($post_id);

    // Invalidate the v4 atomic styles cache for this document: the compiled
    // `local-<id>-<context>-<breakpoint>.css` files plus their cache-validity
    // node. Without this, changes to `styles` fields in atomic elements are not
    // reflected on the frontend. Global classes compile into the site-wide
    // `global-*` files instead and are invalidated by the global-classes
    // abilities themselves (see gc_invalidate_compiled_css()).
    do_action('elementor/atomic-widgets/styles/clear', ['local', $post_id]);
    do_action('elementor/atomic-widgets/styles/clear', ['local', $post_id, 'frontend']);
    do_action('elementor/atomic-widgets/styles/clear', ['local', $post_id, 'preview']);

    // Fire the WP standard post-cache invalidation so third-party
    // optimization plugins (Perfmatters, WP Rocket, LiteSpeed, …) that
    // listen to `clean_post_cache` can purge their per-post caches.
    // Without this, meta-only updates to `_elementor_data` leave those
    // caches stale and the frontend serves old styles.
    clean_post_cache($post_id);
}

/**
 * Get Elementor's cached post CSS file object through its public factory.
 *
 * The constructor remains a compatibility fallback for an Elementor release
 * where the inherited factory is unavailable.
 */
function el_post_css_file(int $post_id): ?\Elementor\Core\Files\CSS\Post
{
    $post_css_class = 'Elementor\\Core\\Files\\CSS\\Post';
    if (!class_exists($post_css_class)) {
        return null;
    }

    try {
        if (is_callable([$post_css_class, 'create'])) {
            /** @var \Elementor\Core\Files\CSS\Post */
            return call_user_func([$post_css_class, 'create'], $post_id);
        }

        return new $post_css_class($post_id);
    } catch (\Throwable) {
        return null;
    }
}

/**
 * Eagerly rebuild one document's post CSS after its write has been verified.
 *
 * Every Elementor write ability now pays for a full document CSS render before
 * returning. This removes the longer exposure until a later view triggers lazy
 * generation and closes the gap after Elementor's Document::save(). It cannot
 * prevent an already in-flight view with primed post meta and element data from
 * later persisting CSS rendered from that stale snapshot.
 *
 * A failed rebuild must never fail the persisted write. Elementor's parser does
 * not restore two process-wide render settings when a hook throws, so capture
 * each setting whose public accessors are available and restore it before
 * falling back to lazy regeneration.
 */
function el_regenerate_post_css(int $post_id): void
{
    $post_css = el_post_css_file($post_id);
    if ($post_css === null) {
        return;
    }

    $performance_class = 'Elementor\\Core\\Frontend\\Performance';
    $initial_style_controls = null;
    if (
        class_exists($performance_class)
        && is_callable([$performance_class, 'is_use_style_controls'])
        && is_callable([$performance_class, 'set_use_style_controls'])
    ) {
        try {
            $initial_style_controls = $performance_class::is_use_style_controls();
        } catch (\Throwable) {
            $initial_style_controls = null;
        }
    }

    $breakpoints = null;
    $initial_duplication_mode = null;
    try {
        /** @var \Elementor\Core\Breakpoints\Manager $elementor_breakpoints */
        $elementor_breakpoints = \Elementor\Plugin::$instance->breakpoints;
        if (
            is_callable([$elementor_breakpoints, 'get_responsive_control_duplication_mode'])
            && is_callable([$elementor_breakpoints, 'set_responsive_control_duplication_mode'])
        ) {
            $breakpoints = $elementor_breakpoints;
            /** @var string $initial_duplication_mode */
            $initial_duplication_mode = $breakpoints->get_responsive_control_duplication_mode();
        }
    } catch (\Throwable) {
        $breakpoints = null;
        $initial_duplication_mode = null;
    }

    try {
        $post_css->update();
    } catch (\Throwable) {
        if ($breakpoints !== null && $initial_duplication_mode !== null) {
            try {
                $breakpoints->set_responsive_control_duplication_mode($initial_duplication_mode);
            } catch (\Throwable) {
                // @mago-expect lint:no-empty-catch-clause
                // Restoring one setting must not prevent restoration of the other.
            }
        }

        if ($initial_style_controls !== null) {
            try {
                $performance_class::set_use_style_controls($initial_style_controls);
            } catch (\Throwable) {
                // @mago-expect lint:no-empty-catch-clause
                // CSS regeneration is best-effort and must not fail the write.
            }
        }
    }
}

/**
 * The two structural type aliases every surface that accepts an element node
 * understands, as `canonical => alias`.
 *
 * `elType` / `widgetType` are Elementor's own key names — the shape
 * `novamira/elementor-get-content` emits and the shape Elementor persists.
 * `element_type` / `widget_type` are the friendly snake_case spellings
 * `novamira/elementor-add-element` uses for the same two values in its
 * single-element parameters. Both name the same thing, so every node-shaped
 * input accepts both and {@see el_apply_node_type_aliases} maps one onto the
 * other before anything reads the type.
 *
 * The map deliberately stops at the type keys. `id`, `settings`, `elements`
 * and `styles` have exactly one spelling inside a node; a plausible-looking
 * variant of those is a caller mistake that
 * {@see el_find_confusable_node_keys} names instead of guessing at.
 */
const EL_NODE_TYPE_ALIASES = ['elType' => 'element_type', 'widgetType' => 'widget_type'];

/**
 * Node keys that are neither canonical nor an accepted alias, but are close
 * enough to one that a caller can reasonably expect them to work, mapped to
 * the key they were reaching for.
 *
 * Every one of these used to be dropped in silence: the write reported success
 * and the stored node came back without the id, the settings, the children or
 * the styles the caller sent. Naming the right key is the whole point — an
 * agent that gets `success: true` has no reason to look again.
 *
 * `elementType` is here because it was the worst of them: the type was ignored
 * and `element_type`'s absence then made set-content's implicit `widget`
 * default answer for it, so a mistyped container came back as a widget error
 * about a completely different key. `is_inner` / `is_locked` /
 * `editorSettings` are the snake-or-camel flips of three structural fields
 * Elementor itself reads (`isInner` decides parent/child container and
 * top/inner section), and each was measured landing in a stored document
 * unchallenged.
 *
 * THIS LIST IS A HEURISTIC AND CANNOT BE COMPLETE. It catches the likely
 * misspellings of the keys that matter; it is not a closed contract. The write
 * path for atomic trees (`el_write_page_raw`) serializes a node as it arrives,
 * so ANY key outside this list still reaches the document. A sweep over the
 * snake/camel/prefixed/plural spellings of ELEVEN node fields found 46 such
 * spellings accepted and stored, from the plausible (`type`, `widget`,
 * `el_type`, `child`, `inner`, `locked`, `css`, `props`, `element_styles`) to
 * the merely possible (`settingss`).
 *
 * Those eleven were a SAMPLE, not the set of fields Elementor reads: they were
 * hand-picked out of a wider grep, and `origin_id` — read top-level by both
 * atomic constructors and written by component expansion — was left out of the
 * pick, so a twelfth field existed all along. 46 is therefore a LOWER BOUND on
 * the accepted spellings, not a census. That is the second reason this list
 * cannot be closed by enumeration: the derivation it would be enumerated from
 * is itself incomplete.
 *
 * Closing it properly means the raw writer keeping only the node contract, or
 * observing what the installed pipeline actually persisted — see the note in
 * tests/elementor-smoke/input-alias-symmetry.php (AS-25) for the measured costs
 * of each. What THIS list guarantees is a named error for the spellings on it,
 * at every depth, on both writers.
 */
const EL_CONFUSABLE_NODE_KEYS = [
    'element_id' => 'id',
    'elementId' => 'id',
    'element_settings' => 'settings',
    'elementSettings' => 'settings',
    'children' => 'elements',
    'style' => 'styles',
    'elementType' => 'elType',
    'is_inner' => 'isInner',
    'is_locked' => 'isLocked',
    'editorSettings' => 'editor_settings',
];

/**
 * Map the structural type aliases of ONE node onto their canonical keys and
 * drop the alias keys, so nothing downstream has to know both spellings and
 * no alias key survives into the persisted document.
 *
 * The canonical key wins a conflict: a node carrying both `elType: "widget"`
 * and `element_type: "container"` is stored as a widget. That is the
 * precedence set-content has always had, and it is the same rule every other
 * surface states — the key the ability documents as primary is the one that
 * answers.
 *
 * An alias is only consulted when the canonical key is absent or null, which
 * is what `??` means everywhere else in these callbacks: an explicit `""`
 * stays an empty type and is rejected as one, rather than being quietly
 * replaced by the alias.
 *
 * Nothing else is touched. In particular no type is invented for a node that
 * has none — see {@see el_normalize_element}, whose `widget` default is
 * set-content's own documented behavior and must not spread to other paths by
 * way of a shared helper.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_apply_node_type_aliases(array $node): array
{
    foreach (EL_NODE_TYPE_ALIASES as $canonical => $alias) {
        if (!array_key_exists($alias, $node)) {
            continue;
        }
        if (($node[$canonical] ?? null) === null) {
            /** @var mixed $value */
            $value = $node[$alias];
            $node[$canonical] = $value;
        }
        unset($node[$alias]);
    }
    return $node;
}

/**
 * {@see el_apply_node_type_aliases} over a whole subtree.
 *
 * Recursion follows `elements` only, and only when it is an array: a node's
 * `settings` / `styles` are opaque payloads that may legitimately contain a
 * key named `element_type`, and a stringified `elements` list is a shape error
 * the caller's own boundary check reports by path.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_apply_tree_type_aliases(array $node): array
{
    $node = el_apply_node_type_aliases($node);
    if (!is_array($node['elements'] ?? null)) {
        return $node;
    }

    $children = $node['elements'];
    foreach (array_keys($children) as $key) {
        /** @var mixed $child */
        $child = $children[$key];
        if (is_array($child)) {
            /** @var array<string, mixed> $child */
            $children[$key] = el_apply_tree_type_aliases($child);
        }
    }
    $node['elements'] = $children;

    return $node;
}

/**
 * {@see el_apply_tree_type_aliases} over a list of top-level nodes.
 *
 * @param list<array<string, mixed>> $nodes
 * @return list<array<string, mixed>>
 */
function el_apply_forest_type_aliases(array $nodes): array
{
    $out = [];
    foreach ($nodes as $node) {
        $out[] = el_apply_tree_type_aliases($node);
    }
    return $out;
}

/**
 * Find every {@see EL_CONFUSABLE_NODE_KEYS} spelling in a subtree, as
 * `path => canonical key`.
 *
 * Only node-level keys are inspected and only `elements` is recursed into, for
 * the reason given on {@see el_apply_tree_type_aliases}: a `children` key
 * inside a widget's `settings` is that widget's own control, not a misspelled
 * child list.
 *
 * @param array<string, mixed> $node
 * @return array<string, string>
 */
function el_find_confusable_node_keys(array $node, string $prefix = ''): array
{
    $found = [];
    foreach (EL_CONFUSABLE_NODE_KEYS as $wrong => $right) {
        if (!array_key_exists($wrong, $node)) {
            continue;
        }
        $found[$prefix . $wrong] = $right;
    }

    if (!is_array($node['elements'] ?? null)) {
        return $found;
    }

    $children = $node['elements'];
    foreach (array_keys($children) as $key) {
        /** @var mixed $child */
        $child = $children[$key];
        if (is_array($child)) {
            /** @var array<string, mixed> $child */
            $found += el_find_confusable_node_keys($child, sprintf('%selements[%s].', $prefix, $key));
        }
    }

    return $found;
}

/**
 * {@see el_find_confusable_node_keys} over a list of top-level nodes.
 *
 * @param list<array<string, mixed>> $nodes
 * @return array<string, string>
 */
function el_find_confusable_forest_keys(array $nodes, string $prefix): array
{
    $found = [];
    foreach (array_keys($nodes) as $key) {
        $found += el_find_confusable_node_keys($nodes[$key], sprintf('%s[%s].', $prefix, $key));
    }
    return $found;
}

/**
 * Build the WP_Error for the confusable node keys found by
 * {@see el_find_confusable_node_keys}: every offending path is named together
 * with the key it should have been, so one roundtrip is enough to correct the
 * payload.
 *
 * `$residue` names the paths that also carry the canonical key, which is the one
 * Elementor uses. That is worth saying, but it is NOT a licence to delete: the
 * extra key can still hold something the author wrote, so the message points at
 * `novamira/elementor-get-content`, which hands back both the cleaned tree and
 * the removed values. A tree read through this plugin never lands here; a tree
 * taken straight from Elementor's own export data can.
 *
 * @param array<string, string> $found   Path → canonical key.
 * @param list<string>          $residue Paths whose canonical counterpart is present too.
 */
function el_build_confusable_node_key_error(string $context, array $found, array $residue = []): WP_Error
{
    $messages = [];
    $entries = [];
    foreach ($found as $path => $canonical) {
        $dot = strrpos($path, needle: '.');
        $messages[] = sprintf('%s → use "%s".', $path, $canonical);
        $entries[] = [
            'key' => $path,
            'value' => $dot === false ? $path : substr($path, $dot + 1),
            'expected' => $canonical,
            'residue' => in_array($path, $residue, strict: true),
        ];
    }

    $note =
        ' If this tree came out of a stored document, read it with novamira/elementor-get-content'
        . ' first: it returns the tree with these ten spellings taken out AND the VALUE each one held in'
        . ' `removed_node_keys`, so you can see what they contained before deciding. Do not delete them'
        . ' blind — `children` in particular can hold a whole subtree that Elementor never rendered but somebody'
        . ' did write.';
    if ($residue !== []) {
        $note .= sprintf(
            ' (%s next to the real key, which is the one Elementor reads.)',
            count($residue) === 1 ? '1 of them sits' : count($residue) . ' of them sit',
        );
    }

    return new WP_Error(
        'unsupported_node_key',
        sprintf(
            '%s: an element node carries one of the mistyped keys this ability refuses by name. Elementor would not read it, so writing it would store something with no effect while the value you meant it to carry goes nowhere. Inside a node the spellings are `id`, `elType` (or `element_type`), `widgetType` (or `widget_type`), `settings`, `elements`, `styles`, `isInner`, `isLocked`, `editor_settings`, `version` and `interactions`. %s%s',
            $context,
            implode(' ', $messages),
            $note,
        ),
        ['status' => 400, 'invalid_values' => $entries],
    );
}

/**
 * The subset of {@see el_find_confusable_node_keys} paths whose canonical
 * counterpart is present on the same node, i.e. the ones that provably cannot
 * carry anything the write would otherwise honour.
 *
 * Measured against the pre-fix writers on all four write paths, this is NOT a
 * reliable test for "came from a stored document". Residue only ever reached
 * storage through the atomic raw path — `Document::save()` rebuilds a v3 node
 * and drops every key outside the node contract — and on that path `style`,
 * `is_inner`, `is_locked` and `editorSettings` have no canonical counterpart
 * next to them, because nothing created one. (`settings` IS always present,
 * including on add-tree v3: an earlier revision of this comment and of the PR
 * table said otherwise and was wrong.) So this is used only to sharpen the
 * message, never to decide whether to accept — the read boundary is what makes
 * stored residue writable again.
 *
 * @param array<string, string> $found Path → canonical key, as returned by the finder.
 * @param array<string, mixed>  $node  The node the paths were found in.
 * @return list<string>
 */
function el_confusable_residue_paths(array $found, array $node, string $prefix): array
{
    $residue = [];
    foreach ($found as $path => $canonical) {
        $relative = substr($path, strlen($prefix));
        $owner = el_node_at_relative_path($node, $relative);
        if ($owner !== null && array_key_exists($canonical, $owner)) {
            $residue[] = $path;
        }
    }
    return $residue;
}

/**
 * {@see el_confusable_residue_paths} over a list of top-level nodes.
 *
 * @param array<string, string>      $found Path → canonical key.
 * @param list<array<string, mixed>> $nodes
 * @return list<string>
 */
function el_confusable_forest_residue_paths(array $found, array $nodes, string $prefix): array
{
    $residue = [];
    foreach (array_keys($nodes) as $index) {
        $node_prefix = sprintf('%s[%s].', $prefix, $index);
        $subset = [];
        foreach ($found as $path => $canonical) {
            if (!str_starts_with($path, $node_prefix)) {
                continue;
            }
            $subset[$path] = $canonical;
        }
        if ($subset !== []) {
            $residue = [...$residue, ...el_confusable_residue_paths($subset, $nodes[$index], $node_prefix)];
        }
    }
    return $residue;
}

/**
 * Resolve the node a `elements[i].elements[j].<key>` path points at, or null
 * when the path does not lead to one. Only the `elements[<int>]` steps the
 * finders emit are followed.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>|null
 */
function el_node_at_relative_path(array $node, string $relative): ?array
{
    $matches = [];
    if (preg_match_all('/elements\[(\d+)\]/', $relative, $matches) === false) {
        return null;
    }
    foreach ($matches[1] as $index) {
        if (!is_array($node['elements'] ?? null)) {
            return null;
        }
        $children = $node['elements'];
        /** @var mixed $child */
        $child = $children[(int) $index] ?? null;
        if (!is_array($child)) {
            return null;
        }
        /** @var array<string, mixed> $child */
        $node = $child;
    }
    return $node;
}

/**
 * Strip every {@see EL_CONFUSABLE_NODE_KEYS} spelling out of a subtree,
 * recording the paths it removed.
 *
 * This is the read-side counterpart of {@see el_find_confusable_node_keys}, and
 * it exists because the writers used to persist those keys. The raw atomic
 * write path serializes a node verbatim, so before this branch a payload
 * carrying `children` or `style` was stored with the junk still in it, and
 * `novamira/elementor-get-content` handed it straight back. Without this strip
 * the full dump of such a document is refused by our own writers — a tree our
 * reader produced that our writer will not take.
 *
 * The value is MOVED, never dropped. Each record carries the path, the key, the
 * canonical key it was reaching for, the value itself and whether the canonical
 * key is present on that node, so the caller can still read what was there and
 * decide. That matters most for `children`, which can hold an entire subtree
 * that Elementor never rendered but the author did write: telling someone to
 * delete the key without handing back its contents would destroy their content
 * on the next save, which is the opposite of the point.
 *
 * @param array<string, mixed> $node
 * @param list<array{path: string, key: string, expected: string, value: mixed, canonical_present: bool}> $removed
 * @return array<string, mixed>
 */
function el_strip_confusable_node_keys(array $node, string $prefix, array &$removed): array
{
    foreach (EL_CONFUSABLE_NODE_KEYS as $key => $canonical) {
        if (!array_key_exists($key, $node)) {
            continue;
        }
        $removed[] = [
            'path' => $prefix . $key,
            'key' => $key,
            'expected' => $canonical,
            'value' => $node[$key],
            'canonical_present' => array_key_exists($canonical, $node),
        ];
        unset($node[$key]);
    }

    if (!is_array($node['elements'] ?? null)) {
        return $node;
    }

    $children = $node['elements'];
    foreach (array_keys($children) as $child_key) {
        /** @var mixed $child */
        $child = $children[$child_key];
        if (is_array($child)) {
            /** @var array<string, mixed> $child */
            $children[$child_key] = el_strip_confusable_node_keys(
                $child,
                sprintf('%selements[%s].', $prefix, $child_key),
                $removed,
            );
        }
    }
    $node['elements'] = $children;

    return $node;
}

/**
 * Give a node whose element type is absent or null the type its own
 * `widgetType` already implies, recursively.
 *
 * This is NOT the implicit `widget` default set-content applies to a node that
 * names nothing — that default stays where it is and must not spread here. The
 * difference is what the payload contains: a node carrying
 * `widgetType: "heading"` HAS stated what it is, and "widget" is the only
 * envelope Elementor stores a widget in. Reading that is not inventing a type.
 * It is also what Elementor itself does at render time, which is why such a
 * node renders even with `elType: null`.
 *
 * The case is reachable in stored documents, not just in fresh payloads: before
 * this branch a tree-mode child with no type was accepted and persisted as
 * `elType: null`, so the full dump of such a page would otherwise be refused by
 * the very ability that wrote it.
 *
 * A node that states nothing at all — no `elType`, no `widgetType` — is left
 * exactly as it is, for {@see el_find_typeless_tree_nodes} to reject. An
 * explicit empty-string `elType` is a stated empty type, not an absent one, and
 * is likewise left to be reported.
 *
 * @param array<string, mixed> $node
 * @return array<string, mixed>
 */
function el_derive_tree_element_types(array $node): array
{
    /** @var mixed $widget_type */
    $widget_type = $node['widgetType'] ?? null;
    if (($node['elType'] ?? null) === null && is_string($widget_type) && $widget_type !== '') {
        $node['elType'] = 'widget';
    }

    if (!is_array($node['elements'] ?? null)) {
        return $node;
    }

    $children = $node['elements'];
    foreach (array_keys($children) as $key) {
        /** @var mixed $child */
        $child = $children[$key];
        if (is_array($child)) {
            /** @var array<string, mixed> $child */
            $children[$key] = el_derive_tree_element_types($child);
        }
    }
    $node['elements'] = $children;

    return $node;
}

/**
 * Paths of every node in a subtree whose element type is missing or empty,
 * the root included.
 *
 * `isset($node['elType'])` is Elementor's own test for "is this an element or
 * a list of elements" (`Db::iterate_data()`), so a node that reaches the
 * document without a type is not merely unvalidated: it stops being an element
 * as far as Elementor's data layer is concerned. It still renders — the
 * frontend resolves it from `widgetType` — but every feature built on that
 * walker (import / export, save as template, the content sanitizer, the usage
 * report, and the data migrations a future Elementor version runs) skips it
 * and emits PHP warnings while treating the node's own scalar keys as a list
 * of children. That is why the type is required at every depth and not just
 * at the root.
 *
 * @param array<string, mixed> $node
 * @return list<string>
 */
function el_find_typeless_tree_nodes(array $node, string $prefix): array
{
    $paths = [];
    /** @var mixed $el_type */
    $el_type = $node['elType'] ?? null;
    if (!is_string($el_type) || $el_type === '') {
        $paths[] = $prefix;
    }

    if (!is_array($node['elements'] ?? null)) {
        return $paths;
    }

    $children = $node['elements'];
    foreach (array_keys($children) as $key) {
        /** @var mixed $child */
        $child = $children[$key];
        if (is_array($child)) {
            /** @var array<string, mixed> $child */
            $paths = [...$paths, ...el_find_typeless_tree_nodes($child, sprintf('%s.elements[%s]', $prefix, $key))];
        }
    }

    return $paths;
}

/**
 * Build the WP_Error for the typeless nodes found by
 * {@see el_find_typeless_tree_nodes}.
 *
 * @param list<string> $paths
 */
function el_build_typeless_node_error(array $paths): WP_Error
{
    return new WP_Error(
        'missing_element_type',
        sprintf(
            'elType is required on every element of the subtree, not only on its root: %s. Use elType (or element_type) "widget" with a widgetType for a widget, "container" for a legacy v3 wrapper, or an atomic element type. A node stored without a type still renders, but Elementor\'s own data layer stops recognizing it as an element — export, save-as-template, the content sanitizer and future data migrations walk past it and log PHP warnings.',
            implode(', ', $paths),
        ),
        [
            'status' => 400,
            'invalid_values' => array_map(static fn(string $path): array => [
                'key' => $path . '.elType',
                'value' => null,
                'expected' => 'string',
            ], $paths),
        ],
    );
}

/**
 * Recursively normalize an element tree, ensuring every node has an `id`,
 * `elType`, `settings`, and `elements` array. Tracks any IDs assigned along
 * the way so callers can return them.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<string>               $assigned
 * @return list<array<string, mixed>>
 */
function el_normalize_tree(array $elements, array &$assigned): array
{
    $out = [];
    foreach ($elements as $element) {
        $out[] = el_normalize_element($element, $assigned);
    }
    return $out;
}

/**
 * @param array<string, mixed> $element
 * @param list<string>         $assigned
 * @return array<string, mixed>
 */
function el_normalize_element(array $element, array &$assigned): array
{
    /** @var mixed $existing_id */
    $existing_id = $element['id'] ?? null;
    if (!is_string($existing_id) || $existing_id === '') {
        $element['id'] = el_generate_id();
        $assigned[] = $element['id'];
    }

    // The add-element-style aliases (element_type / widget_type) were already
    // mapped onto the native keys at the input boundary by
    // el_apply_forest_type_aliases(), which every node-shaped surface shares.
    // What is left here is set-content's own default: a node that names no type
    // at all is taken to be a widget, and the missing widgetType is then what
    // the tree validator reports. That default belongs to this surface only,
    // which is why it lives here and not in the shared alias helper.
    //
    // So the two writers do differ on one input, deliberately: set-content
    // defaults a node that states nothing, add-element tree mode refuses it
    // (el_find_typeless_tree_nodes). Both are documented as such in the two
    // descriptions. Removing the default would refuse payloads that work today;
    // giving it to add-element would mean inventing a type for a subtree going
    // into a page that already exists. What is NOT asymmetric is a node that
    // names a widgetType: el_derive_tree_element_types() reads the envelope from
    // it on the insert path, and this default reaches the same answer here.
    $element['elType'] ??= 'widget';
    $element['settings'] = is_array($element['settings'] ?? null) ? $element['settings'] : [];

    /** @var list<array<string, mixed>> $children */
    $children = is_array($element['elements'] ?? null) ? array_values($element['elements']) : [];
    $element['elements'] = el_normalize_tree($children, $assigned);

    return $element;
}

/**
 * Find an element by ID anywhere in the tree.
 *
 * @param list<array<string, mixed>> $elements
 * @return array<string, mixed>|null
 */
function el_find(array $elements, string $element_id): ?array
{
    foreach ($elements as $element) {
        if (($element['id'] ?? '') === $element_id) {
            return $element;
        }
        if (is_array($element['elements'] ?? null)) {
            /** @var list<array<string, mixed>> $children */
            $children = $element['elements'];
            $found = el_find($children, $element_id);
            if ($found !== null) {
                return $found;
            }
        }
    }
    return null;
}

/**
 * Walk the tree and apply a mutation callback to the element with the given ID.
 * The callback receives the matching element by reference and may modify it
 * (return null) or replace its children. Returns whether the element was found.
 *
 * @param list<array<string, mixed>>                          $elements
 * @param callable(array<string, mixed>): array<string, mixed> $mutate
 */
function el_mutate(array &$elements, string $element_id, callable $mutate): bool
{
    foreach ($elements as &$element) {
        if (($element['id'] ?? '') === $element_id) {
            $element = $mutate($element);
            return true;
        }
        if (is_array($element['elements'] ?? null)) {
            /** @var list<array<string, mixed>> $children */
            $children = $element['elements'];
            if (el_mutate($children, $element_id, $mutate)) {
                $element['elements'] = $children;
                return true;
            }
        }
    }
    return false;
}

/**
 * Remove an element from the tree by ID.
 *
 * @param list<array<string, mixed>> $elements
 */
function el_remove(array &$elements, string $element_id): bool
{
    foreach ($elements as $i => &$element) {
        if (($element['id'] ?? '') === $element_id) {
            array_splice($elements, $i, length: 1);
            return true;
        }
        if (is_array($element['elements'] ?? null)) {
            /** @var list<array<string, mixed>> $children */
            $children = $element['elements'];
            if (el_remove($children, $element_id)) {
                $element['elements'] = $children;
                return true;
            }
        }
    }
    return false;
}

/**
 * Extract the full compact schema (widgetType, label, description, controls)
 * for a widget type or the container pseudo-type. Every call runs the
 * extraction against the live Elementor registry — we intentionally do not
 * cache because extraction is cheap, freshness matters more than the
 * micro-optimization, and caching would entangle unrelated write operations.
 *
 * Returns null when the widget cannot be resolved — validation then fails
 * open for that element and leaves its settings untouched.
 *
 * @return array<string, mixed>|null
 */
function el_extract_schema(string $widget_type): ?array
{
    // Validation needs every control regardless of tab (the caller may legitimately
    // set style / advanced / visibility values), so always include_styles.
    return resolve_compact_schema($widget_type, ['include_styles' => true]);
}

/**
 * Extract the `controls` map from a cached schema entry, or null when the
 * widget could not be resolved (fail-open path).
 *
 * @param array<string, mixed>|null $schema
 * @return array<string, mixed>|null
 */
function el_schema_controls(?array $schema): ?array
{
    /** @var array<string, mixed>|null $controls */
    $controls = $schema === null ? null : $schema['controls'] ?? null;
    return is_array($controls) ? $controls : null;
}

/**
 * The `el_validate_settings` result for a settings map that cannot be
 * validated against controls (no schema, or a schema without controls):
 * string-keyed entries pass through as-is, while non-string keys — the mark
 * of a list-shaped payload — are still reported as invalid values so the
 * fail-open path never silently drops them.
 *
 * @param array<array-key, mixed> $settings
 * @return array{ok: bool, settings: array<string, mixed>, dropped: list<string>, invalid: list<array{key: string, value: mixed, opts: list<mixed>}>}
 */
function el_settings_passthrough(array $settings): array
{
    $out = [];
    /** @var list<array{key: string, value: mixed, opts: list<mixed>}> $invalid */
    $invalid = [];
    /** @var mixed $value */
    foreach ($settings as $key => $value) {
        if (!is_string($key)) {
            $invalid[] = ['key' => (string) $key, 'value' => $value, 'opts' => []];
            continue;
        }
        $out[$key] = $value;
    }
    return ['ok' => $invalid === [], 'settings' => $out, 'dropped' => [], 'invalid' => $invalid];
}

/**
 * Validate and normalize a settings map against a compact schema.
 *
 * Returns a structure describing exactly what happened:
 *   ok:        true only when both dropped and invalid are empty
 *   settings:  the (coerced) settings that the caller should write
 *   dropped:   list of keys that are not part of the widget's schema
 *   invalid:   list of {key, value, opts} entries for enum violations on
 *              select / choose / switcher controls (v3) OR mismatched
 *              `$$type` / out-of-enum inner values (atomic v4)
 *
 * Atomic (v4) widgets are treated specially: every value must reach
 * Elementor as `{"$$type": "<prop_key>", "value": <scalar>}`. The validator
 * auto-wraps scalar inputs and verifies that pre-wrapped inputs match the
 * expected `$$type`, so a caller can always pass the ergonomic scalar form.
 *
 * When $schema is null (or carries no controls) the caller could not resolve
 * the widget's schema and legitimate settings on unknown widgets must not be
 * destroyed: string-keyed settings pass through unchanged and are never
 * dropped or validated ({@see el_settings_passthrough}).
 *
 * A list-shaped payload (integer keys) cannot name a control: every such key
 * is reported as an invalid value — so `ok` is false — instead of reaching
 * the string-typed key classifiers. That holds on the schema-less passthrough
 * too, which returns ok=true only when every key is a string.
 *
 * @param array<array-key, mixed>   $settings
 * @param array<string, mixed>|null $schema
 * @return array{ok: bool, settings: array<string, mixed>, dropped: list<string>, invalid: list<array{key: string, value: mixed, opts: list<mixed>}>}
 */
function el_validate_settings(array $settings, ?array $schema): array
{
    if ($schema === null) {
        return el_settings_passthrough($settings);
    }

    $schema_controls = el_schema_controls($schema);
    if ($schema_controls === null) {
        return el_settings_passthrough($settings);
    }

    $is_atomic = ($schema['is_atomic'] ?? false) === true;

    $out = [];
    /** @var list<string> $dropped */
    $dropped = [];
    /** @var list<array{key: string, value: mixed, opts: list<mixed>}> $invalid */
    $invalid = [];

    foreach (array_keys($settings) as $key) {
        /** @var mixed $value */
        $value = $settings[$key];

        if (!is_string($key)) {
            $invalid[] = ['key' => (string) $key, 'value' => $value, 'opts' => []];
            continue;
        }

        $resolution = $is_atomic
            ? el_classify_atomic_settings_key($key, $schema_controls)
            : el_classify_v3_settings_key($key, $schema_controls);

        if ($resolution['action'] === 'drop') {
            $dropped[] = $key;
            continue;
        }

        if ($resolution['action'] === 'accept_raw') {
            $out[$key] = $value;
            continue;
        }

        /** @var array<string, mixed> $control */
        $control = is_array($schema_controls[$resolution['key']]) ? $schema_controls[$resolution['key']] : [];

        $checked = $is_atomic
            ? el_coerce_atomic_value($key, $value, $control)
            : el_validate_v3_settings_value($key, $value, $control);
        if ($checked['violation'] !== null) {
            $invalid[] = $checked['violation'];
            continue;
        }
        $out[$key] = $checked['value'];
    }

    return [
        'ok' => $dropped === [] && $invalid === [],
        'settings' => $out,
        'dropped' => $dropped,
        'invalid' => $invalid,
    ];
}

/**
 * Check one v3 settings value against its resolved control: the scalar →
 * array coercion for array-valued controls, then the enum validator. Returns
 * the same `{value, violation}` pair as {@see el_coerce_atomic_value} so
 * {@see el_validate_settings} handles both widget generations uniformly.
 *
 * @param array<string, mixed> $control
 * @return array{value: mixed, violation: array{key: string, value: mixed, opts: list<mixed>}|null}
 */
function el_validate_v3_settings_value(string $key, mixed $value, array $control): array
{
    if (($control['arr'] ?? false) === true && !is_array($value)) {
        $value = [$value];
    }

    return ['value' => $value, 'violation' => el_validate_control_value($key, $value, $control)];
}

/**
 * Fill schema defaults into a settings map for any control the caller did
 * not provide. Only runs for atomic (v4) schemas: the editor reads the raw
 * persisted JSON on page load and throws on compound atomic props that were
 * never written (link, tag, attributes, classes on e-button, etc.), while
 * the frontend renderer tolerates missing keys. v3 schemas are left alone
 * so legacy widget nodes stay compact on disk.
 *
 * Must be called *after* `el_validate_settings` so any bad user-supplied
 * values have already been stripped / flagged — otherwise we might leave a
 * broken value in place while also re-adding the default, or skip a key
 * that we thought the caller supplied but was actually invalid.
 *
 * @param array<string, mixed>      $settings
 * @param array<string, mixed>|null $schema
 * @return array<string, mixed>
 */
function el_fill_atomic_schema_defaults(array $settings, ?array $schema): array
{
    if ($schema === null || ($schema['is_atomic'] ?? false) !== true) {
        return $settings;
    }
    $controls = el_schema_controls($schema);
    if ($controls === null) {
        return $settings;
    }
    foreach (array_keys($controls) as $key) {
        if (array_key_exists($key, $settings)) {
            continue;
        }
        /** @var array<string, mixed> $control */
        $control = is_array($controls[$key]) ? $controls[$key] : [];
        if (!array_key_exists('def', $control)) {
            continue;
        }
        $settings[$key] = $control['def'];
    }
    return $settings;
}

/**
 * Coerce a value to the atomic `{"$$type", "value"}` shape, validating its
 * inner scalar against the prop's enum (if present) and verifying that an
 * already-wrapped value has the right `$$type`.
 *
 * Returns `{value, violation}` where `violation` is null on success or a
 * validation record on failure. On success `value` is the coerced wrapped
 * shape ready to be written to `_elementor_data`.
 *
 * @param array<string, mixed> $control
 * @return array{value: mixed, violation: array{key: string, value: mixed, opts: list<mixed>}|null}
 */
function el_coerce_atomic_value(string $key, mixed $value, array $control): array
{
    /** @var mixed $expected_type */
    $expected_type = $control['t'] ?? null;
    if (!is_string($expected_type) || $expected_type === '') {
        // Schema does not carry a prop key — leave the value untouched.
        return ['value' => $value, 'violation' => null];
    }

    // Pre-wrapped shape {"$$type": "...", "value": ...}.
    if (is_array($value) && array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
        /** @var array<string, mixed> $value */
        return el_coerce_prewrapped_atomic_value($key, $value, $control, $expected_type);
    }

    // Scalar / null → auto-wrap. Validate the scalar first against enum,
    // then build the transformable envelope. Object-shaped prop types
    // (e.g. html-v3) need a structured inner value, which we auto-build
    // from the scalar when we know the shape.
    if (is_scalar($value) || $value === null) {
        return el_coerce_scalar_atomic_value($key, $value, $control, $expected_type);
    }

    // v3-style image object {id, url} → wrap into v4 image shape.
    $image_wrapped = el_try_wrap_image_value($expected_type, $value);
    if ($image_wrapped !== null) {
        return ['value' => $image_wrapped, 'violation' => null];
    }

    // Partial / unwrapped object for a known compound prop type
    // (e.g. `{destination: "…", isTargetBlank: true}` for a link). Wrap
    // the outer envelope and delegate to the pre-wrapped path so the
    // inner shape gets normalized — otherwise the raw object would slip
    // through untouched and the frontend renderer would drop the prop.
    if (is_array($value) && el_is_known_compound_prop_type($expected_type)) {
        /** @var array<string, mixed> $value */
        return el_coerce_prewrapped_atomic_value(
            $key,
            ['$$type' => $expected_type, 'value' => $value],
            $control,
            $expected_type,
        );
    }

    // Unwrapped value for a control whose default is itself a typed envelope
    // (e.g. `classes`, persisted as {$$type:"classes", value:[…]}). The v4
    // editor rejects such a value left unwrapped, yet the missing-value path
    // fills the envelope from `def` — so wrap the raw value here too, taking
    // the $$type from the default. This also covers a prop type whose stored
    // envelope cannot be read off `t` at all. Delegating to the pre-wrapped
    // path validates the inner value rather than passing an unsupported shape
    // through silently.
    $default_type = el_atomic_default_envelope_type($control);
    if ($default_type !== null && is_array($value)) {
        // …except a loose list of untyped objects where the control's own
        // default shows its items are typed envelopes (e.g. option rows
        // {key, value}): wrapping only the outer envelope would store items
        // the editor rejects. Refuse it the way a mismatched type is refused.
        if (el_list_has_untyped_object_items($value, $control)) {
            return [
                'value' => $value,
                'violation' => ['key' => $key, 'value' => $value, 'opts' => [$expected_type]],
            ];
        }
        /** @var array<string, mixed> $value */
        return el_coerce_prewrapped_atomic_value(
            $key,
            ['$$type' => $default_type, 'value' => $value],
            $control,
            $expected_type,
        );
    }

    // Complex shape we don't know how to coerce — pass through untouched.
    return ['value' => $value, 'violation' => null];
}

/**
 * Whether the prop type has a known inner object shape that
 * `el_normalize_atomic_inner_shape()` can re-wrap in place.
 */
function el_is_known_compound_prop_type(string $type_key): bool
{
    return in_array($type_key, ['link', 'html-v3', 'image'], strict: true);
}

/**
 * Whether $value is a list carrying an object that is not a typed
 * `{"$$type", "value"}` envelope, for a control whose declared default is a
 * non-empty list of typed items — so its item prop type reads each item's own
 * `$$type`. Lists of plain values, and controls whose default does not show
 * typed items (a class list, a scalar prop), return false.
 *
 * @param array<array-key, mixed> $value
 * @param array<string, mixed>    $control
 */
function el_list_has_untyped_object_items(array $value, array $control): bool
{
    /** @var mixed $def */
    $def = $control['def'] ?? null;
    /** @var mixed $declared */
    $declared = is_array($def) ? $def['value'] ?? null : null;
    if (
        !is_array($declared)
        || $declared === []
        || array_values($declared) !== $declared
        || array_values($value) !== $value
    ) {
        return false;
    }
    /** @var mixed $item */
    foreach ($declared as $item) {
        if (!is_array($item) || !array_key_exists('$$type', $item)) {
            return false;
        }
    }
    /** @var mixed $item */
    foreach ($value as $item) {
        if (is_array($item) && !array_key_exists('$$type', $item)) {
            return true;
        }
    }
    return false;
}

/**
 * When an atomic control's default is itself a typed envelope
 * (`{$$type, value}`), returns that `$$type` — the shape the v4 editor
 * persists. `t` already carries the same wire type for every prop type that
 * declares one, so this is the fallback for a control whose stored envelope
 * can only be read off its default. Returns null when the control has no
 * enveloped default.
 *
 * @param array<string, mixed> $control
 */
function el_atomic_default_envelope_type(array $control): ?string
{
    /** @var mixed $def */
    $def = $control['def'] ?? null;
    if (is_array($def) && is_string($def['$$type'] ?? null) && array_key_exists('value', $def)) {
        return $def['$$type'];
    }
    return null;
}

/**
 * Validate and normalize a pre-wrapped atomic value.
 *
 * @param array<string, mixed> $value
 * @param array<string, mixed> $control
 * @return array{value: mixed, violation: array{key: string, value: mixed, opts: list<mixed>}|null}
 */
function el_coerce_prewrapped_atomic_value(string $key, array $value, array $control, string $expected_type): array
{
    // Allow mismatched $$type in two cases:
    // 1. A bare "plain" prop type is a pass-through container with no wire
    //    type of its own. Concrete plain prop types (`classes`, `string`, …)
    //    report their own key and are matched exactly; this only covers a
    //    prop type that declares no key at all.
    // 2. "dynamic" values can replace any prop type — they are resolved at
    //    render time and the underlying prop type is irrelevant.
    if ($value['$$type'] !== $expected_type && $expected_type !== 'plain' && $value['$$type'] !== 'dynamic') {
        return [
            'value' => $value,
            'violation' => ['key' => $key, 'value' => $value, 'opts' => [$expected_type]],
        ];
    }

    // Dynamic values are resolved at render time — their inner structure
    // ({name, group, settings}) must NOT be normalized as if it were the
    // target prop type (e.g. link or image). Skip both normalization and
    // inner validation for dynamic values.
    if ($value['$$type'] === 'dynamic') {
        return ['value' => $value, 'violation' => null];
    }

    // Normalize the inner shape for known object prop types. Agents
    // sometimes pre-wrap the outer envelope but leave the inner shape
    // partially unwrapped (e.g. `destination: "url"` instead of
    // `destination: {$$type: "url", value: "url"}`). Without this
    // normalization, Elementor's frontend renderer crashes on the
    // malformed structure.
    $value['value'] = el_normalize_atomic_inner_shape($expected_type, $value['value']);
    $inner_violation = el_validate_atomic_inner_value($key, $value['value'], $control);
    if ($inner_violation !== null) {
        return ['value' => $value, 'violation' => $inner_violation];
    }

    return ['value' => $value, 'violation' => null];
}

/**
 * Validate and wrap a scalar atomic value.
 *
 * @param array<string, mixed> $control
 * @return array{value: mixed, violation: array{key: string, value: mixed, opts: list<mixed>}|null}
 */
function el_coerce_scalar_atomic_value(string $key, mixed $value, array $control, string $expected_type): array
{
    $inner_violation = el_validate_atomic_inner_value($key, $value, $control);
    if ($inner_violation !== null) {
        return ['value' => $value, 'violation' => $inner_violation];
    }

    return [
        'value' => [
            '$$type' => $expected_type,
            'value' => el_build_atomic_object_shape($expected_type, $value) ?? $value,
        ],
        'violation' => null,
    ];
}

/**
 * When the expected prop type is `image` and the value is a v3-style
 * `{id, url}` object, return the wrapped v4 image shape. Returns null
 * otherwise.
 */
function el_try_wrap_image_value(string $expected_type, mixed $value): ?array
{
    if ($expected_type !== 'image' || !is_array($value)) {
        return null;
    }
    if (!array_key_exists('id', $value) && !array_key_exists('url', $value)) {
        return null;
    }
    /** @var array<string, mixed> $value */
    return ['$$type' => 'image', 'value' => el_build_image_shape($value)];
}

/**
 * Build the v4 wrapped shape for an image prop from a v3-style
 * `{id, url}` object. Either id or url (or both) can be provided.
 *
 * @param array<string, mixed> $image
 * @return array<string, mixed>
 */
function el_build_image_shape(array $image): array
{
    /** @var array<string, mixed> $src_value */
    $src_value = [];
    if (array_key_exists('id', $image) && $image['id'] !== '' && $image['id'] !== null) {
        $src_value['id'] = ['$$type' => 'image-attachment-id', 'value' => (int) $image['id']];
    }
    if (array_key_exists('url', $image) && is_string($image['url']) && $image['url'] !== '') {
        $src_value['url'] = ['$$type' => 'url', 'value' => $image['url']];
    }

    /** @var mixed $size_value */
    $size_value = $image['size'] ?? null;
    $size = is_string($size_value) && $size_value !== '' ? $size_value : 'full';

    return [
        'src' => ['$$type' => 'image-src', 'value' => $src_value],
        'size' => ['$$type' => 'string', 'value' => $size],
    ];
}

/**
 * Normalize the inner value of a pre-wrapped atomic object prop so that
 * its child fields are wrapped in the `{$$type, value}` format Elementor
 * expects. No-op for types we don't know, or when the inner value is
 * already well-formed.
 */
function el_normalize_atomic_inner_shape(string $type_key, mixed $inner): mixed
{
    if (!is_array($inner)) {
        return $inner;
    }
    /** @var array<string, mixed> $inner */
    if ($type_key === 'link') {
        return el_normalize_link_inner_shape($inner);
    }
    if ($type_key === 'html-v3') {
        return el_normalize_html_v3_inner_shape($inner);
    }
    if ($type_key === 'image') {
        return el_normalize_image_inner_shape($inner);
    }
    return $inner;
}

/**
 * Normalize a `link` prop's inner value.
 *
 * @param array<string, mixed> $inner
 * @return array<string, mixed>
 */
function el_normalize_link_inner_shape(array $inner): array
{
    // An inner value with no sub-props at all is what Elementor itself stores
    // for an element without a link, and what its own prop-type default is.
    // Elementor expands the sub-prop defaults transiently at render time and
    // never writes them back, so materializing them here would rewrite a link
    // the caller never touched and stop the empty value from round-tripping.
    if ($inner === []) {
        return $inner;
    }
    /** @var mixed $destination */
    $destination = $inner['destination'] ?? null;
    if (is_string($destination)) {
        $inner['destination'] = ['$$type' => 'url', 'value' => $destination];
    }
    /** @var mixed $is_target_blank */
    $is_target_blank = $inner['isTargetBlank'] ?? null;
    if (is_bool($is_target_blank)) {
        $inner['isTargetBlank'] = ['$$type' => 'boolean', 'value' => $is_target_blank];
    }
    if (!array_key_exists('isTargetBlank', $inner) || $inner['isTargetBlank'] === null) {
        $inner['isTargetBlank'] = ['$$type' => 'boolean', 'value' => false];
    }
    /** @var mixed $tag */
    $tag = $inner['tag'] ?? null;
    if (is_string($tag)) {
        $inner['tag'] = ['$$type' => 'string', 'value' => $tag];
    }
    if (!array_key_exists('tag', $inner) || $inner['tag'] === null) {
        $inner['tag'] = ['$$type' => 'string', 'value' => 'a'];
    }
    // Drop unknown fields like `label` that aren't part of the v4 link shape.
    unset($inner['label']);
    return $inner;
}

/**
 * Normalize an `html-v3` prop's inner value.
 *
 * @param array<string, mixed> $inner
 * @return array<string, mixed>
 */
function el_normalize_html_v3_inner_shape(array $inner): array
{
    /** @var mixed $content */
    $content = $inner['content'] ?? null;
    if (is_string($content)) {
        $inner['content'] = ['$$type' => 'string', 'value' => $content];
    }
    if (!array_key_exists('children', $inner) || !is_array($inner['children'])) {
        $inner['children'] = [];
    }
    return $inner;
}

/**
 * Normalize an `image` prop's inner value.
 *
 * @param array<string, mixed> $inner
 * @return array<string, mixed>
 */
function el_normalize_image_inner_shape(array $inner): array
{
    /** @var mixed $src */
    $src = $inner['src'] ?? null;
    if (
        is_array($src)
        && (array_key_exists('id', $src) || array_key_exists('url', $src))
        && !array_key_exists('$$type', $src)
    ) {
        /** @var array<string, mixed> $src */
        $inner['src'] = ['$$type' => 'image-src', 'value' => el_build_image_shape($src)['src']['value'] ?? $src];
    }
    /** @var mixed $size */
    $size = $inner['size'] ?? null;
    if (is_string($size)) {
        $inner['size'] = ['$$type' => 'string', 'value' => $size];
    }
    return $inner;
}

/**
 * Build the expected inner structure for an atomic object prop type from a
 * scalar value, or return null when the type is not a known object shape.
 *
 * Handled object prop types:
 * - html-v3: shape content + children, scalar becomes the inner string
 *   content of a rich-text field.
 * - link: shape destination + isTargetBlank + tag, scalar is treated as a
 *   URL and wrapped into the destination slot.
 *
 * Adding more object prop types is straightforward: map the type key to a
 * builder callback that emits the expected wrapped shape.
 *
 * @return array<string, mixed>|null
 */
function el_build_atomic_object_shape(string $type_key, mixed $scalar): ?array
{
    if ($type_key === 'html-v3') {
        return [
            'content' => ['$$type' => 'string', 'value' => $scalar],
            'children' => [],
        ];
    }

    if ($type_key === 'link') {
        return [
            'destination' => ['$$type' => 'url', 'value' => $scalar],
            'isTargetBlank' => ['$$type' => 'boolean', 'value' => false],
            'tag' => ['$$type' => 'string', 'value' => 'a'],
        ];
    }

    return null;
}

/**
 * Validate the inner (unwrapped) scalar of an atomic prop against the
 * control's enum, if any. Empty string / null are accepted as "unset".
 *
 * @param array<string, mixed> $control
 * @return array{key: string, value: mixed, opts: list<mixed>}|null
 */
function el_validate_atomic_inner_value(string $key, mixed $inner, array $control): ?array
{
    /** @var mixed $raw_opts */
    $raw_opts = $control['opts'] ?? null;
    if (!is_array($raw_opts) || $raw_opts === []) {
        return null;
    }

    if ($inner === '' || $inner === null) {
        return null;
    }

    /** @var list<mixed> $opts_list */
    $opts_list = array_values($raw_opts);

    if (!is_scalar($inner)) {
        return ['key' => $key, 'value' => $inner, 'opts' => $opts_list];
    }

    $normalized = (string) $inner;
    $allowed = array_map(static fn(mixed $opt): string => (string) $opt, $raw_opts);
    if (in_array($normalized, $allowed, strict: true)) {
        return null;
    }

    return ['key' => $key, 'value' => $inner, 'opts' => $opts_list];
}

/**
 * Check a single control's value against its declared options. Returns null
 * when the value is acceptable, or an `{key, value, opts}` violation record.
 *
 * @param array<string, mixed> $control
 * @return array{key: string, value: mixed, opts: list<mixed>}|null
 */
function el_validate_control_value(string $key, mixed $value, array $control): ?array
{
    $type = (string) ($control['t'] ?? '');

    if ($type === 'select' || $type === 'choose') {
        return el_validate_enum_value($key, $value, $control);
    }

    if ($type === 'switcher') {
        return el_validate_switcher_value($key, $value, $control);
    }

    return null;
}

/**
 * Enum-style validation for select / choose controls.
 *
 * @param array<string, mixed> $control
 * @return array{key: string, value: mixed, opts: list<mixed>}|null
 */
function el_validate_enum_value(string $key, mixed $value, array $control): ?array
{
    /** @var mixed $raw_opts */
    $raw_opts = $control['opts'] ?? null;
    if (!is_array($raw_opts) || $raw_opts === []) {
        return null;
    }

    // Empty string / null is accepted as "unset" by almost every select — do
    // not fail on it even when not explicitly listed in the option keys.
    if ($value === '' || $value === null) {
        return null;
    }

    if (!is_scalar($value)) {
        /** @var list<mixed> $opts_list */
        $opts_list = array_values($raw_opts);
        return ['key' => $key, 'value' => $value, 'opts' => $opts_list];
    }

    $normalized = (string) $value;
    $allowed = array_map(static fn(mixed $opt): string => (string) $opt, $raw_opts);
    if (in_array($normalized, $allowed, strict: true)) {
        return null;
    }

    /** @var list<mixed> $opts_list */
    $opts_list = array_values($raw_opts);
    return ['key' => $key, 'value' => $value, 'opts' => $opts_list];
}

/**
 * Boolean-ish validation for switcher controls: value must be empty string
 * (off) or match the `rv` (on).
 *
 * @param array<string, mixed> $control
 * @return array{key: string, value: mixed, opts: list<mixed>}|null
 */
function el_validate_switcher_value(string $key, mixed $value, array $control): ?array
{
    /** @var mixed $rv */
    $rv = $control['rv'] ?? 'yes';

    if ($value === '' || $value === null) {
        return null;
    }

    if (is_scalar($value) && (string) $value === (string) $rv) {
        return null;
    }

    return ['key' => $key, 'value' => $value, 'opts' => ['', $rv]];
}

/**
 * Walk an element tree and validate every widget / container's settings.
 *
 * Returns the (coerced) tree, per-element errors, and the compact schemas of
 * every widget type that failed validation — schemas are collected inline
 * from each error record so the response can embed them for self-correction
 * without re-extracting.
 *
 * @param list<array<string, mixed>> $elements
 * @return array{ok: bool, tree: list<array<string, mixed>>, errors: list<array<string, mixed>>, schemas: array<string, array<string, mixed>>}
 */
function el_validate_tree(array $elements): array
{
    /** @var list<array<string, mixed>> $errors */
    $errors = [];
    $tree = el_validate_tree_recurse($elements, $errors);

    // Strip the internal `_schema` marker that the per-element validator used
    // to flag which widget types had errors — the response schema is built
    // separately below with a content-only extraction so the error payload
    // stays compact (embedding include_styles schemas would blow up the size).
    /** @var list<array<string, mixed>> $cleaned_errors */
    $cleaned_errors = [];
    foreach ($errors as $err) {
        unset($err['_schema']);
        $cleaned_errors[] = $err;
    }

    $schemas = el_build_response_schemas($cleaned_errors);

    return [
        'ok' => $cleaned_errors === [],
        'tree' => $tree,
        'errors' => $cleaned_errors,
        'schemas' => $schemas,
    ];
}

/**
 * Re-extract content-only compact schemas for every unique widget type that
 * had errors, so the response can embed them without dragging along the full
 * style / advanced tab payloads.
 *
 * @param list<array<string, mixed>> $errors
 * @return array<string, array<string, mixed>>
 */
function el_build_response_schemas(array $errors): array
{
    $schemas = [];
    foreach ($errors as $err) {
        $widget_type = (string) ($err['widget_type'] ?? '');
        if ($widget_type === '' || array_key_exists($widget_type, $schemas)) {
            continue;
        }
        $schema = resolve_compact_schema($widget_type, ['include_styles' => false]);
        if ($schema !== null) {
            $schemas[$widget_type] = $schema;
        }
    }
    return $schemas;
}

/**
 * Internal recursive walker for `el_validate_tree`. Kept separate so the
 * entry point owns the post-processing (schema collection) and the recursive
 * step only has to worry about traversal.
 *
 * @param list<array<string, mixed>> $elements
 * @param list<array<string, mixed>> $errors
 * @return list<array<string, mixed>>
 */
function el_validate_tree_recurse(array $elements, array &$errors): array
{
    $out = [];
    foreach ($elements as $element) {
        $out[] = el_validate_tree_node($element, $errors);
    }
    return $out;
}

/**
 * Validate a single element and recurse into its children.
 *
 * @param array<string, mixed>       $element
 * @param list<array<string, mixed>> $errors
 * @return array<string, mixed>
 */
function el_validate_tree_node(array $element, array &$errors): array
{
    $element = el_validate_element_settings($element, $errors);

    if (is_array($element['elements'] ?? null)) {
        /** @var list<array<string, mixed>> $children */
        $children = $element['elements'];
        $element['elements'] = el_validate_tree_recurse($children, $errors);
    }

    return $element;
}

/**
 * Resolve the raw `Prop_Type` schema (keys → Prop_Type) for an atomic
 * widget or atomic element. Returns null for v3 widgets, the v3 container,
 * and widget types that cannot be resolved — the deep validator then skips.
 *
 * @return array<string, object>|null
 */
function el_get_raw_props_schema(string $widget_type): ?array
{
    if (!class_exists('Elementor\\Plugin')) {
        return null;
    }
    if ($widget_type === '' || $widget_type === NOVAMIRA_COMPACT_SCHEMA_CONTAINER_KEY) {
        return null;
    }

    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;

    if (in_array($widget_type, NOVAMIRA_ATOMIC_CONTAINER_TYPES, strict: true)) {
        /** @var mixed $elements_manager */
        $elements_manager = $plugin->elements_manager ?? null;
        if (!is_object($elements_manager) || !method_exists($elements_manager, 'get_element_types')) {
            return null;
        }
        /** @var mixed $element */
        $element = $elements_manager->get_element_types($widget_type);
        if (!is_object($element) || !method_exists($element, 'get_props_schema')) {
            return null;
        }
        /** @var array<string, object> */
        return $element::get_props_schema();
    }

    /** @var mixed $widgets_manager */
    $widgets_manager = $plugin->widgets_manager ?? null;
    if (!is_object($widgets_manager) || !method_exists($widgets_manager, 'get_widget_types')) {
        return null;
    }
    /** @var mixed $widget */
    $widget = $widgets_manager->get_widget_types($widget_type);
    if (!class_exists(\Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base::class)) {
        return null;
    }
    if (!$widget instanceof \Elementor\Modules\AtomicWidgets\Elements\Base\Atomic_Widget_Base) {
        return null;
    }
    /** @var array<string, object> */
    return $widget::get_props_schema();
}

/**
 * Run Elementor's `Props_Parser::validate()` against already-coerced atomic
 * settings and convert any errors into the validator's `{key, value, opts}`
 * violation shape. Catches malformed inner shapes that the shallow validator
 * does not inspect (e.g. bogus html-v3 inner content, broken link objects).
 *
 * Returns an empty list when the payload is valid, when the widget has no
 * resolvable raw schema, or when `Props_Parser` is not available.
 *
 * @param array<string, mixed>       $settings
 * @param array<string, object>|null $raw_schema
 * @return list<array{key: string, value: mixed, opts: list<mixed>}>
 */
function el_validate_atomic_props_deep(array $settings, ?array $raw_schema): array
{
    if ($raw_schema === null) {
        return [];
    }
    if (!class_exists(\Elementor\Modules\AtomicWidgets\Parsers\Props_Parser::class)) {
        return [];
    }

    $parser = \Elementor\Modules\AtomicWidgets\Parsers\Props_Parser::make($raw_schema);
    $result = $parser->validate($settings);
    if ($result->is_valid()) {
        return [];
    }

    /** @var list<array{key: string, error: string}> $raw_errors */
    $raw_errors = $result->errors()->all();

    /** @var list<array{key: string, value: mixed, opts: list<mixed>}> $violations */
    $violations = [];
    foreach ($raw_errors as $err) {
        // Skip keys the caller didn't submit: Props_Parser also validates
        // defaults, so a default that fails schema validation would surface
        // here as a "false positive" from the agent's perspective.
        if (!array_key_exists($err['key'], $settings)) {
            continue;
        }
        $violations[] = [
            'key' => $err['key'],
            'value' => $settings[$err['key']],
            'opts' => [$err['error']],
        ];
    }
    return $violations;
}

/**
 * List the CSS property names that live in the atomic Style Schema. Callers
 * use this set to recognize when a dropped `settings` key actually belongs
 * in the per-element `styles` map — turning an opaque "unknown key" error
 * into a targeted hint at the right channel.
 *
 * Cached within the request because `Style_Schema::get()` walks a fair bit
 * of Elementor registry state and this is called from every validation
 * error path on atomic widgets.
 *
 * @return list<string>
 */
function el_style_schema_property_names(): array
{
    /** @var list<string>|null $cache */
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }

    return $cache = el_compute_style_schema_property_names();
}

/**
 * Compute the list used by {@see el_style_schema_property_names()}. Extracted
 * so the caller's static-cache bookkeeping stays in a single line and the
 * analyzer stops flagging `$cache = []; return $cache;` as an inlineable
 * return.
 *
 * @return list<string>
 */
function el_compute_style_schema_property_names(): array
{
    if (!class_exists(\Elementor\Modules\AtomicWidgets\Styles\Style_Schema::class)) {
        return [];
    }

    /** @var mixed $raw */
    $raw = \Elementor\Modules\AtomicWidgets\Styles\Style_Schema::get();
    if (!is_array($raw)) {
        return [];
    }

    $names = [];
    /** @var mixed $key */
    foreach (array_keys($raw) as $key) {
        if (!is_string($key)) {
            continue;
        }
        $names[] = $key;
    }
    return $names;
}

/**
 * Report style ids that are not strings. A JSON list (`[{meta, props}]`)
 * decodes to integer keys, and so does a map entry keyed `"0"`; both would
 * otherwise reach the string-typed per-style helpers and raise a TypeError
 * under strict types. A list-shaped map yields a single `styles_is_list`
 * error; a mixed map yields one `style_id_not_string` error per bad key.
 *
 * @param array<array-key, mixed> $styles
 * @return list<array{style_id: string, reason: string, path: string}>
 */
function el_collect_non_string_style_id_errors(array $styles): array
{
    if ($styles === []) {
        return [];
    }
    if (el_array_is_list($styles)) {
        return [['style_id' => '0', 'reason' => 'styles_is_list', 'path' => '']];
    }
    $errors = [];
    foreach (array_keys($styles) as $key) {
        if (is_string($key)) {
            continue;
        }
        $errors[] = ['style_id' => (string) $key, 'reason' => 'style_id_not_string', 'path' => ''];
    }
    return $errors;
}

/**
 * Walk an element subtree and collect an error record for every node whose
 * `styles` map carries a non-string style id. Mirrors the record shape
 * `el_validate_tree` emits so the tree error response builder can count and
 * summarize it like any other style entry error. Run this BEFORE any walker
 * that hands style ids to a string-typed parameter (the finalize pipeline's
 * CSS class-id sanitizer, the boxed splitter, the validators).
 *
 * @param array<string, mixed> $node
 * @return list<array<string, mixed>>
 */
function el_collect_tree_style_id_errors(array $node): array
{
    $errors = [];

    $styles = is_array($node['styles'] ?? null) ? $node['styles'] : [];
    $style_errors = el_collect_non_string_style_id_errors($styles);
    if ($style_errors !== []) {
        $errors[] = [
            'element_id' => (string) ($node['id'] ?? ''),
            'widget_type' => (string) ($node['widgetType'] ?? $node['elType'] ?? ''),
            'dropped_keys' => [],
            'invalid_values' => [],
            'style_errors' => $style_errors,
        ];
    }

    /** @var list<array<string, mixed>> $children */
    $children = is_array($node['elements'] ?? null) ? $node['elements'] : [];
    foreach ($children as $child) {
        $errors = [...$errors, ...el_collect_tree_style_id_errors($child)];
    }

    return $errors;
}

/**
 * {@see el_collect_tree_style_id_errors} over a list of top-level nodes (the
 * shape `set-content` writes with).
 *
 * @param list<array<string, mixed>> $nodes
 * @return list<array<string, mixed>>
 */
function el_collect_forest_style_id_errors(array $nodes): array
{
    $errors = [];
    foreach ($nodes as $node) {
        $errors = [...$errors, ...el_collect_tree_style_id_errors($node)];
    }
    return $errors;
}

/**
 * Describe the verbose styles-map shape, including how a non-default state
 * is targeted. Appended to the validation errors raised for non-string style
 * ids so the caller can correct the payload in one roundtrip.
 */
function el_styles_map_shape_hint(): string
{
    return 'The `styles` input must be an object keyed by style id, not a list of variants. Verbose shape: {"<style_id>": {"id": "<style_id>", "type": "class", "label": "local", "variants": [{"meta": {"breakpoint": "desktop", "state": null}, "props": {...}}, {"meta": {"breakpoint": "desktop", "state": "hover"}, "props": {"background": {"color": "#ff0000"}}}]}}. A non-default state is selected only through variants[].meta.state — valid values: null (default), "hover", "active", "focus", "focus-visible", "checked", "e--selected".';
}

/**
 * Validate a per-element styles map using Elementor's `Style_Parser`.
 *
 * Every entry must be a full style object `{id, type, label, variants:[...]}`
 * — this is the shape `Atomic_Styles_Manager::group_by_breakpoint` iterates
 * at render time. Writing a flat `{css_prop: {$$type, value}}` map (the
 * Global Classes shape) triggers a fatal TypeError during CSS generation,
 * so we reject anything that does not round-trip through `Style_Parser`.
 *
 * Returns the sanitized styles on success or a list of per-entry errors
 * describing which style ID failed and why.
 *
 * @param mixed $styles
 * @return array{ok: bool, styles: array<string, mixed>, errors: list<array{style_id: string, reason: string, path: string}>}
 */
function el_validate_styles_map(mixed $styles): array
{
    /** @var list<array{style_id: string, reason: string, path: string}> $no_errors */
    $no_errors = [];

    if ($styles === null || $styles === '' || $styles === []) {
        return ['ok' => true, 'styles' => [], 'errors' => $no_errors];
    }
    if (!is_array($styles)) {
        return [
            'ok' => false,
            'styles' => [],
            'errors' => [['style_id' => '', 'reason' => 'styles_not_object', 'path' => '']],
        ];
    }

    // Integer keys never reach the string-typed per-style helpers below.
    $id_errors = el_collect_non_string_style_id_errors($styles);
    if ($id_errors !== []) {
        return ['ok' => false, 'styles' => [], 'errors' => $id_errors];
    }

    /** @var array<string, mixed> $styles_map */
    $styles_map = $styles;

    if (
        !class_exists(\Elementor\Modules\AtomicWidgets\Parsers\Style_Parser::class)
        || !class_exists(\Elementor\Modules\AtomicWidgets\Styles\Style_Schema::class)
    ) {
        return ['ok' => true, 'styles' => $styles_map, 'errors' => $no_errors];
    }

    /** @var mixed $raw_schema */
    $raw_schema = \Elementor\Modules\AtomicWidgets\Styles\Style_Schema::get();
    if (!is_array($raw_schema)) {
        return ['ok' => true, 'styles' => $styles_map, 'errors' => $no_errors];
    }

    // Auto-wrap scalar prop values inside each variant's `props` against the
    // Style Schema before parsing — mirrors the settings-side ergonomic
    // wrapper, so an agent can pass `color: "#FFFFFF"` instead of the
    // long-form `{$$type:"color", value:"#FFFFFF"}` everywhere in styles.
    $styles_map = el_normalize_styles_map_props($styles_map);

    // Coerce each entry's `label` to a CSS-class-safe kebab shape so the
    // Style_Parser rules (2–50 chars, no spaces, `[a-zA-Z0-9_-]+`, can't
    // start with a digit / `--` / `-<digit>`, can't be `container`) are
    // satisfied on natural inputs like "hero h1" or "Card Title".
    $styles_map = el_normalize_styles_map_labels($styles_map);

    $parser = \Elementor\Modules\AtomicWidgets\Parsers\Style_Parser::make($raw_schema);
    $resolved_schema = el_resolve_style_schema();

    /** @var list<array{style_id: string, reason: string, path: string}> $errors */
    $errors = [];
    /** @var array<string, mixed> $clean */
    $clean = [];
    foreach (array_keys($styles_map) as $style_id) {
        // Unknown sub-keys inside shape props (e.g. `flex.flex-wrap`) survive
        // `Object_Prop_Type::validate` because defaults pass, and the v4
        // renderer then ignores them — producing a silently-wrong style.
        // Reject them up front so the caller sees what was dropped.
        $subkey_errors = el_collect_dropped_subkey_errors($style_id, $styles_map[$style_id], $resolved_schema);
        if ($subkey_errors !== []) {
            $errors = [...$errors, ...$subkey_errors];
            continue;
        }
        [$parsed, $entry_errors] = el_parse_single_style($parser, $style_id, $styles_map[$style_id]);
        if ($entry_errors !== []) {
            $errors = [...$errors, ...$entry_errors];
            continue;
        }
        if ($parsed !== null) {
            $clean[$style_id] = $parsed;
        }
    }

    return ['ok' => $errors === [], 'styles' => $clean, 'errors' => $errors];
}

/**
 * Run Style_Parser against a single styles-map entry. Returns the parsed
 * style (or null when the entry wasn't a writable array) and any per-entry
 * errors surfaced by the parser. Isolated so the caller's loop stays
 * free of `mixed` narrowing noise.
 *
 * @return array{0: array<string, mixed>|null, 1: list<array{style_id: string, reason: string, path: string}>}
 */
function el_parse_single_style(
    \Elementor\Modules\AtomicWidgets\Parsers\Style_Parser $parser,
    string $style_id,
    mixed $style_entry,
): array {
    /** @var list<array{style_id: string, reason: string, path: string}> $no_errors */
    $no_errors = [];

    if (!is_array($style_entry)) {
        return [null, [['style_id' => $style_id, 'reason' => 'style_entry_not_object', 'path' => '']]];
    }

    $result = $parser->parse($style_entry);
    if (!$result->is_valid()) {
        /** @var list<array{key: string, error: string}> $raw_errors */
        $raw_errors = $result->errors()->all();
        return [null, el_collect_style_parse_errors($style_id, $raw_errors)];
    }

    /** @var mixed $parsed */
    $parsed = $result->unwrap();
    if (!is_array($parsed)) {
        return [null, $no_errors];
    }
    /** @var array<string, mixed> $typed_parsed */
    $typed_parsed = $parsed;
    return [$typed_parsed, $no_errors];
}

/**
 * Convert Style_Parser error entries into the validator's per-style error
 * shape. Each Elementor error entry is `{key, error}`; we thread the
 * owning style id through so the caller can report them keyed by style.
 *
 * @param list<array{key: string, error: string}> $raw_errors
 * @return list<array{style_id: string, reason: string, path: string}>
 */
function el_collect_style_parse_errors(string $style_id, array $raw_errors): array
{
    $out = [];
    foreach ($raw_errors as $err) {
        $out[] = [
            'style_id' => $style_id,
            'reason' => $err['error'],
            'path' => $err['key'],
        ];
    }
    return $out;
}

/**
 * Render up to $limit style parse errors as a compact inline summary so the
 * per-entry reasons survive an MCP wrapper that only passes the `error`
 * string through.
 *
 * @param list<array{style_id: string, reason: string, path: string}> $errors
 */
function el_summarize_style_errors(array $errors, int $limit): string
{
    if ($errors === []) {
        return '';
    }
    $picked = [];
    foreach ($errors as $entry) {
        if (count($picked) >= $limit) {
            break;
        }
        $picked[] = sprintf(
            'style_id="%s" reason=%s path=%s',
            $entry['style_id'],
            $entry['reason'],
            $entry['path'] === '' ? '(root)' : $entry['path'],
        );
    }
    return sprintf('First %d: %s.', count($picked), implode('; ', $picked));
}

/**
 * Walk a per-element styles map and auto-wrap scalar prop values inside each
 * variant's `props` against the v4 Style Schema. Mirrors the settings-side
 * auto-wrap so an agent can pass:
 *
 *   "color":     "#FFFFFF"   → {$$type:"color", value:"#FFFFFF"}
 *   "font-size": 72          → {$$type:"size",  value:{size:72, unit:"px"}}
 *   "padding":   {"block-start":16, "inline-end":32, ...}
 *                            → {$$type:"dimensions", value:{block-start:{$$type:"size",...}, ...}}
 *   "flex":      {"flexGrow":1, "flexShrink":1, "flexBasis":{"size":0,"unit":"%"}}
 *                            → {$$type:"flex", value:{flexGrow:{$$type:"number",value:1}, ...}}
 *
 * Pre-wrapped values pass through untouched. Object/array prop types still
 * recurse into the inner shape so a partially-wrapped value (outer envelope
 * present but inner sides scalar) is normalized too. Unknown props are left
 * alone — Style_Parser will report them downstream.
 *
 * @param array<string, mixed> $styles
 * @return array<string, mixed>
 */
function el_normalize_styles_map_props(array $styles): array
{
    $schema = el_resolve_style_schema();
    if ($schema === []) {
        return $styles;
    }
    foreach (array_keys($styles) as $sid) {
        /** @var mixed $entry */
        $entry = $styles[$sid];
        if (!is_array($entry) || !is_array($entry['variants'] ?? null)) {
            continue;
        }
        /** @var list<mixed> $variants */
        $variants = $entry['variants'];
        foreach (array_keys($variants) as $vi) {
            /** @var mixed $variant */
            $variant = $variants[$vi];
            if (!is_array($variant) || !is_array($variant['props'] ?? null)) {
                continue;
            }
            /** @var array<string, mixed> $props */
            $props = $variant['props'];
            $variant['props'] = el_normalize_style_props($props, $schema);
            $variants[$vi] = $variant;
        }
        $entry['variants'] = $variants;
        $styles[$sid] = $entry;
    }
    return $styles;
}

/**
 * Coerce every entry's `label` into a form that satisfies the v4 Style_Parser
 * rules (mirrors `Style_Parser::validate_style_label`). Leaves already-valid
 * labels untouched so agent-picked kebab labels round-trip verbatim.
 *
 * @param array<string, mixed> $styles
 * @return array<string, mixed>
 */
function el_normalize_styles_map_labels(array $styles): array
{
    foreach (array_keys($styles) as $sid) {
        /** @var mixed $entry */
        $entry = $styles[$sid];
        if (!is_array($entry) || !is_string($entry['label'] ?? null)) {
            continue;
        }
        $entry['label'] = el_sanitize_style_label($entry['label']);
        $styles[$sid] = $entry;
    }
    return $styles;
}

/**
 * Coerce an arbitrary string into a label that passes Elementor's style-label
 * rules: 2–50 chars, `[a-zA-Z0-9_-]+`, can't start with a digit / `--` /
 * `-<digit>`, can't be `container`. Invalid chars (spaces, punctuation) become
 * hyphens; consecutive hyphens collapse; unsafe prefixes get an `e-` escape.
 * Already-valid labels come back unchanged, so `"hero-h1"` stays `"hero-h1"`
 * while `"hero h1"` becomes `"hero-h1"` and `"Card Title"` becomes
 * `"Card-Title"`.
 */
function el_sanitize_style_label(string $label): string
{
    if (el_is_valid_style_label($label)) {
        return $label;
    }

    $slug = preg_replace(pattern: '/[^a-zA-Z0-9_-]+/', replacement: '-', subject: $label) ?? '';
    $slug = preg_replace(pattern: '/-{2,}/', replacement: '-', subject: $slug) ?? '';
    $slug = trim(string: $slug, characters: '-');

    if ($slug !== '' && $slug[0] >= '0' && $slug[0] <= '9') {
        $slug = 'e-' . $slug;
    }

    if (strlen($slug) > 50) {
        $slug = rtrim(substr($slug, offset: 0, length: 50), characters: '-');
    }

    if (strlen($slug) < 2) {
        $slug = 'e-' . $slug;
    }

    if (strtolower($slug) === 'container') {
        $slug = 'e-' . $slug;
    }

    return $slug;
}

/**
 * Mirror of `Style_Parser::validate_style_label` — returns true when $label
 * already satisfies every rule, so the sanitizer can short-circuit and leave
 * agent-supplied kebab labels exactly as written.
 */
function el_is_valid_style_label(string $label): bool
{
    $len = strlen($label);
    if ($len < 2 || $len > 50) {
        return false;
    }
    if (strtolower($label) === 'container') {
        return false;
    }
    if (preg_match('/^[a-zA-Z0-9_-]+$/', $label) !== 1) {
        return false;
    }
    if (preg_match('/^[0-9]/', $label) === 1) {
        return false;
    }
    if (str_starts_with($label, '--')) {
        return false;
    }
    if (preg_match('/^-[0-9]/', $label) === 1) {
        return false;
    }
    return true;
}

/**
 * Resolve the v4 Style Schema as a CSS-property → Prop_Type map. Returns an
 * empty array when the atomic-widgets module is missing — callers must treat
 * that as a no-op and skip normalization.
 *
 * @return array<string, object>
 */
function el_resolve_style_schema(): array
{
    if (!class_exists(\Elementor\Modules\AtomicWidgets\Styles\Style_Schema::class)) {
        return [];
    }
    /** @var mixed $raw */
    $raw = \Elementor\Modules\AtomicWidgets\Styles\Style_Schema::get();
    if (!is_array($raw)) {
        return [];
    }
    $clean = [];
    foreach (array_keys($raw) as $name) {
        /** @var mixed $prop */
        $prop = $raw[$name];
        if (!is_string($name) || !is_object($prop)) {
            continue;
        }
        $clean[$name] = $prop;
    }
    return $clean;
}

/**
 * Auto-wrap a flat CSS-property → value map against the Style Schema. Used
 * both for per-element styles' inner `variant.props` and for the Global
 * Classes input (which is itself a flat props map at the API surface).
 *
 * @param array<string, mixed>  $props
 * @param array<string, object> $schema
 * @return array<string, mixed>
 */
function el_normalize_style_props(array $props, array $schema): array
{
    foreach (array_keys($props) as $prop_name) {
        if (!array_key_exists($prop_name, $schema)) {
            continue;
        }
        $props[$prop_name] = el_wrap_style_value($props[$prop_name], $schema[$prop_name]);
    }
    return $props;
}

/**
 * Recursively wrap a single style value against its declared Prop_Type.
 * Dispatches by type kind (union, size, object, array, primitive) so each
 * branch can pick the right `$$type` and the right inner shape.
 */
function el_wrap_style_value(mixed $value, object $prop_type): mixed
{
    if ($value === null) {
        return null;
    }

    // Dynamic values resolve at render time — leave their inner structure
    // alone, same as the settings coercer skips dynamic.
    if (is_array($value) && ($value['$$type'] ?? null) === 'dynamic' && array_key_exists('value', $value)) {
        return $value;
    }

    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Union_Prop_Type) {
        return el_wrap_style_union($value, $prop_type);
    }
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type) {
        return el_wrap_style_size($value, $prop_type);
    }
    // Object_Prop_Type must be checked AFTER Size_Prop_Type — Size extends Object.
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Base\Object_Prop_Type) {
        return el_wrap_style_object($value, $prop_type);
    }
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Base\Array_Prop_Type) {
        return el_wrap_style_array($value, $prop_type);
    }
    // String_Prop_Type catches Color_Prop_Type too (Color extends String).
    // get_key() returns the right key for each ('string' / 'color').
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Primitives\String_Prop_Type) {
        return el_wrap_style_string_scalar($value, $prop_type::get_key());
    }
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Number_Prop_Type) {
        return el_wrap_style_typed_scalar($value, type_key: 'number');
    }
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Primitives\Boolean_Prop_Type) {
        return el_wrap_style_typed_scalar($value, type_key: 'boolean');
    }
    return $value;
}

/**
 * Wrap a string-like scalar into `{$$type, value}`, casting numerics to a
 * string first — Style_Parser rejects a raw int for string/color props.
 */
function el_wrap_style_string_scalar(mixed $value, string $type_key): mixed
{
    if (is_array($value) && array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
        return $value;
    }
    if (!is_scalar($value)) {
        return $value;
    }
    return ['$$type' => $type_key, 'value' => (string) $value];
}

/**
 * Wrap a typed scalar (number, boolean) into `{$$type, value}` without
 * mangling the underlying type — Style_Parser checks `is_numeric`/`is_bool`
 * on the inner value.
 */
function el_wrap_style_typed_scalar(mixed $value, string $type_key): mixed
{
    if (is_array($value) && array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
        return $value;
    }
    if (!is_scalar($value)) {
        return $value;
    }
    return ['$$type' => $type_key, 'value' => $value];
}

/**
 * Wrap a value into a Size prop shape, defaulting the unit to whatever the
 * prop's settings declare (or px). Accepts:
 *   - bare number   72         → {size:72,  unit:"px"}
 *   - "72px"                   → {size:72,  unit:"px"}
 *   - "1.5rem"                 → {size:1.5, unit:"rem"}
 *   - "auto"                   → {size:"",  unit:"auto"}
 *   - {size, unit} (no $$type) → wrap envelope
 */
function el_wrap_style_size(mixed $value, \Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type $prop_type): mixed
{
    if (is_array($value) && array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
        return $value;
    }

    $default_unit = el_size_default_unit($prop_type);

    if (is_array($value) && array_key_exists('size', $value) && array_key_exists('unit', $value)) {
        return ['$$type' => 'size', 'value' => $value];
    }

    if (is_int($value) || is_float($value)) {
        return ['$$type' => 'size', 'value' => ['size' => $value, 'unit' => $default_unit]];
    }

    if (is_string($value)) {
        $parsed = el_parse_size_string($value, $default_unit);
        if ($parsed !== null) {
            return ['$$type' => 'size', 'value' => $parsed];
        }
    }

    return $value;
}

/**
 * Pick the default unit for a Size prop. Honors `default_unit()` (e.g. opacity
 * uses %), then falls back to `px` if available, otherwise the first allowed
 * unit, otherwise `px` as a last-resort.
 */
function el_size_default_unit(\Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type $prop_type): string
{
    /** @var array<string, mixed> $settings */
    $settings = $prop_type->get_settings();
    /** @var mixed $explicit */
    $explicit = $settings['default_unit'] ?? null;
    if (is_string($explicit) && $explicit !== '') {
        return $explicit;
    }
    /** @var mixed $units */
    $units = $settings['available_units'] ?? null;
    if (is_array($units) && in_array(needle: 'px', haystack: $units, strict: true)) {
        return 'px';
    }
    if (is_array($units) && array_key_exists(0, $units) && is_string($units[0]) && $units[0] !== '') {
        return $units[0];
    }
    return 'px';
}

/**
 * Split a CSS size string (e.g. "72px", "1.5rem", "auto") into a Size value.
 * Returns null when the string doesn't parse — caller passes the raw value
 * through so Style_Parser can report a precise error.
 *
 * @return array{size: int|float|string, unit: string}|null
 */
function el_parse_size_string(string $value, string $default_unit): ?array
{
    $trimmed = trim($value);
    if ($trimmed === '') {
        return null;
    }
    if ($trimmed === 'auto') {
        return ['size' => '', 'unit' => 'auto'];
    }
    $m = [];
    if (preg_match(pattern: '/^(-?\d+(?:\.\d+)?)\s*([a-zA-Z%]*)$/', subject: $trimmed, matches: $m) !== 1) {
        return null;
    }
    $num = $m[1];
    $unit = $m[2] === '' ? $default_unit : $m[2];
    // The regex guarantees $num is numeric — narrow it for the analyzer.
    if (!is_numeric($num)) {
        return null;
    }
    $size = !str_contains($num, '.') ? (int) $num : (float) $num;
    return ['size' => $size, 'unit' => $unit];
}

/**
 * Wrap an Object prop (dimensions, background, flex, shadow, ...). Each shape
 * field is recursively wrapped against its own declared prop type. A
 * pre-wrapped envelope is preserved — only the inner shape is normalized so
 * a half-wrapped value works too.
 */
function el_wrap_style_object(
    mixed $value,
    \Elementor\Modules\AtomicWidgets\PropTypes\Base\Object_Prop_Type $prop_type,
): mixed {
    if (!is_array($value)) {
        return $value;
    }
    /** @var array<string, mixed> $value */
    $type_key = $prop_type::get_key();
    /** @var array<string, object> $shape */
    $shape = $prop_type->get_shape();

    if (array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
        if (is_array($value['value'])) {
            $value['value'] = el_wrap_object_shape_inner($value['value'], $shape);
        }
        return $value;
    }

    return [
        '$$type' => $type_key,
        'value' => el_wrap_object_shape_inner($value, $shape),
    ];
}

/**
 * Recursively wrap each known field inside an Object prop's inner value.
 * Accepts kebab-case aliases for shape keys that are declared in camelCase
 * (e.g. `flex-grow` → `flexGrow`, `h-offset` → `hOffset`) so the styles map
 * stays consistent with the rest of the kebab-case CSS prop ecosystem.
 * Truly unknown fields are left alone for downstream validation to flag.
 *
 * A list (or any array with a non-string key) is not an object shape: it is
 * returned untouched so {@see el_collect_list_valued_object_props} can report
 * it with the expected keys, instead of feeding integer keys to the
 * string-typed alias resolver.
 *
 * @param array<array-key, mixed> $inner
 * @param array<string, object>   $shape
 * @return array<array-key, mixed>
 */
function el_wrap_object_shape_inner(array $inner, array $shape): array
{
    if (el_array_has_non_string_key($inner)) {
        return $inner;
    }
    /** @var array<string, mixed> $inner */
    $out = [];
    /** @var mixed $value */
    foreach ($inner as $key => $value) {
        $canonical = el_resolve_shape_key_alias($key, $shape);
        if ($canonical === null) {
            $out[$key] = $value;
            continue;
        }
        $out[$canonical] = el_wrap_style_value($value, $shape[$canonical]);
    }
    return $out;
}

/**
 * Map a shape key to its canonical form in $shape. Returns the key as-is when
 * $shape declares it, the camelCase equivalent when the key is a kebab-case
 * alias of a declared field, or null when the key is unknown.
 *
 * @param array<string, object> $shape
 */
function el_resolve_shape_key_alias(string $key, array $shape): ?string
{
    if (array_key_exists($key, $shape)) {
        return $key;
    }
    if (!str_contains($key, '-')) {
        return null;
    }
    $camel = lcfirst(str_replace(
        search: ' ',
        replace: '',
        subject: ucwords(str_replace(search: '-', replace: ' ', subject: $key)),
    ));
    return array_key_exists($camel, $shape) ? $camel : null;
}

/**
 * Walk a normalized style entry and collect errors for any shape sub-keys
 * that don't exist in the schema. Object_Prop_Type::validate accepts missing
 * fields (defaults pass), so unknown sub-keys survive parse-time validation
 * and get silently ignored at render — this guards the call site so the agent
 * sees an explicit rejection instead of a write that looks successful.
 *
 * An object-typed prop (or nested object sub-shape) whose value is a list is
 * reported first, as `object_prop_is_list` naming the expected shape keys,
 * and its sub-keys are not walked further.
 *
 * @param array<string, object> $schema
 * @return list<array{style_id: string, reason: string, path: string}>
 */
function el_collect_dropped_subkey_errors(string $style_id, mixed $style_entry, array $schema): array
{
    if ($schema === [] || !is_array($style_entry) || !is_array($style_entry['variants'] ?? null)) {
        return [];
    }
    /** @var list<mixed> $variants */
    $variants = $style_entry['variants'];
    $errors = [];
    foreach (array_keys($variants) as $vi) {
        /** @var mixed $variant */
        $variant = $variants[$vi];
        if (!is_array($variant) || !is_array($variant['props'] ?? null)) {
            continue;
        }
        /** @var array<string, mixed> $props */
        $props = $variant['props'];
        /** @var mixed $prop_value */
        foreach ($props as $prop_name => $prop_value) {
            if (!array_key_exists($prop_name, $schema)) {
                continue;
            }
            $prefix = "variants[{$vi}].props.{$prop_name}";
            $errors = [
                ...$errors,
                ...el_collect_prop_shape_errors($style_id, $prop_value, $schema[$prop_name], $prefix),
            ];
        }
    }
    return $errors;
}

/**
 * Shape errors for one prop value of a style variant: every list-valued (or
 * empty) object prop, plus every unknown sub-key that does not sit beneath
 * one of those lists — a list has no sub-keys to name, so its integer
 * indexes would only add noise, while unrelated sub-key mistakes elsewhere
 * in the same prop are still reported.
 *
 * @return list<array{style_id: string, reason: string, path: string}>
 */
function el_collect_prop_shape_errors(string $style_id, mixed $prop_value, object $prop_type, string $prefix): array
{
    $errors = [];
    /** @var list<string> $hit_paths */
    $hit_paths = [];
    foreach (el_collect_list_valued_object_props($prop_value, $prop_type, $prefix) as $hit) {
        $hit_paths[] = $hit['path'];
        $errors[] = [
            'style_id' => $style_id,
            'reason' => 'object_prop_is_list: ' . el_object_prop_list_message($hit),
            'path' => $hit['path'],
        ];
    }
    foreach (el_walk_unknown_shape_subkeys($prop_value, $prop_type, $prefix) as $path) {
        if (el_path_is_beneath($path, $hit_paths)) {
            continue;
        }
        $errors[] = ['style_id' => $style_id, 'reason' => 'unknown_shape_subkey', 'path' => $path];
    }
    return $errors;
}

/**
 * True when `$path` is nested under any of `$parents` (`parent.child` or
 * `parent[index]`), never for the parent itself.
 *
 * @param list<string> $parents
 */
function el_path_is_beneath(string $path, array $parents): bool
{
    foreach ($parents as $parent) {
        if (str_starts_with($path, $parent . '.') || str_starts_with($path, $parent . '[')) {
            return true;
        }
    }
    return false;
}

/**
 * True when an array carries at least one non-string key — the shape a JSON
 * list decodes to, or a map with a numeric key. An Object prop's inner value
 * must be keyed by shape field name, so such an array can never be walked by
 * the string-typed shape-key helpers.
 *
 * @param array<array-key, mixed> $value
 */
function el_array_has_non_string_key(array $value): bool
{
    foreach (array_keys($value) as $key) {
        if (!is_string($key)) {
            return true;
        }
    }
    return false;
}

/**
 * Human-readable core of the list-valued object prop error, shared by the
 * verbose styles-map reason and the ergonomic per-property reason. An empty
 * array is called out separately: after JSON decoding PHP cannot tell `{}`
 * from `[]`, and an empty inner would otherwise validate (every shape field
 * is optional) and persist as an envelope that styles nothing.
 *
 * @param array{path: string, expected_keys: list<string>, empty: bool} $hit
 */
function el_object_prop_list_message(array $hit): string
{
    return sprintf(
        'expects an object with keys %s, but %s was given',
        $hit['expected_keys'] === [] ? '(none)' : implode(', ', $hit['expected_keys']),
        $hit['empty'] ? 'an empty list' : 'a list',
    );
}

/**
 * Recursively collect every Object-typed prop, or nested object sub-shape,
 * whose value is a list — or an empty array — instead of a keyed object.
 * Runs after normalization (which leaves such values untouched) so the
 * validators can report them with the expected shape keys rather than an
 * opaque type mismatch, or a silently persisted empty envelope. Unwraps
 * `$$type`/`value` envelopes, routes unions by `$$type`, and recurses into
 * array items and known object fields so a list-valued item shape (e.g. a
 * `transform-rotate` entry under `transform-functions`) is caught too.
 *
 * @return list<array{path: string, expected_keys: list<string>, empty: bool}>
 */
function el_collect_list_valued_object_props(mixed $value, object $prop_type, string $path): array
{
    if (!is_array($value) || ($value['$$type'] ?? null) === 'dynamic') {
        return [];
    }
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Union_Prop_Type) {
        $type_key = is_string($value['$$type'] ?? null) ? $value['$$type'] : null;
        /** @var array<string, object> $alts */
        $alts = $prop_type->get_prop_types();
        if ($type_key === null || !array_key_exists($type_key, $alts)) {
            return [];
        }
        return el_collect_list_valued_object_props($value, $alts[$type_key], $path);
    }
    // Size_Prop_Type extends Object_Prop_Type but is wrapped by its own
    // scalar-aware branch, which never walks shape keys.
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type) {
        return [];
    }
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Base\Object_Prop_Type) {
        return el_collect_list_valued_object_fields($value, $prop_type, $path);
    }
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Base\Array_Prop_Type) {
        return el_collect_list_valued_array_items($value, $prop_type, $path);
    }
    return [];
}

/**
 * Object branch of {@see el_collect_list_valued_object_props}: a list-shaped
 * or empty inner value is the hit itself; a keyed inner value is recursed
 * field by field.
 *
 * @param array<array-key, mixed> $value
 * @return list<array{path: string, expected_keys: list<string>, empty: bool}>
 */
function el_collect_list_valued_object_fields(
    array $value,
    \Elementor\Modules\AtomicWidgets\PropTypes\Base\Object_Prop_Type $prop_type,
    string $path,
): array {
    $inner = $value;
    if (array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
        /** @var mixed $wrapped_inner */
        $wrapped_inner = $value['value'];
        if (!is_array($wrapped_inner)) {
            return [];
        }
        $inner = $wrapped_inner;
    }
    /** @var array<string, object> $shape */
    $shape = $prop_type->get_shape();
    if ($inner === [] || el_array_has_non_string_key($inner)) {
        return [['path' => $path, 'expected_keys' => array_keys($shape), 'empty' => $inner === []]];
    }
    /** @var array<string, mixed> $inner */
    $hits = [];
    /** @var mixed $sub_value */
    foreach ($inner as $sub_key => $sub_value) {
        if (!array_key_exists($sub_key, $shape)) {
            continue;
        }
        $hits = [
            ...$hits,
            ...el_collect_list_valued_object_props($sub_value, $shape[$sub_key], $path . '.' . $sub_key),
        ];
    }
    return $hits;
}

/**
 * Array branch of {@see el_collect_list_valued_object_props}: each list item
 * is checked against the declared item type.
 *
 * @param array<array-key, mixed> $value
 * @return list<array{path: string, expected_keys: list<string>, empty: bool}>
 */
function el_collect_list_valued_array_items(
    array $value,
    \Elementor\Modules\AtomicWidgets\PropTypes\Base\Array_Prop_Type $prop_type,
    string $path,
): array {
    $items = $value;
    if (array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
        /** @var mixed $wrapped_items */
        $wrapped_items = $value['value'];
        if (!is_array($wrapped_items)) {
            return [];
        }
        $items = $wrapped_items;
    }
    if (!el_array_is_list($items)) {
        return [];
    }
    /** @var object $item_type */
    $item_type = $prop_type->get_item_type();
    $hits = [];
    /** @var mixed $item */
    foreach ($items as $i => $item) {
        $hits = [...$hits, ...el_collect_list_valued_object_props($item, $item_type, "{$path}[{$i}]")];
    }
    return $hits;
}

/**
 * Recursively collect paths to sub-keys that don't exist in the prop type's
 * shape. Unwraps `$$type`/`value` envelopes, routes unions to the matching
 * alternative, and recurses into nested objects/arrays so a deep mismatch
 * (e.g. `shadow[0].h-offset` spelled wrong) is caught just like a shallow one.
 *
 * @return list<string>
 */
function el_walk_unknown_shape_subkeys(mixed $value, object $prop_type, string $path): array
{
    if (!is_array($value)) {
        return [];
    }
    if (($value['$$type'] ?? null) === 'dynamic') {
        return [];
    }
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Union_Prop_Type) {
        $type_key = is_string($value['$$type'] ?? null) ? $value['$$type'] : null;
        /** @var array<string, object> $alts */
        $alts = $prop_type->get_prop_types();
        if ($type_key === null || !array_key_exists($type_key, $alts)) {
            return [];
        }
        return el_walk_unknown_shape_subkeys($value, $alts[$type_key], $path);
    }
    // Size_Prop_Type extends Object_Prop_Type but its `size`/`unit` fields are
    // the canonical shape — no CSS-style aliasing applies.
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Size_Prop_Type) {
        return [];
    }
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Base\Object_Prop_Type) {
        return el_walk_unknown_object_subkeys($value, $prop_type, $path);
    }
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Base\Array_Prop_Type) {
        return el_walk_unknown_array_subkeys($value, $prop_type, $path);
    }
    return [];
}

/**
 * Collect unknown sub-keys of an Object prop. Recurses into known sub-keys so
 * a bad `shadow[0].h-ofset` (under a valid `box-shadow`) is still flagged.
 *
 * @return list<string>
 */
function el_walk_unknown_object_subkeys(
    mixed $value,
    \Elementor\Modules\AtomicWidgets\PropTypes\Base\Object_Prop_Type $prop_type,
    string $path,
): array {
    if (!is_array($value)) {
        return [];
    }
    $inner = $value;
    if (array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
        /** @var mixed $wrapped_inner */
        $wrapped_inner = $value['value'];
        if (!is_array($wrapped_inner)) {
            return [];
        }
        $inner = $wrapped_inner;
    }
    /** @var array<string, mixed> $inner */
    /** @var array<string, object> $shape */
    $shape = $prop_type->get_shape();
    $errors = [];
    /** @var mixed $sub_value */
    foreach ($inner as $sub_key => $sub_value) {
        $sub_path = $path . '.' . $sub_key;
        if (!array_key_exists($sub_key, $shape)) {
            $errors[] = $sub_path;
            continue;
        }
        $errors = [...$errors, ...el_walk_unknown_shape_subkeys($sub_value, $shape[$sub_key], $sub_path)];
    }
    return $errors;
}

/**
 * Collect unknown sub-keys inside each item of an Array prop (box-shadow,
 * transform, filter, ...). Items are indexed in the path so a caller can
 * pinpoint which entry of the list owns the bad sub-key.
 *
 * @return list<string>
 */
function el_walk_unknown_array_subkeys(
    mixed $value,
    \Elementor\Modules\AtomicWidgets\PropTypes\Base\Array_Prop_Type $prop_type,
    string $path,
): array {
    if (!is_array($value)) {
        return [];
    }
    $items = $value;
    if (array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
        /** @var mixed $wrapped_items */
        $wrapped_items = $value['value'];
        if (!is_array($wrapped_items)) {
            return [];
        }
        $items = $wrapped_items;
    }
    if (!el_array_is_list($items)) {
        return [];
    }
    /** @var object $item_type */
    $item_type = $prop_type->get_item_type();
    $errors = [];
    /** @var mixed $item */
    foreach ($items as $i => $item) {
        $errors = [...$errors, ...el_walk_unknown_shape_subkeys($item, $item_type, "{$path}[{$i}]")];
    }
    return $errors;
}

/**
 * Wrap an Array prop (box-shadow, transform, filter, ...). Each item is
 * recursively wrapped against the declared item type. A pre-wrapped envelope
 * is preserved; raw lists become a wrapped list.
 */
function el_wrap_style_array(
    mixed $value,
    \Elementor\Modules\AtomicWidgets\PropTypes\Base\Array_Prop_Type $prop_type,
): mixed {
    if (!is_array($value)) {
        return $value;
    }
    /** @var array<string, mixed>|list<mixed> $value */
    $type_key = $prop_type::get_key();
    /** @var object $item_type */
    $item_type = $prop_type->get_item_type();

    if (array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
        if (is_array($value['value'])) {
            $items = array_values($value['value']);
            $value['value'] = el_wrap_array_items($items, $item_type);
        }
        return $value;
    }

    if (!el_array_is_list($value)) {
        return $value;
    }
    $items = array_values($value);

    return [
        '$$type' => $type_key,
        'value' => el_wrap_array_items($items, $item_type),
    ];
}

/**
 * @param list<mixed> $items
 * @return list<mixed>
 */
function el_wrap_array_items(array $items, object $item_type): array
{
    $out = [];
    /** @var mixed $item */
    foreach ($items as $item) {
        $out[] = el_wrap_style_value($item, $item_type);
    }
    return $out;
}

/**
 * Pick the right alternative inside a Union prop and recursively wrap. Routing:
 *   - already wrapped: pick the alt whose key matches `$$type`
 *   - int/float: prefer `number`, fall back to `size`
 *   - bool: prefer `boolean`
 *   - string starting with digit / "auto": prefer `size`, then `string`
 *   - other string: prefer `string` (then `color`)
 *   - assoc array with size+unit keys: prefer `size`
 *   - other assoc array: pick the Object alt whose shape matches the most keys
 *   - list array: pick the Array alt
 */
function el_wrap_style_union(mixed $value, \Elementor\Modules\AtomicWidgets\PropTypes\Union_Prop_Type $prop_type): mixed
{
    /** @var array<string, object> $alts */
    $alts = $prop_type->get_prop_types();

    if (is_array($value) && array_key_exists('$$type', $value) && array_key_exists('value', $value)) {
        $key = is_string($value['$$type']) ? $value['$$type'] : '';
        if ($key !== '' && array_key_exists($key, $alts)) {
            return el_wrap_style_value($value, $alts[$key]);
        }
        return $value;
    }

    $picked = el_pick_union_alternative($value, $alts);
    if ($picked === null) {
        return $value;
    }
    return el_wrap_style_value($value, $picked);
}

/**
 * @param array<string, object> $alts
 */
function el_pick_union_alternative(mixed $value, array $alts): ?object
{
    if (is_int($value) || is_float($value)) {
        return $alts['number'] ?? $alts['size'] ?? null;
    }
    if (is_bool($value)) {
        return $alts['boolean'] ?? null;
    }
    if (is_string($value)) {
        return el_pick_union_string_alt($value, $alts);
    }
    if (is_array($value)) {
        return el_pick_union_array_alt($value, $alts);
    }
    return null;
}

/**
 * String routing inside a union: numeric-leading or "auto" → size; otherwise
 * plain string (or color when string isn't an alternative).
 *
 * @param array<string, object> $alts
 */
function el_pick_union_string_alt(string $value, array $alts): ?object
{
    if ($value === 'auto' || preg_match(pattern: '/^-?\d/', subject: $value) === 1) {
        return $alts['size'] ?? $alts['number'] ?? $alts['string'] ?? null;
    }
    return $alts['string'] ?? $alts['color'] ?? null;
}

/**
 * Array routing inside a union: list → array alt; size-shaped assoc → size;
 * other assoc → best matching object alt.
 *
 * @param array<int|string, mixed> $value
 * @param array<string, object>    $alts
 */
function el_pick_union_array_alt(array $value, array $alts): ?object
{
    if (el_array_is_list($value)) {
        foreach ($alts as $alt) {
            if ($alt instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Base\Array_Prop_Type) {
                return $alt;
            }
        }
        return null;
    }
    if (array_key_exists('size', $value) && array_key_exists('unit', $value) && array_key_exists('size', $alts)) {
        return $alts['size'];
    }
    /** @var array<string, mixed> $value */
    return el_best_object_alternative($value, $alts);
}

/**
 * Pick the Object_Prop_Type alternative whose shape matches the most input
 * keys — handles e.g. `padding: {block-start, inline-end, ...}` choosing the
 * Dimensions branch in a Union(Dimensions, Size).
 *
 * @param array<string, mixed>  $value
 * @param array<string, object> $alts
 */
function el_best_object_alternative(array $value, array $alts): ?object
{
    $best = null;
    $best_match = 0;
    foreach ($alts as $alt) {
        if (!$alt instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Base\Object_Prop_Type) {
            continue;
        }
        /** @var array<string, object> $shape */
        $shape = $alt->get_shape();
        $matches = el_count_shape_matches(array_keys($value), $shape);
        if ($matches > $best_match) {
            $best_match = $matches;
            $best = $alt;
        }
    }
    return $best;
}

/**
 * @param list<int|string>      $keys
 * @param array<string, object> $shape
 */
function el_count_shape_matches(array $keys, array $shape): int
{
    $matches = 0;
    foreach ($keys as $k) {
        if (!is_string($k) || !array_key_exists($k, $shape)) {
            continue;
        }
        $matches++;
    }
    return $matches;
}

/**
 * Validate (and normalize) the `settings` of a single element, appending to
 * `$errors` when anything is dropped or invalid. The freshly extracted schema
 * is stashed on the error record under `_schema` so the tree entry point
 * can dedupe and emit it in the response without re-extracting.
 *
 * Atomic widgets/elements also get two extra validation passes on top of the
 * shallow check: `Props_Parser::validate` on the coerced settings (catches
 * malformed inner shapes) and `Style_Parser::parse` on the element's `styles`
 * map (catches the flat-map shape that crashes the frontend renderer).
 *
 * @param array<string, mixed>       $element
 * @param list<array<string, mixed>> $errors
 * @return array<string, mixed>
 */
function el_validate_element_settings(array $element, array &$errors): array
{
    $widget_type = el_element_widget_type($element);

    // Identity first, regardless of whether the element carries settings: an
    // element Elementor cannot instantiate (unresolvable type, unregistered
    // widget or element type, container feature inactive) would otherwise
    // pass through untouched and be dropped by the save pipeline.
    $identity_error = el_element_identity_error($element, $widget_type);
    if ($identity_error !== null) {
        $errors[] = $identity_error;
        return $element;
    }

    if (!is_array($element['settings'] ?? null)) {
        return $element;
    }

    // Registered structural element types without a compact schema (section,
    // column, newer structural e-* types) have nothing to validate settings
    // against here — they are persisted as sent.
    $schema = el_extract_schema($widget_type);
    if ($schema === null) {
        return $element;
    }

    /** @var array<string, mixed> $settings */
    $settings = $element['settings'];
    $settings_result = el_validate_settings($settings, $schema);
    $element['settings'] = el_fill_atomic_schema_defaults($settings_result['settings'], $schema);

    $is_atomic = ($schema['is_atomic'] ?? false) === true;
    $invalid = $settings_result['invalid'];
    if ($is_atomic) {
        $invalid = [...$invalid, ...el_deep_validate_atomic_element($element, $widget_type)];
    }

    /** @var list<array{style_id: string, reason: string, path: string}> $style_errors */
    $style_errors = [];
    if ($is_atomic) {
        [$element, $style_errors] = el_validate_atomic_element_styles($element);
    }

    el_record_element_errors($errors, [
        'element_id' => (string) ($element['id'] ?? ''),
        'widget_type' => $widget_type,
        'dropped_keys' => $settings_result['dropped'],
        'invalid_values' => $invalid,
        'style_errors' => $style_errors,
    ]);
    return $element;
}

/**
 * Check that an element's identity resolves to something Elementor can
 * instantiate: a widget must name a registered widget type, a legacy
 * `container` needs the container element type, and any other elType must be
 * a registered element type (section, column, e-flexbox, e-tabs, …). Returns
 * the tree error record to append, or null when the identity is acceptable.
 *
 * @param array<string, mixed> $element
 * @return array<string, mixed>|null
 */
function el_element_identity_error(array $element, string $widget_type): ?array
{
    $unresolved_reason = el_unresolved_element_type_reason($element, $widget_type);
    if ($unresolved_reason !== null) {
        return el_unknown_element_type_error($element, $widget_type, $unresolved_reason);
    }

    $el_type = (string) ($element['elType'] ?? 'widget');
    if ($el_type === 'widget') {
        return el_is_widget_type($widget_type)
            ? null
            : el_unknown_element_type_error($element, $widget_type, reason: null);
    }
    if (el_is_element_type($el_type)) {
        return null;
    }
    $reason = $el_type === 'container'
        ? EL_CONTAINER_UNAVAILABLE_MESSAGE
        : sprintf('Element type "%s" is not registered with Elementor on this site.', $el_type);
    return el_unknown_element_type_error($element, $widget_type, $reason);
}

/**
 * Whether a widget type is registered with Elementor's widgets manager
 * (live lookup, no caching — registrations can change within a request).
 */
function el_is_widget_type(string $widget_type): bool
{
    if ($widget_type === '' || !class_exists('Elementor\\Plugin')) {
        return false;
    }
    /** @var object $plugin */
    $plugin = \Elementor\Plugin::$instance;
    /** @var object|null $manager */
    $manager = $plugin->widgets_manager ?? null;
    if (!is_object($manager) || !method_exists($manager, 'get_widget_types')) {
        return false;
    }
    return is_object($manager->get_widget_types($widget_type));
}

/**
 * Explain why an element's type could not be resolved to a schema key, or
 * null when `el_element_widget_type()` produced one. Only the empty key is
 * unresolved: `elType: "widget"` without a `widgetType`, or an empty /
 * non-string `elType`.
 *
 * @param array<string, mixed> $element
 */
function el_unresolved_element_type_reason(array $element, string $widget_type): ?string
{
    if ($widget_type !== '') {
        return null;
    }
    /** @var mixed $el_type */
    $el_type = $element['elType'] ?? 'widget';
    if ($el_type === 'widget') {
        return 'Widget element has no widget type — in novamira/elementor-add-element tree mode set widgetType to a registered widget name; in novamira/elementor-set-content content set widget_type or widgetType.';
    }
    return 'Element has no usable elType — use "widget" (with a widgetType), "container", or an atomic e-* element type.';
}

/**
 * Tree error record for an element whose type is unknown or unresolvable.
 * `reason` is only present when there is something more specific to say than
 * the widget type itself (container feature inactive, missing widgetType).
 *
 * @param array<string, mixed> $element
 * @return array<string, mixed>
 */
function el_unknown_element_type_error(array $element, string $widget_type, ?string $reason): array
{
    $entry = [
        'element_id' => (string) ($element['id'] ?? ''),
        'widget_type' => $widget_type,
        'dropped_keys' => [],
        'invalid_values' => [],
        'unknown_widget_type' => true,
    ];
    if ($reason !== null) {
        $entry['reason'] = $reason;
    }
    return $entry;
}

/**
 * Whether an element type name is an atomic (v4) one. Name-based on purpose:
 * it must stay true on a site where those types are not registered, which is
 * exactly the situation it reports on.
 */
function el_is_atomic_type_name(string $type): bool
{
    return str_starts_with($type, 'e-');
}

/**
 * The atomic (`e-*`) element types named by the tree validation errors that
 * were raised because the type itself is unknown. Deduped, in first-seen
 * order. Both spellings land here — the raw `elType: "e-heading"` an agent
 * sends and the `widgetType` el_normalize_tree_widget_types() rewrites it
 * into — because the error records carry the schema key produced by
 * el_element_widget_type(), which is the `e-*` name either way.
 *
 * @param list<array<string, mixed>> $errors
 * @return list<string>
 */
function el_atomic_types_in_tree_errors(array $errors): array
{
    $types = [];
    foreach ($errors as $error) {
        if (($error['unknown_widget_type'] ?? false) !== true) {
            continue;
        }
        /** @var mixed $type */
        $type = $error['widget_type'] ?? null;
        if (!is_string($type) || !el_is_atomic_type_name($type) || in_array($type, $types, strict: true)) {
            continue;
        }
        $types[] = $type;
    }
    return $types;
}

/**
 * The single explanation every write path gives when a payload names atomic
 * element types that this site cannot store. The type names are valid — the
 * atomic runtime is simply not enabled — so the generic "unknown widget type"
 * wording sends the caller hunting for a typo that does not exist. Naming the
 * types is this function's job; the site state and what to do about it come
 * from check-setup's own describer, stated once, so the two surfaces cannot
 * drift apart and the agent-facing string stays short.
 *
 * @param list<string> $types
 */
function el_atomic_unavailable_message(array $types): string
{
    $state = el_check_setup_atomic_issue(el_check_setup_atomic_block());
    $quoted = array_map(static fn(string $type): string => sprintf('"%s"', $type), $types);
    return sprintf(
        'Cannot write atomic (v4) element type(s) %s — the names are valid. %s',
        implode(', ', $quoted),
        $state ?? 'The atomic (v4) elements feature is not enabled on this site.',
    );
}

/**
 * The WP_Error for a payload that names atomic element types while the atomic
 * runtime is unavailable, or null when that is not what happened — the runtime
 * is available (so an unknown type really is unknown) or no atomic type is
 * involved. Callers fall through to their usual error in that case.
 *
 * @param list<string> $types
 */
function el_atomic_unavailable_error(array $types): ?WP_Error
{
    $atomic = array_values(array_filter($types, static fn(string $type): bool => el_is_atomic_type_name($type)));
    if ($atomic === [] || el_atomic_runtime_available()) {
        return null;
    }
    return new WP_Error('elementor_atomic_elements_unavailable', el_atomic_unavailable_message($atomic), [
        'status' => 400,
        'atomic_types' => $atomic,
    ]);
}

/**
 * Same decision for a tree that failed validation: null unless the failure is
 * (also) about atomic types on a site without the atomic runtime.
 *
 * @param list<array<string, mixed>> $errors
 */
function el_atomic_unavailable_tree_error(array $errors): ?WP_Error
{
    return el_atomic_unavailable_error(el_atomic_types_in_tree_errors($errors));
}

/**
 * Deep-validate an atomic element's coerced settings against the widget's
 * raw prop schema. Returns any violations in the `{key, value, opts}` shape
 * used by the shallow validator.
 *
 * @param array<string, mixed> $element
 * @return list<array{key: string, value: mixed, opts: list<mixed>}>
 */
function el_deep_validate_atomic_element(array $element, string $widget_type): array
{
    $raw_schema = el_get_raw_props_schema($widget_type);
    if ($raw_schema === null) {
        return [];
    }
    /** @var array<string, mixed> $coerced */
    $coerced = $element['settings'];
    return el_validate_atomic_props_deep($coerced, $raw_schema);
}

/**
 * Validate the element's `styles` map via Style_Parser and write the
 * sanitized shape back onto the element. Returns the (possibly updated)
 * element plus the list of per-entry errors — empty when the map is
 * missing, empty, or validates cleanly.
 *
 * @param array<string, mixed> $element
 * @return array{0: array<string, mixed>, 1: list<array{style_id: string, reason: string, path: string}>}
 */
function el_validate_atomic_element_styles(array $element): array
{
    if (!array_key_exists('styles', $element)) {
        return [$element, []];
    }

    $styles_result = el_validate_styles_map($element['styles']);
    if (!$styles_result['ok']) {
        return [$element, $styles_result['errors']];
    }

    $element['styles'] = $styles_result['styles'];
    return [$element, []];
}

/**
 * Append an aggregated error record for the element when any of settings,
 * deep validation, or styles surfaced problems. No-op when everything
 * validated cleanly.
 *
 * @param list<array<string, mixed>>                                                                                                                                           $errors
 * @param array{element_id: string, widget_type: string, dropped_keys: list<string>, invalid_values: list<array{key: string, value: mixed, opts: list<mixed>}>, style_errors: list<array{style_id: string, reason: string, path: string}>} $entry_fields
 */
function el_record_element_errors(array &$errors, array $entry_fields): void
{
    if (
        $entry_fields['dropped_keys'] === []
        && $entry_fields['invalid_values'] === []
        && $entry_fields['style_errors'] === []
    ) {
        return;
    }

    $entry = [
        'element_id' => $entry_fields['element_id'],
        'widget_type' => $entry_fields['widget_type'],
        'dropped_keys' => $entry_fields['dropped_keys'],
        'invalid_values' => $entry_fields['invalid_values'],
    ];
    if ($entry_fields['style_errors'] !== []) {
        $entry['style_errors'] = $entry_fields['style_errors'];
    }
    $errors[] = $entry;
}

/**
 * Resolve the validation-schema key for an element: the widget type for
 * `elType: widget`, the container pseudo-key for `elType: container`, and
 * an empty string for any other element shape (which tells the caller to
 * skip validation entirely).
 *
 * @param array<string, mixed> $element
 */
function el_element_widget_type(array $element): string
{
    $el_type = (string) ($element['elType'] ?? 'widget');

    if ($el_type === 'container') {
        return NOVAMIRA_COMPACT_SCHEMA_CONTAINER_KEY;
    }

    // Atomic container types (e-flexbox, e-div-block) are their own elType
    // and carry their schema key directly — no widgetType field needed.
    if (in_array($el_type, NOVAMIRA_ATOMIC_CONTAINER_TYPES, strict: true)) {
        return $el_type;
    }

    if ($el_type === 'widget') {
        return (string) ($element['widgetType'] ?? '');
    }

    // Atomic widgets (e-heading, e-paragraph, e-button, e-divider, …) use
    // their elType as the schema key — no widgetType field.
    return $el_type;
}

/**
 * Check whether an element tree contains any v4 atomic elements
 * (atomic containers like e-flexbox/e-div-block, or atomic widgets
 * like e-heading/e-paragraph/e-button). Used to decide whether to
 * bypass Document::save() which strips atomic widgets.
 *
 * @param list<array<string, mixed>> $elements
 */
function el_tree_has_atomic_elements(array $elements): bool
{
    foreach ($elements as $element) {
        $el_type = (string) ($element['elType'] ?? '');
        if (str_starts_with($el_type, 'e-')) {
            return true;
        }
        /** @var list<array<string, mixed>> $children */
        $children = is_array($element['elements'] ?? null) ? $element['elements'] : [];
        if ($children !== [] && el_tree_has_atomic_elements($children)) {
            return true;
        }
    }
    return false;
}

/**
 * Decide how `el_validate_settings()` should treat a single settings key
 * on an ATOMIC (v4) widget. Atomic schemas are exhaustive — anything not
 * present in the controls map is unknown and dropped. The `key` field is
 * always populated (echoes the input on `drop`) so the return shape is
 * uniform with the v3 classifier and downstream code can read it without
 * a presence check.
 *
 * @param array<string, mixed> $schema_controls
 * @return array{action: 'drop'|'validate_against', key: string}
 */
function el_classify_atomic_settings_key(string $key, array $schema_controls): array
{
    if (array_key_exists($key, $schema_controls)) {
        return ['action' => 'validate_against', 'key' => $key];
    }
    return ['action' => 'drop', 'key' => $key];
}

/**
 * Decide how `el_validate_settings()` should treat a single settings key
 * on a v3 widget or container. Returns one of three actions, with a flat
 * uniform shape so the validator loop can branch via early `continue`s:
 *
 *  - `drop`            : key is unknown and unrecoverable.
 *  - `accept_raw`      : key is a known v3 passthrough meta-key (margin /
 *                        padding / dynamic / globals / …); persist as-is.
 *  - `validate_against`: key resolves to a control in the schema (either
 *                        directly, or as a responsive `<base>_<bp>` variant
 *                        of a control flagged `r:1`); `key` is the control
 *                        id to validate against.
 *
 * @param array<string, mixed> $schema_controls
 * @return array{action: 'drop'|'accept_raw'|'validate_against', key: string}
 */
function el_classify_v3_settings_key(string $key, array $schema_controls): array
{
    if (array_key_exists($key, $schema_controls)) {
        return ['action' => 'validate_against', 'key' => $key];
    }
    if (el_is_v3_passthrough_key($key)) {
        return ['action' => 'accept_raw', 'key' => $key];
    }
    $base_key = el_v3_resolve_responsive_base($key, $schema_controls);
    if ($base_key !== null) {
        return ['action' => 'validate_against', 'key' => $base_key];
    }
    return ['action' => 'drop', 'key' => $key];
}

/**
 * If `$key` looks like a v3 responsive variant of a base control in
 * `$schema_controls` (e.g. `padding_tablet`, `typography_font_size_mobile`),
 * return the base key name. Otherwise return null.
 *
 * The base control must be present in the compact schema AND carry the
 * `r` flag emitted by `extract_control_responsive_flag()`. Two flag
 * shapes are honored:
 *
 *  - `r: 1`                           — the control is responsive on
 *                                       every breakpoint; any suffix is
 *                                       accepted.
 *  - `r: {min?: <bp>, max?: <bp>}`    — the control is restricted; the
 *                                       suffix breakpoint must lie within
 *                                       the [min, max] window in the
 *                                       canonical size ordering.
 *
 * The size ordering is the one Elementor uses internally (mobile up to
 * widescreen, see `el_v3_breakpoint_size_order`). Unknown shapes — or
 * absent flags — cause the suffix to be treated as unknown so we do not
 * accidentally accept coincidental `_tablet` substrings on non-responsive
 * controls.
 *
 * @param array<string, mixed> $schema_controls
 */
function el_v3_resolve_responsive_base(string $key, array $schema_controls): ?string
{
    $resolved = el_v3_resolve_breakpoint_key($key);
    if ($resolved['breakpoint'] === null) {
        return null;
    }
    $base = $resolved['base'];
    if (!array_key_exists($base, $schema_controls)) {
        return null;
    }
    /** @var mixed $control */
    $control = $schema_controls[$base];
    if (!is_array($control)) {
        return null;
    }
    /** @var mixed $flag */
    $flag = $control['r'] ?? null;
    if ($flag === 1) {
        return $base;
    }
    if (!is_array($flag)) {
        return null;
    }
    /** @var array<string, mixed> $flag */
    if (el_v3_breakpoint_in_range($resolved['breakpoint'], $flag)) {
        return $base;
    }
    return null;
}

/**
 * Whether `$breakpoint` falls within the {min, max} window declared by a
 * compact `r` flag. Both bounds are inclusive; either may be absent.
 * Unknown breakpoint names (e.g. a future Elementor addition we do not
 * have in the canonical order yet) are accepted permissively rather than
 * silently dropped.
 *
 * @param array<string, mixed> $constraints
 */
function el_v3_breakpoint_in_range(string $breakpoint, array $constraints): bool
{
    $order = el_v3_breakpoint_size_order();
    if (!array_key_exists($breakpoint, $order)) {
        return true;
    }
    $idx = $order[$breakpoint];

    /** @var mixed $min */
    $min = $constraints['min'] ?? null;
    if (is_string($min) && array_key_exists($min, $order) && $idx < $order[$min]) {
        return false;
    }

    /** @var mixed $max */
    $max = $constraints['max'] ?? null;
    if (is_string($max) && array_key_exists($max, $order) && $idx > $order[$max]) {
        return false;
    }

    return true;
}

/**
 * Canonical size ordering for Elementor v4 breakpoint names — smallest
 * viewport first. Mirrors the default pixel ranges declared in
 * `core/breakpoints/manager::get_default_config` (mobile 767, mobile_extra
 * 880, tablet 1024, tablet_extra 1200, laptop 1366, then desktop as the
 * implicit base above laptop, and widescreen above 2400).
 *
 * @return array<string, int>
 */
function el_v3_breakpoint_size_order(): array
{
    return [
        'mobile' => 0,
        'mobile_extra' => 1,
        'tablet' => 2,
        'tablet_extra' => 3,
        'laptop' => 4,
        'desktop' => 5,
        'widescreen' => 6,
    ];
}

/**
 * Check whether a settings key is a well-known Elementor meta-key that
 * should be passed through for v3 widgets even though it does not appear
 * in the compact schema extracted from widget controls.
 */
function el_is_v3_passthrough_key(string $key): bool
{
    /** @var list<string> */
    static $prefixes = [
        '__dynamic__',
        '__globals__',
        'custom_css',
        '_margin',
        '_padding',
        '_element_width',
        '_css_classes',
        '_title',
        '_z_index',
        '_flex_align_self',
    ];
    foreach ($prefixes as $prefix) {
        if ($key === $prefix || str_starts_with($key, $prefix . '_')) {
            return true;
        }
    }
    return false;
}

const NOVAMIRA_STYLE_SCHEMA_CLASS = 'Elementor\\Modules\\AtomicWidgets\\Styles\\Style_Schema';

/**
 * Validate + auto-wrap a flat CSS-property → value map against the v4 Style
 * Schema. Shared by create-global-class, edit-global-class, and the atomic
 * widget generator's `base_styles` input so every surface reports the same
 * `unknown_properties` / `invalid_values` shape back to the caller.
 *
 * @param array<string, mixed> $styles
 * @return array{props: array<string, mixed>}|array{error: string, unknown_properties?: list<string>, invalid_values?: array<string, array{received_type: string, expected_types: list<string>, reason: string}>}
 */
function el_validate_style_props(array $styles): array
{
    if (!class_exists(NOVAMIRA_STYLE_SCHEMA_CLASS)) {
        return [
            'error' => 'Elementor Atomic Widgets Style Schema is not available. Requires Elementor v4 with the atomic-widgets module loaded.',
        ];
    }

    $schema = el_resolve_style_schema();
    $styles = el_normalize_style_props($styles, $schema);

    $unknown = [];
    $invalid = [];
    $props = [];

    foreach (array_keys($styles) as $property) {
        if (!array_key_exists($property, $schema)) {
            $unknown[] = $property;
            continue;
        }

        /** @var mixed $value */
        $value = $styles[$property];
        $list_hits = el_collect_list_valued_object_props($value, $schema[$property], $property);
        if ($list_hits !== []) {
            $invalid[$property] = el_object_prop_list_violation($list_hits, $schema[$property]);
            continue;
        }
        $prop_error = el_validate_single_style_prop($property, $value, $schema[$property]);
        if ($prop_error !== null) {
            $invalid[$property] = $prop_error;
            continue;
        }

        /** @var array<string, mixed> $value */
        $props[$property] = $value;
    }

    if ($unknown !== [] || $invalid !== []) {
        $parts = [];
        if ($unknown !== []) {
            $parts[] = sprintf('unknown properties: %s', implode(', ', $unknown));
        }
        if ($invalid !== []) {
            $parts[] = sprintf('invalid values for: %s', implode(', ', array_keys($invalid)));
        }

        $error = [
            'error' => sprintf(
                'Invalid style payload — %s. See "invalid_values"/"unknown_properties" for per-property details; call novamira/elementor-get-style-schema only if you need the full list of valid CSS properties.',
                implode('; ', $parts),
            ),
        ];
        if ($unknown !== []) {
            $error['unknown_properties'] = $unknown;
        }
        if ($invalid !== []) {
            $error['invalid_values'] = $invalid;
        }
        return $error;
    }

    return ['props' => $props];
}

/**
 * Build the per-property violation for an object-typed prop (or nested
 * object sub-shapes) that received a list or an empty array. Same record
 * shape as {@see el_validate_single_style_prop} so the caller reports it
 * under `invalid_values` like any other mismatch; the reason names every
 * offending path with the keys its object expects.
 *
 * @param non-empty-list<array{path: string, expected_keys: list<string>, empty: bool}> $hits
 * @return array{received_type: string, expected_types: list<string>, reason: string}
 */
function el_object_prop_list_violation(array $hits, object $prop_type): array
{
    $sentences = [];
    foreach ($hits as $hit) {
        $sentences[] = sprintf('"%s" %s', $hit['path'], el_object_prop_list_message($hit));
    }
    return [
        'received_type' => 'list',
        'expected_types' => el_expected_prop_types($prop_type),
        'reason' =>
            implode('; ', $sentences)
                . '. Pass an object keyed by those names, or omit the property; only array-typed props (for example transform-functions or box-shadow) take a list.',
    ];
}

/**
 * Validate a single style prop against its schema entry. Returns `null` when
 * the value is acceptable, or a rich error entry describing what was received
 * vs. which `$$type`(s) are expected so the caller can correct without a
 * round-trip to `get-style-schema`.
 *
 * @param mixed $value
 * @return array{received_type: string, expected_types: list<string>, reason: string}|null
 */
function el_validate_single_style_prop(string $property, mixed $value, object $prop_type): ?array
{
    $expected_types = el_expected_prop_types($prop_type);

    if (!is_array($value) || !array_key_exists('$$type', $value) || !array_key_exists('value', $value)) {
        return [
            'received_type' => '(missing)',
            'expected_types' => $expected_types,
            'reason' => sprintf('Value must be an object with keys "$$type" and "value". Expected $$type one of: %s.', implode(
                ', ',
                $expected_types,
            )),
        ];
    }

    if (!method_exists($prop_type, 'validate') || !$prop_type->validate($value)) {
        $received_type = is_string($value['$$type'] ?? null) ? $value['$$type'] : '(unset)';
        return [
            'received_type' => $received_type,
            'expected_types' => $expected_types,
            'reason' => sprintf(
                'Value does not match. Got $$type="%s", expected one of: %s. Call novamira/elementor-get-style-schema for the exact shape of "%s".',
                $received_type,
                implode(', ', $expected_types),
                $property,
            ),
        ];
    }

    return null;
}

/**
 * Resolve the list of acceptable $$type values for a prop type. Union prop
 * types expand to each member's key; plain prop types report their own key.
 * The result is what an agent needs to correct a bad `$$type` without
 * fetching the whole Style Schema.
 *
 * @return list<string>
 */
function el_expected_prop_types(object $prop_type): array
{
    if ($prop_type instanceof \Elementor\Modules\AtomicWidgets\PropTypes\Union_Prop_Type) {
        /** @var list<string> */
        return array_keys($prop_type->get_prop_types());
    }
    if (method_exists($prop_type, 'get_key')) {
        return [(string) $prop_type::get_key()];
    }
    return [];
}

/**
 * Detect whether a per-element styles map is in the ergonomic CSS-property
 * shape (`{"color": "#fff", "padding": 28, "background": {...}, ...}`)
 * rather than the verbose `{"<style_id>": {id, type, label, variants: [...]}}`
 * shape the v4 Style_Parser consumes.
 *
 * Treated as ergonomic when at least one top-level key matches a known v4
 * Style Schema CSS property AND no value carries a `variants` key
 * (`variants` is the marker of a Style_Definition record). The permissive
 * "at least one" rule means a typo in one CSS property name still routes
 * through the ergonomic path so the caller sees `unknown_properties` rather
 * than Style_Parser's `style_entry_not_object` noise. Empty maps are not
 * ergonomic — callers use `{}` with `replace:true` to clear styles, so
 * matching empty would break that contract.
 *
 * @param array<string, mixed> $styles
 */
function el_looks_like_ergonomic_styles_map(array $styles): bool
{
    if ($styles === []) {
        return false;
    }
    $schema_props = el_style_schema_property_names();
    if ($schema_props === []) {
        return false;
    }
    $schema_set = array_flip($schema_props);
    $has_schema_key = false;
    foreach (array_keys($styles) as $key) {
        /** @var mixed $value */
        $value = $styles[$key];
        if (is_array($value) && array_key_exists('variants', $value)) {
            return false;
        }
        if (array_key_exists($key, $schema_set)) {
            $has_schema_key = true;
        }
    }
    return $has_schema_key;
}

/**
 * Expand ONE node's ergonomic `styles` map in place, recording a per-node entry
 * in `$errors` when the flat props do not validate. A node whose `styles` is
 * absent, empty or already verbose comes back untouched.
 *
 * @param array<string, mixed> $node
 * @param list<array{path: string, element_id: string, error: array<string, mixed>}> $errors
 * @return array<string, mixed>
 */
function el_expand_ergonomic_node_styles(array $node, string $prefix, array &$errors): array
{
    if (!is_array($node['styles'] ?? null) || $node['styles'] === []) {
        return $node;
    }

    /** @var array<string, mixed> $styles */
    $styles = $node['styles'];
    if (!el_looks_like_ergonomic_styles_map($styles)) {
        return $node;
    }

    $validation = el_validate_style_props($styles);
    if (array_key_exists('error', $validation)) {
        $errors[] = ['path' => $prefix, 'element_id' => (string) ($node['id'] ?? ''), 'error' => $validation];
        return $node;
    }

    /** @var array{props: array<string, mixed>} $validation */
    $node['styles'] = el_wrap_ergonomic_styles_as_style_def(
        is_string($node['id'] ?? null) ? $node['id'] : '',
        $validation['props'],
    );

    return $node;
}

/**
 * Expand the ergonomic flat `styles` shape of every node in a subtree into the
 * verbose per-style-id records the tree pipeline and Elementor's renderer both
 * expect.
 *
 * `styles: {"color": "#123456"}` is the shape `add-element` single mode,
 * `edit-element`, `create-atomic-widget.base_styles` and
 * `create-global-class.styles` all take. The two tree writers used to be the
 * only surfaces that rejected it — the same structure was valid in one ability
 * and invalid in another — so they now run the identical detector
 * ({@see el_looks_like_ergonomic_styles_map}), validator and wrapper the
 * single-element paths use, and the flat shape means the same thing everywhere.
 *
 * The synthesized style id is `s-<node id>`, so it must run after ids are
 * assigned. Nodes whose `styles` is already verbose (any entry with a
 * `variants` key, or no key that is a Style Schema property) are left exactly
 * as they came in.
 *
 * @param array<string, mixed> $node
 * @param list<array{path: string, element_id: string, error: array<string, mixed>}> $errors
 * @return array<string, mixed>
 */
function el_expand_ergonomic_tree_styles(array $node, string $prefix, array &$errors): array
{
    $node = el_expand_ergonomic_node_styles($node, $prefix, $errors);

    if (!is_array($node['elements'] ?? null)) {
        return $node;
    }

    $children = $node['elements'];
    foreach (array_keys($children) as $key) {
        /** @var mixed $child */
        $child = $children[$key];
        if (is_array($child)) {
            /** @var array<string, mixed> $child */
            $children[$key] = el_expand_ergonomic_tree_styles(
                $child,
                sprintf('%s.elements[%s]', $prefix, $key),
                $errors,
            );
        }
    }
    $node['elements'] = $children;

    return $node;
}

/**
 * {@see el_expand_ergonomic_tree_styles} over a list of top-level nodes.
 *
 * @param list<array<string, mixed>> $nodes
 * @param list<array{path: string, element_id: string, error: array<string, mixed>}> $errors
 * @return list<array<string, mixed>>
 */
function el_expand_ergonomic_forest_styles(array $nodes, string $prefix, array &$errors): array
{
    $out = [];
    foreach (array_keys($nodes) as $key) {
        $out[] = el_expand_ergonomic_tree_styles($nodes[$key], sprintf('%s[%s]', $prefix, $key), $errors);
    }
    return $out;
}

/**
 * Build the WP_Error for ergonomic node styles that failed validation, naming
 * the node by path and id and carrying the same per-property detail the
 * single-element surfaces return.
 *
 * @param list<array{path: string, element_id: string, error: array<string, mixed>}> $errors
 */
function el_build_ergonomic_tree_styles_error(array $errors): WP_Error
{
    $messages = [];
    $details = [];
    foreach ($errors as $entry) {
        $messages[] = sprintf(
            '%s (id "%s"): %s',
            $entry['path'],
            $entry['element_id'],
            (string) ($entry['error']['error'] ?? 'validation failed'),
        );
        $detail = ['path' => $entry['path'], 'element_id' => $entry['element_id']];
        if (array_key_exists('unknown_properties', $entry['error'])) {
            $detail['unknown_style_properties'] = $entry['error']['unknown_properties'];
        }
        if (array_key_exists('invalid_values', $entry['error'])) {
            $detail['invalid_style_values'] = $entry['error']['invalid_values'];
        }
        $details[] = $detail;
    }

    return new WP_Error(
        'invalid_ergonomic_styles',
        sprintf('Invalid ergonomic styles on %d element(s). %s', count($errors), implode(' ', $messages)),
        ['status' => 400, 'style_errors' => $details],
    );
}

/**
 * Wrap a validated ergonomic props map (output of {@see el_validate_style_props})
 * into the single-variant Style_Definition record shape the Style_Parser /
 * renderer pipeline expects. The synthesized style id is deterministic per
 * target element (`s-<element_id>`), so repeated ergonomic calls against the
 * same element overwrite the same entry instead of piling up new styles in
 * merge mode. The id (and not the label) is what becomes the rendered CSS
 * class name; the label is fixed to "local" to match the editor convention
 * Elementor uses for every per-element style.
 *
 * @param array<string, mixed> $props
 * @return array<string, mixed>
 */
function el_wrap_ergonomic_styles_as_style_def(string $element_id, array $props): array
{
    $sid = 's-' . ($element_id !== '' ? $element_id : el_generate_id());
    return [
        $sid => [
            'id' => $sid,
            'type' => 'class',
            'label' => 'local',
            'variants' => [
                [
                    'meta' => ['breakpoint' => EL_BASE_BREAKPOINT, 'state' => null],
                    'props' => $props,
                ],
            ],
        ],
    ];
}

/**
 * Output-schema entry for the `warnings` list the write abilities return when
 * the page would not show a text value the way it was written.
 *
 * @return array<string, mixed>
 */
function el_markup_warnings_schema(): array
{
    return [
        'type' => 'array',
        'description' => 'Only present when a text value written by this call will not appear on the page as written: it contains HTML tags the prop does not render (Elementor removes them), or text Elementor removes or shows literally (such as a "<" followed by more text and a ">", an HTML comment, or a quote inside a tag). `filtered_text` previews the text Elementor keeps from the value; tell the user what will look different. Markup the prop renders (e.g. <strong> in a heading title) and text Elementor keeps as written never produce a warning. Fields listed under `not_persisted` are never warned about: they were not stored.',
        'items' => [
            'type' => 'object',
            'properties' => [
                'element_id' => ['type' => 'string'],
                'widget_type' => ['type' => 'string'],
                'setting' => ['type' => 'string'],
                'dropped_tags' => [
                    'type' => 'array',
                    'items' => ['type' => 'string'],
                    'description' => 'Tags in the value that the page will not render as elements. May be empty when only the text changes.',
                ],
                'filtered_text' => [
                    'type' => 'string',
                    'description' => 'A preview of the text Elementor keeps from this value after its own filtering, with line, paragraph and list-item breaks shown as new lines. It is not a quotation of the page: WordPress formats the page afterwards and can still change it, for example turning straight quotes and double hyphens into typographic quotes and dashes.',
                ],
                'message' => ['type' => 'string'],
            ],
        ],
    ];
}

/**
 * Attach `warnings` to a successful write response for text values written by
 * this call that the page will not show as written. The key is added only
 * when there is something to report.
 *
 * $sent bounds the check by element identity: with a manifest (element id =>
 * the setting keys this call sent for that element) only those keys of those
 * elements are examined, so an untouched descendant or a stored value the call
 * did not send can never warn. Null examines every setting of every element in
 * $elements and their descendants, for calls that wrote the whole subtree.
 *
 * Must run after any report of fields Elementor did not store
 * (`not_persisted`): a field that was not stored renders nothing, so no
 * rendering claim is made for it. `$unpersisted` is the full map of those
 * fields (element id => `settings.<key>` / style paths) when the caller has
 * it — el_with_write_reports() passes it, so fields past the report's listing
 * cap are covered too; without it the response's own report is read.
 *
 * @param array<string, mixed>              $response
 * @param list<array<string, mixed>>        $elements Elements written by this call.
 * @param array<string, list<string>>|null  $sent
 * @param array<array-key, list<string>>|null $unpersisted
 * @return array<string, mixed>
 */
function el_with_markup_warnings(
    array $response,
    array $elements,
    ?array $sent = null,
    ?array $unpersisted = null,
): array {
    $warnings = [];
    foreach ($elements as $element) {
        el_collect_markup_warnings($element, $sent, $warnings);
    }
    $warnings = el_without_unpersisted_warnings($warnings, $unpersisted ?? el_unpersisted_from_response($response));
    if ($warnings !== []) {
        $response['warnings'] = $warnings;
    }
    return $response;
}

/**
 * The not-stored fields a response already reports (`not_persisted.fields`:
 * element id => list of `settings.<key>` paths). The report returns `fields`
 * as an object so every element id stays a JSON object key; an array is read
 * the same way.
 *
 * @param array<string, mixed> $response
 * @return array<array-key, mixed>
 */
function el_unpersisted_from_response(array $response): array
{
    /** @var mixed $report */
    $report = $response['not_persisted'] ?? null;
    /** @var mixed $fields */
    $fields = is_array($report) ? $report['fields'] ?? null : null;
    if (is_object($fields)) {
        return get_object_vars($fields);
    }
    return is_array($fields) ? $fields : [];
}

/**
 * Drop warnings for fields that were not stored (element id => list of
 * `settings.<key>` paths).
 *
 * @param list<array<string, mixed>> $warnings
 * @param array<array-key, mixed>    $fields
 * @return list<array<string, mixed>>
 */
function el_without_unpersisted_warnings(array $warnings, array $fields): array
{
    if ($fields === []) {
        return $warnings;
    }
    $kept = [];
    foreach ($warnings as $warning) {
        /** @var mixed $dropped */
        $dropped = $fields[(string) ($warning['element_id'] ?? '')] ?? null;
        $path = 'settings.' . (string) ($warning['setting'] ?? '');
        if (is_array($dropped) && in_array($path, $dropped, strict: true)) {
            continue;
        }
        $kept[] = $warning;
    }
    return $kept;
}

/**
 * @param array<string, mixed>             $element
 * @param array<string, list<string>>|null $sent
 * @param list<array<string, mixed>>       $warnings
 */
function el_collect_markup_warnings(array $element, ?array $sent, array &$warnings): void
{
    $element_id = (string) ($element['id'] ?? '');
    $widget_type = (string) ($element['widgetType'] ?? '');
    $settings = is_array($element['settings'] ?? null) ? $element['settings'] : [];
    $keys = $sent === null ? array_keys($settings) : $sent[$element_id] ?? [];
    foreach ($keys as $key) {
        if ($widget_type === '' || !is_string($key) || !array_key_exists($key, $settings)) {
            continue;
        }
        $warning = el_markup_warning_for($widget_type, $key, $settings[$key]);
        if ($warning !== null) {
            $warnings[] = ['element_id' => $element_id] + $warning;
        }
    }

    /** @var mixed $children */
    $children = $element['elements'] ?? null;
    /** @var mixed $child */
    foreach (is_array($children) ? $children : [] as $child) {
        if (!is_array($child)) {
            continue;
        }
        /** @var array<string, mixed> $child */
        el_collect_markup_warnings($child, $sent, $warnings);
    }
}

/**
 * The warning for one stored setting value, or null when the page will show
 * it as written.
 *
 * The value is run through Elementor's own filtering steps for these props,
 * not through a model of HTML: the kses pass its transformer applies
 * (Html_Prop_Type::sanitize_allowed_html()) followed by the template's
 * striptags with the tag list Elementor publishes for the prop
 * (Escaped_Html_Prop_Type::get_allowed_html_tags_for_prop(), the list its own
 * MCP gives its agents). The result is compared with what the value asks for —
 * its tags, and its text with comments and tags taken out. A warning is raised
 * only when a tag is not rendered or the text differs. Where Elementor does not
 * provide both steps for the prop, nothing is guessed: no list or no sanitizer
 * means no warning.
 *
 * The filtered text is reported as what it is: the text Elementor keeps, before
 * WordPress formats the page (typographic quotes and dashes, and whatever else
 * the site's content filters do). Those later steps are not modelled.
 *
 * @return array{widget_type: string, setting: string, dropped_tags: list<string>, filtered_text: string, message: string}|null
 */
function el_markup_warning_for(string $widget_type, string $setting, mixed $value): ?array
{
    $text = el_stored_text_value($value);
    if ($text === null || !str_contains($text, '<')) {
        return null;
    }

    $allowed = el_published_markup_tags($widget_type, $setting);
    $sanitizer = 'Elementor\\Modules\\AtomicWidgets\\PropTypes\\Html_Prop_Type';
    if ($allowed === null || !method_exists($sanitizer, 'sanitize_allowed_html')) {
        return null;
    }
    /** @var mixed $sanitized */
    $sanitized = $sanitizer::sanitize_allowed_html($text);
    if (!is_string($sanitized)) {
        return null;
    }
    $rendered = strip_tags($sanitized, $allowed);

    $sent_markup = preg_replace('/<!--.*?(?:-->|$)/s', replacement: '', subject: $text) ?? $text;
    $dropped = array_values(array_diff(el_markup_open_tags($sent_markup), el_markup_open_tags($rendered)));
    $filtered_text = el_markup_visible_text($rendered);
    if ($dropped === [] && $filtered_text === el_markup_visible_text($sent_markup)) {
        return null;
    }

    return [
        'widget_type' => $widget_type,
        'setting' => $setting,
        'dropped_tags' => $dropped,
        'filtered_text' => $filtered_text,
        'message' => el_markup_warning_message($widget_type, $setting, $dropped, $filtered_text, $allowed),
    ];
}

/**
 * @param list<string> $dropped
 * @param list<string> $allowed
 */
function el_markup_warning_message(
    string $widget_type,
    string $setting,
    array $dropped,
    string $filtered_text,
    array $allowed,
): string {
    $wrap = static fn(string $tag): string => '<' . $tag . '>';
    $removed = $dropped === []
        ? ''
        : sprintf(' Elementor does not render %s here and removes those tags.', implode(', ', array_map(
            $wrap,
            $dropped,
        )));
    return sprintf(
        '"%s" on %s will not appear on the page as written.%s After Elementor\'s filtering the text kept is: "%s" (a preview, not a quotation of the page: WordPress formats the page afterwards and can still change quotes and dashes). This prop renders %s as markup; other tags, HTML comments and a "<" followed by more text and a ">" are removed or shown differently.',
        $setting,
        $widget_type,
        $removed,
        $filtered_text,
        implode(', ', array_map($wrap, $allowed)),
    );
}

/**
 * The names of the opening tags in a piece of HTML, lowercased, in order,
 * without repeats. A tag is `<` immediately followed by a letter, running to
 * the first `>` outside quotes — so a comparison like `a < b` or a `<` kept
 * inside a quoted attribute value is not a tag.
 *
 * @return list<string>
 */
function el_markup_open_tags(string $html): array
{
    $matches = [];
    preg_match_all(EL_MARKUP_TAG_PATTERN, $html, $matches);
    $tags = [];
    foreach ($matches[1] as $i => $name) {
        $name = strtolower($name);
        if (str_starts_with($matches[0][$i], '</') || in_array($name, $tags, strict: true)) {
            continue;
        }
        $tags[] = $name;
    }
    return $tags;
}

/**
 * The text of a piece of HTML, one line per line of text it lays out: runs of
 * whitespace in the source collapse to one space as a browser does, a line
 * break or a block-level boundary (paragraph, list item, div, heading, …)
 * becomes a new line, any other tag (see el_markup_open_tags()) is taken out
 * without a gap, entities are decoded, and empty lines are dropped. So words
 * either side of a <br> or of two list items are never reported as one word,
 * while inline tags such as <strong> leave the words they wrap joined.
 */
function el_markup_visible_text(string $html): string
{
    $collapsed = preg_replace('/\s+/u', replacement: ' ', subject: $html) ?? $html;
    $bounded =
        preg_replace_callback(
            EL_MARKUP_TAG_PATTERN,
            static fn(array $m): string => in_array(strtolower($m[1]), EL_MARKUP_BLOCK_TAGS, strict: true) ? "\n" : '',
            $collapsed,
        ) ?? $collapsed;
    $decoded = html_entity_decode($bounded, ENT_QUOTES | ENT_HTML5, encoding: 'UTF-8');
    $lines = [];
    foreach (explode("\n", $decoded) as $line) {
        $line = trim(preg_replace('/[^\S\n]+/u', replacement: ' ', subject: $line) ?? $line);
        if ($line === '') {
            continue;
        }
        $lines[] = $line;
    }
    return implode("\n", $lines);
}

/** Tags that start a new line of text: the line break and HTML's block-level elements. */
const EL_MARKUP_BLOCK_TAGS = [
    'address',
    'article',
    'aside',
    'blockquote',
    'br',
    'dd',
    'details',
    'div',
    'dl',
    'dt',
    'fieldset',
    'figcaption',
    'figure',
    'footer',
    'form',
    'h1',
    'h2',
    'h3',
    'h4',
    'h5',
    'h6',
    'header',
    'hr',
    'li',
    'main',
    'nav',
    'ol',
    'p',
    'pre',
    'section',
    'summary',
    'table',
    'tbody',
    'td',
    'tfoot',
    'th',
    'thead',
    'tr',
    'ul',
];

/** An HTML tag: `<` or `</`, a name starting with a letter, attributes with quotes respected, `>`. */
const EL_MARKUP_TAG_PATTERN = '/<\/?([a-zA-Z][a-zA-Z0-9-]*)(?=[\s\/>])(?:"[^"]*"|\'[^\']*\'|[^\'">])*>/';

/**
 * The text of a stored atomic text value, whichever envelope carries it: a
 * string envelope (`{"$$type": …, "value": "<text>"}`) or a structured one
 * holding it in `content` (`{"$$type": …, "value": {"content": {"$$type":
 * "string", "value": "<text>"}}}`). Null for anything else (dynamic values,
 * overrides, v3 settings, non-text props).
 */
function el_stored_text_value(mixed $value): ?string
{
    if (!is_array($value) || !is_string($value['$$type'] ?? null) || $value['$$type'] === 'dynamic') {
        return null;
    }
    /** @var mixed $inner */
    $inner = $value['value'] ?? null;
    if (is_string($inner)) {
        return $inner;
    }
    /** @var mixed $content */
    $content = is_array($inner) ? $inner['content'] ?? null : null;
    /** @var mixed $content_value */
    $content_value = is_array($content) ? $content['value'] ?? null : null;
    return is_string($content_value) ? $content_value : null;
}

/**
 * The tag names Elementor publishes as rendered markup for a widget prop, or
 * null when it publishes none for it.
 *
 * @return list<string>|null
 */
function el_published_markup_tags(string $widget_type, string $setting): ?array
{
    $class = 'Elementor\\Modules\\AtomicWidgets\\PropTypes\\Escaped_Html_Prop_Type';
    if (!class_exists($class) || !method_exists($class, 'get_allowed_html_tags_for_prop')) {
        return null;
    }
    /** @var mixed $raw */
    $raw = $class::get_allowed_html_tags_for_prop($widget_type, $setting);
    if (!is_array($raw)) {
        return null;
    }
    $tags = [];
    /** @var mixed $tag */
    foreach ($raw as $tag) {
        if (!is_string($tag) || $tag === '') {
            continue;
        }
        $tags[] = strtolower($tag);
    }
    return $tags === [] ? null : $tags;
}
