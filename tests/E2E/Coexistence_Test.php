<?php
/**
 * Real WordPress plugin coexistence, isolated from PHPUnit's Composer loader.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\E2E;

use PHPUnit\Framework\TestCase;

/**
 * @group e2e
 * @group coexistence
 */
final class Coexistence_Test extends TestCase {
    private static array $reports = array();
    private static array $known = array();

    public static function setUpBeforeClass(): void {
        $root = dirname( __DIR__, 2 );
        $directory = $root . '/build/e2e';
        if ( ! is_dir( $directory ) ) {
            mkdir( $directory, 0o755, true );
        }
        $files = array( 'coexistence', 'discrepancies' );
        foreach ( self::scenarios() as $case ) {
            $files[] = $case[0];
        }
        // A failed setup must never leave a previous successful report looking current.
        foreach ( $files as $file ) {
            if ( is_file( $directory . '/' . $file . '.json' ) ) {
                unlink( $directory . '/' . $file . '.json' );
            }
        }
        $plugins = $root . '/tests/tmp/coexistence/plugins';
        foreach ( array( 'legacy-plugin', 'modern-plugin' ) as $plugin ) {
            self::assertFileExists( $plugins . '/' . $plugin . '/vendor/autoload_packages.php', 'Run composer test:e2e:install first.' );
            foreach ( array( 'plugin.php', 'probes.php' ) as $file ) {
                self::assertSame(
                    hash_file( 'sha256', $root . '/test/fixtures/coexistence/' . $plugin . '/' . $file ),
                    hash_file( 'sha256', $plugins . '/' . $plugin . '/' . $file ),
                    'Fixture entrypoints changed. Rerun composer test:e2e:install.',
                );
            }
        }
        self::assertFileExists( ( getenv( 'WP_CORE_DIR' ) ?: $root . '/tests/tmp/wordpress' ) . '/wp-load.php', 'Run composer test:install first.' );
        $env = array_merge(
            getenv(),
            array(
                'WP_TESTS_SQLITE_DIR' => $root . '/tests/tmp/coexistence/database',
                'WP_TESTS_TABLE_PREFIX' => 'coexist_',
            ),
        );
        self::run_php(
            array( $root . '/vendor/wp-phpunit/wp-phpunit/includes/install.php', $root . '/tests/wp-tests-config.php', 'no_ms_tests', 'no_core_tests' ),
            $env,
        );
        foreach ( array( 'legacy-only', 'modern-only', 'legacy-first', 'modern-first' ) as $order ) {
            foreach ( array( 'cold', 'warm' ) as $cache ) {
                $name = $order . '-' . $cache;
                $file = $directory . '/' . $name . '.json';
                self::run_php( array( __DIR__ . '/request.php', $order, $cache, $file ), $env );
                self::assertFileExists( $file );
                self::$reports[ $name ] = json_decode( file_get_contents( $file ), true, 512, JSON_THROW_ON_ERROR );
            }
        }
        self::$known = require __DIR__ . '/known-incompatibilities.php';
        file_put_contents( $directory . '/coexistence.json', json_encode( self::$reports, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n" );
        $discrepancies = array();
        foreach ( self::$reports as $scenario => $report ) {
            foreach ( array( 'legacy', 'modern', 'isolation' ) as $family ) {
                foreach ( $report[ $family ] as $check => $result ) {
                    if ( isset( $result['error'] ) || $result['expected'] !== $result['actual'] ) {
                        $discrepancies[] = array( 'scenario' => $scenario, 'family' => $family, 'check' => $check, 'issue' => self::$known[ $check ]['issue'] ?? null ) + $result;
                    }
                }
            }
        }
        file_put_contents( $directory . '/discrepancies.json', json_encode( $discrepancies, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR ) . "\n" );
    }

    /** @dataProvider scenarios
     * @group coexistence-autoload
     */
    public function test_plugin_loading_selects_the_expected_di_files( string $scenario ): void {
        $report = self::$reports[ $scenario ];
        self::assertNull( $report['bootstrap_error'], json_encode( $report['bootstrap_error'] ) );
        self::assertSame( $report['expected_order'], $report['plugin_order'] );
        self::assertFalse( $report['di_loaded_before_plugins'], 'The runner must not preload DI.' );
        self::assertFalse( $report['factory_loaded_before_plugins_loaded'], 'A plugin eagerly loaded the factory.' );
        self::assertSame( array(), $report['events_before_plugins_loaded'], 'Application startup happened too early.' );
        self::assertSame( array(), $report['php_errors'], json_encode( $report['php_errors'] ) );
        if ( str_contains( $scenario, '-only-' ) ) {
            self::assertSame( array(), $report['notices'] );
        } else {
            self::assertCount( 1, $report['notices'], json_encode( $report['notices'] ) );
            self::assertSame( 'xwp_create_app', $report['notices'][0]['function'] );
            self::assertStringContainsString( 'Container coexist_legacy initialized with deprecated options:', $report['notices'][0]['message'] );
        }
        self::assertCount( 8, $report['sources'] );
        foreach ( $report['sources'] as $symbol => $source ) {
            self::assertStringStartsWith( $report['expected_package'] . '/', $source, $symbol );
        }
        foreach ( $report['loaded_di_classes'] as $class => $source ) {
            self::assertStringStartsWith( $report['expected_package'] . '/', $source, 'Mixed DI class version: ' . $class );
        }
        self::assertSame( str_ends_with( $scenario, '-warm' ), $report['warm_discovery'], 'Jetpack discovery was not actually cold/warm.' );
        if ( $report['warm_discovery'] ) {
            foreach ( $report['expected_order'] as $plugin ) {
                self::assertContains( '{{WP_PLUGIN_DIR}}/' . $plugin . '-plugin', $report['discovery_paths'] );
            }
        }
    }

    /** @dataProvider scenarios
     * @group coexistence-startup
     */
    public function test_initialization_uses_the_declared_wordpress_hook( string $scenario ): void {
        $events = array_filter( self::$reports[ $scenario ]['events'], static fn( $event ) => str_ends_with( $event['event'], '.initialize' ) );
        self::assertNotEmpty( $events );
        foreach ( $events as $event ) {
            self::assertSame( 'init', $event['hook'], $event['plugin'] . '/' . $event['event'] );
        }
    }

    /** @dataProvider legacy_checks
     * @group coexistence-legacy
     */
    public function test_legacy_plugin_behavior( string $scenario, string $check ): void {
        $result = self::$reports[ $scenario ]['legacy'][ $check ] ?? null;
        self::assertNotNull( $result, 'Missing probe ' . $check );
        $matches = ! isset( $result['error'] ) && $result['expected'] === $result['actual'];
        if ( $matches ) {
            self::assertSame( $result['expected'], $result['actual'] );
            return;
        }

        $message = $scenario . ' / ' . $check . ': ' . json_encode( $result, JSON_UNESCAPED_SLASHES );
        $known = self::$known[ $check ] ?? null;
        // Baselines and new regressions always fail, even in diagnostic mode.
        if ( str_starts_with( $scenario, 'legacy-only' ) || null === $known ) {
            self::fail( $message );
        }
        if ( isset( $known['error'] ) ) {
            self::assertSame( $known['error'], $result['error']['class'] ?? null, $message );
            self::assertStringContainsString( $known['contains'], $result['error']['message'] ?? '', $message );
        } else {
            self::assertArrayNotHasKey( 'error', $result, $message );
            self::assertSame( $known['actual'], $result['actual'], $message );
        }
        self::markTestIncomplete( $known['issue'] . ' — ' . $message );
    }

    /** @dataProvider modern_scenarios
     * @group coexistence-modern
     */
    public function test_modern_plugin_keeps_working( string $scenario ): void {
        $checks = self::$reports[ $scenario ]['modern'];
        self::assertNotEmpty( $checks );
        foreach ( $checks as $name => $result ) {
            self::assertArrayNotHasKey( 'error', $result, $name . ': ' . json_encode( $result ) );
            self::assertSame( $result['expected'], $result['actual'], $name );
        }
    }

    /** @dataProvider paired_scenarios
     * @group coexistence-isolation
     */
    public function test_plugins_keep_independent_services_and_shared_hooks( string $scenario ): void {
        self::assertNotEmpty( self::$reports[ $scenario ]['isolation'] );
        foreach ( self::$reports[ $scenario ]['isolation'] as $name => $result ) {
            self::assertArrayNotHasKey( 'error', $result, $name . ': ' . json_encode( $result ) );
            self::assertSame( $result['expected'], $result['actual'], $name );
        }
    }

    public static function scenarios(): array {
        $cases = array();
        foreach ( array( 'legacy-only', 'modern-only', 'legacy-first', 'modern-first' ) as $order ) {
            foreach ( array( 'cold', 'warm' ) as $cache ) {
                $name = $order . '-' . $cache;
                $cases[ $name ] = array( $name );
            }
        }
        return $cases;
    }

    public static function modern_scenarios(): array {
        return array_filter( self::scenarios(), static fn( $case ) => ! str_starts_with( $case[0], 'legacy-only' ) );
    }

    public static function paired_scenarios(): array {
        return array_filter( self::scenarios(), static fn( $case ) => ! str_contains( $case[0], '-only-' ) );
    }

    public static function legacy_checks(): array {
        $cases = array();
        $checks = array(
            'startup.root_once', 'startup.import_once', 'startup.handler_once',
            'definitions.root', 'definitions.import', 'service.constructor_injection', 'service.singleton',
            'hooks.filter_arguments', 'hooks.filter_once', 'hooks.action_arguments', 'hooks.dynamic_filter',
            'container.lookup', 'container.typed_return', 'container.declared_has_method',
            'helpers.module_lookup', 'helpers.handler_registration', 'helpers.extend_named_arguments',
            'helpers.option_definition', 'helpers.transient_definition', 'helpers.filtered_definition',
            'decorators.positional_module',
        );
        foreach ( self::scenarios() as $case ) {
            if ( str_starts_with( $case[0], 'modern-only' ) ) {
                continue;
            }
            foreach ( $checks as $check ) {
                $cases[ $case[0] . ' / ' . $check ] = array( $case[0], $check );
            }
        }
        return $cases;
    }

    private static function run_php( array $arguments, array $env ): void {
        $stdout = tmpfile();
        $stderr = tmpfile();
        $process = proc_open( array_merge( array( PHP_BINARY, '-d', 'auto_prepend_file=' ), $arguments ), array( 0 => array( 'pipe', 'r' ), 1 => $stdout, 2 => $stderr ), $pipes, dirname( __DIR__, 2 ), $env );
        self::assertIsResource( $process );
        fclose( $pipes[0] );
        $deadline = microtime( true ) + 60;
        do {
            $status = proc_get_status( $process );
            if ( ! $status['running'] ) {
                break;
            }
            usleep( 10000 );
        } while ( microtime( true ) < $deadline );
        if ( $status['running'] ) {
            proc_terminate( $process, 9 );
        }
        $closed = proc_close( $process );
        $exit = $status['exitcode'] >= 0 ? $status['exitcode'] : $closed;
        rewind( $stdout );
        rewind( $stderr );
        $output = stream_get_contents( $stdout ) . stream_get_contents( $stderr );
        fclose( $stdout );
        fclose( $stderr );
        self::assertFalse( $status['running'], 'Subprocess exceeded 60 seconds: ' . implode( ' ', $arguments ) );
        self::assertSame( 0, $exit, implode( ' ', $arguments ) . "\n" . $output );
    }
}
