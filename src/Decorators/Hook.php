<?php
/**
 * Hook class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use Closure;
use DI\Container;
use ReflectionClass;
use ReflectionMethod;
use XWP\DI\Interfaces\Can_Hook;
use XWP_Context;

/**
 * Base hook from which the action and filter decorators inherit.
 *
 * @template THndlr of object
 * @template TRflct of ReflectionClass<THndlr>|ReflectionMethod
 *
 * @implements Can_Hook<THndlr, TRflct>
 */
abstract class Hook implements Can_Hook {
    /**
     * The name of the action to which the function is hooked.
     *
     * @var string
     */
    protected string $tag;

    /**
     * Priority when hook was invoked.
     *
     * @var null|Closure|string|int|array{class-string,string}
     */
    protected array|int|string|Closure|null $prio;

    /**
     * Is the handler initialized?
     *
     * @var bool
     */
    protected bool $loaded = false;

    /**
     * Reflector instance.
     *
     * @var TRflct
     */
    protected ReflectionClass|ReflectionMethod $reflector;

    /**
     * Constructor.
     *
     * @param string|null                                        $tag         Hook tag.
     * @param null|Closure|string|int|array{class-string,string} $priority    Hook priority.
     * @param int                                                $context     Hook context.
     * @param null|Closure|string|array{class-string,string}     $conditional Conditional callback.
     * @param array<int,string>|string|false                     $modifiers   Values to replace in the tag name.
     */
    public function __construct(
        ?string $tag,
        array|int|string|Closure|null $priority = null,
        protected int $context = self::CTX_GLOBAL,
        protected array|string|Closure|null $conditional = null,
        string|array|bool $modifiers = false,
    ) {
        $this->prio = $priority;
        $this->tag  = $this->define_tag( $tag ?? '', $modifiers );
    }

    /**
     * Getter for protected properties.
     *
     * @param  string $name Property name.
     * @return mixed
     */
    public function __get( string $name ): mixed {
        return \method_exists( $this, "get_{$name}" )
            ? $this->{"get_{$name}"}()
            : $this->$name ?? null;
    }

    /**
     * Set the reflector instance.
     *
     * @param  TRflct $r Reflector instance.
     * @return static
     */
    public function with_reflector( \Reflector $r ): static {
        $this->reflector ??= $r;

        return $this;
    }

    /**
     * Check if the hook can be fired.
     *
     * @return bool
     */
    public function can_load(): bool {
        return $this->check_context() && $this->check_method( $this->conditional );
    }

    /**
     * Check if the context is valid.
     *
     * @return bool
     */
    public function check_context(): bool {
        return XWP_Context::validate( $this->context );
    }

    /**
     * Calls a method if it exists and is callable.
     *
     * @param  array{0:class-string|object,1:string}|string|\Closure|null $method Method to call.
     * @return bool
     */
    protected function check_method( array|string|\Closure|null $method ): bool {
        return ! $this->can_call( $method ) || $this->container->call( $method );
    }

    /**
     * Check if the method is callable.
     *
     * @param  array{0:class-string|object,1:string}|string|\Closure|null $method Method to call.
     * @return bool
     */
    protected function can_call( array|string|\Closure|null $method ): bool {
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
    protected function define_tag( ?string $tag, array|string|bool $modifiers ): string {
        if ( ! $modifiers || ! $tag ) {
            return $tag;
        }

        $modifiers = \is_array( $modifiers )
            ? $modifiers
            : array( $modifiers );

        return \vsprintf( $tag, $modifiers );
    }

    /**
     * Parse the real priority.
     *
     * @return int
     */
    protected function get_priority(): int {
        $prio = $this->prio;
        $prio = match ( true ) {
            \is_numeric( $prio )  => \intval( $prio ),
            \defined( $prio )     => \constant( $prio ),
            \is_array( $prio )    => $this->call_priority( $prio ),
            \is_callable( $prio ) => $this->call_priority( $prio ),
            \is_string( $prio )   => $this->filter_priority( $prio ),
            default               => 10,
        };

        return $prio;
    }

    /**
     * Filter the priority.
     *
     * @param  string $prio Filter name.
     * @return int
     */
    protected function filter_priority( string $prio ): int {
        $expl = \explode( ':', $prio, 2 );

        return \apply_filters( $expl[0], $expl[1] ?? 10, $this->tag );
    }

    /**
     * Get the hook priority by calling the priority callback.
     *
     * @param string|array{class-string,string} $args Priority callback.
     */
    protected function call_priority( array|string $args ): int {
        return $this->container->call( $args, array( $this->tag ) );
    }

    /**
     * Get the container instance.
     *
     * @return Container
     */
    abstract protected function get_container(): Container;

    /**
     * Get the hook ID.
     *
     * @return string
     */
    abstract protected function get_id(): string;
}
