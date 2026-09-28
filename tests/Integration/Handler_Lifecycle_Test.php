<?php
/**
 * Handler initialization timing against real WordPress hooks.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Decorators\Handler;
use XWP\DIT\Lifecycle\Jit_Handler;
use XWP\DIT\Lifecycle\Lazy_Handler;
use XWP\DIT\Lifecycle\Strategy_Module;

final class Handler_Lifecycle_Test extends TestCase {
    private string $cache_dir;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-strategy-' . uniqid();
        mkdir( $this->cache_dir );
        Strategy_Module::$events = array();
    }

    public function tear_down(): void {
        $this->remove_lifecycle_hooks();
        Strategy_Module::$events = array();
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );
        parent::tear_down();
    }

    /** @dataProvider strategies_and_cache_modes */
    public function test_initialization_occurs_at_the_strategy_boundary( string $strategy, bool $cache_hooks ): void {
        $config = $this->config( $cache_hooks );
        $attachment_hook = 'xwp_strategy_attach_' . $strategy;
        $callback_hook = 'xwp_strategy_value_' . $strategy;
        $initialization = array( 'condition', 'construct', 'initialize' );

        foreach ( array( 'cold', 'warm' ) as $pass ) {
            Strategy_Module::$events = array();
            $app = App_Builder::configure( $config )->build()->get( App::class );

            self::assertSame( array(), Strategy_Module::$events, $pass . ': discovery must not initialize handlers' );
            $app->run();
            self::assertSame( array(), Strategy_Module::$events, $pass . ': app startup must not initialize handlers' );
            self::assertFalse( has_filter( $callback_hook ) );

            do_action( 'xwp_strategy_module' );

            self::assertSame( array(), Strategy_Module::$events, $pass . ': registration must not initialize handlers' );
            self::assertFalse( has_filter( $callback_hook ) );
            self::assertTrue( has_action( $attachment_hook ) );

            do_action( $attachment_hook );

            self::assertTrue( has_filter( $callback_hook ), $pass . ': callback must be attached' );
            self::assertSame(
                'lazy' === $strategy ? $initialization : array(),
                Strategy_Module::$events,
                $pass . ': LAZY initializes on attachment; JIT waits for invocation',
            );

            self::assertSame( 'first:handled', apply_filters( $callback_hook, 'first' ) );
            self::assertSame( array( 'condition', 'construct', 'initialize', 'invoke' ), Strategy_Module::$events );

            self::assertSame( 'second:handled', apply_filters( $callback_hook, 'second' ) );
            self::assertSame(
                array( 'condition', 'construct', 'initialize', 'invoke', 'invoke' ),
                Strategy_Module::$events,
                $pass . ': repeated invocation must reuse the initialized handler',
            );

            self::assertSame( $cache_hooks, file_exists( $this->cache_dir . '/hook-definition.php' ) );
            $this->remove_lifecycle_hooks();
        }
    }

    public static function strategies_and_cache_modes(): array {
        return array(
            'lazy uncached' => array( 'lazy', false ),
            'lazy cached' => array( 'lazy', true ),
            'jit uncached' => array( 'jit', false ),
            'jit cached' => array( 'jit', true ),
        );
    }

    private function remove_lifecycle_hooks(): void {
        foreach ( array( 'xwp_strategy_module', 'xwp_strategy_attach_lazy', 'xwp_strategy_attach_jit', 'xwp_strategy_value_lazy', 'xwp_strategy_value_jit' ) as $hook ) {
            remove_all_filters( $hook );
        }
        remove_all_actions( 'Hook-' . Lazy_Handler::class . '_' . Handler::INIT_LAZY . '_init' );
        remove_all_actions( 'Hook-' . Jit_Handler::class . '_' . Handler::INIT_JIT . '_init' );
    }

    private function config( bool $cache_hooks ): array {
        $id = uniqid( 'strategy_' );

        return array(
            'app_id' => $id,
            'app_class' => 'Compiled' . $id,
            'app_module' => Strategy_Module::class,
            'app_file' => false,
            'app_type' => 'plugin',
            'app_debug' => false,
            'app_preload' => false,
            'app_version' => '1.0.0',
            'cache_app' => false,
            'cache_defs' => false,
            'cache_hooks' => $cache_hooks,
            'cache_dir' => $this->cache_dir,
            'extendable' => false,
            'public' => false,
            'use_attributes' => true,
            'use_autowiring' => true,
            'use_proxies' => false,
        );
    }
}
