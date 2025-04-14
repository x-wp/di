<?php

namespace XWP\DI\Interfaces;

use Decorates_Hook;
use ReflectionClass;

/**
 * Describes decorators that can be hooked into WordPress.
 *
 * @template THndlr of object
 *
 * @extends Decorates_Handler<THndlr>
 */
interface Decorates_Module extends Decorates_Handler, Can_Import {
    /**
     * Get the module imports.
     *
     * @return array<int,Decorates_Module<object>>
     */
    public function get_imports(): array;

    /**
     * Get the module handlers.
     *
     * @return array<int,Decorates_Handler<object>>
     */
    public function get_handlers(): array;

    /**
     * Get the module definition.
     *
     * @return array<string,mixed>
     */
    public function get_configuration(): array;

    /**
     * Get the auto-wired services.
     *
     * @return array<int,class-string>
     */
    public function get_services(): array;
}
