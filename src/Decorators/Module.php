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
     * @param string                  $container  Container ID.
     * @param int                     $context    Module context.
     * @param array<int,class-string> $imports    Array of submodules to import.
     * @param array<int,class-string> $handlers   Array of handlers to register.
     * @param array<int,class-string> $services   Array of autowired services.
     * @param bool                    $extendable Is the module extendable.
     */
    public function __construct(
        string $hook,
        int $priority,
        string $container = '',
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
        /**
         * Is the module extendable?
         *
         * @var bool
         */
        protected bool $extendable = false,
    ) {
        parent::__construct(
            tag: $hook,
            priority: $priority,
            context: $context,
            strategy: self::INIT_AUTO,
            container: $container,
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

    public function get_definition(): array {
        return \method_exists( $this->classname, 'configure' )
            ? $this->classname::configure()
            : array();
    }

    public function get_data(): array {
        $data = parent::get_data();

        $data['args'] = \array_merge(
            \xwp_array_diff_assoc( $data['args'], 'conditional', 'hookable', 'modifiers', 'strategy', 'tag' ),
            array(
                'extendable' => $this->extendable,
                'handlers'   => $this->handlers,
                'hook'       => $this->tag,
                'imports'    => $this->imports,
            ),
        );

        return $data;
    }

    /**
     * Initialize the module.
     *
     * Register the handlers.
     *
     * @return bool
     */
    protected function on_initialize(): bool {
        parent::on_initialize();

        /**
         * Fires when a module is initialized.
         *
         * @param Module<T> $module Module instance.
         *
         * @since 2.0.0
         */
        \do_action( "xwp_{$this->get_app_uuid()}_module_init", $this );

        return true;
    }

    protected function get_token_prefix(): string {
        return 'Module';
    }
}
