<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Dynamic_Action class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use XWP\DI\Interfaces\Can_Handle;

/**
 * Dynamic action decorator
 *
 * @template T of object
 * @template H of Can_Handle<T>
 * @extends Dynamic_Filter<T,H>
 */
#[\Attribute( \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE )]
class Dynamic_Action extends Dynamic_Filter {
}
