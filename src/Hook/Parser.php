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
use XWP\DI\Container;
use XWP\DI\Decorators\Module;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Definition\HandlerDefinition;
use XWP\DI\Definition\ModuleDefinition;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Hook;
use XWP\DI\Invoker;

/**
 * Parses the hook decorated classes.
 *
 * @template TTgt of object
 *
 * @internal Hook parsing pipeline detail.
 */
class Parser {
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
     * Preload the definitions.
     *
     * @var bool
     */
    private bool $preload = false;

    /**
     * Is the module extendable?
     *
     * @var bool
     */
    private bool $extendable = false;

    /**
     * Factory instance.
     *
     * @var Factory
     */
    private Factory $factory;

    /**
     * Optional framework root wrapping the user module.
     *
     * @var ModuleDefinition|null
     */
    private ?ModuleDefinition $root = null;

    /**
     * Constructor.
     *
     * @param  class-string<TTgt> $module Application module.
     * @param  string             $app_id Application ID.
     */
    public function __construct( private string $module, private string $app_id ) {
        $this->factory = new Factory();
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
     * Set the framework root for this application build.
     *
     * @param  ModuleDefinition $root Root module composition.
     * @return static
     */
    public function with_root( ModuleDefinition $root ): static {
        $this->root = $root;

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

        return $this->parse_root( true );
    }

    /**
     * Make the hook definitions.
     *
     * @param  bool $preload Preload the definitions.
     * @return static
     *
     * @throws \DI\DependencyException If a module is not found.
     */
    public function make( bool $preload = false ): static {
        $this->data    = \array_fill_keys( \array_keys( $this->data ), array() );
        $this->ids     = array();
        $this->cached  = false;
        $this->preload = $preload;

        return $this
            ->parse_root( $preload )
            ->parse_module( $this->module, $preload )
            ->extend( $preload );
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
            array( Factory::class => \DI\autowire()->constructor( container: \DI\get( Container::class ) ) ),
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
     * @param  string $token Hook parameter token.
     * @return DefinitionHelper
     */
    private function define( string $token ): DefinitionHelper {
        return \DI\factory( array( Factory::class, 'make' ) )->parameter( 'hook', \DI\get( $token ) );
    }

    /**
     * Parse the extended imports.
     *
     * @param  bool $preload Preload the definitions.
     * @return static
     */
    private function extend( bool $preload ): static {
        foreach ( $this->get_extensions() as $addon ) {
            $this
                ->parse_extension( $addon )
                ->parse_module( $addon['module'], $preload );

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
        $this->data['values'][ "Hook-{$this->module}[params]" ]['args']['imports'][] = $addon['module'];

        if ( $this->cached && $addon['file'] ) {
            \xwp_log( "Registering uninstall hook for {$addon['file']}" );
            \register_uninstall_hook( $addon['file'], 'xwp_uninstall_ext' );
        }

        return $this;
    }

    // Keep custom-module and definition traversal in the same order.
    // phpcs:disable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh
    /**
     * Parse the module.
     *
     * @template T of object
     * @param  class-string<T> $module Module classname.
     * @param  bool            $preload Preload the definitions.
     * @return static
     *
     * @throws \DI\DependencyException If a circular dependency is detected.
     */
    private function parse_module( string $module, bool $preload ): static {
        $hook = $this->factory->resolve_module( $module );

        $composition = $hook instanceof HandlerDefinition ? ModuleDefinition::from_data(
            $hook->get_data(),
        ) : $hook;

        $this
            ->append(
                'definitions',
                $hook instanceof HandlerDefinition ? $hook->get_class() : $hook->get_classname(),
            )
            ->parse_handler( $hook, $preload );

        foreach ( $composition->get_imports() as $import ) {
            $this->parse_module( $import, $preload );
        }

        foreach ( $composition->get_services() as $svc ) {
            $this->append( 'services', $svc, $svc );
        }

        if ( $preload ) {
            foreach ( $composition->get_handlers() as $handler ) {
                $this->parse_handler( $this->factory->resolve_handler( $handler ), $preload );
            }
        }

        return $this;
    }

    // phpcs:enable SlevomatCodingStandard.Complexity.Cognitive.ComplexityTooHigh

    /**
     * Add the configured root, including when loading an older hook cache.
     *
     * Root definitions are supplied by the builder before user definitions.
     * Keep the user module as the anchor for extension definition merging.
     *
     * @param  bool $preload Whether to resolve callback metadata now.
     * @return static
     */
    private function parse_root( bool $preload ): static {
        if ( null === $this->root ) {
            return $this;
        }

        $root = HandlerDefinition::from_data(
            array(
                'args'   => array(
                    'context'  => Module::CTX_GLOBAL,
                    'handlers' => $this->root->get_handlers(),
                    'hook'     => 'plugins_loaded',
                    'imports'  => $this->root->get_imports(),
                    'priority' => 10,
                    'services' => $this->root->get_services(),
                    'strategy' => Module::INIT_NOW,
                ),
                'params' => array(
                    'callbacks' => null,
                    'classname' => $this->root->get_metatype(),
                    'params'    => array( 'on_initialize' => array() ),
                ),
                'type'   => Module::class,
            ),
        );

        $this->parse_handler( $root, $preload );

        return $this;
    }

    /**
     * Parse the handler.
     *
     * @template T of object
     * @param Can_Handle<T>|HandlerDefinition $handler Handler instance.
     * @param bool                            $preload Preload the definitions.
     *
     * @throws \DI\DependencyException If a circular dependency is detected.
     */
    private function parse_handler( Can_Handle|HandlerDefinition $handler, bool $preload ): void {
        $this->check_id( $handler );
        $this->add_hook( $this->parse_callbacks( $handler, $preload ) );
    }

    /**
     * Parse the handler callbacks.
     *
     * @template T of object
     * @param Can_Handle<T>|HandlerDefinition $handler Handler instance.
     * @param bool                            $preload Preload the definitions.
     * @return Can_Handle<T>|HandlerDefinition
     */
    private function parse_callbacks( Can_Handle|HandlerDefinition $handler, bool $preload ): Can_Handle|HandlerDefinition {
        if ( ! $preload || null !== $handler->get_callbacks() ) {
            return $handler;
        }

        $cbs = array();

        foreach ( $this->factory->resolve_callbacks( $handler ) as $cb ) {
            $cbs[] = $this->check_id( $cb )->add_hook( $cb )->get_token();
        }

        if ( $handler instanceof HandlerDefinition ) {
            $data                        = $handler->get_data();
            $data['params']['callbacks'] = $cbs;
            return HandlerDefinition::from_data( $data );
        }

        $handler->with_callbacks( $cbs );

        return $handler;
    }

    /**
     * Get the raw hook definition.
     *
     * @template TRfl of Reflector
     * @template TObj of object
     *
     * @param  Can_Hook<TObj,TRfl>|HandlerDefinition|CallbackDefinition $hook Hook instance.
     * @return Can_Hook<TObj,TRfl>|HandlerDefinition|CallbackDefinition
     */
    private function add_hook( Can_Hook|HandlerDefinition|CallbackDefinition $hook ): Can_Hook|HandlerDefinition|CallbackDefinition {
        if ( $hook instanceof Can_Hook ) {
            $hook->with_cache( $this->cached || $this->preload );
        }
        $token = $hook->get_token();
        $param = $token . '[params]';

        return $this
            ->append( 'hooks', $param, $token )
            ->append( 'values', $hook->get_data(), $param )
            ->set_id( $hook );
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
            $merged[ $key ] = \array_merge_recursive( $merged[ $key ] ?? array(), $val );
        }

        return $merged;
    }

    /**
     * Append a value to the definition.
     *
     * @param  'aliases'|'definitions'|'services'|'values'|'hooks'|'extensions' $type  Type of value.
     * @param  mixed                                                            $value Value to append.
     * @param  string|null                                                      $key  Optional key.
     *
     * @return static
     */
    private function append( string $type, mixed $value, ?string $key = null ): static {
        null !== $key
            ? $this->data[ $type ][ $key ] = $value
            : $this->data[ $type ][]       = $value;

        return $this;
    }

    /**
     * Set the hook ID.
     *
     * Check if the hook ID exists.
     *
     * @template TRfl of Reflector
     * @template TObj of object
     *
     * @param  Can_Hook<TObj,TRfl>|HandlerDefinition|CallbackDefinition $hook Hook instance.
     * @return Can_Hook<TObj,TRfl>|HandlerDefinition|CallbackDefinition
     */
    private function set_id( Can_Hook|HandlerDefinition|CallbackDefinition $hook ): Can_Hook|HandlerDefinition|CallbackDefinition {
        $this->ids[ $hook->get_token() ] = true;

        return $hook;
    }

    /**
     * Check if the hook ID exists.
     *
     * @template TRfl of Reflector
     * @template TObj of object
     * @param  Can_Hook<TObj,TRfl>|HandlerDefinition|CallbackDefinition $hook Hook instance.
     * @return static
     *
     * @throws \DI\DependencyException If a circular dependency is detected.
     */
    private function check_id( Can_Hook|HandlerDefinition|CallbackDefinition $hook ): static {
        if ( isset( $this->ids[ $hook->get_token() ] ) ) {
            throw new \DI\DependencyException( 'Circular dependency detected.' );
        }

        return $this;
    }
}
