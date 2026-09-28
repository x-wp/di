<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * REST_Handler runtime class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use XWP\DI\Interfaces\Can_Handle_REST;

/**
 * Specialized handler runtime.
 *
 * @template T of \XWP_REST_Controller
 * @extends Handler<T>
 * @implements Can_Handle_REST<T>
 * @internal
 */
class REST_Handler extends Handler implements Can_Handle_REST {
    public function get_namespace(): string {
        return $this->definition->get_options()['namespace'];
    }

    public function get_basename(): string {
        return $this->definition->get_options()['basename'];
    }

    public function get_rest_hook(): string {
        return $this->get_namespace() . '/' . $this->get_basename();
    }

    public function can_load(): bool {
        return parent::can_load() && \xwp_can_load_rest_ns( $this->get_namespace() );
    }

    protected function instantiate(): object {
        return parent::instantiate()->with_namespace( $this->get_namespace() )->with_basename(
            $this->get_basename(),
        );
    }
}
