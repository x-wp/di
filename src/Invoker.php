<?php
/**
 * Invoker class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use Psr\Log\LoggerInterface;
use XWP\DI\Hook\Factory;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Import;
use XWP\DI\Interfaces\Can_Invoke;

/**
 * Handles hook registration and invocation.
 *
 * @mixin Factory
 */
class Invoker {
    /**
     * Handlers.
     *
     * @var array<class-string,false|string>
     */
    private array $handlers = array();

    /**
     * Hooks.
     *
     * @var array<class-string,array<string,string>>>
     */
    private array $callbacks = array();

    /**
     * Uncached handlers.
     *
     * @var array<string,string> //array<Can_Handle<object>>
     */
    private array $uncached = array();

    /**
     * List of handlers which use deprecated arguments.
     *
     * @var array<class-string,array<string>>
     */
    private array $old_handlers = array();

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
     * Logger instance.
     *
     * @var LoggerInterface
     */
    private LoggerInterface $logger;

    /**
     * Traced hooks and handlers.
     *
     * @var array<int,class-string>|bool
     */
    private array|bool $traced;

    /**
     * Constructor.
     *
     * @param  Factory   $factory   Factory instance.
     * @param  Container $container Container instance.
     */
    public function __construct( protected Factory $factory, protected Container $container ) {
        $this->debug  = $container->get( 'app.debug' );
        $this->cache  = $container->get( 'app.cache' );
        $this->env    = $container->get( 'app.env' );
        $this->app_id = $container->get( 'app.id' );
        $this->traced = $container->get( 'app.trace' );
        $this->logger = $container->logger( self::class );

        if ( ! $this->can_debug() ) {
            return;
        }

        \add_action( 'shutdown', array( $this, 'debug_output' ), \PHP_INT_MAX );
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

    public function can_trace( string $classname, bool $trace ): bool {
        if ( false === $this->traced ) {
            return false;
        }

        if ( true === $this->traced ) {
            return true;
        }

        return 0 < \count( $this->traced )
            ? \in_array( $classname, $this->traced, true ) || $trace
            : $this->can_debug();
    }

    /**
     * Debug output.
     */
    public function debug_output(): void {
        // \dump( $this->handlers, $this->callbacks );
        // die;
        $this->logger->debug( 'Shutting down application' );

        if ( $this->uncached ) {
            $this->logger->debug(
                'Hook definition cache is active. The following handlers were loaded manually',
                $this->uncached,
            );
        }

        if ( $this->old_handlers ) {
            $this->logger->debug( 'Handlers with deprecated arguments', $this->old_handlers );
        }

        $this->logger->debug( 'Handlers', $this->handlers );
        $this->logger->debug( 'Hooks', $this->callbacks );

        $this->logger->debug( 'Application shutdown complete' );
    }

    /**
     * Get registered handlers, and their initialization hook.
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

    public function register( $something ): mixed {
        return $this->add_handler( $something )->process_handler( $something )->return_handler( $something );
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
        $h = $this->container->get( $this->get_token( $classname ) );

        return $this->add_handler( $h )
            ->process_handler( $h )
            ->return_handler( $h );
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
     * Prepare a handler for invocation.
     *
     * @template TObj of object
     *
     * @param  Can_Handle<TObj> $h Handler instance or classname.
     * @return static
     */
    private function process_handler( Can_Handle $h ): static {
        //phpcs:disable SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
        return match ( $h->get_init_strategy() ) {
            $h::INIT_LAZY,
            $h::INIT_JIT   => $this->queue_lazy_handler( $h )->queue_methods( $h ),
            $h::INIT_EARLY => $this->init_handler( $h )->queue_methods( $h ),
            $h::INIT_NOW   => $this->init_handler( $h )->register_methods( $h )->invoke_methods( $h ),
            $h::INIT_USER  => $this->register_methods( $h )->invoke_methods( $h ),
            $h::INIT_NEVER => $this->disable_handler( $h, 'Invalid context' )->trace_handler( $h ),
            default        => $this->queue_handler( $h ),
        };
        //phpcs:enable SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
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
            function ( mixed ...$args ) use ( $h ) {
                $this
                    ->init_handler( $h, $args )
                    ->register_methods( $h )
                    ->invoke_methods( $h );
            },
            $h->get_priority(),
            $h->get_hook_args_count(),
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
                function ( $handler ) {
                    $this->init_handler( $handler );
                },
                $h->get_priority(),
                1,
            );
        }

