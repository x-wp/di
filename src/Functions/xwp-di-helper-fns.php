<?php
/**
 * Hook invoker functions.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

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
