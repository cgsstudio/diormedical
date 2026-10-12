<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Voxel;

use WP_User;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared helpers for the Sprint 6 community / templates / settings abilities.
 * The reads are strict: timeline/replies are approved-content only, notifications
 * are scoped to an explicit user, and settings secrets are always redacted.
 */

/**
 * Format a Voxel created-at as an ISO-8601 string with an explicit UTC offset, or
 * null. The community getters return a raw 'Y-m-d H:i:s' UTC string; normalize it
 * to ISO 'c' so created_at is consistent with the rest of the Voxel surface.
 */
function vx_created_at_iso(mixed $created): ?string
{
    if ($created instanceof \DateTime) {
        return $created->format('c');
    }
    if (is_string($created) && $created !== '') {
        $timestamp = strtotime($created . ' UTC');
        return $timestamp !== false ? gmdate('c', $timestamp) : $created;
    }
    return null;
}

/** Coerce a truthy input to a real bool (the Abilities API validates leniently). */
function vx_coerce_bool(mixed $value): bool
{
    return $value === true || $value === 1 || $value === '1' || $value === 'true';
}

/**
 * Compact row for one timeline status. `content` is truncated to a
 * {value, truncated, full_size} marker (user-authored text can be long).
 *
 * @param \Voxel\Timeline\Status $status
 * @return array<string, mixed>
 */
function vx_status_row(object $status, int $max_bytes): array
{
    return [
        'id' => $status->get_id(),
        'author_id' => $status->get_author_id(),
        'post_id' => $status->get_post_id(),
        'feed' => $status->get_feed(),
        'content' => vx_truncate_field($status->get_content(), $max_bytes),
        'review_score' => $status->get_review_score(),
        'like_count' => $status->get_like_count(),
        'reply_count' => $status->get_reply_count(),
        'moderation' => $status->get_moderation_status(),
        'created_at' => vx_created_at_iso($status->get_created_at()),
    ];
}

/**
 * Compact row for one timeline reply.
 *
 * @param \Voxel\Timeline\Reply $reply
 * @return array<string, mixed>
 */
function vx_reply_row(object $reply, int $max_bytes): array
{
    return [
        'id' => $reply->get_id(),
        'status_id' => $reply->get_status_id(),
        'parent_id' => $reply->get_parent_id(),
        'author_id' => $reply->get_author_id(),
        'content' => vx_truncate_field($reply->get_content(), $max_bytes),
        'like_count' => $reply->get_like_count(),
        'reply_count' => $reply->get_reply_count(),
        'moderation' => $reply->get_moderation_status(),
        'created_at' => vx_created_at_iso($reply->get_created_at()),
    ];
}

/**
 * Compact row for one notification (no rendered subject — that is resolved only
 * by get-notification, to avoid mass event-rendering and keep the list light).
 *
 * @param \Voxel\Notification $notification
 * @return array<string, mixed>
 */
function vx_notification_row(object $notification): array
{
    return [
        'id' => $notification->get_id(),
        'type' => $notification->get_type(),
        'seen' => $notification->is_seen(),
        'created_at' => vx_created_at_iso($notification->get_created_at()),
    ];
}

/**
 * Full notification projection: the row plus the rendered subject (truncated) and
 * the link target. The subject is the same text the user sees in their bell — it
 * describes the event (e.g. a new follower) and never contains private message
 * bodies.
 *
 * @param \Voxel\Notification $notification
 * @return array<string, mixed>
 */
function vx_notification_full(object $notification, int $max_bytes): array
{
    $row = vx_notification_row($notification);
    $row['user_id'] = $notification->get_user_id();
    $subject = $notification->get_subject();
    $row['subject'] = is_string($subject) ? vx_truncate_field($subject, $max_bytes) : null;
    $links_to = $notification->get_links_to();
    $row['links_to'] = is_string($links_to) ? $links_to : null;
    return $row;
}

/**
 * Find the base site-template area config by its short area name (e.g. "header",
 * matching the "templates.header" key). Null when the area is unknown.
 *
 * @return array<array-key, mixed>|null
 */
function vx_find_base_template(string $area): ?array
{
    if ($area === '') {
        return null;
    }
    $key = 'templates.' . $area;
    foreach (\Voxel\get_base_templates() as $template) {
        if (($template['key'] ?? null) === $key) {
            return $template;
        }
    }
    return null;
}

/** Normalize an input party-type to "user", "post", or "" (unknown). */
function vx_normalize_party_type(mixed $type): string
{
    if ($type === 'post') {
        return 'post';
    }
    if ($type === 'user') {
        return 'user';
    }
    return '';
}

/** Map a raw follow-status int to a stable label. */
function vx_follow_status_label(?int $status): string
{
    return match ($status) {
        1 => 'accepted',
        0 => 'requested',
        -1 => 'blocked',
        default => 'none',
    };
}

/**
 * A minimal, non-PII reference for a follower/followed party: type, id, and (for
 * users) display name — never email.
 *
 * @return array{type: string, id: int, display_name: string|null}
 */
function vx_party_ref(string $type, int $id): array
{
    $display = null;
    if ($type === 'user') {
        $user = get_userdata($id);
        $display = $user instanceof WP_User ? $user->display_name : null;
    }
    if ($type === 'post') {
        $title = get_the_title($id);
        $display = $title !== '' ? $title : null;
    }
    return ['type' => $type, 'id' => $id, 'display_name' => $display];
}

/**
 * Substrings that mark a settings key as secret. Matched case-insensitively as a
 * SUBSTRING (not exact), so a future key like `webhook_secret`, `consumer_secret`,
 * `auth_token` or `password` is caught too, not only today's recaptcha.secret /
 * client_secret / api_key. Over-redacting a benign key is a safe failure.
 *
 * @return list<string>
 */
function vx_secret_setting_tokens(): array
{
    return ['secret', 'api_key', 'apikey', 'password', 'private_key', 'access_token', 'auth_token', 'token'];
}

/** Whether a settings key name should be treated as a secret (substring match). */
function vx_is_secret_key(string $key): bool
{
    $lower = strtolower($key);
    foreach (vx_secret_setting_tokens() as $token) {
        if (str_contains($lower, $token)) {
            return true;
        }
    }
    return false;
}

/**
 * Recursively redact secret values from a settings sub-tree. A secret key with a
 * non-empty value becomes "__redacted__" (so the caller knows one is configured);
 * empty/null secrets are left as-is (nothing to hide).
 *
 * @param mixed $value
 * @return mixed
 */
function vx_redact_settings(mixed $value): mixed
{
    if (!is_array($value)) {
        return $value;
    }
    $out = [];
    /** @var mixed $item */
    foreach ($value as $key => $item) {
        if (is_string($key) && vx_is_secret_key($key)) {
            $out[$key] = $item === null || $item === '' ? $item : '__redacted__';
            continue;
        }
        $out[$key] = is_array($item) ? vx_redact_settings($item) : $item;
    }
    return $out;
}
