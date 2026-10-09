<?php
/**
 * Install locked, isolated plugin dependencies for the coexistence suite.
 *
 * The modern fixture's relative path repository is resolved from the generated
 * plugin directory. Do not run Composer in the tracked fixture directories.
 * This installer never loads either fixture or the repository's autoloader.
 *
 * Usage: php tests/bin/install-coexistence.php
 *
 * @package XWP\DI\Tests
 */

/** Create an installer-owned output directory. */
function xwp_di_coexistence_directory( string $directory ): void {
    if ( is_link( $directory ) ) {
        throw new RuntimeException( "Refusing to install into a symlink: {$directory}" );
    }
    if ( ! is_dir( $directory ) && ! mkdir( $directory, 0o755, true ) && ! is_dir( $directory ) ) {
        throw new RuntimeException( "Could not create {$directory}" );
    }
}

/** Link fixture sources so edits are picked up by the next isolated run. */
function xwp_di_coexistence_link( string $source, string $destination ): void {
    if ( ! file_exists( $source ) ) {
        throw new RuntimeException( "Missing tracked fixture source: {$source}" );
    }
    if ( is_link( $destination ) && readlink( $destination ) === $source ) {
        return;
    }
    if ( is_link( $destination ) ) {
        if ( ! unlink( $destination ) ) {
            throw new RuntimeException( "Could not replace fixture symlink: {$destination}" );
        }
    } elseif ( file_exists( $destination ) ) {
        throw new RuntimeException( "Refusing to replace a non-symlink fixture source: {$destination}" );
    }
    if ( ! symlink( $source, $destination ) ) {
        throw new RuntimeException( "Could not link {$destination}" );
    }
}

/** Check generated metadata without registering an autoloader. */
function xwp_di_coexistence_verify( string $directory, string $version, string $namespace, string $di_source ): void {
    foreach ( array( 'autoload_packages.php', 'composer/installed.php', 'composer/jetpack_autoload_classmap.php', 'composer/jetpack_autoload_filemap.php' ) as $file ) {
        if ( ! is_file( $directory . '/vendor/' . $file ) ) {
            throw new RuntimeException( "Missing generated loader file: {$directory}/vendor/{$file}" );
        }
    }

    $installed = require $directory . '/vendor/composer/installed.php';
    $classmap  = require $directory . '/vendor/composer/jetpack_autoload_classmap.php';
    $filemap   = require $directory . '/vendor/composer/jetpack_autoload_filemap.php';

    if ( $version !== ( $installed['versions']['x-wp/di']['version'] ?? null )
        || '5.0.1.0' !== ( $installed['versions']['automattic/jetpack-autoloader']['version'] ?? null )
        || $version !== ( $classmap['XWP\\DI\\App_Factory']['version'] ?? null )
    ) {
        throw new RuntimeException( "Unexpected DI or Jetpack package version in {$directory}" );
    }
    if ( realpath( $di_source . '/src/App_Factory.php' ) !== realpath( $classmap['XWP\\DI\\App_Factory']['path'] ) ) {
        throw new RuntimeException( "The DI classmap points outside its expected package: {$directory}" );
    }
    if ( ! isset( $classmap[ $namespace . '\\Root_Module' ] ) ) {
        throw new RuntimeException( "The optimized Jetpack classmap is missing fixture classes: {$directory}" );
    }
    foreach ( $filemap as $entry ) {
        if ( $version === $entry['version'] && str_contains( $entry['path'], '/x-wp/di/' ) ) {
            return;
        }
    }
    throw new RuntimeException( "The Jetpack filemap is missing DI helper files: {$directory}" );
}

$root     = dirname( __DIR__, 2 );
$output   = $root . '/tests/tmp/coexistence';
$composer = getenv( 'COMPOSER_BINARY' ) ?: 'composer';
$fixtures = array(
    'legacy-plugin' => array( 'version' => '1.10.0.0', 'namespace' => 'XWP\\DI\\E2E\\Legacy' ),
    'modern-plugin' => array( 'version' => '2.0.0.0-beta1', 'namespace' => 'XWP\\DI\\E2E\\Modern' ),
);

try {
    xwp_di_coexistence_directory( $output );
    xwp_di_coexistence_directory( $output . '/plugins' );
    if ( false === getenv( 'COMPOSER_CACHE_DIR' ) ) {
        putenv( 'COMPOSER_CACHE_DIR=' . $output . '/composer-cache' );
    }

    foreach ( $fixtures as $name => $fixture ) {
        $source      = $root . '/test/fixtures/coexistence/' . $name;
        $destination = $output . '/plugins/' . $name;
        xwp_di_coexistence_directory( $destination );

        xwp_di_coexistence_link( $source . '/src', $destination . '/src' );
        // Copy entrypoints so __DIR__ resolves next to the isolated vendor tree.
        foreach ( array( 'plugin.php', 'probes.php', 'composer.json', 'composer.lock' ) as $file ) {
            if ( is_link( $destination . '/' . $file ) || ! copy( $source . '/' . $file, $destination . '/' . $file ) ) {
                throw new RuntimeException( "Could not copy fixture {$file} into {$destination}" );
            }
        }

        echo "==> installing locked dependencies for {$name}\n";
        $command = escapeshellarg( $composer ) . ' install --no-dev --prefer-dist --no-interaction --no-progress --optimize-autoloader --working-dir='
            . escapeshellarg( $destination );
        passthru( $command, $status );
        if ( 0 !== $status ) {
            throw new RuntimeException( "Composer failed for {$name} (exit {$status})." );
        }
        if ( hash_file( 'sha256', $source . '/composer.lock' ) !== hash_file( 'sha256', $destination . '/composer.lock' ) ) {
            throw new RuntimeException( "Composer changed the checked-in lockfile for {$name}." );
        }

        $di_source = 'modern-plugin' === $name ? $root : $destination . '/vendor/x-wp/di';
        xwp_di_coexistence_verify( $destination, $fixture['version'], $fixture['namespace'], $di_source );
    }
} catch ( RuntimeException $error ) {
    fwrite( STDERR, $error->getMessage() . "\n" );
    exit( 1 );
}

echo "==> coexistence plugin dependencies ready at {$output}/plugins\n";
