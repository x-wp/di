<?php //phpcs:disable Squiz.Commenting, Universal.NamingConventions.NoReservedKeywordParameterNames.publicFound
/**
 * Ajax_Action decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;

/**
 * Ajax action decorator.
 *
 * @template T of object
 * @template H of Ajax_Handler<T>
 * @extends Action<T,H>
 */
#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Ajax_Action extends Action {
    public const AJAX_GET = 'GET';

    public const AJAX_POST = 'POST';

    public const AJAX_REQ = 'REQ';

    /**
     * Action name
     *
     * @var string
     */
    protected string $action;

    /**
     * Prefix for the action name.
     *
     * @var string
     */
    protected string $prefix;

    /**
     * Ajax method.
     *
     * @var 'GET'|'POST'|'REQ'
     */
    protected string $method;

    /**
     * Nonce query var.
     *
     * @var bool|string
     */
    protected bool|string $nonce;

    /**
     * Capability required to perform the action.
     *
     * @var string
     */
    protected ?string $cap;

    /**
     * Variables to fetch.
     *
     * @var array<string,mixed>
     */
    protected array $vars;

    /**
     * Ajax hooks.
     *
     * Can contain private/public ajax hook, or both.
     *
     * @var array<int,string>
     */
    protected array $hooks;

    /**
     * Variable getter function
     *
     * @var Closure(string, string): string
     */
    protected Closure $getter;

    /**
     * Constructor.
     *
     * @param string              $action   Ajax action name.
     * @param string              $prefix   Prefix for the action name.
     * @param bool                $public   Whether the action is public or not.
     * @param 'GET'|'POST'|'REQ'  $method   Method to fetch the variable. GET, POST, or REQ.
     * @param bool|string         $nonce    String defines the query var for nonce, true checks the default vars, false disables nonce check.
     * @param string              $cap      Capability required to perform the action.
     * @param array<string,mixed> $vars     Variables to fetch.
     * @param array<int,mixed>    $params   Parameters to pass to the callback. Will be resolved by the container.
     * @param int                 $priority Hook priority.
     */
    public function __construct(
        string $action,
        string $prefix,
        bool $public = true,
        string $method = self::AJAX_REQ,
        bool|string $nonce = false,
        ?string $cap = null,
        array $vars = array(),
        array $params = array(),
        int $priority = 10,
    ) {
        $this->action = $action;
        $this->prefix = $prefix;
        $this->method = $method;
        $this->nonce  = $nonce;
        $this->cap    = $cap;
        $this->vars   = $vars;
        $this->hooks  = $public ? array( 'wp_ajax_nopriv', 'wp_ajax' ) : array( 'wp_ajax' );
        $this->getter = Closure::fromCallable( $this->get_fetch_cb( $method ) );

        parent::__construct(
            tag: '%s_%s_%s',
            priority:$priority,
            context: self::CTX_AJAX,
            conditional: '__return_true',
            modifiers: array( '%s', \rtrim( $prefix, '_' ), $action ),
            invoke: self::INV_PROXIED,
            args: 0,
            params: $params,
        );
    }

    /**
     * Check if the action can be loaded.
     *
     * @return bool
     */
    public function can_load(): bool {
        return parent::can_load() && $this->handler->loaded;
    }

    /**
     * Get the variable fetch callback.
     *
     * @param  'GET'|'POST'|'REQ' $method Method to fetch the variable.
     * @return Closure(string, mixed): mixed
     */
    protected function get_fetch_cb( string $method ): Closure {
        $cb = match ( $method ) {
            'GET'  => 'xwp_fetch_get_var',
            'POST' => 'xwp_fetch_post_var',
            'REQ'  => 'xwp_fetch_req_var',
        };

        return Closure::fromCallable( $cb );
    }

    /**
     * Loads the hook.
     *
     * @param  ?string $tag Optional hook tag.
     * @return bool
     */
    protected function load_hook( ?string $tag = null ): bool {
        foreach ( $this->hooks as $hook ) {
            parent::load_hook( $this->define_tag( $this->tag, array( $hook ) ) );
        }

        return true;
    }

	/**
     * Fire the hook.
     *
     * @param  mixed ...$args Arguments to pass to the callback.
     * @return mixed
     */
    protected function fire_hook( mixed ...$args ): mixed {
        if ( $this->nonce && ! $this->nonce_check() ) {
            $this->fire_guard_cb( 'nonce' );
        }

        if ( $this->cap && ! $this->cap_check() ) {
            $this->fire_guard_cb( 'cap' );
        }

        return parent::fire_hook( ...$args );
    }

    /**
     * Get the arguments to pass to the callback.
     *
     * @param  array<int, mixed> $args Existing arguments.
     * @return array<int, mixed>
     */
    protected function get_cb_args( array $args ): array {
        if ( isset( $this->vars['body'] ) ) {
            $args[] = \json_decode( \file_get_contents( 'php://input' ), true ) ?? array();
        }

        foreach ( \xwp_array_diff_assoc( $this->vars, 'body' ) as $k => $d ) {
            $args[] = ( $this->getter )( $k, $d );
        }

        return parent::get_cb_args( $args );
    }

    private function fire_guard_cb( string $type ): void {
        $methods = array( "{$this->action}_guard", 'unverified_call', 'invalid_call' );

        foreach ( $methods as $method ) {
            if ( ! \method_exists( $this->handler->classname, $method ) ) {
                continue;
            }

            $this->container->call( array( $this->handler->classname, $method ), array( $type ) );
            return;
        }

        \wp_die( \esc_html( $type ) );
    }

    private function nonce_check(): bool {
        $query_arg = \is_string( $this->nonce ) ? $this->nonce : false;

        return \check_ajax_referer( "{$this->prefix}_{$this->action}", $query_arg, false );
    }

    private function cap_check(): bool {
        return \current_user_can( $this->cap );
    }
}
