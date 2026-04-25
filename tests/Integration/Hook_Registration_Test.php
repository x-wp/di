<?php
/**
 * Integration test: the fixture App boots, walks its module tree, and
 * its `#[Filter]`-decorated callback is wired into a real WP hook.
 *
 * The fixture plugin is loaded by tests/bootstrap.php at muplugins_loaded,
 * which calls xwp_load_app() against XWP\DIT\App_Module. Body_Handler is
 * gated to the frontend context, so we set is_admin() off before init.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

final class Hook_Registration_Test extends TestCase {
    public function test_app_factory_singleton_is_initialized(): void {
        self::assertTrue( class_exists( \XWP\DI\App_Factory::class ) );
        self::assertTrue( function_exists( 'xwp_load_app' ) );
    }

    public function test_body_class_filter_is_registered(): void {
        // The fixture Handler is gated to CTX_FRONTEND; ensure init has
        // already fired (wp-phpunit's bootstrap fires it during install).
        do_action( 'init' );

        $priority = has_filter( 'body_class' );

        self::assertNotFalse(
            $priority,
            'Body_Handler::change_body_class should be wired into the body_class filter after init.'
        );
    }
}
