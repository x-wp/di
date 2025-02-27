<?php
/**
 * Data_Processor_Service class file.
 *
 * @package Example
 */

namespace Example\Admin\Services;

use Example\Interfaces\Config_Interface;
use WP_Post;

/**
 * This simulates a complex service which has a comple and slow data fetch during its construction.
 * Constructing this service is expensive and should be avoided if the service is not needed.
 */
class Data_Processor_Service {
    /**
     * Raw data.
     *
     * @var array<int,WP_Post>
     */
    private array $raw_data;

    /**
     * Constructor.
     *
     * @param  Config_Interface $cfg Configuration object.
     */
    public function __construct( Config_Interface $cfg ) {
        $this->raw_data = $this->load_raw_data( $cfg->get( 'opt' ) );
    }

    /**
     * This simulates a complex data fetch from the database
     *
     * @param  int $uid User ID.
     * @return array<int,WP_Post>
     */
    private function load_raw_data( int $uid ): array {
        global $wpdb;

        $posts = $wpdb->get_results(
            $wpdb->prepare(
                'SELECT ID from %i WHERE post_author=%d ORDER BY post_date DESC LIMIT 10000',
                $wpdb->posts,
                $uid,
            ),
        );

        return \array_map( array( WP_Post::class, 'get_instance' ), $posts );
    }

    /**
     * Dummy method to compare and update data.
     *
     * @param  array<string,mixed> $data Data to compare and update.
     * @return bool
     */
    public function compare_and_update( array $data ): bool {
        if ( ! isset( $this->raw_data[ $data['post_id'] ] ) ) {
            return false;
        }

        $post = $this->raw_data[ $data['post_id'] ];

        foreach ( $data['data'] as $key => $value ) {
            $post->$key = $value;
        }

        $res = \wp_update_post( (array) $post );

        return 0 !== $res && ! \is_wp_error( $res );
    }
}
