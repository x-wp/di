<?php //phpcs:disable WordPress.Security.EscapeOutput.ExceptionNotEscaped

namespace XWP\DI\Definition;

use XWP\DI\Attributes\Module;
use XWP\DI\Core\Internal_Core_Module;
use XWP\DI\Utils\Reflection;

/**
 * Scans a module for dependencies and provides methods to retrieve them.
 *
 * @mixin Reflection
 */
class Dependency_Scanner {
    /**
     * The reflection instance used to inspect classes and methods.
     *
     * @var Reflection
     */
    private readonly Reflection $reflector;

    /**
     * Array of modules indexed by their class names.
     *
     * @var array<string,Module>
     */
    private array $modules = array();

    /**
     * Array of module IDs indexed by their class names.
     *
     * @var array<class-string,string>
     */
    private array $ids = array();

    /**
     * The entry point for the dependency scanner.
     *
     * @var class-string
     */
    private string $entry;

    /**
     * Constructor.
     *
     * @param ?Reflection $reflector Optional. Reflection instance to use for scanning. If not provided, a new instance will be created.
     */
    public function __construct( ?Reflection $reflector = null ) {
        $this->reflector = $reflector ?? new Reflection();
    }

    /**
     * Dynamically calls methods on the reflector instance.
     *
     * @param  string       $name Method name to call on the reflector.
     * @param  array<mixed> $args Arguments to pass to the method.
     * @return mixed
     *
     * @throws \BadMethodCallException If the method does not exist in the Reflection class.
     */
    public function __call( string $name, array $args = array() ): mixed {
        return \method_exists( $this->reflector, $name )
            ? $this->reflector->{$name}( ...$args )
            : throw new \BadMethodCallException( "Method {$name} does not exist in " . Reflection::class );
    }

    public function get_definitions(): array {
        $defs = array();

        foreach ( $this->modules as $module ) {
            $defs[] = $module->get_definition();
        }

        return array();
    }

    /**
     * Find a entry by type and name.
     *
     * @param  'm'|'h'|'c' $type Type of definition to find.
     * @param  string      $name Name of the definition to find.
     * @return mixed
     */
    public function find( string $type, string $name ): mixed {
        if ( 'm' === $type && 'entry' === $name ) {
            return $this->get_by_cname( $this->entry );
        }

        return null;
    }

    public function scan( string $module ): static {
        $this->set_entry( $module )->scan_for_modules( $module );

        return $this;
    }

    public function scan_for_modules( string $module ) {
        [ $module_ref, $added ] = $this->insert_module( $module );

        foreach ( $module_ref->get_imports() as $import ) {
            $this->scan_for_modules( $import );
        }
    }

    /**
     * Loads the module definitions and returns them as an array.
     *
     * @param 'entry'|'all' $method The method to load definitions for. 'entry' loads only the entry module, 'all' loads all modules.
     * @return array<string,mixed>
     */
    public function load( string $method ): array {
        return match ( $method ) {
            'entry' => \array_merge(
                \array_map( \XWP\DI\module( ... ), $this->modules ),
                ...\array_values( \array_map( static fn( $m ) => $m->get_definition(), $this->modules ) ),
            ),
            'all'   => $this->get_definitions(),
            default => throw new \InvalidArgumentException( "Invalid method {$method} for loading." ),
        };
    }

    /**
     * Insert a module into the modules array if it does not already exist.
     *
     * @param string $module The module to insert.
     * @return array{0: Module, 1: bool}
     */
    private function insert_module( string $module ): array {
        if ( ! \class_exists( $module ) ) {
            throw new \InvalidArgumentException( "Module class {$module} does not exist." );
        }

        $instance = $this->get_by_cname( $module );

        if ( $instance ) {
            // If the module is already registered, return it.
            return array( $instance, false );
        }

        $instance = $this->get_module( $module );
        $inserted = null === $this->get_by_id( $instance->get_id() );

        $this->modules[ $instance->get_id() ] = $instance->get_classname() === $this->entry
            ? $instance->add_import( Internal_Core_Module::class )
            : $instance;

        $this->ids[ $instance->get_classname() ] = $instance->get_id();

        return array(
            $instance,
            $inserted,
        );
    }

    private function get_by_cname( string $cname ): ?Module {
        return isset( $this->ids[ $cname ] )
            ? $this->get_by_id( $this->ids[ $cname ] )
            : null;
    }

    private function get_by_id( string $id ): ?Module {
        return $this->modules[ $id ] ?? null;
    }

    private function set_entry( string $entry ): static {
        $this->entry ??= $entry;

        return $this;
    }
}
