<?php
/**
 * Legacy constructor-injected service.
 *
 * @package XWP\DI\Tests
 */

namespace XWP\DI\E2E\Legacy;

final class Message_Service {
    public function __construct( public readonly string $message ) {
        record( 'service.construct', $message );
    }
}
