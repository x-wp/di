<?php
/**
 * Hook decorator compatibility base.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Decorators;

use ReflectionClass;
use ReflectionMethod;

/**
 * Retains the public decorator hierarchy while isolating legacy wiring.
 *
 * @template THndlr of object
 * @template TRflct of ReflectionClass<THndlr>|ReflectionMethod
 * @extends \XWP\DI\Compatibility\Hook<THndlr,TRflct>
 * @internal
 */
abstract class Hook extends \XWP\DI\Compatibility\Hook {
}
