<?php //phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
/**
 * Container class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use DI\Container as DI_Container;
use DI\Definition\Source\MutableDefinitionSource;
use DI\Proxy\ProxyFactoryInterface;
use Psr\Container\ContainerInterface;
use XWP\DI\Interfaces\Can_Handle;

/**
 * Custom WordPress container.
 *
 * @mixin Invoker
 */
class Container extends DI_Container {
    /**
     * Invoker methods.
     */
    private const INV_METHODS = array(
        'create_handler',
        'register_handler',
        'load_handler',
        'load_callbacks',
    );

    /**
     * Use `$container = new Container()` if you want a container with the default configuration.
     *
     * If you want to customize the container's behavior, you are discouraged to create and pass the
     * dependencies yourself, the ContainerBuilder class is here to help you instead.
     *
     * @see ContainerBuilder
     *
     * @param array<string,mixed>|MutableDefinitionSource $definitions      The container definitions.
     * @param ProxyFactoryInterface|null                  $proxyFactory     The proxy factory to use.
     * @param ContainerInterface                          $wrapperContainer If the container is wrapped by another container.
     *
     * @internal Prefer App_Builder, xwp_create_app(), or xwp_load_app().
     */
    public function __construct(
        array|MutableDefinitionSource $definitions = array(),
        ?ProxyFactoryInterface $proxyFactory = null,
        ?ContainerInterface $wrapperContainer = null,
    ) {
        parent::__construct( $definitions, $proxyFactory, $wrapperContainer );

        $this->resolvedEntries[ self::class ]    = $this;
        $this->resolvedEntries[ static::class ]  = $this;
        $this->resolvedEntries['xwp.invoker']    = $this->has( 'xwp.invoker' )
            ? $this->get( 'xwp.invoker' )
            : $this->get( Invoker::class );
        $this->resolvedEntries[ Invoker::class ] = $this->resolvedEntries['xwp.invoker'];
    }

    /**
     * Magic method to call invoker methods.
     *
     * @param  string             $name Method name.
     * @param  array<mixed,mixed> $args Method arguments.
     * @return mixed
     *
     * @internal Runtime proxy for Invoker methods.
     */
    public function __call( string $name, array $args ): mixed {
        if ( \in_array( $name, self::INV_METHODS, true ) ) {
            return $this->resolvedEntries['xwp.invoker']->$name( ...$args );
        }

        return null;
    }

    /**
     * Delegate startup to the XWP application.
     *
     * @return static
     *
     * @throws \RuntimeException If the application is already started.
     */
    public function run(): static {
        $this->get( App::class )->run();

        return $this;
    }

    /**
     * Register a handler or a module.
     *
     * @template T of object
     * @param T $handler Class instance to register as a handler.
     */
    public function hookOn( object $handler ): void {
        $this->get( 'xwp.invoker' )->register_handler( $handler );
    }

    /**
     * Register a handler or a module.
     *
     * @template T of object
     *
     * @param T $instance Class instance to register as a handler.
     * @return Can_Handle<T>
     */
    public function register( object $instance ): Can_Handle {
        return $this->load_handler( $instance );
    }

    /**
     * Check the application's started state.
     *
     * @return bool
     */
    public function started(): bool {
        return $this->get( App::class )->started();
    }
}
