<?php
/** @package XWP\DI\Tests */
namespace Tests\XWP\DI\Unit\Definition;

use PHPUnit\Framework\TestCase;
use XWP\DI\Hook\Parser;

final class Parser_Extensions_Test extends TestCase {
    public function test_extensions_merge_arrays_and_replace_scalar_or_service_definitions(): void {
        $parser = new Parser( Parser_Extension_Fixture::class, 'extension-test' );
        $method = new \ReflectionMethod( $parser, 'merge_definition' );
        $method->setAccessible( true );
        $base = array(
            'scalar' => 'base', 'array' => array( 'old' => true ),
            'service' => \DI\value( 'base' ), 'replace_array' => array( 'old' ),
            'replace_scalar' => 'base',
        );
        $result = $method->invoke( $parser, $base, array(
            'id' => 'review', 'module' => Parser_Extension_Fixture::class,
            'file' => false, 'version' => '1.0', 'type' => 'plugin',
        ) );
        self::assertSame( 'extension', $result['scalar'] );
        self::assertSame( array( 'old' => true, 'new' => true ), $result['array'] );
        self::assertSame( 'replacement', $result['replace_array'] );
        self::assertSame( array( 'replacement' ), $result['replace_scalar'] );
        self::assertSame( 'extension', $result['service']->getValue() );
        self::assertSame( 'new', $result['new'] );
    }
}

final class Parser_Extension_Fixture {
    public static function define(): array {
        return array(
            'scalar' => 'extension', 'array' => array( 'new' => true ),
            'service' => \DI\value( 'extension' ), 'replace_array' => 'replacement',
            'replace_scalar' => array( 'replacement' ), 'new' => 'new',
        );
    }
}
