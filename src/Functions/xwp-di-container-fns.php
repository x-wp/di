<?php //phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName, Squiz.Commenting.FunctionComment.MissingParamTag
/**
 * Container functions.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

use XWP\DI\Container;
use XWP\DI\Interfaces\Extension_Module;

/**
 * Check if a container exists.
 *
 * @param  string $container_id Container ID.
 * @return bool
 */
function xwp_has( string $container_id ): bool {
    return \XWP\DI\App_Factory::has( $container_id );
}

/**
 * Get a container by ID.
 *
 * @param  string $container_id Container ID.
 * @return Container
 */
function xwp_app( string $container_id ): Container {
    return \XWP\DI\App_Factory::get( $container_id );
}

/**
 * Create a new app container.
 *
 * @template TCtr of Container
 * @param  array{
 *   app_class?: class-string<TCtr>,
 *   app_id?: string|false,
 *   app_module?: class-string,
 *   app_file?: string,
 *   app_type?: 'plugin'|'theme',
 *   app_version?: string,
 *   cache_hooks?: bool,
 *   cache_defs?: bool,
 *   cache_app?: bool,
 *   cache_dir?: string,
 *   public?: bool,
 *   use_autowiring?: bool,
 *   use_attributes?: bool,
 *   use_proxies?: bool,
 *   attributes?: bool,
 *   autowiring?: bool,
 *   compile?: bool,
 *   compile_class?: string,
 *   compile_dir?: string,
 *   proxies?: bool
 * }              $app      Application configuration.
 * @param  string $hook     Hook to create the container on.
 * @param  int    $priority Hook priority.
 * @return true
 */
function xwp_load_app( array $app, string $hook = 'plugins_loaded', int $priority = PHP_INT_MIN ): bool {
    return add_action(
        $hook,
        static function () use ( $app ): void {
            xwp_create_app( $app )->run();
        },
        $priority,
    );
}

/**
 * Create a new app container.
 *
 * @template TCtr of Container
 *
 * @param  array{
 *   app_class?: class-string<TCtr>,
 *   app_id?: string|false,
 *   app_module?: class-string,
 *   app_file?: string,
 *   app_type?: 'plugin'|'theme',
 *   app_version?: string,
 *   cache_app?: bool,
 *   cache_defs?: bool,
 *   cache_dir?: string,
 *   cache_hooks?: bool,
 *   public?: bool,
 *   use_autowiring?: bool,
 *   use_attributes?: bool,
 *   use_proxies?: bool,
 *   attributes?: bool,
 *   autowiring?: bool,
 *   compile?: bool,
 *   compile_class?: string,
 *   compile_dir?: string,
 *   proxies?: bool,
 * }  $args Application configuration.
 * @return Container
 */
function xwp_create_app( array $args ): Container {
    return \XWP\DI\App_Factory::instance()->create( $args );
}

/**
 * Extend an application container definition.
 *
 * @template TMod of Extension_Module
 * @param  array{
 *   id: string,
 *   module: class-string<TMod>,
 *   file?: string,
 *   version?: string,
 * }              $extension   Application configuration.
 * @param  string $application Target application ID.
 */
function xwp_extend_app( array $extension, string $application ): void {
    add_action(
        'xwp_di_init',
        static function ( $factory ) use ( $extension, $application ): void {
            $factory->extend( $extension, $application );
        },
    );

    if ( ! ( ( $extension['file'] ?? '' ) ) ) {
        return;
    }

    // We're defining a closure here in order to avoid autoloading classes before `plugins_loaded` hook.
    $register = static fn( $cb, $hook ) => $cb(
        $extension['file'],
        static function () use ( $application, $hook ): void {
            xwp_log( "Decompiling {$application} on {$hook}." );
            xwp_decompile_app( $application, 'deactivate' === $hook );
        },
    );

    $register( 'register_activation_hook', 'activate' );
    $register( 'register_deactivation_hook', 'deactivate' );
}

/**
 * Decompile an application container.
 *
 * @param  string $container_id Container ID.
 * @param  bool   $immediately  Decompile now or on shutdown.
 */
function xwp_decompile_app( string $container_id, bool $immediately = false ): void {
    \XWP\DI\App_Factory::decompile( $container_id, $immediately );
}

/**
 * Uninstall an extension.
 */
function xwp_uninstall_ext(): void {
    \XWP\DI\App_Factory::uninstall();
}
