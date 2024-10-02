<?php
/**
 * Hook invoker functions.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

use XWP\DI\Decorators\Module;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Invoker;

/**
 * Get an application module.
 *
 * @template T of object
 * @param  class-string<T> $classname The module classname.
 * @return Module<T>
 */
function xwp_get_module( string $classname ): ?Module {
    return xwp_hook_invoker()->get_handler( $classname );
}

/**
 * Register an application module.
 *
 * @template T of object
 * @param  class-string<T> $classname The module classname.
 * @return Module<T>
 */
function xwp_register_module( string $classname ): Module {
    return xwp_hook_invoker()->register_module( $classname );
}

/**
 * Register a hook handler.
 *
 * @template T of object
 * @param  class-string<T> $classname The handler classname.
 * @return Can_Handle<T>
 */
function xwp_register_hook_handler( string $classname ): Can_Handle {
    return xwp_hook_invoker()->register_handler( $classname )->get_handler( $classname );
}

/**
 * Load a handler for a given instance.
 *
 * @template THndlr of object
 * @param  THndlr  $instance  The instance to load a handler for.
 * @param  ?string $container The container ID.
 * @return Can_Handle<THndlr>
 */
function xwp_load_hook_handler( object $instance, ?string $container = null ): Can_Handle {
    return xwp_hook_invoker()->load_handler( $instance, $container )->get_handler( $instance::class );
}


/**
 * Get the hook invoker instance.
 *
 * @return Invoker
 */
function xwp_hook_invoker(): Invoker {
    return Invoker::instance();
}
