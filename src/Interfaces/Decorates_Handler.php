<?php

namespace XWP\DI\Interfaces;

use ReflectionClass;
use XWP\DI\Interfaces\Decorates_Hook as InterfacesDecorates_Hook;

/**
 * Describes decorators that can be hooked into WordPress.
 *
 * @template THndlr of object
 *
 * @extends Decorates_Hook<THndlr,ReflectionClass<THndlr>>
 */
interface Decorates_Handler extends Decorates_Hook, Can_Handle {
    /**
     * Get the callback methods.
     *
     * @return array<int,Decorates_Callback<THndlr>>
     */
    public function get_callbacks(): array;
}
