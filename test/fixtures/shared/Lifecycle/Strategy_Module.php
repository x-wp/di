<?php
/**
 * Observable LAZY and JIT handler lifecycle fixtures.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DIT\Lifecycle;

use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;

#[Module( hook: 'xwp_strategy_module', handlers: array( Lazy_Handler::class, Jit_Handler::class, Empty_Lazy_Handler::class ) )]
final class Strategy_Module {
    public static array $events = array();
    public static bool $allow_initialization = true;
}

trait Records_Lifecycle {
    public function __construct() {
        Strategy_Module::$events[] = 'construct';
    }

    public static function can_initialize(): bool {
        Strategy_Module::$events[] = 'condition';
        return Strategy_Module::$allow_initialization;
    }

    public function on_initialize(): void {
        Strategy_Module::$events[] = 'initialize';
    }
}

#[Handler( tag: 'xwp_strategy_attach_lazy', strategy: Handler::INIT_LAZY )]
final class Lazy_Handler {
    use Records_Lifecycle;

    #[Filter( tag: 'xwp_strategy_value_lazy' )]
    public function value( string $value ): string {
        Strategy_Module::$events[] = 'invoke';
        return $value . ':handled';
    }
}

#[Handler( tag: 'xwp_strategy_attach_jit', strategy: Handler::INIT_JIT )]
final class Jit_Handler {
    use Records_Lifecycle;

    #[Filter( tag: 'xwp_strategy_value_jit' )]
    public function value( string $value ): string {
        Strategy_Module::$events[] = 'invoke';
        return $value . ':handled';
    }
}

#[Handler( tag: 'xwp_strategy_attach_empty', strategy: Handler::INIT_LAZY )]
final class Empty_Lazy_Handler {
    use Records_Lifecycle;
}
