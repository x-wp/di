<?php
/**
 * Internal application root module.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Core\Modules;

use Psr\Container\ContainerInterface;
use XWP\DI\App;
use XWP\DI\Container;
use XWP\DI\Definition\ModuleDefinition;

/**
 * Provides framework definitions and wraps the application's module.
 *
 * @internal Bootstrap composition detail. Applications supply their own module.
 */
final class Internal_Root_Module {
    /**
     * Compose a root for one application without shared mutable state.
     *
     * @param  class-string $module Application module class.
     * @return ModuleDefinition
     */
    public static function compose( string $module ): ModuleDefinition {
        return new ModuleDefinition( self::class, imports: array( $module ) );
    }

    /**
     * Get framework services shared by every application definition.
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array(
            'app.env'     => \DI\factory( 'wp_get_environment_type' ),
            'app.root'    => \DI\value( self::class ),
            'app.uuid'    => \DI\factory( 'wp_generate_uuid4' ),
            'xwp.app'     => \DI\get( App::class ),
            'xwp.app.env' => \DI\get( 'app.env' ),
            'xwp.app.tag' => \DI\factory(
                static fn( string $tag, ContainerInterface $ctr ) =>
                    \DI\string( $tag )->resolve( $ctr ),
            ),
            App::class    => \DI\create()->constructor( \DI\get( Container::class ) ),
            self::class   => \DI\create(),
        );
    }

    /**
     * Get definitions for a normalized application configuration.
     *
     * Existing app.* entries continue to describe the user application.
     * The separate app.root entry identifies the internal bootstrap module.
     *
     * @param  array<string,mixed> $config Normalized application configuration.
     * @return array<string,mixed>
     */
    public static function definitions( array $config ): array {
        $definitions = \array_merge(
            self::configure(),
            array(
                'app'        => \DI\get( 'Hook-' . $config['app_module'] ),
                'app.cache'  => \DI\value(
                    array(
                        'app'   => $config['cache_app'],
                        'defs'  => $config['cache_defs'],
                        'dir'   => $config['cache_dir'],
                        'hooks' => $config['cache_hooks'],
                        'ns'    => $config['app_id'],
                    ),
                ),
                'app.debug'  => \DI\value( $config['app_debug'] ),
                'app.extend' => \DI\value( $config['extendable'] ),
                'app.id'     => \DI\value( $config['app_id'] ),
                'app.module' => \DI\value( $config['app_module'] ),
                'app.type'   => \DI\value( $config['app_type'] ),
                'app.ver'    => \DI\value( $config['app_version'] ),
            ),
        );

        if ( $config['app_file'] && 'plugin' === $config['app_type'] ) {
            $definitions['app.file'] = \DI\value( $config['app_file'] );
            $definitions['app.base'] = \DI\factory( 'plugin_basename' )
                ->parameter( 'file', \DI\get( 'app.file' ) );
            $definitions['app.path'] = \DI\factory( 'plugin_dir_path' )
                ->parameter( 'file', \DI\get( 'app.file' ) );
            $definitions['app.url']  = \DI\factory( 'plugins_url' )
                ->parameter( 'path', '' )
                ->parameter( 'plugin', \DI\get( 'app.base' ) );
        }

        return $definitions;
    }
}
