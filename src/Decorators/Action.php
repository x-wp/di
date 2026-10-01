<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Action decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use XWP\DI\Interfaces\Can_Handle;

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
}
