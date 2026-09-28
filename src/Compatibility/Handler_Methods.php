<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, Squiz.Commenting.FunctionComment.Missing
/**
 * Handler legacy compatibility methods.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Compatibility;

use Closure;
use ReflectionClass;
use Reflector;
use XWP\DI\Decorators\Infuse;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Utils\Reflection;

/**
 * Preserves inherited wiring and override seams for existing decorators.
 *
 * @template T of object
 * @internal New runtime extensions belong in XWP\DI\Hook.
 */
trait Handler_Methods {
    /**
     * Did we fire the on_initialize method.
     *
     * @var bool
     */
    protected bool $did_init = false;

    /**
     * Handler instance.
     *
     * @var T
     */
    protected object $instance;

    /**
     * The initialization strategy.
     *
     * @var string
     */
    protected string $strategy;

    /**
     * Is the handler hookable.
     *
     * @var ?bool
     */
    protected ?bool $hookable;

    /**
     * Hook when the handler is initialized.
     *
     * @var string
     */
    protected string $init_hook;

    /**
     * Resolved params.
     *
     * @var array{
     *   on_initialize: null|Infuse
     * }
     */
    protected array $params = array(
        'on_initialize' => null,
    );

    /**
     * Array of hooked methods
     *
     * @var ?array<int,string>
     */
    protected ?array $callbacks = null;

    /**
     * Deprecated constructor arguments.
     *
     * @var array<string>
     */
    protected array $compat_args = array();

    /**
     * Mark the handler as loaded, and call the on_initialize method.
     *
     * @return bool
     */
    protected function on_initialize(): bool {
        if ( ! $this->did_init && $this->method_exists( __FUNCTION__ ) ) {
            $this->container->call(
                array( $this->instance, __FUNCTION__ ),
                $this->resolve_params( __FUNCTION__ ),
            );
        }

        $this->did_init = true;

        return true;
    }

    /**
     * Set the handler instance.
     *
     * @param  T $instance Handler instance.
     * @return static
     *
     * @internal Runtime wiring detail. Attributes are immutable in v2.0.
     */
    public function with_target( object $instance ): static {
        $this->instance  ??= $instance;
        $this->classname ??= $instance::class;
        $this->loaded      = true;
        $this->init_hook   = \current_action();
        $this->did_init    = true;

        return $this;
    }

    /**
     * Set reflected handler data.
     *
     * @internal Parser/runtime wiring detail. Attributes are immutable in v2.0.
     *
     * @param  ReflectionClass<T> $r Reflector instance.
     * @return static
     */
    public function with_reflector( Reflector $r ): static {
        $this->classname = $r->getName();

        return parent::with_reflector( $r );
    }

    /**
     * Set infuse parameter metadata.
     *
     * @internal Parser/runtime wiring detail. Attributes are immutable in v2.0.
     *
     * @param  array<string,array<string>> $params Parameters.
     * @return static
     */
    public function with_params( array $params ): static {
        foreach ( $params as $method => $args ) {
            $this->params[ $method ] = new Infuse( ...$args );
        }

        return $this;
    }

    /**
     * Set callback metadata.
     *
     * @internal Parser/runtime wiring detail. Attributes are immutable in v2.0.
     *
     * @param  array<int,string>|null $callbacks Callbacks.
     * @return static
     */
    public function with_callbacks( ?array $callbacks ): static {
        $this->callbacks = $callbacks;

        return $this;
    }

    /**
     * Get the runtime handler instance.
     *
     * @internal Runtime wiring detail.
     *
     * @return T|null
     */
    public function get_target(): ?object {
        return $this->instance ?? null;
    }

    /**
     * Get infuse parameter metadata for a method.
     *
     * @internal Runtime wiring detail.
     *
     * @param  string $method Method name.
     * @return Infuse|null
     */
    public function get_params( string $method ): ?Infuse {
        return $this->params[ $method ] ??= \method_exists( $this->get_classname(), $method )
            ? Reflection::get_decorator( $this->get_reflector()->getMethod( $method ), Infuse::class )
            : null;
    }

    public function get_strategy(): string {
        return $this->strategy;
    }

