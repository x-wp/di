<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * CLI_Handler runtime class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use XWP\DI\Interfaces\Can_Handle_CLI;

/**
 * Specialized handler runtime.
 *
 * @template T of object
 * @extends Handler<T>
 * @implements Can_Handle_CLI<T>
 * @internal
 */
class CLI_Handler extends Handler implements Can_Handle_CLI {
    public function get_namespace(): string {
        return $this->definition->get_options()['namespace'];
    }

    public function load(): bool {
        CLI_Namespaces::register( $this->get_namespace(), $this->definition->get_options()['description'] ?? '' );
        return parent::load();
    }
}
