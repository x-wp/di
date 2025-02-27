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
 * @method static bool      has( string $id)                                                              Check if a container exists.
 * @method static Container get( string $id )                                                             Get a container instance.
 * @method static Container create( array $config)                                                        Create a new container.
 * @method static void      extend( string $container, array $module, string $position, ?string $target ) Extend an application container definition.
 * @method static bool      decompile( string $id, bool $now )                                            Decompile a container.
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

        $config = $this->parse_config( $config );

        return $this->containers[ $config['id'] ] ??= App_Builder::configure( $config )
            ->addDefinitions( $config['module'] )
            ->addDefinitions( array( 'xwp.app.config' => $config ) )
            ->build();
    }

    /**
     * Extend an application container definition.
     *
     * @param  string              $container Container ID.
     * @param  array<class-string> $module    Module classname or array of module classnames.
     * @param  'before'|'after'    $position  Position to insert the module.
     * @param  string|null         $target    Target module to extend.
     */
    protected function call_extend( string $container, array $module, string $position = 'after', ?string $target = null ): void {
        \add_filter(
            "xwp_extend_import_{$container}",
            static function ( array $imports, string $classname ) use( $module, $position, $target ): array {
                if ( $target && $target !== $classname ) {
                    return $imports;
                }

                $params = 'after' === $position
                    ? array( $imports, $module )
                    : array( $module, $imports );

                return \array_merge( ...$params );
            },
            10,
            2,
        );
    }

    /**
     * Check if a container exists.
     *
     * @param  string $id Container ID.
     * @return bool
     */
    protected function call_has( string $id ): bool {
        return isset( $this->containers[ $id ] );
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

    /**
     * Decompile a container.
     *
     * @param  string $id  Container ID.
     * @param  bool   $now Decompile now or on shutdown.
     * @return bool
     */
    protected function call_decompile( string $id, bool $now = false ): bool {
        $config = $this->containers[ $id ]->get( 'xwp.app.config' );

        if ( ! $config['compile'] || ! \xwp_wpfs()->is_dir( $config['compile_dir'] ) ) {
            return false;
        }

        $cb = static fn() => \xwp_wpfs()->rmdir( $config['compile_dir'], true );

        // @phpstan-ignore return.void
        return ! $now ? \add_action( 'shutdown', $cb ) : $cb();
    }

    /**
     * Get the default configuration.
     *
     * @param  array<string, mixed> $config Configuration options.
     * @return array<string, mixed>
     */
    protected function parse_config( array $config ): array {
        return \wp_parse_args(
            $config,
            array(
                'attributes'    => true,
                'autowiring'    => true,
                'compile'       => 'production' === \wp_get_environment_type(),
                'compile_class' => 'CompiledContainer' . \strtoupper( $config['id'] ),
                'compile_dir'   => \WP_CONTENT_DIR . '/cache/xwp-di/' . $config['id'],
                'proxies'       => false,
            ),
        );
    }
}
