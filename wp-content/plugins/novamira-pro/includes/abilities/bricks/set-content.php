<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;
use WP_Post;

/**
 * Ability: Set Bricks element data for a post or template.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-set-content', [
    'label' => __('Set Bricks Content', domain: 'novamira-pro'),
    'description' => __(
        'Sets the Bricks element tree for a given post or template area. DESTRUCTIVE: replaces the entire element array for the specified area (use bricks-insert-content to add and bricks-patch-elements to edit). Also sets the editor mode to "bricks" so the post renders via Bricks. Before the first write on a site, call novamira/bricks-list-elements (element="<name>" for controls, include_shared_controls=true for the shared style keys) and load the "bricks-build-page" skill via novamira/skill-get — it holds the HTML-to-native mapping, the tokens-first workflow and the verification loop. Model one native element per semantic node: section > container > heading / text-basic / button / image / block. NEVER paste raw HTML into a text, text-basic or code element: a text element holds one paragraph of prose, not a section\'s markup or a <style> block; layout markup inside text is reported as raw_layout_html_ids[] and a section/container/block without children as empty_structural_ids[]. The raw "code" element is gated: a tree containing one is rejected unless you pass allow_code_elements:true after the user explicitly confirms they want raw HTML/code. Use var(--token) / rem values, never raw px.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => [
                'type' => 'integer',
                'description' => 'The WordPress post or template ID.',
            ],
            'area' => [
                'type' => 'string',
                'description' => 'Which area to set: "header", "content", or "footer".',
                'enum' => ['header', 'content', 'footer'],
            ],
            'elements' => [
                'type' => 'array',
                'description' => 'Array of element objects. Each element needs at minimum: id (unique string), name (element type), and settings (object).',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'id' => [
                            'type' => 'string',
                            'description' => 'Unique element ID of exactly 6 characters (e.g. "abc123"). Optional: omit it and a 6-character id is generated server-side and returned in generated_ids (keyed by the element\'s index in this array). Descendants reference their parent by id, so an element that omits its id can only be a root or a leaf. New ids of any other length and duplicate ids are rejected; ids already stored in this area may be re-sent unchanged.',
                        ],
                        'name' => [
                            'type' => 'string',
                            'description' => 'Element type (e.g. "heading", "text", "container").',
                        ],
                        'parent' => [
                            'type' => ['string', 'integer'],
                            'description' => 'Parent element ID, or 0 for root-level elements.',
                        ],
                        'settings' => ['type' => 'object', 'description' => 'Element settings/control values.'],
                    ],
                    'required' => ['name', 'settings'],
                ],
            ],
            'return_css' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'If true, return the CSS for this write: in external CSS file mode the regenerated per-post CSS file, otherwise the inline CSS Bricks generates for the area (css_source says which). Useful for verifying that settings produced the expected styles.',
            ],
            'allow_code_elements' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Confirmation gate for the Bricks "code" element (raw HTML/CSS/JS), which is a last resort. By default a tree containing a "code" element is rejected with guidance to use native elements. Set true ONLY after the user has explicitly confirmed they want a raw code/HTML element.',
            ],
            'force' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Write even when another user currently holds the WordPress edit lock on this post (their builder or editor session is open). Without it such a write is refused with locked_by_other_user naming the user.',
            ],
        ],
        'required' => ['post_id', 'area', 'elements'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'area' => ['type' => 'string'],
            'element_count' => ['type' => 'integer'],
            'revision_id' => [
                'type' => ['integer', 'null'],
                'description' => 'Id of the WordPress revision created before this write, carrying the Bricks element meta of every area as it was — restorable from the Bricks builder\'s revision panel or WordPress. Null when revisions are disabled for the post type (a warning says so).',
            ],
            'generated_ids' => [
                'type' => 'object',
                'description' => 'Present when elements were sent without an id: maps the 0-based index of each such element in the request to the id generated for it (6 lowercase hex characters, the format Bricks itself uses).',
            ],
            'saved' => [
                'type' => 'boolean',
                'description' => 'Deprecated alias of verified (kept for existing callers; it is no longer a literal true). Read verified instead.',
            ],
            'verified' => [
                'type' => 'boolean',
                'description' => 'True only when the element tree read back from the database after the write equals the tree that was written. False means the stored content differs — the warnings say how.',
            ],
            'stored_element_count' => [
                'type' => 'integer',
                'description' => 'Number of elements the area holds in the database after the write (read back, not computed from the request).',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => '"regenerated" when Bricks serves CSS from external files and the per-post CSS file was rewritten for this write; "inline" when Bricks serves CSS inline (nothing to regenerate); "skipped(reason)" when file mode is on but the file could not be written — the reason names the fix.',
            ],
            'unresolved_variables' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when a written value references var(--name) for a name that is neither a Bricks global variable, a palette colour nor a custom property declared in the site\'s custom CSS: those declarations are dropped by the browser until the token exists.',
            ],
            'warnings' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Validation warnings about the element data (data is still saved).',
            ],
            'raw_layout_html_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when a text / text-basic / text-link element carries layout markup, <style>, <script> or inline style attributes in its text: rebuild those as native elements.',
            ],
            'empty_structural_ids' => [
                'type' => 'array',
                'items' => ['type' => 'string'],
                'description' => 'Present when a section / container / block was written without children.',
            ],
            'css' => [
                'type' => 'string',
                'description' => 'Present only when return_css=true. In external CSS file mode the content of the post\'s regenerated CSS file (what the front end loads, every area of the post); otherwise the inline CSS Bricks generates for this area. css_source says which.',
            ],
            'css_source' => [
                'type' => 'string',
                'enum' => ['file', 'inline'],
                'description' => 'Present only when return_css=true: "file" when css is the regenerated per-post CSS file, "inline" when it is the inline generation result.',
            ],
            'css_truncated' => [
                'type' => 'boolean',
                'description' => 'Present (true) only when css_source is "file" and the file exceeded the return_css limit (256 KiB): css holds the first part and a warning names the file size.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_set_content',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Elements need: id (unique string of exactly 6 characters, e.g. "abc123"), name, parent (parent element id or 0 for root), and settings. Omit id to have a six-character id generated. The children array is computed automatically from parent references — you do not need to provide it. Prefer `bricks-insert-content` when adding a new subtree to an existing page, and `bricks-patch-elements` for targeted fixes on existing elements.

Style reuse hierarchy. Before writing raw values:
  Tier 1: respect active theme styles (do not override h1-h6/p/a defaults in element settings).
  Tier 2: apply existing global class via _cssGlobalClasses (look up via novamira/bricks-list-global-classes; attach via novamira/bricks-apply-global-class).
  Tier 3: reference existing variable: "color": {"raw": "var(--brand-primary)"} (look up via novamira/bricks-list-variables).
  Tier 4: create a token first via novamira/bricks-create-variable / bricks-create-global-class / bricks-add-color-palette-entry, then reference it.
  Tier 5: raw values in controls — only for non-systemic one-offs (animation timing, single transforms, clip-paths).
  Tier 6: _cssCustom — last resort.
Hex colors, px values on _padding/_margin/_gap, font sizes/weights/family, radii → NEVER raw. Always tokenize.

Common element names: section, container, div, block, heading, text-basic, text, button, image, icon, video, divider.

Key settings by element type:

Containers/sections — use the flex/grid keys in the "Value formats" section below. Do NOT use unprefixed keys like "direction" or "justifyContent" — they are silently dropped.

Heading — text (string), tag ("h1"–"h6").

Text/text-basic — text (string).

Button — text (string), link ({type:"external",url:"..."}). The `type` must be one of external / internal / taxonomy / meta / lightboxImage / lightboxVideo / media — `"url"` is NOT valid and the button renders with no href.

Image — image ({url:"...",id:0}).

Value formats (critical — wrong shapes are silently dropped by Bricks):

- Number + units controls: scalar string with unit. Examples:
    "_rowGap": "var(--space-l)", "_columnGap": "var(--space-m)", "_gridGap": "var(--space-l)",
    "_widthMax": "var(--content-width)", "_widthMin": "20rem"
  NEVER use {"value": 24, "unit": "px"} — this gets dropped. Layout-family keys (_rowGap, _columnGap, _gridGap, _direction) exist only on section/container/block/div; every other element uses _gap / _flexDirection.

- Typography sub-values: scalar strings.
    "_typography": {
      "font-size": "var(--text-5xl)",
      "line-height": "1.15em",
      "letter-spacing": "0.05em",
      "font-weight": "700",
      "text-align": "center",
      "text-transform": "uppercase",
      "color": {"raw": "var(--text-1)"}
    }

- Spacing controls (_padding, _margin): per-side strings with units.
    "_padding": {"top": "var(--space-3xl)", "right": "var(--space-xl)", "bottom": "var(--space-3xl)", "left": "var(--space-xl)"}
  "auto" is a valid value for margin sides.

- _border: flat shape at top level — NOT nested per side.
    "_border": {
      "width":  {"top": "1px", "right": "1px", "bottom": "1px", "left": "1px"},
      "style":  "solid",
      "color":  {"raw": "var(--brand-primary)"},
      "radius": {"top": "var(--radius-m)", "right": "var(--radius-m)", "bottom": "var(--radius-m)", "left": "var(--radius-m)"}
    }
  For radius-only (e.g. buttons), omit width/style/color.

- _background: {"color": {"raw": "var(--bg-1)"}}. Use _cssCustom for gradients.

- _cssGlobalClasses: flat list of class-ID strings, NOT {id, name} objects.
    "_cssGlobalClasses": ["clshov", "u-card"]
  The {id, name, settings} shape returned by bricks-list-global-classes is the class *definition* — elements reference classes by ID only. Passing objects here would fatally crash Bricks at render time, so the call is rejected.

- _cssCustom: MUST be a full rule block with braces. Use %root% as the
  element selector placeholder — it is auto-replaced with #brxe-{id} on save.
    "_cssCustom": "%root% { display: inline-block; text-transform: uppercase; }"
    "_cssCustom": "%root%:hover { opacity: .8; }"
    "_cssCustom": "@media (max-width: 48em) { %root% { grid-template-columns: 1fr; } }"
  Bare declarations without braces are silently dropped.

- Flex layout on container/section/block/div:
    "_display":       "flex" | "inline-flex" | "grid" | "inline-grid" | "block" | "inline-block" | "inline" | "none"
    "_direction":     "row" | "column" | "row-reverse" | "column-reverse"
    "_alignItems":    "flex-start" | "center" | "flex-end" | "stretch" | "baseline"
    "_justifyContent":"flex-start" | "center" | "flex-end" | "space-between" | ...
    "_flexWrap":      "nowrap" | "wrap" | "wrap-reverse"
    "_rowGap", "_columnGap": scalar strings like "var(--space-m)" or "1rem"

- Grid layout:
    "_display":             "grid"
    "_gridTemplateColumns": "repeat(3, 1fr)" | "1fr 2fr 1fr" | ...
    "_gridGap":             "var(--space-l)"

Settings map to CSS through Bricks control definitions. Only settings that correspond to registered controls generate CSS. Use _cssCustom for any CSS that does not map to a built-in control.

CSS injection
-------------
Do NOT use the "code" element to inject CSS or JS. It renders as a visible <pre> block on the page. For page-scoped CSS use bricks-set-settings with:

  {"post_id": <id>, "settings": {"customCss": "/* your CSS */"}}

