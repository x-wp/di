<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Module decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use XWP\DI\Interfaces\Can_Import;

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
class Module extends Handler implements Can_Import {
    /**
     * Did the module import submodules?
     *
     * @var array<class-string,bool>
     */
    protected static array $imported = array();

    /**
     * Constructor.
     *
     * @param string                  $hook       Hook name.
     * @param int                     $priority   Hook priority.
     * @param int                     $context    Module context.
     * @param array<int,class-string> $imports    Array of submodules to import.
     * @param array<int,class-string> $handlers   Array of handlers to register.
     * @param array<int,class-string> $services   Array of autowired services.
     * @param mixed                   ...$args    Deprecated arguments.
     */
    public function __construct(
        string $hook,
        int $priority = 10,
        int $context = self::CTX_GLOBAL,
        /**
         * Array of submodules to import.
         *
         * @var array<int,class-string>
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
        mixed ...$args,
    ) {
        $params = array(
            'args'     => $args,
            'context'  => $context,
            'priority' => $priority,
            'strategy' => self::INIT_AUTO,
            'tag'      => $hook,
        );

        parent::__construct( ...$params );
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

        $data['args'] = \array_merge(
            \xwp_array_diff_assoc( $data['args'], 'conditional', 'hookable', 'modifiers', 'strategy', 'tag' ),
            array(
                'handlers' => $this->handlers,
                'hook'     => $this->tag,
                'imports'  => $this->imports,
            ),
        );

        return $data;
    }
}
