<?php
/**
 * Can_Handle interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

use DI\Container;
use ReflectionClass;

/**
 * Defines decorators that can handle WordPress hooks.
 *
 * @template THndlr of object
 *
 * @property-read string $lazy_hook Lazy initialization hook.
 * @property-read string $strategy The initialization strategy.
 * @property-read THndlr $target The handler instance.
 * @property-read class-string<THndlr> $classname The handler classname.
 * @property-read ReflectionClass<THndlr> $reflector The handler reflection.
 *
 * @extends Can_Hook<THndlr,ReflectionClass<THndlr>>
 *
 * @since 1.0.0
 */
interface Can_Handle extends Can_Hook {
    public const INIT_EARLY        = 'early';
    public const INIT_IMMEDIATELY  = 'immediately';
    public const INIT_ON_DEMAND    = 'on-demand';
    public const INIT_JUST_IN_TIME = 'just-in-time';
    public const INIT_DYNAMICALY   = 'dynamically';
    public const INIT_DEFFERED     = 'deferred';

    /**
     * Set the handler instance.
     *
     * @param  THndlr $instance Handler instance.
     * @return static
     */
    public function with_target( object $instance ): static;

    /**
     * Set the classname.
     *
     * @param  class-string<THndlr> $classname Handler classname.
     * @return static
     */
    public function with_classname( string $classname ): static;

    /**
     * Set the container instance.
     *
     * @param  ?string $container Container ID.
     * @return static
     */
    public function with_container( ?string $container ): static;

    /**
     * Get the handler instance.
     *
     * @return THndlr|null
     */
    public function get_target(): ?object;

    /**
     * Is the handler lazy loaded?
     *
     * @return bool
     */
    public function is_lazy(): bool;
}
