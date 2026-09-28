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
    public function test_maps_metadata_without_resolving_runtime_values(): void {
        $priority = static function (): int { throw new \LogicException( 'Must remain raw.' ); };
        $condition = static function (): bool { throw new \LogicException( 'Must remain raw.' ); };
        $data = ( new Handler( 'init', priority: $priority, conditional: $condition, strategy: Handler::INIT_JIT ) )
            ->with_classname( Handler_Fixture::class )->with_callbacks( array( 'callback.token' ) )->get_data();
        $definition = HandlerDefinition::from_data( $data );
        self::assertSame( 'Hook-' . Handler_Fixture::class, $definition->get_id() );
        self::assertSame( $priority, $definition->get_priority() );
        self::assertSame( $condition, $definition->get_conditional() );
        self::assertSame( Handler::INIT_JIT, $definition->get_strategy() );
        self::assertSame( array( 'callback.token' ), $definition->get_callbacks() );
        self::assertSame( Handler::class, $definition->get_decorator() );
    }

    public function test_keeps_unscheduled_priority_unresolved(): void {
        $data = ( new Handler() )->with_classname( Handler_Fixture::class )->get_data();
        self::assertNull( HandlerDefinition::from_data( $data )->get_priority() );
    }

    public function test_specialized_metadata_preserves_strategy_context_and_composition(): void {
        $priority = static function (): int { throw new \LogicException( 'Discovery must not resolve priorities.' ); };
        $decorators = array(
            new \XWP\DI\Decorators\Ajax_Handler( 'prefix', priority: 21 ),
            new \XWP\DI\Decorators\REST_Handler( 'test/v1', 'items', priority: 22 ),
            new \XWP\DI\Decorators\CLI_Handler( 'test', priority: $priority ),
            new \XWP\DI\Decorators\Module( 'init', priority: 23, imports: array( 'Imported' ), handlers: array( 'Handled' ), services: array( 'Service' ) ),
        );
        $contexts = array( Handler::CTX_AJAX, Handler::CTX_REST, Handler::CTX_CLI, Handler::CTX_GLOBAL );
        $priorities = array( 21, 22, $priority, 23 );
        foreach ( $decorators as $index => $decorator ) {
            $data = $decorator->with_classname( Handler_Fixture::class )->get_data();
            $definition = HandlerDefinition::from_data( $data );
            self::assertSame( $priorities[ $index ], $definition->get_priority() );
            self::assertSame( $contexts[ $index ], $definition->get_context() );
            self::assertSame( $decorator->get_strategy(), $definition->get_strategy() );
            self::assertSame( $data['args'], $definition->get_options() );
        }
        $composition = \XWP\DI\Definition\ModuleDefinition::from_data( $data );
        self::assertSame( array( 'Imported' ), $composition->get_imports() );
        self::assertSame( array( 'Handled' ), $composition->get_handlers() );
        self::assertSame( array( 'Service' ), $composition->get_services() );
    }

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
