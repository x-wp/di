<?php
/**
 * Default handler scheduling against real WordPress action priorities.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Compiled_Container;
use XWP\DI\Decorators\Handler;
use XWP\DI\Invoker;
use XWP\DIT\Lifecycle\Default_Auto_Handler;
use XWP\DIT\Lifecycle\Default_Early_Handler;
use XWP\DIT\Lifecycle\Default_Jit_Handler;
use XWP\DIT\Lifecycle\Default_Lazy_Handler;
use XWP\DIT\Lifecycle\Default_Schedule_Module;

final class Default_Schedule_Test extends TestCase {
    private string $cache_dir;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-default-schedule-' . uniqid();
        mkdir( $this->cache_dir );
        Default_Schedule_Module::$events = array();
    }

    public function tear_down(): void {
        $this->remove_scheduling_hooks();
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );
        parent::tear_down();
    }

    /** @dataProvider strategies_and_cache_modes */
    public function test_default_schedule_uses_the_active_action_at_the_next_priority( string $class, bool $compile, bool $hooks ): void {
        $config = $this->config( $compile, $hooks );

        // Reuse cached definitions with a different registration hook and priority.
        foreach ( array( 'cold' => 17, 'warm' => -3 ) as $pass => $priority ) {
            $app = App_Builder::configure( $config )->build()->get( App::class );
            $app->run();
            self::assertSame( $compile, $app->container() instanceof Compiled_Container );
            self::assertSame( $hooks, file_exists( $this->cache_dir . '/hook-definition.php' ) );
            self::assertSame( array(), Default_Schedule_Module::$events, 'Building and starting the app must not initialize these handlers.' );

            $invoker = $app->container()->get( Invoker::class );
            $hook = 'xwp_default_' . $pass;
            $before_attachment = Default_Early_Handler::class === $class ? array( 'construct', 'initialize' ) : array();
            $after_attachment = Default_Jit_Handler::class === $class ? array() : array( 'construct', 'initialize' );
            $boundaries = array();

            add_action( $hook, static function () use ( $invoker, $class, $before_attachment, &$boundaries ): void {
                $invoker->register_handler( $class );
                self::assertSame( $before_attachment, Default_Schedule_Module::$events );
                self::assertFalse( has_filter( 'xwp_default_value' ), 'Registration must not attach scheduled callbacks immediately.' );
                $boundaries[] = 'registered';
            }, $priority );

            // Installed before the lifecycle listener: this runs first at priority + 1.
            add_action( $hook, static function () use ( $before_attachment, &$boundaries ): void {
                self::assertSame( $before_attachment, Default_Schedule_Module::$events );
                self::assertFalse( has_filter( 'xwp_default_value' ), 'Callbacks must still be absent just before the scheduled priority.' );
                $boundaries[] = 'before attachment';
            }, $priority + 1 );

            add_action( 'xwp_di_hooks_loaded_' . $class, static function () use ( $hook, $priority, $after_attachment, &$boundaries ): void {
                self::assertTrue( doing_action( $hook ), 'Attachment must occur within the registering action.' );
                self::assertSame( $priority + 1, $GLOBALS['wp_filter'][ $hook ]->current_priority() );
                self::assertTrue( has_filter( 'xwp_default_value' ) );
                self::assertSame( $after_attachment, Default_Schedule_Module::$events );
                $boundaries[] = 'attached';
            } );

            add_action( $hook, static function () use ( $after_attachment, &$boundaries ): void {
                self::assertTrue( has_filter( 'xwp_default_value' ), 'Callbacks must attach during this occurrence of the action.' );
                self::assertSame( $after_attachment, Default_Schedule_Module::$events );
                $boundaries[] = 'after attachment';
            }, $priority + 2 );

            // A nested action must supply the defaults, not its outer caller.
            add_action( 'xwp_default_outer', static function () use ( $hook ): void {
                do_action( $hook );
            }, 40 );
            do_action( 'xwp_default_outer' );

            self::assertSame( array( 'registered', 'before attachment', 'attached', 'after attachment' ), $boundaries, $pass );
            self::assertSame( 'first:handled', apply_filters( 'xwp_default_value', 'first' ) );
            self::assertSame( 'second:handled', apply_filters( 'xwp_default_value', 'second' ) );
            self::assertSame( array( 'construct', 'initialize', 'invoke', 'invoke' ), Default_Schedule_Module::$events );
            $this->remove_scheduling_hooks();
        }
    }

    public static function strategies_and_cache_modes(): array {
        $cases = array();
        $strategies = array(
            'auto' => Default_Auto_Handler::class,
            'early' => Default_Early_Handler::class,
            'lazy' => Default_Lazy_Handler::class,
            'jit' => Default_Jit_Handler::class,
        );
        $modes = array(
            'uncached' => array( false, false ),
            'hooks' => array( false, true ),
            'container' => array( true, false ),
            'both' => array( true, true ),
        );
        foreach ( $strategies as $strategy => $class ) {
            foreach ( $modes as $mode => $flags ) {
                $cases[ $strategy . ' ' . $mode ] = array( $class, ...$flags );
            }
        }
        return $cases;
    }

    private function remove_scheduling_hooks(): void {
        foreach ( array( 'xwp_default_module', 'xwp_default_cold', 'xwp_default_warm', 'xwp_default_outer', 'xwp_default_value' ) as $hook ) {
            remove_all_filters( $hook );
        }
        foreach ( array( Default_Auto_Handler::class, Default_Early_Handler::class, Default_Lazy_Handler::class, Default_Jit_Handler::class ) as $class ) {
            remove_all_actions( 'xwp_di_hooks_loaded_' . $class );
        }
        remove_all_actions( 'Hook-' . Default_Lazy_Handler::class . '_' . Handler::INIT_LAZY . '_init' );
        remove_all_actions( 'Hook-' . Default_Jit_Handler::class . '_' . Handler::INIT_JIT . '_init' );
        Default_Schedule_Module::$events = array();
    }

    private function config( bool $compile, bool $hooks ): array {
        $id = uniqid( 'default_schedule_' );

        return array(
            'app_id' => $id,
            'app_class' => 'Compiled' . $id,
            'app_module' => Default_Schedule_Module::class,
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
    }
}
