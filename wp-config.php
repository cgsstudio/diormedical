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
 
 * @package WordPress
 */

// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
define( 'DB_NAME', 'diormedical' );

/** Database username */
define( 'DB_USER', 'root' );

/** Database password */
define( 'DB_PASSWORD', '' );

/** Database hostname */
define( 'DB_HOST', 'localhost' );

/** Database charset to use in creating database tables. */
define( 'DB_CHARSET', 'utf8mb4' );

/** The database collate type. Don't change this if in doubt. */
define( 'DB_COLLATE', '' );

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
define( 'AUTH_KEY',         '19{uLp)I$w1xkOZvpab$:RYW;L_]dZ~(uTN-ET}yHFh=J l+/^N1fA`Q=UaT:jX~' );
define( 'SECURE_AUTH_KEY',  'Vob&bVf]a.?L(y&Gc5UX@jNw}%o}@wfX,vN`VL`8{OcM%n@Hxv<E%.CCJ!N01fsx' );
define( 'LOGGED_IN_KEY',    'LBK,+$eXgn{+#t>DxS[h:T7w$n1D lf}*;(q`XhtFm%ooDunJ5v=QEjU5:d -}#e' );
define( 'NONCE_KEY',        'T&<QlOou?rcbxgXpnVR!I~S8=^_Nv/<dF_O(j[v9K>J%73EIoU=zl?~PlQ~{C8q4' );
define( 'AUTH_SALT',        'BXv06E[b./S@oG,Gz<AH+2R9cFLn[A^+`2vO84eYRufz {vUm@(rK*U2zR.f`0#1' );
define( 'SECURE_AUTH_SALT', '=2T v u2iDQkLQ9b>wTl9)(EDg.t$8%(eGVyT/cv0Tf%t0ouBl-1&x_hzaBk`F8)' );
define( 'LOGGED_IN_SALT',   'U`%NcP1l}.%c! ex]D`Hfe(1&]|uj=}k=yc|8f,DS-^ev^gxVcqU70qU~kvKe3&C' );
define( 'NONCE_SALT',       'd}u_OyY<>szxp&d/wp<~MqT~gq ~!Y1{OU@}v%Pb8]#M!3mi}=Rb]OEY1KA~:/5i' );

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
define( 'WP_DEBUG', false );

/* Add any custom values between this line and the "stop editing" line. */



/* That's all, stop editing! Happy publishing. */

/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';
