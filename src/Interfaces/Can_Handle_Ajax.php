<?php
/**
 * Can_Handle_Ajax interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

/**
 * Defines the interface for handling ajax actions.
 *
 * @template T of object
 * @extends Can_Handle<T>
 */
interface Can_Handle_Ajax extends Can_Handle {
    /**
     * Get the ajax prefix.
     *
     * @return string
     */
    public function get_prefix(): string;
}
