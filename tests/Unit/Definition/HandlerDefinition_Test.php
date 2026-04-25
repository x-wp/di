<?php
/**
 * HandlerDefinition unit tests.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Unit\Definition;

use Tests\XWP\DI\Unit\TestCase;
use XWP\DI\Decorators\Handler;
use XWP\DI\Definition\HandlerDefinition;

final class HandlerDefinition_Test extends TestCase {
    public function test_exposes_constructor_metadata(): void {
        $definition = new HandlerDefinition(
            Handler_Fixture::class,
            'init',
            20,
            Handler::CTX_ADMIN,
            Handler::INIT_LAZY,
            false,
            array( 'bootstrap' => array( 'service.id' ) ),
            array( 'callback.token' ),
        );

        self::assertSame( Handler_Fixture::class, $definition->get_class() );
        self::assertSame( 'init', $definition->get_tag() );
        self::assertSame( 20, $definition->get_priority() );
        self::assertSame( Handler::CTX_ADMIN, $definition->get_context() );
        self::assertSame( Handler::INIT_LAZY, $definition->get_strategy() );
        self::assertFalse( $definition->is_hookable() );
        self::assertSame( array( 'bootstrap' => array( 'service.id' ) ), $definition->get_params() );
        self::assertSame( array( 'callback.token' ), $definition->get_callbacks() );
    }

    public function test_equal_definitions_compare_equal(): void {
        $left  = new HandlerDefinition( Handler_Fixture::class );
        $right = new HandlerDefinition( Handler_Fixture::class );

        self::assertTrue( $left->equals( $right ) );
    }

    public function test_different_definitions_do_not_compare_equal(): void {
        $left  = new HandlerDefinition( Handler_Fixture::class, 'init' );
        $right = new HandlerDefinition( Handler_Fixture::class, 'plugins_loaded' );

        self::assertFalse( $left->equals( $right ) );
    }
}

final class Handler_Fixture {
}
