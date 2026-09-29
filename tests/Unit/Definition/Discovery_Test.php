<?php
/**
 * Definition discovery preserves the existing metadata wire format.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Unit\Definition;

use PHPUnit\Framework\TestCase;
use XWP\DI\Decorators as D;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Definition\HandlerDefinition;
use XWP\DI\Hook\Factory;
use XWP\DI\Interfaces\Can_Handle;
use XWP\DI\Interfaces\Can_Invoke;
use XWP\DI\Utils\Reflection;

final class Discovery_Test extends TestCase {
    public function test_narrow_specialized_constructors_accept_exported_metadata(): void {
        $factory = new Factory();
        foreach ( array( new Discovery_Narrow_Ajax( 'action' ), new Discovery_Narrow_Route( '/route', 'GET' ) ) as $callback ) {
            $callback->with_classname( Discovery_Repeated_Host::class )->with_method( 'value' );
            self::assertSame( $callback->get_data(), $factory->make( $callback->get_data() )->get_data() );
        }
        $handler = ( new Discovery_Narrow_REST( 'review/v1', 'items' ) )->with_reflector( new \ReflectionClass( Discovery_Repeated_Host::class ) );
        self::assertSame( $handler->get_data(), $factory->make( $handler->get_data() )->get_data() );
    }

    public function test_repeated_specialized_and_custom_callbacks_have_stable_ids(): void {
        $factory = new Factory();
        foreach ( array( Discovery_Repeated_REST::class, Discovery_Repeated_Custom::class ) as $class ) {
            $callbacks = $factory->resolve_callbacks( $factory->resolve_handler( $class ) );
            $tokens = array_map( static fn( $cb ) => $cb->get_token(), $callbacks );
            self::assertCount( count( $callbacks ), array_unique( $tokens ) );
            foreach ( $callbacks as $callback ) {
                $data = $callback->get_data();
                self::assertInstanceOf( $data['type'], new $data['type']( ...$data['args'] ) );
            }
            self::assertSame( $tokens, array_map( static fn( $cb ) => $factory->make( $cb->get_data() )->get_token(), $callbacks ) );
            self::assertSame( $tokens, array_map( static fn( $cb ) => $cb->get_token(), $factory->resolve_callbacks( $factory->resolve_handler( $class ) ) ) );
        }
    }

    public function test_repeated_callback_ids_are_unique_and_survive_metadata_round_trips(): void {
        $factory = new Factory();
        $callbacks = $factory->resolve_callbacks( $factory->resolve_handler( Discovery_Repeated_Host::class ) );
        $tokens = array_map( static fn( $cb ) => $cb->get_token(), $callbacks );
        self::assertCount( 2, array_unique( $tokens ) );
        self::assertSame( $tokens, array_map( static fn( $cb ) => $factory->make( $cb->get_data() )->get_token(), $callbacks ) );
    }

    public function test_missing_handler_uses_the_legacy_exception_contract(): void {
        $this->expectException( \InvalidArgumentException::class );
        ( new Factory() )->resolve_handler( 'Missing_Discovery_Review_Class' );
    }

    public function test_custom_narrow_module_constructor_can_round_trip(): void {
        $factory = new Factory();
        $module = $factory->resolve_module( Discovery_Narrow_Module_Host::class );
        $copy = $factory->make( $module->get_data() );
        self::assertSame( $module->get_data(), $copy->get_data() );
    }

    /** @dataProvider handlers */
    public function test_handler_discovery_returns_definition_with_legacy_metadata( string $class ): void {
        $factory = new Factory();
        $actual = $factory->resolve_handler( $class );
        self::assertInstanceOf( HandlerDefinition::class, $actual );
        $reflector = new \ReflectionClass( $class );
        $legacy = Reflection::get_decorator( $reflector, Can_Handle::class )->with_reflector( $reflector );
        self::assertSame( $legacy->get_data(), $actual->get_data() );
        self::assertSame( $legacy->get_token(), $actual->get_token() );
    }

    public function test_custom_callbacks_share_the_original_handler_adapter(): void {
        Discovery_Custom_Filter::$handlers = array();
        $factory = new Factory();
        $definition = $factory->resolve_handler( Discovery_Custom_Host::class );
        $callbacks = $factory->resolve_callbacks( $definition );
        self::assertCount( 2, $callbacks );
        self::assertSame( Discovery_Custom_Filter::class, $callbacks[0]::class );
        self::assertSame( Discovery_Custom_Filter::$handlers[0], Discovery_Custom_Filter::$handlers[1] );
        self::assertSame( array( 'custom_option' ), Discovery_Custom_Filter::$handlers[0]->get_compat_args() );
    }

    public function test_custom_callback_mutations_reach_handler_metadata(): void {
        $factory = new Factory();
        $handler = $factory->resolve_handler( Discovery_Custom_Host::class );
        $factory->resolve_callbacks( $handler );
        self::assertSame( array( 'service' ), $handler->get_data()['params']['params']['on_initialize'] );
    }

    public function test_custom_module_strategy_and_lazy_overrides_are_preserved(): void {
        $factory = new Factory();
        $module = $factory->resolve_module( Discovery_Custom_Module_Host::class );
        self::assertSame( D\Handler::INIT_NOW, $module->get_data()['args']['strategy'] );
        $handler = $factory->resolve_handler( Discovery_Custom_Lazy_Host::class );
        self::assertSame( D\Filter::INV_STANDARD, $factory->resolve_callbacks( $handler )[0]->get_invoke() );
    }

    public function test_infuse_does_not_require_handler_binding_without_self_token(): void {
        self::assertSame( array( 'service' ), ( new D\Infuse( 'service' ) )->get( new D\Handler( 'init' ) ) );
    }

    public function test_parser_serializes_custom_mutations_and_builtin_callback_ids(): void {
        $raw = ( new \XWP\DI\Hook\Parser( Discovery_Mixed_Module::class, 'discovery' ) )->make( true )->get_raw();
        $custom = $raw['values']['Hook-' . Discovery_Custom_Host::class . '[params]'];
        self::assertSame( array( 'service' ), $custom['params']['params']['on_initialize'] );
        self::assertCount( 2, $custom['params']['callbacks'] );
        $builtin = $raw['values']['Hook-' . Discovery_Handler::class . '[params]'];
        self::assertSame( array( 'Hook-' . Discovery_Handler::class . '::inherited[discovery]' ), $builtin['params']['callbacks'] );
        self::assertSame( D\Handler::class, $builtin['type'] );
        self::assertNull( ( new Factory() )->resolve_handler( Discovery_Handler::class )->get_callbacks() );
    }

    public function test_custom_infuse_preserves_metadata_evaluation_timing_and_constructor_options(): void {
        Discovery_Custom_Infuse::$calls = 0;
        $handler = ( new Factory() )->resolve_handler( Discovery_Infuse_Host::class );
        self::assertSame( D\Handler::class, $handler::class );
        self::assertSame( 0, Discovery_Custom_Infuse::$calls );
        self::assertSame( array( 'custom_option' ), $handler->get_data()['params']['params']['on_initialize'] );
        self::assertSame( 1, Discovery_Custom_Infuse::$calls );
    }

    public static function handlers(): array {
        return array_map( static fn( $class ) => array( $class ), array(
            Discovery_Handler::class, Discovery_Module::class, Discovery_Ajax::class,
            Discovery_REST::class, Discovery_CLI::class,
        ) );
    }

    public function test_callback_discovery_preserves_metadata_without_bound_decorators(): void {
        $factory = new Factory();
        $actual = $factory->resolve_callbacks( $factory->resolve_handler( Discovery_REST::class ) );
        $reflector = new \ReflectionClass( Discovery_REST::class );
        $legacy_handler = Reflection::get_decorator( $reflector, Can_Handle::class )->with_reflector( $reflector );
        $expected = array();
        foreach ( Reflection::get_hookable_methods( $reflector ) as $method ) {
            foreach ( Reflection::get_decorators( $method, Can_Invoke::class ) as $decorator ) {
                $expected[] = $decorator->with_handler( $legacy_handler )->with_reflector( $method )->get_data();
            }
        }
        self::assertCount( 7, $actual );
        foreach ( $actual as $index => $definition ) {
            self::assertInstanceOf( CallbackDefinition::class, $definition );
            self::assertSame( $expected[$index], $definition->get_data() );
        }
    }

    public function test_lazy_callbacks_are_proxied_and_inherit_the_concrete_class(): void {
        $factory = new Factory();
        $definition = $factory->resolve_callbacks( $factory->resolve_handler( Discovery_Handler::class ) )[0];
        self::assertInstanceOf( CallbackDefinition::class, $definition );
        self::assertSame( D\Filter::INV_PROXIED, $definition->get_invoke() );
        self::assertSame( Discovery_Handler::class, $definition->get_handler() );
        self::assertSame( 'inherited', $definition->get_method() );
        self::assertSame( 2, $definition->get_accepted_args() );
        self::assertSame( 'on_initialize', array_key_first( $factory->resolve_handler( Discovery_Handler::class )->get_params() ) );
        self::assertSame( array( 'Hook-' . Discovery_Handler::class, 'service' ), $factory->resolve_handler( Discovery_Handler::class )->get_params()['on_initialize'] );
    }
}

