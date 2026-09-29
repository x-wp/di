<?php
/**
 * Legacy lifecycle extension compatibility.
 *
 * @package XWP\DI\Tests
 */
namespace Tests\XWP\DI\Unit;

use XWP\DI\Decorators\Filter;

final class Lifecycle_Compatibility_Test extends TestCase {
    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_global_context_gate_does_not_freeze_rest_prefix_before_theme_setup(): void {
        $prefix = 'wp-json';
        \Brain\Monkey\Functions\when( 'is_admin' )->justReturn( false );
        \Brain\Monkey\Functions\when( 'rest_get_url_prefix' )->alias( static function () use ( &$prefix ): string { return $prefix; } );
        \Brain\Monkey\Functions\when( 'trailingslashit' )->alias( static fn( string $value ): string => $value . '/' );
        \Brain\Monkey\Functions\when( 'wp_unslash' )->returnArg();
        \Brain\Monkey\Functions\when( 'sanitize_text_field' )->returnArg();
        $_SERVER['REQUEST_URI'] = '/api/wp/v2/posts';
        self::assertTrue( \XWP_Context::validate( Filter::CTX_GLOBAL ) );
        $prefix = 'api';
        self::assertSame( Filter::CTX_REST, \XWP_Context::get() );
    }

    public function test_check_method_retains_the_single_parameter_override_signature(): void {
        $method = new \ReflectionMethod( Filter::class, 'check_method' );
        self::assertSame( 1, $method->getNumberOfParameters(), 'Existing overrides accept exactly one argument.' );
        $filter = new class( 'example' ) extends Filter {
            protected function check_method( null|\Closure|string|array $method ): bool {
                return true;
            }
        };
        self::assertInstanceOf( Filter::class, $filter );
    }
}
