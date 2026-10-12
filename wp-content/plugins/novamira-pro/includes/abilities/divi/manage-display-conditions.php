<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Divi;

use WP_Error;

/**
 * Abilities: discover and set Divi 5 module display conditions (conditional
 * visibility — show/hide a module by login status, role, post/term, date,
 * device, cookie, WooCommerce state, …).
 *
 * A module's conditions live at `module.decoration.conditions.<breakpoint>.value`
 * as a list of condition objects, each shaped
 * `{id, conditionName, conditionSettings: {enableCondition: 'on', displayRule,
 * …}, operator}`. This surface validates the condition type and builds that
 * envelope; the caller supplies the per-type `settings` (discover them with
 * novamira/divi-list-condition-types).
 */

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Catalog of Divi display-condition types. Keyed by `conditionName`; each has a
 * label, the common `display_rules`, and the extra `settings` keys it reads (on
 * top of `displayRule`). Mirrors the switch in Divi's Conditions::is_condition_true.
 *
 * @return array<string, array{label: string, display_rules: list<string>, settings: list<string>}>
 */
function divi_condition_types(): array
{
    return divi_post_condition_types() + divi_visitor_condition_types();
}

/**
 * Post/query-context display conditions (where the module is, on the site).
 *
 * @return array<string, array{label: string, display_rules: list<string>, settings: list<string>}>
 */
function divi_post_condition_types(): array
{
    return [
        'postType' => ['label' => 'Post type', 'display_rules' => ['is', 'isNot'], 'settings' => ['postTypes']],
        'categories' => [
            'label' => 'Post categories',
            'display_rules' => ['is', 'isNot'],
            'settings' => ['categories'],
        ],
        'tags' => ['label' => 'Post tags', 'display_rules' => ['is', 'isNot'], 'settings' => ['tags']],
        'author' => ['label' => 'Post author', 'display_rules' => ['is', 'isNot'], 'settings' => ['userIds']],
        'customField' => [
            'label' => 'Custom field',
            'display_rules' => ['is', 'isNot', 'contains', 'doesNotContain', 'isGreaterThan', 'isLessThan'],
            'settings' => [
                'customFieldName',
                'customFieldValue',
                'selectedFieldName',
                'selectedFieldValue',
                'customValue',
            ],
        ],
        'categoryPage' => [
            'label' => 'On a category archive',
            'display_rules' => ['is', 'isNot'],
            'settings' => ['categories'],
        ],
        'tagPage' => ['label' => 'On a tag archive', 'display_rules' => ['is', 'isNot'], 'settings' => ['tags']],
        'dateArchive' => [
            'label' => 'On a date archive',
            'display_rules' => ['is', 'isNot'],
            'settings' => ['dateArchive'],
        ],
        'searchResults' => [
            'label' => 'On search results',
            'display_rules' => ['is', 'isNot'],
            'settings' => ['specificSearchQueries', 'excludedSearchQueries'],
        ],
        'pageVisit' => ['label' => 'Page/post visit', 'display_rules' => ['is', 'isNot'], 'settings' => ['pages']],
        'postVisit' => ['label' => 'Post visit', 'display_rules' => ['is', 'isNot'], 'settings' => ['pages']],
    ];
}

/**
 * Visitor/context display conditions (who/when/how the visitor is).
 *
 * @return array<string, array{label: string, display_rules: list<string>, settings: list<string>}>
 */
