<?php //phpcs:disable Generic.NamingConventions.UpperCaseConstantName.ClassConstantNotUpperCase
/**
 * Context class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

use Automattic\Jetpack\Constants;
use XWP\DI\Interfaces\Has_Context;

/**
 * Determines execution context.
 *
 * @since 1.0.0
 * @internal Runtime context helper used by hook dispatch.
 */
final class XWP_Context {
    /**
     * Frontend context.
     */
    public const Frontend = Has_Context::CTX_FRONTEND;

    /**
     * Admin context.
     */
    public const Admin = Has_Context::CTX_ADMIN;

    /**
     * AJAX context.
     */
    public const Ajax = Has_Context::CTX_AJAX;

    /**
     * Cron context.
     */
    public const Cron = Has_Context::CTX_CRON;

    /**
     * REST API context.
     */
    public const REST = Has_Context::CTX_REST;

    /**
     * WP CLI context.
     */
    public const CLI = Has_Context::CTX_CLI;

    /**
     * Global context.
     */
    public const Global = Has_Context::CTX_GLOBAL;

    /**
     * Current context.
     *
     * @var int
     */
    private static int $current;

    /**
     * Get the current context.
     *
     * @return int
     */
    public static function get(): int {
        return self::$current ??= match ( true ) {
            self::admin()    => self::Admin,
            self::ajax()     => self::Ajax,
            self::cron()     => self::Cron,
            self::rest()     => self::REST,
            self::cli()      => self::CLI,
            self::frontend() => self::Frontend,
            default          => self::Frontend,
        };
    }

    /**
     * Get the current context as a string.
     *
     * @return string
     */
    public static function show(): string {
        return match ( self::get() ) {
            self::Admin    => 'Admin',
            self::Ajax     => 'Ajax',
            self::Cron     => 'Cron',
            self::REST     => 'REST',
            self::CLI      => 'CLI',
            self::Frontend => 'Frontend',
            default        => 'Frontend',
        };
    }

    /**
     * Check if the context is valid.
     *
     * @param  int $context The context to check.
     * @return bool
     */
    public static function validate( int $context ): bool {
        return 0 !== ( self::get() & $context );
    }

    /**
     * Check if the request is a frontend request.
     *
     * @return bool
     */
    public static function frontend(): bool {
        return ! self::admin() && ! self::cron() && ! self::rest() && ! self::cli();
    }

    /**
     * Check if the request is an admin request.
     *
     * @return bool
     */
    public static function admin(): bool {
        return \is_admin() && ! self::ajax();
    }

    /**
     * Check if the request is an admin request for a specific page.
     *
     * @param  string      $page The page to check.
     * @param  string|null $type The post type to check.
     * @return bool
     */
    public static function admin_page( string $page, ?string $type = null ): bool {
        if ( ! self::admin() ) {
            return false;
        }

        $pagenow = $GLOBALS['pagenow'] ?? '';
        $typenow = $GLOBALS['typenow'] ?? '';

        return $pagenow === $page && ( ! $type || ( $typenow === $type ) );
    }

    /**
     * Check if the request is an AJAX request.
     *
     * @return bool
     */
    public static function ajax(): bool {
        return Constants::is_true( 'DOING_AJAX' );
    }

    /**
     * Check if the request is an AJAX request for a specific action.
     *
     * @param  string $action The action to check.
     * @return bool
     */
    public static function ajax_action( string $action ): bool {
        return self::ajax() && \xwp_fetch_req_var( 'action', '' ) === $action;
    }

    /**
     * Check if the request is a cron request.
     *
     * @return bool
     */
    public static function cron(): bool {
        return Constants::is_true( 'DOING_CRON' );
    }

    /**
     * Check if the request is a REST request.
     *
     * @return bool
     */
    public static function rest(): bool {
        $prefix = \trailingslashit( \rest_get_url_prefix() );

        return false !== \strpos( \xwp_fetch_server_var( 'REQUEST_URI', '' ), $prefix );
    }

    /**
     * Check if the request is a CLI request.
     *
     * @return bool
     */
    public static function cli(): bool {
        return Constants::is_true( 'WP_CLI' );
    }
}
