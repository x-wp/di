<?php
/**
 * Smoke test: App_Builder is a PHP-DI ContainerBuilder subclass and
 * exposes the expected fluent surface.
 *
 * Calling configure() requires WP functions, so the deeper container
 * build is exercised in the integration suite. Here we just guard the
 * type contract that downstream consumers rely on.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Unit;

use DI\ContainerBuilder;
use XWP\DI\App_Builder;
use XWP\DI\Container;

final class App_Builder_Test extends TestCase {
    public function test_extends_php_di_container_builder(): void {
        self::assertTrue( is_subclass_of( App_Builder::class, ContainerBuilder::class ) );
    }

    public function test_construct_accepts_container_class(): void {
        $builder = new App_Builder( Container::class );
        self::assertInstanceOf( App_Builder::class, $builder );
    }

    public function test_is_hook_cache_disabled_by_default(): void {
        $builder = new App_Builder( Container::class );
        self::assertFalse( $builder->isHookCacheEnabled() );
    }
}
