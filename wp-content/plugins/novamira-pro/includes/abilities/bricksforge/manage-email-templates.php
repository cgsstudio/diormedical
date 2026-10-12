<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Bricksforge;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Locate a template by id in the raw list. Returns [index, object] or null.
 *
 * @param list<\stdClass> $templates
 * @return array{0: int, 1: \stdClass}|null
 */
function brf_find_email_template(array $templates, string $id): ?array
{
    foreach ($templates as $index => $template) {
        if (brf_email_template_id($template) === $id) {
            return [$index, $template];
        }
    }

    return null;
}

/**
 * The compact summary row for one template (no canvas body).
 *
 * @return array<string, mixed>
 */
function brf_email_template_summary(object $template): array
{
    return [
        'id' => brf_email_template_id($template),
        'name' => brf_obj_str($template, key: 'name'),
        'status' => brf_email_template_status($template),
        'subject' => brf_email_template_subject($template),
        'overrides' => brf_email_template_condition_types($template),
        'use_theme' => (get_object_vars($template)['useTheme'] ?? null) === true,
    ];
}

/**
 * Append a "Email Designer tool is off" warning when tool 13 is inactive, so
 * the agent knows the stored templates are not overriding any mail right now.
 *
 * @param array<string, mixed> $response
 * @return array<string, mixed>
 */
function brf_email_tool_warning(array $response): array
{
    if (brf_tool_active(13)) {
        return $response;
    }

    return brf_append_warning(
        $response,
        warning: 'The Email Designer tool (13) is not active, so these templates are stored but override no emails. Enable it with bricksforge-enable-tool slug "email-designer".',
    );
}

wp_register_ability('novamira/bricksforge-list-email-templates', [
    'label' => __('List Bricksforge Email Templates', domain: 'novamira-pro'),
    'description' => __(
        'Lists the Bricksforge Email Designer templates in compact form: id, name, status (active/inactive), subject, the WordPress/Bricks/WooCommerce emails each one overrides (condition types), and whether it uses a theme. The visual email body (canvas) is omitted — use bricksforge-get-email-template for one template. These abilities manage only a metadata subset of each template: name, subject, overridden emails, status, and theme binding. The visual body (canvas) — every canvas field the installed Bricksforge supports, including preheader and per-element responsive overrides where present — is authored in the Bricksforge editor. Where Bricksforge keeps Email Designer revisions, only its editor save endpoint creates them; these abilities do not.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [],
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
                        'id' => ['type' => 'string'],
                        'name' => ['type' => 'string'],
                        'status' => ['type' => 'string'],
                        'subject' => ['type' => 'string'],
                        'overrides' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'use_theme' => ['type' => 'boolean'],
                    ],
                ],
            ],
            'tool_active' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_list_email_templates',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => '`overrides` names which emails a template replaces. tool_active=false means none of them are live yet.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @return array<string, mixed>
 */
function bricksforge_list_email_templates(): array
{
    $templates = [];
    foreach (brf_raw_email_templates() as $template) {
        $templates[] = brf_email_template_summary($template);
    }

    $capped = brf_cap_list($templates);

    return brf_list_omitted_warning(
        brf_email_tool_warning([
            'templates' => $capped['rows'],
            'tool_active' => brf_tool_active(13),
        ]),
        $capped['omitted'],
        noun: 'email template(s)',
    );
}

