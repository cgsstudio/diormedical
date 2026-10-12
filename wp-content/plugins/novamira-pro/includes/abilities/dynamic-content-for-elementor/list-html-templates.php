<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\DynamicContentForElementor;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * How many raw rows one call will look at before it stops and hands back a cursor
 * to carry on from. The `renders` filter is applied in PHP, so a page of it costs
 * a walk over the post type, and this is the budget one request is allowed to
 * spend on that walk. It is a budget per call, not a ceiling on the set: the
 * cursor resumes the walk exactly where it stopped, so every row is reachable
 * however many calls it takes.
 */
const DCE_LIST_SCAN_BUDGET = 2000;

/**
 * How many rows that walk fetches at a time. Each batch primes the post meta it
 * then reads, so the batch size is the memory the walk costs at once.
 */
const DCE_LIST_SCAN_BATCH = 200;

/**
 * Prefix of the continuation token, so a value from somewhere else is refused
 * rather than read as a place in the listing.
 */
const DCE_LIST_CURSOR_PREFIX = 'dce2:';

/**
 * The query var that carries the keyset bound into WP_Query. Private to this
 * file: the `posts_where` filter that reads it is added around one query and
 * taken off again, so nothing else on the site can see either.
 */
const DCE_LIST_AFTER_VAR = 'novamira_dce_list_after';

/**
 * The WP_Query hook the keyset comparison is attached to, for the length of one
 * query and no longer.
 */
const DCE_LIST_KEYSET_FILTER = 'posts_where';