For page-scoped JS use customScriptsHeader, customScriptsBodyHeader, or customScriptsBodyFooter (see bricks-set-settings annotation). There is no customJs key.

Global CSS/JS (applies to all Bricks pages) uses the same keys without post_id.

Use novamira/bricks-list-elements with element="<name>" to get the full control schema for any element type. Do this before building a tree with unfamiliar elements — the controls list is the only authoritative source for which settings keys exist and what values they accept. Guessing key names risks silent drops or rendering errors.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

function bricks_set_content(array $input): array|WP_Error
{
    $post_id = (int) $input['post_id'];
    /** @var WP_Post|null $post */
    $post = get_post($post_id);

    if (!$post) {
        return bricks_error('not_found', sprintf('Post %d not found.', $post_id));
    }

    $area = (string) $input['area'];
    /** @var list<array<string, mixed>> $elements */
    $elements = $input['elements'];
    $code_ids = bricks_collect_code_element_ids($elements);
    if ($code_ids !== [] && ($input['allow_code_elements'] ?? false) !== true) {
        return bricks_code_element_block($code_ids);
    }

    $stored_elements = bricks_get_area_elements($post_id, $area);

    // Conform root parents before generating ids for elements sent without one.
    $elements = bricks_normalize_root_parents($elements);
    ['elements' => $elements, 'generated_ids' => $generated_ids] = bricks_assign_missing_ids($elements);

    $unsafe_id_error = bricks_unsafe_new_element_ids_error($elements, $stored_elements);
    if ($unsafe_id_error instanceof WP_Error) {
        return $unsafe_id_error;
    }

    $elements = bricks_normalize_write_elements($elements);

    $validation = bricks_validate_elements($elements);
    $warnings = $validation['warnings'];
    $errors = $validation['errors'];

    if (count($errors) > 0) {
        return bricks_error('invalid_elements', implode(' ', $errors));
    }

    foreach (sc_area_warnings($post, $area) as $area_warning) {
        $warnings[] = $area_warning;
    }

    $elements = bricks_ensure_children($elements);
    $commit = bricks_commit_area_write($post_id, $area, $elements, $warnings, [
        'force' => ($input['force'] ?? false) === true,
    ]);
    if ($commit instanceof WP_Error) {
        return $commit;
    }

    return sc_build_result($input, $post_id, $area, $commit, $generated_ids);
}

