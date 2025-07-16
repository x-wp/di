<?php //phpcs:disable Squiz.Commenting.FunctionComment.MissingParamName, Squiz.Commenting.FunctionComment.MissingParamTag
/**
 * App_Factory class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use DI\Definition\Source\SourceCache;
use Psr\Log\LogLevel;
use XWP\DI\Core\App_Config;
use XWP\DI\Definition\Source\Definition_App;
use XWP\DI\Interfaces\Extension_Module;
use XWP\Helper\Traits\Singleton;

/**
 * Create and manage DI containers.
 *
 * @method static bool      has( string $id)                                                 Check if a container exists.
 * @method static Container get( string $id )                                                Get a container instance.
 * @method static void      uninstall()                                                      Uninstall the container.
 */
final class App_Factory {
    use Singleton;

    /**
     * Array of containers that need to be decompiled.
     *
     * @var array<string,bool>
     */
    private static array $decompiled = array();

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
     * Array of files to register hooks for.
     *
     * @var array<string,string>
     */
    private array $files = array();

    /**
     * Array of application IDs which should be debugged.
     *
     * @var array<string>
     */
    private array $app_debug;

    /**
     * Create a new application container.
     *
     * @template T of object
     *
     * @param  class-string<T> $module  Entry (root) application module class.
     * @param  array{
     *   id: string,
     *   file?: string,
     *   version?: string,
     *   compile?: bool,
     *   cache?: bool
     * }                       $options Application options.
     * @return App
     */
    public static function create( string $module, array $options = array() ): App {
        $config  = new App_Config( ...$options );
        $builder = ( new App_Builder( $config ) )->initialize( $module );
        // $container = App_Builder::create( $config )
            // ->addDefinitions( new Definition_App( $module ) )
            // ->build();

        return new App(
            $builder->addDefinitions( new Definition_App( $module ) )->build(),
            $config,
            $module,
        );

        // return new App( $container, $config, $module );
    }

    /**
     * Add a container to the decompile list.
     *
     * @param  string $id  Container ID.
     * @param  bool   $now Decompile now or on shutdown.
     */
    public static function decompile( string $id, bool $now = false ): void {
        self::$decompiled[ $id ] = $now;
    }

    /**
     * Clear the cache directory.
     *
     * @param  array<string,mixed> $config Configuration.
     * @return bool
     */
    public static function clear( array $config ): bool {
        $fs = \xwp_wpfs();

        if ( ! $fs->is_dir( $config['dir'] ) ) {
            \xwp_log( "Cache directory {$config['dir']} does not exist" );
            return false;
        }

        return $fs->rmdir( $config['dir'], true );
    }

    /**
     * Constructor.
     */
    protected function __construct() {
        $this->app_debug = \defined( 'XWP_DI_DEBUG_APP' )
            ? \xwp_str_to_arr( XWP_DI_DEBUG_APP )
            : array();
        /**
         * Fired when the app factory is initialized.
         *
         * @param App_Factory $factory App factory instance.
         * @since 2.0.0
         */
        \do_action( 'xwp_di_init', $this );
    }

    /**
     * Destructor.
     *
     * Decompile any containers that need to be decompiled.
     */
    public function __destruct() {
        foreach ( self::$decompiled as $id => $now ) {
            $this->call_decompile( $id, $now );
        }
    }

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
    public function make( array $config ): Container {
        $id = $config['app_id'] ?? $config['id'] ?? throw new \InvalidArgumentException( 'Missing app_id' );

        if ( isset( $this->apps[ $id ] ) ) {
            throw new \InvalidArgumentException( \esc_html( "Container {$id} already exists" ) );
        }

        $config = $this->parse_app_config( $config );

        $this->public[ $id ] = $config['public'];

        return $this->apps[ $id ] ??= App_Builder::configure( $config )->build();
    }

    /**
     * Extend an application container definition.
     *
     * @template TMod of Extension_Module
     * @param  array{
     *   id: string,
     *   module: class-string<TMod>,
     *   file?: string,
     *   type?: 'plugin'|'theme',
     *   version?: string,
     * }                     $ext        Application configuration.
     * @param  string $app        Target application ID.
     */
    public function extend( array $ext, string $app ): void {
        $ext = $this->parse_ext_config( $ext );

        if ( ! $this->is_uninstalling( $ext['file'] ) ) {
            \add_filter(
                "xwp_extend_import_{$app}",
                static function ( array $addons ) use ( $ext ): array {
                    $addons[] = $ext;

                    return $addons;
                },
                10,
                1,
            );
        }

        if ( ! $ext['file'] ) {
            return;
        }

        $this->files[ $ext['file'] ] = $app;
    }

