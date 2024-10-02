<?php
/**
 * Handler_Factory class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use XWP\DI\Decorators\Handler;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Utils\Reflection;

/**
 * Creates handlers.
 *
 * @since 1.0.0
 */
class Handler_Factory {
    /**
     * Create a handler from a classname.
     *
     * @template T of object
     * @param  class-string<T> $classname The handler classname.
     * @return Can_Handle<T>
     */
    public static function from_classname( string $classname ): Can_Handle {
        $refl = Reflection::get_reflector( $classname );

        return Reflection::get_decorator( $refl, Can_Handle::class )
            ->with_classname( $classname )
            ->with_reflector( $refl );
    }

    /**
     * Create a handler from an instance.
     *
     * @template T of object
     * @param  Can_Handle<T>|T $instance  Handler instance or class instance.
     * @param  ?string         $container The container ID.
     * @return Can_Handle<T>
     */
    public static function from_instance( object $instance, ?string $container = null ): Can_Handle {
        if ( Reflection::class_implements( $instance, Can_Handle::class ) ) {
			return $instance;
		}

        $refl    = Reflection::get_reflector( $instance );
        $handler = Reflection::get_decorator( $refl, Can_Handle::class )
                ??
                self::new_handler( $container );

        return $handler->with_reflector( $refl )->with_container( $container )->with_target( $instance );
    }

    /**
     * Create a new handler.
     *
     * @param  ?string $container The container ID.
     * @return Can_Handle<object>
     */
    public static function new_handler( ?string $container = null ): Can_Handle {
        return new Handler( strategy: Handler::INIT_DYNAMICALY, container: $container );
    }
}
