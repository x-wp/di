<?php
/**
 * Observable supplied-instance adoption.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DIT\Lifecycle;

use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;

#[Module( hook: 'xwp_supplied_module', handlers: array( User_Supplied_Handler::class, Auto_Supplied_Handler::class ) )]
final class Supplied_Instance_Module {
    public static array $events = array();
    public static array $action_identity = array();
}

final class Supplied_Dependency {}

trait Supplied_Instance_Lifecycle {
    public function __construct( public string $label = 'container-created' ) {
        Supplied_Instance_Module::$events[] = 'construct:' . $label;
    }

    public static function can_initialize(): bool {
        Supplied_Instance_Module::$events[] = 'condition';
        return true;
    }

    public function on_initialize(): void {
        Supplied_Instance_Module::$events[] = 'initialize';
    }

    #[Filter( 'xwp_supplied_value' )]
    public function value( string $value ): string {
        Supplied_Instance_Module::$events[] = 'invoke:' . $this->label;
        return $value . ':' . $this->label;
    }

    #[Filter( 'xwp_supplied_direct_identity' )]
    public function direct_identity(): object {
        return $this;
    }

    #[Filter( 'xwp_supplied_proxy_identity', invoke: Filter::INV_PROXIED, args: 1 )]
    public function proxy_identity( string $value, Supplied_Dependency $dependency ): array {
        return array( $this, $value, $dependency );
    }

    #[Action( 'xwp_supplied_action_identity', invoke: Action::INV_PROXIED, args: 1 )]
    public function action_identity( string $value, Supplied_Dependency $dependency ): void {
        Supplied_Instance_Module::$action_identity = array( $this, $value, $dependency );
    }

    #[Filter( 'xwp_supplied_static', invoke: Filter::INV_PROXIED, args: 1 )]
    public static function static_value( string $value, Supplied_Dependency $dependency ): array {
        return array( $value . ':static', $dependency );
    }
}

#[Handler( strategy: Handler::INIT_USER )]
final class User_Supplied_Handler {
    use Supplied_Instance_Lifecycle;
}

#[Handler( tag: 'xwp_supplied_attach', strategy: Handler::INIT_AUTO )]
final class Auto_Supplied_Handler {
    use Supplied_Instance_Lifecycle;
}

final class Plain_Supplied_Handler {
    use Supplied_Instance_Lifecycle;
}
