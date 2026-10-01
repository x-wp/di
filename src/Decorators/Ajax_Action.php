<?php //phpcs:disable Squiz.Commenting, Universal.NamingConventions.NoReservedKeywordParameterNames.publicFound
/**
 * Ajax_Action decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use XWP\DI\Interfaces\Can_Handle;

/**
 * Ajax action decorator.
 *
 * @template T of object
 * @template H of Can_Handle<T>
 * @extends Action<T,H>
 */
#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Ajax_Action extends Action {
    public const AJAX_GET = 'GET';

    public const AJAX_POST = 'POST';

    public const AJAX_REQ = 'REQ';

    /**
     * Declared action.
     *
     * @var string
     */
    protected string $action;

    /**
     * Declared prefix.
     *
     * @var string|null
     */
    protected ?string $prefix;

    /**
     * Declared nonce.
     *
     * @var array{0?:string,1?:string|false}
     */
    protected array $nonce;

    /**
     * Declared cap.
     *
     * @var null|string|array<string,string|array<int,string>>
     */
    protected null|string|array $cap;

    /**
     * Declared vars.
     *
     * @var array<string,mixed>
     */
    protected array $vars;

    /**
     * Declared public.
     *
     * @var bool
     */
    protected bool $public;

    /**
     * Declared verb.
     *
     * @var 'GET'|'POST'|'REQ'
     */
    protected string $verb;

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
        $this->public = $public;
        $this->verb   = $method;

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
     * Export unbound attribute metadata.
     *
     * @internal Discovery metadata detail.
     * @return array<string,mixed>
     */
    public function get_declaration(): array {
        return array(
            'action'      => $this->action,
            'cap'         => $this->cap,
            'conditional' => $this->conditional,
            'method'      => $this->verb,
            'nonce'       => $this->nonce,
            'params'      => $this->params,
            'prefix'      => $this->prefix,
            'priority'    => $this->prio,
            'public'      => $this->public,
            'vars'        => $this->vars,
        );
    }

    /**
     * Normalize the nonce declaration without fetching request data.
     *
     * @param bool|string|array<string,string>|array{0:string,1:string|false} $nonce Nonce declaration.
     * @return array{0?:string,1?:string|false}
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
