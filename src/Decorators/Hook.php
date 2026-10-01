<?php
/**
 * Hook attribute metadata.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use XWP\DI\Interfaces\Has_Context;

/**
 * Base declaration for hook attributes.
 *
 * @template THndlr of object
 * @template TRflct of \ReflectionClass<THndlr>|\ReflectionMethod
 * @internal
 */
abstract class Hook {
    /**
     * Frontend context.
     */
    public const CTX_FRONTEND = Has_Context::CTX_FRONTEND;

    /**
     * Admin context.
     */
    public const CTX_ADMIN = Has_Context::CTX_ADMIN;

    /**
     * AJAX context.
     */
    public const CTX_AJAX = Has_Context::CTX_AJAX;

    /**
     * Cron context.
     */
    public const CTX_CRON = Has_Context::CTX_CRON;

    /**
     * REST context.
     */
    public const CTX_REST = Has_Context::CTX_REST;

    /**
     * CLI context.
     */
    public const CTX_CLI = Has_Context::CTX_CLI;

    /**
     * Global context.
     */
    public const CTX_GLOBAL = Has_Context::CTX_GLOBAL;

    /**
     * Normalized hook tag.
     *
     * @var string
     */
    protected string $tag;

    /**
     * Unresolved hook priority.
     *
     * @var null|Closure|string|int|array{0:class-string,1:string}
     */
    protected null|Closure|string|int|array $prio;

    /**
     * Constructor.
     *
     * @param string|null                                            $tag Hook tag.
     * @param null|Closure|string|int|array{0:class-string,1:string} $priority Hook priority.
     * @param int                                                    $context Hook context.
     * @param null|Closure|string|array{0:class-string,1:string}     $conditional Conditional callback.
     * @param string|array<int,string>|bool                          $modifiers Tag modifiers.
     * @param bool                                                   $debug Debug flag.
     * @param bool                                                   $trace Trace flag.
     */
    public function __construct(
        ?string $tag,
        array|int|string|Closure|null $priority = null,
        protected int $context = self::CTX_GLOBAL,
        protected array|string|Closure|null $conditional = null,
        protected string|array|bool $modifiers = false,
        protected bool $debug = false,
        protected bool $trace = false,
    ) {
        $this->prio = $priority;
        $this->tag  = \str_replace( array( '{', '}' ), '', $tag ?? '' );
    }

    /**
     * Export unbound attribute metadata.
     *
     * @internal Discovery metadata detail.
     * @return array<string,mixed>
     */
    public function get_declaration(): array {
        return array(
            'conditional' => $this->conditional,
            'context'     => $this->context,
            'modifiers'   => $this->modifiers,
            'priority'    => $this->prio,
            'tag'         => $this->tag,
        );
    }

    /**
     * Get the declared context.
     *
     * @return int
     */
    public function get_context(): int {
        return $this->context;
    }
}
