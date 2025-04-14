<?php

namespace XWP\DI\Interfaces;

/**
 * Module interface file.zs
 *
 * @template T of object
 */
interface Invokes_Module {
    /**
     * Get the module imports.
     *
     * @return array<int,Invokes_Module<object>>
     */
    public function get_imports(): array;

    /**
     * Get the module handlers.
     *
     * @return array<int,Invokes_Handler<object>>
     */
    public function get_handlers(): array;
}
