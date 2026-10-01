<?php
/**
 * Can_Hook interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

use ReflectionClass;
use ReflectionMethod;
use Reflector;
use XWP\DI\Container;

/**
 * Describes runtime objects that can be hooked into WordPress.
 *
 * @template THndlr of object
 * @template TRflct of Reflector
 */
interface Can_Hook extends Has_Context {
    /**
     * Set the definition as cached or not.
     *
     * @param  bool $cached Cached or not.
     * @return static
     *
     * @internal Parser/runtime wiring detail.
     */
    public function with_cache( bool $cached ): static;

    /**
     * Set the classname of the handler.
     *
     * @param  class-string<THndlr> $classname Handler classname.
     * @return static
     *
     * @internal Parser/runtime wiring detail.
     */
    public function with_classname( string $classname ): static;

    /**
     * Set the container.
     *
     * @param  null|string|Container $container Container instance.
     * @return static
     *
     * @internal Parser/runtime wiring detail.
     */
    public function with_container( null|string|Container $container ): static;

    /**
     * Set the reflector
     *
     * @param  TRflct $reflector Reflector instance.
     * @return static
     *
     * @internal Parser/runtime wiring detail.
     */
    public function with_reflector( Reflector $reflector ): static;

    /**
     * Set hook parameters.
     *
     * @param  array<string,mixed> $data Parameters to pass to the callback.
     * @return static
     *
     * @internal Parser/runtime wiring detail.
     */
    public function with_data( array $data ): static;

    /**
     * Get the hook tag.
     *
     * @return string
     */
    public function get_tag(): string;

    /**
     * Get the tag modifiers.
     *
     * @return array<int,string>|string|false
     */
    public function get_modifiers(): array|string|bool;

    /**
     * Get the hook priority.
     *
     * @return int
     */
    public function get_priority(): int;

    /**
     * Get the container.
     *
     * @return ?Container
     *
     * @internal Runtime wiring detail.
     */
    public function get_container(): ?Container;

    /**
     * Get the handler classname.
     *
     * @return class-string<THndlr>
     *
     * @internal Runtime wiring detail.
     */
    public function get_classname(): string;

    /**
     * Get the hook token.
     *
     * @return string
     *
     * @internal Runtime wiring detail.
     */
    public function get_token(): string;

    /**
     * Get the reflector.
     *
     * @return TRflct
     *
     * @internal Parser/runtime wiring detail.
     */
    public function get_reflector(): Reflector;

    /**
     * Get the hook definition.
     *
     * @return array{
     *   args: array<string,mixed>,
     *   type: class-string<static>,
     *   params: array{classname: class-string<THndlr>},
     * }
     *
     * @internal Hook parser/compiler detail.
     */
    public function get_data(): array;

    /**
     * Get the handler initialization hook.
     *
     * @return string
     *
     * @internal Runtime dispatch detail.
     */
    public function get_init_hook(): string;

    /**
     * Is the hook definition cached?
     *
     * @return bool
     *
     * @internal Parser/runtime wiring detail.
     */
    public function is_cached(): bool;

    /**
     * Can the hook be loaded?
     *
     * For handlers - checks if they can be instantiated.
     * For filters and actions - checks if they can be invoked.
     *
     * @return bool
     *
     * @internal Runtime dispatch detail.
     */
    public function can_load(): bool;

    /**
     * Loads the handler, filter or action.
     *
     * @return bool
     *
     * @internal Runtime dispatch detail.
     */
    public function load(): bool;

    /**
     * Check if the hook is loaded.
     *
     * @return bool
     *
     * @internal Runtime dispatch detail.
     */
    public function is_loaded(): bool;
}
