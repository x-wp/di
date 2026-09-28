<?php //phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped, Generic.Commenting.DocComment.MissingShort
/**
 * Factory class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use DI\Definition\Exception\InvalidDefinition;
use ReflectionClass;
use ReflectionMethod;
use XWP\DI\Container;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Hook;
use XWP\DI\Interfaces\Can_Import;
use XWP\DI\Interfaces\Can_Invoke;
use XWP\DI\Traits\Hook_Token_Methods;
use XWP\DI\Utils\Reflection;

/**
 * Factory for creating and resolving hooks.
 *
 * @internal Hook runtime factory detail.
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
     * General hook creation method.
     *
     * Creates parsed and resolved hooks.
     *
     * @template TTgt of Can_Hook
     *
     * @param  array{type: class-string<TTgt>, args: array<string,mixed>, params: array<string,mixed>} $hook Hook data.
     * @return TTgt<object,\Reflector>|Callback<object,Can_Handle<object>>
     */
    public function make( array $hook ): Can_Hook|Callback {
        if ( $this->ctr() && $this->is_plain_callback( $hook['type'] ) ) {
            return new Callback( CallbackDefinition::from_data( $hook ), $this->ctr() );
        }

        return ( new $hook['type']( ...$hook['args'] ) )
            ->with_data( $hook['params'] )->with_container( $this->ctr() );
    }

    /**
     * Get a module by classname
     *
     * @template TObj of object
     *
     * @param  class-string<TObj> $module Module classname.
     * @return Can_Import<TObj>
     */
    public function get_module( string $module ): Can_Import {
        /** @disregard P1006 */
        return $this->get_handler( $module );
    }

    /**
     * Resolve a module by classname
     *
     * @template TObj of object
     *
     * @param  class-string<TObj> $module Module classname.
     * @return Can_Import<TObj>
     */
    public function resolve_module( string $module ): Can_Import {
        return $this->resolve_handler( $module, Can_Import::class );
    }

    /**
     * Get a handler by classname
     *
     * @template TObj of object
     *
     * @param  class-string<TObj> $target Handler classname.
     * @return Can_Handle<TObj>
     *
     * @throws InvalidDefinition If the handler is not found.
     */
    public function get_handler( string $target ): Can_Handle {
        $handler = $this->get( $target )
            ?? $this->resolve_handler( $target )
            ?? throw new InvalidDefinition( "Handler not found: {$target}" );

        return $this->save_hook( $handler );
    }

    /**
     * Resolve the handler for a hook.
     *
     * @template THnd of Can_Import|Can_Handle
     * @template TObj of object
     *
     * @param  class-string<TObj> $hook Hook classname or instance.
     * @param  class-string<THnd> $type Handler classname.
     * @return null|THnd
     */
    public function resolve_handler( string $hook, string $type = Can_Handle::class ): ?Can_Handle {
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
     * @return Can_Invoke<TObj,Can_Handle<TObj>>|Callback<TObj,Can_Handle<TObj>>
     */
    public function get_hook( string $hook ): Can_Invoke|Callback {
        return $this->get( $hook );
    }

    /**
     * Get handler callbacks.
     *
     * @template TObj of object
     *
     * @param  Can_Handle<TObj> $handler Handler instance.
     * @return array<int,Can_Invoke<TObj,Can_Handle<TObj>>|Callback<TObj,Can_Handle<TObj>>>
     *
     * @throws InvalidDefinition If the container is not set.
     */
    public function get_callbacks( Can_Handle $handler ): array {
        if ( null === $handler->get_callbacks() ) {
            $callbacks = $this->resolve_callbacks( $handler );

            return $this->started()
                ? \array_map( fn( $cb ) => $this->ctr()->get( $cb->get_token() ), $callbacks )
                : $callbacks;
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
     * @param  Can_Handle<TObj> $handler Handler instance.
     * @return array<int,Can_Invoke<TObj,Can_Handle<TObj>>>
     */
    public function resolve_callbacks( Can_Handle $handler ): array {
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
     * @return Can_Handle<TObj>
     */
    public function load_handler( object $instance ): Can_Handle {
        /**
         * Handler instance.
         *
         * @var Can_Handle<TObj> $handler
         */
        $handler = $this->get( $instance::class )
            ?? $this->resolve_handler( $instance::class )
            ?? $this->new_handler( $instance );

        if ( null === $handler->get_target() ) {
            $handler->with_target( $instance );
        }

        return $this->save_handler( $handler );
    }

    /**
     * Creates a handler definition for an existing instance.
     *
     * @template TObj of object
     *
     * @param  TObj $instance Instance to load the handler for.
     * @return Can_Handle<TObj>
     */
    public function create_handler( object $instance ): Can_Handle {
        return $this->save_handler( $this->new_handler( $instance ) );
    }

    /**
     * Load callbacks for a handler.
     *
     * @template TObj of object
     *
     * @param  Can_Handle<TObj>                                                             $handler Handler instance.
     * @param  array<int,Can_Invoke<TObj,Can_Handle<TObj>>|Callback<TObj,Can_Handle<TObj>>> $callbacks Callbacks to load.
     * @return Can_Handle<TObj>
     */
    public function load_callbacks( Can_Handle $handler, array $callbacks ): Can_Handle {
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
     * Create a new handler instance.
     *
     * @template TObj of object
     *
     * @param TObj $instance Handler instance.
     * @return Can_Handle<TObj>
     */
    protected function new_handler( object $instance ): Can_Handle {
        $handler = new Handler( strategy: Handler::INIT_USER, hookable: true );

        /**
         * Handler instance.
         *
         * @var Can_Handle<TObj> $handler
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
     * @param  Can_Handle<TObj>|ReflectionClass<TObj> $hook Hook instance or reflection.
     * @return array<string,ReflectionMethod>
     */
    protected function resolve_methods( Can_Handle|ReflectionClass $hook ): array {
        $refl = $hook instanceof ReflectionClass ? $hook : $hook->get_reflector();

        return Reflection::get_hookable_methods( $refl );
    }

    /**
     * Get the callbacks for a method.
     *
     * @template TObj of object
     *
     * @param  Can_Handle<TObj> $handler Handler instance.
     * @param  ReflectionMethod $reflector Method reflection.
     *
     * @return array<int,Can_Invoke<TObj,Can_Handle<TObj>>>
     */
    protected function resolve_method_callbacks( Can_Handle $handler, ReflectionMethod $reflector ): array {
        $callbacks = array();

        foreach ( Reflection::get_decorators( $reflector, Can_Invoke::class ) as $cb ) {
            $callbacks[] = $this->save_hook( $cb->with_handler( $handler )->with_reflector( $reflector ) );
        }

        return $callbacks;
    }

    /**
     * Save a handler to the container.
     *
     * If the handler is not in the container, it will be saved.

     * @template TObj of object
     *
     * @param  Can_Handle<TObj> $handler Handler instance.
     * @return Can_Handle<TObj>
     */
    protected function save_handler( Can_Handle $handler ): Can_Handle {
        if ( $this->started() && $handler->get_target() && ! $this->ctr()->has( $handler->get_classname() ) ) {
            $this->ctr()->set( $handler->get_classname(), $handler->get_target() );
        }

        return $this->save_hook( $handler );
    }

    /**
     * Save a hook while retaining decorator metadata for discovery.
     *
     * Existing token entries retain their runtime identity and invocation state.

     * @template TObj of Can_Hook|Callback
     *
     * @param  TObj $hook Hook instance.
     * @return TObj
     */
    protected function save_hook( Can_Hook|Callback $hook ): Can_Hook|Callback {
        if ( ! $this->started() ) {
            return $hook;
        }

        if ( $hook instanceof Can_Hook ) {
            $hook = $hook->with_container( $this->ctr() );
        }

        $token = $hook->get_token();

        if ( $this->ctr()->has( $token ) ) {
            return $hook;
        }

        $runtime = $hook instanceof Filter && $this->is_plain_callback( $hook::class )
            ? $this->make( $hook->get_data() )
            : $hook;

        $this->ctr()->set( $token, $runtime );

        return $hook;
    }

    /**
     * Get a hook by classname
     *
     * @template TObj of object
     *
     * @param  TObj|class-string<TObj> $hook Hook classname.
     * @return null|Can_Handle<TObj>|Can_Import<TObj>|Can_Invoke<TObj,Can_Handle<TObj>>|Callback<TObj,Can_Handle<TObj>>
     */
    private function get( string|object $hook ): Can_Hook|Callback|null {
        return $this->started() && $this->ctr()->has( $this->get_token( $hook ) )
            ? $this->ctr()->get( $this->get_token( $hook ) )
            : null;
    }

    /**
     * Keep specialized decorators on their existing runtime path.
     *
     * @param  class-string $type Decorator class.
     * @return bool
     */
    private function is_plain_callback( string $type ): bool {
        return \in_array( $type, array( Filter::class, Action::class ), true );
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