wp_register_ability('novamira/bricksforge-get-email-template', [
    'label' => __('Get Bricksforge Email Template', domain: 'novamira-pro'),
    'description' => __(
        'Reads one Bricksforge Email Designer template by id. Returns the metadata (name, status, subject, overridden emails, theme binding) always, and the full visual body (canvas) only when include_canvas=true — the canvas is the design tree, large and meant for a visual editor, so it is omitted by default.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The template id (from bricksforge-list-email-templates).'],
            'include_canvas' => [
                'type' => 'boolean',
                'default' => false,
                'description' => 'Include the full visual design tree (canvas). Large — only for inspecting/copying the raw design.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'subject' => ['type' => 'string'],
            'overrides' => ['type' => 'array', 'items' => ['type' => 'string']],
            'conditions' => ['type' => 'array', 'items' => ['type' => 'object']],
            'use_theme' => ['type' => 'boolean'],
            'theme' => ['type' => ['string', 'null']],
            'canvas' => ['type' => ['object', 'null'], 'description' => 'Only present when include_canvas=true.'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_get_email_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Leave include_canvas off unless you truly need the raw design tree. Edit the visual body in a browser/editor, not here.',
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
function bricksforge_get_email_template(array $input): array|WP_Error
{
    $id = isset($input['id']) && is_string($input['id']) ? $input['id'] : '';
    $found = brf_find_email_template(brf_raw_email_templates(), $id);
    if ($found === null) {
        return brf_error(
            code: 'bricksforge_email_template_not_found',
            message: sprintf('No Bricksforge email template with id "%s".', $id),
            data: ['status' => 404],
        );
    }

    $template = $found[1];
    $vars = get_object_vars($template);
    $response = [
        'id' => brf_email_template_id($template),
        'name' => brf_obj_str($template, key: 'name'),
        'status' => brf_email_template_status($template),
        'subject' => brf_email_template_subject($template),
        'overrides' => brf_email_template_condition_types($template),
        'conditions' => brf_email_template_conditions_output($template),
        'use_theme' => ($vars['useTheme'] ?? null) === true,
        'theme' => is_string($vars['theme'] ?? null) ? $vars['theme'] : null,
    ];

    if (($input['include_canvas'] ?? false) === true) {
        $full = brf_email_template_to_output($template);
        $response['canvas'] = $full['canvas'] ?? null;
        $canvas_bytes = strlen((string) wp_json_encode($response['canvas']));
        if ($canvas_bytes > BRF_EMAIL_CANVAS_WARN_BYTES) {
            $response = brf_append_warning($response, warning: sprintf(
                'The canvas design tree is large (~%d KB) — it is included in full for inspection/copying, not truncated. Omit include_canvas unless you need the raw design.',
                (int) ($canvas_bytes / 1024),
            ));
        }
    }

    return brf_email_tool_warning($response);
}

/**
 * The template's raw conditions as plain arrays for output.
 *
 * @return list<array<array-key, mixed>>
 */
function brf_email_template_conditions_output(object $template): array
{
    /** @var mixed $conditions */
    $conditions = get_object_vars($template)['conditions'] ?? null;
    if (!is_array($conditions)) {
        return [];
    }

    $out = [];
    /** @var mixed $condition */
    foreach ($conditions as $condition) {
        /** @var mixed $condition_decoded */
        $condition_decoded = json_decode((string) wp_json_encode($condition), associative: true);
        if (is_array($condition_decoded)) {
            $out[] = $condition_decoded;
        }
    }

    return $out;
}

wp_register_ability('novamira/bricksforge-duplicate-email-template', [
    'label' => __('Duplicate Bricksforge Email Template', domain: 'novamira-pro'),
    'description' => __(
        'Deep-copies an existing Email Designer template into a new one with a fresh id, an inactive status, and NO overridden emails (its conditions are cleared so it does not fight the original). The visual body and theme binding are copied verbatim. Use this to base a new email on an existing design without opening the editor.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The source template id.'],
            'name' => ['type' => 'string', 'description' => 'Name for the copy. Defaults to "<original> (copy)".'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'status' => ['type' => 'string'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_duplicate_email_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'The copy starts inactive with cleared overrides so it never collides with the source. Enable and set its overrides when ready.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => false,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricksforge_duplicate_email_template(array $input): array|WP_Error
{
    $id = isset($input['id']) && is_string($input['id']) ? $input['id'] : '';
    $name_override = isset($input['name']) && is_string($input['name']) ? $input['name'] : null;

    return brf_with_lock('email_templates', static function () use ($id, $name_override): array|WP_Error {
        $templates = brf_raw_email_templates();
        $found = brf_find_email_template($templates, $id);
        if ($found === null) {
            return brf_error(
                code: 'bricksforge_email_template_not_found',
                message: sprintf('No Bricksforge email template with id "%s".', $id),
                data: ['status' => 404],
            );
        }

        // Deep-copy via serialize round-trip (not JSON) so the canvas keeps
        // its exact types — a JSON round-trip coerces whole-number floats to
        // int.
        /** @var mixed $copy */
        $copy = unserialize(serialize($found[1]));
        if (!$copy instanceof \stdClass) {
            return brf_error(
                code: 'bricksforge_email_template_encode_failed',
                message: 'Could not copy the template.',
                data: ['status' => 500],
            );
        }

        $original_name = brf_obj_str($found[1], key: 'name');
        $original_name = $original_name === '' ? 'Template' : $original_name;
        $copy->id = wp_generate_uuid4();
        $copy->name = $name_override ?? $original_name . ' (copy)';
        $copy->status = 'inactive';
        $copy->conditions = [];

        $templates[] = $copy;
        brf_write_email_templates($templates);

        return ['id' => $copy->id, 'name' => $copy->name, 'status' => 'inactive'];
    });
}

wp_register_ability('novamira/bricksforge-edit-email-template', [
    'label' => __('Edit Bricksforge Email Template', domain: 'novamira-pro'),
    'description' => __(
        'Edits the METADATA of an Email Designer template in place (partial merge): name, subject, the emails it overrides (conditions), and its theme binding. Does NOT touch the visual body (canvas, including preheader and per-element responsive overrides where the installed Bricksforge has them) — design that in the Bricksforge editor. Setting conditions replaces the whole overrides list. Use bricksforge-enable/disable-email-template for the on/off status.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The template id.'],
            'name' => ['type' => 'string', 'description' => 'New display name.'],
            'subject' => [
                'type' => 'string',
                'description' => 'New email subject (stored on canvas.emailSubject). For Bricks form emails it can use Twig field placeholders: {{brx_<field_id>}}, with hyphens in the field id written as underscores.',
            ],
            'conditions' => [
                'type' => 'array',
                'description' => 'Replace the overridden-emails list. Each item is {"type": "<hook>"} where <hook> is the EXACT WordPress/WooCommerce mail filter name (e.g. "retrieve_password_notification_email", "wp_new_user_notification_email", "woocommerce_new_order") — NOT an abbreviation, or the plugin never matches it. For Bricks form notifications use {"type":"bricks_form","formId":"<id>"} / {"type":"bricks_form_confirmation","formId":"<id>"} or {"type":"all_bricks_forms"}. Empty array clears all overrides. Reuse the exact strings shown in the `overrides` of an existing template to be safe.',
                'items' => ['type' => 'object'],
            ],
            'use_theme' => ['type' => 'boolean', 'description' => 'Whether the template uses a saved theme.'],
            'theme' => [
                'type' => ['string', 'null'],
                'description' => 'The theme id (from bricksforge-list-email-themes) when use_theme is true.',
            ],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'name' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'subject' => ['type' => 'string'],
            'overrides' => ['type' => 'array', 'items' => ['type' => 'string']],
            'use_theme' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_edit_email_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Metadata only — omit keys you do not want to change. Bricksforge applies ONE template per email: when several templates list the same email, its lookup picks the last matching one in the list (for a specific Bricks form, the first one with that form id), and that lookup does not skip inactive templates while any template is active. Keep overrides unique per email. Where Bricksforge keeps Email Designer revisions, only its editor save endpoint creates them — this ability does not, so read the template first if the user may want the old values back. Overriding authentication/password-reset emails (e.g. retrieve_password_notification_email) replaces security-sensitive mail — confirm the intent with the user.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricksforge_edit_email_template(array $input): array|WP_Error
{
    $id = isset($input['id']) && is_string($input['id']) ? $input['id'] : '';

    $result = brf_with_lock('email_templates', static function () use ($id, $input): \stdClass|WP_Error {
        $templates = brf_raw_email_templates();
        $found = brf_find_email_template($templates, $id);
        if ($found === null) {
            return brf_error(
                code: 'bricksforge_email_template_not_found',
                message: sprintf('No Bricksforge email template with id "%s".', $id),
                data: ['status' => 404],
            );
        }

        $template = $found[1];
        brf_apply_email_template_edits($template, $input);
        $templates[$found[0]] = $template;
        brf_write_email_templates($templates);

        return $template;
    });

    if ($result instanceof WP_Error) {
        return $result;
    }

    return brf_email_tool_warning(brf_email_template_summary($result));
}

/**
 * Apply the provided metadata edits to a template object in place.
 *
 * @param array<string, mixed> $input
 */
function brf_apply_email_template_edits(\stdClass $template, array $input): void
{
    if (isset($input['name']) && is_string($input['name'])) {
        $template->name = $input['name'];
    }
    if (isset($input['use_theme'])) {
        $template->useTheme = brf_bool($input['use_theme']);
    }
    if (array_key_exists('theme', $input)) {
        $template->theme = is_string($input['theme']) ? $input['theme'] : null;
    }
    if (isset($input['conditions']) && is_array($input['conditions'])) {
        /** @var mixed $conditions_decoded */
        $conditions_decoded = json_decode((string) wp_json_encode(array_values($input['conditions'])));
        $template->conditions = is_array($conditions_decoded) ? $conditions_decoded : [];
    }
    if (isset($input['subject']) && is_string($input['subject'])) {
        /** @var mixed $canvas */
        $canvas = get_object_vars($template)['canvas'] ?? null;
        $canvas = $canvas instanceof \stdClass ? $canvas : new \stdClass();
        $canvas->emailSubject = $input['subject'];
        $template->canvas = $canvas;
    }
}

wp_register_ability('novamira/bricksforge-enable-email-template', [
    'label' => __('Enable Bricksforge Email Template', domain: 'novamira-pro'),
    'description' => __(
        'Activates an Email Designer template (status = active) so it overrides the emails in its conditions. A template with no conditions is active but overrides nothing. Bricksforge applies ONE template per email: when several templates list the same email, its lookup picks the last matching one in the list (for a specific Bricks form, the first one with that form id), and that lookup does not skip inactive templates while any template is active. Keep overrides unique per email.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The template id.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'changed' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_enable_email_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Only takes real effect while the Email Designer tool is on (warned otherwise).',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricksforge_enable_email_template(array $input): array|WP_Error
{
    return brf_set_email_template_status($input, status: 'active');
}

wp_register_ability('novamira/bricksforge-disable-email-template', [
    'label' => __('Disable Bricksforge Email Template', domain: 'novamira-pro'),
    'description' => __(
        'Deactivates an Email Designer template (status = inactive). The template and its design are kept — re-enable to restore it. When no template is active, Bricksforge stops overriding emails and WordPress sends its default mail. While any other template is active, Bricksforge can still use this inactive template for the emails in its conditions; to stop that, also clear its overrides with bricksforge-edit-email-template (conditions: []), or delete it.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The template id.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'status' => ['type' => 'string'],
            'changed' => ['type' => 'boolean'],
            'warnings' => ['type' => 'array', 'items' => ['type' => 'string']],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_disable_email_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Reversible — re-enable with bricksforge-enable-email-template. If other templates stay active, also clear this template\'s conditions so Bricksforge cannot still pick it.',
            'readonly' => false,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricksforge_disable_email_template(array $input): array|WP_Error
{
    return brf_set_email_template_status($input, status: 'inactive');
}

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function brf_set_email_template_status(array $input, string $status): array|WP_Error
{
    $id = isset($input['id']) && is_string($input['id']) ? $input['id'] : '';

    $result = brf_with_lock('email_templates', static function () use ($id, $status): array|WP_Error {
        $templates = brf_raw_email_templates();
        $found = brf_find_email_template($templates, $id);
        if ($found === null) {
            return brf_error(
                code: 'bricksforge_email_template_not_found',
                message: sprintf('No Bricksforge email template with id "%s".', $id),
                data: ['status' => 404],
            );
        }

        $template = $found[1];
        $changed = brf_email_template_status($template) !== $status;
        if ($changed) {
            $template->status = $status;
            $templates[$found[0]] = $template;
            brf_write_email_templates($templates);
        }

        return ['id' => $id, 'status' => $status, 'changed' => $changed];
    });

    if ($result instanceof WP_Error) {
        return $result;
    }

    return $status === 'active' ? brf_email_tool_warning($result) : $result;
}

wp_register_ability('novamira/bricksforge-delete-email-template', [
    'label' => __('Delete Bricksforge Email Template', domain: 'novamira-pro'),
    'description' => __(
        'Permanently removes one Email Designer template by id (design and all). There is no trash. To stop it overriding emails without losing it, use bricksforge-disable-email-template instead.',
        domain: 'novamira-pro',
    ),
    'category' => 'bricksforge',
    'input_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string', 'description' => 'The template id.'],
        ],
        'required' => ['id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'id' => ['type' => 'string'],
            'deleted' => ['type' => 'boolean'],
        ],
    ],
    'execute_callback' => 'Novamira\\Pro\\Abilities\\Bricksforge\\bricksforge_delete_email_template',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Irreversible (no trash). Prefer disable when the user might want the design back.',
            'readonly' => false,
            'destructive' => true,
            'idempotent' => true,
        ],
    ],
]);

/**
 * @param array<string, mixed> $input
 * @return array<string, mixed>|WP_Error
 */
function bricksforge_delete_email_template(array $input): array|WP_Error
{
    $id = isset($input['id']) && is_string($input['id']) ? $input['id'] : '';

    $found = brf_with_lock('email_templates', static function () use ($id): bool {
        $templates = brf_raw_email_templates();
        $kept = [];
        $removed = false;
        foreach ($templates as $template) {
            if (brf_email_template_id($template) === $id) {
                $removed = true;
                continue;
            }
            $kept[] = $template;
        }
        if ($removed) {
            brf_write_email_templates($kept);
        }

        return $removed;
    });

    if ($found instanceof WP_Error) {
        return $found;
    }

    if (!$found) {
        return brf_error(
            code: 'bricksforge_email_template_not_found',
            message: sprintf('No Bricksforge email template with id "%s".', $id),
            data: ['status' => 404],
        );
    }

    return ['id' => $id, 'deleted' => true];
}
