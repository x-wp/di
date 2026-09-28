<?php
/**
 * Initialization conditions resolve explicit arguments at their lifecycle point.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Compiled_Container;
use XWP\DI\Decorators\Handler;
use XWP\DI\Invoker;
use XWP\DIT\Lifecycle\Auto_Infuse_Handler;
use XWP\DIT\Lifecycle\Condition_Service;
use XWP\DIT\Lifecycle\Early_Infuse_Handler;
use XWP\DIT\Lifecycle\Infuse_Condition_Module;
use XWP\DIT\Lifecycle\Jit_Infuse_Handler;
use XWP\DIT\Lifecycle\Lazy_Infuse_Handler;
use XWP\DIT\Lifecycle\Now_Infuse_Handler;
use XWP\DIT\Lifecycle\Typed_Condition_Handler;

final class Infuse_Condition_Test extends TestCase {
    private string $cache_dir;
    private int $original_context;
    private \ReflectionProperty $context;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-infuse-condition-' . uniqid();
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
    public function test_infuse_resolves_at_initialization_and_reads_updated_config_on_retry( string $class, bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $pass => $app ) {
            $container = $app->container();
            $invoker = $container->get( Invoker::class );
            $this->context->setValue( null, Handler::CTX_FRONTEND );
            $handler = $invoker->register_handler( $class );
            self::assertSame( array(), Infuse_Condition_Module::$events, 'Excluded contexts must not resolve condition arguments.' );
            $this->context->setValue( null, Handler::CTX_ADMIN );
            $invoker->register_handler( $class );
            if ( ! in_array( $class, array( Early_Infuse_Handler::class, Now_Infuse_Handler::class ), true ) ) {
                self::assertSame( array(), Infuse_Condition_Module::$events, 'Registration must preserve deferred argument resolution.' );
            }
            do_action( 'xwp_infuse_attach' );
            if ( Jit_Infuse_Handler::class === $class ) {
                self::assertSame( array(), Infuse_Condition_Module::$events, 'JIT attachment must not resolve initialization arguments.' );
            }
            self::assertSame( 'value', apply_filters( 'xwp_infuse_value', 'value' ) );
            self::assertSame( array( 'resolve config', 'resolve service', 'condition' ), Infuse_Condition_Module::$events );
            self::assertFalse( $handler->is_loaded() );
            self::assertNull( $handler->get_target() );
            self::assertSame( array( array(
                array( 'enabled' => false, 'request' => $pass ), $handler, $container->get( Condition_Service::class ),
            ) ), Infuse_Condition_Module::$seen );

            $config = array( 'enabled' => true, 'request' => $pass );
            $container->set( 'infuse.cfg', $config );
            $invoker->register_handler( $class );
            do_action( 'xwp_infuse_attach' );
            self::assertSame( 'value:handled', apply_filters( 'xwp_infuse_value', 'value' ) );
            self::assertTrue( $handler->is_loaded() );
            self::assertCount( 2, Infuse_Condition_Module::$seen );
            self::assertSame( array( $config, $handler, $container->get( Condition_Service::class ) ), Infuse_Condition_Module::$seen[1] );
            self::assertSame( array( 'resolve config', 'resolve service', 'condition', 'condition', 'construct', 'initialize', 'invoke' ), Infuse_Condition_Module::$events );

            $container->set( 'infuse.cfg', array( 'enabled' => false ) );
            $invoker->register_handler( $class );
            do_action( 'xwp_infuse_attach' );
            self::assertSame( 'value:handled', apply_filters( 'xwp_infuse_value', 'value' ) );
            self::assertCount( 2, Infuse_Condition_Module::$seen, 'Successful initialization is retained.' );
        }
    }

    /** @dataProvider cache_modes */
    public function test_typed_condition_without_infuse_still_autowires( bool $compile, bool $hooks ): void {
        foreach ( $this->apps( $compile, $hooks ) as $app ) {
            $handler = $app->container()->get( Invoker::class )->register_handler( Typed_Condition_Handler::class );
            self::assertTrue( $handler->is_loaded() );
            self::assertSame( array( 'resolve service', 'condition', 'construct', 'initialize' ), Infuse_Condition_Module::$events );
            self::assertSame( array( $app->container()->get( Condition_Service::class ) ), Infuse_Condition_Module::$seen );
            self::assertSame( 'value:handled', apply_filters( 'xwp_infuse_value', 'value' ) );
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

    public static function strategies_and_cache_modes(): array {
        $cases = array();
        foreach ( array( 'auto' => Auto_Infuse_Handler::class, 'early' => Early_Infuse_Handler::class, 'now' => Now_Infuse_Handler::class, 'lazy' => Lazy_Infuse_Handler::class, 'jit' => Jit_Infuse_Handler::class ) as $strategy => $class ) {
            foreach ( self::cache_modes() as $mode => $flags ) {
                $cases[ $strategy . ' ' . $mode ] = array( $class, ...$flags );
            }
        }
        return $cases;
    }

    private function apps( bool $compile, bool $hooks ): \Generator {
        $id = uniqid( 'infuse_condition_' );
        $config = array(
            'app_id' => $id,
            'app_class' => 'Compiled' . $id,
            'app_module' => Infuse_Condition_Module::class,
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
            Infuse_Condition_Module::$request = $pass;
            $app = App_Builder::configure( $config )->build()->get( App::class );
            self::assertSame( $compile, $app->container() instanceof Compiled_Container );
            self::assertSame( $hooks, file_exists( $this->cache_dir . '/hook-definition.php' ) );
            $app->run();
            self::assertSame( array(), Infuse_Condition_Module::$events, 'Building and starting must not resolve condition arguments.' );
            yield $pass => $app;
        }
    }

    private function reset_lifecycle(): void {
        foreach ( array( 'xwp_infuse_module', 'xwp_infuse_attach', 'xwp_infuse_value' ) as $hook ) {
            remove_all_filters( $hook );
        }
        remove_all_actions( 'Hook-' . Lazy_Infuse_Handler::class . '_' . Handler::INIT_LAZY . '_init' );
        remove_all_actions( 'Hook-' . Jit_Infuse_Handler::class . '_' . Handler::INIT_JIT . '_init' );
        Infuse_Condition_Module::$events = array();
        Infuse_Condition_Module::$seen = array();
        Infuse_Condition_Module::$request = '';
        $this->context->setValue( null, Handler::CTX_ADMIN );
    }
}
