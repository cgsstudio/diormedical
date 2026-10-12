<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Acf;

use Throwable;
use WP_Error;
use WP_Post;

if (!defined('ABSPATH')) {
    exit();
}

wp_register_ability('novamira/acf-create-fieldgroup', [
    'label' => __('Create ACF Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Creates a new ACF field group from a payload in ACF native format (the same shape produced by ACF Tools → Export). Pass the settings FLAT as the arguments — a `title` is required, everything else is optional, and a missing `key` is auto-generated. Unrecognised top-level keys are rejected instead of being silently stored: per-field settings belong inside fields[] and location rules inside location[][]. Post ids are ignored, so a payload copied from novamira/acf-get-fieldgroup-schema can never overwrite an existing post. Before success is reported the saved group is re-read from the database and compared against the payload — the key, every setting supplied, the location rules group by group, and the fields down through their nesting — and a group that does not match is deleted again through ACF, in the database and in Local JSON. Only rows this call can prove it created are removed; anything else it recorded is left in place and named in the error, and the reply says whether the rollback was clean. Refuses if the supplied key already matches an existing field group.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'description' => 'ACF field group native payload, passed flat. `title` is required; a missing `key` is auto-generated. Unknown top-level keys are rejected — per-field settings go inside fields[], location rules inside location[][]. ACF bookkeeping keys (ID, local, local_file, modified, _valid) and the _meta block added by novamira/acf-get-fieldgroup-schema are accepted and ignored, so a schema read can be mutated and written straight back.',
        'properties' => acf_fg_payload_properties(['title' => ['type' => 'string', 'minLength' => 1]]),
        'required' => ['title'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'id' => ['type' => 'integer'],
            'source' => ['type' => 'string', 'const' => 'db'],
        ],
        'required' => ['key', 'id', 'source'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_create_fieldgroup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to register a new ACF field group. The payload mirrors ACF native export format — fetch an existing group with acf-get-fieldgroup-schema for reference. Send the settings as the arguments themselves; do NOT wrap them in a "parameters" object, and do not pass a post id. `title` must be a non-empty string. Pro-only field types (repeater, flexible_content, gallery, clone) are rejected when ACF Pro is not active.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => false,
        ],
    ],
]);

