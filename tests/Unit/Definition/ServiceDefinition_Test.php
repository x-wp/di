<?php
/**
 * ServiceDefinition unit tests.
 *
 * @package XWP\DI\Tests
 */

namespace Tests\XWP\DI\Unit\Definition;

use DI\Definition\FactoryDefinition;
use DI\Definition\ObjectDefinition;
use DI\Definition\ValueDefinition;
use Tests\XWP\DI\Unit\TestCase;
use XWP\DI\Definition\ServiceDefinition;

final class ServiceDefinition_Test extends TestCase {
    public function test_autowire_definition_converts_to_php_di_object_definition(): void {
        $definition = ServiceDefinition::autowire( Service_Fixture::class );
        $php_di     = $definition->to_php_di();

        self::assertSame( Service_Fixture::class, $definition->get_id() );
        self::assertSame( ServiceDefinition::KIND_AUTOWIRE, $definition->get_kind() );
        self::assertSame( Service_Fixture::class, $definition->get_class() );
        self::assertFalse( $definition->is_public() );
        self::assertInstanceOf( ObjectDefinition::class, $php_di );
        self::assertSame( Service_Fixture::class, $php_di->getName() );
        self::assertSame( Service_Fixture::class, $php_di->getClassName() );
    }

    public function test_factory_definition_converts_to_php_di_factory_definition(): void {
        $factory = static fn(): Service_Fixture => new Service_Fixture();

        $definition = ServiceDefinition::factory( 'service.id', $factory, true );
        $php_di     = $definition->to_php_di();

        self::assertSame( 'service.id', $definition->get_id() );
        self::assertSame( ServiceDefinition::KIND_FACTORY, $definition->get_kind() );
        self::assertSame( $factory, $definition->get_value() );
        self::assertTrue( $definition->is_public() );
        self::assertInstanceOf( FactoryDefinition::class, $php_di );
    }

    public function test_value_definition_converts_to_php_di_value_definition(): void {
        $definition = ServiceDefinition::value( 'feature.flag', true );
        $php_di     = $definition->to_php_di();

        self::assertSame( 'feature.flag', $definition->get_id() );
        self::assertSame( ServiceDefinition::KIND_VALUE, $definition->get_kind() );
        self::assertTrue( $definition->get_value() );
        self::assertInstanceOf( ValueDefinition::class, $php_di );
        self::assertTrue( $php_di->getValue() );
    }

    public function test_equal_definitions_compare_equal(): void {
        $left  = ServiceDefinition::autowire( Service_Fixture::class );
        $right = ServiceDefinition::autowire( Service_Fixture::class );

        self::assertTrue( $left->equals( $right ) );
    }

    public function test_different_definitions_do_not_compare_equal(): void {
        $left  = ServiceDefinition::autowire( Service_Fixture::class );
        $right = ServiceDefinition::value( Service_Fixture::class, 'different' );

        self::assertFalse( $left->equals( $right ) );
    }
}

final class Service_Fixture {
}
