<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh, SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
/**
 * Filter decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use ReflectionMethod;
use Reflector;
use XWP\DI\Container;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;

/**
 * Filter hook decorator.
 *
 * @template T of object
 * @template H of Can_Handle<T>
 * @extends Hook<T,ReflectionMethod>
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
     * @param string                                               $tag         Hook tag.
     * @param Closure|string|int|array{0: class-string,1: string}  $priority    Hook priority.
     * @param int                                                  $context     Hook context.
     * @param null|Closure|string|array{0: class-string,1: string} $conditional Conditional callback.
     * @param array<int,string>|string|false                       $modifiers   Values to replace in the tag name.
     * @param int                                                  $invoke      The invocation strategy.
     * @param int|null                                             $args        The number of arguments to pass to the callback.
     * @param array<string>                                        $params      The parameters to pass to the callback.
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

    /**
     * Set the handler.
     *
     * @param  H $handler The handler.
     * @return static
     */
    public function with_handler( Can_Handle $handler ): static {
        $this->handler   = $handler;
        $this->classname = $handler->get_classname();

        if ( $handler->is_lazy() ) {
            $this->invoke = ( $this->invoke | self::INV_PROXIED ) & ~self::INV_STANDARD;
        }

        return $this;
    }

    public function with_method( string $method ): static {
        $this->method = $method;

        return $this;
    }

    public function get_handler(): Can_Handle {
        if ( isset( $this->handler ) ) {
            return $this->handler;
        }

        if ( ! isset( $this->container ) || ! isset( $this->classname ) ) {
            throw new \RuntimeException( 'Cannot get handler without a container or classname.' );
        }

        $handler = $this->get_container()->get( 'Handler-' . $this->get_classname() );

        return $this->with_handler( $handler )->get_handler();
    }

    public function get_method(): string {
        return $this->method;
    }

    public function get_reflector(): Reflector {
        if ( isset( $this->reflector ) ) {
            return $this->reflector;
        }

        return $this
            ->with_reflector( $this->get_handler()->get_reflector()->getMethod( $this->get_method() ) )
            ->get_reflector();
    }

    public function get_num_args(): int {
        return $this->args ??= $this->get_reflector()->getNumberOfParameters();
    }

    public function get_data(): array {
        $data = parent::get_data();

        $data['args']['args']     = $this->args;
        $data['args']['invoke']   = $this->invoke;
        $data['args']['params']   = $this->params;
        $data['params']['method'] = $this->method;

        return $data;
    }

    public function get_container(): Container {
        return $this->container ??= $this->get_handler()->get_container();
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

    public function can_load(): bool {
        return parent::can_load() && ( $this->get_handler()->is_lazy() || $this->get_handler()->is_loaded() );
    }

    protected function init_handler( string $strategy ): bool {
        if ( $this->get_handler()->is_loaded() ) {
            return true;
        }

        if ( $strategy !== $this->get_handler()->get_strategy() ) {
            return $this->can_load();
        }

        \do_action( "{$this->get_handler()->get_token()}_{$strategy}_init", $this->get_handler() );

        return $this->get_handler()->is_loaded();
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
            ? array( $this->get_handler()->get_target(), $this->get_method() )
            : array( $this, 'invoke' );
    }

    public function load(): bool {
        if ( $this->loaded ) {
            return true;
        }

        if ( ! $this->init_handler( Can_Handle::INIT_LAZY ) ) {
            return false;
        }

        $this->loaded      = $this->load_hook();
        $this->init_hook ??= \current_action();

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
            $tag ?? $this->get_tag(),
            $this->get_target(),
            $this->get_priority(),
            $this->get_num_args(),
        );
    }

    public function invoke( mixed ...$args ): mixed {
        if (
            ! $this->init_handler( Can_Handle::INIT_JIT ) ||
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

        return $this->get_container()->call(
            array( $this->get_classname(), $this->get_method() ),
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
        return match ( $param ) {
            '!self.handler' => $this->get_handler(),
            default        => parent::get_cb_arg( $param ),
        };
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
                \esc_html( $this->get_tag() ),
                \esc_html( $this->get_classname() ),
                \esc_html( $e->getMessage() ),
            ),
        );

        return $v;
    }

    protected function get_token_prefix(): string {
        return 'Hook';
    }

    protected function get_token_base(): string {
        return $this->get_classname();
    }

    protected function get_token_suffix(): string {
        return "{$this->get_method()}[{$this->tag}]";
    }
}
