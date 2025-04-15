<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing

namespace XWP\DI\Definition\Source;

use DI\Definition\Definition;
use DI\Definition\Source\Autowiring;
use DI\Definition\Source\DefinitionArray;
use XWP\DI\Container;
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
     * Dependency graph.
     *
     * @var array<string,mixed>
     */
    private array $graph = array();

    /**
     * Contexts for the definitions.
     *
     * @var array<string,int>
     */
    private array $contexts = array();

    /**
     * Constructor.
     *
     * @param  class-string<TApp> $module     Module class name.
     * @param  ?Autowiring        $autowiring Autowiring.
     */
    public function __construct( private string $module, ?Autowiring $autowiring = null ) {
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

        $defs = $this->process_module( $root, $this->graph );

        $defs['app.module']    = \DI\get( $root->get_token() );
        $defs['xwp.invoker'] ??= \DI\autowire( Invoker::class )->constructor(
            factory: \DI\get( Factory::class ),
            container: \DI\get( Container::class ),
        );

        $this->addDefinitions( $defs );

        $this->initialized = true;
    }

    /**
     * Process the module.
     *
     * @template TMod of object
     * @param  Decorates_Module<TMod> $module Module class name.
     * @param  array<string,mixed>    $node   Node to process.
     * @return array<string,mixed>
     */
    private function process_module( Decorates_Module $module, array &$node ): array {
        $t          = $module->get_token();
        $node[ $t ] = array();

        $defs = \array_merge(
            $module->get_configuration(),
            $this->process_handler( $module, $node[ $t ] ),
        );

        foreach ( $module->get_imports() as $import ) {
            $defs = \array_merge( $defs, $this->process_module( $import, $node[ $t ] ) );
        }

        foreach ( $module->get_handlers() as $handler ) {
            $ht                = $handler->get_token();
            $node[ $t ][ $ht ] = array();

            $defs = \array_merge( $defs, $this->process_handler( $handler, $node[ $t ][ $ht ] ) );
        }

        foreach ( $module->get_services() as $service_token => $def ) {
            $defs[ $service_token ] = \DI\autowire( $def );
        }

        return $defs;
    }

    /**
     * Process the handler.
     *
     * @template THndlr of object
     * @param  Decorates_Handler<THndlr> $handler Handler class name.
     * @param  array<string,mixed>       $node   Node to process.
     * @return array<string,mixed>
     */
    private function process_handler( Decorates_Handler $handler, array &$node ): array {
        $defs = array();

        $this->contexts[ $handler->get_token() ] = $handler->get_context();

        foreach ( $handler->get_callbacks() as $cb ) {
            $token          = $cb->get_token();
            $defs[ $token ] = \XWP\DI\hook( ...$cb->get_data() );
            $node[]         = $token;

            $this->contexts[ $token ] = $handler->get_context();
        }

        $defs[ $handler->get_token() ] = \XWP\DI\hook( ...$handler->get_data() );

        return $defs;
    }
}
