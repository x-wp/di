<?php
/**
 * Route metadata decorators across discovery, caches, and callback round trips.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Compiled_Container;
use XWP\DI\Hook\Dynamic_Callback;
use XWP\DI\Decorators\Filter;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Hook\Callback;
use XWP\DI\Hook\Factory;
use XWP\DI\Invoker;
use XWP\DIT\Lifecycle\Callback_Provided_Handler;
use XWP\DIT\Lifecycle\Callback_Wiring_Handler;
use XWP\DIT\Lifecycle\Callback_Wiring_Module;

final class Callback_Wiring_Test extends TestCase {
    private string $cache_dir;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-callback-wiring-' . uniqid();
        mkdir( $this->cache_dir );
    }

    public function tear_down(): void {
        $this->reset_hooks();
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );
        parent::tear_down();
    }

    /** @dataProvider modes */
    public function test_all_tokens_route_to_runtime_and_custom_metadata_survives( bool $compile, bool $hooks, bool $preload ): void {
        foreach ( $this->apps( $compile, $hooks, $preload ) as $app ) {
            $container = $app->container();
            self::assertInstanceOf( \XWP\DI\Hook\Module::class, $container->get( 'Hook-' . Callback_Wiring_Module::class ) );
            $factory = $container->get( Factory::class );
            $handler = $factory->get_handler( Callback_Wiring_Handler::class );
            self::assertSame( \XWP\DI\Hook\Handler::class, $handler::class );
            $callbacks = $factory->get_callbacks( $handler );
            self::assertCount( 4, $callbacks );
            $types = array( 'standard' => Callback::class, 'action' => Callback::class, 'dynamic' => Dynamic_Callback::class, 'custom' => Callback::class );
            foreach ( $callbacks as $callback ) {
                self::assertSame( $types[ $callback->get_method() ], $callback::class );
                self::assertSame( $callback, $factory->get_hook( $callback->get_token() ) );
                self::assertSame( $callback, $container->get( $callback->get_token() ) );
                self::assertTrue( $factory->has_hook( $callback ) );
                self::assertSame( Callback_Wiring_Handler::class, $factory->get_target( $callback ) );
                self::assertTrue( $callback->is_loaded() );
                self::assertSame( 'xwp_wiring_module', $callback->get_init_hook() );
            }
            $target = $handler->get_target();
            self::assertSame( 17, has_filter( 'xwp_wiring_standard', array( $target, 'standard' ) ) );
            self::assertSame( 'a:standard', apply_filters( 'xwp_wiring_standard', 'a', 'ignored' ) );
            self::assertSame( 'a:dynamic', apply_filters( 'xwp_wiring_dynamic', 'a' ) );
            self::assertSame( 'a:custom:metadata', apply_filters( 'xwp_wiring_custom', 'a' ) );
            do_action( 'xwp_wiring_action' );
            self::assertSame( array( 'standard', 'dynamic', 'custom', 'action' ), $target->events );
            self::assertSame( 1, $callbacks[1]->fired );

            // Resaving resolved runtimes must neither replace their callable identity nor reset state.
            self::assertSame( $handler, xwp_load_handler_cbs( $handler, $callbacks ) );
            self::assertSame( $callbacks, $factory->get_callbacks( $handler ) );
            self::assertSame( 1, $callbacks[1]->fired );
            self::assertSame( 10, has_action( 'xwp_wiring_action', array( $callbacks[1], 'invoke' ) ) );
            self::assertTrue( remove_action( 'xwp_wiring_action', array( $container->get( $callbacks[1]->get_token() ), 'invoke' ), 10 ) );
            self::assertTrue( remove_filter( 'xwp_wiring_standard', array( $target, 'standard' ), 17 ) );

            $registry = $container->get( Invoker::class )->get_actions( Callback_Wiring_Handler::class );
            self::assertSame( 'xwp_wiring_module', $registry['standard:xwp_wiring_standard'] );
            self::assertSame( 'xwp_wiring_module', $registry['action:xwp_wiring_action'] );

            // Discovery supplies cacheable definitions without replacing live runtimes.
            $rediscovered = $factory->resolve_callbacks( $handler );
            self::assertSame( CallbackDefinition::class, $rediscovered[0]::class );
            self::assertSame( $callbacks[0]->get_token(), $rediscovered[0]->get_token() );
            self::assertSame( $callbacks, $factory->get_callbacks( $handler ) );
        }
    }

    /** @dataProvider modes */
    public function test_load_callbacks_accepts_a_new_runtime_without_decorator_mutators( bool $compile, bool $hooks, bool $preload ): void {
        foreach ( $this->apps( $compile, $hooks, $preload ) as $app ) {
            $container = $app->container();
            self::assertInstanceOf( \XWP\DI\Hook\Module::class, $container->get( 'Hook-' . Callback_Wiring_Module::class ) );
            $factory = $container->get( Factory::class );
            $handler = $factory->get_handler( Callback_Wiring_Handler::class );
            $definition = CallbackDefinition::from_data( array(
                'type' => Filter::class, 'args' => array( 'tag' => 'xwp_wiring_added', 'invoke' => Filter::INV_PROXIED, 'args' => 1 ),
                'params' => array( 'classname' => Callback_Wiring_Handler::class, 'method' => 'standard' ),
            ) );
            $runtime = new Callback( $definition, $container );
            $factory->load_callbacks( $handler, array( $runtime ) );
            self::assertSame( $runtime, $factory->get_hook( $runtime->get_token() ) );
            self::assertSame( array( $runtime ), $factory->get_callbacks( $handler ) );
            self::assertTrue( $runtime->load() );
            self::assertSame( 'a:standard', apply_filters( 'xwp_wiring_added', 'a' ) );
            self::assertSame( 1, $runtime->fired );
            $factory->load_callbacks( $handler, array( $runtime ) );
            self::assertSame( $runtime, $factory->get_hook( $runtime->get_token() ) );
            self::assertSame( 1, $runtime->fired );
        }
    }

    public static function modes(): array {
        return array(
            'runtime' => array( false, false, false ),
            'preloaded' => array( false, false, true ),
            'hooks' => array( false, true, false ),
            'compiled' => array( true, false, false ),
            'both' => array( true, true, false ),
        );
    }

    public function test_get_callbacks_for_an_undiscovered_handler_returns_the_registered_runtime(): void {
        foreach ( $this->apps( false, false, false ) as $app ) {
            $factory = $app->container()->get( Factory::class );
            $handler = $factory->create_handler( new Callback_Provided_Handler() );
            self::assertNull( $handler->get_callbacks() );
            self::assertSame( \XWP\DI\Hook\Handler::class, $handler::class );
            $callbacks = $factory->get_callbacks( $handler );
            self::assertCount( 1, $callbacks );
            self::assertInstanceOf( Callback::class, $callbacks[0] );
            self::assertSame( $callbacks[0], $app->container()->get( $callbacks[0]->get_token() ) );
            self::assertTrue( $callbacks[0]->load() );
            self::assertSame( 'a:provided', apply_filters( 'xwp_wiring_provided', 'a' ) );
            self::assertSame( 1, $callbacks[0]->fired );
            self::assertSame( $callbacks, $factory->get_callbacks( $handler ) );
        }
    }

    public function test_containerless_factory_discovers_callback_definitions(): void {
        $factory = new Factory();
        $handler = $factory->resolve_handler( Callback_Wiring_Handler::class );
        $callbacks = $factory->resolve_callbacks( $handler );
        self::assertCount( 4, $callbacks );
        foreach ( $callbacks as $definition ) {
            self::assertInstanceOf( CallbackDefinition::class, $definition );
            $copy = CallbackDefinition::from_data( $definition->get_data() );
            self::assertSame( $definition->get_data(), $copy->get_data() );
            self::assertSame( $definition->get_token(), $copy->get_token() );
        }
    }

    private function apps( bool $compile, bool $hooks, bool $preload ): \Generator {
        $id = uniqid( 'callback_wiring_' );
        $config = array(
            'app_id' => $id, 'app_class' => 'Compiled' . $id,
            'app_module' => Callback_Wiring_Module::class,
            'app_file' => false, 'app_type' => 'plugin', 'app_debug' => false,
            'app_preload' => $preload, 'app_version' => '1.0.0',
            'cache_app' => $compile, 'cache_defs' => false, 'cache_hooks' => $hooks,
            'cache_dir' => $this->cache_dir, 'extendable' => false, 'public' => false,
            'use_attributes' => true, 'use_autowiring' => true, 'use_proxies' => false,
        );
        foreach ( array( 'cold', 'warm' ) as $pass ) {
            $this->reset_hooks();
            $app = App_Builder::configure( $config )->build()->get( App::class );
            self::assertSame( $compile, $app->container() instanceof Compiled_Container );
            self::assertSame( $hooks, file_exists( $this->cache_dir . '/hook-definition.php' ) );
            $app->run();
            do_action( 'xwp_wiring_module' );
            yield $pass => $app;
        }
    }

    private function reset_hooks(): void {
        foreach ( array( 'xwp_wiring_module', 'xwp_wiring_standard', 'xwp_wiring_action', 'xwp_wiring_dynamic', 'xwp_wiring_custom', 'xwp_wiring_added', 'xwp_wiring_provided' ) as $tag ) {
            remove_all_filters( $tag );
        }
    }
}