wp_register_ability('novamira/dynamic-content-for-elementor-list-html-templates', [
    'label' => __('List Dynamic Content for Elementor HTML Templates', domain: 'novamira-pro'),
    'description' => __(
        'Lists the HTML Templates the Dynamic Content for Elementor PDF generator renders from, newest first. Returns one compact row per template: id, title, status, and how it renders — "html" for a template holding its own markup, "elementor_template" for one that renders an Elementor template by id. Use novamira/dynamic-content-for-elementor-get-html-template to read a single template in full, including its code. Trashed templates are excluded unless status is given. Pages with a cursor: when truncated is true the response carries next_cursor, and passing that back as cursor returns the rows that follow. Keep going while truncated is true. When it is false, nothing further was found ahead of the cursor: the cursor remembers the last row it handed you rather than how far it had counted, so templates created or deleted anywhere in the set while you walk never make it repeat a row or skip one, but a template that changes so that it joins or leaves the set part-way through — retitled into or out of your search, restaged, switched between rendering its own markup and rendering an Elementor template, or given a date that moves it in the ordering — can come back twice or not at all. Walk again from the start when you need a set that is certainly current. That is the templates changing under you, which is a separate thing from changing search, status or renders yourself: do not do that part-way through a walk, because a cursor belongs to the query that produced it and is refused by any other.',
        domain: 'novamira-pro',
    ),
    'category' => 'dynamic-content-for-elementor',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'search' => [
                'type' => 'string',
                'maxLength' => 1600,
                'description' => 'Match against the template title, and against the post body and excerpt, which templates of this type normally leave empty. It does not reach the stored markup, so a template cannot be found here by what its HTML contains. WordPress ignores a search longer than this and would return everything instead, so it is refused here.',
            ],
            'status' => [
                'type' => 'array',
                'items' => [
                    'type' => 'string',
                    'enum' => ['publish', 'draft', 'pending', 'private', 'future', 'trash'],
                ],
                'description' => 'Post statuses to include. Defaults to everything except trash and auto-draft.',
            ],
            'renders' => [
                'type' => 'string',
                'enum' => ['html', 'elementor_template'],
                'description' => 'Return only templates that render this way.',
            ],
            'limit' => [
                'type' => 'integer',
                'minimum' => 1,
                'maximum' => 500,
                'description' => 'Maximum rows to return. Defaults to 100, capped at 500.',
            ],
            'cursor' => [
                'type' => 'string',
                'maxLength' => 128,
                'description' => 'Where to carry on from: the next_cursor of the previous response. Omit it for the first page. It is opaque — pass it back exactly as given, do not build one — and it is bound to the search, status and renders you used, so changing any of those means starting again without a cursor.',
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
                    'properties' => [
                        'id' => ['type' => 'integer'],
                        'title' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'renders' => [
                            'type' => 'string',
                            'description' => '"html" or "elementor_template".',
                        ],
                        'is_elementor_template' => ['type' => 'boolean'],
                        'elementor_template_id' => ['type' => 'integer'],
                        'format' => ['type' => 'string'],
                        'orientation' => ['type' => 'string'],
                        'code_bytes' => [
                            'type' => 'integer',
                            'description' => 'Size of the stored markup. Zero means an empty template only when renders is "html"; a template rendering an Elementor template holds no markup here by design.',
                        ],
                    ],
                    'required' => ['id', 'title', 'status', 'renders'],
                    'additionalProperties' => false,
                ],
            ],
            'total' => [
                'type' => 'integer',
                'description' => 'How many rows this call returned, which is not how many templates match when truncated is true. Nothing here counts the matching set; walk the cursor to the end if you need that number. novamira/dynamic-content-for-elementor-check-setup reports how many templates exist altogether, which takes no account of search, status or renders.',
            ],
            'truncated' => [
                'type' => 'boolean',
                'description' => 'True when this page is not the end: either more templates matched than fitted, or the call spent its scan budget with rows still unexamined. Either way next_cursor is there to carry on with. False means nothing further was found ahead of the cursor, which is the end of the walk rather than a promise about the set: a template that joined it behind the cursor, in the ways described above, is not in what you have seen.',
            ],
            'next_cursor' => [
                'type' => 'string',
                'description' => 'Present only when truncated is true. Pass it back as cursor to get the rows that follow. A page can be empty and still carry one, which happens when the call spent its budget on rows that did not match; carry on with it rather than concluding there is nothing left.',
            ],
        ],
        'required' => ['templates', 'total', 'truncated'],
        'additionalProperties' => false,
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\DynamicContentForElementor\\dynamic_content_for_elementor_list_html_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Start here to find a template id. Rows carry no markup; fetch one template to read its code. While truncated is true, call again with cursor set to next_cursor — an empty page with a cursor means keep going, not stop.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function dynamic_content_for_elementor_list_html_templates(array $input = []): array|WP_Error
{
    $limit = max(1, min(500, (int) ($input['limit'] ?? 100)));
    $wanted = isset($input['renders']) && is_string($input['renders']) ? $input['renders'] : '';
    // `future` is included because check-setup counts it and get reads it: three
    // answers about the same templates should not disagree about which exist.
    $default_statuses = ['publish', 'draft', 'pending', 'private', 'future'];

    $status = isset($input['status']) && is_array($input['status'])
        ? array_values(array_filter($input['status'], callback: 'is_string'))
        : $default_statuses;

    $search = isset($input['search']) && is_string($input['search']) ? trim($input['search']) : '';
    $statuses = $status === [] ? $default_statuses : $status;

    // The cursor is bound to the query that produced it. A position means "this
    // many rows of that query", and handing it to a different query would silently
    // address different rows — the kind of wrongness a caller cannot see.
    $query = [
        'statuses' => $statuses,
        'search' => $search,
        'wanted' => $wanted,
        'signature' => dce_list_signature($statuses, $search, $wanted),
    ];

    $start = dce_list_cursor_row($input['cursor'] ?? null, $query['signature']);
    if ($start instanceof WP_Error) {
        return $start;
    }

    return dce_walk_templates($query, $limit, $start);
}

/**
 * Identify the query a cursor belongs to. Only the parts that decide which rows
 * exist and in what order go in; `limit` deliberately does not, so a caller may
 * ask for a smaller or larger page half-way through a walk.
 *
 * @param list<string> $statuses
 */
