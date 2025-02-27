<?php
/**
 * Custom_Data_Service class file.
 *
 * @package Example
 */

namespace Example\Admin\Services;

use Example\Interfaces\Config_Interface;

/**
 * This simulates a simple service which depends on a configuration object.
 */
class Custom_Data_Service {
    /**
     * Constructor.
     *
     * @param  Config_Interface $cfg Configuration object.
     */
    public function __construct( private Config_Interface $cfg ) {
    }

    /**
     * Format data.
     *
     * @param  int                 $post_id Post ID.
     * @param  array<string,mixed> $data    Data to format.
     * @return array<string,mixed>
     */
    public function format_data( int $post_id, array $data ): array {
        $data['meta_input']['related']  = $this->cfg->get( 'opt2' );
        $data['meta_input']['related2'] = $post_id;

        return $data;
    }

    /**
     * Get the custom data.
     *
     * @param  int $post_id Post ID.
     * @return array<string,mixed>
     */
    public function get_data( int $post_id ): array {
        return \get_post_meta( $post_id );
    }
}
