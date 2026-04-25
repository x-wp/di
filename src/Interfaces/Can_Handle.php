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
     *
     * @internal Runtime wiring detail.
     */
    public function with_target( object $instance ): static;

    /**
     * Set the magic function parameters.
     *
     * @param  array<string,mixed> $params Parameters to pass to the magic function.
     * @return static
     *
     * @internal Parser/runtime wiring detail.
     */
    public function with_params( array $params ): static;

    /**
     * Set the handler hook methods
     *
     * @param  array<int,string|Can_Invoke<THndlr,static>> $callbacks Hook methods.
     * @return static
     *
     * @internal Parser/runtime wiring detail.
     */
    public function with_callbacks( array $callbacks ): static;

    /**
     * Get the handler instance.
     *
     * @return THndlr|null
     *
     * @internal Runtime wiring detail.
     */
    public function get_target(): ?object;

    /**
     * Get the magic function parameters.
     *
     * @param  string $method Method name.
     * @return ?Infuse
     *
     * @internal Runtime parameter-resolution detail.
     */
    public function get_params( string $method ): ?Infuse;

    /**
     * Get the handler initialization strategy.
     *
     * @return string
     */
    public function get_strategy(): string;

    /**
     * Get the handler hooks.
     *
     * @return ?array<int,string>
     *
     * @internal Runtime wiring detail.
     */
    public function get_callbacks(): ?array;

    /**
     * Get the tag for lazy loading.
     *
     * @return string
     *
     * @internal Runtime dispatch detail.
     */
    public function get_lazy_tag();

    /**
     * Get the deprecated constructor arguments.
     *
     * @return array<string>
     *
     * @internal Runtime compatibility detail.
     */
    public function get_compat_args(): array;

    /**
     * Is the handler lazy loaded?
     *
     * @return bool
     *
     * @internal Runtime dispatch detail.
     */
    public function is_lazy(): bool;

    /**
     * Is the handler hookable?
     *
     * @return bool
     *
     * @internal Runtime dispatch detail.
     */
    public function is_hookable(): bool;

    /**
     * Lazy load the handler.
     *
     * @return void
     *
     * @internal Runtime dispatch detail.
     */
    public function lazy_load(): void;
}
