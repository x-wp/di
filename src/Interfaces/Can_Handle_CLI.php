<?php
/**
 * Can_Handle_CLI interface file.
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
interface Can_Handle_CLI extends Can_Handle {
    /**
     * Get the namespace for the CLI command.
     *
     * @return string
     */
    public function get_namespace(): string;
}
