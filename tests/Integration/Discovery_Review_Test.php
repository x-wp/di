<?php
/** @package XWP\DI\Tests */
namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Decorators\Filter;
use XWP\DI\Hook\Factory;
use XWP\DIT\Lifecycle\Discovery_Review_Module;
use XWP\DIT\Lifecycle\Discovery_Review_Infused;
use XWP\DIT\Lifecycle\Discovery_Review_Repeated;

final class Discovery_Review_Test extends TestCase {
    /** @dataProvider modes */
    public function test_custom_infuse_and_repeated_callbacks_survive_runtime_and_cache( bool $preload, bool $cache ): void {
        $dir = sys_get_temp_dir() . '/xwp-discovery-review-' . uniqid();
        mkdir( $dir );
        $id = uniqid( 'discovery_review_' );
        $config = array(
            'app_id' => $id, 'app_class' => 'Compiled' . $id,
            'app_module' => Discovery_Review_Module::class,
            'app_file' => false, 'app_type' => 'plugin', 'app_debug' => false,
            'app_preload' => $preload, 'app_version' => '1.0.0',
            'cache_app' => $cache, 'cache_defs' => false, 'cache_hooks' => $cache,
            'cache_dir' => $dir, 'extendable' => false, 'public' => false,
            'use_attributes' => true, 'use_autowiring' => true, 'use_proxies' => false,
        );
        try {
            foreach ( array( 'cold', 'warm' ) as $pass ) {
                $this->reset_hooks();
                $app = App_Builder::configure( $config )->build()->get( App::class );
                $app->run();
                do_action( 'xwp_discovery_review' );
                $factory = $app->container()->get( Factory::class );
                self::assertSame( 'custom-literal', $factory->get_handler( Discovery_Review_Infused::class )->get_target()->initialized, $pass );
                self::assertSame( 'custom-literal', apply_filters( 'xwp_discovery_infused', 'before' ), $pass );
                self::assertSame( 'v:repeat:repeat', apply_filters( 'xwp_discovery_repeat', 'v' ), $pass );
                self::assertSame( 'v:first', apply_filters( 'xwp_discovery_first', 'v' ), $pass );
                self::assertSame( 'v:second', apply_filters( 'xwp_discovery_second', 'v' ), $pass );
                $handler = $factory->get_handler( Discovery_Review_Repeated::class );
                self::assertSame( 'v:same:same', apply_filters( 'xwp_discovery_same', 'v' ) );
                self::assertSame( 'v:legacy:legacy:override', apply_filters( 'xwp_discovery_legacy', 'v' ) );
                apply_filters( 'xwp_discovery_views', 'v' );
                $views = array_values( array_filter( $factory->get_callbacks( $handler ), static fn( $callback ) => 'views' === $callback->get_method() ) );
                self::assertSame( array_map( static fn( $callback ) => $callback->get_token(), $views ), $handler->get_target()->tokens );
                $cb = ( new Filter( 'xwp_discovery_supplied', invoke: Filter::INV_PROXIED ) )->with_handler( $handler )->with_method( 'value' );
                xwp_load_handler_cbs( $handler, array( $cb ) );
                $cb->load();
                self::assertSame( $cb, $app->container()->get( $cb->get_token() ) );
                self::assertTrue( $cb->is_loaded() );
                self::assertSame( 'v:repeat', apply_filters( 'xwp_discovery_supplied', 'v' ) );
                self::assertSame( 1, $cb->fired );
                self::assertTrue( remove_filter( 'xwp_discovery_supplied', array( $cb, 'invoke' ) ) );
            }
        } finally {
            $this->reset_hooks();
            foreach ( glob( $dir . '/*' ) as $file ) { unlink( $file ); }
            rmdir( $dir );
        }
    }

    public function test_supplied_handler_uses_current_priority_plus_one(): void {
        $app = xwp_create_app( array( 'app_module' => Discovery_Review_Module::class, 'app_id' => uniqid( 'priority_' ), 'app_debug' => false ) );
        $app->run();
        $priority = null;
        add_action( 'xwp_discovery_priority', static function () use ( $app, &$priority ) {
            $handler = $app->container()->get( Factory::class )->create_handler( new \stdClass() );
            $priority = $handler->get_priority();
        }, 50 );
        do_action( 'xwp_discovery_priority' );
        remove_all_filters( 'xwp_discovery_priority' );
        self::assertSame( 51, $priority );
    }

    public static function modes(): array {
        return array( 'runtime' => array( false, false ), 'preload' => array( true, false ), 'cache' => array( true, true ) );
    }

    private function reset_hooks(): void {
        foreach ( array( 'review', 'infused', 'repeat', 'first', 'second', 'supplied', 'same', 'views', 'legacy' ) as $suffix ) {
            remove_all_filters( 'xwp_discovery_' . $suffix );
        }
    }
}
