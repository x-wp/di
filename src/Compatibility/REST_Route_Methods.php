<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * REST_Route legacy compatibility methods.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Compatibility;

use Closure;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Route;

/**
 * Preserves inherited wiring and override seams for existing decorators.
 *
 * @template T of object
 * @template H of \XWP\DI\Interfaces\Can_Handle<T>
 * @internal New runtime extensions belong in XWP\DI\Hook.
 */
trait REST_Route_Methods {
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
     * Set the handler instance.
     *
     * @param  H $handler Handler instance.
     * @return static
     *
     * @internal Parser/runtime wiring detail. Attributes are immutable in v2.0.
     */
    public function with_handler( Can_Handle $handler ): static {
        return parent::with_handler( $handler )
            ->with_tag( $handler->get_rest_hook() )
            ->with_priority( $handler->get_priority() + 1 );
    }

    /**
     * Set the runtime REST route priority.
     *
     * @internal Parser/runtime wiring detail. Attributes are immutable in v2.0.
     *
     * @param  int $priority Priority.
     * @return static
     */
    public function with_priority( int $priority ): static {
        $this->prio = $priority;

        return $this;
    }

    /**
     * Set the runtime REST route tag.
     *
     * @internal Parser/runtime wiring detail. Attributes are immutable in v2.0.
     *
     * @param  string $tag Tag.
     * @return static
     */
    public function with_tag( string $tag ): static {
        $this->tag = $tag;

        return $this;
    }

    public function get_route(): string {
        if ( $this->runtime instanceof \XWP\DI\Hook\REST_Callback ) {
            return $this->runtime->get_route();
        }

        return $this->endpoint
            ? "/{$this->get_handler()->get_basename()}/{$this->endpoint}"
            : "/{$this->get_handler()->get_basename()}";
    }

    public function get_guard(): string|array {
        if ( $this->runtime instanceof \XWP\DI\Hook\REST_Callback ) {
            return $this->runtime->get_guard();
        }

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
        if ( $this->runtime instanceof \XWP\DI\Hook\REST_Callback ) {
            return $this->runtime->get_callback();
        }

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
        if ( $this->runtime instanceof \XWP\DI\Hook\REST_Callback ) {
            return $this->runtime->get_vars();
        }

        if ( \is_array( $this->route_args ) ) {
            return $this->route_args;
        }

        return $this->get_container()->call( array( $this->get_handler()->get_target(), $this->route_args ) );
    }

    public function get_methods(): string {
        if ( $this->runtime instanceof \XWP\DI\Hook\REST_Callback ) {
            return $this->runtime->get_methods();
        }

        return $this->methods;
    }

    /**
     * Register the REST route.
     *
     * @internal Runtime dispatch detail. Retained for legacy subclasses and typed views.
     *
     * @param  mixed ...$args Arguments.
     * @return mixed
     */
    public function invoke( mixed ...$args ): mixed {
        if ( $this->runtime instanceof \XWP\DI\Hook\REST_Callback ) {
            return $this->runtime->invoke( ...$args );
        }

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
