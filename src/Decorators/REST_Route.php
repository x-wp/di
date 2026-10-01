<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * REST_Route class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use XWP\DI\Interfaces\Can_Handle;

/**
 * Decorator for REST routes.
 *
 * @template T of \XWP_REST_Controller
 * @template H of Can_Handle<T>
 * @extends Action<T,H>
 */
#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class REST_Route extends Action {
    /**
     * Declared route args.
     *
     * @var string|array<string,mixed>
     */
    protected array|string $route_args;

    /**
     * Declared route guard.
     *
     * @var string
     */
    protected string $route_guard;

    /**
     * Declared endpoint.
     *
     * @var string
     */
    protected string $endpoint;

    /**
     * Declared methods.
     *
     * @var string
     */
    protected string $methods;

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
     * Export unbound attribute metadata.
     *
     * @internal Discovery metadata detail.
     * @return array<string,mixed>
     */
    public function get_declaration(): array {
        return array(
            'guard'   => $this->route_guard,
            'invoke'  => $this->invoke,
            'methods' => $this->methods,
            'params'  => $this->params,
            'route'   => $this->endpoint,
            'vars'    => $this->route_args,
        );
    }
}
