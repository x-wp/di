<?php //phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound, Squiz.Commenting.FunctionComment.Missing
/**
 * REST_Handler class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Core;

use XWP\DI\Interfaces\Can_Handle_REST;

/**
 * Decorator for grouping ajax actions.
 *
 * @property-read string $rest_hook REST hook.
 * @property-read string $namespace REST namespace.
 * @property-read string $basename REST basename.
 *
 * @template T of \XWP_REST_Controller
 * @extends Handler<T>
 * @implements Can_Handle_REST<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class REST_Handler extends Handler implements Can_Handle_REST {
    /**
     * Constructor
     *
     * @param string $namespace REST namespace.
     * @param string $basename  REST basename.
     * @param int    $priority  Handler priority.
     * @param bool   $debug     Debug this hook.
     * @param bool   $trace     Trace this hook.
     * @param mixed  ...$args   Additional arguments.
     */
    public function __construct(
        protected string $namespace,
        protected string $basename,
        int $priority = 10,
        bool $debug = false,
        bool $trace = false,
        mixed ...$args,
    ) {
        $args = $args[0] ?? $args;

        parent::__construct(
            tag: 'rest_api_init',
            priority: $priority,
            context: self::CTX_REST,
            debug: $debug,
            trace: $trace,
            container: $args['container'] ?? null,
        );
    }

    public function get_namespace(): string {
        return $this->namespace;
    }

    public function get_basename(): string {
        return $this->basename;
    }

    public function get_rest_hook(): string {
        return $this->namespace . '/' . $this->basename;
    }

    /**
     * Can the handler be loaded?
     *
     * Checks if the REST namespace matches the requested route.
     *
     * @param  array<int|string,mixed> $args Arguments to pass to the handler.
     * @return bool
     */
    public function can_load( array $args = array() ): bool {
        return parent::can_load() && \xwp_can_load_rest_ns( $this->namespace );
    }

    /**
     * Initialize the handler.
     *
     * Sets the namespace and basename.
     *
     * @param  array<string,mixed> $args Arguments to pass to the handler.
     * @return T
     */
    protected function instantiate( array $args = array() ): object {
        return parent::instantiate()
            ->with_namespace( $this->namespace )
            ->with_basename( $this->basename );
    }

    protected function get_constructor_args(): array {
        return array( 'basename', 'namespace' );
    }
}
