<?php
/**
 * Callback eligibility and initialization timing fixtures.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DIT\Lifecycle;

use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;

#[Module( hook: 'xwp_gates_module', handlers: array( Auto_Gates_Handler::class, Early_Gates_Handler::class, Now_Gates_Handler::class, Lazy_Gates_Handler::class, Jit_Gates_Handler::class ) )]
final class Callback_Gates_Module {
    public static bool $allowed = true;
    public static int $checks = 0;
    public static array $events = array();

    public static function eligible(): bool {
        ++self::$checks;
        return self::$allowed;
    }
}

trait Gated_Callbacks {
    public function __construct() {
        Callback_Gates_Module::$events[] = 'construct';
    }

    public function on_initialize(): void {
        Callback_Gates_Module::$events[] = 'initialize';
    }

    #[Filter( 'xwp_gates_standard', context: Filter::CTX_ADMIN, conditional: array( Callback_Gates_Module::class, 'eligible' ) )]
    public function standard_filter( string $value ): string {
        Callback_Gates_Module::$events[] = 'standard filter';
        return $value . ':standard';
    }

    #[Filter( 'xwp_gates_proxied', context: Filter::CTX_ADMIN, conditional: array( Callback_Gates_Module::class, 'eligible' ), invoke: Filter::INV_PROXIED )]
    public function proxied_filter( string $value ): string {
        Callback_Gates_Module::$events[] = 'proxied filter';
        return $value . ':proxied';
    }

    #[Action( 'xwp_gates_standard_action', context: Action::CTX_ADMIN, conditional: array( Callback_Gates_Module::class, 'eligible' ) )]
    public function standard_action(): void {
        Callback_Gates_Module::$events[] = 'standard action';
    }

    #[Action( 'xwp_gates_proxied_action', context: Action::CTX_ADMIN, conditional: array( Callback_Gates_Module::class, 'eligible' ), invoke: Action::INV_PROXIED )]
    public function proxied_action(): void {
        Callback_Gates_Module::$events[] = 'proxied action';
    }
}

#[Handler( tag: 'xwp_gates_attach', strategy: Handler::INIT_AUTO )]
final class Auto_Gates_Handler { use Gated_Callbacks; }

#[Handler( tag: 'xwp_gates_attach', strategy: Handler::INIT_EARLY )]
final class Early_Gates_Handler { use Gated_Callbacks; }

#[Handler( tag: 'xwp_gates_attach', strategy: Handler::INIT_NOW )]
final class Now_Gates_Handler { use Gated_Callbacks; }

#[Handler( tag: 'xwp_gates_attach', strategy: Handler::INIT_LAZY )]
final class Lazy_Gates_Handler { use Gated_Callbacks; }

#[Handler( tag: 'xwp_gates_attach', strategy: Handler::INIT_JIT )]
final class Jit_Gates_Handler { use Gated_Callbacks; }
