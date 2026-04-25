<?php
/**
 * wp-phpunit test configuration.
 *
 * Read by wp-phpunit via the WP_PHPUNIT__TESTS_CONFIG env var set in tests/bootstrap.php.
 * All values are overridable via environment variables so docker-compose locally and
 * GitHub Actions services can hand off the same contract.
 *
 * @package XWP\DI\Tests
 */

$xwpdi_env = static function ( string $name, string $default ): string {
    $value = getenv( $name );
    return false !== $value && '' !== $value ? $value : $default;
};

if ( ! defined( 'XWPDI_TESTS_DIR' ) ) {
    define( 'XWPDI_TESTS_DIR', __DIR__ );
}

if ( ! defined( 'WP_CORE_DIR' ) ) {
    define( 'WP_CORE_DIR', $xwpdi_env( 'WP_CORE_DIR', XWPDI_TESTS_DIR . '/tmp/wordpress' ) );
}

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', rtrim( WP_CORE_DIR, '/' ) . '/' );
}

define( 'DB_NAME',     $xwpdi_env( 'WP_TESTS_DB_NAME',     'wp_phpunit_tests' ) );
define( 'DB_USER',     $xwpdi_env( 'WP_TESTS_DB_USER',     'root' ) );
define( 'DB_PASSWORD', $xwpdi_env( 'WP_TESTS_DB_PASSWORD', 'root' ) );
define( 'DB_HOST',     $xwpdi_env( 'WP_TESTS_DB_HOST',     '127.0.0.1:33076' ) );
define( 'DB_CHARSET',  'utf8mb4' );
define( 'DB_COLLATE',  '' );

$table_prefix = $xwpdi_env( 'WP_TESTS_TABLE_PREFIX', 'wptests_' );

define( 'WP_TESTS_DOMAIN',     $xwpdi_env( 'WP_TESTS_DOMAIN', 'example.org' ) );
define( 'WP_TESTS_EMAIL',      $xwpdi_env( 'WP_TESTS_EMAIL',  'admin@example.org' ) );
define( 'WP_TESTS_TITLE',      $xwpdi_env( 'WP_TESTS_TITLE',  'xwp-di tests' ) );
define( 'WP_PHP_BINARY',       'php' );
define( 'WPLANG',              '' );

define( 'WP_TESTS_MULTISITE',  false );
define( 'WP_DEBUG',            true );

define(
    'WP_TESTS_PHPUNIT_POLYFILLS_PATH',
    dirname( __DIR__ ) . '/vendor/yoast/phpunit-polyfills'
);

define(
    'AUTH_KEY',         'put your unique phrase here'
);
define( 'SECURE_AUTH_KEY',  'put your unique phrase here' );
define( 'LOGGED_IN_KEY',    'put your unique phrase here' );
define( 'NONCE_KEY',        'put your unique phrase here' );
define( 'AUTH_SALT',        'put your unique phrase here' );
define( 'SECURE_AUTH_SALT', 'put your unique phrase here' );
define( 'LOGGED_IN_SALT',   'put your unique phrase here' );
define( 'NONCE_SALT',       'put your unique phrase here' );
