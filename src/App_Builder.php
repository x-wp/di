<?php //phpcs:disable WordPress.NamingConventions.ValidVariableName
/**
 * Builder class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use DI\CompiledContainer;
use DI\Container;
use DI\Definition\Source\DefinitionSource;

/**
 * Custom container builder.
 *
 * @extends \DI\ContainerBuilder<Container>
 */
class App_Builder extends \DI\ContainerBuilder {
    /**
     * Static method to configure the container.
     *
     * @param  array<string, mixed> $config Configuration options.
     * @return App_Builder
     */
    public static function configure( array $config = array() ): App_Builder {
        $config = static::getDefaultConfig( $config );

        return ( new App_Builder() )
            ->useAttributes( $config['attributes'] )
            ->useAutowiring( $config['autowiring'] )
            ->writeProxiesToFile( writeToFile: $config['proxies'], proxyDirectory: $config['compile_dir'] )
            ->enableCompilation(
                compile: $config['compile'],
                directory: $config['compile_dir'],
                containerClass: $config['compile_class'],
            );
    }

    /**
     * Get the default configuration.
     *
     * @param  array<string, mixed> $config Configuration options.
     * @return array<string, mixed>
     */
    protected static function getDefaultConfig( array $config ): array {
        return \wp_parse_args(
            $config,
            array(
                'attributes'    => true,
                'autowiring'    => true,
                'compile'       => 'production' === \wp_get_environment_type(),
                'compile_class' => 'CompiledContainer' . \strtoupper( $config['id'] ),
                'compile_dir'   => __DIR__ . '/cache',
                'proxies'       => false,
            ),
        );
    }

    //phpcs:ignore Squiz.Commenting.FunctionComment.Missing
    public function enableCompilation(
        string $directory,
        string $containerClass = 'CompiledContainer',
        string $containerParentClass = CompiledContainer::class,
        bool $compile = true,
    ): static {
        // @phpstan-ignore return.type
        return $compile
            ? parent::enableCompilation( $directory, $containerClass, $containerParentClass )
            : $this;
    }

    /**
     * Add definitions to the container.
     *
     * @param class-string|string|array<string,mixed>|DefinitionSource ...$definitions Can be an array of definitions, the
     *                                                                   name of a file containing definitions
     *                                                                   or a DefinitionSource object.
     * @return $this
     */
    public function addDefinitions( string|array|DefinitionSource ...$definitions ): static {
        return \class_exists( $definitions[0] )
            ? parent::addDefinitions( \xwp_register_module( $definitions[0] )->get_definitions() )
            : parent::addDefinitions( ...$definitions );
    }
}
