<?php
/**
 * Legacy root module and service definitions.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DI\E2E\Legacy;

use XWP\DI\Decorators\Infuse;
use XWP\DI\Decorators\Module;

#[Module(
    container: 'coexist_legacy',
    hook: 'init',
    priority: 10,
    imports: array( Child_Module::class ),
    handlers: array( Hook_Handler::class ),
)]
final class Root_Module {
    /** @return array<string,mixed> */
    public static function configure(): array {
        return array(
            'coexist.message'      => 'legacy',
            'coexist.root'         => 'legacy-root',
            Message_Service::class => \DI\autowire()->constructorParameter( 'message', \DI\get( 'coexist.message' ) ),
        );
    }

    #[Infuse( 'coexist.message' )]
    public function on_initialize( ?string $message = null ): void {
        record( 'root.initialize', $message );
    }
}
