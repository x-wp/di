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
     * @param null|string $prefix   Prefix for the action name.
     * @param int         $priority Handler priority.
     * @param mixed       ...$args  Additional arguments.
     */
    public function __construct(
        protected ?string $prefix = null,
        int $priority = 10,
        mixed ...$args,
    ) {
        $args = $args[0] ?? $args;

        parent::__construct(
            tag: 'admin_init',
            priority: $priority,
            context: self::CTX_AJAX,
            strategy: self::INIT_LAZY,
            container: $args['container'] ?? null,
            conditional: $args['conditional'] ?? null,
        );
    }

    public function get_prefix(): string {
        return null !== $this->prefix
            ? \rtrim( $this->prefix, '_' )
            : '';
    }

    public function get_data(): array {
        return $this->merge_compat_args( parent::get_data() );
    }

    protected function get_constructor_args(): array {
        return array( 'prefix', 'priority' );
    }
}
