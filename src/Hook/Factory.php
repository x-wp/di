<?php //phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped
/**
 * Factory class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use DI\Definition\Exception\InvalidDefinition;
use DI\Factory\RequestedEntry;
use ReflectionClass;
use ReflectionMethod;
use XWP\DI\Container;
use XWP\DI\Decorators\Handler;
use XWP\DI\Interfaces\Decorates_Callback;
use XWP\DI\Interfaces\Decorates_Handler;
use XWP\DI\Interfaces\Decorates_Hook;
use XWP\DI\Interfaces\Decorates_Module;
use XWP\DI\Traits\Hook_Token_Methods;
use XWP\DI\Utils\Reflection;

/**
 * Factory for creating and resolving hooks.
 */
class Factory {
    use Hook_Token_Methods;

    /**
     * Did the container start.
     *
     * @var ?bool
     */
    private ?bool $started;

    /**
     * Factory constructor.
     *
     * @param ?Container $container Container instance.
     */
    public function __construct( protected ?Container $container = null ) {
    }

    /**
     * Get a module by classname
     *
     * @template TObj of object
     *
     * @param  class-string<TObj> $module Module classname.
     * @return Decorates_Module<TObj>
     */
    public function get_module( string $module ): Decorates_Module {
        return $this->get_handler( $module );
    }

    /**
     * Resolve a module by classname
     *
     * @template TObj of object
     *
     * @param  class-string<TObj> $module Module classname.
     * @return Decorates_Module<TObj>
     */
    public function resolve_module( string $module ): Decorates_Module {
        return $this->resolve_handler( $module, Decorates_Module::class );
    }

    /**
     * Get a handler by classname
     *
     * @template TObj of object
     *
     * @param  class-string<TObj> $target Handler classname.
     * @return Decorates_Handler<TObj>
     *
     * @throws InvalidDefinition If the handler is not found.
     */
    public function get_handler( string $target ): Decorates_Handler {
        $handler = $this->get( $target )
            ?? $this->resolve_handler( $target )
            ?? throw new InvalidDefinition( "Handler not found: {$target}" );

        return $this->save_hook( $handler );
    }

    /**
     * Resolve the handler for a hook.
     *
     * @template THnd of Decorates_Module|Decorates_Handler
     * @template TObj of object
     *
     * @param  class-string<TObj> $hook Hook classname or instance.
     * @param  class-string<THnd> $type Handler classname.
     * @return null|THnd
     */
    public function resolve_handler( string $hook, string $type = Decorates_Handler::class ): ?Decorates_Handler {
        /**
         * Reflection class for the hook.
         *
         * @var ReflectionClass<TObj>
         */
        $reflector = Reflection::get_reflector( $hook );

        return Reflection::get_decorator( $reflector, $type )?->with_reflector( $reflector );
    }

    /**
     * Get a hook by classname
     *
     * @template TObj of object
     *
     * @param  class-string<TObj> $hook Hook classname.
     * @return Decorates_Callback<TObj,Decorates_Handler<TObj>>
     */
    public function get_hook( string $hook ): Decorates_Callback {
        return $this->get( $hook );
    }

    /**
     * Get handler callbacks.
     *
     * @template TObj of object
     *
     * @param  Decorates_Handler<TObj> $handler Handler instance.
     * @return array<int,Decorates_Callback<TObj,Decorates_Handler<TObj>>>
     *
     * @throws InvalidDefinition If the container is not set.
     */
    public function get_callbacks( Decorates_Handler $handler ): array {
        if ( null === $handler->get_callbacks() ) {
            return $this->resolve_callbacks( $handler );
        }

        if ( ! $this->started() ) {
            throw new InvalidDefinition( 'Container not set' );
        }

        return \array_map( array( $this, 'get' ), $handler->get_callbacks() );
    }

    /**
     * Get handler callbacks.
     *
     * @template TObj of object
     *
     * @param  Decorates_Handler<TObj> $handler Handler instance.
     * @return array<int,Decorates_Callback<TObj,Decorates_Handler<TObj>>>
     */
    public function resolve_callbacks( Decorates_Handler $handler ): array {
        $callbacks = array();

        foreach ( $this->resolve_methods( $handler ) as $reflector ) {
            $callbacks [] = $this->resolve_method_callbacks( $handler, $reflector );
        }

        return \array_merge( ...$callbacks );
    }

    /**
     * Loads a handler definition for an existing instance.
     *
     * @template TObj of object
     *
     * @param  TObj $instance Instance to load the handler for.
     * @return Decorates_Handler<TObj>
     */
    public function load_handler( object $instance ): Decorates_Handler {
        /**
         * Handler instance.
         *
         * @var Decorates_Handler<TObj> $handler
         */
        $handler = $this->get( $instance::class )
            ?? $this->resolve_handler( $instance::class )?->with_target( $instance )
            ?? $this->new_handler( $instance );

        return $this->save_handler( $handler );
    }

