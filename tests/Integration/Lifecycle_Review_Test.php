<?php
/**
 * Regression coverage for lifecycle review findings.
 *
 * @package XWP\DI\Tests
 */
namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Container;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;
use XWP\DI\Hook\Factory;
use XWP\DI\Invoker;

final class Lifecycle_Review_Test extends TestCase {
    private string $cache_dir;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-lifecycle-review-' . uniqid();
        mkdir( $this->cache_dir );
        Review_Self_Adopting_Handler::$events = array();
    }

    public function tear_down(): void {
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );
        parent::tear_down();
    }

    /** @dataProvider cache_modes */
    public function test_declared_user_handler_can_be_registered_at_the_module_hook( bool $compile, bool $hooks ): void {
        $app = $this->app( Review_User_Module::class, $compile, $hooks );
        $app->run();
        do_action( 'xwp_review_module' );
        $handler = $app->container()->get( 'Hook-' . Review_User_Handler::class );
        self::assertFalse( $handler->is_loaded() );
        self::assertFalse( has_filter( 'xwp_review_value' ) );
        $instance = new Review_User_Handler();
        $app->container()->get( Invoker::class )->load_handler( $instance );
        self::assertSame( $instance, $handler->get_target() );
        self::assertTrue( $handler->is_loaded() );
        self::assertIsString( $handler->get_init_hook() );
        self::assertSame( $handler->get_init_hook(), $app->container()->get( Invoker::class )->get_handlers()[ Review_User_Handler::class ] );
        self::assertSame( 'value:handled', apply_filters( 'xwp_review_value', 'value' ) );
    }

    /** @dataProvider cache_modes */
    public function test_constructor_adoption_keeps_the_pending_initialization_callback( bool $compile, bool $hooks ): void {
        $app = $this->app( Review_Adoption_Module::class, $compile, $hooks );
        $app->run();
        do_action( 'xwp_review_module' );
        do_action( 'xwp_review_adopt' );
        self::assertSame( 'value:handled', apply_filters( 'xwp_review_value', 'value' ) );
        self::assertSame( array( 'construct', 'initialize', 'invoke' ), Review_Self_Adopting_Handler::$events );
    }

    public function test_repeated_registration_attaches_later_callbacks_once(): void {
        $app = $this->app();
        $app->run();
        $invoker = $app->container()->get( Invoker::class );
        $handler = $invoker->load_handler( new Review_User_Handler() );
        self::assertSame( 'value:handled', apply_filters( 'xwp_review_value', 'value' ) );
        $callback = $app->container()->get( Factory::class )->make( array(
            'type' => Filter::class, 'args' => array( 'tag' => 'xwp_review_extra' ),
            'params' => array( 'classname' => $handler->get_classname(), 'method' => 'value' ),
        ) );
        $app->container()->get( Factory::class )->load_callbacks( $handler, array( $callback ) );
        $invoker->register_handler( Review_User_Handler::class );
        $invoker->register_handler( Review_User_Handler::class );
        self::assertSame( 'value:handled', apply_filters( 'xwp_review_extra', 'value' ) );
        self::assertSame( 'value:handled', apply_filters( 'xwp_review_value', 'value' ) );
    }

    public function test_attachment_notification_does_not_recurse_on_rejected_callbacks(): void {
        $app = $this->app();
        $app->run();
        $invoker = $app->container()->get( Invoker::class );
        $notifications = 0;
        add_action( 'xwp_di_hooks_loaded_' . Review_Rejected_Callback_Handler::class, static function () use ( $invoker, &$notifications ): void {
            if ( ++$notifications < 3 ) {
                $invoker->register_handler( Review_Rejected_Callback_Handler::class );
            }
        } );
        $invoker->register_handler( Review_Rejected_Callback_Handler::class );
        self::assertSame( 1, $notifications );
        self::assertFalse( has_filter( 'xwp_review_rejected_callback' ) );
    }

    public function test_lazy_initialization_is_scoped_to_the_owning_application(): void {
        $one = $this->app();
        $two = $this->app();
        $one->run();
        $two->run();
        $first = $one->container()->get( Invoker::class )->register_handler( Review_Jit_Handler::class );
        $second = $two->container()->get( Invoker::class )->register_handler( Review_Jit_Handler::class );
        $callbacks = $one->container()->get( Factory::class )->get_callbacks( $first );
        $callback = reset( $callbacks );
        self::assertSame( 'value:handled', $callback->invoke( 'value' ) );
        self::assertTrue( $first->is_loaded() );
        self::assertFalse( $second->is_loaded(), 'Another application must not receive this initialization request.' );
    }

    public function test_auto_module_waits_until_its_hook_to_select_context(): void {
        $context = new \ReflectionProperty( \XWP_Context::class, 'current' );
        $context->setAccessible( true );
        $original = \XWP_Context::get();
        try {
            $context->setValue( null, Handler::CTX_FRONTEND );
            $app = $this->app( Review_REST_Module::class );
            $app->run();
            $context->setValue( null, Handler::CTX_REST );
            do_action( 'xwp_review_module' );
            self::assertTrue( $app->container()->get( 'Hook-' . Review_REST_Module::class )->is_loaded() );
        } finally {
            $context->setValue( null, $original );
        }
    }

    public function test_supplied_handler_priority_defaults_safely_outside_an_action(): void {
        $app = $this->app();
        $handler = $app->container()->get( Factory::class )->create_handler( new Review_User_Handler() );
        self::assertSame( 10, $handler->get_priority() );
    }

    /** @dataProvider module_strategies */
    public function test_explicit_module_strategies_activate_descendants_at_the_documented_boundary( string $module, bool $compile, bool $hooks ): void {
        $app = $this->app( $module, $compile, $hooks );
        $app->run();
        $runtime = $app->container()->get( 'Hook-' . $module );
        $child = $app->container()->get( Factory::class )->get_handler( Review_Module_Child::class );
        $eager = in_array( $runtime->get_strategy(), array( Handler::INIT_EARLY, Handler::INIT_NOW ), true );
        self::assertSame( $eager, $runtime->is_loaded() );
        self::assertSame( $eager, $child->is_loaded() );
        do_action( 'xwp_review_strategy_module' );
        self::assertSame( Handler::INIT_JIT !== $runtime->get_strategy(), $runtime->is_loaded() );
        self::assertSame( Handler::INIT_JIT !== $runtime->get_strategy(), $child->is_loaded() );
        self::assertSame( 'value:module', apply_filters( 'xwp_review_module_value', 'value' ) );
        self::assertTrue( $runtime->is_loaded() );
        self::assertTrue( $child->is_loaded() );
    }

    public function test_lazy_composition_without_own_callbacks_has_no_initialization_trigger(): void {
        $app = $this->app( Review_Lazy_Composition_Module::class );
        $app->run();
        do_action( 'xwp_review_strategy_module' );
        self::assertFalse( $app->container()->get( 'Hook-' . Review_Lazy_Composition_Module::class )->is_loaded() );
        self::assertArrayNotHasKey( Review_Module_Child::class, $app->container()->get( Invoker::class )->get_handlers() );
    }

    public function test_supplied_rest_controller_keeps_its_external_initialization_lifecycle(): void {
        $context = new \ReflectionProperty( \XWP_Context::class, 'current' );
        $context->setAccessible( true );
        $original = \XWP_Context::get();
        try {
            $context->setValue( null, Handler::CTX_REST );
            $app = $this->app();
            $app->run();
            rest_get_server();
            $controller = ( new Review_REST_Controller() )->with_namespace( 'xwp-review/v1' )->with_basename( 'items' );
            $controller->on_initialize();
            $handler = $app->container()->get( Invoker::class )->load_handler( $controller );
            $app->container()->get( Invoker::class )->load_handler( $controller );
            do_action( 'rest_api_init' );
            self::assertSame( 1, $controller->initializations );
            self::assertSame( $controller, $handler->get_target() );
            self::assertSame( 1, $controller->registrations );
        } finally {
            $context->setValue( null, $original );
        }
    }

    public static function module_strategies(): array {
        $cases = array();
        foreach ( array( Review_Lazy_Module::class, Review_Jit_Module::class, Review_Early_Module::class, Review_Now_Module::class ) as $module ) {
            foreach ( self::cache_modes() as $mode => $flags ) {
                $cases[ $module . ' ' . $mode ] = array( $module, ...$flags );
            }
        }
        return $cases;
    }

    public static function cache_modes(): array {
        return array( 'uncached' => array( false, false ), 'hooks' => array( false, true ), 'container' => array( true, false ), 'both' => array( true, true ) );
    }

    private function app( string $module = Review_Empty_Module::class, bool $compile = false, bool $hooks = false ): App {
        $id = uniqid( 'lifecycle_review_' );
        return App_Builder::configure( array(
            'app_id' => $id, 'app_class' => 'Compiled' . $id, 'app_module' => $module,
            'app_file' => false, 'app_type' => 'plugin', 'app_debug' => false, 'app_preload' => false,
            'app_version' => '1.0.0', 'cache_app' => $compile, 'cache_defs' => false, 'cache_hooks' => $hooks,
            'cache_dir' => $this->cache_dir, 'extendable' => false, 'public' => false,
            'use_attributes' => true, 'use_autowiring' => true, 'use_proxies' => false,
        ) )->build()->get( App::class );
    }
}

