<?php

namespace Hostinger\AiAssistant\AiSuite;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class AiSuiteTranslations {
    public function get_values(): array {
        return array(
            'hostinger_ai_suite_nav_home'                   => __( 'Home', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_nav_connect'                => __( 'Connect', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_nav_ai_content_writer'      => __( 'AI Content Writer', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_nav_settings'               => __( 'Settings', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_nav_label'                  => __( 'AI Suite navigation', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_header_dashboard_button'    => __( 'Hostinger dashboard', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_header_visit_site_button'   => __( 'Visit site', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_menu_title'                 => __( 'Menu', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_menu_open_button'           => __( 'Open menu', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_menu_close_button'          => __( 'Close menu', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_footer_title'               => __( 'Hostinger AI Suite', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_footer_version'             => __( 'Version', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_footer_academy_link'        => __( 'Hostinger Academy', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_footer_tutorials_link'      => __( 'WP tutorials', 'hostinger-ai-assistant' ),
            'hostinger_ai_suite_footer_knowledge_base_link' => __( 'Knowledge base', 'hostinger-ai-assistant' ),
        );
    }
}
