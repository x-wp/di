<?php
/**
 * Hook invoker functions.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;

/**
 * Log a message.
 *
 * @param  string                    $message Message to log.
 * @param string|array<mixed,mixed> $vars Optional variables to log.
 * @access protected
 */
function xwp_log( string $message, string|array $vars = array() ): void {
    if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
        return;
    }

    $vars = (array) $vars;

    $message = match ( true ) {
        array() === $vars => $message,
        str_contains( $message, '%s' ) => vsprintf( $message, $vars ),
        default => $message . ' ' . wp_json_encode( $vars, JSON_PRETTY_PRINT ),
    };

    //phpcs:ignore WordPress.PHP.DevelopmentFunctions
    error_log( $message );
}

/**
 * Register a hook handler for an app.
 *
 * @template TObj of object
 * @param  TObj   $instance Instance to register.
 * @param  string $app App to register the instance with.
 * @return Can_Handle<TObj>
 */
function xwp_create_hook_handler( object $instance, string $app ): Can_Handle {
    return xwp_app( $app )->create_handler( $instance );
}

/**
 * Register a hook handler for an app.
 *
 * @template TObj of object
 * @param  TObj   $instance Instance to register.
 * @param  string $app App to register the instance with.
 * @return Can_Handle<TObj>
 */
function xwp_load_hook_handler( object $instance, string $app ): Can_Handle {
    return xwp_app( $app )->load_handler( $instance );
}

/**
 * Load handler callbacks.
 *
 * @template TObj of object
 *
 * @param  Can_Handle<TObj>                             $handler Handler instance.
 * @param  array<int,Can_Invoke<TObj,Can_Handle<TObj>>> $callbacks Callbacks to load.
 * @return Can_Handle<TObj>
 */
function xwp_load_handler_cbs( Can_Handle $handler, array $callbacks ): Can_Handle {
    return $handler->get_container()->load_callbacks( $handler, $callbacks );
}
/**
 * Register a handler or a module.
 *
 * @template TObj of object
 *
 * @param  Can_Handle<TObj> $handler Handler instance.
 */
function xwp_register_hook_handler( Can_Handle $handler ): void {
    $handler->get_container()->register_handler( $handler->get_classname() );
}