#[Module( hook: 'xwp_review_module' )]
final class Review_Empty_Module {}

#[Module( hook: 'xwp_review_module', handlers: array( Review_User_Handler::class ) )]
final class Review_User_Module {}

#[Module( hook: 'xwp_review_module', handlers: array( Review_Self_Adopting_Handler::class ) )]
final class Review_Adoption_Module {}

#[Module( hook: 'xwp_review_module', context: Handler::CTX_REST )]
final class Review_REST_Module {}

#[Handler( strategy: Handler::INIT_USER )]
final class Review_User_Handler {
    #[Filter( 'xwp_review_value' )]
    public function value( string $value ): string { return $value . ':handled'; }
}

#[Handler( tag: 'xwp_review_adopt' )]
final class Review_Self_Adopting_Handler {
    public static array $events = array();
    public function __construct( Container $container ) {
        self::$events[] = 'construct';
        $container->get( Invoker::class )->load_handler( $this );
    }
    public function on_initialize(): void { self::$events[] = 'initialize'; }
    #[Filter( 'xwp_review_value' )]
    public function value( string $value ): string {
        self::$events[] = 'invoke';
        return $value . ':handled';
    }
}

#[Handler( tag: 'xwp_review_attach_jit', strategy: Handler::INIT_JIT )]
final class Review_Jit_Handler {
    #[Filter( 'xwp_review_jit_value' )]
    public function value( string $value ): string { return $value . ':handled'; }
}

