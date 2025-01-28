<?php
/**
 * Module decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use DI\Definition\Source\DefinitionSource;

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
     * @param  string                  $container  Container ID.
     * @param  string                  $hook       Hook name.
     * @param  int                     $priority   Hook priority.
     * @param  array<int,class-string> $imports    Array of submodules to import.
     * @param  array<int,class-string> $handlers   Array of handlers to register.
     * @param  bool                    $extendable Is the module extendable.
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
     * @return array<array<DefinitionSource>>
     */
    public function get_definitions(): array {
        $defs = array( $this->get_definition() );

        foreach ( $this->get_imports() as $import ) {
            $module = $this->imported ? \xwp_get_module( $import ) : \xwp_register_module( $import );

            $defs = \array_merge( $defs, $module->get_definitions() );
        }

        $this->imported = true;

        return \array_values( ( \array_filter( $defs, static fn( $d ) => \count( $d ) > 0 ) ) );
    }

    /**
     * Get the module imports.
     *
     * @return array<int,class-string>
     */
    protected function get_imports(): array {
        if ( ! $this->extendable ) {
            return $this->imports;
        }

        $tag = "xwp_extend_import_{$this->container_id}";

        /**
         * Filter the module imports.
         *
         * @param  array<int,class-string> $imports    Array of submodules to import.
         * @param  class-string            $classname  Module classname.
         * @return array<int,class-string>
         *
         * @since 1.0@beta.8
         */
        return \apply_filters( $tag, $this->imports, $this->classname );
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
