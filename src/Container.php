<?php //phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
/**
 * Container class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use DI\Container as DI_Container;
use DI\Definition\Resolver\DefinitionResolver;
use DI\Definition\Source\MutableDefinitionSource;
use DI\Proxy\ProxyFactory;
use Psr\Container\ContainerInterface;
use Psr\Log\LoggerInterface;
use XWP\DI\Definition\Resolver\Resolver_Dispatcher;
use XWP\DI\Hook\Factory;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;

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
    }

    /**
     * Magic method to call invoker methods.
     *
     * @param  string             $name Method name.
     * @param  array<mixed,mixed> $args Method arguments.
     * @return mixed
     */
    public function __call( string $name, array $args ): mixed {
        if ( \in_array( $name, self::INV_METHODS, true ) ) {
            return $this->resolvedEntries['xwp.invoker']->$name( ...$args );
        }

        return null;
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

        $this->started = true;

        $this->get( Invoker::class )->register( $this->get( 'app.module' ) );

        \do_action( "xwp_{$this->get('app.uuid')}_app_start" );

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
     * Is the container started.
     *
     * @return bool
     */
    public function started(): bool {
        return $this->started;
    }

    /**
     * Get a logger instance.
     *
     * @param  string $context Logger context.
     * @return LoggerInterface
     */
    public function logger( string $context ): LoggerInterface {
        return $this->make( 'app.logger', array( 'ctx' => $context ) );
    }
}
