<?php
/**
 * WC_Module class file.
 *
 * @package Example
 */

namespace Example\WC;

use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;
use XWP\DI\Interfaces\Can_Initialize;

/**
 * WooCommerce module.
 *
 * This is a module which is conditionally initialized.
 */
#[Module(
    tag:'plugins_loaded',
    priority: 10,
    handlers: array(
        Handlers\Account_EP_Handler::class,
        Handlers\Product_Page_Handler::class,
    ),
)]
class WC_Module implements Can_Initialize {
    /**
     * We can initialize this module if WooCommerce is active.
     *
     * @return bool
     */
    public static function can_initialize(): bool {
        return \class_exists( 'WooCommerce' );
    }

    /**
     * Module DI Configuration
     *
     * Each module can provide its own DI configuration.
     * Since it leverages PHP-DI, you can use all of its features - such as overriding, extending, etc.
     *
     * @return array<string,mixed>
     */
    public static function configure(): array {
        return array(
            'cfg.wc.acct'   => \DI\Value(
                array(
                    'menu-1' => 'Account 1',
                    'menu-2' => 'Account 2',
                    'menu-3' => 'Account 3',
                ),
            ),
            'cfg.wc.eps'    => \DI\value(
                array(
                    'ep-1' => 'endpoint-1',
                    'ep-2' => 'endpoint-2',
                    'ep-3' => 'endpoint-3',
                ),
            ),
            'cfg.wc.filter' => \DI\value( 'all' ),
        );
    }
}
