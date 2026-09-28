<?php
/**
 * Observable module tree with unconditional definitions and gated runtime work.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DIT\Lifecycle;

use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\Module;

#[Module(
    hook: 'xwp_module_parent',
    priority: 12,
    context: Module::CTX_ADMIN,
    imports: array( Lifecycle_Child_Module::class, Lifecycle_Excluded_Module::class ),
    handlers: array( Lifecycle_Parent_Handler::class, Lifecycle_Frontend_Handler::class ),
    services: array( Lifecycle_Parent_Service::class ),
)]
final class Module_Lifecycle_Root {
    use Records_Module_Lifecycle;

    public const LABEL = 'parent';
    public static array $events = array();
    public static array $allowed = array();
}

trait Records_Module_Lifecycle {
    public static function configure(): array {
        return array( 'lifecycle.' . self::LABEL . '.definition' => self::LABEL );
    }

    public static function can_initialize(): bool {
        Module_Lifecycle_Root::$events[] = self::LABEL . ':condition';
        return Module_Lifecycle_Root::$allowed[ self::LABEL ] ?? true;
    }

    public function __construct() {
        Module_Lifecycle_Root::$events[] = self::LABEL . ':construct';
    }

    public static function configure_async(): array {
        Module_Lifecycle_Root::$events[] = self::LABEL . ':configure';
        return array( 'lifecycle.' . self::LABEL . '.runtime' => true );
    }

    public function on_initialize(): void {
        Module_Lifecycle_Root::$events[] = self::LABEL . ':initialize';
    }

    #[Filter( 'xwp_module_value' )]
    public function value( string $value ): string {
        Module_Lifecycle_Root::$events[] = self::LABEL . ':invoke';
        return $value . ':' . self::LABEL;
    }

    #[Action( 'xwp_module_action' )]
    public function action(): void {
        Module_Lifecycle_Root::$events[] = self::LABEL . ':action';
    }
}

#[Module(
    hook: 'xwp_module_child',
    priority: 7,
    handlers: array( Lifecycle_Child_Handler::class ),
    services: array( Lifecycle_Child_Service::class ),
)]
final class Lifecycle_Child_Module {
    use Records_Module_Lifecycle;

    public const LABEL = 'child';
}

#[Module(
    hook: 'xwp_module_excluded',
    context: Module::CTX_FRONTEND,
    imports: array( Lifecycle_Descendant_Module::class ),
    services: array( Lifecycle_Excluded_Service::class ),
)]
final class Lifecycle_Excluded_Module {
    use Records_Module_Lifecycle;

    public const LABEL = 'excluded';
}

#[Module( hook: 'xwp_module_descendant' )]
final class Lifecycle_Descendant_Module {
    use Records_Module_Lifecycle;

    public const LABEL = 'descendant';
}

trait Records_Module_Handler {
    public function __construct() {
        Module_Lifecycle_Root::$events[] = self::LABEL . ':construct';
    }

    public function on_initialize(): void {
        Module_Lifecycle_Root::$events[] = self::LABEL . ':initialize';
    }

    #[Filter( 'xwp_module_value' )]
    public function value( string $value ): string {
        Module_Lifecycle_Root::$events[] = self::LABEL . ':invoke';
        return $value . ':' . self::LABEL;
    }
}

#[Handler( strategy: Handler::INIT_NOW )]
final class Lifecycle_Parent_Handler {
    use Records_Module_Handler;

    public const LABEL = 'parent-handler';
}

#[Handler( strategy: Handler::INIT_NOW )]
final class Lifecycle_Child_Handler {
    use Records_Module_Handler;

    public const LABEL = 'child-handler';
}

#[Handler( strategy: Handler::INIT_NOW, context: Handler::CTX_FRONTEND )]
final class Lifecycle_Frontend_Handler {
    use Records_Module_Handler;

    public const LABEL = 'frontend-handler';
}

final class Lifecycle_Parent_Service {}
final class Lifecycle_Child_Service {}
final class Lifecycle_Excluded_Service {}
