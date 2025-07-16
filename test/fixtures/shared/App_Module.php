<?php

namespace XWP\DIT;

use XWP\DI\Attributes\Module;

#[Module( imports: array( Core\Core_Module::class ) )]
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
