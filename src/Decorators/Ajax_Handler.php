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
     * @param null|string                                    $prefix      Prefix for the action name.
     * @param int                                            $priority    Handler priority.
     * @param null|Closure|string|array{class-string,string} $conditional Conditional callback.
     * @param mixed                                          ...$args     Additional arguments.
     */
    public function __construct(
        protected ?string $prefix = null,
        int $priority = 10,
        array|string|Closure|null $conditional = null,
        mixed ...$args,
    ) {
        $params = array(
            'args'        => $args,
            'conditional' => $conditional,
            'context'     => self::CTX_AJAX,
            'priority'    => $priority,
            'strategy'    => self::INIT_LAZY,
            'tag'         => 'admin_init',
        );

        parent::__construct( ...$params );
    }

    public function get_prefix(): string {
        return null !== $this->prefix
            ? \rtrim( $this->prefix, '_' )
            : '';
    }

    public function get_data(): array {
        return \array_merge(
            parent::get_data(),
            array(
                'args' => array(
                    'conditional' => $this->conditional,
                    'prefix'      => $this->prefix,
                    'priority'    => $this->prio,
                ),
            ),
        );
    }
}
