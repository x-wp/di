<?php //phpcs:disable WordPress.NamingConventions.ValidVariableName, Squiz.PHP.CommentedOutCode.Found, Squiz.Commenting.InlineComment.InvalidEndChar
/**
 * Builder class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use DI\CompiledContainer as Compiled;
use DI\ContainerBuilder;
use DI\Definition\Source\DefinitionSource;
use DI\Definition\Source\NoAutowiring;
use DI\Definition\Source\ReflectionBasedAutowiring;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use XWP\DI\Definition\Source\Definition_App;
use XWP\DI\Hook\Compiler;
use XWP\DI\Hook\Factory;
use XWP\DI\Hook\Parser;

/**
 * Custom container builder.
 *
 * @extends ContainerBuilder<Container>
 * @method Container build()
 */
class App_Builder extends ContainerBuilder {
    /**
     * Directory to store the compiled hooks.
     *
     * @var string
     */
    protected ?string $cacheHooksDir = null;

    /**
     * Static method to configure the container.
     *
     * @param  array<string,mixed> $config Configuration options.
     * @return App_Builder
     */
    public static function configure( array $config = array() ): App_Builder {
        // \dump( $config );
        // die;
        return ( new App_Builder( Container::class ) )
            ->useAttributes( $config['use_attributes'] )
            ->useAutowiring( $config['use_autowiring'] )
            ->enableCompilation(
                compile: $config['cache_app'],
                directory: $config['cache_dir'],
                containerClass: $config['app_class'],
                // @phpstan-ignore argument.type
                containerParentClass: Compiled_Container::class,
            )
            ->enableDefinitionCache( enableCache: $config['cache_defs'], cacheNamespace: $config['app_id'] )
            ->enableHookCache( enableCache: $config['cache_hooks'], cacheDirectory: $config['cache_dir'] )
            ->writeProxiesToFile( writeToFile: $config['use_proxies'], proxyDirectory: $config['cache_dir'] )
            ->addBaseDefinition( $config )
            ->addLogDefinition( $config )
            ->addModuleDefinition( $config );
    }

    /**
     * Enable compilation.
     *
     * @template T of Compiled
     * @param  string          $directory        Directory to store the compiled container.
     * @param  string          $containerClass  Name of the compiled container class.
     * @param  class-string<T> $containerParentClass Parent class of the compiled container.
     * @param  bool            $compile         Should we compile the container.
     * @return static
     */
    public function enableCompilation(
        string $directory,
        string $containerClass = 'CompiledContainer',
        string $containerParentClass = Compiled::class,
        bool $compile = true,
    ): static {
        if ( ! $compile ) {
            return $this;
        }

        $this->ensureCacheDirExists( $directory );

        // @phpstan-ignore return.type
        return parent::enableCompilation( $directory, $containerClass, $containerParentClass );
    }

    /**
     * Enable definition cache.
     *
     * @param  string $cacheNamespace Namespace for the cache.
     * @param  bool   $enableCache    Should we cache the definitions.
     * @return static
     */
    public function enableDefinitionCache( string $cacheNamespace = '', bool $enableCache = false ): static {
        return $enableCache && ! \defined( 'WP_CLI' )
            ? parent::enableDefinitionCache( \rtrim( $cacheNamespace, '.' ) . '.' )
            : $this;
    }

    /**
     * Enable hook cache.
     *
     * @param  bool   $enableCache    Should we cache the hooks.
     * @param  string $cacheDirectory Directory to store the cached hooks.
     * @return static
     */
    public function enableHookCache( bool $enableCache, string $cacheDirectory ): static {
        $this->cacheHooksDir = $enableCache ? $cacheDirectory : null;

        if ( $enableCache ) {
            $this->ensureCacheDirExists( $cacheDirectory );
        }

        return $this;
    }

