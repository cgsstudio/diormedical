<?php
/**
 * @package WpdDocBooker
 * @var int   $doctor_id
 * @version 1.0.0
 */
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}
?>

<?php
/**
 * Hook: wpddb_doctor_loop_item.
 *
 *
 * @hooked doctor_thumbnail - 10
 */

do_action('wpddb_doctor_loop_item_start',$doctor_id);

?>
<?php
/**
 * Hook: wpddb_doctor_loop_item.
 *
 * @hooked doctor_loop_item_wrapper_start - 10
 * @hooked doctor_loop_item_department - 15
 * @hooked doctor_loop_item_title - 20
 * @hooked doctor_loop_item_designation - 25
 * @hooked doctor_loop_item_description - 30
 * @hooked doctor_loop_item_wrapper_end- 100
 */

do_action('wpddb_doctor_loop_item',$doctor_id);

?>