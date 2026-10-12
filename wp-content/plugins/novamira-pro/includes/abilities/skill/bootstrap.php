<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Skill;

/**
 * Skill system bootstrap.
 *
 * Implements the Agent Skills specification (https://agentskills.io) to the
 * extent that it applies to an MCP-server-hosted agent: progressive
 * disclosure (catalog injected into MCP server instructions, body loaded on
 * demand via the `novamira/skill-get` ability), SKILL.md + YAML frontmatter,
 * lenient validation, and skipping skills without a description.
 *
 * Intentional deviations:
 *
 * - The MCP server cannot reach the client's filesystem, so there is no
 *   file-read activation path — only the dedicated `skill-get` tool.
 * - Bundled resources (scripts, references, assets) ARE enumerated and
 *   their absolute directory is returned by `skill-get`; the agent can
 *   then read them on demand via Novamira's `read-file` ability.
 * - Skills are bundled with the plugin and read-only; there is no project-
 *   vs user-level scoping and therefore no name-collision handling.
 * - Context-compaction protection is a client-side concern and out of scope.
 */

if (!defined('ABSPATH')) {
    exit();
}

const NOVAMIRA_PRO_SKILLS_DIR = __DIR__ . '/../../skills';

/**
 * Cap on bundled resources returned per skill. Keeps the activation payload
 * bounded; if a skill exceeds this, the `resources_truncated` flag is set.
 */
const NOVAMIRA_PRO_SKILL_RESOURCE_LIMIT = 100;

/**
 * Scan the bundled skills directory and return one entry per skill.
 *
 * @return list<array{slug: string, name: string, description: string, path: string, directory: string, content: string, resources: list<string>, resources_truncated: bool}>
 */
function skill_scan(): array
{
    $dir = NOVAMIRA_PRO_SKILLS_DIR;
    if (!is_dir($dir)) {
        return [];
    }

    $skills = [];
    /** @var list<string>|false $glob */
    $glob = glob($dir . '/*/SKILL.md');
    $entries = $glob === false ? [] : $glob;
    sort($entries);

    foreach ($entries as $path) {
        $slug = basename(dirname($path));
        if (!skill_is_available($slug)) {
            continue;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            continue;
        }

        $parsed = skill_parse_frontmatter($raw);

        // A description is essential for disclosure — skip the skill if missing.
        if ($parsed['description'] === '') {
            error_log(sprintf('[novamira-pro] Skipping skill "%s": missing description in frontmatter.', $slug));
            continue;
        }

        // Lenient validation: warn (but still load) when frontmatter name
        // doesn't match the directory slug.
        if ($parsed['name'] !== '' && $parsed['name'] !== $slug) {
            error_log(sprintf(
                '[novamira-pro] Skill "%s": frontmatter name "%s" does not match directory.',
                $slug,
                $parsed['name'],
            ));
        }

        $skill_dir = realpath(dirname($path));
        if ($skill_dir === false) {
            continue;
        }
        $resources_result = skill_enumerate_resources($skill_dir);

        $skills[] = [
            'slug' => $slug,
            'name' => $parsed['name'] !== '' ? $parsed['name'] : $slug,
            'description' => $parsed['description'],
            'path' => $path,
            'directory' => $skill_dir,
            'content' => $parsed['body'],
            'resources' => $resources_result['resources'],
            'resources_truncated' => $resources_result['truncated'],
        ];
    }

    return $skills;
}

/**
 * Hide skills whose runtime requirements are not met on this site, so an agent is
 * never pointed at a skill whose plugin/builder (and therefore its abilities) is
 * not active here.
 *
 * Most skills map 1:1 to an ability category and are gated on that category being
 * registered: the category registrars (novamira_pro_register_*_categories) hold
 * the authoritative per-plugin / per-version gate, so this stays in sync as
 * integrations are added or removed — a new "<plugin>-build-page" /
 * "<plugin>-integration" skill is gated automatically. A few skills need a more
 * specific or cross-plugin gate and are handled explicitly.
 */
