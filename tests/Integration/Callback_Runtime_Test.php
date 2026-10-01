<?php
/**
 * Standalone callback runtime, before Factory routing switches to it.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\Container;
use XWP\DI\Decorators\Action;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Definition\CallbackDefinition;
use XWP\DI\Definition\HandlerDefinition;
use XWP\DI\Hook\Handler as Handler_Runtime;
use XWP\DI\Hook\Callback;

final class Callback_Runtime_Test extends TestCase {
    private Container $container;
    private Callback_Runtime_Target $target;
    private Handler_Runtime $handler;
    private int $original_context;
    private \ReflectionProperty $context;

    public function set_up(): void {
        parent::set_up();
        $this->container = new Container( array(
            'app.debug' => false,
            'app.cache' => array( 'app' => false, 'defs' => false, 'hooks' => false, 'dir' => false ),
            'app.env' => 'testing',
            'app.id' => 'callback-runtime',
            'app.uuid' => 'callback-runtime-test',
        ) );
        $this->target = new Callback_Runtime_Target();
        $this->set_handler( Handler::INIT_NOW, true );
        $this->original_context = \XWP_Context::get();
        $this->context = new \ReflectionProperty( \XWP_Context::class, 'current' );
        $this->context->setAccessible( true );
        $this->context->setValue( null, Filter::CTX_FRONTEND );
    }

    public function tear_down(): void {
        $this->context->setValue( null, $this->original_context );
        unset( $GLOBALS['xwp_runtime_value'] );
        parent::tear_down();
    }

    public function test_standard_registration_preserves_bound_instance_and_argument_count(): void {
        $callback = $this->make_callback( 'pair', array( 'priority' => 17, 'args' => null, 'invoke' => Filter::INV_STANDARD ) );
        $this->container->set( Callback_Runtime_Target::class, new Callback_Runtime_Target() );
        self::assertTrue( $callback->load() );
        self::assertSame( 17, has_filter( 'xwp_runtime', array( $this->target, 'pair' ) ) );
        self::assertSame( 2, $callback->get_num_args() );
        self::assertSame( 'a:b', apply_filters( 'xwp_runtime', 'a', 'b', 'ignored' ) );
        self::assertSame( 1, $this->target->calls );
        self::assertSame( 0, $callback->fired, 'Standard WordPress dispatch bypasses the runtime proxy.' );
        self::assertTrue( remove_filter( 'xwp_runtime', $callback->target, 17 ) );
    }

    /** @dataProvider hook_types */
    public function test_injected_runtime_owns_live_state_invocation_and_removal( string $type, string $method ): void {
        $callback = $this->make_callback( $method, array( 'params' => array( '!self.hook' ), 'priority' => 19 ), $type );
        $this->container->set( $callback->get_token(), $callback );
        $this->target->observe = static function ( Callback $view ) use ( $callback ): void {
            self::assertTrue( $view->firing );
            self::assertSame( $callback->fired, $view->fired );
            self::assertSame( array( $callback, 'invoke' ), $view->target );
        };
        add_action( 'xwp_runtime_attach', static function () use ( $callback ): void {
            self::assertTrue( $callback->load() );
        } );
        do_action( 'xwp_runtime_attach' );

        $expected = Action::class === $type ? null : 'a:changed';
        self::assertSame( $expected, $callback->invoke( 'a' ) );
        $view = $this->target->views[0];
        self::assertSame( Callback::class, $view::class );
        self::assertSame( $callback, $view );
        self::assertSame( $callback, $this->container->get( $view->get_token() ) );
        self::assertSame( $this->handler, $view->get_handler() );
        self::assertSame( $this->container, $view->get_container() );
        self::assertSame( 'xwp_runtime_attach', $view->get_init_hook() );
        self::assertSame( Callback_Runtime_Target::class, $view->get_classname() );
        self::assertSame( $method, $view->get_method() );
        self::assertSame( $method, $view->method );
        self::assertSame( 'xwp_runtime', $view->tag );
        self::assertSame( 19, $view->get_priority() );
        self::assertSame( 1, $view->get_num_args() );
        self::assertTrue( $view->can_load() );
        self::assertSame( 1, $view->fired );
        self::assertFalse( $view->firing );
        self::assertTrue( $view->is_loaded() );
        self::assertTrue( $view->load() );
        self::assertTrue( remove_filter( $view->tag, array( $view, 'invoke' ), 19 ) );
        self::assertFalse( has_filter( $view->tag, $view->target ) );
        self::assertTrue( $view->load() );
        self::assertFalse( has_filter( $view->tag, $view->target ) );
        self::assertSame( $expected, $view->invoke( 'a' ) );
        self::assertSame( $view, $this->target->views[1] );
        self::assertSame( 2, $view->fired );
        self::assertFalse( $view->firing );
    }

    public static function hook_types(): array {
        return array( 'filter' => array( Filter::class, 'filter_view' ), 'action' => array( Action::class, 'action_view' ) );
    }

    public function test_injected_runtime_exposes_its_definition(): void {
        $callback = $this->make_callback( 'filter_view', array(
            'params' => array( '!self.hook' ), 'priority' => 23, 'conditional' => '__return_true',
        ) );
        self::assertSame( 'a:changed', $callback->invoke( 'a' ) );
        $view = $this->target->views[0];
        self::assertSame( array( '!self.hook' ), $view->get_definition()->get_params() );
        self::assertSame( 23, $view->get_definition()->get_priority() );
        self::assertSame( '__return_true', $view->get_definition()->get_conditional() );
        self::assertSame( 1, $view->fired );
        self::assertFalse( $view->firing );
    }

    public function test_proxy_attaches_before_its_condition_becomes_true(): void {
        $allowed = false;
        $callback = $this->make_callback( 'pair', array(
            'tag' => 'xwp_legacy_condition', 'args' => 2, 'invoke' => Filter::INV_PROXIED,
            'conditional' => static function () use ( &$allowed ): bool { return $allowed; },
        ) );
        self::assertTrue( $callback->load() );
        self::assertSame( 'a', apply_filters( 'xwp_legacy_condition', 'a', 'b' ) );
        $allowed = true;
        self::assertSame( 'a:b', apply_filters( 'xwp_legacy_condition', 'a', 'b' ) );
        self::assertSame( 1, $callback->fired );
    }

    public function test_infuse_preserves_handler_tokens_in_each_argument_position(): void {
        $this->container->set( 'review.service', 'service-value' );
        $infuse = new \XWP\DI\Decorators\Infuse( '!self.handler', 'review.service', '!self.handler' );
        $received = $this->container->call(
            static fn( $first, $second, $third ) => array( $first, $second, $third ),
            array_map( '\DI\get', $infuse->get_tokens( $this->handler->get_token() ) ),
        );
        self::assertSame( array( $this->handler, 'service-value', $this->handler ), $received );
    }

    public function test_runtime_normalizes_null_priority_metadata(): void {
        $data = $this->make_callback( 'filter_view', array( 'params' => array( '!self.hook' ) ) )->get_definition()->get_data();
        $data['args']['priority'] = null;
        $callback = new Callback( CallbackDefinition::from_data( $data ), $this->container );
        self::assertSame( 'a:changed', $callback->invoke( 'a' ) );
        self::assertSame( 10, $this->target->views[0]->get_priority() );
    }

    public function test_repeated_definitions_keep_independent_runtimes_and_counters(): void {
        $first = $this->make_callback( 'filter_view', array( 'params' => array( '!self.hook' ) ) );
        $second = $this->make_callback( 'filter_view', array( 'tag' => 'xwp_runtime_second', 'params' => array( '!self.hook' ) ) );
        $first->invoke( 'first' );
        $second->invoke( 'second' );
        $first->invoke( 'third' );
        self::assertNotSame( $first->get_token(), $second->get_token() );
        self::assertNotSame( $this->target->views[0], $this->target->views[1] );
        self::assertSame( $this->target->views[0], $this->target->views[2] );
        self::assertSame( 2, $this->target->views[0]->fired );
        self::assertSame( 1, $this->target->views[1]->fired );
    }

    public function test_context_gates_attachment_and_condition_gates_proxy_invocation(): void {
        $checks = 0;
        $allowed = false;
        $callback = $this->make_callback( 'run', array(
            'context' => Filter::CTX_ADMIN,
            'conditional' => static function () use ( &$checks, &$allowed ): bool {
                ++$checks;
                return $allowed;
            },
        ) );
        self::assertFalse( $callback->load() );
        self::assertSame( 0, $checks );
        $this->context->setValue( null, Filter::CTX_ADMIN );
        self::assertTrue( $callback->load() );
        self::assertSame( 0, $checks );
        $allowed = true;
        self::assertTrue( $callback->load() );
        self::assertTrue( $callback->load() );
        self::assertSame( 0, $checks );
        $allowed = false;
        self::assertSame( 'a', apply_filters( 'xwp_runtime', 'a' ) );
        self::assertSame( 1, $checks );
        self::assertSame( 0, $callback->fired );
        $allowed = true;
        self::assertSame( 'a:changed', apply_filters( 'xwp_runtime', 'a' ) );
        self::assertSame( 2, $checks );
        self::assertSame( 1, $callback->fired );
        $this->context->setValue( null, Filter::CTX_FRONTEND );
        self::assertSame( 'b', apply_filters( 'xwp_runtime', 'b' ) );
        self::assertSame( 2, $checks );
        self::assertSame( 1, $callback->fired );
    }

    /** @dataProvider lazy_strategies */
    public function test_lazy_initialization_timing_and_rejected_attempts_can_retry( string $strategy ): void {
        $this->set_handler( $strategy, false );
        $attempts = 0;
        $allowed = false;
        $tag = $this->handler->get_lazy_tag();
        add_action( $tag, function ( Handler_Runtime $handler ) use ( &$attempts, &$allowed ): void {
            ++$attempts;
            if ( $allowed ) {
                $handler->with_target( $this->target );
            }
        } );
        $callback = $this->make_callback( 'run', array( 'invoke' => Filter::INV_STANDARD ) );
        self::assertSame( array( $callback, 'invoke' ), $callback->target, 'Reading target must apply the lazy proxy override.' );
        self::assertSame( 0, $attempts );
        $jit = Handler::INIT_JIT === $strategy;
        self::assertSame( $jit, $callback->load() );
        self::assertSame( $jit ? 0 : 1, $attempts );
        if ( $jit ) {
            self::assertSame( 'a', apply_filters( 'xwp_runtime', 'a' ) );
            self::assertSame( 1, $attempts );
            self::assertSame( 0, $callback->fired );
        }
        $allowed = true;
        self::assertTrue( $callback->load() );
        self::assertSame( $jit ? 1 : 2, $attempts );
        self::assertSame( 'a:changed', apply_filters( 'xwp_runtime', 'a' ) );
        self::assertSame( 2, $attempts );
        self::assertSame( 'b:changed', apply_filters( 'xwp_runtime', 'b' ) );
        self::assertSame( 2, $attempts );
        self::assertSame( Filter::INV_STANDARD, $callback->get_definition()->get_invoke(), 'Effective flags do not mutate the definition.' );
    }

    public static function lazy_strategies(): array {
        return array( 'lazy' => array( Handler::INIT_LAZY ), 'jit' => array( Handler::INIT_JIT ) );
    }

    public function test_uninitialized_non_lazy_handler_cannot_attach_or_execute(): void {
        $this->set_handler( Handler::INIT_AUTO, false );
        $callback = $this->make_callback( 'run' );
        self::assertFalse( $callback->can_load() );
        self::assertFalse( $callback->load() );
        self::assertSame( 'a', $callback->invoke( 'a' ) );
        self::assertSame( 0, $callback->fired );
        $this->handler->with_target( $this->target );
        self::assertTrue( $callback->load() );
        self::assertSame( 'a:changed', apply_filters( 'xwp_runtime', 'a' ) );
    }

    /** @dataProvider hook_types */
    public function test_once_skips_without_incrementing_state( string $type, string $method ): void {
        $callback = $this->make_callback( $method, array(
            'invoke' => Filter::INV_PROXIED | Filter::INV_ONCE,
            'params' => array( '!self.hook' ),
        ), $type );
        self::assertSame( Action::class === $type ? null : 'a:changed', $callback->invoke( 'a' ) );
        self::assertSame( Action::class === $type ? null : 'b', $callback->invoke( 'b' ) );
        self::assertSame( 1, $callback->fired );
        self::assertSame( 1, $this->target->views[0]->fired );
        self::assertFalse( $callback->firing );
    }

    public function test_loop_guard_preserves_outer_invocation_state(): void {
        $callback = $this->make_callback( 'filter_view', array(
            'invoke' => Filter::INV_PROXIED | Filter::INV_LOOPED,
            'params' => array( '!self.hook' ),
        ) );
        $this->target->observe = static function ( Callback $view ): void {
            self::assertTrue( $view->firing );
            self::assertSame( 'nested', $view->invoke( 'nested' ) );
            self::assertTrue( $view->firing );
            self::assertSame( 0, $view->fired );
        };
        self::assertSame( 'outer:changed', $callback->invoke( 'outer' ) );
        self::assertSame( 1, $callback->fired );
        self::assertFalse( $callback->firing );
    }

    /** @dataProvider failures */
    public function test_exception_state_and_safe_fallback( string $type, bool $safe ): void {
        $callback = $this->make_callback( 'fail', array(
            'invoke' => Filter::INV_PROXIED | ( $safe ? Filter::INV_SAFELY : 0 ),
        ), $type );
        $log = tempnam( sys_get_temp_dir(), 'xwp-callback-log-' );
        $old_log = ini_set( 'error_log', $log );
        try {
            try {
                $result = $callback->invoke( 'original' );
                self::assertTrue( $safe, 'Unsafe exceptions must propagate.' );
                self::assertSame( Action::class === $type ? null : 'original', $result );
            } catch ( \RuntimeException $exception ) {
                self::assertFalse( $safe );
                self::assertSame( $this->target->failure, $exception );
            }
            self::assertSame( 1, $callback->fired );
            self::assertFalse( $callback->firing );
            if ( $safe ) {
                self::assertStringContainsString( 'fixture failure', file_get_contents( $log ) );
                self::assertStringContainsString( 'xwp_runtime', file_get_contents( $log ) );
            }
        } finally {
            ini_set( 'error_log', $old_log );
            unlink( $log );
        }
    }

    public static function failures(): array {
        return array(
            'filter unsafe' => array( Filter::class, false ),
            'filter safe' => array( Filter::class, true ),
            'action unsafe' => array( Action::class, false ),
            'action safe' => array( Action::class, true ),
        );
    }

    public function test_parameter_tokens_resolve_live_values_and_preserve_order(): void {
        $service = new \stdClass();
        $this->container->set( 'runtime.service', $service );
        $GLOBALS['xwp_runtime_value'] = 'global';
        $callback = $this->make_callback( 'collect', array( 'params' => array(
            '!self.handler', '!self.hook', '!value:literal', '!global:xwp_runtime_value',
            '!const:PHP_INT_MAX', 'runtime.service', 'unknown-token',
        ) ) );
        self::assertSame( 'input', $callback->invoke( 'input' ) );
        $args = $this->target->arguments;
        self::assertSame( $this->handler, $args[0] );
        self::assertSame( $callback, $args[1] );
        self::assertSame( array( 'literal', 'global', PHP_INT_MAX, $service, 'unknown-token' ), array_slice( $args, 2 ) );
        $GLOBALS['xwp_runtime_value'] = 'changed';
        $callback->invoke( 'input' );
        self::assertSame( 'changed', $this->target->arguments[3] );
        self::assertSame( $args[1], $this->target->arguments[1] );
    }

    public function test_dynamic_tag_and_callable_priority_use_raw_metadata(): void {
        $this->container->set( 'runtime.suffix', 'one' );
        $callback = $this->make_callback( 'run', array(
            'tag' => 'xwp_runtime_{%s}',
            'modifiers' => 'runtime.suffix',
            'priority' => static function ( string $tag ): int {
                self::assertSame( 'xwp_runtime_%s', $tag );
                return 23;
            },
        ) );
        self::assertSame( 'xwp_runtime_one', $callback->get_tag() );
        self::assertSame( 23, $callback->get_priority() );
        self::assertTrue( $callback->load() );
        self::assertSame( 23, has_filter( 'xwp_runtime_one', array( $callback, 'invoke' ) ) );
        self::assertSame( 'a:changed', apply_filters( 'xwp_runtime_one', 'a' ) );
        $this->container->set( 'runtime.suffix', 'two' );
        self::assertSame( 'xwp_runtime_two', $callback->get_tag() );
        self::assertSame( 'xwp_runtime_%s', $callback->get_definition()->get_tag() );
    }

    /** @dataProvider priorities */
    public function test_priority_forms_remain_runtime_values( mixed $priority, int $expected ): void {
        $data = $this->make_callback( 'run' )->get_definition()->get_data();
        $data['args']['priority'] = $priority;
        $callback = new Callback( CallbackDefinition::from_data( $data ), $this->container );
        self::assertSame( $expected, $callback->get_priority() );
        self::assertSame( $priority, $callback->get_definition()->get_priority() );
    }

    public static function priorities(): array {
        return array(
            'default' => array( null, 10 ),
            'zero' => array( 0, 0 ),
            'negative' => array( -7, -7 ),
            'numeric string' => array( '23', 23 ),
            'constant' => array( 'PHP_INT_MAX', PHP_INT_MAX ),
            'filter default' => array( 'xwp_runtime_priority:15', 15 ),
            'filter without default' => array( 'xwp_runtime_priority', 10 ),
            'callable string' => array( Callback_Runtime_Target::class . '::priority', 22 ),
            'callable array' => array( array( Callback_Runtime_Target::class, 'priority' ), 22 ),
            'container method' => array( array( Callback_Runtime_Target::class, 'instance_priority' ), 24 ),
        );
    }

    public function test_priority_filter_receives_raw_tag_and_default(): void {
        add_filter( 'xwp_runtime_priority', static function ( $default, string $tag ): int {
            self::assertSame( '15', $default );
            self::assertSame( 'xwp_runtime_%s', $tag );
            return 21;
        }, 10, 2 );
        $callback = $this->make_callback( 'run', array(
            'tag' => 'xwp_runtime_%s', 'modifiers' => '!value:suffix', 'priority' => 'xwp_runtime_priority:15',
        ) );
        self::assertSame( 21, $callback->get_priority() );
    }

    public function test_container_resolves_typed_dependencies_after_wordpress_arguments(): void {
        $dependency = new Callback_Runtime_Dependency();
        $this->container->set( Callback_Runtime_Dependency::class, $dependency );
        $callback = $this->make_callback( 'typed' );
        self::assertSame( 'value', $callback->invoke( 'value' ) );
        self::assertSame( array( $dependency ), $this->target->arguments );
    }

    public function test_zero_arguments_and_static_method_dispatch(): void {
        $zero = $this->make_callback( 'zero', array( 'args' => 0 ) );
        self::assertTrue( $zero->load() );
        self::assertSame( 'zero', apply_filters( 'xwp_runtime', 'discarded' ) );
        self::assertSame( 0, $zero->get_num_args() );
        $static = $this->make_callback( 'static_value', array( 'tag' => 'xwp_runtime_static' ) );
        self::assertSame( 'value:static', $static->invoke( 'value' ) );
    }

    private function set_handler( string $strategy, bool $loaded ): void {
        $this->handler = new Handler_Runtime( new HandlerDefinition( Callback_Runtime_Target::class, strategy: $strategy ), $this->container );
        if ( $loaded ) {
            $this->handler->with_target( $this->target );
        }
        $this->container->set( $this->handler->get_token(), $this->handler );
    }

    private function make_callback( string $method, array $args = array(), string $type = Filter::class ): Callback {
        $data = array(
            'type' => $type,
            'args' => ( new $type( ...array_replace( array( 'tag' => 'xwp_runtime', 'invoke' => Filter::INV_PROXIED, 'args' => 1 ), $args ) ) )->get_declaration(),
            'params' => array( 'classname' => Callback_Runtime_Target::class, 'method' => $method ),
        );
        return new Callback( CallbackDefinition::from_data( $data ), $this->container );
    }
}

