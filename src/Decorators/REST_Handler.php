<?php //phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound, Squiz.Commenting.FunctionComment.Missing
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
     * @param int    $priority  Handler priority.
     * @param mixed  ...$args   Additional arguments.
     *
     * @phpstan-ignore constructor.unusedParameter (Preserve deprecated constructor arguments.)
     */
    public function __construct(
        protected string $namespace,
        protected string $basename,
        int $priority = 10,
        mixed ...$args,
    ) {
        parent::__construct( tag: 'rest_api_init', priority: $priority, context: self::CTX_REST );
    }

    /**
     * Export unbound attribute metadata.
     *
     * @internal Discovery metadata detail.
     * @return array<string,mixed>
     */
    public function get_declaration(): array {
        return array(
            'basename'  => $this->basename,
            'namespace' => $this->namespace,
            'priority'  => $this->prio,
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
}
