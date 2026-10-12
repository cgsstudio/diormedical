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

wp_register_ability('novamira/bricks-read-css', [
    'label' => __('Read Bricks CSS', domain: 'novamira-pro'),
    'description' => __(
        'Returns the compiled CSS of a Bricks post or template: on sites that serve CSS from external files the content of the post\'s CSS file (what the front end loads), otherwise the CSS Bricks generates inline for the requested areas. Pass selector_contains to get only the rules whose selector contains that text (an element id such as "brxe-abc123", a class name, a variable) instead of the whole stylesheet. Bounded to 256 KiB; css_truncated says when the file was cut. The global-class rules the page uses are appended to whichever source is returned, because Bricks compiles those inline instead of writing them into the post file; includes says exactly what is covered and what lives elsewhere.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer', 'description' => 'The WordPress post or Bricks template id.'],
            'area' => [
                'type' => 'string',
                'enum' => ['header', 'content', 'footer', 'all'],
                'default' => 'all',
                'description' => 'Inline generation only: which areas to compile. A CSS file always covers the whole post.',
            ],
            'selector_contains' => [
                'type' => 'string',
                'description' => 'Return only the rules whose selector contains this text (case-sensitive). The full css is omitted then.',
            ],
        ],
        'required' => ['post_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'css_source' => [
                'type' => 'string',
                'enum' => ['file', 'inline'],
                'description' => '"file" when css is the post\'s external CSS file, "inline" when it was generated from the stored elements.',
            ],
            'file_status' => [
                'type' => 'string',
                'enum' => ['present', 'missing', 'not_applicable'],
                'description' => 'External file mode: whether post-<id>.min.css exists. "missing" means the page is not served that file, so the element styles compiled for it are absent until a write or a regeneration creates it; global classes and theme styles come from elsewhere and still apply. The inline generation is returned instead.',
            ],
            'css' => [
                'type' => 'string',
                'description' => 'The stylesheet (omitted when selector_contains is passed).',
            ],
            'css_bytes' => [
                'type' => 'integer',
                'description' => 'Size of everything assembled for this post — the source plus the appended global-class rules — before any cut.',
            ],
            'css_truncated' => [
                'type' => 'boolean',
                'description' => 'Whether what is returned was cut at 256 KiB. True means rules past that point are neither returned nor searched, so a selector_contains miss is not proof the selector is absent.',
            ],
            'includes' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'What the returned CSS covers, and what it does not. Read it before concluding that a selector is unused: global-class rules are compiled inline by Bricks rather than written into the post file, and site-wide CSS (theme styles, variables, palette) is somewhere else entirely.',
            ],
            'rules' => [
                'type' => 'array',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'selector' => ['type' => 'string'],
                        'declarations' => ['type' => 'string'],
                        'context' => ['type' => 'string'],
                    ],
                ],
                'description' => 'Present with selector_contains: the matching rules; context carries the enclosing @media / @layer preludes.',
            ],
            'matched_count' => ['type' => 'integer'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_read_css',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use selector_contains with the element id ("brxe-<id>") to confirm one element\'s rules after a write, or with a variable name to see where a token is used. Global classes, variables, palette and theme styles are not in a per-post file — they live in their own stylesheets; check them through the design-store abilities.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_read_css(array $input): array|WP_Error
{
    $post_id = (int) $input['post_id'];
    if (!get_post($post_id) instanceof WP_Post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
    }
    $area = (string) ($input['area'] ?? 'all');
    $areas = $area === 'all' ? array_keys(bricks_area_meta_keys()) : [$area];

    ['css' => $css, 'fields' => $fields, 'warnings' => $warnings] = bricks_read_css_source($post_id, $areas);
    $result = ['post_id' => $post_id] + $fields;

    /** @var mixed $needle */
    $needle = $input['selector_contains'] ?? null;
    if (is_string($needle) && $needle !== '') {
        $rules = array_values(array_filter(bricks_css_rules($css), static fn(array $rule): bool => str_contains(
            (string) $rule['selector'],
            $needle,
        )));
        $result['rules'] = $rules;
        $result['matched_count'] = count($rules);
    }
    if (!isset($result['rules'])) {
        $result['css'] = $css;
    }
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }

    return $result;
}

/**
 * The stylesheet of a post: its CSS file when the site serves files and
 * the file exists, otherwise the inline generation for the given areas.
 *
 * @param list<string> $areas
 * @return array{css: string, fields: array<string, mixed>, warnings: list<string>}
 */
