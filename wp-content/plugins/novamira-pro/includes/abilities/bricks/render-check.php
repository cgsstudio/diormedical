<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-render-check', [
    'label' => __('Bricks Render Check', domain: 'novamira-pro'),
    'description' => __(
        'Fetches a Bricks post or template through a loopback request, as a visitor would, and reports what actually rendered: whether the request reached the site at all (reachable), HTTP status (a PHP error on render shows up as a non-200), transport error, whether Bricks rendered the page, whether the Bricks CSS is linked and which CSS files, how many nestable roots (slider, tabs, accordion, nav) appear in the markup, and a fatal flag. Read reachable first: when it is false the server could not open the URL — a network fact that says nothing about the page — and every other field is unknown rather than false. Persisted trees can be valid and still fail to render — run this after a page is built, or when the front end does not match what was written. Costs one request with a short timeout; nothing else triggers it.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or Bricks template id. Drafts are not public: publish first, or expect the status a visitor gets.',
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'url' => ['type' => 'string'],
            'reachable' => [
                'type' => 'boolean',
                'description' => 'Whether the request reached the site at all. FALSE means nothing else here describes the page: the server could not open the URL, which is a network fact and not a verdict on the page. Read this before reading any other field.',
            ],
            'status' => [
                'type' => 'integer',
                'description' => 'HTTP status the target answered with; 0 when the request never reached it (reachable false, see transport_error).',
            ],
            'transport_error' => [
                'type' => 'string',
                'description' => 'Present when the request itself could not be made (status 0): the transport error.',
            ],
            'fatal' => [
                'type' => ['boolean', 'null'],
                'description' => 'True on a 5xx status or when the response is a WordPress critical-error page. NULL when reachable is false: no response arrived, so this was not determined.',
            ],
            'bricks_rendered' => [
                'type' => ['boolean', 'null'],
                'description' => 'Whether Bricks element markup (brxe- classes) is in the response. NULL when reachable is false — there is no response to look at, and that is not the same as "did not render".',
            ],
            'css_linked' => [
                'type' => ['boolean', 'null'],
                'description' => 'File mode: a stylesheet under bricks/css is linked; inline mode: the Bricks inline stylesheet is present. NULL when reachable is false.',
            ],
            'css_files' => [
                'type' => ['array', 'null'],
                'items' => ['type' => 'string'],
                'description' => 'File names linked from bricks/css (file mode). NULL when reachable is false.',
            ],
            'nestable_roots' => [
                'type' => ['object', 'null'],
                'description' => '{slider, tabs, accordion, nav, total}: nestable roots found in the markup. NULL when reachable is false.',
            ],
            'element_count' => [
                'type' => ['integer', 'null'],
                'description' => 'Number of brxe- elements in the markup. NULL when reachable is false.',
            ],
            'title' => ['type' => ['string', 'null']],
            'issues' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'What is wrong with the render, each with the next step.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_render_check',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Read reachable first: when it is false the probe never got a response and every render field is null, so the page has NOT been shown to be broken — see issues[] for what to try. A non-200 with bricks_rendered false usually means a settings value in a shape Bricks cannot iterate or a fatal in a code element: read the last written elements with bricks-get-element and fix them. bricks_rendered true with css_linked false on a file-mode site means the CSS file is missing (bricks-check-setup, bricks-read-css). nestable_roots counts markup roots, not stored elements: compare with the tree you wrote.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_render_check(array $input): array|WP_Error
{
    $post_id = (int) $input['post_id'];
    /** @var mixed $post */
    $post = get_post($post_id);
    if (!$post instanceof WP_Post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
    }
    $url = get_permalink($post);
    if ($url === '') {
        return bricks_error('no_url', sprintf('Post %d has no public URL to fetch.', $post_id));
    }

    $fetched = bricks_loopback_fetch($url);
    $body = $fetched['body'];
    $css_loading = bricks_css_loading_is_file() ? 'file' : 'inline';
    $transport_error = $fetched['error'] ?? null;
    $result = [
        'post_id' => $post_id,
        'url' => $url,
        'reachable' => $transport_error === null,
        'status' => $fetched['status'],
    ];
    if ($transport_error !== null) {
        // No response came back. Every render conclusion is genuinely unknown,
        // so it is returned as null — a false here would be read as "checked
        // and not rendered", which is the opposite of what happened.
        $result += [
            'fatal' => null,
            'bricks_rendered' => null,
            'css_linked' => null,
            'css_files' => null,
            'nestable_roots' => null,
            'element_count' => null,
            'title' => null,
            'transport_error' => $transport_error,
        ];
        $result['issues'] = bricks_render_issues($post, $result, $css_loading);

        return $result;
    }
    $result += [
        'fatal' => $fetched['status'] >= 500 || str_contains($body, 'There has been a critical error on this website'),
        'bricks_rendered' => str_contains($body, 'brxe-'),
        'css_linked' => bricks_body_links_bricks_css($body, $css_loading),
        'css_files' => bricks_linked_bricks_css_files($body),
        'nestable_roots' => bricks_count_nestable_roots($body),
        'element_count' => bricks_count_matches('/class="[^"]*\bbrxe-/', $body),
        'title' => bricks_html_title($body),
    ];
    $result['issues'] = bricks_render_issues($post, $result, $css_loading);

    return $result;
}

/**
 * What is wrong with a render, each with the next step.
 *
 * @param array<string, mixed> $result the response so far
 * @return list<string>
 */
function bricks_render_issues(WP_Post $post, array $result, string $css_loading): array
{
    $url = (string) $result['url'];
    $status = (int) $result['status'];
    $issues = [];
    if (isset($result['transport_error'])) {
        $issues[] = sprintf(
            'The request to %s never reached the site (%s). NOTHING here describes the page: reachable is false, and bricks_rendered / css_linked are unknown rather than false. The server cannot open its own front-end URL — usual on a host that maps the site to a port on the outside, or behind a firewall — which says nothing about whether visitors can. Open the URL in a browser to see the real state; the site owner can point the probe at an address the server can reach through the novamira/bricks/render-probe-base filter.',
            $url,
            (string) $result['transport_error'],
        );

        return $issues;
    }
    if ($post->post_status !== 'publish') {
        $issues[] = sprintf(
            'Post %d is "%s", not published: a visitor (and this check) gets the public response, not the draft content.',
            $post->ID,
            $post->post_status,
        );
    }
    if ($status !== 0 && $status !== 200) {
        $issues[] = sprintf(
            '%s answered with HTTP %d%s, so the target was reached and it is the page that failed. Something in the rendered content fails at runtime (a settings value in a shape Bricks cannot iterate, a fatal in a code element): read the last written elements with novamira/bricks-get-element and fix them, or check the PHP error log.',
            $url,
            $status,
            $result['fatal'] === true ? ' (fatal)' : '',
        );

        return $issues;
    }
    if ($status !== 200) {
        return $issues;
    }
    if ($result['bricks_rendered'] !== true) {
        $issues[] = sprintf(
            '%s rendered without Bricks markup: the post is not in Bricks editor mode (editor_mode in bricks-get-content) or a template with matching conditions replaces its content.',
            $url,
        );

        return $issues;
    }
    if ($result['css_linked'] !== true) {
        $issues[] = sprintf(
            '%s carries no Bricks CSS (%s), so the element styles compiled for it are not reaching it. Global classes and theme styles come from elsewhere and can still apply, so open the page to see how much is affected. Make one content write to the post and read its css_file field — it says "regenerated", or "skipped(<reason>)" when the file could not be written — or regenerate the CSS files from Bricks > Settings > Performance.',
            $url,
            $css_loading === 'file' ? 'no file under bricks/css is linked' : 'no inline Bricks stylesheet',
        );
    }

    return $issues;
}

/**
 * File names linked from the Bricks CSS directory in a page.
 *
 * @return list<string>
 */
function bricks_linked_bricks_css_files(string $body): array
{
    $matches = [];
    preg_match_all('#bricks/css/([A-Za-z0-9_.\-]+\.css)#', $body, $matches);

    return array_values(array_unique($matches[1]));
}

/**
 * Nestable roots present in the markup, by family.
 *
 * @return array{slider: int, tabs: int, accordion: int, nav: int, total: int}
 */
function bricks_count_nestable_roots(string $body): array
{
    $families = [
        'slider' => ['brxe-slider-nested', 'brxe-slider', 'brxe-carousel'],
        'tabs' => ['brxe-tabs-nested', 'brxe-tabs'],
        'accordion' => ['brxe-accordion-nested', 'brxe-accordion'],
        'nav' => ['brxe-nav-nested', 'brxe-nav-menu'],
    ];
    $counts = ['slider' => 0, 'tabs' => 0, 'accordion' => 0, 'nav' => 0, 'total' => 0];
    foreach ($families as $family => $classes) {
        foreach ($classes as $class) {
            $counts[$family] += bricks_count_matches(
                '/class="[^"]*\b' . preg_quote($class, delimiter: '/') . '(?![\w-])/',
                $body,
            );
        }
        $counts['total'] += $counts[$family];
    }

    return $counts;
}

function bricks_html_title(string $body): string
{
    $m = [];

    return preg_match('#<title>(.*?)</title>#is', $body, $m) === 1 ? html_entity_decode(trim($m[1])) : '';
}

/**
 * Number of matches of a pattern in a body (0 on a regex failure).
 */
function bricks_count_matches(string $pattern, string $body): int
{
    $matches = [];
    $count = preg_match_all($pattern, $body, $matches);

    return $count === false ? 0 : $count;
}
