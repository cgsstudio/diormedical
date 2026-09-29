<?php
/**
 * Dior Medical - Fullscreen Canvas Template (Equivalent to Elementor Canvas)
 * Removes theme headers, footers, sidebars, and title blocks for a pure web-app view.
 */

if (!defined('ABSPATH')) {
    exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="profile" href="https://gmpg.org/xfn/11">
    <?php wp_head(); ?>
    <style>
        #wpadminbar {
            display: none !important;
            height: 0 !important;
            min-height: 0 !important;
            visibility: hidden !important;
            opacity: 0 !important;
            pointer-events: none !important;
        }
        html, body, html body.admin-bar {
            margin: 0 !important;
            padding: 0 !important;
            margin-top: 0 !important;
            padding-top: 0 !important;
            top: 0 !important;
            width: 100% !important;
            min-height: 100vh !important;
            background: #F8FAFC !important;
            overflow-x: hidden !important;
            -webkit-font-smoothing: antialiased;
        }
        #page, #content, .site, .site-content, .entry-content, .elementor {
            margin: 0 !important;
            padding: 0 !important;
            width: 100% !important;
            max-width: 100% !important;
        }
    </style>
</head>
<body <?php body_class('dior-portal-canvas-body'); ?>>
<?php wp_body_open(); ?>

<div class="dior-canvas-content-wrapper">
    <?php
    while (have_posts()) :
        the_post();
        the_content();
    endwhile;
    ?>
</div>

<?php wp_footer(); ?>
</body>
</html>
