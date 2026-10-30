<?php
/**
 * Content wrappers
 *
 * @package     Gym Builder/Templates
 * @version     1.0.0
 */


use WpDreamers\WPDDB\Controllers\Helper\Helper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

$template = Helper::get_theme_slug_for_templates();

switch ($template) {
    case 'twentyten' :
        echo '</div></div>';
        break;
    case 'twentyeleven' :
        echo '</div>';
        get_sidebar('shop');
        echo '</div>';
        break;
    case 'twentytwelve' :
        echo '</div></div>';
        break;
    case 'twentythirteen' :
        echo '</div></div>';
        break;
    case 'twentyfourteen' :
        echo '</div></div></div>';
        get_sidebar('content');
        break;
    case 'twentyfifteen' :
        echo '</div></div>';
        break;
	case 'oceanwp' :
		echo '</div>';
		break;
    case 'twentysixteen' :
        echo '</main></div>';
        break;
    case 'kadence' :
        echo '</main></div></div>';
        break;
    case 'generatepress' :
        echo '</main></div></div>';
        break;
    case 'blocksy' :
        echo '</main></div></div>';
        break;
    case 'neve' :
        echo '</main></div></div></div>';
        break;
    case 'sydney' :
        echo '</main></div></div>';
        break;
    case 'storefront' :
        echo '</main></div>';
        break;
    case 'Avada' :
        echo '</main></div></div>';
        break;
    case 'Divi' :
        echo '</main></div></div></div></div>';
        break;
    case 'twentytwentyone' :
    case 'twentytwentytwo' :
    case 'twentytwentythree' :
    case 'twentytwentyfour' :
    case 'twentytwentyfive' :
        echo '</main></div>';
        break;
    default :
        echo '</main></div>';
        break;
}
