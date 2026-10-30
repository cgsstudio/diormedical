<?php
/**
 * @package WpdDocBooker
 * @var int   $clinic_id
 * @version 1.0.0
 */
if (!defined('ABSPATH')) {
	exit; // Exit if accessed directly
}
?>

<?php
/**
 * Hook: wpddb_clinic_loop_item.
 *
 *
 * @hooked clinic_thumbnail - 10
 */

do_action('wpddb_clinic_loop_item_start',$clinic_id);

?>
<?php
/**
 * Hook: wpddb_clinic_loop_item.
 *
 * @hooked clinic_loop_item_wrapper_start - 10
 * @hooked clinic_loop_item_title - 20
 * @hooked clinic_loop_item_hotline - 30
 * @hooked clinic_loop_item_description - 40
 *  * @hooked clinic_loop_item_details_button - 50
 * @hooked clinic_loop_item_wrapper_end- 100
 */

do_action('wpddb_clinic_loop_item',$clinic_id);

?>