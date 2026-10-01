<?php
/**
 * Metadata-only attribute coverage.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Unit;

use XWP\DI\Decorators as D;
use XWP\DI\Interfaces\Can_Hook;

class Decorator_Metadata_Test extends TestCase {
    public function test_attributes_do_not_expose_runtime_contracts_or_mutators(): void {
        $attributes = array(
            new D\Handler(), new D\Module( 'init' ), new D\Ajax_Handler(),
            new D\REST_Handler( 'test/v1', 'items' ), new D\CLI_Handler( 'test' ),
            new D\Filter( 'test' ), new D\Action( 'test' ),
            new D\Dynamic_Filter( 'test_%s', array( 'one' ) ),
            new D\Dynamic_Action( 'test_%s', array( 'one' ) ),
            new D\Ajax_Action( 'test' ), new D\REST_Route( 'test', 'GET' ),
            new D\CLI_Command( 'test' ), new D\Infuse( 'service' ),
        );

        foreach ( $attributes as $attribute ) {
            self::assertNotInstanceOf( Can_Hook::class, $attribute );
            foreach ( get_class_methods( $attribute ) as $method ) {
                self::assertStringStartsNotWith( 'with_', $method );
                self::assertNotContains( $method, array( 'load', 'invoke', 'get_data', 'get_container', 'get', 'resolve' ) );
            }
        }
    }

    public function test_callback_metadata_preserves_unresolved_inputs_and_normalizes_tags(): void {
        $priority = static function (): int { throw new \LogicException( 'Do not resolve metadata.' ); };
        $condition = static function (): bool { throw new \LogicException( 'Do not evaluate metadata.' ); };
        $attribute = new D\Filter( '{test}_%s', priority: $priority, conditional: $condition, modifiers: array( 'service' ), args: 0, params: array( '!self.hook' ) );

        self::assertSame( array(
            'conditional' => $condition,
            'context' => 63,
            'modifiers' => array( 'service' ),
            'priority' => $priority,
            'tag' => 'test_%s',
            'args' => 0,
            'invoke' => 1,
            'params' => array( '!self.hook' ),
        ), $attribute->get_declaration() );
    }

    public function test_handler_and_module_defaults_do_not_require_binding(): void {
        self::assertSame( array(
            'conditional' => null, 'context' => 63, 'modifiers' => false,
            'priority' => 10, 'tag' => '', 'hookable' => null, 'strategy' => 'deferred',
        ), ( new D\Handler() )->get_declaration() );
        self::assertSame( array(
            'context' => 63, 'priority' => 10, 'handlers' => array(), 'hook' => 'init',
            'imports' => array(), 'services' => array(), 'strategy' => 'immediately',
        ), ( new D\Module( '{init}', strategy: D\Handler::INIT_NOW ) )->get_declaration() );
    }

    /** @dataProvider nonce_declarations */
    public function test_ajax_nonce_metadata_is_normalized( $nonce, array $expected ): void {
        $metadata = ( new D\Ajax_Action( 'save', prefix: 'test', public: false, nonce: $nonce ) )->get_declaration();
        self::assertSame( $expected, $metadata['nonce'] );
        self::assertFalse( $metadata['public'] );
        self::assertSame( 'REQ', $metadata['method'] );
    }

    public static function nonce_declarations(): array {
        return array(
            array( false, array() ), array( true, array( 'test_save', false ) ),
            array( 'security', array( 'test_save', 'security' ) ),
            array( array( 'security' => 'save' ), array( 'save', 'security' ) ),
            array( array( 'save', 'security' ), array( 'save', 'security' ) ),
        );
    }

    public function test_infuse_replaces_only_the_handler_token(): void {
        self::assertSame( array( 'Hook-test', '!self.hook', 'service' ),
            ( new D\Infuse( '!self.handler', '!self.hook', 'service' ) )->get_tokens( 'Hook-test' ) );
    }
}
