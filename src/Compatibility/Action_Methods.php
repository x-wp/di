<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Action return compatibility adapter.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Compatibility;

/**
 * Preserves action return behavior for custom subclasses and typed views.
 *
 * @internal
 */
trait Action_Methods {
    /**
     * Invoke the action callback.
     *
     * @internal Runtime dispatch detail. Retained for legacy subclasses and typed views.
     *
     * @param  mixed ...$args Hook arguments.
     * @return mixed
     */
    public function invoke( mixed ...$args ): mixed {
        parent::invoke( ...$args );

        return null;
    }

    protected function get_type(): string {
        return 'action';
    }
}
