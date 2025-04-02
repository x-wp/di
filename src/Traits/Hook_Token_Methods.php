<?php
/**
 * Hook_Token_Methods trait file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Traits;

use ReflectionClass;
use ReflectionMethod;
use Reflector;
use XWP\DI\Interfaces\Can_Hook;

/**
 * Methods for working with hook tokens.
 */
trait Hook_Token_Methods {
    /**
     * Get the target classname.
     *
     * @template T of object
     *
     * @param  T|class-string<T>|Can_Hook<T,Reflector>|ReflectionClass<T>|ReflectionMethod $instance Instance to get the target for.
     * @return class-string<T>
     */
    public function get_target( object|string $instance ): string {
        return match ( true ) {
            $instance instanceof Can_Hook         => $instance->get_classname(),
            $instance instanceof ReflectionClass  => $instance->getName(),
            $instance instanceof ReflectionMethod => $instance->class,
            \is_string( $instance )               => $instance,
            default                               => $instance::class,
        };
    }

    /**
     * Get the injection token for a hook.
     *
     * @template T of object
     *
     * @param  class-string<T>|T|Can_Hook<T,Reflector>|ReflectionClass<T> $hook Hook classname, instance, or reflection.
     * @return string
     */
    public function get_token( string|object $hook ): string {
        static $regex;

        $regex ??= '/^(' . \preg_quote( XWP_DI_TOKEN_PREFIX, '/' ) . ')*/';

        $target = \is_object( $hook )
            ? $this->get_target( $hook )
            : \preg_replace( $regex, '', $hook );

        return \XWP_DI_TOKEN_PREFIX . $target;
    }
}
