<?php

namespace XWP\DI;

use XWP\DI\Definition\Helper\Hook_Definition_Helper;
use XWP\DI\Definition\Helper\Module_Definition_Helper;

if ( ! \function_exists( '\XWP\DI\hook' ) ) :
    function hook( string|object $hook ): Hook_Definition_Helper {
        return new Hook_Definition_Helper( $hook );
    }

endif;

if ( ! \function_exists( '\XWP\DI\module' ) ) :
    /**
     * Helper for definig a module
     *
     * @param  string|object $module
     * @return \Module_Definition_Helper
     */
    function module( string|object $module ): Module_Definition_Helper {
        return new Module_Definition_Helper( $module );
    }

endif;
