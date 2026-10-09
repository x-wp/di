<?php
/**
 * Modern root module and service definitions.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DI\E2E\Modern;

use XWP\DI\Decorators\Infuse;
use XWP\DI\Decorators\Module;

#[Module( hook: 'init', priority: 10, imports: array( Child_Module::class ) )]
final class Root_Module {
    /** @return array<string,mixed> */
    public static function define(): array {
        return array(
            'coexist.message'      => 'modern',
            'coexist.root'         => 'modern-root',
            Message_Service::class => \DI\autowire()->constructorParameter( 'message', \DI\get( 'coexist.message' ) ),
        );
    }

    #[Infuse( 'coexist.message' )]
    public function on_initialize( ?string $message = null ): void {
        record( 'root.initialize', $message );
    }
}