    /**
     * Check if the file is a plugin file.
     *
     * @param  string $file File path.
     * @return 'plugin'|'theme'
     */
    protected function parse_type( string $file ): string {
        return \doing_action( 'plugins_loaded' ) || \str_contains( $file, \WP_PLUGIN_DIR )
            ? 'plugin'
            : 'theme';
    }

    /**
     * Are we uninstalling the plugin?
     *
     * @param  string|bool $file File path.
     * @return bool
     */
    protected function is_uninstalling( string|bool $file ): bool {
        return \defined( 'WP_UNINSTALL_PLUGIN' ) && WP_UNINSTALL_PLUGIN === $file;
    }

    /**
     * Can we debug this container?
     *
     * @param  string|null $app_id Application ID.
     * @return bool
     */
    protected function can_debug( ?string $app_id = null ): bool {
        return ! $this->app_debug || \in_array( $app_id, $this->app_debug, true );
    }

    /**
     * Is this a production environment?
     *
     * @return bool
     */
    protected function is_prod(): bool {
        return 'production' === \wp_get_environment_type();
    }

    /**
     * Uninstall the container.
     */
    private function call_uninstall(): void {
        $app = \defined( 'WP_UNINSTALL_PLUGIN' )
            ? $this->files[ WP_UNINSTALL_PLUGIN ] ?? false
            : false;

        if ( ! $app ) {
            return;
        }

        $this->decompile( $app, true );
    }

    /**
     * Check if a container exists.
     *
     * @param  string $id Container ID.
     * @return bool
     */
    private function call_has( string $id ): bool {
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
    private function call_get( string $id ): Container {
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
    private function call_decompile( string $id, bool $now = false ): bool {
        $config = $this->apps[ $id ]->get( 'app.cache' );

        if ( ! $config['app'] && ! $config['hooks'] ) {
            return false;
        }

        if ( $config['defs'] && SourceCache::isSupported() ) {
            \xwp_log( "Clearing definitions cache for {$config['ns']}" );
            \apcu_delete( new \APCUIterator( "/^php-di\.definitions\.{$config['ns']}\..+/" ) );
        }

        return ! $now
            ? \add_action(
                'shutdown',
                static function () use ( $config ) {
                    self::clear( $config );
                },
            )
            : self::clear( $config );
    }

    /**
     * Parse the extension configuration.
     *
     * @param  array<string,mixed> $config Configuration options.
     * @return array<string,mixed>
     */
    private function parse_ext_config( array $config ): array {
        return \wp_parse_args(
            $config,
            array(
                'file'    => false,
                'type'    => $this->parse_type( (string) ( $config['file'] ?? null ) ),
                'version' => '0.0.0-dev',
            ),
        );
    }

    /**
     * Get the default configuration.
     *
     * @param  array<string,mixed> $config Configuration options.
     * @return array<string,mixed>
     */
    private function parse_app_config( array $config ): array {
        $config = $this->parse_legacy_config( $config );
        return \xwp_parse_args(
            $config,
            array(
                'app_class'      => 'CompiledContainer' . \strtoupper( $config['app_id'] ),
                'app_debug'      => $this->can_debug( $config['app_id'] ),
                'app_file'       => false,
                'app_preload'    => false,
                'app_type'       => $this->parse_type( (string) ( $config['app_file'] ?? null ) ),
                'app_version'    => '0.0.0-dev',
                'cache_app'      => $this->is_prod(),
                'cache_defs'     => $this->is_prod() && SourceCache::isSupported(),
                'cache_dir'      => \WP_CONTENT_DIR . '/cache/xwp-di/' . $config['app_id'],
                'cache_hooks'    => $this->is_prod(),
                'extendable'     => true,
                'logger'         => false,
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
    private function parse_legacy_config( $config ): array {
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

        if ( $this->can_debug( $config['app_id'] ) ) {
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
