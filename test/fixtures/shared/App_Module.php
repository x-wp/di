<?php

namespace XWP\DIT;

use XWP\DI\Decorators\Module;
use XWP\DIT\Core\Core_Module;

#[Module(
    hook: 'init',
    imports: array(
        Core\Core_Module::class,
    // array( Core\Core_Module::class . '::for_feature' )
    ),
)]
class App_Module {
    /**
     * Get the module definition.
     *
     * @return array<string,mixed>
     */
    public static function get_definition(): array {
        return array();
    }
}
