<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Ajax_Handler runtime class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use XWP\DI\Interfaces\Can_Handle_Ajax;

/**
 * Specialized handler runtime.
 *
 * @template T of object
 * @extends Handler<T>
 * @implements Can_Handle_Ajax<T>
 * @internal
 */
class Ajax_Handler extends Handler implements Can_Handle_Ajax {
    public function get_prefix(): string {
        return \rtrim( $this->definition->get_options()['prefix'] ?? '', '_' );
    }
}
