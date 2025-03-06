<?php
/**
 * Parser class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use DI\Definition\Helper\DefinitionHelper;
use Reflector;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Hook;
use XWP\DI\Interfaces\Can_Import;
use XWP\DI\Invoker;
use XWP\DI\Traits\Hook_Factory_Methods;

/**
 * Parses the hook decorated classes.
 *
 * @template TTgt of object
 */
class Parser {
    use Hook_Factory_Methods;

    /**
     * Raw hook definitions.
     *
     * @var array<string,Can_Hook<object,Reflector>|array<string,mixed>>
     */
    private array $hooks = array();

    /**
     * Values.
     *
     * @var array<string,mixed>
     */
    private array $values = array();

    /**
     * Aliases.
     *
     * @var array<string,string>
     */
    private array $aliases = array();

    /**
     * Undocumented variable
     *
     * @var array<array<string,mixed>>
     */
    private array $defs = array();

    /**
     * List of tokens.
     *
     * @var array<string>
     */
    private array $ids = array();

    /**
     * Is the hook definition cached?
     *
     * @var bool
     */
    private bool $cached = false;

    /**
     * Constructor.
     *
     * @param  class-string<TTgt> $module Application module.
     */
    public function __construct( private string $module ) {
    }

    /**
     * Load the compiled hook definitions.
     *
     * @param  array<string,array<string,mixed>> $definition Hook definitions.
     * @return static
     */
    public function load( array $definition ): static {
        $this->hooks   = $definition['hooks'] ?? array();
        $this->values  = $definition['values'] ?? array();
        $this->aliases = $definition['aliases'] ?? array();
        $this->defs    = $definition['defs'] ?? array();
        $this->ids     = array();
        $this->cached  = true;

        return $this;
    }

    /**
     * Make the hook definitions.
     *
     * @param  'base'|'complete' $type Type of definition.
     * @return static
     *
     * @throws \DI\DependencyException If a module is not found.
     */
    public function make( string $type = 'base' ): static {
        $this->ids     = array();
        $this->hooks   = array();
        $this->values  = array();
        $this->aliases = array();
        $this->defs    = array();
        $this->cached  = 'complete' === $type;

        $root = $this->get_module( $this->module ) ?? throw new \DI\DependencyException( 'Module not found.' );

        return $this->parse_module( $root, $type );
    }

    /**
     * Get the raw hook definitions.
     *
     * @return array{
     *   aliases: array<string,string>,
     *   defs: array<array<string,mixed>>,
     *   hooks: array<string,array<string,mixed>>,
     *   values: array<string,mixed>,
     * }
     */
    public function get_raw(): array {
        return array(
            'aliases' => $this->aliases,
            'defs'    => $this->defs,
            'hooks'   => $this->hooks,
            'values'  => $this->values,
        );
    }

    /**
     * Get the parsed hook definitions.
     *
     * @return array<string,mixed>
     */
    public function get_parsed(): array {
        return \array_merge(
            \array_map( '\DI\get', $this->aliases ),
            \array_map( array( $this, 'define' ), $this->hooks ),
            \array_map( '\DI\value', $this->values ),
            ...\array_map( array( $this, 'get_definition' ), $this->defs ),
        );
    }

    /**
     * Get the hook definition.
     *
     * @param  string $hook Hook classname.
     * @return DefinitionHelper
     */
    private function define( string $hook ): DefinitionHelper {
        return \DI\factory( array( Invoker::class, 'make_hook' ) )
            ->parameter( 'target', \DI\get( $hook ) );
    }

    /**
     * Parse the module.
     *
     * @template T of object
     * @param  Can_Import<T>     $module Module instance.
     * @param  'base'|'complete' $type Type of definition.
     * @return static
     *
     * @throws \DI\DependencyException If a circular dependency is detected.
     */
    private function parse_module( Can_Import $module, string $type ): static {
        if ( isset( $this->ids[ $module->get_token() ] ) ) {
            throw new \DI\DependencyException( 'Circular dependency detected.' );
        }

        $this->parse_handler( $module, $type );
        $this->aliases[ $this->handler_token( $module ) ] = $module->get_token();
        $this->defs[]                                     = $module->get_classname();

        if ( 'complete' === $type ) {
            foreach ( $module->get_handlers() as $handler ) {
                $this->parse_handler( $this->make_handler( $handler ), $type );
            }
        }

        foreach ( $module->get_imports() as $import ) {
            $this->parse_module( $this->get_module( $import ), $type );
        }

        return $this;
    }

    /**
     * Get the handler token.
     *
     * @template T of object
     * @param  Can_Import<T> $module Module instance.
     * @return string
     */
    private function handler_token( Can_Import $module ): string {
        return \str_replace( 'Module-', 'Handler-', $module->get_token() );
    }

    /**
     * Parse the handler.
     *
     * @template T of object
     * @param Can_Handle<T>     $handler Handler instance.
     * @param  'base'|'complete' $type Type of definition.
     *
     * @throws \DI\DependencyException If a circular dependency is detected.
     */
    private function parse_handler( Can_Handle $handler, string $type ): void {
        if ( isset( $this->ids[ $handler->get_token() ] ) ) {
            throw new \DI\DependencyException( 'Circular dependency detected.' );
        }

        if ( 'complete' === $type && ! $handler->get_hooks() ) {
            $handler = $handler->with_hooks( $this->parse_hooks( $handler ) );
        }

        // @phpstan-ignore argument.type
        $this->add_hook( $handler );
    }

    /**
     * Parse the handler.
     *
     * @template T of object
     * @param Can_Handle<T> $handler Handler instance.
     * @return array<int,string>
     *
     * @throws \DI\DependencyException If a circular dependency is detected.
     */
    private function parse_hooks( Can_Handle $handler ): array {
        $hooks = array();

        foreach ( $this->get_hooks( $handler ) as $hook ) {
            if ( isset( $this->ids[ $hook->get_token() ] ) ) {
                throw new \DI\DependencyException( 'Circular dependency detected.' );
            }

            // @phpstan-ignore argument.type
            $hooks[] = $this->add_hook( $hook )->get_token();
        }

        $this->values[ 'Hooks-' . $handler->get_classname() ] = $hooks;

        return $hooks;
    }

    /**
     * Get the raw hook definition.
     *
     * @template T of object
     * @param  Can_Hook<T,Reflector> $hook Hook instance.
     * @return Can_Hook<T,Reflector>
     */
    private function add_hook( Can_Hook $hook ): Can_Hook {
        $token = $hook->get_token();
        $param = $token . '[params]';
        $data  = $hook->get_data();

        $data['params']['cache'] = $this->cached;

        $this->ids[ $token ]    = true;
        $this->hooks[ $token ]  = $param;
        $this->values[ $param ] = $data;

        return $hook;
    }

    /**
     * Get the module definition.
     *
     * @template T of object
     * @param  class-string<T> $cname Module classname.
     * @return array<string,mixed>
     */
    private function get_definition( string $cname ): array {
        return \method_exists( $cname, 'configure' )
            ? $cname::configure()
            : array();
    }
}
