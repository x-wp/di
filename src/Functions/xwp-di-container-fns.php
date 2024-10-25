<?php //phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName, Squiz.Commenting.FunctionComment.MissingParamTag
/**
 * Container functions.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

use DI\Container;

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

    add_filter(
        "xwp_dynamic_import_{$container}",
        static function ( array $imports, string $classname ) use( $module, $position, $target ): array {
            if ( $target && $target !== $classname ) {
                return $imports;
            }

            $params = 'after' === $position
                ? array( $imports, $module )
                : array( $module, $imports );

            return array_merge( ...$params );
		},
        10,
        2,
    );
}
