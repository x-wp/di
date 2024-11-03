<?php
/**
 * Ajax_Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;

/**
 * Decorator for grouping ajax actions.
 *
 * @template T of object
 * @extends Handler<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Ajax_Handler extends Handler {
    /**
     * Constructor
     *
     * @param string                                         $container   Container ID.
     * @param int                                            $priority    Handler priority.
     * @param null|Closure|string|array{class-string,string} $conditional Conditional callback.
     */
    public function __construct(
        string $container,
        int $priority = 10,
        array|string|Closure|null $conditional = null,
    ) {
        parent::__construct(
            tag: 'admin_init',
            priority: $priority,
            container: $container,
            context: self::CTX_AJAX,
            conditional: $conditional,
        );
    }
}