    /**
     * Get the hook tag.
     *
     * If tag is not set, use the current action.
     * If the handler is lazy, append tag is the injection token
     *
     * @return string
     */
    public function get_tag(): string {
        return parent::get_tag() ?: \current_action();
    }

    public function get_priority(): int {
        if ( '' === $this->tag && null === $this->prio ) {
            $action     = \end( $GLOBALS['wp_current_filter'] );
            $filter     = $GLOBALS['wp_filter'][ $action ];
            $this->prio = $filter->current_priority() + 1;
        }

        return parent::get_priority();
    }

    /**
     * Get the reflector instance.
     *
     * @internal Parser/runtime wiring detail.
     *
     * @return ReflectionClass<T>
     */
    public function get_reflector(): ReflectionClass {
        return $this->reflector ??= new ReflectionClass( $this->classname );
    }

    /**
     * Get resolved callbacks.
     *
     * @internal Runtime wiring detail.
     *
     * @return array<int,string>|null
     */
    public function get_callbacks(): ?array {
        return $this->callbacks;
    }

    /**
     * Get the lazy-load hook tag.
     *
     * @internal Runtime dispatch detail.
     *
     * @return string
     */
    public function get_lazy_tag(): string {
        return \sprintf( '%s_%s_init', $this->get_token(), $this->get_strategy() );
    }

    /**
     * Get legacy compatibility arguments.
     *
     * @internal Runtime compatibility detail.
     *
     * @return array<mixed>
     */
    public function get_compat_args(): array {
        return $this->compat_args;
    }

    /**
     * Whether this handler uses lazy initialization.
     *
     * @internal Runtime dispatch detail.
     *
     * @return bool
     */
    public function is_lazy(): bool {
        return \in_array( $this->get_strategy(), array( self::INIT_LAZY, self::INIT_JIT ), true );
    }

    /**
     * Can the handler be loaded?
     *
     * @internal Runtime dispatch detail.
     *
     * @return bool
     */
    public function can_load(): bool {
        return parent::can_load() && $this->check_method(
            array( $this->classname, 'can_initialize' ),
            $this->resolve_params( 'can_initialize' ),
        );
    }

    /**
     * Whether this handler can register callbacks.
     *
     * @internal Runtime dispatch detail.
     *
     * @return bool
     */
    public function is_hookable(): bool {
        if ( ! $this->check_context() ) {
            return false;
        }

        return $this->hookable ?? true;
    }

    /**
     * Lazy load the handler.
     *
     * @internal Runtime dispatch detail.
     */
    public function lazy_load(): void {
        $this->load();
    }

    /**
     * Loads the handler.
     *
     * @internal Runtime dispatch detail. Retained for legacy subclasses and typed views.
     *
     * @return bool
     */
    public function load(): bool {
        if ( $this->loaded ) {
            return true;
        }

        return $this->can_load() &&
            $this->initialize()->configure_async()->on_initialize();
    }

    /**
     * Instantiate the handler.
     *
     * @return T
     */
    protected function instantiate(): object {
        return $this->get_container()->get( $this->get_classname() );
    }

    /**
     * Initialize the handler.
     *
     * @return static
     */
    protected function initialize(): static {
        if ( $this->is_lazy() && \doing_action( $this->get_tag() ) ) {
            $init_hook = $this->get_lazy_tag();
        }

        $this->instance ??= $this->instantiate();
        $this->init_hook = $init_hook ?? \current_action();

        return $this;
    }

    /**
     * Configure the handler asynchronously.
     *
     * @return static
     */
    protected function configure_async(): static {
        if ( \method_exists( $this->classname, 'configure_async' ) ) {
            foreach ( $this->classname::configure_async() as $key => $val ) {
                $this->container->set( $key, $val );
            }
        }

        $this->loaded = true;

        return $this;
    }

    /**
     * Check if the method exists.
     *
     * @param  string $method Method to check.
     * @return bool
     */
    protected function method_exists( string $method ): bool {
        return \method_exists( $this->instance, $method );
    }

    /**
     * Resolve the parameters for a method.
     *
     * @param  string $method     Method name.
     * @return array<mixed>
     */
    protected function resolve_params( string $method ): array {
        return $this->get_params( $method )?->resolve( $this ) ?? array();
    }
}
