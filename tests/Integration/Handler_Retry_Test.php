<?php
/**
 * Explicit initialization retries preserve single lifecycle registration.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Decorators\Handler;
use XWP\DI\Invoker;
use XWP\DIT\Lifecycle\Early_Retry_Handler;
use XWP\DIT\Lifecycle\Jit_Retry_Handler;
use XWP\DIT\Lifecycle\Lazy_Retry_Handler;
use XWP\DIT\Lifecycle\Now_Retry_Handler;
use XWP\DIT\Lifecycle\Retry_Module;

final class Handler_Retry_Test extends TestCase {
    private string $cache_dir;
    private int $original_context;
    private \ReflectionProperty $context;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-retry-' . uniqid();
        mkdir( $this->cache_dir );
        $this->original_context = \XWP_Context::get();
        $this->context = new \ReflectionProperty( \XWP_Context::class, 'current' );
        $this->context->setAccessible( true );
        $this->reset_lifecycle();
    }

    public function tear_down(): void {
        $this->reset_lifecycle();
        $this->context->setValue( null, $this->original_context );
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );
        parent::tear_down();
    }

    /** @dataProvider eager_strategies_and_cache_modes */
    public function test_registration_retries_rejection_without_repeating_the_lifecycle( string $class, bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $invoker = $app->container()->get( Invoker::class );
            $notification = 'xwp_di_hooks_loaded_' . $class;
            $before = did_action( $notification );
            $handler = $invoker->register_handler( $class );
            $listeners = $this->listener_count( 'xwp_retry_attach' );
            self::assertSame( Early_Retry_Handler::class === $class ? 1 : 0, $listeners );
            self::assertFalse( $invoker->get_handlers()[ $class ] );

            $invoker->register_handler( $class );
            self::assertSame( array( 'condition', 'condition' ), Retry_Module::$events );
            self::assertNull( $handler->get_target() );
            self::assertFalse( has_filter( 'xwp_retry_value' ) );
            self::assertSame( $before, did_action( $notification ), 'Rejected initialization must not announce attachment.' );

            add_action( 'xwp_retry_ready', static function () { Retry_Module::$ready = true; } );
            do_action( 'xwp_retry_ready' );
            self::assertSame( $handler, $invoker->register_handler( $class ) );
            self::assertTrue( $handler->is_loaded() );
            self::assertSame( array( 'condition', 'condition', 'condition', 'construct', 'initialize' ), Retry_Module::$events );
            self::assertSame( $handler->get_init_hook(), $invoker->get_handlers()[ $class ] );
            self::assertSame( Now_Retry_Handler::class === $class, has_filter( 'xwp_retry_value' ) );

            $instance = $handler->get_target();
            Retry_Module::$ready = false;
            $invoker->register_handler( $class );
            do_action( 'xwp_retry_attach' );
            $invoker->register_handler( $class );
            do_action( 'xwp_retry_attach' );
            self::assertSame( $listeners, $this->listener_count( 'xwp_retry_attach' ) );
            self::assertSame( $before + 1, did_action( $notification ) );
            self::assertSame( $instance, $handler->get_target() );
            self::assertSame( 'value:handled', apply_filters( 'xwp_retry_value', 'value' ) );
            self::assertSame( array( 'condition', 'condition', 'condition', 'construct', 'initialize', 'invoke' ), Retry_Module::$events );
        }
    }

    /** @dataProvider cache_modes */
    public function test_early_retry_finishes_attachment_after_its_listener_already_ran( bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $invoker = $app->container()->get( Invoker::class );
            $notification = 'xwp_di_hooks_loaded_' . Early_Retry_Handler::class;
            $before = did_action( $notification );
            $handler = $invoker->register_handler( Early_Retry_Handler::class );
            do_action( 'xwp_retry_attach' );
            do_action( 'xwp_retry_attach' );
            self::assertSame( array( 'condition' ), Retry_Module::$events );
            self::assertFalse( has_filter( 'xwp_retry_value' ) );
            self::assertSame( $before, did_action( $notification ) );

            $invoker->register_handler( Early_Retry_Handler::class );
            Retry_Module::$ready = true;
            $invoker->register_handler( Early_Retry_Handler::class );
            self::assertTrue( $handler->is_loaded() );
            self::assertSame( 'value:handled', apply_filters( 'xwp_retry_value', 'value' ) );
            $invoker->register_handler( Early_Retry_Handler::class );
            do_action( 'xwp_retry_attach' );
            self::assertSame( 1, $this->listener_count( 'xwp_retry_attach' ) );
            self::assertSame( $before + 1, did_action( $notification ) );
            self::assertSame( array( 'condition', 'condition', 'condition', 'construct', 'initialize', 'invoke' ), Retry_Module::$events );
        }
    }

    /** @dataProvider eager_strategies_and_cache_modes */
    public function test_context_exclusion_defers_an_explicit_retry( string $class, bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $invoker = $app->container()->get( Invoker::class );
            $handler = $invoker->register_handler( $class );
            $this->context->setValue( null, Handler::CTX_ADMIN );
            Retry_Module::$ready = true;
            $invoker->register_handler( $class );
            self::assertSame( array( 'condition' ), Retry_Module::$events );
            self::assertFalse( $invoker->get_handlers()[ $class ] );
            self::assertFalse( $handler->is_loaded() );
            self::assertFalse( has_filter( 'xwp_retry_value' ) );

            $this->context->setValue( null, Handler::CTX_FRONTEND );
            $invoker->register_handler( $class );
            do_action( 'xwp_retry_attach' );
            self::assertSame( 'value:handled', apply_filters( 'xwp_retry_value', 'value' ) );
            self::assertSame( array( 'condition', 'condition', 'construct', 'initialize', 'invoke' ), Retry_Module::$events );
        }
    }

    /** @dataProvider eager_strategies_and_cache_modes */
    public function test_retry_cannot_reenter_through_conditions_or_initialization( string $class, bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $invoker = $app->container()->get( Invoker::class );
            $notification = 'xwp_di_hooks_loaded_' . $class;
            $before = did_action( $notification );
            $reenter = static function ( string $handler ) use ( $invoker ): void {
                // Bound recursion so a broken guard produces an assertion failure.
                if ( count( Retry_Module::$events ) < 10 ) {
                    $invoker->register_handler( $handler );
                }
            };
            add_action( 'xwp_retry_condition', $reenter );
            add_action( 'xwp_retry_initialize', $reenter );
            $invoker->register_handler( $class );
            Retry_Module::$ready = true;
            $invoker->register_handler( $class );
            do_action( 'xwp_retry_attach' );
            self::assertSame( array( 'condition', 'condition', 'construct', 'initialize' ), Retry_Module::$events );
            self::assertSame( $before + 1, did_action( $notification ) );
            self::assertSame( 'value:handled', apply_filters( 'xwp_retry_value', 'value' ) );
        }
    }

    /** @dataProvider eager_strategies_and_cache_modes */
    public function test_exceptions_do_not_become_retryable_rejections( string $class, bool $compile, bool $hooks ): void {
        foreach ( array( 'condition', 'construct', 'initialize' ) as $stage ) {
            foreach ( $this->apps( $compile, $hooks ) as $app ) {
                $invoker = $app->container()->get( Invoker::class );
                $invoker->register_handler( $class );
                Retry_Module::$ready = true;
                Retry_Module::$throw_at = $stage;
                try {
                    $invoker->register_handler( $class );
                    self::fail( 'Expected initialization to throw.' );
                } catch ( \Throwable $error ) {
                    self::assertStringContainsString( 'Initialization failed at ' . $stage, $error->getMessage() );
                }
                $events = Retry_Module::$events;
                Retry_Module::$throw_at = null;
                $invoker->register_handler( $class );
                self::assertSame( $events, Retry_Module::$events, 'An exception must not enable another initialization attempt.' );
                self::assertFalse( has_filter( 'xwp_retry_value' ) );
            }
        }
    }

    /** @dataProvider cache_modes */
    public function test_lazy_and_jit_retries_still_follow_their_listeners( bool $compile, bool $hooks ): void {
        foreach ( array( Lazy_Retry_Handler::class, Jit_Retry_Handler::class ) as $class ) {
            foreach ( $this->apps( $compile, $hooks ) as $app ) {
                $invoker = $app->container()->get( Invoker::class );
                $handler = $invoker->register_handler( $class );
                do_action( 'xwp_retry_attach' );
                self::assertSame( 'rejected', apply_filters( 'xwp_retry_value', 'rejected' ) );
                self::assertSame( array( 'condition' ), Retry_Module::$events );
                $invoker->register_handler( $class );
                self::assertSame( array( 'condition' ), Retry_Module::$events, 'Registration must not initialize a lazy handler.' );
                self::assertSame( 1, $this->listener_count( $handler->get_lazy_tag() ) );
                Retry_Module::$ready = true;
                if ( Lazy_Retry_Handler::class === $class ) {
                    do_action( 'xwp_retry_attach' );
                }
                self::assertSame( 'accepted:handled', apply_filters( 'xwp_retry_value', 'accepted' ) );
                self::assertSame( array( 'condition', 'condition', 'construct', 'initialize', 'invoke' ), Retry_Module::$events );
            }
        }
    }

    public static function cache_modes(): array {
        return array(
            'uncached' => array( false, false ),
            'hooks' => array( false, true ),
            'container' => array( true, false ),
            'both' => array( true, true ),
        );
    }

    public static function eager_strategies_and_cache_modes(): array {
        $cases = array();
        foreach ( array( 'early' => Early_Retry_Handler::class, 'now' => Now_Retry_Handler::class ) as $strategy => $class ) {
            foreach ( self::cache_modes() as $mode => $flags ) {
                $cases[ $strategy . ' ' . $mode ] = array( $class, ...$flags );
            }
        }
        return $cases;
    }

    private function apps( bool $compile, bool $hooks ): \Generator {
        $id = uniqid( 'retry_' );
        $config = array(
            'app_id' => $id,
            'app_class' => 'Compiled' . $id,
            'app_module' => Retry_Module::class,
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
            $app->run();
            yield $pass => $app;
        }
    }

    private function reset_lifecycle(): void {
        foreach ( array( 'xwp_retry_module', 'xwp_retry_attach', 'xwp_retry_value', 'xwp_retry_ready', 'xwp_retry_condition', 'xwp_retry_initialize' ) as $hook ) {
            remove_all_filters( $hook );
        }
        remove_all_actions( 'Hook-' . Lazy_Retry_Handler::class . '_' . Handler::INIT_LAZY . '_init' );
        remove_all_actions( 'Hook-' . Jit_Retry_Handler::class . '_' . Handler::INIT_JIT . '_init' );
        Retry_Module::$events = array();
        Retry_Module::$ready = false;
        Retry_Module::$throw_at = null;
        $this->context->setValue( null, Handler::CTX_FRONTEND );
    }

    private function listener_count( string $hook ): int {
        return array_sum( array_map( 'count', $GLOBALS['wp_filter'][ $hook ]->callbacks ?? array() ) );
    }
}
