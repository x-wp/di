<?php
/**
 * Extension_Module interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

/**
 * Defines the application module which extend the root application container.
 */
interface Extension_Module {
    /**
     * Extend the container definition.
     *
     * @return array<string,mixed>
     */
    public static function extend(): array;
}
