<?php
/**
 * Infuse decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use XWP\DI\Interfaces\Can_Handle;

/**
 * Infuse decorator.
 */
#[\Attribute( \Attribute::TARGET_METHOD )]
class Infuse {
    /**
     * The parameters to inject.
     *
     * @var array<string>
     */
    protected array $params;

    /**
     * Constructor.
     *
     * @param string|array<string> ...$params The parameters to inject.
     */
    public function __construct( string|array ...$params, ) {
        $this->params = \is_array( $params[0] ) ? $params[0] : $params;
    }

    /**
     * Resolve the parameters.
     *
     * @template T of object
     * @param  Can_Handle<T> $h The handler.
     * @return array<mixed>
     */
    public function resolve( Can_Handle $h ) {
        $params  = \array_diff( $this->params, array( '!self.handler' ) );
        $hook_it = $params !== $this->params;

        $params = \array_map( '\DI\get', $params );

        if ( $hook_it ) {
            $params[] = \DI\value( $h );
        }

        return $params;
    }
}
