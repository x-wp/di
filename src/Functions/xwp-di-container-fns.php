<?php //phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName, Squiz.Commenting.FunctionComment.MissingParamTag
/**
 * Container functions.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

use DI\Container;

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
 * @param  array{
 *   id: string,
 *   module: class-string,
 *   attributes?: bool,
 *   autowiring?: bool,
 *   compile?: bool,
 *   compile_class?: string,
 *   compile_dir?: string,
 *   proxies?: bool,
 * } $app Application configuration.
 * @param  string $hook     Hook to create the container on.
 * @param  int    $priority Hook priority.
 * @return true
 */
function xwp_load_app( array $app, string $hook = 'plugins_loaded', int $priority = PHP_INT_MIN ): bool {
    return add_action(
        $hook,
        static function () use( $app ): void {
            xwp_create_app( $app );
        },
        $priority,
    );
}

/**
 * Create a new app container.
 *
 * @param  array{
 *   id: string,
 *   module: class-string,
 *   attributes?: bool,
 *   autowiring?: bool,
 *   compile?: bool,
 *   compile_class?: string,
 *   compile_dir?: string,
 *   proxies?: bool,
 * } $args Application configuration.
 * @return Container
 */
function xwp_create_app( array $args ): Container {
    return \XWP\DI\App_Factory::create( $args );
}

/**
 * Extend an application container definition.
 *
 * @param  string                     $container Container ID.
 * @param  string|array<class-string> $module    Module classname or array of module classnames.
 * @param  'before'|'after'           $position  Position to insert the module.
 * @param  string|null                $target    Target module to extend.
 */
function xwp_extend_app( string $container, string|array $module, string $position = 'after', ?string $target = null ): void {
    if ( ! is_array( $module ) ) {
        $module = array( $module );
    }

    \XWP\DI\App_Factory::extend( $container, $module, $position, $target );
}

/**
 * Decompile an application container.
 *
 * @param  string $container_id Container ID.
 * @param  bool   $immediately  Decompile now or on shutdown.
 * @return bool
 */
function xwp_decompile_app( string $container_id, bool $immediately = false ): bool {
    return \XWP\DI\App_Factory::decompile( $container_id, $immediately );
}
