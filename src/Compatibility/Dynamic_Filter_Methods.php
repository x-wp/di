<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Dynamic_Filter legacy compatibility methods.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Compatibility;

use Closure;
use ReflectionMethod;
use Reflector;

/**
 * Preserves inherited wiring and override seams for existing decorators.
 *
 * @template T of object
 * @template H of \XWP\DI\Interfaces\Can_Handle<T>
 * @internal New runtime extensions belong in XWP\DI\Hook.
 */
trait Dynamic_Filter_Methods {
    /**
     * Variables to fetch.
     *
     * @var array<string,string>
     */
    protected array $vars;

    /**
     * Raw variables for substitution.
     *
     * @var string|array<string>|Closure():array<string>
     */
    protected Closure|string|array $raw_vars;

    /**
     * Extra variables to pass to the callback.
     *
     * @var array<string,string>
     */
    protected array $extra;

    /**
     * Set reflected method data.
     *
     * @internal Parser/runtime wiring detail. Attributes are immutable in v2.0.
     *
     * @param  ReflectionMethod $r Reflector instance.
     * @return static
     */
    public function with_reflector( Reflector $r ): static {
        $this->args ??= $r->getNumberOfParameters() - 1;

        return parent::with_reflector( $r );
    }

    /**
     * Load dynamic hooks into WordPress.
     *
     * @internal Runtime dispatch detail. Retained for legacy subclasses and typed views.
     *
     * @param  string|null $tag Hook tag.
     * @return bool
     */
    public function load_hook( ?string $tag = null ): bool {
        if ( $this->runtime ) {
            return $this->runtime->load();
        }

        $res = true;

        foreach ( $this->parse_vars( $this->raw_vars ) as $var => $param ) {
            $tag = $this->resolve_tag( $this->tag, array( $var ) );

            $this->extra[ $tag ] = $param;

            $res = $res && parent::load_hook( $tag );
        }

        return $res;
    }

    /**
     * Parse variables.
     *
     * @param  string|array<string>|callable():array<string> $vars Variables to mix into the tag.
     * @return array<string,string>
     */
    protected function parse_vars( string|callable|array $vars ): array {
        $parsed = array();

        foreach ( $this->process_vars( $vars ) as $key => $val ) {
            $key = \is_int( $key ) ? $val : $key;

            $parsed[ $key ] = $val;
        }

        return $parsed;
    }

    protected function get_cb_args( array $args ): array {
        $args = parent::get_cb_args( $args );

        $args[] = $this->extra[ $this->current() ];

        return $args;
    }

    /**
     * Process variables.
     *
     * @param  string|array<string>|callable():array<string> $vars Variables to mix into the tag.
     * @return array<int,string>|array<string,string>
     */
    private function process_vars( string|callable|array $vars ): array {
        if ( \is_callable( $vars ) ) {
            return $vars();
        }

        if ( \is_string( $vars ) && $this->container->has( $vars ) ) {
            return $this->container->get( $vars );
        }

        return $vars;
    }
}