function divi_visitor_condition_types(): array
{
    return [
        'loggedInStatus' => [
            'label' => 'Logged-in status',
            'display_rules' => ['loggedIn', 'loggedOut'],
            'settings' => [],
        ],
        'userRole' => ['label' => 'User role', 'display_rules' => ['is', 'isNot'], 'settings' => ['userRoles']],
        'dateTime' => [
            'label' => 'Date & time',
            'display_rules' => [
                'isAfter',
                'isBefore',
                'isOnSpecificDate',
                'isNotOnSpecificDate',
                'isOnSpecificDays',
                'isFirstDayOfMonth',
                'isLastDayOfMonth',
            ],
            'settings' => ['date', 'time', 'fromTime', 'untilTime', 'allDay', 'weekdays', 'repeat', 'repeatFrequency'],
        ],
        'numberOfViews' => [
            'label' => 'Number of views',
            'display_rules' => ['untilDate', 'afterNumberOfTimes', 'never'],
            'settings' => [
                'numberOfViews',
                'repeatTimes',
                'displayAgainAfter',
                'displayAgainAfterUnit',
                'resetAfterDuration',
            ],
        ],
        'urlParameter' => [
            'label' => 'URL parameter',
            'display_rules' => ['is', 'isNot'],
            'settings' => ['selectUrlParameter', 'urlParameterName', 'urlParameterValue'],
        ],
        'browser' => ['label' => 'Browser', 'display_rules' => ['is', 'isNot'], 'settings' => ['browsers']],
        'operatingSystem' => [
            'label' => 'Operating system',
            'display_rules' => ['is', 'isNot'],
            'settings' => ['operatingSystems'],
        ],
        'cookie' => [
            'label' => 'Cookie',
            'display_rules' => ['cookieExists', 'cookieDoesNotExist', 'cookieValueEquals', 'cookieValueDoesNotEqual'],
            'settings' => ['cookieName', 'cookieValue'],
        ],
        'productPurchase' => [
            'label' => 'WooCommerce: product purchase',
            'display_rules' => [
                'hasBoughtProduct',
                'hasNotBoughtProduct',
                'hasBoughtSpecificProduct',
                'hasNotBoughtSpecificProduct',
            ],
            'settings' => ['products'],
        ],
        'cartContents' => [
            'label' => 'WooCommerce: cart contents',
            'display_rules' => ['hasProducts', 'isEmpty', 'hasSpecificProduct', 'doesNotHaveSpecificProduct'],
            'settings' => ['products'],
        ],
        'productStock' => [
            'label' => 'WooCommerce: product stock',
            'display_rules' => ['instock', 'outofstock', 'onbackorder'],
            'settings' => ['products'],
        ],
    ];
}

/**
 * Generate an RFC-4122 v4 UUID for a condition instance id.
 */
function divi_condition_uuid(): string
{
    $bytes = random_bytes(16);
    $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
    $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
    $hex = bin2hex($bytes);
    return sprintf(
        '%s-%s-%s-%s-%s',
        substr($hex, offset: 0, length: 8),
        substr($hex, offset: 8, length: 4),
        substr($hex, offset: 12, length: 4),
        substr($hex, offset: 16, length: 4),
        substr($hex, offset: 20, length: 12),
    );
}

