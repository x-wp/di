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
    public function __construct( string|array ...$params ) {
        $this->params = $params && \is_array( $params[0] ) ? $params[0] : $params;
    }

    /**
     * Get parameter tokens for a handler.
     *
     * @internal Runtime parameter-resolution detail.
     *
     * @template T of object
     * @param  Can_Handle<T> $h The handler.
     * @return array<string>
     */
    public function get( Can_Handle $h ) {
        return \in_array( '!self.handler', $this->params, true )
            ? $this->get_tokens( $h->get_token() )
            : $this->params;
    }

    /**
     * Export injection metadata without a bound handler.
     *
     * @internal Discovery metadata detail.
     * @param string $handler_token Handler token.
     * @return array<string>
     */
    public function get_tokens( string $handler_token ): array {
        $params  = \array_diff( $this->params, array( '!self.handler' ) );
        $hook_it = $params !== $this->params;

        if ( $hook_it ) {
            $params[] = $handler_token;
        }

        return $params;
    }

    /**
     * Resolve parameters for a handler.
     *
     * @internal Runtime parameter-resolution detail.
     *
     * @template T of object
     * @param  Can_Handle<T> $h The handler.
     * @return array<mixed>
     */
    public function resolve( Can_Handle $h ) {
        return \array_map( '\DI\get', $this->get( $h ) );
    }
}