/**
 * Response shape of a completed set-content write: counts, structural lint
 * output, optional generated CSS and the merged warnings.
 *
 * @param array<array-key, mixed> $input
 * @param array{elements: list<array<string, mixed>>, warnings: list<string>, verified: bool, stored_element_count: int, css_file: string, unresolved_variables: list<string>, revision_id: int|null} $commit
 * @param array<string, string> $generated_ids
 * @return array<string, mixed>
 */
function sc_build_result(array $input, int $post_id, string $area, array $commit, array $generated_ids): array
{
    $elements = $commit['elements'];
    $warnings = $commit['warnings'];
    $result = bricks_with_write_verification([
        'post_id' => $post_id,
        'area' => $area,
        'element_count' => count($elements),
    ], $commit);
    if ($generated_ids !== []) {
        $result['generated_ids'] = bricks_generated_ids_object($generated_ids);
    }
    [$result, $warnings] = bricks_apply_lint_result($result, $warnings, bricks_lint_structure($elements));

    if (($input['return_css'] ?? false) === true) {
        [$result, $warnings] = bricks_append_return_css($result, $elements, $area, $warnings);
    }

    if (count($warnings) > 0) {
        $result['warnings'] = $warnings;
    }

    return $result;
}

/**
 * Collect warnings about post-type / area / template-type mismatches that
 * cause silent breakage (data saved but editor / front-end never reads it).
 *
 * @return list<string>
 */
function sc_area_warnings(WP_Post $post, string $area): array
{
    $warnings = [];

    if (in_array($area, ['header', 'footer'], strict: true) && $post->post_type !== BRICKS_DB_TEMPLATE_SLUG) {
        $warnings[] = sprintf(
            'Writing the "%s" area to a non-template post (type: "%s") has no visible effect — Bricks renders headers and footers from template CPTs with conditions. Create a template of type "%s" instead.',
            $area,
            $post->post_type,
            $area,
        );
    }

    if ($post->post_type === BRICKS_DB_TEMPLATE_SLUG) {
        $template_type = bricks_template_type($post->ID);
        $expected_area = bricks_template_expected_area($template_type);
        if ($area !== $expected_area) {
            $warnings[] = sprintf(
                'Writing the "%s" area on a template of type "%s" — Bricks reads "%s" templates from the "%s" area, so the structure panel will appear empty in the editor. Use area="%s" instead.',
                $area,
                $template_type,
                $template_type,
                $expected_area,
                $expected_area,
            );
        }
    }

    return $warnings;
}
