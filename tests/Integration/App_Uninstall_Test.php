<?php
/**
 * Uninstall coverage isolated from the suite's WordPress constants.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use Brain\Monkey\Functions;
use XWP\DI\App;
use XWP\DI\App_Factory;
use XWP\DI\Decorators\Module;

/**
 * The subprocess uses the unit bootstrap and the real WordPress filesystem only.
 * Keeping this in integration means the unit suite still needs no downloaded core.
 */
final class App_Uninstall_Test extends \Tests\XWP\DI\Unit\TestCase {
    /**
     * @dataProvider uninstall_paths
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_uninstall_only_clears_the_mapped_application_cache( string $trigger, string|false $extension_file, bool $removed ): void {
        $core = rtrim( getenv( 'WP_CORE_DIR' ) ?: dirname( __DIR__ ) . '/tmp/wordpress', '/' );
        define( 'ABSPATH', $core . '/' );
        define( 'WP_CONTENT_DIR', $core . '/wp-content' );
        define( 'WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins' );
        if ( str_ends_with( $trigger, 'constant' ) ) {
            define( 'WP_UNINSTALL_PLUGIN', ( 'absolute-constant' === $trigger ? WP_PLUGIN_DIR . '/' : '' ) . 'review-extension/plugin.php' );
        }
        require_once ABSPATH . 'wp-includes/class-wp-error.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
        require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
        $GLOBALS['wp_filesystem'] = new \WP_Filesystem_Direct( null );

        Functions\when( 'wp_get_environment_type' )->justReturn( 'testing' );
        Functions\when( 'wp_parse_args' )->alias( static fn( $args, $defaults = array() ) => array_merge( $defaults, $args ) );
        Functions\when( 'untrailingslashit' )->alias( static fn( $path ) => rtrim( $path, '/' ) );
        Functions\when( 'trailingslashit' )->alias( static fn( $path ) => rtrim( $path, '/' ) . '/' );
        Functions\when( 'esc_html' )->returnArg();
        Functions\when( '__' )->returnArg();
        Functions\when( 'plugin_basename' )->alias( static fn( string $file ): string => str_replace( WP_PLUGIN_DIR . '/', '', $file ) );
        Functions\when( 'current_action' )->justReturn( $trigger );

        $cache_dir = sys_get_temp_dir() . '/xwp-uninstall-' . uniqid();
        mkdir( $cache_dir );
        $id = uniqid( 'uninstall_' );
        $decompiled = new \ReflectionProperty( App_Factory::class, 'decompiled' );
        $decompiled->setAccessible( true );
        try {
            App_Factory::instance()->extend( array(
                'id' => 'review-extension', 'module' => Uninstall_Module::class,
                'file' => false === $extension_file ? false : ( 'absolute' === $extension_file ? WP_PLUGIN_DIR . '/' : '' ) . 'review-extension/plugin.php', 'type' => 'plugin',
            ), $id );
            $app = xwp_create_app( array(
                'app_id' => $id, 'app_module' => Uninstall_Module::class,
                'app_debug' => false, 'app_file' => false, 'app_type' => 'plugin',
                'cache_app' => false, 'cache_hooks' => true, 'cache_defs' => false,
                'cache_dir' => $cache_dir,
            ) );
            self::assertInstanceOf( App::class, $app );
            self::assertFileExists( $cache_dir . '/hook-definition.php' );
            self::assertSame( $cache_dir, $app->get( 'app.cache' )['dir'] );

            xwp_uninstall_ext();
            App_Factory::instance()->__destruct();

            if ( $removed ) {
                self::assertDirectoryDoesNotExist( $cache_dir );
            } else {
                self::assertDirectoryExists( $cache_dir );
                self::assertFileExists( $cache_dir . '/hook-definition.php' );
            }
        } finally {
            $decompiled->setValue( null, array() );
            if ( is_dir( $cache_dir ) ) {
                $GLOBALS['wp_filesystem']->rmdir( $cache_dir, true );
            }
        }
    }

    public static function uninstall_paths(): array {
        return array(
            'constant and relative file' => array( 'constant', 'relative', true ),
            'constant and absolute file' => array( 'constant', 'absolute', true ),
            'absolute constant and relative file' => array( 'absolute-constant', 'relative', true ),
            'registered callback and relative file' => array( 'uninstall_review-extension/plugin.php', 'relative', true ),
            'registered callback and absolute file' => array( 'uninstall_review-extension/plugin.php', 'absolute', true ),
            'unrelated callback' => array( 'uninstall_other/plugin.php', 'absolute', false ),
            'ordinary hook' => array( 'init', 'absolute', false ),
            'extension without a file' => array( 'constant', false, false ),
        );
    }

}

#[Module( hook: 'xwp_uninstall_module' )]
final class Uninstall_Module {}
