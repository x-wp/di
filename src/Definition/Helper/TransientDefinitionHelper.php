<?php
/**
 * TransientDefinitionHelper class file.
 *
 * @package eXtended WordPress
 * @subpackage DI
 */

namespace XWP\DI\Definition\Helper;

/**
 * Helps defining a value read from a WordPress transient.
 *
 * Note: `get_transient()` returns `false` for both "not set" and "stored
 * value was literally false". When the transient is absent the provided
 * default is returned — if you store `false` intentionally, it will be
 * indistinguishable from a miss.
 */
class TransientDefinitionHelper extends WrappedHelper {
    /**
     * Constructor
     *
     * @param string $transient_name Transient name to read.
     * @param mixed  $default_value  Value to return if the transient is missing.
     */
    public function __construct( string $transient_name, mixed $default_value = false ) {
        parent::__construct(
            static function ( string $transient, mixed $default ): mixed {
                $value = \get_transient( $transient );

                return false !== $value ? $value : $default;
            },
        );

        $this
            ->parameter( 'transient', $this->string( $transient_name ) )
            ->parameter( 'default', $default_value );
    }

    /**
     * Set the default value to return if the transient is missing.
     *
     * @param  mixed $default_value The default value.
     * @return self
     */
    public function default( mixed $default_value ): self {
        return $this->parameter( 'default', $default_value );
    }
}
