<?php
/** #ddev-generated
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
 * @link https://wordpress.org/documentation/article/editing-wp-config-php/
 *
 * @package WordPress
 */


// ** Database settings - You can get this info from your web host ** //
/** The name of the database for WordPress */
!defined( 'DB_NAME' )     && define( 'DB_NAME', 'db' );
!defined( 'DB_USER' )     && define( 'DB_USER', 'db' );
!defined( 'DB_PASSWORD' ) && define( 'DB_PASSWORD', 'db' );
!defined( 'DB_HOST' )     && define( 'DB_HOST', 'db' );
!defined( 'DB_CHARSET' )  && define( 'DB_CHARSET', 'utf8mb4' );
!defined( 'DB_COLLATE' )  && define( 'DB_COLLATE', '' );

/**
 * WordPress database table prefix.
 *
 * You can have multiple installations in one database if you give each
 * a unique prefix. Only numbers, letters, and underscores please!
 */
$table_prefix = getenv('WPDB_PREFIX') ?: 'wp_';