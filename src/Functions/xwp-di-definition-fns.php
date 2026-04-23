<?php
/**
 * Helpers for defining DI entries related to WordPress features.
 *
 * @package eXtended WordPress
 * @subpackage DI
 */

namespace XWP\DI;

use XWP\DI\Definition\Helper\FilteredDefinitionHelper;
use XWP\DI\Definition\Helper\OptionDefinitionHelper;
use XWP\DI\Definition\Helper\TransientDefinitionHelper;

if ( ! \function_exists( 'XWP\DI\option' ) ) :

    /**
     * Helper for defining a WordPress option.
     *
     * @param  string $option_name   The name of the option to retrieve.
     * @param  mixed  $default_value The default value to return if the option does not exist.
     * @return OptionDefinitionHelper A definition that can be used in the container.
     */
    function option( string $option_name, mixed $default_value = false ): OptionDefinitionHelper {
        return new OptionDefinitionHelper( $option_name, $default_value );
    }

endif;

if ( ! \function_exists( 'XWP\DI\filtered' ) ) :
    /**
     * Helper for defining a value filtered through a WordPress filter.
     *
     * @param  mixed $hook    Hook name or a container entry that resolves to a hook name.
     * @param  mixed $value   Value to filter.
     * @param  mixed ...$args Arguments to pass to the filter.
     * @return FilteredDefinitionHelper
     */
    function filtered( mixed $hook, mixed $value = null, mixed ...$args ): FilteredDefinitionHelper {
        return new FilteredDefinitionHelper( $hook, $value, ...$args );
    }
endif;

if ( ! \function_exists( 'XWP\DI\transient' ) ) :

    /**
     * Helper for defining a WordPress transient.
     *
     * @param  string $transient_name The name of the transient to retrieve.
     * @param  mixed  $default_value  The default value to return if the transient does not exist.
     * @return TransientDefinitionHelper A definition that can be used in the container.
     */
    function transient( string $transient_name, mixed $default_value = false ): TransientDefinitionHelper {
        return new TransientDefinitionHelper( $transient_name, $default_value );
    }

endif;
