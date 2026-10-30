<?php
/**
 * @package WpdDocBooker/Templates
 * @version 1.0.0
 * @var $layout string clinic layout
 */

global $post;

use WpDreamers\WPDDB\Controllers\Helper\Helper;


$args = [
	'clinic_id'     => get_the_ID(),
	'layout' => $layout
];
?>
<div class="clinic-item">
	<?php
	Helper::render_template( 'clinic/layouts/' . $layout, $args );
	?>
</div>