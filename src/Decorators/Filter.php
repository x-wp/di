<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
/**
 * Filter decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use DI\Container;
use ReflectionMethod;
use Reflector;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;

/**
 * Filter hook decorator.
 *
 * @template T of object
 * @template H of Can_Handle<T>
 * @extends Hook<T, ReflectionMethod>
 * @implements Can_Invoke<T,H>
 */
#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Filter extends Hook implements Can_Invoke {
    /**
     * The handler.
     *
     * @var H
     */
    protected Can_Handle $handler;

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
     * Constructor.
     *
     * @param string                                         $tag         Hook tag.
     * @param Closure|string|int|array{class-string,string}  $priority    Hook priority.
     * @param int                                            $context     Hook context.
     * @param null|Closure|string|array{class-string,string} $conditional Conditional callback.
     * @param array<int,string>|string|false                 $modifiers   Values to replace in the tag name.
     * @param int                                            $invoke      The invocation strategy.
     * @param int|null                                       $args        The number of arguments to pass to the callback.
     * @param array<string>                                  $params      The parameters to pass to the callback.
     */
    public function __construct(
        string $tag,
        Closure|array|int|string $priority = 10,
        int $context = self::CTX_GLOBAL,
        array|string|\Closure|null $conditional = null,
        string|array|bool $modifiers = false,
        protected int $invoke = self::INV_STANDARD,
        protected ?int $args = null,
        protected array $params = array(),
    ) {
        parent::__construct( $tag, $priority, $context, $conditional, $modifiers );
    }

    protected function get_container(): Container {
        return $this->handler->container;
    }

    /**
     * Get the type of hook.
     *
     * @return string
     */
    protected function get_type(): string {
        return 'filter';
    }

    /**
     * Get the current hook.
     *
     * @return string
     */
    protected function current(): string {
        $cb = "current_{$this->get_type()}";

        return $cb();
    }

    public function with_reflector( Reflector $r ): static {
        $this->args   ??= $r->getNumberOfParameters();
        $this->method ??= $r->getName();

        return parent::with_reflector( $r );
    }

    /**
     * Set the handler.
     *
     * @param  H $handler The handler.
     * @return static
     */
    public function with_handler( Can_Handle $handler ): static {
        $this->handler = $handler;

        if ( $handler->is_lazy() ) {
            $this->invoke = ( $this->invoke | self::INV_PROXIED ) & ~self::INV_STANDARD;
        }

        return $this;
    }

    public function with_target( string $method ): static {
        $this->method = $method;

        return $this;
    }

    public function can_load(): bool {
        return parent::can_load() && ( $this->handler->is_lazy() || $this->handler->loaded );
    }

    protected function init_handler( string $strategy ): bool {
        if ( $this->handler->loaded ) {
            return true;
        }

        if ( $strategy !== $this->handler->strategy ) {
            return $this->can_load();
        }

        \do_action( "{$this->handler->id}_{$strategy}_init", $this->handler );

        return $this->handler->loaded;
    }

    protected function cb_valid( int $current ): bool {
        return 0 !== ( $this->invoke & $current );
    }

    /**
     * Get the target for the hook.
     *
     * @return array{0:object,1:string}
     */
    protected function get_target(): array {
        return $this->cb_valid( self::INV_STANDARD )
            ? array( $this->handler->target, $this->method )
            : array( $this, 'invoke' );
    }

    public function load(): bool {
        if ( $this->loaded ) {
            return true;
        }

        if ( ! $this->init_handler( $this->handler::INIT_ON_DEMAND ) ) {
            return false;
        }

        $this->loaded = $this->load_hook();

        return $this->loaded;
    }

    /**
     * Loads the hook.
     *
     * @param  ?string $tag Optional hook tag.
     * @return bool
     */
    protected function load_hook( ?string $tag = null ): bool {
        return ( "add_{$this->get_type()}" )(
            $tag ?? $this->tag,
            $this->target,
            $this->priority,
            $this->args,
        );
    }

    public function invoke( mixed ...$args ): mixed {
        if (
            ! $this->init_handler( $this->handler::INIT_JUST_IN_TIME ) ||
            ! parent::can_load() ||
            ( $this->cb_valid( self::INV_ONCE ) && $this->fired ) ||
            ( $this->cb_valid( self::INV_LOOPED ) && $this->firing )
        ) {
            return $args[0] ?? null;
        }

        try {
            return $this->fire_hook( ...$args );
        } catch ( \Throwable $e ) {
            return $this->handle_exception( $e, $args[0] ?? null );
        } finally {
            $this->firing = false;
            ++$this->fired;
        }
    }

    /**
     * Fire the hook.
     *
     * @param  mixed ...$args Arguments to pass to the callback.
     * @return mixed
     */
    protected function fire_hook( mixed ...$args ): mixed {
        $this->firing = true;

        return $this->container->call(
            array( $this->handler->classname, $this->method ),
            $this->get_cb_args( $args ),
        );
    }

    /**
     * Get the arguments to pass to the callback.
     *
     * @param  array<int, mixed> $args Existing arguments.
     * @return array<int, mixed>
     */
    protected function get_cb_args( array $args ): array {
        if ( $this->params ) {
            foreach ( $this->params as $param ) {
                $args[] = $this->get_cb_arg( $param );
            }
        }

        return $args;
    }

    protected function get_cb_arg( string $param ): mixed {
        if ( '!self.hook' === $param ) {
            return $this;
        }

        if ( '!self.handler' === $param ) {
            return $this->handler;
        }

        return ! \str_starts_with( $param, '!value:' )
            ? $this->container->get( $param )
            : \str_replace( '!value:', '', $param );
    }

    /**
     * Handle an exception.
     *
     * @template TExc of \Throwable
     * @param  TExc  $e The exception.
     * @param  mixed $v The value to return.
     * @return mixed
     *
     * @throws TExc If the handler is not set to safely invoke.
     */
    protected function handle_exception( \Throwable $e, mixed $v ): mixed {
        if ( ! $this->cb_valid( self::INV_SAFELY ) ) {
            throw $e;
        }

        //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
        \error_log(
            \sprintf(
                'Error during %s %s for handler %s. %s',
                \esc_html( $this->get_type() ),
                \esc_html( $this->tag ),
                \esc_html( $this->handler->classname ),
                \esc_html( $e->getMessage() ),
            ),
        );

        return $v;
    }

    protected function get_id(): string {
        return $this->handler->id . '_' . $this->method;
    }
}
