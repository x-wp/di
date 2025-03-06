<?php
/**
 * Can_Handle_REST interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

/**
 * Describes decorators which handle REST Controllers.
 *
 * @template T of \XWP_REST_Controller
 * @extends Can_Handle<T>
 */
interface Can_Handle_REST extends Can_Handle {
    /**
     * Get the REST namespace.
     *
     * @return string
     */
    public function get_namespace(): string;

    /**
     * Get the REST basename.
     *
     * @return string
     */
    public function get_basename(): string;

    /**
     * Get the REST hook.
     *
     * @return string
     */
    public function get_rest_hook(): string;
}
