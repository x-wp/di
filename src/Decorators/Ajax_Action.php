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
    /**
     * Legacy custom-subclass and typed-view compatibility.
     *
     * @use \XWP\DI\Compatibility\Ajax_Action_Methods<T,H>
     */
    use \XWP\DI\Compatibility\Ajax_Action_Methods;

    public const AJAX_GET = 'GET';

    public const AJAX_POST = 'POST';

    public const AJAX_REQ = 'REQ';

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
     * @param null|Closure|string|array{0:class-string,1:string} $conditional Conditional callback.
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
        null|Closure|string|array $conditional = null,
    ) {
        $this->action = $action;
        $this->prefix = $prefix;
        $this->nonce  = $this->parse_nonce( $nonce );
        $this->cap    = $cap;
        $this->vars   = $vars;
        $this->hooks  = $public ? array( 'wp_ajax_nopriv', 'wp_ajax' ) : array( 'wp_ajax' );
        $this->verb   = $method;
        $this->getter = $this->getter_cb( $method );

        parent::__construct(
            tag: '%s_%s_%s',
            priority:$priority,
            context: self::CTX_AJAX,
            conditional: $conditional,
            modifiers: false,
            invoke: self::INV_PROXIED,
            args: 0,
            params: $params,
        );
    }

    /**
     * Get compiler data for this AJAX action.
     *
     * @internal Hook parser/compiler detail.
     *
     * @return array<string,mixed>
     */
    public function get_data(): array {
        return \array_merge(
            parent::get_data(),
            array(
                'args' => array(
                    'action'      => $this->action,
                    'cap'         => $this->cap,
                    'conditional' => $this->conditional,
                    'method'      => $this->verb,
                    'nonce'       => $this->nonce,
                    'params'      => $this->params,
                    'prefix'      => $this->prefix,
                    'priority'    => $this->prio,
                    'public'      => \in_array( 'wp_ajax_nopriv', $this->hooks, true ),
                    'vars'        => $this->vars,
                ),
            ),
        );
    }
}
