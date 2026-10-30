<?php
/**
 * Copyright (C) 2014-2025 ServMask Inc.
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * Attribution: This code is part of the All-in-One WP Migration plugin, developed by
 *
 * ███████╗███████╗██████╗ ██╗   ██╗███╗   ███╗ █████╗ ███████╗██╗  ██╗
 * ██╔════╝██╔════╝██╔══██╗██║   ██║████╗ ████║██╔══██╗██╔════╝██║ ██╔╝
 * ███████╗█████╗  ██████╔╝██║   ██║██╔████╔██║███████║███████╗█████╔╝
 * ╚════██║██╔══╝  ██╔══██╗╚██╗ ██╔╝██║╚██╔╝██║██╔══██║╚════██║██╔═██╗
 * ███████║███████╗██║  ██║ ╚████╔╝ ██║ ╚═╝ ██║██║  ██║███████║██║  ██╗
 * ╚══════╝╚══════╝╚═╝  ╚═╝  ╚═══╝  ╚═╝     ╚═╝╚═╝  ╚═╝╚══════╝╚═╝  ╚═╝
 */

if ( ! defined( 'ABSPATH' ) ) {
	die( 'Kangaroos cannot jump here' );
}

// Include all the files that you want to load in here
require_once AI1WMUE_CONTROLLER_PATH . '/class-ai1wmue-advanced-options-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/class-ai1wmue-backups-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/class-ai1wmue-export-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/class-ai1wmue-gtm-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/class-ai1wmue-import-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/class-ai1wmue-install-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/class-ai1wmue-main-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/class-ai1wmue-report-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/class-ai1wmue-reset-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/class-ai1wmue-schedules-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/class-ai1wmue-settings-controller.php';

require_once AI1WMUE_CONTROLLER_PATH . '/reset/class-ai1wmue-reset-tools-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/schedules/class-ai1wmue-schedule-events-controller.php';
require_once AI1WMUE_CONTROLLER_PATH . '/settings/class-ai1wmue-pro-settings-controller.php';

require_once AI1WMUE_MODEL_PATH . '/export/class-ai1wmue-export-compatibility.php';
require_once AI1WMUE_MODEL_PATH . '/export/class-ai1wmue-export-retention.php';

require_once AI1WMUE_MODEL_PATH . '/import/class-ai1wmue-import-compatibility.php';
require_once AI1WMUE_MODEL_PATH . '/import/class-ai1wmue-import-select-settings.php';
require_once AI1WMUE_MODEL_PATH . '/import/class-ai1wmue-import-update-settings.php';
require_once AI1WMUE_MODEL_PATH . '/import/class-ai1wmue-import-upload.php';

require_once AI1WMUE_MODEL_PATH . '/reset/class-ai1wmue-reset-compatibility.php';
require_once AI1WMUE_MODEL_PATH . '/reset/class-ai1wmue-reset-database.php';
require_once AI1WMUE_MODEL_PATH . '/reset/class-ai1wmue-reset-init.php';
require_once AI1WMUE_MODEL_PATH . '/reset/class-ai1wmue-reset-media.php';
require_once AI1WMUE_MODEL_PATH . '/reset/class-ai1wmue-reset-plugins.php';
require_once AI1WMUE_MODEL_PATH . '/reset/class-ai1wmue-reset-themes.php';

require_once AI1WMUE_MODEL_PATH . '/schedules/class-ai1wmue-schedule-event-log.php';
require_once AI1WMUE_MODEL_PATH . '/schedules/class-ai1wmue-schedule-event.php';
require_once AI1WMUE_MODEL_PATH . '/schedules/class-ai1wmue-schedule-events.php';

require_once AI1WMUE_MODEL_PATH . '/class-ai1wmue-settings.php';
require_once AI1WMUE_MODEL_PATH . '/class-ai1wmue-upgrader.php';

// Load WP CLI commands
if ( defined( 'WP_CLI' ) || defined( 'AI1WMUE_PHPUNIT' ) ) {
	require_once AI1WMUE_VENDOR_PATH . '/servmask/cli/class-ai1wmue-backup-cli-base.php';
	require_once AI1WMUE_VENDOR_PATH . '/servmask/cli/class-ai1wmue-backup-cli-command.php';
}
