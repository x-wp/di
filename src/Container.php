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
use DI\Proxy\ProxyFactory;
use Psr\Container\ContainerInterface;

/**
 * Custom WordPress container.
 */
class Container extends DI_Container {
    /**
     * Did we start the container.
     *
     * @var bool
     */
    protected bool $started = false;

    /**
     * Use `$container = new Container()` if you want a container with the default configuration.
     *
     * If you want to customize the container's behavior, you are discouraged to create and pass the
     * dependencies yourself, the ContainerBuilder class is here to help you instead.
     *
     * @see ContainerBuilder
     *
     * @param array<string,mixed>|MutableDefinitionSource $definitions      The container definitions.
     * @param ProxyFactory|null                           $proxyFactory     The proxy factory to use.
     * @param ContainerInterface                          $wrapperContainer If the container is wrapped by another container.
     */
    public function __construct(
        array|MutableDefinitionSource $definitions = array(),
        ?ProxyFactory $proxyFactory = null,
        ?ContainerInterface $wrapperContainer = null,
    ) {
        parent::__construct( $definitions, $proxyFactory, $wrapperContainer );

        $this->resolvedEntries[ self::class ]   = $this;
        $this->resolvedEntries[ static::class ] = $this;
        $this->resolvedEntries['xwp.invoker']   = $this->get( Invoker::class );
    }

    /**
     * Run the XWP application.
     *
     * @return static
     *
     * @throws \RuntimeException If the container is already started.
     */
    public function run(): static {
        if ( $this->started ) {
            throw new \RuntimeException( 'Container already started.' );
        }

        $this->call(
            array( 'xwp.invoker', 'load_module' ),
            array( 'module' => $this->get( 'xwp.app' ) ),
        );

        \do_action( "xwp_{$this->get('xwp.app.uuid')}_app_start" );

        $this->started = true;

        return $this;
    }

    /**
     * Register a handler or a module.
     *
     * @template T of object
     * @param T $handler Class instance to register as a handler.
     */
    public function register( object $handler ): void {
        $this->get( 'xwp.invoker' )->register_handler( $handler );
    }
}
