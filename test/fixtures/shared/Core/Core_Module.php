<?php

namespace XWP\DIT\Core;

use XWP\DI\Decorators\Module;

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

    public static function for_feature(): array {
        return array(
            'imports' => array(),
            'module'  => self::class,
        );
    }
}
