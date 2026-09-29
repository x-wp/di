<?php //phpcs:disable Universal.NamingConventions.NoReservedKeywordParameterNames.namespaceFound, Squiz.Commenting.FunctionComment.Missing

namespace XWP\DI\Decorators;

use Closure;
use WP_CLI;
use XWP\DI\Interfaces\Can_Handle_CLI;

/**
 * Decorator for CLI commands.
 *
 * @template T of object
 * @extends Handler<T>
 * @implements Can_Handle_CLI<T>
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class CLI_Handler extends Handler implements Can_Handle_CLI {
    use \XWP\DI\Compatibility\CLI_Handler_Helpers;

    /**
     * Array of root commands.
     *
     * @var array<string,bool>
     */
    protected static array $roots = array();

    /**
     * Constructor.
     *
     * @param string                                        $namespace   Command namespace.
     * @param string                                        $description Command description.
     * @param Closure|string|int|array{class-string,string} $priority    Hook priority.
     * @param mixed                                         ...$args     Additional arguments.
     */
    public function __construct(
        protected string $namespace,
        protected string $description = '',
        Closure|string|int|array $priority = 10,
        mixed ...$args,
    ) {
        $ctr = $args['container'] ?? null;
        parent::__construct( tag: 'cli_init', priority: $priority, context: static::CTX_CLI, container: $ctr );
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

    /**
     * Load the CLI namespace and commands.
     *
     * @internal Runtime command registration detail. Dispatcher replaces this in v2.0.
     *
     * @return bool
     */
    public function load(): bool {
        static::$roots[ $this->namespace ] ??= \XWP\DI\Hook\CLI_Namespaces::register(
            $this->namespace,
            $this->description,
            fn() => $this->add_command(),
        );

        return parent::load();
    }

    protected function add_command(): bool {
        return WP_CLI::add_command(
            $this->namespace,
            \XWP_CLI_Namespace::class,
            array( 'shortdesc' => $this->description ),
        );
    }
}
