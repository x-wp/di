<?php
/**
 * Handler context gates precede runtime discovery and registration.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Decorators\Handler;
use XWP\DI\Invoker;
use XWP\DIT\Lifecycle\Auto_Context_Handler;
use XWP\DIT\Lifecycle\Context_Module;
use XWP\DIT\Lifecycle\Early_Context_Handler;
use XWP\DIT\Lifecycle\Jit_Context_Handler;
use XWP\DIT\Lifecycle\Lazy_Context_Handler;
use XWP\DIT\Lifecycle\Now_Context_Handler;
use XWP\DIT\Lifecycle\Reentrant_Handler;
use XWP\DIT\Lifecycle\User_Context_Handler;

final class Handler_Context_Test extends TestCase {
    private string $cache_dir;
    private int $original_context;
    private \ReflectionProperty $context;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-context-' . uniqid();
        mkdir( $this->cache_dir );
        $this->original_context = \XWP_Context::get();
        // Simulate separate request contexts without changing the production context API.
        $this->context = new \ReflectionProperty( \XWP_Context::class, 'current' );
        $this->context->setAccessible( true );
        $this->context->setValue( null, Handler::CTX_FRONTEND );
        Context_Module::$events = array();
        Context_Module::$discoveries = 0;
    }

    public function tear_down(): void {
        $this->remove_context_hooks();
        $this->context->setValue( null, $this->original_context );
        Context_Module::$events = array();
        Context_Module::$discoveries = 0;
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );
        parent::tear_down();
    }

    /** @dataProvider cache_modes */
    public function test_excluded_handlers_are_skipped_but_remain_available_in_other_contexts( bool $compile, bool $hooks ): void {
        $config = $this->config( $compile, $hooks );

        foreach ( array( Handler::CTX_FRONTEND, Handler::CTX_ADMIN ) as $context ) {
            $this->context->setValue( null, $context );
            Context_Module::$events = array();
            $app = App_Builder::configure( $config )->build()->get( App::class );
            $discoveries = Context_Module::$discoveries;
            self::assertSame( array(), Context_Module::$events );
            $app->run();
            do_action( 'xwp_context_module' );

            $invoker = $app->container()->get( Invoker::class );
            if ( Handler::CTX_FRONTEND === $context ) {
                self::assertSame( $discoveries, Context_Module::$discoveries, 'Excluded handlers must not discover or resolve runtime callbacks.' );
                foreach ( $this->handlers() as $class => $hook ) {
                    self::assertArrayHasKey( $class, $invoker->get_handlers() );
                    self::assertFalse( $invoker->get_handlers()[ $class ], 'Excluded handlers remain known but uninitialized.' );
                    self::assertFalse( has_action( $hook ) );
                    self::assertFalse( has_action( 'Hook-' . $class . '_' . Handler::INIT_LAZY . '_init' ) );
                    self::assertFalse( has_action( 'Hook-' . $class . '_' . Handler::INIT_JIT . '_init' ) );
                    $handler = $app->container()->get( 'Hook-' . $class );
                    self::assertNull( $handler->get_target() );
                    self::assertFalse( $handler->is_loaded() );
                    if ( ! $hooks ) {
                        self::assertNull( $handler->get_callbacks(), 'Uncached callback metadata must remain undiscovered.' );
                    }
                    do_action( $hook );
                }
                self::assertFalse( has_filter( 'xwp_context_value' ) );
                self::assertSame( 'original', apply_filters( 'xwp_context_value', 'original' ) );
                self::assertSame( array(), Context_Module::$events, 'Excluded handlers must not evaluate conditions or initialize.' );
            } else {
                foreach ( $this->handlers() as $class => $hook ) {
                    self::assertArrayHasKey( $class, $invoker->get_handlers() );
                    do_action( $hook );
                }
                self::assertArrayNotHasKey( Jit_Context_Handler::class, Context_Module::$events, 'Eligible JIT handlers still wait for invocation.' );
                self::assertSame( 'original' . str_repeat( ':handled', 5 ), apply_filters( 'xwp_context_value', 'original' ) );
                foreach ( $this->handlers() as $class => $hook ) {
                    self::assertSame( array( 'condition', 'construct', 'initialize', 'invoke' ), Context_Module::$events[ $class ] );
                }
            }

            $this->remove_context_hooks();
        }
    }

    public function test_supplied_instances_also_respect_the_context_gate(): void {
        $app = App_Builder::configure( $this->config( false, false ) )->build()->get( App::class );
        $app->run();
        $instance = new User_Context_Handler();
        Context_Module::$events = array();

        $invoker = $app->container()->get( Invoker::class );
        $handler = $invoker->load_handler( $instance );

        self::assertSame( $instance, $handler->get_target() );
        self::assertArrayHasKey( User_Context_Handler::class, $invoker->get_handlers() );
        self::assertTrue( $handler->is_loaded() );
        self::assertSame( $handler->get_init_hook(), $invoker->get_handlers()[ User_Context_Handler::class ] );
        self::assertSame( 0, Context_Module::$discoveries );
        self::assertFalse( has_filter( 'xwp_context_value' ) );
        self::assertSame( array(), Context_Module::$events );
    }

    /** @dataProvider cache_modes */
    public function test_repeated_registration_installs_each_lifecycle_once_after_context_becomes_eligible( bool $compile, bool $hooks ): void {
        $app = App_Builder::configure( $this->config( $compile, $hooks ) )->build()->get( App::class );
        $app->run();
        $invoker = $app->container()->get( Invoker::class );
        $invoker->register_handler( Context_Module::class );
        self::assertSame( 1, $this->listener_count( 'xwp_context_module' ), 'Repeated module registration must not add scheduling closures.' );
        do_action( 'xwp_context_module' );

        $notifications = array();
        foreach ( $this->handlers() as $class => $hook ) {
            $notifications[ $class ] = did_action( 'xwp_di_hooks_loaded_' . $class );
            $invoker->register_handler( $class );
            self::assertFalse( $invoker->get_handlers()[ $class ] );
            self::assertSame( 0, $this->listener_count( $hook ) );
        }

        $this->context->setValue( null, Handler::CTX_ADMIN );
        foreach ( $this->handlers() as $class => $hook ) {
            $handler = $invoker->register_handler( $class );
            self::assertSame( $handler, $invoker->register_handler( $class ) );
            self::assertSame( $handler, $invoker->register_handler( $class ) );
            self::assertSame( Now_Context_Handler::class === $class ? 0 : 1, $this->listener_count( $hook ) );
            if ( $handler->is_lazy() ) {
                self::assertSame( 1, $this->listener_count( $handler->get_lazy_tag() ) );
            }
            do_action( $hook );
            $invoker->register_handler( $class );
            self::assertSame( $notifications[ $class ] + 1, did_action( 'xwp_di_hooks_loaded_' . $class ), 'Lifecycle attachment must not repeat after initialization.' );
        }

        self::assertSame( 'value' . str_repeat( ':handled', 5 ), apply_filters( 'xwp_context_value', 'value' ) );
        foreach ( $this->handlers() as $class => $hook ) {
            self::assertSame( array( 'condition', 'construct', 'initialize', 'invoke' ), Context_Module::$events[ $class ] );
        }

        $notification = 'xwp_di_hooks_loaded_' . User_Context_Handler::class;
        $before = did_action( $notification );
        $instance = new User_Context_Handler();
        $handler = $invoker->load_handler( $instance );
        self::assertSame( $handler, $invoker->load_handler( $instance ) );
        self::assertSame( $handler, $invoker->register_handler( User_Context_Handler::class ) );
        self::assertSame( $before + 1, did_action( $notification ), 'Supplied instances must only attach once.' );
    }

    public function test_registration_is_guarded_before_initialization_can_reenter(): void {
        $app = App_Builder::configure( $this->config( false, false ) )->build()->get( App::class );
        $app->run();
        $notification = 'xwp_di_hooks_loaded_' . Reentrant_Handler::class;
        $before = did_action( $notification );

        $handler = $app->container()->get( Invoker::class )->register_handler( Reentrant_Handler::class );

        self::assertTrue( $handler->is_loaded() );
        self::assertSame( $before + 1, did_action( $notification ), 'Registration from on_initialize must not process the strategy twice.' );
        self::assertSame( 'value:reentrant', apply_filters( 'xwp_context_reentrant', 'value' ) );
    }

    public static function cache_modes(): array {
        return array(
            'uncached' => array( false, false ),
            'hooks' => array( false, true ),
            'container' => array( true, false ),
            'both' => array( true, true ),
        );
    }

    private function handlers(): array {
        return array(
            Auto_Context_Handler::class => 'xwp_context_auto',
            Early_Context_Handler::class => 'xwp_context_early',
            Now_Context_Handler::class => 'xwp_context_now',
            Lazy_Context_Handler::class => 'xwp_context_lazy',
            Jit_Context_Handler::class => 'xwp_context_jit',
        );
    }

    private function remove_context_hooks(): void {
        remove_all_actions( 'xwp_context_module' );
        remove_all_filters( 'xwp_context_value' );
        remove_all_filters( 'xwp_context_reentrant' );
        foreach ( $this->handlers() as $class => $hook ) {
            remove_all_actions( $hook );
            remove_all_actions( 'Hook-' . $class . '_' . Handler::INIT_LAZY . '_init' );
            remove_all_actions( 'Hook-' . $class . '_' . Handler::INIT_JIT . '_init' );
        }
    }

    private function listener_count( string $hook ): int {
        return array_sum( array_map( 'count', $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array() ) );
    }

    private function config( bool $compile, bool $hooks ): array {
        $id = uniqid( 'context_' );

        return array(
            'app_id' => $id,
            'app_class' => 'Compiled' . $id,
            'app_module' => Context_Module::class,
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
