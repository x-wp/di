<?php

namespace XWP\DI\Interfaces;

use Reflector;

/**
 * Describes decorators that can be hooked into WordPress.
 *
 * @template THndlr of object
 * @template TRflct of Reflector
 *
 * @extends Can_Hook<THndlr,TRflct>
 */
interface Decorates_Hook extends Can_Hook {
    /**
     * Get the hook definition.
     *
     * @return array{
     *   construct: array<string,mixed>,
     *   metatype: class-string<static>,
     *   hydrate: bool,
     * }
     */
    public function get_data(): array;

    /**
     * Get the reflector.
     *
     * @return TRflct
     */
    public function get_reflector(): Reflector;

    /**
     * Set the reflector
     *
     * @param  TRflct $reflector Reflector instance.
     * @return static
     */
    public function with_reflector( Reflector $reflector ): static;

    /**
     * Set the classname of the handler.
     *
     * @param  class-string<THndlr> $classname Handler classname.
     * @return static
     */
    public function with_classname( string $classname ): static;
}
