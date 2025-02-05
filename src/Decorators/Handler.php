<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, Squiz.Commenting.FunctionComment.Missing
/**
 * Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use DI\Container;
use ReflectionClass;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Utils\Reflection;

/**
 * Decorator for handling WordPress hooks.
 *
 * @template T of object
 *
 * @property-read T               $target    Handler instance.
 * @property-read class-string<T> $classname Handler classname.
 *
 * @extends Hook<T,ReflectionClass<T>>
 * @implements Can_Handle<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Handler extends Hook implements Can_Handle {
    /**
     * Did we fire the on_initialize method.
     *
     * @var bool
     */
    protected bool $did_init = false;


    /**
     * Handler classname.
     *
     * @var class-string<T>
     */
    protected string $classname;

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
     * Container ID.
     *
     * @var ?string
     */
    protected ?string $container_id;

    /**
     * Is the handler hookable.
     *
     * @var ?bool
     */
    protected ?bool $hookable;

    /**
     * Constructor.
     *
     * @param string                                             $tag         Hook tag.
     * @param Closure|string|int|array{0:class-string,1:string}  $priority    Hook priority.
     * @param string                                             $container   Container ID.
     * @param int                                                $context     Hook context.
     * @param null|Closure|string|array{0:class-string,1:string} $conditional Conditional callback.
     * @param array<int,string>|string|false                     $modifiers   Values to replace in the tag name.
     * @param string                                             $strategy    Initialization strategy.
     * @param bool                                               $hookable    Is the handler hookable.
     */
    public function __construct(
        ?string $tag = null,
        Closure|string|int|array $priority = 10,
        ?string $container = null,
        int $context = self::CTX_GLOBAL,
        array|string|Closure|null $conditional = null,
        string|array|false $modifiers = false,
        string $strategy = self::INIT_DEFFERED,
        ?bool $hookable = null,
    ) {
        $this->strategy     = $strategy;
        $this->loaded       = self::INIT_DYNAMICALY === $strategy;
        $this->container_id = $container;
        $this->hookable     = $hookable;

        parent::__construct( $tag, $tag ? $priority : null, $context, $conditional, $modifiers );
    }

    /**
     * Set the handler classname.
     *
     * @param  class-string<T> $classname Handler classname.
     * @return static
     */
    public function with_classname( string $classname ): static {
        $this->classname ??= $classname;

        return $this;
    }

    public function with_container( ?string $container ): static {
        if ( null !== $container ) {
            $this->container_id ??= $container;
        }

        return $this;
    }

    /**
     * Set the handler instance.
     *
     * @param  T $instance Handler instance.
     * @return static
     */
    public function with_target( object $instance ): static {
        $this->instance  ??= $instance;
        $this->classname ??= $instance::class;
        $this->loaded      = true;

        if ( ! $this->container->has( $this->classname ) ) {
            $this->container->set( $this->classname, $this->instance );
        }

        return $this;
    }

    /**
     * Lazy load the handler.
     */
    public function lazy_load(): void {
        $this->load();
    }

    /**
     * Loads the handler.
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
        return $this->container->get( $this->classname );
    }

    /**
     * Initialize the handler.
     *
     * @return static
     */
    protected function initialize(): static {
        if ( \doing_action( $this->lazy_hook ) ) {
            \remove_all_actions( $this->lazy_hook );
        }

        $this->instance ??= $this->instantiate();

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
        $injector = Reflection::get_decorator( $this->reflector->getMethod( $method ), Infuse::class );

        return $injector ? $injector->resolve( $this ) : array();
    }

    /**
     * Get the handler target.
     *
     * @return T|null
     */
    public function get_target(): ?object {
        return $this->instance ?? null;
    }

    public function can_load(): bool {
        return parent::can_load() && $this->check_method( array( $this->classname, 'can_initialize' ) );
    }

    protected function get_id(): string {
        return \strtolower( \str_replace( '\\', '_', $this->classname ) );
    }

    protected function get_tag(): string {
        return $this->tag ?: \current_action();
    }

    protected function get_priority(): int {
        if ( '' === $this->tag && null === $this->prio ) {
            $action     = \end( $GLOBALS['wp_current_filter'] );
            $filter     = $GLOBALS['wp_filter'][ $action ];
            $this->prio = $filter->current_priority() + 1;
        }

        return parent::get_priority();
    }

    protected function get_container(): Container {
        return \xwp_app( $this->container_id );
    }

    protected function get_lazy_hook(): string {
        return "{$this->id}_{$this->strategy}_init";
    }

    public function is_lazy(): bool {
        return self::INIT_ON_DEMAND === $this->strategy || self::INIT_JUST_IN_TIME === $this->strategy;
    }

    public function is_hookable(): bool {
        if ( ! $this->check_context() ) {
            return false;
        }

        return $this->hookable ?? true;
    }
}
