<?php
/**
 * App class file.
 *
 * @package ExamplePro
 */

namespace ExamplePro;

use Example\Interfaces\Config_Interface;
use XWP\DI\Decorators\Module;

/**
 * Pro version of the Example plugin.
 */
#[Module( container: 'example', hook: 'plugins_loaded', priority: 9 )]
class App {
    /**
     * Get the DI container configuration.
     *
     * We remap the interface to implementation for the `Config` class.
     * You can redefine, extend, or override any configuration from the parent plugin.
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array(
            Config_Interface::class => \DI\autowire( Utils\Config_Pro::class ),
        );
    }
}
