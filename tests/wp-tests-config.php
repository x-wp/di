<?php
/**
 * wp-phpunit test configuration.
 *
 * Read by wp-phpunit via the WP_PHPUNIT__TESTS_CONFIG env var set in tests/bootstrap.php.
 * SQLite is the default; set WP_TESTS_DB_ENGINE=mysql to use a MySQL server.
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

define( 'DB_ENGINE', $xwpdi_env( 'WP_TESTS_DB_ENGINE', 'sqlite' ) );

if ( ! in_array( DB_ENGINE, array( 'sqlite', 'mysql' ), true ) ) {
    fwrite( STDERR, "WP_TESTS_DB_ENGINE must be sqlite or mysql.\n" );
    exit( 1 );
}

if ( 'sqlite' === DB_ENGINE ) {
    if ( ! extension_loaded( 'pdo_sqlite' ) ) {
        fwrite( STDERR, "SQLite tests require the PHP pdo_sqlite extension.\n" );
        exit( 1 );
    }

    $xwpdi_sqlite = new PDO( 'sqlite::memory:' );
    if ( version_compare( $xwpdi_sqlite->query( 'SELECT sqlite_version()' )->fetchColumn(), '3.37.0', '<' ) ) {
        fwrite( STDERR, "SQLite tests require SQLite 3.37.0 or newer.\n" );
        exit( 1 );
    }
    unset( $xwpdi_sqlite );

    // Shared by wp-phpunit's install subprocess and the test process.
    define( 'DB_DIR', $xwpdi_env( 'WP_TESTS_SQLITE_DIR', XWPDI_TESTS_DIR . '/tmp/database' ) );
    define( 'DB_FILE', 'wp-phpunit.sqlite' );
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
define( 'WP_PHP_BINARY',       escapeshellarg( PHP_BINARY ) );
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
