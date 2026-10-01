<?php
/**
 * Register commands using the real WP-CLI dispatcher in an isolated process.
 *
 * @package XWP\DI\Tests
 */
namespace Tests\XWP\DI\Unit;

use Brain\Monkey\Functions;
use XWP\DI\Container;
use XWP\DI\Decorators\CLI_Command;
use XWP\DI\Decorators\CLI_Handler;
use XWP\DI\Hook\CLI_Callback;
use XWP\DI\Hook\Factory;

final class CLI_Registration_Test extends TestCase {
    public static function registration_orders(): array {
        return array( 'builtin first' => array( false ), 'custom first' => array( true ) );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     * @dataProvider registration_orders
     */
    public function test_builtin_and_custom_handlers_share_namespace_registration( bool $custom_first ): void {
        define( 'WP_CLI', true );
        define( 'WP_CLI_ROOT', dirname( __DIR__, 2 ) . '/vendor/wp-cli/wp-cli' );
        require_once WP_CLI_ROOT . '/php/utils.php';
        require_once WP_CLI_ROOT . '/php/dispatcher.php';
        $config = new \ReflectionProperty( \WP_CLI::get_runner(), 'config' );
        $config->setAccessible( true );
        $config->setValue( \WP_CLI::get_runner(), array( 'debug' => false ) );
        Functions\when( 'current_action' )->justReturn( 'cli_init' );
        $container = new Container( array(
            'app.debug' => false, 'app.id' => 'cli-registration', 'app.env' => 'testing',
            'app.cache' => array( 'app' => false, 'defs' => false, 'hooks' => false, 'dir' => false ),
        ) );
        $additions = 0;
        \WP_CLI::add_hook( 'before_add_command:shared', static function () use ( &$additions ): void { ++$additions; } );
        $metadata = array(
            'type' => CLI_Handler::class,
            'args' => ( new CLI_Handler( 'shared', description: 'Shared commands' ) )->get_declaration(),
            'params' => array( 'classname' => CLI_Registration_Target::class ),
        );
        $runtime = ( new Factory( $container ) )->make( $metadata );
        $runtime->with_target( new CLI_Registration_Target() );
        $runtime->track( 'Review progress', 2 );
        $runtime->tick();
        $runtime->tick();
        $runtime->finish();
        self::assertSame( 'Shared commands', $runtime->description );
        $custom = ( new Custom_CLI_Registration_Handler( \XWP\DI\Definition\HandlerDefinition::from_data( $metadata ), $container ) )->with_target( new CLI_Registration_Target() );
        foreach ( $custom_first ? array( $custom, $runtime ) : array( $runtime, $custom ) as $handler ) {
            $handler->load();
        }
        self::assertSame( 1, $additions );
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_registers_real_command_and_resolves_option_sources(): void {
        define( 'WP_CLI', true );
        define( 'WP_CLI_ROOT', dirname( __DIR__, 2 ) . '/vendor/wp-cli/wp-cli' );
        require_once WP_CLI_ROOT . '/php/utils.php';
        require_once WP_CLI_ROOT . '/php/dispatcher.php';
        $config = new \ReflectionProperty( \WP_CLI::get_runner(), 'config' );
        $config->setAccessible( true );
        $config->setValue( \WP_CLI::get_runner(), array( 'debug' => false ) );
        Functions\when( 'current_action' )->justReturn( 'cli_init' );
        $context = new \ReflectionProperty( \XWP_Context::class, 'current' );
        $context->setAccessible( true );
        $context->setValue( null, CLI_Command::CTX_CLI );
        $container = new Container( array(
            'special.options' => array( 'one', 'two' ),
            'app.debug' => false, 'app.id' => 'cli-registration', 'app.env' => 'testing',
            'app.cache' => array( 'app' => false, 'defs' => false, 'hooks' => false, 'dir' => false ),
        ) );
        $target = new CLI_Registration_Target();
        $handler = ( new Factory( $container ) )->make( array(
            'type' => CLI_Handler::class,
            'args' => ( new CLI_Handler( 'special' ) )->get_declaration(),
            'params' => array( 'classname' => CLI_Registration_Target::class ),
        ) )->with_target( $target );
        $container->set( $handler->get_token(), $handler );
        $decorator = new CLI_Command(
            'registered', args: array( array( 'type' => 'assoc', 'name' => 'choice', 'optional' => true, 'options' => array( 'special.options', 'two', 'three' ) ) ),
            summary: 'Registered command', deferred: true,
        );
        $callback = ( new Factory( $container ) )->make( array(
            'type' => CLI_Command::class,
            'args' => $decorator->get_declaration(),
            'params' => array( 'classname' => CLI_Registration_Target::class, 'method' => 'run' ),
        ) );
        self::assertSame( CLI_Callback::class, $callback::class );
        self::assertTrue( $callback->load() );
        $root = \WP_CLI::get_root_command()->get_subcommands();
        $command = $root['special']->get_subcommands()['registered'];
        self::assertSame( 'Registered command', $command->get_shortdesc() );
        self::assertSame( '[--choice=<choice>]', $command->get_synopsis() );
        foreach ( array( 'one', 'two', 'three' ) as $option ) {
            self::assertSame( 1, substr_count( $command->get_longdesc(), '- ' . $option ) );
        }
        self::assertTrue( $callback->load() );
        self::assertSame( $command, $root['special']->get_subcommands()['registered'] );
    }
}

final class CLI_Registration_Target {
    public function run( array $flags ): void {}
}

final class Custom_CLI_Registration_Handler extends \XWP\DI\Hook\CLI_Handler {
    protected function add_command(): bool {
        return \WP_CLI::add_command( $this->get_namespace(), \XWP_CLI_Namespace::class, array( 'shortdesc' => 'Custom namespace' ) );
    }
}
