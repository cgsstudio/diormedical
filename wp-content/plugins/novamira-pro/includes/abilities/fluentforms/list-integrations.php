<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\FluentForms;

use WP_Error;

if (!defined('ABSPATH')) {
    exit();
}

if (!ff_pro_runtime_available()) {
    return;
}

wp_register_ability('novamira/fluentforms-list-integrations', [
    'label' => __('List Fluent Forms integration feeds', domain: 'novamira-pro'),
    'description' => __(
        'List the marketing-integration / webhook / payment-platform feeds configured on a Fluent Forms form. Each entry returns {feed_id, integration, name, enabled, raw — the decoded feed body}. Required: form_id. The integration vocabulary (mailchimp / activecampaign / hubspot / convertkit / getresponse / mailerlite / google_sheet / slack / discord / zapier / make / webhook / trello / airtable / hubspot / …) is the same surface check-setup advertises under integrations_active.feeds. Empty list when the form has no configured feeds. Capability: fluentform_settings_manager (falls back to forms_manager).',
        domain: 'novamira-pro',
    ),
    'category' => 'fluentforms',
    'input_schema' => [
        'type' => 'object',
        'default' => [],
        'properties' => [
            'form_id' => ['type' => 'integer', 'minimum' => 1],
        ],
        'required' => ['form_id'],
        'additionalProperties' => false,
    ],
    'output_schema' => [
        'type' => 'object',
        'properties' => [
            'form_id' => ['type' => 'integer'],
            'integrations' => ['type' => 'array', 'items' => ['type' => 'object']],
        ],
        'required' => ['form_id', 'integrations'],
    ],
    'execute_callback' => 'Novamira\Pro\Abilities\FluentForms\fluentforms_list_integrations',
    'permission_callback' => 'novamira_permission_callback',
    'meta' => [
        'show_in_rest' => true,
        'mcp' => ['public' => true],
        'annotations' => [
            'instructions' => 'Pro-only. Returns the integration feeds wired into the form; each entry includes the raw decoded feed body so the agent can read the destination list / channel / spreadsheet without an extra round-trip.',
            'readonly' => true,
            'destructive' => false,
            'idempotent' => true,
        ],
    ],
]);

/**
 * Map of integration key → form_meta notification key. Mirrors the
 * parent::__construct calls inside each FF Pro Integration\Bootstrap.
 * Pinned to the FF Pro 6.0.0+ stable surface; new integrations can be
 * added without breaking existing readers because the helper falls back
 * to a LIKE scan when an unknown feed key turns up on the form.
 *
 * @return array<string, string>
 */
function ff_integration_feed_key_map(): array
{
    return [
        'mailchimp' => 'mailchimp_feed_settings',
        'activecampaign' => 'activecampaign_feeds',
        'hubspot' => 'hubspot_feeds',
        'convertkit' => 'convertkit_feeds',
        'getresponse' => 'getresponse_feeds',
        'mailerlite' => 'mailerlite_feeds',
        'google_sheet' => 'google_sheet_notification_feed',
        'slack' => 'slack_feed',
        'discord' => 'discord_feed',
        'zapier' => 'fluentform_zapier_feed',
        'make' => 'fluentform_make_feed',
        'webhook' => 'fluentform_webhook_feed',
        'trello' => 'fluentform_Trello_feed',
        'airtable' => 'fluentform_airtable_feed',
        'notion' => 'fluentform_notion_feed',
        'telegram' => 'fluentform_telegram_feed',
        'sms_twilio' => 'fluentform_twilio_feed',
        'campaignmonitor' => 'campaign_monitor_feed',
        'constantcontact' => 'constant_contact_feeds',
        'drip' => 'drip_feeds',
        'iContact' => 'i_contact_feeds',
        'mailpoet' => 'mailpoet_feeds',
        'moosend' => 'moosend_feeds',
        'pipedrive' => 'pipedrive_feeds',
        'salesforce' => 'salesforce_feeds',
        'sendinblue' => 'sendinblue_feeds',
        'sendy' => 'sendy_feeds',
        'zoho' => 'zoho_crm_feeds',
    ];
}

