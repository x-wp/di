<?php
/**
 * Specialized callback runtime behavior and compatibility views.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Integration;

use XWP\DI\Container;
use XWP\DI\Decorators\Ajax_Action;
use XWP\DI\Decorators\Ajax_Handler;
use XWP\DI\Decorators\Dynamic_Action;
use XWP\DI\Decorators\Dynamic_Filter;
use XWP\DI\Decorators\Filter;
use XWP\DI\Decorators\Handler;
use XWP\DI\Decorators\CLI_Handler;
use XWP\DI\Decorators\CLI_Command;
use XWP\DI\Hook\CLI_Callback;
use XWP\DI\Decorators\REST_Handler;
use XWP\DI\Decorators\REST_Route;
use XWP\DI\Hook\REST_Callback;
use XWP\DI\Hook\Callback;
use XWP\DI\Hook\Factory;
use XWP\DI\Hook\Dynamic_Callback;
use XWP\DI\Hook\Ajax_Callback;

final class Specialized_Callback_Test extends TestCase {
    private Container $container;
    private Specialized_Callback_Target $target;
    private int $original_context;
    private \ReflectionProperty $context;

    public function set_up(): void {
        parent::set_up();
        $this->container = new Container( array(
            'app.debug' => false,
            'app.cache' => array( 'app' => false, 'defs' => false, 'hooks' => false, 'dir' => false ),
            'app.env' => 'testing', 'app.id' => 'specialized-runtime',
        ) );
        $this->target = new Specialized_Callback_Target();
        $this->original_context = \XWP_Context::get();
        $this->context = new \ReflectionProperty( \XWP_Context::class, 'current' );
        $this->context->setAccessible( true );
        $this->context->setValue( null, Filter::CTX_FRONTEND );
    }

    public function tear_down(): void {
        $this->context->setValue( null, $this->original_context );
        parent::tear_down();
    }

    public function test_dynamic_expansion_preserves_mappings_injection_views_and_action_returns(): void {
        foreach ( array( Dynamic_Filter::class, Dynamic_Action::class ) as $type ) {
            $calls = 0;
            $vars = static function () use ( &$calls ): array {
                ++$calls;
                return array( 'one' => 'mapped', 'two' );
            };
            $callback = $this->make( new $type( 'special_%s', $vars, params: array( '!self.hook' ), args: 1 ), 'dynamic' );
            self::assertInstanceOf( Dynamic_Callback::class, $callback );
            self::assertSame( 0, $calls );
            self::assertTrue( $callback->load() );
            self::assertSame( 1, $calls );
            self::assertTrue( $callback->load() );
            self::assertSame( 1, $calls );
            self::assertSame( Dynamic_Filter::class === $type ? 'a:mapped' : null, apply_filters( 'special_one', 'a', 'ignored' ) );
            self::assertSame( Dynamic_Filter::class === $type ? 'b:two' : null, apply_filters( 'special_two', 'b' ) );
            $view = $this->target->view;
            self::assertSame( $type, $view::class );
            self::assertSame( 2, $view->fired );
            self::assertFalse( $view->firing );
            self::assertSame( $callback->get_token(), $view->get_token() );
            self::assertSame( array( $callback, 'invoke' ), $view->target );
            self::assertTrue( remove_filter( 'special_one', $view->target ) );
            self::assertTrue( remove_filter( 'special_two', $view->target ) );
        }
    }

    public function test_dynamic_container_variables_and_inferred_count(): void {
        $this->container->set( 'special.vars', array( 'one' => 'container' ) );
        $callback = $this->make( new Dynamic_Filter( 'special_%s', 'special.vars', params: array( '!self.hook' ) ), 'dynamic' );
        self::assertInstanceOf( Dynamic_Callback::class, $callback );
        self::assertSame( 2, $callback->get_num_args() );
        self::assertTrue( $callback->load() );
        self::assertSame( 'a:container', apply_filters( 'special_one', 'a' ) );
        self::assertTrue( remove_filter( 'special_one', $callback->target ) );
    }

    public function test_ajax_request_parameters_prefixes_and_view_are_owned_by_runtime(): void {
        $this->context->setValue( null, Filter::CTX_AJAX );
        $_GET['special_value'] = 'request';
        try {
            $callback = $this->make( new Ajax_Action( 'fetch', method: 'GET', vars: array( 'special_value' => 'default' ), params: array( '!self.hook' ) ), 'ajax', new Ajax_Handler( 'special' ) );
            self::assertInstanceOf( Ajax_Callback::class, $callback );
            self::assertTrue( $callback->load() );
            foreach ( array( 'wp_ajax_special_fetch', 'wp_ajax_nopriv_special_fetch' ) as $tag ) {
                self::assertSame( 10, has_action( $tag, $callback->target ) );
                do_action( $tag );
                self::assertSame( 'request', $this->target->value );
                self::assertTrue( remove_action( $tag, $callback->target ) );
            }
            self::assertInstanceOf( Ajax_Action::class, $this->target->view );
            self::assertSame( $callback->get_token(), $this->target->view->get_token() );
            self::assertSame( 2, $this->target->view->fired );
            self::assertSame( $callback->get_modifiers(), $this->target->view->get_modifiers() );
            self::assertTrue( $this->target->view->can_load() );
        } finally {
            unset( $_GET['special_value'] );
        }
    }

    public function test_ajax_private_registration_nonce_and_capability_guards(): void {
        $this->context->setValue( null, Filter::CTX_AJAX );
        $callback = $this->make( new Ajax_Action( 'secure', prefix: '', public: false, nonce: array( 'special_nonce' => 'secure' ), cap: 'read', vars: array( 'missing' => 'default' ), params: array( '!self.hook' ) ), 'ajax', new Ajax_Handler( 'ignored' ) );
        self::assertInstanceOf( Ajax_Callback::class, $callback );
        self::assertTrue( $callback->load() );
        self::assertFalse( has_action( 'wp_ajax_nopriv_secure' ) );
        $die = static function (): never { throw new \RuntimeException( 'guard' ); };
        $filter = static fn() => $die;
        add_filter( 'wp_die_handler', $filter );
        try {
            foreach ( array( false, true ) as $valid_nonce ) {
                $_REQUEST['special_nonce'] = $valid_nonce ? wp_create_nonce( 'secure' ) : 'invalid';
                try {
                    $callback->invoke();
                    self::fail( 'Guard must reject the request.' );
                } catch ( \RuntimeException $e ) {
                    self::assertSame( 'guard', $e->getMessage() );
                }
                self::assertFalse( $callback->firing );
            }
            wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
            $_REQUEST['special_nonce'] = wp_create_nonce( 'secure' );
            $callback->invoke();
            self::assertSame( 'default', $this->target->value );
        } finally {
            unset( $_REQUEST['special_nonce'] );
            remove_filter( 'wp_die_handler', $filter );
            remove_all_actions( 'wp_ajax_secure' );
            wp_set_current_user( 0 );
        }
    }

    public function test_rest_registration_schema_guard_response_and_typed_view(): void {
        $this->context->setValue( null, Filter::CTX_REST );
        $callback = $this->make( new REST_Route( 'item', 'GET', vars: 'schema', guard: 'allowed', params: array( '!self.hook' ) ), 'rest', new REST_Handler( 'special/v1', 'items', priority: 18 ) );
        self::assertSame( REST_Callback::class, $callback::class );
        self::assertSame( 'special/v1/items', $callback->get_tag() );
        self::assertSame( 19, $callback->get_priority() );
        self::assertTrue( $callback->load() );
        rest_get_server();
        do_action( 'special/v1/items' );
        $route = rest_get_server()->get_routes()['/special/v1/items/item'][0];
        self::assertSame( array( 'GET' => true ), $route['methods'] );
        self::assertSame( array( 'type' => 'string' ), $route['args']['value'] );
        self::assertSame( array( $this->target, 'allowed' ), $route['permission_callback'] );
        self::assertTrue( ( $route['permission_callback'] )() );
        $request = new \WP_REST_Request( 'GET', '/special/v1/items/item' );
        self::assertSame( array( 'ok' => true ), ( $route['callback'] )( $request ) );
        $view = $this->target->view;
        self::assertInstanceOf( REST_Route::class, $view );
        self::assertSame( $callback->get_token(), $view->get_token() );
        self::assertSame( $callback->get_route(), $view->get_route() );
        self::assertSame( $callback->get_guard(), $view->get_guard() );
        self::assertSame( $callback->get_vars(), $view->get_vars() );
        self::assertSame( 1, $view->fired );
        self::assertFalse( $view->firing );
        self::assertTrue( $view->invoke() );
        self::assertTrue( remove_action( 'special/v1/items', $view->target, 19 ) );
    }

    public function test_rest_standard_callback_and_older_cache_metadata(): void {
        $this->context->setValue( null, Filter::CTX_REST );
        $callback = $this->make( new REST_Route( 'standard', 'GET', invoke: Filter::INV_STANDARD ), 'rest_standard', new REST_Handler( 'special/v1', 'items', priority: 18 ) );
        self::assertTrue( $callback->load() );
        self::assertSame( array( $callback, 'invoke' ), $callback->target );
        self::assertSame( array( $this->target, 'rest_standard' ), $callback->get_callback() );
        self::assertSame( array( 'standard' => true ), ( $callback->get_callback() )( new \WP_REST_Request() ) );
        $data = ( new REST_Route( 'older', 'GET' ) )->with_handler( $callback->get_handler() )->with_method( 'rest_standard' )->get_data();
        unset( $data['params']['tag'], $data['params']['priority'], $data['args']['invoke'] );
        $older = ( new Factory( $this->container ) )->make( $data );
        self::assertSame( $callback->get_token(), $older->get_token() );
        self::assertSame( 19, $older->get_priority() );
        self::assertInstanceOf( \Closure::class, $older->get_callback() );
        self::assertTrue( remove_action( 'special/v1/items', $callback->target, 19 ) );
    }

    public function test_rest_response_preserves_legacy_dispatch_flags_and_resets_state_on_failure(): void {
        $this->context->setValue( null, Filter::CTX_REST );
        $callback = $this->make( new REST_Route( 'failure', 'GET', invoke: Filter::INV_ONCE | Filter::INV_SAFELY ), 'rest_failure', new REST_Handler( 'special/v1', 'items' ) );
        for ( $attempt = 1; $attempt <= 2; ++$attempt ) {
            try {
                ( $callback->get_callback() )();
                self::fail( 'REST response errors must propagate.' );
            } catch ( \RuntimeException $e ) {
                self::assertSame( 'REST failure', $e->getMessage() );
            }
            self::assertSame( $attempt, $callback->fired );
            self::assertFalse( $callback->firing );
        }
    }

    public function test_cli_arguments_flags_hooks_and_typed_view(): void {
        require_once dirname( __DIR__, 2 ) . '/vendor/wp-cli/wp-cli/php/utils.php';
        $this->context->setValue( null, Filter::CTX_CLI );
        $events = array();
        $callback = $this->make( new CLI_Command(
            'fetch',
            args: array(
                array( 'type' => 'positional', 'name' => 'names', 'repeating' => true ),
                array( 'type' => 'assoc', 'name' => 'count', 'default' => 2, 'format' => 'intval' ),
                array( 'type' => 'flag', 'name' => 'verbose', 'default' => true ),
            ),
            params: array( 'view' => '!self.hook' ),
            summary: 'Fetch things', description: array( array( 'wp special fetch a b', 'example' ) ),
            before: static function () use ( &$events ): void { $events[] = 'before'; },
            after: static function () use ( &$events ): void { $events[] = 'after'; },
        ), 'cli', new CLI_Handler( 'special', priority: 21 ) );
        self::assertSame( CLI_Callback::class, $callback::class );
        self::assertSame( 'special fetch', $callback->get_command() );
        self::assertSame( 21, $callback->get_priority() );
        ( $callback->get_before_invoke() )();
        $callback->run_cmd( array( 'a', 'b' ), array( 'count' => '3', 'verbose' => false ) );
        ( $callback->get_after_invoke() )();
        self::assertSame( array( 'before', 'after' ), $events );
        self::assertSame( array( array( 'a', 'b' ), array( 'count' => 3, 'verbose' => false ) ), $this->target->cli_args );
        $view = $this->target->view;
        self::assertInstanceOf( CLI_Command::class, $view );
        self::assertSame( $callback->get_token(), $view->get_token() );
        self::assertSame( $callback->get_priority(), $view->get_priority() );
        self::assertSame( 'special fetch', $view->get_command() );
        self::assertSame( 'Fetch things', $view->get_shortdesc() );
        self::assertStringContainsString( 'wp special fetch a b', $view->get_longdesc() );
        $view->run_cmd( array( 'c' ), array() );
        self::assertSame( array( array( 'c' ), array( 'count' => 2, 'verbose' => true ) ), $this->target->cli_args );
    }

    private function make( Filter $decorator, string $method, ?Handler $handler = null ): mixed {
        $handler ??= new Handler( strategy: Handler::INIT_NOW );
        $handler->with_classname( $this->target::class )->with_container( $this->container )->with_target( $this->target );
        $this->container->set( 'Hook-' . $this->target::class, $handler );
        $decorator->with_handler( $handler )->with_reflector( new \ReflectionMethod( $this->target, $method ) );
        $runtime = ( new Factory( $this->container ) )->make( $decorator->get_data() );
        self::assertSame( $decorator->get_token(), $runtime->get_token() );
        return $runtime;
    }
}

final class Specialized_Callback_Target {
    public Filter $view;
    public string $value;

    public function dynamic( string $value, Dynamic_Filter $view, string $suffix ): string {
        $this->view = $view;
        return $value . ':' . $suffix;
    }

    public function schema(): array {
        return array( 'value' => array( 'type' => 'string' ) );
    }

    public function allowed(): bool {
        return true;
    }

    public function rest( \WP_REST_Request $request, REST_Route $view ): array {
        $this->view = $view;
        return array( 'ok' => true );
    }

    public function rest_standard( \WP_REST_Request $request ): array {
        return array( 'standard' => true );
    }

    public function rest_failure(): never {
        throw new \RuntimeException( 'REST failure' );
    }

    public array $cli_args;

    public function cli( array $names, array $flags, CLI_Command $view ): void {
        $this->cli_args = array( $names, $flags );
        $this->view = $view;
    }

    public function ajax( string $value, Ajax_Action $view ): void {
        $this->value = $value;
        $this->view = $view;
    }
}
