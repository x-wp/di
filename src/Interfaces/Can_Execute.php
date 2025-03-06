<?php
/**
 * Can_Execute interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

use Closure;

/**
 * Defines the interface for executing actions.
 *
 * @template T of object
 * @template H of Can_Handle_CLI<T>
 * @extends Can_Invoke<T,H>
 */
interface Can_Execute extends Can_Invoke {
    /**
     * Get the before invoke callback.
     *
     * @return ?Closure
     */
    public function get_before_invoke(): ?Closure;

    /**
     * Get the after invoke callback.
     *
     * @return ?Closure
     */
    public function get_after_invoke(): ?Closure;

    /**
     * Get the full command.
     *
     * @return string
     */
    public function get_command(): string;

    /**
     * Get the subcommand.
     *
     * @return string
     */
    public function get_subcommand(): string;

    /**
     * Get the long description.
     *
     * @return string|null
     */
    public function get_longdesc(): ?string;

    /**
     * Get the short description.
     *
     * @return string|null
     */
    public function get_shortdesc(): ?string;
}
