<?php
/**
 * Doctors page — directory row: round photo, then department, name,
 * designation, description and working days, then a column with the buttons.
 *
 * Every hook below is the same one older layouts use, so plugins and settings
 * that add to or hide parts of the card keep working.
 *
 * @package WpdDocBooker
 * @var int   $doctor_id
 * @version 2.0.0
 */

use WpDreamers\WPDDB\Controllers\Hooks\TemplateHooks;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

TemplateHooks::$card = 'row';
?>
<div class="doctor-row">
	<?php
	/**
	 * Hook: wpddb_doctor_loop_item_start.
	 *
	 * @hooked doctor_thumbnail - 10
	 */
	do_action( 'wpddb_doctor_loop_item_start', $doctor_id );

	/**
	 * Hook: wpddb_doctor_loop_item.
	 *
	 * @hooked doctor_loop_item_wrapper_start - 10
	 * @hooked doctor_loop_item_department - 15 (department and wpddb_doctor_card_media as tags)
	 * @hooked doctor_loop_item_title - 20
	 * @hooked doctor_loop_item_designation - 25
	 * @hooked doctor_loop_item_description - 30
	 * @hooked doctor_row_days - 90
	 * @hooked doctor_loop_item_wrapper_end - 100
	 */
	do_action( 'wpddb_doctor_loop_item', $doctor_id );

	/**
	 * Hook: wpddb_doctor_card_footer.
	 *
	 * @hooked doctor_card_footer - 10 (wpddb_doctor_card_before_actions, buttons)
	 */
	do_action( 'wpddb_doctor_card_footer', $doctor_id );
	?>
</div>
<?php
TemplateHooks::$card = false;
