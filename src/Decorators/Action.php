<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Action decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;

/**
 * Action hook decorator.
 *
 * @template T of object
 * @template H of Can_Handle<T>
 * @extends Filter<T,H>
 *
 * @since 1.0.0
 */
#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Action extends Filter {
    protected function get_type(): string {
        return 'action';
    }

    /**
     * Invoke the action callback.
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
