<?php
/**
 * Plain callbacks and custom declaration subclasses in the same handler.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DIT\Lifecycle;

use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Dynamic_Filter;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;

#[Module( hook: 'xwp_wiring_module', handlers: array( Callback_Wiring_Handler::class ) )]
final class Callback_Wiring_Module {}

#[Handler( strategy: Handler::INIT_NOW )]
final class Callback_Wiring_Handler {
    public array $events = array();

    #[Filter( 'xwp_wiring_standard', priority: 17, args: 1 )]
    public function standard( string $value ): string {
        $this->events[] = 'standard';
        return $value . ':standard';
    }

    #[Action( 'xwp_wiring_action', invoke: Action::INV_PROXIED, args: 0 )]
    public function action(): void {
        $this->events[] = 'action';
    }

    #[Dynamic_Filter( 'xwp_wiring_%s', array( 'dynamic' ) )]
    public function dynamic( string $value, string $suffix ): string {
        $this->events[] = 'dynamic';
        return $value . ':' . $suffix;
    }

    #[Custom_Wiring_Filter( 'xwp_wiring_custom', invoke: Filter::INV_PROXIED )]
    public function custom( string $value, string $suffix ): string {
        $this->events[] = 'custom';
        return $value . ':custom:' . $suffix;
    }
}

#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
final class Custom_Wiring_Filter extends Filter {
    public function get_declaration(): array {
        return array_replace( parent::get_declaration(), array( 'args' => 1, 'params' => array( '!value:metadata' ) ) );
    }
}

final class Callback_Provided_Handler {
    #[Filter( 'xwp_wiring_provided', invoke: Filter::INV_PROXIED )]
    public function value( string $value ): string {
        return $value . ':provided';
    }
}
