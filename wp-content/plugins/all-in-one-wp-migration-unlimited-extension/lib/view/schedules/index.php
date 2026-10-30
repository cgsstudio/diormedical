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
?>

<div class="ai1wm-container">
	<div class="ai1wm-row">
		<div class="ai1wm-left">
			<div class="ai1wm-holder">

				<?php if ( Ai1wm_Message::has( 'schedules' ) ) : ?>
					<div class="ai1wm-message ai1wm-success-message">
						<p><?php echo esc_html( Ai1wm_Message::get( 'schedules' ) ); ?></p>
					</div>
				<?php endif; ?>

				<h1 class="ai1wmue-schedule-title">
					<i class="ai1wm-icon-calendar"></i>
					<?php esc_html_e( 'List Created Events', AI1WMUE_PLUGIN_NAME ); ?>
					<a href="<?php echo esc_url( add_query_arg( array( 'action' => 'create-event' ), network_admin_url( 'admin.php?page=ai1wmue_schedules' ) ) ); ?>" class="ai1wm-button-green">
						<i class="ai1wm-icon-plus2"></i>
						<?php esc_html_e( 'New event', AI1WMUE_PLUGIN_NAME ); ?>
					</a>
				</h1>

				<div id="ai1wmue-schedules-list">
					<?php require_once AI1WMUE_TEMPLATES_PATH . '/schedules/schedules-list.php'; ?>
				</div>

				<div id="ai1wmue-schedules-event-vue-actions"></div>

			</div>

		</div>

		<?php require_once AI1WM_TEMPLATES_PATH . '/common/sidebar-right.php'; ?>

	</div>
</div>
