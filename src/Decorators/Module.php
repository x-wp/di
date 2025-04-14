<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Module decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use XWP\DI\Interfaces\Decorates_Handler;
use XWP\DI\Interfaces\Decorates_Module;

/**
 * Module decorator.
 *
 * @template T of object
 * @extends Handler<T>
 * @implements Decorates_Module((<T>
 *
 * @since 1.0.0
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Module extends Handler implements Decorates_Module {
    /**
     * Did the module import submodules?
     *
     * @var array<class-string,bool>
     */
    protected static array $imported = array();

    /**
     * Array of submodules to import.
     *
     * @var array<int,Decorates_Module<object>>
     */
    protected array $imports;

    /**
     * Array of handlers to register.
     *
     * @var array<int,Decorates_Handler<object>>
     */
    protected array $handlers;

    /**
     * Compatibility with the old hookable attribute.
     *
     * @var string
     */
    protected string $hook;

    /**
     * Constructor.
     *
     * @param string                                              $hook     Hook name.
     * @param Closure|string|int|array{0: class-string,1: string} $priority    Hook priority.
     * @param int                                                 $context  Module context.
     * @param array<int,class-string>                             $imports  Array of submodules to import.
     * @param array<int,class-string>                             $handlers Array of handlers to register.
     * @param array<int,class-string>                             $services Array of autowired services.
     * @param bool                                                $debug    Debug this hook.
     * @param bool                                                $trace    Trace this hook.
     * @param mixed                                               ...$args  Deprecated arguments.
     */
    public function __construct(
        string $hook,
        Closure|array|int|string $priority = 10,
        int $context = self::CTX_GLOBAL,
        array $imports = array(),
        array $handlers = array(),
        /**
         * Array of autowired services.
         *
         * @var array<int,class-string>
         */
        protected array $services = array(),
        bool $debug = false,
        bool $trace = false,
        mixed ...$args,
    ) {
        $args = $args[0] ?? $args;

        $this->hook     = $hook;
        $this->imports  = \array_map( static fn( $i ) => self::from_classname( $i ), $imports );
        $this->handlers = \array_map( static fn( $i ) => Handler::from_classname( $i ), $handlers );

        parent::__construct(
            tag: $hook,
            priority: $priority,
            context: $context,
            strategy: self::INIT_AUTO,
            debug: $debug,
            trace: $trace,
        );
    }

    public function get_imports(): array {
        return $this->imports;
    }

    public function get_handlers(): array {
        return $this->handlers;
    }

    public function get_services(): array {
        return $this->services;
    }

    public function get_configuration(): array {
        return \method_exists( $this->classname, 'configure' )
            ? $this->classname::configure()
            : array();
    }

    public function get_data(): array {
        $data = parent::get_data();

        $data['construct']['imports']  = \array_map( static fn( $i ) => $i->get_token(), $this->imports );
        $data['construct']['handlers'] = \array_map( static fn( $h ) => $h->get_token(), $this->handlers );

        return $data;
    }

    protected function get_token_prefix(): string {
        return 'm:';
    }

    protected function get_constructor_args(): array {
        return array(
            'hook',
            'priority',
            'context',
            'imports',
            'handlers',
            'classname',
            'debug',
            'trace',
        );
    }
}
