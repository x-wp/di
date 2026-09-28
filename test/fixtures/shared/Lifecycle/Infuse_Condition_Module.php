<?php
/**
 * Explicit and typed injection into initialization conditions.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DIT\Lifecycle;

use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Infuse;
use XWP\DI\Decorators\Module;
use XWP\DI\Interfaces\Can_Handle;

#[Module( hook: 'xwp_infuse_module', handlers: array( Auto_Infuse_Handler::class, Early_Infuse_Handler::class, Now_Infuse_Handler::class, Lazy_Infuse_Handler::class, Jit_Infuse_Handler::class, Typed_Condition_Handler::class ) )]
final class Infuse_Condition_Module {
    public static array $events = array();
    public static array $seen = array();
    public static string $request = '';

    public static function configure(): array {
        return array( 'infuse.cfg' => \DI\factory( array( self::class, 'config' ) ) );
    }

    public static function config(): array {
        self::$events[] = 'resolve config';
        return array( 'enabled' => false, 'request' => self::$request );
    }
}

final class Condition_Service {
    public function __construct() {
        Infuse_Condition_Module::$events[] = 'resolve service';
    }
}

trait Infuse_Condition_Lifecycle {
    public function __construct() {
        Infuse_Condition_Module::$events[] = 'construct';
    }

    public function on_initialize(): void {
        Infuse_Condition_Module::$events[] = 'initialize';
    }

    #[Filter( 'xwp_infuse_value' )]
    public function value( string $value ): string {
        Infuse_Condition_Module::$events[] = 'invoke';
        return $value . ':handled';
    }
}

trait Infused_Condition {
    #[Infuse( 'infuse.cfg', '!self.handler' )]
    public static function can_initialize( array $cfg, Can_Handle $handler, Condition_Service $service ): bool {
        Infuse_Condition_Module::$events[] = 'condition';
        Infuse_Condition_Module::$seen[] = array( $cfg, $handler, $service );
        return $cfg['enabled'];
    }
}

#[Handler( tag: 'xwp_infuse_attach', context: Handler::CTX_ADMIN, strategy: Handler::INIT_AUTO )]
final class Auto_Infuse_Handler {
    use Infuse_Condition_Lifecycle, Infused_Condition;
}

#[Handler( tag: 'xwp_infuse_attach', context: Handler::CTX_ADMIN, strategy: Handler::INIT_EARLY )]
final class Early_Infuse_Handler {
    use Infuse_Condition_Lifecycle, Infused_Condition;
}

#[Handler( tag: 'xwp_infuse_attach', context: Handler::CTX_ADMIN, strategy: Handler::INIT_NOW )]
final class Now_Infuse_Handler {
    use Infuse_Condition_Lifecycle, Infused_Condition;
}

#[Handler( tag: 'xwp_infuse_attach', context: Handler::CTX_ADMIN, strategy: Handler::INIT_LAZY )]
final class Lazy_Infuse_Handler {
    use Infuse_Condition_Lifecycle, Infused_Condition;
}

#[Handler( tag: 'xwp_infuse_attach', context: Handler::CTX_ADMIN, strategy: Handler::INIT_JIT )]
final class Jit_Infuse_Handler {
    use Infuse_Condition_Lifecycle, Infused_Condition;
}

#[Handler( strategy: Handler::INIT_NOW )]
final class Typed_Condition_Handler {
    use Infuse_Condition_Lifecycle;

    public static function can_initialize( Condition_Service $service ): bool {
        Infuse_Condition_Module::$events[] = 'condition';
        Infuse_Condition_Module::$seen[] = $service;
        return true;
    }
}
