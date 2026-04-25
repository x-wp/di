<?php
/**
 * CallbackDefinition unit tests.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Unit\Definition;

use Tests\XWP\DI\Unit\TestCase;
use XWP\DI\Decorators\Filter;
use XWP\DI\Definition\CallbackDefinition;

final class CallbackDefinition_Test extends TestCase {
    public function test_exposes_constructor_metadata(): void {
        $definition = new CallbackDefinition(
            'Hook-Handler::method[the_tag]',
            Callback_Handler_Fixture::class,
            'filter_value',
            'filter',
            'the_tag',
            15,
            2,
            Filter::CTX_FRONTEND,
            Filter::INV_ONCE,
            array( '!self.handler' ),
            array( 'dynamic' ),
            array( Callback_Handler_Fixture::class, 'can_filter' ),
        );

        self::assertSame( 'Hook-Handler::method[the_tag]', $definition->get_id() );
        self::assertSame( Callback_Handler_Fixture::class, $definition->get_handler() );
        self::assertSame( 'filter_value', $definition->get_method() );
        self::assertSame( 'filter', $definition->get_type() );
        self::assertSame( 'the_tag', $definition->get_tag() );
        self::assertSame( 15, $definition->get_priority() );
        self::assertSame( 2, $definition->get_accepted_args() );
        self::assertSame( Filter::CTX_FRONTEND, $definition->get_context() );
        self::assertSame( Filter::INV_ONCE, $definition->get_invoke() );
        self::assertSame( array( '!self.handler' ), $definition->get_params() );
        self::assertSame( array( 'dynamic' ), $definition->get_modifiers() );
        self::assertSame( array( Callback_Handler_Fixture::class, 'can_filter' ), $definition->get_conditional() );
    }

    public function test_equal_definitions_compare_equal(): void {
        $left  = new CallbackDefinition( 'id', Callback_Handler_Fixture::class, 'callback', 'action', 'init' );
        $right = new CallbackDefinition( 'id', Callback_Handler_Fixture::class, 'callback', 'action', 'init' );

        self::assertTrue( $left->equals( $right ) );
    }

    public function test_different_definitions_do_not_compare_equal(): void {
        $left  = new CallbackDefinition( 'id', Callback_Handler_Fixture::class, 'callback', 'action', 'init' );
        $right = new CallbackDefinition( 'id', Callback_Handler_Fixture::class, 'other', 'action', 'init' );

        self::assertFalse( $left->equals( $right ) );
    }
}

final class Callback_Handler_Fixture {
}
