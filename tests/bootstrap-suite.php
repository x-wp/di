<?php
/**
 * Select the bootstrap from PHPUnit's parsed suite or positional test path.
 *
 * @package XWP\DI\Tests
 */

/**
 * Unit and WordPress tests must run in separate processes.
 *
 * @param array<string> $args PHPUnit command arguments, including the executable.
 * @return string
 */
function xwp_di_test_suite( array $args ): string {
    $arguments = ( new \PHPUnit\TextUI\CliArguments\Builder() )->fromParameters( $args, array() );
    if ( $arguments->hasTestSuite() ) {
        $suites = array_map( 'trim', explode( ',', $arguments->testSuite() ) );
        if ( in_array( 'integration', $suites, true ) ) {
            if ( count( $suites ) > 1 ) {
                throw new \InvalidArgumentException( 'Run suites in separate processes with composer test or composer test:coverage.' );
            }
            return 'integration';
        }
        return 'unit';
    }

    if ( $arguments->hasArgument() ) {
        $path = realpath( $arguments->argument() );
        $integration = realpath( __DIR__ . '/Integration' );
        if ( $path && ( $path === $integration || str_starts_with( $path, $integration . DIRECTORY_SEPARATOR ) ) ) {
            return 'integration';
        }
    }

    // Matches phpunit.xml.dist's defaultTestSuite for bare PHPUnit and IDE runs.
    return 'unit';
}
