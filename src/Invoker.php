<?php
/**
 * Invoker class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use XWP\DI\Decorators\Module;
use XWP\DI\Handler_Factory;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;
use XWP\DI\Utils\Reflection;
use XWP\Helper\Traits\Singleton_Ex;

/**
 * Manages handlers and hooks.
 *
 * @since 1.0.0
 */
class Invoker {
    use Singleton_Ex;

    /**
     * Is WP in debug mode.
     *
     * @var bool
     */
    protected bool $debug = false;

    /**
     * Handlers.
     *
     * @var array<class-string,Can_Handle>
     * @phpstan-ignore missingType.generics
     */
    protected array $handlers = array();

    /**
     * Registered hooks.
     *
     * @var array<class-string,string>
     */
    protected array $reg_hooks = array();

    /**
     * Hooks.
     *
     * @var array<class-string,array<string,array<int,Can_Invoke>>>
     * @phpstan-ignore missingType.generics
     */
    protected array $hooks = array();

    /**
     * Constructor.
     */
    protected function __construct() {
        $this->debug = \defined( 'WP_DEBUG' ) && WP_DEBUG;
    }

    /**
     * Get debug info.
     *
     * @return array<class-string,string>
     */
    public function debug_info(): array {
        return $this->reg_hooks;
    }

    /**
     * Check if a handler is registered.
     *
     * @template T of object
     * @param  class-string<T> $classname The handler classname.
     * @return bool
     */
    public function has_handler( string $classname ): bool {
        return isset( $this->handlers[ $classname ] );
    }

    /**
     * Check if a handler is registered.
     *
     * @template T of object
     * @param  class-string<T> $classname The handler classname.
     * @return null|Can_Handle<T>
     */
    public function get_handler( string $classname ): ?Can_Handle {
        return $this->has_handler( $classname ) ? $this->handlers[ $classname ] : null;
    }

    /**
     * Get all handlers.
     *
     * @return array<class-string,Can_Handle<object>>
     */
    public function all_handlers(): array {
        return $this->handlers;
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
        $cname = $handler->classname;

        $this->handlers[ $cname ] = $handler;

        if ( $clear ) {
            $this->hooks[ $cname ] = array();
        }

        return $this;
    }

    /**
     * Check if a handler has hooks.
     *
     * @template T of object
     * @param  class-string<T> $classname The handler classname.
     * @return bool
     */
    public function has_hooks( string $classname ): bool {
        return isset( $this->hooks[ $classname ] ) && \count( $this->hooks[ $classname ] ) > 0;
    }

    /**
     * Get hooks for a handler.
     *
     * @template T of object
     * @param  class-string<T> $classname The handler classname.
     * @return array<string,array<int,Can_Invoke<T,Can_Handle<T>>>>
     */
    public function get_hooks( string $classname ): array {
        return $this->has_hooks( $classname ) ? $this->hooks[ $classname ] : array();
    }

    /**
     * Get all hooks.
     *
     * @return array<class-string,array<string,array<int,Can_Invoke<object,Can_Handle<object>>>>>
     */
    public function all_hooks(): array {
        return $this->hooks;
    }

    /**
     * Register a module.
     *
     * @template T of object
     * @param  class-string<T> $classname The module classname.
     * @return Module<T>
     */
    public function register_module( string $classname ): Module {
        return $this->register_handler( $classname )->get_handler( $classname );
    }

    /**
     * Register a module.
     *
     * @template T of object
     * @param  class-string<T> ...$classnames Handler classnames.
     * @return static
     */
    public function register_handlers( string ...$classnames ): static {
        foreach ( $classnames as $classname ) {
            $this->register_handler( $classname );
        }

        return $this;
    }

    /**
     * Register a handler.
     *
     * @template T of object
     * @param  class-string<T> $classname The handler classname.
     * @return static
     */
    public function register_handler( string $classname ): static {
        if ( $this->has_handler( $classname ) ) {
            return $this;
        }

        $handler = Handler_Factory::from_classname( $classname );

        return match ( $handler->strategy ) {
            $handler::INIT_EARLY       => $this
                                        ->add_handler( $handler )
                                        ->init_handler( $handler )
                                        ->queue_methods( $handler ),
            $handler::INIT_IMMEDIATELY => $this
                                        ->add_handler( $handler )
                                        ->init_handler( $handler )
                                        ->register_methods( $handler )
                                        ->invoke_methods( $handler ),
            $handler::INIT_ON_DEMAND,
            $handler::INIT_JUST_IN_TIME => $this
                                        ->add_handler( $handler )
                                        ->queue_methods( $handler ),
            default                     => $this
                                        ->add_handler( $handler )
                                        ->queue_handler( $handler ),
        };
    }

