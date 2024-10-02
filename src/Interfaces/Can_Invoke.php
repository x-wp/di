<?php //phpcs:disable Universal.CodeAnalysis.ConstructorDestructorReturn.ReturnTypeFound
/**
 * Can_Invoke interface file.
 *
 * @package WP Utils
 * @subpackage Abstracts
 */

namespace XWP\DI\Interfaces;

/**
 * Defines decorators that can invoke WordPress hooks.
 *
 * @template THndlr of object
 * @extends Can_Hook<THndlr,\ReflectionMethod>
 *
 * @property-read bool $firing Is the hook firing?
 * @property-read int $fired   Number of times the hook has fired.

 * @property-read array{THndlr,string} $target The target method.
 */
interface Can_Invoke extends Can_Hook {
    /**
     * Standard invocation.
     */
    public const INV_STANDARD = 1; // 00001

    /**
     * Proxied invocation (Via container).
     */
    public const INV_PROXIED = 2; // 00010

    /**
     * Invoke only once.
     */
    public const INV_ONCE = 4; // 00100
    /**
     * Prevent looped invocation.
     */
    public const INV_LOOPED = 8; // 01000

    /**
     * Invoke safely (Protect against fatal errors).
     */
    public const INV_SAFELY = 16; // 10000

    /**
     * Set the handler instance.
     *
     * @template Thndlr of object
     * @param  Can_Handle<Thndlr> $handler Handler instance.
     * @return static
     */
    public function with_handler( Can_Handle $handler ): static;

    /**
     * Set the target method.
     *
     * @param  string $method Method name.
     * @return static
     */
    public function with_target( string $method ): static;
}
