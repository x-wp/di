<?php
/**
 * Install the test environment.
 *
 * Replaces the legacy SVN-based install-wp-tests.sh:
 *   1. Downloads WordPress core (if missing) into tests/tmp/wordpress.
 *   2. Installs SQLite Database Integration and resets the disposable database,
 *      or drops and recreates the MySQL test database when explicitly selected.
 *
 * Reads the same configuration as wp-phpunit. SQLite needs no database server.
 *
 * Usage:
 *   composer test:install
 *   WP_TESTS_DB_ENGINE=mysql composer test:install
 */

require_once dirname( __DIR__, 2 ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/wp-tests-config.php';

$env = static fn ( string $name, string $default ): string
    => ( false !== ( $v = getenv( $name ) ) && '' !== $v ) ? $v : $default;

$wp_ver  = $env( 'WP_VERSION',           'latest' );
$wp_dir  = rtrim( WP_CORE_DIR, '/' );

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

if ( 'sqlite' === DB_ENGINE ) {
    $sqlite_version = '3.0.2';
    $plugin_dir     = $wp_dir . '/wp-content/plugins/sqlite-database-integration';
    $dropin_path    = $wp_dir . '/wp-content/db.php';
    $dropin_marker  = '// XWP DI test database drop-in.';

    if ( is_file( $dropin_path ) && ! str_contains( file_get_contents( $dropin_path ), $dropin_marker ) ) {
        fwrite( STDERR, "Refusing to overwrite an existing database drop-in: {$dropin_path}\n" );
        exit( 1 );
    }

    if ( ! is_file( $plugin_dir . '/load.php' )
        || ! is_file( $plugin_dir . '/db.copy' )
        || ! is_file( $plugin_dir . '/wp-includes/sqlite/db.php' )
        || ! str_contains( file_get_contents( $plugin_dir . '/load.php' ), " * Version: {$sqlite_version}\n" )
    ) {
        if ( ! extension_loaded( 'zip' ) ) {
            fwrite( STDERR, "Installing SQLite Database Integration requires the PHP zip extension.\n" );
            exit( 1 );
        }

        $url = "https://downloads.wordpress.org/plugin/sqlite-database-integration.{$sqlite_version}.zip";
        $tmp = tempnam( sys_get_temp_dir(), 'xwpdi-sqlite-' );
        echo "==> downloading SQLite Database Integration {$sqlite_version}\n";

        try {
            if ( ! copy( $url, $tmp ) ) {
                throw new RuntimeException( "Download failed: {$url}" );
            }

            $zip = new ZipArchive();
            if ( true !== $zip->open( $tmp ) ) {
                throw new RuntimeException( 'Could not open SQLite Database Integration archive.' );
            }

            try {
                if ( ! $zip->extractTo( $wp_dir . '/wp-content/plugins' ) ) {
                    throw new RuntimeException( 'Could not extract SQLite Database Integration.' );
                }
            } finally {
                $zip->close();
            }
        } catch ( RuntimeException $e ) {
            fwrite( STDERR, $e->getMessage() . "\n" );
        } finally {
            @unlink( $tmp );
        }

        if ( isset( $e ) ) {
            exit( 1 );
        }
    }

    // Keep the official drop-in, with a guard so MySQL can use the same WP core.
    $dropin = file_get_contents( $plugin_dir . '/db.copy' );
    $dropin = str_replace(
        array( "'{SQLITE_IMPLEMENTATION_FOLDER_PATH}'", '{SQLITE_PLUGIN}' ),
        array( var_export( realpath( $plugin_dir ), true ), 'sqlite-database-integration/load.php' ),
        $dropin
    );
    $dropin = "<?php\n{$dropin_marker}\nif ( ! defined( 'DB_ENGINE' ) || 'sqlite' !== DB_ENGINE ) {\n    return;\n}\n"
        . substr( $dropin, 5 );

    if ( false === file_put_contents( $dropin_path, $dropin ) ) {
        fwrite( STDERR, "Could not write {$dropin_path}\n" );
        exit( 1 );
    }

    $database_path = rtrim( DB_DIR, '/' ) . '/' . DB_FILE;
    echo "==> resetting SQLite test database at `{$database_path}`\n";
    foreach ( array( '', '-wal', '-shm' ) as $suffix ) {
        if ( is_file( $database_path . $suffix ) && ! unlink( $database_path . $suffix ) ) {
            fwrite( STDERR, "Could not remove {$database_path}{$suffix}\n" );
            exit( 1 );
        }
    }
} else {
    [ $host, $port ] = array_pad( explode( ':', DB_HOST, 2 ), 2, '3306' );
    $db_name = DB_NAME;
    echo "==> resetting test database `{$db_name}` on {$host}:{$port}\n";

    try {
        $pdo = new PDO(
            "mysql:host={$host};port={$port}",
            DB_USER,
            DB_PASSWORD,
            array( PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION )
        );
        $pdo->exec( "DROP DATABASE IF EXISTS `{$db_name}`" );
        $pdo->exec( "CREATE DATABASE `{$db_name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" );
    } catch ( PDOException $e ) {
        fwrite( STDERR, "DB reset failed: {$e->getMessage()}\n" );
        fwrite( STDERR, "Is MySQL running? For the optional Docker service, try: composer test:up\n" );
        exit( 1 );
    }
}

echo "==> ready: run `composer test:integration`\n";
