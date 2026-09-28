<?php
/**
 * Context-gated handlers with observable discovery and initialization.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DIT\Lifecycle;

use XWP\DI\App;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;

#[Module( hook: 'xwp_context_module', handlers: array( Auto_Context_Handler::class, Early_Context_Handler::class, Now_Context_Handler::class, Lazy_Context_Handler::class, Jit_Context_Handler::class ) )]
final class Context_Module {
    public static array $events = array();
    public static int $discoveries = 0;
}

#[\Attribute( \Attribute::TARGET_METHOD )]
final class Observed_Filter extends Filter {
    public function __construct( string $tag, mixed ...$args ) {
        ++Context_Module::$discoveries;
        parent::__construct( $tag, ...$args );
    }
}

trait Context_Lifecycle {
    public function __construct() {
        Context_Module::$events[ static::class ][] = 'construct';
    }

    public static function can_initialize(): bool {
        Context_Module::$events[ static::class ][] = 'condition';
        return true;
    }

    public function on_initialize(): void {
        Context_Module::$events[ static::class ][] = 'initialize';
    }

    #[Observed_Filter( 'xwp_context_value' )]
    public function value( string $value ): string {
        Context_Module::$events[ static::class ][] = 'invoke';
        return $value . ':handled';
    }
}

#[Handler( tag: 'xwp_context_auto', context: Handler::CTX_ADMIN, strategy: Handler::INIT_AUTO )]
final class Auto_Context_Handler {
    use Context_Lifecycle;
}

#[Handler( tag: 'xwp_context_early', context: Handler::CTX_ADMIN, strategy: Handler::INIT_EARLY )]
final class Early_Context_Handler {
    use Context_Lifecycle;
}

#[Handler( tag: 'xwp_context_now', context: Handler::CTX_ADMIN, strategy: Handler::INIT_NOW )]
final class Now_Context_Handler {
    use Context_Lifecycle;
}

#[Handler( tag: 'xwp_context_lazy', context: Handler::CTX_ADMIN, strategy: Handler::INIT_LAZY )]
final class Lazy_Context_Handler {
    use Context_Lifecycle;
}

#[Handler( tag: 'xwp_context_jit', context: Handler::CTX_ADMIN, strategy: Handler::INIT_JIT )]
final class Jit_Context_Handler {
    use Context_Lifecycle;
}

#[Handler( context: Handler::CTX_ADMIN, strategy: Handler::INIT_USER )]
final class User_Context_Handler {
    use Context_Lifecycle;
}

#[Handler( strategy: Handler::INIT_NOW )]
final class Reentrant_Handler {
    public function on_initialize( App $app ): void {
        $app->register_handler( self::class );
    }

    #[Filter( 'xwp_context_reentrant' )]
    public function value( string $value ): string {
        return $value . ':reentrant';
    }
}
