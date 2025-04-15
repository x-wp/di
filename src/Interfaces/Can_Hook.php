<?php
/**
 * Can_Hook interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

/**
 * Describes decorators that can be hooked into WordPress.
 *
 * @template THndlr of object
 */
interface Can_Hook extends Has_Context {
    /**
     * Get the hook token.
     *
     * @return string
     */
    public function get_token(): string;

    /**
     * Get the handler classname.
     *
     * @return class-string<THndlr>
     */
    public function get_classname(): string;
}
