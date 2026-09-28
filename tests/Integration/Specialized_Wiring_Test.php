<?php
/**
 * Specialized callback discovery and cache round trips.
 *
 * @package XWP\DI\Tests
 */
namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Compiled_Container;
use XWP\DI\Hook\Factory;
use XWP\DI\Hook\Ajax_Callback;
use XWP\DI\Hook\REST_Callback;
use XWP\DI\Hook\CLI_Callback;
use XWP\DI\Decorators\Filter;
use XWP\DIT\Lifecycle\Specialized_Module;
use XWP\DIT\Lifecycle\Specialized_Ajax;
use XWP\DIT\Lifecycle\Specialized_REST;
use XWP\DIT\Lifecycle\Specialized_CLI;

final class Specialized_Wiring_Test extends TestCase {
    private string $cache_dir;
    private int $original_context;
    private \ReflectionProperty $context;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-specialized-wiring-' . uniqid();
        mkdir( $this->cache_dir );
        $this->original_context = \XWP_Context::get();
        $this->context = new \ReflectionProperty( \XWP_Context::class, 'current' );
        $this->context->setAccessible( true );
        $this->context->setValue( null, Filter::CTX_AJAX | Filter::CTX_REST | Filter::CTX_CLI );
        // Isolate scheduling from WordPress admin redirects and update checks.
        remove_all_actions( 'admin_init' );
        require_once dirname( __DIR__, 2 ) . '/vendor/wp-cli/wp-cli/php/utils.php';
    }

    public function tear_down(): void {
        $this->reset_hooks();
        $GLOBALS['wp_rest_server'] = null;
        $this->context->setValue( null, $this->original_context );
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );
        parent::tear_down();
    }

    /** @dataProvider modes */
    public function test_specialized_callbacks_survive_cold_and_warm_caches( bool $compile, bool $hooks, bool $preload ): void {
        foreach ( $this->apps( $compile, $hooks, $preload ) as $app ) {
            $factory = $app->container()->get( Factory::class );
            foreach ( array( Specialized_Ajax::class => Ajax_Callback::class, Specialized_REST::class => REST_Callback::class, Specialized_CLI::class => CLI_Callback::class ) as $class => $type ) {
                $handler = $factory->get_handler( $class );
                $callbacks = $factory->get_callbacks( $handler );
                self::assertCount( 1, $callbacks );
                $callback = $callbacks[0];
                self::assertSame( $type, $callback::class );
                self::assertSame( $callback, $app->container()->get( $callback->get_token() ) );
                self::assertTrue( $callback->is_loaded() );
                if ( Ajax_Callback::class === $type ) {
                    do_action( 'wp_ajax_specialized_fetch' );
                } elseif ( REST_Callback::class === $type ) {
                    $request = new \WP_REST_Request( 'GET', '/specialized/v1/items/fetch' );
                    self::assertSame( array( 'cached' => true ), rest_do_request( $request )->get_data() );
                } else {
                    $callback->run_cmd( array(), array() );
                }
                self::assertNotNull( $handler->get_target()->view, $class );
                self::assertSame( $callback->get_token(), $handler->get_target()->view->get_token() );
                self::assertSame( $callback, $app->container()->get( $handler->get_target()->view->get_token() ) );
                $factory->load_callbacks( $handler, $callbacks );
                self::assertSame( $callbacks, $factory->get_callbacks( $handler ) );
            }
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

    private function apps( bool $compile, bool $hooks, bool $preload ): \Generator {
        $id = uniqid( 'specialized_' );
        $config = array(
            'app_id' => $id, 'app_class' => 'Compiled' . $id,
            'app_module' => Specialized_Module::class,
            'app_file' => false, 'app_type' => 'plugin', 'app_debug' => false,
            'app_preload' => $preload, 'app_version' => '1.0.0',
            'cache_app' => $compile, 'cache_defs' => false, 'cache_hooks' => $hooks,
            'cache_dir' => $this->cache_dir, 'extendable' => false, 'public' => false,
            'use_attributes' => true, 'use_autowiring' => true, 'use_proxies' => false,
        );
        $original_hooks = array_map( static fn( $hook ) => clone $hook, $GLOBALS['wp_filter'] );
        foreach ( array( 'cold', 'warm' ) as $pass ) {
            $GLOBALS['wp_filter'] = array_map( static fn( $hook ) => clone $hook, $original_hooks );
            $GLOBALS['wp_rest_server'] = new \WP_REST_Server();
            $this->reset_hooks();
            $app = App_Builder::configure( $config )->build()->get( App::class );
            self::assertSame( $compile, $app->container() instanceof Compiled_Container );
            self::assertSame( $hooks, file_exists( $this->cache_dir . '/hook-definition.php' ) );
            $app->run();
            do_action( 'specialized_module' );
            do_action( 'admin_init' );
            do_action( 'cli_init' );
            do_action( 'rest_api_init' );
            yield $pass => $app;
        }
    }

    private function reset_hooks(): void {
        foreach ( array( 'specialized_module', 'wp_ajax_specialized_fetch', 'wp_ajax_nopriv_specialized_fetch', 'specialized/v1/items' ) as $tag ) {
            remove_all_filters( $tag );
        }
    }
}
