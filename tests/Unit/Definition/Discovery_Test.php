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
use XWP\DI\Utils\Reflection;

final class Discovery_Test extends TestCase {
    public function test_narrow_specialized_constructors_accept_exported_metadata(): void {
        foreach ( array( new Discovery_Narrow_Ajax( 'action' ), new Discovery_Narrow_Route( '/route', 'GET' ) ) as $callback ) {
            $data = array( 'type' => $callback::class, 'args' => $callback->get_declaration(), 'params' => array( 'classname' => Discovery_Repeated_Host::class, 'method' => 'value' ) );
            self::assertSame( $data, CallbackDefinition::from_data( $data )->get_data() );
        }
        $handler = new Discovery_Narrow_REST( 'review/v1', 'items' );
        $data = array( 'type' => $handler::class, 'args' => $handler->get_declaration(), 'params' => array( 'classname' => Discovery_Repeated_Host::class ) );
        self::assertSame( $data, HandlerDefinition::from_data( $data )->get_data() );
    }

    public function test_factory_routes_custom_specialized_declarations_to_runtimes(): void {
        $container = new \XWP\DI\Container( array(
            'app.debug' => false, 'app.id' => 'discovery-runtime', 'app.env' => 'testing',
            'app.cache' => array( 'app' => false, 'defs' => false, 'hooks' => false, 'dir' => false ),
        ) );
        $factory = new Factory( $container );
        $cases = array(
            array( new Discovery_Narrow_Ajax( 'action' ), \XWP\DI\Hook\Ajax_Callback::class ),
            array( new Discovery_Narrow_Route( '/route', 'GET' ), \XWP\DI\Hook\REST_Callback::class ),
            array( new Discovery_Narrow_Command( 'command' ), \XWP\DI\Hook\CLI_Callback::class ),
            array( new Discovery_Custom_Dynamic_Action( 'dynamic_%s', array( 'one' ) ), \XWP\DI\Hook\Dynamic_Callback::class ),
        );
        foreach ( $cases as list( $attribute, $runtime_class ) ) {
            $data = array(
                'type' => $attribute::class,
                'args' => $attribute->get_declaration(),
                'params' => array( 'classname' => Discovery_Repeated_Host::class, 'method' => 'value', 'tag' => 'review/v1/items', 'priority' => 11 ),
            );
            $definition = CallbackDefinition::from_data( $data );
            $runtime = $factory->make( $data );
            self::assertSame( $runtime_class, $runtime::class );
            self::assertSame( $definition->get_token(), $runtime->get_token() );
            self::assertSame( 'action', $runtime->get_definition()->get_type() );
            self::assertSame( $data, $runtime->get_definition()->get_data() );
        }
        foreach ( array(
            array( new Discovery_Custom_Ajax_Handler( 'prefix' ), \XWP\DI\Hook\Ajax_Handler::class ),
            array( new Discovery_Narrow_REST( 'review/v1', 'items' ), \XWP\DI\Hook\REST_Handler::class ),
            array( new Discovery_Custom_CLI_Handler( 'command' ), \XWP\DI\Hook\CLI_Handler::class ),
        ) as list( $attribute, $runtime_class ) ) {
            $data = array( 'type' => $attribute::class, 'args' => $attribute->get_declaration(), 'params' => array( 'classname' => Discovery_Repeated_Host::class ) );
            $runtime = $factory->make( $data );
            self::assertSame( $runtime_class, $runtime::class );
            self::assertSame( HandlerDefinition::from_data( $data )->get_token(), $runtime->get_token() );
            self::assertSame( $data, $runtime->get_definition()->get_data() );
        }
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
            self::assertSame( $tokens, array_map( static fn( $cb ) => CallbackDefinition::from_data( $cb->get_data() )->get_token(), $callbacks ) );
            self::assertSame( $tokens, array_map( static fn( $cb ) => $cb->get_token(), $factory->resolve_callbacks( $factory->resolve_handler( $class ) ) ) );
        }
    }

    public function test_repeated_callback_ids_are_unique_and_survive_metadata_round_trips(): void {
        $factory = new Factory();
        $callbacks = $factory->resolve_callbacks( $factory->resolve_handler( Discovery_Repeated_Host::class ) );
        $tokens = array_map( static fn( $cb ) => $cb->get_token(), $callbacks );
        self::assertCount( 2, array_unique( $tokens ) );
        self::assertSame( $tokens, array_map( static fn( $cb ) => CallbackDefinition::from_data( $cb->get_data() )->get_token(), $callbacks ) );
    }

    public function test_missing_handler_uses_the_legacy_exception_contract(): void {
        $this->expectException( \InvalidArgumentException::class );
        ( new Factory() )->resolve_handler( 'Missing_Discovery_Review_Class' );
    }

    public function test_custom_narrow_module_constructor_can_round_trip(): void {
        $factory = new Factory();
        $module = $factory->resolve_module( Discovery_Narrow_Module_Host::class );
        $copy = $factory->make( $module->get_data() );
        self::assertInstanceOf( \XWP\DI\Hook\Module::class, $copy );
        self::assertSame( $module->get_data(), $copy->get_definition()->get_data() );
    }

    /** @dataProvider handlers */
    public function test_handler_discovery_returns_definition_with_declaration_metadata( string $class ): void {
        $actual = ( new Factory() )->resolve_handler( $class );
        self::assertInstanceOf( HandlerDefinition::class, $actual );
        $attribute = Reflection::get_decorator( new \ReflectionClass( $class ), D\Handler::class );
        self::assertSame( $attribute->get_declaration(), $actual->get_data()['args'] );
        self::assertSame( 'Hook-' . $class, $actual->get_token() );
    }

    public function test_custom_callbacks_are_definitions_and_keep_custom_declarations(): void {
        $factory = new Factory();
        $callbacks = $factory->resolve_callbacks( $factory->resolve_handler( Discovery_Custom_Host::class ) );
        self::assertCount( 2, $callbacks );
        self::assertInstanceOf( CallbackDefinition::class, $callbacks[0] );
        self::assertSame( Discovery_Custom_Filter::class, $callbacks[0]->get_decorator() );
        self::assertSame( 23, $callbacks[0]->get_priority() );
        self::assertSame( 0, $callbacks[0]->get_accepted_args() );
    }

    public function test_custom_module_strategy_and_lazy_metadata_are_preserved(): void {
        $factory = new Factory();
        $module = $factory->resolve_module( Discovery_Custom_Module_Host::class );
        self::assertInstanceOf( HandlerDefinition::class, $module );
        self::assertSame( D\Handler::INIT_NOW, $module->get_strategy() );
        $handler = $factory->resolve_handler( Discovery_Custom_Lazy_Host::class );
        self::assertSame( D\Filter::INV_STANDARD, $factory->resolve_callbacks( $handler )[0]->get_invoke() );
    }

    public function test_infuse_exports_tokens_without_handler_binding(): void {
        self::assertSame( array( 'service' ), ( new D\Infuse( 'service' ) )->get_tokens( 'unused' ) );
    }

    public function test_parser_serializes_custom_declarations_and_builtin_callback_ids(): void {
        $raw = ( new \XWP\DI\Hook\Parser( Discovery_Mixed_Module::class, 'discovery' ) )->make( true )->get_raw();
        $custom = $raw['values']['Hook-' . Discovery_Custom_Host::class . '[params]'];
        self::assertCount( 2, $custom['params']['callbacks'] );
        $builtin = $raw['values']['Hook-' . Discovery_Handler::class . '[params]'];
        self::assertSame( array( 'Hook-' . Discovery_Handler::class . '::inherited[discovery]' ), $builtin['params']['callbacks'] );
        self::assertSame( D\Handler::class, $builtin['type'] );
        self::assertNull( ( new Factory() )->resolve_handler( Discovery_Handler::class )->get_callbacks() );
    }

    public function test_custom_infuse_tokens_are_captured_once_during_discovery(): void {
        Discovery_Custom_Infuse::$calls = 0;
        $handler = ( new Factory() )->resolve_handler( Discovery_Infuse_Host::class );
        self::assertInstanceOf( HandlerDefinition::class, $handler );
        self::assertSame( 1, Discovery_Custom_Infuse::$calls );
        self::assertSame( array( $handler->get_token(), 'custom_option' ), $handler->get_data()['params']['params']['on_initialize'] );
        self::assertSame( 1, Discovery_Custom_Infuse::$calls );
    }

    /** @dataProvider legacy_types */
    public function test_legacy_overrides_are_rejected_when_restoring_cache( string $type, string $method ): void {
        $this->expectException( \DI\Definition\Exception\InvalidDefinition::class );
        $this->expectExceptionMessage( $method );
        ( new Factory() )->make( array( 'type' => $type, 'args' => array( 'tag' => 'legacy' ), 'params' => array( 'classname' => Discovery_Repeated_Host::class, 'method' => 'value' ) ) );
    }

    public static function legacy_types(): array {
        return array(
            array( Discovery_Legacy_Filter::class, 'with_handler' ),
            array( Discovery_Legacy_Child::class, 'get_data' ),
            array( Discovery_Legacy_Handler::class, 'is_lazy' ),
            array( Discovery_Legacy_Condition::class, 'check_method' ),
        );
    }

    public function test_legacy_callback_override_is_rejected_during_discovery(): void {
        $factory = new Factory();
        $handler = $factory->resolve_handler( Discovery_Legacy_Host::class );
        $this->expectException( \DI\Definition\Exception\InvalidDefinition::class );
        $this->expectExceptionMessage( 'with_handler' );
        $factory->resolve_callbacks( $handler );
    }

    public function test_legacy_infuse_override_is_rejected_during_discovery(): void {
        $this->expectException( \DI\Definition\Exception\InvalidDefinition::class );
        $this->expectExceptionMessage( 'get' );
        ( new Factory() )->resolve_handler( Discovery_Legacy_Infuse_Host::class );
    }

    public function test_parser_rejects_legacy_overrides_in_cached_metadata(): void {
        $this->expectException( \DI\Definition\Exception\InvalidDefinition::class );
        $this->expectExceptionMessage( 'get_data' );
        ( new \XWP\DI\Hook\Parser( Discovery_Module::class, 'legacy' ) )->load( array(
            'values' => array( 'legacy[params]' => array( 'type' => Discovery_Legacy_Child::class, 'args' => array(), 'params' => array() ) ),
        ) );
    }

    public function test_cached_handler_rejects_legacy_infuse_override(): void {
        $this->expectException( \DI\Definition\Exception\InvalidDefinition::class );
        $this->expectExceptionMessage( 'get' );
        ( new Factory() )->make( array(
            'type' => D\Handler::class,
            'args' => array( 'tag' => 'init' ),
            'params' => array( 'classname' => Discovery_Legacy_Infuse_Host::class, 'params' => array( 'on_initialize' => array() ) ),
        ) );
    }

    public function test_runtime_parameter_lookup_rejects_legacy_infuse_override(): void {
        $handler = new \XWP\DI\Hook\Handler( new HandlerDefinition( Discovery_Legacy_Parameter_Host::class ) );
        $this->expectException( \DI\Definition\Exception\InvalidDefinition::class );
        $this->expectExceptionMessage( 'get' );
        $handler->get_params( 'can_initialize' );
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
        self::assertCount( 7, $actual );
        foreach ( $actual as $definition ) {
            self::assertInstanceOf( CallbackDefinition::class, $definition );
            self::assertSame( Discovery_REST::class, $definition->get_handler() );
            self::assertSame( $definition->get_data(), CallbackDefinition::from_data( $definition->get_data() )->get_data() );
        }
        self::assertSame( 'discovery/v1/items', $actual[5]->get_tag() );
        self::assertSame( 20, $actual[5]->get_priority() );
        self::assertSame( 1, $actual[2]->get_accepted_args() );
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
    public function get_declaration(): array {
        return array_replace( parent::get_declaration(), array( 'priority' => 23 ) );
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
    public function get_declaration(): array {
        return array_replace( parent::get_declaration(), array( 'strategy' => self::INIT_NOW ) );
    }
}

#[Discovery_Custom_Module( 'init' )]
class Discovery_Custom_Module_Host {}

#[\Attribute( \Attribute::TARGET_CLASS )]
class Discovery_Custom_Lazy extends D\Handler {
    public function get_declaration(): array {
        return array_replace( parent::get_declaration(), array( 'strategy' => self::INIT_NOW ) );
    }
}

#[Discovery_Custom_Lazy( 'init', strategy: D\Handler::INIT_LAZY )]
class Discovery_Custom_Lazy_Host extends Discovery_Parent {}

#[D\Module( 'init', handlers: array( Discovery_Custom_Host::class, Discovery_Handler::class ) )]
class Discovery_Mixed_Module {}

#[\Attribute( \Attribute::TARGET_METHOD )]
class Discovery_Custom_Infuse extends D\Infuse {
    public static int $calls = 0;

    public function get_tokens( string $handler_token ): array {
        ++self::$calls;
        return array( $handler_token, 'custom_option' );
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

#[\Attribute( \Attribute::TARGET_METHOD )]
class Discovery_Legacy_Filter extends D\Filter {
    public function with_handler( Can_Handle $handler ): static { return $this; }
}
class Discovery_Legacy_Parent extends D\Filter {
    public function get_data(): array { return array(); }
}
class Discovery_Legacy_Child extends Discovery_Legacy_Parent {}
class Discovery_Legacy_Handler extends D\Handler {
    public function is_lazy(): bool { return false; }
}
#[D\Handler( 'init' )]
class Discovery_Legacy_Host {
    #[Discovery_Legacy_Filter( 'legacy' )]
    public function value() {}
}
#[\Attribute( \Attribute::TARGET_METHOD )]
class Discovery_Legacy_Infuse extends D\Infuse {
    public function get( Can_Handle $handler ) { return array(); }
}
#[D\Handler( 'init' )]
class Discovery_Legacy_Infuse_Host {
    #[Discovery_Legacy_Infuse()]
    public function on_initialize() {}
}

class Discovery_Legacy_Condition extends D\Filter {
    protected function check_method( null|\Closure|string|array $method ): bool { return false; }
}

class Discovery_Legacy_Parameter_Host {
    #[Discovery_Legacy_Infuse()]
    public function can_initialize() {}
}

class Discovery_Narrow_Command extends D\CLI_Command {
    public function __construct( string $command ) { parent::__construct( $command ); }
}
class Discovery_Custom_Dynamic_Action extends D\Dynamic_Action {}
class Discovery_Custom_Ajax_Handler extends D\Ajax_Handler {}
class Discovery_Custom_CLI_Handler extends D\CLI_Handler {}
