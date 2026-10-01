<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
/**
 * Callback runtime class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use Automattic\Jetpack\Constants;
use ReflectionMethod;
use XWP\DI\Container;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;
use XWP\DI\Traits\Hook_Invoke_Methods;
use XWP_Context;

/**
 * Owns registration and invocation state for one callback definition.
 *
 * @template T of object
 * @template H of Can_Handle<T>
 *
 * @property-read string $tag    Resolved hook tag.
 * @property-read string $method Handler method.
 * @property-read int    $fired  Completed invocation attempts.
 * @property-read bool   $firing Whether the callback is executing.
 * @property-read array{0:object|null,1:string} $target WordPress callable.
 *
 * @internal Runtime callback implementation; specialized callbacks may extend it.
 */
class Callback {
    /**
     * Shared priority, tag, and condition resolution.
     *
     * @use Hook_Invoke_Methods<T>
     */
    use Hook_Invoke_Methods;

    /**
     * Completed invocation attempts.
     *
     * @var int
     */
    protected int $fired = 0;

    /**
     * Whether the callback is executing.
     *
     * @var bool
     */
    protected bool $firing = false;

    /**
     * Whether registration succeeded.
     *
     * @var bool
     */
    protected bool $loaded = false;

    /**
     * Hook active during registration.
     *
     * @var string
     */
    protected string $init_hook;

    /**
     * Handler metadata, resolved on demand.
     *
     * @var H
     */
    protected Can_Handle $handler;

    /**
     * Effective invocation flags, including the lazy-handler proxy override.
     *
     * @var int
     */
    protected int $invoke;

    /**
     * Raw tag used by the shared priority resolver.
     *
     * @var string
     */
    protected string $tag;

    /**
     * Accepted argument count, inferred once when omitted.
     *
     * @var int|null
     */
    protected ?int $args;

    /**
     * Reflected handler method.
     *
     * @var ReflectionMethod
     */
    protected ReflectionMethod $reflector;

    /**
     * Constructor.
     *
     * @param CallbackDefinition $definition Callback metadata.
     * @param Container          $container Runtime container.
     */
    public function __construct(
        protected CallbackDefinition $definition,
        protected Container $container,
    ) {
        $this->tag    = $definition->get_tag();
        $this->invoke = $definition->get_invoke();
        $this->args   = $definition->get_accepted_args();
    }

    /**
     * Read runtime state using the decorator property API.
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get( string $name ): mixed {
        return \method_exists( $this, "get_{$name}" )
            ? $this->{"get_{$name}"}()
            : $this->$name ?? null;
    }

    public function get_definition(): CallbackDefinition {
        return $this->definition;
    }

    public function get_container(): Container {
        return $this->container;
    }

    public function get_token(): string {
        return $this->definition->get_id();
    }

    /**
     * Get the handler class name.
     *
     * @return class-string
     */
    public function get_classname(): string {
        return $this->definition->get_handler();
    }

    public function get_method(): string {
        return $this->definition->get_method();
    }

    public function get_tag(): string {
        return $this->resolve_tag( $this->tag, $this->get_modifiers() );
    }

    /**
     * Resolve dynamic tag modifiers at runtime.
     *
     * @return array<int,mixed>|string|bool
     */
    public function get_modifiers(): array|string|bool {
        $modifiers = $this->definition->get_modifiers();

        return $modifiers
            ? \array_map( array( $this, 'get_cb_arg' ), (array) $modifiers )
            : $modifiers;
    }

    public function get_priority(): int {
        return $this->resolve_priority( $this->definition->get_priority() );
    }

    public function get_context(): int {
        return $this->definition->get_context();
    }

    public function get_num_args(): int {
        return $this->args ??= $this->get_reflector()->getNumberOfParameters();
    }

    public function get_reflector(): ReflectionMethod {
        return $this->reflector ??= new ReflectionMethod( $this->get_classname(), $this->get_method() );
    }

    /**
     * Resolve handler metadata without constructing its target.
     *
     * @return H
     */
    public function get_handler(): Can_Handle {
        if ( ! isset( $this->handler ) ) {
            $this->handler = $this->container->get( 'Hook-' . $this->get_classname() );

            if ( $this->handler->is_lazy() ) {
                $this->invoke = ( $this->invoke | Can_Invoke::INV_PROXIED ) & ~Can_Invoke::INV_STANDARD;
            }
        }

        return $this->handler;
    }

    public function get_init_hook(): string {
        return $this->init_hook;
    }

    public function is_loaded(): bool {
        return $this->loaded;
    }

    public function check_context(): bool {
        return XWP_Context::validate( $this->get_context() );
    }

