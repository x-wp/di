<?php
/**
 * Infuse decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

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
     * Export injection metadata without a bound handler.
     *
     * @internal Discovery metadata detail.
     * @param string $handler_token Handler token.
     * @return array<string>
     */
    public function get_tokens( string $handler_token ): array {
        return \array_map(
            static fn( string $param ): string => '!self.handler' === $param ? $handler_token : $param,
            $this->params,
        );
    }
}