function skill_is_available(string $slug): bool
{
    // The Dynamic Shortcodes specialization ships a family of topic skills
    // ("dynamic-shortcodes-<area>") beyond the "-integration" overview. Only the
    // "-integration" one is caught by the default category-suffix check, so gate
    // the whole family on the DSH ability category being registered (which the
    // manifest does only when DSH is active and at or above the version floor).
    if (str_starts_with($slug, 'dynamic-shortcodes-')) {
        return skill_category_registered('dynamic-shortcodes');
    }
    // ORDER IS LOAD-BEARING from here down. The prefix and suffix arms below are
    // NOT mutually exclusive by construction: a slug like "bricks-to-elementor"
    // satisfies both. No bundled slug does today (the tripwire in
    // tests/skill-gating-smoke/01-gating.php asserts that over the real catalog),
    // but if one ever did it would silently take whichever arm comes first and
    // never reach the other's probe — and the two can disagree: on an Elementor
    // site without Bricks the prefix arm answers false while the suffix arm would
    // answer true. Adding such a slug is a routing decision for a human to make
    // here explicitly, which is why the tripwire fails instead of guessing.

    // The Bricks build-page router ships companion skills ("bricks-<topic>":
    // query-loops, nestables, components, page-settings, element-gotchas,
    // verify) that share its gate: the whole family follows the `bricks`
    // ability category. "bricksforge-integration" is a different plugin and
    // does not match the "bricks-" prefix.
    if (str_starts_with($slug, 'bricks-')) {
        return skill_category_registered('bricks');
    }
    // "<plugin>-elementor" skills orchestrate the Elementor surface of a third-party
    // addon and register no abilities, so there is no category to derive their
    // availability from. Dynamic Content is the exception and keeps its own arm in
    // the match below.
    if (str_ends_with($slug, '-elementor') && $slug !== 'dynamic-content-for-elementor') {
        return skill_category_registered('elementor') && skill_elementor_addon_available($slug);
    }
    return match ($slug) {
        // Dynamic Content ships abilities of its own, so it has a category to gate
        // on. Every other "<plugin>-elementor" skill is handled before the match,
        // in skill_elementor_addon_available().
        'dynamic-content-for-elementor' => skill_category_registered('dynamic-content-for-elementor'),
        // Elementor's v4-conversion skill needs more than "Elementor active" — it
        // needs v4 conversion to actually be available on this Elementor build.
        'elementor-convert-to-v4' => function_exists(
            '\\Novamira\\Pro\\Abilities\\Elementor\\el_v4_conversion_skill_available',
        ) && \Novamira\Pro\Abilities\Elementor\el_v4_conversion_skill_available(),
        // Cross-plugin content-model skills: relevant when ANY field-model plugin
        // is active; dynamic-data-binding additionally needs a supported builder.
        'content-model-schema', 'content-model-migration' => skill_any_field_category_registered(),
        'dynamic-data-binding' => skill_any_field_category_registered()
            && (
                skill_category_registered('elementor')
                || skill_category_registered('bricks')
                || skill_category_registered('oxygen')
            ),
        'cf7-integration' => function_exists('\\Novamira\\Pro\\Abilities\\Cf7\\cf7_runtime_available')
            && \Novamira\Pro\Abilities\Cf7\cf7_runtime_available(),
        'astra-integration' => \Novamira\Pro\Abilities\Astra\astra_theme_available(),
        // Site-agnostic skill — always available.
        'novamira-feedback' => true,
        // Everything else: available only when its ability category is registered.
        default => skill_category_available($slug),
    };
}

/**
 * Whether the third-party Elementor addon behind a "<plugin>-elementor" skill is
 * loaded on this site. Keyed by skill slug because these skills register no
 * abilities, so there is no ability category to derive availability from.
 *
 * Each probe checks a constant or class defined when the addon's main plugin file
 * is included, so it is true exactly when that addon is active. The forms are
 * deliberately not uniform: some free and paid editions each define their own
 * constant, so both must be accepted; for other addons, the paid edition alone
 * registers no widgets, so only the free constant is a valid signal. A slug with
 * no entry here stays visible wherever Elementor is active. This is the safe
 * direction because an incorrect probe would silently hide the skill on a site
 * that has the plugin.
 */
function skill_elementor_addon_available(string $slug): bool
{
    return match ($slug) {
        'jetelements-elementor' => class_exists('Jet_Elements'),
        'ultimate-addons-elementor' => defined('UAEL_VER'),
        'unlimited-elements-for-elementor' => defined('UNLIMITED_ELEMENTS_INC'),
        'powerpack-elementor' => defined('POWERPACK_ELEMENTS_VER') || defined('POWERPACK_ELEMENTS_LITE_VER'),
        'element-pack-elementor' => defined('BDTEP_VER'),
        'qi-addons-elementor' => defined('QI_ADDONS_FOR_ELEMENTOR_VERSION'),
        'pafe-elementor' => defined('PAFE_VERSION') || defined('PAFE_PRO_VERSION'),
        'ht-mega-elementor' => defined('HTMEGA_VERSION'),
        'happy-addons-elementor' => defined('HAPPY_ADDONS_VERSION'),
        'xpro-addons-elementor' => defined('XPRO_ELEMENTOR_ADDONS_VERSION'),
        default => true,
    };
}