function dce_list_signature(array $statuses, string $search, string $wanted): string
{
    $sorted = $statuses;
    sort($sorted);

    return substr(sha1(implode('|', $sorted) . "\n" . $search . "\n" . $wanted), offset: 0, length: 12);
}

/**
 * The row a cursor stands for, or the reason it is not usable. Null means "start
 * at the beginning", which is what no cursor at all means.
 *
 * @return array{date: string, id: int}|null|WP_Error
 */
function dce_list_cursor_row(mixed $cursor, string $signature): array|null|WP_Error
{
    if ($cursor === null || $cursor === '') {
        return null;
    }

    $invalid = dce_error(
        'invalid_cursor',
        message: 'That cursor did not come from this listing, or came from one asked with a different search, status or renders. Call again without a cursor to start the walk over.',
    );

    if (!is_string($cursor) || !str_starts_with($cursor, DCE_LIST_CURSOR_PREFIX)) {
        return $invalid;
    }

    $matches = [];
    $body = substr($cursor, offset: strlen(DCE_LIST_CURSOR_PREFIX));
    if (preg_match('/^([0-9a-f]{12}):(\d{14}):(\d+)$/', $body, $matches) !== 1 || $matches[1] !== $signature) {
        return $invalid;
    }

    return dce_list_row_key(dce_list_expand_date($matches[2]), (int) $matches[3]);
}

/**
 * One row's place in the ordering. A named shape rather than an inline array so
 * that the cursor, the scan and the query all agree on what a place is.
 *
 * @return array{date: string, id: int}
 */
function dce_list_row_key(string $date, int $id): array
{
    return ['date' => $date, 'id' => $id];
}

/**
 * Mint the token that resumes the walk after a given row.
 *
 * @param array{date: string, id: int} $row
 */
function dce_list_cursor(string $signature, array $row): string
{
    $compact = preg_replace('/\D+/', replacement: '', subject: $row['date']);

    return (
        DCE_LIST_CURSOR_PREFIX
        . $signature
        . ':'
        . str_pad((string) $compact, length: 14, pad_string: '0')
        . ':'
        . $row['id']
    );
}

/**
 * Turn the fourteen digits back into the datetime the column holds.
 */
function dce_list_expand_date(string $compact): string
{
    return (
        substr($compact, offset: 0, length: 4)
        . '-'
        . substr($compact, offset: 4, length: 2)
        . '-'
        . substr($compact, offset: 6, length: 2)
        . ' '
        . substr($compact, offset: 8, length: 2)
        . ':'
        . substr($compact, offset: 10, length: 2)
        . ':'
        . substr($compact, offset: 12, length: 2)
    );
}

/**
 * One page of the listing, and where to carry on from.
 *
 * The `renders` filter cannot be pushed into the query — the flag lives inside a
 * serialised array, not in its own meta row — so the post type is walked and
 * filtered in PHP. Two things follow, and both are what the cursor exists for.
 *
 * The walk costs raw rows, so one call is allowed a budget of them; spending it
 * is not the end of the set and is never reported as such. And the boundary
 * between pages is a row rather than a count: a count is only true of the set as
 * it stood when it was taken, so a row inserted ahead of it repeats a row already
 * handed over and a row deleted ahead of it skips one never handed over at all.
 * The cursor names the last row it gave out — its date and its id — and the next
 * call asks for what sorts after that row, so inserts and deletions elsewhere in
 * the set cannot move the boundary.
 *
 * The limit of that, which the ability description states as well: the boundary
 * is a place in an ordering, so a row that changes its own place in it — a date
 * edited — or its own membership of the set — a title edited into or out of the
 * search, a status changed, a template switched between the two rendering modes —
 * can fall on the wrong side of a boundary the walk has already passed. Only a
 * snapshot of the whole set would close that, which is more than a listing should
 * cost.
 *
 * @param array{statuses: list<string>, search: string, wanted: string, signature: string} $query
 * @param array{date: string, id: int}|null $start
 * @return array<string, mixed>
 */
