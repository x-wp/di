<?php //phpcs:disable Squiz.Commenting, Universal.NamingConventions.NoReservedKeywordParameterNames.publicFound
/**
 * Ajax_Action legacy compatibility methods.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Compatibility;

use Closure;

/**
 * Preserves inherited wiring and override seams for existing decorators.
 *
 * @template T of object
 * @template H of \XWP\DI\Interfaces\Can_Handle<T>
 * @internal New runtime extensions belong in XWP\DI\Hook.
 */
trait Ajax_Action_Methods {
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
     * Get the modifiers for the hook.
     *
     * @param  null|string $hook Optional hook name.
     * @return array<int,string>
     */
    public function get_modifiers( ?string $hook = null ): array {
        if ( $this->runtime instanceof \XWP\DI\Hook\Ajax_Callback ) {
            /**
             * Specialized runtime owner.
             *
             * @var \XWP\DI\Hook\Ajax_Callback<T,H> $runtime
             */
            $runtime = $this->runtime;

            return $runtime->get_modifiers( $hook );
        }

        return array(
            $hook ?? \next( $this->hooks ),
            $this->get_prefix(),
            $this->action,
        );
    }



    /**
     * Check if the action can be loaded.
     *
     * @return bool
     */
    /**
     * Can the AJAX action be loaded?
     *
     * @internal Runtime dispatch detail.
     *
     * @return bool
     */
    public function can_load(): bool {
        if ( $this->runtime ) {
            return $this->runtime->can_load();
        }

        return parent::can_load() && $this->handler->loaded;
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
    // Signature is the inherited custom-callback override seam.
    // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
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
        [ $action, $arg ] = $this->nonce;

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
     * Parse the nonce parameter.
     *
     * @param  bool|string|array{0:string,1:string}|array<string,string> $nonce Nonce parameter.
     * @return array{0?:string,1?:string}
     */
    private function parse_nonce( bool|string|array $nonce ): array {
        if ( ! $nonce ) {
            return array();
        }

        if ( ! \is_array( $nonce ) ) {
            return array(
                "{$this->prefix}_{$this->action}",
                \is_string( $nonce ) ? $nonce : false,
            );
        }

        return ! \array_is_list( $nonce )
            ? array( \current( $nonce ), \key( $nonce ) )
            : $nonce;
    }
}
