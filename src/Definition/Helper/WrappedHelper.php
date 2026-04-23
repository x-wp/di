<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
/**
 * OptionDefinitionHelper class file.
 *
 * @package eXtended WordPress
 * @subpackage DIw
 */

namespace XWP\DI\Definition\Helper;

use DI\Definition\Definition;
use DI\Definition\Helper\DefinitionHelper;
use DI\Definition\Helper\FactoryDefinitionHelper;
use DI\Definition\Reference;

/**
 * Helps defining a WordPress option.
 */
abstract class WrappedHelper implements DefinitionHelper {
    /**
     * Definition helper
     *
     * @var FactoryDefinitionHelper
     */
    protected FactoryDefinitionHelper $definition;

    /**
     * Constructor
     *
     * @param callable|array{0:mixed,1:string}|string $factory The factory to use for this definition.
     */
    public function __construct( callable|array|string $factory ) {
        $this->definition = \DI\factory( $factory );
    }

    public function getDefinition( string $entryName ): Definition {
        return $this->definition->getDefinition( $entryName );
    }

    protected function parameter( string $parameter, mixed $value ): static {
        $this->definition->parameter( $parameter, $value );

        return $this;
    }

    /**
     * Convert a string value to a container entry reference if it's a string, otherwise return it as is.
     *
     * @template T
     * @param  T $value  The value to convert.
     * @return ($value is string ? Reference : T) The converted value.
     */
    protected function string( mixed $value ): mixed {
        if ( \is_string( $value ) ) {
            $tag = \DI\value( $value );

            return \str_contains( $value, '{' )
                ? \DI\factory( 'xwp.app.tag' )->parameter( 'tag', $tag )
                : $tag;
        }

        return $value;
    }
}
