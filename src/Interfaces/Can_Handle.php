<?php
/**
 * Can_Handle interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

use ReflectionClass;
use XWP\DI\Container;
use XWP\DI\Decorators\Infuse;

/**
 * Defines decorators that can handle WordPress hooks.
 *
 * @template THndlr of object
 *
 * @extends Can_Hook<THndlr,ReflectionClass<THndlr>>
 *
 * @since 1.0.0
 */
interface Can_Handle extends Can_Hook {
    /**
     * Do not proxy hook arguments.
     *
     * @var int
     */
    public const DELEGATE_NEVER = 0;

    /**
     * Are we proxying hook arguments to the constructor?
     *
     * @var int
     */
    public const DELEGATE_ON_LOAD = 1;

    /**
     * Are we proxying hook arguments for the `can_initialize` method?
     *
     * @var int
     */
    public const DELEGATE_ON_CREATE = 2;

    /**
     * Never initialize the handler.
     *
     * @var string
     */
    public const INIT_NEVER = 'never';

    /**
     * Initialize the handler early.
     *
     * @var string
     */
    public const INIT_EARLY = 'early';

    /**
     * Initialize the handler immediately.
     *
     * @var string
     */
    public const INIT_NOW = 'immediately';

    /**
     * Initialize the handler on demand.
     *
     * @var string
     */
    public const INIT_LAZY = 'on-demand';

    /**
     * Initialize the handler just in time (when needed).
     *
     * @var string
     */
    public const INIT_JIT = 'just-in-time';

    /**
     * Initialize the handler automatically.
     *
     * @var string
     */
    public const INIT_AUTO = 'deferred';

    /**
     * Initialize the handler dynamically.
     *
     * @var string
     */
    public const INIT_USER = 'dynamically';

    /**
     * Initialize the handler immediately.
     *
     * @var string
     * @deprecated Use INIT_NOW instead.
     */
    public const INIT_IMMEDIATELY = 'immediately';

    /**
     * Initialize the handler on demand.
     *
     * @var string
     * @deprecated Use INIT_LAZY instead.
     */
    public const INIT_ON_DEMAND = 'on-demand';

    /**
     * Initialize the handler just in time (when needed).
     *
     * @var string
     * @deprecated Use INIT_JIT instead.
     */
    public const INIT_JUST_IN_TIME = 'just-in-time';

    /**
     * Initialize the handler automatically.
     *
     * @var string
     * @deprecated Use INIT_USER instead.
     */
    public const INIT_DYNAMICALY = 'dynamically';

    /**
     * Initialize the handler automatically.
     *
     * @var string
     * @deprecated Use INIT_AUTO instead.
     */
    public const INIT_DEFFERED = 'deferred';

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
     * Get the handler instance.
     *
     * @return THndlr|null
     */
    public function get_target(): ?object;

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
     * Get the deprecated constructor arguments.
     *
     * @return array<string>
     */
    public function get_compat_args(): array;

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
