<?php
/** #ddev-generated
 * 
 * Tweaks for WordPress
 */

// ** Disable Jetpack ** //
define( 'JETPACK_DEV_DEBUG', true );

// ** Cron Tweak ** //
define( 'DISABLE_WP_CRON', true );

// ** WC Log Dir Tweak ** //
define( 'WC_LOG_DIR', '/var/www/html/wc-logs' );

// ** Memory Tweak ** //
define( 'WP_MEMORY_LIMIT', '1024M' );
define( 'WP_MAX_MEMORY_LIMIT', '1024M' );