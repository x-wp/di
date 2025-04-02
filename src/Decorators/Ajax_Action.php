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
     * @var null|string
     */
    protected ?string $prefix;

    /**
     * Nonce query var.
     *
     * @var bool|string|array<string,string>
     */
    protected bool|string|array $nonce;

    /**
     * Capability required to perform the action.
     *
     * @var null|string|array<string,string|array<int,string>>
     */
    protected null|string|array $cap;

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
     * @var 'GET'|'POST'|'REQ'
     */
    protected string $verb;

    /**
     * Variable fetch callback.
     *
     * @var Closure(string, ?string=): mixed
     */
    protected Closure $getter;

    /**
     * Constructor.
     *
     * @param string                                             $action      Ajax action name.
     * @param null|string                                        $prefix      Prefix for the action name.
     * @param bool                                               $public      Whether the action is public or not.
     * @param 'GET'|'POST'|'REQ'                                 $method      Method to fetch the variable. GET, POST, or REQ.
     * @param bool|string|array<string,string>                   $nonce       Nonce query var, or false to disable nonce check, or query var => action keypair.
     * @param null|string|array<string,string|array<int,string>> $cap         Capability required to perform the action.
     * @param array<string,mixed>                                $vars        Variables to fetch.
     * @param array<int,mixed>                                   $params      Parameters to pass to the callback. Will be resolved by the container.
     * @param int                                                $priority    Hook priority.
     * @param bool                                               $debug       Debug this hook.
     * @param bool                                               $trace       Trace this hook.
     * @param mixed                                              ...$depr     Deprecated arguments.
     */
    public function __construct(
        string $action,
        ?string $prefix = null,
        bool $public = true,
        string $method = self::AJAX_REQ,
        bool|string|array $nonce = false,
        null|string|array $cap = null,
        array $vars = array(),
        array $params = array(),
        int $priority = 10,
        bool $debug = false,
        bool $trace = false,
        mixed ...$depr,
    ) {
        $this->action = $action;
        $this->prefix = $prefix;
        $this->nonce  = $nonce;
        $this->cap    = $cap;
        $this->vars   = $vars;
        $this->hooks  = $public ? array( 'wp_ajax_nopriv', 'wp_ajax' ) : array( 'wp_ajax' );
        $this->verb   = $method;
        $this->getter = $this->getter_cb( $method );

        parent::__construct(
            tag: '%s_%s_%s',
            priority:$priority,
            context: self::CTX_AJAX,
            modifiers: false,
            invoke: self::INV_PROXIED,
            args: 0,
            params: $params,
            debug: $debug,
            trace: $trace,
            depr: $depr[0] ?? $depr,
        );
    }

    /**
     * Get the modifiers for the hook.
     *
     * @param  null|string $hook Optional hook name.
     * @return array<int,string>
     */
    public function get_modifiers( ?string $hook = null ): array {
        return array(
            $hook ?? \next( $this->hooks ),
            $this->get_prefix(),
            $this->action,
        );
    }

    public function get_data(): array {
        return \array_merge(
            parent::get_data(),
            array(
                'args' => array(
                    'action'   => $this->action,
                    'cap'      => $this->cap,
                    'method'   => $this->verb,
                    'nonce'    => $this->nonce,
                    'prefix'   => $this->prefix,
                    'priority' => $this->priority,
                    'public'   => \in_array( 'wp_ajax_nopriv', $this->hooks, true ),
                    'vars'     => $this->vars,
                ),
            ),
        );
    }

    /**
     * Check if the action can be loaded.
     *
     * @return bool
     */
    public function can_load(): bool {
        return parent::can_load() && $this->handler->is_loaded();
    }

    protected function get_prefix(): string {
        return $this->prefix ?? $this->get_handler()->get_prefix();
    }

    protected function resolve_tag( ?string $tag, array|string|bool $modifiers ): string {
        return \str_replace( '__', '_', parent::resolve_tag( $tag, $modifiers ) );
    }

    /**
     * Get the variable fetch callback.
     *
     * @param  'GET'|'POST'|'REQ' $method Method to fetch the variable.
     * @return Closure(string, mixed=): mixed
     */
    protected function getter_cb( string $method ): Closure {
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
            parent::load_hook( $this->resolve_tag( $this->tag, $this->get_modifiers( $hook ) ) );
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
        $methods = array( "{$this->action}_{$type}_guard", "{$type}_guard", "{$this->action}_guard", 'unverified_call', 'invalid_call' );

        foreach ( $methods as $method ) {
            if ( ! \method_exists( $this->handler->classname, $method ) ) {
                continue;
            }

            $this->container->call( array( $this->handler->classname, $method ) );
            exit;
        }

        \wp_die( \esc_html( $type ) );
    }

    private function nonce_check(): bool {
        [ $arg, $action ] = $this->get_nonce_args();

        return \check_ajax_referer( $action, $arg, false );
    }

    private function cap_check(): bool {
        if ( \is_string( $this->cap ) ) {
            return \current_user_can( $this->cap );
        }

        foreach ( $this->cap as $cap => $vars ) {
            if ( ! \current_user_can( $cap, ...\array_map( $this->getter, \xwp_str_to_arr( $vars ) ) ) ) {
                return false;
            }
        }

        return true;
    }

    /**
     * Get the nonce arguments.
     *
     * @return array{0: string|false, 1: string}
     */
    private function get_nonce_args(): array {
        $query_arg = match ( true ) {
            \is_array( $this->nonce ) => \key( $this->nonce ),
            \is_string( $this->nonce ) => $this->nonce,
            default => false,
        };
        $action = \is_array( $this->nonce )
            ? \current( $this->nonce )
            : "{$this->prefix}_{$this->action}";

        return array( $query_arg, $action );
    }
}