    public function can_load(): bool {
        $handler = $this->get_handler();

        return $this->check_context()
            && ( ! $this->cb_valid( Can_Invoke::INV_STANDARD ) || $this->check_method( $this->definition->get_conditional() ) )
            && ( $handler->is_lazy() || $handler->is_loaded() );
    }

    public function load(): bool {
        if ( $this->loaded ) {
            return true;
        }

        if ( ! $this->init_handler( Can_Handle::INIT_LAZY ) || ! $this->can_load() ) {
            return false;
        }

        $this->loaded      = $this->load_hook();
        $this->init_hook ??= \current_action();

        return $this->loaded;
    }

    /**
     * Invoke through the container, retaining filter values on skipped calls.
     *
     * @param  mixed ...$args Hook arguments.
     * @return mixed
     */
    // Keep the strategy, eligibility, and invocation gates together for each runtime invocation.
    // phpcs:ignore SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    public function invoke( mixed ...$args ): mixed {
        $fallback = 'action' === $this->get_type() ? null : ( $args[0] ?? null );

        if (
            ! $this->init_handler( Can_Handle::INIT_JIT ) ||
            ! $this->check_context() ||
            ! $this->check_method( $this->definition->get_conditional() ) ||
            ( $this->cb_valid( Can_Invoke::INV_ONCE ) && $this->fired ) ||
            ( $this->cb_valid( Can_Invoke::INV_LOOPED ) && $this->firing )
        ) {
            return $fallback;
        }

        try {
            $value = $this->fire_hook( ...$args );

            return 'action' === $this->get_type() ? null : $value;
        } catch ( \Throwable $e ) {
            return $this->handle_exception( $e, $fallback );
        } finally {
            $this->firing = false;
            ++$this->fired;
        }
    }

    protected function get_type(): string {
        return $this->definition->get_type();
    }

    protected function current(): string {
        return ( "current_{$this->get_type()}" )();
    }

    protected function init_handler( string $strategy ): bool {
        $handler = $this->get_handler();

        if ( $handler->is_loaded() ) {
            return true;
        }

        if ( $strategy !== $handler->get_strategy() ) {
            return $handler->is_lazy();
        }

        \do_action( $handler->get_lazy_tag(), $handler );

        return $handler->is_loaded();
    }

    protected function cb_valid( int $current ): bool {
        return 0 !== ( $this->invoke & $current );
    }

    /**
     * Get the callable registered with WordPress.
     *
     * @return array{0:object|null,1:string}
     */
    protected function get_target(): array {
        $handler = $this->get_handler();

        return $this->cb_valid( Can_Invoke::INV_STANDARD )
            ? array( $handler->get_target(), $this->get_method() )
            : array( $this, 'invoke' );
    }

    protected function load_hook( ?string $tag = null ): bool {
        return ( "add_{$this->get_type()}" )(
            $tag ?? $this->get_tag(),
            $this->get_target(),
            $this->get_priority(),
            $this->get_num_args(),
        );
    }

    /**
     * Call the bound handler instance, including explicit injected parameters.
     *
     * @param  mixed ...$args Hook arguments.
     * @return mixed
     */
    protected function fire_hook( mixed ...$args ): mixed {
        $this->firing = true;

        return $this->container->call(
            array( $this->get_handler()->get_target(), $this->get_method() ),
            $this->get_cb_args( $args ),
        );
    }

    /**
     * Append configured parameters after WordPress arguments.
     *
     * @param  array<int,mixed> $args Hook arguments.
     * @return array<int,mixed>
     */
    protected function get_cb_args( array $args ): array {
        foreach ( $this->definition->get_params() as $param ) {
            $args[] = $this->get_cb_arg( $param );
        }

        return $args;
    }

    protected function get_cb_arg( string $param ): mixed {
        return match ( true ) {
            '!self.hook' === $param                => $this,
            '!self.handler' === $param             => $this->get_handler(),
            \str_starts_with( $param, '!value:' )  => \str_replace( '!value:', '', $param ),
            \str_starts_with( $param, '!global:' ) => $GLOBALS[ \str_replace( '!global:', '', $param ) ] ?? null,
            \str_starts_with( $param, '!const:' )  => Constants::get_constant( \str_replace( '!const:', '', $param ) ),
            $this->container->has( $param )        => $this->container->get( $param ),
            default                                => $param,
        };
    }

    /**
     * Log safe failures or propagate the original exception.
     *
     * @template TExc of \Throwable
     * @param  TExc  $e The exception.
     * @param  mixed $v Fallback result.
     * @return mixed
     *
     * @throws TExc If safe invocation is disabled.
     */
    protected function handle_exception( \Throwable $e, mixed $v ): mixed {
        if ( ! $this->cb_valid( Can_Invoke::INV_SAFELY ) ) {
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
}
