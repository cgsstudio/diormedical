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
define( 'DB_NAME', 'new-diormedical' );

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
define( 'AUTH_KEY',         '`FH{Te-z?AR`q/8bimcxZC;L4_1$`J;q4+m)K!WfzRD]<B|`(CDbECxn~_oR~IGp' );
define( 'SECURE_AUTH_KEY',  '[L*/GampU/7}/QiHW^R|CuDsWgoxv3MmRK#U9%V=h=+tNf6Jg31KUr Sl<3<BPja' );
define( 'LOGGED_IN_KEY',    '?6B?t2-bLP2CT=!NUkPs/s/} <DS7jpmRQD6Bkve0s+XsU[!;bLt+p.{3LhFH_CW' );
define( 'NONCE_KEY',        '=SQ/Ehe1{oF0./Cel^,W3ec. h`2q$xDEb:j,G!16}5esj<:|_Lel$-ol;mekd~@' );
define( 'AUTH_SALT',        'A^=N@h!9tr~)N}z9a0]=Y-RG7X,1w1XS3)g;OM$07{C_nJYo_<c|w;hlSw):B2kp' );
define( 'SECURE_AUTH_SALT', 'q3:Z6k4`j?L9PQY&A<uI.dKT/pF1,%AS0sI*,g&(|7^UZcsF32@sUjWDu WT2-*5' );
define( 'LOGGED_IN_SALT',   'p0R(Pd-/8lXC~$n%2j>]_xtVQqngu4$|[H:IiyG5!~h)Z{KN,K~}GJcAR)=H!zvu' );
define( 'NONCE_SALT',       '<H|j+Ck.4:}M}e{gGc`VuyYE!`:X!i~tkMnFa`}&@9n3f*k,DWXOG1{N))<b{[Aa' );

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
