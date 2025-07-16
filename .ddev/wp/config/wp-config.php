<?php
/** #ddev-generated
 *
 * The wp-config.php creation script uses this file during the installation.
 * You don't have to use the web site, you can copy this file to "wp-config.php"
 * and fill in the values.
 *
 * This file contains the following configurations:
 *
 * * Database settings
 * * Secret keys
 * * Database table prefix
 * * Localized language
 * * ABSPATH
 *
 * @link https://wordpress.org/support/article/editing-wp-config-php/
 *
 * @package WordPress
 */

define( 'WP_CONFIG_BASEDIR',  '/mnt/ddev_config/wp/config');
define( 'WP_CONFIG_EXTRA_BD', '/var/www/html');

if (file_exists(WP_CONFIG_EXTRA_BD . '/wp-config-extra.php')) {
    require_once WP_CONFIG_EXTRA_BD . '/wp-config-extra.php';
}

require_once WP_CONFIG_BASEDIR . '/wp-config-tweaks.php';
require_once WP_CONFIG_BASEDIR . '/wp-config-redis.php';
require_once WP_CONFIG_BASEDIR . '/wp-config-db.php';
require_once WP_CONFIG_BASEDIR . '/wp-config-salt.php';
require_once WP_CONFIG_BASEDIR . '/wp-config-debug.php';


/** Absolute path to the WordPress directory. */
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

/** Sets up WordPress vars and included files. */
require_once ABSPATH . 'wp-settings.php';