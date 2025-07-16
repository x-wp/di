<?php //phpcs:disable Squiz.Commenting

namespace XWP\DI\Definition\Source;

use DI\Definition\Definition;
use DI\Definition\Source\AttributeBasedAutowiring;
use DI\Definition\Source\DefinitionNormalizer;
use DI\Definition\Source\DefinitionSource;
use DI\Definition\Source\MutableDefinitionSource;
use XWP\DI\Core\Injector\Module_Loader;
use XWP\DI\Definition\Dependency_Scanner;

/**
 * Definition of an application from an entry module.
 */
class Definition_App implements DefinitionSource, MutableDefinitionSource {
    private readonly Dependency_Scanner $scanner;

    /**
     * Array of definitions indexed by entry name.
     *
     * @var array<string,Definition>
     */
    private array $definitions;

    private bool $loaded = false;

    /**
     * Normalizer for definitions.
     *
     * @var DefinitionNormalizer
     */
    private DefinitionNormalizer $normalizer;

    /**
     * Constructor.
     *
     * @param class-string        $module  Entry module class name.
     * @param ?Dependency_Scanner $scanner Optional. Dependency scanner instance to use for scanning dependencies.
     */
    public function __construct(
        private readonly string $module,
        ?Dependency_Scanner $scanner = null,
    ) {
        $this->scanner     = $scanner ?? new Dependency_Scanner();
        $this->normalizer  = new DefinitionNormalizer( new AttributeBasedAutowiring() );
        $this->definitions = array(
            'Veles\\Module\\*'        => \DI\factory( array( Module_Loader::class, 'load' ) ),
            Dependency_Scanner::class => \DI\value( $this->scanner ),
        );
    }

    public function addDefinitions( array $definitions ): void {
        if ( isset( $definitions[0] ) ) {
            throw new \Exception(
                'The PHP-DI definition is not indexed by an entry name in the definition array',
            );
        }

        /*
        The newly added data prevails
        For keys that exist in both arrays, the elements from the left-hand array will be used.
        */
        $this->definitions = $definitions + $this->definitions;
    }

    public function addDefinition( Definition $definition ): void {
        $this->definitions[ $definition->getName() ] = $definition;
    }

    public function getDefinition( string $name ): ?Definition {
        $this->load();

        if ( \array_key_exists( $name, $this->definitions ) ) {
            $definition = $this->definitions[ $name ];

            return $this->normalizer->normalizeRootDefinition( $definition, $name );
        }

        return \preg_match( '/^xwp\.([a-z]{1}):(.+)$/i', $name, $matches )
            ? $this->findDefinition( $matches[1], $matches[2] )
            : null;
    }

    public function getDefinitions(): array {
        $this->load( 'all' );

        return \array_merge(
            $this->scanner->get_definitions(),
        );
    }

    /**
     * Load the definitions if not already loaded.
     *
     * @param 'all'|'entry' $what What to load. Defaults to 'all'.
     */
    private function load( string $what = 'entry' ): void {
        if ( $this->loaded ) {
            return;
        }

        $this->addDefinitions( $this->scanner->scan( $this->module )->load( $what ) );
        // \dump( $this->definitions );
        // die;

        $this->loaded = true;
    }

    /**
     * Find a definition by type and name.
     *
     * @param  'm'|'h'|'c' $type Type of definition to find.
     * @param  string      $name Name of the definition to find.
     * @return ?Definition
     */
    private function findDefinition( string $type, string $name ): ?Definition {
        $data = $this->scanner->find( $type, $name );

        return $data && ! ( $data instanceof Definition )
            ? \DI\value( $data )
            : $data;
    }
}
