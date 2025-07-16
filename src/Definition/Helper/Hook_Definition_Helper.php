<?php //phpcs:disable WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase, WordPress.Security.EscapeOutput.ExceptionNotEscaped

namespace XWP\DI\Definition\Helper;

use Callback;
use DI\Definition\Exception\InvalidDefinition;
use DI\Definition\Helper\CreateDefinitionHelper;
use DI\Definition\ObjectDefinition;
use DI\Definition\ObjectDefinition\MethodInjection;
use DI\Definition\ObjectDefinition\PropertyInjection;
use XWP\DI\Definition\Hook_Definition;

/**
 * Helper class for creating hook definitions.
 *
 * @template TPvd of Callback
 */
class Hook_Definition_Helper extends CreateDefinitionHelper {
    /**
     * The class of the hook definition that will be created by this helper.
     *
     * @var class-string<Hook_Definition>
     */
    protected const DEFINITION_CLASS = Hook_Definition::class;

    /**
     * The class of the provider that will be used to create the hook definition.
     *
     * @var class-string<TPvd>
     */
    protected const PROVIDER_CLASS = Callback::class;

    /**
     * Meta information about the hook definition.
     *
     * @var object
     */
    protected object $meta_wrapper;

    protected string $meta_type;

    /**
     * Array of properties and their value.
     *
     * @var array<string,mixed>
     */
    protected array $properties = array();

    public function __construct( string|object $provider ) {
        parent::__construct( static::DEFINITION_CLASS );

        $this->provider( $provider );
    }

    public function provider( string|object $provider ): static {
        if ( \is_object( $provider ) ) {
            $this->meta_wrapper = $provider;

            $type = $provider->get_classname();
        }

        $this->meta_type = $type ?? $provider;

        return $this;
    }

    public function getDefinition( string $entryName ): ObjectDefinition {
        $class = $this::DEFINITION_CLASS;

        $definition = new $class( $entryName, $this->get_meta_type() );

        $this->inject_constructor( $definition );
        $this->inject_properties( $definition );
        $this->inject_methods( $definition );

        return $definition;
    }

    /**
     * Adds a constructor injection to the definition.
     *
     * @param ObjectDefinition $definition The object definition to inject the method into.
     */
    protected function inject_constructor( ObjectDefinition $definition ): void {
        if ( array() === $this->constructor ) {
            return;
        }

        $parameters           = $this->fixParameters( $definition, '__construct', $this->constructor );
        $constructorInjection = MethodInjection::constructor( $parameters );
        $definition->setConstructorInjection( $constructorInjection );
    }

    /**
     * Adds a property injection to the definition.
     *
     * @param ObjectDefinition $definition The object definition to inject the method into.
     */
    protected function inject_properties( ObjectDefinition $definition ): void {
        if ( array() === $this->properties ) {
            return;
        }

        foreach ( $this->properties as $property => $value ) {
            $definition->addPropertyInjection(
                new PropertyInjection( $property, $value ),
            );
        }
    }

    /**
     * Adds a method injection to the definition.
     *
     * @param ObjectDefinition $definition The object definition to inject the method into.
     */
    protected function inject_methods( ObjectDefinition $definition ): void {
        if ( array() === $this->methods ) {
            return;
        }

        foreach ( $this->methods as $method => $calls ) {
            foreach ( $calls as $parameters ) {
                $parameters      = $this->fixParameters( $definition, $method, $parameters );
                $methodInjection = new MethodInjection( $method, $parameters );
                $definition->addMethodInjection( $methodInjection );
            }
        }
    }

    /**
     * Fixes parameters indexed by the parameter name -> reindex by position.
     *
     * This is necessary so that merging definitions between sources is possible.
     *
     * @throws InvalidDefinition
     */
    protected function fixParameters( ObjectDefinition $definition, string $method, array $parameters ): array {
        $fixedParameters = array();

        foreach ( $parameters as $index => $parameter ) {
            // Parameter indexed by the parameter name, we reindex it with its position.
            if ( \is_string( $index ) ) {
                $callable = array( $definition->getClassName(), $method );

                try {
                    $reflectionParameter = new \ReflectionParameter( $callable, $index );
                } catch ( \ReflectionException $e ) {
                    throw InvalidDefinition::create(
                        $definition,
                        \sprintf(
                            "Parameter with name '%s' could not be found. %s.",
                            $index,
                            $e->getMessage(),
                        ),
                    );
                }

                $index = $reflectionParameter->getPosition();
            }

            $fixedParameters[ $index ] = $parameter;
        }

        return $fixedParameters;
    }

    protected function get_provider(): object {
        return $this->meta_wrapper;
    }

    protected function get_meta_type(): string {
        return $this->meta_type;
    }
}
