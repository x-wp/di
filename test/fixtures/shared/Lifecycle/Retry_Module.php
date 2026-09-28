<?php
/**
 * Observable initialization rejection, retry, and re-entry fixtures.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DIT\Lifecycle;

use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;

#[Module( hook: 'xwp_retry_module', handlers: array( Early_Retry_Handler::class, Now_Retry_Handler::class, Lazy_Retry_Handler::class, Jit_Retry_Handler::class ) )]
final class Retry_Module {
    public static array $events = array();
    public static bool $ready = false;
    public static ?string $throw_at = null;
}

trait Retry_Lifecycle {
    public static function record( string $event ): void {
        Retry_Module::$events[] = $event;
        if ( Retry_Module::$throw_at === $event ) {
            throw new \RuntimeException( 'Initialization failed at ' . $event );
        }
    }

    public function __construct() {
        self::record( 'construct' );
    }

    public static function can_initialize(): bool {
        self::record( 'condition' );
        do_action( 'xwp_retry_condition', static::class );
        return Retry_Module::$ready;
    }

    public function on_initialize(): void {
        self::record( 'initialize' );
        do_action( 'xwp_retry_initialize', static::class );
    }

    #[Filter( 'xwp_retry_value' )]
    public function value( string $value ): string {
        self::record( 'invoke' );
        return $value . ':handled';
    }
}

#[Handler( tag: 'xwp_retry_attach', context: Handler::CTX_FRONTEND, strategy: Handler::INIT_EARLY )]
final class Early_Retry_Handler {
    use Retry_Lifecycle;
}

#[Handler( tag: 'xwp_retry_attach', context: Handler::CTX_FRONTEND, strategy: Handler::INIT_NOW )]
final class Now_Retry_Handler {
    use Retry_Lifecycle;
}

#[Handler( tag: 'xwp_retry_attach', context: Handler::CTX_FRONTEND, strategy: Handler::INIT_LAZY )]
final class Lazy_Retry_Handler {
    use Retry_Lifecycle;
}

#[Handler( tag: 'xwp_retry_attach', context: Handler::CTX_FRONTEND, strategy: Handler::INIT_JIT )]
final class Jit_Retry_Handler {
    use Retry_Lifecycle;
}
