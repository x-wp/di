<?php
/**
 * Module decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

/**
 * Module decorator.
 *
 * @template T of object
 * @extends Handler<T>
 *
 * @since 1.0.0
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Module extends Handler {
    /**
     * Did the module import submodules?
     *
     * @var bool
     */
    protected bool $imported = false;

    /**
     * Constructor.
     *
     * @param  string                  $container Container ID.
     * @param  string                  $hook      Hook name.
     * @param  int                     $priority  Hook priority.
     * @param  array<int,class-string> $imports   Array of submodules to import.
     * @param  array<int,class-string> $handlers  Array of handlers to register.
     */
    public function __construct(
        string $container,
        string $hook,
        int $priority,
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
    ) {
        parent::__construct(
            tag: $hook,
            priority: $priority,
            strategy: self::INIT_DEFFERED,
            container: $container,
        );
    }

    /**
     * Initialize the module.
     *
     * Register the handlers.
     *
     * @return bool
     */
    protected function on_initialize(): bool {
        foreach ( $this->handlers as $handler ) {
            \xwp_register_hook_handler( $handler );
        }

        return parent::on_initialize();
    }

    /**
     * Get the module definitions.
     *
     * @return array<string,mixed>
     */
    public function get_definitions(): array {
        $definitions = $this->get_definition();

        foreach ( $this->imports as $import ) {
            $module = $this->imported ? \xwp_get_module( $import ) : \xwp_register_module( $import );

            $definitions = \array_merge( $definitions, $module->get_definitions() );
        }

        $this->imported = true;

        return $definitions;
    }

    /**
     * Get the module definition.
     *
     * @return array<string,mixed>
     */
    public function get_definition(): array {
        return \method_exists( $this->classname, 'configure' )
            ? $this->classname::configure()
            : array();
    }
}
