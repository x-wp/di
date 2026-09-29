<?php
/**
 * Download validation for the disposable test installation.
 *
 * @package XWP\DI\Tests
 */

/** Verify a downloaded archive before it can be extracted. */
function xwp_di_download_verified( string $url, string $path, string $checksum, string $algorithm ): void {
    $length = strlen( hash( $algorithm, '' ) );
    if ( ! preg_match( '/^[a-f0-9]{' . $length . '}$/i', $checksum ) ) {
        throw new RuntimeException( "Invalid {$algorithm} checksum for {$url}" );
    }
    if ( ! copy( $url, $path ) ) {
        throw new RuntimeException( "Download failed: {$url}" );
    }
    if ( ! hash_equals( strtolower( $checksum ), hash_file( $algorithm, $path ) ) ) {
        throw new RuntimeException( "Checksum mismatch: {$url}" );
    }
}

/** Read the version without bootstrapping or executing the downloaded core. */
function xwp_di_installed_wp_version( string $file ): ?string {
    if ( ! is_file( $file ) ) {
        return null;
    }
    preg_match( '/\\$wp_version\s*=\s*[\'"]([^\'"]+)[\'"]/', file_get_contents( $file ), $matches );
    return $matches[1] ?? null;
}
