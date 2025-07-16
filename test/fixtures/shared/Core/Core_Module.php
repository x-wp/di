<?php

namespace XWP\DIT\Core;

use XWP\DI\Attributes\Module;

#[Module( hook: 'init', priority: 1, handlers: array( Handlers\Body_Handler::class ) )]
class Core_Module {
    /**
     * Get the module definition.
     *
     * @return array<string,mixed>
     */
    public static function get_definition(): array {
        return array();
    }
}
