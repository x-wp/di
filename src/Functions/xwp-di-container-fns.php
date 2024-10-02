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
