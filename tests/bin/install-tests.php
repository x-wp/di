<?php
/**
 * Install the test environment.
 *
 * Replaces the legacy SVN-based install-wp-tests.sh:
 *   1. Drops and recreates the test database via PDO.
 *   2. Downloads WordPress core (if missing) into tests/tmp/wordpress.
 *
 * Reads the same env vars as tests/wp-tests-config.php; defaults match
 * docker-compose.test.yml.
 *
 * Usage:
 *   composer test:install
 *   WP_TESTS_DB_HOST=127.0.0.1:33076 php tests/bin/install-tests.php
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';

$env = static fn ( string $name, string $default ): string
    => ( false !== ( $v = getenv( $name ) ) && '' !== $v ) ? $v : $default;

$db_host = $env( 'WP_TESTS_DB_HOST',     '127.0.0.1:33076' );
$db_name = $env( 'WP_TESTS_DB_NAME',     'wp_phpunit_tests' );
$db_user = $env( 'WP_TESTS_DB_USER',     'root' );
$db_pass = $env( 'WP_TESTS_DB_PASSWORD', 'root' );
$wp_ver  = $env( 'WP_VERSION',           'latest' );
$wp_dir  = $env( 'WP_CORE_DIR',          dirname( __DIR__ ) . '/tmp/wordpress' );

[ $host, $port ] = array_pad( explode( ':', $db_host, 2 ), 2, '3306' );

echo "==> resetting test database `{$db_name}` on {$host}:{$port}\n";

try {
    $pdo = new PDO(
        "mysql:host={$host};port={$port}",
        $db_user,
        $db_pass,
        array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION )
    );
    $pdo->exec( "DROP DATABASE IF EXISTS `{$db_name}`" );
    $pdo->exec( "CREATE DATABASE `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );
} catch ( PDOException $e ) {
    fwrite( STDERR, "DB reset failed: {$e->getMessage()}\n" );
    fwrite( STDERR, "Is the docker-compose stack up? Try: composer test:up\n" );
    exit( 1 );
}

echo "==> ensuring WordPress core at `{$wp_dir}`\n";

if ( is_dir( $wp_dir ) && is_file( $wp_dir . '/wp-load.php' ) ) {
    echo "    already present, skipping download\n";
} else {
    if ( ! is_dir( $wp_dir ) && ! mkdir( $wp_dir, 0o755, true ) && ! is_dir( $wp_dir ) ) {
        fwrite( STDERR, "Could not create {$wp_dir}\n" );
        exit( 1 );
    }

    $url = 'latest' === $wp_ver
        ? 'https://wordpress.org/latest.tar.gz'
        : "https://wordpress.org/wordpress-{$wp_ver}.tar.gz";
    $tmp = sys_get_temp_dir() . '/xwpdi-wp-' . uniqid() . '.tar.gz';

    echo "    downloading {$url}\n";
    if ( ! copy( $url, $tmp ) ) {
        fwrite( STDERR, "Download failed: {$url}\n" );
        exit( 1 );
    }

    echo "    extracting into {$wp_dir}\n";
    $rc = 0;
    passthru( sprintf( 'tar -xz --strip-components=1 -C %s -f %s', escapeshellarg( $wp_dir ), escapeshellarg( $tmp ) ), $rc );
    @unlink( $tmp );
    if ( 0 !== $rc ) {
        fwrite( STDERR, "Extraction failed (tar exit code {$rc})\n" );
        exit( 1 );
    }
}

echo "==> ready: run `composer test:integration`\n";
