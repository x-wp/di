<?php //phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound
/**
 * REST_Handler class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

/**
 * Decorator for grouping ajax actions.
 *
 * @property-read string $rest_hook REST hook.
 * @property-read string $namespace REST namespace.
 * @property-read string $basename REST basename.
 *
 * @template T of \XWP_REST_Controller
 * @extends Handler<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class REST_Handler extends Handler {
    /**
     * Constructor
     *
     * @param string $namespace REST namespace.
     * @param string $basename  REST basename.
     * @param string $container Container ID.
     * @param int    $priority  Handler priority.
     */
    public function __construct(
        protected string $namespace,
        protected string $basename,
        string $container,
        int $priority = 10,
    ) {
        parent::__construct(
            tag: 'rest_api_init',
            priority: $priority,
            container: $container,
            context: self::CTX_REST,
        );
    }

    /**
     * Can the handler be loaded?
     *
     * Checks if the REST namespace matches the requested route.
     *
     * @return bool
     */
    public function can_load(): bool {
        return parent::can_load() && \xwp_can_load_rest_ns( $this->namespace );
    }

    /**
     * Initialize the handler.
     *
     * Sets the namespace and basename.
     *
     * @return T
     */
    protected function instantiate(): object {
        return parent::instantiate()
            ->with_namespace( $this->namespace )
            ->with_basename( $this->basename );
    }

    /**
     * Get the REST namespace.
     *
     * @return string
     */
    protected function get_namespace(): string {
        return $this->namespace;
    }

    /**
     * Get the REST basename.
     *
     * @return string
     */
    protected function get_basename(): string {
        return $this->basename;
    }

    /**
     * Get the REST hook.
     *
     * @return string
     */
    public function get_rest_hook(): string {
        return $this->namespace . '/' . $this->basename;
    }
}
