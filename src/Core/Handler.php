<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, Squiz.Commenting.FunctionComment.Missing
/**
 * Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Core;

use Closure;
use ReflectionClass;
use Reflector;
use XWP\DI\Container;
use XWP\DI\Decorators\Infuse;
use XWP\DI\Interfaces\Can_Handle;
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
     * Are we passing arguments to the action.
     *
     * @var int
     */
    protected int $delegate;

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
     * Arguments to pass to the action.
     *
     * @var array<int,null|string>
     */
    protected array $hook_args;

    /**
     * Resolved action arguments.
     *
     * @var array<string,mixed>
     */
    protected array $resolved_action_args;

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
     * Constructor.
     *
     * @param ?string                        $tag         Hook tag.
     * @param ?int                           $priority    Hook priority.
     * @param Container                      $container   Container instance.
     * @param class-string<T>                $classname   Class name of the handler.
     * @param array<int,string>              $callbacks   Array of hooked methods.
     * @param int                            $context     Hook context.
     * @param array<int,string>|string|false $modifiers   Values to replace in the tag name.
     * @param string                         $strategy    Initialization strategy.
     * @param int<0,2>                       $delegate    Pass arguments to the action.
     * @param bool                           $hookable    Is the handler hookable.
     * @param array<int,string>|int          $hook_args Number of arguments to pass to the action.
     * @param bool                           $debug       Debug this hook.
     * @param bool                           $trace       Trace this hook.
     */
    public function __construct(
        ?string $tag,
        ?int $priority,
        Container $container,
        string $classname,
        array $callbacks,
        int $context = self::CTX_GLOBAL,
        string|array|false $modifiers = false,
        string $strategy = self::INIT_AUTO,
        int $delegate = self::DELEGATE_NEVER,
        ?bool $hookable = null,
        int|array $hook_args = array(),
        bool $debug = false,
        bool $trace = false,
    ) {
        parent::__construct(
            tag: $tag,
            priority: $priority,
            container: $container,
            classname: $classname,
            context: $context,
            modifiers: $modifiers,
            debug: $debug,
            trace: $trace,
        );

        $this->callbacks = $callbacks;
        $this->delegate  = $delegate;
        $this->hook_args = ! \is_array( $hook_args ) ? \array_fill( 0, $hook_args, null ) : $hook_args;
        $this->strategy  = $strategy;
        $this->loaded    = false;
        $this->hookable  = $hookable;
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
        if ( '' === $this->tag && null === $this->priority ) {
            $action         = \end( $GLOBALS['wp_current_filter'] );
            $filter         = $GLOBALS['wp_filter'][ $action ];
            $this->priority = $filter->current_priority() + 1;
        }

        return parent::get_priority();
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
        return $this->check_context()
            ? $this->strategy
            : self::INIT_NEVER;
    }

    public function get_init_strategy(): string {
        return $this->check_context()
            ? $this->get_strategy()
            : self::INIT_NEVER;
    }

    public function get_hook_args(): array {
        return $this->hook_args;
    }

    public function get_hook_args_count(): int {
        if ( ! $this->hook_args ) {
            return 0;
        }

        return \max( \count( $this->hook_args ), ...\array_keys( $this->hook_args ) );
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

        $data['params']['callbacks'] = $this->callbacks;
        $data['params']['params']    = \array_combine(
            \array_keys( $this->params ),
            \array_map(
                fn( string $m ) => $this->get_params( $m )?->get( $this ) ?? array(),
                \array_keys( $this->params ),
            ),
        );

        return $this->merge_compat_args( $data );
    }

    public function get_callbacks(): ?array {
        return $this->callbacks;
    }

    public function get_lazy_tag(): string {
        return \sprintf( '%s_%s_init', $this->get_token(), $this->get_strategy() );
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
        $this->did_init    = true;

        return $this;
    }

    public function with_reflector( Reflector $r ): static {
        $this->classname = $r->getName();

        return parent::with_reflector( $r );
    }

    public function with_params( array $params ): static {
        foreach ( $params as $method => $args ) {
            $this->params[ $method ] = new Infuse( ...$args );
        }

        return $this;
    }

    public function with_callbacks( ?array $callbacks ): static {
        $this->callbacks = $callbacks;

        return $this;
    }

    public function is_lazy(): bool {
        return \in_array( $this->get_strategy(), array( self::INIT_LAZY, self::INIT_JIT ), true );
    }

    public function can_load( array $args = array() ): bool {
        return parent::can_load() && $this->check_method(
            array( $this->get_classname(), 'can_initialize' ),
            fn() => $this->resolve_action_args( $args, self::DELEGATE_ON_CREATE ),
        );
    }

    public function load( array $args = array() ): bool {
        return $this->initialize( $args )->configure_async()->on_initialize();
    }

    public function lazy_load( array $args = array() ): bool {
        if ( $this->is_loaded() ) {
            return true;
        }

        if ( ! $this->load( $args ) ) {
            return false;
        }

        $this->init_hook = $this->get_lazy_tag();

        \remove_all_actions( $this->get_lazy_tag() );

        return true;
    }

    /**
     * Instantiate the handler.
     *
     * @param  array<string,mixed> $args Arguments to pass to the handler.
     * @return T
     */
    protected function instantiate( array $args = array() ): object {
        return 0 === $this->get_hook_args_count()
            ? $this->get_container()->get( $this->get_classname() )
            : $this->get_container()->make(
                $this->get_classname(),
                $this->resolve_action_args( $args, self::DELEGATE_ON_LOAD ),
            );
    }

    /**
     * Initialize the handler.
     *
     * @param  array<string,mixed> $args Arguments to pass to the handler.
     * @return static
     */
    protected function initialize( array $args = array() ): static {
        $this->instance ??= $this->instantiate( $args );
        $this->init_hook = \current_action();

        return $this;
    }

    /**
     * Configure the handler asynchronously.
     *
     * @return static
     */
    protected function configure_async(): static {
        $method = $this->method_exists( __FUNCTION__ );

        if ( $method ) {
            foreach ( $this->call_method( $method ) as $key => $val ) {
                $this->get_container()->set( $key, $val );
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
        if ( $this->is_ready() ) {
            return true;
        }

        $method = $this->method_exists( __FUNCTION__ );

        if ( $method ) {
            $this->call_method( $method, $this->resolve_params( __FUNCTION__ ) );
        }

        $this->ready = true;

        return true;
    }

    /**
     * Check if the method exists.
     *
     * @param  string $method Method to check.
     * @return array{0:class-string<T>, 1:string}|false
     */
    protected function method_exists( string $method ): array|bool {
        return \method_exists( $this->get_classname(), $method )
            ? array( $this->get_classname(), $method )
            : false;
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

    public function is_hookable(): bool {
        if ( ! $this->check_context() ) {
            return false;
        }

        return $this->hookable ?? true;
    }

    protected function get_constructor_args(): array {
        return \array_merge(
            parent::get_constructor_args(),
            array( 'hookable', 'strategy' ),
        );
    }

    /**
     * Resolve the arguments for the action.
     *
     * @param  array<mixed> $args Arguments to pass to the action.
     * @param  int          $flag Flag to determine if we should pass the arguments.
     * @return array<mixed>
     */
    protected function resolve_action_args( array $args, int $flag ): array {
        if ( ! $args || ! $this->can_resolve_hook_args( $flag ) ) {
            return $args;
        }

        if ( isset( $this->resolved_action_args ) ) {
            return $this->resolved_action_args;
        }

        $resolved = array();

        foreach ( $this->get_hook_args() as $i => $arg ) {
            $key = $arg ?? \count( $resolved );

            $resolved[ $key ] = $args[ $i ];
        }

        return $this->resolved_action_args ??= $resolved;
    }

    protected function can_resolve_hook_args( int $flag ): bool {
        return $this->get_hook_args_count() && 0 !== $this->delegate && 0 !== $this->delegate & $flag;
    }

    protected function resolve_strategy( string $strategy ): string {
        return $this->check_context()
            ? $strategy
            : self::INIT_NEVER;
    }
}
