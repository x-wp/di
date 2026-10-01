<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Module runtime class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use XWP\DI\Container;
use XWP\DI\Definition\HandlerDefinition;
use XWP\DI\Definition\ModuleDefinition;
use XWP\DI\Interfaces\Can_Import;

/**
 * Runtime module composition consumed by the lifecycle coordinator.
 *
 * @template T of object
 * @extends Handler<T>
 * @implements Can_Import<T>
 * @internal
 */
class Module extends Handler implements Can_Import {
    /**
     * Immutable module composition.
     *
     * @var ModuleDefinition
     */
    protected ModuleDefinition $composition;

    /**
     * Constructor.
     *
     * @param HandlerDefinition $definition Handler metadata.
     * @param Container|null    $container Runtime container.
     */
    public function __construct( HandlerDefinition $definition, ?Container $container = null ) {
        parent::__construct( $definition, $container );
        $options           = $definition->get_options();
        $this->composition = new ModuleDefinition(
            $definition->get_class(),
            imports: $options['imports'] ?? array(),
            handlers: $options['handlers'] ?? array(),
            services: $options['services'] ?? array(),
        );
    }

    public function get_imports(): array {
        return $this->composition->get_imports();
    }

    public function get_handlers(): array {
        return $this->composition->get_handlers();
    }

    public function get_services(): array {
        return $this->composition->get_services();
    }

    public function get_configuration(): array {
        return \method_exists( $this->classname, 'configure' ) ? $this->classname::configure() : array();
    }

    /**
     * Retain the internal module strategy adapter.
     *
     * @param string $strategy Initialization strategy.
     * @return static
     */
    public function with_strategy( string $strategy ): static {
        $this->strategy = $strategy;
        return $this;
    }
}
