<?php
/**
 * App_Factory class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use DI\Container;
use XWP\Helper\Traits\Singleton;

/**
 * Create and manage DI containers.
 *
 * @method static Container create(array $config)     Create a new container.
 * @method static Container get(string $container_id) Get a container instance.
 */
final class App_Factory {
    use Singleton;

    /**
     * Array of container instances.
     *
     * @var array<string, Container>
     */
    private array $containers = array();

    /**
     * Call a static method on the instance.
     *
     * @param  string              $name Method name.
     * @param  array<string,mixed> $args Method arguments.
     * @return mixed
     */
    public static function __callStatic( string $name, array $args = array() ): mixed {
        return \method_exists( self::class, "call_{$name}" )
            ? self::instance()->{"call_{$name}"}( ...$args )
            : null;
    }

    /**
     * Create a new container.
     *
     * @param  array<string,mixed> $config Configuration.
     * @return Container
     */
    protected function call_create( array $config ): Container {
        if ( isset( $this->containers[ $config['id'] ] ) ) {
            return $this->containers[ $config['id'] ];
        }

        return $this->containers[ $config['id'] ] ??= Builder::configure( $config )
            ->addDefinitions( $config['module'] )
            ->build();
    }

    /**
     * Get a container instance.
     *
     * @param  string $id Container ID.
     * @return Container
     */
    protected function call_get( string $id ): Container {
        return $this->containers[ $id ];
    }
}
