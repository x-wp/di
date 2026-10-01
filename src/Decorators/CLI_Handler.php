<?php //phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound, Squiz.Commenting.FunctionComment.Missing

namespace XWP\DI\Decorators;

use Closure;

/**
 * Decorator for CLI commands.
 *
 * @template T of object
 * @extends Handler<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class CLI_Handler extends Handler {
    /**
     * Constructor.
     *
     * @param string                                        $namespace   Command namespace.
     * @param string                                        $description Command description.
     * @param Closure|string|int|array{class-string,string} $priority    Hook priority.
     * @param mixed                                         ...$args     Additional arguments.
     *
     * @phpstan-ignore constructor.unusedParameter (Preserve deprecated constructor arguments.)
     */
    public function __construct(
        protected string $namespace,
        protected string $description = '',
        Closure|string|int|array $priority = 10,
        mixed ...$args,
    ) {
        parent::__construct( tag: 'cli_init', priority: $priority, context: static::CTX_CLI );
    }

    /**
     * Export unbound attribute metadata.
     *
     * @internal Discovery metadata detail.
     * @return array<string,mixed>
     */
    public function get_declaration(): array {
        return array(
            'description' => $this->description,
            'namespace'   => $this->namespace,
            'priority'    => $this->prio,
        );
    }

    public function get_namespace(): string {
        return $this->namespace;
    }
}