    /**
     * Creates a handler definition for an existing instance.
     *
     * @template TObj of object
     *
     * @param  TObj $instance Instance to load the handler for.
     * @return Decorates_Handler<TObj>
     */
    public function create_handler( object $instance ): Decorates_Handler {
        return $this->save_handler( $this->new_handler( $instance ) );
    }

    /**
     * Create a new handler instance.
     *
     * @template TObj of object
     *
     * @param TObj $instance Handler instance.
     * @return Decorates_Handler<TObj>
     */
    protected function new_handler( object $instance ): Decorates_Handler {
        $handler = new Handler( strategy: Handler::INIT_USER, hookable: true );

        /**
         * Handler instance.
         *
         * @var Decorates_Handler<TObj> $handler
         */
        return $handler
            ->with_reflector( Reflection::get_reflector( $instance ) )
            ->with_target( $instance )
            ->with_cache( false );
    }

    /**
     * Get the methods for a hook.
     *
     * @template TObj of object
     *
     * @param  Decorates_Handler<TObj>|ReflectionClass<TObj> $hook Hook instance or reflection.
     * @return array<string,ReflectionMethod>
     */
    protected function resolve_methods( Decorates_Handler|ReflectionClass $hook ): array {
        $refl = $hook instanceof ReflectionClass ? $hook : $hook->get_reflector();

        return Reflection::get_hookable_methods( $refl );
    }

    /**
     * Get the callbacks for a method.
     *
     * @template TObj of object
     *
     * @param  Decorates_Handler<TObj> $handler Handler instance.
     * @param  ReflectionMethod        $reflector Method reflection.
     *
     * @return array<int,Decorates_Callback<TObj,Decorates_Handler<TObj>>>
     */
    protected function resolve_method_callbacks( Decorates_Handler $handler, ReflectionMethod $reflector ): array {
        $callbacks = array();

        foreach ( Reflection::get_decorators( $reflector, Decorates_Callback::class ) as $cb ) {
            $callbacks[] = $this->save_hook( $cb->with_handler( $handler )->with_reflector( $reflector ) );
        }

        return $callbacks;
    }

    /**
     * Load callbacks for a handler.
     *
     * @template TObj of object
     *
     * @param  Decorates_Handler<TObj>                                     $handler Handler instance.
     * @param  array<int,Decorates_Callback<TObj,Decorates_Handler<TObj>>> $callbacks Callbacks to load.
     * @return Decorates_Handler<TObj>
     */
    public function load_callbacks( Decorates_Handler $handler, array $callbacks ): Decorates_Handler {
        $tokens = array();

        foreach ( $callbacks as $cb ) {
            $tokens[] = $this->save_hook( $cb )->get_token();
        }

        return $handler->with_callbacks( $tokens );
    }

    /**
     * Get a hook by classname
     *
     * @template TObj of object
     *
     * @param  class-string<TObj>|TObj $hook Hook classname.
     * @return bool
     */
    public function has_hook( string|object $hook ): bool {
        return $this->ctr()?->has( $this->get_token( $hook ) ) ?? false;
    }

    /**
     * Save a handler to the container.
     *
     * If the handler is not in the container, it will be saved.

     * @template TObj of object
     *
     * @param  Decorates_Handler<TObj> $handler Handler instance.
     * @return Decorates_Handler<TObj>
     */
    protected function save_handler( Decorates_Handler $handler ): Decorates_Handler {
        if ( $this->started() && $handler->get_target() && ! $this->ctr()->has( $handler->get_classname() ) ) {
            $this->ctr()->set( $handler->get_classname(), $handler->get_target() );
        }

        return $this->save_hook( $handler );
    }

    /**
     * Save a handler to the container.
     *
     * If the handler is not in the container, it will be saved.

     * @template TObj of Decorates_Hook
     * @phpstan-pure
     *
     * @param  TObj $hook Handler instance.
     * @return TObj
     */
    protected function save_hook( Decorates_Hook $hook ): Decorates_Hook {
        if ( ! $this->started() ) {
            return $hook;
        }

        // $hook = $hook->with_container( $this->ctr() );

        $token = $hook->get_token();

        if ( ! $this->ctr()->has( $token ) ) {
            $this->ctr()->set( $token, $hook );
        }

        return $hook;
    }

    /**
     * Get a hook by classname
     *
     * @template TObj of object
     *
     * @param  TObj|class-string<TObj> $hook Hook classname.
     * @return null|Decorates_Handler<TObj>|Decorates_Module<TObj>|Decorates_Callback<TObj,Decorates_Handler<TObj>>
     */
    private function get( string|object $hook ): ?Decorates_Hook {
        return $this->started() && $this->ctr()->has( $this->get_token( $hook ) )
            ? $this->ctr()->get( $this->get_token( $hook ) )
            : null;
    }

    /**
     * Get the container.
     *
     * @return ?Container
     */
    private function ctr(): ?Container {
        return $this->container ?? null;
    }

    /**
     * Is the container started.
     *
     * @return bool
     */
    private function started(): bool {
        if ( ! $this->ctr() ) {
            return false;
        }

        return (bool) ( $this->started ??= $this->ctr()->started() ? true : null );
    }
}