    /**
     * Load a handler.
     *
     * @template T of object
     * @param  T           $instance  The handler instance.
     * @param  null|string $container The container to use.
     * @return static
     */
    public function load_handler( object $instance, ?string $container = null ): static {
        if ( $this->has_handler( Handler_Factory::get_target( $instance ) ) ) {
            return $this;
        }

        $handler = Handler_Factory::from_instance( $instance, $container );

        return $this
            ->add_handler( $handler )
            ->register_methods( $handler )
            ->invoke_methods( $handler );
    }

    /**
     * Enqueue a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $handler The handler to enqueue.
     * @return static
     */
    protected function queue_handler( Can_Handle $handler ): static {
        \add_action(
            $handler->tag,
            function () use ( $handler ) {
                $this
                    ->init_handler( $handler )
                    ->register_methods( $handler )
                    ->invoke_methods( $handler );
            },
            $handler->priority,
            0,
        );

        return $this;
    }

    /**
     * Initialize a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $handler The handler to initialize.
     * @return static
     */
    protected function init_handler( Can_Handle $handler ): static {
        $handler->load();

        if ( $this->debug ) {
            $this->reg_hooks[ $handler->classname ] ??= \current_action();
        }

        return $this;
    }

    /**
     * Register methods.
     *
     * @template T of object
     * @param  Can_Handle<T> $handler The handler to register methods for.
     * @return static
     */
    protected function register_methods( Can_Handle $handler ): static {
		if ( $this->has_hooks( $handler->classname ) || ! $handler->is_hookable() ) {
			return $this;
        }

        foreach ( Reflection::get_hookable_methods( $handler->reflector ) as $m ) {
            $hooks = $this->register_method( $handler, $m );

			if ( ! $hooks ) {
				continue;
			}

			$this->hooks[ $handler->classname ][ $m->getName() ] = $hooks;
        }

        return $this;
    }

    /**
     * Register a method.
     *
     * @template T of object
     * @template H of Can_Handle<T>
     *
     * @param  H                 $handler The handler to register the method for.
     * @param  \ReflectionMethod $method       The method to register.
     * @return array<int,Can_Invoke<T,H>>
     */
	private function register_method( Can_Handle $handler, \ReflectionMethod $method ) {
        return \array_map(
            static fn( $h ) => $h
                ->with_reflector( $method )
                ->with_handler( $handler ),
            Reflection::get_decorators( $method, Can_Invoke::class ),
        );
	}

    /**
     * Enqueue methods.
     *
     * @template T of object
     * @param  Can_Handle<T> $handler The handler to enqueue methods for.
     * @return static
     */
    protected function queue_methods( Can_Handle $handler ): static {
        if ( $handler->is_lazy() ) {
            \add_action(
                $handler->lazy_hook,
                function () use ( $handler ) {
                    $this->init_handler( $handler );
                },
                -1,
                0,
            );
        }

        \add_action(
            $handler->tag,
            function () use ( $handler ) {
                $this
                    ->register_methods( $handler )
                    ->invoke_methods( $handler );
            },
            $handler->priority,
            0,
        );

        return $this;
    }

    /**
     * Invoke methods.
     *
     * @template T of object
     * @param  Can_Handle<T> $handler The handler to invoke methods for.
     * @return static
     */
    public function invoke_methods( Can_Handle $handler ): static {
        foreach ( $this->hooks[ $handler->classname ] as $hooks ) {
			foreach ( $hooks as $hook ) {
				$hook->load();
			}
		}

        \do_action( "xwp_di_hooks_loaded_{$handler->classname}", $handler );

        return $this;
    }

    /**
     * Load hooks for a handler.
     *
     * @template T of object
     * @template H of Can_Handle<T>
     * @param  H                                        $handler The handler to load hooks for.
     * @param  array<string,array<int,Can_Invoke<T,H>>> $hooks The hooks to load.
     * @return static
     */
    public function load_hooks( Can_Handle $handler, array $hooks ): static {
        if ( ! $this->has_handler( $handler->classname, ) ) {
            $this->add_handler( $handler, false );
        }

        if ( ! $this->has_hooks( $handler->classname ) ) {
            $this->hooks[ $handler->classname ] = $hooks;
        }

        return $this;
    }
}
