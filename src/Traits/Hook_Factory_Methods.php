<?php
/**
 * Hook_Factory_Methods trait file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Traits;

use ReflectionClass;
use ReflectionMethod;
use Reflector;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Hook;
use XWP\DI\Interfaces\Can_Import;
use XWP\DI\Interfaces\Can_Invoke;
use XWP\DI\Utils\Reflection;

trait Hook_Factory_Methods {
    /**
     * Fetch a module by classname from the container.
     *
     * @template T of object
     * @param  class-string<T> $module Module classname.
     * @return Can_Import<T>
     *
     * @throws \RuntimeException If the container is not set.
     */
    public function fetch_module( string $module ): Can_Import {
        return $this->fetch( $module, 'Module' );
    }

    /**
     * Get the handler for a class.
     *
     * @template T of object
     * @param  class-string<T>|T|array<string,mixed> $target Target class.
     * @return Can_Import<T>
     */
    public function get_module( string|object|array $target ): ?Can_Import {
        return $this->make_hook( $target );
    }

    /**
     * Fetch a handler by classname from the container.
     *
     * @template T of object
     * @param  class-string<T> $handler Handler classname.
     * @return Can_Handle<T>
     *
     * @throws \RuntimeException If the container is not set.
     */
    public function fetch_handler( string $handler ): Can_Handle {
        return $this->fetch( $handler, 'Handler' );
    }

    /**
     * Get the handler for a class.
     *
     * @template T of object
     * @param  class-string<T>|T|array<string,mixed> $target Target class.
     * @return Can_Handle<T>
     */
    public function make_handler( string|object|array $target ): ?Can_Handle {
        return $this->make_hook( $target );
    }

    /**
     * Fetch a hook by classname from the container.
     *
     * @param  string $hook_id Hook classname.
     * @return Can_Invoke<object,Can_Handle<object>>
     *
     * @throws \RuntimeException If the container is not set.
     */
    public function fetch_hook( string $hook_id ): Can_Invoke {
        return isset( $this->container )
            ? $this->container->get( $hook_id )
            : throw new \RuntimeException( 'Container not set.' );
    }

    /**
     * Get the hook callback decorators.
     *
     * @template T of object
     * @param  Can_Handle<T> $handler Handler instance.
     * @return array<int,Can_Invoke<T,Can_Handle<T>>>|array<int,string>
     */
    public function get_hooks( Can_Handle $handler ): array {
        $callbacks = array();
        foreach ( $this->get_methods( $handler ) as $refl ) {
            $callbacks[] = $this->get_callbacks( $handler, $refl );
        }

        return \array_merge( ...$callbacks );
    }

    /**
     * Get the hook callbacks for a method.
     *
     * @template T of object
     * @param  Can_Handle<T>    $h Handler instance.
     * @param  ReflectionMethod $r Method reflection.
     * @return array<int,Can_Invoke<T,Can_Handle<T>>>|array<int,string>
     */
    public function get_callbacks( Can_Handle $h, ReflectionMethod $r ): array {
        $cbs = array();
        $ctr = isset( $this->container );

        foreach ( Reflection::get_decorators( $r, Can_Invoke::class ) as $cb ) {
            $cb = $cb->with_handler( $h )->with_reflector( $r );

            if ( $ctr ) {
                $cb->with_container( $this->container );
                $this->container->set( $cb->get_token(), $cb );
            }

            $cbs[] = $ctr ? $cb->get_token() : $cb;
        }

        return $cbs;
    }

    /**
     * Get the hookable methods for a handler.
     *
     * @template T of object
     * @param  Can_Handle<T> $handler Handler instance.
     * @return array<string,ReflectionMethod>
     */
    public function get_methods( Can_Handle $handler ): array {
        return Reflection::get_hookable_methods( $handler->get_reflector() );
    }

    /**
     * Make a module Decorator.
     *
     * @template T of object
     *
     * @param  null|class-string<T>|array{args:array<string,mixed>, type:class-string<Can_Hook<T,Reflector>>, params: array{classname: class-string<T>}}|T|ReflectionClass<T> $target Target classname, instance, or reflection.
     * @return Can_Hook<T,Reflector>
     *
     * @throws \InvalidArgumentException If the target type is invalid.
     */
    public function make_hook( null|string|array|object $target ): Can_Hook {
        $hook = match ( true ) {
            \is_string( $target ) => $this->from_classname( $target, Can_Hook::class ),
            \is_object( $target ) => $this->from_instance( $target, Can_Hook::class ),
            \is_array( $target )  => $this->from_data( ...$target ),
            default               => throw new \InvalidArgumentException( 'Invalid target type.' ),
        };

        return isset( $this->container ) ? $hook->with_container( $this->container ) : $hook;
    }

    /**
     * Get the decorator params for a class.
     *
     * @template T of object
     * @param  class-string<T>                                                       $target Target class.
     * @param  class-string<Can_Invoke<T,Can_Handle<T>>|Can_Import<T>|Can_Handle<T>> $decorator Decorator type.
     * @return Can_Invoke<T,Can_Handle<T>>|Can_Import<T>|Can_Handle<T>
     */
    public function from_classname( string $target, string $decorator ): ?object {
        $refl = Reflection::get_reflector( $target );

        return Reflection::get_decorator( $refl, $decorator )
            ->with_reflector( $refl )
            ->with_classname( $target );
    }

    /**
     * Get the decorator params for a class.
     *
     * @template T of object
     * @param  T|Can_Invoke<T,Can_Handle<T>>|Can_Import<T>|Can_Handle<T>             $target    Target class.
     * @param  class-string<Can_Invoke<T,Can_Handle<T>>|Can_Import<T>|Can_Handle<T>> $decorator Decorator type.
     * @return Can_Invoke<T,Can_Handle<T>>|Can_Import<T>|Can_Handle<T>
     */
    public function from_instance( object $target, string $decorator ): ?object {
        if ( Reflection::class_implements( $target, $decorator ) ) {
            return $target;
        }

        $refl = Reflection::get_reflector( $target );
        $dec  = Reflection::get_decorator( $refl, $decorator );

        if ( isset( $this->container ) ) {
            $dec->with_container( $this->container );
        }

        return $dec->with_reflector( $refl )->with_target( $target );
    }

    /**
     * Create a decorator from a class name.
     *
     * @template T of object
     *
     * @param  class-string<Can_Hook<T,Reflector>> $type Decorator type.
     * @param  array<string,mixed>                 $args Decorator arguments.
     * @param  array{classname: class-string<T>}   $params Decorator params.
     * @return Can_Hook<T,Reflector>
     */
    public function from_data( string $type, array $args, array $params ): object {
        return ( new $type( ...$args ) )->with_data( $params );
    }

    /**
     * Get the decorator params for a class.
     *
     * @template T of object
     * @template TDec of Can_Invoke|Can_Handle|Can_Import
     * @param  class-string<T>|T  $target Target class.
     * @param  class-string<TDec> $decorator Decorator class.
     * @return null|array{
     *   args: array<string,mixed>,
     *   name: class-string<T>,
     *   type: class-string<TDec>,
     * }
     */
    public function get_params( string|object $target, string $decorator ): ?array {
        $attribute = Reflection::get_attribute( $target, $decorator );
        $classname = \is_object( $target ) ? $target::class : $target;

        if ( null === $attribute ) {
            return null;
        }

        return array(
            'args' => $attribute->getArguments(),
            'name' => $classname,
            'type' => $attribute->getName(),
        );
    }

    /**
     * Get the hook token.
     *
     * @param  string $target Target classname.
     * @param  string $prefix Token prefix.
     * @return string
     */
    protected function get_token( string $target, string $prefix ): string {
        return \sprintf( '%s-%s', \ucfirst( $prefix ), $target );
    }

    /**
     * Fetch a hook by classname from the container.
     *
     * @template T of object
     * @param  class-string<T> $classname classname.
     * @param  string          $prefix    Token prefix.
     * @return Can_Hook<T,Reflector>
     *
     * @throws \RuntimeException If the container is not set.
     */
    private function fetch( string $classname, string $prefix ): Can_Hook {
        if ( ! isset( $this->container ) ) {
            throw new \RuntimeException( 'Container not set.' );
        }

        $token = $this->get_token( $classname, $prefix );

        if ( $this->container->has( $token ) ) {
            return $this->container->get( $token );
        }

        $hook = $this->make_hook( $classname );

        $this->container->set( $token, $hook );

        return $hook;
    }
}
