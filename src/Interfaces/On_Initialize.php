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
     */
    public function on_initialize(): void;
}
