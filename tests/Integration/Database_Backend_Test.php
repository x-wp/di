<?php
/**
 * Verify that the test harness boots the selected database backend.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

final class Database_Backend_Test extends TestCase {
    public function test_wordpress_uses_the_selected_database_backend(): void {
        global $wpdb;

        $engine = getenv( 'WP_TESTS_DB_ENGINE' ) ?: 'sqlite';

        if ( 'sqlite' === $engine ) {
            self::assertInstanceOf( \WP_SQLite_DB::class, $wpdb );
            self::assertFileExists( DB_DIR . '/' . DB_FILE );
            return;
        }

        self::assertSame( \wpdb::class, $wpdb::class );
    }
}