/**
 * Whether an ability category is currently registered — i.e. its plugin/builder
 * is active and meets the version gate the category registrar applies. Skills are
 * scanned at MCP request time (after wp_abilities_api_categories_init), so the
 * category registry is populated by then.
 */
function skill_category_registered(string $category): bool
{
    if (!class_exists('WP_Ability_Categories_Registry')) {
        return false;
    }
    if (function_exists('wp_has_ability_category')) {
        return wp_has_ability_category($category);
    }

    return \WP_Ability_Categories_Registry::get_instance()?->is_registered($category) ?? false;
}

/**
 * Whether any field-model plugin's ability category is registered (ACF, Pods,
 * JetEngine, Meta Box, ACPT, ASE).
 */
function skill_any_field_category_registered(): bool
{
    foreach (['acf', 'pods', 'jetengine', 'metabox', 'acpt', 'ase'] as $category) {
        if (skill_category_registered($category)) {
            return true;
        }
    }
    return false;
}

/**
 * A plugin/builder skill is available when its ability category is registered.
 * The category is derived from the skill slug ("<category>-build-page" /
 * "<category>-integration", plus the wpbakery-register-element special case); a
 * skill with no plugin category (a site-agnostic playbook) is left available.
 */
function skill_category_available(string $slug): bool
{
    if ($slug === 'wpbakery-register-element') {
        return skill_category_registered('wpbakery');
    }
    foreach (['-build-page', '-integration'] as $suffix) {
        if (str_ends_with($slug, $suffix)) {
            return skill_category_registered(substr($slug, offset: 0, length: -strlen($suffix)));
        }
    }
    return true;
}

/**
 * Recursively list every file under a skill directory except SKILL.md
 * itself. Paths are returned relative to the skill directory, sorted and
 * capped at NOVAMIRA_PRO_SKILL_RESOURCE_LIMIT.
 *
 * @return array{resources: list<string>, truncated: bool}
 */
function skill_enumerate_resources(string $skill_dir): array
{
    if (!is_dir($skill_dir)) {
        return ['resources' => [], 'truncated' => false];
    }

    $resources = [];
    $truncated = false;

    $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(
        $skill_dir,
        \FilesystemIterator::SKIP_DOTS,
    ));

    /** @var \SplFileInfo $file */
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }

        $relative = ltrim(substr($file->getPathname(), strlen($skill_dir)), characters: '/\\');
        $relative = str_replace(search: '\\', replace: '/', subject: $relative);

        if ($relative === 'SKILL.md') {
            continue;
        }

        if (count($resources) >= NOVAMIRA_PRO_SKILL_RESOURCE_LIMIT) {
            $truncated = true;
            break;
        }

        $resources[] = $relative;
    }

    sort($resources);

    return ['resources' => $resources, 'truncated' => $truncated];
}

/**
 * Look up a single skill by slug.
 *
 * @return array{slug: string, name: string, description: string, path: string, directory: string, content: string, resources: list<string>, resources_truncated: bool}|null
 */
function skill_find(string $slug): ?array
{
    foreach (skill_scan() as $skill) {
        if ($skill['slug'] === $slug) {
            return $skill;
        }
    }

    return null;
}

/**
 * Minimal YAML-ish frontmatter parser. Supports only `key: value` lines
 * between a leading `---` and a trailing `---`; anything else is body.
 *
 * @return array{name: string, description: string, body: string}
 */
function skill_parse_frontmatter(string $raw): array
{
    $name = '';
    $description = '';
    $body = $raw;

    $normalized = preg_replace(pattern: '/\r\n?/', replacement: "\n", subject: $raw) ?? $raw;

    if (str_starts_with($normalized, "---\n")) {
        $end = strpos(haystack: $normalized, needle: "\n---\n", offset: 4);
        if ($end !== false) {
            $header = substr(string: $normalized, offset: 4, length: $end - 4);
            $body = ltrim(substr($normalized, $end + 5), characters: "\n");

            foreach (explode("\n", $header) as $line) {
                if (!str_contains($line, ':')) {
                    continue;
                }
                [$key, $value] = explode(separator: ':', string: $line, limit: 2);
                $key = strtolower(trim($key));
                $value = trim($value);
                if ($value !== '' && ($value[0] === '"' || $value[0] === "'")) {
                    $value = trim(string: $value, characters: "\"'");
                }
                if ($key === 'name') {
                    $name = $value;
                    continue;
                }
                if ($key === 'description') {
                    $description = $value;
                }
            }
        }
    }

    return [
        'name' => $name,
        'description' => $description,
        'body' => $body,
    ];
}
