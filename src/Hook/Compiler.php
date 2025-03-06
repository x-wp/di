<?php
/**
 * Compiler class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

/**
 * Compiles the hook definitions.
 *
 * @template TMod of object
 */
class Compiler {
    /**
     * Constructor.
     *
     * @param Parser<TMod> $parser The parser.
     */
    public function __construct( protected Parser $parser ) {
    }

    /**
     * Compile the hook definitions.
     *
     * @param  string|null $compile_dir Compile directory.
     * @return array<string,mixed>
     */
    public function compile( ?string $compile_dir ): array {
        $filename = \untrailingslashit( $compile_dir ) . '/hook-definition.php';

        return \file_exists( $filename )
            ? $this->read_file( $filename )->get_definition()
            : $this->write_file( $filename )->get_definition();
    }

    /**
     * Read the file.
     *
     * @param  string $filename Filename.
     * @return static
     */
    private function read_file( string $filename ): static {
        $data = include $filename;

        $this->parser->load( $data );

        return $this;
    }

    /**
     * Write the file.
     *
     * @param  string $filename Filename.
     * @return static
     */
    private function write_file( string $filename ): static {
        $data = $this->parser->make( 'complete' )->get_raw();

        $string = \sprintf(
            <<<'PHP'
            <?php
            /**
             * Hook definitions.
             *
             * @package eXtended WordPress
             * @subpackage Dependency Injection
             *
             */

            defined( 'ABSPATH' ) || exit;

            return %s;

            PHP,
            \var_export( $data, true ), //phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_var_export
        );

        // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
        \file_put_contents( $filename, $string );

        return $this;
    }

    /**
     * Get the definition.
     *
     * @return array<string,mixed>
     */
    private function get_definition(): array {
        // \dump( $this->parser->get_parsed() );
        // die;
        return $this->parser->get_parsed();
    }
}
