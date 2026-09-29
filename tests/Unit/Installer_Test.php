<?php
/** @package XWP\DI\Tests */
namespace Tests\XWP\DI\Unit;

final class Installer_Test extends TestCase {
    protected function set_up(): void {
        parent::set_up();
        require_once dirname( __DIR__ ) . '/bin/install-functions.php';
    }

    public function test_download_rejects_corrupt_archive(): void {
        $source = tempnam( sys_get_temp_dir(), 'di-source-' );
        $destination = tempnam( sys_get_temp_dir(), 'di-destination-' );
        file_put_contents( $source, 'corrupted download' );
        try {
            $this->expectException( \RuntimeException::class );
            $this->expectExceptionMessage( 'Checksum mismatch' );
            xwp_di_download_verified( 'file://' . $source, $destination, hash( 'sha256', 'valid archive' ), 'sha256' );
        } finally {
            unlink( $source );
            unlink( $destination );
        }
    }

    public function test_download_accepts_verified_archive(): void {
        $source = tempnam( sys_get_temp_dir(), 'di-source-' );
        $destination = tempnam( sys_get_temp_dir(), 'di-destination-' );
        file_put_contents( $source, 'valid archive' );
        try {
            xwp_di_download_verified( 'file://' . $source, $destination, hash( 'sha256', 'valid archive' ), 'sha256' );
            self::assertSame( 'valid archive', file_get_contents( $destination ) );
        } finally {
            unlink( $source );
            unlink( $destination );
        }
    }

    public function test_installed_core_version_is_read_without_executing_php(): void {
        $file = tempnam( sys_get_temp_dir(), 'di-version-' );
        file_put_contents( $file, "<?php\n\$wp_version = '6.9.4';\nthrow new Exception();" );
        try {
            self::assertSame( '6.9.4', xwp_di_installed_wp_version( $file ) );
            file_put_contents( $file, "<?php\n\$wp_version = '6.8.3';" );
            self::assertSame( '6.8.3', xwp_di_installed_wp_version( $file ) );
        } finally {
            unlink( $file );
        }
    }
}
