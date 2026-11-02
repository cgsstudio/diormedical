<?php

namespace Hostinger\AiAssistant\Chatbot\Auth;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
    die;
}

class AuthErrors {
    public const LOGIN_REQUIRED = 'login_required';
    public const INVALID_LOGIN  = 'invalid_login';
    public const RELAY_ERROR    = 'relay_error';
    public const SITE_NOT_READY = 'site_not_ready';

    public static function login_required(): WP_Error {
        return new WP_Error(
            self::LOGIN_REQUIRED,
            __( 'Log in with your Hostinger account to use the assistant.', 'hostinger-ai-assistant' ),
            array( 'status' => 401 )
        );
    }

    public static function invalid_login(): WP_Error {
        return new WP_Error(
            self::INVALID_LOGIN,
            __( 'This login was not started by your account. Start the login again.', 'hostinger-ai-assistant' ),
            array( 'status' => 400 )
        );
    }

    public static function site_not_ready(): WP_Error {
        return new WP_Error(
            self::SITE_NOT_READY,
            __( 'This site is still being set up. Wait a few minutes and try again.', 'hostinger-ai-assistant' ),
            array( 'status' => 409 )
        );
    }

    public static function relay_error(): WP_Error {
        return new WP_Error(
            self::RELAY_ERROR,
            __( 'The login service could not be reached. Try again in a moment.', 'hostinger-ai-assistant' ),
            array( 'status' => 502 )
        );
    }
}
