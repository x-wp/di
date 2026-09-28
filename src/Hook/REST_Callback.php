<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * REST callback runtime.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use Closure;
use XWP\DI\Container;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Interfaces\Can_Invoke;

/**
 * Registers REST routes and dispatches their response callbacks.
 *
 * @template T of \XWP_REST_Controller
 * @template H of \XWP\DI\Decorators\REST_Handler<T>
 * @extends Callback<T,H>
 * @internal
 */
class REST_Callback extends Callback {
    /**
     * Route parameter definitions or their provider method.
     *
     * @var string|array<string,mixed>
     */
    protected string|array $route_args;

    /**
     * Permission callback name.
     *
     * @var string
     */
    protected string $route_guard;
    /**
     * Route endpoint.
     *
     * @var string
     */
    protected string $endpoint;
    /**
     * HTTP methods.
     *
     * @var string
     */
    protected string $methods;

    /**
     * Constructor.
     *
     * @param CallbackDefinition $definition Callback metadata.
     * @param Container          $container Runtime container.
     */
    public function __construct( CallbackDefinition $definition, Container $container ) {
        parent::__construct( $definition, $container );
        $options           = $definition->get_options();
        $this->endpoint    = $options['route'];
        $this->route_args  = $options['vars'];
        $this->methods     = $options['methods'];
        $this->route_guard = $options['guard'] ?? '__return_true';
    }

    public function get_route(): string {
        return $this->endpoint
            ? "/{$this->get_handler()->get_basename()}/{$this->endpoint}"
            : "/{$this->get_handler()->get_basename()}";
    }

    /**
     * Get the route permission callback.
     *
     * @return string|array{0:T,1:string}
     */
    public function get_guard(): string|array {
        return \method_exists( $this->get_handler()->get_classname(), $this->route_guard )
            ? array( $this->get_handler()->get_target(), $this->route_guard )
            : $this->route_guard;
    }

    /**
     * Get the runtime route callback.
     *
     * @internal Runtime dispatch detail.
     *
     * @return Closure|array{0: T, 1: string}
     */
    public function get_callback(): array|Closure {
        return $this->cb_valid( Can_Invoke::INV_STANDARD )
            ? array( $this->get_handler()->get_target(), $this->get_method() )
            : fn( ...$args ) => $this->dispatch_route( ...$args );
    }

    /**
     * Get the route parameters.
     *
     * @return array<string,mixed>
     */
    public function get_vars(): array {
        if ( \is_array( $this->route_args ) ) {
            return $this->route_args;
        }

        return $this->get_container()->call( array( $this->get_handler()->get_target(), $this->route_args ) );
    }

    public function get_methods(): string {
        return $this->methods;
    }

    /**
     * Register the REST route.
     *
     * @internal Runtime dispatch detail. Dispatcher replaces this in v2.0.
     *
     * @param  mixed ...$args Arguments.
     * @return mixed
     */
    public function invoke( mixed ...$args ): mixed {
        return \register_rest_route(
            $this->get_handler()->get_namespace(),
            $this->get_route(),
            array(
                'args'                => $this->get_vars(),
                'callback'            => $this->get_callback(),
                'methods'             => $this->get_methods(),
                'permission_callback' => $this->get_guard(),
            ),
        );
    }

    /**
     * Dispatch a response without applying action return semantics or hook gates.
     *
     * @param mixed ...$args Route callback arguments.
     * @return mixed
     */
    protected function dispatch_route( mixed ...$args ): mixed {
        try {
            return $this->fire_hook( ...$args );
        } finally {
            $this->firing = false;
            ++$this->fired;
        }
    }

    /**
     * Registration always invokes this runtime, even for standard route callbacks.
     *
     * @return array{0:object,1:string}
     */
    protected function get_target(): array {
        return array( $this, 'invoke' );
    }
}
