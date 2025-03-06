<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Ajax_Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use XWP\DI\Interfaces\Can_Handle_Ajax;

/**
 * Decorator for grouping ajax actions.
 *
 * @template T of object
 * @extends Handler<T>
 * @implements Can_Handle_Ajax<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Ajax_Handler extends Handler implements Can_Handle_Ajax {
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

    public function get_data(): array {
        return \array_merge(
            parent::get_data(),
            array(
                'args' => array(
                    'conditional' => $this->conditional,
                    'priority'    => $this->get_priority(),
                ),
            ),
        );
    }
}
