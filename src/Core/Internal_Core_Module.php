<?php

namespace XWP\DI\Core;

use XWP\DI\Attributes\Module;

#[Module()]
class Internal_Core_Module {
    /**
     * Get the module definition.
     *
     * @return array<string,mixed>
     */
    public static function get_definition(): array {
        return array(
            'xwp.runner'           => \DI\get( Services\Runner::class ),
            Services\Runner::class => \DI\autowire(),
        );
    }
}
