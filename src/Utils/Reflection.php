<?php
/**
 * Reflection class file.
 *
 * @package eXtended WordPress
 */

namespace XWP\DI\Utils;

use ReflectionMethod as Method;
use XWP\DI\Attributes\Module;
use XWP\DI\Interfaces\Can_Invoke;
use XWP\DI\Traits\Accessible_Hook_Methods;

/**
 * Reflection utilities.
 */
final class Reflection extends \XWP\Helper\Classes\Reflection {
    /**
     * Get the hooked methods for a handler.
     *
     * @template T of object
     * @param  \ReflectionClass<T> $r The reflection class to get the methods for.
     * @return array<string,Method>
     */
    public static function get_hookable_methods( \ReflectionClass $r ): array {
        $methods = array();

        foreach ( $r->getMethods( self::get_method_types( self::class_uses_deep( $r->getName() ) ) ) as $m ) {
            if ( ! self::is_method_hookable( $m ) ) {
                continue;
            }

            $methods[ $m->getName() ] = $m;
        }

        return $methods;
    }

    /**
     * Get the method types to include.
     *
     * @param  array<class-string> $traits The traits to check.
     * @return int
     */
    public static function get_method_types( array $traits ): int {
        return \in_array( Accessible_Hook_Methods::class, $traits, true )
            ? Method::IS_PUBLIC | Method::IS_PRIVATE | Method::IS_PROTECTED & ~Method::IS_STATIC
            : Method::IS_PUBLIC & ~Method::IS_STATIC;
    }

    /**
     * Check if a method is hookable.
     *
     * @param  Method $m The method to check.
     * @return bool
     */
    private static function is_method_hookable( Method $m, ): bool {
        $ignore = array( '__call', '__callStatic', 'check_method_access', 'is_method_valid', 'get_registered_hooks', '__construct' );

        return ! \in_array( $m->getName(), $ignore, true );
    }

    private static function is_classname( mixed $tgt ): bool {
        return \is_string( $tgt ) && \class_exists( $tgt );
    }

    public function get_module( string|array|callable $module ): ?Module {
        return match ( true ) {
            $this->is_classname( $module ) => $this->get_decorator( $module, Module::class )->with_classname(
                $module,
            ),
            $this->is_callable( $module ) => \call_user_func_array( $module, array() ),
            \is_a( $module, Module::class, true ) => $module,
        };
    }
}
