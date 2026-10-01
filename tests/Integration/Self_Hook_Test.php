<?php
/**
 * Verify the !self.hook injection of the registered callback runtime.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Compiled_Container;
use XWP\DI\Hook\Callback;
use XWP\DIT\Lifecycle\Self_Hook_Handler;
use XWP\DIT\Lifecycle\Self_Hook_Module;

final class Self_Hook_Test extends TestCase {
    private string $cache_dir;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-self-hook-' . uniqid();
        mkdir( $this->cache_dir );
        $this->reset_hooks();
    }

    public function tear_down(): void {
        $this->reset_hooks();
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );
        parent::tear_down();
    }

    /** @dataProvider cache_modes */
    public function test_injected_types_identity_and_live_state( bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $callbacks = $this->callbacks( $app );
            $filter = $callbacks['xwp_self_filter'];
            self::assertSame( Callback::class, $filter::class );
            self::assertSame( 17, $filter->get_priority() );
            self::assertSame( 1, $filter->get_num_args() );
            self::assertSame( 'xwp_self_filter', $filter->tag );
            self::assertSame( 'filter_value', $filter->method );
            self::assertSame( Self_Hook_Handler::class, $filter->get_classname() );
            self::assertSame( $app->container(), $filter->get_container() );
            self::assertSame( $app->container()->get( 'Hook-' . Self_Hook_Handler::class ), $filter->get_handler() );
            self::assertSame( 'xwp_self_module', $filter->get_init_hook() );
            self::assertSame( 0, $filter->fired );
            self::assertFalse( $filter->firing );
            self::assertTrue( $filter->is_loaded() );

            self::assertSame( 'first:filtered', apply_filters( 'xwp_self_filter', 'first' ) );
            self::assertSame( 'second:filtered', apply_filters( 'xwp_self_filter', 'second' ) );
            foreach ( Self_Hook_Module::$observations as $count => $observed ) {
                self::assertSame( Callback::class, $observed['hook']::class );
                self::assertSame( $filter, $observed['hook'] );
                self::assertSame( Self_Hook_Module::$observations[0]['hook'], $observed['hook'] );
                self::assertSame( $filter, $app->container()->get( $observed['hook']->get_token() ) );
                self::assertSame( 'xwp_self_filter', $observed['tag'] );
                self::assertSame( 'filter_value', $observed['method'] );
                self::assertSame( $count, $observed['fired'] );
                self::assertTrue( $observed['firing'] );
                self::assertSame( array( $filter, 'invoke' ), $observed['target'] );
                self::assertSame( 17, has_filter( $observed['tag'], $observed['target'] ) );
            }
            self::assertSame( 2, Self_Hook_Module::$observations[0]['hook']->fired, 'A retained injected object exposes current state, not a snapshot.' );
            self::assertFalse( $filter->firing );

            $first = $callbacks['xwp_self_action_first'];
            $second = $callbacks['xwp_self_action_second'];
            self::assertSame( Callback::class, $first::class );
            self::assertSame( Callback::class, $second::class );
            self::assertNotSame( $first, $second, 'Repeated attributes on one method have separate runtime identities.' );
            self::assertNotSame( $first->get_token(), $second->get_token() );
            do_action( 'xwp_self_action_first' );
            self::assertSame( 1, $first->fired );
            self::assertSame( 0, $second->fired );
            do_action( 'xwp_self_action_second' );
            foreach ( array( $first, $second ) as $index => $action ) {
                $observed = Self_Hook_Module::$observations[ $index + 2 ];
                self::assertSame( Callback::class, $observed['hook']::class );
                self::assertSame( $action, $observed['hook'] );
                self::assertSame( $action, $app->container()->get( $action->get_token() ) );
                self::assertSame( $action->get_tag(), $observed['tag'] );
                self::assertSame( 'action', $observed['method'] );
                self::assertSame( 0, $observed['fired'] );
                self::assertTrue( $observed['firing'] );
                self::assertSame( array( $action, 'invoke' ), $observed['target'] );
                self::assertSame( $action->get_priority(), has_action( $observed['tag'], $observed['target'] ) );
                self::assertSame( 1, $action->fired );
                self::assertFalse( $action->firing );
            }
        }
    }

    /** @dataProvider removal_forms_and_cache_modes */
    public function test_injected_callable_can_remove_its_registration( string $form, bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            Self_Hook_Module::$observe = static function ( Callback $hook ) use ( $app, $form ): void {
                $target = match ( $form ) {
                    'target' => $hook->target,
                    'self' => array( $hook, 'invoke' ),
                    'token' => array( $app->container()->get( $hook->get_token() ), 'invoke' ),
                };
                self::assertTrue( remove_filter( $hook->tag, $target, $hook->get_priority() ) );
            };
            self::assertSame( 'first:filtered', apply_filters( 'xwp_self_filter', 'first' ) );
            $hook = Self_Hook_Module::$observations[0]['hook'];
            self::assertFalse( has_filter( 'xwp_self_filter', $hook->target ) );
            self::assertSame( 'second', apply_filters( 'xwp_self_filter', 'second' ) );
            self::assertCount( 1, Self_Hook_Module::$observations );
            self::assertSame( 1, $hook->fired );
            self::assertFalse( $hook->firing );

            // Loaded records successful attachment; removing the WP listener does not reset it.
            self::assertTrue( $hook->is_loaded() );
            self::assertTrue( $hook->load() );
            self::assertFalse( has_filter( 'xwp_self_filter', $hook->target ) );
            Self_Hook_Module::$observe = null;
            self::assertSame( 'manual:filtered', $hook->invoke( 'manual' ) );
            self::assertSame( $hook, Self_Hook_Module::$observations[1]['hook'] );
            self::assertSame( 2, $hook->fired );
        }
    }

    /** @dataProvider cache_modes */
    public function test_live_state_after_skipped_invocation_and_exception( bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $callbacks = $this->callbacks( $app );
            $once = $callbacks['xwp_self_once'];
            self::assertSame( 'first:once', apply_filters( 'xwp_self_once', 'first' ) );
            self::assertSame( 'second', apply_filters( 'xwp_self_once', 'second' ) );
            self::assertSame( 1, $once->fired );
            self::assertFalse( $once->firing );
            self::assertCount( 1, Self_Hook_Module::$observations );

            $failed = $callbacks['xwp_self_throw'];
            try {
                apply_filters( 'xwp_self_throw', 'value' );
                self::fail( 'The fixture exception must propagate.' );
            } catch ( \RuntimeException $exception ) {
                self::assertSame( 'self.hook fixture failure', $exception->getMessage() );
            }
            $observed = Self_Hook_Module::$observations[1];
            self::assertSame( $failed, $observed['hook'] );
            self::assertSame( $failed, $app->container()->get( $observed['hook']->get_token() ) );
            self::assertSame( 1, $observed['hook']->fired );
            self::assertFalse( $observed['hook']->firing );
            self::assertSame( 0, $observed['fired'] );
            self::assertTrue( $observed['firing'] );
            self::assertSame( 1, $failed->fired, 'An invocation that throws still increments fired in finally.' );
            self::assertFalse( $failed->firing );
        }
    }

    public static function cache_modes(): array {
        return array( 'uncached' => array( false, false ), 'hooks' => array( false, true ), 'container' => array( true, false ), 'both' => array( true, true ) );
    }

    public static function removal_forms_and_cache_modes(): array {
        $cases = array();
        foreach ( array( 'target', 'self', 'token' ) as $form ) {
            foreach ( self::cache_modes() as $mode => $flags ) {
                $cases[ $form . ' ' . $mode ] = array( $form, ...$flags );
            }
        }
        return $cases;
    }

    private function callbacks( App $app ): array {
        $handler = $app->container()->get( 'Hook-' . Self_Hook_Handler::class );
        $callbacks = array();
        foreach ( $handler->get_callbacks() as $token ) {
            $callback = $app->container()->get( $token );
            $callbacks[ $callback->get_tag() ] = $callback;
        }
        return $callbacks;
    }

    private function apps( bool $compile, bool $hooks ): \Generator {
        $id = uniqid( 'self_hook_' );
        $config = array(
            'app_id' => $id, 'app_class' => 'Compiled' . $id,
            'app_module' => Self_Hook_Module::class,
            'app_file' => false, 'app_type' => 'plugin',
            'app_debug' => false, 'app_preload' => false,
            'app_version' => '1.0.0', 'cache_app' => $compile,
            'cache_defs' => false, 'cache_hooks' => $hooks,
            'cache_dir' => $this->cache_dir, 'extendable' => false, 'public' => false,
            'use_attributes' => true, 'use_autowiring' => true, 'use_proxies' => false,
        );
        foreach ( array( 'cold', 'warm' ) as $pass ) {
            $this->reset_hooks();
            $app = App_Builder::configure( $config )->build()->get( App::class );
            self::assertSame( $compile, $app->container() instanceof Compiled_Container );
            self::assertSame( $hooks, file_exists( $this->cache_dir . '/hook-definition.php' ) );
            $app->run();
            do_action( 'xwp_self_module' );
            yield $pass => $app;
        }
    }

    private function reset_hooks(): void {
        foreach ( array( 'xwp_self_module', 'xwp_self_filter', 'xwp_self_action_first', 'xwp_self_action_second', 'xwp_self_once', 'xwp_self_throw' ) as $hook ) {
            remove_all_filters( $hook );
        }
        Self_Hook_Module::$observations = array();
        Self_Hook_Module::$observe = null;
    }
}
