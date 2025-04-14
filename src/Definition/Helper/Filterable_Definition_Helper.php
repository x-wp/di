<?php //phpcs:disable WordPress.NamingConventions

namespace XWP\DI\Definition\Helper;

use Closure;
use DI\Definition\FactoryDefinition;
use DI\Definition\Helper\DefinitionHelper;

/**
 * Helper to create an apply_filters definition.
 */
class Filterable_Definition_Helper implements DefinitionHelper {
    /**
     * Additional arguments to pass to the filter.
     *
     * @var array<int,mixed>
     */
    protected array $args = array();

    /**
     * Cast callable.
     *
     * @var null|Closure|string
     */
    protected null|Closure|string $cast = null;

    /**
     * Constructor.
     *
     * @param string $hook Hook name.
     * @param mixed  $value Initial value.
     */
    public function __construct( protected string $hook, protected mixed $value = null ) {
    }

    /**
     * Set the initial value for the filter.
     *
     * @param  mixed $value Initial value.
     * @return self
     */
    public function value( mixed $value ): self {
        $this->$value = $value;

        return $this;
    }

    /**
     * Set the single argument for the filter.
     *
     * @param  int   $index Index of the argument.
     * @param  mixed $value Value of the argument.
     * @return self
     */
    public function arg( int $index, mixed $value ): self {
        $this->args[ $index ] = $value;

        return $this;
    }

    /**
     * Set the arguments for the filter.
     *
     * @param  array<int,mixed> $args Arguments to pass to the filter.
     * @return self
     */
    public function args( array $args ): self {
        $this->args = \array_values( $args );

        return $this;
    }

    /**
     * Set the cast callable.
     *
     * @param  null|callable|string $cast Cast callable.
     * @return self
     */
    public function cast( null|callable|string $cast ): self {
        $this->cast = $cast;

        return $this;
    }

    /**
     * Get the definition for the filter.
     *
     * @param  string $entryName Name of the entry.
     * @return FactoryDefinition
     */
    public function getDefinition( string $entryName ): FactoryDefinition {
        return new FactoryDefinition( $entryName, $this->factory(), $this->params() );
    }

    /**
     * Get the factory callable.
     *
     * @return callable
     */
    protected function factory(): callable {
        if ( ! isset( $this->cast ) ) {
            return 'apply_filters';
        }

        return static function ( string $hook_name, mixed $value, array $args, callable $cast ) {
            $value = \apply_filters( $hook_name, $value, ...$args );

            return $cast( $value );
        };
    }

    /**
     * Get the parameters for the filter.
     *
     * @return array<string,mixed>
     */
    protected function params(): array {
        $params = array(
            'args'      => $this->args,
            'hook_name' => $this->hook,
            'value'     => $this->value,
        );

        if ( null !== $this->cast ) {
            $params['cast'] = $this->cast;
        }

        return $params;
    }
}
