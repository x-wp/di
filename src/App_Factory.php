<?php
/**
 * App_Factory class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use DI\Definition\Source\SourceCache;
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
     * @var array<string,Container>
     */
    private array $apps = array();

    /**
     * Array of public container IDs.
     *
     * @var array<string,bool>
     */
    private array $public = array();

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
     *
     * @throws \InvalidArgumentException If the app_id is missing.
     * @throws \InvalidArgumentException If the container already exists.
     */
    protected function call_create( array $config ): Container {
        $id = $config['app_id'] ?? $config['id'] ?? throw new \InvalidArgumentException( 'Missing app_id' );

        if ( isset( $this->apps[ $id ] ) ) {
            throw new \InvalidArgumentException( \esc_html( "Container {$id} already exists" ) );
        }

        $config = $this->parse_config( $config );

        $this->public[ $id ] = $config['public'];

        return $this->apps[ $id ] ??= App_Builder::configure( $config )->build();
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
            static function ( array $imports, string $classname ) use ( $module, $position, $target ): array {
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
        return isset( $this->apps[ $id ] );
    }

    /**
     * Get a container instance.
     *
     * @param  string $id Container ID.
     * @return Container
     *
     * @throws \InvalidArgumentException If the container does not exist.
     * @throws \InvalidArgumentException If the container is not public.
     */
    protected function call_get( string $id ): Container {
        if ( ! isset( $this->apps[ $id ] ) ) {
            throw new \InvalidArgumentException( \esc_html( "Container {$id} does not exist" ) );
        }

        if ( ! $this->public[ $id ] ) {
            throw new \InvalidArgumentException( \esc_html( "Container {$id} cannot be accessed externally" ) );
        }
        return $this->apps[ $id ];
    }

    /**
     * Decompile a container.
     *
     * @param  string $id  Container ID.
     * @param  bool   $now Decompile now or on shutdown.
     * @return bool
     */
    protected function call_decompile( string $id, bool $now = false ): bool {
        $config = $this->apps[ $id ]->get( 'xwp.app.config' );

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
     * @param  array<string,mixed> $config Configuration options.
     * @return array<string,mixed>
     */
    protected function parse_config( array $config ): array {
        $is_prod = 'production' === \wp_get_environment_type();
        $apcu_on = SourceCache::isSupported();
        $config  = $this->parse_legacy_config( $config );

        return \xwp_parse_args(
            $config,
            array(
                'app_class'      => 'CompiledContainer' . \strtoupper( $config['app_id'] ),
                'app_file'       => false,
                'app_type'       => 'plugin',
                'app_version'    => '0.0.0-dev',
                'cache_app'      => $is_prod,
                'cache_defs'     => $is_prod && $apcu_on,
                'cache_dir'      => \WP_CONTENT_DIR . '/cache/xwp-di/' . $config['app_id'],
                'cache_hooks'    => $is_prod,
                'public'         => true,
                'use_attributes' => true,
                'use_autowiring' => true,
                'use_proxies'    => false,
            ),
        );
    }

    /**
     * Parse legacy configuration options.
     *
     * @param  array<string,mixed> $config Configuration options.
     * @return array<string,mixed>
     *
     * @throws \InvalidArgumentException If the app_module is missing.
     */
    protected function parse_legacy_config( $config ): array {
        $legacy = array(
            'attributes'    => 'use_attributes',
            'autowiring'    => 'use_autowiring',
            'compile'       => 'cache_app',
            'compile_class' => 'app_class',
            'compile_dir'   => 'cache_dir',
            'id'            => 'app_id',
            'module'        => 'app_module',
            'proxies'       => 'use_proxies',
        );
        $legacy = \xwp_array_slice_assoc( $legacy, ...\array_keys( $config ) );

        // @phpstan-ignore identical.alwaysFalse
        if ( 0 === \count( $legacy ) ) {
            return $config;
        }

        foreach ( $legacy as $old => $new ) {
            $config[ $new ] = $config[ $old ];
            unset( $config[ $old ] );
        }

        if ( ! isset( $config['app_module'] ) ) {
            throw new \InvalidArgumentException( 'Missing app_module' );
        }

        if ( 'production' !== \wp_get_environment_type() && ! \defined( 'XWP-DI_HIDE_ERRORS' ) ) {
            \_doing_it_wrong(
                'xwp_create_app',
                \sprintf(
                    'Container %s initialized with deprecated options: %s. Use %s instead.',
                    \esc_html( $config['app_id'] ),
                    \esc_html( \implode( ', ', \array_keys( $legacy ) ) ),
                    \esc_html( \implode( ', ', $legacy ) ),
                ),
                '2.0.0',
            );
        }

        return $config;
    }
}
