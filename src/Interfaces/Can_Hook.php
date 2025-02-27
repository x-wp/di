<?php
/**
 * Can_Hook interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

use DI\Container;
use ReflectionClass;
use ReflectionMethod;

/**
 * Describes decorators that can be hooked into WordPress.
 *
 * @template THndlr of object
 * @template TRflct of ReflectionMethod|ReflectionClass<THndlr>
 *
 * @property-read string    $id        The hook ID.
 * @property-read string    $tag       The hook tag.
 * @property-read int       $priority  The real priority.
 * @property-read int       $context   The hook context.
 * @property-read Container $container Container instance.
 * @property-read bool      $loaded    Is the hook loaded?
 */
interface Can_Hook {
    /**
     * Indicates that a hook can be invoked in user-facing pages.
     *
     * @var int
     */
    public const CTX_FRONTEND = 1;  // 0000001

    /**
     * Indicates that a hook can be invoked in the admin area.
     *
     * @var int
     */
    public const CTX_ADMIN = 2;  // 0000010

    /**
     * Indicates that a hook can be invoked on AJAX requests.
     *
     * @var int
     */
    public const CTX_AJAX = 4;  // 0000100

    /**
     * Indicates that a hook can be invoked when a cron job is running.
     *
     * @var int
     */
    public const CTX_CRON = 8;  // 0001000

    /**
     * Indicates that a hook can be invoked on REST API requests.
     *
     * @var int
     */
    public const CTX_REST = 16; // 0010000

    /**
     * Indicates that a hook can be invoked when WP CLI is running.
     *
     * @var int
     */
    public const CTX_CLI = 32; // 0100000

    /**
     * Indicates that a hook can be invoked in any context.
     *
     * @var int
     */
    public const CTX_GLOBAL = 63; // 0111111

    /**
     * Set the reflector
     *
     * @param  TRflct $reflector Reflector instance.
     * @return static
     */
    public function with_reflector( ReflectionClass|ReflectionMethod $reflector ): static;

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
     * Check if the context is valid.
     *
     * @return bool
     */
    public function check_context(): bool;
}
