<?php
/**
 * Application lifecycle against real WordPress hooks.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Core\Modules\Internal_Root_Module;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;
use XWP\DI\Invoker;

final class App_Bootstrap_Test extends TestCase {
    private string $cache_dir;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-bootstrap-' . uniqid();
        mkdir( $this->cache_dir );
        Lifecycle_Module::$initialized = array();
        Extension_Module::$initialized = false;
    }

    public function tear_down(): void {
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );
        parent::tear_down();
    }

    public function test_load_app_defers_creation_and_preserves_same_hook_module_timing(): void {
        $config = $this->config();
        self::assertTrue( xwp_load_app( $config, 'xwp_app_test_module' ) );
        self::assertFalse( xwp_has( $config['app_id'] ) );
        self::assertSame( array(), Lifecycle_Module::$initialized );

        do_action( 'xwp_app_test_module' );

        $app = xwp_app( $config['app_id'] );
        self::assertInstanceOf( App::class, $app );
        self::assertTrue( $app->started() );
        self::assertSame( array( $app ), Lifecycle_Module::$initialized );
        self::assertSame( 'handled', apply_filters( 'xwp_app_test_value', 'original' ) );
    }

    /** @dataProvider cache_modes */
    public function test_root_starts_imports_and_extensions_with_cold_and_warm_caches( bool $compile, bool $hooks ): void {
        $config = $this->config();
        $config['cache_app'] = $compile;
        $config['cache_hooks'] = $hooks;

        add_filter(
            'xwp_extend_import_' . $config['app_id'],
            static fn() => array(
                array( 'id' => 'extension', 'module' => Extension_Module::class, 'file' => false, 'type' => 'plugin', 'version' => '2.0' ),
            ),
        );

        foreach ( array( 'cold', 'warm' ) as $pass ) {
            Lifecycle_Module::$initialized = array();
            Extension_Module::$initialized = false;
            $app = App_Builder::configure( $config )->build()->get( App::class );

            self::assertSame( $app, $app->run() );
            self::assertSame( array(), Lifecycle_Module::$initialized );
            self::assertFalse( Extension_Module::$initialized );
            self::assertArrayHasKey( Internal_Root_Module::class, $app->container()->get( Invoker::class )->get_handlers() );

            do_action( 'xwp_app_test_module' );

            self::assertSame( array( $app ), Lifecycle_Module::$initialized, $pass );
            self::assertTrue( Extension_Module::$initialized, $pass );
            self::assertSame( 'handled', apply_filters( 'xwp_app_test_value', 'original' ) );
            self::assertSame( array( 'base', 'extension' ), $app->container()->get( 'feature.list' ) );
            self::assertSame( '2.0', $app->container()->get( 'app.ext.extension' )['ver'] );
            self::assertSame( 'user-defined', $app->container()->get( 'app.env' ) );

            remove_all_actions( 'xwp_app_test_module' );
            remove_all_filters( 'xwp_app_test_value' );
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

    public function test_running_twice_is_rejected(): void {
        $app = xwp_create_app( $this->config() );
        $app->run();

        $this->expectException( \RuntimeException::class );
        $app->run();
    }

    public function test_container_entry_point_uses_the_same_application_lifecycle(): void {
        $app = xwp_create_app( $this->config() );
        $container = $app->container();

        self::assertFalse( $container->started() );
        self::assertSame( $container, $container->run() );
        self::assertTrue( $app->started() );
        self::assertTrue( $container->started() );

        do_action( 'xwp_app_test_module' );
        self::assertSame( array( $app ), Lifecycle_Module::$initialized );

        $this->expectException( \RuntimeException::class );
        $app->run();
    }

    private function config(): array {
        $id = uniqid( 'lifecycle_' );

        return array(
            'app_id' => $id,
            'app_class' => 'Compiled' . $id,
            'app_module' => Lifecycle_Module::class,
            'app_file' => __FILE__,
            'app_type' => 'plugin',
            'app_debug' => false,
            'app_preload' => false,
            'app_version' => '1.0.0',
            'cache_app' => false,
            'cache_defs' => false,
            'cache_hooks' => false,
            'cache_dir' => $this->cache_dir,
            'extendable' => true,
            'public' => true,
            'use_attributes' => true,
            'use_autowiring' => true,
            'use_proxies' => false,
        );
    }
}

#[Module( hook: 'xwp_app_test_module', handlers: array( Lifecycle_Handler::class ) )]
final class Lifecycle_Module {
    public static array $initialized = array();

    public function __construct( private App $app ) {}

    public function on_initialize(): void {
        self::$initialized[] = $this->app;
    }

    public static function define(): array {
        return array( 'feature.list' => array( 'base' ), 'app.env' => 'user-defined' );
    }
}

#[Handler( tag: 'xwp_app_test_module', priority: 20 )]
final class Lifecycle_Handler {
    #[Filter( tag: 'xwp_app_test_value' )]
    public function value( string $value ): string {
        return 'handled';
    }
}

#[Module( hook: 'xwp_app_test_module', priority: 11 )]
final class Extension_Module {
    public static bool $initialized = false;

    public function on_initialize(): void {
        self::$initialized = true;
    }

    public static function define(): array {
        return array( 'feature.list' => array( 'extension' ) );
    }
}
