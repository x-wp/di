<?php
/**
 * Invoker class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use XWP\DI\Hook\Factory;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Import;
use XWP\DI\Interfaces\Can_Invoke;

/**
 * Handles hook registration and invocation.
 *
 * @mixin Factory
 *
 * @internal Runtime orchestration detail. Use Container or helper functions.
 */
class Invoker {
    /**
     * Handlers.
     *
     * @var array<class-string,false|string>
     */
    private array $handlers = array();

    /**
     * Handlers whose lifecycle registration has begun.
     *
     * @var array<class-string,true>
     */
    private array $registered = array();

    /**
     * EARLY/NOW handlers whose initialization was rejected without an exception.
     *
     * @var array<class-string,true>
     */
    private array $retryable = array();

    /**
     * Pending EARLY/NOW callback passes: false before the attachment point, true after.
     *
     * Entries are removed when attachment begins, including for handlers with no callbacks.
     *
     * @var array<class-string,bool>
     */
    private array $pending_methods = array();

    /**
     * Hooks.
     *
     * @var array<class-string,array<string,string>>>
     */
    private array $callbacks = array();

    /**
     * Cache configuration.
     *
     * @var array{
     *   app: bool,
     *   defs: bool,
     *   hooks: bool,
     *   dir: bool,
     * }
     */
    private array $cache;

    /**
     * WP Environment.
     *
     * @var string
     */
    private string $env;

    /**
     * Is debug mode enabled.
     *
     * @var bool
     */
    private bool $debug;

    /**
     * Application ID.
     *
     * @var string
     */
    private string $app_id;

    /**
     * Constructor.
     *
     * @param  Factory   $factory   Factory instance.
     * @param  Container $container Container instance.
     */
    public function __construct( protected Factory $factory, Container $container ) {
        $this->debug  = $container->get( 'app.debug' );
        $this->cache  = $container->get( 'app.cache' );
        $this->env    = $container->get( 'app.env' );
        $this->app_id = $container->get( 'app.id' );
    }

    /**
     * Magic method to call factory methods.
     *
     * @param  string       $name Method name.
     * @param  array<mixed> $args Method arguments.
     * @return mixed
     */
    public function __call( string $name, array $args ): mixed {
        if ( \method_exists( $this->factory, $name ) ) {
            return $this->factory->$name( ...$args );
        }

        return null;
    }

    /**
     * Get known handlers, and their initialization hook.
     *
     * @return array<class-string,false|string>
     */
    public function get_handlers(): array {
        return $this->handlers;
    }

    /**
     * Get registered hooks.
     *
     * @param  string|null $handler Handler classname.
     * @return ($handler is null ? array<class-string,array<string,string>> : array<string,string>)
     */
    public function get_actions( ?string $handler = null ): array {
        return $handler ? $this->callbacks[ $handler ] ?? array() : $this->callbacks;
    }

    /**
     * Register a handler.
     *
     * @template T of object
     * @param  class-string<T>|T|Can_Handle<T>|array<string,mixed> ...$handlers Handlers to register.
     * @return static
     */
    public function register_handlers( string|object|array ...$handlers ): static {
        foreach ( $handlers as $handler ) {
            $this->register_handler( $handler );
        }

        return $this;
    }

    /**
     * Register a handler.
     *
     * @template TObj of object
     *
     * @param  class-string<TObj> $classname Handler to register.
     * @return Can_Handle<TObj>
     */
    public function register_handler( string $classname ): Can_Handle {
        $h = $this->get_handler( $classname );
        $this->add_handler( $h );

        if ( ! $h->check_context() ) {
            return $h;
        }

        if ( isset( $this->registered[ $h->get_classname() ] ) ) {
            if ( isset( $this->retryable[ $h->get_classname() ] ) ) {
                $this->init_eager_handler( $h );
            }

            return $h;
        }

        // Prevent registration from re-entering through initialization callbacks.
        $this->registered[ $h->get_classname() ] = true;

        //phpcs:disable SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
        match ( $h->get_strategy() ) {
            $h::INIT_LAZY,
            $h::INIT_JIT   => $this->queue_lazy_handler( $h )->queue_methods( $h ),
            $h::INIT_EARLY => $this->init_eager_handler( $h )->queue_methods( $h ),
            $h::INIT_NOW   => $this->init_eager_handler( $h ),
            $h::INIT_USER  => $this->register_methods( $h )->invoke_methods( $h ),
            default        => $this->queue_handler( $h ),
        };
        //phpcs:enable SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall

        return $h;
    }

    /**
     * Add a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $handler The handler to add.
     * @param  bool          $clear   Whether to clear existing hooks.
     * @return static
     */
    public function add_handler( Can_Handle $handler, bool $clear = true ): static {
        $cname = $handler->get_classname();

        if ( isset( $this->handlers[ $cname ] ) ) {
            return $this;
        }

        if ( $clear ) {
            $this->callbacks[ $cname ] = array();
        }

        $this->handlers[ $cname ] = $handler->is_loaded() ? $handler->get_init_hook() : false;

        return $this;
    }

    /**
     * Load a handler.
     *
     * @template T of object
     *
     * @param  T $instance Instance to load.
     * @return Can_Handle<T>
     */
    public function load_handler( object $instance ): Can_Handle {
        $handler = $this->factory->load_handler( $instance );

        $this->register_handler( $handler->get_classname() );

        return $handler;
    }