function dce_walk_templates(array $query, int $limit, ?array $start): array
{
    // One matching row beyond the page is the evidence that there is more.
    $scan = dce_scan_templates($query, $limit + 1, $start);

    $page = array_slice($scan['matched'], offset: 0, length: $limit);
    $rows = [];
    foreach ($page as $entry) {
        $rows[] = $entry['row'];
    }

    $truncated = count($scan['matched']) > count($page) || !$scan['exhausted'];

    $result = [
        'templates' => $rows,
        'total' => count($rows),
        'truncated' => $truncated,
    ];

    if ($truncated) {
        // Carry on after the last row handed back. When the page is empty that is
        // the last row looked at, which is strictly further into the ordering
        // than the walk started, so a caller following the cursor always moves.
        $resume = $page === [] ? $scan['position'] : $page[count($page) - 1]['after'];
        if ($resume !== null) {
            $result['next_cursor'] = dce_list_cursor($query['signature'], $resume);
        }
    }

    return $result;
}

/**
 * Walk raw rows from a row, collecting the ones that match, until there are
 * `$needed` of them, the post type runs out, or this call's scan budget is spent.
 *
 * Answers with what it found, the last row it looked at, and whether stopping
 * there was the end of the post type — the three things a page and its cursor are
 * built from. Each entry records the row it came from, so a page boundary can be
 * placed between two rows of the same batch.
 *
 * @param array{statuses: list<string>, search: string, wanted: string, signature: string} $query
 * @param array{date: string, id: int}|null $start
 * @return array{matched: list<array{row: array<string, mixed>, after: array{date: string, id: int}}>, position: array{date: string, id: int}|null, exhausted: bool}
 */
function dce_scan_templates(array $query, int $needed, ?array $start): array
{
    // Never smaller than the page itself: an unfiltered listing must not be able
    // to spend its budget before it has even filled one page.
    $budget = max(DCE_LIST_SCAN_BUDGET, $needed);

    /** @var list<array{row: array<string, mixed>, after: array{date: string, id: int}}> $matched */
    $matched = [];
    $after = $start;
    $scanned = 0;
    $exhausted = false;

    while (count($matched) < $needed && $scanned < $budget) {
        // At least a batch, but enough in one go to fill a page that is not being
        // filtered at all, so the common listing stays a single query.
        $size = min($budget - $scanned, max(DCE_LIST_SCAN_BATCH, $needed - count($matched)));
        $posts = dce_query_templates($query['statuses'], $query['search'], $size, $after);

        foreach ($posts as $post) {
            $row = dce_template_row($post);
            $here = dce_list_row_key($post->post_date, $post->ID);
            $after = $here;
            ++$scanned;

            // A batch primes the meta of every post in it, and this meta holds
            // whole templates' markup. Dropping each entry once the row is built
            // keeps the walk's memory to one batch rather than to everything it
            // has ever looked at; the summary row itself carries only the size.
            wp_cache_delete($post->ID, group: 'post_meta');

            if ($query['wanted'] !== '' && $row['renders'] !== $query['wanted']) {
                continue;
            }

            $matched[] = ['row' => $row, 'after' => $here];
            if (count($matched) >= $needed) {
                break;
            }
        }

        if (count($posts) < $size) {
            // A short batch is the end of the post type: what did not match is
            // known not to exist rather than merely not to have been looked at.
            $exhausted = true;
            break;
        }
    }

    if (!$exhausted && count($matched) < $needed) {
        // The budget ran out on a batch that was full to its last row, which says
        // nothing about whether another row follows it. Asking for one settles it,
        // so that a set ending exactly on a batch boundary is reported as ended
        // rather than as "there may be more".
        $more = dce_query_templates($query['statuses'], $query['search'], numberposts: 1, after: $after);
        $exhausted = $more === [];
    }

    return ['matched' => $matched, 'position' => $after, 'exhausted' => $exhausted];
}

