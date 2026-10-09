<?php
/**
 * Legacy imported module.
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
)]
final class Child_Module {
    /** @return array<string,mixed> */
    public static function configure(): array {
        return array( 'coexist.import' => 'legacy-import' );
    }

    #[Infuse( 'coexist.import' )]
    public function on_initialize( ?string $value = null ): void {
        record( 'import.initialize', $value );
    }
}
