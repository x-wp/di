<?php
/**
 * Admin_Ajax_Handler class file.
 *
 * @package Example
 */

namespace Example\Admin\Handlers;

use Example\Admin\Services\Custom_Data_Service;
use Example\Admin\Services\Data_Processor_Service;
use XWP\DI\Decorators\Ajax_Action;
use XWP\DI\Decorators\Ajax_Handler;

/**
 * This defines an ajax handler.
 *
 * Ajax handlers are responsible for handling ajax requests and they only need a container to be defined.
 * They will automatically be registered and initialized only during ajax requests.
 *
 * For maximum performance, avoid injecting complex and resource-intensive services in constructors.
 */
#[Ajax_Handler( container: 'example' )]
class Admin_Ajax_Handler {
    /**
     * Constructor.
     *
     * We inject a lightweight service here since it will be used by almost all ajax requests.
     *
     * @param  Custom_Data_Service $cds Custom data service.
     */
    public function __construct( private Custom_Data_Service $cds ) {
    }

    /**
     * Nonce guard.
     *
     * This method will be fired for every request that doesn't pass the capability check.
     */
    public function nonce_guard(): void {
        \wp_send_json_error( array( 'message' => 'Invalid nonce' ), 403 );
    }

    /**
     * Capability guard.
     *
     * This will be fired only for the `save_custom_data` action.
     */
    public function save_custom_data_cap_guard(): void {
        \wp_send_json_error( array( 'message' => 'Unauthorized' ), 403 );
    }

    /**
     * Example ajax action.
     *
     * Ajax actions use their own decorators which have more options than regular `Filter` and `Action` decorators.
     * This is a callback for `example_save_custom_data` ajax action.
     * Procedural WP equivalent would be `wp_ajax_example_save_custom_data`.
     *
     * @param  int                    $post_id Post ID.
     * @param  array<string,mixed>    $data    Data to process.
     * @param  Data_Processor_Service $dps     Data processor service. Expensive service injected by the container.
     */
    #[Ajax_Action(
        action: 'save_custom_data',              // Action name.
        prefix: 'example',                       // Action prefix. Each action needs this in order to uniquely identify your action and prevent collisions.
        public: false,                           // Is the action public? Setting this to true will enable action for non-logged-in users.
        method: Ajax_Action::AJAX_POST,          // Request method. Determines the manner in which we get the request parameters.
        nonce: 'security',                       // Nonce param. If set to false nonce won't be checked. If set to true, default params will be checked ('_ajax_nonce', '_wpnonce').
        cap: array( 'edit_posts' => 'post_id' ), // Capability required to perform the action. Can be a string, or an array with capability as the key, and request parameters as the value.
        vars: array(                             // Request variables to fetch and pass to the callback.
            'data'    => array(),
            'post_id' => 0,
        ),
        params: array(                           // Parameters to pass to the callback. Will be resolved by the container.
            Data_Processor_Service::class,       // We inject the 'expensive' service here.
        ),
    )]
    public function save_custom_data( int $post_id, array $data, Data_Processor_Service $dps ): void {
        $custom = $this->cds->format_data( $post_id, $data );

        $dps->compare_and_update( $custom )
            ? \wp_send_json_success( array( 'message' => 'Data updated' ), 201 )
            : \wp_send_json_error( array( 'message' => 'Data not updated' ), 500 );
    }

    /**
     * Public action example.
     *
     * We do not use the capability guard here, since this action is public.
     *
     * @param  int $post_id Post ID.
     */
    #[Ajax_Action(
        action: 'get_custom_data',
        prefix: 'example',
        public: true,
        method: Ajax_Action::AJAX_GET,
        nonce: 'security',
        vars: array( 'post_id' => 0 ),
    )]
    public function get_data( int $post_id ): void {
        \wp_send_json_success( $this->cds->get_data( $post_id ), 200 );
    }
}