/**
 * One raw page of the post type, newest first, starting after a given row.
 *
 * Ordered by id as well as by date, because date alone is not a total order —
 * templates created in the same second share one — and a walk resuming after a
 * row the database may place differently each time would repeat some and skip
 * others.
 *
 * The keyset comparison has to be SQL: WP_Query can express "before this date"
 * but not "after this date-and-id pair", and the pair is what makes the ordering
 * total. The filter is put on for this one query and taken off again, and reads a
 * query var private to this file, so nothing else on the site is affected by it.
 *
 * @param list<string> $statuses
 * @param array{date: string, id: int}|null $after
 * @return list<WP_Post>
 */
function dce_query_templates(array $statuses, string $search, int $numberposts, ?array $after): array
{
    if ($after !== null) {
        add_filter(
            DCE_LIST_KEYSET_FILTER,
            callback: 'Novamira\\Pro\\Abilities\\DynamicContentForElementor\\dce_keyset_where',
            accepted_args: 2,
        );
    }

    try {
        // @mago-expect analysis:possibly-invalid-argument
        /** @var list<WP_Post> $posts */
        $posts = get_posts([
            'post_type' => DCE_TEMPLATE_POST_TYPE,
            'post_status' => $statuses,
            'numberposts' => $numberposts,
            'orderby' => ['date' => 'DESC', 'ID' => 'DESC'],
            'suppress_filters' => false,
            's' => $search,
            DCE_LIST_AFTER_VAR => $after,
        ]);
    } finally {
        if ($after !== null) {
            remove_filter(
                DCE_LIST_KEYSET_FILTER,
                callback: 'Novamira\\Pro\\Abilities\\DynamicContentForElementor\\dce_keyset_where',
            );
        }
    }

    return $posts;
}

/**
 * Narrow a listing query to the rows that sort after one particular row.
 *
 * Does nothing to any query that does not carry this file's own query var, which
 * is every query but the one it was put on for.
 */
function dce_keyset_where(string $where, \WP_Query $wp_query): string
{
    /** @var mixed $after */
    $after = $wp_query->get(DCE_LIST_AFTER_VAR);
    if (!is_array($after) || !isset($after['date'], $after['id'])) {
        return $where;
    }

    global $wpdb;
    /** @var \wpdb $wpdb */
    $date = (string) $after['date'];
    $id = (int) $after['id'];

    // Newest first, so "after" is the smaller pair.
    $clause = " AND ( {$wpdb->posts}.post_date < %s OR ( {$wpdb->posts}.post_date = %s AND {$wpdb->posts}.ID < %d ) )";

    // @mago-expect analysis:possibly-invalid-argument
    $keyset = (string) $wpdb->prepare($clause, $date, $date, $id);

    return $where . $keyset;
}

/**
 * One compact listing row. Carries the size of the markup rather than the markup,
 * so a caller can tell an empty template from a full one without fetching it.
 *
 * @return array{id: int, title: string, status: string, renders: string, is_elementor_template: bool, elementor_template_id: int, format: string, orientation: string, code_bytes: int}
 */
function dce_template_row(WP_Post $post): array
{
    $meta = dce_read_template_meta($post->ID);
    $presented = dce_present_template_meta($meta);

    return [
        'id' => $post->ID,
        'title' => $post->post_title,
        'status' => $post->post_status,
        'renders' => $presented['renders'],
        'is_elementor_template' => $presented['is_elementor_template'],
        'elementor_template_id' => $presented['elementor_template_id'],
        'format' => $presented['format'],
        'orientation' => $presented['orientation'],
        'code_bytes' => strlen($meta[DCE_TEMPLATE_FIELDS['code']]),
    ];
}
