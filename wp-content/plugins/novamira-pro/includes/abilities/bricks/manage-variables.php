<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricks;

use WP_Error;

/**
 * Abilities: Bricks Global Variables (CSS custom properties) — list / get
 * / create / edit / delete. Granular abilities for the variables store.
 * Categories are resolved inline by name on create.
 */

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/bricks-list-variables', [
    'label' => __('List Bricks Global Variables', domain: 'novamira-pro'),
    'description' => __(
        'Lists every Bricks global CSS variable defined on this site. Each entry includes id, name (kebab-case, the part after var(--)), value, and category id when assigned.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'category' => [
                'type' => 'string',
                'description' => 'Optional. Filter to variables in this category id.',
            ],
        ],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'variables' => ['type' => 'array'],
            'categories' => ['type' => 'array'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_list_variables',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_list_variables(array $input): array
{
    /** @var mixed $raw_variables */
    $raw_variables = bricks_routed_option_read(BRICKS_DB_GLOBAL_VARIABLES, []);
    $variables = is_array($raw_variables) ? $raw_variables : [];

    /** @var mixed $raw_categories */
    $raw_categories = bricks_routed_option_read(BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES, []);
    $categories = is_array($raw_categories) ? $raw_categories : [];

    /** @var mixed $filter */
    $filter = $input['category'] ?? null;
    if (is_string($filter) && $filter !== '') {
        $variables = array_values(array_filter(
            $variables,
            static fn(mixed $v): bool => is_array($v) && ($v['category'] ?? null) === $filter,
        ));
    }

    return [
        'variables' => array_values($variables),
        'categories' => array_values($categories),
    ];
}

wp_register_ability('novamira/bricks-get-variable', [
    'label' => __('Get Bricks Global Variable', domain: 'novamira-pro'),
    'description' => __(
        'Reads a single Bricks global variable by its id. Returns id, name, value, category.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The id of the variable to read.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'variable' => ['type' => 'object'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_get_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_get_variable(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['id'] ?? '';
    if (!is_string($id) || $id === '') {
        return bricks_error('invalid_input', message: 'id is required.');
    }

    /** @var mixed $raw_variables */
    $raw_variables = bricks_routed_option_read(BRICKS_DB_GLOBAL_VARIABLES, []);
    $variables = is_array($raw_variables) ? $raw_variables : [];

    /** @var mixed $variable */
    foreach ($variables as $variable) {
        if (is_array($variable) && ($variable['id'] ?? null) === $id) {
            return ['variable' => $variable];
        }
    }

    return bricks_error('not_found', sprintf('Variable "%s" not found.', $id));
}

wp_register_ability('novamira/bricks-create-variable', [
    'label' => __('Create Bricks Global Variable', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new Bricks global CSS variable. Provide a name (the CSS custom property name without its leading dashes: a letter first, then letters/digits/-/_, no spaces — invalid names are rejected, never rewritten; a leading "--" is stripped so "--brand" and "brand" both create var(--brand)), a value, and an optional category. Category accepts an existing category id OR a category name (creates the category on-the-fly if it does not exist). Use this from the tokens-first workflow before referencing var(--name) in element settings — colours, spacing, radii, sizes. NOT for font families: Bricks writes font-family literally, so var() there never resolves; write bare family names in typography controls instead.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'name' => [
                'type' => 'string',
                'description' => 'Variable name without the leading "--" (leading dashes are stripped). Must start with a letter, then letters, digits, "-" or "_" — a name starting with a digit, or holding spaces or other characters, is rejected, not rewritten. Used in CSS as var(--name) exactly as given. Must be unique.',
            ],
            'value' => [
                'type' => 'string',
                'description' => 'CSS value (e.g. "#1A2845", "1rem", "clamp(1rem, 2vw, 1.5rem)", "var(--brand-primary)"). A font stack such as "Inter, sans-serif" is accepted but cannot be consumed by font-family controls on Bricks.',
            ],
            'category' => [
                'type' => 'string',
                'description' => 'Optional. Existing category id, or a name for a new category to create.',
            ],
        ],
        'required' => ['name', 'value'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('variables'),
            'variable' => ['type' => 'object'],
            'css_file' => [
                'type' => 'string',
                'description' => 'External CSS file mode: "regenerated" when global-variables.min.css was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise.',
            ],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            'unresolved_variables' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_create_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * Validates the name of a global variable: the CSS custom property name
 * without its leading dashes. Leading dashes are stripped (a name copied
 * with them would be stored as `----name` and never resolve); the rest must
 * start with a letter and continue with letters, digits, "-" or "_".
 * Anything else — whitespace included — is rejected rather than rewritten,
 * so the name the agent references is exactly the one stored.
 */
function var_normalize_name(mixed $raw_name): string|WP_Error
{
    if (!is_string($raw_name) || trim($raw_name) === '') {
        return bricks_error('invalid_input', message: 'name is required.');
    }

    $name = ltrim(trim($raw_name), characters: '-');
    if (preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $name) !== 1) {
        return bricks_error('invalid_input', sprintf(
            'Variable name "%s" is invalid. Give the CSS custom property name without the leading dashes: a letter first, then letters, digits, "-" or "_" — no spaces or other characters, nothing is rewritten (e.g. "brand-primary" for var(--brand-primary)).',
            $raw_name,
        ));
    }

    return $name;
}

function bricks_create_variable(array $input): array|WP_Error
{
    $name = var_normalize_name($input['name'] ?? '');
    if ($name instanceof WP_Error) {
        return $name;
    }

    /** @var mixed $value */
    $value = $input['value'] ?? '';
    if (!is_string($value)) {
        return bricks_error('invalid_input', message: 'value is required.');
    }

    /** @var mixed $raw_variables */
    $raw_variables = bricks_routed_option_read(BRICKS_DB_GLOBAL_VARIABLES, []);
    $variables = is_array($raw_variables) ? $raw_variables : [];

    /** @var mixed $existing */
    foreach ($variables as $existing) {
        if (is_array($existing) && ($existing['name'] ?? null) === $name) {
            return bricks_error('duplicate_name', sprintf('name "%s" already exists.', $name));
        }
    }

    /** @var mixed $category_input */
    $category_input = $input['category'] ?? null;
    $category_id = is_string($category_input)
        ? bricks_resolve_category($category_input, BRICKS_DB_GLOBAL_VARIABLES_CATEGORIES)
        : null;

    $variable = [
        'id' => bricks_random_id(prefix: 'var-'),
        'name' => $name,
        'value' => $value,
    ];
    if ($category_id !== null) {
        $variable['category'] = $category_id;
    }

    $variables[] = $variable;
    $css_file = var_save_variables($variables);

    return var_verified(var_with_value_notes(['variable' => $variable, 'css_file' => $css_file], $value), $variables);
}

/**
 * Adds the read-back verification of the variables option to a response.
 *
 * @param array<string, mixed>          $result
 * @param array<array-key, mixed>|null  $intended null when the row was deleted
 * @return array<string, mixed>
 */
function var_verified(array $result, ?array $intended): array
{
    return bricks_with_option_write_verification(
        $result,
        BRICKS_DB_GLOBAL_VARIABLES,
        $intended === null ? null : array_values($intended),
        read: static fn(): mixed => bricks_routed_option_read(BRICKS_DB_GLOBAL_VARIABLES, default_value: null),
    );
}

/**
 * Persists the variables option (external file mode: the CSS directory is
 * made sure to exist so Bricks' listener can rewrite global-variables.min.css)
 * and returns the css_file status.
 *
 * @param array<array-key, mixed> $variables
 */
function var_save_variables(array $variables): string
{
    bricks_ensure_css_dir();
    $started = time();
    var_option_write(BRICKS_DB_GLOBAL_VARIABLES, $variables);
    bricks_forget_known_css_variables();

    return bricks_global_css_file_status('global-variables.min.css', $started);
}

/**
 * Adds the advisories a variable value deserves: a font stack cannot be
 * consumed through var() on Bricks, and a var() reference to a missing
 * token resolves to nothing.
 *
 * @param array<string, mixed> $result
 * @return array<string, mixed>
 */
function var_with_value_notes(array $result, string $value): array
{
    $warnings = [];
    if (
        !str_contains($value, 'var(')
        && preg_match('/^[A-Za-z][A-Za-z0-9 \'"-]*(,\s*[A-Za-z][A-Za-z0-9 \'"-]*)+$/', $value) === 1
    ) {
        $warnings[] = sprintf(
            'Value "%s" looks like a font-family stack. Bricks writes font-family values literally in quotes, so a typography control cannot use this variable (var() becomes a family named "var(--…)" and no Google Font loads). Set the bare family name directly in typography controls (with the rest in "fallback") and keep variables for colours, spacing, radii and sizes.',
            $value,
        );
    }
    $unresolved = bricks_unresolved_variables($value);
    if ($unresolved !== []) {
        $result['unresolved_variables'] = $unresolved;
        $warnings[] = bricks_unresolved_variables_warning($unresolved);
    }
    if (
        isset($result['css_file'])
        && is_string($result['css_file'])
        && str_starts_with($result['css_file'], 'skipped(')
    ) {
        $warnings[] =
            'External CSS file mode is on but global-variables.min.css was not written: '
            . substr($result['css_file'], offset: 8, length: -1)
            . '. The frontend keeps the previous variable values until it is.';
    }
    if ($warnings !== []) {
        $result['warnings'] = $warnings;
    }

    return $result;
}

wp_register_ability('novamira/bricks-edit-variable', [
    'label' => __('Edit Bricks Global Variable', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing Bricks global variable\'s value. The name and category are immutable after creation (renaming would break every var(--name) reference in pages). To rename, delete and create a new variable.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The id of the variable to edit.'],
            'value' => ['type' => 'string', 'description' => 'New CSS value.'],
        ],
        'required' => ['id', 'value'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('variables'),
            'variable' => ['type' => 'object'],
            'css_file' => [
                'type' => 'string',
                'description' => 'External CSS file mode: "regenerated" when global-variables.min.css was rewritten, "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise.',
            ],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
            'unresolved_variables' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_edit_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

function bricks_edit_variable(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['id'] ?? '';
    /** @var mixed $value */
    $value = $input['value'] ?? null;
    if (!is_string($id) || $id === '') {
        return bricks_error('invalid_input', message: 'id is required.');
    }
    if (!is_string($value)) {
        return bricks_error('invalid_input', message: 'value is required.');
    }

    /** @var mixed $raw_variables */
    $raw_variables = bricks_routed_option_read(BRICKS_DB_GLOBAL_VARIABLES, []);
    $variables = is_array($raw_variables) ? $raw_variables : [];

    /** @var array<string, mixed>|null $updated */
    $updated = null;
    $variable_keys = array_keys($variables);
    foreach ($variable_keys as $vk) {
        /** @var mixed $variable */
        $variable = $variables[$vk];
        if (!is_array($variable) || ($variable['id'] ?? null) !== $id) {
            continue;
        }
        $variable['value'] = $value;
        $variables[$vk] = $variable;
        $updated = $variable;
        break;
    }

    if ($updated === null) {
        return bricks_error('not_found', sprintf('Variable "%s" not found.', $id));
    }

    $css_file = var_save_variables($variables);

    return var_verified(var_with_value_notes(['variable' => $updated, 'css_file' => $css_file], $value), $variables);
}

wp_register_ability('novamira/bricks-delete-variable', [
    'label' => __('Delete Bricks Global Variable', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a Bricks global variable by id. On sites whose Bricks declares a variables trash the variable is moved there, the way the builder does, so it can be restored from the Variable Manager; otherwise it is removed for good and deleted_permanently says so. The response says which happened (trashed). Either way any var(--name) reference in pages falls back to the CSS default until the variable is restored or re-created.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricks',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The id of the variable to delete.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            ...bricks_option_write_verification_schema('variables'),
            'deleted' => ['type' => 'boolean'],
            'trashed' => [
                'type' => 'boolean',
                'description' => 'True when the variable was moved to the Bricks variables trash (restorable from the Variable Manager), false when it was removed for good because this site keeps no trash.',
            ],
            'restore' => ['type' => 'string', 'description' => 'Present when trashed: where to restore it from.'],
            'deleted_permanently' => [
                'type' => 'string',
                'description' => 'Present when trashed is false: says that this Bricks install declares no variables trash, so the variable is gone for good, and how to bring it back.',
            ],
            'css_file' => [
                'type' => 'string',
                'description' => 'External CSS file mode: "regenerated" when global-variables.min.css was rewritten without this variable (or removed with the last one), "inline" when Bricks serves CSS inline, "skipped(reason)" otherwise.',
            ],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Bricks\bricks_delete_variable',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true, 'type' => 'tool'],
        'annotations' => [
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * The last variable: the option row goes away, as the builder's own writer
 * does it, and the stale stylesheet is cleared through Bricks' generator
 * (no option listener fires on a delete).
 *
 * @param  array{trashed: bool, restore?: string, deleted_permanently?: string} $trash
 * @return array<string, mixed>
 */
function var_delete_last_variable(array $trash): array
{
    var_option_write(BRICKS_DB_GLOBAL_VARIABLES, []);
    bricks_forget_known_css_variables();
    if (
        bricks_css_loading_is_file()
        && class_exists(\Bricks\Assets_Global_Variables::class)
        && method_exists(\Bricks\Assets_Global_Variables::class, 'generate_css_file')
    ) {
        \Bricks\Assets_Global_Variables::generate_css_file([]);
    }

    return var_verified(
        $trash
        + [
            'deleted' => true,
            'css_file' => bricks_global_css_file_removed_status('global-variables.min.css'),
        ],
        intended: null,
    );
}

function bricks_delete_variable(array $input): array|WP_Error
{
    /** @var mixed $id */
    $id = $input['id'] ?? '';
    if (!is_string($id) || $id === '') {
        return bricks_error('invalid_input', message: 'id is required.');
    }

    /** @var mixed $raw_variables */
    $raw_variables = bricks_routed_option_read(BRICKS_DB_GLOBAL_VARIABLES, []);
    $variables = is_array($raw_variables) ? $raw_variables : [];

    $filtered = array_values(array_filter(
        $variables,
        static fn(mixed $v): bool => !(is_array($v) && ($v['id'] ?? null) === $id),
    ));

    if (count($filtered) === count($variables)) {
        return bricks_error('not_found', sprintf('Variable "%s" not found.', $id));
    }
    $removed = null;
    /** @var mixed $candidate */
    foreach ($variables as $candidate) {
        if (!is_array($candidate) || ($candidate['id'] ?? null) !== $id) {
            continue;
        }
        $removed = bricks_string_keyed($candidate);
    }
    $trash = var_trash_state($removed);
    if ($trash instanceof WP_Error) {
        return $trash;
    }

    // Deleting the last variable removes the option row, as the builder does,
    // instead of persisting an empty array.
    if ($filtered === []) {
        return var_delete_last_variable($trash);
    }

    return var_verified($trash + ['deleted' => true, 'css_file' => var_save_variables($filtered)], $filtered);
}

/**
 * A trash entry reduced to what identifies its content: every stored field
 * except the deletion timestamp, in a stable key order.
 *
 * @param array<array-key, mixed> $entry
 */
function var_trash_comparable(array $entry): string
{
    unset($entry['deletedAt']);
    ksort($entry);

    return maybe_serialize($entry);
}

/**
 * Whether the installed Bricks implements a variables trash — the builder
 * declares the trash store itself. A stored row is deliberately NOT a
 * signal: a row left behind by a downgrade, by another plugin or by a
 * manual write says nothing about whether this builder can restore from
 * it, and trusting it would make bricks-delete-variable promise a Variable
 * Manager trash the site cannot open.
 */
function bricks_variables_trash_available(): bool
{
    return defined('BRICKS_DB_GLOBAL_VARIABLES_TRASH');
}

/** The trash store this build declares, or null when it declares none. */
function bricks_variables_trash_option(): ?string
{
    return bricks_variables_trash_available() ? (string) constant('BRICKS_DB_GLOBAL_VARIABLES_TRASH') : null;
}

/**
 * Moves a deleted variable into the Bricks variables trash when the site
 * keeps one — the entry as stored plus the deletion time, one entry per id —
 * and returns the response fields that say what happened.
 *
 * Ordering is the safety property: the move happens BEFORE the variable is
 * removed from the active store, and the trash is read back before the
 * removal is allowed to proceed. The trash is the only way back, so a move
 * that did not stick has to stop the deletion — reporting it beside a
 * completed deletion would be describing data loss, not preventing it.
 *
 * @param array<string, mixed>|null $variable the removed entry
 * @return array{trashed: bool, restore?: string, deleted_permanently?: string}|WP_Error
 */
function var_trash_state(?array $variable): array|WP_Error
{
    $option = bricks_variables_trash_option();
    if ($variable === null || $option === null) {
        return [
            'trashed' => false,
            'deleted_permanently' => 'This Bricks install keeps no variables trash, so the variable was removed for good and cannot be restored from the Variable Manager. Re-create it with novamira/bricks-create-variable to bring it back.',
        ];
    }
    $entry = $variable;
    $entry['deletedAt'] = var_deleted_at_now();
    $variable_id = (string) ($variable['id'] ?? '');

    var_write_store($option, var_trash_with_entry(var_read_store($option), $entry, $variable_id));

    // Observed, not deduced from the rule we just applied: the trash has to
    // hold exactly one entry for this id and it has to be this variable's
    // value, because that is what the response is about to promise.
    $mismatch = var_trash_mismatch($option, $entry, $variable_id);
    if ($mismatch !== null) {
        return bricks_error('trash_not_stored', sprintf(
            'The variable "%s" was NOT deleted: it is still in the active store, untouched. Moving it into the Bricks variables trash was attempted first, and the trash was then read back: %s. A deletion completes here only when the trash holds exactly one entry for this variable carrying the value being deleted, so nothing was removed. Read novamira/bricks-list-variables to see what is stored; to remove it anyway, delete it from the Bricks builder\'s Variable Manager.',
            (string) ($variable['name'] ?? $variable_id),
            $mismatch,
        ));
    }

    return ['trashed' => true, 'restore' => 'Bricks builder > Variable Manager > Trash'];
}

/**
 * The deletion stamp to write, in the unit the builder writes it.
 *
 * OBSERVED, not assumed: the shipped builder bundle stamps trashed items with
 * JavaScript's Date.now() — `u = Date.now(); … o.deletedAt = u` in
 * assets/js/main.min.js, and the same in iframe.min.js — which is
 * MILLISECONDS since the epoch. PHP's time() is seconds, so a stamp written
 * that way is a thousandth of the value the builder writes: to the builder's
 * own de-duplication (one row per id, newest deletedAt wins) it reads as 1970
 * and loses to every real row, and to ours it made every real row look newer
 * than a deletion happening right now.
 *
 * NOT an int. A millisecond stamp is about 1.8e12, and PHP_INT_MAX on a
 * 32-bit build is 2.1e9, so an int return would be unrepresentable there —
 * and this product requires only PHP 8.0, not a 64-bit build. It is a float,
 * which is an IEEE double on every build: integers are exact in one up to
 * 2^53, so millisecond stamps stay exact to the millisecond until the year
 * 287396. The store already holds floats on 32-bit installs regardless of
 * what we write, because Bricks' stamps arrive as JSON numbers and json_decode
 * hands back a float for any integer its int type cannot hold.
 */
function var_deleted_at_now(): float
{
    return round(microtime(as_float: true) * 1000);
}

/**
 * A stored deletion stamp in milliseconds, whichever unit it was written in,
 * as a float for the reason given on {@see var_deleted_at_now()}.
 *
 * A store can hold both units: the builder writes milliseconds, and this
 * surface wrote seconds until that was corrected. They are told apart by
 * magnitude at 1e11. EXACTLY what that does, because a rule of thumb stated
 * loosely is how the wrong unit got compared in the first place:
 *
 *  - anything that is not a number, is not finite, or is not above zero reads
 *    as 0, so it can never outrank a real stamp;
 *  - a value at or above 1e11 is read as milliseconds, unchanged;
 *  - a value below 1e11 is read as seconds and multiplied by 1000.
 *
 * So a stamp is read in the unit it was written for every instant between
 * 1973-03-03 (the first millisecond value at or above the threshold) and the
 * year 5138 (the last second value below it). OUTSIDE that window the unit is
 * guessed wrong, and BOTH ends behave the same way: what it costs depends on
 * where the number lands relative to now, not on which direction the guess
 * went. Measured, both sides, on a run at 2026-08-31 15:58 UTC:
 *
 *  - a millisecond stamp from before 1973-03-03 is read as seconds and lands
 *    a thousand times later than it means. 1e9 ms is 1970-01-12 and lands on
 *    2001-09-09, which LOSES to a deletion now; 1.8e9 ms is 1970-01-21 and
 *    lands on 2027-01-15, which REFUSES it. The crossover is the current time
 *    divided by 1000 — a millisecond stamp dated 1970-01-21 16:43 UTC on that
 *    run;
 *  - a seconds stamp from beyond the year 5138 is read as milliseconds and
 *    lands a thousand times earlier. 2e11 s is the year 8307 and lands on
 *    1976-05-03, which loses; 2e12 s is the year 65347 and lands on
 *    2033-05-18, which REFUSES. The crossover is the current time in
 *    milliseconds read as a number of seconds — a seconds stamp dated
 *    58635-07-19 UTC on that run;
 *  - both crossovers creep later as time passes, because both are the current
 *    time;
 *  - a corrupt but finite stamp far outside any date range — 1e30, say — is
 *    read as-is, outranks everything, and refuses.
 *
 * Over-refusal is the deliberate direction: a stamp we cannot make sense of
 * is not a reason to delete a live value, and the refusal says what was seen
 * and leaves the variable untouched.
 */
function var_deleted_at_ms(mixed $value): float
{
    if (!is_int($value) && !is_float($value) && !(is_string($value) && is_numeric($value))) {
        return 0.0;
    }
    $number = (float) $value;
    if (!is_finite($number) || $number <= 0.0) {
        return 0.0;
    }

    return $number >= 100_000_000_000.0 ? $number : $number * 1000.0;
}

/**
 * The trash to store: every row for another id untouched, plus exactly ONE
 * row for this id — the newest of what is already there and the entry being
 * moved, ties going to the entry being moved because it is the newer write.
 *
 * Selected across the COMPLETE set rather than by walking the rows in order,
 * and compared in one unit — see {@see var_deleted_at_ms()}, because the
 * builder's stamps and the ones this surface used to write are a thousand
 * times apart.
 * Deciding as it went made the LAST matching row win instead of the newest,
 * and left two rows for one id in the store; the builder's own normaliser
 * then dropped whichever of them it did not keep, which could be the value
 * that had just been deleted.
 *
 * @param  list<mixed>          $stored
 * @param  array<string, mixed> $entry
 * @return list<array<string, mixed>>
 */
function var_trash_with_entry(array $stored, array $entry, string $variable_id): array
{
    $others = [];
    $winner = $entry;
    /** @var mixed $row */
    foreach ($stored as $row) {
        if (!is_array($row)) {
            continue;
        }
        if ((string) ($row['id'] ?? '') !== $variable_id) {
            $others[] = bricks_string_keyed($row);

            continue;
        }
        if (var_deleted_at_ms($row['deletedAt'] ?? null) > var_deleted_at_ms($winner['deletedAt'] ?? null)) {
            $winner = bricks_string_keyed($row);
        }
    }
    $others[] = $winner;

    return $others;
}

/**
 * Why the trash does not hold what the response is about to promise, or null
 * when it does: exactly one entry for this id, carrying this variable's
 * stored value.
 *
 * @param array<string, mixed> $entry
 */
function var_trash_mismatch(string $option, array $entry, string $variable_id): ?string
{
    $rows = [];
    /** @var mixed $row */
    foreach (var_read_store($option) as $row) {
        if (!is_array($row) || (string) ($row['id'] ?? '') !== $variable_id) {
            continue;
        }
        $rows[] = bricks_string_keyed($row);
    }
    if ($rows === []) {
        return 'it holds no entry for this variable at all';
    }
    if (count($rows) > 1) {
        return sprintf(
            'it holds %d entries for this variable id, so which of them a restore returns is not settled here',
            count($rows),
        );
    }
    if (var_trash_comparable($rows[0]) !== var_trash_comparable($entry)) {
        return 'the entry it holds for this variable id carries a different value from the one being deleted';
    }

    return null;
}

/**
 * Writes the active variables or their trash to the site that holds them.
 * An empty store removes the option row — what the builder's own writer
 * does, and what these abilities already did for the active store.
 *
 * Reads go through {@see bricks_routed_option_read()}, which every other
 * surface that touches this family uses too.
 *
 * @param array<array-key, mixed> $items
 */
function var_option_write(string $option, array $items): void
{
    $items = array_values($items);
    if (method_exists(\Bricks\Helpers::class, 'save_global_variables_array_option')) {
        \Bricks\Helpers::save_global_variables_array_option($option, $items);

        return;
    }
    $site = bricks_variable_option_site($option);
    if ($site === null) {
        if ($items === []) {
            delete_option($option);

            return;
        }
        update_option($option, $items);

        return;
    }
    if ($items === []) {
        delete_blog_option($site, $option);

        return;
    }
    update_blog_option($site, $option, $items);
}

/**
 * The trash store as a list.
 *
 * @return list<mixed>
 */
function var_read_store(string $option): array
{
    return bricks_routed_option_rows($option);
}

/**
 * @param list<mixed> $items
 */
function var_write_store(string $option, array $items): void
{
    var_option_write($option, $items);
}
