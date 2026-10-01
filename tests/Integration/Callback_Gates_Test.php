<?php
/**
 * Callback eligibility stays independent of handler readiness.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Compiled_Container;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Hook\Callback;
use XWP\DI\Hook\Factory;
use XWP\DI\Invoker;
use XWP\DIT\Lifecycle\Auto_Gates_Handler;
use XWP\DIT\Lifecycle\Callback_Gates_Module;
use XWP\DIT\Lifecycle\Early_Gates_Handler;
use XWP\DIT\Lifecycle\Jit_Gates_Handler;
use XWP\DIT\Lifecycle\Lazy_Gates_Handler;
use XWP\DIT\Lifecycle\Now_Gates_Handler;

final class Callback_Gates_Test extends TestCase {
    private string $cache_dir;
    private int $original_context;
    private \ReflectionProperty $context;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-callback-gates-' . uniqid();
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

    /** @dataProvider strategies_and_cache_modes */
    public function test_proxied_conditions_are_evaluated_when_the_hook_fires( string $class, bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            Callback_Gates_Module::$allowed = false;
            $handler = $app->container()->get( Invoker::class )->register_handler( $class );
            do_action( 'xwp_gates_attach' );

            self::assertNotFalse( has_filter( 'xwp_gates_proxied' ) );
            self::assertNotFalse( has_action( 'xwp_gates_proxied_action' ) );
            self::assertSame( $handler->is_lazy(), false !== has_filter( 'xwp_gates_standard' ) );
            self::assertSame( $handler->is_lazy() ? 0 : 2, Callback_Gates_Module::$checks );
            self::assertSame( 'value', apply_filters( 'xwp_gates_proxied', 'value' ) );
            do_action( 'xwp_gates_proxied_action' );
            self::assertSame( array( 'construct', 'initialize' ), Callback_Gates_Module::$events );

            // A changing request condition needs no explicit reattachment.
            Callback_Gates_Module::$allowed = true;
            self::assertSame( 'value:proxied', apply_filters( 'xwp_gates_proxied', 'value' ) );
            do_action( 'xwp_gates_proxied_action' );
            self::assertSame( array( 'construct', 'initialize', 'proxied filter', 'proxied action' ), Callback_Gates_Module::$events );

            // Direct callbacks can retry a previously rejected attachment.
            foreach ( $handler->get_callbacks() as $token ) {
                $callback = $app->container()->get( $token );
                self::assertTrue( $callback->load() );
                self::assertTrue( $callback->load() );
            }
            self::assertSame( 'value:standard', apply_filters( 'xwp_gates_standard', 'value' ) );
        }
    }

    /** @dataProvider strategies_and_cache_modes */
    public function test_context_rejection_can_retry_and_proxies_recheck_at_invocation( string $class, bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $this->context->setValue( null, Handler::CTX_FRONTEND );
            $handler = $app->container()->get( Invoker::class )->register_handler( $class );
            do_action( 'xwp_gates_attach' );
            self::assertFalse( has_filter( 'xwp_gates_proxied' ) );
            self::assertSame( 0, Callback_Gates_Module::$checks );

            $this->context->setValue( null, Handler::CTX_ADMIN );
            foreach ( $handler->get_callbacks() as $token ) {
                self::assertTrue( $app->container()->get( $token )->load() );
            }
            self::assertSame( $handler->is_lazy() ? 0 : 2, Callback_Gates_Module::$checks );

            Callback_Gates_Module::$allowed = false;
            self::assertSame( $handler->is_lazy() ? 'value' : 'value:standard', apply_filters( 'xwp_gates_standard', 'value' ) );
            self::assertSame( 'value', apply_filters( 'xwp_gates_proxied', 'value' ) );
            do_action( 'xwp_gates_standard_action' );
            do_action( 'xwp_gates_proxied_action' );
            self::assertSame( $handler->is_lazy() ? array( 'construct', 'initialize' ) : array( 'construct', 'initialize', 'standard filter', 'standard action' ), Callback_Gates_Module::$events );
            self::assertSame( 4, Callback_Gates_Module::$checks );

            $events = Callback_Gates_Module::$events;
            $checks = Callback_Gates_Module::$checks;
            Callback_Gates_Module::$allowed = true;
            $this->context->setValue( null, Handler::CTX_FRONTEND );
            self::assertSame( 'value', apply_filters( 'xwp_gates_proxied', 'value' ) );
            do_action( 'xwp_gates_proxied_action' );
            self::assertSame( $events, Callback_Gates_Module::$events );
            self::assertSame( $checks, Callback_Gates_Module::$checks );

            $this->context->setValue( null, Handler::CTX_ADMIN );
            self::assertSame( 'value:proxied', apply_filters( 'xwp_gates_proxied', 'value' ) );
            do_action( 'xwp_gates_proxied_action' );
            self::assertSame( array_merge( $events, array( 'proxied filter', 'proxied action' ) ), Callback_Gates_Module::$events );
            self::assertSame( $checks + 2, Callback_Gates_Module::$checks );
        }
    }

    public function test_attachment_honors_subclass_eligibility(): void {
        foreach ( $this->apps( false, false ) as $app ) {
            $handler = $app->container()->get( Invoker::class )->register_handler( Now_Gates_Handler::class );
            $definition = CallbackDefinition::from_data( array(
                'type' => Filter::class, 'args' => array( 'tag' => 'xwp_gates_override', 'args' => 1 ),
                'params' => array( 'classname' => $handler->get_classname(), 'method' => 'standard_filter' ),
            ) );
            $callback = new Eligibility_Callback( $definition, $app->container() );
            $factory = $app->container()->get( Factory::class );
            $factory->load_callbacks( $handler, array( $callback ) );
            self::assertSame( $callback, $app->container()->get( $callback->get_token() ) );
            self::assertFalse( $callback->load() );
            self::assertFalse( has_filter( 'xwp_gates_override' ) );
            $callback->eligible = true;
            self::assertTrue( $callback->load() );
            self::assertSame( 'value:standard', apply_filters( 'xwp_gates_override', 'value' ) );
            self::assertSame( 1, $callback->fired );
            $factory->load_callbacks( $handler, array( $callback ) );
            self::assertSame( array( $callback ), $factory->get_callbacks( $handler ) );
            self::assertSame( 1, $callback->fired );
            self::assertTrue( remove_filter( 'xwp_gates_override', array( $callback, 'invoke' ) ) );
        }
    }

    public static function strategies_and_cache_modes(): array {
        $cases = array();
        foreach ( array( 'auto' => Auto_Gates_Handler::class, 'early' => Early_Gates_Handler::class, 'now' => Now_Gates_Handler::class, 'lazy' => Lazy_Gates_Handler::class, 'jit' => Jit_Gates_Handler::class ) as $strategy => $class ) {
            foreach ( array( 'uncached' => array( false, false ), 'hooks' => array( false, true ), 'container' => array( true, false ), 'both' => array( true, true ) ) as $mode => $flags ) {
                $cases[ $strategy . ' ' . $mode ] = array( $class, ...$flags );
            }
        }
        return $cases;
    }

    private function apps( bool $compile, bool $hooks ): \Generator {
        $id = uniqid( 'callback_gates_' );
        $config = array(
            'app_id' => $id,
            'app_class' => 'Compiled' . $id,
            'app_module' => Callback_Gates_Module::class,
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
        foreach ( array( 'xwp_gates_module', 'xwp_gates_attach', 'xwp_gates_standard', 'xwp_gates_proxied', 'xwp_gates_standard_action', 'xwp_gates_proxied_action', 'xwp_gates_override' ) as $hook ) {
            remove_all_filters( $hook );
        }
        remove_all_actions( 'Hook-' . Lazy_Gates_Handler::class . '_' . Handler::INIT_LAZY . '_init' );
        remove_all_actions( 'Hook-' . Jit_Gates_Handler::class . '_' . Handler::INIT_JIT . '_init' );
        Callback_Gates_Module::$allowed = true;
        Callback_Gates_Module::$checks = 0;
        Callback_Gates_Module::$events = array();
        $this->context->setValue( null, Handler::CTX_ADMIN );
    }
}

final class Eligibility_Callback extends Callback {
    public bool $eligible = false;

    public function can_load(): bool {
        return parent::can_load() && $this->eligible;
    }
}
