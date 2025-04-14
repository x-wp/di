<?php

namespace XWP\DI\Interfaces;

use ReflectionClass;
use XWP\DI\Decorators\Infuse;

/**
 * Defines decorators that can handle WordPress hooks.
 *
 * @template THndlr of object
 *
 * @extends Invokes_Hook<THndlr,ReflectionClass<THndlr>>
 *
 * @since 1.0.0
 */
interface Invokes_Handler extends Invokes_Hook, Can_Handle {
    /**
     * Get the handler instance.
     *
     * @return THndlr|null
     */
    public function get_target(): ?object;

    /**
     * Set the handler instance.
     *
     * @param  THndlr $instance Handler instance.
     * @return static
     */
    public function with_target( object $instance ): static;

    /**
     * Set the magic function parameters.
     *
     * @param  array<string,mixed> $params Parameters to pass to the magic function.
     * @return static
     */
    public function with_params( array $params ): static;

    /**
     * Set the handler hook methods
     *
     * @param  array<int,string|Can_Invoke<THndlr,static>> $callbacks Hook methods.
     * @return static
     */
    public function with_callbacks( array $callbacks ): static;

    /**
     * Get the magic function parameters.
     *
     * @param  string $method Method name.
     * @return ?Infuse
     */
    public function get_params( string $method ): ?Infuse;

    /**
     * Get the handler initialization strategy.
     *
     * @return string
     */
    public function get_strategy(): string;

    /**
     * Get the handler load strategy.
     *
     * @return string
     */
    public function get_init_strategy(): string;

    /**
     * Get the handler hooks.
     *
     * @return ?array<int,string>
     */
    public function get_callbacks(): ?array;

    /**
     * Get the tag for lazy loading.
     *
     * @return string
     */
    public function get_lazy_tag();

    /**
     * Get the arguments for the action.
     *
     * @return array<int,null|string>
     */
    public function get_hook_args(): array;

    /**
     * Get the number of arguments for the action.
     *
     * @return int
     */
    public function get_hook_args_count(): int;

    /**
     * Is the handler lazy loaded?
     *
     * @return bool
     */
    public function is_lazy(): bool;

    /**
     * Is the handler hookable?
     *
     * @return bool
     */
    public function is_hookable(): bool;

    /**
     * Can the handler be loaded?
     *
     * @param  array<int|string,mixed> $args Arguments to pass to the handler.
     * @return bool
     */
    public function can_load( array $args = array() ): bool;

    /**
     * Load the handler.
     *
     * @param  array<int|string,mixed> $args Arguments to pass to the handler.
     * @return bool
     */
    public function load( array $args = array() ): bool;

    /**
     * Lazy load the handler.
     *
     * @param  array<int|string,mixed> $args Arguments to pass to the handler.
     * @return bool
     */
    public function lazy_load( array $args = array() ): bool;
}
