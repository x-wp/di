<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Ajax_Handler decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Core;

use Closure;
use XWP\DI\Container;
use XWP\DI\Interfaces\Can_Handle_Ajax;

/**
 * Decorator for grouping ajax actions.
 *
 * @template T of object
 * @extends Handler<T>
 * @implements Can_Handle_Ajax<T>
 */
class Ajax_Handler extends Handler implements Can_Handle_Ajax {
    // /**
    // * Constructor
    // *
    // * @param null|string $prefix   Prefix for the action name.
    // * @param int         $priority Handler priority.
    // * @param mixed       ...$args  Additional arguments.
    // */
    // public function __construct(
    // string $classname,
    // Container $container,
    // array $callbacks,
    // bool $debug,
    // bool $trace,
    // protected ?string $prefix = null,
    // int $priority = 10,
    // ) {
    // parent::__construct(
    // tag: 'admin_init',
    // priority: $priority,
    // classname: $classname,
    // context: self::CTX_AJAX,
    // strategy: self::INIT_LAZY,
    // container: $container,
    // callbacks: $callbacks,
    // hydrated: false,
    // debug: $debug,
    // trace: $trace,
    // );
    // }

    public function get_prefix(): string {
        return null !== $this->prefix
            ? \rtrim( $this->prefix, '_' )
            : '';
    }

    // public function get_data(): array {
    // return $this->merge_compat_args( parent::get_data() );
    // }

    protected function get_constructor_args(): array {
        return array( 'prefix', 'priority' );
    }
}