class Discovery_Parent {
    #[D\Filter( '{discovery}', priority: 'unresolved_priority' )]
    public function inherited( $value, $extra ) {}
}

#[D\Handler( strategy: D\Handler::INIT_JIT )]
class Discovery_Handler extends Discovery_Parent {
    #[D\Infuse( '!self.handler', 'service' )]
    public function on_initialize( $handler, $service ) {}
}

#[D\Module( 'plugins_loaded', services: array( \stdClass::class ), strategy: D\Handler::INIT_NOW )]
class Discovery_Module {}

#[D\Ajax_Handler( 'discovery_', conditional: 'unresolved_condition' )]
class Discovery_Ajax {}

#[D\CLI_Handler( 'discovery', priority: 'unresolved_priority' )]
class Discovery_CLI {}

#[D\REST_Handler( 'discovery/v1', 'items', priority: 19 )]
class Discovery_REST {
    #[D\Filter( '{one}', args: 0 )]
    #[D\Action( 'two' )]
    #[D\Dynamic_Filter( 'three_%s', array( 'a' ) )]
    #[D\Dynamic_Action( 'four_%s', 'unresolved_provider', args: 2 )]
    #[D\Ajax_Action( 'five', nonce: true, params: array( '!self.hook' ) )]
    #[D\REST_Route( 'six', 'GET' )]
    #[D\CLI_Command( 'seven', args: array( 'id' => 'ID' ) )]
    public function run( $value, $extra ) {}
}

