<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * AJAX callback runtime.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use Closure;
use XWP\DI\Container;
use XWP\DI\Definition\CallbackDefinition;

/**
 * Owns AJAX registration, request arguments, and guards.
 *
 * @template T of object
 * @template H of \XWP\DI\Decorators\Ajax_Handler<T>
 * @extends Callback<T,H>
 * @internal
 */
class Ajax_Callback extends Callback {
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
     * @var array{0?:string,1?:string}
     */
    protected array $nonce;

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
     * @param CallbackDefinition $definition Callback metadata.
     * @param Container          $container Runtime container.
     */
    public function __construct( CallbackDefinition $definition, Container $container ) {
        parent::__construct( $definition, $container );
        $options      = $definition->get_options();
        $this->action = $options['action'];
        $this->prefix = $options['prefix'];
        $this->nonce  = $options['nonce'];
        $this->cap    = $options['cap'];
        $this->vars   = $options['vars'];
        $this->hooks  = $options['public'] ? array( 'wp_ajax_nopriv', 'wp_ajax' ) : array( 'wp_ajax' );
        $this->verb   = $options['method'];
        $this->getter = $this->getter_cb( $this->verb );
    }

    public function can_load(): bool {
        return parent::can_load() && $this->get_handler()->is_loaded();
    }

    /**
     * Get the modifiers for the hook.
     *
     * @param  null|string $hook Optional hook name.
     * @return array<int,string>
     */
    public function get_modifiers( ?string $hook = null ): array {
        return array(
            $hook ?? 'wp_ajax',
            $this->get_prefix(),
            $this->action,
        );
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
            if ( ! \method_exists( $this->get_classname(), $method ) ) {
                continue;
            }

            $this->container->call( array( $this->get_handler()->get_target(), $method ) );
            exit;
        }

        \wp_die( \esc_html( $type ) );
    }

    private function nonce_check(): bool {
        [ $action, $arg ] = $this->nonce;

        return false !== \check_ajax_referer( $action, $arg, false );
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
}
