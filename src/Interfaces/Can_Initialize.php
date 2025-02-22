<?php //phpcs:disable Universal.CodeAnalysis.ConstructorDestructorReturn.ReturnTypeFound

namespace XWP\DI\Interfaces;

/**
 * Interface describing handlers that are conditionally initialized.
 */
interface Can_Initialize {
    /**
     * Can we initialize this handler?
     *
     * You can pass any arguments that are resolvable by the container.
     * Caveat is that they must have default values in the method signature.
     *
     * @return bool
     */
    public static function can_initialize(): bool;
}
