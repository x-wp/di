<?php
/**
 * Runtime hook priority resolution.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\Container;
use XWP\DI\Decorators\Filter;

final class Priority_Resolution_Test extends TestCase {
    /** @dataProvider priorities */
    public function test_resolves_supported_priorities( mixed $priority, int $expected ): void {
        $hook = ( new Filter( 'xwp_priority_target', priority: $priority ) )
            ->with_container( new Container( array(
                'app.debug' => false,
                'app.cache' => array( 'app' => false, 'defs' => false, 'hooks' => false, 'dir' => false ),
                'app.env' => 'testing',
                'app.id' => 'priority-resolution',
            ) ) );

        self::assertSame( $expected, $hook->get_priority() );
    }

    public static function priorities(): array {
        return array(
            'zero' => array( 0, 0 ),
            'negative' => array( -7, -7 ),
            'numeric string' => array( '23', 23 ),
            'constant' => array( Priority_Callback::class . '::PRIORITY', 19 ),
            'closure' => array( static function ( string $tag ): int {
                self::assertSame( 'xwp_priority_target', $tag );
                return 21;
            }, 21 ),
            'static array' => array( array( Priority_Callback::class, 'static_priority' ), 22 ),
            'container resolved instance' => array( array( Priority_Callback::class, 'instance_priority' ), 23 ),
            'callable string' => array( Priority_Callback::class . '::static_priority', 22 ),
        );
    }

    public function test_filter_priority_receives_default_and_hook_tag(): void {
        $callback = static function ( $default, string $tag ): int {
            self::assertSame( '15', $default );
            self::assertSame( 'xwp_priority_target', $tag );
            return 24;
        };
        add_filter( 'xwp_priority_setting', $callback, 10, 2 );
        try {
            $hook = new Filter( 'xwp_priority_target', priority: 'xwp_priority_setting:15' );
            self::assertSame( 24, $hook->get_priority() );
        } finally {
            remove_filter( 'xwp_priority_setting', $callback, 10 );
        }
    }

    public function test_filter_priority_without_callbacks_uses_its_default(): void {
        self::assertSame( 15, ( new Filter( 'xwp_priority_target', priority: 'xwp_unused_priority:15' ) )->get_priority() );
        self::assertSame( 10, ( new Filter( 'xwp_priority_target', priority: 'xwp_unused_priority' ) )->get_priority() );
    }
}

final class Priority_Callback {
    public const PRIORITY = 19;

    public static function static_priority( string $tag ): int {
        Priority_Resolution_Test::assertSame( 'xwp_priority_target', $tag );
        return 22;
    }

    public function instance_priority( string $tag ): int {
        Priority_Resolution_Test::assertSame( 'xwp_priority_target', $tag );
        return 23;
    }
}
