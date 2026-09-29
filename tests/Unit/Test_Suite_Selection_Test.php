<?php
/** @package XWP\DI\Tests */
namespace Tests\XWP\DI\Unit;

final class Test_Suite_Selection_Test extends TestCase {
    /** @dataProvider suite_arguments */
    public function test_suite_selection( array $args, string $expected ): void {
        require_once dirname( __DIR__ ) . '/bootstrap-suite.php';
        self::assertSame( $expected, xwp_di_test_suite( $args ) );
    }

    public static function suite_arguments(): array {
        return array(
            array( array( '/integration/project/vendor/bin/phpunit' ), 'unit' ),
            array( array( 'phpunit', '--testsuite=unit', '--filter=integration' ), 'unit' ),
            array( array( 'phpunit', '--testsuite', 'integration' ), 'integration' ),
            array( array( 'phpunit', '--testsuite=integration' ), 'integration' ),
            array( array( 'phpunit', dirname( __DIR__ ) . '/Integration/Specialized_Callback_Test.php' ), 'integration' ),
            array( array( 'phpunit', '--filter', 'Integration', dirname( __DIR__ ) . '/Unit/App_Test.php' ), 'unit' ),
        );
    }

    public function test_mixed_suites_require_separate_processes(): void {
        $this->expectException( \InvalidArgumentException::class );
        $this->expectExceptionMessage( 'composer test' );
        xwp_di_test_suite( array( 'phpunit', '--testsuite=unit,integration' ) );
    }
}