wp_register_ability('novamira/acf-edit-fieldgroup', [
    'label' => __('Edit ACF Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Edits an existing ACF field group in place using ACF native format. Top-level keys (title, location, position, active, etc.) follow PATCH semantics — fields the agent omits are preserved from the existing record. The fields[] array, in contrast, follows REPLACE semantics: any field not in the payload is removed. Renaming a field key orphans values stored under the old key, so preserve keys to keep data. Post ids are ignored, at the group level and anywhere inside fields[], so a payload copied from novamira/acf-get-fieldgroup-schema can never overwrite an existing post. Before success is reported the saved group is re-read from the database and compared against the payload — the key, every setting supplied, the location rules group by group, and the fields down through their nesting. A group that does not match is NOT rolled back: a failed edit is reported, not repaired. The error names the differences found and the field tree the group holds after the write, so the state can be inspected and repaired deliberately rather than guessed at. Refuses field groups whose source is "php" (registered in code).',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'description' => 'ACF field group native payload, passed flat. `key` identifies the group to edit. Unknown top-level keys are rejected — per-field settings go inside fields[], location rules inside location[][]. ACF bookkeeping keys and the _meta block added by novamira/acf-get-fieldgroup-schema are accepted and ignored, so a schema read can be mutated and written straight back.',
        'properties' => acf_fg_payload_properties(['key' => ['type' => 'string', 'pattern' => '^group_']]),
        'required' => ['key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string'],
            'created_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
            'updated_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
            'removed_fields' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
        'required' => ['key', 'created_fields', 'updated_fields', 'removed_fields'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_edit_fieldgroup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use to mutate a field group. A write that does not verify is reported, never undone — re-read the group and repair it rather than assuming the failed call left nothing behind. The fields[] array is REPLACE semantics: anything missing is removed. To partially update, fetch the current state with acf-get-fieldgroup-schema, mutate it, and write it back. Send the settings as the arguments themselves; do NOT wrap them in a "parameters" object, and do not pass post ids. `key` must be the field group key, beginning with group_ — a post id is refused. Fields are matched by key; renaming a key creates a new field and abandons stored values.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/acf-delete-fieldgroup', [
    'label' => __('Delete ACF Field Group', domain: 'novamira-pro'),
    'description' => __(
        'Deletes a field group permanently. Values previously stored under that group remain in postmeta but become unreachable via the ACF API.',
        domain: 'novamira-pro',
    ),
    'category' => 'acf',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'key' => ['type' => 'string', 'pattern' => '^group_'],
        ],
        'required' => ['key'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'deleted' => ['type' => 'boolean'],
            'key' => ['type' => 'string'],
            'orphaned_meta_warning' => ['type' => 'string'],
        ],
        'required' => ['deleted', 'key', 'orphaned_meta_warning'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Acf\acf_delete_fieldgroup',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Use only with explicit user confirmation. Deletion is permanent. Values stored under fields of the deleted group remain in postmeta as orphans, recoverable via raw DB but not via ACF API.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * JSON-schema `properties` map shared by create and edit.
 *
 * Static on purpose: abilities are registered even when ACF is inactive, so the
 * declared schema cannot be derived from ACF at registration time. The list
 * mirrors ACF_Field_Group::get_settings_array(); acf_fg_accepted_keys() unions
 * it with whatever the installed ACF actually supports at call time.
 *
 * @param array<string, array<string, mixed>> $overrides Per-ability property schemas.
 * @return array<string, array<string, mixed>>
 */
function acf_fg_payload_properties(array $overrides = []): array
{
    $properties = [
        'key' => ['type' => 'string'],
        'title' => ['type' => 'string'],
        'fields' => ['type' => 'array'],
        'location' => ['type' => 'array'],
        'menu_order' => ['type' => 'integer'],
        'position' => ['type' => 'string'],
        'style' => ['type' => 'string'],
        'label_placement' => ['type' => 'string'],
        'instruction_placement' => ['type' => 'string'],
        'hide_on_screen' => ['type' => 'array'],
        'active' => ['type' => 'boolean'],
        'description' => ['type' => 'string'],
        'show_in_rest' => ['type' => 'boolean'],
        'display_title' => ['type' => 'string'],
        'allow_ai_access' => ['type' => 'boolean'],
        'ai_description' => ['type' => 'string'],
        // Accepted and ignored. ACF adds these when reading a group, and
        // novamira/acf-get-fieldgroup-schema passes them through, so tolerating
        // them keeps the documented "read a schema, mutate it, write it back"
        // round-trip working. None of them is ever persisted, and `ID` never
        // retargets the write — see acf_fg_strip_bookkeeping_keys().
        'ID' => ['type' => 'integer'],
        'local' => ['type' => 'string'],
        'local_file' => ['type' => 'string'],
        'modified' => ['type' => 'integer'],
        '_valid' => ['type' => 'boolean'],
        '_meta' => ['type' => 'object'],
    ];

    // Per-ability overrides: create requires a non-empty title, edit constrains
    // `key` to a real field group key. Neither constraint is right for the other
    // ability, so the shared map deliberately carries neither.
    foreach ($overrides as $name => $schema) {
        $properties[$name] = $schema;
    }

    return $properties;
}

/**
 * Keys accepted on input but never written to the field group row.
 *
 * @return array<string, true>
 */
function acf_fg_bookkeeping_keys(): array
{
    return [
        'ID' => true,
        'local' => true,
        'local_file' => true,
        'modified' => true,
        '_valid' => true,
        '_meta' => true,
    ];
}

/**
 * Every top-level key a field group payload may carry.
 *
 * Unions the declared schema properties with the settings the installed ACF
 * actually recognises — acf_validate_field_group() fills each one with its
 * default — so a newer ACF adding a setting does not get rejected here.
 *
 * @return array<string, true>
 */
function acf_fg_accepted_keys(): array
{
    $accepted = [];
    foreach (array_keys(acf_fg_payload_properties()) as $name) {
        $accepted[$name] = true;
    }

    if (function_exists('acf_validate_field_group')) {
        /** @var mixed $defaults */
        $defaults = acf_validate_field_group([]);
        if (is_array($defaults)) {
            foreach (array_keys($defaults) as $raw_name) {
                $name = (string) $raw_name;
                // `_`-prefixed keys are ACF bookkeeping, handled separately.
                if (!str_starts_with($name, '_')) {
                    $accepted[$name] = true;
                }
            }
        }
    }

    return $accepted + acf_fg_bookkeeping_keys();
}

/**
 * The accepted keys worth naming back to an agent: real settings, no
 * bookkeeping. Sorted so error messages are stable.
 *
 * @return list<string>
 */
function acf_fg_settings_key_names(): array
{
    $names = [];
    $bookkeeping = acf_fg_bookkeeping_keys();
    foreach (array_keys(acf_fg_accepted_keys()) as $name) {
        if (array_key_exists($name, $bookkeeping)) {
            continue;
        }
        $names[] = $name;
    }
    sort($names);
    return $names;
}

/**
 * Detect a double-wrapped payload.
 *
 * The MCP adapter's generic `execute-ability` tool takes
 * `{ability_name, parameters}`. A client that forwards that envelope verbatim
 * instead of unwrapping it delivers the real settings nested under
 * `parameters`; ACF then serialises the entire envelope into post_content and
 * produces a keyed field group with a blank title and no fields. Fail loudly
 * rather than persisting that.
 *
 * @param array<string, mixed> $args
 */
function acf_fg_detect_envelope(array $args): ?WP_Error
{
    $found = [];
    foreach (['parameters', 'ability_name'] as $wrapper) {
        if (!array_key_exists($wrapper, $args)) {
            continue;
        }
        $found[] = $wrapper;
    }
    if ($found === []) {
        return null;
    }

    return new WP_Error(
        'invalid_payload',
        sprintf(
            'the field group payload looks double-wrapped: it carries the top-level key(s) %s, which belong to the generic execute-ability envelope {ability_name, parameters}, not to an ACF field group. Pass the field group settings flat as this ability\'s arguments — {"title": "...", "fields": [...], "location": [[...]]} — not nested inside a "parameters" object.',
            implode(', ', $found),
        ),
        ['envelope_keys' => $found],
    );
}

/**
 * Reject any top-level key ACF does not recognise.
 *
 * Without this the argument array is adopted verbatim as the field group array:
 * ACF's validate_post() merges it via wp_parse_args (a union, so unknown keys
 * survive) and update_post() serialises everything it does not explicitly
 * extract into post_content. A mis-nested or typo'd key was therefore persisted
 * silently while the call still reported success.
 *
 * @param array<string, mixed> $args
 */
function acf_fg_reject_unknown_keys(array $args): ?WP_Error
{
    $rejected = array_diff_key($args, acf_fg_accepted_keys());
    // Any letter-case of `ID` is tolerated here and dropped later, so a
    // case-normalising client cannot smuggle one past the strip.
    foreach (acf_fg_id_key_variants() as $variant) {
        unset($rejected[$variant]);
    }
    if ($rejected === []) {
        return null;
    }

    $unknown = array_map('strval', array_keys($rejected));
    sort($unknown);

    $allowed = acf_fg_settings_key_names();
    return new WP_Error(
        'invalid_payload',
        sprintf(
            'unrecognised top-level key(s) in the field group payload: %s. Allowed top-level keys are: %s. Per-field settings (label, name, type, ...) belong inside fields[], and location rules inside location[][].',
            implode(', ', $unknown),
            implode(', ', $allowed),
        ),
        ['unknown_keys' => $unknown, 'allowed_keys' => $allowed],
    );
}

/**
 * Drop every bookkeeping key before the payload reaches ACF.
 *
 * `ID` is the dangerous one. A non-zero ID makes acf_import_field_group() skip
 * its insert and wp_update_post() an arbitrary existing post instead, rewriting
 * that post's post_type, title, slug, content, status and parent. ACF registers
 * `acf-field-group` with supports => false, so WordPress stores no revision and
 * the overwritten post is unrecoverable. Stripped case-insensitively.
 *
 * @param array<string, mixed> $payload
 * @return array<string, mixed>
 */
function acf_fg_strip_bookkeeping_keys(array $payload): array
{
    foreach (array_keys(acf_fg_bookkeeping_keys()) as $name) {
        unset($payload[$name]);
    }
    foreach (acf_fg_id_key_variants() as $variant) {
        unset($payload[$variant]);
    }
    return $payload;
}

/**
 * Every letter-case of the two-letter key `ID` other than ACF's own `ID`, which
 * acf_fg_bookkeeping_keys() already covers. Enumerated rather than compared
 * case-insensitively so the set is provably complete and needs no key casting.
 *
 * @return list<string>
 */
function acf_fg_id_key_variants(): array
{
    return ['id', 'Id', 'iD'];
}

/**
 * Strip post ids and ACF's validation flag out of a fields[] tree, recursing
 * into sub_fields and flexible_content layouts.
 *
 * `ID` is the dangerous one, the same hazard as the top-level `ID` one level
 * down. ACF's import substitutes an incoming field id only when that field's key
 * already exists in the database — `isset($ids[$field['key']])` in
 * post-types/class-acf-field-group.php — so a field whose key is NEW keeps
 * whatever id the caller sent, and acf_update_field() then wp_update_post()s
 * that row into an `acf-field`: title, slug, excerpt, content, status, parent
 * and post_type all rewritten. Neither `acf-field-group` nor `acf-field`
 * supports revisions, so the overwritten post is unrecoverable. That makes the
 * strip necessary on edit exactly as much as on create, where no field row
 * exists to resolve against at all.
 *
 * `_valid` has to go with it. acf_validate_field() bails out on the first line
 * when `_valid` is set (acf-field-functions.php), so a field read back from
 * get-fieldgroup-schema — ACF marks loaded fields valid — would skip defaulting
 * altogether and reach acf_update_field() with no `ID` and no
 * `conditional_logic` at all, which ACF then reads as undefined array keys.
 * Dropping the flag makes ACF re-validate the field the way it validates any
 * hand-written one.
 *
 * ACF sets `parent` itself on import, so nothing else has to be removed.
 *
 * @param array<int, mixed> $fields
 * @return array<int, mixed>
 */
function acf_fg_strip_field_bookkeeping(array $fields): array
{
    /** @var mixed $field */
    foreach ($fields as &$field) {
        if (!is_array($field)) {
            continue;
        }
        unset($field['_valid']);
        foreach (array_keys($field) as $raw_name) {
            if (strcasecmp((string) $raw_name, 'ID') !== 0) {
                continue;
            }
            unset($field[$raw_name]);
        }
        /** @var mixed $sub */
        $sub = $field['sub_fields'] ?? null;
        if (is_array($sub)) {
            $field['sub_fields'] = acf_fg_strip_field_bookkeeping(array_values($sub));
        }
        /** @var mixed $layouts */
        $layouts = $field['layouts'] ?? null;
        if (!is_array($layouts)) {
            continue;
        }
        /** @var mixed $layout */
        foreach ($layouts as &$layout) {
            if (!is_array($layout)) {
                continue;
            }
            /** @var mixed $layout_sub */
            $layout_sub = $layout['sub_fields'] ?? null;
            if (is_array($layout_sub)) {
                $layout['sub_fields'] = acf_fg_strip_field_bookkeeping(array_values($layout_sub));
            }
        }
        unset($layout);
        $field['layouts'] = $layouts;
    }
    unset($field);
    return $fields;
}

/**
 * The input checks create and edit share, in the order that produces the most
 * useful message: an enveloped payload is reported as such rather than as a set
 * of unknown keys, and unknown keys are reported before per-key shape errors.
 *
 * @param array<string, mixed> $args
 */
function acf_fg_validate_write_input(array $args): ?WP_Error
{
    $envelope_check = acf_fg_detect_envelope($args);
    if ($envelope_check instanceof WP_Error) {
        return $envelope_check;
    }

    $unknown_check = acf_fg_reject_unknown_keys($args);
    if ($unknown_check instanceof WP_Error) {
        return $unknown_check;
    }

    return acf_validate_payload_shape($args);
}

/**
 * A blank title is unusable in the admin UI and is the signature of a payload
 * whose real settings never arrived. Require it on create.
 *
 * @param array<string, mixed> $args
 */
function acf_fg_require_title(array $args): string|WP_Error
{
    /** @var mixed $raw */
    $raw = $args['title'] ?? null;
    $title = is_string($raw) ? trim($raw) : '';
    if ($title === '') {
        return new WP_Error(
            'invalid_payload',
            'title is required and must be a non-empty string. Pass the field group settings flat, for example {"title": "Product Details", "fields": [{"label": "Subtitle", "name": "subtitle", "type": "text"}], "location": [[{"param": "post_type", "operator": "==", "value": "product"}]]}.',
            ['path' => 'title'],
        );
    }
    return $title;
}

/**
 * Normalise a value for tolerant text comparison during read-back. WordPress
 * may entity-encode a title on save for users without `unfiltered_html`, which
 * is a faithful save, not a mismatch.
 */
function acf_fg_normalize_text(mixed $value): string
{
    if (is_string($value)) {
        return trim(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, encoding: 'UTF-8'));
    }
    if (is_int($value) || is_float($value)) {
        return (string) $value;
    }
    if (is_bool($value)) {
        return $value ? '1' : '0';
    }
    return '';
}

/**
 * Apply ACF's own location normalisation to a requested location array.
 *
 * `ACF_Field_Group::pre_update_field_group()` is hooked on
 * `acf/pre_update_field_group` at priority 1 and runs inside `update_post()`
 * before the row is written, so what ACF stores is never quite what the caller
 * sent. Comparing the raw request against the stored row therefore reports a
 * mismatch for a save that was perfectly faithful — and on create that now costs
 * the caller a rollback of a good write.
 *
 * Mirrored step for step rather than run through `apply_filters()`, so a third
 * party filter that *injects* a location rule is still detected as a divergence
 * instead of being normalised into the expectation:
 *
 *   array_filter()  → drop falsy rule groups
 *   array_values()  → reindex
 *   array_map('array_filter')  → drop falsy rules inside each group
 *   array_map('array_values')  → reindex each group
 *
 * Note the order: groups are filtered BEFORE rules are, so a group that still
 * held a falsy rule survives and is left behind as an empty group. That is ACF's
 * behaviour and it is reproduced here, not tidied up.
 *
 * @return array<array-key, mixed>
 */
function acf_fg_normalize_location_rules(mixed $location): array
{
    if (!is_array($location) || $location === []) {
        // ACF guards the whole block with `if ( $field_group['location'] )`.
        return [];
    }

    $groups = [];
    /** @var mixed $group */
    foreach (array_values(array_filter($location)) as $group) {
        $groups[] = is_array($group) ? array_values(array_filter($group)) : $group;
    }
    return $groups;
}

/**
 * Apply ACF's DETERMINISTIC transformations to one requested field setting.
 *
 * Not `acf_validate_field()`. That was the first attempt and it is unsound as an
 * expectation builder: it ends with `apply_filters( 'acf/validate_field', $field )`
 * (acf-field-functions.php), so any filter that mutates a caller-supplied setting
 * runs twice — once on the way into the database and once while we compute what we
 * expect to find there — and the two agree, which is precisely the blindness the
 * read-back exists to prevent. Only transformations that are hardcoded in ACF and
 * cannot be filtered are reproduced here.
 *
 * `wrapper` is the one that matters in practice: `acf_validate_field()` fills it
 * through `wp_parse_args( $field['wrapper'], ['width' => '', 'class' => '',
 * 'id' => ''] )`, so a caller who names only `width` gets all three back.
 *
 * `conditional_logic` is cleaned in `acf_update_field()` BEFORE the
 * `acf/update_field` filter runs — empty rules dropped and both levels reindexed —
 * so that is reproduced too.
 *
 * Anything else is returned untouched. A setting ACF transforms in some way not
 * listed here will read as a mismatch, which is the safe direction: a loud refusal
 * to verify, never a quiet claim that it verified.
 */
function acf_fg_normalize_field_setting(string $setting, mixed $value): mixed
{
    if ($setting === 'wrapper') {
        return wp_parse_args(is_array($value) ? $value : [], [
            'width' => '',
            'class' => '',
            'id' => '',
        ]);
    }

    if ($setting === 'conditional_logic' && is_array($value) && $value !== []) {
        $groups = [];
        /** @var mixed $group */
        foreach (array_values(array_filter($value)) as $group) {
            $groups[] = is_array($group) ? array_values(array_filter($group)) : $group;
        }
        return $groups;
    }

    return $value;
}

/**
 * Canonicalise location rules while preserving ACF's semantics: the outer array
 * is an OR of rule groups, each inner array an AND of rules. Order is irrelevant
 * on both levels, so both are sorted — but the group boundaries are kept, so
 * [[A, B], [C]] and [[A], [B, C]] do not compare equal even though they contain
 * the same three rules.
 *
 * @return list<string>
 */
function acf_fg_canonical_location(mixed $location): array
{
    $groups = [];
    if (!is_array($location)) {
        return $groups;
    }
    /** @var mixed $group */
    foreach ($location as $group) {
        if (!is_array($group)) {
            continue;
        }
        $rules = [];
        /** @var mixed $rule */
        foreach ($group as $rule) {
            if (!is_array($rule)) {
                continue;
            }
            $rules[] = sprintf(
                '%s %s %s',
                acf_fg_normalize_text($rule['param'] ?? null),
                acf_fg_normalize_text($rule['operator'] ?? null),
                acf_fg_normalize_text($rule['value'] ?? null),
            );
        }
        sort($rules);
        $groups[] = '(' . implode(' AND ', $rules) . ')';
    }
    sort($groups);
    return $groups;
}

/**
 * Render canonicalised location groups for an error message.
 *
 * @param list<string> $groups
 */
function acf_fg_describe_location(array $groups): string
{
    return $groups === [] ? '(no rules)' : implode(' OR ', $groups);
}

/**
 * Keys inside a field entry that must never take part in read-back comparison:
 * ACF assigns or rewrites them on save, or they exist only at runtime.
 *
 * @return array<string, true>
 */
function acf_fg_field_ignored_keys(): array
{
    return [
        'ID' => true, // assigned by ACF on insert
        'parent' => true, // rewritten to the parent's post id
        'menu_order' => true, // renumbered by position, which order already covers
        '_valid' => true,
        '_name' => true,
        '_prepare' => true,
        'value' => true, // runtime only
        'prefix' => true, // runtime only
    ];
}

/**
 * Render a value for an error message without json_encode's string|false return.
 */
function acf_fg_describe(mixed $value): string
{
    if (is_array($value)) {
        return sprintf('an array of %d item(s)', count($value));
    }
    if ($value === null) {
        return 'null';
    }
    return '"' . acf_fg_normalize_text($value) . '"';
}

/**
 * Compare a requested setting value against the persisted one.
 *
 * Tolerates the type normalisation ACF applies on save — acf_parse_types() turns
 * the string '0' into int 0, booleans are stored as 0/1 — without tolerating an
 * actual change of value. Arrays are compared recursively, keys and all.
 */
function acf_fg_values_match(mixed $want, mixed $got): bool
{
    if (is_array($want) !== is_array($got)) {
        return false;
    }
    if (!is_array($want) || !is_array($got)) {
        return acf_fg_normalize_text($want) === acf_fg_normalize_text($got);
    }
    if (count($want) !== count($got)) {
        return false;
    }
    /** @var mixed $value */
    foreach ($want as $name => $value) {
        if (!array_key_exists($name, $got)) {
            return false;
        }
        if (!acf_fg_values_match($value, $got[$name])) {
            return false;
        }
    }
    return true;
}

/**
 * Re-index flexible-content layouts positionally. ACF stores them as a map keyed
 * by layout key while a caller normally supplies a plain list, so neither side's
 * keys can be compared directly.
 *
 * @return array<int, mixed>
 */
function acf_fg_layout_list(mixed $layouts): array
{
    return is_array($layouts) ? array_values($layouts) : [];
}

/**
 * Recursively compare a requested field tree against what was persisted.
 *
 * Position matters (ACF stores it as menu_order) and so does hierarchy, but only
 * the settings the caller actually supplied are compared: ACF fills dozens of
 * defaults nobody asked for, and comparing those would fail every single call.
 *
 * @param array<int, mixed> $requested
 * @param array<int, mixed> $persisted
 * @return list<string>
 */
function acf_fg_compare_field_tree(array $requested, array $persisted, string $path): array
{
    if (count($requested) !== count($persisted)) {
        return [
            sprintf('%s: persisted %d field(s), expected %d', $path, count($persisted), count($requested)),
        ];
    }

    $mismatches = [];
    $index = 0;
    /** @var mixed $want */
    foreach ($requested as $want) {
        /** @var mixed $got */
        $got = $persisted[$index] ?? null;
        $where = sprintf('%s[%d]', $path, $index);
        ++$index;
        if (!is_array($want) || !is_array($got)) {
            $mismatches[] = sprintf('%s: not a field definition', $where);
            continue;
        }
        $mismatches = array_merge($mismatches, acf_fg_compare_one_field($want, $got, $where));
    }
    return $mismatches;
}

/**
 * Compare one requested field entry against its persisted counterpart, then
 * recurse into sub_fields and flexible-content layouts.
 *
 * @param array<array-key, mixed> $want
 * @param array<array-key, mixed> $got
 * @return list<string>
 */
function acf_fg_compare_one_field(array $want, array $got, string $where): array
{
    $want_key = acf_fg_normalize_text($want['key'] ?? null);
    $got_key = acf_fg_normalize_text($got['key'] ?? null);
    if ($want_key !== $got_key) {
        // A different field entirely; comparing its settings would just be noise.
        return [sprintf('%s: key is "%s", expected "%s"', $where, $got_key, $want_key)];
    }

    $mismatches = acf_fg_compare_field_settings($want, $got, $where);

    if (array_key_exists('sub_fields', $want)) {
        /** @var mixed $want_sub */
        $want_sub = $want['sub_fields'];
        /** @var mixed $got_sub */
        $got_sub = $got['sub_fields'] ?? null;
        $mismatches = array_merge($mismatches, acf_fg_compare_field_tree(
            is_array($want_sub) ? array_values($want_sub) : [],
            is_array($got_sub) ? array_values($got_sub) : [],
            $where . '.sub_fields',
        ));
    }

    if (!array_key_exists('layouts', $want)) {
        return $mismatches;
    }
    return array_merge($mismatches, acf_fg_compare_layouts($want['layouts'], $got['layouts'] ?? null, $where));
}

/**
 * Compare the settings of one field entry, skipping the keys ACF owns and the
 * two that hold children.
 *
 * @param array<array-key, mixed> $want
 * @param array<array-key, mixed> $got
 * @return list<string>
 */
function acf_fg_compare_field_settings(array $want, array $got, string $where): array
{
    $mismatches = [];
    $ignored = acf_fg_field_ignored_keys();
    foreach (array_keys($want) as $raw_name) {
        $setting = (string) $raw_name;
        if (array_key_exists($setting, $ignored) || $setting === 'sub_fields' || $setting === 'layouts') {
            continue;
        }
        if (!array_key_exists($setting, $got)) {
            $mismatches[] = sprintf('%s: setting "%s" was not persisted', $where, $setting);
            continue;
        }
        // Normalised deterministically, never through a filterable ACF call: a
        // filter that mutates this value must show up as a mismatch, not be baked
        // into the expectation.
        /** @var mixed $value */
        $value = acf_fg_normalize_field_setting($setting, $want[$setting]);
        if (acf_fg_values_match($value, $got[$setting])) {
            continue;
        }
        $mismatches[] = sprintf(
            '%s: setting "%s" reads back as %s, expected %s',
            $where,
            $setting,
            acf_fg_describe($got[$setting]),
            acf_fg_describe($value),
        );
    }
    return $mismatches;
}

/**
 * Compare flexible-content layouts positionally, then recurse into each one. A
 * layout is shaped like a field — key, label, sub_fields — so the same
 * comparison applies.
 *
 * @return list<string>
 */
function acf_fg_compare_layouts(mixed $want_raw, mixed $got_raw, string $where): array
{
    $want_layouts = acf_fg_layout_list($want_raw);
    $got_layouts = acf_fg_layout_list($got_raw);
    if (count($want_layouts) !== count($got_layouts)) {
        return [
            sprintf(
                '%s.layouts: persisted %d layout(s), expected %d',
                $where,
                count($got_layouts),
                count($want_layouts),
            ),
        ];
    }

    $mismatches = [];
    $index = 0;
    /** @var mixed $want_layout */
    foreach ($want_layouts as $want_layout) {
        /** @var mixed $got_layout */
        $got_layout = $got_layouts[$index] ?? null;
        $layout_where = sprintf('%s.layouts[%d]', $where, $index);
        ++$index;
        if (!is_array($want_layout) || !is_array($got_layout)) {
            $mismatches[] = sprintf('%s: not a layout definition', $layout_where);
            continue;
        }
        $mismatches = array_merge($mismatches, acf_fg_compare_one_field($want_layout, $got_layout, $layout_where));
    }
    return $mismatches;
}

/**
 * Reduce a field tree to a one-line signature of keys and nesting.
 *
 * Used to verify an edit, where the saved payload carries every runtime key ACF
 * loaded rather than a set of settings the caller chose, so comparing settings
 * would be meaningless — while a dropped, renamed or re-nested field still has
 * to be caught. A string rather than a nested array because the same value both
 * compares and prints: `rep{one, two}, flex<block{body}>`.
 *
 * Empty `sub_fields` / `layouts` are omitted rather than rendered, so ACF's own
 * load — get_valid_layout() always fills `sub_fields` — does not read as a
 * different shape from a caller who left them out.
 *
 * @param array<int, mixed> $fields
 */
function acf_fg_field_tree_signature(array $fields): string
{
    $rendered = [];
    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $label = acf_fg_normalize_text($field['key'] ?? null);
        /** @var mixed $sub */
        $sub = $field['sub_fields'] ?? null;
        if (is_array($sub) && $sub !== []) {
            $label .= '{' . acf_fg_field_tree_signature(array_values($sub)) . '}';
        }
        $layouts = acf_fg_layout_list($field['layouts'] ?? null);
        if ($layouts !== []) {
            $label .= '<' . acf_fg_field_tree_signature($layouts) . '>';
        }
        $rendered[] = $label;
    }
    return $rendered === [] ? '(no fields)' : implode(', ', $rendered);
}

/**
 * Compare a field group re-read from the database against what the call intended
 * to persist.
 *
 * `$expected` is a fully resolved view, not the raw arguments: the key the call
 * resolved, every group setting the caller supplied, and `location` / `fields`
 * completed to the values this call meant to write. Completing them matters —
 * leaving either out when the caller omitted it meant a filter that injected a
 * location rule or an extra field passed verification unnoticed, because there was
 * nothing to compare against.
 *
 * Every supplied setting is compared, not just `title`: `active`, `position`,
 * `style`, `hide_on_screen` and the rest are exactly as persisted-or-not as the
 * title is, and a description promising the payload was verified has to be true of
 * all of them.
 *
 * @param array<string, mixed> $stored
 * @param array<string, mixed> $expected
 * @return list<string>
 */
function acf_fg_collect_readback_mismatches(array $stored, array $expected): array
{
    /** @var mixed $raw_key */
    $raw_key = $expected['key'] ?? null;
    $key = is_string($raw_key) ? $raw_key : '';

    $mismatches = [];

    /** @var mixed $stored_key */
    $stored_key = $stored['key'] ?? null;
    if (!is_string($stored_key) || $stored_key !== $key) {
        $mismatches[] = sprintf('key reads back as "%s", expected "%s"', acf_fg_normalize_text($stored_key), $key);
    }

    $mismatches = array_values(array_merge($mismatches, acf_fg_compare_group_settings($stored, $expected)));

    $got_rules = acf_fg_canonical_location($stored['location'] ?? null);
    $want_rules = acf_fg_canonical_location(acf_fg_normalize_location_rules($expected['location'] ?? null));
    if ($got_rules !== $want_rules) {
        $mismatches[] = sprintf(
            'location reads back as %s, expected %s',
            acf_fg_describe_location($got_rules),
            acf_fg_describe_location($want_rules),
        );
    }

    /** @var mixed $want_fields */
    $want_fields = $expected['fields'] ?? null;
    $persisted = acf_get_fields($key);
    return array_values(array_merge($mismatches, acf_fg_compare_field_tree(
        is_array($want_fields) ? array_values($want_fields) : [],
        is_array($persisted) ? array_values($persisted) : [],
        path: 'fields',
    )));
}

/**
 * Compare every group setting in the expectation against the stored row.
 *
 * `key`, `location` and `fields` are handled by the caller — each needs its own
 * comparison — and ACF's bookkeeping is never written, so neither is compared.
 *
 * @param array<string, mixed> $stored
 * @param array<string, mixed> $expected
 * @return list<string>
 */
function acf_fg_compare_group_settings(array $stored, array $expected): array
{
    $skip = acf_fg_bookkeeping_keys();
    $skip['key'] = true;
    $skip['location'] = true;
    $skip['fields'] = true;

    $mismatches = [];
    /** @var mixed $value */
    foreach ($expected as $setting => $value) {
        if (array_key_exists($setting, $skip)) {
            continue;
        }
        if (!array_key_exists($setting, $stored)) {
            $mismatches[] = sprintf('setting "%s" was not persisted', $setting);
            continue;
        }
        if (acf_fg_values_match($value, $stored[$setting])) {
            continue;
        }
        $mismatches[] = sprintf(
            'setting "%s" reads back as %s, expected %s',
            $setting,
            acf_fg_describe($stored[$setting]),
            acf_fg_describe($value),
        );
    }
    return $mismatches;
}

/**
 * Run `acf_import_field_group()` while recording every `acf-field` /
 * `acf-field-group` row it INSERTS.
 *
 * The recording bounds the candidate set to this call's window; it does not by
 * itself establish ownership, and must never be used as a delete authority.
 * `acf_fg_owned_rows()` decides what we own, from the group row id and
 * `post_parent` ancestry.
 *
 * `$update` separates an insert from a row ACF merely rewrote, so the temporary
 * group post ACF re-saves at the end of the import and any existing field it
 * updates in place are not recorded.
 *
 * @param array<string, mixed> $payload
 * @param array<int, true> $created Filled with the ids this import inserted.
 * @return array<string, mixed> Whatever acf_import_field_group() returned.
 */
function acf_fg_import_recording_inserts(array $payload, array &$created): array
{
    // @mago-expect lint:no-boolean-flag-parameter -- the signature is WordPress's,
    // not ours: the wp_insert_post action passes ($post_id, $post, $update), and
    // $update is the only thing that distinguishes a row ACF inserted from one it
    // merely rewrote. Dropping it would mean deleting rows we do not own.
    $collector = function (int $post_id, WP_Post $post, bool $update) use (&$created): void {
        if ($update) {
            return;
        }
        if ($post->post_type === 'acf-field-group' || $post->post_type === 'acf-field') {
            $created[$post_id] = true;
        }
    };

    add_action('wp_insert_post', $collector, priority: 10, accepted_args: 3);
    try {
        return acf_import_field_group($payload);
    } finally {
        remove_action('wp_insert_post', $collector, priority: 10);
    }
}

/**
 * The id of the field group row THIS call inserted, or 0 when that cannot be
 * established.
 *
 * Both conditions are required: ACF must have reported a positive id, AND that id
 * must be one of the rows this call's window recorded as inserted. There is no
 * fallback, deliberately. The previous version treated a sole recorded group row as
 * ours even when ACF reported nothing, and ACF fires
 * `acf/prepare_field_group_for_import` BEFORE it inserts its temporary group post
 * (class-acf-field-group.php) — so a prepare hook that creates a field group of its
 * own and then throws left that bystander as the only recorded group, and the
 * rollback deleted it. We had created nothing at all at that point.
 *
 * No id means no group deletion. Whatever was recorded gets named instead.
 *
 * @param array<int, true> $created
 */
function acf_fg_recorded_group_id(array $created, int $reported_id): int
{
    if ($reported_id <= 0 || !array_key_exists($reported_id, $created)) {
        return 0;
    }
    /** @var mixed $row */
    $row = get_post($reported_id);
    if (!$row instanceof WP_Post || $row->post_type !== 'acf-field-group') {
        return 0;
    }
    return $reported_id;
}

/**
 * Partition the recorded rows into the ones we can prove we own and the ones we
 * cannot.
 *
 * A row is ours when it IS the group row this call inserted, or when it is an
 * `acf-field` whose `post_parent` chain reaches that row. `post_parent` is the only
 * relation that identifies a row; a slug does not. ACF deliberately never
 * deduplicates `acf-field` / `acf-field-group` slugs — `_acf_apply_unique_field_slug()`
 * and `ACF_Internal_Post_Type::apply_unique_post_slug()` both return
 * `$original_slug` — so a slug names a logical KEY that two rows may legitimately
 * share. Matching on it deleted a bystander's field out of a bystander's field
 * group, which is the failure this function exists to make impossible.
 *
 * The ancestry rule is restricted to `acf-field` on purpose. A field group is only
 * ever deleted through `acf_delete_field_group()` so that ACF's Local JSON listener
 * runs; allowing a group row to qualify here would route it to `wp_delete_post()`
 * instead and leave its JSON file on disk to be re-imported.
 *
 * The walk happens before anything is deleted, because deleting the group breaks
 * the chain for everything below it.
 *
 * Everything else recorded — another writer's rows, a field reparented out from
 * under us, a chain deeper than the walk follows — lands in `unattributed`. Those
 * are never deleted and never ignored: the rollback names each one that is still
 * present, which is what keeps it from reporting a clean rollback it cannot prove.
 *
 * @param array<int, true> $created
 * @return array{owned: list<int>, unattributed: list<int>}
 */
function acf_fg_owned_rows(array $created, int $group_id): array
{
    $owned = [];
    $unattributed = [];

    foreach (array_keys($created) as $id) {
        /** @var mixed $row */
        $row = get_post($id);
        if (!$row instanceof WP_Post) {
            continue;
        }
        if ($group_id > 0 && $id === $group_id) {
            $owned[] = $id;
            continue;
        }
        if ($group_id > 0 && $row->post_type === 'acf-field' && acf_fg_descends_from($id, $group_id)) {
            $owned[] = $id;
            continue;
        }
        $unattributed[] = $id;
    }

    return ['owned' => $owned, 'unattributed' => $unattributed];
}

/**
 * Does this row descend from $group_id through post_parent?
 *
 * Depth-bounded: ACF nests fields inside fields, but a cycle in post_parent would
 * otherwise loop forever, and a malformed tree must not hang a rollback. Hitting
 * the bound returns false, which makes the row unattributed and therefore reported
 * rather than deleted.
 */
function acf_fg_descends_from(int $id, int $group_id): bool
{
    $current = $id;
    for ($depth = 0; $depth < 32; ++$depth) {
        if ($current === $group_id) {
            return true;
        }
        if ($current <= 0) {
            return false;
        }
        /** @var mixed $row */
        $row = get_post($current);
        if (!$row instanceof WP_Post) {
            return false;
        }
        $current = $row->post_parent;
    }
    return false;
}

/**
 * Roll back everything a failed create wrote.
 *
 * The group is deleted through `acf_delete_field_group()` BY ITS ROW ID, which
 * matters three times over. ACF loads the row by id, so it sees the key that was
 * actually stored rather than the one we asked for; it resolves the group's fields
 * through `acf_get_fields($post)`, i.e. by `post_parent`, so only our rows are
 * touched; and it fires `acf/delete_field_group`, which is the only hook ACF's
 * Local JSON listens to for deletions (local-json.php). Deleting the row with
 * `wp_delete_post()` instead left the JSON file on disk, so the group came back at
 * the next sync — a resurrection bug, and the reason the key is never used to
 * address the delete.
 *
 * ACF's own delete only removes the group's TOP-LEVEL fields, so nested sub_field
 * rows are cascaded first, resolved from the group loaded by id and therefore also
 * by parent.
 *
 * Rows that cannot be proved ours are never deleted and never passed over in
 * silence: each one still present is named, so the caller is told exactly what is
 * left and `rolled_back` is false. A rollback that cannot account for everything it
 * recorded must not claim to be clean.
 *
 * @param array<int, true> $created The rows this invocation inserted.
 * @return list<string> Problems encountered; empty when the rollback was clean.
 */
function acf_fg_rollback_created(array $created, int $group_id): array
{
    $problems = [];
    $partition = acf_fg_owned_rows($created, $group_id);

    if ($group_id > 0) {
        /** @var mixed $stored */
        $stored = acf_get_field_group($group_id);
        if (is_array($stored)) {
            acf_cascade_delete_fields(acf_get_fields($stored));
            acf_delete_field_group($group_id);
            acf_flush_field_group_cache($stored);
            clean_post_cache($group_id);
            if (acf_get_field_group($group_id) !== false) {
                $problems[] = "field group row {$group_id} is still readable after rollback";
            }
        }
    }

    foreach ($partition['owned'] as $id) {
        if (get_post($id) === null) {
            continue;
        }
        wp_delete_post($id, force_delete: true);
        clean_post_cache($id);
        if (get_post($id) !== null) {
            $problems[] = "row {$id} is still present after rollback";
        }
    }

    foreach ($partition['unattributed'] as $id) {
        clean_post_cache($id);
        /** @var mixed $row */
        $row = get_post($id);
        if (!$row instanceof WP_Post) {
            // It went with the group delete after all.
            continue;
        }
        $problems[] = sprintf(
            '%s row %d was inserted while this call ran but cannot be proved to belong to it, so it was left in place; inspect it and delete it by hand if it is unwanted',
            $row->post_type,
            $id,
        );
    }

    return $problems;
}

/**
 * Build the WP_Error for a failed create, rolling back first so no partial field
 * group, stray field row or orphan is ever left behind. The outcome of the
 * rollback is reported in the message and the error data, so a caller is never
 * left guessing what survived.
 *
 * `$data['id']`, when the call got far enough to have one, is the VERIFIED inserted
 * group row id — the rollback's only delete authority. The key in `$data` is
 * reporting only; it is never used to address a delete.
 *
 * @param array<int, true> $created The rows this invocation inserted.
 * @param array<string, mixed> $data
 */
function acf_fg_create_failure(string $code, string $message, array $created, array $data = []): WP_Error
{
    /** @var mixed $raw_id */
    $raw_id = $data['id'] ?? null;
    $group_id = is_int($raw_id) ? $raw_id : 0;

    $problems = acf_fg_rollback_created($created, $group_id);
    $data['rolled_back'] = $problems === [];
    if ($problems === []) {
        return new WP_Error($code, $message . ' Everything this call wrote has been rolled back.', $data);
    }
    $data['rollback_problems'] = $problems;
    return new WP_Error(
        $code,
        $message . ' Rolling it back did not fully succeed: ' . implode('; ', $problems) . '.',
        $data,
    );
}

/**
 * Re-read the row a create reported and prove it really holds that field group.
 *
 * Returns the stored field group, or a message describing how the database
 * diverges from what the save claimed. Kept separate from acf_fg_verify_created()
 * so each of these four ways of being wrong reads as one check rather than as
 * another branch in a long function.
 *
 * @return array<string, mixed>|string
 */
function acf_fg_reread_created(string $key, int $id): array|string
{
    $post_type = get_post_type($id);
    if ($post_type !== 'acf-field-group') {
        return sprintf(
            'field group %s resolved to post %d, which is a "%s", not an acf-field-group.',
            $key,
            $id,
            is_string($post_type) ? $post_type : 'missing post',
        );
    }

    // The row's own slug is where ACF keeps the key, so this catches a key that
    // was silently rewritten (by uniqueness handling, for instance) even when the
    // returned array still claims the requested one.
    $slug = get_post_field('post_name', $id);
    if ($slug !== $key) {
        return sprintf(
            'field group %s was stored under the slug "%s"; the reported key does not match the row.',
            $key,
            is_string($slug) ? $slug : '',
        );
    }

    $stored = acf_get_field_group($key);
    if (!is_array($stored)) {
        return "field group {$key} could not be read back after saving.";
    }

    /** @var mixed $stored_id */
    $stored_id = $stored['ID'] ?? null;
    if ((is_int($stored_id) ? $stored_id : 0) !== $id) {
        return sprintf(
            'field group %s reads back as post %s, not the post %d the save reported.',
            $key,
            acf_fg_normalize_text($stored_id),
            $id,
        );
    }

    return $stored;
}

/**
 * Verify what ACF actually persisted before reporting success.
 *
 * acf_import_field_group() returns its own input whatever happens and never
 * signals failure: a failed wp_insert_post() just leaves ID at 0, and a payload
 * whose real settings never arrived still yields a row. Flush ACF's caches so the
 * re-read hits the database rather than the store, then compare. Any failure rolls
 * the write back.
 *
 * The REQUESTED key is the authority throughout, never the key ACF handed back.
 * `acf/pre_update_field_group` and the import filters can rewrite it, and
 * comparing ACF's own output against itself would agree with the rewrite instead
 * of reporting it — so the two are checked against each other up front and every
 * later lookup uses the requested one.
 *
 * @param array<string, mixed> $saved
 * @param array<string, mixed> $expected Resolved expectation; see acf_fg_collect_readback_mismatches().
 * @param array<int, true> $created
 * @return array{key: string, id: int, source: 'db'}|WP_Error
 */
function acf_fg_verify_created(array $saved, array $expected, array $created): array|WP_Error
{
    /** @var mixed $raw_requested */
    $raw_requested = $expected['key'] ?? null;
    $key = is_string($raw_requested) ? $raw_requested : '';

    // The id is resolved before the key is judged: a key the import rewrote still
    // left a row behind, and the rollback needs its id to reach it. It comes from
    // the recorded inserts, with ACF's reported id used only to disambiguate — an
    // import filter must not be able to aim the rollback at someone else's row.
    /** @var mixed $saved_id */
    $saved_id = $saved['ID'] ?? null;
    $id = acf_fg_recorded_group_id($created, is_int($saved_id) ? $saved_id : 0);

    $identity = [];
    if ($key !== '') {
        $identity['key'] = $key;
    }
    if ($id > 0) {
        $identity['id'] = $id;
    }

    $key_problem = acf_fg_created_key_problem($saved, $key);
    if ($key_problem !== null) {
        return acf_fg_create_failure('create_failed', $key_problem, $created, $identity);
    }

    if ($id <= 0) {
        return acf_fg_create_failure(
            'create_failed',
            "ACF persisted no post for field group {$key}; the field group was not created.",
            $created,
            $identity,
        );
    }

    acf_flush_field_group_cache(['key' => $key]);
    clean_post_cache($id);

    $stored = acf_fg_reread_created($key, $id);
    if (is_string($stored)) {
        return acf_fg_create_failure('create_failed', $stored, $created, $identity);
    }

    $mismatches = acf_fg_collect_readback_mismatches($stored, $expected);
    if ($mismatches !== []) {
        return acf_fg_create_failure(
            'create_unverified',
            sprintf(
                'field group %s was written but does not match the requested payload: %s.',
                $key,
                implode('; ', $mismatches),
            ),
            $created,
            $identity + ['mismatches' => $mismatches],
        );
    }

    return ['key' => $key, 'id' => $id, 'source' => 'db'];
}

/**
 * Check the key ACF reported against the key the call resolved.
 *
 * Returns a message, or null when they agree. A disagreement is a real outcome,
 * not a formality: an `acf/prepare_field_group_for_import` or
 * `acf/pre_update_field_group` filter can rewrite `key`, and the caller asked for
 * the one it asked for.
 *
 * @param array<string, mixed> $saved
 */
function acf_fg_created_key_problem(array $saved, string $requested_key): ?string
{
    if ($requested_key === '') {
        return 'the create resolved no field group key, so nothing can be verified.';
    }

    /** @var mixed $saved_key */
    $saved_key = $saved['key'] ?? null;
    if (!is_string($saved_key) || $saved_key === '') {
        return 'ACF returned no key for the new field group, so nothing can be verified.';
    }
    if ($saved_key !== $requested_key) {
        return sprintf(
            'the save reports key "%s" but "%s" was requested; something rewrote the key.',
            $saved_key,
            $requested_key,
        );
    }
    return null;
}

/**
 * Defensive shape validation for create/edit payloads. Catches bad scalar
 * types on `fields` and `location` before they hit ACF internals (which would
 * fatal with TypeError). Returns null when the shape is acceptable.
 *
 * @param array<string, mixed> $args
 */
function acf_validate_payload_shape(array $args): ?WP_Error
{
    if (array_key_exists('fields', $args) && !is_array($args['fields'])) {
        return new WP_Error('invalid_payload', 'fields must be an array of field definitions', ['path' => 'fields']);
    }
    if (array_key_exists('location', $args) && !is_array($args['location'])) {
        return new WP_Error(
            'invalid_payload',
            'location must be an array of rule groups (each rule group is an array of rules)',
            ['path' => 'location'],
        );
    }
    return null;
}

/**
 * Guard helper — ACF active and current user can manage_options.
 */
function acf_fg_guard(): ?WP_Error
{
    if (!class_exists('ACF')) {
        return new WP_Error('acf_not_active', 'ACF is not active');
    }
    if (!current_user_can('manage_options')) {
        return new WP_Error('not_authorized', 'admin capability required');
    }
    return null;
}

/**
 * @param array<string, mixed> $args
 * @return array{key: string, id: int, source: 'db'}|WP_Error
 */
function acf_create_fieldgroup(array $args): array|WP_Error
{
    $guard = acf_fg_guard();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $input_check = acf_fg_validate_write_input($args);
    if ($input_check instanceof WP_Error) {
        return $input_check;
    }

    $title = acf_fg_require_title($args);
    if ($title instanceof WP_Error) {
        return $title;
    }

    $payload = acf_fg_strip_bookkeeping_keys($args);
    $payload['title'] = $title;

    $type_check = acf_check_pro_only_types($payload);
    if ($type_check instanceof WP_Error) {
        return $type_check;
    }

    $key_check = acf_resolve_create_key($payload);
    if ($key_check instanceof WP_Error) {
        return $key_check;
    }
    $payload['key'] = $key_check;

    /** @var mixed $fields */
    $fields = $payload['fields'] ?? null;
    if (is_array($fields)) {
        $payload['fields'] = acf_fg_strip_field_bookkeeping(acf_autofill_field_keys(array_values($fields)));
    }

    // Record the rows the import inserts, so a failure can be rolled back to the
    // ones this call provably owns.
    $created = [];
    try {
        $saved = acf_fg_import_recording_inserts($payload, $created);
    } catch (Throwable $e) {
        // The hook removal is exception-safe but the WRITE was not: a throw out of
        // the import used to leave everything it had already persisted behind,
        // because nothing rolled back on this path at all.
        return acf_fg_create_failure(
            'create_failed',
            sprintf('the field group write threw an exception: %s.', $e->getMessage()),
            $created,
            // No reported id on this path, so there is no group to delete: ACF fires
            // its prepare hook before inserting the group post, so a throw from
            // there means our group never existed. Whatever was recorded is named.
            ['key' => $payload['key'], 'id' => 0],
        );
    }

    // On create, an omitted `fields` or `location` is not "unspecified" — ACF
    // defaults both to an empty array, so that is the effective request and has to
    // be compared. Otherwise a filter that injects either one passes unnoticed.
    $expected = $payload;
    $expected['fields'] = is_array($payload['fields'] ?? null) ? $payload['fields'] : [];
    $expected['location'] = is_array($payload['location'] ?? null) ? $payload['location'] : [];

    return acf_fg_verify_created($saved, $expected, $created);
}

/**
 * Decide which key to use on create. Returns the resolved key string, or
 * a WP_Error if the supplied key collides with an existing field group.
 *
 * @param array<string, mixed> $payload
 */
function acf_resolve_create_key(array $payload): string|WP_Error
{
    /** @var mixed $supplied */
    $supplied = $payload['key'] ?? null;
    if (!is_string($supplied) || $supplied === '') {
        return acf_generate_unique_group_key();
    }
    $existing = acf_get_field_group($supplied);
    if ($existing === false) {
        return $supplied;
    }
    /** @var mixed $existing_id */
    $existing_id = $existing['ID'] ?? null;
    return new WP_Error('key_already_exists', "field group key already exists: {$supplied}", [
        'key' => $supplied,
        'existing_id' => $existing_id,
    ]);
}

/**
 * Auto-generate keys for any field that does not already have one.
 *
 * @param array<int, mixed> $fields
 * @return array<int, mixed>
 */
function acf_autofill_field_keys(array $fields): array
{
    /** @var mixed $field */
    foreach ($fields as &$field) {
        if (!is_array($field)) {
            continue;
        }
        /** @var mixed $existing_key */
        $existing_key = $field['key'] ?? null;
        if (!is_string($existing_key) || $existing_key === '') {
            $field['key'] = acf_generate_unique_field_key();
        }
        /** @var mixed $sub */
        $sub = $field['sub_fields'] ?? null;
        if (is_array($sub)) {
            $field['sub_fields'] = acf_autofill_field_keys(array_values($sub));
        }
        /** @var mixed $layouts */
        $layouts = $field['layouts'] ?? null;
        if (is_array($layouts)) {
            /** @var mixed $layout */
            foreach ($layouts as &$layout) {
                if (!is_array($layout)) {
                    continue;
                }
                /** @var mixed $layout_sub */
                $layout_sub = $layout['sub_fields'] ?? null;
                if (is_array($layout_sub)) {
                    $layout['sub_fields'] = acf_autofill_field_keys(array_values($layout_sub));
                }
            }
            unset($layout);
            $field['layouts'] = $layouts;
        }
    }
    unset($field);
    return $fields;
}

/**
 * Recursively collect Pro-only field types in a fields[] tree.
 *
 * @param array<int, mixed>|mixed $items
 * @param list<string> $pro_only
 * @param list<array{name: string, type: string}> $offending
 */
function acf_collect_pro_only_types(mixed $items, array $pro_only, array &$offending): void
{
    if (!is_array($items)) {
        return;
    }
    /** @var mixed $item */
    foreach ($items as $item) {
        if (!is_array($item)) {
            continue;
        }
        /** @var mixed $raw_type */
        $raw_type = $item['type'] ?? null;
        $type = is_string($raw_type) ? $raw_type : '';
        if (in_array($type, $pro_only, strict: true)) {
            /** @var mixed $raw_name */
            $raw_name = $item['name'] ?? null;
            $name = is_string($raw_name) ? $raw_name : '?';
            $offending[] = ['name' => $name, 'type' => $type];
        }
        /** @var mixed $sub_fields */
        $sub_fields = $item['sub_fields'] ?? null;
        if ($sub_fields !== null) {
            acf_collect_pro_only_types($sub_fields, $pro_only, $offending);
        }
        /** @var mixed $layouts */
        $layouts = $item['layouts'] ?? null;
        if ($layouts !== null) {
            acf_collect_pro_only_types($layouts, $pro_only, $offending);
        }
    }
}

/**
 * Reject Pro-only field types when ACF Pro is not active.
 *
 * @param array<string, mixed> $payload
 */
function acf_check_pro_only_types(array $payload): ?WP_Error
{
    $setup = acf_detect_setup();
    if ($setup['edition'] === 'pro') {
        return null;
    }

    $pro_only = ['repeater', 'flexible_content', 'gallery', 'clone'];
    /** @var list<array{name: string, type: string}> $offending */
    $offending = [];

    acf_collect_pro_only_types($payload['fields'] ?? [], $pro_only, $offending);

    if ($offending !== []) {
        return new WP_Error('invalid_payload', 'payload uses Pro-only field types but ACF Pro is not active', [
            'fields' => $offending,
        ]);
    }
    return null;
}

function acf_generate_unique_group_key(): string
{
    do {
        $entropy = (string) microtime(as_float: true) . wp_generate_password(length: 8, special_chars: false);
        $candidate = 'group_' . substr(md5($entropy), offset: 0, length: 13);
    } while (acf_get_field_group($candidate) !== false);
    return $candidate;
}

function acf_generate_unique_field_key(): string
{
    do {
        $entropy = (string) microtime(as_float: true) . wp_generate_password(length: 8, special_chars: false);
        $candidate = 'field_' . substr(md5($entropy), offset: 0, length: 13);
    } while (acf_get_field($candidate) !== false);
    return $candidate;
}

/**
 * Collect every field key in a tree, recursing into sub_fields and into
 * flexible_content layouts.
 *
 * @param array<array-key, mixed>|false $fields
 * @return array<string, true>
 */
function acf_collect_field_keys(array|false $fields): array
{
    $keys = [];
    if (!is_array($fields)) {
        return $keys;
    }
    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        $keys = array_merge($keys, acf_collect_one_field_keys($field));
    }
    return $keys;
}

/**
 * Collect keys from a single field entry (its own key + nested sub_fields and
 * layouts). Extracted to keep acf_collect_field_keys flat.
 *
 * @param array<array-key, mixed> $field
 * @return array<string, true>
 */
function acf_collect_one_field_keys(array $field): array
{
    $keys = [];
    /** @var mixed $field_key */
    $field_key = $field['key'] ?? null;
    if (is_string($field_key) && $field_key !== '') {
        $keys[$field_key] = true;
    }
    /** @var mixed $sub */
    $sub = $field['sub_fields'] ?? null;
    if (is_array($sub)) {
        $keys = array_merge($keys, acf_collect_field_keys($sub));
    }
    /** @var mixed $layouts */
    $layouts = $field['layouts'] ?? null;
    if (!is_array($layouts)) {
        return $keys;
    }
    /** @var mixed $layout */
    foreach ($layouts as $layout) {
        if (!is_array($layout)) {
            continue;
        }
        /** @var mixed $layout_key */
        $layout_key = $layout['key'] ?? null;
        if (is_string($layout_key) && $layout_key !== '') {
            $keys[$layout_key] = true;
        }
        /** @var mixed $layout_sub */
        $layout_sub = $layout['sub_fields'] ?? null;
        if (is_array($layout_sub)) {
            $keys = array_merge($keys, acf_collect_field_keys($layout_sub));
        }
    }
    return $keys;
}

/**
 * Compute created / updated / removed sets from two recursive key snapshots.
 *
 * @param array<string, true> $before
 * @param array<string, true> $after
 * @return array{created: list<string>, updated: list<string>, removed: list<string>}
 */
function acf_compute_field_diff(array $before, array $after): array
{
    $created = [];
    $updated = [];
    foreach (array_keys($after) as $key) {
        if (array_key_exists($key, $before)) {
            $updated[] = $key;
            continue;
        }
        $created[] = $key;
    }
    $removed = [];
    foreach (array_keys($before) as $key) {
        if (array_key_exists($key, $after)) {
            continue;
        }
        $removed[] = $key;
    }
    return ['created' => $created, 'updated' => $updated, 'removed' => $removed];
}

/**
 * Reject if the field group is registered via PHP.
 *
 * @param string $key
 * @param array<string, mixed> $existing
 * @param string $action  'edit' or 'delete'
 */
function acf_reject_php_source(string $key, array $existing, string $action): ?WP_Error
{
    $source = acf_resolve_fieldgroup_source($key);
    if ($source !== 'php') {
        return null;
    }
    /** @var mixed $local_file */
    $local_file = $existing['local_file'] ?? null;
    $file = is_string($local_file) ? $local_file : null;
    $message = $action === 'delete'
        ? 'field group is registered via PHP and cannot be deleted at runtime'
        : 'field group is registered via PHP and cannot be modified at runtime';
    return new WP_Error('field_group_not_editable', $message, ['key' => $key, 'source' => 'php', 'file' => $file]);
}

/**
 * PATCH semantics for `fields` on edit: when the agent omits `fields` entirely,
 * preserve the existing array. Only treat `fields` as a write-intent when the
 * agent explicitly passes it — even as an empty list, which means "clear".
 *
 * @param array<string, mixed> $args
 * @param array<array-key, mixed> $existing_fields
 * @return array<int, mixed>
 */
function acf_fg_resolve_edit_fields(array $args, array $existing_fields): array
{
    if (!array_key_exists('fields', $args)) {
        return array_values($existing_fields);
    }
    /** @var mixed $raw_fields */
    $raw_fields = $args['fields'];
    /** @var array<int, mixed> $supplied */
    $supplied = is_array($raw_fields) ? array_values($raw_fields) : [];
    // Strip caller-supplied field post ids here too, not just on create: ACF
    // resolves an existing field by its key, never by the id the caller sent, so
    // dropping the id costs nothing on an update and closes the overwrite vector
    // on a field whose key is new. acf_prepare_fields_for_import() flattens
    // sub_fields and flexible-content layouts into the same loop, which is why
    // the strip recurses. See acf_fg_strip_field_bookkeeping().
    return acf_fg_strip_field_bookkeeping(acf_autofill_field_keys($supplied));
}

/**
 * Merge the agent payload over the stored group and save it.
 *
 * PATCH semantics on the top level: agent-supplied keys win, keys the agent did
 * not pass fall back to the existing record. Without that union, omitting
 * `title` or `location` would blank them on save — acf_import_field_group() does
 * not merge with the stored group.
 *
 * Bookkeeping is stripped from BOTH sides: from the agent args so a supplied
 * post id can never retarget the write, and from the stored group so `local` /
 * `local_file` / `modified` / `_valid` — which ACF injects when it reads a
 * JSON-sourced group — are not copied into the database row. Dropping `_valid`
 * also makes ACF re-validate the merged payload instead of trusting it verbatim.
 *
 * @param array<string, mixed> $args
 * @param array<string, mixed> $existing
 * @param array<int, mixed> $incoming_fields
 * @return int|WP_Error  The post id ACF wrote to.
 */
function acf_fg_save_edit(array $args, string $key, array $existing, array $incoming_fields): int|WP_Error
{
    $payload = acf_fg_strip_bookkeeping_keys($args);
    $payload['key'] = $key;
    $payload['fields'] = $incoming_fields;
    $payload += acf_fg_strip_bookkeeping_keys($existing);
    // Re-target the write at the stored row explicitly, never at a caller value.
    $payload['ID'] = $existing['ID'] ?? null;

    $saved = acf_import_field_group($payload);
    /** @var mixed $saved_id */
    $saved_id = $saved['ID'] ?? null;
    if (!is_int($saved_id) || $saved_id <= 0) {
        return new WP_Error(
            'edit_failed',
            "field group {$key} was not persisted; ACF reported no post id for the save",
            ['key' => $key],
        );
    }
    return $saved_id;
}

/**
 * Build the resolved expectation for an edit.
 *
 * Only what the caller actually supplied is claimed as expected — the saved
 * payload is a union with the stored group, so every other setting is ACF's own
 * and comparing it would be circular. `location` and `fields` are completed:
 * omitting `location` means "keep what is stored", omitting `fields` means the
 * same, and both still have to be verified as having survived the write.
 *
 * @param array<string, mixed> $args
 * @param array<string, mixed> $existing
 * @param array<int, mixed> $incoming_fields
 * @return array<string, mixed>
 */
function acf_fg_expected_from_edit(array $args, string $key, array $existing, array $incoming_fields): array
{
    $expected = acf_fg_strip_bookkeeping_keys($args);
    $expected['key'] = $key;
    $expected['fields'] = $incoming_fields;
    if (!array_key_exists('location', $expected)) {
        /** @var mixed $stored_location */
        $stored_location = $existing['location'] ?? null;
        $expected['location'] = is_array($stored_location) ? $stored_location : [];
    }
    return $expected;
}

/**
 * Verify an edit against the database before reporting success.
 *
 * acf_import_field_group() signals nothing — it returns its own input — so a
 * rejected save, a nested field that never made it, a setting that did not take
 * and a location array that lost its grouping all look exactly like success.
 * Re-read the row and compare, using the same comparison create uses.
 *
 * @param array<string, mixed> $expected
 */
function acf_fg_verify_edited(string $key, int $id, array $expected): ?WP_Error
{
    acf_flush_field_group_cache(['key' => $key]);
    clean_post_cache($id);

    $stored = acf_get_field_group($key);
    if (!is_array($stored)) {
        return new WP_Error(
            'edit_unverified',
            "field group {$key} could not be read back after saving; post {$id} may no longer hold it.",
            ['key' => $key, 'id' => $id, 'mismatches' => ['the field group is no longer readable']],
        );
    }

    $mismatches = acf_fg_collect_readback_mismatches($stored, $expected);
    if ($mismatches === []) {
        return null;
    }

    return new WP_Error(
        'edit_unverified',
        sprintf(
            'field group %s was saved but does not match the requested payload: %s.',
            $key,
            implode('; ', $mismatches),
        ),
        ['key' => $key, 'id' => $id, 'mismatches' => $mismatches],
    );
}

/**
 * Turn a failed edit verification into the error the caller gets.
 *
 * No second write. A failed edit is REPORTED, never repaired.
 *
 * An earlier version re-imported the pre-edit snapshot whenever it judged the edit
 * restorable. Judging that correctly turned out to need more than we can soundly
 * know. The test was "is every pre-edit field key still under the group", but
 * `acf/update_field` runs after ACF resolves a field's parent and its result lands
 * in `post_parent` (acf-field-functions.php), so a filter can REPARENT a row: the
 * key leaves the group while the row is still there and still recoverable by its
 * id. Reading that as "gone from the database" refused restoration for a fully
 * recoverable edit. Making it right meant growing the mechanism into row-id and
 * reparenting recovery — more inference on a failure path, in machinery that had
 * already needed correcting in three consecutive reviews.
 *
 * So it is gone. What the caller gets instead is the truth: the differences that
 * were found, the field tree the group holds right now, and `rolled_back: false`.
 * A caller that wants the old state back has `novamira/acf-get-fieldgroup-schema`
 * and the same edit ability, and is in a better position to decide than a guess on
 * an error path.
 */
function acf_fg_edit_failure(WP_Error $failure, string $key, int $id): WP_Error
{
    $code = $failure->get_error_code();
    /** @var array<string, mixed> $data */
    $data = is_array($failure->get_error_data()) ? $failure->get_error_data() : [];

    // Re-read at the moment of reporting, so the tree describes what is true now.
    acf_flush_field_group_cache(['key' => $key]);
    $persisted = acf_get_fields($key);
    $tree = acf_fg_field_tree_signature(is_array($persisted) ? array_values($persisted) : []);

    $data['rolled_back'] = false;
    $data['persisted_field_tree'] = $tree;

    return new WP_Error(
        is_string($code) ? $code : 'edit_unverified',
        sprintf(
            '%s The save was NOT rolled back — a failed edit is reported, not repaired. The field group now stands as post %d with the field tree [%s]. Re-read it with novamira/acf-get-fieldgroup-schema and repair it from there.',
            $failure->get_error_message(),
            $id,
            $tree,
        ),
        $data,
    );
}

/**
 * Resolve which field group an edit targets, and refuse anything that is not a
 * field group key.
 *
 * The `^group_` constraint lives in the input schema, but the schema is not the
 * only entry point and the callback has to hold the same line — a NUMERIC string
 * is meaningful to ACF, not merely invalid: `ACF_Internal_Post_Type::get_post_object()`
 * does `if ( is_numeric( $id ) ) { $post_object = get_post( $id ); … }`, so
 * `acf_get_field_group('42')` resolves post 42 by id. The callback would then
 * happily edit that group and, because acf_fg_save_edit() writes `key` straight
 * back out as `post_name`, replace its real key with the string "42" — leaving the
 * group unreachable under the key every stored field still points at.
 *
 * The canonical key is then taken from the stored record rather than from the
 * argument, so everything downstream writes back the key ACF actually holds.
 *
 * @param array<string, mixed> $args
 * @return array{0: string, 1: array<string, mixed>}|WP_Error
 */
function acf_fg_resolve_edit_key(array $args): array|WP_Error
{
    /** @var mixed $raw_key */
    $raw_key = $args['key'] ?? null;
    if (!is_string($raw_key) || $raw_key === '') {
        return new WP_Error('field_group_not_found', 'key is required');
    }
    if (!str_starts_with($raw_key, 'group_')) {
        return new WP_Error(
            'invalid_payload',
            sprintf(
                'key must be a field group key beginning with "group_", not "%s". Field groups are addressed by key, never by post id — list them with novamira/acf-list-fieldgroups to find the one you mean.',
                $raw_key,
            ),
            ['path' => 'key', 'key' => $raw_key],
        );
    }

    $existing = acf_get_field_group($raw_key);
    if (!is_array($existing)) {
        return new WP_Error('field_group_not_found', "field group not found: {$raw_key}", ['key' => $raw_key]);
    }

    /** @var mixed $stored_key */
    $stored_key = $existing['key'] ?? null;
    if (!is_string($stored_key) || $stored_key === '') {
        return new WP_Error(
            'field_group_not_found',
            "field group {$raw_key} was found but carries no key of its own; refusing to write to it",
            ['key' => $raw_key],
        );
    }

    return [$stored_key, $existing];
}

/**
 * @param array<string, mixed> $args
 * @return array<string, mixed>|WP_Error
 */
function acf_edit_fieldgroup(array $args): array|WP_Error
{
    $guard = acf_fg_guard();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    $input_check = acf_fg_validate_write_input($args);
    if ($input_check instanceof WP_Error) {
        return $input_check;
    }

    $resolved = acf_fg_resolve_edit_key($args);
    if ($resolved instanceof WP_Error) {
        return $resolved;
    }
    [$key, $existing] = $resolved;

    $php_check = acf_reject_php_source($key, $existing, action: 'edit');
    if ($php_check instanceof WP_Error) {
        return $php_check;
    }

    $type_check = acf_check_pro_only_types($args);
    if ($type_check instanceof WP_Error) {
        return $type_check;
    }

    $existing_fields = acf_get_fields($key);
    $existing_fields_array = is_array($existing_fields) ? $existing_fields : [];
    $existing_keys_before = acf_collect_field_keys($existing_fields_array);

    $incoming_fields = acf_fg_resolve_edit_fields($args, $existing_fields_array);
    $incoming_keys = acf_collect_field_keys($incoming_fields);

    $save = acf_fg_save_edit($args, $key, $existing, $incoming_fields);
    if ($save instanceof WP_Error) {
        return $save;
    }

    $diff = acf_compute_field_diff($existing_keys_before, $incoming_keys);

    $verified = acf_fg_verify_edited($key, $save, acf_fg_expected_from_edit($args, $key, $existing, $incoming_fields));
    if ($verified instanceof WP_Error) {
        return acf_fg_edit_failure($verified, $key, $save);
    }

    return [
        'key' => $key,
        'created_fields' => $diff['created'],
        'updated_fields' => $diff['updated'],
        'removed_fields' => $diff['removed'],
    ];
}

/**
 * @param array{key: string} $args
 * @return array{deleted: bool, key: string, orphaned_meta_warning: string}|WP_Error
 */
function acf_delete_fieldgroup(array $args): array|WP_Error
{
    $guard = acf_fg_guard();
    if ($guard instanceof WP_Error) {
        return $guard;
    }

    /** @var mixed $raw_key */
    $raw_key = $args['key'];
    if (!is_string($raw_key) || $raw_key === '') {
        return new WP_Error('field_group_not_found', 'key is required');
    }
    $key = $raw_key;

    $existing = acf_get_field_group($key);
    if (!is_array($existing)) {
        return new WP_Error('field_group_not_found', "field group not found: {$key}", ['key' => $key]);
    }

    $php_check = acf_reject_php_source($key, $existing, action: 'delete');
    if ($php_check instanceof WP_Error) {
        return $php_check;
    }

    /** @var mixed $existing_id */
    $existing_id = $existing['ID'] ?? null;
    $delete_target = is_int($existing_id) || is_string($existing_id) ? $existing_id : $key;

    // ACF deletes only the group post; child fields and their nested sub_fields
    // are left orphaned and confuse later name-based lookups. Cascade manually.
    acf_cascade_delete_fields(acf_get_fields($key));

    $deleted = acf_delete_field_group($delete_target);

    return [
        'deleted' => (bool) $deleted,
        'key' => $key,
        'orphaned_meta_warning' => 'values previously stored under this group remain in postmeta but become unreachable via ACF API',
    ];
}

/**
 * Recursively delete fields and their sub_fields / layouts.
 *
 * @param array<array-key, mixed>|false $fields
 */
function acf_cascade_delete_fields(array|false $fields): void
{
    if (!is_array($fields)) {
        return;
    }
    /** @var mixed $field */
    foreach ($fields as $field) {
        if (!is_array($field)) {
            continue;
        }
        /** @var mixed $sub */
        $sub = $field['sub_fields'] ?? null;
        if (is_array($sub)) {
            acf_cascade_delete_fields($sub);
        }
        /** @var mixed $layouts */
        $layouts = $field['layouts'] ?? null;
        if (is_array($layouts)) {
            /** @var mixed $layout */
            foreach ($layouts as $layout) {
                if (!is_array($layout)) {
                    continue;
                }
                /** @var mixed $layout_sub */
                $layout_sub = $layout['sub_fields'] ?? null;
                if (is_array($layout_sub)) {
                    acf_cascade_delete_fields($layout_sub);
                }
            }
        }
        /** @var mixed $field_id */
        $field_id = $field['ID'] ?? null;
        if (is_int($field_id) || is_string($field_id)) {
            acf_delete_field($field_id);
        }
    }
}