    /**
     * Add the base definition to the container.
     *
     * @param  array<string,mixed> $config Configuration options.
     * @return App_Builder
     */
    public function addBaseDefinition( array $config ): App_Builder {
        $definition = array(
            'app'        => \DI\get( \XWP_DI_TOKEN_PREFIX . "{$config['app_module']}" ),
            'app.cache'  => \DI\value(
                array(
                    'app'   => $config['cache_app'],
                    'defs'  => $config['cache_defs'],
                    'dir'   => $config['cache_dir'],
                    'hooks' => $config['cache_hooks'],
                    'ns'    => $config['app_id'],
                ),
            ),
            'app.debug'  => \DI\value( $config['app_debug'] ),
            'app.env'    => \DI\factory( 'wp_get_environment_type' ),
            'app.extend' => \DI\value( $config['extendable'] ),
            'app.id'     => \DI\value( $config['app_id'] ),
            // 'app.module' => \DI\value( $config['app_module'] ),
            'app.trace'  => \DI\value( $config['app_trace'] ),
            'app.type'   => \DI\value( $config['app_type'] ),
            'app.uuid'   => \DI\factory( 'wp_generate_uuid4' ),
            'app.ver'    => \DI\value( $config['app_version'] ),
        );

        if ( $config['app_file'] && 'plugin' === $config['app_type'] ) {
            $definition['app.file'] = \DI\value( $config['app_file'] );
            $definition['app.base'] = \DI\factory( 'plugin_basename', )
                ->parameter( 'file', \DI\get( 'app.file' ) );
            $definition['app.path'] = \DI\factory( 'plugin_dir_path' )
                ->parameter( 'file', \DI\get( 'app.file' ) );
            $definition['app.url']  = \DI\factory( 'plugin_dir_url' )
                ->parameter( 'file', \DI\get( 'app.file' ) );

        }

        return parent::addDefinitions( $definition );
    }

    /**
     * Add a log definition to the container.
     *
     * @param  array<string,mixed> $config Configuration options.
     * @return App_Builder
     */
    public function addLogDefinition( array $config ): App_Builder {
        $log_cfg = $config['logger'];
        $params  = array(
            'app_id'  => $log_cfg['app_id'],
            'basedir' => $log_cfg['basedir'],
            'level'   => $log_cfg['level'],
        );

        if ( ! $log_cfg['enabled'] ) {
            $log_cfg['handler'] = NullLogger::class;
            $params             = array();
        }

        $definition = array(
            'xwp.logger' => \DI\autowire( $log_cfg['handler'] )->constructor( ...$params ),
            'app.logger' => \DI\factory(
                static fn( $logger, string $ctx ) => \method_exists( $logger, 'with_context' )
                    ? $logger->with_context( $ctx )
                    : $logger,
            )
                ->parameter( 'logger', \DI\get( 'xwp.logger' ) ),

        );

        return $this->addDefinitions( $definition );
    }

    /**
     * Add a module definition to the container.
     *
     * @param array<string,mixed> $config Configuration options.
     * @return App_Builder
     */
    public function addModuleDefinition( array $config ): App_Builder {
        $autowiring = $config['use_autowiring'] ? new ReflectionBasedAutowiring() : new NoAutowiring();
        $definition = new Definition_App( $config['app_module'], $autowiring );

        return $this->addDefinitions( $definition );
    }

    /**
     * Are we caching the hook definitions?
     *
     * @return bool
     */
    public function isHookCacheEnabled(): bool {
        return (bool) $this->cacheHooksDir;
    }

    /**
     * Ensure the cache directory exists.
     *
     * @param  string $directory Directory to check.
     * @return void
     *
     * @throws \RuntimeException If the directory could not be created.
     */
    protected function ensureCacheDirExists( string $directory ): void {
        if ( ! \is_dir( $directory ) && ! \wp_mkdir_p( $directory ) ) {
            throw new \RuntimeException(
                \sprintf( 'Could not create cache directory: %s', \esc_html( $directory ) ),
            );
        }
    }
}
