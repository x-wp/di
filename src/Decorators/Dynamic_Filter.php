<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Dynamic_Filter class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use Reflector;

/**
 * Dynamic filter decorator
 *
 * @template T of object
 * @template H of Ajax_Handler<T>
 * @extends Filter<T,H>
 */
#[\Attribute( \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE )]
class Dynamic_Filter extends Filter {
    /**
     * Variables to fetch.
     *
     * @var array<string,string>
     */
    protected array $vars;

    /**
     * Raw variables for substitution.
     *
     * @var string|array<string>|Closure():array<string>
     */
    protected Closure|string|array $raw_vars;

    /**
     * Extra variables to pass to the callback.
     *
     * @var array<string,string>
     */
    protected array $extra;

    /**
     * Constructor.
     *
     * @param string                                        $tag      Hook tag.
     * @param string|array<string>|callable():array<string> $vars     Variables to mix into the tag.
     * @param int                                           $context  Hook context.
     * @param Closure|string|int|array{class-string,string} $priority Hook priority.
     * @param int|null                                      $args        The number of arguments to pass to the callback.
     * @param array<int,string>                             $params   The parameters to pass to the callback.
     */
    public function __construct(
        string $tag,
        callable|array|string $vars,
        int $context = self::CTX_GLOBAL,
        Closure|array|int|string $priority = 10,
        ?int $args = null,
        array $params = array(),
    ) {
        $this->raw_vars = $vars;

        parent::__construct(
            tag: $tag,
            priority: $priority,
            context: $context,
            invoke: self::INV_PROXIED,
            args: $args,
            params: $params,
        );
    }

    public function get_data(): array {
        $data = parent::get_data();

        $data['args'] = array(
            'args'     => $this->args,
            'context'  => $this->context,
            'params'   => $this->params,
            'priority' => $this->prio,
            'tag'      => $this->tag,
            'vars'     => $this->raw_vars,
        );

        return $data;
    }

    /**
     * Process variables.
     *
     * @param  string|array<string>|callable():array<string> $vars Variables to mix into the tag.
     * @return array<int,string>|array<string,string>
     */
    private function process_vars( string|callable|array $vars ): array {
        if ( \is_callable( $vars ) ) {
            return $vars();
        }

        if ( \is_string( $vars ) && $this->container->has( $vars ) ) {
            return $this->container->get( $vars );
        }

        return $vars;
    }

    /**
     * Parse variables.
     *
     * @param  string|array<string>|callable():array<string> $vars Variables to mix into the tag.
     * @return array<string,string>
     */
    protected function parse_vars( string|callable|array $vars ): array {
        $parsed = array();

        foreach ( $this->process_vars( $vars ) as $key => $val ) {
            $key = \is_int( $key ) ? $val : $key;

            $parsed[ $key ] = $val;
        }

        return $parsed;
    }

    public function with_reflector( Reflector $r ): static {
        $this->args ??= $r->getNumberOfParameters() - 1;

        return parent::with_reflector( $r );
    }

    public function load_hook( ?string $tag = null ): bool {
        $res = true;

        foreach ( $this->parse_vars( $this->raw_vars ) as $var => $param ) {
            $tag = $this->resolve_tag( $this->tag, array( $var ) );

            $this->extra[ $tag ] = $param;

            $res = $res && parent::load_hook( $tag );
        }

        return $res;
    }

    protected function get_cb_args( array $args ): array {
        $args = parent::get_cb_args( $args );

        $args[] = $this->extra[ $this->current() ];

        return $args;
    }
}