/**
 * @param array<string, mixed> $input
 *
 * @return array{form_id:int, integrations:list<array<string, mixed>>}|WP_Error
 */
function fluentforms_list_integrations(array $input): array|WP_Error
{
    $pro = ff_pro_required('integrations');
    if (!ff_pro_runtime_available()) {
        return $pro;
    }
    $form = ff_settings_load_form_for_read($input['form_id'] ?? null);
    if ($form instanceof WP_Error) {
        return $form;
    }
    if (!function_exists('wpFluent')) {
        return ff_operation_failed('Fluent Forms query builder is unavailable.');
    }

    $form_id = (int) $form->id;
    $integrations = ff_list_integrations_collect($form_id);

    return ['form_id' => $form_id, 'integrations' => $integrations];
}

/**
 * Collect the integration feeds wired into the form. Two-pass scan:
 * 1) look up every meta row whose key matches one of the known feed
 *    keys (reverse lookup via the map);
 * 2) decode the body, normalise to a {feed_id, integration, name, enabled,
 *    raw} envelope.
 *
 * @return list<array<string, mixed>>
 */
function ff_list_integrations_collect(int $form_id): array
{
    $map = ff_integration_feed_key_map();
    $reverse = array_flip($map);
    $known_keys = array_values($map);
    if ($known_keys === []) {
        return [];
    }
    $rows = ff_rows_to_array(
        wpFluent()->table('fluentform_form_meta')->where('form_id', $form_id)->whereIn('meta_key', $known_keys)->get(),
    );

    $out = [];
    foreach ($rows as $row) {
        $meta_key = ff_row_str($row, column: 'meta_key');
        $value = ff_row_str($row, column: 'value');
        $integration_key = $reverse[$meta_key] ?? $meta_key;
        $feed_id = ff_row_id($row);
        /** @var mixed $decoded */
        $decoded = ff_decode_integration_feed_value($value);
        $out[] = ff_format_integration_feed(feed_id: $feed_id, integration: $integration_key, decoded: $decoded);
    }
    return $out;
}

/**
 * Decode an integration feed value. FF stores either a single object or
 * a list of objects depending on the integration — both shapes are
 * accepted. Returns the decoded structure or an empty array.
 */
function ff_decode_integration_feed_value(string $value): mixed
{
    if ($value === '') {
        return [];
    }
    /** @var mixed $decoded */
    $decoded = json_decode($value, associative: true);
    return $decoded === null ? [] : $decoded;
}

/**
 * Build the envelope returned in the integrations[] list. enabled and
 * name come from the decoded feed when present.
 *
 * @return array{feed_id:int, integration:string, name:string, enabled:bool, raw:mixed}
 */
function ff_format_integration_feed(int $feed_id, string $integration, mixed $decoded): array
{
    $name = '';
    $enabled = true;
    if (is_array($decoded)) {
        if (isset($decoded['name']) && is_string($decoded['name'])) {
            $name = $decoded['name'];
        }
        if (array_key_exists('enabled', $decoded) && $decoded['enabled'] !== null) {
            /** @var mixed $raw_enabled */
            $raw_enabled = $decoded['enabled'];
            $enabled = $raw_enabled === true || $raw_enabled === 1 || $raw_enabled === '1' || $raw_enabled === 'true';
        }
    }
    return [
        'feed_id' => $feed_id,
        'integration' => $integration,
        'name' => $name,
        'enabled' => $enabled,
        // Strip known secret-bearing keys (api_key / access_token / webhook_url
        // / merchant_id / …) from the raw body so a compromised log or audit
        // trail can't leak vendor credentials. Round-5 audit HIGH.
        'raw' => ff_redact_integration_secrets($decoded),
    ];
}
