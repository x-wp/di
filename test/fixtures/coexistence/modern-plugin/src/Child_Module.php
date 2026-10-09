<?php
/**
 * Modern imported module.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DI\E2E\Modern;

use XWP\DI\Decorators\Infuse;
use XWP\DI\Decorators\Module;

// The parent registers this import during init:10; its own schedule must still be ahead.
#[Module( hook: 'init', priority: 11, handlers: array( Hook_Handler::class ) )]
final class Child_Module {
    /** @return array<string,mixed> */
    public static function configure(): array {
        return array( 'coexist.import' => 'modern-import' );
    }

    #[Infuse( 'coexist.import' )]
    public function on_initialize( ?string $value = null ): void {
        record( 'import.initialize', $value );
    }
}