final class Callback_Runtime_Target {
    public array $views = array();
    public array $arguments = array();
    public int $calls = 0;
    public ?\Closure $observe = null;
    public ?\RuntimeException $failure = null;

    public function pair( string $first, string $second ): string {
        ++$this->calls;
        return $first . ':' . $second;
    }

    public function run( string $value ): string {
        ++$this->calls;
        return $value . ':changed';
    }

    public function filter_view( string $value, Callback $view ): string {
        $this->views[] = $view;
        if ( $this->observe ) {
            ( $this->observe )( $view );
        }
        return $value . ':changed';
    }

    public function action_view( string $value, Callback $view ): string {
        return $this->filter_view( $value, $view );
    }

    public function fail( string $value ): string {
        $this->failure = new \RuntimeException( 'fixture failure' );
        throw $this->failure;
    }

    public function collect( string $value, mixed ...$args ): string {
        $this->arguments = $args;
        return $value;
    }

    public function zero(): string {
        return 'zero';
    }

    public function typed( string $value, Callback_Runtime_Dependency $dependency ): string {
        $this->arguments = array( $dependency );
        return $value;
    }

    public static function priority( string $tag ): int {
        Callback_Runtime_Test::assertSame( 'xwp_runtime', $tag );
        return 22;
    }

    public function instance_priority( string $tag ): int {
        Callback_Runtime_Test::assertSame( 'xwp_runtime', $tag );
        return 24;
    }

    public static function static_value( string $value ): string {
        return $value . ':static';
    }
}

final class Callback_Runtime_Dependency {}
