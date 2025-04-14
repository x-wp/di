<?php //phpcs:disable Universal.CodeAnalysis.ConstructorDestructorReturn.ReturnTypeFound
/**
 * On_Initialize interface file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Interfaces;

/**
 * Interface describing handlers with an initialization method.
 *
 * @since 1.0.0
 */
interface On_Initialize {
    /**
     * Fired when the handler is initialized.
     *
     * This method can have an `Infuse` attribute to receive dependencies.
     * Function arguments must have default values in signature.
     *
     * Example:
     * ```php
     * use DI\Decorators\Inject;
     *
     * class My_Handler implements On_Initialize {
     *   #[Infuse( My_Dependency::class, 'definition.id' )]
     *   public function on_initialize( My_Dependency $inst = null, array $dep = array() ): void {
     *     $inst->do($dep);
     *   }
     * }
     * ```
     *
     * @see https://php-di.org/doc/attributes.html#inject
     */
    public function on_initialize(): void;
}
