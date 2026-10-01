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
use XWP\DI\Decorators\Ajax_Action;
use XWP\DI\Decorators\CLI_Command;
use XWP\DI\Decorators\Dynamic_Filter;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler as Handler_Decorator;
use XWP\DI\Decorators\REST_Route;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Definition\HandlerDefinition;
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
     * Declaration metadata discovery.
     *
     * @var Discovery
     */
    private Discovery $discovery;

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
        $this->discovery = new Discovery();
    }

    // Keep metadata/runtime routing and identity guards together.
    // phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    /**
     * General hook creation method.
     *
     * Creates parsed and resolved hooks.
     *
     * @template TTgt of object
     *
     * @param  array{type: class-string<TTgt>, args: array<string,mixed>, params: array<string,mixed>} $hook Hook data.
     * @return Callback<object,Can_Handle<object>>|Handler<object>
     * @throws InvalidDefinition If the declaration is unsupported or a callback has no container.
     */
    public function make( array $hook ): Can_Hook|Callback {
        Discovery::assert_hook_metadata( $hook );
        $runtime = $this->handler_runtime_class( $hook['type'] );
        if ( $runtime ) {
            return new $runtime( HandlerDefinition::from_data( $hook ), $this->ctr() );
        }

        $runtime = $this->runtime_class( $hook['type'] );
        if ( ! $runtime ) {
            throw new InvalidDefinition( "Unsupported hook declaration: {$hook['type']}" );
        }
        if ( ! $this->ctr() ) {
            throw new InvalidDefinition( 'Container not set for callback runtime.' );
        }
        $rest_route = \is_a( $hook['type'], REST_Route::class, true );
        if ( $rest_route && ( ! isset( $hook['params']['tag'] ) || ! isset( $hook['params']['priority'] ) ) ) {
            // Older cache entries omit the handler-specific registration tag.
            $handler         = $this->ctr()->get( 'Hook-' . $hook['params']['classname'] );
            $hook['params'] += array(
                'priority' => $handler->get_priority() + 1,
                'tag'      => $handler->get_rest_hook(),
            );
        }

        return new $runtime( CallbackDefinition::from_data( $hook ), $this->ctr() );
    }
    // phpcs:enable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh

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
     * @return Can_Import<TObj>|HandlerDefinition
     */
    public function resolve_module( string $module ): Can_Import|HandlerDefinition {
        return $this->resolve_handler( $module, \XWP\DI\Decorators\Module::class );
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

        if ( $handler instanceof HandlerDefinition ) {
            $handler = $this->make( $handler->get_data() );
        }
        $this->save_hook( $handler );

        return $this->get( $handler->get_classname() ) ?? $handler;
    }

    /**
     * Resolve the handler for a hook.
     *
     * @template THnd of Handler_Decorator
     * @template TObj of object
     *
     * @param  class-string<TObj> $hook Hook classname or instance.
     * @param  class-string<THnd> $type Handler classname.
     * @return null|Can_Handle<TObj>|HandlerDefinition
     */
    public function resolve_handler( string $hook, string $type = Handler_Decorator::class ): Can_Handle|HandlerDefinition|null {
        $definition = $this->discovery->handler( $hook, $type );
        return $definition instanceof HandlerDefinition && $this->ctr()
            ? $this->make( $definition->get_data() )
            : $definition;
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
     * @return array<int,Can_Invoke<TObj,Can_Handle<TObj>>|Callback<TObj,Can_Handle<TObj>>|CallbackDefinition>
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
     * @param  Can_Handle<object>|HandlerDefinition $handler Handler metadata or runtime.
     * @return array<int,CallbackDefinition>
     */
    public function resolve_callbacks( Can_Handle|HandlerDefinition $handler ): array {
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
         * @var Can_Handle<TObj>|HandlerDefinition $handler
         */
        $handler = $this->get( $instance::class )
            ?? $this->resolve_handler( $instance::class )
            ?? $this->new_handler( $instance );

        if ( $handler instanceof HandlerDefinition ) {
            /** @var Can_Handle<TObj> $handler */
            $handler = $this->make( $handler->get_data() );
        }

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
        /** @var Handler<TObj> $runtime */
        $runtime = new Handler(
            new HandlerDefinition(
                $instance::class,
                priority: null,
                strategy: Handler_Decorator::INIT_USER,
                hookable: true,
            ),
            $this->ctr(),
        );
        return $runtime->with_target( $instance );
    }

    /**
     * Get the methods for a hook.
     *
     * @template TObj of object
     *
     * @param  Can_Handle<TObj>|HandlerDefinition|ReflectionClass<TObj> $hook Hook instance or reflection.
     * @return array<string,ReflectionMethod>
     */
    protected function resolve_methods( Can_Handle|HandlerDefinition|ReflectionClass $hook ): array {
        $refl = $hook instanceof HandlerDefinition ? new ReflectionClass(
            $hook->get_class(),
        ) : ( $hook instanceof ReflectionClass ? $hook : $hook->get_reflector() );

        return Reflection::get_hookable_methods( $refl );
    }

    /**
     * Get the callbacks for a method.
     *
     * @param  Can_Handle<object>|HandlerDefinition $handler Handler metadata or runtime.
     * @param  ReflectionMethod                     $reflector Method reflection.
     *
     * @return array<int,CallbackDefinition>
     */
    protected function resolve_method_callbacks( Can_Handle|HandlerDefinition $handler, ReflectionMethod $reflector ): array {
        $callbacks = array();

        foreach ( $this->discovery->callbacks( $handler, $reflector ) as $cb ) {
            $callbacks[] = $this->save_hook( $cb );
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

        $this->save_hook( $handler );

        return $this->get( $handler->get_classname() ) ?? $handler;
    }

    // Keep metadata/runtime routing and identity guards together.
    // phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    /**
     * Save a hook while retaining definition metadata for discovery.
     *
     * Existing token entries retain their runtime identity and invocation state.

     * @template TObj of Can_Hook|Callback|CallbackDefinition|HandlerDefinition
     *
     * @param  TObj $hook Hook instance.
     * @return TObj
     */
    protected function save_hook( Can_Hook|Callback|CallbackDefinition|HandlerDefinition $hook ): Can_Hook|Callback|CallbackDefinition|HandlerDefinition {
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

        if ( $hook instanceof CallbackDefinition || $hook instanceof HandlerDefinition ) {
            $this->ctr()->set( $token, $this->make( $hook->get_data() ) );
            return $hook;
        }

        // Supplied runtimes own their live listener state.
        $this->ctr()->set( $token, $hook );

        return $hook;
    }
    // phpcs:enable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh

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
     * Route declarations by their built-in metadata hierarchy.
     *
     * @param  class-string $type Decorator class.
     * @return class-string<Callback<object,Can_Handle<object>>>|null
     */
    private function runtime_class( string $type ): ?string {
        return match ( true ) {
            \is_a( $type, REST_Route::class, true ) => REST_Callback::class,
            \is_a( $type, CLI_Command::class, true ) => CLI_Callback::class,
            \is_a( $type, Ajax_Action::class, true ) => Ajax_Callback::class,
            \is_a( $type, Dynamic_Filter::class, true ) => Dynamic_Callback::class,
            \is_a( $type, Filter::class, true ) => Callback::class,
            default => null,
        };
    }

    /**
     * Route handler declarations to their runtime specialization.
     *
     * @param class-string $type Decorator class.
     * @return class-string<Handler<object>>|null
     */
    private function handler_runtime_class( string $type ): ?string {
        return match ( true ) {
            \is_a( $type, \XWP\DI\Decorators\Module::class, true ) => Module::class,
            \is_a( $type, \XWP\DI\Decorators\Ajax_Handler::class, true ) => Ajax_Handler::class,
            \is_a( $type, \XWP\DI\Decorators\REST_Handler::class, true ) => REST_Handler::class,
            \is_a( $type, \XWP\DI\Decorators\CLI_Handler::class, true ) => CLI_Handler::class,
            \is_a( $type, Handler_Decorator::class, true ) => Handler::class,
            default => null,
        };
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