trait Review_Module_Callback {
    #[Filter( 'xwp_review_module_value' )]
    public function value( string $value ): string { return $value . ':module'; }
}

#[Module( hook: 'xwp_review_strategy_module', handlers: array( Review_Module_Child::class ), strategy: Handler::INIT_LAZY )]
final class Review_Lazy_Module { use Review_Module_Callback; }

#[Module( hook: 'xwp_review_strategy_module', handlers: array( Review_Module_Child::class ), strategy: Handler::INIT_JIT )]
final class Review_Jit_Module { use Review_Module_Callback; }

#[Module( hook: 'xwp_review_strategy_module', handlers: array( Review_Module_Child::class ), strategy: Handler::INIT_EARLY )]
final class Review_Early_Module { use Review_Module_Callback; }

#[Module( hook: 'xwp_review_strategy_module', handlers: array( Review_Module_Child::class ), strategy: Handler::INIT_NOW )]
final class Review_Now_Module { use Review_Module_Callback; }

#[Module( hook: 'xwp_review_strategy_module', handlers: array( Review_Module_Child::class ), strategy: Handler::INIT_LAZY )]
final class Review_Lazy_Composition_Module {}

#[Handler( strategy: Handler::INIT_NOW )]
final class Review_Module_Child {}

#[\XWP\DI\Decorators\REST_Handler( 'xwp-review/v1', 'items' )]
final class Review_REST_Controller extends \XWP_REST_Controller {
    public int $initializations = 0;
    public int $registrations = 0;
    public function on_initialize(): void {
        ++$this->initializations;
        parent::on_initialize();
    }
    public function register_routes(): void { ++$this->registrations; }
}

#[Handler( strategy: Handler::INIT_NOW )]
final class Review_Rejected_Callback_Handler {
    #[Filter( 'xwp_review_rejected_callback', conditional: '__return_false' )]
    public function value( string $value ): string {
        return $value . ':handled';
    }
}
