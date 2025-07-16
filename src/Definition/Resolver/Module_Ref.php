<?php // phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, Generic.CodeAnalysis.UselessOverridingMethod.Found

namespace XWP\DI\Definition\Resolver;

use DI\Definition\Definition;
use DI\Definition\Resolver\ResolverDispatcher;
use DI\Definition\SelfResolvingDefinition;
use DI\Proxy\ProxyFactory;
use Psr\Container\ContainerInterface;
use XWP\DI\Container;

class Module_Ref extends ResolverDispatcher {
    /**
     * Container instance
     *
     * @var ContainerInterface
     */
    protected ContainerInterface $container;

    /**
     * Constructor
     *
     * @param  ContainerInterface $container    Container instance.
     * @param  ProxyFactory       $proxyFactory Proxy factory instance.
     */
    public function __construct(
        ContainerInterface $container,
        ProxyFactory $proxyFactory,
    ) {
        parent::__construct( $container, $proxyFactory );
    }

    public function resolve( Definition $definition, array $parameters = array() ): mixed {
        // Special case, tested early for speed.
        if ( $definition instanceof SelfResolvingDefinition ) {
            return $definition->resolve( $this->container );
        }

        return parent::resolve( $definition, $parameters );
    }

    public function isResolvable( Definition $definition, array $parameters = array() ): bool {
        // Special case, tested early for speed.
        if ( $definition instanceof SelfResolvingDefinition ) {
            return $definition->isResolvable( $this->container );
        }

        return parent::isResolvable( $definition, $parameters );
    }
}