    /**
     * Queue a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler to queue.
     * @return static
     */
    private function queue_handler( Can_Handle $h ): static {
        \add_action(
            $h->get_tag(),
            function () use ( $h ) {
                $this
                    ->init_handler( $h )
                    ->register_methods( $h )
                    ->invoke_methods( $h );
            },
            $h->get_priority(),
            0,
        );

        return $this;
    }

    /**
     * Queue a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler to queue.
     * @return static
     */
    private function queue_lazy_handler( Can_Handle $h ): static {
        if ( $h->is_lazy() ) {
            \add_action(
                $h->get_lazy_tag(),
                function () use ( $h ) {
                    $this->init_handler( $h );
                },
                $h->get_priority(),
                0,
            );
        }

        return $this;
    }

    /**
     * Initialize a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler to initialize.
     * @return static
     */
    private function init_handler( Can_Handle $h ): static {
        if ( ! $h->load() ) {
            return $this;
        }

        $this->handlers[ $h->get_classname() ] = $h->get_init_hook();

        return $h instanceof Can_Import
            ? $this->init_module( $h )
            : $this;
    }

    /**
     * Attempt EARLY/NOW initialization without reinstalling its lifecycle.
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler to initialize.
     * @return static
     */
    private function init_eager_handler( Can_Handle $h ): static {
        $classname = $h->get_classname();

        // Only a normal rejection enables another attempt, never re-entry or an exception.
        unset( $this->retryable[ $classname ] );
        $this->pending_methods[ $classname ] ??= $h::INIT_NOW === $h->get_strategy();

        $this->init_handler( $h );

        if ( ! $h->is_loaded() ) {
            $this->retryable[ $classname ] = true;
            return $this;
        }

        return $this->attach_pending_methods( $h );
    }

    /**
     * Finish one EARLY/NOW callback pass when initialization and scheduling permit it.
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler whose callbacks are pending.
     * @return static
     */
    private function attach_pending_methods( Can_Handle $h ): static {
        $classname = $h->get_classname();

        if ( ! ( $this->pending_methods[ $classname ] ?? false ) || ! $h->is_loaded() ) {
            return $this;
        }

        unset( $this->pending_methods[ $classname ] );

        return $this->register_methods( $h )->invoke_methods( $h );
    }

    /**
     * Load module imports.
     *
     * @template T of object
     * @param  Can_Import<T> $module Module instance.
     * @return static
     */
    private function init_module( Can_Import $module ): static {
        foreach ( $module->get_handlers() as $handler ) {
            $this->register_handler( $handler );
        }

        foreach ( $module->get_imports() as $import ) {
            $this->register_handler( $import );
        }

        return $this;
    }

    /**
     * Register handler methods
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler instance.
     * @return static
     */
    private function register_methods( Can_Handle $h ): static {
        if ( null !== $h->get_callbacks() ) {
            return $this;
        }

        $cbs = \array_map( static fn( $cb ) => $cb->get_token(), $this->resolve_callbacks( $h ) );
        $h->with_callbacks( $cbs );

        return $this;
    }

    /**
     * Queue handler methods
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler instance.
     * @return static
     */
    private function queue_methods( Can_Handle $h ): static {
        if ( ! $h->is_hookable() ) {
            return $this;
        }

        \add_action(
            $h->get_tag(),
            function () use ( $h ) {
                if ( $h::INIT_EARLY === $h->get_strategy() ) {
                    if ( isset( $this->pending_methods[ $h->get_classname() ] ) ) {
                        $this->pending_methods[ $h->get_classname() ] = true;
                        $this->attach_pending_methods( $h );
                    }

                    return;
                }

                $this
                ->register_methods( $h )
                ->invoke_methods( $h );
            },
            $h->get_priority(),
            0,
        );

        return $this;
    }

    /**
     * Invoke handler methods
     *
     * @template T of object
     * @param  Can_Handle<T> $h Handler instance.
     * @return static
     */
    private function invoke_methods( Can_Handle $h ): static {
        /**
         * Variable override
         *
         * @var class-string<T> $cb_token
         */
        foreach ( $h->get_callbacks() as $cb_token ) {
            $cb = $this->get_hook( $cb_token );

            $cb->load();

            $this->add_callback( $cb );
        }

        \do_action( "xwp_di_hooks_loaded_{$h->get_classname()}" );

        return $this;
    }

    /**
     * Add a hook to the registry.
     *
     * @template T of object
     *
     * @param  Can_Invoke<T,Can_Handle<T>> $cb Callback instance.
     */
    private function add_callback( Can_Invoke $cb ): void {
        $id = "{$cb->get_method()}:{$cb->get_tag()}";
        $cn = $cb->get_classname();

        $this->callbacks[ $cn ][ $id ] = $cb->is_loaded() ? $cb->get_init_hook() : false;
    }

    /**
     * Get the application ID.
     *
     * @return string
     */
    private function app_id(): string {
        return $this->app_id;
    }

    /**
     * Check if cache is enabled for a feature.
     *
     * @param  'app'|'defs'|'hooks' $feature Feature to check.
     * @return bool
     */
    private function is_cached( string $feature ): bool {
        return $this->cache[ $feature ] ?? false;
    }

    /**
     * Are we in production?
     *
     * @return bool
     */
    private function is_prod(): bool {
        return 'production' === $this->env;
    }

    /**
     * Is debug mode enabled.
     *
     * @return bool
     */
    private function can_debug(): bool {
        if ( ! $this->debug ) {
            return false;
        }

        return ! \defined( 'XWP_DI_DEBUG_APP' ) || \str_contains( \XWP_DI_DEBUG_APP, $this->app_id() );
    }
}
