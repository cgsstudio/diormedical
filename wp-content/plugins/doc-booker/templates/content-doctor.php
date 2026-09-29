<?php
/**
 * @package WpdDocBooker/Templates
 * @version 1.0.0
 * @var $layout string doctor layout
 */
defined( 'ABSPATH' ) || exit;
global $post;

use WpDreamers\WPDDB\Controllers\Helper\Helper;

$doctor_id = get_the_ID();

$args = [
	'doctor_id' => $doctor_id,
	'layout'    => $layout
];
?>
<div class="doctor-item">
	<?php
	Helper::render_template( 'doctor/layouts/' . $layout, $args );
	?>
</div>