<?php
/**
 * CallbackDefinition unit tests.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Unit\Definition;

use Tests\XWP\DI\Unit\TestCase;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Dynamic_Filter;
use XWP\DI\Decorators\Filter;
use XWP\DI\Definition\CallbackDefinition;

final class CallbackDefinition_Test extends TestCase {
    public function test_maps_decorator_metadata_without_evaluating_it(): void {
        $priority = static function (): int {
            throw new \LogicException( 'Priority must remain unevaluated.' );
        };
        $conditional = static function (): bool {
            throw new \LogicException( 'Condition must remain unevaluated.' );
        };
        $hook = ( new Filter(
            tag: 'the_{app.id}_title',
            priority: $priority,
            context: Filter::CTX_FRONTEND,
            conditional: $conditional,
            modifiers: array( 'app.id' ),
            invoke: Filter::INV_ONCE | Filter::INV_SAFELY,
            args: 0,
            params: array( '!self.handler', '!self.hook' ),
        ) )->with_classname( Callback_Handler_Fixture::class )
            ->with_reflector( new \ReflectionMethod( Callback_Handler_Fixture::class, 'filter_value' ) );

        $definition = CallbackDefinition::from_data( $hook->get_data() );

        self::assertSame( $hook->get_token(), $definition->get_id() );
        self::assertSame( Callback_Handler_Fixture::class, $definition->get_handler() );
        self::assertSame( 'filter_value', $definition->get_method() );
        self::assertSame( 'filter', $definition->get_type() );
        self::assertSame( 'the_app.id_title', $definition->get_tag() );
        self::assertSame( $priority, $definition->get_priority() );
        self::assertSame( 0, $definition->get_accepted_args() );
        self::assertSame( Filter::CTX_FRONTEND, $definition->get_context() );
        self::assertSame( Filter::INV_ONCE | Filter::INV_SAFELY, $definition->get_invoke() );
        self::assertSame( array( '!self.handler', '!self.hook' ), $definition->get_params() );
        self::assertSame( array( 'app.id' ), $definition->get_modifiers() );
        self::assertSame( $conditional, $definition->get_conditional() );
    }

    public function test_preserves_tokens_and_defaults_for_plain_callbacks(): void {
        foreach ( array( Filter::class => 'filter', Action::class => 'action' ) as $class => $type ) {
            foreach ( array( 'the_title', 'the_{app.id}_title', 'the_%s_title' ) as $tag ) {
                $hook = ( new $class( $tag ) )->with_classname( Callback_Handler_Fixture::class )->with_reflector(
                    new \ReflectionMethod( Callback_Handler_Fixture::class, 'filter_value' ),
                );

                $definition = CallbackDefinition::from_data( $hook->get_data() );

                self::assertSame( $hook->get_token(), $definition->get_id() );
                self::assertSame( $type, $definition->get_type() );
                self::assertSame( 2, $definition->get_accepted_args() );
                self::assertSame( 10, $definition->get_priority() );
                self::assertSame( Filter::CTX_GLOBAL, $definition->get_context() );
                self::assertSame( Filter::INV_STANDARD, $definition->get_invoke() );
                self::assertSame( array(), $definition->get_params() );
                self::assertFalse( $definition->get_modifiers() );
                self::assertNull( $definition->get_conditional() );
            }
        }
    }

    public function test_preserves_raw_priorities_and_unresolved_argument_count(): void {
        $hook = ( new Filter( 'the_title' ) )
            ->with_data( array( 'classname' => Callback_Handler_Fixture::class, 'method' => 'filter_value' ) );

        foreach ( array( null, 0, 'PHP_INT_MAX', 'priority_filter:15', array( Callback_Handler_Fixture::class, 'priority' ) ) as $priority ) {
            $data = $hook->get_data();
            $data['args']['priority'] = $priority;
            $data['args']['modifiers'] = 'app.id';
            $data['args']['conditional'] = array( Callback_Handler_Fixture::class, 'can_filter' );

            $definition = CallbackDefinition::from_data( $data );

            self::assertSame( $priority, $definition->get_priority() );
            self::assertNull( $definition->get_accepted_args() );
            self::assertSame( 'app.id', $definition->get_modifiers() );
            self::assertSame( array( Callback_Handler_Fixture::class, 'can_filter' ), $definition->get_conditional() );
        }
    }

    public function test_rejects_specialized_callbacks_instead_of_losing_their_type(): void {
        $hook = ( new Dynamic_Filter( 'the_%s_title', array( 'app.id' ) ) )->with_classname( Callback_Handler_Fixture::class )->with_reflector(
            new \ReflectionMethod( Callback_Handler_Fixture::class, 'filter_value' ),
        );

        $this->expectException( \InvalidArgumentException::class );

        CallbackDefinition::from_data( $hook->get_data() );
    }

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
    public function filter_value( string $value, int $id ): string {
        return $value;
    }

    public static function priority(): int {
        throw new \LogicException( 'Priority must remain unevaluated.' );
    }

    public static function can_filter(): bool {
        throw new \LogicException( 'Condition must remain unevaluated.' );
    }
}
