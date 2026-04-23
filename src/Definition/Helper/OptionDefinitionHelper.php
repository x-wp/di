<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing, WordPress.NamingConventions.ValidVariableName.VariableNotSnakeCase
/**
 * OptionDefinitionHelper class file.
 *
 * @package eXtended WordPress
 * @subpackage DIw
 */

namespace XWP\DI\Definition\Helper;

/**
 * Helps defining a WordPress option.
 */
class OptionDefinitionHelper extends WrappedHelper {
    /**
     * Constructor
     *
     * @param string $option_name   Option name to retrieve from the database.
     * @param mixed  $default_value Value to return if the option does not exist.
     * @return void
     */
    public function __construct( string $option_name, mixed $default_value = false ) {
        parent::__construct( \get_option( ... ) );

        $this
            ->parameter( 'option', $this->string( $option_name ) )
            ->parameter( 'default_value', $default_value );
    }

    /**
     * Set the default value to return if the option does not exist.
     *
     * @param  mixed $default_value The default value to return if the option does not exist.
     * @return self
     */
    public function default( mixed $default_value ): self {
        return $this->parameter( 'default_value', $default_value );
    }
}
