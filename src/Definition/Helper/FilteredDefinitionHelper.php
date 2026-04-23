<?php
/**
 * FilteredDefinitionHelper class file.
 *
 * @package eXtended WordPress
 * @subpackage DI
 */

namespace XWP\DI\Definition\Helper;

/**
 * Helper for defining a value filtered through a WordPress filter.
 */
class FilteredDefinitionHelper extends WrappedHelper {
    /**
     * Arguments for the hook
     *
     * @var array<int, mixed>
     */
    private array $args = array();

    /**
     * Constructor
     *
     * @param mixed $hook    Hook name or a container entry that resolves to a hook name.
     * @param mixed $value   Value to filter.
     * @param mixed ...$args Arguments to pass to the filter.
     */
    public function __construct( mixed $hook = '', mixed $value = null, mixed ...$args ) {
        parent::__construct(
            static fn( $hook, $value, $args ) =>
                \apply_filters( $hook, $value, ...$args ),
        );

        $this
            ->hook( $hook )
            ->value( $value )
            ->args( $args );
    }

    /**
     * Set the hook to apply.
     *
     * @param  mixed $hook The hook name or a container entry that resolves to a hook name.
     * @return self
     */
    public function hook( mixed $hook ): self {
        return $this->parameter( 'hook', $this->string( $hook ) );
    }

    /**
     * Set the value to filter.
     *
     * @param  mixed $value The value to filter.
     * @return self
     */
    public function value( mixed $value ): self {
        return $this->parameter( 'value', $value );
    }

    /**
     * Set the arguments to pass to the filter.
     *
     * @param  array<int,mixed> $args The arguments to pass to the filter.
     * @return self
     */
    public function args( array $args ): self {
        $this->args = \array_values( $args );

        return $this->parameter( 'args', $this->args );
    }

    /**
     * Add an argument to the arguments passed to the filter.
     *
     * @param  mixed $arg   The argument value.
     * @param  ?int  $index The argument position (0-based). If null, the argument will be added to the end of the arguments list.
     * @return self
     */
    public function arg( mixed $arg, ?int $index = null ): self {
        $index ??= \count( $this->args );

        $this->args[ $index ] = $arg;

        return $this->args( $this->args );
    }
}
