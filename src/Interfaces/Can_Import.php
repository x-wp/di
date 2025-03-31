<?php
/**
 * Can_Import interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

/**
 * Defines decorators that are modules.
 *
 * @template THndlr of object
 * @extends Can_Handle<THndlr>
 */
interface Can_Import extends Can_Handle {
    /**
     * Get the module definition.
     *
     * @return array<string,mixed>
     */
    public function get_configuration(): array;

    /**
     * Get the module imports.
     *
     * @return array<int,class-string<object>>
     */
    public function get_imports(): array;

    /**
     * Get the module handlers.
     *
     * @return array<int,class-string<object>>
     */
    public function get_handlers(): array;

    /**
     * Get the auto-wired services.
     *
     * @return array<int,class-string>
     */
    public function get_services(): array;
}
