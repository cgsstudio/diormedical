<?php
/**
 * Content wrappers
 *
 * @package     WpdDocBooker/Templates
 * @version     1.0.0
 */


use WpDreamers\WPDDB\Controllers\Helper\Helper;

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}
$template = Helper::get_theme_slug_for_templates();

switch ($template) {
    case 'twentyten' :
        echo '<div id="container"><div id="content" role="main">';
        break;
    case 'twentyeleven' :
        echo '<div id="primary"><div id="content" role="main" class="twentyeleven">';
        break;
    case 'twentytwelve' :
        echo '<div id="primary" class="site-content"><div id="content" role="main" class="twentytwelve">';
        break;
    case 'twentythirteen' :
        echo '<div id="primary" class="site-content"><div id="content" role="main" class="entry-content twentythirteen">';
        break;
    case 'twentyfourteen' :
        echo '<div id="primary" class="content-area"><div id="content" role="main" class="site-content twentyfourteen"><div class="tfwc">';
        break;
    case 'twentyfifteen' :
        echo '<div id="primary" role="main" class="content-area twentyfifteen"><div id="main" class="site-main t15wc">';
        break;
    case 'twentysixteen' :
        echo '<div id="primary" class="content-area twentysixteen"><main id="main" class="site-main" role="main">';
        break;
	case 'twentytwenty' :
		echo '<div id="primary" class="content-area section-inner"><main id="main" class="site-main" role="main">';
		break;
	case 'oceanwp' :
		echo '<div id="primary" class="content-area section-inner">';
		break;
    case 'kadence' :
        echo '<div id="primary" class="content-area"><div class="content-container site-container"><main id="main" class="site-main wpddb-kadence-main" role="main">';
        break;
    case 'generatepress' :
        echo '<div id="primary" class="content-area"><div class="grid-container"><main id="main" class="site-main wpddb-gp-main" role="main">';
        break;
    case 'blocksy' :
        echo '<div id="primary" class="content-area"><div class="ct-container"><main id="main" class="site-main" role="main">';
        break;
    case 'neve' :
        echo '<div id="primary" class="container single-post-container"><div class="row"><div class="nv-single-post-wrap col-12"><main id="main" class="site-main" role="main">';
        break;
    case 'sydney' :
        echo '<div id="primary" class="content-area col-md-12"><div class="container"><main id="main" class="site-main" role="main">';
        break;
    case 'storefront' :
        echo '<div id="primary" class="content-area"><main id="main" class="site-main" role="main">';
        break;
    case 'Avada' :
        echo '<div id="primary" class="content-area"><div class="awb-content-main-container"><main id="main" class="site-main" role="main">';
        break;
    case 'Divi' :
        echo '<div id="main-content"><div class="container"><div id="content-area" class="clearfix"><div id="left-area"><main id="main" class="site-main" role="main">';
        break;
    case 'twentytwentyone' :
    case 'twentytwentytwo' :
    case 'twentytwentythree' :
    case 'twentytwentyfour' :
    case 'twentytwentyfive' :
        echo '<div id="primary" class="content-area wp-block-group alignwide"><main id="main" class="site-main" role="main">';
        break;
    default :
        echo '<div id="primary" class="content-area container '.esc_attr($template).'-'.'theme"><main id="main" class="site-main" role="main">';
        break;
}