<?php
/**
 * Hook_Invoke_Methods trait file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Traits;

use Closure;
use Reflector;
use XWP\DI\Container;
use XWP\DI\Interfaces\Can_Hook;

/**
 * Shared methods needed for hook invocation.
 *
 * @template TTgt of object
 *
 * @phpstan-require-implements Can_Hook<TTgt,Reflector>
 */
trait Hook_Invoke_Methods {
    /**
     * Get the container instance.
     *
     * @return ?Container
     */
    abstract public function get_container(): ?Container;

    /**
     * Calls a method if it exists and is callable.
     *
     * @param  null|Closure|string|array{0: class-string,1: string} $method Method to call.
     * @return bool
     */
    protected function check_method( null|Closure|string|array $method ): bool {
        return ! $this->can_call( $method ) || $this->get_container()->call( $method );
    }

    /**
     * Check if the method is callable.
     *
     * @param  null|Closure|string|array{0: class-string,1: string} $method Method to call.
     * @return bool
     */
    protected function can_call( null|Closure|string|array $method ): bool {
        if ( ! \is_array( $method ) ) {
            return \is_callable( $method );
        }

        return \method_exists( $method[0], $method[1] );
    }

    /**
     * If the tag is dynamic (contains %s), replace the placeholders with the provided arguments.
     *
     * @param  ?string                        $tag       Tag to set.
     * @param  array<int,string>|string|false $modifiers Values to replace in the tag name.
     * @return string
     */
    protected function resolve_tag( ?string $tag, array|string|bool $modifiers ): string {
        if ( ! $modifiers || ! $tag ) {
            return $tag;
        }

        $modifiers = \is_array( $modifiers )
            ? $modifiers
            : array( $modifiers );

        return \vsprintf( $tag, $modifiers );
    }

    /**
     * Resolve the hook priority.
     *
     * @param  null|Closure|string|int|array{0: class-string,1: string} $prio Priority.
     * @return int
     */
    protected function resolve_priority( null|Closure|string|int|array $prio ): int {
        return match ( true ) {
            \is_numeric( $prio )  => \intval( $prio ),
            \defined( $prio )     => \constant( $prio ),
            \is_array( $prio )    => $this->call_priority( $prio ),
            \is_callable( $prio ) => $this->call_priority( $prio ),
            \is_string( $prio )   => $this->filter_priority( $prio ),
            default               => 10,
        };
    }

    /**
     * Filter the priority.
     *
     * @param  string $prio Filter name.
     * @return int
     */
    private function filter_priority( string $prio ): int {
        $expl = \explode( ':', $prio, 2 );

        return \apply_filters( $expl[0], $expl[1] ?? 10, $this->tag );
    }

    /**
     * Get the hook priority by calling the priority callback.
     *
     * @param string|array{class-string,string} $args Priority callback.
     */
    private function call_priority( array|string $args ): int {
        return $this->get_container()->call( $args, array( $this->tag ) );
    }
}
