<?php
/**
 * Handlers that inherit their scheduling hook and priority from registration.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DIT\Lifecycle;

use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;

#[Module( hook: 'xwp_default_module', handlers: array( Default_Auto_Handler::class, Default_Early_Handler::class, Default_Lazy_Handler::class, Default_Jit_Handler::class ) )]
final class Default_Schedule_Module {
    public static array $events = array();
}

trait Default_Schedule_Lifecycle {
    public function __construct() {
        Default_Schedule_Module::$events[] = 'construct';
    }

    public function on_initialize(): void {
        Default_Schedule_Module::$events[] = 'initialize';
    }

    #[Filter( 'xwp_default_value' )]
    public function value( string $value ): string {
        Default_Schedule_Module::$events[] = 'invoke';
        return $value . ':handled';
    }
}

#[Handler]
final class Default_Auto_Handler {
    use Default_Schedule_Lifecycle;
}

#[Handler( strategy: Handler::INIT_EARLY )]
final class Default_Early_Handler {
    use Default_Schedule_Lifecycle;
}

#[Handler( strategy: Handler::INIT_LAZY )]
final class Default_Lazy_Handler {
    use Default_Schedule_Lifecycle;
}

#[Handler( strategy: Handler::INIT_JIT )]
final class Default_Jit_Handler {
    use Default_Schedule_Lifecycle;
}