wp_register_ability('novamira/divi-list-condition-types', [
    'label' => __('List Divi Display Condition Types', domain: 'novamira-pro'),
    'description' => __(
        'Lists the display-condition types a module can be shown/hidden by, as rows: conditionName, label, display_rules (the values for "displayRule"), and settings (the extra keys that condition reads). Pass these to novamira/divi-set-display-conditions. Examples: loggedInStatus (displayRule loggedIn/loggedOut), categories (displayRule is/isNot + categories), dateTime, numberOfViews, cookie. WooCommerce types (productPurchase, cartContents, productStock) require WooCommerce.',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'conditions' => ['type' => 'array', 'items' => ['type' => 'object']],
            'total' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_list_condition_types',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Discover the display-condition types and their displayRule/settings before calling divi-set-display-conditions.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

wp_register_ability('novamira/divi-set-display-conditions', [
    'label' => __('Set Divi Display Conditions', domain: 'novamira-pro'),
    'description' => __(
        'Sets the display (visibility) conditions on the module at "address" — show/hide it by login status, role, post/term, date, device, cookie, WooCommerce state, etc. "conditions" is a list; each item is {conditionName, settings, operator?}. "conditionName" is a type from novamira/divi-list-condition-types; "settings" holds that type\'s "displayRule" plus its extra keys; "operator" ("OR" default, or "AND") joins it to the next condition. Replaces the module\'s current condition list. Example: [{"conditionName":"loggedInStatus","settings":{"displayRule":"loggedIn"}}] shows the module only to logged-in visitors. Accepts the post id as "post" or "post_id".',
        domain: 'novamira-pro',
    ),
    'category' => 'divi',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'post' => ['type' => 'integer', 'description' => 'Post id to write to.'],
            'post_id' => ['type' => 'integer', 'description' => 'WordPress-native alias for "post".'],
            'address' => ['type' => 'string', 'description' => 'Module address, e.g. "0/0/0/0".'],
            'conditions' => [
                'type' => 'array',
                'items' => ['type' => 'object'],
                'description' => 'List of {conditionName, settings, operator?}. Replaces the current conditions.',
            ],
            'breakpoint' => [
                'type' => 'string',
                'description' => 'Responsive breakpoint (default "desktop").',
            ],
        ],
        'required' => ['address', 'conditions'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'post_id' => ['type' => 'integer'],
            'address' => ['type' => 'string'],
            'breakpoint' => ['type' => 'string'],
            'conditions' => ['type' => 'integer'],
        ],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\Divi\divi_set_display_conditions',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Conditionally show/hide a module. Build each condition\'s "settings" from divi-list-condition-types (displayRule + the type\'s keys). The list replaces the module\'s current conditions.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>
 */
function divi_list_condition_types(array $input): array
{
    $rows = [];
    foreach (divi_condition_types() as $name => $def) {
        $rows[] = [
            'conditionName' => $name,
            'label' => $def['label'],
            'display_rules' => $def['display_rules'],
            'settings' => $def['settings'],
        ];
    }
    return ['conditions' => $rows, 'total' => count($rows)];
}

/**
 * Build one condition object (the stored envelope) from a caller-supplied
 * `{conditionName, settings, operator?}`, or a WP_Error when invalid.
 *
 * @param mixed $raw
 * @return array<string, mixed>|WP_Error
 */
function divi_build_condition($raw)
{
    if (!is_array($raw)) {
        return divi_error(
            'invalid_input',
            message: 'Each condition must be an object {conditionName, settings, operator?}.',
        );
    }
    $name = is_string($raw['conditionName'] ?? null) ? $raw['conditionName'] : '';
    if (!array_key_exists($name, divi_condition_types())) {
        return divi_error(
            'unknown_condition',
            message: sprintf('Unknown conditionName "%s". Use novamira/divi-list-condition-types.', $name),
            status: 422,
        );
    }
    $settings = is_array($raw['settings'] ?? null) ? $raw['settings'] : [];
    $settings['enableCondition'] = 'on';
    if (!is_string($settings['adminLabel'] ?? null)) {
        $settings['adminLabel'] = divi_condition_types()[$name]['label'];
    }
    $operator = ($raw['operator'] ?? 'OR') === 'AND' ? 'AND' : 'OR';

    return [
        'id' => divi_condition_uuid(),
        'conditionName' => $name,
        'conditionSettings' => $settings,
        'operator' => $operator,
    ];
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function divi_set_display_conditions(array $input)
{
    $post = divi_resolve_post($input);
    if ($post instanceof WP_Error) {
        return $post;
    }

    $address = divi_string_arg($input, key: 'address');
    if (trim($address) === '') {
        return divi_error('invalid_input', message: 'An "address" is required (e.g. "0/0/0").');
    }

    /** @var mixed $raw */
    $raw = $input['conditions'] ?? null;
    if (!is_array($raw) || $raw === [] || divi_is_list($raw) === false) {
        return divi_error(
            'invalid_input',
            message: '"conditions" must be a non-empty list of {conditionName, settings, operator?}.',
        );
    }

    $list = [];
    /** @var mixed $entry */
    foreach ($raw as $entry) {
        $built = divi_build_condition($entry);
        if ($built instanceof WP_Error) {
            return $built;
        }
        $list[] = $built;
    }

    $breakpoint = divi_string_arg($input, key: 'breakpoint');
    if ($breakpoint === '') {
        $breakpoint = 'desktop';
    }

    $patch = ['module' => ['decoration' => ['conditions' => [$breakpoint => ['value' => $list]]]]];
    $result = divi_merge_module_attrs($post, $address, $patch);
    if ($result instanceof WP_Error) {
        return $result;
    }

    return [
        'post_id' => $post->ID,
        'address' => $address,
        'breakpoint' => $breakpoint,
        'conditions' => count($list),
    ];
}
