<?php
/**
 * Supplied targets bind consistently to fresh and cached handler metadata.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Compiled_Container;
use XWP\DI\Hook\Factory;
use XWP\DI\Invoker;
use XWP\DIT\Lifecycle\Auto_Supplied_Handler;
use XWP\DIT\Lifecycle\Plain_Supplied_Handler;
use XWP\DIT\Lifecycle\Supplied_Instance_Module;
use XWP\DIT\Lifecycle\User_Supplied_Handler;

final class Supplied_Instance_Test extends TestCase {
    private string $cache_dir;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-supplied-instance-' . uniqid();
        mkdir( $this->cache_dir );
        $this->reset_lifecycle();
    }

    public function tear_down(): void {
        $this->reset_lifecycle();
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );
        parent::tear_down();
    }

    /** @dataProvider handlers_and_cache_modes */
    public function test_supplied_identity_is_bound_once_with_existing_or_fresh_metadata( string $class, bool $resolve_first, bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $pass => $app ) {
            $container = $app->container();
            $invoker = $container->get( Invoker::class );
            $metadata = $resolve_first ? $container->get( Factory::class )->get_handler( $class ) : null;
            if ( $metadata ) {
                self::assertNull( $metadata->get_target() );
            }
            self::assertSame( array(), Supplied_Instance_Module::$events );
            $label = $pass . '-supplied';
            $instance = new $class( $label );
            $handler = null;
            $notification = 'xwp_di_hooks_loaded_' . $class;
            $before = did_action( $notification );
            add_action( 'xwp_supplied_adopt', static function () use ( $invoker, $instance, &$handler ): void {
                $handler = $invoker->load_handler( $instance );
            } );
            do_action( 'xwp_supplied_adopt' );

            if ( $metadata ) {
                self::assertSame( $metadata, $handler );
            }
            self::assertSame( $handler, $container->get( 'Hook-' . $class ) );
            self::assertSame( $instance, $handler->get_target() );
            self::assertTrue( $handler->is_loaded() );
            self::assertSame( 'xwp_supplied_adopt', $handler->get_init_hook() );
            self::assertSame( 'xwp_supplied_adopt', $invoker->get_handlers()[ $class ] );
            self::assertSame( array( 'construct:' . $label ), Supplied_Instance_Module::$events, 'Adoption must not construct a target or run its initialization lifecycle.' );
            self::assertSame( Auto_Supplied_Handler::class !== $class, has_filter( 'xwp_supplied_value' ) );
            do_action( 'xwp_supplied_attach' );
            self::assertSame( 'value:' . $label, apply_filters( 'xwp_supplied_value', 'value' ) );
            self::assertSame( array( 'construct:' . $label, 'invoke:' . $label ), Supplied_Instance_Module::$events );
            self::assertSame( $before + 1, did_action( $notification ) );

            $replacement = new $class( 'replacement' );
            add_action( 'xwp_supplied_repeat', static function () use ( $invoker, $instance, $replacement, $handler ): void {
                self::assertSame( $handler, $invoker->load_handler( $instance ) );
                self::assertSame( $handler, $invoker->load_handler( $replacement ) );
            } );
            do_action( 'xwp_supplied_repeat' );
            self::assertSame( $instance, $handler->get_target(), 'An already bound target retains its identity.' );
            self::assertSame( 'xwp_supplied_adopt', $handler->get_init_hook(), 'Repeated adoption must preserve initialization metadata.' );
            self::assertSame( $before + 1, did_action( $notification ), 'Repeated adoption must not attach callbacks again.' );
            self::assertSame( 'value:' . $label, apply_filters( 'xwp_supplied_value', 'value' ) );
            self::assertSame( array( 'construct:' . $label, 'invoke:' . $label, 'construct:replacement', 'invoke:' . $label ), Supplied_Instance_Module::$events );
        }
    }

    public static function handlers_and_cache_modes(): array {
        $cases = array();
        foreach ( array( 'user' => User_Supplied_Handler::class, 'auto' => Auto_Supplied_Handler::class, 'plain' => Plain_Supplied_Handler::class ) as $name => $class ) {
            foreach ( Plain_Supplied_Handler::class === $class ? array( false ) : array( false, true ) as $resolve_first ) {
                foreach ( array( 'uncached' => array( false, false ), 'hooks' => array( false, true ), 'container' => array( true, false ), 'both' => array( true, true ) ) as $mode => $flags ) {
                    $cases[ $name . ( $resolve_first ? ' resolved ' : ' fresh ' ) . $mode ] = array( $class, $resolve_first, ...$flags );
                }
            }
        }
        return $cases;
    }

    private function apps( bool $compile, bool $hooks ): \Generator {
        $id = uniqid( 'supplied_instance_' );
        $config = array(
            'app_id' => $id,
            'app_class' => 'Compiled' . $id,
            'app_module' => Supplied_Instance_Module::class,
            'app_file' => false,
            'app_type' => 'plugin',
            'app_debug' => false,
            'app_preload' => false,
            'app_version' => '1.0.0',
            'cache_app' => $compile,
            'cache_defs' => false,
            'cache_hooks' => $hooks,
            'cache_dir' => $this->cache_dir,
            'extendable' => false,
            'public' => false,
            'use_attributes' => true,
            'use_autowiring' => true,
            'use_proxies' => false,
        );
        foreach ( array( 'cold', 'warm' ) as $pass ) {
            $this->reset_lifecycle();
            $app = App_Builder::configure( $config )->build()->get( App::class );
            self::assertSame( $compile, $app->container() instanceof Compiled_Container );
            self::assertSame( $hooks, file_exists( $this->cache_dir . '/hook-definition.php' ) );
            $app->run();
            yield $pass => $app;
        }
    }

    private function reset_lifecycle(): void {
        foreach ( array( 'xwp_supplied_module', 'xwp_supplied_attach', 'xwp_supplied_value', 'xwp_supplied_adopt', 'xwp_supplied_repeat' ) as $hook ) {
            remove_all_filters( $hook );
        }
        Supplied_Instance_Module::$events = array();
    }
}
