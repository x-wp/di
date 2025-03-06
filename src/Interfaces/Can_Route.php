<?php

namespace XWP\DI\Interfaces;

use Closure;

/**
 * Describes decorators which can route.
 *
 * @template T of \XWP_REST_Controller
 * @template H of Can_Handle_REST<T>
 * @extends Can_Invoke<T,H>
 */
interface Can_Route extends Can_Invoke {
    /**
     * Set the route priority.
     *
     * @param  int $priority Priority.
     * @return static
     */
    public function with_priority( int $priority ): static;

    /**
     * Set the route tag.
     *
     * @param  string $tag Tag.
     * @return static
     */
    public function with_tag( string $tag ): static;

    /**
     * Get the route.
     *
     * @return string
     */
    public function get_route(): string;

    /**
     * Get the route guard.
     *
     * @return string|array{0: T, 1:string}
     */
    public function get_guard(): string|array;

    /**
     * Get the route callback.
     *
     * @return Closure|array{0: T, 1: string}
     */
    public function get_callback(): array|Closure;

    /**
     * Get the route methods.
     *
     * @return string
     */
    public function get_methods(): string;
}
