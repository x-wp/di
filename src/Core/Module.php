<?php //phpcs:disable Squiz.Commenting.FunctionComment.Missing
/**
 * Module decorator class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Core;

use XWP\DI\Container;
use XWP\DI\Interfaces\Can_Import;
use XWP\DI\Interfaces\Invokes_Module;

/**
 * Module decorator.
 *
 * @template T of object
 * @extends Handler<T>
 * @implements Can_Import<T>
 *
 * @since 1.0.0
 */
#[\Attribute( \Attribute::TARGET_CLASS )]
class Module extends Handler implements Can_Import, Invokes_Module {
    public function get_imports(): array {
        return ! $this->hydrated
            ? $this->hydrate( 'imports' )
            : $this->imports;
    }

    public function get_handlers(): array {
        return ! $this->hydrated
            ? $this->hydrate( 'handlers' )
            : $this->handlers;
    }
}
