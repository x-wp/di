<?php

namespace XWP\DI\Interfaces;

use Psr\Log\LoggerInterface;
use Reflector;
use XWP\DI\Container;

/**
 * Describes decorators that can be hooked into WordPress.
 *
 * @template THndlr of object
 * @template TRflct of Reflector
 *
 * @extends Can_Hook<THndlr,TRflct>
 */
interface Invokes_Hook extends Can_Hook {
    /**
     * Get the hook tag.
     *
     * @return string
     */
    public function get_tag(): string;

    /**
     * Get the hook priority.
     *
     * @return int
     */
    public function get_priority(): int;

    /**
     * Get the tag modifiers.
     *
     * @return array<int,string>|string|false
     */
    public function get_modifiers(): array|string|bool;

    /**
     * Get the container.
     *
     * @return Container
     */
    public function get_container(): Container;

    /**
     * Get the shortname.
     *
     * @return string
     */
    public function get_shortname(): string;

    /**
     * Get logger instance.
     *
     * @return LoggerInterface
     */
    public function get_logger(): LoggerInterface;

    /**
     * Get the handler initialization hook.
     *
     * @return string
     */
    public function get_init_hook(): string;

    /**
     * Is the hook definition cached?
     *
     * @return bool
     */
    public function is_cached(): bool;

    /**
     * Check if the hook is enabled.
     *
     * Hook is enabled if the context is valid.
     *
     * @return bool
     */
    public function is_enabled(): bool;

    /**
     * Is the hook ready to be loaded?
     *
     * @return bool
     */
    public function is_ready(): bool;

    /**
     * Are we debugging the hook?
     *
     * @return bool
     */
    public function debug(): bool;

    /**
     * Is the hook traced?
     *
     * @return bool
     */
    public function trace(): bool;

    /**
     * Can the hook be loaded?
     *
     * For handlers - checks if they can be instantiated.
     * For filters and actions - checks if they can be invoked.
     *
     * @return bool
     */
    public function can_load(): bool;

    /**
     * Loads the handler, filter or action.
     *
     * @return bool
     */
    public function load(): bool;

    /**
     * Invalidate the hook.
     *
     * @param  string $reason Reason for invalidating the hook.
     * @return static
     */
    public function disable( string $reason = '' ): static;

    /**
     * Check if the hook is loaded.
     *
     * @return bool
     */
    public function is_loaded(): bool;
}
