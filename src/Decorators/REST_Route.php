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
     * REST Route arguments.
     *
     * @var string|array<string,mixed>
     */
    protected array|string $route_args;

    /**
     * REST Route guard.
     *
     * @var string
     */
    protected string $route_guard;

    /**
     * REST Route endpoint.
     *
     * @var string
     */
    protected string $endpoint;

    /**
     * REST Route methods.
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
     * Set the handler instance.
     *
     * @param  H $handler Handler instance.
     * @return static
     */
    public function with_handler( Can_Handle $handler ): static {
        return parent::with_handler( $handler )
            ->with_tag( $handler->get_rest_hook() )
            ->with_priority( $handler->get_priority() + 1 );
    }

    public function with_priority( int $priority ): static {
        $this->prio = $priority;

        return $this;
    }

    public function with_tag( string $tag ): static {
        $this->tag = $tag;

        return $this;
    }

    public function get_data(): array {
        return \array_merge(
            parent::get_data(),
            array(
                'args' => array(
                    'guard'   => $this->route_guard,
                    'methods' => $this->methods,
                    'params'  => $this->params,
                    'route'   => $this->endpoint,
                    'vars'    => $this->route_args,
                ),
            ),
        );
    }

    public function get_route(): string {
        return $this->endpoint
            ? "/{$this->get_handler()->get_basename()}/{$this->endpoint}"
            : "/{$this->get_handler()->get_basename()}";
    }

    public function get_guard(): string|array {
        return \method_exists( $this->get_handler()->get_classname(), $this->route_guard )
            ? array( $this->get_handler()->get_target(), $this->route_guard )
            : $this->route_guard;
    }

    public function get_callback(): array|Closure {
        return $this->cb_valid( self::INV_STANDARD )
            ? array( $this->get_handler()->get_target(), $this->get_method() )
            : fn( ...$args ) => $this->fire_hook( ...$args );
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
}
