<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, Squiz.Commenting.FunctionComment.Missing
/**
 * Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use Exception;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use XWP\DI\Container;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;
use XWP\DI\Utils\Reflection;

/**
 * Decorator for handling WordPress hooks.
 *
 * @template T of object
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
    protected ?array $hooks = null;

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
        string $strategy = self::INIT_AUTO,
        ?bool $hookable = null,
    ) {
        $this->strategy     = $strategy;
        $this->loaded       = self::INIT_USER === $strategy;
        $this->container_id = $container;
        $this->hookable     = $hookable;

        // ToooDooo: Add _doing_it_wrong notice if container_id is redefined.

        parent::__construct( $tag, $tag ? $priority : null, $context, $conditional, $modifiers );
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
        $this->init_hook   = \current_action();

        if ( ! $this->get_container()->has( $this->classname ) ) {
            $this->get_container()->set( $this->classname, $this->instance );
        }

        return $this;
    }

    public function with_params( array $params ): static {
        foreach ( $params as $method => $args ) {
            $this->params[ $method ] = new Infuse( ...$args );
        }

        return $this;
    }

    public function with_hooks( ?array $hooks ): static {
        $this->hooks = $hooks;

        return $this;
    }

    public function get_target(): ?object {
        return $this->instance ?? null;
    }

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
     * @return ReflectionClass<T>
     */
    public function get_reflector(): ReflectionClass {
        return $this->reflector ??= new ReflectionClass( $this->classname );
    }

    public function get_data(): array {
        $data = parent::get_data();

        $data['args']['hookable'] = $this->hookable;
        $data['args']['strategy'] = $this->strategy;
        $data['params']['hooks']  = $this->get_hooks();
        $data['params']['params'] = \array_combine(
            \array_keys( $this->params ),
            \array_map(
                fn( string $m ) => $this->get_params( $m )?->get( $this ) ?? array(),
                \array_keys( $this->params ),
            ),
        );

        return $data;
    }

    public function get_hooks(): ?array {
        if ( isset( $this->hooks ) ) {
            return $this->hooks;
        }

        if ( ! $this->is_cached() ) {
            return null;
        }

        if ( ! $this->get_container() ) {
            return array();
        }

        $token = 'Hooks-' . $this->get_classname();

        return $this->hooks ??= $this->get_container()->has( $token )
            ? $this->get_container()->get( $token )
            : null;
    }

    public function get_lazy_tag(): string {
        return \sprintf( '%s_%s_init', $this->get_token(), $this->get_strategy() );
    }

    public function is_lazy(): bool {
        return \in_array( $this->get_strategy(), array( self::INIT_LAZY, self::INIT_JIT ), true );
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

            \remove_all_actions( $this->get_tag() );
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
        return $this->get_params( $method )?->resolve( $this ) ?? array();
    }

    public function can_load(): bool {
        return parent::can_load() && $this->check_method( array( $this->classname, 'can_initialize' ) );
    }

    public function is_hookable(): bool {
        if ( ! $this->check_context() ) {
            return false;
        }

        return $this->hookable ?? true;
    }

    public function is_loaded(): bool {
        return $this->loaded;
    }

    protected function get_token_prefix(): string {
        return 'Handler';
    }
}
