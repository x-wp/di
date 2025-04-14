<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing

namespace XWP\DI\Definition\Source;

use DI\Definition\Definition;
use DI\Definition\Source\Autowiring;
use DI\Definition\Source\DefinitionArray;
use XWP\DI\Container;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;
use XWP\DI\Hook\Factory;
use XWP\DI\Interfaces\Decorates_Handler;
use XWP\DI\Interfaces\Decorates_Module;
use XWP\DI\Invoker;

/**
 * Reads DI definition from an application module.
 *
 * @template TApp of object
 */
class Definition_App extends DefinitionArray {
    /**
     * Indicates if the definitions have been initialized.
     *
     * @var bool
     */
    private bool $initialized = false;

    /**
     * Constructor.
     *
     * @param  class-string<TApp> $module     Module class name.
     * @param  ?Autowiring        $autowiring Autowiring.
     */
    public function __construct(
        private string $module,
        ?Autowiring $autowiring = null,
    ) {
        parent::__construct( array(), $autowiring );
    }

    public function getDefinition( string $name ): ?Definition {
        $this->initialize();

        return parent::getDefinition( $name );
    }

    public function getDefinitions(): array {
        $this->initialize();

        return parent::getDefinitions();
    }

    /**
     * Initialize the definitions.
     */
    private function initialize(): void {
        if ( $this->initialized ) {
            return;
        }

        $root = Module::from_classname( $this->module );
        $defs = $this->process_module( $root );

        $defs['app.module']    = \DI\get( $root->get_token() );
        $defs['xwp.invoker'] ??= \DI\autowire( Invoker::class )->constructor(
            factory: \DI\get( Factory::class ),
            container: \DI\get( Container::class ),
        );

        $this->addDefinitions( $defs );

        $this->initialized = true;

        \dump( $this->getDefinitions() );
        die;
    }

    /**
     * Process the module.
     *
     * @template TMod of object
     * @param  Decorates_Module<TMod> $module Module class name.
     * @return array<string,mixed>
     */
    private function process_module( Decorates_Module $module ): array {
        $defs = \array_merge(
            $module->get_configuration(),
            $this->process_handler( $module ),
        );

        foreach ( $module->get_handlers() as $handler ) {
            $defs = \array_merge( $defs, $this->process_handler( $handler ) );
        }

        foreach ( $module->get_imports() as $import ) {
            $defs = \array_merge( $defs, $this->process_module( $import ) );
        }

        return $defs;
    }

    /**
     * Process the handler.
     *
     * @template THndlr of object
     * @param  Decorates_Handler<THndlr> $handler Handler class name.
     * @return array<string,mixed>
     */
    private function process_handler( Decorates_Handler $handler ): array {
        $defs = array();

        foreach ( $handler->get_callbacks() as $cb ) {
            $defs[ $cb->get_token() ] = \XWP\DI\hook( ...$cb->get_data() );
        }

        $defs[ $handler->get_token() ] = \XWP\DI\hook( ...$handler->get_data() );

        return $defs;
    }
}
