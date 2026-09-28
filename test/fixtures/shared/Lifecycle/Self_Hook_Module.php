<?php
/**
 * Observable self.hook identity and live invocation state.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DIT\Lifecycle;

use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;

#[Module( hook: 'xwp_self_module', handlers: array( Self_Hook_Handler::class ) )]
final class Self_Hook_Module {
    public static array $observations = array();
    public static ?\Closure $observe = null;

    public static function record( Filter $hook ): void {
        self::$observations[] = array(
            'hook' => $hook,
            'tag' => $hook->tag,
            'method' => $hook->method,
            'fired' => $hook->fired,
            'firing' => $hook->firing,
            'target' => $hook->target,
        );
        if ( self::$observe ) {
            ( self::$observe )( $hook );
        }
    }
}

#[Handler( strategy: Handler::INIT_NOW )]
final class Self_Hook_Handler {
    #[Filter( 'xwp_self_{%s}', priority: 17, modifiers: array( '!value:filter' ), invoke: Filter::INV_PROXIED, args: 1, params: array( '!self.hook' ) )]
    public function filter_value( string $value, Filter $hook ): string {
        Self_Hook_Module::record( $hook );
        return $value . ':filtered';
    }

    #[Action( 'xwp_self_action_first', priority: 19, invoke: Action::INV_PROXIED, args: 0, params: array( '!self.hook' ) )]
    #[Action( 'xwp_self_action_second', priority: 23, invoke: Action::INV_PROXIED, args: 0, params: array( '!self.hook' ) )]
    public function action( Action $hook ): void {
        Self_Hook_Module::record( $hook );
    }

    #[Filter( 'xwp_self_once', invoke: Filter::INV_PROXIED | Filter::INV_ONCE, args: 1, params: array( '!self.hook' ) )]
    public function once( string $value, Filter $hook ): string {
        Self_Hook_Module::record( $hook );
        return $value . ':once';
    }

    #[Filter( 'xwp_self_throw', invoke: Filter::INV_PROXIED, args: 1, params: array( '!self.hook' ) )]
    public function fail( string $value, Filter $hook ): string {
        Self_Hook_Module::record( $hook );
        throw new \RuntimeException( 'self.hook fixture failure' );
    }
}
