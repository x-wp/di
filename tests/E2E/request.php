<?php
/**
 * A fresh WordPress request. Do not load the repository's Composer autoloader here.
 *
 * @package XWP\DI\Tests
 */

$root = dirname( __DIR__, 2 );
$order = $argv[1] ?? '';
$cache = $argv[2] ?? '';
$output = $argv[3] ?? '';
$orders = array(
    'legacy-only' => array( 'legacy' ),
    'modern-only' => array( 'modern' ),
    'legacy-first' => array( 'legacy', 'modern' ),
    'modern-first' => array( 'modern', 'legacy' ),
);
if ( ! isset( $orders[ $order ] ) || ! in_array( $cache, array( 'cold', 'warm' ), true ) || '' === $output ) {
    fwrite( STDERR, "Usage: request.php legacy-only|modern-only|legacy-first|modern-first cold|warm report.json\n" );
    exit( 1 );
}

require $root . '/tests/wp-tests-config.php';
require $root . '/vendor/wp-phpunit/wp-phpunit/includes/functions.php';
tests_reset__SERVER();
define( 'WP_PLUGIN_DIR', $root . '/tests/tmp/coexistence/plugins' );
define( 'WPMU_PLUGIN_DIR', $root . '/tests/tmp/coexistence/mu-plugins' );
define( 'DISABLE_WP_CRON', true );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_DEBUG_DISPLAY', false );
define( 'WP_ENVIRONMENT_TYPE', 'local' );

$report = array(
    'scenario' => $order . '-' . $cache,
    'expected_order' => $orders[ $order ],
    'plugin_order' => array(),
    'di_loaded_before_plugins' => function_exists( 'xwp_load_app' ) || class_exists( 'XWP\\DI\\App_Factory', false ),
    'factory_loaded_before_plugins_loaded' => null,
    'events_before_plugins_loaded' => null,
    'warm_discovery' => null,
    'discovery_paths' => array(),
    'expected_package' => realpath( WP_PLUGIN_DIR . '/' . ( 'legacy-only' === $order ? 'legacy' : 'modern' ) . '-plugin/vendor/x-wp/di' ),
    'bootstrap_error' => null,
    'notices' => array(),
    'php_errors' => array(),
    'sources' => array(),
    'loaded_di_classes' => array(),
    'legacy' => array(),
    'modern' => array(),
    'isolation' => array(),
);
$GLOBALS['xwp_di_e2e_events'] = array();
$GLOBALS['xwp_di_e2e_plugin_order'] = array();

tests_add_filter( 'pre_option_active_plugins', static fn() => array_map( static fn( $plugin ) => $plugin . '-plugin/plugin.php', $orders[ $order ] ) );
tests_add_filter( 'pre_site_option_active_sitewide_plugins', static fn() => array() );
tests_add_filter(
    'muplugins_loaded',
    static function () use ( $cache, &$report ): void {
        if ( 'cold' === $cache ) {
            delete_transient( 'jetpack_autoloader_plugin_paths' );
        }
        $paths = get_transient( 'jetpack_autoloader_plugin_paths' );
        $report['warm_discovery'] = is_array( $paths ) && count( $paths ) > 0;
        $report['discovery_paths'] = $paths ?: array();
    },
);
tests_add_filter(
    'plugins_loaded',
    static function () use ( &$report ): void {
        $report['factory_loaded_before_plugins_loaded'] = class_exists( 'XWP\\DI\\App_Factory', false );
        $report['events_before_plugins_loaded'] = $GLOBALS['xwp_di_e2e_events'];
    },
    PHP_INT_MIN,
);
tests_add_filter(
    'doing_it_wrong_run',
    static function ( $function, $message ) use ( &$report ): void {
        $report['notices'][] = array( 'function' => $function, 'message' => $message );
    },
    10,
    2,
);
// Preserve migration notices in the report instead of PHP's HTML error output.
tests_add_filter( 'doing_it_wrong_trigger_error', static fn() => false );
set_error_handler(
    static function ( int $severity, string $message, string $file, int $line ) use ( &$report ): bool {
        if ( error_reporting() & $severity ) {
            $report['php_errors'][] = compact( 'severity', 'message', 'file', 'line' );
        }
        return true;
    },
);

try {
    require ABSPATH . 'wp-settings.php';
} catch ( Throwable $error ) {
    $report['bootstrap_error'] = array( 'class' => $error::class, 'message' => $error->getMessage() );
}
$report['plugin_order'] = $GLOBALS['xwp_di_e2e_plugin_order'];

foreach ( $orders[ $order ] as $plugin ) {
    $probe = 'XWP\\DI\\E2E\\' . ucfirst( $plugin ) . '\\probes';
    if ( function_exists( $probe ) ) {
        $report[ $plugin ] = $probe();
    }
}

if ( 2 === count( $orders[ $order ] ) && null === $report['bootstrap_error'] ) {
    $checks = array(
        'distinct_apps' => array( true, static fn() => xwp_app( 'coexist_legacy' ) !== xwp_app( 'coexist_modern' ) ),
        'legacy_service' => array( 'legacy', static fn() => xwp_app( 'coexist_legacy' )->get( 'coexist.message' ) ),
        'modern_service' => array( 'modern', static fn() => xwp_app( 'coexist_modern' )->get( 'coexist.message' ) ),
        'shared_filter' => array( array( 'legacy', 'modern' ), static fn() => apply_filters( 'coexist_e2e_shared', array() ) ),
    );
    foreach ( $checks as $name => [ $expected, $callback ] ) {
        try {
            $report['isolation'][ $name ] = array( 'expected' => $expected, 'actual' => $callback() );
        } catch ( Throwable $error ) {
            $report['isolation'][ $name ] = array( 'expected' => $expected, 'error' => array( 'class' => $error::class, 'message' => $error->getMessage() ) );
        }
    }
}

foreach ( array( 'xwp_load_app', 'xwp_create_app', 'xwp_app', 'xwp_load_hook_handler' ) as $function ) {
    if ( function_exists( $function ) ) {
        $report['sources'][ $function ] = ( new ReflectionFunction( $function ) )->getFileName();
    }
}
foreach ( array( 'XWP\\DI\\App_Factory', 'XWP\\DI\\Decorators\\Module', 'XWP\\DI\\Decorators\\Handler', 'XWP_Context' ) as $class ) {
    if ( class_exists( $class ) ) {
        $report['sources'][ $class ] = ( new ReflectionClass( $class ) )->getFileName();
    }
}
foreach ( array_merge( get_declared_classes(), get_declared_interfaces(), get_declared_traits() ) as $class ) {
    if ( str_starts_with( $class, 'XWP\\DI\\' ) && ! str_starts_with( $class, 'XWP\\DI\\E2E\\' ) ) {
        $report['loaded_di_classes'][ $class ] = ( new ReflectionClass( $class ) )->getFileName();
    }
}
$report['events'] = $GLOBALS['xwp_di_e2e_events'];
file_put_contents( $output, json_encode( $report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n" );
