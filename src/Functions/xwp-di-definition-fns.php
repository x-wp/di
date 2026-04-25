<?php
/**
 * Definition helper functions.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI;

use XWP\DI\Definition\Helper\ModuleDefinitionHelper;

if ( ! \function_exists( 'XWP\DI\module' ) ) :
    /**
     * Create a module definition helper.
     *
     * @param  class-string $module_class Module class name.
     * @return ModuleDefinitionHelper
     */
    function module( string $module_class ): ModuleDefinitionHelper {
        return new ModuleDefinitionHelper( $module_class );
    }
endif;
