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
     * Definitions array.
     *
     * @var array{
     *   services: array<string,class-string>,
     *   hooks: array<string,Can_Hook<object,Reflector>|array<string,mixed>>,
     *   values: array<string,mixed>,
     *   aliases: array<string,string>,
     *   definitions: array<string,mixed>,
     *   extensions: array<int,array{
     *     id: string,
     *     module: class-string,
     *     file: false|string,
     *     version: string
     *   }>
     * }
     */
    private array $data = array(
        'aliases'     => array(),
        'definitions' => array(),
        'extensions'  => array(),
        'hooks'       => array(),
        'services'    => array(),
        'values'      => array(),
    );

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
     * Is the module extendable?
     *
     * @var bool
     */
    private bool $extendable = false;

    /**
     * Constructor.
     *
     * @param  class-string<TTgt> $module Application module.
     * @param  string             $app_id Application ID.
     */
    public function __construct( private string $module, private string $app_id ) {
    }

    /**
     * Set the extendable flag.
     *
     * @param  bool $ext Extendable flag.
     * @return static
     */
    public function set_extendable( bool $ext ): static {
        $this->extendable = $ext;
        return $this;
    }

    /**
     * Load the compiled hook definitions.
     *
     * @param  array<string,array<string,mixed>> $definition Hook definitions.
     * @return static
     */
    public function load( array $definition ): static {
        $this->data   = \wp_parse_args( $definition, $this->data );
        $this->ids    = array();
        $this->cached = true;

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
        $this->data   = \array_fill_keys( \array_keys( $this->data ), array() );
        $this->ids    = array();
        $this->cached = 'complete' === $type;

        return $this
            ->parse_module(
                $this->get_module( $this->module )
                ??
                throw new \DI\DependencyException( 'Module not found.' ),
                $type,
            )->extend( $type );
    }

    /**
     * Get the raw hook definitions.
     *
     * @return array{
     *   services: array<string,class-string>,
     *   hooks: array<string,Can_Hook<object,Reflector>|array<string,mixed>>,
     *   values: array<string,mixed>,
     *   aliases: array<string,string>,
     *   definitions: array<string,mixed>,
     *   extensions: array<array<string,mixed>>,
     * }
     */
    public function get_raw(): array {
        return $this->data;
    }

    /**
     * Get the parsed hook definitions.
     *
     * @return array<string,mixed>
     */
    public function get_parsed(): array {
        //phpcs:disable SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
        return \array_merge(
            \array_map( '\DI\get', $this->data['aliases'] ),
            \array_map( array( $this, 'define' ), $this->data['hooks'] ),
            \array_map( '\DI\value', $this->data['values'] ),
            \array_map( static fn() => \DI\autowire(), $this->data['services'] ),
            \array_reduce( $this->data['extensions'], array( $this, 'merge_definition' ), $this->get_definition( $this->module ) ),
            ...\array_map( array( $this, 'get_configuration' ), $this->data['definitions'] ),
        );
        //phpcs:enable SlevomatCodingStandard.Functions.RequireMultiLineCall.RequiredMultiLineCall
    }

    /**
     * Get extensions
     *
     * @return array<int,array{
     *   id:string,
     *   module:class-string,
     *   file: false|string,
     *   version: string
     *  }>
     */
    private function get_extensions(): array {
        if ( ! $this->extendable ) {
            return array();
        }

        /**
         * Filter the extended imports.
         *
         * @param  array<array<string,mixed>> $extra  Extra imports.
         * @return array<int,array{
         *   id:string,
         *   module:class-string,
         *   file: false|string,
         *   version: string
         *  }>
         */
        $this->data['extensions'] = \apply_filters( "xwp_extend_import_{$this->app_id}", array() );

        return $this->data['extensions'];
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
     * Parse the extended imports.
     *
     * @param  'base'|'complete' $type Type of definition.
     * @return static
     */
    private function extend( string $type ): static {
        foreach ( $this->get_extensions() as $addon ) {
            $this
                ->parse_extension( $addon )
                ->parse_module( $this->get_module( $addon['module'] ), $type );

        }

        return $this;
    }

    /**
     * Parse the extension.
     *
     * @param  array<string,mixed> $addon Extension data.
     * @return static
     */
    private function parse_extension( array $addon ): static {
        $this->data['values'][ "Module-{$this->module}[params]" ]['args']['imports'][] = $addon['module'];

        if ( $this->cached && $addon['file'] ) {
            \xwp_log( "Registering uninstall hook for {$addon['file']}" );
            \register_uninstall_hook( $addon['file'], 'xwp_uninstall_ext' );
        }

        return $this;
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
        $this->parse_handler( $module, $type );

        $this->data['aliases'][ $this->handler_token( $module ) ] = $module->get_token();
        $this->data['definitions'][]                              = $module->get_classname();

        foreach ( $module->get_imports() as $import ) {
            $this->parse_module( $this->get_module( $import ), $type );
        }

        foreach ( $module->get_services() as $svc ) {
            $this->data['services'][ $svc ] = $svc;
        }

        if ( 'complete' === $type ) {
            foreach ( $module->get_handlers() as $handler ) {
                $this->parse_handler( $this->make_handler( $handler ), $type );
            }
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

        $this->data['values'][ 'Hooks-' . $handler->get_classname() ] = $hooks;

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

        $this->ids[ $token ]            = true;
        $this->data['hooks'][ $token ]  = $param;
        $this->data['values'][ $param ] = $data;

        return $hook;
    }

    /**
     * Get the module configuration.
     *
     * @template T of object
     * @param  class-string<T> $cname Module classname.
     * @return array<string,mixed>
     */
    private function get_configuration( string $cname ): array {
        return \method_exists( $cname, 'configure' )
            ? $cname::configure()
            : array();
    }

    /**
     * Get module definition.
     *
     * @template T of object
     * @param  class-string<T> $cname Module classname.
     * @return array<string,mixed>
     */
    private function get_definition( string $cname ): array {
        return match ( true ) {
            \method_exists( $cname, 'define' ) => $cname::define(),
            \method_exists( $cname, 'extend' ) => $cname::extend(),
            default                            => array(),
        };
    }

    /**
     * Merge the extended definition.
     *
     * @param  array<string,mixed> $merged Merged definition.
     * @param  array<string,mixed> $ext    Extended definition.
     * @return array<string,mixed>
     */
    private function merge_definition( ?array $merged, array $ext ): array {
        $merged ??= $this->get_definition( $this->module );

        $merged['app.extensions'] ??= array();

        $info = array(
            'file'   => $ext['file'],
            'module' => $ext['module'],
            'ver'    => $ext['version'],
        );

        if ( 'plugin' === $ext['type'] && $ext['file'] ) {
            $info['base'] = \DI\factory( 'plugin_basename' )->parameter( 'file', $ext['file'] );
            $info['path'] = \DI\factory( 'plugin_dir_path' )->parameter( 'file', $ext['file'] );
            $info['url']  = \DI\factory( 'plugin_dir_url' )->parameter( 'file', $ext['file'] );
        }

        $merged[ "app.ext.{$ext['id']}" ] = $info;

        $merged['app.extensions'][ $ext['id'] ] = \DI\get( "app.ext.{$ext['id']}" );

        foreach ( $this->get_definition( $ext['module'] ) as $key => $val ) {
            $merged[ $key ] = \array_merge( $merged[ $key ] ?? array(), $val );
        }

        return $merged;
    }
}
