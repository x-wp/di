<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh, SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
/**
 * Filter decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use Psr\Log\LoggerInterface;
use ReflectionMethod;
use Reflector;
use XWP\DI\Container;
use XWP\DI\Interfaces\Can_Invoke;
use XWP\DI\Interfaces\Decorates_Callback;
use XWP\DI\Interfaces\Decorates_Handler;
use XWP\DI\Utils\Reflection;

/**
 * Filter hook decorator.
 *
 * @template T of object
 * @template H of Decorates_Handler<T>
 * @extends Hook<T,ReflectionMethod>
 * @implements Can_Invoke<T,H>
 */
#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Filter extends Hook implements Can_Invoke, Decorates_Callback {
    /**
     * The handler.
     *
     * @var H
     */
    protected Decorates_Handler $handler;

    /**
     * Class method to call.
     *
     * @var string
     */
    protected string $method;

    /**
     * Number of times the hook has fired.
     *
     * @var int
     */
    protected int $fired = 0;

    /**
     * Flag to indicate if the hook is currently firing.
     *
     * @var bool
     */
    protected bool $firing = false;

    /**
     * Invocation strategy.
     *
     * @var array{
     *   args: null|int|array<int,string>,
     *   catch: bool,
     *   limit: int,
     *   params: array<string>,
     *   proxy: bool,
     *   recursive: bool,
     * }
     */
    protected array $invoke = array();

    /**
     * Constructor.
     *
     * @param string                                              $tag         Hook tag.
     * @param Closure|string|int|array{0: class-string,1: string} $priority    Hook priority.
     * @param int                                                 $context     Hook context.
     * @param array<int,string>|string|false                      $modifiers   Values to replace in the tag name.
     * @param int                                                 $invoke      The invocation strategy.
     * @param int|null                                            $args        The number of arguments to pass to the callback.
     * @param array<string>                                       $params      The parameters to pass to the callback.
     * @param bool                                                $debug       Debug this hook.
     * @param bool                                                $trace       Trace this hook.
     * @param mixed                                               ...$depr     Compatibility arguments.
     */
    public function __construct(
        string $tag,
        Closure|array|int|string $priority = 10,
        int $context = self::CTX_GLOBAL,
        string|array|bool $modifiers = false,
        int $invoke = self::INV_STANDARD,
        ?int $args = null,
        ?array $params = null,
        ?bool $debug = null,
        ?bool $trace = null,
        mixed ...$depr,
    ) {
        $depr         = $depr[0] ?? $depr;
        $this->invoke = array(
            'args'      => $args,
            'catch'     => 0 !== ( $invoke & self::INV_SAFELY ),
            'limit'     => 0 !== ( $invoke & self::INV_ONCE ) ? 1 : 0,
            'params'    => $params ?? array(),
            'proxy'     => 0 !== ( $invoke & self::INV_PROXIED ),
            'recursive' => 0 !== ( $invoke & self::INV_LOOPED ),
        );

        parent::__construct(
            tag:$tag,
            priority:$priority,
            context:$context,
            modifiers: $modifiers,
            debug: $debug,
            trace: $trace,
        );
    }

    /**
     * Set the handler.
     *
     * @param  H $handler The handler.
     * @return static
     */
    public function with_handler( Decorates_Handler $handler ): static {
        $this->handler   = $handler;
        $this->classname = $handler->get_classname();

        return $this;
    }

    public function with_method( string $method ): static {
        $this->method = $method;

        return $this;
    }

    public function get_handler(): Decorates_Handler {
        return $this->handler;
    }

    public function get_method(): string {
        return $this->method;
    }

    public function get_data(): array {
        $data = parent::get_data();

        $data['construct']['handler'] = $this->get_handler()->get_token();

        return $data;
    }

    /**
     * Get the type of hook.
     *
     * @return string
     */
    protected function get_type(): string {
        return 'filter';
    }

    protected function get_token_prefix(): string {
        return 'cb:';
    }

    public function with_reflector( Reflector $r ): static {
        $this->invoke   = $this->resolve_invoke( $r );
        $this->method ??= $r->getName();

        return parent::with_reflector( $r );
    }

    protected function get_token_base(): string {
        return 'Callback\\' . $this->get_classname();
    }

    protected function get_token_suffix(): string {
        $index = \implode( '', (array) $this->priority );

        return "{$this->get_method()}[{$this->tag}:{$index}]";
    }

    protected function get_constructor_args(): array {
        return \array_merge(
            parent::get_constructor_args(),
            array( 'invoke', 'method', 'handler' ),
        );
    }

    protected function resolve_invoke( ReflectionMethod $m ): array {
        $invoke = array();

        foreach ( Reflection::get_decorators( $m, Invocation::class ) as $i ) {
            foreach ( $i->get_args() as $arg => $val ) {
                $invoke[ $arg ] = $val;
            }
        }

        $invoke['args'] ??= $m->getNumberOfParameters();

        return \array_merge( $this->invoke, $invoke );
    }

    protected function is_unconditional(): bool {
        return ! \method_exists( $this->classname, "can_invoke_{$this->method}" );
    }
}
