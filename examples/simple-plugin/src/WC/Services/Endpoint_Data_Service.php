<?php
/**
 * Endpoint_Data_Service class file.
 *
 * @package Example
 */

namespace Example\WC\Services;

use WC_Data_Store;
use WC_Product;

/**
 * Process data for the endpoint.
 */
class Endpoint_Data_Service {
    /**
     * Get data.
     *
     * @param  string $search Search string.
     * @return array<int,WC_Product>
     */
    public function get_data( string $search ): array {
        return \array_map(
            'wc_get_product',
            WC_Data_Store::load( 'product' )->search_products( $search, true, false, 1000 ),
        );
    }
}