        return $this;
    }

    /**
     * Initialize a handler.
     *
     * @template T of object
     * @param  Can_Handle<T>           $h Handler to initialize.
     * @param  array<int|string,mixed> $args Handler arguments.
     * @return static
     */
    private function init_handler( Can_Handle $h, array $args = array() ): static {
        if ( $h->is_loaded() ) {
            \dump( 'Handler already loaded', array( 'handler' => $h->get_classname() ) );
            die;
            // $this->logger->debug( 'Handler already loaded', array( 'handler' => $h->get_classname() ) );
            return $this;
        }

        if ( ! $h->can_load( $args ) ) {
            return $this->disable_handler( $h, 'Conditions not met' );
        }

        $method = $h->is_lazy() ? 'lazy_load' : 'load';

        if ( ! $h->$method( $args ) ) {
            return $this;
        }

        return $h instanceof Can_Import
            ? $this->trace_handler( $h )->init_module( $h )
            : $this->trace_handler( $h );
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
            $this->register( $handler );
        }

        foreach ( $module->get_imports() as $import ) {
            $this->register( $import );
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
        // if ( ! $h->is_hookable() || null !== $h->get_callbacks() ) {
        // return $this;
        // }

        // $cbs = \array_map( static fn( $cb ) => $cb->get_token(), $this->resolve_callbacks( $h ) );
        // $h->with_callbacks( $cbs );

        // if ( $this->is_cached( 'hooks' ) && ( $this->debug || $this->is_prod() ) ) {
        // $this->uncached[ $h->get_token() ] = $h->get_classname();
        // }

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
        if ( $h->is_hookable() ) {
            \add_action(
                $h->get_tag(),
                function () use ( $h ) {
                    $this
                    ->register_methods( $h )
                    ->invoke_methods( $h );
                },
                $h->get_priority(),
                0,
            );
        }

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
        foreach ( $h->get_callbacks() as $cb ) {
            if ( \is_string( $cb ) ) {
                \dump( $h );
                die;
            }
            // $cb = $this->container->get( $cb_token );

            $cb->load();

            $this->add_callback( $cb );
        }

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
        return $this->debug;
        if ( ! $this->debug ) {
            return false;
        }

        return ! \defined( 'XWP_DI_DEBUG_APP' ) || \str_contains( XWP_DI_DEBUG_APP, $this->app_id() );
    }

    /**
     * Invalidate a handler.
     *
     * @template T of object
     *
     * @param  Can_Handle<T> $h Handler instance.
     * @param  string        $reason Reason for invalidation.
     * @return static
     */
    private function disable_handler( Can_Handle $h, string $reason = '' ): static {
        $h->disable( $reason );

        return $this;
    }

    /**
     * Trace a handler.
     *
     * @template T of object
     *
     * @param  Can_Handle<T> $h Handler instance.
     * @return static
     */
    private function trace_handler( Can_Handle $h ): static {
        $this->handlers[ $h->get_classname() ] = $h->is_loaded() ? $h->get_init_hook() : false;

        return $this;
    }

    /**
     * Trace a handler.
     *
     * @template T of object
     *
     * @param  Can_Handle<T> $h Handler instance.
     * @return Can_Handle<T>
     */
    private function return_handler( Can_Handle $h ): Can_Handle {
        return $h;
    }
}
