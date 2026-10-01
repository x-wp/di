<?php
/**
 * Application extension and cache management through public helpers.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use DI\Definition\Source\SourceCache;
use XWP\DI\App_Factory;
use XWP\DI\Decorators\Module;

final class App_Management_Test extends TestCase {
    private string $cache_dir;
    private \ReflectionProperty $instance;
    private \ReflectionProperty $decompiled;
    private mixed $old_instance;
    private array $old_decompiled;

    public function set_up(): void {
        parent::set_up();
        $this->cache_dir = sys_get_temp_dir() . '/xwp-management-' . uniqid();
        mkdir( $this->cache_dir );
        $this->instance = new \ReflectionProperty( App_Factory::class, 'instance' );
        $this->instance->setAccessible( true );
        $this->old_instance = $this->instance->getValue();
        $this->instance->setValue( null, null );
        $this->decompiled = new \ReflectionProperty( App_Factory::class, 'decompiled' );
        $this->decompiled->setAccessible( true );
        $this->old_decompiled = $this->decompiled->getValue();
        $this->decompiled->setValue( null, array() );
    }

    public function tear_down(): void {
        $this->decompiled->setValue( null, array() );
        $this->instance->setValue( null, $this->old_instance );
        $this->decompiled->setValue( null, $this->old_decompiled );
        foreach ( glob( $this->cache_dir . '/*' ) as $file ) {
            unlink( $file );
        }
        if ( is_dir( $this->cache_dir ) ) {
            rmdir( $this->cache_dir );
        }
        parent::tear_down();
    }

    public function test_public_extension_helper_adds_module_definitions(): void {
        $config = $this->config();
        xwp_extend_app( array( 'id' => 'review-extension', 'module' => Management_Extension::class ), $config['app_id'] );
        $app = xwp_create_app( $config );
        self::assertSame( 'extension-value', $app->get( 'review.extension.value' ) );
        self::assertSame( '0.0.0-dev', $app->get( 'app.ext.review-extension' )['ver'] );
        self::assertFalse( $app->started() );
    }

    /** @dataProvider decompile_modes */
    public function test_decompile_helper_clears_the_wrapped_application_cache( bool $immediately ): void {
        $config = $this->config();
        $config['cache_hooks'] = true;
        $app = xwp_create_app( $config );
        self::assertFileExists( $this->cache_dir . '/hook-definition.php' );
        self::assertSame( $this->cache_dir, $app->get( 'app.cache' )['dir'] );
        remove_all_actions( 'shutdown' );
        xwp_decompile_app( $config['app_id'], $immediately );
        App_Factory::instance()->__destruct();
        $this->decompiled->setValue( null, array() );
        if ( ! $immediately ) {
            self::assertDirectoryExists( $this->cache_dir );
            do_action( 'shutdown' );
        }
        self::assertDirectoryDoesNotExist( $this->cache_dir );
    }

    public static function decompile_modes(): array {
        return array( 'immediate' => array( true ), 'shutdown' => array( false ) );
    }

    /** @dataProvider scheduled_uninstall_modes */
    public function test_uninstall_after_plugins_loaded_does_not_create_the_app( bool $scheduled, bool $cached, bool $legacy ): void {
        self::assertGreaterThan( 0, did_action( 'plugins_loaded' ) );
        $config = $this->config();
        $id = $config['app_id'];
        $config['cache_hooks'] = $cached;
        // An unavailable module makes accidental discovery or application construction fail.
        $config['app_module'] = 'UninstallMustNotLoad\\Module';
        if ( $legacy ) {
            $config['id'] = $id;
            $config['module'] = $config['app_module'];
            $config['compile_dir'] = $config['cache_dir'];
            unset( $config['app_id'], $config['app_module'], $config['cache_dir'] );
            $this->setExpectedIncorrectUsage( 'xwp_create_app' );
        }
        if ( $scheduled ) {
            xwp_load_app( $config );
        }
        file_put_contents( $this->cache_dir . '/hook-definition.php', '<?php return array();' );

        $other = $this->config();
        $other['cache_dir'] .= '-other';
        $other['cache_hooks'] = true;
        mkdir( $other['cache_dir'] );
        file_put_contents( $other['cache_dir'] . '/sentinel', 'keep' );
        xwp_load_app( $other );
        try {
            xwp_extend_app( array(
                'id' => 'review-extension', 'module' => Management_Extension::class,
                'file' => WP_PLUGIN_DIR . '/review-extension/plugin.php',
            ), $id );
            self::assertFalse( xwp_has( $id ) );
            add_action( 'uninstall_review-extension/plugin.php', 'xwp_uninstall_ext' );
            do_action( 'uninstall_review-extension/plugin.php' );
            App_Factory::instance()->__destruct();
            $this->decompiled->setValue( null, array() );

            self::assertFalse( xwp_has( $id ) );
            self::assertFalse( xwp_has( $other['app_id'] ) );
            self::assertSame( 'keep', file_get_contents( $other['cache_dir'] . '/sentinel' ) );
            if ( $scheduled && $cached ) {
                self::assertDirectoryDoesNotExist( $this->cache_dir );
            } else {
                self::assertFileExists( $this->cache_dir . '/hook-definition.php' );
            }
        } finally {
            unlink( $other['cache_dir'] . '/sentinel' );
            rmdir( $other['cache_dir'] );
        }
    }

    public static function scheduled_uninstall_modes(): array {
        return array(
            'scheduled with cache' => array( true, true, false ),
            'scheduled without cache' => array( true, false, false ),
            'unknown app' => array( false, true, false ),
            'legacy scheduled configuration' => array( true, true, true ),
        );
    }

    public function test_definition_cache_reports_missing_apcu_or_resolves_cached_definitions(): void {
        $config = $this->config();
        $config['cache_defs'] = true;
        if ( ! SourceCache::isSupported() ) {
            $this->expectException( \Exception::class );
            $this->expectExceptionMessage( 'APCu is not enabled' );
        }
        $app = xwp_create_app( $config );
        self::assertSame( $app, $app->get( \XWP\DI\App::class ) );
        self::assertTrue( $app->get( 'app.cache' )['defs'] );
        self::assertSame( 'base-value', $app->get( 'review.base.value' ) );
    }

    private function config(): array {
        return array(
            'app_id' => uniqid( 'management_' ), 'app_module' => Management_Module::class,
            'app_debug' => false, 'app_file' => false, 'app_type' => 'plugin',
            'cache_app' => false, 'cache_hooks' => false, 'cache_defs' => false,
            'cache_dir' => $this->cache_dir,
        );
    }
}

#[Module( hook: 'xwp_management_module' )]
final class Management_Module {
    public static function define(): array {
        return array( 'review.base.value' => 'base-value' );
    }
}

#[Module( hook: 'xwp_management_extension' )]
final class Management_Extension {
    public static function define(): array {
        return array( 'review.extension.value' => 'extension-value' );
    }
}
