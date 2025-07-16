<?php
/**
 * ID_Factory class file.
 *
 * @package eXtended WordPress
 * @subpackage Dependency Injection
 */

namespace XWP\DI\Hook;

use XWP\DI\Enums\IdFactoryMode;

/**
 * Generates unique IDs for the application components.
 */
class ID_Factory {
    /**
     * Current mode for ID generation.
     *
     * @var IdFactoryMode
     */
    public readonly IdFactoryMode $mode;

    /**
     * Registry of IDs.
     *
     * @var array<string,boolean>
     */
    private array $registry = array();

    /**
     * Constructor for ID_Factory.
     *
     * @param bool $snapshot If true, use deterministic mode; otherwise, use random mode.
     */
    public function __construct( bool $snapshot = false ) {
        $this->mode = $snapshot
            ? IdFactoryMode::Deterministic
            : IdFactoryMode::Random;
    }

    /**
     * Set the mode for ID generation.
     *
     * @param IdFactoryMode $mode Mode to set.
     * @return self
     */
    public function with_mode( IdFactoryMode $mode ): self {
        $this->mode = $mode;

        return $this;
    }

    /**
     * Clear the deterministic ID registry.
     *
     * @return self
     */
    public function clear(): self {
        $this->registry = array();

        return $this;
    }

    /**
     * Get an ID based on the current mode.
     *
     * @param  string $key Key for ID generation.
     * @return string
     */
    public function get( string $key = '' ): string {
        return IdFactoryMode::Deterministic === $this->mode
            ? $this->deterministic( $key )
            : $this->random();
    }

    /**
     * Generate a random ID.
     *
     * @return string
     */
    private function random(): string {
        return \strtolower( \wp_generate_password( 21, false, false ) );
    }

    /**
     * Generate a deterministic ID for a given key.
     *
     * @param string $key Key for ID.
     * @param int    $inc Increment counter for collision resolution.
     * @return string
     */
    private function deterministic( string $key, int $inc = 0 ): string {
        $str = $key . ( $inc ? '_' . $inc : '' );
        $id  = $this->hash_code( $str );

        if ( isset( $this->registry[ $id ] ) ) {
            return $this->deterministic( $key, $inc + 1 );
        }

        $this->registry[ $id ] = true;
        return $id;
    }

    /**
     * Compute a hash code for a string similar to Java's String.hashCode().
     *
     * @param  string $str String to hash.
     * @return string
     */
    private function hash_code( string $str ): string {
        $h   = 0;
        $len = \strlen( $str );

        for ( $i = 0; $i < $len; $i++ ) {
            $h = ( $h * 31 + \ord( $str[ $i ] ) ) & 0xFFFFFFFF;
        }

        // Convert to signed 32-bit integer.
        if ( $h & 0x80000000 ) {
            $h = -( ( ~$h & 0xFFFFFFFF ) + 1 );
        }

        return \strtolower( (string) $h );
    }
}
