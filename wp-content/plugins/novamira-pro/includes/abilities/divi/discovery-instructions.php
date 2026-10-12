<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro;

if (!defined('ABSPATH')) {
    exit();
}

add_filter(
    'novamira_discover_abilities_instructions',
    static function (mixed $instructions): mixed {
        if (
            !is_string($instructions)
            || !license_allows_abilities()
            || !novamira_pro_divi_available()
            || !function_exists('wp_get_ability')
            || wp_get_ability('novamira/divi-set-content') === null
        ) {
            return $instructions;
        }

        $note = implode("\n", [
            '## Divi module writes',
            '',
            'Write Divi pages and divi/* modules only through the `novamira/divi-*` abilities: use `novamira/divi-set-content` for whole trees and `novamira/divi-add-module`, `novamira/divi-edit-module`, `novamira/divi-move-module`, or `novamira/divi-delete-module` for targeted changes. Never use the Gutenberg Block Editor Queue (`novamira/gutenberg-add-pending-change` / `novamira/gutenberg-enable-batch-finalization`), `novamira/gutenberg-write-content`, or raw post content because Divi modules are not registered in the standard block editor runtime and cannot be finalized there.',
        ]);

        return $note . "\n\n" . $instructions;
    },
    // Run after the priority-5 license/context layer and before the priority-15
    // memory index. Prepending keeps this note away from the truncatable tail
    // while preserving the established ordering of the other Pro sections.
    priority: 12,
);
