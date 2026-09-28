<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * REST_Route class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Route;

/**
 * Decorator for REST routes.
 *
 * @property-read string                        $methods  REST route methods.
 * @property-read array                         $vars   REST route parameters.
 * @property-read string                        $guard    REST route guard.
 *
 * @template T of \XWP_REST_Controller
 * @template H of REST_Handler<T>
 * @extends Action<T,H>
 * @implements Can_Route<T,H>
 */
#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class REST_Route extends Action implements Can_Route {
    /**
     * Legacy custom-subclass and typed-view compatibility.
     *
     * @use \XWP\DI\Compatibility\REST_Route_Methods<T,H>
     */
    use \XWP\DI\Compatibility\REST_Route_Methods;

    /**
     * Constructor.
     *
     * @param  string                     $route   REST route.
     * @param  string                     $methods HTTP methods.
     * @param  string|array<string,mixed> $vars    Route parameters.
     * @param  string|null                $guard   Route guard.
     * @param  int                        $invoke  Invocation strategy.
     * @param  array<string>              $params  Additional parameters.
     */
    public function __construct(
        string $route,
        string $methods,
        string|array $vars = array(),
        ?string $guard = null,
        int $invoke = self::INV_PROXIED,
        array $params = array(),
    ) {
        parent::__construct(
            tag: 'rest_api_init',
            priority: 10,
            context: self::CTX_REST,
            invoke: $invoke,
            params: $params,
        );

        $this->endpoint    = $route;
        $this->route_args  = $vars;
        $this->methods     = $methods;
        $this->route_guard = $guard ?? '__return_true';
    }

    /**
     * Get compiler data for this REST route.
     *
     * @internal Hook parser/compiler detail.
     *
     * @return array<string,mixed>
     */
    public function get_data(): array {
        $data                       = parent::get_data();
        $data['params']['tag']      = $this->tag;
        $data['params']['priority'] = $this->prio;

        return \array_merge(
            $data,
            array(
                'args' => array(
                    'guard'   => $this->route_guard,
                    'invoke'  => $this->invoke,
                    'methods' => $this->methods,
                    'params'  => $this->params,
                    'route'   => $this->endpoint,
                    'vars'    => $this->route_args,
                ),
            ),
        );
    }
}
