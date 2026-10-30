<?php
define( 'WP_CACHE', true ); // Added by WP Rocket

/**
 * The base configuration for WordPress
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the website, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * ABSPATH
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/
 *
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define('DB_NAME', 'new-diormedical');

/** Database username */
define('DB_USER', 'root');

/** Database password */
define('DB_PASSWORD', '');

/** Database hostname */
define('DB_HOST', 'localhost');

/** Database charset to use in creating database tables. */
define('DB_CHARSET', 'utf8mb4');

/** The database collate type. Don't change this if in doubt. */
define('DB_COLLATE', '');

/**#@+
 * Authentication unique keys and salts.
 *
 * Change these to different unique phrases! You can generate these using
 * the {@link https://api.wordpress.org/secret-key/1.1/salt/ WordPress.org secret-key service}.
 *
 * You can change these at any point in time to invalidate all existing cookies.
 * This will force all users to have to log in again.
 *
 * @since 2.6.0
 */
define('AUTH_KEY', 'p~NtQknz;h!;A@qoeHxjdSBHaoF8l 3]`?0Xdf-Ny c ~<6D#Tw2lFslySk%A(I.');
define('SECURE_AUTH_KEY', '_G~C:eVol7%JulHv319e8^j84|2[g*({CSoA@K4x:U!=|EylVg!`[a )=h{.T3fn');
define('LOGGED_IN_KEY', 'E8YSFpn Cn[FSh<4JoWf|~&*_Ao-d#/FTS]A4O@ZSL+8>@26LLu>?#-_2Vmam-2^');
define('NONCE_KEY', 'jAGo+t)u% )XZA3(eMvFAWed3I+VJ4/1}Ersj^w2$3*}kg#:Y5i I4@T9vm46Vy.');
define('AUTH_SALT', '4<(~SIzrPvA)frIa[*VL<~D0u/0XFt0GmF-8ax)NqMP2}k1>chI/>|BB=&I{rW>z');
define('SECURE_AUTH_SALT', ',Gd3gLNRrQ:,,~=VzmqU>m8A-Z$pcZ_Z9Dj{X4yX.Rbr5P>9.:gdk7]lP03FWH,E');
define('LOGGED_IN_SALT', '$-mH_@*@Q.mlyAJSh<h!t[1+Q4y0&Q>ypPJ$$7R&(5u~twKp}O`r/ucS0A0QQ</W');
define('NONCE_SALT', '!(UyvAAteNfFQ71KIT=C$A0f<1~JLyR4=t7UG .p#!AUToI,bQ<zX60O(r:H%qD<');

/**#@-*/

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 *
 * At the installation time, database tables are created with the specified prefix.
 * Changing this value after WordPress is installed will make your site think
 * it has not been installed.
 *
 * @link https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#table-prefix
 */
$table_prefix = 'wp_';

/**
 * For developers: WordPress debugging mode.
 *
 * Change this to true to enable the display of notices during development.
 * It is strongly recommended that plugin and theme developers use WP_DEBUG
 * in their development environments.
 *
 * For information on other constants that can be used for debugging,
 * visit the documentation.
 *
 * @link https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/
 */
define('WP_DEBUG', false);

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if (!defined('ABSPATH')) {
	define('ABSPATH', __DIR__ . '/');
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
