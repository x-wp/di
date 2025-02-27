<?php
/**
 * REST_Route class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use XWP\DI\Interfaces\Can_Handle;

/**
 * Decorator for REST routes.
 *
 * @property-read array{0:T, 1: string}|Closure $callback REST route callback.
 * @property-read string                        $methods  REST route methods.
 * @property-read string                        $route    REST route.
 * @property-read array                         $vars   REST route parameters.
 * @property-read string                        $guard    REST route guard.
 *
 * @template T of \XWP_REST_Controller
 * @template H of REST_Handler<T>
 * @extends Action<T,H>
 */
#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class REST_Route extends Action {
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
            ->with_tag( $handler->rest_hook )
            ->with_priority( $handler->priority + 1 );
    }

    /**
     * Set the route priority.
     *
     * @param  int $priority Priority.
     * @return static
     */
    protected function with_priority( int $priority ): static {
        $this->prio = $priority;

        return $this;
    }

    /**
     * Set the route tag.
     *
     * @param  string $tag Tag.
     * @return static
     */
    protected function with_tag( string $tag ): static {
        $this->tag = $tag;

        return $this;
    }

    /**
     * Register the REST route.
     *
     * @param  mixed ...$args Arguments.
     * @return mixed
     */
    public function invoke( mixed ...$args ): mixed {
        return \register_rest_route(
            $this->handler->namespace,
            $this->route,
            array(
                'args'                => $this->vars,
                'callback'            => $this->callback,
                'methods'             => $this->methods,
                'permission_callback' => $this->guard,
            ),
        );
    }

    /**
     * Get the route.
     *
     * @return string
     */
    protected function get_route(): string {
        return $this->endpoint
            ? "/{$this->handler->basename}/{$this->endpoint}"
            : "/{$this->handler->basename}";
    }

    /**
     * Get the route parameters.
     *
     * @return array<string,mixed>
     */
    protected function get_vars(): array {
        if ( \is_array( $this->route_args ) ) {
            return $this->route_args;
        }

        return $this->container->call( array( $this->handler->target, $this->route_args ) );
    }

    /**
     * Get the route callback.
     *
     * @return array{0:T, 1: string}|Closure
     */
    protected function get_callback(): array|Closure {
        return $this->cb_valid( self::INV_STANDARD )
            ? array( $this->handler->target, $this->method )
            : fn( ...$args ) => $this->fire_hook( ...$args );
    }

    /**
     * Get the route guard.
     *
     * @return string|array{T,string}
     */
    protected function get_guard(): string|array {
        return \method_exists( $this->handler->classname, $this->route_guard )
            ? array( $this->handler->target, $this->route_guard )
            : $this->route_guard;
    }
}
