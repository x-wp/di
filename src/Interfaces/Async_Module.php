<?php
/**
 * Async_Module interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

/**
 * Asynchronous module interface.
 */
interface Async_Module {
    /**
     * Configure the async module.
     *
     * @return array<string,mixed>
     */
    public static function configure_async(): array;
}
