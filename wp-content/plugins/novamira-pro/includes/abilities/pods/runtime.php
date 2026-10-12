<?php

// SPDX-FileCopyrightText: 2026 Ovation S.r.l. <dev@novamira.ai>
// SPDX-License-Identifier: AGPL-3.0-or-later

declare(strict_types=1);

namespace Novamira\Pro\Abilities\Pods;

if (!defined('ABSPATH')) {
    exit();
}

/**
 * Shared runtime guards for Pods abilities.
 *
 * The Advanced Content Types component is optional and gates `create-act`.
 * Keep both checks small and side-effect free so they can run on every load.
 */

/**
 * Whether the Advanced Content Types component is loaded — required to
 * create / edit pods of type `pod` (custom storage tables).
 *
 * Pods registers each component as a class; the ACT component declares
 * `Pods_Advanced_Content_Types` in `components/Advanced-Content-Types.php`
 * (no `Pods_Component_*` prefix despite extending `PodsComponent`).
 * Component activation lives in the `pods_component_settings` option, but
 * checking for the class is the cheaper and more direct test that the
 * runtime actually exposes the table-storage code paths.
 */
function pods_act_runtime_available(): bool
{
    if (!pods_min_runtime_available()) {
        return false;
    }

    return class_exists('Pods_Advanced_Content_Types');
}
