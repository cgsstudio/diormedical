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

<script type="text/html" id="schedule-event-template">
	<form method="post" action="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=ai1wm_schedule_event_save' ), 'ai1wm_schedule_event_save' ) ); ?>" id="ai1wmve-schedule-event-form" class="ai1wm-clear">
		<input type="hidden" name="event_id" v-model="form.event_id">
		<div class="ai1wm-event-fieldset">
			<h2><?php esc_html_e( 'Event info', AI1WM_PLUGIN_NAME ); ?></h2>
			<div class="ai1wm-event-row">
				<div class="ai1wm-event-field">
					<label class="ai1wm-event-label" for="ai1wm-event-title"><?php esc_html_e( 'Title', AI1WM_PLUGIN_NAME ); ?></label>
					<input type="text" class="ai1wm-event-input" id="ai1wm-event-title" name="title" v-model="form.title" placeholder="<?php esc_attr_e( 'Event title here', AI1WM_PLUGIN_NAME ); ?>" required/>
				</div>
				<div class="ai1wm-event-field">
					<label class="ai1wm-event-label" for="ai1wm-event-type"><?php esc_html_e( 'Event type', AI1WM_PLUGIN_NAME ); ?></label>
					<select class="ai1wm-event-input" id="ai1wm-event-type" name="type" v-model="form.type" required>
						<option value="" disabled><?php esc_html_e( 'Select event type', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::TYPE_EXPORT ); ?>">
							<?php esc_html_e( 'Export', AI1WM_PLUGIN_NAME ); ?>
						</option>
					</select>
				</div>
			</div>

			<div class="ai1wm-event-row" v-if="advancedTypeOptions.length">
				<div class="ai1wm-event-field">
					<label class="ai1wm-event-label"><?php esc_html_e( 'Advanced options', AI1WM_PLUGIN_NAME ); ?></label>
					<multiselect id="ai1wm-event-advanced-options" v-model="form.options" :options="advancedTypeOptions" multiple taggable :searchable="false">
						<template v-slot:tag="props">
							<span class="multiselect__tag">
								<span v-html="advancedOptionLocale(props.option)"></span>
								<i aria-hidden="true" tabindex="1" class="multiselect__tag-icon" @click="props.remove(props.option)"></i>
							</span>
						</template>
						<template v-slot:option="props">
							<span v-html="advancedOptionLocale(props.option)"></span>
						</template>
					</multiselect>
				</div>
			</div>
			<input name="options[]" v-for="option in form.options" type="hidden" :value="option" :key="'option_' +  option">

			<div class="ai1wm-event-row" v-if="hasIncremental">
				<div class="ai1wm-event-field">
					<label class="ai1wm-event-label" for="ai1wm-event-incremental">
						<input type="checkbox" class="ai1wm-event-input" id="ai1wm-event-incremental" name="incremental" v-model="form.incremental"/>
						<?php esc_html_e( 'Incremental backup', AI1WM_PLUGIN_NAME ); ?>
					</label>
				</div>
			</div>

			<div class="ai1wm-event-row" v-if="form.type === '<?php echo esc_js( Ai1wmve_Schedule_Event::TYPE_EXPORT ); ?>'">
				<div class="ai1wm-event-field ai1wm-encrypt-backups-container">
					<label class="ai1wm-event-label" for="ai1wm-event-password">
						<input type="checkbox" class="ai1wm-event-input" id="ai1wm-event-password" v-model="encrypted"/>
						<?php esc_html_e( 'Protect this backup with a password', AI1WM_PLUGIN_NAME ); ?>
					</label>
					<div class="ai1wm-encrypt-backups-passwords-toggle" v-if="encrypted">
						<div class="ai1wm-encrypt-backups-passwords-container">
							<toggle-password name="password" placeholder="<?php esc_attr_e( 'Enter a password', AI1WM_PLUGIN_NAME ); ?>" class-name="ai1wm-event-input ai1wm-event-input-small" v-model="form.password"></toggle-password>
							<toggle-password name="password_confirmation" placeholder="<?php esc_attr_e( 'Repeat the password', AI1WM_PLUGIN_NAME ); ?>" class-name="ai1wm-event-input ai1wm-event-input-small" v-model="password" :error="passwordConfirmed ? null : '<?php echo esc_js( __( 'The passwords do not match', AI1WM_PLUGIN_NAME ) ); ?>'"></toggle-password>
						</div>
					</div>
					<input type="hidden" name="password" value="" v-else />
				</div>
			</div>

			<?php if ( ai1wm_has_compression_type( 'gzip' ) || ai1wm_has_compression_type( 'bzip2' ) ) : ?>
				<div class="ai1wm-event-row" v-if="form.type === '<?php echo esc_js( Ai1wmve_Schedule_Event::TYPE_EXPORT ); ?>'">
					<div class="ai1wm-event-field ai1wm-compression-backups-container">
						<label class="ai1wm-event-label" for="ai1wm-event-compression">
							<input type="checkbox" class="ai1wm-event-input" id="ai1wm-event-compression" v-model="compressed"/>
							<?php esc_html_e( 'Compress this backup', AI1WM_PLUGIN_NAME ); ?>
						</label>
						<div class="ai1wm-compression-backups-types-toggle" v-if="compressed">
							<div class="ai1wm-compression-backups-types-container">
								<?php if ( ai1wm_has_compression_type( 'gzip' ) ) : ?>
									<div class="ai1wm-input-compression-container">
										<label for="ai1wm-compression-type-gzip">
											<input type="radio" id="ai1wm-compression-type-gzip" name="compression_type" value="gzip" v-model="form.compression_type" checked />
											<?php esc_html_e( 'GZip (Fast, good compression)', AI1WM_PLUGIN_NAME ); ?>
										</label>
									</div>
								<?php endif; ?>

								<?php if ( ai1wm_has_compression_type( 'bzip2' ) ) : ?>
									<div class="ai1wm-input-compression-container">
										<label for="ai1wm-compression-type-bzip2">
											<input type="radio" id="ai1wm-compression-type-bzip2" name="compression_type" value="bzip2" v-model="form.compression_type" />
											<?php esc_html_e( 'BZip2 (Slower, better compression)', AI1WM_PLUGIN_NAME ); ?>
										</label>
									</div>
								<?php endif; ?>
							</div>
						</div>
						<input type="hidden" name="compression_type" value="" v-else />
					</div>
				</div>
			<?php endif; ?>

			<div class="ai1wm-event-row" v-if="form.type === '<?php echo esc_js( Ai1wmve_Schedule_Event::TYPE_EXPORT ); ?>'">
				<div class="ai1wm-event-field ai1wm-event-field-row">
					<label for="ai1wmve-exclude_files">
						<input type="checkbox" id="ai1wmve-exclude_files" class="ai1wm-event-input" v-model="exclude_files"/>
						<?php esc_html_e( 'Exclude the selected files', AI1WM_PLUGIN_NAME ); ?>
					</label>
					<file-browser :value="this.excludedFiles"></file-browser>
				</div>
			</div>

			<div class="ai1wm-event-row" v-if="form.type === '<?php echo esc_js( Ai1wmve_Schedule_Event::TYPE_EXPORT ); ?>'">
				<div class="ai1wm-event-field ai1wm-event-field-row" id="ai1wmve-db-table-excluder" v-show="databaseIncluded">
					<label for="ai1wmve-exclude_db_tables" v-show="showDbExcluder">
						<input type="checkbox" id="ai1wmve-exclude_db_tables" class="ai1wm-event-input" v-model="exclude_db_tables"/>
						<?php esc_html_e( 'Exclude the selected database tables', AI1WM_PLUGIN_NAME ); ?>
					</label>
					<db-tables v-show="showDbExcluder" :value="this.excludedDbTables" :db-tables='<?php echo json_encode( $exclude_tables, JSON_HEX_APOS ); ?>' label-id="#ai1wmve-exclude_db_tables" field-name="excluded_db_tables" />
				</div>
			</div>

			<div class="ai1wm-event-row" v-if="form.type === '<?php echo esc_js( Ai1wmve_Schedule_Event::TYPE_EXPORT ); ?>'">
				<div class="ai1wm-event-field ai1wm-event-field-row" id="ai1wmve-db-table-excluder" v-show="databaseIncluded">
					<label for="ai1wmve-include_db_tables" v-show="showDbIncluder">
						<input type="checkbox" id="ai1wmve-include_db_tables" class="ai1wm-event-input" v-model="include_db_tables"/>
						<?php esc_html_e( 'Include the selected non‑WP tables', AI1WM_PLUGIN_NAME ); ?>
					</label>
					<db-tables v-show="showDbIncluder" :value="this.includedDbTables" :db-tables='<?php echo json_encode( $include_tables, JSON_HEX_APOS ); ?>' label-id="#ai1wmve-include_db_tables" field-name="included_db_tables" />
				</div>
			</div>
		</div>

		<div class="ai1wm-event-fieldset" v-if="form.type">
			<h2><?php esc_html_e( 'Storage', AI1WM_PLUGIN_NAME ); ?></h2>
			<div class="ai1wm-event-row">
				<div class="ai1wm-event-field">
					<label class="ai1wm-event-label" for="ai1wm-event-storage"><?php esc_html_e( 'Storage', AI1WM_PLUGIN_NAME ); ?></label>
					<select class="ai1wm-event-input" id="ai1wm-event-storage" name="storage" v-model="form.storage" required>
						<option value="" disabled><?php esc_html_e( 'Select storage', AI1WM_PLUGIN_NAME ); ?></option>
						<?php foreach ( apply_filters( 'ai1wmve_export_buttons_schedules', array() ) as $button ) : ?>
							<?php echo $button; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Filtered output from ai1wmve_export_buttons_schedules; each filter callback is responsible for escaping its own output. ?>
						<?php endforeach; ?>
					</select>
				</div>
				<div class="ai1wm-event-field"></div>
			</div>

			<div class="ai1wm-event-row" v-if="storageLink">
				<a :href="storageLink" v-if="this.isServMaskLink" target="_blank"
					v-html="'<?php echo esc_js( __( 'To use <strong>%s</strong> storage, purchase it here.', AI1WM_PLUGIN_NAME ) ); ?>'.replace('%s', storageName)"></a>
				<a :href="storageLink" v-else
					v-html="'<?php echo esc_js( __( 'To use <strong>%s</strong> storage, you need to configure it first.', AI1WM_PLUGIN_NAME ) ); ?>'.replace('%s', storageName)"></a>
			</div>

		</div>

		<div class="ai1wm-event-fieldset" v-if="form.type">
			<h2><?php esc_html_e( 'Schedule', AI1WM_PLUGIN_NAME ); ?></h2>
			<div class="ai1wm-event-row">
				<div class="ai1wm-event-field">
					<label class="ai1wm-event-label" for="ai1wm-event-schedule-interval"><?php esc_html_e( 'Interval', AI1WM_PLUGIN_NAME ); ?></label>
					<select class="ai1wm-event-input" id="ai1wm-event-schedule-interval" v-model="form.schedule.interval" name="schedule[interval]" required>
						<option value="" disabled><?php esc_html_e( 'Select interval', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::INTERVAL_HOURLY ); ?>" v-text="form.incremental ? '<?php echo esc_js( __( 'Continuous', AI1WM_PLUGIN_NAME ) ); ?>' : '<?php echo esc_js( __( 'Hourly', AI1WM_PLUGIN_NAME ) ); ?>'"></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::INTERVAL_DAILY ); ?>" v-text="form.incremental ? '<?php echo esc_js( __( 'Once per day', AI1WM_PLUGIN_NAME ) ); ?>' : '<?php echo esc_js( __( 'Daily', AI1WM_PLUGIN_NAME ) ); ?>'"></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::INTERVAL_WEEKLY ); ?>"><?php esc_html_e( 'Weekly', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::INTERVAL_MONTHLY ); ?>"><?php esc_html_e( 'Monthly', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::INTERVAL_N_HOUR ); ?>"><?php esc_html_e( 'N Hour', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::INTERVAL_N_DAYS ); ?>"><?php esc_html_e( 'N Days', AI1WM_PLUGIN_NAME ); ?></option>
					</select>
				</div>

				<div class="ai1wm-event-field ai1wm-event-field-nested">
					<div class="ai1wm-event-field" v-if="form.schedule.interval === '<?php echo esc_js( Ai1wmve_Schedule_Event::INTERVAL_WEEKLY ); ?>'">
						<label class="ai1wm-event-label" for="ai1wm-event-schedule-weekday"><?php esc_html_e( 'Day', AI1WM_PLUGIN_NAME ); ?></label>
						<select class="ai1wm-event-input" id="ai1wm-event-schedule-weekday" v-model="form.schedule.weekday" name="schedule[weekday]" required>
							<option value="" disabled><?php esc_html_e( 'Day', AI1WM_PLUGIN_NAME ); ?></option>
							<option value="monday"><?php echo esc_html( date_i18n( 'l', strtotime( 'monday' ) ) ); ?></option>
							<option value="tuesday"><?php echo esc_html( date_i18n( 'l', strtotime( 'tuesday' ) ) ); ?></option>
							<option value="wednesday"><?php echo esc_html( date_i18n( 'l', strtotime( 'wednesday' ) ) ); ?></option>
							<option value="thursday"><?php echo esc_html( date_i18n( 'l', strtotime( 'thursday' ) ) ); ?></option>
							<option value="friday"><?php echo esc_html( date_i18n( 'l', strtotime( 'friday' ) ) ); ?></option>
							<option value="saturday"><?php echo esc_html( date_i18n( 'l', strtotime( 'saturday' ) ) ); ?></option>
							<option value="sunday"><?php echo esc_html( date_i18n( 'l', strtotime( 'sunday' ) ) ); ?></option>
						</select>
					</div>

					<div class="ai1wm-event-field" v-else-if="form.schedule.interval === '<?php echo esc_js( Ai1wmve_Schedule_Event::INTERVAL_MONTHLY ); ?>'">
						<label class="ai1wm-event-label" for="ai1wm-event-schedule-day"><?php esc_html_e( 'Day', AI1WM_PLUGIN_NAME ); ?></label>
						<select class="ai1wm-event-input" id="ai1wm-event-schedule-day" v-model="form.schedule.day" name="schedule[day]" required>
							<option value="" disabled><?php esc_html_e( 'Day', AI1WM_PLUGIN_NAME ); ?></option>
							<?php foreach ( range( 1, 28 ) as $day ) : ?>
								<option value="<?php echo esc_attr( $day ); ?>"><?php echo esc_html( date_i18n( 'd', mktime( 0, null, null, null, $day ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="ai1wm-event-field" v-if="form.schedule.interval === '<?php echo esc_js( Ai1wmve_Schedule_Event::INTERVAL_N_HOUR ); ?>' || form.schedule.interval === '<?php echo esc_js( Ai1wmve_Schedule_Event::INTERVAL_N_DAYS ); ?>'">
						<label class="ai1wm-event-label" for="ai1wm-event-schedule-n" v-text="form.schedule.interval === '<?php echo esc_js( Ai1wmve_Schedule_Event::INTERVAL_N_HOUR ); ?>' ? '<?php echo esc_js( __( 'N Hour', AI1WM_PLUGIN_NAME ) ); ?>' : '<?php echo esc_js( __( 'N Days', AI1WM_PLUGIN_NAME ) ); ?>'"></label>
						<input type="number" class="ai1wm-event-input" id="ai1wm-event-schedule-n" name="schedule[n]" v-model="form.schedule.n" :placeholder="form.schedule.interval === '<?php echo esc_js( Ai1wmve_Schedule_Event::INTERVAL_N_HOUR ); ?>' ? '<?php echo esc_js( __( 'Hours', AI1WM_PLUGIN_NAME ) ); ?>' : '<?php echo esc_js( __( 'Days', AI1WM_PLUGIN_NAME ) ); ?>'" required />
					</div>

					<div class="ai1wm-event-field" v-if="form.schedule.interval && form.schedule.interval !== '<?php echo esc_js( Ai1wmve_Schedule_Event::INTERVAL_N_HOUR ); ?>' && form.schedule.interval !== '<?php echo esc_js( Ai1wmve_Schedule_Event::INTERVAL_HOURLY ); ?>'">
						<label class="ai1wm-event-label" for="ai1wm-event-schedule-hour"><?php esc_html_e( 'Hour', AI1WM_PLUGIN_NAME ); ?></label>
						<select class="ai1wm-event-input" id="ai1wm-event-schedule-hour" v-model="form.schedule.hour" name="schedule[hour]" required>
							<option value="" disabled><?php esc_html_e( 'Hour', AI1WM_PLUGIN_NAME ); ?></option>
							<?php foreach ( range( 0, 23 ) as $hour ) : ?>
								<option value="<?php echo esc_attr( $hour ); ?>"><?php echo esc_html( date_i18n( 'g a', mktime( $hour ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>

					<div class="ai1wm-event-field" v-if="! form.incremental && form.schedule.interval">
						<label class="ai1wm-event-label" for="ai1wm-event-schedule-minute"><?php esc_html_e( 'Minute', AI1WM_PLUGIN_NAME ); ?></label>
						<select class="ai1wm-event-input" id="ai1wm-event-schedule-minute" v-model="form.schedule.minute" name="schedule[minute]" required>
							<option value="" disabled><?php esc_html_e( 'Minute', AI1WM_PLUGIN_NAME ); ?></option>
							<?php foreach ( range( 0, 59 ) as $minute ) : ?>
								<option value="<?php echo esc_attr( $minute ); ?>"><?php echo esc_html( date_i18n( 'i', mktime( 0, $minute ) ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</div>
					<input type="hidden" name="schedule[minute]" v-else-if="form.incremental" v-model="form.schedule.minute">
				</div>
			</div>

			<div class="ai1wm-event-row" v-if="! form.incremental">
				<div class="ai1wm-event-field">
					<label class="ai1wm-event-label">
						<input type="checkbox" class="ai1wm-event-input" name="do-not-repeat" v-model="do_not_repeat"/>
						<?php esc_html_e( 'Do not repeat', AI1WM_PLUGIN_NAME ); ?>
					</label>
				</div>
			</div>
		</div>

		<div class="ai1wm-event-fieldset" v-if="hasRetention">
			<h2><?php esc_html_e( 'Retention settings', AI1WM_PLUGIN_NAME ); ?></h2>
			<div class="ai1wm-event-row ai1wm-column">
				<div class="ai1wm-event-field">
					<label for="ai1wmve-backups">
						<?php esc_html_e( 'Keep the most recent', AI1WM_PLUGIN_NAME ); ?>
						<input class="ai1wm-event-input" type="number" min="0" name="retention[backups]" id="ai1wmve-backups" v-model="form.retention.backups" />
					</label>
					<?php esc_html_e( 'backups. Default: 0 unlimited', AI1WM_PLUGIN_NAME ); ?>
				</div>

				<div class="ai1wm-event-field">
					<label for="ai1wmve-total">
						<?php esc_html_e( 'Limit the total size of backups to', AI1WM_PLUGIN_NAME ); ?>
						<input class="ai1wm-event-input" type="number" min="0" name="retention[total]" id="ai1wmve-total" v-model="form.retention.total" />
					</label>
					<select class="ai1wm-event-input" name="retention[total_unit]" id="ai1wmve-total-unit" v-model="form.retention.total_unit">
						<option value="MB"><?php esc_html_e( 'MB', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="GB"><?php esc_html_e( 'GB', AI1WM_PLUGIN_NAME ); ?></option>
					</select>
					<?php esc_html_e( 'Default: 0 unlimited', AI1WM_PLUGIN_NAME ); ?>
				</div>

				<div class="ai1wm-event-field">
					<label for="ai1wmve-days">
						<?php esc_html_e( 'Remove backups older than ', AI1WM_PLUGIN_NAME ); ?>
						<input class="ai1wm-event-input" type="number" min="0" name="retention[days]" id="ai1wmve-days" v-model="form.retention.days" />
					</label>
					<?php esc_html_e( 'days. Default: 0 off', AI1WM_PLUGIN_NAME ); ?>
				</div>
			</div>
		</div>

		<div class="ai1wm-event-fieldset" v-if="form.type">
			<h2><?php esc_html_e( 'Notification', AI1WM_PLUGIN_NAME ); ?></h2>
			<div class="ai1wm-event-row">
				<div class="ai1wm-event-field">
					<label class="ai1wm-event-label" for="ai1wm-event-notification-reminder"><?php esc_html_e( 'Reminder', AI1WM_PLUGIN_NAME ); ?></label>
					<select class="ai1wm-event-input" id="ai1wm-event-notification-reminder" v-model="form.notification.reminder" name="notification[reminder]" required>
						<option value="" disabled><?php esc_html_e( 'Select reminder', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::REMINDER_NONE ); ?>"><?php esc_html_e( 'Never', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::REMINDER_ALWAYS ); ?>"><?php esc_html_e( 'Always (Success & Failure)', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::REMINDER_SUCCESS ); ?>"><?php esc_html_e( 'On Success Only', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::REMINDER_FAILED ); ?>"><?php esc_html_e( 'On Failure Only', AI1WM_PLUGIN_NAME ); ?></option>
					</select>
				</div>
				<div class="ai1wm-event-field">
					<label class="ai1wm-event-label" for="ai1wm-event-notification-status"><?php esc_html_e( 'Status', AI1WM_PLUGIN_NAME ); ?></label>
					<select class="ai1wm-event-input" id="ai1wm-event-notification-status" v-model="form.notification.status" name="notification[status]" :disabled="!form.notification.reminder || form.notification.reminder === '<?php echo esc_js( Ai1wmve_Schedule_Event::REMINDER_NONE ); ?>'">
						<option value="" disabled><?php esc_html_e( 'Select status', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::STATUS_ENABLED ); ?>"><?php esc_html_e( 'Enabled', AI1WM_PLUGIN_NAME ); ?></option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::STATUS_DISABLED ); ?>"><?php esc_html_e( 'Disabled', AI1WM_PLUGIN_NAME ); ?></option>
					</select>
				</div>
				<div class="ai1wm-event-field">
					<label class="ai1wm-event-label" for="ai1wm-event-notification-email"><?php esc_html_e( 'Email', AI1WM_PLUGIN_NAME ); ?></label>
					<input type="text" class="ai1wm-event-input" id="ai1wm-event-notification-email" v-model="form.notification.email" name="notification[email]" placeholder="<?php esc_attr_e( 'Your email here', AI1WM_PLUGIN_NAME ); ?>" :disabled="!form.notification.reminder || form.notification.reminder === '<?php echo esc_js( Ai1wmve_Schedule_Event::REMINDER_NONE ); ?>'" />
				</div>
			</div>
		</div>

		<?php if ( is_multisite() ) : ?>
			<sub-sites v-if="form.type" :checked="form.sites"></sub-sites>
		<?php endif; ?>

		<div class="ai1wm-event-fieldset" v-if="form.type">
			<h2><?php esc_html_e( 'Status', AI1WM_PLUGIN_NAME ); ?></h2>
			<div class="ai1wm-event-row">
				<div class="ai1wm-event-field">
					<label class="ai1wm-event-label" for="ai1wm-event-status"><?php esc_html_e( 'Status', AI1WM_PLUGIN_NAME ); ?></label>
					<select class="ai1wm-event-input" id="ai1wm-event-status" name="status" v-model="form.status">
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::STATUS_ENABLED ); ?>">
							<?php esc_html_e( 'Enabled', AI1WM_PLUGIN_NAME ); ?>
						</option>
						<option value="<?php echo esc_attr( Ai1wmve_Schedule_Event::STATUS_DISABLED ); ?>">
							<?php esc_html_e( 'Disabled', AI1WM_PLUGIN_NAME ); ?>
						</option>
					</select>
				</div>
				<div class="ai1wm-event-field"></div>
			</div>
		</div>

		<div class="ai1wm-event-fieldset" style="display: flex; justify-content: flex-end;">
			<button class="ai1wm-button-green" :disabled="!passwordConfirmed"><?php esc_html_e( 'Save', AI1WM_PLUGIN_NAME ); ?></button>
		</div>
	</form>
</script>
