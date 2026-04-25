<?php
/**
 * PHPUnit bootstrap.
 *
 * Suite-aware: the unit suite stays out of WordPress (Brain\Monkey only),
 * the integration suite boots wp-phpunit against the docker MySQL service.
 *
 * @package XWP\DI\Tests
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

$argv_str = implode( ' ', $_SERVER['argv'] ?? array() );
$is_integration = str_contains( $argv_str, 'integration' )
    || str_contains( $argv_str, 'Integration' );

if ( ! $is_integration ) {
    \Brain\Monkey\setUp();
    return;
}

$wp_phpunit_dir = getenv( 'WP_PHPUNIT__DIR' )
    ?: dirname( __DIR__ ) . '/vendor/wp-phpunit/wp-phpunit';

putenv( 'WP_PHPUNIT__TESTS_CONFIG=' . __DIR__ . '/wp-tests-config.php' );
putenv( 'XWPDI_PATH=' . dirname( __DIR__ ) );

require $wp_phpunit_dir . '/includes/functions.php';

tests_add_filter(
    'muplugins_loaded',
    static function (): void {
        require dirname( __DIR__ ) . '/test/fixtures/di-plugin/di-plugin.php';
    }
);

require $wp_phpunit_dir . '/includes/bootstrap.php';
