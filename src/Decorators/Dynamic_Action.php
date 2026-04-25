<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Dynamic_Action class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

/**
 * Dynamic action decorator
 *
 * @template T of object
 * @template H of Ajax_Handler<T>
 * @extends Dynamic_Filter<T,H>
 */
#[\Attribute( \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE )]
class Dynamic_Action extends Dynamic_Filter {
    protected function get_type(): string {
        return 'action';
    }

    /**
     * Invoke the dynamic action callback.
     *
     * @internal Runtime dispatch detail. Dispatcher replaces this in v2.0.
     *
     * @param  mixed ...$args Hook arguments.
     * @return mixed
     */
    public function invoke( mixed ...$args ): mixed {
        parent::invoke( ...$args );

        return null;
    }
}
