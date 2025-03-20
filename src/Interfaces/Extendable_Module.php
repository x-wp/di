<?php
/**
 * Extendable_Module interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

/**
 * Defines the application module which can be extended.
 */
interface Extendable_Module {
    /**
     * Define the extendable configuration.
     *
     * @return array<string,mixed>
     */
    public static function define(): array;
}