function bricks_read_css_source(int $post_id, array $areas): array
{
    $warnings = [];
    // Global-class rules live outside the post's CSS, so they are appended to
    // whichever source is used below. Without them a search for a class name
    // comes back empty on a page that is styled by exactly that class.
    $global_classes = bricks_global_classes_css(bricks_get_area_elements($post_id, area: 'content'));
    $appendix = trim($global_classes) === ''
        ? ''
        : "\n/* ---- global classes, compiled inline by Bricks and not part of the post CSS ---- */\n"
        . $global_classes;

    if (bricks_css_loading_is_file()) {
        $file = bricks_post_css_file_content($post_id);
        if ($file['size'] > 0 || $file['css'] !== '') {
            // The file is read bounded; the class rules are appended AFTER it,
            // so the bound has to be re-applied to the whole payload and the
            // reported size has to count both. Otherwise a large class returns
            // hundreds of KiB while css_bytes reports the size of the file.
            return bricks_bounded_css_payload(
                $file['css'] . $appendix,
                $file['size'] + strlen($appendix),
                ['css_source' => 'file', 'file_status' => 'present', 'includes' => bricks_css_includes($appendix)],
                $warnings,
            );
        }
        $warnings[] = sprintf(
            'post-%d.min.css does not exist, so the front end is not serving the element styles compiled for this post; global classes and theme styles come from elsewhere and can still apply, so open the page to see how much is affected. A content write through these abilities asks Bricks to regenerate the file and reports the outcome in css_file — read that rather than assuming it worked — or regenerate the CSS files from Bricks > Settings > Performance. The inline generation is returned instead.',
            $post_id,
        );
    }

    $parts = [];
    foreach ($areas as $candidate) {
        $parts[] = bricks_render_area_css(bricks_get_area_elements($post_id, $candidate), $candidate);
    }
    $css = implode('', $parts) . $appendix;

    return bricks_bounded_css_payload(
        $css,
        strlen($css),
        [
            'css_source' => 'inline',
            'file_status' => bricks_css_loading_is_file() ? 'missing' : 'not_applicable',
            'includes' => bricks_css_includes($appendix),
        ],
        $warnings,
    );
}

/**
 * Applies the size bound to the CSS that is actually returned, and reports
 * the true size of what was assembled.
 *
 * @param array<string, mixed> $fields
 * @param list<string>         $warnings
 * @return array{css: string, fields: array<string, mixed>, warnings: list<string>}
 */
function bricks_bounded_css_payload(string $css, int $bytes, array $fields, array $warnings): array
{
    $fields['css_bytes'] = $bytes;
    // Compare the TRUE assembled size, not the string in hand: the file branch
    // hands over content that was already cut when it was read, so measuring
    // the string says "nothing was truncated" about a payload that was.
    $fields['css_truncated'] = $bytes > NOVAMIRA_BRICKS_RETURN_CSS_LIMIT;
    if ($fields['css_truncated'] !== true) {
        return ['css' => $css, 'fields' => $fields, 'warnings' => $warnings];
    }

    $warnings[] = sprintf(
        'The CSS for this post is %d bytes; css holds the first 256 KiB and rules past it are not searched either. Narrow the read with selector_contains, or read one area at a time.',
        $bytes,
    );

    return [
        'css' => substr($css, offset: 0, length: NOVAMIRA_BRICKS_RETURN_CSS_LIMIT),
        'fields' => $fields,
        'warnings' => $warnings,
    ];
}

/**
 * Flat list of the rules in a stylesheet, with the enclosing at-rule
 * preludes (@media, @layer, @supports) as context. A small brace walker —
 * enough for the CSS Bricks emits; comments are stripped first.
 *
 * @return list<array{selector: string, declarations: string, context: string}>
 */
/**
 * What the returned CSS covers, so a caller cannot read "no match" as "not
 * styled". Everything not listed here is somewhere else, and the list says
 * where.
 *
 * @return list<string>
 */
function bricks_css_includes(string $global_classes_css): array
{
    $includes = ['this post\'s element CSS'];
    if (trim($global_classes_css) !== '') {
        $includes[] = 'the global classes its elements use';
    }
    $includes[] = 'NOT included: theme styles, variables and colour palette (site-wide, read them with novamira/bricks-get-design-system), nor the CSS of header/footer templates (read those with novamira/bricks-read-css on the template post id)';

    return $includes;
}

function bricks_css_rules(string $css): array
{
    $css = (string) preg_replace('#/\*.*?\*/#s', replacement: '', subject: $css);
    $rules = [];
    /** @var list<string> $stack */
    $stack = [];
    $buffer = '';
    $length = strlen($css);
    for ($i = 0; $i < $length; $i++) {
        $char = $css[$i];
        if ($char === '{') {
            $prelude = trim($buffer);
            $buffer = '';
            if ($prelude !== '' && $prelude[0] === '@' && !bricks_css_at_rule_holds_declarations($prelude)) {
                $stack[] = $prelude;
                continue;
            }
            $close = bricks_css_block_end($css, $i);
            $rules[] = [
                'selector' => $prelude,
                'declarations' => trim(substr($css, $i + 1, $close - $i - 1)),
                'context' => implode(' ', $stack),
            ];
            $i = $close;
            continue;
        }
        if ($char === '}') {
            array_pop($stack);
            $buffer = '';
            continue;
        }
        $buffer .= $char;
    }

    return $rules;
}

/**
 * At-rules whose block holds declarations rather than nested rules.
 */
function bricks_css_at_rule_holds_declarations(string $prelude): bool
{
    foreach (['@font-face', '@page', '@counter-style', '@property', '@font-feature-values'] as $name) {
        if (str_starts_with($prelude, $name)) {
            return true;
        }
    }

    return false;
}

/**
 * Index of the brace closing the block opened at `$open`.
 */
function bricks_css_block_end(string $css, int $open): int
{
    $depth = 0;
    $length = strlen($css);
    for ($i = $open; $i < $length; $i++) {
        if ($css[$i] === '{') {
            $depth++;
            continue;
        }
        if ($css[$i] !== '}') {
            continue;
        }
        $depth--;
        if ($depth === 0) {
            return $i;
        }
    }

    return $length - 1;
}
