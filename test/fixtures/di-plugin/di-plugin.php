<?php
/**
 * Plugin Name: DI Test Plugin
 * Description: A plugin to test the DI container.
 * Version:     1.0.0
 * Author:      Oblak Solutions
 *
 * @package eXtended WordPress
 * @subpackage Test
 */

use XWP\DI\App_Factory;
use XWP\DIT\App_Module;

define( 'XWPDIT_FILE', __FILE__ );

require_once getenv( 'XWPDI_PATH' ) . '/vendor/autoload.php';
require_once __DIR__ . '/bootstrap-fn.php';

xwp_bootstrap_app(
    bootstrap_fn: static function (): void {
        $app = App_Factory::create(
            App_Module::class,
            array(
                'compile' => false,
                'cache'   => false,
                'file'    => \XWPDIT_FILE,
                'id'      => 'xwpdi',
            ),
        )->enable_hooks( 'activation' )->run();
    },
);

return;

xwp_load_app(
    array(
        'app_id'      => 'xwpdi',
        'app_module'  => \XWP\DIT\App_Module::class,
        'app_preload' => false,
        'cache_app'   => false,
        'cache_defs'  => false,
        'cache_hooks' => false,
    ),
);
