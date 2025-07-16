<?php

use XWP\DI\App_Factory;
use XWP\DIT\App_Module;

function xwp_di_plugin_bs(): void {
    $app = App_Factory::create(
        App_Module::class,
        array(
            'compile' => false,
            'file'    => \XWPDIT_FILE,
            'id'      => 'xwpdi',
        ),
    )->run();
}
