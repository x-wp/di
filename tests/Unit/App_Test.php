<?php
/**
 * Application bootstrap and container identity tests.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Unit;

use Brain\Monkey\Functions;
use XWP\DI\App;
use XWP\DI\App_Builder;
use XWP\DI\Compiled_Container;
use XWP\DI\Container;
use XWP\DI\Core\Modules\Internal_Root_Module;
use XWP\DI\Decorators\Module;

final class App_Test extends TestCase {
    private string $cache_dir;

    protected function set_up(): void {
        parent::set_up();

        $this->cache_dir = sys_get_temp_dir() . '/xwp-app-' . uniqid();
        mkdir( $this->cache_dir );

        defined( 'ABSPATH' ) || define( 'ABSPATH', '/tmp/wordpress/' );
        defined( 'WP_CONTENT_DIR' ) || define( 'WP_CONTENT_DIR', '/tmp/wordpress/wp-content' );
        defined( 'WP_PLUGIN_DIR' ) || define( 'WP_PLUGIN_DIR', '/tmp/wordpress/wp-content/plugins' );

        Functions\when( 'wp_get_environment_type' )->justReturn( 'testing' );
        Functions\when( 'wp_parse_args' )->alias( static fn( $args, $defaults = array() ) => array_merge( $defaults, $args ) );
        Functions\when( 'untrailingslashit' )->alias( static fn( $path ) => rtrim( $path, '/' ) );
        Functions\when( 'esc_html' )->returnArg();
    }

    protected function tear_down(): void {
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        rmdir( $this->cache_dir );

        parent::tear_down();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_load_app_does_not_autoload_classes_before_its_hook(): void {
        self::assertFalse( class_exists( App::class, false ) );
        $autoloaded = array();
        $loader = static function ( string $class ) use ( &$autoloaded ): void {
            if ( str_starts_with( $class, 'XWP\\' ) || str_starts_with( $class, 'DI\\' ) ) {
                $autoloaded[] = $class;
            }
        };
        spl_autoload_register( $loader, true, true );

        try {
            self::assertTrue( xwp_load_app( array( 'app_id' => 'deferred', 'app_module' => 'NotLoadedYet\\Module' ) ) );
            self::assertSame( array(), $autoloaded );
            self::assertFalse( class_exists( App::class, false ) );
            self::assertFalse( class_exists( \XWP\DI\App_Factory::class, false ) );
        } finally {
            spl_autoload_unregister( $loader );
        }
    }

    public function test_helpers_return_the_registered_app_and_injection_uses_the_same_instance(): void {
        $id  = uniqid( 'app_' );
        $app = xwp_create_app( $this->config( $id ) );

        self::assertInstanceOf( App::class, $app );
        self::assertNotInstanceOf( Container::class, $app );
        self::assertSame( $app, xwp_app( $id ) );
        self::assertTrue( xwp_has( $id ) );
        self::assertSame( $app, $app->container()->get( 'xwp.app' ) );
        self::assertSame( $app, $app->container()->get( App::class ) );
        self::assertSame( $app, $app->container()->get( App_Consumer::class )->app );
        self::assertFalse( $app->started() );
    }

    /** @dataProvider cache_modes */
    public function test_root_imports_and_app_identity_survive_cache_reuse( bool $compile, bool $hooks ): void {
        $config = $this->config( uniqid( 'cached_' ) );
        $config['cache_app']   = $compile;
        $config['cache_hooks'] = $hooks;

        $first  = App_Builder::configure( $config )->build();
        $second = App_Builder::configure( $config )->build();

        foreach ( array( $first, $second ) as $container ) {
            $app = $container->get( 'xwp.app' );
            self::assertInstanceOf( App::class, $app );
            self::assertSame( $container, $app->container() );
            self::assertSame( $app, $container->get( App::class ) );
            self::assertSame( $app, $container->get( App_Consumer::class )->app );
            self::assertSame( App_Test_Module::class, $container->get( 'app.module' ) );
            self::assertSame( 'application override', $container->get( 'app.env' ) );
            self::assertSame( 'application override', $container->get( 'xwp.app.env' ) );
            self::assertSame(
                array( App_Test_Module::class ),
                $container->get( 'Hook-' . Internal_Root_Module::class )->get_imports(),
            );
        }

        self::assertNotSame( $first->get( 'xwp.app' ), $second->get( 'xwp.app' ) );
        if ( $compile ) {
            self::assertInstanceOf( Compiled_Container::class, $first );
        }
    }

    public static function cache_modes(): array {
        return array(
            'uncached' => array( false, false ),
            'hooks'    => array( false, true ),
            'container' => array( true, false ),
            'both'     => array( true, true ),
        );
    }

    public function test_existing_hook_cache_can_be_wrapped_by_the_internal_root(): void {
        $config = $this->config( uniqid( 'legacy_cache_' ) );
        $config['cache_hooks'] = true;
        $parser = new \XWP\DI\Hook\Parser( App_Test_Module::class, $config['app_id'] );
        ( new \XWP\DI\Hook\Compiler( $parser ) )->compile( $this->cache_dir );

        $container = App_Builder::configure( $config )->build();

        self::assertSame(
            array( App_Test_Module::class ),
            $container->get( 'Hook-' . Internal_Root_Module::class )->get_imports(),
        );
    }

    public function test_two_apps_keep_their_own_module_and_metadata(): void {
        $first_config  = $this->config( uniqid( 'first_' ) );
        $second_config = $this->config( uniqid( 'second_' ) );
        $second_config['app_module'] = Other_App_Test_Module::class;

        $first  = xwp_create_app( $first_config );
        $second = xwp_create_app( $second_config );

        self::assertSame( $first_config['app_id'], $first->container()->get( 'app.id' ) );
        self::assertSame( $second_config['app_id'], $second->container()->get( 'app.id' ) );
        self::assertSame( array( App_Test_Module::class ), $first->container()->get( 'Hook-' . Internal_Root_Module::class )->get_imports() );
        self::assertSame( array( Other_App_Test_Module::class ), $second->container()->get( 'Hook-' . Internal_Root_Module::class )->get_imports() );
    }

    public function test_private_apps_are_injectable_but_not_externally_accessible(): void {
        $config = $this->config( uniqid( 'private_' ) );
        $config['public'] = false;
        $app = xwp_create_app( $config );

        self::assertSame( $app, $app->container()->get( 'xwp.app' ) );
        $this->expectException( \InvalidArgumentException::class );
        xwp_app( $config['app_id'] );
    }

    public function test_duplicate_app_ids_are_rejected(): void {
        $config = $this->config( uniqid( 'duplicate_' ) );
        xwp_create_app( $config );

        $this->expectException( \InvalidArgumentException::class );
        xwp_create_app( $config );
    }

    private function config( string $id ): array {
        return array(
            'app_id' => $id,
            'app_class' => 'Compiled' . $id,
            'app_module' => App_Test_Module::class,
            'app_file' => false,
            'app_type' => 'plugin',
            'app_debug' => false,
            'app_preload' => false,
            'app_version' => '1.2.3',
            'cache_app' => false,
            'cache_defs' => false,
            'cache_hooks' => false,
            'cache_dir' => $this->cache_dir,
            'extendable' => false,
            'public' => true,
            'use_attributes' => true,
            'use_autowiring' => true,
            'use_proxies' => false,
        );
    }
}

#[Module( hook: 'init' )]
final class App_Test_Module {
    public static function configure(): array {
        return array(
            'app.env' => 'application override',
            App_Consumer::class => \DI\autowire(),
        );
    }
}

#[Module( hook: 'init' )]
final class Other_App_Test_Module {}

final class App_Consumer {
    public function __construct( public App $app ) {}
}
