<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh, SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
/**
 * Filter decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Core;

use Closure;
use Psr\Log\LoggerInterface;
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
     * @param string                         $tag         Hook tag.
     * @param int                            $priority    Hook priority.
     * @param Container                      $container   The container.
     * @param Can_Handle<T>                  $handler     The handler.
     * @param class-string<T>                $classname   The class name.
     * @param string                         $method      The method to call.
     * @param int                            $context     Hook context.
     * @param array<int,string>|string|false $modifiers   Values to replace in the tag name.
     * @param int                            $invoke      The invocation strategy.
     * @param int|null                       $args        The number of arguments to pass to the callback.
     * @param array<string>                  $params      The parameters to pass to the callback.
     * @param bool                           $debug       Debug this hook.
     * @param bool                           $trace       Trace this hook.
     * @param mixed                          ...$depr     Compatibility arguments.
     */
    public function __construct(
        string $tag,
        int $priority,
        Container $container,
        Can_Handle $handler,
        string $classname,
        string $method,
        int $context = self::CTX_GLOBAL,
        string|array|bool $modifiers = false,
        protected int $invoke = self::INV_STANDARD,
        protected ?int $args = null,
        protected array $params = array(),
        bool $debug = false,
        bool $trace = false,
    ) {
        parent::__construct(
            tag:$tag,
            priority:$priority,
            container: $container,
            classname: $classname,
            context:$context,
            modifiers: $modifiers,
            debug: $debug,
            trace: $trace,
        );

        $this->handler = $handler;
        $this->method  = $method;
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
        return $this->handler;
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

        $data['params']['method']  = $this->method;
        $data['params']['handler'] = $this->get_handler()->get_token();

        return $this->merge_compat_args( $data, 'depr' );
    }

    public function get_container(): Container {
        return $this->container ??= $this->get_handler()->get_container();
    }

    public function get_logger(): LoggerInterface {
        return $this->logger ??= $this->get_handler()->get_logger();
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
        if ( $this->is_loaded() ) {
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

        $this->get_logger()->error(
            \sprintf( 'Error executing %s "%s": %s', $this->get_type(), $this->get_tag(), $e->getMessage() ),
            array(
                'handler' => $this->get_classname(),
                'method'  => $this->get_method(),
                'tag'     => $this->get_tag(),
            ),
        );

        return $v;
    }

    protected function get_id_base(): string {
        return 'Callback\\' . $this->get_classname();
    }

    protected function get_id_suffix(): string {
        return "{$this->get_method()}[{$this->tag}:{$this->get_priority()}]";
    }

    protected function get_constructor_args(): array {
        return \array_merge(
            parent::get_constructor_args(),
            array( 'invoke', 'args', 'params' ),
        );
    }
}
