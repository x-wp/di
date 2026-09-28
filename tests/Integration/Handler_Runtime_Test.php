<?php
/**
 * Handler runtime metadata and compatibility contracts.
 *
 * @package XWP\DI\Tests
 */
namespace Tests\XWP\DI\Integration;

use XWP\DI\Container;
use XWP\DI\Decorators\Handler as Handler_Attribute;
use XWP\DI\Decorators\Module as Module_Attribute;
use XWP\DI\Definition\HandlerDefinition;
use XWP\DI\Hook\Factory;
use XWP\DI\Hook\Handler;
use XWP\DI\Hook\Module;

final class Handler_Runtime_Test extends TestCase {
    private function container(): Container {
        return new Handler_Runtime_Container( array(
            'app.debug' => false, 'app.id' => 'handler-runtime', 'app.env' => 'testing',
            'app.cache' => array( 'app' => false, 'defs' => false, 'hooks' => false, 'dir' => false ),
        ) );
    }

    public function test_definition_constructor_and_adapter_metadata_roundtrip(): void {
        $definition = new HandlerDefinition( Handler_Runtime_Target::class, 'init', 27, strategy: Handler_Attribute::INIT_LAZY );
        $runtime = new Handler( $definition, $this->container() );
        self::assertNotInstanceOf( \XWP\DI\Decorators\Hook::class, $runtime );
        $runtime->with_params( array( 'can_initialize' => array( 'service.token' ) ) )->with_callbacks( array( 'callback.token' ) );
        $copy = HandlerDefinition::from_data( $runtime->get_data() );
        self::assertSame( 'init', $copy->get_tag() );
        self::assertSame( 27, $copy->get_priority() );
        self::assertSame( Handler_Attribute::INIT_LAZY, $copy->get_strategy() );
        self::assertSame( array( 'service.token' ), $copy->get_params()['can_initialize'] );
        self::assertSame( array( 'callback.token' ), $copy->get_callbacks() );
        self::assertNull( $definition->get_callbacks(), 'Runtime discovery must not mutate the original definition.' );
    }

    public function test_module_strategy_and_composition_roundtrip(): void {
        $data = ( new Module_Attribute( 'init', services: array( Handler_Runtime_Target::class ) ) )->with_classname( Handler_Runtime_Target::class )->get_data();
        $runtime = new Module( HandlerDefinition::from_data( $data ), $this->container() );
        $runtime->with_strategy( Handler_Attribute::INIT_NOW );
        $copy = new Module( HandlerDefinition::from_data( $runtime->get_data() ), $this->container() );
        self::assertSame( Handler_Attribute::INIT_NOW, $copy->get_strategy() );
        self::assertSame( array( Handler_Runtime_Target::class ), $copy->get_services() );
    }

    public function test_custom_handler_decorator_keeps_overrides_and_instance_identity(): void {
        $container = $this->container();
        $factory = new Factory( $container );
        $instance = new Custom_Handler_Runtime_Target();
        $runtime = $factory->load_handler( $instance );
        self::assertSame( Custom_Handler_Attribute::class, $runtime::class );
        self::assertSame( $instance, $runtime->get_target() );
        self::assertSame( $runtime, $factory->get_handler( $instance::class ) );
        self::assertSame( 37, $runtime->get_priority() );
    }
}

final class Handler_Runtime_Target {
    public static function can_initialize( mixed $service ): bool { return true; }
}

#[\Attribute( \Attribute::TARGET_CLASS )]
final class Custom_Handler_Attribute extends Handler_Attribute {
    public function get_priority(): int { return 37; }
}

#[Custom_Handler_Attribute]
final class Custom_Handler_Runtime_Target {}

final class Handler_Runtime_Container extends Container {
    public function started(): bool { return true; }
}
