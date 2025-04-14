<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Module decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Core;

use XWP\DI\Container;
use XWP\DI\Interfaces\Can_Import;
use XWP\DI\Interfaces\Invokes_Module;

/**
 * Module decorator.
 *
 * @template T of object
 * @extends Handler<T>
 * @implements Can_Import<T>
 *
 * @since 1.0.0
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Module extends Handler implements Can_Import, Invokes_Module {
    /**
     * Did the module import submodules?
     *
     * @var array<class-string,bool>
     */
    protected static array $imported = array();

    /**
     * Compatibility with the old hookable attribute.
     *
     * @var string
     */
    protected string $hook;

    protected array $resolved_imports;

    protected array $resolved_handlers;

    /**
     * Constructor.
     *
     * @param string                  $hook     Hook name.
     * @param Container               $container DI container.
     * @param class-string<T>         $classname Class name.
     * @param array<int,string>       $callbacks Array of callbacks.
     * @param int                     $priority Hook priority.
     * @param int                     $context  Module context.
     * @param array<int,class-string> $imports  Array of submodules to import.
     * @param array<int,class-string> $handlers Array of handlers to register.
     * @param array<int,class-string> $services Array of autowired services.
     * @param bool                    $debug    Debug this hook.
     * @param bool                    $trace    Trace this hook.
     */
    public function __construct(
        string $hook,
        Container $container,
        string $classname,
        array $callbacks = array(),
        int $priority = 10,
        int $context = self::CTX_GLOBAL,
        /**
         * Array of submodules to import.
         *
         * @var array<int,class-string|Invokes_Module>
         */
        protected array $imports = array(),
        /**
         * Array of handlers to register.
         *
         * @var array<int,class-string>
         */
        protected array $handlers = array(),
        /**
         * Array of autowired services.
         *
         * @var array<int,class-string>
         */
        protected array $services = array(),
        bool $debug = false,
        bool $trace = false,
    ) {
        $this->hook = $hook;

        parent::__construct(
            tag: $hook,
            priority: $priority,
            container: $container,
            classname: $classname,
            callbacks: $callbacks,
            context: $context,
            strategy: self::INIT_AUTO,
            debug: $debug,
            trace: $trace,
        );
    }

    public function get_imports(): array {
        return $this->resolved_imports ??= $this->resolve( ...$this->imports );
    }

    public function get_handlers(): array {
        return $this->resolved_handlers ??= $this->resolve( ...$this->handlers );
    }

    public function get_services(): array {
        return $this->services;
    }

    public function get_configuration(): array {
        return \method_exists( $this->classname, 'configure' )
            ? $this->classname::configure()
            : array();
    }

    protected function get_constructor_args(): array {
        return array( 'hook', 'priority', 'context', 'imports', 'handlers', 'services', 'debug', 'trace' );
    }
}
