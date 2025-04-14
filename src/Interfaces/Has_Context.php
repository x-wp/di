<?php
/**
 * Has_Context interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

/**
 * Describes decorators that are context-aware.
 */
interface Has_Context {
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
}
