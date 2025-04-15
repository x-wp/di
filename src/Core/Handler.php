<?php //phpcs:disable Universal.Operators.DisallowShortTernary.Found, Squiz.Commenting.FunctionComment.Missing
/**
 * Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Core;

use XWP\DI\Decorators\Infuse;
use XWP\DI\Interfaces\Invokes_Handler;
use XWP\DI\Utils\Reflection;

/**
 * Decorator for handling WordPress hooks.
 *
 * @template T of object
 *
 * @extends Hook<T>
 * @implements Invokes_Handler<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Handler extends Hook implements Invokes_Handler {
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
    protected string $strategy = self::INIT_AUTO;

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
    protected array $hook_args = array();

    /**
     * Resolved action arguments.
     *
     * @var array<string,mixed>
     */
    protected array $resolved_action_args;

    /**
     * Hydrated state.
     *
     * @var array<string,object>
     */
    protected array $hydrate = array();

    /**
     * Resolved params.
     *
     * @var array{
     *   on_initialize: null|Infuse
     * }
     */
    protected array|false $on_init = false;

    /**
     * Array of hooked methods
     *
     * @var ?array<int,string>
     */
    protected ?array $callbacks = null;

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

    public function get_priority(): ?int {
        return parent::get_priority() ?? $this->current_priority();
    }

    public function get_target(): ?object {
        return $this->instance ?? null;
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

    public function get_callbacks(): ?array {
        if ( $this->hydrated ) {
            return $this->callbacks;
        }

        $this->callbacks = \array_combine(
            $this->callbacks,
            \array_map(
                fn( $token ) => $this->get_container()->get( $token )->with_handler( $this ),
                $this->callbacks,
            ),
        );

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

        if ( false !== $this->on_init ) {
            $this->call_method(
                $this->method_exists( __FUNCTION__ ),
                $this->get_params(),
            );
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

    public function is_hookable(): bool {
        if ( ! $this->check_context() ) {
            return false;
        }

        return $this->hookable ?? true;
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

    protected function hydrate( string $what ): array {
        return $this->hydrate[ $what ] ??= $this->resolve( ...$this->$what );
    }

    protected function current_priority(): int {
        $action = \end( $GLOBALS['wp_current_filter'] );
        $filter = $GLOBALS['wp_filter'][ $action ];

        return $this->priority ??= $filter->current_priority() + 1;
    }

    protected function get_params(): array {
        return \array_map( '\DI\get', $this->on_init );
    }
}