#[\Attribute( \Attribute::TARGET_METHOD )]
class Discovery_Custom_Filter extends D\Filter {
    public static array $handlers = array();

    public function with_handler( Can_Handle $handler ): static {
        self::$handlers[] = $handler;
        $handler->with_params( array( 'on_initialize' => array( 'service' ) ) );
        return parent::with_handler( $handler );
    }
}

#[D\Handler( 'custom_host', custom_option: true )]
class Discovery_Custom_Host {
    #[Discovery_Custom_Filter( 'custom_one' )]
    public function one() {}

    #[Discovery_Custom_Filter( 'custom_two' )]
    public function two() {}
}

#[\Attribute( \Attribute::TARGET_CLASS )]
class Discovery_Custom_Module extends D\Module {
    public function get_strategy(): string {
        return self::INIT_NOW;
    }
}

#[Discovery_Custom_Module( 'init' )]
class Discovery_Custom_Module_Host {}

#[\Attribute( \Attribute::TARGET_CLASS )]
class Discovery_Custom_Lazy extends D\Handler {
    public function is_lazy(): bool {
        return false;
    }
}

#[Discovery_Custom_Lazy( 'init', strategy: D\Handler::INIT_LAZY )]
class Discovery_Custom_Lazy_Host extends Discovery_Parent {}

#[D\Module( 'init', handlers: array( Discovery_Custom_Host::class, Discovery_Handler::class ) )]
class Discovery_Mixed_Module {}

#[\Attribute( \Attribute::TARGET_METHOD )]
class Discovery_Custom_Infuse extends D\Infuse {
    public static int $calls = 0;

    public function get( Can_Handle $handler ) {
        ++self::$calls;
        return $handler->get_compat_args();
    }
}

#[D\Handler( 'init', custom_option: true )]
class Discovery_Infuse_Host {
    #[Discovery_Custom_Infuse()]
    protected function on_initialize() {}
}

#[\Attribute( \Attribute::TARGET_CLASS )]
class Discovery_Narrow_Module extends D\Module {
    public function __construct( string $hook, int $priority = 10, int $context = self::CTX_GLOBAL, array $imports = array(), array $handlers = array() ) {
        parent::__construct( $hook, $priority, $context, $imports, $handlers );
    }
}

#[Discovery_Narrow_Module( 'init' )]
class Discovery_Narrow_Module_Host {}

#[D\Handler( 'init' )]
class Discovery_Repeated_Host {
    #[D\Filter( 'repeated', priority: 10 )]
    #[D\Action( 'repeated', priority: 20 )]
    public function value( $value ) { return $value; }
}

class Discovery_Narrow_Ajax extends D\Ajax_Action {
    public function __construct( string $action ) { parent::__construct( $action ); }
}
class Discovery_Narrow_Route extends D\REST_Route {
    public function __construct( string $route, string $methods ) { parent::__construct( $route, $methods ); }
}
class Discovery_Narrow_REST extends D\REST_Handler {
    public function __construct( string $namespace, string $basename ) { parent::__construct( $namespace, $basename ); }
}

#[D\REST_Handler( 'review/v1', 'items' )]
class Discovery_Repeated_REST {
    #[D\REST_Route( '/first', 'GET' )]
    #[D\REST_Route( '/second', 'GET' )]
    public function route() {}

    #[D\Ajax_Action( 'first' )]
    #[D\Ajax_Action( 'second' )]
    public function ajax() {}
}

#[\Attribute( \Attribute::IS_REPEATABLE | \Attribute::TARGET_METHOD )]
class Discovery_Repeated_Filter extends D\Filter {}

#[D\Handler( 'init' )]
class Discovery_Repeated_Custom {
    #[Discovery_Repeated_Filter( 'repeat_custom', invoke: D\Filter::INV_PROXIED )]
    #[Discovery_Repeated_Filter( 'repeat_custom', invoke: D\Filter::INV_PROXIED )]
    public function value( $value ) { return $value; }
}
