<?php
/**
 * App class file.
 *
 * @package Example
 */

namespace Example;

use XWP\DI\Decorators\Infuse;
use XWP\DI\Decorators\Module;
use XWP\DI\Interfaces\On_Initialize;

/**
 * Entry module for the application
 *
 * Check the `configure` method to see how to define the DI container.
 * It also implements the `On_Initialize` interface which exposes the `on_initialize` method.
 * This method will fire on module initialization (construction).
 *
 * We define the application module as extendable which means other plugins can hook into the container initialization.
 */
#[Module(
    container: 'example',
    hook: 'plugins_loaded',
    priority: 1,
    imports: array(
        Admin\Admin_Module::class,
        WC\WC_Module::class,
    ),
    handlers: array(),
    extendable: true,
)]
class App implements On_Initialize {
    /**
     * Get the DI container configuration.
     *
     * Static method used by modules to configure the DI container.
     * You can leverage everything that PHP-DI has to offer while defining your container.
     *
     * In this specific instance we do the following:
     *  - Define the `cfg.app` key with an array of values.
     *  - Define the `cfg.init` key with an array of values.
     *  - Map the `Config_Interface` to the `Config` class.
     *  - Map the `Utils\Installer` instatiation to the `instance` method of the `Utils\Installer` class.
     *
     * Each application module can provide its own DI configuration, which can be extended or overridden by other modules.
     * This is useful when you want to provide a base configuration that can be extended by other modules.
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array(
            'cfg.app'                          => \DI\value(
                array(
                    'var'  => 'value',
                    'var2' => 'value2',
                    'var3' => 'value3',
                    'var4' => array( 1, 2, 3, 4, 5, 6 ),
                ),
            ),
            'cfg.init'                         => \DI\value( array( 'my-app' => 'my-define' ) ),
            Interfaces\Config_Interface::class => \DI\autowire( Utils\Config::class ),
            Utils\Installer::class             => \DI\factory( array( Utils\Installer::class, 'instance' ) ),
        );
    }

    /**
     * Initialize the application.
     *
     * This method is unique because it uses the `Infuse` decorator which allows the container to inject dependencies.
     * Since the method signature has no arguments, they all need to be nullable.
     *
     * You can inject anything defined in the container - even the container itself.
     *
     * @param  Utils\Installer|null $inst The installer instance. Injected by the container.
     * @param  array|null           $init The initialization data. Injected by the container.
     */
    #[Infuse( Utils\Installer::class, 'cfg.init' )]
    public function on_initialize( ?Utils\Installer $inst = null, ?array $init = null ): void {
        $inst->install( ...$init );
    }
}
