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
    use \XWP\DI\Compatibility\CLI_Handler_Helpers;

    public function get_description(): string {
        return $this->definition->get_options()['description'] ?? '';
    }

    public function get_namespace(): string {
        return $this->definition->get_options()['namespace'];
    }

    public function load(): bool {
        CLI_Namespaces::register(
            $this->get_namespace(),
            $this->get_description(),
            fn() => $this->add_command(),
        );
        return parent::load();
    }

    protected function add_command(): bool {
        return \WP_CLI::add_command(
            $this->get_namespace(),
            \XWP_CLI_Namespace::class,
            array( 'shortdesc' => $this->get_description() ),
        );
    }
}
