<?php
/**
 * Can_Handle interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

/**
 * Defines decorators that can handle WordPress hooks.
 *
 * @since 1.0.0
 */
interface Can_Handle {
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
}
