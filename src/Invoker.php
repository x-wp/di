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
     * Handlers.
     *
     * @var array<class-string,Can_Handle>
     * @phpstan-ignore missingType.generics
     */
    protected array $handlers = array();

    /**
     * Hooks.
     *
     * @var array<class-string,array<string,array<int,Can_Invoke>>>
     * @phpstan-ignore missingType.generics
     */
    protected array $hooks = array();

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
     * Add a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $handler The handler to add.
     * @param  bool          $clear   Whether to clear existing hooks.
     * @return static
     */
    public function add_handler( Can_Handle $handler, bool $clear = true ): static {
        $this->handlers[ $handler->classname ] = $handler;

        if ( $clear ) {
            $this->hooks[ $handler->classname ] = array();
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
     * @return array<string,array<int,Can_Invoke<T>>>
     */
    public function get_hooks( string $classname ): array {
        return $this->has_hooks( $classname ) ? $this->hooks[ $classname ] : array();
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
        $handler = Handler_Factory::from_instance( $instance, $container );

        if ( $this->has_handler( $handler->classname ) ) {
            return $this;
        }

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
		if ( $this->has_hooks( $handler->classname ) ) {
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
     * @param  Can_Handle<T>     $handler The handler to register the method for.
     * @param  \ReflectionMethod $m       The method to register.
     * @return array<int,Can_Invoke<T>>
     */
	private function register_method( Can_Handle $handler, \ReflectionMethod $m ) {
		$hooks = array();

		foreach ( Reflection::get_decorators( $m, Can_Invoke::class ) as $hook ) {
			$hooks[] = $hook
                ->with_handler( $handler )
                ->with_target( $m->getName() )
                ->with_reflector( $m );
		}

		return $hooks;
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
                0,
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

        return $this;
    }

    /**
     * Load hooks for a handler.
     *
     * @template T of object
     * @param  Can_Handle<T>                          $handler The handler to load hooks for.
     * @param  array<string,array<int,Can_Invoke<T>>> $hooks The hooks to load.
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
