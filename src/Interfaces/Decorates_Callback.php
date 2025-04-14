<?php

namespace XWP\DI\Interfaces;

use ReflectionMethod;

/**
 * Defines decorators that can invoke WordPress hooks.
 *
 * @template TInst of object
 * @extends Decorates_Hook<TInst,ReflectionMethod>
 */
interface Decorates_Callback extends Decorates_Hook {
    public function with_handler( Decorates_Handler $handler ): static;
}
