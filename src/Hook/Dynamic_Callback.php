<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Dynamic callback runtime.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

/**
 * Registers expanded tags and supplies the value associated with the active tag.
 *
 * @template T of object
 * @template H of \XWP\DI\Interfaces\Can_Handle<T>
 * @extends Callback<T,H>
 * @internal
 */
class Dynamic_Callback extends Callback {
    /**
     * Extra arguments indexed by expanded tag.
     *
     * @var array<string,string>
     */
    protected array $extra = array();

    public function get_num_args(): int {
        return $this->args ??= $this->get_reflector()->getNumberOfParameters() - 1;
    }

    /**
     * Load dynamic hooks into WordPress.
     *
     * @internal Runtime dispatch detail. Dispatcher replaces this in v2.0.
     *
     * @param  string|null $tag Hook tag.
     * @return bool
     */
    public function load_hook( ?string $tag = null ): bool {
        $res = true;

        foreach ( $this->parse_vars( $this->definition->get_options()['vars'] ) as $var => $param ) {
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
