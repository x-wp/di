<?php

namespace XWP\DI;

use DI\Definition\Helper\FactoryDefinitionHelper;
use DI\Definition\Reference;
use XWP\DI\Definition\Helper\Filterable_Definition_Helper;
use XWP\DI\Definition\Helper\Hook_Definition_Helper;
use XWP\DI\Definition\Hook_Reference;

if ( ! \function_exists( 'XWP\DI\hook' ) ) :
    /**
     * Create a hook definition.
     *
     * @param  string $metatype Class name of the hook.
     * @param  array  $construct Arguments to pass to the method.
     * @param  bool   $hydrate   Whether to hydrate the object.
     * @return Hook_Definition_Helper
     */
    function hook( string $metatype, array $construct, bool $hydrate ): Hook_Definition_Helper {
        return new Hook_Definition_Helper( $metatype, $construct, $hydrate );
    }
endif;


if ( ! \function_exists( 'XWP\DI\get' ) ) :
    function get( string $target ): Hook_Reference {
        return new Hook_Reference( $target );
    }
endif;

if ( ! \function_exists( 'XWP\DI\filter' ) ) :
    function filter( string $tag, mixed $initial = null, mixed ...$args ): Filterable_Definition_Helper {
        return ( new Filterable_Definition_Helper( $tag, $initial ) )
            ->args( $args );
    }
endif;
